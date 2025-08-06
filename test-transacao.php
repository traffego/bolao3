<?php
/**
 * Teste simples para verificar se o arquivo acao-transacao.php tem erros
 */

// Simular algumas variáveis de ambiente
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST['action'] = 'approve';
$_POST['transacao_id'] = '1';

// Inicializar sessão
session_start();

// Simular login de admin
$_SESSION['admin_id'] = 1;

// Tentar incluir o arquivo
echo "Testando arquivo acao-transacao.php...\n";

try {
    // Capturar qualquer output
    ob_start();
    
    // Verificar se o arquivo existe
    if (!file_exists('admin/acao-transacao.php')) {
        echo "ERRO: Arquivo admin/acao-transacao.php não encontrado\n";
        exit;
    }
    
    // Tentar parsear o arquivo sem executar
    $code = file_get_contents('admin/acao-transacao.php');
    
    // Verificar sintaxe
    $result = eval('return true; ?>' . $code);
    
    echo "Sintaxe do arquivo OK\n";
    
} catch (ParseError $e) {
    echo "ERRO DE SINTAXE: " . $e->getMessage() . "\n";
    echo "Linha: " . $e->getLine() . "\n";
} catch (Error $e) {
    echo "ERRO: " . $e->getMessage() . "\n";
} catch (Exception $e) {
    echo "EXCEÇÃO: " . $e->getMessage() . "\n";
} finally {
    $output = ob_get_clean();
    if (!empty($output)) {
        echo "Output capturado:\n" . $output . "\n";
    }
}

echo "Teste concluído.\n";
?>