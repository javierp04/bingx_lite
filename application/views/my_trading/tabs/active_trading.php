<!-- Trading Dashboard Header -->
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0">
        <i class="fas fa-tachometer-alt me-1"></i>Trading Dashboard
        <span class="badge bg-primary ms-2"><?= count($dashboard_signals) ?></span>
    </h5>
    <div class="btn-group">
        <button class="btn btn-outline-primary btn-sm" id="refreshBtn">
            <i class="fas fa-sync-alt me-1"></i>Refresh
        </button>
        <div class="form-check form-switch ms-3">
            <input class="form-check-input" type="checkbox" id="autoRefresh" checked>
            <label class="form-check-label" for="autoRefresh">Auto-refresh</label>
        </div>
    </div>
</div>

<!-- Dashboard Filters -->
<div class="card mb-4">
    <div class="card-header">
        <h6 class="mb-0">
            <i class="fas fa-filter me-1"></i>Dashboard Filters
        </h6>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-3">
                <label for="status_filter" class="form-label">Status</label>
                <select class="form-select" id="status_filter" name="status_filter">
                    <option value="">All Signals</option>
                    <option value="active" <?= ($filters['status_filter'] ?? '') === 'active' ? 'selected' : '' ?>>Active Only (Pending + Open)</option>
                    <option value="pending" <?= ($filters['status_filter'] ?? '') === 'pending' ? 'selected' : '' ?>>Pending Only</option>
                    <option value="open" <?= ($filters['status_filter'] ?? '') === 'open' ? 'selected' : '' ?>>Open Only</option>
                    <option value="closed" <?= ($filters['status_filter'] ?? '') === 'closed' ? 'selected' : '' ?>>Closed Only</option>
                </select>
            </div>
            <div class="col-md-2">
                <label for="ticker_filter" class="form-label">Ticker</label>
                <select class="form-select" id="ticker_filter" name="ticker_filter">
                    <option value="">All Tickers</option>
                    <?php if (isset($user_tickers)): ?>
                        <?php foreach ($user_tickers as $ticker): ?>
                            <option value="<?= $ticker->ticker_symbol ?>" <?= ($filters['ticker_filter'] ?? '') === $ticker->ticker_symbol ? 'selected' : '' ?>>
                                <?= $ticker->ticker_symbol ?>
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label for="date_range" class="form-label">Date Range</label>
                <select class="form-select" id="date_range" name="date_range">
                    <option value="7" <?= ($filters['date_range'] ?? '7') === '7' ? 'selected' : '' ?>>Last 7 days</option>
                    <option value="30" <?= ($filters['date_range'] ?? '') === '30' ? 'selected' : '' ?>>Last 30 days</option>
                    <option value="all" <?= ($filters['date_range'] ?? '') === 'all' ? 'selected' : '' ?>>All Time</option>
                </select>
            </div>
            <div class="col-md-2">
                <label for="pnl_filter" class="form-label">PNL Filter</label>
                <select class="form-select" id="pnl_filter" name="pnl_filter">
                    <option value="">All PNL</option>
                    <option value="profit" <?= ($filters['pnl_filter'] ?? '') === 'profit' ? 'selected' : '' ?>>Profitable</option>
                    <option value="loss" <?= ($filters['pnl_filter'] ?? '') === 'loss' ? 'selected' : '' ?>>Loss</option>
                    <option value="breakeven" <?= ($filters['pnl_filter'] ?? '') === 'breakeven' ? 'selected' : '' ?>>Break Even</option>
                </select>
            </div>
            <div class="col-md-3">
                <div class="d-flex gap-2 align-items-end h-100">
                    <button type="button" class="btn btn-primary" id="filterBtn">
                        <i class="fas fa-search me-1"></i>Filter
                    </button>
                    <button type="button" class="btn btn-secondary" id="resetBtn">
                        <i class="fas fa-undo me-1"></i>Reset
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Dashboard Content Container (Table + Stats) -->
<div id="dashboard-content-container">
    <!-- Initial load - this will be replaced by AJAX -->
    <?php $this->load->view('my_trading/partials/dashboard_content', ['dashboard_signals' => $dashboard_signals]); ?>
</div>

<script>
const base_url = '<?= base_url() ?>';

document.addEventListener('DOMContentLoaded', function() {
    let autoRefreshInterval = null;
    let isRefreshing = false;
    let isFiltering = false;
    const autoRefreshMs = 30000; // 30 seconds
    
    const refreshBtn = document.getElementById('refreshBtn');
    const autoRefreshSwitch = document.getElementById('autoRefresh');
    const filterBtn = document.getElementById('filterBtn');
    const resetBtn = document.getElementById('resetBtn');
    
    // Manual refresh button
    refreshBtn.addEventListener('click', function() {
        manualRefresh();
    });
    
    // Auto refresh toggle
    autoRefreshSwitch.addEventListener('change', function() {
        if (this.checked) {
            startAutoRefresh();
        } else {
            stopAutoRefresh();
        }
    });
    
    // Filter button
    filterBtn.addEventListener('click', function() {
        applyFilters();
    });
    
    // Reset button
    resetBtn.addEventListener('click', function() {
        resetFilters();
    });
    
    // Start auto refresh by default
    startAutoRefresh();
    
    function manualRefresh() {
        if (isRefreshing || isFiltering) return;
        
        const originalContent = refreshBtn.innerHTML;
        
        refreshBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Refreshing...';
        refreshBtn.disabled = true;
        isRefreshing = true;
        
        refreshDashboard()
            .then(() => {
                showSuccess('Dashboard refreshed successfully');
            })
            .catch((error) => {
                console.error('Manual refresh failed:', error);
                showError('Failed to refresh dashboard');
            })
            .finally(() => {
                refreshBtn.innerHTML = originalContent;
                refreshBtn.disabled = false;
                isRefreshing = false;
            });
    }
    
    function applyFilters() {
        if (isRefreshing || isFiltering) return;
        
        const originalContent = filterBtn.innerHTML;
        
        filterBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Filtering...';
        filterBtn.disabled = true;
        isFiltering = true;
        
        refreshDashboard()
            .then(() => {
                showSuccess('Filters applied successfully');
            })
            .catch((error) => {
                console.error('Filter application failed:', error);
                showError('Failed to apply filters');
            })
            .finally(() => {
                filterBtn.innerHTML = originalContent;
                filterBtn.disabled = false;
                isFiltering = false;
            });
    }
    
    function resetFilters() {
        if (isRefreshing || isFiltering) return;
        
        const originalContent = resetBtn.innerHTML;
        
        resetBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Resetting...';
        resetBtn.disabled = true;
        isFiltering = true;
        
        // Clear all filters to default values
        document.getElementById('status_filter').value = '';
        document.getElementById('ticker_filter').value = '';
        document.getElementById('date_range').value = '7';
        document.getElementById('pnl_filter').value = '';
        
        refreshDashboard()
            .then(() => {
                showSuccess('Filters reset successfully');
            })
            .catch((error) => {
                console.error('Filter reset failed:', error);
                showError('Failed to reset filters');
            })
            .finally(() => {
                resetBtn.innerHTML = originalContent;
                resetBtn.disabled = false;
                isFiltering = false;
            });
    }
    
    function startAutoRefresh() {
        stopAutoRefresh(); // Clear any existing interval
        
        autoRefreshInterval = setInterval(() => {
            if (!isRefreshing && !isFiltering && autoRefreshSwitch.checked) {
                refreshDashboard().catch(error => {
                    console.error('Auto refresh failed:', error);
                });
            }
        }, autoRefreshMs);
    }
    
    function stopAutoRefresh() {
        if (autoRefreshInterval) {
            clearInterval(autoRefreshInterval);
            autoRefreshInterval = null;
        }
    }
    
    async function refreshDashboard() {
        // Get current filter values
        const filters = getCurrentFilters();
        const queryString = new URLSearchParams(filters).toString();
        
        const response = await fetch(`${base_url}my_trading/refresh_dashboard_ajax?${queryString}`, {
            method: 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Cache-Control': 'no-cache'
            }
        });
        
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        
        const data = await response.json();
        
        if (!data.success) {
            throw new Error('Server returned error');
        }
        
        // Update UI
        updateDashboard(data);
    }
    
    function getCurrentFilters() {
        return {
            status_filter: document.getElementById('status_filter')?.value || '',
            ticker_filter: document.getElementById('ticker_filter')?.value || '',
            date_range: document.getElementById('date_range')?.value || '7',
            pnl_filter: document.getElementById('pnl_filter')?.value || ''
        };
    }
    
    function updateDashboard(data) {
        // Update complete content (table + stats)
        const contentContainer = document.getElementById('dashboard-content-container');
        if (contentContainer) {
            contentContainer.innerHTML = data.content_html;
        }
        
        // Update count badge in header
        const countBadge = document.querySelector('h5 .badge');
        if (countBadge) {
            countBadge.textContent = data.count;
        }
        
        // Update or add last refresh indicator
        updateLastRefresh(data.timestamp);
    }
    
    function updateLastRefresh(timestamp) {
        let indicator = document.getElementById('lastRefreshIndicator');
        if (!indicator) {
            // Create indicator if it doesn't exist
            indicator = document.createElement('small');
            indicator.id = 'lastRefreshIndicator';
            indicator.className = 'text-muted ms-3';
            
            // Add to the header
            const headerTitle = document.querySelector('h5');
            if (headerTitle) {
                headerTitle.appendChild(indicator);
            }
        }
        
        const refreshTime = new Date(timestamp).toLocaleTimeString();
        indicator.textContent = `Last updated: ${refreshTime}`;
    }
    
    function showSuccess(message) {
        showNotification(message, 'success');
    }
    
    function showError(message) {
        showNotification(message, 'danger');
    }
    
    function showNotification(message, type) {
        // Remove any existing notifications
        const existing = document.querySelector('.refresh-notification');
        if (existing) {
            existing.remove();
        }
        
        // Create notification
        const notification = document.createElement('div');
        notification.className = `alert alert-${type} alert-dismissible fade show refresh-notification position-fixed`;
        notification.style.cssText = `
            top: 20px;
            right: 20px;
            z-index: 9999;
            min-width: 300px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        `;
        
        notification.innerHTML = `
            <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-triangle'} me-2"></i>
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        
        document.body.appendChild(notification);
        
        // Auto remove after 3 seconds
        setTimeout(() => {
            if (notification.parentNode) {
                notification.remove();
            }
        }, 3000);
    }
    
    // Optional: Apply filters when pressing Enter on selects
    document.querySelectorAll('#status_filter, #ticker_filter, #date_range, #pnl_filter').forEach(select => {
        select.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                applyFilters();
            }
        });
    });
    
    // Cleanup on page unload
    window.addEventListener('beforeunload', function() {
        stopAutoRefresh();
    });
});
</script>

<style>
    .signal-row:hover {
        background-color: #f8f9fa;
    }

    .signal-row.table-secondary {
        opacity: 0.8;
    }

    .progress {
        width: 60px;
    }

    .elapsed-time {
        font-weight: 500;
    }

    .volume-info {
        min-width: 80px;
    }

    .pnl-display {
        min-width: 70px;
        font-weight: 500;
    }

    .entry-price,
    .current-price,
    .final-price {
        line-height: 1.2;
    }
    
    .refresh-notification {
        animation: slideInRight 0.3s ease-out;
    }
    
    @keyframes slideInRight {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
    
    /* Loading states for buttons */
    .btn:disabled {
        opacity: 0.7;
        cursor: not-allowed;
    }
</style>