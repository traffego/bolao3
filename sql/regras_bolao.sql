CREATE TABLE `regras_bolao` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `pontos_acerto_exato` int(11) NOT NULL DEFAULT 10,
  `pontos_acerto_vencedor` int(11) NOT NULL DEFAULT 5,
  `pontos_acerto_placar_time1` int(11) NOT NULL DEFAULT 3,
  `pontos_acerto_placar_time2` int(11) NOT NULL DEFAULT 3,
  `pontos_erro_total` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci; 