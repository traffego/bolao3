<?php
/**
 * General utility functions for the application
 */

// Incluir gerenciador de afiliação
require_once __DIR__ . '/referral_manager.php';

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
 * Check if the current user is an active affiliate
 * 
 * @return bool True if user is logged in and is an active affiliate, false otherwise
 */
function isActiveAffiliate() {
    if (!isLoggedIn()) {
        return false;
    }
    
    $userId = getCurrentUserId();
    $user = dbFetchOne(
        "SELECT afiliado_ativo FROM jogador WHERE id = ?", 
        [$userId]
    );
    
    return $user && $user['afiliado_ativo'] === 'ativo';
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
 * @param string|null $date Date to format
 * @param string $format Format to use (default: DATE_FORMAT)
 * @return string Formatted date
 */
function formatDate($date, $format = DATE_FORMAT) {
    if (empty($date)) {
        return 'N/A';
    }
    
    try {
        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $date)) {
            $dateObj = new DateTime($date);
        } else {
            // Try to parse Brazilian format (dd/mm/yyyy)
            $dateObj = DateTime::createFromFormat('d/m/Y', $date);
            
            // If that fails, try MySQL format
            if (!$dateObj) {
                $dateObj = new DateTime($date);
            }
        }
        
        if (!$dateObj) {
            return 'Data inválida';
        }
        
        return $dateObj->format($format);
    } catch (Exception $e) {
        return 'Data inválida';
    }
}

/**
 * Format a time according to the default format
 * 
 * @param string|null $datetime Datetime to format
 * @param string $format Format to use (default: 'H:i')
 * @return string Formatted time
 */
function formatTime($datetime, $format = 'H:i') {
    if (empty($datetime)) {
        return 'N/A';
    }
    
    try {
        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $datetime)) {
            $dateObj = new DateTime($datetime);
        } else {
            // Try to parse Brazilian format first
            $dateObj = DateTime::createFromFormat('d/m/Y H:i', $datetime);
            
            // If that fails, try MySQL format
            if (!$dateObj) {
                $dateObj = new DateTime($datetime);
            }
        }
        
        if (!$dateObj) {
            return 'N/A';
        }
        
        return $dateObj->format($format);
    } catch (Exception $e) {
        return 'N/A';
    }
}

/**
 * Format a datetime according to the default format
 * 
 * @param string|null $datetime Datetime to format
 * @param string $format Format to use (default: DATETIME_FORMAT)
 * @return string Formatted datetime
 */
function formatDateTime($datetime, $format = DATETIME_FORMAT) {
    if (empty($datetime)) {
        return 'N/A';
    }
    
    try {
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
    } catch (Exception $e) {
        return 'Data inválida';
    }
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
 * Nova política: 1 acerto = 1 ponto (sempre)
 * 
 * @param int $predictedHome Predicted home score
 * @param int $predictedAway Predicted away score
 * @param int $actualHome Actual home score
 * @param int $actualAway Actual away score
 * @return int Points earned (1 for correct prediction, 0 for incorrect)
 */
function calculatePoints($predictedHome, $predictedAway, $actualHome, $actualAway) {
    // Exact result - 1 point
    if ($predictedHome == $actualHome && $predictedAway == $actualAway) {
        return 1;
    }
    
    // Correct winner or draw - 1 point
    $predictedResult = $predictedHome <=> $predictedAway; // -1: home win, 0: draw, 1: away win
    $actualResult = $actualHome <=> $actualAway;
    
    if ($predictedResult == $actualResult) {
        return 1;
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
        
        // Update positions using a single UPDATE query
        $sql = "UPDATE ranking r1
                JOIN (
                    SELECT id,
                           @pos := @pos + 1 AS new_position
                    FROM (SELECT id, pontos_totais 
                          FROM ranking 
                          WHERE bolao_id = ?
                          ORDER BY pontos_totais DESC, id ASC) r2,
                    (SELECT @pos := 0) p
                ) r3 ON r1.id = r3.id
                SET r1.posicao = r3.new_position
                WHERE r1.bolao_id = ?";
        
        // Execute the position update query
        $success = dbExecute($sql, [$bolaoId, $bolaoId]);
        
        if (!$success) {
            throw new Exception('Failed to update positions');
        }
        
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

/**
 * Calcular e registrar comissão de afiliado quando um depósito é aprovado
 * 
 * @param int $transacaoId ID da transação de depósito aprovada
 * @return bool True se a comissão foi calculada e registrada com sucesso, false caso contrário
 */
function calculateAffiliateCommission($transacaoId) {
    try {
        // Buscar dados da transação de depósito
        $transacao = dbFetchOne(
            "SELECT t.*, c.jogador_id 
             FROM transacoes t 
             INNER JOIN contas c ON t.conta_id = c.id 
             WHERE t.id = ? AND t.tipo = 'deposito' AND t.status = 'aprovado'",
            [$transacaoId]
        );
        
        if (!$transacao) {
            error_log("Transação de depósito não encontrada ou não aprovada: {$transacaoId}");
            return false;
        }
        
        // Buscar dados do jogador que fez o depósito
        $jogadorIndicado = dbFetchOne(
            "SELECT id, ref_indicacao FROM jogador WHERE id = ?",
            [$transacao['jogador_id']]
        );
        
        if (!$jogadorIndicado || empty($jogadorIndicado['ref_indicacao'])) {
            // Jogador não foi indicado por ninguém, não há comissão
            return true;
        }
        
        // Buscar dados do afiliado (quem indicou)
        $afiliado = dbFetchOne(
            "SELECT id, codigo_afiliado, afiliado_ativo, comissao_afiliado 
             FROM jogador 
             WHERE codigo_afiliado = ? AND afiliado_ativo = 'ativo'",
            [$jogadorIndicado['ref_indicacao']]
        );
        
        if (!$afiliado) {
            error_log("Afiliado não encontrado ou inativo para código: {$jogadorIndicado['ref_indicacao']}");
            return false;
        }
        
        // Verificar se já existe comissão para esta transação
        $comissaoExistente = dbFetchOne(
            "SELECT id FROM transacoes 
             WHERE tipo = 'comissao' AND referencia = ?",
            ["deposito_{$transacaoId}"]
        );
        
        if ($comissaoExistente) {
            error_log("Comissão já calculada para transação: {$transacaoId}");
            return true;
        }
        
        // Calcular valor da comissão
        $valorDeposito = (float)$transacao['valor'];
        $percentualComissao = (float)$afiliado['comissao_afiliado'];
        $valorComissao = ($valorDeposito * $percentualComissao) / 100;
        
        // Buscar conta do afiliado
        $contaAfiliado = dbFetchOne(
            "SELECT id FROM contas WHERE jogador_id = ?",
            [$afiliado['id']]
        );
        
        if (!$contaAfiliado) {
            error_log("Conta do afiliado não encontrada: {$afiliado['id']}");
            return false;
        }
        
        // Registrar transação de comissão
        $descricaoComissao = sprintf(
            "Comissão de %.2f%% sobre depósito de %s do jogador indicado (ID: %d)",
            $percentualComissao,
            formatMoney($valorDeposito),
            $jogadorIndicado['id']
        );
        
        $success = dbExecute(
            "INSERT INTO transacoes (conta_id, tipo, valor, status, descricao, referencia, data_solicitacao, data_processamento, afeta_saldo) 
             VALUES (?, 'comissao', ?, 'aprovado', ?, ?, NOW(), NOW(), 1)",
            [
                $contaAfiliado['id'],
                $valorComissao,
                $descricaoComissao,
                "deposito_{$transacaoId}"
            ]
        );
        
        if ($success) {
            error_log("Comissão calculada com sucesso: Afiliado {$afiliado['id']}, Valor: {$valorComissao}, Transação: {$transacaoId}");
            return true;
        } else {
            error_log("Erro ao registrar comissão para transação: {$transacaoId}");
            return false;
        }
        
    } catch (Exception $e) {
        error_log("Erro ao calcular comissão de afiliado: " . $e->getMessage());
        return false;
    }
}

/**
 * Generate a unique affiliate code
 * 
 * @param int $length Length of the code (default: 10)
 * @return string Unique affiliate code
 */
function generateUniqueAffiliateCode($length = 10) {
    do {
        $code = generateRandomString($length);
        $existing = dbFetchOne("SELECT id FROM jogador WHERE codigo_afiliado = ?", [$code]);
    } while ($existing);
    
    return $code;
}

/**
 * Retorna o modelo de pagamento atual
 * @return string 'por_aposta' ou 'conta_saldo'
 */
function getModeloPagamento() {
    $config = dbFetchOne("SELECT valor FROM configuracoes WHERE nome_configuracao = 'modelo_pagamento' AND categoria = 'pagamento'");
    return $config ? $config['valor'] : 'por_aposta';
}

/**
 * Verifica se um jogador tem saldo suficiente para uma aposta
 * @param int $jogador_id ID do jogador
 * @param float $valor Valor necessário
 * @return array ['tem_saldo' => bool, 'saldo_atual' => float]
 */
function verificarSaldoJogador($jogador_id) {
    // Buscar conta do jogador
    $conta = dbFetchOne("SELECT id FROM contas WHERE jogador_id = ?", [$jogador_id]);
    
    if (!$conta) {
        return ['tem_saldo' => false, 'saldo_atual' => 0];
    }

    // Calcular saldo atual
    $sql = "
        SELECT COALESCE(SUM(CASE 
            WHEN tipo IN ('deposito', 'premio', 'bonus') THEN valor 
            WHEN tipo IN ('saque', 'aposta') THEN -valor 
        END), 0) as saldo_atual
        FROM transacoes 
        WHERE conta_id = ? 
        AND status = 'aprovado' 
        AND afeta_saldo = TRUE";

    $result = dbFetchOne($sql, [$conta['id']]);
    $saldoAtual = $result ? floatval($result['saldo_atual']) : 0;

    return [
        'tem_saldo' => true,
        'saldo_atual' => $saldoAtual,
        'conta_id' => $conta['id']
    ];
}

/**
 * Cria uma transação de débito para um palpite
 * @param int $conta_id ID da conta
 * @param float $valor Valor a ser debitado
 * @param int $palpite_id ID do palpite
 * @return bool|array false em caso de erro ou array com dados da transação
 */
function criarTransacaoPalpite($conta_id, $valor, $palpite_id) {
    try {
        global $pdo;
        
        // Iniciar transação
        $pdo->beginTransaction();

        // Verificar saldo atual
        $sql = "
            SELECT COALESCE(SUM(CASE 
                WHEN tipo IN ('deposito', 'premio', 'bonus') THEN valor 
                WHEN tipo IN ('saque', 'aposta') THEN -valor 
            END), 0) as saldo_atual
            FROM transacoes 
            WHERE conta_id = ? 
            AND status = 'aprovado' 
            AND afeta_saldo = TRUE";

        $result = dbFetchOne($sql, [$conta_id]);
        $saldoAtual = $result ? floatval($result['saldo_atual']) : 0;

        // Verificar se tem saldo suficiente
        if ($saldoAtual < $valor) {
            throw new Exception('Saldo insuficiente');
        }

        // Criar transação de débito
        $dados = [
            'conta_id' => $conta_id,
            'tipo' => 'aposta',
            'valor' => $valor,
            'status' => 'aprovado',
            'metodo_pagamento' => null,
            'afeta_saldo' => true,
            'palpite_id' => $palpite_id,
            'descricao' => 'Débito automático para palpite #' . $palpite_id,
            'data_processamento' => date('Y-m-d H:i:s')
        ];

        $transacao_id = dbInsert('transacoes', $dados);

        if (!$transacao_id) {
            throw new Exception('Erro ao criar transação');
        }

        // Atualizar status do palpite
        $stmt = $pdo->prepare("UPDATE palpites SET status = 'pago' WHERE id = ?");
        $stmt->execute([$palpite_id]);

        // Commit da transação
        $pdo->commit();

        return array_merge($dados, ['id' => $transacao_id]);

    } catch (Exception $e) {
        $pdo->rollBack();
        error_log('Erro ao criar transação de palpite: ' . $e->getMessage());
        return false;
    }
}

/**
 * Verifica o status da API Football
 * @return array Array com status e informações detalhadas
 */
function checkApiFootballStatus() {
    try {
        // Buscar configuração da API
        $config = getConfig('api_football');
        
        if (!$config || empty($config['api_key'])) {
            return [
                'status' => 'not_configured',
                'message' => 'API não configurada',
                'details' => []
            ];
        }
        
        // Usar o endpoint de status da API
        $url = api_football_url('status');
        
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_HTTPHEADER => [
                'x-rapidapi-key: ' . $config['api_key'],
                'x-rapidapi-host: v3.football.api-sports.io'
            ]
        ]);
        
        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        
        if ($httpCode === 200) {
            $data = json_decode($response, true);
            
            if ($data && isset($data['response'])) {
                $apiInfo = $data['response'];
                return [
                    'status' => 'online',
                    'message' => 'API Football funcionando',
                    'details' => [
                        'requests' => [
                            'current' => $apiInfo['requests']['current'] ?? 0,
                            'limit_day' => $apiInfo['requests']['limit_day'] ?? 0
                        ],
                        'subscription' => [
                            'started' => $apiInfo['subscription']['started'] ?? '',
                            'ends' => $apiInfo['subscription']['ends'] ?? '',
                            'plan' => $apiInfo['subscription']['plan'] ?? ''
                        ]
                    ]
                ];
            }
        }
        
        return [
            'status' => 'error',
            'message' => 'Erro na API (HTTP ' . $httpCode . ')',
            'details' => []
        ];
        
    } catch (Exception $e) {
        return [
            'status' => 'offline',
            'message' => 'Erro: ' . $e->getMessage(),
            'details' => []
        ];
    }
}

/**
 * Verifica o status detalhado do banco de dados
 * @return array Array com status e informações detalhadas
 */
function checkDatabaseStatus() {
    try {
        global $pdo;
        
        // Teste de conexão
        $pdo->query("SELECT 1");
        
        // Verificar tabelas principais
        $tables = [
            'jogador' => 'Jogadores',
            'dados_boloes' => 'Bolões',
            'palpites' => 'Palpites',
            'resultados_jogos' => 'Resultados',
            'configuracoes' => 'Configurações',
            'logs' => 'Logs',
            'administrador' => 'Administradores',
            'afiliados' => 'Afiliados',
            'afiliados_comissoes' => 'Comissões',
            'afiliados_indicacoes' => 'Indicações',
            'config_pagamentos' => 'Config. Pagamentos',
            'contas' => 'Contas',
            'metodos_pagamento' => 'Métodos Pagamento',
            'pagamentos' => 'Pagamentos',
            'transacoes' => 'Transações'
        ];
        
        $tableStatus = [];
        $totalTables = 0;
        
        foreach ($tables as $table => $label) {
            $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
            if ($stmt->fetch()) {
                $totalTables++;
                // Verificar quantidade de registros
                $count = $pdo->query("SELECT COUNT(*) as total FROM $table")->fetch()['total'];
                $tableStatus[$table] = [
                    'exists' => true,
                    'label' => $label,
                    'records' => $count
                ];
            } else {
                $tableStatus[$table] = [
                    'exists' => false,
                    'label' => $label,
                    'records' => 0
                ];
            }
        }
        
        // Verificar espaço usado pelo banco
        $dbName = DB_NAME;
        $sizeQuery = $pdo->query("
            SELECT 
                SUM(data_length + index_length) as size,
                SUM(data_free) as free_space
            FROM information_schema.TABLES 
            WHERE table_schema = '$dbName'
        ");
        $sizeInfo = $sizeQuery->fetch();
        
        return [
            'status' => 'online',
            'message' => 'Banco de dados conectado',
            'details' => [
                'name' => DB_NAME,
                'host' => DB_HOST,
                'tables' => [
                    'total' => $totalTables,
                    'expected' => count($tables),
                    'status' => $tableStatus
                ],
                'size' => [
                    'total' => $sizeInfo['size'] ?? 0,
                    'free' => $sizeInfo['free_space'] ?? 0
                ],
                'version' => $pdo->getAttribute(PDO::ATTR_SERVER_VERSION)
            ]
        ];
        
    } catch (Exception $e) {
        return [
            'status' => 'offline',
            'message' => 'Erro de conexão: ' . $e->getMessage(),
            'details' => []
        ];
    }
} 

/**
 * Wrapper function for getConfig to maintain compatibility
 * 
 * @param string $name Configuration name
 * @param mixed $default Default value if configuration not found
 * @return mixed Configuration value or default
 */
function getConfiguracao($name, $default = null) {
    return getConfig($name, $default);
}

/**
 * Calcular prazo limite para palpites (5 minutos antes do primeiro jogo)
 * 
 * @param array $jogos Array de jogos do bolão
 * @param string|null $dataLimiteFallback Data limite manual como fallback
 * @return DateTime|null Data limite ou null se não conseguir calcular
 */
function calcularPrazoLimitePalpites($jogos, $dataLimiteFallback = null) {
    if (empty($jogos) || !is_array($jogos)) {
        // Se não há jogos, usar fallback
        if ($dataLimiteFallback) {
            return new DateTime($dataLimiteFallback);
        }
        return null;
    }
    
    // Ordenar jogos por data para pegar o primeiro
    usort($jogos, function($a, $b) {
        $dateA = isset($a['data_iso']) ? $a['data_iso'] : $a['data'];
        $dateB = isset($b['data_iso']) ? $b['data_iso'] : $b['data'];
        return strtotime($dateA) - strtotime($dateB);
    });
    
    $primeiroJogo = $jogos[0];
    
    // Determinar a data do primeiro jogo
    $dataInicialJogo = null;
    if (!empty($primeiroJogo['data_formatada'])) {
        // Se temos data_formatada (formato brasileiro "dd/mm/yyyy HH:mm")
        $dataInicialJogo = DateTime::createFromFormat('d/m/Y H:i', $primeiroJogo['data_formatada']);
    } elseif (!empty($primeiroJogo['data'])) {
        // Se temos data (formato ISO "yyyy-mm-dd HH:mm:ss")
        $dataInicialJogo = new DateTime($primeiroJogo['data']);
    }
    
    if ($dataInicialJogo) {
        // Subtrair 5 minutos para criar o prazo limite
        $dataLimite = clone $dataInicialJogo;
        $dataLimite->sub(new DateInterval('PT5M')); // PT5M = 5 minutos
        return $dataLimite;
    }
    
    // Se não conseguiu calcular, usar fallback
    if ($dataLimiteFallback) {
        return new DateTime($dataLimiteFallback);
    }
    
    return null;
}