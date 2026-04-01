<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Module Access Helper
 * Centralizes all user module permission checks.
 */

define('MODULE_BINGX', 'bingx');
define('MODULE_METATRADER', 'metatrader');
define('MODULE_ATVIP', 'atvip');

define('ALL_MODULES', [MODULE_BINGX, MODULE_METATRADER, MODULE_ATVIP]);

/**
 * Check if current user has access to a specific module
 */
function has_module($module)
{
    $ci = &get_instance();
    return (bool) $ci->session->userdata('module_' . $module);
}

/**
 * Get array of enabled module keys for current user
 */
function user_modules()
{
    $modules = [];
    foreach (ALL_MODULES as $mod) {
        if (has_module($mod)) {
            $modules[] = $mod;
        }
    }
    return $modules;
}

/**
 * Count how many modules the user has enabled
 */
function user_module_count()
{
    return count(user_modules());
}

/**
 * Check if user has only one specific module (and nothing else)
 */
function has_only_module($module)
{
    $modules = user_modules();
    return count($modules) === 1 && $modules[0] === $module;
}

/**
 * Check if current user is admin
 */
function is_admin()
{
    $ci = &get_instance();
    return $ci->session->userdata('role') === 'admin';
}

/**
 * Get source values for DB queries based on enabled modules
 * Maps modules to trades.source values
 */
function get_allowed_sources()
{
    $map = [
        MODULE_BINGX      => 'bingx',
        MODULE_METATRADER  => 'metatrader_tv',
        MODULE_ATVIP       => 'atvip',
    ];

    $sources = [];
    foreach (user_modules() as $mod) {
        if (isset($map[$mod])) {
            $sources[] = $map[$mod];
        }
    }
    return $sources;
}

/**
 * Get human-readable label for a module
 */
function module_label($module)
{
    $labels = [
        MODULE_BINGX      => 'BingX',
        MODULE_METATRADER  => 'MetaTrader TV',
        MODULE_ATVIP       => 'AT VIP Trading',
    ];
    return $labels[$module] ?? ucfirst($module);
}

/**
 * Get badge CSS class for a module
 */
function module_badge_class($module)
{
    $classes = [
        MODULE_BINGX      => 'bg-warning text-dark',
        MODULE_METATRADER  => 'bg-info text-dark',
        MODULE_ATVIP       => 'bg-success',
    ];
    return $classes[$module] ?? 'bg-secondary';
}

/**
 * Get signal status display info (badge class, label, and failure flag)
 * Shared by dashboard_content, trading_detail, and any signal views
 *
 * @param object $signal Signal object with status, close_reason, current_level
 * @return array ['class' => string, 'text' => string, 'is_failure' => bool]
 */
function get_signal_status_display($signal)
{
    $result = ['class' => 'bg-secondary', 'text' => ucfirst($signal->status), 'is_failure' => false];

    switch ($signal->status) {
        case 'pending':
        case 'claimed':
            $result['class'] = 'bg-warning text-dark';
            $result['text'] = 'Pending Order';
            break;

        case 'open':
            if ($signal->current_level >= 1) {
                $result['class'] = 'bg-success';
                $result['text'] = 'TP' . $signal->current_level . ' Reached';
            } else {
                $result['class'] = 'bg-primary';
                $result['text'] = 'Position Open';
            }
            break;

        case 'closed':
            $result = array_merge($result, _resolve_close_reason_display($signal->close_reason));
            break;

        case 'failed_execution':
            $result['is_failure'] = true;
            $result['class'] = 'bg-dark';
            $result['text'] = _get_failure_label($signal->close_reason);
            break;

        case 'cancelled':
            $result['is_failure'] = true;
            $result['class'] = 'bg-warning text-dark';
            $result['text'] = 'Order Cancelled';
            break;
    }

    return $result;
}

/**
 * @internal Resolve display for a legitimately closed signal by close_reason
 */
function _resolve_close_reason_display($close_reason)
{
    if (!$close_reason) {
        return ['class' => 'bg-secondary', 'text' => 'Closed', 'is_failure' => false];
    }

    $map = [
        'CLOSED_COMPLETE'    => ['bg-success', 'TP Complete'],
        'CLOSED_STOPLOSS'    => ['bg-danger', 'Stop Loss'],
        'CLOSED_CODE_STOP'   => ['bg-danger', 'Stop Loss'],
        'CLOSED_SAFETY_STOP' => ['bg-danger', 'Stop Loss'],
        'CLOSED_EXTERNAL'    => ['bg-warning text-dark', 'Manual Close'],
        'CLOSED_MANUAL'      => ['bg-warning text-dark', 'Manual Close'],
    ];

    if (isset($map[$close_reason])) {
        return ['class' => $map[$close_reason][0], 'text' => $map[$close_reason][1], 'is_failure' => false];
    }

    return ['class' => 'bg-secondary', 'text' => 'Closed', 'is_failure' => false];
}

/**
 * @internal Get a user-friendly label for failure close_reasons
 */
function _get_failure_label($close_reason)
{
    $labels = [
        'INVALID_TPS'            => 'Invalid TPs',
        'INVALID_STOPLOSS'       => 'Invalid SL',
        'PRICE_CORRECTION_ERROR' => 'Price Error',
        'SPREAD_TOO_HIGH'        => 'Spread Too High',
        'VOLUME_ERROR'           => 'Volume Error',
        'EXECUTION_FAILED'       => 'Execution Failed',
    ];
    return $labels[$close_reason] ?? 'Error';
}

// ==========================================
// Signal display helpers (shared across views)
// ==========================================

/**
 * Render signal level badge HTML
 * @param int|null $level current_level value (-2, -1, 0, 1-5)
 * @return string HTML badge
 */
function signal_level_badge($level)
{
    $level_text = '-';
    $level_class = 'bg-light text-muted';

    if ($level == -2 || $level === null) {
        // No progress yet
    } elseif ($level == 0) {
        $level_text = 'INIT';
        $level_class = 'bg-secondary';
    } elseif ($level >= 1 && $level <= 5) {
        $level_text = 'TP' . $level;
        $level_class = 'bg-success';
    } elseif ($level == -1) {
        $level_text = 'SL HIT';
        $level_class = 'bg-danger';
    }

    return '<span class="badge ' . $level_class . '">' . $level_text . '</span>';
}

/**
 * Render op_type (LONG/SHORT) badge HTML
 * @param string|null $op_type 'LONG', 'SHORT', or null
 * @return string HTML badge
 */
function signal_op_type_badge($op_type)
{
    if (empty($op_type)) {
        return '<span class="text-muted">-</span>';
    }

    $op_type = strtoupper($op_type);

    if ($op_type === 'LONG') {
        return '<span class="badge bg-success"><i class="fas fa-arrow-up me-1"></i>LONG</span>';
    } elseif ($op_type === 'SHORT') {
        return '<span class="badge bg-danger"><i class="fas fa-arrow-down me-1"></i>SHORT</span>';
    }

    return '<span class="badge bg-secondary"><i class="fas fa-question me-1"></i>' . $op_type . '</span>';
}

/**
 * Format price with signal's display_decimals
 * @param float|null $value Price value
 * @param object|int $signal_or_decimals Signal object (with display_decimals) or integer decimals
 * @return string Formatted price or 'N/A'
 */
function signal_price($value, $signal_or_decimals = 5)
{
    if ($value === null || $value === '' || $value === false) {
        return '<span class="text-muted">N/A</span>';
    }

    $decimals = is_object($signal_or_decimals)
        ? ($signal_or_decimals->display_decimals ?? 5)
        : (int) $signal_or_decimals;

    return number_format((float) $value, $decimals);
}

/**
 * Format PNL with color and icon
 * @param float $pnl PNL value
 * @return string HTML formatted PNL
 */
function signal_pnl($pnl)
{
    if (!$pnl || $pnl == 0) {
        return '<span class="text-muted">$0.00</span>';
    }

    $class = $pnl > 0 ? 'text-success' : 'text-danger';
    $icon = $pnl > 0 ? 'fa-arrow-up' : 'fa-arrow-down';

    return '<span class="' . $class . '"><i class="fas ' . $icon . ' me-1"></i>$' . number_format(abs($pnl), 2) . '</span>';
}

/**
 * Format elapsed time since a timestamp
 * @param string $created_at datetime string
 * @return string e.g. "5m", "2h 30m", "1d 5h"
 */
function signal_elapsed($created_at)
{
    $elapsed = time() - strtotime($created_at);
    if ($elapsed < 3600) {
        return floor($elapsed / 60) . 'm';
    } elseif ($elapsed < 86400) {
        return floor($elapsed / 3600) . 'h ' . floor(($elapsed % 3600) / 60) . 'm';
    }
    return floor($elapsed / 86400) . 'd ' . floor(($elapsed % 86400) / 3600) . 'h';
}
