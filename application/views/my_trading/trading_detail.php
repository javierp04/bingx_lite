<div class="mb-4">
    <div class="d-flex justify-content-between align-items-center">
        <h1 class="h3 mb-0">
            <i class="fas fa-chart-line me-2"></i>Trading Detail
        </h1>
        <div>
            <a href="<?= base_url('my_trading/active') ?>" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-1"></i>Back to Dashboard
            </a>
        </div>
    </div>
</div>

<div class="row">
    <!-- LEFT COLUMN: Signal Overview -->
    <div class="col-lg-4">
        <!-- Signal Overview Card -->
        <div class="card mb-3">
            <div class="card-header bg-primary text-white">
                <h6 class="mb-0"><i class="fas fa-info-circle me-1"></i>Signal Overview</h6>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted">Signal ID</span>
                        <strong>#<?= $signal->id ?></strong>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted">Ticker</span>
                        <strong><?= $signal->ticker_symbol ?></strong>
                    </div>
                    <?php if ($signal->mt_ticker): ?>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted">MT Symbol</span>
                        <code><?= $signal->mt_ticker ?></code>
                    </div>
                    <?php endif; ?>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted">Type</span>
                        <?php
                        $op_type = strtoupper($signal->op_type);
                        $op_class = $op_type === 'LONG' ? 'bg-success' : 'bg-danger';
                        $op_icon = $op_type === 'LONG' ? 'fas fa-arrow-up' : 'fas fa-arrow-down';
                        ?>
                        <span class="badge <?= $op_class ?>">
                            <i class="<?= $op_icon ?> me-1"></i><?= $op_type ?>
                        </span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted">Status</span>
                        <?php
                        $status_class = '';
                        switch ($signal->status) {
                            case 'pending':
                            case 'claimed':
                                $status_class = 'bg-warning text-dark';
                                break;
                            case 'open':
                                $status_class = 'bg-primary';
                                break;
                            case 'closed':
                                $status_class = 'bg-secondary';
                                break;
                        }
                        ?>
                        <span class="badge <?= $status_class ?>">
                            <?= ucfirst($signal->status) ?>
                        </span>
                    </div>
                </div>

                <hr>

                <!-- Current State -->
                <div class="mb-2">
                    <h6 class="text-muted mb-2">Current State</h6>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Level</span>
                        <?php
                        $level = $signal->current_level;
                        $level_text = '-';
                        $level_class = 'bg-light text-muted';
                        if ($level == 0) {
                            $level_text = 'INIT';
                            $level_class = 'bg-secondary';
                        } elseif ($level >= 1 && $level <= 5) {
                            $level_text = 'TP' . $level;
                            $level_class = 'bg-success';
                        } elseif ($level == -1) {
                            $level_text = 'SL HIT';
                            $level_class = 'bg-danger';
                        }
                        ?>
                        <span class="badge <?= $level_class ?>"><?= $level_text ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Volume</span>
                        <span>
                            <?php if ($signal->real_volume): ?>
                                <strong><?= number_format($signal->remaining_volume ?: $signal->real_volume, 2) ?></strong> / <?= number_format($signal->real_volume, 2) ?>
                            <?php else: ?>
                                <em class="text-muted">N/A</em>
                            <?php endif; ?>
                        </span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span>PNL</span>
                        <?php
                        $pnl_class = $signal->gross_pnl > 0 ? 'text-success' : ($signal->gross_pnl < 0 ? 'text-danger' : 'text-muted');
                        $pnl_icon = $signal->gross_pnl > 0 ? 'fa-arrow-up' : ($signal->gross_pnl < 0 ? 'fa-arrow-down' : '');
                        ?>
                        <span class="<?= $pnl_class ?>">
                            <?php if ($pnl_icon): ?><i class="fas <?= $pnl_icon ?> me-1"></i><?php endif; ?>
                            <strong>$<?= number_format(abs($signal->gross_pnl), 2) ?></strong>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Timeline Card -->
        <div class="card mb-3">
            <div class="card-header">
                <h6 class="mb-0"><i class="fas fa-clock me-1"></i>Timeline</h6>
            </div>
            <div class="card-body">
                <div class="timeline">
                    <div class="timeline-item">
                        <div class="timeline-marker bg-info"></div>
                        <div class="timeline-content">
                            <h6>Received</h6>
                            <small><?= date('M j, Y H:i:s', strtotime($signal->created_at)) ?></small>
                        </div>
                    </div>
                    <?php if (in_array($signal->status, ['claimed', 'open', 'closed'])): ?>
                    <div class="timeline-item">
                        <div class="timeline-marker bg-warning"></div>
                        <div class="timeline-content">
                            <h6>Claimed by EA</h6>
                            <small><?= $signal->updated_at ? date('M j, Y H:i:s', strtotime($signal->updated_at)) : '-' ?></small>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php if (in_array($signal->status, ['open', 'closed'])): ?>
                    <div class="timeline-item">
                        <div class="timeline-marker bg-success"></div>
                        <div class="timeline-content">
                            <h6>Position Opened</h6>
                            <small><?= $signal->updated_at ? date('M j, Y H:i:s', strtotime($signal->updated_at)) : '-' ?></small>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php if ($signal->status === 'closed'): ?>
                    <div class="timeline-item">
                        <div class="timeline-marker bg-secondary"></div>
                        <div class="timeline-content">
                            <h6>Closed</h6>
                            <small><?= $signal->updated_at ? date('M j, Y H:i:s', strtotime($signal->updated_at)) : '-' ?></small>
                            <?php if ($signal->close_reason): ?>
                                <br><span class="badge bg-secondary"><?= $signal->close_reason ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Charts & Images -->
        <div class="card mb-3">
            <div class="card-header">
                <h6 class="mb-0"><i class="fas fa-images me-1"></i>Charts</h6>
            </div>
            <div class="card-body">
                <?php if ($signal->tradingview_url): ?>
                <a href="<?= $signal->tradingview_url ?>" target="_blank" class="btn btn-outline-primary btn-sm w-100 mb-2">
                    <i class="fas fa-chart-line me-1"></i>Open TradingView Chart
                </a>
                <?php endif; ?>
                <?php if (file_exists($signal->image_path)): ?>
                <a href="<?= base_url('telegram_signals/view_image/' . $signal->telegram_signal_id) ?>" target="_blank" class="btn btn-outline-info btn-sm w-100 mb-2">
                    <i class="fas fa-image me-1"></i>View Original Image
                </a>
                <?php endif; ?>
                <?php
                $path_info = pathinfo($signal->image_path);
                $cropped_filename = 'cropped-' . $path_info['filename'] . '.' . $path_info['extension'];
                $cropped_path = $path_info['dirname'] . '/' . $cropped_filename;
                if (file_exists($cropped_path)):
                ?>
                <a href="<?= base_url('telegram_signals/view_cropped_image/' . $signal->telegram_signal_id) ?>" target="_blank" class="btn btn-outline-success btn-sm w-100">
                    <i class="fas fa-crop me-1"></i>View Cropped Image
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- CENTER COLUMN: Signal & Execution Data -->
    <div class="col-lg-5">
        <!-- MT5 Signal Data Card -->
        <?php
        $mt_data = !empty($signal->mt_execution_data) ? json_decode($signal->mt_execution_data, true) : null;
        $analysis_data = !empty($signal->analysis_data) ? json_decode($signal->analysis_data, true) : null;

        // Use mt_execution_data if available, fallback to analysis_data
        $signal_data = $mt_data ?: $analysis_data;
        ?>
        <?php if ($signal_data): ?>
        <div class="card mb-3">
            <div class="card-header bg-info text-white">
                <h6 class="mb-0"><i class="fas fa-signal me-1"></i>MT5 Signal Data (Original)</h6>
            </div>
            <div class="card-body">
                <?php
                $decimals = $signal->display_decimals ?? 5;
                $entry = $signal_data['entry'] ?? 0;
                $stoploss = $signal_data['stoploss'] ?? [];
                $tps = $signal_data['tps'] ?? [];
                $sl1 = $stoploss[0] ?? 0;
                $sl2 = $stoploss[1] ?? 0;
                ?>

                <!-- Visual Price Levels -->
                <div class="price-levels mb-3">
                    <!-- TPs (top to bottom for LONG, bottom to top for SHORT) -->
                    <?php if ($op_type === 'LONG'): ?>
                        <?php for ($i = 4; $i >= 0; $i--): ?>
                            <?php if (isset($tps[$i]) && $tps[$i] > 0): ?>
                                <div class="price-level tp mb-1">
                                    <span class="badge bg-success">TP<?= $i + 1 ?></span>
                                    <span class="price"><?= number_format($tps[$i], $decimals) ?></span>
                                </div>
                            <?php endif; ?>
                        <?php endfor; ?>
                    <?php endif; ?>

                    <!-- Entry -->
                    <div class="price-level entry my-2">
                        <span class="badge bg-primary">ENTRY</span>
                        <span class="price fw-bold"><?= number_format($entry, $decimals) ?></span>
                    </div>

                    <!-- TPs for SHORT -->
                    <?php if ($op_type === 'SHORT'): ?>
                        <?php for ($i = 0; $i < count($tps); $i++): ?>
                            <?php if (isset($tps[$i]) && $tps[$i] > 0): ?>
                                <div class="price-level tp mb-1">
                                    <span class="badge bg-success">TP<?= $i + 1 ?></span>
                                    <span class="price"><?= number_format($tps[$i], $decimals) ?></span>
                                </div>
                            <?php endif; ?>
                        <?php endfor; ?>
                    <?php endif; ?>

                    <!-- SLs -->
                    <?php if ($sl2 > 0): ?>
                        <div class="price-level sl mb-1">
                            <span class="badge bg-warning text-dark">SL2</span>
                            <span class="price"><?= number_format($sl2, $decimals) ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if ($sl1 > 0): ?>
                        <div class="price-level sl mb-1">
                            <span class="badge bg-danger">SL1</span>
                            <span class="price"><?= number_format($sl1, $decimals) ?></span>
                        </div>
                    <?php endif; ?>
                </div>

                <small class="text-muted">
                    <i class="fas fa-info-circle"></i> Signal prices from Telegram (pre-correction)
                </small>
            </div>
        </div>
        <?php endif; ?>

        <!-- Execution Data Card -->
        <?php
        $exec_data = !empty($signal->execution_data) ? json_decode($signal->execution_data, true) : null;
        ?>
        <?php if ($exec_data): ?>
        <div class="card mb-3">
            <div class="card-header bg-success text-white">
                <h6 class="mb-0"><i class="fas fa-check-circle me-1"></i>Execution Data (Real)</h6>
            </div>
            <div class="card-body">
                <table class="table table-sm mb-0">
                    <tr>
                        <th>Order Type</th>
                        <td>
                            <?php
                            $order_type = $exec_data['order_type'] ?? 'N/A';
                            $order_badge_class = 'bg-secondary';
                            if (strpos($order_type, 'BUY') !== false) $order_badge_class = 'bg-success';
                            if (strpos($order_type, 'SELL') !== false) $order_badge_class = 'bg-danger';
                            ?>
                            <span class="badge <?= $order_badge_class ?>"><?= $order_type ?></span>
                        </td>
                    </tr>
                    <tr>
                        <th>Real Entry</th>
                        <td><strong><?= number_format($exec_data['real_entry_price'] ?? 0, $decimals) ?></strong></td>
                    </tr>
                    <tr>
                        <th>Real Stop Loss</th>
                        <td>
                            <?= number_format($exec_data['real_stop_loss'] ?? 0, $decimals) ?>
                            <?php
                            $is_breakeven = ($signal->real_stop_loss == $signal->real_entry_price);
                            if ($is_breakeven):
                            ?>
                                <span class="badge bg-success ms-2">
                                    <i class="fas fa-shield-alt"></i> BE
                                </span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Real Volume</th>
                        <td><?= number_format($exec_data['real_volume'] ?? 0, 2) ?> lots</td>
                    </tr>
                    <tr>
                        <th>Trade ID</th>
                        <td><code><?= $exec_data['trade_id'] ?? 'N/A' ?></code></td>
                    </tr>
                    <?php if (isset($exec_data['execution_time'])): ?>
                    <tr>
                        <th>Execution Time</th>
                        <td><?= $exec_data['execution_time'] ?></td>
                    </tr>
                    <?php endif; ?>
                </table>

                <?php if ($signal->status === 'open'): ?>
                <hr>
                <h6 class="text-muted mb-2">Current Progress</h6>
                <div class="d-flex justify-content-between mb-2">
                    <span>Current Level</span>
                    <span class="badge <?= $level_class ?>"><?= $level_text ?></span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span>Volume Closed</span>
                    <span><?= number_format($signal->volume_closed_percent, 1) ?>%</span>
                </div>
                <div class="progress mb-2" style="height: 8px;">
                    <div class="progress-bar bg-success" style="width: <?= $signal->volume_closed_percent ?>%"></div>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span>Remaining Volume</span>
                    <strong><?= number_format($signal->remaining_volume, 2) ?></strong>
                </div>
                <?php if ($signal->last_price): ?>
                <div class="d-flex justify-content-between mb-2">
                    <span>Last Price</span>
                    <strong><?= number_format($signal->last_price, $decimals) ?></strong>
                </div>
                <?php endif; ?>
                <?php endif; ?>

                <?php if ($signal->status === 'closed'): ?>
                <hr>
                <h6 class="text-muted mb-2">Final Result</h6>
                <?php if ($signal->exit_level !== null): ?>
                <div class="d-flex justify-content-between mb-2">
                    <span>Exit Level</span>
                    <?php
                    $exit_badge = 'bg-secondary';
                    $exit_text = 'Level ' . $signal->exit_level;
                    if ($signal->exit_level >= 1 && $signal->exit_level <= 5) {
                        $exit_badge = 'bg-success';
                        $exit_text = 'TP' . $signal->exit_level;
                    } elseif ($signal->exit_level == 0) {
                        $exit_badge = 'bg-danger';
                        $exit_text = 'Stop Loss';
                    }
                    ?>
                    <span class="badge <?= $exit_badge ?>"><?= $exit_text ?></span>
                </div>
                <?php endif; ?>
                <?php if ($signal->last_price): ?>
                <div class="d-flex justify-content-between mb-2">
                    <span>Final Price</span>
                    <strong><?= number_format($signal->last_price, $decimals) ?></strong>
                </div>
                <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Original Telegram Message -->
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0"><i class="fas fa-comment-dots me-1"></i>Original Message</h6>
            </div>
            <div class="card-body">
                <pre class="bg-light p-2 rounded mb-0" style="font-size: 0.85rem; white-space: pre-wrap;"><?= htmlspecialchars($signal->message_text) ?></pre>
            </div>
        </div>
    </div>

    <!-- RIGHT COLUMN: Raw Data -->
    <div class="col-lg-3">
        <!-- Raw MT Execution Data -->
        <?php if ($signal->mt_execution_data): ?>
        <div class="card mb-3">
            <div class="card-header">
                <a class="text-decoration-none text-dark d-flex justify-content-between align-items-center" data-bs-toggle="collapse" href="#rawMtData">
                    <h6 class="mb-0"><i class="fas fa-code me-1"></i>Raw MT Data</h6>
                    <i class="fas fa-chevron-down"></i>
                </a>
            </div>
            <div class="collapse" id="rawMtData">
                <div class="card-body p-2">
                    <pre class="mb-0" style="font-size: 0.75rem; max-height: 300px; overflow-y: auto;"><?= json_encode(json_decode($signal->mt_execution_data), JSON_PRETTY_PRINT) ?></pre>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Raw Execution Data -->
        <?php if ($signal->execution_data): ?>
        <div class="card mb-3">
            <div class="card-header">
                <a class="text-decoration-none text-dark d-flex justify-content-between align-items-center" data-bs-toggle="collapse" href="#rawExecData">
                    <h6 class="mb-0"><i class="fas fa-file-code me-1"></i>Raw Exec Data</h6>
                    <i class="fas fa-chevron-down"></i>
                </a>
            </div>
            <div class="collapse" id="rawExecData">
                <div class="card-body p-2">
                    <pre class="mb-0" style="font-size: 0.75rem; max-height: 300px; overflow-y: auto;"><?= json_encode(json_decode($signal->execution_data), JSON_PRETTY_PRINT) ?></pre>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Raw Analysis Data -->
        <?php if ($signal->analysis_data): ?>
        <div class="card mb-3">
            <div class="card-header">
                <a class="text-decoration-none text-dark d-flex justify-content-between align-items-center" data-bs-toggle="collapse" href="#rawAnalysisData">
                    <h6 class="mb-0"><i class="fas fa-brain me-1"></i>AI Analysis</h6>
                    <i class="fas fa-chevron-down"></i>
                </a>
            </div>
            <div class="collapse" id="rawAnalysisData">
                <div class="card-body p-2">
                    <pre class="mb-0" style="font-size: 0.75rem; max-height: 300px; overflow-y: auto;"><?= json_encode(json_decode($signal->analysis_data), JSON_PRETTY_PRINT) ?></pre>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Debug Info -->
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0"><i class="fas fa-bug me-1"></i>Debug Info</h6>
            </div>
            <div class="card-body">
                <small class="text-muted d-block mb-1">User Signal ID: <?= $signal->id ?></small>
                <small class="text-muted d-block mb-1">Telegram Signal ID: <?= $signal->telegram_signal_id ?></small>
                <small class="text-muted d-block mb-1">User ID: <?= $this->session->userdata('user_id') ?></small>
                <small class="text-muted d-block">Created: <?= $signal->created_at ?></small>
                <small class="text-muted d-block">Updated: <?= $signal->updated_at ?: '-' ?></small>
            </div>
        </div>
    </div>
</div>

<style>
/* Timeline Styles */
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

/* Price Levels Styles */
.price-levels {
    font-family: 'Courier New', monospace;
}

.price-level {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 4px 8px;
    border-radius: 4px;
}

.price-level.tp {
    background-color: rgba(25, 135, 84, 0.1);
    border-left: 3px solid #198754;
}

.price-level.entry {
    background-color: rgba(13, 110, 253, 0.15);
    border: 2px solid #0d6efd;
}

.price-level.sl {
    background-color: rgba(220, 53, 69, 0.1);
    border-left: 3px solid #dc3545;
}

.price-level .price {
    font-size: 0.95rem;
}
</style>
