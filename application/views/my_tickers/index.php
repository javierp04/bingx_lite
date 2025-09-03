<div class="mb-4">
    <h1 class="h3 mb-0">
        <i class="fas fa-user-check me-2"></i>My Trading Tickers
    </h1>
</div>

<!-- My Selected Tickers -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="fas fa-list me-1"></i>My Selected Tickers (<?= count($selected_tickers) ?>)
        </h5>
    </div>
    <div class="card-body p-0">
        <?php if (empty($selected_tickers)): ?>
            <div class="text-center py-4">
                <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                <h5 class="text-muted">No Tickers Selected</h5>
                <p class="text-muted">Select tickers from the available list below to start trading.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Telegram Symbol</th>
                            <th>Name</th>
                            <th>MetaTrader Symbol</th>
                            <th>Status</th>
                            <th width="150">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($selected_tickers as $ticker): ?>
                            <tr data-ticker="<?= $ticker->ticker_symbol ?>">
                                <td><strong><?= $ticker->ticker_symbol ?></strong></td>
                                <td><?= $ticker->ticker_name ?></td>
                                <td>
                                    <div class="mt-symbol-container">
                                        <span class="mt-display">
                                            <?php if ($ticker->mt_ticker): ?>
                                                <code><?= $ticker->mt_ticker ?></code>
                                            <?php else: ?>
                                                <em class="text-danger">Not Set</em>
                                            <?php endif; ?>
                                        </span>
                                        <input type="text" class="form-control form-control-sm mt-input d-none" 
                                               value="<?= $ticker->mt_ticker ?>" placeholder="e.g. US500">
                                    </div>
                                </td>
                                <td>
                                    <span class="badge <?= $ticker->active ? 'bg-success' : 'bg-secondary' ?>">
                                        <?= $ticker->active ? 'Active' : 'Inactive' ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <button class="btn btn-outline-primary btn-edit-mt" title="Edit MT Symbol">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <a href="<?= base_url('my_tickers/toggle_ticker/' . $ticker->ticker_symbol) ?>" 
                                           class="btn <?= $ticker->active ? 'btn-outline-secondary' : 'btn-outline-success' ?>" 
                                           title="<?= $ticker->active ? 'Deactivate' : 'Activate' ?>">
                                            <i class="fas <?= $ticker->active ? 'fa-pause' : 'fa-play' ?>"></i>
                                        </a>
                                        <a href="<?= base_url('my_tickers/remove_ticker/' . $ticker->ticker_symbol) ?>" 
                                           class="btn btn-outline-danger" title="Remove"
                                           onclick="return confirm('Remove <?= $ticker->ticker_symbol ?> from your selection?')">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Available Tickers -->
<?php if (!empty($available_tickers)): ?>
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">
                <i class="fas fa-plus-circle me-1"></i>Available Tickers (<?= count($available_tickers) ?>)
            </h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Symbol</th>
                            <th>Name</th>
                            <th width="100">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($available_tickers as $ticker): ?>
                            <tr>
                                <td><strong><?= $ticker->symbol ?></strong></td>
                                <td><?= $ticker->name ?></td>
                                <td>
                                    <button class="btn btn-sm btn-primary btn-add-ticker" 
                                            data-symbol="<?= $ticker->symbol ?>" 
                                            data-name="<?= $ticker->name ?>">
                                        <i class="fas fa-plus me-1"></i>Add
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php else: ?>
    <div class="alert alert-info">
        <i class="fas fa-info-circle me-2"></i>All available tickers are already in your selection.
    </div>
<?php endif; ?>

<!-- Add Ticker Modal -->
<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Ticker</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-sm-4">
                        <label class="form-label">Telegram Symbol:</label>
                        <div class="form-control-plaintext"><strong id="modal-symbol"></strong></div>
                    </div>
                    <div class="col-sm-8">
                        <label class="form-label">Name:</label>
                        <div class="form-control-plaintext" id="modal-name"></div>
                    </div>
                </div>
                <div class="mt-3">
                    <label for="modal-mt-symbol" class="form-label">MetaTrader Symbol <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="modal-mt-symbol" 
                           placeholder="Enter MT symbol (e.g. US500, EURUSD)" required>
                    <div class="form-text">This is how the symbol appears in your MetaTrader platform.</div>
                </div>
                <div class="mt-3">
                    <div class="alert alert-info mb-0">
                        <strong>Common mappings:</strong> ES → US500, NQ → USTEC, YM → US30, XAUUSD → GOLD
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="btn-confirm-add">
                    <i class="fas fa-plus me-1"></i>Add Ticker
                </button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    let currentSymbol = '';
    const addModal = new bootstrap.Modal(document.getElementById('addModal'));
    
    // Add ticker button clicks
    document.querySelectorAll('.btn-add-ticker').forEach(button => {
        button.addEventListener('click', function() {
            const symbol = this.dataset.symbol;
            const name = this.dataset.name;
            
            currentSymbol = symbol;
            document.getElementById('modal-symbol').textContent = symbol;
            document.getElementById('modal-name').textContent = name;
            document.getElementById('modal-mt-symbol').value = '';
            
            addModal.show();
            
            // Focus input after modal is shown
            setTimeout(() => {
                document.getElementById('modal-mt-symbol').focus();
            }, 500);
        });
    });
    
    // Confirm add ticker
    document.getElementById('btn-confirm-add').addEventListener('click', function() {
        const mtSymbol = document.getElementById('modal-mt-symbol').value.trim();
        
        if (!mtSymbol) {
            alert('Please enter a MetaTrader symbol');
            document.getElementById('modal-mt-symbol').focus();
            return;
        }
        
        const btn = this;
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Adding...';
        
        // Create form data
        const formData = new FormData();
        formData.append('ticker_symbol', currentSymbol);
        formData.append('mt_ticker', mtSymbol);
        
        fetch('<?= base_url('my_tickers/add_ticker') ?>', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            if (response.ok) {
                location.reload();
            } else {
                throw new Error('Failed to add ticker');
            }
        })
        .catch(error => {
            alert('Failed to add ticker. Please try again.');
        })
        .finally(() => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-plus me-1"></i>Add Ticker';
        });
    });
    
    // Edit MT symbol inline
    document.querySelectorAll('.btn-edit-mt').forEach(button => {
        button.addEventListener('click', function() {
            const row = this.closest('tr');
            const ticker = row.dataset.ticker;
            const display = row.querySelector('.mt-display');
            const input = row.querySelector('.mt-input');
            const btn = this;
            
            if (input.classList.contains('d-none')) {
                // Switch to edit mode
                display.classList.add('d-none');
                input.classList.remove('d-none');
                input.focus();
                input.select();
                btn.classList.remove('btn-outline-primary');
                btn.classList.add('btn-success');
                btn.innerHTML = '<i class="fas fa-save"></i>';
                btn.title = 'Save';
            } else {
                // Save changes
                const newValue = input.value.trim();
                btn.disabled = true;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
                
                // Create form data
                const formData = new FormData();
                formData.append('ticker_symbol', ticker);
                formData.append('mt_ticker', newValue);
                
                fetch('<?= base_url('my_tickers/update_mt_ticker') ?>', {
                    method: 'POST',
                    body: formData
                })
                .then(response => {
                    if (response.ok) {
                        // Update display
                        if (newValue) {
                            display.innerHTML = '<code>' + newValue + '</code>';
                        } else {
                            display.innerHTML = '<em class="text-danger">Not Set</em>';
                        }
                        
                        // Switch back to display mode
                        input.classList.add('d-none');
                        display.classList.remove('d-none');
                        btn.classList.remove('btn-success');
                        btn.classList.add('btn-outline-primary');
                        btn.innerHTML = '<i class="fas fa-edit"></i>';
                        btn.title = 'Edit MT Symbol';
                    } else {
                        throw new Error('Failed to update');
                    }
                })
                .catch(error => {
                    alert('Failed to update MT symbol');
                })
                .finally(() => {
                    btn.disabled = false;
                });
            }
        });
    });
    
    // Handle Enter key in MT inputs
    document.querySelectorAll('.mt-input').forEach(input => {
        input.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                this.closest('tr').querySelector('.btn-edit-mt').click();
            }
        });
    });
    
    // Handle Enter key in modal
    document.getElementById('modal-mt-symbol').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            document.getElementById('btn-confirm-add').click();
        }
    });
    
    // Reset modal on close
    document.getElementById('addModal').addEventListener('hidden.bs.modal', function() {
        document.getElementById('modal-mt-symbol').value = '';
        const btn = document.getElementById('btn-confirm-add');
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-plus me-1"></i>Add Ticker';
    });
});
</script>