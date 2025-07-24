<?php
require_once '../config/config.php';
require_once '../includes/functions.php';

// Verificar se o administrador está logado
if (!isAdmin()) {
    setFlashMessage('danger', 'Acesso negado. Faça login como administrador.');
    redirect(APP_URL . '/admin/login.php');
}

// Obter e validar ID do bolão
$bolaoId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$bolaoId) {
    setFlashMessage('danger', 'ID do bolão inválido.');
    redirect(APP_URL . '/admin/boloes.php');
}

// Carregar dados do bolão
$bolao = dbFetchOne("SELECT b.*, 
    (SELECT COUNT(*) FROM participantes p WHERE p.bolao_id = b.id) as total_participantes,
    (SELECT COUNT(*) FROM participantes p WHERE p.bolao_id = b.id AND p.pagamento_confirmado = 1) as participantes_confirmados
    FROM dados_boloes b 
    WHERE b.id = ?", [$bolaoId]);

if (!$bolao) {
    setFlashMessage('danger', 'Bolão não encontrado.');
    redirect(APP_URL . '/admin/boloes.php');
}

// Carregar resultados se existirem
$resultados = dbFetchOne("SELECT * FROM resultados WHERE bolao_id = ?", [$bolaoId]);

// Decodificar dados JSON
$jogos = json_decode($bolao['jogos'], true) ?: [];
$campeonatos = json_decode($bolao['campeonatos'], true) ?: [];
$resultadosJson = $resultados ? json_decode($resultados['resultado'], true) : ['jogos' => []];

// Ordenar jogos por data
usort($jogos, function($a, $b) {
    $dateA = isset($a['data_iso']) ? $a['data_iso'] : $a['data'];
    $dateB = isset($b['data_iso']) ? $b['data_iso'] : $b['data'];
    return strtotime($dateA) - strtotime($dateB);
});

// Calcular estatísticas
$jogosFinalizados = 0;
$jogosEmAndamento = 0;
foreach ($jogos as $jogo) {
    if (in_array($jogo['status'], ['FT', 'AET', 'PEN'])) {
        $jogosFinalizados++;
    } elseif (in_array($jogo['status'], ['LIVE', '1H', '2H', 'HT'])) {
        $jogosEmAndamento++;
    }
}

// Template
$pageTitle = "Visualizar Bolão: " . $bolao['nome'];
$currentPage = "boloes";
require_once '../templates/admin/header.php';
?>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0"><?= htmlspecialchars($bolao['nome']) ?></h1>
                </div>
                <div class="col-sm-6">
                    <div class="float-sm-right">
                        <a href="<?= APP_URL ?>/admin/boloes.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Voltar
                        </a>
                        <a href="<?= APP_URL ?>/admin/editar-bolao.php?id=<?= $bolao['id'] ?>" class="btn btn-primary">
                            <i class="fas fa-edit"></i> Editar
                        </a>
                        <a href="<?= APP_URL ?>/admin/editar-resultados.php?id=<?= $bolao['id'] ?>" class="btn btn-success">
                            <i class="fas fa-futbol"></i> Gerenciar Resultados
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <section class="content">
        <div class="container-fluid">
            <div class="row">
                <!-- Informações Gerais -->
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-info-circle"></i> Informações Gerais</h3>
                        </div>
                        <div class="card-body">
                            <table class="table table-striped">
                                <tr>
                                    <th style="width: 40%">Status do Bolão</th>
                                    <td>
                                        <?php if ($bolao['status'] == 1): ?>
                                            <span class="badge badge-success">Ativo</span>
                                        <?php else: ?>
                                            <span class="badge badge-danger">Inativo</span>
                                        <?php endif; ?>
                                        
                                        <?php if ($bolao['publico'] == 1): ?>
                                            <span class="badge badge-info">Público</span>
                                        <?php else: ?>
                                            <span class="badge badge-warning">Privado</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Período</th>
                                    <td>
                                        De <?= formatDateTime($bolao['data_inicio']) ?><br>
                                        Até <?= formatDateTime($bolao['data_fim']) ?>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Limite para Palpites</th>
                                    <td><?= $bolao['data_limite_palpitar'] ? formatDateTime($bolao['data_limite_palpitar']) : 'Não definido' ?></td>
                                </tr>
                                <tr>
                                    <th>Participantes</th>
                                    <td>
                                        <strong><?= $bolao['total_participantes'] ?></strong> total
                                        (<strong><?= $bolao['participantes_confirmados'] ?></strong> confirmados)
                                        <?php if ($bolao['max_participantes'] > 0): ?>
                                            <br>Limite: <?= $bolao['max_participantes'] ?> participantes
                                        <?php endif; ?>
                                    </td>
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
                                    <th>Progresso dos Jogos</th>
                                    <td>
                                        <div class="progress">
                                            <?php
                                            $totalJogos = count($jogos);
                                            $porcentagemFinalizados = $totalJogos > 0 ? ($jogosFinalizados / $totalJogos) * 100 : 0;
                                            $porcentagemEmAndamento = $totalJogos > 0 ? ($jogosEmAndamento / $totalJogos) * 100 : 0;
                                            ?>
                                            <div class="progress-bar bg-success" role="progressbar" 
                                                 style="width: <?= $porcentagemFinalizados ?>%"
                                                 title="<?= $jogosFinalizados ?> jogos finalizados">
                                                <?= $jogosFinalizados ?>
                                            </div>
                                            <div class="progress-bar bg-warning" role="progressbar" 
                                                 style="width: <?= $porcentagemEmAndamento ?>%"
                                                 title="<?= $jogosEmAndamento ?> jogos em andamento">
                                                <?= $jogosEmAndamento ?>
                                            </div>
                                        </div>
                                        <small class="text-muted">
                                            <?= $jogosFinalizados ?> finalizados, 
                                            <?= $jogosEmAndamento ?> em andamento, 
                                            <?= $totalJogos - ($jogosFinalizados + $jogosEmAndamento) ?> não iniciados
                                        </small>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    <!-- Campeonatos -->
                    <div class="card mt-4">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-trophy"></i> Campeonatos</h3>
                        </div>
                        <div class="card-body">
                            <?php if (empty($campeonatos)): ?>
                                <p class="text-muted">Nenhum campeonato selecionado.</p>
                            <?php else: ?>
                                <div class="d-flex flex-wrap gap-2">
                                    <?php foreach ($campeonatos as $campeonato): ?>
                                        <span class="badge badge-info">
                                            <?= htmlspecialchars($campeonato['nome']) ?>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Imagem do Bolão -->
                    <?php if (!empty($bolao['imagem_bolao_url'])): ?>
                    <div class="card mt-4">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-image"></i> Imagem do Bolão</h3>
                        </div>
                        <div class="card-body text-center">
                            <img src="<?= APP_URL ?>/<?= htmlspecialchars($bolao['imagem_bolao_url']) ?>" 
                                 alt="<?= htmlspecialchars($bolao['nome']) ?>" 
                                 class="img-fluid" style="max-height: 300px;">
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Jogos -->
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-futbol"></i> 
                                Jogos (<?= count($jogos) ?>)
                            </h3>
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
                                                <th>Partida</th>
                                                <th>Placar</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($jogos as $jogo): 
                                                $dataJogo = new DateTime($jogo['data_formatada'] ?? $jogo['data']);
                                                $statusClass = 'secondary';
                                                $statusText = 'Não iniciado';
                                                
                                                switch ($jogo['status']) {
                                                    case 'LIVE':
                                                    case '1H':
                                                    case '2H':
                                                    case 'HT':
                                                        $statusClass = 'warning';
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
                                            <tr>
                                                <td>
                                                    <?= $dataJogo->format('d/m/Y') ?><br>
                                                    <small class="text-muted"><?= $dataJogo->format('H:i') ?></small>
                                                </td>
                                                <td>
                                                    <div class="d-flex flex-column">
                                                        <span><?= htmlspecialchars($jogo['time_casa']) ?></span>
                                                        <span><?= htmlspecialchars($jogo['time_visitante']) ?></span>
                                                        <small class="text-muted"><?= htmlspecialchars($jogo['campeonato']) ?></small>
                                                    </div>
                                                </td>
                                                <td class="text-center">
                                                    <?php if (isset($jogo['resultado_casa']) && isset($jogo['resultado_visitante'])): ?>
                                                        <span class="badge badge-success">
                                                            <?= $jogo['resultado_casa'] ?><br>
                                                            <?= $jogo['resultado_visitante'] ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="badge badge-secondary">-<br>-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="badge badge-<?= $statusClass ?>">
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

                    <!-- Ações -->
                    <div class="card mt-4">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-cogs"></i> Ações</h3>
                        </div>
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                <a href="<?= APP_URL ?>/admin/editar-resultados.php?id=<?= $bolao['id'] ?>" 
                                   class="btn btn-success btn-block">
                                    <i class="fas fa-futbol"></i> Gerenciar Resultados
                                </a>
                                
                                <a href="<?= APP_URL ?>/admin/editar-bolao.php?id=<?= $bolao['id'] ?>" 
                                   class="btn btn-primary btn-block">
                                    <i class="fas fa-edit"></i> Editar Informações
                                </a>
                                
                                <?php if ($bolao['status'] == 1): ?>
                                    <a href="<?= APP_URL ?>/admin/boloes.php?action=status&id=<?= $bolao['id'] ?>&status=0" 
                                       class="btn btn-warning btn-block"
                                       onclick="return confirm('Tem certeza que deseja desativar este bolão?')">
                                        <i class="fas fa-ban"></i> Desativar Bolão
                                    </a>
                                <?php else: ?>
                                    <a href="<?= APP_URL ?>/admin/boloes.php?action=status&id=<?= $bolao['id'] ?>&status=1" 
                                       class="btn btn-info btn-block"
                                       onclick="return confirm('Tem certeza que deseja ativar este bolão?')">
                                        <i class="fas fa-check"></i> Ativar Bolão
                                    </a>
                                <?php endif; ?>
                                
                                <a href="<?= APP_URL ?>/admin/boloes.php?action=delete&id=<?= $bolao['id'] ?>" 
                                   class="btn btn-danger btn-block"
                                   onclick="return confirm('ATENÇÃO! Esta ação não pode ser desfeita. Tem certeza que deseja excluir este bolão?')">
                                    <i class="fas fa-trash"></i> Excluir Bolão
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<?php require_once '../templates/admin/footer.php'; ?> 