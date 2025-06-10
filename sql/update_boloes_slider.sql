-- Adicionar coluna para imagem do slider
ALTER TABLE dados_boloes ADD COLUMN imagem_slider VARCHAR(255) NULL AFTER nome;

-- Criar índice para melhorar performance de consultas
CREATE INDEX idx_boloes_status ON dados_boloes(status); 