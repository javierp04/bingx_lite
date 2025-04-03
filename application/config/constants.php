<?php
defined('BASEPATH') OR exit('No direct script access allowed');

// Custom constants for BingX API
define('BINGX_SPOT_API_URL_PRODUCTION', 'https://open-api.bingx.com');
define('BINGX_FUTURES_API_URL_PRODUCTION', 'https://open-api.bingx.com');
define('BINGX_SPOT_API_URL_SANDBOX', 'https://open-api.bingx.com'); // Same as production for spot
define('BINGX_FUTURES_API_URL_SANDBOX', 'https://open-api-vst.bingx.com'); // Sandbox URL for futures

// File upload paths
define('UPLOAD_PATH', './uploads/');