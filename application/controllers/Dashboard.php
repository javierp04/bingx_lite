<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Dashboard extends CI_Controller {
    
    public function __construct() {
        parent::__construct();
        
        // Check if user is logged in
        if (!$this->session->userdata('logged_in')) {
            redirect('auth');
        }
    }
    
    public function index() {
        $data['title'] = 'Dashboard';
        $user_id = $this->session->userdata('user_id');
        
        // Get open trades for sandbox
        $data['sandbox_trades'] = $this->Trade_model->get_all_trades($user_id, 'open', 'sandbox');
        
        // Get open trades for production
        $data['production_trades'] = $this->Trade_model->get_all_trades($user_id, 'open', 'production');
        
        // Get API keys for both environments
        $data['sandbox_api'] = $this->Api_key_model->get_api_key($user_id, 'sandbox');
        $data['production_api'] = $this->Api_key_model->get_api_key($user_id, 'production');
        
        // Get all strategies
        $data['strategies'] = $this->Strategy_model->get_all_strategies($user_id);
        
        // Load view
        $this->load->view('templates/header', $data);
        $this->load->view('dashboard/index', $data);
        $this->load->view('templates/footer');
    }
    
    public function refresh_trades() {
        $user_id = $this->session->userdata('user_id');
        $environment = $this->input->get('environment');
        
        // Get open trades for specified environment
        $trades = $this->Trade_model->get_all_trades($user_id, 'open', $environment);
        
        // Get API key for this environment
        $api_key = $this->Api_key_model->get_api_key($user_id, $environment);
        
        // Update PNL for each trade
        if ($api_key) {
            foreach ($trades as $trade) {
                $this->load->library('BingxApi');
                
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
                        $pnl = ($current_price - $trade->entry_price) * $trade->quantity;
                    } else {
                        $pnl = ($trade->entry_price - $current_price) * $trade->quantity;
                    }
                    
                    // Update trade
                    $this->Trade_model->update_trade($trade->id, array(
                        'pnl' => $pnl
                    ));
                }
            }
        }
        
        // Refresh trades
        $updated_trades = $this->Trade_model->get_all_trades($user_id, 'open', $environment);
        
        // Return JSON response
        echo json_encode($updated_trades);
    }
}