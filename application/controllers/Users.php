<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Users extends CI_Controller {
    
    public function __construct() {
        parent::__construct();
        
        // Check if user is logged in
        if (!$this->session->userdata('logged_in')) {
            redirect('auth');
        }
        
        // Check if user is admin
        if ($this->session->userdata('role') != 'admin') {
            $this->session->set_flashdata('error', 'Access denied. Admin privileges required.');
            redirect('dashboard');
        }
    }
    
    public function index() {
        $data['title'] = 'User Management';
        $data['users'] = $this->User_model->get_all_users();
        
        $this->load->view('templates/header', $data);
        $this->load->view('users/index', $data);
        $this->load->view('templates/footer');
    }
    
    public function add() {
        $data['title'] = 'Add User';
        
        // Form validation
        $this->form_validation->set_rules('username', 'Username', 'required|is_unique[users.username]');
        $this->form_validation->set_rules('email', 'Email', 'required|valid_email|is_unique[users.email]');
        $this->form_validation->set_rules('password', 'Password', 'required');
        $this->form_validation->set_rules('confirm_password', 'Confirm Password', 'required|matches[password]');
        
        if ($this->form_validation->run() === FALSE) {
            $this->load->view('templates/header', $data);
            $this->load->view('users/add', $data);
            $this->load->view('templates/footer');
        } else {
            // Hash password
            $password = password_hash($this->input->post('password'), PASSWORD_DEFAULT);
            
            // Add user
            $user_data = array(
                'username' => $this->input->post('username'),
                'email' => $this->input->post('email'),
                'password' => $password,
                'role' => $this->input->post('role')
            );
            
            $user_id = $this->User_model->add_user($user_data);
            
            // Log action
            $log_data = array(
                'user_id' => $this->session->userdata('user_id'),
                'action' => 'add_user',
                'description' => 'Added new user: ' . $this->input->post('username')
            );
            $this->Log_model->add_log($log_data);
            
            $this->session->set_flashdata('success', 'User added successfully');
            redirect('users');
        }
    }
    
    public function edit($id) {
        $data['title'] = 'Edit User';
        $data['user'] = $this->User_model->get_user_by_id($id);
        
        if (empty($data['user'])) {
            $this->session->set_flashdata('error', 'User not found');
            redirect('users');
        }
        
        // Form validation
        $this->form_validation->set_rules('username', 'Username', 'required');
        $this->form_validation->set_rules('email', 'Email', 'required|valid_email');
        
        if ($this->input->post('password')) {
            $this->form_validation->set_rules('password', 'Password', 'required');
            $this->form_validation->set_rules('confirm_password', 'Confirm Password', 'required|matches[password]');
        }
        
        if ($this->form_validation->run() === FALSE) {
            $this->load->view('templates/header', $data);
            $this->load->view('users/edit', $data);
            $this->load->view('templates/footer');
        } else {
            // Update user
            $user_data = array(
                'username' => $this->input->post('username'),
                'email' => $this->input->post('email'),
                'role' => $this->input->post('role')
            );
            
            // Update password if provided
            if ($this->input->post('password')) {
                $user_data['password'] = password_hash($this->input->post('password'), PASSWORD_DEFAULT);
            }
            
            $this->User_model->update_user($id, $user_data);
            
            // Log action
            $log_data = array(
                'user_id' => $this->session->userdata('user_id'),
                'action' => 'edit_user',
                'description' => 'Updated user: ' . $this->input->post('username')
            );
            $this->Log_model->add_log($log_data);
            
            $this->session->set_flashdata('success', 'User updated successfully');
            redirect('users');
        }
    }
    
    public function delete($id) {
        $user = $this->User_model->get_user_by_id($id);
        
        if (empty($user)) {
            $this->session->set_flashdata('error', 'User not found');
            redirect('users');
        }
        
        // Don't allow deleting yourself
        if ($id == $this->session->userdata('user_id')) {
            $this->session->set_flashdata('error', 'You cannot delete your own account');
            redirect('users');
        }
        
        // Delete user
        $this->User_model->delete_user($id);
        
        // Log action
        $log_data = array(
            'user_id' => $this->session->userdata('user_id'),
            'action' => 'delete_user',
            'description' => 'Deleted user: ' . $user->username
        );
        $this->Log_model->add_log($log_data);
        
        $this->session->set_flashdata('success', 'User deleted successfully');
        redirect('users');
    }
}