<!-- Trading Dashboard Header -->
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0">
        <i class="fas fa-tachometer-alt me-1"></i>Trading Dashboard
        <span class="badge bg-primary ms-2"><?= count($dashboard_signals) ?></span>
    </h5>
    <div class="btn-group">
        <button class="btn btn-outline-primary btn-sm" onclick="refreshDashboardData()" id="refreshBtn">
            <i class="fas fa-sync-alt me-1"></i>Refresh
        </button>
        <div class="form-check form-switch ms-3">
            <input class="form-check-input" type="checkbox" id="autoRefresh" checked>
            <label class="form-check-label" for="autoRefresh">Auto-refresh</label>
        </div>
    </div>
</div>

<!-- Dashboard Filters -->
<div class="card mb-4">
    <div class="card-header">
        <h6 class="mb-0">
            <i class="fas fa-filter me-1"></i>Dashboard Filters
        </h6>
    </div>
    <div class="card-body">
        <?= form_open('my_trading/active', ['method' => 'get', 'class' => 'row g-3']) ?>
        <div class="col-md-3">
            <label for="status_filter" class="form-label">Status</label>
            <select class="form-select" id="status_filter" name="status_filter">
                <option value="">All Signals</option>
                <option value="active" <?= ($filters['status_filter'] ?? '') === 'active' ? 'selected' : '' ?>>Active Only (Pending + Open)</option>
                <option value="pending" <?= ($filters['status_filter'] ?? '') === 'pending' ? 'selected' : '' ?>>Pending Only</option>
                <option value="open" <?= ($filters['status_filter'] ?? '') === 'open' ? 'selected' : '' ?>>Open Only</option>
                <option value="closed" <?= ($filters['status_filter'] ?? '') === 'closed' ? 'selected' : '' ?>>Closed Only</option>
            </select>
        </div>
        <div class="col-md-2">
            <label for="ticker_filter" class="form-label">Ticker</label>
            <select class="form-select" id="ticker_filter" name="ticker_filter">
                <option value="">All Tickers</option>
                <?php if (isset($user_tickers)): ?>
                    <?php foreach ($user_tickers as $ticker): ?>
                        <option value="<?= $ticker->ticker_symbol ?>" <?= ($filters['ticker_filter'] ?? '') === $ticker->ticker_symbol ? 'selected' : '' ?>>
                            <?= $ticker->ticker_symbol ?>
                        </option>
                    <?php endforeach; ?>
                <?php endif; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label for="date_range" class="form-label">Date Range</label>
            <select class="form-select" id="date_range" name="date_range">
                <option value="7" <?= ($filters['date_range'] ?? '7') === '7' ? 'selected' : '' ?>>Last 7 days</option>
                <option value="30" <?= ($filters['date_range'] ?? '') === '30' ? 'selected' : '' ?>>Last 30 days</option>
                <option value="all" <?= ($filters['date_range'] ?? '') === 'all' ? 'selected' : '' ?>>All Time</option>
            </select>
        </div>
        <div class="col-md-2">
            <label for="pnl_filter" class="form-label">PNL Filter</label>
            <select class="form-select" id="pnl_filter" name="pnl_filter">
                <option value="">All PNL</option>
                <option value="profit" <?= ($filters['pnl_filter'] ?? '') === 'profit' ? 'selected' : '' ?>>Profitable</option>
                <option value="loss" <?= ($filters['pnl_filter'] ?? '') === 'loss' ? 'selected' : '' ?>>Loss</option>
                <option value="breakeven" <?= ($filters['pnl_filter'] ?? '') === 'breakeven' ? 'selected' : '' ?>>Break Even</option>
            </select>
        </div>
        <div class="col-md-3">
            <div class="d-flex gap-2 align-items-end h-100">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-search me-1"></i>Filter
                </button>
                <a href="<?= base_url('my_trading/active') ?>" class="btn btn-secondary">
                    <i class="fas fa-undo me-1"></i>Reset
                </a>
            </div>
        </div>
        <?= form_close() ?>
    </div>
</div>

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
                            // Parse analysis data for operation type
                            $analysis = json_decode($signal->analysis_data, true);
                            $op_type = $signal->op_type ?: ($analysis['op_type'] ?? 'UNKNOWN');

                            // Calculate time elapsed
                            $created_time = strtotime($signal->created_at);
                            $elapsed = time() - $created_time;
                            $elapsed_formatted = '';
                            if ($elapsed < 3600) {
                                $elapsed_formatted = floor($elapsed / 60) . 'm';
                            } else if ($elapsed < 86400) {
                                $elapsed_formatted = floor($elapsed / 3600) . 'h ' . floor(($elapsed % 3600) / 60) . 'm';
                            } else {
                                $elapsed_formatted = floor($elapsed / 86400) . 'd ' . floor(($elapsed % 86400) / 3600) . 'h';
                            }

                            // Detectar breakeven
                            $is_breakeven = ($signal->real_stop_loss == $signal->real_entry_price);

                            // Row class for active vs closed
                            $row_class = in_array($signal->status, ['pending', 'claimed', 'open']) ? '' : 'table-secondary';
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
                                    <?php
                                    $op_type_upper = strtoupper($op_type);
                                    $op_class = '';
                                    $op_icon = '';
                                    if ($op_type_upper === 'LONG') {
                                        $op_class = 'bg-success';
                                        $op_icon = 'fas fa-arrow-up';
                                    } elseif ($op_type_upper === 'SHORT') {
                                        $op_class = 'bg-danger';
                                        $op_icon = 'fas fa-arrow-down';
                                    } else {
                                        $op_class = 'bg-secondary';
                                        $op_icon = 'fas fa-question';
                                    }
                                    ?>
                                    <span class="badge <?= $op_class ?>">
                                        <i class="<?= $op_icon ?> me-1"></i><?= $op_type_upper ?>
                                    </span>
                                </td>
                                <td>
                                <td>
                                    <?php
                                    // Status principal - SIN incluir BE como status
                                    $status_class = '';
                                    $status_text = '';

                                    switch ($signal->status) {
                                        case 'pending':
                                        case 'claimed':
                                            $status_class = 'bg-warning text-dark';
                                            $status_text = 'Pending Order';
                                            break;
                                        case 'open':
                                            if ($signal->current_level >= 1) {
                                                $status_class = 'bg-success';
                                                $status_text = 'TP' . $signal->current_level . ' Reached';
                                            } else {
                                                $status_class = 'bg-primary';
                                                $status_text = 'Position Open';
                                            }
                                            break;
                                        case 'closed':
                                            if ($signal->close_reason) {
                                                switch ($signal->close_reason) {
                                                    case 'CLOSED_COMPLETE':
                                                        $status_class = 'bg-success';
                                                        $status_text = 'TP Complete';
                                                        break;
                                                    case 'CLOSED_STOPLOSS':
                                                    case 'CLOSED_CODE_STOP':
                                                        $status_class = 'bg-danger';
                                                        $status_text = 'Stop Loss';
                                                        break;
                                                    case 'CLOSED_EXTERNAL':
                                                        $status_class = 'bg-warning text-dark';
                                                        $status_text = 'Manual Close';
                                                        break;
                                                    default:
                                                        $status_class = 'bg-secondary';
                                                        $status_text = 'Closed';
                                                }
                                            } else {
                                                $status_class = 'bg-secondary';
                                                $status_text = 'Closed';
                                            }
                                            break;
                                        default:
                                            $status_class = 'bg-secondary';
                                            $status_text = ucfirst($signal->status);
                                    }
                                    ?>
                                    <span class="badge <?= $status_class ?>">
                                        <?= $status_text ?>
                                    </span>

                                    <?php
                                    // NUEVO: BE como indicador separado debajo del status
                                    $is_breakeven = ($signal->real_stop_loss == $signal->real_entry_price);
                                    if ($is_breakeven && $signal->status == 'open'):
                                    ?>
                                        <br><small class="text-success mt-1 d-inline-block">
                                            <i class="fas fa-shield-alt"></i> Break Even
                                        </small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($signal->real_entry_price): ?>
                                        <div class="entry-price" data-entry="<?= $signal->real_entry_price ?>">
                                            <strong><?= number_format($signal->real_entry_price, 5) ?></strong>
                                        </div>
                                        <?php if ($signal->last_price && $signal->status != 'closed'): ?>
                                            <div class="current-price" data-current="<?= $signal->last_price ?>">
                                                <small class="text-muted">→ <?= number_format($signal->last_price, 5) ?></small>
                                            </div>
                                        <?php elseif ($signal->status == 'closed' && $signal->last_price): ?>
                                            <div class="final-price">
                                                <small class="text-muted">Final: <?= number_format($signal->last_price, 5) ?></small>
                                            </div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-muted">Waiting...</span>
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
                                    <?php
                                    // LÓGICA DE LEVELS CORREGIDA - Sin redundancia con Status
                                    $level = $signal->current_level;
                                    $level_text = '';
                                    $level_class = 'bg-secondary';

                                    if ($level == -2) {
                                        $level_text = '-';              // No hay progreso aún
                                        $level_class = 'bg-light text-muted';
                                    } elseif ($level == 0) {
                                        $level_text = 'INIT';           // Posición abierta, sin TPs
                                        $level_class = 'bg-secondary';
                                    } elseif ($level >= 1 && $level <= 5) {
                                        $level_text = 'TP' . $level;    // TPs alcanzados
                                        $level_class = 'bg-success';
                                    } elseif ($level == -1) {
                                        $level_text = 'SL HIT';         // Stop loss tocado
                                        $level_class = 'bg-danger';
                                    }
                                    ?>
                                    <span class="badge <?= $level_class ?>">
                                        <?= $level_text ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="pnl-display" data-pnl="<?= $signal->gross_pnl ?>">
                                        <?php if ($signal->gross_pnl != 0): ?>
                                            <?php
                                            $pnl_class = $signal->gross_pnl > 0 ? 'text-success' : 'text-danger';
                                            $pnl_icon = $signal->gross_pnl > 0 ? 'fa-arrow-up' : 'fa-arrow-down';
                                            ?>
                                            <span class="<?= $pnl_class ?>">
                                                <i class="fas <?= $pnl_icon ?> me-1"></i>$<?= number_format(abs($signal->gross_pnl), 2) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">$0.00</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="time-info">
                                        <span class="elapsed-time"><?= $elapsed_formatted ?></span><br>
                                        <small class="text-muted"><?= date('M j H:i', $created_time) ?></small>
                                    </div>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="<?= base_url('my_trading/signal_detail/' . $signal->id) ?>"
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
        $active_signals = array_filter($dashboard_signals, function ($s) {
            return in_array($s->status, ['pending', 'claimed', 'open']);
        });
        $closed_signals = array_filter($dashboard_signals, function ($s) {
            return $s->status === 'closed';
        });
        $profitable_signals = array_filter($dashboard_signals, function ($s) {
            return $s->gross_pnl > 0;
        });
        $total_pnl = array_sum(array_column($dashboard_signals, 'gross_pnl'));
        ?>
        <div class="col-md-3">
            <div class="card border-info">
                <div class="card-body text-center">
                    <h6 class="text-muted">Active Positions</h6>
                    <h4 class="text-info mb-0">
                        <?= count($active_signals) ?>
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
                        <?= count($closed_signals) ?>
                    </h4>
                    <small class="text-muted">In selected period</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-success">
                <div class="card-body text-center">
                    <h6 class="text-muted">Win Rate</h6>
                    <?php
                    $win_rate = count($closed_signals) > 0 ? (count($profitable_signals) / count($closed_signals)) * 100 : 0;
                    ?>
                    <h4 class="text-success mb-0">
                        <?= number_format($win_rate, 1) ?>%
                    </h4>
                    <small class="text-muted"><?= count($profitable_signals) ?> / <?= count($closed_signals) ?> wins</small>
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
                        $<?= number_format(abs($total_pnl), 2) ?>
                    </h4>
                    <small class="text-muted">Period P&L</small>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const refreshBtn = document.getElementById('refreshBtn');

        // Simple page reload on refresh button click
        refreshBtn.addEventListener('click', function() {
            // Show loading state
            const originalContent = refreshBtn.innerHTML;
            refreshBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Refreshing...';
            refreshBtn.disabled = true;

            // Reload the current page
            window.location.reload();
        });
    });
</script>
<style>
    .signal-row:hover {
        background-color: #f8f9fa;
    }

    .signal-row.table-secondary {
        opacity: 0.8;
    }

    .progress {
        width: 60px;
    }

    .elapsed-time {
        font-weight: 500;
    }

    .volume-info {
        min-width: 80px;
    }

    .pnl-display {
        min-width: 70px;
        font-weight: 500;
    }

    .entry-price,
    .current-price,
    .final-price {
        line-height: 1.2;
    }
</style>