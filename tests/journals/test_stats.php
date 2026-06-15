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

$rows = array(
    array('close_reason'=>'ORDER_CANCELLED','gross_pnl'=>0.0,'order_type'=>'LIMIT','exit_level'=>-999,'dist_entry'=>1.5,'t1'=>7.0),
    array('close_reason'=>'CLOSED_COMPLETE','gross_pnl'=>150.0,'order_type'=>'MARKET','exit_level'=>3,'dist_entry'=>1.0,'t1'=>20.0),
    array('close_reason'=>'CLOSED_STOPLOSS','gross_pnl'=>-80.0,'order_type'=>'STOP','exit_level'=>-1,'dist_entry'=>1.0,'t1'=>20.0),
);
$k = $s->per_symbol($rows);
check_eq($k['total'], 3, 'total 3');
check_eq($k['operated'], 2, 'operated 2');
check_eq($k['cancelled'], 1, 'cancelled 1');
check_eq($k['wins'], 1, 'wins 1');
check_eq($k['losses'], 1, 'losses 1');
check_eq($k['win_rate'], 50.0, 'win_rate 50');
check_eq($k['pnl_total'], 70.0, 'pnl_total 70');
check_eq($k['pnl_avg'], 35.0, 'pnl_avg 35 (over operated)');
check_eq($k['order_types']['LIMIT'], 1, 'order_types count');
check_eq($k['exit_levels']['3'], 1, 'exit_levels count keyed by string');

$z = $s->per_symbol(array());
check_eq($z['win_rate'], null, 'win_rate null on 0 operated');
check_eq($z['pnl_total'], 0.0, 'pnl_total 0 on empty');

done();
