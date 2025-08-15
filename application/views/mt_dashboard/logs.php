<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">
        <i class="fas fa-file-alt me-2"></i>MetaTrader Logs
    </h1>
    <a href="<?= base_url('mt_dashboard') ?>" class="btn btn-secondary">
        <i class="fas fa-arrow-left me-1"></i>Back to Dashboard
    </a>
</div>

<!-- Filter Form -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="fas fa-filter me-1"></i>Filter Logs
        </h5>
    </div>
    <div class="card-body">
        <?= form_open('mt_dashboard/logs', ['method' => 'get', 'class' => 'row g-3']) ?>
            <div class="col-md-3">
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
                <label for="date_from" class="form-label">Date From</label>
                <input type="date" class="form-control" id="date_from" name="date_from" value="<?= $filters['date_from'] ?>">
            </div>
            <div class="col-md-3">
                <label for="date_to" class="form-label">Date To</label>
                <input type="date" class="form-control" id="date_to" name="date_to" value="<?= $filters['date_to'] ?>">
            </div>
            <div class="col-md-3 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="fas fa-search me-1"></i>Apply Filters
                </button>
            </div>
        <?= form_close() ?>
    </div>
</div>

<!-- Log Statistics -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card bg-info bg-opacity-10">
            <div class="card-body text-center">
                <h6 class="text-muted">Debug Logs</h6>
                <h4 class="text-info">
                    <?= count(array_filter($logs, function($log) { return $log->action == 'mt_webhook_debug'; })) ?>
                </h4>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-danger bg-opacity-10">
            <div class="card-body text-center">
                <h6 class="text-muted">Error Logs</h6>
                <h4 class="text-danger">
                    <?= count(array_filter($logs, function($log) { return $log->action == 'mt_webhook_error'; })) ?>
                </h4>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-success bg-opacity-10">
            <div class="card-body text-center">
                <h6 class="text-muted">Signals Queued</h6>
                <h4 class="text-success">
                    <?= count(array_filter($logs, function($log) { return $log->action == 'mt_signal_queued'; })) ?>
                </h4>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-warning bg-opacity-10">
            <div class="card-body text-center">
                <h6 class="text-muted">Processed</h6>
                <h4 class="text-warning">
                    <?= count(array_filter($logs, function($log) { return $log->action == 'mt_signal_processed'; })) ?>
                </h4>
            </div>
        </div>
    </div>
</div>

<!-- Logs Table -->
<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-striped table-hover mb-0">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>User</th>
                        <th>Action</th>
                        <th>Description</th>
                        <th>IP Address</th>
                        <th>Timestamp</th>
                        <th>Details</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-3">No logs found</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td><?= $log->id ?></td>
                                <td>
                                    <?php if ($log->user_id): ?>
                                        <?php 
                                            $user = $this->User_model->get_user_by_id($log->user_id);
                                            echo $user ? $user->username : 'ID: ' . $log->user_id;
                                        ?>
                                    <?php else: ?>
                                        <span class="text-muted">System</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge <?= get_mt_log_badge_class($log->action) ?>">
                                        <?= $log->action ?>
                                    </span>
                                </td>
                                <td class="description-cell">
                                    <?php 
                                        $truncated_desc = (strlen($log->description) > 80) ? 
                                            substr($log->description, 0, 80) . '...' : $log->description;
                                        echo htmlspecialchars($truncated_desc);
                                    ?>
                                </td>
                                <td><?= $log->ip_address ?></td>
                                <td><?= date('Y-m-d H:i:s', strtotime($log->created_at)) ?></td>
                                <td>
                                    <button class="btn btn-sm btn-info" onclick="showLogDetails(<?= $log->id ?>, <?= htmlspecialchars(json_encode($log->description), ENT_QUOTES) ?>, <?= htmlspecialchars(json_encode($log->action), ENT_QUOTES) ?>)">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal for Log Details -->
<div class="modal fade" id="logDetailsModal" tabindex="-1" aria-labelledby="logDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="logDetailsModalLabel">Log Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-3">
                        <strong>Log ID:</strong>
                        <p id="logId"></p>
                        
                        <strong>Action:</strong>
                        <p id="logAction"></p>
                    </div>
                    <div class="col-md-9">
                        <strong>Description:</strong>
                        <pre id="logDescription" class="bg-light p-3 rounded"></pre>
                        
                        <div id="webhookAnalysis" style="display: none;">
                            <strong>Webhook Analysis:</strong>
                            <div id="webhookContent" class="mt-2"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function showLogDetails(logId, description, action) {
    document.getElementById('logId').textContent = logId;
    document.getElementById('logAction').textContent = action;
    
    // Try to format JSON if it's webhook data
    if (action.includes('webhook') && description.includes('. Data: ')) {
        const parts = description.split('. Data: ');
        const message = parts[0];
        const data = parts[1];
        
        document.getElementById('logDescription').textContent = message;
        
        // Show webhook analysis
        const webhookAnalysis = document.getElementById('webhookAnalysis');
        const webhookContent = document.getElementById('webhookContent');
        
        try {
            const parsedData = JSON.parse(data);
            webhookContent.innerHTML = `
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0">Webhook Data</h6>
                    </div>
                    <div class="card-body">
                        <pre class="bg-light p-3 rounded">${JSON.stringify(parsedData, null, 2)}</pre>
                    </div>
                </div>
            `;
            webhookAnalysis.style.display = 'block';
        } catch (e) {
            webhookContent.innerHTML = `
                <div class="alert alert-warning">
                    <strong>Raw Data:</strong><br>
                    <pre>${data}</pre>
                </div>
            `;
            webhookAnalysis.style.display = 'block';
        }
    } else {
        document.getElementById('logDescription').textContent = description;
        document.getElementById('webhookAnalysis').style.display = 'none';
    }
    
    const modal = new bootstrap.Modal(document.getElementById('logDetailsModal'));
    modal.show();
}
</script>

<?php
// Helper function for MT log badge classes
function get_mt_log_badge_class($action) {
    switch ($action) {
        case 'mt_webhook_debug':
        case 'mt_debug_test':
            return 'bg-info';
        case 'mt_webhook_error':
        case 'mt_signal_failed':
            return 'bg-danger';
        case 'mt_signal_queued':
            return 'bg-warning text-dark';
        case 'mt_signal_processed':
        case 'mt_signal_retry':
            return 'bg-success';
        default:
            return 'bg-secondary';
    }
}
?>