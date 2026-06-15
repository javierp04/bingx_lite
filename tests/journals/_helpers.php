<?php
// Permite require directo de las libraries (que tienen el guard defined('BASEPATH')).
defined('BASEPATH') OR define('BASEPATH', true);

$GLOBALS['__t'] = array('n' => 0, 'fail' => 0);

function check($cond, $msg) {
    $GLOBALS['__t']['n']++;
    if ($cond) {
        echo "  PASS  $msg\n";
    } else {
        $GLOBALS['__t']['fail']++;
        echo "  FAIL  $msg\n";
    }
}

function check_eq($actual, $expected, $msg) {
    $ok = ($actual === $expected);
    if (!$ok) $msg .= "  [got=" . var_export($actual, true) . " want=" . var_export($expected, true) . "]";
    check($ok, $msg);
}

function done() {
    $t = $GLOBALS['__t'];
    echo "\n{$t['n']} checks, {$t['fail']} failed\n";
    exit($t['fail'] > 0 ? 1 : 0);
}
