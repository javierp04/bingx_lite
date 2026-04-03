<!-- Visual Price Levels (TradingView box style: mayor precio arriba) -->
<div class="price-levels mb-3">
    <?php if ($op_type === 'LONG'): ?>
        <!-- LONG: TP5→TP1 (profit arriba), ENTRY, SL1, SL2 (loss abajo) -->
        <?php for ($i = 4; $i >= 0; $i--): ?>
            <?php if (isset($tps[$i]) && $tps[$i] > 0): ?>
                <div class="price-level tp mb-1">
                    <span class="badge bg-success">TP<?= $i + 1 ?></span>
                    <span class="price"><?= number_format($tps[$i], $decimals) ?></span>
                </div>
            <?php endif; ?>
        <?php endfor; ?>

        <div class="price-level entry my-2">
            <span class="badge bg-primary">ENTRY</span>
            <span class="price fw-bold"><?= number_format($entry, $decimals) ?></span>
        </div>

        <?php if ($sl2 > 0): ?>
            <div class="price-level sl mb-1">
                <span class="badge bg-warning text-dark">SL ref</span>
                <span class="price text-muted"><?= number_format($sl2, $decimals) ?></span>
            </div>
        <?php endif; ?>
        <?php if ($sl1 > 0): ?>
            <div class="price-level sl mb-1">
                <span class="badge bg-danger">SL</span>
                <span class="price"><?= number_format($sl1, $decimals) ?></span>
            </div>
        <?php endif; ?>

    <?php else: ?>
        <!-- SHORT: SL (loss arriba), ENTRY, SL ref, TP1→TP5 (profit abajo) -->
        <?php if ($sl1 > 0): ?>
            <div class="price-level sl mb-1">
                <span class="badge bg-danger">SL</span>
                <span class="price"><?= number_format($sl1, $decimals) ?></span>
            </div>
        <?php endif; ?>

        <div class="price-level entry my-2">
            <span class="badge bg-primary">ENTRY</span>
            <span class="price fw-bold"><?= number_format($entry, $decimals) ?></span>
        </div>

        <?php if ($sl2 > 0): ?>
            <div class="price-level sl mb-1">
                <span class="badge bg-warning text-dark">SL ref</span>
                <span class="price text-muted"><?= number_format($sl2, $decimals) ?></span>
            </div>
        <?php endif; ?>

        <?php for ($i = 0; $i < count($tps); $i++): ?>
            <?php if (isset($tps[$i]) && $tps[$i] > 0): ?>
                <div class="price-level tp mb-1">
                    <span class="badge bg-success">TP<?= $i + 1 ?></span>
                    <span class="price"><?= number_format($tps[$i], $decimals) ?></span>
                </div>
            <?php endif; ?>
        <?php endfor; ?>
    <?php endif; ?>
</div>
