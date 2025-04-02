-- phpMyAdmin SQL Dump
-- version 5.2.0
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 02-04-2025 a las 01:06:18
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
(1, 1, 'BTC_H1_RSI', 'Estrategia Bitcoin RSI en H1 - SHORT Estructural', 'spot', '', 'f6f06595be174ca88fc3b46ad9e9cf45.jpg', 1, '2025-04-01 23:51:23', NULL);

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
(1, 1, 'login', 'User logged in', '127.0.0.1', '2025-04-01 21:39:41'),
(2, 1, 'add_user', 'Added new user: javier', '127.0.0.1', '2025-04-01 21:44:45'),
(3, 1, 'logout', 'User logged out', '127.0.0.1', '2025-04-01 21:44:54'),
(4, 2, 'login', 'User logged in', '127.0.0.1', '2025-04-01 21:45:01'),
(5, 2, 'logout', 'User logged out', '127.0.0.1', '2025-04-01 21:45:53'),
(6, 1, 'login', 'User logged in', '127.0.0.1', '2025-04-01 21:45:58'),
(7, 1, 'add_api_key', 'Added API key for sandbox', '127.0.0.1', '2025-04-01 23:32:18'),
(8, 1, 'add_api_key', 'Added API key for production', '127.0.0.1', '2025-04-01 23:32:34'),
(9, 1, 'add_strategy', 'Added strategy: Estrategia Bitcoin RSI en H1 - SHORT Estructural', '127.0.0.1', '2025-04-01 23:51:23'),
(10, 1, 'logout', 'User logged out', '127.0.0.1', '2025-04-02 00:18:55'),
(11, 1, 'login', 'User logged in', '127.0.0.1', '2025-04-02 00:19:56'),
(12, NULL, 'webhook_error', 'Strategy not found: BTC_H1_RSI for user 2. Data: {\"user_id\":\"2\",\"strategy_id\":\"BTC_H1_RSI\",\"ticker\":\"BTCUSDT\",\"timeframe\":\"1h\",\"action\":\"BUY\",\"quantity\":\"0.0005\",\"leverage\":\"1\"}', '127.0.0.1', '2025-04-02 00:20:17'),
(13, NULL, 'webhook_error', 'Strategy not found: BTC_H1_RSI for user 2. Data: {\"user_id\":\"2\",\"strategy_id\":\"BTC_H1_RSI\",\"ticker\":\"BTCUSDT\",\"timeframe\":\"1h\",\"action\":\"BUY\",\"quantity\":\"0.0005\",\"leverage\":\"1\"}', '127.0.0.1', '2025-04-02 00:26:11'),
(14, 1, 'api_request', '{\"endpoint\":\"\\/openApi\\/spot\\/v1\\/trade\\/order\",\"params\":\"{\\\"symbol\\\":\\\"BTCUSDT\\\",\\\"side\\\":\\\"BUY\\\",\\\"type\\\":\\\"MARKET\\\",\\\"quantity\\\":\\\"0.0005\\\",\\\"timestamp\\\":1743564488079,\\\"apiKey\\\":\\\"mlTNwZLYS5qb0ojsPMUxym78kboX5ekCAYLUcUrPrr2kYrSpbP6DRTEDDkQcLMT9C8cNEcqjUdlI0zyA794Q\\\",\\\"signature\\\":\\\"d2550523c036f65bde0f6ed2c7660ec7a83d8feb461f8f6d28d8c69b9bb41882\\\"}\",\"response\":\"{\\\"code\\\":100413,\\\"msg\\\":\\\"Null apiKey,unable to find API KEY in HTTP header,please Pass the API Key with X-BX-APIKEY in HTTP request header,please refer to the API docs https:\\/\\/bingx-api.github.io\\/docs\\/\\\",\\\"timestamp\\\":1743564501800}\",\"http_code\":200}', '127.0.0.1', '2025-04-02 00:28:47'),
(15, NULL, 'webhook_error', 'Failed to execute order. Data: {\"user_id\":\"1\",\"strategy_id\":\"BTC_H1_RSI\",\"ticker\":\"BTCUSDT\",\"timeframe\":\"1h\",\"action\":\"BUY\",\"quantity\":\"0.0005\",\"leverage\":\"1\"}', '127.0.0.1', '2025-04-02 00:28:53'),
(16, NULL, 'webhook_error', 'Strategy not found: BTC_H1_RSI for user 2. Data: {\"user_id\":\"2\",\"strategy_id\":\"BTC_H1_RSI\",\"ticker\":\"BTCUSDT\",\"timeframe\":\"1h\",\"action\":\"BUY\",\"quantity\":\"0.0005\",\"leverage\":\"1\"}', '127.0.0.1', '2025-04-02 00:30:27'),
(17, 1, 'api_request', '{\"endpoint\":\"\\/openApi\\/spot\\/v1\\/trade\\/order\",\"params\":\"{\\\"symbol\\\":\\\"BTCUSDT\\\",\\\"side\\\":\\\"BUY\\\",\\\"type\\\":\\\"MARKET\\\",\\\"quantity\\\":\\\"0.0005\\\",\\\"timestamp\\\":1743564654562,\\\"apiKey\\\":\\\"mlTNwZLYS5qb0ojsPMUxym78kboX5ekCAYLUcUrPrr2kYrSpbP6DRTEDDkQcLMT9C8cNEcqjUdlI0zyA794Q\\\",\\\"signature\\\":\\\"30976b2659f9696615576826e8efb2bb04f72835b1b42377e70caac9caee2d92\\\"}\",\"response\":\"{\\\"code\\\":100413,\\\"msg\\\":\\\"Null apiKey,unable to find API KEY in HTTP header,please Pass the API Key with X-BX-APIKEY in HTTP request header,please refer to the API docs https:\\/\\/bingx-api.github.io\\/docs\\/\\\",\\\"timestamp\\\":1743564658265}\",\"http_code\":200}', '127.0.0.1', '2025-04-02 00:32:58'),
(18, NULL, 'webhook_error', 'Failed to execute order. Data: {\"user_id\":\"1\",\"strategy_id\":\"BTC_H1_RSI\",\"ticker\":\"BTCUSDT\",\"timeframe\":\"1h\",\"action\":\"BUY\",\"quantity\":\"0.0005\",\"leverage\":\"1\"}', '127.0.0.1', '2025-04-02 00:33:31'),
(19, 1, 'api_request', '{\"endpoint\":\"\\/openApi\\/spot\\/v1\\/account\\/balance\",\"url\":\"https:\\/\\/open-api.bingx.com\\/openApi\\/spot\\/v1\\/account\\/balance?timestamp=1743565827690&signature=5276eb692f88d1d80a267f720d8f74d2701352472e062a0f531fc27ec9f0a097\",\"method\":\"GET\",\"headers\":[\"X-BX-APIKEY: mlTNw...\"],\"params\":\"{\\\"timestamp\\\":1743565827690,\\\"signature\\\":\\\"5276eb692f88d1d80a267f720d8f74d2701352472e062a0f531fc27ec9f0a097\\\"}\",\"response\":\"{\\\"code\\\":0,\\\"msg\\\":\\\"\\\",\\\"debugMsg\\\":\\\"\\\",\\\"data\\\":{\\\"balances\\\":[{\\\"asset\\\":\\\"USDT\\\",\\\"free\\\":\\\"2590.589994651396\\\",\\\"locked\\\":\\\"150.4\\\"},{\\\"asset\\\":\\\"BTC\\\",\\\"free\\\":\\\"0.009993\\\",\\\"locked\\\":\\\"0\\\"},{\\\"asset\\\":\\\"LTC\\\",\\\"free\\\":\\\"0.000099\\\",\\\"locked\\\":\\\"0\\\"},{\\\"asset\\\":\\\"ETH\\\",\\\"free\\\":\\\"0\\\",\\\"locked\\\":\\\"0\\\"},{\\\"asset\\\":\\\"PYUSD\\\",\\\"free\\\":\\\"0\\\",\\\"locked\\\":\\\"0\\\"}]}}\",\"http_code\":200,\"curl_error\":\"\"}', '127.0.0.1', '2025-04-02 00:50:28'),
(20, 1, 'api_request', '{\"endpoint\":\"\\/openApi\\/swap\\/v2\\/user\\/balance\",\"url\":\"https:\\/\\/open-api.bingx.com\\/openApi\\/swap\\/v2\\/user\\/balance?timestamp=1743565835496&signature=7a45dfa98a23e9e299e518e9e3fb381ae514ee8f41cd457112fefef58078852d\",\"method\":\"GET\",\"headers\":[\"X-BX-APIKEY: mlTNw...\"],\"params\":\"{\\\"timestamp\\\":1743565835496,\\\"signature\\\":\\\"7a45dfa98a23e9e299e518e9e3fb381ae514ee8f41cd457112fefef58078852d\\\"}\",\"response\":\"{\\\"code\\\":0,\\\"msg\\\":\\\"\\\",\\\"data\\\":{\\\"balance\\\":{\\\"userId\\\":\\\"908276340030590979\\\",\\\"asset\\\":\\\"USDT\\\",\\\"balance\\\":\\\"0.0000\\\",\\\"equity\\\":\\\"0.0000\\\",\\\"unrealizedProfit\\\":\\\"0.0000\\\",\\\"realisedProfit\\\":\\\"0\\\",\\\"availableMargin\\\":\\\"0.0000\\\",\\\"usedMargin\\\":\\\"0.0000\\\",\\\"freezedMargin\\\":\\\"0.0000\\\",\\\"shortUid\\\":\\\"6303282\\\"}}}\",\"http_code\":200,\"curl_error\":\"\"}', '127.0.0.1', '2025-04-02 00:50:35'),
(21, 1, 'api_request', '{\"endpoint\":\"\\/openApi\\/spot\\/v1\\/ticker\\/price\",\"url\":\"https:\\/\\/open-api.bingx.com\\/openApi\\/spot\\/v1\\/ticker\\/price?symbol=BTCUSDT&timestamp=1743565844921&signature=c11b2cd8226e74b1a0144a8182ae7d936fb0031c592d35652778e8fe9fe5db09\",\"method\":\"GET\",\"headers\":[\"X-BX-APIKEY: mlTNw...\"],\"params\":\"{\\\"symbol\\\":\\\"BTCUSDT\\\",\\\"timestamp\\\":1743565844921,\\\"signature\\\":\\\"c11b2cd8226e74b1a0144a8182ae7d936fb0031c592d35652778e8fe9fe5db09\\\"}\",\"response\":\"{\\\"code\\\":100204,\\\"msg\\\":\\\"BTCUSDT not found or no trades lately\\\",\\\"timestamp\\\":1743565844280}\",\"http_code\":200,\"curl_error\":\"\"}', '127.0.0.1', '2025-04-02 00:50:45'),
(22, 1, 'api_request', '{\"endpoint\":\"\\/openApi\\/swap\\/v2\\/quote\\/price\",\"url\":\"https:\\/\\/open-api.bingx.com\\/openApi\\/swap\\/v2\\/quote\\/price?symbol=BTCUSDT&timestamp=1743565852221&signature=395772bc3cf605df34451ace248af25c7b578a30411fc754c21b98ccad9ed64d\",\"method\":\"GET\",\"headers\":[\"X-BX-APIKEY: mlTNw...\"],\"params\":\"{\\\"symbol\\\":\\\"BTCUSDT\\\",\\\"timestamp\\\":1743565852221,\\\"signature\\\":\\\"395772bc3cf605df34451ace248af25c7b578a30411fc754c21b98ccad9ed64d\\\"}\",\"response\":\"{\\\"code\\\":80014,\\\"msg\\\":\\\"Invalid parameters, err:symbol: This field must be either empty or end with -USDT or -USDC. \\\",\\\"data\\\":{}}\",\"http_code\":200,\"curl_error\":\"\"}', '127.0.0.1', '2025-04-02 00:50:52'),
(23, 1, 'api_request', '{\"endpoint\":\"\\/openApi\\/spot\\/v1\\/account\\/balance\",\"url\":\"https:\\/\\/open-api.bingx.com\\/openApi\\/spot\\/v1\\/account\\/balance?timestamp=1743565864386&signature=cc5da07d4e4c7c8d7acc4358767677febb09e2273b775235edd589d46ee22d2e\",\"method\":\"GET\",\"headers\":[\"X-BX-APIKEY: mlTNw...\"],\"params\":\"{\\\"timestamp\\\":1743565864386,\\\"signature\\\":\\\"cc5da07d4e4c7c8d7acc4358767677febb09e2273b775235edd589d46ee22d2e\\\"}\",\"response\":\"{\\\"code\\\":0,\\\"msg\\\":\\\"\\\",\\\"debugMsg\\\":\\\"\\\",\\\"data\\\":{\\\"balances\\\":[{\\\"asset\\\":\\\"USDT\\\",\\\"free\\\":\\\"2590.589994651396\\\",\\\"locked\\\":\\\"150.4\\\"},{\\\"asset\\\":\\\"BTC\\\",\\\"free\\\":\\\"0.009993\\\",\\\"locked\\\":\\\"0\\\"},{\\\"asset\\\":\\\"LTC\\\",\\\"free\\\":\\\"0.000099\\\",\\\"locked\\\":\\\"0\\\"},{\\\"asset\\\":\\\"PYUSD\\\",\\\"free\\\":\\\"0\\\",\\\"locked\\\":\\\"0\\\"},{\\\"asset\\\":\\\"ETH\\\",\\\"free\\\":\\\"0\\\",\\\"locked\\\":\\\"0\\\"}]}}\",\"http_code\":200,\"curl_error\":\"\"}', '127.0.0.1', '2025-04-02 00:51:04'),
(24, 1, 'api_request', '{\"endpoint\":\"\\/openApi\\/swap\\/v2\\/user\\/balance\",\"url\":\"https:\\/\\/open-api.bingx.com\\/openApi\\/swap\\/v2\\/user\\/balance?timestamp=1743565868407&signature=6c4e2a758fe5cf0ee35e72a9ee96f7ba1f64b4728a4eb9af2789c849cfb17ea8\",\"method\":\"GET\",\"headers\":[\"X-BX-APIKEY: mlTNw...\"],\"params\":\"{\\\"timestamp\\\":1743565868407,\\\"signature\\\":\\\"6c4e2a758fe5cf0ee35e72a9ee96f7ba1f64b4728a4eb9af2789c849cfb17ea8\\\"}\",\"response\":\"{\\\"code\\\":0,\\\"msg\\\":\\\"\\\",\\\"data\\\":{\\\"balance\\\":{\\\"userId\\\":\\\"908276340030590979\\\",\\\"asset\\\":\\\"USDT\\\",\\\"balance\\\":\\\"0.0000\\\",\\\"equity\\\":\\\"0.0000\\\",\\\"unrealizedProfit\\\":\\\"0.0000\\\",\\\"realisedProfit\\\":\\\"0\\\",\\\"availableMargin\\\":\\\"0.0000\\\",\\\"usedMargin\\\":\\\"0.0000\\\",\\\"freezedMargin\\\":\\\"0.0000\\\",\\\"shortUid\\\":\\\"6303282\\\"}}}\",\"http_code\":200,\"curl_error\":\"\"}', '127.0.0.1', '2025-04-02 00:51:10'),
(25, 1, 'api_request', '{\"endpoint\":\"\\/openApi\\/spot\\/v1\\/ticker\\/price\",\"url\":\"https:\\/\\/open-api.bingx.com\\/openApi\\/spot\\/v1\\/ticker\\/price?symbol=BTC-USDT&timestamp=1743565900998&signature=172aa6a069360f7b5569743bf29ad9eb2291f5526a826f7ec2c6a5851d429e0e\",\"method\":\"GET\",\"headers\":[\"X-BX-APIKEY: mlTNw...\"],\"params\":\"{\\\"symbol\\\":\\\"BTC-USDT\\\",\\\"timestamp\\\":1743565900998,\\\"signature\\\":\\\"172aa6a069360f7b5569743bf29ad9eb2291f5526a826f7ec2c6a5851d429e0e\\\"}\",\"response\":\"{\\\"code\\\":0,\\\"timestamp\\\":1743565900301,\\\"data\\\":[{\\\"symbol\\\":\\\"BTC_USDT\\\",\\\"trades\\\":[{\\\"timestamp\\\":1743565900148,\\\"tradeId\\\":\\\"144225103\\\",\\\"price\\\":\\\"84632.86\\\",\\\"amount\\\":\\\"\\\",\\\"type\\\":2,\\\"volume\\\":\\\"0.002346\\\"}]}]}\",\"http_code\":200,\"curl_error\":\"\"}', '127.0.0.1', '2025-04-02 00:51:41'),
(26, 1, 'api_request', '{\"endpoint\":\"\\/openApi\\/spot\\/v1\\/ticker\\/price\",\"url\":\"https:\\/\\/open-api.bingx.com\\/openApi\\/spot\\/v1\\/ticker\\/price?symbol=BTCUSDT&timestamp=1743566025063&signature=97cadad0d53d66d5f995df5369722d47a531fe2c87dae3c4706f637d6d121b6b\",\"method\":\"GET\",\"headers\":[\"X-BX-APIKEY: mlTNw...\"],\"params\":\"{\\\"symbol\\\":\\\"BTCUSDT\\\",\\\"timestamp\\\":1743566025063,\\\"signature\\\":\\\"97cadad0d53d66d5f995df5369722d47a531fe2c87dae3c4706f637d6d121b6b\\\"}\",\"response\":\"{\\\"code\\\":100204,\\\"msg\\\":\\\"BTCUSDT not found or no trades lately\\\",\\\"timestamp\\\":1743566024379}\",\"http_code\":200,\"curl_error\":\"\"}', '127.0.0.1', '2025-04-02 00:53:45'),
(27, 1, 'api_request', '{\"endpoint\":\"\\/openApi\\/swap\\/v2\\/quote\\/price\",\"url\":\"https:\\/\\/open-api.bingx.com\\/openApi\\/swap\\/v2\\/quote\\/price?symbol=BTCUSDT&timestamp=1743566043443&signature=6b0729238119145f7d54bed909c192e5d6fd2007c9e1ee15b42778594ac89732\",\"method\":\"GET\",\"headers\":[\"X-BX-APIKEY: mlTNw...\"],\"params\":\"{\\\"symbol\\\":\\\"BTCUSDT\\\",\\\"timestamp\\\":1743566043443,\\\"signature\\\":\\\"6b0729238119145f7d54bed909c192e5d6fd2007c9e1ee15b42778594ac89732\\\"}\",\"response\":\"{\\\"code\\\":80014,\\\"msg\\\":\\\"Invalid parameters, err:symbol: This field must be either empty or end with -USDT or -USDC. \\\",\\\"data\\\":{}}\",\"http_code\":200,\"curl_error\":\"\"}', '127.0.0.1', '2025-04-02 00:54:03');

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
  `quantity` decimal(18,8) NOT NULL,
  `entry_price` decimal(18,8) NOT NULL,
  `current_price` decimal(18,8) DEFAULT NULL,
  `leverage` int(11) DEFAULT 1,
  `exit_price` decimal(18,8) DEFAULT NULL,
  `pnl` decimal(18,8) DEFAULT NULL,
  `status` enum('open','closed') NOT NULL DEFAULT 'open',
  `webhook_data` text DEFAULT NULL,
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
  ADD KEY `strategy_id` (`strategy_id`);

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `system_logs`
--
ALTER TABLE `system_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

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
