<?php
class LogFinanceiroManager {
    private $pdo;
    
    // Tipos de operações financeiras
    const TIPO_DEPOSITO = 'deposito';
    const TIPO_SAQUE = 'saque';
    const TIPO_APOSTA = 'aposta';
    const TIPO_PREMIO = 'premio';
    const TIPO_ESTORNO = 'estorno';
    const TIPO_BONUS = 'bonus';
    
    // Status das operações
    const STATUS_PENDENTE = 'pendente';
    const STATUS_APROVADO = 'aprovado';
    const STATUS_REJEITADO = 'rejeitado';
    const STATUS_CANCELADO = 'cancelado';
    const STATUS_PROCESSANDO = 'processando';
    
    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
    }
    
    /**
     * Registra log de operação financeira
     */
    public function registrarOperacao($usuarioId, $tipo, $descricao, $dadosAdicionais = []) {
        try {
            // Adiciona informações padrão aos dados adicionais
            $dadosAdicionais['ip'] = $_SERVER['REMOTE_ADDR'] ?? null;
            $dadosAdicionais['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? null;
            $dadosAdicionais['timestamp'] = date('Y-m-d H:i:s');
            
            $stmt = $this->pdo->prepare("
                INSERT INTO logs (
                    tipo,
                    descricao,
                    usuario_id,
                    data_hora,
                    dados_adicionais,
                    ip_address
                ) VALUES (?, ?, ?, NOW(), ?, ?)
            ");
            
            return $stmt->execute([
                'financeiro_' . $tipo,
                $descricao,
                $usuarioId,
                json_encode($dadosAdicionais),
                $dadosAdicionais['ip']
            ]);
            
        } catch (Exception $e) {
            error_log('Erro ao registrar log financeiro: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Registra log de depósito
     */
    public function registrarDeposito($usuarioId, $valor, $status, $dadosAdicionais = []) {
        $descricao = sprintf(
            "Depósito de %s - Status: %s",
            formatMoney($valor),
            ucfirst($status)
        );
        
        $dadosAdicionais['valor'] = $valor;
        $dadosAdicionais['status'] = $status;
        
        return $this->registrarOperacao(
            $usuarioId,
            self::TIPO_DEPOSITO,
            $descricao,
            $dadosAdicionais
        );
    }
    
    /**
     * Registra log de saque
     */
    public function registrarSaque($usuarioId, $valor, $status, $dadosAdicionais = []) {
        $descricao = sprintf(
            "Saque de %s - Status: %s",
            formatMoney($valor),
            ucfirst($status)
        );
        
        $dadosAdicionais['valor'] = $valor;
        $dadosAdicionais['status'] = $status;
        
        return $this->registrarOperacao(
            $usuarioId,
            self::TIPO_SAQUE,
            $descricao,
            $dadosAdicionais
        );
    }
    
    /**
     * Registra log de aposta
     */
    public function registrarAposta($usuarioId, $valor, $bolaoId, $palpiteId, $dadosAdicionais = []) {
        $descricao = sprintf(
            "Aposta de %s no bolão #%d - Palpite #%d",
            formatMoney($valor),
            $bolaoId,
            $palpiteId
        );
        
        $dadosAdicionais['valor'] = $valor;
        $dadosAdicionais['bolao_id'] = $bolaoId;
        $dadosAdicionais['palpite_id'] = $palpiteId;
        
        return $this->registrarOperacao(
            $usuarioId,
            self::TIPO_APOSTA,
            $descricao,
            $dadosAdicionais
        );
    }
    
    /**
     * Registra log de prêmio
     */
    public function registrarPremio($usuarioId, $valor, $bolaoId, $posicao = null, $dadosAdicionais = []) {
        $descricao = sprintf(
            "Prêmio de %s do bolão #%d%s",
            formatMoney($valor),
            $bolaoId,
            $posicao ? " - {$posicao}º lugar" : ""
        );
        
        $dadosAdicionais['valor'] = $valor;
        $dadosAdicionais['bolao_id'] = $bolaoId;
        if ($posicao) {
            $dadosAdicionais['posicao'] = $posicao;
        }
        
        return $this->registrarOperacao(
            $usuarioId,
            self::TIPO_PREMIO,
            $descricao,
            $dadosAdicionais
        );
    }
    
    /**
     * Registra log de estorno
     */
    public function registrarEstorno($usuarioId, $valor, $transacaoOriginalId, $motivo, $dadosAdicionais = []) {
        $descricao = sprintf(
            "Estorno de %s - Motivo: %s",
            formatMoney($valor),
            $motivo
        );
        
        $dadosAdicionais['valor'] = $valor;
        $dadosAdicionais['transacao_original_id'] = $transacaoOriginalId;
        $dadosAdicionais['motivo'] = $motivo;
        
        return $this->registrarOperacao(
            $usuarioId,
            self::TIPO_ESTORNO,
            $descricao,
            $dadosAdicionais
        );
    }
    
    /**
     * Registra log de bônus
     */
    public function registrarBonus($usuarioId, $valor, $motivo, $dadosAdicionais = []) {
        $descricao = sprintf(
            "Bônus de %s - Motivo: %s",
            formatMoney($valor),
            $motivo
        );
        
        $dadosAdicionais['valor'] = $valor;
        $dadosAdicionais['motivo'] = $motivo;
        
        return $this->registrarOperacao(
            $usuarioId,
            self::TIPO_BONUS,
            $descricao,
            $dadosAdicionais
        );
    }
    
    /**
     * Busca logs financeiros
     */
    public function buscarLogs($filtros = [], $limit = 50, $offset = 0) {
        try {
            $where = ["tipo LIKE 'financeiro_%'"];
            $params = [];
            
            if (isset($filtros['usuario_id'])) {
                $where[] = "usuario_id = ?";
                $params[] = $filtros['usuario_id'];
            }
            
            if (isset($filtros['tipo'])) {
                $where[] = "tipo = ?";
                $params[] = 'financeiro_' . $filtros['tipo'];
            }
            
            if (isset($filtros['data_inicio'])) {
                $where[] = "data_hora >= ?";
                $params[] = $filtros['data_inicio'];
            }
            
            if (isset($filtros['data_fim'])) {
                $where[] = "data_hora <= ?";
                $params[] = $filtros['data_fim'];
            }
            
            $sql = "
                SELECT l.*, j.nome as usuario_nome
                FROM logs l
                LEFT JOIN jogador j ON l.usuario_id = j.id
                WHERE " . implode(" AND ", $where) . "
                ORDER BY l.data_hora DESC
                LIMIT ? OFFSET ?
            ";
            
            $params[] = $limit;
            $params[] = $offset;
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            
            return $stmt->fetchAll();
            
        } catch (Exception $e) {
            error_log('Erro ao buscar logs financeiros: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Conta total de logs financeiros
     */
    public function contarLogs($filtros = []) {
        try {
            $where = ["tipo LIKE 'financeiro_%'"];
            $params = [];
            
            if (isset($filtros['usuario_id'])) {
                $where[] = "usuario_id = ?";
                $params[] = $filtros['usuario_id'];
            }
            
            if (isset($filtros['tipo'])) {
                $where[] = "tipo = ?";
                $params[] = 'financeiro_' . $filtros['tipo'];
            }
            
            if (isset($filtros['data_inicio'])) {
                $where[] = "data_hora >= ?";
                $params[] = $filtros['data_inicio'];
            }
            
            if (isset($filtros['data_fim'])) {
                $where[] = "data_hora <= ?";
                $params[] = $filtros['data_fim'];
            }
            
            $sql = "
                SELECT COUNT(*) as total
                FROM logs
                WHERE " . implode(" AND ", $where);
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            
            return (int) $stmt->fetch()['total'];
            
        } catch (Exception $e) {
            error_log('Erro ao contar logs financeiros: ' . $e->getMessage());
            return 0;
        }
    }
} 