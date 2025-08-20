<?php
/**
 * Componente do Slider Principal
 * 
 * @param array $slideBoloes - Array com os dados dos bolões para o slider
 * @param string $containerClass - Classe CSS adicional para o container (opcional)
 * @param array $swiperConfig - Configurações customizadas do Swiper (opcional)
 */

// Configurações padrão do Swiper
$defaultSwiperConfig = [
    'loop' => true,
    'autoplay' => [
        'delay' => 5000,
        'disableOnInteraction' => false
    ],
    'pagination' => [
        'el' => '.swiper-pagination',
        'clickable' => true
    ],
    'navigation' => [
        'nextEl' => '.swiper-button-next',
        'prevEl' => '.swiper-button-prev'
    ]
];

// Mesclar configurações customizadas se fornecidas
$swiperConfig = isset($swiperConfig) ? array_merge($defaultSwiperConfig, $swiperConfig) : $defaultSwiperConfig;
$containerClass = isset($containerClass) ? $containerClass : '';

// Verificar se há bolões para exibir
if (empty($slideBoloes)) {
    return;
}
?>

<!-- Preload de recursos críticos -->
<link rel="preload" href="https://cdn.jsdelivr.net/npm/swiper@8/swiper-bundle.min.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
<noscript><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@8/swiper-bundle.min.css"></noscript>

<!-- Preload do primeiro slide se houver bolões -->
<?php if (!empty($slideBoloes) && !empty($slideBoloes[0]['imagem_bolao_url'])): ?>
    <link rel="preload" href="<?= APP_URL ?>/<?= $slideBoloes[0]['imagem_bolao_url'] ?>" as="image">
<?php endif; ?>

<!-- Preload do JavaScript do Swiper -->
<link rel="preload" href="https://cdn.jsdelivr.net/npm/swiper@8/swiper-bundle.min.js" as="script">

<!-- Skip link para acessibilidade -->
<a href="#main-content" class="skip-link visually-hidden-focusable">
    Pular para o conteúdo principal
</a>

<!-- Slider Principal -->
<section class="slider-container <?= $containerClass ?>" 
         aria-label="Bolões em destaque" 
         role="banner">
    
    <!-- Título oculto para leitores de tela -->
    <h2 class="visually-hidden">Bolões em Destaque</h2>
    
    <!-- Instruções para usuários de leitor de tela -->
    <div class="visually-hidden" aria-live="polite" id="slider-instructions">
        Use as setas do teclado ou os botões de navegação para navegar pelos bolões em destaque.
        Pressione Enter ou Espaço para acessar um bolão.
    </div>
    
    <div class="swiper main-slider" 
         role="region" 
         aria-label="Carrossel de bolões em destaque"
         aria-describedby="slider-instructions"
         tabindex="0">
        
        <div class="swiper-wrapper" role="list">
            <?php foreach ($slideBoloes as $index => $bolao): ?>
                <div class="swiper-slide" 
                     role="listitem" 
                     aria-label="Slide <?= $index + 1 ?> de <?= count($slideBoloes) ?>: <?= htmlspecialchars($bolao['nome']) ?>">
                    
                    <article class="slide-article">
                        <a href="<?= APP_URL ?>/bolao.php?id=<?= $bolao['id'] ?>" 
                           class="slide-link" 
                           aria-describedby="slide-<?= $index ?>-description"
                           tabindex="0">
                            
                            <!-- Container da imagem -->
                            <div class="slide-image-container">
                                <?php 
                                $imageSrc = !empty($bolao['imagem_bolao_url']) 
                                    ? APP_URL . '/' . $bolao['imagem_bolao_url']
                                    : APP_URL . '/public/img/noimage.jpg';
                                $imageAlt = !empty($bolao['imagem_bolao_url']) 
                                    ? 'Imagem do bolão ' . htmlspecialchars($bolao['nome'])
                                    : 'Imagem não disponível para este bolão';
                                
                                // Otimizações de performance
                                $isFirstSlide = $index === 0;
                                $isSecondSlide = $index === 1;
                                $loadingStrategy = $isFirstSlide ? 'eager' : 'lazy';
                                $fetchPriority = $isFirstSlide ? 'high' : ($isSecondSlide ? 'auto' : 'low');
                                ?>
                                
                                <?php if ($isFirstSlide): ?>
                                    <!-- Primeira imagem: carregamento imediato -->
                                    <img src="<?= $imageSrc ?>" 
                                         alt="<?= $imageAlt ?>"
                                         class="slide-image"
                                         loading="eager"
                                         fetchpriority="high"
                                         decoding="sync"
                                         width="1200"
                                         height="600">
                                <?php else: ?>
                                    <!-- Demais imagens: lazy loading -->
                                    <img data-src="<?= $imageSrc ?>" 
                                         src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 1200 600'%3E%3Crect width='100%25' height='100%25' fill='%23f8f9fa'/%3E%3C/svg%3E"
                                         alt="<?= $imageAlt ?>"
                                         class="slide-image swiper-lazy"
                                         loading="lazy"
                                         fetchpriority="<?= $fetchPriority ?>"
                                         decoding="async"
                                         width="1200"
                                         height="600">
                                    <div class="swiper-lazy-preloader"></div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Overlay com informações -->
                            <div class="slide-overlay">
                                <div class="slide-content" id="slide-<?= $index ?>-description">
                                    <!-- Cabeçalho do bolão -->
                                    <header class="slide-header">
                                        <h3 class="slide-title visually-hidden">
                                            <?= htmlspecialchars($bolao['nome']) ?>
                                        </h3>
                                    </header>
                                    

                                    
                                    <!-- Descrição -->
                                    <?php if (!empty($bolao['descricao'])): ?>
                                        <p class="slide-description">
                                            <?= htmlspecialchars(substr($bolao['descricao'], 0, 150)) ?>...
                                        </p>
                                    <?php endif; ?>
                                    
                                    <!-- Botão de ação -->
                                    <div class="slide-action">
                                        <span class="btn btn-primary btn-lg" 
                                              role="button" 
                                              tabindex="-1"
                                              aria-label="Palpitar no bolão <?= htmlspecialchars($bolao['nome']) ?> - Participação: <?= formatMoney($bolao['valor_participacao']) ?> - Prêmio: <?= formatMoney($bolao['premio_total']) ?>">
                                            <div class="btn-content">
                                                <div class="btn-info">
                                                    <small class="btn-participation"><?= formatMoney($bolao['valor_participacao']) ?></small>
                                                    <small class="btn-prize">Prêmio: <?= formatMoney($bolao['premio_total']) ?></small>
                                                </div>
                                                <div class="btn-text">
                                                    <i class="bi bi-bullseye" aria-hidden="true"></i> 
                                                    Palpitar
                                                </div>
                                            </div>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </a>
                    </article>
                </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Controles do Swiper -->
        <div class="swiper-pagination" 
             role="tablist" 
             aria-label="Navegação do carrossel de bolões"></div>
        
        <button class="swiper-button-next" 
                aria-label="Próximo bolão" 
                type="button"
                title="Próximo bolão (Seta direita)">
            <span class="visually-hidden">Próximo</span>
        </button>
        
        <button class="swiper-button-prev" 
                aria-label="Bolão anterior" 
                type="button"
                title="Bolão anterior (Seta esquerda)">
            <span class="visually-hidden">Anterior</span>
        </button>
    </div>
    
    <!-- Status do autoplay para leitores de tela -->
    <div class="visually-hidden" aria-live="polite" id="autoplay-status">
        Reprodução automática ativa. Pressione Escape para pausar.
    </div>
</section>

<!-- JavaScript do Swiper -->
<script src="https://cdn.jsdelivr.net/npm/swiper@8/swiper-bundle.min.js"></script>
<script src="<?= APP_URL ?>/public/js/slider.js"></script>
<script>
(function() {
    'use strict';
    
    // Configuração do slider para este componente
    const sliderConfig = <?= json_encode($swiperConfig, JSON_PRETTY_PRINT) ?>;
    
    // Definir configuração global para o slider
    window.sliderConfig = sliderConfig;
    
    // Aguardar carregamento do DOM e inicializar
    document.addEventListener('DOMContentLoaded', function() {
        // Verificar se o SliderManager está disponível
        if (typeof window.SliderManager === 'undefined') {
            console.error('SliderManager não foi carregado corretamente');
            return;
        }
        
        try {
            // Inicializar o slider usando o manager otimizado
            window.mainSlider = new window.SliderManager('.main-slider', sliderConfig);
            
            // Eventos personalizados do slider
            document.addEventListener('slider:initialized', function(e) {
                console.log('Slider inicializado:', e.detail);
            });
            
            document.addEventListener('slider:slideChanged', function(e) {
                // Atualizar analytics ou outras métricas se necessário
                if (typeof gtag !== 'undefined') {
                    gtag('event', 'slide_change', {
                        'slide_index': e.detail.realIndex
                    });
                }
            });
            
            document.addEventListener('slider:error', function(e) {
                console.error('Erro no slider:', e.detail.error);
                // Reportar erro para serviço de monitoramento se configurado
            });
            
        } catch (error) {
            console.error('Erro ao inicializar o slider manager:', error);
        }
    });
    
    // Cleanup quando a página é descarregada
    window.addEventListener('beforeunload', function() {
        if (window.mainSlider && typeof window.mainSlider.destroy === 'function') {
            window.mainSlider.destroy();
        }
    });
})();
</script>

<style>
/* Estilos específicos do componente slider */
.slide-image-container {
    position: relative;
    width: 100%;
    height: 100%;
    overflow: hidden;
}

.slide-overlay {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    background: var(--slider-overlay-gradient);
    pointer-events: none;
    padding: 0;
}

.slide-content {
    padding: 2rem;
    color: white;
}

.slide-prizes {
    margin-bottom: 1rem;
}

.slide-prize-total,
.slide-prize-round,
.slide-participation {
    margin-bottom: 0.5rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.slide-prize-total {
    font-size: 1.5rem;
    font-weight: bold;
}

.slide-prize-total i {
    color: #ffc107;
}

.slide-prize-round i {
    color: #28a745;
}

.slide-participation i {
    color: #17a2b8;
}

.slide-description {
    margin-bottom: 1.5rem;
    line-height: 1.4;
}

.slide-action span {
    pointer-events: none;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

/* Responsividade */
@media (max-width: 767.98px) {
    .slide-content {
        padding: 1rem;
    }
    
    .slide-prize-total {
        font-size: var(--slider-mobile-font-h3);
    }
    
    .slide-prize-round,
    .slide-participation {
        font-size: var(--slider-mobile-font-h4);
    }
    
    .slide-description {
        font-size: var(--slider-mobile-font-p);
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }
    
    .slide-action span {
        font-size: var(--slider-mobile-font-btn);
        padding: var(--slider-mobile-btn-padding);
    }
}
</style>