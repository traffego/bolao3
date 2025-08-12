<?php
require_once 'config/config.php';require_once 'includes/functions.php';

// Page title
$pageTitle = 'Como Funciona';

// Include header
include TEMPLATE_DIR . '/header.php';
?>

<!-- Steps to Participate Section -->
<section class="how-to-participate-section" style="margin-top: 3rem; margin-left: calc(-50vw + 50%); margin-right: calc(-50vw + 50%); width: 100vw;">
    <div class="how-to-participate-bg">
        <div class="container">
            <!-- Section Header -->
            <div class="text-center mb-5">
                <h1 class="section-title text-white mb-3">Como Participar</h1>
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
        </div>
    </div>
</section>

<!-- Como Funciona a Pontuação -->
<div class="container" style="margin-top: 5rem; margin-bottom: 5rem;">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="card border-0 shadow-lg">
                <div class="card-header text-center py-4" style="background: linear-gradient(135deg, var(--globo-verde-principal), var(--globo-verde-claro)); color: white;">
                    <h2 class="mb-0">
                        <i class="bi bi-trophy-fill me-2"></i>
                        Como Funciona a Pontuação
                    </h2>
                </div>
                <div class="card-body p-5">
                    <div class="row g-4">
                        <!-- Sistema de Pontos -->
                        <div class="col-md-6">
                            <div class="text-center mb-4">
                                <div class="bg-success rounded-circle mx-auto d-flex align-items-center justify-content-center mb-3" 
                                     style="width: 80px; height: 80px;">
                                    <i class="bi bi-bullseye text-white fs-1"></i>
                                </div>
                                <h4 class="text-success mb-3">Sistema de Pontos</h4>
                                <div class="text-start">
                                    <p class="fs-5 mb-3">
                                        <i class="bi bi-arrow-right-circle-fill text-success me-2"></i>
                                        <strong>Cada jogo acertado = 1 ponto</strong>
                                    </p>
                                    <p class="text-muted">
                                        Sistema simples e justo: acertou o resultado do jogo, ganhou 1 ponto!
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Prêmio do Bolão -->
                        <div class="col-md-6">
                            <div class="text-center mb-4">
                                <div class="bg-warning rounded-circle mx-auto d-flex align-items-center justify-content-center mb-3" 
                                     style="width: 80px; height: 80px;">
                                    <i class="bi bi-trophy-fill text-white fs-1"></i>
                                </div>
                                <h4 class="text-warning mb-3">Prêmio do Bolão</h4>
                                <div class="text-start">
                                    <p class="fs-5 mb-3">
                                        <i class="bi bi-arrow-right-circle-fill text-warning me-2"></i>
                                        <strong>Maior pontuação ganha</strong>
                                    </p>
                                    <p class="text-muted">
                                        Quem acertar a maior quantidade de jogos leva o prêmio principal. 
                                        Se houver empate, o prêmio é dividido igualmente.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Prêmio da Rodada -->
                        <div class="col-12">
                            <div class="text-center">
                                <div class="bg-primary rounded-circle mx-auto d-flex align-items-center justify-content-center mb-3" 
                                     style="width: 80px; height: 80px;">
                                    <i class="bi bi-star-fill text-white fs-1"></i>
                                </div>
                                <h4 class="text-primary mb-3">Prêmio da Rodada</h4>
                                <div class="row justify-content-center">
                                    <div class="col-lg-8">
                                        <p class="fs-5 mb-3">
                                            <i class="bi bi-arrow-right-circle-fill text-primary me-2"></i>
                                            <strong>Acertou TODOS os jogos? Ganhou o prêmio da rodada!</strong>
                                        </p>
                                        <p class="text-muted">
                                            Para ganhar o prêmio da rodada, você precisa acertar 100% dos jogos. 
                                            Se mais pessoas conseguirem essa façanha, o prêmio é dividido entre elas.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Call to Action -->
                    <div class="text-center mt-5 pt-4 border-top">
                        <h5 class="mb-3">Pronto para testar seus conhecimentos?</h5>
                        <a href="<?= APP_URL ?>/boloes.php" class="btn btn-lg px-5 py-3" 
                           style="background: linear-gradient(135deg, var(--globo-verde-principal), var(--globo-verde-claro)); color: white; border-radius: 12px; font-weight: 600;">
                            <i class="bi bi-rocket-takeoff me-2"></i>
                            Participar Agora
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include TEMPLATE_DIR . '/footer.php'; ?> 