<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Trades extends CI_Controller
{

    public function __construct()
    {
        parent::__construct();

        // Check if user is logged in
        if (!$this->session->userdata('logged_in')) {
            redirect('auth');
        }
    }

    public function index()
    {
        $data['title'] = 'Trade History';
        $user_id = $this->session->userdata('user_id');

        // Get filter params (only closed trades shown here, open trades are on Dashboard)
        $strategy = $this->input->get('strategy');
        $platform = $this->input->get('platform');
        $symbol = $this->input->get('symbol');

        // Convertir strings vacíos a null
        $strategy = empty($strategy) ? null : $strategy;
        $platform = empty($platform) ? null : $platform;
        $symbol = empty($symbol) ? null : $symbol;

        // Get all strategies for filter dropdown
        $data['strategies'] = $this->Strategy_model->get_all_strategies($user_id);

        // Get all symbols for filter dropdown
        $data['symbols'] = $this->Trade_model->get_distinct_symbols($user_id);

        // Get all closed trades with filters
        $trades = $this->Trade_model->find_trades([
            'user_id' => $user_id,
            'status' => 'closed',
            'platform' => $platform,
            'strategy_id' => $strategy,
            'symbol' => $symbol
        ], ['with_relations' => true]);

        // Group trades by strategy_id
        $grouped_trades = [];
        foreach ($trades as $trade) {
            $sid = $trade->strategy_id ?? 0;
            if (!isset($grouped_trades[$sid])) {
                $grouped_trades[$sid] = [
                    'strategy_id' => $sid,
                    'strategy_name' => $trade->strategy_name ?? 'Unknown',
                    'symbol' => $trade->symbol,
                    'platform' => $trade->platform ?? 'unknown',
                    'trades' => [],
                    'stats' => null
                ];
            }
            $grouped_trades[$sid]['trades'][] = $trade;
        }

        // Calculate stats for each strategy group
        foreach ($grouped_trades as $sid => &$group) {
            $group['stats'] = $this->Trade_model->get_platform_statistics($user_id, null, $sid);
        }
        unset($group);

        $data['grouped_trades'] = $grouped_trades;
        $data['trades'] = $trades; // Keep for global stats

        // Calculate global trading statistics
        $stats = $this->Trade_model->get_platform_statistics($user_id, $platform, $strategy);
        $data['stats'] = $stats;

        // Pass current filters to view
        $data['current_platform'] = $platform;
        $data['current_strategy'] = $strategy;
        $data['current_symbol'] = $symbol;

        $this->load->view('templates/header', $data);
        $this->load->view('trades/index', $data);
        $this->load->view('templates/footer');
    }

    public function detail($id)
    {
        $data['title'] = 'Trade Detail';
        $user_id = $this->session->userdata('user_id');

        // Get trade
        $data['trade'] = $this->Trade_model->find_trade([
            'trades.id' => $id
        ], ['with_relations' => true]);

        // Check if trade exists and belongs to user
        if (empty($data['trade']) || $data['trade']->user_id != $user_id) {
            $this->session->set_flashdata('error', 'Trade not found or access denied');
            redirect('trades');
        }

        $this->load->view('templates/header', $data);
        $this->load->view('trades/detail', $data);
        $this->load->view('templates/footer');
    }

    public function close($id)
    {
        $user_id = $this->session->userdata('user_id');

        // Get trade
        $trade = $this->Trade_model->find_trade([
            'trades.id' => $id
        ]);

        // Check if trade exists, belongs to user, and is open
        if (empty($trade) || $trade->user_id != $user_id || $trade->status != 'open') {
            $this->session->set_flashdata('error', 'Trade not found, access denied, or already closed');
            redirect('trades');
        }

        // Get API key for this environment
        $api_key = $this->Api_key_model->get_api_key($user_id);

        if (empty($api_key)) {
            $this->session->set_flashdata('error', 'API key not configured for ' . $trade->environment);
            redirect('trades');
        }

        // Load BingX API library
        $this->load->library('BingXApi');

        // Set correct environment
        $this->bingxapi->set_environment($trade->environment);

        // Close trade
        if ($trade->trade_type == 'futures') {
            $result = $this->bingxapi->close_futures_position(
                $api_key,
                $trade->symbol,
                $trade->side,
                $trade->quantity
            );
        } else {
            $result = $this->bingxapi->close_spot_position(
                $api_key,
                $trade->symbol,
                $trade->side,
                $trade->quantity
            );
        }

        if ($result && isset($result->price)) {
            // SUCCESS: Position closed in exchange
            // Calculate PNL
            $exit_price = $result->price;

            if ($trade->side == 'BUY') {
                $pnl = ($exit_price - $trade->entry_price) * $trade->quantity;
            } else {
                $pnl = ($trade->entry_price - $exit_price) * $trade->quantity;
            }

            // Update trade
            $this->Trade_model->close_trade($id, $exit_price, $pnl);

            // Log action
            $log_data = array(
                'user_id' => $user_id,
                'action' => 'close_trade',
                'description' => 'Closed trade for ' . $trade->symbol . ' with PNL: ' . number_format($pnl, 2)
            );
            $this->Log_model->add_log($log_data);

            $this->session->set_flashdata('success', 'Trade closed successfully. PNL: ' . number_format($pnl, 2));
        } else {
            // FAILED: Position not found in exchange or API error
            // Close trade in database anyway using estimated values
            $exit_price = isset($trade->current_price) ? $trade->current_price : $trade->entry_price;

            if ($trade->side == 'BUY') {
                $pnl = ($exit_price - $trade->entry_price) * $trade->quantity;
            } else {
                $pnl = ($trade->entry_price - $exit_price) * $trade->quantity;
            }

            // Close trade in database
            $this->Trade_model->close_trade($id, $exit_price, $pnl);

            // Get the BingX error for logging
            $bingx_error = $this->bingxapi->get_last_error();

            // Log the situation
            $log_data = array(
                'user_id' => $user_id,
                'action' => 'close_trade_fallback',
                'description' => 'Trade closed in database (position not found in exchange). Symbol: ' . $trade->symbol .
                    ', Estimated PNL: ' . number_format($pnl, 2) .
                    ', BingX Error: ' . $bingx_error
            );
            $this->Log_model->add_log($log_data);

            // Show warning message
            $this->session->set_flashdata(
                'warning',
                'Trade removed from database. Position not found in BingX exchange (may have been closed manually). ' .
                    'Estimated PNL: ' . number_format($pnl, 2) . ' USDT'
            );
        }

        redirect('dashboard');
    }
}
