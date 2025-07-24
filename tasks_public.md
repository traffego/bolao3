# Lista de Tarefas - Adaptação da Interface Pública

## Status
🔴 Não iniciado | 🟡 Em andamento | 🟢 Concluído

## 1. Adaptação do Salvamento de Palpites
- 🟢 Revisar `salvar-palpite.php`:
  - ✅ Remover campos antigos (txid_pagamento)
  - ✅ Implementar lógica por modelo de pagamento
  - ✅ Criar transações apropriadas
  - ✅ Validar saldo quando necessário
  - ✅ Adicionar logs de operação
  - ✅ Garantir transações do banco (begin/commit/rollback)

## 2. Adaptação da Lista de Palpites
- 🔴 Revisar `meus-palpites.php`:
  - Adicionar informações de pagamento
  - Mostrar status da transação
  - Adicionar link para pagamento quando pendente
  - Implementar filtros por status
  - Melhorar exibição de erros/sucesso
  - Adicionar tooltips informativos

## 3. Adaptação da Verificação de Pagamento
- 🔴 Revisar `ajax/check-payment.php`:
  - Adaptar para novo sistema de transações
  - Implementar verificação por modelo
  - Melhorar retorno de status
  - Adicionar mais informações no retorno
  - Implementar cache de consultas
  - Adicionar logs de verificação

## 4. Melhorias no JavaScript
- 🔴 Revisar `public/js/scripts.js`:
  - Adicionar validações de saldo
  - Implementar verificações em tempo real
  - Melhorar feedback visual
  - Adicionar animações de loading
  - Implementar retry em caso de erro
  - Melhorar tratamento de erros

## 5. Melhorias no CSS
- 🔴 Revisar `public/css/styles.css`:
  - Adicionar estilos para novos elementos
  - Criar classes para status
  - Melhorar responsividade
  - Adicionar animações
  - Padronizar cores de status
  - Melhorar acessibilidade

## 6. Testes de Interface
- 🔴 Testar fluxo de palpite por PIX:
  - Criação do palpite
  - Redirecionamento para pagamento
  - Confirmação e retorno
  - Exibição na lista
  - Tratamento de erros
  - Responsividade

- 🔴 Testar fluxo de palpite por saldo:
  - Verificação de saldo
  - Criação do palpite
  - Débito automático
  - Atualização da lista
  - Histórico de transações
  - Responsividade

## 7. Documentação
- 🔴 Documentar alterações:
  - Atualizar README
  - Documentar novos parâmetros
  - Criar guia de troubleshooting
  - Documentar códigos de erro
  - Adicionar exemplos de uso
  - Criar FAQ

## Histórico de Atualizações
- 25/03/2024: Criação da lista de tarefas para adaptação da interface pública
- 25/03/2024: ✅ Concluída adaptação do salvamento de palpites 