<?php
/**
 * Database Structure Viewer
 * Arquivo para consultar e exibir a estrutura completa do banco de dados
 * 
 * Uso: Acesse este arquivo no navegador para ver todas as tabelas e suas estruturas
 */

// Configurações do banco local
$host = 'localhost';
$user = 'root';
$pass = '';
$dbname = 'bolao_football';

// Criar conexão PDO
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Erro de conexão: " . $e->getMessage());
}

// Função para obter informações detalhadas de uma tabela
function getTableInfo($pdo, $tableName) {
    $info = [];
    
    // Informações básicas da tabela
    $sql = "SHOW TABLE STATUS LIKE ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$tableName]);
    $tableStatus = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($tableStatus) {
        $info['name'] = $tableStatus['Name'];
        $info['engine'] = $tableStatus['Engine'];
        $info['rows'] = $tableStatus['Rows'];
        $info['data_length'] = $tableStatus['Data_length'];
        $info['auto_increment'] = $tableStatus['Auto_increment'];
        $info['create_time'] = $tableStatus['Create_time'];
        $info['update_time'] = $tableStatus['Update_time'];
        $info['comment'] = $tableStatus['Comment'];
    }
    
    // Estrutura das colunas
    $sql = "DESCRIBE `$tableName`";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $info['columns'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Chaves estrangeiras
    $sql = "SELECT 
                COLUMN_NAME,
                REFERENCED_TABLE_NAME,
                REFERENCED_COLUMN_NAME,
                CONSTRAINT_NAME
            FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
            WHERE TABLE_SCHEMA = DATABASE() 
            AND TABLE_NAME = ? 
            AND REFERENCED_TABLE_NAME IS NOT NULL";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$tableName]);
    $info['foreign_keys'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Índices
    $sql = "SHOW INDEX FROM `$tableName`";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $info['indexes'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    return $info;
}

// Função para formatar bytes
function formatBytes($bytes, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    
    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, $precision) . ' ' . $units[$i];
}

// Função para obter estatísticas do banco
function getDatabaseStats($pdo) {
    $stats = [];
    
    // Total de tabelas
    $sql = "SELECT COUNT(*) as total_tables FROM information_schema.tables WHERE table_schema = DATABASE()";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $stats['total_tables'] = $stmt->fetch(PDO::FETCH_ASSOC)['total_tables'];
    
    // Tamanho total do banco
    $sql = "SELECT 
                ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS 'DB Size in MB'
            FROM information_schema.tables 
            WHERE table_schema = DATABASE()";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $stats['total_size'] = $stmt->fetch(PDO::FETCH_ASSOC)['DB Size in MB'];
    
    // Contagem de registros por tabela
    $sql = "SELECT 
                table_name,
                table_rows
            FROM information_schema.tables 
            WHERE table_schema = DATABASE()
            ORDER BY table_rows DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $stats['table_counts'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    return $stats;
}

// Obter lista de todas as tabelas
$sql = "SHOW TABLES";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Obter estatísticas do banco
$dbStats = getDatabaseStats($pdo);

// Processar tabela específica se solicitada
$selectedTable = $_GET['table'] ?? null;
$tableInfo = null;

if ($selectedTable && in_array($selectedTable, $tables)) {
    $tableInfo = getTableInfo($pdo, $selectedTable);
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Estrutura do Banco de Dados - Bolão Vitimba</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .table-info {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
        }
        .column-info {
            font-family: 'Courier New', monospace;
            font-size: 0.9em;
        }
        .foreign-key {
            color: #dc3545;
        }
        .primary-key {
            color: #198754;
            font-weight: bold;
        }
        .index-info {
            background-color: #e9ecef;
            padding: 5px;
            border-radius: 3px;
            margin: 2px 0;
        }
        .nav-tabs .nav-link {
            color: #495057;
        }
        .nav-tabs .nav-link.active {
            color: #0d6efd;
            font-weight: bold;
        }
        .database-info {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body class="bg-light">
    <div class="container-fluid py-4">
        <div class="row">
            <div class="col-12">
                <h1 class="mb-4">
                    <i class="bi bi-database"></i> 
                    Estrutura do Banco de Dados - Bolão Vitimba
                </h1>
                
                <!-- Informações do Banco -->
                <div class="database-info">
                    <div class="row">
                        <div class="col-md-3">
                            <h6><i class="bi bi-server"></i> Host</h6>
                            <p class="mb-0"><?= $host ?></p>
                        </div>
                        <div class="col-md-3">
                            <h6><i class="bi bi-database"></i> Banco</h6>
                            <p class="mb-0"><?= $dbname ?></p>
                        </div>
                        <div class="col-md-3">
                            <h6><i class="bi bi-person"></i> Usuário</h6>
                            <p class="mb-0"><?= $user ?></p>
                        </div>
                        <div class="col-md-3">
                            <h6><i class="bi bi-check-circle"></i> Status</h6>
                            <p class="mb-0">✅ Conectado</p>
                        </div>
                    </div>
                </div>
                
                <!-- Estatísticas Gerais -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <h5 class="card-title text-primary">
                                    <i class="bi bi-table"></i> <?= $dbStats['total_tables'] ?>
                                </h5>
                                <p class="card-text">Tabelas</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <h5 class="card-title text-success">
                                    <i class="bi bi-hdd"></i> <?= $dbStats['total_size'] ?> MB
                                </h5>
                                <p class="card-text">Tamanho Total</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-body">
                                <h6 class="card-title">Top 5 Tabelas por Registros</h6>
                                <div class="row">
                                    <?php foreach (array_slice($dbStats['table_counts'], 0, 5) as $table): ?>
                                        <div class="col-6">
                                            <small class="text-muted">
                                                <?= $table['table_name'] ?>: 
                                                <span class="badge bg-secondary"><?= number_format($table['table_rows']) ?></span>
                                            </small>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- Lista de Tabelas -->
                    <div class="col-md-3">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="bi bi-list"></i> Tabelas</h5>
                            </div>
                            <div class="card-body p-0">
                                <div class="list-group list-group-flush">
                                    <?php foreach ($tables as $table): ?>
                                        <a href="?table=<?= urlencode($table) ?>" 
                                           class="list-group-item list-group-item-action <?= $selectedTable === $table ? 'active' : '' ?>">
                                            <i class="bi bi-table"></i> <?= $table ?>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Detalhes da Tabela Selecionada -->
                    <div class="col-md-9">
                        <?php if ($tableInfo): ?>
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="mb-0">
                                        <i class="bi bi-table"></i> 
                                        Tabela: <?= htmlspecialchars($tableInfo['name']) ?>
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <!-- Informações Gerais -->
                                    <div class="table-info">
                                        <div class="row">
                                            <div class="col-md-3">
                                                <strong>Engine:</strong> <?= $tableInfo['engine'] ?>
                                            </div>
                                            <div class="col-md-3">
                                                <strong>Registros:</strong> <?= number_format($tableInfo['rows']) ?>
                                            </div>
                                            <div class="col-md-3">
                                                <strong>Tamanho:</strong> <?= formatBytes($tableInfo['data_length']) ?>
                                            </div>
                                            <div class="col-md-3">
                                                <strong>Auto Increment:</strong> <?= $tableInfo['auto_increment'] ?: 'N/A' ?>
                                            </div>
                                        </div>
                                        <?php if ($tableInfo['comment']): ?>
                                            <div class="mt-2">
                                                <strong>Comentário:</strong> <?= htmlspecialchars($tableInfo['comment']) ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Abas -->
                                    <ul class="nav nav-tabs" id="tableTabs" role="tablist">
                                        <li class="nav-item" role="presentation">
                                            <button class="nav-link active" id="columns-tab" data-bs-toggle="tab" 
                                                    data-bs-target="#columns" type="button" role="tab">
                                                <i class="bi bi-columns"></i> Colunas
                                            </button>
                                        </li>
                                        <li class="nav-item" role="presentation">
                                            <button class="nav-link" id="indexes-tab" data-bs-toggle="tab" 
                                                    data-bs-target="#indexes" type="button" role="tab">
                                                <i class="bi bi-key"></i> Índices
                                            </button>
                                        </li>
                                        <li class="nav-item" role="presentation">
                                            <button class="nav-link" id="foreign-keys-tab" data-bs-toggle="tab" 
                                                    data-bs-target="#foreign-keys" type="button" role="tab">
                                                <i class="bi bi-link-45deg"></i> Chaves Estrangeiras
                                            </button>
                                        </li>
                                    </ul>

                                    <div class="tab-content mt-3" id="tableTabsContent">
                                        <!-- Colunas -->
                                        <div class="tab-pane fade show active" id="columns" role="tabpanel">
                                            <div class="table-responsive">
                                                <table class="table table-sm table-striped">
                                                    <thead>
                                                        <tr>
                                                            <th>Campo</th>
                                                            <th>Tipo</th>
                                                            <th>Null</th>
                                                            <th>Chave</th>
                                                            <th>Padrão</th>
                                                            <th>Extra</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($tableInfo['columns'] as $column): ?>
                                                            <tr>
                                                                <td>
                                                                    <?php if ($column['Key'] === 'PRI'): ?>
                                                                        <span class="primary-key"><?= $column['Field'] ?></span>
                                                                    <?php elseif (in_array($column['Field'], array_column($tableInfo['foreign_keys'], 'COLUMN_NAME'))): ?>
                                                                        <span class="foreign-key"><?= $column['Field'] ?></span>
                                                                    <?php else: ?>
                                                                        <?= $column['Field'] ?>
                                                                    <?php endif; ?>
                                                                </td>
                                                                <td class="column-info"><?= $column['Type'] ?></td>
                                                                <td><?= $column['Null'] ?></td>
                                                                <td><?= $column['Key'] ?></td>
                                                                <td><?= $column['Default'] ?: 'NULL' ?></td>
                                                                <td><?= $column['Extra'] ?></td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>

                                        <!-- Índices -->
                                        <div class="tab-pane fade" id="indexes" role="tabpanel">
                                            <?php if (!empty($tableInfo['indexes'])): ?>
                                                <div class="table-responsive">
                                                    <table class="table table-sm table-striped">
                                                        <thead>
                                                            <tr>
                                                                <th>Nome</th>
                                                                <th>Coluna</th>
                                                                <th>Tipo</th>
                                                                <th>Cardinalidade</th>
                                                                <th>Subparte</th>
                                                                <th>Packed</th>
                                                                <th>Null</th>
                                                                <th>Comment</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php foreach ($tableInfo['indexes'] as $index): ?>
                                                                <tr>
                                                                    <td><?= $index['Key_name'] ?></td>
                                                                    <td><?= $index['Column_name'] ?></td>
                                                                    <td><?= $index['Index_type'] ?></td>
                                                                    <td><?= $index['Cardinality'] ?></td>
                                                                    <td><?= $index['Sub_part'] ?: '-' ?></td>
                                                                    <td><?= $index['Packed'] ?: '-' ?></td>
                                                                    <td><?= $index['Null'] ?></td>
                                                                    <td><?= $index['Comment'] ?: '-' ?></td>
                                                                </tr>
                                                            <?php endforeach; ?>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            <?php else: ?>
                                                <div class="alert alert-info">
                                                    <i class="bi bi-info-circle"></i> Nenhum índice encontrado para esta tabela.
                                                </div>
                                            <?php endif; ?>
                                        </div>

                                        <!-- Chaves Estrangeiras -->
                                        <div class="tab-pane fade" id="foreign-keys" role="tabpanel">
                                            <?php if (!empty($tableInfo['foreign_keys'])): ?>
                                                <div class="table-responsive">
                                                    <table class="table table-sm table-striped">
                                                        <thead>
                                                            <tr>
                                                                <th>Coluna</th>
                                                                <th>Tabela Referenciada</th>
                                                                <th>Coluna Referenciada</th>
                                                                <th>Nome da Constraint</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php foreach ($tableInfo['foreign_keys'] as $fk): ?>
                                                                <tr>
                                                                    <td class="foreign-key"><?= $fk['COLUMN_NAME'] ?></td>
                                                                    <td><?= $fk['REFERENCED_TABLE_NAME'] ?></td>
                                                                    <td><?= $fk['REFERENCED_COLUMN_NAME'] ?></td>
                                                                    <td><?= $fk['CONSTRAINT_NAME'] ?></td>
                                                                </tr>
                                                            <?php endforeach; ?>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            <?php else: ?>
                                                <div class="alert alert-info">
                                                    <i class="bi bi-info-circle"></i> Nenhuma chave estrangeira encontrada para esta tabela.
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="card">
                                <div class="card-body text-center">
                                    <i class="bi bi-info-circle text-muted" style="font-size: 3rem;"></i>
                                    <h5 class="mt-3 text-muted">Selecione uma tabela para ver seus detalhes</h5>
                                    <p class="text-muted">Clique em uma tabela na lista à esquerda para visualizar sua estrutura completa.</p>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 