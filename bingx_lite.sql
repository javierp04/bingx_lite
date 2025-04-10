-- phpMyAdmin SQL Dump
-- version 5.2.0
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 03-04-2025 a las 13:01:01
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
(2, 1, 'mlTNwZLYS5qb0ojsPMUxym78kboX5ekCAYLUcUrPrr2kYrSpbP6DRTEDDkQcLMT9C8cNEcqjUdlI0zyA794Q', 'vsk1FT5W2ZOUNmkHyO3B8OlvPXMlVefAJ4Vqt1PPtK2oGZq50on8g1XFBMBcEnhAPtVLiNBM5MaDxmdMWImHIg', '2025-04-01 23:32:34', NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `strategies`
--

CREATE TABLE `strategies` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `strategy_id` varchar(50) NOT NULL,
  `name` varchar(100) NOT NULL,
  `type` enum('spot','futures') NOT NULL,
  `description` text DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `strategies`
--

INSERT INTO `strategies` (`id`, `user_id`, `strategy_id`, `name`, `type`, `description`, `image`, `active`, `created_at`, `updated_at`) VALUES
(1, 1, 'BTC_H1_RSI', 'Estrategia Bitcoin RSI en H1 - SHORT Estructural', 'spot', '', 'f6f06595be174ca88fc3b46ad9e9cf45.jpg', 1, '2025-04-01 23:51:23', NULL),
(2, 1, 'FUT_BTC_H1_RSI', 'FUT Estrategia Bitcoin RSI en H1 - SHORT Estructural', 'futures', '', 'ed9424a0c102e6e27ba32c8e178a5939.jpg', 1, '2025-04-02 23:10:12', NULL);

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
(1, 1, 'webhook_debug', 'Simulating order with raw data. Data: {\"strategy_id\":\"BTC_H1_RSI\",\"ticker\":\"BTCUSDT\",\"timeframe\":\"1h\",\"action\":\"BUY\",\"quantity\":\"0.0001\",\"leverage\":\"1\",\"environment\":\"production\",\"position_id\":\"12345\"}', '127.0.0.1', '2025-04-03 12:53:23'),
(2, 1, 'webhook_debug', 'Preparing order parameters. Data: {\"ticker\":\"BTCUSDT\",\"formatted_ticker\":\"BTC-USDT\",\"action\":\"BUY\",\"quantity\":\"0.0001\",\"strategy_type\":\"spot\",\"environment\":\"production\",\"take_profit\":null,\"stop_loss\":null,\"position_id\":\"12345\"}', '127.0.0.1', '2025-04-03 12:53:23'),
(3, 1, 'api_debug', '{\"endpoint\":\"\\/openApi\\/spot\\/v1\\/trade\\/order\",\"method\":\"POST\",\"parameters\":\"timestamp=1743695603293&symbol=BTC-USDT&side=BUY&type=MARKET&quantity=0.0001\",\"url\":\"https:\\/\\/open-api.bingx.com\\/openApi\\/spot\\/v1\\/trade\\/order?timestamp=1743695603293&symbol=BTC-USDT&side=BUY&type=MARKET&quantity=0.0001&signature=f924f89093f7480f11d317d0545dc4e72b30dff5891c926f78e4f3bad8f008be\",\"environment\":\"production\",\"is_futures\":false}', '127.0.0.1', '2025-04-03 12:53:23'),
(4, 1, 'api_request', '{\"endpoint\":\"\\/openApi\\/spot\\/v1\\/trade\\/order\",\"method\":\"POST\",\"url\":\"https:\\/\\/open-api.bingx.com\\/openApi\\/spot\\/v1\\/trade\\/order?timestamp=1743695603293&symbol=BTC-USDT&side=BUY&type=MARKET&quantity=0.0001&signature=f924f89093f7480f11d317d0545dc4e72b30dff5891c926f78e4f3bad8f008be\",\"headers\":[\"Content-Type: application\\/x-www-form-urlencoded\",\"User-Agent: BingX-Trading-Bot\",\"X-BX-APIKEY: mlTNwZLYS5qb0ojsPMUxym78kboX5ekCAYLUcUrPrr2kYrSpbP6DRTEDDkQcLMT9C8cNEcqjUdlI0zyA794Q\"],\"parameters\":\"timestamp=1743695603293&symbol=BTC-USDT&side=BUY&type=MARKET&quantity=0.0001\",\"response\":\"{\\\"code\\\":0,\\\"msg\\\":\\\"\\\",\\\"debugMsg\\\":\\\"\\\",\\\"data\\\":{\\\"symbol\\\":\\\"BTC-USDT\\\",\\\"orderId\\\":1907823749680332800,\\\"transactTime\\\":1743695602232,\\\"price\\\":\\\"81725.56\\\",\\\"stopPrice\\\":\\\"0\\\",\\\"origQty\\\":\\\"0.0001\\\",\\\"executedQty\\\":\\\"0.0001\\\",\\\"cummulativeQuoteQty\\\":\\\"8.172647000000001\\\",\\\"status\\\":\\\"FILLED\\\",\\\"type\\\":\\\"MARKET\\\",\\\"side\\\":\\\"BUY\\\",\\\"clientOrderID\\\":\\\"\\\",\\\"clientUserID\\\":\\\"\\\",\\\"msg\\\":\\\"\\\",\\\"bxUid\\\":\\\"\\\"}}\",\"http_code\":200,\"curl_error\":\"\",\"environment\":\"production\"}', '127.0.0.1', '2025-04-03 12:53:24'),
(5, 1, 'open_trade', 'Opened spot BUY position for BTCUSDT via webhook (Strategy: Estrategia Bitcoin RSI en H1 - SHORT Estructural, Environment: production, Position ID: 12345)', '127.0.0.1', '2025-04-03 12:53:24');

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
  `trade_type` enum('spot','futures') NOT NULL,
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

--
-- Volcado de datos para la tabla `trades`
--

INSERT INTO `trades` (`id`, `user_id`, `strategy_id`, `order_id`, `symbol`, `timeframe`, `side`, `trade_type`, `environment`, `quantity`, `entry_price`, `current_price`, `leverage`, `take_profit`, `stop_loss`, `exit_price`, `pnl`, `status`, `webhook_data`, `position_id`, `created_at`, `updated_at`, `closed_at`) VALUES
(1, 1, 1, '1907823749680332800', 'BTCUSDT', '1h', 'BUY', 'spot', 'production', '0.00010000', '81726.64000000', '81726.63000000', 1, NULL, NULL, NULL, '-0.00000100', 'open', '{\"strategy_id\":\"BTC_H1_RSI\",\"ticker\":\"BTCUSDT\",\"timeframe\":\"1h\",\"action\":\"BUY\",\"quantity\":\"0.0001\",\"leverage\":\"1\",\"environment\":\"production\",\"position_id\":\"12345\"}', '12345', '2025-04-03 12:53:24', '2025-04-03 12:53:27', NULL);

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
-- Indices de la tabla `trades`
--
ALTER TABLE `trades`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `strategy_id` (`strategy_id`),
  ADD KEY `idx_position_id` (`position_id`);

--
-- Indices de la tabla `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `api_keys`
--
ALTER TABLE `api_keys`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `strategies`
--
ALTER TABLE `strategies`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `system_logs`
--
ALTER TABLE `system_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `trades`
--
ALTER TABLE `trades`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `api_keys`
--
ALTER TABLE `api_keys`
  ADD CONSTRAINT `api_keys_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

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
-- Filtros para la tabla `trades`
--
ALTER TABLE `trades`
  ADD CONSTRAINT `trades_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `trades_ibfk_2` FOREIGN KEY (`strategy_id`) REFERENCES `strategies` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
