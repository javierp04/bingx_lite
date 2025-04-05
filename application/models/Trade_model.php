<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Trade_model extends CI_Model {
    
    public function __construct() {
        parent::__construct();
    }
    
    public function get_all_trades($user_id = null, $status = null) {
        if ($user_id) {
            $this->db->where('trades.user_id', $user_id);
        }
        
        if ($status) {
            $this->db->where('trades.status', $status);
        }
        
        $this->db->select('trades.*, strategies.name as strategy_name, strategies.strategy_id as strategy_external_id, users.username');
        $this->db->join('strategies', 'strategies.id = trades.strategy_id', 'left');
        $this->db->join('users', 'users.id = trades.user_id', 'left');
        $this->db->order_by('trades.created_at', 'DESC');
        
        return $this->db->get('trades')->result();
    }
    
    public function get_trade_by_id($id) {
        $this->db->select('trades.*, strategies.name as strategy_name, strategies.strategy_id as strategy_external_id, users.username');
        $this->db->join('strategies', 'strategies.id = trades.strategy_id', 'left');
        $this->db->join('users', 'users.id = trades.user_id', 'left');
        return $this->db->get_where('trades', array('trades.id' => $id))->row();
    }
    
    public function get_trade_by_order_id($order_id) {
        return $this->db->get_where('trades', array(
            'order_id' => $order_id
        ))->row();
    }
    
    public function get_trade_by_position_id($position_id, $user_id = null, $symbol = null, $timeframe = null, $side = null) {
        if ($user_id) {
            $this->db->where('user_id', $user_id);
        }
        
        $this->db->where('position_id', $position_id);
        $this->db->where('status', 'open');
        
        // Add additional filters for more specificity
        if ($symbol) {
            $this->db->where('symbol', $symbol);
        }
        
        if ($timeframe) {
            $this->db->where('timeframe', $timeframe);
        }
        
        if ($side) {
            $this->db->where('side', $side);
        }
        
        return $this->db->get('trades')->row();
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
    
    public function get_total_pnl($trades) {
        $total_pnl = 0;
        foreach ($trades as $trade) {
            $total_pnl += isset($trade->pnl) ? $trade->pnl : 0;
        }
        return $total_pnl;
    }
}