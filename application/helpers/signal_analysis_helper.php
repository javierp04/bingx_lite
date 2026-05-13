<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Signal Analysis Helper
 * Transforms raw AI label_prices into the processed signal format the EA consumes.
 */

if (!function_exists('transform_analysis_data')) {
    function transform_analysis_data($raw_json)
    {
        if (!is_array($raw_json) || !isset($raw_json['op_type']) || !isset($raw_json['label_prices'])) {
            return null;
        }

        $prices = $raw_json['label_prices'];
        $op_type = strtoupper(trim($raw_json['op_type']));
        $n = is_array($prices) ? count($prices) : 0;

        if ($n < 7) {
            return null;
        }

        $prices = array_map('floatval', $prices);

        if ($op_type === 'LONG') {
            $sl1 = $prices[$n - 1];
            $sl2 = $prices[$n - 2];
            $entry = $prices[$n - 3];
            $tps = array_reverse(array_slice($prices, 0, $n - 3));

            return [
                'op_type'  => $op_type,
                'stoploss' => [$sl1, $sl2],
                'entry'    => $entry,
                'tps'      => $tps,
            ];
        }

        if ($op_type === 'SHORT') {
            return [
                'op_type'  => $op_type,
                'stoploss' => [$prices[0], $prices[1]],
                'entry'    => $prices[2],
                'tps'      => array_slice($prices, 3),
            ];
        }

        return null;
    }
}
