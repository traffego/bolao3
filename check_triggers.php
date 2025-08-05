<?php
require_once 'config/config.php';
require_once 'includes/functions.php';

echo "=== Análise de Triggers na Tabela transacoes ===\n\n";

try {
    // Lista todas as triggers da tabela transacoes
    $sql = "SHOW TRIGGERS WHERE `Table` = 'transacoes'";
    $triggers = dbFetchAll($sql);
    
    if (empty($triggers)) {
        echo "❌ Nenhuma trigger encontrada na tabela 'transacoes'\n";
    } else {
        echo "📋 Triggers encontradas na tabela 'transacoes':\n\n";
        
        foreach ($triggers as $trigger) {
            echo "🔧 Trigger: " . $trigger['Trigger'] . "\n";
            echo "   Evento: " . $trigger['Event'] . "\n";
            echo "   Timing: " . $trigger['Timing'] . "\n";
            echo "   Statement: " . $trigger['Statement'] . "\n";
            echo "   ---\n\n";
        }
    }
    
    // Verifica se há triggers que referenciam 'saldo'
    echo "🔍 Procurando triggers que referenciam 'saldo'...\n\n";
    
    foreach ($triggers as $trigger) {
        if (stripos($trigger['Statement'], 'saldo') !== false && 
            stripos($trigger['Statement'], 'saldo_anterior') === false && 
            stripos($trigger['Statement'], 'saldo_posterior') === false) {
            
            echo "⚠️  TRIGGER PROBLEMÁTICA ENCONTRADA!\n";
            echo "   Nome: " . $trigger['Trigger'] . "\n";
            echo "   Contém referência à coluna 'saldo' inexistente\n";
            echo "   Statement: " . $trigger['Statement'] . "\n\n";
            
            // Gera comando para remover a trigger
            echo "🔧 Comando para corrigir:\n";
            echo "   DROP TRIGGER IF EXISTS `" . $trigger['Trigger'] . "`;\n\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ Erro ao verificar triggers: " . $e->getMessage() . "\n";
}

echo "=== Fim da Análise ===\n";
?>
