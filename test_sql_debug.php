<?php
require_once 'config/config.php';
require_once 'includes/functions.php';

// Teste para identificar o problema SQL com coluna 'saldo'
echo "=== Teste de Debug SQL ===\n";

try {
    // Tenta atualizar a transação 24313 para aprovado
    $transacaoId = 24313;
    
    echo "Tentando atualizar transação ID: $transacaoId\n";
    
    $sql = "UPDATE transacoes 
            SET status = 'aprovado',
                data_processamento = NOW(),
                afeta_saldo = 1
            WHERE id = ?";
    
    echo "SQL que será executado:\n$sql\n";
    
    $result = dbExecute($sql, [$transacaoId]);
    
    if ($result) {
        echo "✅ Sucesso! Transação atualizada.\n";
    } else {
        echo "❌ Falha ao atualizar transação.\n";
    }
    
} catch (Exception $e) {
    echo "❌ Erro capturado: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n=== Verificando estrutura da tabela transacoes ===\n";

try {
    $sql = "DESCRIBE transacoes";
    $columns = dbFetchAll($sql);
    
    echo "Colunas da tabela transacoes:\n";
    foreach ($columns as $column) {
        echo "- " . $column['Field'] . " (" . $column['Type'] . ")\n";
    }
    
} catch (Exception $e) {
    echo "❌ Erro ao verificar estrutura: " . $e->getMessage() . "\n";
}

echo "\n=== Fim do teste ===\n";
?>
