<!-- application/views/strategies/index.php -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">
        <i class="fas fa-chart-line me-2"></i>Trading Strategies
    </h1>
    <a href="<?= base_url('strategies/add') ?>" class="btn btn-primary">
        <i class="fas fa-plus-circle me-1"></i>Add New Strategy
    </a>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-striped table-hover mb-0">
                <thead>
                    <tr>                        
                        <th>Strategy Name</th>
                        <th>Strategy ID</th>
                        <th>Platform</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Image</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($strategies)): ?>
                        <tr>
                            <td colspan="8" class="text-center py-3">No strategies found</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($strategies as $strategy): ?>
                            <tr>                                
                                <td><?= $strategy->name ?></td>
                                <td><code><?= $strategy->strategy_id ?></code></td>
                                <td>
                                    <?php
                                    // Platform badge colors
                                    $platform_badge = $strategy->platform == 'metatrader' ? 'bg-dark' : 'bg-info';
                                    ?>
                                    <span class="badge <?= $platform_badge ?>">
                                        <?= ucfirst($strategy->platform) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php
                                    // Badge colors for strategy types
                                    $badge_class = 'bg-secondary'; // default
                                    switch ($strategy->type) {
                                        case 'spot':
                                            $badge_class = 'bg-info';
                                            break;
                                        case 'futures':
                                            $badge_class = 'bg-warning text-dark';
                                            break;
                                        case 'forex':
                                            $badge_class = 'bg-success';
                                            break;
                                        case 'indices':
                                            $badge_class = 'bg-primary';
                                            break;
                                        case 'commodities':
                                            $badge_class = 'bg-danger';
                                            break;
                                    }
                                    ?>
                                    <span class="badge <?= $badge_class ?>">
                                        <?= ucfirst($strategy->type) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge <?= $strategy->active ? 'bg-success' : 'bg-secondary' ?>">
                                        <?= $strategy->active ? 'Active' : 'Inactive' ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($strategy->image): ?>
                                        <a href="<?= base_url('strategies/view_image/' . $strategy->id) ?>" class="btn btn-sm btn-info">
                                            <i class="fas fa-image"></i> View
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted">No image</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= date('Y-m-d', strtotime($strategy->created_at)) ?></td>
                                <td>
                                    <a href="<?= base_url('strategies/edit/' . $strategy->id) ?>" class="btn btn-sm btn-primary">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="<?= base_url('strategies/delete/' . $strategy->id) ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this strategy?')">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card mt-4">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="fas fa-info-circle me-1"></i>Strategy Configuration Guide
        </h5>
    </div>
    <div class="card-body">
        <p>Each strategy must have a unique Strategy ID that will be included in TradingView webhook alerts. This ID helps the system identify which strategy generated the signal.</p>

        <h6 class="mt-3">Platform & Type Combinations:</h6>
        <div class="row">
            <div class="col-md-6">
                <h6><span class="badge bg-info">BingX Platform</span></h6>
                <ul>
                    <li><span class="badge bg-info">Spot</span> - Cryptocurrency spot trading (1x leverage)</li>
                    <li><span class="badge bg-warning text-dark">Futures</span> - Cryptocurrency perpetual futures (leveraged)</li>
                </ul>
            </div>
            <div class="col-md-6">
                <h6><span class="badge bg-dark">MetaTrader Platform</span></h6>
                <ul>
                    <li><span class="badge bg-success">Forex</span> - Currency pairs (EUR/USD, GBP/USD, etc.)</li>
                    <li><span class="badge bg-primary">Indices</span> - Stock indices (S&P500, NASDAQ, etc.)</li>
                    <li><span class="badge bg-danger">Commodities</span> - Gold, Oil, Silver, etc.</li>
                </ul>
            </div>
        </div>

        <h6 class="mt-3">Webhook URLs:</h6>
        <div class="row">
            <div class="col-md-6">
                <strong>BingX Strategies:</strong>
                <div class="input-group input-group-sm">
                    <input type="text" class="form-control" value="<?= base_url('webhook/tradingview') ?>" readonly>
                    <button class="btn btn-outline-secondary" onclick="copyToClipboard(this.previousElementSibling)">
                        <i class="fas fa-copy"></i>
                    </button>
                </div>
            </div>
            <div class="col-md-6">
                <strong>MetaTrader Strategies:</strong>
                <div class="input-group input-group-sm">
                    <input type="text" class="form-control" value="<?= base_url('metatrader/webhook') ?>" readonly>
                    <button class="btn btn-outline-secondary" onclick="copyToClipboard(this.previousElementSibling)">
                        <i class="fas fa-copy"></i>
                    </button>
                </div>
            </div>
        </div>

        <h6 class="mt-3">Example TradingView Alert Messages:</h6>

        <div class="row">
            <div class="col-md-12">
                <strong>Alert Format:</strong>
                <pre class="bg-light p-3 rounded"><code>{
  "user_id": <?= $this->session->userdata('user_id') ?>,
  "strategy_id": "YOUR_STRATEGY_ID",
  "ticker": "{{ticker}}",
  "timeframe": "{{interval}}",
  "action": "{{strategy.order.action}}",
  "quantity": "{{strategy.order.contracts}}",
  "position_id": "{{strategy.order.comment}}",
  "leverage": 8,
  "environment": "production"
}</code></pre>
            </div>

        </div>

        <div class="alert alert-warning mt-3">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <strong>Important:</strong> Make sure your TradingView alerts include the correct Strategy ID and are properly formatted in JSON.
            BingX uses timeframe as strings (1h, 5m, etc.) while MetaTrader uses minutes as integers (60, 5, etc.).
        </div>
    </div>
</div>

<script>
    function copyToClipboard(element) {
        element.select();
        document.execCommand('copy');

        // Visual feedback
        const button = element.nextElementSibling;
        const originalHTML = button.innerHTML;
        button.innerHTML = '<i class="fas fa-check"></i>';
        button.classList.add('btn-success');

        setTimeout(() => {
            button.innerHTML = originalHTML;
            button.classList.remove('btn-success');
        }, 1500);
    }
</script>