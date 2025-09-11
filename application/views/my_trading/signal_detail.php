<div class="mb-4">
    <div class="d-flex justify-content-between align-items-center">
        <h1 class="h3 mb-0">
            <i class="fas fa-signal me-2"></i>Signal Details
        </h1>
        <div>
            <a href="<?= base_url('my_trading/signals') ?>" class="btn btn-secondary">
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
                <h5 class="mb-0">My Signal Information</h5>
            </div>
            <div class="card-body">
                <table class="table table-bordered">
                    <tr>
                        <th width="25%">Signal ID</th>
                        <td>#<?= $signal->id ?></td>
                    </tr>
                    <tr>
                        <th>Ticker</th>
                        <td>
                            <strong><?= $signal->ticker_symbol ?></strong>
                            <?php if ($signal->mt_ticker): ?>
                                <span class="badge bg-info ms-2">MT: <?= $signal->mt_ticker ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Status</th>
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
                    </tr>
                    <tr>
                        <th>Operation Type</th>
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
                                <span class="text-muted">Not Available</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Received</th>
                        <td><?= date('Y-m-d H:i:s', strtotime($signal->created_at)) ?></td>
                    </tr>
                    <?php if ($signal->updated_at): ?>
                    <tr>
                        <th>Last Updated</th>
                        <td><?= date('Y-m-d H:i:s', strtotime($signal->updated_at)) ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if ($signal->trade_id): ?>
                    <tr>
                        <th>Trade ID</th>
                        <td><code><?= $signal->trade_id ?></code></td>
                    </tr>
                    <?php endif; ?>
                    <tr>
                        <th>TradingView Chart</th>
                        <td>
                            <a href="<?= $signal->tradingview_url ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                <i class="fas fa-external-link-alt me-1"></i>Open Chart
                            </a>
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- AI Analysis -->
        <?php if (!empty($signal->analysis_data)): ?>
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">AI Analysis Result</h5>
            </div>
            <div class="card-body">
                <pre class="bg-light p-3 rounded mb-0"><?= json_encode(json_decode($signal->analysis_data), JSON_PRETTY_PRINT) ?></pre>
            </div>
        </div>
        <?php endif; ?>

        <!-- Execution Data -->
        <?php if (!empty($signal->execution_data)): ?>
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Execution Details</h5>
            </div>
            <div class="card-body">
                <pre class="bg-light p-3 rounded mb-0"><?= json_encode(json_decode($signal->execution_data), JSON_PRETTY_PRINT) ?></pre>
            </div>
        </div>
        <?php endif; ?>

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
                    <a href="<?= $signal->tradingview_url ?>" target="_blank" class="btn btn-outline-primary">
                        <i class="fas fa-chart-line me-1"></i>View Chart
                    </a>
                    
                    <?php if (file_exists($signal->image_path)): ?>
                        <a href="<?= base_url('telegram_signals/view_image/' . $signal->telegram_signal_id) ?>" 
                           target="_blank" class="btn btn-outline-info">
                            <i class="fas fa-image me-1"></i>Original Image
                        </a>
                    <?php endif; ?>

                    <?php 
                    // Check for cropped image
                    $path_info = pathinfo($signal->image_path);
                    $cropped_filename = 'cropped-' . $path_info['filename'] . '.' . $path_info['extension'];
                    $cropped_path = $path_info['dirname'] . '/' . $cropped_filename;
                    if (file_exists($cropped_path)): 
                    ?>
                        <a href="<?= base_url('telegram_signals/view_cropped_image/' . $signal->telegram_signal_id) ?>" 
                           target="_blank" class="btn btn-outline-success">
                            <i class="fas fa-crop me-1"></i>Cropped Image
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <!-- Original Image Preview -->
        <?php if (file_exists($signal->image_path)): ?>
            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="fas fa-image me-1"></i>Original Chart Image
                    </h6>
                </div>
                <div class="card-body text-center">
                    <img src="<?= base_url('telegram_signals/view_image/' . $signal->telegram_signal_id) ?>" 
                         class="img-fluid rounded" 
                         style="max-height: 250px; cursor: pointer;"
                         onclick="window.open('<?= base_url('telegram_signals/view_image/' . $signal->telegram_signal_id) ?>', '_blank')">
                    <div class="mt-2">
                        <small class="text-muted">Click to view full size</small>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Cropped Image Preview -->
        <?php 
        $path_info = pathinfo($signal->image_path);
        $cropped_filename = 'cropped-' . $path_info['filename'] . '.' . $path_info['extension'];
        $cropped_path = $path_info['dirname'] . '/' . $cropped_filename;
        if (file_exists($cropped_path)): 
        ?>
            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="fas fa-crop me-1"></i>Cropped Chart Image
                    </h6>
                </div>
                <div class="card-body text-center">
                    <img src="<?= base_url('telegram_signals/view_cropped_image/' . $signal->telegram_signal_id) ?>" 
                         class="img-fluid rounded" 
                         style="max-height: 250px; cursor: pointer;"
                         onclick="window.open('<?= base_url('telegram_signals/view_cropped_image/' . $signal->telegram_signal_id) ?>', '_blank')">
                    <div class="mt-2">
                        <small class="text-muted">Click to view full size</small>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Signal Timeline -->
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-clock me-1"></i>Signal Timeline
                </h6>
            </div>
            <div class="card-body">
                <div class="timeline">
                    <div class="timeline-item">
                        <div class="timeline-marker bg-info"></div>
                        <div class="timeline-content">
                            <h6>Signal Received</h6>
                            <small><?= date('M j, Y H:i:s', strtotime($signal->created_at)) ?></small>
                        </div>
                    </div>
                    
                    <?php if ($signal->status !== 'pending'): ?>
                    <div class="timeline-item">
                        <div class="timeline-marker <?= $signal->status === 'executed' ? 'bg-success' : 'bg-danger' ?>"></div>
                        <div class="timeline-content">
                            <h6><?= ucfirst(str_replace('_', ' ', $signal->status)) ?></h6>
                            <small><?= $signal->updated_at ? date('M j, Y H:i:s', strtotime($signal->updated_at)) : 'Unknown' ?></small>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Signal Stats -->
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-info-circle me-1"></i>Additional Info
                </h6>
            </div>
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span>Telegram Signal ID:</span>
                    <span class="text-muted">#<?= $signal->telegram_signal_id ?></span>
                </div>
                
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span>MT Symbol:</span>
                    <span><?= $signal->mt_ticker ? '<code>' . $signal->mt_ticker . '</code>' : '<em class="text-muted">Not Set</em>' ?></span>
                </div>
                
                <?php if ($signal->trade_id): ?>
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span>Trade Reference:</span>
                    <span><code><?= $signal->trade_id ?></code></span>
                </div>
                <?php endif; ?>
                
                <hr>
                
                <div class="text-center">
                    <a href="<?= base_url('my_trading/signals?ticker_symbol=' . $signal->ticker_symbol) ?>" 
                       class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-filter me-1"></i>More <?= $signal->ticker_symbol ?> Signals
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.timeline {
    position: relative;
    padding-left: 30px;
}

.timeline-item {
    position: relative;
    margin-bottom: 20px;
}

.timeline-item:before {
    content: '';
    position: absolute;
    left: -22px;
    top: 0;
    bottom: -20px;
    width: 2px;
    background: #dee2e6;
}

.timeline-item:last-child:before {
    display: none;
}

.timeline-marker {
    position: absolute;
    left: -28px;
    top: 5px;
    width: 12px;
    height: 12px;
    border-radius: 50%;
    border: 2px solid #fff;
    box-shadow: 0 0 0 2px #dee2e6;
}

.timeline-content h6 {
    margin-bottom: 0;
    font-size: 0.9rem;
}

.timeline-content small {
    color: #6c757d;
}
</style>