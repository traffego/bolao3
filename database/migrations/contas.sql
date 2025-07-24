-- Tabela de contas dos jogadores
CREATE TABLE IF NOT EXISTS contas (
    id INT PRIMARY KEY AUTO_INCREMENT,
    jogador_id INT NOT NULL,
    saldo DECIMAL(10,2) DEFAULT 0.00,
    status ENUM('ativo', 'bloqueado', 'suspenso') DEFAULT 'ativo',
    data_criacao DATETIME DEFAULT CURRENT_TIMESTAMP,
    data_atualizacao DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (jogador_id) REFERENCES jogador(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabela de transações
CREATE TABLE IF NOT EXISTS transacoes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    conta_id INT NOT NULL,
    tipo ENUM('deposito', 'saque', 'aposta', 'premio', 'estorno', 'bonus') NOT NULL,
    valor DECIMAL(10,2) NOT NULL,
    saldo_anterior DECIMAL(10,2) NOT NULL,
    saldo_posterior DECIMAL(10,2) NOT NULL,
    status ENUM('pendente', 'aprovado', 'rejeitado', 'cancelado', 'processando') DEFAULT 'pendente',
    descricao TEXT,
    referencia VARCHAR(100),
    data_solicitacao DATETIME DEFAULT CURRENT_TIMESTAMP,
    data_processamento DATETIME,
    processado_por INT,
    FOREIGN KEY (conta_id) REFERENCES contas(id),
    FOREIGN KEY (processado_por) REFERENCES jogador(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabela de métodos de pagamento
CREATE TABLE IF NOT EXISTS metodos_pagamento (
    id INT PRIMARY KEY AUTO_INCREMENT,
    jogador_id INT NOT NULL,
    tipo ENUM('pix', 'transferencia_bancaria', 'cartao_credito') NOT NULL,
    dados JSON NOT NULL,
    principal BOOLEAN DEFAULT FALSE,
    ativo BOOLEAN DEFAULT TRUE,
    data_criacao DATETIME DEFAULT CURRENT_TIMESTAMP,
    data_atualizacao DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (jogador_id) REFERENCES jogador(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabela de configurações de pagamento
CREATE TABLE IF NOT EXISTS config_pagamentos (
    id INT PRIMARY KEY AUTO_INCREMENT,
    chave VARCHAR(100) NOT NULL,
    valor TEXT NOT NULL,
    descricao TEXT,
    data_atualizacao DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Inserir configurações padrão
INSERT INTO config_pagamentos (chave, valor, descricao) VALUES
('deposito_minimo', '10.00', 'Valor mínimo para depósito'),
('deposito_maximo', '5000.00', 'Valor máximo para depósito'),
('saque_minimo', '30.00', 'Valor mínimo para saque'),
('saque_maximo', '5000.00', 'Valor máximo para saque'),
('taxa_saque', '0.00', 'Taxa cobrada por saque'),
('prazo_saque', '2', 'Prazo em dias úteis para processar saques'),
('metodos_deposito', '["pix", "cartao_credito"]', 'Métodos de pagamento aceitos para depósito'),
('metodos_saque', '["pix", "transferencia_bancaria"]', 'Métodos de pagamento aceitos para saque'),
('modelo_pagamento', 'por_aposta', 'Modelo de pagamento: por_aposta (paga cada aposta) ou conta_saldo (usa saldo da conta)'); 