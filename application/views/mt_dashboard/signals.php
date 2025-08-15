<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">
        <i class="fas fa-signal me-2"></i>MetaTrader Signals Management
    </h1>
    <div class="btn-group">
        <a href="<?= base_url('mt_dashboard') ?>" class="btn btn-secondary">
            <i class="fas fa-arrow-left me-1"></i>Back to Dashboard
        </a>
        <button class="btn btn-primary" id="auto-refresh-toggle">
            <i class="fas fa-pause me-1"></i>Auto-refresh: ON
        </button>
    </div>
</div>

<!-- Filter Form -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="fas fa-filter me-1"></i>Filter Signals
        </h5>
    </div>
    <div class="card-body">
        <?= form_open('mt_dashboard/signals', ['method' => 'get', 'class' => 'row g-3']) ?>
            <div class="col-md-2">
                <label for="status" class="form-label">Status</label>
                <select class="form-select" id="status" name="status">
                    <option value="">All Status</option>
                    <option value="pending" <?= $filters['status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="processed" <?= $filters['status'] === 'processed' ? 'selected' : '' ?>>Processed</option>
                    <option value="failed" <?= $filters['status'] === 'failed' ? 'selected' : '' ?>>Failed</option>
                </select>
            </div>
            <div class="col-md-2">
                <label for="user_id" class="form-label">User</label>
                <select class="form-select" id="user_id" name="user_id">
                    <option value="">All Users</option>
                    <?php foreach ($users as $user): ?>
                        <option value="<?= $user->id ?>" <?= $filters['user_id'] == $user->id ? 'selected' : '' ?>>
                            <?= $user->username ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label for="strategy_id" class="form-label">Strategy</label>
                <select class="form-select" id="strategy_id" name="strategy_id">
                    <option value="">All Strategies</option>
                    <?php foreach ($strategies as $strategy): ?>
                        <option value="<?= $strategy->id ?>" <?= $filters['strategy_id'] == $strategy->id ? 'selected' : '' ?>>
                            <?= $strategy->name ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label for="date_from" class="form-label">Date From</label>
                <input type="date" class="form-control" id="date_from" name="date_from" value="<?= $filters['date_from'] ?>">
            </div>
            <div class="col-md-2">
                <label for="date_to" class="form-label">Date To</label>
                <input type="date" class="form-control" id="date_to" name="date_to" value="<?= $filters['date_to'] ?>">
            </div>
            <div class="col-12">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-search me-1"></i>Apply Filters
                </button>
                <a href="<?= base_url('mt_dashboard/signals') ?>" class="btn btn-secondary">
                    <i class="fas fa-undo me-1"></i>Reset
                </a>
            </div>
        <?= form_close() ?>
    </div>
</div>

<!-- Signals Table -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Signals</h5>
        <span class="badge bg-secondary" id="signal-count"><?= count($signals) ?> signals</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-striped table-hover mb-0">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>User</th>
                        <th>Strategy</th>
                        <th>Signal Data</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>Processed</th>
                        <th>EA Response</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="signals-tbody">
                    <?php if (empty($signals)): ?>
                        <tr>
                            <td colspan="9" class="text-center py-3">No signals found</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($signals as $signal): ?>
                            <?php 
                                $signal_data = json_decode($signal->signal_data);
                                $status_class = $signal->status == 'processed' ? 'bg-success' : 
                                              ($signal->status == 'failed' ? 'bg-danger' : 'bg-warning');
                            ?>
                            <tr>
                                <td><?= $signal->id ?></td>
                                <td><?= $signal->username ?></td>
                                <td>
                                    <strong><?= $signal->strategy_name ?></strong><br>
                                    <small class="text-muted"><?= $signal->strategy_external_id ?></small>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-outline-info" onclick="showSignalData(<?= $signal->id ?>, '<?= htmlspecialchars($signal->signal_data, ENT_QUOTES) ?>')">
                                        <i class="fas fa-eye"></i> View JSON
                                    </button>
                                    <?php if (isset($signal_data->action)): ?>
                                        <?php
                                            // Badge colors for MT actions: buy, short, sell, cover
                                            $badge_class = 'bg-secondary';
                                            if ($signal_data->action == 'buy') $badge_class = 'bg-success';
                                            elseif ($signal_data->action == 'short') $badge_class = 'bg-danger';
                                            elseif ($signal_data->action == 'sell') $badge_class = 'bg-warning text-dark';
                                            elseif ($signal_data->action == 'cover') $badge_class = 'bg-info';
                                        ?>
                                        <br><span class="badge <?= $badge_class ?>">
                                            <?= strtoupper($signal_data->action) ?>
                                        </span>
                                        <?php if (isset($signal_data->ticker)): ?>
                                            <small class="text-muted"><?= $signal_data->ticker ?></small>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge <?= $status_class ?>">
                                        <?= ucfirst($signal->status) ?>
                                    </span>
                                </td>
                                <td><?= date('Y-m-d H:i:s', strtotime($signal->created_at)) ?></td>
                                <td><?= $signal->processed_at ? date('Y-m-d H:i:s', strtotime($signal->processed_at)) : '-' ?></td>
                                <td>
                                    <?php if ($signal->ea_response): ?>
                                        <button class="btn btn-sm btn-outline-secondary" onclick="showEaResponse(<?= $signal->id ?>, '<?= htmlspecialchars($signal->ea_response, ENT_QUOTES) ?>')">
                                            <i class="fas fa-comment"></i> View
                                        </button>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($signal->status == 'failed'): ?>
                                        <a href="<?= base_url('mt_dashboard/retry_signal/' . $signal->id) ?>" class="btn btn-sm btn-warning" title="Retry">
                                            <i class="fas fa-redo"></i>
                                        </a>
                                    <?php endif; ?>
                                    <a href="<?= base_url('mt_dashboard/delete_signal/' . $signal->id) ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this signal?')" title="Delete">
                                        <i class="fas fa-trash"></i>
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

<!-- Modal for Signal Data -->
<div class="modal fade" id="signalDataModal" tabindex="-1" aria-labelledby="signalDataModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="signalDataModalLabel">Signal Data</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <pre id="signalDataContent" class="bg-light p-3 rounded"></pre>
            </div>
        </div>
    </div>
</div>

<!-- Modal for EA Response -->
<div class="modal fade" id="eaResponseModal" tabindex="-1" aria-labelledby="eaResponseModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="eaResponseModalLabel">EA Response</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <pre id="eaResponseContent" class="bg-light p-3 rounded"></pre>
            </div>
        </div>
    </div>
</div>

<script>
let autoRefreshInterval;
let autoRefreshEnabled = true;

document.addEventListener('DOMContentLoaded', function() {
    // Auto-refresh every 10 seconds
    startAutoRefresh();
    
    // Auto-refresh toggle
    document.getElementById('auto-refresh-toggle').addEventListener('click', function() {
        if (autoRefreshEnabled) {
            stopAutoRefresh();
            this.innerHTML = '<i class="fas fa-play me-1"></i>Auto-refresh: OFF';
            this.classList.remove('btn-primary');
            this.classList.add('btn-secondary');
        } else {
            startAutoRefresh();
            this.innerHTML = '<i class="fas fa-pause me-1"></i>Auto-refresh: ON';
            this.classList.remove('btn-secondary');
            this.classList.add('btn-primary');
        }
        autoRefreshEnabled = !autoRefreshEnabled;
    });
});

function startAutoRefresh() {
    autoRefreshInterval = setInterval(function() {
        // Simple page reload - en una implementación más avanzada podrías hacer AJAX
        if (autoRefreshEnabled && !document.querySelector('.modal.show')) {
            location.reload();
        }
    }, 10000); // 10 seconds
}

function stopAutoRefresh() {
    if (autoRefreshInterval) {
        clearInterval(autoRefreshInterval);
    }
}

function showSignalData(signalId, signalData) {
    try {
        const parsedData = JSON.parse(signalData);
        document.getElementById('signalDataContent').textContent = JSON.stringify(parsedData, null, 2);
    } catch (e) {
        document.getElementById('signalDataContent').textContent = signalData;
    }
    
    const modal = new bootstrap.Modal(document.getElementById('signalDataModal'));
    modal.show();
}

function showEaResponse(signalId, response) {
    document.getElementById('eaResponseContent').textContent = response;
    
    const modal = new bootstrap.Modal(document.getElementById('eaResponseModal'));
    modal.show();
}
</script>