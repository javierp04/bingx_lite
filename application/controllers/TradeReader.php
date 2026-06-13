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
        $this->load->helper('signal_analysis');
    }

    /**
     * Webhook principal de Telegram
     * Procesa mensajes y genera señales automáticamente
     */
    public function generateSignalFromTelegram()
    {
        $update = file_get_contents("php://input");
        error_log('[TradeReader] RAW INPUT: ' . substr($update, 0, 500));

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

            // PASO 4: Ejecutar análisis IA con información de las cajas verde y roja
            $cropped_filename = 'cropped-' . pathinfo($image_filename, PATHINFO_FILENAME);
            $cajaCoords = $crop_result['box_coords'];
            $redCoords = $crop_result['red_coords'];
            $imageHeight = $crop_result['image_height'];
            $analysis_result = $this->createTradeAnalysis($cropped_filename, $cajaCoords, $imageHeight, $redCoords, $ticker_symbol);

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

            // PASO 5: Guardar análisis según modo (single o dual)
            $is_dual = isset($analysis_result['dual_mode']) && $analysis_result['dual_mode'];

            if ($is_dual) {
                $final_analysis = $analysis_result['analysis'];
                $ai_validated = $analysis_result['ai_validated'];

                $this->Telegram_signals_model->complete_signal_dual(
                    $signal_id,
                    $final_analysis ? json_encode($final_analysis) : null,
                    $analysis_result['analysis_by_provider'],
                    $ai_validated
                );

                if (!$ai_validated) {
                    // No coinciden: NO distribuir, logear discrepancia
                    $this->Log_model->add_log([
                        'user_id' => null,
                        'action' => 'dual_ai_mismatch',
                        'description' => 'Signal ID: ' . $signal_id . ' - IAs no coinciden: ' . $analysis_result['discrepancy']
                    ]);
                    return;
                }

                // Coinciden: proceder a distribuir
                $users_count = $this->Telegram_signals_model->create_user_signals_for_ticker($signal_id, $ticker_symbol);

                $this->Log_model->add_log([
                    'user_id' => null,
                    'action' => 'telegram_pipeline_completed',
                    'description' => 'Signal pipeline completed (DUAL validated) for ID: ' . $signal_id .
                        '. Analysis: ' . json_encode($final_analysis) . '. Distributed to ' . $users_count . ' users.'
                ]);
            } else {
                // Modo single: comportamiento original
                $this->Telegram_signals_model->complete_signal($signal_id, json_encode($analysis_result));

                $users_count = $this->Telegram_signals_model->create_user_signals_for_ticker($signal_id, $ticker_symbol);

                $this->Log_model->add_log([
                    'user_id' => null,
                    'action' => 'telegram_pipeline_completed',
                    'description' => 'Signal pipeline completed for ID: ' . $signal_id . '. Analysis: ' . json_encode($analysis_result) . '. Distributed to ' . $users_count . ' users.'
                ]);
            }
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

    /**
     * Análisis IA que devuelve JSON transformado o null si falló
     * Soporta OpenAI y Claude según configuración
     *
     * @param string     $cropped_filename Nombre del archivo cropped
     * @param array      $cajaCoords       Coordenadas de la caja verde (con y_min y y_max)
     * @param int        $imageHeight      Altura de la imagen original
     * @param array|null $redCoords        Coordenadas de la caja roja (o null)
     */
    private function createTradeAnalysis($cropped_filename, $cajaCoords, $imageHeight, $redCoords = null, $ticker_symbol = '')
    {
        $in_path = "uploads/trades/" . $cropped_filename . ".png";

        if (!file_exists($in_path)) {
            return null;
        }

        // 1. Detectar tipo de operación visual (fallback)
        $visual_op_type = $this->detectOperationType($cajaCoords, $imageHeight, $redCoords);

        // 2. Preparar imagen: upscale 2x para mejorar lectura de dígitos por la IA
        $src = imagecreatefrompng($in_path);
        $w = imagesx($src);
        $h = imagesy($src);
        $dst = imagecreatetruecolor($w * 2, $h * 2);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $w * 2, $h * 2, $w, $h);
        imagedestroy($src);

        ob_start();
        imagepng($dst);
        $image_base64 = base64_encode(ob_get_clean());
        imagedestroy($dst);

        $prompt = $this->build_prompt2($ticker_symbol);

        // 3. Detectar modo: single o dual (settings → config → default)
        $ai_mode = $this->input->get('ai_mode') ?: $this->settingGet('ai_mode', 'single');

        // La logica de IA (dispatch + consenso) vive en la library Ai_analysis.
        // Este controller solo resuelve modo y proveedor(es) y delega.
        $this->load->library('ai_analysis');

        if ($ai_mode === 'dual') {
            list($providerA, $providerB) = $this->getProviderPair();
            return $this->ai_analysis->dual($image_base64, $prompt, $visual_op_type, $cropped_filename, $providerA, $providerB);
        }

        // Single usa el proveedor A de AI Settings, salvo override por query (?ai_provider=)
        $provider = $this->input->get('ai_provider') ?: $this->getProviderPair()[0];
        return $this->ai_analysis->single($image_base64, $prompt, $visual_op_type, $cropped_filename, $provider);
    }

    // Helper: lee un setting de system_settings, con fallback a config.php y a un default.
    private function settingGet($key, $default = null)
    {
        $this->load->model('Setting_model');
        return $this->Setting_model->resolve($key, $default);
    }

    // Devuelve el par de proveedores del consenso [A, B].
    private function getProviderPair()
    {
        return [
            $this->settingGet('ai_provider_a', 'gemini'),
            $this->settingGet('ai_provider_b', 'openai')
        ];
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

        // Detectar caja roja ANTES de destruir la imagen
        $redCoords = $this->findRedBox($image, $width, $height, $cajaCoords);

        $cropX1 = max(0, min($cajaCoords['x1'] - 5, $cajaCoords['x2'] - 250));
        $cropX2 = $cajaCoords['x2'] + 150;
        $cropY1 = 40;
        $cropY2 = $height - 120;

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
                'box_coords' => $cajaCoords,   // Coordenadas de la caja verde detectada
                'red_coords' => $redCoords,     // Coordenadas de la caja roja (o null)
                'image_height' => $height       // Altura de la imagen original
            ];
        } else {
            return ['error' => 'No se pudo guardar la imagen croppada'];
        }
    }
    private function build_prompt2($ticker_symbol = '')
    {
        $ticker_line = '';
        if (!empty($ticker_symbol)) {
            $ticker_line = "\nCONTEXTO: El activo es {$ticker_symbol}. Usa esto para interpretar correctamente el rango de precios y el formato numérico.\n";
        }

        return <<<PROMPT
La imagen es un gráfico de TradingView con un plan de operación. El plan tiene una caja coloreada (zona de operación) con etiquetas de precio alineadas verticalmente a su derecha.
{$ticker_line}
Extrae TODOS los precios de esas etiquetas Y determina el tipo de operación.

REGLAS:
1. Extrae TODAS las etiquetas de precio que estén a la derecha de la caja coloreada, sin excepción. Pueden ser 8, 9, 10 o más.
2. Solo las etiquetas del plan de operación: están alineadas verticalmente a la derecha de la caja coloreada
3. Ignorar etiquetas del broker, Fibonacci, indicadores, eje de precios del gráfico
4. Si una etiqueta está claramente aislada o separada del grupo principal (no alineada con las demás a la derecha de la caja), no la incluyas
5. Orden: de ARRIBA hacia ABAJO visualmente
6. FORMATOS DE PRECIO — convertir TODOS a número decimal estándar con punto:
   - Punto como separador de MILES (ej: 77.170 → 77170, 1.234 → 1234): cuando hay exactamente 3 dígitos después del punto. Común en activos con precios altos (BTC, índices, etc.)
   - Formato europeo con coma decimal: 1.234,56 → 1234.56
   - Apóstrofe como separador decimal (futuros): 1174'6 → 1174.6, 1050'4 → 1050.4
   - Guión como separador decimal (futuros): 1174-6 → 1174.6
   - Punto decimal normal (ej: 1.2345, 0.6780): respetar tal cual
   - En caso de ambigüedad, usa el contexto del activo para determinar el rango razonable
7. Lee cada número con máxima precisión. Distingue bien entre dígitos similares (3 vs 1, 6 vs 8, etc.)

TIPO DE OPERACIÓN:
- Si encuentras al menos UNA leyenda/etiqueta que diga "zona a superar" → op_type = "LONG"
- Si encuentras al menos UNA leyenda/etiqueta que diga "zona a perforar" → op_type = "SHORT"
- Si no encuentras ninguna de estas leyendas → NO incluyas el campo op_type en la respuesta

SALIDA:
{"label_prices": [precio1, precio2, ...], "op_type": "LONG" o "SHORT"}

Si hay menos de 8 etiquetas, devuelve {}. Solo el JSON, sin texto adicional.
PROMPT;
    }


    // POST JSON generico para las APIs de IA. Lo unico que cambia entre proveedores
    // son los headers (auth); el resto del manejo (timeout, errores, status) es identico.
    private function findRightmostGreenBox($image, $width, $height)
    {
        $targetGreens = [
            ['r' => 197, 'g' => 233, 'b' => 197],
            ['r' => 170, 'g' => 222, 'b' => 170],
            ['r' => 170, 'g' => 222, 'b' => 134]
        ];
        $tolerance = 15;
        $gapThreshold = 40;  // Píxeles no-verdes consecutivos para considerar fin de caja

        $cajaX1 = null;
        $cajaX2 = null;
        $cajaY1 = null;
        $cajaYMin = null;  // Primera Y donde encontró verde
        $cajaYMax = null;  // Última Y donde encontró verde

        for ($y = 100; $y < $height - 150; $y += 3) {
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
     * Detecta si la operación es LONG o SHORT comparando posición verde vs roja
     *
     * LÓGICA:
     * - Verde ARRIBA, Rojo ABAJO → LONG (TPs arriba, SL abajo)
     * - Rojo ARRIBA, Verde ABAJO → SHORT (SL arriba, TPs abajo)
     * - Si no encuentra rojo, fallback a verde vs midpoint de imagen
     *
     * @param array      $cajaCoords  Coordenadas de la caja verde (con y_min, y_max)
     * @param int        $imageHeight Altura total de la imagen
     * @param array|null $redCoords   Coordenadas de la caja roja (con y_min, y_max) o null
     * @return string 'LONG' o 'SHORT'
     */
    private function detectOperationType($cajaCoords, $imageHeight, $redCoords = null)
    {
        // Si tenemos coordenadas de la caja roja, comparar posiciones
        if ($redCoords !== null) {
            $green_center_y = ($cajaCoords['y_min'] + $cajaCoords['y_max']) / 2;
            $red_center_y = ($redCoords['y_min'] + $redCoords['y_max']) / 2;

            if ($red_center_y > $green_center_y) {
                // Rojo DEBAJO del verde → LONG
                return 'LONG';
            } else {
                // Rojo ENCIMA del verde → SHORT
                return 'SHORT';
            }
        }

        // Fallback: verde vs midpoint de la imagen
        $green_center_y = ($cajaCoords['y_min'] + $cajaCoords['y_max']) / 2;
        $image_midpoint_y = $imageHeight / 2;

        if ($green_center_y < $image_midpoint_y) {
            return 'LONG';
        } else {
            return 'SHORT';
        }
    }

    /**
     * Busca la caja roja/rosa en el mismo rango X que la caja verde
     * La caja roja es la zona de Stop Loss en los planes de TradingView
     *
     * @param resource $image       Recurso GD de la imagen
     * @param int      $width       Ancho de la imagen
     * @param int      $height      Altura de la imagen
     * @param array    $greenCoords Coordenadas de la caja verde para acotar búsqueda en X
     * @return array|null ['y_min' => int, 'y_max' => int] o null si no encontró
     */
    private function findRedBox($image, $width, $height, $greenCoords)
    {
        $targetReds = [
            ['r' => 244, 'g' => 204, 'b' => 204],  // Rosa claro
            ['r' => 234, 'g' => 153, 'b' => 153],  // Rosa medio
            ['r' => 255, 'g' => 182, 'b' => 182],  // Rosa salmón
            ['r' => 239, 'g' => 154, 'b' => 154],  // Rosa
            ['r' => 255, 'g' => 168, 'b' => 168],  // Rojo claro
            ['r' => 213, 'g' => 134, 'b' => 134],  // Rojo apagado
            ['r' => 250, 'g' => 200, 'b' => 200],  // Rosa muy claro
            ['r' => 220, 'g' => 160, 'b' => 160],  // Rosa oscuro
        ];
        $tolerance = 25;
        $minRedPixelsPerRow = 10;  // Mínimo de píxeles rojos en una fila para contar

        // Escanear en el mismo rango X que la caja verde (con margen)
        $scanX1 = max(0, $greenCoords['x1'] - 20);
        $scanX2 = min($width - 1, $greenCoords['x2'] + 20);

        $redYMin = null;
        $redYMax = null;

        for ($y = 100; $y < $height - 150; $y++) {
            // Saltar el rango Y de la caja verde (buscar fuera de ella)
            if ($y >= $greenCoords['y_min'] - 5 && $y <= $greenCoords['y_max'] + 5) {
                continue;
            }

            $redCount = 0;
            for ($x = $scanX1; $x <= $scanX2; $x += 3) {  // Saltar de 3 en 3 para velocidad
                $rgb = imagecolorat($image, $x, $y);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;

                if ($this->isRedColor($r, $g, $b, $targetReds, $tolerance)) {
                    $redCount++;
                }
            }

            if ($redCount >= $minRedPixelsPerRow) {
                if ($redYMin === null) {
                    $redYMin = $y;
                }
                $redYMax = $y;
            }
        }

        if ($redYMin !== null && $redYMax !== null) {
            return [
                'y_min' => $redYMin,
                'y_max' => $redYMax
            ];
        }

        return null;
    }

    /**
     * Verifica si un color RGB coincide con los colores rojos/rosa objetivo
     */
    private function isRedColor($r, $g, $b, $targetReds, $tolerance)
    {
        foreach ($targetReds as $target) {
            if (
                abs($r - $target['r']) <= $tolerance &&
                abs($g - $target['g']) <= $tolerance &&
                abs($b - $target['b']) <= $tolerance
            ) {
                return true;
            }
        }
        return false;
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
