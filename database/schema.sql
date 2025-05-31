-- Bolão Football Betting System Database Schema

-- Create database if not exists
CREATE DATABASE IF NOT EXISTS bolao CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Use the database
USE bolao;

-- Configurações
CREATE TABLE IF NOT EXISTS configuracoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome_configuracao VARCHAR(100) NOT NULL,
    valor TEXT NOT NULL,
    categoria VARCHAR(50) NOT NULL,
    descricao TEXT NULL,
    data_atualizacao DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_config (nome_configuracao, categoria)
);

-- Administradores
CREATE TABLE IF NOT EXISTS administradores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    senha VARCHAR(255) NOT NULL,
    status TINYINT(1) DEFAULT 1,
    data_cadastro DATETIME DEFAULT CURRENT_TIMESTAMP,
    ultimo_acesso DATETIME NULL
);

-- Jogadores (Usuários)
CREATE TABLE IF NOT EXISTS jogadores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    senha VARCHAR(255) NOT NULL,
    telefone VARCHAR(20) NULL,
    status TINYINT(1) DEFAULT 1,
    data_cadastro DATETIME DEFAULT CURRENT_TIMESTAMP,
    ultimo_acesso DATETIME NULL,
    token_recuperacao VARCHAR(100) NULL,
    token_expira DATETIME NULL,
    afiliado_id INT NULL,
    FOREIGN KEY (afiliado_id) REFERENCES jogadores(id) ON DELETE SET NULL
);

-- Bolões
CREATE TABLE IF NOT EXISTS boloes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    slug VARCHAR(120) NOT NULL UNIQUE,
    descricao TEXT NULL,
    data_inicio DATE NOT NULL,
    data_fim DATE NOT NULL,
    data_criacao DATETIME DEFAULT CURRENT_TIMESTAMP,
    status TINYINT(1) DEFAULT 1,
    valor_participacao DECIMAL(10,2) DEFAULT 0.00,
    premio_total DECIMAL(10,2) DEFAULT 0.00,
    regras TEXT NULL,
    max_participantes INT NULL,
    publico TINYINT(1) DEFAULT 1,
    admin_id INT NOT NULL,
    FOREIGN KEY (admin_id) REFERENCES administradores(id) ON DELETE CASCADE
);

-- Jogos
CREATE TABLE IF NOT EXISTS jogos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bolao_id INT NOT NULL,
    time_casa VARCHAR(100) NOT NULL,
    time_visitante VARCHAR(100) NOT NULL,
    data_hora DATETIME NOT NULL,
    local VARCHAR(100) NULL,
    peso INT DEFAULT 1,
    status ENUM('agendado', 'em_andamento', 'finalizado', 'cancelado') DEFAULT 'agendado',
    id_externo VARCHAR(100) NULL,
    FOREIGN KEY (bolao_id) REFERENCES boloes(id) ON DELETE CASCADE
);

-- Resultados
CREATE TABLE IF NOT EXISTS resultados (
    id INT AUTO_INCREMENT PRIMARY KEY,
    jogo_id INT NOT NULL,
    gols_casa INT NULL,
    gols_visitante INT NULL,
    data_atualizacao DATETIME DEFAULT CURRENT_TIMESTAMP,
    status ENUM('parcial', 'final') DEFAULT 'parcial',
    FOREIGN KEY (jogo_id) REFERENCES jogos(id) ON DELETE CASCADE
);

-- Participacoes (Jogadores em Bolões)
CREATE TABLE IF NOT EXISTS participacoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    jogador_id INT NOT NULL,
    bolao_id INT NOT NULL,
    data_entrada DATETIME DEFAULT CURRENT_TIMESTAMP,
    status TINYINT(1) DEFAULT 1,
    FOREIGN KEY (jogador_id) REFERENCES jogadores(id) ON DELETE CASCADE,
    FOREIGN KEY (bolao_id) REFERENCES boloes(id) ON DELETE CASCADE,
    UNIQUE KEY (jogador_id, bolao_id)
);

-- Tabela de palpites
DROP TABLE IF EXISTS palpites;
CREATE TABLE IF NOT EXISTS palpites (
    id INT AUTO_INCREMENT PRIMARY KEY,
    jogador_id INT NOT NULL,
    bolao_id INT NOT NULL,
    palpites JSON NOT NULL COMMENT 'JSON com os palpites no formato: {"jogo_id": "1"} onde 1=casa vence, 0=empate, 2=visitante vence',
    data_palpite DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (jogador_id) REFERENCES jogadores(id) ON DELETE CASCADE,
    FOREIGN KEY (bolao_id) REFERENCES boloes(id) ON DELETE CASCADE,
    UNIQUE KEY unique_palpite (jogador_id, bolao_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Pagamentos
CREATE TABLE IF NOT EXISTS pagamentos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    jogador_id INT NOT NULL,
    bolao_id INT NULL,
    valor DECIMAL(10,2) NOT NULL,
    data_pagamento DATETIME DEFAULT CURRENT_TIMESTAMP,
    metodo VARCHAR(50) NOT NULL,
    status ENUM('pendente', 'aprovado', 'recusado') DEFAULT 'pendente',
    transacao_id VARCHAR(100) NULL,
    descricao VARCHAR(255) NULL,
    FOREIGN KEY (jogador_id) REFERENCES jogadores(id) ON DELETE CASCADE,
    FOREIGN KEY (bolao_id) REFERENCES boloes(id) ON DELETE SET NULL
);

-- Rankings (Classificação dos jogadores por bolão)
CREATE TABLE IF NOT EXISTS rankings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    jogador_id INT NOT NULL,
    bolao_id INT NOT NULL,
    pontos_total INT DEFAULT 0,
    acertos_exatos INT DEFAULT 0,
    acertos_parciais INT DEFAULT 0,
    posicao INT DEFAULT 0,
    data_atualizacao DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (jogador_id) REFERENCES jogadores(id) ON DELETE CASCADE,
    FOREIGN KEY (bolao_id) REFERENCES boloes(id) ON DELETE CASCADE,
    UNIQUE KEY (jogador_id, bolao_id)
);

-- Afiliados
CREATE TABLE IF NOT EXISTS afiliados (
    id INT AUTO_INCREMENT PRIMARY KEY,
    jogador_id INT NOT NULL,
    codigo VARCHAR(20) NOT NULL UNIQUE,
    comissao_percentual DECIMAL(5,2) DEFAULT 10.00,
    status TINYINT(1) DEFAULT 1,
    dados_bancarios TEXT NULL,
    FOREIGN KEY (jogador_id) REFERENCES jogadores(id) ON DELETE CASCADE
);

-- Insert default admin user (use IGNORE to prevent duplicate key error)
INSERT IGNORE INTO administradores (nome, email, senha, status) 
VALUES ('Admin', 'admin@bolao.com', '$2y$10$4FQzC/nBpO6jJIgBD0wJBeOSQgXAJojK9dmpWWj/L3jFQw2xBGYV2', 1);
-- Default password is 'admin123'

-- Insert default configurations (use IGNORE to prevent duplicate key errors)
INSERT IGNORE INTO configuracoes (nome_configuracao, valor, categoria, descricao) 
VALUES 
('pontos_acerto_exato', '10', 'pontuacao', 'Pontos por acerto exato'),
('pontos_acerto_parcial', '5', 'pontuacao', 'Pontos por acerto parcial (vencedor ou empate)'),
('pontos_acerto_vencedor', '3', 'pontuacao', 'Pontos por acerto apenas do vencedor'),
('api_football', '{"api_key":"API_KEY_HERE","base_url":"https://api.football-data.org/v4"}', 'api', 'Configurações da API de futebol'),
('pagamento', '{"pix_key":"EMAIL@EXAMPLE.COM"}', 'pagamento', 'Configurações de pagamento'),
('site_name', 'Bolão Football', 'geral', 'Nome do sistema');

-- Tabela de dados dos bolões
CREATE TABLE IF NOT EXISTS dados_boloes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL UNIQUE,
    descricao TEXT,
    regras TEXT,
    data_inicio DATE NOT NULL,
    data_fim DATE NOT NULL,
    data_limite_palpitar DATETIME,
    valor_participacao DECIMAL(10,2) NOT NULL DEFAULT 0,
    premio_total DECIMAL(10,2) NOT NULL DEFAULT 0,
    status TINYINT NOT NULL DEFAULT 1,
    publico TINYINT NOT NULL DEFAULT 1,
    campeonatos JSON,
    jogos JSON,
    imagem_bolao_url VARCHAR(255),
    data_criacao DATETIME DEFAULT CURRENT_TIMESTAMP,
    data_atualizacao DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Tabela de palpites
CREATE TABLE IF NOT EXISTS palpites_bolao (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bolao_id INT NOT NULL,
    usuario_id INT NOT NULL,
    palpites JSON NOT NULL,
    data_palpite DATETIME NOT NULL,
    data_atualizacao DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (bolao_id) REFERENCES dados_boloes(id),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
    UNIQUE KEY unique_palpite (bolao_id, usuario_id)
);

-- Tabela de resultados dos jogos
CREATE TABLE IF NOT EXISTS resultados_jogos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bolao_id INT NOT NULL,
    jogo_id VARCHAR(50) NOT NULL,
    resultado JSON NOT NULL,
    status VARCHAR(20) NOT NULL,
    data_atualizacao DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (bolao_id) REFERENCES dados_boloes(id),
    UNIQUE KEY unique_resultado (bolao_id, jogo_id)
);

-- Tabela de pagamentos
CREATE TABLE IF NOT EXISTS pagamentos_bolao (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bolao_id INT NOT NULL,
    usuario_id INT NOT NULL,
    valor DECIMAL(10,2) NOT NULL,
    status VARCHAR(20) NOT NULL,
    metodo_pagamento VARCHAR(50) NOT NULL,
    dados_pagamento JSON,
    data_pagamento DATETIME,
    data_criacao DATETIME DEFAULT CURRENT_TIMESTAMP,
    data_atualizacao DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (bolao_id) REFERENCES dados_boloes(id),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
);

-- Tabela de usuários
CREATE TABLE IF NOT EXISTS usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    senha VARCHAR(255) NOT NULL,
    data_cadastro DATETIME DEFAULT CURRENT_TIMESTAMP,
    ultimo_acesso DATETIME NULL,
    status TINYINT(1) DEFAULT 1,
    pagamento_confirmado TINYINT(1) DEFAULT 0,
    token_recuperacao VARCHAR(100) NULL,
    token_expiracao DATETIME NULL,
    UNIQUE KEY unique_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de dados dos bolões
CREATE TABLE IF NOT EXISTS dados_boloes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL UNIQUE,
    descricao TEXT NULL,
    regras TEXT NULL,
    data_inicio DATE NOT NULL,
    data_fim DATE NOT NULL,
    data_limite_palpitar DATETIME NULL,
    valor_participacao DECIMAL(10,2) DEFAULT 0.00,
    premio_total DECIMAL(10,2) DEFAULT 0.00,
    imagem_bolao_url VARCHAR(255) NULL,
    status TINYINT(1) DEFAULT 1,
    publico TINYINT(1) DEFAULT 1,
    jogos JSON NULL,
    campeonatos JSON NULL,
    data_criacao DATETIME DEFAULT CURRENT_TIMESTAMP,
    data_atualizacao DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de participações em bolões
CREATE TABLE IF NOT EXISTS participacoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bolao_id INT NOT NULL,
    jogador_id INT NOT NULL,
    data_entrada DATETIME DEFAULT CURRENT_TIMESTAMP,
    status TINYINT(1) DEFAULT 1,
    FOREIGN KEY (bolao_id) REFERENCES dados_boloes(id) ON DELETE CASCADE,
    FOREIGN KEY (jogador_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    UNIQUE KEY unique_participacao (bolao_id, jogador_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de configurações
CREATE TABLE IF NOT EXISTS configuracoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome_configuracao VARCHAR(100) NOT NULL,
    valor TEXT NOT NULL,
    categoria VARCHAR(50) NOT NULL DEFAULT 'geral',
    descricao TEXT NULL,
    data_atualizacao DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_config (nome_configuracao, categoria)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Inserir configurações padrão
INSERT IGNORE INTO configuracoes (nome_configuracao, valor, categoria, descricao) 
VALUES 
('pontos_acerto_exato', '10', 'pontuacao', 'Pontos por acerto exato'),
('pontos_acerto_parcial', '5', 'pontuacao', 'Pontos por acerto parcial (vencedor ou empate)'),
('pontos_acerto_vencedor', '3', 'pontuacao', 'Pontos por acerto apenas do vencedor'),
('api_football', '{"api_key":"API_KEY_HERE","base_url":"https://api.football-data.org/v4"}', 'api', 'Configurações da API de futebol'),
('pagamento', '{"pix_key":"EMAIL@EXAMPLE.COM"}', 'pagamento', 'Configurações de pagamento'),
('site_name', 'Bolão Football', 'geral', 'Nome do sistema');

CREATE TABLE jogador (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    senha VARCHAR(255) NOT NULL,
    telefone VARCHAR(20),
    data_cadastro DATETIME NOT NULL,
    status ENUM('ativo', 'inativo', 'bloqueado') DEFAULT 'ativo',
    reset_token VARCHAR(100),
    reset_token_expiry DATETIME,
    UNIQUE KEY email_unique (email)
);

CREATE TABLE afiliados (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    senha VARCHAR(255) NOT NULL,
    codigo_afiliado VARCHAR(20) NOT NULL UNIQUE,
    data_cadastro DATETIME NOT NULL,
    status ENUM('ativo', 'inativo', 'bloqueado') DEFAULT 'ativo',
    comissao_percentual DECIMAL(5,2) DEFAULT 10.00,
    UNIQUE KEY codigo_afiliado_unique (codigo_afiliado)
); 