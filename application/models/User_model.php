<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class User_model extends CI_Model {
    
    public function __construct() {
        parent::__construct();
    }
    
    public function get_all_users() {
        $this->db->order_by('created_at', 'DESC');
        return $this->db->get('users')->result();
    }
    
    public function get_user_by_id($id) {
        return $this->db->get_where('users', array('id' => $id))->row();
    }
    
    public function get_user_by_username($username) {
        return $this->db->get_where('users', array('username' => $username))->row();
    }
    
    public function add_user($data) {
        $this->db->insert('users', $data);
        return $this->db->insert_id();
    }
    
    public function update_user($id, $data) {
        $this->db->where('id', $id);
        return $this->db->update('users', $data);
    }
    
    public function delete_user($id) {
        $this->db->where('id', $id);
        return $this->db->delete('users');
    }
    
    public function check_login($username, $password) {
        $user = $this->get_user_by_username($username);
        
        if ($user && password_verify($password, $user->password)) {
            return $user;
        }
        
        return false;
    }
}