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
$slideBoloes = dbFetchAll("SELECT id, nome, imagem_bolao_url, descricao, premio_total, valor_participacao 
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
<!-- Slider Section with Ambilight Effect -->
<div class="ambilight-container mb-4">
    <div class="swiper main-slider" style="border-radius: 10px; overflow: hidden; position: relative; z-index: 1;">
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
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <div>
                                            <h3 class="text-white mb-2">
                                                <i class="bi bi-trophy-fill text-warning"></i> 
                                                Prêmio: <?= formatMoney($bolao['premio_total']) ?>
                                            </h3>
                                            <h4 class="text-white">
                                                <i class="bi bi-ticket-fill text-info"></i> 
                                                Participação: <?= formatMoney($bolao['valor_participacao']) ?>
                                            </h4>
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
    <!-- Ambilight Background -->
    <div class="ambilight-bg"></div>
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
        on: {
            slideChange: function () {
                updateAmbilight();
            },
            init: function() {
                updateAmbilight();
            }
        }
    });

    // Função para atualizar o efeito ambilight
    function updateAmbilight() {
        const activeSlide = document.querySelector('.swiper-slide-active');
        const activeImage = activeSlide ? activeSlide.querySelector('.slide-image') : null;
        const ambilightBg = document.querySelector('.ambilight-bg');
        
        if (activeImage && ambilightBg) {
            const imageSrc = activeImage.src;
            ambilightBg.style.backgroundImage = `url(${imageSrc})`;
        }
    }

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
<h2 class="mb-4 text-center">Bolões Disponíveis</h2>
<div class="row g-4 mb-5">
    <?php if (count($boloes) > 0): ?>
        <?php foreach ($boloes as $bolao): ?>
            <div class="col-md-6">
                <div class="card h-100 shadow-sm border-0 card-hover" style="border-radius: 15px; overflow: hidden;">
                    <div class="card-header text-white py-3 bg-green">
                        <h5 class="mb-0 fw-bold"><?= sanitize($bolao['nome']) ?></h5>
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div class="d-flex align-items-center">
                                <i class="bi bi-calendar-event text-green me-2"></i>
                                <span>Término: <?= formatDate($bolao['data_fim']) ?></span>
                            </div>
                            <div class="d-flex align-items-center">
                                <i class="bi bi-people-fill text-green me-2"></i>
                                <span><?= $bolao['total_jogadores'] ?> jogadores</span>
                            </div>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <div class="d-flex align-items-center">
                                <i class="bi bi-ticket-fill text-green me-2"></i>
                                <span>Entrada: <?= formatMoney($bolao['valor_participacao']) ?></span>
                            </div>
                            <div class="d-flex align-items-center">
                                <i class="bi bi-trophy-fill text-green me-2"></i>
                                <span>Prêmio: <?= formatMoney($bolao['premio_total']) ?></span>
                            </div>
                        </div>
                        <div class="text-center">
                            <a href="<?= APP_URL ?>/bolao.php?id=<?= $bolao['id'] ?>" class="btn btn-green btn-lg px-4">
                                <i class="bi bi-play-fill me-2"></i>Ver Detalhes
                            </a>
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
<div class="container">
    <div class="row">
        <div class="col-12 px-0">
            <div class="py-5 mb-5 w-100 bg-green" style="margin-left: calc(-50vw + 50%); margin-right: calc(-50vw + 50%); width: 100vw !important;">
                <div class="container">
                    <h2 class="text-center text-white mb-5">Como Participar</h2>
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
                                    <h4 class="mb-3">Cadastre-se</h4>
                                    <p class="text-muted mb-0">Crie sua conta gratuitamente em nosso sistema.</p>
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
                                    <h4 class="mb-3">Escolha um Bolão</h4>
                                    <p class="text-muted mb-0">Selecione um bolão ativo para participar.</p>
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
                                    <h4 class="mb-3">Faça seus Palpites</h4>
                                    <p class="text-muted mb-0">Registre seus palpites para os jogos selecionados.</p>
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
                                    <h4 class="mb-3">Ganhe Prêmios</h4>
                                    <p class="text-muted mb-0">Acompanhe o ranking e ganhe prêmios em dinheiro.</p>
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

<?php
// Include footer
include TEMPLATE_DIR . '/footer.php';
?> 