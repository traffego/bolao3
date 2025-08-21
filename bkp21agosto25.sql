-- --------------------------------------------------------
-- Servidor:                     187.33.241.40
-- Versão do servidor:           10.11.13-MariaDB-cll-lve - MariaDB Server
-- OS do Servidor:               Linux
-- HeidiSQL Versão:              12.11.0.7080
-- --------------------------------------------------------

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET NAMES utf8 */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

-- Copiando estrutura para tabela platafo5_bolao3.administrador
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

-- Copiando dados para a tabela platafo5_bolao3.administrador: ~0 rows (aproximadamente)
REPLACE INTO `administrador` (`id`, `nome`, `email`, `senha`, `status`, `data_cadastro`, `ultimo_acesso`, `ultimo_login`) VALUES
	(1, 'Administrador', 'admin@bolao.com', '$2y$10$BOxWWRWkbsrwrV.GSN6/SOSmjSj/8ad98mYask3Bazjup6DnIVMiq', 'ativo', '2025-05-20 18:08:25', NULL, '2025-08-20 20:36:42');

-- Copiando estrutura para tabela platafo5_bolao3.afiliados
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

-- Copiando dados para a tabela platafo5_bolao3.afiliados: ~0 rows (aproximadamente)

-- Copiando estrutura para tabela platafo5_bolao3.afiliados_comissoes
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

-- Copiando dados para a tabela platafo5_bolao3.afiliados_comissoes: ~0 rows (aproximadamente)

-- Copiando estrutura para tabela platafo5_bolao3.afiliados_indicacoes
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

-- Copiando dados para a tabela platafo5_bolao3.afiliados_indicacoes: ~0 rows (aproximadamente)

-- Copiando estrutura para tabela platafo5_bolao3.configuracoes
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
) ENGINE=InnoDB AUTO_INCREMENT=46 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Copiando dados para a tabela platafo5_bolao3.configuracoes: ~10 rows (aproximadamente)
REPLACE INTO `configuracoes` (`id`, `nome_configuracao`, `valor`, `categoria`, `descricao`, `data_criacao`, `data_atualizacao`) VALUES
	(1, 'site_name', '"Bolão de Futebol"', 'geral', 'Nome do site', '2025-05-30 19:38:41', '2025-07-22 15:44:16');
REPLACE INTO `configuracoes` (`id`, `nome_configuracao`, `valor`, `categoria`, `descricao`, `data_criacao`, `data_atualizacao`) VALUES
	(2, 'site_description', '"O melhor sistema de bolões de futebol!"', 'geral', 'Descrição do site', '2025-05-30 19:38:41', '2025-07-22 15:44:16');
REPLACE INTO `configuracoes` (`id`, `nome_configuracao`, `valor`, `categoria`, `descricao`, `data_criacao`, `data_atualizacao`) VALUES
	(3, 'moeda', '"BRL"', 'geral', 'Moeda padrão do sistema', '2025-05-30 19:38:41', '2025-07-22 15:44:16');
REPLACE INTO `configuracoes` (`id`, `nome_configuracao`, `valor`, `categoria`, `descricao`, `data_criacao`, `data_atualizacao`) VALUES
	(13, 'api_football', '{"api_key":"ad0b29bd4984b69fd16d9680c6d017c1","base_url":"https:\\/\\/v3.football.api-sports.io","last_request":"2025-08-20 21:25:03"}', 'api', 'Configurações da API Football', '2025-05-30 19:38:41', '2025-08-20 21:25:03');
REPLACE INTO `configuracoes` (`id`, `nome_configuracao`, `valor`, `categoria`, `descricao`, `data_criacao`, `data_atualizacao`) VALUES
	(21, 'efi_pix_config', '{"ambiente":"producao","client_id":"Client_Id_3e9ce7b7f569d0a4aa8f9ec8b172c3ed7dd9d948","client_secret":"Client_Secret_31e8f33edba74286002f4c91a2df6896f2764fd1","pix_key":"60409292-a359-4992-9f5f-5886bace6fe6","webhook_url":"https:\\/\\/bolao.traffego.agency\\/webhook_pix.php"}', 'pagamentos', 'Configurações da API Pix da Efí', '2025-05-30 19:38:41', '2025-08-05 14:34:52');
REPLACE INTO `configuracoes` (`id`, `nome_configuracao`, `valor`, `categoria`, `descricao`, `data_criacao`, `data_atualizacao`) VALUES
	(24, 'tipos_logs', '["configuracao","pagamento","palpite","bolao","usuario","sistema"]', 'sistema', 'Tipos de logs permitidos no sistema', '2025-05-30 21:34:58', '2025-05-30 18:34:58');
REPLACE INTO `configuracoes` (`id`, `nome_configuracao`, `valor`, `categoria`, `descricao`, `data_criacao`, `data_atualizacao`) VALUES
	(29, 'api_football_key', 'ad0b29bd4984b69fd16d9680c6d017c1', 'api', 'Chave da API Football', '2025-07-04 16:31:29', '2025-07-16 17:22:09');
REPLACE INTO `configuracoes` (`id`, `nome_configuracao`, `valor`, `categoria`, `descricao`, `data_criacao`, `data_atualizacao`) VALUES
	(31, 'deposito_minimo', '1.00', 'pagamento', 'Valor mínimo para depósito', '2025-07-04 19:26:04', '2025-07-23 15:38:10');
REPLACE INTO `configuracoes` (`id`, `nome_configuracao`, `valor`, `categoria`, `descricao`, `data_criacao`, `data_atualizacao`) VALUES
	(44, 'deposito_maximo', '5000.00', 'pagamento', 'Valor máximo para depósito', '2025-07-24 20:52:54', '2025-07-24 17:52:54');
REPLACE INTO `configuracoes` (`id`, `nome_configuracao`, `valor`, `categoria`, `descricao`, `data_criacao`, `data_atualizacao`) VALUES
	(45, 'api_keys_migration', '{"migrated_at":"2025-08-05 13:49:38","version":"1.0","migration_script":"migrate_api_keys.php"}', 'system', 'API Keys migration tracking', '2025-08-05 16:49:37', '2025-08-05 13:49:37');

-- Copiando estrutura para tabela platafo5_bolao3.contas
CREATE TABLE IF NOT EXISTS `contas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `jogador_id` int(11) NOT NULL,
  `status` enum('ativo','bloqueado','suspenso') DEFAULT 'ativo',
  `data_criacao` datetime DEFAULT current_timestamp(),
  `data_atualizacao` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `jogador_id` (`jogador_id`),
  CONSTRAINT `contas_ibfk_1` FOREIGN KEY (`jogador_id`) REFERENCES `jogador` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=61 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Copiando dados para a tabela platafo5_bolao3.contas: ~8 rows (aproximadamente)
REPLACE INTO `contas` (`id`, `jogador_id`, `status`, `data_criacao`, `data_atualizacao`) VALUES
	(1, 1, 'ativo', '2025-07-23 00:36:24', '2025-07-23 00:36:24');
REPLACE INTO `contas` (`id`, `jogador_id`, `status`, `data_criacao`, `data_atualizacao`) VALUES
	(52, 7, 'ativo', '2025-08-20 20:21:48', '2025-08-20 20:21:48');
REPLACE INTO `contas` (`id`, `jogador_id`, `status`, `data_criacao`, `data_atualizacao`) VALUES
	(55, 10, 'ativo', '2025-08-21 02:33:20', '2025-08-21 02:33:20');
REPLACE INTO `contas` (`id`, `jogador_id`, `status`, `data_criacao`, `data_atualizacao`) VALUES
	(56, 11, 'ativo', '2025-08-21 02:35:32', '2025-08-21 02:35:32');
REPLACE INTO `contas` (`id`, `jogador_id`, `status`, `data_criacao`, `data_atualizacao`) VALUES
	(57, 12, 'ativo', '2025-08-21 02:38:02', '2025-08-21 02:38:02');
REPLACE INTO `contas` (`id`, `jogador_id`, `status`, `data_criacao`, `data_atualizacao`) VALUES
	(58, 13, 'ativo', '2025-08-21 02:44:27', '2025-08-21 02:44:27');
REPLACE INTO `contas` (`id`, `jogador_id`, `status`, `data_criacao`, `data_atualizacao`) VALUES
	(59, 14, 'ativo', '2025-08-21 02:48:52', '2025-08-21 02:48:52');
REPLACE INTO `contas` (`id`, `jogador_id`, `status`, `data_criacao`, `data_atualizacao`) VALUES
	(60, 15, 'ativo', '2025-08-21 02:50:31', '2025-08-21 02:50:31');

-- Copiando estrutura para tabela platafo5_bolao3.dados_boloes
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
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Copiando dados para a tabela platafo5_bolao3.dados_boloes: ~3 rows (aproximadamente)
REPLACE INTO `dados_boloes` (`id`, `nome`, `slug`, `descricao`, `data_inicio`, `data_fim`, `data_limite_palpitar`, `valor_participacao`, `premio_rodada`, `premio_total`, `status`, `publico`, `max_participantes`, `quantidade_jogos`, `imagem_bolao_url`, `admin_id`, `jogos`, `campeonatos`, `data_criacao`) VALUES
	(20, 'Bolão 23/08/2025 a 25/08/2025', 'bolão-23-08-2025-a-25-08-2025', '', '2025-08-23', '2025-08-25', NULL, 0.50, 5000.00, 500.00, 1, 1, NULL, 11, 'uploads/boloes/bolao_1755034207_689bb25fa1099.jpg', 1, '[{"id":1351247,"campeonato":"Serie A","campeonato_id":71,"time_casa":"RB Bragantino","time_visitante":"Fluminense","nome_time_casa":"RB Bragantino","nome_time_visitante":"Fluminense","logo_time_casa":"https:\\/\\/media.api-sports.io\\/football\\/teams\\/794.png","logo_time_visitante":"https:\\/\\/media.api-sports.io\\/football\\/teams\\/124.png","data":"2025-08-23 16:00:00","data_formatada":"23\\/08\\/2025 16:00","status":"NS","resultado_casa":null,"resultado_visitante":null,"local":"Est\\u00e1dio Cicero de Souza Marques"},{"id":1351248,"campeonato":"Serie A","campeonato_id":71,"time_casa":"Cruzeiro","time_visitante":"Internacional","nome_time_casa":"Cruzeiro","nome_time_visitante":"Internacional","logo_time_casa":"https:\\/\\/media.api-sports.io\\/football\\/teams\\/135.png","logo_time_visitante":"https:\\/\\/media.api-sports.io\\/football\\/teams\\/119.png","data":"2025-08-23 18:30:00","data_formatada":"23\\/08\\/2025 18:30","status":"NS","resultado_casa":null,"resultado_visitante":null,"local":"Est\\u00e1dio Governador Magalh\\u00e3es Pinto"},{"id":1351249,"campeonato":"Serie A","campeonato_id":71,"time_casa":"Gremio","time_visitante":"Ceara","nome_time_casa":"Gremio","nome_time_visitante":"Ceara","logo_time_casa":"https:\\/\\/media.api-sports.io\\/football\\/teams\\/130.png","logo_time_visitante":"https:\\/\\/media.api-sports.io\\/football\\/teams\\/129.png","data":"2025-08-23 21:00:00","data_formatada":"23\\/08\\/2025 21:00","status":"NS","resultado_casa":null,"resultado_visitante":null,"local":"Arena do Gr\\u00eamio"},{"id":1351250,"campeonato":"Serie A","campeonato_id":71,"time_casa":"Bahia","time_visitante":"Santos","nome_time_casa":"Bahia","nome_time_visitante":"Santos","logo_time_casa":"https:\\/\\/media.api-sports.io\\/football\\/teams\\/118.png","logo_time_visitante":"https:\\/\\/media.api-sports.io\\/football\\/teams\\/128.png","data":"2025-08-24 16:00:00","data_formatada":"24\\/08\\/2025 16:00","status":"NS","resultado_casa":null,"resultado_visitante":null,"local":"Casa de Apostas Arena Fonte Nova"},{"id":1351244,"campeonato":"Serie A","campeonato_id":71,"time_casa":"Vasco DA Gama","time_visitante":"Corinthians","nome_time_casa":"Vasco DA Gama","nome_time_visitante":"Corinthians","logo_time_casa":"https:\\/\\/media.api-sports.io\\/football\\/teams\\/133.png","logo_time_visitante":"https:\\/\\/media.api-sports.io\\/football\\/teams\\/131.png","data":"2025-08-24 16:00:00","data_formatada":"24\\/08\\/2025 16:00","status":"NS","resultado_casa":null,"resultado_visitante":null,"local":"Est\\u00e1dio S\\u00e3o Janu\\u00e1rio"},{"id":1351252,"campeonato":"Serie A","campeonato_id":71,"time_casa":"Juventude","time_visitante":"Botafogo","nome_time_casa":"Juventude","nome_time_visitante":"Botafogo","logo_time_casa":"https:\\/\\/media.api-sports.io\\/football\\/teams\\/152.png","logo_time_visitante":"https:\\/\\/media.api-sports.io\\/football\\/teams\\/120.png","data":"2025-08-24 18:30:00","data_formatada":"24\\/08\\/2025 18:30","status":"NS","resultado_casa":null,"resultado_visitante":null,"local":"Est\\u00e1dio Alfredo Jaconi"},{"id":1351251,"campeonato":"Serie A","campeonato_id":71,"time_casa":"Fortaleza EC","time_visitante":"Mirassol","nome_time_casa":"Fortaleza EC","nome_time_visitante":"Mirassol","logo_time_casa":"https:\\/\\/media.api-sports.io\\/football\\/teams\\/154.png","logo_time_visitante":"https:\\/\\/media.api-sports.io\\/football\\/teams\\/7848.png","data":"2025-08-24 18:30:00","data_formatada":"24\\/08\\/2025 18:30","status":"NS","resultado_casa":null,"resultado_visitante":null,"local":"Est\\u00e1dio Governador Pl\\u00e1cido Aderaldo Castelo"},{"id":1351246,"campeonato":"Serie A","campeonato_id":71,"time_casa":"Sao Paulo","time_visitante":"Atletico-MG","nome_time_casa":"Sao Paulo","nome_time_visitante":"Atletico-MG","logo_time_casa":"https:\\/\\/media.api-sports.io\\/football\\/teams\\/126.png","logo_time_visitante":"https:\\/\\/media.api-sports.io\\/football\\/teams\\/1062.png","data":"2025-08-24 20:30:00","data_formatada":"24\\/08\\/2025 20:30","status":"NS","resultado_casa":null,"resultado_visitante":null,"local":"MorumBIS"},{"id":1351245,"campeonato":"Serie A","campeonato_id":71,"time_casa":"Palmeiras","time_visitante":"Sport Recife","nome_time_casa":"Palmeiras","nome_time_visitante":"Sport Recife","logo_time_casa":"https:\\/\\/media.api-sports.io\\/football\\/teams\\/121.png","logo_time_visitante":"https:\\/\\/media.api-sports.io\\/football\\/teams\\/123.png","data":"2025-08-25 19:00:00","data_formatada":"25\\/08\\/2025 19:00","status":"NS","resultado_casa":null,"resultado_visitante":null,"local":"Allianz Parque"},{"id":1353485,"campeonato":"Serie B","campeonato_id":72,"time_casa":"Criciuma","time_visitante":"Novorizontino","nome_time_casa":"Criciuma","nome_time_visitante":"Novorizontino","logo_time_casa":"https:\\/\\/media.api-sports.io\\/football\\/teams\\/140.png","logo_time_visitante":"https:\\/\\/media.api-sports.io\\/football\\/teams\\/7834.png","data":"2025-08-22 21:35:00","data_formatada":"22\\/08\\/2025 21:35","status":"NS","resultado_casa":null,"resultado_visitante":null,"local":"Est\\u00e1dio Heriberto H\\u00fclse"},{"id":1353480,"campeonato":"Serie B","campeonato_id":72,"time_casa":"Coritiba","time_visitante":"remo","nome_time_casa":"Coritiba","nome_time_visitante":"remo","logo_time_casa":"https:\\/\\/media.api-sports.io\\/football\\/teams\\/147.png","logo_time_visitante":"https:\\/\\/media.api-sports.io\\/football\\/teams\\/1198.png","data":"2025-08-23 16:00:00","data_formatada":"23\\/08\\/2025 16:00","status":"NS","resultado_casa":null,"resultado_visitante":null,"local":"Est\\u00e1dio Major Ant\\u00f4nio Couto Pereira"}]', '[{"id":"71","nome":"Brasileir\\u00e3o S\\u00e9rie A"},{"id":"72","nome":"Brasileir\\u00e3o S\\u00e9rie B"}]', '2025-08-12 18:30:43');
REPLACE INTO `dados_boloes` (`id`, `nome`, `slug`, `descricao`, `data_inicio`, `data_fim`, `data_limite_palpitar`, `valor_participacao`, `premio_rodada`, `premio_total`, `status`, `publico`, `max_participantes`, `quantidade_jogos`, `imagem_bolao_url`, `admin_id`, `jogos`, `campeonatos`, `data_criacao`) VALUES
	(21, 'Bolão 20/08/2025 a 26/08/2025', 'bolão-20-08-2025-a-26-08-2025', '', '2025-08-20', '2025-08-26', NULL, 0.05, 50000.00, 1000.00, 1, 1, NULL, 11, 'uploads/boloes/bolao_1755631837_68a4d0dd45400.jpg', 1, '[{"id":1351182,"campeonato":"Serie A","campeonato_id":71,"time_casa":"Juventude","time_visitante":"Vasco DA Gama","nome_time_casa":"Juventude","nome_time_visitante":"Vasco DA Gama","logo_time_casa":"https:\\/\\/media.api-sports.io\\/football\\/teams\\/152.png","logo_time_visitante":"https:\\/\\/media.api-sports.io\\/football\\/teams\\/133.png","data":"2025-08-20 19:00:00","data_formatada":"20\\/08\\/2025 19:00","status":"FT","resultado_casa":2,"resultado_visitante":0,"local":"Est\\u00e1dio Alfredo Jaconi"},{"id":1351243,"campeonato":"Serie A","campeonato_id":71,"time_casa":"Flamengo","time_visitante":"Vitoria","nome_time_casa":"Flamengo","nome_time_visitante":"Vitoria","logo_time_casa":"https:\\/\\/media.api-sports.io\\/football\\/teams\\/127.png","logo_time_visitante":"https:\\/\\/media.api-sports.io\\/football\\/teams\\/136.png","data":"2025-08-25 21:00:00","data_formatada":"25\\/08\\/2025 21:00","status":"NS","resultado_casa":null,"resultado_visitante":null,"local":"Estadio Jornalista M\\u00e1rio Filho"},{"id":1353487,"campeonato":"Serie B","campeonato_id":72,"time_casa":"Athletic Club","time_visitante":"Chapecoense-sc","nome_time_casa":"Athletic Club","nome_time_visitante":"Chapecoense-sc","logo_time_casa":"https:\\/\\/media.api-sports.io\\/football\\/teams\\/13975.png","logo_time_visitante":"https:\\/\\/media.api-sports.io\\/football\\/teams\\/132.png","data":"2025-08-22 19:00:00","data_formatada":"22\\/08\\/2025 19:00","status":"NS","resultado_casa":null,"resultado_visitante":null,"local":"Est\\u00e1dio Joaquim Portugal"},{"id":1353481,"campeonato":"Serie B","campeonato_id":72,"time_casa":"Goias","time_visitante":"America Mineiro","nome_time_casa":"Goias","nome_time_visitante":"America Mineiro","logo_time_casa":"https:\\/\\/media.api-sports.io\\/football\\/teams\\/151.png","logo_time_visitante":"https:\\/\\/media.api-sports.io\\/football\\/teams\\/125.png","data":"2025-08-23 18:00:00","data_formatada":"23\\/08\\/2025 18:00","status":"NS","resultado_casa":null,"resultado_visitante":null,"local":"Estadio da Serrinha"},{"id":1353486,"campeonato":"Serie B","campeonato_id":72,"time_casa":"CRB","time_visitante":"Atletico Paranaense","nome_time_casa":"CRB","nome_time_visitante":"Atletico Paranaense","logo_time_casa":"https:\\/\\/media.api-sports.io\\/football\\/teams\\/146.png","logo_time_visitante":"https:\\/\\/media.api-sports.io\\/football\\/teams\\/134.png","data":"2025-08-23 20:30:00","data_formatada":"23\\/08\\/2025 20:30","status":"NS","resultado_casa":null,"resultado_visitante":null,"local":"Est\\u00e1dio Rei Pel\\u00e9"},{"id":1353483,"campeonato":"Serie B","campeonato_id":72,"time_casa":"Paysandu","time_visitante":"Operario-PR","nome_time_casa":"Paysandu","nome_time_visitante":"Operario-PR","logo_time_casa":"https:\\/\\/media.api-sports.io\\/football\\/teams\\/149.png","logo_time_visitante":"https:\\/\\/media.api-sports.io\\/football\\/teams\\/1223.png","data":"2025-08-24 16:00:00","data_formatada":"24\\/08\\/2025 16:00","status":"NS","resultado_casa":null,"resultado_visitante":null,"local":"Est\\u00e1dio Le\\u00f4nidas Sodr\\u00e9 de Castro"},{"id":1353488,"campeonato":"Serie B","campeonato_id":72,"time_casa":"Ferrovi\\u00e1ria","time_visitante":"Volta Redonda","nome_time_casa":"Ferrovi\\u00e1ria","nome_time_visitante":"Volta Redonda","logo_time_casa":"https:\\/\\/media.api-sports.io\\/football\\/teams\\/7826.png","logo_time_visitante":"https:\\/\\/media.api-sports.io\\/football\\/teams\\/7814.png","data":"2025-08-24 18:30:00","data_formatada":"24\\/08\\/2025 18:30","status":"NS","resultado_casa":null,"resultado_visitante":null,"local":"Est\\u00e1dio Doutor Adhemar de Barros"},{"id":1353482,"campeonato":"Serie B","campeonato_id":72,"time_casa":"Cuiaba","time_visitante":"Atletico Goianiense","nome_time_casa":"Cuiaba","nome_time_visitante":"Atletico Goianiense","logo_time_casa":"https:\\/\\/media.api-sports.io\\/football\\/teams\\/1193.png","logo_time_visitante":"https:\\/\\/media.api-sports.io\\/football\\/teams\\/144.png","data":"2025-08-24 20:30:00","data_formatada":"24\\/08\\/2025 20:30","status":"NS","resultado_casa":null,"resultado_visitante":null,"local":"Arena Pantanal"},{"id":1353489,"campeonato":"Serie B","campeonato_id":72,"time_casa":"Botafogo SP","time_visitante":"Vila Nova","nome_time_casa":"Botafogo SP","nome_time_visitante":"Vila Nova","logo_time_casa":"https:\\/\\/media.api-sports.io\\/football\\/teams\\/2618.png","logo_time_visitante":"https:\\/\\/media.api-sports.io\\/football\\/teams\\/142.png","data":"2025-08-25 19:00:00","data_formatada":"25\\/08\\/2025 19:00","status":"NS","resultado_casa":null,"resultado_visitante":null,"local":"Arena NicNet"},{"id":1351261,"campeonato":"Serie A","campeonato_id":71,"time_casa":"Ceara","time_visitante":"Juventude","nome_time_casa":"Ceara","nome_time_visitante":"Juventude","logo_time_casa":"https:\\/\\/media.api-sports.io\\/football\\/teams\\/129.png","logo_time_visitante":"https:\\/\\/media.api-sports.io\\/football\\/teams\\/152.png","data":"2025-08-30 16:00:00","data_formatada":"30\\/08\\/2025 16:00","status":"NS","resultado_casa":null,"resultado_visitante":null,"local":"Est\\u00e1dio Governador Pl\\u00e1cido Aderaldo Castelo"},{"id":1351254,"campeonato":"Serie A","campeonato_id":71,"time_casa":"Botafogo","time_visitante":"RB Bragantino","nome_time_casa":"Botafogo","nome_time_visitante":"RB Bragantino","logo_time_casa":"https:\\/\\/media.api-sports.io\\/football\\/teams\\/120.png","logo_time_visitante":"https:\\/\\/media.api-sports.io\\/football\\/teams\\/794.png","data":"2025-08-30 18:30:00","data_formatada":"30\\/08\\/2025 18:30","status":"NS","resultado_casa":null,"resultado_visitante":null,"local":"Est\\u00e1dio Ol\\u00edmpico Nilton Santos"}]', '[{"id":"71","nome":"Brasileir\\u00e3o S\\u00e9rie A"},{"id":"72","nome":"Brasileir\\u00e3o S\\u00e9rie B"}]', '2025-08-19 16:31:06');
REPLACE INTO `dados_boloes` (`id`, `nome`, `slug`, `descricao`, `data_inicio`, `data_fim`, `data_limite_palpitar`, `valor_participacao`, `premio_rodada`, `premio_total`, `status`, `publico`, `max_participantes`, `quantidade_jogos`, `imagem_bolao_url`, `admin_id`, `jogos`, `campeonatos`, `data_criacao`) VALUES
	(22, 'Bolão 30/08/2025 a 07/08/2025', 'bolão-30-08-2025-a-07-08-2025', NULL, '2025-08-30', '2025-09-07', NULL, 0.12, 78888.00, 1000.00, 1, 1, NULL, 11, 'uploads/boloes/bolao_1755637444_68a4e6c44da76.png', 1, '[{"id":1351258,"campeonato":"Serie A","campeonato_id":71,"time_casa":"Cruzeiro","time_visitante":"Sao Paulo","nome_time_casa":"Cruzeiro","nome_time_visitante":"Sao Paulo","logo_time_casa":"https:\\/\\/media.api-sports.io\\/football\\/teams\\/135.png","logo_time_visitante":"https:\\/\\/media.api-sports.io\\/football\\/teams\\/126.png","data":"2025-08-30 21:00:00","data_formatada":"30\\/08\\/2025 21:00","status":"NS","resultado_casa":null,"resultado_visitante":null,"local":"Est\\u00e1dio Governador Magalh\\u00e3es Pinto"},{"id":1351253,"campeonato":"Serie A","campeonato_id":71,"time_casa":"Flamengo","time_visitante":"Gremio","nome_time_casa":"Flamengo","nome_time_visitante":"Gremio","logo_time_casa":"https:\\/\\/media.api-sports.io\\/football\\/teams\\/127.png","logo_time_visitante":"https:\\/\\/media.api-sports.io\\/football\\/teams\\/130.png","data":"2025-08-31 16:00:00","data_formatada":"31\\/08\\/2025 16:00","status":"NS","resultado_casa":null,"resultado_visitante":null,"local":"Estadio Jornalista M\\u00e1rio Filho"},{"id":1351256,"campeonato":"Serie A","campeonato_id":71,"time_casa":"Santos","time_visitante":"Fluminense","nome_time_casa":"Santos","nome_time_visitante":"Fluminense","logo_time_casa":"https:\\/\\/media.api-sports.io\\/football\\/teams\\/128.png","logo_time_visitante":"https:\\/\\/media.api-sports.io\\/football\\/teams\\/124.png","data":"2025-08-31 16:00:00","data_formatada":"31\\/08\\/2025 16:00","status":"NS","resultado_casa":null,"resultado_visitante":null,"local":"Est\\u00e1dio Urbano Caldeira"},{"id":1351255,"campeonato":"Serie A","campeonato_id":71,"time_casa":"Corinthians","time_visitante":"Palmeiras","nome_time_casa":"Corinthians","nome_time_visitante":"Palmeiras","logo_time_casa":"https:\\/\\/media.api-sports.io\\/football\\/teams\\/131.png","logo_time_visitante":"https:\\/\\/media.api-sports.io\\/football\\/teams\\/121.png","data":"2025-08-31 18:30:00","data_formatada":"31\\/08\\/2025 18:30","status":"NS","resultado_casa":null,"resultado_visitante":null,"local":"Neo Qu\\u00edmica Arena"},{"id":1351260,"campeonato":"Serie A","campeonato_id":71,"time_casa":"Vitoria","time_visitante":"Atletico-MG","nome_time_casa":"Vitoria","nome_time_visitante":"Atletico-MG","logo_time_casa":"https:\\/\\/media.api-sports.io\\/football\\/teams\\/136.png","logo_time_visitante":"https:\\/\\/media.api-sports.io\\/football\\/teams\\/1062.png","data":"2025-08-31 18:30:00","data_formatada":"31\\/08\\/2025 18:30","status":"NS","resultado_casa":null,"resultado_visitante":null,"local":"Est\\u00e1dio Manoel Barradas"},{"id":1351257,"campeonato":"Serie A","campeonato_id":71,"time_casa":"Mirassol","time_visitante":"Bahia","nome_time_casa":"Mirassol","nome_time_visitante":"Bahia","logo_time_casa":"https:\\/\\/media.api-sports.io\\/football\\/teams\\/7848.png","logo_time_visitante":"https:\\/\\/media.api-sports.io\\/football\\/teams\\/118.png","data":"2025-08-31 18:30:00","data_formatada":"31\\/08\\/2025 18:30","status":"NS","resultado_casa":null,"resultado_visitante":null,"local":"Est\\u00e1dio Jos\\u00e9 Maria de Campos Maia"},{"id":1351259,"campeonato":"Serie A","campeonato_id":71,"time_casa":"Internacional","time_visitante":"Fortaleza EC","nome_time_casa":"Internacional","nome_time_visitante":"Fortaleza EC","logo_time_casa":"https:\\/\\/media.api-sports.io\\/football\\/teams\\/119.png","logo_time_visitante":"https:\\/\\/media.api-sports.io\\/football\\/teams\\/154.png","data":"2025-08-31 20:30:00","data_formatada":"31\\/08\\/2025 20:30","status":"NS","resultado_casa":null,"resultado_visitante":null,"local":"Est\\u00e1dio Jos\\u00e9 Pinheiro Borda"},{"id":1351262,"campeonato":"Serie A","campeonato_id":71,"time_casa":"Sport Recife","time_visitante":"Vasco DA Gama","nome_time_casa":"Sport Recife","nome_time_visitante":"Vasco DA Gama","logo_time_casa":"https:\\/\\/media.api-sports.io\\/football\\/teams\\/123.png","logo_time_visitante":"https:\\/\\/media.api-sports.io\\/football\\/teams\\/133.png","data":"2025-08-31 20:30:00","data_formatada":"31\\/08\\/2025 20:30","status":"NS","resultado_casa":null,"resultado_visitante":null,"local":"Est\\u00e1dio Adelmar da Costa Carvalho"},{"id":1353498,"campeonato":"Serie B","campeonato_id":72,"time_casa":"Ferrovi\\u00e1ria","time_visitante":"Cuiaba","nome_time_casa":"Ferrovi\\u00e1ria","nome_time_visitante":"Cuiaba","logo_time_casa":"https:\\/\\/media.api-sports.io\\/football\\/teams\\/7826.png","logo_time_visitante":"https:\\/\\/media.api-sports.io\\/football\\/teams\\/1193.png","data":"2025-08-30 15:00:00","data_formatada":"30\\/08\\/2025 15:00","status":"NS","resultado_casa":null,"resultado_visitante":null,"local":"Est\\u00e1dio Doutor Adhemar de Barros"},{"id":1353496,"campeonato":"Serie B","campeonato_id":72,"time_casa":"CRB","time_visitante":"Paysandu","nome_time_casa":"CRB","nome_time_visitante":"Paysandu","logo_time_casa":"https:\\/\\/media.api-sports.io\\/football\\/teams\\/146.png","logo_time_visitante":"https:\\/\\/media.api-sports.io\\/football\\/teams\\/149.png","data":"2025-08-30 16:00:00","data_formatada":"30\\/08\\/2025 16:00","status":"NS","resultado_casa":null,"resultado_visitante":null,"local":"Est\\u00e1dio Rei Pel\\u00e9"},{"id":1353491,"campeonato":"Serie B","campeonato_id":72,"time_casa":"Goias","time_visitante":"Botafogo SP","nome_time_casa":"Goias","nome_time_visitante":"Botafogo SP","logo_time_casa":"https:\\/\\/media.api-sports.io\\/football\\/teams\\/151.png","logo_time_visitante":"https:\\/\\/media.api-sports.io\\/football\\/teams\\/2618.png","data":"2025-08-30 18:30:00","data_formatada":"30\\/08\\/2025 18:30","status":"NS","resultado_casa":null,"resultado_visitante":null,"local":"Est\\u00e1dio de Hail\\u00e9 Pinheiro"}]', '[{"id":"71","nome":"Brasileir\\u00e3o S\\u00e9rie A"},{"id":"72","nome":"Brasileir\\u00e3o S\\u00e9rie B"}]', '2025-08-19 18:04:32');

-- Copiando estrutura para tabela platafo5_bolao3.jogador
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
  `codigo_afiliado` varchar(20) DEFAULT NULL COMMENT 'Código único do afiliado',
  `ref_indicacao` varchar(20) DEFAULT NULL COMMENT 'Código do afiliado que indicou este jogador',
  `afiliado_ativo` enum('inativo','ativo') DEFAULT 'inativo' COMMENT 'Status do jogador como afiliado',
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `uk_cpf` (`cpf`),
  UNIQUE KEY `codigo_afiliado` (`codigo_afiliado`),
  KEY `idx_jogador_codigo_afiliado` (`codigo_afiliado`),
  KEY `idx_jogador_ref_indicacao` (`ref_indicacao`),
  KEY `idx_jogador_afiliado_ativo` (`afiliado_ativo`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Copiando dados para a tabela platafo5_bolao3.jogador: ~8 rows (aproximadamente)
REPLACE INTO `jogador` (`id`, `nome`, `email`, `senha`, `telefone`, `cpf`, `status`, `data_cadastro`, `ultimo_acesso`, `token_recuperacao`, `token_expira`, `codigo_afiliado`, `ref_indicacao`, `afiliado_ativo`) VALUES
	(1, 'Jogador de Testes', 'jogador@bolao.com', '$2y$12$5Q4bPMNugRrydUWBjvl5uu7tX83y1NW.PUa.j34zl/fF/p3YG7Joy', '(21) 96738-0813', NULL, 'ativo', '2025-05-24 16:26:20', NULL, NULL, NULL, 'af2E4FAB3A', NULL, 'ativo');
REPLACE INTO `jogador` (`id`, `nome`, `email`, `senha`, `telefone`, `cpf`, `status`, `data_cadastro`, `ultimo_acesso`, `token_recuperacao`, `token_expira`, `codigo_afiliado`, `ref_indicacao`, `afiliado_ativo`) VALUES
	(7, 'Eu não sei jogar', 'gerson@gmail.com', '$2y$10$RTeD2zpy1V/uc9NfB9AqMuGA3StAEbKJ5VkAJ2M5WPKK5OIfvM0AW', NULL, NULL, 'ativo', '2025-08-20 20:21:36', NULL, NULL, NULL, 'afDAF56679', NULL, 'ativo');
REPLACE INTO `jogador` (`id`, `nome`, `email`, `senha`, `telefone`, `cpf`, `status`, `data_cadastro`, `ultimo_acesso`, `token_recuperacao`, `token_expira`, `codigo_afiliado`, `ref_indicacao`, `afiliado_ativo`) VALUES
	(10, 'TULIO MARAVILHA', 'tulio.maravilha@gmail.com', '$2y$10$V6FSDNLHlkVOe6q2T9o2..mUwk9Vow5JK4K4imLU19gVgog7sORji', NULL, NULL, 'ativo', '2025-08-21 02:32:51', NULL, NULL, NULL, NULL, NULL, 'inativo');
REPLACE INTO `jogador` (`id`, `nome`, `email`, `senha`, `telefone`, `cpf`, `status`, `data_cadastro`, `ultimo_acesso`, `token_recuperacao`, `token_expira`, `codigo_afiliado`, `ref_indicacao`, `afiliado_ativo`) VALUES
	(11, 'Biribinha do Bolao ', 'geraldo@gmail.com', '$2y$10$iSCVInhmuTd57QbR0SB.DO4HpLSUNubgIBTj9xJ0XAS4iMiSJiW6y', NULL, NULL, 'ativo', '2025-08-21 02:35:25', NULL, NULL, NULL, NULL, NULL, 'inativo');
REPLACE INTO `jogador` (`id`, `nome`, `email`, `senha`, `telefone`, `cpf`, `status`, `data_cadastro`, `ultimo_acesso`, `token_recuperacao`, `token_expira`, `codigo_afiliado`, `ref_indicacao`, `afiliado_ativo`) VALUES
	(12, 'Biribona de Bolão', 'biribinha@bolao.com', '$2y$10$iiDAyrMkC72rznvPp/VabO8etWKZAYFfQavLwpLuLFdqTNhUZ.DSi', NULL, NULL, 'ativo', '2025-08-21 02:37:57', NULL, NULL, NULL, NULL, NULL, 'inativo');
REPLACE INTO `jogador` (`id`, `nome`, `email`, `senha`, `telefone`, `cpf`, `status`, `data_cadastro`, `ultimo_acesso`, `token_recuperacao`, `token_expira`, `codigo_afiliado`, `ref_indicacao`, `afiliado_ativo`) VALUES
	(13, 'Mendanha', 'jogador4@bolao.com', '$2y$10$ZE.l/AID.qIRz1IHBbN0Ke8zKeGmJsn8z2fgFyZ7DEcCCr4Lw3pei', NULL, NULL, 'ativo', '2025-08-21 02:44:14', NULL, NULL, NULL, NULL, NULL, 'inativo');
REPLACE INTO `jogador` (`id`, `nome`, `email`, `senha`, `telefone`, `cpf`, `status`, `data_cadastro`, `ultimo_acesso`, `token_recuperacao`, `token_expira`, `codigo_afiliado`, `ref_indicacao`, `afiliado_ativo`) VALUES
	(14, 'Gordão da XJ', 'gordaro@gordar.com', '$2y$10$c4txmTU/KowffUqHPhYlbeRreMoRupIM0CdVe4IUPoyMI2SP1IbIq', NULL, NULL, 'ativo', '2025-08-21 02:48:47', NULL, NULL, NULL, NULL, NULL, 'inativo');
REPLACE INTO `jogador` (`id`, `nome`, `email`, `senha`, `telefone`, `cpf`, `status`, `data_cadastro`, `ultimo_acesso`, `token_recuperacao`, `token_expira`, `codigo_afiliado`, `ref_indicacao`, `afiliado_ativo`) VALUES
	(15, 'Luiz da Silva', 'luis@luis.com', '$2y$10$LUTZQv95A1NgVL4951/X2emvonJLiI16K9Ofw9d0XE7tbR1gMxFBu', NULL, NULL, 'ativo', '2025-08-21 02:50:26', NULL, NULL, NULL, NULL, NULL, 'inativo');

-- Copiando estrutura para tabela platafo5_bolao3.jogador_copy
CREATE TABLE IF NOT EXISTS `jogador_copy` (
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
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `email` (`email`) USING BTREE,
  UNIQUE KEY `uk_cpf` (`cpf`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

-- Copiando dados para a tabela platafo5_bolao3.jogador_copy: ~2 rows (aproximadamente)
REPLACE INTO `jogador_copy` (`id`, `nome`, `email`, `senha`, `telefone`, `cpf`, `status`, `data_cadastro`, `ultimo_acesso`, `token_recuperacao`, `token_expira`) VALUES
	(1, 'Jogador de Testes', 'jogador@bolao.com', '$2y$12$5Q4bPMNugRrydUWBjvl5uu7tX83y1NW.PUa.j34zl/fF/p3YG7Joy', '(21) 96738-0813', NULL, 'ativo', '2025-05-24 16:26:20', NULL, NULL, NULL);
REPLACE INTO `jogador_copy` (`id`, `nome`, `email`, `senha`, `telefone`, `cpf`, `status`, `data_cadastro`, `ultimo_acesso`, `token_recuperacao`, `token_expira`) VALUES
	(7, 'Eu não sei jogar', 'gerson@gmail.com', '$2y$10$RTeD2zpy1V/uc9NfB9AqMuGA3StAEbKJ5VkAJ2M5WPKK5OIfvM0AW', NULL, NULL, 'ativo', '2025-08-20 20:21:36', NULL, NULL, NULL);

-- Copiando estrutura para tabela platafo5_bolao3.logs
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Copiando dados para a tabela platafo5_bolao3.logs: ~0 rows (aproximadamente)

-- Copiando estrutura para tabela platafo5_bolao3.notificacoes
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

-- Copiando dados para a tabela platafo5_bolao3.notificacoes: ~0 rows (aproximadamente)

-- Copiando estrutura para tabela platafo5_bolao3.palpites
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
) ENGINE=InnoDB AUTO_INCREMENT=30 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Copiando dados para a tabela platafo5_bolao3.palpites: ~27 rows (aproximadamente)
REPLACE INTO `palpites` (`id`, `jogador_id`, `bolao_id`, `palpites`, `data_palpite`, `status`, `afiliado_id`) VALUES
	(1, 1, 21, '{"jogos":[]}', '2025-08-19 16:38:19', 'pendente', NULL);
REPLACE INTO `palpites` (`id`, `jogador_id`, `bolao_id`, `palpites`, `data_palpite`, `status`, `afiliado_id`) VALUES
	(2, 1, 21, '{"jogos":[]}', '2025-08-19 16:40:14', 'pago', NULL);
REPLACE INTO `palpites` (`id`, `jogador_id`, `bolao_id`, `palpites`, `data_palpite`, `status`, `afiliado_id`) VALUES
	(3, 1, 21, '{"jogos":{"1351182":"1","1353487":"0","1353481":"2","1353486":"1","1353483":"2","1353488":"1","1353482":"2","1353489":"1","1351243":"2","1351261":"2","1351254":"1"}}', '2025-08-19 16:51:25', 'pago', NULL);
REPLACE INTO `palpites` (`id`, `jogador_id`, `bolao_id`, `palpites`, `data_palpite`, `status`, `afiliado_id`) VALUES
	(4, 1, 21, '{"jogos":[]}', '2025-08-19 16:51:35', 'pago', NULL);
REPLACE INTO `palpites` (`id`, `jogador_id`, `bolao_id`, `palpites`, `data_palpite`, `status`, `afiliado_id`) VALUES
	(5, 1, 21, '{"jogos":{"1351182":"0","1353487":"0","1353481":"0","1353486":"0","1353483":"0","1353488":"1","1353482":"2","1353489":"2","1351243":"0","1351261":"2","1351254":"1"}}', '2025-08-19 16:52:21', 'pago', NULL);
REPLACE INTO `palpites` (`id`, `jogador_id`, `bolao_id`, `palpites`, `data_palpite`, `status`, `afiliado_id`) VALUES
	(6, 1, 20, '{"jogos":{"1353485":"0","1351247":"2","1353480":"0","1351248":"1","1351249":"0","1351250":"1","1351244":"1","1351252":"0","1351251":"0","1351246":"0","1351245":"2"}}', '2025-08-19 17:30:03', 'pago', NULL);
REPLACE INTO `palpites` (`id`, `jogador_id`, `bolao_id`, `palpites`, `data_palpite`, `status`, `afiliado_id`) VALUES
	(7, 1, 20, '{"jogos":{"1353485":"2","1351247":"2","1353480":"2","1351248":"0","1351249":"2","1351250":"2","1351244":"2","1351252":"0","1351251":"0","1351246":"1","1351245":"2"}}', '2025-08-19 17:30:40', 'pendente', NULL);
REPLACE INTO `palpites` (`id`, `jogador_id`, `bolao_id`, `palpites`, `data_palpite`, `status`, `afiliado_id`) VALUES
	(8, 1, 21, '{"jogos":{"1351182":"0","1353487":"2","1353481":"1","1353486":"2","1353483":"1","1353488":"0","1353482":"0","1353489":"1","1351243":"2","1351261":"0","1351254":"0"}}', '2025-08-19 17:39:35', 'pago', NULL);
REPLACE INTO `palpites` (`id`, `jogador_id`, `bolao_id`, `palpites`, `data_palpite`, `status`, `afiliado_id`) VALUES
	(9, 1, 20, '{"jogos":{"1353485":"1","1351247":"1","1353480":"0","1351248":"2","1351249":"0","1351250":"0","1351244":"2","1351252":"2","1351251":"2","1351246":"0","1351245":"1"}}', '2025-08-19 17:39:58', 'pendente', NULL);
REPLACE INTO `palpites` (`id`, `jogador_id`, `bolao_id`, `palpites`, `data_palpite`, `status`, `afiliado_id`) VALUES
	(10, 1, 20, '{"jogos":{"1353485":"1","1351247":"2","1353480":"0","1351248":"1","1351249":"2","1351250":"0","1351244":"2","1351252":"1","1351251":"1","1351246":"2","1351245":"1"}}', '2025-08-19 17:57:02', 'pago', NULL);
REPLACE INTO `palpites` (`id`, `jogador_id`, `bolao_id`, `palpites`, `data_palpite`, `status`, `afiliado_id`) VALUES
	(11, 1, 20, '{"jogos":{"1353485":"0","1351247":"2","1353480":"2","1351248":"1","1351249":"2","1351250":"2","1351244":"1","1351252":"2","1351251":"2","1351246":"0","1351245":"0"}}', '2025-08-19 17:57:37', 'pago', NULL);
REPLACE INTO `palpites` (`id`, `jogador_id`, `bolao_id`, `palpites`, `data_palpite`, `status`, `afiliado_id`) VALUES
	(12, 1, 22, '{"jogos":[]}', '2025-08-20 18:21:47', 'pago', NULL);
REPLACE INTO `palpites` (`id`, `jogador_id`, `bolao_id`, `palpites`, `data_palpite`, `status`, `afiliado_id`) VALUES
	(13, 1, 22, '{"jogos":{"1353498":"0","1353496":"0","1353491":"0","1351258":"1","1351253":"1","1351256":"2","1351255":"1","1351260":"2","1351257":"1","1351259":"2","1351262":"0"}}', '2025-08-20 19:30:50', 'pago', NULL);
REPLACE INTO `palpites` (`id`, `jogador_id`, `bolao_id`, `palpites`, `data_palpite`, `status`, `afiliado_id`) VALUES
	(14, 1, 22, '{"jogos":{"1353498":"2","1353496":"2","1353491":"2","1351258":"2","1351253":"0","1351256":"0","1351255":"0","1351260":"0","1351257":"2","1351259":"1","1351262":"0"}}', '2025-08-20 19:31:41', 'pago', NULL);
REPLACE INTO `palpites` (`id`, `jogador_id`, `bolao_id`, `palpites`, `data_palpite`, `status`, `afiliado_id`) VALUES
	(15, 1, 22, '{"jogos":{"1353498":"0","1353496":"1","1353491":"2","1351258":"2","1351253":"1","1351256":"0","1351255":"2","1351260":"2","1351257":"0","1351259":"2","1351262":"2"}}', '2025-08-20 19:34:36', 'pago', NULL);
REPLACE INTO `palpites` (`id`, `jogador_id`, `bolao_id`, `palpites`, `data_palpite`, `status`, `afiliado_id`) VALUES
	(16, 1, 22, '{"jogos":{"1353498":"2","1353496":"1","1353491":"0","1351258":"2","1351253":"1","1351256":"0","1351255":"2","1351260":"1","1351257":"2","1351259":"1","1351262":"0"}}', '2025-08-20 19:54:18', 'pago', NULL);
REPLACE INTO `palpites` (`id`, `jogador_id`, `bolao_id`, `palpites`, `data_palpite`, `status`, `afiliado_id`) VALUES
	(17, 1, 22, '{"jogos":{"1353498":"2","1353496":"0","1353491":"0","1351258":"2","1351253":"0","1351256":"2","1351255":"2","1351260":"2","1351257":"0","1351259":"1","1351262":"1"}}', '2025-08-20 20:04:22', 'pago', NULL);
REPLACE INTO `palpites` (`id`, `jogador_id`, `bolao_id`, `palpites`, `data_palpite`, `status`, `afiliado_id`) VALUES
	(18, 1, 22, '{"jogos":{"1353498":"2","1353496":"0","1353491":"1","1351258":"0","1351253":"0","1351256":"0","1351255":"0","1351260":"2","1351257":"1","1351259":"0","1351262":"1"}}', '2025-08-20 20:12:07', 'pago', NULL);
REPLACE INTO `palpites` (`id`, `jogador_id`, `bolao_id`, `palpites`, `data_palpite`, `status`, `afiliado_id`) VALUES
	(19, 7, 22, '{"jogos":{"1353498":"0","1353496":"0","1353491":"0","1351258":"1","1351253":"0","1351256":"1","1351255":"0","1351260":"2","1351257":"2","1351259":"0","1351262":"0"}}', '2025-08-20 20:21:48', 'pendente', NULL);
REPLACE INTO `palpites` (`id`, `jogador_id`, `bolao_id`, `palpites`, `data_palpite`, `status`, `afiliado_id`) VALUES
	(20, 1, 22, '{"jogos":{"1353498":"0","1353496":"0","1353491":"1","1351258":"0","1351253":"1","1351256":"0","1351255":"1","1351260":"1","1351257":"1","1351259":"0","1351262":"0"}}', '2025-08-21 01:12:03', 'pago', NULL);
REPLACE INTO `palpites` (`id`, `jogador_id`, `bolao_id`, `palpites`, `data_palpite`, `status`, `afiliado_id`) VALUES
	(23, 10, 20, '{"jogos":{"1353485":"2","1351247":"0","1353480":"2","1351248":"2","1351249":"2","1351250":"0","1351244":"1","1351252":"1","1351251":"2","1351246":"1","1351245":"1"}}', '2025-08-21 02:33:20', 'pendente', NULL);
REPLACE INTO `palpites` (`id`, `jogador_id`, `bolao_id`, `palpites`, `data_palpite`, `status`, `afiliado_id`) VALUES
	(24, 11, 22, '{"jogos":{"1353498":"1","1353496":"1","1353491":"1","1351258":"1","1351253":"2","1351256":"1","1351255":"1","1351260":"0","1351257":"2","1351259":"0","1351262":"2"}}', '2025-08-21 02:35:32', 'pendente', NULL);
REPLACE INTO `palpites` (`id`, `jogador_id`, `bolao_id`, `palpites`, `data_palpite`, `status`, `afiliado_id`) VALUES
	(25, 12, 22, '{"jogos":{"1353498":"1","1353496":"0","1353491":"1","1351258":"1","1351253":"2","1351256":"0","1351255":"1","1351260":"1","1351257":"2","1351259":"2","1351262":"2"}}', '2025-08-21 02:38:02', 'pendente', NULL);
REPLACE INTO `palpites` (`id`, `jogador_id`, `bolao_id`, `palpites`, `data_palpite`, `status`, `afiliado_id`) VALUES
	(26, 12, 22, '{"jogos":{"1353498":"2","1353496":"1","1353491":"0","1351258":"2","1351253":"0","1351256":"2","1351255":"1","1351260":"0","1351257":"1","1351259":"1","1351262":"0"}}', '2025-08-21 02:39:19', 'pago', NULL);
REPLACE INTO `palpites` (`id`, `jogador_id`, `bolao_id`, `palpites`, `data_palpite`, `status`, `afiliado_id`) VALUES
	(27, 13, 22, '{"jogos":{"1353498":"0","1353496":"2","1353491":"0","1351258":"1","1351253":"0","1351256":"2","1351255":"2","1351260":"2","1351257":"1","1351259":"2","1351262":"2"}}', '2025-08-21 02:44:27', 'pendente', NULL);
REPLACE INTO `palpites` (`id`, `jogador_id`, `bolao_id`, `palpites`, `data_palpite`, `status`, `afiliado_id`) VALUES
	(28, 14, 22, '{"jogos":{"1353498":"0","1353496":"2","1353491":"0","1351258":"1","1351253":"2","1351256":"0","1351255":"2","1351260":"2","1351257":"2","1351259":"2","1351262":"1"}}', '2025-08-21 02:48:52', 'pendente', NULL);
REPLACE INTO `palpites` (`id`, `jogador_id`, `bolao_id`, `palpites`, `data_palpite`, `status`, `afiliado_id`) VALUES
	(29, 15, 22, '{"jogos":{"1353498":"2","1353496":"1","1353491":"1","1351258":"0","1351253":"1","1351256":"0","1351255":"2","1351260":"2","1351257":"1","1351259":"0","1351262":"0"}}', '2025-08-21 02:50:31', 'pendente', NULL);

-- Copiando estrutura para tabela platafo5_bolao3.transacoes
CREATE TABLE IF NOT EXISTS `transacoes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `conta_id` int(11) NOT NULL,
  `tipo` enum('deposito','saque','aposta','premio','estorno','bonus') NOT NULL,
  `valor` decimal(10,2) NOT NULL,
  `status` enum('pendente','aprovado','rejeitado','cancelado','processando') DEFAULT 'pendente',
  `descricao` text DEFAULT NULL,
  `referencia` varchar(100) DEFAULT NULL,
  `data_solicitacao` datetime DEFAULT current_timestamp(),
  `data_processamento` datetime DEFAULT NULL,
  `txid` varchar(100) DEFAULT NULL,
  `palpite_id` int(11) DEFAULT NULL,
  `afeta_saldo` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_txid` (`txid`),
  KEY `fk_transacao_palpite` (`palpite_id`),
  KEY `idx_conta_status` (`conta_id`,`status`),
  KEY `idx_data_conta` (`data_solicitacao`,`conta_id`),
  KEY `idx_tipo_status` (`tipo`,`status`),
  CONSTRAINT `fk_transacao_palpite` FOREIGN KEY (`palpite_id`) REFERENCES `palpites` (`id`),
  CONSTRAINT `transacoes_ibfk_1` FOREIGN KEY (`conta_id`) REFERENCES `contas` (`id`),
  CONSTRAINT `chk_afeta_saldo_aprovado` CHECK (`status` <> 'aprovado' or `afeta_saldo` = 1)
) ENGINE=InnoDB AUTO_INCREMENT=24 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Copiando dados para a tabela platafo5_bolao3.transacoes: ~22 rows (aproximadamente)
REPLACE INTO `transacoes` (`id`, `conta_id`, `tipo`, `valor`, `status`, `descricao`, `referencia`, `data_solicitacao`, `data_processamento`, `txid`, `palpite_id`, `afeta_saldo`) VALUES
	(1, 1, 'deposito', 1.00, 'aprovado', NULL, 'zhaxoT5fcWRJjC3hvED9BYWaTT3nAleN', '2025-08-19 16:39:23', '2025-08-19 16:39:46', 'JV2ZHrkBBfvDrRGnP9ySruH5ha9OE5E3', NULL, 1);
REPLACE INTO `transacoes` (`id`, `conta_id`, `tipo`, `valor`, `status`, `descricao`, `referencia`, `data_solicitacao`, `data_processamento`, `txid`, `palpite_id`, `afeta_saldo`) VALUES
	(2, 1, 'aposta', 0.05, 'aprovado', NULL, 'bolao_21', '2025-08-19 16:40:14', NULL, NULL, 2, 1);
REPLACE INTO `transacoes` (`id`, `conta_id`, `tipo`, `valor`, `status`, `descricao`, `referencia`, `data_solicitacao`, `data_processamento`, `txid`, `palpite_id`, `afeta_saldo`) VALUES
	(3, 1, 'aposta', 0.05, 'aprovado', NULL, 'bolao_21', '2025-08-19 16:51:25', NULL, NULL, 3, 1);
REPLACE INTO `transacoes` (`id`, `conta_id`, `tipo`, `valor`, `status`, `descricao`, `referencia`, `data_solicitacao`, `data_processamento`, `txid`, `palpite_id`, `afeta_saldo`) VALUES
	(4, 1, 'aposta', 0.05, 'aprovado', NULL, 'bolao_21', '2025-08-19 16:51:35', NULL, NULL, 4, 1);
REPLACE INTO `transacoes` (`id`, `conta_id`, `tipo`, `valor`, `status`, `descricao`, `referencia`, `data_solicitacao`, `data_processamento`, `txid`, `palpite_id`, `afeta_saldo`) VALUES
	(5, 1, 'aposta', 0.05, 'aprovado', NULL, 'bolao_21', '2025-08-19 16:52:21', NULL, NULL, 5, 1);
REPLACE INTO `transacoes` (`id`, `conta_id`, `tipo`, `valor`, `status`, `descricao`, `referencia`, `data_solicitacao`, `data_processamento`, `txid`, `palpite_id`, `afeta_saldo`) VALUES
	(7, 1, 'aposta', 0.50, 'aprovado', NULL, 'bolao_20', '2025-08-19 17:30:03', NULL, NULL, 6, 1);
REPLACE INTO `transacoes` (`id`, `conta_id`, `tipo`, `valor`, `status`, `descricao`, `referencia`, `data_solicitacao`, `data_processamento`, `txid`, `palpite_id`, `afeta_saldo`) VALUES
	(8, 1, 'aposta', 0.05, 'aprovado', NULL, 'bolao_21', '2025-08-19 17:39:35', NULL, NULL, 8, 1);
REPLACE INTO `transacoes` (`id`, `conta_id`, `tipo`, `valor`, `status`, `descricao`, `referencia`, `data_solicitacao`, `data_processamento`, `txid`, `palpite_id`, `afeta_saldo`) VALUES
	(9, 1, 'deposito', 1.00, 'aprovado', NULL, 'vEIEKP2AXl9Wkpe9T48zJxwPAhldbg6j', '2025-08-19 17:40:21', '2025-08-19 17:40:45', '1Ubs0fDzA14h1A2XZloKBY4bOIJVq56V', NULL, 1);
REPLACE INTO `transacoes` (`id`, `conta_id`, `tipo`, `valor`, `status`, `descricao`, `referencia`, `data_solicitacao`, `data_processamento`, `txid`, `palpite_id`, `afeta_saldo`) VALUES
	(10, 1, 'aposta', 0.50, 'aprovado', NULL, 'bolao_20', '2025-08-19 17:57:02', NULL, NULL, 10, 1);
REPLACE INTO `transacoes` (`id`, `conta_id`, `tipo`, `valor`, `status`, `descricao`, `referencia`, `data_solicitacao`, `data_processamento`, `txid`, `palpite_id`, `afeta_saldo`) VALUES
	(11, 1, 'aposta', 0.50, 'aprovado', NULL, 'bolao_20', '2025-08-19 17:57:37', NULL, NULL, 11, 1);
REPLACE INTO `transacoes` (`id`, `conta_id`, `tipo`, `valor`, `status`, `descricao`, `referencia`, `data_solicitacao`, `data_processamento`, `txid`, `palpite_id`, `afeta_saldo`) VALUES
	(12, 1, 'deposito', 1.00, 'aprovado', NULL, 'BCdKmgoOkWLnw88dbhyJyEElZ1cuFuRk', '2025-08-19 17:58:24', '2025-08-19 17:58:39', 'GDNiaLMibjEi4bNNzPw1pBqZRhCArMem', NULL, 1);
REPLACE INTO `transacoes` (`id`, `conta_id`, `tipo`, `valor`, `status`, `descricao`, `referencia`, `data_solicitacao`, `data_processamento`, `txid`, `palpite_id`, `afeta_saldo`) VALUES
	(13, 1, 'aposta', 0.12, 'aprovado', NULL, 'bolao_22', '2025-08-20 18:21:47', NULL, NULL, 12, 1);
REPLACE INTO `transacoes` (`id`, `conta_id`, `tipo`, `valor`, `status`, `descricao`, `referencia`, `data_solicitacao`, `data_processamento`, `txid`, `palpite_id`, `afeta_saldo`) VALUES
	(14, 1, 'aposta', 0.12, 'aprovado', NULL, 'bolao_22', '2025-08-20 19:30:50', NULL, NULL, 13, 1);
REPLACE INTO `transacoes` (`id`, `conta_id`, `tipo`, `valor`, `status`, `descricao`, `referencia`, `data_solicitacao`, `data_processamento`, `txid`, `palpite_id`, `afeta_saldo`) VALUES
	(15, 1, 'aposta', 0.12, 'aprovado', NULL, 'bolao_22', '2025-08-20 19:31:41', NULL, NULL, 14, 1);
REPLACE INTO `transacoes` (`id`, `conta_id`, `tipo`, `valor`, `status`, `descricao`, `referencia`, `data_solicitacao`, `data_processamento`, `txid`, `palpite_id`, `afeta_saldo`) VALUES
	(16, 1, 'aposta', 0.12, 'aprovado', NULL, 'bolao_22', '2025-08-20 19:34:36', NULL, NULL, 15, 1);
REPLACE INTO `transacoes` (`id`, `conta_id`, `tipo`, `valor`, `status`, `descricao`, `referencia`, `data_solicitacao`, `data_processamento`, `txid`, `palpite_id`, `afeta_saldo`) VALUES
	(17, 1, 'aposta', 0.12, 'aprovado', NULL, 'bolao_22', '2025-08-20 19:54:18', NULL, NULL, 16, 1);
REPLACE INTO `transacoes` (`id`, `conta_id`, `tipo`, `valor`, `status`, `descricao`, `referencia`, `data_solicitacao`, `data_processamento`, `txid`, `palpite_id`, `afeta_saldo`) VALUES
	(18, 1, 'aposta', 0.12, 'aprovado', NULL, 'bolao_22', '2025-08-20 20:04:22', NULL, NULL, 17, 1);
REPLACE INTO `transacoes` (`id`, `conta_id`, `tipo`, `valor`, `status`, `descricao`, `referencia`, `data_solicitacao`, `data_processamento`, `txid`, `palpite_id`, `afeta_saldo`) VALUES
	(19, 1, 'aposta', 0.12, 'aprovado', NULL, 'bolao_22', '2025-08-20 20:12:08', NULL, NULL, 18, 1);
REPLACE INTO `transacoes` (`id`, `conta_id`, `tipo`, `valor`, `status`, `descricao`, `referencia`, `data_solicitacao`, `data_processamento`, `txid`, `palpite_id`, `afeta_saldo`) VALUES
	(20, 1, 'deposito', 1.00, 'aprovado', NULL, '0MhkLyBGgI2j81VZmllRXY1Nu6kBPmMJ', '2025-08-20 20:15:13', '2025-08-20 20:15:28', 'WugWZTnZAo9kdf9uIEH5W8BSFgFKnlBp', NULL, 1);
REPLACE INTO `transacoes` (`id`, `conta_id`, `tipo`, `valor`, `status`, `descricao`, `referencia`, `data_solicitacao`, `data_processamento`, `txid`, `palpite_id`, `afeta_saldo`) VALUES
	(21, 1, 'aposta', 0.12, 'aprovado', NULL, 'bolao_22', '2025-08-21 01:12:03', NULL, NULL, 20, 1);
REPLACE INTO `transacoes` (`id`, `conta_id`, `tipo`, `valor`, `status`, `descricao`, `referencia`, `data_solicitacao`, `data_processamento`, `txid`, `palpite_id`, `afeta_saldo`) VALUES
	(22, 57, 'deposito', 1.00, 'aprovado', NULL, 'o5dZBjz9iLB0oCY07ITvgXmq2pWrzU6R', '2025-08-21 02:38:27', '2025-08-21 02:38:51', 'QNewf2M3YXMYSAJERAFWozDQ9VNVMvJz', NULL, 1);
REPLACE INTO `transacoes` (`id`, `conta_id`, `tipo`, `valor`, `status`, `descricao`, `referencia`, `data_solicitacao`, `data_processamento`, `txid`, `palpite_id`, `afeta_saldo`) VALUES
	(23, 57, 'aposta', 0.12, 'aprovado', NULL, 'bolao_22', '2025-08-21 02:39:19', NULL, NULL, 26, 1);

/*!40103 SET TIME_ZONE=IFNULL(@OLD_TIME_ZONE, 'system') */;
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;
