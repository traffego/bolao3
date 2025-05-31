<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

// Verificar se o usuário é admin
if (!isAdmin()) {
    die('Acesso negado');
}

try {
    // Obter conexão com o banco de dados
    $pdo = getPDO();
    
    // Ler o arquivo SQL
    $sql = file_get_contents(__DIR__ . '/../sql/create_configuracoes_table.sql');
    
    // Executar o SQL
    $result = $pdo->exec($sql);
    
    // Verificar se a tabela foi criada/existe
    $checkStmt = $pdo->query("SHOW TABLES LIKE 'configuracoes'");
    if ($checkStmt->rowCount() > 0) {
        echo "Tabela 'configuracoes' está pronta para uso.<br>";
        
        // Verificar estrutura da tabela
        $columnsStmt = $pdo->query("SHOW COLUMNS FROM configuracoes");
        $columns = $columnsStmt->fetchAll(PDO::FETCH_COLUMN);
        echo "Colunas existentes: " . implode(", ", $columns) . "<br>";
        
        // Verificar registros existentes
        $countStmt = $pdo->query("SELECT COUNT(*) as total FROM configuracoes");
        $count = $countStmt->fetch(PDO::FETCH_ASSOC);
        echo "Total de registros: " . $count['total'] . "<br>";
        
        // Listar configurações existentes
        $configsStmt = $pdo->query("SELECT nome_configuracao, categoria FROM configuracoes");
        echo "Configurações existentes:<br>";
        while ($config = $configsStmt->fetch(PDO::FETCH_ASSOC)) {
            echo "- {$config['nome_configuracao']} ({$config['categoria']})<br>";
        }
    } else {
        echo "Erro: A tabela 'configuracoes' não pôde ser criada.";
    }
    
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage();
} 