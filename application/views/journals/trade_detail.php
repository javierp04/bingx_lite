<?php
/**
 * Detalle unificado de un trade (estilo mock).
 * Fuente preferente: ea_trade_snapshots / ea_price_corrections / ea_trade_events.
 * Fallback (trades previos a v10.21): blobs execution_data / mt_corrected_data / event_log.
 */
$d    = (int)(isset($signal->display_decimals) ? $signal->display_decimals : 5);
$snap = isset($snapshot) ? $snapshot : null;
$corr = isset($correction) ? $correction : null;
$op   = strtoupper($signal->op_type ?: ($snap->dir ?? ''));

function jd_num($v, $dec = 2) { return is_numeric($v) ? number_format((float)$v, $dec) : '—'; }
function jd_has($o, $f) { return $o && isset($o->$f) && $o->$f !== null && $o->$f !== ''; }

// ---- Precios: señal cruda -> corregido -> real ----
$mt_corr = !empty($signal->mt_corrected_data) ? json_decode($signal->mt_corrected_data, true) : null;
$mt_raw  = !empty($signal->mt_execution_data) ? json_decode($signal->mt_execution_data, true) : null;
$factor  = jd_has($snap, 'corr_factor') ? (float)$snap->corr_factor : 1.0;

$c_entry = jd_has($snap, 'entry') ? (float)$snap->entry : (isset($mt_corr['entry']) ? (float)$mt_corr['entry'] : 0);
$c_sl    = jd_has($snap, 'sl')    ? (float)$snap->sl    : (isset($mt_corr['stoploss'][0]) ? (float)$mt_corr['stoploss'][0] : 0);
$c_tps   = array();
for ($i = 1; $i <= 5; $i++) {
    $c_tps[$i] = jd_has($snap, 'tp'.$i) ? (float)$snap->{'tp'.$i}
               : (isset($mt_corr['tps'][$i-1]) ? (float)$mt_corr['tps'][$i-1] : 0);
}
$r_entry = jd_has($snap, 'entry_raw') ? (float)$snap->entry_raw : (isset($mt_raw['entry']) ? (float)$mt_raw['entry'] : ($c_entry * $factor));
$r_sl    = jd_has($snap, 'sl_raw')    ? (float)$snap->sl_raw    : (isset($mt_raw['stoploss'][0]) ? (float)$mt_raw['stoploss'][0] : ($c_sl * $factor));
$r_tps   = array();
for ($i = 1; $i <= 5; $i++) {
    $r_tps[$i] = isset($mt_raw['tps'][$i-1]) ? (float)$mt_raw['tps'][$i-1] : ($c_tps[$i] * $factor);
}
$real_entry = $signal->real_entry_price ?: (jd_has($snap, 'real_entry') ? $snap->real_entry : 0);
$real_sl    = $signal->real_stop_loss ?: 0;

// Nivel máximo alcanzado (para marcar TPs ejecutados)
$reached = max(
    (int)$signal->current_level,
    (int)($signal->exit_level ?? 0),
    jd_has($snap, 'max_level') ? (int)$snap->max_level : 0
);
$is_be = ($real_sl > 0 && $real_entry > 0 && abs($real_sl - $real_entry) < pow(10, -$d));

// Estado / cierre
$closed_reason = $signal->close_reason ?: '';
$status_label  = $closed_reason ? journal_reason_label($closed_reason) : strtoupper($signal->status);
$pnl  = (float)$signal->gross_pnl;
$dir_class = ($op === 'LONG') ? 'bg-success' : 'bg-danger';
?>
<style>
  .text-profit{color:#28a745;} .text-loss{color:#dc3545;}
  .lbl{font-size:.72rem;text-transform:uppercase;letter-spacing:.04em;color:#8a94a6;}
  .calc{background:#eef4ff;border-left:4px solid #0d6efd;border-radius:6px;padding:14px 16px;font-size:.92rem;}
  .calc b{color:#0d6efd;}
  .cmp td,.cmp th{padding:.35rem .6rem;font-variant-numeric:tabular-nums;}
  .cmp .arrow{color:#8a94a6;}
  .tl{position:relative;margin-left:8px;}
  .tl:before{content:"";position:absolute;left:9px;top:4px;bottom:4px;width:2px;background:#dde3ea;}
  .tl-item{position:relative;padding:0 0 18px 34px;}
  .tl-dot{position:absolute;left:0;top:2px;width:20px;height:20px;border-radius:50%;display:flex;align-items:center;justify-content:center;color:#fff;font-size:.6rem;}
  .tl-item h6{margin:0;font-size:.92rem;} .tl-item small{color:#6c757d;}
  .pill{font-size:.7rem;} .badge-soft{background:#eef4ff;color:#0d6efd;}
  .corr-grid{display:flex;flex-wrap:wrap;gap:18px;font-variant-numeric:tabular-nums;}
  .corr-grid .lbl{margin-bottom:2px;}
</style>

<?php
// Breadcrumb parametrizable: admin -> Journals; dueño -> Mi Trading.
$bc_home_url   = isset($bc_home_url) ? $bc_home_url : base_url('journals');
$bc_home_label = isset($bc_home_label) ? $bc_home_label : 'Journals';
?>
<!-- Breadcrumb -->
<nav style="font-size:.85rem" class="mb-2">
  <a href="<?= htmlspecialchars($bc_home_url) ?>"><?= htmlspecialchars($bc_home_label) ?></a> <span class="text-muted">/</span>
  <a href="<?= htmlspecialchars($back_url) ?>"><?= htmlspecialchars($sym) ?></a> <span class="text-muted">/</span>
  <span class="text-muted">Trade #<?= (int)$signal->id ?></span>
</nav>

<!-- Header -->
<div class="card"><div class="card-body d-flex flex-wrap justify-content-between align-items-center">
  <div>
    <h3 class="mb-1"><?= htmlspecialchars($signal->ticker_symbol) ?>
      <span class="badge <?= $dir_class ?>"><?= htmlspecialchars($op ?: '—') ?></span>
      <span class="badge bg-secondary" title="<?= htmlspecialchars($closed_reason ?: $signal->status) ?>"><?= htmlspecialchars($status_label) ?></span>
    </h3>
    <div class="text-muted" style="font-size:.85rem">
      Señal <?= htmlspecialchars($signal->created_at) ?>
      <?php if ($signal->updated_at): ?> · actualizado <?= htmlspecialchars($signal->updated_at) ?><?php endif; ?>
      <?php if ($signal->order_type): ?> · <?= htmlspecialchars(journal_order_label($signal->order_type)) ?><?php endif; ?>
      <?php if ($signal->real_volume): ?> · vol <?= jd_num($signal->real_volume, 2) ?><?php endif; ?>
    </div>
  </div>
  <div class="text-end">
    <div class="lbl">PnL real (DB)</div>
    <div class="h2 mb-0 <?= $pnl >= 0 ? 'text-profit' : 'text-loss' ?>">
      <?= $pnl >= 0 ? '+' : '' ?><?= jd_num($pnl, 2) ?> <small class="text-muted" style="font-size:.5em">USD</small>
    </div>
  </div>
</div></div>

<div class="row">
  <!-- IZQUIERDA -->
  <div class="col-lg-6">

    <!-- Señal -> Corregido -> Real -->
    <div class="card"><div class="card-body">
      <h6 class="mb-3"><i class="fas fa-exchange-alt me-1 text-primary"></i>Señal → Corregido → Real</h6>
      <table class="table table-sm cmp mb-2">
        <thead><tr><th></th><th>Señal (cruda)</th><th></th><th>Corregido</th><th></th><th>Real / Ejecutado</th></tr></thead>
        <tbody>
          <tr><td class="lbl">Entry</td>
              <td><?= jd_num($r_entry, $d) ?></td><td class="arrow">→</td>
              <td><b><?= jd_num($c_entry, $d) ?></b></td><td class="arrow">→</td>
              <td class="<?= $real_entry ? 'text-profit' : 'text-muted' ?>"><?= $real_entry ? jd_num($real_entry, $d) : '—' ?></td></tr>
          <tr><td class="lbl">SL</td>
              <td><?= jd_num($r_sl, $d) ?></td><td class="arrow">→</td>
              <td><?= jd_num($c_sl, $d) ?></td><td></td>
              <td><?php if ($is_be): ?><span class="badge bg-success">BE</span><?php elseif ($real_sl): ?><?= jd_num($real_sl, $d) ?><?php else: ?>—<?php endif; ?></td></tr>
          <?php for ($i = 1; $i <= 5; $i++):
              $hit = ($reached >= $i && $i >= 1);
              ?>
          <tr><td class="lbl">TP<?= $i ?></td>
              <td><?= jd_num($r_tps[$i], $d) ?></td><td class="arrow">→</td>
              <td><?= jd_num($c_tps[$i], $d) ?></td><td></td>
              <td><?php if ($hit): ?><span class="text-profit">✓</span><?php else: ?>—<?php endif; ?></td></tr>
          <?php endfor; ?>
        </tbody>
      </table>
      <div class="text-muted" style="font-size:.8rem">
        <?php if ($factor != 1.0): ?>
          Corrección futuros→CFD · factor <b><?= number_format($factor, 6) ?></b> · cada precio = crudo ÷ factor
        <?php else: ?>
          Sin corrección de precios (factor 1.0)
        <?php endif; ?>
      </div>
    </div></div>

    <!-- ¿Por qué este order type? (solo con snapshot) -->
    <?php if (jd_has($snap, 'order_type')):
        $side    = $snap->side;
        $kcoef   = ($side === 'ADVERSO') ? $snap->cfg_k_limit_ratio : $snap->cfg_k_stop_ratio;
        ?>
    <div class="card"><div class="card-body">
      <h6 class="mb-3"><i class="fas fa-question-circle me-1 text-primary"></i>¿Por qué <b><?= htmlspecialchars($snap->order_type) ?></b>?</h6>
      <div class="calc">
        Mercado al llegar la señal: <b><?= jd_num($snap->price_signal, $d) ?></b> · Entry corregido: <b><?= jd_num($snap->entry, $d) ?></b><br>
        Distancia = <b><?= jd_num($snap->dist_entry, $d) ?></b> · Lado <b><?= htmlspecialchars($side ?: '—') ?></b><br>
        <?php if (jd_has($snap, 'k_band') && jd_has($snap, 't1')): ?>
          Banda MARKET = K (<?= jd_num($kcoef, 4) ?>) × T1 (<?= jd_num($snap->t1, 5) ?>) = <b><?= jd_num($snap->k_band, 5) ?></b><br>
          <?= jd_num($snap->dist_entry, 5) ?> (distancia) vs <?= jd_num($snap->k_band, 5) ?> (banda) ⟶ <b><?= htmlspecialchars($snap->order_type) ?></b>
        <?php endif; ?>
      </div>
    </div></div>
    <?php endif; ?>

    <!-- Volumen y gates (solo con snapshot) -->
    <?php if ($snap): ?>
    <div class="card"><div class="card-body">
      <h6 class="mb-3"><i class="fas fa-sliders-h me-1 text-primary"></i>Volumen y gates</h6>
      <?php if (jd_has($snap, 'real_volume') && jd_has($snap, 'r_dist')): ?>
      <div class="calc mb-2">
        Volumen por riesgo = (Balance × RISK% <?= jd_num($snap->cfg_risk_percent, 1) ?>) ÷ R(<?= jd_num($snap->r_dist, 5) ?>) = <b><?= jd_num($snap->real_volume, 2) ?> lots</b>
      </div>
      <?php endif; ?>
      <div class="d-flex flex-wrap gap-2">
        <span class="badge badge-soft pill">spread real <?= jd_num($snap->spread_real, 3) ?> / tol <?= jd_num($snap->spread_tol, 3) ?> · check <?= $snap->cfg_enable_spread ? 'ON' : 'OFF' ?></span>
        <span class="badge badge-soft pill">slippage real <?= jd_num($snap->slip_real, 3) ?> / tol <?= jd_num($snap->slip_tol, 3) ?> · check <?= $snap->cfg_enable_slip ? 'ON' : 'OFF' ?></span>
        <span class="badge badge-soft pill">stops_min <?= jd_num($snap->stops_min, 5) ?> · sl_dist <?= jd_num($snap->sl_dist, 5) ?></span>
        <span class="badge badge-soft pill">T1 <?= jd_num($snap->t1, 5) ?> · R <?= jd_num($snap->r_dist, 5) ?></span>
        <span class="badge badge-soft pill">BE level <?= (int)$snap->cfg_be_level ?> · TP split <?= jd_num($snap->cfg_tp1_pct,0) ?>/<?= jd_num($snap->cfg_tp2_pct,0) ?>/<?= jd_num($snap->cfg_tp3_pct,0) ?>/<?= jd_num($snap->cfg_tp4_pct,0) ?>/<?= jd_num($snap->cfg_tp5_pct,0) ?></span>
      </div>
    </div></div>
    <?php endif; ?>

    <!-- Corrección de precios (solo si corrió) -->
    <?php if ($corr): ?>
    <?php $bad = ($corr->status === 'ERROR') || ($corr->candles_aligned !== null && !$corr->candles_aligned); ?>
    <div class="card"><div class="card-body">
      <h6 class="mb-3"><i class="fas fa-satellite me-1 text-primary"></i>Proceso de corrección
        <?php if ($corr->status === 'OK'): ?><span class="badge bg-success ms-1">OK</span>
        <?php else: ?><span class="badge bg-dark ms-1"><?= htmlspecialchars($corr->error_stage ?: 'ERROR') ?></span><?php endif; ?>
      </h6>

      <?php if ($bad): ?>
        <div class="alert alert-warning py-2" style="font-size:.85rem">
          <i class="fas fa-exclamation-triangle me-1"></i>
          <?php if ($corr->status === 'ERROR'): ?>
            La corrección falló (<?= htmlspecialchars($corr->error_stage) ?>): <?= htmlspecialchars($corr->error_message) ?> — el trade no se tomó.
          <?php else: ?>
            Vela CFD <b>desalineada</b> del objetivo (<?= (int)$corr->bar_gap_sec ?>s de gap). El factor se calculó contra una vela que no coincide con el timestamp del futuro.
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <div class="corr-grid">
        <div>
          <div class="lbl">Futuro (Yahoo, UTC)</div>
          <div><b><?= jd_num($corr->fut_price, $d) ?></b> @ <?= htmlspecialchars($corr->fut_candle_time ?: '—') ?></div>
        </div>
        <div>
          <div class="lbl">Vela CFD MT5 usada</div>
          <div><b><?= jd_num($corr->mt5_price, $d) ?></b> @ <?= htmlspecialchars($corr->mt5_bar_time ?: '—') ?> <span class="text-muted">(idx <?= (int)$corr->mt5_bar_index ?>)</span></div>
        </div>
        <div>
          <div class="lbl">MT5 vivo al recibir señal</div>
          <div><b><?= jd_num($corr->signal_mt5_price, $d) ?></b></div>
        </div>
        <div>
          <div class="lbl">¿Misma vela?</div>
          <div><?php if ($corr->candles_aligned): ?><span class="text-profit">Sí (gap <?= (int)$corr->bar_gap_sec ?>s)</span><?php else: ?><span class="text-loss">No (gap <?= (int)$corr->bar_gap_sec ?>s)</span><?php endif; ?></div>
        </div>
        <div>
          <div class="lbl">Factor / Desvío</div>
          <div><b><?= number_format((float)$corr->corr_factor, 6) ?></b> · <?= jd_num($corr->deviation_pct, 4) ?>%</div>
        </div>
        <div>
          <div class="lbl">Antigüedad dato futuro</div>
          <div><?= round(((int)$corr->timestamp_age_sec) / 60, 1) ?> min</div>
        </div>
      </div>
    </div></div>
    <?php endif; ?>

  </div>

  <!-- DERECHA: cronología -->
  <div class="col-lg-6">
    <div class="card"><div class="card-body">
      <h6 class="mb-3"><i class="fas fa-clock me-1 text-primary"></i>Cronología</h6>
      <div class="tl">
        <!-- Señal recibida (siempre primero) -->
        <div class="tl-item"><div class="tl-dot bg-info"><i class="fas fa-satellite-dish"></i></div>
          <h6>Señal recibida</h6><small><?= date('Y-m-d H:i:s', strtotime($signal->created_at)) ?></small></div>

        <?php
        // Etiquetas de order_type / close_reason -> helper journal_labels (fuente única).
        foreach ($events as $ev):
            $type = $ev['event'] ?? '';
            $time = isset($ev['at']) ? date('Y-m-d H:i:s', strtotime($ev['at'])) : '—';
            if ($type === 'signal_received') continue; // ya lo mostramos arriba
        ?>
          <div class="tl-item">
          <?php if ($type === 'claimed'): ?>
            <div class="tl-dot bg-warning"><i class="fas fa-hand-pointer"></i></div>
            <h6>Reclamada por el EA</h6><small><?= $time ?></small>

          <?php elseif ($type === 'open'): $ol = !empty($ev['order_type']) ? journal_order_label($ev['order_type']) : 'Orden a mercado'; ?>
            <div class="tl-dot bg-success"><i class="fas fa-bolt"></i></div>
            <h6><?= $ol ?> ejecutada</h6>
            <small><?= $time ?><?php if (isset($ev['entry'])): ?> · entry <?= jd_num($ev['entry'], $d) ?><?php endif; ?><?php if (isset($ev['volume'])): ?> · vol <?= jd_num($ev['volume'], 2) ?><?php endif; ?></small>

          <?php elseif ($type === 'pending_order'): $ol = !empty($ev['order_type']) ? journal_order_label($ev['order_type']) : 'Orden pendiente'; ?>
            <div class="tl-dot bg-info"><i class="fas fa-hourglass"></i></div>
            <h6><?= $ol ?> colocada</h6><small><?= $time ?> · esperando entry</small>

          <?php elseif ($type === 'filled'): ?>
            <div class="tl-dot bg-success"><i class="fas fa-check"></i></div>
            <h6>Orden filleada</h6>
            <small><?= $time ?><?php if (isset($ev['entry'])): ?> · entry <?= jd_num($ev['entry'], $d) ?><?php endif; ?></small>

          <?php elseif ($type === 'tp'): ?>
            <div class="tl-dot bg-success"><i class="fas fa-bullseye"></i></div>
            <h6>TP<?= $ev['level'] ?? '?' ?> alcanzado</h6>
            <small><?= $time ?> · precio <?= isset($ev['price']) ? jd_num($ev['price'], $d) : '—' ?>
              <?php if (isset($ev['closed_pct'])): ?> · cierra <b><?= jd_num($ev['closed_pct'], 1) ?>%</b><?php endif; ?>
              <?php if (isset($ev['pnl']) && $ev['pnl'] != 0): ?> · PnL tramo <span class="<?= $ev['pnl'] >= 0 ? 'text-profit' : 'text-loss' ?>"><?= $ev['pnl'] >= 0 ? '+' : '' ?><?= jd_num($ev['pnl'], 2) ?></span><?php endif; ?>
            </small>

          <?php elseif ($type === 'breakeven'): ?>
            <div class="tl-dot bg-primary"><i class="fas fa-shield-alt"></i></div>
            <h6>SL movido a Breakeven</h6>
            <small><?= $time ?><?php if (isset($ev['new_sl'])): ?> · <span class="badge-soft badge pill">SL → <?= jd_num($ev['new_sl'], $d) ?></span><?php endif; ?></small>

          <?php elseif ($type === 'closed' || $type === 'failed'):
              $cr = $ev['reason'] ?? $closed_reason;
              $cfg = $cr ? journal_reason_meta($cr) : ['bg-secondary', 'Cerrado']; ?>
            <div class="tl-dot <?= $cfg[0] ?>"><i class="fas fa-flag-checkered"></i></div>
            <h6><?= $type === 'failed' ? 'Rechazado' : 'Cierre' ?> — <?= htmlspecialchars($cfg[1]) ?></h6>
            <small><?= $time ?>
              <?php if (isset($ev['price']) && $ev['price']): ?> · @ <?= jd_num($ev['price'], $d) ?><?php endif; ?>
              <?php if (isset($ev['pnl'])): ?> · Total <span class="<?= $ev['pnl'] >= 0 ? 'text-profit' : 'text-loss' ?>"><?= $ev['pnl'] >= 0 ? '+' : '' ?><?= jd_num($ev['pnl'], 2) ?></span><?php endif; ?>
            </small>

          <?php else: ?>
            <div class="tl-dot bg-secondary"><i class="fas fa-circle"></i></div>
            <h6><?= htmlspecialchars(ucfirst($type)) ?></h6><small><?= $time ?></small>
          <?php endif; ?>
          </div>
        <?php endforeach; ?>

        <?php if (empty($events)): ?>
          <div class="tl-item"><div class="tl-dot bg-secondary"></div>
            <h6 class="text-muted">Sin eventos registrados</h6></div>
        <?php endif; ?>
      </div>
    </div></div>

    <!-- Mensaje original -->
    <?php if (!empty($signal->message_text)): ?>
    <div class="card"><div class="card-body">
      <h6 class="mb-2"><i class="fas fa-comment-dots me-1 text-primary"></i>Mensaje original</h6>
      <pre class="bg-light p-2 rounded mb-0" style="font-size:.8rem;white-space:pre-wrap"><?= htmlspecialchars($signal->message_text) ?></pre>
    </div></div>
    <?php endif; ?>
  </div>
</div>
