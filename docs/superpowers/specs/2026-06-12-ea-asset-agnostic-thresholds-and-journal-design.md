# EA_Signals — Asset-Agnostic Thresholds + Trade Journal — Design Spec

**Date:** 2026-06-12
**Status:** Approved (design phase)
**Scope:** `EA/EA_Signals.mq5` — replace fixed-"points" thresholds with risk/reward-anchored
ratios, add broker stop-level validation, and add structured logging + a CSV trade journal.
**Supersedes / absorbs:** `docs/EA_Signals_asset_agnostic_spread_tolerance.md` (the original
hand-off note). This spec refines its core idea and corrects the anchoring.

---

## 1. Problem & Goal

The EA makes three execution decisions in **points** (`SYMBOL_POINT`), and a "point" has a
different magnitude per symbol. The same number means different things per asset, forcing
per-chart reconfiguration and causing wrong behavior:

- **`MAX_SPREAD = 500`** (fixed points) — in EURUSD ≈ 50 pips (filter never fires); in
  indices/commodities it can reject valid signals. RTY was rejected `SPREAD_TOO_HIGH` today.
- **`PRICE_TOLERANCE_POINTS = 50`** (fixed points) — the market-vs-pending band. Effective
  ratio was ~0.82·target for EURUSD vs ~0.03 for gold. Wildly inconsistent.
- **`SetDeviationInPoints(50)`** (fixed points) — max slippage on market fills. Same defect.
- **No `STOPS_LEVEL` validation** — the broker's minimum stop distance is never checked →
  silent order rejections when SL is too close.

**Goal:** one set of global, dimensionless coefficients valid for forex, indices,
commodities and crypto-CFD, with **zero per-symbol config**, plus full observability
(human log + analyzable journal) to calibrate those coefficients with evidence.

---

## 2. Core principle (the anchoring)

There are two natural scales in every signal; they serve different functions and must NOT
be conflated (the original note anchored everything to risk; that is wrong for entry/cost):

- **`R = |entry − SL1|`** (risk distance) → **sizes the volume** (unchanged; already correct).
- **`T1 = |entry − TP1|`** (first-target distance) → **sizes the entry/cost gates**, because
  spread and entry slippage erode your **reward**, and the nearest reward is TP1.

Evidence (real signals, corrected/CFD prices): T1 is 0.24–0.72 of R, so a tolerance of
"10% of R" would be 14–42% of the distance to TP1 — far too loose. **The binding scale for
entry quality and cost is T1, not R.**

All prices used are the **Yahoo-corrected (CFD) prices**, since the correction is a ratio
applied to all levels and therefore preserves geometry (`R` and `T1` scale together).

---

## 3. The gates

### 3.1 Order type — market vs pending (anchored to T1)

```
T1   = |entry − TP1|
side = STOP  (favorable: market would fill BETTER than entry)
       LIMIT (adverse:   market would fill WORSE than entry / chasing)

tol  = (side == STOP) ? k_stop·T1 : k_limit·T1

if |entry − price| <= tol      → MARKET (BUY if LONG, SELL if SHORT)
else                           → pending, type by direction:
     LONG,  price < entry  → BUY_STOP   (breakout up to entry)
     LONG,  price > entry  → BUY_LIMIT  (pullback down to entry)
     SHORT, price > entry  → SELL_STOP  (breakdown down to entry)
     SHORT, price < entry  → SELL_LIMIT (rally up to entry)
```

- **STOP side = favorable price** (LONG below entry / SHORT above entry): market gives a
  better-than-entry fill → a wider band is acceptable → larger `k_stop`.
- **LIMIT side = adverse price** (chasing): keep the band tight → smaller `k_limit`.
- The direction logic (which pending) is unchanged from current code; only the band width
  changes from fixed points to `k·T1`.
- Pendings are placed at the **exact corrected entry**.

### 3.2 Spread gate — reject the whole signal (anchored to T1)

```
spread_real = SymbolInfoDouble(ASK) − SymbolInfoDouble(BID)   // real Bid/Ask, not SYMBOL_SPREAD*point
if spread_real > c·T1   → reject: SPREAD_TOO_HIGH (exit_level -999)
```

Meaning: the spread must not eat more than `c` (≈40%) of the distance to the first target.
This is a **reject** (applies to market AND pending), not a band.

### 3.3 Slippage — deviation cap on market fills (anchored to T1)

```
slippage_tol_price  = m·T1
deviation_points    = round(slippage_tol_price / SYMBOL_POINT)
trade.SetDeviationInPoints(deviation_points)   // per-trade, before sending a MARKET order
```

**Hard invariant:** `m < k_limit`. The slippage tolerance must always be the smallest entry
band — otherwise a market fill could land farther from entry than the threshold that chose
market in the first place. Anchoring to T1 (not to spread) guarantees this by construction
(same T1, smaller coefficient).

Only applies to MARKET orders. Pendings fill at the exact price (no slippage, save gaps).

### 3.4 Broker stop-level validation (new)

```
stops_min_price = SymbolInfoInteger(SYMBOL_TRADE_STOPS_LEVEL) * SYMBOL_POINT
sl_dist         = |entry − orderStopLoss|        // after the ENABLE_CODE_STOP multiplier
if sl_dist < stops_min_price   → reject: SL_TOO_CLOSE (exit_level -999)
```

The broker rejects orders whose SL is closer than `STOPS_LEVEL`. We validate up front and
reject cleanly with a logged reason instead of a silent broker failure. (TPs are managed by
code via partial closes, not as broker TP orders, so they are not constrained here.)

### 3.5 Coefficients (starting values, all configurable inputs)

| Input | Default | Anchor | Role | Invariant |
|---|---|---|---|---|
| `K_STOP_RATIO`   | 0.30 | T1 | market band, favorable side | — |
| `K_LIMIT_RATIO`  | 0.15 | T1 | market band, adverse side   | `> M_SLIP_RATIO` |
| `M_SLIP_RATIO`   | 0.05 | T1 | slippage / deviation cap    | `< K_LIMIT_RATIO` |
| `C_SPREAD_RATIO` | 0.40 | T1 | spread reject threshold     | — |

Ordering for the entry bands: `M_SLIP_RATIO (0.05) < K_LIMIT_RATIO (0.15) < K_STOP_RATIO
(0.30)`. The values are starting points; the journal (§5) calibrates them with evidence.

The old inputs `MAX_SPREAD`, `PRICE_TOLERANCE_POINTS`, `PRICE_TOLERANCE_PERCENT` and the
hardcoded `SetDeviationInPoints(50)` are removed/replaced.

### 3.6 Edge cases

1. `T1 == 0` (TP1 missing/equals entry) → already rejected by the existing TP validation;
   as a guard, fall back to `R` for the anchor.
2. `R == 0` → already rejected by the volume calc.
3. `spread_real <= 0` (broker reports 0 momentarily) → treat as "no data", do not reject on 0;
   use the last valid spread.
4. Spread/slippage on pendings: validated at signal time, but a pending fills later under a
   possibly different spread — accepted limitation (the real cost is paid at fill).

---

## 4. Logging — human (MT5)

The MT5 Experts log (auto-archived by MT5 to `MQL5/Logs/`) gets a per-signal gate block at
**INFO**, rejections at **ERROR**. Each gate prints **tolerated vs real** plus the % of T1.

```
[INFO]  [GATES]    #1234 GC SHORT | entry=4221.32 SL=4245.50 TP1=4204.00 | R=24.18 T1=17.31
[INFO]  [SPREAD]   real=1.20 | tol=6.93 (c=0.40·T1) | 6.9% T1 → OK
[INFO]  [ORDER]    price=4232.10 | dist=10.78 (62% T1) | side=ADVERSE | k_limit=2.60 (0.15·T1) → SELL_LIMIT @ 4221.32
[INFO]  [SLIPPAGE] tol=0.87 (m=0.05·T1)            // only for MARKET; real logged post-fill
[INFO]  [STOPS]    broker_min=1.20 | sl_dist=24.18 → OK
```

No separate human `.txt` file — MT5 already files the Experts output.

---

## 5. Logging — CSV journal (analysis) + live view

Two CSV files in `MQL5/Files/`, **per EA instance** (avoids write-contention corruption when
multiple EAs run); merge by glob for cross-asset analysis. **Both share the exact same column
set**, so a live row "graduates" into a journal row at close.

### 5.1 `bxlite_journal_{USER_ID}_{TICKER}.csv` — the dataset
- **Append-only.** One finalized row per **completed trade**. One row per **rejected signal**
  (written immediately on reject).
- Header written once on file creation.
- Row finalized at **close**, accumulating values in the state store during the trade.

### 5.2 `bxlite_live_{USER_ID}_{TICKER}.csv` — the live view
- **Header + exactly one row**, **overwritten on every state change** (open, each TP, BE,
  close) — the same trigger points as `SaveState()`. Cheap (2 lines).
- Watch/tail this to see `current_level`, `vol_closed%`, `gross_pnl`, `last_price` update live.
- On close, the row is cleared (or shows "no active trade") and the finalized row lands in the
  journal.

### 5.3 Columns (one wide row = the whole trade DNA)

Grouped (analyzer-friendly: fixed header, ISO-8601 timestamps, dot decimals forced regardless
of VPS locale, no thousands separators):

```
IDENTITY:    ts_signal, ts_close, signal_id, symbol, broker_symbol, dir, duration_min
CORRECTION:  corr_on, future_price, cfd_price, corr_factor, corr_dev_pct, entry_raw, entry_corr, sl_raw, sl_corr
GEOMETRY:    entry, sl, tp1, tp2, tp3, tp4, tp5, R, T1
SPREAD:      spread_real, spread_tol, spread_pct_t1, spread_result
DECISION:    price_signal, dist_entry, dist_pct_t1, side, k_coef, k_band, order_type
EXECUTION:   price_requested, real_entry, slip_real, slip_tol, real_volume
STOPS:       stops_min, sl_dist, stops_result
PROGRESSION: tp1_hit, tp2_hit, tp3_hit, tp4_hit, tp5_hit, be_on, be_price, max_level, vol_closed_pct
OUTCOME:     exit_level, close_reason, gross_pnl, last_price, result
```

To keep the row ~40 cols (not 70): store `corr_factor` + raw/corrected entry & SL only (any
level reconstructs from the factor); do not duplicate raw+corrected for all five TPs.

`result` values: `OK`, `REJECTED_SPREAD`, `REJECTED_SL_TOO_CLOSE`, `REJECTED_*`,
`CLOSED_TP`, `CLOSED_SL`, `CLOSED_MANUAL`, etc. — single column to filter on.

---

## 6. Phasing

**Phase A — behavior (the refactor):**
- Replace the three fixed-point thresholds with the T1-anchored gates (§3.1–3.3).
- Add `STOPS_LEVEL` validation (§3.4).
- New configurable inputs (§3.5); remove the old ones.
- Pass `entry`/`SL`/`TP1` (or `R`,`T1`) into `ValidateSpread()` and `DetermineOrderType()`;
  compute `R`/`T1` in `ExecuteTrade()` **before** those calls (today `slDistance` is computed
  after).
- Add the MT5 gate logging (§4).

**Phase B — instrumentation (the journal):**
- Add the journal + live CSV writers (§5), wired to the existing state-store mutation points.
- Capture per-gate tolerated/real values into the state store so the close-time row is complete.

Phase A is independently shippable and testable. Phase B depends on A (it logs A's values).

---

## 7. Calibration plan

The defaults (`K_STOP=0.30`, `K_LIMIT=0.15`, `M_SLIP=0.05`, `C_SPREAD=0.40`) are starting
estimates. After ~20–30 real signals, import the journal into an analyzer (Excel/pandas; later
possibly a view in the bingx_lite web app) and inspect the distributions of `spread_pct_t1`,
`dist_pct_t1`, `slip_real/T1`, and outcomes per condition. Tune the four coefficients with
evidence. Capturing the next `RTY` rejection is a priority (calibrates `C_SPREAD`).

---

## 8. Touch points (current code)

- `ValidateSpread()` (~`EA_Signals.mq5:1267`) — replace points compare with `c·T1`; use real Bid/Ask.
- `DetermineOrderType()` (~`:1289`) — replace `PRICE_TOLERANCE_*` with `k_stop/k_limit · T1` by side.
- `ExecuteTrade()` — compute `R`,`T1` before the spread/order-type calls; per-trade
  `SetDeviationInPoints(m·T1/point)`; add `STOPS_LEVEL` validation before sending.
- Inputs block — swap the old inputs for the four ratios.
- New section: CSV journal/live writers (Phase B), wired to `SetupTPState` / `ClosePartialPosition`
  / `SetBreakeven` / `CheckAndExecuteTP` / close sites (same hooks as `SaveState()`).

## 9. Out of scope
- Multi-ticker single EA (separate, larger project).
- Web-app analyzer view (future; the CSV is the interface).
- Changes to volume sizing, price correction, TP percentages, BE logic (unchanged).
