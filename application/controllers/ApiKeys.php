<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class ApiKeys extends CI_Controller {
    
    public function __construct() {
        parent::__construct();
        
        // Check if user is logged in
        if (!$this->session->userdata('logged_in')) {
            redirect('auth');
        }
    }
    
    public function index() {
        $data['title'] = 'API Keys Configuration';
        $user_id = $this->session->userdata('user_id');
        
        // Get API keys
        $data['api_key'] = $this->Api_key_model->get_api_key($user_id);
        
        $this->load->view('templates/header', $data);
        $this->load->view('apikeys/index', $data);
        $this->load->view('templates/footer');
    }
    
    public function add() {
        $data['title'] = 'Add API Key';
        
        // Form validation
        $this->form_validation->set_rules('api_key', 'API Key', 'required');
        $this->form_validation->set_rules('api_secret', 'API Secret', 'required');
        
        if ($this->form_validation->run() === FALSE) {
            $this->load->view('templates/header', $data);
            $this->load->view('apikeys/add', $data);
            $this->load->view('templates/footer');
        } else {
            $user_id = $this->session->userdata('user_id');
            
            // Add API key
            $api_data = array(
                'user_id' => $user_id,
                'api_key' => $this->input->post('api_key'),
                'api_secret' => $this->input->post('api_secret')
            );
            
            $this->Api_key_model->add_api_key($api_data);
            
            // Log action
            $log_data = array(
                'user_id' => $user_id,
                'action' => 'add_api_key',
                'description' => 'Added API key'
            );
            $this->Log_model->add_log($log_data);
            
            $this->session->set_flashdata('success', 'API key added successfully');
            redirect('apikeys');
        }
    }
    
    public function edit($id) {
        $data['title'] = 'Edit API Key';
        $data['api_key'] = $this->Api_key_model->get_api_key_by_id($id);
        
        // Check if API key exists and belongs to user
        if (empty($data['api_key']) || $data['api_key']->user_id != $this->session->userdata('user_id')) {
            $this->session->set_flashdata('error', 'API key not found or access denied');
            redirect('apikeys');
        }
        
        // Form validation
        $this->form_validation->set_rules('api_key', 'API Key', 'required');
        $this->form_validation->set_rules('api_secret', 'API Secret', 'required');
        
        if ($this->form_validation->run() === FALSE) {
            $this->load->view('templates/header', $data);
            $this->load->view('apikeys/edit', $data);
            $this->load->view('templates/footer');
        } else {
            // Update API key
            $api_data = array(
                'api_key' => $this->input->post('api_key'),
                'api_secret' => $this->input->post('api_secret')
            );
            
            $this->Api_key_model->update_api_key($id, $api_data);
            
            // Log action
            $log_data = array(
                'user_id' => $this->session->userdata('user_id'),
                'action' => 'edit_api_key',
                'description' => 'Updated API key'
            );
            $this->Log_model->add_log($log_data);
            
            $this->session->set_flashdata('success', 'API key updated successfully');
            redirect('apikeys');
        }
    }
    
    public function delete($id) {
        $api_key = $this->Api_key_model->get_api_key_by_id($id);
        
        // Check if API key exists and belongs to user
        if (empty($api_key) || $api_key->user_id != $this->session->userdata('user_id')) {
            $this->session->set_flashdata('error', 'API key not found or access denied');
            redirect('apikeys');
        }
        
        // Delete API key
        $this->Api_key_model->delete_api_key($id);
        
        // Log action
        $log_data = array(
            'user_id' => $this->session->userdata('user_id'),
            'action' => 'delete_api_key',
            'description' => 'Deleted API key'
        );
        $this->Log_model->add_log($log_data);
        
        $this->session->set_flashdata('success', 'API key deleted successfully');
        redirect('apikeys');
    }
}