# Journal Viewer — Design Spec

**Date:** 2026-06-15
**Status:** Approved (design), pending implementation plan
**Component:** New admin tool inside the CodeIgniter 3 app (`bingx_lite` / `bx-trade`)

## Purpose

The EA `EA/EA_Signals.mq5` writes a CSV trade journal + live row + JSON state per
symbol to the MT5 `MQL5/Files` folder. Today these are only inspectable by opening
the raw files. This tool renders them in the web app: per-symbol tables, aggregate
statistics, and charts — primarily to **analyze trade behavior and calibrate the
asset-agnostic gates** (`K_STOP_RATIO`, `K_LIMIT_RATIO`, etc.) with real evidence
instead of intuition.

Read-only. No writing, deleting, or mutating of any journal/state file.

## Data source

The files live on the **same Debian server** as the PHP app, written by MT5 under
Wine at `~/.mt5/drive_c/Program Files/MetaTrader 5/MQL5/Files/`. The viewer reads the
filesystem directly (PHP-FPM as `www-data`). nginx is **not** the mechanism (an
`alias` would only serve raw downloads, not render).

- **Path**: a single configurable constant `JOURNALS_PATH` (see Config). A symlink
  from the app into the Wine folder is an optional convenience; not required.
- **Permissions (ops, out of code scope but documented)**: `www-data` needs
  read + traverse on the path. Recommended via ACL (read-only, no chmod of the home):
  ```bash
  setfacl -m  u:www-data:--x /home/<user>
  setfacl -R  -m u:www-data:r-X "/home/<user>/.mt5/drive_c/Program Files/MetaTrader 5/MQL5/Files"
  setfacl -R -d -m u:www-data:r-X "/home/<user>/.mt5/drive_c/Program Files/MetaTrader 5/MQL5/Files"
  ```
  The `-d` (default ACL) makes **new** journal files inherit the read grant.

## File formats (input)

Three file kinds, named `bxlite_<kind>_<USER_ID>_<SYMBOL>.<ext>`:

- `bxlite_journal_<uid>_<sym>.csv` — **dataset**: header + one row per **closed or
  rejected** trade (append-only).
- `bxlite_live_<uid>_<sym>.csv` — header + the **current** trade row (overwritten on
  each state change); `result` column is `OPEN` while a trade is live, or the close
  reason after it ends (file reset to header-only between trades).
- `bxlite_state_<uid>_<sym>.json` — one JSON object: persisted `OptimizedTPState`
  (ticket, positionID, direction, volumes, currentLevel, levelFlags, levelVolumes,
  slMovedToBE, entry/SL/TP prices).

**Journal CSV columns** (current header, order may evolve — parse by header name):
`ts_signal, signal_id, symbol, dir, corr_on, corr_factor, entry_raw, sl_raw, entry,
sl, tp1..tp5, R, T1, spread_real, spread_tol, price_signal, dist_entry, side, k_band,
order_type, real_entry, slip_real, slip_tol, real_volume, stops_min, sl_dist,
max_level, vol_closed_pct, be_on, exit_level, close_reason, gross_pnl, last_price,
result`.

### Coded column semantics

- `signal_id` — global backend `user_signal.id` (one sequence across all symbols).
- `max_level` — max TP reached: `-2` never opened (cancelled pending), `0` opened/no
  TP, `1..5` reached TP1..TP5.
- `exit_level` — `1..5` closed at TPn; `-1` stop (`EXIT_STOP`); `-998` invalid signal
  (`EXIT_INVALID`); `-999` error/gate/cancel (`EXIT_ERROR`); `0` while live.
- `side` — `FAVORABLE` (→ STOP) / `ADVERSO` (→ LIMIT).
- `order_type` — `STOP` / `LIMIT` / `MARKET` / `MARKET_FB` (v10.20 fallback).
- `be_on` — `slMovedToBE` (0/1).
- `close_reason` / `result` — exact strings from the EA (see CLAUDE.md). `result`
  mirrors `close_reason`, or `OPEN` in live files.

## Architecture (B — overview + per-symbol detail)

Small single-purpose units:

- **`application/controllers/Journals.php`** — admin-guarded (constructor: redirect to
  `auth` unless `logged_in` and `role == 'admin'`). Methods:
  - `index()` — overview; optional `from`/`to` date filter via GET.
  - `symbol($sym)` — per-symbol detail.
  Orchestration only: calls the libraries, passes data to views.
- **`application/libraries/Journal_reader.php`** — *files → arrays*. Discovers files
  via `glob` under `JOURNALS_PATH`, parses filename → `user_id` + `symbol`, parses CSV
  mapping each row to an assoc array keyed by header name (numeric casting), parses
  state JSON. Knows nothing about stats or HTML.
- **`application/libraries/Journal_stats.php`** — *arrays → aggregates*. Computes
  per-symbol and global KPIs, distributions, cumulative-PnL series. Decoupled from the
  reader → unit-testable in isolation.
- **`application/views/journals/overview.php`**, **`.../detail.php`** — use
  `templates/header` + `templates/footer`; Chart.js via CDN included only here.
- **`application/config/routes.php`** — `journals` → `Journals/index`;
  `journals/symbol/(:any)` → `Journals/symbol/$1`.
- **`application/config/constants.php`** — `define('JOURNALS_PATH', ...)`, default to
  the dev path (`APPPATH.'../EA/journals/'`), overridden per environment to the Wine
  `MQL5/Files` path in prod.
- **`application/views/templates/header.php`** — add a "Journals" link under the
  admin **System** dropdown.

## Data flow

1. Controller method runs → instantiates `Journal_reader`, lists files under
   `JOURNALS_PATH`.
2. Reader parses journal/live/state into PHP arrays.
3. `Journal_stats` consumes the arrays → KPIs + distributions + time series.
4. Controller passes structured data + chart datasets (as JSON) to the view.
5. View renders Bootstrap tables/cards server-side; Chart.js draws the datasets
   client-side. No AJAX.

## KPI definitions (explicit, to avoid ambiguity)

- **Operated vs not operated**: `ORDER_CANCELLED` and any failure reason
  (`INVALID_*`, `*_ERROR`, `SPREAD_TOO_HIGH`, `VOLUME_ERROR`, `SL_TOO_CLOSE`,
  `EXECUTION_FAILED`, `PRICE_CORRECTION_ERROR`) = **not operated**; counted
  separately, excluded from win/loss.
- **Win / Loss**: only real closes (non-failure `close_reason`). Win = `gross_pnl > 0`;
  Loss = `gross_pnl <= 0` (includes `CLOSED_STOPLOSS` and a breakeven that closed
  slightly negative from spread).
- **Win rate** = wins / operated trades (`—` if 0 operated).
- **% cancelled** = cancelled / total signal rows.
- **Per symbol**: # signals, # operated, # cancelled, win, loss, win rate, total &
  avg PnL, avg `dist_entry`, avg `T1`, `dist_entry/T1` ratio, `order_type` and
  `exit_level` distributions.
- **Global**: cross-symbol sums + comparison table.
- **Time series**: cumulative PnL ordered by `ts_signal`.
- **Current state** (detail): merge `state.json` + `live.csv` → live trade (direction,
  ticket, currentLevel, `levelVolumes` ladder over TP1..5, SL/BE, live PnL).

## UI

### Overview (`/journals`)
- Date-range filter (GET) at the top.
- Row of global KPI cards (profit/loss colored via existing `text-profit`/`text-loss`).
- Per-symbol comparison table (sortable): symbol (links to detail), # signals,
  operated, cancelled, win/loss, win rate, PnL, avg `dist_entry`/`T1`, most frequent
  `order_type` & `exit_level`.
- Charts: PnL by symbol (bar), `order_type` distribution (pie), `exit_level`
  distribution (bar), global cumulative PnL over time (line).

### Detail (`/journals/symbol/<SYM>`)
- Symbol KPI cards.
- **Current-state card**: live trade — direction, ticket, level, TP1–5 ladder with
  `levelVolumes`, SL/BE, live PnL; "sin posición" if none.
- Charts: cumulative PnL, **`dist_entry` vs `T1` scatter colored by `order_type`**
  (the key calibration view), `exit_level` histogram, win/loss by `order_type`.
- Full journal table: all columns, sortable, rows colored (profit/loss/cancelled),
  tooltips explaining the coded columns. Optional `order_type`/`exit_level` filters.

## Error handling & edge cases

- `JOURNALS_PATH` missing/unreadable → clear non-fatal view message with the path.
- No files → empty state ("no hay journals todavía").
- Malformed CSV / short row → skip the row, continue (log how many skipped); missing
  header → ignore that file with a notice.
- Invalid state JSON → state panel shows "no parseable"; rest still works.
- Zero trades → guarded KPIs (win rate `—`).
- Unknown symbol in `/journals/symbol/X` → friendly 404 / redirect to overview.
- **Security**: `$sym` from the URL is whitelisted to `[A-Z0-9]` and used only to
  build the filename — no path traversal. Entire tool is read-only.

## Testing

- **`Journal_reader`**: fixtures from the real `EA/journals/` samples → correct
  parsing, header mapping, short-row / missing-header handling, state JSON parsing.
- **`Journal_stats`**: known row set → verify win rate, % cancelled, total PnL,
  distributions, cumulative series, and zero-trade edge.
- Runner: simple standalone PHP test scripts under `tests/` building fixtures and
  asserting (no PHPUnit unless desired). The libraries are pure
  (files → arrays → stats), easy to test in isolation.

## Out of scope (YAGNI)

- No write/delete/reset of journal or state files.
- No live polling / websockets; page is a server-rendered snapshot (refresh to update).
- No DB persistence of journal data (read straight from files).
- No nginx static-download route for raw CSVs (could be added later).
- No multi-user separation beyond the existing admin gate.

## USER_ID handling (disambiguation)

`USER_ID` is part of the filename and is effectively always `1` in this deployment.
The viewer groups files **by symbol**, merging rows across any `USER_ID`s present, and
keeps `user_id` as a per-row field (shown as a column in the detail table and usable
as a future filter). The detail route `/journals/symbol/<SYM>` therefore covers all
`USER_ID`s for that symbol. No per-user routing in v1.
