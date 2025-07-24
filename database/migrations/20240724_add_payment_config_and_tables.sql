-- Adicionar configurações de pagamento
INSERT IGNORE INTO configuracoes (nome_configuracao, valor, categoria, descricao) 
VALUES 
('deposito_minimo', '10.00', 'pagamento', 'Valor mínimo para depósito'),
('deposito_maximo', '5000.00', 'pagamento', 'Valor máximo para depósito');

-- Criar tabela de resultados se não existir
CREATE TABLE IF NOT EXISTS resultados (
    id INT AUTO_INCREMENT PRIMARY KEY,
    jogo_id INT NOT NULL,
    gols_casa INT NULL,
    gols_visitante INT NULL,
    data_atualizacao DATETIME DEFAULT CURRENT_TIMESTAMP,
    status ENUM('parcial', 'final') DEFAULT 'parcial',
    FOREIGN KEY (jogo_id) REFERENCES jogos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Criar tabela de participações se não existir
CREATE TABLE IF NOT EXISTS participacoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bolao_id INT NOT NULL,
    jogador_id INT NOT NULL,
    data_entrada DATETIME DEFAULT CURRENT_TIMESTAMP,
    status TINYINT(1) DEFAULT 1,
    FOREIGN KEY (bolao_id) REFERENCES dados_boloes(id) ON DELETE CASCADE,
    FOREIGN KEY (jogador_id) REFERENCES jogador(id) ON DELETE CASCADE,
    UNIQUE KEY unique_participacao (bolao_id, jogador_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci; 