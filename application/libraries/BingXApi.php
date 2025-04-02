<?php
defined('BASEPATH') or exit('No direct script access allowed');

class BingxApi
{
    private $CI;

    // BingX API URLs
    private $spot_api_url = 'https://open-api.bingx.com';
    private $futures_api_url = 'https://open-api.bingx.com';

    // Para almacenar el último error
    private $last_error = null;

    public function __construct()
    {
        $this->CI = &get_instance();
    }

    /**
     * Obtener el último error de la API
     * 
     * @return string|null El último mensaje de error o null si no hay error
     */
    public function get_last_error()
    {
        return $this->last_error;
    }

    /**
     * Format symbol for BingX API based on operation type
     * 
     * @param string $symbol Raw symbol (e.g. BTCUSDT)
     * @return string Formatted symbol
     */
    public function format_symbol($symbol)
    {
        // Remove any hyphens first
        $symbol = str_replace('-', '', $symbol);

        // Add hyphen for standard pairs
        if (substr($symbol, -4) === 'USDT') {
            return substr($symbol, 0, -4) . '-USDT';
        } elseif (substr($symbol, -4) === 'USDC') {
            return substr($symbol, 0, -4) . '-USDC';
        } elseif (substr($symbol, -3) === 'BTC') {
            return substr($symbol, 0, -3) . '-BTC';
        } elseif (substr($symbol, -3) === 'ETH') {
            return substr($symbol, 0, -3) . '-ETH';
        }

        // For any other format, return as is
        return $symbol;
    }

    /**
     * Generate signature for BingX API - following their official example
     * 
     * @param string $parameters Query string of parameters
     * @param string $api_secret API Secret
     * @return string HMAC SHA256 signature
     */
    private function _generate_signature($parameters, $api_secret)
    {
        // Generate HMAC SHA256 signature and return lowercase hexadecimal
        $hash = hash_hmac("sha256", $parameters, $api_secret, true);
        $hashHex = bin2hex($hash);
        return strtolower($hashHex);
    }

    /**
     * Make API request to BingX - following their official example
     * 
     * @param object $api_key API key object with api_key and api_secret
     * @param string $endpoint API endpoint
     * @param array $params Request parameters
     * @param string $method HTTP method (GET, POST, DELETE)
     * @param bool $is_futures Whether this is a futures API call
     * @return object|false Response data or false on failure
     */
    private function _make_request($api_key, $endpoint, $params = [], $method = 'GET', $is_futures = false)
    {
        // Reset last error
        $this->last_error = null;

        // Determine base URL
        $base_url = $is_futures ? $this->futures_api_url : $this->spot_api_url;
        $host = parse_url($base_url, PHP_URL_HOST);
        $protocol = parse_url($base_url, PHP_URL_SCHEME);

        // Start with timestamp parameter
        $timestamp = round(microtime(true) * 1000);
        $parameters = "timestamp=" . $timestamp;

        // Add all other parameters
        foreach ($params as $key => $value) {
            $parameters .= "&$key=$value";
        }

        // Generate signature
        $signature = $this->_generate_signature($parameters, $api_key->api_secret);

        // Construct URL with parameters and signature
        $url = "{$protocol}://{$host}{$endpoint}?{$parameters}&signature={$signature}";

        // Debug log
        $this->CI->Log_model->add_log([
            'user_id' => $this->CI->session->userdata('user_id'),
            'action' => 'api_debug',
            'description' => json_encode([
                'endpoint' => $endpoint,
                'method' => $method,
                'parameters' => $parameters,
                'url' => $url
            ])
        ]);

        // Initialize cURL
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        
        if ($method == 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            // Empty post body as parameters are in the URL
            curl_setopt($ch, CURLOPT_POSTFIELDS, '');
        } else {
            curl_setopt($ch, CURLOPT_HTTPGET, true);
        }

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        // Set headers
        $headers = [
            'Content-Type: application/x-www-form-urlencoded',
            'User-Agent: BingX-Trading-Bot',
            'X-BX-APIKEY: ' . $api_key->api_key
        ];

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        // Execute request
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);

        // Close cURL
        curl_close($ch);

        // Log request and response
        $log_data = [
            'endpoint' => $endpoint,
            'method' => $method,
            'url' => $url,
            'headers' => $headers,
            'parameters' => $parameters,
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
    public function get_spot_price($api_key, $symbol)
    {
        $formatted_symbol = $this->format_symbol($symbol);
        
        $endpoint = '/openApi/spot/v1/ticker/price';
        $params = [
            'symbol' => $formatted_symbol
        ];

        $response = $this->_make_request($api_key, $endpoint, $params, 'GET', false);

        if ($response && isset($response->data) && is_array($response->data) && !empty($response->data)) {
            // Extract from nested structure
            if (isset($response->data[0]->trades) && is_array($response->data[0]->trades) && !empty($response->data[0]->trades)) {
                $price = $response->data[0]->trades[0]->price;

                // Create a standardized response object
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
     */
    public function get_futures_price($api_key, $symbol)
    {
        $formatted_symbol = $this->format_symbol($symbol);
        
        $endpoint = '/openApi/swap/v2/quote/price';
        $params = [
            'symbol' => $formatted_symbol
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
    public function open_spot_position($api_key, $symbol, $side, $quantity)
    {
        $formatted_symbol = $this->format_symbol($symbol);
        
        $endpoint = '/openApi/spot/v1/trade/order';
        $params = [
            'symbol' => $formatted_symbol,
            'side' => $side,
            'type' => 'MARKET',
            'quantity' => (string)$quantity
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
    public function close_spot_position($api_key, $symbol, $side, $quantity)
    {
        // To close a position, we need to do the opposite action
        $close_side = $side == 'BUY' ? 'SELL' : 'BUY';
        $formatted_symbol = $this->format_symbol($symbol);

        $endpoint = '/openApi/spot/v1/trade/order';
        $params = [
            'symbol' => $formatted_symbol,
            'side' => $close_side,
            'type' => 'MARKET',
            'quantity' => (string)$quantity
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
    public function set_futures_leverage($api_key, $symbol, $leverage)
    {
        $formatted_symbol = $this->format_symbol($symbol);
        
        $endpoint = '/openApi/swap/v2/trade/leverage';
        $params = [
            'symbol' => $formatted_symbol,
            'leverage' => (string)$leverage
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
    public function open_futures_position($api_key, $symbol, $side, $quantity)
    {
        $formatted_symbol = $this->format_symbol($symbol);
        
        $endpoint = '/openApi/swap/v2/trade/order';
        $params = [
            'symbol' => $formatted_symbol,
            'side' => $side,
            'positionSide' => 'BOTH',
            'type' => 'MARKET',
            'quantity' => (string)$quantity
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
    public function close_futures_position($api_key, $symbol, $side, $quantity)
    {
        // To close a position, we need to do the opposite action
        $close_side = $side == 'BUY' ? 'SELL' : 'BUY';
        $formatted_symbol = $this->format_symbol($symbol);

        $endpoint = '/openApi/swap/v2/trade/order';
        $params = [
            'symbol' => $formatted_symbol,
            'side' => $close_side,
            'positionSide' => 'BOTH',
            'type' => 'MARKET',
            'quantity' => (string)$quantity,
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
    public function get_futures_account($api_key)
    {
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
    public function get_spot_account($api_key)
    {
        $endpoint = '/openApi/spot/v1/account/balance';

        $response = $this->_make_request($api_key, $endpoint, [], 'GET', false);

        if ($response && isset($response->data)) {
            return $response->data;
        }

        return false;
    }
}