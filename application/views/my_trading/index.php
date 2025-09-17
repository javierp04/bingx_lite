<div class="mb-4">
    <h1 class="h3 mb-0">
        <i class="fas fa-chart-line me-2"></i>My Trading
    </h1>
</div>

<!-- Tab Navigation - Reordenado: Active Trading primero -->
<ul class="nav nav-tabs mb-4" id="tradingTabs" role="tablist">

    <li class="nav-item" role="presentation">
        <a class="nav-link <?= $active_tab === 'active' ? 'active' : '' ?>"
            href="<?= base_url('my_trading/active') ?>">
            <i class="fas fa-tachometer-alt me-1"></i>Trading Dashboard
            <?php if (isset($dashboard_signals) && !empty($dashboard_signals)): ?>
                <span class="badge bg-success ms-1"><?= count($dashboard_signals) ?></span>
            <?php endif; ?>
        </a>
    </li>
    <li class="nav-item" role="presentation">
        <a class="nav-link <?= $active_tab === 'signals' ? 'active' : '' ?>"
            href="<?= base_url('my_trading/signals') ?>">
            <i class="fas fa-signal me-1"></i>Signal History
        </a>
    </li>
    <li class="nav-item" role="presentation">
        <a class="nav-link <?= $active_tab === 'tickers' ? 'active' : '' ?>"
            href="<?= base_url('my_trading/tickers') ?>">
            <i class="fas fa-tags me-1"></i>My Tickers
        </a>
    </li>
</ul>

<!-- Tab Content -->
<div class="tab-content" id="tradingTabContent">
    <?php
    switch ($active_tab) {
        case 'active':
            $this->load->view('my_trading/tabs/active_trading');
            break;
        case 'tickers':
            $this->load->view('my_trading/tabs/tickers');
            break;
        default: // signals
            $this->load->view('my_trading/tabs/signals');
    }
    ?>
</div>