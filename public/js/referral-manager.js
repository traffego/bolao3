/**
 * Gerenciador Simples de Códigos de Afiliação
 * Mantém persistência do parâmetro ?ref= usando apenas localStorage
 */
(function() {
    'use strict';
    
    // Configurações
    const CONFIG = {
        STORAGE_KEY: 'bolao_referral_code',
        URL_PARAM: 'ref',
        DEBUG: true // Debug ativo para acompanhar funcionamento
    };

    const ReferralManager = {
        /**
         * Inicializa o sistema de afiliação
         */
        init: function() {
            this.log('Inicializando gerenciador simples de afiliação');
            
            // Capturar código da URL atual
            const urlCode = this.getCodeFromUrl();
            if (urlCode) {
                this.setReferralCode(urlCode);
                this.log('Código capturado da URL:', urlCode);
            }
            
            // Aplicar código existente aos links
            this.applyCodeToLinks();
            
            // Monitorar mudanças no DOM
            this.observeDOM();
            
            this.log('Gerenciador inicializado - Código atual:', this.getStoredCode());
        },

        /**
         * Captura código da URL atual
         */
        getCodeFromUrl: function() {
            const urlParams = new URLSearchParams(window.location.search);
            const code = urlParams.get(CONFIG.URL_PARAM);
            return code ? code.trim() : null;
        },

        /**
         * Obtém código armazenado
         */
        getStoredCode: function() {
            return localStorage.getItem(CONFIG.STORAGE_KEY);
        },

        /**
         * Define código de afiliação
         */
        setReferralCode: function(code) {
            if (code && code.trim() !== '') {
                localStorage.setItem(CONFIG.STORAGE_KEY, code.trim());
                this.log('Código salvo:', code.trim());
            }
        },

        /**
         * Aplica código a todos os links internos
         */
        applyCodeToLinks: function() {
            const code = this.getStoredCode();
            if (!code) return;

            const links = document.querySelectorAll('a[href]');
            
            links.forEach(link => {
                const href = link.getAttribute('href');
                
                // Verificar se é link interno
                if (this.isInternalLink(href)) {
                    const newHref = this.addCodeToUrl(href, code);
                    if (newHref !== href) {
                        link.setAttribute('href', newHref);
                    }
                }
            });
            
            this.log('Código aplicado aos links internos');
        },

        /**
         * Verifica se é link interno
         */
        isInternalLink: function(href) {
            if (!href) return false;
            
            return !href.startsWith('http') && 
                   !href.startsWith('//') && 
                   !href.startsWith('mailto:') && 
                   !href.startsWith('tel:') &&
                   !href.startsWith('#');
        },

        /**
         * Adiciona código à URL
         */
        addCodeToUrl: function(url, code) {
            if (!url || !code) return url;
            
            // Se já tem o parâmetro ref, não adicionar
            if (url.includes(CONFIG.URL_PARAM + '=')) {
                return url;
            }
            
            const separator = url.includes('?') ? '&' : '?';
            return url + separator + CONFIG.URL_PARAM + '=' + encodeURIComponent(code);
        },

        /**
         * Monitora mudanças no DOM para aplicar código a novos links
         */
        observeDOM: function() {
            const self = this;
            
            // Observer para novos elementos
            const observer = new MutationObserver(function(mutations) {
                let hasNewLinks = false;
                
                mutations.forEach(function(mutation) {
                    if (mutation.type === 'childList') {
                        mutation.addedNodes.forEach(function(node) {
                            if (node.nodeType === 1) { // Element node
                                if (node.tagName === 'A' || node.querySelector('a')) {
                                    hasNewLinks = true;
                                }
                            }
                        });
                    }
                });
                
                if (hasNewLinks) {
                    self.applyCodeToLinks();
                }
            });
            
            observer.observe(document.body, {
                childList: true,
                subtree: true
            });
        },

        /**
         * Remove código de afiliação
         */
        clearReferralCode: function() {
            localStorage.removeItem(CONFIG.STORAGE_KEY);
            this.log('Código removido');
        },

        /**
         * Log de debug
         */
        log: function() {
            if (CONFIG.DEBUG && console && console.log) {
                console.log('[ReferralManager]', ...arguments);
            }
        },
        
        /**
         * Obtém código para formulários (usado no cadastro)
         */
        getCodeForForm: function() {
            return this.getStoredCode();
        }
    };

    // Inicializar automaticamente
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            ReferralManager.init();
        });
    } else {
        ReferralManager.init();
    }
    
    // Expor globalmente
    window.ReferralManager = ReferralManager;
    
})();