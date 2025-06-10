-- Script to create missing tables for Bolão Vitimba

-- Participacoes (Jogadores em Bolões)
CREATE TABLE IF NOT EXISTS participacoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    jogador_id INT NOT NULL,
    bolao_id INT NOT NULL,
    data_entrada DATETIME DEFAULT CURRENT_TIMESTAMP,
    status TINYINT(1) DEFAULT 1,
    FOREIGN KEY (jogador_id) REFERENCES jogador(id) ON DELETE CASCADE,
    FOREIGN KEY (bolao_id) REFERENCES boloes(id) ON DELETE CASCADE,
    UNIQUE KEY (jogador_id, bolao_id)
); 