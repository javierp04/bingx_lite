<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Dashboard extends CI_Controller
{

    public function __construct()
    {
        parent::__construct();

        // Check if user is logged in
        if (!$this->session->userdata('logged_in')) {
            redirect('auth');
        }

        // Load BingX API library
        $this->load->library('BingxApi');
    }

    public function index()
    {
        $data['title'] = 'Dashboard';
        $user_id = $this->session->userdata('user_id');

        // Get all open trades for the user
        $data['open_trades'] = $this->Trade_model->get_all_trades($user_id, 'open');

        // Get API key
        $data['api_key'] = $this->Api_key_model->get_api_key($user_id);

        // Get all strategies for this user
        $data['strategies'] = $this->Strategy_model->get_all_strategies($user_id);

        // Check if user is admin (for simulation panel)
        $data['is_admin'] = ($this->session->userdata('role') == 'admin');

        // If user is admin, get additional data for the simulation panel
        if ($data['is_admin']) {
            // Get all users for the simulation panel dropdown
            $data['all_users'] = $this->User_model->get_all_users();

            // Get all strategies
            $data['all_strategies'] = $this->Strategy_model->get_all_strategies();
        }

        // Load view
        $this->load->view('templates/header', $data);
        $this->load->view('dashboard/index', $data);
        $this->load->view('templates/footer');
    }

    public function refresh_trades()
    {
        $user_id = $this->session->userdata('user_id');

        // Get all open trades
        $trades = $this->Trade_model->get_all_trades($user_id, 'open');

        // Get API key for this user
        $api_key = $this->Api_key_model->get_api_key($user_id);

        // Update PNL for each trade
        if ($api_key && !empty($trades)) {
            foreach ($trades as $trade) {
                try {
                    // Update price and PNL information
                    if ($trade->trade_type == 'futures') {
                        $price_info = $this->bingxapi->get_futures_price($api_key, $trade->symbol);
                    } else {
                        $price_info = $this->bingxapi->get_spot_price($api_key, $trade->symbol);
                    }

                    if ($price_info && isset($price_info->price)) {
                        // Calculate PNL
                        $current_price = $price_info->price;

                        if ($trade->side == 'BUY') {
                            $pnl = ($current_price - $trade->entry_price) * $trade->quantity * $trade->leverage;
                        } else {
                            $pnl = ($trade->entry_price - $current_price) * $trade->quantity * $trade->leverage;
                        }

                        // Update trade with current price and PNL
                        $this->Trade_model->update_trade($trade->id, array(
                            'current_price' => $current_price,
                            'pnl' => $pnl
                        ));

                        // Update object for JSON response
                        $trade->current_price = $current_price;
                        $trade->pnl = $pnl;
                    }
                } catch (Exception $e) {
                    // Log error but continue with other trades
                    $this->Log_model->add_log([
                        'user_id' => $user_id,
                        'action' => 'refresh_error',
                        'description' => 'Error updating trade ' . $trade->id . ': ' . $e->getMessage()
                    ]);
                }
            }
        }

        // Format prices and PNL to limit decimals
        foreach ($trades as $trade) {
            if (isset($trade->current_price)) {
                $trade->current_price_formatted = number_format($trade->current_price, 2);
            }
            if (isset($trade->entry_price)) {
                $trade->entry_price_formatted = number_format($trade->entry_price, 2);
            }
            if (isset($trade->pnl)) {
                $trade->pnl_formatted = number_format($trade->pnl, 2);
            }
        }

        // Return JSON response
        echo json_encode($trades);
    }

    // Pruebas de API

    public function test_spot_balance()
    {
        $user_id = $this->session->userdata('user_id');
        $api_key = $this->Api_key_model->get_api_key($user_id);

        if (!$api_key) {
            $this->session->set_flashdata('error', 'API key not configured');
            redirect('dashboard');
            return;
        }

        $result = $this->bingxapi->get_spot_account($api_key);

        if ($result) {
            // Formatear el resultado para mostrar los balances de manera legible
            $balances_html = '<div class="table-responsive"><table class="table table-sm table-striped">
                              <thead><tr><th>Ticker</th><th>Disponible</th><th>Bloqueado</th></tr></thead>
                              <tbody>';

            $has_balance = false;

            if (isset($result->balances) && is_array($result->balances)) {
                foreach ($result->balances as $balance) {
                    // Mostrar solo los activos con balance mayor a 0
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
                $balances_html .= '<tr><td colspan="3" class="text-center">No hay balances disponibles</td></tr>';
            }

            $balances_html .= '</tbody></table></div>';

            $this->session->set_flashdata('success', 'Spot balance retrieved successfully:<br>' . $balances_html);
        } else {
            $this->session->set_flashdata('error', 'Failed to get spot balance: ' . $this->bingxapi->get_last_error());
        }

        redirect('dashboard');
    }

    public function test_futures_balance()
    {
        $user_id = $this->session->userdata('user_id');
        $api_key = $this->Api_key_model->get_api_key($user_id);

        if (!$api_key) {
            $this->session->set_flashdata('error', 'API key not configured');
            redirect('dashboard');
            return;
        }

        $result = $this->bingxapi->get_futures_account($api_key);

        if ($result) {
            // Formatear el resultado para mostrar los balances de manera legible
            $balances_html = '<div class="table-responsive"><table class="table table-sm table-striped">
                              <thead>
                                <tr>
                                  <th>Activo</th>
                                  <th>Balance Total</th>
                                  <th>Disponible</th>
                                  <th>En Uso</th>
                                  <th>Bloqueado</th>
                                  <th>PnL No Realizado</th>
                                </tr>
                              </thead>
                              <tbody>';

            // En futuros, la estructura puede ser diferente
            if (isset($result->balance)) {
                // Solo un activo (típicamente USDT)
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
                // Múltiples activos (depende de la API)
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
            }

            if (empty($result->balance) && empty($result->balances)) {
                $balances_html .= '<tr><td colspan="6" class="text-center">No hay balances disponibles</td></tr>';
            }

            $balances_html .= '</tbody></table></div>';

            $this->session->set_flashdata('success', 'Futures balance retrieved successfully:<br>' . $balances_html);
        } else {
            $this->session->set_flashdata('error', 'Failed to get futures balance: ' . $this->bingxapi->get_last_error());
        }

        redirect('dashboard');
    }

    public function test_spot_price()
    {
        $user_id = $this->session->userdata('user_id');
        $api_key = $this->Api_key_model->get_api_key($user_id);
        $symbol = $this->input->get('symbol', true);

        if (!$symbol) {
            $symbol = 'BTCUSDT';
        }

        if (!$api_key) {
            $this->session->set_flashdata('error', 'API key not configured');
            redirect('dashboard');
            return;
        }

        // Get spot price only
        $spot_price = $this->bingxapi->get_spot_price($api_key, $symbol);

        if ($spot_price) {
            $this->session->set_flashdata('success', 'Spot price for ' . $symbol . ': ' . $spot_price->price);
        } else {
            $this->session->set_flashdata('error', 'Failed to get spot price for ' . $symbol . ': ' . $this->bingxapi->get_last_error());
        }

        redirect('dashboard');
    }

    public function test_futures_price()
    {
        $user_id = $this->session->userdata('user_id');
        $api_key = $this->Api_key_model->get_api_key($user_id);
        $symbol = $this->input->get('symbol', true);

        if (!$symbol) {
            $symbol = 'BTCUSDT';
        }

        if (!$api_key) {
            $this->session->set_flashdata('error', 'API key not configured');
            redirect('dashboard');
            return;
        }

        // Get futures price only
        $futures_price = $this->bingxapi->get_futures_price($api_key, $symbol);

        if ($futures_price) {
            $this->session->set_flashdata('success', 'Futures price for ' . $symbol . ': ' . $futures_price->price);
        } else {
            $this->session->set_flashdata('error', 'Failed to get futures price for ' . $symbol . ': ' . $this->bingxapi->get_last_error());
        }

        redirect('dashboard');
    }

    public function get_btc_price()
    {
        $user_id = $this->session->userdata('user_id');

        // Check login
        if (!$user_id) {
            echo json_encode(['error' => 'Not logged in']);
            return;
        }

        // Get API key
        $api_key = $this->Api_key_model->get_api_key($user_id);

        if (!$api_key) {
            echo json_encode(['error' => 'No API key configured']);
            return;
        }

        // Get BTC price
        $price_info = $this->bingxapi->get_spot_price($api_key, 'BTCUSDT');

        if ($price_info && isset($price_info->price)) {
            $price = $price_info->price;

            echo json_encode([
                'success' => true,
                'price' => $price,
                'price_formatted' => number_format($price, 2)
            ]);
        } else {
            echo json_encode([
                'error' => 'Failed to get price',
                'message' => $this->bingxapi->get_last_error()
            ]);
        }
    }
}
