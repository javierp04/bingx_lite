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
        $this->load->model('Trade_model');
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

                // Aplanar la estructura para el EA
                $response = array(
                    'success' => true,
                    'user_signal_id' => (int)$signal->id,
                    'telegram_signal_id' => (int)$signal->telegram_signal_id,
                    'ticker_symbol' => $signal->ticker_symbol,
                    'mt_ticker' => $signal->mt_ticker,
                    'tradingview_url' => $signal->tradingview_url,
                    'created_at' => $signal->created_at
                );

                // Agregar campos de analysis directamente en el nivel raíz
                if (is_array($analysis_data)) {
                    $response = array_merge($response, $analysis_data);
                }

                http_response_code(200);
                echo json_encode($response);
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
     * NUEVO: API ENDPOINT para reportar apertura de posición
     * POST /api/signals/{user_signal_id}/open
     */
    public function report_open($user_signal_id)
    {
        $open_data = json_decode(file_get_contents("php://input"), true);

        if (!is_numeric($user_signal_id) || !$open_data) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid parameters']);
            return;
        }

        try {
            if ($this->Telegram_signals_model->report_open($user_signal_id, $open_data)) {
                http_response_code(200);
                echo json_encode(['success' => true, 'status' => 'opened']);
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'Signal not found']);
            }
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Internal server error']);
        }
    }

    /**
     * NUEVO: API ENDPOINT para reportar progreso de TPs
     * POST /api/signals/{user_signal_id}/progress
     */
    public function report_progress($user_signal_id)
    {
        $progress_data = json_decode(file_get_contents("php://input"), true);

        if (!is_numeric($user_signal_id) || !$progress_data) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid parameters']);
            return;
        }

        try {
            if ($this->Telegram_signals_model->report_progress($user_signal_id, $progress_data)) {
                http_response_code(200);
                echo json_encode(['success' => true, 'status' => 'progress_updated']);
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'Signal not found']);
            }
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Internal server error']);
        }
    }

    /**
     * NUEVO: API ENDPOINT para reportar cierre final
     * POST /api/signals/{user_signal_id}/close
     */
    public function report_close($user_signal_id)
    {
        $close_data = json_decode(file_get_contents("php://input"), true);

        if (!is_numeric($user_signal_id) || !$close_data) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid parameters']);
            return;
        }

        try {
            if ($this->Telegram_signals_model->report_close($user_signal_id, $close_data)) {
                http_response_code(200);
                echo json_encode(['success' => true, 'status' => 'closed']);
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'Signal not found']);
            }
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Internal server error']);
        }
    }

    /**
     * GET /api/fut_price/{symbol}?source=chart|spark
     * Obtiene precio de futuros de Yahoo Finance.
     * Default: chart (v8). Fallback: spark (v7) si se pasa ?source=spark.
     */
    public function fut_price($symbol = null)
    {
        if ($symbol == null) {
            http_response_code(400);
            echo json_encode(['error' => 'Symbol parameter is required']);
            return;
        }

        $source = $this->input->get('source') ?: 'chart';
        $symbol = strtoupper($symbol) . "=F";
        $symEnc = rawurlencode($symbol);

        // Construir URL segun source
        if ($source === 'spark') {
            $url = "https://query1.finance.yahoo.com/v7/finance/spark?symbols={$symEnc}&range=1d&interval=1m";
        } else {
            $url = "https://query1.finance.yahoo.com/v8/finance/chart/{$symEnc}?interval=1m&range=1d";
        }

        // HTTP GET
        $body = $this->_yahoo_fetch($url);
        $lastClose = null;
        $ts = null;

        if ($body !== null) {
            $j = json_decode($body, true);

            // Extraer closes y timestamps segun formato
            $closes = [];
            $times = [];

            if ($source === 'spark') {
                $resp0 = $j['spark']['result'][0]['response'][0] ?? null;
                if ($resp0) {
                    $closes = isset($resp0['close']) ? $resp0['close'] : ($resp0['indicators']['quote'][0]['close'] ?? []);
                    $times  = $resp0['timestamp'] ?? [];
                }
            } else {
                $res = $j['chart']['result'][0] ?? null;
                if ($res) {
                    $closes = $res['indicators']['quote'][0]['close'] ?? [];
                    $times  = $res['timestamp'] ?? [];
                }
            }

            // Penultima no-nula (ultima vela CERRADA)
            $result = $this->_extract_last_closed_candle($closes, $times);
            $lastClose = $result['close'];
            $ts = $result['timestamp'];
        }

        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode([
                'last_close' => $lastClose,
                'ts_epoch'   => $ts,
                'ts_local'   => $this->_epoch_to_local($ts),
                'source'     => $source,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /**
     * Fetch URL de Yahoo Finance con curl
     */
    private function _yahoo_fetch($url)
    {
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

        return ($code === 200 && $body) ? $body : null;
    }

    /**
     * Extrae la penultima vela cerrada (no-nula) de arrays de closes/timestamps
     */
    private function _extract_last_closed_candle($closes, $times)
    {
        $lastClose = null;
        $ts = null;
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

        return ['close' => $lastClose, 'timestamp' => $ts];
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

    // =========================================================================
    // EA AUTÓNOMO ENDPOINTS - Trabajan directamente con tabla trades
    // =========================================================================

    /**
     * POST /api/trades/open
     * Crea un nuevo trade desde EA autónomo
     */
    public function trade_open()
    {
        $data = json_decode(file_get_contents("php://input"), true);

        if (!$data) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid JSON data']);
            return;
        }

        // Validar campos requeridos
        $required = ['user_id', 'strategy_id', 'symbol', 'side', 'entry_price', 'quantity'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                http_response_code(400);
                echo json_encode(['error' => "Missing required field: {$field}"]);
                return;
            }
        }

        // Validar side
        $data['side'] = strtoupper($data['side']);
        if (!in_array($data['side'], ['BUY', 'SELL'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid side. Must be BUY or SELL']);
            return;
        }

        try {
            $trade_data = [
                'user_id' => $data['user_id'],
                'strategy_id' => $data['strategy_id'],
                'symbol' => strtoupper($data['symbol']),
                'side' => $data['side'],
                'trade_type' => $data['trade_type'] ?? 'forex',
                'quantity' => $data['quantity'],
                'entry_price' => $data['entry_price'],
                'leverage' => $data['leverage'] ?? 1,
                'stop_loss' => $data['stop_loss'] ?? null,
                'take_profit' => $data['take_profit'] ?? null,
                'order_id' => $data['order_id'] ?? null,
                'timeframe' => $data['timeframe'] ?? 'H1',
                'source' => $data['source'] ?? 'external_ea',
                'status' => 'open',
                'created_at' => date('Y-m-d H:i:s')
            ];

            $trade_id = $this->Trade_model->add_trade($trade_data);

            if ($trade_id) {
                http_response_code(201);
                echo json_encode([
                    'success' => true,
                    'trade_id' => $trade_id,
                    'message' => 'Trade created successfully'
                ]);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to create trade']);
            }
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Internal server error']);
        }
    }

    /**
     * POST /api/trades/{id}/update
     * Actualiza un trade existente (precio actual, SL, etc.)
     */
    public function trade_update($trade_id)
    {
        if (!is_numeric($trade_id)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid trade_id']);
            return;
        }

        $data = json_decode(file_get_contents("php://input"), true);

        if (!$data) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid JSON data']);
            return;
        }

        try {
            // Verificar que el trade existe y está abierto
            $trade = $this->Trade_model->find_trade(['id' => $trade_id, 'status' => 'open']);
            if (!$trade) {
                http_response_code(404);
                echo json_encode(['error' => 'Trade not found or already closed']);
                return;
            }

            $update_data = ['updated_at' => date('Y-m-d H:i:s')];

            // Campos actualizables
            if (isset($data['current_price'])) {
                $update_data['current_price'] = $data['current_price'];
            }
            if (isset($data['stop_loss'])) {
                $update_data['stop_loss'] = $data['stop_loss'];
            }
            if (isset($data['take_profit'])) {
                $update_data['take_profit'] = $data['take_profit'];
            }

            if ($this->Trade_model->update_trade($trade_id, $update_data)) {
                http_response_code(200);
                echo json_encode(['success' => true, 'message' => 'Trade updated']);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to update trade']);
            }
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Internal server error']);
        }
    }

    /**
     * POST /api/trades/{id}/close
     * Cierra un trade con precio de salida y PNL
     */
    public function trade_close($trade_id)
    {
        if (!is_numeric($trade_id)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid trade_id']);
            return;
        }

        $data = json_decode(file_get_contents("php://input"), true);

        if (!$data) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid JSON data']);
            return;
        }

        // Validar campos requeridos
        if (!isset($data['exit_price']) || !isset($data['pnl'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing required fields: exit_price, pnl']);
            return;
        }

        try {
            // Verificar que el trade existe y está abierto
            $trade = $this->Trade_model->find_trade(['id' => $trade_id, 'status' => 'open']);
            if (!$trade) {
                http_response_code(404);
                echo json_encode(['error' => 'Trade not found or already closed']);
                return;
            }

            if ($this->Trade_model->close_trade($trade_id, $data['exit_price'], $data['pnl'])) {
                http_response_code(200);
                echo json_encode([
                    'success' => true,
                    'message' => 'Trade closed successfully',
                    'pnl' => $data['pnl']
                ]);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to close trade']);
            }
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Internal server error']);
        }
    }
}
