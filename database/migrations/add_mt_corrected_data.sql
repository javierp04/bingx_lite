-- Migration: Agregar columna mt_corrected_data para almacenar precios post-corrección del EA
-- Fecha: 2026-04-01

ALTER TABLE user_telegram_signals 
ADD COLUMN mt_corrected_data TEXT NULL COMMENT 'JSON con precios corregidos por el EA (post-correction)' AFTER mt_execution_data;
