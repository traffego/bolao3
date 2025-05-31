<?php
/**
 * Configuration File
 * Contains all system configuration, database connection settings and constants
 */

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'bolao_football');

// Application Configuration
define('APP_NAME', 'Bolão Football');
define('APP_URL', 'http://localhost/bolao3');
define('APP_VERSION', '1.0.0');

// Directory Configuration
define('ROOT_DIR', dirname(__DIR__));
define('PUBLIC_DIR', ROOT_DIR . '/public');
define('TEMPLATE_DIR', ROOT_DIR . '/templates');
define('UPLOAD_DIR', PUBLIC_DIR . '/uploads');

// Session Configuration
define('SESSION_NAME', 'bolao_session');
define('SESSION_LIFETIME', 86400); // 24 hours
define('SESSION_PATH', '/');
define('SESSION_SECURE', false);
define('SESSION_HTTPONLY', true);

// API Configuration
define('API_FOOTBALL_URL', 'https://v3.football.api-sports.io');
define('API_TIMEOUT', 30); // seconds

// Security
define('HASH_COST', 12); // Password hashing cost

// Date and Time
define('DATE_FORMAT', 'd/m/Y');
define('TIME_FORMAT', 'H:i');
define('DATETIME_FORMAT', 'd/m/Y H:i');
define('DEFAULT_TIMEZONE', 'America/Sao_Paulo');

// Pagination
define('ITEMS_PER_PAGE', 10);

// Initialize settings
date_default_timezone_set(DEFAULT_TIMEZONE);

// Configuração de erros - ativado para debug
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

// Log de erros - habilitado
ini_set('log_errors', 1);
ini_set('error_log', ROOT_DIR . '/logs/php_errors.log');

// Iniciar sessão após definir todas as constantes
if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_set_cookie_params(SESSION_LIFETIME, SESSION_PATH, $_SERVER['HTTP_HOST'] ?? '', SESSION_SECURE, SESSION_HTTPONLY);
    session_start();
} 