# Journal Viewer Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** An admin-only web tool in the CodeIgniter 3 app that reads the EA's per-symbol CSV journals + JSON state and renders an overview (cross-symbol stats + charts) plus per-symbol detail (KPIs, charts, full table, live state).

**Architecture:** Two plain PHP libraries — `Journal_reader` (files → arrays) and `Journal_stats` (arrays → aggregates) — kept decoupled and unit-testable. A thin admin-guarded `Journals` controller wires them to two Bootstrap views (`overview`, `detail`) with Chart.js. Read-only; reads a configurable filesystem path.

**Tech Stack:** PHP 7.4, CodeIgniter 3, Bootstrap 5.3 + Font Awesome (existing layout), Chart.js (CDN). Tests = standalone PHP assertion scripts (no PHPUnit).

**Spec:** `docs/superpowers/specs/2026-06-15-journal-viewer-design.md`

---

## File Structure

- Create `application/libraries/Journal_reader.php` — file discovery + CSV/JSON parsing (no stats, no HTML).
- Create `application/libraries/Journal_stats.php` — KPIs, distributions, cumulative-PnL series.
- Create `application/controllers/Journals.php` — admin-guarded; `index()` (overview) + `symbol($sym)` (detail).
- Create `application/views/journals/overview.php` — global KPIs, comparison table, charts.
- Create `application/views/journals/detail.php` — symbol KPIs, state panel, charts, full table.
- Modify `application/config/constants.php` — add `JOURNALS_PATH`.
- Modify `application/config/routes.php` — add `journals` routes.
- Modify `application/views/templates/header.php` — add "Journals" link in the admin System dropdown.
- Create `tests/journals/_helpers.php` — test bootstrap (`BASEPATH`, assert helpers).
- Create `tests/journals/fixtures/` — deterministic sample files.
- Create `tests/journals/test_reader.php`, `tests/journals/test_stats.php` — test scripts.

---

### Task 1: Config constant `JOURNALS_PATH`

**Files:**
- Modify: `application/config/constants.php`

- [ ] **Step 1: Append the constant**

Add at the end of `application/config/constants.php` (before the closing `?>` if present, otherwise at EOF):

```php
/*
| -------------------------------------------------------------------------
| Journal Viewer
| -------------------------------------------------------------------------
| Carpeta donde EA_Signals.mq5 escribe bxlite_journal/live/state.
| Dev (XAMPP): la carpeta EA/journals del repo.
| Prod (Debian + Wine): apuntar a
|   /home/<user>/.mt5/drive_c/Program Files/MetaTrader 5/MQL5/Files/
| (www-data necesita read+traverse; ver spec, sección Permissions).
*/
defined('JOURNALS_PATH') OR define('JOURNALS_PATH', FCPATH . 'EA/journals/');
```

- [ ] **Step 2: Verify it loads**

Run: `php -r "define('BASEPATH',1); define('FCPATH', getcwd().'/'); require 'application/config/constants.php'; echo JOURNALS_PATH, PHP_EOL;"`
Expected: prints a path ending in `EA/journals/` (no PHP errors).

- [ ] **Step 3: Commit**

```bash
git add application/config/constants.php
git commit -m "feat(journals): JOURNALS_PATH config constant"
```

---

### Task 2: Test bootstrap + fixtures

**Files:**
- Create: `tests/journals/_helpers.php`
- Create: `tests/journals/fixtures/bxlite_journal_1_ES.csv`
- Create: `tests/journals/fixtures/bxlite_journal_1_GC.csv`
- Create: `tests/journals/fixtures/bxlite_live_1_ES.csv`
- Create: `tests/journals/fixtures/bxlite_state_1_ES.json`
- Create: `tests/journals/fixtures/bxlite_journal_1_BAD.csv`

- [ ] **Step 1: Create the test helper**

`tests/journals/_helpers.php`:

```php
<?php
// Permite require directo de las libraries (que tienen el guard defined('BASEPATH')).
defined('BASEPATH') OR define('BASEPATH', true);

$GLOBALS['__t'] = array('n' => 0, 'fail' => 0);

function check($cond, $msg) {
    $GLOBALS['__t']['n']++;
    if ($cond) {
        echo "  PASS  $msg\n";
    } else {
        $GLOBALS['__t']['fail']++;
        echo "  FAIL  $msg\n";
    }
}

function check_eq($actual, $expected, $msg) {
    $ok = ($actual === $expected);
    if (!$ok) $msg .= "  [got=" . var_export($actual, true) . " want=" . var_export($expected, true) . "]";
    check($ok, $msg);
}

function done() {
    $t = $GLOBALS['__t'];
    echo "\n{$t['n']} checks, {$t['fail']} failed\n";
    exit($t['fail'] > 0 ? 1 : 0);
}
```

- [ ] **Step 2: Create fixtures**

`tests/journals/fixtures/bxlite_journal_1_ES.csv` (header + one cancelled + one win + one loss):

```
ts_signal,signal_id,symbol,dir,corr_on,corr_factor,entry_raw,sl_raw,entry,sl,tp1,tp2,tp3,tp4,tp5,R,T1,spread_real,spread_tol,price_signal,dist_entry,side,k_band,order_type,real_entry,slip_real,slip_tol,real_volume,stops_min,sl_dist,max_level,vol_closed_pct,be_on,exit_level,close_reason,gross_pnl,last_price,result
2026-06-15T13:03:42,5,ES,LONG,1,1.008784,7591.5,7544.75,7525.39,7479.05,7532.58,7541.0,7550.92,7560.33,7571.98,46.34,7.18,0.7,0.35,7526.9,1.5,ADVERSO,0.71,LIMIT,7525.4,0,0,4.33,0.5,46.3,-2,0.0,0,-999,ORDER_CANCELLED,0.0,0.0,ORDER_CANCELLED
2026-06-15T14:00:00,8,ES,LONG,1,1.0,7500.0,7480.0,7500.0,7480.0,7520.0,7530.0,7540.0,7550.0,7560.0,20.0,20.0,0.5,0.3,7499.0,1.0,ADVERSO,0.5,MARKET,7500.0,0.1,0.2,2.0,0.5,20.0,3,70.0,1,3,CLOSED_COMPLETE,150.0,7540.0,CLOSED_COMPLETE
2026-06-15T15:00:00,9,ES,SHORT,1,1.0,7600.0,7620.0,7600.0,7620.0,7580.0,7570.0,7560.0,7550.0,7540.0,20.0,20.0,0.5,0.3,7601.0,1.0,FAVORABLE,0.5,STOP,7600.0,0.1,0.2,2.0,0.5,20.0,0,0.0,0,-1,CLOSED_STOPLOSS,-80.0,7620.0,CLOSED_STOPLOSS
```

`tests/journals/fixtures/bxlite_journal_1_GC.csv` (header + one win):

```
ts_signal,signal_id,symbol,dir,corr_on,corr_factor,entry_raw,sl_raw,entry,sl,tp1,tp2,tp3,tp4,tp5,R,T1,spread_real,spread_tol,price_signal,dist_entry,side,k_band,order_type,real_entry,slip_real,slip_tol,real_volume,stops_min,sl_dist,max_level,vol_closed_pct,be_on,exit_level,close_reason,gross_pnl,last_price,result
2026-06-15T12:00:00,3,GC,LONG,1,1.0,4300.0,4290.0,4300.0,4290.0,4310.0,4320.0,4330.0,4340.0,4350.0,10.0,10.0,0.4,0.6,4299.0,1.0,ADVERSO,0.5,LIMIT,4300.0,0.0,0.0,0.5,0.03,10.0,5,100.0,1,5,CLOSED_COMPLETE,40.0,4350.0,CLOSED_COMPLETE
```

`tests/journals/fixtures/bxlite_live_1_ES.csv` (header only — no live trade):

```
ts_signal,signal_id,symbol,dir,corr_on,corr_factor,entry_raw,sl_raw,entry,sl,tp1,tp2,tp3,tp4,tp5,R,T1,spread_real,spread_tol,price_signal,dist_entry,side,k_band,order_type,real_entry,slip_real,slip_tol,real_volume,stops_min,sl_dist,max_level,vol_closed_pct,be_on,exit_level,close_reason,gross_pnl,last_price,result
```

`tests/journals/fixtures/bxlite_state_1_ES.json`:

```
{"isActive":true,"signalId":4,"ticket":"38346632","positionID":"38346632","direction":"SHORT","originalVolume":0.10,"currentVolume":0.10,"totalClosedVolume":0.00,"closedPercent":0.00,"currentLevel":0,"slMovedToBE":false,"entry":80.927,"originalSL":82.42557,"currentSL":82.426,"tp1":80.47843,"tp2":79.92354,"tp3":79.37875,"tp4":78.7936,"tp5":78.08738,"levelFlags":[0,0,0,0,0,0],"levelVolumes":[0.0,0.0,0.10,0.0,0.0,0.0]}
```

`tests/journals/fixtures/bxlite_journal_1_BAD.csv` (header + a short row that must be skipped + a valid row):

```
ts_signal,signal_id,symbol,dir,corr_on,corr_factor,entry_raw,sl_raw,entry,sl,tp1,tp2,tp3,tp4,tp5,R,T1,spread_real,spread_tol,price_signal,dist_entry,side,k_band,order_type,real_entry,slip_real,slip_tol,real_volume,stops_min,sl_dist,max_level,vol_closed_pct,be_on,exit_level,close_reason,gross_pnl,last_price,result
2026-06-15T16:00:00,10,BAD,LONG,1,1.0,short,row
2026-06-15T16:30:00,11,BAD,LONG,1,1.0,1.0,0.9,1.0,0.9,1.1,1.2,1.3,1.4,1.5,0.1,0.1,0.01,0.01,0.99,0.01,ADVERSO,0.05,LIMIT,1.0,0,0,1.0,0.0,0.1,0,0.0,0,0,OPEN,0.0,0.0,OPEN
```

- [ ] **Step 3: Commit**

```bash
git add tests/journals/_helpers.php tests/journals/fixtures
git commit -m "test(journals): test bootstrap + fixtures"
```

---

### Task 3: `Journal_reader::parse_filename`

**Files:**
- Create: `application/libraries/Journal_reader.php`
- Test: `tests/journals/test_reader.php`

- [ ] **Step 1: Write the failing test**

`tests/journals/test_reader.php`:

```php
<?php
require __DIR__ . '/_helpers.php';
require __DIR__ . '/../../application/libraries/Journal_reader.php';

$r = new Journal_reader(array('path' => __DIR__ . '/fixtures'));

// parse_filename
check_eq($r->parse_filename('bxlite_journal_1_ES.csv'),
    array('kind' => 'journal', 'user_id' => 1, 'symbol' => 'ES'), 'parse journal name');
check_eq($r->parse_filename('bxlite_state_1_EURUSD.json'),
    array('kind' => 'state', 'user_id' => 1, 'symbol' => 'EURUSD'), 'parse state name');
check_eq($r->parse_filename('bxlite_live_2_GC.csv'),
    array('kind' => 'live', 'user_id' => 2, 'symbol' => 'GC'), 'parse live name');
check_eq($r->parse_filename('get.sh'), null, 'non-journal name -> null');
check_eq($r->parse_filename('bxlite_journal_1_BTC-USD.csv'),
    array('kind' => 'journal', 'user_id' => 1, 'symbol' => 'BTC-USD'), 'symbol with dash');

done();
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/journals/test_reader.php`
Expected: FAIL/fatal — `Class 'Journal_reader' not found`.

- [ ] **Step 3: Create the class with constructor + parse_filename**

`application/libraries/Journal_reader.php`:

```php
<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Lee los archivos de journal/live/state que escribe EA_Signals.mq5 y los
 * convierte en arrays PHP. Sin stats ni HTML: solo archivos -> arrays.
 */
class Journal_reader {

    private $basePath;

    public function __construct($params = array()) {
        $path = isset($params['path']) ? $params['path']
              : (defined('JOURNALS_PATH') ? JOURNALS_PATH : '');
        $this->basePath = rtrim($path, "/\\") . DIRECTORY_SEPARATOR;
    }

    public function base_path() { return $this->basePath; }

    public function is_readable_dir() {
        return is_dir($this->basePath) && is_readable($this->basePath);
    }

    /**
     * Parsea bxlite_<kind>_<uid>_<symbol>.<ext>.
     * Devuelve ['kind'=>, 'user_id'=>int, 'symbol'=>] o null.
     */
    public function parse_filename($filename) {
        if (!preg_match('/^bxlite_(journal|live|state)_(\d+)_(.+)\.(csv|json)$/', $filename, $m)) {
            return null;
        }
        return array('kind' => $m[1], 'user_id' => (int)$m[2], 'symbol' => $m[3]);
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php tests/journals/test_reader.php`
Expected: all PASS, exit 0.

- [ ] **Step 5: Commit**

```bash
git add application/libraries/Journal_reader.php tests/journals/test_reader.php
git commit -m "feat(journals): Journal_reader.parse_filename"
```

---

### Task 4: `Journal_reader::parse_csv`

**Files:**
- Modify: `application/libraries/Journal_reader.php`
- Test: `tests/journals/test_reader.php`

- [ ] **Step 1: Add failing tests**

Append before `done();` in `tests/journals/test_reader.php`:

```php
// parse_csv
$es = $r->parse_csv(__DIR__ . '/fixtures/bxlite_journal_1_ES.csv');
check_eq(count($es), 3, 'ES journal has 3 rows');
check_eq($es[0]['signal_id'], 5, 'signal_id cast to int');
check_eq($es[0]['close_reason'], 'ORDER_CANCELLED', 'close_reason string');
check_eq($es[1]['gross_pnl'], 150.0, 'gross_pnl cast to float');
check_eq($es[0]['ts_signal'], '2026-06-15T13:03:42', 'timestamp stays string');

// short row skipped, valid kept
$bad = $r->parse_csv(__DIR__ . '/fixtures/bxlite_journal_1_BAD.csv');
check_eq(count($bad), 1, 'short row skipped, 1 valid row kept');
check_eq($bad[0]['signal_id'], 11, 'valid row is the second one');

// header-only file -> 0 rows
$live = $r->parse_csv(__DIR__ . '/fixtures/bxlite_live_1_ES.csv');
check_eq(count($live), 0, 'header-only live -> 0 rows');

// missing file -> 0 rows, no fatal
check_eq(count($r->parse_csv(__DIR__ . '/fixtures/nope.csv')), 0, 'missing file -> 0 rows');
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/journals/test_reader.php`
Expected: FAIL/fatal — `Call to undefined method Journal_reader::parse_csv()`.

- [ ] **Step 3: Add parse_csv + cast**

Insert these methods inside the `Journal_reader` class (before the closing `}`):

```php
    /**
     * Parsea un CSV: 1ra línea header, cada fila siguiente -> assoc por nombre.
     * Saltea filas cuyo nº de campos != header. Castea numéricos.
     */
    public function parse_csv($filepath) {
        $rows = array();
        $raw = @file_get_contents($filepath);
        if ($raw === false || trim($raw) === '') return $rows;
        $lines = preg_split('/\r\n|\r|\n/', trim($raw));
        if (count($lines) < 2) return $rows;
        $header = str_getcsv($lines[0]);
        $ncol = count($header);
        for ($i = 1; $i < count($lines); $i++) {
            if (trim($lines[$i]) === '') continue;
            $fields = str_getcsv($lines[$i]);
            if (count($fields) !== $ncol) continue;
            $row = array();
            foreach ($header as $c => $name) {
                $row[$name] = $this->cast($fields[$c]);
            }
            $rows[] = $row;
        }
        return $rows;
    }

    private function cast($v) {
        if (is_numeric($v)) {
            return ($v == (int)$v && strpos($v, '.') === false) ? (int)$v : (float)$v;
        }
        return $v;
    }
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php tests/journals/test_reader.php`
Expected: all PASS, exit 0.

- [ ] **Step 5: Commit**

```bash
git add application/libraries/Journal_reader.php tests/journals/test_reader.php
git commit -m "feat(journals): Journal_reader.parse_csv (header-mapped, skips short rows)"
```

---

### Task 5: `Journal_reader` discovery + read_journal/live/state

**Files:**
- Modify: `application/libraries/Journal_reader.php`
- Test: `tests/journals/test_reader.php`

- [ ] **Step 1: Add failing tests**

Append before `done();` in `tests/journals/test_reader.php`:

```php
// list_symbols (union across kinds; BAD only has journal)
check_eq($r->list_symbols(), array('BAD', 'ES', 'GC'), 'list_symbols sorted union');

// read_journal merges + adds user_id
$esj = $r->read_journal('ES');
check_eq(count($esj), 3, 'read_journal ES = 3 rows');
check_eq($esj[0]['user_id'], 1, 'read_journal adds user_id');
check_eq(count($r->read_journal('GC')), 1, 'read_journal GC = 1 row');
check_eq(count($r->read_journal('NONE')), 0, 'unknown symbol -> 0 rows');

// read_live: ES live is header-only -> null
check_eq($r->read_live('ES'), null, 'header-only live -> null');

// read_state: ES has a state json
$st = $r->read_state('ES');
check_eq(is_array($st), true, 'read_state returns array');
check_eq($st['signalId'], 4, 'state signalId');
check_eq($st['levelVolumes'][2], 0.10, 'state levelVolumes parsed');
check_eq($r->read_state('GC'), null, 'no state -> null');
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/journals/test_reader.php`
Expected: FAIL/fatal — `Call to undefined method Journal_reader::list_symbols()`.

- [ ] **Step 3: Add discovery + readers**

Insert these methods inside the `Journal_reader` class (before the closing `}`):

```php
    /** Metadata de todos los archivos reconocidos. */
    private function all_files() {
        $out = array();
        if (!$this->is_readable_dir()) return $out;
        foreach (scandir($this->basePath) as $f) {
            $info = $this->parse_filename($f);
            if ($info !== null) {
                $info['file'] = $this->basePath . $f;
                $out[] = $info;
            }
        }
        return $out;
    }

    /** Símbolos presentes (union de journal/live/state), ordenados. */
    public function list_symbols() {
        $symbols = array();
        foreach ($this->all_files() as $info) $symbols[$info['symbol']] = true;
        $out = array_keys($symbols);
        sort($out);
        return $out;
    }

    /** Filas de todos los bxlite_journal_*_<symbol>.csv, con user_id por fila. */
    public function read_journal($symbol) {
        $rows = array();
        foreach ($this->all_files() as $info) {
            if ($info['kind'] !== 'journal' || $info['symbol'] !== $symbol) continue;
            foreach ($this->parse_csv($info['file']) as $row) {
                $row['user_id'] = $info['user_id'];
                $rows[] = $row;
            }
        }
        return $rows;
    }

    /** Fila viva del live csv, o null. */
    public function read_live($symbol) {
        foreach ($this->all_files() as $info) {
            if ($info['kind'] !== 'live' || $info['symbol'] !== $symbol) continue;
            $rows = $this->parse_csv($info['file']);
            if (count($rows) > 0) {
                $rows[0]['user_id'] = $info['user_id'];
                return $rows[0];
            }
        }
        return null;
    }

    /** State json decodificado, o null si no existe / no parseable. */
    public function read_state($symbol) {
        foreach ($this->all_files() as $info) {
            if ($info['kind'] !== 'state' || $info['symbol'] !== $symbol) continue;
            $raw = @file_get_contents($info['file']);
            if ($raw === false) return null;
            $data = json_decode($raw, true);
            return is_array($data) ? $data : null;
        }
        return null;
    }
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php tests/journals/test_reader.php`
Expected: all PASS, exit 0.

- [ ] **Step 5: Commit**

```bash
git add application/libraries/Journal_reader.php tests/journals/test_reader.php
git commit -m "feat(journals): Journal_reader discovery + read_journal/live/state"
```

---

### Task 6: `Journal_stats` classification (operated/win/cancelled)

**Files:**
- Create: `application/libraries/Journal_stats.php`
- Test: `tests/journals/test_stats.php`

- [ ] **Step 1: Write the failing test**

`tests/journals/test_stats.php`:

```php
<?php
require __DIR__ . '/_helpers.php';
require __DIR__ . '/../../application/libraries/Journal_stats.php';

$s = new Journal_stats();

$cancelled = array('close_reason' => 'ORDER_CANCELLED', 'gross_pnl' => 0.0);
$win       = array('close_reason' => 'CLOSED_COMPLETE', 'gross_pnl' => 150.0);
$loss      = array('close_reason' => 'CLOSED_STOPLOSS', 'gross_pnl' => -80.0);
$failed    = array('close_reason' => 'EXECUTION_FAILED', 'gross_pnl' => 0.0);

check_eq($s->is_operated($cancelled), false, 'cancelled not operated');
check_eq($s->is_operated($failed), false, 'failed not operated');
check_eq($s->is_operated($win), true, 'win operated');
check_eq($s->is_cancelled($cancelled), true, 'is_cancelled');
check_eq($s->is_cancelled($win), false, 'win not cancelled');
check_eq($s->is_win($win), true, 'win is win');
check_eq($s->is_win($loss), false, 'loss not win');
check_eq($s->is_win($cancelled), false, 'cancelled not win');

done();
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/journals/test_stats.php`
Expected: FAIL/fatal — `Class 'Journal_stats' not found`.

- [ ] **Step 3: Create the class with classification**

`application/libraries/Journal_stats.php`:

```php
<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Calcula KPIs/agregados a partir de filas de journal ya parseadas (arrays -> stats).
 */
class Journal_stats {

    // close_reason que significan "nunca operó" (espejo de is_failure_reason del backend).
    private $notOperated = array(
        'ORDER_CANCELLED', 'INVALID_STOPLOSS', 'INVALID_TPS', 'INVALID_OPTYPE',
        'INVALID_ENTRY', 'PRICE_CORRECTION_ERROR', 'SPREAD_TOO_HIGH', 'VOLUME_ERROR',
        'SL_TOO_CLOSE', 'EXECUTION_FAILED'
    );

    public function __construct($params = array()) {}

    private function val($row, $k, $def = 0) {
        return isset($row[$k]) ? $row[$k] : $def;
    }

    public function is_operated($row) {
        return !in_array((string)$this->val($row, 'close_reason', ''), $this->notOperated, true);
    }

    public function is_cancelled($row) {
        return (string)$this->val($row, 'close_reason', '') === 'ORDER_CANCELLED';
    }

    public function is_win($row) {
        return $this->is_operated($row) && (float)$this->val($row, 'gross_pnl', 0) > 0;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php tests/journals/test_stats.php`
Expected: all PASS, exit 0.

- [ ] **Step 5: Commit**

```bash
git add application/libraries/Journal_stats.php tests/journals/test_stats.php
git commit -m "feat(journals): Journal_stats trade classification"
```

---

### Task 7: `Journal_stats::per_symbol`

**Files:**
- Modify: `application/libraries/Journal_stats.php`
- Test: `tests/journals/test_stats.php`

- [ ] **Step 1: Add failing tests**

Append before `done();` in `tests/journals/test_stats.php`:

```php
$rows = array(
    array('close_reason'=>'ORDER_CANCELLED','gross_pnl'=>0.0,'order_type'=>'LIMIT','exit_level'=>-999,'dist_entry'=>1.5,'t1'=>7.0),
    array('close_reason'=>'CLOSED_COMPLETE','gross_pnl'=>150.0,'order_type'=>'MARKET','exit_level'=>3,'dist_entry'=>1.0,'t1'=>20.0),
    array('close_reason'=>'CLOSED_STOPLOSS','gross_pnl'=>-80.0,'order_type'=>'STOP','exit_level'=>-1,'dist_entry'=>1.0,'t1'=>20.0),
);
$k = $s->per_symbol($rows);
check_eq($k['total'], 3, 'total 3');
check_eq($k['operated'], 2, 'operated 2');
check_eq($k['cancelled'], 1, 'cancelled 1');
check_eq($k['wins'], 1, 'wins 1');
check_eq($k['losses'], 1, 'losses 1');
check_eq($k['win_rate'], 50.0, 'win_rate 50');
check_eq($k['pnl_total'], 70.0, 'pnl_total 70');
check_eq($k['pnl_avg'], 35.0, 'pnl_avg 35 (over operated)');
check_eq($k['order_types']['LIMIT'], 1, 'order_types count');
check_eq($k['exit_levels']['3'], 1, 'exit_levels count keyed by string');

// zero trades -> guarded
$z = $s->per_symbol(array());
check_eq($z['win_rate'], null, 'win_rate null on 0 operated');
check_eq($z['pnl_total'], 0.0, 'pnl_total 0 on empty');
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/journals/test_stats.php`
Expected: FAIL/fatal — `Call to undefined method Journal_stats::per_symbol()`.

- [ ] **Step 3: Add per_symbol**

Insert inside the `Journal_stats` class (before the closing `}`):

```php
    /** KPIs de un conjunto de filas (un símbolo). */
    public function per_symbol($rows) {
        $total = count($rows);
        $operated = 0; $cancelled = 0; $wins = 0; $losses = 0;
        $pnl = 0.0; $sumDist = 0.0; $sumT1 = 0.0;
        $orderTypes = array(); $exitLevels = array();
        foreach ($rows as $row) {
            $ot = (string)$this->val($row, 'order_type', '');
            $el = (string)$this->val($row, 'exit_level', '');
            if ($ot !== '') $orderTypes[$ot] = (isset($orderTypes[$ot]) ? $orderTypes[$ot] : 0) + 1;
            $exitLevels[$el] = (isset($exitLevels[$el]) ? $exitLevels[$el] : 0) + 1;
            $sumDist += (float)$this->val($row, 'dist_entry', 0);
            $sumT1   += (float)$this->val($row, 't1', 0);
            if ($this->is_cancelled($row)) $cancelled++;
            if ($this->is_operated($row)) {
                $operated++;
                $pnl += (float)$this->val($row, 'gross_pnl', 0);
                if ($this->is_win($row)) $wins++; else $losses++;
            }
        }
        return array(
            'total'          => $total,
            'operated'       => $operated,
            'cancelled'      => $cancelled,
            'wins'           => $wins,
            'losses'         => $losses,
            'win_rate'       => $operated > 0 ? round($wins / $operated * 100, 1) : null,
            'pnl_total'      => round($pnl, 2),
            'pnl_avg'        => $operated > 0 ? round($pnl / $operated, 2) : 0.0,
            'avg_dist_entry' => $total > 0 ? round($sumDist / $total, 5) : 0.0,
            'avg_t1'         => $total > 0 ? round($sumT1 / $total, 5) : 0.0,
            'order_types'    => $orderTypes,
            'exit_levels'    => $exitLevels,
        );
    }
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php tests/journals/test_stats.php`
Expected: all PASS, exit 0.

- [ ] **Step 5: Commit**

```bash
git add application/libraries/Journal_stats.php tests/journals/test_stats.php
git commit -m "feat(journals): Journal_stats.per_symbol KPIs"
```

---

### Task 8: `Journal_stats` cumulative_pnl + distribution

**Files:**
- Modify: `application/libraries/Journal_stats.php`
- Test: `tests/journals/test_stats.php`

- [ ] **Step 1: Add failing tests**

Append before `done();` in `tests/journals/test_stats.php`:

```php
$series = array(
    array('close_reason'=>'CLOSED_COMPLETE','gross_pnl'=>10.0,'ts_signal'=>'2026-06-15T10:00:00'),
    array('close_reason'=>'ORDER_CANCELLED','gross_pnl'=>0.0,'ts_signal'=>'2026-06-15T09:00:00'),
    array('close_reason'=>'CLOSED_STOPLOSS','gross_pnl'=>-4.0,'ts_signal'=>'2026-06-15T11:00:00'),
);
$cum = $s->cumulative_pnl($series);
check_eq(count($cum), 2, 'cumulative skips not-operated (2 points)');
check_eq($cum[0]['cum'], 10.0, 'first cum = 10 (sorted by ts)');
check_eq($cum[1]['cum'], 6.0, 'second cum = 6');

$dist = $s->distribution($series, 'close_reason');
check_eq($dist['CLOSED_COMPLETE'], 1, 'distribution counts CLOSED_COMPLETE');
check_eq($dist['ORDER_CANCELLED'], 1, 'distribution counts ORDER_CANCELLED');
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/journals/test_stats.php`
Expected: FAIL/fatal — `Call to undefined method Journal_stats::cumulative_pnl()`.

- [ ] **Step 3: Add cumulative_pnl + distribution**

Insert inside the `Journal_stats` class (before the closing `}`):

```php
    /** PnL acumulado de trades operados, ordenado por ts_signal. [['ts'=>,'cum'=>],...]. */
    public function cumulative_pnl($rows) {
        $op = array();
        foreach ($rows as $row) if ($this->is_operated($row)) $op[] = $row;
        usort($op, function($a, $b) {
            return strcmp((string)(isset($a['ts_signal'])?$a['ts_signal']:''),
                          (string)(isset($b['ts_signal'])?$b['ts_signal']:''));
        });
        $out = array(); $cum = 0.0;
        foreach ($op as $row) {
            $cum += (float)$this->val($row, 'gross_pnl', 0);
            $out[] = array('ts' => (string)$this->val($row, 'ts_signal', ''), 'cum' => round($cum, 2));
        }
        return $out;
    }

    /** Conteo por valor de un campo. */
    public function distribution($rows, $field) {
        $out = array();
        foreach ($rows as $row) {
            $kk = (string)$this->val($row, $field, '');
            $out[$kk] = (isset($out[$kk]) ? $out[$kk] : 0) + 1;
        }
        return $out;
    }
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php tests/journals/test_stats.php`
Expected: all PASS, exit 0.

- [ ] **Step 5: Commit**

```bash
git add application/libraries/Journal_stats.php tests/journals/test_stats.php
git commit -m "feat(journals): Journal_stats cumulative_pnl + distribution"
```

---

### Task 9: Routes + admin menu link

**Files:**
- Modify: `application/config/routes.php`
- Modify: `application/views/templates/header.php`

- [ ] **Step 1: Add routes**

In `application/config/routes.php`, add these lines just after the `$route['default_controller']` line (before any catch-all `(:any)` routes):

```php
$route['journals'] = 'journals/index';
$route['journals/symbol/(:any)'] = 'journals/symbol/$1';
```

- [ ] **Step 2: Add the menu link**

In `application/views/templates/header.php`, inside the System dropdown `<ul class="dropdown-menu">` (the one opened right after the "System" toggle, near the System Logs item), add this `<li>` right after the System Logs item:

```php
                                    <li><a class="dropdown-item" href="<?= base_url('journals') ?>">
                                            <i class="fas fa-table me-2"></i>Journals
                                        </a></li>
```

- [ ] **Step 3: Verify routing resolves (after controller exists this returns the page; for now just syntax)**

Run: `php -l application/config/routes.php && php -l application/views/templates/header.php`
Expected: `No syntax errors detected` for both.

- [ ] **Step 4: Commit**

```bash
git add application/config/routes.php application/views/templates/header.php
git commit -m "feat(journals): routes + admin System menu link"
```

---

### Task 10: `Journals` controller

**Files:**
- Create: `application/controllers/Journals.php`

- [ ] **Step 1: Create the controller**

`application/controllers/Journals.php`:

```php
<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Journals extends CI_Controller {

    public function __construct() {
        parent::__construct();
        if (!$this->session->userdata('logged_in')) redirect('auth');
        if ($this->session->userdata('role') !== 'admin') show_error('Acceso denegado', 403);
        $this->load->library('journal_reader');
        $this->load->library('journal_stats');
    }

    public function index() {
        $data['title']    = 'Journals';
        $data['readable'] = $this->journal_reader->is_readable_dir();
        $data['path']     = $this->journal_reader->base_path();
        $data['symbols']  = array();
        $data['global']   = $this->empty_global();
        $data['chart']    = array('pnl_by_symbol' => array(), 'order_types' => array(),
                                  'exit_levels' => array(), 'cum' => array());

        if ($data['readable']) {
            $allRows = array();
            foreach ($this->journal_reader->list_symbols() as $sym) {
                $rows = $this->journal_reader->read_journal($sym);
                if (count($rows) === 0) continue;
                $k = $this->journal_stats->per_symbol($rows);
                $k['symbol'] = $sym;
                $data['symbols'][] = $k;
                $data['chart']['pnl_by_symbol'][$sym] = $k['pnl_total'];
                $allRows = array_merge($allRows, $rows);
            }
            $data['global'] = $this->aggregate_global($data['symbols']);
            $data['chart']['order_types'] = $this->journal_stats->distribution($allRows, 'order_type');
            $data['chart']['exit_levels'] = $this->journal_stats->distribution($allRows, 'exit_level');
            $data['chart']['cum']         = $this->journal_stats->cumulative_pnl($allRows);
        }

        $this->load->view('templates/header', $data);
        $this->load->view('journals/overview', $data);
        $this->load->view('templates/footer');
    }

    public function symbol($sym = '') {
        $sym = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $sym)); // whitelist anti path-traversal
        if ($sym === '') redirect('journals');

        $rows  = $this->journal_reader->read_journal($sym);
        $state = $this->journal_reader->read_state($sym);
        $live  = $this->journal_reader->read_live($sym);

        if (count($rows) === 0 && $state === null && $live === null) {
            $this->session->set_flashdata('warning', "No hay datos para el símbolo $sym");
            redirect('journals');
        }

        $data['title']  = "Journal $sym";
        $data['symbol'] = $sym;
        $data['kpi']    = $this->journal_stats->per_symbol($rows);
        $data['rows']   = $rows;
        $data['state']  = $state;
        $data['live']   = $live;
        $data['chart']  = array(
            'cum'         => $this->journal_stats->cumulative_pnl($rows),
            'scatter'     => $this->scatter_data($rows),
            'exit_levels' => $this->journal_stats->distribution($rows, 'exit_level'),
        );

        $this->load->view('templates/header', $data);
        $this->load->view('journals/detail', $data);
        $this->load->view('templates/footer');
    }

    private function scatter_data($rows) {
        $out = array();
        foreach ($rows as $r) {
            $out[] = array(
                'x'    => isset($r['dist_entry']) ? (float)$r['dist_entry'] : 0,
                'y'    => isset($r['t1']) ? (float)$r['t1'] : 0,
                'type' => isset($r['order_type']) ? (string)$r['order_type'] : '',
                'pnl'  => isset($r['gross_pnl']) ? (float)$r['gross_pnl'] : 0,
            );
        }
        return $out;
    }

    private function empty_global() {
        return array('total'=>0,'operated'=>0,'cancelled'=>0,'wins'=>0,'losses'=>0,
                     'win_rate'=>null,'pnl_total'=>0.0,'cancel_rate'=>null);
    }

    private function aggregate_global($symbols) {
        $g = $this->empty_global();
        foreach ($symbols as $k) {
            $g['total']     += $k['total'];
            $g['operated']  += $k['operated'];
            $g['cancelled'] += $k['cancelled'];
            $g['wins']      += $k['wins'];
            $g['losses']    += $k['losses'];
            $g['pnl_total'] += $k['pnl_total'];
        }
        $g['pnl_total']   = round($g['pnl_total'], 2);
        $g['win_rate']    = $g['operated'] > 0 ? round($g['wins'] / $g['operated'] * 100, 1) : null;
        $g['cancel_rate'] = $g['total'] > 0 ? round($g['cancelled'] / $g['total'] * 100, 1) : null;
        return $g;
    }
}
```

- [ ] **Step 2: Lint**

Run: `php -l application/controllers/Journals.php`
Expected: `No syntax errors detected`.

- [ ] **Step 3: Commit**

```bash
git add application/controllers/Journals.php
git commit -m "feat(journals): Journals controller (overview + symbol detail)"
```

---

### Task 11: Overview view

**Files:**
- Create: `application/views/journals/overview.php`

- [ ] **Step 1: Create the view**

`application/views/journals/overview.php`:

```php
<div class="d-flex justify-content-between align-items-center mb-3">
    <h2><i class="fas fa-table me-2"></i>Journals</h2>
</div>

<?php if (!$readable): ?>
    <div class="alert alert-warning">
        No se puede leer la carpeta de journals:<br><code><?= htmlspecialchars($path) ?></code><br>
        Revisá <code>JOURNALS_PATH</code> y los permisos de lectura de www-data.
    </div>
<?php elseif (count($symbols) === 0): ?>
    <div class="alert alert-info">No hay journals todavía en <code><?= htmlspecialchars($path) ?></code>.</div>
<?php else: ?>

    <div class="row">
        <?php
        $cards = array(
            array('Señales', $global['total'], ''),
            array('Operadas', $global['operated'], ''),
            array('Canceladas', ($global['cancel_rate'] === null ? '—' : $global['cancel_rate'].'%'), ''),
            array('Win rate', ($global['win_rate'] === null ? '—' : $global['win_rate'].'%'), ''),
            array('PnL total', number_format($global['pnl_total'], 2), $global['pnl_total'] >= 0 ? 'text-profit' : 'text-loss'),
        );
        foreach ($cards as $c): ?>
            <div class="col">
                <div class="card text-center"><div class="card-body">
                    <div class="text-muted small"><?= $c[0] ?></div>
                    <div class="h4 <?= $c[2] ?>"><?= $c[1] ?></div>
                </div></div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="card"><div class="card-body">
        <h5 class="card-title">Por símbolo</h5>
        <table class="table table-sm table-hover align-middle">
            <thead><tr>
                <th>Símbolo</th><th>Señales</th><th>Operadas</th><th>Canceladas</th>
                <th>W/L</th><th>Win%</th><th>PnL</th><th>dist/T1 prom</th>
            </tr></thead>
            <tbody>
            <?php foreach ($symbols as $k):
                $ratio = ($k['avg_t1'] > 0) ? round($k['avg_dist_entry'] / $k['avg_t1'], 3) : '—'; ?>
                <tr>
                    <td><a href="<?= base_url('journals/symbol/'.$k['symbol']) ?>"><strong><?= htmlspecialchars($k['symbol']) ?></strong></a></td>
                    <td><?= $k['total'] ?></td>
                    <td><?= $k['operated'] ?></td>
                    <td><?= $k['cancelled'] ?></td>
                    <td><?= $k['wins'] ?>/<?= $k['losses'] ?></td>
                    <td><?= $k['win_rate'] === null ? '—' : $k['win_rate'].'%' ?></td>
                    <td class="<?= $k['pnl_total'] >= 0 ? 'text-profit' : 'text-loss' ?>"><?= number_format($k['pnl_total'], 2) ?></td>
                    <td><?= $ratio ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div></div>

    <div class="row">
        <div class="col-md-6"><div class="card"><div class="card-body">
            <h6>PnL por símbolo</h6><canvas id="chPnlSym"></canvas>
        </div></div></div>
        <div class="col-md-6"><div class="card"><div class="card-body">
            <h6>PnL acumulado</h6><canvas id="chCum"></canvas>
        </div></div></div>
        <div class="col-md-6"><div class="card"><div class="card-body">
            <h6>Order type</h6><canvas id="chOrderType"></canvas>
        </div></div></div>
        <div class="col-md-6"><div class="card"><div class="card-body">
            <h6>Exit level</h6><canvas id="chExit"></canvas>
        </div></div></div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script>
    const pnlSym = <?= json_encode($chart['pnl_by_symbol']) ?>;
    const cum    = <?= json_encode($chart['cum']) ?>;
    const otype  = <?= json_encode($chart['order_types']) ?>;
    const exitl  = <?= json_encode($chart['exit_levels']) ?>;

    new Chart(document.getElementById('chPnlSym'), {
        type: 'bar',
        data: { labels: Object.keys(pnlSym),
                datasets: [{ label: 'PnL', data: Object.values(pnlSym),
                    backgroundColor: Object.values(pnlSym).map(v => v >= 0 ? '#28a745' : '#dc3545') }] }
    });
    new Chart(document.getElementById('chCum'), {
        type: 'line',
        data: { labels: cum.map(p => p.ts),
                datasets: [{ label: 'PnL acum', data: cum.map(p => p.cum), borderColor: '#0d6efd', tension: 0.1 }] }
    });
    new Chart(document.getElementById('chOrderType'), {
        type: 'pie',
        data: { labels: Object.keys(otype), datasets: [{ data: Object.values(otype) }] }
    });
    new Chart(document.getElementById('chExit'), {
        type: 'bar',
        data: { labels: Object.keys(exitl), datasets: [{ label: 'count', data: Object.values(exitl), backgroundColor: '#6c757d' }] }
    });
    </script>
<?php endif; ?>
```

- [ ] **Step 2: Lint**

Run: `php -l application/views/journals/overview.php`
Expected: `No syntax errors detected`.

- [ ] **Step 3: Commit**

```bash
git add application/views/journals/overview.php
git commit -m "feat(journals): overview view (KPIs, comparison table, charts)"
```

---

### Task 12: Detail view

**Files:**
- Create: `application/views/journals/detail.php`

- [ ] **Step 1: Create the view**

`application/views/journals/detail.php`:

```php
<?php
$el_help = array(
    '1'=>'TP1','2'=>'TP2','3'=>'TP3','4'=>'TP4','5'=>'TP5',
    '-1'=>'Stop loss','-998'=>'Señal inválida','-999'=>'Error/gate/cancel','0'=>'En vivo'
);
function jv_num($v, $d = 2) { return is_numeric($v) ? number_format((float)$v, $d) : htmlspecialchars((string)$v); }
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h2><i class="fas fa-chart-line me-2"></i><?= htmlspecialchars($symbol) ?></h2>
    <a href="<?= base_url('journals') ?>" class="btn btn-sm btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i>Volver</a>
</div>

<div class="row">
    <?php
    $cards = array(
        array('Señales', $kpi['total'], ''),
        array('Operadas', $kpi['operated'], ''),
        array('Canceladas', $kpi['cancelled'], ''),
        array('Win rate', ($kpi['win_rate'] === null ? '—' : $kpi['win_rate'].'%'), ''),
        array('PnL total', number_format($kpi['pnl_total'], 2), $kpi['pnl_total'] >= 0 ? 'text-profit' : 'text-loss'),
    );
    foreach ($cards as $c): ?>
        <div class="col"><div class="card text-center"><div class="card-body">
            <div class="text-muted small"><?= $c[0] ?></div>
            <div class="h4 <?= $c[2] ?>"><?= $c[1] ?></div>
        </div></div></div>
    <?php endforeach; ?>
</div>

<div class="card"><div class="card-body">
    <h5 class="card-title">Estado actual</h5>
    <?php if ($state === null && $live === null): ?>
        <p class="text-muted mb-0">Sin posición activa.</p>
    <?php else: $st = $state ?: array(); ?>
        <div class="row">
            <div class="col-md-6">
                <table class="table table-sm mb-0">
                    <tr><th>Dirección</th><td><?= htmlspecialchars(isset($st['direction']) ? $st['direction'] : '—') ?></td></tr>
                    <tr><th>Ticket</th><td><?= htmlspecialchars(isset($st['ticket']) ? $st['ticket'] : '—') ?></td></tr>
                    <tr><th>Nivel actual</th><td><?= isset($st['currentLevel']) ? (int)$st['currentLevel'] : '—' ?></td></tr>
                    <tr><th>SL en BE</th><td><?= !empty($st['slMovedToBE']) ? 'Sí' : 'No' ?></td></tr>
                    <tr><th>Entry / SL</th><td><?= isset($st['entry']) ? jv_num($st['entry'],5) : '—' ?> / <?= isset($st['currentSL']) ? jv_num($st['currentSL'],5) : '—' ?></td></tr>
                </table>
            </div>
            <div class="col-md-6">
                <strong>Escalera TP (reparto de lotes)</strong>
                <table class="table table-sm mb-0">
                    <thead><tr><th>TP</th><th>Precio</th><th>Lotes</th></tr></thead>
                    <tbody>
                    <?php for ($i = 1; $i <= 5; $i++):
                        $price = isset($st['tp'.$i]) ? $st['tp'.$i] : null;
                        $lots  = isset($st['levelVolumes'][$i]) ? $st['levelVolumes'][$i] : null; ?>
                        <tr><td>TP<?= $i ?></td>
                            <td><?= $price === null ? '—' : jv_num($price,5) ?></td>
                            <td><?= $lots === null ? '—' : jv_num($lots,2) ?></td></tr>
                    <?php endfor; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div></div>

<div class="row">
    <div class="col-md-6"><div class="card"><div class="card-body">
        <h6>PnL acumulado</h6><canvas id="chCum"></canvas>
    </div></div></div>
    <div class="col-md-6"><div class="card"><div class="card-body">
        <h6>dist_entry vs T1 (por order_type)</h6><canvas id="chScatter"></canvas>
    </div></div></div>
    <div class="col-md-6"><div class="card"><div class="card-body">
        <h6>Exit level</h6><canvas id="chExit"></canvas>
    </div></div></div>
</div>

<div class="card"><div class="card-body">
    <h5 class="card-title">Journal (<?= count($rows) ?> filas)</h5>
    <div class="table-responsive">
    <table class="table table-sm table-striped" style="font-size:.8rem">
        <thead><tr>
            <th>ts</th><th>id</th><th>dir</th><th title="order type">type</th><th>side</th>
            <th title="distancia al entry">dist</th><th>T1</th>
            <th title="-2 nunca abrió, 0 sin TP, 1-5 TPn">max_lvl</th>
            <th title="1-5 TPn · -1 SL · -998 inválida · -999 error/cancel">exit</th>
            <th>close_reason</th><th>PnL</th>
        </tr></thead>
        <tbody>
        <?php foreach ($rows as $r):
            $pnl = isset($r['gross_pnl']) ? (float)$r['gross_pnl'] : 0;
            $reason = isset($r['close_reason']) ? (string)$r['close_reason'] : '';
            $cls = ($reason === 'ORDER_CANCELLED') ? 'table-secondary' : ($pnl > 0 ? 'table-success' : ($pnl < 0 ? 'table-danger' : ''));
            $elk = (string)(isset($r['exit_level']) ? $r['exit_level'] : '');
            ?>
            <tr class="<?= $cls ?>">
                <td><?= htmlspecialchars(isset($r['ts_signal']) ? $r['ts_signal'] : '') ?></td>
                <td><?= isset($r['signal_id']) ? (int)$r['signal_id'] : '' ?></td>
                <td><?= htmlspecialchars(isset($r['dir']) ? $r['dir'] : '') ?></td>
                <td><?= htmlspecialchars(isset($r['order_type']) ? $r['order_type'] : '') ?></td>
                <td><?= htmlspecialchars(isset($r['side']) ? $r['side'] : '') ?></td>
                <td><?= isset($r['dist_entry']) ? jv_num($r['dist_entry'],5) : '' ?></td>
                <td><?= isset($r['t1']) ? jv_num($r['t1'],5) : '' ?></td>
                <td><?= isset($r['max_level']) ? (int)$r['max_level'] : '' ?></td>
                <td title="<?= isset($el_help[$elk]) ? $el_help[$elk] : '' ?>"><?= htmlspecialchars($elk) ?></td>
                <td><?= htmlspecialchars($reason) ?></td>
                <td class="<?= $pnl >= 0 ? 'text-profit' : 'text-loss' ?>"><?= jv_num($pnl,2) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div></div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
const cum     = <?= json_encode($chart['cum']) ?>;
const scatter = <?= json_encode($chart['scatter']) ?>;
const exitl   = <?= json_encode($chart['exit_levels']) ?>;
const typeColor = { 'MARKET':'#0d6efd','MARKET_FB':'#6610f2','LIMIT':'#fd7e14','STOP':'#20c997' };

new Chart(document.getElementById('chCum'), {
    type: 'line',
    data: { labels: cum.map(p => p.ts),
            datasets: [{ label: 'PnL acum', data: cum.map(p => p.cum), borderColor: '#0d6efd', tension: 0.1 }] }
});

const byType = {};
scatter.forEach(p => { (byType[p.type] = byType[p.type] || []).push({ x: p.x, y: p.y }); });
new Chart(document.getElementById('chScatter'), {
    type: 'scatter',
    data: { datasets: Object.keys(byType).map(t => ({
        label: t, data: byType[t], backgroundColor: typeColor[t] || '#6c757d' })) },
    options: { scales: { x: { title: { display: true, text: 'dist_entry' } },
                         y: { title: { display: true, text: 'T1' } } } }
});

new Chart(document.getElementById('chExit'), {
    type: 'bar',
    data: { labels: Object.keys(exitl), datasets: [{ label: 'count', data: Object.values(exitl), backgroundColor: '#6c757d' }] }
});
</script>
```

- [ ] **Step 2: Lint**

Run: `php -l application/views/journals/detail.php`
Expected: `No syntax errors detected`.

- [ ] **Step 3: Commit**

```bash
git add application/views/journals/detail.php
git commit -m "feat(journals): detail view (state panel, charts, full table)"
```

---

### Task 13: Manual verification

**Files:** none (verification only)

- [ ] **Step 1: Run the full test suite**

Run: `php tests/journals/test_reader.php && php tests/journals/test_stats.php`
Expected: both end with `0 failed`, exit 0.

- [ ] **Step 2: Copy real journals to the dev folder (if not already present)**

Confirm `EA/journals/` contains the real `bxlite_*` files (the ones already committed/brought over). `JOURNALS_PATH` defaults there.

- [ ] **Step 3: Browser check — overview**

Start XAMPP, log in as an admin user, visit `http://localhost/bingx_lite/journals`.
Expected: KPI cards, per-symbol table with links, 4 charts rendered. The "Journals" link appears under the System dropdown.

- [ ] **Step 4: Browser check — detail**

Click a symbol (e.g. ES), or visit `http://localhost/bingx_lite/journals/symbol/ES`.
Expected: KPI cards, "Estado actual" panel (live trade or "Sin posición"), 3 charts (cumulative, scatter, exit-level), full journal table with colored rows and tooltips on `max_lvl`/`exit`.

- [ ] **Step 5: Browser check — auth + edge**

Log in as a non-admin (or hit the URL while logged out) → redirect/403. Visit `journals/symbol/NOPE` → redirect to overview with a warning flash.

- [ ] **Step 6: Final commit (if any tweak was needed)**

```bash
git add -A
git commit -m "test(journals): manual verification pass"
```

---

## Self-Review notes (addressed)

- **Spec coverage:** data source/config (Task 1), reader (3–5), stats incl. KPI defs (6–8), controller w/ admin gate + whitelist (10), overview (11), detail incl. state panel + scatter (12), routes/menu (9), error/empty states (10/11), tests (2–8), manual verification (13). All spec sections mapped.
- **Naming consistency:** `Journal_reader` methods (`parse_filename`, `parse_csv`, `list_symbols`, `read_journal`, `read_live`, `read_state`, `is_readable_dir`, `base_path`) and `Journal_stats` methods (`is_operated`, `is_cancelled`, `is_win`, `per_symbol`, `cumulative_pnl`, `distribution`) are used identically in the controller and tests.
- **No placeholders:** every step has runnable code/commands.
- **Permissions/ACL** are an ops step from the spec, not a code task; the viewer degrades gracefully (Task 10/11 "not readable" branch) when the path isn't accessible.
