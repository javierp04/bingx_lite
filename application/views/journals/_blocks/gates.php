<?php
/** Bloque volumen + gates. In: $vm, $compact. Nada si no hay snapshot. */
$g = $vm['gates'];
$compact = isset($compact) ? $compact : false;
if (empty($g['present'])) return;
?>
<?php if (!$compact && $g['real_volume'] !== null && $g['r_dist'] !== null): ?>
  <div class="calc mb-2">
    Volumen por riesgo = (Balance × RISK% <?= tv_num($g['risk_percent'], 1) ?>) ÷ R(<?= tv_num($g['r_dist'], 5) ?>) = <b><?= tv_num($g['real_volume'], 2) ?> lots</b>
  </div>
<?php endif; ?>
<div class="d-flex flex-wrap gap-2">
  <?php if ($compact && $g['real_volume'] !== null): ?>
    <span class="badge badge-soft pill">vol <?= tv_num($g['real_volume'], 2) ?> · riesgo <?= tv_num($g['risk_percent'], 1) ?>% ÷ R(<?= tv_num($g['r_dist'], 2) ?>)</span>
  <?php endif; ?>
  <span class="badge badge-soft pill">spread <?= tv_num($g['spread_real'], 3) ?>/<?= tv_num($g['spread_tol'], 3) ?> · <?= $g['enable_spread'] ? 'ON' : 'OFF' ?></span>
  <span class="badge badge-soft pill">slippage <?= tv_num($g['slip_real'], 3) ?>/<?= tv_num($g['slip_tol'], 3) ?> · <?= $g['enable_slip'] ? 'ON' : 'OFF' ?></span>
  <span class="badge badge-soft pill">stops_min <?= tv_num($g['stops_min'], 5) ?> · sl_dist <?= tv_num($g['sl_dist'], 5) ?></span>
  <span class="badge badge-soft pill">T1 <?= tv_num($g['t1'], 5) ?> · R <?= tv_num($g['r_dist'], 5) ?></span>
  <?php if (!$compact): ?>
    <span class="badge badge-soft pill">BE level <?= (int)$g['be_level'] ?> · TP split <?= tv_num($g['tp_pcts'][0],0) ?>/<?= tv_num($g['tp_pcts'][1],0) ?>/<?= tv_num($g['tp_pcts'][2],0) ?>/<?= tv_num($g['tp_pcts'][3],0) ?>/<?= tv_num($g['tp_pcts'][4],0) ?></span>
  <?php endif; ?>
</div>
