<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">
        <i class="fas fa-chart-area me-2"></i>MetaTrader Dashboard
    </h1>
    <div class="btn-group">
        <a href="<?= base_url('mt_dashboard/signals') ?>" class="btn btn-primary">
            <i class="fas fa-signal me-1"></i>Manage Signals
        </a>
        <a href="<?= base_url('mt_dashboard/debug') ?>" class="btn btn-info">
            <i class="fas fa-bug me-1"></i>Debug Panel
        </a>
    </div>
</div>

<!-- Statistics Cards -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card bg-warning bg-opacity-10">
            <div class="card-body text-center">
                <h5 class="card-title text-muted">Pending Signals</h5>
                <h2 class="mb-0 text-warning" id="pending-count"><?= $stats['pending'] ?></h2>
                <small class="text-muted">Awaiting EA processing</small>
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
                <button class="btn btn-sm btn-outline-primary" id="refresh-signals-btn">
                    <i class="fas fa-sync-alt me-1"></i>Refresh
                </button>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped table-hover mb-0">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Strategy</th>
                                <th>Action</th>
                                <th>Status</th>
                                <th>Created</th>
                                <th>Processed</th>
                            </tr>
                        </thead>
                        <tbody id="recent-signals-tbody">
                            <?php if (empty($recent_signals)): ?>
                                <tr>
                                    <td colspan="6" class="text-center py-3">No recent signals</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($recent_signals as $signal): ?>
                                    <?php 
                                        $signal_data = json_decode($signal->signal_data);
                                        $status_class = $signal->status == 'processed' ? 'bg-success' : 
                                                      ($signal->status == 'failed' ? 'bg-danger' : 'bg-warning');
                                    ?>
                                    <tr>
                                        <td><?= $signal->id ?></td>
                                        <td><?= $signal->strategy_name ?></td>
                                        <td>
                                            <?php if (isset($signal_data->action)): ?>
                                                <span class="badge <?= $signal_data->action == 'BUY' ? 'bg-success' : ($signal_data->action == 'SELL' ? 'bg-danger' : 'bg-secondary') ?>">
                                                    <?= $signal_data->action ?>
                                                </span>
                                            <?php else: ?>
                                                N/A
                                            <?php endif; ?>
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
    
    <!-- EA Activity Monitor -->
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-robot me-1"></i>EA Activity
                </h5>
            </div>
            <div class="card-body">
                <?php if (empty($ea_activity)): ?>
                    <div class="text-center text-muted py-3">
                        <i class="fas fa-robot fa-2x mb-2"></i>
                        <p>No EA activity detected</p>
                        <small>EAs will appear here once they start polling for signals</small>
                    </div>
                <?php else: ?>
                    <?php foreach ($ea_activity as $activity): ?>
                        <?php 
                            $last_poll = strtotime($activity->last_poll);
                            $minutes_ago = floor((time() - $last_poll) / 60);
                            $status_class = $minutes_ago < 5 ? 'text-success' : ($minutes_ago < 15 ? 'text-warning' : 'text-danger');
                            $status_text = $minutes_ago < 5 ? 'Active' : ($minutes_ago < 15 ? 'Slow' : 'Inactive');
                        ?>
                        <div class="d-flex justify-content-between align-items-center mb-2 p-2 border rounded">
                            <div>
                                <strong><?= $activity->username ?></strong><br>
                                <small class="text-muted"><?= $activity->strategy_name ?></small>
                            </div>
                            <div class="text-end">
                                <span class="badge <?= $status_class == 'text-success' ? 'bg-success' : ($status_class == 'text-warning' ? 'bg-warning' : 'bg-danger') ?>">
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
        <div class="card mt-3">
            <div class="card-header">
                <h6 class="mb-0">Quick Actions</h6>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <a href="<?= base_url('mt_dashboard/signals?status=failed') ?>" class="btn btn-outline-danger btn-sm">
                        <i class="fas fa-exclamation-triangle me-1"></i>View Failed Signals
                    </a>
                    <a href="<?= base_url('mt_dashboard/logs') ?>" class="btn btn-outline-info btn-sm">
                        <i class="fas fa-file-alt me-1"></i>View Logs
                    </a>
                    <a href="<?= base_url('strategies') ?>" class="btn btn-outline-secondary btn-sm">
                        <i class="fas fa-cog me-1"></i>Manage Strategies
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Auto-refresh JavaScript -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Auto-refresh every 30 seconds
    setInterval(updateStats, 30000);
    
    // Manual refresh button
    document.getElementById('refresh-signals-btn').addEventListener('click', function() {
        updateStats();
        location.reload(); // Simple refresh for now
    });
    
    function updateStats() {
        fetch('<?= base_url('mt_dashboard/get_signal_stats') ?>')
            .then(response => response.json())
            .then(data => {
                document.getElementById('pending-count').textContent = data.pending;
                document.getElementById('processed-count').textContent = data.processed;
                document.getElementById('failed-count').textContent = data.failed;
                document.getElementById('success-rate').textContent = data.success_rate + '%';
            })
            .catch(error => console.error('Error updating stats:', error));
    }
});
</script>