<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Webhook_processor
{
    private $CI;

    public function __construct()
    {
        $this->CI = &get_instance();

        // Load required models
        $this->CI->load->model('User_model');
        $this->CI->load->model('Strategy_model');
        $this->CI->load->model('Api_key_model');
        $this->CI->load->model('Trade_model');
        $this->CI->load->model('Log_model');

        // Load BingX API library
        $this->CI->load->library('BingxApi');
    }

   public function process_webhook_data($json_data)
    {
        // Decode JSON data
        $data = json_decode($json_data);

        // Verify data
        if (
            !$data || !isset($data->strategy_id) || !isset($data->ticker) ||
            !isset($data->timeframe) || !isset($data->action)
        ) {
            $this->_log_webhook_error('Missing required fields in webhook data', $json_data);
            return 'Missing required fields';
        }

        // If user_id is not in JSON, use current user (for simulations)
        if (!isset($data->user_id) && $this->CI->session->userdata('user_id')) {
            $data->user_id = $this->CI->session->userdata('user_id');
        }

        // Verify user_id exists
        if (!isset($data->user_id)) {
            $this->_log_webhook_error('Missing user_id in webhook data', $json_data);
            return 'Missing user_id';
        }

        // Get user
        $user = $this->CI->User_model->get_user_by_id($data->user_id);
        if (!$user) {
            $this->_log_webhook_error('User not found: ' . $data->user_id, $json_data);
            return 'User not found';
        }

        // Get strategy
        $strategy = $this->CI->Strategy_model->get_strategy_by_strategy_id($data->user_id, $data->strategy_id);
        if (!$strategy) {
            $this->_log_webhook_error('Strategy not found: ' . $data->strategy_id . ' for user ' . $data->user_id, $json_data);
            return 'Strategy not found: ' . $data->strategy_id;
        }

        // Check if strategy is active
        if (!$strategy->active) {
            $this->_log_webhook_error('Strategy is inactive: ' . $data->strategy_id, $json_data);
            return 'Strategy is inactive';
        }

        // Determine trade environment (sandbox or production)
        $environment = 'production';
        if (isset($data->environment) && $data->environment == 'sandbox') {
            // Only futures can use sandbox
            if ($strategy->type == 'futures') {
                $environment = 'sandbox';
            }
        }

        // Get API key for this user
        $api_key = $this->CI->Api_key_model->get_api_key($data->user_id);
        if (!$api_key) {
            $this->_log_webhook_error('API key not configured for user: ' . $data->user_id, $json_data);
            return 'API key not configured for this user';
        }

        // Set environment in BingX API library
        $this->CI->bingxapi->set_environment($environment);

        // Extract data fields
        $quantity = isset($data->quantity) ? $data->quantity : 0.001;
        $leverage = isset($data->leverage) ? $data->leverage : 1;
        $take_profit = isset($data->take_profit) ? $data->take_profit : null;
        $stop_loss = isset($data->stop_loss) ? $data->stop_loss : null;
        $position_id = isset($data->position_id) ? $data->position_id : null;
        $trade_type = $strategy->type;

        // NEW LOGIC: Determine if we need to open or close based on existing positions
        $trade_to_close = null;
        $operation = 'OPEN'; // Default to opening

        // 1. Search by position_id + strategy_id if available
        if ($position_id) {
            $trade_to_close = $this->CI->Trade_model->find_trade_by_position_and_strategy(
                $position_id, 
                $strategy->id, 
                $environment
            );
            
            if ($trade_to_close) {
                $operation = 'CLOSE';
                $this->_log_webhook_debug('Found trade to close by position_id', json_encode([
                    'trade_id' => $trade_to_close->id,
                    'position_id' => $trade_to_close->position_id,
                    'side' => $trade_to_close->side
                ]));
            }
        }

        // 2. FALLBACK: Search for position with OPPOSITE side to close
        if (!$trade_to_close) {
            // 🔥 Si viene SELL -> buscar posiciones BUY abiertas para cerrar
            // 🔥 Si viene BUY -> buscar posiciones SELL abiertas para cerrar
            
            $trade_to_close = $this->CI->Trade_model->find_trade_for_fallback(
                $data->user_id,
                $strategy->id,
                $data->ticker,
                $environment,
                $quantity,
                $data->action  // El método internamente busca el side opuesto
            );
            
            if ($trade_to_close) {
                $operation = 'CLOSE';
                
                $this->_log_webhook_debug('Found trade to close by fallback (opposite side)', json_encode([
                    'trade_id' => $trade_to_close->id,
                    'found_side' => $trade_to_close->side,
                    'signal_action' => $data->action,
                    'search_criteria' => 'fallback_opposite_side'
                ]));
            }
        }

        // Log parameters before processing
        $this->_log_webhook_debug('Processing order', json_encode([
            'operation' => $operation,
            'action' => $data->action,
            'ticker' => $data->ticker,
            'quantity' => $quantity,
            'leverage' => $leverage,
            'strategy_type' => $trade_type,
            'environment' => $environment,
            'position_id' => $position_id,
            'trade_to_close_id' => $trade_to_close ? $trade_to_close->id : null
        ]));

        // Execute the operation
        if ($operation == 'CLOSE') {
            return $this->_close_position($trade_to_close, $api_key, $data, $strategy, $environment, $json_data);
        } else {
            return $this->_open_position($api_key, $data, $strategy, $environment, $quantity, $leverage, $take_profit, $stop_loss, $position_id, $trade_type, $json_data);
        }
    }

    /**
     * Open a new position
     */
    private function _open_position($api_key, $data, $strategy, $environment, $quantity, $leverage, $take_profit, $stop_loss, $position_id, $trade_type, $json_data)
    {
        // Execute order
        if ($trade_type == 'futures') {
            // Open futures position
            $result = $this->CI->bingxapi->open_futures_position(
                $api_key,
                $data->ticker,
                $data->action,
                $quantity,
                $take_profit,
                $stop_loss
            );
        } else {
            // Spot trading doesn't support take profit/stop loss through API
            $result = $this->CI->bingxapi->open_spot_position($api_key, $data->ticker, $data->action, $quantity);
        }

        if (!$result) {
            $error = $this->CI->bingxapi->get_last_error();
            $this->_log_webhook_error('Failed to execute order: ' . $error, $json_data);
            return 'Failed to execute order: ' . $error;
        }

        if (isset($result->orderId) && isset($result->price)) {
            // Save trade to database
            $trade_data = array(
                'user_id' => $data->user_id,
                'strategy_id' => $strategy->id,
                'order_id' => $result->orderId,
                'symbol' => $data->ticker,
                'timeframe' => $data->timeframe,
                'side' => $data->action,
                'trade_type' => $trade_type,
                'environment' => $environment,
                'quantity' => $quantity,
                'entry_price' => $result->price,
                'current_price' => $result->price,
                'leverage' => $trade_type == 'futures' ? $leverage : 1,
                'take_profit' => $take_profit,
                'stop_loss' => $stop_loss,
                'pnl' => 0,
                'status' => 'open',
                'position_id' => $position_id,
                'webhook_data' => $json_data
            );

            $trade_id = $this->CI->Trade_model->add_trade($trade_data);

            // Log action
            $log_data = array(
                'user_id' => $data->user_id,
                'action' => 'open_trade',
                'description' => 'Opened ' . $trade_type . ' ' . $data->action . ' position for ' . $data->ticker .
                    ' via webhook (ID: ' . $trade_id . ', Strategy: ' . $strategy->name . ', Environment: ' . $environment .
                    ', Position ID: ' . $position_id . ')'
            );
            $this->CI->Log_model->add_log($log_data);

            return true;
        } else {
            $this->_log_webhook_error('Invalid order response format', $json_data);
            return 'Invalid order response format';
        }
    }

    /**
     * Close an existing position
     */
    private function _close_position($trade_to_close, $api_key, $data, $strategy, $environment, $json_data)
    {
        // Determine close quantity
        $close_quantity = isset($data->quantity) ? $data->quantity : $trade_to_close->quantity;
        
        // Validate close quantity
        if ($close_quantity > $trade_to_close->quantity) {
            $this->_log_webhook_error('Close quantity (' . $close_quantity . 
                ') exceeds open quantity (' . $trade_to_close->quantity . ')', $json_data);
            return 'Close quantity exceeds open quantity';
        }

        // Close position using the trade's original side
        if ($trade_to_close->trade_type == 'futures') {
            $result = $this->CI->bingxapi->close_futures_position(
                $api_key,
                $trade_to_close->symbol,
                $trade_to_close->side,  // Use original side (BingX handles the opposite internally)
                $close_quantity
            );
        } else {
            $result = $this->CI->bingxapi->close_spot_position(
                $api_key,
                $trade_to_close->symbol,
                $trade_to_close->side,  // Use original side
                $close_quantity
            );
        }

        if (!$result) {
            $error = $this->CI->bingxapi->get_last_error();
            $this->_log_webhook_error('Failed to close position: ' . $error, $json_data);
            return 'Failed to close position: ' . $error;
        }

        if (isset($result->price)) {
            // Calculate PNL
            $exit_price = $result->price;

            if ($trade_to_close->side == 'BUY') {
                $pnl = ($exit_price - $trade_to_close->entry_price) * $close_quantity;
            } else {
                $pnl = ($trade_to_close->entry_price - $exit_price) * $close_quantity;
            }

            // Check if this is a partial close
            if ($close_quantity < $trade_to_close->quantity) {
                // Partial close - update the trade
                $new_quantity = $trade_to_close->quantity - $close_quantity;
                
                $this->CI->Trade_model->update_trade($trade_to_close->id, array(
                    'quantity' => $new_quantity,
                    'updated_at' => date('Y-m-d H:i:s')
                ));

                $log_data = array(
                    'user_id' => $data->user_id,
                    'action' => 'partial_close_trade',
                    'description' => 'Partially closed ' . $close_quantity . ' of ' . $trade_to_close->quantity . 
                        ' ' . $trade_to_close->symbol . ' with PNL: ' . number_format($pnl, 2) . 
                        ' via webhook (Strategy: ' . $strategy->name . 
                        ', Environment: ' . $environment . ', Position ID: ' . $trade_to_close->position_id . 
                        '. New quantity: ' . $new_quantity . ')'
                );
            } else {
                // Full close - mark trade as closed
                $this->CI->Trade_model->close_trade($trade_to_close->id, $exit_price, $pnl);

                $log_data = array(
                    'user_id' => $data->user_id,
                    'action' => 'close_trade',
                    'description' => 'Closed trade for ' . $trade_to_close->symbol . ' with PNL: ' .
                        number_format($pnl, 2) . ' via webhook (Strategy: ' . $strategy->name .
                        ', Environment: ' . $environment . ', Position ID: ' . $trade_to_close->position_id . ')'
                );
            }
            
            $this->CI->Log_model->add_log($log_data);
            return true;
        } else {
            $this->_log_webhook_error('Invalid close response format', $json_data);
            return 'Invalid close response format';
        }
    }

    private function _log_webhook_debug($message, $data)
    {
        $log_data = array(
            'user_id' => $this->CI->session->userdata('user_id'),
            'action' => 'webhook_debug',
            'description' => $message . '. Data: ' . $data
        );
        $this->CI->Log_model->add_log($log_data);
    }

    private function _log_webhook_error($error, $data)
    {
        $log_data = array(
            'user_id' => $this->CI->session->userdata('user_id'),
            'action' => 'webhook_error',
            'description' => $error . '. Data: ' . $data
        );
        $this->CI->Log_model->add_log($log_data);
    }
}