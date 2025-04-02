<?php
// Test script using GET instead of POST

// Your API credentials
$API_KEY = "mlTNwZLYS5qb0ojsPMUxym78kboX5ekCAYLUcUrPrr2kYrSpbP6DRTEDDkQcLMT9C8cNEcqjUdlI0zyA794Q";
$API_SECRET = "vsk1FT5W2ZOUNmkHyO3B8OlvPXMlVefAJ4Vqt1PPtK2oGZq50on8g1XFBMBcEnhAPtVLiNBM5MaDxmdMWImHIg";

// Fixed parameters 
$HOST = "open-api.bingx.com";
$api_endpoint = "/openApi/spot/v1/trade/order";
$method = "GET"; // Changed to GET
$protocol = "https";

// Order parameters
$order_params = [
    "symbol" => "BTC-USDT",
    "side" => "BUY",
    "type" => "MARKET",
    "quantity" => "0.0001"
];

// Create parameters string exactly like the official example
$timestamp = round(microtime(true) * 1000);
$parameters = "timestamp=" . $timestamp;

foreach ($order_params as $key => $value) {
    $parameters .= "&$key=$value";
}

// Generate signature (lowercase as in their example)
$hash = hash_hmac("sha256", $parameters, $API_SECRET, true);
$hashHex = bin2hex($hash);
$signature = strtolower($hashHex);

// Construct URL exactly as in the example
$url = "{$protocol}://{$HOST}{$api_endpoint}?{$parameters}&signature={$signature}";

// Debug information
echo "Method: {$method}\n";
echo "URL: {$url}\n";
echo "Parameters: {$parameters}\n";
echo "Signature: {$signature}\n";

// Make the request
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);

// GET setup
curl_setopt($ch, CURLOPT_HTTPGET, true);

curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/x-www-form-urlencoded',
    'X-BX-APIKEY: ' . $API_KEY
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);

curl_close($ch);

echo "\nHTTP Code: {$httpCode}\n";
if ($error) {
    echo "CURL Error: {$error}\n";
}
echo "Response:\n{$response}\n";