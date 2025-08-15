<?php
require_once '../includes/auth_admin.php';
require_once '../config/database.php';
require_once '../includes/EfiPixManager.php';
require_once '../includes/classes/Logger.php';

$logger = Logger::getInstance();
$pixManager = new EfiPixManager(defined('EFI_WEBHOOK_FATAL_FAILURE') ? EFI_WEBHOOK_FATAL_FAILURE : false);

// Função para verificar se um arquivo existe e é legível
function checkFile($path, $description) {
    $fullPath = realpath($path);
    if (file_exists($path)) {
        if (is_readable($path)) {
            return ['status' => 'success', 'message' => "$description encontrado e legível", 'path' => $fullPath];
        } else {
            return ['status' => 'error', 'message' => "$description encontrado mas não é legível", 'path' => $fullPath];
        }
    } else {
        return ['status' => 'error', 'message' => "$description não encontrado", 'path' => $path];
    }
}

// Função para testar conectividade com a API EFI
function testEfiConnectivity($pixManager) {
    try {
        $result = $pixManager->testConnectivity();
        return $result;
    } catch (Exception $e) {
        return ['status' => 'error', 'message' => 'Erro ao testar conectividade: ' . $e->getMessage()];
    }
}

// Função para verificar configurações no banco
function checkDatabaseConfig($pdo) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM configuracoes WHERE nome IN ('efi_client_id', 'efi_client_secret', 'efi_sandbox', 'efi_webhook_url', 'efi_chave_pix')");
        $stmt->execute();
        $configs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $configMap = [];
        foreach ($configs as $config) {
            $configMap[$config['nome']] = $config['valor'];
        }
        
        return $configMap;
    } catch (Exception $e) {
        return ['error' => 'Erro ao buscar configurações: ' . $e->getMessage()];
    }
}

// Função para verificar permissões do diretório de logs
function checkLogsDirectory() {
    $logsDir = '../logs';
    $results = [];
    
    if (!is_dir($logsDir)) {
        $results[] = ['status' => 'error', 'message' => 'Diretório de logs não existe'];
    } else {
        $results[] = ['status' => 'success', 'message' => 'Diretório de logs existe'];
        
        if (is_writable($logsDir)) {
            $results[] = ['status' => 'success', 'message' => 'Diretório de logs tem permissão de escrita'];
        } else {
            $results[] = ['status' => 'error', 'message' => 'Diretório de logs não tem permissão de escrita'];
        }
        
        // Verificar .htaccess
        $htaccessFile = $logsDir . '/.htaccess';
        if (file_exists($htaccessFile)) {
            $results[] = ['status' => 'success', 'message' => 'Arquivo .htaccess existe no diretório de logs'];
        } else {
            $results[] = ['status' => 'warning', 'message' => 'Arquivo .htaccess não encontrado no diretório de logs'];
        }
    }
    
    return $results;
}

// Executar diagnósticos
$diagnostics = [];

// 1. Verificar configurações do banco
$diagnostics['database'] = checkDatabaseConfig($pdo);

// 2. Verificar arquivos necessários
$diagnostics['files'] = [
    'efi_config' => checkFile('../config/efi_config_db.php', 'Arquivo de configuração EFI'),
    'certificate' => checkFile('../config/certificados/producao.p12', 'Certificado de produção'),
    'certificate_sandbox' => checkFile('../config/certificados/homologacao.p12', 'Certificado de homologação'),
    'pix_manager' => checkFile('../includes/EfiPixManager.php', 'EfiPixManager'),
    'webhook' => checkFile('../api/webhook_pix.php', 'Webhook PIX')
];

// 3. Testar conectividade EFI
$diagnostics['connectivity'] = testEfiConnectivity($pixManager);

// 4. Verificar diretório de logs
$diagnostics['logs'] = checkLogsDirectory();

// 5. Verificar últimas transações
try {
    $stmt = $pdo->prepare("SELECT id, txid, status, valor, created_at FROM transacoes_pix ORDER BY created_at DESC LIMIT 10");
    $stmt->execute();
    $diagnostics['recent_transactions'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $diagnostics['recent_transactions'] = ['error' => $e->getMessage()];
}

// Função para exibir status com cores
function displayStatus($status) {
    $colors = [
        'success' => '#28a745',
        'error' => '#dc3545',
        'warning' => '#ffc107'
    ];
    $color = $colors[$status] ?? '#6c757d';
    return "<span style='color: $color; font-weight: bold;'>" . strtoupper($status) . "</span>";
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Diagnóstico EFI PIX - Bolão</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .diagnostic-section {
            margin-bottom: 2rem;
            padding: 1.5rem;
            border: 1px solid #dee2e6;
            border-radius: 0.375rem;
        }
        .status-success { color: #28a745; font-weight: bold; }
        .status-error { color: #dc3545; font-weight: bold; }
        .status-warning { color: #ffc107; font-weight: bold; }
        pre { background: #f8f9fa; padding: 1rem; border-radius: 0.375rem; }
    </style>
</head>
<body>
    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-12">
                <h1 class="mb-4">Diagnóstico EFI PIX</h1>
                
                <!-- Configurações do Banco -->
                <div class="diagnostic-section">
                    <h3>Configurações do Banco de Dados</h3>
                    <?php if (isset($diagnostics['database']['error'])): ?>
                        <div class="alert alert-danger"><?= $diagnostics['database']['error'] ?></div>
                    <?php else: ?>
                        <table class="table table-sm">
                            <tr><td><strong>Client ID:</strong></td><td><?= isset($diagnostics['database']['efi_client_id']) ? '***' . substr($diagnostics['database']['efi_client_id'], -4) : 'NÃO CONFIGURADO' ?></td></tr>
                            <tr><td><strong>Client Secret:</strong></td><td><?= isset($diagnostics['database']['efi_client_secret']) ? '***' . substr($diagnostics['database']['efi_client_secret'], -4) : 'NÃO CONFIGURADO' ?></td></tr>
                            <tr><td><strong>Sandbox:</strong></td><td><?= $diagnostics['database']['efi_sandbox'] ?? 'NÃO CONFIGURADO' ?></td></tr>
                            <tr><td><strong>Webhook URL:</strong></td><td><?= $diagnostics['database']['efi_webhook_url'] ?? 'NÃO CONFIGURADO' ?></td></tr>
                            <tr><td><strong>Chave PIX:</strong></td><td><?= $diagnostics['database']['efi_chave_pix'] ?? 'NÃO CONFIGURADO' ?></td></tr>
                        </table>
                    <?php endif; ?>
                </div>

                <!-- Arquivos -->
                <div class="diagnostic-section">
                    <h3>Arquivos Necessários</h3>
                    <table class="table table-sm">
                        <?php foreach ($diagnostics['files'] as $key => $file): ?>
                            <tr>
                                <td><?= $file['path'] ?></td>
                                <td><?= displayStatus($file['status']) ?></td>
                                <td><?= $file['message'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                </div>

                <!-- Conectividade -->
                <div class="diagnostic-section">
                    <h3>Teste de Conectividade EFI</h3>
                    <?php if (isset($diagnostics['connectivity']['status'])): ?>
                        <div class="alert alert-<?= $diagnostics['connectivity']['status'] === 'success' ? 'success' : 'danger' ?>">
                            <?= displayStatus($diagnostics['connectivity']['status']) ?> - <?= $diagnostics['connectivity']['message'] ?>
                        </div>
                        <?php if (isset($diagnostics['connectivity']['details'])): ?>
                            <pre><?= json_encode($diagnostics['connectivity']['details'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?></pre>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>

                <!-- Logs -->
                <div class="diagnostic-section">
                    <h3>Diretório de Logs</h3>
                    <?php foreach ($diagnostics['logs'] as $log): ?>
                        <div class="alert alert-<?= $log['status'] === 'success' ? 'success' : ($log['status'] === 'warning' ? 'warning' : 'danger') ?>">
                            <?= displayStatus($log['status']) ?> - <?= $log['message'] ?>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Transações Recentes -->
                <div class="diagnostic-section">
                    <h3>Últimas 10 Transações PIX</h3>
                    <?php if (isset($diagnostics['recent_transactions']['error'])): ?>
                        <div class="alert alert-danger"><?= $diagnostics['recent_transactions']['error'] ?></div>
                    <?php else: ?>
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>TXID</th>
                                    <th>Status</th>
                                    <th>Valor</th>
                                    <th>Data</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($diagnostics['recent_transactions'] as $transaction): ?>
                                    <tr>
                                        <td><?= $transaction['id'] ?></td>
                                        <td><?= substr($transaction['txid'], 0, 10) ?>...</td>
                                        <td><span class="badge bg-<?= $transaction['status'] === 'CONCLUIDA' ? 'success' : 'warning' ?>"><?= $transaction['status'] ?></span></td>
                                        <td>R$ <?= number_format($transaction['valor'], 2, ',', '.') ?></td>
                                        <td><?= date('d/m/Y H:i', strtotime($transaction['created_at'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>

                <!-- Ações -->
                <div class="diagnostic-section">
                    <h3>Ações Disponíveis</h3>
                    <div class="btn-group" role="group">
                        <a href="configuracoes.php" class="btn btn-primary">Configurações EFI</a>
                        <a href="../api/webhook_status.php" class="btn btn-secondary" target="_blank">Status Webhook</a>
                        <button onclick="location.reload()" class="btn btn-info">Atualizar Diagnóstico</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
