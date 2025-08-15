<!-- application/views/dashboard/index.php -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">
        <i class="fas fa-tachometer-alt me-2"></i>Trading Dashboard
    </h1>
    <div class="btc-price-container">
        <span class="text-muted me-2">BTC Price:</span>
        <span id="btc-price" class="badge bg-primary">Loading...</span>
    </div>
</div>

<!-- Platform Filter -->
<div class="card mb-4">
    <div class="card-body py-3">
        <div class="row align-items-center">
            <div class="col-md-6">
                <h6 class="mb-0">Platform Filter</h6>
            </div>
            <div class="col-md-6">
                <div class="btn-group w-100" role="group">
                    <input type="radio" class="btn-check" name="platform-filter" id="platform-all" value="" <?= empty($current_platform) ? 'checked' : '' ?>>
                    <label class="btn btn-outline-primary" for="platform-all">All Platforms</label>

                    <input type="radio" class="btn-check" name="platform-filter" id="platform-bingx" value="bingx" <?= $current_platform === 'bingx' ? 'checked' : '' ?>>
                    <label class="btn btn-outline-primary" for="platform-bingx">
                        <i class="fas fa-bitcoin me-1"></i>BingX
                    </label>

                    <input type="radio" class="btn-check" name="platform-filter" id="platform-mt" value="metatrader" <?= $current_platform === 'metatrader' ? 'checked' : '' ?>>
                    <label class="btn btn-outline-primary" for="platform-mt">
                        <i class="fas fa-chart-area me-1"></i>MetaTrader
                    </label>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Summary Dashboard -->
<div class="row">
    <div class="col-md-3">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">Active Trades</h5>
                <h2 class="mb-0"><?= count($open_trades) ?></h2>
                <?php
                $bingx_count = count(array_filter($open_trades, function($t) { return $t->platform === 'bingx'; }));
                $mt_count = count(array_filter($open_trades, function($t) { return $t->platform === 'metatrader'; }));
                ?>
                <?php if (empty($current_platform)): ?>
                    <small class="text-muted">BingX: <?= $bingx_count ?> | MT: <?= $mt_count ?></small>
                <?php endif; ?>
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
                // Calculate PNL only for BingX trades (MT doesn't have real-time PNL)
                $total_pnl = 0;
                foreach ($open_trades as $trade) {
                    if ($trade->platform === 'bingx' && isset($trade->pnl)) {
                        $total_pnl += $trade->pnl;
                    }
                }
                $pnl_class = $total_pnl >= 0 ? 'text-profit' : 'text-loss';
                ?>
                <h2 class="mb-0 <?= $pnl_class ?>" id="total-pnl"><?= number_format($total_pnl, 2) ?> USDT</h2>
                <small class="text-muted">BingX only (real-time)</small>
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
<div class="card mb-4">
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
                        <th>Platform</th>
                        <th>Symbol</th>
                        <th>Strategy</th>
                        <th>Side</th>
                        <th>Type</th>
                        <th>Position ID</th>
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
                            <td colspan="13" class="text-center py-3">No active trades</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($open_trades as $trade): ?>
                            <?php
                            $pnl_class = isset($trade->pnl) && $trade->pnl >= 0 ? 'text-profit' : 'text-loss';
                            $side_class = $trade->side == 'BUY' ? 'text-success' : 'text-danger';
                            
                            // Platform-specific badges
                            $platform_badge = $trade->platform === 'metatrader' ? 'bg-dark' : 'bg-info';
                            
                            // Type badges
                            $type_badges = [
                                'futures' => 'bg-warning text-dark',
                                'spot' => 'bg-info',
                                'forex' => 'bg-success',
                                'indices' => 'bg-primary', 
                                'commodities' => 'bg-danger'
                            ];
                            $type_class = $type_badges[$trade->trade_type] ?? 'bg-secondary';
                            ?>
                            <tr>
                                <td>
                                    <span class="badge <?= $platform_badge ?>">
                                        <?= ucfirst($trade->platform) ?>
                                    </span>
                                </td>
                                <td><?= $trade->symbol ?></td>
                                <td><?= $trade->strategy_name ?></td>
                                <td class="<?= $side_class ?>"><?= $trade->side ?></td>
                                <td>
                                    <span class="badge <?= $type_class ?>">
                                        <?= ucfirst($trade->trade_type) ?>
                                    </span>
                                </td>
                                <td><?= isset($trade->position_id) ? $trade->position_id : 'N/A' ?></td>
                                <td><?= number_format($trade->entry_price, 2) ?></td>
                                <td class="current-price">
                                    <?php if ($trade->platform === 'bingx'): ?>
                                        <?= isset($trade->current_price) ? number_format($trade->current_price, 2) : number_format($trade->entry_price, 2) ?>
                                    <?php else: ?>
                                        <span class="text-muted">N/A</span>
                                        <small class="d-block text-muted">No real-time</small>
                                    <?php endif; ?>
                                </td>
                                <td><?= rtrim(rtrim(number_format($trade->quantity, 8), '0'), '.') ?></td>
                                <td><?= $trade->leverage ?>x</td>
                                <td class="<?= $pnl_class ?>">
                                    <?php if ($trade->platform === 'bingx' && isset($trade->pnl)): ?>
                                        <?= number_format($trade->pnl, 2) . ' USDT' ?>
                                    <?php else: ?>
                                        <span class="text-muted">At close</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= date('Y-m-d H:i', strtotime($trade->created_at)) ?></td>
                                <td>
                                    <?php if ($trade->platform === 'bingx'): ?>
                                        <a href="<?= base_url('trades/close/' . $trade->id) ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to close this trade?')">
                                            <i class="fas fa-times-circle"></i> Close
                                        </a>
                                    <?php else: ?>
                                        <button class="btn btn-sm btn-outline-secondary" disabled title="Close via MT EA">
                                            <i class="fas fa-robot"></i> EA
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

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
                <!-- First Row - Essential fields: Environment, Strategy, Ticker, Timeframe, Action -->
                <div class="col-md-2">
                    <label for="sim_environment" class="form-label">Environment</label>
                    <select class="form-select" id="sim_environment">
                        <option value="production">Production</option>
                        <option value="sandbox">Sandbox (Futures Only)</option>
                    </select>
                </div>
                
                <div class="col-md-3">
                    <label for="sim_strategy_id" class="form-label">Strategy</label>
                    <select class="form-select" id="sim_strategy_id" required>
                        <?php foreach ($all_strategies as $strategy): ?>
                            <option value="<?= $strategy->strategy_id ?>" data-platform="<?= $strategy->platform ?>"><?= $strategy->name ?> (<?= $strategy->strategy_id ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <label for="sim_ticker" class="form-label">Ticker</label>
                    <input type="text" class="form-control" id="sim_ticker" placeholder="e.g., BTCUSDT" required value="BTCUSDT">
                </div>
                
                <div class="col-md-2">
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
                    <label for="sim_action" class="form-label">Action</label>
                    <select class="form-select" id="sim_action" required>
                        <option value="BUY">BUY</option>
                        <option value="SELL">SELL</option>
                        <option value="CLOSE">CLOSE</option>
                    </select>
                </div>
                
                <!-- Second Row - Position ID, Quantity, Leverage, TP/SL -->
                <div class="col-md-3">
                    <label for="sim_position_id" class="form-label">Position ID</label>
                    <input type="text" class="form-control" id="sim_position_id" placeholder="e.g., 12345">
                    <div class="form-text small">Optional identifier for position tracking</div>
                </div>
                
                <div class="col-md-2">
                    <label for="sim_quantity" class="form-label">Quantity</label>
                    <input type="number" class="form-control" id="sim_quantity" step="0.0001" min="0.0001" value="0.0001" required>
                </div>
                
                <div class="col-md-2">
                    <label for="sim_leverage" class="form-label">Leverage</label>
                    <select class="form-select" id="sim_leverage">
                        <option value="">--------</option>
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
                
                <div class="col-md-2">
                    <label for="sim_take_profit" class="form-label">Take Profit</label>
                    <input type="number" class="form-control" id="sim_take_profit" step="0.01" placeholder="TP price">
                </div>
                
                <div class="col-md-2">
                    <label for="sim_stop_loss" class="form-label">Stop Loss</label>
                    <input type="number" class="form-control" id="sim_stop_loss" step="0.01" placeholder="SL price">
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
<?php endif; ?>

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
            <div class="row">
                <div class="col-md-6">
                    <p><strong>BingX Webhook URL:</strong></p>
                    <div class="input-group mb-3">
                        <input type="text" class="form-control" value="<?= base_url('webhook/tradingview') ?>" readonly>
                        <button class="btn btn-outline-secondary" type="button" onclick="copyWebhookUrl(this.previousElementSibling)">
                            <i class="fas fa-copy"></i> Copy
                        </button>
                    </div>
                </div>
                <div class="col-md-6">
                    <p><strong>MetaTrader Webhook URL:</strong></p>
                    <div class="input-group mb-3">
                        <input type="text" class="form-control" value="<?= base_url('metatrader/webhook') ?>" readonly>
                        <button class="btn btn-outline-secondary" type="button" onclick="copyWebhookUrl(this.previousElementSibling)">
                            <i class="fas fa-copy"></i> Copy
                        </button>
                    </div>
                </div>
            </div>
            
            <p>Required JSON format for TradingView webhook:</p>
            <div class="row">
                <div class="col-md-6">
                    <h6>BingX Alert Format:</h6>
                    <pre class="bg-light p-3 rounded"><code>{
  "user_id": <?= $this->session->userdata('user_id') ?>,
  "strategy_id": "YOUR_STRATEGY_ID",
  "ticker": "{{ticker}}",
  "timeframe": "{{interval}}",
  "action": "{{strategy.order.action}}",
  "quantity": "{{strategy.order.contracts}}",
  "position_id": "{{strategy.order.comment}}",
  "leverage": 8,
  "environment": "production"
}</code></pre>
                </div>
                <div class="col-md-6">
                    <h6>MetaTrader Alert Format:</h6>
                    <pre class="bg-light p-3 rounded"><code>{
  "user_id": <?= $this->session->userdata('user_id') ?>,
  "strategy_id": "YOUR_STRATEGY_ID",
  "ticker": "{{ticker}}",
  "timeframe": 60,
  "action": "{{strategy.order.action}}",
  "quantity": 0.1,
  "price": {{close}},
  "position_id": "{{strategy.order.comment}}"
}</code></pre>
                </div>
            </div>
            
            <div class="alert alert-info mt-3">
                <i class="fas fa-lightbulb me-2"></i><strong>Platform Differences:</strong><br>
                <strong>BingX:</strong> Uses timeframe as strings (1h, 5m), supports leverage and environment<br>
                <strong>MetaTrader:</strong> Uses timeframe in minutes (60), requires price field, no leverage/environment
            </div>
        </div>
    </div>
</div>

<!-- Estilos adicionales para el precio de BTC -->
<style>
    .btc-price-container {
        font-size: 0.9rem;
        display: flex;
        align-items: center;
    }

    #btc-price {
        font-size: 1rem;
        padding: 0.4rem 0.8rem;
        font-weight: bold;
        transition: background-color 0.3s ease;
    }

    .bg-success {
        background-color: #28a745 !important;
    }

    .bg-danger {
        background-color: #dc3545 !important;
    }
</style>

<!-- JavaScript para actualizaciones en tiempo real y filtros -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Platform filter functionality
        setupPlatformFilter();

        // Iniciar actualizaciones de precio BTC
        updateBtcPrice();

        // Iniciar actualizaciones de trades
        setupTradesRefresh();

        // Configurar controles de simulación
        if (document.getElementById('simulate-order-btn')) {
            setupSimulationControls();
        }

        // Configurar eventos para el panel de webhooks
        setupWebhookPanel();
    });

    // Setup platform filter
    function setupPlatformFilter() {
        const filterInputs = document.querySelectorAll('input[name="platform-filter"]');
        
        filterInputs.forEach(input => {
            input.addEventListener('change', function() {
                if (this.checked) {
                    const platform = this.value;
                    const currentUrl = new URL(window.location);
                    
                    if (platform) {
                        currentUrl.searchParams.set('platform', platform);
                    } else {
                        currentUrl.searchParams.delete('platform');
                    }
                    
                    window.location.href = currentUrl.toString();
                }
            });
        });
    }

    // Configurar panel de webhook
    function setupWebhookPanel() {
        const webhookInfoElement = document.getElementById('webhookInfo');
        if (webhookInfoElement) {
            webhookInfoElement.addEventListener('show.bs.collapse', function() {
                const chevronIcon = document.querySelector('.card-header .fa-chevron-down');
                if (chevronIcon) {
                    chevronIcon.classList.remove('fa-chevron-down');
                    chevronIcon.classList.add('fa-chevron-up');
                }
            });

            webhookInfoElement.addEventListener('hide.bs.collapse', function() {
                const chevronIcon = document.querySelector('.card-header .fa-chevron-up');
                if (chevronIcon) {
                    chevronIcon.classList.remove('fa-chevron-up');
                    chevronIcon.classList.add('fa-chevron-down');
                }
            });
        }
    }

    // Actualizar precio de BTC
    function updateBtcPrice() {
        const btcPrice = document.getElementById('btc-price');
        if (!btcPrice) return;

        fetch('<?= base_url('dashboard/get_btc_price') ?>')
            .then(response => response.json())
            .then(data => {
                if (data.success && data.price) {
                    const price = parseFloat(data.price);

                    // Formatear el precio
                    btcPrice.textContent = '$' + data.price_formatted;

                    // Cambiar el color basado en la comparación con el precio anterior
                    const prevPrice = btcPrice.getAttribute('data-prev-price');
                    if (prevPrice) {
                        if (price > parseFloat(prevPrice)) {
                            btcPrice.classList.remove('bg-danger', 'bg-primary');
                            btcPrice.classList.add('bg-success');
                        } else if (price < parseFloat(prevPrice)) {
                            btcPrice.classList.remove('bg-success', 'bg-primary');
                            btcPrice.classList.add('bg-danger');
                        } else {
                            btcPrice.classList.remove('bg-success', 'bg-danger');
                            btcPrice.classList.add('bg-primary');
                        }
                    }

                    // Guardar el precio actual para la próxima comparación
                    btcPrice.setAttribute('data-prev-price', price.toString());
                }
            })
            .catch(error => {
                console.error('Error fetching BTC price:', error);
            })
            .finally(() => {
                // Actualizar cada 5 segundos
                setTimeout(updateBtcPrice, 5000);
            });
    }

    // Configurar actualización de trades
    function setupTradesRefresh() {
        // Variables para actualización de trades
        let refreshTimer;

        // Iniciar auto-refresh
        startRefresh();

        // Botón de actualización manual
        const refreshBtn = document.getElementById('refresh-trades-btn');
        if (refreshBtn) {
            refreshBtn.addEventListener('click', refreshTrades);
        }

        function startRefresh() {
            refreshTimer = setInterval(refreshTrades, 5000); // Cada 5 segundos
        }

        function stopRefresh() {
            clearInterval(refreshTimer);
        }
    }

    // Función para actualizar trades
    function refreshTrades() {
        const currentPlatform = document.querySelector('input[name="platform-filter"]:checked')?.value || '';
        const url = '<?= base_url('dashboard/refresh_trades') ?>' + (currentPlatform ? '?platform=' + currentPlatform : '');
        
        fetch(url)
            .then(response => response.json())
            .then(data => {
                updateTradesTable(data);

                const lastUpdatedElement = document.getElementById('last-updated');
                if (lastUpdatedElement) {
                    lastUpdatedElement.textContent = getCurrentTime();
                }

                // Calcular PNL total solo para BingX
                const totalPnl = calculateBingXPnl(data);
                const pnlClass = totalPnl >= 0 ? 'text-profit' : 'text-loss';

                // Actualizar visualización del PNL
                const totalPnlElement = document.getElementById('total-pnl');
                if (totalPnlElement) {
                    totalPnlElement.classList.remove('text-profit', 'text-loss');
                    totalPnlElement.classList.add(pnlClass);
                    totalPnlElement.textContent = formatNumber(totalPnl, 2) + ' USDT';
                }
            })
            .catch(error => {
                console.error('Error refreshing trades:', error);
            });
    }

    // Actualizar tabla de trades
    function updateTradesTable(trades) {
        const tbody = document.getElementById('trades-tbody');
        if (!tbody) return;

        // Limpiar tabla actual
        tbody.innerHTML = '';

        if (trades.length === 0) {
            const emptyRow = document.createElement('tr');
            emptyRow.innerHTML = '<td colspan="13" class="text-center py-3">No active trades</td>';
            tbody.appendChild(emptyRow);
            return;
        }

        // Crear filas para cada trade
        trades.forEach(function(trade) {
            const pnlClass = (parseFloat(trade.pnl || 0) >= 0) ? 'text-profit' : 'text-loss';
            const sideClass = (trade.side === 'BUY') ? 'text-success' : 'text-danger';
            
            // Platform badge
            const platformBadge = trade.platform === 'metatrader' ? 'bg-dark' : 'bg-info';
            
            // Type badges
            const typeBadges = {
                'futures': 'bg-warning text-dark',
                'spot': 'bg-info',
                'forex': 'bg-success',
                'indices': 'bg-primary',
                'commodities': 'bg-danger'
            };
            const typeBadge = typeBadges[trade.trade_type] || 'bg-secondary';

            // Format quantity - remove trailing zeros
            const quantity = formatQuantity(trade.quantity);
            
            // Usar valores formateados o aplicar formato a los originales
            const entryPrice = trade.entry_price_formatted || formatNumber(trade.entry_price, 2);
            const currentPrice = trade.platform === 'bingx' ? 
                (trade.current_price_formatted || (trade.current_price ? formatNumber(trade.current_price, 2) : entryPrice)) :
                'N/A';
            
            const formattedPnl = trade.platform === 'bingx' ? 
                (trade.pnl_formatted ? trade.pnl_formatted + ' USDT' : 
                ((trade.pnl !== null) ? formatNumber(trade.pnl, 2) + ' USDT' : 'N/A')) :
                'At close';

            const formattedDate = new Date(trade.created_at).toLocaleString();
            
            // Display position ID or N/A
            const positionId = trade.position_id || 'N/A';

            const row = document.createElement('tr');
            row.innerHTML = `
                <td>
                    <span class="badge ${platformBadge}">
                        ${trade.platform.charAt(0).toUpperCase() + trade.platform.slice(1)}
                    </span>
                </td>
                <td>${trade.symbol}</td>
                <td>${trade.strategy_name}</td>
                <td class="${sideClass}">${trade.side}</td>
                <td>
                    <span class="badge ${typeBadge}">
                        ${trade.trade_type.charAt(0).toUpperCase() + trade.trade_type.slice(1)}
                    </span>
                </td>
                <td>${positionId}</td>
                <td>${entryPrice}</td>
                <td class="current-price">
                    ${trade.platform === 'bingx' ? currentPrice : '<span class="text-muted">N/A</span><small class="d-block text-muted">No real-time</small>'}
                </td>
                <td>${quantity}</td>
                <td>${trade.leverage}x</td>
                <td class="${pnlClass}">
                    ${trade.platform === 'bingx' ? formattedPnl : '<span class="text-muted">At close</span>'}
                </td>
                <td>${formattedDate}</td>
                <td>
                    ${trade.platform === 'bingx' ? 
                        `<a href="<?= base_url('trades/close/') ?>${trade.id}" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to close this trade?')">
                            <i class="fas fa-times-circle"></i> Close
                        </a>` :
                        `<button class="btn btn-sm btn-outline-secondary" disabled title="Close via MT EA">
                            <i class="fas fa-robot"></i> EA
                        </button>`
                    }
                </td>
            `;

            tbody.appendChild(row);
        });
    }

    // Formatear números con número fijo de decimals
    function formatNumber(number, decimals) {
        return parseFloat(number).toFixed(decimals);
    }

    // Formatear quantity sin ceros a la derecha
    function formatQuantity(number) {
        if (!number) return "0";
        
        // First format with 8 decimal places
        let formatted = parseFloat(number).toFixed(8);
        
        // Remove trailing zeros
        formatted = formatted.replace(/\.?0+$/, '');
        
        // If we accidentally removed the decimal point too, add it back if needed
        if (formatted.endsWith('.')) {
            formatted = formatted.slice(0, -1);
        }
        
        return formatted;
    }

    // Calcular PNL total de trades BingX solamente
    function calculateBingXPnl(trades) {
        let totalPnl = 0;
        trades.forEach(function(trade) {
            if (trade.platform === 'bingx') {
                totalPnl += parseFloat(trade.pnl || 0);
            }
        });
        return totalPnl;
    }

    // Obtener hora actual formateada
    function getCurrentTime() {
        const now = new Date();
        const hours = now.getHours().toString().padStart(2, '0');
        const minutes = now.getMinutes().toString().padStart(2, '0');
        const seconds = now.getSeconds().toString().padStart(2, '0');
        return `${hours}:${minutes}:${seconds}`;
    }

    // Configurar controles de simulación
    function setupSimulationControls() {
        // Botón de simulación de orden
        const simulateBtn = document.getElementById('simulate-order-btn');
        if (simulateBtn) {
            simulateBtn.addEventListener('click', function() {
                // Obtener valores del formulario
                const selectedStrategy = document.getElementById('sim_strategy_id');
                const platform = selectedStrategy.options[selectedStrategy.selectedIndex].getAttribute('data-platform');
                
                const formData = {
                    strategy_id: selectedStrategy.value,
                    ticker: document.getElementById('sim_ticker').value,
                    timeframe: document.getElementById('sim_timeframe').value,
                    action: document.getElementById('sim_action').value,
                    quantity: document.getElementById('sim_quantity').value
                };

                // Add platform-specific fields
                if (platform === 'bingx') {
                    formData.leverage = document.getElementById('sim_leverage').value;
                    formData.environment = document.getElementById('sim_environment').value;
                    
                    // Add take profit and stop loss if they have values
                    const takeProfitEl = document.getElementById('sim_take_profit');
                    if (takeProfitEl && takeProfitEl.value) {
                        formData.take_profit = parseFloat(takeProfitEl.value);
                    }
                    
                    const stopLossEl = document.getElementById('sim_stop_loss');
                    if (stopLossEl && stopLossEl.value) {
                        formData.stop_loss = parseFloat(stopLossEl.value);
                    }
                }
                
                // Add position_id if it has a value
                const positionIdEl = document.getElementById('sim_position_id');
                if (positionIdEl && positionIdEl.value) {
                    formData.position_id = positionIdEl.value;
                }
                
                // Establecer los datos JSON en el campo oculto
                const dataField = document.getElementById('simulate_data');
                if (dataField) {
                    dataField.value = JSON.stringify(formData);
                    // Enviar el formulario
                    dataField.form.submit();
                }
            });
        }

        // Strategy change handler - adjust available fields
        const strategySelect = document.getElementById('sim_strategy_id');
        if (strategySelect) {
            strategySelect.addEventListener('change', updateSimulationFields);
            updateSimulationFields(); // Initial call
        }

        function updateSimulationFields() {
            const selectedStrategy = strategySelect.options[strategySelect.selectedIndex];
            const platform = selectedStrategy.getAttribute('data-platform');
            
            const environmentField = document.getElementById('sim_environment').closest('.col-md-2');
            const leverageField = document.getElementById('sim_leverage').closest('.col-md-2');
            const takeProfitField = document.getElementById('sim_take_profit').closest('.col-md-2');
            const stopLossField = document.getElementById('sim_stop_loss').closest('.col-md-2');
            
            if (platform === 'metatrader') {
                // Hide BingX-specific fields
                environmentField.style.display = 'none';
                leverageField.style.display = 'none';
                takeProfitField.style.display = 'none';
                stopLossField.style.display = 'none';
                
                // Update ticker placeholder for MT
                document.getElementById('sim_ticker').placeholder = 'e.g., EURUSD, XAUUSD';
                document.getElementById('sim_ticker').value = 'EURUSD';
            } else {
                // Show BingX fields
                environmentField.style.display = 'block';
                leverageField.style.display = 'block';
                takeProfitField.style.display = 'block';
                stopLossField.style.display = 'block';
                
                // Update ticker placeholder for BingX
                document.getElementById('sim_ticker').placeholder = 'e.g., BTCUSDT';
                document.getElementById('sim_ticker').value = 'BTCUSDT';
            }
        }

        // Botón de prueba de precio spot
        const testSpotPriceBtn = document.getElementById('test-spot-price-btn');
        if (testSpotPriceBtn) {
            testSpotPriceBtn.addEventListener('click', function() {
                const symbol = document.getElementById('test-symbol').value;
                window.location.href = '<?= base_url('dashboard/test_spot_price') ?>?symbol=' + encodeURIComponent(symbol);
            });
        }

        // Botón de prueba de precio futures
        const testFuturesPriceBtn = document.getElementById('test-futures-price-btn');
        if (testFuturesPriceBtn) {
            testFuturesPriceBtn.addEventListener('click', function() {
                const symbol = document.getElementById('test-symbol').value;
                window.location.href = '<?= base_url('dashboard/test_futures_price') ?>?symbol=' + encodeURIComponent(symbol);
            });
        }

        // Manejar tecla Enter en el campo de símbolo
        const testSymbolInput = document.getElementById('test-symbol');
        if (testSymbolInput) {
            testSymbolInput.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    if (testSpotPriceBtn) {
                        testSpotPriceBtn.click();
                    }
                }
            });
        }
    }

    // Función para copiar la URL del webhook
    function copyWebhookUrl(element) {
        element.select();
        document.execCommand('copy');

        // Mostrar confirmación
        const button = element.nextElementSibling;
        if (button) {
            const originalText = button.innerHTML;
            button.innerHTML = '<i class="fas fa-check"></i> Copied!';

            setTimeout(function() {
                button.innerHTML = originalText;
            }, 2000);
        }
    }
</script>