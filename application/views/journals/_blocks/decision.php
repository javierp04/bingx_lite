<?php
/** Bloque decisión de order type. In: $vm, $compact. Nada si no hay snapshot. */
$dec = $vm['decision']; $d = $vm['decimals'];
$compact = isset($compact) ? $compact : false;
if (empty($dec['present'])) return;
$ol = function_exists('journal_order_label') ? journal_order_label($dec['order_type']) : $dec['order_type'];
// Precisión: distancias/escalas en los decimales del símbolo ($d); el coeficiente K es un ratio.
?>
<?php if ($compact): ?>
  <div class="calc mb-2" style="font-size:.85rem">
    <b>¿Por qué <?= htmlspecialchars($ol) ?>?</b>
    dist <?= tv_num($dec['dist_entry'], $d) ?> · lado <?= htmlspecialchars($dec['side'] ?: '—') ?>
    <?php if ($dec['k_band'] !== null && $dec['t1'] !== null): ?>
      vs banda K·T1 (<?= tv_num($dec['kcoef'], 4) ?>×<?= tv_num($dec['t1'], $d) ?>=<?= tv_num($dec['k_band'], $d) ?>) ⟶ <b><?= htmlspecialchars($dec['order_type']) ?></b>
    <?php endif; ?>
  </div>
<?php else: ?>
  <h6 class="mb-2"><i class="fas fa-question-circle me-1 text-primary"></i>¿Por qué <b><?= htmlspecialchars($dec['order_type']) ?></b>?</h6>
  <div class="calc">
    Mercado al llegar la señal: <b><?= tv_num($dec['price_signal'], $d) ?></b> · Entry corregido: <b><?= tv_num($dec['entry'], $d) ?></b><br>
    Distancia = <b><?= tv_num($dec['dist_entry'], $d) ?></b> · Lado <b><?= htmlspecialchars($dec['side'] ?: '—') ?></b><br>
    <?php if ($dec['k_band'] !== null && $dec['t1'] !== null): ?>
      Banda MARKET = K (<?= tv_num($dec['kcoef'], 4) ?>) × T1 (<?= tv_num($dec['t1'], $d) ?>) = <b><?= tv_num($dec['k_band'], $d) ?></b><br>
      <?= tv_num($dec['dist_entry'], $d) ?> (distancia) vs <?= tv_num($dec['k_band'], $d) ?> (banda) ⟶ <b><?= htmlspecialchars($dec['order_type']) ?></b>
    <?php endif; ?>
  </div>
<?php endif; ?>
