<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/EfiPixManager.php';
require_once __DIR__ . '/../includes/classes/ContaManager.php';

// Configurar modo de teste
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Iniciando testes do fluxo de pagamento por palpite...\n\n";

try {
    // 1. Configurar modelo de pagamento
    echo "1. Configurando modelo de pagamento como 'por_aposta'...\n";
    $stmt = dbPrepare("
        INSERT INTO configuracoes (nome_configuracao, valor, categoria, descricao)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
        valor = VALUES(valor)
    ");
    $stmt->execute([
        'modelo_pagamento',
        'por_aposta',
        'pagamento',
        'Modelo de pagamento: por_aposta (paga cada aposta) ou conta_saldo (usa saldo da conta)'
    ]);
    echo "✓ Modelo configurado\n\n";

    // 2. Criar usuário de teste
    echo "2. Criando usuário de teste...\n";
    $email = 'teste_' . time() . '@example.com';
    $senha = password_hash('teste123', PASSWORD_DEFAULT);
    $jogadorId = dbInsert('jogador', [
        'nome' => 'Usuário Teste',
        'email' => $email,
        'senha' => $senha,
        'status' => 1,
        'data_cadastro' => date('Y-m-d H:i:s')
    ]);
    echo "✓ Usuário criado (ID: $jogadorId)\n\n";

    // 3. Criar bolão de teste
    echo "3. Criando bolão de teste...\n";
    $bolaoId = dbInsert('dados_boloes', [
        'nome' => 'Bolão Teste',
        'descricao' => 'Bolão para testes',
        'valor_participacao' => 10.00,
        'status' => 1,
        'data_inicio' => date('Y-m-d'),
        'data_fim' => date('Y-m-d', strtotime('+7 days')),
        'data_limite_palpitar' => date('Y-m-d', strtotime('+6 days')),
        'jogos' => json_encode([
            [
                'id' => 1,
                'time_casa' => 'Time A',
                'time_visitante' => 'Time B',
                'data' => date('Y-m-d H:i:s', strtotime('+1 day'))
            ]
        ])
    ]);
    echo "✓ Bolão criado (ID: $bolaoId)\n\n";

    // 4. Criar palpite
    echo "4. Criando palpite...\n";
    $palpiteId = dbInsert('palpites', [
        'jogador_id' => $jogadorId,
        'bolao_id' => $bolaoId,
        'status' => 'pendente',
        'data_palpite' => date('Y-m-d H:i:s')
    ]);
    echo "✓ Palpite criado (ID: $palpiteId)\n\n";

    // 5. Criar transação PIX
    echo "5. Criando transação PIX...\n";
    $efiPix = new EfiPixManager();
    
    // Gerar TXID
    $timestamp = time();
    $random = bin2hex(random_bytes(10));
    $prefix = 'TST';
    $userId = str_pad($jogadorId, 3, '0', STR_PAD_LEFT);
    $txid = substr($prefix . $userId . $timestamp . $random, 0, 35);

    // Criar transação
    $dados = [
        'tipo' => 'aposta',
        'valor' => 10.00,
        'status' => 'pendente',
        'metodo_pagamento' => 'pix',
        'afeta_saldo' => false,
        'palpite_id' => $palpiteId,
        'txid' => $txid,
        'descricao' => 'Teste: Pagamento do palpite #' . $palpiteId
    ];

    $transacaoId = dbInsert('transacoes', $dados);
    echo "✓ Transação criada (ID: $transacaoId)\n\n";

    // 6. Simular pagamento
    echo "6. Simulando pagamento...\n";
    $stmt = dbPrepare("
        UPDATE transacoes 
        SET status = 'aprovado',
            data_processamento = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$transacaoId]);

    $stmt = dbPrepare("UPDATE palpites SET status = 'pago' WHERE id = ?");
    $stmt->execute([$palpiteId]);
    echo "✓ Pagamento simulado\n\n";

    // 7. Verificar status final
    echo "7. Verificando status final...\n";
    $transacao = dbFetchOne("SELECT * FROM transacoes WHERE id = ?", [$transacaoId]);
    $palpite = dbFetchOne("SELECT * FROM palpites WHERE id = ?", [$palpiteId]);

    echo "Transação:\n";
    echo "- Status: " . $transacao['status'] . "\n";
    echo "- Valor: R$ " . number_format($transacao['valor'], 2, ',', '.') . "\n";
    echo "- TXID: " . $transacao['txid'] . "\n\n";

    echo "Palpite:\n";
    echo "- Status: " . $palpite['status'] . "\n";
    echo "- Data: " . $palpite['data_palpite'] . "\n\n";

    // 8. Limpar dados de teste
    echo "8. Limpando dados de teste...\n";
    dbExecute("DELETE FROM transacoes WHERE id = ?", [$transacaoId]);
    dbExecute("DELETE FROM palpites WHERE id = ?", [$palpiteId]);
    dbExecute("DELETE FROM dados_boloes WHERE id = ?", [$bolaoId]);
    dbExecute("DELETE FROM jogador WHERE id = ?", [$jogadorId]);
    echo "✓ Dados limpos\n\n";

    echo "Testes concluídos com sucesso!\n";

} catch (Exception $e) {
    echo "\n❌ ERRO: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
} 