-- Script para corrigir o default do campo afiliado_ativo
-- Mudando de 'inativo' para 'ativo' conforme solicitado

USE bolao;

-- Alterar o default do campo afiliado_ativo para 'ativo'
ALTER TABLE jogador 
MODIFY COLUMN afiliado_ativo ENUM('inativo','ativo') DEFAULT 'ativo';

-- Verificar a estrutura atualizada
DESCRIBE jogador;

-- Opcional: Atualizar registros existentes que estão como 'inativo' para 'ativo'
-- (descomente se quiser ativar todos os usuários existentes)
-- UPDATE jogador SET afiliado_ativo = 'ativo' WHERE afiliado_ativo = 'inativo';

SELECT 'Campo afiliado_ativo atualizado com sucesso - default agora é ATIVO' as status;