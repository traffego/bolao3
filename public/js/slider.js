/**
 * Slider Principal - JavaScript Otimizado
 * 
 * Gerencia a inicialização e comportamento do slider principal
 * com tratamento de erros, performance otimizada e acessibilidade
 */

(function(window, document) {
    'use strict';
    
    // Configuração padrão do slider
    const DEFAULT_CONFIG = {
        loop: true,
        centeredSlides: true,
        slidesPerView: 1,
        spaceBetween: 0,
        autoplay: {
            delay: 5000,
            disableOnInteraction: false,
            pauseOnMouseEnter: true,
            waitForTransition: true
        },
        pagination: {
            el: '.swiper-pagination',
            clickable: true,
            dynamicBullets: false,
            bulletActiveClass: 'swiper-pagination-bullet-active',
            renderBullet: function (index, className) {
                return `<span class="${className}" 
                              role="tab" 
                              aria-label="Ir para bolão ${index + 1}"
                              aria-selected="false"
                              tabindex="-1"></span>`;
            }
        },
        navigation: {
            nextEl: '.swiper-button-next',
            prevEl: '.swiper-button-prev'
        },
        keyboard: {
            enabled: true,
            onlyInViewport: true,
            pageUpDown: true
        },
        a11y: {
            enabled: true,
            prevSlideMessage: 'Bolão anterior',
            nextSlideMessage: 'Próximo bolão',
            firstSlideMessage: 'Este é o primeiro bolão',
            lastSlideMessage: 'Este é o último bolão',
            paginationBulletMessage: 'Ir para bolão {{index}}',
            notificationClass: 'swiper-notification'
        },
        effect: 'slide',
        speed: 600,
        grabCursor: true,
        watchSlidesProgress: true,
        preloadImages: false,
        lazy: {
            enabled: true,
            loadPrevNext: true,
            loadPrevNextAmount: 2,
            loadOnTransitionStart: true,
            elementClass: 'swiper-lazy',
            loadingClass: 'swiper-lazy-loading',
            loadedClass: 'swiper-lazy-loaded',
            preloaderClass: 'swiper-lazy-preloader'
        },
        mousewheel: {
            enabled: false
        },
        touchRatio: 1,
        touchAngle: 45,
        simulateTouch: true,
        resistance: true,
        resistanceRatio: 0.85
    };
    
    // Classe principal do Slider
    class SliderManager {
        constructor(selector = '.main-slider', config = {}) {
            this.selector = selector;
            this.config = this.mergeConfig(DEFAULT_CONFIG, config);
            this.swiper = null;
            this.container = null;
            this.isInitialized = false;
            this.observers = [];
            
            this.init();
        }
        
        /**
         * Mescla configurações personalizadas com as padrão
         */
        mergeConfig(defaultConfig, customConfig) {
            const merged = { ...defaultConfig };
            
            for (const key in customConfig) {
                if (typeof customConfig[key] === 'object' && !Array.isArray(customConfig[key])) {
                    merged[key] = { ...defaultConfig[key], ...customConfig[key] };
                } else {
                    merged[key] = customConfig[key];
                }
            }
            
            return merged;
        }
        
        /**
         * Inicializa o slider
         */
        init() {
            // Aguardar carregamento do DOM
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', () => this.setup());
            } else {
                this.setup();
            }
        }
        
        /**
         * Configura o slider após o DOM estar pronto
         */
        setup() {
            try {
                // Verificar se o Swiper está disponível
                if (typeof Swiper === 'undefined') {
                    throw new Error('Swiper library não foi carregada');
                }
                
                // Encontrar o container do slider
                this.container = document.querySelector(this.selector);
                if (!this.container) {
                    console.warn(`Slider container '${this.selector}' não encontrado`);
                    return;
                }
                
                // Verificar se há slides
                const slides = this.container.querySelectorAll('.swiper-slide');
                if (slides.length === 0) {
                    console.warn('Nenhum slide encontrado no container');
                    return;
                }
                
                // Inicializar o Swiper
                this.initSwiper();
                
                // Configurar eventos
                this.setupEvents();
                
                // Configurar observadores
            this.setupObservers();
            
            // Configura navegação por teclado aprimorada
            this.setupKeyboardNavigation();
            
            // Configura eventos de acessibilidade
            this.setupAccessibilityEvents();
            
            // Configura preload inteligente de imagens
            this.setupIntelligentPreload();
            
            // Otimiza baseado na conexão
            this.optimizeForConnection();
            
            this.isInitialized = true;
                
                // Disparar evento personalizado
                this.dispatchEvent('sliderInitialized', {
                    swiper: this.swiper,
                    slidesCount: slides.length
                });
                
                console.log('Slider inicializado com sucesso');
                
            } catch (error) {
                console.error('Erro ao inicializar o slider:', error);
                this.handleError(error);
            }
        }
        
        /**
         * Inicializa o Swiper
         */
        initSwiper() {
            this.swiper = new Swiper(this.selector, this.config);
            
            // Configurar eventos do Swiper
            this.swiper.on('slideChange', () => {
                this.dispatchEvent('slideChanged', {
                    activeIndex: this.swiper.activeIndex,
                    realIndex: this.swiper.realIndex
                });
            });
            
            this.swiper.on('autoplayStart', () => {
                this.dispatchEvent('autoplayStarted');
            });
            
            this.swiper.on('autoplayStop', () => {
                this.dispatchEvent('autoplayStopped');
            });
        }
        
        /**
         * Configura eventos do DOM
         */
        setupEvents() {
            // Prevenir que os controles interfiram com o clique do slide
            const controls = this.container.querySelectorAll(
                '.swiper-button-next, .swiper-button-prev, .swiper-pagination'
            );
            
            controls.forEach(control => {
                control.addEventListener('click', (e) => {
                    e.stopPropagation();
                });
            });
            
            // Pausar/retomar autoplay no hover (se habilitado)
            if (this.config.autoplay && this.config.autoplay.pauseOnMouseEnter) {
                this.container.addEventListener('mouseenter', () => {
                    if (this.swiper && this.swiper.autoplay) {
                        this.swiper.autoplay.stop();
                    }
                });
                
                this.container.addEventListener('mouseleave', () => {
                    if (this.swiper && this.swiper.autoplay) {
                        this.swiper.autoplay.start();
                    }
                });
            }
            
            // Pausar autoplay quando a aba não está visível
            document.addEventListener('visibilitychange', () => {
                if (!this.swiper || !this.swiper.autoplay) return;
                
                if (document.hidden) {
                    this.swiper.autoplay.stop();
                } else {
                    this.swiper.autoplay.start();
                }
            });
            
            // Configura navegação por teclado aprimorada
            this.setupKeyboardNavigation();
            
            // Configura eventos de acessibilidade
            this.setupAccessibilityEvents();
        }
        
        /**
         * Configura observadores (Intersection Observer, etc.)
         */
        setupObservers() {
            // Intersection Observer para pausar autoplay quando fora da viewport
            if ('IntersectionObserver' in window) {
                const observer = new IntersectionObserver((entries) => {
                    entries.forEach(entry => {
                        if (!this.swiper || !this.swiper.autoplay) return;
                        
                        if (entry.isIntersecting) {
                            this.swiper.autoplay.start();
                        } else {
                            this.swiper.autoplay.stop();
                        }
                    });
                }, {
                    threshold: 0.5
                });
                
                observer.observe(this.container);
                this.observers.push(observer);
            }
        }
        
        /**
         * Dispara evento personalizado
         */
        dispatchEvent(eventName, detail = {}) {
            const event = new CustomEvent(`slider:${eventName}`, {
                detail: { slider: this, ...detail },
                bubbles: true
            });
            
            this.container.dispatchEvent(event);
        }
        
        /**
         * Trata erros do slider
         */
        handleError(error) {
            // Log do erro
            console.error('Slider Error:', error);
            
            // Tentar fallback gracioso
            this.setupFallback();
            
            // Disparar evento de erro
            this.dispatchEvent('error', { error });
        }
        
        /**
         * Configura fallback quando o Swiper falha
         */
        setupFallback() {
            if (!this.container) return;
            
            // Mostrar apenas o primeiro slide
            const slides = this.container.querySelectorAll('.swiper-slide');
            slides.forEach((slide, index) => {
                if (index > 0) {
                    slide.style.display = 'none';
                }
            });
            
            // Ocultar controles
            const controls = this.container.querySelectorAll(
                '.swiper-button-next, .swiper-button-prev, .swiper-pagination'
            );
            controls.forEach(control => {
                control.style.display = 'none';
            });
            
            console.warn('Slider em modo fallback - apenas primeiro slide visível');
        }
        
        /**
         * Configura navegação por teclado aprimorada
         */
        setupKeyboardNavigation() {
            const container = this.container;
            if (!container) return;
            
            container.addEventListener('keydown', (e) => {
                // Verifica se o slider está focado
                if (!container.contains(document.activeElement)) return;
                
                switch (e.key) {
                    case 'ArrowLeft':
                    case 'ArrowUp':
                        e.preventDefault();
                        this.swiper?.slidePrev();
                        this.announceSlideChange('anterior');
                        break;
                        
                    case 'ArrowRight':
                    case 'ArrowDown':
                        e.preventDefault();
                        this.swiper?.slideNext();
                        this.announceSlideChange('próximo');
                        break;
                        
                    case 'Home':
                        e.preventDefault();
                        this.swiper?.slideTo(0);
                        this.announceSlideChange('primeiro');
                        break;
                        
                    case 'End':
                        e.preventDefault();
                        const lastIndex = this.swiper?.slides?.length - 1 || 0;
                        this.swiper?.slideTo(lastIndex);
                        this.announceSlideChange('último');
                        break;
                        
                    case 'Enter':
                    case ' ': // Espaço
                        e.preventDefault();
                        this.activateCurrentSlide();
                        break;
                        
                    case 'Escape':
                        e.preventDefault();
                        this.toggleAutoplay();
                        break;
                }
            });
            
            // Configura foco nos bullets da paginação
            this.setupPaginationKeyboard();
        }
        
        /**
         * Configura navegação por teclado na paginação
         */
        setupPaginationKeyboard() {
            const pagination = this.container?.querySelector('.swiper-pagination');
            if (!pagination) return;
            
            pagination.addEventListener('keydown', (e) => {
                const bullets = pagination.querySelectorAll('.swiper-pagination-bullet');
                const currentIndex = Array.from(bullets).findIndex(bullet => 
                    bullet.classList.contains('swiper-pagination-bullet-active'));
                
                let newIndex = currentIndex;
                
                switch (e.key) {
                    case 'ArrowLeft':
                    case 'ArrowUp':
                        e.preventDefault();
                        newIndex = currentIndex > 0 ? currentIndex - 1 : bullets.length - 1;
                        break;
                        
                    case 'ArrowRight':
                    case 'ArrowDown':
                        e.preventDefault();
                        newIndex = currentIndex < bullets.length - 1 ? currentIndex + 1 : 0;
                        break;
                        
                    case 'Home':
                        e.preventDefault();
                        newIndex = 0;
                        break;
                        
                    case 'End':
                        e.preventDefault();
                        newIndex = bullets.length - 1;
                        break;
                        
                    case 'Enter':
                    case ' ':
                        e.preventDefault();
                        bullets[currentIndex]?.click();
                        return;
                }
                
                if (newIndex !== currentIndex && bullets[newIndex]) {
                    bullets[newIndex].focus();
                    bullets[newIndex].click();
                }
            });
        }
        
        /**
         * Configura eventos de acessibilidade
         */
        setupAccessibilityEvents() {
            if (!this.swiper) return;
            
            // Atualiza ARIA attributes quando o slide muda
            this.swiper.on('slideChange', () => {
                this.updateAriaAttributes();
                this.updateAutoplayStatus();
            });
            
            // Atualiza quando o autoplay é pausado/retomado
            this.swiper.on('autoplayStart', () => {
                this.updateAutoplayStatus(true);
            });
            
            this.swiper.on('autoplayStop', () => {
                this.updateAutoplayStatus(false);
            });
        }
        
        /**
         * Atualiza atributos ARIA
         */
        updateAriaAttributes() {
            if (!this.swiper) return;
            
            const activeIndex = this.swiper.realIndex;
            const slides = this.container?.querySelectorAll('.swiper-slide');
            const bullets = this.container?.querySelectorAll('.swiper-pagination-bullet');
            
            // Atualiza slides
            slides?.forEach((slide, index) => {
                const isActive = index === activeIndex;
                const link = slide.querySelector('.slide-link');
                
                if (link) {
                    link.setAttribute('tabindex', isActive ? '0' : '-1');
                    link.setAttribute('aria-hidden', isActive ? 'false' : 'true');
                }
            });
            
            // Atualiza bullets da paginação
            bullets?.forEach((bullet, index) => {
                const isActive = index === activeIndex;
                bullet.setAttribute('aria-selected', isActive ? 'true' : 'false');
                bullet.setAttribute('tabindex', isActive ? '0' : '-1');
            });
        }
        
        /**
         * Ativa o slide atual (simula clique no link)
         */
        activateCurrentSlide() {
            const activeSlide = this.container?.querySelector('.swiper-slide-active');
            const link = activeSlide?.querySelector('.slide-link');
            
            if (link) {
                // Dispara evento de clique
                link.click();
            }
        }
        
        /**
         * Alterna autoplay
         */
        toggleAutoplay() {
            if (!this.swiper?.autoplay) return;
            
            if (this.swiper.autoplay.running) {
                this.swiper.autoplay.stop();
                this.announceToScreenReader('Reprodução automática pausada');
            } else {
                this.swiper.autoplay.start();
                this.announceToScreenReader('Reprodução automática retomada');
            }
        }
        
        /**
         * Anuncia mudança de slide para leitores de tela
         */
        announceSlideChange(direction) {
            const activeIndex = this.swiper?.realIndex + 1 || 1;
            const totalSlides = this.swiper?.slides?.length || 0;
            const message = `Navegando para o ${direction} bolão. Slide ${activeIndex} de ${totalSlides}.`;
            
            this.announceToScreenReader(message);
        }
        
        /**
         * Atualiza status do autoplay para leitores de tela
         */
        updateAutoplayStatus(isRunning = null) {
            const statusElement = document.getElementById('autoplay-status');
            if (!statusElement) return;
            
            const running = isRunning !== null ? isRunning : this.swiper?.autoplay?.running;
            const message = running 
                ? 'Reprodução automática ativa. Pressione Escape para pausar.'
                : 'Reprodução automática pausada. Pressione Escape para retomar.';
                
            statusElement.textContent = message;
        }
        
        /**
     * Anuncia mensagem para leitores de tela
     */
    announceToScreenReader(message) {
        // Cria elemento temporário para anúncio
        const announcement = document.createElement('div');
        announcement.setAttribute('aria-live', 'polite');
        announcement.setAttribute('aria-atomic', 'true');
        announcement.className = 'visually-hidden';
        announcement.textContent = message;
        
        document.body.appendChild(announcement);
        
        // Remove após um tempo
        setTimeout(() => {
            document.body.removeChild(announcement);
        }, 1000);
    }
    
    /**
     * Configura preload inteligente de imagens
     */
    setupIntelligentPreload() {
        if (!this.swiper) return;
        
        // Preload das próximas 2 imagens quando o slide muda
        this.swiper.on('slideChange', () => {
            this.preloadNextImages();
        });
        
        // Preload inicial das primeiras imagens
        setTimeout(() => {
            this.preloadNextImages();
        }, 100);
    }
    
    /**
     * Preload das próximas imagens
     */
    preloadNextImages() {
        if (!this.swiper) return;
        
        const currentIndex = this.swiper.realIndex;
        const totalSlides = this.swiper.slides.length;
        
        // Preload das próximas 2 imagens
        for (let i = 1; i <= 2; i++) {
            const nextIndex = (currentIndex + i) % totalSlides;
            const nextSlide = this.swiper.slides[nextIndex];
            
            if (nextSlide) {
                const lazyImg = nextSlide.querySelector('.swiper-lazy[data-src]');
                if (lazyImg && !lazyImg.classList.contains('swiper-lazy-loaded')) {
                    this.preloadImage(lazyImg);
                }
            }
        }
    }
    
    /**
     * Preload de uma imagem específica
     */
    preloadImage(imgElement) {
        if (!imgElement || !imgElement.dataset.src) return;
        
        // Verifica se a imagem já está sendo carregada
        if (imgElement.classList.contains('swiper-lazy-loading')) return;
        
        // Marca como carregando
        imgElement.classList.add('swiper-lazy-loading');
        
        // Cria nova imagem para preload
        const img = new Image();
        
        img.onload = () => {
            // Substitui o src e marca como carregada
            imgElement.src = imgElement.dataset.src;
            imgElement.classList.remove('swiper-lazy-loading');
            imgElement.classList.add('swiper-lazy-loaded');
            
            // Remove o preloader
            const preloader = imgElement.parentNode.querySelector('.swiper-lazy-preloader');
            if (preloader) {
                preloader.remove();
            }
            
            console.log('🖼️ Imagem precarregada:', imgElement.dataset.src);
        };
        
        img.onerror = () => {
            imgElement.classList.remove('swiper-lazy-loading');
            console.warn('❌ Erro ao precarregar imagem:', imgElement.dataset.src);
        };
        
        // Inicia o carregamento
        img.src = imgElement.dataset.src;
    }
    
    /**
     * Otimiza performance baseada na conexão
     */
    optimizeForConnection() {
        // Verifica se a API Network Information está disponível
        if ('connection' in navigator) {
            const connection = navigator.connection;
            
            // Ajusta configurações baseado na velocidade da conexão
            if (connection.effectiveType === 'slow-2g' || connection.effectiveType === '2g') {
                // Conexão lenta: reduz autoplay e preload
                if (this.swiper?.autoplay) {
                    this.swiper.autoplay.delay = 8000; // Aumenta delay
                }
                
                // Reduz quantidade de preload
                this.config.lazy.loadPrevNextAmount = 1;
                
                console.log('🐌 Conexão lenta detectada - otimizações aplicadas');
            } else if (connection.effectiveType === '4g') {
                // Conexão rápida: aumenta preload
                this.config.lazy.loadPrevNextAmount = 3;
                
                console.log('🚀 Conexão rápida detectada - preload aumentado');
            }
        }
    }
        
        /**
         * Destrói o slider e limpa recursos
         */
        destroy() {
            try {
                // Limpar observadores
                this.observers.forEach(observer => {
                    if (observer.disconnect) {
                        observer.disconnect();
                    }
                });
                this.observers = [];
                
                // Destruir Swiper
                if (this.swiper && typeof this.swiper.destroy === 'function') {
                    this.swiper.destroy(true, true);
                }
                
                this.swiper = null;
                this.container = null;
                this.isInitialized = false;
                
                console.log('Slider destruído com sucesso');
                
            } catch (error) {
                console.error('Erro ao destruir slider:', error);
            }
        }
        
        /**
         * Métodos públicos para controle do slider
         */
        next() {
            if (this.swiper) this.swiper.slideNext();
        }
        
        prev() {
            if (this.swiper) this.swiper.slidePrev();
        }
        
        goTo(index) {
            if (this.swiper) this.swiper.slideTo(index);
        }
        
        startAutoplay() {
            if (this.swiper && this.swiper.autoplay) {
                this.swiper.autoplay.start();
            }
        }
        
        stopAutoplay() {
            if (this.swiper && this.swiper.autoplay) {
                this.swiper.autoplay.stop();
            }
        }
        
        /**
         * Getters
         */
        get isReady() {
            return this.isInitialized && this.swiper !== null;
        }
        
        get currentSlide() {
            return this.swiper ? this.swiper.activeIndex : 0;
        }
        
        get totalSlides() {
            return this.swiper ? this.swiper.slides.length : 0;
        }
    }
    
    // Função de inicialização global
    window.initSlider = function(selector, config) {
        return new SliderManager(selector, config);
    };
    
    // Auto-inicialização se houver configuração global
    if (window.sliderConfig) {
        window.mainSlider = new SliderManager('.main-slider', window.sliderConfig);
    }
    
    // Exportar classe para uso avançado
    window.SliderManager = SliderManager;
    
})(window, document);