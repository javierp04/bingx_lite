<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Strategies extends CI_Controller {
    
    public function __construct() {
        parent::__construct();
        
        // Check if user is logged in
        if (!$this->session->userdata('logged_in')) {
            redirect('auth');
        }
    }
    
    public function index() {
        $data['title'] = 'Trading Strategies';
        $user_id = $this->session->userdata('user_id');
        
        // Get strategies
        $data['strategies'] = $this->Strategy_model->get_all_strategies($user_id);
        
        $this->load->view('templates/header', $data);
        $this->load->view('strategies/index', $data);
        $this->load->view('templates/footer');
    }
    
    public function add() {
        $data['title'] = 'Add Strategy';
        
        // Form validation
        $this->form_validation->set_rules('strategy_id', 'Strategy ID', 'required');
        $this->form_validation->set_rules('name', 'Name', 'required');
        $this->form_validation->set_rules('type', 'Type', 'required');
        
        if ($this->form_validation->run() === FALSE) {
            $this->load->view('templates/header', $data);
            $this->load->view('strategies/add', $data);
            $this->load->view('templates/footer');
        } else {
            $user_id = $this->session->userdata('user_id');
            
            // Add strategy
            $strategy_data = array(
                'user_id' => $user_id,
                'strategy_id' => $this->input->post('strategy_id'),
                'name' => $this->input->post('name'),
                'type' => $this->input->post('type'),
                'description' => $this->input->post('description'),
                'active' => $this->input->post('active') ? 1 : 0
            );
            
            $this->Strategy_model->add_strategy($strategy_data);
            
            // Log action
            $log_data = array(
                'user_id' => $user_id,
                'action' => 'add_strategy',
                'description' => 'Added strategy: ' . $this->input->post('name')
            );
            $this->Log_model->add_log($log_data);
            
            $this->session->set_flashdata('success', 'Strategy added successfully');
            redirect('strategies');
        }
    }
    
    public function edit($id) {
        $data['title'] = 'Edit Strategy';
        $data['strategy'] = $this->Strategy_model->get_strategy_by_id($id);
        
        // Check if strategy exists and belongs to user
        if (empty($data['strategy']) || $data['strategy']->user_id != $this->session->userdata('user_id')) {
            $this->session->set_flashdata('error', 'Strategy not found or access denied');
            redirect('strategies');
        }
        
        // Form validation
        $this->form_validation->set_rules('strategy_id', 'Strategy ID', 'required');
        $this->form_validation->set_rules('name', 'Name', 'required');
        $this->form_validation->set_rules('type', 'Type', 'required');
        
        if ($this->form_validation->run() === FALSE) {
            $this->load->view('templates/header', $data);
            $this->load->view('strategies/edit', $data);
            $this->load->view('templates/footer');
        } else {
            // Update strategy
            $strategy_data = array(
                'strategy_id' => $this->input->post('strategy_id'),
                'name' => $this->input->post('name'),
                'type' => $this->input->post('type'),
                'description' => $this->input->post('description'),
                'active' => $this->input->post('active') ? 1 : 0
            );
            
            $this->Strategy_model->update_strategy($id, $strategy_data);
            
            // Log action
            $log_data = array(
                'user_id' => $this->session->userdata('user_id'),
                'action' => 'edit_strategy',
                'description' => 'Updated strategy: ' . $this->input->post('name')
            );
            $this->Log_model->add_log($log_data);
            
            $this->session->set_flashdata('success', 'Strategy updated successfully');
            redirect('strategies');
        }
    }
    
    public function delete($id) {
        $strategy = $this->Strategy_model->get_strategy_by_id($id);
        
        // Check if strategy exists and belongs to user
        if (empty($strategy) || $strategy->user_id != $this->session->userdata('user_id')) {
            $this->session->set_flashdata('error', 'Strategy not found or access denied');
            redirect('strategies');
        }
        
        // Check if strategy has active trades
        $trades = $this->Trade_model->get_all_trades(null, 'open', null);
        foreach ($trades as $trade) {
            if ($trade->strategy_id == $id) {
                $this->session->set_flashdata('error', 'Cannot delete strategy with active trades');
                redirect('strategies');
            }
        }
        
        // Delete strategy
        $this->Strategy_model->delete_strategy($id);
        
        // Log action
        $log_data = array(
            'user_id' => $this->session->userdata('user_id'),
            'action' => 'delete_strategy',
            'description' => 'Deleted strategy: ' . $strategy->name
        );
        $this->Log_model->add_log($log_data);
        
        $this->session->set_flashdata('success', 'Strategy deleted successfully');
        redirect('strategies');
    }
}