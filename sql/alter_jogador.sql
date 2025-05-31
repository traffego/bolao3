ALTER TABLE `jogador`
ADD COLUMN `cpf` VARCHAR(14) NULL DEFAULT NULL COLLATE 'utf8mb4_unicode_ci' AFTER `telefone`,
ADD UNIQUE INDEX `uk_cpf` (`cpf`); 