<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Telegram_signals_model extends CI_Model
{
    // Cache del check de existencia de tablas de analytics (ea_trade_*). Evita romper el
    // flujo de trading si la migracion 2026-06-16-ea-trade-snapshots.sql aun no corrio.
    private $_ea_ready = null;

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
        // INSERT ... SELECT directo: evita fetch + loop PHP
        $sql = "INSERT IGNORE INTO user_telegram_signals
                (telegram_signal_id, user_id, ticker_symbol, mt_ticker, status, created_at)
                SELECT ?, ust.user_id, ust.ticker_symbol, COALESCE(ust.mt_ticker, ''), 'available', NOW()
                FROM user_selected_tickers ust
                JOIN available_tickers at ON ust.ticker_symbol = at.symbol
                WHERE ust.ticker_symbol = ?
                AND ust.active = 1
                AND at.active = 1";

        $this->db->query($sql, [$telegram_signal_id, $ticker_symbol]);
        return $this->db->affected_rows();
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

        $this->db->update('user_telegram_signals', [
            'status' => 'claimed',
            'updated_at' => date('Y-m-d H:i:s')
        ]);

        // Ganamos el claim SOLO si esta query cambió la fila. update() devuelve TRUE aunque
        // 0 filas coincidan (otro proceso ya la reclamó); affected_rows() distingue al ganador
        // de la carrera y evita que dos EAs ejecuten la misma señal por duplicado. El cambio
        // de status available->claimed garantiza que una fila matcheada siempre cuente como afectada.
        $claimed = ($this->db->affected_rows() > 0);

        if ($claimed) {
            $this->append_event($user_signal_id, 'claimed');
        }

        return $claimed;
    }

    /**
     * Append an event to the event_log JSON array
     */
    private function append_event($user_signal_id, $event_type, $extra = [], $utc_time = null)
    {
        $at = $utc_time ? $this->convert_utc_to_local($utc_time) : date('Y-m-d H:i:s');

        // Fuente de verdad: tabla relacional consultable (ea_trade_events). El blob event_log
        // queda SOLO como fallback legacy mientras la migracion no haya corrido — sin duplicar.
        if ($this->ea_tables_ready()) {
            $this->record_event($user_signal_id, $event_type, $extra, $at);
        } else {
            $this->append_event_log($user_signal_id, $event_type, $extra, $at);
        }
    }

    /** Fallback legacy: append al blob JSON user_telegram_signals.event_log */
    private function append_event_log($user_signal_id, $event_type, $extra, $at)
    {
        $this->db->select('event_log');
        $this->db->where('id', $user_signal_id);
        $row = $this->db->get('user_telegram_signals')->row();

        $log = ($row && !empty($row->event_log)) ? (json_decode($row->event_log, true) ?: []) : [];
        $log[] = array_merge(['event' => $event_type, 'at' => $at], (array)$extra);

        $this->db->where('id', $user_signal_id);
        $this->db->update('user_telegram_signals', ['event_log' => json_encode($log)]);
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
    public function report_open($user_signal_id, $open_data, $raw_body = null)
    {
        // Guard de existencia -> el controller devuelve 404 si el id no existe (ver signal_exists()).
        if (!$this->signal_exists($user_signal_id)) {
            return false;
        }

        // Idempotencia: un reporte reenviado (mismo body) es no-op (ver begin_report()).
        $report_state = $this->begin_report($user_signal_id, 'open', $raw_body, $open_data);
        if ($report_state === 'duplicate') {
            return true;
        }

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

        // NUEVO: Guardar mt_corrected_data con precios post-corrección (lo que realmente usa MT5)
        if (isset($open_data['mt_corrected_data'])) {
            $update_data['mt_corrected_data'] = json_encode($open_data['mt_corrected_data']);
        }

        // Actualizar execution_data con info completa
        if ($open_data) {
            $update_data['execution_data'] = json_encode($open_data);
        }

        $this->db->where('id', $user_signal_id);
        $result = $this->db->update('user_telegram_signals', $update_data);

        if ($result) {
            $is_market = isset($open_data['order_type']) && in_array($open_data['order_type'], ['ORDER_TYPE_BUY', 'ORDER_TYPE_SELL']);
            $event_extra = ['order_type' => $open_data['order_type'] ?? null];
            if ($is_market) {
                $event_extra['entry'] = $open_data['real_entry_price'] ?? null;
                $event_extra['volume'] = $open_data['real_volume'] ?? null;
                $event_extra['stop_loss'] = $open_data['real_stop_loss'] ?? null;
            }
            $this->append_event(
                $user_signal_id,
                $is_market ? 'open' : 'pending_order',
                $event_extra,
                $open_data['execution_time'] ?? null
            );

            // Analytics: snapshot estatico + proceso de correccion (ea_trade_* )
            $this->record_ea_open($user_signal_id, $open_data);
        }

        $this->finish_report($report_state);
        return $result;
    }

    /**
     * ACTUALIZADO: Reportar progreso de TPs/Breakeven (con conversión UTC)
     */
    public function report_progress($user_signal_id, $progress_data, $raw_body = null)
    {
        // Guard de existencia -> el controller devuelve 404 si el id no existe (ver signal_exists()).
        if (!$this->signal_exists($user_signal_id)) {
            return false;
        }

        // Idempotencia: un reporte reenviado (mismo body) es no-op (ver begin_report()).
        $report_state = $this->begin_report($user_signal_id, 'progress', $raw_body, $progress_data);
        if ($report_state === 'duplicate') {
            return true;
        }

        $update_data = [
            'updated_at' => date('Y-m-d H:i:s')
        ];

        // Cambiar status si era pending y ahora está ejecutándose
        if (isset($progress_data['now_open']) && $progress_data['now_open']) {
            $update_data['status'] = 'open';
            if (isset($progress_data['real_entry_price'])) {
                $update_data['real_entry_price'] = $progress_data['real_entry_price'];
            }
            // Guardar volumen real cuando pending order se ejecuta
            if (isset($progress_data['remaining_volume']) && $progress_data['remaining_volume'] > 0) {
                $update_data['real_volume'] = $progress_data['remaining_volume'];
            }
            // Guardar campos adicionales de ejecución real
            if (isset($progress_data['real_stop_loss'])) {
                $update_data['real_stop_loss'] = $progress_data['real_stop_loss'];
            }
            if (isset($progress_data['trade_id'])) {
                $update_data['trade_id'] = $progress_data['trade_id'];
            }
            $update_data['current_level'] = 0;
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

        // NUEVO: Mergear con execution_data existente en lugar de sobreescribir
        $existing_data = $this->get_existing_execution_data($user_signal_id);
        if ($existing_data) {
            $merged_data = array_merge($existing_data, $progress_data);
        } else {
            $merged_data = $progress_data;
        }
        $update_data['execution_data'] = json_encode($merged_data);

        $this->db->where('id', $user_signal_id);
        $result = $this->db->update('user_telegram_signals', $update_data);

        if ($result) {
            $utc_time = $progress_data['execution_time'] ?? null;

            // Pending order filled
            if (isset($progress_data['now_open']) && $progress_data['now_open']) {
                $this->append_event($user_signal_id, 'filled', [
                    'entry' => $progress_data['real_entry_price'] ?? null,
                ], $utc_time);
            }

            // TP hit (level >= 1, has PNL — excludes breakeven-only reports)
            $level = $progress_data['current_level'] ?? 0;
            $pnl = $progress_data['gross_pnl'] ?? 0;
            if ($level >= 1 && $pnl != 0) {
                $this->append_event($user_signal_id, 'tp', [
                    'level' => $level,
                    'price' => $progress_data['last_price'] ?? null,
                    'pnl' => $pnl,
                    'closed_pct' => $progress_data['volume_closed_percent'] ?? null,
                ], $utc_time);
            }

            // Breakeven
            if (isset($progress_data['new_stop_loss']) && $progress_data['new_stop_loss'] > 0) {
                $this->append_event($user_signal_id, 'breakeven', [
                    'new_sl' => $progress_data['new_stop_loss'],
                ], $utc_time);
            }
        }

        $this->finish_report($report_state);
        return $result;
    }

    /**
     * ACTUALIZADO: Reportar cierre final de posición (con conversión UTC)
     * También inserta en tabla trades para unificar Trade History (solo para cierres reales)
     */
    public function report_close($user_signal_id, $close_data, $raw_body = null)
    {
        // Guard de existencia -> el controller devuelve 404 si el id no existe (ver signal_exists()).
        if (!$this->signal_exists($user_signal_id)) {
            return false;
        }

        // Idempotencia: un reporte reenviado (mismo body) es no-op (ver begin_report()).
        $report_state = $this->begin_report($user_signal_id, 'close', $raw_body, $close_data);
        if ($report_state === 'duplicate') {
            return true;
        }

        $close_reason = $close_data['close_reason'] ?? '';
        $is_real_close = !$this->is_failure_reason($close_reason);

        $update_data = [
            'status' => $this->resolve_close_status($close_reason),
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

        // Volumen cerrado al 100% solo para cierres reales
        if ($is_real_close) {
            $update_data['volume_closed_percent'] = 100.00;
            $update_data['remaining_volume'] = 0.00;
        }

        // Exit level final
        if (isset($close_data['exit_level'])) {
            $update_data['exit_level'] = $close_data['exit_level'];
        }

        // Convertir execution_time de UTC a local
        if (isset($close_data['execution_time'])) {
            $close_data['execution_time_local'] = $this->convert_utc_to_local($close_data['execution_time']);
        }

        // Mergear con execution_data existente en lugar de sobreescribir
        $existing_data = $this->get_existing_execution_data($user_signal_id);
        if ($existing_data) {
            $merged_data = array_merge($existing_data, $close_data);
        } else {
            $merged_data = $close_data;
        }
        $update_data['execution_data'] = json_encode($merged_data);

        $this->db->where('id', $user_signal_id);
        $result = $this->db->update('user_telegram_signals', $update_data);

        if ($result) {
            $event_type = $is_real_close ? 'closed' : 'failed';
            $this->append_event($user_signal_id, $event_type, [
                'reason' => $close_data['close_reason'] ?? null,
                'exit_level' => $close_data['exit_level'] ?? null,
                'pnl' => $update_data['gross_pnl'] ?? null,
                'price' => $close_data['last_price'] ?? null,
            ], $close_data['execution_time'] ?? null);

            // Solo insertar en trades si fue un cierre real (la posición operó)
            if ($is_real_close) {
                $this->insert_closed_trade_to_trades($user_signal_id, $close_data, $update_data['gross_pnl']);
            }

            // Analytics: completar resultado del snapshot + correccion fallida (ea_trade_*)
            $cum_pnl = isset($update_data['gross_pnl'])
                ? $update_data['gross_pnl']
                : $this->get_current_gross_pnl($user_signal_id);
            $this->record_ea_close($user_signal_id, $close_data, $cum_pnl);
        }

        $this->finish_report($report_state);
        return $result;
    }

    /**
     * Determina si un close_reason indica un fallo pre-ejecución (nunca operó)
     */
    private function is_failure_reason($close_reason)
    {
        $failure_reasons = [
            'ORDER_CANCELLED',
            'INVALID_TPS',
            'INVALID_STOPLOSS',
            'INVALID_OPTYPE',
            'INVALID_ENTRY',
            'PRICE_CORRECTION_ERROR',
            'SPREAD_TOO_HIGH',
            'VOLUME_ERROR',
            'SL_TOO_CLOSE',
            'EXECUTION_FAILED',
        ];
        return in_array($close_reason, $failure_reasons);
    }

    /**
     * Mapea close_reason al status correcto de user_telegram_signals
     */
    private function resolve_close_status($close_reason)
    {
        if ($close_reason === 'ORDER_CANCELLED') {
            return 'cancelled';
        }
        if ($this->is_failure_reason($close_reason)) {
            return 'failed_execution';
        }
        return 'closed';
    }

    /**
     * Inserta el trade cerrado en la tabla trades para unificar Trade History
     */
    private function insert_closed_trade_to_trades($user_signal_id, $close_data, $final_pnl)
    {
        // Obtener datos de la señal del usuario
        $signal = $this->get_user_signal_by_id($user_signal_id);
        if (!$signal) {
            return false;
        }

        // Obtener o crear strategy_id para ATVIP
        $strategy_id = $this->get_or_create_atvip_strategy($signal->user_id);
        if (!$strategy_id) {
            return false;
        }

        // Determinar side basado en order_type
        $side = 'BUY';
        if (!empty($signal->order_type)) {
            $side = (strpos($signal->order_type, 'SELL') !== false) ? 'SELL' : 'BUY';
        }

        $this->load->model('Trade_model');

        $trade_data = [
            'user_id' => $signal->user_id,
            'strategy_id' => $strategy_id,
            'symbol' => $signal->ticker_symbol,
            'timeframe' => 'ATVIP',
            'side' => $side,
            'trade_type' => 'forex',
            'quantity' => $signal->real_volume ?? 0.01,
            'entry_price' => $signal->real_entry_price ?? 0,
            'exit_price' => $close_data['last_price'] ?? 0,
            'pnl' => $final_pnl ?? 0,
            'status' => 'closed',
            'source' => 'atvip',
            'user_signal_id' => $user_signal_id,
            'order_id' => $signal->trade_id ?? null,
            'created_at' => $signal->created_at,
            'closed_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ];

        return $this->Trade_model->add_trade($trade_data);
    }

    /**
     * Obtiene o crea una estrategia ATVIP para el usuario
     */
    private function get_or_create_atvip_strategy($user_id)
    {
        $this->load->model('Strategy_model');

        // Buscar estrategia ATVIP existente
        $this->db->where('user_id', $user_id);
        $this->db->where('strategy_id', 'ATVIP_SIGNALS');
        $existing = $this->db->get('strategies')->row();

        if ($existing) {
            return $existing->id;
        }

        // Crear estrategia ATVIP si no existe
        $strategy_data = [
            'user_id' => $user_id,
            'strategy_id' => 'ATVIP_SIGNALS',
            'name' => 'ATVIP Signals',
            'type' => 'forex',
            'platform' => 'metatrader',
            'description' => 'Trades from ATVIP Telegram signals',
            'active' => 1,
            'created_at' => date('Y-m-d H:i:s')
        ];

        $this->db->insert('strategies', $strategy_data);
        return $this->db->insert_id();
    }

    /**
     * Obtiene una señal de usuario por ID
     */
    private function get_user_signal_by_id($user_signal_id)
    {
        $this->db->where('id', $user_signal_id);
        return $this->db->get('user_telegram_signals')->row();
    }

    /**
     * ¿Existe la user_signal? Guard para los reportes del EA. El 404 no puede inferirse de
     * update() (devuelve TRUE con 0 filas) ni de affected_rows() (MySQL devuelve 0 cuando los
     * valores no cambian — p.ej. dos progress idénticos en el mismo segundo darían un falso 404).
     * Chequeo de existencia explícito: distingue "id inexistente" de "update sin cambios".
     */
    private function signal_exists($user_signal_id)
    {
        return (bool) $this->db->select('id')
            ->get_where('user_telegram_signals', ['id' => $user_signal_id], 1)->row();
    }

    // =====================================================================
    // IDEMPOTENCIA DE REPORTES (ea_report_dedup)
    // Hace open/progress/close seguros de recibir 2 veces (base para el retry futuro del EA).
    // Guard table_exists: si la migracion no corrio, se aplica directo (= comportamiento previo).
    // =====================================================================

    /** ¿Existe la tabla de dedup? (cacheado) */
    private $_dedup_ready = null;
    private function dedup_ready()
    {
        if ($this->_dedup_ready === null) {
            $this->_dedup_ready = $this->db->table_exists('ea_report_dedup');
        }
        return $this->_dedup_ready;
    }

    /**
     * Gate de idempotencia. Inserta la huella (sha1 del body) del reporte; un reenvío del mismo
     * body choca el UNIQUE y se detecta como duplicado. Devuelve uno de:
     *   'duplicate'   -> ya procesado: el llamador hace no-op y `return true`.
     *   'transaction' -> primera vez, transacción abierta: aplicar y luego finish_report().
     *   'no_dedup'    -> tabla no migrada: aplicar sin transacción y luego finish_report().
     * INSERT IGNORE + affected_rows() (no captura de excepción): robusto con db_debug on/off.
     */
    private function begin_report($user_signal_id, $endpoint, $raw_body, $data)
    {
        if (!$this->dedup_ready()) {
            return 'no_dedup';
        }

        $hash = sha1($raw_body !== null ? $raw_body : json_encode($data));

        $this->db->trans_start();
        $this->db->query(
            "INSERT IGNORE INTO ea_report_dedup (user_signal_id, endpoint, body_hash, created_at)
             VALUES (?, ?, ?, ?)",
            [$user_signal_id, $endpoint, $hash, date('Y-m-d H:i:s')]
        );

        if ((int) $this->db->affected_rows() === 0) {
            // Duplicado: INSERT IGNORE no escribió nada -> no-op. Cerrar la transacción.
            $this->db->trans_complete();
            return 'duplicate';
        }

        return 'transaction';
    }

    /**
     * Cierra la transacción abierta por begin_report. Solo actúa si se abrió una (estado
     * 'transaction'); si algún query falló, trans_complete() hace rollback y borra también la
     * fila de dedup, dejando el reintento limpio. No-op cuando el dedup no estaba disponible.
     */
    private function finish_report($report_state)
    {
        if ($report_state === 'transaction') {
            $this->db->trans_complete();
        }
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

    public function complete_signal_dual($signal_id, $analysis_data, $analysis_by_provider, $validated)
    {
        $analysis_json = json_decode($analysis_data, true);
        $op_type = isset($analysis_json['op_type']) ? $analysis_json['op_type'] : null;

        $update_data = [
            'status' => $validated ? 'completed' : 'pending_review',
            'analysis_data' => $analysis_data,
            'ai_validated' => $validated ? 1 : 0,
            'op_type' => $op_type,
            'updated_at' => date('Y-m-d H:i:s')
        ];

        // Guardar el crudo de cada proveedor en su columna (whitelist de columnas existentes)
        $valid = ['openai', 'claude', 'gemini'];
        if (is_array($analysis_by_provider)) {
            foreach ($analysis_by_provider as $provider => $raw) {
                if (in_array($provider, $valid, true)) {
                    $update_data['analysis_' . $provider] = $raw;
                }
            }
        }

        $this->db->where('id', $signal_id);
        return $this->db->update('telegram_signals', $update_data);
    }

    public function resolve_signal($signal_id, $raw_ai_data, $ticker_symbol)
    {
        $raw_json = json_decode($raw_ai_data, true);
        if (!$raw_json) {
            return false;
        }

        $op_type = isset($raw_json['op_type']) ? $raw_json['op_type'] : null;

        $this->db->where('id', $signal_id);
        $updated = $this->db->update('telegram_signals', [
            'status' => 'completed',
            'analysis_data' => $raw_ai_data,
            'op_type' => $op_type,
            'ai_validated' => 1,
            'updated_at' => date('Y-m-d H:i:s')
        ]);

        if (!$updated) {
            return false;
        }

        return $this->create_user_signals_for_ticker($signal_id, $ticker_symbol);
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
     * Obtener todos los conteos de señales en una sola query
     */
    public function get_signal_counts()
    {
        $sql = "SELECT
            COUNT(*) as total,
            SUM(status = 'completed') as completed,
            SUM(status IN ('failed_crop', 'failed_analysis', 'failed_download')) as failed,
            SUM(created_at >= NOW() - INTERVAL 24 HOUR) as last_24h
            FROM telegram_signals";

        $row = $this->db->query($sql)->row();

        return [
            'total'    => (int) ($row->total ?? 0),
            'completed' => (int) ($row->completed ?? 0),
            'failed'   => (int) ($row->failed ?? 0),
            'last_24h' => (int) ($row->last_24h ?? 0),
        ];
    }

    // Backwards-compatible wrappers (deprecated - use get_signal_counts())
    public function count_signals_total()
    {
        $c = $this->get_signal_counts();
        return $c['total'];
    }
    public function count_signals_completed()
    {
        $c = $this->get_signal_counts();
        return $c['completed'];
    }
    public function count_signals_failed()
    {
        $c = $this->get_signal_counts();
        return $c['failed'];
    }
    public function count_signals_last_24h()
    {
        $c = $this->get_signal_counts();
        return $c['last_24h'];
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
        $cutoff = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        // Prune de las huellas de dedup de reportes (filas chicas, mismo horizonte).
        if ($this->dedup_ready()) {
            $this->db->where('created_at <', $cutoff)->delete('ea_report_dedup');
        }

        $this->db->where('created_at <', $cutoff);
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
            $cutoff = ($filters['date_range'] === 'today')
                ? date('Y-m-d 00:00:00')                                        // desde las 00:00 de hoy
                : date('Y-m-d H:i:s', strtotime('-' . (int)$filters['date_range'] . ' days'));

            // Las posiciones ACTIVAS (open/pending/claimed) se muestran SIEMPRE, sin importar la
            // fecha; el rango solo limita a las terminadas (closed/failed/cancelled). Así "Today"
            // no oculta un trade abierto de días previos.
            $this->db->group_start();
                $this->db->where_in('uts.status', ['open', 'pending', 'claimed']);
                $this->db->or_where('uts.created_at >=', $cutoff);
            $this->db->group_end();
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

        $rows = $this->db->get()->result();
        $this->attach_snap_corr($rows);  // enriquece con ea_trade_snapshots / ea_price_corrections
        return $rows;
    }

    /**
     * Adjunta ->snap (ea_trade_snapshots) y ->corr (ea_price_corrections) a cada fila por
     * user_signal_id, en 2 queries batch (sin N+1, sin colisiones de columnas). Si la
     * migracion no corrio o no hay filas, deja ambos en null.
     */
    private function attach_snap_corr(&$rows)
    {
        foreach ($rows as $r) { $r->snap = null; $r->corr = null; }
        if (empty($rows) || !$this->ea_tables_ready()) return;

        $ids = array();
        foreach ($rows as $r) $ids[] = $r->id;

        $snaps = array();
        $this->db->where_in('user_signal_id', $ids);
        foreach ($this->db->get('ea_trade_snapshots')->result() as $s) $snaps[$s->user_signal_id] = $s;

        $corrs = array();
        $this->db->where_in('user_signal_id', $ids);
        foreach ($this->db->get('ea_price_corrections')->result() as $c) $corrs[$c->user_signal_id] = $c;

        foreach ($rows as $r) {
            if (isset($snaps[$r->id])) $r->snap = $snaps[$r->id];
            if (isset($corrs[$r->id])) $r->corr = $corrs[$r->id];
        }
    }

    private function get_current_gross_pnl($user_signal_id)
    {
        $this->db->select('gross_pnl');
        $this->db->where('id', $user_signal_id);
        $result = $this->db->get('user_telegram_signals')->row();

        return $result ? $result->gross_pnl : 0.00;
    }

    /**
     * Obtener execution_data existente para mergear con nuevos datos
     */
    private function get_existing_execution_data($user_signal_id)
    {
        $this->db->select('execution_data');
        $this->db->where('id', $user_signal_id);
        $result = $this->db->get('user_telegram_signals')->row();

        if ($result && !empty($result->execution_data)) {
            $decoded = json_decode($result->execution_data, true);
            return is_array($decoded) ? $decoded : null;
        }
        return null;
    }

    // =====================================================================
    // ANALYTICS EA: ea_trade_snapshots / ea_trade_events / ea_price_corrections
    // Materializa en columnas consultables lo que el EA reporta (mirror del CSV journal).
    // Todo guardado tras ea_tables_ready(): si la migracion no corrio, no rompe nada.
    // =====================================================================

    /** Existen las tablas de analytics? (cacheado) */
    private function ea_tables_ready()
    {
        if ($this->_ea_ready === null) {
            $this->_ea_ready = $this->db->table_exists('ea_trade_snapshots');
        }
        return $this->_ea_ready;
    }

    /** Contexto de la señal (user_id, symbol, telegram_signal_id, ts) */
    private function get_signal_context($user_signal_id)
    {
        $this->db->select('user_id, ticker_symbol, telegram_signal_id, created_at');
        return $this->db->get_where('user_telegram_signals', ['id' => $user_signal_id])->row();
    }

    /** Columnas permitidas en ea_trade_snapshots (whitelist anti-columnas-basura) */
    private function snapshot_columns()
    {
        return [
            'telegram_signal_id','user_id','symbol','ea_version',
            'dir','entry_raw','sl_raw','corr_on','corr_factor','entry','sl',
            'tp1','tp2','tp3','tp4','tp5','r_dist','t1','spread_real','spread_tol',
            'slip_real','slip_tol','price_signal','dist_entry','side','k_band',
            'stops_min','sl_dist','order_type','real_entry','real_volume','trade_id',
            'max_level','vol_closed_pct','exit_level','close_reason','gross_pnl',
            'last_price','result','cfg_risk_percent','cfg_k_stop_ratio','cfg_k_limit_ratio',
            'cfg_m_slip_ratio','cfg_c_spread_ratio','cfg_enable_slip','cfg_enable_spread',
            'cfg_enable_corr','cfg_be_level','cfg_tp1_pct','cfg_tp2_pct','cfg_tp3_pct',
            'cfg_tp4_pct','cfg_tp5_pct','cfg_extra','ts_signal','opened_at','closed_at',
        ];
    }

    /** Filtra un array a las columnas validas del snapshot (cfg_extra -> json) */
    private function filter_snapshot_columns($incoming)
    {
        $fields = array_intersect_key($incoming, array_flip($this->snapshot_columns()));
        if (isset($fields['cfg_extra']) && is_array($fields['cfg_extra'])) {
            $fields['cfg_extra'] = json_encode($fields['cfg_extra']);
        }
        return $fields;
    }

    /** Upsert del snapshot por user_signal_id. Devuelve snapshot_id. */
    private function upsert_snapshot($user_signal_id, $fields)
    {
        if (empty($fields)) return null;

        $existing = $this->db->select('id')
            ->get_where('ea_trade_snapshots', ['user_signal_id' => $user_signal_id])->row();

        if ($existing) {
            if (!empty($fields)) {
                $this->db->where('id', $existing->id)->update('ea_trade_snapshots', $fields);
            }
            return $existing->id;
        }

        $fields['user_signal_id'] = $user_signal_id;
        if (empty($fields['created_at'])) $fields['created_at'] = date('Y-m-d H:i:s');
        $this->db->insert('ea_trade_snapshots', $fields);
        return $this->db->insert_id();
    }

    /** Inserta el snapshot estatico + correccion al abrir (o al fallar pre-ejecucion en close) */
    private function record_ea_open($user_signal_id, $open_data)
    {
        if (!$this->ea_tables_ready()) return;

        $ctx = $this->get_signal_context($user_signal_id);

        // Base: contexto siempre presente (satisface NOT NULL de user_id/symbol)
        $fields = [];
        if ($ctx) {
            $fields['user_id']            = $ctx->user_id;
            $fields['symbol']             = $ctx->ticker_symbol;
            $fields['telegram_signal_id'] = $ctx->telegram_signal_id;
            $fields['ts_signal']          = $ctx->created_at;
        }

        // Fallbacks desde el payload top-level (los pisa el objeto snapshot si viene)
        if (isset($open_data['real_entry_price'])) $fields['real_entry']  = $open_data['real_entry_price'];
        if (isset($open_data['real_volume']))      $fields['real_volume'] = $open_data['real_volume'];
        if (isset($open_data['trade_id']))         $fields['trade_id']    = $open_data['trade_id'];
        if (isset($open_data['symbol']))           $fields['symbol']      = $open_data['symbol'];
        if (isset($open_data['ea_version']))       $fields['ea_version']  = $open_data['ea_version'];
        if (isset($open_data['execution_time_local'])) $fields['opened_at'] = $open_data['execution_time_local'];

        // Objetos nuevos del EA (keys == nombres de columna)
        $incoming = [];
        if (isset($open_data['snapshot']) && is_array($open_data['snapshot'])) {
            $incoming = array_merge($incoming, $open_data['snapshot']);
        }
        if (isset($open_data['ea_config']) && is_array($open_data['ea_config'])) {
            $incoming = array_merge($incoming, $open_data['ea_config']);
        }
        $fields = array_merge($fields, $this->filter_snapshot_columns($incoming));

        $snapshot_id = $this->upsert_snapshot($user_signal_id, $fields);

        if (isset($open_data['correction']) && is_array($open_data['correction'])) {
            $this->record_correction($user_signal_id, $snapshot_id, $open_data['correction'],
                $ctx ? $ctx->ticker_symbol : ($open_data['symbol'] ?? ''));
        }
    }

    /** Completa los campos de resultado del snapshot al cerrar (+ correccion fallida) */
    private function record_ea_close($user_signal_id, $close_data, $cumulative_pnl)
    {
        if (!$this->ea_tables_ready()) return;

        $ctx = $this->get_signal_context($user_signal_id);

        // Contexto para garantizar NOT NULL si el snapshot aun no existe (fallo pre-open)
        $fields = [];
        if ($ctx) {
            $fields['user_id']            = $ctx->user_id;
            $fields['symbol']             = $ctx->ticker_symbol;
            $fields['telegram_signal_id'] = $ctx->telegram_signal_id;
        }

        if (isset($close_data['exit_level']))     $fields['exit_level']     = $close_data['exit_level'];
        if (isset($close_data['close_reason']))   { $fields['close_reason'] = $close_data['close_reason'];
                                                    $fields['result']       = $close_data['close_reason']; }
        if (isset($close_data['last_price']))     $fields['last_price']     = $close_data['last_price'];
        if (isset($close_data['max_level']))      $fields['max_level']      = $close_data['max_level'];
        if (isset($close_data['vol_closed_pct'])) $fields['vol_closed_pct'] = $close_data['vol_closed_pct'];
        if (isset($close_data['execution_time_local'])) $fields['closed_at'] = $close_data['execution_time_local'];
        $fields['gross_pnl'] = $cumulative_pnl;

        $snapshot_id = $this->upsert_snapshot($user_signal_id, $fields);

        if (isset($close_data['correction']) && is_array($close_data['correction'])) {
            $this->record_correction($user_signal_id, $snapshot_id, $close_data['correction'],
                $ctx ? $ctx->ticker_symbol : '');
        }
    }

    /** Upsert del proceso de correccion (1 fila por señal) */
    private function record_correction($user_signal_id, $snapshot_id, $c, $symbol)
    {
        if (!$this->ea_tables_ready() || empty($c) || !is_array($c)) return;

        $cols = [
            'enabled','fut_price','fut_candle_time','mt5_price','mt5_bar_time','mt5_bar_index',
            'signal_mt5_price','broker_offset_sec','target_broker_time','bar_gap_sec',
            'candles_aligned','corr_factor','deviation_pct','timestamp_age_sec',
            'status','error_stage','error_message',
        ];
        $fields = array_intersect_key($c, array_flip($cols));
        if (empty($fields)) return;

        $fields['symbol'] = $symbol;
        if ($snapshot_id) $fields['snapshot_id'] = $snapshot_id;
        if (empty($fields['status'])) {
            $fields['status'] = !empty($fields['error_stage']) ? 'ERROR' : 'OK';
        }

        $existing = $this->db->select('id')
            ->get_where('ea_price_corrections', ['user_signal_id' => $user_signal_id])->row();

        if ($existing) {
            $this->db->where('id', $existing->id)->update('ea_price_corrections', $fields);
        } else {
            $fields['user_signal_id'] = $user_signal_id;
            $fields['created_at']     = date('Y-m-d H:i:s');
            $this->db->insert('ea_price_corrections', $fields);
        }
    }

    /** Inserta una fila en la timeline relacional (ea_trade_events) */
    private function record_event($user_signal_id, $event_type, $extra, $event_time_local)
    {
        if (!$this->ea_tables_ready()) return;

        $snap = $this->db->select('id')
            ->get_where('ea_trade_snapshots', ['user_signal_id' => $user_signal_id])->row();

        $this->db->select_max('seq');
        $seq_row = $this->db->get_where('ea_trade_events', ['user_signal_id' => $user_signal_id])->row();
        $seq = ($seq_row && $seq_row->seq) ? ((int)$seq_row->seq + 1) : 1;

        $data = [
            'snapshot_id'           => $snap ? $snap->id : null,
            'user_signal_id'        => $user_signal_id,
            'seq'                   => $seq,
            'event_type'            => $event_type,
            'current_level'         => isset($extra['level']) ? $extra['level'] : null,
            'last_price'            => isset($extra['price']) ? $extra['price']
                                       : (isset($extra['entry']) ? $extra['entry'] : null),
            'new_stop_loss'         => isset($extra['new_sl']) ? $extra['new_sl'] : null,
            'volume_closed_percent' => isset($extra['closed_pct']) ? $extra['closed_pct'] : null,
            'remaining_volume'      => isset($extra['remaining_volume']) ? $extra['remaining_volume'] : null,
            // pnl_delta solo cuando es claramente incremental (TP parcial); el resto lo da el acumulado
            'pnl_delta'             => ($event_type === 'tp' && isset($extra['pnl'])) ? $extra['pnl'] : null,
            'pnl_cumulative'        => $this->get_current_gross_pnl($user_signal_id),
            'message'               => isset($extra['message']) ? $extra['message'] : null,
            'order_type'            => isset($extra['order_type']) ? $extra['order_type'] : null,
            'volume'                => isset($extra['volume']) ? $extra['volume'] : null,
            'close_reason'          => isset($extra['reason']) ? $extra['reason'] : null,
            'event_time'            => $event_time_local,
            'created_at'            => date('Y-m-d H:i:s'),
        ];

        $this->db->insert('ea_trade_events', $data);
    }

    // =====================================================================
    // LECTURA PARA JOURNALS (drill-down: overview -> symbol -> trade detail)
    // Base = user_telegram_signals (siempre poblada) + LEFT JOIN ea_trade_* (rico cuando existe).
    // =====================================================================

    /** Estados que representan un trade que el EA accionó (operó o rechazó). */
    private function journal_statuses()
    {
        return ['open', 'closed', 'failed_execution', 'cancelled', 'pending'];
    }

    /** Snapshot estatico de un trade (o null). */
    public function get_trade_snapshot($user_signal_id)
    {
        if (!$this->ea_tables_ready()) return null;
        return $this->db->get_where('ea_trade_snapshots', ['user_signal_id' => $user_signal_id])->row();
    }

    /** Proceso de correccion de un trade (o null). */
    public function get_trade_correction($user_signal_id)
    {
        if (!$this->ea_tables_ready()) return null;
        return $this->db->get_where('ea_price_corrections', ['user_signal_id' => $user_signal_id])->row();
    }

    /**
     * Timeline normalizada (shape legacy event_log). Preferente: ea_trade_events.
     * Fallback: blob event_log para señales historicas (sin filas en la tabla).
     */
    public function get_timeline_events($signal)
    {
        $uid = is_object($signal) ? $signal->id : $signal;

        if ($this->ea_tables_ready()) {
            $this->db->order_by('seq', 'ASC');
            $rows = $this->db->get_where('ea_trade_events', ['user_signal_id' => $uid])->result_array();
            if (!empty($rows)) {
                $out = [];
                foreach ($rows as $r) {
                    $is_close = in_array($r['event_type'], ['closed', 'failed'], true);
                    $out[] = [
                        'event'      => $r['event_type'],
                        'at'         => $r['event_time'],
                        'order_type' => $r['order_type'],
                        'entry'      => $r['last_price'],
                        'price'      => $r['last_price'],
                        'volume'     => $r['volume'],
                        'level'      => $r['current_level'],
                        'pnl'        => $is_close ? $r['pnl_cumulative'] : $r['pnl_delta'],
                        'closed_pct' => $r['volume_closed_percent'],
                        'new_sl'     => $r['new_stop_loss'],
                        'reason'     => $r['close_reason'],
                    ];
                }
                return $out;
            }
        }

        // Fallback legacy
        if (is_object($signal) && !empty($signal->event_log)) {
            $decoded = json_decode($signal->event_log, true);
            return is_array($decoded) ? $decoded : [];
        }
        return [];
    }

    /** Detalle de un trade para journals (admin: cualquier usuario). Mismos joins que get_user_signal_detail. */
    public function get_signal_detail_admin($user_signal_id)
    {
        $this->db->select('uts.*, ts.ticker_symbol, ts.image_path, ts.tradingview_url,
                          ts.message_text, ts.analysis_data, ts.op_type, at.display_decimals');
        $this->db->from('user_telegram_signals uts');
        $this->db->join('telegram_signals ts', 'uts.telegram_signal_id = ts.id');
        $this->db->join('available_tickers at', 'ts.ticker_symbol = at.symbol', 'left');
        $this->db->where('uts.id', $user_signal_id);
        return $this->db->get()->row();
    }

    /** Simbolos con trades accionados por el EA (para el overview). */
    public function journal_list_symbols()
    {
        $this->db->distinct();
        $this->db->select('ticker_symbol');
        $this->db->from('user_telegram_signals');
        $this->db->where_in('status', $this->journal_statuses());
        $this->db->order_by('ticker_symbol', 'ASC');
        $rows = $this->db->get()->result();
        $out = [];
        foreach ($rows as $r) $out[] = $r->ticker_symbol;
        return $out;
    }

    /**
     * Filas de un símbolo con las claves que espera Journal_stats (order_type, exit_level,
     * dist_entry, t1, close_reason, gross_pnl, ts_signal). Enriquece con el snapshot si existe.
     */
    public function journal_rows_for_symbol($sym)
    {
        if ($this->ea_tables_ready()) {
            $this->db->select("uts.id,
                               COALESCE(s.ts_signal, uts.created_at) AS ts_signal,
                               COALESCE(s.order_type, uts.order_type) AS order_type,
                               COALESCE(s.exit_level, uts.exit_level) AS exit_level,
                               COALESCE(s.close_reason, uts.close_reason) AS close_reason,
                               COALESCE(s.gross_pnl, uts.gross_pnl) AS gross_pnl,
                               s.dist_entry AS dist_entry, s.t1 AS t1", false);
            $this->db->from('user_telegram_signals uts');
            $this->db->join('ea_trade_snapshots s', 's.user_signal_id = uts.id', 'left');
        } else {
            $this->db->select("uts.id, uts.created_at AS ts_signal, uts.order_type AS order_type,
                               uts.exit_level AS exit_level, uts.close_reason AS close_reason,
                               uts.gross_pnl AS gross_pnl, NULL AS dist_entry, NULL AS t1", false);
            $this->db->from('user_telegram_signals uts');
        }
        $this->db->where('uts.ticker_symbol', $sym);
        $this->db->where_in('uts.status', $this->journal_statuses());
        $this->db->order_by('ts_signal', 'ASC');
        return $this->db->get()->result_array();
    }

    /** Lista de trades de un símbolo para la tabla de la subpágina (objetos). */
    public function journal_trades_for_symbol($sym)
    {
        $this->db->select("uts.id, uts.status, uts.current_level, uts.real_volume,
                           uts.exit_level AS uts_exit_level, uts.close_reason AS uts_close_reason,
                           uts.gross_pnl AS uts_gross_pnl, uts.order_type AS uts_order_type,
                           uts.created_at, ts.op_type", false);
        $this->db->from('user_telegram_signals uts');
        $this->db->join('telegram_signals ts', 'uts.telegram_signal_id = ts.id');
        $this->db->where('uts.ticker_symbol', $sym);
        $this->db->where_in('uts.status', $this->journal_statuses());
        $this->db->order_by('uts.created_at', 'DESC');
        $rows = $this->db->get()->result();

        if (!$this->ea_tables_ready()) {
            foreach ($rows as $r) { $r->snap = null; }
            return $rows;
        }
        // Enriquecer con snapshot (order_type/exit/close/pnl/ts del EA) sin N+1: un IN
        $ids = [];
        foreach ($rows as $r) $ids[] = $r->id;
        $snaps = [];
        if (!empty($ids)) {
            $this->db->where_in('user_signal_id', $ids);
            foreach ($this->db->get('ea_trade_snapshots')->result() as $s) {
                $snaps[$s->user_signal_id] = $s;
            }
        }
        foreach ($rows as $r) $r->snap = isset($snaps[$r->id]) ? $snaps[$r->id] : null;
        return $rows;
    }
}
