<?php
/**
 * Trading Dashboard (my_trading/active) — cards desplegables.
 * Resumen colapsado = datos en vivo de user_telegram_signals.
 * Expandido = ea_trade_snapshots + ea_price_corrections (vía build_trade_view + partials _blocks),
 * mismos bloques que el detalle de journals (modo compact). Fallback a blobs para históricas.
 */
$this->load->view('journals/_blocks/styles');
?>

<?php if (empty($dashboard_signals)): ?>
    <div class="card"><div class="card-body text-center py-5">
        <i class="fas fa-chart-line fa-3x text-muted mb-3"></i>
        <h5 class="text-muted">No Trading Activity</h5>
        <p class="text-muted">Tus operaciones aparecerán acá cuando tengas señales.</p>
        <a href="<?= base_url('my_trading/signals') ?>" class="btn btn-outline-primary">
            <i class="fas fa-history me-1"></i>Ver todas las señales
        </a>
    </div></div>
<?php else: ?>
    <div id="dashboardSignalsList">
        <?php foreach ($dashboard_signals as $signal):
            $vm = build_trade_view($signal, $signal->snap ?? null, $signal->corr ?? null);
            $m  = $vm['meta']; $ph = $vm['phase']; $h = $vm['health']; $d = $vm['decimals'];
            $cid = 'sig-' . $m['id'];
            $elapsed = signal_elapsed($signal->created_at);
            $has_detail = $vm['decision']['present'] || $vm['gates']['present'] || $vm['correction']['present'];
            $detail_url = base_url('my_trading/trading_detail/' . $m['id']);
        ?>
        <div class="card sig-card" data-signal-id="<?= $m['id'] ?>">
            <div class="sig-head collapsed" data-bs-toggle="collapse" data-bs-target="#<?= $cid ?>" aria-expanded="false">
                <div style="min-width:46px"><strong>#<?= $m['id'] ?></strong></div>
                <div style="min-width:118px">
                    <strong><?= htmlspecialchars($m['symbol']) ?></strong>
                    <span class="badge <?= $m['dir_class'] ?> pill"><?= htmlspecialchars($m['op'] ?: '—') ?></span>
                </div>
                <div>
                    <span class="badge <?= $ph['class'] ?> pill">
                        <?php if ($ph['is_failure']): ?><i class="fas fa-exclamation-triangle me-1"></i><?php endif; ?>
                        <?= htmlspecialchars($ph['label']) ?>
                    </span>
                </div>
                <?php if ($m['order_type']): ?>
                    <div><span class="badge badge-soft pill"><?= htmlspecialchars(journal_order_label($m['order_type'])) ?></span></div>
                <?php endif; ?>
                <?php if ($signal->corr ?? null): ?>
                    <div title="<?= htmlspecialchars($h['title']) ?>"><span class="<?= $h['class'] ?>" style="font-size:1rem"><?= $h['icon'] ?></span></div>
                <?php endif; ?>

                <div class="grow num text-muted small">
                    <?php if ($m['real_entry']): ?>
                        <?= tv_num($m['real_entry'], $d) ?><?php if ($m['last_price'] && $m['status'] !== 'closed'): ?> <i class="fas fa-arrow-right mx-1"></i> <?= tv_num($m['last_price'], $d) ?><?php elseif ($m['status'] === 'closed' && $m['last_price']): ?> <span class="text-muted">→ <?= tv_num($m['last_price'], $d) ?></span><?php endif; ?>
                    <?php elseif ($ph['key'] === 'PENDING'): $tgt = $vm['prices']['corr']['entry']; ?>
                        <?php if ($tgt): ?><i class="fas fa-crosshairs me-1"></i><?= tv_num($tgt, $d) ?> <span class="text-muted">(target)</span><?php else: ?><i class="fas fa-clock me-1"></i>Esperando…<?php endif; ?>
                    <?php elseif ($ph['is_failure']): ?>
                        —
                    <?php else: ?>
                        <i class="fas fa-clock me-1"></i>Esperando…
                    <?php endif; ?>
                </div>

                <div class="num small text-muted" style="min-width:70px">
                    <?php if ($signal->real_volume): ?>
                        vol <?php if ($m['status'] === 'open' && $signal->remaining_volume !== null): ?><?= tv_num($signal->remaining_volume, 2) ?>/<?= tv_num($signal->real_volume, 2) ?><?php else: ?><?= tv_num($signal->real_volume, 2) ?><?php endif; ?>
                    <?php else: ?>—<?php endif; ?>
                </div>

                <div class="num">
                    <span class="<?= $m['pnl'] > 0 ? 'text-profit' : ($m['pnl'] < 0 ? 'text-loss' : 'text-muted') ?> fw-bold">
                        <?= $m['pnl'] >= 0 ? '+' : '' ?><?= tv_num($m['pnl'], 2) ?>
                    </span>
                </div>
                <div class="text-muted small text-end" style="min-width:92px">
                    <div><?= $elapsed ?></div>
                    <div style="font-size:.85em"><?= date('M j H:i', strtotime($m['created_at'])) ?></div>
                </div>
                <div><i class="fas fa-chevron-down chev"></i></div>
            </div>

            <div id="<?= $cid ?>" class="collapse">
                <div class="card-body border-top bg-white">
                    <?php if ($has_detail): ?>
                        <?php $this->load->view('journals/_blocks/correction', ['vm' => $vm, 'compact' => true], false); ?>
                        <?php if ($vm['decision']['present']): ?>
                            <div class="mt-2"><?php $this->load->view('journals/_blocks/decision', ['vm' => $vm, 'compact' => false], false); ?></div>
                        <?php endif; ?>
                        <?php if ($vm['gates']['present']): ?>
                            <div class="mt-2"><?php $this->load->view('journals/_blocks/gates', ['vm' => $vm, 'compact' => true], false); ?></div>
                        <?php endif; ?>
                    <?php else: ?>
                        <p class="text-muted small mb-2">Aún sin datos de ejecución (señal reclamada, esperando al EA).</p>
                    <?php endif; ?>
                    <a href="<?= $detail_url ?>" class="btn btn-sm btn-outline-primary mt-3">
                        <i class="fas fa-search me-1"></i>Ver detalle completo
                    </a>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- Quick Stats Cards -->
<?php if (!empty($dashboard_signals)): ?>
    <div class="row mt-4">
        <?php
        $stat_active = 0; $stat_closed = 0; $stat_wins = 0; $total_pnl = 0;
        foreach ($dashboard_signals as $s) {
            $total_pnl += $s->gross_pnl;
            if (in_array($s->status, ['pending', 'claimed', 'open'])) $stat_active++;
            if ($s->status === 'closed') { $stat_closed++; if ($s->gross_pnl > 0) $stat_wins++; }
        }
        ?>
        <div class="col-md-3">
            <div class="card border-info"><div class="card-body text-center">
                <h6 class="text-muted">Active Positions</h6>
                <h4 class="text-info mb-0"><?= $stat_active ?></h4>
                <small class="text-muted">Pending + Open</small>
            </div></div>
        </div>
        <div class="col-md-3">
            <div class="card border-secondary"><div class="card-body text-center">
                <h6 class="text-muted">Closed Positions</h6>
                <h4 class="text-secondary mb-0"><?= $stat_closed ?></h4>
                <small class="text-muted">In selected period</small>
            </div></div>
        </div>
        <div class="col-md-3">
            <div class="card border-success"><div class="card-body text-center">
                <h6 class="text-muted">Win Rate</h6>
                <?php $win_rate = $stat_closed > 0 ? ($stat_wins / $stat_closed) * 100 : 0; ?>
                <h4 class="text-success mb-0"><?= number_format($win_rate, 1) ?>%</h4>
                <small class="text-muted"><?= $stat_wins ?> / <?= $stat_closed ?> wins</small>
            </div></div>
        </div>
        <div class="col-md-3">
            <div class="card border-primary"><div class="card-body text-center">
                <h6 class="text-muted">Total PNL</h6>
                <?php $pnl_class = $total_pnl > 0 ? 'text-success' : ($total_pnl < 0 ? 'text-danger' : 'text-muted'); ?>
                <h4 class="<?= $pnl_class ?> mb-0"><?= $total_pnl >= 0 ? '+' : '-' ?>$<?= number_format(abs($total_pnl), 2) ?></h4>
                <small class="text-muted">Period P&L</small>
            </div></div>
        </div>
    </div>
<?php endif; ?>
