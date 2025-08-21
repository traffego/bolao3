/**
 * Sistema simples de captura de referência
 * Captura parâmetro ?ref= da URL e salva no localStorage
 * Aplica o valor em formulários de cadastro
 */

(function() {
    'use strict';
    
    // 1. Capturar parâmetro ref da URL
    function captureRefFromUrl() {
        const urlParams = new URLSearchParams(window.location.search);
        const refCode = urlParams.get('ref');
        
        if (refCode && refCode.trim() !== '') {
            // 2. Salvar no localStorage
            localStorage.setItem('bolao_ref_code', refCode.trim());
            console.log('Código de referência capturado e salvo:', refCode);
        }
    }
    
    // 3. Aplicar valor do localStorage em campos hidden dos formulários
    function applyRefToForms() {
        const storedRef = localStorage.getItem('bolao_ref_code');
        
        if (storedRef) {
            // Procurar por campos hidden de referência
            const refFields = document.querySelectorAll('input[name="referral_code"], input[name="ref_code"], input[name="ref_indicacao"]');
            
            refFields.forEach(field => {
                field.value = storedRef;
                console.log('Código de referência aplicado ao campo:', field.name, '=', storedRef);
            });
            
            // Também aplicar ao campo registerReferralCode se existir
            const registerRefField = document.getElementById('registerReferralCode');
            if (registerRefField) {
                registerRefField.value = storedRef;
                console.log('Código de referência aplicado ao registerReferralCode:', storedRef);
            }
        }
    }
    
    // Executar quando a página carregar
    document.addEventListener('DOMContentLoaded', function() {
        // Sempre tentar capturar da URL primeiro
        captureRefFromUrl();
        
        // Depois aplicar aos formulários
        applyRefToForms();
        
        // Também aplicar quando novos formulários forem criados dinamicamente
        const observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                if (mutation.addedNodes.length > 0) {
                    applyRefToForms();
                }
            });
        });
        
        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
    });
    
    // Função global para debug
    window.debugRef = function() {
        const stored = localStorage.getItem('bolao_ref_code');
        const urlParams = new URLSearchParams(window.location.search);
        const urlRef = urlParams.get('ref');
        
        console.log('=== DEBUG REFERÊNCIA ===');
        console.log('URL atual:', window.location.href);
        console.log('Parâmetro ref na URL:', urlRef);
        console.log('Código armazenado:', stored);
        console.log('Campos de referência na página:');
        
        const refFields = document.querySelectorAll('input[name="referral_code"], input[name="ref_code"], input[name="ref_indicacao"], #registerReferralCode');
        refFields.forEach(field => {
            console.log('- Campo:', field.name || field.id, 'Valor:', field.value);
        });
        
        return {
            urlRef: urlRef,
            storedRef: stored,
            fieldsCount: refFields.length
        };
    };
    
})();