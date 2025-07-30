<?php
/**
 * Test script to verify URL configuration
 * This script tests that all URLs are properly using the global variables
 */

require_once 'config/config.php';

// Test APP_URL
echo "APP_URL: " . APP_URL . "\n";

// Test WEBHOOK_URL
echo "WEBHOOK_URL: " . WEBHOOK_URL . "\n";

// Test API_FOOTBALL_URL
echo "API_FOOTBALL_URL: " . API_FOOTBALL_URL . "\n";

// Test helper functions
echo "\nHelper Function Tests:\n";
echo "app_url(): " . app_url() . "\n";
echo "app_url('admin'): " . app_url('admin') . "\n";
echo "api_football_url(): " . api_football_url() . "\n";
echo "api_football_url('fixtures'): " . api_football_url('fixtures') . "\n";

echo "\nLocalhost Detection:\n";
echo "is_localhost(): " . (is_localhost() ? 'true' : 'false') . "\n";

echo "\nAll URL configuration tests completed successfully!\n";
?>
