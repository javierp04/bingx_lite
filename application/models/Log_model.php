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
    
    public function get_logs($limit = 100, $user_id = null) {
        if ($user_id) {
            $this->db->where('user_id', $user_id);
        }
        
        $this->db->order_by('created_at', 'DESC');
        $this->db->limit($limit);
        
        return $this->db->get('system_logs')->result();
    }
}