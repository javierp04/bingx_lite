<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Log_model extends CI_Model {
    
    public function __construct() {
        parent::__construct();
    }
    
    public function add_log($data) {
        // Add IP address if not provided
        if (!isset($data['ip_address'])) {
            $data['ip_address'] = $this->input->ip_address();
        }
        
        $this->db->insert('system_logs', $data);
        return $this->db->insert_id();
    }
    
    public function get_logs($limit = 100, $user_id = null, $filters = array()) {
        // Apply filters
        if (!empty($filters)) {
            // Filter by action type
            if (isset($filters['action']) && $filters['action']) {
                $this->db->like('action', $filters['action']);
            }
            
            // Filter by description
            if (isset($filters['description']) && $filters['description']) {
                $this->db->like('description', $filters['description']);
            }
            
            // Filter by date range
            if (isset($filters['date_from']) && $filters['date_from']) {
                $this->db->where('created_at >=', $filters['date_from'] . ' 00:00:00');
            }
            
            if (isset($filters['date_to']) && $filters['date_to']) {
                $this->db->where('created_at <=', $filters['date_to'] . ' 23:59:59');
            }
        }
        
        // Filter by user if specified
        if ($user_id) {
            $this->db->where('user_id', $user_id);
        }
        
        $this->db->order_by('created_at', 'DESC');
        
        // Apply limit if specified
        if ($limit > 0) {
            $this->db->limit($limit);
        }
        
        return $this->db->get('system_logs')->result();
    }
    
    public function get_log_by_id($id) {
        return $this->db->get_where('system_logs', array('id' => $id))->row();
    }
    
    public function get_log_actions() {
        $this->db->select('action');
        $this->db->distinct();
        $this->db->order_by('action', 'ASC');
        $query = $this->db->get('system_logs');
        
        $actions = array();
        foreach ($query->result() as $row) {
            $actions[] = $row->action;
        }
        
        return $actions;
    }
    
    public function delete_logs_before($date) {
        $this->db->where('created_at <', $date);
        return $this->db->delete('system_logs');
    }
    
    public function count_logs($filters = array()) {
        // Apply filters
        if (!empty($filters)) {
            // Filter by action type
            if (isset($filters['action']) && $filters['action']) {
                $this->db->like('action', $filters['action']);
            }
            
            // Filter by description
            if (isset($filters['description']) && $filters['description']) {
                $this->db->like('description', $filters['description']);
            }
            
            // Filter by date range
            if (isset($filters['date_from']) && $filters['date_from']) {
                $this->db->where('created_at >=', $filters['date_from'] . ' 00:00:00');
            }
            
            if (isset($filters['date_to']) && $filters['date_to']) {
                $this->db->where('created_at <=', $filters['date_to'] . ' 23:59:59');
            }
            
            // Filter by user id
            if (isset($filters['user_id']) && $filters['user_id']) {
                $this->db->where('user_id', $filters['user_id']);
            }
        }
        
        return $this->db->count_all_results('system_logs');
    }
}