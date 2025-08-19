<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Webhook extends CI_Controller
{

    public function __construct()
    {
        parent::__construct();

        // Load Webhook processor library
        $this->load->library('Webhook_processor');
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
            // Convert TradingView actions to simplified format (only BUY/SELL)
            switch ($data['action']) {
                case 'buy':
                    $data['action'] = 'BUY';
                    break;
                case 'sell':
                    $data['action'] = 'SELL';
                    break;
                // Remove old short/cover logic
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

        // Process webhook data using the library
        $result = $this->webhook_processor->process_webhook_data($json_data);

        // Send response
        if ($result === true) {
            $this->_send_response(200, 'Order executed successfully');
        } else {
            $this->_send_response(400, $result);
        }
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