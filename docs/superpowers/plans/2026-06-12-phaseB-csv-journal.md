# Phase B — CSV Trade Journal + Live View

> **For agentic workers:** REQUIRED SUB-SKILL: superpowers:executing-plans (inline). Steps use checkbox (`- [ ]`).

**Goal:** Write a per-trade CSV journal (append-only dataset, one row per completed trade and per rejected signal) plus a live CSV (header + one row, overwritten on every state change) capturing the full trade DNA: correction, gates (tolerated vs real), decision, execution, progression, outcome.

**Architecture:** A single global `JournalRecord g_journal` accumulates the trade's values as they are computed in `ProcessSignalResponse` / `ExecuteTrade` / `ValidateSpread` / `DetermineOrderType`. Progression/outcome are read live from `currentTP` at write time. Three hooks reuse Plan 1's mutation points: `JournalWriteLive()` inside `SaveState()` and `ClearState()`; `JournalAppendClosed()` inside `ReportClose()`. Additive only — no behavior change.

**Tech Stack:** MQL5, single file `EA/EA_Signals.mq5`. Files written to `MQL5/Files/`.

**Verification:** compile (F7) after each task; demo check at the end. No unit framework (platform limitation).

---

## Columns (38, fixed order — same for journal and live)

```
ts_signal,signal_id,symbol,dir,corr_on,corr_factor,entry_raw,sl_raw,
entry,sl,tp1,tp2,tp3,tp4,tp5,R,T1,spread_real,spread_tol,
price_signal,dist_entry,side,k_band,order_type,
real_entry,slip_real,slip_tol,real_volume,stops_min,sl_dist,
max_level,vol_closed_pct,be_on,exit_level,close_reason,gross_pnl,last_price,result
```

ISO-8601 `ts_signal`; dot decimals; `result` = close_reason (or `OPEN` in the live row). Correction columns store only factor + raw/corrected entry & SL (any level reconstructs from the factor).

---

## Task 1: Journal infrastructure (struct, reset, header, row builder, writers)

**Files:** Modify `EA/EA_Signals.mq5`

- [ ] **Step 1: Declare the struct + global, next to the other structs**

After the `OptimizedTPState { ... };` struct definition, add:
```mql5
struct JournalRecord {
    string  ts_signal;
    int     signal_id;
    string  dir;
    bool    corr_on;
    double  corr_factor, entry_raw, sl_raw;
    double  entry, sl, tp1, tp2, tp3, tp4, tp5, rdist, t1;
    double  spread_real, spread_tol;
    double  price_signal, dist_entry;
    string  side;
    double  k_band;
    string  order_type;
    double  real_entry, slip_real, slip_tol, real_volume;
    double  stops_min, sl_dist;
};
```

Next to `OptimizedTPState currentTP;` (globals), add:
```mql5
JournalRecord currentJournal;
```

- [ ] **Step 2: Add the journal functions after the STATE STORE section (before `AdoptStateOnInit` or right after `ClearState`)**

```mql5
// ==========================================
// CSV JOURNAL (dataset + live)
// ==========================================
string JournalIsoTime() {
    MqlDateTime t; TimeGMT(t);
    return StringFormat("%04d-%02d-%02dT%02d:%02d:%02d", t.year, t.mon, t.day, t.hour, t.min, t.sec);
}

string JournalFilePath() { return "bxlite_journal_" + IntegerToString(USER_ID) + "_" + TICKER_SYMBOL + ".csv"; }
string JournalLivePath() { return "bxlite_live_"    + IntegerToString(USER_ID) + "_" + TICKER_SYMBOL + ".csv"; }

void JournalReset() {
    currentJournal.ts_signal = "";  currentJournal.signal_id = 0;  currentJournal.dir = "";
    currentJournal.corr_on = false; currentJournal.corr_factor = 1.0;
    currentJournal.entry_raw = 0;   currentJournal.sl_raw = 0;
    currentJournal.entry = 0; currentJournal.sl = 0;
    currentJournal.tp1 = 0; currentJournal.tp2 = 0; currentJournal.tp3 = 0; currentJournal.tp4 = 0; currentJournal.tp5 = 0;
    currentJournal.rdist = 0; currentJournal.t1 = 0;
    currentJournal.spread_real = 0; currentJournal.spread_tol = 0;
    currentJournal.price_signal = 0; currentJournal.dist_entry = 0;
    currentJournal.side = ""; currentJournal.k_band = 0; currentJournal.order_type = "";
    currentJournal.real_entry = 0; currentJournal.slip_real = 0; currentJournal.slip_tol = 0; currentJournal.real_volume = 0;
    currentJournal.stops_min = 0; currentJournal.sl_dist = 0;
}

string JournalHeader() {
    return "ts_signal,signal_id,symbol,dir,corr_on,corr_factor,entry_raw,sl_raw,"
         + "entry,sl,tp1,tp2,tp3,tp4,tp5,R,T1,spread_real,spread_tol,"
         + "price_signal,dist_entry,side,k_band,order_type,"
         + "real_entry,slip_real,slip_tol,real_volume,stops_min,sl_dist,"
         + "max_level,vol_closed_pct,be_on,exit_level,close_reason,gross_pnl,last_price,result";
}

string JournalRow(int exitLevel, string closeReason, double grossPnl, double lastPrice, string result) {
    string r = "";
    r += currentJournal.ts_signal + ",";
    r += IntegerToString(currentJournal.signal_id) + ",";
    r += TICKER_SYMBOL + ",";
    r += currentJournal.dir + ",";
    r += (currentJournal.corr_on ? "1" : "0") + ",";
    r += DoubleToString(currentJournal.corr_factor, 6) + ",";
    r += DoubleToString(currentJournal.entry_raw, 5) + ",";
    r += DoubleToString(currentJournal.sl_raw, 5) + ",";
    r += DoubleToString(currentJournal.entry, 5) + ",";
    r += DoubleToString(currentJournal.sl, 5) + ",";
    r += DoubleToString(currentJournal.tp1, 5) + ",";
    r += DoubleToString(currentJournal.tp2, 5) + ",";
    r += DoubleToString(currentJournal.tp3, 5) + ",";
    r += DoubleToString(currentJournal.tp4, 5) + ",";
    r += DoubleToString(currentJournal.tp5, 5) + ",";
    r += DoubleToString(currentJournal.rdist, 5) + ",";
    r += DoubleToString(currentJournal.t1, 5) + ",";
    r += DoubleToString(currentJournal.spread_real, 5) + ",";
    r += DoubleToString(currentJournal.spread_tol, 5) + ",";
    r += DoubleToString(currentJournal.price_signal, 5) + ",";
    r += DoubleToString(currentJournal.dist_entry, 5) + ",";
    r += currentJournal.side + ",";
    r += DoubleToString(currentJournal.k_band, 5) + ",";
    r += currentJournal.order_type + ",";
    r += DoubleToString(currentJournal.real_entry, 5) + ",";
    r += DoubleToString(currentJournal.slip_real, 5) + ",";
    r += DoubleToString(currentJournal.slip_tol, 5) + ",";
    r += DoubleToString(currentJournal.real_volume, 2) + ",";
    r += DoubleToString(currentJournal.stops_min, 5) + ",";
    r += DoubleToString(currentJournal.sl_dist, 5) + ",";
    r += IntegerToString(currentTP.currentLevel) + ",";
    r += DoubleToString(currentTP.closedPercent, 2) + ",";
    r += (currentTP.slMovedToBE ? "1" : "0") + ",";
    r += IntegerToString(exitLevel) + ",";
    r += closeReason + ",";
    r += DoubleToString(grossPnl, 2) + ",";
    r += DoubleToString(lastPrice, 5) + ",";
    r += result;
    return r;
}

// Sobrescribe el archivo live: header + 1 fila (si hay trade activo/pendiente).
void JournalWriteLive() {
    int h = FileOpen(JournalLivePath(), FILE_WRITE|FILE_TXT|FILE_ANSI);
    if(h == INVALID_HANDLE) return;
    FileWriteString(h, JournalHeader() + "\r\n");
    if(currentTP.isActive || currentTP.ticket > 0) {
        FileWriteString(h, JournalRow(currentTP.currentLevel, "OPEN", 0.0, 0.0, "OPEN") + "\r\n");
    }
    FileClose(h);
}

// Append de una fila final (cierre o rechazo) al journal.
void JournalAppendClosed(int exitLevel, string closeReason, double grossPnl, double lastPrice) {
    bool existed = FileIsExist(JournalFilePath());
    int h;
    if(existed) {
        h = FileOpen(JournalFilePath(), FILE_READ|FILE_WRITE|FILE_TXT|FILE_ANSI);
        if(h == INVALID_HANDLE) return;
        FileSeek(h, 0, SEEK_END);
    } else {
        h = FileOpen(JournalFilePath(), FILE_WRITE|FILE_TXT|FILE_ANSI);
        if(h == INVALID_HANDLE) return;
        FileWriteString(h, JournalHeader() + "\r\n");
    }
    FileWriteString(h, JournalRow(exitLevel, closeReason, grossPnl, lastPrice, closeReason) + "\r\n");
    FileClose(h);
}
```

- [ ] **Step 3: Compile (F7). Expected `0 errors`. Commit.**
```bash
git add EA/EA_Signals.mq5
git commit -m "feat(EA_Signals): journal infra (struct, row builder, live/append writers)"
```

---

## Task 2: Reset + identity capture in ProcessSignalResponse

**Files:** Modify `EA/EA_Signals.mq5` (`ProcessSignalResponse`)

- [ ] **Step 1: After parsing op_type + geometry (before the SL/TP reject checks), reset and stamp identity**

Find:
```mql5
    Log(INFO_LVL, "SIGNAL", StringFormat("Nueva señal: ID=%d, %s, Entry=%.5f, SL1=%.5f, SL2=%.5f, TPs=[%.5f,%.5f,%.5f,%.5f,%.5f]",
        userSignalId, opType, entry, sl1, sl2, tp1, tp2, tp3, tp4, tp5));
```
Insert immediately AFTER it:
```mql5

    JournalReset();
    currentJournal.ts_signal = JournalIsoTime();
    currentJournal.signal_id = userSignalId;
    currentJournal.dir       = opType;
```

- [ ] **Step 2: Compile (F7) + commit.**
```bash
git add EA/EA_Signals.mq5
git commit -m "feat(EA_Signals): reset journal + capture identity per signal"
```

---

## Task 3: Populate gate/execution values

**Files:** Modify `EA/EA_Signals.mq5` (`ExecuteTrade`, `ValidateSpread`, `DetermineOrderType`)

- [ ] **Step 1: Correction + geometry, in ExecuteTrade right after the [GATES] log**

Find:
```mql5
    Log(INFO_LVL, "GATES", StringFormat("#%d %s %s | entry=%.5f SL=%.5f TP1=%.5f | R=%.5f T1=%.5f",
        userSignalId, TICKER_SYMBOL, opType, entryPrice, stopLoss, tp1, MathAbs(entryPrice - stopLoss), t1));
```
Insert AFTER it:
```mql5

    currentJournal.corr_on    = (correctionFactor != 1.0);
    currentJournal.corr_factor= correctionFactor;
    currentJournal.entry_raw  = originalEntry;
    currentJournal.sl_raw     = originalSL1;
    currentJournal.entry      = entryPrice;
    currentJournal.sl         = stopLoss;
    currentJournal.tp1 = tp1; currentJournal.tp2 = tp2; currentJournal.tp3 = tp3; currentJournal.tp4 = tp4; currentJournal.tp5 = tp5;
    currentJournal.rdist      = MathAbs(entryPrice - stopLoss);
    currentJournal.t1         = t1;
```

- [ ] **Step 2: Spread values, inside ValidateSpread (just before the final `return true;`)**

Find (the OK path log line in ValidateSpread):
```mql5
    Log(INFO_LVL, "SPREAD", StringFormat("real=%.5f | tol=%.5f (c=%.2f*T1) | %.1f%% T1 -> OK",
        spreadReal, spreadTol, C_SPREAD_RATIO, pctT1));
    return true;
```
Replace with:
```mql5
    Log(INFO_LVL, "SPREAD", StringFormat("real=%.5f | tol=%.5f (c=%.2f*T1) | %.1f%% T1 -> OK",
        spreadReal, spreadTol, C_SPREAD_RATIO, pctT1));
    currentJournal.spread_real = spreadReal;
    currentJournal.spread_tol  = spreadTol;
    return true;
```

- [ ] **Step 3: Decision values, inside DetermineOrderType (before its `return orderType;`)**

Find:
```mql5
    Log(INFO_LVL, "ORDER", StringFormat("price=%.5f | dist=%.5f (%.1f%% T1) | side=%s | k=%.2f tol=%.5f -> %s",
        currentPrice, diff, pctT1, side, k, tol, orderDecision));

    return orderType;
```
Replace with:
```mql5
    Log(INFO_LVL, "ORDER", StringFormat("price=%.5f | dist=%.5f (%.1f%% T1) | side=%s | k=%.2f tol=%.5f -> %s",
        currentPrice, diff, pctT1, side, k, tol, orderDecision));

    currentJournal.price_signal = currentPrice;
    currentJournal.dist_entry   = diff;
    currentJournal.side         = side;
    currentJournal.k_band       = tol;
    currentJournal.order_type   = orderDecision;

    return orderType;
```

- [ ] **Step 4: Stops + slippage tol, in ExecuteTrade**

Find:
```mql5
    Log(INFO_LVL, "STOPS", StringFormat("broker_min=%.5f | sl_dist=%.5f -> OK", stopsMin, slDistFinal));
```
Insert AFTER it:
```mql5
    currentJournal.stops_min = stopsMin;
    currentJournal.sl_dist   = slDistFinal;
```

Find:
```mql5
        trade.SetDeviationInPoints(devPoints);
        Log(INFO_LVL, "SLIPPAGE", StringFormat("tol=%.5f (m=%.2f*T1) = %d points", slipTol, M_SLIP_RATIO, devPoints));
```
Insert AFTER it:
```mql5
        currentJournal.slip_tol = slipTol;
```

- [ ] **Step 5: Real entry / slip / volume, in the success branch of ExecuteTrade**

Find:
```mql5
            double slipReal = MathAbs(realEntryPrice - currentPrice);
            Log(INFO_LVL, "SLIPPAGE", StringFormat("real=%.5f | pedido=%.5f fill=%.5f", slipReal, currentPrice, realEntryPrice));
            entryPrice = realEntryPrice;
        }
```
Replace with:
```mql5
            double slipReal = MathAbs(realEntryPrice - currentPrice);
            Log(INFO_LVL, "SLIPPAGE", StringFormat("real=%.5f | pedido=%.5f fill=%.5f", slipReal, currentPrice, realEntryPrice));
            currentJournal.slip_real = slipReal;
            entryPrice = realEntryPrice;
        }
```

Find:
```mql5
        SetupTPState(userSignalId, opType, entryPrice, stopLoss, calculatedVolume,
                     tp1, tp2, tp3, tp4, tp5, ticket, isMarketOrder, positionID);
```
Insert BEFORE it:
```mql5
        currentJournal.real_entry  = entryPrice;
        currentJournal.real_volume = calculatedVolume;

```

- [ ] **Step 6: Compile (F7) + commit.**
```bash
git add EA/EA_Signals.mq5
git commit -m "feat(EA_Signals): populate journal record across gates/execution"
```

---

## Task 4: Wire the writers into existing hooks

**Files:** Modify `EA/EA_Signals.mq5` (`SaveState`, `ClearState`, `ReportClose`)

- [ ] **Step 1: Live update on every state mutation (inside SaveState, at the end)**

Find (the last lines of `SaveState`):
```mql5
    FileWriteString(h, json);
    FileClose(h);
    Log(DEBUG_LVL, "STATE", "Estado persistido: " + TruncLog(json));
}
```
Replace with:
```mql5
    FileWriteString(h, json);
    FileClose(h);
    Log(DEBUG_LVL, "STATE", "Estado persistido: " + TruncLog(json));

    JournalWriteLive();
}
```

- [ ] **Step 2: Clear the live file on close (inside ClearState, at the end)**

Find:
```mql5
void ClearState() {
    if(FileIsExist(StateFilePath())) {
        FileDelete(StateFilePath());
        Log(DEBUG_LVL, "STATE", "State file eliminado");
    }
}
```
Replace with:
```mql5
void ClearState() {
    if(FileIsExist(StateFilePath())) {
        FileDelete(StateFilePath());
        Log(DEBUG_LVL, "STATE", "State file eliminado");
    }
    JournalWriteLive();   // refresca el live (sin trade activo queda solo el header)
}
```

- [ ] **Step 3: Append the final row on every terminal event (inside ReportClose)**

Find the end of `ReportClose` (the closing of the function after the API send result handling). It ends with:
```mql5
    } else {
        Log(ERROR_LVL, "REPORT", StringFormat("Close FALLÓ: %s (HTTP %d) — Response: %s", response.message, response.httpCode, TruncLog(response.data)));
    }
}
```
Replace with:
```mql5
    } else {
        Log(ERROR_LVL, "REPORT", StringFormat("Close FALLÓ: %s (HTTP %d) — Response: %s", response.message, response.httpCode, TruncLog(response.data)));
    }

    JournalAppendClosed(exitLevel, closeReason, finalPnl, finalPrice);
}
```

- [ ] **Step 4: Compile (F7) + commit.**
```bash
git add EA/EA_Signals.mq5
git commit -m "feat(EA_Signals): wire journal live/append into SaveState/ClearState/ReportClose"
```

---

## Task 5: Version bump + demo verification

- [ ] **Step 1: Bump version to 10.10** (`#property version`, description, and the `Print` in `LogInitialization`) and commit:
```bash
git commit -am "chore(EA_Signals): bump version to 10.10 (CSV trade journal)"
```

- [ ] **Step 2: Demo checks**
  - On a trade open, `MQL5/Files/bxlite_live_<user>_<ticker>.csv` exists with header + 1 row that **changes** as TPs hit / BE moves.
  - On close, `bxlite_journal_<user>_<ticker>.csv` gains a finalized row; the live file drops to header-only.
  - On a rejected signal (e.g. SPREAD_TOO_HIGH), the journal gains a row with `result=SPREAD_TOO_HIGH` and blank gate fields beyond what was computed.
  - Open the journal in Excel → 38 columns, parses cleanly.

---

## Self-Review

- Spec §5.1 journal append-only, one row/trade + rejects → `JournalAppendClosed` hooked in `ReportClose` (every terminal event). ✅
- Spec §5.2 live, header+1 row, overwritten per state change → `JournalWriteLive` in `SaveState`/`ClearState`. ✅
- Spec §5.3 columns, ISO time, dot decimals, correction = factor + raw/corrected → `JournalHeader`/`JournalRow`. ✅
- Per-EA files (no contention) → paths keyed by USER_ID + TICKER_SYMBOL. ✅
- Identifier consistency: `currentJournal` used everywhere (global). `JournalRow` field count (38) matches `JournalHeader` (38). `JournalAppendClosed(exitLevel, closeReason, finalPnl, finalPrice)` arg order matches `ReportClose` locals. ✅
- Restart edge: an adopted trade has an empty `currentJournal` (gates blank) but valid outcome at close — acceptable, noted.
- No behavior change: only file writes added. ✅
