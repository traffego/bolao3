<?php
require_once 'config/config.php';
require_once 'includes/functions.php';

echo "=== Verificação da Tabela contas ===\n\n";

try {
    // Verifica estrutura da tabela contas
    $sql = "DESCRIBE contas";
    $columns = dbFetchAll($sql);
    
    echo "📋 Colunas da tabela contas:\n";
    $hasSaldo = false;
    foreach ($columns as $column) {
        echo "- " . $column['Field'] . " (" . $column['Type'] . ")\n";
        if ($column['Field'] === 'saldo') {
            $hasSaldo = true;
        }
    }
    
    if (!$hasSaldo) {
        echo "\n❌ PROBLEMA ENCONTRADO: Tabela 'contas' não tem coluna 'saldo'!\n";
        echo "As triggers tentam atualizar 'contas.saldo' mas a coluna não existe.\n\n";
    } else {
        echo "\n✅ Tabela 'contas' tem coluna 'saldo' - OK!\n\n";
    }
    
    // Testa uma query simples na tabela contas
    echo "🔍 Testando query na tabela contas...\n";
    $sql = "SELECT id, saldo FROM contas LIMIT 1";
    $result = dbFetchOne($sql);
    
    if ($result) {
        echo "✅ Query na tabela contas funcionou!\n";
        echo "   ID: " . $result['id'] . ", Saldo: " . $result['saldo'] . "\n\n";
    } else {
        echo "❌ Nenhum registro encontrado na tabela contas\n\n";
    }
    
} catch (Exception $e) {
    echo "❌ Erro ao verificar tabela contas: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n\n";
}

// Teste específico da transação 24313
echo "🧪 Teste específico da transação 24313...\n";

try {
    // Busca informações da transação
    $sql = "SELECT t.*, c.id as conta_id FROM transacoes t 
            INNER JOIN contas c ON t.conta_id = c.id 
            WHERE t.id = 24313";
    $transacao = dbFetchOne($sql);
    
    if ($transacao) {
        echo "✅ Transação encontrada:\n";
        echo "   ID: " . $transacao['id'] . "\n";
        echo "   Conta ID: " . $transacao['conta_id'] . "\n";
        echo "   Status: " . $transacao['status'] . "\n";
        echo "   Tipo: " . $transacao['tipo'] . "\n";
        echo "   Afeta Saldo: " . $transacao['afeta_saldo'] . "\n\n";
        
        // Verifica se a conta existe
        $sql = "SELECT id, saldo FROM contas WHERE id = ?";
        $conta = dbFetchOne($sql, [$transacao['conta_id']]);
        
        if ($conta) {
            echo "✅ Conta associada encontrada:\n";
            echo "   Conta ID: " . $conta['id'] . "\n";
            echo "   Saldo atual: " . $conta['saldo'] . "\n\n";
        } else {
            echo "❌ Conta associada não encontrada!\n\n";
        }
        
    } else {
        echo "❌ Transação 24313 não encontrada\n\n";
    }
    
} catch (Exception $e) {
    echo "❌ Erro no teste da transação: " . $e->getMessage() . "\n\n";
}

echo "=== Fim da Verificação ===\n";
?>
