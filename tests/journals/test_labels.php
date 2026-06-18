<?php
require __DIR__ . '/_helpers.php';
require __DIR__ . '/../../application/helpers/journal_labels_helper.php';

// exit_level -> descripción
check_eq(journal_exit_label('1'), 'TP1', 'exit 1 -> TP1');
check_eq(journal_exit_label('5'), 'TP5', 'exit 5 -> TP5');
check_eq(journal_exit_label('-1'), 'Stop loss', 'exit -1 -> Stop loss');
check_eq(journal_exit_label('-998'), 'Señal inválida', 'exit -998');
check_eq(journal_exit_label('-999'), 'Error/gate/cancel', 'exit -999');
check_eq(journal_exit_label('0'), 'Sin TP', 'exit 0 -> Sin TP (cerró sin TP)');
check_eq(journal_exit_label(''), 'En vivo', 'exit vacío/NULL -> En vivo (abierto)');
check_eq(journal_exit_label('42'), '42', 'exit desconocido -> código crudo');
check_eq(journal_exit_label(3), 'TP3', 'exit acepta int');

// close_reason -> descripción
check_eq(journal_reason_label('CLOSED_COMPLETE'), 'Todos los TP', 'reason complete');
check_eq(journal_reason_label('CLOSED_STOPLOSS'), 'Stop Loss', 'reason stoploss');
check_eq(journal_reason_label('ORDER_CANCELLED'), 'Cancelada', 'reason cancelled');
check_eq(journal_reason_label('EXECUTION_FAILED'), 'Broker rechazó', 'reason exec failed');
check_eq(journal_reason_label('CLOSED_TIME'), 'Cierre por horario', 'reason cierre por horario');
check_eq(journal_reason_label('SL_TOO_CLOSE'), 'SL muy cerca', 'reason sl too close');
check_eq(journal_reason_label('WEIRD_CODE'), 'WEIRD_CODE', 'reason desconocido -> código crudo');
check_eq(journal_reason_label(''), '—', 'reason vacío -> dash');

// close_reason -> [clase, descripción]
$m = journal_reason_meta('CLOSED_STOPLOSS');
check_eq($m[0], 'bg-danger', 'meta clase stoploss');
check_eq($m[1], 'Stop Loss', 'meta texto stoploss');
$mu = journal_reason_meta('NOPE');
check_eq($mu[0], 'bg-secondary', 'meta clase default');
check_eq($mu[1], 'NOPE', 'meta texto default -> código');

// order_type -> descripción (ambas representaciones)
check_eq(journal_order_label('ORDER_TYPE_BUY'), 'Market Buy', 'order enum buy');
check_eq(journal_order_label('ORDER_TYPE_SELL_LIMIT'), 'Sell Limit', 'order enum sell limit');
check_eq(journal_order_label('MARKET'), 'Mercado', 'order decision market');
check_eq(journal_order_label('MARKET_FB'), 'Mercado (fallback)', 'order decision fallback');
check_eq(journal_order_label(''), '—', 'order vacío -> dash');

// clase bootstrap -> hex (datasets de charts)
check_eq(journal_class_hex('bg-success'), '#28a745', 'hex success');
check_eq(journal_class_hex('bg-danger'), '#dc3545', 'hex danger');
check_eq(journal_class_hex('bg-dark'), '#343a40', 'hex dark');
check_eq(journal_class_hex('bg-info'), '#0dcaf0', 'hex info');
check_eq(journal_class_hex('bg-desconocida'), '#6c757d', 'hex default');

done();
