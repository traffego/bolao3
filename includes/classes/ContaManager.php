<?php

class ContaManager {
    /**
     * Cria uma nova conta para um jogador
     */
    public function criarConta($jogadorId) {
        try {
            // Verifica se já existe conta
            $conta = $this->buscarContaPorJogador($jogadorId);
            if ($conta) {
                throw new Exception('Jogador já possui uma conta.');
            }
            
            $sql = "INSERT INTO contas (jogador_id) VALUES (?)";
            dbExecute($sql, [$jogadorId]);
            
            return dbLastInsertId();
        } catch (Exception $e) {
            throw new Exception('Erro ao criar conta: ' . $e->getMessage());
        }
    }
    
    /**
     * Busca conta por ID do jogador
     */
    public function buscarContaPorJogador($jogadorId) {
        $sql = "SELECT * FROM contas WHERE jogador_id = ?";
        return dbFetchOne($sql, [$jogadorId]);
    }
    
    /**
     * Realiza um depósito
     */
    public function depositar($contaId, $valor, $metodo, $referencia = null) {
        try {
            // Validações
            $this->validarValorDeposito($valor);
            $this->validarStatusConta($contaId);
            
            // Inicia transação
            dbBeginTransaction();
            
            // Busca saldo atual
            $conta = $this->buscarConta($contaId);
            $saldoAnterior = $this->getSaldo($contaId);
            $saldoPosterior = $saldoAnterior + $valor;
            
            // Registra transação
            $sql = "INSERT INTO transacoes (conta_id, tipo, valor, saldo_anterior, saldo_posterior, status, referencia) 
                    VALUES (?, 'deposito', ?, ?, ?, 'pendente', ?)";
            dbExecute($sql, [$contaId, $valor, $saldoAnterior, $saldoPosterior, $referencia]);
            
            dbCommit();
            return true;
        } catch (Exception $e) {
            dbRollback();
            throw new Exception('Erro ao processar depósito: ' . $e->getMessage());
        }
    }
    
    /**
     * Solicita um saque
     */
    public function solicitarSaque($contaId, $valor, $metodo) {
        try {
            // Validações
            $this->validarValorSaque($valor);
            $this->validarStatusConta($contaId);
            $this->validarSaldoSuficiente($contaId, $valor);
            
            // Inicia transação
            dbBeginTransaction();
            
            // Busca saldo atual
            $conta = $this->buscarConta($contaId);
            $saldoAnterior = $this->getSaldo($contaId);
            $saldoPosterior = $saldoAnterior - $valor;
            
            // Registra transação
            $sql = "INSERT INTO transacoes (conta_id, tipo, valor, saldo_anterior, saldo_posterior, status) 
                    VALUES (?, 'saque', ?, ?, ?, 'pendente')";
            dbExecute($sql, [$contaId, $valor, $saldoAnterior, $saldoPosterior]);
            
            dbCommit();
            return true;
        } catch (Exception $e) {
            dbRollback();
            throw new Exception('Erro ao solicitar saque: ' . $e->getMessage());
        }
    }
    
    /**
     * Processa uma aposta
     */
    public function processarAposta($contaId, $valor, $bolaoId) {
        try {
            // Validações
            $this->validarStatusConta($contaId);
            $this->validarSaldoSuficiente($contaId, $valor);
            
            // Inicia transação
            dbBeginTransaction();
            
            // Busca saldo atual
            $conta = $this->buscarConta($contaId);
            $saldoAnterior = $this->getSaldo($contaId);
            $saldoPosterior = $saldoAnterior - $valor;
            
            // Registra transação
            $sql = "INSERT INTO transacoes (conta_id, tipo, valor, saldo_anterior, saldo_posterior, status, referencia) 
                    VALUES (?, 'aposta', ?, ?, ?, 'aprovado', ?)";
            dbExecute($sql, [$contaId, $valor, $saldoAnterior, $saldoPosterior, "bolao_$bolaoId"]);
            
            dbCommit();
            return true;
        } catch (Exception $e) {
            dbRollback();
            throw new Exception('Erro ao processar aposta: ' . $e->getMessage());
        }
    }
    
    /**
     * Processa um prêmio
     */
    public function processarPremio($contaId, $valor, $bolaoId) {
        try {
            // Validações
            $this->validarStatusConta($contaId);
            
            // Inicia transação
            dbBeginTransaction();
            
            // Busca saldo atual
            $conta = $this->buscarConta($contaId);
            $saldoAnterior = $this->getSaldo($contaId);
            $saldoPosterior = $saldoAnterior + $valor;
            
            // Registra transação
            $sql = "INSERT INTO transacoes (conta_id, tipo, valor, saldo_anterior, saldo_posterior, status, referencia) 
                    VALUES (?, 'premio', ?, ?, ?, 'aprovado', ?)";
            dbExecute($sql, [$contaId, $valor, $saldoAnterior, $saldoPosterior, "premio_bolao_$bolaoId"]);
            
            dbCommit();
            return true;
        } catch (Exception $e) {
            dbRollback();
            throw new Exception('Erro ao processar prêmio: ' . $e->getMessage());
        }
    }
    
    /**
     * Busca conta por ID
     */
    private function buscarConta($contaId) {
        $sql = "SELECT id, jogador_id, status FROM contas WHERE id = ?";
        $conta = dbFetchOne($sql, [$contaId]);
        if (!$conta) {
            throw new Exception('Conta não encontrada.');
        }
        return $conta;
    }
    
    /**
     * Valida valor mínimo e máximo para depósito
     */
    private function validarValorDeposito($valor) {
        $minimo = $this->getConfigValue('deposito_minimo');
        $maximo = $this->getConfigValue('deposito_maximo');
        
        if ($valor < $minimo) {
            throw new Exception("Valor mínimo para depósito é R$ " . number_format($minimo, 2, ',', '.'));
        }
        if ($valor > $maximo) {
            throw new Exception("Valor máximo para depósito é R$ " . number_format($maximo, 2, ',', '.'));
        }
    }
    
    /**
     * Valida valor mínimo e máximo para saque
     */
    private function validarValorSaque($valor) {
        $minimo = $this->getConfigValue('saque_minimo');
        $maximo = $this->getConfigValue('saque_maximo');
        
        if ($valor < $minimo) {
            throw new Exception("Valor mínimo para saque é R$ " . number_format($minimo, 2, ',', '.'));
        }
        if ($valor > $maximo) {
            throw new Exception("Valor máximo para saque é R$ " . number_format($maximo, 2, ',', '.'));
        }
    }
    
    /**
     * Valida se conta está ativa
     */
    private function validarStatusConta($contaId) {
        $conta = $this->buscarConta($contaId);
        if ($conta['status'] !== 'ativo') {
            throw new Exception('Conta não está ativa.');
        }
    }
    
    /**
     * Valida se tem saldo suficiente
     */
    private function validarSaldoSuficiente($contaId, $valor) {
        $saldoAtual = $this->getSaldo($contaId);
        if ($saldoAtual < $valor) {
            throw new Exception('Saldo insuficiente.');
        }
    }
    
    /**
     * Busca valor de configuração
     */
    private function getConfigValue($chave) {
        $sql = "SELECT valor FROM configuracoes WHERE nome_configuracao = ? AND categoria = 'pagamento'";
        $config = dbFetchOne($sql, [$chave]);
        return $config ? floatval($config['valor']) : 0;
    }
    
    /**
     * Lista transações de uma conta
     */
    public function listarTransacoes($contaId, $limit = 10, $offset = 0) {
        $sql = "SELECT * FROM transacoes WHERE conta_id = ? ORDER BY data_solicitacao DESC LIMIT ? OFFSET ?";
        return dbFetchAll($sql, [$contaId, $limit, $offset]);
    }
    
    /**
     * Busca saldo atual
     */
    public function getSaldo($contaId) {
        $sql = "
            SELECT COALESCE(SUM(
                CASE 
                    WHEN tipo IN ('deposito', 'premio', 'bonus') AND status = 'aprovado' THEN valor
                    WHEN tipo IN ('saque', 'aposta') AND status IN ('aprovado', 'pendente') THEN -valor
                    ELSE 0
                END
            ), 0) as saldo
            FROM transacoes 
            WHERE conta_id = ?
        ";
        $result = dbFetchOne($sql, [$contaId]);
        return floatval($result['saldo']);
    }
} 