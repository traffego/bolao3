-- Migração para Sistema de Afiliados Unificado
-- Adiciona campos necessários na tabela jogador
-- Data: 2024

-- Adicionar novos campos na tabela jogador
ALTER TABLE jogador 
ADD COLUMN codigo_afiliado VARCHAR(20) UNIQUE DEFAULT NULL COMMENT 'Código único do afiliado',
ADD COLUMN ref_indicacao VARCHAR(20) DEFAULT NULL COMMENT 'Código do afiliado que indicou este jogador',
ADD COLUMN afiliado_ativo TINYINT(1) DEFAULT 0 COMMENT 'Se o jogador está ativo como afiliado (0=não, 1=sim)';

-- Criar índices para melhor performance
CREATE INDEX idx_jogador_codigo_afiliado ON jogador(codigo_afiliado);
CREATE INDEX idx_jogador_ref_indicacao ON jogador(ref_indicacao);
CREATE INDEX idx_jogador_afiliado_ativo ON jogador(afiliado_ativo);

-- Migrar dados existentes da tabela afiliados para jogador
-- Primeiro, gerar códigos únicos para jogadores existentes que não têm
UPDATE jogador 
SET codigo_afiliado = CONCAT('af', UPPER(SUBSTRING(MD5(CONCAT(id, email, UNIX_TIMESTAMP())), 1, 8)))
WHERE codigo_afiliado IS NULL;

-- Como estamos unificando tudo na tabela jogador,
-- não precisamos migrar dados de tabelas afiliados antigas
-- Os novos afiliados serão criados diretamente na tabela jogador
-- através do sistema de cadastro atualizado

-- Verificar integridade dos dados
-- Mostrar estatísticas após migração
SELECT 
    'Total de jogadores' as tipo,
    COUNT(*) as quantidade
FROM jogador
UNION ALL
SELECT 
    'Jogadores com código de afiliado' as tipo,
    COUNT(*) as quantidade
FROM jogador 
WHERE codigo_afiliado IS NOT NULL
UNION ALL
SELECT 
    'Jogadores afiliados ativos' as tipo,
    COUNT(*) as quantidade
FROM jogador 
WHERE afiliado_ativo = 1
UNION ALL
SELECT 
    'Jogadores com indicação' as tipo,
    COUNT(*) as quantidade
FROM jogador 
WHERE ref_indicacao IS NOT NULL;

-- IMPORTANTE: Execute este script em ambiente de teste primeiro!
-- Faça backup da base de dados antes de executar em produção!

-- Sistema de afiliados agora unificado na tabela jogador
-- As tabelas antigas (afiliados, afiliados_indicacoes, afiliados_comissoes)
-- podem ser mantidas para histórico ou removidas conforme necessário