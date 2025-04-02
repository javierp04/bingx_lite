<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Logs Helper
 * Functions for handling system logs formatting
 */

// ------------------------------------------------------------------------

/**
 * Get badge class for log action
 *
 * Returns the appropriate Bootstrap badge class based on log action type
 *
 * @param     string    $action    Log action type
 * @return    string
 */
if (!function_exists('get_badge_class'))
{
    function get_badge_class($action)
    {
        switch ($action) {
            case 'login':
            case 'add_user':
            case 'add_strategy':
            case 'add_api_key':
                return 'bg-success';
            case 'logout':
            case 'delete_user':
            case 'delete_strategy':
            case 'delete_api_key':
                return 'bg-danger';
            case 'open_trade':
                return 'bg-primary';
            case 'close_trade':
                return 'bg-warning text-dark';
            case 'webhook_error':
            case 'api_error':
                return 'bg-danger';
            case 'api_request':
            case 'api_debug':
            case 'signature_debug':
                return 'bg-info';
            case 'webhook_debug':
                return 'bg-secondary';
            default:
                return 'bg-secondary';
        }
    }
}

/**
 * Format JSON
 *
 * Formats JSON string for display
 *
 * @param     string    $json_string    JSON string to format
 * @return    string
 */
if (!function_exists('format_json'))
{
    function format_json($json_string)
    {
        try {
            $obj = json_decode($json_string);
            if (json_last_error() === JSON_ERROR_NONE) {
                return json_encode($obj, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            }
            return $json_string;
        } catch (Exception $e) {
            return $json_string;
        }
    }
}