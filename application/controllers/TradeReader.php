<?php
defined('BASEPATH') or exit('No direct script access allowed');

class TradeReader extends CI_Controller
{

    public function __construct()
    {
        parent::__construct();
    }
    public function generateSignalFromTelegram()
    {
        $update = file_get_contents("php://input");
        $update = '{"update_id":219260603,
"message":{"message_id":9,"from":{"id":671627305,"is_bot":false,"first_name":"Javier","username":"javi_pel","language_code":"es"},"chat":{"id":-1003019203652,"title":"Test Signal Generator","type":"supergroup"},"date":1756855249,"text":"Sentimiento #NQ https://www.tradingview.com/x/vpaJOzpO/","entities":[{"offset":12,"length":3,"type":"hashtag"},{"offset":16,"length":39,"type":"url"}],"link_preview_options":{"url":"https://www.tradingview.com/x/vpaJOzpO/","prefer_large_media":true}}}
';
        file_put_contents("uploads/telegram_webhook_log.txt", $update . "\n\n", FILE_APPEND);

        try {
            // 1. Validar JSON
            $webhook_data = json_decode($update, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->Log_model->add_log([
                    'user_id' => null,
                    'action' => 'telegram_webhook_error',
                    'description' => 'Invalid JSON received from Telegram webhook. Data: ' . $update
                ]);
                http_response_code(400);
                echo json_encode(['status' => 'error', 'message' => 'Invalid JSON']);
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

            // 4. Extraer ticker
            if (!preg_match('/#([A-Z0-9]+)/', $message_text, $ticker_matches)) {
                $this->Log_model->add_log([
                    'user_id' => null,
                    'action' => 'telegram_webhook_error',
                    'description' => 'Sentiment message found but no valid ticker hashtag detected: ' . $message_text
                ]);
                http_response_code(400);
                echo json_encode(['status' => 'error', 'message' => 'No ticker found']);
                return;
            }

            $ticker = $ticker_matches[1];

            // 5. Extraer URL de TradingView
            if (!preg_match('/(https:\/\/www\.tradingview\.com\/x\/[a-zA-Z0-9\/]+)/', $message_text, $url_matches)) {
                $this->Log_model->add_log([
                    'user_id' => null,
                    'action' => 'telegram_webhook_error',
                    'description' => 'Sentiment signal for ' . $ticker . ' found but no TradingView URL detected: ' . $message_text
                ]);
                http_response_code(400);
                echo json_encode(['status' => 'error', 'message' => 'No TradingView URL found']);
                return;
            }

            $tradingview_url = $url_matches[1];

            // 6. Preparar archivos
            $current_date = date('Y-m-d');
            $image_filename = $current_date . '_' . $ticker . '.png';
            $image_path = 'uploads/trades/' . $image_filename;

            // Crear directorio si no existe
            $upload_dir = dirname($image_path);
            if (!is_dir($upload_dir)) {
                if (!mkdir($upload_dir, 0755, true)) {
                    $this->Log_model->add_log([
                        'user_id' => null,
                        'action' => 'telegram_image_error',
                        'description' => 'Failed to create directory: ' . $upload_dir . ' for ticker: ' . $ticker
                    ]);
                    http_response_code(500);
                    echo json_encode(['status' => 'error', 'message' => 'Directory creation failed']);
                    return;
                }
            }

            // 7. Descargar imagen
            $image_downloaded = $this->downloadTradingViewImage($tradingview_url, $image_path, $ticker);

            if ($image_downloaded) {
                $this->Log_model->add_log([
                    'user_id' => null,
                    'action' => 'telegram_signal_processed',
                    'description' => 'Sentiment signal successfully processed for ticker: ' . $ticker .
                        '. Image saved as: ' . $image_filename .
                        '. TradingView URL: ' . $tradingview_url
                ]);

                http_response_code(200);
                echo json_encode([
                    'status' => 'success',
                    'ticker' => $ticker,
                    'image_file' => $image_filename
                ]);
            } else {
                http_response_code(207);
                echo json_encode([
                    'status' => 'partial_success',
                    'ticker' => $ticker,
                    'message' => 'Signal processed but image download failed'
                ]);
            }
        } catch (Exception $e) {
            $this->Log_model->add_log([
                'user_id' => null,
                'action' => 'telegram_webhook_error',
                'description' => 'Telegram webhook processing failed with exception: ' . $e->getMessage()
            ]);

            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Internal server error']);
        }
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
                        '. HTTP Code: ' . $http_code . '. cURL Error: ' . ($curl_error ?: 'None') . '. URL: ' . $tradingview_url
                ]);
                return false;
            }

            // PASO 2: Extraer URL de imagen del <main>
            if (!preg_match('/<main[^>]*class=["\']main["\'][^>]*>.*?<img[^>]+src=["\']([^"\']+)["\'][^>]*>.*?<\/main>/si', $html_content, $matches)) {
                $this->Log_model->add_log([
                    'user_id' => null,
                    'action' => 'telegram_image_error',
                    'description' => 'Could not find image URL in <main> section for ticker: ' . $ticker . '. URL: ' . $tradingview_url
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
                        '. Image URL: ' . $image_url . '. HTTP Code: ' . $http_code .
                        '. cURL Error: ' . ($curl_error ?: 'None')
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

            $this->Log_model->add_log([
                'user_id' => null,
                'action' => 'telegram_image_downloaded',
                'description' => 'Image successfully downloaded for ticker: ' . $ticker .
                    '. Saved to: ' . $save_path . '. Size: ' . strlen($image_data) . ' bytes. ' .
                    'Page URL: ' . $tradingview_url . '. Image URL: ' . $image_url
            ]);

            return true;
        } catch (Exception $e) {
            $this->Log_model->add_log([
                'user_id' => null,
                'action' => 'telegram_image_error',
                'description' => 'Exception downloading image for ticker: ' . $ticker .
                    '. Error: ' . $e->getMessage() . '. URL: ' . $tradingview_url
            ]);
            return false;
        }
    }


    public function run($in_path = null, $out_path = null)
    {
        // Permitir llamada por querystring o CLI
        if ($in_path === null) {
            $in_path  = $this->input->get('in');
        }
        if ($out_path === null) {
            $out_path = $this->input->get('out');
        }

        $in_path = "uploads/trades/cropped-" . $in_path . ".png";
        $out_path = "mt_out_signals/" . pathinfo($in_path, PATHINFO_FILENAME) . ".json";

        if (empty($in_path) || empty($out_path)) {
            return $this->output
                ->set_status_header(400)
                ->set_content_type('application/json')
                ->set_output(json_encode(['error' => 'Faltan parámetros in/out']));
        }

        if (!file_exists($in_path)) {
            return $this->output
                ->set_status_header(404)
                ->set_content_type('application/json')
                ->set_output(json_encode(['error' => 'No se encuentra la imagen: ' . $in_path]));
        }

        // 1) Leer y codificar la imagen a data-URL base64
        $mime = mime_content_type($in_path); // ej: image/png o image/jpeg
        $data = base64_encode(file_get_contents($in_path));
        $data_url = "data:{$mime};base64,{$data}";

        // 2) Prompt (reglas ajustadas a su especificación)
        $prompt = $this->build_prompt();

        // 3) Llamar a la API (Responses API)
        $apiKey = $this->config->item('openai_api_key');
        if (!$apiKey) {
            return $this->output
                ->set_status_header(500)
                ->set_content_type('application/json')
                ->set_output(json_encode(['error' => 'OPENAI_API_KEY no configurado']));
        }

        $payload = [
            "model" => "gpt-4.1-mini", // si necesita más precisión, pruebe "gpt-4.1" solo en casos difíciles
            "input" => [[
                "role" => "user",
                "content" => [
                    ["type" => "input_text",  "text" => $prompt],   // Prompt v2.1 de arriba
                    ["type" => "input_image", "image_url" => $data_url]
                ]
            ]]
        ];

        $response = $this->openai_post_json("https://api.openai.com/v1/responses", $apiKey, $payload);
        if (isset($response['error'])) {
            return $this->output
                ->set_status_header(500)
                ->set_content_type('application/json')
                ->set_output(json_encode(['error' => 'Error API', 'detail' => $response['error']]));
        }

        // 4) Extraer el texto de salida del Responses API
        // Respuesta típica: $response['output'][0]['content'][0]['text']
        $text = '';
        if (isset($response['output'][0]['content'][0]['text'])) {
            $text = $response['output'][0]['content'][0]['text'];
        } elseif (isset($response['output_text'])) {
            $text = $response['output_text'];
        }

        if (!$text) {
            return $this->output
                ->set_status_header(502)
                ->set_content_type('application/json')
                ->set_output(json_encode(['error' => 'Sin texto en la respuesta del modelo']));
        }

        // 5) Validar que el modelo devolvió SOLO JSON        
        $json = json_decode($text, true);
        if ($json === null) {
            // Intento de limpiar si vino con texto extra accidental
            $json_candidate = $this->extract_json($text);
            if ($json_candidate !== null) {
                $json = $json_candidate;
            } else {
                return $this->output
                    ->set_status_header(422)
                    ->set_content_type('application/json')
                    ->set_output(json_encode(['error' => 'La salida no es JSON válido', 'raw' => $text]));
            }
        }

        // 6) Guardar en archivo
        $ok = @file_put_contents($out_path, json_encode($json, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        if ($ok === false) {
            return $this->output
                ->set_status_header(500)
                ->set_content_type('application/json')
                ->set_output(json_encode(['error' => 'No se pudo escribir el archivo de salida: ' . $out_path]));
        }

        // return $this->output
        //     ->set_content_type('application/json')
        //     ->set_output(json_encode(['status' => 'ok', 'out' => $out_path]));
        //echo "<pre>";
        print_r($json);
    }

    /**
     * Prompt con reglas: etiquetas a la derecha, normalización decimal, etc.
     */

    private function build_prompt()
    {
        return <<<'PROMPT'
1. Necesito que analices esta imagen de un plan de trading de TradingView para identificar el tipo de operacion (LONG o SHORT). AYUDA. SI LA PARTE VERDE ESTA ARRIBA DE LA ROJA, ES LONG. SI LA ROJA ESTA ARRIBA DE LA VERDE, ES SHORT.
2. Extrae los precios UNICAMENTE de las etiquetas que se encuentran inmediatamente a la derecha de la caja de operación IGNORANDO cualquier otra etiqueta que esté en la parte superior o inferior de la imagen.
3. Los numeros se presentan con separador de miles (.) y decimal (,) Debes convertirlos a formato internacional sin separador de miles y con punto decimal. Ejemplo: 1.234,56 -> 1234.56
La salida tiene que ser un JSON estrictamente solo, sin explicacion adicional: {op_type : "long o short", "label_prices" : array de precios.
PROMPT;
    }

    //Necesito que analices esta imagen de un plan de trading de TradingView para identificar el tipo de operacion (LONG o SHORT), los niveles de Take Profit y Stop Loss que están en las etiquetas que se encuentran a la derecha de la misma.
    //Asimismo, hay que identificar el precio de entrada, que es la etiqueta más cercana a la de color azul (con fondo rojo o verede únicamente).
    //La salida tiene que ser un JSON estrictamente solo, sin explicacion adicional: {op_type : "long o short", "entry": precio de entrada, stoploss : <array de stops>, tps: array de tps.

    /**
     * POST JSON a OpenAI Responses API con cURL
     */
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

    /**
     * Si el modelo retorna texto con JSON embebido, lo extrae.
     */
    private function extract_json($text)
    {
        if (preg_match('/\{(?:[^{}]|(?R))*\}/s', $text, $m)) {
            $candidate = json_decode($m[0], true);
            return $candidate;
        }
        return null;
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

        // Detectar la caja verde más a la derecha
        $cajaCoords = $this->findRightmostGreenBox($image, $width, $height);

        if (!$cajaCoords) {
            imagedestroy($image);
            return ['error' => 'No se encontraron cajas verdes'];
        }

        // Calcular coordenadas de crop
        $cropX1 = $cajaCoords['x1'] - 50;
        $cropX2 = $cajaCoords['x2'] + 150;
        $cropY1 = 100;
        $cropY2 = $height - 150;

        $cropWidth = $cropX2 - $cropX1;
        $cropHeight = $cropY2 - $cropY1;

        // Crear imagen croppada
        $croppedImage = imagecreatetruecolor($cropWidth, $cropHeight);
        imagecopy($croppedImage, $image, 0, 0, $cropX1, $cropY1, $cropWidth, $cropHeight);

        // Guardar resultado
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

    private function findRightmostGreenBox($image, $width, $height)
    {
        $targetGreens = [
            ['r' => 197, 'g' => 233, 'b' => 197],
            ['r' => 170, 'g' => 222, 'b' => 170]
        ];
        $tolerance = 20;

        // Variables para la caja más a la derecha
        $cajaX1 = null;
        $cajaX2 = null;
        $cajaY1 = null;

        // Barrido desde Y=100 hasta height-150
        for ($y = 100; $y < $height - 150; $y++) {
            // Definir límite de escaneo
            $limiteScaneo = ($cajaX2 !== null) ? $cajaX2 + 10 : (int)($width * 0.4);

            // Escanear desde width-300 hasta el límite
            for ($x = $width - 300; $x >= $limiteScaneo; $x--) {
                $rgb = imagecolorat($image, $x, $y);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;

                if ($this->isGreenColor($r, $g, $b, $targetGreens, $tolerance)) {
                    // Encontré verde, buscar el rango completo de esta caja
                    $cajaInfo = $this->findBoxRange($image, $x, $y, $targetGreens, $tolerance);

                    if ($cajaInfo !== null) { // Solo si es una caja válida (20+ píxeles verdes)
                        // Actualizar coordenadas
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
        // Primero verificar que hay al menos 20 píxeles verdes consecutivos hacia la derecha
        $greenCount = 0;
        for ($x = $startX; $x >= 0; $x--) {
            $rgb = imagecolorat($image, $x, $y);
            $r = ($rgb >> 16) & 0xFF;
            $g = ($rgb >> 8) & 0xFF;
            $b = $rgb & 0xFF;

            if ($this->isGreenColor($r, $g, $b, $targetGreens, $tolerance)) {
                $greenCount++;
            } else {
                break; // Se acabaron los verdes consecutivos
            }
        }

        // Si no hay al menos 20 píxeles verdes consecutivos, no es una caja válida
        if ($greenCount < 20) {
            return null;
        }

        $x2 = $startX; // El primer verde encontrado
        $x1 = $startX;

        // Ahora SÍ buscar hacia la izquierda hasta encontrar 20 píxeles no-verdes consecutivos
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
