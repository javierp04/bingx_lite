<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">
        <i class="fas fa-history me-2"></i>System Logs
    </h1>
    <button class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#cleanupModal">
        <i class="fas fa-trash me-1"></i>Cleanup Old Logs
    </button>
</div>

<!-- Filter Form -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="fas fa-filter me-1"></i>Filter Logs
        </h5>
    </div>
    <div class="card-body">
        <?= form_open('systemlogs', ['method' => 'get', 'class' => 'row g-3']) ?>
            <div class="col-md-3">
                <label for="action" class="form-label">Action Type</label>
                <select class="form-select" id="action" name="action">
                    <option value="">All Actions</option>
                    <?php foreach ($log_actions as $action): ?>
                        <option value="<?= $action ?>" <?= $filters['action'] === $action ? 'selected' : '' ?>>
                            <?= $action ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label for="user_id" class="form-label">User</label>
                <select class="form-select" id="user_id" name="user_id">
                    <option value="">All Users</option>
                    <?php foreach ($users as $user): ?>
                        <option value="<?= $user->id ?>" <?= $filters['user_id'] == $user->id ? 'selected' : '' ?>>
                            <?= $user->username ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label for="date_from" class="form-label">Date From</label>
                <input type="date" class="form-control" id="date_from" name="date_from" value="<?= $filters['date_from'] ?>">
            </div>
            <div class="col-md-3">
                <label for="date_to" class="form-label">Date To</label>
                <input type="date" class="form-control" id="date_to" name="date_to" value="<?= $filters['date_to'] ?>">
            </div>
            <div class="col-md-12">
                <label for="description" class="form-label">Search in Description</label>
                <input type="text" class="form-control" id="description" name="description" value="<?= $filters['description'] ?>" placeholder="Enter keywords to search in descriptions">
            </div>
            <div class="col-12">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-search me-1"></i>Apply Filters
                </button>
                <a href="<?= base_url('systemlogs') ?>" class="btn btn-secondary">
                    <i class="fas fa-undo me-1"></i>Reset Filters
                </a>
            </div>
        <?= form_close() ?>
    </div>
</div>

<!-- Logs Table -->
<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-striped table-hover mb-0">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>User</th>
                        <th>Action</th>
                        <th>Description</th>
                        <th>IP Address</th>
                        <th>Timestamp</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-3">No logs found</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td><?= $log->id ?></td>
                                <td>
                                    <?php if ($log->user_id): ?>
                                        <?php 
                                            $user = $this->User_model->get_user_by_id($log->user_id);
                                            echo $user ? $user->username : 'ID: ' . $log->user_id;
                                        ?>
                                    <?php else: ?>
                                        <span class="text-muted">System</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge <?= get_badge_class($log->action) ?>">
                                        <?= $log->action ?>
                                    </span>
                                </td>
                                <td>
                                    <?php 
                                        $truncated_desc = (strlen($log->description) > 100) ? 
                                            substr($log->description, 0, 100) . '...' : $log->description;
                                        echo htmlspecialchars($truncated_desc);
                                    ?>
                                </td>
                                <td><?= $log->ip_address ?></td>
                                <td><?= date('Y-m-d H:i:s', strtotime($log->created_at)) ?></td>
                                <td>
                                    <a href="<?= base_url('systemlogs/view/' . $log->id) ?>" class="btn btn-sm btn-info">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
        <div class="card-footer">
            <nav>
                <ul class="pagination justify-content-center mb-0">
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <li class="page-item <?= $i == $current_page ? 'active' : '' ?>">
                            <a class="page-link" href="<?= base_url('systemlogs') ?>?page=<?= $i ?>&action=<?= $filters['action'] ?>&description=<?= $filters['description'] ?>&date_from=<?= $filters['date_from'] ?>&date_to=<?= $filters['date_to'] ?>&user_id=<?= $filters['user_id'] ?>">
                                <?= $i ?>
                            </a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        </div>
    <?php endif; ?>
</div>

<!-- Cleanup Modal -->
<div class="modal fade" id="cleanupModal" tabindex="-1" aria-labelledby="cleanupModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="cleanupModalLabel">Cleanup Old Logs</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <?= form_open('systemlogs/cleanup') ?>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="days" class="form-label">Delete logs older than (days):</label>
                        <input type="number" class="form-control" id="days" name="days" min="1" value="30" required>
                    </div>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>This action cannot be undone!
                    </div>
                    <input type="hidden" name="confirm" value="yes">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete Old Logs</button>
                </div>
            <?= form_close() ?>
        </div>
    </div>
</div>