<div class="mb-4">
    <div class="d-flex justify-content-between align-items-center">
        <h1 class="h3 mb-0">
            <i class="fas fa-image me-2"></i>Strategy Image - <?= $strategy->name ?>
        </h1>
        <a href="<?= base_url('strategies') ?>" class="btn btn-secondary">
            <i class="fas fa-arrow-left me-1"></i>Back to Strategies
        </a>
    </div>
</div>

<div class="card">
    <div class="card-body text-center">
        <img src="<?= base_url(UPLOAD_PATH . 'strategies/' . $strategy->image) ?>" class="img-fluid rounded" alt="Strategy Image">
        
        <div class="mt-4">
            <h5>Strategy Details</h5>
            <table class="table table-bordered">
                <tr>
                    <th>Strategy ID</th>
                    <td><code><?= $strategy->strategy_id ?></code></td>
                </tr>
                <tr>
                    <th>Name</th>
                    <td><?= $strategy->name ?></td>
                </tr>
                <tr>
                    <th>Type</th>
                    <td>
                        <span class="badge <?= $strategy->type == 'futures' ? 'bg-warning text-dark' : 'bg-info' ?>">
                            <?= ucfirst($strategy->type) ?>
                        </span>
                    </td>
                </tr>
                <?php if ($strategy->description): ?>
                <tr>
                    <th>Description</th>
                    <td><?= $strategy->description ?></td>
                </tr>
                <?php endif; ?>
            </table>
        </div>
    </div>
</div>