# EA ↔ Web Sync Reliability — Design Spec

**Date:** 2026-06-12
**Status:** Approved (design phase)
**Scope:** Communication and state-sync between the web/API (CodeIgniter) and the MetaTrader EA (`EA_Signals.mq5`)

---

## 1. Problem & Goal

### The trigger
A `SELL_LIMIT` pending order (signal 1234, symbol GC) was placed and reported to the API
(`ReportOpen` with `order_type: ORDER_TYPE_SELL_LIMIT`). The order later **filled in
MetaTrader**, but the API was **never notified** that it became an open position. The web
state diverged permanently from MT5 reality.

### Root causes (the class of bug we are eliminating)
1. **Fill detection depends on the order comment.** `FindOwnPosition()` matches by
   `magic + symbol + comment == "TelegramSignal"`. Many brokers rewrite/strip the comment
   when a pending order becomes a position → the EA never recognizes its own fill, fails to
   report `now_open`, and may even fire a **false `ORDER_CANCELLED`**.
2. **EA state lives only in memory.** A recompile / EA reload / VPS restart between
   placement and fill runs `InitTPState()` → the EA forgets it had a pending order →
   orphaned position, never reported.
3. **Fire-and-forget reporting.** If a `open/progress/close` POST fails (HTTP error,
   `WebRequest` 4014, network blip), the EA logs an error and **moves on**. The message is
   lost forever and the API state never recovers.
4. **No reconciliation.** Nothing ever re-syncs "what is actually open in MT5" against
   "what the API believes". A lost message stays lost.

### Goal
**Reliability / no lost state.** The API must always converge to reflect what actually
happened in MT5: no `open/progress/close` permanently lost, survival across EA restarts,
and self-correction when state diverges.

### Explicit non-goals (for now)
- **Lower latency** is not the driver (nice side effect, not the point).
- **Multiple concurrent positions** — keep the current single-position-per-EA constraint.
  The snapshot protocol is shaped as a list to keep the door open, but EA execution stays
  one-trade-at-a-time.
- **No change to trading logic** — volume sizing, price correction, TP management,
  breakeven, code-stop all stay exactly as they are. We only change how state is
  **communicated and persisted**.

---

## 2. Decisions (locked in)

| Decision | Choice |
|----------|--------|
| Primary goal | Reliability / never lose state |
| Approach | Redesign the sync: **snapshot + reconciliation** model |
| Positions | **Single** per EA for now; snapshot payload is a **list** (future-proof) |
| Event model | **Hybrid** — periodic heartbeat is source of truth + immediate idempotent events for low latency / realized PnL on close |
| EA structure | **Single file** (`EA_Signals.mq5`), kept clean internally (optimize / simplify / dedup, organized by sections — no `.mqh` split) |
| Backward compat | All changes **additive**; current endpoints keep working |

---

## 3. Core Concept — Three Pillars

Today the source of truth is the EA's in-memory state, pushed via fire-and-forget messages.
We invert that with three pillars.

### Pillar 1 — Durable trade identity (kills the comment dependency)
- Stop relying on `comment == "TelegramSignal"` to recognize our own positions.
- The EA keeps a **persistent local map** in `MQL5/Files` (JSON):
  `position_id → { user_signal_id, levels, volume, SL, tps }`.
- Position identity is `POSITION_IDENTIFIER` (stable across partial closes; a filled pending
  inherits the original order ticket, so it is always findable).
- The order comment becomes a **secondary hint only** (`TS:{signal_id}`), never the source
  of truth. Robust against brokers that alter the comment.

### Pillar 2 — Survive restarts
- On `OnInit`: load the persistent map → enumerate live MT5 positions by magic →
  **rebuild `currentTP`** and re-adopt the active trade.
- No more orphaned positions from recompiling or restarting the VPS.

### Pillar 3 — Heartbeat + reconciliation (self-healing)
- Every N seconds the EA sends a **snapshot** of its real state (positions + pendings with
  level / volume / SL / PnL). The API reconciles to match.
- If an immediate event was lost, the **next heartbeat corrects it automatically**.
- On top: keep immediate `open/progress/close` events, now **idempotent + queued with
  disk-backed retries**, so the UI reacts immediately and close carries real PnL/reason.

```
TODAY:  EA (memory) ──fire&forget──► API        lost message = permanent lie
NEW:    EA (disk)   ──idempotent event + retry──► API   (low latency)
                    ──heartbeat snapshot every N s──► API (self-heals)
        OnInit: EA rebuilds state from MT5 + disk        (survives restarts)
```

---

## 4. Protocol / Contract

All additive and backward-compatible — existing endpoints are extended, not replaced.

### A) Signal claim (poll) — kept, made idempotent
`GET /api/signals/{user_id}/{ticker}` → same as today, response adds a unique **`claim_token`**.
If the EA claims and the ACK is lost, the trade is not duplicated.

### B) Immediate events — kept, now idempotent + sequenced
`POST /api/signals/{id}/open|progress|close` → today's payload **plus two fields**:

```json
{
  "event_id": "unique-uuid-or-hash",
  "seq": 7,
  "...": "rest identical to today"
}
```

The API stores `last_seq` and `last_event_id` per signal. A duplicate or an out-of-order
(older) event **does not overwrite** newer state; the API responds 200 so the EA stops
retrying.

### C) Heartbeat — NEW endpoint (the self-healing core)
`POST /api/ea/{user_id}/heartbeat`

```json
{
  "ea_version": "10.00",
  "ticker": "GC",
  "ts": "2026-06-12T13:14:40Z",
  "positions": [
    {
      "user_signal_id": 1234,
      "position_id": 38288642,
      "kind": "pending",
      "op_type": "SHORT",
      "current_level": -2,
      "remaining_volume": 0.08,
      "current_sl": 4245.49,
      "entry": 4221.31,
      "gross_pnl": 0.0,
      "last_price": 4240.10
    }
  ]
}
```

- `positions` is a **list** (supports N in future; today 0 or 1).
- Response carries reconciliation actions:

```json
{
  "ack": true,
  "alerts": [
    { "type": "orphan_position", "position_id": 99, "msg": "no associated signal" },
    { "type": "stale_signal", "user_signal_id": 555, "msg": "API thinks open but absent from snapshot" }
  ]
}
```

### Close resolution (important)
The heartbeat only lists **what is alive**. When a position disappears from the snapshot the
API knows it closed, but the **realized PnL / exit level / close reason come from the
`close` event** (which reads the history deal). Hence the hybrid: heartbeat detects *that*
it closed, the close event says *how*. If the close event was lost, the API marks the signal
`closed-without-detail` and can request a re-close.

### Liveness
Each heartbeat updates `last_seen`. No heartbeat for > X seconds → the web marks the EA
**offline** (today there is no way to know the EA is down).

**Contract delta summary:** 1 new endpoint (heartbeat) + 2 fields on existing events
(`event_id`, `seq`) + 1 field on the claim (`claim_token`).

---

## 5. EA-side Changes (single file, organized by sections)

Kept in `EA_Signals.mq5`, no `.mqh` split. Internally organized into clear sections,
simplifying and deduplicating where possible. Logical components:

| Component | Responsibility | Replaces / fixes |
|-----------|----------------|------------------|
| **State store** | Persist/load to `MQL5/Files` (JSON): the `position_id → {signal_id, levels, vol, SL, tps}` map **+ the pending-event queue** | In-memory state lost on restart |
| **Position registry** | Enumerate own positions/pendings **by magic**, map to `signal_id` via the state store | `FindOwnPosition()` (comment-dependent) |
| **Report queue** | Durable outbox: every event queued on disk, sent with `event_id`+`seq`, removed on 200, retried on failure | Today's fire-and-forget |
| **Heartbeat** | Every N s build the snapshot from the registry, POST to `/ea/{user}/heartbeat`, process `alerts` | (new) |

### Behavioral fixes
1. **Comment-independent fill detection** — registry matches by `magic + symbol +
   position_id`. Today's bug (filled pending never reported) is caught on the next tick
   **and** the next heartbeat.
2. **Adoption in `OnInit`** — load state → enumerate live MT5 positions → rebuild
   `currentTP` → re-adopt. Kills restart orphans.
3. **Retries** — a non-200 / failed `WebRequest` (the `4014` / HTTP 0 seen in the field)
   leaves the event in the queue, retried next cycle instead of lost.
4. **Idempotency** — each event carries `event_id` (hash of signal_id + type + seq) and a
   persisted incremental `seq`, so a retry never duplicates on the API side.

### Unchanged on purpose
- Trading logic: volume sizing, price correction, TP management, breakeven, code-stop.
- Single-position-at-a-time constraint.

```
OnInit ──► StateStore.load() ──► Registry.adopt() ──► rebuild currentTP
OnTick ──► Registry detects fills/closes ──► ReportQueue.enqueue(event)
OnTimer ──► ReportQueue.flush() (retries)  +  Heartbeat.send() every N s
```

---

## 6. API / Web-side Changes

### Database (all additive)
- **`user_telegram_signals`** → add `last_seq INT`, `last_event_id VARCHAR` (idempotency).
- **`ea_heartbeats`** (new) → `user_id`, `ticker`, `ea_version`, `last_seen`,
  `last_snapshot JSON`. Liveness + last real reported state.
- **`ea_events`** (new, optional) → append-only log of every received event (full
  traceability of what the EA said and when — does not exist today).

### Logic (in an `Ea_sync_model` / reconciliation service)
1. **Reconciler** — given a heartbeat snapshot, for each position update the signal's
   `current_level`, `remaining_volume`, `real_stop_loss`, `gross_pnl`, `last_price` to match
   EA reality. This is what self-heals any lost event.
2. **Divergence detection** —
   - Signal the API believes `open` but **absent** from snapshot → flag for close, request
     `close` detail.
   - Snapshot position with **no** associated signal → orphan alert (visibility instead of
     blindness).
3. **Idempotency guard** — in `report_open/progress/close`: if `seq <= last_seq` or
   `event_id` already seen → ignore and return 200.

### UI / web ("see everything, but better")
- **EA online/offline indicator** per ticker (from `last_seen`).
- **Always-fresh state** — heartbeat reconciliation means `My Trading` reflects real MT5,
  not the last message that happened to survive.
- **"Out-of-sync" badge** when the API detects an unresolved divergence.
- All current info (levels, volume, PnL, SL, corrected vs original prices) stays — now
  reliable, with a "last real update" timestamp.

### Endpoints
- `get_signals` → add `claim_token`.
- `report_open/progress/close` → add idempotency guard (same payload + `event_id`/`seq`).
- `heartbeat` → new; calls the reconciler, returns `alerts`.

---

## 7. Rollout (phased, each phase ships independently)

### Phase 1 — Kill today's bug (urgent)
*No heartbeat yet. Basic reliability only.*
- EA: comment-independent fill detection (`magic + symbol + position_id`).
- EA: state store on disk + **adoption in `OnInit`** → survives restarts.
- EA: report queue with retries + `event_id`/`seq`.
- API: idempotency guard in open/progress/close.
- **Result:** the filled-pending-not-reported bug **can no longer happen**; failed POSTs retry.

### Phase 2 — Self-healing
- EA: heartbeat every N s.
- API: `ea_heartbeats` table + reconciler + divergence detection.
- Web: online/offline indicator + fresh state.
- **Result:** even if something is lost, the next heartbeat fixes it.

### Phase 3 — Polish
- `ea_events` (audit), out-of-sync badges, final EA cleanup/dedup, contract versioning.

---

## 8. Testing

- **API (PHP, genuinely testable):** simulate snapshots + events against the reconciler —
  cases: lost event, duplicate, out-of-order, orphan, stale signal. Solid coverage here.
- **EA (MQL5, manual checklist):** the matrix of cases that broke —
  1. Place pending → restart EA before fill → does it re-adopt and report?
  2. Pending fills with broker-changed comment → is it detected?
  3. Kill network during `close` → does it retry without duplicating?
  4. Manual close from phone → reconciled correctly?
- **Backtest caveat:** `WebRequest` does not run in the Strategy Tester → EA testing is on a
  demo/live account via the Experts tab.

## 9. Risks / Cautions
- The disk state store must be **versioned** (format change → migrate or discard cleanly).
- The reconciler must **never overwrite newer state with an older snapshot** → `seq` always
  wins.
