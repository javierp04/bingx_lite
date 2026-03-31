-- Migration: Add trading module access columns to users table
-- Date: 2026-03-30

ALTER TABLE users ADD COLUMN module_bingx TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE users ADD COLUMN module_metatrader TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE users ADD COLUMN module_atvip TINYINT(1) NOT NULL DEFAULT 0;

-- Enable all modules for existing users
UPDATE users SET module_bingx = 1, module_metatrader = 1, module_atvip = 1;
