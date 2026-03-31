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

        // Get filter params — now uses 'source' (bingx, metatrader_tv, atvip) instead of platform
        $source = $this->input->get('source');
        $strategy = $this->input->get('strategy');
        $symbol = $this->input->get('symbol');

        // Convertir strings vacíos a null
        $source = empty($source) ? null : $source;
        $strategy = empty($strategy) ? null : $strategy;
        $symbol = empty($symbol) ? null : $symbol;

        // Restrict to user's allowed sources when no specific source is selected
        $allowed_sources = get_allowed_sources();
        $source_filter = $source ?? $allowed_sources;

        // Determine if strategy dropdown should be hidden (ATVIP doesn't use strategies)
        $hide_strategy = ($source === 'atvip') || has_only_module('atvip');
        $data['hide_strategy'] = $hide_strategy;

        // Get strategies for filter dropdown (exclude ATVIP_SIGNALS, filter by platform per tab)
        if (!$hide_strategy) {
            $all_strategies = $this->Strategy_model->get_all_strategies($user_id);
            // Map source to platform for strategy filtering
            $platform_map = ['bingx' => 'bingx', 'metatrader_tv' => 'metatrader'];
            $filter_platform = isset($platform_map[$source]) ? $platform_map[$source] : null;

            $data['strategies'] = array_filter($all_strategies, function ($s) use ($filter_platform) {
                // Always exclude ATVIP_SIGNALS
                if ($s->strategy_id === 'ATVIP_SIGNALS') return false;
                // If on a specific tab, filter by platform
                if ($filter_platform !== null) return $s->platform === $filter_platform;
                return true;
            });
        } else {
            $data['strategies'] = [];
        }

        // Get symbols filtered by current source tab
        $data['symbols'] = $this->Trade_model->get_distinct_symbols($user_id, $source_filter);

        // Build filters
        $filters = [
            'user_id' => $user_id,
            'status' => 'closed',
            'source' => $source_filter,
            'strategy_id' => $strategy,
            'symbol' => $symbol
        ];

        // Get all closed trades with filters
        $trades = $this->Trade_model->find_trades($filters, ['with_relations' => true]);

        // ATVIP: group by symbol (single strategy, no sense grouping by it)
        // Others: group by strategy_id (multiple strategies per platform)
        $is_atvip_view = $hide_strategy;
        $data['is_atvip_view'] = $is_atvip_view;

        $grouped_trades = [];
        foreach ($trades as $trade) {
            if ($is_atvip_view) {
                // Group by symbol for ATVIP
                $key = $trade->symbol ?? 'UNKNOWN';
                if (!isset($grouped_trades[$key])) {
                    $grouped_trades[$key] = [
                        'group_key' => $key,
                        'group_label' => $trade->symbol,
                        'strategy_id' => $trade->strategy_id ?? 0,
                        'strategy_name' => $trade->strategy_name ?? 'ATVIP Signals',
                        'symbol' => $trade->symbol,
                        'platform' => $trade->platform ?? 'metatrader',
                        'source' => $trade->source ?? 'atvip',
                        'trades' => [],
                        'stats' => null
                    ];
                }
                $grouped_trades[$key]['trades'][] = $trade;
            } else {
                // Group by strategy_id for BingX/MetaTrader
                $sid = $trade->strategy_id ?? 0;
                if (!isset($grouped_trades[$sid])) {
                    $grouped_trades[$sid] = [
                        'group_key' => $sid,
                        'group_label' => $trade->strategy_name ?? 'Unknown',
                        'strategy_id' => $sid,
                        'strategy_name' => $trade->strategy_name ?? 'Unknown',
                        'symbol' => $trade->symbol,
                        'platform' => $trade->platform ?? 'unknown',
                        'source' => $trade->source ?? 'bingx',
                        'trades' => [],
                        'stats' => null
                    ];
                }
                $grouped_trades[$sid]['trades'][] = $trade;
            }
        }

        // Calculate stats for each group
        foreach ($grouped_trades as $key => &$group) {
            if ($is_atvip_view) {
                // Stats by symbol + source for ATVIP
                $group['stats'] = $this->Trade_model->get_platform_statistics(
                    $user_id,
                    null,
                    null,
                    ['source' => 'atvip', 'symbol' => $group['symbol']]
                );
            } else {
                // Stats by strategy_id for others
                $group['stats'] = $this->Trade_model->get_platform_statistics($user_id, null, $group['strategy_id']);
            }
        }
        unset($group);

        $data['grouped_trades'] = $grouped_trades;
        $data['trades'] = $trades;

        // Calculate global trading statistics with source filter
        $stat_options = ['source' => $source_filter];
        $stats = $this->Trade_model->get_platform_statistics($user_id, null, $strategy, $stat_options);
        $data['stats'] = $stats;

        // Pass current filters to view
        $data['current_source'] = $source;
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
        $this->load->library('Bingx Api');

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
