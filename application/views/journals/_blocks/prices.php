<?php
/** Bloque precios Señal→Corregido→Real. In: $vm. Renderiza solo la tabla + footnote. */
$p = $vm['prices']; $d = $vm['decimals'];
?>
<table class="table table-sm cmp mb-2">
  <thead><tr><th></th><th>Señal (cruda)</th><th></th><th>Corregido</th><th></th><th>Real / Ejec.</th></tr></thead>
  <tbody>
    <tr><td class="lbl">Entry</td>
        <td><?= tv_num($p['raw']['entry'], $d) ?></td><td class="arrow">→</td>
        <td><b><?= tv_num($p['corr']['entry'], $d) ?></b></td><td class="arrow">→</td>
        <td class="<?= $p['real']['entry'] ? 'text-profit' : 'text-muted' ?>"><?= $p['real']['entry'] ? tv_num($p['real']['entry'], $d) : '—' ?></td></tr>
    <tr><td class="lbl">SL</td>
        <td><?= tv_num($p['raw']['sl'], $d) ?></td><td class="arrow">→</td>
        <td><?= tv_num($p['corr']['sl'], $d) ?></td><td></td>
        <td><?php if ($p['is_be']): ?><span class="badge bg-success">BE</span><?php elseif ($p['real']['sl']): ?><?= tv_num($p['real']['sl'], $d) ?><?php else: ?>—<?php endif; ?></td></tr>
    <?php for ($i = 1; $i <= 5; $i++): $hit = ($p['reached'] >= $i); ?>
    <tr><td class="lbl">TP<?= $i ?></td>
        <td><?= tv_num($p['raw']['tp'][$i], $d) ?></td><td class="arrow">→</td>
        <td><?= tv_num($p['corr']['tp'][$i], $d) ?></td><td></td>
        <td><?php if ($hit): ?><span class="text-profit">✓</span><?php else: ?>—<?php endif; ?></td></tr>
    <?php endfor; ?>
  </tbody>
</table>
<div class="text-muted" style="font-size:.78rem">
  <?php if ($p['factor'] != 1.0): ?>
    Corrección futuros→CFD · factor <b><?= number_format($p['factor'], 6) ?></b> · precio = crudo ÷ factor
  <?php else: ?>
    Sin corrección de precios (factor 1.0)
  <?php endif; ?>
</div>
