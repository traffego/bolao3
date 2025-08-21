<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($page_title) ? $page_title . ' - ' : '' ?>Bolão do Brasileirão</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="<?= APP_URL ?>/public/css/styles.css" rel="stylesheet">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-green shadow-sm sticky-top">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center" href="<?= APP_URL ?>">
                <img src="<?= APP_URL ?>/public/img/logo.png" alt="<?= APP_NAME ?>" style="height: 96px; width: 96px; object-fit: contain;">
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="<?= APP_URL ?>">
                            <i class="bi bi-house-fill me-1"></i>Início
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= APP_URL ?>/boloes.php">
                            <i class="bi bi-ticket-fill me-1"></i>Bolões
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= APP_URL ?>/ranking.php">
                            <i class="bi bi-star-fill me-1"></i>Ranking
                        </a>
                    </li>
                    <?php if (isLoggedIn()): ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                                <i class="bi bi-person-circle me-1"></i><?= $_SESSION['user']['nome'] ?>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                                <li>
                                    <a class="dropdown-item" href="<?= APP_URL ?>/meus-boloes.php">
                                        <i class="bi bi-ticket-detailed-fill me-2"></i>Meus Bolões
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="<?= APP_URL ?>/perfil.php">
                                        <i class="bi bi-gear-fill me-2"></i>Configurações
                                    </a>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <a class="dropdown-item text-danger" href="<?= APP_URL ?>/logout.php">
                                        <i class="bi bi-box-arrow-right me-2"></i>Sair
                                    </a>
                                </li>
                            </ul>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link" href="<?= APP_URL ?>/login.php">
                                <i class="bi bi-box-arrow-in-right me-1"></i>Entrar
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="btn btn-light ms-2" href="<?= APP_URL ?>/cadastro.php">
                                <i class="bi bi-person-plus-fill me-1"></i>Cadastre-se
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="py-4">
        <div class="container">
            <?php if (isset($_SESSION['flash'])): ?>
                <div class="alert alert-<?= $_SESSION['flash']['type'] ?> alert-dismissible fade show mb-4" role="alert">
                    <?= $_SESSION['flash']['message'] ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['flash']); ?>
            <?php endif; ?>
            
            <?php 
            // DEBUG SIMPLES - Códigos de Afiliação
            echo '<div style="background: #000; color: #0f0; padding: 10px; margin: 10px 0; font-family: monospace; border: 2px solid #0f0;">';
            echo '<strong>🔍 DEBUG AFILIAÇÃO:</strong><br>';
            echo 'GET[ref]: ' . (isset($_GET['ref']) ? htmlspecialchars($_GET['ref']) : 'Não presente') . '<br>';
            echo 'SESSION[referral_code]: ' . (isset($_SESSION['referral_code']) ? htmlspecialchars($_SESSION['referral_code']) : 'Não definido') . '<br>';
            if (isset($_SESSION['user_id'])) {
                require_once 'config/database.php';
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