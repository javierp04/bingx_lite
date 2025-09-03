<?php
defined('BASEPATH') or exit('No direct script access allowed');

class My_tickers extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();

        // Check if user is logged in
        if (!$this->session->userdata('logged_in')) {
            redirect('auth');
        }

        $this->load->model('User_tickers_model');
    }

    public function index()
    {
        $data['title'] = 'My Trading Tickers';
        $user_id = $this->session->userdata('user_id');
        
        // Get user's selected tickers
        $data['selected_tickers'] = $this->User_tickers_model->get_user_selected_tickers($user_id, false); // Include inactive
        
        // Get available tickers that user hasn't selected yet
        $data['available_tickers'] = $this->User_tickers_model->get_available_tickers_for_user($user_id);
        
        $this->load->view('templates/header', $data);
        $this->load->view('my_tickers/index', $data);
        $this->load->view('templates/footer');
    }

    public function add_ticker()
    {
        $user_id = $this->session->userdata('user_id');
        $ticker_symbol = $this->input->post('ticker_symbol');

        if (empty($ticker_symbol)) {
            $this->session->set_flashdata('error', 'No ticker selected');
            redirect('my_tickers');
        }

        if ($this->User_tickers_model->add_user_ticker($user_id, $ticker_symbol)) {
            $this->session->set_flashdata('success', "Ticker {$ticker_symbol} added to your selection");
        } else {
            $this->session->set_flashdata('error', "Failed to add ticker {$ticker_symbol}");
        }

        redirect('my_tickers');
    }

    public function remove_ticker($ticker_symbol)
    {
        $user_id = $this->session->userdata('user_id');

        if ($this->User_tickers_model->remove_user_ticker($user_id, $ticker_symbol)) {
            $this->session->set_flashdata('success', "Ticker {$ticker_symbol} removed from your selection");
        } else {
            $this->session->set_flashdata('error', "Failed to remove ticker {$ticker_symbol}");
        }

        redirect('my_tickers');
    }

    public function toggle_ticker($ticker_symbol)
    {
        $user_id = $this->session->userdata('user_id');

        // Check if user has this ticker
        if (!$this->User_tickers_model->user_has_ticker($user_id, $ticker_symbol)) {
            $this->session->set_flashdata('error', 'Ticker not found in your selection');
            redirect('my_tickers');
        }

        // Get current status
        $selected_tickers = $this->User_tickers_model->get_user_selected_tickers($user_id, false);
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

        redirect('my_tickers');
    }

    public function bulk_update()
    {
        $user_id = $this->session->userdata('user_id');
        $selected_tickers = $this->input->post('selected_tickers');

        if (!is_array($selected_tickers)) {
            $selected_tickers = array();
        }

        if ($this->User_tickers_model->update_user_tickers($user_id, $selected_tickers)) {
            $count = count($selected_tickers);
            $this->session->set_flashdata('success', "Updated ticker selection: {$count} tickers selected");
        } else {
            $this->session->set_flashdata('error', 'Failed to update ticker selection');
        }

        redirect('my_tickers');
    }
}