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
- EA polls `/api/signals/{user_id}/{ticker_symbol}` for new signals
- System returns oldest `available` signal, marks as `claimed`
- EA reports trade progress back via `/api/signals/{id}/open|progress|close`

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
