<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">
        <i class="fas fa-history me-2"></i>Trade History
    </h1>
</div>

<!-- Filter Form -->
<div class="card mb-4">
    <div class="card-body">
        <?= form_open('trades', ['method' => 'get', 'class' => 'row g-3']) ?>
            <div class="col-md-2">
                <label for="platform" class="form-label">Platform</label>
                <select class="form-select" id="platform" name="platform">
                    <option value="" <?= empty($current_platform) ? 'selected' : '' ?>>All Platforms</option>
                    <option value="bingx" <?= $current_platform === 'bingx' ? 'selected' : '' ?>>BingX</option>
                    <option value="metatrader" <?= $current_platform === 'metatrader' ? 'selected' : '' ?>>MetaTrader</option>
                </select>
            </div>
            <div class="col-md-2">
                <label for="status" class="form-label">Status</label>
                <select class="form-select" id="status" name="status">
                    <option value="" <?= empty($current_status) ? 'selected' : '' ?>>All</option>
                    <option value="open" <?= $current_status === 'open' ? 'selected' : '' ?>>Open</option>
                    <option value="closed" <?= $current_status === 'closed' ? 'selected' : '' ?>>Closed</option>
                </select>
            </div>
            <div class="col-md-2">
                <label for="symbol" class="form-label">Symbol</label>
                <select class="form-select" id="symbol" name="symbol">
                    <option value="" <?= empty($current_symbol) ? 'selected' : '' ?>>All Symbols</option>
                    <?php foreach ($symbols as $sym): ?>
                        <option value="<?= $sym ?>" <?= $current_symbol === $sym ? 'selected' : '' ?>>
                            <?= $sym ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label for="strategy" class="form-label">Strategy</label>
                <select class="form-select" id="strategy" name="strategy">
                    <option value="">All Strategies</option>
                    <?php foreach ($strategies as $strategy): ?>
                        <option value="<?= $strategy->id ?>" <?= $current_strategy == $strategy->id ? 'selected' : '' ?>>
                            <?= $strategy->name ?> (<?= ucfirst($strategy->platform) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="fas fa-filter me-1"></i>Filter
                </button>
            </div>
        <?= form_close() ?>

        <?php if (!empty($current_platform) || !empty($current_status) || !empty($current_strategy) || !empty($current_symbol)): ?>
            <div class="mt-2">
                <a href="<?= base_url('trades') ?>" class="btn btn-sm btn-outline-secondary">
                    <i class="fas fa-times me-1"></i>Clear Filters
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Trades Grouped by Strategy -->
<?php if (empty($grouped_trades)): ?>
    <div class="card">
        <div class="card-body text-center py-5">
            <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
            <p class="text-muted mb-0">No trades found</p>
        </div>
    </div>
<?php else: ?>
    <?php foreach ($grouped_trades as $group): ?>
        <?php
            $platform_badge = $group['platform'] === 'metatrader' ? 'bg-dark' : 'bg-info';
            $group_stats = $group['stats'];
        ?>
        <div class="card mb-4">
            <!-- Strategy Header -->
            <div class="card-header bg-light">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="mb-0">
                            <i class="fas fa-chart-line me-2"></i>
                            <?= $group['strategy_name'] ?>
                            <span class="badge bg-secondary ms-2"><?= $group['symbol'] ?></span>
                            <span class="badge <?= $platform_badge ?> ms-1"><?= ucfirst($group['platform']) ?></span>
                        </h5>
                    </div>
                    <div>
                        <span class="text-muted"><?= count($group['trades']) ?> trades</span>
                    </div>
                </div>
            </div>

            <!-- Trades Table -->
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped table-hover mb-0">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Side</th>
                                <th>Type</th>
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
                            <?php foreach ($group['trades'] as $trade): ?>
                                <?php
                                    $pnl_class = isset($trade->pnl) && $trade->pnl >= 0 ? 'text-profit' : 'text-loss';
                                    $side_class = $trade->side == 'BUY' ? 'text-success' : 'text-danger';
                                    $status_class = $trade->status == 'open' ? 'bg-primary' : 'bg-success';

                                    $type_badges = [
                                        'futures' => 'bg-warning text-dark',
                                        'spot' => 'bg-info',
                                        'forex' => 'bg-success',
                                        'indices' => 'bg-primary',
                                        'commodities' => 'bg-danger'
                                    ];
                                    $type_badge = $type_badges[$trade->trade_type] ?? 'bg-secondary';
                                ?>
                                <tr>
                                    <td><?= $trade->id ?></td>
                                    <td class="<?= $side_class ?> fw-bold"><?= $trade->side ?></td>
                                    <td>
                                        <span class="badge <?= $type_badge ?>">
                                            <?= ucfirst($trade->trade_type) ?>
                                        </span>
                                    </td>
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
                                            <?php if ($trade->platform === 'bingx'): ?>
                                                <a href="<?= base_url('trades/close/' . $trade->id) ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to close this trade?')">
                                                    <i class="fas fa-times-circle"></i>
                                                </a>
                                            <?php else: ?>
                                                <button class="btn btn-sm btn-outline-secondary" disabled title="Close via MT EA">
                                                    <i class="fas fa-robot"></i>
                                                </button>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Strategy Subtotals -->
            <?php if ($group_stats && $group_stats['total_trades'] > 0): ?>
                <div class="card-footer bg-light">
                    <div class="row text-center">
                        <div class="col-md-3">
                            <small class="text-muted d-block">Total PNL</small>
                            <strong class="<?= $group_stats['total_pnl'] >= 0 ? 'text-success' : 'text-danger' ?>">
                                <?= number_format($group_stats['total_pnl'], 2) ?> USDT
                            </strong>
                        </div>
                        <div class="col-md-3">
                            <small class="text-muted d-block">PNL %</small>
                            <strong class="<?= $group_stats['total_pnl_percentage'] >= 0 ? 'text-success' : 'text-danger' ?>">
                                <?= number_format($group_stats['total_pnl_percentage'], 2) ?>%
                            </strong>
                        </div>
                        <div class="col-md-3">
                            <small class="text-muted d-block">Winrate</small>
                            <strong><?= number_format($group_stats['winrate'], 1) ?>%</strong>
                            <small class="text-muted">(<?= $group_stats['winning_trades'] ?>/<?= $group_stats['total_trades'] ?>)</small>
                        </div>
                        <div class="col-md-3">
                            <small class="text-muted d-block">Avg Profit/Trade</small>
                            <strong class="<?= $group_stats['profit_per_trade'] >= 0 ? 'text-success' : 'text-danger' ?>">
                                <?= number_format($group_stats['profit_per_trade'], 2) ?> USDT
                            </strong>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<!-- Global Trading Statistics -->
<?php if (!empty($stats) && $stats['total_trades'] > 0): ?>
<div class="card mt-4">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="fas fa-chart-pie me-1"></i>Global Statistics
            <?php if (!empty($current_platform)): ?>
                <span class="badge <?= $current_platform === 'metatrader' ? 'bg-dark' : 'bg-info' ?> ms-2">
                    <?= ucfirst($current_platform) ?>
                </span>
            <?php endif; ?>
            <?php if (!empty($current_symbol)): ?>
                <span class="badge bg-secondary ms-2"><?= $current_symbol ?></span>
            <?php endif; ?>
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
                        <h6 class="text-muted">PNL %</h6>
                        <h4 class="mb-0 <?= $stats['total_pnl_percentage'] >= 0 ? 'text-success' : 'text-danger' ?>">
                            <?= number_format($stats['total_pnl_percentage'], 2) ?>%
                        </h4>
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
                        <h6 class="text-muted">Avg Profit/Trade</h6>
                        <h4 class="mb-0 <?= $stats['profit_per_trade'] >= 0 ? 'text-success' : 'text-danger' ?>">
                            <?= number_format($stats['profit_per_trade'], 2) ?> USDT
                        </h4>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>
