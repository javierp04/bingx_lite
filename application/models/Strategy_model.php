<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Strategy_model extends CI_Model {
    
    public function __construct() {
        parent::__construct();
    }
    
    public function get_all_strategies($user_id = null) {
        if ($user_id) {
            $this->db->where('user_id', $user_id);
        }
        $this->db->order_by('created_at', 'DESC');
        return $this->db->get('strategies')->result();
    }
    
    public function get_strategy_by_id($id) {
        return $this->db->get_where('strategies', array('id' => $id))->row();
    }
    
    public function get_strategy_by_strategy_id($user_id, $strategy_id) {
        return $this->db->get_where('strategies', array(
            'user_id' => $user_id,
            'strategy_id' => $strategy_id
        ))->row();
    }
    
    public function add_strategy($data) {
        $this->db->insert('strategies', $data);
        return $this->db->insert_id();
    }
    
    public function update_strategy($id, $data) {
        $this->db->where('id', $id);
        return $this->db->update('strategies', $data);
    }
    
    public function delete_strategy($id) {
        $this->db->where('id', $id);
        return $this->db->delete('strategies');
    }
}