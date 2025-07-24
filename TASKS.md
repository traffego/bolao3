# Tarefas de Atualização do Sistema

## Banco de Dados

### Limpeza e Ajustes
- [x] Remover tabela `metodos_pagamento`
- [x] Simplificar coluna `metodo_pagamento` na tabela `transacoes` para usar apenas 'pix'
- [x] Remover ou atualizar configuração `modelo_pagamento` na tabela `configuracoes`
- [x] Verificar e ajustar todas as foreign keys e dependências relacionadas a pagamentos

### Otimizações
- [x] Garantir que a coluna `afeta_saldo` na tabela `transacoes` seja sempre 1 para transações aprovadas
- [x] Criar índices apropriados para melhor performance em consultas de saldo e transações
- [x] Verificar e otimizar triggers existentes relacionados a transações

## Backend

### Depósitos
- [x] Criar endpoint para iniciar depósito via PIX
- [x] Integrar geração de QR Code/Copia e Cola com a EFÍ
- [x] Implementar webhook para recebimento de confirmação de pagamento
- [x] Criar sistema de atualização automática de saldo após confirmação
- [x] Implementar sistema de notificação ao usuário sobre status do depósito

### Saques
- [x] Implementar processador de solicitação de saque
- [x] Implementar validação de saldo disponível
- [x] Criar sistema de aprovação/rejeição de saques pelo admin
- [x] Implementar registro de dados de pagamento manual pelo admin
- [x] Implementar sistema de notificação sobre status do saque

### Geral
- [x] Atualizar sistema de logs para registrar todas as operações financeiras
- [x] Implementar validações de segurança adicionais
- [x] Criar endpoints para consulta de histórico de transações
- [x] Implementar sistema de cache para consultas frequentes de saldo

## Frontend

### Interface de Depósitos
- [ ] Criar página de depósito
- [ ] Implementar formulário com valor do depósito
- [ ] Desenvolver exibição do QR Code e código PIX
- [ ] Criar interface de acompanhamento do status do depósito
- [ ] Implementar atualização em tempo real do status

### Interface de Saques
- [ ] Criar página de solicitação de saque
- [ ] Implementar formulário com dados do saque (valor e chave PIX)
- [ ] Desenvolver interface de acompanhamento de saques
- [ ] Criar área de histórico de saques
- [ ] Implementar exibição de status e notificações

### Área Administrativa
- [ ] Criar interface para aprovação/rejeição de saques
- [ ] Implementar dashboard com visão geral de transações
- [ ] Desenvolver relatórios de movimentações
- [ ] Criar sistema de alertas para transações suspeitas

## Testes

### Backend
- [ ] Criar testes unitários para novos endpoints
- [ ] Implementar testes de integração com a EFÍ
- [ ] Testar webhooks e callbacks
- [ ] Validar sistema de saldo e transações

### Frontend
- [ ] Testar fluxo completo de depósito
- [ ] Testar fluxo completo de saque
- [ ] Validar responsividade das novas interfaces
- [ ] Testar em diferentes navegadores

## Documentação
- [ ] Atualizar documentação da API
- [ ] Criar documentação do novo fluxo de pagamentos
- [ ] Documentar processos administrativos
- [ ] Criar guia de resolução de problemas

## Monitoramento
- [ ] Implementar logs específicos para transações
- [ ] Criar alertas para falhas em pagamentos
- [ ] Monitorar tempos de resposta da EFÍ
- [ ] Implementar métricas de sucesso/falha de transações

## Segurança
- [ ] Revisar permissões de acesso
- [ ] Implementar validações adicionais para transações
- [ ] Criar sistema de detecção de fraudes
- [ ] Revisar práticas de segurança com dados sensíveis

---

### Status do Projeto
- Data de Início: 25/03/2024
- Última Atualização: 25/03/2024 16:40
- Status Geral: Em Andamento

### Notas
- Priorizar implementação de depósitos
- Manter compatibilidade com dados existentes
- Documentar todas as alterações realizadas