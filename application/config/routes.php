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

// Signals routes (MetaTrader only)
$route['signals'] = 'signals';
$route['signals/retry_signal/(:num)'] = 'signals/retry_signal/$1';
$route['signals/delete_signal/(:num)'] = 'signals/delete_signal/$1';
$route['signals/get_stats'] = 'signals/get_stats';

// Webhook route
$route['webhook/tradingview'] = 'webhook/tradingview';

// MetaTrader routes
$route['metatrader/webhook'] = 'metatrader/webhook';
$route['api/mt/pending_signals'] = 'metatrader/get_pending_signals';
$route['api/mt/confirm_execution'] = 'metatrader/confirm_execution';

// Debug routes
$route['debug'] = 'debug';
$route['debug/test_mt_signal'] = 'debug/test_mt_signal';
$route['debug/test_bingx_signal'] = 'debug/test_bingx_signal';
$route['debug/test_spot_balance'] = 'debug/test_spot_balance';
$route['debug/test_futures_balance'] = 'debug/test_futures_balance';
$route['debug/test_spot_price'] = 'debug/test_spot_price';
$route['debug/test_futures_price'] = 'debug/test_futures_price';