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

        // NUEVO: Check if user is admin - SECURIZACIÓN
        if ($this->session->userdata('role') !== 'admin') {
            $this->session->set_flashdata('error', 'Access denied. This section is for administrators only. Use "My Trading" to view your signals.');
            redirect('my_trading/signals'); // Redirigir a su vista personal
        }

        $this->load->model('Telegram_signals_model');
        $this->load->model('User_tickers_model');
        $this->load->model('Log_model');
    }

    public function index()
    {
        $data['title'] = 'Telegram Signals (Admin)';

        // Get filter params
        $filters = array();
        $filters['ticker_symbol'] = $this->input->get('ticker_symbol') ?: '';
        $filters['status'] = $this->input->get('status');
        $filters['date_from'] = $this->input->get('date_from') ?: '';
        $filters['date_to'] = $this->input->get('date_to') ?: '';

        // Get signals with filters
        $data['signals'] = $this->Telegram_signals_model->get_signals_with_filters($filters);

        // Get filter options
        $data['available_tickers'] = $this->User_tickers_model->get_all_available_tickers(true);
        $data['filters'] = $filters;

        // Get stats
        $data['stats'] = array(
            'total' => $this->Telegram_signals_model->count_signals_total(),
            'completed' => $this->Telegram_signals_model->count_signals_completed(),
            'failed' => $this->Telegram_signals_model->count_signals_failed(),
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
        $data['title'] = 'View Telegram Signal (Admin)';
        $data['signal'] = $this->Telegram_signals_model->get_signal_by_id($id);

        if (!$data['signal']) {
            $this->session->set_flashdata('error', 'Signal not found');
            redirect('telegram_signals');
        }

        // Get users trading this ticker
        $data['trading_users'] = $this->Telegram_signals_model->get_users_trading_ticker($data['signal']->ticker_symbol);
        
        // Get recent signals for this ticker
        $data['recent_signals'] = $this->Telegram_signals_model->get_recent_signals_by_ticker(
            $data['signal']->ticker_symbol, 
            $data['signal']->id,
            5
        );

        // Check if cropped image exists
        $data['cropped_image_exists'] = $this->get_cropped_image_path($data['signal']->image_path) ? true : false;

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

        // Delete image files if they exist
        if (file_exists($signal->image_path)) {
            unlink($signal->image_path);
        }

        // Delete cropped image if exists
        $cropped_path = $this->get_cropped_image_path($signal->image_path);
        if ($cropped_path && file_exists($cropped_path)) {
            unlink($cropped_path);
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
        // Already admin-only due to constructor check
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

        $this->serve_image($signal->image_path);
    }

    public function view_cropped_image($id)
    {
        $signal = $this->Telegram_signals_model->get_signal_by_id($id);

        if (!$signal) {
            $this->session->set_flashdata('error', 'Signal not found');
            redirect('telegram_signals');
        }

        $cropped_path = $this->get_cropped_image_path($signal->image_path);

        if (!$cropped_path || !file_exists($cropped_path)) {
            $this->session->set_flashdata('error', 'Cropped image not found');
            redirect('telegram_signals');
        }

        $this->serve_image($cropped_path);
    }

    private function serve_image($image_path)
    {
        // Get image info
        $image_info = pathinfo($image_path);
        $mime_type = 'image/png'; // Default to PNG

        // Set proper headers
        header('Content-Type: ' . $mime_type);
        header('Content-Length: ' . filesize($image_path));
        header('Content-Disposition: inline; filename="' . basename($image_path) . '"');

        // Output image
        readfile($image_path);
    }

    private function get_cropped_image_path($original_path)
    {
        // Convert uploads/trades/2025-01-01_SYMBOL.png to uploads/trades/cropped-2025-01-01_SYMBOL.png
        $path_info = pathinfo($original_path);
        $cropped_filename = 'cropped-' . $path_info['filename'] . '.' . $path_info['extension'];
        $cropped_path = $path_info['dirname'] . '/' . $cropped_filename;
        
        return file_exists($cropped_path) ? $cropped_path : null;
    }
}