# Unified Journals + Trade Detail Implementation Plan

> **For agentic workers:** Executed inline in this session, committing to `main` after each task. No automated test harness exists in this CodeIgniter 3 project; verification is `php -l` lint per file + reasoned review (runtime needs MySQL + EA data, both unavailable at authoring time).

**Goal:** Unify all per-trade analysis under `/journals` — drill-down overview → symbol → trade detail (styled like the approved mock) — reading from the new `ea_trade_*` tables with graceful fallback to existing `user_telegram_signals` JSON for historical trades. Deprecate `my_trading/trading_detail`. Remove the event-log duplication.

**Architecture:** Journals becomes the DB-backed analytics area. Base record per trade = `user_telegram_signals` (always populated) LEFT JOIN `ea_trade_snapshots` / `ea_price_corrections` (rich data when the EA has reported it). The trade-detail view prefers snapshot/correction/events; falls back to `execution_data` / `mt_corrected_data` / `event_log` blobs for historical signals. One shared view feeds both the admin journals route and the owner `my_trading` route (DRY). `append_event` writes to `ea_trade_events` as the single source of truth, keeping `event_log` only as a pre-migration fallback. Existing `Journal_stats` aggregation is reused by feeding it DB rows as arrays.

**Tech Stack:** CodeIgniter 3 (PHP), MySQL/MariaDB, Bootstrap 5, Font Awesome. No build step.

---

## File Structure

- `database/migrations/2026-06-16-ea-trade-snapshots.sql` — add 3 columns to `ea_trade_events` (order_type, volume, close_reason) so the timeline is self-sufficient.
- `application/models/Telegram_signals_model.php` — dedup `append_event`; map new event columns in `record_event`; add reader/aggregation methods.
- `application/config/routes.php` — journals drill-down routes (specific before generic).
- `application/controllers/Journals.php` — DB-backed `index()` (overview) + `symbol()` (trade list) + new `trade()` (detail).
- `application/controllers/My_trading.php` — `trading_detail()` repointed to the shared detail view (owner-scoped).
- `application/views/journals/overview.php` — reused; data now from DB (same KPI array shape).
- `application/views/journals/detail.php` — symbol page now lists trades from DB with links to detail.
- `application/views/journals/trade_detail.php` — NEW, the mock, shared by both controllers.
- `application/views/my_trading/trading_detail.php` — DELETED (deprecated).

---

## Task 1: Migration — self-sufficient event rows

**Files:** Modify `database/migrations/2026-06-16-ea-trade-snapshots.sql`

- [ ] Add `order_type VARCHAR(20)`, `volume DECIMAL(12,2)`, `close_reason VARCHAR(40)` after `message` in `ea_trade_events`.
- [ ] Commit.

## Task 2: Model refactor

**Files:** Modify `application/models/Telegram_signals_model.php`

- [ ] `append_event`: write to `ea_trade_events` via `record_event` when `ea_tables_ready()`, else legacy `append_event_log` (no dual write).
- [ ] `record_event`: map `order_type`, `volume`, `close_reason` from `$extra`.
- [ ] Add readers: `get_trade_snapshot($id)`, `get_trade_correction($id)`, `get_timeline_events($signal)` (table preferred, `event_log` fallback).
- [ ] Add aggregation: `journal_symbols()` (per-symbol KPI rows via `Journal_stats`), `journal_trades_for_symbol($sym)`, `journal_trade_detail($id)` (signal row + snapshot + correction).
- [ ] `php -l` clean. Commit.

## Task 3: Routes

**Files:** Modify `application/config/routes.php`

- [ ] Add `journals/symbol/(:any)/(:num)` → `Journals/trade/$1/$2` BEFORE `journals/symbol/(:any)`.
- [ ] Commit.

## Task 4: Journals controller (DB-backed)

**Files:** Modify `application/controllers/Journals.php`

- [ ] `index()`: symbols + global aggregates from `journal_symbols()` (DB), keep chart arrays.
- [ ] `symbol($sym)`: trade list from `journal_trades_for_symbol()` + KPI.
- [ ] `trade($sym, $id)`: assemble detail (snapshot/correction/events + blob fallback) → `journals/trade_detail`.
- [ ] `php -l` clean. Commit.

## Task 5: Views

**Files:** Modify `journals/overview.php`, `journals/detail.php`; Create `journals/trade_detail.php`

- [ ] `overview.php`: links/labels still valid against DB KPI arrays.
- [ ] `detail.php`: symbol page renders trade list table, each row links to `journals/symbol/{sym}/{id}`.
- [ ] `trade_detail.php`: mock layout — header, Señal→Corregido→Ejecutado, ¿por qué order type?, volumen+gates, correction card (futuro/CFD/vela + alineación), cronología from events. Fallbacks when snapshot/correction absent.
- [ ] Commit.

## Task 6: Deprecate trading_detail

**Files:** Modify `application/controllers/My_trading.php`; Delete `application/views/my_trading/trading_detail.php`

- [ ] `My_trading::trading_detail($id)`: owner-scoped, assemble via `journal_trade_detail`, render shared `journals/trade_detail`.
- [ ] Delete old view.
- [ ] `php -l` clean. Commit.

## Task 7: Final

- [ ] `php -l` all touched PHP. Update CLAUDE.md note if needed. Commit.

---

## Notes / decisions

- Journals is admin-only; `my_trading` owner route reuses the same view scoped to the session user, so non-admin owners keep access.
- Trade detail is keyed by `user_signal_id` (globally unique); `{sym}` in the URL is cosmetic (breadcrumb).
- Journal listing includes signals the EA acted on: status IN (`open`,`closed`,`failed_execution`,`cancelled`,`pending`).
- CSV `Journal_reader`/`Journal_stats` kept; `Journal_stats` reused on DB rows. CSV files remain the EA's local artifact, no longer the web source.
