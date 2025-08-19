<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Trade_model extends CI_Model
{

    public function __construct()
    {
        parent::__construct();
    }

    public function get_all_trades($user_id = null, $status = null)
    {
        if ($user_id) {
            $this->db->where('trades.user_id', $user_id);
        }

        if ($status) {
            $this->db->where('trades.status', $status);
        }

        $this->db->select('trades.*, strategies.name as strategy_name, strategies.strategy_id as strategy_external_id, users.username');
        $this->db->join('strategies', 'strategies.id = trades.strategy_id', 'left');
        $this->db->join('users', 'users.id = trades.user_id', 'left');
        $this->db->order_by('trades.created_at', 'DESC');

        return $this->db->get('trades')->result();
    }

    public function get_trade_by_id($id)
    {
        $this->db->select('trades.*, strategies.name as strategy_name, strategies.strategy_id as strategy_external_id, users.username');
        $this->db->join('strategies', 'strategies.id = trades.strategy_id', 'left');
        $this->db->join('users', 'users.id = trades.user_id', 'left');
        return $this->db->get_where('trades', array('trades.id' => $id))->row();
    }

    public function get_trade_by_order_id($order_id)
    {
        return $this->db->get_where('trades', array(
            'order_id' => $order_id
        ))->row();
    }

    public function get_trade_by_position_id($position_id, $user_id = null, $symbol = null, $timeframe = null, $side = null)
    {
        if ($user_id) {
            $this->db->where('user_id', $user_id);
        }

        $this->db->where('position_id', $position_id);
        $this->db->where('status', 'open');

        // Add additional filters for more specificity
        if ($symbol) {
            $this->db->where('symbol', $symbol);
        }

        if ($timeframe) {
            $this->db->where('timeframe', $timeframe);
        }

        if ($side) {
            $this->db->where('side', $side);
        }

        return $this->db->get('trades')->row();
    }

    /**
     * Find trade by position_id and strategy_id for closing
     * 
     * @param string $position_id Position ID from webhook
     * @param int $strategy_id Internal strategy ID 
     * @param string $environment production/sandbox
     * @return object|null Trade object or null if not found
     */
    public function find_trade_by_position_and_strategy($position_id, $strategy_id, $environment)
    {
        $this->db->where('position_id', $position_id);
        $this->db->where('strategy_id', $strategy_id);
        $this->db->where('environment', $environment);
        $this->db->where('status', 'open');
        $this->db->limit(1);

        return $this->db->get('trades')->row();
    }

    /**
     * Find trade by comprehensive criteria (fallback method)
     * 
     * @param int $user_id User ID
     * @param int $strategy_id Internal strategy ID
     * @param string $symbol Trading symbol
     * @param string $timeframe Timeframe
     * @param string $environment production/sandbox
     * @param float $quantity Quantity to match
     * @param string $side Side to look for (opposite of incoming action)
     * @return object|null Trade object or null if not found
     */
    public function find_trade_by_criteria($user_id, $strategy_id, $symbol, $timeframe, $environment, $quantity, $side)
    {
        $this->db->where('user_id', $user_id);
        $this->db->where('strategy_id', $strategy_id);
        $this->db->where('symbol', $symbol);
        $this->db->where('timeframe', $timeframe);
        $this->db->where('environment', $environment);
        $this->db->where('quantity', $quantity);
        $this->db->where('side', $side);
        $this->db->where('status', 'open');
        $this->db->order_by('created_at', 'DESC'); // Get most recent if multiple matches
        $this->db->limit(1);

        return $this->db->get('trades')->row();
    }

    /**
     * Find trade for fallback - busca posiciones abiertas con el side OPUESTO
     * 
     * @param int $user_id User ID
     * @param int $strategy_id Internal strategy ID
     * @param string $symbol Trading symbol
     * @param string $environment production/sandbox
     * @param float $quantity Quantity to match
     * @param string $incoming_action Action from webhook (BUY/SELL)
     * @return object|null Trade object or null if not found
     */
    public function find_trade_for_fallback($user_id, $strategy_id, $symbol, $environment, $quantity, $side)
    {
        // 🔥 Determinar el side OPUESTO al action que viene
        
        
        // Buscar posición abierta con el side opuesto
        $this->db->where('user_id', $user_id);
        $this->db->where('strategy_id', $strategy_id);
        $this->db->where('symbol', $symbol);
        $this->db->where('environment', $environment);
        $this->db->where('quantity', $quantity);
        $this->db->where('side', $opposite_side);
        $this->db->where('status', 'open');
        $this->db->order_by('created_at', 'ASC'); // FIFO - close oldest first
        $this->db->limit(1);

        $result = $this->db->get('trades')->row();
        return $result;
    }

    public function add_trade($data)
    {
        $this->db->insert('trades', $data);
        return $this->db->insert_id();
    }

    public function update_trade($id, $data)
    {
        $this->db->where('id', $id);
        return $this->db->update('trades', $data);
    }

    public function close_trade($id, $exit_price, $pnl)
    {
        $data = array(
            'exit_price' => $exit_price,
            'pnl' => $pnl,
            'status' => 'closed',
            'closed_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        );

        $this->db->where('id', $id);
        return $this->db->update('trades', $data);
    }

    public function delete_trade($id)
    {
        $this->db->where('id', $id);
        return $this->db->delete('trades');
    }

    public function get_total_pnl($trades)
    {
        $total_pnl = 0;
        foreach ($trades as $trade) {
            $total_pnl += isset($trade->pnl) ? $trade->pnl : 0;
        }
        return $total_pnl;
    }

    /**
     * Get trades with platform filter
     * 
     * @param int $user_id User ID filter
     * @param string $status Status filter (open/closed)
     * @param string $platform Platform filter (bingx/metatrader)
     * @param int $strategy_id Strategy filter
     * @return array Trades
     */
    public function get_trades_by_platform($user_id = null, $status = null, $platform = null, $strategy_id = null)
    {
        if ($user_id) {
            $this->db->where('trades.user_id', $user_id);
        }

        if ($status) {
            $this->db->where('trades.status', $status);
        }

        if ($platform) {
            $this->db->where('trades.platform', $platform);
        }

        if ($strategy_id) {
            $this->db->where('trades.strategy_id', $strategy_id);
        }

        $this->db->select('trades.*, strategies.name as strategy_name, strategies.strategy_id as strategy_external_id, users.username');
        $this->db->join('strategies', 'strategies.id = trades.strategy_id', 'left');
        $this->db->join('users', 'users.id = trades.user_id', 'left');
        $this->db->order_by('trades.created_at', 'DESC');

        return $this->db->get('trades')->result();
    }

    /**
     * Get platform-specific statistics
     * 
     * @param int $user_id User ID
     * @param string $platform Platform filter (bingx/metatrader/null for all)
     * @return array Statistics
     */
    public function get_platform_statistics($user_id, $platform = null)
    {
        // Base query for closed trades
        $this->db->where('user_id', $user_id);
        $this->db->where('status', 'closed');

        if ($platform) {
            $this->db->where('platform', $platform);
        }

        $trades = $this->db->get('trades')->result();

        // Calculate statistics
        $stats = [
            'total_pnl' => 0,
            'total_invested' => 0,
            'total_trades' => count($trades),
            'winning_trades' => 0,
            'losing_trades' => 0,
            'winrate' => 0,
            'profit_per_trade' => 0,
            'total_pnl_percentage' => 0
        ];

        if (empty($trades)) {
            return $stats;
        }

        foreach ($trades as $trade) {
            $stats['total_pnl'] += $trade->pnl;

            if ($trade->pnl > 0) {
                $stats['winning_trades']++;
            } else {
                $stats['losing_trades']++;
            }

            // Calculate investment (considering leverage for BingX)
            $leverage = $trade->platform == 'bingx' ? $trade->leverage : 1;
            $investment = ($trade->quantity * $trade->entry_price) / $leverage;
            $stats['total_invested'] += $investment;

            // Store for weighted percentage calculation
            if ($investment > 0) {
                $stats['trades_data'][] = [
                    'pnl_percentage' => ($trade->pnl / $investment) * 100,
                    'investment' => $investment
                ];
            }
        }

        // Calculate derived stats
        $stats['winrate'] = ($stats['winning_trades'] / $stats['total_trades']) * 100;
        $stats['profit_per_trade'] = $stats['total_pnl'] / $stats['total_trades'];

        // Calculate weighted PNL percentage
        if ($stats['total_invested'] > 0 && isset($stats['trades_data'])) {
            $weighted_percentage = 0;
            foreach ($stats['trades_data'] as $trade_data) {
                $weighted_percentage += ($trade_data['investment'] / $stats['total_invested']) * $trade_data['pnl_percentage'];
            }
            $stats['total_pnl_percentage'] = $weighted_percentage;
        }

        // Clean up temporary data
        unset($stats['trades_data']);

        return $stats;
    }
}