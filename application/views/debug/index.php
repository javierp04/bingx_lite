<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">
        <i class="fas fa-bug me-2"></i>Debug Panel - MT Circuit Updated
    </h1>
    <div class="btn-group">
        <a href="<?= base_url('dashboard') ?>" class="btn btn-secondary">
            <i class="fas fa-tachometer-alt me-1"></i>Dashboard
        </a>
        <a href="<?= base_url('signals') ?>" class="btn btn-info">
            <i class="fas fa-signal me-1"></i>Signals
        </a>
    </div>
</div>

<div class="row">
    <!-- Signal Testing Section -->
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-vial me-1"></i>Signal Testing
                </h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label for="signal_data" class="form-label">Signal JSON Data</label>
                    <textarea class="form-control" id="signal_data" name="signal_data" rows="15" placeholder="Paste or generate your webhook JSON here..." required></textarea>
                    <div class="form-text">
                        Position IDs now use simplified format: "buy|123456", "sell|789012", etc.
                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <button type="button" class="btn btn-outline-primary" onclick="loadTemplate()">
                            <i class="fas fa-file-code me-1"></i>Load Template
                        </button>
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-2">
                    <button type="button" class="btn btn-dark" onclick="testSignal('metatrader')">
                        <i class="fas fa-chart-area me-1"></i>Test MetaTrader
                    </button>
                    <button type="button" class="btn btn-info" onclick="testSignal('bingx')">
                        <i class="fas fa-bitcoin me-1"></i>Test BingX
                    </button>
                </div>
            </div>
        </div>

        <!-- EA Testing Section -->
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-robot me-1"></i>EA Execution Testing
                </h5>
            </div>
            <div class="card-body">
                <p class="text-muted mb-3">
                    Test the EA confirmation flow. First send a MT signal above, then use these tools to simulate EA responses.
                </p>

                <div class="row">
                    <div class="col-md-6">
                        <label for="test_position_id" class="form-label">Position ID</label>
                        <input type="text" class="form-control" id="test_position_id" placeholder="e.g., buy|123456">
                    </div>
                    <div class="col-md-3">
                        <label for="test_execution_price" class="form-label">Execution Price</label>
                        <input type="number" class="form-control" id="test_execution_price" step="0.00001" placeholder="1.08923">
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <div class="btn-group w-100">
                            <button class="btn btn-success" onclick="confirmExecution('success')">
                                <i class="fas fa-check me-1"></i>Success
                            </button>
                            <button class="btn btn-danger" onclick="confirmExecution('failed')">
                                <i class="fas fa-times me-1"></i>Failed
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- BingX API Testing Section -->
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-link me-1"></i>BingX API Testing
                </h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <!-- Environment Selector -->
                    <div class="col-md-3">
                        <label for="api_environment" class="form-label">Environment</label>
                        <select class="form-select" id="api_environment">
                            <option value="sandbox">Sandbox</option>
                            <option value="production">Production</option>
                        </select>
                    </div>

                    <!-- Test Symbol Input -->
                    <div class="col-md-4">
                        <label for="test_symbol" class="form-label">Test Symbol</label>
                        <input type="text" class="form-control" id="test_symbol" value="BTCUSDT" placeholder="e.g., BTCUSDT">
                    </div>

                    <!-- Connection Status -->
                    <div class="col-md-5">
                        <label class="form-label">Connection Status</label>
                        <div id="connection_status" class="p-2 border rounded bg-light">
                            <span class="badge bg-secondary">Not tested</span>
                        </div>
                    </div>
                </div>

                <hr class="my-3">

                <!-- API Test Buttons -->
                <div class="row g-2">
                    <div class="col-md-3">
                        <button class="btn btn-outline-primary w-100" onclick="testSpotBalance()">
                            <i class="fas fa-wallet me-1"></i>Spot Balance
                        </button>
                    </div>
                    <div class="col-md-3">
                        <button class="btn btn-outline-primary w-100" onclick="testFuturesBalance()">
                            <i class="fas fa-chart-line me-1"></i>Futures Balance
                        </button>
                    </div>
                    <div class="col-md-3">
                        <button class="btn btn-outline-primary w-100" onclick="testSpotPrice()">
                            <i class="fas fa-coins me-1"></i>Spot Price
                        </button>
                    </div>
                    <div class="col-md-3">
                        <button class="btn btn-outline-primary w-100" onclick="testFuturesPrice()">
                            <i class="fas fa-chart-line me-1"></i>Futures Price
                        </button>
                    </div>
                </div>

                <!-- Test Results - Unified -->
                <div id="test_results" class="mt-3" style="display: none;">
                    <h6><i class="fas fa-clipboard-list me-1"></i>Test Results:</h6>
                    <div id="test_content" class="border rounded p-3 bg-light"></div>
                </div>
                <!-- API Response Display -->
                <div id="api_response" class="mt-3" style="display: none;">
                    <h6>API Response:</h6>
                    <pre class="bg-light p-3 rounded"><code id="api_response_content"></code></pre>
                </div>
            </div>
        </div>
    </div>

    <!-- Tools and Configuration -->
    <div class="col-md-4">
        <!-- Template Generator -->
        <div class="card mb-3">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-cogs me-1"></i>Template Generator
                </h6>
            </div>
            <div class="card-body">
                <!-- Platform y Environment -->
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="template_platform" class="form-label">Platform</label>
                        <select class="form-select form-select-sm" id="template_platform" onchange="updatePlatformFields()">
                            <option value="bingx">BingX</option>
                            <option value="metatrader">MetaTrader</option>
                        </select>
                    </div>
                    <div class="col-md-6" id="environment_field">
                        <label for="template_environment" class="form-label">Environment</label>
                        <select class="form-select form-select-sm" id="template_environment">
                            <option value="sandbox">Sandbox</option>
                            <option value="production">Production</option>
                        </select>
                    </div>
                </div>

                <!-- User y Timeframe -->
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="template_user" class="form-label">User</label>
                        <select class="form-select form-select-sm" id="template_user">
                            <?php foreach ($users as $user): ?>
                                <option value="<?= $user->id ?>" <?= $user->id == $this->session->userdata('user_id') ? 'selected' : '' ?>>
                                    <?= $user->username ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="template_timeframe" class="form-label">Timeframe</label>
                        <select class="form-select form-select-sm" id="template_timeframe">
                            <option value="1">1 Minute</option>
                            <option value="5">5 Minutes</option>
                            <option value="15">15 Minutes</option>
                            <option value="60" selected>1 Hour</option>
                            <option value="240">4 Hours</option>
                        </select>
                    </div>
                </div>

                <!-- Strategy (solo) -->
                <div class="mb-3">
                    <label for="template_strategy" class="form-label">Strategy</label>
                    <select class="form-select form-select-sm" id="template_strategy">
                        <?php foreach ($strategies as $strategy): ?>
                            <option value="<?= $strategy->strategy_id ?>" data-platform="<?= $strategy->platform ?>" data-type="<?= $strategy->type ?>">
                                <?= $strategy->name ?> (<?= ucfirst($strategy->platform) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Operation Type y Symbol -->
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="template_operation" class="form-label">Operation Type</label>
                        <select class="form-select form-select-sm" id="template_operation">
                            <option value="buy">BUY</option>
                            <option value="sell">SELL</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="template_symbol" class="form-label">Symbol</label>
                        <input type="text" class="form-control form-control-sm" id="template_symbol" value="BTCUSDT">
                    </div>
                </div>

                <!-- Quantity y Leverage -->
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="template_quantity" class="form-label">Quantity</label>
                        <input type="number" class="form-control form-control-sm" id="template_quantity" value="0.001" step="0.0001">
                    </div>
                    <div class="col-md-6">
                        <label for="template_leverage" class="form-label">Leverage</label>
                        <select class="form-select form-select-sm" id="template_leverage">
                            <option value="1">1x</option>
                            <option value="5">5x</option>
                            <option value="10" selected>10x</option>
                            <option value="20">20x</option>
                        </select>
                    </div>
                </div>

                <!-- Stop Loss y Take Profit -->
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="template_stop_loss" class="form-label">Stop Loss</label>
                        <input type="number" class="form-control form-control-sm" id="template_stop_loss" step="0.00001" placeholder="Optional">
                    </div>
                    <div class="col-md-6">
                        <label for="template_take_profit" class="form-label">Take Profit</label>
                        <input type="number" class="form-control form-control-sm" id="template_take_profit" step="0.00001" placeholder="Optional">
                    </div>
                </div>
            </div>
        </div>

        <!-- API Endpoints Reference -->
        <div class="card mb-3">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-link me-1"></i>API Endpoints
                </h6>
            </div>
            <div class="card-body">
                <div class="mb-2">
                    <strong>BingX Webhook:</strong>
                    <div class="input-group input-group-sm">
                        <input type="text" class="form-control" value="<?= base_url('webhook/tradingview') ?>" readonly>
                        <button class="btn btn-outline-secondary" onclick="copyToClipboard(this.previousElementSibling)">
                            <i class="fas fa-copy"></i>
                        </button>
                    </div>
                </div>

                <div class="mb-2">
                    <strong>MetaTrader Webhook:</strong>
                    <div class="input-group input-group-sm">
                        <input type="text" class="form-control" value="<?= base_url('metatrader/webhook') ?>" readonly>
                        <button class="btn btn-outline-secondary" onclick="copyToClipboard(this.previousElementSibling)">
                            <i class="fas fa-copy"></i>
                        </button>
                    </div>
                </div>

                <div class="mb-2">
                    <strong>MT Pending Signals:</strong>
                    <div class="input-group input-group-sm">
                        <input type="text" class="form-control" value="<?= base_url('api/mt/pending_signals') ?>" readonly>
                        <button class="btn btn-outline-secondary" onclick="copyToClipboard(this.previousElementSibling)">
                            <i class="fas fa-copy"></i>
                        </button>
                    </div>
                </div>

                <div class="mb-2">
                    <strong>MT Confirm Execution:</strong>
                    <div class="input-group input-group-sm">
                        <input type="text" class="form-control" value="<?= base_url('api/mt/confirm_execution') ?>" readonly>
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
                <button type="button" class="btn btn-outline-primary btn-sm w-100 mb-2" onclick="validateJson()">
                    <i class="fas fa-check me-1"></i>Validate JSON
                </button>
                <div id="validation_result"></div>
            </div>
        </div>
    </div>
</div>

<script>
    function updatePlatformFields() {
        const platform = document.getElementById('template_platform').value;
        const environmentField = document.getElementById('environment_field');
        const symbolInput = document.getElementById('template_symbol');
        const strategySelect = document.getElementById('template_strategy');

        // Update symbol based on platform
        if (platform === 'metatrader') {
            environmentField.style.display = 'none';
            symbolInput.value = 'EURUSD';
            symbolInput.placeholder = 'e.g., EURUSD, GBPUSD';
        } else {
            environmentField.style.display = 'block';
            symbolInput.value = 'BTCUSDT';
            symbolInput.placeholder = 'e.g., BTCUSDT';
        }

        // Filter strategies by platform
        const options = strategySelect.options;
        let firstVisible = null;

        for (let i = 0; i < options.length; i++) {
            const option = options[i];
            if (option.dataset.platform === platform) {
                option.style.display = '';
                if (!firstVisible) firstVisible = option;
            } else {
                option.style.display = 'none';
            }
        }

        // Select first visible strategy or clear selection
        strategySelect.value = firstVisible ? firstVisible.value : '';
    }

    function loadTemplate() {
        // Validate strategy selection
        const strategyValue = document.getElementById('template_strategy').value;
        if (!strategyValue) {
            showTestResult(false, 'Please select a strategy for the selected platform');
            return;
        }

        const action = document.getElementById('template_operation').value;
        const randomId = Math.floor(100000 + Math.random() * 900000);

        // Build signal object directly from form inputs
        const signal = {
            user_id: parseInt(document.getElementById('template_user').value),
            strategy_id: strategyValue,
            ticker: document.getElementById('template_symbol').value,
            timeframe: document.getElementById('template_timeframe').value,
            action: action,
            quantity: parseFloat(document.getElementById('template_quantity').value),
            leverage: parseInt(document.getElementById('template_leverage').value),
            environment: document.getElementById('template_environment').value,
            position_id: `${action}|${randomId}`
        };

        // Add stop loss if provided
        const stopLossValue = document.getElementById('template_stop_loss').value;
        if (stopLossValue && stopLossValue.trim() !== '') {
            signal.stop_loss = parseFloat(stopLossValue);
        }

        // Add take profit if provided
        const takeProfitValue = document.getElementById('template_take_profit').value;
        if (takeProfitValue && takeProfitValue.trim() !== '') {
            signal.take_profit = parseFloat(takeProfitValue);
        }

        // Load into textarea and validate
        document.getElementById('signal_data').value = JSON.stringify(signal, null, 2);
        validateJson();
    }

    function testSignal(platform) {
        const signalData = document.getElementById('signal_data').value;

        if (!signalData.trim()) {
            showTestResult(false, 'Please enter signal data before testing.');
            return;
        }

        // Validate JSON first
        try {
            JSON.parse(signalData);
        } catch (e) {
            showTestResult(false, 'Invalid JSON format. Please check your signal data.');
            return;
        }

        const url = platform === 'metatrader' ?
            '<?= base_url('debug/test_mt_signal') ?>' :
            '<?= base_url('debug/test_bingx_signal') ?>';

        showTestInProgress(`Testing ${platform.toUpperCase()} signal...`);

        fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: signalData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    let successMsg = `${platform.toUpperCase()} signal sent successfully!`;
                    if (platform === 'metatrader') {
                        successMsg += ' Signal queued for EA processing. Use EA testing below to confirm execution.';
                    }
                    showTestResult(true, successMsg);
                } else {
                    showTestResult(false, `${platform.toUpperCase()} signal failed: ${data.message}`);
                }
            })
            .catch(error => {
                showTestResult(false, `Error testing ${platform} signal: ${error.message}`);
            });
    }

    function confirmExecution(status) {
        const positionId = document.getElementById('test_position_id').value;
        const executionPrice = document.getElementById('test_execution_price').value;

        if (!positionId) {
            showTestResult(false, 'Please enter a position ID');
            return;
        }

        const data = {
            position_id: positionId,
            status: status
        };

        if (status === 'success' && executionPrice) {
            data.execution_price = parseFloat(executionPrice);
        }

        if (status === 'failed') {
            data.error_message = 'Simulated failure from debug panel';
        }

        showTestInProgress(`Confirming execution as ${status.toUpperCase()}...`);

        fetch('<?= base_url('debug/test_confirm_execution') ?>', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: new URLSearchParams(data)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showTestResult(true, `Execution confirmed as ${status.toUpperCase()}! Check trades table for results.`);
                } else {
                    showTestResult(false, `Confirmation failed: ${data.message}`);
                }
            })
            .catch(error => {
                showTestResult(false, `Error confirming execution: ${error.message}`);
            });
    }

    // BingX API Testing Functions - AJAX Version
    function testSpotBalance() {
        showTestInProgress('Testing Spot Balance...');

        fetch('<?= base_url('debug/test_spot_balance') ?>', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                }
            })
            .then(response => response.json())
            .then(data => {
                showTestResult(data.success, data.message, data.data);
            })
            .catch(error => {
                showTestResult(false, 'Error: ' + error.message);
            });
    }

    function testFuturesBalance() {
        showTestInProgress('Testing Futures Balance...');

        fetch('<?= base_url('debug/test_futures_balance') ?>', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                }
            })
            .then(response => response.json())
            .then(data => {
                showTestResult(data.success, data.message, data.data);
            })
            .catch(error => {
                showTestResult(false, 'Error: ' + error.message);
            });
    }

    function testSpotPrice() {
        const symbol = document.getElementById('test_symbol').value;
        showTestInProgress('Testing Spot Price...');

        fetch('<?= base_url('debug/test_spot_price') ?>', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: `symbol=${encodeURIComponent(symbol)}`
            })
            .then(response => response.json())
            .then(data => {
                showTestResult(data.success, data.message, data.data);
            })
            .catch(error => {
                showTestResult(false, 'Error: ' + error.message);
            });
    }

    function testFuturesPrice() {
        const symbol = document.getElementById('test_symbol').value;
        showTestInProgress('Testing Futures Price...');

        fetch('<?= base_url('debug/test_futures_price') ?>', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: `symbol=${encodeURIComponent(symbol)}`
            })
            .then(response => response.json())
            .then(data => {
                showTestResult(data.success, data.message, data.data);
            })
            .catch(error => {
                showTestResult(false, 'Error: ' + error.message);
            });
    }

    function showTestInProgress(message) {
        const status = document.getElementById('connection_status');
        status.innerHTML = `<span class="badge bg-warning"><i class="fas fa-spinner fa-spin me-1"></i>${message}</span>`;

        // Hide previous results
        document.getElementById('test_results').style.display = 'none';
    }

    function showTestResult(success, message, data = null) {
        const status = document.getElementById('connection_status');
        const resultsDiv = document.getElementById('test_results');
        const contentDiv = document.getElementById('test_content');

        // Update status
        if (success) {
            status.innerHTML = '<span class="badge bg-success"><i class="fas fa-check me-1"></i>Test Completed</span>';
        } else {
            status.innerHTML = '<span class="badge bg-danger"><i class="fas fa-times me-1"></i>Test Failed</span>';
        }

        // Show results with timestamp
        const timestamp = new Date().toLocaleTimeString();
        let resultHtml = `
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h6 class="mb-0">Latest Test Result</h6>
                <small class="text-muted">${timestamp}</small>
            </div>
            <div class="alert alert-${success ? 'success' : 'danger'} mb-2">
                <strong><i class="fas fa-${success ? 'check-circle' : 'exclamation-triangle'} me-1"></i>${success ? 'Success' : 'Error'}:</strong> ${message}
            </div>`;

        if (data) {
            resultHtml += data;
        }

        contentDiv.innerHTML = resultHtml;
        resultsDiv.style.display = 'block';

        // Scroll to results
        resultsDiv.scrollIntoView({
            behavior: 'smooth',
            block: 'nearest'
        });
    }

    function validateJson() {
        const textarea = document.getElementById('signal_data');
        const resultDiv = document.getElementById('validation_result');

        try {
            const parsed = JSON.parse(textarea.value);

            // Check required fields
            const requiredFields = ['user_id', 'strategy_id', 'ticker', 'timeframe', 'action'];
            const missing = requiredFields.filter(field => !parsed[field]);

            if (missing.length > 0) {
                resultDiv.innerHTML = `
                <div class="alert alert-warning alert-sm p-2">
                    <i class="fas fa-exclamation-triangle me-1"></i>
                    Missing: ${missing.join(', ')}
                </div>
            `;
            } else {
                resultDiv.innerHTML = `
                <div class="alert alert-success alert-sm p-2">
                    <i class="fas fa-check-circle me-1"></i>
                    Valid JSON
                </div>
            `;
            }

            textarea.classList.remove('is-invalid');

        } catch (e) {
            resultDiv.innerHTML = `
            <div class="alert alert-danger alert-sm p-2">
                <i class="fas fa-times-circle me-1"></i>
                Invalid JSON
            </div>
        `;
            textarea.classList.add('is-invalid');
        }
    }

    function copyToClipboard(element) {
        element.select();
        document.execCommand('copy');

        const button = element.nextElementSibling;
        const originalHTML = button.innerHTML;
        button.innerHTML = '<i class="fas fa-check"></i>';
        button.classList.add('btn-success');

        setTimeout(() => {
            button.innerHTML = originalHTML;
            button.classList.remove('btn-success');
        }, 1500);
    }

    // Legacy function for backwards compatibility (remove showAlert calls)
    function showAlert(message, type) {
        // Convert to new unified system
        showTestResult(type === 'success', message);
    }

    // Initialize page
    document.addEventListener('DOMContentLoaded', function() {
        updatePlatformFields();

        // Auto-validate as user types
        const textarea = document.getElementById('signal_data');
        let timeout;

        textarea.addEventListener('input', function() {
            clearTimeout(timeout);
            timeout = setTimeout(validateJson, 500);
        });

        // Load default template
        loadTemplate();
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