-- EA trade analytics: snapshot estatico + timeline de eventos + proceso de correccion.
-- Materializa en columnas consultables lo que hoy vive en blobs JSON
-- (user_telegram_signals.execution_data / mt_corrected_data / event_log) y en el CSV journal.
-- Idempotente; seguro de re-ejecutar.

-- =====================================================================
-- 1) ea_trade_snapshots  -- 1 fila por trade (ejecutado O rechazado): lo estatico
-- =====================================================================
CREATE TABLE IF NOT EXISTS `ea_trade_snapshots` (
  `id`                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_signal_id`     INT UNSIGNED NOT NULL,
  `telegram_signal_id` INT UNSIGNED DEFAULT NULL,
  `user_id`            INT UNSIGNED NOT NULL,
  `symbol`             VARCHAR(20) NOT NULL,
  `ea_version`         VARCHAR(10) DEFAULT NULL,

  -- Señal raw (pre-correccion)
  `dir`            ENUM('LONG','SHORT') DEFAULT NULL,
  `entry_raw`      DECIMAL(18,5) DEFAULT NULL,
  `sl_raw`         DECIMAL(18,5) DEFAULT NULL,

  -- Precios corregidos (lo que MT5 opero)
  `corr_on`        TINYINT(1) DEFAULT NULL,
  `corr_factor`    DECIMAL(12,6) DEFAULT NULL,
  `entry`          DECIMAL(18,5) DEFAULT NULL,
  `sl`             DECIMAL(18,5) DEFAULT NULL,
  `tp1`            DECIMAL(18,5) DEFAULT NULL,
  `tp2`            DECIMAL(18,5) DEFAULT NULL,
  `tp3`            DECIMAL(18,5) DEFAULT NULL,
  `tp4`            DECIMAL(18,5) DEFAULT NULL,
  `tp5`            DECIMAL(18,5) DEFAULT NULL,

  -- Escalas / gates derivados por trade
  `r_dist`         DECIMAL(18,5) DEFAULT NULL,   -- R = |entry - SL|
  `t1`             DECIMAL(18,5) DEFAULT NULL,   -- T1 = |entry - TP1|
  `spread_real`    DECIMAL(18,5) DEFAULT NULL,
  `spread_tol`     DECIMAL(18,5) DEFAULT NULL,
  `slip_real`      DECIMAL(18,5) DEFAULT NULL,
  `slip_tol`       DECIMAL(18,5) DEFAULT NULL,
  `price_signal`   DECIMAL(18,5) DEFAULT NULL,   -- MT5 vivo al recibir la señal
  `dist_entry`     DECIMAL(18,5) DEFAULT NULL,   -- |price_signal - entry|
  `side`           ENUM('FAVORABLE','ADVERSO') DEFAULT NULL,
  `k_band`         DECIMAL(18,5) DEFAULT NULL,   -- banda MARKET = k * T1
  `stops_min`      DECIMAL(18,5) DEFAULT NULL,   -- SYMBOL_TRADE_STOPS_LEVEL
  `sl_dist`        DECIMAL(18,5) DEFAULT NULL,
  `acct_balance`   DECIMAL(14,2) DEFAULT NULL,   -- balance al dimensionar (para la fórmula de volumen)
  `sl_risk_per_lot` DECIMAL(14,4) DEFAULT NULL,  -- riesgo en $ por 1 lote para la distancia de SL

  -- Ejecucion inicial
  `order_type`     VARCHAR(20) DEFAULT NULL,     -- MARKET / LIMIT / STOP / MARKET_FB
  `real_entry`     DECIMAL(18,5) DEFAULT NULL,
  `real_volume`    DECIMAL(12,2) DEFAULT NULL,
  `trade_id`       VARCHAR(40) DEFAULT NULL,

  -- Resultado final (se completa en el close)
  `max_level`      TINYINT DEFAULT NULL,
  `vol_closed_pct` DECIMAL(6,2) DEFAULT NULL,
  `exit_level`     SMALLINT DEFAULT NULL,
  `close_reason`   VARCHAR(40) DEFAULT NULL,
  `gross_pnl`      DECIMAL(14,2) DEFAULT NULL,
  `last_price`     DECIMAL(18,5) DEFAULT NULL,
  `result`         VARCHAR(40) DEFAULT NULL,

  -- Constantes de config del EA vigentes en ESTE trade
  `cfg_risk_percent`   DECIMAL(5,2) DEFAULT NULL,
  `cfg_k_stop_ratio`   DECIMAL(6,4) DEFAULT NULL,
  `cfg_k_limit_ratio`  DECIMAL(6,4) DEFAULT NULL,
  `cfg_m_slip_ratio`   DECIMAL(6,4) DEFAULT NULL,
  `cfg_c_spread_ratio` DECIMAL(6,4) DEFAULT NULL,
  `cfg_enable_slip`    TINYINT(1) DEFAULT NULL,
  `cfg_enable_spread`  TINYINT(1) DEFAULT NULL,
  `cfg_enable_corr`    TINYINT(1) DEFAULT NULL,
  `cfg_be_level`       TINYINT DEFAULT NULL,
  `cfg_tp1_pct`        DECIMAL(5,2) DEFAULT NULL,
  `cfg_tp2_pct`        DECIMAL(5,2) DEFAULT NULL,
  `cfg_tp3_pct`        DECIMAL(5,2) DEFAULT NULL,
  `cfg_tp4_pct`        DECIMAL(5,2) DEFAULT NULL,
  `cfg_tp5_pct`        DECIMAL(5,2) DEFAULT NULL,
  `cfg_extra`          JSON DEFAULT NULL,        -- inputs futuros sin migrar esquema

  `ts_signal`      DATETIME DEFAULT NULL,        -- timestamp de la señal (hora local)
  `opened_at`      DATETIME DEFAULT NULL,
  `closed_at`      DATETIME DEFAULT NULL,
  `created_at`     DATETIME NOT NULL,

  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user_signal` (`user_signal_id`),
  KEY `idx_user_symbol` (`user_id`, `symbol`),
  KEY `idx_close_reason` (`close_reason`),
  KEY `idx_order_type` (`order_type`),
  KEY `idx_ts_signal` (`ts_signal`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================================
-- 2) ea_trade_events  -- N filas por trade: la linea de tiempo (Cronologia)
-- =====================================================================
CREATE TABLE IF NOT EXISTS `ea_trade_events` (
  `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `snapshot_id`     INT UNSIGNED DEFAULT NULL,
  `user_signal_id`  INT UNSIGNED NOT NULL,
  `seq`             INT UNSIGNED NOT NULL,       -- orden dentro del trade (1,2,3...)

  `event_type`      ENUM('claimed','signal_received','open','pending_order','filled',
                         'progress','tp','breakeven','closed','failed') NOT NULL,

  `current_level`         TINYINT DEFAULT NULL,
  `last_price`            DECIMAL(18,5) DEFAULT NULL,
  `new_stop_loss`         DECIMAL(18,5) DEFAULT NULL,  -- solo en breakeven
  `volume_closed_percent` DECIMAL(6,2) DEFAULT NULL,
  `remaining_volume`      DECIMAL(12,2) DEFAULT NULL,
  `pnl_delta`             DECIMAL(14,2) DEFAULT NULL,  -- PNL de ESTE evento
  `pnl_cumulative`        DECIMAL(14,2) DEFAULT NULL,  -- acumulado hasta este evento
  `message`               VARCHAR(255) DEFAULT NULL,
  -- Self-sufficiente para la timeline (sin joins): order type al abrir, volumen, motivo de cierre
  `order_type`            VARCHAR(20) DEFAULT NULL,
  `volume`                DECIMAL(12,2) DEFAULT NULL,
  `close_reason`          VARCHAR(40) DEFAULT NULL,

  `event_time`      DATETIME DEFAULT NULL,             -- execution_time del EA (hora local)
  `created_at`      DATETIME NOT NULL,

  PRIMARY KEY (`id`),
  KEY `idx_signal_seq` (`user_signal_id`, `seq`),
  KEY `idx_snapshot` (`snapshot_id`),
  KEY `idx_type` (`event_type`),
  CONSTRAINT `fk_event_snapshot` FOREIGN KEY (`snapshot_id`)
    REFERENCES `ea_trade_snapshots` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================================
-- 3) ea_price_corrections  -- 1 fila por señal: el proceso de correccion (futuro vs CFD)
--    Puede existir sin snapshot (PRICE_CORRECTION_ERROR -> nunca opero).
-- =====================================================================
CREATE TABLE IF NOT EXISTS `ea_price_corrections` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_signal_id`  INT UNSIGNED NOT NULL,
  `snapshot_id`     INT UNSIGNED DEFAULT NULL,
  `symbol`          VARCHAR(20) NOT NULL,
  `enabled`         TINYINT(1) DEFAULT NULL,     -- ENABLE_PRICE_CORRECTION

  -- Lado futuro (Yahoo, UTC)
  `fut_price`        DECIMAL(18,5) DEFAULT NULL, -- last_close
  `fut_candle_time`  DATETIME DEFAULT NULL,      -- ts_epoch -> UTC (vela del futuro)

  -- Lado MT5 / CFD (reloj del broker)
  `mt5_price`         DECIMAL(18,5) DEFAULT NULL, -- iClose(barIndex), vela usada
  `mt5_bar_time`      DATETIME DEFAULT NULL,      -- iTime(barIndex) real
  `mt5_bar_index`     INT DEFAULT NULL,           -- barIndex de iBarShift
  `signal_mt5_price`  DECIMAL(18,5) DEFAULT NULL, -- MT5 vivo al recibir señal (staleness check)
  `broker_offset_sec` INT DEFAULT NULL,           -- TimeCurrent - TimeGMT
  `target_broker_time` DATETIME DEFAULT NULL,     -- ts_epoch + offset (lo que se buscó)

  -- Verificacion
  `bar_gap_sec`      INT DEFAULT NULL,            -- mt5_bar_time - target_broker_time (0 = misma vela)
  `candles_aligned`  TINYINT(1) DEFAULT NULL,     -- |bar_gap_sec| <= tolerancia
  `corr_factor`      DECIMAL(12,6) DEFAULT NULL,  -- fut_price / mt5_price
  `deviation_pct`    DECIMAL(8,4) DEFAULT NULL,   -- |factor-1| * 100
  `timestamp_age_sec` INT DEFAULT NULL,           -- antiguedad del dato del futuro

  -- Resultado
  `status`        ENUM('OK','ERROR') NOT NULL,
  `error_stage`   ENUM('FETCH','INVALID_DATA','STALE_TIMESTAMP',
                       'NO_BAR','INVALID_CFD','DEVIATION_TOO_HIGH') DEFAULT NULL,
  `error_message` VARCHAR(255) DEFAULT NULL,

  `created_at`    DATETIME NOT NULL,

  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_corr_signal` (`user_signal_id`),
  KEY `idx_snapshot` (`snapshot_id`),
  KEY `idx_status` (`status`),
  KEY `idx_aligned` (`candles_aligned`),
  CONSTRAINT `fk_corr_snapshot` FOREIGN KEY (`snapshot_id`)
    REFERENCES `ea_trade_snapshots` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
