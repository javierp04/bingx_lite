<div class="mb-4">
    <div class="d-flex justify-content-between align-items-center">
        <h1 class="h3 mb-0">
            <i class="fas fa-broadcast-tower me-2"></i>ATVIP Signal #<?= $signal->id ?>
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
                            <?php
                            $status_classes = [
                                'pending' => 'bg-warning text-dark',
                                'cropping' => 'bg-info',
                                'analyzing' => 'bg-primary',
                                'completed' => 'bg-success',
                                'pending_review' => 'bg-orange',
                                'failed_crop' => 'bg-danger',
                                'failed_analysis' => 'bg-danger',
                                'failed_download' => 'bg-danger'
                            ];
                            $class = isset($status_classes[$signal->status]) ? $status_classes[$signal->status] : 'bg-secondary';
                            ?>
                            <span class="badge <?= $class ?>" <?= $signal->status === 'pending_review' ? 'style="background-color: #fd7e14 !important;"' : '' ?>>
                                <?= ucfirst(str_replace('_', ' ', $signal->status)) ?>
                            </span>
                            <?php if (isset($signal->ai_validated) && $signal->ai_validated !== null): ?>
                                <?php if ($signal->ai_validated): ?>
                                    <span class="badge bg-success"><i class="fas fa-check-double me-1"></i>Dual Validated</span>
                                <?php else: ?>
                                    <span class="badge bg-danger"><i class="fas fa-exclamation-triangle me-1"></i>AI Mismatch</span>
                                <?php endif; ?>
                            <?php endif; ?>
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

        <!-- Dual AI Comparison (when available) -->
        <?php if (!empty($signal->analysis_openai) || !empty($signal->analysis_claude)): ?>
        <div class="card mb-4 <?= ($signal->status === 'pending_review') ? 'border-warning' : 'border-success' ?>">
            <div class="card-header" 
                 style="<?= ($signal->status === 'pending_review') ? 'background-color: #fd7e14; color: white;' : '' ?>">
                <?php
                $tmp_oai = $signal->analysis_openai ? json_decode($signal->analysis_openai, true) : null;
                $num_rounds = 1;
                if ($tmp_oai !== null && isset($tmp_oai[0]) && is_array($tmp_oai[0])) {
                    $num_rounds = count($tmp_oai);
                }
                ?>
                <h5 class="mb-0 d-flex align-items-center justify-content-between">
                    <span>
                    <?php if ($signal->status === 'pending_review'): ?>
                        <i class="fas fa-exclamation-triangle me-1"></i>Dual AI Comparison &mdash; MISMATCH
                    <?php else: ?>
                        <i class="fas fa-check-double me-1"></i>Dual AI Comparison &mdash; MATCH
                    <?php endif; ?>
                    </span>
                    <span class="badge <?= $num_rounds > 1 ? 'bg-warning text-dark' : 'bg-info' ?>" style="font-size: 0.75rem;">
                        <i class="fas fa-<?= $num_rounds > 1 ? 'redo' : 'check' ?> me-1"></i><?= $num_rounds ?> ronda<?= $num_rounds > 1 ? 's' : '' ?>
                    </span>
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <?php
                    $openai_raw = $signal->analysis_openai ? json_decode($signal->analysis_openai, true) : null;
                    $claude_raw = $signal->analysis_claude ? json_decode($signal->analysis_claude, true) : null;

                    $openai_responses = [];
                    if ($openai_raw !== null) {
                        $openai_responses = (isset($openai_raw[0]) && is_array($openai_raw[0])) ? $openai_raw : [$openai_raw];
                    }
                    $claude_responses = [];
                    if ($claude_raw !== null) {
                        $claude_responses = (isset($claude_raw[0]) && is_array($claude_raw[0])) ? $claude_raw : [$claude_raw];
                    }
                    $total_rounds = max(count($openai_responses), count($claude_responses));
                    ?>

                    <?php for ($round = 0; $round < $total_rounds; $round++): ?>
                        <?php
                        $oai = isset($openai_responses[$round]) ? $openai_responses[$round] : null;
                        $cld = isset($claude_responses[$round]) ? $claude_responses[$round] : null;
                        $oai_prices = ($oai && isset($oai['label_prices'])) ? $oai['label_prices'] : [];
                        $cld_prices = ($cld && isset($cld['label_prices'])) ? $cld['label_prices'] : [];
                        $max_prices = max(count($oai_prices), count($cld_prices));
                        $oai_ot = $oai && isset($oai['op_type']) ? strtoupper($oai['op_type']) : '—';
                        $cld_ot = $cld && isset($cld['op_type']) ? strtoupper($cld['op_type']) : '—';
                        $ot_match = ($oai_ot === $cld_ot);
                        ?>

                        <?php if ($total_rounds > 1): ?>
                            <div class="small fw-bold text-muted mb-2 <?= $round > 0 ? 'mt-3 pt-2 border-top' : '' ?>">
                                <i class="fas fa-sync-alt me-1"></i>Ronda <?= $round + 1 ?>
                            </div>
                        <?php endif; ?>

                        <div class="row mb-1">
                            <div class="col-6 text-center">
                                <span class="badge bg-success"><i class="fas fa-robot me-1"></i>OpenAI</span>
                                <strong class="small ms-1">op_type:</strong>
                                <span class="badge <?= $oai_ot === 'LONG' ? 'bg-success' : ($oai_ot === 'SHORT' ? 'bg-danger' : 'bg-secondary') ?> <?= !$ot_match ? 'border border-danger border-2' : '' ?>"><?= $oai_ot ?></span>
                                <span class="text-muted small">(<?= count($oai_prices) ?>)</span>
                            </div>
                            <div class="col-6 text-center">
                                <span class="badge bg-primary"><i class="fas fa-robot me-1"></i>Claude</span>
                                <strong class="small ms-1">op_type:</strong>
                                <span class="badge <?= $cld_ot === 'LONG' ? 'bg-success' : ($cld_ot === 'SHORT' ? 'bg-danger' : 'bg-secondary') ?> <?= !$ot_match ? 'border border-danger border-2' : '' ?>"><?= $cld_ot ?></span>
                                <span class="text-muted small">(<?= count($cld_prices) ?>)</span>
                            </div>
                        </div>

                        <?php for ($i = 0; $i < $max_prices; $i++):
                            $p_oai = isset($oai_prices[$i]) ? $oai_prices[$i] : null;
                            $p_cld = isset($cld_prices[$i]) ? $cld_prices[$i] : null;
                            $is_diff = ($p_oai !== null && $p_cld !== null && (float)$p_oai !== (float)$p_cld)
                                    || ($p_oai === null && $p_cld !== null)
                                    || ($p_oai !== null && $p_cld === null);
                        ?>
                        <div class="row small" style="margin: 0; <?= $is_diff ? 'background: #f8d7da; border-left: 3px solid #dc3545;' : '' ?>">
                            <div class="col-6 py-0 ps-4">
                                <?= ($i + 1) ?>.
                                <?php if ($p_oai !== null): ?>
                                    <span class="<?= $is_diff ? 'text-danger fw-bold' : '' ?>"><?= $p_oai ?></span>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </div>
                            <div class="col-6 py-0 ps-4">
                                <?= ($i + 1) ?>.
                                <?php if ($p_cld !== null): ?>
                                    <span class="<?= $is_diff ? 'text-danger fw-bold' : '' ?>"><?= $p_cld ?></span>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endfor; ?>
                    <?php endfor; ?>

                    <div class="row mt-3">
                        <div class="col-6">
                            <details>
                                <summary class="small text-muted">Raw OpenAI JSON</summary>
                                <pre class="bg-light p-2 rounded small mt-1 mb-0"><?= json_encode($openai_raw, JSON_PRETTY_PRINT) ?></pre>
                            </details>
                        </div>
                        <div class="col-6">
                            <details>
                                <summary class="small text-muted">Raw Claude JSON</summary>
                                <pre class="bg-light p-2 rounded small mt-1 mb-0"><?= json_encode($claude_raw, JSON_PRETTY_PRINT) ?></pre>
                            </details>
                        </div>
                    </div>

                    <?php if ($signal->status === 'pending_review'): ?>
                    <hr>
                    <div class="text-center">
                        <p class="small text-muted mb-2">Seleccionar resultado para aprobar esta señal:</p>
                        <div class="d-flex justify-content-center gap-2">
                            <?php if ($signal->analysis_openai): ?>
                            <form action="<?= base_url('telegram_signals/resolve/' . $signal->id) ?>" method="post">
                                <input type="hidden" name="provider" value="openai">
                                <button type="submit" class="btn btn-success btn-sm" onclick="return confirm('Aprobar con resultado de OpenAI?')">
                                    <i class="fas fa-check me-1"></i>Usar OpenAI
                                </button>
                            </form>
                            <?php endif; ?>
                            <?php if ($signal->analysis_claude): ?>
                            <form action="<?= base_url('telegram_signals/resolve/' . $signal->id) ?>" method="post">
                                <input type="hidden" name="provider" value="claude">
                                <button type="submit" class="btn btn-primary btn-sm" onclick="return confirm('Aprobar con resultado de Claude?')">
                                    <i class="fas fa-check me-1"></i>Usar Claude
                                </button>
                            </form>
                            <?php endif; ?>
                            <form action="<?= base_url('telegram_signals/resolve/' . $signal->id) ?>" method="post">
                                <input type="hidden" name="provider" value="discard">
                                <button type="submit" class="btn btn-outline-danger btn-sm" onclick="return confirm('Descartar esta señal?')">
                                    <i class="fas fa-trash me-1"></i>Descartar
                                </button>
                            </form>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if (in_array($signal->status, ['completed', 'pending_review']) && $signal->analysis_data): ?>
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">AI Analysis Result (Final)</h5>
            </div>
            <div class="card-body">
                <pre class="bg-light p-3 rounded mb-0"><?= json_encode(json_decode($signal->analysis_data), JSON_PRETTY_PRINT) ?></pre>
            </div>
        </div>
        <?php endif; ?>

        <!-- Original Message -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Original ATVIP Message</h5>
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
                        <a href="<?= base_url('telegram_signals/view_image/' . $signal->id) ?>" 
                           target="_blank" class="btn btn-outline-info">
                            <i class="fas fa-image me-1"></i>Original Image
                        </a>
                    <?php endif; ?>

                    <?php if ($cropped_image_exists): ?>
                        <a href="<?= base_url('telegram_signals/view_cropped_image/' . $signal->id) ?>" 
                           target="_blank" class="btn btn-outline-success">
                            <i class="fas fa-crop me-1"></i>Cropped Image
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
        <!-- Original Image Preview -->
        <?php if (file_exists($signal->image_path)): ?>
            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="fas fa-image me-1"></i>Original Chart Image
                    </h6>
                </div>
                <div class="card-body text-center">
                    <img src="<?= base_url('telegram_signals/view_image/' . $signal->id) ?>" 
                         class="img-fluid rounded" 
                         style="max-height: 250px; cursor: pointer;"
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
                        <i class="fas fa-image me-1"></i>Original Chart Image
                    </h6>
                </div>
                <div class="card-body text-center">
                    <div class="text-muted py-4">
                        <i class="fas fa-image fa-3x mb-2"></i>
                        <p>Original image not found</p>
                        <small>Path: <?= $signal->image_path ?></small>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Cropped Image Preview -->
        <?php if ($cropped_image_exists): ?>
            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="fas fa-crop me-1"></i>Cropped Chart Image
                    </h6>
                </div>
                <div class="card-body text-center">
                    <img src="<?= base_url('telegram_signals/view_cropped_image/' . $signal->id) ?>" 
                         class="img-fluid rounded" 
                         style="max-height: 250px; cursor: pointer;"
                         onclick="window.open('<?= base_url('telegram_signals/view_cropped_image/' . $signal->id) ?>', '_blank')">
                    <div class="mt-2">
                        <small class="text-muted">Click to view full size</small>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="fas fa-crop me-1"></i>Cropped Chart Image
                    </h6>
                </div>
                <div class="card-body text-center">
                    <div class="text-muted py-4">
                        <i class="fas fa-crop fa-3x mb-2"></i>
                        <p>Cropped image not available</p>
                        <small>Not processed yet or crop failed</small>
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
                            <?php
                            $status_classes = [
                                'pending' => 'bg-warning text-dark',
                                'cropping' => 'bg-info',
                                'analyzing' => 'bg-primary',
                                'completed' => 'bg-success',
                                'pending_review' => 'bg-secondary',
                                'failed_crop' => 'bg-danger',
                                'failed_analysis' => 'bg-danger',
                                'failed_download' => 'bg-danger'
                            ];
                            $class = isset($status_classes[$recent->status]) ? $status_classes[$recent->status] : 'bg-secondary';
                            ?>
                            <span class="badge <?= $class ?> badge-sm" <?= $recent->status === 'pending_review' ? 'style="background-color: #fd7e14 !important;"' : '' ?>>
                                <?= ucfirst(str_replace('_', ' ', $recent->status)) ?>
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