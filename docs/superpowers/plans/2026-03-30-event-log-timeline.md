# Event Log & Timeline Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a JSON event_log column to user_telegram_signals that accumulates every lifecycle event with real timestamps, then render it as a rich timeline in the trading detail view.

**Architecture:** The EA already sends `execution_time` in every POST. The PHP model will append each event to a JSON array in `event_log` (never overwrite). The view reads the array and renders each event as a timeline step with proper icons, colors, and data. No EA changes needed - the server extracts what it needs from existing payloads.

**Tech Stack:** MySQL (ALTER TABLE), CodeIgniter 3 Model, PHP view, existing Bootstrap 5 timeline CSS.

---

## File Structure

| File | Action | Responsibility |
|------|--------|----------------|
| `application/models/Telegram_signals_model.php` | Modify | Add `append_event()` helper, call it from `claim_user_signal`, `report_open`, `report_progress`, `report_close` |
| `application/views/my_trading/trading_detail.php` | Modify | Replace hardcoded timeline with event_log-driven loop |
| `database/bingx_lite.sql` | Modify | Add `event_log` column definition |

---

### Task 1: Add event_log column to database

**Files:**
- Modify: `database/bingx_lite.sql`
- Run: SQL on local MySQL

- [ ] **Step 1: Run ALTER TABLE on local database**

```sql
ALTER TABLE `user_telegram_signals` ADD `event_log` TEXT NULL DEFAULT NULL AFTER `execution_data`;
```

Run via phpMyAdmin or:
```bash
mysql -u u_bingx -p bingx_lite -e "ALTER TABLE user_telegram_signals ADD event_log TEXT NULL DEFAULT NULL AFTER execution_data;"
```

Expected: Query OK, 0 rows affected.

- [ ] **Step 2: Update the SQL dump file to match**

In `database/bingx_lite.sql`, inside the CREATE TABLE `user_telegram_signals` block, add after the `execution_data` line:

```sql
  `event_log` text DEFAULT NULL COMMENT 'JSON array of lifecycle events with timestamps',
```

- [ ] **Step 3: Commit**

```bash
git add database/bingx_lite.sql
git commit -m "feat: add event_log column to user_telegram_signals"
```

---

### Task 2: Add append_event helper to model

**Files:**
- Modify: `application/models/Telegram_signals_model.php`

The helper reads the current event_log JSON, appends a new event object, and writes it back. Every event has: `event` (type string), `at` (local timestamp), and optional extra fields.

- [ ] **Step 1: Add the append_event private method**

Add after the `convert_utc_to_local()` method (around line 129):

```php
/**
 * Append an event to the event_log JSON array
 * @param int    $user_signal_id
 * @param string $event_type  e.g. 'claimed', 'open', 'tp', 'breakeven', 'filled', 'rejected', 'closed'
 * @param array  $extra       Additional key-value pairs to store with the event
 * @param string|null $utc_time  UTC timestamp from EA (null = use server time)
 */
private function append_event($user_signal_id, $event_type, $extra = [], $utc_time = null)
{
    // Get current log
    $this->db->select('event_log');
    $this->db->where('id', $user_signal_id);
    $row = $this->db->get('user_telegram_signals')->row();

    $log = [];
    if ($row && !empty($row->event_log)) {
        $log = json_decode($row->event_log, true) ?: [];
    }

    // Build event
    $event = ['event' => $event_type];

    // Timestamp: convert UTC from EA or use server local time
    if ($utc_time) {
        $event['at'] = $this->convert_utc_to_local($utc_time);
    } else {
        $event['at'] = date('Y-m-d H:i:s');
    }

    // Merge extra fields
    if (!empty($extra)) {
        $event = array_merge($event, $extra);
    }

    $log[] = $event;

    // Write back
    $this->db->where('id', $user_signal_id);
    $this->db->update('user_telegram_signals', [
        'event_log' => json_encode($log)
    ]);
}
```

- [ ] **Step 2: Commit**

```bash
git add application/models/Telegram_signals_model.php
git commit -m "feat: add append_event helper for event_log"
```

---

### Task 3: Emit events from claim, open, progress, close

**Files:**
- Modify: `application/models/Telegram_signals_model.php`

Each existing function gets ONE `append_event()` call added. The event captures the key data from that step. No other logic changes.

- [ ] **Step 1: Add event to claim_user_signal**

In `claim_user_signal()`, after the `$this->db->update(...)` call (line ~107), add:

```php
if ($result) {
    $this->append_event($user_signal_id, 'claimed');
}
```

Wrap the existing return: change `return $this->db->update(...)` to:

```php
$result = $this->db->update('user_telegram_signals', [
    'status' => 'claimed',
    'updated_at' => date('Y-m-d H:i:s')
]);

if ($result) {
    $this->append_event($user_signal_id, 'claimed');
}

return $result;
```

- [ ] **Step 2: Add event to report_open**

In `report_open()`, after the successful `$this->db->update(...)` call (line ~183), before the `return`, add:

```php
if ($result) {
    $is_market = isset($open_data['order_type']) && in_array($open_data['order_type'], ['ORDER_TYPE_BUY', 'ORDER_TYPE_SELL']);
    $event_extra = ['order_type' => $open_data['order_type'] ?? null];

    if ($is_market) {
        $event_extra['entry'] = $open_data['real_entry_price'] ?? null;
        $event_extra['volume'] = $open_data['real_volume'] ?? null;
        $event_extra['stop_loss'] = $open_data['real_stop_loss'] ?? null;
    }

    $this->append_event(
        $user_signal_id,
        $is_market ? 'open' : 'pending_order',
        $event_extra,
        $open_data['execution_time'] ?? null
    );
}
```

Wrap existing return: change the last two lines to:

```php
$this->db->where('id', $user_signal_id);
$result = $this->db->update('user_telegram_signals', $update_data);

if ($result) {
    $is_market = isset($open_data['order_type']) && in_array($open_data['order_type'], ['ORDER_TYPE_BUY', 'ORDER_TYPE_SELL']);
    $event_extra = ['order_type' => $open_data['order_type'] ?? null];

    if ($is_market) {
        $event_extra['entry'] = $open_data['real_entry_price'] ?? null;
        $event_extra['volume'] = $open_data['real_volume'] ?? null;
        $event_extra['stop_loss'] = $open_data['real_stop_loss'] ?? null;
    }

    $this->append_event(
        $user_signal_id,
        $is_market ? 'open' : 'pending_order',
        $event_extra,
        $open_data['execution_time'] ?? null
    );
}

return $result;
```

- [ ] **Step 3: Add events to report_progress**

In `report_progress()`, after the successful update, add event logic. This function can emit multiple event types:

- `filled` — when pending order executes (now_open=true)
- `tp` — when a TP level is reached (current_level >= 1 and message contains "TP parcial")
- `breakeven` — when SL moved to entry (message contains "Breakeven")

Replace the last three lines of the function:

```php
$this->db->where('id', $user_signal_id);
$result = $this->db->update('user_telegram_signals', $update_data);

if ($result) {
    $utc_time = $progress_data['execution_time'] ?? null;

    // Pending order filled
    if (isset($progress_data['now_open']) && $progress_data['now_open']) {
        $this->append_event($user_signal_id, 'filled', [
            'entry' => $progress_data['real_entry_price'] ?? null,
        ], $utc_time);
    }

    // TP hit (current_level >= 1 and has pnl or volume data)
    $level = $progress_data['current_level'] ?? 0;
    if ($level >= 1 && isset($progress_data['volume_closed_percent']) && $progress_data['volume_closed_percent'] > 0) {
        $this->append_event($user_signal_id, 'tp', [
            'level' => $level,
            'price' => $progress_data['last_price'] ?? null,
            'pnl' => $progress_data['gross_pnl'] ?? null,
            'closed_pct' => $progress_data['volume_closed_percent'] ?? null,
        ], $utc_time);
    }

    // Breakeven
    if (isset($progress_data['new_stop_loss']) && $progress_data['new_stop_loss'] > 0) {
        $this->append_event($user_signal_id, 'breakeven', [
            'new_sl' => $progress_data['new_stop_loss'],
        ], $utc_time);
    }
}

return $result;
```

- [ ] **Step 4: Add event to report_close**

In `report_close()`, after the `$result = $this->db->update(...)` line and before the `insert_closed_trade_to_trades` call, add:

```php
if ($result) {
    $this->append_event($user_signal_id, 'closed', [
        'reason' => $close_data['close_reason'] ?? null,
        'exit_level' => $close_data['exit_level'] ?? null,
        'pnl' => $update_data['gross_pnl'] ?? null,
        'price' => $close_data['last_price'] ?? null,
    ], $close_data['execution_time'] ?? null);
}
```

- [ ] **Step 5: Handle error closes (no report_open happened)**

For signals that go from `claimed` directly to `closed` (INVALID_TPS, PRICE_CORRECTION_ERROR, etc.), `report_close` is called without `report_open`. The event_log will only have `claimed` + `closed`. The close event's `reason` field is enough to distinguish this in the view — no special handling needed.

However, `report_close` may be called when no `claim` event was logged (if `event_log` is NULL from old signals). The `append_event` helper already handles this (starts with empty array).

- [ ] **Step 6: Commit**

```bash
git add application/models/Telegram_signals_model.php
git commit -m "feat: emit events from claim, open, progress, close into event_log"
```

---

### Task 4: Rewrite timeline view to use event_log

**Files:**
- Modify: `application/views/my_trading/trading_detail.php`

Replace the entire timeline `<div class="card-body">` content (the PHP/HTML block between the Timeline card-header and the Charts card) with a loop over `event_log`. Fall back to the current inferred timeline if `event_log` is empty (backwards compat with old signals).

- [ ] **Step 1: Replace the timeline card body**

Find the Timeline card body (the `<div class="card-body">` block that starts with the `$is_market_order` PHP block and ends before `<!-- Charts & Images -->`). Replace the entire `<div class="card-body">...</div>` of the Timeline card with:

```php
            <div class="card-body">
                <?php
                $events = !empty($signal->event_log) ? json_decode($signal->event_log, true) : [];

                // Event rendering config: [icon, marker_class, label]
                $event_config = [
                    'claimed'       => ['fas fa-hand-pointer',     'bg-warning',   'Claimed by EA'],
                    'pending_order' => ['fas fa-clock',            'bg-info',      'Pending Order Placed'],
                    'open'          => ['fas fa-bolt',             'bg-success',   'Position Opened'],
                    'filled'        => ['fas fa-check-circle',     'bg-success',   'Pending Order Filled'],
                    'tp'            => ['fas fa-bullseye',         'bg-success',   'Take Profit'],
                    'breakeven'     => ['fas fa-shield-alt',       'bg-primary',   'Breakeven Activated'],
                    'rejected'      => ['fas fa-exclamation-triangle', 'bg-dark',  'Rejected'],
                    'closed'        => ['fas fa-flag-checkered',   'bg-secondary', 'Closed'],
                ];

                // Close reason display config: [badge_class, human_label]
                $close_reasons = [
                    'CLOSED_COMPLETE'        => ['bg-success',           'All TPs Reached'],
                    'CLOSED_STOPLOSS'        => ['bg-danger',            'Stop Loss Hit'],
                    'CLOSED_CODE_STOP'       => ['bg-danger',            'Code Stop Loss'],
                    'CLOSED_SAFETY_STOP'     => ['bg-danger',            'Safety Stop'],
                    'CLOSED_EXTERNAL'        => ['bg-warning text-dark', 'Manual Close (MT5)'],
                    'ORDER_CANCELLED'        => ['bg-warning text-dark', 'Order Cancelled (MT5)'],
                    'INVALID_TPS'            => ['bg-dark',              'Invalid Take Profits'],
                    'INVALID_STOPLOSS'       => ['bg-dark',              'Invalid Stop Loss'],
                    'PRICE_CORRECTION_ERROR' => ['bg-dark',              'Price Correction Failed'],
                    'SPREAD_TOO_HIGH'        => ['bg-dark',              'Spread Too High'],
                    'VOLUME_ERROR'           => ['bg-dark',              'Volume Calculation Error'],
                    'EXECUTION_FAILED'       => ['bg-dark',              'Broker Rejected Order'],
                ];

                // Order type human labels
                $order_labels = [
                    'ORDER_TYPE_BUY'        => 'Market Buy',
                    'ORDER_TYPE_SELL'       => 'Market Sell',
                    'ORDER_TYPE_BUY_LIMIT'  => 'Buy Limit',
                    'ORDER_TYPE_SELL_LIMIT'  => 'Sell Limit',
                    'ORDER_TYPE_BUY_STOP'   => 'Buy Stop',
                    'ORDER_TYPE_SELL_STOP'   => 'Sell Stop',
                ];
                ?>

                <?php if (!empty($events)): ?>
                <!-- Event log timeline -->
                <div class="timeline">
                    <!-- Signal received (always first, from created_at) -->
                    <div class="timeline-item">
                        <div class="timeline-marker bg-info"></div>
                        <div class="timeline-content">
                            <h6><i class="fas fa-satellite-dish me-1"></i>Signal Received</h6>
                            <small><?= date('M j, Y H:i:s', strtotime($signal->created_at)) ?></small>
                        </div>
                    </div>

                    <?php foreach ($events as $ev):
                        $type = $ev['event'] ?? 'unknown';
                        $cfg = $event_config[$type] ?? ['fas fa-circle', 'bg-secondary', ucfirst($type)];
                        $icon = $cfg[0];
                        $marker = $cfg[1];
                        $label = $cfg[2];
                        $time = isset($ev['at']) ? date('M j, Y H:i:s', strtotime($ev['at'])) : '-';
                    ?>
                    <div class="timeline-item">
                        <div class="timeline-marker <?= $marker ?>"></div>
                        <div class="timeline-content">
                            <?php if ($type === 'open' || $type === 'pending_order'): ?>
                                <?php
                                $ol = isset($ev['order_type'], $order_labels[$ev['order_type']]) ? $order_labels[$ev['order_type']] : $label;
                                ?>
                                <h6><i class="<?= $icon ?> me-1"></i><?= $ol ?> <?= $type === 'open' ? 'Executed' : 'Placed' ?></h6>
                                <small><?= $time ?></small>
                                <?php if (isset($ev['entry'])): ?>
                                    <br><small class="text-muted">Entry: <?= number_format($ev['entry'], $decimals) ?><?php if (isset($ev['volume'])): ?> | Vol: <?= number_format($ev['volume'], 2) ?><?php endif; ?></small>
                                <?php endif; ?>

                            <?php elseif ($type === 'filled'): ?>
                                <h6><i class="<?= $icon ?> me-1"></i><?= $label ?></h6>
                                <small><?= $time ?></small>
                                <?php if (isset($ev['entry'])): ?>
                                    <br><small class="text-muted">Entry: <?= number_format($ev['entry'], $decimals) ?></small>
                                <?php endif; ?>

                            <?php elseif ($type === 'tp'): ?>
                                <h6><i class="<?= $icon ?> me-1"></i>TP<?= $ev['level'] ?? '?' ?> Reached</h6>
                                <small><?= $time ?></small>
                                <br><small class="text-muted">
                                    Price: <?= isset($ev['price']) ? number_format($ev['price'], $decimals) : '-' ?>
                                    <?php if (isset($ev['pnl'])): ?> | PNL: $<?= number_format($ev['pnl'], 2) ?><?php endif; ?>
                                    <?php if (isset($ev['closed_pct'])): ?> | Closed: <?= number_format($ev['closed_pct'], 1) ?>%<?php endif; ?>
                                </small>

                            <?php elseif ($type === 'breakeven'): ?>
                                <h6><i class="<?= $icon ?> me-1"></i><?= $label ?></h6>
                                <small><?= $time ?></small>
                                <?php if (isset($ev['new_sl'])): ?>
                                    <br><small class="text-muted">SL moved to <?= number_format($ev['new_sl'], $decimals) ?></small>
                                <?php endif; ?>

                            <?php elseif ($type === 'closed'): ?>
                                <?php
                                $cr = $ev['reason'] ?? '';
                                $cr_cfg = $close_reasons[$cr] ?? ['bg-secondary', $cr ?: 'Closed'];
                                $cr_badge = $cr_cfg[0];
                                $cr_label = $cr_cfg[1];
                                // Override marker color for close
                                $close_marker = $cr_badge;
                                ?>
                                <h6><i class="<?= $icon ?> me-1"></i>Closed</h6>
                                <small><?= $time ?></small>
                                <br><span class="badge <?= $cr_badge ?>"><?= $cr_label ?></span>
                                <?php if (isset($ev['pnl'])): ?>
                                    <br><small class="<?= $ev['pnl'] >= 0 ? 'text-success' : 'text-danger' ?>">
                                        Final PNL: $<?= number_format(abs($ev['pnl']), 2) ?>
                                    </small>
                                <?php endif; ?>

                            <?php else: ?>
                                <h6><i class="<?= $icon ?> me-1"></i><?= $label ?></h6>
                                <small><?= $time ?></small>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <?php else: ?>
                <!-- Fallback: inferred timeline for old signals without event_log -->
                <?php
                $is_market_order = in_array($signal->order_type, ['ORDER_TYPE_BUY', 'ORDER_TYPE_SELL']);
                $is_pending_order = in_array($signal->order_type, ['ORDER_TYPE_BUY_LIMIT', 'ORDER_TYPE_SELL_LIMIT', 'ORDER_TYPE_BUY_STOP', 'ORDER_TYPE_SELL_STOP']);
                $had_execution = !empty($signal->real_entry_price);
                $error_reasons = ['INVALID_TPS', 'INVALID_STOPLOSS', 'PRICE_CORRECTION_ERROR', 'SPREAD_TOO_HIGH', 'VOLUME_ERROR', 'EXECUTION_FAILED'];
                $is_error_close = in_array($signal->close_reason, $error_reasons);
                $ol = isset($order_labels[$signal->order_type]) ? $order_labels[$signal->order_type] : ($signal->order_type ?: 'Order');
                $cr = $signal->close_reason;
                $cr_cfg = $close_reasons[$cr] ?? ['bg-secondary', $cr ?: 'Closed'];
                ?>
                <div class="timeline">
                    <div class="timeline-item">
                        <div class="timeline-marker bg-info"></div>
                        <div class="timeline-content">
                            <h6>Signal Received</h6>
                            <small><?= date('M j, Y H:i:s', strtotime($signal->created_at)) ?></small>
                        </div>
                    </div>
                    <?php if (in_array($signal->status, ['claimed', 'pending', 'open', 'closed'])): ?>
                    <div class="timeline-item">
                        <div class="timeline-marker bg-warning"></div>
                        <div class="timeline-content">
                            <h6>Claimed by EA</h6>
                            <small><?= $signal->updated_at ? date('M j, Y H:i:s', strtotime($signal->updated_at)) : '-' ?></small>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php if ($signal->status === 'closed' && $is_error_close && !$had_execution): ?>
                    <div class="timeline-item">
                        <div class="timeline-marker <?= $cr_cfg[0] ?>"></div>
                        <div class="timeline-content">
                            <h6><i class="fas fa-exclamation-triangle me-1"></i>Rejected</h6>
                            <small><?= $signal->updated_at ? date('M j, Y H:i:s', strtotime($signal->updated_at)) : '-' ?></small>
                            <br><span class="badge <?= $cr_cfg[0] ?>"><?= $cr_cfg[1] ?></span>
                        </div>
                    </div>
                    <?php elseif (in_array($signal->status, ['open', 'closed']) && $had_execution): ?>
                    <div class="timeline-item">
                        <div class="timeline-marker bg-success"></div>
                        <div class="timeline-content">
                            <h6><?= $ol ?> <?= $is_pending_order ? 'Filled' : 'Executed' ?></h6>
                            <small><?= $signal->updated_at ? date('M j, Y H:i:s', strtotime($signal->updated_at)) : '-' ?></small>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php if ($signal->status === 'closed' && !($is_error_close && !$had_execution)): ?>
                    <div class="timeline-item">
                        <div class="timeline-marker <?= $cr_cfg[0] ?>"></div>
                        <div class="timeline-content">
                            <h6>Closed</h6>
                            <small><?= $signal->updated_at ? date('M j, Y H:i:s', strtotime($signal->updated_at)) : '-' ?></small>
                            <?php if ($cr): ?>
                                <br><span class="badge <?= $cr_cfg[0] ?>"><?= $cr_cfg[1] ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
```

- [ ] **Step 2: Verify $decimals is available**

`$decimals` must be defined before the timeline. Confirm line 1 of the file has:

```php
<?php $decimals = $signal->display_decimals ?? 5; ?>
```

This was already added in the previous session's changes.

- [ ] **Step 3: Commit**

```bash
git add application/views/my_trading/trading_detail.php
git commit -m "feat: event_log-driven timeline with fallback for old signals"
```

---

### Task 5: Ensure event_log is fetched in queries

**Files:**
- Modify: `application/models/Telegram_signals_model.php`

- [ ] **Step 1: Check that get_user_signal_detail includes event_log**

The `get_user_signal_detail()` method (line ~477) uses `SELECT *` or selects `uts.*`, which will include `event_log` automatically since it's a column on `user_telegram_signals`. Verify this by reading the function.

If it uses explicit column names instead of `*`, add `event_log` to the select list.

- [ ] **Step 2: Commit (only if changes were needed)**

```bash
git add application/models/Telegram_signals_model.php
git commit -m "fix: ensure event_log is included in signal detail query"
```

---

### Task 6: Handle TP event deduplication in report_progress

**Files:**
- Modify: `application/models/Telegram_signals_model.php`

The EA calls `ReportProgress` for both TP partial closes AND breakeven. A single TP hit can trigger TWO progress calls in quick succession (ClosePartialPosition → ReportProgress, then SetBreakeven → ReportProgress). The breakeven call has `volume_closed_percent > 0` from the previous state but `gross_pnl = 0.0`.

- [ ] **Step 1: Refine TP event condition**

In the `report_progress` event emission (Task 3, Step 3), the TP event condition should check that `gross_pnl` is non-zero to avoid logging breakeven-only reports as TP events:

```php
// TP hit: level >= 1, has volume data, AND has actual PNL (not just breakeven report)
$level = $progress_data['current_level'] ?? 0;
$pnl = $progress_data['gross_pnl'] ?? 0;
if ($level >= 1 && $pnl != 0 && isset($progress_data['volume_closed_percent']) && $progress_data['volume_closed_percent'] > 0) {
    $this->append_event($user_signal_id, 'tp', [
        'level' => $level,
        'price' => $progress_data['last_price'] ?? null,
        'pnl' => $pnl,
        'closed_pct' => $progress_data['volume_closed_percent'] ?? null,
    ], $utc_time);
}
```

Note: TP1 with `TP1_PERCENT = 0%` does NOT call ClosePartialPosition (no volume to close), so it only triggers breakeven. In this case, only the `breakeven` event is logged — no TP event. This is correct because no volume was closed.

- [ ] **Step 2: Commit**

```bash
git add application/models/Telegram_signals_model.php
git commit -m "fix: prevent duplicate TP events from breakeven-only reports"
```

---

## Event Log Examples

### Market order with TPs and breakeven:
```json
[
  {"event": "claimed", "at": "2026-03-30 14:00:00"},
  {"event": "open", "order_type": "ORDER_TYPE_BUY", "entry": 1.0850, "volume": 0.10, "stop_loss": 1.0800, "at": "2026-03-30 14:00:02"},
  {"event": "breakeven", "new_sl": 1.0850, "at": "2026-03-30 15:30:00"},
  {"event": "tp", "level": 2, "price": 1.0920, "pnl": 28.00, "closed_pct": 40.0, "at": "2026-03-30 16:00:00"},
  {"event": "tp", "level": 3, "price": 1.0950, "pnl": 22.50, "closed_pct": 70.0, "at": "2026-03-30 17:30:00"},
  {"event": "tp", "level": 4, "price": 1.0970, "pnl": 16.00, "closed_pct": 90.0, "at": "2026-03-30 18:00:00"},
  {"event": "tp", "level": 5, "price": 1.0990, "pnl": 10.00, "closed_pct": 100.0, "at": "2026-03-30 19:00:00"},
  {"event": "closed", "reason": "CLOSED_COMPLETE", "exit_level": 5, "pnl": 76.50, "price": 1.0990, "at": "2026-03-30 19:00:01"}
]
```

### Pending order filled then SL hit:
```json
[
  {"event": "claimed", "at": "2026-03-30 14:00:00"},
  {"event": "pending_order", "order_type": "ORDER_TYPE_BUY_LIMIT", "at": "2026-03-30 14:00:02"},
  {"event": "filled", "entry": 1.0830, "at": "2026-03-30 15:45:00"},
  {"event": "breakeven", "new_sl": 1.0830, "at": "2026-03-30 16:30:00"},
  {"event": "closed", "reason": "CLOSED_STOPLOSS", "exit_level": 0, "pnl": -5.00, "price": 1.0830, "at": "2026-03-30 17:00:00"}
]
```

### Error pre-execution (INVALID_TPS):
```json
[
  {"event": "claimed", "at": "2026-03-30 14:00:00"},
  {"event": "closed", "reason": "INVALID_TPS", "exit_level": -998, "pnl": 0, "price": 0, "at": "2026-03-30 14:00:03"}
]
```

### Pending order cancelled manually:
```json
[
  {"event": "claimed", "at": "2026-03-30 14:00:00"},
  {"event": "pending_order", "order_type": "ORDER_TYPE_SELL_LIMIT", "at": "2026-03-30 14:00:02"},
  {"event": "closed", "reason": "ORDER_CANCELLED", "exit_level": -999, "pnl": 0, "price": 0, "at": "2026-03-30 14:05:30"}
]
```
