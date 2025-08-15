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