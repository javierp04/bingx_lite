<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Telegram_signals extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();

        // Check if user is logged in
        if (!$this->session->userdata('logged_in')) {
            redirect('auth');
        }

        $this->load->model('Telegram_signals_model');
        $this->load->model('User_tickers_model');
    }

    public function index()
    {
        $data['title'] = 'Telegram Signals';

        // Get filter params
        $filters = array();
        $filters['ticker_symbol'] = $this->input->get('ticker_symbol') ?: '';
        $filters['processed'] = $this->input->get('processed');
        $filters['date_from'] = $this->input->get('date_from') ?: '';
        $filters['date_to'] = $this->input->get('date_to') ?: '';

        // Get signals with filters
        $data['signals'] = $this->Telegram_signals_model->get_signals_with_filters($filters);

        // Get filter options
        $data['available_tickers'] = $this->User_tickers_model->get_all_available_tickers(true);
        $data['filters'] = $filters;

        // Get stats
        $data['stats'] = array(
            'total' => $this->Telegram_signals_model->count_signals_by_status(),
            'pending' => $this->Telegram_signals_model->count_signals_by_status(0),
            'processed' => $this->Telegram_signals_model->count_signals_by_status(1),
            'last_24h' => $this->Telegram_signals_model->count_signals_last_24h()
        );

        // Get ticker stats
        $data['ticker_stats'] = $this->Telegram_signals_model->get_ticker_stats(7);

        $this->load->view('templates/header', $data);
        $this->load->view('telegram_signals/index', $data);
        $this->load->view('templates/footer');
    }

    public function view($id)
    {
        $data['title'] = 'View Telegram Signal';
        $data['signal'] = $this->Telegram_signals_model->get_signal_by_id($id);

        if (!$data['signal']) {
            $this->session->set_flashdata('error', 'Signal not found');
            redirect('telegram_signals');
        }

        $this->load->view('templates/header', $data);
        $this->load->view('telegram_signals/view', $data);
        $this->load->view('templates/footer');
    }

    public function delete($id)
    {
        $signal = $this->Telegram_signals_model->get_signal_by_id($id);

        if (!$signal) {
            $this->session->set_flashdata('error', 'Signal not found');
            redirect('telegram_signals');
        }

        // Delete image file if exists
        if (file_exists($signal->image_path)) {
            unlink($signal->image_path);
        }

        if ($this->Telegram_signals_model->delete_signal($id)) {
            $this->session->set_flashdata('success', 'Signal deleted successfully');
        } else {
            $this->session->set_flashdata('error', 'Failed to delete signal');
        }

        redirect('telegram_signals');
    }

    public function cleanup()
    {
        // Only admin can cleanup
        if ($this->session->userdata('role') !== 'admin') {
            $this->session->set_flashdata('error', 'Access denied');
            redirect('telegram_signals');
        }

        $days = (int)$this->input->post('days') ?: 30;
        $deleted = $this->Telegram_signals_model->cleanup_old_signals($days);

        $this->Log_model->add_log([
            'user_id' => $this->session->userdata('user_id'),
            'action' => 'telegram_cleanup',
            'description' => "Cleaned up {$deleted} telegram signals older than {$days} days"
        ]);

        $this->session->set_flashdata('success', "Deleted {$deleted} old signals");
        redirect('telegram_signals');
    }

    public function view_image($id)
    {
        $signal = $this->Telegram_signals_model->get_signal_by_id($id);

        if (!$signal || !file_exists($signal->image_path)) {
            $this->session->set_flashdata('error', 'Image not found');
            redirect('telegram_signals');
        }

        // Get image info
        $image_info = pathinfo($signal->image_path);
        $mime_type = 'image/png'; // Default to PNG

        // Set proper headers
        header('Content-Type: ' . $mime_type);
        header('Content-Length: ' . filesize($signal->image_path));
        header('Content-Disposition: inline; filename="' . basename($signal->image_path) . '"');

        // Output image
        readfile($signal->image_path);
    }

    // API endpoint for MetaTrader EA to get signals
    public function api_get_signals($user_id)
    {
        // Validate user_id
        if (!is_numeric($user_id)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid user ID']);
            return;
        }

        // Get hours limit from query parameter (default 2 hours)
        $hours = (int)$this->input->get('hours') ?: 2;
        
        try {
            // Get pending signals for user
            $signals = $this->Telegram_signals_model->get_pending_signals_for_user($user_id, $hours);
            
            $response_signals = array();
            foreach ($signals as $signal) {
                $response_signals[] = array(
                    'id' => $signal->id,
                    'ticker_symbol' => $signal->ticker_symbol,
                    'ticker_name' => $signal->ticker_name,
                    'image_path' => base_url($signal->image_path),
                    'tradingview_url' => $signal->tradingview_url,
                    'created_at' => $signal->created_at
                );
            }
            
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'signals' => $response_signals,
                'count' => count($response_signals),
                'hours_limit' => $hours
            ]);
            
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Internal server error']);
        }
    }   
}