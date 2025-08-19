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

        // Get platform filter
        $platform_filter = $this->input->get('platform');

        // Get all open trades for the user with platform filter
        $data['open_trades'] = $this->Trade_model->find_trades([
            'user_id' => $user_id,
            'status' => 'open',
            'platform' => $platform_filter
        ], ['with_relations' => true]);

        // Get API key
        $data['api_key'] = $this->Api_key_model->get_api_key($user_id);

        // Get all strategies for this user
        $data['strategies'] = $this->Strategy_model->get_all_strategies($user_id);

        // Check if user is admin (for simulation panel)
        $data['is_admin'] = ($this->session->userdata('role') == 'admin');

        // Filter data for admin simulation panel
        if ($data['is_admin']) {
            $data['all_users'] = $this->User_model->get_all_users();
            $data['all_strategies'] = $this->Strategy_model->get_all_strategies();
        }

        // Current filter
        $data['current_platform'] = $platform_filter;

        // Load view
        $this->load->view('templates/header', $data);
        $this->load->view('dashboard/index', $data);
        $this->load->view('templates/footer');
    }

    public function refresh_trades()
    {
        $user_id = $this->session->userdata('user_id');

        // Get platform filter
        $platform_filter = $this->input->get('platform');

        // Get all open trades with platform filter
        $trades = $this->Trade_model->find_trades([
            'user_id' => $user_id,
            'status' => 'open',
            'platform' => $platform_filter
        ], ['with_relations' => true]);

        // Get API key for BingX operations
        $api_key = $this->Api_key_model->get_api_key($user_id);

        // Update PNL ONLY for BingX trades (MT trades don't have real-time prices)
        if ($api_key && !empty($trades)) {
            // Filter BingX trades for price updates
            $bingx_trades = array_filter($trades, function ($trade) {
                return $trade->platform === 'bingx';
            });

            if (!empty($bingx_trades)) {
                // Collect unique symbol-environment-type combinations for BingX
                $price_requests = [];

                foreach ($bingx_trades as $trade) {
                    $key = $trade->symbol . '_' . $trade->environment . '_' . $trade->trade_type;
                    if (!isset($price_requests[$key])) {
                        $price_requests[$key] = [
                            'symbol' => $trade->symbol,
                            'environment' => $trade->environment,
                            'trade_type' => $trade->trade_type
                        ];
                    }
                }

                // Fetch prices for unique combinations
                $price_cache = [];
                foreach ($price_requests as $key => $request) {
                    try {
                        $this->bingxapi->set_environment($request['environment']);

                        if ($request['trade_type'] == 'futures') {
                            $price_info = $this->bingxapi->get_futures_price($api_key, $request['symbol'], true);
                        } else {
                            $price_info = $this->bingxapi->get_spot_price($api_key, $request['symbol'], true);
                        }

                        if ($price_info && isset($price_info->price)) {
                            $price_cache[$key] = $price_info->price;
                        }
                    } catch (Exception $e) {
                        $this->Log_model->add_log([
                            'user_id' => $user_id,
                            'action' => 'refresh_error',
                            'description' => 'Error fetching price for ' . $request['symbol'] . ': ' . $e->getMessage()
                        ]);
                    }
                }

                // Update BingX trades with cached prices
                foreach ($bingx_trades as $trade) {
                    $key = $trade->symbol . '_' . $trade->environment . '_' . $trade->trade_type;

                    if (isset($price_cache[$key])) {
                        $current_price = $price_cache[$key];

                        // Calculate PNL
                        if ($trade->side == 'BUY') {
                            $pnl = ($current_price - $trade->entry_price) * $trade->quantity;
                        } else {
                            $pnl = ($trade->entry_price - $current_price) * $trade->quantity;
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
                }
            }
        }

        // Format values for JSON response (all trades)
        foreach ($trades as $trade) {
            // Format price values
            if (isset($trade->current_price)) {
                $trade->current_price_formatted = number_format($trade->current_price, 2);
            }

            if (isset($trade->entry_price)) {
                $trade->entry_price_formatted = number_format($trade->entry_price, 2);
            }

            if (isset($trade->pnl)) {
                $trade->pnl_formatted = number_format($trade->pnl, 2);
            }

            if (isset($trade->quantity)) {
                $trade->quantity_formatted = rtrim(rtrim(number_format($trade->quantity, 8), '0'), '.');
            }

            // Add platform-specific formatting
            $trade->platform_badge_class = $trade->platform === 'metatrader' ? 'bg-dark' : 'bg-info';
            $trade->platform_display = ucfirst($trade->platform);
        }

        // Return JSON response
        echo json_encode($trades);
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
        $price_info = $this->bingxapi->get_spot_price($api_key, 'BTCUSDT', true);

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
