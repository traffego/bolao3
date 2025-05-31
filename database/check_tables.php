<?php
// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database configuration
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'bolao_football';

echo "Checking database structure...\n";

// Create connection
$conn = new mysqli($host, $username, $password, $database);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "Connected successfully to database: $database\n\n";

// List all tables
echo "Tables in database:\n";
$result = $conn->query("SHOW TABLES");
$tables = [];
while ($row = $result->fetch_row()) {
    $tableName = $row[0];
    $tables[] = $tableName;
    echo "- $tableName\n";
}

echo "\nChecking specific tables needed for bolao.php:\n";

// Check administrador table
if (in_array('administrador', $tables)) {
    echo "\nTable 'administrador' exists, checking structure:\n";
    $result = $conn->query("DESCRIBE administrador");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            echo "  - " . $row['Field'] . " (" . $row['Type'] . ")\n";
        }
    } else {
        echo "Error getting table structure: " . $conn->error . "\n";
    }
} else {
    echo "ERROR: 'administrador' table doesn't exist\n";
}

// Check boloes table
if (in_array('boloes', $tables)) {
    echo "\nTable 'boloes' exists, checking structure:\n";
    $result = $conn->query("DESCRIBE boloes");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            echo "  - " . $row['Field'] . " (" . $row['Type'] . ")\n";
        }
    } else {
        echo "Error getting table structure: " . $conn->error . "\n";
    }
} else {
    echo "ERROR: 'boloes' table doesn't exist\n";
}

// Check participacoes table
if (in_array('participacoes', $tables)) {
    echo "\nTable 'participacoes' exists, checking structure:\n";
    $result = $conn->query("DESCRIBE participacoes");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            echo "  - " . $row['Field'] . " (" . $row['Type'] . ")\n";
        }
    } else {
        echo "Error getting table structure: " . $conn->error . "\n";
    }
} else {
    echo "ERROR: 'participacoes' table doesn't exist\n";
}

// Close connection
$conn->close();
echo "\nDone!\n";
?> 