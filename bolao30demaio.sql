-- --------------------------------------------------------
-- Servidor:                     127.0.0.1
-- Versão do servidor:           10.4.32-MariaDB - mariadb.org binary distribution
-- OS do Servidor:               Win64
-- HeidiSQL Versão:              12.10.0.7000
-- --------------------------------------------------------

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET NAMES utf8 */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

-- Copiando estrutura para tabela bolao_football.administrador
CREATE TABLE IF NOT EXISTS `administrador` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nome` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `senha` varchar(255) NOT NULL,
  `status` enum('ativo','inativo') DEFAULT 'ativo',
  `data_cadastro` datetime DEFAULT current_timestamp(),
  `ultimo_acesso` datetime DEFAULT NULL,
  `ultimo_login` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Copiando dados para a tabela bolao_football.administrador: ~0 rows (aproximadamente)
INSERT INTO `administrador` (`id`, `nome`, `email`, `senha`, `status`, `data_cadastro`, `ultimo_acesso`, `ultimo_login`) VALUES
	(1, 'Administrador', 'admin@bolao.com', '$2a$10$/pFpEVzPqpLUnQqOqO9JJ.ExbamPa7aPRR9KicD7ZMu5sgFl6Xo/y', 'ativo', '2025-05-20 18:08:25', NULL, '2025-05-30 21:16:42');

-- Copiando estrutura para tabela bolao_football.afiliados
CREATE TABLE IF NOT EXISTS `afiliados` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `jogador_id` int(11) NOT NULL,
  `codigo_afiliado` varchar(20) NOT NULL,
  `comissao_percentual` decimal(5,2) DEFAULT 10.00,
  `saldo_disponivel` decimal(10,2) DEFAULT 0.00,
  `data_cadastro` datetime DEFAULT current_timestamp(),
  `status` enum('ativo','inativo') DEFAULT 'ativo',
  PRIMARY KEY (`id`),
  UNIQUE KEY `codigo_afiliado` (`codigo_afiliado`),
  KEY `jogador_id` (`jogador_id`),
  CONSTRAINT `afiliados_ibfk_1` FOREIGN KEY (`jogador_id`) REFERENCES `jogador` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Copiando dados para a tabela bolao_football.afiliados: ~0 rows (aproximadamente)

-- Copiando estrutura para tabela bolao_football.boloes
CREATE TABLE IF NOT EXISTS `boloes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `titulo` varchar(100) NOT NULL,
  `descricao` text DEFAULT NULL,
  `data_inicio` date NOT NULL,
  `data_fim` date NOT NULL,
  `valor_participacao` decimal(10,2) DEFAULT 0.00,
  `premio_total` decimal(10,2) DEFAULT 0.00,
  `regras` text DEFAULT NULL,
  `max_participantes` int(11) DEFAULT NULL,
  `status` enum('aberto','em_andamento','finalizado','cancelado') DEFAULT 'aberto',
  `publico` tinyint(1) DEFAULT 1,
  `data_criacao` datetime DEFAULT current_timestamp(),
  `admin_id` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `admin_id` (`admin_id`),
  CONSTRAINT `boloes_ibfk_1` FOREIGN KEY (`admin_id`) REFERENCES `administrador` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Copiando dados para a tabela bolao_football.boloes: ~0 rows (aproximadamente)

-- Copiando estrutura para tabela bolao_football.configuracoes
CREATE TABLE IF NOT EXISTS `configuracoes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nome_configuracao` varchar(100) NOT NULL,
  `valor` text NOT NULL,
  `categoria` varchar(50) NOT NULL DEFAULT 'geral',
  `descricao` text DEFAULT NULL,
  `data_criacao` timestamp NOT NULL DEFAULT current_timestamp(),
  `data_atualizacao` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_config` (`nome_configuracao`,`categoria`),
  UNIQUE KEY `uk_nome_categoria` (`nome_configuracao`,`categoria`)
) ENGINE=InnoDB AUTO_INCREMENT=28 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Copiando dados para a tabela bolao_football.configuracoes: ~19 rows (aproximadamente)
INSERT INTO `configuracoes` (`id`, `nome_configuracao`, `valor`, `categoria`, `descricao`, `data_criacao`, `data_atualizacao`) VALUES
	(1, 'site_name', '"Bol\\u00e3o de Futebol"', 'geral', 'Nome do site', '2025-05-30 19:38:41', '2025-05-24 22:01:08');
INSERT INTO `configuracoes` (`id`, `nome_configuracao`, `valor`, `categoria`, `descricao`, `data_criacao`, `data_atualizacao`) VALUES
	(2, 'site_description', '"O melhor sistema de bol\\u00f5es de futebol!"', 'geral', 'Descri????o do site', '2025-05-30 19:38:41', '2025-05-24 22:01:08');
INSERT INTO `configuracoes` (`id`, `nome_configuracao`, `valor`, `categoria`, `descricao`, `data_criacao`, `data_atualizacao`) VALUES
	(3, 'moeda', '"BRL"', 'geral', 'Moeda padr??o do sistema', '2025-05-30 19:38:41', '2025-05-24 22:01:08');
INSERT INTO `configuracoes` (`id`, `nome_configuracao`, `valor`, `categoria`, `descricao`, `data_criacao`, `data_atualizacao`) VALUES
	(4, 'taxa_admin', '10', 'geral', 'Taxa administrativa em percentual', '2025-05-30 19:38:41', '2025-05-24 22:01:08');
INSERT INTO `configuracoes` (`id`, `nome_configuracao`, `valor`, `categoria`, `descricao`, `data_criacao`, `data_atualizacao`) VALUES
	(5, 'min_participantes', '2', 'geral', 'N??mero m??nimo de participantes por bol??o', '2025-05-30 19:38:41', '2025-05-24 22:01:08');
INSERT INTO `configuracoes` (`id`, `nome_configuracao`, `valor`, `categoria`, `descricao`, `data_criacao`, `data_atualizacao`) VALUES
	(6, 'max_palpites', '100', 'geral', 'N??mero m??ximo de palpites por bol??o', '2025-05-30 19:38:41', '2025-05-24 22:01:08');
INSERT INTO `configuracoes` (`id`, `nome_configuracao`, `valor`, `categoria`, `descricao`, `data_criacao`, `data_atualizacao`) VALUES
	(7, 'pontos_placar_exato', '10', 'pontuacao', 'Pontos por acertar o placar exato', '2025-05-30 19:38:41', '2025-05-24 22:01:08');
INSERT INTO `configuracoes` (`id`, `nome_configuracao`, `valor`, `categoria`, `descricao`, `data_criacao`, `data_atualizacao`) VALUES
	(8, 'pontos_vencedor', '5', 'pontuacao', 'Pontos por acertar apenas o vencedor', '2025-05-30 19:38:41', '2025-05-24 22:01:08');
INSERT INTO `configuracoes` (`id`, `nome_configuracao`, `valor`, `categoria`, `descricao`, `data_criacao`, `data_atualizacao`) VALUES
	(9, 'pontos_empate', '5', 'pontuacao', 'Pontos por acertar um empate', '2025-05-30 19:38:41', '2025-05-24 22:01:08');
INSERT INTO `configuracoes` (`id`, `nome_configuracao`, `valor`, `categoria`, `descricao`, `data_criacao`, `data_atualizacao`) VALUES
	(10, 'email_boas_vindas', '1', 'geral', 'Enviar email de boas-vindas para novos usu??rios', '2025-05-30 19:38:41', '2025-05-24 22:01:08');
INSERT INTO `configuracoes` (`id`, `nome_configuracao`, `valor`, `categoria`, `descricao`, `data_criacao`, `data_atualizacao`) VALUES
	(11, 'email_confirmacao_palpite', '1', 'geral', 'Enviar email de confirma????o de palpite', '2025-05-30 19:38:41', '2025-05-24 22:01:08');
INSERT INTO `configuracoes` (`id`, `nome_configuracao`, `valor`, `categoria`, `descricao`, `data_criacao`, `data_atualizacao`) VALUES
	(12, 'email_resultado', '1', 'geral', 'Enviar email com resultados dos jogos', '2025-05-30 19:38:41', '2025-05-24 22:01:08');
INSERT INTO `configuracoes` (`id`, `nome_configuracao`, `valor`, `categoria`, `descricao`, `data_criacao`, `data_atualizacao`) VALUES
	(13, 'api_football', '{"api_key":"ad0b29bd4984b69fd16d9680c6d017c1","base_url":"https:\\/\\/v3.football.api-sports.io","last_request":"2025-05-30 14:02:11"}', 'api', 'Configurações da API Football', '2025-05-30 19:38:41', '2025-05-30 14:02:11');
INSERT INTO `configuracoes` (`id`, `nome_configuracao`, `valor`, `categoria`, `descricao`, `data_criacao`, `data_atualizacao`) VALUES
	(16, 'pontos_acerto_exato', '10', 'pontuacao', 'Pontos por acerto exato', '2025-05-30 19:38:41', '2025-05-24 22:01:08');
INSERT INTO `configuracoes` (`id`, `nome_configuracao`, `valor`, `categoria`, `descricao`, `data_criacao`, `data_atualizacao`) VALUES
	(17, 'pontos_acerto_parcial', '5', 'pontuacao', 'Pontos por acerto parcial (vencedor ou empate)', '2025-05-30 19:38:41', '2025-05-24 22:01:08');
INSERT INTO `configuracoes` (`id`, `nome_configuracao`, `valor`, `categoria`, `descricao`, `data_criacao`, `data_atualizacao`) VALUES
	(18, 'pontos_acerto_vencedor', '3', 'pontuacao', 'Pontos por acerto apenas do vencedor', '2025-05-30 19:38:41', '2025-05-24 22:01:08');
INSERT INTO `configuracoes` (`id`, `nome_configuracao`, `valor`, `categoria`, `descricao`, `data_criacao`, `data_atualizacao`) VALUES
	(19, 'pagamento', '{"pix_key":"EMAIL@EXAMPLE.COM"}', 'pagamento', 'Configurações de pagamento', '2025-05-30 19:38:41', '2025-05-24 22:01:08');
INSERT INTO `configuracoes` (`id`, `nome_configuracao`, `valor`, `categoria`, `descricao`, `data_criacao`, `data_atualizacao`) VALUES
	(21, 'efi_pix_config', '{"ambiente":"producao","client_id":"Client_Id_3e9ce7b7f569d0a4aa8f9ec8b172c3ed7dd9d948","client_secret":"Client_Secret_31e8f33edba74286002f4c91a2df6896f2764fd1","pix_key":"60409292-a359-4992-9f5f-5886bace6fe6","webhook_url":"https:\\/\\/localhost\\/bolao3\\/api\\/webhook_pix.php"}', 'pagamentos', 'Configurações da API Pix da Efí', '2025-05-30 19:38:41', '2025-05-30 18:32:58');
INSERT INTO `configuracoes` (`id`, `nome_configuracao`, `valor`, `categoria`, `descricao`, `data_criacao`, `data_atualizacao`) VALUES
	(24, 'tipos_logs', '["configuracao","pagamento","palpite","bolao","usuario","sistema"]', 'sistema', 'Tipos de logs permitidos no sistema', '2025-05-30 21:34:58', '2025-05-30 18:34:58');

-- Copiando estrutura para tabela bolao_football.dados_boloes
CREATE TABLE IF NOT EXISTS `dados_boloes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nome` varchar(100) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `descricao` text DEFAULT NULL,
  `data_inicio` date NOT NULL,
  `data_fim` date NOT NULL,
  `data_limite_palpitar` date DEFAULT NULL,
  `valor_participacao` decimal(10,2) DEFAULT 0.00,
  `premio_total` decimal(10,2) DEFAULT 0.00,
  `status` tinyint(1) DEFAULT 1 COMMENT '1=Ativo, 0=Inativo',
  `publico` tinyint(1) DEFAULT 1 COMMENT '1=Público, 0=Privado',
  `max_participantes` int(11) DEFAULT NULL,
  `quantidade_jogos` int(11) DEFAULT 0,
  `imagem_bolao_url` varchar(255) DEFAULT NULL,
  `admin_id` int(11) NOT NULL,
  `jogos` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Dados dos jogos em formato JSON' CHECK (json_valid(`jogos`)),
  `campeonatos` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Campeonatos selecionados em formato JSON' CHECK (json_valid(`campeonatos`)),
  `data_criacao` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `idx_admin` (`admin_id`),
  KEY `idx_status` (`status`),
  KEY `idx_data` (`data_inicio`,`data_fim`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Copiando dados para a tabela bolao_football.dados_boloes: ~7 rows (aproximadamente)
INSERT INTO `dados_boloes` (`id`, `nome`, `slug`, `descricao`, `data_inicio`, `data_fim`, `data_limite_palpitar`, `valor_participacao`, `premio_total`, `status`, `publico`, `max_participantes`, `quantidade_jogos`, `imagem_bolao_url`, `admin_id`, `jogos`, `campeonatos`, `data_criacao`) VALUES
	(1, 'Bolão 24/05/2025 a 30/05/2025', 'bolão-24-05-2025-a-30-05-2025', NULL, '2025-05-24', '2025-05-30', '2025-05-24', 10.00, 1000.00, 1, 1, NULL, 11, 'uploads/boloes/bolao_1747886014_682e9fbe580bb.png', 1, '[{"id":1351133,"campeonato":"Serie A","campeonato_id":71,"time_casa":"Fluminense","time_visitante":"Vasco DA Gama","data":"24\\/05\\/2025 18:30","data_iso":"2025-05-24T21:30:00+00:00","status":"FT","resultado_casa":2,"resultado_visitante":1,"local":"Estadio Jornalista M\\u00e1rio Filho"},{"id":1351136,"campeonato":"Serie A","campeonato_id":71,"time_casa":"Sao Paulo","time_visitante":"Mirassol","data":"24\\/05\\/2025 18:30","data_iso":"2025-05-24T21:30:00+00:00","status":"FT","resultado_casa":0,"resultado_visitante":2,"local":"MorumBIS"},{"id":1351138,"campeonato":"Serie A","campeonato_id":71,"time_casa":"Atletico-MG","time_visitante":"Corinthians","data":"24\\/05\\/2025 21:00","data_iso":"2025-05-25T00:00:00+00:00","status":"FT","resultado_casa":0,"resultado_visitante":0,"local":"Arena MRV"},{"id":1351139,"campeonato":"Serie A","campeonato_id":71,"time_casa":"Gremio","time_visitante":"Bahia","data":"25\\/05\\/2025 11:00","data_iso":"2025-05-25T14:00:00+00:00","status":"FT","resultado_casa":1,"resultado_visitante":0,"local":"Arena do Gr\\u00eamio"},{"id":1351135,"campeonato":"Serie A","campeonato_id":71,"time_casa":"Palmeiras","time_visitante":"Flamengo","data":"25\\/05\\/2025 16:00","data_iso":"2025-05-25T19:00:00+00:00","status":"FT","resultado_casa":0,"resultado_visitante":2,"local":"Allianz Parque"},{"id":1351142,"campeonato":"Serie A","campeonato_id":71,"time_casa":"Sport Recife","time_visitante":"Internacional","data":"25\\/05\\/2025 16:00","data_iso":"2025-05-25T19:00:00+00:00","status":"FT","resultado_casa":1,"resultado_visitante":1,"local":"Est\\u00e1dio Adelmar da Costa Carvalho"},{"id":1351140,"campeonato":"Serie A","campeonato_id":71,"time_casa":"Vitoria","time_visitante":"Santos","data":"25\\/05\\/2025 18:30","data_iso":"2025-05-25T21:30:00+00:00","status":"FT","resultado_casa":0,"resultado_visitante":1,"local":"Est\\u00e1dio Manoel Barradas"},{"id":1351141,"campeonato":"Serie A","campeonato_id":71,"time_casa":"Fortaleza EC","time_visitante":"Cruzeiro","data":"25\\/05\\/2025 20:30","data_iso":"2025-05-25T23:30:00+00:00","status":"FT","resultado_casa":0,"resultado_visitante":2,"local":"Est\\u00e1dio Governador Pl\\u00e1cido Aderaldo Castelo"},{"id":1351137,"campeonato":"Serie A","campeonato_id":71,"time_casa":"RB Bragantino","time_visitante":"Juventude","data":"26\\/05\\/2025 20:00","data_iso":"2025-05-26T23:00:00+00:00","status":"FT","resultado_casa":1,"resultado_visitante":0,"local":"Est\\u00e1dio Cicero de Souza Marques"},{"id":1353341,"campeonato":"Serie B","campeonato_id":72,"time_casa":"Goias","time_visitante":"Ferrovi\\u00e1ria","data":"23\\/05\\/2025 21:35","data_iso":"2025-05-24T00:35:00+00:00","status":"FT","resultado_casa":2,"resultado_visitante":0,"local":"Est\\u00e1dio de Hail\\u00e9 Pinheiro"},{"id":1353340,"campeonato":"Serie B","campeonato_id":72,"time_casa":"Atletico Paranaense","time_visitante":"Athletic Club","data":"24\\/05\\/2025 16:00","data_iso":"2025-05-24T19:00:00+00:00","status":"FT","resultado_casa":1,"resultado_visitante":0,"local":"Ligga Arena"}]', '[{"id":"71","nome":"Brasileir\\u00e3o S\\u00e9rie A"},{"id":"72","nome":"Brasileir\\u00e3o S\\u00e9rie B"}]', '2025-05-22 00:54:07');
INSERT INTO `dados_boloes` (`id`, `nome`, `slug`, `descricao`, `data_inicio`, `data_fim`, `data_limite_palpitar`, `valor_participacao`, `premio_total`, `status`, `publico`, `max_participantes`, `quantidade_jogos`, `imagem_bolao_url`, `admin_id`, `jogos`, `campeonatos`, `data_criacao`) VALUES
	(2, 'Bolão 22/05/2025 a 30/05/2025', 'bolão-22-05-2025-a-30-05-2025', NULL, '2025-05-22', '2025-05-30', '2025-05-22', 10.00, 1000.00, 1, 1, NULL, 11, 'uploads/boloes/bolao_1747919733_682f2375eae54.png', 1, '[{"id":1351133,"campeonato":"Serie A","campeonato_id":71,"time_casa":"Fluminense","time_visitante":"Vasco DA Gama","data":"24\\/05\\/2025 18:30","data_iso":"2025-05-24T21:30:00+00:00","status":"FT","resultado_casa":2,"resultado_visitante":1,"local":"Estadio Jornalista M\\u00e1rio Filho"},{"id":1351136,"campeonato":"Serie A","campeonato_id":71,"time_casa":"Sao Paulo","time_visitante":"Mirassol","data":"24\\/05\\/2025 18:30","data_iso":"2025-05-24T21:30:00+00:00","status":"FT","resultado_casa":0,"resultado_visitante":2,"local":"MorumBIS"},{"id":1351138,"campeonato":"Serie A","campeonato_id":71,"time_casa":"Atletico-MG","time_visitante":"Corinthians","data":"24\\/05\\/2025 21:00","data_iso":"2025-05-25T00:00:00+00:00","status":"FT","resultado_casa":0,"resultado_visitante":0,"local":"Arena MRV"},{"id":1351139,"campeonato":"Serie A","campeonato_id":71,"time_casa":"Gremio","time_visitante":"Bahia","data":"25\\/05\\/2025 11:00","data_iso":"2025-05-25T14:00:00+00:00","status":"FT","resultado_casa":1,"resultado_visitante":0,"local":"Arena do Gr\\u00eamio"},{"id":1351135,"campeonato":"Serie A","campeonato_id":71,"time_casa":"Palmeiras","time_visitante":"Flamengo","data":"25\\/05\\/2025 16:00","data_iso":"2025-05-25T19:00:00+00:00","status":"FT","resultado_casa":0,"resultado_visitante":2,"local":"Allianz Parque"},{"id":1351142,"campeonato":"Serie A","campeonato_id":71,"time_casa":"Sport Recife","time_visitante":"Internacional","data":"25\\/05\\/2025 16:00","data_iso":"2025-05-25T19:00:00+00:00","status":"FT","resultado_casa":1,"resultado_visitante":1,"local":"Est\\u00e1dio Adelmar da Costa Carvalho"},{"id":1351140,"campeonato":"Serie A","campeonato_id":71,"time_casa":"Vitoria","time_visitante":"Santos","data":"25\\/05\\/2025 18:30","data_iso":"2025-05-25T21:30:00+00:00","status":"FT","resultado_casa":0,"resultado_visitante":1,"local":"Est\\u00e1dio Manoel Barradas"},{"id":1351141,"campeonato":"Serie A","campeonato_id":71,"time_casa":"Fortaleza EC","time_visitante":"Cruzeiro","data":"25\\/05\\/2025 20:30","data_iso":"2025-05-25T23:30:00+00:00","status":"FT","resultado_casa":0,"resultado_visitante":2,"local":"Est\\u00e1dio Governador Pl\\u00e1cido Aderaldo Castelo"},{"id":1351137,"campeonato":"Serie A","campeonato_id":71,"time_casa":"RB Bragantino","time_visitante":"Juventude","data":"26\\/05\\/2025 20:00","data_iso":"2025-05-26T23:00:00+00:00","status":"FT","resultado_casa":1,"resultado_visitante":0,"local":"Est\\u00e1dio Cicero de Souza Marques"},{"id":1353341,"campeonato":"Serie B","campeonato_id":72,"time_casa":"Goias","time_visitante":"Ferrovi\\u00e1ria","data":"23\\/05\\/2025 21:35","data_iso":"2025-05-24T00:35:00+00:00","status":"FT","resultado_casa":2,"resultado_visitante":0,"local":"Est\\u00e1dio de Hail\\u00e9 Pinheiro"},{"id":1353340,"campeonato":"Serie B","campeonato_id":72,"time_casa":"Atletico Paranaense","time_visitante":"Athletic Club","data":"24\\/05\\/2025 16:00","data_iso":"2025-05-24T19:00:00+00:00","status":"FT","resultado_casa":1,"resultado_visitante":0,"local":"Ligga Arena"}]', '[{"id":"71","nome":"Brasileir\\u00e3o S\\u00e9rie A"},{"id":"72","nome":"Brasileir\\u00e3o S\\u00e9rie B"}]', '2025-05-22 10:15:58');
INSERT INTO `dados_boloes` (`id`, `nome`, `slug`, `descricao`, `data_inicio`, `data_fim`, `data_limite_palpitar`, `valor_participacao`, `premio_total`, `status`, `publico`, `max_participantes`, `quantidade_jogos`, `imagem_bolao_url`, `admin_id`, `jogos`, `campeonatos`, `data_criacao`) VALUES
	(3, 'Bolão 24/05/2025 a 24/05/2025', 'bolão-24-05-2025-a-24-05-2025', NULL, '2025-05-24', '2025-05-30', '2025-05-24', 10.00, 1000.00, 1, 1, NULL, 11, 'uploads/boloes/bolao_1747952473_682fa359abeae.png', 1, '[{"id":1351133,"campeonato":"Serie A","campeonato_id":71,"time_casa":"Fluminense","time_visitante":"Vasco DA Gama","data":"24\\/05\\/2025 18:30","data_iso":"2025-05-24T21:30:00+00:00","status":"FT","resultado_casa":2,"resultado_visitante":1,"local":"Estadio Jornalista M\\u00e1rio Filho"},{"id":1351136,"campeonato":"Serie A","campeonato_id":71,"time_casa":"Sao Paulo","time_visitante":"Mirassol","data":"24\\/05\\/2025 18:30","data_iso":"2025-05-24T21:30:00+00:00","status":"FT","resultado_casa":0,"resultado_visitante":2,"local":"MorumBIS"},{"id":1351138,"campeonato":"Serie A","campeonato_id":71,"time_casa":"Atletico-MG","time_visitante":"Corinthians","data":"24\\/05\\/2025 21:00","data_iso":"2025-05-25T00:00:00+00:00","status":"FT","resultado_casa":0,"resultado_visitante":0,"local":"Arena MRV"},{"id":1351139,"campeonato":"Serie A","campeonato_id":71,"time_casa":"Gremio","time_visitante":"Bahia","data":"25\\/05\\/2025 11:00","data_iso":"2025-05-25T14:00:00+00:00","status":"FT","resultado_casa":1,"resultado_visitante":0,"local":"Arena do Gr\\u00eamio"},{"id":1351135,"campeonato":"Serie A","campeonato_id":71,"time_casa":"Palmeiras","time_visitante":"Flamengo","data":"25\\/05\\/2025 16:00","data_iso":"2025-05-25T19:00:00+00:00","status":"FT","resultado_casa":0,"resultado_visitante":2,"local":"Allianz Parque"},{"id":1351142,"campeonato":"Serie A","campeonato_id":71,"time_casa":"Sport Recife","time_visitante":"Internacional","data":"25\\/05\\/2025 16:00","data_iso":"2025-05-25T19:00:00+00:00","status":"FT","resultado_casa":1,"resultado_visitante":1,"local":"Est\\u00e1dio Adelmar da Costa Carvalho"},{"id":1351140,"campeonato":"Serie A","campeonato_id":71,"time_casa":"Vitoria","time_visitante":"Santos","data":"25\\/05\\/2025 18:30","data_iso":"2025-05-25T21:30:00+00:00","status":"FT","resultado_casa":0,"resultado_visitante":1,"local":"Est\\u00e1dio Manoel Barradas"},{"id":1351141,"campeonato":"Serie A","campeonato_id":71,"time_casa":"Fortaleza EC","time_visitante":"Cruzeiro","data":"25\\/05\\/2025 20:30","data_iso":"2025-05-25T23:30:00+00:00","status":"FT","resultado_casa":0,"resultado_visitante":2,"local":"Est\\u00e1dio Governador Pl\\u00e1cido Aderaldo Castelo"},{"id":1351137,"campeonato":"Serie A","campeonato_id":71,"time_casa":"RB Bragantino","time_visitante":"Juventude","data":"26\\/05\\/2025 20:00","data_iso":"2025-05-26T23:00:00+00:00","status":"FT","resultado_casa":1,"resultado_visitante":0,"local":"Est\\u00e1dio Cicero de Souza Marques"},{"id":1353341,"campeonato":"Serie B","campeonato_id":72,"time_casa":"Goias","time_visitante":"Ferrovi\\u00e1ria","data":"23\\/05\\/2025 21:35","data_iso":"2025-05-24T00:35:00+00:00","status":"FT","resultado_casa":2,"resultado_visitante":0,"local":"Est\\u00e1dio de Hail\\u00e9 Pinheiro"},{"id":1353340,"campeonato":"Serie B","campeonato_id":72,"time_casa":"Atletico Paranaense","time_visitante":"Athletic Club","data":"24\\/05\\/2025 16:00","data_iso":"2025-05-24T19:00:00+00:00","status":"FT","resultado_casa":1,"resultado_visitante":0,"local":"Ligga Arena"}]', '[{"id":"71","nome":"Brasileir\\u00e3o S\\u00e9rie A"},{"id":"72","nome":"Brasileir\\u00e3o S\\u00e9rie B"}]', '2025-05-22 19:21:43');
INSERT INTO `dados_boloes` (`id`, `nome`, `slug`, `descricao`, `data_inicio`, `data_fim`, `data_limite_palpitar`, `valor_participacao`, `premio_total`, `status`, `publico`, `max_participantes`, `quantidade_jogos`, `imagem_bolao_url`, `admin_id`, `jogos`, `campeonatos`, `data_criacao`) VALUES
	(4, 'Bolão 23/05/2025 a 30/05/2025', 'bolão-23-05-2025-a-30-05-2025', NULL, '2025-05-23', '2025-05-30', '2025-05-23', 10.00, 1000.00, 1, 1, NULL, 11, 'uploads/boloes/bolao_1748011359_6830895f985fb.png', 1, '[{"id":1351133,"campeonato":"Serie A","campeonato_id":71,"time_casa":"Fluminense","time_visitante":"Vasco DA Gama","data":"24\\/05\\/2025 18:30","data_iso":"2025-05-24T21:30:00+00:00","status":"FT","resultado_casa":2,"resultado_visitante":1,"local":"Estadio Jornalista M\\u00e1rio Filho"},{"id":1351136,"campeonato":"Serie A","campeonato_id":71,"time_casa":"Sao Paulo","time_visitante":"Mirassol","data":"24\\/05\\/2025 18:30","data_iso":"2025-05-24T21:30:00+00:00","status":"FT","resultado_casa":0,"resultado_visitante":2,"local":"MorumBIS"},{"id":1351138,"campeonato":"Serie A","campeonato_id":71,"time_casa":"Atletico-MG","time_visitante":"Corinthians","data":"24\\/05\\/2025 21:00","data_iso":"2025-05-25T00:00:00+00:00","status":"FT","resultado_casa":0,"resultado_visitante":0,"local":"Arena MRV"},{"id":1351139,"campeonato":"Serie A","campeonato_id":71,"time_casa":"Gremio","time_visitante":"Bahia","data":"25\\/05\\/2025 11:00","data_iso":"2025-05-25T14:00:00+00:00","status":"FT","resultado_casa":1,"resultado_visitante":0,"local":"Arena do Gr\\u00eamio"},{"id":1351135,"campeonato":"Serie A","campeonato_id":71,"time_casa":"Palmeiras","time_visitante":"Flamengo","data":"25\\/05\\/2025 16:00","data_iso":"2025-05-25T19:00:00+00:00","status":"FT","resultado_casa":0,"resultado_visitante":2,"local":"Allianz Parque"},{"id":1351142,"campeonato":"Serie A","campeonato_id":71,"time_casa":"Sport Recife","time_visitante":"Internacional","data":"25\\/05\\/2025 16:00","data_iso":"2025-05-25T19:00:00+00:00","status":"FT","resultado_casa":1,"resultado_visitante":1,"local":"Est\\u00e1dio Adelmar da Costa Carvalho"},{"id":1351140,"campeonato":"Serie A","campeonato_id":71,"time_casa":"Vitoria","time_visitante":"Santos","data":"25\\/05\\/2025 18:30","data_iso":"2025-05-25T21:30:00+00:00","status":"FT","resultado_casa":0,"resultado_visitante":1,"local":"Est\\u00e1dio Manoel Barradas"},{"id":1351141,"campeonato":"Serie A","campeonato_id":71,"time_casa":"Fortaleza EC","time_visitante":"Cruzeiro","data":"25\\/05\\/2025 20:30","data_iso":"2025-05-25T23:30:00+00:00","status":"FT","resultado_casa":0,"resultado_visitante":2,"local":"Est\\u00e1dio Governador Pl\\u00e1cido Aderaldo Castelo"},{"id":1351137,"campeonato":"Serie A","campeonato_id":71,"time_casa":"RB Bragantino","time_visitante":"Juventude","data":"26\\/05\\/2025 20:00","data_iso":"2025-05-26T23:00:00+00:00","status":"FT","resultado_casa":1,"resultado_visitante":0,"local":"Est\\u00e1dio Cicero de Souza Marques"},{"id":1353341,"campeonato":"Serie B","campeonato_id":72,"time_casa":"Goias","time_visitante":"Ferrovi\\u00e1ria","data":"23\\/05\\/2025 21:35","data_iso":"2025-05-24T00:35:00+00:00","status":"FT","resultado_casa":2,"resultado_visitante":0,"local":"Est\\u00e1dio de Hail\\u00e9 Pinheiro"},{"id":1353340,"campeonato":"Serie B","campeonato_id":72,"time_casa":"Atletico Paranaense","time_visitante":"Athletic Club","data":"24\\/05\\/2025 16:00","data_iso":"2025-05-24T19:00:00+00:00","status":"FT","resultado_casa":1,"resultado_visitante":0,"local":"Ligga Arena"}]', '[{"id":"71","nome":"Brasileir\\u00e3o S\\u00e9rie A"},{"id":"72","nome":"Brasileir\\u00e3o S\\u00e9rie B"}]', '2025-05-23 11:42:57');
INSERT INTO `dados_boloes` (`id`, `nome`, `slug`, `descricao`, `data_inicio`, `data_fim`, `data_limite_palpitar`, `valor_participacao`, `premio_total`, `status`, `publico`, `max_participantes`, `quantidade_jogos`, `imagem_bolao_url`, `admin_id`, `jogos`, `campeonatos`, `data_criacao`) VALUES
	(5, 'Bolão 25/05/2025 a 31/05/2025', 'bolão-25-05-2025-a-31-05-2025', '', '2025-05-25', '2025-05-31', '2025-05-25', 10.00, 1000.00, 1, 1, NULL, 11, 'uploads/boloes/bolao_1748114201_68321b19bc76f.jpg', 1, '[{"id":1351138,"campeonato":"Serie A","campeonato_id":71,"time_casa":"Atletico-MG","time_visitante":"Corinthians","data":"24\\/05\\/2025 21:00","data_iso":"2025-05-25T00:00:00+00:00","status":"FT","resultado_casa":0,"resultado_visitante":0,"local":"Arena MRV"},{"id":1351139,"campeonato":"Serie A","campeonato_id":71,"time_casa":"Gremio","time_visitante":"Bahia","data":"25\\/05\\/2025 11:00","data_iso":"2025-05-25T14:00:00+00:00","status":"FT","resultado_casa":1,"resultado_visitante":0,"local":"Arena do Gr\\u00eamio"},{"id":1351135,"campeonato":"Serie A","campeonato_id":71,"time_casa":"Palmeiras","time_visitante":"Flamengo","data":"25\\/05\\/2025 16:00","data_iso":"2025-05-25T19:00:00+00:00","status":"FT","resultado_casa":0,"resultado_visitante":2,"local":"Allianz Parque"},{"id":1351142,"campeonato":"Serie A","campeonato_id":71,"time_casa":"Sport Recife","time_visitante":"Internacional","data":"25\\/05\\/2025 16:00","data_iso":"2025-05-25T19:00:00+00:00","status":"FT","resultado_casa":1,"resultado_visitante":1,"local":"Est\\u00e1dio Adelmar da Costa Carvalho"},{"id":1351140,"campeonato":"Serie A","campeonato_id":71,"time_casa":"Vitoria","time_visitante":"Santos","data":"25\\/05\\/2025 18:30","data_iso":"2025-05-25T21:30:00+00:00","status":"FT","resultado_casa":0,"resultado_visitante":1,"local":"Est\\u00e1dio Manoel Barradas"},{"id":1351141,"campeonato":"Serie A","campeonato_id":71,"time_casa":"Fortaleza EC","time_visitante":"Cruzeiro","data":"25\\/05\\/2025 20:30","data_iso":"2025-05-25T23:30:00+00:00","status":"FT","resultado_casa":0,"resultado_visitante":2,"local":"Est\\u00e1dio Governador Pl\\u00e1cido Aderaldo Castelo"},{"id":1351137,"campeonato":"Serie A","campeonato_id":71,"time_casa":"RB Bragantino","time_visitante":"Juventude","data":"26\\/05\\/2025 20:00","data_iso":"2025-05-26T23:00:00+00:00","status":"FT","resultado_casa":1,"resultado_visitante":0,"local":"Est\\u00e1dio Cicero de Souza Marques"},{"id":1351150,"campeonato":"Serie A","campeonato_id":71,"time_casa":"Bahia","time_visitante":"Sao Paulo","data":"31\\/05\\/2025 18:30","data_iso":"2025-05-31T21:30:00+00:00","status":"NS","resultado_casa":null,"resultado_visitante":null,"local":"Casa de Apostas Arena Fonte Nova"},{"id":1353348,"campeonato":"Serie B","campeonato_id":72,"time_casa":"Novorizontino","time_visitante":"Paysandu","data":"25\\/05\\/2025 16:00","data_iso":"2025-05-25T19:00:00+00:00","status":"FT","resultado_casa":3,"resultado_visitante":1,"local":"Est\\u00e1dio Dr. Jorge Ismael de Biasi"},{"id":1353346,"campeonato":"Serie B","campeonato_id":72,"time_casa":"Amazonas","time_visitante":"Operario-PR","data":"25\\/05\\/2025 18:30","data_iso":"2025-05-25T21:30:00+00:00","status":"FT","resultado_casa":2,"resultado_visitante":0,"local":"Est\\u00e1dio Municipal Carlos Zamith"},{"id":1353345,"campeonato":"Serie B","campeonato_id":72,"time_casa":"Criciuma","time_visitante":"Coritiba","data":"26\\/05\\/2025 19:00","data_iso":"2025-05-26T22:00:00+00:00","status":"FT","resultado_casa":0,"resultado_visitante":1,"local":"Est\\u00e1dio Heriberto H\\u00fclse"}]', '[{"id":"71","nome":"Brasileir\\u00e3o S\\u00e9rie A"},{"id":"72","nome":"Brasileir\\u00e3o S\\u00e9rie B"}]', '2025-05-24 16:17:03');
INSERT INTO `dados_boloes` (`id`, `nome`, `slug`, `descricao`, `data_inicio`, `data_fim`, `data_limite_palpitar`, `valor_participacao`, `premio_total`, `status`, `publico`, `max_participantes`, `quantidade_jogos`, `imagem_bolao_url`, `admin_id`, `jogos`, `campeonatos`, `data_criacao`) VALUES
	(6, 'Bolão 30/05/2025 a 01/06/2025', 'bolão-30-05-2025-a-01-06-2025', NULL, '2025-05-30', '2025-06-01', '2025-05-30', 10.00, 1000.00, 1, 1, NULL, 11, 'uploads/boloes/bolao_1748536725_68388d95815fa.png', 1, '[{"id":1351150,"campeonato":"Serie A","campeonato_id":71,"time_casa":"Bahia","time_visitante":"Sao Paulo","data":"31\\/05\\/2025 18:30","data_iso":"2025-05-31T21:30:00+00:00","status":"NS","resultado_casa":null,"resultado_visitante":null,"local":"Casa de Apostas Arena Fonte Nova"},{"id":1351144,"campeonato":"Serie A","campeonato_id":71,"time_casa":"Vasco DA Gama","time_visitante":"RB Bragantino","data":"31\\/05\\/2025 21:00","data_iso":"2025-06-01T00:00:00+00:00","status":"NS","resultado_casa":null,"resultado_visitante":null,"local":"Est\\u00e1dio S\\u00e3o Janu\\u00e1rio"},{"id":1351147,"campeonato":"Serie A","campeonato_id":71,"time_casa":"Mirassol","time_visitante":"Sport Recife","data":"01\\/06\\/2025 11:00","data_iso":"2025-06-01T14:00:00+00:00","status":"NS","resultado_casa":null,"resultado_visitante":null,"local":"Est\\u00e1dio Jos\\u00e9 Maria de Campos Maia"},{"id":1351146,"campeonato":"Serie A","campeonato_id":71,"time_casa":"Santos","time_visitante":"Botafogo","data":"01\\/06\\/2025 16:00","data_iso":"2025-06-01T19:00:00+00:00","status":"NS","resultado_casa":null,"resultado_visitante":null,"local":"Est\\u00e1dio Urbano Caldeira"},{"id":1351152,"campeonato":"Serie A","campeonato_id":71,"time_casa":"Juventude","time_visitante":"Gremio","data":"01\\/06\\/2025 16:00","data_iso":"2025-06-01T19:00:00+00:00","status":"NS","resultado_casa":null,"resultado_visitante":null,"local":"Est\\u00e1dio Alfredo Jaconi"},{"id":1351143,"campeonato":"Serie A","campeonato_id":71,"time_casa":"Flamengo","time_visitante":"Fortaleza EC","data":"01\\/06\\/2025 18:30","data_iso":"2025-06-01T21:30:00+00:00","status":"NS","resultado_casa":null,"resultado_visitante":null,"local":"Estadio Jornalista M\\u00e1rio Filho"},{"id":1351151,"campeonato":"Serie A","campeonato_id":71,"time_casa":"Ceara","time_visitante":"Atletico-MG","data":"01\\/06\\/2025 18:30","data_iso":"2025-06-01T21:30:00+00:00","status":"NS","resultado_casa":null,"resultado_visitante":null,"local":"Est\\u00e1dio Governador Pl\\u00e1cido Aderaldo Castelo"},{"id":1351145,"campeonato":"Serie A","campeonato_id":71,"time_casa":"Corinthians","time_visitante":"Vitoria","data":"01\\/06\\/2025 18:30","data_iso":"2025-06-01T21:30:00+00:00","status":"NS","resultado_casa":null,"resultado_visitante":null,"local":"Neo Qu\\u00edmica Arena"},{"id":1351148,"campeonato":"Serie A","campeonato_id":71,"time_casa":"Cruzeiro","time_visitante":"Palmeiras","data":"01\\/06\\/2025 19:30","data_iso":"2025-06-01T22:30:00+00:00","status":"NS","resultado_casa":null,"resultado_visitante":null,"local":"Est\\u00e1dio Governador Magalh\\u00e3es Pinto"},{"id":1351149,"campeonato":"Serie A","campeonato_id":71,"time_casa":"Internacional","time_visitante":"Fluminense","data":"01\\/06\\/2025 20:30","data_iso":"2025-06-01T23:30:00+00:00","status":"NS","resultado_casa":null,"resultado_visitante":null,"local":"Est\\u00e1dio Jos\\u00e9 Pinheiro Borda"},{"id":1353358,"campeonato":"Serie B","campeonato_id":72,"time_casa":"Ferrovi\\u00e1ria","time_visitante":"Botafogo SP","data":"29\\/05\\/2025 21:35","data_iso":"2025-05-30T00:35:00+00:00","status":"FT","resultado_casa":1,"resultado_visitante":1,"local":"Est\\u00e1dio Doutor Adhemar de Barros"}]', '[{"id":"71","nome":"Brasileir\\u00e3o S\\u00e9rie A"},{"id":"72","nome":"Brasileir\\u00e3o S\\u00e9rie B"}]', '2025-05-29 13:39:26');
INSERT INTO `dados_boloes` (`id`, `nome`, `slug`, `descricao`, `data_inicio`, `data_fim`, `data_limite_palpitar`, `valor_participacao`, `premio_total`, `status`, `publico`, `max_participantes`, `quantidade_jogos`, `imagem_bolao_url`, `admin_id`, `jogos`, `campeonatos`, `data_criacao`) VALUES
	(7, 'Bolão 30/05/2025 a 07/06/2025', 'bolão-30-05-2025-a-07-06-2025', '', '2025-05-30', '2025-06-07', '2025-05-31', 0.15, 1000.00, 1, 1, NULL, 11, 'uploads/boloes/bolao_1748624531_6839e493afea6.png', 1, '[{"id":1351150,"campeonato":"Serie A","campeonato_id":71,"time_casa":"Bahia","time_visitante":"Sao Paulo","data":"31\\/05\\/2025 18:30","data_iso":"2025-05-31T21:30:00+00:00","status":"NS","resultado_casa":null,"resultado_visitante":null,"local":"Casa de Apostas Arena Fonte Nova"},{"id":1351144,"campeonato":"Serie A","campeonato_id":71,"time_casa":"Vasco DA Gama","time_visitante":"RB Bragantino","data":"31\\/05\\/2025 21:00","data_iso":"2025-06-01T00:00:00+00:00","status":"NS","resultado_casa":null,"resultado_visitante":null,"local":"Est\\u00e1dio S\\u00e3o Janu\\u00e1rio"},{"id":1351147,"campeonato":"Serie A","campeonato_id":71,"time_casa":"Mirassol","time_visitante":"Sport Recife","data":"01\\/06\\/2025 11:00","data_iso":"2025-06-01T14:00:00+00:00","status":"NS","resultado_casa":null,"resultado_visitante":null,"local":"Est\\u00e1dio Jos\\u00e9 Maria de Campos Maia"},{"id":1351146,"campeonato":"Serie A","campeonato_id":71,"time_casa":"Santos","time_visitante":"Botafogo","data":"01\\/06\\/2025 16:00","data_iso":"2025-06-01T19:00:00+00:00","status":"NS","resultado_casa":null,"resultado_visitante":null,"local":"Est\\u00e1dio Urbano Caldeira"},{"id":1351152,"campeonato":"Serie A","campeonato_id":71,"time_casa":"Juventude","time_visitante":"Gremio","data":"01\\/06\\/2025 16:00","data_iso":"2025-06-01T19:00:00+00:00","status":"NS","resultado_casa":null,"resultado_visitante":null,"local":"Est\\u00e1dio Alfredo Jaconi"},{"id":1351143,"campeonato":"Serie A","campeonato_id":71,"time_casa":"Flamengo","time_visitante":"Fortaleza EC","data":"01\\/06\\/2025 18:30","data_iso":"2025-06-01T21:30:00+00:00","status":"NS","resultado_casa":null,"resultado_visitante":null,"local":"Estadio Jornalista M\\u00e1rio Filho"},{"id":1351151,"campeonato":"Serie A","campeonato_id":71,"time_casa":"Ceara","time_visitante":"Atletico-MG","data":"01\\/06\\/2025 18:30","data_iso":"2025-06-01T21:30:00+00:00","status":"NS","resultado_casa":null,"resultado_visitante":null,"local":"Est\\u00e1dio Governador Pl\\u00e1cido Aderaldo Castelo"},{"id":1351145,"campeonato":"Serie A","campeonato_id":71,"time_casa":"Corinthians","time_visitante":"Vitoria","data":"01\\/06\\/2025 18:30","data_iso":"2025-06-01T21:30:00+00:00","status":"NS","resultado_casa":null,"resultado_visitante":null,"local":"Neo Qu\\u00edmica Arena"},{"id":1351148,"campeonato":"Serie A","campeonato_id":71,"time_casa":"Cruzeiro","time_visitante":"Palmeiras","data":"01\\/06\\/2025 19:30","data_iso":"2025-06-01T22:30:00+00:00","status":"NS","resultado_casa":null,"resultado_visitante":null,"local":"Est\\u00e1dio Governador Magalh\\u00e3es Pinto"},{"id":1351149,"campeonato":"Serie A","campeonato_id":71,"time_casa":"Internacional","time_visitante":"Fluminense","data":"01\\/06\\/2025 20:30","data_iso":"2025-06-01T23:30:00+00:00","status":"NS","resultado_casa":null,"resultado_visitante":null,"local":"Est\\u00e1dio Jos\\u00e9 Pinheiro Borda"},{"id":1351134,"campeonato":"Serie A","campeonato_id":71,"time_casa":"Botafogo","time_visitante":"Ceara","data":"04\\/06\\/2025 20:00","data_iso":"2025-06-04T23:00:00+00:00","status":"NS","resultado_casa":null,"resultado_visitante":null,"local":"Est\\u00e1dio Ol\\u00edmpico Nilton Santos"}]', '[{"id":"71","nome":"Brasileir\\u00e3o S\\u00e9rie A"},{"id":"72","nome":"Brasileir\\u00e3o S\\u00e9rie B"}]', '2025-05-30 14:02:39');

-- Copiando estrutura para tabela bolao_football.jogador
CREATE TABLE IF NOT EXISTS `jogador` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nome` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `senha` varchar(255) NOT NULL,
  `telefone` varchar(20) DEFAULT NULL,
  `cpf` varchar(14) DEFAULT NULL,
  `status` enum('ativo','inativo') DEFAULT 'ativo',
  `data_cadastro` datetime DEFAULT current_timestamp(),
  `ultimo_acesso` datetime DEFAULT NULL,
  `token_recuperacao` varchar(100) DEFAULT NULL,
  `token_expira` datetime DEFAULT NULL,
  `saldo` decimal(10,2) DEFAULT 0.00,
  `pagamento_confirmado` tinyint(1) DEFAULT 0,
  `txid_pagamento` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `uk_cpf` (`cpf`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Copiando dados para a tabela bolao_football.jogador: ~4 rows (aproximadamente)
INSERT INTO `jogador` (`id`, `nome`, `email`, `senha`, `telefone`, `cpf`, `status`, `data_cadastro`, `ultimo_acesso`, `token_recuperacao`, `token_expira`, `saldo`, `pagamento_confirmado`, `txid_pagamento`) VALUES
	(1, 'JONATHAS QUINTANILHA', 'jogador@bolao.com', '$2y$12$5Q4bPMNugRrydUWBjvl5uu7tX83y1NW.PUa.j34zl/fF/p3YG7Joy', '(21) 96738-0813', NULL, 'ativo', '2025-05-24 16:26:20', NULL, NULL, NULL, 0.00, 0, 'BOL0010071748656198e608a974d02c54e3');
INSERT INTO `jogador` (`id`, `nome`, `email`, `senha`, `telefone`, `cpf`, `status`, `data_cadastro`, `ultimo_acesso`, `token_recuperacao`, `token_expira`, `saldo`, `pagamento_confirmado`, `txid_pagamento`) VALUES
	(2, 'JONATHAS QUINTANILHA', 'traffego.mkt@gmail.com', '$2y$12$NkS/7fN1zjHYg3vMLrHiF.x1KuqkE3R3Je8VkQrnfF3VySxt35WHu', '(21) 96738-0813', NULL, 'ativo', '2025-05-29 14:46:06', NULL, NULL, NULL, 0.00, 0, NULL);
INSERT INTO `jogador` (`id`, `nome`, `email`, `senha`, `telefone`, `cpf`, `status`, `data_cadastro`, `ultimo_acesso`, `token_recuperacao`, `token_expira`, `saldo`, `pagamento_confirmado`, `txid_pagamento`) VALUES
	(3, 'JONATHAS QUINTANILHA', 'milton@bolaoforte.com', '$2y$12$ga29B/T/9Y/gs.EaXyC.ROLgHyVgGT317ee9kJurhoTBbuU4/DOM6', '(21) 96738-0813', '12255175754', 'ativo', '2025-05-30 14:27:34', NULL, NULL, NULL, 0.00, 0, NULL);
INSERT INTO `jogador` (`id`, `nome`, `email`, `senha`, `telefone`, `cpf`, `status`, `data_cadastro`, `ultimo_acesso`, `token_recuperacao`, `token_expira`, `saldo`, `pagamento_confirmado`, `txid_pagamento`) VALUES
	(4, 'Milton Cunha', 'miltoncunha@bolaodomeuvo.com', '$2y$12$TAf0aC9nmSdK8m5grY1ogOnTf2NpxIopItle.aYiuOMvSj7gNWIyK', '(77) 98765-4321', '12221212121', 'ativo', '2025-05-30 14:28:40', NULL, NULL, NULL, 0.00, 0, NULL);

-- Copiando estrutura para tabela bolao_football.jogos
CREATE TABLE IF NOT EXISTS `jogos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `bolao_id` int(11) NOT NULL,
  `equipe_casa` varchar(100) NOT NULL,
  `equipe_visitante` varchar(100) NOT NULL,
  `campeonato` varchar(100) NOT NULL,
  `data_hora` datetime NOT NULL,
  `local` varchar(100) DEFAULT NULL,
  `peso` int(11) DEFAULT 1,
  `status` enum('agendado','em_andamento','finalizado','cancelado') DEFAULT 'agendado',
  `id_externo` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `bolao_id` (`bolao_id`),
  CONSTRAINT `jogos_ibfk_1` FOREIGN KEY (`bolao_id`) REFERENCES `boloes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Copiando dados para a tabela bolao_football.jogos: ~0 rows (aproximadamente)

-- Copiando estrutura para tabela bolao_football.logs
CREATE TABLE IF NOT EXISTS `logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tipo` varchar(50) NOT NULL COMMENT 'Tipo de log (ex: configuracao, pagamento, etc)',
  `descricao` text NOT NULL COMMENT 'Descrição detalhada do log',
  `usuario_id` int(11) NOT NULL COMMENT 'ID do usuário que realizou a ação',
  `data_hora` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Data e hora do registro',
  `dados_adicionais` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Dados adicionais em formato JSON (opcional)' CHECK (json_valid(`dados_adicionais`)),
  `ip_address` varchar(45) DEFAULT NULL COMMENT 'Endereço IP do usuário',
  PRIMARY KEY (`id`),
  KEY `idx_tipo` (`tipo`),
  KEY `idx_usuario` (`usuario_id`),
  KEY `idx_data` (`data_hora`),
  CONSTRAINT `logs_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `jogador` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Copiando dados para a tabela bolao_football.logs: ~7 rows (aproximadamente)
INSERT INTO `logs` (`id`, `tipo`, `descricao`, `usuario_id`, `data_hora`, `dados_adicionais`, `ip_address`) VALUES
	(1, 'configuracao', 'Alteração nas configurações do Pix', 1, '2025-05-30 21:35:23', NULL, NULL);
INSERT INTO `logs` (`id`, `tipo`, `descricao`, `usuario_id`, `data_hora`, `dados_adicionais`, `ip_address`) VALUES
	(2, 'configuracao', 'Alteração nas configurações do Pix', 1, '2025-05-30 21:41:28', NULL, NULL);
INSERT INTO `logs` (`id`, `tipo`, `descricao`, `usuario_id`, `data_hora`, `dados_adicionais`, `ip_address`) VALUES
	(3, 'configuracao', 'Alteração nas configurações do Pix', 1, '2025-05-30 21:49:08', NULL, NULL);
INSERT INTO `logs` (`id`, `tipo`, `descricao`, `usuario_id`, `data_hora`, `dados_adicionais`, `ip_address`) VALUES
	(4, 'configuracao', 'Upload de certificado P12', 1, '2025-05-30 21:50:27', NULL, '::1');
INSERT INTO `logs` (`id`, `tipo`, `descricao`, `usuario_id`, `data_hora`, `dados_adicionais`, `ip_address`) VALUES
	(5, 'configuracao', 'Upload de certificado P12', 1, '2025-05-30 22:17:23', NULL, '::1');
INSERT INTO `logs` (`id`, `tipo`, `descricao`, `usuario_id`, `data_hora`, `dados_adicionais`, `ip_address`) VALUES
	(6, 'configuracao', 'Upload de certificado P12', 1, '2025-05-30 22:17:51', NULL, '::1');
INSERT INTO `logs` (`id`, `tipo`, `descricao`, `usuario_id`, `data_hora`, `dados_adicionais`, `ip_address`) VALUES
	(7, 'configuracao', 'Upload de certificado P12', 1, '2025-05-31 01:36:50', NULL, '::1');

-- Copiando estrutura para tabela bolao_football.pagamentos
CREATE TABLE IF NOT EXISTS `pagamentos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `jogador_id` int(11) NOT NULL,
  `bolao_id` int(11) NOT NULL,
  `valor` decimal(10,2) NOT NULL,
  `tipo` enum('entrada','saida') NOT NULL,
  `status` enum('pendente','confirmado','cancelado') DEFAULT 'pendente',
  `data_pagamento` datetime DEFAULT current_timestamp(),
  `metodo_pagamento` varchar(50) DEFAULT NULL,
  `comprovante_url` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `jogador_id` (`jogador_id`),
  KEY `bolao_id` (`bolao_id`),
  CONSTRAINT `pagamentos_ibfk_1` FOREIGN KEY (`jogador_id`) REFERENCES `jogador` (`id`) ON DELETE CASCADE,
  CONSTRAINT `pagamentos_ibfk_2` FOREIGN KEY (`bolao_id`) REFERENCES `boloes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Copiando dados para a tabela bolao_football.pagamentos: ~0 rows (aproximadamente)

-- Copiando estrutura para tabela bolao_football.palpites
CREATE TABLE IF NOT EXISTS `palpites` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `jogador_id` int(11) NOT NULL,
  `bolao_id` int(11) NOT NULL,
  `palpites` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL COMMENT 'JSON com os palpites no formato: {"jogo_id": "1"} onde 1=casa vence, 0=empate, 2=visitante vence' CHECK (json_valid(`palpites`)),
  `data_palpite` datetime DEFAULT current_timestamp(),
  `status` enum('pendente','pago','cancelado') NOT NULL DEFAULT 'pendente',
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_palpite` (`jogador_id`,`bolao_id`),
  KEY `bolao_id` (`bolao_id`),
  CONSTRAINT `palpites_ibfk_1` FOREIGN KEY (`jogador_id`) REFERENCES `jogador` (`id`) ON DELETE CASCADE,
  CONSTRAINT `palpites_ibfk_2` FOREIGN KEY (`bolao_id`) REFERENCES `boloes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Copiando dados para a tabela bolao_football.palpites: ~0 rows (aproximadamente)

-- Copiando estrutura para tabela bolao_football.participacoes
CREATE TABLE IF NOT EXISTS `participacoes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `bolao_id` int(11) NOT NULL,
  `jogador_id` int(11) NOT NULL,
  `data_participacao` datetime DEFAULT current_timestamp(),
  `status` enum('pendente','confirmado','cancelado') DEFAULT 'pendente',
  `valor_pago` decimal(10,2) DEFAULT 0.00,
  `data_pagamento` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_participacao` (`bolao_id`,`jogador_id`),
  KEY `jogador_id` (`jogador_id`),
  CONSTRAINT `participacoes_ibfk_1` FOREIGN KEY (`bolao_id`) REFERENCES `boloes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `participacoes_ibfk_2` FOREIGN KEY (`jogador_id`) REFERENCES `jogador` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Copiando dados para a tabela bolao_football.participacoes: ~0 rows (aproximadamente)

-- Copiando estrutura para tabela bolao_football.regras_bolao
CREATE TABLE IF NOT EXISTS `regras_bolao` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `pontos_acerto_exato` int(11) NOT NULL DEFAULT 10,
  `pontos_acerto_vencedor` int(11) NOT NULL DEFAULT 5,
  `pontos_acerto_placar_time1` int(11) NOT NULL DEFAULT 3,
  `pontos_acerto_placar_time2` int(11) NOT NULL DEFAULT 3,
  `pontos_erro_total` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Copiando dados para a tabela bolao_football.regras_bolao: ~0 rows (aproximadamente)

-- Copiando estrutura para tabela bolao_football.resultados
CREATE TABLE IF NOT EXISTS `resultados` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `jogo_id` int(11) NOT NULL,
  `placar_casa` int(11) NOT NULL,
  `placar_visitante` int(11) NOT NULL,
  `data_resultado` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_resultado` (`jogo_id`),
  CONSTRAINT `resultados_ibfk_1` FOREIGN KEY (`jogo_id`) REFERENCES `jogos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Copiando dados para a tabela bolao_football.resultados: ~0 rows (aproximadamente)

/*!40103 SET TIME_ZONE=IFNULL(@OLD_TIME_ZONE, 'system') */;
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;
