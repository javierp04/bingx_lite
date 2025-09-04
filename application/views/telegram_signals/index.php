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
            <div class="card-body text-center">
                <h6 class="card-title text-muted">Total</h6>
                <h4 class="mb-0"><?= $stats['total'] ?></h4>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card">
            <div class="card-body text-center">
                <h6 class="card-title text-muted">Completed</h6>
                <h4 class="mb-0 text-success"><?= $stats['completed'] ?></h4>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card">
            <div class="card-body text-center">
                <h6 class="card-title text-muted">Failed</h6>
                <h4 class="mb-0 text-danger"><?= $stats['failed'] ?></h4>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card">
            <div class="card-body text-center">
                <h6 class="card-title text-muted">Last 24h</h6>
                <h4 class="mb-0 text-warning"><?= $stats['last_24h'] ?></h4>
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
        <div class="col-md-3">
            <label for="status" class="form-label">Status</label>
            <select class="form-select" id="status" name="status">
                <option value="">All Status</option>
                <option value="pending" <?= $filters['status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                <option value="cropping" <?= $filters['status'] === 'cropping' ? 'selected' : '' ?>>Cropping</option>
                <option value="analyzing" <?= $filters['status'] === 'analyzing' ? 'selected' : '' ?>>Analyzing</option>
                <option value="completed" <?= $filters['status'] === 'completed' ? 'selected' : '' ?>>Completed</option>
                <option value="failed_crop" <?= $filters['status'] === 'failed_crop' ? 'selected' : '' ?>>Failed Crop</option>
                <option value="failed_analysis" <?= $filters['status'] === 'failed_analysis' ? 'selected' : '' ?>>Failed Analysis</option>
                <option value="failed_download" <?= $filters['status'] === 'failed_download' ? 'selected' : '' ?>>Failed Download</option>
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
        <div class="col-md-2">
            <div class="d-flex gap-2 align-items-end h-100">
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


<!-- Signals Table -->
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
                        <th>Op Type</th>
                        <th>Analysis</th>
                        <th>Images</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($signals)): ?>
                        <tr>
                            <td colspan="8" class="text-center py-3">No signals found</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($signals as $signal): ?>
                            <?php
                            // Check if cropped image exists
                            $path_info = pathinfo($signal->image_path);
                            $cropped_filename = 'cropped-' . $path_info['filename'] . '.' . $path_info['extension'];
                            $cropped_path = $path_info['dirname'] . '/' . $cropped_filename;
                            $cropped_exists = file_exists($cropped_path);
                            ?>
                            <tr>
                                <td><?= $signal->id ?></td>
                                <td>
                                    <strong><?= $signal->ticker_symbol ?></strong><br>
                                    <small class="text-muted"><?= $signal->ticker_name ?></small>
                                </td>
                                <td>
                                    <?php
                                    $status_classes = [
                                        'pending' => 'bg-warning text-dark',
                                        'cropping' => 'bg-info',
                                        'analyzing' => 'bg-primary',
                                        'completed' => 'bg-success',
                                        'failed_crop' => 'bg-danger',
                                        'failed_analysis' => 'bg-danger',
                                        'failed_download' => 'bg-danger'
                                    ];
                                    $class = isset($status_classes[$signal->status]) ? $status_classes[$signal->status] : 'bg-secondary';
                                    ?>
                                    <span class="badge <?= $class ?>">
                                        <?= ucfirst(str_replace('_', ' ', $signal->status)) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($signal->status === 'completed' && !empty($signal->op_type)): ?>
                                        <?php
                                        $op_type_class = '';
                                        $op_type_icon = '';
                                        if (strtoupper($signal->op_type) === 'LONG') {
                                            $op_type_class = 'bg-success';
                                            $op_type_icon = 'fas fa-arrow-up';
                                        } elseif (strtoupper($signal->op_type) === 'SHORT') {
                                            $op_type_class = 'bg-danger';
                                            $op_type_icon = 'fas fa-arrow-down';
                                        } else {
                                            $op_type_class = 'bg-secondary';
                                            $op_type_icon = 'fas fa-question';
                                        }
                                        ?>
                                        <span class="badge <?= $op_type_class ?>">
                                            <i class="<?= $op_type_icon ?> me-1"></i><?= strtoupper($signal->op_type) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($signal->status === 'completed' && $signal->analysis_data): ?>
                                        <button class="btn btn-sm btn-outline-success"
                                            onclick="showAnalysis(<?= $signal->id ?>, '<?= htmlspecialchars($signal->analysis_data, ENT_QUOTES) ?>')">
                                            <i class="fas fa-chart-line"></i> View
                                        </button>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (file_exists($signal->image_path) || $cropped_exists): ?>
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-outline-info dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                                <i class="fas fa-image"></i> View
                                            </button>
                                            <ul class="dropdown-menu">
                                                <?php if (file_exists($signal->image_path)): ?>
                                                    <li>
                                                        <a class="dropdown-item" href="<?= base_url('telegram_signals/view_image/' . $signal->id) ?>" target="_blank">
                                                            <i class="fas fa-image me-1"></i>Original
                                                        </a>
                                                    </li>
                                                <?php endif; ?>
                                                <?php if ($cropped_exists): ?>
                                                    <li>
                                                        <a class="dropdown-item" href="<?= base_url('telegram_signals/view_cropped_image/' . $signal->id) ?>" target="_blank">
                                                            <i class="fas fa-crop me-1"></i>Cropped
                                                        </a>
                                                    </li>
                                                <?php endif; ?>
                                            </ul>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted">No images</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?= date('Y-m-d', strtotime($signal->created_at)) ?><br>
                                    <small class="text-muted"><?= date('H:i:s', strtotime($signal->created_at)) ?></small>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="<?= base_url('telegram_signals/view/' . $signal->id) ?>"
                                            class="btn btn-outline-info" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="<?= base_url('telegram_signals/delete/' . $signal->id) ?>"
                                            class="btn btn-outline-danger" title="Delete"
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

<!-- Analysis Modal -->
<div class="modal fade" id="analysisModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">AI Analysis Result</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <pre id="analysisContent" class="bg-light p-3 rounded"></pre>
            </div>
        </div>
    </div>
</div>

<!-- Cleanup Modal -->
<?php if ($this->session->userdata('role') === 'admin'): ?>
    <div class="modal fade" id="cleanupModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Cleanup Old Signals</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="<?= base_url('telegram_signals/cleanup') ?>" method="post">
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
                </form>
            </div>
        </div>
    </div>
<?php endif; ?>

<script>
    function showAnalysis(signalId, analysisData) {
        try {
            const parsed = JSON.parse(analysisData);
            document.getElementById('analysisContent').textContent = JSON.stringify(parsed, null, 2);
        } catch (e) {
            document.getElementById('analysisContent').textContent = analysisData;
        }

        const modal = new bootstrap.Modal(document.getElementById('analysisModal'));
        modal.show();
    }
</script>