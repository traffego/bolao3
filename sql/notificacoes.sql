-- Tabela de notificações
CREATE TABLE IF NOT EXISTS `notificacoes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `jogador_id` int(11) NOT NULL,
  `tipo` enum('saque_aprovado','saque_rejeitado','deposito_confirmado','sistema') NOT NULL,
  `titulo` varchar(255) NOT NULL,
  `mensagem` text NOT NULL,
  `lida` tinyint(1) DEFAULT 0,
  `data_criacao` datetime DEFAULT current_timestamp(),
  `data_leitura` datetime DEFAULT NULL,
  `dados_adicionais` JSON DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_jogador_lida` (`jogador_id`, `lida`),
  KEY `idx_data` (`data_criacao`),
  CONSTRAINT `notificacoes_ibfk_1` FOREIGN KEY (`jogador_id`) REFERENCES `jogador` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci; 