<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Mt_signal_model extends CI_Model {
    
    public function add_signal($data) {
        $this->db->insert('mt_signals', $data);
        return $this->db->insert_id();
    }
    
    public function get_pending_signals($user_id, $strategy_id = null) {
        $this->db->select('mt_signals.*, strategies.strategy_id as strategy_external_id, strategies.name as strategy_name');
        $this->db->from('mt_signals');
        $this->db->join('strategies', 'strategies.id = mt_signals.strategy_id');
        $this->db->where('mt_signals.user_id', $user_id);
        $this->db->where('mt_signals.status', 'pending');
        
        if ($strategy_id) {
            $this->db->where('strategies.strategy_id', $strategy_id);
        }
        
        $this->db->order_by('mt_signals.created_at', 'ASC');
        
        return $this->db->get()->result();
    }
    
    public function update_signal_status($signal_id, $status, $ea_response = null) {
        $data = array(
            'status' => $status,
            'processed_at' => date('Y-m-d H:i:s')
        );
        
        if ($ea_response) {
            $data['ea_response'] = $ea_response;
        }
        
        $this->db->where('id', $signal_id);
        return $this->db->update('mt_signals', $data);
    }
    
    public function get_signal_by_id($id) {
        return $this->db->get_where('mt_signals', array('id' => $id))->row();
    }
    
    public function get_signal_by_position_id($position_id) {
        $this->db->where('status', 'pending');
        $this->db->like('signal_data', '"position_id":"' . $position_id . '"');
        $this->db->order_by('created_at', 'DESC');
        $this->db->limit(1);
        
        return $this->db->get('mt_signals')->row();
    }
    
    public function get_signals_by_user($user_id, $limit = 100) {
        $this->db->select('mt_signals.*, strategies.strategy_id as strategy_external_id, strategies.name as strategy_name');
        $this->db->from('mt_signals');
        $this->db->join('strategies', 'strategies.id = mt_signals.strategy_id');
        $this->db->where('mt_signals.user_id', $user_id);
        $this->db->order_by('mt_signals.created_at', 'DESC');
        $this->db->limit($limit);
        
        return $this->db->get()->result();
    }

    public function get_recent_signals($limit = 10) {
        $this->db->select('mt_signals.*, strategies.strategy_id as strategy_external_id, strategies.name as strategy_name, users.username');
        $this->db->from('mt_signals');
        $this->db->join('strategies', 'strategies.id = mt_signals.strategy_id');
        $this->db->join('users', 'users.id = mt_signals.user_id');
        $this->db->order_by('mt_signals.created_at', 'DESC');
        $this->db->limit($limit);
        
        return $this->db->get()->result();
    }
    
    public function get_ea_activity() {
        // This would track when EAs last polled for signals
        // For now, we'll simulate based on recent processed signals
        $this->db->select('users.username, strategies.name as strategy_name, MAX(mt_signals.processed_at) as last_poll');
        $this->db->from('mt_signals');
        $this->db->join('users', 'users.id = mt_signals.user_id');
        $this->db->join('strategies', 'strategies.id = mt_signals.strategy_id');
        $this->db->where('mt_signals.status', 'processed');
        $this->db->where('mt_signals.processed_at >', date('Y-m-d H:i:s', strtotime('-24 hours')));
        $this->db->group_by(['users.id', 'strategies.id']);
        $this->db->order_by('last_poll', 'DESC');
        
        return $this->db->get()->result();
    }
    
    public function count_signals_by_status($status) {
        $this->db->where('status', $status);
        return $this->db->count_all_results('mt_signals');
    }
    
    public function count_signals_last_24h() {
        $this->db->where('created_at >', date('Y-m-d H:i:s', strtotime('-24 hours')));
        return $this->db->count_all_results('mt_signals');
    }
    
    public function count_signals_today($status = null) {
        $this->db->where('DATE(created_at)', date('Y-m-d'));
        if ($status) {
            $this->db->where('status', $status);
        }
        return $this->db->count_all_results('mt_signals');
    }
    
    public function get_signals_with_filters($filters = array()) {
        $this->db->select('mt_signals.*, strategies.strategy_id as strategy_external_id, strategies.name as strategy_name, users.username');
        $this->db->from('mt_signals');
        $this->db->join('strategies', 'strategies.id = mt_signals.strategy_id');
        $this->db->join('users', 'users.id = mt_signals.user_id');
        
        // Apply filters
        if (!empty($filters['status'])) {
            $this->db->where('mt_signals.status', $filters['status']);
        }
        
        if (!empty($filters['user_id'])) {
            $this->db->where('mt_signals.user_id', $filters['user_id']);
        }
        
        if (!empty($filters['strategy_id'])) {
            $this->db->where('mt_signals.strategy_id', $filters['strategy_id']);
        }
        
        if (!empty($filters['date_from'])) {
            $this->db->where('DATE(mt_signals.created_at) >=', $filters['date_from']);
        }
        
        if (!empty($filters['date_to'])) {
            $this->db->where('DATE(mt_signals.created_at) <=', $filters['date_to']);
        }
        
        $this->db->order_by('mt_signals.created_at', 'DESC');
        $this->db->limit(100); // Limit to prevent overwhelming
        
        return $this->db->get()->result();
    }
    
    public function delete_signal($signal_id) {
        $this->db->where('id', $signal_id);
        return $this->db->delete('mt_signals');
    }
}