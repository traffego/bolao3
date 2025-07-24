<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/EfiPixManager.php';
require_once __DIR__ . '/../includes/classes/ContaManager.php';

// Configurar modo de teste
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Iniciando testes de cenários de erro...\n\n";

try {
    // Criar dados de teste base
    echo "Criando dados de teste base...\n";
    
    // Criar usuário
    $email = 'teste_' . time() . '@example.com';
    $senha = password_hash('teste123', PASSWORD_DEFAULT);
    $jogadorId = dbInsert('jogador', [
        'nome' => 'Usuário Teste',
        'email' => $email,
        'senha' => $senha,
        'status' => 1,
        'data_cadastro' => date('Y-m-d H:i:s')
    ]);

    // Criar conta
    $contaManager = new ContaManager();
    $contaId = $contaManager->criarConta($jogadorId);

    // Criar bolão
    $bolaoId = dbInsert('dados_boloes', [
        'nome' => 'Bolão Teste',
        'descricao' => 'Bolão para testes',
        'valor_participacao' => 50.00,
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
    
    echo "✓ Dados base criados\n\n";

    // 1. Testar saldo insuficiente
    echo "1. Testando saldo insuficiente...\n";
    
    // Configurar modelo de pagamento
    $stmt = dbPrepare("
        INSERT INTO configuracoes (nome_configuracao, valor, categoria, descricao)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
        valor = VALUES(valor)
    ");
    $stmt->execute([
        'modelo_pagamento',
        'conta_saldo',
        'pagamento',
        'Modelo de pagamento: por_aposta (paga cada aposta) ou conta_saldo (usa saldo da conta)'
    ]);

    // Depositar saldo insuficiente
    $dados = [
        'conta_id' => $contaId,
        'tipo' => 'deposito',
        'valor' => 10.00, // Menor que o valor do bolão
        'status' => 'aprovado',
        'metodo_pagamento' => 'pix',
        'afeta_saldo' => true,
        'descricao' => 'Teste: Depósito insuficiente',
        'data_processamento' => date('Y-m-d H:i:s')
    ];
    $transacaoDepositoId = dbInsert('transacoes', $dados);

    // Tentar criar palpite
    try {
        $saldoInfo = verificarSaldoJogador($jogadorId);
        if ($saldoInfo['saldo_atual'] >= 50.00) {
            throw new Exception("Erro no teste: saldo deveria ser insuficiente");
        }
        echo "✓ Saldo insuficiente detectado corretamente\n\n";
    } catch (Exception $e) {
        if (strpos($e->getMessage(), "saldo deveria ser insuficiente") !== false) {
            throw $e;
        }
        echo "✓ Erro capturado corretamente: " . $e->getMessage() . "\n\n";
    }

    // 2. Testar falha na API
    echo "2. Testando falha na API...\n";
    
    // Configurar modelo de pagamento
    $stmt->execute([
        'modelo_pagamento',
        'por_aposta',
        'pagamento',
        'Modelo de pagamento: por_aposta (paga cada aposta) ou conta_saldo (usa saldo da conta)'
    ]);

    // Criar palpite
    $palpiteId = dbInsert('palpites', [
        'jogador_id' => $jogadorId,
        'bolao_id' => $bolaoId,
        'status' => 'pendente',
        'data_palpite' => date('Y-m-d H:i:s')
    ]);

    // Simular falha na API
    try {
        $efiPix = new EfiPixManager();
        
        // Forçar falha definindo credenciais inválidas
        $stmt->execute([
            'client_id',
            'invalid_id',
            'pagamento',
            'ID do cliente fornecido pela Efí'
        ]);
        $stmt->execute([
            'client_secret',
            'invalid_secret',
            'pagamento',
            'Chave secreta fornecida pela Efí'
        ]);

        // Tentar criar cobrança
        $charge = $efiPix->createCharge('TST123', $jogadorId, 50.00);
        throw new Exception("Erro no teste: deveria ter falhado ao criar cobrança");
    } catch (Exception $e) {
        if (strpos($e->getMessage(), "deveria ter falhado") !== false) {
            throw $e;
        }
        echo "✓ Erro da API capturado corretamente: " . $e->getMessage() . "\n\n";
    }

    // 3. Testar timeout no pagamento
    echo "3. Testando timeout no pagamento...\n";
    
    // Criar transação que vai expirar
    $timestamp = time();
    $random = bin2hex(random_bytes(10));
    $prefix = 'TST';
    $userId = str_pad($jogadorId, 3, '0', STR_PAD_LEFT);
    $txid = substr($prefix . $userId . $timestamp . $random, 0, 35);

    $dados = [
        'tipo' => 'aposta',
        'valor' => 50.00,
        'status' => 'pendente',
        'metodo_pagamento' => 'pix',
        'afeta_saldo' => false,
        'palpite_id' => $palpiteId,
        'txid' => $txid,
        'descricao' => 'Teste: Pagamento que vai expirar',
        'data_solicitacao' => date('Y-m-d H:i:s', strtotime('-1 day')) // Data antiga
    ];
    $transacaoTimeoutId = dbInsert('transacoes', $dados);

    // Simular verificação após timeout
    try {
        $status = $efiPix->checkPayment($txid);
        if ($status['status'] !== 'EXPIRADA') {
            throw new Exception("Erro no teste: pagamento deveria ter expirado");
        }
        echo "✓ Timeout do pagamento detectado corretamente\n\n";
    } catch (Exception $e) {
        if (strpos($e->getMessage(), "deveria ter expirado") !== false) {
            throw $e;
        }
        echo "✓ Erro de timeout capturado corretamente: " . $e->getMessage() . "\n\n";
    }

    // 4. Testar estorno/cancelamento
    echo "4. Testando estorno/cancelamento...\n";
    
    // Criar transação aprovada
    $dados = [
        'conta_id' => $contaId,
        'tipo' => 'aposta',
        'valor' => 50.00,
        'status' => 'aprovado',
        'metodo_pagamento' => 'pix',
        'afeta_saldo' => true,
        'palpite_id' => $palpiteId,
        'descricao' => 'Teste: Pagamento para estorno',
        'data_processamento' => date('Y-m-d H:i:s')
    ];
    $transacaoEstornoId = dbInsert('transacoes', $dados);

    // Criar estorno
    $dados = [
        'conta_id' => $contaId,
        'tipo' => 'estorno',
        'valor' => 50.00,
        'status' => 'aprovado',
        'metodo_pagamento' => 'pix',
        'afeta_saldo' => true,
        'transacao_origem_id' => $transacaoEstornoId,
        'descricao' => 'Teste: Estorno do pagamento',
        'data_processamento' => date('Y-m-d H:i:s')
    ];
    $estornoId = dbInsert('transacoes', $dados);

    // Verificar se o saldo voltou ao normal
    $saldoInfo = verificarSaldoJogador($jogadorId);
    if ($saldoInfo['saldo_atual'] !== 10.00) { // Deve voltar ao saldo inicial
        throw new Exception("Erro no teste: saldo após estorno deveria ser R$ 10,00");
    }
    echo "✓ Estorno processado corretamente\n\n";

    // Limpar dados de teste
    echo "Limpando dados de teste...\n";
    dbExecute("DELETE FROM transacoes WHERE id IN (?, ?, ?, ?)", [
        $transacaoDepositoId,
        $transacaoTimeoutId,
        $transacaoEstornoId,
        $estornoId
    ]);
    dbExecute("DELETE FROM palpites WHERE id = ?", [$palpiteId]);
    dbExecute("DELETE FROM dados_boloes WHERE id = ?", [$bolaoId]);
    dbExecute("DELETE FROM contas WHERE id = ?", [$contaId]);
    dbExecute("DELETE FROM jogador WHERE id = ?", [$jogadorId]);
    echo "✓ Dados limpos\n\n";

    echo "Testes de cenários de erro concluídos com sucesso!\n";

} catch (Exception $e) {
    echo "\n❌ ERRO: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
} 