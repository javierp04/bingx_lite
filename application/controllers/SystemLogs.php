<?php
defined('BASEPATH') or exit('No direct script access allowed');

class SystemLogs extends CI_Controller
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

        // Load logs helper
        $this->load->helper('logs');
    }
    public function index()
    {
        $data['title'] = 'System Logs';

        // Get filter params from GET or session
        $filters = array();
        $filters['action'] = $this->input->get('action') ?: '';
        $filters['description'] = $this->input->get('description') ?: '';
        $filters['date_from'] = $this->input->get('date_from') ?: '';
        $filters['date_to'] = $this->input->get('date_to') ?: '';
        $filters['user_id'] = $this->input->get('user_id') ?: '';

        // Get page from GET
        $page = $this->input->get('page') ?: 1;
        $limit = 50; // Logs per page
        $offset = ($page - 1) * $limit;

        // Get logs with filters
        $data['logs'] = $this->Log_model->get_logs($limit, null, $filters);

        // Get total logs count for pagination
        $total_logs = $this->Log_model->count_logs($filters);
        $data['total_pages'] = ceil($total_logs / $limit);
        $data['current_page'] = $page;

        // Get available log actions for filter dropdown
        $data['log_actions'] = $this->Log_model->get_log_actions();

        // Get all users for filter dropdown
        $data['users'] = $this->User_model->get_all_users();

        // Pass filters to view
        $data['filters'] = $filters;

        $this->load->view('templates/header', $data);
        $this->load->view('systemlogs/index', $data);
        $this->load->view('templates/footer');
    }

    // Helper function to get previous and next log IDs
    public function get_adjacent_logs($id)
    {
        // Get previous log ID
        $this->db->select('id');
        $this->db->from('system_logs');
        $this->db->where('id <', $id);
        $this->db->order_by('id', 'DESC');
        $this->db->limit(1);
        $prev_query = $this->db->get();
        $prev_id = ($prev_query->num_rows() > 0) ? $prev_query->row()->id : null;

        // Get next log ID
        $this->db->select('id');
        $this->db->from('system_logs');
        $this->db->where('id >', $id);
        $this->db->order_by('id', 'ASC');
        $this->db->limit(1);
        $next_query = $this->db->get();
        $next_id = ($next_query->num_rows() > 0) ? $next_query->row()->id : null;

        return [
            'prev_id' => $prev_id,
            'next_id' => $next_id
        ];
    }

    public function view($id)
    {
        $data['title'] = 'View Log Details';
        $data['log'] = $this->Log_model->get_log_by_id($id);

        if (!$data['log']) {
            $this->session->set_flashdata('error', 'Log not found');
            redirect('systemlogs');
        }

        // Get adjacent log IDs
        $data['adjacent_logs'] = $this->get_adjacent_logs($id);

        // Get user if exists
        if ($data['log']->user_id) {
            $data['user'] = $this->User_model->get_user_by_id($data['log']->user_id);
        } else {
            $data['user'] = null;
        }

        // Format JSON description if it's JSON
        $description = $data['log']->description;
        if ($this->_is_json($description)) {
            $data['formatted_description'] = $this->_format_json($description);
            $data['is_json'] = true;
        } else {
            $data['formatted_description'] = $description;
            $data['is_json'] = false;
        }

        $this->load->view('templates/header', $data);
        $this->load->view('systemlogs/view', $data);
        $this->load->view('templates/footer');
    }

    // Helper to check if a string is valid JSON
    private function _is_json($string)
    {
        json_decode($string);
        return (json_last_error() == JSON_ERROR_NONE);
    }

    // Helper to format JSON for display
    private function _format_json($json_string)
    {
        $json_obj = json_decode($json_string);
        return json_encode($json_obj, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    // AJAX endpoint to search logs
    public function search()
    {
        // Get filter params
        $filters = array();
        $filters['action'] = $this->input->post('action') ?: '';
        $filters['description'] = $this->input->post('description') ?: '';
        $filters['date_from'] = $this->input->post('date_from') ?: '';
        $filters['date_to'] = $this->input->post('date_to') ?: '';
        $filters['user_id'] = $this->input->post('user_id') ?: '';

        // Get logs with filters
        $logs = $this->Log_model->get_logs(100, null, $filters);

        // Format the logs for JSON response
        $formatted_logs = array();
        foreach ($logs as $log) {
            $log_item = array(
                'id' => $log->id,
                'user_id' => $log->user_id,
                'action' => $log->action,
                'description' => (strlen($log->description) > 100) ?
                    substr($log->description, 0, 100) . '...' : $log->description,
                'ip_address' => $log->ip_address,
                'created_at' => $log->created_at
            );
            $formatted_logs[] = $log_item;
        }

        echo json_encode($formatted_logs);
    }

    // Endpoint to delete old logs
    public function cleanup()
    {
        // Check if confirmed
        if ($this->input->post('confirm') == 'yes') {
            $days = (int)$this->input->post('days');
            if ($days > 0) {
                $date = date('Y-m-d', strtotime("-$days days"));
                $deleted = $this->Log_model->delete_logs_before($date);
                $this->session->set_flashdata('success', "Deleted $deleted logs older than $days days");
            } else {
                $this->session->set_flashdata('error', 'Invalid number of days');
            }
        }

        redirect('systemlogs');
    }
}