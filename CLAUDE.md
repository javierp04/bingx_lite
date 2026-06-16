# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a **BingX Trading Signal Management System** built with CodeIgniter 3. It processes trading signals from TradingView webhooks and Telegram messages, manages trades on BingX exchange (spot & futures), and integrates with MetaTrader Expert Advisors (EA). Users can track trades, manage strategies, and receive AI-analyzed signals from Telegram.

**Tech Stack:**

- Framework: CodeIgniter 3 (PHP)
- Database: MySQL/MariaDB
- APIs: BingX Exchange API, OpenAI API (for signal analysis), Yahoo Finance API (for futures prices)
- Webhooks: TradingView, Telegram Bot

## Development Environment

This is a **XAMPP-based development environment** on Windows.

**Git workflow:** work directly on `main` — this repo does **not** use feature branches. Commit straight to `main`.

### Key Commands

**Start/Stop Services:**

```bash
# Start Apache and MySQL via XAMPP Control Panel
# No build or compilation steps required - PHP runs directly
```

**Database Setup:**

```bash
# Import database schema
mysql -u u_bingx -p bingx_lite < bingx_lite.sql
```

**Database Credentials:**

- Host: localhost
- Database: bingx_lite
- Username: u_bingx
- Password: Pelota01\*

**Access Application:**

```
http://localhost/bingx_lite/
```

## Architecture

### MVC Structure (CodeIgniter 3)

**Controllers** (`application/controllers/`):

- `Auth.php` - Login/logout, default route
- `Dashboard.php` - Main trading dashboard (BingX real-time PNL updates)
- `My_trading.php` - User trading dashboard with Telegram signals (active/signals/tickers tabs)
- `Webhook.php` - TradingView webhook processor (converts TV alerts to BingX orders)
- `TradeReader.php` - Telegram webhook processor (receives messages, triggers AI analysis)
- `Api.php` - MetaTrader EA API endpoints (signal distribution, trade reporting)
- `Telegram_signals.php` - Admin signal management
- `Strategies.php`, `Trades.php`, `Users.php`, `ApiKeys.php` - CRUD management

**Models** (`application/models/`):

- `Telegram_signals_model.php` - Central signal lifecycle management (available → claimed → open → closed)
- `User_tickers_model.php` - User ticker subscriptions with MT symbol mapping
- `Trade_model.php` - BingX/MT trades
- `Strategy_model.php`, `Api_key_model.php`, `User_model.php`, `Log_model.php`

**Libraries** (`application/libraries/`):

- `Bingxapi.php` - BingX exchange integration (spot/futures, production/sandbox environments)
- `Webhook_processor.php` - TradingView signal processing
- `Mt_signal_processor.php` - MetaTrader signal processing

### Signal Flow Architecture

**Telegram Signal Lifecycle:**

1. **Inbound**: Telegram → `TradeReader.php` webhook → stores in `telegram_signals` table
2. **Processing**: Status progression: `pending` → `analyzing` → `completed` (via OpenAI analysis)
3. **Distribution**: Signal duplicated to `user_signals` table (one per subscribed user)
4. **User Lifecycle**: `available` → `claimed` (EA pulls) → `open` (trade started) → `closed` (trade completed)
5. **Tracking**: EA reports back via `/api/signals/{id}/open|progress|close` endpoints

**TradingView Signal Flow:**

1. TradingView Alert → `Webhook.php` → `Webhook_processor.php`
2. Executes BingX order via `Bingxapi.php`
3. Records trade in `trades` table

**MetaTrader Integration:**

- EA polls `/api/signals/{user_id}/{ticker_symbol}` for new signals every POLL_INTERVAL seconds
- System returns oldest `available` signal, marks as `claimed`
- EA reports trade progress back via `/api/signals/{id}/open|progress|close`

### MetaTrader Expert Advisor (EA_Signals.mq5)

**Location:** `EA/EA_Signals.mq5`
**Version:** 10.21
**Language:** MQL5 (MetaTrader 5)
**Lines:** ~2,200

Polls the backend for Telegram-derived signals, executes them on the chart symbol, manages multi-TP partial closes + breakeven, persists state to disk (survives EA/terminal restarts), reports execution back to the API, and writes a CSV trade journal (dataset + live). **Asset-agnostic:** every entry/cost gate is scaled to the signal's own size (`T1 = |entry − TP1|`), so it works across FX, indices, oil, etc. without per-symbol tuning.

#### EA Architecture Overview

**Signal → Execution Flow:**

```
OnInit() → AdoptStateOnInit() (reconcile persisted state vs MT5) → EventSetTimer(POLL_INTERVAL)
    ↓
OnTimer() → (idle?) CheckForSignals() → GET /api/signals/{USER_ID}/{TICKER_SYMBOL}
    ↓
ProcessSignalResponse() → parse {user_signal_id, op_type, entry, stoploss[], tps[]} → validate SL + TP1..5
    ↓
ExecuteTrade():
    price correction → T1/R scale → spread gate → risk volume →
    order type MARKET/STOP/LIMIT (by k·T1) → broker STOPS_LEVEL gate → place order
    ↓
ReportOpen() → POST /api/signals/{id}/open   (raw + corrected price sets)
    ↓
OnTick():  pending → CheckPendingOrderExecution() → ReportPendingExecuted()
           live    → ManageTPs() → partial close → ReportProgress() → POST .../progress
    ↓
TP tranche (integer lots, pre-split at open) brings remaining → 0 ⇒ ReportClose(COMPLETE) → POST .../close
position vanished   → GetCloseReasonFromHistory() → ReportClose()
```

#### Key Configuration Parameters (`input`s)

**API:**

- `API_URL` = "http://bx-trade.local/api/" - Base API endpoint
- `USER_ID` = 1 - User id for signal queries
- `TICKER_SYMBOL` = "EURUSD" - Server-side symbol sent in API requests/reports (the EA trades the chart symbol)

**Trading:**

- `RISK_PERCENT` = 2.0 - Risk per trade as % of balance (max 10)
- `POLL_INTERVAL` = 10 - Seconds between signal polls (min 5)

**Asset-Agnostic Gates** (all anchored to `T1 = |entry − TP1|`):

- `K_STOP_RATIO` = 0.05 - Market band on the favorable side (→ STOP). Respects the analyst's stop entry (wait for confirmation); kept `≥ M_SLIP_RATIO` so the favorable band is at least as wide as the slippage cap.
- `K_LIMIT_RATIO` = 0.10 - Market band on the adverse side (→ LIMIT). Wider than STOP to absorb signal→execution latency (price drifted past entry); must be > `M_SLIP_RATIO`.
- `ENABLE_SLIP_CHECK` = false - Slippage cap is **off by default** (high-liquidity brokers/hours): market deviation is set wide (no effective cap). The slippage tolerance is still recorded in the journal; it just doesn't gate. When **on**, deviation = `M_SLIP_RATIO · T1`.
- `M_SLIP_RATIO` = 0.04 - When `ENABLE_SLIP_CHECK` is on: slippage/deviation cap, market only; must be < `K_LIMIT_RATIO`. Unused (no constraint) when the check is off.
- `ENABLE_SPREAD_CHECK` = false - Spread gate is **off by default** (built for high-liquidity brokers/hours). The spread is still recorded in the journal; it just doesn't reject.
- `C_SPREAD_RATIO` = 0.05 - When `ENABLE_SPREAD_CHECK` is on: reject if spread > `C_SPREAD_RATIO · T1` (recalibrated from 0.40 — a spread that big would eat ~40% of TP1)

**Price Correction:**

- `ENABLE_PRICE_CORRECTION` = true - Align signal prices to the real futures market
- `MAX_PRICE_DEVIATION` = 5.0 - Max allowed deviation %
- `MAX_TIMESTAMP_HOURS` = 4 - Max age of futures price data

**Take Profit Distribution:**

- `TP1_PERCENT` = 0.0 / `TP2` = 40 / `TP3` = 30 / `TP4` = 20 / `TP5` = 10 - % of volume closed at each TP. The volume is split into **integer lots at open** (largest-remainder) so each tranche is ≥ 1 lot step; a TP with 0% (or whose share rounds to 0 lots) closes nothing, and TP5 (closeAll) sweeps any remainder.
- `BE_LEVEL` = 1 - TP level at which SL moves to breakeven (0 = never)

**Trading Hours / Order / Stop / Logging:**

- `ENABLE_TIME_FILTER` = false, `START_HOUR` = 11, `END_HOUR` = 13 (GMT, skips weekends)
- `MAGIC_NUMBER` = 12345, `TRADE_COMMENT` = "TelegramSignal"
- `ENABLE_CODE_STOP` = false (close by code if price crosses SL), `SAFETY_FACTOR` = 1.5
- `MIN_LOG_LEVEL` = INFO_LVL (DEBUG_LVL / INFO_LVL / WARNING_LVL / ERROR_LVL)

> Legacy `MAX_SPREAD` and `DEBUG_MODE`/`DEBUG_*` inputs no longer exist — spread is gated by `C_SPREAD_RATIO · T1`.

#### Order Type Decision (`DetermineOrderType`)

Distance between current market price and signal entry, measured in units of T1:

- distance ≤ `k · T1` → **MARKET** (`k` = `K_STOP_RATIO` if price is on the favorable side of entry, else `K_LIMIT_RATIO`)
- otherwise: favorable side → **STOP** order at entry; adverse side → **LIMIT** order at entry
- Market orders set deviation via `ENABLE_SLIP_CHECK`: on → `M_SLIP_RATIO · T1` (always < the `k` band, by the `M < K_LIMIT` invariant); off (default) → wide deviation (no cap)
- **STOP/LIMIT too close to market** (broker returns `TRADE_RETCODE_INVALID_STOPS` because the pending price is within `SYMBOL_TRADE_STOPS_LEVEL`) → **fallback to MARKET** (v10.20). Self-gating: on brokers with stops level 0 the pending is never rejected, so the fallback never fires. Journalled as `MARKET_FB`.
- Prices sent to the broker (pending entry + SL) are normalized to the symbol's `tickSize`/`digits` before sending (v10.20) — the price correction leaves arbitrary decimals that some brokers reject.

`R = |entry − SL|` is used for volume sizing; `T1` for the entry/cost gates. Both use post-correction prices.

#### EA Reporting Callbacks

**ReportOpen** (`POST /api/signals/{id}/open`) — sends raw pre-correction prices as `signal_data` and the corrected prices MT5 actually used as `mt_corrected_data`:

```json
{
  "success": true,
  "trade_id": "12345",
  "order_type": "ORDER_TYPE_BUY",
  "real_entry_price": 1.085,
  "real_stop_loss": 1.08,
  "real_volume": 0.1,
  "signal_data": { "op_type": "LONG", "entry": 1.085, "stoploss": [1.08, 0.0], "tps": [1.09, 1.092, 1.095, 1.098, 1.10] },
  "mt_corrected_data": { "op_type": "LONG", "entry": 1.0849, "stoploss": [1.0799, 0.0], "tps": [/* ... */] },
  "symbol": "EURUSD",
  "execution_time": "2026-06-14 14:30:00"
}
```

`real_entry_price` / `real_stop_loss` are sent only for market orders; for STOP/LIMIT orders the fill is reported later via ReportProgress (`now_open`).

**ReportProgress** (`POST /api/signals/{id}/progress`) — only dynamic fields; `new_stop_loss` only when moving to breakeven. A pending order that fills adds `now_open`, `trade_id`, `real_entry_price`, `real_stop_loss`:

```json
{
  "current_level": 2,
  "volume_closed_percent": 40.0,
  "remaining_volume": 0.06,
  "gross_pnl": 125.5,
  "last_price": 1.092,
  "message": "TP parcial",
  "new_stop_loss": 1.085,
  "execution_time": "2026-06-14 15:45:00"
}
```

**ReportClose** (`POST /api/signals/{id}/close`):

```json
{
  "success": true,
  "exit_level": 5,
  "close_reason": "CLOSED_COMPLETE",
  "gross_pnl": 248.75,
  "last_price": 1.0985,
  "symbol": "EURUSD",
  "execution_time": "2026-06-14 18:22:00"
}
```

**Exit Level Codes:**

- `1`–`5`: closed at TP1–TP5
- `-1` (EXIT_STOP): closed by Stop Loss (broker or code-stop)
- `-998` (EXIT_INVALID): invalid signal (missing SL/TPs) — never operated
- `-999` (EXIT_ERROR): execution / price-correction / gate error — never operated

**Close Reason Codes** — the server matches these **exact strings** (`Telegram_signals_model::is_failure_reason`); do NOT change the values.

Real closes (the trade ran):

- `CLOSED_COMPLETE` - all volume closed / final TP
- `CLOSED_STOPLOSS` - stop loss hit (SL still at its original/losing level)
- `CLOSED_BREAKEVEN` - SL hit after it was moved to breakeven (`slMovedToBE`); a TP was reached first, so it is **not** a losing stop (v10.17)
- `CLOSED_MANUAL` - manual close detected in history
- `CLOSED_EXTERNAL` - closed by expert/other (default)
- `CLOSED_CODE_STOP` / `CLOSED_SAFETY_STOP` - code-based / safety stop
- `ORDER_CANCELLED` - pending order cancelled (server status → cancelled)

Failure reasons (never operated → server status `failed_execution`):

- `INVALID_STOPLOSS`, `INVALID_TPS`, `INVALID_OPTYPE`, `INVALID_ENTRY`, `PRICE_CORRECTION_ERROR`, `SPREAD_TOO_HIGH`, `VOLUME_ERROR`, `SL_TOO_CLOSE`, `EXECUTION_FAILED`

  (`op_type` must be exactly `LONG`/`SHORT` and `entry` > 0, else the signal is rejected without operating — v10.16. These plus `SL_TOO_CLOSE` are matched by `Telegram_signals_model::is_failure_reason`.)

#### State Persistence & Restart Reconciliation

- `OptimizedTPState` tracks the single active trade (or pending order): ticket, positionID, direction, volumes, currentLevel, `levelFlags[6]`, `slMovedToBE`, entry/SL/TP prices.
- `SaveState` / `LoadState` / `ClearState` persist it as one JSON line in `MQL5/Files/bxlite_state_{USER_ID}_{TICKER}.json`, so a trade survives EA/terminal restarts.
- On startup, `AdoptStateOnInit` reconciles the saved state against MT5:
  1. live own position (magic+symbol) found → re-adopt (and, if it had been pending, report the fill that happened while down);
  2. pending order still alive → keep waiting;
  3. neither → trade closed/cancelled while down → reconcile the close from history and report it.

#### Pending Order Handling

STOP/LIMIT orders are monitored in `OnTick` via `CheckPendingOrderExecution`:

- filled → `AdoptLivePosition` + `ReportPendingExecuted` (`now_open`);
- cancelled (e.g. `ORDER_TIME_DAY` expiry) → `ReportClose(ORDER_CANCELLED)`.

#### CSV Trade Journal

Two files in `MQL5/Files/`:

- `bxlite_journal_{USER_ID}_{TICKER}.csv` — dataset: one row appended per closed **or rejected** trade.
- `bxlite_live_{USER_ID}_{TICKER}.csv` — header + the current trade row, overwritten on each state change.

Each row captures the full feature vector: raw vs corrected prices, R, T1, real/tolerated spread, entry distance & side, order-type decision, real entry/slippage, broker stops level, max level reached, % closed, exit level, close reason, gross PnL. Intended for offline analysis/ML.

#### Price Correction Mechanism

1. EA calls `/api/fut_price/{symbol}` (Yahoo Finance proxy) → `last_close` + `ts_epoch`.
2. `correctionFactor = futurePrice / cfdPrice` (CFD price from the M1 bar matching the futures timestamp, after applying the broker-time offset).
3. Validates deviation < `MAX_PRICE_DEVIATION` and timestamp freshness < `MAX_TIMESTAMP_HOURS`.
4. Divides entry / SL / all TPs by the factor (signal prices are in futures space; MT5 trades CFD space).
5. Any failure → `ReportClose(PRICE_CORRECTION_ERROR)`; the trade is not taken.

#### Important EA Behaviors

**Signal validation:** SL1 and all of TP1–TP5 must be > 0, else the signal is rejected (`INVALID_STOPLOSS` / `INVALID_TPS`) without operating. **Geometry is also validated** (v10.20): for LONG `sl1 < entry < tp1 < tp2 < tp3 < tp4 < tp5`, inverse for SHORT — a wrong-side SL or out-of-order/wrong-side TP is rejected (`INVALID_STOPLOSS` / `INVALID_TPS`), preventing absurd executions (a wrong-side TP would be "reached" instantly; a wrong-side SL would mirror to a made-up level).

**Volume management:**

- Risk-based: `(Balance · RISK_PERCENT/100) / SL_distance`, floored to the lot step.
- **v10.15 — integer-lot split at open (`ComputeLevelVolumes`):** the original volume is divided among the 5 TPs by their percentages using the **largest-remainder method**, in whole lot steps that sum exactly to the original. Each TP closes a fixed tranche ≥ 1 step and the last tranche with lots brings remaining to 0, so the sub-min "dust" remnant **never comes into existence** — no dust-close, no retries, no in-tick recompute. Split is computed once in `SetupTPState`, persisted in `levelVolumes[]` (survives restarts), and consumed in `CheckAndExecuteTP`.
- `COMPLETE` is reported **only when the position is actually closed** (remaining 0). Any stop — broker SL, code-stop, safety, or SL moved to breakeven — closes 100% of the remainder.
- With small volumes, a TP whose share rounds below one lot step gets 0 lots and is skipped, so the trade simply completes at an earlier TP (e.g. 40/40/20 on a 2-lot position closes 50/50 at TP1/TP2). This is the lot-minimum at work, not a bug.

**Breakeven:** at `BE_LEVEL`, SL → entry (zero risk), reported via `ReportProgress(new_stop_loss)`.

**Historical close detection:** if the position disappears, `GetCloseReasonFromHistory` inspects the closing deal to classify SL / TP / manual / external and avoid duplicate close reports.

**Single position constraint:** the EA handles one trade (or pending order) at a time (`HasTrade()`); new signals are ignored while busy.

**Known limitation — report delivery is best-effort (not yet retried):** `ReportOpen/Progress/Close` log on failure but do not retry, and the close path calls `EndTrade()` (clears state) regardless. If a `POST .../close` fails (network down, server restarting) the trade is closed in MT5 but the server never learns → the `user_signal` stays `open` in the DB. A robust fix (a persistent outbound queue retried by `OnTimer`, plus making `report_close`/`report_progress` idempotent server-side — they currently *add* `gross_pnl` cumulatively, so naive retries would double-count) is pending.

### TradingView Expert Advisor (EA_TV.mq5)

**Location:** `EA_TV.mq5` (root directory)
**Version:** 1.00
**Language:** MQL5 (MetaTrader 5)
**Lines:** 632

#### EA Architecture Overview

**Signal Polling Flow:**

```
OnInit() → EventSetTimer(CheckInterval)
    ↓
OnTimer() → CheckPendingSignals()
    ↓
GET /metatrader/pending_signals?user_id={USER_ID}&symbol={SYMBOL}
    ↓
ProcessServerResponse() → Parse JSON array of signals
    ↓
For each signal → ProcessSingleSignal()
    ↓
ExecuteMarketOrder() → trade.Buy() or trade.Sell()
    ↓
POST /metatrader/confirm_execution (success or failure)
```

#### Key Configuration Parameters

**API Settings:**

- `ServerURL` = "https://2bunnylabs.com" - Base server URL
- `UserID` - User ID for signal queries
- `ServerSymbol` = "EURUSD" - Symbol name sent to server (without broker suffix)
- `CheckInterval` = 5 - Seconds between signal checks (1-300)

**Trading Settings:**

- `DefaultLotSize` = 0.01 - Default lot size if signal doesn't specify
- `MagicNumber` = 123456 - Magic number to identify orders
- `MaxSlippage` = 3 - Maximum slippage in points
- `BrokerSuffix` = "" - Broker symbol suffix (e.g., ".m", ".raw")
- `UseCurrent Symbol` = true - Use chart symbol instead of ServerSymbol

**Debug & Logging:**

- `EnableLogging` = true - Enable detailed logging
- `EnableConsoleOutput` = true - Show logs in console
- `EnableFileLogging` = true - Save logs to file
- `LogLevel` = 2 - Logging level (0=ERROR, 1=WARNING, 2=INFO, 3=DEBUG)

#### Signal Data Structure

**SignalData Struct:**

```mql5
struct SignalData {
    long signal_id;           // ID from database
    string action;            // "buy" or "sell"
    string ticker;            // Symbol (e.g., "EURUSD")
    double quantity;          // Lot size (0 = use DefaultLotSize)
    double price;             // Entry price (not used - uses market)
    double stop_loss;         // Stop loss price (0 = no SL)
    double take_profit;       // Take profit price (0 = no TP)
    string position_id;       // Unique position identifier
    string strategy_id;       // Strategy identifier
    long user_id;             // User ID
};
```

#### EA Workflow Details

**1. Signal Polling (OnTimer - Every CheckInterval seconds):**

- Calls `GET /metatrader/pending_signals?user_id={ID}&symbol={SYMBOL}`
- Expects JSON array: `[{signal_data}, {signal_data}, ...]`
- Empty array `[]` means no pending signals
- Processes ALL signals in single poll

**2. Signal Processing:**

- **Validation**: Checks ticker matches ServerSymbol (rejects mismatched symbols)
- **Action Parsing**: Converts "buy"/"sell" to ORDER_TYPE_BUY/ORDER_TYPE_SELL
- **Lot Normalization**:
  - Uses `signal.quantity` if > 0, otherwise `DefaultLotSize`
  - Normalizes to broker's min/max/step lot sizes
  - Formula: `max(min_lot, min(max_lot, round(quantity/lot_step) * lot_step))`

**3. Order Execution:**

- **Market Orders Only** (no pending orders)
- Uses current Ask/Bid price (ignores signal.price)
- Applies SL/TP if provided in signal
- Comment format: `"TradingView Signal: {position_id}"`

**4. Execution Reporting:**

**Success Report (POST /metatrader/confirm_execution):**

```json
{
	"position_id": "unique_id",
	"status": "success",
	"execution_price": 1.08503
}
```

**Failure Report (POST /metatrader/confirm_execution):**

```json
{
	"position_id": "unique_id",
	"status": "failed",
	"error_message": "Código: 10006 - No enough money"
}
```

#### Symbol Mapping

**Key Concept:**

- `ServerSymbol`: Symbol name used in API requests (e.g., "EURUSD")
- `g_symbol`: Actual broker symbol used for trading (e.g., "EURUSD.m")
- `BrokerSuffix`: Added to ServerSymbol to get g_symbol

**Modes:**

1. **UseCurrentSymbol = true**: Uses chart symbol (ignores ServerSymbol + BrokerSuffix)
2. **UseCurrentSymbol = false**: Uses `ServerSymbol + BrokerSuffix`

**Example:**

- ServerSymbol = "EURUSD"
- BrokerSuffix = ".m"
- UseCurrent Symbol = false
- → Trades on "EURUSD.m"

#### Logging System

**Log Levels:**

- `0 = ERROR`: Critical errors only
- `1 = WARNING`: Warnings + errors
- `2 = INFO`: General information + warnings + errors
- `3 = DEBUG`: All messages including verbose debug

**Log File:**

- Name: `TradingView_EA_{UserID}_{ServerSymbol}.log`
- Location: `MQL5/Files/`
- Format: Timestamped entries with level prefix

#### Error Handling

**HTTP Errors:**

- Returns -1: Network/permission error (check GetLastError())
- Returns ≠ 200: Server error (logs HTTP code)

**Trade Errors:**

- Captures `trade.ResultRetcode()` and `trade.ResultRetcodeDescription()`
- Reports back to server via failure callback
- Common codes: 10006 (no money), 10016 (invalid stops), etc.

**Connection Resilience:**

- If initial connection fails at OnInit, continues retrying every CheckInterval
- Does not stop EA if server temporarily unavailable

#### Important EA Behaviors

**Multiple Signals:**

- EA can process MULTIPLE signals per poll cycle
- No single-position constraint (unlike EA_Signals.mq5)
- Executes all pending signals sequentially in one timer event

**No Position Management:**

- EA only OPENS positions (no TP management, no trailing stop, no breakeven)
- Relies on MT broker to handle SL/TP if set
- No OnTick() position monitoring (stateless after execution)

**Simple JSON Parser:**

- Custom lightweight parser (no external libraries)
- Handles nested `signal_data` object extraction
- Functions: `GetJSONStringValue()`, `GetJSONDoubleValue()`, `GetJSONLongValue()`

**Broker Compatibility:**

- Uses `ORDER_FILLING_FOK` (Fill-Or-Kill)
- Falls back to other filling modes may be needed for some brokers
- Validates symbol exists via `symbolInfo.Name()` at startup

### Module Access Control System

The system supports three trading modules with per-user access control:

- **BingX** (`module_bingx`) - BingX exchange trading via TradingView webhooks
- **MetaTrader TV** (`module_metatrader`) - MetaTrader signals from TradingView
- **AT VIP Trading** (`module_atvip`) - Telegram signal trading via MetaTrader EA

**Key Files:**

- `application/helpers/modules_helper.php` - Central helper with `has_module()`, `user_modules()`, `has_only_module()`, `get_allowed_sources()`, etc.
- `application/views/users/_module_checkboxes.php` - Reusable partial for user add/edit forms

**Database:** `users` table has `module_bingx`, `module_metatrader`, `module_atvip` TINYINT(1) columns.

**Session:** Module flags stored on login. Admins auto-grant all modules.

**UI Behavior:**

- Navigation menus conditionally shown based on `has_module()` checks
- ATVIP-only users: Dashboard redirects to `my_trading/active` (no code duplication)
- ATVIP + other modules: ATVIP Trading menu visible in header
- API Keys menu only shown if user has BingX module
- Dashboard platform filter hidden when user has only 1 module
- Trade History uses `source`-based tabs (bingx, metatrader_tv, atvip) instead of platform
- ATVIP tab in Trade History has no Strategy dropdown (single strategy: ATVIP_SIGNALS)

**Controller Guards:**

- `ApiKeys` requires `bingx` module
- `My_trading` requires `atvip` module
- `Users`, `Strategies`, admin routes require admin role

**Trade Source Values** (`trades.source` column):

- `bingx` - BingX exchange trades
- `metatrader_tv` - MetaTrader TradingView signal trades
- `atvip` - AT VIP Telegram signal trades

### Database Schema Key Points

**Core Tables:**

- `telegram_signals` - Master signals from Telegram
- `user_signals` - Per-user signal instances (linked to `telegram_signals.id`)
- `trades` - Executed trades (BingX or MetaTrader), with `source` column for origin tracking
- `user_tickers` - User ticker subscriptions with MT symbol mapping
- `strategies` - Trading strategies (can be BingX or MetaTrader platform)
- `api_keys` - User BingX API credentials
- `users` - User accounts with module access flags (`module_bingx`, `module_metatrader`, `module_atvip`)

**EA Analytics Tables** (migration `database/migrations/2026-06-16-ea-trade-snapshots.sql`, populated by `Telegram_signals_model` from EA reports — EA v10.21+):

- `ea_trade_snapshots` - 1 row per trade (executed or rejected): full feature vector (raw/corrected prices, R/T1, gates, order-type decision) + the EA config constants in effect (`cfg_*`).
- `ea_trade_events` - relational timeline (one row per lifecycle hit: claimed/open/pending/filled/tp/breakeven/closed/failed) with cumulative PnL. **Single source of truth** for the timeline; `user_telegram_signals.event_log` is now only a pre-migration fallback.
- `ea_price_corrections` - 1 row per signal: price-correction telemetry (futures vs CFD candle, broker offset, candle-alignment check, deviation, error stage).

All writes are guarded by a `table_exists` check, so the trading flow is unaffected if the migration hasn't run.

**EA Report Idempotency** (migration `database/migrations/2026-06-17-ea-report-dedup.sql`): `ea_report_dedup` stores a `sha1` of each report body keyed by `UNIQUE(user_signal_id, body_hash)`. `report_open/progress/close` run an `INSERT IGNORE` inside a transaction before applying — a re-sent report (identical body) hits the unique index → `affected_rows()==0` → idempotent no-op (no double-counted PnL, no duplicate events/trades); a mid-apply failure rolls back the dedup row too, so a retry stays clean. Also `table_exists`-guarded (applies directly if not migrated). This is the **server-side base for a future EA outbound-retry queue** — the EA itself is unchanged and still reports best-effort (no retry yet); the contract for that future queue is to **resend the byte-identical body** (same `execution_time`) so the hash matches.

**Unified trade detail:** `/journals` is the DB-backed analytics area — drill-down `journals` (overview) → `journals/symbol/{SYM}` (trade list) → `journals/symbol/{SYM}/{id}` (trade detail). The detail view (`application/views/journals/trade_detail.php`) is shared by `Journals::trade` (admin, any user) and `My_trading::trading_detail` (owner-scoped); it prefers the `ea_trade_*` tables and falls back to the legacy JSON blobs for historical trades. The old `my_trading/trading_detail.php` view is deprecated/removed.

**Important Relationships:**

- Telegram signals are duplicated to multiple users via `user_signals` table
- Each `user_signal` tracks its own status independently
- `trades.source` distinguishes trade origin (bingx, metatrader_tv, atvip)
- `strategies.platform` distinguishes BingX vs MetaTrader strategies (ATVIP uses platform='metatrader' with strategy_id='ATVIP_SIGNALS')

## Configuration

**Environment:**

- Set in `index.php` line 56: `define('ENVIRONMENT', 'development')`
- Controls error reporting and logging

**BingX API Environments:**

- Configured in `application/config/constants.php`
- Production: `https://open-api.bingx.com`
- Sandbox (futures only): `https://open-api-vst.bingx.com`
- Environment set per-strategy in database

**OpenAI API Key:**

- Hardcoded in `application/config/config.php` line 7
- Used for Telegram signal analysis

## Important Implementation Notes

### BingX API Integration

- Symbol formatting: BingX uses hyphenated format (e.g., `BTC-USDT`) for spot
- Environment switching: Call `Bingxapi::set_environment('production'|'sandbox')` before API calls
- Price caching: Dashboard uses batch price fetching to minimize API calls

### Route Configuration

- Routes defined in `application/config/routes.php`
- Order matters: specific routes MUST come before generic ones (e.g., `my_trading/add_ticker` before `my_trading/(:any)`)
- API routes for MetaTrader are grouped at bottom with specific POST routes before generic GET

### AJAX Refresh Patterns

- Dashboard PNL updates: `Dashboard::refresh_trades()` - updates only BingX trades (MT trades don't have real-time prices)
- My Trading dashboard: `My_trading::refresh_dashboard_ajax()` - full dashboard content refresh with filters

### MetaTrader EA Communication

- EA uses GET `/api/signals/{user_id}/{ticker}` to poll for new signals
- Claiming mechanism prevents duplicate signal execution
- EA reports back execution status (open/progress/close) for PNL tracking

### Futures Price Data

- Yahoo Finance API used for MetaTrader futures prices (not BingX)
- Endpoint: `/api/fut_price/{symbol}` (automatically appends `=F` suffix)
- Returns last closed candle price to avoid incomplete data

## Common Workflows

### Adding New Telegram Signal Processing

1. Modify signal extraction in `TradeReader::generateSignalFromTelegram()`
2. Update OpenAI prompt in processing logic for analysis
3. Test with Telegram webhook: POST to `/tradereader/run`

### Adding New Strategy

1. Create via UI at `/strategies/add`
2. Platform field determines if BingX or MetaTrader
3. For MetaTrader: ensure ticker mapping in `user_tickers` table

### Debugging Signal Issues

1. Check `system_logs` table for processing errors
2. Verify signal status progression in `telegram_signals` and `user_signals`
3. For MetaTrader: check EA can access `/api/signals/{user_id}/{ticker}` endpoint
4. BingX trades: verify API key exists and environment is correct

### User Ticker Management

- Users subscribe to tickers via `/my_trading/tickers`
- MT ticker mapping required for MetaTrader strategies (e.g., BTC → BTCUSD)
- Active status determines if signals are generated for user

## Security Notes

**WARNING:** This codebase contains hardcoded credentials:

- Database password in `application/config/database.php`
- OpenAI API key in `application/config/config.php`
- These should be moved to environment variables before deployment

**CSRF Protection:** Currently disabled (`application/config/config.php` line 47)
