<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">
        <i class="fas fa-history me-2"></i>Trade History
    </h1>
</div>

<!-- Filter Form -->
<div class="card mb-4">
    <div class="card-body">
        <?= form_open('trades', ['method' => 'get', 'class' => 'row g-3']) ?>
            <div class="col-md-3">
                <label for="status" class="form-label">Status</label>
                <select class="form-select" id="status" name="status">
                    <option value="" <?= $this->input->get('status') === null ? 'selected' : '' ?>>All</option>
                    <option value="open" <?= $this->input->get('status') === 'open' ? 'selected' : '' ?>>Open</option>
                    <option value="closed" <?= $this->input->get('status') === 'closed' ? 'selected' : '' ?>>Closed</option>
                </select>
            </div>
            <div class="col-md-6">
                <label for="strategy" class="form-label">Strategy</label>
                <select class="form-select" id="strategy" name="strategy">
                    <option value="">All</option>
                    <?php foreach ($strategies as $strategy): ?>
                        <option value="<?= $strategy->id ?>" <?= $this->input->get('strategy') == $strategy->id ? 'selected' : '' ?>>
                            <?= $strategy->name ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="fas fa-filter me-1"></i>Apply Filters
                </button>
            </div>
        <?= form_close() ?>
    </div>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-striped table-hover mb-0">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Symbol</th>
                        <th>Strategy</th>
                        <th>Side</th>
                        <th>Type</th>                        
                        <th>Entry Price</th>
                        <th>Exit Price</th>
                        <th>Quantity</th>
                        <th>Leverage</th>
                        <th>PNL</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($trades)): ?>
                        <tr>
                            <td colspan="14" class="text-center py-3">No trades found</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($trades as $trade): ?>
                            <?php 
                                $pnl_class = isset($trade->pnl) && $trade->pnl >= 0 ? 'text-profit' : 'text-loss';
                                $side_class = $trade->side == 'BUY' ? 'text-success' : 'text-danger';
                                $status_class = $trade->status == 'open' ? 'bg-primary' : 'bg-success';
                            ?>
                            <tr>
                                <td><?= $trade->id ?></td>
                                <td><?= $trade->symbol ?></td>
                                <td><?= $trade->strategy_name ?></td>
                                <td class="<?= $side_class ?>"><?= $trade->side ?></td>
                                <td>
                                    <span class="badge <?= $trade->trade_type == 'futures' ? 'bg-warning text-dark' : 'bg-info' ?>">
                                        <?= ucfirst($trade->trade_type) ?>
                                    </span>
                                </td>                                
                                <td><?= $trade->entry_price ?></td>
                                <td><?= $trade->exit_price ? $trade->exit_price : '-' ?></td>
                                <td><?= $trade->quantity ?></td>
                                <td><?= $trade->leverage ?>x</td>
                                <td class="<?= $pnl_class ?>">
                                    <?= isset($trade->pnl) ? number_format($trade->pnl, 2) . ' USDT' : '-' ?>
                                </td>
                                <td>
                                    <span class="badge <?= $status_class ?>">
                                        <?= ucfirst($trade->status) ?>
                                    </span>
                                </td>
                                <td><?= date('Y-m-d H:i', strtotime($trade->created_at)) ?></td>
                                <td>
                                    <a href="<?= base_url('trades/detail/' . $trade->id) ?>" class="btn btn-sm btn-info">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <?php if ($trade->status == 'open'): ?>
                                        <a href="<?= base_url('trades/close/' . $trade->id) ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to close this trade?')">
                                            <i class="fas fa-times-circle"></i>
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>