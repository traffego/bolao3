<?php
/**
 * Meus Palpites - Bolão Football
 */
require_once 'config/config.php';
require_once 'includes/database.php';
require_once 'includes/functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    $_SESSION['redirect_after_login'] = APP_URL . '/meus-palpites.php';
    redirect(APP_URL . '/login.php');
}

$userId = $_SESSION['user_id'];

// Debug: Print user ID
echo "<pre>User ID: " . $userId . "</pre>";

// Get all bolões the user participates in
$boloes = dbFetchAll(
    "SELECT DISTINCT b.*, 
            JSON_LENGTH(b.jogos, '$') as total_jogos,
            0 as jogos_finalizados
     FROM dados_boloes b
     WHERE b.id IN (
         SELECT DISTINCT bolao_id 
         FROM palpites 
         WHERE jogador_id = ?
     ) AND b.status = 1
     ORDER BY b.data_inicio DESC", 
    [$userId]
);

// Calculate finalized games count in PHP since MariaDB JSON support is limited
foreach ($boloes as &$bolao) {
    $jogos = json_decode($bolao['jogos'], true) ?: [];
    $bolao['jogos_finalizados'] = count(array_filter($jogos, function($jogo) {
        return isset($jogo['status']) && $jogo['status'] === 'finalizado';
    }));
}
unset($bolao); // break the reference

// Debug: Print bolões
echo "<pre>Bolões encontrados: " . print_r($boloes, true) . "</pre>";

// Get bolão ID from URL or use the first one
$bolaoId = isset($_GET['bolao_id']) ? (int)$_GET['bolao_id'] : ($boloes[0]['id'] ?? 0);

// Debug: Print bolão ID
echo "<pre>Bolão ID selecionado: " . $bolaoId . "</pre>";

// Get user's palpites for the selected bolão
$palpites = [];
$jogos = [];
if ($bolaoId > 0) {
    // Get bolão data with jogos
    $bolao = dbFetchOne(
        "SELECT * FROM dados_boloes WHERE id = ? AND status = 1",
        [$bolaoId]
    );
    
    // Debug: Print bolão data
    echo "<pre>Dados do bolão: " . print_r($bolao, true) . "</pre>";
    
    if ($bolao) {
        // Decode jogos from JSON
        $jogos = json_decode($bolao['jogos'], true) ?: [];
        
        // Debug: Print jogos
        echo "<pre>Jogos decodificados: " . print_r($jogos, true) . "</pre>";
        
        // Get user's palpites
        $palpitesData = dbFetchOne(
            "SELECT * FROM palpites 
             WHERE jogador_id = ? AND bolao_id = ?", 
            [$userId, $bolaoId]
        );
        
        // Debug: Print raw palpites data
        echo "<pre>Dados brutos dos palpites: " . print_r($palpitesData, true) . "</pre>";
        
        if ($palpitesData) {
            $palpites = json_decode($palpitesData['palpites'], true) ?: [];
            
            // Debug: Print decoded palpites
            echo "<pre>Palpites decodificados: " . print_r($palpites, true) . "</pre>";
        }
    }
}

// Get configuration for points
$config = dbFetchAll(
    "SELECT nome_configuracao, valor FROM configuracoes 
     WHERE nome_configuracao IN ('pontos_acerto_exato', 'pontos_acerto_vencedor', 'pontos_acerto_parcial')"
);

$pontuacao = [
    'pontos_acerto_exato' => 10,
    'pontos_acerto_vencedor' => 5,
    'pontos_acerto_parcial' => 3
];

foreach ($config as $cfg) {
    $pontuacao[$cfg['nome_configuracao']] = (int)$cfg['valor'];
}

// Page title
$pageTitle = 'Meus Palpites';

// Include header
include 'templates/header.php';
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1><?= $pageTitle ?></h1>
    </div>

    <?php if (empty($boloes)): ?>
        <div class="alert alert-info">
            Você ainda não participa de nenhum bolão. 
            <a href="<?= APP_URL ?>/boloes.php" class="alert-link">Participe de um bolão</a>
        </div>
    <?php else: ?>
        <!-- Bolão selector -->
        <div class="card mb-4">
            <div class="card-body">
                <form action="" method="get" class="row g-3">
                    <div class="col-md-6">
                        <label for="bolao_id" class="form-label">Selecione o Bolão</label>
                        <select class="form-select" id="bolao_id" name="bolao_id" onchange="this.form.submit()">
                            <?php foreach ($boloes as $b): ?>
                                <option value="<?= $b['id'] ?>" <?= $bolaoId == $b['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($b['nome']) ?> 
                                    (<?= $b['jogos_finalizados'] ?>/<?= $b['total_jogos'] ?> jogos)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </form>
            </div>
        </div>

        <?php if (empty($palpites)): ?>
            <div class="alert alert-warning">
                Você ainda não fez nenhum palpite neste bolão.
                <a href="<?= APP_URL ?>/bolao.php?slug=<?= $bolao['slug'] ?>" class="alert-link">Fazer palpites</a>
            </div>
        <?php else: ?>
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Jogo</th>
                                    <th>Data/Hora</th>
                                    <th>Seu Palpite</th>
                                    <th>Resultado</th>
                                    <th>Pontos</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($jogos as $jogo): ?>
                                    <?php 
                                    $jogoId = $jogo['id'];
                                    $palpiteJogo = $palpites[$jogoId] ?? null;
                                    $resultado = isset($jogo['resultado']) ? $jogo['resultado'] : null;
                                    ?>
                                    <tr>
                                        <td>
                                            <?= htmlspecialchars($jogo['time_casa']) ?> x 
                                            <?= htmlspecialchars($jogo['time_visitante']) ?>
                                        </td>
                                        <td><?= formatDateTime($jogo['data']) ?></td>
                                        <td class="text-center">
                                            <?php if ($palpiteJogo): ?>
                                                <?php if ($palpiteJogo['casa'] > $palpiteJogo['visitante']): ?>
                                                    Vitória <?= htmlspecialchars($jogo['time_casa']) ?>
                                                <?php elseif ($palpiteJogo['casa'] < $palpiteJogo['visitante']): ?>
                                                    Vitória <?= htmlspecialchars($jogo['time_visitante']) ?>
                                                <?php else: ?>
                                                    Empate
                                                <?php endif; ?>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <?php if ($resultado): ?>
                                                <?= $resultado['placar_casa'] ?> x <?= $resultado['placar_visitante'] ?>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <?php
                                            if ($palpiteJogo && $resultado) {
                                                if ($palpiteJogo['casa'] == $resultado['placar_casa'] && 
                                                    $palpiteJogo['visitante'] == $resultado['placar_visitante']) {
                                                    echo "<span class='badge bg-success'>{$pontuacao['pontos_acerto_exato']}</span>";
                                                } elseif (
                                                    ($palpiteJogo['casa'] > $palpiteJogo['visitante'] && $resultado['placar_casa'] > $resultado['placar_visitante']) ||
                                                    ($palpiteJogo['casa'] < $palpiteJogo['visitante'] && $resultado['placar_casa'] < $resultado['placar_visitante']) ||
                                                    ($palpiteJogo['casa'] == $palpiteJogo['visitante'] && $resultado['placar_casa'] == $resultado['placar_visitante'])
                                                ) {
                                                    echo "<span class='badge bg-primary'>{$pontuacao['pontos_acerto_vencedor']}</span>";
                                                } else {
                                                    echo "<span class='badge bg-danger'>0</span>";
                                                }
                                            } else {
                                                echo "-";
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <?php
                                            $status = match($jogo['status'] ?? 'agendado') {
                                                'agendado' => '<span class="badge bg-info">Agendado</span>',
                                                'em_andamento' => '<span class="badge bg-warning">Em Andamento</span>',
                                                'finalizado' => '<span class="badge bg-success">Finalizado</span>',
                                                'cancelado' => '<span class="badge bg-danger">Cancelado</span>',
                                                default => '<span class="badge bg-secondary">Indefinido</span>'
                                            };
                                            echo $status;
                                            ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Legenda de Pontuação</h5>
                </div>
                <div class="card-body">
                    <ul class="list-unstyled mb-0">
                        <li><span class="badge bg-success"><?= $pontuacao['pontos_acerto_exato'] ?></span> - Acerto exato do placar</li>
                        <li><span class="badge bg-primary"><?= $pontuacao['pontos_acerto_vencedor'] ?></span> - Acerto do vencedor/empate</li>
                        <li><span class="badge bg-danger">0</span> - Erro</li>
                    </ul>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php include 'templates/footer.php'; ?> 