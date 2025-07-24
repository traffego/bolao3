<?php
/**
 * Funções auxiliares para o painel administrativo
 */

/**
 * Gera um token CSRF para proteção contra ataques CSRF
 */
function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verifica se o token CSRF é válido
 */
function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Constrói uma string de query mantendo os filtros existentes
 */
function buildQueryString($filters, $updates = []) {
    $params = array_merge($filters, $updates);
    return http_build_query($params);
}

/**
 * Retorna o ícone de ordenação apropriado
 */
function getSortIcon($filters, $field) {
    if ($filters['sort'] !== $field) {
        return '<i class="bi bi-arrow-down-up text-muted"></i>';
    }
    return $filters['order'] === 'asc' 
        ? '<i class="bi bi-arrow-up"></i>' 
        : '<i class="bi bi-arrow-down"></i>';
}

/**
 * Registra uma ação no log do sistema
 */
function logAdminAction($tipo, $descricao, $dados_adicionais = null) {
    if (!isAdmin()) return false;
    
    $admin_id = $_SESSION['admin_id'];
    $ip = $_SERVER['REMOTE_ADDR'];
    
    $query = "INSERT INTO logs (tipo, descricao, usuario_id, dados_adicionais, ip_address) 
              VALUES (?, ?, ?, ?, ?)";
    
    $params = [
        $tipo,
        $descricao,
        $admin_id,
        $dados_adicionais ? json_encode($dados_adicionais) : null,
        $ip
    ];
    
    return dbExecute($query, $params);
}

/**
 * Obtém estatísticas gerais do sistema
 */
function getSystemStats() {
    $stats = [
        'total_jogadores' => dbCount('jogador'),
        'jogadores_ativos' => dbCount('jogador', "status = 'ativo'"),
        'total_boloes' => dbCount('dados_boloes'),
        'boloes_ativos' => dbCount('dados_boloes', 'status = 1'),
        'total_palpites' => dbCount('palpites'),
        'total_pagamentos' => dbCount('pagamentos', "status = 'confirmado'"),
        'valor_total_pagamentos' => dbFetchOne(
            "SELECT SUM(valor) as total FROM pagamentos WHERE status = 'confirmado'"
        )['total'] ?? 0
    ];
    
    return $stats;
}

/**
 * Obtém os últimos logs do sistema
 */
function getRecentLogs($limit = 10) {
    return dbFetchAll(
        "SELECT l.*, j.nome as usuario_nome 
         FROM logs l 
         LEFT JOIN jogador j ON l.usuario_id = j.id 
         ORDER BY l.data_hora DESC 
         LIMIT ?",
        [$limit]
    );
}

/**
 * Obtém os jogadores mais ativos
 */
function getTopJogadores($limit = 5) {
    return dbFetchAll(
        "SELECT j.*, 
                COUNT(p.id) as total_palpites,
                COUNT(DISTINCT p.bolao_id) as total_boloes
         FROM jogador j
         LEFT JOIN palpites p ON j.id = p.jogador_id
         WHERE j.status = 'ativo'
         GROUP BY j.id
         ORDER BY total_palpites DESC
         LIMIT ?",
        [$limit]
    );
}

/**
 * Obtém os bolões mais populares
 */
function getTopBoloes($limit = 5) {
    return dbFetchAll(
        "SELECT b.*, 
                COUNT(DISTINCT p.jogador_id) as total_participantes,
                COUNT(p.id) as total_palpites
         FROM dados_boloes b
         LEFT JOIN palpites p ON b.id = p.bolao_id
         WHERE b.status = 1
         GROUP BY b.id
         ORDER BY total_participantes DESC
         LIMIT ?",
        [$limit]
    );
} 