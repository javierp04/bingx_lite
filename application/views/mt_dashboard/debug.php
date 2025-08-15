<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">
        <i class="fas fa-bug me-2"></i>MetaTrader Debug Panel
    </h1>
    <a href="<?= base_url('mt_dashboard') ?>" class="btn btn-secondary">
        <i class="fas fa-arrow-left me-1"></i>Back to Dashboard
    </a>
</div>

<div class="row">
    <!-- Signal Tester -->
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-vial me-1"></i>Signal Tester
                </h5>
            </div>
            <div class="card-body">
                <p class="text-muted mb-3">
                    Simulate a TradingView webhook signal to test the MetaTrader processing pipeline.
                    The signal will be queued and available for EA polling. Note: timeframe should be in minutes (1, 5, 15, 30, 60, 240, etc.).
                </p>
                
                <?= form_open('mt_dashboard/test_signal') ?>
                    <div class="mb-3">
                        <label for="signal_data" class="form-label">Signal JSON Data</label>
                        <textarea class="form-control" id="signal_data" name="signal_data" rows="15" placeholder="Paste your TradingView webhook JSON here..." required></textarea>
                        <div class="form-text">
                            This should be the exact JSON that TradingView would send to the webhook endpoint.
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="btn-group">
                            <button type="button" class="btn btn-outline-secondary" onclick="loadTemplate('long')">
                                <i class="fas fa-arrow-up text-success me-1"></i>Load LONG Template
                            </button>
                            <button type="button" class="btn btn-outline-secondary" onclick="loadTemplate('short')">
                                <i class="fas fa-arrow-down text-danger me-1"></i>Load SHORT Template
                            </button>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-play me-1"></i>Test Signal
                        </button>
                    </div>
                <?= form_close() ?>
            </div>
        </div>
    </div>
    
    <!-- Templates and Tools -->
    <div class="col-md-4">
        <!-- Quick Templates -->
        <div class="card mb-3">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-template me-1"></i>Quick Templates
                </h6>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label for="template_operation" class="form-label">Operation</label>
                    <select class="form-select form-select-sm" id="template_operation">
                        <option value="open" selected>OPEN Position</option>
                        <option value="close">CLOSE Position</option>
                    </select>
                </div>
                
                <div class="mb-3">
                    <label for="template_user" class="form-label">User</label>
                    <select class="form-select form-select-sm" id="template_user">
                        <?php foreach ($users as $user): ?>
                            <option value="<?= $user->id ?>"><?= $user->username ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="mb-3">
                    <label for="template_strategy" class="form-label">Strategy</label>
                    <select class="form-select form-select-sm" id="template_strategy">
                        <?php foreach ($strategies as $strategy): ?>
                            <option value="<?= $strategy->strategy_id ?>" data-name="<?= $strategy->name ?>">
                                <?= $strategy->name ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="mb-3">
                    <label for="template_symbol" class="form-label">Symbol</label>
                    <input type="text" class="form-control form-control-sm" id="template_symbol" value="EURUSD" placeholder="e.g., EURUSD, GBPUSD">
                </div>
                
                <div class="mb-3">
                    <label for="template_timeframe" class="form-label">Timeframe (Minutes)</label>
                    <select class="form-select form-select-sm" id="template_timeframe">
                        <option value="1">1 Minute</option>
                        <option value="5">5 Minutes</option>
                        <option value="15">15 Minutes</option>
                        <option value="30">30 Minutes</option>
                        <option value="60" selected>1 Hour (60min)</option>
                        <option value="240">4 Hours (240min)</option>
                        <option value="1440">1 Day (1440min)</option>
                    </select>
                </div>
            </div>
        </div>
        
        <!-- API Endpoints -->
        <div class="card mb-3">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-link me-1"></i>API Endpoints
                </h6>
            </div>
            <div class="card-body">
                <div class="mb-2">
                    <strong>Webhook URL:</strong>
                    <div class="input-group input-group-sm">
                        <input type="text" class="form-control" value="<?= base_url('metatrader/webhook') ?>" readonly>
                        <button class="btn btn-outline-secondary" onclick="copyToClipboard(this.previousElementSibling)">
                            <i class="fas fa-copy"></i>
                        </button>
                    </div>
                </div>
                
                <div class="mb-2">
                    <strong>Get Pending Signals:</strong>
                    <div class="input-group input-group-sm">
                        <input type="text" class="form-control" value="<?= base_url('api/mt/pending_signals') ?>" readonly>
                        <button class="btn btn-outline-secondary" onclick="copyToClipboard(this.previousElementSibling)">
                            <i class="fas fa-copy"></i>
                        </button>
                    </div>
                </div>
                
                <div class="mb-2">
                    <strong>Mark Processed:</strong>
                    <div class="input-group input-group-sm">
                        <input type="text" class="form-control" value="<?= base_url('api/mt/mark_processed') ?>" readonly>
                        <button class="btn btn-outline-secondary" onclick="copyToClipboard(this.previousElementSibling)">
                            <i class="fas fa-copy"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- JSON Validator -->
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-check-circle me-1"></i>JSON Validator
                </h6>
            </div>
            <div class="card-body">
                <button type="button" class="btn btn-outline-primary btn-sm w-100" onclick="validateJson()">
                    <i class="fas fa-check me-1"></i>Validate JSON
                </button>
                <div id="validation-result" class="mt-2"></div>
            </div>
        </div>
    </div>
</div>

<!-- Signal Templates -->
<script>
const signalTemplates = {
    long: {
        user_id: null,
        strategy_id: null,
        ticker: null,
        timeframe: null,
        action: null, // Will be set based on operation (open/close)
        quantity: "0.1",
        position_id: "12345",
        leverage: 100,
        take_profit: null,
        stop_loss: null,
        comment: null // Will be set based on final action
    },
    short: {
        user_id: null,
        strategy_id: null,
        ticker: null,
        timeframe: null,
        action: null, // Will be set based on operation (open/close)
        quantity: "0.1",
        position_id: "12346",
        leverage: 100,
        take_profit: null,
        stop_loss: null,
        comment: null // Will be set based on final action
    }
};

function loadTemplate(direction) {
    const template = { ...signalTemplates[direction] };
    const operation = document.getElementById('template_operation').value;
    
    // Fill template with current form values
    template.user_id = parseInt(document.getElementById('template_user').value);
    template.strategy_id = document.getElementById('template_strategy').value;
    template.ticker = document.getElementById('template_symbol').value;
    template.timeframe = parseInt(document.getElementById('template_timeframe').value);
    
    // Generate random 6-digit position ID
    template.position_id = Math.floor(100000 + Math.random() * 900000).toString();
    
    // Set action based on operation + direction
    if (operation === 'open') {
        if (direction === 'long') {
            template.action = 'buy';
            template.comment = 'MT BUY (open long) signal from TradingView';
        } else { // short
            template.action = 'short';
            template.comment = 'MT SHORT (open short) signal from TradingView';
        }
    } else { // close
        if (direction === 'long') {
            template.action = 'sell';
            template.comment = 'MT SELL (close long) signal from TradingView';
        } else { // short
            template.action = 'cover';
            template.comment = 'MT COVER (close short) signal from TradingView';
        }
    }
    
    // Load into textarea
    document.getElementById('signal_data').value = JSON.stringify(template, null, 2);
    
    // Validate automatically
    validateJson();
}

function validateJson() {
    const textarea = document.getElementById('signal_data');
    const resultDiv = document.getElementById('validation-result');
    
    try {
        const parsed = JSON.parse(textarea.value);
        
        // Check required fields
        const requiredFields = ['user_id', 'strategy_id', 'ticker', 'timeframe', 'action'];
        const missing = requiredFields.filter(field => !parsed[field]);
        
        // Validate action
        const validActions = ['buy', 'short', 'sell', 'cover'];
        const hasValidAction = validActions.includes(parsed.action);
        
        if (missing.length > 0) {
            resultDiv.innerHTML = `
                <div class="alert alert-warning alert-sm p-2">
                    <i class="fas fa-exclamation-triangle me-1"></i>
                    Missing fields: ${missing.join(', ')}
                </div>
            `;
        } else if (!hasValidAction) {
            resultDiv.innerHTML = `
                <div class="alert alert-warning alert-sm p-2">
                    <i class="fas fa-exclamation-triangle me-1"></i>
                    Invalid action. Valid: buy, short, sell, cover
                </div>
            `;
        } else {
            resultDiv.innerHTML = `
                <div class="alert alert-success alert-sm p-2">
                    <i class="fas fa-check-circle me-1"></i>
                    Valid JSON with all required fields
                </div>
            `;
        }
        
        // Remove any syntax error styling
        textarea.classList.remove('is-invalid');
        
    } catch (e) {
        resultDiv.innerHTML = `
            <div class="alert alert-danger alert-sm p-2">
                <i class="fas fa-times-circle me-1"></i>
                Invalid JSON: ${e.message}
            </div>
        `;
        
        // Add error styling
        textarea.classList.add('is-invalid');
    }
}

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

// Auto-validate as user types
document.addEventListener('DOMContentLoaded', function() {
    const textarea = document.getElementById('signal_data');
    let timeout;
    
    textarea.addEventListener('input', function() {
        clearTimeout(timeout);
        timeout = setTimeout(validateJson, 500);
    });
    
    // Auto-reload template when operation changes
    document.getElementById('template_operation').addEventListener('change', function() {
        // Reload the currently selected template (default to long)
        loadTemplate('long');
    });
    
    // Load default template
    loadTemplate('long');
});
</script>

<style>
.alert-sm {
    font-size: 0.875rem;
}

.form-control.is-invalid {
    border-color: #dc3545;
}

.input-group-sm .form-control {
    font-size: 0.875rem;
}
</style>