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
            <div class="col-md-5">
                <label for="strategy" class="form-label">Strategy</label>
                <select class="form-select" id="strategy" name="strategy">
                    <option value="">All Strategies</option>
                    <?php foreach ($strategies as $strategy): ?>
                        <option value="<?= $strategy->id ?>" <?= $current_strategy == $strategy->id ? 'selected' : '' ?>>
                            <?= $strategy->name ?> (<?= ucfirst($strategy->platform) ?> - <?= ucfirst($strategy->type) ?>)
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
        
        <?php if (!empty($current_platform) || !empty($current_status) || !empty($current_strategy)): ?>
            <div class="mt-2">
                <a href="<?= base_url('trades') ?>" class="btn btn-sm btn-outline-secondary">
                    <i class="fas fa-times me-1"></i>Clear Filters
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-striped table-hover mb-0">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Platform</th>
                        <th>Symbol</th>
                        <th>Strategy</th>
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
                    <?php if (empty($trades)): ?>
                        <tr>
                            <td colspan="15" class="text-center py-3">No trades found</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($trades as $trade): ?>
                            <?php 
                                $pnl_class = isset($trade->pnl) && $trade->pnl >= 0 ? 'text-profit' : 'text-loss';
                                $side_class = $trade->side == 'BUY' ? 'text-success' : 'text-danger';
                                $status_class = $trade->status == 'open' ? 'bg-primary' : 'bg-success';
                                
                                // Platform badge
                                $platform_badge = $trade->platform === 'metatrader' ? 'bg-dark' : 'bg-info';
                                
                                // Type badges with more variety for MT
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
                                <td>
                                    <span class="badge <?= $platform_badge ?>">
                                        <?= ucfirst($trade->platform) ?>
                                    </span>
                                </td>
                                <td><?= $trade->symbol ?></td>
                                <td>
                                    <code><?= $trade->strategy_external_id ?></code>
                                </td>
                                <td class="<?= $side_class ?>"><?= $trade->side ?></td>
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
            <?php if (!empty($current_platform)): ?>
                <span class="badge <?= $current_platform === 'metatrader' ? 'bg-dark' : 'bg-info' ?> ms-2">
                    <?= ucfirst($current_platform) ?>
                </span>
            <?php else: ?>
                <span class="badge bg-secondary ms-2">All Platforms</span>
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
                            These statistics reflect only your closed trades<?= !empty($current_platform) ? ' on ' . ucfirst($current_platform) : ' across all platforms' ?>.
                            <?php if (empty($current_platform) || $current_platform === 'bingx'): ?>
                                The PNL percentage accounts for leverage on BingX trades.
                            <?php endif; ?>
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
        
        <!-- Platform Breakdown (only if viewing all platforms) -->
        <?php if (empty($current_platform)): ?>
            <div class="row mt-3">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0">Platform Breakdown</h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <?php 
                                // Count trades by platform
                                $bingx_trades = array_filter($trades, function($t) { return $t->platform === 'bingx' && $t->status === 'closed'; });
                                $mt_trades = array_filter($trades, function($t) { return $t->platform === 'metatrader' && $t->status === 'closed'; });
                                
                                $bingx_pnl = array_sum(array_map(function($t) { return $t->pnl ?? 0; }, $bingx_trades));
                                $mt_pnl = array_sum(array_map(function($t) { return $t->pnl ?? 0; }, $mt_trades));
                                ?>
                                <div class="col-md-6">
                                    <div class="d-flex justify-content-between align-items-center p-2 border rounded">
                                        <div>
                                            <span class="badge bg-info">BingX</span>
                                            <div class="mt-1">
                                                <small class="text-muted"><?= count($bingx_trades) ?> trades</small>
                                            </div>
                                        </div>
                                        <div class="text-end">
                                            <div class="<?= $bingx_pnl >= 0 ? 'text-success' : 'text-danger' ?>">
                                                <?= number_format($bingx_pnl, 2) ?> USDT
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="d-flex justify-content-between align-items-center p-2 border rounded">
                                        <div>
                                            <span class="badge bg-dark">MetaTrader</span>
                                            <div class="mt-1">
                                                <small class="text-muted"><?= count($mt_trades) ?> trades</small>
                                            </div>
                                        </div>
                                        <div class="text-end">
                                            <div class="<?= $mt_pnl >= 0 ? 'text-success' : 'text-danger' ?>">
                                                <?= number_format($mt_pnl, 2) ?> USDT
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>