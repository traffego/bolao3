# Configuração de Webhook do EfiPixManager

## Visão Geral

A classe `EfiPixManager` agora suporta uma configuração para determinar se falhas no registro de webhook devem ser fatais (interrompendo a execução) ou apenas logadas (permitindo que a aplicação continue funcionando).

## Como Usar

### 1. Configuração no Construtor

```php
// Falhas de webhook NÃO são fatais (comportamento padrão)
$pixManager = new EfiPixManager(false);

// Falhas de webhook SÃO fatais - interrompem a execução
$pixManager = new EfiPixManager(true);

// Sem parâmetro (comportamento padrão: não fatal)
$pixManager = new EfiPixManager();
```

### 2. Alteração Após Construção

```php
$pixManager = new EfiPixManager();

// Definir como fatal
$pixManager->setWebhookFailureFatal(true);

// Definir como não fatal
$pixManager->setWebhookFailureFatal(false);

// Verificar configuração atual
$isFatal = $pixManager->isWebhookFailureFatal();
```

## Comportamentos

### Quando `webhookFailureFatal = false` (Padrão)
- Falhas no registro de webhook são apenas logadas como WARNING
- A aplicação continua funcionando normalmente
- PIX ainda pode ser criado e verificado
- Recomendado para **ambiente de produção** onde a disponibilidade é crítica

### Quando `webhookFailureFatal = true`
- Falhas no registro de webhook geram exceção e interrompem a execução
- Força a correção de problemas de configuração antes de usar PIX
- Recomendado para **ambiente de desenvolvimento/teste** para garantir configuração correta

## Cenários de Uso

### Produção - Alta Disponibilidade
```php
try {
    // Não interromper por problemas de webhook
    $pixManager = new EfiPixManager(false);
    
    // Aplicação continua funcionando mesmo se webhook falhar
    $charge = $pixManager->createCharge($userId, $valor);
    
} catch (Exception $e) {
    // Apenas erros críticos (certificado, autenticação, etc.)
    log_error("Erro crítico no PIX", ['error' => $e->getMessage()]);
}
```

### Desenvolvimento - Validação Estrita
```php
try {
    // Forçar correção de problemas de webhook
    $pixManager = new EfiPixManager(true);
    
    $charge = $pixManager->createCharge($userId, $valor);
    
} catch (Exception $e) {
    // Qualquer erro, incluindo webhook, interrompe
    echo "Erro: " . $e->getMessage();
    // Desenvolvedor deve corrigir configuração antes de continuar
}
```

### Configuração Dinâmica via Banco
```php
// Buscar configuração do banco de dados
$config = dbFetchOne("SELECT valor FROM configuracoes WHERE nome_configuracao = 'webhook_fatal'");
$webhookFatal = $config ? json_decode($config['valor'], true) : false;

$pixManager = new EfiPixManager($webhookFatal);
```

## Logs Gerados

### Falha Não Fatal (Padrão)
```
[WARNING] Falha no registro de webhook (não fatal): Erro de conectividade
```

### Falha Fatal
```
[ERROR] Falha fatal no registro de webhook: Erro de conectividade
```

## Vantagens

1. **Flexibilidade**: Administradores podem escolher o comportamento adequado para cada ambiente
2. **Disponibilidade**: Em produção, PIX continua funcionando mesmo com problemas de webhook
3. **Debugging**: Em desenvolvimento, força a correção de problemas de configuração
4. **Monitoramento**: Logs claros indicam o tipo de falha e configuração ativa

## Configuração Recomendada

- **Desenvolvimento/Teste**: `webhookFailureFatal = true`
- **Produção**: `webhookFailureFatal = false`
- **Staging**: `webhookFailureFatal = true` (para validar configuração antes de produção)
