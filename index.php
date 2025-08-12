<?php
/**
 * HomePage - Bolão Vitimba
 */
require_once 'config/config.php';
require_once 'config/database.php';
require_once 'includes/functions.php';

// Page title
$pageTitle = 'Início';

// Get active boloes with slider images
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

// Get boloes with slider images
$slideBoloes = dbFetchAll("SELECT id, nome, imagem_bolao_url, descricao, premio_total, premio_rodada, valor_participacao 
                          FROM dados_boloes 
                          WHERE status = 1 
                          ORDER BY data_inicio DESC");

// Get latest results
$resultados = dbFetchAll("SELECT r.*, j.equipe_casa, j.equipe_visitante, j.campeonato, j.data_hora
                       FROM resultados r
                       JOIN jogos j ON j.id = r.jogo_id
                       WHERE r.placar_casa IS NOT NULL AND r.placar_visitante IS NOT NULL
                       ORDER BY j.data_hora DESC
                       LIMIT 6");

// Include header
include TEMPLATE_DIR . '/header.php';

// Adicionar CSS do Swiper
?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@8/swiper-bundle.min.css" />

<?php if (!empty($slideBoloes)): ?>
<!-- Slider Section -->
<div class="slider-container" style="margin-top: 3rem;">
    <div class="swiper main-slider" style="border-radius: 4px; overflow: hidden;">
        <div class="swiper-wrapper">
            <?php foreach ($slideBoloes as $bolao): ?>
                <div class="swiper-slide">
                    <a href="<?= APP_URL ?>/bolao.php?id=<?= $bolao['id'] ?>" class="slide-link">
                        <div class="position-relative">
                            <?php if (!empty($bolao['imagem_bolao_url'])): ?>
                                <img src="<?= APP_URL ?>/<?= $bolao['imagem_bolao_url'] ?>" 
                                     alt="<?= htmlspecialchars($bolao['nome']) ?>"
                                     class="w-100 slide-image"
                                     style="height: 600px; object-fit: cover;">
                            <?php else: ?>
                                <img src="<?= APP_URL ?>/public/img/noimage.jpg" 
                                     alt="Imagem não disponível"
                                     class="w-100 slide-image"
                                     style="height: 600px; object-fit: cover;">
                            <?php endif; ?>
                            <div class="position-absolute bottom-0 start-0 w-100 p-4" 
                                 style="background: linear-gradient(to top, rgba(0,0,0,0.8), transparent); pointer-events: none;">
                                <div class="container">
                                    <div class="mb-3">
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <h3 class="text-white mb-2">
                                                    <i class="bi bi-trophy-fill text-warning"></i> 
                                                    Prêmio Total: <?= formatMoney($bolao['premio_total']) ?>
                                                </h3>
                                                <?php if ($bolao['premio_rodada'] > 0): ?>
                                                    <h4 class="text-white mb-2">
                                                        <i class="bi bi-award-fill text-success"></i> 
                                                        Por Rodada: <?= formatMoney($bolao['premio_rodada']) ?>
                                                    </h4>
                                                <?php else: ?>
                                                    <h4 class="text-white mb-2">
                                                        <i class="bi bi-gift-fill text-warning"></i> 
                                                        Por Rodada: Surpresa...
                                                    </h4>
                                                <?php endif; ?>
                                            </div>
                                            <div class="col-md-6">
                                                <h4 class="text-white">
                                                    <i class="bi bi-ticket-fill text-info"></i> 
                                                    Participação: <?= formatMoney($bolao['valor_participacao']) ?>
                                                </h4>
                                            </div>
                                        </div>
                                    </div>
                                    <?php if (!empty($bolao['descricao'])): ?>
                                        <p class="text-white mb-3"><?= htmlspecialchars(substr($bolao['descricao'], 0, 150)) ?>...</p>
                                    <?php endif; ?>
                                    <div class="btn btn-primary btn-lg" style="pointer-events: none;">
                                        <i class="bi bi-play-fill"></i> Participar Agora
                                    </div>
                                </div>
                            </div>
                        </div>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="swiper-pagination"></div>
        <div class="swiper-button-next"></div>
        <div class="swiper-button-prev"></div>
    </div>
</div>

<!-- Swiper JS -->
<script src="https://cdn.jsdelivr.net/npm/swiper@8/swiper-bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const swiper = new Swiper('.main-slider', {
        loop: true,
        autoplay: {
            delay: 5000,
            disableOnInteraction: false,
        },
        pagination: {
            el: '.swiper-pagination',
            clickable: true,
        },
        navigation: {
            nextEl: '.swiper-button-next',
            prevEl: '.swiper-button-prev',
        },

    });



    // Prevenir que os controles do swiper interfiram com o clique do slide
    const swiperControls = document.querySelectorAll('.swiper-button-next, .swiper-button-prev, .swiper-pagination');
    swiperControls.forEach(control => {
        control.addEventListener('click', function(e) {
            e.stopPropagation();
        });
    });
});
</script>
<?php endif; ?>



<!-- Steps to Participate -->
<div class="container" style="margin-top: 5rem;">
    <div class="row">
        <div class="col-12 px-0">
            <div class="py-5 mb-5 w-100 bg-green" style="margin-left: calc(-50vw + 50%); margin-right: calc(-50vw + 50%); width: 100vw !important;">
                <div class="container">
                    <h2 class="text-center mb-5">Como Participar</h2>
                    <div class="row g-4 justify-content-center">
                        <div class="col-lg-2 col-md-3 col-sm-6">
                            <div class="card h-100 border-0 shadow card-hover" style="border-radius: 15px;">
                                <div class="card-body text-center p-4">
                                    <div class="mb-4">
                                        <div class="bg-green text-white rounded-circle mx-auto d-flex align-items-center justify-content-center" 
                                             style="width: 80px; height: 80px;">
                                            <i class="bi bi-person-plus-fill fs-1"></i>
                                        </div>
                                    </div>
                                    <h4 class="mb-3 text-white">Cadastre-se</h4>
                                    <p class="mb-0 text-white">Crie sua conta gratuitamente em nosso sistema.</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-2 col-md-3 col-sm-6">
                            <div class="card h-100 border-0 shadow card-hover" style="border-radius: 15px;">
                                <div class="card-body text-center p-4">
                                    <div class="mb-4">
                                        <div class="bg-green text-white rounded-circle mx-auto d-flex align-items-center justify-content-center" 
                                             style="width: 80px; height: 80px;">
                                            <i class="bi bi-ticket-perforated-fill fs-1"></i>
                                        </div>
                                    </div>
                                    <h4 class="mb-3 text-white">Escolha um Bolão</h4>
                                    <p class="mb-0 text-white">Selecione um bolão ativo para participar.</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-2 col-md-3 col-sm-6">
                            <div class="card h-100 border-0 shadow card-hover" style="border-radius: 15px;">
                                <div class="card-body text-center p-4">
                                    <div class="mb-4">
                                        <div class="bg-green text-white rounded-circle mx-auto d-flex align-items-center justify-content-center" 
                                             style="width: 80px; height: 80px;">
                                            <i class="bi bi-controller fs-1"></i>
                                        </div>
                                    </div>
                                    <h4 class="mb-3 text-white">Faça seus Palpites</h4>
                                    <p class="mb-0 text-white">Registre seus palpites para os jogos selecionados.</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-2 col-md-3 col-sm-6">
                            <div class="card h-100 border-0 shadow card-hover" style="border-radius: 15px;">
                                <div class="card-body text-center p-4">
                                    <div class="mb-4">
                                        <div class="bg-green text-white rounded-circle mx-auto d-flex align-items-center justify-content-center" 
                                             style="width: 80px; height: 80px;">
                                            <i class="bi bi-trophy-fill fs-1"></i>
                                        </div>
                                    </div>
                                    <h4 class="mb-3 text-white">Ganhe Prêmios</h4>
                                    <p class="mb-0 text-white">Acompanhe o ranking e ganhe prêmios em dinheiro.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="text-center mt-5">
                        <a href="<?= APP_URL ?>/como-funciona.php" class="btn btn-light btn-lg px-5" style="border-radius: 10px;">
                            <i class="bi bi-info-circle me-2"></i>Saiba Mais
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Available Boloes -->
<div style="margin-top: 5rem;">
    <h2 class="mb-4 text-center">Bolões Disponíveis</h2>
</div>
<div class="row g-4 mb-5">
    <?php if (count($boloes) > 0): ?>
        <?php foreach ($boloes as $bolao): ?>
            <div class="col-lg-6 col-xl-4">
                <div class="bolao-card h-100">
                    <div class="bolao-card-header" <?php if (!empty($bolao['imagem_bolao_url'])): ?>style="background-image: url('<?= APP_URL ?>/<?= $bolao['imagem_bolao_url'] ?>');"<?php endif; ?>>
                        <div class="bolao-header-overlay"></div>
                        <div class="bolao-prize-badge">
                            <i class="bi bi-trophy-fill"></i>
                            <span><?= formatMoney($bolao['premio_total']) ?></span>
                        </div>
                        <h5 class="bolao-title"><?= sanitize($bolao['nome']) ?></h5>
                    </div>
                    
                    <div class="bolao-card-body">
                        <div class="bolao-stats">
                            <div class="stat-item">
                                <div class="stat-icon">
                                    <?php if ($bolao['premio_rodada'] > 0): ?>
                                        <i class="bi bi-award-fill"></i>
                                    <?php else: ?>
                                        <i class="bi bi-gift-fill"></i>
                                    <?php endif; ?>
                                </div>
                                <div class="stat-content">
                                    <?php if ($bolao['premio_rodada'] > 0): ?>
                                        <span class="stat-value"><?= formatMoney($bolao['premio_rodada']) ?></span>
                                        <span class="stat-label">Prêmio por Rodada</span>
                                    <?php else: ?>
                                        <span class="stat-value">Surpresa...</span>
                                        <span class="stat-label">Prêmio por Rodada</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="stat-row">
                                <div class="stat-item-half">
                                    <div class="stat-icon">
                                        <i class="bi bi-ticket-fill"></i>
                                    </div>
                                    <div class="stat-content">
                                        <span class="stat-value"><?= formatMoney($bolao['valor_participacao']) ?></span>
                                        <span class="stat-label">Entrada</span>
                                    </div>
                                </div>
                                
                                <div class="stat-item-half">
                                    <div class="stat-icon">
                                        <i class="bi bi-calendar-event"></i>
                                    </div>
                                    <div class="stat-content">
                                        <span class="stat-value"><?= formatDate($bolao['data_fim']) ?></span>
                                        <span class="stat-label">Término</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bolao-card-footer">
                        <a href="<?= APP_URL ?>/bolao.php?id=<?= $bolao['id'] ?>" class="bolao-btn">
                            <i class="bi bi-play-fill"></i>
                            <span>Participar Agora</span>
                        </a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="col-12">
            <div class="alert alert-info text-center">
                <i class="bi bi-info-circle fs-3 mb-3 d-block"></i>
                <h5>Nenhum bolão disponível</h5>
                <p class="mb-0">Não há bolões disponíveis no momento. Volte mais tarde!</p>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Ver Todos os Bolões Button -->
<div class="text-center mb-5" style="margin-top: 3rem;">
    <a href="<?= APP_URL ?>/boloes.php" class="btn-ver-todos">
        <i class="bi bi-grid-fill"></i>
        <span>Ver Todos os Bolões</span>
        <i class="bi bi-arrow-right"></i>
    </a>
</div>

<!-- Recent Results -->
<?php if (count($resultados) > 0): ?>
    <div style="margin-top: 5rem;">
        <h2 class="mb-3">Resultados Recentes</h2>
    </div>
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



<?php
// Include footer
include TEMPLATE_DIR . '/footer.php';
?> 