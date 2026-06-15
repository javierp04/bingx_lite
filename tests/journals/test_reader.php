<?php
require __DIR__ . '/_helpers.php';
require __DIR__ . '/../../application/libraries/Journal_reader.php';

$r = new Journal_reader(array('path' => __DIR__ . '/fixtures'));

// parse_filename
check_eq($r->parse_filename('bxlite_journal_1_ES.csv'),
    array('kind' => 'journal', 'user_id' => 1, 'symbol' => 'ES'), 'parse journal name');
check_eq($r->parse_filename('bxlite_state_1_EURUSD.json'),
    array('kind' => 'state', 'user_id' => 1, 'symbol' => 'EURUSD'), 'parse state name');
check_eq($r->parse_filename('bxlite_live_2_GC.csv'),
    array('kind' => 'live', 'user_id' => 2, 'symbol' => 'GC'), 'parse live name');
check_eq($r->parse_filename('get.sh'), null, 'non-journal name -> null');
check_eq($r->parse_filename('bxlite_journal_1_BTC-USD.csv'),
    array('kind' => 'journal', 'user_id' => 1, 'symbol' => 'BTC-USD'), 'symbol with dash');

// parse_csv
$es = $r->parse_csv(__DIR__ . '/fixtures/bxlite_journal_1_ES.csv');
check_eq(count($es), 3, 'ES journal has 3 rows');
check_eq($es[0]['signal_id'], 5, 'signal_id cast to int');
check_eq($es[0]['close_reason'], 'ORDER_CANCELLED', 'close_reason string');
check_eq($es[1]['gross_pnl'], 150.0, 'gross_pnl cast to float');
check_eq($es[0]['ts_signal'], '2026-06-15T13:03:42', 'timestamp stays string');

// short row skipped, valid kept
$bad = $r->parse_csv(__DIR__ . '/fixtures/bxlite_journal_1_BAD.csv');
check_eq(count($bad), 1, 'short row skipped, 1 valid row kept');
check_eq($bad[0]['signal_id'], 11, 'valid row is the second one');

// header-only file -> 0 rows
$live = $r->parse_csv(__DIR__ . '/fixtures/bxlite_live_1_ES.csv');
check_eq(count($live), 0, 'header-only live -> 0 rows');

// missing file -> 0 rows, no fatal
check_eq(count($r->parse_csv(__DIR__ . '/fixtures/nope.csv')), 0, 'missing file -> 0 rows');

done();
