<?php
class SecurityValidator {
    private $pdo;
    
    // Limites de transações
    const LIMITE_DIARIO_SAQUE = 10000.00;
    const LIMITE_MENSAL_SAQUE = 50000.00;
    const LIMITE_DIARIO_DEPOSITO = 20000.00;
    const LIMITE_MENSAL_DEPOSITO = 100000.00;
    const MAX_TENTATIVAS_LOGIN = 5;
    const TEMPO_BLOQUEIO = 1800; // 30 minutos
    
    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
    }
    
    /**
     * Valida uma transação financeira
     */
    public function validarTransacao($contaId, $tipo, $valor, $dadosAdicionais = []) {
        // Validar valor
        if (!$this->validarValor($valor)) {
            throw new Exception('Valor inválido para a transação.');
        }
        
        // Validar status da conta
        if (!$this->validarStatusConta($contaId)) {
            throw new Exception('Conta não está em status válido para transações.');
        }
        
        // Validar limites
        if (!$this->validarLimites($contaId, $tipo, $valor)) {
            throw new Exception('Limite de transação excedido.');
        }
        
        // Validar duplicidade
        if ($this->isTransacaoDuplicada($contaId, $tipo, $valor, $dadosAdicionais)) {
            throw new Exception('Possível transação duplicada detectada.');
        }
        
        return true;
    }
    
    /**
     * Valida valor da transação
     */
    private function validarValor($valor) {
        // Valor deve ser numérico e positivo
        if (!is_numeric($valor) || $valor <= 0) {
            return false;
        }
        
        // Valor não pode ter mais de 2 casas decimais
        if (ceil($valor * 100) / 100 != $valor) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Valida status da conta
     */
    private function validarStatusConta($contaId) {
        $stmt = $this->pdo->prepare("
            SELECT status 
            FROM contas 
            WHERE id = ?
        ");
        $stmt->execute([$contaId]);
        $status = $stmt->fetchColumn();
        
        return $status === 'ativo';
    }
    
    /**
     * Valida limites de transações
     */
    private function validarLimites($contaId, $tipo, $valor) {
        $hoje = date('Y-m-d');
        $inicioMes = date('Y-m-01');
        
        // Verificar limite diário
        $stmt = $this->pdo->prepare("
            SELECT COALESCE(SUM(valor), 0) as total
            FROM transacoes
            WHERE conta_id = ?
            AND tipo = ?
            AND DATE(data_solicitacao) = ?
            AND status NOT IN ('rejeitado', 'cancelado')
        ");
        $stmt->execute([$contaId, $tipo, $hoje]);
        $totalDiario = $stmt->fetchColumn() + $valor;
        
        // Verificar limite mensal
        $stmt = $this->pdo->prepare("
            SELECT COALESCE(SUM(valor), 0) as total
            FROM transacoes
            WHERE conta_id = ?
            AND tipo = ?
            AND DATE(data_solicitacao) >= ?
            AND status NOT IN ('rejeitado', 'cancelado')
        ");
        $stmt->execute([$contaId, $tipo, $inicioMes]);
        $totalMensal = $stmt->fetchColumn() + $valor;
        
        switch ($tipo) {
            case 'saque':
                if ($totalDiario > self::LIMITE_DIARIO_SAQUE) {
                    return false;
                }
                if ($totalMensal > self::LIMITE_MENSAL_SAQUE) {
                    return false;
                }
                break;
                
            case 'deposito':
                if ($totalDiario > self::LIMITE_DIARIO_DEPOSITO) {
                    return false;
                }
                if ($totalMensal > self::LIMITE_MENSAL_DEPOSITO) {
                    return false;
                }
                break;
        }
        
        return true;
    }
    
    /**
     * Verifica transações duplicadas
     */
    private function isTransacaoDuplicada($contaId, $tipo, $valor, $dadosAdicionais) {
        // Buscar transações similares nos últimos 5 minutos
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) 
            FROM transacoes
            WHERE conta_id = ?
            AND tipo = ?
            AND valor = ?
            AND data_solicitacao >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
        ");
        $stmt->execute([$contaId, $tipo, $valor]);
        
        return $stmt->fetchColumn() > 0;
    }
    
    /**
     * Valida tentativas de login
     */
    public function validarTentativasLogin($email) {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) as tentativas,
                   MAX(data_hora) as ultima_tentativa
            FROM logs
            WHERE tipo = 'login_falha'
            AND JSON_UNQUOTE(JSON_EXTRACT(dados_adicionais, '$.email')) = ?
            AND data_hora >= DATE_SUB(NOW(), INTERVAL ? SECOND)
        ");
        $stmt->execute([$email, self::TEMPO_BLOQUEIO]);
        $result = $stmt->fetch();
        
        if ($result['tentativas'] >= self::MAX_TENTATIVAS_LOGIN) {
            $tempoRestante = self::TEMPO_BLOQUEIO - 
                           (time() - strtotime($result['ultima_tentativa']));
            
            if ($tempoRestante > 0) {
                throw new Exception(sprintf(
                    'Conta bloqueada. Tente novamente em %d minutos.',
                    ceil($tempoRestante / 60)
                ));
            }
        }
        
        return true;
    }
    
    /**
     * Registra falha de login
     */
    public function registrarFalhaLogin($email, $ip = null) {
        $dados = [
            'email' => $email,
            'ip' => $ip ?? $_SERVER['REMOTE_ADDR']
        ];
        
        $stmt = $this->pdo->prepare("
            INSERT INTO logs (
                tipo,
                descricao,
                usuario_id,
                data_hora,
                dados_adicionais,
                ip_address
            ) VALUES (
                'login_falha',
                'Tentativa de login falhou',
                NULL,
                NOW(),
                ?,
                ?
            )
        ");
        
        return $stmt->execute([
            json_encode($dados),
            $dados['ip']
        ]);
    }
    
    /**
     * Valida força da senha
     */
    public function validarSenha($senha) {
        // Mínimo 8 caracteres
        if (strlen($senha) < 8) {
            return false;
        }
        
        // Deve conter pelo menos:
        $temMaiuscula = preg_match('/[A-Z]/', $senha);
        $temMinuscula = preg_match('/[a-z]/', $senha);
        $temNumero = preg_match('/[0-9]/', $senha);
        $temEspecial = preg_match('/[^A-Za-z0-9]/', $senha);
        
        return $temMaiuscula && $temMinuscula && $temNumero && $temEspecial;
    }
    
    /**
     * Valida CPF
     */
    public function validarCPF($cpf) {
        // Remove caracteres não numéricos
        $cpf = preg_replace('/[^0-9]/', '', $cpf);
        
        // Verifica se tem 11 dígitos
        if (strlen($cpf) != 11) {
            return false;
        }
        
        // Verifica se todos os dígitos são iguais
        if (preg_match('/^(\d)\1*$/', $cpf)) {
            return false;
        }
        
        // Calcula primeiro dígito verificador
        $soma = 0;
        for ($i = 0; $i < 9; $i++) {
            $soma += $cpf[$i] * (10 - $i);
        }
        $resto = $soma % 11;
        $dv1 = $resto < 2 ? 0 : 11 - $resto;
        
        // Verifica primeiro dígito
        if ($cpf[9] != $dv1) {
            return false;
        }
        
        // Calcula segundo dígito verificador
        $soma = 0;
        for ($i = 0; $i < 10; $i++) {
            $soma += $cpf[$i] * (11 - $i);
        }
        $resto = $soma % 11;
        $dv2 = $resto < 2 ? 0 : 11 - $resto;
        
        // Verifica segundo dígito
        return $cpf[10] == $dv2;
    }
    
    /**
     * Valida endereço de email
     */
    public function validarEmail($email) {
        // Validação básica de formato
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        
        // Verifica MX record do domínio
        $domain = substr(strrchr($email, "@"), 1);
        return checkdnsrr($domain, 'MX');
    }
    
    /**
     * Valida dados do usuário para transações
     */
    public function validarDadosUsuario($usuarioId) {
        $stmt = $this->pdo->prepare("
            SELECT nome, email, cpf, telefone
            FROM jogador
            WHERE id = ?
        ");
        $stmt->execute([$usuarioId]);
        $usuario = $stmt->fetch();
        
        if (!$usuario) {
            return false;
        }
        
        // Verifica se tem todos os dados necessários
        if (empty($usuario['nome']) || 
            empty($usuario['email']) || 
            empty($usuario['cpf']) || 
            empty($usuario['telefone'])) {
            return false;
        }
        
        // Valida formato dos dados
        if (!$this->validarCPF($usuario['cpf'])) {
            return false;
        }
        
        if (!$this->validarEmail($usuario['email'])) {
            return false;
        }
        
        return true;
    }
} 