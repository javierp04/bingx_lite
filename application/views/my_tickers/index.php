<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">
        <i class="fas fa-user-check me-2"></i>My Trading Tickers
    </h1>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addTickerModal">
        <i class="fas fa-plus-circle me-1"></i>Add Ticker
    </button>
</div>

<!-- Stats Cards -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="card-title text-muted">Selected Tickers</h6>
                        <h4 class="mb-0"><?= count($selected_tickers) ?></h4>
                    </div>
                    <div class="text-primary">
                        <i class="fas fa-tags fa-2x"></i>
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
                        <h6 class="card-title text-muted">Active</h6>
                        <h4 class="mb-0 text-success"><?= count(array_filter($selected_tickers, function($t) { return $t->active; })) ?></h4>
                    </div>
                    <div class="text-success">
                        <i class="fas fa-play fa-2x"></i>
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
                        <h6 class="card-title text-muted">Available</h6>
                        <h4 class="mb-0 text-info"><?= count($available_tickers) ?></h4>
                    </div>
                    <div class="text-info">
                        <i class="fas fa-plus fa-2x"></i>
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
                        <h6 class="card-title text-muted">Recent Signals</h6>
                        <small class="text-muted">Last 24h</small>
                    </div>
                    <div class="text-warning">
                        <i class="fas fa-signal fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Selected Tickers Table -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="fas fa-list me-1"></i>My Selected Tickers
        </h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-striped table-hover mb-0">
                <thead>
                    <tr>
                        <th>Symbol</th>
                        <th>Name</th>
                        <th>Status</th>
                        <th>Recent Signals</th>
                        <th>Selected Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($selected_tickers)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-3">
                                <div class="text-muted">
                                    <i class="fas fa-inbox fa-2x mb-2"></i>
                                    <p class="mb-1">No tickers selected yet</p>
                                    <small>Click "Add Ticker" to start selecting tickers for trading</small>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($selected_tickers as $ticker): ?>
                            <tr>
                                <td>
                                    <strong><?= $ticker->ticker_symbol ?></strong>
                                </td>
                                <td><?= $ticker->ticker_name ?></td>
                                <td>
                                    <span class="badge <?= $ticker->active ? 'bg-success' : 'bg-secondary' ?>">
                                        <?= $ticker->active ? 'Active' : 'Inactive' ?>
                                    </span>
                                </td>
                                <td>
                                    <?php 
                                        // Count recent signals for this ticker
                                        $this->db->where('ticker_symbol', $ticker->ticker_symbol);
                                        $this->db->where('created_at >=', date('Y-m-d H:i:s', strtotime('-24 hours')));
                                        $recent_count = $this->db->count_all_results('telegram_signals');
                                    ?>
                                    <span class="badge bg-info"><?= $recent_count ?> signals</span>
                                    <?php if ($recent_count > 0): ?>
                                        <a href="<?= base_url('telegram_signals?ticker_symbol=' . $ticker->ticker_symbol) ?>" class="ms-1">
                                            <i class="fas fa-external-link-alt"></i>
                                        </a>
                                    <?php endif; ?>
                                </td>
                                <td><?= date('Y-m-d', strtotime($ticker->created_at)) ?></td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="<?= base_url('my_tickers/toggle_ticker/' . $ticker->ticker_symbol) ?>" 
                                           class="btn <?= $ticker->active ? 'btn-secondary' : 'btn-success' ?>" 
                                           title="<?= $ticker->active ? 'Deactivate' : 'Activate' ?>">
                                            <i class="fas fa-<?= $ticker->active ? 'pause' : 'play' ?>"></i>
                                        </a>
                                        <a href="<?= base_url('my_tickers/remove_ticker/' . $ticker->ticker_symbol) ?>" 
                                           class="btn btn-danger" title="Remove"
                                           onclick="return confirm('Are you sure you want to remove <?= $ticker->ticker_symbol ?> from your selection?')">
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

<!-- Available Tickers -->
<?php if (!empty($available_tickers)): ?>
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">
                <i class="fas fa-plus-circle me-1"></i>Available Tickers
            </h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Symbol</th>
                            <th>Name</th>
                            <th>Recent Activity</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($available_tickers as $ticker): ?>
                            <tr>
                                <td><strong><?= $ticker->symbol ?></strong></td>
                                <td><?= $ticker->name ?></td>
                                <td>
                                    <?php 
                                        $this->db->where('ticker_symbol', $ticker->symbol);
                                        $this->db->where('created_at >=', date('Y-m-d H:i:s', strtotime('-7 days')));
                                        $weekly_signals = $this->db->count_all_results('telegram_signals');
                                    ?>
                                    <small class="text-muted"><?= $weekly_signals ?> signals this week</small>
                                </td>
                                <td>
                                    <?= form_open('my_tickers/add_ticker', ['class' => 'd-inline']) ?>
                                        <input type="hidden" name="ticker_symbol" value="<?= $ticker->symbol ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-plus me-1"></i>Add
                                        </button>
                                    <?= form_close() ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Add Ticker Modal -->
<div class="modal fade" id="addTickerModal" tabindex="-1" aria-labelledby="addTickerModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addTickerModalLabel">
                    <i class="fas fa-plus-circle me-1"></i>Add Ticker to Your Selection
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <?php if (!empty($available_tickers)): ?>
                <?= form_open('my_tickers/add_ticker') ?>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="ticker_symbol" class="form-label">Select Ticker</label>
                            <select class="form-select" id="ticker_symbol" name="ticker_symbol" required>
                                <option value="">Choose a ticker...</option>
                                <?php foreach ($available_tickers as $ticker): ?>
                                    <option value="<?= $ticker->symbol ?>">
                                        <?= $ticker->symbol ?> - <?= $ticker->name ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            Selected tickers will receive Telegram signals and can be traded by your MetaTrader EA.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-plus me-1"></i>Add Ticker
                        </button>
                    </div>
                <?= form_close() ?>
            <?php else: ?>
                <div class="modal-body">
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        No tickers available to add. All available tickers are already in your selection.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Info Card -->
<div class="card mt-4">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="fas fa-info-circle me-1"></i>How Ticker Selection Works
        </h5>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <h6>Ticker States:</h6>
                <ul>
                    <li><span class="badge bg-success">Active</span> - Will receive and process Telegram signals</li>
                    <li><span class="badge bg-secondary">Inactive</span> - Signals ignored, no trading</li>
                </ul>
            </div>
            <div class="col-md-6">
                <h6>Signal Flow:</h6>
                <ol>
                    <li>Telegram sends signal for selected ticker</li>
                    <li>System processes and stores the signal</li>
                    <li>MetaTrader EA polls for your selected tickers</li>
                    <li>EA executes trades based on signals</li>
                </ol>
            </div>
        </div>
        
        <div class="alert alert-info mt-3">
            <i class="fas fa-lightbulb me-2"></i>
            <strong>Tip:</strong> Only select tickers you actively want to trade. Your MetaTrader EA will only see signals for your selected and active tickers.
        </div>
    </div>
</div>