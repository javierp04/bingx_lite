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
                    $analysis_result['analysis_openai'],
                    $analysis_result['analysis_claude'],
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

    private function transformAnalysisData($raw_json)
    {
        // Verificar estructura básica
        if (!isset($raw_json['op_type']) || !isset($raw_json['label_prices'])) {
            return null;
        }

        $prices = $raw_json['label_prices'];
        $op_type = strtoupper(trim($raw_json['op_type']));
        $n = count($prices);

        // Validar mínimo 7 precios (2 SL + 1 Entry + 4+ TPs)
        if (!is_array($prices) || $n < 7) {
            return null;
        }

        // Convertir todos los precios a float
        $prices = array_map('floatval', $prices);

        // LOS PRECIOS VIENEN ORDENADOS VISUALMENTE DE ARRIBA HACIA ABAJO
        // Dinámico: siempre 2 SL + 1 Entry, el resto son TPs (5 o más)

        if ($op_type === 'LONG') {
            // LONG: de arriba a abajo = TPs..., Entry, SL2, SL1
            $sl1 = $prices[$n - 1];       // último (más abajo)
            $sl2 = $prices[$n - 2];       // anteúltimo
            $entry = $prices[$n - 3];     // tercero desde abajo
            $tps = array_reverse(array_slice($prices, 0, $n - 3));  // todo el resto, invertido (TP1 a TPn)

            return [
                'op_type' => $op_type,
                'stoploss' => [$sl1, $sl2],
                'entry' => $entry,
                'tps' => $tps
            ];
        } elseif ($op_type === 'SHORT') {
            // SHORT: de arriba a abajo = SL1, SL2, Entry, TPs...
            $sl1 = $prices[0];            // primero (más arriba)
            $sl2 = $prices[1];            // segundo
            $entry = $prices[2];          // tercero desde arriba
            $tps = array_slice($prices, 3);  // todo el resto (TP1 a TPn)

            return [
                'op_type' => $op_type,
                'stoploss' => [$sl1, $sl2],
                'entry' => $entry,
                'tps' => $tps
            ];
        } else {
            return null; // op_type inválido
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

        // 3. Detectar modo: single o dual
        $ai_mode = $this->input->get('ai_mode') ?: $this->config->item('ai_mode') ?: 'single';

        if ($ai_mode === 'dual') {
            return $this->createTradeAnalysisDual($image_base64, $prompt, $visual_op_type, $cropped_filename);
        } else {
            return $this->createTradeAnalysisSingle($image_base64, $prompt, $visual_op_type, $cropped_filename);
        }
    }

    private function createTradeAnalysisSingle($image_base64, $prompt, $visual_op_type, $cropped_filename)
    {
        $provider = $this->input->get('ai_provider') ?: $this->config->item('ai_provider') ?: 'openai';

        $raw_json = $this->analyzeWithProvider($image_base64, $prompt, $provider);
        if ($raw_json === null) {
            return null;
        }

        // Determinar op_type: primero de la IA (leyendas), luego fallback visual
        $op_type_final = $this->resolveOpType($raw_json, $visual_op_type, $cropped_filename);
        $raw_json['op_type'] = $op_type_final;

        $transformed_json = $this->transformAnalysisData($raw_json);
        if ($transformed_json === null) {
            return null;
        }

        return $transformed_json;
    }

    private function createTradeAnalysisDual($image_base64, $prompt, $visual_op_type, $cropped_filename)
    {
        // === RONDA 1 ===
        $o1 = $this->analyzeWithProvider($image_base64, $prompt, 'openai');
        $c1 = $this->analyzeWithProvider($image_base64, $prompt, 'claude');

        // Si ambas fallaron completamente
        if ($o1 === null && $c1 === null) {
            $this->Log_model->add_log([
                'user_id' => null,
                'action' => 'dual_ai_both_failed',
                'description' => "Ambas IAs fallaron para imagen: {$cropped_filename}"
            ]);
            return null;
        }

        // Si solo una respondió: no validada, sin retry
        if ($o1 === null || $c1 === null) {
            $working_provider = $o1 !== null ? 'openai' : 'claude';
            $working_raw = $o1 !== null ? $o1 : $c1;

            $this->Log_model->add_log([
                'user_id' => null,
                'action' => 'dual_ai_one_failed',
                'description' => "Solo {$working_provider} respondió para imagen: {$cropped_filename}"
            ]);

            return $this->buildDualResult($working_raw, $visual_op_type, $cropped_filename, $o1, $c1, false, "Solo {$working_provider} respondió");
        }

        // Resolver op_type de ambas
        $this->prepareRawWithOpType($o1, $visual_op_type, $cropped_filename . '_O1');
        $this->prepareRawWithOpType($c1, $visual_op_type, $cropped_filename . '_C1');

        // Ronda 1: comparar O1 vs C1
        $match = $this->findMatchingPair([[$o1, $c1]], $cropped_filename, 'R1');

        if ($match) {
            return $this->buildDualResult($match['winner'], $visual_op_type, $cropped_filename, $o1, $c1, true, $match['detail'], $match['matched_prices']);
        }

        // === RONDA 2: retry ===
        $this->Log_model->add_log([
            'user_id' => null,
            'action' => 'dual_ai_retry',
            'description' => "Ronda 1 mismatch, ejecutando retry para imagen: {$cropped_filename}"
        ]);

        $o2 = $this->analyzeWithProvider($image_base64, $prompt, 'openai');
        $c2 = $this->analyzeWithProvider($image_base64, $prompt, 'claude');

        // Resolver op_type de las nuevas respuestas (si existen)
        if ($o2 !== null) {
            $this->prepareRawWithOpType($o2, $visual_op_type, $cropped_filename . '_O2');
        }
        if ($c2 !== null) {
            $this->prepareRawWithOpType($c2, $visual_op_type, $cropped_filename . '_C2');
        }

        // Armar pares cruzados para comparar (solo los que tienen ambas respuestas)
        $cross_pairs = [];
        if ($o1 !== null && $c2 !== null) $cross_pairs[] = [$o1, $c2];
        if ($o2 !== null && $c1 !== null) $cross_pairs[] = [$o2, $c1];
        if ($o2 !== null && $c2 !== null) $cross_pairs[] = [$o2, $c2];

        $match = $this->findMatchingPair($cross_pairs, $cropped_filename, 'R2');

        // Consolidar todas las respuestas para guardar en DB
        $all_openai = array_values(array_filter([$o1, $o2]));
        $all_claude = array_values(array_filter([$c1, $c2]));

        if ($match) {
            return $this->buildDualResult($match['winner'], $visual_op_type, $cropped_filename, $all_openai, $all_claude, true, $match['detail'] . ' (retry)', $match['matched_prices']);
        }

        // Ningún par coincidió → pending_review
        $this->Log_model->add_log([
            'user_id' => null,
            'action' => 'dual_ai_all_mismatch',
            'description' => "Ningún par coincidió después de retry para imagen: {$cropped_filename}"
        ]);

        return $this->buildDualResult($o1, $visual_op_type, $cropped_filename, $all_openai, $all_claude, false, 'Ningún par coincidió después de 2 rondas');
    }

    private function prepareRawWithOpType(&$raw, $visual_op_type, $context)
    {
        $raw['op_type'] = $this->resolveOpType($raw, $visual_op_type, $context);
    }

    private function findMatchingPair($pairs, $cropped_filename, $round_label)
    {
        foreach ($pairs as $idx => $pair) {
            list($rawA, $rawB) = $pair;
            $comparison = $this->compareAnalysisResults($rawA, $rawB);

            $pair_label = "{$round_label}_par{$idx}";
            $this->Log_model->add_log([
                'user_id' => null,
                'action' => 'dual_ai_comparison',
                'description' => "{$pair_label}: " . ($comparison['match'] ? 'MATCH' : 'NO') .
                    " | {$comparison['detail']} | Imagen: {$cropped_filename}"
            ]);

            if ($comparison['match']) {
                // Usar rawA como ganador, con precios truncados al subset validado si aplica
                $winner = $rawA;
                if (isset($comparison['matched_prices'])) {
                    $winner['label_prices'] = $comparison['matched_prices'];
                }
                return [
                    'winner' => $winner,
                    'detail' => $comparison['detail'],
                    'matched_prices' => isset($comparison['matched_prices']) ? $comparison['matched_prices'] : $rawA['label_prices']
                ];
            }
        }
        return null;
    }

    private function buildDualResult($raw_winner, $visual_op_type, $cropped_filename, $openai_data, $claude_data, $validated, $detail, $matched_prices = null)
    {
        // Si matched_prices fue proporcionado, usarlo en el winner para la transformación
        if ($matched_prices !== null && $raw_winner !== null) {
            $raw_winner['label_prices'] = $matched_prices;
        }

        $transformed = null;
        if ($raw_winner !== null) {
            if (!isset($raw_winner['op_type'])) {
                $raw_winner['op_type'] = $this->resolveOpType($raw_winner, $visual_op_type, $cropped_filename);
            }
            $transformed = $this->transformAnalysisData($raw_winner);
        }

        return [
            'analysis' => $transformed,
            'ai_validated' => $validated,
            'analysis_openai' => json_encode($openai_data),
            'analysis_claude' => json_encode($claude_data),
            'discrepancy' => $validated ? null : $detail,
            'dual_mode' => true
        ];
    }

    private function analyzeWithProvider($image_base64, $prompt, $provider)
    {
        if ($provider === 'claude') {
            $response = $this->call_claude_api($image_base64, $prompt);
        } else {
            $response = $this->call_openai_api($image_base64, $prompt);
        }

        if (isset($response['error'])) {
            $this->Log_model->add_log([
                'user_id' => null,
                'action' => 'ai_analysis_error',
                'description' => 'AI analysis failed with ' . $provider . ': ' . json_encode($response['error'])
            ]);
            return null;
        }

        $text = $this->extract_ai_response($response, $provider);
        if (!$text) {
            return null;
        }

        $raw_json = json_decode($text, true);
        if ($raw_json === null) {
            $json_candidate = $this->extract_json($text);
            if ($json_candidate !== null) {
                $raw_json = $json_candidate;
            } else {
                return null;
            }
        }

        if (empty($raw_json) || (count($raw_json) == 0)) {
            return null;
        }

        return $raw_json;
    }

    private function resolveOpType($raw_json, $visual_op_type, $context)
    {
        if (isset($raw_json['op_type']) && in_array(strtoupper($raw_json['op_type']), ['LONG', 'SHORT'])) {
            $op_type_final = strtoupper($raw_json['op_type']);
            $detection_method = 'IA (leyendas)';
            error_log('[TradeReader] op_type detectado por IA (leyendas): ' . $op_type_final . ' [' . $context . ']');
        } else {
            $op_type_final = $visual_op_type;
            $detection_method = 'Visual (cajas)';
            error_log('[TradeReader] op_type por fallback visual (cajas): ' . $op_type_final . ' [' . $context . ']');
        }

        $this->Log_model->add_log([
            'user_id' => null,
            'action' => 'op_type_detection',
            'description' => "Método: {$detection_method} | Resultado: {$op_type_final} | Contexto: {$context}"
        ]);

        return $op_type_final;
    }

    private function compareAnalysisResults($rawA, $rawB)
    {
        $op_a = isset($rawA['op_type']) ? strtoupper($rawA['op_type']) : 'UNKNOWN';
        $op_b = isset($rawB['op_type']) ? strtoupper($rawB['op_type']) : 'UNKNOWN';

        if ($op_a !== $op_b) {
            return [
                'match' => false,
                'detail' => "op_type mismatch: A={$op_a} vs B={$op_b}"
            ];
        }

        $prices_a = isset($rawA['label_prices']) ? array_map('floatval', $rawA['label_prices']) : [];
        $prices_b = isset($rawB['label_prices']) ? array_map('floatval', $rawB['label_prices']) : [];
        $count_a = count($prices_a);
        $count_b = count($prices_b);

        if ($count_a < 7 || $count_b < 7) {
            return [
                'match' => false,
                'detail' => "Insuficientes precios para comparar: A={$count_a}, B={$count_b} (mín 7)"
            ];
        }

        $required = 7;

        if ($op_a === 'LONG') {
            // LONG: comparar las últimas 7 (entry + stops + TPs cercanos)
            $tail_a = array_slice($prices_a, -$required);
            $tail_b = array_slice($prices_b, -$required);

            for ($i = 0; $i < $required; $i++) {
                if ($tail_a[$i] !== $tail_b[$i]) {
                    $pos_a = $count_a - $required + $i + 1;
                    $pos_b = $count_b - $required + $i + 1;
                    return [
                        'match' => false,
                        'detail' => "LONG tail#{$i}: A[{$pos_a}]={$tail_a[$i]} vs B[{$pos_b}]={$tail_b[$i]}"
                    ];
                }
            }

            // Match: usar el set completo del proveedor con más precios
            $matched = $count_a >= $count_b ? $prices_a : $prices_b;

            return [
                'match' => true,
                'detail' => "LONG match: últimas {$required} coinciden (A={$count_a}, B={$count_b}), usando set completo de " . count($matched),
                'matched_prices' => $matched
            ];
        } else {
            // SHORT: comparar las primeras 7 (SL1, SL2, entry + TPs cercanos)
            $head_a = array_slice($prices_a, 0, $required);
            $head_b = array_slice($prices_b, 0, $required);

            for ($i = 0; $i < $required; $i++) {
                if ($head_a[$i] !== $head_b[$i]) {
                    $pos = $i + 1;
                    return [
                        'match' => false,
                        'detail' => "SHORT head#{$pos}: A={$head_a[$i]} vs B={$head_b[$i]}"
                    ];
                }
            }

            // Match: usar el set completo del proveedor con más precios
            $matched = $count_a >= $count_b ? $prices_a : $prices_b;

            return [
                'match' => true,
                'detail' => "SHORT match: primeras {$required} coinciden (A={$count_a}, B={$count_b}), usando set completo de " . count($matched),
                'matched_prices' => $matched
            ];
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
            "model" => "gpt-4o",
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
