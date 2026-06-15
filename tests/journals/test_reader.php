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

done();
