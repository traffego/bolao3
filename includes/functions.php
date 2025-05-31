<?php
/**
 * General utility functions for the application
 */

/**
 * Redirect to a specific URL
 * 
 * @param string $url URL to redirect to
 * @return void
 */
function redirect($url) {
    header("Location: $url");
    exit;
}

/**
 * Check if the user is logged in
 * 
 * @return bool True if logged in, false otherwise
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

/**
 * Check if the user is an admin
 * 
 * @return bool True if admin, false otherwise
 */
function isAdmin() {
    return isset($_SESSION['admin_id']);
}

/**
 * Get the current user ID
 * 
 * @return int|null User ID or null if not logged in
 */
function getCurrentUserId() {
    return $_SESSION['user_id'] ?? null;
}

/**
 * Get the current admin ID
 * 
 * @return int|null Admin ID or null if not logged in as admin
 */
function getCurrentAdminId() {
    return $_SESSION['admin_id'] ?? null;
}

/**
 * Generate a random string
 * 
 * @param int $length Length of the string
 * @return string Random string
 */
function generateRandomString($length = 10) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $randomString = '';
    
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, strlen($characters) - 1)];
    }
    
    return $randomString;
}

/**
 * Format a date according to the default format
 * 
 * @param string $date Date to format
 * @param string $format Format to use (default: DATE_FORMAT)
 * @return string Formatted date
 */
function formatDate($date, $format = DATE_FORMAT) {
    $dateObj = new DateTime($date);
    return $dateObj->format($format);
}

/**
 * Format a datetime according to the default format
 * 
 * @param string $datetime Datetime to format
 * @param string $format Format to use (default: DATETIME_FORMAT)
 * @return string Formatted datetime
 */
function formatDateTime($datetime, $format = DATETIME_FORMAT) {
    if (empty($datetime)) {
        return 'N/A';
    }
    
    // If the date is already in the correct format, create DateTime object directly
    if (preg_match('/^\d{4}-\d{2}-\d{2}/', $datetime)) {
        $dateObj = new DateTime($datetime);
    } else {
        // Try to parse Brazilian format (dd/mm/yyyy HH:ii)
        $dateObj = DateTime::createFromFormat('d/m/Y H:i', $datetime);
        
        // If that fails, try without time
        if (!$dateObj) {
            $dateObj = DateTime::createFromFormat('d/m/Y', $datetime);
        }
        
        // If that still fails, try MySQL format
        if (!$dateObj) {
            $dateObj = new DateTime($datetime);
        }
    }
    
    if (!$dateObj) {
        return 'Data inválida';
    }
    
    return $dateObj->format($format);
}

/**
 * Format a monetary value
 * 
 * @param float $value Value to format
 * @param bool $withSymbol Whether to include the currency symbol
 * @return string Formatted value
 */
function formatMoney($value, $withSymbol = true) {
    // Handle null values by defaulting to 0
    $value = $value ?? 0;
    return ($withSymbol ? 'R$ ' : '') . number_format($value, 2, ',', '.');
}

/**
 * Sanitize input to prevent XSS
 * 
 * @param string $input Input to sanitize
 * @return string Sanitized input
 */
function sanitize($input) {
    if ($input === null) {
        return '';
    }
    return htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
}

/**
 * Display a flash message
 * 
 * @param string $type Message type (success, danger, warning, info)
 * @param string $message Message to display
 * @return void
 */
function setFlashMessage($type, $message) {
    $_SESSION['flash_message'] = [
        'type' => $type,
        'message' => $message
    ];
}

/**
 * Get flash message and clear it
 * 
 * @return array|null Flash message or null if none
 */
function getFlashMessage() {
    $message = $_SESSION['flash_message'] ?? null;
    unset($_SESSION['flash_message']);
    return $message;
}

/**
 * Get flash message type
 * 
 * @return string Message type or empty string if none
 */
function getFlashMessageType() {
    return $_SESSION['flash_message']['type'] ?? '';
}

/**
 * Display flash messages and clear them
 * 
 * @return void
 */
function displayFlashMessages() {
    $message = getFlashMessage();
    if ($message) {
        echo '<div class="alert alert-' . $message['type'] . ' alert-dismissible fade show" role="alert">';
        echo $message['message'];
        echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>';
        echo '</div>';
    }
}

/**
 * Hash a password
 * 
 * @param string $password Password to hash
 * @return string Hashed password
 */
function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT, ['cost' => HASH_COST]);
}

/**
 * Verify a password against a hash
 * 
 * @param string $password Password to verify
 * @param string $hash Hash to verify against
 * @return bool True if password matches hash, false otherwise
 */
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

/**
 * Get current page URL
 * 
 * @return string Current page URL
 */
function getCurrentUrl() {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
    return $protocol . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
}

/**
 * Check if a string is valid JSON
 * 
 * @param string $string String to check
 * @return bool True if valid JSON, false otherwise
 */
function isValidJson($string) {
    if (!is_string($string)) {
        return false;
    }
    
    if (empty($string)) {
        return false;
    }
    
    json_decode($string);
    return json_last_error() === JSON_ERROR_NONE;
}

/**
 * Get configuration value from the database
 * 
 * @param string $name Configuration name
 * @param mixed $default Default value if configuration not found
 * @return mixed Configuration value or default
 */
function getConfig($name, $default = null) {
    $config = dbFetchOne("SELECT valor FROM configuracoes WHERE nome_configuracao = ?", [$name]);
    
    if (!$config) {
        return $default;
    }
    
    return json_decode($config['valor'], true);
}

/**
 * Save configuration to the database
 * 
 * @param string $name Configuration name
 * @param mixed $value Configuration value
 * @param string $description Configuration description
 * @return bool True on success, false on failure
 */
function saveConfig($name, $value, $description = null) {
    $jsonValue = json_encode($value);
    
    // Check if config exists
    $exists = dbFetchOne("SELECT id FROM configuracoes WHERE nome_configuracao = ?", [$name]);
    
    if ($exists) {
        // Update
        $data = ['valor' => $jsonValue];
        if ($description !== null) {
            $data['descricao'] = $description;
        }
        
        return dbUpdate('configuracoes', $data, 'nome_configuracao = ?', [$name]);
    } else {
        // Insert
        $data = [
            'nome_configuracao' => $name,
            'valor' => $jsonValue,
            'descricao' => $description ?? ''
        ];
        
        return dbInsert('configuracoes', $data) !== false;
    }
}

/**
 * Make a request to the Football API
 * 
 * @param string $endpoint API endpoint
 * @param array $params Query parameters
 * @return array|null API response or null on error
 */
function apiFootballRequest($endpoint, $params = []) {
    $config = getConfig('api_football');
    
    if (!$config || empty($config['api_key'])) {
        return null;
    }
    
    $url = $config['base_url'] . '/' . ltrim($endpoint, '/');
    
    if (!empty($params)) {
        $url .= (strpos($url, '?') !== false ? '&' : '?') . http_build_query($params);
    }
    
    $curl = curl_init();
    
    curl_setopt_array($curl, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => API_TIMEOUT,
        CURLOPT_HTTPHEADER => [
            'X-RapidAPI-Key: ' . $config['api_key'],
            'X-RapidAPI-Host: v3.football.api-sports.io'
        ]
    ]);
    
    $response = curl_exec($curl);
    $err = curl_error($curl);
    
    curl_close($curl);
    
    if ($err) {
        return null;
    }
    
    // Update last request time and count
    $config['last_request'] = date('Y-m-d H:i:s');
    saveConfig('api_football', $config);
    
    return json_decode($response, true);
}

/**
 * Calculate points based on prediction and result
 * 
 * @param int $predictedHome Predicted home score
 * @param int $predictedAway Predicted away score
 * @param int $actualHome Actual home score
 * @param int $actualAway Actual away score
 * @return int Points earned
 */
function calculatePoints($predictedHome, $predictedAway, $actualHome, $actualAway) {
    $scoring = getConfig('pontuacao', [
        'resultado_exato' => 5,
        'vencedor_correto' => 2,
        'empate_correto' => 2
    ]);
    
    // Exact result
    if ($predictedHome == $actualHome && $predictedAway == $actualAway) {
        return $scoring['resultado_exato'];
    }
    
    // Correct winner or draw
    $predictedResult = $predictedHome <=> $predictedAway; // -1: home win, 0: draw, 1: away win
    $actualResult = $actualHome <=> $actualAway;
    
    if ($predictedResult == $actualResult) {
        return $predictedResult == 0 ? $scoring['empate_correto'] : $scoring['vencedor_correto'];
    }
    
    return 0;
}

/**
 * Update ranking for a specific bolao
 * 
 * @param int $bolaoId Bolao ID
 * @return bool True on success, false on failure
 */
function updateRanking($bolaoId) {
    // Get all players in this bolao
    $sql = "SELECT DISTINCT p.jogador_id FROM palpites p WHERE p.bolao_id = ?";
    $jogadores = dbFetchAll($sql, [$bolaoId]);
    
    if (empty($jogadores)) {
        return false;
    }
    
    // Start transaction
    dbBeginTransaction();
    
    try {
        // Update points for each player
        foreach ($jogadores as $jogador) {
            $jogadorId = $jogador['jogador_id'];
            
            // Calculate total points
            $sql = "SELECT SUM(pontos_obtidos) as total_pontos 
                    FROM palpites 
                    WHERE jogador_id = ? AND bolao_id = ?";
            $result = dbFetchOne($sql, [$jogadorId, $bolaoId]);
            $totalPoints = $result ? (int) $result['total_pontos'] : 0;
            
            // Update or insert ranking
            $exists = dbFetchOne("SELECT id FROM ranking WHERE bolao_id = ? AND jogador_id = ?", 
                                [$bolaoId, $jogadorId]);
            
            if ($exists) {
                dbUpdate('ranking', ['pontos_totais' => $totalPoints], 
                         'bolao_id = ? AND jogador_id = ?', [$bolaoId, $jogadorId]);
            } else {
                dbInsert('ranking', [
                    'bolao_id' => $bolaoId,
                    'jogador_id' => $jogadorId,
                    'pontos_totais' => $totalPoints,
                    'posicao' => 0,
                    'premio' => 0
                ]);
            }
        }
        
        // Update positions
        $sql = "SET @pos := 0;
                UPDATE ranking r
                JOIN (
                    SELECT id, @pos := @pos + 1 AS pos
                    FROM ranking
                    WHERE bolao_id = ?
                    ORDER BY pontos_totais DESC, id ASC
                ) AS t ON r.id = t.id
                SET r.posicao = t.pos
                WHERE r.bolao_id = ?";
        
        dbQuery($sql, [$bolaoId, $bolaoId]);
        
        // Commit transaction
        dbCommit();
        return true;
    } catch (Exception $e) {
        // Rollback on error
        dbRollback();
        return false;
    }
}

/**
 * Update a specific key in a configuration value without overriding other settings
 * 
 * @param string $name Configuration name
 * @param string $key Key to update within the configuration
 * @param mixed $value New value for the key
 * @param bool $createIfNotExists Create the configuration if it doesn't exist
 * @return bool True on success, false on failure
 */
function updateConfigurationValue($name, $key, $value, $createIfNotExists = true) {
    // Get current configuration
    $config = getConfig($name);
    
    if (!$config && !$createIfNotExists) {
        return false;
    }
    
    // Initialize empty array if config doesn't exist
    if (!$config) {
        $config = [];
    }
    
    // Update specific key
    $config[$key] = $value;
    
    // Save updated configuration
    return saveConfig($name, $config);
}

/**
 * Generate slug from string
 * 
 * @param string $string String to convert
 * @return string Slug
 */
function slugify($string) {
    $string = preg_replace('/[^\p{L}\p{N}]+/u', '-', $string);
    $string = mb_strtolower($string, 'UTF-8');
    $string = trim($string, '-');
    return $string;
}

/**
 * Function to fetch data from API Football
 * 
 * @param string $endpoint API endpoint
 * @param array $params Query parameters
 * @return array|null API response or null on failure
 */
function fetchApiFootballData($endpoint, $params = []) {
    $response = apiFootballRequest($endpoint, $params);
    if ($response && isset($response['response'])) {
        return $response['response'];
    }
    return null;
} 