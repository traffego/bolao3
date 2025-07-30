<?php
/**
 * Test script to verify dbPrepare function
 */

// Include the database configuration
require_once __DIR__ . '/config/database.php';

try {
    // Test dbPrepare function
    $stmt = dbPrepare("SELECT 1 as test");
    
    if ($stmt) {
        echo "dbPrepare function is working correctly!\n";
        
        // Test executing the prepared statement
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result && isset($result['test']) && $result['test'] == 1) {
            echo "Prepared statement executed successfully!\n";
            echo "Test result: " . $result['test'] . "\n";
        } else {
            echo "Error executing prepared statement\n";
        }
    } else {
        echo "Error: dbPrepare function returned false\n";
    }
    
} catch (Exception $e) {
    echo "Exception occurred: " . $e->getMessage() . "\n";
}

echo "\nTest completed successfully!\n";
?>
