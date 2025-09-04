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
    public function create_signal($ticker_symbol, $image_path, $tradingview_url, $message_text)
    {
        $signal_data = [
            'ticker_symbol' => $ticker_symbol,
            'image_path' => $image_path,
            'tradingview_url' => $tradingview_url,
            'message_text' => $message_text,
            'status' => 'pending'
        ];

        $this->db->insert('telegram_signals', $signal_data);
        return $this->db->insert_id();
    }

    /**
     * Obtener señales con filtros
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
        // Extraer op_type del JSON
        $analysis_json = json_decode($analysis_data, true);
        $op_type = isset($analysis_json['op_type']) ? $analysis_json['op_type'] : null;

        return $this->db->update('telegram_signals', [
            'status' => 'completed',
            'analysis_data' => $analysis_data,
            'op_type' => $op_type,  // ← NUEVO CAMPO
            'updated_at' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Obtener señales completadas para un usuario específico
     */
    public function get_completed_signals_for_user($user_id, $ticker_symbol, $hours_limit = 24)
    {
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
    public function create_user_signal($telegram_signal_id, $user_id, $ticker_symbol, $mt_ticker)
    {
        $data = [
            'telegram_signal_id' => $telegram_signal_id,
            'user_id' => $user_id,
            'ticker_symbol' => $ticker_symbol,
            'mt_ticker' => $mt_ticker,
            'status' => 'pending'
        ];

        // Usar INSERT IGNORE para evitar duplicados
        $this->db->query(
            'INSERT IGNORE INTO user_telegram_signals 
                         (telegram_signal_id, user_id, ticker_symbol, mt_ticker, status, created_at) 
                         VALUES (?, ?, ?, ?, ?, NOW())',
            [$telegram_signal_id, $user_id, $ticker_symbol, $mt_ticker, 'pending']
        );

        if ($this->db->affected_rows() > 0) {
            return $this->db->insert_id();
        }

        return false;
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
