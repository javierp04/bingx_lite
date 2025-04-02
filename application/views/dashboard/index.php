<!-- application/views/dashboard/index.php -->
<h1 class="h3 mb-4">
    <i class="fas fa-tachometer-alt me-2"></i>Trading Dashboard
</h1>

<?php if ($is_admin): ?>
<!-- Admin Simulation Panel -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="fas fa-tools me-1"></i>Order Simulation Panel
        </h5>
    </div>
    <div class="card-body">
        <!-- API Connection Tests -->
        <div class="mb-4">
            <h6 class="mb-3">API Connection Tests</h6>
            <div class="row g-2">
                <div class="col-md-auto">
                    <a href="<?= base_url('dashboard/test_spot_balance') ?>" class="btn btn-outline-primary">
                        <i class="fas fa-wallet me-1"></i>Test Spot Balance
                    </a>
                </div>
                <div class="col-md-auto">
                    <a href="<?= base_url('dashboard/test_futures_balance') ?>" class="btn btn-outline-primary">
                        <i class="fas fa-chart-line me-1"></i>Test Futures Balance
                    </a>
                </div>
                
                <!-- Price Testing Controls -->
                <div class="col-md-12 mt-3">
                    <label class="form-label">Test Symbol Price</label>
                    <div class="d-flex gap-2">
                        <div class="input-group" style="max-width: 300px;">
                            <span class="input-group-text">Symbol</span>
                            <input type="text" class="form-control" placeholder="BTCUSDT" id="test-symbol" value="BTCUSDT">
                        </div>
                        <a href="javascript:void(0);" class="btn btn-primary" id="test-spot-price-btn">
                            <i class="fas fa-coins me-1"></i>Test Spot Price
                        </a>
                        <a href="javascript:void(0);" class="btn btn-danger" id="test-futures-price-btn">
                            <i class="fas fa-chart-line me-1"></i>Test Futures Price
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <hr class="my-3">
        
        <!-- Order Simulation Form -->
        <h6 class="mb-3">Send Order</h6>
        <?= form_open('webhook/simulate') ?>
            <input type="hidden" name="simulate_data" id="simulate_data" value="">
            <div class="row g-3">
                <div class="col-md-6">
                    <label for="sim_strategy_id" class="form-label">Strategy</label>
                    <select class="form-select" id="sim_strategy_id" required>
                        <?php foreach ($all_strategies as $strategy): ?>
                            <option value="<?= $strategy->strategy_id ?>"><?= $strategy->name ?> (<?= $strategy->strategy_id ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label for="sim_ticker" class="form-label">Ticker</label>
                    <input type="text" class="form-control" id="sim_ticker" placeholder="e.g., BTCUSDT" required>
                </div>
                <div class="col-md-3">
                    <label for="sim_action" class="form-label">Action</label>
                    <select class="form-select" id="sim_action" required>
                        <option value="BUY">BUY</option>
                        <option value="SELL">SELL</option>
                        <option value="CLOSE">CLOSE</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="sim_timeframe" class="form-label">Timeframe</label>
                    <select class="form-select" id="sim_timeframe" required>
                        <option value="1m">1m</option>
                        <option value="5m">5m</option>
                        <option value="15m">15m</option>
                        <option value="30m">30m</option>
                        <option value="1h" selected>1h</option>
                        <option value="4h">4h</option>
                        <option value="1d">1d</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="sim_quantity" class="form-label">Quantity</label>
                    <input type="number" class="form-control" id="sim_quantity" step="0.0001" min="0.0001" value="0.01" required>
                </div>
                <div class="col-md-3">
                    <label for="sim_leverage" class="form-label">Leverage</label>
                    <select class="form-select" id="sim_leverage">
                        <option value="1">1x</option>
                        <option value="2">2x</option>
                        <option value="3">3x</option>
                        <option value="5">5x</option>
                        <option value="10">10x</option>
                        <option value="20">20x</option>
                        <option value="50">50x</option>
                        <option value="100">100x</option>
                    </select>
                </div>
                <div class="col-12 mt-3">
                    <button type="button" class="btn btn-primary" id="simulate-order-btn">
                        <i class="fas fa-play me-1"></i>Simulate Order
                    </button>
                </div>
            </div>
        <?= form_close() ?>
    </div>
</div>

<script>
    document.getElementById('simulate-order-btn').addEventListener('click', function() {
        // Get form values
        const formData = {
            // No se incluye user_id, el controlador usará el usuario actual
            strategy_id: document.getElementById('sim_strategy_id').value,
            ticker: document.getElementById('sim_ticker').value,
            timeframe: document.getElementById('sim_timeframe').value,
            action: document.getElementById('sim_action').value,
            quantity: document.getElementById('sim_quantity').value,
            leverage: document.getElementById('sim_leverage').value
        };
        
        // Set the JSON data to the hidden field
        document.getElementById('simulate_data').value = JSON.stringify(formData);
        
        // Submit the form
        document.getElementById('simulate_data').form.submit();
    });
    
    // Spot Price Test Button
    document.getElementById('test-spot-price-btn').addEventListener('click', function() {
        // Get symbol value
        const symbol = document.getElementById('test-symbol').value;
        
        // Navigate to test spot price endpoint
        window.location.href = '<?= base_url('dashboard/test_spot_price') ?>?symbol=' + encodeURIComponent(symbol);
    });
    
    // Futures Price Test Button
    document.getElementById('test-futures-price-btn').addEventListener('click', function() {
        // Get symbol value
        const symbol = document.getElementById('test-symbol').value;
        
        // Navigate to test futures price endpoint
        window.location.href = '<?= base_url('dashboard/test_futures_price') ?>?symbol=' + encodeURIComponent(symbol);
    });
    
    // Make Enter key work in price test input
    document.getElementById('test-symbol').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            document.getElementById('test-spot-price-btn').click();
        }
    });
</script>
<?php endif; ?>

<!-- Summary Dashboard -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">Active Trades</h5>
                <h2 class="mb-0"><?= count($open_trades) ?></h2>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">Strategies</h5>
                <h2 class="mb-0"><?= count($strategies) ?></h2>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">Total PNL</h5>
                <?php
                    $total_pnl = 0;
                    foreach ($open_trades as $trade) {
                        $total_pnl += isset($trade->pnl) ? $trade->pnl : 0;
                    }
                    $pnl_class = $total_pnl >= 0 ? 'text-profit' : 'text-loss';
                ?>
                <h2 class="mb-0 <?= $pnl_class ?>" id="total-pnl"><?= number_format($total_pnl, 2) ?> USDT</h2>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">Last Updated</h5>
                <h2 class="mb-0" id="last-updated">Now</h2>
            </div>
        </div>
    </div>
</div>

<!-- Active Trades Table -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Active Trades</h5>
        <button class="btn btn-sm btn-outline-primary" id="refresh-trades-btn">
            <i class="fas fa-sync-alt me-1"></i>Refresh
        </button>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-striped table-hover mb-0">
                <thead>
                    <tr>
                        <th>Symbol</th>
                        <th>Strategy</th>
                        <th>Side</th>
                        <th>Type</th>
                        <th>Entry Price</th>
                        <th>Current Price</th>
                        <th>Quantity</th>
                        <th>Leverage</th>
                        <th>Current PNL</th>
                        <th>Opened</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="trades-tbody">
                    <?php if (empty($open_trades)): ?>
                        <tr>
                            <td colspan="11" class="text-center py-3">No active trades</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($open_trades as $trade): ?>
                            <?php 
                                $pnl_class = isset($trade->pnl) && $trade->pnl >= 0 ? 'text-profit' : 'text-loss';
                                $side_class = $trade->side == 'BUY' ? 'text-success' : 'text-danger';
                                $type_class = $trade->trade_type == 'futures' ? 'bg-warning text-dark' : 'bg-info';
                            ?>
                            <tr>
                                <td><?= $trade->symbol ?></td>
                                <td><?= $trade->strategy_name ?></td>
                                <td class="<?= $side_class ?>"><?= $trade->side ?></td>
                                <td>
                                    <span class="badge <?= $type_class ?>">
                                        <?= ucfirst($trade->trade_type) ?>
                                    </span>
                                </td>
                                <td><?= $trade->entry_price ?></td>
                                <td class="current-price"><?= isset($trade->current_price) ? $trade->current_price : $trade->entry_price ?></td>
                                <td><?= $trade->quantity ?></td>
                                <td><?= $trade->leverage ?>x</td>
                                <td class="<?= $pnl_class ?>">
                                    <?= isset($trade->pnl) ? number_format($trade->pnl, 2) . ' USDT' : 'N/A' ?>
                                </td>
                                <td><?= date('Y-m-d H:i', strtotime($trade->created_at)) ?></td>
                                <td>
                                    <a href="<?= base_url('trades/close/' . $trade->id) ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to close this trade?')">
                                        <i class="fas fa-times-circle"></i> Close
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

<!-- Webhook URL Information Card (Collapsible) -->
<div class="card mt-4">
    <div class="card-header" role="button" data-bs-toggle="collapse" data-bs-target="#webhookInfo" aria-expanded="false" aria-controls="webhookInfo">
        <div class="d-flex justify-content-between align-items-center">
            <h5 class="mb-0">
                <i class="fas fa-webhook me-1"></i>TradingView Webhook Information
            </h5>
            <i class="fas fa-chevron-down"></i>
        </div>
    </div>
    <div class="collapse" id="webhookInfo">
        <div class="card-body">
            <p>Use the following webhook URL in your TradingView alerts:</p>
            <div class="input-group mb-3">
                <input type="text" class="form-control" value="<?= base_url('webhook/tradingview') ?>" id="webhook-url" readonly>
                <button class="btn btn-outline-secondary" type="button" onclick="copyWebhookUrl()">
                    <i class="fas fa-copy"></i> Copy
                </button>
            </div>
            <p>Required JSON format for TradingView webhook:</p>
            <pre class="bg-light p-3 rounded"><code>{
  "user_id": <?= $this->session->userdata('user_id') ?>,
  "strategy_id": "YOUR_STRATEGY_ID",
  "ticker": "BTCUSDT",
  "timeframe": "1h",
  "action": "BUY", // BUY, SELL, or CLOSE
  "quantity": 0.01,
  "leverage": 5 // Only used for futures
}</code></pre>
        </div>
    </div>
</div>

<!-- JavaScript for real-time updates -->
<script>
    $(document).ready(function() {
        // Toggle chevron icon for webhook info collapse
        $('#webhookInfo').on('show.bs.collapse', function () {
            $('.card-header .fa-chevron-down').removeClass('fa-chevron-down').addClass('fa-chevron-up');
        });
        
        $('#webhookInfo').on('hide.bs.collapse', function () {
            $('.card-header .fa-chevron-up').removeClass('fa-chevron-up').addClass('fa-chevron-down');
        });
        
        // Auto-refresh trades every 5 seconds
        let refreshTimer;
        
        function startRefresh() {
            refreshTimer = setInterval(refreshTrades, 5000);
        }
        
        function stopRefresh() {
            clearInterval(refreshTimer);
        }
        
        // Start auto-refresh
        startRefresh();
        
        // Manual refresh button
        $('#refresh-trades-btn').click(refreshTrades);
        
        // Refresh function
        function refreshTrades() {
            $.ajax({
                url: '<?= base_url('dashboard/refresh_trades') ?>',
                dataType: 'json',
                success: function(data) {
                    updateTradesTable(data);
                    $('#last-updated').text(getCurrentTime());
                    
                    // Calculate total PNL
                    const totalPnl = calculateTotalPnl(data);
                    const pnlClass = totalPnl >= 0 ? 'text-profit' : 'text-loss';
                    
                    // Update the PNL display
                    $('#total-pnl')
                        .removeClass('text-profit text-loss')
                        .addClass(pnlClass)
                        .text(totalPnl.toFixed(2) + ' USDT');
                }
            });
        }
        
        function updateTradesTable(trades) {
            const tbody = $('#trades-tbody');
            tbody.empty();
            
            if (trades.length === 0) {
                tbody.append('<tr><td colspan="11" class="text-center py-3">No active trades</td></tr>');
                return;
            }
            
            trades.forEach(function(trade) {
                const pnlClass = (trade.pnl >= 0) ? 'text-profit' : 'text-loss';
                const sideClass = (trade.side === 'BUY') ? 'text-success' : 'text-danger';
                const typeClass = (trade.trade_type === 'futures') ? 'bg-warning text-dark' : 'bg-info';
                const formattedPnl = (trade.pnl !== null) ? Number(trade.pnl).toFixed(2) + ' USDT' : 'N/A';
                const formattedDate = new Date(trade.created_at).toLocaleString();
                const currentPrice = trade.current_price || trade.entry_price;
                
                const row = `<tr>
                    <td>${trade.symbol}</td>
                    <td>${trade.strategy_name}</td>
                    <td class="${sideClass}">${trade.side}</td>
                    <td>
                        <span class="badge ${typeClass}">
                            ${trade.trade_type.charAt(0).toUpperCase() + trade.trade_type.slice(1)}
                        </span>
                    </td>
                    <td>${trade.entry_price}</td>
                    <td class="current-price">${currentPrice}</td>
                    <td>${trade.quantity}</td>
                    <td>${trade.leverage}x</td>
                    <td class="${pnlClass}">${formattedPnl}</td>
                    <td>${formattedDate}</td>
                    <td>
                        <a href="<?= base_url('trades/close/') ?>${trade.id}" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to close this trade?')">
                            <i class="fas fa-times-circle"></i> Close
                        </a>
                    </td>
                </tr>`;
                
                tbody.append(row);
            });
        }
        
        function calculateTotalPnl(trades) {
            let totalPnl = 0;
            trades.forEach(function(trade) {
                totalPnl += parseFloat(trade.pnl || 0);
            });
            return totalPnl;
        }
        
        function getCurrentTime() {
            const now = new Date();
            const hours = now.getHours().toString().padStart(2, '0');
            const minutes = now.getMinutes().toString().padStart(2, '0');
            const seconds = now.getSeconds().toString().padStart(2, '0');
            return `${hours}:${minutes}:${seconds}`;
        }
    });
    
    // Copy webhook URL function
    function copyWebhookUrl() {
        const webhookUrl = document.getElementById('webhook-url');
        webhookUrl.select();
        document.execCommand('copy');
        
        // Show tooltip
        const button = document.querySelector('#webhook-url + button');
        const originalText = button.innerHTML;
        button.innerHTML = '<i class="fas fa-check"></i> Copied!';
        
        setTimeout(function() {
            button.innerHTML = originalText;
        }, 2000);
    }
</script>