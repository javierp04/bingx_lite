<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">
        <i class="fas fa-paper-plane me-2"></i>Telegram Signals
    </h1>
    <div class="btn-group">
        <button class="btn btn-outline-primary" onclick="location.reload()">
            <i class="fas fa-sync-alt me-1"></i>Refresh
        </button>
        <?php if ($this->session->userdata('role') === 'admin'): ?>
            <button class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#cleanupModal">
                <i class="fas fa-broom me-1"></i>Cleanup
            </button>
        <?php endif; ?>
    </div>
</div>

<!-- Stats Cards -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="card-title text-muted">Total Signals</h6>
                        <h4 class="mb-0"><?= $stats['total'] ?></h4>
                    </div>
                    <div class="text-primary">
                        <i class="fas fa-paper-plane fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="card-title text-muted">Pending</h6>
                        <h4 class="mb-0 text-warning"><?= $stats['pending'] ?></h4>
                    </div>
                    <div class="text-warning">
                        <i class="fas fa-clock fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="card-title text-muted">Processed</h6>
                        <h4 class="mb-0 text-success"><?= $stats['processed'] ?></h4>
                    </div>
                    <div class="text-success">
                        <i class="fas fa-check-circle fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="card-title text-muted">Last 24h</h6>
                        <h4 class="mb-0 text-info"><?= $stats['last_24h'] ?></h4>
                    </div>
                    <div class="text-info">
                        <i class="fas fa-calendar-day fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
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
        <?= form_open('telegram_signals', ['method' => 'get', 'class' => 'row g-3']) ?>
            <div class="col-md-3">
                <label for="ticker_symbol" class="form-label">Ticker</label>
                <select class="form-select" id="ticker_symbol" name="ticker_symbol">
                    <option value="">All Tickers</option>
                    <?php foreach ($available_tickers as $ticker): ?>
                        <option value="<?= $ticker->symbol ?>" <?= $filters['ticker_symbol'] === $ticker->symbol ? 'selected' : '' ?>>
                            <?= $ticker->symbol ?> - <?= $ticker->name ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label for="processed" class="form-label">Status</label>
                <select class="form-select" id="processed" name="processed">
                    <option value="">All Status</option>
                    <option value="0" <?= $filters['processed'] === '0' ? 'selected' : '' ?>>Pending</option>
                    <option value="1" <?= $filters['processed'] === '1' ? 'selected' : '' ?>>Processed</option>
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
            <div class="col-md-3">
                <label class="form-label">&nbsp;</label>
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search me-1"></i>Filter
                    </button>
                    <a href="<?= base_url('telegram_signals') ?>" class="btn btn-secondary">
                        <i class="fas fa-undo me-1"></i>Reset
                    </a>
                </div>
            </div>
        <?= form_close() ?>
    </div>
</div>

<div class="row">
    <!-- Signals Table -->
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-list me-1"></i>Telegram Signals
                    <span class="badge bg-secondary ms-2"><?= count($signals) ?></span>
                </h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped table-hover mb-0">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Ticker</th>
                                <th>Status</th>
                                <th>Image</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($signals)): ?>
                                <tr>
                                    <td colspan="6" class="text-center py-3">No signals found</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($signals as $signal): ?>
                                    <tr>
                                        <td><?= $signal->id ?></td>
                                        <td>
                                            <strong><?= $signal->ticker_symbol ?></strong><br>
                                            <small class="text-muted"><?= $signal->ticker_name ?></small>
                                        </td>
                                        <td>
                                            <span class="badge <?= $signal->processed ? 'bg-success' : 'bg-warning text-dark' ?>">
                                                <?= $signal->processed ? 'Processed' : 'Pending' ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if (file_exists($signal->image_path)): ?>
                                                <a href="<?= base_url('telegram_signals/view_image/' . $signal->id) ?>" 
                                                   class="btn btn-sm btn-info" target="_blank">
                                                    <i class="fas fa-image"></i> View
                                                </a>
                                            <?php else: ?>
                                                <span class="text-muted">No image</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?= date('Y-m-d', strtotime($signal->created_at)) ?><br>
                                            <small class="text-muted"><?= date('H:i:s', strtotime($signal->created_at)) ?></small>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <button class="btn btn-outline-info" 
                                                        onclick="showSignalDetails(<?= $signal->id ?>, '<?= htmlspecialchars($signal->message_text, ENT_QUOTES) ?>', '<?= $signal->tradingview_url ?>')">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <?php if (!$signal->processed): ?>
                                                    <a href="<?= base_url('telegram_signals/mark_processed/' . $signal->id) ?>" 
                                                       class="btn btn-success" title="Mark as Processed"
                                                       onclick="return confirm('Mark this signal as processed?')">
                                                        <i class="fas fa-check"></i>
                                                    </a>
                                                <?php endif; ?>
                                                <a href="<?= base_url('telegram_signals/delete/' . $signal->id) ?>" 
                                                   class="btn btn-danger" title="Delete"
                                                   onclick="return confirm('Are you sure you want to delete this signal?')">
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
    </div>
    
    <!-- Ticker Stats -->
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-chart-bar me-1"></i>Ticker Activity (7 days)
                </h6>
            </div>
            <div class="card-body">
                <?php if (empty($ticker_stats)): ?>
                    <p class="text-muted mb-0">No activity in the last 7 days</p>
                <?php else: ?>
                    <?php foreach ($ticker_stats as $stat): ?>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div>
                                <strong><?= $stat->ticker_symbol ?></strong><br>
                                <small class="text-muted"><?= $stat->ticker_name ?></small>
                            </div>
                            <div class="text-end">
                                <span class="badge bg-primary"><?= $stat->total_signals ?></span><br>
                                <small class="text-success"><?= $stat->processed_signals ?> processed</small>
                            </div>
                        </div>
                        <hr class="my-2">
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="card mt-3">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-info-circle me-1"></i>API Endpoints
                </h6>
            </div>
            <div class="card-body">
                <h6>MetaTrader EA Endpoints:</h6>
                <div class="mb-2">
                    <strong>Get Signals:</strong>
                    <div class="input-group input-group-sm">
                        <input type="text" class="form-control" value="<?= base_url('telegram_signals/api_get_signals/{user_id}') ?>" readonly>
                        <button class="btn btn-outline-secondary" onclick="copyToClipboard(this.previousElementSibling)">
                            <i class="fas fa-copy"></i>
                        </button>
                    </div>
                </div>
                <div class="mb-2">
                    <strong>Mark Processed:</strong>
                    <div class="input-group input-group-sm">
                        <input type="text" class="form-control" value="<?= base_url('telegram_signals/api_mark_processed/{signal_id}') ?>" readonly>
                        <button class="btn btn-outline-secondary" onclick="copyToClipboard(this.previousElementSibling)">
                            <i class="fas fa-copy"></i>
                        </button>
                    </div>
                </div>
                <small class="text-muted">Replace {user_id} and {signal_id} with actual values</small>
            </div>
        </div>
    </div>
</div>

<!-- Signal Details Modal -->
<div class="modal fade" id="signalDetailsModal" tabindex="-1" aria-labelledby="signalDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="signalDetailsModalLabel">
                    <i class="fas fa-paper-plane me-1"></i>Signal Details
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <strong>Signal ID:</strong>
                        <p id="modalSignalId"></p>
                        
                        <strong>TradingView URL:</strong>
                        <p><a id="modalTradingViewUrl" href="#" target="_blank">View Chart</a></p>
                    </div>
                    <div class="col-md-6">
                        <strong>Original Message:</strong>
                        <pre id="modalMessageText" class="bg-light p-3 rounded"></pre>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Cleanup Modal -->
<?php if ($this->session->userdata('role') === 'admin'): ?>
<div class="modal fade" id="cleanupModal" tabindex="-1" aria-labelledby="cleanupModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="cleanupModalLabel">Cleanup Old Signals</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <?= form_open('telegram_signals/cleanup') ?>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="days" class="form-label">Delete signals older than (days):</label>
                        <input type="number" class="form-control" id="days" name="days" min="1" value="30" required>
                    </div>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>This action cannot be undone!
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete Old Signals</button>
                </div>
            <?= form_close() ?>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
function showSignalDetails(signalId, messageText, tradingViewUrl) {
    document.getElementById('modalSignalId').textContent = signalId;
    document.getElementById('modalMessageText').textContent = messageText;
    document.getElementById('modalTradingViewUrl').href = tradingViewUrl;
    
    const modal = new bootstrap.Modal(document.getElementById('signalDetailsModal'));
    modal.show();
}

function copyToClipboard(element) {
    element.select();
    document.execCommand('copy');

    // Visual feedback
    const button = element.nextElementSibling;
    const originalHTML = button.innerHTML;
    button.innerHTML = '<i class="fas fa-check"></i>';
    button.classList.add('btn-success');

    setTimeout(() => {
        button.innerHTML = originalHTML;
        button.classList.remove('btn-success');
    }, 1500);
}
</script>