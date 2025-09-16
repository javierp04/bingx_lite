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

            // PASO 4: Ejecutar análisis IA
            $cropped_filename = 'cropped-' . pathinfo($image_filename, PATHINFO_FILENAME);
            $analysis_result = $this->createTradeAnalysis($cropped_filename);

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

        // Validar mínimo 6 precios
        if (!is_array($prices) || count($prices) < 6) {
            return null;
        }

        // Convertir todos los precios a float para asegurar ordenamiento correcto
        $prices = array_map('floatval', $prices);

        // Ordenar según operación
        if ($op_type === 'LONG') {
            sort($prices, SORT_NUMERIC);  // Ascendente (menor a mayor)
        } elseif ($op_type === 'SHORT') {
            rsort($prices, SORT_NUMERIC); // Descendente (mayor a menor)
        } else {
            return null; // op_type inválido
        }

        // Crear estructura final
        return [
            'op_type' => $op_type,
            'stoploss' => [$prices[0], $prices[1]],
            'entry' => $prices[2],
            'tps' => array_slice($prices, 3)
        ];
    }

    /**
     * Análisis IA que devuelve JSON transformado o null si falló
     */
    private function createTradeAnalysis($cropped_filename)
    {
        $in_path = "uploads/trades/" . $cropped_filename . ".png";

        if (!file_exists($in_path)) {
            return null;
        }

        // Leer y codificar imagen
        $mime = mime_content_type($in_path);
        $data = base64_encode(file_get_contents($in_path));
        $data_url = "data:{$mime};base64,{$data}";

        // Prompt
        $prompt = $this->build_prompt();

        // Llamar API OpenAI
        $apiKey = $this->config->item('openai_api_key');
        if (!$apiKey) {
            return null;
        }

        $payload = [
            "model" => "gpt-4.1-mini",
            "input" => [[
                "role" => "user",
                "content" => [
                    ["type" => "input_text", "text" => $prompt],
                    ["type" => "input_image", "image_url" => $data_url]
                ]
            ]]
        ];

        $response = $this->openai_post_json("https://api.openai.com/v1/responses", $apiKey, $payload);
        if (isset($response['error'])) {
            return null;
        }

        // Extraer texto
        $text = '';
        if (isset($response['output'][0]['content'][0]['text'])) {
            $text = $response['output'][0]['content'][0]['text'];
        } elseif (isset($response['output_text'])) {
            $text = $response['output_text'];
        }

        if (!$text) {
            return null;
        }

        // Validar JSON inicial de OpenAI
        $raw_json = json_decode($text, true);
        if ($raw_json === null) {
            $json_candidate = $this->extract_json($text);
            if ($json_candidate !== null) {
                $raw_json = $json_candidate;
            } else {
                return null;
            }
        }

        // Verificar que no sea JSON vacío
        if (empty($raw_json) || (count($raw_json) == 0)) {
            return null;
        }

        // Transformar JSON al formato final
        $transformed_json = $this->transformAnalysisData($raw_json);

        // Si la transformación falla (menos de 6 precios), retornar null
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
                ]
            ];
        } else {
            return ['error' => 'No se pudo guardar la imagen croppada'];
        }
    }

    private function build_prompt()
    {
        return <<<'PROMPT'
Necesito que analices esta imagen de un plan de trading de TradingView:
1. Extrae los precios UNICAMENTE de las etiquetas que se encuentran inmediatamente a la derecha de la caja de operación que IGNORANDO cualquier otra etiqueta que esté en la parte superior o inferior de la imagen.
2. Los numeros se presentan con separador de miles (.) y decimal (,) Debes convertirlos a formato internacional sin separador de miles y con punto decimal. Ejemplo: 1.234,56 -> 1234.56
3. Identifica si la operación es LONG o SHORT. La forma de identificarlo es:
    - ES LONG SI Y SOLO SI los números de las etiquetas con fondo verde o rojo son menores a los números de las etiquetas con fondo azul estan en el mismo eje vertical.
    - ES SHORT SI Y SOLO SI los números de las etiquetas con fondo verde o rojo son mayores a los números de las etiquetas con fondo azul que estan en el mismo eje vertical.    
La salida tiene que ser un JSON estrictamente solo, sin explicacion adicional: {op_type : "long o short", "label_prices" : array de precios}. En caso de no tener por lo menos 7 números, devolver un JSON vacio {}.
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
            ['r' => 170, 'g' => 222, 'b' => 170]
        ];
        $tolerance = 20;

        $cajaX1 = null;
        $cajaX2 = null;
        $cajaY1 = null;

        for ($y = 100; $y < $height - 150; $y++) {
            $limiteScaneo = ($cajaX2 !== null) ? $cajaX2 + 10 : (int)($width * 0.4);

            for ($x = $width - 300; $x >= $limiteScaneo; $x--) {
                $rgb = imagecolorat($image, $x, $y);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;

                if ($this->isGreenColor($r, $g, $b, $targetGreens, $tolerance)) {
                    $cajaInfo = $this->findBoxRange($image, $x, $y, $targetGreens, $tolerance);

                    if ($cajaInfo !== null) {
                        $cajaX1 = $cajaInfo['x1'];
                        $cajaX2 = $cajaInfo['x2'];
                        $cajaY1 = $y;
                        break;
                    }
                }
            }
        }

        if ($cajaX1 !== null) {
            return [
                'x1' => $cajaX1,
                'x2' => $cajaX2,
                'y1' => $cajaY1
            ];
        }

        return null;
    }

    private function findBoxRange($image, $startX, $y, $targetGreens, $tolerance)
    {
        $greenCount = 0;
        for ($x = $startX; $x >= 0; $x--) {
            $rgb = imagecolorat($image, $x, $y);
            $r = ($rgb >> 16) & 0xFF;
            $g = ($rgb >> 8) & 0xFF;
            $b = $rgb & 0xFF;

            if ($this->isGreenColor($r, $g, $b, $targetGreens, $tolerance)) {
                $greenCount++;
            } else {
                break;
            }
        }

        if ($greenCount < 20) {
            return null;
        }

        $x2 = $startX;
        $x1 = $startX;
        $nonGreenCount = 0;

        for ($x = $startX - 1; $x >= 0; $x--) {
            $rgb = imagecolorat($image, $x, $y);
            $r = ($rgb >> 16) & 0xFF;
            $g = ($rgb >> 8) & 0xFF;
            $b = $rgb & 0xFF;

            if (!$this->isGreenColor($r, $g, $b, $targetGreens, $tolerance)) {
                $nonGreenCount++;
                if ($nonGreenCount >= 20) {
                    $x1 = $x + 20;
                    break;
                }
            } else {
                $nonGreenCount = 0;
            }
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
