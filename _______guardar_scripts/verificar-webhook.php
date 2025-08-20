<?php
/**
 * Script CLI para verificar transações pendentes e atualizar status via API EFI
 * Este script pode ser executado via cron job para fallback de webhooks
 */

// Incluir configurações e classes necessárias
require_once __DIR__ . '/../config/efi_config_db.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/classes/Logger.php';
require_once __DIR__ . '/../includes/EfiPixManager.php';

// Verificar se está sendo executado via CLI
if (php_sapi_name() !== 'cli') {
    die('Este script deve ser executado apenas via CLI');
}

try {
    echo "Iniciando verificação de transações pendentes...\n";
    
    // Inicializar logger
    $logger = new Logger('verificar-webhook');
    $logger->info('Iniciando verificação de transações pendentes');
    
    // Inicializar conexão com banco de dados
    global $pdo;
    
    // Buscar transações pendentes (últimas 24 horas)
    $stmt = $pdo->prepare("SELECT * FROM transacoes WHERE status = 'pendente' AND data_criacao >= DATE_SUB(NOW(), INTERVAL 1 DAY) ORDER BY data_criacao DESC");
    $stmt->execute();
    $transacoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Encontradas " . count($transacoes) . " transações pendentes\n";
    $logger->info('Transações pendentes encontradas', ['count' => count($transacoes)]);
    
    if (empty($transacoes)) {
        echo "Nenhuma transação pendente para verificar\n";
        $logger->info('Nenhuma transação pendente para verificar');
        exit(0);
    }
    
    // Inicializar EfiPixManager
    $efiManager = new EfiPixManager(defined('EFI_WEBHOOK_FATAL_FAILURE') ? EFI_WEBHOOK_FATAL_FAILURE : false);
    
    $atualizadas = 0;
    $erros = 0;
    
    foreach ($transacoes as $transacao) {
        try {
            echo "Verificando transação ID: {$transacao['id']} - TXID: {$transacao['txid']}\n";
            $logger->info('Verificando transação', [
                'transacao_id' => $transacao['id'],
                'txid' => $transacao['txid']
            ]);
            
            // Verificar status da transação via API EFI
            $resultado = $efiManager->checkPayment($transacao['txid']);
            
            if (in_array($resultado['status'], ['aprovado', 'cancelado'])) {
                $atualizadas++;
                echo "Transação {$transacao['id']} atualizada para: {$resultado['status']}\n";
                $logger->info('Transação atualizada via verificação automática', [
                    'transacao_id' => $transacao['id'],
                    'novo_status' => $resultado['status'],
                    'valor_cobrado' => $resultado['valor']['cobrado'],
                    'valor_pago' => $resultado['valor']['pago']
                ]);
            } else {
                echo "Transação {$transacao['id']} ainda está pendente\n";
            }
            
        } catch (Exception $e) {
            $erros++;
            echo "Erro ao verificar transação {$transacao['id']}: " . $e->getMessage() . "\n";
            $logger->error('Erro ao verificar transação', [
                'transacao_id' => $transacao['id'],
                'txid' => $transacao['txid'],
                'error' => $e->getMessage()
            ]);
        }
        
        // Pequeno delay para não sobrecarregar a API
        usleep(500000); // 0.5 segundos
    }
    
    echo "\nVerificação concluída!";
    echo "Transações atualizadas: $atualizadas";
    echo "Erros: $erros";
    
    $logger->info('Verificação concluída', [
        'atualizadas' => $atualizadas,
        'erros' => $erros
    ]);
    
} catch (Exception $e) {
    echo "Erro fatal: " . $e->getMessage() . "\n";
    error_log("Erro no script verificar-webhook.php: " . $e->getMessage());
    exit(1);
}
