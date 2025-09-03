-- phpMyAdmin SQL Dump
-- version 5.0.4deb2+deb11u2
-- https://www.phpmyadmin.net/
--
-- Servidor: localhost:3306
-- Tiempo de generación: 02-09-2025 a las 23:59:36
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

--
-- Volcado de datos para la tabla `api_keys`
--

INSERT INTO `api_keys` (`id`, `user_id`, `api_key`, `api_secret`, `created_at`, `updated_at`) VALUES
(1, 1, 'mlTNwZLYS5qb0ojsPMUxym78kboX5ekCAYLUcUrPrr2kYrSpbP6DRTEDDkQcLMT9C8cNEcqjUdlI0zyA794Q', 'vsk1FT5W2ZOUNmkHyO3B8OlvPXMlVefAJ4Vqt1PPtK2oGZq50on8g1XFBMBcEnhAPtVLiNBM5MaDxmdMWImHIg', '2025-04-01 23:32:18', NULL),
(2, 1, 'mlTNwZLYS5qb0ojsPMUxym78kboX5ekCAYLUcUrPrr2kYrSpbP6DRTEDDkQcLMT9C8cNEcqjUdlI0zyA794Q', 'vsk1FT5W2ZOUNmkHyO3B8OlvPXMlVefAJ4Vqt1PPtK2oGZq50on8g1XFBMBcEnhAPtVLiNBM5MaDxmdMWImHIg', '2025-04-01 23:32:34', NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `available_tickers`
--

CREATE TABLE `available_tickers` (
  `symbol` varchar(20) NOT NULL,
  `name` varchar(100) NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
  `processed` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
  `platform` enum('bingx','metatrader') NOT NULL DEFAULT 'bingx',
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
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
  ADD KEY `idx_processed_created` (`processed`,`created_at`),
  ADD KEY `idx_ticker_created` (`ticker_symbol`,`created_at`);

--
-- Indices de la tabla `trades`
--
ALTER TABLE `trades`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `strategy_id` (`strategy_id`),
  ADD KEY `idx_position_id` (`position_id`),
  ADD KEY `idx_platform_status` (`platform`,`status`),
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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `api_keys`
--
ALTER TABLE `api_keys`
  ADD CONSTRAINT `api_keys_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `mt_signals`
--
ALTER TABLE `mt_signals`
  ADD CONSTRAINT `mt_signals_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `mt_signals_ibfk_2` FOREIGN KEY (`strategy_id`) REFERENCES `strategies` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `strategies`
--
ALTER TABLE `strategies`
  ADD CONSTRAINT `strategies_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `system_logs`
--
ALTER TABLE `system_logs`
  ADD CONSTRAINT `system_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Filtros para la tabla `telegram_signals`
--
ALTER TABLE `telegram_signals`
  ADD CONSTRAINT `telegram_signals_ibfk_1` FOREIGN KEY (`ticker_symbol`) REFERENCES `available_tickers` (`symbol`) ON DELETE CASCADE;

--
-- Filtros para la tabla `trades`
--
ALTER TABLE `trades`
  ADD CONSTRAINT `fk_mt_signal` FOREIGN KEY (`mt_signal_id`) REFERENCES `mt_signals` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `trades_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `trades_ibfk_2` FOREIGN KEY (`strategy_id`) REFERENCES `strategies` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `user_selected_tickers`
--
ALTER TABLE `user_selected_tickers`
  ADD CONSTRAINT `user_selected_tickers_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_selected_tickers_ibfk_2` FOREIGN KEY (`ticker_symbol`) REFERENCES `available_tickers` (`symbol`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
