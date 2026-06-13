<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Ai_analysis — motor de analisis de señales con IA (single + consenso dual).
 *
 * Saca de TradeReader (controller) toda la logica de "hablar con la IA y validar":
 * dispatch al proveedor, consenso dual (2 IAs + retry), comparacion de precios,
 * extraccion y parseo. NO sabe nada del request HTTP ni de settings: recibe la imagen,
 * el prompt, el op_type visual y el/los proveedor(es) como parametros. El controller
 * resuelve esos parametros (modo, par, override de provider) y delega aca.
 *
 * Depende de la library Ai_provider (definicion de cada IA) y del helper
 * signal_analysis (transform_analysis_data). Loguea via Log_model.
 */
class Ai_analysis
{
    /** @var CI_Controller */
    private $CI;

    // Precios "ancla" que deben coincidir entre las dos IAs para validar el consenso:
    // entry + 2 stop-loss + 4 take-profits cercanos. En LONG estan al FINAL del array; en SHORT al INICIO.
    private const DUAL_ANCHOR_PRICES = 7;

    public function __construct()
    {
        $this->CI = &get_instance();
        $this->CI->load->library('ai_provider');
        $this->CI->load->helper('signal_analysis');
        $this->CI->load->model('Log_model');
    }

    /**
     * Analisis single: una sola IA. Devuelve el JSON transformado o null si fallo.
     */
    public function single($image_base64, $prompt, $visual_op_type, $cropped_filename, $provider)
    {
        $raw_json = $this->analyzeWithProvider($image_base64, $prompt, $provider);
        if ($raw_json === null) {
            return null;
        }

        // Determinar op_type: primero de la IA (leyendas), luego fallback visual
        $op_type_final = $this->resolveOpType($raw_json, $visual_op_type, $cropped_filename);
        $raw_json['op_type'] = $op_type_final;

        $transformed_json = transform_analysis_data($raw_json);
        if ($transformed_json === null) {
            return null;
        }

        return $transformed_json;
    }

    /**
     * Analisis dual: dos IAs (providerA + providerB) con validacion por consenso y 1 retry.
     * Devuelve la estructura de buildDualResult (analysis + ai_validated + analysis_by_provider...).
     */
    public function dual($image_base64, $prompt, $visual_op_type, $cropped_filename, $providerA, $providerB)
    {
        $a1 = $this->analyzeWithProvider($image_base64, $prompt, $providerA);
        $b1 = $this->analyzeWithProvider($image_base64, $prompt, $providerB);

        // Si ambas fallaron completamente
        if ($a1 === null && $b1 === null) {
            $this->CI->Log_model->add_log([
                'user_id' => null,
                'action' => 'dual_ai_both_failed',
                'description' => "Ambas IAs ({$providerA}+{$providerB}) fallaron para imagen: {$cropped_filename}"
            ]);
            return null;
        }

        // Si solo una respondió: no validada, sin retry
        if ($a1 === null || $b1 === null) {
            $working_provider = $a1 !== null ? $providerA : $providerB;
            $working_raw = $a1 !== null ? $a1 : $b1;

            $this->CI->Log_model->add_log([
                'user_id' => null,
                'action' => 'dual_ai_one_failed',
                'description' => "Solo {$working_provider} respondió para imagen: {$cropped_filename}"
            ]);

            return $this->buildDualResult($working_raw, $visual_op_type, $cropped_filename, [$providerA => $a1, $providerB => $b1], false, "Solo {$working_provider} respondió");
        }

        // Resolver op_type de ambas
        $this->prepareRawWithOpType($a1, $visual_op_type, $cropped_filename . '_A1');
        $this->prepareRawWithOpType($b1, $visual_op_type, $cropped_filename . '_B1');

        // Ronda 1: comparar A1 vs B1
        $match = $this->findMatchingPair([[$a1, $b1]], $cropped_filename, 'R1');

        if ($match) {
            return $this->buildDualResult($match['winner'], $visual_op_type, $cropped_filename, [$providerA => $a1, $providerB => $b1], true, $match['detail'], $match['matched_prices']);
        }

        // === RONDA 2: retry ===
        $this->CI->Log_model->add_log([
            'user_id' => null,
            'action' => 'dual_ai_retry',
            'description' => "Ronda 1 mismatch, ejecutando retry para imagen: {$cropped_filename}"
        ]);

        $a2 = $this->analyzeWithProvider($image_base64, $prompt, $providerA);
        $b2 = $this->analyzeWithProvider($image_base64, $prompt, $providerB);

        // Resolver op_type de las nuevas respuestas (si existen)
        if ($a2 !== null) {
            $this->prepareRawWithOpType($a2, $visual_op_type, $cropped_filename . '_A2');
        }
        if ($b2 !== null) {
            $this->prepareRawWithOpType($b2, $visual_op_type, $cropped_filename . '_B2');
        }

        // Armar pares cruzados para comparar (solo los que tienen ambas respuestas)
        $cross_pairs = [];
        if ($a1 !== null && $b2 !== null) $cross_pairs[] = [$a1, $b2];
        if ($a2 !== null && $b1 !== null) $cross_pairs[] = [$a2, $b1];
        if ($a2 !== null && $b2 !== null) $cross_pairs[] = [$a2, $b2];

        $match = $this->findMatchingPair($cross_pairs, $cropped_filename, 'R2');

        // Consolidar todas las respuestas para guardar en DB
        $all_a = array_values(array_filter([$a1, $a2]));
        $all_b = array_values(array_filter([$b1, $b2]));

        if ($match) {
            return $this->buildDualResult($match['winner'], $visual_op_type, $cropped_filename, [$providerA => $all_a, $providerB => $all_b], true, $match['detail'] . ' (retry)', $match['matched_prices']);
        }

        // Ningún par coincidió → pending_review
        $this->CI->Log_model->add_log([
            'user_id' => null,
            'action' => 'dual_ai_all_mismatch',
            'description' => "Ningún par coincidió después de retry para imagen: {$cropped_filename}"
        ]);

        return $this->buildDualResult($a1, $visual_op_type, $cropped_filename, [$providerA => $all_a, $providerB => $all_b], false, 'Ningún par coincidió después de 2 rondas');
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
            $this->CI->Log_model->add_log([
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

    /**
     * Construye el resultado del analisis dual.
     * @param array      $by_provider     Mapa [proveedor => respuesta_cruda] de las IAs comparadas
     * @param bool       $validated       true si las IAs coincidieron en los precios ancla
     * @param string     $detail          Detalle del match/mismatch (logs + campo discrepancy)
     * @param array|null $matched_prices  Subset de precios validados a aplicar al winner
     */
    private function buildDualResult($raw_winner, $visual_op_type, $cropped_filename, $by_provider, $validated, $detail, $matched_prices = null)
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
            $transformed = transform_analysis_data($raw_winner);
        }

        return [
            'analysis' => $transformed,
            'ai_validated' => $validated,
            'analysis_by_provider' => array_map('json_encode', $by_provider),
            'discrepancy' => $validated ? null : $detail,
            'dual_mode' => true
        ];
    }

    private function analyzeWithProvider($image_base64, $prompt, $provider)
    {
        // El registry del proveedor define URL, headers y forma del payload.
        $req = $this->CI->ai_provider->build_request($provider, $prompt, $image_base64);
        if (isset($req['error'])) {
            $this->CI->Log_model->add_log([
                'user_id' => null,
                'action' => 'ai_analysis_error',
                'description' => 'AI request build failed (' . $provider . '): ' . $req['error']
            ]);
            return null;
        }

        $response = $this->http_post_json($req['url'], $req['headers'], $req['payload']);

        if (isset($response['error'])) {
            $this->CI->Log_model->add_log([
                'user_id' => null,
                'action' => 'ai_analysis_error',
                'description' => 'AI analysis failed with ' . $provider . ': ' . json_encode($response['error'])
            ]);
            return null;
        }

        $text = $this->CI->ai_provider->extract_text($provider, $response);
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

        if (empty($raw_json)) {
            return null;
        }

        return $raw_json;
    }

    private function resolveOpType($raw_json, $visual_op_type, $context)
    {
        if (isset($raw_json['op_type']) && in_array(strtoupper($raw_json['op_type']), ['LONG', 'SHORT'])) {
            $op_type_final = strtoupper($raw_json['op_type']);
            $detection_method = 'IA (leyendas)';
        } else {
            $op_type_final = $visual_op_type;
            $detection_method = 'Visual (cajas)';
        }

        $this->CI->Log_model->add_log([
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
            return ['match' => false, 'detail' => "op_type mismatch: A={$op_a} vs B={$op_b}"];
        }

        $prices_a = isset($rawA['label_prices']) ? array_map('floatval', $rawA['label_prices']) : [];
        $prices_b = isset($rawB['label_prices']) ? array_map('floatval', $rawB['label_prices']) : [];
        $count_a = count($prices_a);
        $count_b = count($prices_b);
        $required = self::DUAL_ANCHOR_PRICES;

        if ($count_a < $required || $count_b < $required) {
            return ['match' => false, 'detail' => "Insuficientes precios para comparar: A={$count_a}, B={$count_b} (mín {$required})"];
        }

        // LONG mira la cola del array; SHORT la cabeza. Resto de la logica identico.
        $isLong   = ($op_a === 'LONG');
        $anchor_a = $isLong ? array_slice($prices_a, -$required) : array_slice($prices_a, 0, $required);
        $anchor_b = $isLong ? array_slice($prices_b, -$required) : array_slice($prices_b, 0, $required);

        for ($i = 0; $i < $required; $i++) {
            if ($anchor_a[$i] !== $anchor_b[$i]) {
                $pos_a = $isLong ? ($count_a - $required + $i + 1) : ($i + 1);
                $pos_b = $isLong ? ($count_b - $required + $i + 1) : ($i + 1);
                return [
                    'match' => false,
                    'detail' => "{$op_a} ancla#" . ($i + 1) . ": A[{$pos_a}]={$anchor_a[$i]} vs B[{$pos_b}]={$anchor_b[$i]}"
                ];
            }
        }

        // Match: usar el set completo del proveedor con mas precios
        $matched = $count_a >= $count_b ? $prices_a : $prices_b;

        return [
            'match' => true,
            'detail' => "{$op_a} match: {$required} ancla coinciden (A={$count_a}, B={$count_b}), usando set completo de " . count($matched),
            'matched_prices' => $matched
        ];
    }

    private function http_post_json($url, $headers, $payload)
    {
        $ch = curl_init($url);
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
}
