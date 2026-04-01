<!-- Dashboard Table -->
<div class="card">
    <div class="card-body p-0">
        <?php if (empty($dashboard_signals)): ?>
            <div class="text-center py-5">
                <i class="fas fa-chart-line fa-3x text-muted mb-3"></i>
                <h5 class="text-muted">No Trading Activity</h5>
                <p class="text-muted">
                    Your trading operations will appear here once you have signals.
                </p>
                <a href="<?= base_url('my_trading/signals') ?>" class="btn btn-outline-primary">
                    <i class="fas fa-history me-1"></i>View All Signals
                </a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0" id="dashboardSignalsTable">
                    <thead class="table-light">
                        <tr>
                            <th>Signal</th>
                            <th>Ticker</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Entry / Current</th>
                            <th>Volume</th>
                            <th>Level</th>
                            <th>PNL</th>
                            <th>Time</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($dashboard_signals as $signal): ?>
                            <?php
                            $op_type = $signal->op_type ?: 'UNKNOWN';
                            $decimals = $signal->display_decimals ?? 5;
                            $elapsed_formatted = signal_elapsed($signal->created_at);

                            // Row class: activas = normal, closed con ejecución real = blanco, failed/cancelled = gris
                            $row_class = '';
                            if (in_array($signal->status, ['failed_execution', 'cancelled'])) {
                                $row_class = 'table-secondary';
                            } elseif ($signal->status === 'closed' && !$signal->real_entry_price) {
                                $row_class = 'table-secondary';
                            }
                            ?>
                            <tr data-signal-id="<?= $signal->id ?>" class="signal-row <?= $row_class ?>" data-status="<?= $signal->status ?>">
                                <td>
                                    <strong>#<?= $signal->id ?></strong><br>
                                    <small class="text-muted">Trade: <?= $signal->trade_id ?: 'N/A' ?></small>
                                </td>
                                <td>
                                    <strong><?= $signal->ticker_symbol ?></strong><br>
                                    <?php if ($signal->mt_ticker): ?>
                                        <small class="text-muted">MT: <?= $signal->mt_ticker ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?= signal_op_type_badge($op_type) ?>
                                </td>
                                <td>
                                    <?php $ssd = get_signal_status_display($signal); ?>
                                    <span class="badge <?= $ssd['class'] ?>">
                                        <?php if ($ssd['is_failure']): ?>
                                            <i class="fas fa-exclamation-triangle me-1"></i>
                                        <?php endif; ?>
                                        <?= $ssd['text'] ?>
                                    </span>

                                    <?php if ($signal->status == 'open' && $signal->real_stop_loss > 0 && $signal->real_stop_loss == $signal->real_entry_price): ?>
                                        <br><small class="text-success mt-1 d-inline-block">
                                            <i class="fas fa-shield-alt"></i> BE
                                        </small>
                                    <?php endif; ?>
                                    <?php if ($signal->status == 'closed' && $signal->exit_level >= 1): ?>
                                        <br><small class="text-muted mt-1 d-inline-block">at TP<?= $signal->exit_level ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($signal->real_entry_price): ?>
                                        <div class="entry-price" data-entry="<?= $signal->real_entry_price ?>">
                                            <strong><?= number_format($signal->real_entry_price, $decimals) ?></strong>
                                        </div>
                                        <?php if ($signal->last_price && $signal->status != 'closed'): ?>
                                            <div class="current-price" data-current="<?= $signal->last_price ?>">
                                                <small class="text-muted">→ <?= number_format($signal->last_price, $decimals) ?></small>
                                            </div>
                                        <?php elseif ($signal->status == 'closed' && $signal->last_price): ?>
                                            <div class="final-price">
                                                <small class="text-muted">Final: <?= number_format($signal->last_price, $decimals) ?></small>
                                            </div>
                                        <?php endif; ?>
                                    <?php elseif ($signal->status === 'pending'): ?>
                                        <?php
                                        $target_data = !empty($signal->mt_corrected_data) ? json_decode($signal->mt_corrected_data, true) : null;
                                        if (!$target_data) $target_data = !empty($signal->mt_execution_data) ? json_decode($signal->mt_execution_data, true) : null;
                                        $target_entry = $target_data['entry'] ?? 0;
                                        ?>
                                        <?php if ($target_entry > 0): ?>
                                            <div class="entry-price text-muted">
                                                <i class="fas fa-crosshairs me-1"></i><?= number_format($target_entry, $decimals) ?>
                                            </div>
                                            <small class="text-muted">(target)</small>
                                        <?php else: ?>
                                            <span class="text-muted"><i class="fas fa-clock me-1"></i>Waiting...</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-muted"><i class="fas fa-clock me-1"></i>Waiting...</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($signal->real_volume): ?>
                                        <div class="volume-info">
                                            <?php if ($signal->status == 'closed'): ?>
                                                <strong>0.00</strong> / <?= number_format($signal->real_volume, 2) ?>
                                                <div class="progress mt-1" style="height: 4px;">
                                                    <div class="progress-bar bg-secondary" style="width: 100%"></div>
                                                </div>
                                                <small class="text-muted">100% closed</small>
                                            <?php else: ?>
                                                <strong><?= number_format($signal->remaining_volume ?: $signal->real_volume, 2) ?></strong> /
                                                <?= number_format($signal->real_volume, 2) ?>
                                                <?php if ($signal->volume_closed_percent > 0): ?>
                                                    <div class="progress mt-1" style="height: 4px;">
                                                        <div class="progress-bar bg-success" style="width: <?= $signal->volume_closed_percent ?>%"></div>
                                                    </div>
                                                    <small class="text-muted"><?= number_format($signal->volume_closed_percent, 1) ?>% closed</small>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?= signal_level_badge($signal->current_level) ?>
                                </td>
                                <td>
                                    <div class="pnl-display" data-pnl="<?= $signal->gross_pnl ?>">
                                        <?= signal_pnl($signal->gross_pnl) ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="time-info">
                                        <span class="elapsed-time"><?= $elapsed_formatted ?></span><br>
                                        <small class="text-muted"><?= date('M j H:i', strtotime($signal->created_at)) ?></small>
                                    </div>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="<?= base_url('my_trading/trading_detail/' . $signal->id) ?>"
                                            class="btn btn-outline-info" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <?php if ($signal->tradingview_url): ?>
                                            <a href="<?= $signal->tradingview_url ?>" target="_blank"
                                                class="btn btn-outline-primary" title="TradingView Chart">
                                                <i class="fas fa-chart-line"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Quick Stats Cards -->
<?php if (!empty($dashboard_signals)): ?>
    <div class="row mt-4">
        <?php
        $stat_active = 0;
        $stat_closed = 0;
        $stat_wins = 0;
        $total_pnl = 0;
        foreach ($dashboard_signals as $s) {
            $total_pnl += $s->gross_pnl;
            if (in_array($s->status, ['pending', 'claimed', 'open'])) $stat_active++;
            if ($s->status === 'closed') {
                $stat_closed++;
                if ($s->gross_pnl > 0) $stat_wins++;
            }
        }
        ?>
        <div class="col-md-3">
            <div class="card border-info">
                <div class="card-body text-center">
                    <h6 class="text-muted">Active Positions</h6>
                    <h4 class="text-info mb-0">
                        <?= $stat_active ?>
                    </h4>
                    <small class="text-muted">Pending + Open</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-secondary">
                <div class="card-body text-center">
                    <h6 class="text-muted">Closed Positions</h6>
                    <h4 class="text-secondary mb-0">
                        <?= $stat_closed ?>
                    </h4>
                    <small class="text-muted">In selected period</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-success">
                <div class="card-body text-center">
                    <h6 class="text-muted">Win Rate</h6>
                    <?php $win_rate = $stat_closed > 0 ? ($stat_wins / $stat_closed) * 100 : 0; ?>
                    <h4 class="text-success mb-0">
                        <?= number_format($win_rate, 1) ?>%
                    </h4>
                    <small class="text-muted"><?= $stat_wins ?> / <?= $stat_closed ?> wins</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-primary">
                <div class="card-body text-center">
                    <h6 class="text-muted">Total PNL</h6>
                    <?php
                    $pnl_class = $total_pnl > 0 ? 'text-success' : ($total_pnl < 0 ? 'text-danger' : 'text-muted');
                    ?>
                    <h4 class="<?= $pnl_class ?> mb-0">
                        <?= $total_pnl >= 0 ? '+' : '-' ?>$<?= number_format(abs($total_pnl), 2) ?>
                    </h4>
                    <small class="text-muted">Period P&L</small>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>