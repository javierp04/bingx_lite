<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">
        <i class="fas fa-tags me-2"></i>Available Tickers Management
    </h1>
    <a href="<?= base_url('available_tickers/add') ?>" class="btn btn-primary">
        <i class="fas fa-plus-circle me-1"></i>Add New Ticker
    </a>
</div>



<!-- Tickers Table -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="fas fa-list me-1"></i>Available Tickers
        </h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-striped table-hover mb-0">
                <thead>
                    <tr>
                        <th>Symbol</th>
                        <th>Name</th>
                        <th>Decimals</th>
                        <th>Status</th>
                        <th>User Selections</th>
                        <th>Signals</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($tickers)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-3">No tickers found</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($tickers as $ticker): ?>
                            <tr>
                                <td>
                                    <strong><?= $ticker->symbol ?></strong>
                                </td>
                                <td><?= $ticker->name ?></td>
                                <td>
                                    <span class="badge bg-secondary"><?= $ticker->display_decimals ?></span>
                                </td>
                                <td>
                                    <span class="badge <?= $ticker->active ? 'bg-success' : 'bg-secondary' ?>">
                                        <?= $ticker->active ? 'Active' : 'Inactive' ?>
                                    </span>
                                </td>
                                <td>
                                    <?php 
                                        // Count user selections for this ticker
                                        $this->db->where('ticker_symbol', $ticker->symbol);
                                        $user_count = $this->db->count_all_results('user_selected_tickers');
                                    ?>
                                    <span class="badge bg-info"><?= $user_count ?> users</span>
                                </td>
                                <td>
                                    <?php 
                                        // Count telegram signals for this ticker
                                        $this->db->where('ticker_symbol', $ticker->symbol);
                                        $signals_count = $this->db->count_all_results('telegram_signals');
                                    ?>
                                    <span class="badge bg-warning text-dark"><?= $signals_count ?> signals</span>
                                </td>                                
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="<?= base_url('available_tickers/edit/' . $ticker->symbol) ?>" 
                                           class="btn btn-outline-primary" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="<?= base_url('available_tickers/toggle/' . $ticker->symbol) ?>" 
                                           class="btn <?= $ticker->active ? 'btn-outline-secondary' : 'btn-outline-success' ?>" 
                                           title="<?= $ticker->active ? 'Deactivate' : 'Activate' ?>"
                                           onclick="return confirm('Are you sure you want to <?= $ticker->active ? 'deactivate' : 'activate' ?> this ticker?')">
                                            <i class="fas fa-<?= $ticker->active ? 'pause' : 'play' ?>"></i>
                                        </a>
                                        <?php if ($user_count == 0 && $signals_count == 0): ?>
                                            <a href="<?= base_url('available_tickers/delete/' . $ticker->symbol) ?>" 
                                               class="btn btn-outline-danger" title="Delete"
                                               onclick="return confirm('Are you sure you want to delete this ticker?')">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        <?php else: ?>
                                            <button class="btn btn-outline-danger" disabled title="Cannot delete: has user selections or signals">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        <?php endif; ?>
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

<!-- Info Card -->
<div class="card mt-4">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="fas fa-info-circle me-1"></i>Ticker Management Guide
        </h5>
    </div>
    <div class="card-body">
        <p>Available tickers represent the symbols that can be traded through Telegram signals. Users can select which tickers they want to trade from this list.</p>
        
        <div class="row">
            <div class="col-md-6">
                <h6>Ticker States:</h6>
                <ul>
                    <li><span class="badge bg-success">Active</span> - Can be selected by users and will process signals</li>
                    <li><span class="badge bg-secondary">Inactive</span> - Hidden from users, signals ignored</li>
                </ul>
            </div>
            <div class="col-md-6">
                <h6>Deletion Rules:</h6>
                <ul>
                    <li>Tickers with user selections cannot be deleted</li>
                    <li>Tickers with telegram signals cannot be deleted</li>
                    <li>Use deactivation instead to hide tickers</li>
                </ul>
            </div>
        </div>
        
        <div class="alert alert-info mt-3">
            <i class="fas fa-lightbulb me-2"></i>
            <strong>Tip:</strong> Use clear, standardized symbols (e.g., EURUSD, BTCUSDT, NQ) that match your Telegram signals.
        </div>
    </div>
</div>