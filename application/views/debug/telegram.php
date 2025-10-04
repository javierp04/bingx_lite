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
    <!-- Telegram Signal Generator -->
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-magic me-1"></i>Telegram Signal Generator
                </h5>
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>Purpose:</strong> Simulate the complete Telegram signal flow by generating the final JSON 
                    that EA_Signals expects, bypassing image analysis and AI processing.
                </div>

                <form id="telegramSignalForm">
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="ticker" class="form-label">Ticker <span class="text-danger">*</span></label>
                            <select class="form-select" id="ticker" name="ticker" required>
                                <option value="">Select Ticker</option>
                                <?php foreach ($available_tickers as $ticker): ?>
                                    <option value="<?= $ticker->symbol ?>">
                                        <?= $ticker->symbol ?> - <?= $ticker->name ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="op_type" class="form-label">Operation Type <span class="text-danger">*</span></label>
                            <select class="form-select" id="op_type" name="op_type" required>
                                <option value="">Select Type</option>
                                <option value="LONG">LONG (Buy)</option>
                                <option value="SHORT">SHORT (Sell)</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="volume" class="form-label">Base Volume</label>
                            <input type="number" class="form-control" id="volume" name="volume" 
                                   value="1.0" step="0.1" min="0.1" placeholder="1.0">
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="entry_price" class="form-label">Entry Price <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="entry_price" name="entry_price" 
                                   step="0.00001" placeholder="e.g. 1.08750" required>
                        </div>
                        <div class="col-md-4">
                            <label for="stop_loss_1" class="form-label">Stop Loss 1</label>
                            <input type="number" class="form-control" id="stop_loss_1" name="stop_loss_1" 
                                   step="0.00001" placeholder="Optional">
                        </div>
                        <div class="col-md-4">
                            <label for="stop_loss_2" class="form-label">Stop Loss 2</label>
                            <input type="number" class="form-control" id="stop_loss_2" name="stop_loss_2" 
                                   step="0.00001" placeholder="Optional">
                        </div>
                    </div>

                    <div class="row mb-4">
                        <div class="col">
                            <label class="form-label">Take Profits (at least one recommended)</label>
                            <div class="row">
                                <div class="col">
                                    <input type="number" class="form-control" id="tp1" name="tp1" 
                                           step="0.00001" placeholder="TP1">
                                </div>
                                <div class="col">
                                    <input type="number" class="form-control" id="tp2" name="tp2" 
                                           step="0.00001" placeholder="TP2">
                                </div>
                                <div class="col">
                                    <input type="number" class="form-control" id="tp3" name="tp3" 
                                           step="0.00001" placeholder="TP3">
                                </div>
                                <div class="col">
                                    <input type="number" class="form-control" id="tp4" name="tp4" 
                                           step="0.00001" placeholder="TP4">
                                </div>
                                <div class="col">
                                    <input type="number" class="form-control" id="tp5" name="tp5" 
                                           step="0.00001" placeholder="TP5">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center">
                        <button type="button" class="btn btn-outline-secondary" onclick="loadSampleData()">
                            <i class="fas fa-file-import me-1"></i>Load Sample
                        </button>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-magic me-1"></i>Generate Telegram Signal
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Generation Results -->
        <div id="generation-results" class="card mt-4" style="display: none;">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-check-circle me-1"></i>Generation Results
                </h5>
            </div>
            <div class="card-body">
                <div id="generation-content"></div>
            </div>
        </div>
    </div>

    <!-- EA Simulation & Info -->
    <div class="col-md-4">
        <!-- EA Simulation -->
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-robot me-1"></i>EA_Signals Simulation
                </h6>
            </div>
            <div class="card-body">
                <p class="text-muted mb-3">
                    Test if the generated signal can be consumed by EA_Signals.
                </p>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="test_user" class="form-label">User</label>
                        <select class="form-select form-select-sm" id="test_user">
                            <?php foreach ($users as $user): ?>
                                <option value="<?= $user->id ?>">
                                    <?= $user->username ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="test_ticker" class="form-label">Ticker</label>
                        <select class="form-select form-select-sm" id="test_ticker">
                            <option value="">Select ticker first</option>
                        </select>
                    </div>
                </div>

                <button type="button" class="btn btn-primary btn-sm w-100" onclick="testEAPolling()">
                    <i class="fas fa-play me-1"></i>Simulate EA Polling
                </button>
                
                <div id="ea-test-results" class="mt-3" style="display: none;">
                    <div id="ea-test-content"></div>
                </div>
            </div>
        </div>

        <!-- Quick Info -->
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-info-circle me-1"></i>Process Flow
                </h6>
            </div>
            <div class="card-body">
                <div class="timeline">
                    <div class="timeline-item">
                        <div class="timeline-marker bg-primary"></div>
                        <div class="timeline-content">
                            <h6>1. Generate JSON</h6>
                            <small>Manual input instead of AI analysis</small>
                        </div>
                    </div>
                    
                    <div class="timeline-item">
                        <div class="timeline-marker bg-success"></div>
                        <div class="timeline-content">
                            <h6>2. Create Signal</h6>
                            <small>Insert into telegram_signals table</small>
                        </div>
                    </div>

                    <div class="timeline-item">
                        <div class="timeline-marker bg-info"></div>
                        <div class="timeline-content">
                            <h6>3. Distribute</h6>
                            <small>Create user_telegram_signals records</small>
                        </div>
                    </div>

                    <div class="timeline-item">
                        <div class="timeline-marker bg-warning"></div>
                        <div class="timeline-content">
                            <h6>4. EA Polling</h6>
                            <small>EA_Signals consumes signal</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Links -->
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-link me-1"></i>Quick Links
                </h6>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <a href="<?= base_url('telegram_signals') ?>" class="btn btn-outline-primary btn-sm">
                        <i class="fas fa-list me-1"></i>View All Telegram Signals
                    </a>
                    <a href="<?= base_url('my_trading') ?>" class="btn btn-outline-success btn-sm">
                        <i class="fas fa-chart-line me-1"></i>My Trading Dashboard
                    </a>
                    <a href="<?= base_url('available_tickers') ?>" class="btn btn-outline-secondary btn-sm">
                        <i class="fas fa-tags me-1"></i>Manage Tickers
                    </a>
                    <a href="<?= base_url('systemlogs?action=telegram_') ?>" class="btn btn-outline-info btn-sm">
                        <i class="fas fa-file-alt me-1"></i>Telegram Logs
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const base_url = '<?= base_url() ?>';

document.addEventListener('DOMContentLoaded', function() {
    // Form submission
    document.getElementById('telegramSignalForm').addEventListener('submit', function(e) {
        e.preventDefault();
        generateTelegramSignal();
    });

    // Update test ticker dropdown when main ticker changes
    document.getElementById('ticker').addEventListener('change', function() {
        const testTickerSelect = document.getElementById('test_ticker');
        testTickerSelect.innerHTML = '<option value="">Select ticker first</option>';
        
        if (this.value) {
            testTickerSelect.innerHTML = `<option value="${this.value}">${this.value}</option>`;
            testTickerSelect.value = this.value;
        }
    });
});

function generateTelegramSignal() {
    const form = document.getElementById('telegramSignalForm');
    const formData = new FormData(form);
    const submitBtn = form.querySelector('button[type="submit"]');
    
    // Validate required fields
    if (!formData.get('ticker') || !formData.get('op_type') || !formData.get('entry_price')) {
        showAlert('Please fill in all required fields', 'danger');
        return;
    }

    // Update button state
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Generating...';
    submitBtn.disabled = true;

    fetch(`${base_url}debug/telegram/generate`, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showGenerationResults(data);
            showAlert('Telegram signal generated successfully!', 'success');
            
            // Reset form
            form.reset();
        } else {
            showAlert('Failed to generate signal: ' + data.message, 'danger');
        }
    })
    .catch(error => {
        showAlert('Error: ' + error.message, 'danger');
    })
    .finally(() => {
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
    });
}

function testEAPolling() {
    const userId = document.getElementById('test_user').value;
    const ticker = document.getElementById('test_ticker').value;
    
    if (!userId || !ticker) {
        showAlert('Please select user and ticker for EA simulation', 'warning');
        return;
    }

    const formData = new FormData();
    formData.append('user_id', userId);
    formData.append('ticker', ticker);

    fetch(`${base_url}debug/telegram/test`, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        showEATestResults(data);
    })
    .catch(error => {
        showAlert('Error testing EA polling: ' + error.message, 'danger');
    });
}

function loadSampleData() {
    // Load sample EURUSD LONG signal
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
    document.getElementById('volume').value = '1.0';
    
    showAlert('Sample EURUSD LONG signal loaded', 'info');
}

function showGenerationResults(data) {
    const resultsDiv = document.getElementById('generation-results');
    const contentDiv = document.getElementById('generation-content');
    
    let html = `
        <div class="alert alert-success">
            <h6><i class="fas fa-check-circle me-1"></i>Signal Generated Successfully!</h6>
            <p class="mb-2">${data.message}</p>
        </div>
        
        <div class="row mb-3">
            <div class="col-md-6">
                <strong>Telegram Signal ID:</strong> ${data.data.telegram_signal_id}
            </div>
            <div class="col-md-6">
                <strong>Users Affected:</strong> ${data.data.users_affected}
            </div>
        </div>
        
        <div class="mb-3">
            <a href="${data.data.view_url}" target="_blank" class="btn btn-primary btn-sm">
                <i class="fas fa-eye me-1"></i>View Generated Signal
            </a>
        </div>
        
        <div class="accordion" id="analysisAccordion">
            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#analysisCollapse">
                        <i class="fas fa-code me-2"></i>Generated Analysis JSON
                    </button>
                </h2>
                <div id="analysisCollapse" class="accordion-collapse collapse" data-bs-parent="#analysisAccordion">
                    <div class="accordion-body">
                        <pre class="bg-light p-3 rounded">${JSON.stringify(data.data.analysis_data, null, 2)}</pre>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    contentDiv.innerHTML = html;
    resultsDiv.style.display = 'block';
    
    // Scroll to results
    resultsDiv.scrollIntoView({ behavior: 'smooth' });
}

function showEATestResults(data) {
    const resultsDiv = document.getElementById('ea-test-results');
    const contentDiv = document.getElementById('ea-test-content');
    
    if (data.success) {
        contentDiv.innerHTML = `
            <div class="alert alert-success alert-sm">
                <strong>EA Simulation Success!</strong><br>
                ${data.message}
            </div>
            <div class="mt-2">
                <small><strong>User Signal ID:</strong> ${data.data.user_signal_id}</small><br>
                <small><strong>Status:</strong> ${data.data.status}</small>
            </div>
        `;
    } else {
        contentDiv.innerHTML = `
            <div class="alert alert-danger alert-sm">
                <strong>EA Simulation Failed!</strong><br>
                ${data.message}
            </div>
        `;
    }
    
    resultsDiv.style.display = 'block';
}

function showAlert(message, type) {
    // Remove existing temp alerts
    const existingAlert = document.querySelector('.temp-alert');
    if (existingAlert) existingAlert.remove();
    
    // Create alert element with proper container structure
    const alert = document.createElement('div');
    alert.className = `alert alert-${type} temp-alert`;
    alert.innerHTML = `
        <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'danger' ? 'exclamation-triangle' : 'info-circle'} me-2"></i>
        ${message}
    `;
    
    // Insert after the existing info alert (before the form)
    const infoAlert = document.querySelector('.alert-info');
    if (infoAlert) {
        infoAlert.parentNode.insertBefore(alert, infoAlert.nextSibling);
    } else {
        // Fallback: insert at the beginning of card-body
        const cardBody = document.querySelector('.card-body');
        cardBody.insertBefore(alert, cardBody.firstChild);
    }
    
    // Auto remove after 4 seconds
    setTimeout(() => {
        if (alert.parentNode) alert.remove();
    }, 4000);
}
</script>

<style>
.timeline {
    position: relative;
    padding-left: 30px;
}

.timeline-item {
    position: relative;
    margin-bottom: 20px;
}

.timeline-item:before {
    content: '';
    position: absolute;
    left: -22px;
    top: 0;
    bottom: -20px;
    width: 2px;
    background: #dee2e6;
}

.timeline-item:last-child:before {
    display: none;
}

.timeline-marker {
    position: absolute;
    left: -28px;
    top: 5px;
    width: 12px;
    height: 12px;
    border-radius: 50%;
    border: 2px solid #fff;
    box-shadow: 0 0 0 2px #dee2e6;
}

.timeline-content h6 {
    margin-bottom: 0;
    font-size: 0.9rem;
}

.timeline-content small {
    color: #6c757d;
}

.alert-sm {
    padding: 0.5rem 0.75rem;
    font-size: 0.875rem;
}

.accordion-button {
    font-size: 0.9rem;
    padding: 0.75rem 1rem;
}
</style>