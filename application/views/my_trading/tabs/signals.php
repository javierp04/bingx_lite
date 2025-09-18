<!-- Filter Form -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="fas fa-filter me-1"></i>Filter My Signals
        </h5>
    </div>
    <div class="card-body">
        <?= form_open('my_trading/signals', ['method' => 'get', 'class' => 'row g-3']) ?>
        <div class="col-md-3">
            <label for="ticker_symbol" class="form-label">My Tickers</label>
            <select class="form-select" id="ticker_symbol" name="ticker_symbol">
                <option value="">All My Tickers</option>
                <?php foreach ($user_tickers as $ticker): ?>
                    <option value="<?= $ticker->ticker_symbol ?>" <?= $filters['ticker_symbol'] === $ticker->ticker_symbol ? 'selected' : '' ?>>
                        <?= $ticker->ticker_symbol ?> - <?= $ticker->ticker_name ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label for="status" class="form-label">Status</label>
            <select class="form-select" id="status" name="status">
                <option value="">All Status</option>
                <option value="pending" <?= $filters['status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                <option value="executed" <?= $filters['status'] === 'executed' ? 'selected' : '' ?>>Executed</option>
                <option value="failed_execution" <?= $filters['status'] === 'failed_execution' ? 'selected' : '' ?>>Failed Execution</option>
                <option value="closed" <?= $filters['status'] === 'closed' ? 'selected' : '' ?>>Closed</option>
                <option value="rejected" <?= $filters['status'] === 'rejected' ? 'selected' : '' ?>>Rejected</option>
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
                <a href="<?= base_url('my_trading/signals') ?>" class="btn btn-secondary">
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
            <i class="fas fa-list me-1"></i>My Signal History
            <span class="badge bg-secondary ms-2"><?= count($signals) ?></span>
        </h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-striped table-hover mb-0">
                <thead>
                    <tr>
                        <th>Ticker</th>
                        <th>MT Symbol</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Analysis</th>
                        <th>Images</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($signals)): ?>
                        <tr>
                            <td colspan="8" class="text-center py-5">
                                <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">No Signals Found</h5>
                                <p class="text-muted">
                                    <?php if (empty($user_tickers)): ?>
                                        <a href="<?= base_url('my_trading/tickers') ?>" class="btn btn-primary">
                                            <i class="fas fa-plus me-1"></i>Add Tickers to Start Receiving Signals
                                        </a>
                                    <?php else: ?>
                                        Your trading signals will appear here once received from Telegram.
                                    <?php endif; ?>
                                </p>
                            </td>
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
                                <td>
                                    <strong><?= $signal->ticker_symbol ?></strong>
                                    <?php if (isset($signal->ticker_is_active) && !$signal->ticker_is_active): ?>
                                        <span class="badge bg-secondary ms-1" title="Ticker disabled">❌</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($signal->mt_ticker): ?>
                                        <code><?= $signal->mt_ticker ?></code>
                                    <?php else: ?>
                                        <em class="text-muted">Not Set</em>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($signal->op_type)): ?>
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
                                    <?php
                                    $status_classes = [
                                        'pending' => 'bg-warning text-dark',
                                        'executed' => 'bg-success',
                                        'failed_execution' => 'bg-danger',
                                        'closed' => 'bg-info',
                                        'rejected' => 'bg-secondary'
                                    ];
                                    $class = isset($status_classes[$signal->status]) ? $status_classes[$signal->status] : 'bg-secondary';
                                    ?>
                                    <span class="badge <?= $class ?>">
                                        <?= ucfirst(str_replace('_', ' ', $signal->status)) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if (!empty($signal->analysis_data)): ?>
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
                                                        <a class="dropdown-item" href="<?= base_url('telegram_signals/view_image/' . $signal->telegram_signal_id) ?>" target="_blank">
                                                            <i class="fas fa-image me-1"></i>Original
                                                        </a>
                                                    </li>
                                                <?php endif; ?>
                                                <?php if ($cropped_exists): ?>
                                                    <li>
                                                        <a class="dropdown-item" href="<?= base_url('telegram_signals/view_cropped_image/' . $signal->telegram_signal_id) ?>" target="_blank">
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
                                    <a href="<?= base_url('my_trading/signal_detail/' . $signal->id) ?>"
                                        class="btn btn-sm btn-outline-info" title="View Details">
                                        <i class="fas fa-eye"></i>
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