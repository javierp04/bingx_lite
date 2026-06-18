<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Etiquetas legibles (ES) para los códigos del journal del EA.
 * Fuente ÚNICA reusada por overview / detail / trade_detail — antes estos mapas estaban
 * triplicados (e inconsistentes) en las vistas, mostrando códigos crudos en unos lados y
 * descripciones en otros. Solo presentación; no toca datos.
 */

if (!function_exists('journal_exit_label')) {
    /** exit_level (1..5 / -1 / -998 / -999 / 0) -> descripción. */
    function journal_exit_label($code)
    {
        $map = array(
            '1' => 'TP1', '2' => 'TP2', '3' => 'TP3', '4' => 'TP4', '5' => 'TP5',
            '-1' => 'Stop loss', '-998' => 'Señal inválida', '-999' => 'Error/gate/cancel',
            '0' => 'Sin TP',   // cerró en nivel 0 = no alcanzó ningún TP (el close_reason dice cómo)
        );
        // exit_level vacío/NULL = el trade todavía no cerró -> sigue en vivo (abierto).
        $k = (string) $code;
        return isset($map[$k]) ? $map[$k] : ($k === '' ? 'En vivo' : $k);
    }
}

if (!function_exists('journal_reason_meta')) {
    /** close_reason -> [css_class, descripción]. Default: gris + el código crudo. */
    function journal_reason_meta($reason)
    {
        $map = array(
            'CLOSED_COMPLETE'        => array('bg-success', 'Todos los TP'),
            'CLOSED_STOPLOSS'        => array('bg-danger', 'Stop Loss'),
            'CLOSED_BREAKEVEN'       => array('bg-secondary', 'Breakeven'),
            'CLOSED_CODE_STOP'       => array('bg-danger', 'Code Stop'),
            'CLOSED_SAFETY_STOP'     => array('bg-danger', 'Safety Stop'),
            'CLOSED_MANUAL'          => array('bg-warning text-dark', 'Manual'),
            'CLOSED_EXTERNAL'        => array('bg-warning text-dark', 'Externo'),
            'CLOSED_TIME'            => array('bg-info', 'Cierre por horario'),
            'ORDER_CANCELLED'        => array('bg-warning text-dark', 'Cancelada'),
            'PRICE_CORRECTION_ERROR' => array('bg-dark', 'Corrección falló'),
            'SPREAD_TOO_HIGH'        => array('bg-dark', 'Spread alto'),
            'VOLUME_ERROR'           => array('bg-dark', 'Error volumen'),
            'INVALID_TPS'            => array('bg-dark', 'TPs inválidos'),
            'INVALID_STOPLOSS'       => array('bg-dark', 'SL inválido'),
            'INVALID_OPTYPE'         => array('bg-dark', 'op_type inválido'),
            'INVALID_ENTRY'          => array('bg-dark', 'Entry inválido'),
            'SL_TOO_CLOSE'           => array('bg-dark', 'SL muy cerca'),
            'EXECUTION_FAILED'       => array('bg-dark', 'Broker rechazó'),
        );
        $k = (string) $reason;
        if (isset($map[$k])) return $map[$k];
        return array('bg-secondary', $k === '' ? '—' : $k);
    }
}

if (!function_exists('journal_reason_label')) {
    /** Solo la descripción del close_reason. */
    function journal_reason_label($reason)
    {
        $m = journal_reason_meta($reason);
        return $m[1];
    }
}

if (!function_exists('journal_order_label')) {
    /**
     * order_type -> descripción. Cubre las dos representaciones que llegan al journal:
     * la enum del EA (ORDER_TYPE_*) y la decisión de tipo de orden (MARKET/LIMIT/STOP/MARKET_FB).
     */
    function journal_order_label($code)
    {
        $map = array(
            'ORDER_TYPE_BUY'        => 'Market Buy',
            'ORDER_TYPE_SELL'       => 'Market Sell',
            'ORDER_TYPE_BUY_LIMIT'  => 'Buy Limit',
            'ORDER_TYPE_SELL_LIMIT' => 'Sell Limit',
            'ORDER_TYPE_BUY_STOP'   => 'Buy Stop',
            'ORDER_TYPE_SELL_STOP'  => 'Sell Stop',
            'MARKET'                => 'Mercado',
            'LIMIT'                 => 'Límite',
            'STOP'                  => 'Stop',
            'MARKET_FB'             => 'Mercado (fallback)',
        );
        $k = (string) $code;
        return isset($map[$k]) ? $map[$k] : ($k === '' ? '—' : $k);
    }
}

if (!function_exists('journal_class_hex')) {
    /** Clase bootstrap de badge -> color hex (para los datasets de Chart.js). */
    function journal_class_hex($class)
    {
        $map = array(
            'bg-success'           => '#28a745',
            'bg-danger'            => '#dc3545',
            'bg-warning text-dark' => '#ffc107',
            'bg-info'              => '#0dcaf0',
            'bg-dark'              => '#343a40',
            'bg-secondary'         => '#6c757d',
        );
        return isset($map[$class]) ? $map[$class] : '#6c757d';
    }
}
