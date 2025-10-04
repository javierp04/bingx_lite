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
- Password: Pelota01*

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
- `BingXApi.php` - BingX exchange integration (spot/futures, production/sandbox environments)
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
2. Executes BingX order via `BingXApi.php`
3. Records trade in `trades` table

**MetaTrader Integration:**
- EA polls `/api/signals/{user_id}/{ticker_symbol}` for new signals every POLL_INTERVAL seconds
- System returns oldest `available` signal, marks as `claimed`
- EA reports trade progress back via `/api/signals/{id}/open|progress|close`

### MetaTrader Expert Advisor (EA_Signals.mq5)

**Location:** `EA_Signals.mq5` (root directory)
**Version:** 8.04
**Language:** MQL5 (MetaTrader 5)
**Lines:** 1,312

#### EA Architecture Overview

**Signal Acquisition Flow:**
```
OnInit() → EventSetTimer(POLL_INTERVAL)
    ↓
OnTimer() → CheckForSignals()
    ↓
GET /api/signals/{USER_ID}/{TICKER_SYMBOL}
    ↓
ProcessSignalResponse() → Parse JSON (user_signal_id, op_type, entry, stoploss[], tps[])
    ↓
ExecuteTrade() → Apply price correction → Calculate volume → Execute order
    ↓
ReportOpen() → POST /api/signals/{id}/open
    ↓
OnTick() Loop → ManageTPs() → ReportProgress() → POST /api/signals/{id}/progress
    ↓
Position Closed → ReportClose() → POST /api/signals/{id}/close
```

#### Key Configuration Parameters

**API Settings:**
- `API_URL` = "http://bxlite.local/api/" - Base API endpoint
- `USER_ID` - User ID for signal queries
- `TICKER_SYMBOL` - Symbol to trade (e.g., "EURUSD")

**Trading Settings:**
- `RISK_PERCENT` = 2.0 - Risk per trade as % of balance
- `POLL_INTERVAL` = 30 - Seconds between signal checks
- `MAX_SPREAD` = 500.0 - Maximum spread in points

**Take Profit Distribution:**
- `TP1_PERCENT` = 0.0 - % of volume to close at TP1
- `TP2_PERCENT` = 40.0 - % of volume to close at TP2
- `TP3_PERCENT` = 30.0 - % of volume to close at TP3
- `TP4_PERCENT` = 20.0 - % of volume to close at TP4
- `TP5_PERCENT` = 10.0 - % of volume to close at TP5 (closes ALL remaining)
- `BE_LEVEL` = 1 - TP level at which to move SL to breakeven (0=never)

**Stop Loss Management:**
- `ENABLE_CODE_STOP` = false - Close by code if price crosses SL (don't wait for broker)
- `SAFETY_FACTOR` = 1.5 - Emergency stop multiplier

**Price Correction:**
- `ENABLE_PRICE_CORRECTION` = true - Adjust prices using Yahoo Finance futures data
- `MAX_PRICE_DEVIATION` = 5.0 - Maximum allowed deviation %
- `MAX_TIMESTAMP_HOURS` = 4 - Maximum age of futures price data

**Debug Mode:**
- `DEBUG_MODE` = false - Use synthetic signals instead of API
- `DEBUG_USER_SIGNAL_ID` = 999 - Signal ID for debug mode
- `DEBUG_FIXED_VOLUME` = 0.1 - Fixed volume for debug trades

#### EA Reporting Callbacks

**ReportOpen (POST /api/signals/{id}/open):**
```json
{
    "success": true,
    "trade_id": "12345",
    "order_type": "ORDER_TYPE_BUY",
    "real_entry_price": 1.0850,
    "real_stop_loss": 1.0800,
    "real_volume": 0.10,
    "symbol": "EURUSD",
    "execution_time": "2025-10-03 14:30:00"
}
```

**ReportProgress (POST /api/signals/{id}/progress):**
```json
{
    "success": true,
    "current_level": 2,
    "volume_closed_percent": 40.0,
    "remaining_volume": 0.06,
    "gross_pnl": 125.50,
    "last_price": 1.0920,
    "message": "TP2 reached",
    "new_stop_loss": 1.0850,
    "symbol": "EURUSD",
    "execution_time": "2025-10-03 15:45:00"
}
```

**ReportClose (POST /api/signals/{id}/close):**
```json
{
    "success": true,
    "exit_level": 5,
    "close_reason": "CLOSED_COMPLETE",
    "gross_pnl": 248.75,
    "last_price": 1.0985,
    "symbol": "EURUSD",
    "execution_time": "2025-10-03 18:22:00"
}
```

**Exit Level Codes:**
- `1-5`: Closed at TP1-TP5
- `0`: Closed by Stop Loss
- `-1`: Safety stop triggered
- `-998`: Invalid TPs (missing TP data)
- `-999`: Execution/price correction error

**Close Reason Codes:**
- `CLOSED_COMPLETE`: All TPs reached
- `CLOSED_BY_SL`: Stop loss hit
- `CLOSED_CODE_STOP`: Code-based stop triggered
- `CLOSED_SAFETY_STOP`: Emergency safety stop
- `CLOSED_MANUAL`: Manual close detected in history
- `INVALID_TPS`: Missing or incomplete TP data
- `PRICE_CORRECTION_ERROR`: Yahoo Finance API failed
- `SPREAD_TOO_HIGH`: Spread exceeded MAX_SPREAD

#### EA State Management

**OptimizedTPState Structure:**
- Tracks single active position (EA handles one trade at a time)
- Stores: ticket, positionID, direction, volumes, TP levels, prices
- `levelFlags[6]`: Boolean array tracking which TPs have been hit
- `slMovedToBE`: Flag indicating breakeven activation

**Position Lifecycle:**
1. Signal received → ExecuteTrade() → Position opened
2. OnTick() monitors price → ManageTPs() checks each TP level
3. TP reached → Partial close → ReportProgress()
4. Breakeven triggered at configured BE_LEVEL
5. Final TP or SL hit → Full close → ReportClose() → InitTPState()

#### Price Correction Mechanism

**Flow:**
1. EA calls `/api/fut_price/{symbol}` (Yahoo Finance proxy)
2. Gets `last_close` price from futures market
3. Calculates `correctionFactor = futurePrice / cfdPrice`
4. Validates deviation is < MAX_PRICE_DEVIATION
5. Multiplies all TPs and SL by correctionFactor
6. Ensures timestamp is fresh (< MAX_TIMESTAMP_HOURS)

**Purpose:** Aligns CFD broker prices with real futures market to prevent slippage

#### Important EA Behaviors

**Signal Validation:**
- EA validates ALL TPs (TP1-TP5) must be > 0
- If any TP is missing/invalid → ReportClose(-998, "INVALID_TPS")
- This prevents partial signal execution

**Volume Management:**
- Volume calculated using risk management: `(Balance * RISK_PERCENT / 100) / StopLossDistance`
- Each TP closes its configured percentage of ORIGINAL volume
- TP5 always closes 100% of remaining volume regardless of TP5_PERCENT

**Breakeven Logic:**
- Activated when price reaches TP at BE_LEVEL
- Moves SL to entry price (zero-risk position)
- Reports new SL in ReportProgress()

**Historical Position Tracking:**
- If position disappears, EA checks history to determine WHY
- Distinguishes: SL hit, TP hit, manual close, unknown
- Prevents duplicate close reports

**Single Position Constraint:**
- EA only handles ONE active trade at a time (`currentTP.isActive` flag)
- New signals ignored while position is open
- This simplifies state management and prevents conflicts

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

### Database Schema Key Points

**Core Tables:**
- `telegram_signals` - Master signals from Telegram
- `user_signals` - Per-user signal instances (linked to `telegram_signals.id`)
- `trades` - Executed trades (BingX or MetaTrader)
- `user_tickers` - User ticker subscriptions with MT symbol mapping
- `strategies` - Trading strategies (can be BingX or MetaTrader platform)
- `api_keys` - User BingX API credentials

**Important Relationships:**
- Telegram signals are duplicated to multiple users via `user_signals` table
- Each `user_signal` tracks its own status independently
- `trades.platform` distinguishes BingX vs MetaTrader trades

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
- Environment switching: Call `BingxApi::set_environment('production'|'sandbox')` before API calls
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
