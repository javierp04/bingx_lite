<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($title) ? $title . ' - ' : '' ?>BingX Trading System</title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">

    <!-- Custom CSS -->
    <style>
        body {
            padding-top: 56px;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .content {
            flex: 1;
        }

        .footer {
            margin-top: auto;
            background-color: #f8f9fa;
            padding: 1rem 0;
        }

        .navbar-brand {
            font-weight: bold;
        }

        .card {
            margin-bottom: 20px;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        }

        .bg-profit {
            background-color: rgba(40, 167, 69, 0.1);
        }

        .bg-loss {
            background-color: rgba(220, 53, 69, 0.1);
        }

        .text-profit {
            color: #28a745;
        }

        .text-loss {
            color: #dc3545;
        }

        .nav-tabs .nav-link.active {
            font-weight: bold;
        }

        .table td {
            white-space: normal !important;
        }

        .table .description-cell {
            max-width: 300px;
            word-wrap: break-word;
        }
    </style>
</head>

<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark fixed-top">
        <div class="container">
            <a class="navbar-brand" href="<?= base_url('dashboard') ?>">
                <i class="fas fa-robot me-2"></i>BingX Trading System
            </a>

            <?php if ($this->session->userdata('logged_in')) : ?>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                    <span class="navbar-toggler-icon"></span>
                </button>

                <div class="collapse navbar-collapse" id="navbarNav">
                    <ul class="navbar-nav me-auto">
                        <!-- Dashboard -->
                        <li class="nav-item">
                            <a class="nav-link" href="<?= base_url('dashboard') ?>">
                                <i class="fas fa-tachometer-alt me-1"></i>Dashboard
                            </a>
                        </li>

                        <!-- My Trading -->
                        <li class="nav-item">
                            <a class="nav-link" href="<?= base_url('my_trading') ?>">
                                <i class="fas fa-chart-line me-1"></i>My Trading
                            </a>
                        </li>

                        <!-- Trading Dropdown -->
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="tradingDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-exchange-alt me-1"></i>Trading
                            </a>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="<?= base_url('trades') ?>">
                                    <i class="fas fa-history me-2"></i>Trade History
                                </a></li>
                                <li><a class="dropdown-item" href="<?= base_url('apikeys') ?>">
                                    <i class="fas fa-key me-2"></i>API Keys
                                </a></li>
                            </ul>
                        </li>

                        <!-- Admin Dropdown (Solo Admin) -->
                        <?php if ($this->session->userdata('role') == 'admin') : ?>
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle" href="#" id="adminDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="fas fa-cog me-1"></i>Admin
                                </a>
                                <ul class="dropdown-menu">
                                    <li><a class="dropdown-item" href="<?= base_url('signals') ?>">
                                        <i class="fas fa-signal me-2"></i>MT Signals Management
                                    </a></li>
                                    <li><a class="dropdown-item" href="<?= base_url('strategies') ?>">
                                        <i class="fas fa-chart-line me-2"></i>Strategies Management
                                    </a></li>
                                    <li><a class="dropdown-item" href="<?= base_url('users') ?>">
                                        <i class="fas fa-users me-2"></i>Users Management
                                    </a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item" href="<?= base_url('telegram_signals') ?>">
                                        <i class="fas fa-paper-plane me-2"></i>All Telegram Signals
                                    </a></li>
                                    <li><a class="dropdown-item" href="<?= base_url('available_tickers') ?>">
                                        <i class="fas fa-tags me-2"></i>Manage Tickers
                                    </a></li>
                                </ul>
                            </li>
                        <?php endif; ?>

                        <!-- System Logs (Solo Admin) -->
                        <?php if ($this->session->userdata('role') == 'admin') : ?>
                            <li class="nav-item">
                                <a class="nav-link" href="<?= base_url('systemlogs') ?>">
                                    <i class="fas fa-file-alt me-1"></i>System Logs
                                </a>
                            </li>
                        <?php endif; ?>

                        <!-- Debug Dropdown -->
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="debugDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-bug me-1"></i>Debug
                            </a>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="<?= base_url('debug') ?>">
                                    <i class="fas fa-chart-line me-2"></i>TradingView Debug
                                </a></li>
                                <li><a class="dropdown-item" href="<?= base_url('debug/telegram') ?>">
                                    <i class="fas fa-paper-plane me-2"></i>Telegram Debug
                                </a></li>
                            </ul>
                        </li>
                    </ul>

                    <ul class="navbar-nav">
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                                <i class="fas fa-user me-1"></i><?= $this->session->userdata('username') ?>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item" href="<?= base_url('logout') ?>"><i class="fas fa-sign-out-alt me-1"></i>Logout</a></li>
                            </ul>
                        </li>
                    </ul>
                </div>
            <?php endif; ?>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="content py-4">
        <div class="container">
            <?php if ($this->session->flashdata('success')) : ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo html_entity_decode($this->session->flashdata('success')); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <?php if ($this->session->flashdata('error')) : ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?= $this->session->flashdata('error') ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <?php if (validation_errors()) : ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?= validation_errors() ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>