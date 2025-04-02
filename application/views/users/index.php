<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">
        <i class="fas fa-users me-2"></i>User Management
    </h1>
    <a href="<?= base_url('users/add') ?>" class="btn btn-primary">
        <i class="fas fa-user-plus me-1"></i>Add New User
    </a>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-striped table-hover mb-0">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($users)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-3">No users found</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?= $user->id ?></td>
                                <td><?= $user->username ?></td>
                                <td><?= $user->email ?></td>
                                <td>
                                    <span class="badge <?= $user->role == 'admin' ? 'bg-danger' : 'bg-primary' ?>">
                                        <?= ucfirst($user->role) ?>
                                    </span>
                                </td>
                                <td><?= date('Y-m-d', strtotime($user->created_at)) ?></td>
                                <td>
                                    <?php if ($user->id != $this->session->userdata('user_id')): ?>
                                        <a href="<?= base_url('users/edit/' . $user->id) ?>" class="btn btn-sm btn-primary">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="<?= base_url('users/delete/' . $user->id) ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this user?')">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    <?php else: ?>
                                        <a href="<?= base_url('users/edit/' . $user->id) ?>" class="btn btn-sm btn-primary">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <button class="btn btn-sm btn-danger" disabled title="Cannot delete your own account">
                                            <i class="fas fa-trash"></i>
                                        </button>
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