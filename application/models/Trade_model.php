<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Trade_model extends CI_Model {
    
    public function __construct() {
        parent::__construct();
    }
    
    public function get_all_trades($user_id = null, $status = null, $environment = null) {
        if ($user_id) {
            $this->db->where('trades.user_id', $user_id);
        }
        
        if ($status) {
            $this->db->where('trades.status', $status);
        }
        
        if ($environment) {
            $this->db->where('trades.environment', $environment);
        }
        
        $this->db->select('trades.*, strategies.name as strategy_name, strategies.strategy_id as strategy_external_id');
        $this->db->join('strategies', 'strategies.id = trades.strategy_id', 'left');
        $this->db->order_by('trades.created_at', 'DESC');
        
        return $this->db->get('trades')->result();
    }
    
    public function get_trade_by_id($id) {
        $this->db->select('trades.*, strategies.name as strategy_name, strategies.strategy_id as strategy_external_id');
        $this->db->join('strategies', 'strategies.id = trades.strategy_id', 'left');
        return $this->db->get_where('trades', array('trades.id' => $id))->row();
    }
    
    public function get_trade_by_order_id($order_id, $environment) {
        return $this->db->get_where('trades', array(
            'order_id' => $order_id,
            'environment' => $environment
        ))->row();
    }
    
    public function add_trade($data) {
        $this->db->insert('trades', $data);
        return $this->db->insert_id();
    }
    
    public function update_trade($id, $data) {
        $this->db->where('id', $id);
        return $this->db->update('trades', $data);
    }
    
    public function close_trade($id, $exit_price, $pnl) {
        $data = array(
            'exit_price' => $exit_price,
            'pnl' => $pnl,
            'status' => 'closed',
            'closed_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        );
        
        $this->db->where('id', $id);
        return $this->db->update('trades', $data);
    }
    
    public function delete_trade($id) {
        $this->db->where('id', $id);
        return $this->db->delete('trades');
    }
}