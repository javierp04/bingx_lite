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

// My Trading routes (User trading dashboard - CORREGIDO)
$route['my_trading'] = 'My_trading/index/active';                          // CAMBIADO: active por defecto
$route['my_trading/add_ticker'] = 'My_trading/add_ticker';                  // ANTES de la genérica
$route['my_trading/update_mt_ticker'] = 'My_trading/update_mt_ticker';      // ANTES de la genérica
$route['my_trading/refresh_dashboard_ajax'] = 'My_trading/refresh_dashboard_ajax';  // AGREGAR ESTA LÍNEA
$route['my_trading/remove_ticker/(:any)'] = 'My_trading/remove_ticker/$1';
$route['my_trading/toggle_ticker/(:any)'] = 'My_trading/toggle_ticker/$1';
$route['my_trading/trading_detail/(:num)'] = 'My_trading/trading_detail/$1';  // NUEVO: Trading detail
$route['my_trading/signal_detail/(:num)'] = 'My_trading/signal_detail/$1';    // MANTENER: Redirige a trading_detail
$route['my_trading/(:any)'] = 'My_trading/index/$1';                       // GENÉRICA al final

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
$route['signals'] = 'signals';
$route['signals/retry_signal/(:num)'] = 'signals/retry_signal/$1';
$route['signals/delete_signal/(:num)'] = 'signals/delete_signal/$1';
$route['signals/get_stats'] = 'signals/get_stats';

// Webhook route
$route['webhook/tradingview'] = 'webhook/tradingview';

$route['metatrader/webhook'] = 'metatrader/webhook';
$route['metatrader/pending_signals'] = 'metatrader/get_pending_signals';
$route['metatrader/confirm_execution'] = 'metatrader/confirm_execution';

// Debug routes
$route['debug'] = 'debug';
$route['debug/test_mt_signal'] = 'debug/test_mt_signal';
$route['debug/test_bingx_signal'] = 'debug/test_bingx_signal';
$route['debug/test_spot_balance'] = 'debug/test_spot_balance';
$route['debug/test_futures_balance'] = 'debug/test_futures_balance';
$route['debug/test_spot_price'] = 'debug/test_spot_price';
$route['debug/test_futures_price'] = 'debug/test_futures_price';
$route['debug/telegram'] = 'debug/telegram';
$route['debug/telegram/simulate'] = 'debug/simulate_telegram_webhook';  // NUEVO: Full webhook simulator
$route['debug/telegram/generate'] = 'debug/generate_telegram_signal';
$route['debug/telegram/test'] = 'debug/test_telegram_signal';

// AI Trade Reader route (Telegram webhook)
$route['tradereader/run'] = 'tradereader/generateSignalFromTelegram';

// ==========================================
// API ROUTES FOR METATRADER EA - ORDEN CORRECTO
// ==========================================

// POST reportes específicos (DEBEN IR PRIMERO)
$route['api/signals/(:num)/open'] = 'Api/report_open/$1';             
$route['api/signals/(:num)/progress'] = 'Api/report_progress/$1';     
$route['api/signals/(:num)/close'] = 'Api/report_close/$1';           

// GET precio de futuros
$route['api/fut_price/(:any)'] = 'Api/fut_price/$1';                 

// GET señales disponibles (GENÉRICA - VA AL FINAL)
$route['api/signals/(:num)/(:any)'] = 'Api/get_signals/$1/$2';