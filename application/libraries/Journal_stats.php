<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Calcula KPIs/agregados a partir de filas de journal ya parseadas (arrays -> stats).
 */
class Journal_stats {

    // close_reason que significan "nunca operó" (espejo de is_failure_reason del backend).
    private $notOperated = array(
        'ORDER_CANCELLED', 'INVALID_STOPLOSS', 'INVALID_TPS', 'INVALID_OPTYPE',
        'INVALID_ENTRY', 'PRICE_CORRECTION_ERROR', 'SPREAD_TOO_HIGH', 'VOLUME_ERROR',
        'SL_TOO_CLOSE', 'EXECUTION_FAILED'
    );

    public function __construct($params = array()) {}

    private function val($row, $k, $def = 0) {
        return isset($row[$k]) ? $row[$k] : $def;
    }

    public function is_operated($row) {
        return !in_array((string)$this->val($row, 'close_reason', ''), $this->notOperated, true);
    }

    public function is_cancelled($row) {
        return (string)$this->val($row, 'close_reason', '') === 'ORDER_CANCELLED';
    }

    public function is_win($row) {
        return $this->is_operated($row) && (float)$this->val($row, 'gross_pnl', 0) > 0;
    }

    /** KPIs de un conjunto de filas (un símbolo). */
    public function per_symbol($rows) {
        $total = count($rows);
        $operated = 0; $cancelled = 0; $wins = 0; $losses = 0;
        $pnl = 0.0; $sumDist = 0.0; $sumT1 = 0.0;
        $orderTypes = array(); $exitLevels = array();
        foreach ($rows as $row) {
            $ot = (string)$this->val($row, 'order_type', '');
            $el = (string)$this->val($row, 'exit_level', '');
            if ($ot !== '') $orderTypes[$ot] = (isset($orderTypes[$ot]) ? $orderTypes[$ot] : 0) + 1;
            $exitLevels[$el] = (isset($exitLevels[$el]) ? $exitLevels[$el] : 0) + 1;
            $sumDist += (float)$this->val($row, 'dist_entry', 0);
            $sumT1   += (float)$this->val($row, 't1', 0);
            if ($this->is_cancelled($row)) $cancelled++;
            if ($this->is_operated($row)) {
                $operated++;
                $pnl += (float)$this->val($row, 'gross_pnl', 0);
                if ($this->is_win($row)) $wins++; else $losses++;
            }
        }
        return array(
            'total'          => $total,
            'operated'       => $operated,
            'cancelled'      => $cancelled,
            'wins'           => $wins,
            'losses'         => $losses,
            'win_rate'       => $operated > 0 ? round($wins / $operated * 100, 1) : null,
            'pnl_total'      => round($pnl, 2),
            'pnl_avg'        => $operated > 0 ? round($pnl / $operated, 2) : 0.0,
            'avg_dist_entry' => $total > 0 ? round($sumDist / $total, 5) : 0.0,
            'avg_t1'         => $total > 0 ? round($sumT1 / $total, 5) : 0.0,
            'order_types'    => $orderTypes,
            'exit_levels'    => $exitLevels,
        );
    }

    /** PnL acumulado de trades operados, ordenado por ts_signal. [['ts'=>,'cum'=>],...]. */
    public function cumulative_pnl($rows) {
        $op = array();
        foreach ($rows as $row) if ($this->is_operated($row)) $op[] = $row;
        usort($op, function($a, $b) {
            return strcmp((string)(isset($a['ts_signal'])?$a['ts_signal']:''),
                          (string)(isset($b['ts_signal'])?$b['ts_signal']:''));
        });
        $out = array(); $cum = 0.0;
        foreach ($op as $row) {
            $cum += (float)$this->val($row, 'gross_pnl', 0);
            $out[] = array('ts' => (string)$this->val($row, 'ts_signal', ''), 'cum' => round($cum, 2));
        }
        return $out;
    }

    /** Conteo por valor de un campo. */
    public function distribution($rows, $field) {
        $out = array();
        foreach ($rows as $row) {
            $kk = (string)$this->val($row, $field, '');
            $out[$kk] = (isset($out[$kk]) ? $out[$kk] : 0) + 1;
        }
        return $out;
    }
}
