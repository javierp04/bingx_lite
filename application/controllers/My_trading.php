<?php
defined('BASEPATH') or exit('No direct script access allowed');

class My_trading extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();

        // Check if user is logged in
        if (!$this->session->userdata('logged_in')) {
            redirect('auth');
        }

        $this->load->model('User_tickers_model');
        $this->load->model('Telegram_signals_model');
    }

    public function index($tab = 'signals')
    {
        $data['title'] = 'My Trading';
        $user_id = $this->session->userdata('user_id');
        $data['active_tab'] = $tab;
        
        // Always load basic stats for header cards
        $data['stats'] = $this->get_basic_stats($user_id);
        
        switch($tab) {
            case 'tickers':
                // Get user's selected tickers
                $data['selected_tickers'] = $this->User_tickers_model->get_user_selected_tickers($user_id);
                // Get available tickers that user hasn't selected yet
                $data['available_tickers'] = $this->User_tickers_model->get_available_tickers_for_user($user_id);
                break;
                
            default: // signals
                // Get filter params
                $filters = array();
                $filters['ticker_symbol'] = $this->input->get('ticker_symbol') ?: '';
                $filters['status'] = $this->input->get('status');
                $filters['date_from'] = $this->input->get('date_from') ?: '';
                $filters['date_to'] = $this->input->get('date_to') ?: '';
                
                // Get user signals with filters
                $data['signals'] = $this->Telegram_signals_model->get_user_signals_with_filters($user_id, $filters);
                $data['filters'] = $filters;
                
                // Get user's active tickers for filter dropdown
                $data['user_tickers'] = $this->User_tickers_model->get_user_selected_tickers($user_id, true);
        }
        
        $this->load->view('templates/header', $data);
        $this->load->view('my_trading/index', $data);
        $this->load->view('templates/footer');
    }

    // AJAX endpoint - Add ticker
    public function add_ticker()
    {
        $user_id = $this->session->userdata('user_id');
        $ticker_symbol = $this->input->post('ticker_symbol');
        $mt_ticker = $this->input->post('mt_ticker');

        if (empty($ticker_symbol)) {
            http_response_code(400);
            echo json_encode(['error' => 'No ticker selected']);
            return;
        }

        if ($this->User_tickers_model->add_user_ticker_with_mt($user_id, $ticker_symbol, $mt_ticker)) {
            http_response_code(200);
            echo json_encode(['success' => true]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to add ticker']);
        }
    }

    // AJAX endpoint - Update MT ticker
    public function update_mt_ticker()
    {
        $user_id = $this->session->userdata('user_id');
        $ticker_symbol = $this->input->post('ticker_symbol');
        $mt_ticker = $this->input->post('mt_ticker');

        if (empty($ticker_symbol)) {
            http_response_code(400);
            echo json_encode(['error' => 'No ticker specified']);
            return;
        }

        if ($this->User_tickers_model->update_user_mt_ticker($user_id, $ticker_symbol, $mt_ticker)) {
            http_response_code(200);
            echo json_encode(['success' => true]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to update MT ticker']);
        }
    }

    // Remove ticker
    public function remove_ticker($ticker_symbol)
    {
        $user_id = $this->session->userdata('user_id');

        if ($this->User_tickers_model->remove_user_ticker($user_id, $ticker_symbol)) {
            $this->session->set_flashdata('success', "Ticker {$ticker_symbol} removed from your selection");
        } else {
            $this->session->set_flashdata('error', "Failed to remove ticker {$ticker_symbol}");
        }

        redirect('my_trading/tickers');
    }

    // Toggle ticker active status
    public function toggle_ticker($ticker_symbol)
    {
        $user_id = $this->session->userdata('user_id');

        // Check if user has this ticker
        if (!$this->User_tickers_model->user_has_ticker($user_id, $ticker_symbol)) {
            $this->session->set_flashdata('error', 'Ticker not found in your selection');
            redirect('my_trading/tickers');
        }

        // Get current status
        $selected_tickers = $this->User_tickers_model->get_user_selected_tickers($user_id);
        $current_status = true;
        foreach ($selected_tickers as $ticker) {
            if ($ticker->ticker_symbol == $ticker_symbol) {
                $current_status = $ticker->active;
                break;
            }
        }

        // Toggle status
        $new_status = !$current_status;
        if ($this->User_tickers_model->toggle_user_ticker_status($user_id, $ticker_symbol, $new_status)) {
            $status_text = $new_status ? 'activated' : 'deactivated';
            $this->session->set_flashdata('success', "Ticker {$ticker_symbol} {$status_text}");
        } else {
            $this->session->set_flashdata('error', "Failed to toggle ticker {$ticker_symbol}");
        }

        redirect('my_trading/tickers');
    }

    // View signal detail
    public function signal_detail($user_signal_id)
    {
        $user_id = $this->session->userdata('user_id');
        $data['title'] = 'Signal Detail';
        
        $data['signal'] = $this->Telegram_signals_model->get_user_signal_detail($user_id, $user_signal_id);
        
        if (!$data['signal']) {
            $this->session->set_flashdata('error', 'Signal not found');
            redirect('my_trading/signals');
        }
        
        $this->load->view('templates/header', $data);
        $this->load->view('my_trading/signal_detail', $data);
        $this->load->view('templates/footer');
    }

    // Private helper method - only basic stats
    private function get_basic_stats($user_id)
    {
        $stats = array();
        
        // Active tickers count
        $active_tickers = $this->User_tickers_model->get_user_selected_tickers($user_id, true);
        $stats['active_tickers'] = count($active_tickers);
        
        // Today's signals
        $stats['signals_today'] = $this->Telegram_signals_model->count_user_signals_today($user_id);
        
        // Pending signals
        $stats['pending_signals'] = $this->Telegram_signals_model->count_user_signals_by_status($user_id, 'pending');
        
        // Execution rate (executed vs total non-pending)
        $total_processed = $this->Telegram_signals_model->count_user_signals_processed($user_id);
        $executed = $this->Telegram_signals_model->count_user_signals_by_status($user_id, 'executed');
        $stats['execution_rate'] = $total_processed > 0 ? round(($executed / $total_processed) * 100, 1) : 0;
        
        return $stats;
    }
}