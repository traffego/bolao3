<?php
require_once 'config/config.php';
require_once 'includes/functions.php';

// Verificar se usuário está logado
if (!isLoggedIn()) {
    $_SESSION['redirect_after_login'] = APP_URL . '/ver-palpite.php';
    redirect(APP_URL . '/login.php');
}

$userId = $_SESSION['user_id'];
$palpiteId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Buscar dados do palpite
$palpite = dbFetchOne(
    "SELECT p.*, b.nome as bolao_nome, b.jogos, b.data_inicio, b.data_fim, b.valor_participacao 
     FROM palpites p 
     JOIN dados_boloes b ON p.bolao_id = b.id 
     WHERE p.id = ? AND p.jogador_id = ?",
    [$palpiteId, $userId]
);

if (!$palpite) {
    setFlashMessage('danger', 'Palpite não encontrado ou você não tem permissão para visualizá-lo.');
    redirect(APP_URL . '/meus-palpites.php');
}

// Se o palpite estiver pendente e o bolão tem valor de participação, redirecionar para confirmação
if ($palpite['status'] === 'pendente' && $palpite['valor_participacao'] > 0) {
    // Salvar dados do palpite na sessão para recuperar na página de confirmação
    $_SESSION['palpite_pendente'] = [
        'id' => $palpiteId,
        'bolao_id' => $palpite['bolao_id']
    ];
    
    redirect(APP_URL . '/confirmar-palpite.php?id=' . $palpite['bolao_id']);
}

// Decodificar palpites e jogos
$palpitesJson = json_decode($palpite['palpites'], true);
$palpites = $palpitesJson['jogos'] ?? [];
$jogos = json_decode($palpite['jogos'], true) ?? [];

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
$pageTitle = 'Visualizar Palpite - ' . $palpite['bolao_nome'];

// Include header
include 'templates/header.php';
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1><?= $pageTitle ?></h1>
        <a href="<?= APP_URL ?>/meus-palpites.php" class="btn btn-outline-primary">
            <i class="bi bi-arrow-left"></i> Voltar para Meus Palpites
        </a>
    </div>

    <div class="card mb-4">
        <div class="card-header bg-green text-white">
            <h5 class="card-title mb-0">Detalhes do Bolão</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <p><strong>Nome do Bolão:</strong> <?= htmlspecialchars($palpite['bolao_nome']) ?></p>
                    <p><strong>Data de Início:</strong> <?= formatDate($palpite['data_inicio']) ?></p>
                    <p><strong>Data de Fim:</strong> <?= formatDate($palpite['data_fim']) ?></p>
                </div>
                <div class="col-md-6">
                    <p><strong>Data do Palpite:</strong> <?= formatDateTime($palpite['data_palpite']) ?></p>
                    <p><strong>Status do Palpite:</strong> 
                        <span class="badge bg-<?= $palpite['status'] === 'pago' ? 'success' : 'warning' ?>">
                            <?= ucfirst($palpite['status']) ?>
                        </span>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h5 class="card-title mb-0">Seus Palpites</h5>
        </div>
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
                                <td><?= formatDateTime($jogo['data_formatada'] ?? $jogo['data']) ?></td>
                                <td class="text-center">
                                    <?php if ($palpiteJogo): ?>
                                        <?php if ($palpiteJogo === '1'): ?>
                                            <span class="text-success">Vitória <?= htmlspecialchars($jogo['time_casa']) ?></span>
                                        <?php elseif ($palpiteJogo === '2'): ?>
                                            <span class="text-danger">Vitória <?= htmlspecialchars($jogo['time_visitante']) ?></span>
                                        <?php else: ?>
                                            <span class="text-warning">Empate</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-warning">Empate</span>
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
                                        $palpiteVitoria = '';
                                        $resultadoVitoria = '';

                                        if ($palpiteJogo === '1') $palpiteVitoria = 'casa';
                                        elseif ($palpiteJogo === '2') $palpiteVitoria = 'visitante';
                                        else $palpiteVitoria = 'empate';

                                        if ($resultado['placar_casa'] > $resultado['placar_visitante']) $resultadoVitoria = 'casa';
                                        elseif ($resultado['placar_casa'] < $resultado['placar_visitante']) $resultadoVitoria = 'visitante';
                                        else $resultadoVitoria = 'empate';

                                        if ($palpiteVitoria === $resultadoVitoria) {
                                            echo "<span class='badge bg-success'>{$pontuacao['pontos_acerto_vencedor']}</span>";
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
                <li><span class="badge bg-success"><?= $pontuacao['pontos_acerto_vencedor'] ?></span> - ACERTOU</li>
                <li><span class="badge bg-danger">0</span> - ERROU</li>
            </ul>
        </div>
    </div>
</div>

<?php include 'templates/footer.php'; ?> 