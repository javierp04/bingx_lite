<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * API Controller para MetaTrader EA
 * Maneja las comunicaciones entre el EA y el sistema
 */
class Api extends CI_Controller
{
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

            if ($this->Telegram_signals_model->update_user_signal($user_signal_id, $status, $execution_data)) {
                http_response_code(200);
                echo json_encode(['success' => true, 'status' => $status]);
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
     * API ENDPOINT para EA: reportar cierre de trade  
     * POST /api/signals/{user_signal_id}/close
     */
    public function close_trade($user_signal_id)
    {
        $close_data = json_decode(file_get_contents("php://input"), true);

        if (!is_numeric($user_signal_id)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid parameters']);
            return;
        }

        try {
            if ($this->Telegram_signals_model->update_user_signal($user_signal_id, 'closed', $close_data)) {
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
     * API ENDPOINT para EA: obtener múltiples señales disponibles
     * GET /api/signals/{user_id}/pending
     */
    public function get_pending_signals($user_id)
    {
        if (!is_numeric($user_id)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid user_id']);
            return;
        }

        try {
            // ✨ NUEVO: Obtener todas las señales disponibles del usuario
            $filters = ['status' => 'available'];
            $signals = $this->Telegram_signals_model->get_user_signals_with_filters($user_id, $filters);

            $response_signals = array();
            foreach ($signals as $signal) {
                if (!empty($signal->analysis_data)) {
                    $response_signals[] = array(
                        'user_signal_id' => (int)$signal->id,
                        'telegram_signal_id' => (int)$signal->telegram_signal_id,
                        'ticker_symbol' => $signal->ticker_symbol,
                        'mt_ticker' => $signal->mt_ticker,
                        'analysis' => json_decode($signal->analysis_data, true),
                        'created_at' => $signal->created_at
                    );
                }
            }

            http_response_code(200);
            echo json_encode([
                'success' => true,
                'count' => count($response_signals),
                'signals' => $response_signals
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Internal server error']);
        }
    }

    /**
     * API ENDPOINT de health check
     * GET /api/health
     */
    public function health()
    {
        http_response_code(200);
        echo json_encode([
            'status' => 'ok',
            'timestamp' => date('Y-m-d H:i:s'),
            'version' => '1.0.0'
        ]);
    }
}