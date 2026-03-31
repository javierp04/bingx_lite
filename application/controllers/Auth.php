<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Auth extends CI_Controller
{

    public function __construct()
    {
        parent::__construct();
    }

    public function index()
    {
        // Check if user is already logged in
        if ($this->session->userdata('logged_in')) {
            redirect('dashboard');
        }

        // Load login view
        $this->load->view('auth/login');
    }

    public function login()
    {
        // Check if user is already logged in
        if ($this->session->userdata('logged_in')) {
            redirect('dashboard');
        }

        // Form validation
        $this->form_validation->set_rules('username', 'Username', 'required');
        $this->form_validation->set_rules('password', 'Password', 'required');

        if ($this->form_validation->run() === FALSE) {
            $this->load->view('auth/login');
        } else {
            // Get form data
            $username = $this->input->post('username');
            $password = $this->input->post('password');

            // Check login
            $user = $this->User_model->check_login($username, $password);

            if ($user) {
                // Set user session — admins auto-grant all modules
                $is_admin = ($user->role === 'admin');
                $user_data = array(
                    'user_id' => $user->id,
                    'username' => $user->username,
                    'email' => $user->email,
                    'role' => $user->role,
                    'logged_in' => TRUE,
                    'module_bingx' => $is_admin || (bool) $user->module_bingx,
                    'module_metatrader' => $is_admin || (bool) $user->module_metatrader,
                    'module_atvip' => $is_admin || (bool) $user->module_atvip,
                );

                $this->session->set_userdata($user_data);

                // Log login
                $log_data = array(
                    'user_id' => $user->id,
                    'action' => 'login',
                    'description' => 'User logged in'
                );
                $this->Log_model->add_log($log_data);

                redirect('dashboard');
            } else {
                $this->session->set_flashdata('error', 'Invalid username or password');
                redirect('auth');
            }
        }
    }

    public function logout()
    {
        // Log logout
        if ($this->session->userdata('user_id')) {
            $log_data = array(
                'user_id' => $this->session->userdata('user_id'),
                'action' => 'logout',
                'description' => 'User logged out'
            );
            $this->Log_model->add_log($log_data);
        }

        // Unset user session
        $this->session->unset_userdata([
            'user_id',
            'username',
            'email',
            'role',
            'logged_in',
            'module_bingx',
            'module_metatrader',
            'module_atvip'
        ]);

        $this->session->set_flashdata('success', 'Successfully logged out');
        redirect('auth');
    }
}
