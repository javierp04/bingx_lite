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
