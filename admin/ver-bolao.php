<?php
require_once '../config/config.php';require_once '../includes/functions.php';

// Check if admin is logged in
if (!isAdmin()) {
    setFlashMessage('danger', 'Acesso negado. Faça login como administrador.');
    redirect(APP_URL . '/admin/login.php');
}

// Get bolão ID
$bolaoId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Get bolão data
$bolao = dbFetchOne("SELECT * FROM dados_boloes WHERE id = ?", [$bolaoId]);

if (!$bolao) {
    setFlashMessage('danger', 'Bolão não encontrado.');
    redirect(APP_URL . '/admin/boloes.php');
}

// Decode JSON data
$jogos = json_decode($bolao['jogos'], true) ?: [];
$campeonatos = json_decode($bolao['campeonatos'], true) ?: [];

// Sort games by date
usort($jogos, function($a, $b) {
    $dateA = isset($a['data_iso']) ? $a['data_iso'] : $a['data'];
    $dateB = isset($b['data_iso']) ? $b['data_iso'] : $b['data'];
    return strtotime($dateA) - strtotime($dateB);
});

// Include header
$pageTitle = "Visualizar Bolão: " . $bolao['nome'];
$currentPage = "boloes";
include '../templates/admin/header.php';
?>

<div class="container-fluid px-4">
    <div class="d-flex justify-content-between align-items-center mt-4 mb-3">
        <h1><?= htmlspecialchars($bolao['nome']) ?></h1>
        <div>
            <a href="boloes.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Voltar
            </a>
            <a href="editar-bolao.php?id=<?= $bolao['id'] ?>" class="btn btn-primary">
                <i class="fas fa-edit"></i> Editar
            </a>
        </div>
    </div>

    <div class="row">
        <!-- Informações Gerais -->
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-info-circle"></i> Informações Gerais
                </div>
                <div class="card-body">
                    <table class="table table-striped">
                        <tr>
                            <th style="width: 40%">ID</th>
                            <td><?= $bolao['id'] ?></td>
                        </tr>
                        <tr>
                            <th>Slug</th>
                            <td><?= htmlspecialchars($bolao['slug']) ?></td>
                        </tr>
                        <tr>
                            <th>Data de Início</th>
                            <td><?= formatDate($bolao['data_inicio']) ?></td>
                        </tr>
                        <tr>
                            <th>Data de Fim</th>
                            <td><?= formatDate($bolao['data_fim']) ?></td>
                        </tr>
                        <tr>
                            <th>Limite para Palpites</th>
                            <td><?= $bolao['data_limite_palpitar'] ? formatDateTime($bolao['data_limite_palpitar']) : 'Não definido' ?></td>
                        </tr>
                        <tr>
                            <th>Valor de Participação</th>
                            <td><?= formatMoney($bolao['valor_participacao']) ?></td>
                        </tr>
                        <tr>
                            <th>Prêmio Total</th>
                            <td><?= formatMoney($bolao['premio_total']) ?></td>
                        </tr>
                        <tr>
                            <th>Status</th>
                            <td>
                                <?php if ($bolao['status'] == 1): ?>
                                    <span class="badge bg-success">Ativo</span>
                                <?php else: ?>
                                    <span class="badge bg-danger">Inativo</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th>Visibilidade</th>
                            <td>
                                <?php if ($bolao['publico'] == 1): ?>
                                    <span class="badge bg-primary">Público</span>
                                <?php else: ?>
                                    <span class="badge bg-warning text-dark">Privado</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th>Máximo de Participantes</th>
                            <td><?= $bolao['max_participantes'] ?: 'Sem limite' ?></td>
                        </tr>
                        <tr>
                            <th>Quantidade de Jogos</th>
                            <td><?= count($jogos) ?></td>
                        </tr>
                        <tr>
                            <th>Data de Criação</th>
                            <td><?= formatDateTime($bolao['data_criacao']) ?></td>
                        </tr>
                    </table>
                </div>
            </div>

            <!-- Imagem do Bolão -->
            <?php if (!empty($bolao['imagem_bolao_url'])): ?>
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-image"></i> Imagem do Bolão
                </div>
                <div class="card-body text-center">
                    <img src="<?= APP_URL ?>/<?= htmlspecialchars($bolao['imagem_bolao_url']) ?>" 
                         alt="<?= htmlspecialchars($bolao['nome']) ?>" 
                         class="img-fluid" style="max-height: 300px;">
                </div>
            </div>
            <?php endif; ?>

            <!-- Campeonatos -->
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-trophy"></i> Campeonatos
                </div>
                <div class="card-body">
                    <?php if (empty($campeonatos)): ?>
                        <p class="text-muted">Nenhum campeonato selecionado.</p>
                    <?php else: ?>
                        <div class="d-flex flex-wrap gap-2">
                            <?php foreach ($campeonatos as $campeonato): ?>
                                <span class="badge bg-info text-dark">
                                    <?= htmlspecialchars($campeonato['nome']) ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Jogos -->
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-futbol"></i> Jogos (<?= count($jogos) ?>)
                </div>
                <div class="card-body">
                    <?php if (empty($jogos)): ?>
                        <p class="text-muted">Nenhum jogo selecionado.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Data</th>
                                        <th>Campeonato</th>
                                        <th>Partida</th>
                                        <th>Resultado</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($jogos as $jogo): ?>
                                        <tr>
                                            <td><?= $jogo['data'] ?></td>
                                            <td><?= htmlspecialchars($jogo['campeonato']) ?></td>
                                            <td>
                                                <strong><?= htmlspecialchars($jogo['time_casa']) ?></strong>
                                                x 
                                                <strong><?= htmlspecialchars($jogo['time_visitante']) ?></strong>
                                            </td>
                                            <td>
                                                <?php if ($jogo['resultado_casa'] !== null && $jogo['resultado_visitante'] !== null): ?>
                                                    <span class="badge bg-primary">
                                                        <?= $jogo['resultado_casa'] ?> x <?= $jogo['resultado_visitante'] ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Aguardando</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php
                                                $statusClass = 'secondary';
                                                $statusText = $jogo['status'];
                                                
                                                switch ($jogo['status']) {
                                                    case 'NS':
                                                        $statusText = 'Não iniciado';
                                                        break;
                                                    case 'LIVE':
                                                    case '1H':
                                                    case '2H':
                                                    case 'HT':
                                                        $statusClass = 'danger';
                                                        $statusText = 'Em andamento';
                                                        break;
                                                    case 'FT':
                                                    case 'AET':
                                                    case 'PEN':
                                                        $statusClass = 'success';
                                                        $statusText = 'Finalizado';
                                                        break;
                                                    case 'SUSP':
                                                    case 'INT':
                                                        $statusClass = 'warning';
                                                        $statusText = 'Suspenso';
                                                        break;
                                                    case 'PST':
                                                    case 'CANC':
                                                    case 'ABD':
                                                        $statusClass = 'danger';
                                                        $statusText = 'Cancelado';
                                                        break;
                                                }
                                                ?>
                                                <span class="badge bg-<?= $statusClass ?>">
                                                    <?= $statusText ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Botões de Ação -->
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-cogs"></i> Ações
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="editar-bolao.php?id=<?= $bolao['id'] ?>" class="btn btn-primary">
                            <i class="fas fa-edit"></i> Editar Bolão
                        </a>
                        
                        <?php if ($bolao['status'] == 1): ?>
                            <a href="boloes.php?status=0&id=<?= $bolao['id'] ?>" class="btn btn-warning">
                                <i class="fas fa-ban"></i> Desativar Bolão
                            </a>
                        <?php else: ?>
                            <a href="boloes.php?status=1&id=<?= $bolao['id'] ?>" class="btn btn-success">
                                <i class="fas fa-check"></i> Ativar Bolão
                            </a>
                        <?php endif; ?>
                        
                        <a href="boloes.php?delete=<?= $bolao['id'] ?>" class="btn btn-danger"
                           onclick="return confirm('Tem certeza que deseja excluir este bolão?')">
                            <i class="fas fa-trash"></i> Excluir Bolão
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../templates/admin/footer.php'; ?> 