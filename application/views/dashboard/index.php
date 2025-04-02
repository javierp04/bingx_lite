<h1 class="h3 mb-4">
    <i class="fas fa-tachometer-alt me-2"></i>Trading Dashboard
</h1>

<!-- Environment Selection Tabs -->
<ul class="nav nav-tabs mb-4" id="environmentTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active" id="sandbox-tab" data-bs-toggle="tab" data-bs-target="#sandbox" type="button" role="tab">
            <i class="fas fa-flask me-1"></i>Sandbox
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="production-tab" data-bs-toggle="tab" data-bs-target="#production" type="button" role="tab">
            <i class="fas fa-rocket me-1"></i>Production
        </button>
    </li>
</ul>

<!-- Tab Content -->
<div class="tab-content" id="environmentTabsContent">
    <!-- Sandbox Tab -->
    <div class="tab-pane fade show active" id="sandbox" role="tabpanel">
        <?php if (empty($sandbox_api)): ?>
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle me-2"></i>No API keys configured for Sandbox environment.
                <a href="<?= base_url('apikeys/add') ?>" class="alert-link">Configure API Keys</a>
            </div>
        <?php else: ?>
            <!-- Sandbox Summary -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">Active Trades</h5>
                            <h2 class="mb-0"><?= count($sandbox_trades) ?></h2>
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
                                foreach ($sandbox_trades as $trade) {
                                    $total_pnl += isset($trade->pnl) ? $trade->pnl : 0;
                                }
                                $pnl_class = $total_pnl >= 0 ? 'text-profit' : 'text-loss';
                            ?>
                            <h2 class="mb-0 <?= $pnl_class ?>"><?= number_format($total_pnl, 2) ?> USDT</h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">Last Updated</h5>
                            <h2 class="mb-0" id="sandbox-last-updated">Now</h2>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Sandbox Active Trades -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Active Trades - Sandbox</h5>
                    <button class="btn btn-sm btn-outline-primary" id="refresh-sandbox-btn">
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
                                    <th>Quantity</th>
                                    <th>Entry Price</th>
                                    <th>Leverage</th>
                                    <th>Current PNL</th>
                                    <th>Opened</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="sandbox-trades-tbody">
                                <?php if (empty($sandbox_trades)): ?>
                                    <tr>
                                        <td colspan="9" class="text-center py-3">No active trades</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($sandbox_trades as $trade): ?>
                                        <?php 
                                            $pnl_class = isset($trade->pnl) && $trade->pnl >= 0 ? 'text-profit' : 'text-loss';
                                            $side_class = $trade->side == 'BUY' ? 'text-success' : 'text-danger';
                                        ?>
                                        <tr>
                                            <td><?= $trade->symbol ?></td>
                                            <td><?= $trade->strategy_name ?></td>
                                            <td class="<?= $side_class ?>"><?= $trade->side ?></td>
                                            <td><?= $trade->quantity ?></td>
                                            <td><?= $trade->entry_price ?></td>
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
        <?php endif; ?>
    </div>
    
    <!-- Production Tab -->
    <div class="tab-pane fade" id="production" role="tabpanel">
        <?php if (empty($production_api)): ?>
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle me-2"></i>No API keys configured for Production environment.
                <a href="<?= base_url('apikeys/add') ?>" class="alert-link">Configure API Keys</a>
            </div>
        <?php else: ?>
            <!-- Production Summary -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">Active Trades</h5>
                            <h2 class="mb-0"><?= count($production_trades) ?></h2>
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
                                foreach ($production_trades as $trade) {
                                    $total_pnl += isset($trade->pnl) ? $trade->pnl : 0;
                                }
                                $pnl_class = $total_pnl >= 0 ? 'text-profit' : 'text-loss';
                            ?>
                            <h2 class="mb-0 <?= $pnl_class ?>"><?= number_format($total_pnl, 2) ?> USDT</h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">Last Updated</h5>
                            <h2 class="mb-0" id="production-last-updated">Now</h2>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Production Active Trades -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Active Trades - Production</h5>
                    <button class="btn btn-sm btn-outline-primary" id="refresh-production-btn">
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
                                    <th>Quantity</th>
                                    <th>Entry Price</th>
                                    <th>Leverage</th>
                                    <th>Current PNL</th>
                                    <th>Opened</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="production-trades-tbody">
                                <?php if (empty($production_trades)): ?>
                                    <tr>
                                        <td colspan="9" class="text-center py-3">No active trades</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($production_trades as $trade): ?>
                                        <?php 
                                            $pnl_class = isset($trade->pnl) && $trade->pnl >= 0 ? 'text-profit' : 'text-loss';
                                            $side_class = $trade->side == 'BUY' ? 'text-success' : 'text-danger';
                                        ?>
                                        <tr>
                                            <td><?= $trade->symbol ?></td>
                                            <td><?= $trade->strategy_name ?></td>
                                            <td class="<?= $side_class ?>"><?= $trade->side ?></td>
                                            <td><?= $trade->quantity ?></td>
                                            <td><?= $trade->entry_price ?></td>
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
        <?php endif; ?>
    </div>
</div>

<!-- Webhook URL Information Card -->
<div class="card mt-4">
    <div class="card-header">
        <h5 class="mb-0">TradingView Webhook Information</h5>
    </div>
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

<!-- JavaScript for real-time updates -->
<script>
    $(document).ready(function() {
        // Auto-refresh trades every 5 seconds
        let sandboxTimer;
        let productionTimer;
        
        function startSandboxRefresh() {
            sandboxTimer = setInterval(refreshSandboxTrades, 5000);
        }
        
        function startProductionRefresh() {
            productionTimer = setInterval(refreshProductionTrades, 5000);
        }
        
        function stopSandboxRefresh() {
            clearInterval(sandboxTimer);
        }
        
        function stopProductionRefresh() {
            clearInterval(productionTimer);
        }
        
        // Start auto-refresh based on active tab
        if ($('#sandbox-tab').hasClass('active')) {
            startSandboxRefresh();
        }
        
        // Tab change event
        $('button[data-bs-toggle="tab"]').on('shown.bs.tab', function (e) {
            const targetId = $(e.target).attr('id');
            
            if (targetId === 'sandbox-tab') {
                startSandboxRefresh();
                stopProductionRefresh();
            } else if (targetId === 'production-tab') {
                startProductionRefresh();
                stopSandboxRefresh();
            }
        });
        
        // Manual refresh buttons
        $('#refresh-sandbox-btn').click(refreshSandboxTrades);
        $('#refresh-production-btn').click(refreshProductionTrades);
        
        // Refresh functions
        function refreshSandboxTrades() {
            $.ajax({
                url: '<?= base_url('dashboard/refresh_trades') ?>',
                data: { environment: 'sandbox' },
                dataType: 'json',
                success: function(data) {
                    updateTradesTable('sandbox', data);
                    $('#sandbox-last-updated').text(getCurrentTime());
                }
            });
        }
        
        function refreshProductionTrades() {
            $.ajax({
                url: '<?= base_url('dashboard/refresh_trades') ?>',
                data: { environment: 'production' },
                dataType: 'json',
                success: function(data) {
                    updateTradesTable('production', data);
                    $('#production-last-updated').text(getCurrentTime());
                }
            });
        }
        
        function updateTradesTable(environment, trades) {
            const tbody = $(`#${environment}-trades-tbody`);
            tbody.empty();
            
            if (trades.length === 0) {
                tbody.append('<tr><td colspan="9" class="text-center py-3">No active trades</td></tr>');
                return;
            }
            
            let totalPnl = 0;
            
            trades.forEach(function(trade) {
                const pnlClass = (trade.pnl >= 0) ? 'text-profit' : 'text-loss';
                const sideClass = (trade.side === 'BUY') ? 'text-success' : 'text-danger';
                const formattedPnl = (trade.pnl !== null) ? Number(trade.pnl).toFixed(2) + ' USDT' : 'N/A';
                const formattedDate = new Date(trade.created_at).toLocaleString();
                
                totalPnl += parseFloat(trade.pnl || 0);
                
                const row = `<tr>
                    <td>${trade.symbol}</td>
                    <td>${trade.strategy_name}</td>
                    <td class="${sideClass}">${trade.side}</td>
                    <td>${trade.quantity}</td>
                    <td>${trade.entry_price}</td>
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
            
            // Update total PNL
            const pnlClass = totalPnl >= 0 ? 'text-profit' : 'text-loss';
            $(`#${environment} .card:nth-child(3) h2`).removeClass('text-profit text-loss').addClass(pnlClass).text(totalPnl.toFixed(2) + ' USDT');
            
            // Update active trades count
            $(`#${environment} .card:first-child h2`).text(trades.length);
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