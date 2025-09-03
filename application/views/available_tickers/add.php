<div class="mb-4">
    <h1 class="h3 mb-0">
        <i class="fas fa-tags me-2"></i>Add New Ticker
    </h1>
</div>

<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-body">
                <?= form_open('available_tickers/add') ?>
                    <div class="mb-3">
                        <label for="symbol" class="form-label">Ticker Symbol</label>
                        <input type="text" class="form-control" id="symbol" name="symbol" value="<?= set_value('symbol') ?>" required>
                        <div class="form-text">Enter the ticker symbol (e.g., EURUSD, BTCUSDT, NQ). Will be converted to uppercase.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="name" class="form-label">Display Name</label>
                        <input type="text" class="form-control" id="name" name="name" value="<?= set_value('name') ?>" required>
                        <div class="form-text">Descriptive name for the ticker (e.g., "Euro/US Dollar", "Bitcoin/USDT", "Nasdaq 100")</div>
                    </div>
                    
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="active" name="active" value="1" <?= set_checkbox('active', '1', true) ?>>
                        <label class="form-check-label" for="active">Active</label>
                        <div class="form-text">Only active tickers can be selected by users and process signals</div>
                    </div>
                    
                    <div class="d-flex justify-content-between">
                        <a href="<?= base_url('available_tickers') ?>" class="btn btn-secondary">
                            <i class="fas fa-arrow-left me-1"></i>Back
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i>Save Ticker
                        </button>
                    </div>
                <?= form_close() ?>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-lightbulb me-1"></i>Guidelines
                </h6>
            </div>
            <div class="card-body">
                <h6>Symbol Format Examples:</h6>
                <ul class="list-unstyled">
                    <li><code>EURUSD</code> - Forex pairs</li>
                    <li><code>BTCUSDT</code> - Crypto pairs</li>
                    <li><code>NQ</code> - Futures symbols</li>
                    <li><code>XAUUSD</code> - Commodities</li>
                </ul>
                
                <h6 class="mt-3">Name Examples:</h6>
                <ul class="list-unstyled">
                    <li><strong>EURUSD:</strong> Euro/US Dollar</li>
                    <li><strong>BTCUSDT:</strong> Bitcoin/USDT</li>
                    <li><strong>NQ:</strong> Nasdaq 100</li>
                    <li><strong>XAUUSD:</strong> Gold/US Dollar</li>
                </ul>
                
                <div class="alert alert-info mt-3">
                    <small>
                        <i class="fas fa-info-circle me-1"></i>
                        Make sure the symbol exactly matches what comes from your Telegram signals.
                    </small>
                </div>
            </div>
        </div>
        
        <div class="card mt-3">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-chart-line me-1"></i>Popular Tickers
                </h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-6">
                        <strong>Forex:</strong>
                        <ul class="list-unstyled small">
                            <li>EURUSD</li>
                            <li>GBPUSD</li>
                            <li>USDJPY</li>
                        </ul>
                    </div>
                    <div class="col-6">
                        <strong>Crypto:</strong>
                        <ul class="list-unstyled small">
                            <li>BTCUSDT</li>
                            <li>ETHUSDT</li>
                            <li>ADAUSDT</li>
                        </ul>
                    </div>
                </div>
                <div class="row">
                    <div class="col-6">
                        <strong>Indices:</strong>
                        <ul class="list-unstyled small">
                            <li>NQ (Nasdaq)</li>
                            <li>ES (S&P500)</li>
                            <li>YM (Dow)</li>
                        </ul>
                    </div>
                    <div class="col-6">
                        <strong>Commodities:</strong>
                        <ul class="list-unstyled small">
                            <li>XAUUSD (Gold)</li>
                            <li>XAGUSD (Silver)</li>
                            <li>USOIL (Oil)</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>