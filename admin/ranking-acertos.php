<?php
require_once '../config/config.php';
require_once '../includes/functions.php';

// Verificar se o administrador está logado
if (!isAdmin()) {
    setFlashMessage('danger', 'Acesso negado. Faça login como administrador.');
    redirect(APP_URL . '/admin/login.php');
}

// Obter e validar ID do bolão
$bolaoId = filter_input(INPUT_GET, 'bolao_id', FILTER_VALIDATE_INT);
if (!$bolaoId) {
    setFlashMessage('danger', 'ID do bolão inválido.');
    redirect(APP_URL . '/admin/boloes.php');
}

// Carregar dados do bolão
$bolao = dbFetchOne(
    "SELECT * FROM dados_boloes WHERE id = ?", 
    [$bolaoId]
);

if (!$bolao) {
    setFlashMessage('danger', 'Bolão não encontrado.');
    redirect(APP_URL . '/admin/boloes.php');
}

// Carregar palpites
$palpites = dbFetchAll(
    "SELECT p.*, j.nome as jogador_nome, j.email as jogador_email
     FROM palpites p
     JOIN jogador j ON j.id = p.jogador_id
     WHERE p.bolao_id = ?
     ORDER BY p.data_palpite DESC",
    [$bolaoId]
);

// Decodificar jogos do bolão
$jogos = json_decode($bolao['jogos'], true);

// Função para calcular o resultado de um jogo
function calcularResultado($jogo) {
    if ($jogo['status'] !== 'FT') {
        return 'Não finalizado';
    }
    
    if ($jogo['resultado_casa'] == $jogo['resultado_visitante']) {
        return '0';
    }
    if ($jogo['resultado_casa'] > $jogo['resultado_visitante']) {
        return '1';
    }
    return '2';
}

// Função para mostrar o texto do resultado
function textoResultado($resultado) {
    switch ($resultado) {
        case '0': return '0 (Empate)';
        case '1': return '1 (Casa vence)';
        case '2': return '2 (Visitante vence)';
        default: return $resultado;
    }
}

// Processar os palpites e calcular acertos
$ranking = [];
$totalGeralAcertos = 0;
$jogosFinalizados = array_filter($jogos, function($jogo) {
    return $jogo['status'] === 'FT';
});
$totalJogosFinalizados = count($jogosFinalizados);

foreach ($palpites as $palpite) {
    $palpitesJogos = json_decode($palpite['palpites'], true);
    if (!isset($palpitesJogos['jogos'])) continue;

    $totalAcertos = 0;
    $detalhes = [];

    foreach ($palpitesJogos['jogos'] as $jogoId => $resultado) {
        // Encontrar o jogo correspondente
        $jogoEncontrado = null;
        foreach ($jogos as $jogo) {
            if ($jogo['id'] == $jogoId) {
                $jogoEncontrado = $jogo;
                break;
            }
        }

        if ($jogoEncontrado && $jogoEncontrado['status'] === 'FT') {
            $resultadoReal = calcularResultado($jogoEncontrado);
            $acertou = $resultado === $resultadoReal;
            if ($acertou) $totalAcertos++;

            $detalhes[] = [
                'id' => $jogoId,
                'time_casa' => $jogoEncontrado['time_casa'],
                'time_visitante' => $jogoEncontrado['time_visitante'],
                'gols_casa' => $jogoEncontrado['resultado_casa'],
                'gols_visitante' => $jogoEncontrado['resultado_visitante'],
                'resultado' => $resultadoReal,
                'palpite' => $resultado,
                'acertou' => $acertou
            ];
        }
    }

    $totalGeralAcertos += $totalAcertos;

    $ranking[] = [
        'jogador_id' => $palpite['jogador_id'],
        'jogador_nome' => $palpite['jogador_nome'],
        'jogador_email' => $palpite['jogador_email'],
        'total_palpites' => count($palpitesJogos['jogos']),
        'total_acertos' => $totalAcertos,
        'total_jogos_finalizados' => $totalJogosFinalizados,
        'detalhes_acertos' => $detalhes
    ];
}

// Ordenar o ranking
usort($ranking, function($a, $b) {
    if ($a['total_acertos'] !== $b['total_acertos']) {
        return $b['total_acertos'] - $a['total_acertos'];
    }
    if ($a['total_palpites'] !== $b['total_palpites']) {
        return $b['total_palpites'] - $a['total_palpites'];
    }
    return strcmp($a['jogador_nome'], $b['jogador_nome']);
});

// Template
$pageTitle = "Ranking de Acertos - " . htmlspecialchars($bolao['nome']);
$currentPage = "boloes";
require_once '../templates/admin/header.php';
?>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">
                        <i class="fas fa-trophy"></i> 
                        Ranking de Acertos
                    </h1>
                    <p class="text-muted">
                        <?= htmlspecialchars($bolao['nome']) ?>
                    </p>
                </div>
                <div class="col-sm-6">
                    <div class="float-sm-right">
                        <a href="<?= APP_URL ?>/admin/palpites-bolao.php?bolao_id=<?= $bolaoId ?>" class="btn btn-info">
                            <i class="fas fa-list"></i> Ver Palpites
                        </a>
                        <a href="<?= APP_URL ?>/admin/ver-bolao.php?id=<?= $bolaoId ?>" class="btn btn-primary">
                            <i class="fas fa-info-circle"></i> Detalhes
                        </a>
                        <a href="<?= APP_URL ?>/admin/boloes.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Voltar
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <section class="content">
        <div class="container-fluid">
            <!-- Cards de Estatísticas -->
            <div class="row">
                <div class="col-lg-3 col-6">
                    <div class="small-box bg-info">
                        <div class="inner">
                            <h3><?= count($ranking) ?></h3>
                            <p>Participantes</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-users"></i>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-3 col-6">
                    <div class="small-box bg-success">
                        <div class="inner">
                            <h3><?= $totalGeralAcertos ?></h3>
                            <p>Total de Acertos</p>
                            <?php if ($totalJogosFinalizados > 0): ?>
                                <small class="text-white">
                                    <?= $totalJogosFinalizados ?> jogos finalizados
                                </small>
                            <?php endif; ?>
                        </div>
                        <div class="icon">
                            <i class="fas fa-check"></i>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-3 col-6">
                    <div class="small-box bg-warning">
                        <div class="inner">
                            <?php 
                            $primeiroLugar = !empty($ranking) ? $ranking[0]['total_acertos'] : 0;
                            ?>
                            <h3><?= $primeiroLugar ?></h3>
                            <p>Recorde de Acertos</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-medal"></i>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-3 col-6">
                    <div class="small-box bg-danger">
                        <div class="inner">
                            <h3><?= formatMoney($bolao['premio_total']) ?></h3>
                            <p>Prêmio Total</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-dollar-sign"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabela de Ranking -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-trophy"></i> 
                        Ranking de Acertos
                    </h3>
                    <div class="card-tools">
                        <?php if ($totalJogosFinalizados > 0): ?>
                            <span class="badge badge-info">
                                <?= $totalJogosFinalizados ?> jogos finalizados
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card-body table-responsive p-0">
                    <table class="table table-hover text-nowrap">
                        <thead>
                            <tr>
                                <th width="80">Posição</th>
                                <th>Jogador</th>
                                <th class="text-center">Palpites</th>
                                <th class="text-center">Acertos</th>
                                <th class="text-center">Aproveitamento</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($ranking)): ?>
                                <tr>
                                    <td colspan="5" class="text-center">
                                        Nenhum palpite registrado ainda.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php 
                                $posicao = 1;
                                $lastAcertos = null;
                                $lastPosicao = 1;
                                
                                foreach ($ranking as $index => $jogador): 
                                    // Se o número de acertos for diferente do último, atualiza a posição
                                    if ($jogador['total_acertos'] !== $lastAcertos) {
                                        $posicao = $index + 1;
                                        $lastPosicao = $posicao;
                                    } else {
                                        $posicao = $lastPosicao;
                                    }
                                    $lastAcertos = $jogador['total_acertos'];

                                    // Calcular aproveitamento sobre jogos finalizados
                                    $aproveitamento = $jogador['total_jogos_finalizados'] > 0 ? 
                                        ($jogador['total_acertos'] / $jogador['total_jogos_finalizados']) * 100 : 0;
                                ?>
                                    <tr>
                                        <td class="text-center">
                                            <?php
                                            $medalha = '';
                                            if ($posicao === 1) $medalha = '🥇';
                                            else if ($posicao === 2) $medalha = '🥈';
                                            else if ($posicao === 3) $medalha = '🥉';
                                            ?>
                                            <span class="badge badge-primary"><?= $posicao ?></span>
                                            <?= $medalha ?>
                                        </td>
                                        <td>
                                            <img src="https://www.gravatar.com/avatar/<?= md5(strtolower(trim($jogador['jogador_email']))) ?>?s=32&d=mp" 
                                                 class="img-circle mr-2" alt="Avatar">
                                            <?= htmlspecialchars($jogador['jogador_nome']) ?>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge badge-info">
                                                <?= $jogador['total_palpites'] ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge badge-success">
                                                <?= $jogador['total_acertos'] ?>
                                            </span>
                                            <?php if ($jogador['total_jogos_finalizados'] > 0): ?>
                                                <small class="text-muted">
                                                    de <?= $jogador['total_jogos_finalizados'] ?> possíveis
                                                </small>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <div class="progress" style="height: 20px;">
                                                <div class="progress-bar bg-success" 
                                                     role="progressbar" 
                                                     style="width: <?= $aproveitamento ?>%"
                                                     aria-valuenow="<?= $aproveitamento ?>" 
                                                     aria-valuemin="0" 
                                                     aria-valuemax="100">
                                                    <?= number_format($aproveitamento, 1) ?>%
                                                </div>
                                            </div>
                                        </td>
                                    </tr>

                                    <!-- Modal de Detalhes -->
                                    <div class="modal fade" id="modalDetalhes<?= $jogador['jogador_id'] ?>" tabindex="-1" role="dialog">
                                        <div class="modal-dialog modal-lg" role="document">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">
                                                        Detalhes dos Palpites - <?= htmlspecialchars($jogador['jogador_nome']) ?>
                                                    </h5>
                                                    <button type="button" class="close" data-dismiss="modal">
                                                        <span>&times;</span>
                                                    </button>
                                                </div>
                                                <div class="modal-body">
                                                    <div class="table-responsive">
                                                        <table class="table">
                                                            <thead>
                                                                <tr>
                                                                    <th>Jogo</th>
                                                                    <th class="text-center">Resultado</th>
                                                                    <th class="text-center">Palpite</th>
                                                                    <th class="text-center">Status</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                <?php foreach ($jogador['detalhes_acertos'] as $detalhe): ?>
                                                                    <tr>
                                                                        <td>
                                                                            <?= $detalhe['time_casa'] ?>
                                                                            <?= $detalhe['gols_casa'] ?>
                                                                            x
                                                                            <?= $detalhe['gols_visitante'] ?>
                                                                            <?= $detalhe['time_visitante'] ?>
                                                                        </td>
                                                                        <td class="text-center">
                                                                            <span class="badge badge-info">
                                                                                <?= textoResultado($detalhe['resultado']) ?>
                                                                            </span>
                                                                        </td>
                                                                        <td class="text-center">
                                                                            <span class="badge badge-primary">
                                                                                <?= textoResultado($detalhe['palpite']) ?>
                                                                            </span>
                                                                        </td>
                                                                        <td class="text-center">
                                                                            <?php if ($detalhe['acertou']): ?>
                                                                                <span class="badge badge-success">
                                                                                    <i class="fas fa-check"></i> Acertou
                                                                                </span>
                                                                            <?php else: ?>
                                                                                <span class="badge badge-danger">
                                                                                    <i class="fas fa-times"></i> Errou
                                                                                </span>
                                                                            <?php endif; ?>
                                                                        </td>
                                                                    </tr>
                                                                <?php endforeach; ?>
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-dismiss="modal">
                                                        Fechar
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div class="card-footer">
                    <div class="row">
                        <div class="col-md-6">
                            <h5>Como funciona o ranking?</h5>
                            <ul class="list-unstyled">
                                <li><i class="fas fa-check text-success"></i> Cada acerto vale <strong>1 ponto</strong></li>
                                <li><i class="fas fa-sort text-info"></i> Ordem: acertos > total de palpites > nome</li>
                                <li><i class="fas fa-percentage text-warning"></i> Aproveitamento: acertos ÷ jogos com resultado</li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h5>Legenda dos Resultados</h5>
                            <ul class="list-unstyled">
                                <li><i class="fas fa-home text-primary"></i> Casa vence (1)</li>
                                <li><i class="fas fa-equals text-warning"></i> Empate (0)</li>
                                <li><i class="fas fa-plane text-success"></i> Visitante vence (2)</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<?php require_once '../templates/admin/footer.php'; ?> 