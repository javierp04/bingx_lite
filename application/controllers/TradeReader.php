<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * TradeReader Controller
 * Procesa webhooks de Telegram y genera señales
 */
class TradeReader extends CI_Controller
{

    public function __construct()
    {
        parent::__construct();
        $this->load->model('User_tickers_model');
        $this->load->model('Telegram_signals_model');
        $this->load->model('Log_model');
    }

    /**
     * Webhook principal de Telegram
     * Procesa mensajes y genera señales automáticamente
     */
    public function generateSignalFromTelegram()
    {
        $update = file_get_contents("php://input");

        try {   
            // 1. Validar JSON
            $webhook_data = json_decode($update, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->Log_model->add_log([
                    'user_id' => null,
                    'action' => 'telegram_webhook_error',
                    'description' => 'Invalid JSON received from Telegram webhook'
                ]);
                http_response_code(200);
                echo json_encode(['status' => 'ok', 'message' => 'Invalid JSON']);
                return;
            }

            // 2. Verificar que hay texto
            if (!isset($webhook_data['message']['text'])) {
                http_response_code(200);
                echo json_encode(['status' => 'ok']);
                return;
            }

            $message_text = $webhook_data['message']['text'];

            // 3. Solo procesar si empieza con "Sentimiento" - sino salir sin loguear
            if (!preg_match('/^Sentimiento\s+/', $message_text)) {
                http_response_code(200);
                echo json_encode(['status' => 'ok']);
                return;
            }

            // 4. Extraer ticker - REGEX CORREGIDO
            if (!preg_match('/#([A-Za-z0-9]+)/', $message_text, $ticker_matches)) {
                $this->Log_model->add_log([
                    'user_id' => null,
                    'action' => 'telegram_webhook_error',
                    'description' => 'Sentiment message found but no valid ticker hashtag detected: ' . $message_text
                ]);
                http_response_code(200);
                echo json_encode(['status' => 'ok', 'message' => 'No ticker found']);
                return;
            }

            $ticker_symbol = strtoupper($ticker_matches[1]); // NORMALIZAR A MAYÚSCULAS
            $ticker = $this->User_tickers_model->get_available_ticker($ticker_symbol);

            if (!$ticker) {
                $this->Log_model->add_log([
                    'user_id' => null,
                    'action' => 'telegram_webhook_error',
                    'description' => 'Ticker not found in available tickers: ' . $ticker_symbol
                ]);
                http_response_code(200);
                echo json_encode(['status' => 'ok', 'message' => 'Ticker not available']);
                return;
            }

            // 5. Extraer URL de TradingView
            if (!preg_match('/(https:\/\/www\.tradingview\.com\/x\/[a-zA-Z0-9\/]+)/', $message_text, $url_matches)) {
                $this->Log_model->add_log([
                    'user_id' => null,
                    'action' => 'telegram_webhook_error',
                    'description' => 'Sentiment signal for ' . $ticker_symbol . ' found but no TradingView URL detected'
                ]);
                http_response_code(200);
                echo json_encode(['status' => 'ok', 'message' => 'No TradingView URL found']);
                return;
            }

            $tradingview_url = $url_matches[1];

            // 6. Preparar archivos
            $current_date = date('Y-m-d');
            $image_filename = $current_date . '_' . $ticker_symbol . '.png';
            $image_path = 'uploads/trades/' . $image_filename;

            // Crear directorio si no existe
            $upload_dir = dirname($image_path);
            if (!is_dir($upload_dir)) {
                if (!mkdir($upload_dir, 0755, true)) {
                    $this->Log_model->add_log([
                        'user_id' => null,
                        'action' => 'telegram_webhook_error',
                        'description' => 'Failed to create directory: ' . $upload_dir . ' for ticker: ' . $ticker_symbol
                    ]);
                    http_response_code(200);
                    echo json_encode(['status' => 'ok', 'message' => 'Directory creation failed']);
                    return;
                }
            }

            // 7. Descargar imagen
            $image_downloaded = $this->downloadTradingViewImage($tradingview_url, $image_path, $ticker_symbol);

            if (!$image_downloaded) {
                // Error ya logueado en downloadTradingViewImage()
                http_response_code(200);
                echo json_encode(['status' => 'ok', 'message' => 'Image download failed']);
                return;
            }

            // 8. Crear señal con status 'pending'
            $signal_id = $this->Telegram_signals_model->create_signal($ticker_symbol, $image_path, $tradingview_url, $message_text, $update);

            // 9. INICIAR PIPELINE AUTOMÁTICO
            $this->processSignalPipeline($signal_id, $image_filename, $ticker_symbol);

            http_response_code(200);
            echo json_encode([
                'status' => 'success',
                'signal_id' => $signal_id,
                'ticker' => $ticker_symbol,
                'image_file' => $image_filename
            ]);
        } catch (Exception $e) {
            $this->Log_model->add_log([
                'user_id' => null,
                'action' => 'telegram_webhook_error',
                'description' => 'Telegram webhook processing failed with exception: ' . $e->getMessage()
            ]);

            http_response_code(200);
            echo json_encode(['status' => 'ok', 'message' => 'Internal server error']);
        }
    }

    /**
     * Pipeline automático: crop → análisis → completion
     */
    private function processSignalPipeline($signal_id, $image_filename, $ticker_symbol)
    {
        try {
            // PASO 1: Cambiar status a 'cropping'
            $this->Telegram_signals_model->update_signal_status($signal_id, 'cropping');

            // PASO 2: Ejecutar crop
            $crop_result = $this->detectAndCrop(pathinfo($image_filename, PATHINFO_FILENAME));

            if (isset($crop_result['error'])) {
                // Crop falló
                $this->Telegram_signals_model->update_signal_status($signal_id, 'failed_crop');
                $this->Log_model->add_log([
                    'user_id' => null,
                    'action' => 'telegram_crop_failed',
                    'description' => 'Crop failed for signal ID: ' . $signal_id . '. Error: ' . $crop_result['error']
                ]);
                return;
            }

            // PASO 3: Cambiar status a 'analyzing'
            $this->Telegram_signals_model->update_signal_status($signal_id, 'analyzing');

            // PASO 4: Ejecutar análisis IA con información de la caja verde
            $cropped_filename = 'cropped-' . pathinfo($image_filename, PATHINFO_FILENAME);
            $cajaCoords = $crop_result['box_coords'];
            $imageHeight = $crop_result['image_height'];
            $analysis_result = $this->createTradeAnalysis($cropped_filename, $cajaCoords, $imageHeight);

            if (!$analysis_result) {
                // Análisis falló o devolvió JSON vacío
                $this->Telegram_signals_model->update_signal_status($signal_id, 'failed_analysis');
                $this->Log_model->add_log([
                    'user_id' => null,
                    'action' => 'telegram_analysis_empty',
                    'description' => 'AI analysis returned empty JSON for signal ID: ' . $signal_id
                ]);
                return;
            }

            // PASO 5: Guardar análisis y marcar como completado
            $this->Telegram_signals_model->complete_signal($signal_id, json_encode($analysis_result));

            // ✨ PASO 6: AGREGAR ESTA LÍNEA - Crear user_signals automáticamente
            $users_count = $this->Telegram_signals_model->create_user_signals_for_ticker($signal_id, $ticker_symbol);

            $this->Log_model->add_log([
                'user_id' => null,
                'action' => 'telegram_pipeline_completed',
                'description' => 'Signal pipeline completed for ID: ' . $signal_id . '. Analysis: ' . json_encode($analysis_result) . '. Distributed to ' . $users_count . ' users.'
            ]);
        } catch (Exception $e) {
            // Error general del pipeline
            $this->Telegram_signals_model->update_signal_status($signal_id, 'failed_analysis');
            $this->Log_model->add_log([
                'user_id' => null,
                'action' => 'telegram_pipeline_error',
                'description' => 'Pipeline failed for signal ID: ' . $signal_id . '. Error: ' . $e->getMessage()
            ]);
        }
    }

    private function transformAnalysisData($raw_json)
    {
        // Verificar estructura básica
        if (!isset($raw_json['op_type']) || !isset($raw_json['label_prices'])) {
            return null;
        }

        $prices = $raw_json['label_prices'];
        $op_type = strtoupper(trim($raw_json['op_type']));

        // Validar mínimo 8 precios (2 SL + 1 Entry + 5 TPs)
        if (!is_array($prices) || count($prices) < 8) {
            return null;
        }

        // Convertir todos los precios a float
        $prices = array_map('floatval', $prices);

        // LOS PRECIOS VIENEN ORDENADOS VISUALMENTE DE ARRIBA HACIA ABAJO
        // NO necesitamos sort/rsort, solo asignarlos correctamente según el tipo de operación

        if ($op_type === 'LONG') {
            // LONG: Visualmente de arriba hacia abajo = TP5, TP4, TP3, TP2, TP1, Entry, SL2, SL1
            // Los TPs están arriba (profit), SL y Entry abajo
            return [
                'op_type' => $op_type,
                'stoploss' => [$prices[7], $prices[6]],  // Los 2 últimos (más abajo)
                'entry' => $prices[5],                    // Tercero desde abajo
                'tps' => [                                // Invertir orden (de TP1 a TP5)
                    $prices[4],  // TP1
                    $prices[3],  // TP2
                    $prices[2],  // TP3
                    $prices[1],  // TP4
                    $prices[0]   // TP5 (el más arriba)
                ]
            ];
        } elseif ($op_type === 'SHORT') {
            // SHORT: Visualmente de arriba hacia abajo = SL1, SL2, Entry, TP1, TP2, TP3, TP4, TP5
            // Los SL están arriba (stop), TPs y Entry abajo
            return [
                'op_type' => $op_type,
                'stoploss' => [$prices[0], $prices[1]],  // Los 2 primeros (más arriba)
                'entry' => $prices[2],                    // Tercero desde arriba
                'tps' => [                                // Orden directo (de TP1 a TP5)
                    $prices[3],  // TP1
                    $prices[4],  // TP2
                    $prices[5],  // TP3
                    $prices[6],  // TP4
                    $prices[7]   // TP5 (el más abajo)
                ]
            ];
        } else {
            return null; // op_type inválido
        }
    }

    /**
     * Análisis IA que devuelve JSON transformado o null si falló
     * Soporta OpenAI y Claude según configuración
     *
     * @param string $cropped_filename Nombre del archivo cropped
     * @param array  $cajaCoords Coordenadas de la caja verde (con y_min y y_max)
     * @param int    $imageHeight Altura de la imagen original
     */
    private function createTradeAnalysis($cropped_filename, $cajaCoords, $imageHeight)
    {
        $in_path = "uploads/trades/" . $cropped_filename . ".png";

        if (!file_exists($in_path)) {
            return null;
        }

        // 1. Detectar tipo de operación ANTES de llamar a la IA
        $op_type = $this->detectOperationType($cajaCoords, $imageHeight);

        // 2. Preparar datos comunes
        $image_base64 = base64_encode(file_get_contents($in_path));
        $prompt = $this->build_prompt();

        // 3. Detectar provider desde configuración
        $provider = $this->config->item('ai_provider') ?: 'openai';

        // 4. Llamar API según provider
        if ($provider === 'claude') {
            $response = $this->call_claude_api($image_base64, $prompt);
        } else {
            $response = $this->call_openai_api($image_base64, $prompt);
        }

        // 5. Verificar errores
        if (isset($response['error'])) {
            $this->Log_model->add_log([
                'user_id' => null,
                'action' => 'ai_analysis_error',
                'description' => 'AI analysis failed with ' . $provider . ': ' . json_encode($response['error'])
            ]);
            return null;
        }

        // 6. Extraer texto de la respuesta (normalizado para ambas APIs)
        $text = $this->extract_ai_response($response, $provider);

        if (!$text) {
            return null;
        }

        // 7. Validar JSON inicial de la IA
        $raw_json = json_decode($text, true);
        if ($raw_json === null) {
            $json_candidate = $this->extract_json($text);
            if ($json_candidate !== null) {
                $raw_json = $json_candidate;
            } else {
                return null;
            }
        }

        // 8. Verificar que no sea JSON vacío
        if (empty($raw_json) || (count($raw_json) == 0)) {
            return null;
        }

        // 9. NUEVO: Agregar op_type detectado automáticamente
        $raw_json['op_type'] = $op_type;

        // 10. Transformar JSON al formato final
        $transformed_json = $this->transformAnalysisData($raw_json);

        // Si la transformación falla (menos de 8 precios), retornar null
        if ($transformed_json === null) {
            return null;
        }

        return $transformed_json;
    }

    /**
     * Descarga imagen de TradingView haciendo scraping del HTML
     */
    private function downloadTradingViewImage($tradingview_url, $save_path, $ticker)
    {
        try {
            // PASO 1: Obtener HTML de la página
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $tradingview_url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                'Accept-Language: en-US,en;q=0.5',
            ]);

            $html_content = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curl_error = curl_error($ch);
            curl_close($ch);

            if ($curl_error || $http_code !== 200 || empty($html_content)) {
                $this->Log_model->add_log([
                    'user_id' => null,
                    'action' => 'telegram_image_error',
                    'description' => 'Failed to fetch TradingView page for ticker: ' . $ticker .
                        '. HTTP Code: ' . $http_code . '. cURL Error: ' . ($curl_error ?: 'None')
                ]);
                return false;
            }

            // PASO 2: Extraer URL de imagen del <main>
            if (!preg_match('/<main[^>]*class=["\']main["\'][^>]*>.*?<img[^>]+src=["\']([^"\']+)["\'][^>]*>.*?<\/main>/si', $html_content, $matches)) {
                $this->Log_model->add_log([
                    'user_id' => null,
                    'action' => 'telegram_image_error',
                    'description' => 'Could not find image URL in <main> section for ticker: ' . $ticker
                ]);
                return false;
            }

            $image_url = $matches[1];

            // PASO 3: Descargar imagen directamente
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $image_url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

            $image_data = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curl_error = curl_error($ch);
            curl_close($ch);

            if ($curl_error || $http_code !== 200 || empty($image_data)) {
                $this->Log_model->add_log([
                    'user_id' => null,
                    'action' => 'telegram_image_error',
                    'description' => 'Failed to download image for ticker: ' . $ticker .
                        '. Image URL: ' . $image_url . '. HTTP Code: ' . $http_code
                ]);
                return false;
            }

            // PASO 4: Guardar imagen
            if (file_put_contents($save_path, $image_data) === false) {
                $this->Log_model->add_log([
                    'user_id' => null,
                    'action' => 'telegram_image_error',
                    'description' => 'Failed to save image file for ticker: ' . $ticker . '. Path: ' . $save_path
                ]);
                return false;
            }

            return true;
        } catch (Exception $e) {
            $this->Log_model->add_log([
                'user_id' => null,
                'action' => 'telegram_image_error',
                'description' => 'Exception downloading image for ticker: ' . $ticker .
                    '. Error: ' . $e->getMessage()
            ]);
            return false;
        }
    }

    public function detectAndCrop($inputPath)
    {
        $in_path = "uploads/trades/" . $inputPath . ".png";
        $outputPath = "uploads/trades/cropped-" . pathinfo($in_path, PATHINFO_FILENAME) . ".png";

        if (!file_exists($in_path)) {
            return ['error' => 'Archivo no encontrado: ' . $in_path];
        }

        $image = imagecreatefrompng($in_path);
        if (!$image) {
            return ['error' => 'No se pudo cargar la imagen'];
        }

        $width = imagesx($image);
        $height = imagesy($image);

        $cajaCoords = $this->findRightmostGreenBox($image, $width, $height);

        if (!$cajaCoords) {
            imagedestroy($image);
            return ['error' => 'No se encontraron cajas verdes'];
        }

        $cropX1 = $cajaCoords['x1'] - 50;
        $cropX2 = $cajaCoords['x2'] + 150;
        $cropY1 = 40;
        $cropY2 = $height - 150;

        $cropWidth = $cropX2 - $cropX1;
        $cropHeight = $cropY2 - $cropY1;

        $croppedImage = imagecreatetruecolor($cropWidth, $cropHeight);
        imagecopy($croppedImage, $image, 0, 0, $cropX1, $cropY1, $cropWidth, $cropHeight);

        $success = imagepng($croppedImage, $outputPath);

        imagedestroy($image);
        imagedestroy($croppedImage);

        if ($success) {
            return [
                'success' => true,
                'output_path' => $outputPath,
                'coordinates' => [
                    'x1' => $cropX1,
                    'x2' => $cropX2,
                    'y1' => $cropY1,
                    'y2' => $cropY2
                ],
                'box_coords' => $cajaCoords,  // Coordenadas de la caja verde detectada
                'image_height' => $height      // Altura de la imagen original
            ];
        } else {
            return ['error' => 'No se pudo guardar la imagen croppada'];
        }
    }

    private function build_prompt()
    {
        return <<<'PROMPT'
Analiza esta imagen de TradingView y extrae ÚNICAMENTE los precios de las etiquetas que aparecen a la derecha de la caja de operación.

INSTRUCCIONES:
1. Extrae SOLO los números de las etiquetas inmediatamente a la derecha de la caja coloreada
2. IGNORA cualquier etiqueta que esté en la parte superior o inferior de la imagen
3. Los números usan formato europeo: separador de miles (.) y decimal (,)
4. Convierte al formato estándar: sin separador de miles y punto decimal
   Ejemplo: 1.234,56 → 1234.56

ORDEN DE EXTRACCIÓN:
- Extrae los precios en orden visual DE ARRIBA HACIA ABAJO
- El primer precio debe ser el que está visualmente más arriba
- El último precio debe ser el que está visualmente más abajo

FORMATO DE SALIDA:
Devuelve un JSON con un array de precios ordenados de arriba hacia abajo:
{"label_prices": [precio1, precio2, precio3, ...]}

IMPORTANTE:
- Devuelve al menos 8 precios (si hay menos de 8, devuelve {})
- Solo números en formato estándar (punto decimal)
- Sin texto adicional, solo el JSON
- Mantén el orden visual estricto: de arriba hacia abajo
PROMPT;
    }

    private function openai_post_json($url, $apiKey, $payload)
    {
        $ch = curl_init($url);
        $headers = [
            "Authorization: Bearer {$apiKey}",
            "Content-Type: application/json"
        ];
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 120,
            CURLOPT_POSTFIELDS => json_encode($payload)
        ]);
        $raw = curl_exec($ch);
        if ($raw === false) {
            $err = curl_error($ch);
            curl_close($ch);
            return ['error' => "cURL error: {$err}"];
        }
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $json = json_decode($raw, true);
        if ($code >= 400) {
            return ['error' => $json ?: $raw, 'status' => $code];
        }
        return $json ?: ['error' => 'Respuesta no JSON', 'raw' => $raw];
    }

    private function claude_post_json($url, $apiKey, $payload)
    {
        $ch = curl_init($url);
        $headers = [
            "x-api-key: {$apiKey}",
            "anthropic-version: 2023-06-01",
            "Content-Type: application/json"
        ];
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 120,
            CURLOPT_POSTFIELDS => json_encode($payload)
        ]);
        $raw = curl_exec($ch);
        if ($raw === false) {
            $err = curl_error($ch);
            curl_close($ch);
            return ['error' => "cURL error: {$err}"];
        }
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $json = json_decode($raw, true);
        if ($code >= 400) {
            return ['error' => $json ?: $raw, 'status' => $code];
        }
        return $json ?: ['error' => 'Respuesta no JSON', 'raw' => $raw];
    }

    private function call_openai_api($image_base64, $prompt)
    {
        $apiKey = $this->config->item('openai_api_key');
        if (!$apiKey) {
            return ['error' => 'OpenAI API key not configured'];
        }

        // OpenAI espera la imagen con el prefijo data:image
        $data_url = "data:image/png;base64,{$image_base64}";

        $payload = [
            "model" => "gpt-4o-mini",
            "messages" => [[
                "role" => "user",
                "content" => [
                    ["type" => "text", "text" => $prompt],
                    ["type" => "image_url", "image_url" => ["url" => $data_url]]
                ]
            ]]
        ];

        return $this->openai_post_json("https://api.openai.com/v1/chat/completions", $apiKey, $payload);
    }

    private function call_claude_api($image_base64, $prompt)
    {
        $apiKey = $this->config->item('claude_api_key');
        if (!$apiKey) {
            return ['error' => 'Claude API key not configured'];
        }

        // Claude espera la imagen SIN el prefijo data:image
        $payload = [
            "model" => "claude-sonnet-4-5-20250929",
            "max_tokens" => 4096,
            "messages" => [[
                "role" => "user",
                "content" => [
                    ["type" => "text", "text" => $prompt],
                    [
                        "type" => "image",
                        "source" => [
                            "type" => "base64",
                            "media_type" => "image/png",
                            "data" => $image_base64
                        ]
                    ]
                ]
            ]]
        ];

        return $this->claude_post_json("https://api.anthropic.com/v1/messages", $apiKey, $payload);
    }

    private function extract_ai_response($response, $provider)
    {
        if (isset($response['error'])) {
            return null;
        }

        $text = '';

        if ($provider === 'claude') {
            // Claude: response.content[0].text
            if (isset($response['content'][0]['text'])) {
                $text = $response['content'][0]['text'];
            }
        } else {
            // OpenAI: response.choices[0].message.content
            if (isset($response['choices'][0]['message']['content'])) {
                $text = $response['choices'][0]['message']['content'];
            }
        }

        return $text ?: null;
    }

    private function extract_json($text)
    {
        if (preg_match('/\{(?:[^{}]|(?R))*\}/s', $text, $m)) {
            $candidate = json_decode($m[0], true);
            return $candidate;
        }
        return null;
    }

    private function findRightmostGreenBox($image, $width, $height)
    {
        $targetGreens = [
            ['r' => 197, 'g' => 233, 'b' => 197],
            ['r' => 170, 'g' => 222, 'b' => 170],
            ['r' => 170, 'g' => 222, 'b' => 134]
        ];
        $tolerance = 15;
        $gapThreshold = 20;  // Píxeles no-verdes consecutivos para considerar fin de caja

        $cajaX1 = null;
        $cajaX2 = null;
        $cajaY1 = null;
        $cajaYMin = null;  // Primera Y donde encontró verde
        $cajaYMax = null;  // Última Y donde encontró verde

        for ($y = 100; $y < $height - 150; $y++) {
            $limiteScaneo = ($cajaX2 !== null) ? $cajaX2 + 10 : (int)($width * 0.4);

            for ($x = $width - 300; $x >= $limiteScaneo; $x--) {
                $rgb = imagecolorat($image, $x, $y);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;

                if ($this->isGreenColor($r, $g, $b, $targetGreens, $tolerance)) {
                    $cajaInfo = $this->findBoxRange($image, $x, $y, $targetGreens, $tolerance, $gapThreshold);

                    if ($cajaInfo !== null) {
                        $cajaX1 = $cajaInfo['x1'];
                        $cajaX2 = $cajaInfo['x2'];
                        $cajaY1 = $y;

                        // Rastrear Y mínimo (primera vez que encontramos verde)
                        if ($cajaYMin === null) {
                            $cajaYMin = $y;
                        }
                        // Actualizar Y máximo (última vez que encontramos verde)
                        $cajaYMax = $y;

                        break;
                    }
                }
            }
        }

        if ($cajaX1 !== null) {
            return [
                'x1' => $cajaX1,
                'x2' => $cajaX2,
                'y1' => $cajaY1,
                'y_min' => $cajaYMin,
                'y_max' => $cajaYMax
            ];
        }

        return null;
    }

    /**
     * Detecta si la operación es LONG o SHORT basándose en la posición vertical del verde
     *
     * LÓGICA:
     * - Verde en mitad SUPERIOR de la imagen → LONG (take profit arriba)
     * - Verde en mitad INFERIOR de la imagen → SHORT (take profit abajo)
     *
     * @param array $cajaCoords Coordenadas de la caja verde (con y_min y y_max)
     * @param int   $imageHeight Altura total de la imagen
     * @return string 'LONG' o 'SHORT'
     */
    private function detectOperationType($cajaCoords, $imageHeight)
    {
        // Calcular el centro vertical de la caja verde
        $green_center_y = ($cajaCoords['y_min'] + $cajaCoords['y_max']) / 2;

        // Calcular el punto medio de la imagen
        $image_midpoint_y = $imageHeight / 2;

        // Comparar posiciones
        if ($green_center_y < $image_midpoint_y) {
            // Centro del verde está en la MITAD SUPERIOR → LONG
            return 'LONG';
        } else {
            // Centro del verde está en la MITAD INFERIOR → SHORT
            return 'SHORT';
        }
    }

    private function findBoxRange($image, $startX, $y, $targetGreens, $tolerance, $gapThreshold = 20)
    {
        $x2 = $startX;        // Borde derecho (donde empezamos)
        $x1 = null;           // Borde izquierdo (lo encontramos escaneando)
        $nonGreenCount = 0;   // Contador de píxeles no-verdes consecutivos
        $minBoxWidth = 20;    // Ancho mínimo de caja válida

        // UN SOLO LOOP: escanear de derecha a izquierda tolerando ruido
        for ($x = $startX; $x >= 0; $x--) {
            $rgb = imagecolorat($image, $x, $y);
            $r = ($rgb >> 16) & 0xFF;
            $g = ($rgb >> 8) & 0xFF;
            $b = $rgb & 0xFF;

            if ($this->isGreenColor($r, $g, $b, $targetGreens, $tolerance)) {
                // Encontramos píxel verde → actualizar borde izquierdo
                $x1 = $x;
                $nonGreenCount = 0;  // Reset contador (toleramos ruido)
            } else {
                // Encontramos píxel no-verde → incrementar contador
                $nonGreenCount++;

                // ¿Llegamos al threshold de píxeles no-verdes consecutivos?
                if ($nonGreenCount >= $gapThreshold) {
                    // Encontramos el fin de la caja (gap definitivo)
                    break;
                }
            }
        }

        // Validar que encontramos al menos un píxel verde
        if ($x1 === null) {
            return null;  // No hay píxeles verdes en esta línea
        }

        // Validar que la caja sea suficientemente ancha
        $width = $x2 - $x1;
        if ($width < $minBoxWidth) {
            return null;  // Caja demasiado angosta (probablemente ruido)
        }

        return [
            'x1' => max(0, $x1),
            'x2' => $x2
        ];
    }

    private function isGreenColor($r, $g, $b, $targetGreens, $tolerance)
    {
        foreach ($targetGreens as $targetGreen) {
            if (
                abs($r - $targetGreen['r']) <= $tolerance &&
                abs($g - $targetGreen['g']) <= $tolerance &&
                abs($b - $targetGreen['b']) <= $tolerance
            ) {
                return true;
            }
        }
        return false;
    }
}
