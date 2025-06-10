-- Remover a constraint antiga
ALTER TABLE `pagamentos` DROP FOREIGN KEY `pagamentos_ibfk_2`;

-- Atualizar a referência para a tabela dados_boloes
ALTER TABLE `pagamentos` ADD CONSTRAINT `pagamentos_ibfk_2` 
FOREIGN KEY (`bolao_id`) REFERENCES `dados_boloes` (`id`) ON DELETE CASCADE; 