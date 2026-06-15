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
