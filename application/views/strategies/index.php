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
                        <th>ID</th>
                        <th>Strategy Name</th>
                        <th>Strategy ID</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($strategies)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-3">No strategies found</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($strategies as $strategy): ?>
                            <tr>
                                <td><?= $strategy->id ?></td>
                                <td><?= $strategy->name ?></td>
                                <td><code><?= $strategy->strategy_id ?></code></td>
                                <td>
                                    <span class="badge <?= $strategy->type == 'futures' ? 'bg-warning text-dark' : 'bg-info' ?>">
                                        <?= ucfirst($strategy->type) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge <?= $strategy->active ? 'bg-success' : 'bg-secondary' ?>">
                                        <?= $strategy->active ? 'Active' : 'Inactive' ?>
                                    </span>
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
        
        <h6 class="mt-3">Example TradingView Alert Message:</h6>
        <pre class="bg-light p-3 rounded"><code>{
  "user_id": <?= $this->session->userdata('user_id') ?>,
  "strategy_id": "BTCUSD_EMA_CROSS",
  "ticker": "BTCUSDT",
  "timeframe": "1h",
  "action": "BUY",
  "quantity": 0.01,
  "leverage": 5,
  "environment": "sandbox"
}</code></pre>

        <h6 class="mt-3">Strategy Types:</h6>
        <ul>
            <li><span class="badge bg-info">Spot</span> - For spot trading with 1x leverage</li>
            <li><span class="badge bg-warning text-dark">Futures</span> - For perpetual futures trading with configurable leverage</li>
        </ul>
        
        <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle me-2"></i>Make sure your TradingView alerts include the correct Strategy ID and are properly formatted in JSON.
        </div>
    </div>
</div>