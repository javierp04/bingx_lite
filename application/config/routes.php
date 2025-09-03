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
$route['strategies/view_image/(:num)'] = 'strategies/view_image/$1';

// Trades routes
$route['trades'] = 'trades';
$route['trades/close/(:num)'] = 'trades/close/$1';
$route['trades/detail/(:num)'] = 'trades/detail/$1';

// Available Tickers routes (Admin only)
$route['available_tickers'] = 'Available_tickers';
$route['available_tickers/add'] = 'Available_tickers/add';
$route['available_tickers/edit/(:any)'] = 'Available_tickers/edit/$1';
$route['available_tickers/toggle/(:any)'] = 'Available_tickers/toggle/$1';
$route['available_tickers/delete/(:any)'] = 'Available_tickers/delete/$1';

// My Tickers routes (User ticker selection)
$route['my_tickers'] = 'My_tickers';
$route['my_tickers/add_ticker'] = 'My_tickers/add_ticker';
$route['my_tickers/remove_ticker/(:any)'] = 'My_tickers/remove_ticker/$1';
$route['my_tickers/toggle_ticker/(:any)'] = 'My_tickers/toggle_ticker/$1';
$route['my_tickers/update_mt_ticker'] = 'My_tickers/update_mt_ticker';

// Telegram Signals routes
$route['telegram_signals'] = 'Telegram_signals';
$route['telegram_signals/view/(:num)'] = 'Telegram_signals/view/$1';
$route['telegram_signals/mark_processed/(:num)'] = 'Telegram_signals/mark_processed/$1';
$route['telegram_signals/delete/(:num)'] = 'Telegram_signals/delete/$1';
$route['telegram_signals/cleanup'] = 'Telegram_signals/cleanup';
$route['telegram_signals/view_image/(:num)'] = 'Telegram_signals/view_image/$1';

// Telegram Signals API routes (for MetaTrader EA)
$route['api/telegram/signals/(:num)'] = 'Telegram_signals/api_get_signals/$1';
$route['api/telegram/processed/(:num)'] = 'Telegram_signals/api_mark_processed/$1';

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

// AI Trade Reader route
$route['/tradereader/run'] = 'tradereader/run';