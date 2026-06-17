<?php
/** Bloque volumen + gates. In: $vm, $compact. Nada si no hay snapshot.
 *  El cálculo de volumen (riesgo ÷ R) va SOLO en el detalle (full); la card no lo muestra. */
$g = $vm['gates']; $d = $vm['decimals'];
$compact = isset($compact) ? $compact : false;
if (empty($g['present'])) return;
?>
<?php if (!$compact): ?>
  <h6 class="mb-2"><i class="fas fa-sliders-h me-1 text-primary"></i>Volumen y gates</h6>
<?php endif; ?>
<?php if (!$compact && $g['real_volume'] !== null && $g['r_dist'] !== null): ?>
  <div class="calc mb-2">
    Volumen por riesgo = (Balance × RISK% <?= tv_num($g['risk_percent'], 1) ?>) ÷ R(<?= tv_num($g['r_dist'], $d) ?>) = <b><?= tv_num($g['real_volume'], 2) ?> lots</b>
  </div>
<?php endif; ?>
<div class="d-flex flex-wrap gap-2">
  <span class="badge badge-soft pill">spread <?= tv_num($g['spread_real'], $d) ?>/<?= tv_num($g['spread_tol'], $d) ?> · <?= $g['enable_spread'] ? 'ON' : 'OFF' ?></span>
  <span class="badge badge-soft pill">slippage <?= tv_num($g['slip_real'], $d) ?>/<?= tv_num($g['slip_tol'], $d) ?> · <?= $g['enable_slip'] ? 'ON' : 'OFF' ?></span>
  <span class="badge badge-soft pill">stops_min <?= tv_num($g['stops_min'], $d) ?> · sl_dist <?= tv_num($g['sl_dist'], $d) ?></span>
  <span class="badge badge-soft pill">T1 <?= tv_num($g['t1'], $d) ?> · R <?= tv_num($g['r_dist'], $d) ?></span>
  <?php if (!$compact): ?>
    <span class="badge badge-soft pill">BE level <?= (int)$g['be_level'] ?> · TP split <?= tv_num($g['tp_pcts'][0],0) ?>/<?= tv_num($g['tp_pcts'][1],0) ?>/<?= tv_num($g['tp_pcts'][2],0) ?>/<?= tv_num($g['tp_pcts'][3],0) ?>/<?= tv_num($g['tp_pcts'][4],0) ?></span>
  <?php endif; ?>
</div>
