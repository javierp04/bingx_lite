-- phpMyAdmin SQL Dump
-- version 5.0.4deb2+deb11u2
-- https://www.phpmyadmin.net/
--
-- Servidor: localhost:3306
-- Tiempo de generación: 16-09-2025 a las 20:06:13
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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `user_selected_tickers`
--
ALTER TABLE `user_selected_tickers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `user_telegram_signals`
--
ALTER TABLE `user_telegram_signals`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
