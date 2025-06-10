-- Backup dos dados existentes
CREATE TEMPORARY TABLE temp_configuracoes AS SELECT * FROM configuracoes;

-- Dropar a tabela antiga
DROP TABLE IF EXISTS configuracoes;

-- Criar a nova estrutura
CREATE TABLE IF NOT EXISTS configuracoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome_configuracao VARCHAR(100) NOT NULL,
    valor TEXT NOT NULL,
    categoria VARCHAR(50) NOT NULL DEFAULT 'geral',
    descricao TEXT NULL,
    data_atualizacao DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_config (nome_configuracao, categoria)
);

-- Reinsere os dados com as categorias apropriadas
INSERT INTO configuracoes (nome_configuracao, valor, categoria, descricao)
SELECT 
    nome_configuracao,
    valor,
    CASE 
        WHEN nome_configuracao LIKE 'pontos_%' THEN 'pontuacao'
        WHEN nome_configuracao = 'api_football' THEN 'api'
        WHEN nome_configuracao = 'pagamento' THEN 'pagamento'
        ELSE 'geral'
    END as categoria,
    descricao
FROM temp_configuracoes;

-- Insere as configurações padrão que podem estar faltando
INSERT IGNORE INTO configuracoes (nome_configuracao, valor, categoria, descricao) 
VALUES 
('pontos_acerto_exato', '10', 'pontuacao', 'Pontos por acerto exato'),
('pontos_acerto_parcial', '5', 'pontuacao', 'Pontos por acerto parcial (vencedor ou empate)'),
('pontos_acerto_vencedor', '3', 'pontuacao', 'Pontos por acerto apenas do vencedor'),
('api_football', '{"api_key":"API_KEY_HERE","base_url":"https://api.football-data.org/v4"}', 'api', 'Configurações da API de futebol'),
('pagamento', '{"pix_key":"EMAIL@EXAMPLE.COM"}', 'pagamento', 'Configurações de pagamento'),
('site_name', 'Bolão Vitimba', 'geral', 'Nome do sistema');

-- Drop da tabela temporária
DROP TEMPORARY TABLE IF EXISTS temp_configuracoes; 