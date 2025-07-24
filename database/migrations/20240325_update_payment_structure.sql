-- Remover campos da tabela jogador
ALTER TABLE jogador
DROP COLUMN txid_pagamento,
DROP COLUMN pagamento_confirmado,
DROP COLUMN saldo;

-- Adicionar campos na tabela transacoes
ALTER TABLE transacoes
ADD COLUMN txid VARCHAR(100) DEFAULT NULL,
ADD COLUMN palpite_id INT DEFAULT NULL,
ADD COLUMN metodo_pagamento ENUM('pix', 'transferencia_bancaria', 'cartao_credito') DEFAULT NULL,
ADD COLUMN afeta_saldo BOOLEAN DEFAULT FALSE,
ADD UNIQUE KEY uk_txid (txid),
ADD CONSTRAINT fk_transacao_palpite FOREIGN KEY (palpite_id) REFERENCES palpites(id);

-- Atualizar tabela contas (remover campos desnecessários e manter apenas o essencial)
ALTER TABLE contas
DROP COLUMN saldo; 