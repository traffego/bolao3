# Webhook Troubleshooting Guide

Este guia fornece informações detalhadas para identificar e resolver problemas relacionados ao webhook do sistema de pagamentos EFI Pay.

## Índice

1. [Problemas Comuns](#problemas-comuns)
2. [Diagnóstico Passo a Passo](#diagnóstico-passo-a-passo)
3. [Ferramentas de Diagnóstico](#ferramentas-de-diagnóstico)
4. [Configuração do Webhook](#configuração-do-webhook)
5. [Resolução de Problemas Específicos](#resolução-de-problemas-específicos)
6. [Monitoramento e Manutenção](#monitoramento-e-manutenção)
7. [Comandos Úteis](#comandos-úteis)

## Problemas Comuns

### 1. URL Localhost em Produção

**Sintoma:** Webhook não funciona em produção, logs mostram tentativas de registro falhas.

**Causa:** O banco de dados contém uma URL localhost que não é acessível pela EFI Pay.

**Solução:**
```bash
# Execute o script de correção
php scripts/fix_webhook_config.php

# Ou corrija manualmente via admin panel
# Admin → Configurações → Pagamentos → EFI Pay
```

### 2. Certificado SSL Inválido/Expirado

**Sintoma:** Erros de SSL ao registrar webhook ou receber notificações.

**Causa:** Certificado SSL do servidor expirado ou configurado incorretamente.

**Soluções:**
- Renove o certificado SSL do servidor
- Verifique se o certificado cobre o domínio correto
- Teste SSL: `openssl s_client -connect seu-dominio.com:443`

### 3. Webhook Não Registrado na EFI Pay

**Sintoma:** Pagamentos não são processados automaticamente.

**Causa:** Webhook não foi registrado ou foi removido da EFI Pay.

**Solução:**
```bash
# Verifique status do webhook
php scripts/webhook_monitor.php --check=efi --verbose

# Re-registre o webhook
php scripts/webhook_monitor.php --check=efi --fix
```

### 4. Endpoint Webhook Não Acessível

**Sintoma:** EFI Pay não consegue entregar notificações.

**Causa:** URL não está acessível publicamente ou retorna erro.

**Diagnóstico:**
```bash
# Teste conectividade
curl -X POST https://seu-dominio.com/api/webhook_pix.php

# Verifique logs do servidor web
tail -f /var/log/nginx/error.log
```

## Diagnóstico Passo a Passo

### Passo 1: Verificação Básica

```bash
# Execute diagnóstico completo
php scripts/webhook_monitor.php --check=all --verbose
```

### Passo 2: Verificar Configuração

1. **Via Admin Panel:**
   - Acesse `/admin/webhook-diagnostics.php`
   - Verifique status geral
   - Execute testes de conectividade

2. **Via Linha de Comando:**
   ```bash
   # Verificar consistência da configuração
   php scripts/webhook_monitor.php --check=config --verbose
   ```

### Passo 3: Testar Conectividade

```bash
# Teste EFI Pay
php scripts/webhook_monitor.php --check=efi --verbose

# Teste endpoint webhook
curl -v -X POST -H "Content-Type: application/json" \
  -d '{"test": true}' \
  https://seu-dominio.com/api/webhook_pix.php
```

### Passo 4: Verificar Logs

```bash
# Logs da aplicação
tail -f logs/app.log | grep -i webhook

# Logs de pagamento
tail -f logs/payment.log

# Logs de erro PHP
tail -f logs/php_errors.log
```

## Ferramentas de Diagnóstico

### 1. Admin Panel - Webhook Diagnostics
- **URL:** `/admin/webhook-diagnostics.php`
- **Funcionalidades:**
  - Status geral do sistema
  - Validação de configuração
  - Testes de conectividade
  - Re-registro de webhook
  - Logs recentes

### 2. Script CLI - Webhook Monitor
```bash
# Uso básico
php scripts/webhook_monitor.php --check=all --verbose

# Com correção automática
php scripts/webhook_monitor.php --check=all --fix

# Com alertas por email
php scripts/webhook_monitor.php --check=all --alert=admin@exemplo.com

# Formato JSON
php scripts/webhook_monitor.php --check=all --format=json
```

### 3. API Endpoint - Webhook Status
```bash
# Status básico
curl https://seu-dominio.com/api/webhook_status.php

# Status completo (requer autenticação)
curl -H "X-API-Key: SUA_API_KEY" \
  "https://seu-dominio.com/api/webhook_status.php?checks=all&details=true"
```

### 4. Script de Correção
```bash
# Corrigir configuração automaticamente
php scripts/fix_webhook_config.php
```

## Configuração do Webhook

### URLs Corretas por Ambiente

**Produção:**
```
https://bolao.traffego.agency/api/webhook_pix.php
```

**Desenvolvimento:**
```
http://localhost/bolao3/api/webhook_pix.php (apenas para testes locais)
```

### Configuração no Banco de Dados

A configuração está armazenada na tabela `configuracoes`:

```sql
SELECT valor FROM configuracoes 
WHERE nome_configuracao = 'efi_pix_config' 
AND categoria = 'pagamentos';
```

Estrutura da configuração:
```json
{
  "client_id": "Client_Id...",
  "client_secret": "Client_Secret...",
  "pix_key": "sua-chave-pix",
  "ambiente": "producao",
  "webhook_url": "https://bolao.traffego.agency/api/webhook_pix.php",
  "webhook_fatal_failure": false
}
```

## Resolução de Problemas Específicos

### Erro: "Webhook URL não foi fornecida"

1. Verifique se existe configuração no banco
2. Execute script de correção:
   ```bash
   php scripts/fix_webhook_config.php
   ```

### Erro: "URL localhost não é válida em ambiente de produção"

1. **Correção Automática:**
   ```bash
   php scripts/fix_webhook_config.php
   ```

2. **Correção Manual:**
   - Acesse Admin → Configurações → Pagamentos
   - Altere webhook URL para: `https://bolao.traffego.agency/api/webhook_pix.php`

### Erro: "Webhook deve usar protocolo HTTPS"

- Certifique-se de que a URL começa com `https://`
- Verifique se o certificado SSL está válido
- Configure redirecionamento HTTP → HTTPS no servidor

### Erro: "Certificado não encontrado"

1. Verifique se o certificado existe:
   ```bash
   ls -la config/certificates/certificate.p12
   ```

2. Se não existir, gere um novo certificado no painel da EFI Pay
3. Faça upload via Admin → Configurações → Pagamentos

### Erro: HTTP 404 no endpoint webhook

1. Verifique se o arquivo existe:
   ```bash
   ls -la api/webhook_pix.php
   ```

2. Verifique configuração do servidor web (nginx/apache)
3. Teste acesso direto: `curl https://seu-dominio.com/api/webhook_pix.php`

### Problemas de Autenticação EFI Pay

1. Verifique credenciais:
   - Client ID e Client Secret corretos
   - Certificado P12 válido e não expirado

2. Teste conectividade:
   ```bash
   php scripts/webhook_monitor.php --check=efi --verbose
   ```

3. Se necessário, gere novas credenciais no painel EFI Pay

## Monitoramento e Manutenção

### Monitoramento Automático (Cron)

Adicione ao crontab para monitoramento automático:

```bash
# Verificação a cada 5 minutos
*/5 * * * * cd /caminho/para/bolao3 && php scripts/webhook_monitor.php --check=basic --format=json --output=logs/webhook_status.json

# Verificação completa diária com alertas
0 9 * * * cd /caminho/para/bolao3 && php scripts/webhook_monitor.php --check=all --alert=admin@exemplo.com --verbose

# Correção automática semanal
0 2 * * 1 cd /caminho/para/bolao3 && php scripts/webhook_monitor.php --check=all --fix --verbose
```

### Alertas por Email

Configure alertas automáticos:

```bash
# Enviar email se problemas forem detectados
php scripts/webhook_monitor.php --check=all --alert=admin@exemplo.com
```

### Logs Importantes

Monitore estes arquivos de log:

- `logs/app.log` - Logs gerais da aplicação
- `logs/payment.log` - Logs específicos de pagamento
- `logs/php_errors.log` - Erros PHP
- `/var/log/nginx/access.log` - Logs do servidor web

## Comandos Úteis

### Verificação Rápida de Status
```bash
# Status básico
php scripts/webhook_monitor.php --check=basic

# Status com saída JSON
php scripts/webhook_monitor.php --check=all --format=json

# Status via API
curl -s https://seu-dominio.com/api/webhook_status.php | jq '.'
```

### Correção de Problemas
```bash
# Corrigir configuração
php scripts/fix_webhook_config.php

# Corrigir e re-registrar webhook
php scripts/webhook_monitor.php --check=all --fix
```

### Testes de Conectividade
```bash
# Teste endpoint webhook
curl -X POST -H "Content-Type: application/json" \
  -d '{"test": true}' \
  https://bolao.traffego.agency/api/webhook_pix.php

# Teste SSL
openssl s_client -connect bolao.traffego.agency:443 -servername bolao.traffego.agency

# Teste DNS
nslookup bolao.traffego.agency
```

### Verificação de Certificados
```bash
# Verificar certificado P12 da EFI
openssl pkcs12 -info -in config/certificates/certificate.p12 -nokeys

# Verificar certificado SSL do servidor
openssl x509 -in /etc/ssl/certs/seu-certificado.crt -text -noout
```

## Contato e Suporte

Se os problemas persistirem após seguir este guia:

1. **Verifique os logs** detalhadamente
2. **Execute diagnóstico completo** com `--verbose`
3. **Colete informações** do sistema e configuração
4. **Documente os passos** já tentados

### Informações Úteis para Suporte

- Versão do PHP: `php -v`
- Status do webhook: `php scripts/webhook_monitor.php --check=all --format=json`
- Logs recentes: `tail -50 logs/app.log`
- Configuração (sanitizada): Via `/admin/webhook-diagnostics.php`

---

**Última atualização:** {{ date('Y-m-d') }}
**Versão do sistema:** 1.0.0