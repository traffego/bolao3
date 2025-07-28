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
REPLACE INTO `administrador` (`id`, `nome`, `email`, `senha`, `status`, `data_cadastro`, `ultimo_acesso`, `ultimo_login`) VALUES
	(1, 'Administrador', 'admin@bolao.com', '$2y$10$BOxWWRWkbsrwrV.GSN6/SOSmjSj/8ad98mYask3Bazjup6DnIVMiq', 'ativo', '2025-05-20 18:08:25', NULL, '2025-07-24 16:38:06');

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

-- Copiando estrutura para tabela bolao_football.afiliados_comissoes
CREATE TABLE IF NOT EXISTS `afiliados_comissoes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `afiliado_id` int(11) NOT NULL,
  `jogador_id` int(11) NOT NULL,
  `pagamento_id` int(11) NOT NULL,
  `valor_pagamento` decimal(10,2) NOT NULL,
  `percentual_comissao` decimal(5,2) NOT NULL,
  `valor_comissao` decimal(10,2) NOT NULL,
  `status` enum('pendente','pago') DEFAULT 'pendente',
  `data_criacao` datetime DEFAULT current_timestamp(),
  `data_pagamento` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `afiliado_id` (`afiliado_id`),
  KEY `jogador_id` (`jogador_id`),
  KEY `pagamento_id` (`pagamento_id`),
  CONSTRAINT `afiliados_comissoes_ibfk_1` FOREIGN KEY (`afiliado_id`) REFERENCES `afiliados` (`id`),
  CONSTRAINT `afiliados_comissoes_ibfk_2` FOREIGN KEY (`jogador_id`) REFERENCES `jogador` (`id`),
  CONSTRAINT `afiliados_comissoes_ibfk_3` FOREIGN KEY (`pagamento_id`) REFERENCES `pagamentos` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Copiando dados para a tabela bolao_football.afiliados_comissoes: ~0 rows (aproximadamente)

-- Copiando estrutura para tabela bolao_football.afiliados_indicacoes
CREATE TABLE IF NOT EXISTS `afiliados_indicacoes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `afiliado_id` int(11) NOT NULL,
  `jogador_id` int(11) NOT NULL,
  `data_indicacao` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `afiliado_id` (`afiliado_id`),
  KEY `jogador_id` (`jogador_id`),
  CONSTRAINT `afiliados_indicacoes_ibfk_1` FOREIGN KEY (`afiliado_id`) REFERENCES `afiliados` (`id`),
  CONSTRAINT `afiliados_indicacoes_ibfk_2` FOREIGN KEY (`jogador_id`) REFERENCES `jogador` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Copiando dados para a tabela bolao_football.afiliados_indicacoes: ~0 rows (aproximadamente)

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
  UNIQUE KEY `uk_nome_categoria` (`nome_configuracao`,`categoria`),
  CONSTRAINT `chk_modelo_pagamento` CHECK (`nome_configuracao` <> 'modelo_pagamento' or `valor` in ('por_aposta','conta_saldo'))
) ENGINE=InnoDB AUTO_INCREMENT=45 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Copiando dados para a tabela bolao_football.configuracoes: ~10 rows (aproximadamente)
REPLACE INTO `configuracoes` (`id`, `nome_configuracao`, `valor`, `categoria`, `descricao`, `data_criacao`, `data_atualizacao`) VALUES
	(1, 'site_name', '"Bolão de Futebol"', 'geral', 'Nome do site', '2025-05-30 19:38:41', '2025-07-22 15:44:16'),
	(2, 'site_description', '"O melhor sistema de bolões de futebol!"', 'geral', 'Descrição do site', '2025-05-30 19:38:41', '2025-07-22 15:44:16'),
	(3, 'moeda', '"BRL"', 'geral', 'Moeda padrão do sistema', '2025-05-30 19:38:41', '2025-07-22 15:44:16'),
	(13, 'api_football', '{"api_key":"ad0b29bd4984b69fd16d9680c6d017c1","base_url":"https:\\/\\/v3.football.api-sports.io","last_request":"2025-07-22 23:16:24"}', 'api', 'Configurações da API Football', '2025-05-30 19:38:41', '2025-07-22 23:16:24'),
	(19, 'pagamento', '{"pix_key":"EMAIL@EXAMPLE.COM"}', 'pagamento', 'Configurações de pagamento', '2025-05-30 19:38:41', '2025-05-24 22:01:08'),
	(21, 'efi_pix_config', '{\n    "ambiente": "producao",\n    "client_id": "Client_Id_3e9ce7b7f569d0a4aa8f9ec8b172c3ed7dd9d948",\n    "client_secret": "Client_Secret_31e8f33edba74286002f4c91a2df6896f2764fd1",\n    "pix_key": "60409292-a359-4992-9f5f-5886bace6fe6",\n    "webhook_url": "https://localhost/bolao3/api/webhook_pix.php"\n}', 'pagamentos', 'Configurações da API Pix da Efí', '2025-05-30 19:38:41', '2025-07-23 16:41:52'),
	(24, 'tipos_logs', '["configuracao","pagamento","palpite","bolao","usuario","sistema"]', 'sistema', 'Tipos de logs permitidos no sistema', '2025-05-30 21:34:58', '2025-05-30 18:34:58'),
	(29, 'api_football_key', 'ad0b29bd4984b69fd16d9680c6d017c1', 'api', 'Chave da API Football', '2025-07-04 16:31:29', '2025-07-16 17:22:09'),
	(31, 'deposito_minimo', '1.00', 'pagamento', 'Valor mínimo para depósito', '2025-07-04 19:26:04', '2025-07-23 15:38:10'),
	(44, 'deposito_maximo', '5000.00', 'pagamento', 'Valor máximo para depósito', '2025-07-24 20:52:54', '2025-07-24 17:52:54');

-- Copiando estrutura para tabela bolao_football.config_pagamentos
CREATE TABLE IF NOT EXISTS `config_pagamentos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `chave` varchar(100) NOT NULL,
  `valor` text NOT NULL,
  `descricao` text DEFAULT NULL,
  `data_atualizacao` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Copiando dados para a tabela bolao_football.config_pagamentos: ~8 rows (aproximadamente)
REPLACE INTO `config_pagamentos` (`id`, `chave`, `valor`, `descricao`, `data_atualizacao`) VALUES
	(1, 'deposito_minimo', '10.00', 'Valor mínimo para depósito', '2025-07-04 16:10:00'),
	(2, 'deposito_maximo', '5000.00', 'Valor máximo para depósito', '2025-07-04 16:10:00'),
	(3, 'saque_minimo', '30.00', 'Valor mínimo para saque', '2025-07-04 16:10:00'),
	(4, 'saque_maximo', '5000.00', 'Valor máximo para saque', '2025-07-04 16:10:00'),
	(5, 'taxa_saque', '0.00', 'Taxa cobrada por saque', '2025-07-04 16:10:00'),
	(6, 'prazo_saque', '2', 'Prazo em dias úteis para processar saques', '2025-07-04 16:10:00'),
	(7, 'metodos_deposito', '["pix", "cartao_credito"]', 'Métodos de pagamento aceitos para depósito', '2025-07-04 16:10:00'),
	(8, 'metodos_saque', '["pix", "transferencia_bancaria"]', 'Métodos de pagamento aceitos para saque', '2025-07-04 16:10:00');

-- Copiando estrutura para tabela bolao_football.contas
CREATE TABLE IF NOT EXISTS `contas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `jogador_id` int(11) NOT NULL,
  `status` enum('ativo','bloqueado','suspenso') DEFAULT 'ativo',
  `data_criacao` datetime DEFAULT current_timestamp(),
  `data_atualizacao` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `jogador_id` (`jogador_id`),
  CONSTRAINT `contas_ibfk_1` FOREIGN KEY (`jogador_id`) REFERENCES `jogador` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Copiando dados para a tabela bolao_football.contas: ~2 rows (aproximadamente)
REPLACE INTO `contas` (`id`, `jogador_id`, `status`, `data_criacao`, `data_atualizacao`) VALUES
	(1, 1, 'ativo', '2025-07-23 00:36:24', '2025-07-23 00:36:24'),
	(2, 4, 'ativo', '2025-07-23 15:58:45', '2025-07-23 15:58:45');

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
  `premio_rodada` decimal(10,2) DEFAULT 0.00,
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
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Copiando dados para a tabela bolao_football.dados_boloes: ~3 rows (aproximadamente)
REPLACE INTO `dados_boloes` (`id`, `nome`, `slug`, `descricao`, `data_inicio`, `data_fim`, `data_limite_palpitar`, `valor_participacao`, `premio_rodada`, `premio_total`, `status`, `publico`, `max_participantes`, `quantidade_jogos`, `imagem_bolao_url`, `admin_id`, `jogos`, `campeonatos`, `data_criacao`) VALUES
	(13, 'Bolão 22/07/2025 a 30/07/2025', 'bolão-22-07-2025-a-30-07-2025', '', '2025-07-22', '2025-07-30', '0000-00-00', 10.00, 1000.00, 5000.00, 1, 1, NULL, 11, 'uploads/boloes/bolao_1753195292_687fa31c974df.png', 1, '[{"id":1351193,"campeonato":"Serie A","campeonato_id":71,"time_casa":"Fluminense","time_visitante":"Palmeiras","data":"23\\/07\\/2025 19:00","data_iso":"2025-07-23T22:00:00+00:00","status":"NS","resultado_casa":null,"resultado_visitante":null,"local":"Estadio Jornalista M\\u00e1rio Filho"},{"id":1351201,"campeonato":"Serie A","campeonato_id":71,"time_casa":"Ceara","time_visitante":"Mirassol","data":"23\\/07\\/2025 19:00","data_iso":"2025-07-23T22:00:00+00:00","status":"NS","resultado_casa":null,"resultado_visitante":null,"local":"Est\\u00e1dio Governador Pl\\u00e1cido Aderaldo Castelo"},{"id":1351195,"campeonato":"Serie A","campeonato_id":71,"time_casa":"Corinthians","time_visitante":"Cruzeiro","data":"23\\/07\\/2025 19:30","data_iso":"2025-07-23T22:30:00+00:00","status":"NS","resultado_casa":null,"resultado_visitante":null,"local":"Neo Qu\\u00edmica Arena"},{"id":1351196,"campeonato":"Serie A","campeonato_id":71,"time_casa":"Santos","time_visitante":"Internacional","data":"23\\/07\\/2025 21:30","data_iso":"2025-07-24T00:30:00+00:00","status":"NS","resultado_casa":null,"resultado_visitante":null,"local":"Est\\u00e1dio Urbano Caldeira"},{"id":1351200,"campeonato":"Serie A","campeonato_id":71,"time_casa":"Vitoria","time_visitante":"Sport Recife","data":"23\\/07\\/2025 21:30","data_iso":"2025-07-24T00:30:00+00:00","status":"NS","resultado_casa":null,"resultado_visitante":null,"local":"Est\\u00e1dio Manoel Barradas"},{"id":1351197,"campeonato":"Serie A","campeonato_id":71,"time_casa":"RB Bragantino","time_visitante":"Flamengo","data":"23\\/07\\/2025 21:30","data_iso":"2025-07-24T00:30:00+00:00","status":"NS","resultado_casa":null,"resultado_visitante":null,"local":"Est\\u00e1dio Cicero de Souza Marques"},{"id":1351202,"campeonato":"Serie A","campeonato_id":71,"time_casa":"Juventude","time_visitante":"Sao Paulo","data":"24\\/07\\/2025 19:00","data_iso":"2025-07-24T22:00:00+00:00","status":"NS","resultado_casa":null,"resultado_visitante":null,"local":"Est\\u00e1dio Alfredo Jaconi"},{"id":1351204,"campeonato":"Serie A","campeonato_id":71,"time_casa":"Botafogo","time_visitante":"Corinthians","data":"26\\/07\\/2025 18:30","data_iso":"2025-07-26T21:30:00+00:00","status":"NS","resultado_casa":null,"resultado_visitante":null,"local":"Est\\u00e1dio Ol\\u00edmpico Nilton Santos"},{"id":1351212,"campeonato":"Serie A","campeonato_id":71,"time_casa":"Sport Recife","time_visitante":"Santos","data":"26\\/07\\/2025 18:30","data_iso":"2025-07-26T21:30:00+00:00","status":"NS","resultado_casa":null,"resultado_visitante":null,"local":"Est\\u00e1dio Adelmar da Costa Carvalho"},{"id":1351211,"campeonato":"Serie A","campeonato_id":71,"time_casa":"Fortaleza EC","time_visitante":"RB Bragantino","data":"26\\/07\\/2025 18:30","data_iso":"2025-07-26T21:30:00+00:00","status":"NS","resultado_casa":null,"resultado_visitante":null,"local":"Est\\u00e1dio Governador Pl\\u00e1cido Aderaldo Castelo"},{"id":1351207,"campeonato":"Serie A","campeonato_id":71,"time_casa":"Mirassol","time_visitante":"Vitoria","data":"26\\/07\\/2025 18:30","data_iso":"2025-07-26T21:30:00+00:00","status":"NS","resultado_casa":null,"resultado_visitante":null,"local":"Est\\u00e1dio Jos\\u00e9 Maria de Campos Maia"}]', '[{"id":"71","nome":"Brasileir\\u00e3o S\\u00e9rie A"},{"id":"72","nome":"Brasileir\\u00e3o S\\u00e9rie B"}]', '2025-07-22 11:42:02'),
	(14, 'Bolão 22/07/2025 a 30/07/2025', 'bolão-22-07-2025-a-30-07-2025-1753236704', '', '2025-07-22', '2025-07-30', NULL, 10.00, 0.00, 1000.00, 1, 1, NULL, 11, 'uploads/boloes/bolao_1753236683_688044cbaf19a.png', 1, '[{"id":1351199,"campeonato":"Serie A","campeonato_id":71,"time_casa":"Gremio","time_visitante":"Botafogo","data":"2025-07-23 12:00:00","data_formatada":"23\\/07\\/2025 12:00","status":"NS","resultado_casa":null,"resultado_visitante":null,"local":"Arena do Gr\\u00eamio"},{"id":1351194,"campeonato":"Serie A","campeonato_id":71,"time_casa":"Vasco DA Gama","time_visitante":"Bahia","data":"2025-07-23 12:00:00","data_formatada":"23\\/07\\/2025 12:00","status":"NS","resultado_casa":null,"resultado_visitante":null,"local":"Est\\u00e1dio S\\u00e3o Janu\\u00e1rio"},{"id":1351198,"campeonato":"Serie A","campeonato_id":71,"time_casa":"Atletico-MG","time_visitante":"Fortaleza EC","data":"2025-07-23 12:00:00","data_formatada":"23\\/07\\/2025 12:00","status":"NS","resultado_casa":null,"resultado_visitante":null,"local":"Arena MRV"},{"id":1351193,"campeonato":"Serie A","campeonato_id":71,"time_casa":"Fluminense","time_visitante":"Palmeiras","data":"2025-07-23 19:00:00","data_formatada":"23\\/07\\/2025 19:00","status":"NS","resultado_casa":null,"resultado_visitante":null,"local":"Estadio Jornalista M\\u00e1rio Filho"},{"id":1351201,"campeonato":"Serie A","campeonato_id":71,"time_casa":"Ceara","time_visitante":"Mirassol","data":"2025-07-23 19:00:00","data_formatada":"23\\/07\\/2025 19:00","status":"NS","resultado_casa":null,"resultado_visitante":null,"local":"Est\\u00e1dio Governador Pl\\u00e1cido Aderaldo Castelo"},{"id":1351195,"campeonato":"Serie A","campeonato_id":71,"time_casa":"Corinthians","time_visitante":"Cruzeiro","data":"2025-07-23 19:30:00","data_formatada":"23\\/07\\/2025 19:30","status":"NS","resultado_casa":null,"resultado_visitante":null,"local":"Neo Qu\\u00edmica Arena"},{"id":1351196,"campeonato":"Serie A","campeonato_id":71,"time_casa":"Santos","time_visitante":"Internacional","data":"2025-07-23 21:30:00","data_formatada":"23\\/07\\/2025 21:30","status":"NS","resultado_casa":null,"resultado_visitante":null,"local":"Est\\u00e1dio Urbano Caldeira"},{"id":1351200,"campeonato":"Serie A","campeonato_id":71,"time_casa":"Vitoria","time_visitante":"Sport Recife","data":"2025-07-23 21:30:00","data_formatada":"23\\/07\\/2025 21:30","status":"NS","resultado_casa":null,"resultado_visitante":null,"local":"Est\\u00e1dio Manoel Barradas"},{"id":1351197,"campeonato":"Serie A","campeonato_id":71,"time_casa":"RB Bragantino","time_visitante":"Flamengo","data":"2025-07-23 21:30:00","data_formatada":"23\\/07\\/2025 21:30","status":"NS","resultado_casa":null,"resultado_visitante":null,"local":"Est\\u00e1dio Cicero de Souza Marques"},{"id":1351202,"campeonato":"Serie A","campeonato_id":71,"time_casa":"Juventude","time_visitante":"Sao Paulo","data":"2025-07-24 19:00:00","data_formatada":"24\\/07\\/2025 19:00","status":"NS","resultado_casa":null,"resultado_visitante":null,"local":"Est\\u00e1dio Alfredo Jaconi"},{"id":1351204,"campeonato":"Serie A","campeonato_id":71,"time_casa":"Botafogo","time_visitante":"Corinthians","data":"2025-07-26 18:30:00","data_formatada":"26\\/07\\/2025 18:30","status":"NS","resultado_casa":null,"resultado_visitante":null,"local":"Est\\u00e1dio Ol\\u00edmpico Nilton Santos"}]', '[{"id":"71","nome":"Brasileir\\u00e3o S\\u00e9rie A"},{"id":"72","nome":"Brasileir\\u00e3o S\\u00e9rie B"}]', '2025-07-22 23:11:53'),
	(15, 'Bolão 22/07/2025 a 30/07/2025 teste', 'bolão-22-07-2025-a-30-07-2025-teste', '', '2025-07-22', '2025-07-30', NULL, 10.00, 0.00, 1000.00, 1, 1, NULL, 11, 'uploads/boloes/bolao_1753236984_688045f881e90.png', 1, '[{"id":1351199,"campeonato":"Serie A","campeonato_id":71,"time_casa":"Gremio","time_visitante":"Botafogo","data":"2025-07-23 12:00:00","data_formatada":"23\\/07\\/2025 12:00","status":"NS","resultado_casa":null,"resultado_visitante":null,"local":"Arena do Gr\\u00eamio"},{"id":1351194,"campeonato":"Serie A","campeonato_id":71,"time_casa":"Vasco DA Gama","time_visitante":"Bahia","data":"2025-07-23 12:00:00","data_formatada":"23\\/07\\/2025 12:00","status":"NS","resultado_casa":null,"resultado_visitante":null,"local":"Est\\u00e1dio S\\u00e3o Janu\\u00e1rio"},{"id":1351198,"campeonato":"Serie A","campeonato_id":71,"time_casa":"Atletico-MG","time_visitante":"Fortaleza EC","data":"2025-07-23 12:00:00","data_formatada":"23\\/07\\/2025 12:00","status":"NS","resultado_casa":null,"resultado_visitante":null,"local":"Arena MRV"},{"id":1351193,"campeonato":"Serie A","campeonato_id":71,"time_casa":"Fluminense","time_visitante":"Palmeiras","data":"2025-07-23 19:00:00","data_formatada":"23\\/07\\/2025 19:00","status":"NS","resultado_casa":null,"resultado_visitante":null,"local":"Estadio Jornalista M\\u00e1rio Filho"},{"id":1351201,"campeonato":"Serie A","campeonato_id":71,"time_casa":"Ceara","time_visitante":"Mirassol","data":"2025-07-23 19:00:00","data_formatada":"23\\/07\\/2025 19:00","status":"NS","resultado_casa":null,"resultado_visitante":null,"local":"Est\\u00e1dio Governador Pl\\u00e1cido Aderaldo Castelo"},{"id":1351195,"campeonato":"Serie A","campeonato_id":71,"time_casa":"Corinthians","time_visitante":"Cruzeiro","data":"2025-07-23 19:30:00","data_formatada":"23\\/07\\/2025 19:30","status":"NS","resultado_casa":null,"resultado_visitante":null,"local":"Neo Qu\\u00edmica Arena"},{"id":1351196,"campeonato":"Serie A","campeonato_id":71,"time_casa":"Santos","time_visitante":"Internacional","data":"2025-07-23 21:30:00","data_formatada":"23\\/07\\/2025 21:30","status":"NS","resultado_casa":null,"resultado_visitante":null,"local":"Est\\u00e1dio Urbano Caldeira"},{"id":1351200,"campeonato":"Serie A","campeonato_id":71,"time_casa":"Vitoria","time_visitante":"Sport Recife","data":"2025-07-23 21:30:00","data_formatada":"23\\/07\\/2025 21:30","status":"NS","resultado_casa":null,"resultado_visitante":null,"local":"Est\\u00e1dio Manoel Barradas"},{"id":1351197,"campeonato":"Serie A","campeonato_id":71,"time_casa":"RB Bragantino","time_visitante":"Flamengo","data":"2025-07-23 21:30:00","data_formatada":"23\\/07\\/2025 21:30","status":"NS","resultado_casa":null,"resultado_visitante":null,"local":"Est\\u00e1dio Cicero de Souza Marques"},{"id":1351202,"campeonato":"Serie A","campeonato_id":71,"time_casa":"Juventude","time_visitante":"Sao Paulo","data":"2025-07-24 19:00:00","data_formatada":"24\\/07\\/2025 19:00","status":"NS","resultado_casa":null,"resultado_visitante":null,"local":"Est\\u00e1dio Alfredo Jaconi"},{"id":1351204,"campeonato":"Serie A","campeonato_id":71,"time_casa":"Botafogo","time_visitante":"Corinthians","data":"2025-07-26 18:30:00","data_formatada":"26\\/07\\/2025 18:30","status":"NS","resultado_casa":null,"resultado_visitante":null,"local":"Est\\u00e1dio Ol\\u00edmpico Nilton Santos"}]', '[{"id":"71","nome":"Brasileir\\u00e3o S\\u00e9rie A"},{"id":"72","nome":"Brasileir\\u00e3o S\\u00e9rie B"}]', '2025-07-22 23:17:04');

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
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `uk_cpf` (`cpf`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Copiando dados para a tabela bolao_football.jogador: ~5 rows (aproximadamente)
REPLACE INTO `jogador` (`id`, `nome`, `email`, `senha`, `telefone`, `cpf`, `status`, `data_cadastro`, `ultimo_acesso`, `token_recuperacao`, `token_expira`) VALUES
	(1, 'JONATHAS QUINTANILHA', 'jogador@bolao.com', '$2y$12$5Q4bPMNugRrydUWBjvl5uu7tX83y1NW.PUa.j34zl/fF/p3YG7Joy', '(21) 96738-0813', NULL, 'ativo', '2025-05-24 16:26:20', NULL, NULL, NULL),
	(2, 'JONATHAS QUINTANILHA', 'traffego.mkt@gmail.com', '$2y$12$NkS/7fN1zjHYg3vMLrHiF.x1KuqkE3R3Je8VkQrnfF3VySxt35WHu', '(21) 96738-0813', NULL, 'ativo', '2025-05-29 14:46:06', NULL, NULL, NULL),
	(3, 'JONATHAS QUINTANILHA', 'milton@bolaoforte.com', '$2y$12$ga29B/T/9Y/gs.EaXyC.ROLgHyVgGT317ee9kJurhoTBbuU4/DOM6', '(21) 96738-0813', '12255175754', 'ativo', '2025-05-30 14:27:34', NULL, NULL, NULL),
	(4, 'Milton Cunha', 'miltoncunha@bolao.com', '$2y$10$W9/VykpLj1SPVIJx.O6bhOAHMuNRRsZjxaXlDsDmXp.I9HWYpoKaa', '(77) 98765-4321', '12221212121', 'ativo', '2025-05-30 14:28:40', NULL, NULL, NULL),
	(5, 'Central Do Script', 'central_jogador@bolao.com', '$2y$10$/dOaRFI9bBjZjLyD.Pv8FO/bD0863j.jIb40MxCzGAxdBTrNAbqyW', '(21) 9673-80813', '46143888000', 'ativo', '2025-06-05 19:34:54', NULL, NULL, NULL);

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
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Copiando dados para a tabela bolao_football.logs: ~19 rows (aproximadamente)
REPLACE INTO `logs` (`id`, `tipo`, `descricao`, `usuario_id`, `data_hora`, `dados_adicionais`, `ip_address`) VALUES
	(1, 'configuracao', 'Alteração nas configurações do Pix', 1, '2025-05-30 21:35:23', NULL, NULL),
	(2, 'configuracao', 'Alteração nas configurações do Pix', 1, '2025-05-30 21:41:28', NULL, NULL),
	(3, 'configuracao', 'Alteração nas configurações do Pix', 1, '2025-05-30 21:49:08', NULL, NULL),
	(4, 'configuracao', 'Upload de certificado P12', 1, '2025-05-30 21:50:27', NULL, '::1'),
	(5, 'configuracao', 'Upload de certificado P12', 1, '2025-05-30 22:17:23', NULL, '::1'),
	(6, 'configuracao', 'Upload de certificado P12', 1, '2025-05-30 22:17:51', NULL, '::1'),
	(7, 'configuracao', 'Upload de certificado P12', 1, '2025-05-31 01:36:50', NULL, '::1'),
	(8, 'configuracao', 'Alteração nas configurações do Pix', 1, '2025-05-31 20:15:13', NULL, '::1'),
	(9, 'configuracao', 'Upload de certificado P12', 1, '2025-05-31 20:17:33', NULL, '::1'),
	(10, 'configuracao', 'Upload de certificado P12', 1, '2025-05-31 20:18:00', NULL, '::1'),
	(11, 'configuracao', 'Upload de certificado P12', 1, '2025-05-31 20:19:23', NULL, '::1'),
	(12, 'jogador', 'Editou dados do jogador Central Do Script', 1, '2025-06-13 17:51:17', '{"jogador_id":5,"dados_alterados":{"nome":"Central Do Script","email":"central_jogador@bolao.com","telefone":"(21) 9673-80813","cpf":"46143888000","status":"ativo"}}', '::1'),
	(13, 'configuracao', 'Alteração na chave da API Football', 1, '2025-07-04 16:31:29', NULL, '::1'),
	(14, 'teste_api', 'Teste da API Football realizado', 1, '2025-07-16 20:21:32', '{"resultados":[{"nome":"Status da API","sucesso":true,"mensagem":"27 de 100 requisi\\u00e7\\u00f5es hoje"},{"nome":"Lista de Pa\\u00edses","sucesso":true,"mensagem":"Retornou 171 pa\\u00edses"},{"nome":"Ligas do Brasil","sucesso":true,"mensagem":"Retornou 103 ligas"}]}', '::1'),
	(15, 'configuracao', 'Alteração na chave da API Football', 1, '2025-07-16 20:22:09', NULL, '::1'),
	(16, 'teste_api', 'Teste da API Football realizado', 1, '2025-07-16 20:22:18', '{"resultados":[{"nome":"Status da API","sucesso":true,"mensagem":"27 de 100 requisi\\u00e7\\u00f5es hoje"},{"nome":"Lista de Pa\\u00edses","sucesso":true,"mensagem":"Retornou 171 pa\\u00edses"},{"nome":"Ligas do Brasil","sucesso":true,"mensagem":"Retornou 103 ligas"}]}', '::1'),
	(17, 'login_sucesso', 'Login realizado com sucesso', 1, '2025-07-23 19:32:00', NULL, '::1'),
	(18, 'login_sucesso', 'Login realizado com sucesso', 4, '2025-07-23 19:32:57', NULL, '::1'),
	(19, 'login_sucesso', 'Login realizado com sucesso', 1, '2025-07-24 11:53:43', NULL, '::1'),
	(20, 'login_sucesso', 'Login realizado com sucesso', 1, '2025-07-24 19:28:18', NULL, '::1'),
	(21, 'jogador', 'Editou dados do jogador Milton Cunha', 1, '2025-07-24 19:51:17', '{"jogador_id":4,"dados_alterados":{"nome":"Milton Cunha","email":"miltoncunha@bolao.com","telefone":"(77) 98765-4321","cpf":"12221212121","status":"ativo"}}', '::1'),
	(22, 'login_sucesso', 'Login realizado com sucesso', 4, '2025-07-24 19:52:56', NULL, '::1');

-- Copiando estrutura para tabela bolao_football.notificacoes
CREATE TABLE IF NOT EXISTS `notificacoes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `jogador_id` int(11) NOT NULL,
  `tipo` enum('saque_aprovado','saque_rejeitado','deposito_confirmado','sistema') NOT NULL,
  `titulo` varchar(255) NOT NULL,
  `mensagem` text NOT NULL,
  `lida` tinyint(1) DEFAULT 0,
  `data_criacao` datetime DEFAULT current_timestamp(),
  `data_leitura` datetime DEFAULT NULL,
  `dados_adicionais` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`dados_adicionais`)),
  PRIMARY KEY (`id`),
  KEY `idx_jogador_lida` (`jogador_id`,`lida`),
  KEY `idx_data` (`data_criacao`),
  CONSTRAINT `notificacoes_ibfk_1` FOREIGN KEY (`jogador_id`) REFERENCES `jogador` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Copiando dados para a tabela bolao_football.notificacoes: ~0 rows (aproximadamente)

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
  KEY `pagamentos_ibfk_2` (`bolao_id`),
  CONSTRAINT `pagamentos_ibfk_1` FOREIGN KEY (`jogador_id`) REFERENCES `jogador` (`id`) ON DELETE CASCADE,
  CONSTRAINT `pagamentos_ibfk_2` FOREIGN KEY (`bolao_id`) REFERENCES `dados_boloes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Copiando dados para a tabela bolao_football.pagamentos: ~0 rows (aproximadamente)

-- Copiando estrutura para tabela bolao_football.palpites
CREATE TABLE IF NOT EXISTS `palpites` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `jogador_id` int(11) NOT NULL,
  `bolao_id` int(11) NOT NULL,
  `palpites` longtext NOT NULL,
  `data_palpite` datetime DEFAULT current_timestamp(),
  `status` enum('pendente','pago','cancelado') NOT NULL DEFAULT 'pendente',
  `afiliado_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  KEY `bolao_id` (`bolao_id`) USING BTREE,
  KEY `palpites_ibfk_3` (`afiliado_id`) USING BTREE,
  KEY `palpites_ibfk_1` (`jogador_id`),
  CONSTRAINT `palpites_ibfk_1` FOREIGN KEY (`jogador_id`) REFERENCES `jogador` (`id`) ON DELETE CASCADE,
  CONSTRAINT `palpites_ibfk_2` FOREIGN KEY (`bolao_id`) REFERENCES `dados_boloes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `palpites_ibfk_3` FOREIGN KEY (`afiliado_id`) REFERENCES `afiliados` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `check_json_palpites` CHECK (json_valid(`palpites`))
) ENGINE=InnoDB AUTO_INCREMENT=68 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Copiando dados para a tabela bolao_football.palpites: ~4 rows (aproximadamente)
REPLACE INTO `palpites` (`id`, `jogador_id`, `bolao_id`, `palpites`, `data_palpite`, `status`, `afiliado_id`) VALUES
	(64, 1, 15, '{"jogos":{"1351199":"2","1351194":"0","1351198":"1","1351193":"1","1351201":"2","1351195":"1","1351196":"2","1351200":"1","1351197":"1","1351202":"2","1351204":"0"}}', '2025-07-23 00:10:55', 'pendente', NULL),
	(65, 1, 15, '{"jogos":{"1351199":"2","1351194":"0","1351198":"1","1351193":"1","1351201":"2","1351195":"1","1351196":"2","1351200":"1","1351197":"1","1351202":"2","1351204":"0"}}', '2025-07-23 00:20:28', 'pendente', NULL),
	(66, 1, 15, '{"jogos":{"resultado_1351199":"2","resultado_1351194":"0","resultado_1351198":"1","resultado_1351193":"1","resultado_1351201":"2","resultado_1351195":"1","resultado_1351196":"2","resultado_1351200":"1","resultado_1351197":"1","resultado_1351202":"2","resultado_1351204":"0"}}', '2025-07-23 00:22:15', 'pendente', NULL),
	(67, 1, 15, '{"jogos":{"resultado_1351199":"2","resultado_1351194":"2","resultado_1351198":"2","resultado_1351193":"1","resultado_1351201":"1","resultado_1351195":"2","resultado_1351196":"1","resultado_1351200":"2","resultado_1351197":"0","resultado_1351202":"0","resultado_1351204":"0"}}', '2025-07-23 00:26:57', 'pendente', NULL);

-- Copiando estrutura para tabela bolao_football.transacoes
CREATE TABLE IF NOT EXISTS `transacoes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `conta_id` int(11) NOT NULL,
  `tipo` enum('deposito','saque','aposta','premio','estorno','bonus') NOT NULL,
  `valor` decimal(10,2) NOT NULL,
  `saldo_anterior` decimal(10,2) NOT NULL,
  `saldo_posterior` decimal(10,2) NOT NULL,
  `status` enum('pendente','aprovado','rejeitado','cancelado','processando') DEFAULT 'pendente',
  `descricao` text DEFAULT NULL,
  `referencia` varchar(100) DEFAULT NULL,
  `data_solicitacao` datetime DEFAULT current_timestamp(),
  `data_processamento` datetime DEFAULT NULL,
  `processado_por` int(11) DEFAULT NULL,
  `txid` varchar(100) DEFAULT NULL,
  `palpite_id` int(11) DEFAULT NULL,
  `metodo_pagamento` enum('pix') DEFAULT 'pix',
  `afeta_saldo` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_txid` (`txid`),
  KEY `processado_por` (`processado_por`),
  KEY `fk_transacao_palpite` (`palpite_id`),
  KEY `idx_conta_status` (`conta_id`,`status`),
  KEY `idx_data_conta` (`data_solicitacao`,`conta_id`),
  KEY `idx_tipo_status` (`tipo`,`status`),
  CONSTRAINT `fk_transacao_palpite` FOREIGN KEY (`palpite_id`) REFERENCES `palpites` (`id`),
  CONSTRAINT `transacoes_ibfk_1` FOREIGN KEY (`conta_id`) REFERENCES `contas` (`id`),
  CONSTRAINT `transacoes_ibfk_2` FOREIGN KEY (`processado_por`) REFERENCES `jogador` (`id`),
  CONSTRAINT `chk_afeta_saldo_aprovado` CHECK (`status` <> 'aprovado' or `afeta_saldo` = 1)
) ENGINE=InnoDB AUTO_INCREMENT=133 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Copiando dados para a tabela bolao_football.transacoes: ~19 rows (aproximadamente)
REPLACE INTO `transacoes` (`id`, `conta_id`, `tipo`, `valor`, `saldo_anterior`, `saldo_posterior`, `status`, `descricao`, `referencia`, `data_solicitacao`, `data_processamento`, `processado_por`, `txid`, `palpite_id`, `metodo_pagamento`, `afeta_saldo`) VALUES
	(105, 1, 'deposito', 10.00, 0.00, 10.00, 'pendente', NULL, 'DEP17532418149986', '2025-07-23 00:36:54', NULL, NULL, NULL, NULL, 'pix', 0),
	(106, 1, 'deposito', 1.00, 0.00, 0.00, 'pendente', 'Depósito via PIX', 'DEP17532961169413', '2025-07-23 15:41:56', NULL, NULL, NULL, NULL, 'pix', 1),
	(107, 1, 'deposito', 1.00, 0.00, 0.00, 'pendente', 'Depósito via PIX', 'DEP17532961295213', '2025-07-23 15:42:10', NULL, NULL, NULL, NULL, 'pix', 1),
	(116, 2, 'deposito', 2.00, 0.00, 0.00, 'pendente', 'Depósito via PIX', 'DEP17533014136593', '2025-07-23 17:10:13', NULL, NULL, NULL, NULL, 'pix', 0),
	(117, 2, 'deposito', 1.00, 0.00, 0.00, 'pendente', 'Depósito via PIX', 'DEP17533019553889', '2025-07-23 17:19:16', NULL, NULL, NULL, NULL, 'pix', 0),
	(118, 2, 'deposito', 1.50, 0.00, 0.00, 'pendente', 'Depósito via PIX', 'DEP17533022789218', '2025-07-23 17:24:38', NULL, NULL, NULL, NULL, 'pix', 0),
	(119, 2, 'deposito', 1.23, 0.00, 0.00, 'pendente', 'Depósito via PIX', 'DEP17533025427922', '2025-07-23 17:29:02', NULL, NULL, NULL, NULL, 'pix', 0),
	(120, 2, 'deposito', 1.21, 0.00, 0.00, 'pendente', 'Depósito via PIX', 'DEP17533027793983', '2025-07-23 17:32:59', NULL, NULL, NULL, NULL, 'pix', 0),
	(121, 2, 'deposito', 1.43, 0.00, 0.00, 'pendente', 'Depósito via PIX', 'DEP17533032011709', '2025-07-23 17:40:01', NULL, NULL, NULL, NULL, 'pix', 0),
	(122, 2, 'deposito', 1.00, 0.00, 0.00, 'pendente', NULL, 'DEP17533905964280935', '2025-07-24 17:56:39', '2025-07-24 17:56:39', NULL, 'DEP00417533905965c952bc4b3cd78e9', NULL, 'pix', 0),
	(123, 2, 'deposito', 1.00, 0.00, 1.00, 'pendente', NULL, 'DEP17533911516907728', '2025-07-24 18:05:54', '2025-07-24 18:05:54', NULL, 'DEP0041753391151ba12269d708d7cce', NULL, 'pix', 1),
	(124, 2, 'deposito', 1.00, 0.00, 1.00, 'pendente', NULL, 'DEP17533911572944087', '2025-07-24 18:05:59', '2025-07-24 18:05:59', NULL, 'DEP0041753391157a94c82cc541d0fb9', NULL, 'pix', 1),
	(125, 2, 'deposito', 1.00, 0.00, 1.00, 'pendente', NULL, 'DEP17533911639934790', '2025-07-24 18:06:06', '2025-07-24 18:06:06', NULL, 'DEP0041753391163cd4371986a6b6409', NULL, 'pix', 1),
	(126, 2, 'deposito', 1.00, 0.00, 1.00, 'pendente', NULL, 'DEP17533911694807088', '2025-07-24 18:06:11', '2025-07-24 18:06:11', NULL, 'DEP0041753391169e49c318987b69f12', NULL, 'pix', 1),
	(127, 2, 'deposito', 1.00, 0.00, 1.00, 'pendente', NULL, 'DEP17533911741645373', '2025-07-24 18:06:17', '2025-07-24 18:06:17', NULL, 'DEP004175339117445593570e584f5c4', NULL, 'pix', 1),
	(128, 2, 'deposito', 1.00, 0.00, 1.00, 'pendente', NULL, 'DEP17533911807724121', '2025-07-24 18:06:23', '2025-07-24 18:06:23', NULL, 'DEP0041753391180407bd3f3c001af0f', NULL, 'pix', 1),
	(129, 2, 'deposito', 1.00, 0.00, 1.00, 'pendente', NULL, 'DEP17533911855188412', '2025-07-24 18:06:28', '2025-07-24 18:06:28', NULL, 'DEP0041753391185b81fb6011cca0d43', NULL, 'pix', 1),
	(130, 2, 'deposito', 1.00, 0.00, 1.00, 'pendente', NULL, 'DEP17533911919654148', '2025-07-24 18:06:34', '2025-07-24 18:06:34', NULL, 'DEP0041753391191decfb83b3c18aac2', NULL, 'pix', 1),
	(131, 2, 'deposito', 1.00, 0.00, 1.00, 'pendente', NULL, 'DEP17533911972397484', '2025-07-24 18:06:40', '2025-07-24 18:06:40', NULL, 'DEP0041753391197a8e5ec1a4b22365a', NULL, 'pix', 1),
	(132, 2, 'deposito', 1.00, 0.00, 1.00, 'pendente', NULL, 'DEP17533915658726550', '2025-07-24 18:12:48', '2025-07-24 18:12:48', NULL, 'DEP00417533915653a0acb21561237c2', NULL, 'pix', 1);

-- Copiando estrutura para trigger bolao_football.after_transaction_approved
SET @OLDTMP_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION';
DELIMITER //
CREATE TRIGGER after_transaction_approved
AFTER UPDATE ON transacoes
FOR EACH ROW
BEGIN
    IF NEW.status = 'aprovado' AND OLD.status != 'aprovado' AND NEW.afeta_saldo = 1 THEN
        IF NEW.tipo IN ('deposito', 'premio', 'bonus') THEN
            UPDATE contas 
            SET saldo = saldo + NEW.valor 
            WHERE id = NEW.conta_id;
        ELSEIF NEW.tipo IN ('saque', 'aposta') THEN
            UPDATE contas 
            SET saldo = saldo - NEW.valor 
            WHERE id = NEW.conta_id;
        END IF;
    END IF;
END//
DELIMITER ;
SET SQL_MODE=@OLDTMP_SQL_MODE;

-- Copiando estrutura para trigger bolao_football.before_transaction_approved
SET @OLDTMP_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION';
DELIMITER //
CREATE TRIGGER before_transaction_approved
BEFORE UPDATE ON transacoes
FOR EACH ROW
BEGIN
    DECLARE saldo_atual DECIMAL(10,2);
    
    IF NEW.status = 'aprovado' AND OLD.status != 'aprovado' 
       AND NEW.afeta_saldo = 1 
       AND NEW.tipo IN ('saque', 'aposta') THEN
        
        SELECT saldo INTO saldo_atual
        FROM contas
        WHERE id = NEW.conta_id;
        
        IF saldo_atual < NEW.valor THEN
            SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Saldo insuficiente para esta operação';
        END IF;
    END IF;
END//
DELIMITER ;
SET SQL_MODE=@OLDTMP_SQL_MODE;

/*!40103 SET TIME_ZONE=IFNULL(@OLD_TIME_ZONE, 'system') */;
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;
