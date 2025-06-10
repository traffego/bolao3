<?php
/**
 * Admin Bolão View - Bolão Vitimba
 */
require_once '../config/config.php';require_once '../includes/functions.php';

// Check if admin is logged in
if (!isAdmin()) {
    $_SESSION['redirect_after_login'] = APP_URL . '/admin/bolao.php';
    redirect(APP_URL . '/admin/login.php');
}

// Get bolão ID from URL
$bolaoId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($bolaoId <= 0) {
    setFlashMessage('danger', 'Bolão não encontrado.');
    redirect(APP_URL . '/admin/boloes.php');
}

// Get bolão data
$bolao = dbFetchOne(
    "SELECT b.*, a.nome as admin_nome, 
            COUNT(DISTINCT p.jogador_id) as total_jogadores,
            COUNT(DISTINCT j.id) as total_jogos
     FROM dados_boloes b
     LEFT JOIN participacoes p ON p.bolao_id = b.id
     LEFT JOIN jogos j ON j.bolao_id = b.id
     LEFT JOIN administrador a ON a.id = b.admin_id
     WHERE b.id = ?
     GROUP BY b.id", 
    [$bolaoId]
);

if (!$bolao) {
    setFlashMessage('danger', 'Bolão não encontrado.');
    redirect(APP_URL . '/admin/boloes.php');
}

// Get jogos do bolão
$jogos = dbFetchAll(
    "SELECT j.*, 
            r.gols_casa, r.gols_visitante, r.status as resultado_status,
            (SELECT COUNT(*) FROM palpites WHERE jogo_id = j.id) as total_palpites
     FROM jogos j
     LEFT JOIN resultados r ON r.jogo_id = j.id
     WHERE j.bolao_id = ?
     ORDER BY j.data_hora ASC", 
    [$bolaoId]
);

// Get jogadores participantes
$jogadores = dbFetchAll(
    "SELECT j.*, p.data_entrada,
            (SELECT COUNT(*) FROM palpites WHERE jogador_id = j.id AND bolao_id = ?) as total_palpites,
            COALESCE((SELECT SUM(pontos) FROM palpites WHERE jogador_id = j.id AND bolao_id = ?), 0) as pontos_total
     FROM jogador j
     JOIN participacoes p ON p.jogador_id = j.id
     WHERE p.bolao_id = ?
     ORDER BY pontos_total DESC, j.nome ASC", 
    [$bolaoId, $bolaoId, $bolaoId]
);

// Page title
$pageTitle = 'Detalhes do Bolão: ' . $bolao['nome'];

// Include admin header
include '../templates/admin/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1><?= sanitize($bolao['nome']) ?></h1>
    <div>
        <a href="<?= APP_URL ?>/admin/editar-bolao.php?id=<?= $bolaoId ?>" class="btn btn-warning">
            <i class="bi bi-pencil"></i> Editar Bolão
        </a>
        <a href="<?= APP_URL ?>/admin/novo-jogo.php?bolao_id=<?= $bolaoId ?>" class="btn btn-primary">
            <i class="bi bi-plus-circle"></i> Adicionar Jogo
        </a>
        <a href="<?= APP_URL ?>/admin/boloes.php" class="btn btn-secondary">
            <i class="bi bi-arrow-left"></i> Voltar
        </a>
    </div>
</div>

<!-- Bolão Summary -->
<div class="row mb-4">
    <div class="col-lg-8">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Informações do Bolão</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Status:</strong> 
                            <?php if ($bolao['status'] === 'aberto'): ?>
                                <span class="badge bg-success">Aberto</span>
                            <?php elseif ($bolao['status'] === 'fechado'): ?>
                                <span class="badge bg-warning">Fechado</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Finalizado</span>
                            <?php endif; ?>
                        </p>
                        <p><strong>Período:</strong> <?= formatDate($bolao['data_inicio']) ?> a <?= formatDate($bolao['data_fim']) ?></p>
                        <p><strong>Valor de Participação:</strong> <?= formatMoney($bolao['valor_participacao']) ?></p>
                        <p><strong>Prêmio Total:</strong> <?= formatMoney($bolao['premio_total']) ?></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Participantes:</strong> <?= $bolao['total_jogadores'] ?><?= $bolao['max_participantes'] ? ' / ' . $bolao['max_participantes'] : '' ?></p>
                        <p><strong>Jogos:</strong> <?= $bolao['total_jogos'] ?></p>
                        <p><strong>Criado por:</strong> <?= sanitize($bolao['admin_nome']) ?></p>
                        <p><strong>Data de Criação:</strong> <?= formatDateTime($bolao['data_criacao']) ?></p>
                    </div>
                </div>
                <h6 class="mt-3">Descrição</h6>
                <div class="border rounded p-3 bg-light mb-3">
                    <?= nl2br(sanitize($bolao['descricao'] ?? 'Nenhuma descrição disponível.')) ?>
                </div>
                
                <h6>Regras</h6>
                <div class="border rounded p-3 bg-light">
                    <?= nl2br(sanitize($bolao['regras'] ?? 'Nenhuma regra específica.')) ?>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Ações</h5>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <?php if ($bolao['status'] === 'aberto'): ?>
                        <button type="button" class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#closeModal">
                            <i class="bi bi-lock"></i> Fechar Bolão
                        </button>
                    <?php elseif ($bolao['status'] === 'fechado'): ?>
                        <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#openModal">
                            <i class="bi bi-unlock"></i> Reabrir Bolão
                        </button>
                        <button type="button" class="btn btn-secondary" data-bs-toggle="modal" data-bs-target="#finishModal">
                            <i class="bi bi-check-circle"></i> Finalizar Bolão
                        </button>
                    <?php endif; ?>
                    
                    <a href="<?= APP_URL ?>/admin/atualizar-resultados.php?bolao_id=<?= $bolaoId ?>" class="btn btn-info">
                        <i class="bi bi-arrow-repeat"></i> Atualizar Resultados
                    </a>
                    
                    <a href="<?= APP_URL ?>/admin/calcular-pontos.php?bolao_id=<?= $bolaoId ?>" class="btn btn-primary">
                        <i class="bi bi-calculator"></i> Calcular Pontuação
                    </a>
                    
                    <a href="<?= APP_URL ?>/admin/ranking.php?bolao_id=<?= $bolaoId ?>" class="btn btn-success">
                        <i class="bi bi-trophy"></i> Ver Ranking
                    </a>
                    
                    <a href="<?= APP_URL ?>/admin/exportar.php?bolao_id=<?= $bolaoId ?>" class="btn btn-outline-primary">
                        <i class="bi bi-download"></i> Exportar Dados
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Tabs for Jogos and Jogadores -->
<ul class="nav nav-tabs mb-4" id="bolaoTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active" id="jogos-tab" data-bs-toggle="tab" data-bs-target="#jogos" type="button" role="tab" aria-controls="jogos" aria-selected="true">
            Jogos (<?= count($jogos) ?>)
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="jogadores-tab" data-bs-toggle="tab" data-bs-target="#jogadores" type="button" role="tab" aria-controls="jogadores" aria-selected="false">
            Participantes (<?= count($jogadores) ?>)
        </button>
    </li>
</ul>

<div class="tab-content" id="bolaoTabsContent">
    <!-- Jogos Tab -->
    <div class="tab-pane fade show active" id="jogos" role="tabpanel" aria-labelledby="jogos-tab">
        <div class="card">
            <div class="card-body">
                <?php if (count($jogos) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Times</th>
                                    <th>Data/Hora</th>
                                    <th>Local</th>
                                    <th>Status</th>
                                    <th>Resultado</th>
                                    <th>Palpites</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($jogos as $jogo): ?>
                                    <tr>
                                        <td><?= $jogo['id'] ?></td>
                                        <td>
                                            <div><?= sanitize($jogo['time_casa']) ?></div>
                                            <small class="text-muted">vs</small>
                                            <div><?= sanitize($jogo['time_visitante']) ?></div>
                                        </td>
                                        <td><?= formatDateTime($jogo['data_hora']) ?></td>
                                        <td><?= sanitize($jogo['local'] ?? 'N/A') ?></td>
                                        <td>
                                            <?php if ($jogo['status'] === 'agendado'): ?>
                                                <span class="badge bg-green">Agendado</span>
                                            <?php elseif ($jogo['status'] === 'em_andamento'): ?>
                                                <span class="badge bg-warning">Em Andamento</span>
                                            <?php elseif ($jogo['status'] === 'finalizado'): ?>
                                                <span class="badge bg-success">Finalizado</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">Cancelado</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (isset($jogo['gols_casa']) && isset($jogo['gols_visitante'])): ?>
                                                <span class="badge bg-light text-dark">
                                                    <?= $jogo['gols_casa'] ?> x <?= $jogo['gols_visitante'] ?>
                                                    <?php if ($jogo['resultado_status'] === 'parcial'): ?>
                                                        <small class="text-muted">(Parcial)</small>
                                                    <?php endif; ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted">Não definido</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= $jogo['total_palpites'] ?></td>
                                        <td>
                                            <div class="btn-group">
                                                <a href="<?= APP_URL ?>/admin/editar-jogo.php?id=<?= $jogo['id'] ?>" class="btn btn-sm btn-warning" title="Editar">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                                <a href="<?= APP_URL ?>/admin/resultado.php?jogo_id=<?= $jogo['id'] ?>" class="btn btn-sm btn-primary" title="Definir Resultado">
                                                    <i class="bi bi-trophy"></i>
                                                </a>
                                                <a href="<?= APP_URL ?>/admin/palpites.php?jogo_id=<?= $jogo['id'] ?>" class="btn btn-sm btn-info" title="Ver Palpites">
                                                    <i class="bi bi-list-check"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        Nenhum jogo cadastrado para este bolão.
                    </div>
                    <div class="text-center">
                        <a href="<?= APP_URL ?>/admin/novo-jogo.php?bolao_id=<?= $bolaoId ?>" class="btn btn-primary">
                            <i class="bi bi-plus-circle"></i> Adicionar Jogo
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Jogadores Tab -->
    <div class="tab-pane fade" id="jogadores" role="tabpanel" aria-labelledby="jogadores-tab">
        <div class="card">
            <div class="card-body">
                <?php if (count($jogadores) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Nome</th>
                                    <th>Email</th>
                                    <th>Data de Entrada</th>
                                    <th>Palpites</th>
                                    <th>Pontos</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($jogadores as $jogador): ?>
                                    <tr>
                                        <td><?= $jogador['id'] ?></td>
                                        <td><?= sanitize($jogador['nome']) ?></td>
                                        <td><?= sanitize($jogador['email']) ?></td>
                                        <td><?= formatDateTime($jogador['data_entrada']) ?></td>
                                        <td>
                                            <?= $jogador['total_palpites'] ?> / <?= $bolao['total_jogos'] ?>
                                            (<?= $bolao['total_jogos'] > 0 ? round(($jogador['total_palpites'] / $bolao['total_jogos']) * 100) : 0 ?>%)
                                        </td>
                                        <td><?= $jogador['pontos_total'] ?></td>
                                        <td>
                                            <div class="btn-group">
                                                <a href="<?= APP_URL ?>/admin/jogador.php?id=<?= $jogador['id'] ?>" class="btn btn-sm btn-primary" title="Ver detalhes">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                                <a href="<?= APP_URL ?>/admin/jogador-palpites.php?jogador_id=<?= $jogador['id'] ?>&bolao_id=<?= $bolaoId ?>" class="btn btn-sm btn-info" title="Ver palpites">
                                                    <i class="bi bi-list-check"></i>
                                                </a>
                                                <button type="button" class="btn btn-sm btn-danger remove-jogador" data-id="<?= $jogador['id'] ?>" data-nome="<?= htmlspecialchars($jogador['nome'], ENT_QUOTES) ?>" title="Remover do bolão">
                                                    <i class="bi bi-x-circle"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        Nenhum jogador participando deste bolão.
                    </div>
                    <div class="text-center">
                        <a href="<?= APP_URL ?>/admin/adicionar-jogador.php?bolao_id=<?= $bolaoId ?>" class="btn btn-primary">
                            <i class="bi bi-plus-circle"></i> Adicionar Jogador
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Close Bolão Modal -->
<div class="modal fade" id="closeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Fechar Bolão</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <p>Tem certeza que deseja fechar este bolão? Isso impedirá que novos jogadores se inscrevam.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <form action="<?= APP_URL ?>/admin/acao-bolao.php" method="post">
                    <input type="hidden" name="action" value="close">
                    <input type="hidden" name="bolao_id" value="<?= $bolaoId ?>">
                    <button type="submit" class="btn btn-warning">Fechar Bolão</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Open Bolão Modal -->
<div class="modal fade" id="openModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Reabrir Bolão</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <p>Tem certeza que deseja reabrir este bolão? Isso permitirá que novos jogadores se inscrevam.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <form action="<?= APP_URL ?>/admin/acao-bolao.php" method="post">
                    <input type="hidden" name="action" value="open">
                    <input type="hidden" name="bolao_id" value="<?= $bolaoId ?>">
                    <button type="submit" class="btn btn-success">Reabrir Bolão</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Finish Bolão Modal -->
<div class="modal fade" id="finishModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Finalizar Bolão</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <p>Tem certeza que deseja finalizar este bolão? Esta ação é irreversível.</p>
                <div class="alert alert-warning">
                    Certifique-se de que todos os resultados foram registrados e a pontuação foi calculada antes de finalizar.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <form action="<?= APP_URL ?>/admin/acao-bolao.php" method="post">
                    <input type="hidden" name="action" value="finish">
                    <input type="hidden" name="bolao_id" value="<?= $bolaoId ?>">
                    <button type="submit" class="btn btn-danger">Finalizar Bolão</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Remove Jogador Modal -->
<div class="modal fade" id="removeJogadorModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Remover Jogador</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <p>Tem certeza que deseja remover <span id="jogador-nome"></span> deste bolão?</p>
                <div class="alert alert-warning">
                    Esta ação também removerá todos os palpites deste jogador no bolão.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <form action="<?= APP_URL ?>/admin/acao-bolao.php" method="post">
                    <input type="hidden" name="action" value="remove_jogador">
                    <input type="hidden" name="bolao_id" value="<?= $bolaoId ?>">
                    <input type="hidden" name="jogador_id" id="jogador_id" value="">
                    <button type="submit" class="btn btn-danger">Remover</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Handle remove jogador buttons
    const removeButtons = document.querySelectorAll('.remove-jogador');
    removeButtons.forEach(button => {
        button.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const nome = this.getAttribute('data-nome');
            
            document.getElementById('jogador_id').value = id;
            document.getElementById('jogador-nome').textContent = nome;
            
            const modal = new bootstrap.Modal(document.getElementById('removeJogadorModal'));
            modal.show();
        });
    });
});
</script>

<?php include '../templates/admin/footer.php'; ?> 