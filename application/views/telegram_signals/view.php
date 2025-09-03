<div class="mb-4">
    <div class="d-flex justify-content-between align-items-center">
        <h1 class="h3 mb-0">
            <i class="fas fa-paper-plane me-2"></i>Telegram Signal #<?= $signal->id ?>
        </h1>
        <div>
            <a href="<?= base_url('telegram_signals') ?>" class="btn btn-secondary">
                <i class="fas fa-list me-1"></i>Back to Signals
            </a>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-8">
        <!-- Signal Details -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Signal Information</h5>
            </div>
            <div class="card-body">
                <table class="table table-bordered">
                    <tr>
                        <th width="25%">Signal ID</th>
                        <td><?= $signal->id ?></td>
                    </tr>
                    <tr>
                        <th>Ticker</th>
                        <td>
                            <strong><?= $signal->ticker_symbol ?></strong> - <?= $signal->ticker_name ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Status</th>
                        <td>
                            <span class="badge <?= $signal->processed ? 'bg-success' : 'bg-warning text-dark' ?>">
                                <?= $signal->processed ? 'Processed' : 'Pending' ?>
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <th>Created</th>
                        <td><?= date('Y-m-d H:i:s', strtotime($signal->created_at)) ?></td>
                    </tr>
                    <?php if ($signal->updated_at): ?>
                    <tr>
                        <th>Last Updated</th>
                        <td><?= date('Y-m-d H:i:s', strtotime($signal->updated_at)) ?></td>
                    </tr>
                    <?php endif; ?>
                    <tr>
                        <th>TradingView URL</th>
                        <td>
                            <a href="<?= $signal->tradingview_url ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                <i class="fas fa-external-link-alt me-1"></i>Open Chart
                            </a>
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- Original Message -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Original Telegram Message</h5>
            </div>
            <div class="card-body">
                <pre class="bg-light p-3 rounded mb-0"><?= htmlspecialchars($signal->message_text) ?></pre>
            </div>
        </div>

        <!-- Actions -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Actions</h5>
            </div>
            <div class="card-body">
                <div class="btn-group">
                    <?php if (!$signal->processed): ?>
                        <a href="<?= base_url('telegram_signals/mark_processed/' . $signal->id) ?>" 
                           class="btn btn-success"
                           onclick="return confirm('Mark this signal as processed?')">
                            <i class="fas fa-check me-1"></i>Mark as Processed
                        </a>
                    <?php endif; ?>
                    
                    <a href="<?= $signal->tradingview_url ?>" target="_blank" class="btn btn-outline-primary">
                        <i class="fas fa-chart-line me-1"></i>View Chart
                    </a>
                    
                    <?php if (file_exists($signal->image_path)): ?>
                        <a href="<?= base_url('telegram_signals/view_image/' . $signal->id) ?>" 
                           target="_blank" class="btn btn-outline-info">
                            <i class="fas fa-image me-1"></i>View Image
                        </a>
                    <?php endif; ?>
                    
                    <a href="<?= base_url('telegram_signals/delete/' . $signal->id) ?>" 
                       class="btn btn-danger"
                       onclick="return confirm('Are you sure you want to delete this signal? This action cannot be undone.')">
                        <i class="fas fa-trash me-1"></i>Delete
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <!-- Image Preview -->
        <?php if (file_exists($signal->image_path)): ?>
            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="fas fa-image me-1"></i>Chart Image
                    </h6>
                </div>
                <div class="card-body text-center">
                    <img src="<?= base_url('telegram_signals/view_image/' . $signal->id) ?>" 
                         class="img-fluid rounded" 
                         style="max-height: 300px; cursor: pointer;"
                         onclick="window.open('<?= base_url('telegram_signals/view_image/' . $signal->id) ?>', '_blank')">
                    <div class="mt-2">
                        <small class="text-muted">Click to view full size</small>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="fas fa-image me-1"></i>Chart Image
                    </h6>
                </div>
                <div class="card-body text-center">
                    <div class="text-muted py-4">
                        <i class="fas fa-image fa-3x mb-2"></i>
                        <p>Image file not found</p>
                        <small>Path: <?= $signal->image_path ?></small>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Users Trading This Ticker -->
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-users me-1"></i>Users Trading <?= $signal->ticker_symbol ?>
                </h6>
            </div>
            <div class="card-body">
                <?php 
                    $this->db->select('u.username, ust.active');
                    $this->db->from('user_selected_tickers ust');
                    $this->db->join('users u', 'ust.user_id = u.id');
                    $this->db->where('ust.ticker_symbol', $signal->ticker_symbol);
                    $this->db->where('ust.active', 1);
                    $this->db->order_by('u.username', 'ASC');
                    $trading_users = $this->db->get()->result();
                ?>
                
                <?php if (empty($trading_users)): ?>
                    <div class="text-muted text-center py-2">
                        <i class="fas fa-user-slash mb-2"></i>
                        <p class="mb-0">No users trading this ticker</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($trading_users as $user): ?>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span><?= $user->username ?></span>
                            <span class="badge bg-success badge-sm">Active</span>
                        </div>
                    <?php endforeach; ?>
                    
                    <div class="text-center mt-3">
                        <small class="text-muted"><?= count($trading_users) ?> active trader<?= count($trading_users) != 1 ? 's' : '' ?></small>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Recent Signals for this Ticker -->
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-history me-1"></i>Recent <?= $signal->ticker_symbol ?> Signals
                </h6>
            </div>
            <div class="card-body">
                <?php 
                    $this->db->select('id, created_at, processed');
                    $this->db->where('ticker_symbol', $signal->ticker_symbol);
                    $this->db->where('id !=', $signal->id);
                    $this->db->order_by('created_at', 'DESC');
                    $this->db->limit(5);
                    $recent_signals = $this->db->get('telegram_signals')->result();
                ?>
                
                <?php if (empty($recent_signals)): ?>
                    <div class="text-muted text-center py-2">
                        <p class="mb-0">No other signals for this ticker</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($recent_signals as $recent): ?>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div>
                                <a href="<?= base_url('telegram_signals/view/' . $recent->id) ?>">
                                    Signal #<?= $recent->id ?>
                                </a><br>
                                <small class="text-muted"><?= date('M j, H:i', strtotime($recent->created_at)) ?></small>
                            </div>
                            <span class="badge <?= $recent->processed ? 'bg-success' : 'bg-warning text-dark' ?> badge-sm">
                                <?= $recent->processed ? 'Processed' : 'Pending' ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                    
                    <div class="text-center mt-3">
                        <a href="<?= base_url('telegram_signals?ticker_symbol=' . $signal->ticker_symbol) ?>" class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-list me-1"></i>View All
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>