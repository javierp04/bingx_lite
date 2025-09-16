<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * API Controller para MetaTrader EA
 * Maneja las comunicaciones entre el EA y el sistema
 */
class Api extends CI_Controller
{

    private $tz = 'America/Argentina/Buenos_Aires'; // UTC-3

    public function __construct()
    {
        parent::__construct();
        $this->load->model('Telegram_signals_model');
        $this->load->model('User_tickers_model');
    }

    /**
     * API ENDPOINT para EA: obtener señal disponible por user_id y ticker
     * GET /api/signals/{user_id}/{ticker_symbol}
     */
    public function get_signals($user_id, $ticker_symbol = null)
    {
        if (!is_numeric($user_id)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid user_id']);
            return;
        }

        // Si no se especifica ticker, obtener de query params o headers
        if (!$ticker_symbol) {
            $ticker_symbol = $this->input->get('ticker') ?: $this->input->get_request_header('X-Ticker-Symbol');
        }

        if (empty($ticker_symbol)) {
            http_response_code(400);
            echo json_encode(['error' => 'Ticker symbol required']);
            return;
        }

        try {
            // ✨ NUEVO: Obtener señal disponible (available) más reciente para este ticker del usuario
            $signal = $this->Telegram_signals_model->get_available_signals_for_user($user_id, $ticker_symbol);

            // Si no hay señal disponible
            if (!$signal) {
                http_response_code(200);
                echo json_encode([
                    'success' => true,
                    'signal' => null,
                    'message' => 'No new signals available'
                ]);
                return;
            }

            // ✨ NUEVO: Marcar señal como claimed
            if ($this->Telegram_signals_model->claim_user_signal($signal->id, $user_id)) {
                $analysis_data = json_decode($signal->analysis_data, true);

                $response_signal = array(
                    'user_signal_id' => (int)$signal->id,
                    'telegram_signal_id' => (int)$signal->telegram_signal_id,
                    'ticker_symbol' => $signal->ticker_symbol,
                    'mt_ticker' => $signal->mt_ticker,
                    'analysis' => $analysis_data,
                    'tradingview_url' => $signal->tradingview_url,
                    'created_at' => $signal->created_at
                );

                http_response_code(200);
                echo json_encode([
                    'success' => true,
                    'signal' => $response_signal
                ]);
            } else {
                http_response_code(200);
                echo json_encode([
                    'success' => true,
                    'signal' => null,
                    'message' => 'Signal was claimed by another process'
                ]);
            }
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Internal server error']);
        }
    }

    /**
     * API ENDPOINT para EA: reportar ejecución de trade
     * POST /api/signals/{user_signal_id}/execution
     */
    public function update_execution($user_signal_id)
    {
        $execution_data = json_decode(file_get_contents("php://input"), true);

        if (!is_numeric($user_signal_id) || !$execution_data) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid parameters']);
            return;
        }

        try {
            $status = isset($execution_data['success']) && $execution_data['success'] ? 'executed' : 'failed_execution';
            if ($status == 'executed') {

                if ($this->Telegram_signals_model->update_user_signal($user_signal_id, $status, $execution_data)) {
                    http_response_code(200);
                    echo json_encode(['success' => true, 'status' => $status]);
                } else {
                    http_response_code(404);
                    echo json_encode(['error' => 'Signal not found']);
                }
            }
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Internal server error']);
        }
    }

    public function fut_price($symbol = null)
    {
        if ($symbol == null) {
            http_response_code(400);
            echo json_encode(['error' => 'Symbol parameter is required']);
            return;
        }
        $symbol = strtoupper($symbol) . "=F"; // Append =F for futures

        // 1) URL (query1, un solo símbolo, encodeado)
        $symEnc = rawurlencode($symbol);
        $url = "https://query1.finance.yahoo.com/v7/finance/spark?symbols={$symEnc}&range=1d&interval=1m";

        // 2) HTTP GET simple
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_ENCODING       => 'gzip',
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT        => 8,
            CURLOPT_HTTPHEADER     => ['Accept: application/json'],
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (CI3)'
        ]);
        $body = curl_exec($ch);
        $code = (int) (curl_getinfo($ch)['http_code'] ?? 0);
        curl_close($ch);

        // 3) Defaults minimal por si algo falla
        $lastClose = null;
        $ts = null;

        if ($code === 200 && $body) {
            $j = json_decode($body, true);

            // Formato anidado: spark.result[0].response[0]
            $resp0 = $j['spark']['result'][0]['response'][0] ?? null;
            if ($resp0) {
                // Preferir close directo; si no está, usar indicators.quote[0].close
                $closes = isset($resp0['close']) ? $resp0['close'] : ($resp0['indicators']['quote'][0]['close'] ?? []);
                $times  = $resp0['timestamp'] ?? [];

                // Penúltima no-nula (última vela CERRADA)
                $n = count($closes);
                if ($n > 0) {
                    $idxLast = null;
                    for ($i = $n - 1; $i >= 0; $i--) {
                        if ($closes[$i] !== null) {
                            $idxLast = $i;
                            break;
                        }
                    }
                    if ($idxLast !== null) {
                        $idxPrev = null;
                        for ($k = $idxLast - 1; $k >= 0; $k--) {
                            if ($closes[$k] !== null) {
                                $idxPrev = $k;
                                break;
                            }
                        }
                        $idx = ($idxPrev !== null) ? $idxPrev : $idxLast;
                        $lastClose = $closes[$idx] ?? null;
                        $ts        = $times[$idx]  ?? null;
                    }
                }
            }
        }

        // 4) Salida minimal
        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode([
                'last_close' => $lastClose,
                'ts_epoch'   => $ts,
                'ts_local'   => $this->_epoch_to_local($ts),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function _epoch_to_local($epoch)
    {
        if (!$epoch) return null;
        try {
            $dt = new DateTime('@' . $epoch);           // UTC
            $dt->setTimezone(new DateTimeZone($this->tz)); // UTC-3
            return $dt->format('Y-m-d H:i:s');
        } catch (Exception $e) {
            return null;
        }
    }
}
