<div class="mb-4">
    <h1 class="h3 mb-0">
        <i class="fas fa-key me-2"></i>Add API Key
    </h1>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="card">
            <div class="card-body">
                <?= form_open('apikeys/add') ?>
                    <div class="mb-3">
                        <label for="api_key" class="form-label">API Key</label>
                        <input type="text" class="form-control" id="api_key" name="api_key" value="<?= set_value('api_key') ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="api_secret" class="form-label">API Secret</label>
                        <input type="text" class="form-control" id="api_secret" name="api_secret" value="<?= set_value('api_secret') ?>" required>
                    </div>
                    
                    <div class="d-flex justify-content-between">
                        <a href="<?= base_url('apikeys') ?>" class="btn btn-secondary">
                            <i class="fas fa-arrow-left me-1"></i>Back
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i>Save API Key
                        </button>
                    </div>
                <?= form_close() ?>
            </div>
        </div>
    </div>
</div>