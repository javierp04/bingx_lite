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
        
        // Get filter params
        $status = $this->input->get('status');
        $strategy = $this->input->get('strategy');
        
        // Get all strategies for filter dropdown
        $data['strategies'] = $this->Strategy_model->get_all_strategies($user_id);
        
        // Get all trades with filters
        $data['trades'] = $this->Trade_model->get_all_trades($user_id, $status, $strategy);
        
        // Calculate trading statistics
        $stats = $this->calculate_trade_statistics($data['trades']);
        $data['stats'] = $stats;
        
        $this->load->view('templates/header', $data);
        $this->load->view('trades/index', $data);
        $this->load->view('templates/footer');
    }
    
    /**
     * Calculate trading statistics from trades array
     * 
     * @param array $trades List of trades
     * @return array Statistics array
     */
    private function calculate_trade_statistics($trades) {
        // Initialize statistics
        $stats = [
            'total_pnl' => 0,
            'total_pnl_percentage' => 0,
            'total_invested' => 0,
            'winrate' => 0,
            'profit_per_trade' => 0,
            'total_trades' => 0,
            'winning_trades' => 0,
            'losing_trades' => 0
        ];
        
        if (empty($trades)) {
            return $stats;
        }
        
        $closed_trades = array_filter($trades, function($trade) {
            return $trade->status == 'closed';
        });
        
        $stats['total_trades'] = count($closed_trades);
        
        if ($stats['total_trades'] == 0) {
            return $stats;
        }
        
        foreach ($closed_trades as $trade) {
            // Calculate total PNL
            $stats['total_pnl'] += $trade->pnl;
            
            // Count winning trades
            if ($trade->pnl > 0) {
                $stats['winning_trades']++;
            } else {
                $stats['losing_trades']++;
            }
            
            // Calculate real investment (considering leverage)
            $real_investment = ($trade->quantity * $trade->entry_price) / $trade->leverage;
            $stats['total_invested'] += $real_investment;
            
            // Store PNL percentage for each trade
            if ($real_investment > 0) {
                $stats['trades_pnl_percentages'][] = [
                    'pnl_percentage' => ($trade->pnl / $real_investment) * 100,
                    'investment' => $real_investment
                ];
            }
        }
        
        // Calculate winrate
        $stats['winrate'] = ($stats['winning_trades'] / $stats['total_trades']) * 100;
        
        // Calculate profit per trade
        $stats['profit_per_trade'] = $stats['total_pnl'] / $stats['total_trades'];
        
        // Calculate weighted PNL percentage (weighted by investment size)
        if ($stats['total_invested'] > 0) {
            $weighted_percentage = 0;
            foreach ($stats['trades_pnl_percentages'] as $trade_pnl) {
                $weighted_percentage += ($trade_pnl['investment'] / $stats['total_invested']) * $trade_pnl['pnl_percentage'];
            }
            $stats['total_pnl_percentage'] = $weighted_percentage;
        }
        
        // Remove temporary data
        unset($stats['trades_pnl_percentages']);
        
        return $stats;
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
        $api_key = $this->Api_key_model->get_api_key($user_id);
        
        if (empty($api_key)) {
            $this->session->set_flashdata('error', 'API key not configured for ' . $trade->environment);
            redirect('trades');
        }
        
        // Load BingX API library
        $this->load->library('BingxApi');
        
        // Set correct environment
        $this->bingxapi->set_environment($trade->environment);
        
        // Close trade
        if ($trade->trade_type == 'futures') {
            $result = $this->bingxapi->close_futures_position(
                $api_key,
                $trade->symbol,
                $trade->side,
                $trade->quantity
            );
        } else {
            $result = $this->bingxapi->close_spot_position(
                $api_key,
                $trade->symbol,
                $trade->side,
                $trade->quantity
            );
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
            $this->session->set_flashdata('error', 'Failed to close trade: ' . $this->bingxapi->get_last_error());
        }
        
        redirect('dashboard');
    }
}