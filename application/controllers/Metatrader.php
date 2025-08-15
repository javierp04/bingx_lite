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

        // Opción 2: NO convertimos las acciones, mantenemos las originales
        // TradingView envía: buy, short, sell, cover
        // Las mantenemos tal cual para el EA
        
        $result = $this->process_mt_webhook($json_data);

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

        $signals = $this->Mt_signal_model->get_pending_signals($user_id, $strategy_id);
        
        $this->_send_response(200, 'Success', $signals);
    }

    // Endpoint para marcar señal como procesada
    public function mark_signal_processed()
    {
        $signal_id = $this->input->post('signal_id');
        $status = $this->input->post('status'); // 'processed' o 'failed'
        $ea_response = $this->input->post('response'); // Respuesta del EA
        
        if (!$signal_id || !$status) {
            $this->_send_response(400, 'signal_id and status required');
            return;
        }

        $updated = $this->Mt_signal_model->update_signal_status($signal_id, $status, $ea_response);
        
        if ($updated) {
            $this->_send_response(200, 'Signal updated successfully');
        } else {
            $this->_send_response(400, 'Failed to update signal');
        }
    }

    public function process_mt_webhook($json_data)
    {
        $data = json_decode($json_data);

        // Validaciones similares al webhook existente
        if (!isset($data->user_id)) {
            return 'Missing user_id';
        }

        $user = $this->User_model->get_user_by_id($data->user_id);
        if (!$user) {
            return 'User not found';
        }

        $strategy = $this->Strategy_model->get_strategy_by_strategy_id($data->user_id, $data->strategy_id);
        if (!$strategy) {
            return 'Strategy not found';
        }

        if (!$strategy->active) {
            return 'Strategy is inactive';
        }

        // Verificar que la estrategia es para MetaTrader
        if ($strategy->platform !== 'metatrader') {
            return 'Strategy is not configured for MetaTrader';
        }

        // Validar que la acción es válida (Opción 2: mantenemos las 4 originales)
        $valid_actions = ['buy', 'short', 'sell', 'cover'];
        if (!in_array($data->action, $valid_actions)) {
            return 'Invalid action. Valid actions: ' . implode(', ', $valid_actions);
        }

        // Guardar señal como pendiente (sin convertir la acción)
        $signal_data = array(
            'user_id' => $data->user_id,
            'strategy_id' => $strategy->id,
            'signal_data' => $json_data,
            'status' => 'pending'
        );

        $signal_id = $this->Mt_signal_model->add_signal($signal_data);

        // Log con la acción original
        $log_data = array(
            'user_id' => $data->user_id,
            'action' => 'mt_signal_queued',
            'description' => 'MetaTrader signal queued (ID: ' . $signal_id . 
                           ', Strategy: ' . $strategy->name . 
                           ', Action: ' . $data->action . 
                           ', Symbol: ' . (isset($data->ticker) ? $data->ticker : 'N/A') . ')'
        );
        $this->Log_model->add_log($log_data);

        return true;
    }

    // Para testing desde el debug panel
    public function process_mt_webhook_debug($json_data)
    {
        return $this->process_mt_webhook($json_data);
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