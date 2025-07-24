CREATE TABLE IF NOT EXISTS afiliados (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nome VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    telefone VARCHAR(20),
    codigo_afiliado VARCHAR(50) NOT NULL UNIQUE,
    comissao_percentual DECIMAL(5,2) DEFAULT 10.00,
    saldo DECIMAL(10,2) DEFAULT 0.00,
    pix_chave VARCHAR(255),
    pix_tipo ENUM('cpf', 'cnpj', 'email', 'telefone', 'aleatoria'),
    status ENUM('ativo', 'inativo') DEFAULT 'ativo',
    data_cadastro DATETIME DEFAULT CURRENT_TIMESTAMP,
    data_atualizacao DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS afiliados_indicacoes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    afiliado_id INT NOT NULL,
    jogador_id INT NOT NULL,
    data_indicacao DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (afiliado_id) REFERENCES afiliados(id),
    FOREIGN KEY (jogador_id) REFERENCES jogador(id)
);

CREATE TABLE IF NOT EXISTS afiliados_comissoes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    afiliado_id INT NOT NULL,
    jogador_id INT NOT NULL,
    pagamento_id INT NOT NULL,
    valor_pagamento DECIMAL(10,2) NOT NULL,
    percentual_comissao DECIMAL(5,2) NOT NULL,
    valor_comissao DECIMAL(10,2) NOT NULL,
    status ENUM('pendente', 'pago') DEFAULT 'pendente',
    data_criacao DATETIME DEFAULT CURRENT_TIMESTAMP,
    data_pagamento DATETIME,
    FOREIGN KEY (afiliado_id) REFERENCES afiliados(id),
    FOREIGN KEY (jogador_id) REFERENCES jogador(id),
    FOREIGN KEY (pagamento_id) REFERENCES pagamentos(id)
); 