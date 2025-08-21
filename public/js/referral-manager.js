/**
 * Gerenciador de Códigos de Afiliação
 * Garante persistência do parâmetro ?ref= em toda a navegação
 */
class ReferralManager {
    constructor() {
        this.storageKey = 'bolao_referral_code';
        this.init();
    }

    init() {
        // Capturar parâmetro ?ref= da URL atual
        this.captureFromURL();
        
        // Sincronizar com localStorage
        this.syncWithStorage();
        
        // Adicionar código a todos os links internos
        this.enhanceInternalLinks();
        
        // Interceptar navegação via JavaScript
        this.interceptNavigation();
    }

    /**
     * Captura parâmetro ?ref= da URL atual
     */
    captureFromURL() {
        const urlParams = new URLSearchParams(window.location.search);
        const refCode = urlParams.get('ref');
        
        if (refCode && refCode.trim() !== '') {
            const cleanCode = refCode.trim();
            console.log('🔗 Código de afiliação capturado da URL:', cleanCode);
            
            // Salvar no localStorage
            localStorage.setItem(this.storageKey, cleanCode);
            
            // Enviar para o servidor via AJAX para sincronizar com a sessão
            this.syncWithServer(cleanCode);
            
            // Limpar URL (opcional - remove o parâmetro ?ref= da barra de endereços)
            this.cleanURL();
        }
    }

    /**
     * Sincroniza código com localStorage
     */
    syncWithStorage() {
        const storedCode = localStorage.getItem(this.storageKey);
        if (storedCode) {
            console.log('💾 Código de afiliação recuperado do localStorage:', storedCode);
            // Enviar para o servidor para manter sessão sincronizada
            this.syncWithServer(storedCode);
        }
    }

    /**
     * Envia código para o servidor via AJAX
     */
    syncWithServer(code) {
        fetch('/bolao3/ajax/sync-referral.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({ referral_code: code })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                console.log('✅ Código sincronizado com o servidor');
            } else {
                console.warn('⚠️ Erro ao sincronizar código:', data.message);
            }
        })
        .catch(error => {
            console.error('❌ Erro na sincronização:', error);
        });
    }

    /**
     * Adiciona código de afiliação a todos os links internos
     */
    enhanceInternalLinks() {
        const referralCode = this.getReferralCode();
        if (!referralCode) return;

        // Selecionar todos os links internos
        const internalLinks = document.querySelectorAll('a[href]');
        
        internalLinks.forEach(link => {
            const href = link.getAttribute('href');
            
            // Verificar se é link interno (não começa com http/https ou //)
            if (href && !href.startsWith('http') && !href.startsWith('//') && !href.startsWith('mailto:') && !href.startsWith('tel:')) {
                // Verificar se já tem parâmetros
                const separator = href.includes('?') ? '&' : '?';
                
                // Verificar se já tem parâmetro ref
                if (!href.includes('ref=')) {
                    link.setAttribute('href', href + separator + 'ref=' + encodeURIComponent(referralCode));
                }
            }
        });
    }

    /**
     * Intercepta navegação via JavaScript (para SPAs ou AJAX)
     */
    interceptNavigation() {
        // Interceptar pushState e replaceState
        const originalPushState = history.pushState;
        const originalReplaceState = history.replaceState;
        
        const self = this;
        
        history.pushState = function(state, title, url) {
            self.addReferralToURL(url);
            return originalPushState.apply(history, arguments);
        };
        
        history.replaceState = function(state, title, url) {
            self.addReferralToURL(url);
            return originalReplaceState.apply(history, arguments);
        };
    }

    /**
     * Adiciona código de afiliação a uma URL
     */
    addReferralToURL(url) {
        const referralCode = this.getReferralCode();
        if (!referralCode || !url) return url;
        
        try {
            const urlObj = new URL(url, window.location.origin);
            if (!urlObj.searchParams.has('ref')) {
                urlObj.searchParams.set('ref', referralCode);
            }
            return urlObj.toString();
        } catch (e) {
            // Se não conseguir parsear como URL, adicionar manualmente
            const separator = url.includes('?') ? '&' : '?';
            return url.includes('ref=') ? url : url + separator + 'ref=' + encodeURIComponent(referralCode);
        }
    }

    /**
     * Remove parâmetro ?ref= da URL na barra de endereços
     */
    cleanURL() {
        const url = new URL(window.location);
        if (url.searchParams.has('ref')) {
            url.searchParams.delete('ref');
            window.history.replaceState({}, document.title, url.toString());
        }
    }

    /**
     * Obtém código de afiliação atual
     */
    getReferralCode() {
        return localStorage.getItem(this.storageKey);
    }

    /**
     * Remove código de afiliação (para logout ou limpeza)
     */
    clearReferralCode() {
        localStorage.removeItem(this.storageKey);
        console.log('🗑️ Código de afiliação removido');
    }

    /**
     * Define código de afiliação manualmente
     */
    setReferralCode(code) {
        if (code && code.trim() !== '') {
            localStorage.setItem(this.storageKey, code.trim());
            this.syncWithServer(code.trim());
            console.log('📝 Código de afiliação definido:', code.trim());
        }
    }
}

// Inicializar automaticamente quando o DOM estiver pronto
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        window.referralManager = new ReferralManager();
    });
} else {
    window.referralManager = new ReferralManager();
}

// Expor globalmente para uso manual se necessário
window.ReferralManager = ReferralManager;