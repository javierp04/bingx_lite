<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">
        <i class="fas fa-paper-plane me-2"></i>Telegram Debug Panel
    </h1>
    <div class="btn-group">
        <a href="<?= base_url('telegram_signals') ?>" class="btn btn-info">
            <i class="fas fa-list me-1"></i>View Signals
        </a>
        <a href="<?= base_url('my_trading') ?>" class="btn btn-outline-primary">
            <i class="fas fa-chart-line me-1"></i>My Trading
        </a>
    </div>
</div>

<div class="row">
    <!-- Main Content: Telegram Simulators -->
    <div class="col-md-8">
        <!-- Tabs Navigation -->
        <ul class="nav nav-tabs mb-3" id="debugTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="webhook-tab" data-bs-toggle="tab" data-bs-target="#webhook-panel" type="button">
                    <i class="fas fa-globe me-1"></i>Full Webhook
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="json-tab" data-bs-toggle="tab" data-bs-target="#json-panel" type="button">
                    <i class="fas fa-magic me-1"></i>JSON Generator
                </button>
            </li>
        </ul>

        <!-- Tab Content -->
        <div class="tab-content" id="debugTabContent">

            <!-- WEBHOOK SIMULATOR TAB -->
            <div class="tab-pane fade show active" id="webhook-panel" role="tabpanel">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-globe me-1"></i>Full Telegram Webhook Simulator
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info mb-3">
                            <i class="fas fa-info-circle me-2"></i>
                            Simulates complete flow: parse → download → crop → AI (<?= $this->config->item('ai_provider') ?: 'openai' ?>) → signal
                        </div>

                        <form id="webhookSimulatorForm">
                            <div class="input-group mb-3">
                                <span class="input-group-text"><i class="fas fa-comment-dots"></i></span>
                                <input type="text" class="form-control" id="telegram_message" name="telegram_message"
                                       placeholder="Sentimiento #ES https://www.tradingview.com/x/abc123" required>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-paper-plane me-1"></i>Simulate Webhook
                                </button>
                            </div>
                        </form>

                        <!-- Webhook Results -->
                        <div id="webhook-results" style="display: none;">
                            <hr>
                            <div id="webhook-content"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- JSON GENERATOR TAB -->
            <div class="tab-pane fade" id="json-panel" role="tabpanel">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-magic me-1"></i>Manual JSON Generator
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info mb-3">
                            <i class="fas fa-info-circle me-2"></i>
                            Generate signal JSON manually, bypassing AI analysis
                        </div>

                        <form id="telegramSignalForm">
                            <div class="row mb-2">
                                <div class="col-md-4">
                                    <label for="ticker" class="form-label">Ticker <span class="text-danger">*</span></label>
                                    <select class="form-select form-select-sm" id="ticker" name="ticker" required>
                                        <option value="">Select Ticker</option>
                                        <?php foreach ($available_tickers as $ticker): ?>
                                            <option value="<?= $ticker->symbol ?>">
                                                <?= $ticker->symbol ?> - <?= $ticker->name ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label for="op_type" class="form-label">Type <span class="text-danger">*</span></label>
                                    <select class="form-select form-select-sm" id="op_type" name="op_type" required>
                                        <option value="">Select</option>
                                        <option value="LONG">LONG</option>
                                        <option value="SHORT">SHORT</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label for="entry_price" class="form-label">Entry <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control form-control-sm" id="entry_price" name="entry_price"
                                           step="0.00001" placeholder="1.08750" required>
                                </div>
                            </div>

                            <div class="row mb-2">
                                <div class="col-md-6">
                                    <label for="stop_loss_1" class="form-label">SL1</label>
                                    <input type="number" class="form-control form-control-sm" id="stop_loss_1" name="stop_loss_1" step="0.00001">
                                </div>
                                <div class="col-md-6">
                                    <label for="stop_loss_2" class="form-label">SL2</label>
                                    <input type="number" class="form-control form-control-sm" id="stop_loss_2" name="stop_loss_2" step="0.00001">
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col">
                                    <label class="form-label">Take Profits</label>
                                    <div class="row g-1">
                                        <div class="col">
                                            <input type="number" class="form-control form-control-sm" id="tp1" name="tp1" step="0.00001" placeholder="TP1">
                                        </div>
                                        <div class="col">
                                            <input type="number" class="form-control form-control-sm" id="tp2" name="tp2" step="0.00001" placeholder="TP2">
                                        </div>
                                        <div class="col">
                                            <input type="number" class="form-control form-control-sm" id="tp3" name="tp3" step="0.00001" placeholder="TP3">
                                        </div>
                                        <div class="col">
                                            <input type="number" class="form-control form-control-sm" id="tp4" name="tp4" step="0.00001" placeholder="TP4">
                                        </div>
                                        <div class="col">
                                            <input type="number" class="form-control form-control-sm" id="tp5" name="tp5" step="0.00001" placeholder="TP5">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="d-flex justify-content-between">
                                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="loadSampleData()">
                                    <i class="fas fa-file-import me-1"></i>Load Sample
                                </button>
                                <button type="submit" class="btn btn-success btn-sm">
                                    <i class="fas fa-magic me-1"></i>Generate Signal
                                </button>
                            </div>
                        </form>

                        <!-- JSON Results -->
                        <div id="generation-results" style="display: none;">
                            <hr>
                            <div id="generation-content"></div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <!-- Sidebar: EA Simulation & Info -->
    <div class="col-md-4">

        <!-- EA Simulation -->
        <div class="card mb-3">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-robot me-1"></i>EA Simulation
                </h6>
            </div>
            <div class="card-body">
                <p class="text-muted small mb-2">Test if EA_Signals can consume the signal</p>

                <div class="row mb-2">
                    <div class="col-6">
                        <label for="test_user" class="form-label small">User</label>
                        <select class="form-select form-select-sm" id="test_user">
                            <?php foreach ($users as $user): ?>
                                <option value="<?= $user->id ?>"><?= $user->username ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-6">
                        <label for="test_ticker" class="form-label small">Ticker</label>
                        <select class="form-select form-select-sm" id="test_ticker">
                            <option value="">Auto</option>
                        </select>
                    </div>
                </div>

                <button type="button" class="btn btn-primary btn-sm w-100" onclick="testEAPolling()">
                    <i class="fas fa-play me-1"></i>Simulate EA Poll
                </button>

                <div id="ea-test-results" class="mt-2" style="display: none;">
                    <div id="ea-test-content"></div>
                </div>
            </div>
        </div>

        <!-- Process Flow -->
        <div class="card mb-3">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-info-circle me-1"></i>Process Flow
                </h6>
            </div>
            <div class="card-body p-2">
                <div class="timeline-compact">
                    <div class="timeline-item-compact">
                        <span class="badge bg-primary">1</span>
                        <small>Parse Message</small>
                    </div>
                    <div class="timeline-item-compact">
                        <span class="badge bg-success">2</span>
                        <small>Download Image</small>
                    </div>
                    <div class="timeline-item-compact">
                        <span class="badge bg-info">3</span>
                        <small>Crop & Analyze AI</small>
                    </div>
                    <div class="timeline-item-compact">
                        <span class="badge bg-warning">4</span>
                        <small>Create Signal</small>
                    </div>
                    <div class="timeline-item-compact">
                        <span class="badge bg-secondary">5</span>
                        <small>EA Consumes</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Links -->
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-link me-1"></i>Quick Links
                </h6>
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
                    <a href="<?= base_url('systemlogs?action=telegram_') ?>" class="btn btn-outline-info btn-sm">
                        <i class="fas fa-file-alt me-1"></i>Logs
                    </a>
                </div>
            </div>
        </div>

    </div>
</div>

<script>
const base_url = '<?= base_url() ?>';

document.addEventListener('DOMContentLoaded', function() {
    // Webhook simulator
    document.getElementById('webhookSimulatorForm').addEventListener('submit', function(e) {
        e.preventDefault();
        simulateTelegramWebhook();
    });

    // JSON generator
    document.getElementById('telegramSignalForm').addEventListener('submit', function(e) {
        e.preventDefault();
        generateTelegramSignal();
    });

    // Update test ticker when main ticker changes
    document.getElementById('ticker').addEventListener('change', function() {
        const testTickerSelect = document.getElementById('test_ticker');
        testTickerSelect.innerHTML = this.value ?
            `<option value="${this.value}">${this.value}</option>` :
            '<option value="">Auto</option>';
    });
});

function simulateTelegramWebhook() {
    const message = document.getElementById('telegram_message').value.trim();
    const btn = document.querySelector('#webhookSimulatorForm button[type="submit"]');

    if (!message) {
        showAlert('Please enter a message', 'danger');
        return;
    }

    const originalHtml = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Processing...';
    btn.disabled = true;

    const formData = new FormData();
    formData.append('message', message);

    fetch(`${base_url}debug/telegram/simulate`, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showWebhookResults(data);
            showAlert('Webhook simulated successfully!', 'success');
        } else {
            showAlert('Failed: ' + data.message, 'danger');
            if (data.data?.error_details) {
                showWebhookResults(data);
            }
        }
    })
    .catch(error => showAlert('Error: ' + error.message, 'danger'))
    .finally(() => {
        btn.innerHTML = originalHtml;
        btn.disabled = false;
    });
}

function showWebhookResults(data) {
    const resultsDiv = document.getElementById('webhook-results');
    const contentDiv = document.getElementById('webhook-content');

    let html = '';

    // DEBUG MODE - Show raw response
    if (data.success && data.data?.debug_mode) {
        html = `
            <div class="alert alert-warning">
                <strong><i class="fas fa-bug me-1"></i>DEBUG MODE</strong>
                <p class="mb-0 small">${data.message}</p>
            </div>

            <div class="row mb-2">
                <div class="col-4"><small><strong>HTTP Code:</strong> ${data.data.http_code}</small></div>
                <div class="col-4"><small><strong>Length:</strong> ${data.data.response_length} bytes</small></div>
                <div class="col-4"><small><strong>Valid JSON:</strong> ${data.data.is_valid_json ? '✅' : '❌'}</small></div>
            </div>

            <div class="accordion accordion-flush" id="debugAccordion">
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button py-2" type="button" data-bs-toggle="collapse" data-bs-target="#rawResponseCollapse">
                            <small><i class="fas fa-file-code me-1"></i>Full Raw Response</small>
                        </button>
                    </h2>
                    <div id="rawResponseCollapse" class="accordion-collapse collapse show" data-bs-parent="#debugAccordion">
                        <div class="accordion-body p-2">
                            <pre class="bg-dark text-light p-2 rounded mb-0 small" style="max-height: 400px; overflow-y: auto;">${escapeHtml(data.data.raw_response)}</pre>
                        </div>
                    </div>
                </div>
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed py-2" type="button" data-bs-toggle="collapse" data-bs-target="#firstCharsCollapse">
                            <small><i class="fas fa-arrow-down me-1"></i>First 200 Characters</small>
                        </button>
                    </h2>
                    <div id="firstCharsCollapse" class="accordion-collapse collapse" data-bs-parent="#debugAccordion">
                        <div class="accordion-body p-2">
                            <pre class="bg-light p-2 rounded mb-0 small">${escapeHtml(data.data.first_200_chars)}</pre>
                        </div>
                    </div>
                </div>
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed py-2" type="button" data-bs-toggle="collapse" data-bs-target="#lastCharsCollapse">
                            <small><i class="fas fa-arrow-up me-1"></i>Last 200 Characters</small>
                        </button>
                    </h2>
                    <div id="lastCharsCollapse" class="accordion-collapse collapse" data-bs-parent="#debugAccordion">
                        <div class="accordion-body p-2">
                            <pre class="bg-light p-2 rounded mb-0 small">${escapeHtml(data.data.last_200_chars)}</pre>
                        </div>
                    </div>
                </div>
            </div>
        `;
    } else if (data.success) {
        // Normal success mode
        html = `
            <div class="alert alert-success">
                <strong><i class="fas fa-check-circle me-1"></i>Pipeline Completed!</strong>
                <p class="mb-0 small">${data.message}</p>
            </div>

            <div class="row mb-2">
                <div class="col-4"><small><strong>Signal:</strong> ${data.data.signal_id}</small></div>
                <div class="col-4"><small><strong>Ticker:</strong> ${data.data.ticker}</small></div>
                <div class="col-4"><small><strong>Users:</strong> ${data.data.users_distributed || 0}</small></div>
            </div>

            <a href="${base_url}telegram_signals/view/${data.data.signal_id}" target="_blank" class="btn btn-sm btn-primary mb-2">
                <i class="fas fa-eye me-1"></i>View Signal
            </a>

            ${data.data.analysis_data ? `
                <div class="accordion accordion-flush" id="webhookAccordion">
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed py-2" type="button" data-bs-toggle="collapse" data-bs-target="#analysisCollapse">
                                <small><i class="fas fa-brain me-1"></i>AI Analysis (${data.data.ai_provider})</small>
                            </button>
                        </h2>
                        <div id="analysisCollapse" class="accordion-collapse collapse" data-bs-parent="#webhookAccordion">
                            <div class="accordion-body p-2">
                                <pre class="bg-light p-2 rounded mb-0 small">${JSON.stringify(data.data.analysis_data, null, 2)}</pre>
                            </div>
                        </div>
                    </div>
                </div>
            ` : ''}
        `;
    } else {
        // Error mode
        html = `
            <div class="alert alert-danger">
                <strong><i class="fas fa-exclamation-triangle me-1"></i>Failed</strong>
                <p class="mb-0 small">${data.message}</p>
                ${data.data?.error_details ? `<hr><small>${data.data.error_details}</small>` : ''}
            </div>
        `;
    }

    contentDiv.innerHTML = html;
    resultsDiv.style.display = 'block';
}

function escapeHtml(text) {
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, m => map[m]);
}

function generateTelegramSignal() {
    const form = document.getElementById('telegramSignalForm');
    const formData = new FormData(form);
    const btn = form.querySelector('button[type="submit"]');

    if (!formData.get('ticker') || !formData.get('op_type') || !formData.get('entry_price')) {
        showAlert('Fill required fields', 'danger');
        return;
    }

    const originalHtml = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Generating...';
    btn.disabled = true;

    fetch(`${base_url}debug/telegram/generate`, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showGenerationResults(data);
            showAlert('Signal generated!', 'success');
            form.reset();
        } else {
            showAlert('Failed: ' + data.message, 'danger');
        }
    })
    .catch(error => showAlert('Error: ' + error.message, 'danger'))
    .finally(() => {
        btn.innerHTML = originalHtml;
        btn.disabled = false;
    });
}

function showGenerationResults(data) {
    const resultsDiv = document.getElementById('generation-results');
    const contentDiv = document.getElementById('generation-content');

    const html = `
        <div class="alert alert-success">
            <strong><i class="fas fa-check-circle me-1"></i>Generated!</strong>
            <p class="mb-0 small">${data.message}</p>
        </div>

        <div class="row mb-2">
            <div class="col-6"><small><strong>Signal ID:</strong> ${data.data.telegram_signal_id}</small></div>
            <div class="col-6"><small><strong>Users:</strong> ${data.data.users_affected}</small></div>
        </div>

        <a href="${data.data.view_url}" target="_blank" class="btn btn-sm btn-primary">
            <i class="fas fa-eye me-1"></i>View Signal
        </a>
    `;

    contentDiv.innerHTML = html;
    resultsDiv.style.display = 'block';
}

function testEAPolling() {
    const userId = document.getElementById('test_user').value;
    const ticker = document.getElementById('test_ticker').value;

    if (!userId) {
        showAlert('Select user', 'warning');
        return;
    }

    const formData = new FormData();
    formData.append('user_id', userId);
    formData.append('ticker', ticker || '');

    fetch(`${base_url}debug/telegram/test`, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        const resultsDiv = document.getElementById('ea-test-results');
        const contentDiv = document.getElementById('ea-test-content');

        if (data.success) {
            contentDiv.innerHTML = `
                <div class="alert alert-success alert-sm p-2 mb-0 mt-2">
                    <small><strong>Success!</strong><br>${data.message}</small>
                </div>
            `;
        } else {
            contentDiv.innerHTML = `
                <div class="alert alert-danger alert-sm p-2 mb-0 mt-2">
                    <small><strong>Failed!</strong><br>${data.message}</small>
                </div>
            `;
        }

        resultsDiv.style.display = 'block';
    })
    .catch(error => showAlert('Error: ' + error.message, 'danger'));
}

function loadSampleData() {
    document.getElementById('ticker').value = 'EURUSD';
    document.getElementById('op_type').value = 'LONG';
    document.getElementById('entry_price').value = '1.08750';
    document.getElementById('stop_loss_1').value = '1.08500';
    document.getElementById('stop_loss_2').value = '1.08250';
    document.getElementById('tp1').value = '1.09000';
    document.getElementById('tp2').value = '1.09250';
    document.getElementById('tp3').value = '1.09500';
    document.getElementById('tp4').value = '1.09750';
    document.getElementById('tp5').value = '1.10000';

    showAlert('Sample loaded', 'info');
}

function showAlert(message, type) {
    const existingAlert = document.querySelector('.temp-alert');
    if (existingAlert) existingAlert.remove();

    const alert = document.createElement('div');
    alert.className = `alert alert-${type} alert-sm temp-alert mt-2`;
    alert.innerHTML = `<small>${message}</small>`;

    const activeTab = document.querySelector('.tab-pane.active .card-body');
    if (activeTab) {
        activeTab.insertBefore(alert, activeTab.firstChild);
    }

    setTimeout(() => alert.remove(), 3000);
}
</script>

<style>
.timeline-compact {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.timeline-item-compact {
    display: flex;
    align-items: center;
    gap: 8px;
}

.timeline-item-compact .badge {
    width: 24px;
    height: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.75rem;
}

.alert-sm {
    padding: 0.5rem;
    font-size: 0.875rem;
}

.accordion-button {
    font-size: 0.875rem;
}
</style>
