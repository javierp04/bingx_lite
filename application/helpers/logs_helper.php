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
            // User actions - GREEN
            case 'login':
            case 'add_user':
            case 'add_strategy':
            case 'add_api_key':
                return 'bg-success';
                
            // Delete actions - RED
            case 'logout':
            case 'delete_user':
            case 'delete_strategy':
            case 'delete_api_key':
                return 'bg-danger';
                
            // Trading actions - BLUE/PURPLE
            case 'open_trade':
                return 'bg-primary';
            case 'close_trade':
            case 'partial_close_trade':
                return 'bg-warning text-dark';
                
            // API & Request actions - LIGHT BLUE
            case 'api_request':
            case 'api_debug':
            case 'api_response_debug':
            case 'signature_debug':
            case 'close_position_request':
                return 'bg-info';
                
            // Error actions - RED
            case 'webhook_error':
            case 'api_error':
            case 'refresh_error':
                return 'bg-danger';
                
            // BingX Webhook actions - GRAY
            case 'webhook_debug':
                return 'bg-secondary';
                
            // MetaTrader actions
            case 'mt_webhook_debug':
            case 'mt_debug_test':
                return 'bg-info';
            case 'mt_webhook_error':
            case 'mt_signal_failed':
                return 'bg-danger';
            case 'mt_signal_queued':
                return 'bg-warning text-dark';
            case 'mt_signal_processed':
            case 'mt_signal_retry':
            case 'mt_signal_delete':
                return 'bg-success';
                
            // Edit actions - PURPLE
            case 'edit_user':
            case 'edit_strategy':
            case 'edit_api_key':
                return 'bg-primary';
                
            // Default - GRAY
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