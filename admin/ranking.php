<?php
/**
 * Admin Ranking - Bolão Vitimba
 */
require_once '../config/config.php';
require_once '../includes/functions.php';

// Check if admin is logged in
if (!isAdmin()) {
    setFlashMessage('danger', 'Acesso negado. Faça login como administrador.');
    redirect(APP_URL . '/admin/login.php');
}

// Get bolão ID from URL
$bolaoId = filter_input(INPUT_GET, 'bolao_id', FILTER_VALIDATE_INT);
if (!$bolaoId) {
    setFlashMessage('danger', 'ID do bolão inválido.');
    redirect(APP_URL . '/admin/boloes.php');
}

// Get bolão data with all games
$bolao = dbFetchOne(
    "SELECT b.*, 
            (SELECT COUNT(DISTINCT p.id) FROM palpites p WHERE p.bolao_id = b.id) as total_palpites,
            (SELECT COUNT(DISTINCT jogador_id) FROM palpites p WHERE p.bolao_id = b.id) as total_jogadores
     FROM dados_boloes b
     WHERE b.id = ?", 
    [$bolaoId]
);

if (!$bolao) {
    setFlashMessage('danger', 'Bolão não encontrado.');
    redirect(APP_URL . '/admin/boloes.php');
}

// Decodificar jogos do bolão
$jogos = json_decode($bolao['jogos'], true) ?: [];

// Buscar todos os palpites com detalhes e acertos
$sql = "WITH jogos_resultados AS (
    SELECT 
        jogo.id,
        CASE 
            WHEN CAST(JSON_EXTRACT(jogo.value, '$.resultado_casa') AS SIGNED) = CAST(JSON_EXTRACT(jogo.value, '$.resultado_visitante') AS SIGNED) THEN '0'
            WHEN CAST(JSON_EXTRACT(jogo.value, '$.resultado_casa') AS SIGNED) > CAST(JSON_EXTRACT(jogo.value, '$.resultado_visitante') AS SIGNED) THEN '1'
            ELSE '2'
        END as resultado,
        JSON_EXTRACT(jogo.value, '$.time_casa') as time_casa,
        JSON_EXTRACT(jogo.value, '$.time_visitante') as time_visitante,
        JSON_EXTRACT(jogo.value, '$.resultado_casa') as gols_casa,
        JSON_EXTRACT(jogo.value, '$.resultado_visitante') as gols_visitante,
        JSON_EXTRACT(jogo.value, '$.status') as status
    FROM dados_boloes b
    CROSS JOIN JSON_TABLE(
        b.jogos,
        '$[*]' COLUMNS(
            id VARCHAR(20) PATH '$.id',
            value JSON PATH '$'
        )
    ) as jogo
    WHERE b.id = ?
)
SELECT 
    p.id as palpite_id,
    p.jogador_id,
    p.data_palpite,
    p.palpites,
    j.nome as jogador_nome,
    j.email as jogador_email,
    (
        SELECT COUNT(*)
        FROM jogos_resultados jr
        WHERE jr.status = 'FT'
        AND jr.resultado = JSON_EXTRACT(p.palpites, CONCAT('$.jogos.', jr.id))
    ) as acertos,
    (
        SELECT COUNT(*)
        FROM jogos_resultados
        WHERE status = 'FT'
    ) as total_jogos_finalizados,
    (
        SELECT JSON_OBJECTAGG(
            jr.id,
            JSON_OBJECT(
                'palpite', JSON_EXTRACT(p.palpites, CONCAT('$.jogos.', jr.id)),
                'resultado', jr.resultado,
                'time_casa', jr.time_casa,
                'time_visitante', jr.time_visitante,
                'gols_casa', jr.gols_casa,
                'gols_visitante', jr.gols_visitante,
                'status', jr.status,
                'acertou', jr.status = 'FT' AND jr.resultado = JSON_EXTRACT(p.palpites, CONCAT('$.jogos.', jr.id))
            )
        )
        FROM jogos_resultados jr
        WHERE JSON_EXTRACT(p.palpites, CONCAT('$.jogos.', jr.id)) IS NOT NULL
    ) as detalhes_palpites
FROM palpites p
JOIN jogador j ON j.id = p.jogador_id
WHERE p.bolao_id = ?
ORDER BY acertos DESC, p.data_palpite DESC";

$palpites = dbFetchAll($sql, [$bolaoId, $bolaoId]);

// Page title
$pageTitle = 'Palpites do Bolão: ' . $bolao['nome'];
$currentPage = 'boloes';

// Include admin header
include '../templates/admin/header.php';
?>

<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1><?= htmlspecialchars($bolao['nome']) ?></h1>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            <!-- Cards de Estatísticas -->
            <div class="row">
                <div class="col-lg-3 col-6">
                    <div class="small-box bg-info">
                        <div class="inner">
                            <h3><?= count($palpites) ?></h3>
                            <p>Total de Palpites</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-list-ol"></i>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-3 col-6">
                    <div class="small-box bg-success">
                        <div class="inner">
                            <h3><?= count($jogos) ?></h3>
                            <p>Total de Jogos</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-futbol"></i>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-3 col-6">
                    <div class="small-box bg-warning">
                        <div class="inner">
                            <h3><?= count(array_unique(array_column($palpites, 'jogador_id'))) ?></h3>
                            <p>Participantes</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-users"></i>
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

            <!-- Lista de Palpites -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-list"></i> 
                        Lista de Palpites
                    </h3>
                    <div class="card-tools">
                        <span class="badge badge-info">
                            <?= count($palpites) ?> palpites registrados
                        </span>
                    </div>
                </div>
                <div class="card-body table-responsive p-0">
                    <table class="table table-hover text-nowrap">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Jogador</th>
                                <th>Data do Palpite</th>
                                <th class="text-center">Acertos</th>
                                <th>Palpites</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($palpites)): ?>
                                <tr>
                                    <td colspan="6" class="text-center">
                                        Nenhum palpite registrado ainda.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($palpites as $palpite): 
                                    $palpitesJson = json_decode($palpite['palpites'], true);
                                    $totalPalpites = isset($palpitesJson['jogos']) ? count($palpitesJson['jogos']) : 0;
                                    $detalhesPalpites = json_decode($palpite['detalhes_palpites'], true) ?: [];
                                    
                                    // Calcular aproveitamento
                                    $aproveitamento = $palpite['total_jogos_finalizados'] > 0 
                                        ? ($palpite['acertos'] / $palpite['total_jogos_finalizados']) * 100 
                                        : 0;
                                ?>
                                    <tr>
                                        <td>
                                            <span class="badge badge-primary">
                                                <?= $palpite['palpite_id'] ?>
                                            </span>
                                        </td>
                                        <td>
                                            <img src="https://www.gravatar.com/avatar/<?= md5(strtolower(trim($palpite['jogador_email']))) ?>?s=32&d=mp" 
                                                 class="img-circle mr-2" alt="Avatar">
                                            <?= htmlspecialchars($palpite['jogador_nome']) ?>
                                        </td>
                                        <td>
                                            <?= formatDateTime($palpite['data_palpite']) ?>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge badge-success">
                                                <?= $palpite['acertos'] ?>
                                            </span>
                                            <?php if ($palpite['total_jogos_finalizados'] > 0): ?>
                                                <div class="progress mt-1" style="height: 4px;">
                                                    <div class="progress-bar bg-success" 
                                                         role="progressbar" 
                                                         style="width: <?= $aproveitamento ?>%"
                                                         aria-valuenow="<?= $aproveitamento ?>" 
                                                         aria-valuemin="0" 
                                                         aria-valuemax="100">
                                                    </div>
                                                </div>
                                                <small class="text-muted">
                                                    <?= number_format($aproveitamento, 1) ?>%
                                                </small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <button type="button" 
                                                    class="btn btn-sm btn-info" 
                                                    data-toggle="modal" 
                                                    data-target="#modalPalpites"
                                                    data-palpites='<?= json_encode($detalhesPalpites) ?>'
                                                    data-jogador="<?= htmlspecialchars($palpite['jogador_nome']) ?>">
                                                <i class="fas fa-eye"></i>
                                                Ver <?= $totalPalpites ?> palpites
                                            </button>
                                        </td>
                                        <td>
                                            <div class="btn-group">
                                                <a href="<?= APP_URL ?>/admin/palpites-bolao.php?bolao_id=<?= $bolaoId ?>&jogador_id=<?= $palpite['jogador_id'] ?>" 
                                                   class="btn btn-sm btn-info" 
                                                   title="Ver Todos os Palpites">
                                                    <i class="fas fa-list"></i>
                                                </a>
                                                <a href="<?= APP_URL ?>/admin/jogador.php?id=<?= $palpite['jogador_id'] ?>" 
                                                   class="btn btn-sm btn-primary" 
                                                   title="Perfil do Jogador">
                                                    <i class="fas fa-user"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>
</div>

<!-- Modal para visualizar palpites -->
<div class="modal fade" id="modalPalpites" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Palpites do Jogador</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div id="palpitesContent"></div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Modal de palpites
    $('#modalPalpites').on('show.bs.modal', function (event) {
        const button = $(event.relatedTarget);
        const palpites = button.data('palpites');
        const jogador = button.data('jogador');
        const modal = $(this);
        
        modal.find('.modal-title').text('Palpites de ' + jogador);
        
        let content = '<div class="table-responsive"><table class="table">';
        content += `<thead>
            <tr>
                <th>Jogo</th>
                <th>Times</th>
                <th>Resultado</th>
                <th>Palpite</th>
                <th>Status</th>
            </tr>
        </thead><tbody>`;
        
        for (const [jogoId, dados] of Object.entries(palpites)) {
            const resultadoReal = dados.status === 'FT' 
                ? `${dados.gols_casa} x ${dados.gols_visitante}` 
                : '-';
            
            const statusClass = dados.status === 'FT' 
                ? (dados.acertou ? 'success' : 'danger')
                : 'warning';
            
            const statusText = dados.status === 'FT'
                ? (dados.acertou ? 'Acertou' : 'Errou')
                : 'Aguardando';
            
            content += `<tr>
                <td>Jogo ${jogoId}</td>
                <td>${dados.time_casa.replace(/"/g, '')} x ${dados.time_visitante.replace(/"/g, '')}</td>
                <td>${resultadoReal}</td>
                <td>${getPalpiteText(dados.palpite)}</td>
                <td><span class="badge badge-${statusClass}">${statusText}</span></td>
            </tr>`;
        }
        
        content += '</tbody></table></div>';
        modal.find('#palpitesContent').html(content);
    });
});

function getPalpiteText(palpite) {
    switch(palpite) {
        case '1': return 'Casa Vence';
        case '0': return 'Empate';
        case '2': return 'Visitante Vence';
        default: return 'Inválido';
    }
}
</script>

<?php require_once '../templates/admin/footer.php'; ?> 