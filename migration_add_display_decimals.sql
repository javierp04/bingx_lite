-- Migration: Add display_decimals column to available_tickers
-- Date: 2025-10-09
-- Description: Stores the number of decimal places to display prices for each ticker

ALTER TABLE `available_tickers`
ADD COLUMN `display_decimals` TINYINT UNSIGNED NOT NULL DEFAULT 5
COMMENT 'Number of decimal places to display prices (e.g., 5 for EURUSD, 3 for XAUUSD, 1 for US500)'
AFTER `name`;

-- Update existing tickers with appropriate decimal places
-- Forex (5 decimals)
UPDATE available_tickers SET display_decimals = 5
WHERE symbol IN ('EURUSD', 'GBPUSD', 'USDJPY', 'AUDUSD', 'USDCAD', 'NZDUSD', 'EURGBP', 'EURJPY', 'GBPJPY', 'AUDJPY');

-- Gold/Silver (3 decimals)
UPDATE available_tickers SET display_decimals = 3
WHERE symbol IN ('XAUUSD', 'XAGUSD');

-- Oil/Commodities (3 decimals)
UPDATE available_tickers SET display_decimals = 3
WHERE symbol IN ('USOIL', 'UKOIL', 'NATGAS');

-- US Indices (1 decimal)
UPDATE available_tickers SET display_decimals = 1
WHERE symbol IN ('US500', 'US100', 'US30');

-- Other Indices (3 decimals)
UPDATE available_tickers SET display_decimals = 3
WHERE symbol IN ('US2000');

-- Crypto (2 decimals for most, 8 for precision pairs)
UPDATE available_tickers SET display_decimals = 2
WHERE symbol LIKE '%USDT' OR symbol LIKE '%BUSD' OR symbol = 'BTCUSD';

-- Special cases (if exist)
UPDATE available_tickers SET display_decimals = 8
WHERE symbol IN ('BTCUSD', 'ETHUSD') AND symbol NOT LIKE '%USDT';
