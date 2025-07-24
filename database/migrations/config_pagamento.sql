-- Adicionar configuração de modelo de pagamento
INSERT INTO configuracoes (nome_configuracao, valor, categoria, descricao) VALUES 
('modelo_pagamento', 'por_aposta', 'pagamento', 'Modelo de pagamento: por_aposta (paga cada aposta) ou conta_saldo (usa saldo da conta)');

-- Adicionar outras configurações de pagamento se não existirem
INSERT IGNORE INTO configuracoes (nome_configuracao, valor, categoria, descricao) VALUES 
('deposito_minimo', '10.00', 'pagamento', 'Valor mínimo para depósito'),
('deposito_maximo', '5000.00', 'pagamento', 'Valor máximo para depósito'),
('saque_minimo', '30.00', 'pagamento', 'Valor mínimo para saque'),
('saque_maximo', '5000.00', 'pagamento', 'Valor máximo para saque'),
('taxa_saque', '0.00', 'pagamento', 'Taxa cobrada por saque'),
('prazo_saque', '2', 'pagamento', 'Prazo em dias úteis para processar saques'),
('metodos_deposito', '["pix", "cartao_credito"]', 'pagamento', 'Métodos de pagamento aceitos para depósito'),
('metodos_saque', '["pix", "transferencia_bancaria"]', 'pagamento', 'Métodos de pagamento aceitos para saque'); 