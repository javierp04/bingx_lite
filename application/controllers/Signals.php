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
        
        // Check if user is admin
        if ($this->session->userdata('role') !== 'admin') {
            $this->session->set_flashdata('error', 'Access denied. Signals management requires admin privileges.');
            redirect('dashboard');
        }
    }
    
    public function index()
    {
        $data['title'] = 'MetaTrader Signals Management';
        
        // Get filter params
        $filters = array();
        $filters['status'] = $this->input->get('status') ?: '';
        $filters['user_id'] = $this->input->get('user_id') ?: '';
        $filters['strategy_id'] = $this->input->get('strategy_id') ?: '';
        $filters['date_from'] = $this->input->get('date_from') ?: '';
        $filters['date_to'] = $this->input->get('date_to') ?: '';
        
        // Get signals with filters (MetaTrader only)
        $data['signals'] = $this->Mt_signal_model->get_signals_with_filters($filters);
        
        // Get filter options
        $data['users'] = $this->User_model->get_all_users();
        $data['strategies'] = $this->Strategy_model->get_mt_strategies(); // Only MT strategies
        $data['filters'] = $filters;
        
        // Get EA activity monitor
        $data['ea_activity'] = $this->Mt_signal_model->get_ea_activity();
        
        $this->load->view('templates/header', $data);
        $this->load->view('signals/index', $data);
        $this->load->view('templates/footer');
    }
    
    public function retry_signal($signal_id)
    {
        $signal = $this->Mt_signal_model->get_signal_by_id($signal_id);
        
        if (!$signal) {
            $this->session->set_flashdata('error', 'Signal not found');
            redirect('signals');
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
        redirect('signals');
    }
    
    public function delete_signal($signal_id)
    {
        $signal = $this->Mt_signal_model->get_signal_by_id($signal_id);
        
        if (!$signal) {
            $this->session->set_flashdata('error', 'Signal not found');
            redirect('signals');
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
        redirect('signals');
    }
    
    // AJAX endpoint for real-time updates
    public function get_stats()
    {
        $stats = array();
        
        // Count signals by status (MetaTrader only)
        $stats['pending'] = $this->Mt_signal_model->count_signals_by_status('pending');
        $stats['processed'] = $this->Mt_signal_model->count_signals_by_status('processed');
        $stats['failed'] = $this->Mt_signal_model->count_signals_by_status('failed');
        
        // Last 24h activity
        $stats['last_24h'] = $this->Mt_signal_model->count_signals_last_24h();
        
        echo json_encode(['success' => true, 'stats' => $stats]);
    }
}