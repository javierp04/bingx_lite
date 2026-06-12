-- AI provider: add Gemini analysis storage + generic settings table
-- Idempotent where possible; safe to re-run.

-- 1) Columna para guardar el resultado crudo de Gemini (junto a openai/claude)
ALTER TABLE `telegram_signals`
  ADD COLUMN `analysis_gemini` TEXT DEFAULT NULL AFTER `analysis_claude`;

-- 2) Tabla genérica de settings (key/value) para selección de proveedores y futuros toggles
CREATE TABLE IF NOT EXISTS `system_settings` (
  `setting_key`   VARCHAR(64) NOT NULL,
  `setting_value` TEXT DEFAULT NULL,
  `updated_at`    DATETIME DEFAULT NULL,
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3) Defaults del par del consenso (no pisa si ya existen)
INSERT INTO `system_settings` (`setting_key`, `setting_value`, `updated_at`) VALUES
  ('ai_provider_a', 'gemini', NOW()),
  ('ai_provider_b', 'openai', NOW()),
  ('ai_mode', 'dual', NOW())
ON DUPLICATE KEY UPDATE `setting_key` = `setting_key`;
