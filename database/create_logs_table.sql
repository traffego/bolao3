CREATE TABLE IF NOT EXISTS logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tipo VARCHAR(50) NOT NULL COMMENT 'Tipo de log (ex: configuracao, pagamento, etc)',
    descricao TEXT NOT NULL COMMENT 'Descrição detalhada do log',
    usuario_id INT NOT NULL COMMENT 'ID do usuário que realizou a ação',
    data_hora TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Data e hora do registro',
    dados_adicionais JSON DEFAULT NULL COMMENT 'Dados adicionais em formato JSON (opcional)',
    ip_address VARCHAR(45) DEFAULT NULL COMMENT 'Endereço IP do usuário',
    FOREIGN KEY (usuario_id) REFERENCES jogador(id) ON DELETE RESTRICT,
    INDEX idx_tipo (tipo),
    INDEX idx_usuario (usuario_id),
    INDEX idx_data (data_hora)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Inserir alguns tipos de logs comuns para referência
INSERT INTO configuracoes (nome_configuracao, valor, categoria, descricao) VALUES 
('tipos_logs', 
'["configuracao","pagamento","palpite","bolao","usuario","sistema"]',
'sistema',
'Tipos de logs permitidos no sistema')
ON DUPLICATE KEY UPDATE 
valor = VALUES(valor),
descricao = VALUES(descricao); 