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
