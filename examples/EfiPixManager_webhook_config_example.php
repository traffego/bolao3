<?php
/**
 * Exemplo de implementação da configuração de webhook fatal no EfiPixManager
 * 
 * Este arquivo demonstra como integrar a nova funcionalidade no sistema existente
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/EfiPixManager.php';

class EfiPixManagerFactory {
    
    /**
     * Cria instância do EfiPixManager com configuração adequada para o ambiente
     * 
     * @param string|null $environment Ambiente específico ou null para auto-detectar
     * @return EfiPixManager
     */
    public static function create($environment = null) {
        // Auto-detectar ambiente se não especificado
        if ($environment === null) {
            $environment = self::detectEnvironment();
        }
        
        // Configurar comportamento baseado no ambiente
        $webhookFatal = self::getWebhookFatalConfig($environment);
        
        // Log da configuração
        error_log("EfiPixManager criado para ambiente: {$environment}, webhook_fatal: " . 
                  ($webhookFatal ? 'true' : 'false'));
        
        return new EfiPixManager($webhookFatal);
    }
    
    /**
     * Detecta o ambiente atual
     * 
     * @return string 'development', 'staging' ou 'production'
     */
    private static function detectEnvironment() {
        // Verificar se é localhost
        $serverName = $_SERVER['SERVER_NAME'] ?? '';
        if (in_array($serverName, ['localhost', '127.0.0.1', '::1'])) {
            return 'development';
        }
        
        // Verificar URL de staging
        if (strpos($serverName, 'staging') !== false || strpos($serverName, 'test') !== false) {
            return 'staging';
        }
        
        // Verificar variável de ambiente
        $env = $_ENV['APP_ENV'] ?? $_SERVER['APP_ENV'] ?? 'production';
        return $env;
    }
    
    /**
     * Retorna configuração de webhook fatal baseada no ambiente
     * 
     * @param string $environment
     * @return bool
     */
    private static function getWebhookFatalConfig($environment) {
        // Tentar buscar do banco de dados primeiro
        try {
            $config = dbFetchOne("
                SELECT valor 
                FROM configuracoes 
                WHERE nome_configuracao = 'efi_webhook_fatal' 
                AND categoria = 'pagamentos'
            ");
            
            if ($config) {
                $configData = json_decode($config['valor'], true);
                if (isset($configData[$environment])) {
                    return (bool) $configData[$environment];
                }
            }
        } catch (Exception $e) {
            error_log("Erro ao buscar configuração de webhook fatal: " . $e->getMessage());
        }
        
        // Configuração padrão por ambiente
        $defaultConfig = [
            'development' => true,  // Fatal - força correção de problemas
            'staging' => true,      // Fatal - validação antes de produção
            'production' => false   // Não fatal - alta disponibilidade
        ];
        
        return $defaultConfig[$environment] ?? false;
    }
}

/**
 * Classe utilitária para gerenciar configurações do EfiPixManager
 */
class EfiPixManagerConfig {
    
    /**
     * Salva configuração de webhook fatal no banco
     * 
     * @param array $config Configuração por ambiente
     * @return bool
     */
    public static function saveWebhookFatalConfig($config) {
        try {
            // Validar configuração
            $validEnvironments = ['development', 'staging', 'production'];
            foreach ($config as $env => $value) {
                if (!in_array($env, $validEnvironments)) {
                    throw new InvalidArgumentException("Ambiente inválido: {$env}");
                }
                if (!is_bool($value)) {
                    throw new InvalidArgumentException("Valor deve ser boolean para {$env}");
                }
            }
            
            // Salvar no banco
            $stmt = dbExecute("
                INSERT INTO configuracoes (nome_configuracao, valor, categoria, descricao) 
                VALUES ('efi_webhook_fatal', ?, 'pagamentos', 'Configuração de webhook fatal por ambiente')
                ON DUPLICATE KEY UPDATE valor = VALUES(valor)
            ", [json_encode($config)]);
            
            return true;
            
        } catch (Exception $e) {
            error_log("Erro ao salvar configuração: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Carrega configuração atual do banco
     * 
     * @return array|null
     */
    public static function loadWebhookFatalConfig() {
        try {
            $config = dbFetchOne("
                SELECT valor 
                FROM configuracoes 
                WHERE nome_configuracao = 'efi_webhook_fatal' 
                AND categoria = 'pagamentos'
            ");
            
            return $config ? json_decode($config['valor'], true) : null;
            
        } catch (Exception $e) {
            error_log("Erro ao carregar configuração: " . $e->getMessage());
            return null;
        }
    }
}

// Exemplos de uso:

// 1. Uso básico com factory
try {
    $pixManager = EfiPixManagerFactory::create();
    $charge = $pixManager->createCharge($userId, $valor);
} catch (Exception $e) {
    // Tratar erro baseado no ambiente
    error_log("Erro PIX: " . $e->getMessage());
}

// 2. Forçar ambiente específico
try {
    $pixManager = EfiPixManagerFactory::create('development');
    // Modo development - webhook fatal ativo
} catch (Exception $e) {
    echo "Erro de configuração que deve ser corrigido: " . $e->getMessage();
}

// 3. Configurar via admin
if (isset($_POST['save_webhook_config'])) {
    $config = [
        'development' => true,
        'staging' => true, 
        'production' => false
    ];
    
    if (EfiPixManagerConfig::saveWebhookFatalConfig($config)) {
        echo "Configuração salva com sucesso!";
    } else {
        echo "Erro ao salvar configuração.";
    }
}

// 4. Uso manual com configuração dinâmica
$pixManager = new EfiPixManager();

// Alterar configuração conforme necessário
if ($isDebugMode) {
    $pixManager->setWebhookFailureFatal(true);
}

// Verificar configuração atual
if ($pixManager->isWebhookFailureFatal()) {
    echo "Modo strict ativo - falhas de webhook são fatais";
}
