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
}
