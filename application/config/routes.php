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
$route['apikeys'] = 'apikeys';
$route['apikeys/add'] = 'apikeys/add';
$route['apikeys/edit/(:num)'] = 'apikeys/edit/$1';
$route['apikeys/delete/(:num)'] = 'apikeys/delete/$1';

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