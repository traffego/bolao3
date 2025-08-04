<?php
/**
 * Configuration File
 * Contains all system configuration, database connection settings and constants
 */

// Definir modo de debug
if (!defined('DEBUG_MODE')) {
    define('DEBUG_MODE', true);
}

// Carregar configurações do banco de dados
require_once __DIR__ . '/database.php';

// Application Configuration
define('APP_NAME', 'Bolão Vitimba');
define('APP_URL', 'https://bolao.traffego.agency');
// define('APP_URL', 'http://localhost/bolao3');
define('APP_VERSION', '1.0.0');

// Webhook Configuration - Dynamic based on APP_URL
define('WEBHOOK_URL', APP_URL . '/api/webhook_pix.php');

// Directory Configuration
define('ROOT_DIR', dirname(__DIR__));
define('PUBLIC_DIR', ROOT_DIR . '/public');
define('TEMPLATE_DIR', ROOT_DIR . '/templates');
define('UPLOAD_DIR', PUBLIC_DIR . '/uploads');

// Session Configuration
define('SESSION_NAME', 'bolao_session');
define('SESSION_LIFETIME', 86400); // 24 hours
define('SESSION_PATH', '/');
define('SESSION_DOMAIN', ''); // Deixar vazio para usar o domínio atual
define('SESSION_SECURE', false); // Temporariamente false para debug
define('SESSION_HTTPONLY', true);
define('SESSION_SAMESITE', 'Lax');

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
    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME,
        'path' => SESSION_PATH,
        'domain' => SESSION_DOMAIN,
        'secure' => SESSION_SECURE,
        'httponly' => SESSION_HTTPONLY,
        'samesite' => SESSION_SAMESITE
    ]);
    session_start();
    
    // Regenerar ID da sessão se não existir
    if (empty($_SESSION['initialized'])) {
        session_regenerate_id(true);
        $_SESSION['initialized'] = true;
        $_SESSION['created'] = time();
    }
    
    // Renovar sessão se estiver próxima de expirar
    if (isset($_SESSION['created']) && (time() - $_SESSION['created'] > SESSION_LIFETIME / 2)) {
        session_regenerate_id(true);
        $_SESSION['created'] = time();
    }
}

// URL Helper Functions
if (!function_exists('app_url')) {
    /**
     * Generate application URL with optional path
     * @param string $path Optional path to append
     * @return string Complete URL
     */
    function app_url($path = '') {
        $url = rtrim(APP_URL, '/');
        if (!empty($path)) {
            $path = ltrim($path, '/');
            $url .= '/' . $path;
        }
        return $url;
    }
}

if (!function_exists('api_football_url')) {
    /**
     * Generate API Football URL with endpoint
     * @param string $endpoint API endpoint
     * @return string Complete API URL
     */
    function api_football_url($endpoint = '') {
        $url = rtrim(API_FOOTBALL_URL, '/');
        if (!empty($endpoint)) {
            $endpoint = ltrim($endpoint, '/');
            $url .= '/' . $endpoint;
        }
        return $url;
    }
}

if (!function_exists('is_localhost')) {
    /**
     * Check if the application is running on localhost
     * @return bool True if localhost, false otherwise
     */
    function is_localhost() {
        return in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', '127.0.0.1']) || 
               strpos($_SERVER['HTTP_HOST'] ?? '', 'localhost:') === 0;
    }
}

// Carregar classes principais
require_once ROOT_DIR . '/includes/classes/NotificacaoManager.php'; 