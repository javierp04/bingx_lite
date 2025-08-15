<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">
        <i class="fas fa-file-alt me-2"></i>Signal Logs
    </h1>
    <div class="btn-group">
        <a href="<?= base_url('signals') ?>" class="btn btn-secondary">
            <i class="fas fa-arrow-left me-1"></i>Back to Signals
        </a>
        <a href="<?= base_url('systemlogs') ?>" class="btn btn-info">
            <i class="fas fa-history me-1"></i>All System Logs
        </a>
    </div>
</div>

<!-- Filter Form -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="fas fa-filter me-1"></i>Filter Logs
        </h5>
    </div>
    <div class="card-body">
        <?= form_open('signals/logs', ['method' => 'get', 'class' => 'row g-3']) ?>
            <div class="col-md-3">
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
                <label for="date_from" class="form-label">Date From</label>
                <input type="date" class="form-control" id="date_from" name="date_from" value="<?= isset($filters['date_from']) ? $filters['date_from'] : '' ?>">
            </div>
            <div class="col-md-3">
                <label for="date_to" class="form-label">Date To</label>
                <input type="date" class="form-control" id="date_to" name="date_to" value="<?= isset($filters['date_to']) ? $filters['date_to'] : '' ?>">
            </div>
            <div class="col-md-3 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="fas fa-search me-1"></i>Apply Filters
                </button>
            </div>
        <?= form_close() ?>
    </div>
</div>

<!-- Log Statistics by Platform -->
<div class="row mb-4">
    <div class="col-md-6">
        <!-- MetaTrader Logs -->
        <div class="card">
            <div class="card-header bg-dark text-white">
                <h6 class="mb-0">
                    <i class="fas fa-chart-area me-1"></i>MetaTrader Signal Logs
                </h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-6">
                        <div class="text-center">
                            <h6 class="text-muted">Debug Logs</h6>
                            <h4 class="text-info">
                                <?= count(array_filter($logs, function($log) { 
                                    return $log->action == 'mt_webhook_debug' || $log->action == 'mt_debug_test'; 
                                })) ?>
                            </h4>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="text-center">
                            <h6 class="text-muted">Error Logs</h6>
                            <h4 class="text-danger">
                                <?= count(array_filter($logs, function($log) { 
                                    return $log->action == 'mt_webhook_error' || $log->action == 'mt_signal_failed'; 
                                })) ?>
                            </h4>
                        </div>
                    </div>
                </div>
                <div class="row mt-3">
                    <div class="col-6">
                        <div class="text-center">
                            <h6 class="text-muted">Queued</h6>
                            <h4 class="text-warning">
                                <?= count(array_filter($logs, function($log) { 
                                    return $log->action == 'mt_signal_queued'; 
                                })) ?>
                            </h4>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="text-center">
                            <h6 class="text-muted">Processed</h6>
                            <h4 class="text-success">
                                <?= count(array_filter($logs, function($log) { 
                                    return $log->action == 'mt_signal_processed'; 
                                })) ?>
                            </h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <!-- BingX Logs -->
        <div class="card">
            <div class="card-header bg-info text-white">
                <h6 class="mb-0">
                    <i class="fas fa-bitcoin me-1"></i>BingX Signal Logs
                </h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-6">
                        <div class="text-center">
                            <h6 class="text-muted">Debug Logs</h6>
                            <h4 class="text-info">
                                <?= count(array_filter($logs, function($log) { 
                                    return $log->action == 'webhook_debug' || $log->action == 'bingx_debug_test'; 
                                })) ?>
                            </h4>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="text-center">
                            <h6 class="text-muted">Error Logs</h6>
                            <h4 class="text-danger">
                                <?= count(array_filter($logs, function($log) { 
                                    return $log->action == 'webhook_error'; 
                                })) ?>
                            </h4>
                        </div>
                    </div>
                </div>
                <div class="row mt-3">
                    <div class="col-6">
                        <div class="text-center">
                            <h6 class="text-muted">Trades Opened</h6>
                            <h4 class="text-success">
                                <?= count(array_filter($logs, function($log) { 
                                    return $log->action == 'open_trade' && strpos($log->description, 'via webhook') !== false; 
                                })) ?>
                            </h4>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="text-center">
                            <h6 class="text-muted">Trades Closed</h6>
                            <h4 class="text-warning">
                                <?= count(array_filter($logs, function($log) { 
                                    return ($log->action == 'close_trade' || $log->action == 'partial_close_trade') 
                                           && strpos($log->description, 'via webhook') !== false; 
                                })) ?>
                            </h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Logs Table -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">
            <i class="fas fa-list me-1"></i>Signal Activity Logs
        </h5>
        <span class="badge bg-secondary"><?= count($logs) ?> logs</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-striped table-hover mb-0">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Platform</th>
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
                            <td colspan="8" class="text-center py-3">No logs found</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($logs as $log): ?>
                            <?php
                                // Determine platform based on action prefix
                                $platform = 'Unknown';
                                $platform_badge = 'bg-secondary';
                                
                                if (strpos($log->action, 'mt_') === 0) {
                                    $platform = 'MetaTrader';
                                    $platform_badge = 'bg-dark';
                                } elseif (in_array($log->action, ['webhook_debug', 'webhook_error', 'bingx_debug_test', 'open_trade', 'close_trade', 'partial_close_trade'])) {
                                    $platform = 'BingX';
                                    $platform_badge = 'bg-info';
                                }
                            ?>
                            <tr>
                                <td><?= $log->id ?></td>
                                <td>
                                    <span class="badge <?= $platform_badge ?>">
                                        <?= $platform ?>
                                    </span>
                                </td>
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
                                    <span class="badge <?= get_signal_log_badge_class($log->action) ?>">
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
                                <td>
                                    <?= date('Y-m-d', strtotime($log->created_at)) ?><br>
                                    <small class="text-muted"><?= date('H:i:s', strtotime($log->created_at)) ?></small>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-info" onclick="showLogDetails(<?= $log->id ?>, <?= htmlspecialchars(json_encode($log->description), ENT_QUOTES) ?>, <?= htmlspecialchars(json_encode($log->action), ENT_QUOTES) ?>, '<?= $platform ?>')">
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
                <h5 class="modal-title" id="logDetailsModalLabel">
                    <i class="fas fa-file-alt me-1"></i>Log Details
                    <span id="platformBadge" class="badge ms-2"></span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-3">
                        <strong>Log ID:</strong>
                        <p id="logId"></p>
                        
                        <strong>Action:</strong>
                        <p id="logAction"></p>
                        
                        <strong>Platform:</strong>
                        <p id="logPlatform"></p>
                    </div>
                    <div class="col-md-9">
                        <strong>Description:</strong>
                        <pre id="logDescription" class="bg-light p-3 rounded"></pre>
                        
                        <div id="signalAnalysis" style="display: none;">
                            <strong>Signal Data Analysis:</strong>
                            <div id="signalContent" class="mt-2"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function showLogDetails(logId, description, action, platform) {
    document.getElementById('logId').textContent = logId;
    document.getElementById('logAction').textContent = action;
    document.getElementById('logPlatform').textContent = platform;
    
    // Set platform badge
    const platformBadge = document.getElementById('platformBadge');
    platformBadge.textContent = platform;
    platformBadge.className = 'badge ms-2 ' + (platform === 'MetaTrader' ? 'bg-dark' : 'bg-info');
    
    // Check if this is webhook/signal data
    if ((action.includes('webhook') || action.includes('signal')) && description.includes('. Data: ')) {
        const parts = description.split('. Data: ');
        const message = parts[0];
        const data = parts[1];
        
        document.getElementById('logDescription').textContent = message;
        
        // Show signal analysis
        const signalAnalysis = document.getElementById('signalAnalysis');
        const signalContent = document.getElementById('signalContent');
        
        try {
            const parsedData = JSON.parse(data);
            
            // Create analysis table
            let analysisHtml = `
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0">Signal/Webhook Data</h6>
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <table class="table table-sm">
                                    <tr><th>Strategy ID:</th><td>${parsedData.strategy_id || 'N/A'}</td></tr>
                                    <tr><th>Action:</th><td><span class="badge ${getActionBadgeClass(parsedData.action)}">${parsedData.action || 'N/A'}</span></td></tr>
                                    <tr><th>Symbol:</th><td>${parsedData.ticker || 'N/A'}</td></tr>
                                    <tr><th>Timeframe:</th><td>${parsedData.timeframe || 'N/A'}</td></tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <table class="table table-sm">
                                    <tr><th>Quantity:</th><td>${parsedData.quantity || 'N/A'}</td></tr>
                                    <tr><th>Position ID:</th><td>${parsedData.position_id || 'N/A'}</td></tr>
                                    ${parsedData.leverage ? '<tr><th>Leverage:</th><td>' + parsedData.leverage + 'x</td></tr>' : ''}
                                    ${parsedData.environment ? '<tr><th>Environment:</th><td>' + parsedData.environment + '</td></tr>' : ''}
                                </table>
                            </div>
                        </div>
                        <h6>Full JSON Data:</h6>
                        <pre class="bg-light p-3 rounded">${JSON.stringify(parsedData, null, 2)}</pre>
                    </div>
                </div>
            `;
            
            signalContent.innerHTML = analysisHtml;
            signalAnalysis.style.display = 'block';
        } catch (e) {
            signalContent.innerHTML = `
                <div class="alert alert-warning">
                    <strong>Raw Data:</strong><br>
                    <pre>${data}</pre>
                </div>
            `;
            signalAnalysis.style.display = 'block';
        }
    } else {
        document.getElementById('logDescription').textContent = description;
        document.getElementById('signalAnalysis').style.display = 'none';
    }
    
    const modal = new bootstrap.Modal(document.getElementById('logDetailsModal'));
    modal.show();
}

function getActionBadgeClass(action) {
    if (!action) return 'bg-secondary';
    
    const actionUpper = action.toUpperCase();
    if (actionUpper === 'BUY') return 'bg-success';
    if (actionUpper === 'SHORT' || actionUpper === 'SELL') return 'bg-danger';
    if (actionUpper === 'CLOSE' || actionUpper === 'COVER') return 'bg-warning text-dark';
    return 'bg-secondary';
}
</script>

<?php
// Helper function for signal log badge classes
function get_signal_log_badge_class($action) {
    switch ($action) {
        // MetaTrader logs
        case 'mt_webhook_debug':
        case 'mt_debug_test':
        case 'webhook_debug':
        case 'bingx_debug_test':
            return 'bg-info';
            
        case 'mt_webhook_error':
        case 'mt_signal_failed':
        case 'webhook_error':
            return 'bg-danger';
            
        case 'mt_signal_queued':
            return 'bg-warning text-dark';
            
        case 'mt_signal_processed':
        case 'mt_signal_retry':
        case 'open_trade':
            return 'bg-success';
            
        case 'close_trade':
        case 'partial_close_trade':
            return 'bg-warning text-dark';
            
        case 'mt_signal_delete':
            return 'bg-danger';
            
        default:
            return 'bg-secondary';
    }
}
?>

<style>
.description-cell {
    max-width: 300px;
    word-wrap: break-word;
}

#logDescription, #signalContent pre {
    max-height: 400px;
    overflow-y: auto;
    font-size: 0.875rem;
}

.table-sm th {
    width: 40%;
}
</style>