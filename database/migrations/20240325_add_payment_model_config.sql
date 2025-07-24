-- Inserir ou atualizar a configuração do modelo de pagamento
INSERT INTO configuracoes 
(nome_configuracao, valor, categoria, descricao) 
VALUES 
('modelo_pagamento', 'por_aposta', 'pagamento', 'Define como os pagamentos são processados: por_aposta (individual) ou conta_saldo (débito em conta)')
ON DUPLICATE KEY UPDATE 
valor = VALUES(valor),
descricao = VALUES(descricao),
data_atualizacao = CURRENT_TIMESTAMP;

-- Adicionar CHECK CONSTRAINT para garantir valores válidos
ALTER TABLE configuracoes
ADD CONSTRAINT chk_modelo_pagamento 
CHECK (
    nome_configuracao != 'modelo_pagamento' OR 
    valor IN ('por_aposta', 'conta_saldo')
); 