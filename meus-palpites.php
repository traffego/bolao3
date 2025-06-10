<?php
/**
 * Meus Palpites - Bolão Football
 */
require_once 'config/config.php';
require_once 'includes/functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    $_SESSION['redirect_after_login'] = APP_URL . '/meus-palpites.php';
    redirect(APP_URL . '/login.php');
}

$userId = $_SESSION['user_id'];

// Debug: Print user ID
error_log("=== DEBUG MEUS PALPITES ===");
error_log("User ID: " . $userId);

// Verificar primeiro se existem palpites
$checkPalpites = dbFetchAll("SELECT * FROM palpites WHERE jogador_id = ?", [$userId]);
error_log("Palpites encontrados (query direta): " . count($checkPalpites));
if (!empty($checkPalpites)) {
    error_log("Exemplo de palpite: " . print_r($checkPalpites[0], true));
}

// Get all palpites do usuário com uma query mais simples
$query = "SELECT p.*, b.nome as bolao_nome, b.data_inicio, b.data_fim, b.jogos
 FROM palpites p
 JOIN dados_boloes b ON p.bolao_id = b.id
 WHERE p.jogador_id = ?
 ORDER BY p.data_palpite DESC";

// Debug: Print query
error_log("Query SQL: " . $query);
error_log("User ID para query: " . $userId);

$palpites = dbFetchAll($query, [$userId]);

// Processar os jogos para contar finalizados
foreach ($palpites as &$palpite) {
    $jogos = json_decode($palpite['jogos'], true) ?? [];
    $total_jogos = count($jogos);
    $jogos_finalizados = 0;
    
    foreach ($jogos as $jogo) {
        if (isset($jogo['status']) && $jogo['status'] === 'finalizado') {
            $jogos_finalizados++;
        }
    }
    
    $palpite['total_jogos'] = $total_jogos;
    $palpite['jogos_finalizados'] = $jogos_finalizados;
}
unset($palpite);

// Debug: Print result
error_log("Número de palpites encontrados (query completa): " . count($palpites));
if (!empty($palpites)) {
    error_log("Exemplo de resultado: " . print_r($palpites[0], true));
} else {
    error_log("Nenhum palpite encontrado. Verificando tabelas...");
    
    // Verificar tabela dados_boloes
    $checkBoloes = dbFetchAll("SELECT * FROM dados_boloes LIMIT 1");
    error_log("Exemplo de bolão: " . print_r($checkBoloes[0] ?? 'Nenhum bolão encontrado', true));
    
    // Verificar join
    $checkJoin = dbFetchAll("
        SELECT p.id, p.bolao_id, b.id as bolao_id_join, b.nome 
        FROM palpites p 
        LEFT JOIN dados_boloes b ON p.bolao_id = b.id 
        WHERE p.jogador_id = ?", 
        [$userId]
    );
    error_log("Resultado do JOIN: " . print_r($checkJoin, true));
}

// Page title
$pageTitle = 'Meus Palpites';

// Include header
include 'templates/header.php';
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1><?= $pageTitle ?></h1>
        <a href="<?= APP_URL ?>/boloes.php" class="btn btn-primary">
            <i class="bi bi-plus-circle"></i> Participar de Novo Bolão
        </a>
    </div>

    <?php if (empty($palpites)): ?>
        <div class="alert alert-info">
            Você ainda não fez nenhum palpite. 
            <a href="<?= APP_URL ?>/boloes.php" class="alert-link">Participe de um bolão</a>
        </div>
    <?php else: ?>
        <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
            <?php foreach ($palpites as $palpite): ?>
                <div class="col">
                    <div class="card h-100">
                        <div class="card-header bg-primary text-white">
                            <h5 class="card-title mb-0"><?= htmlspecialchars($palpite['bolao_nome']) ?></h5>
                        </div>
                        <div class="card-body">
                            <p class="card-text">
                                <strong>Data do Palpite:</strong><br>
                                <?= formatDateTime($palpite['data_palpite']) ?>
                            </p>
                            <p class="card-text">
                                <strong>Período do Bolão:</strong><br>
                                <?= formatDate($palpite['data_inicio']) ?> a <?= formatDate($palpite['data_fim']) ?>
                            </p>
                            <p class="card-text">
                                <strong>Progresso:</strong><br>
                                <?= $palpite['jogos_finalizados'] ?> de <?= $palpite['total_jogos'] ?> jogos finalizados
                            </p>
                            <p class="card-text">
                                <strong>Status:</strong><br>
                                <span class="badge bg-<?= $palpite['status'] === 'pago' ? 'success' : 'warning' ?>">
                                    <?= ucfirst($palpite['status']) ?>
                                </span>
                            </p>
                        </div>
                        <div class="card-footer bg-white border-top-0">
                            <a href="<?= APP_URL ?>/ver-palpite.php?id=<?= $palpite['id'] ?>" class="btn btn-primary w-100">
                                <i class="bi bi-eye"></i> Ver Detalhes
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php include 'templates/footer.php'; ?> 