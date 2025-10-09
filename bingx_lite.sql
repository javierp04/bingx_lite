-- phpMyAdmin SQL Dump
-- version 5.0.4deb2+deb11u2
-- https://www.phpmyadmin.net/
--
-- Servidor: localhost:3306
-- Tiempo de generación: 09-10-2025 a las 00:41:13
-- Versión del servidor: 10.5.29-MariaDB-0+deb11u1
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
('BTCUSD', 'Bitcoin / US Dollar', 8, 1, '2025-09-03 00:56:26', '2025-10-09 00:25:38'),
('CL', 'NYMEX:CL1! - Futuros de Crudo Ligero', 5, 1, '2025-09-03 00:58:15', '2025-09-03 00:58:41'),
('ES', 'ES1! - Futuros S&P 500', 5, 1, '2025-09-03 01:01:45', '2025-09-03 01:02:37'),
('EURUSD', 'Euro/US Dollar', 5, 1, '2025-09-03 00:48:21', NULL),
('GC', 'COMEX:GC1! - Futuros de Oro', 5, 1, '2025-09-03 00:58:01', '2025-09-03 00:58:59'),
('NQ', 'NQ1! - Futuros NASDAQ 100', 5, 1, '2025-09-03 01:02:26', NULL),
('RTY', 'RTY1! - Futuros Russell 2000', 5, 1, '2025-09-03 01:03:02', NULL),
('USDBRL', 'US Dollar / Brazilean Real', 5, 1, '2025-09-03 00:57:15', NULL),
('VIX', 'TVC:VIX', 5, 1, '2025-09-03 01:01:21', NULL),
('ZS', 'CBOT:ZS1! - Futuros de Soja', 5, 1, '2025-09-03 00:57:47', '2025-09-03 00:59:22');

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
  `status` enum('pending','cropping','analyzing','completed','failed_crop','failed_analysis','failed_download') DEFAULT 'pending',
  `analysis_data` text DEFAULT NULL,
  `op_type` varchar(10) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `telegram_signals`
--

INSERT INTO `telegram_signals` (`id`, `ticker_symbol`, `image_path`, `tradingview_url`, `message_text`, `webhook_raw_data`, `created_at`, `updated_at`, `status`, `analysis_data`, `op_type`) VALUES
(4, 'GC', 'uploads/trades/2025-10-07_GC.png', 'https://www.tradingview.com/x/udbu4uR4/', 'Sentimiento #GC https://www.tradingview.com/x/udbu4uR4/', '{\"update_id\":219260780,\n\"message\":{\"message_id\":186,\"from\":{\"id\":671627305,\"is_bot\":false,\"first_name\":\"Javier\",\"username\":\"javi_pel\",\"language_code\":\"es\"},\"chat\":{\"id\":-1003019203652,\"title\":\"Test Signal Generator\",\"type\":\"supergroup\"},\"date\":1759839043,\"text\":\"Sentimiento #GC https://www.tradingview.com/x/udbu4uR4/\",\"entities\":[{\"offset\":12,\"length\":3,\"type\":\"hashtag\"},{\"offset\":16,\"length\":39,\"type\":\"url\"}],\"link_preview_options\":{\"url\":\"https://www.tradingview.com/x/udbu4uR4/\"}}}', '2025-10-07 09:10:44', '2025-10-07 09:10:49', 'completed', '{\"op_type\":\"LONG\",\"stoploss\":[3974.6,3983.6],\"entry\":3991.6,\"tps\":[3997.1,4001.9,4006.9,4014.2,4030.9]}', 'LONG'),
(5, 'USDBRL', 'uploads/trades/2025-10-07_USDBRL.png', 'https://www.tradingview.com/x/oxPtBPkN/', 'Sentimiento #USDBRL https://www.tradingview.com/x/oxPtBPkN/', '{\"update_id\":219260781,\n\"message\":{\"message_id\":187,\"from\":{\"id\":671627305,\"is_bot\":false,\"first_name\":\"Javier\",\"username\":\"javi_pel\",\"language_code\":\"es\"},\"chat\":{\"id\":-1003019203652,\"title\":\"Test Signal Generator\",\"type\":\"supergroup\"},\"date\":1759839091,\"text\":\"Sentimiento #USDBRL https://www.tradingview.com/x/oxPtBPkN/\",\"entities\":[{\"offset\":12,\"length\":7,\"type\":\"hashtag\"},{\"offset\":20,\"length\":39,\"type\":\"url\"}],\"link_preview_options\":{\"url\":\"https://www.tradingview.com/x/oxPtBPkN/\"}}}', '2025-10-07 09:11:32', '2025-10-07 09:11:36', 'completed', '{\"op_type\":\"SHORT\",\"stoploss\":[5.3715,5.3483],\"entry\":5.3312,\"tps\":[5.3216,5.3138,5.306,5.298,5.2902]}', 'SHORT'),
(6, 'EURUSD', 'uploads/trades/2025-10-07_EURUSD.png', 'https://www.tradingview.com/x/UADNxfFy/', 'Sentimiento #EURUSD https://www.tradingview.com/x/UADNxfFy/', '{\"update_id\":219260782,\n\"message\":{\"message_id\":188,\"from\":{\"id\":671627305,\"is_bot\":false,\"first_name\":\"Javier\",\"username\":\"javi_pel\",\"language_code\":\"es\"},\"chat\":{\"id\":-1003019203652,\"title\":\"Test Signal Generator\",\"type\":\"supergroup\"},\"date\":1759839104,\"text\":\"Sentimiento #EURUSD https://www.tradingview.com/x/UADNxfFy/\",\"entities\":[{\"offset\":12,\"length\":7,\"type\":\"hashtag\"},{\"offset\":20,\"length\":39,\"type\":\"url\"}],\"link_preview_options\":{\"url\":\"https://www.tradingview.com/x/UADNxfFy/\"}}}', '2025-10-07 09:11:44', '2025-10-07 09:11:51', 'completed', '{\"op_type\":\"LONG\",\"stoploss\":[1.1628,1.16458],\"entry\":1.16554,\"tps\":[1.1663,1.16707,1.1679,1.16891,1.17122]}', 'LONG'),
(7, 'CL', 'uploads/trades/2025-10-07_CL.png', 'https://www.tradingview.com/x/nC72ljEh/', 'Sentimiento #CL https://www.tradingview.com/x/nC72ljEh/', '{\"update_id\":474204672,\n\"message\":{\"message_id\":112219,\"from\":{\"id\":603500055,\"is_bot\":false,\"first_name\":\"Favio\",\"last_name\":\"Schneeberger\",\"username\":\"FavioSchneeberger\"},\"chat\":{\"id\":-1001196812259,\"title\":\"AT VIP Canal\",\"type\":\"supergroup\"},\"date\":1759839127,\"text\":\"Sentimiento #CL https://www.tradingview.com/x/nC72ljEh/\",\"entities\":[{\"offset\":12,\"length\":3,\"type\":\"hashtag\"},{\"offset\":16,\"length\":39,\"type\":\"url\"}],\"link_preview_options\":{\"url\":\"https://www.tradingview.com/x/nC72ljEh/\"}}}', '2025-10-07 09:12:08', '2025-10-07 09:12:12', 'completed', '{\"op_type\":\"LONG\",\"stoploss\":[60.48,60.68],\"entry\":60.83,\"tps\":[61.01,61.14,61.28,61.48,61.65]}', 'LONG'),
(8, 'ZS', 'uploads/trades/2025-10-07_ZS.png', 'https://www.tradingview.com/x/BUyU2jNc/', 'Sentimiento #ZS https://www.tradingview.com/x/BUyU2jNc/', '{\"update_id\":474204674,\n\"message\":{\"message_id\":112221,\"from\":{\"id\":603500055,\"is_bot\":false,\"first_name\":\"Favio\",\"last_name\":\"Schneeberger\",\"username\":\"FavioSchneeberger\"},\"chat\":{\"id\":-1001196812259,\"title\":\"AT VIP Canal\",\"type\":\"supergroup\"},\"date\":1759839511,\"text\":\"Sentimiento #ZS https://www.tradingview.com/x/BUyU2jNc/\",\"entities\":[{\"offset\":12,\"length\":3,\"type\":\"hashtag\"},{\"offset\":16,\"length\":39,\"type\":\"url\"}],\"link_preview_options\":{\"url\":\"https://www.tradingview.com/x/BUyU2jNc/\"}}}', '2025-10-07 09:18:32', '2025-10-07 09:18:37', 'completed', '{\"op_type\":\"SHORT\",\"stoploss\":[1030.6,1025.6],\"entry\":1023,\"tps\":[1020.6,1019.2,1017.4,1016,1013.4]}', 'SHORT'),
(9, 'BTCUSD', 'uploads/trades/2025-10-07_BTCUSD.png', 'https://www.tradingview.com/x/DVwrD7sI/', 'Sentimiento #BTCusd (M45) https://www.tradingview.com/x/DVwrD7sI/', '{\"update_id\":474204676,\n\"message\":{\"message_id\":112223,\"from\":{\"id\":603500055,\"is_bot\":false,\"first_name\":\"Favio\",\"last_name\":\"Schneeberger\",\"username\":\"FavioSchneeberger\"},\"chat\":{\"id\":-1001196812259,\"title\":\"AT VIP Canal\",\"type\":\"supergroup\"},\"date\":1759840163,\"text\":\"Sentimiento #BTCusd (M45) https://www.tradingview.com/x/DVwrD7sI/\",\"entities\":[{\"offset\":12,\"length\":7,\"type\":\"hashtag\"},{\"offset\":26,\"length\":39,\"type\":\"url\"}],\"link_preview_options\":{\"url\":\"https://www.tradingview.com/x/DVwrD7sI/\"}}}', '2025-10-07 09:29:24', '2025-10-07 09:29:28', 'completed', '{\"op_type\":\"LONG\",\"stoploss\":[122.34,123.4],\"entry\":124.524,\"tps\":[125.025,125.486,125.946,126.498,127.36]}', 'LONG'),
(10, 'VIX', 'uploads/trades/2025-10-07_VIX.png', 'https://www.tradingview.com/x/J35UL8bv/', 'Sentimiento #VIX https://www.tradingview.com/x/J35UL8bv/', '{\"update_id\":474204683,\n\"message\":{\"message_id\":112230,\"from\":{\"id\":603500055,\"is_bot\":false,\"first_name\":\"Favio\",\"last_name\":\"Schneeberger\",\"username\":\"FavioSchneeberger\"},\"chat\":{\"id\":-1001196812259,\"title\":\"AT VIP Canal\",\"type\":\"supergroup\"},\"date\":1759840370,\"text\":\"Sentimiento #VIX https://www.tradingview.com/x/J35UL8bv/\",\"entities\":[{\"offset\":12,\"length\":4,\"type\":\"hashtag\"},{\"offset\":17,\"length\":39,\"type\":\"url\"}],\"link_preview_options\":{\"url\":\"https://www.tradingview.com/x/J35UL8bv/\"}}}', '2025-10-07 09:32:52', '2025-10-07 09:32:55', 'completed', '{\"op_type\":\"SHORT\",\"stoploss\":[16.92,16.6],\"entry\":16.4,\"tps\":[16.27,16.1,15.92,15.7,15.45]}', 'SHORT'),
(11, 'ES', 'uploads/trades/2025-10-07_ES.png', 'https://www.tradingview.com/x/zE0AbZAS/', 'Sentimiento #ES https://www.tradingview.com/x/zE0AbZAS/', '{\"update_id\":474204685,\n\"message\":{\"message_id\":112232,\"from\":{\"id\":603500055,\"is_bot\":false,\"first_name\":\"Favio\",\"last_name\":\"Schneeberger\",\"username\":\"FavioSchneeberger\"},\"chat\":{\"id\":-1001196812259,\"title\":\"AT VIP Canal\",\"type\":\"supergroup\"},\"date\":1759840862,\"text\":\"Sentimiento #ES https://www.tradingview.com/x/zE0AbZAS/\",\"entities\":[{\"offset\":12,\"length\":3,\"type\":\"hashtag\"},{\"offset\":16,\"length\":39,\"type\":\"url\"}],\"link_preview_options\":{\"url\":\"https://www.tradingview.com/x/zE0AbZAS/\"}}}', '2025-10-07 09:41:04', '2025-10-07 09:41:08', 'completed', '{\"op_type\":\"SHORT\",\"stoploss\":[6841.75,6829],\"entry\":6819.75,\"tps\":[6810.5,6803,6796.5,6790.75,6783.25]}', 'SHORT'),
(12, 'NQ', 'uploads/trades/2025-10-07_NQ.png', 'https://www.tradingview.com/x/lclPicKt/', 'Sentimiento #NQ https://www.tradingview.com/x/lclPicKt/', '{\"update_id\":474204686,\n\"message\":{\"message_id\":112233,\"from\":{\"id\":603500055,\"is_bot\":false,\"first_name\":\"Favio\",\"last_name\":\"Schneeberger\",\"username\":\"FavioSchneeberger\"},\"chat\":{\"id\":-1001196812259,\"title\":\"AT VIP Canal\",\"type\":\"supergroup\"},\"date\":1759840952,\"text\":\"Sentimiento #NQ https://www.tradingview.com/x/lclPicKt/\",\"entities\":[{\"offset\":12,\"length\":3,\"type\":\"hashtag\"},{\"offset\":16,\"length\":39,\"type\":\"url\"}],\"link_preview_options\":{\"url\":\"https://www.tradingview.com/x/lclPicKt/\"}}}', '2025-10-07 09:42:33', '2025-10-07 09:42:37', 'completed', '{\"op_type\":\"LONG\",\"stoploss\":[25129.75,25184.25],\"entry\":25224.5,\"tps\":[25259.5,25299.25,25331.25,25374.5,25449]}', 'LONG'),
(13, 'RTY', 'uploads/trades/2025-10-07_RTY.png', 'https://www.tradingview.com/x/OavGC5eQ/', 'Sentimiento #RTY https://www.tradingview.com/x/OavGC5eQ/', '{\"update_id\":474204687,\n\"message\":{\"message_id\":112234,\"from\":{\"id\":603500055,\"is_bot\":false,\"first_name\":\"Favio\",\"last_name\":\"Schneeberger\",\"username\":\"FavioSchneeberger\"},\"chat\":{\"id\":-1001196812259,\"title\":\"AT VIP Canal\",\"type\":\"supergroup\"},\"date\":1759841101,\"text\":\"Sentimiento #RTY https://www.tradingview.com/x/OavGC5eQ/\",\"entities\":[{\"offset\":12,\"length\":4,\"type\":\"hashtag\"},{\"offset\":17,\"length\":39,\"type\":\"url\"}],\"link_preview_options\":{\"url\":\"https://www.tradingview.com/x/OavGC5eQ/\"}}}', '2025-10-07 09:45:02', '2025-10-07 09:45:06', 'completed', '{\"op_type\":\"LONG\",\"stoploss\":[2488.7,2498.9],\"entry\":2504.3,\"tps\":[2508.6,2513.4,2518.8,2524.1,2533.2]}', 'LONG'),
(14, 'CL', 'uploads/trades/2025-10-08_CL.png', 'https://www.tradingview.com/x/cSH7nyjp/', 'Sentimiento #CL https://www.tradingview.com/x/cSH7nyjp/', '{\"update_id\":474204747,\n\"message\":{\"message_id\":112293,\"from\":{\"id\":603500055,\"is_bot\":false,\"first_name\":\"Favio\",\"last_name\":\"Schneeberger\",\"username\":\"FavioSchneeberger\"},\"chat\":{\"id\":-1001196812259,\"title\":\"AT VIP Canal\",\"type\":\"supergroup\"},\"date\":1759925248,\"text\":\"Sentimiento #CL https://www.tradingview.com/x/cSH7nyjp/\",\"entities\":[{\"offset\":12,\"length\":3,\"type\":\"hashtag\"},{\"offset\":16,\"length\":39,\"type\":\"url\"}],\"link_preview_options\":{\"url\":\"https://www.tradingview.com/x/cSH7nyjp/\"}}}', '2025-10-08 09:07:29', '2025-10-08 09:07:34', 'completed', '{\"op_type\":\"LONG\",\"stoploss\":[62.2,62.46],\"entry\":62.61,\"tps\":[62.74,62.88,63.03,63.23,63.46]}', 'LONG'),
(15, 'USDBRL', 'uploads/trades/2025-10-08_USDBRL.png', 'https://www.tradingview.com/x/ZNEcRXst/', 'Sentimiento #USDBRL https://www.tradingview.com/x/ZNEcRXst/', '{\"update_id\":474204749,\n\"message\":{\"message_id\":112295,\"from\":{\"id\":603500055,\"is_bot\":false,\"first_name\":\"Favio\",\"last_name\":\"Schneeberger\",\"username\":\"FavioSchneeberger\"},\"chat\":{\"id\":-1001196812259,\"title\":\"AT VIP Canal\",\"type\":\"supergroup\"},\"date\":1759925495,\"text\":\"Sentimiento #USDBRL https://www.tradingview.com/x/ZNEcRXst/\",\"entities\":[{\"offset\":12,\"length\":7,\"type\":\"hashtag\"},{\"offset\":20,\"length\":39,\"type\":\"url\"}],\"link_preview_options\":{\"url\":\"https://www.tradingview.com/x/ZNEcRXst/\"}}}', '2025-10-08 09:11:36', '2025-10-08 09:11:40', 'completed', '{\"op_type\":\"SHORT\",\"stoploss\":[5.3713,5.3685],\"entry\":5.3616,\"tps\":[5.3537,5.3459,5.3378,5.33,5.3088]}', 'SHORT'),
(16, 'EURUSD', 'uploads/trades/2025-10-08_EURUSD.png', 'https://www.tradingview.com/x/WOeppkIZ/', 'Sentimiento #EURUSD https://www.tradingview.com/x/WOeppkIZ/', '{\"update_id\":474204752,\n\"message\":{\"message_id\":112298,\"from\":{\"id\":603500055,\"is_bot\":false,\"first_name\":\"Favio\",\"last_name\":\"Schneeberger\",\"username\":\"FavioSchneeberger\"},\"chat\":{\"id\":-1001196812259,\"title\":\"AT VIP Canal\",\"type\":\"supergroup\"},\"date\":1759925576,\"text\":\"Sentimiento #EURUSD https://www.tradingview.com/x/WOeppkIZ/\",\"entities\":[{\"offset\":12,\"length\":7,\"type\":\"hashtag\"},{\"offset\":20,\"length\":39,\"type\":\"url\"}],\"link_preview_options\":{\"url\":\"https://www.tradingview.com/x/WOeppkIZ/\"}}}', '2025-10-08 09:12:57', '2025-10-08 09:13:03', 'completed', '{\"op_type\":\"LONG\",\"stoploss\":[1.16002,1.1618],\"entry\":1.16275,\"tps\":[1.16351,1.16428,1.16511,1.16612,1.16843]}', 'LONG'),
(17, 'GC', 'uploads/trades/2025-10-08_GC.png', 'https://www.tradingview.com/x/RpylvAck/', 'Sentimiento #GC https://www.tradingview.com/x/RpylvAck/', '{\"update_id\":474204754,\n\"message\":{\"message_id\":112300,\"from\":{\"id\":603500055,\"is_bot\":false,\"first_name\":\"Favio\",\"last_name\":\"Schneeberger\",\"username\":\"FavioSchneeberger\"},\"chat\":{\"id\":-1001196812259,\"title\":\"AT VIP Canal\",\"type\":\"supergroup\"},\"date\":1759925912,\"text\":\"Sentimiento #GC https://www.tradingview.com/x/RpylvAck/\",\"entities\":[{\"offset\":12,\"length\":3,\"type\":\"hashtag\"},{\"offset\":16,\"length\":39,\"type\":\"url\"}],\"link_preview_options\":{\"url\":\"https://www.tradingview.com/x/RpylvAck/\"}}}', '2025-10-08 09:18:34', '2025-10-08 09:18:38', 'completed', '{\"op_type\":\"SHORT\",\"stoploss\":[4113,4104],\"entry\":4086.8,\"tps\":[4079.5,4074.4,4069.5,4063.8,4055.8]}', 'SHORT'),
(18, 'ZS', 'uploads/trades/2025-10-08_ZS.png', 'https://www.tradingview.com/x/3uDw8qBk/', 'Sentimiento #ZS https://www.tradingview.com/x/3uDw8qBk/', '{\"update_id\":474204756,\n\"message\":{\"message_id\":112302,\"from\":{\"id\":603500055,\"is_bot\":false,\"first_name\":\"Favio\",\"last_name\":\"Schneeberger\",\"username\":\"FavioSchneeberger\"},\"chat\":{\"id\":-1001196812259,\"title\":\"AT VIP Canal\",\"type\":\"supergroup\"},\"date\":1759926304,\"text\":\"Sentimiento #ZS https://www.tradingview.com/x/3uDw8qBk/\",\"entities\":[{\"offset\":12,\"length\":3,\"type\":\"hashtag\"},{\"offset\":16,\"length\":39,\"type\":\"url\"}],\"link_preview_options\":{\"url\":\"https://www.tradingview.com/x/3uDw8qBk/\"}}}', '2025-10-08 09:25:05', '2025-10-08 09:25:09', 'completed', '{\"op_type\":\"LONG\",\"stoploss\":[10104,10134],\"entry\":10160,\"tps\":[10174,10192,10206,10230,10256]}', 'LONG'),
(19, 'VIX', 'uploads/trades/2025-10-08_VIX.png', 'https://www.tradingview.com/x/7vCEii5M/', 'Sentimiento #VIX https://www.tradingview.com/x/7vCEii5M/', '{\"update_id\":474204766,\n\"message\":{\"message_id\":112312,\"from\":{\"id\":603500055,\"is_bot\":false,\"first_name\":\"Favio\",\"last_name\":\"Schneeberger\",\"username\":\"FavioSchneeberger\"},\"chat\":{\"id\":-1001196812259,\"title\":\"AT VIP Canal\",\"type\":\"supergroup\"},\"date\":1759927073,\"text\":\"Sentimiento #VIX https://www.tradingview.com/x/7vCEii5M/\",\"entities\":[{\"offset\":12,\"length\":4,\"type\":\"hashtag\"},{\"offset\":17,\"length\":39,\"type\":\"url\"}],\"link_preview_options\":{\"url\":\"https://www.tradingview.com/x/7vCEii5M/\"}}}', '2025-10-08 09:37:55', '2025-10-08 09:37:59', 'completed', '{\"op_type\":\"LONG\",\"stoploss\":[16.5,16.8],\"entry\":17.07,\"tps\":[17.32,17.51,17.69,17.84,18.06]}', 'LONG'),
(20, 'ES', 'uploads/trades/2025-10-08_ES.png', 'https://www.tradingview.com/x/6QkbYpM8/', 'Sentimiento #ES https://www.tradingview.com/x/6QkbYpM8/', '{\"update_id\":474204768,\n\"message\":{\"message_id\":112314,\"from\":{\"id\":603500055,\"is_bot\":false,\"first_name\":\"Favio\",\"last_name\":\"Schneeberger\",\"username\":\"FavioSchneeberger\"},\"chat\":{\"id\":-1001196812259,\"title\":\"AT VIP Canal\",\"type\":\"supergroup\"},\"date\":1759927459,\"text\":\"Sentimiento #ES https://www.tradingview.com/x/6QkbYpM8/\",\"entities\":[{\"offset\":12,\"length\":3,\"type\":\"hashtag\"},{\"offset\":16,\"length\":39,\"type\":\"url\"}],\"link_preview_options\":{\"url\":\"https://www.tradingview.com/x/6QkbYpM8/\"}}}', '2025-10-08 09:44:21', '2025-10-08 09:44:25', 'completed', '{\"op_type\":\"LONG\",\"stoploss\":[6750.75,6763.25],\"entry\":6769.75,\"tps\":[6776.5,6783,6790.5,6799.75,6809]}', 'LONG'),
(21, 'NQ', 'uploads/trades/2025-10-08_NQ.png', 'https://www.tradingview.com/x/ETXIHo3H/', 'Sentimiento #NQ https://www.tradingview.com/x/ETXIHo3H/', '{\"update_id\":474204769,\n\"message\":{\"message_id\":112315,\"from\":{\"id\":603500055,\"is_bot\":false,\"first_name\":\"Favio\",\"last_name\":\"Schneeberger\",\"username\":\"FavioSchneeberger\"},\"chat\":{\"id\":-1001196812259,\"title\":\"AT VIP Canal\",\"type\":\"supergroup\"},\"date\":1759927620,\"text\":\"Sentimiento #NQ https://www.tradingview.com/x/ETXIHo3H/\",\"entities\":[{\"offset\":12,\"length\":3,\"type\":\"hashtag\"},{\"offset\":16,\"length\":39,\"type\":\"url\"}],\"link_preview_options\":{\"url\":\"https://www.tradingview.com/x/ETXIHo3H/\"}}}', '2025-10-08 09:47:01', '2025-10-08 09:47:06', 'completed', '{\"op_type\":\"LONG\",\"stoploss\":[24968.25,25022.5],\"entry\":25062.25,\"tps\":[25097.25,25136.5,25168.5,25211.5,25285.5]}', 'LONG'),
(22, 'RTY', 'uploads/trades/2025-10-08_RTY.png', 'https://www.tradingview.com/x/YyE6PFBu/', 'Sentimiento #RTY https://www.tradingview.com/x/YyE6PFBu/', '{\"update_id\":474204770,\n\"message\":{\"message_id\":112316,\"from\":{\"id\":603500055,\"is_bot\":false,\"first_name\":\"Favio\",\"last_name\":\"Schneeberger\",\"username\":\"FavioSchneeberger\"},\"chat\":{\"id\":-1001196812259,\"title\":\"AT VIP Canal\",\"type\":\"supergroup\"},\"date\":1759927717,\"text\":\"Sentimiento #RTY https://www.tradingview.com/x/YyE6PFBu/\",\"entities\":[{\"offset\":12,\"length\":4,\"type\":\"hashtag\"},{\"offset\":17,\"length\":39,\"type\":\"url\"}],\"link_preview_options\":{\"url\":\"https://www.tradingview.com/x/YyE6PFBu/\"}}}', '2025-10-08 09:48:39', '2025-10-08 09:48:43', 'completed', '{\"op_type\":\"LONG\",\"stoploss\":[2468.2,2478.4],\"entry\":2483.7,\"tps\":[2488,2492.8,2498.1,2503.4,2512.4]}', 'LONG'),
(23, 'BTCUSD', 'uploads/trades/2025-10-08_BTCUSD.png', 'https://www.tradingview.com/x/DbmjX1HJ/', 'Sentimiento #BTCUSD (M45) https://www.tradingview.com/x/DbmjX1HJ/', '{\"update_id\":474204773,\n\"message\":{\"message_id\":112319,\"from\":{\"id\":603500055,\"is_bot\":false,\"first_name\":\"Favio\",\"last_name\":\"Schneeberger\",\"username\":\"FavioSchneeberger\"},\"chat\":{\"id\":-1001196812259,\"title\":\"AT VIP Canal\",\"type\":\"supergroup\"},\"date\":1759928346,\"text\":\"Sentimiento #BTCUSD (M45) https://www.tradingview.com/x/DbmjX1HJ/\",\"entities\":[{\"offset\":12,\"length\":7,\"type\":\"hashtag\"},{\"offset\":26,\"length\":39,\"type\":\"url\"}],\"link_preview_options\":{\"url\":\"https://www.tradingview.com/x/DbmjX1HJ/\"}}}', '2025-10-08 09:59:08', '2025-10-08 09:59:12', 'completed', '{\"op_type\":\"LONG\",\"stoploss\":[119.873,121.491],\"entry\":122.59,\"tps\":[123.12,123.64,124.138,124.595,125.6]}', 'LONG');

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
  `closed_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `email`, `role`, `created_at`, `updated_at`) VALUES
(1, 'admin', '$2y$10$6ycCI42StRpPm77/Rsgv5.yUB0t0Wdi7ALBL5FYOC2wYXBcXEXOom', 'admin@example.com', 'admin', '2025-04-01 21:07:56', '2025-04-01 21:39:35'),
(2, 'javier', '$2y$10$wdMYUGL7PIDA5yd6FqAhPuHgBt/qsaow.JFTxIjw0qtIDmjDtverW', 'javier@gmail.com', 'user', '2025-04-01 21:44:45', NULL);

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
(1, 1, 'EURUSD', 'EURUSD', 1, '2025-09-03 00:50:06', '2025-09-03 15:03:23'),
(3, 1, 'GC', 'XAUUSD', 1, '2025-09-03 15:03:40', NULL),
(4, 1, 'NQ', 'US100', 1, '2025-09-10 19:38:40', NULL),
(5, 1, 'ES', 'US500', 1, '2025-09-10 19:39:04', NULL),
(6, 1, 'CL', 'USOIL', 1, '2025-09-10 19:39:20', '2025-10-05 16:58:26'),
(7, 1, 'RTY', 'US2000', 1, '2025-10-05 16:58:12', NULL);

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
  `mt_execution_data` text DEFAULT NULL COMMENT 'Original MT5 signal data (entry, stoploss, tps) sent by EA at open',
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

INSERT INTO `user_telegram_signals` (`id`, `telegram_signal_id`, `user_id`, `ticker_symbol`, `mt_ticker`, `status`, `execution_data`, `mt_execution_data`, `trade_id`, `real_entry_price`, `real_stop_loss`, `real_volume`, `order_type`, `current_level`, `volume_closed_percent`, `remaining_volume`, `gross_pnl`, `last_price`, `close_reason`, `exit_level`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 'EURUSD', 'EURUSD', 'pending', '{\"success\":true,\"trade_id\":\"29908243\",\"order_type\":\"ORDER_TYPE_BUY_STOP\",\"symbol\":\"EURUSD\",\"execution_time\":\"2025.10.07 12:05:07\",\"execution_time_local\":\"2025-10-07 09:05:07\"}', NULL, '29908243', NULL, NULL, NULL, 'ORDER_TYPE_BUY_STOP', -2, '0.00', NULL, '0.00', NULL, NULL, NULL, '2025-10-07 09:01:24', '2025-10-07 09:05:07'),
(2, 4, 1, 'GC', 'XAUUSD', 'closed', '{\"success\":true,\"exit_level\":-1,\"close_reason\":\"CLOSED_STOPLOSS\",\"gross_pnl\":1.98,\"last_price\":3969.095,\"symbol\":\"GC\",\"execution_time\":\"2025.10.07 14:15:26\",\"execution_time_local\":\"2025-10-07 11:15:27\"}', NULL, '29908449', '3968.91500', '3968.91500', NULL, 'ORDER_TYPE_BUY_LIMIT', 1, '100.00', '0.00', '-2.75', '3969.09500', 'CLOSED_STOPLOSS', -1, '2025-10-07 09:10:49', '2025-10-07 11:15:27'),
(3, 6, 1, 'EURUSD', 'EURUSD', 'closed', '{\"success\":true,\"exit_level\":-1,\"close_reason\":\"CLOSED_STOPLOSS\",\"gross_pnl\":0,\"last_price\":1.16554,\"symbol\":\"EURUSD\",\"execution_time\":\"2025.10.07 14:01:11\",\"execution_time_local\":\"2025-10-07 11:01:11\"}', NULL, '29908469', '1.16554', '1.16554', NULL, 'ORDER_TYPE_BUY_LIMIT', 1, '100.00', '0.00', '-0.21', '1.16554', 'CLOSED_STOPLOSS', -1, '2025-10-07 09:11:51', '2025-10-07 11:01:11'),
(4, 7, 1, 'CL', 'USOIL', 'closed', '{\"success\":true,\"exit_level\":5,\"close_reason\":\"CLOSED_COMPLETE\",\"gross_pnl\":0,\"last_price\":61.836,\"symbol\":\"CL\",\"execution_time\":\"2025.10.07 13:34:56\",\"execution_time_local\":\"2025-10-07 10:34:56\"}', NULL, '29908483', '60.99400', '60.99400', NULL, 'ORDER_TYPE_BUY_LIMIT', 5, '100.00', '0.00', '283.80', '61.83600', 'CLOSED_COMPLETE', 5, '2025-10-07 09:12:12', '2025-10-07 10:34:56'),
(5, 11, 1, 'ES', 'US500', 'closed', '{\"success\":true,\"exit_level\":-999,\"close_reason\":\"ORDER_CANCELLED\",\"gross_pnl\":0,\"last_price\":0,\"symbol\":\"ES\",\"execution_time\":\"2025.10.07 22:00:56\",\"execution_time_local\":\"2025-10-07 19:00:56\"}', NULL, '29909470', NULL, NULL, NULL, 'ORDER_TYPE_SELL_LIMI', -2, '100.00', '0.00', '0.00', '0.00000', 'ORDER_CANCELLED', -999, '2025-10-07 09:41:08', '2025-10-07 19:00:56'),
(6, 12, 1, 'NQ', 'US100', 'closed', '{\"success\":true,\"exit_level\":-1,\"close_reason\":\"CLOSED_STOPLOSS\",\"gross_pnl\":-0.2,\"last_price\":25052,\"symbol\":\"NQ\",\"execution_time\":\"2025.10.07 14:03:42\",\"execution_time_local\":\"2025-10-07 11:03:42\"}', NULL, '29909540', '25052.10000', '25052.10000', NULL, 'ORDER_TYPE_BUY_LIMIT', 1, '100.00', '0.00', '-2.24', '25052.00000', 'CLOSED_STOPLOSS', -1, '2025-10-07 09:42:37', '2025-10-07 11:03:42'),
(7, 13, 1, 'RTY', 'US2000', 'closed', '{\"success\":true,\"exit_level\":-999,\"close_reason\":\"SPREAD_TOO_HIGH\",\"gross_pnl\":0,\"last_price\":0,\"symbol\":\"RTY\",\"execution_time\":\"2025.10.07 12:45:21\",\"execution_time_local\":\"2025-10-07 09:45:21\"}', NULL, NULL, NULL, NULL, NULL, NULL, -2, '100.00', '0.00', '0.00', '0.00000', 'SPREAD_TOO_HIGH', -999, '2025-10-07 09:45:06', '2025-10-07 09:45:21'),
(8, 14, 1, 'CL', 'USOIL', 'closed', '{\"success\":true,\"exit_level\":-1,\"close_reason\":\"CLOSED_STOPLOSS\",\"gross_pnl\":0,\"last_price\":62.807,\"symbol\":\"CL\",\"execution_time\":\"2025.10.08 12:55:00\",\"execution_time_local\":\"2025-10-08 09:55:00\"}', NULL, '29937791', '62.80700', '62.80700', '0.40', 'ORDER_TYPE_BUY', 1, '100.00', '0.00', '12.00', '62.80700', 'CLOSED_STOPLOSS', -1, '2025-10-08 09:07:34', '2025-10-08 09:55:00'),
(9, 16, 1, 'EURUSD', 'EURUSD', 'closed', '{\"success\":true,\"exit_level\":-1,\"close_reason\":\"CLOSED_STOPLOSS\",\"gross_pnl\":0.65,\"last_price\":1.16337,\"symbol\":\"EURUSD\",\"execution_time\":\"2025.10.08 12:15:33\",\"execution_time_local\":\"2025-10-08 09:15:33\"}', NULL, '29937869', '1.16336', '1.16336', '0.72', 'ORDER_TYPE_BUY', 1, '100.00', '0.00', '1.70', '1.16337', 'CLOSED_STOPLOSS', -1, '2025-10-08 09:13:03', '2025-10-08 09:15:33'),
(10, 17, 1, 'GC', 'XAUUSD', 'closed', '{\"success\":true,\"exit_level\":-999,\"close_reason\":\"ORDER_CANCELLED\",\"gross_pnl\":0,\"last_price\":0,\"symbol\":\"GC\",\"execution_time\":\"2025.10.08 13:43:52\",\"execution_time_local\":\"2025-10-08 10:43:52\"}', NULL, '29937984', NULL, NULL, NULL, 'ORDER_TYPE_SELL_LIMI', -2, '100.00', '0.00', '0.00', '0.00000', 'ORDER_CANCELLED', -999, '2025-10-08 09:18:38', '2025-10-08 10:43:52'),
(11, 20, 1, 'ES', 'US500', 'open', '{\"success\":true,\"trade_id\":\"29938334\",\"order_type\":\"ORDER_TYPE_BUY\",\"real_entry_price\":6733.2,\"real_stop_loss\":6703.75019,\"real_volume\":10.52,\"symbol\":\"ES\",\"execution_time\":\"2025.10.08 12:44:43\",\"execution_time_local\":\"2025-10-08 09:44:43\"}', NULL, '29938334', '6733.20000', '6703.75019', '10.52', 'ORDER_TYPE_BUY', 0, '0.00', '10.52', '0.00', NULL, NULL, NULL, '2025-10-08 09:44:25', '2025-10-08 09:44:43'),
(12, 21, 1, 'NQ', 'US100', 'open', '{\"success\":true,\"current_level\":2,\"volume_closed_percent\":39.62,\"remaining_volume\":1.28,\"gross_pnl\":29.99,\"last_price\":24969.6,\"message\":\"TP parcial\",\"symbol\":\"NQ\",\"execution_time\":\"2025.10.08 13:39:43\",\"execution_time_local\":\"2025-10-08 10:39:44\"}', NULL, '29938372', '24898.20000', '24898.20000', '2.12', 'ORDER_TYPE_BUY', 2, '39.62', '1.28', '43.56', '24969.60000', NULL, NULL, '2025-10-08 09:47:06', '2025-10-08 10:39:44'),
(13, 22, 1, 'RTY', 'US2000', 'open', '{\"success\":true,\"current_level\":0,\"volume_closed_percent\":0,\"remaining_volume\":12.9,\"gross_pnl\":-8.39,\"last_price\":2469.897,\"now_open\":true,\"real_entry_price\":2469.897,\"message\":\"Orden pendiente ejecutada\",\"symbol\":\"RTY\",\"execution_time\":\"2025.10.08 12:51:34\",\"execution_time_local\":\"2025-10-08 09:51:35\"}', NULL, '29938401', '2469.89700', NULL, NULL, 'ORDER_TYPE_BUY_LIMIT', 0, '0.00', '12.90', '-8.39', '2469.89700', NULL, NULL, '2025-10-08 09:48:43', '2025-10-08 09:51:35');

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
  ADD KEY `fk_mt_signal` (`mt_signal_id`);

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `mt_signals`
--
ALTER TABLE `mt_signals`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `strategies`
--
ALTER TABLE `strategies`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT de la tabla `system_logs`
--
ALTER TABLE `system_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `telegram_signals`
--
ALTER TABLE `telegram_signals`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT de la tabla `trades`
--
ALTER TABLE `trades`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `user_selected_tickers`
--
ALTER TABLE `user_selected_tickers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT de la tabla `user_telegram_signals`
--
ALTER TABLE `user_telegram_signals`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
