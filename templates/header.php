<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? $pageTitle . ' - ' . APP_NAME : APP_NAME ?></title>
    <link rel="icon" type="image/x-icon" href="<?= APP_URL ?>/public/img/favicon.ico">
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    
    <!-- Google Fonts - Nunito Sans & Roboto Mono -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Nunito+Sans:ital,opsz,wght@0,6..12,200..1000;1,6..12,200..1000&family=Roboto+Mono:wght@400;500&display=swap" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?= APP_URL ?>/public/css/styles.css">
    
    <?php if (isset($extraCss)): ?>
        <?= $extraCss ?>
    <?php endif; ?>
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-dark" style="background: var(--globo-verde-principal, #06AA48) !important; box-shadow: 0 2px 10px rgba(6, 170, 72, 0.3);">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center" href="<?= APP_URL ?>" style="font-family: var(--font-primary); font-weight: 700; color: white !important;">
            <img src="<?= APP_URL ?>/public/img/logo.png" alt="<?= APP_NAME ?>" style="height: 96px; width: 96px; object-fit: contain;">
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain">
            <span class="navbar-toggler-icon"></span>
        </button>
        
        <div class="collapse navbar-collapse" id="navbarMain">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link" href="<?= APP_URL ?>">Início</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?= APP_URL ?>/boloes.php">Bolões</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?= APP_URL ?>/ranking.php">Ranking</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?= APP_URL ?>/como-funciona.php">Como Funciona</a>
                </li>
            </ul>
            
            <ul class="navbar-nav">
                <?php if (isLoggedIn()): ?>
                    <?php
                        // Buscar saldo do usuário
                        require_once ROOT_DIR . '/includes/classes/ContaManager.php';
                        $contaManager = new ContaManager();
                        $conta = $contaManager->buscarContaPorJogador(getCurrentUserId());
                        $saldo = $conta ? $contaManager->getSaldo($conta['id']) : 0;
                    ?>
                    <!-- Saldo -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="saldoDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-wallet2"></i> R$ <?= number_format($saldo, 2, ',', '.') ?>
                        </a>
                        <ul class="dropdown-menu">
                            <li>
                                <a class="dropdown-item" href="<?= APP_URL ?>/deposito.php">
                                    <i class="bi bi-plus-circle text-success"></i> Fazer Depósito
                                </a>
                            </li>
                            <?php if ($saldo > 0): ?>
                            <li>
                                <a class="dropdown-item" href="<?= APP_URL ?>/minha-conta.php#saque">
                                    <i class="bi bi-cash text-primary"></i> Solicitar Saque
                                </a>
                            </li>
                            <?php endif; ?>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item" href="<?= APP_URL ?>/minha-conta.php">
                                    <i class="bi bi-clock-history"></i> Histórico
                                </a>
                            </li>
                        </ul>
                    </li>
                    <!-- Notificações -->
                    <li class="nav-item">
                        <a href="<?= APP_URL ?>/notificacoes.php" class="nav-link">
                            <i class="bi bi-bell"></i>
                            <?php
                            $notificacaoManager = new NotificacaoManager();
                            $naoLidas = $notificacaoManager->contarNaoLidas(getCurrentUserId());
                            if ($naoLidas > 0):
                            ?>
                                <span class="badge bg-danger"><?= $naoLidas ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <!-- Menu do Usuário -->
                    <li class="nav-item dropdown">
                        <?php 
                            $user = dbFetchOne("SELECT nome FROM jogador WHERE id = ?", [getCurrentUserId()]);
                            $userName = $user ? $user['nome'] : 'Usuário';
                        ?>
                        <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle"></i> <?= sanitize($userName) ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="<?= APP_URL ?>/meus-boloes.php">Meus Bolões</a></li>
                            <li><a class="dropdown-item" href="<?= APP_URL ?>/meus-palpites.php">Meus Palpites</a></li>
                            <li><a class="dropdown-item" href="<?= APP_URL ?>/perfil.php">Meu Perfil</a></li>
                            <?php if (isAdmin()): ?>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-primary" href="<?= APP_URL ?>/admin/">Painel Admin</a></li>
                            <?php endif; ?>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="<?= APP_URL ?>/logout.php">Sair</a></li>
                        </ul>
                    </li>
                <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= APP_URL ?>/login.php">Entrar</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link btn btn-outline-light btn-sm ms-2" href="<?= APP_URL ?>/cadastro.php">Cadastre-se</a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<!-- Flash Messages -->
<?php $flashMessage = getFlashMessage(); ?>
<?php if ($flashMessage): ?>
    <div class="container mt-3">
        <div class="alert alert-<?= $flashMessage['type'] ?> alert-dismissible fade show">
            <?= $flashMessage['message'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    </div>
<?php endif; ?>

<?php 
// DEBUG SIMPLES - Códigos de Afiliação
echo '<div style="background: #000; color: #0f0; padding: 10px; margin: 10px 0; font-family: monospace; border: 2px solid #0f0;">';
echo '<strong>🔍 DEBUG AFILIAÇÃO:</strong><br>';
echo 'GET[ref]: ' . (isset($_GET['ref']) ? htmlspecialchars($_GET['ref']) : 'Não presente') . '<br>';
echo 'localStorage será usado pelo JavaScript<br>';
if (isset($_SESSION['user_id'])) {
    require_once ROOT_DIR . '/config/database.php';
    $stmt = $pdo->prepare("SELECT codigo_afiliado, ref_indicacao FROM jogador WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user_data) {
        echo 'DB - Código Afiliado: ' . ($user_data['codigo_afiliado'] ?: 'Vazio') . '<br>';
        echo 'DB - Ref Indicação: ' . ($user_data['ref_indicacao'] ?: 'Vazio') . '<br>';
    }
}
echo 'Página: ' . basename($_SERVER['PHP_SELF']) . ' | Hora: ' . date('H:i:s');
echo '</div>';
?>

<!-- Main Content -->
<div class="container py-4"><?php // Content will go here ?>