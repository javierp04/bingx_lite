<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Signals extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
        
        // Check if user is logged in
        if (!$this->session->userdata('logged_in')) {
            redirect('auth');
        }
        
        // Check if user is admin (optional - adjust based on requirements)
        if ($this->session->userdata('role') !== 'admin') {
            $this->session->set_flashdata('error', 'Access denied. Signals management requires admin privileges.');
            redirect('dashboard');
        }
    }
    
    public function index()
    {
        $data['title'] = 'Signals Management';
        
        // Get signal statistics
        $data['stats'] = $this->get_signal_statistics();
        
        // Get recent signals (last 20 for overview)
        $data['recent_signals'] = $this->Mt_signal_model->get_recent_signals(20);
        
        // Get EA activity (last polling times) - MT specific
        $data['ea_activity'] = $this->Mt_signal_model->get_ea_activity();
        
        $this->load->view('templates/header', $data);
        $this->load->view('signals/index', $data);
        $this->load->view('templates/footer');
    }
    
    public function management()
    {
        $data['title'] = 'Signal Management';
        
        // Get filter params
        $filters = array();
        $filters['status'] = $this->input->get('status') ?: '';
        $filters['user_id'] = $this->input->get('user_id') ?: '';
        $filters['strategy_id'] = $this->input->get('strategy_id') ?: '';
        $filters['date_from'] = $this->input->get('date_from') ?: '';
        $filters['date_to'] = $this->input->get('date_to') ?: '';
        
        // Get signals with filters (currently only MT signals exist)
        $data['signals'] = $this->Mt_signal_model->get_signals_with_filters($filters);
        
        // Get filter options
        $data['users'] = $this->User_model->get_all_users();
        $data['strategies'] = $this->Strategy_model->get_all_strategies(); // All strategies, not just MT
        $data['filters'] = $filters;
        
        $this->load->view('templates/header', $data);
        $this->load->view('signals/management', $data);
        $this->load->view('templates/footer');
    }
    
    public function logs()
    {
        $data['title'] = 'Signal Logs';
        
        // Get signal-specific logs (both MT and BingX related)
        $signal_actions = [
            'mt_webhook_debug', 'mt_webhook_error', 'mt_signal_queued', 
            'mt_signal_processed', 'mt_signal_failed', 'mt_debug_test',
            'bingx_debug_test', 'webhook_debug', 'webhook_error'
        ];
        
        $filters = array();
        $filters['actions'] = $signal_actions;
        $filters['date_from'] = $this->input->get('date_from') ?: '';
        $filters['date_to'] = $this->input->get('date_to') ?: '';
        $filters['user_id'] = $this->input->get('user_id') ?: '';
        
        $data['logs'] = $this->Log_model->get_logs(100, null, $filters);
        $data['users'] = $this->User_model->get_all_users();
        $data['filters'] = $filters;
        
        $this->load->view('templates/header', $data);
        $this->load->view('signals/logs', $data);
        $this->load->view('templates/footer');
    }
    
    public function retry_signal($signal_id)
    {
        $signal = $this->Mt_signal_model->get_signal_by_id($signal_id);
        
        if (!$signal) {
            $this->session->set_flashdata('error', 'Signal not found');
            redirect('signals/management');
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
        redirect('signals/management');
    }
    
    public function delete_signal($signal_id)
    {
        $signal = $this->Mt_signal_model->get_signal_by_id($signal_id);
        
        if (!$signal) {
            $this->session->set_flashdata('error', 'Signal not found');
            redirect('signals/management');
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
        redirect('signals/management');
    }
    
    // AJAX endpoint for real-time updates
    public function get_stats()
    {
        $stats = $this->get_signal_statistics();
        echo json_encode(['success' => true, 'stats' => $stats]);
    }
    
    private function get_signal_statistics()
    {
        $stats = array();
        
        // Count signals by status (currently only MT signals exist)
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