<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Debug extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();

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

        // Load the webhook controller to process the signal
        $this->load->library('../controllers/Webhook', null, 'webhook_lib');
        $result = $this->webhook_lib->process_webhook_data($json_data);

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
