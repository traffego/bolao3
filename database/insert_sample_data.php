<?php
// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database configuration
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'bolao_football';

echo "Setting up sample data...\n";

// Create connection
$conn = new mysqli($host, $username, $password, $database);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "Connected successfully to database: $database\n\n";

// Check if we have at least one bolao
$result = $conn->query("SELECT id FROM dados_boloes LIMIT 1");
$bolaoId = null;
if ($result && $result->num_rows > 0) {
    $bolaoId = $result->fetch_assoc()['id'];
    echo "Found bolao with ID: $bolaoId\n";
} else {
    // Insert a sample bolao if none exists
    echo "No boloes found, creating a sample one...\n";
    
    $sql = "INSERT INTO boloes (nome, slug, descricao, data_inicio, data_fim, data_criacao, status, admin_id) 
            VALUES ('Bolão Copa do Mundo 2022', 'copa-do-mundo-2022', 'Bolão para a Copa do Mundo FIFA 2022', 
            '2022-11-20', '2022-12-18', NOW(), 'aberto', 1)";
    
    if ($conn->query($sql) === TRUE) {
        $bolaoId = $conn->insert_id;
        echo "Created sample bolao with ID: $bolaoId\n";
    } else {
        echo "Error creating sample bolao: " . $conn->error . "\n";
    }
}

// Check if we have at least one jogador
$result = $conn->query("SELECT id FROM jogador LIMIT 1");
$jogadorId = null;
if ($result && $result->num_rows > 0) {
    $jogadorId = $result->fetch_assoc()['id'];
    echo "Found jogador with ID: $jogadorId\n";
} else {
    // Insert a sample jogador if none exists
    echo "No jogadores found, creating a sample one...\n";
    
    $password = password_hash('123456', PASSWORD_DEFAULT);
    $sql = "INSERT INTO jogador (nome, email, senha, telefone, status) 
            VALUES ('João Silva', 'joao@example.com', '$password', '(11) 98765-4321', 'ativo')";
    
    if ($conn->query($sql) === TRUE) {
        $jogadorId = $conn->insert_id;
        echo "Created sample jogador with ID: $jogadorId\n";
    } else {
        echo "Error creating sample jogador: " . $conn->error . "\n";
    }
}

// Now insert a participation if we have both bolao and jogador
if ($bolaoId && $jogadorId) {
    // Check if participation already exists
    $result = $conn->query("SELECT id FROM participacoes WHERE jogador_id = $jogadorId AND bolao_id = $bolaoId");
    
    if ($result && $result->num_rows > 0) {
        echo "Participation already exists\n";
    } else {
        echo "Creating participation for jogador $jogadorId in bolao $bolaoId...\n";
        
        $sql = "INSERT INTO participacoes (jogador_id, bolao_id, data_entrada, status) 
                VALUES ($jogadorId, $bolaoId, NOW(), 1)";
        
        if ($conn->query($sql) === TRUE) {
            echo "Participation created successfully\n";
        } else {
            echo "Error creating participation: " . $conn->error . "\n";
        }
    }
} else {
    echo "Cannot create participation: missing bolao or jogador\n";
}

// Close connection
$conn->close();
echo "\nDone!\n";
?> 