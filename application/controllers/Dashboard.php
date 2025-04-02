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
        
        // Prepare data for webhook
        $webhook_data = [
            'user_id' => $this->input->post('user_id'),
            'strategy_id' => $this->input->post('strategy_id'),
            'ticker' => $this->input->post('ticker'),
            'timeframe' => $this->input->post('timeframe'),
            'action' => $this->input->post('action'),
            'quantity' => $this->input->post('quantity'),
            'leverage' => $this->input->post('leverage') ? $this->input->post('leverage') : 1,
            'environment' => $this->input->post('environment')
        ];
        
        // Get user
        $user = $this->User_model->get_user_by_id($webhook_data['user_id']);
        if (!$user) {
            $this->session->set_flashdata('error', 'User not found');
            redirect('dashboard');
        }
        
        // Get strategy
        $strategy = $this->Strategy_model->get_strategy_by_strategy_id($webhook_data['user_id'], $webhook_data['strategy_id']);
        if (!$strategy) {
            $this->session->set_flashdata('error', 'Strategy not found');
            redirect('dashboard');
        }
        
        // Check if strategy is active
        if (!$strategy->active) {
            $this->session->set_flashdata('error', 'Strategy is inactive');
            redirect('dashboard');
        }
        
        // Get API key for this environment
        $api_key = $this->Api_key_model->get_api_key($webhook_data['user_id'], $webhook_data['environment']);
        if (!$api_key) {
            $this->session->set_flashdata('error', 'API key not configured for this environment');
            redirect('dashboard');
        }
        
        // Determine trade type (spot or futures)
        $trade_type = $strategy->type;
        
        // Process action
        switch ($webhook_data['action']) {
            case 'BUY':
            case 'SELL':
                // Get quantity and leverage
                $quantity = $webhook_data['quantity'];
                $leverage = $webhook_data['leverage'];
                
                // Execute order
                if ($trade_type == 'futures') {
                    // Set leverage first if needed
                    if ($leverage > 1) {
                        $this->bingxapi->set_futures_leverage($api_key, $webhook_data['ticker'], $leverage);
                    }
                    
                    $result = $this->bingxapi->open_futures_position($api_key, $webhook_data['ticker'], $webhook_data['action'], $quantity);
                } else {
                    $result = $this->bingxapi->open_spot_position($api_key, $webhook_data['ticker'], $webhook_data['action'], $quantity);
                }
                
                if ($result && isset($result->orderId) && isset($result->price)) {
                    // Save trade to database
                    $trade_data = array(
                        'user_id' => $webhook_data['user_id'],
                        'strategy_id' => $strategy->id,
                        'order_id' => $result->orderId,
                        'symbol' => $webhook_data['ticker'],
                        'timeframe' => $webhook_data['timeframe'],
                        'side' => $webhook_data['action'],
                        'trade_type' => $trade_type,
                        'quantity' => $quantity,
                        'entry_price' => $result->price,
                        'current_price' => $result->price,
                        'leverage' => $trade_type == 'futures' ? $leverage : 1,
                        'pnl' => 0, // Initial PNL is 0
                        'status' => 'open',
                        'environment' => $webhook_data['environment'],
                        'webhook_data' => json_encode($webhook_data)
                    );
                    
                    $trade_id = $this->Trade_model->add_trade($trade_data);
                    
                    // Log action
                    $log_data = array(
                        'user_id' => $webhook_data['user_id'],
                        'action' => 'open_trade',
                        'description' => 'Opened ' . $trade_type . ' ' . $webhook_data['action'] . ' position for ' . $webhook_data['ticker'] . 
                                       ' via simulation (Strategy: ' . $strategy->name . ')'
                    );
                    $this->Log_model->add_log($log_data);
                    
                    $this->session->set_flashdata('success', 'Order executed successfully');
                } else {
                    $this->session->set_flashdata('error', 'Failed to execute order');
                }
                break;
                
            case 'CLOSE':
                // Find open trade by symbol and strategy
                $trades = $this->Trade_model->get_all_trades($webhook_data['user_id'], 'open', $webhook_data['environment']);
                $trade_to_close = null;
                
                foreach ($trades as $trade) {
                    if ($trade->symbol == $webhook_data['ticker'] && $trade->strategy_id == $strategy->id) {
                        $trade_to_close = $trade;
                        break;
                    }
                }
                
                if (!$trade_to_close) {
                    $this->session->set_flashdata('error', 'No open trade found to close');
                    redirect('dashboard');
                }
                
                // Close position
                if ($trade_to_close->trade_type == 'futures') {
                    $result = $this->bingxapi->close_futures_position(
                        $api_key, 
                        $trade_to_close->symbol, 
                        $trade_to_close->side, 
                        $trade_to_close->quantity
                    );
                } else {
                    $result = $this->bingxapi->close_spot_position(
                        $api_key, 
                        $trade_to_close->symbol, 
                        $trade_to_close->side, 
                        $trade_to_close->quantity
                    );
                }
                
                if ($result && isset($result->price)) {
                    // Calculate PNL
                    $exit_price = $result->price;
                    
                    if ($trade_to_close->side == 'BUY') {
                        $pnl = ($exit_price - $trade_to_close->entry_price) * $trade_to_close->quantity * $trade_to_close->leverage;
                    } else {
                        $pnl = ($trade_to_close->entry_price - $exit_price) * $trade_to_close->quantity * $trade_to_close->leverage;
                    }
                    
                    // Update trade
                    $this->Trade_model->close_trade($trade_to_close->id, $exit_price, $pnl);
                    
                    // Log action
                    $log_data = array(
                        'user_id' => $webhook_data['user_id'],
                        'action' => 'close_trade',
                        'description' => 'Closed trade for ' . $trade_to_close->symbol . ' with PNL: ' . 
                                       number_format($pnl, 2) . ' via simulation (Strategy: ' . $strategy->name . ')'
                    );
                    $this->Log_model->add_log($log_data);
                    
                    $this->session->set_flashdata('success', 'Position closed successfully');
                } else {
                    $this->session->set_flashdata('error', 'Failed to close position');
                }
                break;
                
            default:
                $this->session->set_flashdata('error', 'Invalid action');
                break;
        }
        
        redirect('dashboard');
    }
}