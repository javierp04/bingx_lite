<?php
// Etiquetas legibles (exit_level / close_reason) -> helper journal_labels (fuente única).
function jv_num($v, $d = 2) { return is_numeric($v) ? number_format((float)$v, $d) : htmlspecialchars((string)$v); }

// Resuelve un campo prefiriendo el snapshot del EA, con fallback a user_telegram_signals.
function jv_pick($t, $snapField, $utsField) {
    if (isset($t->snap) && $t->snap && isset($t->snap->$snapField) && $t->snap->$snapField !== null && $t->snap->$snapField !== '') {
        return $t->snap->$snapField;
    }
    return isset($t->$utsField) ? $t->$utsField : null;
}
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
    <h5 class="card-title">Trades (<?= count($trades) ?>)</h5>
    <div class="table-responsive">
    <table class="table table-sm table-hover align-middle" style="font-size:.82rem">
        <thead><tr>
            <th>Fecha</th><th>#</th><th>dir</th><th title="order type">type</th>
            <th title="distancia al entry">dist</th><th>T1</th>
            <th title="1-5 TPn · -1 SL · -998 inválida · -999 error/cancel">exit</th>
            <th>close_reason</th><th>vol</th><th>PnL</th><th></th>
        </tr></thead>
        <tbody>
        <?php foreach ($trades as $t):
            $pnl    = (float) jv_pick($t, 'gross_pnl', 'uts_gross_pnl');
            $reason = (string) jv_pick($t, 'close_reason', 'uts_close_reason');
            $otype  = (string) jv_pick($t, 'order_type', 'uts_order_type');
            $exit   = jv_pick($t, 'exit_level', 'uts_exit_level');
            $dir    = isset($t->snap, $t->snap->dir) && $t->snap->dir ? $t->snap->dir : ($t->op_type ?? '');
            $ts     = (isset($t->snap, $t->snap->ts_signal) && $t->snap->ts_signal) ? $t->snap->ts_signal : $t->created_at;
            $cls    = ($reason === 'ORDER_CANCELLED') ? 'table-secondary' : ($pnl > 0 ? 'table-success' : ($pnl < 0 ? 'table-danger' : ''));
            $elk    = (string)($exit === null ? '' : $exit);
            $url    = base_url('journals/symbol/' . rawurlencode($symbol) . '/' . (int)$t->id);
            ?>
            <tr class="<?= $cls ?>" style="cursor:pointer" onclick="window.location='<?= $url ?>'">
                <td><?= htmlspecialchars((string)$ts) ?></td>
                <td><a href="<?= $url ?>">#<?= (int)$t->id ?></a></td>
                <td><?= htmlspecialchars((string)$dir) ?></td>
                <td><?= htmlspecialchars($otype) ?></td>
                <td><?= ($t->snap && $t->snap->dist_entry !== null) ? jv_num($t->snap->dist_entry, 5) : '—' ?></td>
                <td><?= ($t->snap && $t->snap->t1 !== null) ? jv_num($t->snap->t1, 5) : '—' ?></td>
                <td title="<?= htmlspecialchars($elk) ?>"><?= htmlspecialchars(journal_exit_label($elk)) ?></td>
                <td title="<?= htmlspecialchars($reason) ?>"><?= htmlspecialchars(journal_reason_label($reason)) ?></td>
                <td><?= $t->real_volume !== null ? jv_num($t->real_volume, 2) : '—' ?></td>
                <td class="<?= $pnl >= 0 ? 'text-profit' : 'text-loss' ?>"><?= jv_num($pnl, 2) ?></td>
                <td><a href="<?= $url ?>" class="btn btn-sm btn-outline-primary py-0"><i class="fas fa-search"></i></a></td>
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
