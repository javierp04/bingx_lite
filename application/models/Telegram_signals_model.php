<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Telegram_signals_model extends CI_Model {
    
    public function __construct() {
        parent::__construct();
    }
    
    /**
     * Obtener señales con filtros
     */
    public function get_signals_with_filters($filters = array()) {
        $this->db->select('ts.*, at.name as ticker_name');
        $this->db->from('telegram_signals ts');
        $this->db->join('available_tickers at', 'ts.ticker_symbol = at.symbol');
        
        // Aplicar filtros
        if (isset($filters['ticker_symbol']) && $filters['ticker_symbol']) {
            $this->db->where('ts.ticker_symbol', $filters['ticker_symbol']);
        }
        
        if (isset($filters['processed']) && $filters['processed'] !== '') {
            $this->db->where('ts.processed', (int)$filters['processed']);
        }
        
        if (isset($filters['date_from']) && $filters['date_from']) {
            $this->db->where('ts.created_at >=', $filters['date_from'] . ' 00:00:00');
        }
        
        if (isset($filters['date_to']) && $filters['date_to']) {
            $this->db->where('ts.created_at <=', $filters['date_to'] . ' 23:59:59');
        }
        
        $this->db->order_by('ts.created_at', 'DESC');
        $this->db->limit(200); // Limitar para performance
        
        return $this->db->get()->result();
    }
    
    /**
     * Obtener señal por ID
     */
    public function get_signal_by_id($id) {
        $this->db->select('ts.*, at.name as ticker_name');
        $this->db->from('telegram_signals ts');
        $this->db->join('available_tickers at', 'ts.ticker_symbol = at.symbol');
        $this->db->where('ts.id', $id);
        return $this->db->get()->row();
    }
    
    /**
     * Marcar señal como procesada
     */
    public function mark_as_processed($id) {
        $this->db->where('id', $id);
        return $this->db->update('telegram_signals', [
            'processed' => 1,
            'updated_at' => date('Y-m-d H:i:s')
        ]);
    }
    
    /**
     * Obtener señales no procesadas para un usuario específico
     */
    public function get_pending_signals_for_user($user_id, $hours_limit = 24) {
        $this->db->select('ts.*, at.name as ticker_name');
        $this->db->from('telegram_signals ts');
        $this->db->join('available_tickers at', 'ts.ticker_symbol = at.symbol');
        $this->db->join('user_selected_tickers ust', 'ts.ticker_symbol = ust.ticker_symbol');
        
        $this->db->where('ust.user_id', $user_id);
        $this->db->where('ust.active', 1);
        $this->db->where('at.active', 1);
        $this->db->where('ts.processed', 0);
        
        // Solo señales de las últimas X horas
        $this->db->where('ts.created_at >=', date('Y-m-d H:i:s', strtotime("-{$hours_limit} hours")));
        
        $this->db->order_by('ts.created_at', 'ASC');
        
        return $this->db->get()->result();
    }
    
    /**
     * Contar señales por estado
     */
    public function count_signals_by_status($processed = null) {
        if ($processed !== null) {
            $this->db->where('processed', (int)$processed);
        }
        return $this->db->count_all_results('telegram_signals');
    }
    
    /**
     * Contar señales de las últimas 24 horas
     */
    public function count_signals_last_24h() {
        $this->db->where('created_at >=', date('Y-m-d H:i:s', strtotime('-24 hours')));
        return $this->db->count_all_results('telegram_signals');
    }
    
    /**
     * Obtener estadísticas de señales por ticker
     */
    public function get_ticker_stats($days = 7) {
        $this->db->select('ts.ticker_symbol, at.name as ticker_name, COUNT(*) as total_signals, 
                          SUM(CASE WHEN ts.processed = 1 THEN 1 ELSE 0 END) as processed_signals');
        $this->db->from('telegram_signals ts');
        $this->db->join('available_tickers at', 'ts.ticker_symbol = at.symbol');
        $this->db->where('ts.created_at >=', date('Y-m-d H:i:s', strtotime("-{$days} days")));
        $this->db->group_by('ts.ticker_symbol');
        $this->db->order_by('total_signals', 'DESC');
        
        return $this->db->get()->result();
    }
    
    /**
     * Limpiar señales antiguas
     */
    public function cleanup_old_signals($days = 30) {
        $this->db->where('created_at <', date('Y-m-d H:i:s', strtotime("-{$days} days")));
        return $this->db->delete('telegram_signals');
    }
    
    /**
     * Eliminar señal
     */
    public function delete_signal($id) {
        $this->db->where('id', $id);
        return $this->db->delete('telegram_signals');
    }
}