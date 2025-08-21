<?php
/**
 * Debug para capturar o fluxo real do cadastro manual
 * Este arquivo deve ser incluído no cadastro.php para monitorar o processo
 */

// Função para log de debug
function debugLog($message, $data = null) {
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] $message";
    if ($data !== null) {
        $logEntry .= " - Data: " . print_r($data, true);
    }
    $logEntry .= "\n";
    
    // Salvar em arquivo de log
    file_put_contents('debug_cadastro_log.txt', $logEntry, FILE_APPEND | LOCK_EX);
    
    // Também exibir na tela se estivermos em modo debug
    if (isset($_GET['debug']) || isset($_POST['debug'])) {
        echo "<div style='background: #f0f0f0; padding: 10px; margin: 5px; border-left: 3px solid #007cba;'>";
        echo "<strong>DEBUG:</strong> " . htmlspecialchars($message);
        if ($data !== null) {
            echo "<pre>" . htmlspecialchars(print_r($data, true)) . "</pre>";
        }
        echo "</div>";
    }
}

// Limpar log anterior
if (isset($_GET['clear_log'])) {
    file_put_contents('debug_cadastro_log.txt', '');
    echo "<p style='color: green;'>Log limpo!</p>";
}

// Log inicial
debugLog("=== INÍCIO DO PROCESSO DE CADASTRO ===");
debugLog("Método da requisição", $_SERVER['REQUEST_METHOD']);
debugLog("URL completa", $_SERVER['REQUEST_URI']);
debugLog("Parâmetros GET", $_GET);

// Log da sessão inicial
debugLog("Estado inicial da sessão", $_SESSION);

// Log da captura do referral code
if (isset($_GET['ref']) && !empty($_GET['ref'])) {
    debugLog("Parâmetro ref encontrado na URL", $_GET['ref']);
} else {
    debugLog("Nenhum parâmetro ref na URL");
}

if (isset($_SESSION['referral_code']) && !empty($_SESSION['referral_code'])) {
    debugLog("Código de referência encontrado na sessão", $_SESSION['referral_code']);
} else {
    debugLog("Nenhum código de referência na sessão");
}

// Se for POST, log dos dados enviados
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    debugLog("=== PROCESSAMENTO DO FORMULÁRIO ===");
    debugLog("Dados POST recebidos", $_POST);
    
    // Log específico do referral_code
    if (isset($_POST['referral_code'])) {
        debugLog("Campo referral_code do POST", [
            'valor' => $_POST['referral_code'],
            'vazio' => empty($_POST['referral_code']),
            'length' => strlen($_POST['referral_code'])
        ]);
    } else {
        debugLog("Campo referral_code NÃO encontrado no POST");
    }
    
    // Log da lógica de atribuição
    $referralFromPost = trim($_POST['referral_code'] ?? '');
    $referralFromSession = $_SESSION['referral_code'] ?? '';
    
    debugLog("Análise dos códigos de referência", [
        'post_trimmed' => $referralFromPost,
        'session' => $referralFromSession,
        'post_empty' => empty($referralFromPost),
        'will_use' => !empty($referralFromPost) ? $referralFromPost : $referralFromSession
    ]);
}

// Função para interceptar a inserção no banco
function debugDbInsert($table, $data) {
    debugLog("=== TENTATIVA DE INSERÇÃO NO BANCO ===");
    debugLog("Tabela", $table);
    debugLog("Dados para inserção", $data);
    
    // Verificar especificamente os campos de afiliação
    if (isset($data['ref_indicacao'])) {
        debugLog("Campo ref_indicacao", [
            'valor' => $data['ref_indicacao'],
            'tipo' => gettype($data['ref_indicacao']),
            'vazio' => empty($data['ref_indicacao'])
        ]);
    } else {
        debugLog("Campo ref_indicacao NÃO encontrado nos dados");
    }
    
    if (isset($data['codigo_afiliado'])) {
        debugLog("Campo codigo_afiliado", $data['codigo_afiliado']);
    }
    
    if (isset($data['afiliado_ativo'])) {
        debugLog("Campo afiliado_ativo", $data['afiliado_ativo']);
    }
    
    // Chamar a função original
    $result = dbInsert($table, $data);
    
    debugLog("Resultado da inserção", [
        'sucesso' => $result !== false,
        'id_retornado' => $result
    ]);
    
    // Se houve sucesso, verificar o que foi realmente salvo
    if ($result && $table === 'jogador') {
        $savedData = dbFetchOne("SELECT codigo_afiliado, ref_indicacao, afiliado_ativo FROM jogador WHERE id = ?", [$result]);
        debugLog("Dados realmente salvos no banco", $savedData);
    }
    
    return $result;
}

// Exibir log atual se solicitado
if (isset($_GET['show_log'])) {
    echo "<h2>Log de Debug do Cadastro</h2>";
    if (file_exists('debug_cadastro_log.txt')) {
        echo "<pre style='background: #f5f5f5; padding: 15px; border: 1px solid #ddd; max-height: 500px; overflow-y: auto;'>";
        echo htmlspecialchars(file_get_contents('debug_cadastro_log.txt'));
        echo "</pre>";
        echo "<p><a href='?clear_log=1'>Limpar Log</a></p>";
    } else {
        echo "<p>Nenhum log encontrado.</p>";
    }
    exit;
}

debugLog("Debug inicializado com sucesso");
?>