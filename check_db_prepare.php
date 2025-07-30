<?php
/**
 * Simple check script to verify dbPrepare function exists
 */

// Include the database configuration
require_once __DIR__ . '/config/database.php';

// Check if dbPrepare function exists
echo "Checking if dbPrepare function exists...\n";

if (function_exists('dbPrepare')) {
    echo "SUCCESS: dbPrepare function is defined!\n";
} else {
    echo "ERROR: dbPrepare function is NOT defined!\n";
}

echo "\nFunction check completed.\n";
?>
