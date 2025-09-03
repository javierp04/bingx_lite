<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Available_tickers extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();

        // Check if user is logged in
        if (!$this->session->userdata('logged_in')) {
            redirect('auth');
        }

        // Check if user is admin
        if ($this->session->userdata('role') !== 'admin') {
            $this->session->set_flashdata('error', 'Access denied. Admin privileges required.');
            redirect('dashboard');
        }

        $this->load->model('User_tickers_model');
    }

    public function index()
    {
        $data['title'] = 'Available Tickers Management';
        
        // Get all available tickers
        $data['tickers'] = $this->User_tickers_model->get_all_available_tickers(false); // Include inactive
        
        $this->load->view('templates/header', $data);
        $this->load->view('available_tickers/index', $data);
        $this->load->view('templates/footer');
    }

    public function add()
    {
        $data['title'] = 'Add Available Ticker';

        // Form validation
        $this->form_validation->set_rules('symbol', 'Symbol', 'required|alpha_dash|is_unique[available_tickers.symbol]');
        $this->form_validation->set_rules('name', 'Name', 'required');

        if ($this->form_validation->run() === FALSE) {
            $this->load->view('templates/header', $data);
            $this->load->view('available_tickers/add', $data);
            $this->load->view('templates/footer');
        } else {
            // Add ticker
            $ticker_data = array(
                'symbol' => strtoupper($this->input->post('symbol')),
                'name' => $this->input->post('name'),
                'active' => $this->input->post('active') ? 1 : 0
            );

            $this->User_tickers_model->add_available_ticker($ticker_data);

            // Log action
            $this->Log_model->add_log([
                'user_id' => $this->session->userdata('user_id'),
                'action' => 'add_ticker',
                'description' => 'Added available ticker: ' . $ticker_data['symbol'] . ' (' . $ticker_data['name'] . ')'
            ]);

            $this->session->set_flashdata('success', 'Ticker added successfully');
            redirect('available_tickers');
        }
    }

    public function edit($symbol)
    {
        $data['title'] = 'Edit Available Ticker';
        $data['ticker'] = $this->User_tickers_model->get_available_ticker($symbol);

        if (empty($data['ticker'])) {
            $this->session->set_flashdata('error', 'Ticker not found');
            redirect('available_tickers');
        }

        // Form validation
        $this->form_validation->set_rules('symbol', 'Symbol', 'required|alpha_dash');
        $this->form_validation->set_rules('name', 'Name', 'required');

        // Check if symbol changed and is unique
        if ($this->input->post('symbol') !== $symbol) {
            $this->form_validation->set_rules('symbol', 'Symbol', 'required|alpha_dash|is_unique[available_tickers.symbol]');
        }

        if ($this->form_validation->run() === FALSE) {
            $this->load->view('templates/header', $data);
            $this->load->view('available_tickers/edit', $data);
            $this->load->view('templates/footer');
        } else {
            $new_symbol = strtoupper($this->input->post('symbol'));
            
            // Update ticker
            $ticker_data = array(
                'symbol' => $new_symbol,
                'name' => $this->input->post('name'),
                'active' => $this->input->post('active') ? 1 : 0
            );

            // If symbol changed, we need special handling
            if ($new_symbol !== $symbol) {
                // This is complex because symbol is the PK, so we need to:
                // 1. Create new record
                // 2. Update foreign keys
                // 3. Delete old record
                $this->db->trans_start();
                
                // Insert new ticker
                $this->User_tickers_model->add_available_ticker($ticker_data);
                
                // Update foreign keys in related tables
                $this->db->where('ticker_symbol', $symbol);
                $this->db->update('user_selected_tickers', ['ticker_symbol' => $new_symbol]);
                
                $this->db->where('ticker_symbol', $symbol);
                $this->db->update('telegram_signals', ['ticker_symbol' => $new_symbol]);
                
                // Delete old ticker
                $this->db->where('symbol', $symbol);
                $this->db->delete('available_tickers');
                
                $this->db->trans_complete();
                
                if ($this->db->trans_status() === FALSE) {
                    $this->session->set_flashdata('error', 'Error updating ticker symbol');
                    redirect('available_tickers/edit/' . $symbol);
                }
            } else {
                $this->User_tickers_model->update_available_ticker($symbol, $ticker_data);
            }

            // Log action
            $this->Log_model->add_log([
                'user_id' => $this->session->userdata('user_id'),
                'action' => 'edit_ticker',
                'description' => 'Updated available ticker: ' . $symbol . ' -> ' . $new_symbol
            ]);

            $this->session->set_flashdata('success', 'Ticker updated successfully');
            redirect('available_tickers');
        }
    }

    public function toggle($symbol)
    {
        $ticker = $this->User_tickers_model->get_available_ticker($symbol);

        if (empty($ticker)) {
            $this->session->set_flashdata('error', 'Ticker not found');
            redirect('available_tickers');
        }

        // Toggle active status
        $new_status = !$ticker->active;
        $this->User_tickers_model->toggle_ticker_status($symbol, $new_status);

        // Log action
        $this->Log_model->add_log([
            'user_id' => $this->session->userdata('user_id'),
            'action' => 'toggle_ticker',
            'description' => 'Toggled ticker status: ' . $symbol . ' -> ' . ($new_status ? 'Active' : 'Inactive')
        ]);

        $status_text = $new_status ? 'activated' : 'deactivated';
        $this->session->set_flashdata('success', "Ticker {$symbol} {$status_text} successfully");
        redirect('available_tickers');
    }

    public function delete($symbol)
    {
        $ticker = $this->User_tickers_model->get_available_ticker($symbol);

        if (empty($ticker)) {
            $this->session->set_flashdata('error', 'Ticker not found');
            redirect('available_tickers');
        }

        // Check if ticker is being used by users or has signals
        $this->db->where('ticker_symbol', $symbol);
        $user_selections = $this->db->count_all_results('user_selected_tickers');

        $this->db->where('ticker_symbol', $symbol);
        $signals = $this->db->count_all_results('telegram_signals');

        if ($user_selections > 0 || $signals > 0) {
            $this->session->set_flashdata('error', 'Cannot delete ticker: it has user selections or telegram signals. Deactivate it instead.');
            redirect('available_tickers');
        }

        // Delete ticker
        $this->db->where('symbol', $symbol);
        $this->db->delete('available_tickers');

        // Log action
        $this->Log_model->add_log([
            'user_id' => $this->session->userdata('user_id'),
            'action' => 'delete_ticker',
            'description' => 'Deleted available ticker: ' . $symbol
        ]);

        $this->session->set_flashdata('success', 'Ticker deleted successfully'); 
        redirect('available_tickers');
    }
}