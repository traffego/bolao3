<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? $pageTitle . ' - ' . APP_NAME : APP_NAME ?> Admin</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    
    <!-- Admin CSS -->
    <link rel="stylesheet" href="<?= APP_URL ?>/public/css/admin.css">
    
    <?php if (isset($extraCss)): ?>
        <?= $extraCss ?>
    <?php endif; ?>
</head>
<body>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <nav id="sidebar" class="col-md-3 col-lg-2 d-md-block bg-dark sidebar collapse">
            <div class="position-sticky pt-3">
                <div class="text-center mb-4">
                    <a href="<?= APP_URL ?>/admin/" class="text-decoration-none">
                        <i class="bi bi-shield-lock text-light fs-1"></i>
                        <h5 class="text-light mt-1"><?= APP_NAME ?> Admin</h5>
                    </a>
                </div>
                
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link" href="<?= APP_URL ?>/admin/">
                            <i class="bi bi-speedometer2"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#" data-bs-toggle="collapse" data-bs-target="#submenuBoloes">
                            <i class="bi bi-trophy"></i> Bolões <i class="bi bi-chevron-down float-end"></i>
                        </a>
                        <div class="collapse" id="submenuBoloes">
                            <ul class="nav flex-column ms-3">
                                <li class="nav-item">
                                    <a class="nav-link py-1" href="<?= APP_URL ?>/admin/boloes.php">
                                        <i class="bi bi-list"></i> Listar Todos
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link py-1" href="<?= APP_URL ?>/admin/novo-bolao.php">
                                        <i class="bi bi-plus-circle"></i> Novo Bolão
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= APP_URL ?>/admin/jogadores.php">
                            <i class="bi bi-people"></i> Jogadores
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= APP_URL ?>/admin/jogos.php">
                            <i class="bi bi-controller"></i> Jogos
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= APP_URL ?>/admin/pagamentos.php">
                            <i class="bi bi-cash-coin"></i> Pagamentos
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= APP_URL ?>/admin/afiliados.php">
                            <i class="bi bi-diagram-3"></i> Afiliados
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= APP_URL ?>/admin/relatorios.php">
                            <i class="bi bi-bar-chart"></i> Relatórios
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= APP_URL ?>/admin/regras-bolao.php">
                            <i class="bi bi-list-check"></i> Regras do Bolão
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= APP_URL ?>/admin/configuracoes.php">
                            <i class="bi bi-gear"></i> Configurações
                        </a>
                    </li>
                </ul>
                
                <hr class="text-light">
                
                <ul class="nav flex-column mb-2">
                    <li class="nav-item">
                        <a class="nav-link" href="<?= APP_URL ?>/" target="_blank">
                            <i class="bi bi-house"></i> Ver Site
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= APP_URL ?>/admin/logout.php">
                            <i class="bi bi-box-arrow-right"></i> Sair
                        </a>
                    </li>
                </ul>
            </div>
        </nav>

        <!-- Main content -->
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
            
            <!-- Top Navigation Bar -->
            <nav class="navbar navbar-expand-lg navbar-light bg-light mb-4 d-md-none">
                <div class="container-fluid">
                    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#sidebar">
                        <span class="navbar-toggler-icon"></span>
                    </button>
                    <a class="navbar-brand" href="<?= APP_URL ?>/admin/"><?= APP_NAME ?> Admin</a>
                    <div class="dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle"></i>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="<?= APP_URL ?>/admin/perfil.php">Meu Perfil</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="<?= APP_URL ?>/admin/logout.php">Sair</a></li>
                        </ul>
                    </div>
                </div>
            </nav>
            
            <!-- Flash Messages -->
            <?php $flashMessage = getFlashMessage(); ?>
            <?php if ($flashMessage): ?>
                <div class="alert alert-<?= $flashMessage['type'] ?> alert-dismissible fade show">
                    <?= $flashMessage['message'] ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?> 