<?php
/**
 * Example logging configuration
 * 
 * Copy this file to your environment-specific config and modify as needed.
 * 
 * Log levels (in order of severity):
 * - ERROR: Critical errors that need immediate attention
 * - WARN: Warning conditions that should be investigated  
 * - INFO: General information about application flow
 * - DEBUG: Detailed debug information (only in development)
 * - TRACE: Very detailed trace information (only in development)
 */

// Development environment - verbose logging
if (defined('DEBUG_MODE') && DEBUG_MODE) {
    define('LOG_LEVEL', 'DEBUG');  // Show all logs including debug
    define('LOG_FILE', ROOT_DIR . '/logs/app_dev.log');
} else {
    // Production environment - minimal logging
    define('LOG_LEVEL', 'INFO');   // Only show INFO, WARN, and ERROR
    define('LOG_FILE', ROOT_DIR . '/logs/app.log');
}

// Alternative: Set via environment variables
// LOG_LEVEL=ERROR    // Only critical errors
// LOG_LEVEL=WARN     // Warnings and errors
// LOG_LEVEL=INFO     // General info, warnings, and errors (recommended for production)
// LOG_LEVEL=DEBUG    // Debug info and above (development)
// LOG_LEVEL=TRACE    // All logs (very verbose, development only)

// Example environment-specific configurations:

// Local development
// define('LOG_LEVEL', 'TRACE');
// define('LOG_FILE', ROOT_DIR . '/logs/app_local.log');

// Staging environment  
// define('LOG_LEVEL', 'DEBUG');
// define('LOG_FILE', ROOT_DIR . '/logs/app_staging.log');

// Production environment
// define('LOG_LEVEL', 'INFO');
// define('LOG_FILE', ROOT_DIR . '/logs/app_prod.log');

// Emergency/critical only
// define('LOG_LEVEL', 'ERROR');
// define('LOG_FILE', ROOT_DIR . '/logs/app_critical.log'); 