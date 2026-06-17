<?php
/** Bloque proceso de corrección. In: $vm, $compact. Nada si la corrección no corrió. */
$c = $vm['correction']; $d = $vm['decimals'];
$compact = isset($compact) ? $compact : false;
if (empty($c['present'])) return;
?>
<?php if (!$compact): ?>
  <h6 class="mb-2"><i class="fas fa-satellite me-1 text-primary"></i>Proceso de corrección
    <?php if ($c['status'] === 'OK'): ?><span class="badge bg-success ms-1">OK</span>
    <?php else: ?><span class="badge bg-dark ms-1"><?= htmlspecialchars($c['error_stage'] ?: 'ERROR') ?></span><?php endif; ?>
  </h6>
<?php endif; ?>
<?php if ($c['bad']): ?>
  <div class="alert alert-warning py-2 mb-2" style="font-size:.85rem">
    <i class="fas fa-exclamation-triangle me-1"></i>
    <?php if ($c['status'] === 'ERROR'): ?>
      Corrección falló (<b><?= htmlspecialchars($c['error_stage']) ?></b>)<?php if ($c['error_message']): ?>: <?= htmlspecialchars($c['error_message']) ?><?php endif; ?> — el trade no se tomó.
    <?php else: ?>
      Vela CFD <b>desalineada</b> del objetivo (gap <?= (int)$c['bar_gap_sec'] ?>s). El factor se calculó contra una vela que no coincide con el timestamp del futuro.
    <?php endif; ?>
  </div>
<?php endif; ?>

<?php if ($compact): ?>
  <div class="d-flex flex-wrap gap-2">
    <span class="badge badge-soft pill">futuro <?= tv_num($c['fut_price'], $d) ?> @ <?= htmlspecialchars($c['fut_candle_time'] ?: '—') ?></span>
    <span class="badge badge-soft pill">CFD <?= tv_num($c['mt5_price'], $d) ?> @ <?= htmlspecialchars($c['mt5_bar_time'] ?: '—') ?></span>
    <span class="badge badge-soft pill">¿misma vela? <?= $c['candles_aligned'] ? '✓' : '✗' ?> (<?= (int)$c['bar_gap_sec'] ?>s)</span>
    <span class="badge badge-soft pill">factor <?= number_format((float)$c['corr_factor'], 6) ?> · <?= tv_num($c['deviation_pct'], 2) ?>%</span>
  </div>
<?php else: ?>
  <div class="corr-grid">
    <div><div class="lbl">Futuro (Yahoo, UTC)</div><div><b><?= tv_num($c['fut_price'], $d) ?></b> @ <?= htmlspecialchars($c['fut_candle_time'] ?: '—') ?></div></div>
    <div><div class="lbl">Vela CFD MT5 usada</div><div><b><?= tv_num($c['mt5_price'], $d) ?></b> @ <?= htmlspecialchars($c['mt5_bar_time'] ?: '—') ?> <span class="text-muted">(idx <?= (int)$c['mt5_bar_index'] ?>)</span></div></div>
    <div><div class="lbl">MT5 vivo al recibir señal</div><div><b><?= tv_num($c['signal_mt5_price'], $d) ?></b></div></div>
    <div><div class="lbl">¿Misma vela?</div><div><?php if ($c['candles_aligned']): ?><span class="text-profit">Sí (gap <?= (int)$c['bar_gap_sec'] ?>s)</span><?php else: ?><span class="text-loss">No (gap <?= (int)$c['bar_gap_sec'] ?>s)</span><?php endif; ?></div></div>
    <div><div class="lbl">Factor / Desvío</div><div><b><?= number_format((float)$c['corr_factor'], 6) ?></b> · <?= tv_num($c['deviation_pct'], 4) ?>%</div></div>
    <div><div class="lbl">Antigüedad dato futuro</div><div><?= round(((int)$c['timestamp_age_sec']) / 60, 1) ?> min</div></div>
  </div>
<?php endif; ?>
