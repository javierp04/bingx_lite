# Phase A — Asset-Agnostic Gates (T1-anchored) + STOPS_LEVEL + MT5 Logging

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the three fixed-"points" execution thresholds in `EA_Signals.mq5` (spread, market/pending tolerance, slippage) with dimensionless ratios anchored to the signal's first-target distance `T1 = |entry − TP1|`, add broker `STOPS_LEVEL` validation, and log each gate as `tolerado vs real`.

**Architecture:** All edits are in the single file `EA/EA_Signals.mq5`. The work is sequenced into 6 tasks ordered so the file **compiles after every commit** (new inputs added alongside old ones first; old inputs removed last). No trading logic (volume, price correction, TP management, breakeven) changes — only the entry/cost gates and their logging.

**Tech Stack:** MQL5 (MetaTrader 5), single-file EA.

**Scope:** Phase A only (behavior + human logging). The CSV journal/live files are Phase B (separate plan). Spec: `docs/superpowers/specs/2026-06-12-ea-asset-agnostic-thresholds-and-journal-design.md`.

**Testing reality:** MQL5 has no unit-test framework and `WebRequest`/trading don't run in the Strategy Tester. Each task is verified by **compiling in MetaEditor (F7)**; the full behavior is verified by a **demo-account checklist** at the end (Task 7). This is a deliberate adaptation of the TDD template to the platform.

---

## File Structure
- **Modify only:** `EA/EA_Signals.mq5`.
  - Inputs block (~line 21-27): swap 3 old inputs → 4 ratio inputs.
  - `ValidateSpread()` (~1439): spread vs `c·T1` using real Bid/Ask.
  - `DetermineOrderType()` (~1452): market band `k_stop/k_limit · T1` by side.
  - `ExecuteTrade()` (~1492): compute `T1` early; per-trade slippage deviation; `STOPS_LEVEL` check; gate header log.
  - `OnInit()` (~468): remove fixed `SetDeviationInPoints(50)`.

---

## Task 1: Add the four ratio inputs (keep old ones for now)

Adding the new inputs alongside the old keeps the file compiling; the old ones are removed in Task 6 once nothing references them.

**Files:** Modify `EA/EA_Signals.mq5` (inputs block, after line 27)

- [ ] **Step 1: Insert the new input group**

Find:
```mql5
input int       PRICE_TOLERANCE_POINTS = 50;
input double    PRICE_TOLERANCE_PERCENT = 0.0;  // 0.0 = usar points, > 0 = usar porcentaje (prioridad)
```
Replace with (keeps both old lines, adds the new group after them):
```mql5
input int       PRICE_TOLERANCE_POINTS = 50;
input double    PRICE_TOLERANCE_PERCENT = 0.0;  // 0.0 = usar points, > 0 = usar porcentaje (prioridad)

input group "=== Gates asset-agnostic (anclados a TP1) ==="
input double    K_STOP_RATIO   = 0.30;   // banda market lado favorable (STOP)
input double    K_LIMIT_RATIO  = 0.15;   // banda market lado adverso (LIMIT) — debe ser > M_SLIP_RATIO
input double    M_SLIP_RATIO   = 0.05;   // tope de slippage (deviation) — debe ser < K_LIMIT_RATIO
input double    C_SPREAD_RATIO = 0.40;   // rechaza si spread > C_SPREAD_RATIO * T1
```

- [ ] **Step 2: Compile**

Run: MetaEditor → open `EA_Signals.mq5` → F7.
Expected: `0 errors`. (Warnings about the new inputs being unused are acceptable at this stage.)

- [ ] **Step 3: Commit**

```bash
git add EA/EA_Signals.mq5
git commit -m "feat(EA_Signals): add T1-anchored gate ratio inputs"
```

---

## Task 2: Compute T1 in ExecuteTrade + refactor the spread gate

**Files:** Modify `EA/EA_Signals.mq5` (`ValidateSpread`, `ExecuteTrade`)

- [ ] **Step 1: Replace `ValidateSpread()` with the T1-anchored version**

Find:
```mql5
// Subfuncion: Validar spread. Retorna false si muy alto.
bool ValidateSpread(int userSignalId) {
    int spreadPoints = (int)SymbolInfoInteger(currentSymbol, SYMBOL_SPREAD);
    if(spreadPoints > MAX_SPREAD) {
        SymbolSpecs specs = GetSymbolSpecs();
        Log(ERROR_LVL, "SPREAD", StringFormat("Spread demasiado alto: %d points > %d max | Symbol=%s",
                spreadPoints, (int)MAX_SPREAD, currentSymbol));
        ReportClose(userSignalId, -999, "SPREAD_TOO_HIGH", 0, 0);
        return false;
    }
    return true;
}
```
Replace with:
```mql5
// Subfuncion: Validar spread como fraccion de T1 (asset-agnostic). Retorna false si muy alto.
bool ValidateSpread(int userSignalId, double t1) {
    double spreadReal = SymbolInfoDouble(currentSymbol, SYMBOL_ASK) - SymbolInfoDouble(currentSymbol, SYMBOL_BID);
    double spreadTol  = C_SPREAD_RATIO * t1;
    double pctT1      = (t1 > 0) ? (spreadReal / t1 * 100.0) : 0.0;

    // spreadReal <= 0: dato no disponible (algunos brokers reportan 0 un instante) -> no rechazar por 0
    if(spreadReal > 0 && spreadReal > spreadTol) {
        Log(ERROR_LVL, "SPREAD", StringFormat("real=%.5f | tol=%.5f (c=%.2f*T1) | %.1f%% T1 -> RECHAZA SPREAD_TOO_HIGH",
            spreadReal, spreadTol, C_SPREAD_RATIO, pctT1));
        ReportClose(userSignalId, -999, "SPREAD_TOO_HIGH", 0, 0);
        return false;
    }
    Log(INFO_LVL, "SPREAD", StringFormat("real=%.5f | tol=%.5f (c=%.2f*T1) | %.1f%% T1 -> OK",
        spreadReal, spreadTol, C_SPREAD_RATIO, pctT1));
    return true;
}
```

- [ ] **Step 2: Compute `t1` in ExecuteTrade and pass it to ValidateSpread**

Find:
```mql5
    // 1. Corrección de precios
    double correctionFactor;
    if(!ApplyPriceCorrection(entryPrice, stopLoss, tp1, tp2, tp3, tp4, tp5, userSignalId, correctionFactor))
        return false;

    // 2. Validar spread
    if(!ValidateSpread(userSignalId))
        return false;
```
Replace with:
```mql5
    // 1. Corrección de precios
    double correctionFactor;
    if(!ApplyPriceCorrection(entryPrice, stopLoss, tp1, tp2, tp3, tp4, tp5, userSignalId, correctionFactor))
        return false;

    // 1.5 Escala de la señal: T1 (distancia a TP1) para los gates de entrada/costo.
    //     R (entry->SL) se sigue usando para el volumen. Ambos con precios ya corregidos.
    double t1 = MathAbs(entryPrice - tp1);
    if(t1 <= 0) t1 = MathAbs(entryPrice - stopLoss);   // fallback a R si TP1 degenerado

    // 2. Validar spread (fraccion de T1)
    if(!ValidateSpread(userSignalId, t1))
        return false;
```

- [ ] **Step 3: Compile**

Run: MetaEditor → F7.
Expected: `0 errors`.

- [ ] **Step 4: Commit**

```bash
git add EA/EA_Signals.mq5
git commit -m "feat(EA_Signals): spread gate as fraction of T1 with real Bid/Ask"
```

---

## Task 3: Refactor DetermineOrderType to market band k·T1 by side

**Files:** Modify `EA/EA_Signals.mq5` (`DetermineOrderType` + its call in `ExecuteTrade`)

- [ ] **Step 1: Replace `DetermineOrderType()`**

Find the whole function (from `// Subfuncion: Determinar tipo de orden` through its closing `return orderType; }`):
```mql5
// Subfuncion: Determinar tipo de orden basado en precio actual vs entry
ENUM_ORDER_TYPE DetermineOrderType(string opType, double entryPrice, double &currentPrice, string &orderDecision) {
    currentPrice = (opType == "LONG") ?
                   SymbolInfoDouble(currentSymbol, SYMBOL_ASK) :
                   SymbolInfoDouble(currentSymbol, SYMBOL_BID);

    double point = SymbolInfoDouble(currentSymbol, SYMBOL_POINT);
    double priceDifference = MathAbs(entryPrice - currentPrice);
    double differencePoints = priceDifference / point;

    // Calcular tolerancia
    double tolerance;
    string toleranceMode;

    if(PRICE_TOLERANCE_PERCENT > 0.0) {
        tolerance = entryPrice * (PRICE_TOLERANCE_PERCENT / 100.0);
        double tolerancePoints = tolerance / point;
        toleranceMode = StringFormat("%.3f%% (%.1f pts)", PRICE_TOLERANCE_PERCENT, tolerancePoints);
    } else {
        tolerance = PRICE_TOLERANCE_POINTS * point;
        toleranceMode = StringFormat("%d pts", PRICE_TOLERANCE_POINTS);
    }

    ENUM_ORDER_TYPE orderType;

    if(opType == "LONG") {
        if(priceDifference <= tolerance)     { orderType = ORDER_TYPE_BUY;       orderDecision = "MARKET"; }
        else if(entryPrice < currentPrice)   { orderType = ORDER_TYPE_BUY_LIMIT; orderDecision = "LIMIT"; }
        else                                 { orderType = ORDER_TYPE_BUY_STOP;  orderDecision = "STOP"; }
    } else {
        if(priceDifference <= tolerance)     { orderType = ORDER_TYPE_SELL;       orderDecision = "MARKET"; }
        else if(entryPrice > currentPrice)   { orderType = ORDER_TYPE_SELL_LIMIT; orderDecision = "LIMIT"; }
        else                                 { orderType = ORDER_TYPE_SELL_STOP;  orderDecision = "STOP"; }
    }

    Log(INFO_LVL, "ORDER_DECISION", StringFormat("Actual=%.5f, Entry=%.5f, Diff=%.1f pts, Tolerancia=%s, Decisión=%s",
        currentPrice, entryPrice, differencePoints, toleranceMode, orderDecision));

    return orderType;
}
```
Replace with:
```mql5
// Subfuncion: Determinar tipo de orden. Banda market = k*T1, con k distinto por lado:
//   lado FAVORABLE (STOP): market daria mejor precio que el entry  -> k_stop  (mas ancho)
//   lado ADVERSO  (LIMIT): market seria chase (peor precio)        -> k_limit (mas angosto)
ENUM_ORDER_TYPE DetermineOrderType(string opType, double entryPrice, double t1, double &currentPrice, string &orderDecision) {
    currentPrice = (opType == "LONG") ?
                   SymbolInfoDouble(currentSymbol, SYMBOL_ASK) :
                   SymbolInfoDouble(currentSymbol, SYMBOL_BID);

    double diff = MathAbs(entryPrice - currentPrice);

    // Lado favorable = el precio actual esta del lado que daria mejor entrada que el entry de la señal.
    bool stopSide = (opType == "LONG") ? (currentPrice < entryPrice) : (currentPrice > entryPrice);
    double k      = stopSide ? K_STOP_RATIO : K_LIMIT_RATIO;
    double tol    = k * t1;
    double pctT1  = (t1 > 0) ? (diff / t1 * 100.0) : 0.0;
    string side   = stopSide ? "FAVORABLE" : "ADVERSO";

    ENUM_ORDER_TYPE orderType;
    if(diff <= tol) {
        orderType     = (opType == "LONG") ? ORDER_TYPE_BUY : ORDER_TYPE_SELL;
        orderDecision = "MARKET";
    } else if(stopSide) {
        orderType     = (opType == "LONG") ? ORDER_TYPE_BUY_STOP : ORDER_TYPE_SELL_STOP;
        orderDecision = "STOP";
    } else {
        orderType     = (opType == "LONG") ? ORDER_TYPE_BUY_LIMIT : ORDER_TYPE_SELL_LIMIT;
        orderDecision = "LIMIT";
    }

    Log(INFO_LVL, "ORDER", StringFormat("price=%.5f | dist=%.5f (%.1f%% T1) | side=%s | k=%.2f tol=%.5f -> %s",
        currentPrice, diff, pctT1, side, k, tol, orderDecision));

    return orderType;
}
```

- [ ] **Step 2: Update the call in ExecuteTrade**

Find:
```mql5
    // 4. Determinar tipo de orden
    double currentPrice;
    string orderDecision;
    ENUM_ORDER_TYPE orderType = DetermineOrderType(opType, entryPrice, currentPrice, orderDecision);
```
Replace with:
```mql5
    // 4. Determinar tipo de orden
    double currentPrice;
    string orderDecision;
    ENUM_ORDER_TYPE orderType = DetermineOrderType(opType, entryPrice, t1, currentPrice, orderDecision);
```

- [ ] **Step 3: Compile**

Run: MetaEditor → F7.
Expected: `0 errors`.

- [ ] **Step 4: Commit**

```bash
git add EA/EA_Signals.mq5
git commit -m "feat(EA_Signals): market/pending band as k_stop/k_limit * T1 by side"
```

---

## Task 4: Per-trade slippage deviation + STOPS_LEVEL validation; remove fixed deviation in OnInit

**Files:** Modify `EA/EA_Signals.mq5` (`ExecuteTrade`, `OnInit`)

- [ ] **Step 1: Add STOPS_LEVEL validation after the SL calc**

Find:
```mql5
    Log(INFO_LVL, "SL_CALC", StringFormat("Original=%.5f, Distance=%.5f, Factor=%.2f, Final=%.5f",
            stopLoss, slDistance, ENABLE_CODE_STOP ? SAFETY_FACTOR : 1.0, orderStopLoss));

    // 6. Ejecutar orden
    bool success = false;
    ulong ticket = 0;
    bool isMarketOrder = (orderType == ORDER_TYPE_BUY || orderType == ORDER_TYPE_SELL);
```
Replace with:
```mql5
    Log(INFO_LVL, "SL_CALC", StringFormat("Original=%.5f, Distance=%.5f, Factor=%.2f, Final=%.5f",
            stopLoss, slDistance, ENABLE_CODE_STOP ? SAFETY_FACTOR : 1.0, orderStopLoss));

    // 5.5 Validar distancia minima del broker (STOPS_LEVEL): si el SL queda mas cerca, el broker rechaza.
    double point    = SymbolInfoDouble(currentSymbol, SYMBOL_POINT);
    double stopsMin = (double)SymbolInfoInteger(currentSymbol, SYMBOL_TRADE_STOPS_LEVEL) * point;
    double slDistFinal = MathAbs(entryPrice - orderStopLoss);
    if(stopsMin > 0 && slDistFinal < stopsMin) {
        Log(ERROR_LVL, "STOPS", StringFormat("broker_min=%.5f | sl_dist=%.5f -> RECHAZA SL_TOO_CLOSE", stopsMin, slDistFinal));
        ReportClose(userSignalId, -999, "SL_TOO_CLOSE", 0, 0);
        return false;
    }
    Log(INFO_LVL, "STOPS", StringFormat("broker_min=%.5f | sl_dist=%.5f -> OK", stopsMin, slDistFinal));

    // 6. Ejecutar orden
    bool success = false;
    ulong ticket = 0;
    bool isMarketOrder = (orderType == ORDER_TYPE_BUY || orderType == ORDER_TYPE_SELL);

    // Slippage permitido solo para market: deviation = m*T1 (siempre < banda k por invariante m < k_limit)
    if(isMarketOrder) {
        double slipTol  = M_SLIP_RATIO * t1;
        int    devPoints = (int)MathRound(slipTol / point);
        if(devPoints < 1) devPoints = 1;
        trade.SetDeviationInPoints(devPoints);
        Log(INFO_LVL, "SLIPPAGE", StringFormat("tol=%.5f (m=%.2f*T1) = %d points", slipTol, M_SLIP_RATIO, devPoints));
    }
```

- [ ] **Step 2: Remove the fixed deviation in OnInit**

Find:
```mql5
    trade.SetExpertMagicNumber(MAGIC_NUMBER);
    trade.SetDeviationInPoints(50);
```
Replace with:
```mql5
    trade.SetExpertMagicNumber(MAGIC_NUMBER);
    // deviation se setea por-trade en ExecuteTrade (m*T1), no fijo
```

- [ ] **Step 3: Compile**

Run: MetaEditor → F7.
Expected: `0 errors`.

- [ ] **Step 4: Commit**

```bash
git add EA/EA_Signals.mq5
git commit -m "feat(EA_Signals): per-trade slippage deviation (m*T1) + STOPS_LEVEL validation"
```

---

## Task 5: Gate header log + post-fill real slippage

**Files:** Modify `EA/EA_Signals.mq5` (`ExecuteTrade`)

- [ ] **Step 1: Add the [GATES] header line right after t1 is computed**

Find:
```mql5
    // 1.5 Escala de la señal: T1 (distancia a TP1) para los gates de entrada/costo.
    //     R (entry->SL) se sigue usando para el volumen. Ambos con precios ya corregidos.
    double t1 = MathAbs(entryPrice - tp1);
    if(t1 <= 0) t1 = MathAbs(entryPrice - stopLoss);   // fallback a R si TP1 degenerado
```
Replace with:
```mql5
    // 1.5 Escala de la señal: T1 (distancia a TP1) para los gates de entrada/costo.
    //     R (entry->SL) se sigue usando para el volumen. Ambos con precios ya corregidos.
    double t1 = MathAbs(entryPrice - tp1);
    if(t1 <= 0) t1 = MathAbs(entryPrice - stopLoss);   // fallback a R si TP1 degenerado

    Log(INFO_LVL, "GATES", StringFormat("#%d %s %s | entry=%.5f SL=%.5f TP1=%.5f | R=%.5f T1=%.5f",
        userSignalId, TICKER_SYMBOL, opType, entryPrice, stopLoss, tp1, MathAbs(entryPrice - stopLoss), t1));
```

- [ ] **Step 2: Log real slippage post-fill (market orders)**

Find:
```mql5
        if(isMarketOrder && FindOwnPosition()) {
            realEntryPrice = position.PriceOpen();
            positionID = position.Identifier();
            if(realEntryPrice != entryPrice) {
                Log(INFO_LVL, "REAL_ENTRY", StringFormat("Precio real: %.5f (vs señal: %.5f)", realEntryPrice, entryPrice));
            }
            entryPrice = realEntryPrice;
        }
```
Replace with:
```mql5
        if(isMarketOrder && FindOwnPosition()) {
            realEntryPrice = position.PriceOpen();
            positionID = position.Identifier();
            double slipReal = MathAbs(realEntryPrice - currentPrice);
            Log(INFO_LVL, "SLIPPAGE", StringFormat("real=%.5f | pedido=%.5f fill=%.5f", slipReal, currentPrice, realEntryPrice));
            entryPrice = realEntryPrice;
        }
```

- [ ] **Step 3: Compile**

Run: MetaEditor → F7.
Expected: `0 errors`.

- [ ] **Step 4: Commit**

```bash
git add EA/EA_Signals.mq5
git commit -m "feat(EA_Signals): gate header log + post-fill real slippage log"
```

---

## Task 6: Remove the obsolete fixed-point inputs

`MAX_SPREAD`, `PRICE_TOLERANCE_POINTS`, `PRICE_TOLERANCE_PERCENT` are no longer referenced after Tasks 2-3.

**Files:** Modify `EA/EA_Signals.mq5` (inputs block)

- [ ] **Step 1: Confirm they are unreferenced**

Run (Grep): search the file for `MAX_SPREAD`, `PRICE_TOLERANCE_POINTS`, `PRICE_TOLERANCE_PERCENT`.
Expected: each appears ONLY on its own `input` declaration line (no other usages). If any other usage remains, stop and fix that first.

- [ ] **Step 2: Remove the three input lines**

Find:
```mql5
input double    MAX_SPREAD = 500.0;
input int       PRICE_TOLERANCE_POINTS = 50;
input double    PRICE_TOLERANCE_PERCENT = 0.0;  // 0.0 = usar points, > 0 = usar porcentaje (prioridad)

input group "=== Gates asset-agnostic (anclados a TP1) ==="
```
Replace with:
```mql5
input group "=== Gates asset-agnostic (anclados a TP1) ==="
```

- [ ] **Step 3: Compile**

Run: MetaEditor → F7.
Expected: `0 errors, 0 warnings`.

- [ ] **Step 4: Commit**

```bash
git add EA/EA_Signals.mq5
git commit -m "refactor(EA_Signals): remove obsolete fixed-point inputs"
```

---

## Task 7: Demo verification (manual)

Run on a demo account, `MIN_LOG_LEVEL = INFO_LVL`, `API_URL` whitelisted in MT5 (Tools → Options → Expert Advisors → Allow WebRequest).

- [ ] **Check 1: Gate block prints on each signal.** When a signal arrives, the Experts tab shows the block:
  ```
  [INFO] [GATES]   #... <symbol> <dir> | entry=.. SL=.. TP1=.. | R=.. T1=..
  [INFO] [SPREAD]  real=.. | tol=.. (c=0.40*T1) | ..% T1 -> OK
  [INFO] [ORDER]   price=.. | dist=.. (..% T1) | side=FAVORABLE/ADVERSO | k=.. tol=.. -> MARKET/LIMIT/STOP
  [INFO] [STOPS]   broker_min=.. | sl_dist=.. -> OK
  ```
- [ ] **Check 2: Order type matches the side.** On a LONG with price above entry → `BUY_LIMIT`; price below entry → `BUY_STOP`; price within `k·T1` → market. Mirror for SHORT. Confirm against the price shown in `[ORDER]`.
- [ ] **Check 3: Market fill logs slippage.** On a market entry, `[SLIPPAGE] tol=..` prints before send and `[SLIPPAGE] real=.. | pedido=.. fill=..` prints after fill, with `real < tol` in normal conditions.
- [ ] **Check 4: A wide spread rejects.** If you can catch a high-spread moment (or temporarily lower `C_SPREAD_RATIO` to e.g. 0.01 on demo), confirm `[SPREAD] ... -> RECHAZA SPREAD_TOO_HIGH` and the signal closes with reason `SPREAD_TOO_HIGH`. Restore `C_SPREAD_RATIO` after.
- [ ] **Check 5: Cross-asset, no per-symbol config.** Attach the same EA (same ratios) to two different CFD charts (e.g. XAUUSD and US500). Confirm both produce sane `tol` values scaled to their own T1, with no per-chart number changes.

---

## Self-Review

**Spec coverage (Phase A):**
- §3.1 order type k_stop/k_limit·T1 → Task 3. ✅
- §3.2 spread c·T1, real Bid/Ask → Task 2. ✅
- §3.3 slippage m·T1, invariant m<k_limit → Task 4 (deviation = m·T1; ratios ordered 0.05<0.15). ✅
- §3.4 STOPS_LEVEL → Task 4. ✅
- §3.5 four configurable inputs; old ones removed → Tasks 1 & 6. ✅
- §4 MT5 gate logging (tolerated vs real) → SPREAD (T2), ORDER (T3), SLIPPAGE+STOPS (T4), GATES header + real slippage (T5). ✅
- §8 reorder R/T1 before gate calls → Task 2 (t1 computed right after correction). ✅
- Phase B (CSV journal/live) → intentionally out of scope (separate plan). ✅

**Placeholder scan:** no TBD/TODO; every code step shows complete before/after code. ✅

**Type/identifier consistency:** input names `K_STOP_RATIO`, `K_LIMIT_RATIO`, `M_SLIP_RATIO`, `C_SPREAD_RATIO` used identically across Tasks 1-6. `ValidateSpread(userSignalId, t1)` and `DetermineOrderType(opType, entryPrice, t1, currentPrice, orderDecision)` signatures match their updated calls. `t1`, `point`, `stopsMin`, `slDistFinal` declared once each in `ExecuteTrade` scope (no redeclaration: `point` is introduced in Task 4 step 1 and reused by the slippage block in the same step). ✅

**Compile-green ordering:** new inputs added before use (T1), old inputs removed only after all references gone (T6). Each task ends with an F7 compile check. ✅

**Risk note:** `currentPrice` used for `slip_real` in Task 5 is the decision-time price from `DetermineOrderType`, a close approximation of the market order's requested price (CTrade re-reads Ask/Bid at send). Good enough for logging; exact request price is not separately exposed by CTrade.

---

## Execution Handoff

Two execution options:

1. **Subagent-Driven** — fresh subagent per task. Note: subagents cannot compile MQL5 or run the demo; those checks are yours.
2. **Inline Execution (recommended here)** — I make the edits in-session per task; you run F7 between tasks and the demo checklist at the end. Better fit for single-file MQL5 with manual compile/test, same as the reliability plan.

Which approach?
