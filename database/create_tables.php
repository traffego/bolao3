<?php
// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database configuration
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'bolao_football';

echo "Attempting to connect to database...\n";

// Create connection
$conn = new mysqli($host, $username, $password, $database);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "Connected successfully to database: $database\n";

// List all tables
echo "\nList of tables in database:\n";
$result = $conn->query("SHOW TABLES");
$tables = [];
while ($row = $result->fetch_row()) {
    echo "- " . $row[0] . "\n";
    $tables[] = $row[0];
}

// Check if required tables exist
if (!in_array('jogador', $tables)) {
    echo "\nERROR: 'jogador' table doesn't exist. Creating it first...\n";
    
    $sql = "
    CREATE TABLE IF NOT EXISTS jogador (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nome VARCHAR(100) NOT NULL,
        email VARCHAR(100) NOT NULL UNIQUE,
        senha VARCHAR(255) NOT NULL,
        telefone VARCHAR(20) NULL,
        status VARCHAR(10) DEFAULT 'ativo',
        data_cadastro DATETIME DEFAULT CURRENT_TIMESTAMP,
        ultimo_acesso DATETIME NULL,
        token_recuperacao VARCHAR(100) NULL,
        token_expira DATETIME NULL,
        saldo DECIMAL(10,2) DEFAULT 0.00
    )";
    
    if ($conn->query($sql) === TRUE) {
        echo "Table 'jogador' created successfully\n";
    } else {
        echo "Error creating table 'jogador': " . $conn->error . "\n";
    }
}

if (!in_array('boloes', $tables)) {
    echo "\nERROR: 'boloes' table doesn't exist. Creating it first...\n";
    
    $sql = "
    CREATE TABLE IF NOT EXISTS boloes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nome VARCHAR(100) NOT NULL,
        slug VARCHAR(120) NOT NULL UNIQUE,
        descricao TEXT NULL,
        data_inicio DATE NOT NULL,
        data_fim DATE NOT NULL,
        data_criacao DATETIME DEFAULT CURRENT_TIMESTAMP,
        status VARCHAR(20) DEFAULT 'aberto',
        valor_participacao DECIMAL(10,2) DEFAULT 0.00,
        premio_total DECIMAL(10,2) DEFAULT 0.00,
        regras TEXT NULL,
        max_participantes INT NULL,
        publico TINYINT(1) DEFAULT 1,
        admin_id INT NOT NULL
    )";
    
    if ($conn->query($sql) === TRUE) {
        echo "Table 'boloes' created successfully\n";
    } else {
        echo "Error creating table 'boloes': " . $conn->error . "\n";
    }
}

// Create participacoes table without foreign keys first
echo "\nCreating 'participacoes' table...\n";

// SQL to create participacoes table without foreign keys
$sql = "
CREATE TABLE IF NOT EXISTS participacoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    jogador_id INT NOT NULL,
    bolao_id INT NOT NULL,
    data_entrada DATETIME DEFAULT CURRENT_TIMESTAMP,
    status TINYINT(1) DEFAULT 1,
    UNIQUE KEY (jogador_id, bolao_id)
)";

echo "Executing SQL to create table...\n";
echo "SQL: " . $sql . "\n";

// Execute SQL
if ($conn->query($sql) === TRUE) {
    echo "Table 'participacoes' created successfully\n";
} else {
    echo "Error creating table: " . $conn->error . "\n";
}

// List all tables again
echo "\nUpdated list of tables in database:\n";
$result = $conn->query("SHOW TABLES");
while ($row = $result->fetch_row()) {
    echo "- " . $row[0] . "\n";
}

// Close connection
$conn->close();
echo "Done!\n";
?> 