-- Remover colunas não utilizadas da tabela palpites
ALTER TABLE palpites
    DROP COLUMN pontuacao,
    DROP COLUMN data_atualizacao; 