-- Backup da tabela antiga
CREATE TABLE IF NOT EXISTS palpites_backup AS SELECT * FROM palpites;

-- Drop foreign keys
ALTER TABLE palpites
DROP FOREIGN KEY palpites_ibfk_1,
DROP FOREIGN KEY palpites_ibfk_2,
DROP FOREIGN KEY palpites_ibfk_3;

-- Drop unique key
ALTER TABLE palpites DROP INDEX unique_palpite;

-- Drop e recriar a tabela palpites
DROP TABLE IF EXISTS palpites;
CREATE TABLE IF NOT EXISTS palpites (
    id INT AUTO_INCREMENT PRIMARY KEY,
    jogador_id INT NOT NULL,
    bolao_id INT NOT NULL,
    palpites JSON NOT NULL COMMENT 'JSON com os palpites no formato: {"jogo_id": "1"} onde 1=casa vence, 0=empate, 2=visitante vence',
    data_palpite DATETIME DEFAULT CURRENT_TIMESTAMP,
    status ENUM('pendente', 'pago', 'cancelado') DEFAULT 'pendente',
    FOREIGN KEY (jogador_id) REFERENCES jogador(id) ON DELETE CASCADE,
    FOREIGN KEY (bolao_id) REFERENCES boloes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Migrar dados da tabela antiga
INSERT INTO palpites (jogador_id, bolao_id, palpites, data_palpite)
SELECT 
    jogador_id,
    bolao_id,
    JSON_OBJECT(
        'jogos',
        JSON_OBJECTAGG(
            jogo_id,
            CASE 
                WHEN gols_casa > gols_visitante THEN '1'
                WHEN gols_casa < gols_visitante THEN '2'
                ELSE '0'
            END
        )
    ),
    MIN(data_palpite)
FROM palpites_backup
GROUP BY jogador_id, bolao_id; 