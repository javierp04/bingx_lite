<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Mt_dashboard extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
        
        // Check if user is logged in
        if (!$this->session->userdata('logged_in')) {
            redirect('auth');
        }
        
        // Check if user is admin (optional - puedes cambiar esto si quieres que usuarios normales accedan)
        if ($this->session->userdata('role') !== 'admin') {
            $this->session->set_flashdata('error', 'Access denied. Admin privileges required.');
            redirect('dashboard');
        }
    }
    
    public function index()
    {
        $data['title'] = 'MetaTrader Dashboard';
        
        // Get signal statistics
        $data['stats'] = $this->get_signal_statistics();
        
        // Get recent signals (last 10)
        $data['recent_signals'] = $this->Mt_signal_model->get_recent_signals(10);
        
        // Get EA activity (last polling times)
        $data['ea_activity'] = $this->Mt_signal_model->get_ea_activity();
        
        $this->load->view('templates/header', $data);
        $this->load->view('mt_dashboard/index', $data);
        $this->load->view('templates/footer');
    }
    
    public function signals()
    {
        $data['title'] = 'MetaTrader Signals Management';
        
        // Get filter params
        $filters = array();
        $filters['status'] = $this->input->get('status') ?: '';
        $filters['user_id'] = $this->input->get('user_id') ?: '';
        $filters['strategy_id'] = $this->input->get('strategy_id') ?: '';
        $filters['date_from'] = $this->input->get('date_from') ?: '';
        $filters['date_to'] = $this->input->get('date_to') ?: '';
        
        // Get signals with filters
        $data['signals'] = $this->Mt_signal_model->get_signals_with_filters($filters);
        
        // Get filter options
        $data['users'] = $this->User_model->get_all_users();
        $data['strategies'] = $this->Strategy_model->get_mt_strategies();
        $data['filters'] = $filters;
        
        $this->load->view('templates/header', $data);
        $this->load->view('mt_dashboard/signals', $data);
        $this->load->view('templates/footer');
    }
    
    public function logs()
    {
        $data['title'] = 'MetaTrader Logs';
        
        // Get MT-specific logs
        $mt_actions = ['mt_webhook_debug', 'mt_webhook_error', 'mt_signal_queued', 'mt_signal_processed', 'mt_signal_failed', 'mt_debug_test'];
        
        $filters = array();
        $filters['actions'] = $mt_actions;
        $filters['date_from'] = $this->input->get('date_from') ?: '';
        $filters['date_to'] = $this->input->get('date_to') ?: '';
        $filters['user_id'] = $this->input->get('user_id') ?: '';
        
        $data['logs'] = $this->Log_model->get_logs(100, null, $filters);
        $data['users'] = $this->User_model->get_all_users();
        $data['filters'] = $filters;
        
        $this->load->view('templates/header', $data);
        $this->load->view('mt_dashboard/logs', $data);
        $this->load->view('templates/footer');
    }
    
    public function debug()
    {
        $data['title'] = 'MetaTrader Debug Panel';
        
        // Get all MT strategies for the dropdown
        $data['strategies'] = $this->Strategy_model->get_mt_strategies();
        $data['users'] = $this->User_model->get_all_users();
        
        $this->load->view('templates/header', $data);
        $this->load->view('mt_dashboard/debug', $data);
        $this->load->view('templates/footer');
    }
    
public function test_signal()
    {
        if (!$this->input->post('signal_data')) {
            $this->session->set_flashdata('error', 'No signal data provided');
            redirect('mt_dashboard/debug');
            return;
        }
        
        $signal_data = $this->input->post('signal_data');
        
        // Log the test
        $this->Log_model->add_log([
            'user_id' => $this->session->userdata('user_id'),
            'action' => 'mt_debug_test',
            'description' => 'Testing MT signal: ' . $signal_data
        ]);
        
        // Process the signal using the Mt_signal_processor library
        $result = $this->mt_signal_processor->process_signal($signal_data);
        
        if ($result === true) {
            $this->session->set_flashdata('success', 'Signal processed successfully and queued for MetaTrader');
        } else {
            $this->session->set_flashdata('error', 'Signal processing failed: ' . $result);
        }
        
        redirect('mt_dashboard/debug');
    }
    
    public function retry_signal($signal_id)
    {
        $signal = $this->Mt_signal_model->get_signal_by_id($signal_id);
        
        if (!$signal) {
            $this->session->set_flashdata('error', 'Signal not found');
            redirect('mt_dashboard/signals');
            return;
        }
        
        // Reset signal to pending
        $this->Mt_signal_model->update_signal_status($signal_id, 'pending', null);
        
        // Log action
        $this->Log_model->add_log([
            'user_id' => $this->session->userdata('user_id'),
            'action' => 'mt_signal_retry',
            'description' => 'Retried signal ID: ' . $signal_id
        ]);
        
        $this->session->set_flashdata('success', 'Signal marked as pending for retry');
        redirect('mt_dashboard/signals');
    }
    
    public function delete_signal($signal_id)
    {
        $signal = $this->Mt_signal_model->get_signal_by_id($signal_id);
        
        if (!$signal) {
            $this->session->set_flashdata('error', 'Signal not found');
            redirect('mt_dashboard/signals');
            return;
        }
        
        // Delete signal
        $this->Mt_signal_model->delete_signal($signal_id);
        
        // Log action
        $this->Log_model->add_log([
            'user_id' => $this->session->userdata('user_id'),
            'action' => 'mt_signal_delete',
            'description' => 'Deleted signal ID: ' . $signal_id
        ]);
        
        $this->session->set_flashdata('success', 'Signal deleted successfully');
        redirect('mt_dashboard/signals');
    }
    
    // AJAX endpoint for real-time updates
    public function get_signal_stats()
    {
        $stats = $this->get_signal_statistics();
        echo json_encode($stats);
    }
    
    private function get_signal_statistics()
    {
        $stats = array();
        
        // Count signals by status
        $stats['pending'] = $this->Mt_signal_model->count_signals_by_status('pending');
        $stats['processed'] = $this->Mt_signal_model->count_signals_by_status('processed');
        $stats['failed'] = $this->Mt_signal_model->count_signals_by_status('failed');
        
        // Last 24h activity
        $stats['last_24h'] = $this->Mt_signal_model->count_signals_last_24h();
        
        // Processing rate (today)
        $today_total = $this->Mt_signal_model->count_signals_today();
        $today_processed = $this->Mt_signal_model->count_signals_today('processed');
        $stats['success_rate'] = $today_total > 0 ? round(($today_processed / $today_total) * 100, 1) : 0;
        
        return $stats;
    }
}