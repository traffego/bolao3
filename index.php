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
                                        <div class="text-start">
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
                                            <h4 class="text-white mb-3">
                                                <i class="bi bi-ticket-fill text-info"></i> 
                                                Participação: <?= formatMoney($bolao['valor_participacao']) ?>
                                            </h4>
                                        </div>
                                    </div>
                                    <?php if (!empty($bolao['descricao'])): ?>
                                        <p class="text-white mb-3 text-start"><?= htmlspecialchars(substr($bolao['descricao'], 0, 150)) ?>...</p>
                                    <?php endif; ?>
                                    <div class="text-start">
                                        <div class="btn btn-primary btn-lg" style="pointer-events: none;">
                                            <i class="bi bi-play-fill"></i> Participar Agora
                                        </div>
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



<!-- Steps to Participate Section -->
<section class="how-to-participate-section" style="margin-top: 5rem; margin-left: calc(-50vw + 50%); margin-right: calc(-50vw + 50%); width: 100vw;">
    <div class="how-to-participate-bg">
        <div class="container">
            <!-- Section Header -->
            <div class="text-center mb-5">
                <h2 class="section-title text-white mb-3">Como Participar</h2>
                <p class="section-subtitle text-white-50 fs-5">Siga estes 4 passos simples para começar a ganhar</p>
            </div>

            <!-- Steps Grid -->
            <div class="row g-4 justify-content-center">
                <!-- Step 1: Cadastre-se -->
                <div class="col-lg-3 col-md-6">
                    <div class="step-card">
                        <div class="step-number">1</div>
                        <div class="step-icon-container">
                            <div class="step-icon">
                                <i class="bi bi-person-plus-fill"></i>
                            </div>
                        </div>
                        <div class="step-content">
                            <h4 class="step-title">Cadastre-se</h4>
                            <p class="step-description">Crie sua conta gratuitamente em segundos. Apenas dados básicos necessários.</p>
                            <div class="step-features">
                                <small><i class="bi bi-check-circle-fill me-1"></i>100% Gratuito</small>
                                <small><i class="bi bi-check-circle-fill me-1"></i>Rápido e Seguro</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Step 2: Escolha um Bolão -->
                <div class="col-lg-3 col-md-6">
                    <div class="step-card">
                        <div class="step-number">2</div>
                        <div class="step-icon-container">
                            <div class="step-icon">
                                <i class="bi bi-grid-3x3-gap-fill"></i>
                            </div>
                        </div>
                        <div class="step-content">
                            <h4 class="step-title">Escolha um Bolão</h4>
                            <p class="step-description">Navegue pelos bolões disponíveis e escolha o que mais combina com você.</p>
                            <div class="step-features">
                                <small><i class="bi bi-check-circle-fill me-1"></i>Vários Campeonatos</small>
                                <small><i class="bi bi-check-circle-fill me-1"></i>Prêmios Atrativos</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Step 3: Faça seus Palpites -->
                <div class="col-lg-3 col-md-6">
                    <div class="step-card">
                        <div class="step-number">3</div>
                        <div class="step-icon-container">
                            <div class="step-icon">
                                <i class="bi bi-bullseye"></i>
                            </div>
                        </div>
                        <div class="step-content">
                            <h4 class="step-title">Faça seus Palpites</h4>
                            <p class="step-description">Registre seus palpites para os jogos. Use sua expertise futebolística!</p>
                            <div class="step-features">
                                <small><i class="bi bi-check-circle-fill me-1"></i>Interface Intuitiva</small>
                                <small><i class="bi bi-check-circle-fill me-1"></i>Palpites Flexíveis</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Step 4: Ganhe Prêmios -->
                <div class="col-lg-3 col-md-6">
                    <div class="step-card">
                        <div class="step-number">4</div>
                        <div class="step-icon-container">
                            <div class="step-icon">
                                <i class="bi bi-trophy-fill"></i>
                            </div>
                        </div>
                        <div class="step-content">
                            <h4 class="step-title">Ganhe Prêmios</h4>
                            <p class="step-description">Acompanhe o ranking em tempo real e ganhe prêmios em dinheiro!</p>
                            <div class="step-features">
                                <small><i class="bi bi-check-circle-fill me-1"></i>Prêmios em Dinheiro</small>
                                <small><i class="bi bi-check-circle-fill me-1"></i>Saque Rápido</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Call to Action -->
            <div class="text-center mt-5">
                <div class="cta-container">
                    <h3 class="text-white mb-3">Pronto para começar a ganhar?</h3>
                    <div class="d-flex justify-content-center">
                        <a href="<?= APP_URL ?>/boloes.php" class="btn btn-light btn-lg px-4 py-3 cta-btn-primary">
                            <i class="bi bi-rocket-takeoff me-2"></i>Começar Agora
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Available Boloes -->
<div class="container" style="margin-top: 5rem;">
    <h2 class="mb-4 text-center">Bolões Disponíveis</h2>
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