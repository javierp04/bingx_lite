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
        <div class="col-md-6"><div class="card"><div class="card-body">
            <h6>Motivo de cierre</h6><canvas id="chReason"></canvas>
        </div></div></div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script>
    const pnlSym = <?= json_encode($chart['pnl_by_symbol']) ?>;
    const cum    = <?= json_encode($chart['cum']) ?>;
    const otype  = <?= json_encode($chart['order_types']) ?>;
    const exitl  = <?= json_encode($chart['exit_levels']) ?>;
    const reasons = <?= json_encode($chart['close_reasons']) ?>;

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
    new Chart(document.getElementById('chReason'), {
        type: 'bar',
        data: { labels: reasons.labels, datasets: [{ label: 'count', data: reasons.counts, backgroundColor: reasons.colors }] }
    });
    </script>
<?php endif; ?>
