-- phpMyAdmin SQL Dump
-- version 5.2.0
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 03-04-2026 a las 01:03:37
-- Versión del servidor: 10.4.27-MariaDB
-- Versión de PHP: 7.4.33

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `bingx_lite`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `api_keys`
--

CREATE TABLE `api_keys` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `api_key` varchar(255) NOT NULL,
  `api_secret` varchar(255) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `api_keys`
--

INSERT INTO `api_keys` (`id`, `user_id`, `api_key`, `api_secret`, `created_at`, `updated_at`) VALUES
(1, 1, 'mlTNwZLYS5qb0ojsPMUxym78kboX5ekCAYLUcUrPrr2kYrSpbP6DRTEDDkQcLMT9C8cNEcqjUdlI0zyA794Q', 'vsk1FT5W2ZOUNmkHyO3B8OlvPXMlVefAJ4Vqt1PPtK2oGZq50on8g1XFBMBcEnhAPtVLiNBM5MaDxmdMWImHIg', '2025-04-01 23:32:18', NULL),
(2, 2, 'mlTNwZLYS5qb0ojsPMUxym78kboX5ekCAYLUcUrPrr2kYrSpbP6DRTEDDkQcLMT9C8cNEcqjUdlI0zyA794Q', 'vsk1FT5W2ZOUNmkHyO3B8OlvPXMlVefAJ4Vqt1PPtK2oGZq50on8g1XFBMBcEnhAPtVLiNBM5MaDxmdMWImHIg', '2025-04-01 23:32:34', '2026-04-03 01:01:31');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `available_tickers`
--

CREATE TABLE `available_tickers` (
  `symbol` varchar(20) NOT NULL,
  `name` varchar(100) NOT NULL,
  `display_decimals` tinyint(3) UNSIGNED NOT NULL DEFAULT 5 COMMENT 'Number of decimal places to display prices (e.g., 5 for EURUSD, 3 for XAUUSD, 1 for US500)',
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `available_tickers`
--

INSERT INTO `available_tickers` (`symbol`, `name`, `display_decimals`, `active`, `created_at`, `updated_at`) VALUES
('BTCUSD', 'Bitcoin / US Dollar', 0, 1, '2025-09-03 00:56:26', '2025-10-09 00:46:39'),
('CL', 'NYMEX:CL1! - Futuros de Crudo Ligero', 3, 1, '2025-09-03 00:58:15', '2025-10-09 00:46:39'),
('ES', 'ES1! - Futuros S&P 500', 1, 1, '2025-09-03 01:01:45', '2025-10-09 00:46:39'),
('EURUSD', 'Euro/US Dollar', 5, 1, '2025-09-03 00:48:21', NULL),
('GC', 'COMEX:GC1! - Futuros de Oro', 3, 1, '2025-09-03 00:58:01', '2025-10-09 00:46:39'),
('NQ', 'NQ1! - Futuros NASDAQ 100', 1, 1, '2025-09-03 01:02:26', '2025-10-09 00:46:39'),
('RTY', 'RTY1! - Futuros Russell 2000', 3, 1, '2025-09-03 01:03:02', '2025-10-09 00:46:39'),
('USDBRL', 'US Dollar / Brazilean Real', 5, 1, '2025-09-03 00:57:15', NULL),
('VIX', 'TVC:VIX', 1, 1, '2025-09-03 01:01:21', '2025-10-09 00:46:39'),
('ZS', 'CBOT:ZS1! - Futuros de Soja', 3, 1, '2025-09-03 00:57:47', '2025-10-09 00:46:39');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `mt_signals`
--

CREATE TABLE `mt_signals` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `strategy_id` int(11) NOT NULL,
  `signal_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`signal_data`)),
  `status` enum('pending','processing','processed','failed') DEFAULT 'pending',
  `created_at` datetime DEFAULT current_timestamp(),
  `processed_at` datetime DEFAULT NULL,
  `ea_response` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `strategies`
--

CREATE TABLE `strategies` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `strategy_id` varchar(50) NOT NULL,
  `name` varchar(100) NOT NULL,
  `type` enum('spot','futures','forex','indices','commodities') NOT NULL,
  `platform` enum('bingx','metatrader') NOT NULL DEFAULT 'bingx',
  `description` text DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `strategies`
--

INSERT INTO `strategies` (`id`, `user_id`, `strategy_id`, `name`, `type`, `platform`, `description`, `image`, `active`, `created_at`, `updated_at`) VALUES
(1, 1, 'BTC_H1_RSI', 'Estrategia Bitcoin RSI en H1 - SHORT Estructural', 'spot', 'bingx', '', 'f6f06595be174ca88fc3b46ad9e9cf45.jpg', 0, '2025-04-01 23:51:23', '2025-05-10 16:41:04'),
(2, 1, 'FUT_BTC_H1_TREND_ALIGNED_RSI', 'BTC H1 RSI Trend Aligned', 'futures', 'bingx', '', '8ea43fe06e0d7470cb57780964f434bb.jpg', 1, '2025-04-02 23:10:12', '2025-08-11 23:44:32'),
(3, 1, 'FUT_BTC_M15_TREND_ALIGNED_RSI', 'M15 RSI Trend Aligned & EMA Cross', 'futures', 'bingx', '', 'e7ace78da24ce8c22e585e09cc29b9be.jpg', 0, '2025-05-11 19:57:27', '2025-06-11 23:59:55'),
(4, 1, 'FUT_BTC_H1_GANN', 'BTC H1 Gann HiLo Strategy', 'futures', 'bingx', 'Gann HiLo Strategy for BTCUSDT - H1', '35d05f8b3053bfa4aa8fef8298f87d49.jpg', 1, '2025-06-11 23:59:25', '2025-08-09 00:46:11'),
(5, 1, 'FUT_BTC_H1_URSI_TREND', 'BTC H1 URSI Trend Aligned', 'futures', 'bingx', '', '43831fe5896a8032456b59db1b24010d.jpg', 0, '2025-06-24 07:08:21', '2025-08-18 17:02:08'),
(6, 1, 'FUT_ETH_H1_URSI_TREND', 'ETH H1 URSI Trend Aligned', 'futures', 'bingx', '', 'aae7cea0c0b50967e205431316e69f0a.jpg', 1, '2025-08-07 15:36:20', NULL),
(7, 1, 'FUT_ETH_H1_TREND_ALIGNED_RSI', 'ETH H1 RSI Trend Aligned', 'futures', 'bingx', '', '42324dae6346bdaf1e595d3c6551953f.jpg', 0, '2025-08-07 16:22:02', '2025-08-12 00:38:01'),
(8, 1, 'FUT_BTC_H1_TEMA_ST_OVER_MA', 'BTC H1 TEMA ST Channel Over MA', 'futures', 'bingx', '', '372515fa69de91287318b89b67e8e726.jpg', 1, '2025-08-09 00:51:25', '2025-08-22 23:25:51'),
(9, 1, 'FUT_ETH_H1_TEMA_SUPERTREND', 'ETH H1 TEMA Supertrend Channel Breakout', 'futures', 'bingx', '', '09200f5039304859782625c015ef83f6.jpg', 1, '2025-08-09 02:00:36', NULL),
(10, 1, 'FUT_ETH_H1_GANN', 'ETH H1 Gann HiLo Strategy', 'futures', 'bingx', '', '64a584745347b6cafcf8f3a47c2b03e5.jpg', 1, '2025-08-12 01:13:37', NULL),
(11, 1, 'FUT_BTC_H1_TEMA_ST_BELOW_MA', 'BTC H1 TEMA ST Channel Below MA', 'futures', 'bingx', '', '2de806ba0d020060f80c4076ea3e5233.jpg', 1, '2025-08-22 23:26:53', NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `system_logs`
--

CREATE TABLE `system_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `description` text NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `system_logs`
--

INSERT INTO `system_logs` (`id`, `user_id`, `action`, `description`, `ip_address`, `created_at`) VALUES
(1, 2, 'login', 'User logged in', '127.0.0.1', '2026-04-03 00:26:43'),
(2, 2, 'logout', 'User logged out', '127.0.0.1', '2026-04-03 01:00:05'),
(3, 2, 'login', 'User logged in', '127.0.0.1', '2026-04-03 01:00:10'),
(4, NULL, 'op_type_detection', 'Método: IA (leyendas) | Resultado: LONG | Contexto: cropped-2026-04-03_NQ', '127.0.0.1', '2026-04-03 01:02:09'),
(5, NULL, 'telegram_pipeline_completed', 'Signal pipeline completed for ID: 1. Analysis: {\"op_type\":\"LONG\",\"stoploss\":[23565.5,23639.25],\"entry\":23697.5,\"tps\":[23740.25,23783.25,23832.5,23880.5,23970.5,24030,24125.75,24290.5]}. Distributed to 1 users.', '127.0.0.1', '2026-04-03 01:02:09'),
(6, 2, 'telegram_webhook_simulation', 'Simulated Telegram webhook. Signal ID: 1. AI: claude', '127.0.0.1', '2026-04-03 01:02:09');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `telegram_signals`
--

CREATE TABLE `telegram_signals` (
  `id` int(11) NOT NULL,
  `ticker_symbol` varchar(20) NOT NULL,
  `image_path` varchar(255) NOT NULL,
  `tradingview_url` text NOT NULL,
  `message_text` text DEFAULT NULL,
  `webhook_raw_data` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `status` enum('pending','cropping','analyzing','completed','failed_crop','failed_analysis','failed_download','pending_review') DEFAULT 'pending',
  `analysis_data` text DEFAULT NULL,
  `analysis_openai` text DEFAULT NULL,
  `analysis_claude` text DEFAULT NULL,
  `ai_validated` tinyint(1) DEFAULT NULL,
  `op_type` varchar(10) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `telegram_signals`
--

INSERT INTO `telegram_signals` (`id`, `ticker_symbol`, `image_path`, `tradingview_url`, `message_text`, `webhook_raw_data`, `created_at`, `updated_at`, `status`, `analysis_data`, `analysis_openai`, `analysis_claude`, `ai_validated`, `op_type`) VALUES
(1, 'NQ', 'uploads/trades/2026-04-03_NQ.png', 'https://www.tradingview.com/x/J5X2Jyed/', 'Sentimiento #NQ https://www.tradingview.com/x/J5X2Jyed/', '{\"update_id\":573549,\"message\":{\"message_id\":3674,\"from\":{\"id\":999999,\"is_bot\":false,\"first_name\":\"Debug\",\"last_name\":\"Simulator\",\"username\":\"debug_simulator\"},\"chat\":{\"id\":-1001234567890,\"title\":\"Debug Test Channel\",\"type\":\"channel\"},\"date\":1775188923,\"text\":\"Sentimiento #NQ https:\\/\\/www.tradingview.com\\/x\\/J5X2Jyed\\/\"}}', '2026-04-03 01:02:05', '2026-04-03 01:02:09', 'completed', '{\"op_type\":\"LONG\",\"stoploss\":[23565.5,23639.25],\"entry\":23697.5,\"tps\":[23740.25,23783.25,23832.5,23880.5,23970.5,24030,24125.75,24290.5]}', NULL, NULL, NULL, 'LONG');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `trades`
--

CREATE TABLE `trades` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `strategy_id` int(11) NOT NULL,
  `order_id` varchar(100) DEFAULT NULL,
  `symbol` varchar(20) NOT NULL,
  `timeframe` varchar(10) NOT NULL,
  `side` enum('BUY','SELL') NOT NULL,
  `trade_type` enum('spot','futures','forex','indices','commodities') NOT NULL,
  `mt_signal_id` int(11) DEFAULT NULL,
  `environment` enum('production','sandbox') NOT NULL DEFAULT 'production',
  `quantity` decimal(18,8) NOT NULL,
  `entry_price` decimal(18,8) NOT NULL,
  `current_price` decimal(18,8) DEFAULT NULL,
  `leverage` int(11) DEFAULT 1,
  `take_profit` decimal(18,8) DEFAULT NULL,
  `stop_loss` decimal(18,8) DEFAULT NULL,
  `exit_price` decimal(18,8) DEFAULT NULL,
  `pnl` decimal(18,8) DEFAULT NULL,
  `status` enum('open','closed') NOT NULL DEFAULT 'open',
  `webhook_data` text DEFAULT NULL,
  `position_id` varchar(50) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `closed_at` datetime DEFAULT NULL,
  `source` varchar(50) DEFAULT 'bingx',
  `user_signal_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `trades`
--

INSERT INTO `trades` (`id`, `user_id`, `strategy_id`, `order_id`, `symbol`, `timeframe`, `side`, `trade_type`, `mt_signal_id`, `environment`, `quantity`, `entry_price`, `current_price`, `leverage`, `take_profit`, `stop_loss`, `exit_price`, `pnl`, `status`, `webhook_data`, `position_id`, `created_at`, `updated_at`, `closed_at`, `source`, `user_signal_id`) VALUES
(36, 1, 4, '1965305812029018112', 'BTCUSDT', '60', 'BUY', 'futures', NULL, 'production', '0.00708000', '112889.40000000', '112889.40000000', 8, NULL, NULL, '112480.20000000', '-2.89713600', 'closed', '{\"user_id\":1,\"strategy_id\":\"FUT_BTC_H1_GANN\",\"ticker\":\"BTCUSDT\",\"timeframe\":\"60\",\"action\":\"BUY\",\"quantity\":\"0.00708\",\"position_id\":\"1757372400\",\"leverage\":8,\"environment\":\"production\"}', '1757372400', '2025-09-09 03:46:34', '2025-09-09 08:58:45', '2025-09-09 08:58:45', 'bingx', NULL),
(37, 1, 6, '1965686709358170112', 'ETHUSDT', '60', 'BUY', 'futures', NULL, 'production', '0.18470000', '4328.41000000', '4391.35000000', 8, NULL, NULL, '4403.56000000', '13.88020500', 'closed', '{\"user_id\":1,\"strategy_id\":\"FUT_ETH_H1_URSI_TREND\",\"ticker\":\"ETHUSDT\",\"timeframe\":\"60\",\"action\":\"BUY\",\"quantity\":\"0.1847\",\"position_id\":\"1757487600\",\"leverage\":8,\"environment\":\"production\"}', '1757487600', '2025-09-10 05:00:07', '2025-09-11 01:00:12', '2025-09-11 01:00:12', 'bingx', NULL),
(38, 1, 11, '1965754733255725056', 'BTCUSDT', '60', 'BUY', 'futures', NULL, 'production', '0.01765000', '113339.40000000', '114027.80000000', 8, NULL, NULL, '114356.70000000', '17.95534500', 'closed', '{\"user_id\":1,\"strategy_id\":\"FUT_BTC_H1_TEMA_ST_BELOW_MA\",\"ticker\":\"BTCUSDT\",\"timeframe\":\"60\",\"action\":\"BUY\",\"quantity\":\"0.01765\",\"position_id\":\"1757444400\",\"leverage\":8,\"environment\":\"production\"}', '1757444400', '2025-09-10 09:30:25', '2025-09-11 01:00:06', '2025-09-11 01:00:06', 'bingx', NULL),
(39, 1, 2, '1965928299553099776', 'BTCUSDT', '60', 'BUY', 'futures', NULL, 'production', '0.00702000', '113882.70000000', '114437.60000000', 8, NULL, NULL, '114970.60000000', '7.63705800', 'closed', '{\"user_id\":1,\"strategy_id\":\"FUT_BTC_H1_TREND_ALIGNED_RSI\",\"ticker\":\"BTCUSDT\",\"timeframe\":\"60\",\"action\":\"BUY\",\"quantity\":\"0.00702\",\"position_id\":\"1757545200\",\"leverage\":8,\"environment\":\"production\"}', '1757545200', '2025-09-10 21:00:07', '2025-09-12 05:06:41', '2025-09-12 05:06:41', 'bingx', NULL),
(40, 1, 6, '1966018902689320960', 'ETHUSDT', '60', 'BUY', 'futures', NULL, 'production', '0.18100000', '4416.49000000', '4419.86000000', 8, NULL, NULL, '4428.78000000', '2.22449000', 'closed', '{\"user_id\":1,\"strategy_id\":\"FUT_ETH_H1_URSI_TREND\",\"ticker\":\"ETHUSDT\",\"timeframe\":\"60\",\"action\":\"BUY\",\"quantity\":\"0.181\",\"position_id\":\"1757566800\",\"leverage\":8,\"environment\":\"production\"}', '1757566800', '2025-09-11 03:00:08', '2025-09-11 19:00:07', '2025-09-11 19:00:07', 'bingx', NULL),
(41, 1, 8, '1966112825847844864', 'BTCUSDT', '60', 'BUY', 'futures', NULL, 'production', '0.01747000', '114443.20000000', '114437.60000000', 16, NULL, NULL, '115137.50000000', '12.12942100', 'closed', '{\"user_id\":1,\"strategy_id\":\"FUT_BTC_H1_TEMA_ST_OVER_MA\",\"ticker\":\"BTCUSDT\",\"timeframe\":\"60\",\"action\":\"BUY\",\"quantity\":\"0.01747\",\"position_id\":\"1757584800\",\"leverage\":16,\"environment\":\"production\"}', '1757584800', '2025-09-11 09:13:21', '2025-09-12 01:00:10', '2025-09-12 01:00:10', 'bingx', NULL),
(42, 1, 9, '1966301825971785729', 'ETHUSDT', '60', 'BUY', 'futures', NULL, 'production', '0.17850000', '4489.22000000', '4489.22000000', 16, NULL, NULL, '4554.95000000', '11.73280500', 'closed', '{\"user_id\":1,\"strategy_id\":\"FUT_ETH_H1_TEMA_SUPERTREND\",\"ticker\":\"ETHUSDT\",\"timeframe\":\"60\",\"action\":\"BUY\",\"quantity\":\"0.1785\",\"position_id\":\"1757620800\",\"leverage\":16,\"environment\":\"production\"}', '1757620800', '2025-09-11 21:44:22', '2025-09-12 03:00:08', '2025-09-12 03:00:08', 'bingx', NULL),
(43, 1, 6, '1966305792416026624', 'ETHUSDT', '60', 'BUY', 'futures', NULL, 'production', '0.17730000', '4514.71000000', '4514.71000000', 8, NULL, NULL, '4651.75000000', '24.29719200', 'closed', '{\"user_id\":1,\"strategy_id\":\"FUT_ETH_H1_URSI_TREND\",\"ticker\":\"ETHUSDT\",\"timeframe\":\"60\",\"action\":\"BUY\",\"quantity\":\"0.1773\",\"position_id\":\"1757635200\",\"leverage\":8,\"environment\":\"production\"}', '1757635200', '2025-09-11 22:00:08', '2025-09-13 13:00:05', '2025-09-13 13:00:05', 'bingx', NULL),
(44, 1, 10, '1966504482128793600', 'ETHUSDT', '60', 'BUY', 'futures', NULL, 'production', '0.17530000', '4562.76000000', '4562.76000000', 8, NULL, NULL, '4654.08000000', '16.00839600', 'closed', '{\"user_id\":1,\"strategy_id\":\"FUT_ETH_H1_GANN\",\"ticker\":\"ETHUSDT\",\"timeframe\":\"60\",\"action\":\"BUY\",\"quantity\":\"0.1753\",\"position_id\":\"1757678400\",\"leverage\":8,\"environment\":\"production\"}', '1757678400', '2025-09-12 11:09:40', '2025-09-12 17:00:17', '2025-09-12 17:00:17', 'bingx', NULL),
(45, 1, 4, '1966570098558767104', 'BTCUSDT', '60', 'BUY', 'futures', NULL, 'production', '0.00687000', '116292.50000000', '116292.50000000', 8, NULL, NULL, '115797.70000000', '-3.39927600', 'closed', '{\"user_id\":1,\"strategy_id\":\"FUT_BTC_H1_GANN\",\"ticker\":\"BTCUSDT\",\"timeframe\":\"60\",\"action\":\"BUY\",\"quantity\":\"0.00687\",\"position_id\":\"1757674800\",\"leverage\":8,\"environment\":\"production\"}', '1757674800', '2025-09-12 15:30:24', '2025-09-12 18:44:42', '2025-09-12 18:44:42', 'bingx', NULL),
(46, 1, 8, '1966570099435376640', 'BTCUSDT', '60', 'BUY', 'futures', NULL, 'production', '0.01719000', '116292.50000000', '116292.50000000', 16, NULL, NULL, '115979.90000000', '-5.37359400', 'closed', '{\"user_id\":1,\"strategy_id\":\"FUT_BTC_H1_TEMA_ST_OVER_MA\",\"ticker\":\"BTCUSDT\",\"timeframe\":\"60\",\"action\":\"BUY\",\"quantity\":\"0.01719\",\"position_id\":\"1757667600\",\"leverage\":16,\"environment\":\"production\"}', '1757667600', '2025-09-12 15:30:24', '2025-09-13 07:00:05', '2025-09-13 07:00:05', 'bingx', NULL),
(47, 1, 2, '1966577581427789824', 'BTCUSDT', '60', 'BUY', 'futures', NULL, 'production', '0.00686000', '116429.80000000', '116429.80000000', 8, NULL, NULL, '115613.20000000', '-5.60187600', 'closed', '{\"user_id\":1,\"strategy_id\":\"FUT_BTC_H1_TREND_ALIGNED_RSI\",\"ticker\":\"BTCUSDT\",\"timeframe\":\"60\",\"action\":\"BUY\",\"quantity\":\"0.00686\",\"position_id\":\"1757700000\",\"leverage\":8,\"environment\":\"production\"}', '1757700000', '2025-09-12 16:00:07', '2025-09-12 22:54:48', '2025-09-12 22:54:48', 'bingx', NULL),
(48, 1, 8, '1967443917657870336', 'BTCUSDT', '60', 'BUY', 'futures', NULL, 'production', '0.01721000', '116069.80000000', '116069.80000000', 16, NULL, NULL, '115335.30000000', '-12.64074500', 'closed', '{\"user_id\":1,\"strategy_id\":\"FUT_BTC_H1_TEMA_ST_OVER_MA\",\"ticker\":\"BTCUSDT\",\"timeframe\":\"60\",\"action\":\"BUY\",\"quantity\":\"0.01721\",\"position_id\":\"1757872800\",\"leverage\":16,\"environment\":\"production\"}', '1757872800', '2025-09-15 01:22:38', '2025-09-15 05:40:19', '2025-09-15 05:40:19', 'bingx', NULL),
(49, 1, 11, '1967443918467371008', 'BTCUSDT', '60', 'BUY', 'futures', NULL, 'production', '0.01721000', '116067.60000000', '116067.60000000', 8, NULL, NULL, '115335.10000000', '-12.60632500', 'closed', '{\"user_id\":1,\"strategy_id\":\"FUT_BTC_H1_TEMA_ST_BELOW_MA\",\"ticker\":\"BTCUSDT\",\"timeframe\":\"60\",\"action\":\"BUY\",\"quantity\":\"0.01721\",\"position_id\":\"1757872800\",\"leverage\":8,\"environment\":\"production\"}', '1757872800', '2025-09-15 01:22:38', '2025-09-15 05:40:19', '2025-09-15 05:40:19', 'bingx', NULL),
(50, 1, 6, '1967468461852463104', 'ETHUSDT', '60', 'BUY', 'futures', NULL, 'production', '0.17150000', '4661.75000000', '4661.75000000', 8, NULL, NULL, '4503.09000000', '-27.21019000', 'closed', '{\"user_id\":1,\"strategy_id\":\"FUT_ETH_H1_URSI_TREND\",\"ticker\":\"ETHUSDT\",\"timeframe\":\"60\",\"action\":\"BUY\",\"quantity\":\"0.1715\",\"position_id\":\"1757912400\",\"leverage\":8,\"environment\":\"production\"}', '1757912400', '2025-09-15 03:00:10', '2025-09-15 06:00:20', '2025-09-15 06:00:20', 'bingx', NULL),
(51, 1, 4, '1967470857089454080', 'BTCUSDT', '60', 'BUY', 'futures', NULL, 'production', '0.00685000', '116641.70000000', '116641.70000000', 8, NULL, NULL, '116069.60000000', '-3.91888500', 'closed', '{\"user_id\":1,\"strategy_id\":\"FUT_BTC_H1_GANN\",\"ticker\":\"BTCUSDT\",\"timeframe\":\"60\",\"action\":\"BUY\",\"quantity\":\"0.00685\",\"position_id\":\"1757829600\",\"leverage\":8,\"environment\":\"production\"}', '1757829600', '2025-09-15 03:09:41', '2025-09-15 04:00:12', '2025-09-15 04:00:12', 'bingx', NULL),
(52, 1, 4, '1967996643785576448', 'BTCUSDT', '60', 'BUY', 'futures', NULL, 'production', '0.00689000', '115979.80000000', '116784.10000000', 8, NULL, NULL, '116799.80000000', '5.64980000', 'closed', '{\"user_id\":1,\"strategy_id\":\"FUT_BTC_H1_GANN\",\"ticker\":\"BTCUSDT\",\"timeframe\":\"60\",\"action\":\"BUY\",\"quantity\":\"0.00689\",\"position_id\":\"1758031200\",\"leverage\":8,\"environment\":\"production\"}', '1758031200', '2025-09-16 13:58:58', '2025-09-16 19:00:08', '2025-09-16 19:00:08', 'bingx', NULL),
(53, 1, 2, '1968057324509597696', 'BTCUSDT', '60', 'BUY', 'futures', NULL, 'production', '0.00684000', '116771.90000000', '116855.50000000', 8, NULL, NULL, '116259.30000000', '-3.50618400', 'closed', '{\"user_id\":1,\"strategy_id\":\"FUT_BTC_H1_TREND_ALIGNED_RSI\",\"ticker\":\"BTCUSDT\",\"timeframe\":\"60\",\"action\":\"BUY\",\"quantity\":\"0.00684\",\"position_id\":\"1758052800\",\"leverage\":8,\"environment\":\"production\"}', '1758052800', '2025-09-16 18:00:06', '2025-09-17 02:00:05', '2025-09-17 02:00:05', 'bingx', NULL),
(54, 1, 6, '1968178134092091392', 'ETHUSDT', '60', 'BUY', 'futures', NULL, 'production', '0.17860000', '4474.58000000', '4495.10000000', 8, NULL, NULL, '4485.13000000', '1.88423000', 'closed', '{\"user_id\":1,\"strategy_id\":\"FUT_ETH_H1_URSI_TREND\",\"ticker\":\"ETHUSDT\",\"timeframe\":\"60\",\"action\":\"BUY\",\"quantity\":\"0.1786\",\"position_id\":\"1758081600\",\"leverage\":8,\"environment\":\"production\"}', '1758081600', '2025-09-17 02:00:09', '2025-09-17 14:00:07', '2025-09-17 14:00:07', 'bingx', NULL),
(55, 1, 10, '1968206529756663808', 'ETHUSDT', '60', 'BUY', 'futures', NULL, 'production', '0.17570000', '4545.87000000', '4545.87000000', 8, NULL, NULL, '4509.36000000', '-6.41480700', 'closed', '{\"user_id\":1,\"strategy_id\":\"FUT_ETH_H1_GANN\",\"ticker\":\"ETHUSDT\",\"timeframe\":\"60\",\"action\":\"BUY\",\"quantity\":\"0.1757\",\"position_id\":\"1758085200\",\"leverage\":8,\"environment\":\"production\"}', '1758085200', '2025-09-17 03:52:59', '2025-09-17 05:29:19', '2025-09-17 05:29:19', 'bingx', NULL),
(56, 1, 9, '1968441524572131328', 'ETHUSDT', '60', 'BUY', 'futures', NULL, 'production', '0.17550000', '4560.76000000', '4612.16000000', 16, NULL, NULL, '4613.91000000', '9.32782500', 'closed', '{\"user_id\":1,\"strategy_id\":\"FUT_ETH_H1_TEMA_SUPERTREND\",\"ticker\":\"ETHUSDT\",\"timeframe\":\"60\",\"action\":\"BUY\",\"quantity\":\"0.1755\",\"position_id\":\"1758106800\",\"leverage\":16,\"environment\":\"production\"}', '1758106800', '2025-09-17 19:26:46', '2025-09-18 01:00:09', '2025-09-18 01:00:09', 'bingx', NULL),
(57, 1, 6, '1968465879490367488', 'ETHUSDT', '60', 'BUY', 'futures', NULL, 'production', '0.17420000', '4591.58000000', '4570.50000000', 8, NULL, NULL, '4597.92000000', '1.10442800', 'closed', '{\"user_id\":1,\"strategy_id\":\"FUT_ETH_H1_URSI_TREND\",\"ticker\":\"ETHUSDT\",\"timeframe\":\"60\",\"action\":\"BUY\",\"quantity\":\"0.1742\",\"position_id\":\"1758150000\",\"leverage\":8,\"environment\":\"production\"}', '1758150000', '2025-09-17 21:03:33', '2025-09-18 13:00:13', '2025-09-18 13:00:13', 'bingx', NULL),
(58, 1, 11, '1968512137294778368', 'BTCUSDT', '60', 'BUY', 'futures', NULL, 'production', '0.01659000', '117300.00000000', '117071.80000000', 8, NULL, NULL, '117741.40000000', '7.32282600', 'closed', '{\"user_id\":1,\"strategy_id\":\"FUT_BTC_H1_TEMA_ST_BELOW_MA\",\"ticker\":\"BTCUSDT\",\"timeframe\":\"60\",\"action\":\"BUY\",\"quantity\":\"0.01659\",\"position_id\":\"1758135600\",\"leverage\":8,\"environment\":\"production\"}', '1758135600', '2025-09-18 00:07:21', '2025-09-18 16:00:27', '2025-09-18 16:00:27', 'bingx', NULL),
(59, 1, 4, '1968512137940701184', 'BTCUSDT', '60', 'BUY', 'futures', NULL, 'production', '0.00682000', '117300.00000000', '117705.40000000', 8, NULL, NULL, '117178.60000000', '-0.82794800', 'closed', '{\"user_id\":1,\"strategy_id\":\"FUT_BTC_H1_GANN\",\"ticker\":\"BTCUSDT\",\"timeframe\":\"60\",\"action\":\"BUY\",\"quantity\":\"0.00682\",\"position_id\":\"1758110400\",\"leverage\":8,\"environment\":\"production\"}', '1758110400', '2025-09-18 00:07:22', '2025-09-18 06:00:07', '2025-09-18 06:00:07', 'bingx', NULL),
(60, 1, 8, '1968512138683092992', 'BTCUSDT', '60', 'BUY', 'futures', NULL, 'production', '0.01705000', '117321.80000000', '117071.80000000', 16, NULL, NULL, '117770.60000000', '7.65204000', 'closed', '{\"user_id\":1,\"strategy_id\":\"FUT_BTC_H1_TEMA_ST_OVER_MA\",\"ticker\":\"BTCUSDT\",\"timeframe\":\"60\",\"action\":\"BUY\",\"quantity\":\"0.01705\",\"position_id\":\"1758106800\",\"leverage\":16,\"environment\":\"production\"}', '1758106800', '2025-09-18 00:07:22', '2025-09-18 16:00:10', '2025-09-18 16:00:10', 'bingx', NULL),
(61, 1, 6, '1969340776538181632', 'ETHUSDT', '60', 'BUY', 'futures', NULL, 'production', '0.17910000', '4464.95000000', '4475.62000000', 8, NULL, NULL, '4469.80000000', '0.86863500', 'closed', '{\"user_id\":1,\"strategy_id\":\"FUT_ETH_H1_URSI_TREND\",\"ticker\":\"ETHUSDT\",\"timeframe\":\"60\",\"action\":\"BUY\",\"quantity\":\"0.1791\",\"position_id\":\"1758358800\",\"leverage\":8,\"environment\":\"production\"}', '1758358800', '2025-09-20 07:00:04', '2025-09-21 02:00:05', '2025-09-21 02:00:05', 'bingx', NULL),
(62, 1, 4, '1970861466156273664', 'BTCUSDT', '60', 'BUY', 'futures', NULL, 'production', '0.00706000', '113264.70000000', '113264.70000000', 8, NULL, NULL, '113375.40000000', '0.78154200', 'closed', '{\"user_id\":1,\"strategy_id\":\"FUT_BTC_H1_GANN\",\"ticker\":\"BTCUSDT\",\"timeframe\":\"60\",\"action\":\"BUY\",\"quantity\":\"0.00706\",\"position_id\":\"1758650400\",\"leverage\":8,\"environment\":\"production\"}', '1758650400', '2025-09-24 11:42:45', '2025-09-24 17:00:09', '2025-09-24 17:00:09', 'bingx', NULL),
(63, 1, 4, '1972332389581459456', 'BTCUSDT', '60', 'BUY', 'futures', NULL, 'production', '0.00725000', '110257.70000000', '110257.70000000', 8, NULL, NULL, '110840.00000000', '4.22167500', 'closed', '{\"user_id\":1,\"strategy_id\":\"FUT_BTC_H1_GANN\",\"ticker\":\"BTCUSDT\",\"timeframe\":\"60\",\"action\":\"BUY\",\"quantity\":\"0.00725\",\"position_id\":\"1759032000\",\"leverage\":8,\"environment\":\"production\"}', '1759032000', '2025-09-28 13:07:41', '2025-09-28 19:00:13', '2025-09-28 19:00:13', 'bingx', NULL),
(64, 1, 10, '1972422506958557184', 'ETHUSDT', '60', 'BUY', 'futures', NULL, 'production', '0.19660000', '4080.00000000', '4080.00000000', 8, NULL, NULL, '4119.74000000', '7.81288400', 'closed', '{\"user_id\":1,\"strategy_id\":\"FUT_ETH_H1_GANN\",\"ticker\":\"ETHUSDT\",\"timeframe\":\"60\",\"action\":\"BUY\",\"quantity\":\"0.1966\",\"position_id\":\"1759028400\",\"leverage\":8,\"environment\":\"production\"}', '1759028400', '2025-09-28 19:05:46', '2025-09-29 01:00:07', '2025-09-29 01:00:07', 'bingx', NULL),
(65, 1, 9, '1973307569569009664', 'ETHUSDT', '60', 'BUY', 'futures', NULL, 'production', '0.18840000', '4247.11000000', '4247.11000000', 16, NULL, NULL, '4292.98000000', '8.64190800', 'closed', '{\"user_id\":1,\"strategy_id\":\"FUT_ETH_H1_TEMA_SUPERTREND\",\"ticker\":\"ETHUSDT\",\"timeframe\":\"60\",\"action\":\"BUY\",\"quantity\":\"0.1884\",\"position_id\":\"1759212000\",\"leverage\":16,\"environment\":\"production\"}', '1759212000', '2025-10-01 05:42:42', '2025-10-01 11:00:15', '2025-10-01 11:00:15', 'bingx', NULL),
(66, 1, 6, '1973311950511149056', 'ETHUSDT', '60', 'BUY', 'futures', NULL, 'production', '0.18640000', '4293.68000000', '4370.86000000', 8, NULL, NULL, '4370.86000000', '14.38635200', 'closed', '{\"user_id\":1,\"strategy_id\":\"FUT_ETH_H1_URSI_TREND\",\"ticker\":\"ETHUSDT\",\"timeframe\":\"60\",\"action\":\"BUY\",\"quantity\":\"0.1864\",\"position_id\":\"1759305600\",\"leverage\":8,\"environment\":\"production\"}', '1759305600', '2025-10-01 06:00:06', '2025-10-01 23:09:51', '2025-10-01 23:09:51', 'bingx', NULL),
(67, 1, 2, '1973311992718430208', 'BTCUSDT', '60', 'BUY', 'futures', NULL, 'production', '0.00688000', '116078.90000000', '119075.10000000', 8, NULL, NULL, '119074.90000000', '20.61248000', 'closed', '{\"user_id\":1,\"strategy_id\":\"FUT_BTC_H1_TREND_ALIGNED_RSI\",\"ticker\":\"BTCUSDT\",\"timeframe\":\"60\",\"action\":\"BUY\",\"quantity\":\"0.00688\",\"position_id\":\"1759305600\",\"leverage\":8,\"environment\":\"production\"}', '1759305600', '2025-10-01 06:00:16', '2025-10-02 09:36:40', '2025-10-02 09:36:40', 'bingx', NULL),
(68, 1, 10, '1973946919268913152', 'ETHUSDT', '60', 'BUY', 'futures', NULL, 'production', '0.17710000', '4522.79000000', '4522.79000000', 8, NULL, NULL, '4469.73000000', '-9.39692600', 'closed', '{\"user_id\":1,\"strategy_id\":\"FUT_ETH_H1_GANN\",\"ticker\":\"ETHUSDT\",\"timeframe\":\"60\",\"action\":\"BUY\",\"quantity\":\"0.1771\",\"position_id\":\"1759453200\",\"leverage\":8,\"environment\":\"production\"}', '1759453200', '2025-10-03 00:03:14', '2025-10-03 02:46:45', '2025-10-03 02:46:45', 'bingx', NULL),
(69, 1, 2, '1973988214393802752', 'BTCUSDT', '60', 'SELL', 'futures', NULL, 'production', '0.00688000', '119794.00000000', '121726.70000000', 8, NULL, NULL, '121726.70000000', '-13.29697600', 'closed', '{\"user_id\":1,\"strategy_id\":\"FUT_BTC_H1_TREND_ALIGNED_RSI\",\"ticker\":\"BTCUSDT\",\"timeframe\":\"60\",\"action\":\"SELL\",\"quantity\":\"0.00688\",\"position_id\":\"1759305600\",\"leverage\":8,\"environment\":\"production\"}', '1759305600', '2025-10-03 02:47:20', '2025-10-03 23:41:54', '2025-10-03 23:41:54', 'bingx', NULL),
(70, 1, 6, '1974051825166323712', 'ETHUSDT', '60', 'SELL', 'futures', NULL, 'production', '0.18640000', '4471.35000000', '4473.80000000', 8, NULL, NULL, '4473.80000000', '-0.45668000', 'closed', '{\"user_id\":1,\"strategy_id\":\"FUT_ETH_H1_URSI_TREND\",\"ticker\":\"ETHUSDT\",\"timeframe\":\"60\",\"action\":\"SELL\",\"quantity\":\"0.1864\",\"position_id\":\"1759305600\",\"leverage\":8,\"environment\":\"production\"}', '1759305600', '2025-10-03 07:00:06', '2025-10-03 23:41:45', '2025-10-03 23:41:45', 'bingx', NULL),
(71, 1, 8, '1974131840084086784', 'BTCUSDT', '60', 'BUY', 'futures', NULL, 'production', '0.01452000', '120992.80000000', '120992.80000000', 16, NULL, NULL, '121835.30000000', '12.23310000', 'closed', '{\"user_id\":1,\"strategy_id\":\"FUT_BTC_H1_TEMA_ST_OVER_MA\",\"ticker\":\"BTCUSDT\",\"timeframe\":\"60\",\"action\":\"BUY\",\"quantity\":\"0.01452\",\"position_id\":\"1759456800\",\"leverage\":16,\"environment\":\"production\"}', '1759456800', '2025-10-03 12:18:03', '2025-10-03 23:00:04', '2025-10-03 23:00:04', 'bingx', NULL),
(72, 1, 4, '1974131842659389440', 'BTCUSDT', '60', 'BUY', 'futures', NULL, 'production', '0.00661000', '120992.80000000', '120992.80000000', 8, NULL, NULL, '122378.30000000', '9.15815500', 'closed', '{\"user_id\":1,\"strategy_id\":\"FUT_BTC_H1_GANN\",\"ticker\":\"BTCUSDT\",\"timeframe\":\"60\",\"action\":\"BUY\",\"quantity\":\"0.00661\",\"position_id\":\"1759467600\",\"leverage\":8,\"environment\":\"production\"}', '1759467600', '2025-10-03 12:18:04', '2025-10-03 18:00:06', '2025-10-03 18:00:06', 'bingx', NULL),
(73, 1, 2, '1974142451094392832', 'BTCUSDT', '60', 'BUY', 'futures', NULL, 'production', '0.00654000', '122364.00000000', '122455.20000000', 8, NULL, NULL, '121928.20000000', '-2.85013200', 'closed', '{\"user_id\":1,\"strategy_id\":\"FUT_BTC_H1_TREND_ALIGNED_RSI\",\"ticker\":\"BTCUSDT\",\"timeframe\":\"60\",\"action\":\"BUY\",\"quantity\":\"0.00654\",\"position_id\":\"1759503600\",\"leverage\":8,\"environment\":\"production\"}', '1759503600', '2025-10-03 13:00:13', '2025-10-04 09:00:04', '2025-10-04 09:00:04', 'bingx', NULL),
(74, 1, 9, '1974151247107723264', 'ETHUSDT', '60', 'BUY', 'futures', NULL, 'production', '0.17540000', '4563.34000000', '4563.34000000', 16, NULL, NULL, '4513.38000000', '-8.76298400', 'closed', '{\"user_id\":1,\"strategy_id\":\"FUT_ETH_H1_TEMA_SUPERTREND\",\"ticker\":\"ETHUSDT\",\"timeframe\":\"60\",\"action\":\"BUY\",\"quantity\":\"0.1754\",\"position_id\":\"1759474800\",\"leverage\":16,\"environment\":\"production\"}', '1759474800', '2025-10-03 13:35:10', '2025-10-03 14:00:18', '2025-10-03 14:00:18', 'bingx', NULL),
(75, 1, 6, '1974233012799279104', 'ETHUSDT', '60', 'BUY', 'futures', NULL, 'production', '0.17680000', '4525.06000000', '4493.70000000', 8, NULL, NULL, '4501.62000000', '-4.14419200', 'closed', '{\"user_id\":1,\"strategy_id\":\"FUT_ETH_H1_URSI_TREND\",\"ticker\":\"ETHUSDT\",\"timeframe\":\"60\",\"action\":\"BUY\",\"quantity\":\"0.1768\",\"position_id\":\"1759525200\",\"leverage\":8,\"environment\":\"production\"}', '1759525200', '2025-10-03 19:00:05', '2025-10-04 03:00:05', '2025-10-04 03:00:05', 'bingx', NULL),
(76, 1, 8, '1974664333216452608', 'BTCUSDT', '60', 'BUY', 'futures', NULL, 'production', '0.01518000', '123966.10000000', '123966.10000000', 16, NULL, NULL, '122547.00000000', '-21.54193800', 'closed', '{\"user_id\":1,\"strategy_id\":\"FUT_BTC_H1_TEMA_ST_OVER_MA\",\"ticker\":\"BTCUSDT\",\"timeframe\":\"60\",\"action\":\"BUY\",\"quantity\":\"0.01518\",\"position_id\":\"1759572000\",\"leverage\":16,\"environment\":\"production\"}', '1759572000', '2025-10-04 23:33:59', '2025-10-05 07:10:55', '2025-10-05 07:10:54', 'bingx', NULL),
(77, 1, 4, '1974664333862375424', 'BTCUSDT', '60', 'BUY', 'futures', NULL, 'production', '0.00645000', '123938.20000000', '123938.20000000', 8, NULL, NULL, '124679.10000000', '4.77880500', 'closed', '{\"user_id\":1,\"strategy_id\":\"FUT_BTC_H1_GANN\",\"ticker\":\"BTCUSDT\",\"timeframe\":\"60\",\"action\":\"BUY\",\"quantity\":\"0.00645\",\"position_id\":\"1759586400\",\"leverage\":8,\"environment\":\"production\"}', '1759586400', '2025-10-04 23:34:00', '2025-10-05 05:00:07', '2025-10-05 05:00:07', 'bingx', NULL),
(78, 1, 2, '1974670908702330880', 'BTCUSDT', '60', 'BUY', 'futures', NULL, 'production', '0.00644000', '124047.40000000', '124047.40000000', 8, NULL, NULL, '123024.00000000', '-6.59069600', 'closed', '{\"user_id\":1,\"strategy_id\":\"FUT_BTC_H1_TREND_ALIGNED_RSI\",\"ticker\":\"BTCUSDT\",\"timeframe\":\"60\",\"action\":\"BUY\",\"quantity\":\"0.00644\",\"position_id\":\"1759629600\",\"leverage\":8,\"environment\":\"production\"}', '1759629600', '2025-10-05 00:00:07', '2025-10-05 07:00:05', '2025-10-05 07:00:05', 'bingx', NULL),
(79, 1, 6, '1974716196649439232', 'ETHUSDT', '60', 'BUY', 'futures', NULL, 'production', '0.17500000', '4568.38000000', '4535.90000000', 8, NULL, NULL, '4527.07000000', '-7.22925000', 'closed', '{\"user_id\":1,\"strategy_id\":\"FUT_ETH_H1_URSI_TREND\",\"ticker\":\"ETHUSDT\",\"timeframe\":\"60\",\"action\":\"BUY\",\"quantity\":\"0.175\",\"position_id\":\"1759640400\",\"leverage\":8,\"environment\":\"production\"}', '1759640400', '2025-10-05 03:00:05', '2025-10-05 14:00:05', '2025-10-05 14:00:05', 'bingx', NULL),
(80, 1, 6, '1975138993100034048', 'ETHUSDT', '60', 'BUY', 'futures', NULL, 'production', '0.17490000', '4572.41000000', '4682.07000000', 8, NULL, NULL, '4678.31000000', '18.52191000', 'closed', '{\"user_id\":1,\"strategy_id\":\"FUT_ETH_H1_URSI_TREND\",\"ticker\":\"ETHUSDT\",\"timeframe\":\"60\",\"action\":\"BUY\",\"quantity\":\"0.1749\",\"position_id\":\"1759741200\",\"leverage\":8,\"environment\":\"production\"}', '1759741200', '2025-10-06 07:00:07', '2025-10-07 07:00:07', '2025-10-07 07:00:07', 'bingx', NULL),
(81, 1, 2, '1975274899463213056', 'BTCUSDT', '60', 'BUY', 'futures', NULL, 'production', '0.00634000', '125989.70000000', '125097.20000000', 8, NULL, NULL, '124586.70000000', '-8.89502000', 'closed', '{\"user_id\":1,\"strategy_id\":\"FUT_BTC_H1_TREND_ALIGNED_RSI\",\"ticker\":\"BTCUSDT\",\"timeframe\":\"60\",\"action\":\"BUY\",\"quantity\":\"0.00634\",\"position_id\":\"1759773600\",\"leverage\":8,\"environment\":\"production\"}', '1759773600', '2025-10-06 16:00:10', '2025-10-06 21:00:16', '2025-10-06 21:00:16', 'bingx', NULL),
(82, 1, 9, '1975545701953703936', 'ETHUSDT', '60', 'BUY', 'futures', NULL, 'production', '0.16890000', '4740.58000000', '4740.58000000', 16, NULL, NULL, '4698.34000000', '-7.13433600', 'closed', '{\"user_id\":1,\"strategy_id\":\"FUT_ETH_H1_TEMA_SUPERTREND\",\"ticker\":\"ETHUSDT\",\"timeframe\":\"60\",\"action\":\"BUY\",\"quantity\":\"0.1689\",\"position_id\":\"1759824000\",\"leverage\":16,\"environment\":\"production\"}', '1759824000', '2025-10-07 09:56:14', '2025-10-07 10:42:25', '2025-10-07 10:42:25', 'bingx', NULL),
(83, 1, 10, '1975545706085093376', 'ETHUSDT', '60', 'BUY', 'futures', NULL, 'production', '0.16890000', '4745.26000000', '4745.26000000', 8, NULL, NULL, '4698.30000000', '-7.93154400', 'closed', '{\"user_id\":1,\"strategy_id\":\"FUT_ETH_H1_GANN\",\"ticker\":\"ETHUSDT\",\"timeframe\":\"60\",\"action\":\"BUY\",\"quantity\":\"0.1689\",\"position_id\":\"1759806000\",\"leverage\":8,\"environment\":\"production\"}', '1759806000', '2025-10-07 09:56:15', '2025-10-07 10:42:25', '2025-10-07 10:42:25', 'bingx', NULL),
(84, 1, 6, '1976014765947883520', 'ETHUSDT', '60', 'BUY', 'futures', NULL, 'production', '0.17710000', '4513.66000000', '4513.66000000', 8, NULL, NULL, '4476.42000000', '-6.59520400', 'closed', '{\"user_id\":1,\"strategy_id\":\"FUT_ETH_H1_URSI_TREND\",\"ticker\":\"ETHUSDT\",\"timeframe\":\"60\",\"action\":\"BUY\",\"quantity\":\"0.1771\",\"position_id\":\"1759950000\",\"leverage\":8,\"environment\":\"production\"}', '1759950000', '2025-10-08 17:00:08', '2025-10-08 23:00:06', '2025-10-08 23:00:06', 'bingx', NULL),
(85, 1, 10, '1977796240607285248', 'ETHUSDT', '60', 'BUY', 'futures', NULL, 'production', '0.18950000', '4230.17000000', '4230.17000000', 8, NULL, NULL, '4267.24000000', '7.02476500', 'closed', '{\"user_id\":1,\"strategy_id\":\"FUT_ETH_H1_GANN\",\"ticker\":\"ETHUSDT\",\"timeframe\":\"60\",\"action\":\"BUY\",\"quantity\":\"0.1895\",\"position_id\":\"1760360400\",\"leverage\":8,\"environment\":\"production\"}', '1760360400', '2025-10-13 14:59:04', '2025-10-13 20:00:07', '2025-10-13 20:00:07', 'bingx', NULL),
(86, 1, 9, '1977796246227652608', 'ETHUSDT', '60', 'BUY', 'futures', NULL, 'production', '0.18950000', '4228.16000000', '4228.16000000', 16, NULL, NULL, '4267.24000000', '7.40566000', 'closed', '{\"user_id\":1,\"strategy_id\":\"FUT_ETH_H1_TEMA_SUPERTREND\",\"ticker\":\"ETHUSDT\",\"timeframe\":\"60\",\"action\":\"BUY\",\"quantity\":\"0.1895\",\"position_id\":\"1760356800\",\"leverage\":16,\"environment\":\"production\"}', '1760356800', '2025-10-13 14:59:06', '2025-10-13 20:00:07', '2025-10-13 20:00:07', 'bingx', NULL),
(87, 1, 6, '1977841796046131200', 'ETHUSDT', '60', 'BUY', 'futures', NULL, 'production', '0.18660000', '4287.11000000', '4287.11000000', 8, NULL, NULL, '4067.97000000', '-40.89152400', 'closed', '{\"user_id\":1,\"strategy_id\":\"FUT_ETH_H1_URSI_TREND\",\"ticker\":\"ETHUSDT\",\"timeframe\":\"60\",\"action\":\"BUY\",\"quantity\":\"0.1866\",\"position_id\":\"1760385600\",\"leverage\":8,\"environment\":\"production\"}', '1760385600', '2025-10-13 18:00:06', '2025-10-14 03:00:11', '2025-10-14 03:00:11', 'bingx', NULL),
(88, 1, 9, '1979824690243309568', 'ETHUSDT', '60', 'BUY', 'futures', NULL, 'production', '0.20360000', '3942.51000000', '3942.51000000', 16, NULL, NULL, '3881.50000000', '-12.42163600', 'closed', '{\"user_id\":1,\"strategy_id\":\"FUT_ETH_H1_TEMA_SUPERTREND\",\"ticker\":\"ETHUSDT\",\"timeframe\":\"60\",\"action\":\"BUY\",\"quantity\":\"0.2036\",\"position_id\":\"1760839200\",\"leverage\":16,\"environment\":\"production\"}', '1760839200', '2025-10-19 05:19:24', '2025-10-19 05:22:46', '2025-10-19 05:22:46', 'bingx', NULL),
(89, 1, 11, '1979847207175589888', 'BTCUSDT', '60', 'BUY', 'futures', NULL, 'production', '0.01400000', '107875.30000000', '107875.30000000', 8, NULL, NULL, '108045.80000000', '2.38700000', 'closed', '{\"user_id\":1,\"strategy_id\":\"FUT_BTC_H1_TEMA_ST_BELOW_MA\",\"ticker\":\"BTCUSDT\",\"timeframe\":\"60\",\"action\":\"BUY\",\"quantity\":\"0.014\",\"position_id\":\"1760857200\",\"leverage\":8,\"environment\":\"production\"}', '1760857200', '2025-10-19 06:48:53', '2025-10-19 22:00:08', '2025-10-19 22:00:08', 'bingx', NULL),
(90, 1, 4, '1980119339143008256', 'BTCUSDT', '60', 'BUY', 'futures', NULL, 'production', '0.00730000', '109428.30000000', '109428.30000000', 8, NULL, NULL, '111050.40000000', '11.84133000', 'closed', '{\"user_id\":1,\"strategy_id\":\"FUT_BTC_H1_GANN\",\"ticker\":\"BTCUSDT\",\"timeframe\":\"60\",\"action\":\"BUY\",\"quantity\":\"0.0073\",\"position_id\":\"1760925600\",\"leverage\":8,\"environment\":\"production\"}', '1760925600', '2025-10-20 00:50:14', '2025-10-20 06:00:05', '2025-10-20 06:00:05', 'bingx', NULL),
(91, 1, 9, '1980120482459947008', 'ETHUSDT', '60', 'BUY', 'futures', NULL, 'production', '0.19840000', '4035.20000000', '4035.20000000', 16, NULL, NULL, '4043.00000000', '1.54752000', 'closed', '{\"user_id\":1,\"strategy_id\":\"FUT_ETH_H1_TEMA_SUPERTREND\",\"ticker\":\"ETHUSDT\",\"timeframe\":\"60\",\"action\":\"BUY\",\"quantity\":\"0.1984\",\"position_id\":\"1760925600\",\"leverage\":16,\"environment\":\"production\"}', '1760925600', '2025-10-20 00:54:47', '2025-10-20 06:00:07', '2025-10-20 06:00:07', 'bingx', NULL),
(92, 1, 10, '1980120490353627136', 'ETHUSDT', '60', 'BUY', 'futures', NULL, 'production', '0.19840000', '4032.96000000', '4032.96000000', 8, NULL, NULL, '4043.00000000', '1.99193600', 'closed', '{\"user_id\":1,\"strategy_id\":\"FUT_ETH_H1_GANN\",\"ticker\":\"ETHUSDT\",\"timeframe\":\"60\",\"action\":\"BUY\",\"quantity\":\"0.1984\",\"position_id\":\"1760922000\",\"leverage\":8,\"environment\":\"production\"}', '1760922000', '2025-10-20 00:54:48', '2025-10-20 06:00:06', '2025-10-20 06:00:06', 'bingx', NULL),
(93, 1, 11, '1980649307010764800', 'BTCUSDT', '60', 'BUY', 'futures', NULL, 'production', '0.01223000', '111691.10000000', '111691.10000000', 8, NULL, NULL, '109955.90000000', '-21.22149600', 'closed', '{\"user_id\":1,\"strategy_id\":\"FUT_BTC_H1_TEMA_ST_BELOW_MA\",\"ticker\":\"BTCUSDT\",\"timeframe\":\"60\",\"action\":\"BUY\",\"quantity\":\"0.01223\",\"position_id\":\"1761012000\",\"leverage\":8,\"environment\":\"production\"}', '1761012000', '2025-10-21 11:56:08', '2025-10-21 19:21:42', '2025-10-21 19:21:42', 'bingx', NULL),
(94, 1, 4, '1980649331937513472', 'BTCUSDT', '60', 'BUY', 'futures', NULL, 'production', '0.00716000', '111655.70000000', '111655.70000000', 8, NULL, NULL, '111747.80000000', '0.65943600', 'closed', '{\"user_id\":1,\"strategy_id\":\"FUT_BTC_H1_GANN\",\"ticker\":\"BTCUSDT\",\"timeframe\":\"60\",\"action\":\"BUY\",\"quantity\":\"0.00716\",\"position_id\":\"1760990400\",\"leverage\":8,\"environment\":\"production\"}', '1760990400', '2025-10-21 11:56:14', '2025-10-21 17:00:13', '2025-10-21 17:00:13', 'bingx', NULL),
(95, 1, 9, '1980665486483918848', 'ETHUSDT', '60', 'BUY', 'futures', NULL, 'production', '0.19580000', '4090.57000000', '4090.57000000', 16, NULL, NULL, '4043.97000000', '-9.12428000', 'closed', '{\"user_id\":1,\"strategy_id\":\"FUT_ETH_H1_TEMA_SUPERTREND\",\"ticker\":\"ETHUSDT\",\"timeframe\":\"60\",\"action\":\"BUY\",\"quantity\":\"0.1958\",\"position_id\":\"1760979600\",\"leverage\":16,\"environment\":\"production\"}', '1760979600', '2025-10-21 13:00:26', '2025-10-21 13:57:25', '2025-10-21 13:57:25', 'bingx', NULL),
(96, 1, 10, '1980665493375160320', 'ETHUSDT', '60', 'BUY', 'futures', NULL, 'production', '0.19580000', '4090.22000000', '4090.22000000', 8, NULL, NULL, '4022.49000000', '-13.26153400', 'closed', '{\"user_id\":1,\"strategy_id\":\"FUT_ETH_H1_GANN\",\"ticker\":\"ETHUSDT\",\"timeframe\":\"60\",\"action\":\"BUY\",\"quantity\":\"0.1958\",\"position_id\":\"1761015600\",\"leverage\":8,\"environment\":\"production\"}', '1761015600', '2025-10-21 13:00:28', '2025-10-21 14:00:08', '2025-10-21 14:00:08', 'bingx', NULL),
(97, 1, 10, '1981403854809337856', 'ETHUSDT', '60', 'BUY', 'futures', NULL, 'production', '0.20480000', '3903.24000000', '3903.24000000', 8, NULL, NULL, '3854.47000000', '-9.98809600', 'closed', '{\"user_id\":1,\"strategy_id\":\"FUT_ETH_H1_GANN\",\"ticker\":\"ETHUSDT\",\"timeframe\":\"60\",\"action\":\"BUY\",\"quantity\":\"0.2048\",\"position_id\":\"1761228000\",\"leverage\":8,\"environment\":\"production\"}', '1761228000', '2025-10-23 13:54:26', '2025-10-23 16:02:33', '2025-10-23 16:02:33', 'bingx', NULL),
(98, 1, 9, '1981579611397427200', 'ETHUSDT', '60', 'BUY', 'futures', NULL, 'production', '0.20330000', '3937.65000000', '3937.65000000', 16, NULL, NULL, '3931.30000000', '-1.29095500', 'closed', '{\"user_id\":1,\"strategy_id\":\"FUT_ETH_H1_TEMA_SUPERTREND\",\"ticker\":\"ETHUSDT\",\"timeframe\":\"60\",\"action\":\"BUY\",\"quantity\":\"0.2033\",\"position_id\":\"1761253200\",\"leverage\":16,\"environment\":\"production\"}', '1761253200', '2025-10-24 01:32:50', '2025-10-24 05:06:35', '2025-10-24 05:06:35', 'bingx', NULL),
(99, 1, 4, '1981579963731546112', 'BTCUSDT', '60', 'BUY', 'futures', NULL, 'production', '0.00718000', '111392.90000000', '111392.90000000', 8, NULL, NULL, '111283.40000000', '-0.78621000', 'closed', '{\"user_id\":1,\"strategy_id\":\"FUT_BTC_H1_GANN\",\"ticker\":\"BTCUSDT\",\"timeframe\":\"60\",\"action\":\"BUY\",\"quantity\":\"0.00718\",\"position_id\":\"1761264000\",\"leverage\":8,\"environment\":\"production\"}', '1761264000', '2025-10-24 01:34:14', '2025-10-24 07:00:07', '2025-10-24 07:00:07', 'bingx', NULL),
(100, 1, 4, '1982377502131097600', 'BTCUSDT', '60', 'BUY', 'futures', NULL, 'production', '0.00713000', '112038.00000000', '112038.00000000', 8, NULL, NULL, '113534.00000000', '10.66648000', 'closed', '{\"user_id\":1,\"strategy_id\":\"FUT_BTC_H1_GANN\",\"ticker\":\"BTCUSDT\",\"timeframe\":\"60\",\"action\":\"BUY\",\"quantity\":\"0.00713\",\"position_id\":\"1761458400\",\"leverage\":8,\"environment\":\"production\"}', '1761458400', '2025-10-26 06:23:22', '2025-10-26 12:00:04', '2025-10-26 12:00:04', 'bingx', NULL),
(101, 1, 9, '1982377682117070848', 'ETHUSDT', '60', 'BUY', 'futures', NULL, 'production', '0.20100000', '3977.79000000', '3977.79000000', 16, NULL, NULL, '4066.85000000', '17.90106000', 'closed', '{\"user_id\":1,\"strategy_id\":\"FUT_ETH_H1_TEMA_SUPERTREND\",\"ticker\":\"ETHUSDT\",\"timeframe\":\"60\",\"action\":\"BUY\",\"quantity\":\"0.201\",\"position_id\":\"1761454800\",\"leverage\":16,\"environment\":\"production\"}', '1761454800', '2025-10-26 06:24:05', '2025-10-26 12:00:05', '2025-10-26 12:00:05', 'bingx', NULL),
(102, 1, 10, '1982412654269960192', 'ETHUSDT', '60', 'BUY', 'futures', NULL, 'production', '0.19860000', '4032.04000000', '4032.04000000', 8, NULL, NULL, '4054.13000000', '4.38707400', 'closed', '{\"user_id\":1,\"strategy_id\":\"FUT_ETH_H1_GANN\",\"ticker\":\"ETHUSDT\",\"timeframe\":\"60\",\"action\":\"BUY\",\"quantity\":\"0.1986\",\"position_id\":\"1761411600\",\"leverage\":8,\"environment\":\"production\"}', '1761411600', '2025-10-26 08:43:03', '2025-10-26 14:00:04', '2025-10-26 14:00:04', 'bingx', NULL),
(103, 1, 6, '1982583043264614400', 'ETHUSDT', '60', 'BUY', 'futures', NULL, 'production', '0.19220000', '4158.57000000', '4158.57000000', 8, NULL, NULL, '4192.89000000', '6.59630400', 'closed', '{\"user_id\":1,\"strategy_id\":\"FUT_ETH_H1_URSI_TREND\",\"ticker\":\"ETHUSDT\",\"timeframe\":\"60\",\"action\":\"BUY\",\"quantity\":\"0.1922\",\"position_id\":\"1761516000\",\"leverage\":8,\"environment\":\"production\"}', '1761516000', '2025-10-26 20:00:07', '2025-10-27 17:00:09', '2025-10-27 17:00:09', 'bingx', NULL),
(104, 1, 2, '1982583052622106624', 'BTCUSDT', '60', 'BUY', 'futures', NULL, 'production', '0.00697000', '114624.90000000', '114624.90000000', 8, NULL, NULL, '114734.20000000', '0.76182100', 'closed', '{\"user_id\":1,\"strategy_id\":\"FUT_BTC_H1_TREND_ALIGNED_RSI\",\"ticker\":\"BTCUSDT\",\"timeframe\":\"60\",\"action\":\"BUY\",\"quantity\":\"0.00697\",\"position_id\":\"1761516000\",\"leverage\":8,\"environment\":\"production\"}', '1761516000', '2025-10-26 20:00:09', '2025-10-27 10:36:27', '2025-10-27 10:36:27', 'bingx', NULL),
(105, 1, 10, '1984343061601193985', 'ETHUSDT', '60', 'BUY', 'futures', NULL, 'production', '0.20570000', '3887.64000000', '3887.64000000', 8, NULL, NULL, '3830.60000000', '-11.73312800', 'closed', '{\"user_id\":1,\"strategy_id\":\"FUT_ETH_H1_GANN\",\"ticker\":\"ETHUSDT\",\"timeframe\":\"60\",\"action\":\"BUY\",\"quantity\":\"0.2057\",\"position_id\":\"1761933600\",\"leverage\":8,\"environment\":\"production\"}', '1761933600', '2025-10-31 16:33:48', '2025-10-31 21:22:33', '2025-10-31 21:22:33', 'bingx', NULL),
(106, 1, 10, '1984847164085374977', 'ETHUSDT', '60', 'BUY', 'futures', NULL, 'production', '0.20460000', '3910.41000000', '3910.41000000', 8, NULL, NULL, '3869.26000000', '-8.41929000', 'closed', '{\"user_id\":1,\"strategy_id\":\"FUT_ETH_H1_GANN\",\"ticker\":\"ETHUSDT\",\"timeframe\":\"60\",\"action\":\"BUY\",\"quantity\":\"0.2046\",\"position_id\":\"1762023600\",\"leverage\":8,\"environment\":\"production\"}', '1762023600', '2025-11-02 01:56:56', '2025-11-02 06:41:33', '2025-11-02 06:41:33', 'bingx', NULL),
(107, 1, 9, '1984847172121661441', 'ETHUSDT', '60', 'BUY', 'futures', NULL, 'production', '0.20460000', '3909.82000000', '3909.82000000', 16, NULL, NULL, '3869.37000000', '-8.27607000', 'closed', '{\"user_id\":1,\"strategy_id\":\"FUT_ETH_H1_TEMA_SUPERTREND\",\"ticker\":\"ETHUSDT\",\"timeframe\":\"60\",\"action\":\"BUY\",\"quantity\":\"0.2046\",\"position_id\":\"1762038000\",\"leverage\":16,\"environment\":\"production\"}', '1762038000', '2025-11-02 01:56:57', '2025-11-02 06:41:33', '2025-11-02 06:41:33', 'bingx', NULL),
(108, 1, 11, '1984951723860430849', 'BTCUSDT', '60', 'BUY', 'futures', NULL, 'production', '0.01547000', '111093.80000000', '111093.80000000', 8, NULL, NULL, '109836.90000000', '-19.44424300', 'closed', '{\"user_id\":1,\"strategy_id\":\"FUT_BTC_H1_TEMA_ST_BELOW_MA\",\"ticker\":\"BTCUSDT\",\"timeframe\":\"60\",\"action\":\"BUY\",\"quantity\":\"0.01547\",\"position_id\":\"1762041600\",\"leverage\":8,\"environment\":\"production\"}', '1762041600', '2025-11-02 08:52:24', '2025-11-02 13:00:40', '2025-11-02 13:00:40', 'bingx', NULL),
(109, 1, 11, '1987549182700818433', 'BTCUSDT', '60', 'BUY', 'futures', NULL, 'production', '0.00988000', '104046.10000000', '104046.10000000', 8, NULL, NULL, '106188.80000000', '21.16987600', 'closed', '{\"user_id\":1,\"strategy_id\":\"FUT_BTC_H1_TEMA_ST_BELOW_MA\",\"ticker\":\"BTCUSDT\",\"timeframe\":\"60\",\"action\":\"BUY\",\"quantity\":\"0.00988\",\"position_id\":\"1762578000\",\"leverage\":8,\"environment\":\"production\"}', '1762578000', '2025-11-09 12:53:47', '2025-11-10 04:00:09', '2025-11-10 04:00:09', 'bingx', NULL),
(110, 1, 4, '1988053736043319297', 'BTCUSDT', '60', 'BUY', 'futures', NULL, 'production', '0.00749000', '106750.00000000', '106750.00000000', 8, NULL, NULL, '105837.80000000', '-6.83237800', 'closed', '{\"user_id\":1,\"strategy_id\":\"FUT_BTC_H1_GANN\",\"ticker\":\"BTCUSDT\",\"timeframe\":\"60\",\"action\":\"BUY\",\"quantity\":\"0.00749\",\"position_id\":\"1762794000\",\"leverage\":8,\"environment\":\"production\"}', '1762794000', '2025-11-10 22:18:42', '2025-11-10 23:03:41', '2025-11-10 23:03:41', 'bingx', NULL),
(111, 1, 10, '1992594798518734849', 'ETHUSDT', '60', 'BUY', 'futures', NULL, 'production', '0.28040000', '2854.86000000', '2854.86000000', 8, NULL, NULL, '2800.00000000', '-15.38274400', 'closed', '{\"user_id\":1,\"strategy_id\":\"FUT_ETH_H1_GANN\",\"ticker\":\"ETHUSDT\",\"timeframe\":\"60\",\"action\":\"BUY\",\"quantity\":\"0.2804\",\"position_id\":\"1763888400\",\"leverage\":8,\"environment\":\"production\"}', '1763888400', '2025-11-23 11:03:15', '2025-11-23 13:58:28', '2025-11-23 13:58:28', 'bingx', NULL),
(112, 1, 9, '1992778904720379905', 'ETHUSDT', '60', 'BUY', 'futures', NULL, 'production', '0.27990000', '2858.38000000', '2858.38000000', 16, NULL, NULL, '2810.13000000', '-13.50517500', 'closed', '{\"user_id\":1,\"strategy_id\":\"FUT_ETH_H1_TEMA_SUPERTREND\",\"ticker\":\"ETHUSDT\",\"timeframe\":\"60\",\"action\":\"BUY\",\"quantity\":\"0.2799\",\"position_id\":\"1763946000\",\"leverage\":16,\"environment\":\"production\"}', '1763946000', '2025-11-23 23:14:50', '2025-11-24 01:23:20', '2025-11-24 01:23:20', 'bingx', NULL),
(113, 1, 10, '1992778927625474049', 'ETHUSDT', '60', 'BUY', 'futures', NULL, 'production', '0.27990000', '2857.92000000', '2857.92000000', 8, NULL, NULL, '2814.74000000', '-12.08608200', 'closed', '{\"user_id\":1,\"strategy_id\":\"FUT_ETH_H1_GANN\",\"ticker\":\"ETHUSDT\",\"timeframe\":\"60\",\"action\":\"BUY\",\"quantity\":\"0.2799\",\"position_id\":\"1763946000\",\"leverage\":8,\"environment\":\"production\"}', '1763946000', '2025-11-23 23:14:55', '2025-11-24 01:22:00', '2025-11-24 01:22:00', 'bingx', NULL),
(114, 1, 9, '1993007786841083905', 'ETHUSDT', '60', 'BUY', 'futures', NULL, 'production', '0.27720000', '2888.62000000', '2888.62000000', 16, NULL, NULL, '2957.83000000', '19.18501200', 'closed', '{\"user_id\":1,\"strategy_id\":\"FUT_ETH_H1_TEMA_SUPERTREND\",\"ticker\":\"ETHUSDT\",\"timeframe\":\"60\",\"action\":\"BUY\",\"quantity\":\"0.2772\",\"position_id\":\"1763982000\",\"leverage\":16,\"environment\":\"production\"}', '1763982000', '2025-11-24 14:24:19', '2025-11-24 20:00:05', '2025-11-24 20:00:05', 'bingx', NULL),
(115, 1, 4, '1993015860809175041', 'BTCUSDT', '60', 'BUY', 'futures', NULL, 'production', '0.00907000', '88113.20000000', '88113.20000000', 8, NULL, NULL, '88585.00000000', '4.27922600', 'closed', '{\"user_id\":1,\"strategy_id\":\"FUT_BTC_H1_GANN\",\"ticker\":\"BTCUSDT\",\"timeframe\":\"60\",\"action\":\"BUY\",\"quantity\":\"0.00907\",\"position_id\":\"1763949600\",\"leverage\":8,\"environment\":\"production\"}', '1763949600', '2025-11-24 14:56:24', '2025-11-24 20:00:07', '2025-11-24 20:00:07', 'bingx', NULL),
(116, 1, 11, '1993015885828198401', 'BTCUSDT', '60', 'BUY', 'futures', NULL, 'production', '0.01012000', '88170.50000000', '88170.50000000', 8, NULL, NULL, '86962.70000000', '-12.22293600', 'closed', '{\"user_id\":1,\"strategy_id\":\"FUT_BTC_H1_TEMA_ST_BELOW_MA\",\"ticker\":\"BTCUSDT\",\"timeframe\":\"60\",\"action\":\"BUY\",\"quantity\":\"0.01012\",\"position_id\":\"1763974800\",\"leverage\":8,\"environment\":\"production\"}', '1763974800', '2025-11-24 14:56:30', '2025-11-25 06:00:10', '2025-11-25 06:00:10', 'bingx', NULL),
(117, 1, 4, '1993733139888869377', 'BTCUSDT', '60', 'BUY', 'futures', NULL, 'production', '0.00906000', '88336.50000000', '88336.50000000', 8, NULL, NULL, '90303.80000000', '17.82373800', 'closed', '{\"user_id\":1,\"strategy_id\":\"FUT_BTC_H1_GANN\",\"ticker\":\"BTCUSDT\",\"timeframe\":\"60\",\"action\":\"BUY\",\"quantity\":\"0.00906\",\"position_id\":\"1764136800\",\"leverage\":8,\"environment\":\"production\"}', '1764136800', '2025-11-26 14:26:37', '2025-11-26 20:00:06', '2025-11-26 20:00:06', 'bingx', NULL),
(118, 1, 11, '1993733538385498113', 'BTCUSDT', '60', 'BUY', 'futures', NULL, 'production', '0.01197000', '88710.10000000', '88710.10000000', 8, NULL, NULL, '91435.00000000', '32.61705300', 'closed', '{\"user_id\":1,\"strategy_id\":\"FUT_BTC_H1_TEMA_ST_BELOW_MA\",\"ticker\":\"BTCUSDT\",\"timeframe\":\"60\",\"action\":\"BUY\",\"quantity\":\"0.01197\",\"position_id\":\"1764169200\",\"leverage\":8,\"environment\":\"production\"}', '1764169200', '2025-11-26 14:28:12', '2025-11-27 06:00:06', '2025-11-27 06:00:06', 'bingx', NULL),
(119, 1, 9, '1993734806839496705', 'ETHUSDT', '60', 'BUY', 'futures', NULL, 'production', '0.26780000', '2989.51000000', '2989.51000000', 16, NULL, NULL, '3024.58000000', '9.39174600', 'closed', '{\"user_id\":1,\"strategy_id\":\"FUT_ETH_H1_TEMA_SUPERTREND\",\"ticker\":\"ETHUSDT\",\"timeframe\":\"60\",\"action\":\"BUY\",\"quantity\":\"0.2678\",\"position_id\":\"1764151200\",\"leverage\":16,\"environment\":\"production\"}', '1764151200', '2025-11-26 14:33:14', '2025-11-26 20:00:07', '2025-11-26 20:00:07', 'bingx', NULL),
(120, 1, 9, '1994328290042580995', 'ETHUSDT', '60', 'BUY', 'futures', NULL, 'production', '0.26040000', '3073.28000000', '3073.28000000', 16, NULL, NULL, '3032.69000000', '-10.56963600', 'closed', '{\"user_id\":1,\"strategy_id\":\"FUT_ETH_H1_TEMA_SUPERTREND\",\"ticker\":\"ETHUSDT\",\"timeframe\":\"60\",\"action\":\"BUY\",\"quantity\":\"0.2604\",\"position_id\":\"1764288000\",\"leverage\":16,\"environment\":\"production\"}', '1764288000', '2025-11-28 05:51:32', '2025-11-28 08:14:59', '2025-11-28 08:14:59', 'bingx', NULL),
(121, 1, 4, '1994361238645116929', 'BTCUSDT', '60', 'BUY', 'futures', NULL, 'production', '0.00870000', '91930.00000000', '91930.00000000', 8, NULL, NULL, '91300.00000000', '-5.48100000', 'closed', '{\"user_id\":1,\"strategy_id\":\"FUT_BTC_H1_GANN\",\"ticker\":\"BTCUSDT\",\"timeframe\":\"60\",\"action\":\"BUY\",\"quantity\":\"0.0087\",\"position_id\":\"1764302400\",\"leverage\":8,\"environment\":\"production\"}', '1764302400', '2025-11-28 08:02:27', '2025-11-28 13:23:24', '2025-11-28 13:23:24', 'bingx', NULL),
(122, 1, 4, '1996351121232236545', 'BTCUSDT', '60', 'BUY', 'futures', NULL, 'production', '0.00851000', '94061.50000000', '94061.50000000', 8, NULL, NULL, '93102.40000000', '-8.16194100', 'closed', '{\"user_id\":1,\"strategy_id\":\"FUT_BTC_H1_GANN\",\"ticker\":\"BTCUSDT\",\"timeframe\":\"60\",\"action\":\"BUY\",\"quantity\":\"0.00851\",\"position_id\":\"1764784800\",\"leverage\":8,\"environment\":\"production\"}', '1764784800', '2025-12-03 19:49:33', '2025-12-03 21:12:12', '2025-12-03 21:12:12', 'bingx', NULL),
(123, 1, 6, '1996414181288448001', 'ETHUSDT', '60', 'BUY', 'futures', NULL, 'production', '0.24830000', '3220.00000000', '3220.00000000', 8, NULL, NULL, '3145.47000000', '-18.50579900', 'closed', '{\"user_id\":1,\"strategy_id\":\"FUT_ETH_H1_URSI_TREND\",\"ticker\":\"ETHUSDT\",\"timeframe\":\"60\",\"action\":\"BUY\",\"quantity\":\"0.2483\",\"position_id\":\"1764813600\",\"leverage\":8,\"environment\":\"production\"}', '1764813600', '2025-12-04 00:00:07', '2025-12-04 12:00:17', '2025-12-04 12:00:17', 'bingx', NULL),
(124, 1, 10, '1997717354762473473', 'ETHUSDT', '60', 'BUY', 'futures', NULL, 'production', '0.26060000', '3073.06000000', '3073.06000000', 8, NULL, NULL, '3028.01000000', '-11.74003000', 'closed', '{\"user_id\":1,\"strategy_id\":\"FUT_ETH_H1_GANN\",\"ticker\":\"ETHUSDT\",\"timeframe\":\"60\",\"action\":\"BUY\",\"quantity\":\"0.2606\",\"position_id\":\"1765123200\",\"leverage\":8,\"environment\":\"production\"}', '1765123200', '2025-12-07 14:18:28', '2025-12-07 19:07:13', '2025-12-07 19:07:13', 'bingx', NULL),
(125, 1, 4, '1997933296910077953', 'BTCUSDT', '60', 'BUY', 'futures', NULL, 'production', '0.00871000', '91787.40000000', '91787.40000000', 8, NULL, NULL, '91750.00000000', '-0.32575400', 'closed', '{\"user_id\":1,\"strategy_id\":\"FUT_BTC_H1_GANN\",\"ticker\":\"BTCUSDT\",\"timeframe\":\"60\",\"action\":\"BUY\",\"quantity\":\"0.00871\",\"position_id\":\"1765155600\",\"leverage\":8,\"environment\":\"production\"}', '1765155600', '2025-12-08 04:36:33', '2025-12-08 10:00:09', '2025-12-08 10:00:09', 'bingx', NULL),
(126, 1, 9, '1998417656524312577', 'ETHUSDT', '60', 'BUY', 'futures', NULL, 'production', '0.25150000', '3181.92000000', '3181.92000000', 16, NULL, NULL, '3335.20000000', '38.54992000', 'closed', '{\"user_id\":1,\"strategy_id\":\"FUT_ETH_H1_TEMA_SUPERTREND\",\"ticker\":\"ETHUSDT\",\"timeframe\":\"60\",\"action\":\"BUY\",\"quantity\":\"0.2515\",\"position_id\":\"1765209600\",\"leverage\":16,\"environment\":\"production\"}', '1765209600', '2025-12-09 12:41:13', '2025-12-09 17:18:19', '2025-12-09 17:18:19', 'bingx', NULL),
(127, 1, 11, '1998419950816989185', 'BTCUSDT', '60', 'BUY', 'futures', NULL, 'production', '0.01258000', '92340.80000000', '92340.80000000', 8, NULL, NULL, '92350.00000000', '0.11573600', 'closed', '{\"user_id\":1,\"strategy_id\":\"FUT_BTC_H1_TEMA_ST_BELOW_MA\",\"ticker\":\"BTCUSDT\",\"timeframe\":\"60\",\"action\":\"BUY\",\"quantity\":\"0.01258\",\"position_id\":\"1765238400\",\"leverage\":8,\"environment\":\"production\"}', '1765238400', '2025-12-09 12:50:20', '2025-12-10 01:00:10', '2025-12-10 01:00:10', 'bingx', NULL),
(128, 1, 6, '1998422431332569089', 'ETHUSDT', '60', 'BUY', 'futures', NULL, 'production', '0.24640000', '3250.60000000', '3250.60000000', 8, NULL, NULL, '3321.37000000', '17.43772800', 'closed', '{\"user_id\":1,\"strategy_id\":\"FUT_ETH_H1_URSI_TREND\",\"ticker\":\"ETHUSDT\",\"timeframe\":\"60\",\"action\":\"BUY\",\"quantity\":\"0.2464\",\"position_id\":\"1765292400\",\"leverage\":8,\"environment\":\"production\"}', '1765292400', '2025-12-09 13:00:11', '2025-12-10 06:00:06', '2025-12-10 06:00:06', 'bingx', NULL),
(129, 1, 6, '1998815003037143041', 'ETHUSDT', '60', 'BUY', 'futures', NULL, 'production', '0.23730000', '3366.82000000', '3366.82000000', 8, NULL, NULL, '3276.19000000', '-21.50649900', 'closed', '{\"user_id\":1,\"strategy_id\":\"FUT_ETH_H1_URSI_TREND\",\"ticker\":\"ETHUSDT\",\"timeframe\":\"60\",\"action\":\"BUY\",\"quantity\":\"0.2373\",\"position_id\":\"1765386000\",\"leverage\":8,\"environment\":\"production\"}', '1765386000', '2025-12-10 15:00:08', '2025-12-10 22:00:06', '2025-12-10 22:00:06', 'bingx', NULL),
(130, 1, 9, '1998824265536770049', 'ETHUSDT', '60', 'BUY', 'futures', NULL, 'production', '0.23540000', '3403.70000000', '3403.70000000', 16, NULL, NULL, '3363.78000000', '-9.39716800', 'closed', '{\"user_id\":1,\"strategy_id\":\"FUT_ETH_H1_TEMA_SUPERTREND\",\"ticker\":\"ETHUSDT\",\"timeframe\":\"60\",\"action\":\"BUY\",\"quantity\":\"0.2354\",\"position_id\":\"1765368000\",\"leverage\":16,\"environment\":\"production\"}', '1765368000', '2025-12-10 15:36:56', '2025-12-10 15:57:03', '2025-12-10 15:57:03', 'bingx', NULL);
INSERT INTO `trades` (`id`, `user_id`, `strategy_id`, `order_id`, `symbol`, `timeframe`, `side`, `trade_type`, `mt_signal_id`, `environment`, `quantity`, `entry_price`, `current_price`, `leverage`, `take_profit`, `stop_loss`, `exit_price`, `pnl`, `status`, `webhook_data`, `position_id`, `created_at`, `updated_at`, `closed_at`, `source`, `user_signal_id`) VALUES
(131, 1, 2, '1999237782479835137', 'BTCUSDT', '60', 'BUY', 'futures', NULL, 'production', '0.00861000', '92962.80000000', '92962.80000000', 8, NULL, NULL, '91640.40000000', '-11.38586400', 'closed', '{\"user_id\":1,\"strategy_id\":\"FUT_BTC_H1_TREND_ALIGNED_RSI\",\"ticker\":\"BTCUSDT\",\"timeframe\":\"60\",\"action\":\"BUY\",\"quantity\":\"0.00861\",\"position_id\":\"1765486800\",\"leverage\":8,\"environment\":\"production\"}', '1765486800', '2025-12-11 19:00:06', '2025-12-11 22:00:09', '2025-12-11 22:00:09', 'bingx', NULL),
(132, 1, 6, '1999343475023155201', 'ETHUSDT', '60', 'BUY', 'futures', NULL, 'production', '0.24630000', '3245.99000000', '3245.99000000', 8, NULL, NULL, '3069.69000000', '-43.42269000', 'closed', '{\"user_id\":1,\"strategy_id\":\"FUT_ETH_H1_URSI_TREND\",\"ticker\":\"ETHUSDT\",\"timeframe\":\"60\",\"action\":\"BUY\",\"quantity\":\"0.2463\",\"position_id\":\"1765512000\",\"leverage\":8,\"environment\":\"production\"}', '1765512000', '2025-12-12 02:00:05', '2025-12-12 13:00:22', '2025-12-12 13:00:22', 'bingx', NULL),
(133, 1, 9, '2000389879707471873', 'ETHUSDT', '60', 'BUY', 'futures', NULL, 'production', '0.25510000', '3140.00000000', '3140.00000000', 16, NULL, NULL, '3105.14000000', '-8.89278600', 'closed', '{\"user_id\":1,\"strategy_id\":\"FUT_ETH_H1_TEMA_SUPERTREND\",\"ticker\":\"ETHUSDT\",\"timeframe\":\"60\",\"action\":\"BUY\",\"quantity\":\"0.2551\",\"position_id\":\"1765720800\",\"leverage\":16,\"environment\":\"production\"}', '1765720800', '2025-12-14 23:18:07', '2025-12-15 01:24:30', '2025-12-15 01:24:30', 'bingx', NULL),
(134, 1, 11, '2001304610479804417', 'BTCUSDT', '60', 'BUY', 'futures', NULL, 'production', '0.01337000', '88500.00000000', '88500.00000000', 8, NULL, NULL, '86669.60000000', '-24.47244800', 'closed', '{\"user_id\":1,\"strategy_id\":\"FUT_BTC_H1_TEMA_ST_BELOW_MA\",\"ticker\":\"BTCUSDT\",\"timeframe\":\"60\",\"action\":\"BUY\",\"quantity\":\"0.01337\",\"position_id\":\"1765958400\",\"leverage\":8,\"environment\":\"production\"}', '1765958400', '2025-12-17 11:52:56', '2025-12-17 13:15:49', '2025-12-17 13:15:49', 'bingx', NULL),
(135, 1, 4, '2001304647838470145', 'BTCUSDT', '60', 'BUY', 'futures', NULL, 'production', '0.00907000', '88729.80000000', '88729.80000000', 8, NULL, NULL, '87340.10000000', '-12.60457900', 'closed', '{\"user_id\":1,\"strategy_id\":\"FUT_BTC_H1_GANN\",\"ticker\":\"BTCUSDT\",\"timeframe\":\"60\",\"action\":\"BUY\",\"quantity\":\"0.00907\",\"position_id\":\"1765954800\",\"leverage\":8,\"environment\":\"production\"}', '1765954800', '2025-12-17 11:53:05', '2025-12-17 12:55:13', '2025-12-17 12:55:13', 'bingx', NULL),
(136, 1, 10, '2001306147792883713', 'ETHUSDT', '60', 'BUY', 'futures', NULL, 'production', '0.26820000', '2984.93000000', '2984.93000000', 8, NULL, NULL, '2940.05000000', '-12.03681600', 'closed', '{\"user_id\":1,\"strategy_id\":\"FUT_ETH_H1_GANN\",\"ticker\":\"ETHUSDT\",\"timeframe\":\"60\",\"action\":\"BUY\",\"quantity\":\"0.2682\",\"position_id\":\"1765936800\",\"leverage\":8,\"environment\":\"production\"}', '1765936800', '2025-12-17 11:59:03', '2025-12-17 12:45:18', '2025-12-17 12:45:18', 'bingx', NULL),
(137, 1, 10, '2002895330823114753', 'ETHUSDT', '60', 'BUY', 'futures', NULL, 'production', '0.26490000', '3019.55000000', '3019.55000000', 8, NULL, NULL, '2972.60000000', '-12.43705500', 'closed', '{\"user_id\":1,\"strategy_id\":\"FUT_ETH_H1_GANN\",\"ticker\":\"ETHUSDT\",\"timeframe\":\"60\",\"action\":\"BUY\",\"quantity\":\"0.2649\",\"position_id\":\"1766242800\",\"leverage\":8,\"environment\":\"production\"}', '1766242800', '2025-12-21 21:13:54', '2025-12-21 23:06:04', '2025-12-21 23:06:04', 'bingx', NULL),
(138, 1, 2, '2003042867949342721', 'BTCUSDT', '60', 'BUY', 'futures', NULL, 'production', '0.00890000', '89802.80000000', '89802.80000000', 8, NULL, NULL, '89267.80000000', '-4.76150000', 'closed', '{\"user_id\":1,\"strategy_id\":\"FUT_BTC_H1_TREND_ALIGNED_RSI\",\"ticker\":\"BTCUSDT\",\"timeframe\":\"60\",\"action\":\"BUY\",\"quantity\":\"0.0089\",\"position_id\":\"1766394000\",\"leverage\":8,\"environment\":\"production\"}', '1766394000', '2025-12-22 07:00:09', '2025-12-22 15:00:07', '2025-12-22 15:00:07', 'bingx', NULL),
(139, 1, 4, '2004212230639325185', 'BTCUSDT', '60', 'BUY', 'futures', NULL, 'production', '0.00908000', '88087.20000000', '88087.20000000', 8, NULL, NULL, '87850.40000000', '-2.15014400', 'closed', '{\"user_id\":1,\"strategy_id\":\"FUT_BTC_H1_GANN\",\"ticker\":\"BTCUSDT\",\"timeframe\":\"60\",\"action\":\"BUY\",\"quantity\":\"0.00908\",\"position_id\":\"1766664000\",\"leverage\":8,\"environment\":\"production\"}', '1766664000', '2025-12-25 12:26:47', '2025-12-25 18:00:04', '2025-12-25 18:00:04', 'bingx', NULL),
(140, 1, 11, '2004216525963137025', 'BTCUSDT', '60', 'BUY', 'futures', NULL, 'production', '0.02056000', '88419.70000000', '88419.70000000', 8, NULL, NULL, '87400.80000000', '-20.94858400', 'closed', '{\"user_id\":1,\"strategy_id\":\"FUT_BTC_H1_TEMA_ST_BELOW_MA\",\"ticker\":\"BTCUSDT\",\"timeframe\":\"60\",\"action\":\"BUY\",\"quantity\":\"0.02056\",\"position_id\":\"1766656800\",\"leverage\":8,\"environment\":\"production\"}', '1766656800', '2025-12-25 12:43:51', '2025-12-25 19:47:43', '2025-12-25 19:47:43', 'bingx', NULL),
(141, 1, 10, '2004375459273379841', 'ETHUSDT', '60', 'BUY', 'futures', NULL, 'production', '0.26920000', '2970.17000000', '2970.17000000', 8, NULL, NULL, '2953.56000000', '-4.47141200', 'closed', '{\"user_id\":1,\"strategy_id\":\"FUT_ETH_H1_GANN\",\"ticker\":\"ETHUSDT\",\"timeframe\":\"60\",\"action\":\"BUY\",\"quantity\":\"0.2692\",\"position_id\":\"1766707200\",\"leverage\":8,\"environment\":\"production\"}', '1766707200', '2025-12-25 23:15:24', '2025-12-26 05:00:06', '2025-12-26 05:00:06', 'bingx', NULL),
(142, 1, 11, '2004375667520573441', 'BTCUSDT', '60', 'BUY', 'futures', NULL, 'production', '0.02064000', '88410.30000000', '88410.30000000', 8, NULL, NULL, '87643.20000000', '-15.83294400', 'closed', '{\"user_id\":1,\"strategy_id\":\"FUT_BTC_H1_TEMA_ST_BELOW_MA\",\"ticker\":\"BTCUSDT\",\"timeframe\":\"60\",\"action\":\"BUY\",\"quantity\":\"0.02064\",\"position_id\":\"1766703600\",\"leverage\":8,\"environment\":\"production\"}', '1766703600', '2025-12-25 23:16:13', '2025-12-26 11:50:38', '2025-12-26 11:50:38', 'bingx', NULL),
(143, 1, 4, '2004375682431324161', 'BTCUSDT', '60', 'BUY', 'futures', NULL, 'production', '0.00903000', '88444.10000000', '88444.10000000', 8, NULL, NULL, '88405.40000000', '-0.34946100', 'closed', '{\"user_id\":1,\"strategy_id\":\"FUT_BTC_H1_GANN\",\"ticker\":\"BTCUSDT\",\"timeframe\":\"60\",\"action\":\"BUY\",\"quantity\":\"0.00903\",\"position_id\":\"1766710800\",\"leverage\":8,\"environment\":\"production\"}', '1766710800', '2025-12-25 23:16:17', '2025-12-26 05:00:07', '2025-12-26 05:00:07', 'bingx', NULL),
(144, 1, 4, '2005226371302821889', 'BTCUSDT', '60', 'BUY', 'futures', NULL, 'production', '0.00909000', '87959.50000000', '87959.50000000', 8, NULL, NULL, '87800.10000000', '-1.44894600', 'closed', '{\"user_id\":1,\"strategy_id\":\"FUT_BTC_H1_GANN\",\"ticker\":\"BTCUSDT\",\"timeframe\":\"60\",\"action\":\"BUY\",\"quantity\":\"0.00909\",\"position_id\":\"1766912400\",\"leverage\":8,\"environment\":\"production\"}', '1766912400', '2025-12-28 07:36:37', '2025-12-28 13:00:03', '2025-12-28 13:00:03', 'bingx', NULL),
(145, 1, 10, '2005297957733142529', 'ETHUSDT', '60', 'BUY', 'futures', NULL, 'production', '0.27010000', '2958.48000000', '2958.48000000', 8, NULL, NULL, '2942.83000000', '-4.22706500', 'closed', '{\"user_id\":1,\"strategy_id\":\"FUT_ETH_H1_GANN\",\"ticker\":\"ETHUSDT\",\"timeframe\":\"60\",\"action\":\"BUY\",\"quantity\":\"0.2701\",\"position_id\":\"1766905200\",\"leverage\":8,\"environment\":\"production\"}', '1766905200', '2025-12-28 12:21:04', '2025-12-28 13:25:24', '2025-12-28 13:25:24', 'bingx', NULL),
(146, 1, 10, '2005436142408699905', 'ETHUSDT', '60', 'BUY', 'futures', NULL, 'production', '0.27010000', '2965.29000000', '2965.29000000', 8, NULL, NULL, '3036.51000000', '19.23652200', 'closed', '{\"user_id\":1,\"strategy_id\":\"FUT_ETH_H1_GANN\",\"ticker\":\"ETHUSDT\",\"timeframe\":\"60\",\"action\":\"BUY\",\"quantity\":\"0.2701\",\"position_id\":\"1766952000\",\"leverage\":8,\"environment\":\"production\"}', '1766952000', '2025-12-28 21:30:10', '2025-12-29 03:00:06', '2025-12-29 03:00:06', 'bingx', NULL),
(147, 1, 9, '2005436147580276737', 'ETHUSDT', '60', 'BUY', 'futures', NULL, 'production', '0.27010000', '2967.00000000', '2967.00000000', 16, NULL, NULL, '3036.57000000', '18.79085700', 'closed', '{\"user_id\":1,\"strategy_id\":\"FUT_ETH_H1_TEMA_SUPERTREND\",\"ticker\":\"ETHUSDT\",\"timeframe\":\"60\",\"action\":\"BUY\",\"quantity\":\"0.2701\",\"position_id\":\"1766952000\",\"leverage\":16,\"environment\":\"production\"}', '1766952000', '2025-12-28 21:30:11', '2025-12-29 03:00:06', '2025-12-29 03:00:06', 'bingx', NULL),
(148, 1, 4, '2005436421015343105', 'BTCUSDT', '60', 'BUY', 'futures', NULL, 'production', '0.00908000', '88192.10000000', '88192.10000000', 8, NULL, NULL, '89987.30000000', '16.30041600', 'closed', '{\"user_id\":1,\"strategy_id\":\"FUT_BTC_H1_GANN\",\"ticker\":\"BTCUSDT\",\"timeframe\":\"60\",\"action\":\"BUY\",\"quantity\":\"0.00908\",\"position_id\":\"1766952000\",\"leverage\":8,\"environment\":\"production\"}', '1766952000', '2025-12-28 21:31:17', '2025-12-29 03:00:07', '2025-12-29 03:00:07', 'bingx', NULL),
(149, 1, 6, '2007029125750984705', 'ETHUSDT', '60', 'BUY', 'futures', NULL, 'production', '0.26240000', '3047.82000000', '3047.82000000', 8, NULL, NULL, '3097.50000000', '13.03603200', 'closed', '{\"user_id\":1,\"strategy_id\":\"FUT_ETH_H1_URSI_TREND\",\"ticker\":\"ETHUSDT\",\"timeframe\":\"60\",\"action\":\"BUY\",\"quantity\":\"0.2624\",\"position_id\":\"1767344400\",\"leverage\":8,\"environment\":\"production\"}', '1767344400', '2026-01-02 07:00:07', '2026-01-03 04:00:04', '2026-01-03 04:00:04', 'bingx', NULL),
(150, 1, 2, '2007029127948800001', 'BTCUSDT', '60', 'BUY', 'futures', NULL, 'production', '0.00894000', '89447.50000000', '89447.50000000', 8, NULL, NULL, '89827.30000000', '3.39541200', 'closed', '{\"user_id\":1,\"strategy_id\":\"FUT_BTC_H1_TREND_ALIGNED_RSI\",\"ticker\":\"BTCUSDT\",\"timeframe\":\"60\",\"action\":\"BUY\",\"quantity\":\"0.00894\",\"position_id\":\"1767344400\",\"leverage\":8,\"environment\":\"production\"}', '1767344400', '2026-01-02 07:00:08', '2026-01-03 04:00:05', '2026-01-03 04:00:05', 'bingx', NULL),
(151, 1, 6, '2007572694370881537', 'ETHUSDT', '60', 'BUY', 'futures', NULL, 'production', '0.25630000', '3119.27000000', '3119.27000000', 8, NULL, NULL, '3135.64000000', '4.19563100', 'closed', '{\"user_id\":1,\"strategy_id\":\"FUT_ETH_H1_URSI_TREND\",\"ticker\":\"ETHUSDT\",\"timeframe\":\"60\",\"action\":\"BUY\",\"quantity\":\"0.2563\",\"position_id\":\"1767474000\",\"leverage\":8,\"environment\":\"production\"}', '1767474000', '2026-01-03 19:00:04', '2026-01-04 12:00:04', '2026-01-04 12:00:04', 'bingx', NULL),
(152, 1, 2, '2007617993927823361', 'BTCUSDT', '60', 'BUY', 'futures', NULL, 'production', '0.00875000', '91315.10000000', '91315.10000000', 8, NULL, NULL, '91123.20000000', '-1.67912500', 'closed', '{\"user_id\":1,\"strategy_id\":\"FUT_BTC_H1_TREND_ALIGNED_RSI\",\"ticker\":\"BTCUSDT\",\"timeframe\":\"60\",\"action\":\"BUY\",\"quantity\":\"0.00875\",\"position_id\":\"1767484800\",\"leverage\":8,\"environment\":\"production\"}', '1767484800', '2026-01-03 22:00:04', '2026-01-04 09:41:49', '2026-01-04 09:41:49', 'bingx', NULL),
(153, 1, 4, '2007965587795349505', 'BTCUSDT', '60', 'BUY', 'futures', NULL, 'production', '0.00871000', '91877.70000000', '91877.70000000', 8, NULL, NULL, '92318.00000000', '3.83501300', 'closed', '{\"user_id\":1,\"strategy_id\":\"FUT_BTC_H1_GANN\",\"ticker\":\"BTCUSDT\",\"timeframe\":\"60\",\"action\":\"BUY\",\"quantity\":\"0.00871\",\"position_id\":\"1767538800\",\"leverage\":8,\"environment\":\"production\"}', '1767538800', '2026-01-04 21:01:17', '2026-01-05 03:00:08', '2026-01-05 03:00:08', 'bingx', NULL),
(154, 1, 8, '2007965607265308673', 'BTCUSDT', '60', 'BUY', 'futures', NULL, 'production', '0.02178000', '91990.00000000', '91990.00000000', 16, NULL, NULL, '93929.20000000', '42.23577600', 'closed', '{\"user_id\":1,\"strategy_id\":\"FUT_BTC_H1_TEMA_ST_OVER_MA\",\"ticker\":\"BTCUSDT\",\"timeframe\":\"60\",\"action\":\"BUY\",\"quantity\":\"0.02178\",\"position_id\":\"1767549600\",\"leverage\":16,\"environment\":\"production\"}', '1767549600', '2026-01-04 21:01:22', '2026-01-05 13:00:09', '2026-01-05 13:00:09', 'bingx', NULL),
(155, 1, 10, '2007977740526948355', 'ETHUSDT', '60', 'BUY', 'futures', NULL, 'production', '0.25250000', '3173.68000000', '3173.68000000', 8, NULL, NULL, '3147.25000000', '-6.67357500', 'closed', '{\"user_id\":1,\"strategy_id\":\"FUT_ETH_H1_GANN\",\"ticker\":\"ETHUSDT\",\"timeframe\":\"60\",\"action\":\"BUY\",\"quantity\":\"0.2525\",\"position_id\":\"1767517200\",\"leverage\":8,\"environment\":\"production\"}', '1767517200', '2026-01-04 21:49:35', '2026-01-05 03:00:07', '2026-01-05 03:00:07', 'bingx', NULL),
(156, 1, 9, '2007977769450868737', 'ETHUSDT', '60', 'BUY', 'futures', NULL, 'production', '0.25250000', '3169.07000000', '3169.07000000', 16, NULL, NULL, '3175.70000000', '1.67407500', 'closed', '{\"user_id\":1,\"strategy_id\":\"FUT_ETH_H1_TEMA_SUPERTREND\",\"ticker\":\"ETHUSDT\",\"timeframe\":\"60\",\"action\":\"BUY\",\"quantity\":\"0.2525\",\"position_id\":\"1767531600\",\"leverage\":16,\"environment\":\"production\"}', '1767531600', '2026-01-04 21:49:41', '2026-01-05 01:13:42', '2026-01-05 01:13:42', 'bingx', NULL),
(157, 1, 2, '2007980408238510081', 'BTCUSDT', '60', 'BUY', 'futures', NULL, 'production', '0.00865000', '92390.30000000', '92390.30000000', 8, NULL, NULL, '93532.20000000', '9.87743500', 'closed', '{\"user_id\":1,\"strategy_id\":\"FUT_BTC_H1_TREND_ALIGNED_RSI\",\"ticker\":\"BTCUSDT\",\"timeframe\":\"60\",\"action\":\"BUY\",\"quantity\":\"0.00865\",\"position_id\":\"1767571200\",\"leverage\":8,\"environment\":\"production\"}', '1767571200', '2026-01-04 22:00:11', '2026-01-06 03:00:07', '2026-01-06 03:00:07', 'bingx', NULL),
(158, 1, 6, '2008116287305158657', 'ETHUSDT', '60', 'BUY', 'futures', NULL, 'production', '0.25250000', '3167.85000000', '3167.85000000', 8, NULL, NULL, '3167.25000000', '-0.15150000', 'closed', '{\"user_id\":1,\"strategy_id\":\"FUT_ETH_H1_URSI_TREND\",\"ticker\":\"ETHUSDT\",\"timeframe\":\"60\",\"action\":\"BUY\",\"quantity\":\"0.2525\",\"position_id\":\"1767603600\",\"leverage\":8,\"environment\":\"production\"}', '1767603600', '2026-01-05 07:00:07', '2026-01-05 12:00:07', '2026-01-05 12:00:07', 'bingx', NULL),
(159, 1, 10, '2008236594393255937', 'ETHUSDT', '60', 'BUY', 'futures', NULL, 'production', '0.24830000', '3227.20000000', '3227.20000000', 8, NULL, NULL, '3197.05000000', '-7.48624500', 'closed', '{\"user_id\":1,\"strategy_id\":\"FUT_ETH_H1_GANN\",\"ticker\":\"ETHUSDT\",\"timeframe\":\"60\",\"action\":\"BUY\",\"quantity\":\"0.2483\",\"position_id\":\"1767596400\",\"leverage\":8,\"environment\":\"production\"}', '1767596400', '2026-01-05 14:58:10', '2026-01-05 15:18:49', '2026-01-05 15:18:49', 'bingx', NULL),
(160, 1, 6, '2008252185036460033', 'ETHUSDT', '60', 'BUY', 'futures', NULL, 'production', '0.24780000', '3223.78000000', '3223.78000000', 8, NULL, NULL, '3227.12000000', '0.82765200', 'closed', '{\"user_id\":1,\"strategy_id\":\"FUT_ETH_H1_URSI_TREND\",\"ticker\":\"ETHUSDT\",\"timeframe\":\"60\",\"action\":\"BUY\",\"quantity\":\"0.2478\",\"position_id\":\"1767636000\",\"leverage\":8,\"environment\":\"production\"}', '1767636000', '2026-01-05 16:00:07', '2026-01-06 08:00:06', '2026-01-06 08:00:06', 'bingx', NULL),
(161, 1, 9, '2008542113830014977', 'ETHUSDT', '60', 'BUY', 'futures', NULL, 'production', '0.24490000', '3277.47000000', '3277.47000000', 16, NULL, NULL, '3252.96000000', '-6.00249900', 'closed', '{\"user_id\":1,\"strategy_id\":\"FUT_ETH_H1_TEMA_SUPERTREND\",\"ticker\":\"ETHUSDT\",\"timeframe\":\"60\",\"action\":\"BUY\",\"quantity\":\"0.2449\",\"position_id\":\"1767690000\",\"leverage\":16,\"environment\":\"production\"}', '1767690000', '2026-01-06 11:12:11', '2026-01-06 13:25:41', '2026-01-06 13:25:41', 'bingx', NULL),
(162, 1, 6, '2008554179349450753', 'ETHUSDT', '60', 'BUY', 'futures', NULL, 'production', '0.24400000', '3275.83000000', '3275.83000000', 8, NULL, NULL, '3183.83000000', '-22.44800000', 'closed', '{\"user_id\":1,\"strategy_id\":\"FUT_ETH_H1_URSI_TREND\",\"ticker\":\"ETHUSDT\",\"timeframe\":\"60\",\"action\":\"BUY\",\"quantity\":\"0.244\",\"position_id\":\"1767708000\",\"leverage\":8,\"environment\":\"production\"}', '1767708000', '2026-01-06 12:00:08', '2026-01-06 15:00:13', '2026-01-06 15:00:13', 'bingx', NULL),
(163, 1, 6, '2008690098492674049', 'ETHUSDT', '60', 'BUY', 'futures', NULL, 'production', '0.24260000', '3294.97000000', '3294.97000000', 8, NULL, NULL, '3254.63000000', '-9.78648400', 'closed', '{\"user_id\":1,\"strategy_id\":\"FUT_ETH_H1_URSI_TREND\",\"ticker\":\"ETHUSDT\",\"timeframe\":\"60\",\"action\":\"BUY\",\"quantity\":\"0.2426\",\"position_id\":\"1767740400\",\"leverage\":8,\"environment\":\"production\"}', '1767740400', '2026-01-06 21:00:14', '2026-01-07 02:00:06', '2026-01-07 02:00:06', 'bingx', NULL),
(164, 1, 6, '2008810878538878977', 'ETHUSDT', '60', 'BUY', 'futures', NULL, 'production', '0.24570000', '3253.19000000', '3253.19000000', 8, NULL, NULL, '3196.31000000', '-13.97541600', 'closed', '{\"user_id\":1,\"strategy_id\":\"FUT_ETH_H1_URSI_TREND\",\"ticker\":\"ETHUSDT\",\"timeframe\":\"60\",\"action\":\"BUY\",\"quantity\":\"0.2457\",\"position_id\":\"1767769200\",\"leverage\":8,\"environment\":\"production\"}', '1767769200', '2026-01-07 05:00:10', '2026-01-07 07:00:09', '2026-01-07 07:00:09', 'bingx', NULL),
(165, 1, 4, '2009648593161424901', 'BTCUSDT', '60', 'BUY', 'futures', NULL, 'production', '0.00873000', '91615.90000000', '91615.90000000', 8, NULL, NULL, '91017.00000000', '-5.22839700', 'closed', '{\"user_id\":1,\"strategy_id\":\"FUT_BTC_H1_GANN\",\"ticker\":\"BTCUSDT\",\"timeframe\":\"60\",\"action\":\"BUY\",\"quantity\":\"0.00873\",\"position_id\":\"1767952800\",\"leverage\":8,\"environment\":\"production\"}', '1767952800', '2026-01-09 12:28:57', '2026-01-09 14:17:07', '2026-01-09 14:17:07', 'bingx', NULL),
(166, 1, 11, '2009648631199567873', 'BTCUSDT', '60', 'BUY', 'futures', NULL, 'production', '0.01547000', '91604.50000000', '91604.50000000', 8, NULL, NULL, '90295.20000000', '-20.25487100', 'closed', '{\"user_id\":1,\"strategy_id\":\"FUT_BTC_H1_TEMA_ST_BELOW_MA\",\"ticker\":\"BTCUSDT\",\"timeframe\":\"60\",\"action\":\"BUY\",\"quantity\":\"0.01547\",\"position_id\":\"1767952800\",\"leverage\":8,\"environment\":\"production\"}', '1767952800', '2026-01-09 12:29:06', '2026-01-09 15:31:21', '2026-01-09 15:31:21', 'bingx', NULL),
(167, 1, 8, '2009648633791647745', 'BTCUSDT', '60', 'BUY', 'futures', NULL, 'production', '0.01547000', '91600.40000000', '91600.40000000', 16, NULL, NULL, '90305.40000000', '-20.03365000', 'closed', '{\"user_id\":1,\"strategy_id\":\"FUT_BTC_H1_TEMA_ST_OVER_MA\",\"ticker\":\"BTCUSDT\",\"timeframe\":\"60\",\"action\":\"BUY\",\"quantity\":\"0.01547\",\"position_id\":\"1767952800\",\"leverage\":16,\"environment\":\"production\"}', '1767952800', '2026-01-09 12:29:06', '2026-01-09 15:31:21', '2026-01-09 15:31:21', 'bingx', NULL),
(168, 1, 6, '2009943331252998145', 'ETHUSDT', '60', 'BUY', 'futures', NULL, 'production', '0.25780000', '3101.32000000', '3101.32000000', 8, NULL, NULL, '3084.88000000', '-4.23823200', 'closed', '{\"user_id\":1,\"strategy_id\":\"FUT_ETH_H1_URSI_TREND\",\"ticker\":\"ETHUSDT\",\"timeframe\":\"60\",\"action\":\"BUY\",\"quantity\":\"0.2578\",\"position_id\":\"1768039200\",\"leverage\":8,\"environment\":\"production\"}', '1768039200', '2026-01-10 08:00:08', '2026-01-10 19:00:04', '2026-01-10 19:00:04', 'bingx', NULL),
(169, 1, 6, '2010290605371953153', 'ETHUSDT', '60', 'BUY', 'futures', NULL, 'production', '0.25760000', '3102.95000000', '3102.95000000', 8, NULL, NULL, '3105.25000000', '0.59248000', 'closed', '{\"user_id\":1,\"strategy_id\":\"FUT_ETH_H1_URSI_TREND\",\"ticker\":\"ETHUSDT\",\"timeframe\":\"60\",\"action\":\"BUY\",\"quantity\":\"0.2576\",\"position_id\":\"1768122000\",\"leverage\":8,\"environment\":\"production\"}', '1768122000', '2026-01-11 07:00:04', '2026-01-12 08:00:06', '2026-01-12 08:00:06', 'bingx', NULL),
(170, 1, 11, '2010517147419152385', 'BTCUSDT', '60', 'BUY', 'futures', NULL, 'production', '0.02190000', '91352.40000000', '91352.40000000', 8, NULL, NULL, '90644.70000000', '-15.49863000', 'closed', '{\"user_id\":1,\"strategy_id\":\"FUT_BTC_H1_TEMA_ST_BELOW_MA\",\"ticker\":\"BTCUSDT\",\"timeframe\":\"60\",\"action\":\"BUY\",\"quantity\":\"0.0219\",\"position_id\":\"1768165200\",\"leverage\":8,\"environment\":\"production\"}', '1768165200', '2026-01-11 22:00:16', '2026-01-12 05:37:36', '2026-01-12 05:37:36', 'bingx', NULL),
(171, 1, 8, '2010517147960217601', 'BTCUSDT', '60', 'BUY', 'futures', NULL, 'production', '0.02190000', '91352.20000000', '91352.20000000', 16, NULL, NULL, '90638.10000000', '-15.63879000', 'closed', '{\"user_id\":1,\"strategy_id\":\"FUT_BTC_H1_TEMA_ST_OVER_MA\",\"ticker\":\"BTCUSDT\",\"timeframe\":\"60\",\"action\":\"BUY\",\"quantity\":\"0.0219\",\"position_id\":\"1768165200\",\"leverage\":16,\"environment\":\"production\"}', '1768165200', '2026-01-11 22:00:16', '2026-01-12 05:37:36', '2026-01-12 05:37:36', 'bingx', NULL),
(172, 1, 4, '2010517151969972225', 'BTCUSDT', '60', 'BUY', 'futures', NULL, 'production', '0.00876000', '91361.50000000', '91361.50000000', 8, NULL, NULL, '91806.80000000', '3.90082800', 'closed', '{\"user_id\":1,\"strategy_id\":\"FUT_BTC_H1_GANN\",\"ticker\":\"BTCUSDT\",\"timeframe\":\"60\",\"action\":\"BUY\",\"quantity\":\"0.00876\",\"position_id\":\"1768168800\",\"leverage\":8,\"environment\":\"production\"}', '1768168800', '2026-01-11 22:00:17', '2026-01-12 04:00:07', '2026-01-12 04:00:07', 'bingx', NULL),
(173, 1, 10, '2010535994620645377', 'ETHUSDT', '60', 'BUY', 'futures', NULL, 'production', '0.25410000', '3143.09000000', '3143.09000000', 8, NULL, NULL, '3132.57000000', '-2.67313200', 'closed', '{\"user_id\":1,\"strategy_id\":\"FUT_ETH_H1_GANN\",\"ticker\":\"ETHUSDT\",\"timeframe\":\"60\",\"action\":\"BUY\",\"quantity\":\"0.2541\",\"position_id\":\"1768172400\",\"leverage\":8,\"environment\":\"production\"}', '1768172400', '2026-01-11 23:15:10', '2026-01-12 05:00:07', '2026-01-12 05:00:07', 'bingx', NULL),
(174, 1, 2, '2010547317945405441', 'BTCUSDT', '60', 'BUY', 'futures', NULL, 'production', '0.00867000', '92302.50000000', '92302.50000000', 8, NULL, NULL, '91376.90000000', '-8.02495200', 'closed', '{\"user_id\":1,\"strategy_id\":\"FUT_BTC_H1_TREND_ALIGNED_RSI\",\"ticker\":\"BTCUSDT\",\"timeframe\":\"60\",\"action\":\"BUY\",\"quantity\":\"0.00867\",\"position_id\":\"1768183200\",\"leverage\":8,\"environment\":\"production\"}', '1768183200', '2026-01-12 00:00:09', '2026-01-12 05:00:11', '2026-01-12 05:00:11', 'bingx', NULL),
(175, 1, 8, '2011005650930241537', 'BTCUSDT', '60', 'BUY', 'futures', NULL, 'production', '0.02112000', '92581.40000000', '92581.40000000', 16, NULL, NULL, '95208.10000000', '55.47590400', 'closed', '{\"user_id\":1,\"strategy_id\":\"FUT_BTC_H1_TEMA_ST_OVER_MA\",\"ticker\":\"BTCUSDT\",\"timeframe\":\"60\",\"action\":\"BUY\",\"quantity\":\"0.02112\",\"position_id\":\"1768222800\",\"leverage\":16,\"environment\":\"production\"}', '1768222800', '2026-01-13 06:21:24', '2026-01-13 22:00:07', '2026-01-13 22:00:07', 'bingx', NULL),
(176, 1, 6, '2011030485358088193', 'ETHUSDT', '60', 'BUY', 'futures', NULL, 'production', '0.25520000', '3133.69000000', '3133.69000000', 8, NULL, NULL, '3338.96000000', '52.38490400', 'closed', '{\"user_id\":1,\"strategy_id\":\"FUT_ETH_H1_URSI_TREND\",\"ticker\":\"ETHUSDT\",\"timeframe\":\"60\",\"action\":\"BUY\",\"quantity\":\"0.2552\",\"position_id\":\"1768298400\",\"leverage\":8,\"environment\":\"production\"}', '1768298400', '2026-01-13 08:00:06', '2026-01-14 15:00:09', '2026-01-14 15:00:09', 'bingx', NULL),
(177, 1, 2, '2011106044637351937', 'BTCUSDT', '60', 'BUY', 'futures', NULL, 'production', '0.00856000', '93461.10000000', '93461.10000000', 8, NULL, NULL, '95918.70000000', '21.03705600', 'closed', '{\"user_id\":1,\"strategy_id\":\"FUT_BTC_H1_TREND_ALIGNED_RSI\",\"ticker\":\"BTCUSDT\",\"timeframe\":\"60\",\"action\":\"BUY\",\"quantity\":\"0.00856\",\"position_id\":\"1768316400\",\"leverage\":8,\"environment\":\"production\"}', '1768316400', '2026-01-13 13:00:20', '2026-01-15 01:00:14', '2026-01-15 01:00:14', 'bingx', NULL),
(178, 1, 4, '2011450015939039233', 'BTCUSDT', '60', 'BUY', 'futures', NULL, 'production', '0.00829000', '96384.00000000', '96384.00000000', 8, NULL, NULL, '97284.30000000', '7.46348700', 'closed', '{\"user_id\":1,\"strategy_id\":\"FUT_BTC_H1_GANN\",\"ticker\":\"BTCUSDT\",\"timeframe\":\"60\",\"action\":\"BUY\",\"quantity\":\"0.00829\",\"position_id\":\"1768381200\",\"leverage\":8,\"environment\":\"production\"}', '1768381200', '2026-01-14 11:47:09', '2026-01-14 17:00:10', '2026-01-14 17:00:10', 'bingx', NULL),
(179, 1, 8, '2011450036478545921', 'BTCUSDT', '60', 'BUY', 'futures', NULL, 'production', '0.01539000', '96424.10000000', '96424.10000000', 16, NULL, NULL, '96654.30000000', '3.54277800', 'closed', '{\"user_id\":1,\"strategy_id\":\"FUT_BTC_H1_TEMA_ST_OVER_MA\",\"ticker\":\"BTCUSDT\",\"timeframe\":\"60\",\"action\":\"BUY\",\"quantity\":\"0.01539\",\"position_id\":\"1768395600\",\"leverage\":16,\"environment\":\"production\"}', '1768395600', '2026-01-14 11:47:14', '2026-01-14 22:00:06', '2026-01-14 22:00:06', 'bingx', NULL),
(180, 1, 10, '2011470906672549889', 'ETHUSDT', '60', 'BUY', 'futures', NULL, 'production', '0.23750000', '3364.54000000', '3364.54000000', 8, NULL, NULL, '3333.47000000', '-7.37912500', 'closed', '{\"user_id\":1,\"strategy_id\":\"FUT_ETH_H1_GANN\",\"ticker\":\"ETHUSDT\",\"timeframe\":\"60\",\"action\":\"BUY\",\"quantity\":\"0.2375\",\"position_id\":\"1768392000\",\"leverage\":8,\"environment\":\"production\"}', '1768392000', '2026-01-14 13:10:10', '2026-01-14 15:15:14', '2026-01-14 15:15:14', 'bingx', NULL),
(181, 1, 9, '2011470913584762881', 'ETHUSDT', '60', 'BUY', 'futures', NULL, 'production', '0.23750000', '3363.55000000', '3363.55000000', 16, NULL, NULL, '3341.68000000', '-5.19412500', 'closed', '{\"user_id\":1,\"strategy_id\":\"FUT_ETH_H1_TEMA_SUPERTREND\",\"ticker\":\"ETHUSDT\",\"timeframe\":\"60\",\"action\":\"BUY\",\"quantity\":\"0.2375\",\"position_id\":\"1768392000\",\"leverage\":16,\"environment\":\"production\"}', '1768392000', '2026-01-14 13:10:12', '2026-01-14 14:36:21', '2026-01-14 14:36:21', 'bingx', NULL),
(182, 1, 6, '2011860964449718273', 'ETHUSDT', '60', 'BUY', 'futures', NULL, 'production', '0.24230000', '3298.75000000', '3298.75000000', 8, NULL, NULL, '3286.28000000', '-3.02148100', 'closed', '{\"user_id\":1,\"strategy_id\":\"FUT_ETH_H1_URSI_TREND\",\"ticker\":\"ETHUSDT\",\"timeframe\":\"60\",\"action\":\"BUY\",\"quantity\":\"0.2423\",\"position_id\":\"1768496400\",\"leverage\":8,\"environment\":\"production\"}', '1768496400', '2026-01-15 15:00:07', '2026-01-15 16:00:08', '2026-01-15 16:00:08', 'bingx', NULL),
(183, 1, 6, '2012404533644234753', 'ETHUSDT', '60', 'BUY', 'futures', NULL, 'production', '0.24300000', '3289.94000000', '3289.94000000', 8, NULL, NULL, '3289.63000000', '-0.07533000', 'closed', '{\"user_id\":1,\"strategy_id\":\"FUT_ETH_H1_URSI_TREND\",\"ticker\":\"ETHUSDT\",\"timeframe\":\"60\",\"action\":\"BUY\",\"quantity\":\"0.243\",\"position_id\":\"1768626000\",\"leverage\":8,\"environment\":\"production\"}', '1768626000', '2026-01-17 03:00:04', '2026-01-17 05:00:05', '2026-01-17 05:00:05', 'bingx', NULL),
(184, 1, 6, '2012449839521992705', 'ETHUSDT', '60', 'BUY', 'futures', NULL, 'production', '0.24260000', '3295.17000000', '3295.17000000', 8, NULL, NULL, '3302.30000000', '1.72973800', 'closed', '{\"user_id\":1,\"strategy_id\":\"FUT_ETH_H1_URSI_TREND\",\"ticker\":\"ETHUSDT\",\"timeframe\":\"60\",\"action\":\"BUY\",\"quantity\":\"0.2426\",\"position_id\":\"1768636800\",\"leverage\":8,\"environment\":\"production\"}', '1768636800', '2026-01-17 06:00:06', '2026-01-18 00:00:05', '2026-01-18 00:00:05', 'bingx', NULL),
(185, 1, 10, '2012883020830216193', 'ETHUSDT', '60', 'BUY', 'futures', NULL, 'production', '0.24020000', '3327.16000000', '3327.16000000', 8, NULL, NULL, '3362.30000000', '8.44062800', 'closed', '{\"user_id\":1,\"strategy_id\":\"FUT_ETH_H1_GANN\",\"ticker\":\"ETHUSDT\",\"timeframe\":\"60\",\"action\":\"BUY\",\"quantity\":\"0.2402\",\"position_id\":\"1768694400\",\"leverage\":8,\"environment\":\"production\"}', '1768694400', '2026-01-18 10:41:24', '2026-01-18 16:00:06', '2026-01-18 16:00:06', 'bingx', NULL),
(186, 1, 6, '2012917923324104705', 'ETHUSDT', '60', 'BUY', 'futures', NULL, 'production', '0.23990000', '3332.15000000', '3332.15000000', 8, NULL, NULL, '3207.07000000', '-30.00669200', 'closed', '{\"user_id\":1,\"strategy_id\":\"FUT_ETH_H1_URSI_TREND\",\"ticker\":\"ETHUSDT\",\"timeframe\":\"60\",\"action\":\"BUY\",\"quantity\":\"0.2399\",\"position_id\":\"1768748400\",\"leverage\":8,\"environment\":\"production\"}', '1768748400', '2026-01-18 13:00:06', '2026-01-18 22:00:13', '2026-01-18 22:00:13', 'bingx', NULL),
(187, 1, 10, '2014059900853817345', 'ETHUSDT', '60', 'BUY', 'futures', NULL, 'production', '0.26430000', '3028.95000000', '3028.95000000', 8, NULL, NULL, '2986.64000000', '-11.18253300', 'closed', '{\"user_id\":1,\"strategy_id\":\"FUT_ETH_H1_GANN\",\"ticker\":\"ETHUSDT\",\"timeframe\":\"60\",\"action\":\"BUY\",\"quantity\":\"0.2643\",\"position_id\":\"1769018400\",\"leverage\":8,\"environment\":\"production\"}', '1769018400', '2026-01-21 16:37:54', '2026-01-21 20:42:57', '2026-01-21 20:42:57', 'bingx', NULL),
(188, 1, 11, '2014740275406049281', 'BTCUSDT', '60', 'BUY', 'futures', NULL, 'production', '0.01627000', '90320.60000000', '90320.60000000', 8, NULL, NULL, '89568.20000000', '-12.24154800', 'closed', '{\"user_id\":1,\"strategy_id\":\"FUT_BTC_H1_TEMA_ST_BELOW_MA\",\"ticker\":\"BTCUSDT\",\"timeframe\":\"60\",\"action\":\"BUY\",\"quantity\":\"0.01627\",\"position_id\":\"1769169600\",\"leverage\":8,\"environment\":\"production\"}', '1769169600', '2026-01-23 13:41:28', '2026-01-24 05:00:04', '2026-01-24 05:00:04', 'bingx', NULL),
(189, 1, 4, '2014751510566539267', 'BTCUSDT', '60', 'BUY', 'futures', NULL, 'production', '0.00883000', '90658.50000000', '90658.50000000', 8, NULL, NULL, '90027.90000000', '-5.56819800', 'closed', '{\"user_id\":1,\"strategy_id\":\"FUT_BTC_H1_GANN\",\"ticker\":\"BTCUSDT\",\"timeframe\":\"60\",\"action\":\"BUY\",\"quantity\":\"0.00883\",\"position_id\":\"1769162400\",\"leverage\":8,\"environment\":\"production\"}', '1769162400', '2026-01-23 14:26:07', '2026-01-23 16:16:07', '2026-01-23 16:16:07', 'bingx', NULL),
(190, 1, 9, '2016180970478112769', 'ETHUSDT', '60', 'BUY', 'futures', NULL, 'production', '0.27050000', '2961.55000000', '2961.55000000', 16, NULL, NULL, '2933.65000000', '-7.54695000', 'closed', '{\"user_id\":1,\"strategy_id\":\"FUT_ETH_H1_TEMA_SUPERTREND\",\"ticker\":\"ETHUSDT\",\"timeframe\":\"60\",\"action\":\"BUY\",\"quantity\":\"0.2705\",\"position_id\":\"1769508000\",\"leverage\":16,\"environment\":\"production\"}', '1769508000', '2026-01-27 13:06:17', '2026-01-27 14:19:06', '2026-01-27 14:19:06', 'bingx', NULL),
(191, 1, 10, '2016180972109697025', 'ETHUSDT', '60', 'BUY', 'futures', NULL, 'production', '0.27050000', '2961.94000000', '2961.94000000', 8, NULL, NULL, '2925.29000000', '-9.91382500', 'closed', '{\"user_id\":1,\"strategy_id\":\"FUT_ETH_H1_GANN\",\"ticker\":\"ETHUSDT\",\"timeframe\":\"60\",\"action\":\"BUY\",\"quantity\":\"0.2705\",\"position_id\":\"1769504400\",\"leverage\":8,\"environment\":\"production\"}', '1769504400', '2026-01-27 13:06:17', '2026-01-27 14:42:47', '2026-01-27 14:42:47', 'bingx', NULL),
(192, 1, 4, '2016244933882548225', 'BTCUSDT', '60', 'BUY', 'futures', NULL, 'production', '0.00898000', '88997.90000000', '88997.90000000', 8, NULL, NULL, '89350.00000000', '3.16185800', 'closed', '{\"user_id\":1,\"strategy_id\":\"FUT_BTC_H1_GANN\",\"ticker\":\"BTCUSDT\",\"timeframe\":\"60\",\"action\":\"BUY\",\"quantity\":\"0.00898\",\"position_id\":\"1769508000\",\"leverage\":8,\"environment\":\"production\"}', '1769508000', '2026-01-27 17:20:27', '2026-01-27 23:00:05', '2026-01-27 23:00:05', 'bingx', NULL),
(193, 1, 11, '2016244947933466625', 'BTCUSDT', '60', 'BUY', 'futures', NULL, 'production', '0.01748000', '89128.80000000', '89128.80000000', 8, NULL, NULL, '90045.90000000', '16.03090800', 'closed', '{\"user_id\":1,\"strategy_id\":\"FUT_BTC_H1_TEMA_ST_BELOW_MA\",\"ticker\":\"BTCUSDT\",\"timeframe\":\"60\",\"action\":\"BUY\",\"quantity\":\"0.01748\",\"position_id\":\"1769504400\",\"leverage\":8,\"environment\":\"production\"}', '1769504400', '2026-01-27 17:20:30', '2026-01-28 09:00:08', '2026-01-28 09:00:08', 'bingx', NULL),
(194, 1, 4, '2016451227692306433', 'BTCUSDT', '60', 'BUY', 'futures', NULL, 'production', '0.00893000', '89531.60000000', '89531.60000000', 8, NULL, NULL, '89577.90000000', '0.41345900', 'closed', '{\"user_id\":1,\"strategy_id\":\"FUT_BTC_H1_GANN\",\"ticker\":\"BTCUSDT\",\"timeframe\":\"60\",\"action\":\"BUY\",\"quantity\":\"0.00893\",\"position_id\":\"1769587200\",\"leverage\":8,\"environment\":\"production\"}', '1769587200', '2026-01-28 07:00:11', '2026-01-28 12:00:10', '2026-01-28 12:00:10', 'bingx', NULL),
(195, 1, 10, '2016455028897746945', 'ETHUSDT', '60', 'BUY', 'futures', NULL, 'production', '0.26350000', '3035.66000000', '3035.66000000', 8, NULL, NULL, '3004.12000000', '-8.31079000', 'closed', '{\"user_id\":1,\"strategy_id\":\"FUT_ETH_H1_GANN\",\"ticker\":\"ETHUSDT\",\"timeframe\":\"60\",\"action\":\"BUY\",\"quantity\":\"0.2635\",\"position_id\":\"1769580000\",\"leverage\":8,\"environment\":\"production\"}', '1769580000', '2026-01-28 07:15:17', '2026-01-28 12:10:27', '2026-01-28 12:10:27', 'bingx', NULL),
(198, 1, 6, '2036699640232939520', 'ETHUSDT', '60', 'BUY', 'futures', NULL, 'production', '0.36970000', '2162.19000000', '2162.19000000', 8, NULL, NULL, '2174.47000000', '4.53991600', 'closed', '{\"user_id\":1,\"strategy_id\":\"FUT_ETH_H1_URSI_TREND\",\"ticker\":\"ETHUSDT\",\"timeframe\":\"60\",\"action\":\"BUY\",\"quantity\":\"0.3697\",\"position_id\":\"1774418400\",\"leverage\":8,\"environment\":\"production\"}', '1774418400', '2026-03-25 04:00:09', '2026-03-25 20:00:05', '2026-03-25 20:00:05', 'bingx', NULL),
(199, 1, 10, '2038447550284959744', 'ETHUSDT', '60', 'BUY', 'futures', NULL, 'production', '0.39530000', '2023.00000000', '2036.67000000', 8, NULL, NULL, '2058.96000000', '14.21498800', 'closed', '{\"user_id\":1,\"strategy_id\":\"FUT_ETH_H1_GANN\",\"ticker\":\"ETHUSDT\",\"timeframe\":\"60\",\"action\":\"BUY\",\"quantity\":\"0.3953\",\"position_id\":\"1774828800\",\"leverage\":8,\"environment\":\"production\"}', '1774828800', '2026-03-29 23:45:43', '2026-03-30 05:00:07', '2026-03-30 05:00:07', 'bingx', NULL),
(200, 1, 11, '2038447674553798656', 'BTCUSDT', '60', 'BUY', 'futures', NULL, 'production', '0.02398000', '67330.30000000', '67428.00000000', 8, NULL, NULL, '67345.90000000', '0.37408800', 'closed', '{\"user_id\":1,\"strategy_id\":\"FUT_BTC_H1_TEMA_ST_BELOW_MA\",\"ticker\":\"BTCUSDT\",\"timeframe\":\"60\",\"action\":\"BUY\",\"quantity\":\"0.02398\",\"position_id\":\"1774796400\",\"leverage\":8,\"environment\":\"production\"}', '1774796400', '2026-03-29 23:46:13', '2026-03-30 14:00:13', '2026-03-30 14:00:13', 'bingx', NULL),
(201, 1, 4, '2038447688873152512', 'BTCUSDT', '60', 'BUY', 'futures', NULL, 'production', '0.01188000', '67377.30000000', '67189.20000000', 8, NULL, NULL, '67584.80000000', '2.46510000', 'closed', '{\"user_id\":1,\"strategy_id\":\"FUT_BTC_H1_GANN\",\"ticker\":\"BTCUSDT\",\"timeframe\":\"60\",\"action\":\"BUY\",\"quantity\":\"0.01188\",\"position_id\":\"1774807200\",\"leverage\":8,\"environment\":\"production\"}', '1774807200', '2026-03-29 23:46:16', '2026-03-30 05:00:09', '2026-03-30 05:00:09', 'bingx', NULL),
(202, 1, 9, '2038791824121794560', 'ETHUSDT', '60', 'BUY', 'futures', NULL, 'production', '0.38360000', '2084.22000000', '2078.09000000', 16, NULL, NULL, '2065.52000000', '-7.17332000', 'closed', '{\"user_id\":1,\"strategy_id\":\"FUT_ETH_H1_TEMA_SUPERTREND\",\"ticker\":\"ETHUSDT\",\"timeframe\":\"60\",\"action\":\"BUY\",\"quantity\":\"0.3836\",\"position_id\":\"1774897200\",\"leverage\":16,\"environment\":\"production\"}', '1774897200', '2026-03-30 22:33:44', '2026-03-31 00:20:29', '2026-03-31 00:20:29', 'bingx', NULL),
(203, 1, 10, '2038791849732214784', 'ETHUSDT', '60', 'BUY', 'futures', NULL, 'production', '0.38360000', '2082.03000000', '2078.09000000', 8, NULL, NULL, '2060.89000000', '-8.10930400', 'closed', '{\"user_id\":1,\"strategy_id\":\"FUT_ETH_H1_GANN\",\"ticker\":\"ETHUSDT\",\"timeframe\":\"60\",\"action\":\"BUY\",\"quantity\":\"0.3836\",\"position_id\":\"1774897200\",\"leverage\":8,\"environment\":\"production\"}', '1774897200', '2026-03-30 22:33:50', '2026-03-31 00:42:48', '2026-03-31 00:42:48', 'bingx', NULL),
(204, 1, 4, '2038791925921746944', 'BTCUSDT', '60', 'BUY', 'futures', NULL, 'production', '0.01173000', '68324.00000000', '68016.90000000', 8, NULL, NULL, '67633.10000000', '-8.10425700', 'closed', '{\"user_id\":1,\"strategy_id\":\"FUT_BTC_H1_GANN\",\"ticker\":\"BTCUSDT\",\"timeframe\":\"60\",\"action\":\"BUY\",\"quantity\":\"0.01173\",\"position_id\":\"1774897200\",\"leverage\":8,\"environment\":\"production\"}', '1774897200', '2026-03-30 22:34:09', '2026-03-31 00:39:39', '2026-03-31 00:39:39', 'bingx', NULL),
(205, 1, 11, '2038791949934137344', 'BTCUSDT', '60', 'BUY', 'futures', NULL, 'production', '0.01817000', '68350.90000000', '68016.90000000', 8, NULL, NULL, '67079.20000000', '-23.10678900', 'closed', '{\"user_id\":1,\"strategy_id\":\"FUT_BTC_H1_TEMA_ST_BELOW_MA\",\"ticker\":\"BTCUSDT\",\"timeframe\":\"60\",\"action\":\"BUY\",\"quantity\":\"0.01817\",\"position_id\":\"1774893600\",\"leverage\":8,\"environment\":\"production\"}', '1774893600', '2026-03-30 22:34:14', '2026-03-31 05:39:15', '2026-03-31 05:39:15', 'bingx', NULL),
(206, 1, 9, '2039020204058480640', 'ETHUSDT', '60', 'BUY', 'futures', NULL, 'production', '0.38230000', '2093.37000000', '2111.29000000', 16, NULL, NULL, '2101.56000000', '3.13103700', 'closed', '{\"user_id\":1,\"strategy_id\":\"FUT_ETH_H1_TEMA_SUPERTREND\",\"ticker\":\"ETHUSDT\",\"timeframe\":\"60\",\"action\":\"BUY\",\"quantity\":\"0.3823\",\"position_id\":\"1774947600\",\"leverage\":16,\"environment\":\"production\"}', '1774947600', '2026-03-31 13:41:14', '2026-03-31 19:00:06', '2026-03-31 19:00:06', 'bingx', NULL),
(207, 1, 10, '2039020229765369856', 'ETHUSDT', '60', 'BUY', 'futures', NULL, 'production', '0.38230000', '2095.85000000', '2111.29000000', 8, NULL, NULL, '2101.63000000', '2.20969400', 'closed', '{\"user_id\":1,\"strategy_id\":\"FUT_ETH_H1_GANN\",\"ticker\":\"ETHUSDT\",\"timeframe\":\"60\",\"action\":\"BUY\",\"quantity\":\"0.3823\",\"position_id\":\"1774951200\",\"leverage\":8,\"environment\":\"production\"}', '1774951200', '2026-03-31 13:41:21', '2026-03-31 19:00:07', '2026-03-31 19:00:07', 'bingx', NULL),
(208, 1, 4, '2039028642402537472', 'BTCUSDT', '60', 'BUY', 'futures', NULL, 'production', '0.01169000', '68374.80000000', '68043.40000000', 8, NULL, NULL, '67515.60000000', '-10.04404800', 'closed', '{\"user_id\":1,\"strategy_id\":\"FUT_BTC_H1_GANN\",\"ticker\":\"BTCUSDT\",\"timeframe\":\"60\",\"action\":\"BUY\",\"quantity\":\"0.01169\",\"position_id\":\"1774954800\",\"leverage\":8,\"environment\":\"production\"}', '1774954800', '2026-03-31 14:14:46', '2026-03-31 15:00:12', '2026-03-31 15:00:12', 'bingx', NULL),
(209, 1, 6, '2039085351305220096', 'ETHUSDT', '60', 'BUY', 'futures', NULL, 'production', '0.37950000', '2105.99000000', '2105.99000000', 8, NULL, NULL, '2141.81000000', '13.59369000', 'closed', '{\"user_id\":1,\"strategy_id\":\"FUT_ETH_H1_URSI_TREND\",\"ticker\":\"ETHUSDT\",\"timeframe\":\"60\",\"action\":\"BUY\",\"quantity\":\"0.3795\",\"position_id\":\"1774987200\",\"leverage\":8,\"environment\":\"production\"}', '1774987200', '2026-03-31 18:00:07', '2026-04-01 18:00:05', '2026-04-01 18:00:05', 'bingx', NULL),
(210, 1, 10, '2039212276019367936', 'ETHUSDT', '60', 'BUY', 'futures', NULL, 'production', '0.37660000', '2122.78000000', '2122.78000000', 8, NULL, NULL, '2131.22000000', '3.17850400', 'closed', '{\"user_id\":1,\"strategy_id\":\"FUT_ETH_H1_GANN\",\"ticker\":\"ETHUSDT\",\"timeframe\":\"60\",\"action\":\"BUY\",\"quantity\":\"0.3766\",\"position_id\":\"1775012400\",\"leverage\":8,\"environment\":\"production\"}', '1775012400', '2026-04-01 02:24:28', '2026-04-01 08:00:07', '2026-04-01 08:00:07', 'bingx', NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(100) NOT NULL,
  `role` enum('admin','user') NOT NULL DEFAULT 'user',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `module_bingx` tinyint(1) NOT NULL DEFAULT 0,
  `module_metatrader` tinyint(1) NOT NULL DEFAULT 0,
  `module_atvip` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `email`, `role`, `created_at`, `updated_at`, `module_bingx`, `module_metatrader`, `module_atvip`) VALUES
(1, 'admin', '$2y$10$6ycCI42StRpPm77/Rsgv5.yUB0t0Wdi7ALBL5FYOC2wYXBcXEXOom', 'admin@example.com', 'admin', '2025-04-01 21:07:56', '2026-03-31 11:56:52', 1, 1, 1),
(2, 'javier', '$2y$10$wdMYUGL7PIDA5yd6FqAhPuHgBt/qsaow.JFTxIjw0qtIDmjDtverW', 'javier@gmail.com', 'admin', '2025-04-01 21:44:45', '2026-04-03 01:00:00', 1, 1, 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `user_selected_tickers`
--

CREATE TABLE `user_selected_tickers` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `ticker_symbol` varchar(20) NOT NULL,
  `mt_ticker` varchar(50) DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `user_selected_tickers`
--

INSERT INTO `user_selected_tickers` (`id`, `user_id`, `ticker_symbol`, `mt_ticker`, `active`, `created_at`, `updated_at`) VALUES
(1, 1, 'EURUSD', 'EURUSD', 1, '2025-09-03 00:50:06', '2026-02-12 15:03:44'),
(3, 1, 'GC', 'XAUUSD', 0, '2025-09-03 15:03:40', '2025-12-19 00:32:38'),
(4, 1, 'NQ', 'US100', 0, '2025-09-10 19:38:40', '2025-12-19 00:32:40'),
(5, 1, 'ES', 'US500', 0, '2025-09-10 19:39:04', '2025-12-19 00:32:35'),
(6, 1, 'CL', 'USOIL', 0, '2025-09-10 19:39:20', '2025-12-19 00:32:33'),
(7, 1, 'RTY', 'US2000', 0, '2025-10-05 16:58:12', '2025-12-19 00:32:41'),
(8, 2, 'EURUSD', 'EURSD', 1, '2026-04-03 00:27:01', NULL),
(9, 2, 'NQ', 'US100', 1, '2026-04-03 00:59:27', NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `user_telegram_signals`
--

CREATE TABLE `user_telegram_signals` (
  `id` int(11) NOT NULL,
  `telegram_signal_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `ticker_symbol` varchar(20) NOT NULL,
  `mt_ticker` varchar(50) NOT NULL,
  `status` enum('available','claimed','pending','open','closed','failed_execution','expired','cancelled') DEFAULT 'available',
  `execution_data` text DEFAULT NULL,
  `event_log` text DEFAULT NULL,
  `mt_execution_data` text DEFAULT NULL COMMENT 'Original MT5 signal data (entry, stoploss, tps) sent by EA at open',
  `mt_corrected_data` text DEFAULT NULL COMMENT 'Post-correction MT5 prices (entry, stoploss, tps) applied by EA',
  `trade_id` varchar(100) DEFAULT NULL,
  `real_entry_price` decimal(10,5) DEFAULT NULL,
  `real_stop_loss` decimal(10,5) DEFAULT NULL,
  `real_volume` decimal(8,2) DEFAULT NULL,
  `order_type` varchar(20) DEFAULT NULL,
  `current_level` int(11) DEFAULT -2,
  `volume_closed_percent` decimal(5,2) DEFAULT 0.00,
  `remaining_volume` decimal(8,2) DEFAULT NULL,
  `gross_pnl` decimal(10,2) DEFAULT 0.00,
  `last_price` decimal(10,5) DEFAULT NULL,
  `close_reason` varchar(50) DEFAULT NULL,
  `exit_level` int(2) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `user_telegram_signals`
--

INSERT INTO `user_telegram_signals` (`id`, `telegram_signal_id`, `user_id`, `ticker_symbol`, `mt_ticker`, `status`, `execution_data`, `event_log`, `mt_execution_data`, `trade_id`, `real_entry_price`, `real_stop_loss`, `real_volume`, `order_type`, `current_level`, `volume_closed_percent`, `remaining_volume`, `gross_pnl`, `last_price`, `close_reason`, `exit_level`, `created_at`, `updated_at`) VALUES
(1, 1, 2, 'NQ', 'US100', 'claimed', NULL, '[{\"event\":\"claimed\",\"at\":\"2026-04-03 01:02:11\"}]', NULL, NULL, NULL, NULL, NULL, NULL, -2, '0.00', NULL, '0.00', NULL, NULL, NULL, '2026-04-03 01:02:09', '2026-04-03 01:02:11');

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `api_keys`
--
ALTER TABLE `api_keys`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indices de la tabla `available_tickers`
--
ALTER TABLE `available_tickers`
  ADD PRIMARY KEY (`symbol`);

--
-- Indices de la tabla `mt_signals`
--
ALTER TABLE `mt_signals`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `strategy_id` (`strategy_id`),
  ADD KEY `idx_status_user` (`status`,`user_id`),
  ADD KEY `idx_created` (`created_at`);

--
-- Indices de la tabla `strategies`
--
ALTER TABLE `strategies`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_strategy` (`user_id`,`strategy_id`);

--
-- Indices de la tabla `system_logs`
--
ALTER TABLE `system_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indices de la tabla `telegram_signals`
--
ALTER TABLE `telegram_signals`
  ADD PRIMARY KEY (`id`),
  ADD KEY `ticker_symbol` (`ticker_symbol`),
  ADD KEY `idx_processed_created` (`created_at`),
  ADD KEY `idx_ticker_created` (`ticker_symbol`,`created_at`);

--
-- Indices de la tabla `trades`
--
ALTER TABLE `trades`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `strategy_id` (`strategy_id`),
  ADD KEY `idx_position_id` (`position_id`),
  ADD KEY `idx_platform_status` (`status`),
  ADD KEY `fk_mt_signal` (`mt_signal_id`),
  ADD KEY `idx_trades_source` (`source`),
  ADD KEY `idx_trades_user_signal_id` (`user_signal_id`);

--
-- Indices de la tabla `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indices de la tabla `user_selected_tickers`
--
ALTER TABLE `user_selected_tickers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_ticker` (`user_id`,`ticker_symbol`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `ticker_symbol` (`ticker_symbol`);

--
-- Indices de la tabla `user_telegram_signals`
--
ALTER TABLE `user_telegram_signals`
  ADD PRIMARY KEY (`id`),
  ADD KEY `telegram_signal_id` (`telegram_signal_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `status` (`status`),
  ADD KEY `idx_user_ticker` (`user_id`,`ticker_symbol`),
  ADD KEY `idx_status_ticker` (`status`,`ticker_symbol`),
  ADD KEY `idx_user_status` (`user_id`,`status`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `api_keys`
--
ALTER TABLE `api_keys`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `mt_signals`
--
ALTER TABLE `mt_signals`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `strategies`
--
ALTER TABLE `strategies`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT de la tabla `system_logs`
--
ALTER TABLE `system_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de la tabla `telegram_signals`
--
ALTER TABLE `telegram_signals`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `trades`
--
ALTER TABLE `trades`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=211;

--
-- AUTO_INCREMENT de la tabla `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `user_selected_tickers`
--
ALTER TABLE `user_selected_tickers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT de la tabla `user_telegram_signals`
--
ALTER TABLE `user_telegram_signals`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
