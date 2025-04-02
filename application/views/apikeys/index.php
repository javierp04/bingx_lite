<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">
        <i class="fas fa-key me-2"></i>API Keys Configuration
    </h1>
    <a href="<?= base_url('apikeys/add') ?>" class="btn btn-primary">
        <i class="fas fa-plus-circle me-1"></i>Add API Key
    </a>
</div>

<div class="row mb-4">
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-flask me-1"></i>Sandbox Environment
                </h5>
            </div>
            <div class="card-body">
                <?php
                $sandbox_key = null;
                foreach ($api_keys as $key) {
                    if ($key->environment === 'sandbox') {
                        $sandbox_key = $key;
                        break;
                    }
                }
                ?>
                
                <?php if ($sandbox_key): ?>
                    <div class="mb-3">
                        <label class="form-label">API Key</label>
                        <div class="input-group">
                            <input type="text" class="form-control" value="<?= substr($sandbox_key->api_key, 0, 4) . '...' . substr($sandbox_key->api_key, -4) ?>" readonly>
                            <button class="btn btn-outline-secondary" type="button" onclick="toggleApiKey(this, '<?= $sandbox_key->api_key ?>')">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">API Secret</label>
                        <div class="input-group">
                            <input type="text" class="form-control" value="<?= substr($sandbox_key->api_secret, 0, 4) . '...' . substr($sandbox_key->api_secret, -4) ?>" readonly>
                            <button class="btn btn-outline-secondary" type="button" onclick="toggleApiKey(this, '<?= $sandbox_key->api_secret ?>')">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="text-end">
                        <a href="<?= base_url('apikeys/edit/' . $sandbox_key->id) ?>" class="btn btn-primary">
                            <i class="fas fa-edit me-1"></i>Edit
                        </a>
                        <a href="<?= base_url('apikeys/delete/' . $sandbox_key->id) ?>" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete this API key?')">
                            <i class="fas fa-trash me-1"></i>Delete
                        </a>
                    </div>
                <?php else: ?>
                    <div class="alert alert-warning mb-0">
                        <i class="fas fa-exclamation-triangle me-2"></i>No API keys configured for Sandbox environment.
                        <a href="<?= base_url('apikeys/add') ?>" class="alert-link">Add API Keys</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-rocket me-1"></i>Production Environment
                </h5>
            </div>
            <div class="card-body">
                <?php
                $production_key = null;
                foreach ($api_keys as $key) {
                    if ($key->environment === 'production') {
                        $production_key = $key;
                        break;
                    }
                }
                ?>
                
                <?php if ($production_key): ?>
                    <div class="mb-3">
                        <label class="form-label">API Key</label>
                        <div class="input-group">
                            <input type="text" class="form-control" value="<?= substr($production_key->api_key, 0, 4) . '...' . substr($production_key->api_key, -4) ?>" readonly>
                            <button class="btn btn-outline-secondary" type="button" onclick="toggleApiKey(this, '<?= $production_key->api_key ?>')">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">API Secret</label>
                        <div class="input-group">
                            <input type="text" class="form-control" value="<?= substr($production_key->api_secret, 0, 4) . '...' . substr($production_key->api_secret, -4) ?>" readonly>
                            <button class="btn btn-outline-secondary" type="button" onclick="toggleApiKey(this, '<?= $production_key->api_secret ?>')">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="text-end">
                        <a href="<?= base_url('apikeys/edit/' . $production_key->id) ?>" class="btn btn-primary">
                            <i class="fas fa-edit me-1"></i>Edit
                        </a>
                        <a href="<?= base_url('apikeys/delete/' . $production_key->id) ?>" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete this API key?')">
                            <i class="fas fa-trash me-1"></i>Delete
                        </a>
                    </div>
                <?php else: ?>
                    <div class="alert alert-warning mb-0">
                        <i class="fas fa-exclamation-triangle me-2"></i>No API keys configured for Production environment.
                        <a href="<?= base_url('apikeys/add') ?>" class="alert-link">Add API Keys</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="fas fa-info-circle me-1"></i>BingX API Information
        </h5>
    </div>
    <div class="card-body">
        <p>To use this system, you need to configure your BingX API keys. Follow these steps to get your API keys:</p>
        <ol>
            <li>Log in to your BingX account</li>
            <li>Navigate to Account Settings > API Management</li>
            <li>Create a new API key with trading permissions</li>
            <li>Copy the API Key and Secret Key</li>
            <li>Enter them in this system for the appropriate environment (Sandbox/Production)</li>
        </ol>
        <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle me-2"></i>Never share your API keys with anyone. Make sure to configure appropriate IP restrictions on BingX platform.
        </div>
    </div>
</div>

<script>
    function toggleApiKey(buttonElement, fullKey) {
        const inputElement = buttonElement.previousElementSibling;
        const iconElement = buttonElement.querySelector('i');
        
        if (inputElement.value.includes('...')) {
            inputElement.value = fullKey;
            iconElement.classList.remove('fa-eye');
            iconElement.classList.add('fa-eye-slash');
        } else {
            inputElement.value = fullKey.substring(0, 4) + '...' + fullKey.substring(fullKey.length - 4);
            iconElement.classList.remove('fa-eye-slash');
            iconElement.classList.add('fa-eye');
        }
    }
</script>