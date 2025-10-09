<div class="mb-4">
    <h1 class="h3 mb-0">
        <i class="fas fa-tags me-2"></i>Edit Ticker: <?= $ticker->symbol ?>
    </h1>
</div>

<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-body">
                <?= form_open('available_tickers/edit/' . $ticker->symbol) ?>
                    <div class="mb-3">
                        <label for="symbol" class="form-label">Ticker Symbol</label>
                        <input type="text" class="form-control" id="symbol" name="symbol" value="<?= set_value('symbol', $ticker->symbol) ?>" required>
                        <div class="form-text">Enter the ticker symbol (e.g., EURUSD, BTCUSDT, NQ). Will be converted to uppercase.</div>
                        <?php if ($ticker->symbol != set_value('symbol', $ticker->symbol)): ?>
                            <div class="alert alert-warning mt-2">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <strong>Warning:</strong> Changing the symbol will update all related records (user selections and signals).
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="mb-3">
                        <label for="name" class="form-label">Display Name</label>
                        <input type="text" class="form-control" id="name" name="name" value="<?= set_value('name', $ticker->name) ?>" required>
                        <div class="form-text">Descriptive name for the ticker (e.g., "Euro/US Dollar", "Bitcoin/USDT", "Nasdaq 100")</div>
                    </div>

                    <div class="mb-3">
                        <label for="display_decimals" class="form-label">Display Decimals</label>
                        <select class="form-select" id="display_decimals" name="display_decimals" required>
                            <option value="1" <?= $ticker->display_decimals == 1 ? 'selected' : '' ?>>1 (Indices: US500, US100, US30)</option>
                            <option value="2" <?= $ticker->display_decimals == 2 ? 'selected' : '' ?>>2 (Crypto: BTCUSDT)</option>
                            <option value="3" <?= $ticker->display_decimals == 3 ? 'selected' : '' ?>>3 (Gold/Oil: XAUUSD, USOIL, US2000)</option>
                            <option value="5" <?= $ticker->display_decimals == 5 ? 'selected' : '' ?>>5 (Forex: EURUSD, GBPUSD)</option>
                            <option value="8" <?= $ticker->display_decimals == 8 ? 'selected' : '' ?>>8 (Custom/High Precision)</option>
                        </select>
                        <div class="form-text">Number of decimal places to display prices for this ticker</div>
                    </div>

                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="active" name="active" value="1" <?= $ticker->active ? 'checked' : '' ?>>
                        <label class="form-check-label" for="active">Active</label>
                        <div class="form-text">Only active tickers can be selected by users and process signals</div>
                    </div>
                    
                    <div class="d-flex justify-content-between">
                        <a href="<?= base_url('available_tickers') ?>" class="btn btn-secondary">
                            <i class="fas fa-arrow-left me-1"></i>Back
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i>Update Ticker
                        </button>
                    </div>
                <?= form_close() ?>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <!-- Ticker Usage Info -->
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-info-circle me-1"></i>Ticker Usage
                </h6>
            </div>
            <div class="card-body">
                <?php 
                    // Count user selections
                    $this->db->where('ticker_symbol', $ticker->symbol);
                    $user_count = $this->db->count_all_results('user_selected_tickers');
                    
                    // Count telegram signals
                    $this->db->where('ticker_symbol', $ticker->symbol);
                    $signals_count = $this->db->count_all_results('telegram_signals');
                ?>
                
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span>User Selections:</span>
                    <span class="badge bg-info"><?= $user_count ?></span>
                </div>
                
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span>Telegram Signals:</span>
                    <span class="badge bg-warning text-dark"><?= $signals_count ?></span>
                </div>
                
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span>Display Decimals:</span>
                    <span class="badge bg-info"><?= $ticker->display_decimals ?></span>
                </div>

                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span>Status:</span>
                    <span class="badge <?= $ticker->active ? 'bg-success' : 'bg-secondary' ?>">
                        <?= $ticker->active ? 'Active' : 'Inactive' ?>
                    </span>
                </div>

                <hr>
                
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span>Created:</span>
                    <span class="text-muted"><?= date('Y-m-d', strtotime($ticker->created_at)) ?></span>
                </div>
                
                <?php if ($ticker->updated_at): ?>
                    <div class="d-flex justify-content-between align-items-center">
                        <span>Last Updated:</span>
                        <span class="text-muted"><?= date('Y-m-d', strtotime($ticker->updated_at)) ?></span>
                    </div>
                <?php endif; ?>
                
                <?php if ($user_count > 0 || $signals_count > 0): ?>
                    <div class="alert alert-info mt-3">
                        <small>
                            <i class="fas fa-shield-alt me-1"></i>
                            This ticker is in use and cannot be deleted.
                        </small>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Recent Signals -->
        <?php if ($signals_count > 0): ?>
            <div class="card mt-3">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="fas fa-signal me-1"></i>Recent Signals
                    </h6>
                </div>
                <div class="card-body">
                    <?php
                        $this->db->select('id, created_at, status');
                        $this->db->where('ticker_symbol', $ticker->symbol);
                        $this->db->order_by('created_at', 'DESC');
                        $this->db->limit(5);
                        $recent_signals = $this->db->get('telegram_signals')->result();
                    ?>

                    <?php if (!empty($recent_signals)): ?>
                        <?php foreach ($recent_signals as $signal): ?>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <small class="text-muted">
                                    <?= date('M j, H:i', strtotime($signal->created_at)) ?>
                                </small>
                                <?php
                                    $status_class = 'bg-secondary';
                                    $status_text = ucfirst($signal->status);
                                    switch($signal->status) {
                                        case 'completed':
                                            $status_class = 'bg-success';
                                            break;
                                        case 'pending':
                                        case 'cropping':
                                        case 'analyzing':
                                            $status_class = 'bg-warning text-dark';
                                            break;
                                        case 'failed_crop':
                                        case 'failed_analysis':
                                        case 'failed_download':
                                            $status_class = 'bg-danger';
                                            break;
                                    }
                                ?>
                                <span class="badge <?= $status_class ?> badge-sm">
                                    <?= $status_text ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                        
                        <div class="text-center mt-2">
                            <a href="<?= base_url('telegram_signals?ticker_symbol=' . $ticker->symbol) ?>" class="btn btn-sm btn-outline-info">
                                View All Signals
                            </a>
                        </div>
                    <?php else: ?>
                        <p class="text-muted mb-0">No recent signals</p>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Users with this ticker -->
        <?php if ($user_count > 0): ?>
            <div class="card mt-3">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="fas fa-users me-1"></i>Users Trading This
                    </h6>
                </div>
                <div class="card-body">
                    <?php 
                        $this->db->select('u.username, ust.active');
                        $this->db->from('user_selected_tickers ust');
                        $this->db->join('users u', 'ust.user_id = u.id');
                        $this->db->where('ust.ticker_symbol', $ticker->symbol);
                        $this->db->order_by('u.username', 'ASC');
                        $ticker_users = $this->db->get()->result();
                    ?>
                    
                    <?php foreach ($ticker_users as $user): ?>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span><?= $user->username ?></span>
                            <span class="badge <?= $user->active ? 'bg-success' : 'bg-secondary' ?> badge-sm">
                                <?= $user->active ? 'Active' : 'Inactive' ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>