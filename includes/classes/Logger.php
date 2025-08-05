<?php

/**
 * Logger class for configurable logging levels
 * 
 * Log levels (in order of severity):
 * - ERROR: Critical errors that need immediate attention
 * - WARN: Warning conditions that should be investigated
 * - INFO: General information about application flow
 * - DEBUG: Detailed debug information (only in development)
 * - TRACE: Very detailed trace information (only in development)
 */
class Logger {
    private static $instance = null;
    private $logLevel;
    private $logFile;
    private $context;
    
    // Log level constants
    const ERROR = 0;
    const WARN = 1;
    const INFO = 2;
    const DEBUG = 3;
    const TRACE = 4;
    
    private function __construct() {
        $this->logLevel = $this->getLogLevelFromConfig();
        $this->logFile = $this->getLogFileFromConfig();
        $this->context = $this->getContextFromConfig();
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Get log level from configuration
     */
    private function getLogLevelFromConfig() {
        // Check for environment variable first
        $envLevel = getenv('LOG_LEVEL');
        if ($envLevel !== false) {
            return $this->parseLogLevel($envLevel);
        }
        
        // Check for constant in config
        if (defined('LOG_LEVEL')) {
            return $this->parseLogLevel(LOG_LEVEL);
        }
        
        // Default based on environment
        if (defined('DEBUG_MODE') && DEBUG_MODE) {
            return self::DEBUG;
        }
        
        // Production default
        return self::INFO;
    }
    
    /**
     * Parse log level string to constant
     */
    private function parseLogLevel($level) {
        $level = strtoupper(trim($level));
        switch ($level) {
            case 'ERROR':
                return self::ERROR;
            case 'WARN':
            case 'WARNING':
                return self::WARN;
            case 'INFO':
                return self::INFO;
            case 'DEBUG':
                return self::DEBUG;
            case 'TRACE':
                return self::TRACE;
            default:
                return self::INFO;
        }
    }
    
    /**
     * Get log file path from configuration
     */
    private function getLogFileFromConfig() {
        // Check for environment variable
        $envFile = getenv('LOG_FILE');
        if ($envFile !== false) {
            return $envFile;
        }
        
        // Check for constant
        if (defined('LOG_FILE')) {
            return LOG_FILE;
        }
        
        // Default log file
        return __DIR__ . '/../../logs/app.log';
    }
    
    /**
     * Get context from configuration
     */
    private function getContextFromConfig() {
        $context = [];
        
        // Add environment info
        $context['environment'] = defined('DEBUG_MODE') && DEBUG_MODE ? 'development' : 'production';
        
        // Add application info
        if (defined('APP_NAME')) {
            $context['app'] = APP_NAME;
        }
        
        return $context;
    }
    
    /**
     * Log a message with specified level
     */
    public function log($level, $message, $context = []) {
        if ($level > $this->logLevel) {
            return; // Skip logging if level is higher than configured
        }
        
        $logEntry = $this->formatLogEntry($level, $message, $context);
        $this->writeLog($logEntry);
    }
    
    /**
     * Log error message
     */
    public function error($message, $context = []) {
        $this->log(self::ERROR, $message, $context);
    }
    
    /**
     * Log warning message
     */
    public function warn($message, $context = []) {
        $this->log(self::WARN, $message, $context);
    }
    
    /**
     * Log info message
     */
    public function info($message, $context = []) {
        $this->log(self::INFO, $message, $context);
    }
    
    /**
     * Log debug message
     */
    public function debug($message, $context = []) {
        $this->log(self::DEBUG, $message, $context);
    }
    
    /**
     * Log trace message
     */
    public function trace($message, $context = []) {
        $this->log(self::TRACE, $message, $context);
    }
    
    /**
     * Format log entry
     */
    private function formatLogEntry($level, $message, $context) {
        $timestamp = date('Y-m-d H:i:s');
        $levelName = $this->getLevelName($level);
        $requestId = $this->getRequestId();
        
        $entry = "[{$timestamp}] [{$levelName}] [{$requestId}] {$message}";
        
        // Add context if provided
        if (!empty($context)) {
            $entry .= " | Context: " . json_encode($context);
        }
        
        // Add global context
        if (!empty($this->context)) {
            $entry .= " | Global: " . json_encode($this->context);
        }
        
        return $entry;
    }
    
    /**
     * Get level name from constant
     */
    private function getLevelName($level) {
        switch ($level) {
            case self::ERROR:
                return 'ERROR';
            case self::WARN:
                return 'WARN';
            case self::INFO:
                return 'INFO';
            case self::DEBUG:
                return 'DEBUG';
            case self::TRACE:
                return 'TRACE';
            default:
                return 'UNKNOWN';
        }
    }
    
    /**
     * Generate or get request ID for tracking
     */
    private function getRequestId() {
        if (!isset($_SESSION['request_id'])) {
            $_SESSION['request_id'] = uniqid('req_', true);
        }
        return $_SESSION['request_id'] ?? 'no_session';
    }
    
    /**
     * Write log entry to file
     */
    private function writeLog($entry) {
        // Ensure log directory exists
        $logDir = dirname($this->logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        // Write to file
        file_put_contents($this->logFile, $entry . PHP_EOL, FILE_APPEND | LOCK_EX);
        
        // Also log to error_log if in development
        if (defined('DEBUG_MODE') && DEBUG_MODE) {
            error_log($entry);
        }
    }
    
    /**
     * Get current log level
     */
    public function getLogLevel() {
        return $this->logLevel;
    }
    
    /**
     * Check if a level is enabled
     */
    public function isLevelEnabled($level) {
        return $level <= $this->logLevel;
    }
    
    /**
     * Log with automatic context detection
     */
    public function logWithContext($level, $message, $additionalContext = []) {
        $context = array_merge($this->getAutoContext(), $additionalContext);
        $this->log($level, $message, $context);
    }
    
    /**
     * Get automatic context from current request
     */
    private function getAutoContext() {
        $context = [];
        
        // Add user info if available
        if (function_exists('getCurrentUserId')) {
            $userId = getCurrentUserId();
            if ($userId) {
                $context['user_id'] = $userId;
            }
        }
        
        // Add request info
        $context['method'] = $_SERVER['REQUEST_METHOD'] ?? 'CLI';
        $context['uri'] = $_SERVER['REQUEST_URI'] ?? 'CLI';
        $context['ip'] = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        
        // Add session info if available
        if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION)) {
            $context['session_id'] = session_id();
        }
        
        return $context;
    }
}

// Convenience functions for backward compatibility
function log_error($message, $context = []) {
    Logger::getInstance()->error($message, $context);
}

function log_warn($message, $context = []) {
    Logger::getInstance()->warn($message, $context);
}

function log_info($message, $context = []) {
    Logger::getInstance()->info($message, $context);
}

function log_debug($message, $context = []) {
    Logger::getInstance()->debug($message, $context);
}

function log_trace($message, $context = []) {
    Logger::getInstance()->trace($message, $context);
} 