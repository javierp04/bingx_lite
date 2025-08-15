<!-- application/views/strategies/add.php -->
<div class="mb-4">
    <h1 class="h3 mb-0">
        <i class="fas fa-chart-line me-2"></i>Add New Strategy
    </h1>
</div>

<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-body">
                <?= form_open_multipart('strategies/add') ?>
                    <div class="mb-3">
                        <label for="strategy_id" class="form-label">Strategy ID</label>
                        <input type="text" class="form-control" id="strategy_id" name="strategy_id" value="<?= set_value('strategy_id') ?>" required>
                        <div class="form-text">Unique identifier for this strategy (e.g., BTCUSD_EMA_CROSS or EURUSD_RSI_MT)</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="name" class="form-label">Strategy Name</label>
                        <input type="text" class="form-control" id="name" name="name" value="<?= set_value('name') ?>" required>
                        <div class="form-text">Descriptive name for your strategy</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="platform" class="form-label">Platform</label>
                        <select class="form-select" id="platform" name="platform" required onchange="updateTypeOptions()">
                            <option value="">Select Platform</option>
                            <option value="bingx" <?= set_select('platform', 'bingx', true) ?>>BingX</option>
                            <option value="metatrader" <?= set_select('platform', 'metatrader') ?>>MetaTrader</option>
                        </select>
                        <div class="form-text">Choose the trading platform for this strategy</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="type" class="form-label">Strategy Type</label>
                        <select class="form-select" id="type" name="type" required>
                            <option value="">Select Platform First</option>
                        </select>
                        <div class="form-text" id="type-description">Select a platform to see available types</div>
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
</div>

<script>
function updateTypeOptions() {
    const platform = document.getElementById('platform').value;
    const typeSelect = document.getElementById('type');
    const description = document.getElementById('type-description');
    
    // Clear current options
    typeSelect.innerHTML = '';
    
    if (platform === 'bingx') {
        typeSelect.innerHTML = `
            <option value="">Select Type</option>
            <option value="spot">Spot</option>
            <option value="futures">Futures</option>
        `;
        description.textContent = 'Spot trading uses 1x leverage. Futures allows configurable leverage.';
    } else if (platform === 'metatrader') {
        typeSelect.innerHTML = `
            <option value="">Select Type</option>
            <option value="forex">Forex</option>
            <option value="indices">Indices</option>
            <option value="commodities">Commodities</option>
        `;
        description.textContent = 'Choose the asset class for your MetaTrader strategy.';
    } else {
        typeSelect.innerHTML = '<option value="">Select Platform First</option>';
        description.textContent = 'Select a platform to see available types';
    }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    updateTypeOptions();
});
</script>