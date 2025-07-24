<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/EfiPixManager.php';
require_once __DIR__ . '/../includes/classes/ContaManager.php';

// Configurar modo de teste
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Iniciando testes do fluxo de pagamento por saldo...\n\n";

try {
    // 1. Configurar modelo de pagamento
    echo "1. Configurando modelo de pagamento como 'conta_saldo'...\n";
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

    // 3. Criar conta e depositar saldo
    echo "3. Criando conta e depositando saldo...\n";
    $contaManager = new ContaManager();
    $contaId = $contaManager->criarConta($jogadorId);

    // Criar transação de depósito
    $dados = [
        'conta_id' => $contaId,
        'tipo' => 'deposito',
        'valor' => 50.00,
        'status' => 'aprovado',
        'metodo_pagamento' => 'pix',
        'afeta_saldo' => true,
        'descricao' => 'Teste: Depósito inicial',
        'data_processamento' => date('Y-m-d H:i:s')
    ];
    $transacaoDepositoId = dbInsert('transacoes', $dados);
    echo "✓ Conta criada e saldo depositado\n\n";

    // 4. Criar bolão de teste
    echo "4. Criando bolão de teste...\n";
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

    // 5. Verificar saldo antes do palpite
    echo "5. Verificando saldo antes do palpite...\n";
    $saldoAntes = verificarSaldoJogador($jogadorId);
    echo "Saldo antes: R$ " . number_format($saldoAntes['saldo_atual'], 2, ',', '.') . "\n\n";

    // 6. Criar palpite e debitar saldo
    echo "6. Criando palpite e debitando saldo...\n";
    $palpiteId = dbInsert('palpites', [
        'jogador_id' => $jogadorId,
        'bolao_id' => $bolaoId,
        'status' => 'pendente',
        'data_palpite' => date('Y-m-d H:i:s')
    ]);

    // Criar transação de débito
    $dados = [
        'conta_id' => $contaId,
        'tipo' => 'aposta',
        'valor' => 10.00,
        'status' => 'aprovado',
        'metodo_pagamento' => null,
        'afeta_saldo' => true,
        'palpite_id' => $palpiteId,
        'descricao' => 'Teste: Débito para palpite #' . $palpiteId,
        'data_processamento' => date('Y-m-d H:i:s')
    ];
    $transacaoDebitoId = dbInsert('transacoes', $dados);

    // Atualizar status do palpite
    $stmt = dbPrepare("UPDATE palpites SET status = 'pago' WHERE id = ?");
    $stmt->execute([$palpiteId]);
    echo "✓ Palpite criado e saldo debitado\n\n";

    // 7. Verificar saldo após o palpite
    echo "7. Verificando saldo após o palpite...\n";
    $saldoDepois = verificarSaldoJogador($jogadorId);
    echo "Saldo depois: R$ " . number_format($saldoDepois['saldo_atual'], 2, ',', '.') . "\n\n";

    // 8. Verificar status final
    echo "8. Verificando status final...\n";
    $palpite = dbFetchOne("SELECT * FROM palpites WHERE id = ?", [$palpiteId]);
    $transacaoDebito = dbFetchOne("SELECT * FROM transacoes WHERE id = ?", [$transacaoDebitoId]);

    echo "Palpite:\n";
    echo "- Status: " . $palpite['status'] . "\n";
    echo "- Data: " . $palpite['data_palpite'] . "\n\n";

    echo "Transação de débito:\n";
    echo "- Status: " . $transacaoDebito['status'] . "\n";
    echo "- Valor: R$ " . number_format($transacaoDebito['valor'], 2, ',', '.') . "\n";
    echo "- Tipo: " . $transacaoDebito['tipo'] . "\n\n";

    // 9. Limpar dados de teste
    echo "9. Limpando dados de teste...\n";
    dbExecute("DELETE FROM transacoes WHERE id IN (?, ?)", [$transacaoDepositoId, $transacaoDebitoId]);
    dbExecute("DELETE FROM palpites WHERE id = ?", [$palpiteId]);
    dbExecute("DELETE FROM dados_boloes WHERE id = ?", [$bolaoId]);
    dbExecute("DELETE FROM contas WHERE id = ?", [$contaId]);
    dbExecute("DELETE FROM jogador WHERE id = ?", [$jogadorId]);
    echo "✓ Dados limpos\n\n";

    echo "Testes concluídos com sucesso!\n";

} catch (Exception $e) {
    echo "\n❌ ERRO: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
} 