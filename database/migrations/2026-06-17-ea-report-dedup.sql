-- Idempotencia de los reportes del EA (open/progress/close).
-- 1 fila por reporte aplicado. El UNIQUE(user_signal_id, body_hash) es el gate: un reporte
-- reenviado (mismo body) choca el indice y se trata como no-op, evitando doble-conteo de PNL
-- y eventos/trades duplicados. Atomico (lo resuelve la DB), cierra de paso cualquier race.
-- Idempotente; seguro de re-ejecutar. Si esta tabla no existe, el server aplica directo
-- (= comportamiento previo): el deploy de la migracion se puede hacer cuando se quiera.

CREATE TABLE IF NOT EXISTS `ea_report_dedup` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_signal_id` INT UNSIGNED NOT NULL,
  `endpoint`       ENUM('open','progress','close') NOT NULL,  -- informativo/debug
  `body_hash`      CHAR(40) NOT NULL,                          -- sha1 del body crudo del request
  `created_at`     DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_signal_hash` (`user_signal_id`, `body_hash`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
