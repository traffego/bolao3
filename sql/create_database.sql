ALTER TABLE `palpites`
ADD COLUMN `status` ENUM('pendente', 'pago', 'cancelado') NOT NULL DEFAULT 'pendente' AFTER `data_palpite`; 