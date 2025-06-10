<?php
/**
 * HomePage - Bolão Football
 */
require_once 'config/config.php';
require_once 'config/database.php';
require_once 'includes/functions.php';

// Page title
$pageTitle = 'Início';

// Get active boloes
$boloes = dbFetchAll("SELECT b.*, 
                       COUNT(DISTINCT p.jogador_id) as total_jogadores,
                       a.nome as admin_nome
                    FROM dados_boloes b
                    LEFT JOIN palpites p ON p.bolao_id = b.id
                    LEFT JOIN administrador a ON a.id = b.admin_id
                    WHERE b.status = 1 
                    GROUP BY b.id
                    ORDER BY b.data_fim ASC
                    LIMIT 4");

// Get latest results
$resultados = dbFetchAll("SELECT r.*, j.equipe_casa, j.equipe_visitante, j.campeonato, j.data_hora
                       FROM resultados r
                       JOIN jogos j ON j.id = r.jogo_id
                       WHERE r.placar_casa IS NOT NULL AND r.placar_visitante IS NOT NULL
                       ORDER BY j.data_hora DESC
                       LIMIT 6");

// Include header
include TEMPLATE_DIR . '/header.php';
?>

<!-- Hero Section -->
<div class="bg-primary text-white py-5 mb-4 rounded">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h1>Bem-vindo ao Bolão Football!</h1>
                <p class="lead">O melhor sistema de bolões de futebol! Participe, faça seus palpites e concorra a prêmios incríveis.</p>
                <?php if (!isLoggedIn()): ?>
                    <a href="<?= APP_URL ?>/cadastro.php" class="btn btn-light btn-lg me-2">Cadastre-se</a>
                    <a href="<?= APP_URL ?>/login.php" class="btn btn-outline-light btn-lg">Entrar</a>
                <?php else: ?>
                    <a href="<?= APP_URL ?>/boloes.php" class="btn btn-light btn-lg">Ver Bolões Ativos</a>
                <?php endif; ?>
            </div>
            <div class="col-md-4 d-none d-md-block text-center">
                <i class="bi bi-trophy-fill" style="font-size: 8rem;"></i>
            </div>
        </div>
    </div>
</div>

<!-- Features -->
<div class="row g-4 mb-5">
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-body text-center">
                <i class="bi bi-controller fs-1 text-primary mb-3"></i>
                <h5 class="card-title">Simples de Participar</h5>
                <p class="card-text">Escolha um bolão, faça seus palpites e acompanhe os resultados em tempo real.</p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-body text-center">
                <i class="bi bi-graph-up-arrow fs-1 text-primary mb-3"></i>
                <h5 class="card-title">Estatísticas Completas</h5>
                <p class="card-text">Acompanhe seu desempenho, ranking e estatísticas detalhadas dos seus palpites.</p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-body text-center">
                <i class="bi bi-cash-coin fs-1 text-primary mb-3"></i>
                <h5 class="card-title">Prêmios Reais</h5>
                <p class="card-text">Prêmios em dinheiro para os melhores colocados em cada bolão.</p>
            </div>
        </div>
    </div>
</div>

<!-- Available Boloes -->
<h2 class="mb-3">Bolões Disponíveis</h2>
<div class="row g-4 mb-5">
    <?php if (count($boloes) > 0): ?>
        <?php foreach ($boloes as $bolao): ?>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><?= sanitize($bolao['nome']) ?></h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($bolao['descricao'])): ?>
                            <p><?= nl2br(sanitize($bolao['descricao'])) ?></p>
                        <?php endif; ?>
                        <div class="d-flex justify-content-between mb-2">
                            <span><i class="bi bi-calendar-event"></i> Término: <?= formatDate($bolao['data_fim']) ?></span>
                            <span><i class="bi bi-people-fill"></i> <?= $bolao['total_jogadores'] ?> jogadores</span>
                        </div>
                        <div class="d-flex justify-content-between mb-3">
                            <span><i class="bi bi-cash"></i> Valor: <?= formatMoney($bolao['valor_participacao']) ?></span>
                            <span><i class="bi bi-trophy-fill"></i> Prêmio: <?= formatMoney($bolao['premio_total']) ?></span>
                        </div>
                        <div class="text-center">
                            <a href="<?= APP_URL ?>/bolao.php?id=<?= $bolao['id'] ?>" class="btn btn-primary">Ver Detalhes</a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="col-12">
            <div class="alert alert-info">
                Não há bolões disponíveis no momento. Volte mais tarde!
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Recent Results -->
<?php if (count($resultados) > 0): ?>
    <h2 class="mb-3">Resultados Recentes</h2>
    <div class="row">
        <?php foreach ($resultados as $resultado): ?>
            <div class="col-md-4 mb-3">
                <div class="card">
                    <div class="card-header">
                        <small class="text-muted"><?= sanitize($resultado['campeonato']) ?></small>
                        <small class="text-muted float-end"><?= formatDateTime($resultado['data_hora']) ?></small>
                    </div>
                    <div class="card-body text-center">
                        <div class="row align-items-center">
                            <div class="col-4 text-end">
                                <strong><?= sanitize($resultado['equipe_casa']) ?></strong>
                            </div>
                            <div class="col-4">
                                <span class="fs-4 fw-bold text-center">
                                    <?= $resultado['placar_casa'] ?> - <?= $resultado['placar_visitante'] ?>
                                </span>
                            </div>
                            <div class="col-4 text-start">
                                <strong><?= sanitize($resultado['equipe_visitante']) ?></strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- Steps to Participate -->
<div class="bg-light p-4 rounded mt-5">
    <h2 class="mb-4 text-center">Como Participar</h2>
    <div class="row g-4">
        <div class="col-md-3">
            <div class="text-center mb-3">
                <div class="bg-primary text-white rounded-circle mx-auto d-flex align-items-center justify-content-center" style="width: 60px; height: 60px;">
                    <h3 class="mb-0">1</h3>
                </div>
            </div>
            <h5 class="text-center">Cadastre-se</h5>
            <p class="text-center">Crie sua conta gratuitamente em nosso sistema.</p>
        </div>
        <div class="col-md-3">
            <div class="text-center mb-3">
                <div class="bg-primary text-white rounded-circle mx-auto d-flex align-items-center justify-content-center" style="width: 60px; height: 60px;">
                    <h3 class="mb-0">2</h3>
                </div>
            </div>
            <h5 class="text-center">Escolha um Bolão</h5>
            <p class="text-center">Selecione um bolão ativo para participar.</p>
        </div>
        <div class="col-md-3">
            <div class="text-center mb-3">
                <div class="bg-primary text-white rounded-circle mx-auto d-flex align-items-center justify-content-center" style="width: 60px; height: 60px;">
                    <h3 class="mb-0">3</h3>
                </div>
            </div>
            <h5 class="text-center">Faça seus Palpites</h5>
            <p class="text-center">Registre seus palpites para os jogos selecionados.</p>
        </div>
        <div class="col-md-3">
            <div class="text-center mb-3">
                <div class="bg-primary text-white rounded-circle mx-auto d-flex align-items-center justify-content-center" style="width: 60px; height: 60px;">
                    <h3 class="mb-0">4</h3>
                </div>
            </div>
            <h5 class="text-center">Ganhe Prêmios</h5>
            <p class="text-center">Acompanhe o ranking e ganhe prêmios em dinheiro.</p>
        </div>
    </div>
    <div class="text-center mt-4">
        <a href="<?= APP_URL ?>/como-funciona.php" class="btn btn-outline-primary">Saiba Mais</a>
    </div>
</div>

<?php
// Include footer
include TEMPLATE_DIR . '/footer.php';
?> 