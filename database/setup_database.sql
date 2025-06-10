-- Bolão Vitimba Betting System Database Setup
-- Script para criar todas as tabelas e inserir dados de demonstração

-- Drop database if exists (descomente se quiser recriar o banco)
-- DROP DATABASE IF EXISTS bolao_football;

-- Create database
CREATE DATABASE IF NOT EXISTS bolao_football CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Use the database
USE bolao_football;

-- Administradores
CREATE TABLE IF NOT EXISTS administrador (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    senha VARCHAR(255) NOT NULL,
    nivel_acesso ENUM('admin', 'superadmin') DEFAULT 'admin',
    ultimo_login DATETIME NULL,
    status ENUM('ativo', 'inativo') DEFAULT 'ativo'
);

-- Jogadores (Usuários)
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

-- Bolões
CREATE TABLE IF NOT EXISTS boloes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    slug VARCHAR(120) NOT NULL UNIQUE,
    descricao TEXT NULL,
    data_inicio DATE NOT NULL,
    data_fim DATE NOT NULL,
    data_criacao DATETIME DEFAULT CURRENT_TIMESTAMP,
    status VARCHAR(20) DEFAULT 'aberto',
    valor_participacao DECIMAL(10,2) DEFAULT 0.00,
    premio_total DECIMAL(10,2) DEFAULT 0.00,
    regras TEXT NULL,
    max_participantes INT NULL,
    publico TINYINT(1) DEFAULT 1,
    admin_id INT NOT NULL,
    FOREIGN KEY (admin_id) REFERENCES administrador(id) ON DELETE CASCADE
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
    FOREIGN KEY (jogador_id) REFERENCES jogador(id) ON DELETE CASCADE,
    FOREIGN KEY (bolao_id) REFERENCES boloes(id) ON DELETE CASCADE,
    UNIQUE KEY (jogador_id, bolao_id)
);

-- Palpites
CREATE TABLE IF NOT EXISTS palpites (
    id INT AUTO_INCREMENT PRIMARY KEY,
    jogador_id INT NOT NULL,
    jogo_id INT NOT NULL,
    bolao_id INT NOT NULL,
    gols_casa INT NOT NULL,
    gols_visitante INT NOT NULL,
    pontos INT DEFAULT 0,
    data_palpite DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (jogador_id) REFERENCES jogador(id) ON DELETE CASCADE,
    FOREIGN KEY (jogo_id) REFERENCES jogos(id) ON DELETE CASCADE,
    FOREIGN KEY (bolao_id) REFERENCES boloes(id) ON DELETE CASCADE,
    UNIQUE KEY (jogador_id, jogo_id, bolao_id)
);

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
    FOREIGN KEY (jogador_id) REFERENCES jogador(id) ON DELETE CASCADE,
    FOREIGN KEY (bolao_id) REFERENCES boloes(id) ON DELETE SET NULL
);

-- Rankings (Classificação dos jogadores por bolão)
CREATE TABLE IF NOT EXISTS ranking (
    id INT AUTO_INCREMENT PRIMARY KEY,
    jogador_id INT NOT NULL,
    bolao_id INT NOT NULL,
    pontos_total INT DEFAULT 0,
    acertos_exatos INT DEFAULT 0,
    acertos_parciais INT DEFAULT 0,
    posicao INT DEFAULT 0,
    data_atualizacao DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (jogador_id) REFERENCES jogador(id) ON DELETE CASCADE,
    FOREIGN KEY (bolao_id) REFERENCES boloes(id) ON DELETE CASCADE,
    UNIQUE KEY (jogador_id, bolao_id)
);

-- Afiliados
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

-- Configurações
CREATE TABLE IF NOT EXISTS configuracoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome_configuracao VARCHAR(100) NOT NULL UNIQUE,
    valor TEXT NOT NULL,
    descricao VARCHAR(255) NULL
);

-- ======================================================
-- INSERINDO DADOS DE DEMONSTRAÇÃO
-- ======================================================

-- Inserir administrador
INSERT INTO administrador (nome, email, senha, nivel_acesso, status) VALUES 
('Administrador', 'admin@bolao.com', '$2y$10$4FQzC/nBpO6jJIgBD0wJBeOSQgXAJojK9dmpWWj/L3jFQw2xBGYV2', 'superadmin', 'ativo');
-- senha: admin123

-- Inserir jogadores
INSERT INTO jogador (nome, email, senha, telefone, data_cadastro, status) VALUES
('João Silva', 'joao@email.com', '$2y$10$yhN2OsES/AuNbZwTzkPa1.0P3RQfDH9afWTzaziHwz.3XQ5cRqedS', '(11) 98765-4321', NOW(), 'ativo'),
('Maria Souza', 'maria@email.com', '$2y$10$yhN2OsES/AuNbZwTzkPa1.0P3RQfDH9afWTzaziHwz.3XQ5cRqedS', '(11) 97654-3210', NOW(), 'ativo'),
('Carlos Pereira', 'carlos@email.com', '$2y$10$yhN2OsES/AuNbZwTzkPa1.0P3RQfDH9afWTzaziHwz.3XQ5cRqedS', '(11) 95555-1234', NOW(), 'ativo'),
('Ana Oliveira', 'ana@email.com', '$2y$10$yhN2OsES/AuNbZwTzkPa1.0P3RQfDH9afWTzaziHwz.3XQ5cRqedS', '(11) 94444-5678', NOW(), 'ativo'),
('Pedro Santos', 'pedro@email.com', '$2y$10$yhN2OsES/AuNbZwTzkPa1.0P3RQfDH9afWTzaziHwz.3XQ5cRqedS', '(11) 93333-7890', NOW(), 'ativo');
-- senha: 123456

-- Inserir bolões
INSERT INTO boloes (nome, slug, descricao, data_inicio, data_fim, data_criacao, status, valor_participacao, premio_total, regras, admin_id) VALUES
('Copa do Mundo 2022', 'copa-do-mundo-2022', 'Bolão para a Copa do Mundo FIFA 2022', '2022-11-20', '2022-12-18', NOW(), 'finalizado', 50.00, 250.00, 'Acerto exato: 10 pontos\nAcerto do vencedor: 5 pontos\nAcerto do empate: 3 pontos', 1),
('Brasileirão 2023', 'brasileirao-2023', 'Bolão para o Campeonato Brasileiro 2023', '2023-04-15', '2023-12-03', NOW(), 'fechado', 30.00, 150.00, 'Acerto exato: 10 pontos\nAcerto do vencedor: 5 pontos\nAcerto do empate: 3 pontos', 1),
('Copa América 2024', 'copa-america-2024', 'Bolão para a Copa América 2024', '2024-06-14', '2024-07-14', NOW(), 'aberto', 40.00, 200.00, 'Acerto exato: 10 pontos\nAcerto do vencedor: 5 pontos\nAcerto do empate: 3 pontos', 1);

-- Inserir jogos para o Bolão da Copa do Mundo
INSERT INTO jogos (bolao_id, time_casa, time_visitante, data_hora, local, status) VALUES
(1, 'Brasil', 'Sérvia', '2022-11-24 16:00:00', 'Lusail Stadium', 'finalizado'),
(1, 'Brasil', 'Suíça', '2022-11-28 13:00:00', 'Stadium 974', 'finalizado'),
(1, 'Camarões', 'Brasil', '2022-12-02 16:00:00', 'Lusail Stadium', 'finalizado'),
(1, 'Argentina', 'França', '2022-12-18 12:00:00', 'Lusail Stadium', 'finalizado');

-- Inserir jogos para o Bolão do Brasileirão
INSERT INTO jogos (bolao_id, time_casa, time_visitante, data_hora, local, status) VALUES
(2, 'Palmeiras', 'Corinthians', '2023-04-16 16:00:00', 'Allianz Parque', 'finalizado'),
(2, 'São Paulo', 'Flamengo', '2023-04-23 16:00:00', 'Morumbi', 'finalizado'),
(2, 'Santos', 'Botafogo', '2023-05-01 19:00:00', 'Vila Belmiro', 'finalizado'),
(2, 'Fluminense', 'Grêmio', '2023-05-07 16:00:00', 'Maracanã', 'finalizado');

-- Inserir jogos para o Bolão da Copa América
INSERT INTO jogos (bolao_id, time_casa, time_visitante, data_hora, local, status) VALUES
(3, 'Argentina', 'Canadá', '2024-06-20 21:00:00', 'Mercedes-Benz Stadium', 'agendado'),
(3, 'Estados Unidos', 'Bolívia', '2024-06-23 18:00:00', 'AT&T Stadium', 'agendado'),
(3, 'Brasil', 'Costa Rica', '2024-06-24 21:00:00', 'SoFi Stadium', 'agendado'),
(3, 'Brasil', 'Colômbia', '2024-06-28 21:00:00', 'Levi\'s Stadium', 'agendado');

-- Inserir resultados dos jogos finalizados
INSERT INTO resultados (jogo_id, gols_casa, gols_visitante, status) VALUES
(1, 2, 0, 'final'), -- Brasil 2-0 Sérvia
(2, 1, 0, 'final'), -- Brasil 1-0 Suíça
(3, 1, 0, 'final'), -- Camarões 1-0 Brasil
(4, 3, 3, 'final'), -- Argentina 3-3 França (Argentina venceu nos pênaltis)
(5, 2, 1, 'final'), -- Palmeiras 2-1 Corinthians
(6, 1, 1, 'final'), -- São Paulo 1-1 Flamengo
(7, 0, 1, 'final'), -- Santos 0-1 Botafogo
(8, 2, 0, 'final'); -- Fluminense 2-0 Grêmio

-- Inserir participações de jogadores nos bolões
INSERT INTO participacoes (jogador_id, bolao_id, data_entrada) VALUES
(1, 1, '2022-10-15 10:30:00'), -- João na Copa do Mundo
(2, 1, '2022-10-16 14:20:00'), -- Maria na Copa do Mundo
(3, 1, '2022-10-18 09:45:00'), -- Carlos na Copa do Mundo
(1, 2, '2023-03-20 11:15:00'), -- João no Brasileirão
(2, 2, '2023-03-22 16:40:00'), -- Maria no Brasileirão
(4, 2, '2023-03-25 08:30:00'), -- Ana no Brasileirão
(1, 3, '2024-05-10 12:00:00'), -- João na Copa América
(3, 3, '2024-05-12 14:30:00'), -- Carlos na Copa América
(5, 3, '2024-05-15 10:45:00'); -- Pedro na Copa América

-- Inserir palpites para a Copa do Mundo
INSERT INTO palpites (jogador_id, jogo_id, bolao_id, gols_casa, gols_visitante, pontos) VALUES
-- Jogo 1: Brasil 2-0 Sérvia
(1, 1, 1, 2, 0, 10), -- João acertou
(2, 1, 1, 3, 0, 5),  -- Maria acertou vencedor
(3, 1, 1, 1, 1, 0),  -- Carlos errou

-- Jogo 2: Brasil 1-0 Suíça
(1, 2, 1, 2, 0, 5),  -- João acertou vencedor
(2, 2, 1, 1, 0, 10), -- Maria acertou
(3, 2, 1, 0, 0, 0),  -- Carlos errou

-- Jogo 3: Camarões 1-0 Brasil
(1, 3, 1, 0, 2, 0),  -- João errou
(2, 3, 1, 0, 1, 0),  -- Maria errou
(3, 3, 1, 1, 0, 10), -- Carlos acertou

-- Jogo 4: Argentina 3-3 França
(1, 4, 1, 2, 2, 3),  -- João acertou empate, placar errado
(2, 4, 1, 3, 3, 10), -- Maria acertou o placar
(3, 4, 1, 1, 2, 0);  -- Carlos errou

-- Inserir palpites para o Brasileirão
INSERT INTO palpites (jogador_id, jogo_id, bolao_id, gols_casa, gols_visitante, pontos) VALUES
-- Jogo 5: Palmeiras 2-1 Corinthians
(1, 5, 2, 2, 1, 10), -- João acertou
(2, 5, 2, 1, 0, 5),  -- Maria acertou vencedor
(4, 5, 2, 0, 0, 0),  -- Ana errou

-- Jogo 6: São Paulo 1-1 Flamengo
(1, 6, 2, 1, 1, 10), -- João acertou
(2, 6, 2, 2, 2, 3),  -- Maria acertou empate
(4, 6, 2, 0, 1, 0),  -- Ana errou

-- Jogo 7: Santos 0-1 Botafogo
(1, 7, 2, 1, 2, 5),  -- João acertou vencedor
(2, 7, 2, 0, 1, 10), -- Maria acertou
(4, 7, 2, 1, 1, 0),  -- Ana errou

-- Jogo 8: Fluminense 2-0 Grêmio
(1, 8, 2, 2, 0, 10), -- João acertou
(2, 8, 2, 1, 0, 5),  -- Maria acertou vencedor
(4, 8, 2, 2, 1, 5);  -- Ana acertou vencedor

-- Inserir pagamentos
INSERT INTO pagamentos (jogador_id, bolao_id, valor, data_pagamento, metodo, status) VALUES
(1, 1, 50.00, '2022-10-15 10:35:00', 'PIX', 'aprovado'),      -- João paga Copa do Mundo
(2, 1, 50.00, '2022-10-16 14:25:00', 'Cartão', 'aprovado'),   -- Maria paga Copa do Mundo
(3, 1, 50.00, '2022-10-18 09:50:00', 'PIX', 'aprovado'),      -- Carlos paga Copa do Mundo
(1, 2, 30.00, '2023-03-20 11:20:00', 'PIX', 'aprovado'),      -- João paga Brasileirão
(2, 2, 30.00, '2023-03-22 16:45:00', 'Cartão', 'aprovado'),   -- Maria paga Brasileirão
(4, 2, 30.00, '2023-03-25 08:35:00', 'PIX', 'aprovado'),      -- Ana paga Brasileirão
(1, 3, 40.00, '2024-05-10 12:05:00', 'PIX', 'aprovado'),      -- João paga Copa América
(3, 3, 40.00, '2024-05-12 14:35:00', 'Cartão', 'pendente'),   -- Carlos paga Copa América
(5, 3, 40.00, '2024-05-15 10:50:00', 'PIX', 'pendente');      -- Pedro paga Copa América

-- Atualizar rankings
INSERT INTO ranking (jogador_id, bolao_id, pontos_total, acertos_exatos, acertos_parciais, posicao) VALUES
(1, 1, 18, 1, 2, 2),  -- João na Copa do Mundo
(2, 1, 28, 2, 1, 1),  -- Maria na Copa do Mundo
(3, 1, 10, 1, 0, 3),  -- Carlos na Copa do Mundo
(1, 2, 35, 3, 1, 1),  -- João no Brasileirão
(2, 2, 23, 1, 3, 2),  -- Maria no Brasileirão
(4, 2, 5, 0, 1, 3);   -- Ana no Brasileirão

-- Inserir configurações
INSERT INTO configuracoes (nome_configuracao, valor, descricao) VALUES
('pontos_acerto_exato', '10', 'Pontos por acerto exato do placar'),
('pontos_acerto_vencedor', '5', 'Pontos por acerto apenas do vencedor'),
('pontos_acerto_empate', '3', 'Pontos por acerto de empate sem placar exato'),
('pagamento', '{"pix_key":"bolao@example.com"}', 'Configurações de pagamento'),
('site', '{"nome":"Bolão Vitimba","url":"https://bolao.example.com","email_contato":"contato@bolao.example.com"}', 'Configurações do site'),
('api_football', '{"api_key":"SUA_CHAVE_API_AQUI","base_url":"https://api-football-v1.p.rapidapi.com/v3"}', 'Configurações da API de futebol'); 