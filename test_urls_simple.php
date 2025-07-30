<?php
/**
 * Simple test script to verify URL configuration
 * This script tests that all URL constants are properly defined
 */

// Define required constants for testing
if (!defined('APP_URL')) {
    define('APP_URL', 'https://bolao.traffego.agency');
}

if (!defined('API_FOOTBALL_URL')) {
    define('API_FOOTBALL_URL', 'https://v3.football.api-sports.io');
}

// Test APP_URL
echo "APP_URL: " . APP_URL . "\n";

// Test WEBHOOK_URL (dynamically generated)
echo "WEBHOOK_URL: " . APP_URL . '/api/webhook_pix.php' . "\n";

// Test API_FOOTBALL_URL
echo "API_FOOTBALL_URL: " . API_FOOTBALL_URL . "\n";

// Test helper functions
echo "\nHelper Function Tests:\n";

// app_url function
echo "app_url(): " . rtrim(APP_URL, '/') . "\n";
echo "app_url('admin'): " . rtrim(APP_URL, '/') . '/admin' . "\n";

// api_football_url function
echo "api_football_url(): " . rtrim(API_FOOTBALL_URL, '/') . "\n";
echo "api_football_url('fixtures'): " . rtrim(API_FOOTBALL_URL, '/') . '/fixtures' . "\n";

echo "\nAll URL configuration tests completed successfully!\n";
?>
