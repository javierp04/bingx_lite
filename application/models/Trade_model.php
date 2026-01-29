<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Trade_model extends CI_Model
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     /**
     * Unified method to find multiple trades
     * 
     * @param array $filters Associative array of filters (null values ignored)
     * @param array $options Query options (joins, order_by, limit, etc.)
     * @return array Array of trade objects
     */
    public function find_trades($filters = [], $options = [])
    {
        // Default options - CAMBIAR ORDEN POR DEFECTO
        $defaults = [
            'with_relations' => false,
            'order_by' => 'trades.status ASC, trades.created_at DESC', // OPEN primero, luego por fecha
            'limit' => null
        ];
        $options = array_merge($defaults, $options);

        // Force JOIN if we need platform filter or with_relations is true
        $need_join = $options['with_relations'] || isset($filters['platform']);

        // Base query
        if ($need_join) {
            $this->db->select('trades.*, strategies.name as strategy_name, strategies.strategy_id as strategy_external_id, strategies.platform, users.username');
            $this->db->join('strategies', 'strategies.id = trades.strategy_id', 'left');
            $this->db->join('users', 'users.id = trades.user_id', 'left');
        }

        // Apply filters (only non-null values)
        foreach ($filters as $field => $value) {
            if ($value !== null) {
                // Handle special field mappings
                if ($field === 'platform') {
                    $this->db->where('strategies.platform', $value);
                } elseif ($field === 'strategy_id') {
                    // NUEVO: manejar strategy_id correctamente
                    $this->db->where('trades.strategy_id', $value);
                } elseif ($need_join && in_array($field, ['user_id', 'status'])) {
                    // Calificar campos que existen en trades cuando hay JOIN
                    $this->db->where('trades.' . $field, $value);
                } else {
                    $this->db->where($field, $value);
                }
            }
        }

        // Apply ordering - MEJORAR LÓGICA DE ORDENAMIENTO
        if ($options['order_by']) {
            $order_parts = explode(', ', $options['order_by']);
            foreach ($order_parts as $order_part) {
                $parts = explode(' ', trim($order_part));
                $field = $parts[0];
                $direction = isset($parts[1]) ? $parts[1] : 'ASC';
                $this->db->order_by($field, $direction);
            }
        }

        // Apply limit
        if ($options['limit']) {
            $this->db->limit($options['limit']);
        }

        return $this->db->get('trades')->result();
    }

    /**
     * Unified method to find a single trade
     * 
     * @param array $filters Associative array of filters (null values ignored)
     * @param array $options Query options (joins, order_by)
     * @return object|null Single trade object or null
     */
    public function find_trade($filters = [], $options = [])
    {
        $options['limit'] = 1;
        $results = $this->find_trades($filters, $options);
        return !empty($results) ? $results[0] : null;
    }

    /**
     * Add new trade
     * 
     * @param array $data Trade data
     * @return int Insert ID
     */
    public function add_trade($data)
    {
        $this->db->insert('trades', $data);
        return $this->db->insert_id();
    }

    /**
     * Update trade
     * 
     * @param int $id Trade ID
     * @param array $data Update data
     * @return bool Success status
     */
    public function update_trade($id, $data)
    {
        $this->db->where('id', $id);
        return $this->db->update('trades', $data);
    }

    /**
     * Close trade with exit price and PNL
     * 
     * @param int $id Trade ID
     * @param float $exit_price Exit price
     * @param float $pnl Profit/Loss
     * @return bool Success status
     */
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

    /**
     * Delete trade
     * 
     * @param int $id Trade ID
     * @return bool Success status
     */
    public function delete_trade($id)
    {
        $this->db->where('id', $id);
        return $this->db->delete('trades');
    }

    /**
     * Calculate total PNL from trades array
     * 
     * @param array $trades Array of trade objects
     * @return float Total PNL
     */
    public function get_total_pnl($trades)
    {
        $total_pnl = 0;
        foreach ($trades as $trade) {
            $total_pnl += isset($trade->pnl) ? $trade->pnl : 0;
        }
        return $total_pnl;
    }

    /**
     * Get platform-specific statistics
     *
     * @param int $user_id User ID
     * @param string $platform Platform filter (bingx/metatrader/null for all)
     * @param int $strategy_id Strategy filter (strategy ID or null for all)
     * @return array Statistics
     */
    public function get_platform_statistics($user_id, $platform = null, $strategy_id = null)
    {
        // Always JOIN with strategies to get platform
        $this->db->select('trades.*, strategies.platform');
        $this->db->from('trades');
        $this->db->join('strategies', 'strategies.id = trades.strategy_id', 'left');

        // Apply filters
        $this->db->where('trades.user_id', $user_id);
        $this->db->where('trades.status', 'closed');

        if ($platform) {
            $this->db->where('strategies.platform', $platform);
        }

        if ($strategy_id) {
            $this->db->where('trades.strategy_id', $strategy_id);
        }

        $trades = $this->db->get()->result();

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
