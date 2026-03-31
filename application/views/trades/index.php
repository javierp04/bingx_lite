<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">
        <i class="fas fa-history me-2"></i>Trade History
    </h1>
</div>

<!-- Source Tabs (module-aware) -->
<?php
$current_source = isset($current_source) ? $current_source : null;
$source_tabs = [];
if (has_module('bingx'))      $source_tabs['bingx']         = ['label' => 'BingX',          'icon' => 'fas fa-chart-line'];
if (has_module('metatrader')) $source_tabs['metatrader_tv'] = ['label' => 'MetaTrader TV',   'icon' => 'fas fa-robot'];
if (has_module('atvip'))      $source_tabs['atvip']         = ['label' => 'AT VIP Trading',  'icon' => 'fas fa-broadcast-tower'];
$show_tabs = count($source_tabs) > 1;
$is_atvip_tab = ($current_source === 'atvip');
$hide_strategy = isset($hide_strategy) ? $hide_strategy : $is_atvip_tab;
?>
<?php if ($show_tabs): ?>
    <ul class="nav nav-tabs mb-4">
        <li class="nav-item">
            <a class="nav-link <?= empty($current_source) ? 'active' : '' ?>" href="<?= base_url('trades') ?>">
                All Platforms
            </a>
        </li>
        <?php foreach ($source_tabs as $src_key => $src_meta): ?>
            <li class="nav-item">
                <a class="nav-link <?= $current_source === $src_key ? 'active' : '' ?>" href="<?= base_url('trades') ?>?<?= http_build_query(['source' => $src_key]) ?>">
                    <i class="<?= $src_meta['icon'] ?> me-1"></i><?= $src_meta['label'] ?>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>

<!-- Filter Form -->
<div class="card mb-4">
    <div class="card-body">
        <?= form_open('trades', ['method' => 'get', 'class' => 'row g-3']) ?>
        <?php if ($current_source): ?>
            <input type="hidden" name="source" value="<?= $current_source ?>">
        <?php endif; ?>
        <div class="col-md-<?= $hide_strategy ? '9' : '3' ?>">
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
        <?php if (!$hide_strategy): ?>
            <div class="col-md-6">
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
        <?php endif; ?>
        <div class="col-md-3 d-flex align-items-end">
            <button type="submit" class="btn btn-primary w-100">
                <i class="fas fa-filter me-1"></i>Filter
            </button>
        </div>
        <?= form_close() ?>

        <?php if (!empty($current_strategy) || !empty($current_symbol)): ?>
            <div class="mt-2">
                <a href="<?= base_url('trades') ?><?= $current_source ? '?source=' . $current_source : '' ?>" class="btn btn-sm btn-outline-secondary">
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
    <?php
    $group_index = 0;
    $is_atvip_view = isset($is_atvip_view) ? $is_atvip_view : false;
    ?>
    <?php foreach ($grouped_trades as $group): ?>
        <?php
        $platform_badge = $group['platform'] === 'metatrader' ? 'bg-dark' : 'bg-info';
        $group_stats = $group['stats'];
        $collapse_id = 'collapse-group-' . $group_index;
        ?>
        <div class="card mb-3">
            <!-- Group Header with Subtotals (always visible) -->
            <div class="card-header bg-light" style="cursor: pointer;" data-bs-toggle="collapse" data-bs-target="#<?= $collapse_id ?>">
                <div class="d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-chevron-down me-2 collapse-icon" id="icon-<?= $collapse_id ?>"></i>
                        <?php if ($is_atvip_view): ?>
                            <h5 class="mb-0">
                                <i class="fas fa-chart-area me-1"></i><?= $group['symbol'] ?>
                            </h5>
                        <?php else: ?>
                            <h5 class="mb-0">
                                <?= $group['strategy_name'] ?>
                                <span class="badge bg-secondary ms-2"><?= $group['symbol'] ?></span>
                                <span class="badge <?= $platform_badge ?> ms-1"><?= ucfirst($group['platform']) ?></span>
                            </h5>
                        <?php endif; ?>
                    </div>
                    <?php if ($group_stats && $group_stats['total_trades'] > 0): ?>
                        <div class="d-flex align-items-center gap-4">
                            <div class="text-center">
                                <small class="text-muted d-block" style="font-size: 0.7rem;">PNL</small>
                                <strong class="<?= $group_stats['total_pnl'] >= 0 ? 'text-success' : 'text-danger' ?>">
                                    <?= number_format($group_stats['total_pnl'], 2) ?>
                                </strong>
                            </div>
                            <div class="text-center">
                                <small class="text-muted d-block" style="font-size: 0.7rem;">PNL %</small>
                                <strong class="<?= $group_stats['total_pnl_percentage'] >= 0 ? 'text-success' : 'text-danger' ?>">
                                    <?= number_format($group_stats['total_pnl_percentage'], 2) ?>%
                                </strong>
                            </div>
                            <div class="text-center">
                                <small class="text-muted d-block" style="font-size: 0.7rem;">Winrate</small>
                                <strong><?= number_format($group_stats['winrate'], 1) ?>%</strong>
                            </div>
                            <div class="text-center">
                                <small class="text-muted d-block" style="font-size: 0.7rem;">Trades</small>
                                <strong><?= $group_stats['total_trades'] ?></strong>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Collapsible Trades Table -->
            <div class="collapse" id="<?= $collapse_id ?>">
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
                                    <th>Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($group['trades'] as $trade): ?>
                                    <?php
                                    $pnl_class = isset($trade->pnl) && $trade->pnl >= 0 ? 'text-success' : 'text-danger';
                                    $side_class = $trade->side == 'BUY' ? 'text-success' : 'text-danger';

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
                                            <?= isset($trade->pnl) ? number_format($trade->pnl, 2) . ' USD' : '-' ?>
                                        </td>
                                        <td><?= date('Y-m-d H:i', strtotime($trade->created_at)) ?></td>
                                        <td>
                                            <a href="<?= base_url('trades/detail/' . $trade->id) ?>" class="btn btn-sm btn-info">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <?php $group_index++; ?>
    <?php endforeach; ?>
<?php endif; ?>

<script>
    // Toggle chevron icon on collapse
    document.querySelectorAll('[data-bs-toggle="collapse"]').forEach(function(element) {
        var targetId = element.getAttribute('data-bs-target');
        var target = document.querySelector(targetId);
        var icon = element.querySelector('.collapse-icon');

        target.addEventListener('show.bs.collapse', function() {
            icon.classList.remove('fa-chevron-down');
            icon.classList.add('fa-chevron-up');
        });
        target.addEventListener('hide.bs.collapse', function() {
            icon.classList.remove('fa-chevron-up');
            icon.classList.add('fa-chevron-down');
        });
    });
</script>

<!-- Global Trading Statistics -->
<?php if (!empty($stats) && $stats['total_trades'] > 0): ?>
    <div class="card mt-4">
        <div class="card-header">
            <h5 class="mb-0">
                <i class="fas fa-chart-pie me-1"></i>Global Statistics
                <?php if (!empty($current_source) && isset($source_tabs[$current_source])): ?>
                    <span class="badge bg-info ms-2">
                        <?= $source_tabs[$current_source]['label'] ?>
                    </span>
                <?php endif; ?>
                <?php if (!empty($current_symbol)): ?>
                    <span class="badge bg-secondary ms-2"><?= $current_symbol ?></span>
                <?php endif; ?>
            </h5>
        </div>
        <div class="card-body">
            <!-- First Row: Main Stats -->
            <div class="row">
                <div class="col-md-3 mb-3">
                    <div class="card h-100 <?= $stats['total_pnl'] >= 0 ? 'bg-success bg-opacity-10' : 'bg-danger bg-opacity-10' ?>">
                        <div class="card-body text-center">
                            <h6 class="text-muted">Total PNL</h6>
                            <h4 class="mb-0 <?= $stats['total_pnl'] >= 0 ? 'text-success' : 'text-danger' ?>">
                                <?= number_format($stats['total_pnl'], 2) ?> USD
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
                                <?= number_format($stats['profit_per_trade'], 2) ?> USD
                            </h4>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Second Row: Additional Stats -->
            <div class="row">
                <div class="col-md-3 mb-3">
                    <div class="card h-100">
                        <div class="card-body text-center">
                            <h6 class="text-muted">Total Invested</h6>
                            <h4 class="mb-0">
                                <?= number_format($stats['total_invested'], 2) ?> USD
                            </h4>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card h-100">
                        <div class="card-body text-center">
                            <h6 class="text-muted">Trades</h6>
                            <h4 class="mb-0">
                                <span class="text-success"><?= $stats['winning_trades'] ?> W</span>
                                /
                                <span class="text-danger"><?= $stats['losing_trades'] ?> L</span>
                            </h4>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card h-100 bg-success bg-opacity-10">
                        <div class="card-body text-center">
                            <h6 class="text-muted">Best Trade</h6>
                            <h4 class="mb-0 text-success">
                                +<?= number_format($stats['best_trade'], 2) ?> USD
                            </h4>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card h-100 bg-danger bg-opacity-10">
                        <div class="card-body text-center">
                            <h6 class="text-muted">Worst Trade</h6>
                            <h4 class="mb-0 text-danger">
                                <?= number_format($stats['worst_trade'], 2) ?> USD
                            </h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>