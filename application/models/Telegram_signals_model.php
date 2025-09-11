<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Telegram_signals_model extends CI_Model
{

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Crear nueva señal de Telegram
     */
    public function create_signal($ticker_symbol, $image_path, $tradingview_url, $message_text, $webhook_raw_data = null)
    {
        $signal_data = [
            'ticker_symbol' => $ticker_symbol,
            'image_path' => $image_path,
            'tradingview_url' => $tradingview_url,
            'message_text' => $message_text,
            'webhook_raw_data' => $webhook_raw_data,
            'status' => 'pending'
        ];

        $this->db->insert('telegram_signals', $signal_data);
        return $this->db->insert_id();
    }

    /**
     * ✨ NUEVO: Crear user_telegram_signals automáticamente para todos los usuarios con un ticker
     */
    public function create_user_signals_for_ticker($telegram_signal_id, $ticker_symbol)
    {
        // Obtener todos los usuarios que tienen este ticker activo
        $this->db->select('ust.user_id, ust.ticker_symbol, ust.mt_ticker');
        $this->db->from('user_selected_tickers ust');
        $this->db->join('available_tickers at', 'ust.ticker_symbol = at.symbol');
        $this->db->where('ust.ticker_symbol', $ticker_symbol);
        $this->db->where('ust.active', 1);
        $this->db->where('at.active', 1);
        
        $users_with_ticker = $this->db->get()->result();
        
        if (empty($users_with_ticker)) {
            return 0; // No users found
        }
        
        // Crear registros en batch usando INSERT IGNORE
        $values = array();
        foreach ($users_with_ticker as $user) {
            $values[] = sprintf(
                "(%d, %d, '%s', '%s', 'available', NOW())",
                $telegram_signal_id,
                $user->user_id,
                $this->db->escape_str($user->ticker_symbol),
                $this->db->escape_str($user->mt_ticker ?: '')
            );
        }
        
        if (!empty($values)) {
            $sql = "INSERT IGNORE INTO user_telegram_signals 
                    (telegram_signal_id, user_id, ticker_symbol, mt_ticker, status, created_at) 
                    VALUES " . implode(', ', $values);
            
            $this->db->query($sql);
            return $this->db->affected_rows();
        }
        
        return 0;
    }

    /**
     * ✨ NUEVO: Obtener señales disponibles (no claimed) para un usuario
     */
    public function get_available_signals_for_user($user_id, $ticker_symbol, $hours_limit = 24)
    {
        $this->db->select('uts.*, ts.analysis_data, ts.tradingview_url, ts.created_at as telegram_created_at');
        $this->db->from('user_telegram_signals uts');
        $this->db->join('telegram_signals ts', 'uts.telegram_signal_id = ts.id');
        
        $this->db->where('uts.user_id', $user_id);
        $this->db->where('uts.ticker_symbol', $ticker_symbol);
        $this->db->where('uts.status', 'available');
        
        // Solo señales recientes
        $this->db->where('uts.created_at >=', date('Y-m-d H:i:s', strtotime("-{$hours_limit} hours")));
        
        $this->db->order_by('uts.created_at', 'DESC');
        $this->db->limit(1);
        
        return $this->db->get()->row();
    }

    /**
     * ✨ NUEVO: Marcar señal como claimed por el EA
     */
    public function claim_user_signal($user_signal_id, $user_id)
    {
        $this->db->where('id', $user_signal_id);
        $this->db->where('user_id', $user_id);
        $this->db->where('status', 'available');
        
        return $this->db->update('user_telegram_signals', [
            'status' => 'claimed',
            'updated_at' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Obtener señales con filtros (para admin)
     */
    public function get_signals_with_filters($filters = array())
    {
        $this->db->select('ts.*, at.name as ticker_name');
        $this->db->from('telegram_signals ts');
        $this->db->join('available_tickers at', 'ts.ticker_symbol = at.symbol');

        // Aplicar filtros
        if (isset($filters['ticker_symbol']) && $filters['ticker_symbol']) {
            $this->db->where('ts.ticker_symbol', $filters['ticker_symbol']);
        }

        if (isset($filters['status']) && $filters['status'] !== '') {
            $this->db->where('ts.status', $filters['status']);
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

    // ==========================================
    // MÉTODOS PARA USUARIOS ESPECÍFICOS
    // ==========================================

    /**
     * Obtener señales de un usuario específico con filtros
     */
    public function get_user_signals_with_filters($user_id, $filters = array())
    {
        $this->db->select('uts.*, ts.ticker_symbol, ts.image_path, ts.tradingview_url, ts.message_text, 
                          ts.analysis_data, ts.op_type, ts.created_at as telegram_created_at');
        $this->db->from('user_telegram_signals uts');
        $this->db->join('telegram_signals ts', 'uts.telegram_signal_id = ts.id');
        $this->db->where('uts.user_id', $user_id);

        // Aplicar filtros
        if (isset($filters['ticker_symbol']) && $filters['ticker_symbol']) {
            $this->db->where('uts.ticker_symbol', $filters['ticker_symbol']);
        }

        if (isset($filters['status']) && $filters['status'] !== '') {
            $this->db->where('uts.status', $filters['status']);
        }

        if (isset($filters['date_from']) && $filters['date_from']) {
            $this->db->where('uts.created_at >=', $filters['date_from'] . ' 00:00:00');
        }

        if (isset($filters['date_to']) && $filters['date_to']) {
            $this->db->where('uts.created_at <=', $filters['date_to'] . ' 23:59:59');
        }

        $this->db->order_by('uts.created_at', 'DESC');
        $this->db->limit(100); // Limitar para performance

        return $this->db->get()->result();
    }

    /**
     * Obtener señales recientes de un usuario
     */
    public function get_recent_user_signals($user_id, $limit = 10)
    {
        $this->db->select('uts.*, ts.ticker_symbol, ts.op_type, ts.analysis_data');
        $this->db->from('user_telegram_signals uts');
        $this->db->join('telegram_signals ts', 'uts.telegram_signal_id = ts.id');
        $this->db->where('uts.user_id', $user_id);
        $this->db->order_by('uts.created_at', 'DESC');
        $this->db->limit($limit);

        return $this->db->get()->result();
    }

    /**
     * Obtener detalle completo de una señal de usuario
     */
    public function get_user_signal_detail($user_id, $user_signal_id)
    {
        $this->db->select('uts.*, ts.ticker_symbol, ts.image_path, ts.tradingview_url, 
                          ts.message_text, ts.analysis_data, ts.op_type');
        $this->db->from('user_telegram_signals uts');
        $this->db->join('telegram_signals ts', 'uts.telegram_signal_id = ts.id');
        $this->db->where('uts.user_id', $user_id);
        $this->db->where('uts.id', $user_signal_id);

        return $this->db->get()->row();
    }

    /**
     * Contar señales de usuario hoy
     */
    public function count_user_signals_today($user_id)
    {
        $this->db->where('user_id', $user_id);
        $this->db->where('created_at >=', date('Y-m-d 00:00:00'));
        return $this->db->count_all_results('user_telegram_signals');
    }

    /**
     * Contar señales de usuario por status
     */
    public function count_user_signals_by_status($user_id, $status)
    {
        $this->db->where('user_id', $user_id);
        $this->db->where('status', $status);
        return $this->db->count_all_results('user_telegram_signals');
    }

    /**
     * Contar señales procesadas (no available) de usuario - CORREGIDO
     */
    public function count_user_signals_processed($user_id)
    {
        $this->db->where('user_id', $user_id);
        $this->db->where('status !=', 'available');
        return $this->db->count_all_results('user_telegram_signals');
    }

    /**
     * Actualizar status de user_signal
     */
    public function update_user_signal($user_signal_id, $status, $execution_data = null)
    {
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

    // ==========================================
    // MÉTODOS PARA TELEGRAM_SIGNALS (ADMIN)
    // ==========================================

    public function update_signal_status($signal_id, $status)
    {
        $this->db->where('id', $signal_id);
        return $this->db->update('telegram_signals', [
            'status' => $status,
            'updated_at' => date('Y-m-d H:i:s')
        ]);
    }

    public function complete_signal($signal_id, $analysis_data)
    {
        $analysis_json = json_decode($analysis_data, true);
        $op_type = isset($analysis_json['op_type']) ? $analysis_json['op_type'] : null;

        $this->db->where('id', $signal_id);
        return $this->db->update('telegram_signals', [
            'status' => 'completed',
            'analysis_data' => $analysis_data,
            'op_type' => $op_type,
            'updated_at' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Obtener señal por ID
     */
    public function get_signal_by_id($id)
    {
        $this->db->select('ts.*, at.name as ticker_name');
        $this->db->from('telegram_signals ts');
        $this->db->join('available_tickers at', 'ts.ticker_symbol = at.symbol');
        $this->db->where('ts.id', $id);
        return $this->db->get()->row();
    }

    /**
     * Contar todas las señales
     */
    public function count_signals_total()
    {
        return $this->db->count_all_results('telegram_signals');
    }

    /**
     * Contar señales completadas
     */
    public function count_signals_completed()
    {
        $this->db->where('status', 'completed');
        return $this->db->count_all_results('telegram_signals');
    }

    /**
     * Contar señales fallidas
     */
    public function count_signals_failed()
    {
        $this->db->where_in('status', ['failed_crop', 'failed_analysis', 'failed_download']);
        return $this->db->count_all_results('telegram_signals');
    }

    /**
     * Contar señales de las últimas 24 horas
     */
    public function count_signals_last_24h()
    {
        $this->db->where('created_at >=', date('Y-m-d H:i:s', strtotime('-24 hours')));
        return $this->db->count_all_results('telegram_signals');
    }

    /**
     * Obtener estadísticas de señales por ticker
     */
    public function get_ticker_stats($days = 7)
    {
        $this->db->select('ts.ticker_symbol, at.name as ticker_name, COUNT(*) as total_signals, 
                          SUM(CASE WHEN ts.status = "completed" THEN 1 ELSE 0 END) as completed_signals');
        $this->db->from('telegram_signals ts');
        $this->db->join('available_tickers at', 'ts.ticker_symbol = at.symbol');
        $this->db->where('ts.created_at >=', date('Y-m-d H:i:s', strtotime("-{$days} days")));
        $this->db->group_by('ts.ticker_symbol');
        $this->db->order_by('total_signals', 'DESC');

        return $this->db->get()->result();
    }

    /**
     * Obtener usuarios que están tradeando un ticker específico
     */
    public function get_users_trading_ticker($ticker_symbol)
    {
        $this->db->select('u.username, ust.active');
        $this->db->from('user_selected_tickers ust');
        $this->db->join('users u', 'ust.user_id = u.id');
        $this->db->where('ust.ticker_symbol', $ticker_symbol);
        $this->db->where('ust.active', 1);
        $this->db->order_by('u.username', 'ASC');

        return $this->db->get()->result();
    }

    /**
     * Obtener señales recientes del mismo ticker (excluyendo una específica)
     */
    public function get_recent_signals_by_ticker($ticker_symbol, $exclude_id = null, $limit = 5)
    {
        $this->db->select('id, created_at, status');
        $this->db->where('ticker_symbol', $ticker_symbol);

        if ($exclude_id) {
            $this->db->where('id !=', $exclude_id);
        }

        $this->db->order_by('created_at', 'DESC');
        $this->db->limit($limit);

        return $this->db->get('telegram_signals')->result();
    }

    /**
     * Limpiar señales antiguas
     */
    public function cleanup_old_signals($days = 30)
    {
        $this->db->where('created_at <', date('Y-m-d H:i:s', strtotime("-{$days} days")));
        return $this->db->delete('telegram_signals');
    }

    /**
     * Eliminar señal
     */
    public function delete_signal($id)
    {
        $this->db->where('id', $id);
        return $this->db->delete('telegram_signals');
    }
}