-- Migração para adicionar configuração de webhook fatal do EfiPixManager
-- Data: 2025-08-05
-- Descrição: Adiciona configuração para controlar se falhas de webhook devem ser fatais

-- Inserir configuração padrão
INSERT INTO configuracoes (nome_configuracao, valor, categoria, descricao, criado_em, atualizado_em) 
VALUES (
    'efi_webhook_fatal',
    '{"development": true, "staging": true, "production": false}',
    'pagamentos',
    'Configuração que determina se falhas no registro de webhook EFI devem ser fatais por ambiente. true = interrompe execução, false = apenas loga o erro.',
    NOW(),
    NOW()
) 
ON DUPLICATE KEY UPDATE 
    descricao = VALUES(descricao),
    atualizado_em = NOW();

-- Verificar se a configuração foi inserida
SELECT 
    nome_configuracao,
    valor,
    categoria,
    descricao,
    criado_em
FROM configuracoes 
WHERE nome_configuracao = 'efi_webhook_fatal';

-- Exemplo de como atualizar a configuração via SQL
-- UPDATE configuracoes 
-- SET valor = '{"development": false, "staging": true, "production": false}',
--     atualizado_em = NOW()
-- WHERE nome_configuracao = 'efi_webhook_fatal' 
-- AND categoria = 'pagamentos';
