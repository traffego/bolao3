-- Criar o banco de dados
CREATE DATABASE IF NOT EXISTS bolao_football CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Usar o banco de dados
USE bolao_football;

-- Tabela de administradores
CREATE TABLE IF NOT EXISTS administrador (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    senha VARCHAR(255) NOT NULL,
    status ENUM('ativo', 'inativo') DEFAULT 'ativo',
    data_cadastro DATETIME DEFAULT CURRENT_TIMESTAMP,
    ultimo_acesso DATETIME NULL
);

-- Tabela de jogadores
CREATE TABLE IF NOT EXISTS jogador (
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

-- Tabela de bolões
CREATE TABLE IF NOT EXISTS boloes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(100) NOT NULL,
    descricao TEXT NULL,
    data_inicio DATE NOT NULL,
    data_fim DATE NOT NULL,
    valor_participacao DECIMAL(10,2) DEFAULT 0.00,
    premio_total DECIMAL(10,2) DEFAULT 0.00,
    regras TEXT NULL,
    max_participantes INT NULL,
    status ENUM('aberto', 'em_andamento', 'finalizado', 'cancelado') DEFAULT 'aberto',
    publico TINYINT(1) DEFAULT 1,
    data_criacao DATETIME DEFAULT CURRENT_TIMESTAMP,
    admin_id INT NOT NULL,
    FOREIGN KEY (admin_id) REFERENCES administrador(id) ON DELETE CASCADE
);

-- Tabela de jogos
CREATE TABLE IF NOT EXISTS jogos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bolao_id INT NOT NULL,
    equipe_casa VARCHAR(100) NOT NULL,
    equipe_visitante VARCHAR(100) NOT NULL,
    campeonato VARCHAR(100) NOT NULL,
    data_hora DATETIME NOT NULL,
    local VARCHAR(100) NULL,
    peso INT DEFAULT 1,
    status ENUM('agendado','em_andamento','finalizado','cancelado') DEFAULT 'agendado',
    id_externo VARCHAR(100) NULL,
    FOREIGN KEY (bolao_id) REFERENCES boloes(id) ON DELETE CASCADE
);

-- Tabela de participações
CREATE TABLE IF NOT EXISTS participacoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bolao_id INT NOT NULL,
    jogador_id INT NOT NULL,
    data_participacao DATETIME DEFAULT CURRENT_TIMESTAMP,
    status ENUM('pendente', 'confirmado', 'cancelado') DEFAULT 'pendente',
    valor_pago DECIMAL(10,2) DEFAULT 0.00,
    data_pagamento DATETIME NULL,
    FOREIGN KEY (bolao_id) REFERENCES boloes(id) ON DELETE CASCADE,
    FOREIGN KEY (jogador_id) REFERENCES jogador(id) ON DELETE CASCADE,
    UNIQUE KEY unique_participacao (bolao_id, jogador_id)
);

-- Tabela de palpites
CREATE TABLE IF NOT EXISTS palpites (
    id INT AUTO_INCREMENT PRIMARY KEY,
    jogador_id INT NOT NULL,
    bolao_id INT NOT NULL,
    jogo_id INT NOT NULL,
    placar_casa INT NOT NULL,
    placar_visitante INT NOT NULL,
    pontos INT DEFAULT 0,
    data_palpite DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (jogador_id) REFERENCES jogador(id) ON DELETE CASCADE,
    FOREIGN KEY (bolao_id) REFERENCES boloes(id) ON DELETE CASCADE,
    FOREIGN KEY (jogo_id) REFERENCES jogos(id) ON DELETE CASCADE,
    UNIQUE KEY unique_palpite (jogador_id, jogo_id)
);

-- Tabela de resultados
CREATE TABLE IF NOT EXISTS resultados (
    id INT AUTO_INCREMENT PRIMARY KEY,
    jogo_id INT NOT NULL,
    placar_casa INT NOT NULL,
    placar_visitante INT NOT NULL,
    data_resultado DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (jogo_id) REFERENCES jogos(id) ON DELETE CASCADE,
    UNIQUE KEY unique_resultado (jogo_id)
);

-- Tabela de pagamentos
CREATE TABLE IF NOT EXISTS pagamentos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    jogador_id INT NOT NULL,
    bolao_id INT NOT NULL,
    valor DECIMAL(10,2) NOT NULL,
    tipo ENUM('entrada', 'saida') NOT NULL,
    status ENUM('pendente', 'confirmado', 'cancelado') DEFAULT 'pendente',
    data_pagamento DATETIME DEFAULT CURRENT_TIMESTAMP,
    metodo_pagamento VARCHAR(50) NULL,
    comprovante_url VARCHAR(255) NULL,
    FOREIGN KEY (jogador_id) REFERENCES jogador(id) ON DELETE CASCADE,
    FOREIGN KEY (bolao_id) REFERENCES boloes(id) ON DELETE CASCADE
);

-- Tabela de configurações
CREATE TABLE IF NOT EXISTS configuracoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome_configuracao VARCHAR(100) NOT NULL,
    valor TEXT NOT NULL,
    categoria VARCHAR(50) NOT NULL,
    descricao TEXT NULL,
    data_atualizacao DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_config (nome_configuracao, categoria)
);

-- Tabela de afiliados
CREATE TABLE IF NOT EXISTS afiliados (
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

-- Inserir administrador padrão
-- Senha: admin123
INSERT INTO administrador (nome, email, senha, status) 
VALUES ('Administrador', 'admin@bolao.com', '$2y$10$4FQzC/nBpO6jJIgBD0wJBeOSQgXAJojK9dmpWWj/L3jFQw2xBGYV2', 'ativo');

-- Inserir configurações padrão
INSERT INTO configuracoes (nome_configuracao, valor, categoria, descricao) VALUES
('site_name', 'Bolão Vitimba', 'geral', 'Nome do site'),
('site_description', 'O melhor sistema de bolões de futebol!', 'geral', 'Descrição do site'),
('moeda', 'BRL', 'geral', 'Moeda padrão do sistema'),
('taxa_admin', '10', 'pagamentos', 'Taxa administrativa em percentual'),
('min_participantes', '2', 'bolao', 'Número mínimo de participantes por bolão'),
('max_palpites', '100', 'bolao', 'Número máximo de palpites por bolão'),
('pontos_placar_exato', '10', 'pontuacao', 'Pontos por acertar o placar exato'),
('pontos_vencedor', '5', 'pontuacao', 'Pontos por acertar apenas o vencedor'),
('pontos_empate', '5', 'pontuacao', 'Pontos por acertar um empate'),
('email_boas_vindas', '1', 'emails', 'Enviar email de boas-vindas para novos usuários'),
('email_confirmacao_palpite', '1', 'emails', 'Enviar email de confirmação de palpite'),
('email_resultado', '1', 'emails', 'Enviar email com resultados dos jogos'); 