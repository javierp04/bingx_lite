<?php
/**
 * Detalle unificado de un trade (estilo mock).
 * View-model armado por build_trade_view() (helper trade_view) — fuente única compartida
 * con la card desplegable de my_trading/active. Los bloques se renderizan vía partials
 * journals/_blocks/* (mismos que la card, en modo full). Aquí solo va el layout + timeline.
 */
$vm  = build_trade_view($signal, isset($snapshot) ? $snapshot : null, isset($correction) ? $correction : null);
$m   = $vm['meta'];
$d   = $vm['decimals'];
$ph  = $vm['phase'];

$bc_home_url   = isset($bc_home_url) ? $bc_home_url : base_url('journals');
$bc_home_label = isset($bc_home_label) ? $bc_home_label : 'Journals';
?>
<?php $this->load->view('journals/_blocks/styles'); ?>

<!-- Breadcrumb -->
<nav style="font-size:.85rem" class="mb-2">
  <a href="<?= htmlspecialchars($bc_home_url) ?>"><?= htmlspecialchars($bc_home_label) ?></a> <span class="text-muted">/</span>
  <a href="<?= htmlspecialchars($back_url) ?>"><?= htmlspecialchars($sym) ?></a> <span class="text-muted">/</span>
  <span class="text-muted">Trade #<?= $m['id'] ?></span>
</nav>

<!-- Header -->
<div class="card"><div class="card-body d-flex flex-wrap justify-content-between align-items-center">
  <div>
    <h3 class="mb-1"><?= htmlspecialchars($m['symbol']) ?>
      <span class="badge <?= $m['dir_class'] ?>"><?= htmlspecialchars($m['op'] ?: '—') ?></span>
      <span class="badge <?= $ph['class'] ?>" title="<?= htmlspecialchars($m['close_reason'] ?: $m['status']) ?>"><?= htmlspecialchars($ph['label']) ?></span>
    </h3>
    <div class="text-muted" style="font-size:.85rem">
      Señal <?= htmlspecialchars($m['created_at']) ?>
      <?php if ($m['updated_at']): ?> · actualizado <?= htmlspecialchars($m['updated_at']) ?><?php endif; ?>
      <?php if ($m['order_type']): ?> · <?= htmlspecialchars(journal_order_label($m['order_type'])) ?><?php endif; ?>
      <?php if ($m['real_volume']): ?> · vol <?= tv_num($m['real_volume'], 2) ?><?php endif; ?>
    </div>
  </div>
  <div class="text-end">
    <div class="lbl">PnL real (DB)</div>
    <div class="h2 mb-0 <?= $m['pnl'] >= 0 ? 'text-profit' : 'text-loss' ?>">
      <?= $m['pnl'] >= 0 ? '+' : '' ?><?= tv_num($m['pnl'], 2) ?> <small class="text-muted" style="font-size:.5em">USD</small>
    </div>
  </div>
</div></div>

<div class="row">
  <!-- IZQUIERDA -->
  <div class="col-lg-6">

    <!-- Señal -> Corregido -> Real -->
    <div class="card"><div class="card-body">
      <h6 class="mb-3"><i class="fas fa-exchange-alt me-1 text-primary"></i>Señal → Corregido → Real</h6>
      <?php $this->load->view('journals/_blocks/prices', ['vm' => $vm], false); ?>
    </div></div>

    <!-- ¿Por qué este order type? -->
    <?php if ($vm['decision']['present']): ?>
    <div class="card"><div class="card-body">
      <h6 class="mb-3"><i class="fas fa-question-circle me-1 text-primary"></i>¿Por qué <b><?= htmlspecialchars($vm['decision']['order_type']) ?></b>?</h6>
      <?php $this->load->view('journals/_blocks/decision', ['vm' => $vm, 'compact' => false], false); ?>
    </div></div>
    <?php endif; ?>

    <!-- Volumen y gates -->
    <?php if ($vm['gates']['present']): ?>
    <div class="card"><div class="card-body">
      <h6 class="mb-3"><i class="fas fa-sliders-h me-1 text-primary"></i>Volumen y gates</h6>
      <?php $this->load->view('journals/_blocks/gates', ['vm' => $vm, 'compact' => false], false); ?>
    </div></div>
    <?php endif; ?>

    <!-- Proceso de corrección -->
    <?php if ($vm['correction']['present']):
        $cstat = $vm['correction']['status']; ?>
    <div class="card"><div class="card-body">
      <h6 class="mb-3"><i class="fas fa-satellite me-1 text-primary"></i>Proceso de corrección
        <?php if ($cstat === 'OK'): ?><span class="badge bg-success ms-1">OK</span>
        <?php else: ?><span class="badge bg-dark ms-1"><?= htmlspecialchars($vm['correction']['error_stage'] ?: 'ERROR') ?></span><?php endif; ?>
      </h6>
      <?php $this->load->view('journals/_blocks/correction', ['vm' => $vm, 'compact' => false], false); ?>
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
          <h6>Señal recibida</h6><small><?= date('Y-m-d H:i:s', strtotime($m['created_at'])) ?></small></div>

        <?php
        foreach ($events as $ev):
            $type = $ev['event'] ?? '';
            $time = isset($ev['at']) ? date('Y-m-d H:i:s', strtotime($ev['at'])) : '—';
            if ($type === 'signal_received') continue;
        ?>
          <div class="tl-item">
          <?php if ($type === 'claimed'): ?>
            <div class="tl-dot bg-warning"><i class="fas fa-hand-pointer"></i></div>
            <h6>Reclamada por el EA</h6><small><?= $time ?></small>

          <?php elseif ($type === 'open'): $ol = !empty($ev['order_type']) ? journal_order_label($ev['order_type']) : 'Orden a mercado'; ?>
            <div class="tl-dot bg-success"><i class="fas fa-bolt"></i></div>
            <h6><?= $ol ?> ejecutada</h6>
            <small><?= $time ?><?php if (isset($ev['entry'])): ?> · entry <?= tv_num($ev['entry'], $d) ?><?php endif; ?><?php if (isset($ev['volume'])): ?> · vol <?= tv_num($ev['volume'], 2) ?><?php endif; ?></small>

          <?php elseif ($type === 'pending_order'): $ol = !empty($ev['order_type']) ? journal_order_label($ev['order_type']) : 'Orden pendiente'; ?>
            <div class="tl-dot bg-info"><i class="fas fa-hourglass"></i></div>
            <h6><?= $ol ?> colocada</h6><small><?= $time ?> · esperando entry</small>

          <?php elseif ($type === 'filled'): ?>
            <div class="tl-dot bg-success"><i class="fas fa-check"></i></div>
            <h6>Orden filleada</h6>
            <small><?= $time ?><?php if (isset($ev['entry'])): ?> · entry <?= tv_num($ev['entry'], $d) ?><?php endif; ?></small>

          <?php elseif ($type === 'tp'): ?>
            <div class="tl-dot bg-success"><i class="fas fa-bullseye"></i></div>
            <h6>TP<?= $ev['level'] ?? '?' ?> alcanzado</h6>
            <small><?= $time ?> · precio <?= isset($ev['price']) ? tv_num($ev['price'], $d) : '—' ?>
              <?php if (isset($ev['closed_pct'])): ?> · cierra <b><?= tv_num($ev['closed_pct'], 1) ?>%</b><?php endif; ?>
              <?php if (isset($ev['pnl']) && $ev['pnl'] != 0): ?> · PnL tramo <span class="<?= $ev['pnl'] >= 0 ? 'text-profit' : 'text-loss' ?>"><?= $ev['pnl'] >= 0 ? '+' : '' ?><?= tv_num($ev['pnl'], 2) ?></span><?php endif; ?>
            </small>

          <?php elseif ($type === 'breakeven'): ?>
            <div class="tl-dot bg-primary"><i class="fas fa-shield-alt"></i></div>
            <h6>SL movido a Breakeven</h6>
            <small><?= $time ?><?php if (isset($ev['new_sl'])): ?> · <span class="badge-soft badge pill">SL → <?= tv_num($ev['new_sl'], $d) ?></span><?php endif; ?></small>

          <?php elseif ($type === 'closed' || $type === 'failed'):
              $cr = $ev['reason'] ?? $m['close_reason'];
              $cfg = $cr ? journal_reason_meta($cr) : ['bg-secondary', 'Cerrado']; ?>
            <div class="tl-dot <?= $cfg[0] ?>"><i class="fas fa-flag-checkered"></i></div>
            <h6><?= $type === 'failed' ? 'Rechazado' : 'Cierre' ?> — <?= htmlspecialchars($cfg[1]) ?></h6>
            <small><?= $time ?>
              <?php if (isset($ev['price']) && $ev['price']): ?> · @ <?= tv_num($ev['price'], $d) ?><?php endif; ?>
              <?php if (isset($ev['pnl'])): ?> · Total <span class="<?= $ev['pnl'] >= 0 ? 'text-profit' : 'text-loss' ?>"><?= $ev['pnl'] >= 0 ? '+' : '' ?><?= tv_num($ev['pnl'], 2) ?></span><?php endif; ?>
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
