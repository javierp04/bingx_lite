<!-- application/views/dashboard/index.php -->
<h1 class="h3 mb-4">
    <i class="fas fa-tachometer-alt me-2"></i>Trading Dashboard
</h1>

<?php if ($is_admin): ?>
<!-- Admin Simulation Panel -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="fas fa-tools me-1"></i>Order Simulation Panel (Admin)
        </h5>
    </div>
    <div class="card-body">
        <?= form_open('dashboard/simulate_order', ['class' => 'row g-3']) ?>
            <div class="col-md-3">
                <label for="user_id" class="form-label">User</label>
                <select class="form-select" id="user_id" name="user_id" required>
                    <?php foreach ($all_users as $user): ?>
                        <option value="<?= $user->id ?>"><?= $user->username ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label for="strategy_id" class="form-label">Strategy</label>
                <select class="form-select" id="strategy_id" name="strategy_id" required>
                    <?php foreach ($all_strategies as $strategy): ?>
                        <option value="<?= $strategy->strategy_id ?>"><?= $strategy->name ?> (<?= $strategy->strategy_id ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label for="ticker" class="form-label">Ticker</label>
                <input type="text" class="form-control" id="ticker" name="ticker" placeholder="e.g., BTCUSDT" required>
            </div>
            <div class="col-md-3">
                <label for="timeframe" class="form-label">Timeframe</label>
                <select class="form-select" id="timeframe" name="timeframe" required>
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
                <label for="action" class="form-label">Action</label>
                <select class="form-select" id="action" name="action" required>
                    <option value="BUY">BUY</option>
                    <option value="SELL">SELL</option>
                    <option value="CLOSE">CLOSE</option>
                </select>
            </div>
            <div class="col-md-3">
                <label for="quantity" class="form-label">Quantity</label>
                <input type="number" class="form-control" id="quantity" name="quantity" step="0.0001" min="0.0001" value="0.01" required>
            </div>
            <div class="col-md-3">
                <label for="leverage" class="form-label">Leverage</label>
                <select class="form-select" id="leverage" name="leverage">
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
            <div class="col-md-3">
                <label for="environment" class="form-label">Environment</label>
                <select class="form-select" id="environment" name="environment" required>
                    <option value="sandbox" selected>Sandbox</option>
                    <option value="production">Production</option>
                </select>
            </div>
            <div class="col-12 mt-3">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-play me-1"></i>Simulate Order
                </button>
            </div>
        <?= form_close() ?>
    </div>
</div>
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
                        <th>Environment</th>
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
                                $env_class = $trade->environment == 'production' ? 'bg-danger' : 'bg-secondary';
                            ?>
                            <tr>
                                <td><?= $trade->symbol ?></td>
                                <td><?= $trade->strategy_name ?></td>
                                <td class="<?= $side_class ?>"><?= $trade->side ?></td>
                                <td>
                                    <span class="badge <?= $env_class ?>">
                                        <?= ucfirst($trade->environment) ?>
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
  "leverage": 5, // Only used for futures
  "environment": "sandbox" // sandbox or production
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
                const envClass = (trade.environment === 'production') ? 'bg-danger' : 'bg-secondary';
                const formattedPnl = (trade.pnl !== null) ? Number(trade.pnl).toFixed(2) + ' USDT' : 'N/A';
                const formattedDate = new Date(trade.created_at).toLocaleString();
                const currentPrice = trade.current_price || trade.entry_price;
                
                const row = `<tr>
                    <td>${trade.symbol}</td>
                    <td>${trade.strategy_name}</td>
                    <td class="${sideClass}">${trade.side}</td>
                    <td>
                        <span class="badge ${envClass}">
                            ${trade.environment.charAt(0).toUpperCase() + trade.environment.slice(1)}
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