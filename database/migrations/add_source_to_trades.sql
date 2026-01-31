-- Migration: Add source and user_signal_id columns to trades table
-- Date: 2026-01-30
-- Purpose: Enable EA autonomous trading and unify Trade History

-- Add source column to identify trade origin
ALTER TABLE trades ADD COLUMN source VARCHAR(50) DEFAULT 'bingx';

-- Add user_signal_id to link ATVIP trades
ALTER TABLE trades ADD COLUMN user_signal_id INT NULL;

-- Update existing trades with appropriate source
UPDATE trades t
JOIN strategies s ON t.strategy_id = s.id
SET t.source = CASE
    WHEN s.platform = 'bingx' THEN 'bingx'
    WHEN t.mt_signal_id IS NOT NULL THEN 'metatrader_tv'
    ELSE 'bingx'
END
WHERE t.source IS NULL OR t.source = 'bingx';

-- Add index for faster filtering
CREATE INDEX idx_trades_source ON trades(source);
CREATE INDEX idx_trades_user_signal_id ON trades(user_signal_id);
