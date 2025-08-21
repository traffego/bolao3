<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? $pageTitle . ' - ' . APP_NAME : APP_NAME ?> Admin</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Sweet Alert -->
    <link type="text/css" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.min.css" rel="stylesheet">

    <!-- Notyf -->
    <link type="text/css" href="https://cdn.jsdelivr.net/npm/notyf@3.10.0/notyf.min.css" rel="stylesheet">

    <!-- Volt CSS -->
    <link type="text/css" href="../public/css/styles.css" rel="stylesheet">
    <link type="text/css" href="../public/css/admin.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- AdminLTE -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <!-- Google Fonts (opcional) -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700">
    
    <style>
        .conteudo-principal {
            float: right;
            width: calc(100% - 250px); /* Adjust based on sidebar width */
        }

        /* Hero Cards Styles */
        .avatar {
            width: 48px;
            height: 48px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .avatar.avatar-lg {
            width: 56px;
            height: 56px;
        }
        
        .avatar i {
            font-size: 24px;
        }
        
        .bg-green-soft {
            background-color: rgba(40, 167, 69, 0.1);
        }
        
        .bg-warning-soft {
            background-color: rgba(255, 193, 7, 0.1);
        }
        
        .bg-success-soft {
            background-color: rgba(40, 167, 69, 0.1);
        }
        
        .bg-danger-soft {
            background-color: rgba(220, 53, 69, 0.1);
        }
        
        .text-green {
            color: #28a745;
        }
        
        .card {
            transition: transform 0.2s ease-in-out;
        }
        
        .card:hover {
            transform: translateY(-5px);
        }
        
        .stretched-link::after {
            position: absolute;
            top: 0;
            right: 0;
            bottom: 0;
            left: 0;
            z-index: 1;
            content: "";
        }
    </style>
    
    <?php if (isset($extraCss)): ?>
        <?= $extraCss ?>
    <?php endif; ?>
</head>
<body class="hold-transition sidebar-mini">
<div class="wrapper">

    <!-- Sidebar -->
    <aside class="main-sidebar sidebar-dark-primary elevation-4">
        <!-- Logo -->
        <a href="<?= APP_URL ?>/admin/" class="brand-link">
            <img src="<?= APP_URL ?>/public/img/logo.png" alt="Logo" class="brand-image img-circle elevation-3" style="opacity: .8">
            <span class="brand-text font-weight-light">Bolão Vitimba</span>
        </a>
        <!-- Menu -->
        <div class="sidebar">
            <nav class="mt-2">
                <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu">
                    <li class="nav-item">
                        <a href="<?= APP_URL ?>/admin/" class="nav-link">
                            <i class="nav-icon fas fa-tachometer-alt"></i>
                            <p>Dashboard</p>
                        </a>
                    </li>
                    <li class="nav-item has-treeview">
                        <a href="#" class="nav-link">
                            <i class="nav-icon fas fa-trophy"></i>
                            <p>Bolões<i class="right fas fa-angle-left"></i></p>
                        </a>
                        <ul class="nav nav-treeview">
                            <li class="nav-item">
                                <a href="<?= APP_URL ?>/admin/boloes.php" class="nav-link">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>Listar Bolões</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="<?= APP_URL ?>/admin/novo-bolao.php" class="nav-link">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>Novo Bolão</p>
                                </a>
                            </li>
                        </ul>
                    </li>
                    <li class="nav-item">
                        <a href="<?= APP_URL ?>/admin/jogadores.php" class="nav-link">
                            <i class="nav-icon fas fa-users"></i>
                            <p>Jogadores</p>
                        </a>
                    </li>
                    <li class="nav-item has-treeview">
                        <a href="#" class="nav-link">
                            <i class="nav-icon fas fa-chart-pie"></i>
                            <p>Resultados<i class="right fas fa-angle-left"></i></p>
                        </a>
                        <ul class="nav nav-treeview">
                            <li class="nav-item">
                                <a href="<?= APP_URL ?>/admin/gerenciar-resultados.php" class="nav-link">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>Gerenciar Resultados</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="<?= APP_URL ?>/admin/atualizar-jogos.php" class="nav-link">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>Atualizar Agora</p>
                                </a>
                            </li>
                        </ul>
                    </li>
                    <li class="nav-item has-treeview">
                        <a href="#" class="nav-link">
                            <i class="nav-icon fas fa-dollar-sign"></i>
                            <p>Financeiro<i class="right fas fa-angle-left"></i></p>
                        </a>
                        <ul class="nav nav-treeview">
                            <li class="nav-item">
                                <a href="<?= APP_URL ?>/admin/pagamentos.php" class="nav-link">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>Pagamentos</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="<?= APP_URL ?>/admin/saques.php" class="nav-link">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>Saques</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="<?= APP_URL ?>/admin/logs-financeiros.php" class="nav-link">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>Logs Financeiros</p>
                                </a>
                            </li>
                        </ul>
                    </li>
                    <li class="nav-item">
                        <a href="<?= APP_URL ?>/admin/afiliados.php" class="nav-link">
                            <i class="nav-icon fas fa-handshake"></i>
                            <p>Afiliados</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="<?= APP_URL ?>/admin/relatorios.php" class="nav-link">
                            <i class="nav-icon fas fa-chart-bar"></i>
                            <p>Relatórios</p>
                        </a>
                    </li>

                    <li class="nav-item">
                        <a href="<?= APP_URL ?>/admin/configuracoes.php" class="nav-link">
                            <i class="nav-icon fas fa-cogs"></i>
                            <p>Configurações</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="<?= APP_URL ?>/logout.php" class="nav-link">
                            <i class="nav-icon fas fa-sign-out-alt"></i>
                            <p>Sair</p>
                        </a>
                    </li>
                </ul>
            </nav>
        </div>
    </aside>

    <!-- Content Wrapper -->
    <div class="content-wrapper">
        <!-- Status Topbar -->
        <?php include __DIR__ . '/status_topbar.php'; ?>
        
        <!-- Content Header -->
        <?php if (!isset($hasHeroSection)): ?>
        <section class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1><?= isset($pageTitle) ? $pageTitle : 'Dashboard' ?></h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="<?= APP_URL ?>/admin/">Home</a></li>
                            <?php if (isset($pageTitle)): ?>
                                <li class="breadcrumb-item active"><?= $pageTitle ?></li>
                            <?php endif; ?>
                        </ol>
                    </div>
                </div>
            </div>
        </section>
        <?php endif; ?>

        <!-- Main content -->
        <section class="content">
            <div class="container-fluid">