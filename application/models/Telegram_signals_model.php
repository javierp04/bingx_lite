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
     * Helper: Convertir UTC timestamp del EA a hora local (Argentina UTC-3)
     */
    private function convert_utc_to_local($utc_timestamp)
    {
        if (empty($utc_timestamp)) {
            return date('Y-m-d H:i:s'); // Fallback a hora actual local
        }

        try {
            // Crear DateTime en UTC
            $utc_dt = new DateTime($utc_timestamp, new DateTimeZone('UTC'));
            // Convertir a timezone local (Argentina)
            $utc_dt->setTimezone(new DateTimeZone('America/Argentina/Buenos_Aires'));
            return $utc_dt->format('Y-m-d H:i:s');
        } catch (Exception $e) {
            // En caso de error, usar hora actual
            return date('Y-m-d H:i:s');
        }
    }

    /**
     * ACTUALIZADO: Reportar apertura de posición (con conversión UTC)
     */
    public function report_open($user_signal_id, $open_data)
    {
        $update_data = [
            'updated_at' => date('Y-m-d H:i:s')
        ];

        // Determinar status y current_level según tipo de orden
        if (isset($open_data['order_type'])) {
            $is_market = in_array($open_data['order_type'], ['ORDER_TYPE_BUY', 'ORDER_TYPE_SELL']);
            $update_data['status'] = $is_market ? 'open' : 'pending';
            $update_data['current_level'] = $is_market ? 0 : -2;  // CORREGIDO: 0 para market, -2 para pending
            $update_data['order_type'] = $open_data['order_type'];
        }

        // Datos de ejecución real (solo si es market)
        if (isset($open_data['real_entry_price'])) {
            $update_data['real_entry_price'] = $open_data['real_entry_price'];
        }
        if (isset($open_data['real_stop_loss'])) {
            $update_data['real_stop_loss'] = $open_data['real_stop_loss'];
        }
        if (isset($open_data['real_volume'])) {
            $update_data['real_volume'] = $open_data['real_volume'];
            $update_data['remaining_volume'] = $open_data['real_volume'];
        }
        if (isset($open_data['trade_id'])) {
            $update_data['trade_id'] = $open_data['trade_id'];
        }

        // Resetear campos de progreso
        $update_data['volume_closed_percent'] = 0.00;
        $update_data['gross_pnl'] = 0.00;

        // NUEVO: Convertir execution_time de UTC a local
        if (isset($open_data['execution_time'])) {
            $open_data['execution_time_local'] = $this->convert_utc_to_local($open_data['execution_time']);
        }

        // NUEVO: Guardar mt_execution_data con datos originales de la señal (pre-corrección)
        if (isset($open_data['signal_data'])) {
            $update_data['mt_execution_data'] = json_encode($open_data['signal_data']);
        }

        // Actualizar execution_data con info completa
        if ($open_data) {
            $update_data['execution_data'] = json_encode($open_data);
        }

        $this->db->where('id', $user_signal_id);
        return $this->db->update('user_telegram_signals', $update_data);
    }

    /**
     * ACTUALIZADO: Reportar progreso de TPs/Breakeven (con conversión UTC)
     */
    public function report_progress($user_signal_id, $progress_data)
    {
        $update_data = [
            'updated_at' => date('Y-m-d H:i:s')
        ];

        // Cambiar status si era pending y ahora está ejecutándose
        if (isset($progress_data['now_open']) && $progress_data['now_open']) {
            $update_data['status'] = 'open';
            if (isset($progress_data['real_entry_price'])) {
                $update_data['real_entry_price'] = $progress_data['real_entry_price'];
            }
        }

        // Actualizar nivel actual alcanzado
        if (isset($progress_data['current_level'])) {
            $update_data['current_level'] = $progress_data['current_level'];
        }

        // Actualizar volumen cerrado y restante
        if (isset($progress_data['volume_closed_percent'])) {
            $update_data['volume_closed_percent'] = $progress_data['volume_closed_percent'];
        }
        if (isset($progress_data['remaining_volume'])) {
            $update_data['remaining_volume'] = $progress_data['remaining_volume'];
        }

        // NUEVO: PNL acumulado en lugar de sobrescribir
        if (isset($progress_data['gross_pnl'])) {
            $current_pnl = $this->get_current_gross_pnl($user_signal_id);
            $update_data['gross_pnl'] = $current_pnl + $progress_data['gross_pnl'];
        }

        if (isset($progress_data['last_price'])) {
            $update_data['last_price'] = $progress_data['last_price'];
        }

        // Actualizar stop loss si se reporta
        if (isset($progress_data['new_stop_loss'])) {
            $update_data['real_stop_loss'] = $progress_data['new_stop_loss'];
        }

        // Convertir execution_time de UTC a local
        if (isset($progress_data['execution_time'])) {
            $progress_data['execution_time_local'] = $this->convert_utc_to_local($progress_data['execution_time']);
        }

        // Mantener execution_data actualizada
        $update_data['execution_data'] = json_encode($progress_data);

        $this->db->where('id', $user_signal_id);
        return $this->db->update('user_telegram_signals', $update_data);
    }

    /**
     * ACTUALIZADO: Reportar cierre final de posición (con conversión UTC)
     */
    public function report_close($user_signal_id, $close_data)
    {
        $update_data = [
            'status' => 'closed',
            'updated_at' => date('Y-m-d H:i:s')
        ];

        // Datos finales
        if (isset($close_data['close_reason'])) {
            $update_data['close_reason'] = $close_data['close_reason'];
        }
        if (isset($close_data['gross_pnl'])) {
            $current_pnl = $this->get_current_gross_pnl($user_signal_id);
            $update_data['gross_pnl'] = $current_pnl + $close_data['gross_pnl'];
        }
        if (isset($close_data['last_price'])) {
            $update_data['last_price'] = $close_data['last_price'];
        }

        // Volumen cerrado al 100%
        $update_data['volume_closed_percent'] = 100.00;
        $update_data['remaining_volume'] = 0.00;

        // Exit level final
        if (isset($close_data['exit_level'])) {
            $update_data['exit_level'] = $close_data['exit_level'];
        }

        // NUEVO: Convertir execution_time de UTC a local
        if (isset($close_data['execution_time'])) {
            $close_data['execution_time_local'] = $this->convert_utc_to_local($close_data['execution_time']);
        }

        // Mantener execution_data actualizada
        $update_data['execution_data'] = json_encode($close_data);

        $this->db->where('id', $user_signal_id);
        return $this->db->update('user_telegram_signals', $update_data);
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

    public function get_user_signals_with_filters($user_id, $filters = array())
    {
        $this->db->select('uts.*, ts.ticker_symbol, ts.image_path, ts.tradingview_url, ts.message_text, 
                      ts.analysis_data, ts.op_type, ts.created_at as telegram_created_at,
                      ust_info.active as ticker_is_active');
        $this->db->from('user_telegram_signals uts');
        $this->db->join('telegram_signals ts', 'uts.telegram_signal_id = ts.id');

        // NUEVO: Join para obtener status del ticker
        $this->db->join(
            'user_selected_tickers ust_info',
            'uts.user_id = ust_info.user_id AND uts.ticker_symbol = ust_info.ticker_symbol',
            'left'
        );

        $this->db->where('uts.user_id', $user_id);

        // Aplicar filtros existentes...
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
        $this->db->limit(100);

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
                          ts.message_text, ts.analysis_data, ts.op_type, at.display_decimals');
        $this->db->from('user_telegram_signals uts');
        $this->db->join('telegram_signals ts', 'uts.telegram_signal_id = ts.id');
        $this->db->join('available_tickers at', 'ts.ticker_symbol = at.symbol', 'left');
        $this->db->where('uts.user_id', $user_id);
        $this->db->where('uts.id', $user_signal_id);

        return $this->db->get()->row();
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

    public function get_trading_dashboard_signals($user_id, $filters = array())
    {
        $this->db->select('uts.*, ts.ticker_symbol, ts.analysis_data, ts.op_type, ts.tradingview_url,
                      ts.created_at as telegram_created_at, ust_info.active as ticker_is_active,
                      at.display_decimals');
        $this->db->from('user_telegram_signals uts');
        $this->db->join('telegram_signals ts', 'uts.telegram_signal_id = ts.id');

        // NUEVO: Join con user_selected_tickers para verificar status
        $this->db->join(
            'user_selected_tickers ust_info',
            'uts.user_id = ust_info.user_id AND uts.ticker_symbol = ust_info.ticker_symbol',
            'left'
        );

        // Join con available_tickers para obtener display_decimals
        $this->db->join('available_tickers at', 'ts.ticker_symbol = at.symbol', 'left');

        $this->db->where('uts.user_id', $user_id);

        // NUEVO: Solo mostrar tickers activos en dashboard
        $this->db->where('ust_info.active', 1);

        // Aplicar filtros existentes...
        if (!empty($filters['status_filter'])) {
            switch ($filters['status_filter']) {
                case 'active':
                    $this->db->where_in('uts.status', ['pending', 'claimed', 'open']);
                    break;
                case 'pending':
                    $this->db->where_in('uts.status', ['pending', 'claimed']);
                    break;
                case 'open':
                    $this->db->where('uts.status', 'open');
                    break;
                case 'closed':
                    $this->db->where('uts.status', 'closed');
                    break;
            }
        }

        if (!empty($filters['ticker_filter'])) {
            $this->db->where('uts.ticker_symbol', $filters['ticker_filter']);
        }

        if (!empty($filters['date_range']) && $filters['date_range'] !== 'all') {
            $days = (int)$filters['date_range'];
            $this->db->where('uts.created_at >=', date('Y-m-d H:i:s', strtotime("-{$days} days")));
        }

        if (!empty($filters['pnl_filter'])) {
            switch ($filters['pnl_filter']) {
                case 'profit':
                    $this->db->where('uts.gross_pnl >', 0);
                    break;
                case 'loss':
                    $this->db->where('uts.gross_pnl <', 0);
                    break;
                case 'breakeven':
                    $this->db->where('uts.gross_pnl', 0);
                    break;
            }
        }

        $this->db->order_by('CASE uts.status 
                        WHEN "open" THEN 1 
                        WHEN "pending" THEN 2 
                        WHEN "claimed" THEN 2 
                        WHEN "closed" THEN 3 
                        ELSE 4 END', '', FALSE);
        $this->db->order_by('uts.created_at', 'DESC');
        $this->db->limit(100);

        return $this->db->get()->result();
    }

    private function get_current_gross_pnl($user_signal_id)
    {
        $this->db->select('gross_pnl');
        $this->db->where('id', $user_signal_id);
        $result = $this->db->get('user_telegram_signals')->row();

        return $result ? $result->gross_pnl : 0.00;
    }
}
