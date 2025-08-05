<?php
/**
 * Migration script to convert error_log calls to new logging system
 * 
 * Usage: php scripts/migrate_logging.php [directory]
 * 
 * This script will scan PHP files and suggest replacements for error_log calls.
 */

if ($argc < 2) {
    echo "Usage: php scripts/migrate_logging.php [directory]\n";
    echo "Example: php scripts/migrate_logging.php includes/\n";
    exit(1);
}

$directory = $argv[1];

if (!is_dir($directory)) {
    echo "Error: Directory '$directory' does not exist.\n";
    exit(1);
}

echo "Scanning directory: $directory\n";
echo "Looking for error_log calls to migrate...\n\n";

$files = findPhpFiles($directory);
$totalFiles = count($files);
$processedFiles = 0;
$totalErrorLogs = 0;

foreach ($files as $file) {
    $content = file_get_contents($file);
    $lines = explode("\n", $content);
    $hasErrorLogs = false;
    $suggestions = [];
    
    foreach ($lines as $lineNum => $line) {
        $lineNum++; // Convert to 1-based line numbers
        
        if (preg_match('/error_log\s*\(\s*["\']([^"\']+)["\']/', $line, $matches)) {
            $hasErrorLogs = true;
            $message = $matches[1];
            $suggestion = suggestReplacement($message, $line);
            
            $suggestions[] = [
                'line' => $lineNum,
                'original' => trim($line),
                'suggestion' => $suggestion
            ];
        }
    }
    
    if ($hasErrorLogs) {
        $processedFiles++;
        echo "File: $file\n";
        echo "Found " . count($suggestions) . " error_log calls:\n";
        
        foreach ($suggestions as $suggestion) {
            echo "  Line {$suggestion['line']}:\n";
            echo "    Original: {$suggestion['original']}\n";
            echo "    Suggested: {$suggestion['suggestion']}\n";
            echo "\n";
        }
        
        $totalErrorLogs += count($suggestions);
        echo str_repeat("-", 80) . "\n\n";
    }
}

echo "Migration Summary:\n";
echo "  Files scanned: $totalFiles\n";
echo "  Files with error_log: $processedFiles\n";
echo "  Total error_log calls: $totalErrorLogs\n";
echo "\n";
echo "Next steps:\n";
echo "1. Review the suggestions above\n";
echo "2. Manually replace error_log calls with appropriate logging functions\n";
echo "3. Test the application to ensure logging works correctly\n";
echo "4. Update configuration to set appropriate LOG_LEVEL for your environment\n";

function findPhpFiles($directory) {
    $files = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS)
    );
    
    foreach ($iterator as $file) {
        if ($file->getExtension() === 'php') {
            $files[] = $file->getPathname();
        }
    }
    
    return $files;
}

function suggestReplacement($message, $originalLine) {
    $message = strtolower($message);
    
    // Determine log level based on message content
    if (strpos($message, 'erro') !== false || strpos($message, 'error') !== false || strpos($message, 'exception') !== false) {
        $level = 'log_error';
    } elseif (strpos($message, 'warn') !== false || strpos($message, 'warning') !== false) {
        $level = 'log_warn';
    } elseif (strpos($message, 'debug') !== false) {
        $level = 'log_debug';
    } elseif (strpos($message, 'trace') !== false || strpos($message, 'verbose') !== false) {
        $level = 'log_trace';
    } else {
        $level = 'log_info';
    }
    
    // Extract potential context from the message
    $context = extractContext($message);
    
    // Create the replacement
    if (empty($context)) {
        return str_replace('error_log(', $level . '(', $originalLine);
    } else {
        $contextStr = json_encode($context, JSON_PRETTY_PRINT);
        $newMessage = extractCleanMessage($message);
        return "        $level(\"$newMessage\", $contextStr);";
    }
}

function extractContext($message) {
    $context = [];
    
    // Look for common patterns
    if (preg_match('/user_id[:\s]+(\d+)/i', $message, $matches)) {
        $context['user_id'] = (int)$matches[1];
    }
    
    if (preg_match('/valor[:\s]+([\d.]+)/i', $message, $matches)) {
        $context['valor'] = (float)$matches[1];
    }
    
    if (preg_match('/http[:\s]+(\d+)/i', $message, $matches)) {
        $context['http_code'] = (int)$matches[1];
    }
    
    if (preg_match('/txid[:\s]+([a-zA-Z0-9]+)/i', $message, $matches)) {
        $context['txid'] = $matches[1];
    }
    
    if (preg_match('/path[:\s]+([^\s]+)/i', $message, $matches)) {
        $context['path'] = $matches[1];
    }
    
    return $context;
}

function extractCleanMessage($message) {
    // Remove common prefixes and suffixes
    $message = preg_replace('/^(EFIPIX\s+(DEBUG|ERROR|INFO)\s*-\s*)/i', '', $message);
    $message = preg_replace('/^(DEPOSITO\s+(DEBUG|ERROR|INFO)\s*-\s*)/i', '', $message);
    $message = preg_replace('/^(PAGAMENTO\s+(DEBUG|ERROR|INFO)\s*-\s*)/i', '', $message);
    
    // Remove common patterns that should be context
    $message = preg_replace('/\s+user_id[:\s]+\d+/i', '', $message);
    $message = preg_replace('/\s+valor[:\s]+[\d.]+/i', '', $message);
    $message = preg_replace('/\s+http[:\s]+\d+/i', '', $message);
    $message = preg_replace('/\s+txid[:\s]+[a-zA-Z0-9]+/i', '', $message);
    $message = preg_replace('/\s+path[:\s]+[^\s]+/i', '', $message);
    
    return trim($message);
} 