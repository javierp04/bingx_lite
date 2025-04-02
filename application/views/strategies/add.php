<!-- application/views/strategies/add.php -->
<div class="mb-4">
    <h1 class="h3 mb-0">
        <i class="fas fa-chart-line me-2"></i>Add New Strategy
    </h1>
</div>

<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-body">
                <?= form_open_multipart('strategies/add') ?>
                    <div class="mb-3">
                        <label for="strategy_id" class="form-label">Strategy ID</label>
                        <input type="text" class="form-control" id="strategy_id" name="strategy_id" value="<?= set_value('strategy_id') ?>" required>
                        <div class="form-text">Unique identifier for this strategy (e.g., BTCUSD_EMA_CROSS)</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="name" class="form-label">Strategy Name</label>
                        <input type="text" class="form-control" id="name" name="name" value="<?= set_value('name') ?>" required>
                        <div class="form-text">Descriptive name for your strategy</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="type" class="form-label">Strategy Type</label>
                        <select class="form-select" id="type" name="type" required>
                            <option value="spot" <?= set_select('type', 'spot', true) ?>>Spot</option>
                            <option value="futures" <?= set_select('type', 'futures') ?>>Futures</option>
                        </select>
                        <div class="form-text">Spot trading uses 1x leverage. Futures allows configurable leverage.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3"><?= set_value('description') ?></textarea>
                        <div class="form-text">Optional description of your strategy</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="strategy_image" class="form-label">Strategy Image</label>
                        <input type="file" class="form-control" id="strategy_image" name="strategy_image">
                        <div class="form-text">Upload an image of the TradingView strategy or indicator parameters (optional, max 2MB)</div>
                    </div>
                    
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="active" name="active" value="1" <?= set_checkbox('active', '1', true) ?>>
                        <label class="form-check-label" for="active">Active</label>
                        <div class="form-text">Inactive strategies will not execute trades</div>
                    </div>
                    
                    <div class="d-flex justify-content-between">
                        <a href="<?= base_url('strategies') ?>" class="btn btn-secondary">
                            <i class="fas fa-arrow-left me-1"></i>Back
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i>Save Strategy
                        </button>
                    </div>
                <?= form_close() ?>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Strategy Setup Guide</h5>
            </div>
            <div class="card-body">
                <h6>TradingView Strategy Example:</h6>
                <pre class="bg-light p-2 rounded small"><code>// Create alert message
alertMessage = '{
  "user_id": <?= $this->session->userdata('user_id') ?>,
  "strategy_id": "STRATEGY_ID",
  "ticker": "{{ticker}}",
  "timeframe": "{{interval}}",
  "action": "{{strategy.order.action}}",
  "quantity": 0.01,
  "leverage": 5,
  "environment": "sandbox"
}'

// Send alert
alert(alertMessage, alert.freq_once_per_bar_close)</code></pre>
                <p class="small text-muted">Replace STRATEGY_ID with the ID you enter above.</p>
            </div>
        </div>
    </div>
</div>