<?php
/** Bloque volumen + gates. In: $vm, $compact.
 *  compact = línea honesta (sin aritmética que no cierra). full = fórmula completa + chips. */
$g = $vm['gates']; $d = $vm['decimals'];
$compact = isset($compact) ? $compact : false;
if (empty($g['present'])) return;
?>
<?php if ($compact): ?>
  <?php if ($g['real_volume'] !== null): ?>
    <div class="calc" style="font-size:.85rem">
      <b>Volumen</b> <?= tv_num($g['real_volume'], 2) ?> lots · riesgo <?= tv_num($g['risk_percent'], 1) ?>% del balance · R <?= tv_num($g['r_dist'], $d) ?> · T1 <?= tv_num($g['t1'], $d) ?>
    </div>
  <?php endif; ?>
<?php else: ?>
  <h6 class="mb-2"><i class="fas fa-sliders-h me-1 text-primary"></i>Volumen y gates</h6>
  <?php if ($g['real_volume'] !== null): ?>
    <div class="calc mb-2">
      <?php if ($g['risk_money'] !== null && $g['risk_per_lot'] !== null && $g['risk_per_lot'] > 0): ?>
        <?php // Fórmula completa que cierra: riesgo $ ÷ riesgo $/lote = lotes ?>
        Volumen por riesgo = riesgo (<?= tv_num($g['risk_percent'], 1) ?>% × $<?= number_format($g['acct_balance'], 2) ?> = <b>$<?= number_format($g['risk_money'], 2) ?></b>)
        ÷ <b>$<?= number_format($g['risk_per_lot'], 2) ?></b>/lote (R <?= tv_num($g['r_dist'], $d) ?>)
        = <b><?= tv_num($g['real_volume'], 2) ?> lots</b>
      <?php else: ?>
        <?php // Fallback (trade previo a v10.22, sin balance/valor de punto reportado) ?>
        Volumen <b><?= tv_num($g['real_volume'], 2) ?> lots</b> · riesgo <?= tv_num($g['risk_percent'], 1) ?>% del balance · R <?= tv_num($g['r_dist'], $d) ?>
        <div class="text-muted" style="font-size:.78rem">Sin balance/valor-de-punto reportado para mostrar la cuenta completa.</div>
      <?php endif; ?>
    </div>
  <?php endif; ?>
  <div class="d-flex flex-wrap gap-2">
    <span class="badge badge-soft pill">spread <?= tv_num($g['spread_real'], $d) ?>/<?= tv_num($g['spread_tol'], $d) ?> · <?= $g['enable_spread'] ? 'ON' : 'OFF' ?></span>
    <span class="badge badge-soft pill">slippage <?= tv_num($g['slip_real'], $d) ?>/<?= tv_num($g['slip_tol'], $d) ?> · <?= $g['enable_slip'] ? 'ON' : 'OFF' ?></span>
    <span class="badge badge-soft pill">stops_min <?= tv_num($g['stops_min'], $d) ?> · sl_dist <?= tv_num($g['sl_dist'], $d) ?></span>
    <span class="badge badge-soft pill">T1 <?= tv_num($g['t1'], $d) ?> · R <?= tv_num($g['r_dist'], $d) ?></span>
    <span class="badge badge-soft pill">BE level <?= (int)$g['be_level'] ?> · TP split <?= tv_num($g['tp_pcts'][0],0) ?>/<?= tv_num($g['tp_pcts'][1],0) ?>/<?= tv_num($g['tp_pcts'][2],0) ?>/<?= tv_num($g['tp_pcts'][3],0) ?>/<?= tv_num($g['tp_pcts'][4],0) ?></span>
  </div>
<?php endif; ?>
