<?php
/**
 * Fix script for database schema issues
 * Adds missing columns and corrects schema problems
 */

// Load configuration
require_once '../config/config.php';
require_once '../includes/database.php';
require_once '../includes/functions.php';

// Connect to database
$conn = dbConnect();

if (!$conn) {
    die("Erro de conexão: " . mysqli_connect_error());
}

mysqli_set_charset($conn, 'utf8mb4');

// Start output with proper styling
echo "<!DOCTYPE html>
<html>
<head>
    <meta charset='utf-8'>
    <title>Correção do Banco de Dados</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; line-height: 1.6; }
        h1, h2 { color: #333; }
        .success { color: green; }
        .error { color: red; background-color: #ffeeee; padding: 10px; border-left: 4px solid red; margin-bottom: 10px; }
        .warning { color: orange; }
    </style>
</head>
<body>
    <h1>Corrigindo Estrutura da Base de Dados</h1>";

// Function to check if table exists
function table_exists($conn, $table) {
    $sql = "SELECT 1 FROM INFORMATION_SCHEMA.TABLES 
            WHERE TABLE_SCHEMA = '" . DB_NAME . "' 
            AND TABLE_NAME = '$table'";
    
    $result = mysqli_query($conn, $sql);
    return mysqli_num_rows($result) > 0;
}

// Function to check if column exists
function column_exists($conn, $table, $column) {
    $sql = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
            WHERE TABLE_SCHEMA = '" . DB_NAME . "' 
            AND TABLE_NAME = '$table' 
            AND COLUMN_NAME = '$column'";
    
    $result = mysqli_query($conn, $sql);
    return mysqli_num_rows($result) > 0;
}

// Function to get existing columns in a table
function get_table_columns($conn, $table) {
    $columns = [];
    $sql = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
            WHERE TABLE_SCHEMA = '" . DB_NAME . "' 
            AND TABLE_NAME = '$table'";
    
    $result = mysqli_query($conn, $sql);
    while ($row = mysqli_fetch_assoc($result)) {
        $columns[] = $row['COLUMN_NAME'];
    }
    
    return $columns;
}

// Check if boloes table exists
if (table_exists($conn, 'boloes')) {
    echo "<p>Tabela 'boloes' encontrada.</p>";
    
    // Get current columns
    $columns = get_table_columns($conn, 'boloes');
    echo "<p>Colunas existentes: " . implode(", ", $columns) . "</p>";
    
    // Define expected structure
    $expected_columns = [
        'id', 'nome', 'slug', 'descricao', 'data_inicio', 'data_fim', 
        'data_criacao', 'status', 'valor_participacao', 'premio_total',
        'regras', 'max_participantes', 'publico', 'admin_id'
    ];
    
    // Check if the table structure is mostly correct
    $missing_columns = array_diff($expected_columns, $columns);
    $extra_columns = array_diff($columns, $expected_columns);
    
    if (count($missing_columns) > 5) { // If more than 5 columns missing, it's probably the wrong table
        echo "<p class='error'>A tabela 'boloes' existe mas faltam muitas colunas essenciais. Recriando a estrutura completa...</p>";
        
        // Rename the old table to keep data just in case
        $backup_name = 'boloes_backup_' . date('Ymd_His');
        $sql = "RENAME TABLE boloes TO $backup_name";
        
        if (mysqli_query($conn, $sql)) {
            echo "<p class='warning'>Tabela atual renomeada para '$backup_name'.</p>";
            
            // Now recreate the table
            $create_table_sql = "CREATE TABLE boloes (
                id INT AUTO_INCREMENT PRIMARY KEY,
                nome VARCHAR(100) NOT NULL,
                slug VARCHAR(120) NOT NULL UNIQUE,
                descricao TEXT NULL,
                data_inicio DATE NOT NULL,
                data_fim DATE NOT NULL,
                data_criacao DATETIME DEFAULT CURRENT_TIMESTAMP,
                status TINYINT(1) DEFAULT 1,
                valor_participacao DECIMAL(10,2) DEFAULT 0.00,
                premio_total DECIMAL(10,2) DEFAULT 0.00,
                regras TEXT NULL,
                max_participantes INT NULL,
                publico TINYINT(1) DEFAULT 1,
                admin_id INT NOT NULL
            )";
            
            if (mysqli_query($conn, $create_table_sql)) {
                echo "<p class='success'>Tabela 'boloes' recriada com a estrutura correta!</p>";
            } else {
                echo "<p class='error'>Erro ao recriar tabela 'boloes': " . mysqli_error($conn) . "</p>";
            }
        } else {
            echo "<p class='error'>Erro ao renomear tabela: " . mysqli_error($conn) . "</p>";
        }
    } else {
        // Add missing columns
        foreach ($missing_columns as $column) {
            echo "<p>Coluna '$column' não existe. Adicionando...</p>";
            
            $definition = '';
            switch ($column) {
                case 'nome':
                    $definition = "VARCHAR(100) NOT NULL";
                    break;
                case 'slug':
                    $definition = "VARCHAR(120) NOT NULL";
                    break;
                case 'descricao':
                    $definition = "TEXT NULL";
                    break;
                case 'data_inicio':
                    $definition = "DATE NOT NULL DEFAULT CURRENT_DATE";
                    break;
                case 'data_fim':
                    $definition = "DATE NOT NULL DEFAULT (CURRENT_DATE + INTERVAL 30 DAY)";
                    break;
                case 'data_criacao':
                    $definition = "DATETIME DEFAULT CURRENT_TIMESTAMP";
                    break;
                case 'status':
                    $definition = "TINYINT(1) DEFAULT 1";
                    break;
                case 'valor_participacao':
                    $definition = "DECIMAL(10,2) DEFAULT 0.00";
                    break;
                case 'premio_total':
                    $definition = "DECIMAL(10,2) DEFAULT 0.00";
                    break;
                case 'regras':
                    $definition = "TEXT NULL";
                    break;
                case 'max_participantes':
                    $definition = "INT NULL";
                    break;
                case 'publico':
                    $definition = "TINYINT(1) DEFAULT 1";
                    break;
                case 'admin_id':
                    $definition = "INT NOT NULL DEFAULT 1";
                    break;
            }
            
            if (!empty($definition)) {
                $sql = "ALTER TABLE boloes ADD COLUMN $column $definition";
                if (mysqli_query($conn, $sql)) {
                    echo "<p class='success'>Coluna '$column' adicionada com sucesso!</p>";
                } else {
                    echo "<p class='error'>Erro ao adicionar coluna '$column': " . mysqli_error($conn) . "</p>";
                }
            }
        }
        
        // Add unique index on slug if needed
        if (in_array('slug', $missing_columns)) {
            $sql = "ALTER TABLE boloes ADD UNIQUE INDEX (slug)";
            if (mysqli_query($conn, $sql)) {
                echo "<p class='success'>Índice UNIQUE adicionado à coluna 'slug'.</p>";
                
                // Update existing records with slugs based on names
                $sql = "SELECT id, nome FROM boloes";
                $result = mysqli_query($conn, $sql);
                
                if ($result && mysqli_num_rows($result) > 0) {
                    echo "<p>Atualizando registros existentes com slugs...</p>";
                    
                    while ($row = mysqli_fetch_assoc($result)) {
                        $id = $row['id'];
                        $nome = $row['nome'];
                        
                        // Generate slug from name
                        $slug = preg_replace('/[^\p{L}\p{N}]+/u', '-', $nome);
                        $slug = mb_strtolower($slug, 'UTF-8');
                        $slug = trim($slug, '-');
                        
                        // Append ID to ensure uniqueness
                        $slug = $slug . '-' . $id;
                        
                        $update_sql = "UPDATE boloes SET slug = '" . mysqli_real_escape_string($conn, $slug) . "' WHERE id = $id";
                        mysqli_query($conn, $update_sql);
                    }
                    
                    echo "<p class='success'>Registros existentes atualizados com slugs.</p>";
                }
            } else {
                echo "<p class='error'>Erro ao adicionar índice: " . mysqli_error($conn) . "</p>";
            }
        }
    }
} else {
    echo "<p class='warning'>Tabela 'boloes' não existe. Criando...</p>";
    
    // Create the table from scratch
    $sql = "CREATE TABLE boloes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nome VARCHAR(100) NOT NULL,
        slug VARCHAR(120) NOT NULL UNIQUE,
        descricao TEXT NULL,
        data_inicio DATE NOT NULL,
        data_fim DATE NOT NULL,
        data_criacao DATETIME DEFAULT CURRENT_TIMESTAMP,
        status TINYINT(1) DEFAULT 1,
        valor_participacao DECIMAL(10,2) DEFAULT 0.00,
        premio_total DECIMAL(10,2) DEFAULT 0.00,
        regras TEXT NULL,
        max_participantes INT NULL,
        publico TINYINT(1) DEFAULT 1,
        admin_id INT NOT NULL DEFAULT 1
    )";
    
    if (mysqli_query($conn, $sql)) {
        echo "<p class='success'>Tabela 'boloes' criada com sucesso!</p>";
    } else {
        echo "<p class='error'>Erro ao criar tabela 'boloes': " . mysqli_error($conn) . "</p>";
    }
}

// Check for administradores table
if (!table_exists($conn, 'administradores')) {
    echo "<p class='warning'>Tabela 'administradores' não existe. Criando...</p>";
    
    $sql = "CREATE TABLE administradores (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nome VARCHAR(100) NOT NULL,
        email VARCHAR(100) NOT NULL UNIQUE,
        senha VARCHAR(255) NOT NULL,
        status TINYINT(1) DEFAULT 1,
        data_cadastro DATETIME DEFAULT CURRENT_TIMESTAMP,
        ultimo_acesso DATETIME NULL
    )";
    
    if (mysqli_query($conn, $sql)) {
        echo "<p class='success'>Tabela 'administradores' criada com sucesso!</p>";
        
        // Insert default admin
        $sql = "INSERT IGNORE INTO administradores (nome, email, senha, status) 
                VALUES ('Admin', 'admin@bolao.com', '$2y$10$4FQzC/nBpO6jJIgBD0wJBeOSQgXAJojK9dmpWWj/L3jFQw2xBGYV2', 1)";
        
        if (mysqli_query($conn, $sql)) {
            echo "<p class='success'>Administrador padrão inserido.</p>";
        } else {
            echo "<p class='error'>Erro ao inserir administrador padrão: " . mysqli_error($conn) . "</p>";
        }
    } else {
        echo "<p class='error'>Erro ao criar tabela 'administradores': " . mysqli_error($conn) . "</p>";
    }
}

mysqli_close($conn);

echo "
    <h2>Processo de correção concluído!</h2>
    <p>Agora você pode <a href='../admin/boloes.php'>gerenciar bolões</a> ou <a href='../index.php'>voltar para a página inicial</a>.</p>
</body>
</html>";
?> 