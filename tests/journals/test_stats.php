<?php
require __DIR__ . '/_helpers.php';
require __DIR__ . '/../../application/libraries/Journal_stats.php';

$s = new Journal_stats();

$cancelled = array('close_reason' => 'ORDER_CANCELLED', 'gross_pnl' => 0.0);
$win       = array('close_reason' => 'CLOSED_COMPLETE', 'gross_pnl' => 150.0);
$loss      = array('close_reason' => 'CLOSED_STOPLOSS', 'gross_pnl' => -80.0);
$failed    = array('close_reason' => 'EXECUTION_FAILED', 'gross_pnl' => 0.0);

check_eq($s->is_operated($cancelled), false, 'cancelled not operated');
check_eq($s->is_operated($failed), false, 'failed not operated');
check_eq($s->is_operated($win), true, 'win operated');
check_eq($s->is_cancelled($cancelled), true, 'is_cancelled');
check_eq($s->is_cancelled($win), false, 'win not cancelled');
check_eq($s->is_win($win), true, 'win is win');
check_eq($s->is_win($loss), false, 'loss not win');
check_eq($s->is_win($cancelled), false, 'cancelled not win');

done();
