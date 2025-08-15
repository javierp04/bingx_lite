<?php
defined('BASEPATH') OR exit('No direct script access allowed');

// Default route
$route['default_controller'] = 'auth';
$route['404_override'] = '';
$route['translate_uri_dashes'] = FALSE;

// Auth routes
$route['login'] = 'auth/login';
$route['logout'] = 'auth/logout';

// Dashboard routes
$route['dashboard'] = 'dashboard';

// User management routes
$route['users'] = 'users';
$route['users/add'] = 'users/add';
$route['users/edit/(:num)'] = 'users/edit/$1';
$route['users/delete/(:num)'] = 'users/delete/$1';

// API Keys routes
$route['apikeys'] = 'ApiKeys';
$route['apikeys/add'] = 'ApiKeys/add';
$route['apikeys/edit/(:num)'] = 'ApiKeys/edit/$1';
$route['apikeys/delete/(:num)'] = 'ApiKeys/delete/$1';

// Strategies routes
$route['strategies'] = 'strategies';
$route['strategies/add'] = 'strategies/add';
$route['strategies/edit/(:num)'] = 'strategies/edit/$1';
$route['strategies/delete/(:num)'] = 'strategies/delete/$1';

// Trades routes
$route['trades'] = 'trades';
$route['trades/close/(:num)'] = 'trades/close/$1';
$route['trades/detail/(:num)'] = 'trades/detail/$1';

// System Logs routes
$route['systemlogs'] = 'SystemLogs';
$route['systemlogs/view/(:num)'] = 'SystemLogs/view/$1';
$route['systemlogs/search'] = 'SystemLogs/search';
$route['systemlogs/cleanup'] = 'SystemLogs/cleanup';

// Webhook route
$route['webhook/tradingview'] = 'webhook/tradingview';

// MetaTrader routes
$route['metatrader/webhook'] = 'metatrader/webhook';
$route['api/mt/pending_signals'] = 'metatrader/get_pending_signals';
$route['api/mt/mark_processed'] = 'metatrader/mark_signal_processed';

// MetaTrader Dashboard routes
$route['mt_dashboard'] = 'mt_dashboard';
$route['mt_dashboard/signals'] = 'mt_dashboard/signals';
$route['mt_dashboard/logs'] = 'mt_dashboard/logs';
$route['mt_dashboard/debug'] = 'mt_dashboard/debug';
$route['mt_dashboard/test_signal'] = 'mt_dashboard/test_signal';
$route['mt_dashboard/retry_signal/(:num)'] = 'mt_dashboard/retry_signal/$1';
$route['mt_dashboard/delete_signal/(:num)'] = 'mt_dashboard/delete_signal/$1';