<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Metatrader extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Mt_signal_model');
        $this->load->model('Strategy_model');
    }

    // Endpoint para webhooks de TradingView dirigidos a MT
    public function webhook()
    {
        $json_data = file_get_contents('php://input');
        
        $this->_log_webhook_debug('Received MT webhook', $json_data);
        
        $data = json_decode($json_data, true);
        
        if (!$data || !isset($data['strategy_id']) || !isset($data['action'])) {
            $this->_log_webhook_error('Missing required fields in MT webhook data', $json_data);
            $this->_send_response(400, 'Missing required fields');
            return;
        }

        // Usar la librería para procesar la señal
        $result = $this->mt_signal_processor->process_signal($json_data);

        if ($result === true) {
            $this->_send_response(200, 'Signal queued successfully');
        } else {
            $this->_send_response(400, $result);
        }
    }

    // Endpoint para que el EA consulte señales pendientes
    public function get_pending_signals()
    {
        $user_id = $this->input->get('user_id');
        $strategy_id = $this->input->get('strategy_id'); // Opcional, para filtrar por estrategia
        
        if (!$user_id) {
            $this->_send_response(400, 'user_id required');
            return;
        }

        $signals = $this->mt_signal_processor->get_pending_signals($user_id, $strategy_id);
        
        $this->_send_response(200, 'Success', $signals);
    }

    // Endpoint para confirmar ejecución desde EA
    public function confirm_execution()
    {
        $position_id = $this->input->post('position_id');
        $status = $this->input->post('status'); // 'success' o 'failed'
        $execution_price = $this->input->post('execution_price'); // Precio real de ejecución
        $error_message = $this->input->post('error_message'); // Si failed
        
        if (!$position_id || !$status) {
            $this->_send_response(400, 'position_id and status required');
            return;
        }

        $result = $this->mt_signal_processor->confirm_execution($position_id, $status, $execution_price, $error_message);
        
        if ($result === true) {
            $this->_send_response(200, 'Execution confirmed successfully');
        } else {
            $this->_send_response(400, $result);
        }
    }

    /**
     * Public method to process webhook (for use by other controllers)
     * 
     * @param string $json_data JSON signal data
     * @return mixed true on success, error message on failure
     */
    public function process_mt_webhook($json_data)
    {
        return $this->mt_signal_processor->process_signal($json_data);
    }

    private function _log_webhook_debug($message, $data)
    {
        $this->Log_model->add_log([
            'user_id' => $this->session->userdata('user_id'),
            'action' => 'mt_webhook_debug',
            'description' => $message . '. Data: ' . $data
        ]);
    }

    private function _log_webhook_error($error, $data)
    {
        $this->Log_model->add_log([
            'user_id' => $this->session->userdata('user_id'),
            'action' => 'mt_webhook_error',
            'description' => $error . '. Data: ' . $data
        ]);
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