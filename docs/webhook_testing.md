# Webhook Testing Documentation

## Overview

O sistema de teste de webhook foi refatorado para evitar a inclusão direta do arquivo `webhook_pix.php`. Agora oferece duas abordagens diferentes para testar webhooks PIX.

## Mudanças Implementadas

### 1. Refatoração do `webhook_pix.php`

- **Função `processPixWebhook()`**: Toda a lógica de processamento foi extraída para uma função reutilizável
- **Detecção de contexto**: O arquivo detecta se está sendo incluído ou executado diretamente
- **Variável global `$GLOBALS['is_webhook_test']`**: Usado para identificar quando está em modo de teste

### 2. Novo `webhook_test.php`

O arquivo de teste agora oferece duas opções:

#### Opção 1: Teste via Função (Padrão)
```
POST /api/webhook_test.php
```
- Chama diretamente a função `processPixWebhook()`
- Mais rápido e eficiente
- Permite debugging mais fácil
- Usa a mesma conexão de banco de dados

#### Opção 2: Teste via HTTP
```
POST /api/webhook_test.php?type=http
```
- Faz uma requisição HTTP real para o endpoint do webhook
- Simula completamente o comportamento de produção
- Testa também a conectividade de rede
- Mais realístico para testes end-to-end

## Como Usar

### Exemplo de Payload de Teste

```json
{
  "pix": [
    {
      "txid": "TESTE123456789012345678901234",
      "valor": "10.00",
      "endToEndId": "E123456789012345678901234567890123456"
    }
  ]
}
```

### Teste via Função (Recomendado)

```bash
curl -X POST https://seudominio.com/api/webhook_test.php \
  -H "Content-Type: application/json" \
  -b "PHPSESSID=sua_sessao_admin" \
  -d '{
    "pix": [
      {
        "txid": "TESTE123456789012345678901234",
        "valor": "10.00"
      }
    ]
  }'
```

### Teste via HTTP

```bash
curl -X POST "https://seudominio.com/api/webhook_test.php?type=http" \
  -H "Content-Type: application/json" \
  -b "PHPSESSID=sua_sessao_admin" \
  -d '{
    "pix": [
      {
        "txid": "TESTE123456789012345678901234",
        "valor": "10.00"
      }
    ]
  }'
```

## Formatos de Payload Suportados

O webhook suporta múltiplos formatos para máxima compatibilidade:

### Formato 1: EFI Pay Oficial
```json
{
  "pix": [
    {
      "txid": "SEU_TXID_AQUI",
      "valor": "10.00",
      "endToEndId": "E123456789..."
    }
  ]
}
```

### Formato 2: TXID no Root
```json
{
  "txid": "SEU_TXID_AQUI",
  "valor": "10.00"
}
```

### Formato 3: EndToEndId no Root
```json
{
  "endToEndId": "E123456789...",
  "valor": "10.00"
}
```

## Resposta do Teste

### Sucesso
```json
{
  "status": "success",
  "test_type": "function",
  "message": "Webhook testado com sucesso",
  "result": {
    "status": "success",
    "txid": "TESTE123456789012345678901234",
    "transacao_id": "123",
    "http_code": 200
  },
  "received_payload": { ... }
}
```

### Erro
```json
{
  "status": "error",
  "test_type": "function",
  "message": "Transação não encontrada para TXID: TESTE123456789012345678901234",
  "received_payload": { ... }
}
```

## Segurança

- **Apenas administradores**: Endpoint protegido por sessão de admin
- **Método POST obrigatório**: Aceita apenas requisições POST
- **Validação de payload**: Verifica se o JSON é válido
- **Logging completo**: Todas as operações são logadas

## Logging

Os testes são logados com o contexto `webhook_test` e incluem:
- Tipo de teste executado
- Payload recebido
- IP do solicitante
- Resultado do processamento
- Erros, se houver

## Benefícios da Refatoração

1. **Separação de responsabilidades**: Lógica de negócio separada da apresentação
2. **Testabilidade**: Função pode ser testada isoladamente
3. **Flexibilidade**: Suporte a diferentes tipos de teste
4. **Manutenibilidade**: Código mais organizado e fácil de manter
5. **Debugging**: Melhor rastreabilidade de erros
6. **Reutilização**: Função pode ser usada em outros contextos

## Troubleshooting

### Erro 403: Acesso Negado
- Certifique-se de estar logado como administrador
- Verifique se a sessão não expirou

### Erro 405: Método Não Permitido
- Use apenas requisições POST
- Verifique se não está usando GET por engano

### Erro 400: Payload Inválido
- Verifique se o JSON está bem formado
- Certifique-se de incluir o header Content-Type: application/json

### Transação Não Encontrada
- Verifique se existe uma transação com o TXID fornecido no banco de dados
- Use um TXID válido de uma transação existente para testes reais
