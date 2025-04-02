<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Api_key_model extends CI_Model {
    
    public function __construct() {
        parent::__construct();
    }
    
    public function get_all_api_keys($user_id = null) {
        if ($user_id) {
            $this->db->where('user_id', $user_id);
        }
        return $this->db->get('api_keys')->result();
    }
    
    public function get_api_key_by_id($id) {
        return $this->db->get_where('api_keys', array('id' => $id))->row();
    }
    
    public function get_api_key($user_id) {
        return $this->db->get_where('api_keys', array(
            'user_id' => $user_id
        ))->row();
    }
    
    public function add_api_key($data) {
        // Check if user already has keys
        $existing = $this->db->get_where('api_keys', array(
            'user_id' => $data['user_id']
        ))->row();
        
        if ($existing) {
            // Update instead of insert
            $this->db->where('id', $existing->id);
            return $this->db->update('api_keys', $data);
        } else {
            $this->db->insert('api_keys', $data);
            return $this->db->insert_id();
        }
    }
    
    public function update_api_key($id, $data) {
        $this->db->where('id', $id);
        return $this->db->update('api_keys', $data);
    }
    
    public function delete_api_key($id) {
        $this->db->where('id', $id);
        return $this->db->delete('api_keys');
    }
}