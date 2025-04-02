<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Webhook extends CI_Controller {
    
    public function __construct() {
        parent::__construct();
    }
    
    public function tradingview() {
        // Get JSON data from TradingView webhook
        $json_data = file_get_contents('php://input');
        $data = json_decode($json_data);
        
        // Verify data
        if (!$data || !isset($data->user_id) || !isset($data->strategy_id) || !isset($data->ticker) || 
            !isset($data->timeframe) || !isset($data->action) || !isset($data->environment)) {
            $this->_log_webhook_error('Missing required fields in webhook data', $json_data);
            return $this->_send_response(400, 'Missing required fields');
        }
        
        // Get user
        $user = $this->User_model->get_user_by_id($data->user_id);
        if (!$user) {
            $this->_log_webhook_error('User not found: ' . $data->user_id, $json_data);
            return $this->_send_response(404, 'User not found');
        }
        
        // Get strategy
        $strategy = $this->Strategy_model->get_strategy_by_strategy_id($data->user_id, $data->strategy_id);
        if (!$strategy) {
            $this->_log_webhook_error('Strategy not found: ' . $data->strategy_id, $json_data);
            return $this->_send_response(404, 'Strategy not found');
        }
        
        // Check if strategy is active
        if (!$strategy->active) {
            $this->_log_webhook_error('Strategy is inactive: ' . $data->strategy_id, $json_data);
            return $this->_send_response(400, 'Strategy is inactive');
        }
        
        // Get API key for this environment
        $api_key = $this->Api_key_model->get_api_key($data->user_id, $data->environment);
        if (!$api_key) {
            $this->_log_webhook_error('API key not configured for environment: ' . $data->environment, $json_data);
            return $this->_send_response(400, 'API key not configured for this environment');
        }
        
        // Load BingX API library
        $this->load->library('BingxApi');
        
        // Determine trade type (spot or futures)
        $trade_type = $strategy->type;
        
        // Process webhook action
        switch ($data->action) {
            case 'BUY':
            case 'SELL':
                // Get quantity and leverage
                $quantity = isset($data->quantity) ? $data->quantity : 0.01; // Default quantity
                $leverage = isset($data->leverage) ? $data->leverage : 1; // Default leverage
                
                // Execute order
                if ($trade_type == 'futures') {
                    // Set leverage first if needed
                    if ($leverage > 1) {
                        $this->bingxapi->set_futures_leverage($api_key, $data->ticker, $leverage);
                    }
                    
                    $result = $this->bingxapi->open_futures_position($api_key, $data->ticker, $data->action, $quantity);
                } else {
                    $result = $this->bingxapi->open_spot_position($api_key, $data->ticker, $data->action, $quantity);
                }
                
                if ($result && isset($result->orderId) && isset($result->price)) {
                    // Save trade to database
                    $trade_data = array(
                        'user_id' => $data->user_id,
                        'strategy_id' => $strategy->id,
                        'order_id' => $result->orderId,
                        'symbol' => $data->ticker,
                        'timeframe' => $data->timeframe,
                        'side' => $data->action,
                        'trade_type' => $trade_type,
                        'quantity' => $quantity,
                        'entry_price' => $result->price,
                        'leverage' => $trade_type == 'futures' ? $leverage : 1,
                        'status' => 'open',
                        'environment' => $data->environment,
                        'webhook_data' => $json_data
                    );
                    
                    $trade_id = $this->Trade_model->add_trade($trade_data);
                    
                    // Log action
                    $log_data = array(
                        'user_id' => $data->user_id,
                        'action' => 'open_trade',
                        'description' => 'Opened ' . $trade_type . ' ' . $data->action . ' position for ' . $data->ticker . 
                                       ' via webhook (Strategy: ' . $strategy->name . ')'
                    );
                    $this->Log_model->add_log($log_data);
                    
                    return $this->_send_response(200, 'Order executed successfully', array(
                        'trade_id' => $trade_id,
                        'order_id' => $result->orderId,
                        'price' => $result->price
                    ));
                } else {
                    $this->_log_webhook_error('Failed to execute order', $json_data);
                    return $this->_send_response(500, 'Failed to execute order');
                }
                break;
                
            case 'CLOSE':
                // Find open trade by symbol and strategy
                $trades = $this->Trade_model->get_all_trades($data->user_id, 'open', $data->environment);
                $trade_to_close = null;
                
                foreach ($trades as $trade) {
                    if ($trade->symbol == $data->ticker && $trade->strategy_id == $strategy->id) {
                        $trade_to_close = $trade;
                        break;
                    }
                }
                
                if (!$trade_to_close) {
                    $this->_log_webhook_error('No open trade found to close', $json_data);
                    return $this->_send_response(404, 'No open trade found to close');
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
                
                if ($result && isset($result->price)) {
                    // Calculate PNL
                    $exit_price = $result->price;
                    
                    if ($trade_to_close->side == 'BUY') {
                        $pnl = ($exit_price - $trade_to_close->entry_price) * $trade_to_close->quantity;
                    } else {
                        $pnl = ($trade_to_close->entry_price - $exit_price) * $trade_to_close->quantity;
                    }
                    
                    // Update trade
                    $this->Trade_model->close_trade($trade_to_close->id, $exit_price, $pnl);
                    
                    // Log action
                    $log_data = array(
                        'user_id' => $data->user_id,
                        'action' => 'close_trade',
                        'description' => 'Closed trade for ' . $trade_to_close->symbol . ' with PNL: ' . 
                                       number_format($pnl, 2) . ' via webhook (Strategy: ' . $strategy->name . ')'
                    );
                    $this->Log_model->add_log($log_data);
                    
                    return $this->_send_response(200, 'Position closed successfully', array(
                        'trade_id' => $trade_to_close->id,
                        'exit_price' => $exit_price,
                        'pnl' => $pnl
                    ));
                } else {
                    $this->_log_webhook_error('Failed to close position', $json_data);
                    return $this->_send_response(500, 'Failed to close position');
                }
                break;
                
            default:
                $this->_log_webhook_error('Invalid action: ' . $data->action, $json_data);
                return $this->_send_response(400, 'Invalid action');
        }
    }
    
    private function _log_webhook_error($error, $data) {
        $log_data = array(
            'user_id' => null,
            'action' => 'webhook_error',
            'description' => $error . '. Data: ' . $data
        );
        $this->Log_model->add_log($log_data);
    }
    
    private function _send_response($status_code, $message, $data = null) {
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