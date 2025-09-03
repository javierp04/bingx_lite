<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Telegram_signals_model extends CI_Model {
    
    public function __construct() {
        parent::__construct();
    }

    /**
     * Crear nueva señal de Telegram
     */
    public function create_signal($ticker_symbol, $image_path, $tradingview_url, $message_text) {
        $signal_data = [
            'ticker_symbol' => $ticker_symbol,
            'image_path' => $image_path,
            'tradingview_url' => $tradingview_url,
            'message_text' => $message_text,
            'processed' => 0
        ];
        
        $this->db->insert('telegram_signals', $signal_data);
        return $this->db->insert_id();
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

    public function update_signal_status($signal_id, $status) {
        $this->db->where('id', $signal_id);
        return $this->db->update('telegram_signals', [
            'status' => $status,
            'updated_at' => date('Y-m-d H:i:s')
        ]);
    }

    public function complete_signal($signal_id, $analysis_data) {
        $this->db->where('id', $signal_id);
        return $this->db->update('telegram_signals', [
            'status' => 'completed',
            'analysis_data' => $analysis_data,
            'updated_at' => date('Y-m-d H:i:s')
        ]);
    }

      /**
     * Obtener señales completadas para un usuario específico
     */
    public function get_completed_signals_for_user($user_id, $ticker_symbol, $hours_limit = 24) {
        $this->db->select('ts.*, ust.mt_ticker');
        $this->db->from('telegram_signals ts');
        $this->db->join('user_selected_tickers ust', 'ts.ticker_symbol = ust.ticker_symbol');
        
        $this->db->where('ust.user_id', $user_id);
        $this->db->where('ts.ticker_symbol', $ticker_symbol);
        $this->db->where('ts.status', 'completed');
        $this->db->where('ust.active', 1);
        
        // Solo señales recientes
        $this->db->where('ts.created_at >=', date('Y-m-d H:i:s', strtotime("-{$hours_limit} hours")));
        
        // Verificar que no exista ya un user_signal para este usuario y señal
        $this->db->where('ts.id NOT IN (
            SELECT telegram_signal_id 
            FROM user_telegram_signals 
            WHERE user_id = ' . (int)$user_id . '
        )');
        
        $this->db->order_by('ts.created_at', 'ASC');
        
        return $this->db->get()->result();
    }
    
    /**
     * Crear registro de user_signal cuando EA consulta
     */
    public function create_user_signal($telegram_signal_id, $user_id, $ticker_symbol, $mt_ticker) {
        $data = [
            'telegram_signal_id' => $telegram_signal_id,
            'user_id' => $user_id,
            'ticker_symbol' => $ticker_symbol,
            'mt_ticker' => $mt_ticker,
            'status' => 'pending'
        ];
        
        // Usar INSERT IGNORE para evitar duplicados
        $this->db->query('INSERT IGNORE INTO user_telegram_signals 
                         (telegram_signal_id, user_id, ticker_symbol, mt_ticker, status, created_at) 
                         VALUES (?, ?, ?, ?, ?, NOW())', 
                         [$telegram_signal_id, $user_id, $ticker_symbol, $mt_ticker, 'pending']);
        
        if ($this->db->affected_rows() > 0) {
            return $this->db->insert_id();
        }
        
        return false;
    }
    
    /**
     * Actualizar status de user_signal
     */
    public function update_user_signal($user_signal_id, $status, $execution_data = null) {
        $update_data = [
            'status' => $status,
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        if ($execution_data) {
            $update_data['execution_data'] = json_encode($execution_data);
            if (isset($execution_data['trade_id'])) {
                $update_data['trade_id'] = $execution_data['trade_id'];
            }
        }
        
        $this->db->where('id', $user_signal_id);
        return $this->db->update('user_telegram_signals', $update_data);
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