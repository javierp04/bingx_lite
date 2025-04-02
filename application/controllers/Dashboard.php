<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Dashboard extends CI_Controller {
    
    public function __construct() {
        parent::__construct();
        
        // Check if user is logged in
        if (!$this->session->userdata('logged_in')) {
            redirect('auth');
        }
        
        // Load BingX API library
        $this->load->library('BingxApi');
    }
    
    public function index() {
        $data['title'] = 'Dashboard';
        $user_id = $this->session->userdata('user_id');
        
        // Get all open trades for the user (both sandbox and production)
        $data['open_trades'] = $this->Trade_model->get_all_trades($user_id, 'open');
        
        // Get API keys for both environments
        $data['sandbox_api'] = $this->Api_key_model->get_api_key($user_id, 'sandbox');
        $data['production_api'] = $this->Api_key_model->get_api_key($user_id, 'production');
        
        // Get all strategies for this user
        $data['strategies'] = $this->Strategy_model->get_all_strategies($user_id);
        
        // Check if user is admin (for simulation panel)
        $data['is_admin'] = ($this->session->userdata('role') == 'admin');
        
        // If user is admin, get additional data for the simulation panel
        if ($data['is_admin']) {
            // Get all users for the simulation panel dropdown
            $data['all_users'] = $this->User_model->get_all_users();
            
            // Get all strategies for the simulation panel dropdown
            $data['all_strategies'] = $this->Strategy_model->get_all_strategies();
        }
        
        // Load view
        $this->load->view('templates/header', $data);
        $this->load->view('dashboard/index', $data);
        $this->load->view('templates/footer');
    }
    
    public function refresh_trades() {
        $user_id = $this->session->userdata('user_id');
        
        // Get all open trades (both sandbox and production)
        $trades = $this->Trade_model->get_all_trades($user_id, 'open');
        
        // Initialize arrays to store environment-specific API keys
        $api_keys = [
            'sandbox' => $this->Api_key_model->get_api_key($user_id, 'sandbox'),
            'production' => $this->Api_key_model->get_api_key($user_id, 'production')
        ];
        
        // Update PNL for each trade
        foreach ($trades as $trade) {
            // Get API key for this environment
            $api_key = $api_keys[$trade->environment];
            
            // Skip if no API key found
            if (!$api_key) {
                continue;
            }
            
            // Update price and PNL information
            if ($trade->trade_type == 'futures') {
                $price_info = $this->bingxapi->get_futures_price($api_key, $trade->symbol);
            } else {
                $price_info = $this->bingxapi->get_spot_price($api_key, $trade->symbol);
            }
            
            if ($price_info) {
                // Calculate PNL
                $current_price = $price_info->price;
                
                if ($trade->side == 'BUY') {
                    $pnl = ($current_price - $trade->entry_price) * $trade->quantity * $trade->leverage;
                } else {
                    $pnl = ($trade->entry_price - $current_price) * $trade->quantity * $trade->leverage;
                }
                
                // Update trade with current price and PNL
                $this->Trade_model->update_trade($trade->id, array(
                    'current_price' => $current_price,
                    'pnl' => $pnl
                ));
                
                // Update object for JSON response
                $trade->current_price = $current_price;
                $trade->pnl = $pnl;
            }
        }
        
        // Return JSON response
        echo json_encode($trades);
    }
    
    public function simulate_order() {
        // Check if user is admin
        if ($this->session->userdata('role') != 'admin') {
            $this->session->set_flashdata('error', 'Permission denied. Admin privileges required.');
            redirect('dashboard');
        }
        
        // Validate input
        $this->form_validation->set_rules('user_id', 'User', 'required');
        $this->form_validation->set_rules('strategy_id', 'Strategy', 'required');
        $this->form_validation->set_rules('ticker', 'Ticker', 'required');
        $this->form_validation->set_rules('timeframe', 'Timeframe', 'required');
        $this->form_validation->set_rules('action', 'Action', 'required');
        $this->form_validation->set_rules('quantity', 'Quantity', 'required|numeric');
        $this->form_validation->set_rules('environment', 'Environment', 'required');
        
        if ($this->form_validation->run() === FALSE) {
            $this->session->set_flashdata('error', validation_errors());
            redirect('dashboard');
        }
        
        // Prepare data
        $data = array(
            'user_id' => $this->input->post('user_id'),
            'strategy_id' => $this->input->post('strategy_id'),
            'ticker' => $this->input->post('ticker'),
            'timeframe' => $this->input->post('timeframe'),
            'action' => $this->input->post('action'),
            'quantity' => $this->input->post('quantity'),
            'leverage' => $this->input->post('leverage') ? $this->input->post('leverage') : 1,
            'environment' => $this->input->post('environment')
        );
        
        // Format data as JSON
        $json_data = json_encode($data);
        
        // Load Webhook controller and call method
        $this->load->library('../controllers/webhook');
        $result = $this->webhook->process_webhook_data($json_data);
        
        if ($result === true) {
            $this->session->set_flashdata('success', 'Order simulated successfully.');
        } else {
            $this->session->set_flashdata('error', 'Error simulating order: ' . $result);
        }
        
        redirect('dashboard');
    }
}