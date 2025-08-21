/**
 * Sistema de captura de referência de afiliados
 * Captura parâmetro ?ref= da URL e salva no localStorage
 * Aplica o valor em formulários de cadastro
 * 
 * CHAVE UNIFICADA: bolao_referral_code
 */

(function() {
    'use strict';
    
    // Configuração unificada
    const REFERRAL_CONFIG = {
        STORAGE_KEY: 'bolao_referral_code', // Chave unificada
        URL_PARAM: 'ref',
        DEBUG: true
    };
    
    // 1. Capturar parâmetro ref da URL
    function captureRefFromUrl() {
        const urlParams = new URLSearchParams(window.location.search);
        const refCode = urlParams.get(REFERRAL_CONFIG.URL_PARAM);
        
        if (refCode && refCode.trim() !== '') {
            const cleanCode = refCode.trim();
            // 2. Salvar no localStorage com chave unificada
            localStorage.setItem(REFERRAL_CONFIG.STORAGE_KEY, cleanCode);
            
            if (REFERRAL_CONFIG.DEBUG) {
                console.log('[AFILIADOS] Código capturado da URL e salvo:', cleanCode);
            }
            
            return cleanCode;
        }
        return null;
    }
    
    // 3. Aplicar valor do localStorage em campos de formulários
    function applyRefToForms() {
        const storedRef = localStorage.getItem(REFERRAL_CONFIG.STORAGE_KEY);
        
        if (storedRef) {
            // Procurar por todos os campos de referência possíveis
            const refFields = document.querySelectorAll(
                'input[name="referral_code"], input[name="ref_code"], input[name="ref_indicacao"], #referral_code, #registerReferralCode'
            );
            
            refFields.forEach(field => {
                // Só preencher se o campo estiver vazio
                if (!field.value || field.value.trim() === '') {
                    field.value = storedRef;
                    
                    if (REFERRAL_CONFIG.DEBUG) {
                        console.log('[AFILIADOS] Código aplicado ao campo:', field.name || field.id, '=', storedRef);
                    }
                }
            });
        }
    }
    
    // 4. Limpar código de referência (útil para testes)
    function clearReferralCode() {
        localStorage.removeItem(REFERRAL_CONFIG.STORAGE_KEY);
        if (REFERRAL_CONFIG.DEBUG) {
            console.log('[AFILIADOS] Código de referência removido do localStorage');
        }
    }
    
    // 5. Obter código atual
    function getCurrentReferralCode() {
        return localStorage.getItem(REFERRAL_CONFIG.STORAGE_KEY);
    }
    
    // Executar quando a página carregar
    document.addEventListener('DOMContentLoaded', function() {
        if (REFERRAL_CONFIG.DEBUG) {
            console.log('[AFILIADOS] Inicializando sistema de captura de referência');
        }
        
        // Sempre tentar capturar da URL primeiro
        const capturedCode = captureRefFromUrl();
        
        // Depois aplicar aos formulários (seja da URL ou localStorage)
        applyRefToForms();
        
        // Também aplicar quando novos formulários forem criados dinamicamente
        const observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                if (mutation.addedNodes.length > 0) {
                    // Aguardar um pouco para o DOM se estabilizar
                    setTimeout(applyRefToForms, 100);
                }
            });
        });
        
        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
        
        if (REFERRAL_CONFIG.DEBUG) {
            const currentCode = getCurrentReferralCode();
            console.log('[AFILIADOS] Sistema inicializado. Código atual:', currentCode || 'Nenhum');
        }
    });
    
    // Funções globais para debug e controle
    window.debugRef = function() {
        const stored = getCurrentReferralCode();
        const urlParams = new URLSearchParams(window.location.search);
        const urlRef = urlParams.get(REFERRAL_CONFIG.URL_PARAM);
        
        console.log('=== DEBUG SISTEMA DE AFILIADOS ===');
        console.log('URL atual:', window.location.href);
        console.log('Parâmetro ref na URL:', urlRef || 'Não presente');
        console.log('Código armazenado:', stored || 'Nenhum');
        console.log('Chave do localStorage:', REFERRAL_CONFIG.STORAGE_KEY);
        console.log('Campos de referência na página:');
        
        const refFields = document.querySelectorAll(
            'input[name="referral_code"], input[name="ref_code"], input[name="ref_indicacao"], #referral_code, #registerReferralCode'
        );
        
        if (refFields.length === 0) {
            console.log('- Nenhum campo de referência encontrado');
        } else {
            refFields.forEach(field => {
                console.log('- Campo:', field.name || field.id, 'Valor:', field.value || 'Vazio');
            });
        }
        
        return {
            urlRef: urlRef,
            storedRef: stored,
            fieldsCount: refFields.length,
            storageKey: REFERRAL_CONFIG.STORAGE_KEY
        };
    };
    
    // Função global para limpar (útil para testes)
    window.clearRef = clearReferralCode;
    
    // Função global para obter código atual
    window.getCurrentRef = getCurrentReferralCode;
    
})();