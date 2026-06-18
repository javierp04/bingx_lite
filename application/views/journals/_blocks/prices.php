<?php
/** Bloque precios Señal→Corregido→Real. In: $vm, $compact. Renderiza tabla + footnote. */
$p = $vm['prices']; $d = $vm['decimals'];
$compact = isset($compact) ? $compact : false;
?>
<?php if ($compact): ?>
  <!-- Compact: sin columnas de flecha, fuente chica -->
  <table class="table table-sm cmp mb-1" style="font-size:.76rem">
    <thead><tr><th></th><th>Señal</th><th>Corr.</th><th>Real</th></tr></thead>
    <tbody>
      <tr><td class="lbl">Entry</td>
          <td><?= tv_num($p['raw']['entry'], $d) ?></td>
          <td><b><?= tv_num($p['corr']['entry'], $d) ?></b></td>
          <td class="<?= $p['real']['entry'] ? 'text-profit' : 'text-muted' ?>"><?= $p['real']['entry'] ? tv_num($p['real']['entry'], $d) : '—' ?></td></tr>
      <tr><td class="lbl">SL</td>
          <td><?= tv_num($p['raw']['sl'], $d) ?></td>
          <td><?= tv_num($p['corr']['sl'], $d) ?></td>
          <td><?php if ($p['is_be']): ?><span class="badge bg-success">BE</span><?php elseif ($p['real']['sl']): ?><?= tv_num($p['real']['sl'], $d) ?><?php else: ?>—<?php endif; ?></td></tr>
      <?php for ($i = 1; $i <= 5; $i++): $hit = ($p['reached'] >= $i); ?>
      <tr><td class="lbl">TP<?= $i ?></td>
          <td><?= tv_num($p['raw']['tp'][$i], $d) ?></td>
          <td><?= tv_num($p['corr']['tp'][$i], $d) ?></td>
          <td><?php if ($hit): ?><span class="text-profit">✓</span><?php else: ?>—<?php endif; ?></td></tr>
      <?php endfor; ?>
    </tbody>
  </table>
<?php else:
  $tpr = isset($vm['tp_results']) ? $vm['tp_results'] : array();
  $has_tp = !empty($tpr);
  $total_pnl = isset($vm['meta']['pnl']) ? (float)$vm['meta']['pnl'] : 0;
  $tot_pct = 0; foreach ($tpr as $rr) { if ($rr['closed_pct'] !== null) $tot_pct += $rr['closed_pct']; }
?>
  <table class="table table-sm cmp mb-2">
    <thead><tr><th></th><th>Señal (cruda)</th><th></th><th>Corregido</th><th></th><th>Real / Ejec.</th><?php if ($has_tp): ?><th>% cerr.</th><th>PnL</th><?php endif; ?></tr></thead>
    <tbody>
      <tr><td class="lbl">Entry</td>
          <td><?= tv_num($p['raw']['entry'], $d) ?></td><td class="arrow">→</td>
          <td><b><?= tv_num($p['corr']['entry'], $d) ?></b></td><td class="arrow">→</td>
          <td class="<?= $p['real']['entry'] ? 'text-profit' : 'text-muted' ?>"><?= $p['real']['entry'] ? tv_num($p['real']['entry'], $d) : '—' ?></td>
          <?php if ($has_tp): ?><td></td><td></td><?php endif; ?></tr>
      <tr><td class="lbl">SL</td>
          <td><?= tv_num($p['raw']['sl'], $d) ?></td><td class="arrow">→</td>
          <td><?= tv_num($p['corr']['sl'], $d) ?></td><td></td>
          <td><?php if ($p['is_be']): ?><span class="badge bg-success">BE</span><?php elseif ($p['real']['sl']): ?><?= tv_num($p['real']['sl'], $d) ?><?php else: ?>—<?php endif; ?></td>
          <?php if ($has_tp): ?><td></td><td></td><?php endif; ?></tr>
      <?php for ($i = 1; $i <= 5; $i++): $hit = ($p['reached'] >= $i); $r = isset($tpr[$i]) ? $tpr[$i] : null; ?>
      <tr><td class="lbl">TP<?= $i ?></td>
          <td><?= tv_num($p['raw']['tp'][$i], $d) ?></td><td class="arrow">→</td>
          <td><?= tv_num($p['corr']['tp'][$i], $d) ?></td><td></td>
          <td><?php if ($hit): ?><span class="text-profit">✓</span><?php else: ?>—<?php endif; ?></td>
          <?php if ($has_tp): ?>
            <td><?= ($r && $r['closed_pct'] !== null) ? tv_num($r['closed_pct'], 1).'%' : '—' ?></td>
            <td class="<?= ($r && $r['pnl'] !== null) ? ($r['pnl'] >= 0 ? 'text-profit' : 'text-loss') : '' ?>"><?= ($r && $r['pnl'] !== null) ? (($r['pnl'] >= 0 ? '+' : '').tv_num($r['pnl'], 2)) : '—' ?></td>
          <?php endif; ?></tr>
      <?php endfor; ?>
      <?php if ($has_tp): ?>
      <tr style="border-top:2px solid #dee2e6">
        <td class="lbl">Total</td>
        <td colspan="6" class="text-end text-muted">cerrado en TPs: <b><?= tv_num($tot_pct, 1) ?>%</b> · PnL total del trade</td>
        <td class="<?= $total_pnl >= 0 ? 'text-profit' : 'text-loss' ?>"><b><?= ($total_pnl >= 0 ? '+' : '').tv_num($total_pnl, 2) ?></b></td>
      </tr>
      <?php endif; ?>
    </tbody>
  </table>
<?php endif; ?>
<div class="text-muted" style="font-size:.76rem">
  <?php if ($p['factor'] != 1.0): ?>
    factor <b><?= number_format($p['factor'], 6) ?></b> · precio = crudo ÷ factor
  <?php else: ?>
    Sin corrección (factor 1.0)
  <?php endif; ?>
</div>
