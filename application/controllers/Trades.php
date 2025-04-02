<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Trades extends CI_Controller {
    
    public function __construct() {
        parent::__construct();
        
        // Check if user is logged in
        if (!$this->session->userdata('logged_in')) {
            redirect('auth');
        }
    }
    
    public function index() {
        $data['title'] = 'Trade History';
        $user_id = $this->session->userdata('user_id');
        
        // Get all trades
        $data['trades'] = $this->Trade_model->get_all_trades($user_id);
        
        $this->load->view('templates/header', $data);
        $this->load->view('trades/index', $data);
        $this->load->view('templates/footer');
    }
    
    public function detail($id) {
        $data['title'] = 'Trade Detail';
        $user_id = $this->session->userdata('user_id');
        
        // Get trade
        $data['trade'] = $this->Trade_model->get_trade_by_id($id);
        
        // Check if trade exists and belongs to user
        if (empty($data['trade']) || $data['trade']->user_id != $user_id) {
            $this->session->set_flashdata('error', 'Trade not found or access denied');
            redirect('trades');
        }
        
        $this->load->view('templates/header', $data);
        $this->load->view('trades/detail', $data);
        $this->load->view('templates/footer');
    }
    
    public function close($id) {
        $user_id = $this->session->userdata('user_id');
        
        // Get trade
        $trade = $this->Trade_model->get_trade_by_id($id);
        
        // Check if trade exists, belongs to user, and is open
        if (empty($trade) || $trade->user_id != $user_id || $trade->status != 'open') {
            $this->session->set_flashdata('error', 'Trade not found, access denied, or already closed');
            redirect('trades');
        }
        
        // Get API key for this environment
        $api_key = $this->Api_key_model->get_api_key($user_id, $trade->environment);
        
        if (empty($api_key)) {
            $this->session->set_flashdata('error', 'API key not configured for ' . $trade->environment);
            redirect('trades');
        }
        
        // Load BingX API library
        $this->load->library('BingxApi');
        
        // Close trade
        if ($trade->trade_type == 'futures') {
            $result = $this->bingxapi->close_futures_position($api_key, $trade->symbol, $trade->side, $trade->quantity);
        } else {
            $result = $this->bingxapi->close_spot_position($api_key, $trade->symbol, $trade->side, $trade->quantity);
        }
        
        if ($result && isset($result->price)) {
            // Calculate PNL
            $exit_price = $result->price;
            
            if ($trade->side == 'BUY') {
                $pnl = ($exit_price - $trade->entry_price) * $trade->quantity;
            } else {
                $pnl = ($trade->entry_price - $exit_price) * $trade->quantity;
            }
            
            // Update trade
            $this->Trade_model->close_trade($id, $exit_price, $pnl);
            
            // Log action
            $log_data = array(
                'user_id' => $user_id,
                'action' => 'close_trade',
                'description' => 'Closed trade for ' . $trade->symbol . ' with PNL: ' . number_format($pnl, 2)
            );
            $this->Log_model->add_log($log_data);
            
            $this->session->set_flashdata('success', 'Trade closed successfully. PNL: ' . number_format($pnl, 2));
        } else {
            $this->session->set_flashdata('error', 'Failed to close trade');
        }
        
        redirect('dashboard');
    }
}