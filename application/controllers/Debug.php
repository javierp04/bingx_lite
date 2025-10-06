<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Debug extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Telegram_signals_model');
        $this->load->model('User_tickers_model');

        // Check if user is logged in
        if (!$this->session->userdata('logged_in')) {
            redirect('auth');
        }

        // Check if user is admin (optional - adjust based on requirements)
        if ($this->session->userdata('role') !== 'admin') {
            $this->session->set_flashdata('error', 'Access denied. Debug panel requires admin privileges.');
            redirect('dashboard');
        }

        // Load BingX API library
        $this->load->library('BingxApi');

        // Load Webhook processor library
        $this->load->library('Webhook_processor');
    }

    public function index()
    {
        $data['title'] = 'Debug Panel';

        // Get all users for template generator
        $data['users'] = $this->User_model->get_all_users();

        // Get all strategies for template generator
        $data['strategies'] = $this->Strategy_model->get_all_strategies();

        $this->load->view('templates/header', $data);
        $this->load->view('debug/index', $data);
        $this->load->view('templates/footer');
    }

    /**
     * Test MetaTrader signal
     */
    public function test_mt_signal()
    {
        $json_data = file_get_contents('php://input');

        if (!$json_data) {
            $this->_send_json_response(false, 'No signal data provided');
            return;
        }

        // Validate JSON
        $data = json_decode($json_data);
        if (!$data) {
            $this->_send_json_response(false, 'Invalid JSON format');
            return;
        }

        // Log the test
        $this->Log_model->add_log([
            'user_id' => $this->session->userdata('user_id'),
            'action' => 'mt_debug_test',
            'description' => 'Testing MT signal from debug panel: ' . $json_data
        ]);

        // Process the signal using the Mt_signal_processor library
        $result = $this->mt_signal_processor->process_signal($json_data);

        if ($result === true) {
            $this->_send_json_response(true, 'MetaTrader signal processed successfully and queued');
        } else {
            $this->_send_json_response(false, 'MetaTrader signal failed: ' . $result);
        }
    }

    /**
     * Test BingX signal
     */
    public function test_bingx_signal()
    {
        $json_data = file_get_contents('php://input');

        if (!$json_data) {
            $this->_send_json_response(false, 'No signal data provided');
            return;
        }

        // Validate JSON
        $data = json_decode($json_data);
        if (!$data) {
            $this->_send_json_response(false, 'Invalid JSON format');
            return;
        }

        // Log the test
        $this->Log_model->add_log([
            'user_id' => $this->session->userdata('user_id'),
            'action' => 'bingx_debug_test',
            'description' => 'Testing BingX signal from debug panel: ' . $json_data
        ]);

        // Use the webhook processor library to process the signal
        $result = $this->webhook_processor->process_webhook_data($json_data);

        if ($result === true) {
            $this->_send_json_response(true, 'BingX signal processed successfully');
        } else {
            $this->_send_json_response(false, 'BingX signal failed: ' . $result);
        }
    }

    /**
     * Test BingX Spot Balance
     */
    public function test_spot_balance()
    {
        $user_id = $this->session->userdata('user_id');
        $api_key = $this->Api_key_model->get_api_key($user_id);

        if (!$api_key) {
            $this->_send_json_response(false, 'API key not configured');
            return;
        }

        $result = $this->bingxapi->get_spot_account($api_key);

        if ($result) {
            // Format the result for display
            $balances_html = '<div class="table-responsive"><table class="table table-sm table-striped">
                          <thead><tr><th>Asset</th><th>Available</th><th>Locked</th></tr></thead>
                          <tbody>';

            $has_balance = false;

            if (isset($result->balances) && is_array($result->balances)) {
                foreach ($result->balances as $balance) {
                    // Show only assets with balance > 0
                    if (floatval($balance->free) > 0 || floatval($balance->locked) > 0) {
                        $has_balance = true;
                        $balances_html .= '<tr>
                                        <td>' . $balance->asset . '</td>
                                        <td>' . number_format(floatval($balance->free), 8) . '</td>
                                        <td>' . number_format(floatval($balance->locked), 8) . '</td>
                                      </tr>';
                    }
                }
            }

            if (!$has_balance) {
                $balances_html .= '<tr><td colspan="3" class="text-center">No balances available</td></tr>';
            }

            $balances_html .= '</tbody></table></div>';

            $this->_send_json_response(true, 'Spot balance retrieved successfully', $balances_html);
        } else {
            $this->_send_json_response(false, 'Failed to get spot balance: ' . $this->bingxapi->get_last_error());
        }
    }

    /**
     * Test BingX Futures Balance
     */
    public function test_futures_balance()
    {
        $user_id = $this->session->userdata('user_id');
        $api_key = $this->Api_key_model->get_api_key($user_id);

        if (!$api_key) {
            $this->_send_json_response(false, 'API key not configured');
            return;
        }

        $result = $this->bingxapi->get_futures_account($api_key);

        if ($result) {
            // Format the result for display
            $balances_html = '<div class="table-responsive"><table class="table table-sm table-striped">
                          <thead>
                            <tr>
                              <th>Asset</th>
                              <th>Total Balance</th>
                              <th>Available</th>
                              <th>Used</th>
                              <th>Locked</th>
                              <th>Unrealized PnL</th>
                            </tr>
                          </thead>
                          <tbody>';

            // For futures, structure may be different
            if (isset($result->balance)) {
                // Single asset (typically USDT)
                $balance = $result->balance;
                $balances_html .= '<tr>
                                <td>' . $balance->asset . '</td>
                                <td>' . number_format(floatval($balance->balance), 4) . '</td>
                                <td>' . number_format(floatval($balance->availableMargin), 4) . '</td>
                                <td>' . number_format(floatval($balance->usedMargin), 4) . '</td>
                                <td>' . number_format(floatval($balance->freezedMargin), 4) . '</td>
                                <td>' . number_format(floatval($balance->unrealizedProfit), 4) . '</td>
                              </tr>';
            } elseif (isset($result->balances) && is_array($result->balances)) {
                // Multiple assets
                foreach ($result->balances as $balance) {
                    if (floatval($balance->balance) > 0) {
                        $balances_html .= '<tr>
                                        <td>' . $balance->asset . '</td>
                                        <td>' . number_format(floatval($balance->balance), 4) . '</td>
                                        <td>' . number_format(floatval($balance->availableMargin), 4) . '</td>
                                        <td>' . number_format(floatval($balance->usedMargin), 4) . '</td>
                                        <td>' . number_format(floatval($balance->freezedMargin), 4) . '</td>
                                        <td>' . number_format(floatval($balance->unrealizedProfit), 4) . '</td>
                                      </tr>';
                    }
                }
            } else {
                $balances_html .= '<tr><td colspan="6" class="text-center">No balances available</td></tr>';
            }

            $balances_html .= '</tbody></table></div>';

            $this->_send_json_response(true, 'Futures balance retrieved successfully', $balances_html);
        } else {
            $this->_send_json_response(false, 'Failed to get futures balance: ' . $this->bingxapi->get_last_error());
        }
    }

    /**
     * Test BingX Spot Price
     */
    public function test_spot_price()
    {
        $user_id = $this->session->userdata('user_id');
        $api_key = $this->Api_key_model->get_api_key($user_id);
        $symbol = $this->input->post('symbol', true) ?: $this->input->get('symbol', true);

        if (!$symbol) {
            $symbol = 'BTCUSDT';
        }

        if (!$api_key) {
            $this->_send_json_response(false, 'API key not configured');
            return;
        }

        // Get spot price only
        $spot_price = $this->bingxapi->get_spot_price($api_key, $symbol);

        if ($spot_price) {
            $price_html = '<div class="alert alert-info">
                        <strong>' . $symbol . ' Spot Price:</strong> $' . number_format($spot_price->price, 2) . '
                      </div>';
            $this->_send_json_response(true, 'Spot price retrieved successfully', $price_html);
        } else {
            $this->_send_json_response(false, 'Failed to get spot price for ' . $symbol . ': ' . $this->bingxapi->get_last_error());
        }
    }

    /**
     * Test BingX Futures Price
     */
    public function test_futures_price()
    {
        $user_id = $this->session->userdata('user_id');
        $api_key = $this->Api_key_model->get_api_key($user_id);
        $symbol = $this->input->post('symbol', true) ?: $this->input->get('symbol', true);

        if (!$symbol) {
            $symbol = 'BTCUSDT';
        }

        if (!$api_key) {
            $this->_send_json_response(false, 'API key not configured');
            return;
        }

        // Get futures price only
        $futures_price = $this->bingxapi->get_futures_price($api_key, $symbol);

        if ($futures_price) {
            $price_html = '<div class="alert alert-info">
                        <strong>' . $symbol . ' Futures Price:</strong> $' . number_format($futures_price->price, 2) . '
                      </div>';
            $this->_send_json_response(true, 'Futures price retrieved successfully', $price_html);
        } else {
            $this->_send_json_response(false, 'Failed to get futures price for ' . $symbol . ': ' . $this->bingxapi->get_last_error());
        }
    }

    /**
     * Test EA execution confirmation (for testing the new MT circuit)
     */
    public function test_confirm_execution()
    {
        $position_id = $this->input->post('position_id');
        $status = $this->input->post('status');
        $execution_price = $this->input->post('execution_price');
        $error_message = $this->input->post('error_message');

        if (!$position_id || !$status) {
            $this->_send_json_response(false, 'position_id and status required');
            return;
        }

        // Log the test
        $this->Log_model->add_log([
            'user_id' => $this->session->userdata('user_id'),
            'action' => 'mt_confirm_test',
            'description' => 'Testing MT execution confirmation from debug panel: ' . json_encode([
                'position_id' => $position_id,
                'status' => $status,
                'execution_price' => $execution_price,
                'error_message' => $error_message
            ])
        ]);

        // Load the Metatrader controller to process the confirmation
        $this->load->library('Mt_signal_processor');
        $result = $this->mt_signal_processor->confirm_execution($position_id, $status, $execution_price, $error_message);

        if ($result === true) {
            $this->_send_json_response(true, 'Execution confirmation processed successfully');
        } else {
            $this->_send_json_response(false, 'Execution confirmation failed: ' . $result);
        }
    }

    public function telegram()
    {
        $data['title'] = 'Telegram Debug Panel';

        // Get available tickers for dropdown
        $data['available_tickers'] = $this->User_tickers_model->get_all_available_tickers(true);

        // Get users for reference
        $data['users'] = $this->User_model->get_all_users();

        $this->load->view('templates/header', $data);
        $this->load->view('debug/telegram', $data);
        $this->load->view('templates/footer');
    }

    /**
     * Generate Telegram Signal
     */
    public function generate_telegram_signal()
    {
        $ticker = $this->input->post('ticker', true);
        $op_type = $this->input->post('op_type', true);
        $entry_price = $this->input->post('entry_price', true);
        $stop_loss_1 = $this->input->post('stop_loss_1', true);
        $stop_loss_2 = $this->input->post('stop_loss_2', true);
        $tp1 = $this->input->post('tp1', true);
        $tp2 = $this->input->post('tp2', true);
        $tp3 = $this->input->post('tp3', true);
        $tp4 = $this->input->post('tp4', true);
        $tp5 = $this->input->post('tp5', true);
        $volume = $this->input->post('volume', true) ?: 1.0;

        if (!$ticker || !$op_type || !$entry_price) {
            $this->_send_json_response(false, 'Missing required fields: ticker, operation type, and entry price');
            return;
        }

        try {
            // Generate analysis JSON in the CORRECT simple format
            $analysis_data = [
                'op_type' => strtoupper($op_type),
                'stoploss' => [],
                'entry' => floatval($entry_price),
                'tps' => []
            ];

            // Add stop losses - array must have consistent structure
            $sl_values = [$stop_loss_1, $stop_loss_2];
            foreach ($sl_values as $sl) {
                if ($sl && $sl > 0) {
                    $analysis_data['stoploss'][] = floatval($sl);
                } else {
                    // Pad with 0 if missing
                    $analysis_data['stoploss'][] = 0.0;
                }
            }

            // Add take profits - MUST have exactly 5 TPs
            $tp_values = [$tp1, $tp2, $tp3, $tp4, $tp5];
            $tp_count = 0;
            foreach ($tp_values as $tp) {
                if ($tp && $tp > 0) {
                    $analysis_data['tps'][] = floatval($tp);
                    $tp_count++;
                } else {
                    // Missing TP - pad with 0 to maintain array structure
                    $analysis_data['tps'][] = 0.0;
                }
            }

            // Validate that we have at least 1 TP
            if ($tp_count == 0) {
                throw new Exception('At least one Take Profit is required');
            }

            // Create fake TradingView URL
            $tradingview_url = "https://www.tradingview.com/chart/?symbol={$ticker}&interval=1H";

            // Create fake image path (we won't actually create the file)
            $image_path = 'uploads/trades/debug-' . date('Y-m-d') . '_' . $ticker . '.png';

            // Create fake message text
            $message_text = "DEBUG GENERATED SIGNAL\n\n" .
                "Ticker: {$ticker}\n" .
                "Type: " . strtoupper($op_type) . "\n" .
                "Entry: {$entry_price}\n" .
                "Generated by: " . $this->session->userdata('username') . "\n" .
                "Time: " . date('Y-m-d H:i:s');

            // 1. Create telegram signal
            $telegram_signal_id = $this->Telegram_signals_model->create_signal(
                $ticker,
                $image_path,
                $tradingview_url,
                $message_text,
                json_encode(['debug' => true, 'generator' => 'debug_panel'])
            );

            if (!$telegram_signal_id) {
                throw new Exception('Failed to create telegram signal');
            }

            // 2. Complete the signal with analysis data
            if (!$this->Telegram_signals_model->complete_signal($telegram_signal_id, json_encode($analysis_data))) {
                throw new Exception('Failed to complete telegram signal');
            }

            // 3. Create user signals for all users with this ticker
            $users_affected = $this->Telegram_signals_model->create_user_signals_for_ticker($telegram_signal_id, $ticker);

            // Log the action
            $this->Log_model->add_log([
                'user_id' => $this->session->userdata('user_id'),
                'action' => 'telegram_debug_signal',
                'description' => "Generated debug Telegram signal for {$ticker} ({$op_type}). " .
                    "Signal ID: {$telegram_signal_id}, Users affected: {$users_affected}"
            ]);

            $this->_send_json_response(
                true,
                "Telegram signal generated successfully! " .
                    "Signal ID: {$telegram_signal_id}, Users affected: {$users_affected}",
                [
                    'telegram_signal_id' => $telegram_signal_id,
                    'users_affected' => $users_affected,
                    'analysis_data' => $analysis_data,
                    'view_url' => base_url('telegram_signals/view/' . $telegram_signal_id)
                ]
            );
        } catch (Exception $e) {
            $this->Log_model->add_log([
                'user_id' => $this->session->userdata('user_id'),
                'action' => 'telegram_debug_error',
                'description' => 'Failed to generate debug Telegram signal: ' . $e->getMessage()
            ]);

            $this->_send_json_response(false, 'Failed to generate signal: ' . $e->getMessage());
        }
    }

    /**
     * Test generated telegram signal (simulate EA polling)
     */
    public function test_telegram_signal()
    {
        $user_id = $this->input->post('user_id', true);
        $ticker = $this->input->post('ticker', true);

        if (!$user_id || !$ticker) {
            $this->_send_json_response(false, 'User ID and ticker required');
            return;
        }

        try {
            // Simulate EA_Signals polling
            $signal = $this->Telegram_signals_model->get_available_signals_for_user($user_id, $ticker);

            if (!$signal) {
                $this->_send_json_response(false, 'No available signals found for this user/ticker combination');
                return;
            }

            // Simulate claiming the signal
            if ($this->Telegram_signals_model->claim_user_signal($signal->id, $user_id)) {
                $this->_send_json_response(
                    true,
                    'Signal successfully claimed by EA simulation!',
                    [
                        'user_signal_id' => $signal->id,
                        'telegram_signal_id' => $signal->telegram_signal_id,
                        'analysis' => json_decode($signal->analysis_data, true),
                        'status' => 'claimed'
                    ]
                );
            } else {
                $this->_send_json_response(false, 'Failed to claim signal (already claimed or expired)');
            }
        } catch (Exception $e) {
            $this->_send_json_response(false, 'Error testing signal: ' . $e->getMessage());
        }
    }

    /**
     * Simulate full Telegram webhook from raw message
     */
    public function simulate_telegram_webhook()
    {
        $message_text = $this->input->post('message', true);

        if (!$message_text) {
            $this->_send_json_response(false, 'No message provided');
            return;
        }

        try {
            // 1. Build fake Telegram webhook payload
            $telegram_payload = [
                'update_id' => rand(100000, 999999),
                'message' => [
                    'message_id' => rand(1000, 9999),
                    'from' => [
                        'id' => 999999,
                        'is_bot' => false,
                        'first_name' => 'Debug',
                        'last_name' => 'Simulator',
                        'username' => 'debug_simulator'
                    ],
                    'chat' => [
                        'id' => -1001234567890,
                        'title' => 'Debug Test Channel',
                        'type' => 'channel'
                    ],
                    'date' => time(),
                    'text' => $message_text
                ]
            ];

            // 2. POST to real webhook endpoint
            $webhook_url = base_url('tradereader/run');

            $ch = curl_init($webhook_url);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($telegram_payload),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                CURLOPT_TIMEOUT => 120
            ]);

            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            // 3. Parse response
            $result = json_decode($response, true);

            // Check if webhook succeeded
            if ($result && isset($result['status']) && $result['status'] === 'success' && isset($result['signal_id'])) {
                // Get final signal data
                $signal_id = $result['signal_id'];
                $final_signal = $this->Telegram_signals_model->get_signal_by_id($signal_id);

                // Get AI provider
                $ai_provider = $this->config->item('ai_provider') ?: 'openai';

                // Log success
                $this->Log_model->add_log([
                    'user_id' => $this->session->userdata('user_id'),
                    'action' => 'telegram_webhook_simulation',
                    'description' => "Simulated Telegram webhook. Signal ID: {$signal_id}. AI: {$ai_provider}"
                ]);

                $this->_send_json_response(
                    true,
                    'Telegram webhook simulated successfully!',
                    [
                        'signal_id' => $signal_id,
                        'ticker' => $result['ticker'],
                        'status' => $final_signal->status,
                        'image_path' => $final_signal->image_path ?? null,
                        'analysis_data' => json_decode($final_signal->analysis_data, true),
                        'users_distributed' => $this->db->where('telegram_signal_id', $signal_id)->count_all_results('user_telegram_signals'),
                        'ai_provider' => $ai_provider
                    ]
                );
            } else {
                // Webhook failed - show error
                $error_msg = $result['message'] ?? 'Unknown error';

                $this->_send_json_response(false, 'Webhook processing failed: ' . $error_msg, [
                    'error_details' => $response,
                    'http_code' => $http_code
                ]);
            }

        } catch (Exception $e) {
            $this->_send_json_response(false, 'Simulation error: ' . $e->getMessage(), [
                'error_details' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Send JSON response for AJAX calls
     */
    private function _send_json_response($success, $message, $data = null)
    {
        $response = array(
            'success' => $success,
            'message' => $message
        );

        if ($data) {
            $response['data'] = $data;
        }

        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode($response));
    }
}
