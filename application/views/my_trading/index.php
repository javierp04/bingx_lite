<div class="mb-4">
    <h1 class="h3 mb-0">
        <i class="fas fa-chart-line me-2"></i>My Trading
    </h1>
</div>

<!-- Stats Cards -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card">
            <div class="card-body text-center">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="card-title text-muted mb-0">Active Tickers</h6>
                    <i class="fas fa-tags text-primary"></i>
                </div>
                <h4 class="mb-0 text-primary"><?= $stats['active_tickers'] ?></h4>
                <small class="text-muted">Trading instruments</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card">
            <div class="card-body text-center">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="card-title text-muted mb-0">Today's Signals</h6>
                    <i class="fas fa-signal text-info"></i>
                </div>
                <h4 class="mb-0 text-info"><?= $stats['signals_today'] ?></h4>
                <small class="text-muted">Received today</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card">
            <div class="card-body text-center">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="card-title text-muted mb-0">Pending</h6>
                    <i class="fas fa-clock text-warning"></i>
                </div>
                <h4 class="mb-0 text-warning"><?= $stats['pending_signals'] ?></h4>
                <small class="text-muted">Awaiting execution</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card">
            <div class="card-body text-center">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="card-title text-muted mb-0">Execution Rate</h6>
                    <i class="fas fa-percentage text-success"></i>
                </div>
                <h4 class="mb-0 text-success"><?= $stats['execution_rate'] ?>%</h4>
                <small class="text-muted">Success rate</small>
            </div>
        </div>
    </div>
</div>

<!-- Tab Navigation - Only 2 Tabs -->
<ul class="nav nav-tabs mb-4" id="tradingTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <a class="nav-link <?= $active_tab === 'signals' ? 'active' : '' ?>" 
           href="<?= base_url('my_trading/signals') ?>">
            <i class="fas fa-signal me-1"></i>Signal History
            <?php if ($stats['pending_signals'] > 0): ?>
                <span class="badge bg-warning text-dark ms-1"><?= $stats['pending_signals'] ?></span>
            <?php endif; ?>
        </a>
    </li>
    <li class="nav-item" role="presentation">
        <a class="nav-link <?= $active_tab === 'tickers' ? 'active' : '' ?>" 
           href="<?= base_url('my_trading/tickers') ?>">
            <i class="fas fa-tags me-1"></i>My Tickers 
            <span class="badge bg-secondary ms-1"><?= $stats['active_tickers'] ?></span>
        </a>
    </li>
</ul>

<!-- Tab Content -->
<div class="tab-content" id="tradingTabContent">
    <?php 
    switch($active_tab) {
        case 'tickers':
            $this->load->view('my_trading/tabs/tickers');
            break;
        default: // signals
            $this->load->view('my_trading/tabs/signals');
    }
    ?>
</div>