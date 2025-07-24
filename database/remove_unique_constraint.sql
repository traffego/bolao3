-- Remove a restrição de chave única da tabela palpites
ALTER TABLE palpites
DROP INDEX unique_palpite; 