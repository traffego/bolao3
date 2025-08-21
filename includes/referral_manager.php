<?php
/**
 * Gerenciador Global de Códigos de Afiliação
 * Garante captura e persistência do parâmetro ?ref= em todas as páginas
 */

/**
 * Inicializa o sistema de afiliação global
 * Deve ser chamado no início de cada página
 */
function initReferralSystem() {
    // Capturar parâmetro ?ref= da URL
    $referralCode = '';
    
    // Prioridade 1: Parâmetro da URL atual
    if (isset($_GET['ref']) && !empty(trim($_GET['ref']))) {
        $referralCode = trim($_GET['ref']);
        $_SESSION['referral_code'] = $referralCode;
        
        // Log para debug
        if (defined('DEBUG_REFERRAL') && DEBUG_REFERRAL) {
            error_log("[REFERRAL] Código capturado da URL: {$referralCode} | Página: {$_SERVER['REQUEST_URI']}");
        }
    }
    // Prioridade 2: Código já existente na sessão
    elseif (isset($_SESSION['referral_code']) && !empty($_SESSION['referral_code'])) {
        $referralCode = $_SESSION['referral_code'];
    }
    
    // Retornar código atual
    return $referralCode;
}

/**
 * Obtém o código de afiliação atual
 */
function getCurrentReferralCode() {
    return isset($_SESSION['referral_code']) ? $_SESSION['referral_code'] : '';
}

/**
 * Define um código de afiliação na sessão
 */
function setReferralCode($code) {
    if (!empty(trim($code))) {
        $_SESSION['referral_code'] = trim($code);
        return true;
    }
    return false;
}

/**
 * Remove o código de afiliação da sessão
 */
function clearReferralCode() {
    if (isset($_SESSION['referral_code'])) {
        unset($_SESSION['referral_code']);
        return true;
    }
    return false;
}

/**
 * Valida se um código de afiliação existe e está ativo
 */
function validateReferralCode($code) {
    if (empty(trim($code))) {
        return false;
    }
    
    try {
        $referral = dbFetchOne(
            "SELECT id, nome FROM jogador WHERE codigo_afiliado = ? AND afiliado_ativo = 'ativo'",
            [trim($code)]
        );
        return !empty($referral);
    } catch (Exception $e) {
        // Em caso de erro no banco, retorna false
        error_log("[REFERRAL VALIDATION ERROR] " . $e->getMessage());
        return false;
    }
}

/**
 * Adiciona código de afiliação a uma URL
 */
function addReferralToUrl($url, $code = null) {
    if ($code === null) {
        $code = getCurrentReferralCode();
    }
    
    if (empty($code) || empty($url)) {
        return $url;
    }
    
    // Verificar se já tem parâmetro ref
    if (strpos($url, 'ref=') !== false) {
        return $url;
    }
    
    // Adicionar parâmetro
    $separator = strpos($url, '?') !== false ? '&' : '?';
    return $url . $separator . 'ref=' . urlencode($code);
}

/**
 * Gera HTML para incluir o JavaScript do gerenciador de afiliação
 */
function getReferralManagerScript() {
    $referralCode = getCurrentReferralCode();
    $appUrl = defined('APP_URL') ? APP_URL : '';
    
    $html = "\n<!-- Gerenciador de Afiliação -->\n";
    $html .= "<script src='{$appUrl}/public/js/referral-manager.js'></script>\n";
    
    // Se há código na sessão, sincronizar com localStorage
    if (!empty($referralCode)) {
        $html .= "<script>\n";
        $html .= "document.addEventListener('DOMContentLoaded', function() {\n";
        $html .= "    if (window.referralManager) {\n";
        $html .= "        window.referralManager.setReferralCode('" . addslashes($referralCode) . "');\n";
        $html .= "    }\n";
        $html .= "});\n";
        $html .= "</script>\n";
    }
    
    return $html;
}

/**
 * Exibe informações de debug do sistema de afiliação
 */
function debugReferralSystem() {
    if (!defined('DEBUG_REFERRAL') || !DEBUG_REFERRAL) {
        return '';
    }
    
    $refGet = isset($_GET['ref']) ? $_GET['ref'] : 'Não presente';
    $refSession = getCurrentReferralCode() ?: 'Não definido';
    $currentPage = basename($_SERVER['PHP_SELF']);
    $currentTime = date('H:i:s');
    
    $html = "\n<!-- DEBUG AFILIAÇÃO -->\n";
    $html .= "<div style='position: fixed; top: 10px; right: 10px; background: rgba(0,0,0,0.8); color: white; padding: 10px; border-radius: 5px; font-size: 12px; z-index: 9999;'>\n";
    $html .= "🔍 DEBUG AFILIAÇÃO:<br>\n";
    $html .= "GET[ref]: {$refGet}<br>\n";
    $html .= "SESSION[referral_code]: {$refSession}<br>\n";
    $html .= "Página: {$currentPage} | Hora: {$currentTime}\n";
    $html .= "</div>\n";
    
    return $html;
}

/**
 * Atualiza ref_indicacao de usuário logado se ainda não tiver
 */
function updateUserReferralIfEmpty($userId = null) {
    if ($userId === null) {
        $userId = getCurrentUserId();
    }
    
    if (!$userId) {
        return false;
    }
    
    $referralCode = getCurrentReferralCode();
    if (empty($referralCode)) {
        return false;
    }
    
    try {
        // Verificar se usuário já tem ref_indicacao
        $user = dbFetchOne("SELECT ref_indicacao FROM jogador WHERE id = ?", [$userId]);
        
        if ($user && empty($user['ref_indicacao'])) {
            // Verificar se o código de afiliação é válido
            if (validateReferralCode($referralCode)) {
                // Atualizar ref_indicacao
                $result = dbExecute(
                    "UPDATE jogador SET ref_indicacao = ? WHERE id = ?",
                    [$referralCode, $userId]
                );
                
                if ($result) {
                    // Limpar código da sessão após usar
                    clearReferralCode();
                    
                    if (defined('DEBUG_REFERRAL') && DEBUG_REFERRAL) {
                        error_log("[REFERRAL] ref_indicacao atualizado para usuário {$userId} com código {$referralCode}");
                    }
                    
                    return true;
                }
            }
        }
    } catch (Exception $e) {
        error_log("[REFERRAL UPDATE ERROR] " . $e->getMessage());
    }
    
    return false;
}

// Inicializar sistema automaticamente quando arquivo for incluído
if (session_status() === PHP_SESSION_ACTIVE) {
    initReferralSystem();
}
?>