<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">
        <i class="fas fa-signal me-2"></i>Signal Management
    </h1>
    <div class="btn-group">
        <a href="<?= base_url('signals') ?>" class="btn btn-secondary">
            <i class="fas fa-arrow-left me-1"></i>Back to Overview
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
        <?= form_open('signals/management', ['method' => 'get', 'class' => 'row g-3']) ?>
            <div class="col-md-2">
                <label for="status" class="form-label">Status</label>
                <select class="form-select" id="status" name="status">
                    <option value="">All Status</option>
                    <option value="pending" <?= isset($filters['status']) && $filters['status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="processed" <?= isset($filters['status']) && $filters['status'] === 'processed' ? 'selected' : '' ?>>Processed</option>
                    <option value="failed" <?= isset($filters['status']) && $filters['status'] === 'failed' ? 'selected' : '' ?>>Failed</option>
                </select>
            </div>
            <div class="col-md-2">
                <label for="user_id" class="form-label">User</label>
                <select class="form-select" id="user_id" name="user_id">
                    <option value="">All Users</option>
                    <?php foreach ($users as $user): ?>
                        <option value="<?= $user->id ?>" <?= isset($filters['user_id']) && $filters['user_id'] == $user->id ? 'selected' : '' ?>>
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
                        <option value="<?= $strategy->id ?>" <?= isset($filters['strategy_id']) && $filters['strategy_id'] == $strategy->id ? 'selected' : '' ?>>
                            <?= $strategy->name ?> (<?= ucfirst($strategy->platform) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label for="date_from" class="form-label">Date From</label>
                <input type="date" class="form-control" id="date_from" name="date_from" value="<?= isset($filters['date_from']) ? $filters['date_from'] : '' ?>">
            </div>
            <div class="col-md-2">
                <label for="date_to" class="form-label">Date To</label>
                <input type="date" class="form-control" id="date_to" name="date_to" value="<?= isset($filters['date_to']) ? $filters['date_to'] : '' ?>">
            </div>
            <div class="col-12">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-search me-1"></i>Apply Filters
                </button>
                <a href="<?= base_url('signals/management') ?>" class="btn btn-secondary">
                    <i class="fas fa-undo me-1"></i>Reset
                </a>
                <span class="ms-3 text-muted">
                    <i class="fas fa-info-circle me-1"></i>
                    Currently showing MetaTrader signals only. BingX signals execute immediately without queuing.
                </span>
            </div>
        <?= form_close() ?>
    </div>
</div>

<!-- Statistics Summary -->
<div class="row mb-3">
    <div class="col-md-3">
        <div class="card">
            <div class="card-body text-center">
                <h6 class="text-muted mb-2">Total Signals</h6>
                <h3 class="mb-0"><?= count($signals) ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card">
            <div class="card-body text-center">
                <h6 class="text-muted mb-2">Pending</h6>
                <h3 class="mb-0 text-warning">
                    <?= count(array_filter($signals, function($s) { return $s->status === 'pending'; })) ?>
                </h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card">
            <div class="card-body text-center">
                <h6 class="text-muted mb-2">Processed</h6>
                <h3 class="mb-0 text-success">
                    <?= count(array_filter($signals, function($s) { return $s->status === 'processed'; })) ?>
                </h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card">
            <div class="card-body text-center">
                <h6 class="text-muted mb-2">Failed</h6>
                <h3 class="mb-0 text-danger">
                    <?= count(array_filter($signals, function($s) { return $s->status === 'failed'; })) ?>
                </h3>
            </div>
        </div>
    </div>
</div>

<!-- Signals Table -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">
            <i class="fas fa-list me-1"></i>Signals
            <span class="badge bg-secondary ms-2"><?= count($signals) ?> total</span>
        </h5>
        <div class="btn-group">
            <button class="btn btn-sm btn-outline-primary" onclick="location.reload()">
                <i class="fas fa-sync-alt me-1"></i>Refresh
            </button>
            <button class="btn btn-sm btn-outline-info" onclick="exportSignals()">
                <i class="fas fa-download me-1"></i>Export
            </button>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-striped table-hover mb-0">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Platform</th>
                        <th>User</th>
                        <th>Strategy</th>
                        <th>Signal Data</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>Processed</th>
                        <th>Response</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="signals-tbody">
                    <?php if (empty($signals)): ?>
                        <tr>
                            <td colspan="10" class="text-center py-3">No signals found</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($signals as $signal): ?>
                            <?php 
                                $signal_data = json_decode($signal->signal_data);
                                
                                // Determine platform based on action format
                                $platform = 'Unknown';
                                $platform_badge = 'bg-secondary';
                                if (isset($signal_data->action)) {
                                    // MetaTrader uses lowercase actions: buy, short, sell, cover
                                    // BingX uses uppercase: BUY, SELL, CLOSE
                                    if (in_array($signal_data->action, ['buy', 'short', 'sell', 'cover'])) {
                                        $platform = 'MetaTrader';
                                        $platform_badge = 'bg-dark';
                                    } elseif (in_array($signal_data->action, ['BUY', 'SELL', 'CLOSE'])) {
                                        $platform = 'BingX';
                                        $platform_badge = 'bg-info';
                                    }
                                }
                                
                                // Status badge colors
                                $status_class = 'bg-secondary';
                                switch ($signal->status) {
                                    case 'pending':
                                        $status_class = 'bg-warning';
                                        break;
                                    case 'processed':
                                        $status_class = 'bg-success';
                                        break;
                                    case 'failed':
                                        $status_class = 'bg-danger';
                                        break;
                                }
                                
                                // Action badge colors
                                $action_badge = 'bg-secondary';
                                if (isset($signal_data->action)) {
                                    $action_upper = strtoupper($signal_data->action);
                                    if (in_array($action_upper, ['BUY'])) $action_badge = 'bg-success';
                                    elseif (in_array($action_upper, ['SHORT', 'SELL'])) $action_badge = 'bg-danger';
                                    elseif (in_array($action_upper, ['CLOSE', 'COVER'])) $action_badge = 'bg-warning text-dark';
                                }
                            ?>
                            <tr>
                                <td><?= $signal->id ?></td>
                                <td>
                                    <span class="badge <?= $platform_badge ?>">
                                        <?= $platform ?>
                                    </span>
                                </td>
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
                                        <div class="mt-1">
                                            <span class="badge <?= $action_badge ?>">
                                                <?= strtoupper($signal_data->action) ?>
                                            </span>
                                            <?php if (isset($signal_data->ticker)): ?>
                                                <small class="text-muted ms-1"><?= $signal_data->ticker ?></small>
                                            <?php endif; ?>
                                            <?php if (isset($signal_data->position_id)): ?>
                                                <br><small class="text-muted">POS: <?= $signal_data->position_id ?></small>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge <?= $status_class ?>">
                                        <?= ucfirst($signal->status) ?>
                                    </span>
                                </td>
                                <td>
                                    <?= date('Y-m-d', strtotime($signal->created_at)) ?><br>
                                    <small class="text-muted"><?= date('H:i:s', strtotime($signal->created_at)) ?></small>
                                </td>
                                <td>
                                    <?php if ($signal->processed_at): ?>
                                        <?= date('Y-m-d', strtotime($signal->processed_at)) ?><br>
                                        <small class="text-muted"><?= date('H:i:s', strtotime($signal->processed_at)) ?></small>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
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
                                    <div class="btn-group btn-group-sm">
                                        <?php if ($signal->status == 'failed'): ?>
                                            <a href="<?= base_url('signals/retry_signal/' . $signal->id) ?>" 
                                               class="btn btn-warning" 
                                               title="Retry"
                                               onclick="return confirm('Are you sure you want to retry this signal?')">
                                                <i class="fas fa-redo"></i>
                                            </a>
                                        <?php endif; ?>
                                        <a href="<?= base_url('signals/delete_signal/' . $signal->id) ?>" 
                                           class="btn btn-danger" 
                                           onclick="return confirm('Are you sure you want to delete this signal?')" 
                                           title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </div>
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
                <h5 class="modal-title" id="signalDataModalLabel">
                    <i class="fas fa-code me-1"></i>Signal Data
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <button class="btn btn-sm btn-outline-secondary" onclick="copySignalData()">
                        <i class="fas fa-copy me-1"></i>Copy JSON
                    </button>
                    <button class="btn btn-sm btn-outline-primary" onclick="formatSignalData()">
                        <i class="fas fa-code me-1"></i>Format
                    </button>
                </div>
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
                <h5 class="modal-title" id="eaResponseModalLabel">
                    <i class="fas fa-robot me-1"></i>EA Response
                </h5>
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
let currentSignalData = '';

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
        // Don't refresh if a modal is open
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
    currentSignalData = signalData;
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

function copySignalData() {
    const content = document.getElementById('signalDataContent').textContent;
    navigator.clipboard.writeText(content).then(function() {
        // Show success message
        const btn = event.target.closest('button');
        const originalText = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-check me-1"></i>Copied!';
        btn.classList.add('btn-success');
        btn.classList.remove('btn-outline-secondary');
        
        setTimeout(function() {
            btn.innerHTML = originalText;
            btn.classList.remove('btn-success');
            btn.classList.add('btn-outline-secondary');
        }, 2000);
    });
}

function formatSignalData() {
    try {
        const content = document.getElementById('signalDataContent').textContent;
        const parsed = JSON.parse(content);
        document.getElementById('signalDataContent').textContent = JSON.stringify(parsed, null, 2);
    } catch (e) {
        console.error('Failed to format JSON:', e);
    }
}

function exportSignals() {
    // Get current filters from URL
    const urlParams = new URLSearchParams(window.location.search);
    const filters = Object.fromEntries(urlParams.entries());
    
    // Create CSV content
    let csv = 'ID,Platform,User,Strategy,Action,Symbol,Status,Created,Processed\n';
    
    const rows = document.querySelectorAll('#signals-tbody tr');
    rows.forEach(row => {
        if (!row.querySelector('.text-center')) { // Skip "no signals" row
            const cells = row.querySelectorAll('td');
            if (cells.length > 0) {
                const platform = cells[1].textContent.trim();
                const user = cells[2].textContent.trim();
                const strategy = cells[3].querySelector('strong').textContent.trim();
                const actionBadge = cells[4].querySelector('.badge');
                const action = actionBadge ? actionBadge.textContent.trim() : '';
                const symbolElement = cells[4].querySelector('small.text-muted');
                const symbol = symbolElement ? symbolElement.textContent.trim() : '';
                const status = cells[5].textContent.trim();
                const created = cells[6].textContent.replace(/\s+/g, ' ').trim();
                const processed = cells[7].textContent.replace(/\s+/g, ' ').trim();
                
                csv += `${cells[0].textContent},${platform},${user},${strategy},${action},${symbol},${status},"${created}","${processed}"\n`;
            }
        }
    });
    
    // Download CSV
    const blob = new Blob([csv], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'signals_export_' + new Date().toISOString().split('T')[0] + '.csv';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    window.URL.revokeObjectURL(url);
}
</script>

<style>
.table td {
    vertical-align: middle;
}

.btn-group-sm > .btn {
    padding: 0.25rem 0.5rem;
    font-size: 0.875rem;
}

#signalDataContent {
    max-height: 500px;
    overflow-y: auto;
    font-size: 0.875rem;
    font-family: 'Courier New', monospace;
}

#eaResponseContent {
    max-height: 400px;
    overflow-y: auto;
    font-size: 0.875rem;
}
</style>