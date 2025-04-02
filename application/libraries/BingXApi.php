<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class BingxApi {
    
    private $CI;
    
    // BingX API URLs
    private $spot_api_url = 'https://open-api.bingx.com';
    private $futures_api_url = 'https://open-api.bingx.com';
    
    // Para almacenar el último error
    private $last_error = null;
    
    public function __construct() {
        $this->CI =& get_instance();
    }
    
    /**
     * Obtener el último error de la API
     * 
     * @return string|null El último mensaje de error o null si no hay error
     */
    public function get_last_error() {
        return $this->last_error;
    }
    
    /**
     * Format symbol for BingX API based on operation type
     * 
     * @param string $symbol Raw symbol (e.g. BTCUSDT)
     * @param string $operation Type of operation: 'price', 'trade', 'futures_price', 'futures_trade'
     * @return string Formatted symbol
     */
    private function _format_symbol($symbol, $operation = 'price') {
        // Remove any existing hyphens first
        $symbol = str_replace('-', '', $symbol);
        
        switch ($operation) {
            case 'price':
                // For spot price endpoint (uses BTC-USDT format)
                if (substr($symbol, -4) === 'USDT') {
                    return substr($symbol, 0, -4) . '-USDT';
                } elseif (substr($symbol, -4) === 'USDC') {
                    return substr($symbol, 0, -4) . '-USDC';
                }
                return $symbol;
                
            case 'futures_price':
                // For futures price endpoint (requires ending with -USDT or -USDC)
                if (substr($symbol, -4) === 'USDT') {
                    return substr($symbol, 0, -4) . '-USDT';
                } elseif (substr($symbol, -4) === 'USDC') {
                    return substr($symbol, 0, -4) . '-USDC';
                }
                // Default to -USDT if no suffix
                return $symbol . '-USDT';
                
            case 'trade':
                // For spot trading (uses BTCUSDT format)
                return $symbol;
                
            case 'futures_trade':
                // For futures trading (uses BTCUSDT format)
                return $symbol;
                
            default:
                // Default no formatting
                return $symbol;
        }
    }
    
    /**
     * Generate signature for BingX API
     * 
     * @param array $params Request parameters
     * @param string $api_secret API Secret
     * @return string HMAC SHA256 signature
     */
    private function _generate_signature($params, $api_secret) {
        // Sort parameters alphabetically
        ksort($params);
        
        // Convert parameters to query string
        $query_string = http_build_query($params);
        
        // Replace special characters in query string
        $query_string = str_replace(['%40', '%3A', '%5B', '%5D', '%2C'], ['@', ':', '[', ']', ','], $query_string);
        
        // Generate HMAC SHA256 signature
        $signature = hash_hmac('sha256', $query_string, $api_secret);
        
        return $signature;
    }
    
    /**
     * Make API request to BingX
     * 
     * @param object $api_key API key object with api_key and api_secret
     * @param string $endpoint API endpoint
     * @param array $params Request parameters
     * @param string $method HTTP method (GET, POST, DELETE)
     * @param bool $is_futures Whether this is a futures API call
     * @return object|false Response data or false on failure
     */
    private function _make_request($api_key, $endpoint, $params = [], $method = 'GET', $is_futures = false) {
        // Reset last error
        $this->last_error = null;
        
        // Determine base URL based on API type
        $base_url = $is_futures ? $this->futures_api_url : $this->spot_api_url;
        
        // Add timestamp to parameters (REQUIRED)
        $params['timestamp'] = round(microtime(true) * 1000);
        
        // Generate signature
        $signature = $this->_generate_signature($params, $api_key->api_secret);
        $params['signature'] = $signature;
        
        // Log symbol format for debugging
        if (isset($params['symbol'])) {
            $this->CI->Log_model->add_log([
                'user_id' => $this->CI->session->userdata('user_id'),
                'action' => 'api_debug',
                'description' => 'Symbol format: ' . json_encode([
                    'original' => $params['symbol'],
                    'endpoint' => $endpoint,
                    'is_futures' => $is_futures
                ])
            ]);
        }
        
        // Initialize cURL
        $ch = curl_init();
        
        // Set URL and other options
        $url = $base_url . $endpoint;
        
        if ($method == 'GET') {
            $url .= '?' . http_build_query($params);
            curl_setopt($ch, CURLOPT_URL, $url);
        } else {
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, $method == 'POST');
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        }
        
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        // BingX requiere X-BX-APIKEY en el encabezado HTTP
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/x-www-form-urlencoded',
            'User-Agent: BingX-Trading-Bot',
            'X-BX-APIKEY: ' . $api_key->api_key
        ]);
        
        // Para debug, guardar la URL completa
        $debug_url = $method == 'GET' ? $url : ($url . '?' . http_build_query($params));
        
        // Execute request
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        
        // Close cURL
        curl_close($ch);
        
        // Log request and response
        $log_data = [
            'endpoint' => $endpoint,
            'url' => $debug_url,
            'method' => $method,
            'headers' => ['X-BX-APIKEY: ' . substr($api_key->api_key, 0, 5) . '...'],
            'params' => json_encode($params),
            'response' => $response,
            'http_code' => $http_code,
            'curl_error' => $curl_error
        ];
        
        $this->CI->Log_model->add_log([
            'user_id' => $this->CI->session->userdata('user_id'),
            'action' => 'api_request',
            'description' => json_encode($log_data)
        ]);
        
        // Check for cURL errors
        if ($curl_error) {
            $this->last_error = "cURL Error: " . $curl_error;
            return false;
        }
        
        // Check HTTP status
        if ($http_code != 200) {
            $this->last_error = "HTTP Error: " . $http_code . " - " . $response;
            return false;
        }
        
        // Parse response
        $data = json_decode($response);
        
        // Check for API errors
        if (isset($data->code) && $data->code != 0) {
            $this->last_error = "API Error: " . $data->code . " - " . (isset($data->msg) ? $data->msg : 'Unknown error');
            return false;
        }
        
        return $data;
    }
    
    /**
     * Get spot price for a symbol
     * 
     * @param object $api_key API key object
     * @param string $symbol Trading pair (e.g. BTCUSDT)
     * @return object|false Price information or false on failure
     */
    public function get_spot_price($api_key, $symbol) {
        $endpoint = '/openApi/spot/v1/ticker/price';
        $params = [
            'symbol' => $this->_format_symbol($symbol, 'price')
        ];
        
        $response = $this->_make_request($api_key, $endpoint, $params, 'GET', false);
        
        if ($response && isset($response->data) && is_array($response->data) && !empty($response->data)) {
            // Extract from nested structure
            if (isset($response->data[0]->trades) && is_array($response->data[0]->trades) && !empty($response->data[0]->trades)) {
                $price = $response->data[0]->trades[0]->price;
                
                // Create a standardized response object for consistency with other functions
                $price_object = new stdClass();
                $price_object->price = $price;
                $price_object->symbol = $symbol;
                
                return $price_object;
            }
        }
        
        return false;
    }
    
    /**
     * Get futures price for a symbol
     * 
     * @param object $api_key API key object
     * @param string $symbol Trading pair (e.g. BTCUSDT)
     * @return object|false Price information or false on failure
     */
    public function get_futures_price($api_key, $symbol) {
        $endpoint = '/openApi/swap/v2/quote/price';
        $params = [
            'symbol' => $this->_format_symbol($symbol, 'futures_price')
        ];
        
        $response = $this->_make_request($api_key, $endpoint, $params, 'GET', true);
        
        if ($response && isset($response->data)) {
            // Add more detailed extraction for futures price data if needed
            if (isset($response->data->price)) {
                return $response->data;
            } elseif (isset($response->data[0]) && isset($response->data[0]->price)) {
                return $response->data[0];
            }
        }
        
        return false;
    }
    
    /**
     * Open spot position
     * 
     * @param object $api_key API key object
     * @param string $symbol Trading pair
     * @param string $side BUY or SELL
     * @param float $quantity Trade quantity
     * @return object|false Order information or false on failure
     */
    public function open_spot_position($api_key, $symbol, $side, $quantity) {
        $endpoint = '/openApi/spot/v1/trade/order';
        $params = [
            'symbol' => $this->_format_symbol($symbol, 'trade'),
            'side' => $side,
            'type' => 'MARKET',
            'quantity' => $quantity
        ];
        
        $response = $this->_make_request($api_key, $endpoint, $params, 'POST', false);
        
        if ($response && isset($response->data)) {
            // Update with current price
            $price_info = $this->get_spot_price($api_key, $symbol);
            if ($price_info) {
                $response->data->price = $price_info->price;
            }
            return $response->data;
        }
        
        return false;
    }
    
    /**
     * Close spot position
     * 
     * @param object $api_key API key object
     * @param string $symbol Trading pair
     * @param string $side Original position side (BUY or SELL)
     * @param float $quantity Trade quantity
     * @return object|false Order information or false on failure
     */
    public function close_spot_position($api_key, $symbol, $side, $quantity) {
        // To close a position, we need to do the opposite action
        $close_side = $side == 'BUY' ? 'SELL' : 'BUY';
        
        $endpoint = '/openApi/spot/v1/trade/order';
        $params = [
            'symbol' => $this->_format_symbol($symbol, 'trade'),
            'side' => $close_side,
            'type' => 'MARKET',
            'quantity' => $quantity
        ];
        
        $response = $this->_make_request($api_key, $endpoint, $params, 'POST', false);
        
        if ($response && isset($response->data)) {
            // Update with current price
            $price_info = $this->get_spot_price($api_key, $symbol);
            if ($price_info) {
                $response->data->price = $price_info->price;
            }
            return $response->data;
        }
        
        return false;
    }
    
    /**
     * Set futures leverage
     * 
     * @param object $api_key API key object
     * @param string $symbol Trading pair
     * @param int $leverage Leverage level
     * @return bool Success status
     */
    public function set_futures_leverage($api_key, $symbol, $leverage) {
        $endpoint = '/openApi/swap/v2/trade/leverage';
        $params = [
            'symbol' => $this->_format_symbol($symbol, 'futures_trade'),
            'leverage' => $leverage
        ];
        
        $response = $this->_make_request($api_key, $endpoint, $params, 'POST', true);
        
        return $response !== false;
    }
    
    /**
     * Open futures position
     * 
     * @param object $api_key API key object
     * @param string $symbol Trading pair
     * @param string $side BUY or SELL
     * @param float $quantity Trade quantity
     * @return object|false Order information or false on failure
     */
    public function open_futures_position($api_key, $symbol, $side, $quantity) {
        $endpoint = '/openApi/swap/v2/trade/order';
        $params = [
            'symbol' => $this->_format_symbol($symbol, 'futures_trade'),
            'side' => $side,
            'positionSide' => 'BOTH',
            'type' => 'MARKET',
            'quantity' => $quantity
        ];
        
        $response = $this->_make_request($api_key, $endpoint, $params, 'POST', true);
        
        if ($response && isset($response->data)) {
            // Update with current price
            $price_info = $this->get_futures_price($api_key, $symbol);
            if ($price_info) {
                $response->data->price = $price_info->price;
            }
            return $response->data;
        }
        
        return false;
    }
    
    /**
     * Close futures position
     * 
     * @param object $api_key API key object
     * @param string $symbol Trading pair
     * @param string $side Original position side (BUY or SELL)
     * @param float $quantity Trade quantity
     * @return object|false Order information or false on failure
     */
    public function close_futures_position($api_key, $symbol, $side, $quantity) {
        // To close a position, we need to do the opposite action
        $close_side = $side == 'BUY' ? 'SELL' : 'BUY';
        
        $endpoint = '/openApi/swap/v2/trade/order';
        $params = [
            'symbol' => $this->_format_symbol($symbol, 'futures_trade'),
            'side' => $close_side,
            'positionSide' => 'BOTH',
            'type' => 'MARKET',
            'quantity' => $quantity,
            'reduceOnly' => 'true'
        ];
        
        $response = $this->_make_request($api_key, $endpoint, $params, 'POST', true);
        
        if ($response && isset($response->data)) {
            // Update with current price
            $price_info = $this->get_futures_price($api_key, $symbol);
            if ($price_info) {
                $response->data->price = $price_info->price;
            }
            return $response->data;
        }
        
        return false;
    }
    
    /**
     * Get futures account information
     * 
     * @param object $api_key API key object
     * @return object|false Account information or false on failure
     */
    public function get_futures_account($api_key) {
        $endpoint = '/openApi/swap/v2/user/balance';
        
        $response = $this->_make_request($api_key, $endpoint, [], 'GET', true);
        
        if ($response && isset($response->data)) {
            return $response->data;
        }
        
        return false;
    }
    
    /**
     * Get spot account information
     * 
     * @param object $api_key API key object
     * @return object|false Account information or false on failure
     */
    public function get_spot_account($api_key) {
        $endpoint = '/openApi/spot/v1/account/balance';
        
        $response = $this->_make_request($api_key, $endpoint, [], 'GET', false);
        
        if ($response && isset($response->data)) {
            return $response->data;
        }
        
        return false;
    }
}