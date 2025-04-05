<div class="mb-4">
    <div class="d-flex justify-content-between align-items-center">
        <h1 class="h3 mb-0">
            <i class="fas fa-exchange-alt me-2"></i>Trade Details
        </h1>
        <div>
            <a href="<?= base_url('trades') ?>" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-1"></i>Back to Trades
            </a>
            <?php if ($trade->status == 'open'): ?>
                <a href="<?= base_url('trades/close/' . $trade->id) ?>" class="btn btn-danger" onclick="return confirm('Are you sure you want to close this trade?')">
                    <i class="fas fa-times-circle me-1"></i>Close Trade
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-8">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Trade Information</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Symbol:</strong> <?= $trade->symbol ?></p>
                        <p><strong>Strategy:</strong> <?= $trade->strategy_name ?> (<?= $trade->strategy_external_id ?>)</p>
                        <p><strong>Side:</strong> <span class="<?= $trade->side == 'BUY' ? 'text-success' : 'text-danger' ?>"><?= $trade->side ?></span></p>
                        <p><strong>Type:</strong> 
                            <span class="badge <?= $trade->trade_type == 'futures' ? 'bg-warning text-dark' : 'bg-info' ?>">
                                <?= ucfirst($trade->trade_type) ?>
                            </span>
                        </p>
                        <p><strong>Timeframe:</strong> <?= $trade->timeframe ?></p>
                        <p><strong>Position ID:</strong> <?= isset($trade->position_id) ? $trade->position_id : 'N/A' ?></p>
                    </div>
                    <div class="col-md-6">                        
                        <p><strong>Status:</strong> 
                            <span class="badge <?= $trade->status == 'open' ? 'bg-primary' : 'bg-success' ?>">
                                <?= ucfirst($trade->status) ?>
                            </span>
                        </p>
                        <p><strong>Order ID:</strong> <?= $trade->order_id ? $trade->order_id : 'N/A' ?></p>
                        <p><strong>Opened:</strong> <?= date('Y-m-d H:i:s', strtotime($trade->created_at)) ?></p>
                        <?php if ($trade->status == 'closed' && $trade->closed_at): ?>
                            <p><strong>Closed:</strong> <?= date('Y-m-d H:i:s', strtotime($trade->closed_at)) ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Trade Performance</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Entry Price:</strong> <?= number_format($trade->entry_price, 2) ?> USDT</p>
                        <?php if ($trade->status == 'closed' && $trade->exit_price): ?>
                            <p><strong>Exit Price:</strong> <?= number_format($trade->exit_price, 2) ?> USDT</p>
                        <?php endif; ?>
                        <p><strong>Quantity:</strong> <?= rtrim(rtrim(number_format($trade->quantity, 8), '0'), '.') ?></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Leverage:</strong> <?= $trade->leverage ?>x</p>
                        <?php if (isset($trade->pnl)): ?>
                            <?php $pnl_class = $trade->pnl >= 0 ? 'text-profit' : 'text-loss'; ?>
                            <p><strong>PNL:</strong> <span class="<?= $pnl_class ?>"><?= number_format($trade->pnl, 2) ?> USDT</span></p>
                            
                            <?php if ($trade->entry_price > 0 && $trade->quantity > 0): ?>
                                <?php 
                                    $total_invested = $trade->entry_price * $trade->quantity / $trade->leverage;
                                    $pnl_percentage = $total_invested > 0 ? ($trade->pnl / $total_invested) * 100 : 0;
                                ?>
                                <p><strong>PNL %:</strong> <span class="<?= $pnl_class ?>"><?= number_format($pnl_percentage, 2) ?>%</span></p>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Webhook Data</h5>
            </div>
            <div class="card-body">
                <?php if ($trade->webhook_data): ?>
                    <pre class="bg-light p-3 rounded"><code><?= json_encode(json_decode($trade->webhook_data), JSON_PRETTY_PRINT) ?></code></pre>
                <?php else: ?>
                    <p class="text-muted">No webhook data available</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>