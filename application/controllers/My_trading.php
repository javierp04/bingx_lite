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

    public function index($tab = 'active')
    {
        $data['title'] = 'My Trading';
        $user_id = $this->session->userdata('user_id');
        $data['active_tab'] = $tab;

        switch ($tab) {
            case 'active':
                // Get filter params
                $filters = array();
                $filters['status_filter'] = $this->input->get('status_filter') ?: '';
                $filters['ticker_filter'] = $this->input->get('ticker_filter') ?: '';
                $filters['date_range'] = $this->input->get('date_range') ?: '7';
                $filters['pnl_filter'] = $this->input->get('pnl_filter') ?: '';

                // Get dashboard signals with filters
                $data['dashboard_signals'] = $this->Telegram_signals_model->get_trading_dashboard_signals($user_id, $filters);
                $data['filters'] = $filters;

                // Get user's active tickers for filter dropdown
                $data['user_tickers'] = $this->User_tickers_model->get_user_selected_tickers($user_id, true);
                break;

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

    // View trading detail (renamed from signal_detail)
    public function trading_detail($user_signal_id)
    {
        $user_id = $this->session->userdata('user_id');
        $data['title'] = 'Trading Detail';

        $data['signal'] = $this->Telegram_signals_model->get_user_signal_detail($user_id, $user_signal_id);

        if (!$data['signal']) {
            $this->session->set_flashdata('error', 'Signal not found');
            redirect('my_trading/active');
        }

        $this->load->view('templates/header', $data);
        $this->load->view('my_trading/trading_detail', $data);
        $this->load->view('templates/footer');
    }

    // Mantener compatibilidad con enlaces antiguos
    public function signal_detail($user_signal_id)
    {
        redirect('my_trading/trading_detail/' . $user_signal_id);
    }

    // Método para AJAX refresh del dashboard completo
    public function refresh_dashboard_ajax()
    {
        // Solo permitir AJAX
        if (!$this->input->is_ajax_request()) {
            show_404();
            return;
        }

        $user_id = $this->session->userdata('user_id');

        // Get filter params from query string
        $filters = array();
        $filters['status_filter'] = $this->input->get('status_filter') ?: '';
        $filters['ticker_filter'] = $this->input->get('ticker_filter') ?: '';
        $filters['date_range'] = $this->input->get('date_range') ?: '7';
        $filters['pnl_filter'] = $this->input->get('pnl_filter') ?: '';

        $dashboard_signals = $this->Telegram_signals_model->get_trading_dashboard_signals($user_id, $filters);

        // Calculate statistics
        $stats = $this->calculate_dashboard_stats($dashboard_signals);

        // Prepare data for view
        $data['dashboard_signals'] = $dashboard_signals;

        // Render complete dashboard content (table + stats)
        $content_html = $this->load->view('my_trading/partials/dashboard_content', $data, TRUE);

        // Return JSON response
        http_response_code(200);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'content_html' => $content_html,
            'count' => count($dashboard_signals),
            'timestamp' => date('Y-m-d H:i:s'),
            'stats' => $stats
        ]);
    }

    private function calculate_dashboard_stats($dashboard_signals)
    {
        $active_signals = array_filter($dashboard_signals, function ($s) {
            return in_array($s->status, ['pending', 'claimed', 'open']);
        });
        
        $closed_signals = array_filter($dashboard_signals, function ($s) {
            return $s->status === 'closed';
        });
        
        $profitable_signals = array_filter($dashboard_signals, function ($s) {
            return $s->gross_pnl > 0;
        });
        
        $total_pnl = array_sum(array_column($dashboard_signals, 'gross_pnl'));
        
        $win_rate = count($closed_signals) > 0 ? (count($profitable_signals) / count($closed_signals)) * 100 : 0;
        
        return [
            'active_count' => count($active_signals),
            'closed_count' => count($closed_signals),
            'profitable_count' => count($profitable_signals),
            'win_rate' => $win_rate,
            'total_pnl' => $total_pnl
        ];
    }
}
