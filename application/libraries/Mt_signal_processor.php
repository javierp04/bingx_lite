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
        
        // Create or close MT trade
        $this->_handle_mt_trade($data, $strategy, $signal_id);

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
     * Mark signal as processed
     * 
     * @param int $signal_id Signal ID
     * @param string $status Status (processed, failed)
     * @param string $ea_response Optional EA response
     * @return bool Success status
     */
    public function mark_signal_processed($signal_id, $status, $ea_response = null)
    {
        $result = $this->CI->Mt_signal_model->update_signal_status($signal_id, $status, $ea_response);

        if ($result) {
            // Log the action
            $log_data = array(
                'user_id' => $this->CI->session->userdata('user_id'),
                'action' => 'mt_signal_' . $status,
                'description' => 'Signal ' . $signal_id . ' marked as ' . $status .
                    ($ea_response ? ' with response: ' . $ea_response : '')
            );
            $this->CI->Log_model->add_log($log_data);
        }

        return $result;
    }

    /**
     * Create or close MetaTrader trade based on signal action
     * 
     * @param object $signal_data Decoded signal data
     * @param object $strategy Strategy object  
     * @param int $signal_id Signal ID
     * @return bool Success status
     */
    private function _handle_mt_trade($signal_data, $strategy, $signal_id)
    {
        // Load Trade model
        $this->CI->load->model('Trade_model');

        if (in_array($signal_data->action, ['buy', 'short'])) {
            // Open position
            return $this->_create_mt_trade($signal_data, $strategy, $signal_id);
        } else if (in_array($signal_data->action, ['sell', 'cover'])) {
            // Close position
            return $this->_close_mt_trade($signal_data, $strategy, $signal_id);
        }

        return true; // Unknown action, but don't fail
    }

    /**
     * Create MT trade for buy/short signals
     * 
     * @param object $signal_data Signal data
     * @param object $strategy Strategy object
     * @param int $signal_id Signal ID
     * @return bool Success status
     */
    private function _create_mt_trade($signal_data, $strategy, $signal_id)
    {
        // Determine side
        $side = $signal_data->action == 'buy' ? 'BUY' : 'SELL';

        // Get price from signal or use 0 as placeholder
        $price = isset($signal_data->price) ? (float)$signal_data->price : 0;

        $trade_data = array(
            'user_id' => $signal_data->user_id,
            'strategy_id' => $strategy->id,
            'symbol' => $signal_data->ticker,
            'timeframe' => isset($signal_data->timeframe) ? $signal_data->timeframe . 'm' : '60m',
            'side' => $side,
            'trade_type' => $strategy->type, // forex, indices, commodities
            'platform' => 'metatrader',
            'environment' => 'production', // MT doesn't have sandbox
            'quantity' => isset($signal_data->quantity) ? (float)$signal_data->quantity : 0.01,
            'entry_price' => $price,
            'current_price' => $price,
            'leverage' => 1, // MT doesn't use leverage concept like crypto
            'pnl' => 0,
            'status' => 'open',
            'position_id' => isset($signal_data->position_id) ? $signal_data->position_id : null,
            'mt_signal_id' => $signal_id,
            'webhook_data' => json_encode($signal_data)
        );

        $trade_id = $this->CI->Trade_model->add_trade($trade_data);

        if ($trade_id) {
            $this->_log_debug('MT Trade created', "Trade ID: $trade_id, Signal ID: $signal_id, Action: {$signal_data->action}");
            return true;
        }

        $this->_log_error('Failed to create MT trade', "Signal ID: $signal_id");
        return false;
    }

    /**
     * Close MT trade for sell/cover signals
     * 
     * @param object $signal_data Signal data
     * @param object $strategy Strategy object
     * @param int $signal_id Signal ID
     * @return bool Success status
     */
    private function _close_mt_trade($signal_data, $strategy, $signal_id)
    {
        // Find matching open trade
        $trade = $this->_find_mt_trade_to_close($signal_data, $strategy);

        if (!$trade) {
            $this->_log_error(
                'No matching MT trade found to close',
                "Position ID: " . (isset($signal_data->position_id) ? $signal_data->position_id : 'N/A') .
                    ", Symbol: {$signal_data->ticker}, Strategy: {$strategy->id}"
            );
            return false;
        }

        // Get exit price from signal or use entry price
        $exit_price = isset($signal_data->price) ? (float)$signal_data->price : $trade->entry_price;

        // Calculate PNL
        if ($trade->side == 'BUY') {
            $pnl = ($exit_price - $trade->entry_price) * $trade->quantity;
        } else {
            $pnl = ($trade->entry_price - $exit_price) * $trade->quantity;
        }

        // Close trade
        $closed = $this->CI->Trade_model->close_trade($trade->id, $exit_price, $pnl);

        if ($closed) {
            $this->_log_debug(
                'MT Trade closed',
                "Trade ID: {$trade->id}, Signal ID: $signal_id, PNL: " . number_format($pnl, 2)
            );
            return true;
        }

        $this->_log_error('Failed to close MT trade', "Trade ID: {$trade->id}, Signal ID: $signal_id");
        return false;
    }

    /**
     * Find MT trade to close based on signal data
     * 
     * @param object $signal_data Signal data
     * @param object $strategy Strategy object
     * @return object|null Trade object or null
     */
    private function _find_mt_trade_to_close($signal_data, $strategy)
    {
        // Try to find by position_id first (most reliable)
        if (isset($signal_data->position_id) && $signal_data->position_id) {
            $trade = $this->CI->Trade_model->get_trade_by_position_id(
                $signal_data->position_id,
                $signal_data->user_id,
                $signal_data->ticker,
                null, // timeframe
                null  // side - let it match any
            );

            if ($trade && $trade->platform == 'metatrader' && $trade->status == 'open') {
                return $trade;
            }
        }

        // Fallback: find by symbol + strategy + platform
        $trades = $this->CI->Trade_model->get_all_trades($signal_data->user_id, 'open');

        foreach ($trades as $trade) {
            if (
                $trade->platform == 'metatrader' &&
                $trade->symbol == $signal_data->ticker &&
                $trade->strategy_id == $strategy->id
            ) {
                // For sell action, find BUY trade (close long)
                // For cover action, find SELL trade (close short)
                if (
                    ($signal_data->action == 'sell' && $trade->side == 'BUY') ||
                    ($signal_data->action == 'cover' && $trade->side == 'SELL')
                ) {
                    return $trade;
                }
            }
        }

        return null;
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
