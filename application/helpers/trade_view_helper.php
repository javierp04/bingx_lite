<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * View-model único de un trade del EA. Fuente de verdad compartida por:
 *   - journals/trade_detail.php (detalle completo)
 *   - my_trading/partials/dashboard_content.php (card desplegable)
 *
 * Toma la señal (user_telegram_signals + joins) + snapshot (ea_trade_snapshots) +
 * correction (ea_price_corrections) y devuelve una estructura normalizada con la
 * asamblea de precios (raw/corregido/real), decisión de order type, gates, correción
 * y la fase del ciclo de vida — para que NINGUNA vista re-derive esto a mano.
 *
 * Solo presentación/derivación: no toca la base.
 */

if (!function_exists('tv_num')) {
    /** Formato numérico con fallback a guion. */
    function tv_num($v, $dec = 2) { return is_numeric($v) ? number_format((float)$v, $dec) : '—'; }
}

if (!function_exists('tv_has')) {
    /** ¿El objeto tiene el campo con valor real (no null/''/ausente)? */
    function tv_has($o, $f) { return $o && isset($o->$f) && $o->$f !== null && $o->$f !== ''; }
}

if (!function_exists('signal_phase')) {
    /**
     * Fase normalizada del ciclo de vida ATVIP — fuente única para badge/clase/contadores.
     * Calcula a partir de status + current_level + close_reason en un solo lugar.
     * @return array ['key','label','class','is_failure']
     */
    function signal_phase($signal)
    {
        $status = isset($signal->status) ? $signal->status : '';
        $level  = isset($signal->current_level) ? (int)$signal->current_level : 0;
        $reason = isset($signal->close_reason) ? $signal->close_reason : '';

        switch ($status) {
            case 'pending':
            case 'claimed':
                return ['key' => 'PENDING', 'label' => 'Pendiente', 'class' => 'bg-warning text-dark', 'is_failure' => false];

            case 'open':
                if ($level >= 1) {
                    return ['key' => 'LIVE_TP', 'label' => 'TP' . $level, 'class' => 'bg-success', 'is_failure' => false];
                }
                return ['key' => 'LIVE', 'label' => 'Abierta', 'class' => 'bg-primary', 'is_failure' => false];

            case 'closed':
                $meta = function_exists('journal_reason_meta') ? journal_reason_meta($reason) : ['bg-secondary', 'Cerrado'];
                return ['key' => 'CLOSED', 'label' => $meta[1], 'class' => $meta[0], 'is_failure' => false];

            case 'failed_execution':
                $label = function_exists('journal_reason_label') ? journal_reason_label($reason) : 'Error';
                return ['key' => 'FAILED', 'label' => $label, 'class' => 'bg-dark', 'is_failure' => true];

            case 'cancelled':
                return ['key' => 'CANCELLED', 'label' => 'Cancelada', 'class' => 'bg-warning text-dark', 'is_failure' => true];
        }
        return ['key' => 'OTHER', 'label' => ucfirst($status ?: '—'), 'class' => 'bg-secondary', 'is_failure' => false];
    }
}

if (!function_exists('correction_health')) {
    /**
     * Indicador at-a-glance de la corrección (para el ícono del dashboard).
     * @return array ['class','icon','title']
     */
    function correction_health($corr)
    {
        if (!$corr) {
            return ['class' => 'text-muted', 'icon' => '—', 'title' => 'Sin reporte de corrección'];
        }
        if (isset($corr->status) && $corr->status === 'ERROR') {
            $stage = isset($corr->error_stage) ? $corr->error_stage : 'ERROR';
            return ['class' => 'text-danger', 'icon' => '⚠', 'title' => 'Corrección falló: ' . $stage];
        }
        if (isset($corr->candles_aligned) && $corr->candles_aligned !== null && !$corr->candles_aligned) {
            $gap = (int)(isset($corr->bar_gap_sec) ? $corr->bar_gap_sec : 0);
            return ['class' => 'text-warning', 'icon' => '⚠', 'title' => 'Corrección OK pero vela desfasada (' . $gap . 's)'];
        }
        return ['class' => 'text-success', 'icon' => '✓', 'title' => 'Corrección OK · vela alineada'];
    }
}

if (!function_exists('build_trade_view')) {
    /**
     * Arma el view-model normalizado. Prefiere snapshot/correction (tablas nuevas);
     * cae a los blobs (mt_corrected_data / mt_execution_data) para trades históricos.
     *
     * @param object      $signal  fila user_telegram_signals (+ ts.op_type, at.display_decimals, blobs)
     * @param object|null $snap    fila ea_trade_snapshots
     * @param object|null $corr    fila ea_price_corrections
     * @return array
     */
    function build_trade_view($signal, $snap = null, $corr = null)
    {
        $d  = (int)(tv_has($signal, 'display_decimals') ? $signal->display_decimals : 5);
        $op = strtoupper($signal->op_type ?? ($snap && isset($snap->dir) ? $snap->dir : ''));

        // ---- Precios: señal cruda -> corregido -> real (snapshot, fallback a blobs) ----
        $mt_corr = !empty($signal->mt_corrected_data) ? json_decode($signal->mt_corrected_data, true) : null;
        $mt_raw  = !empty($signal->mt_execution_data) ? json_decode($signal->mt_execution_data, true) : null;
        $factor  = tv_has($snap, 'corr_factor') ? (float)$snap->corr_factor : 1.0;

        $c_entry = tv_has($snap, 'entry') ? (float)$snap->entry : (isset($mt_corr['entry']) ? (float)$mt_corr['entry'] : 0);
        $c_sl    = tv_has($snap, 'sl')    ? (float)$snap->sl    : (isset($mt_corr['stoploss'][0]) ? (float)$mt_corr['stoploss'][0] : 0);
        $c_tps = []; $r_tps = [];
        for ($i = 1; $i <= 5; $i++) {
            $c_tps[$i] = tv_has($snap, 'tp'.$i) ? (float)$snap->{'tp'.$i}
                       : (isset($mt_corr['tps'][$i-1]) ? (float)$mt_corr['tps'][$i-1] : 0);
        }
        $r_entry = tv_has($snap, 'entry_raw') ? (float)$snap->entry_raw : (isset($mt_raw['entry']) ? (float)$mt_raw['entry'] : ($c_entry * $factor));
        $r_sl    = tv_has($snap, 'sl_raw')    ? (float)$snap->sl_raw    : (isset($mt_raw['stoploss'][0]) ? (float)$mt_raw['stoploss'][0] : ($c_sl * $factor));
        for ($i = 1; $i <= 5; $i++) {
            $r_tps[$i] = isset($mt_raw['tps'][$i-1]) ? (float)$mt_raw['tps'][$i-1] : ($c_tps[$i] * $factor);
        }
        $real_entry = ($signal->real_entry_price ?? 0) ?: (tv_has($snap, 'real_entry') ? (float)$snap->real_entry : 0);
        $real_sl    = ($signal->real_stop_loss ?? 0) ?: 0;

        $reached = max(
            (int)($signal->current_level ?? 0),
            (int)($signal->exit_level ?? 0),
            tv_has($snap, 'max_level') ? (int)$snap->max_level : 0
        );
        $is_be = ($real_sl > 0 && $real_entry > 0 && abs($real_sl - $real_entry) < pow(10, -$d));

        // ---- Decisión de order type (solo con snapshot) ----
        $decision = ['present' => false];
        if (tv_has($snap, 'order_type')) {
            $side  = $snap->side;
            $kcoef = ($side === 'ADVERSO') ? $snap->cfg_k_limit_ratio : $snap->cfg_k_stop_ratio;
            $decision = [
                'present'      => true,
                'order_type'   => $snap->order_type,
                'side'         => $side,
                'price_signal' => $snap->price_signal,
                'signal_time'  => tv_has($snap, 'opened_at') ? $snap->opened_at : null, // cuándo el EA leyó el mercado
                'entry'        => $snap->entry,
                'dist_entry'   => $snap->dist_entry,
                'k_band'       => $snap->k_band,
                't1'           => $snap->t1,
                'kcoef'        => $kcoef,
            ];
        }

        // ---- Gates (solo con snapshot) ----
        $gates = ['present' => false];
        if ($snap) {
            $gates = [
                'present'        => true,
                'risk_percent'   => $snap->cfg_risk_percent ?? null,
                'r_dist'         => $snap->r_dist ?? null,
                't1'             => $snap->t1 ?? null,
                'spread_real'    => $snap->spread_real ?? null,
                'spread_tol'     => $snap->spread_tol ?? null,
                'slip_real'      => $snap->slip_real ?? null,
                'slip_tol'       => $snap->slip_tol ?? null,
                'enable_spread'  => !empty($snap->cfg_enable_spread),
                'enable_slip'    => !empty($snap->cfg_enable_slip),
                'stops_min'      => $snap->stops_min ?? null,
                'sl_dist'        => $snap->sl_dist ?? null,
                'be_level'       => $snap->cfg_be_level ?? null,
                'real_volume'    => $snap->real_volume ?? null,
                'tp_pcts'        => [
                    $snap->cfg_tp1_pct ?? null, $snap->cfg_tp2_pct ?? null, $snap->cfg_tp3_pct ?? null,
                    $snap->cfg_tp4_pct ?? null, $snap->cfg_tp5_pct ?? null,
                ],
            ];
        }

        // ---- Corrección ----
        $correction = ['present' => false];
        if ($corr) {
            $bad = ($corr->status === 'ERROR') || ($corr->candles_aligned !== null && !$corr->candles_aligned);
            $correction = [
                'present'           => true,
                'status'            => $corr->status,
                'error_stage'       => $corr->error_stage ?? null,
                'error_message'     => $corr->error_message ?? null,
                'bad'               => $bad,
                'fut_price'         => $corr->fut_price ?? null,
                'fut_candle_time'   => $corr->fut_candle_time ?? null,
                'mt5_price'         => $corr->mt5_price ?? null,
                'mt5_bar_time'      => $corr->mt5_bar_time ?? null,
                'mt5_bar_index'     => $corr->mt5_bar_index ?? null,
                'signal_mt5_price'  => $corr->signal_mt5_price ?? null,
                'candles_aligned'   => $corr->candles_aligned ?? null,
                'bar_gap_sec'       => $corr->bar_gap_sec ?? null,
                'corr_factor'       => $corr->corr_factor ?? null,
                'deviation_pct'     => $corr->deviation_pct ?? null,
                'timestamp_age_sec' => $corr->timestamp_age_sec ?? null,
            ];
        }

        return [
            'decimals' => $d,
            'meta' => [
                'id'           => (int)$signal->id,
                'symbol'       => $signal->ticker_symbol ?? '',
                'op'           => $op,
                'dir_class'    => ($op === 'LONG') ? 'bg-success' : 'bg-danger',
                'order_type'   => $signal->order_type ?? '',
                'real_volume'  => $signal->real_volume ?? null,
                'pnl'          => (float)($signal->gross_pnl ?? 0),
                'last_price'   => $signal->last_price ?? null,
                'real_entry'   => $real_entry,
                'created_at'   => $signal->created_at ?? null,
                'updated_at'   => $signal->updated_at ?? null,
                'status'       => $signal->status ?? '',
                'close_reason' => $signal->close_reason ?? '',
            ],
            'phase'      => signal_phase($signal),
            'health'     => correction_health($corr),
            'prices' => [
                'factor'  => $factor,
                'raw'     => ['entry' => $r_entry, 'sl' => $r_sl, 'tp' => $r_tps],
                'corr'    => ['entry' => $c_entry, 'sl' => $c_sl, 'tp' => $c_tps],
                'real'    => ['entry' => $real_entry, 'sl' => $real_sl],
                'reached' => $reached,
                'is_be'   => $is_be,
            ],
            'decision'   => $decision,
            'gates'      => $gates,
            'correction' => $correction,
        ];
    }
}
