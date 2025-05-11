<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">
        <i class="fas fa-history me-2"></i>Trade History
    </h1>
</div>

<!-- Filter Form -->
<div class="card mb-4">
    <div class="card-body">
        <?= form_open('trades', ['method' => 'get', 'class' => 'row g-3']) ?>
            <div class="col-md-3">
                <label for="status" class="form-label">Status</label>
                <select class="form-select" id="status" name="status">
                    <option value="" <?= $this->input->get('status') === null ? 'selected' : '' ?>>All</option>
                    <option value="open" <?= $this->input->get('status') === 'open' ? 'selected' : '' ?>>Open</option>
                    <option value="closed" <?= $this->input->get('status') === 'closed' ? 'selected' : '' ?>>Closed</option>
                </select>
            </div>
            <div class="col-md-6">
                <label for="strategy" class="form-label">Strategy</label>
                <select class="form-select" id="strategy" name="strategy">
                    <option value="">All</option>
                    <?php foreach ($strategies as $strategy): ?>
                        <option value="<?= $strategy->id ?>" <?= $this->input->get('strategy') == $strategy->id ? 'selected' : '' ?>>
                            <?= $strategy->name ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="fas fa-filter me-1"></i>Apply Filters
                </button>
            </div>
        <?= form_close() ?>
    </div>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-striped table-hover mb-0">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Symbol</th>
                        <th>Strategy</th>
                        <th>Side</th>
                        <th>Type</th>
                        <th>Position ID</th>                        
                        <th>Entry Price</th>
                        <th>Exit Price</th>
                        <th>Quantity</th>
                        <th>Leverage</th>
                        <th>PNL</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($trades)): ?>
                        <tr>
                            <td colspan="14" class="text-center py-3">No trades found</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($trades as $trade): ?>
                            <?php 
                                $pnl_class = isset($trade->pnl) && $trade->pnl >= 0 ? 'text-profit' : 'text-loss';
                                $side_class = $trade->side == 'BUY' ? 'text-success' : 'text-danger';
                                $status_class = $trade->status == 'open' ? 'bg-primary' : 'bg-success';
                            ?>
                            <tr>
                                <td><?= $trade->id ?></td>
                                <td><?= $trade->symbol ?></td>
                                <td><?= $trade->strategy_name ?></td>
                                <td class="<?= $side_class ?>"><?= $trade->side ?></td>
                                <td>
                                    <span class="badge <?= $trade->trade_type == 'futures' ? 'bg-warning text-dark' : 'bg-info' ?>">
                                        <?= ucfirst($trade->trade_type) ?>
                                    </span>
                                </td>
                                <td><?= isset($trade->position_id) ? $trade->position_id : 'N/A' ?></td>                                
                                <td><?= number_format($trade->entry_price, 2) ?></td>
                                <td><?= $trade->exit_price ? number_format($trade->exit_price, 2) : '-' ?></td>
                                <td><?= rtrim(rtrim(number_format($trade->quantity, 8), '0'), '.') ?></td>
                                <td><?= $trade->leverage ?>x</td>
                                <td class="<?= $pnl_class ?>">
                                    <?= isset($trade->pnl) ? number_format($trade->pnl, 2) . ' USDT' : '-' ?>
                                </td>
                                <td>
                                    <span class="badge <?= $status_class ?>">
                                        <?= ucfirst($trade->status) ?>
                                    </span>
                                </td>
                                <td><?= date('Y-m-d H:i', strtotime($trade->created_at)) ?></td>
                                <td>
                                    <a href="<?= base_url('trades/detail/' . $trade->id) ?>" class="btn btn-sm btn-info">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <?php if ($trade->status == 'open'): ?>
                                        <a href="<?= base_url('trades/close/' . $trade->id) ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to close this trade?')">
                                            <i class="fas fa-times-circle"></i>
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Trading Statistics -->
<?php if (!empty($stats) && $stats['total_trades'] > 0): ?>
<div class="card mt-4">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="fas fa-chart-pie me-1"></i>Trading Statistics
        </h5>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-3 mb-3">
                <div class="card h-100 <?= $stats['total_pnl'] >= 0 ? 'bg-success bg-opacity-10' : 'bg-danger bg-opacity-10' ?>">
                    <div class="card-body text-center">
                        <h6 class="text-muted">Total PNL</h6>
                        <h4 class="mb-0 <?= $stats['total_pnl'] >= 0 ? 'text-success' : 'text-danger' ?>">
                            <?= number_format($stats['total_pnl'], 2) ?> USDT
                        </h4>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card h-100 <?= $stats['total_pnl_percentage'] >= 0 ? 'bg-success bg-opacity-10' : 'bg-danger bg-opacity-10' ?>">
                    <div class="card-body text-center">
                        <h6 class="text-muted">PNL Percentage</h6>
                        <h4 class="mb-0 <?= $stats['total_pnl_percentage'] >= 0 ? 'text-success' : 'text-danger' ?>">
                            <?= number_format($stats['total_pnl_percentage'], 2) ?>%
                        </h4>
                        <small class="text-muted">Weighted by investment size</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card h-100">
                    <div class="card-body text-center">
                        <h6 class="text-muted">Winrate</h6>
                        <h4 class="mb-0">
                            <?= number_format($stats['winrate'], 1) ?>%
                        </h4>
                        <small class="text-muted"><?= $stats['winning_trades'] ?> / <?= $stats['total_trades'] ?> trades</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card h-100 <?= $stats['profit_per_trade'] >= 0 ? 'bg-success bg-opacity-10' : 'bg-danger bg-opacity-10' ?>">
                    <div class="card-body text-center">
                        <h6 class="text-muted">Avg. Profit per Trade</h6>
                        <h4 class="mb-0 <?= $stats['profit_per_trade'] >= 0 ? 'text-success' : 'text-danger' ?>">
                            <?= number_format($stats['profit_per_trade'], 2) ?> USDT
                        </h4>
                        <small class="text-muted">From <?= $stats['total_trades'] ?> closed trades</small>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row mt-2">
            <div class="col-md-6 mb-3">
                <div class="card h-100">
                    <div class="card-body">
                        <h6 class="text-muted">Detailed Statistics</h6>
                        <table class="table table-sm table-borderless mb-0">
                            <tr>
                                <td>Total Trades Closed:</td>
                                <td><?= $stats['total_trades'] ?></td>
                            </tr>
                            <tr>
                                <td>Winning Trades:</td>
                                <td><?= $stats['winning_trades'] ?> (<?= number_format(($stats['winning_trades'] / $stats['total_trades']) * 100, 1) ?>%)</td>
                            </tr>
                            <tr>
                                <td>Losing Trades:</td>
                                <td><?= $stats['losing_trades'] ?> (<?= number_format(($stats['losing_trades'] / $stats['total_trades']) * 100, 1) ?>%)</td>
                            </tr>
                            <tr>
                                <td>Total Capital Invested:</td>
                                <td><?= number_format($stats['total_invested'], 2) ?> USDT</td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-md-6 mb-3">
                <div class="card h-100">
                    <div class="card-body">
                        <h6 class="text-muted">Performance Summary</h6>
                        <p class="mb-2">
                            <i class="fas fa-info-circle me-1 text-primary"></i>
                            These statistics reflect only your closed trades. The PNL percentage takes leverage into account by measuring
                            gains against your real invested capital.
                        </p>
                        <?php if ($stats['total_pnl'] >= 0): ?>
                            <div class="alert alert-success py-2 mb-0">
                                <i class="fas fa-check-circle me-1"></i>
                                Your trading has been profitable with a total gain of <?= number_format($stats['total_pnl'], 2) ?> USDT (<?= number_format($stats['total_pnl_percentage'], 2) ?>%).
                            </div>
                        <?php else: ?>
                            <div class="alert alert-danger py-2 mb-0">
                                <i class="fas fa-exclamation-circle me-1"></i>
                                Your trading has resulted in a loss of <?= number_format(abs($stats['total_pnl']), 2) ?> USDT (<?= number_format(abs($stats['total_pnl_percentage']), 2) ?>%).
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>