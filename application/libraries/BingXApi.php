// application/libraries/BingxApi.php
<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class BingxApi {
    
    private $CI;
    
    public function __construct() {
        $this->CI =& get_instance();
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
        // Determine base URL based on environment and API type
        if ($api_key->environment == 'production') {
            $base_url = $is_futures ? BINGX_FUTURES_API_URL_PRODUCTION : BINGX_SPOT_API_URL_PRODUCTION;
        } else {
            $base_url = $is_futures ? BINGX_FUTURES_API_URL_SANDBOX : BINGX_SPOT_API_URL_SANDBOX;
        }
        
        // Add timestamp and API key to parameters
        $params['timestamp'] = round(microtime(true) * 1000);
        $params['apiKey'] = $api_key->api_key;
        
        // Generate signature
        $signature = $this->_generate_signature($params, $api_key->api_secret);
        $params['signature'] = $signature;
        
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
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/x-www-form-urlencoded',
            'User-Agent: BingX-Trading-Bot'
        ]);
        
        // Execute request
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        // Close cURL
        curl_close($ch);
        
        // Log request and response
        $log_data = [
            'endpoint' => $endpoint,
            'params' => json_encode($params),
            'response' => $response,
            'http_code' => $http_code
        ];
        $this->CI->Log_model->add_log([
            'user_id' => $this->CI->session->userdata('user_id'),
            'action' => 'api_request',
            'description' => json_encode($log_data)
        ]);
        
        // Check if request was successful
        if ($http_code != 200) {
            return false;
        }
        
        // Parse response
        $data = json_decode($response);
        
        // Check for API errors
        if (isset($data->code) && $data->code != 0) {
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
            'symbol' => $symbol
        ];
        
        $response = $this->_make_request($api_key, $endpoint, $params, 'GET', false);
        
        if ($response && isset($response->data)) {
            return $response->data;
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
            'symbol' => $symbol
        ];
        
        $response = $this->_make_request($api_key, $endpoint, $params, 'GET', true);
        
        if ($response && isset($response->data)) {
            return $response->data;
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
            'symbol' => $symbol,
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
            'symbol' => $symbol,
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
            'symbol' => $symbol,
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
            'symbol' => $symbol,
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
            'symbol' => $symbol,
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