<?php
/**
 * Database schema import script
 * Run this script to set up the database tables
 */

// Set maximum execution time to 5 minutes
ini_set('max_execution_time', 300);

// Start output buffering to prevent header issues
ob_start();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Importação de Banco de Dados</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; line-height: 1.6; }
        h1 { color: #333; }
        .success { color: green; }
        .error { color: red; background-color: #ffeeee; padding: 10px; border-left: 4px solid red; margin-bottom: 10px; }
        .query { font-family: monospace; background-color: #f0f0f0; padding: 10px; margin-bottom: 5px; white-space: pre-wrap; }
        .step { margin-bottom: 15px; border-bottom: 1px solid #ddd; padding-bottom: 10px; }
    </style>
</head>
<body>
    <h1>Importação do Banco de Dados - Bolão Football</h1>

<?php
// Manual database creation function
function create_database($conn, $dbname) {
    $sql = "CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
    if (mysqli_query($conn, $sql)) {
        echo "<div class='step'>";
        echo "<span class='success'>✓ Banco de dados '$dbname' criado com sucesso.</span>";
        echo "</div>";
        return true;
    } else {
        echo "<div class='step error'>";
        echo "Erro ao criar o banco de dados: " . mysqli_error($conn);
        echo "</div>";
        return false;
    }
}

// Load configuration 
require_once '../config/config.php';

// Connect to server (without database)
$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS);
if (!$conn) {
    die("<div class='error'>Erro na conexão com o servidor MySQL: " . mysqli_connect_error() . "</div>");
}

// Set charset
mysqli_set_charset($conn, 'utf8mb4');

// Create database manually first
if (!create_database($conn, DB_NAME)) {
    die("<div class='error'>Falha ao criar o banco de dados. O script foi encerrado.</div>");
}

// Select the database
if (!mysqli_select_db($conn, DB_NAME)) {
    die("<div class='error'>Não foi possível selecionar o banco de dados '" . DB_NAME . "': " . mysqli_error($conn) . "</div>");
}

// Read the schema file
$schemaFile = __DIR__ . '/schema.sql';
$sql = file_get_contents($schemaFile);

if (!$sql) {
    die("<div class='error'>Não foi possível ler o arquivo de esquema do banco de dados.</div>");
}

// Remove the database creation part since we already did that
$sql = preg_replace('/CREATE DATABASE.*?;/s', '', $sql);
$sql = preg_replace('/USE.*?;/s', '', $sql);

// Split SQL by semicolons and comments
$queries = preg_split('/;\s*$/m', $sql);

echo "<h2>Executando consultas SQL:</h2>";

// Track tables created
$tables_created = [];
$success = true;

foreach ($queries as $query) {
    // Trim and skip empty queries
    $query = trim($query);
    if (empty($query) || strpos($query, '--') === 0) {
        continue;
    }

    // Extract table name if it's a CREATE TABLE statement
    if (preg_match('/CREATE\s+TABLE\s+IF\s+NOT\s+EXISTS\s+`?(\w+)`?/i', $query, $matches)) {
        $table_name = $matches[1];
    } elseif (preg_match('/CREATE\s+TABLE\s+`?(\w+)`?/i', $query, $matches)) {
        $table_name = $matches[1];
    } else {
        $table_name = "Unknown";
    }

    echo "<div class='step'>";
    
    // Execute the query
    if (mysqli_query($conn, $query)) {
        if ($table_name !== "Unknown") {
            $tables_created[] = $table_name;
            echo "<span class='success'>✓ Tabela '$table_name' criada/modificada com sucesso.</span>";
        } else if (strpos(strtoupper($query), 'INSERT') === 0) {
            echo "<span class='success'>✓ Dados inseridos com sucesso.</span>";
        } else {
            echo "<span class='success'>✓ Consulta executada com sucesso.</span>";
        }
    } else {
        $success = false;
        echo "<div class='error'>Erro: " . mysqli_error($conn) . "</div>";
        echo "<div class='query'>" . htmlspecialchars($query) . "</div>";
    }
    
    echo "</div>";
}

mysqli_close($conn);

if ($success) {
    echo "<h2 class='success'>Banco de dados importado com sucesso!</h2>";
    echo "<p>Tabelas criadas: " . implode(", ", $tables_created) . "</p>";
    echo "<p><a href='../index.php'>Voltar para a página inicial</a></p>";
} else {
    echo "<h2 class='error'>Houve erros durante a importação.</h2>";
    echo "<p>Por favor, corrija os erros e tente novamente.</p>";
}
?>

</body>
</html>
<?php
ob_end_flush();
?> 