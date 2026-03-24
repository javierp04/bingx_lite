<?php
defined('BASEPATH') OR exit('No direct script access allowed');

// Default route
$route['default_controller'] = 'Auth';
$route['404_override'] = '';
$route['translate_uri_dashes'] = FALSE;

// Auth routes
$route['login'] = 'Auth/login';
$route['logout'] = 'Auth/logout';

// Dashboard routes
$route['dashboard'] = 'Dashboard';

// User management routes
$route['users'] = 'Users';
$route['users/add'] = 'Users/add';
$route['users/edit/(:num)'] = 'Users/edit/$1';
$route['users/delete/(:num)'] = 'Users/delete/$1';

// API Keys routes
$route['apikeys'] = 'ApiKeys';
$route['apikeys/add'] = 'ApiKeys/add';
$route['apikeys/edit/(:num)'] = 'ApiKeys/edit/$1';
$route['apikeys/delete/(:num)'] = 'ApiKeys/delete/$1';

// Strategies routes
$route['strategies'] = 'Strategies';
$route['strategies/add'] = 'Strategies/add';
$route['strategies/edit/(:num)'] = 'Strategies/edit/$1';
$route['strategies/delete/(:num)'] = 'Strategies/delete/$1';
$route['strategies/view_image/(:num)'] = 'Strategies/view_image/$1';

// Trades routes
$route['trades'] = 'Trades';
$route['trades/close/(:num)'] = 'Trades/close/$1';
$route['trades/detail/(:num)'] = 'Trades/detail/$1';

// Available Tickers routes (Admin only)
$route['available_tickers'] = 'Available_tickers';
$route['available_tickers/add'] = 'Available_tickers/add';
$route['available_tickers/edit/(:any)'] = 'Available_tickers/edit/$1';
$route['available_tickers/toggle/(:any)'] = 'Available_tickers/toggle/$1';
$route['available_tickers/delete/(:any)'] = 'Available_tickers/delete/$1';

// My Trading routes (User trading dashboard - CORREGIDO)
$route['my_trading'] = 'My_trading/index/active';
$route['my_trading/add_ticker'] = 'My_trading/add_ticker';
$route['my_trading/update_mt_ticker'] = 'My_trading/update_mt_ticker';
$route['my_trading/refresh_dashboard_ajax'] = 'My_trading/refresh_dashboard_ajax';
$route['my_trading/remove_ticker/(:any)'] = 'My_trading/remove_ticker/$1';
$route['my_trading/toggle_ticker/(:any)'] = 'My_trading/toggle_ticker/$1';
$route['my_trading/trading_detail/(:num)'] = 'My_trading/trading_detail/$1';
$route['my_trading/signal_detail/(:num)'] = 'My_trading/signal_detail/$1';
$route['my_trading/(:any)'] = 'My_trading/index/$1';

// Telegram Signals routes (Admin only - MODIFICADO)
$route['telegram_signals'] = 'Telegram_signals';
$route['telegram_signals/view/(:num)'] = 'Telegram_signals/view/$1';
$route['telegram_signals/delete/(:num)'] = 'Telegram_signals/delete/$1';
$route['telegram_signals/cleanup'] = 'Telegram_signals/cleanup';
$route['telegram_signals/view_image/(:num)'] = 'Telegram_signals/view_image/$1';
$route['telegram_signals/view_cropped_image/(:num)'] = 'Telegram_signals/view_cropped_image/$1';

// System Logs routes
$route['systemlogs'] = 'SystemLogs';
$route['systemlogs/view/(:num)'] = 'SystemLogs/view/$1';
$route['systemlogs/search'] = 'SystemLogs/search';
$route['systemlogs/cleanup'] = 'SystemLogs/cleanup';

// Signals routes (MetaTrader only)
$route['signals'] = 'Signals';
$route['signals/retry_signal/(:num)'] = 'Signals/retry_signal/$1';
$route['signals/delete_signal/(:num)'] = 'Signals/delete_signal/$1';
$route['signals/get_stats'] = 'Signals/get_stats';

// Webhook route
$route['webhook/tradingview'] = 'Webhook/tradingview';

$route['metatrader/webhook'] = 'Metatrader/webhook';
$route['metatrader/pending_signals'] = 'Metatrader/get_pending_signals';
$route['metatrader/confirm_execution'] = 'Metatrader/confirm_execution';

// Debug routes
$route['debug'] = 'Debug';
$route['debug/test_mt_signal'] = 'Debug/test_mt_signal';
$route['debug/test_bingx_signal'] = 'Debug/test_bingx_signal';
$route['debug/test_spot_balance'] = 'Debug/test_spot_balance';
$route['debug/test_futures_balance'] = 'Debug/test_futures_balance';
$route['debug/test_spot_price'] = 'Debug/test_spot_price';
$route['debug/test_futures_price'] = 'Debug/test_futures_price';
$route['debug/telegram'] = 'Debug/telegram';
$route['debug/telegram/simulate'] = 'Debug/simulate_telegram_webhook';
$route['debug/telegram/generate'] = 'Debug/generate_telegram_signal';
$route['debug/telegram/test'] = 'Debug/test_telegram_signal';

// AI Trade Reader route (Telegram webhook)
$route['tradereader/run'] = 'TradeReader/generateSignalFromTelegram';

// ==========================================
// API ROUTES FOR METATRADER EA - ORDEN CORRECTO
// ==========================================

// EA Autónomo - Trades API (DEBEN IR PRIMERO)
$route['api/trades/open'] = 'Api/trade_open';
$route['api/trades/(:num)/update'] = 'Api/trade_update/$1';
$route['api/trades/(:num)/close'] = 'Api/trade_close/$1';

// ATVIP - POST reportes específicos
$route['api/signals/(:num)/open'] = 'Api/report_open/$1';
$route['api/signals/(:num)/progress'] = 'Api/report_progress/$1';
$route['api/signals/(:num)/close'] = 'Api/report_close/$1';

// GET precio de futuros
$route['api/fut_price/(:any)'] = 'Api/fut_price/$1';

// GET señales disponibles (GENÉRICA - VA AL FINAL)
$route['api/signals/(:num)/(:any)'] = 'Api/get_signals/$1/$2';