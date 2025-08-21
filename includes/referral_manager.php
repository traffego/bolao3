<?php
/**
 * Gerenciador Simples de Códigos de Afiliação
 * Funções básicas para trabalhar com localStorage
 */

/**
 * Inicializa o sistema de afiliação
 * Captura ?ref= da URL se presente
 */
function initReferralSystem() {
    // Capturar código da URL se presente
    if (isset($_GET['ref']) && !empty(trim($_GET['ref']))) {
        $referralCode = trim($_GET['ref']);
        
        // Validar código
         if (validateReferralCode($referralCode)) {
             // Se usuário logado e não tem ref_indicacao, atualizar
             updateUserReferralIfEmpty($referralCode);
             
             if (defined('DEBUG_REFERRAL') && DEBUG_REFERRAL) {
                 error_log("[ReferralManager] Código capturado: {$referralCode}");
             }
         }
    }
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
 * Gera HTML para incluir o script do gerenciador de afiliação
 */
function getReferralManagerScript() {
    return '
<script src="/public/js/referral-manager.js"></script>';
}

/**
 * Exibe informações de debug do sistema de afiliação
 */
function debugReferralSystem() {
    if (!defined('DEBUG_REFERRAL') || !DEBUG_REFERRAL) {
        return;
    }
    
    $getRef = isset($_GET['ref']) ? $_GET['ref'] : 'não definido';
    
    $userInfo = 'não logado';
    if (function_exists('getCurrentUserId')) {
        $userId = getCurrentUserId();
        if ($userId) {
            global $pdo;
            $stmt = $pdo->prepare("SELECT nome, codigo_afiliado, ref_indicacao FROM jogador WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user) {
                $userInfo = "ID: {$userId}, Nome: {$user['nome']}, Código: {$user['codigo_afiliado']}, Ref: {$user['ref_indicacao']}";
            }
        }
    }
    
    echo "<div style='background: #f0f0f0; padding: 10px; margin: 10px 0; border: 1px solid #ccc; font-family: monospace; font-size: 12px;'>";
    echo "<strong>DEBUG - Sistema de Afiliação (localStorage):</strong><br>";
    echo "GET[ref]: {$getRef}<br>";
    echo "Usuário: {$userInfo}<br>";
    echo "<script>";
    echo "const storedRef = localStorage.getItem('bolao_referral_code');";
    echo "document.write('localStorage[bolao_referral_code]: ' + (storedRef || 'não definido'));";
    echo "</script><br>";
    echo "</div>";
}

/**
 * Obter código de referência do localStorage (via JavaScript)
 */
function getCurrentReferralCode() {
    // Esta função retorna null pois o código agora está no localStorage
    // A verificação será feita via JavaScript no frontend
    return null;
}

/**
 * Atualiza ref_indicacao de usuário logado se ainda não tiver
 */
function updateUserReferralIfEmpty($referralCode = null) {
    $userId = getCurrentUserId();
    if (!$userId) {
        return false;
    }
    
    // Se não foi passado código, não há nada para fazer
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