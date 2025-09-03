<?php
defined('BASEPATH') OR exit('No direct script access allowed');

$autoload['packages'] = array();
$autoload['libraries'] = array('database', 'session', 'form_validation', 'Mt_signal_processor');
$autoload['drivers'] = array();
$autoload['helper'] = array('url', 'form', 'security', 'date', 'logs');
$autoload['config'] = array();
$autoload['language'] = array();
$autoload['model'] = array('User_model', 'Trade_model', 'Strategy_model', 'Api_key_model', 'Log_model', 'Mt_signal_model'); 