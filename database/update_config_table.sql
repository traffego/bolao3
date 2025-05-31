-- Adicionar colunas se não existirem
ALTER TABLE configuracoes 
ADD COLUMN IF NOT EXISTS nome_configuracao VARCHAR(100) NOT NULL AFTER id,
ADD COLUMN IF NOT EXISTS valor TEXT NOT NULL AFTER nome_configuracao,
ADD COLUMN IF NOT EXISTS categoria VARCHAR(50) NOT NULL AFTER valor,
ADD COLUMN IF NOT EXISTS descricao TEXT AFTER categoria,
ADD COLUMN IF NOT EXISTS data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER descricao,
ADD COLUMN IF NOT EXISTS data_atualizacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER data_criacao;

-- Adicionar índice único se não existir
ALTER TABLE configuracoes
ADD UNIQUE INDEX IF NOT EXISTS uk_nome_categoria (nome_configuracao, categoria);

-- Inserir/Atualizar configuração da EFIBANK
INSERT INTO configuracoes (nome_configuracao, valor, categoria, descricao) VALUES 
('efi_pix_config', 
'{"ambiente":"producao","client_id":"Client_Id_3e9ce7b7f569d0a4aa8f9ec8b172c3ed7dd9d948","client_secret":"Client_Secret_31e8f33edba74286002f4c91a2df6896f2764fd1","pix_key":"60409292-a359-4992-9f5f-5886bace6fe6","webhook_url":"http://localhost/bolao3/api/webhook_pix.php"}',
'pagamentos',
'Configurações da API Pix da Efí')
ON DUPLICATE KEY UPDATE 
valor = VALUES(valor),
descricao = VALUES(descricao); 