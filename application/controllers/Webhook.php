<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Webhook extends CI_Controller
{

    public function __construct()
    {
        parent::__construct();

        // Load BingX API library
        $this->load->library('BingxApi');
    }

    public function tradingview()
    {
        // Get JSON data from TradingView webhook
        $json_data = file_get_contents('php://input');

        // Log the raw webhook data for debugging
        $this->_log_webhook_debug('Received webhook', $json_data);

        // Decode JSON to PHP array
        $data = json_decode($json_data, true);

        // Check if JSON was valid and contains the action field
        if ($data && isset($data['action'])) {
            // Convert TradingView actions to your format
            switch ($data['action']) {
                case 'buy':
                    $data['action'] = 'BUY';                    
                    break;
                case 'short':
                    $data['action'] = 'SELL';                    
                    break;
                case 'sell':
                case 'cover':
                    $data['action'] = 'CLOSE';
                    break;
            }
            $comment = $data['position_id'] ?? '';
            
            if (strpos($comment, '|') !== false) {
                $parts = explode('|', $comment);
                $data['position_id'] = end($parts);
            }

            // Log the converted data
            $this->_log_webhook_debug('Converted actions', json_encode($data));

            // Re-encode to JSON for process_webhook_data
            $json_data = json_encode($data);
        } else {
            $this->_log_webhook_debug('Warning', 'Invalid JSON or missing action field');
        }

        // Process webhook data with converted actions
        $result = $this->process_webhook_data($json_data);

        // Send response
        if ($result === true) {
            $this->_send_response(200, 'Order executed successfully');
        } else {
            $this->_send_response(400, $result);
        }
    }

    public function simulate()
    {
        // Check if request is from our system
        if (!$this->input->post('simulate_data')) {
            $this->session->set_flashdata('error', 'Error simulating order: Missing data');
            redirect('dashboard');
            return;
        }

        // Get JSON data from form post
        $json_data = $this->input->post('simulate_data');

        // Log the raw simulation data for debugging
        $this->_log_webhook_debug('Simulating order with raw data', $json_data);

        // Process webhook data
        $result = $this->process_webhook_data($json_data);

        // Send response to browser
        if ($result === true) {
            $this->session->set_flashdata('success', 'Order simulated successfully');
        } else {
            $this->session->set_flashdata('error', 'Error simulating order: ' . $result);
        }

        redirect('dashboard');
    }

    // Webhook debug logger 
    private function _log_webhook_debug($message, $data)
    {
        $log_data = array(
            'user_id' => $this->session->userdata('user_id'),
            'action' => 'webhook_debug',
            'description' => $message . '. Data: ' . $data
        );
        $this->Log_model->add_log($log_data);
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
        $data->lvg_side = $data->action == 'BUY' ? 'LONG' : ($data->action == 'SELL' ? 'SHORT' : null);

        // If user_id is not in JSON, use current user (for simulations)
        if (!isset($data->user_id) && $this->session->userdata('user_id')) {
            $data->user_id = $this->session->userdata('user_id');
        }

        // Verify user_id exists
        if (!isset($data->user_id)) {
            $this->_log_webhook_error('Missing user_id in webhook data', $json_data);
            return 'Missing user_id';
        }

        // Get user
        $user = $this->User_model->get_user_by_id($data->user_id);
        if (!$user) {
            $this->_log_webhook_error('User not found: ' . $data->user_id, $json_data);
            return 'User not found';
        }

        // Get strategy
        $strategy = $this->Strategy_model->get_strategy_by_strategy_id($data->user_id, $data->strategy_id);
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
        $api_key = $this->Api_key_model->get_api_key($data->user_id);
        if (!$api_key) {
            $this->_log_webhook_error('API key not configured for user: ' . $data->user_id, $json_data);
            return 'API key not configured for this user';
        }

        // Set environment in BingX API library
        $this->bingxapi->set_environment($environment);

        // Extract take profit and stop loss if available
        $take_profit = isset($data->take_profit) ? $data->take_profit : null;
        $stop_loss = isset($data->stop_loss) ? $data->stop_loss : null;

        // Extract position_id if available (for identifying which position to close)
        $position_id = isset($data->position_id) ? $data->position_id : null;

        // Log parameters before sending to API
        $this->_log_webhook_debug('Preparing order parameters', json_encode([
            'ticker' => $data->ticker,
            'formatted_ticker' => $this->bingxapi->format_symbol($data->ticker),
            'action' => $data->action,
            'quantity' => isset($data->quantity) ? $data->quantity : 0.01,
            'strategy_type' => $strategy->type,
            'environment' => $environment,
            'take_profit' => $take_profit,
            'stop_loss' => $stop_loss,
            'position_id' => $position_id
        ]));

        // Determine trade type (spot or futures)
        $trade_type = $strategy->type;

        // Process webhook action
        switch ($data->action) {
            case 'BUY':
            case 'SELL':
                /* 
                // COMMENTED OUT: Code to close opposite position in spot trading
                if ($trade_type == 'spot') {
                    // Verificar si hay un trade abierto opuesto para cerrar primero
                    $opposite_side = $data->action === 'BUY' ? 'SELL' : 'BUY';
                    $trades = $this->Trade_model->get_all_trades($data->user_id, 'open');
                    $opposite_trade = null;

                    // Buscar un trade abierto con dirección opuesta para la misma estrategia/símbolo/timeframe
                    foreach ($trades as $trade) {
                        if (
                            $trade->symbol == $data->ticker &&
                            $trade->strategy_id == $strategy->id &&
                            $trade->side == $opposite_side &&
                            $trade->timeframe == $data->timeframe &&
                            $trade->environment == $environment
                        ) {
                            $opposite_trade = $trade;
                            break;
                        }
                    }

                    // Si existe un trade opuesto, cerrarlo primero
                    if ($opposite_trade) {
                        $this->_log_webhook_debug(
                            "Closing opposite direction trade before opening new position",
                            json_encode($opposite_trade)
                        );
                        $result = $this->bingxapi->close_spot_position(
                            $api_key,
                            $opposite_trade->symbol,
                            $opposite_trade->side,
                            $opposite_trade->quantity
                        );

                        if ($result && isset($result->price)) {
                            // Calcular PNL
                            $exit_price = $result->price;

                            if ($opposite_trade->side == 'BUY') {
                                $pnl = ($exit_price - $opposite_trade->entry_price) *
                                    $opposite_trade->quantity * $opposite_trade->leverage;
                            } else {
                                $pnl = ($opposite_trade->entry_price - $exit_price) *
                                    $opposite_trade->quantity * $opposite_trade->leverage;
                            }

                            // Actualizar el trade
                            $this->Trade_model->close_trade($opposite_trade->id, $exit_price, $pnl);

                            // Log de cierre
                            $log_data = array(
                                'user_id' => $data->user_id,
                                'action' => 'close_trade',
                                'description' => 'Auto-closed opposite trade for ' . $opposite_trade->symbol .
                                    ' with PNL: ' . number_format($pnl, 2) .
                                    ' before opening new position'
                            );
                            $this->Log_model->add_log($log_data);
                        }
                    }
                }
                */

                // Get quantity and leverage
                $quantity = isset($data->quantity) ? $data->quantity : 0.001; // Default quantity                

                // Execute order
                if ($trade_type == 'futures') {
                    // Set leverage first if needed
                    if ($data->leverage != "") {
                        $result = $this->bingxapi->set_futures_leverage($api_key, $data->ticker, $data->leverage, $data->lvg_side);
                        if (!$result) {
                            $error = $this->bingxapi->get_last_error();
                            $this->_log_webhook_error('Failed to set leverage: ' . $error, $json_data);
                            return 'Failed to set leverage: ' . $error;
                        }
                    }

                    // Open futures position with take profit and stop loss
                    $result = $this->bingxapi->open_futures_position(
                        $api_key,
                        $data->ticker,
                        $data->action,
                        $quantity,
                        $take_profit,
                        $stop_loss
                    );
                } else {
                    // Spot trading doesn't support take profit/stop loss through API
                    $result = $this->bingxapi->open_spot_position($api_key, $data->ticker, $data->action, $quantity);
                }

                if (!$result) {
                    $error = $this->bingxapi->get_last_error();
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
                        'pnl' => 0, // Initial PNL is 0
                        'status' => 'open',
                        'position_id' => $position_id, // Store position_id from TradingView
                        'webhook_data' => $json_data
                    );

                    $trade_id = $this->Trade_model->add_trade($trade_data);

                    // Log action
                    $log_data = array(
                        'user_id' => $data->user_id,
                        'action' => 'open_trade',
                        'description' => 'Opened ' . $trade_type . ' ' . $data->action . ' position for ' . $data->ticker .
                            ' via webhook (Strategy: ' . $strategy->name . ', Environment: ' . $environment .
                            ', Position ID: ' . $position_id . ')'
                    );
                    $this->Log_model->add_log($log_data);

                    return true;
                } else {
                    $this->_log_webhook_error('Invalid order response format', $json_data);
                    return 'Invalid order response format';
                }
                break;

            case 'CLOSE':
                // Find open trade by comprehensive matching criteria
                $trades = $this->Trade_model->get_all_trades($data->user_id, 'open');
                $trade_to_close = null;

                // Log the search criteria
                $this->_log_webhook_debug('Position close criteria', json_encode([
                    'position_id' => $position_id,
                    'ticker' => $data->ticker,
                    'timeframe' => $data->timeframe,
                    'side' => isset($data->side) ? $data->side : 'not specified',
                    'strategy_id' => $strategy->id
                ]));

                if ($position_id) {
                    // Find trade by matching position_id AND symbol/timeframe for safety
                    foreach ($trades as $trade) {
                        if (
                            $trade->position_id == $position_id &&
                            $trade->symbol == $data->ticker &&
                            $trade->timeframe == $data->timeframe &&
                            $trade->environment == $environment
                        ) {
                            // For futures, also match the side (BUY/SELL) when specified
                            if ($trade_type == 'futures' && isset($data->side)) {
                                if ($trade->side == $data->side) {
                                    $trade_to_close = $trade;
                                    break;
                                }
                            } else {
                                $trade_to_close = $trade;
                                break;
                            }
                        }
                    }
                } else {
                    // Fallback to traditional search if no position_id provided
                    foreach ($trades as $trade) {
                        if (
                            $trade->symbol == $data->ticker &&
                            $trade->strategy_id == $strategy->id &&
                            $trade->timeframe == $data->timeframe &&
                            $trade->environment == $environment
                        ) {
                            // For futures, we need to match the side as well in case of hedged positions
                            if ($trade_type == 'futures' && isset($data->side)) {
                                if ($trade->side == $data->side) {
                                    $trade_to_close = $trade;
                                    break;
                                }
                            } else {
                                $trade_to_close = $trade;
                                break;
                            }
                        }
                    }
                }

                if (!$trade_to_close) {
                    $this->_log_webhook_error(
                        'No open trade found to close matching criteria: ' .
                            (isset($position_id) ? 'Position ID: ' . $position_id : 'Symbol/Strategy'),
                        $json_data
                    );
                    return 'No open trade found to close';
                }

                // Close position
                if ($trade_to_close->trade_type == 'futures') {
                    $result = $this->bingxapi->close_futures_position(
                        $api_key,
                        $trade_to_close->symbol,
                        $trade_to_close->side,
                        $trade_to_close->quantity
                    );
                } else {
                    $result = $this->bingxapi->close_spot_position(
                        $api_key,
                        $trade_to_close->symbol,
                        $trade_to_close->side,
                        $trade_to_close->quantity
                    );
                }

                if (!$result) {
                    $error = $this->bingxapi->get_last_error();
                    $this->_log_webhook_error('Failed to close position: ' . $error, $json_data);
                    return 'Failed to close position: ' . $error;
                }

                if (isset($result->price)) {
                    // Calculate PNL
                    $exit_price = $result->price;

                    if ($trade_to_close->side == 'BUY') {
                        $pnl = ($exit_price - $trade_to_close->entry_price) * $trade_to_close->quantity * $trade_to_close->leverage;
                    } else {
                        $pnl = ($trade_to_close->entry_price - $exit_price) * $trade_to_close->quantity * $trade_to_close->leverage;
                    }

                    // Update trade
                    $this->Trade_model->close_trade($trade_to_close->id, $exit_price, $pnl);

                    // Log action
                    $log_data = array(
                        'user_id' => $data->user_id,
                        'action' => 'close_trade',
                        'description' => 'Closed trade for ' . $trade_to_close->symbol . ' with PNL: ' .
                            number_format($pnl, 2) . ' via webhook (Strategy: ' . $strategy->name .
                            ', Environment: ' . $environment . ', Position ID: ' . $trade_to_close->position_id . ')'
                    );
                    $this->Log_model->add_log($log_data);

                    return true;
                } else {
                    $this->_log_webhook_error('Invalid close response format', $json_data);
                    return 'Invalid close response format';
                }
                break;

            default:
                $this->_log_webhook_error('Invalid action: ' . $data->action, $json_data);
                return 'Invalid action';
        }
    }

    private function _log_webhook_error($error, $data)
    {
        $log_data = array(
            'user_id' => $this->session->userdata('user_id'),
            'action' => 'webhook_error',
            'description' => $error . '. Data: ' . $data
        );
        $this->Log_model->add_log($log_data);
    }

    private function _send_response($status_code, $message, $data = null)
    {
        $response = array(
            'status' => $status_code,
            'message' => $message
        );

        if ($data) {
            $response['data'] = $data;
        }

        $this->output
            ->set_status_header($status_code)
            ->set_content_type('application/json')
            ->set_output(json_encode($response));
    }
}
