CREATE TABLE IF NOT EXISTS configuracoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome_configuracao VARCHAR(100) NOT NULL,
    valor TEXT NOT NULL,
    categoria VARCHAR(50) NOT NULL,
    descricao TEXT,
    data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    data_atualizacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_nome_categoria (nome_configuracao, categoria)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Inserir configuração da EFIBANK
INSERT INTO configuracoes (nome_configuracao, valor, categoria, descricao) VALUES 
('efi_pix_config', 
'{"ambiente":"producao","client_id":"Client_Id_3e9ce7b7f569d0a4aa8f9ec8b172c3ed7dd9d948","client_secret":"Client_Secret_31e8f33edba74286002f4c91a2df6896f2764fd1","pix_key":"60409292-a359-4992-9f5f-5886bace6fe6","webhook_url":"https://bolao.traffego.agency/api/webhook_pix.php"}',
'pagamentos',
'Configurações da API Pix da Efí')
ON DUPLICATE KEY UPDATE 
valor = VALUES(valor),
descricao = VALUES(descricao); 