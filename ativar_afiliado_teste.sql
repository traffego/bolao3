-- Script para ativar o primeiro jogador como afiliado para teste
UPDATE jogador 
SET afiliado_ativo = 'ativo' 
WHERE id = 1 AND codigo_afiliado IS NOT NULL;

-- Verificar se foi atualizado
SELECT id, nome, codigo_afiliado, afiliado_ativo 
FROM jogador 
WHERE id = 1;