<?php
/**
 * Bolão Football - Setup Database Script
 * 
 * Este script configura o banco de dados para o Bolão Football
 * Ele cria todas as tabelas necessárias e insere dados de demonstração
 */

// Definir reporting de erros
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Configuração do banco de dados
$host = 'localhost';
$username = 'root';
$password = '';

echo "=======================================================\n";
echo "     Bolão Football - Setup de Banco de Dados          \n";
echo "=======================================================\n\n";

// Criar conexão inicial (sem banco específico)
echo "Conectando ao servidor MySQL...\n";
$conn = new mysqli($host, $username, $password);

// Verificar conexão
if ($conn->connect_error) {
    die("Falha na conexão: " . $conn->connect_error . "\n");
}
echo "Conexão estabelecida com sucesso!\n\n";

// Criar o banco de dados
$dbName = 'bolao_football';
echo "Criando banco de dados '$dbName'...\n";

$sql = "CREATE DATABASE IF NOT EXISTS $dbName CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
if ($conn->query($sql) === TRUE) {
    echo "Banco de dados criado com sucesso ou já existe!\n";
} else {
    die("Erro ao criar banco de dados: " . $conn->error . "\n");
}

// Selecionar o banco de dados
$conn->select_db($dbName);
echo "Banco de dados '$dbName' selecionado.\n\n";

// Ler o conteúdo do arquivo SQL
$sqlFile = __DIR__ . '/setup_database.sql';
echo "Lendo arquivo SQL '$sqlFile'...\n";

if (!file_exists($sqlFile)) {
    die("Erro: O arquivo '$sqlFile' não foi encontrado.\n");
}

$sqlContent = file_get_contents($sqlFile);
if (!$sqlContent) {
    die("Erro ao ler o arquivo SQL.\n");
}

// Remover a primeira linha que cria o banco e a linha USE
$sqlContent = preg_replace('/CREATE DATABASE.*?;/s', '', $sqlContent);
$sqlContent = preg_replace('/USE.*?;/s', '', $sqlContent);

// Dividir o conteúdo em comandos individuais
echo "Executando comandos SQL...\n";
$sqlCommands = explode(';', $sqlContent);
$successCount = 0;
$errorCount = 0;

foreach ($sqlCommands as $sql) {
    $sql = trim($sql);
    
    if (empty($sql)) continue;
    
    if ($conn->query($sql) === TRUE) {
        $successCount++;
    } else {
        echo "Erro ao executar comando SQL: " . $conn->error . "\n";
        echo "Comando: " . substr($sql, 0, 150) . "...\n\n";
        $errorCount++;
    }
}

echo "Execução concluída! $successCount comandos executados com sucesso, $errorCount erros.\n\n";

// Verificar tabelas criadas
echo "Verificando tabelas criadas:\n";
$result = $conn->query("SHOW TABLES");
if ($result) {
    $tableCount = $result->num_rows;
    echo "Total de tabelas: $tableCount\n";
    
    while ($row = $result->fetch_row()) {
        echo "- " . $row[0] . "\n";
    }
} else {
    echo "Erro ao listar tabelas: " . $conn->error . "\n";
}

// Verificar dados
echo "\nVerificando dados inseridos:\n";

// Verificar administradores
$result = $conn->query("SELECT COUNT(*) as total FROM administrador");
if ($result) {
    $row = $result->fetch_assoc();
    echo "- Administradores: " . $row['total'] . "\n";
}

// Verificar jogadores
$result = $conn->query("SELECT COUNT(*) as total FROM jogador");
if ($result) {
    $row = $result->fetch_assoc();
    echo "- Jogadores: " . $row['total'] . "\n";
}

// Verificar bolões
$result = $conn->query("SELECT COUNT(*) as total FROM dados_boloes");
if ($result) {
    $row = $result->fetch_assoc();
    echo "- Bolões: " . $row['total'] . "\n";
}

// Verificar jogos
$result = $conn->query("SELECT COUNT(*) as total FROM jogos");
if ($result) {
    $row = $result->fetch_assoc();
    echo "- Jogos: " . $row['total'] . "\n";
}

// Verificar participações
$result = $conn->query("SELECT COUNT(*) as total FROM participacoes");
if ($result) {
    $row = $result->fetch_assoc();
    echo "- Participações: " . $row['total'] . "\n";
}

// Verificar palpites
$result = $conn->query("SELECT COUNT(*) as total FROM palpites");
if ($result) {
    $row = $result->fetch_assoc();
    echo "- Palpites: " . $row['total'] . "\n";
}

// Fechar conexão
$conn->close();

echo "\n=======================================================\n";
echo "Setup concluído com sucesso! O banco de dados '$dbName' está pronto.\n";
echo "=======================================================\n\n";

echo "Credenciais de acesso ao painel admin:\n";
echo "- Email: admin@bolao.com\n";
echo "- Senha: admin123\n\n";

echo "Credenciais de acesso como jogador (qualquer um dos jogadores):\n";
echo "- Email: joao@email.com (ou qualquer outro email de jogador)\n";
echo "- Senha: 123456\n\n";

echo "Acesse o sistema pelo navegador para começar a usar!\n";
?> 