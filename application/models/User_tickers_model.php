<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class User_tickers_model extends CI_Model {
    
    public function __construct() {
        parent::__construct();
    }
    
    // ================================
    // AVAILABLE TICKERS FUNCTIONS
    // ================================
    
    /**
     * Obtener ticker por symbol
     */
    public function get_available_ticker($symbol) {
        $this->db->where('symbol', $symbol);
        $this->db->where('active', 1);
        return $this->db->get('available_tickers')->row();
    }
    
    /**
     * Obtener todos los tickers disponibles
     */
    public function get_all_available_tickers($active_only = true) {
        if ($active_only) {
            $this->db->where('active', 1);
        }
        $this->db->order_by('symbol', 'ASC');
        return $this->db->get('available_tickers')->result();
    }
    
    /**
     * Verificar si un ticker existe y está activo
     */
    public function ticker_exists($symbol) {
        $this->db->where('symbol', $symbol);
        $this->db->where('active', 1);
        return $this->db->get('available_tickers')->num_rows() > 0;
    }
    
    /**
     * Agregar ticker disponible
     */
    public function add_available_ticker($data) {
        return $this->db->insert('available_tickers', $data);
    }
    
    /**
     * Actualizar ticker disponible
     */
    public function update_available_ticker($symbol, $data) {
        $this->db->where('symbol', $symbol);
        return $this->db->update('available_tickers', $data);
    }
    
    /**
     * Activar/Desactivar ticker
     */
    public function toggle_ticker_status($symbol, $active = true) {
        $this->db->where('symbol', $symbol);
        return $this->db->update('available_tickers', ['active' => $active ? 1 : 0]);
    }
    
    // ================================
    // USER SELECTED TICKERS FUNCTIONS
    // ================================
    
    /**
     * Obtener tickers seleccionados por usuario
     */
    public function get_user_selected_tickers($user_id, $active_only = true) {
        $this->db->select('ust.*, at.name as ticker_name');
        $this->db->from('user_selected_tickers ust');
        $this->db->join('available_tickers at', 'ust.ticker_symbol = at.symbol');
        $this->db->where('ust.user_id', $user_id);
        
        if ($active_only) {
            $this->db->where('ust.active', 1);
            $this->db->where('at.active', 1);
        }
        
        $this->db->order_by('ust.ticker_symbol', 'ASC');
        return $this->db->get()->result();
    }
    
    /**
     * Obtener tickers disponibles para un usuario (no seleccionados aún)
     */
    public function get_available_tickers_for_user($user_id) {
        $this->db->select('at.*');
        $this->db->from('available_tickers at');
        $this->db->where('at.active', 1);
        $this->db->where('at.symbol NOT IN (
            SELECT ticker_symbol 
            FROM user_selected_tickers 
            WHERE user_id = ' . (int)$user_id . '
        )');
        $this->db->order_by('at.symbol', 'ASC');
        return $this->db->get()->result();
    }
    
    /**
     * Verificar si usuario tiene un ticker seleccionado
     */
    public function user_has_ticker($user_id, $ticker_symbol) {
        $this->db->where('user_id', $user_id);
        $this->db->where('ticker_symbol', $ticker_symbol);
        $this->db->where('active', 1);
        return $this->db->get('user_selected_tickers')->num_rows() > 0;
    }
    
    /**
     * Agregar ticker a usuario
     */
    public function add_user_ticker($user_id, $ticker_symbol) {
        // Verificar que el ticker existe
        if (!$this->ticker_exists($ticker_symbol)) {
            return false;
        }
        
        $data = [
            'user_id' => $user_id,
            'ticker_symbol' => $ticker_symbol,
            'active' => 1
        ];
        
        // Usar INSERT IGNORE para evitar duplicados
        $this->db->query('INSERT IGNORE INTO user_selected_tickers (user_id, ticker_symbol, active, created_at) 
                         VALUES (?, ?, ?, NOW())', 
                         [$user_id, $ticker_symbol, 1]);
        
        return $this->db->affected_rows() > 0;
    }
    
    /**
     * Remover ticker de usuario
     */
    public function remove_user_ticker($user_id, $ticker_symbol) {
        $this->db->where('user_id', $user_id);
        $this->db->where('ticker_symbol', $ticker_symbol);
        return $this->db->delete('user_selected_tickers');
    }
    
    /**
     * Activar/Desactivar ticker de usuario
     */
    public function toggle_user_ticker_status($user_id, $ticker_symbol, $active = true) {
        $this->db->where('user_id', $user_id);
        $this->db->where('ticker_symbol', $ticker_symbol);
        return $this->db->update('user_selected_tickers', ['active' => $active ? 1 : 0]);
    }
    
    /**
     * Obtener lista de symbols activos de un usuario (para queries rápidas)
     */
    public function get_user_active_ticker_symbols($user_id) {
        $this->db->select('ust.ticker_symbol');
        $this->db->from('user_selected_tickers ust');
        $this->db->join('available_tickers at', 'ust.ticker_symbol = at.symbol');
        $this->db->where('ust.user_id', $user_id);
        $this->db->where('ust.active', 1);
        $this->db->where('at.active', 1);
        
        $result = $this->db->get()->result();
        return array_column($result, 'ticker_symbol');
    }
    
    /**
     * Actualizar múltiples tickers de usuario de una vez
     */
    public function update_user_tickers($user_id, $selected_tickers) {
        // Primero desactivar todos los tickers del usuario
        $this->db->where('user_id', $user_id);
        $this->db->update('user_selected_tickers', ['active' => 0]);
        
        // Luego activar/insertar los seleccionados
        foreach ($selected_tickers as $ticker_symbol) {
            if ($this->ticker_exists($ticker_symbol)) {
                // Intentar actualizar primero
                $this->db->where('user_id', $user_id);
                $this->db->where('ticker_symbol', $ticker_symbol);
                $updated = $this->db->update('user_selected_tickers', ['active' => 1]);
                
                // Si no existía, insertarlo
                if ($this->db->affected_rows() == 0) {
                    $this->add_user_ticker($user_id, $ticker_symbol);
                }
            }
        }
        
        return true;
    }
}