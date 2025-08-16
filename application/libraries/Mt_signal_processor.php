<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Mt_signal_processor
{
    private $CI;

    public function __construct()
    {
        $this->CI = &get_instance();

        // Load required models
        $this->CI->load->model('User_model');
        $this->CI->load->model('Strategy_model');
        $this->CI->load->model('Mt_signal_model');
        $this->CI->load->model('Log_model');
    }

    /**
     * Process MetaTrader webhook signal
     * 
     * @param string $json_data JSON string with signal data
     * @return mixed true on success, error message string on failure
     */
    public function process_signal($json_data)
    {
        $data = json_decode($json_data);

        // Validate basic structure
        if (!$data || !isset($data->strategy_id) || !isset($data->action)) {
            $this->_log_error('Missing required fields in MT signal data', $json_data);
            return 'Missing required fields';
        }

        // Verify user_id exists
        if (!isset($data->user_id)) {
            $this->_log_error('Missing user_id in MT signal data', $json_data);
            return 'Missing user_id';
        }

        // Get user
        $user = $this->CI->User_model->get_user_by_id($data->user_id);
        if (!$user) {
            $this->_log_error('User not found: ' . $data->user_id, $json_data);
            return 'User not found';
        }

        // Get strategy
        $strategy = $this->CI->Strategy_model->get_strategy_by_strategy_id($data->user_id, $data->strategy_id);
        if (!$strategy) {
            $this->_log_error('Strategy not found: ' . $data->strategy_id . ' for user ' . $data->user_id, $json_data);
            return 'Strategy not found: ' . $data->strategy_id;
        }

        // Check if strategy is active
        if (!$strategy->active) {
            $this->_log_error('Strategy is inactive: ' . $data->strategy_id, $json_data);
            return 'Strategy is inactive';
        }

        // Verify that the strategy is for MetaTrader
        if ($strategy->platform !== 'metatrader') {
            $this->_log_error('Strategy is not configured for MetaTrader: ' . $data->strategy_id, $json_data);
            return 'Strategy is not configured for MetaTrader';
        }

        // Validate that the action is valid (keeping original 4 actions)
        $valid_actions = ['buy', 'short', 'sell', 'cover'];
        if (!in_array($data->action, $valid_actions)) {
            $this->_log_error('Invalid action: ' . $data->action, $json_data);
            return 'Invalid action. Valid actions: ' . implode(', ', $valid_actions);
        }

        // Save signal as pending (without converting the action)
        $signal_data = array(
            'user_id' => $data->user_id,
            'strategy_id' => $strategy->id,
            'signal_data' => $json_data,
            'status' => 'pending'
        );

        $signal_id = $this->CI->Mt_signal_model->add_signal($signal_data);

        if (!$signal_id) {
            $this->_log_error('Failed to save signal to database', $json_data);
            return 'Failed to save signal';
        }

        // Log with original action
        $log_data = array(
            'user_id' => $data->user_id,
            'action' => 'mt_signal_queued',
            'description' => 'MetaTrader signal queued (ID: ' . $signal_id .
                ', Strategy: ' . $strategy->name .
                ', Action: ' . $data->action .
                ', Symbol: ' . (isset($data->ticker) ? $data->ticker : 'N/A') . ')'
        );
        $this->CI->Log_model->add_log($log_data);

        $this->_log_debug('Signal processed successfully', $json_data);
        
        return true;
    }

    /**
     * Get pending signals for a user/strategy
     * 
     * @param int $user_id User ID
     * @param string $strategy_id Optional strategy ID filter
     * @return array Array of pending signals
     */
    public function get_pending_signals($user_id, $strategy_id = null)
    {
        return $this->CI->Mt_signal_model->get_pending_signals($user_id, $strategy_id);
    }

    /**
     * Confirm execution from EA
     * 
     * @param string $position_id Position ID from TradingView
     * @param string $status 'success' or 'failed'
     * @param float $execution_price Actual execution price (optional)
     * @param string $error_message Error message if failed (optional)
     * @return mixed true on success, error message string on failure
     */
    public function confirm_execution($position_id, $status, $execution_price = null, $error_message = null)
    {
        // Find pending signal with this position_id
        $signal = $this->_find_signal_by_position_id($position_id);
        
        if (!$signal) {
            $this->_log_error('No pending signal found for position_id', $position_id);
            return 'No pending signal found for position_id: ' . $position_id;
        }
        
        $signal_data = json_decode($signal->signal_data);
        
        if ($status === 'success') {
            // Determine if this is an open or close action
            if (in_array($signal_data->action, ['buy', 'short'])) {
                // Opening position - create trade
                $result = $this->_create_trade_from_execution($signal, $execution_price);
            } else {
                // Closing position - close existing trade
                $result = $this->_close_trade_from_execution($signal, $execution_price);
            }
            
            if ($result === true) {
                // Mark signal as processed
                $this->CI->Mt_signal_model->update_signal_status(
                    $signal->id, 
                    'processed', 
                    'Execution confirmed by EA'
                );
                
                $this->_log_debug('Execution confirmed successfully', 
                    "Signal ID: {$signal->id}, Position ID: {$position_id}, Action: {$signal_data->action}");
                
                return true;
            } else {
                // Mark signal as failed
                $this->CI->Mt_signal_model->update_signal_status(
                    $signal->id, 
                    'failed', 
                    'Failed to process execution: ' . $result
                );
                
                return $result;
            }
        } else {
            // Execution failed
            $this->CI->Mt_signal_model->update_signal_status(
                $signal->id, 
                'failed', 
                $error_message ?: 'Execution failed in EA'
            );
            
            $this->_log_error('Execution failed', 
                "Signal ID: {$signal->id}, Position ID: {$position_id}, Error: " . ($error_message ?: 'Unknown'));
            
            return true; // Return true because we handled the failure correctly
        }
    }

    /**
     * Find signal by position_id
     * 
     * @param string $position_id Position ID from TradingView
     * @return object|null Signal object or null
     */
    private function _find_signal_by_position_id($position_id)
    {
        // Load Trade model if not loaded
        $this->CI->load->model('Trade_model');
        
        return $this->CI->Mt_signal_model->get_signal_by_position_id($position_id);
    }

    /**
     * Create trade from successful execution
     * 
     * @param object $signal Signal object
     * @param float $execution_price Actual execution price
     * @return mixed true on success, error message on failure
     */
    private function _create_trade_from_execution($signal, $execution_price = null)
    {
        $signal_data = json_decode($signal->signal_data);
        
        // Load Trade model if not loaded
        $this->CI->load->model('Trade_model');
        
        // Get strategy
        $strategy = $this->CI->Strategy_model->get_strategy_by_id($signal->strategy_id);
        if (!$strategy) {
            return 'Strategy not found for signal';
        }
        
        // Determine side
        $side = $signal_data->action == 'buy' ? 'BUY' : 'SELL';
        
        // Use execution price or signal price or 0 as fallback
        $price = $execution_price ?: (isset($signal_data->price) ? (float)$signal_data->price : 0);
        
        $trade_data = array(
            'user_id' => $signal_data->user_id,
            'strategy_id' => $strategy->id,
            'symbol' => $signal_data->ticker,
            'timeframe' => isset($signal_data->timeframe) ? $signal_data->timeframe . 'm' : '60m',
            'side' => $side,
            'trade_type' => $strategy->type,
            'platform' => 'metatrader',
            'environment' => 'production',
            'quantity' => isset($signal_data->quantity) ? (float)$signal_data->quantity : 0.01,
            'entry_price' => $price,
            'current_price' => $price,
            'leverage' => 1,
            'pnl' => 0,
            'status' => 'open',
            'position_id' => $signal_data->position_id,
            'mt_signal_id' => $signal->id,
            'webhook_data' => $signal->signal_data
        );
        
        $trade_id = $this->CI->Trade_model->add_trade($trade_data);
        
        if ($trade_id) {
            $this->_log_debug('MT Trade created from execution', 
                "Trade ID: $trade_id, Signal ID: {$signal->id}, Position ID: {$signal_data->position_id}, Price: $price");
            return true;
        }
        
        return 'Failed to create trade in database';
    }

    /**
     * Close trade from successful execution
     * 
     * @param object $signal Signal object
     * @param float $execution_price Actual execution price
     * @return mixed true on success, error message on failure
     */
    private function _close_trade_from_execution($signal, $execution_price = null)
    {
        $signal_data = json_decode($signal->signal_data);
        
        // Load Trade model if not loaded
        $this->CI->load->model('Trade_model');
        
        // Find the trade to close by position_id
        $trade = $this->CI->Trade_model->get_trade_by_position_id(
            $signal_data->position_id,
            $signal_data->user_id,
            $signal_data->ticker
        );
        
        if (!$trade) {
            return 'No open trade found with position_id: ' . $signal_data->position_id;
        }
        
        if ($trade->status !== 'open') {
            return 'Trade is already closed';
        }
        
        // Use execution price or signal price or current entry price as fallback
        $exit_price = $execution_price ?: 
                     (isset($signal_data->price) ? (float)$signal_data->price : $trade->entry_price);
        
        // Calculate PNL
        if ($trade->side == 'BUY') {
            $pnl = ($exit_price - $trade->entry_price) * $trade->quantity;
        } else {
            $pnl = ($trade->entry_price - $exit_price) * $trade->quantity;
        }
        
        // Close trade
        $closed = $this->CI->Trade_model->close_trade($trade->id, $exit_price, $pnl);
        
        if ($closed) {
            $this->_log_debug('MT Trade closed from execution', 
                "Trade ID: {$trade->id}, Signal ID: {$signal->id}, Position ID: {$signal_data->position_id}, PNL: " . number_format($pnl, 2));
            return true;
        }
        
        return 'Failed to close trade in database';
    }

    /**
     * Log debug information
     * 
     * @param string $message Debug message
     * @param string $data Additional data
     */
    private function _log_debug($message, $data)
    {
        $log_data = array(
            'user_id' => $this->CI->session->userdata('user_id'),
            'action' => 'mt_webhook_debug',
            'description' => $message . '. Data: ' . $data
        );
        $this->CI->Log_model->add_log($log_data);
    }

    /**
     * Log error information
     * 
     * @param string $error Error message
     * @param string $data Additional data
     */
    private function _log_error($error, $data)
    {
        $log_data = array(
            'user_id' => $this->CI->session->userdata('user_id'),
            'action' => 'mt_webhook_error',
            'description' => $error . '. Data: ' . $data
        );
        $this->CI->Log_model->add_log($log_data);
    }
}