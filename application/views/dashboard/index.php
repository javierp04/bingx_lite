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

<!-- Webhook URLs Card (Simplified) -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="fas fa-webhook me-1"></i>Webhook URLs
        </h5>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <label class="form-label"><strong>BingX Webhook:</strong></label>
                <div class="input-group">
                    <input type="text" class="form-control" value="<?= base_url('webhook/tradingview') ?>" readonly>
                    <button class="btn btn-outline-secondary" onclick="copyWebhookUrl(this.previousElementSibling)">
                        <i class="fas fa-copy"></i>
                    </button>
                </div>
            </div>
            <div class="col-md-6">
                <label class="form-label"><strong>MetaTrader Webhook:</strong></label>
                <div class="input-group">
                    <input type="text" class="form-control" value="<?= base_url('metatrader/webhook') ?>" readonly>
                    <button class="btn btn-outline-secondary" onclick="copyWebhookUrl(this.previousElementSibling)">
                        <i class="fas fa-copy"></i>
                    </button>
                </div>
            </div>
        </div>
        <div class="alert alert-info mt-3 mb-0">
            <i class="fas fa-info-circle me-2"></i>
            Use these URLs in your TradingView alerts. Need to test signals or check API connections? Visit the <a href="<?= base_url('debug') ?>" class="alert-link">Debug Panel</a>.
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