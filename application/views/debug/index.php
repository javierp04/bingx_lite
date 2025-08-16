<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">
        <i class="fas fa-bug me-2"></i>Debug Panel
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
                <p class="text-muted mb-3">
                    Test webhook signals for both platforms. Use the templates below to generate proper JSON format,
                    then send to either MetaTrader or BingX for testing.
                </p>

                <div class="mb-3">
                    <label for="signal_data" class="form-label">Signal JSON Data</label>
                    <textarea class="form-control" id="signal_data" name="signal_data" rows="15" placeholder="Paste or generate your webhook JSON here..." required></textarea>
                    <div class="form-text">
                        This should be the exact JSON that TradingView would send to the webhook endpoint.
                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div class="btn-group">
                        <button type="button" class="btn btn-outline-success" onclick="loadTemplate('long')">
                            <i class="fas fa-arrow-up me-1"></i>Long Template
                        </button>
                        <button type="button" class="btn btn-outline-danger" onclick="loadTemplate('short')">
                            <i class="fas fa-arrow-down me-1"></i>Short Template
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
                            <option value="production">Production</option>
                            <option value="sandbox">Sandbox</option>
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
                <div class="mb-3">
                    <label for="template_platform" class="form-label">Platform</label>
                    <select class="form-select form-select-sm" id="template_platform" onchange="updatePlatformFields()">
                        <option value="bingx">BingX</option>
                        <option value="metatrader">MetaTrader</option>
                    </select>
                </div>

                <!-- Environment field - only for BingX -->
                <div class="mb-3" id="environment_field">
                    <label for="template_environment" class="form-label">Environment</label>
                    <select class="form-select form-select-sm" id="template_environment">
                        <option value="production">Production</option>
                        <option value="sandbox">Sandbox</option>
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
                            <option value="<?= $strategy->strategy_id ?>" data-platform="<?= $strategy->platform ?>" data-type="<?= $strategy->type ?>">
                                <?= $strategy->name ?> (<?= ucfirst($strategy->platform) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label for="template_operation" class="form-label">Tipo de Operación</label>
                    <select class="form-select form-select-sm" id="template_operation">
                        <option value="ABRIR">ABRIR</option>
                        <option value="CERRAR">CERRAR</option>
                    </select>
                </div>

                <div class="mb-3">
                    <label for="template_symbol" class="form-label">Symbol</label>
                    <input type="text" class="form-control form-control-sm" id="template_symbol" value="BTCUSDT">
                </div>

                <div class="mb-3">
                    <label for="template_quantity" class="form-label">Quantity</label>
                    <input type="number" class="form-control form-control-sm" id="template_quantity" value="0.001" step="0.0001">
                </div>



                <div class="mb-3">
                    <label for="template_leverage" class="form-label">Leverage</label>
                    <select class="form-select form-select-sm" id="template_leverage">
                        <option value="1">1x</option>
                        <option value="5">5x</option>
                        <option value="10" selected>10x</option>
                        <option value="20">20x</option>
                    </select>
                </div>

                <div class="mb-3">
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

<!-- Success/Error Messages Display -->
<div id="alert_container" class="mt-3"></div>

<script>
    // Signal templates for different platforms
    const signalTemplates = {
        bingx: {
            long: {
                user_id: null,
                strategy_id: null,
                ticker: null,
                timeframe: null,
                action: null, // Will be set based on operation type
                quantity: null,
                leverage: null,
                environment: null,
                position_id: null
            },
            short: {
                user_id: null,
                strategy_id: null,
                ticker: null,
                timeframe: null,
                action: null, // Will be set based on operation type
                quantity: null,
                leverage: null,
                environment: null,
                position_id: null
            }
        },
        metatrader: {
            long: {
                user_id: null,
                strategy_id: null,
                ticker: null,
                timeframe: null,
                action: null, // Will be set based on operation type
                quantity: null,
                position_id: null
            },
            short: {
                user_id: null,
                strategy_id: null,
                ticker: null,
                timeframe: null,
                action: null, // Will be set based on operation type
                quantity: null,
                position_id: null
            }
        }
    };

    function updatePlatformFields() {
        const platform = document.getElementById('template_platform').value;        
        const environmentField = document.getElementById('environment_field');
        const symbolInput = document.getElementById('template_symbol');

        if (platform === 'metatrader') {            
            environmentField.style.display = 'none'; // Hide environment for MT
            symbolInput.value = 'EURUSD';
            symbolInput.placeholder = 'e.g., EURUSD, GBPUSD';
        } else {            
            environmentField.style.display = 'block'; // Show environment for BingX
            symbolInput.value = 'BTCUSDT';
            symbolInput.placeholder = 'e.g., BTCUSDT';
        }
    }

    function loadTemplate(direction) {
        const platform = document.getElementById('template_platform').value;
        const operation = document.getElementById('template_operation').value;
        const template = {
            ...signalTemplates[platform][direction]
        };

        // Fill template with current form values
        template.user_id = parseInt(document.getElementById('template_user').value);
        template.strategy_id = document.getElementById('template_strategy').value;
        template.ticker = document.getElementById('template_symbol').value;
        template.quantity = parseFloat(document.getElementById('template_quantity').value);

        // Set action based on direction and operation type
        if (direction === 'long') {
            template.action = operation === 'ABRIR' ? 'buy' : 'sell';
        } else { // short
            template.action = operation === 'ABRIR' ? 'short' : 'cover';
        }

        template.timeframe = document.getElementById('template_timeframe').value;
        template.leverage = parseInt(document.getElementById('template_leverage').value);
        template.environment = document.getElementById('template_environment').value;


        // Generate random position ID
        template.position_id = Math.floor(100000 + Math.random() * 900000).toString();

        // Load into textarea
        document.getElementById('signal_data').value = JSON.stringify(template, null, 2);

        // Validate automatically
        validateJson();
    }

    function testSignal(platform) {
        const signalData = document.getElementById('signal_data').value;

        if (!signalData.trim()) {
            showAlert('Please enter signal data before testing.', 'warning');
            return;
        }

        // Validate JSON first
        try {
            JSON.parse(signalData);
        } catch (e) {
            showAlert('Invalid JSON format. Please check your signal data.', 'danger');
            return;
        }

        const url = platform === 'metatrader' ?
            '<?= base_url('debug/test_mt_signal') ?>' :
            '<?= base_url('debug/test_bingx_signal') ?>';

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
                    showAlert(`${platform.toUpperCase()} signal sent successfully!`, 'success');
                } else {
                    showAlert(`${platform.toUpperCase()} signal failed: ${data.message}`, 'danger');
                }
            })
            .catch(error => {
                showAlert(`Error testing ${platform} signal: ${error.message}`, 'danger');
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

    // BingX API Testing Functions
    function testSpotBalance() {
        showApiTest('Testing Spot Balance...');
        window.location.href = '<?= base_url('debug/test_spot_balance') ?>';
    }

    function testFuturesBalance() {
        showApiTest('Testing Futures Balance...');
        window.location.href = '<?= base_url('debug/test_futures_balance') ?>';
    }

    function testSpotPrice() {
        const symbol = document.getElementById('test_symbol').value;
        showApiTest('Testing Spot Price...');
        window.location.href = `<?= base_url('debug/test_spot_price') ?>?symbol=${encodeURIComponent(symbol)}`;
    }

    function testFuturesPrice() {
        const symbol = document.getElementById('test_symbol').value;
        showApiTest('Testing Futures Price...');
        window.location.href = `<?= base_url('debug/test_futures_price') ?>?symbol=${encodeURIComponent(symbol)}`;
    }

    function showApiTest(message) {
        const status = document.getElementById('connection_status');
        status.innerHTML = `<span class="badge bg-warning">${message}</span>`;
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

    function showAlert(message, type) {
        const container = document.getElementById('alert_container');
        const alertHtml = `
        <div class="alert alert-${type} alert-dismissible fade show" role="alert">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
        container.innerHTML = alertHtml;

        // Auto dismiss after 5 seconds
        setTimeout(() => {
            const alert = container.querySelector('.alert');
            if (alert) {
                alert.remove();
            }
        }, 5000);
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