<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">
        <i class="fas fa-signal me-2"></i>Signals Management
    </h1>
    <div class="btn-group">
        <a href="<?= base_url('signals/management') ?>" class="btn btn-primary">
            <i class="fas fa-cog me-1"></i>Manage Signals
        </a>
        <button class="btn btn-info" id="auto-refresh-toggle">
            <i class="fas fa-pause me-1"></i>Auto-refresh: ON
        </button>
    </div>
</div>

<!-- Statistics Cards -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card bg-warning bg-opacity-10">
            <div class="card-body text-center">
                <h5 class="card-title text-muted">Pending Signals</h5>
                <h2 class="mb-0 text-warning" id="pending-count"><?= $stats['pending'] ?></h2>
                <small class="text-muted">Awaiting processing</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-success bg-opacity-10">
            <div class="card-body text-center">
                <h5 class="card-title text-muted">Processed</h5>
                <h2 class="mb-0 text-success" id="processed-count"><?= $stats['processed'] ?></h2>
                <small class="text-muted">Successfully executed</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-danger bg-opacity-10">
            <div class="card-body text-center">
                <h5 class="card-title text-muted">Failed</h5>
                <h2 class="mb-0 text-danger" id="failed-count"><?= $stats['failed'] ?></h2>
                <small class="text-muted">Execution errors</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card">
            <div class="card-body text-center">
                <h5 class="card-title text-muted">Success Rate</h5>
                <h2 class="mb-0" id="success-rate"><?= $stats['success_rate'] ?>%</h2>
                <small class="text-muted">Today's performance</small>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Recent Signals -->
    <div class="col-md-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="fas fa-clock me-1"></i>Recent Signals
                </h5>
                <div class="btn-group">
                    <button class="btn btn-sm btn-outline-primary" id="refresh-signals-btn">
                        <i class="fas fa-sync-alt me-1"></i>Refresh
                    </button>
                    <div class="btn-group">
                        <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                            <i class="fas fa-filter me-1"></i>Filter
                        </button>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="?status=">All Signals</a></li>
                            <li><a class="dropdown-item" href="?status=pending">Pending Only</a></li>
                            <li><a class="dropdown-item" href="?status=processed">Processed Only</a></li>
                            <li><a class="dropdown-item" href="?status=failed">Failed Only</a></li>
                        </ul>
                    </div>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped table-hover mb-0">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Platform</th>
                                <th>Strategy</th>
                                <th>Action</th>
                                <th>Symbol</th>
                                <th>Status</th>
                                <th>Created</th>
                                <th>Processed</th>
                            </tr>
                        </thead>
                        <tbody id="recent-signals-tbody">
                            <?php if (empty($recent_signals)): ?>
                                <tr>
                                    <td colspan="8" class="text-center py-3">No recent signals</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($recent_signals as $signal): ?>
                                    <?php 
                                        $signal_data = json_decode($signal->signal_data);
                                        $status_class = $signal->status == 'processed' ? 'bg-success' : 
                                                      ($signal->status == 'failed' ? 'bg-danger' : 'bg-warning');
                                        
                                        // Determine platform from strategy or signal data
                                        $platform = 'Unknown';
                                        $platform_badge = 'bg-secondary';
                                        if (isset($signal_data->action)) {
                                            if (in_array($signal_data->action, ['buy', 'short', 'sell', 'cover'])) {
                                                $platform = 'MetaTrader';
                                                $platform_badge = 'bg-dark';
                                            } elseif (in_array($signal_data->action, ['BUY', 'SELL', 'CLOSE'])) {
                                                $platform = 'BingX';
                                                $platform_badge = 'bg-info';
                                            }
                                        }
                                    ?>
                                    <tr>
                                        <td><?= $signal->id ?></td>
                                        <td>
                                            <span class="badge <?= $platform_badge ?>">
                                                <?= $platform ?>
                                            </span>
                                        </td>
                                        <td>
                                            <strong><?= $signal->strategy_name ?></strong><br>
                                            <small class="text-muted"><?= $signal->strategy_external_id ?></small>
                                        </td>
                                        <td>
                                            <?php if (isset($signal_data->action)): ?>
                                                <?php
                                                    // Badge colors for actions
                                                    $action_badge = 'bg-secondary';
                                                    $action = strtoupper($signal_data->action);
                                                    if (in_array($action, ['BUY', 'BUY'])) $action_badge = 'bg-success';
                                                    elseif (in_array($action, ['SELL', 'SHORT'])) $action_badge = 'bg-danger';
                                                    elseif (in_array($action, ['CLOSE', 'COVER'])) $action_badge = 'bg-warning text-dark';
                                                ?>
                                                <span class="badge <?= $action_badge ?>">
                                                    <?= $action ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted">N/A</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?= isset($signal_data->ticker) ? $signal_data->ticker : 'N/A' ?>
                                        </td>
                                        <td>
                                            <span class="badge <?= $status_class ?>">
                                                <?= ucfirst($signal->status) ?>
                                            </span>
                                        </td>
                                        <td><?= date('H:i:s', strtotime($signal->created_at)) ?></td>
                                        <td><?= $signal->processed_at ? date('H:i:s', strtotime($signal->processed_at)) : '-' ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Platform Activity & Quick Actions -->
    <div class="col-md-4">
        <!-- Platform Breakdown -->
        <div class="card mb-3">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-chart-pie me-1"></i>Platform Activity
                </h5>
            </div>
            <div class="card-body">
                <?php
                // Calculate platform breakdown
                $platform_stats = ['bingx' => 0, 'metatrader' => 0];
                foreach ($recent_signals as $signal) {
                    $signal_data = json_decode($signal->signal_data);
                    if (isset($signal_data->action)) {
                        if (in_array($signal_data->action, ['buy', 'short', 'sell', 'cover'])) {
                            $platform_stats['metatrader']++;
                        } elseif (in_array($signal_data->action, ['BUY', 'SELL', 'CLOSE'])) {
                            $platform_stats['bingx']++;
                        }
                    }
                }
                ?>
                <div class="row text-center">
                    <div class="col-6">
                        <div class="border rounded p-3">
                            <span class="badge bg-info mb-2">BingX</span>
                            <h4 class="mb-0"><?= $platform_stats['bingx'] ?></h4>
                            <small class="text-muted">signals today</small>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="border rounded p-3">
                            <span class="badge bg-dark mb-2">MetaTrader</span>
                            <h4 class="mb-0"><?= $platform_stats['metatrader'] ?></h4>
                            <small class="text-muted">signals today</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- EA Activity Monitor (for MT only) -->
        <div class="card mb-3">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-robot me-1"></i>MT EA Activity
                </h5>
            </div>
            <div class="card-body">
                <?php if (empty($ea_activity)): ?>
                    <div class="text-center text-muted py-3">
                        <i class="fas fa-robot fa-2x mb-2"></i>
                        <p class="mb-1">No EA activity detected</p>
                        <small>EAs will appear here once they start polling for signals</small>
                    </div>
                <?php else: ?>
                    <?php foreach ($ea_activity as $activity): ?>
                        <?php 
                            $last_poll = strtotime($activity->last_poll);
                            $minutes_ago = floor((time() - $last_poll) / 60);
                            $status_class = $minutes_ago < 5 ? 'text-success' : ($minutes_ago < 15 ? 'text-warning' : 'text-danger');
                            $status_text = $minutes_ago < 5 ? 'Active' : ($minutes_ago < 15 ? 'Slow' : 'Inactive');
                            $badge_class = $status_class == 'text-success' ? 'bg-success' : ($status_class == 'text-warning' ? 'bg-warning' : 'bg-danger');
                        ?>
                        <div class="d-flex justify-content-between align-items-center mb-2 p-2 border rounded">
                            <div>
                                <strong><?= $activity->username ?></strong><br>
                                <small class="text-muted"><?= $activity->strategy_name ?></small>
                            </div>
                            <div class="text-end">
                                <span class="badge <?= $badge_class ?>">
                                    <?= $status_text ?>
                                </span><br>
                                <small class="text-muted"><?= $minutes_ago ?>m ago</small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-bolt me-1"></i>Quick Actions
                </h6>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <a href="<?= base_url('signals/management?status=failed') ?>" class="btn btn-outline-danger btn-sm">
                        <i class="fas fa-exclamation-triangle me-1"></i>View Failed Signals
                    </a>
                    <a href="<?= base_url('signals/logs') ?>" class="btn btn-outline-info btn-sm">
                        <i class="fas fa-file-alt me-1"></i>View Signal Logs
                    </a>
                    <a href="<?= base_url('strategies') ?>" class="btn btn-outline-secondary btn-sm">
                        <i class="fas fa-cog me-1"></i>Manage Strategies
                    </a>
                    <a href="<?= base_url('debug') ?>" class="btn btn-outline-warning btn-sm">
                        <i class="fas fa-bug me-1"></i>Debug Panel
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Auto-refresh JavaScript -->
<script>
let autoRefreshInterval;
let autoRefreshEnabled = true;

document.addEventListener('DOMContentLoaded', function() {
    // Auto-refresh every 15 seconds
    startAutoRefresh();
    
    // Auto-refresh toggle
    document.getElementById('auto-refresh-toggle').addEventListener('click', function() {
        if (autoRefreshEnabled) {
            stopAutoRefresh();
            this.innerHTML = '<i class="fas fa-play me-1"></i>Auto-refresh: OFF';
            this.classList.remove('btn-info');
            this.classList.add('btn-secondary');
        } else {
            startAutoRefresh();
            this.innerHTML = '<i class="fas fa-pause me-1"></i>Auto-refresh: ON';
            this.classList.remove('btn-secondary');
            this.classList.add('btn-info');
        }
        autoRefreshEnabled = !autoRefreshEnabled;
    });
    
    // Manual refresh button
    document.getElementById('refresh-signals-btn').addEventListener('click', function() {
        updateStats();
        location.reload(); // Simple refresh for now
    });
});

function startAutoRefresh() {
    autoRefreshInterval = setInterval(function() {
        updateStats();
    }, 15000); // 15 seconds
}

function stopAutoRefresh() {
    if (autoRefreshInterval) {
        clearInterval(autoRefreshInterval);
    }
}

function updateStats() {
    fetch('<?= base_url('signals/get_stats') ?>')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('pending-count').textContent = data.stats.pending;
                document.getElementById('processed-count').textContent = data.stats.processed;
                document.getElementById('failed-count').textContent = data.stats.failed;
                document.getElementById('success-rate').textContent = data.stats.success_rate + '%';
            }
        })
        .catch(error => console.error('Error updating stats:', error));
}
</script>