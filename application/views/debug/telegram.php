<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">
        <i class="fas fa-paper-plane me-2"></i>Telegram Debug Panel
    </h1>
    <div class="btn-group">
        <a href="<?= base_url('telegram_signals') ?>" class="btn btn-info btn-sm">
            <i class="fas fa-list me-1"></i>Signals
        </a>
        <a href="<?= base_url('my_trading') ?>" class="btn btn-outline-primary btn-sm">
            <i class="fas fa-chart-line me-1"></i>Trading
        </a>
    </div>
</div>

<div class="row">
    <div class="col-lg-8">
        <!-- Tabs -->
        <ul class="nav nav-tabs mb-3" role="tablist">
            <li class="nav-item">
                <button class="nav-link active" id="json-tab" data-bs-toggle="tab" data-bs-target="#json-panel" type="button">
                    <i class="fas fa-magic me-1"></i>JSON Generator
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link" id="webhook-tab" data-bs-toggle="tab" data-bs-target="#webhook-panel" type="button">
                    <i class="fas fa-globe me-1"></i>Webhook
                </button>
            </li>
        </ul>

        <div class="tab-content">
            <!-- JSON Generator Tab -->
            <div class="tab-pane fade show active" id="json-panel">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-magic me-1"></i>Signal Generator</h5>
                    </div>
                    <div class="card-body">
                        <form id="jsonForm">
                            <div class="row g-2 mb-3">
                                <div class="col-md-3">
                                    <label class="form-label small mb-1">Ticker</label>
                                    <select class="form-select form-select-sm" id="ticker" name="ticker" required>
                                        <option value="">Select</option>
                                        <?php foreach ($available_tickers as $ticker): ?>
                                            <option value="<?= $ticker->symbol ?>"><?= $ticker->symbol ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label small mb-1">Type</label>
                                    <select class="form-select form-select-sm" id="op_type" name="op_type" required>
                                        <option value="">Select</option>
                                        <option value="LONG">LONG</option>
                                        <option value="SHORT">SHORT</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label small mb-1">Entry</label>
                                    <input type="number" class="form-control form-control-sm" id="entry_price" name="entry_price" step="0.00001" placeholder="1.16554" required>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label small mb-1">Diff</label>
                                    <input type="number" class="form-control form-control-sm" id="diff_points" name="diff_points" step="0.00001" placeholder="0.003" required>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small mb-1"><i class="fas fa-robot me-1"></i>AI Provider</label>
                                    <select class="form-select form-select-sm" id="json_ai_provider" name="ai_provider">
                                        <option value="openai">OpenAI (GPT-4o-mini)</option>
                                        <option value="claude" selected>Claude (Sonnet 4.5)</option>
                                    </select>
                                </div>
                            </div>

                            <div class="text-end mb-3">
                                <button type="button" class="btn btn-info btn-sm" onclick="calculate()">
                                    <i class="fas fa-calculator me-1"></i>Calculate
                                </button>
                            </div>

                            <!-- Hidden inputs -->
                            <input type="hidden" id="stop_loss_1" name="stop_loss_1">
                            <input type="hidden" id="stop_loss_2" name="stop_loss_2">
                            <input type="hidden" id="tp1" name="tp1">
                            <input type="hidden" id="tp2" name="tp2">
                            <input type="hidden" id="tp3" name="tp3">
                            <input type="hidden" id="tp4" name="tp4">
                            <input type="hidden" id="tp5" name="tp5">
                        </form>

                        <!-- Preview Container -->
                        <div id="preview-container"></div>

                        <!-- Results Container -->
                        <div id="json-results"></div>
                    </div>
                </div>
            </div>

            <!-- Webhook Tab -->
            <div class="tab-pane fade" id="webhook-panel">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-globe me-1"></i>Webhook Simulator</h5>
                    </div>
                    <div class="card-body">
                        <form id="webhookForm">
                            <div class="row g-2 mb-3">
                                <div class="col-md-8">
                                    <label class="form-label small mb-1">Telegram Message</label>
                                    <input type="text" class="form-control form-control-sm" id="telegram_message" name="telegram_message"
                                           placeholder="Sentimiento #ES https://www.tradingview.com/x/abc123" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small mb-1"><i class="fas fa-robot me-1"></i>AI Provider</label>
                                    <select class="form-select form-select-sm" id="webhook_ai_provider" name="ai_provider">
                                        <option value="openai">OpenAI (GPT-4o-mini)</option>
                                        <option value="claude" selected>Claude (Sonnet 4.5)</option>
                                    </select>
                                </div>
                            </div>
                            <div class="text-end">
                                <button type="submit" class="btn btn-primary btn-sm">
                                    <i class="fas fa-paper-plane me-1"></i>Send Webhook
                                </button>
                            </div>
                        </form>
                        <div id="webhook-results"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <!-- EA Test -->
        <div class="card mb-3">
            <div class="card-header">
                <h6 class="mb-0"><i class="fas fa-robot me-1"></i>EA Simulation</h6>
            </div>
            <div class="card-body">
                <div class="row g-2 mb-2">
                    <div class="col-6">
                        <label class="form-label small mb-1">User</label>
                        <select class="form-select form-select-sm" id="test_user">
                            <?php foreach ($users as $user): ?>
                                <option value="<?= $user->id ?>"><?= $user->username ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label small mb-1">Ticker</label>
                        <select class="form-select form-select-sm" id="test_ticker">
                            <option value="">Auto</option>
                        </select>
                    </div>
                </div>
                <button class="btn btn-primary btn-sm w-100" onclick="testEA()">
                    <i class="fas fa-play me-1"></i>Test EA Poll
                </button>
                <div id="ea-results"></div>
            </div>
        </div>

        <!-- Quick Links -->
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0"><i class="fas fa-link me-1"></i>Quick Links</h6>
            </div>
            <div class="card-body p-2">
                <div class="d-grid gap-1">
                    <a href="<?= base_url('telegram_signals') ?>" class="btn btn-outline-primary btn-sm">
                        <i class="fas fa-list me-1"></i>All Signals
                    </a>
                    <a href="<?= base_url('my_trading') ?>" class="btn btn-outline-success btn-sm">
                        <i class="fas fa-chart-line me-1"></i>My Trading
                    </a>
                    <a href="<?= base_url('available_tickers') ?>" class="btn btn-outline-secondary btn-sm">
                        <i class="fas fa-tags me-1"></i>Tickers
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const BASE_URL = '<?= base_url() ?>';

// Form handlers
document.getElementById('jsonForm').addEventListener('submit', e => e.preventDefault());
document.getElementById('webhookForm').addEventListener('submit', e => {
    e.preventDefault();
    simulateWebhook();
});

// Update test ticker on main ticker change
document.getElementById('ticker').addEventListener('change', function() {
    const testTicker = document.getElementById('test_ticker');
    testTicker.innerHTML = this.value ? `<option value="${this.value}">${this.value}</option>` : '<option value="">Auto</option>';
});

// Calculate SL/TP values
function calculate() {
    const ticker = document.getElementById('ticker').value;
    const opType = document.getElementById('op_type').value;
    const entry = parseFloat(document.getElementById('entry_price').value);
    const diff = parseFloat(document.getElementById('diff_points').value);

    if (!ticker || !opType || !entry || !diff) {
        alert('Fill all fields');
        return;
    }

    if (entry <= 0 || diff <= 0) {
        alert('Entry and Diff must be > 0');
        return;
    }

    let sl1, sl2, tp1, tp2, tp3, tp4, tp5;

    if (opType === 'LONG') {
        sl1 = entry - (diff * 2);
        sl2 = entry - diff;
        tp1 = entry + diff;
        tp2 = entry + (diff * 2);
        tp3 = entry + (diff * 3);
        tp4 = entry + (diff * 4);
        tp5 = entry + (diff * 5);
    } else {
        sl1 = entry + (diff * 2);
        sl2 = entry + diff;
        tp1 = entry - diff;
        tp2 = entry - (diff * 2);
        tp3 = entry - (diff * 3);
        tp4 = entry - (diff * 4);
        tp5 = entry - (diff * 5);
    }

    // Save to hidden inputs
    document.getElementById('stop_loss_1').value = sl1.toFixed(5);
    document.getElementById('stop_loss_2').value = sl2.toFixed(5);
    document.getElementById('tp1').value = tp1.toFixed(5);
    document.getElementById('tp2').value = tp2.toFixed(5);
    document.getElementById('tp3').value = tp3.toFixed(5);
    document.getElementById('tp4').value = tp4.toFixed(5);
    document.getElementById('tp5').value = tp5.toFixed(5);

    // Render preview
    document.getElementById('preview-container').innerHTML = `
        <div class="alert alert-info mb-3">
            <h6 class="mb-2"><i class="fas fa-eye me-1"></i>Preview</h6>
            <div class="row small">
                <div class="col-6">
                    <strong>Entry:</strong> ${entry.toFixed(5)}<br>
                    <strong>SL1:</strong> ${sl1.toFixed(5)}<br>
                    <strong>SL2:</strong> ${sl2.toFixed(5)}
                </div>
                <div class="col-6">
                    <strong>TP1:</strong> ${tp1.toFixed(5)}<br>
                    <strong>TP2:</strong> ${tp2.toFixed(5)}<br>
                    <strong>TP3:</strong> ${tp3.toFixed(5)}<br>
                    <strong>TP4:</strong> ${tp4.toFixed(5)}<br>
                    <strong>TP5:</strong> ${tp5.toFixed(5)}
                </div>
            </div>
        </div>
        <div class="text-end">
            <button type="button" class="btn btn-success btn-sm" onclick="generateSignal()">
                <i class="fas fa-magic me-1"></i>Generate Signal
            </button>
        </div>
    `;
}

// Generate signal
function generateSignal() {
    const form = document.getElementById('jsonForm');
    const formData = new FormData(form);
    const btn = event.target;

    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Generating...';
    btn.disabled = true;

    fetch(`${BASE_URL}debug/telegram/generate`, {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            const aiProviderName = data.data.ai_provider === 'claude' ? 'Claude Sonnet 4.5' : 'OpenAI GPT-4o-mini';
            const aiProviderBadge = data.data.ai_provider === 'claude'
                ? '<span class="badge bg-primary">Claude</span>'
                : '<span class="badge bg-success">OpenAI</span>';

            document.getElementById('json-results').innerHTML = `
                <div class="alert alert-success mt-3">
                    <strong><i class="fas fa-check me-1"></i>Generated!</strong> ${aiProviderBadge}
                    <p class="mb-2 small">${data.message}</p>
                    <p class="mb-2 small"><strong>Signal ID:</strong> ${data.data.telegram_signal_id} | <strong>AI:</strong> ${aiProviderName}</p>
                    <a href="${data.data.view_url}" target="_blank" class="btn btn-sm btn-primary">
                        <i class="fas fa-eye me-1"></i>View Signal
                    </a>
                </div>
            `;
            form.reset();
            document.getElementById('preview-container').innerHTML = '';
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(e => alert('Error: ' + e.message))
    .finally(() => {
        btn.innerHTML = '<i class="fas fa-magic me-1"></i>Generate Signal';
        btn.disabled = false;
    });
}

// Simulate webhook
function simulateWebhook() {
    const msg = document.getElementById('telegram_message').value.trim();
    const aiProvider = document.getElementById('webhook_ai_provider').value;
    const btn = document.querySelector('#webhookForm button[type="submit"]');

    if (!msg) {
        alert('Enter message');
        return;
    }

    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Processing...';
    btn.disabled = true;

    const formData = new FormData();
    formData.append('message', msg);
    formData.append('ai_provider', aiProvider);

    fetch(`${BASE_URL}debug/telegram/simulate`, {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            const aiProviderName = data.data.ai_provider === 'claude' ? 'Claude Sonnet 4.5' : 'OpenAI GPT-4o-mini';
            const aiProviderBadge = data.data.ai_provider === 'claude'
                ? '<span class="badge bg-primary">Claude</span>'
                : '<span class="badge bg-success">OpenAI</span>';

            document.getElementById('webhook-results').innerHTML = `
                <div class="alert alert-success mt-3">
                    <strong><i class="fas fa-check me-1"></i>Success!</strong> ${aiProviderBadge}
                    <p class="mb-2 small">${data.message}</p>
                    <p class="mb-2 small"><strong>AI Provider:</strong> ${aiProviderName}</p>
                    <a href="${BASE_URL}telegram_signals/view/${data.data.signal_id}" target="_blank" class="btn btn-sm btn-primary">
                        <i class="fas fa-eye me-1"></i>View Signal #${data.data.signal_id}
                    </a>
                </div>
            `;
        } else {
            document.getElementById('webhook-results').innerHTML = `
                <div class="alert alert-danger mt-3">
                    <strong><i class="fas fa-times me-1"></i>Failed</strong>
                    <p class="mb-0 small">${data.message}</p>
                </div>
            `;
        }
    })
    .catch(e => alert('Error: ' + e.message))
    .finally(() => {
        btn.innerHTML = '<i class="fas fa-paper-plane me-1"></i>Send Webhook';
        btn.disabled = false;
    });
}

// Test EA
function testEA() {
    const userId = document.getElementById('test_user').value;
    const ticker = document.getElementById('test_ticker').value;

    if (!userId) {
        alert('Select user');
        return;
    }

    const formData = new FormData();
    formData.append('user_id', userId);
    formData.append('ticker', ticker || '');

    fetch(`${BASE_URL}debug/telegram/test`, {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        const cls = data.success ? 'success' : 'danger';
        const icon = data.success ? 'check' : 'times';
        document.getElementById('ea-results').innerHTML = `
            <div class="alert alert-${cls} mt-2 p-2">
                <small><i class="fas fa-${icon} me-1"></i>${data.message}</small>
            </div>
        `;
    })
    .catch(e => alert('Error: ' + e.message));
}
</script>
