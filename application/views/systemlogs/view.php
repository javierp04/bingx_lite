<div class="mb-4">
    <div class="d-flex justify-content-between align-items-center">
        <h1 class="h3 mb-0">
            <i class="fas fa-file-alt me-2"></i>Log Details #<?= $log->id ?>
        </h1>
        <a href="<?= base_url('systemlogs') ?>" class="btn btn-secondary">
            <i class="fas fa-arrow-left me-1"></i>Back to Logs
        </a>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Log Information</h5>
            </div>
            <div class="card-body">
                <table class="table table-bordered">
                    <tr>
                        <th width="30%">ID</th>
                        <td><?= $log->id ?></td>
                    </tr>
                    <tr>
                        <th>Action</th>
                        <td>
                            <span class="badge <?= get_badge_class($log->action) ?>">
                                <?= $log->action ?>
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <th>User</th>
                        <td>
                            <?php if ($log->user_id): ?>
                                <?php if ($user): ?>
                                    <a href="<?= base_url('users/edit/' . $user->id) ?>"><?= $user->username ?></a>
                                    (ID: <?= $user->id ?>)
                                <?php else: ?>
                                    User ID: <?= $log->user_id ?> (deleted)
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="text-muted">System</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>IP Address</th>
                        <td><?= $log->ip_address ?></td>
                    </tr>
                    <tr>
                        <th>Timestamp</th>
                        <td><?= date('Y-m-d H:i:s', strtotime($log->created_at)) ?></td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <!-- API Response Analysis -->
        <?php if ($log->action === 'api_request'): ?>
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">API Response Analysis</h5>
                </div>
                <div class="card-body">
                    <?php 
                        $api_data = json_decode($log->description);
                        $http_code = isset($api_data->http_code) ? $api_data->http_code : 'N/A';
                        $response = isset($api_data->response) ? $api_data->response : '';
                        $response_data = json_decode($response);
                        $status = ($http_code == 200 && isset($response_data->code) && $response_data->code == 0) ? 'Success' : 'Error';
                    ?>
                    <table class="table table-bordered">
                        <tr>
                            <th width="30%">Status</th>
                            <td>
                                <span class="badge <?= $status == 'Success' ? 'bg-success' : 'bg-danger' ?>">
                                    <?= $status ?>
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <th>HTTP Code</th>
                            <td>
                                <span class="badge <?= $http_code == 200 ? 'bg-success' : 'bg-danger' ?>">
                                    <?= $http_code ?>
                                </span>
                            </td>
                        </tr>
                        <?php if (isset($response_data->code)): ?>
                            <tr>
                                <th>API Code</th>
                                <td>
                                    <span class="badge <?= $response_data->code == 0 ? 'bg-success' : 'bg-danger' ?>">
                                        <?= $response_data->code ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endif; ?>
                        <?php if (isset($response_data->msg) && $response_data->msg): ?>
                            <tr>
                                <th>Message</th>
                                <td><?= $response_data->msg ?></td>
                            </tr>
                        <?php endif; ?>
                        <?php if (isset($api_data->endpoint)): ?>
                            <tr>
                                <th>Endpoint</th>
                                <td><code><?= $api_data->endpoint ?></code></td>
                            </tr>
                        <?php endif; ?>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Log Description -->
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Description</h5>
        <?php if ($is_json): ?>
            <button class="btn btn-sm btn-outline-secondary" id="toggle-format">
                <i class="fas fa-code"></i> Toggle Format
            </button>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <?php if ($is_json): ?>
            <div id="formatted-json" class="bg-light p-3 rounded">
                <pre class="mb-0"><?= htmlspecialchars($formatted_description) ?></pre>
            </div>
            <div id="raw-json" class="d-none">
                <textarea class="form-control" rows="10" readonly><?= $log->description ?></textarea>
            </div>
            
            <!-- Signature Analysis for API Requests -->
            <?php 
                $data = json_decode($log->description);
                if ($log->action === 'api_request' && isset($data->params_before_signature) && isset($data->params_with_signature)): 
                    $params = json_decode($data->params_before_signature);
            ?>
                <div class="mt-4">
                    <h6>Signature Analysis</h6>
                    <table class="table table-sm table-bordered">
                        <tr>
                            <th>Parameters (Before Signature)</th>
                            <td><code><?= htmlspecialchars($data->params_before_signature) ?></code></td>
                        </tr>
                        <tr>
                            <th>Final Request String</th>
                            <td><code><?= htmlspecialchars($data->params_with_signature) ?></code></td>
                        </tr>
                    </table>
                </div>
            <?php endif; ?>
            
        <?php else: ?>
            <p><?= nl2br(htmlspecialchars($log->description)) ?></p>
        <?php endif; ?>
    </div>
</div>

<!-- Full Request/Response (for API requests) -->
<?php if ($log->action === 'api_request' && isset($api_data)): ?>
<div class="card mb-4">
    <div class="card-header">
        <ul class="nav nav-tabs card-header-tabs" id="apiTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="request-tab" data-bs-toggle="tab" data-bs-target="#request" type="button" role="tab" aria-controls="request" aria-selected="true">Request</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="response-tab" data-bs-toggle="tab" data-bs-target="#response" type="button" role="tab" aria-controls="response" aria-selected="false">Response</button>
            </li>
        </ul>
    </div>
    <div class="card-body">
        <div class="tab-content" id="apiTabsContent">
            <div class="tab-pane fade show active" id="request" role="tabpanel" aria-labelledby="request-tab">
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <tr>
                            <th width="15%">Method</th>
                            <td><?= isset($api_data->method) ? $api_data->method : 'N/A' ?></td>
                        </tr>
                        <tr>
                            <th>URL</th>
                            <td>
                                <code class="d-block text-break"><?= isset($api_data->url) ? $api_data->url : 'N/A' ?></code>
                            </td>
                        </tr>
                        <tr>
                            <th>Headers</th>
                            <td>
                                <?php if (isset($api_data->headers) && is_array($api_data->headers)): ?>
                                    <pre class="mb-0"><?= htmlspecialchars(implode("\n", $api_data->headers)) ?></pre>
                                <?php else: ?>
                                    N/A
                                <?php endif; ?>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <?php if (isset($api_data->params_before_signature)): ?>
                    <h6 class="mt-3">Parameters (Before Signature)</h6>
                    <pre class="bg-light p-3 rounded mb-3"><?= htmlspecialchars(format_json($api_data->params_before_signature)) ?></pre>
                <?php endif; ?>
                
                <?php if (isset($api_data->params_with_signature)): ?>
                    <h6>Final Request String</h6>
                    <pre class="bg-light p-3 rounded"><?= htmlspecialchars($api_data->params_with_signature) ?></pre>
                <?php endif; ?>
            </div>
            <div class="tab-pane fade" id="response" role="tabpanel" aria-labelledby="response-tab">
                <h6>Response Body</h6>
                <pre class="bg-light p-3 rounded mb-0"><?= htmlspecialchars(format_json($api_data->response)) ?></pre>
                
                <?php if (isset($api_data->curl_error) && $api_data->curl_error): ?>
                    <h6 class="mt-3">cURL Error</h6>
                    <div class="alert alert-danger">
                        <?= $api_data->curl_error ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Webhook Data Analysis (for webhook logs) -->
<?php if (in_array($log->action, ['webhook_debug', 'webhook_error'])): ?>
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0">Webhook Analysis</h5>
    </div>
    <div class="card-body">
        <?php
            // Extract payload data from description
            $desc_parts = explode('. Data: ', $log->description, 2);
            $error_message = $desc_parts[0];
            $payload = isset($desc_parts[1]) ? $desc_parts[1] : '';
            $payload_data = json_decode($payload);
        ?>
        
        <?php if ($error_message): ?>
            <div class="alert <?= strpos($log->action, 'error') !== false ? 'alert-danger' : 'alert-info' ?>">
                <?= $error_message ?>
            </div>
        <?php endif; ?>
        
        <?php if ($payload_data): ?>
            <h6>Webhook Payload</h6>
            <table class="table table-bordered">
                <tr>
                    <th width="20%">Strategy ID</th>
                    <td><?= isset($payload_data->strategy_id) ? $payload_data->strategy_id : 'N/A' ?></td>
                </tr>
                <tr>
                    <th>Ticker</th>
                    <td><?= isset($payload_data->ticker) ? $payload_data->ticker : 'N/A' ?></td>
                </tr>
                <tr>
                    <th>Action</th>
                    <td>
                        <?php if (isset($payload_data->action)): ?>
                            <span class="badge <?= $payload_data->action === 'BUY' ? 'bg-success' : ($payload_data->action === 'SELL' ? 'bg-danger' : 'bg-warning') ?>">
                                <?= $payload_data->action ?>
                            </span>
                        <?php else: ?>
                            N/A
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th>Quantity</th>
                    <td><?= isset($payload_data->quantity) ? $payload_data->quantity : 'N/A' ?></td>
                </tr>
                <tr>
                    <th>Timeframe</th>
                    <td><?= isset($payload_data->timeframe) ? $payload_data->timeframe : 'N/A' ?></td>
                </tr>
                <tr>
                    <th>Leverage</th>
                    <td><?= isset($payload_data->leverage) ? $payload_data->leverage . 'x' : 'N/A' ?></td>
                </tr>
            </table>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<script>
    // Function to toggle between formatted and raw JSON
    document.addEventListener('DOMContentLoaded', function() {
        const toggleBtn = document.getElementById('toggle-format');
        const formattedJson = document.getElementById('formatted-json');
        const rawJson = document.getElementById('raw-json');
        
        if (toggleBtn) {
            toggleBtn.addEventListener('click', function() {
                if (formattedJson.classList.contains('d-none')) {
                    formattedJson.classList.remove('d-none');
                    rawJson.classList.add('d-none');
                    toggleBtn.innerHTML = '<i class="fas fa-code"></i> Toggle Format';
                } else {
                    formattedJson.classList.add('d-none');
                    rawJson.classList.remove('d-none');
                    toggleBtn.innerHTML = '<i class="fas fa-align-left"></i> Toggle Format';
                }
            });
        }
    });
</script>