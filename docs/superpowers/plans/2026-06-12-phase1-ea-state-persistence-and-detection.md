# Phase 1 · Part 1 — EA State Persistence + Restart Adoption + Comment-Independent Fill Detection

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make `EA_Signals.mq5` recognize its own positions without depending on the order comment, and survive EA restarts by persisting active-trade state to disk — eliminating the "filled pending never reported" bug.

**Architecture:** Add a small disk-backed state store (`MQL5/Files`, single JSON line) that mirrors the in-memory `currentTP`. Persist on every meaningful state change; on `OnInit`, reload it and re-adopt the live MT5 position (or, if it closed while the EA was down, report the close from history). Replace the comment-based position match with a `magic + symbol` match.

**Tech Stack:** MQL5 (MetaTrader 5), single-file EA. Reuses the existing `JsonBuilder` (write) and `SimpleJSONParser` (read) classes already in the file.

**Scope boundary:** This plan does NOT add the durable retry/outbox queue, `event_id`/`seq`, or the API idempotency guard — those are the next plan (Phase 1 · Part 2). Reports here are still best-effort (a failed POST is logged, not retried), exactly like today; we are only fixing detection + restart survival.

**Testing reality:** MQL5 has no unit-test framework and `WebRequest` does not run in the Strategy Tester. So tasks are verified by a **manual checklist on a demo account**, observing the `Experts` tab log. Each task lists the exact steps and the exact expected log lines. This is a deliberate adaptation of the TDD template to the platform.

---

## File Structure

- **Modify only:** `EA_Signals.mq5` (single file, per project decision). New code is organized into a clearly-commented `STATE STORE (PERSISTENCIA)` section plus targeted edits to existing functions.

New functions added (all in `EA_Signals.mq5`):
- `string StateFilePath()` — deterministic filename per user+ticker.
- `void SaveState()` — serialize `currentTP` to disk.
- `bool LoadState()` — deserialize disk → `currentTP`; returns whether a trade was loaded.
- `void ClearState()` — delete the state file.
- `void AdoptStateOnInit()` — reload + reconcile on startup.

Existing functions edited:
- `FindOwnPosition()` — drop the comment condition.
- `OnInit()` — call `AdoptStateOnInit()`.
- `SetupTPState()`, `CheckPendingOrderExecution()`, `ClosePartialPosition()`, `SetBreakeven()`, `CheckAndExecuteTP()` — add `SaveState()` after mutations.
- `OnTick()` close branch, `ClosePositionByCode()`, `CheckPendingOrderExecution()` cancel branch — add `ClearState()` after the existing `InitTPState()`.

---

## Task 1: Comment-independent fill detection

This is the smallest, highest-value change and is independent of persistence. The broker rewriting the order comment on fill must no longer hide our own position.

**Files:**
- Modify: `EA_Signals.mq5` — `FindOwnPosition()` (currently ~line 283)

- [ ] **Step 1: Read the current function to confirm the anchor**

Run (in your editor / Grep): locate `bool FindOwnPosition()`. Current body:

```mql5
bool FindOwnPosition() {
    for(int i = 0; i < PositionsTotal(); i++) {
        if(position.SelectByIndex(i)) {
            if(position.Symbol() == currentSymbol &&
               position.Magic() == MAGIC_NUMBER &&
               position.Comment() == TRADE_COMMENT) {
                return true;
            }
        }
    }
    return false;
}
```

- [ ] **Step 2: Replace it with the comment-independent version**

```mql5
// Busca posicion propia por Magic+Symbol (SIN depender del comment:
// muchos brokers reescriben el comment al ejecutar una pendiente).
// El EA maneja una sola posicion a la vez, asi que magic+symbol es univoco.
bool FindOwnPosition() {
    for(int i = 0; i < PositionsTotal(); i++) {
        if(position.SelectByIndex(i)) {
            if(position.Symbol() == currentSymbol &&
               position.Magic() == MAGIC_NUMBER) {
                return true;
            }
        }
    }
    return false;
}
```

- [ ] **Step 3: Compile**

Run: open `EA_Signals.mq5` in MetaEditor → press F7 (Compile).
Expected: `0 errors, 0 warnings`.

- [ ] **Step 4: Commit**

```bash
git add EA/EA_Signals.mq5
git commit -m "fix(EA_Signals): detect own position by magic+symbol, not comment"
```

---

## Task 2: State store — serialize / deserialize / clear

Add the persistence primitives. No behavior change yet (nothing calls them); we wire them in Tasks 3–4.

**Files:**
- Modify: `EA_Signals.mq5` — add a new section after the `SimpleJSONParser` / `JsonBuilder` classes and before `OnInit()` (anchor: just above the `// INICIALIZACIÓN` comment block, ~line 290).

- [ ] **Step 1: Add the state-store section**

Insert this block immediately before the `// ==========================================` / `// INICIALIZACIÓN` header:

```mql5
// ==========================================
// STATE STORE (PERSISTENCIA EN DISCO)
// ==========================================
// Persiste currentTP a MQL5/Files para sobrevivir reinicios del EA/terminal.
// Formato: una sola linea JSON. Se reusa JsonBuilder (write) y SimpleJSONParser (read).

string StateFilePath() {
    return "bxlite_state_" + IntegerToString(USER_ID) + "_" + TICKER_SYMBOL + ".json";
}

void SaveState() {
    JsonBuilder jb;
    jb.AddBool("isActive", currentTP.isActive);
    jb.AddInt("signalId", currentTP.signalId);
    jb.AddString("ticket", IntegerToString((long)currentTP.ticket));
    jb.AddString("positionID", IntegerToString((long)currentTP.positionID));
    jb.AddString("direction", currentTP.direction);
    jb.AddDouble("originalVolume", currentTP.originalVolume, 2);
    jb.AddDouble("currentVolume", currentTP.currentVolume, 2);
    jb.AddDouble("totalClosedVolume", currentTP.totalClosedVolume, 2);
    jb.AddDouble("closedPercent", currentTP.closedPercent, 2);
    jb.AddInt("currentLevel", currentTP.currentLevel);
    jb.AddBool("slMovedToBE", currentTP.slMovedToBE);
    jb.AddDouble("entry", currentTP.entry, 5);
    jb.AddDouble("originalSL", currentTP.originalSL, 5);
    jb.AddDouble("currentSL", currentTP.currentSL, 5);
    jb.AddDouble("tp1", currentTP.tp1, 5);
    jb.AddDouble("tp2", currentTP.tp2, 5);
    jb.AddDouble("tp3", currentTP.tp3, 5);
    jb.AddDouble("tp4", currentTP.tp4, 5);
    jb.AddDouble("tp5", currentTP.tp5, 5);
    // levelFlags como array crudo [0/1 x6]
    string flags = "[";
    for(int i = 0; i < 6; i++) {
        if(i > 0) flags += ",";
        flags += (currentTP.levelFlags[i] ? "1" : "0");
    }
    flags += "]";
    jb.AddRaw("levelFlags", flags);

    string json = jb.Build();

    int h = FileOpen(StateFilePath(), FILE_WRITE|FILE_TXT|FILE_ANSI);
    if(h == INVALID_HANDLE) {
        Log(ERROR_LVL, "STATE", "No se pudo abrir state file para escritura: " + IntegerToString(GetLastError()));
        return;
    }
    FileWriteString(h, json);
    FileClose(h);
    Log(DEBUG_LVL, "STATE", "Estado persistido: " + TruncLog(json));
}

// Devuelve true si habia un estado con trade (activo o pendiente) cargado.
bool LoadState() {
    if(!FileIsExist(StateFilePath())) return false;

    int h = FileOpen(StateFilePath(), FILE_READ|FILE_TXT|FILE_ANSI);
    if(h == INVALID_HANDLE) {
        Log(ERROR_LVL, "STATE", "No se pudo abrir state file para lectura: " + IntegerToString(GetLastError()));
        return false;
    }
    string json = "";
    while(!FileIsEnding(h)) {
        json += FileReadString(h);
    }
    FileClose(h);

    if(StringLen(json) < 2) return false;

    SimpleJSONParser parser(json);
    currentTP.isActive          = (parser.GetInt("isActive", 0) == 1) || (StringFind(json, "\"isActive\":true") > -1);
    currentTP.signalId          = parser.GetInt("signalId", 0);
    currentTP.ticket            = (ulong)StringToInteger(parser.GetString("ticket", "0"));
    currentTP.positionID        = (ulong)StringToInteger(parser.GetString("positionID", "0"));
    currentTP.direction         = parser.GetString("direction", "");
    currentTP.originalVolume    = parser.GetDouble("originalVolume", 0);
    currentTP.currentVolume     = parser.GetDouble("currentVolume", 0);
    currentTP.totalClosedVolume = parser.GetDouble("totalClosedVolume", 0);
    currentTP.closedPercent     = parser.GetDouble("closedPercent", 0);
    currentTP.currentLevel      = parser.GetInt("currentLevel", -2);
    currentTP.slMovedToBE       = (StringFind(json, "\"slMovedToBE\":true") > -1);
    currentTP.entry             = parser.GetDouble("entry", 0);
    currentTP.originalSL        = parser.GetDouble("originalSL", 0);
    currentTP.currentSL         = parser.GetDouble("currentSL", 0);
    currentTP.tp1               = parser.GetDouble("tp1", 0);
    currentTP.tp2               = parser.GetDouble("tp2", 0);
    currentTP.tp3               = parser.GetDouble("tp3", 0);
    currentTP.tp4               = parser.GetDouble("tp4", 0);
    currentTP.tp5               = parser.GetDouble("tp5", 0);
    for(int i = 0; i < 6; i++) {
        currentTP.levelFlags[i] = (parser.GetArrayDouble("levelFlags", i, 0) >= 0.5);
    }

    // signalId>0 indica que habia un trade trackeado (activo o pendiente)
    return (currentTP.signalId > 0);
}

void ClearState() {
    if(FileIsExist(StateFilePath())) {
        FileDelete(StateFilePath());
        Log(DEBUG_LVL, "STATE", "State file eliminado");
    }
}
```

> Note on `isActive` parsing: `JsonBuilder.AddBool` writes `true`/`false` (not 1/0), and `SimpleJSONParser` has no native bool reader, so we detect the literal `"isActive":true` substring. The `GetInt` fallback keeps it robust if the format ever changes.

- [ ] **Step 2: Compile**

Run: MetaEditor → F7.
Expected: `0 errors, 0 warnings`.

- [ ] **Step 3: Commit**

```bash
git add EA/EA_Signals.mq5
git commit -m "feat(EA_Signals): add disk state store (save/load/clear currentTP)"
```

---

## Task 3: Persist on state changes + clear on close

Wire `SaveState()` into every mutation and `ClearState()` into every close, so the disk always mirrors reality.

**Files:**
- Modify: `EA_Signals.mq5` — `SetupTPState()`, `CheckPendingOrderExecution()`, `ClosePartialPosition()`, `SetBreakeven()`, `CheckAndExecuteTP()`, and the three close sites.

- [ ] **Step 1: Save after a new trade is set up**

In `SetupTPState()`, the function currently ends with a `Log(INFO_LVL, "SETUP", ...)` call. Add `SaveState();` immediately after that log line (last statement of the function):

```mql5
    Log(INFO_LVL, "SETUP", StringFormat("TP State: SignalID=%d, Ticket=%d, PosID=%d, Active=%s",
        signalId, ticket, currentTP.positionID, (isMarketOrder ? "YES" : "PENDING")));

    SaveState();   // <-- AÑADIR
}
```

- [ ] **Step 2: Save after a pending order is detected as filled**

In `CheckPendingOrderExecution()`, inside the `if(FindOwnPosition())` block, after the existing `ReportPendingExecuted(...)` call and before `return;`, add `SaveState();`:

```mql5
        ReportPendingExecuted(currentTP.signalId, currentTP.ticket, currentTP.entry,
                              currentTP.currentSL, currentTP.currentVolume);
        SaveState();   // <-- AÑADIR
        return;
```

- [ ] **Step 3: Save after a partial close (level/volume changed)**

In `ClosePartialPosition()`, inside the `if(trade.PositionClosePartial(...))` block, after the `Log(INFO_LVL, "PARTIAL_CLOSE", ...)` line and before the `if(currentTP.currentVolume <= specs.minVolume)` check, add `SaveState();`:

```mql5
        Log(INFO_LVL, "PARTIAL_CLOSE", StringFormat("TP%d: Cerrado %.2f lots (%.1f%%) a %.5f, PNL=%.2f, Restante=%.2f",
            currentTP.currentLevel, volume, (volume/currentTP.originalVolume)*100, currentPrice, closedPnl, currentTP.currentVolume));

        SaveState();   // <-- AÑADIR

        if(currentTP.currentVolume <= specs.minVolume) {
```

- [ ] **Step 4: Save after breakeven moves the SL**

In `SetBreakeven()`, inside the `if(trade.PositionModify(...))` block, after `currentTP.currentSL = newSL;`, add `SaveState();`:

```mql5
            currentTP.slMovedToBE = true;
            currentTP.currentSL = newSL;
            SaveState();   // <-- AÑADIR
```

- [ ] **Step 5: Save after a TP level flag is set**

In `CheckAndExecuteTP()`, just before `return true;` at the end of the executed branch (after `currentTP.levelFlags[tpLevel] = true;`), add `SaveState();`:

```mql5
        currentTP.levelFlags[tpLevel] = true;
        SaveState();   // <-- AÑADIR
        return true;
```

- [ ] **Step 6: Clear state at every close site**

Add `ClearState();` immediately after each existing `InitTPState();` that follows a real close/cancel. There are three:

(a) In `OnTick()`, the position-closed branch:
```mql5
        ReportClose(currentTP.signalId, closeResult.exitLevel, closeResult.reason, reportPrice, finalPnl);
        InitTPState();
        ClearState();   // <-- AÑADIR
        return;
```

(b) In `ClosePartialPosition()`, the fully-closed branch:
```mql5
        if(currentTP.currentVolume <= specs.minVolume) {
            ReportClose(currentTP.signalId, currentTP.currentLevel, "CLOSED_COMPLETE", currentPrice, 0.0);
            InitTPState();
            ClearState();   // <-- AÑADIR
        }
```

(c) In `ClosePositionByCode()`:
```mql5
        ReportClose(currentTP.signalId, exitLevel, reason, currentPrice, finalPnl);
        Log(INFO_LVL, "CODE_CLOSE", "Posición cerrada por código: " + reason);
        InitTPState();
        ClearState();   // <-- AÑADIR
        return true;
```

(d) In `CheckPendingOrderExecution()`, the cancelled branch:
```mql5
    if(!OrderSelect(currentTP.ticket)) {
        Log(WARNING_LVL, "PENDING", "Orden pendiente cancelada");
        ReportClose(currentTP.signalId, -999, "ORDER_CANCELLED", 0, 0);
        InitTPState();
        ClearState();   // <-- AÑADIR
    }
```

- [ ] **Step 7: Compile**

Run: MetaEditor → F7.
Expected: `0 errors, 0 warnings`.

- [ ] **Step 8: Manual verification — state file lifecycle**

1. Attach EA to a demo chart of your ticker with a valid `API_URL`. Set `MIN_LOG_LEVEL = DEBUG_LVL`.
2. When a signal opens a trade, check `MQL5/Files/` for `bxlite_state_<USER_ID>_<TICKER>.json`.
   Expected: file exists, contains one JSON line with `"isActive":true`, the `signalId`, ticket, and the TPs.
3. When the trade fully closes, the file disappears.
   Expected log: `[DEBUG] [STATE] State file eliminado`.

- [ ] **Step 9: Commit**

```bash
git add EA/EA_Signals.mq5
git commit -m "feat(EA_Signals): persist currentTP on mutations, clear on close"
```

---

## Task 4: Restart adoption in OnInit

On startup, reload the persisted state and reconcile it against MT5: re-adopt a live position, keep waiting on a still-pending order, or report a close that happened while the EA was down.

**Files:**
- Modify: `EA_Signals.mq5` — add `AdoptStateOnInit()` (place it right after the state-store functions from Task 2) and call it from `OnInit()`.

- [ ] **Step 1: Add the adoption function**

Add immediately after `ClearState()`:

```mql5
// Reconcilia el estado persistido contra la realidad de MT5 al arrancar.
void AdoptStateOnInit() {
    if(!LoadState()) {
        ClearState();   // archivo vacio/corrupto: limpiar
        return;
    }

    // Caso 1: hay una posicion viva nuestra (magic+symbol) -> re-adoptar
    if(FindOwnPosition()) {
        bool wasPending = !currentTP.isActive;   // si el estado guardado era pendiente y ahora hay posicion => se ejecuto mientras estabamos caidos

        currentTP.isActive     = true;
        currentTP.ticket       = position.Ticket();
        currentTP.positionID   = position.Identifier();
        currentTP.currentVolume = position.Volume();
        currentTP.currentSL    = position.StopLoss();
        if(currentTP.currentLevel < 0) currentTP.currentLevel = 0;

        Log(INFO_LVL, "ADOPT", StringFormat("Posición re-adoptada: SignalID=%d, Ticket=%d, PosID=%d, Vol=%.2f, %s",
            currentTP.signalId, currentTP.ticket, currentTP.positionID, currentTP.currentVolume,
            (wasPending ? "(pendiente ejecutada mientras EA caido)" : "")));

        // Si la pendiente se ejecuto mientras el EA estaba caido, avisar ahora a la API
        if(wasPending) {
            ReportPendingExecuted(currentTP.signalId, currentTP.ticket, currentTP.entry,
                                  currentTP.currentSL, currentTP.currentVolume);
        }
        SaveState();
        return;
    }

    // Caso 2: la orden pendiente sigue viva (todavia no se ejecuto) -> seguir esperando
    if(!currentTP.isActive && currentTP.ticket > 0 && OrderSelect(currentTP.ticket)) {
        Log(INFO_LVL, "ADOPT", StringFormat("Orden pendiente aún viva re-adoptada: SignalID=%d, Ticket=%d",
            currentTP.signalId, currentTP.ticket));
        SaveState();
        return;
    }

    // Caso 3: no hay posicion ni pendiente -> cerro/cancelo mientras estabamos caidos
    Log(WARNING_LVL, "ADOPT", StringFormat("Trade SignalID=%d ya no existe en MT5: reconciliando cierre desde historial",
        currentTP.signalId));
    HistoryCloseResult closeResult = GetCloseReasonFromHistory(currentTP.positionID);
    double reportPrice = closeResult.hasDealData ? closeResult.dealPrice : 0.0;
    double finalPnl    = closeResult.hasDealData ? closeResult.dealProfit : 0.0;
    ReportClose(currentTP.signalId, closeResult.exitLevel, closeResult.reason, reportPrice, finalPnl);
    InitTPState();
    ClearState();
}
```

- [ ] **Step 2: Call it from OnInit**

In `OnInit()`, the current sequence is `InitTPState(); SetupBaseUrls();` then `trade.Set...`. Change so base URLs are ready (adoption may need to report), then adopt, **replacing** the bare `InitTPState();`:

Current:
```mql5
    InitTPState();
    SetupBaseUrls();

    trade.SetExpertMagicNumber(MAGIC_NUMBER);
    trade.SetDeviationInPoints(50);

    LogInitialization();
    EventSetTimer(POLL_INTERVAL);
    CheckForSignals();
```

New:
```mql5
    InitTPState();
    SetupBaseUrls();

    trade.SetExpertMagicNumber(MAGIC_NUMBER);
    trade.SetDeviationInPoints(50);

    AdoptStateOnInit();   // <-- AÑADIR: reconciliar estado persistido contra MT5

    LogInitialization();
    EventSetTimer(POLL_INTERVAL);

    // Solo pollear señales nuevas si NO quedamos con un trade adoptado
    if(!currentTP.isActive && currentTP.ticket == 0) {
        CheckForSignals();
    }
```

> Why the guard on `CheckForSignals()`: if we just adopted an active/pending trade, we must not immediately claim a new signal on top of it (the EA is single-position).

- [ ] **Step 3: Compile**

Run: MetaEditor → F7.
Expected: `0 errors, 0 warnings`.

- [ ] **Step 4: Manual verification — the four restart scenarios**

Use a demo account, `MIN_LOG_LEVEL = INFO_LVL`, valid `API_URL` whitelisted in MT5 (Tools → Options → Expert Advisors → Allow WebRequest).

Scenario A — **pending fills while EA is down (the original bug):**
1. Get a signal that places a pending order (state file shows `"isActive":false`).
2. Remove the EA from the chart (or close MT5).
3. Manually move price / wait until the pending would fill — or in a controlled test, let it fill.
4. Re-attach the EA.
   Expected log: `[INFO] [ADOPT] Posición re-adoptada: ... (pendiente ejecutada mientras EA caido)` followed by a `progress`/`now_open` POST. The API now shows the signal as `open`. ✅ This is the bug we set out to kill.

Scenario B — **active position survives a recompile:**
1. With an open market position, recompile the EA (F7) so MT5 reloads it.
   Expected log: `[INFO] [ADOPT] Posición re-adoptada: SignalID=...`. TP management continues; no duplicate signal claimed.

Scenario C — **position closed (e.g. SL) while EA down:**
1. With an open position, remove the EA. Close the position manually or let SL hit.
2. Re-attach the EA.
   Expected log: `[WARNING] [ADOPT] Trade ... ya no existe ... reconciliando cierre desde historial` + a `close` POST with the real reason/PnL. State file removed.

Scenario D — **broker changed the comment on fill:**
1. Reproduce Scenario A on the broker that previously hid the fill.
   Expected: detection still works (we no longer read the comment). ✅

- [ ] **Step 5: Commit**

```bash
git add EA/EA_Signals.mq5
git commit -m "feat(EA_Signals): adopt/reconcile persisted state on OnInit (survive restarts)"
```

---

## Self-Review

**Spec coverage (Phase 1 · Part 1 subset):**
- Comment-independent fill detection → Task 1. ✅
- State store on disk → Task 2. ✅
- Persist on changes / clear on close → Task 3. ✅
- Restart adoption in `OnInit` → Task 4. ✅
- Durable retry/outbox, `event_id`/`seq`, API idempotency → **explicitly deferred** to Phase 1 · Part 2 (noted in Scope boundary). ✅ (no gap — intentional)

**Type consistency:** `currentTP` field names (`isActive`, `signalId`, `ticket`, `positionID`, `direction`, `originalVolume`, `currentVolume`, `totalClosedVolume`, `closedPercent`, `currentLevel`, `levelFlags`, `slMovedToBE`, `entry`, `originalSL`, `currentSL`, `tp1..tp5`) match the `OptimizedTPState` struct exactly. `SaveState`/`LoadState`/`ClearState`/`AdoptStateOnInit`/`StateFilePath`/`FindOwnPosition`/`ReportPendingExecuted`/`GetCloseReasonFromHistory`/`HistoryCloseResult` all referenced as defined in the file or in earlier tasks. ✅

**Placeholder scan:** No TBD/TODO; every code step shows complete code. ✅

**Risk notes:**
- `TICKER_SYMBOL` is used in the filename — our tickers (`GC`, `CL`, `ES`, `BTCUSD`) are filesystem-safe. If a future ticker contains `/` or `!`, sanitize in `StateFilePath()`.
- Adoption reports (`ReportPendingExecuted` / `ReportClose`) are still best-effort in this plan; if that single POST fails it is lost. Part 2's outbox makes it durable.

---

## Execution Handoff

Two execution options:

1. **Subagent-Driven (recommended)** — dispatch a fresh subagent per task, review between tasks. Note: the manual MT5 verification steps can't be done by a subagent — those are checkpoints for you to run on the demo account.
2. **Inline Execution** — implement the edits here in-session, you run the compile + demo checklist between tasks.

Which approach?
