<?php
// Reusable module checkboxes partial for user add/edit forms
// Expects: $user (object, optional — for edit form pre-population)
$modules = [
    'module_bingx'      => ['label' => 'BingX', 'icon' => 'fas fa-chart-line', 'class' => 'warning'],
    'module_metatrader' => ['label' => 'MetaTrader TV', 'icon' => 'fas fa-signal', 'class' => 'info'],
    'module_atvip'      => ['label' => 'AT VIP Trading', 'icon' => 'fas fa-broadcast-tower', 'class' => 'success'],
];
?>
<div class="mb-3">
    <label class="form-label">Enabled Modules</label>
    <div class="d-flex flex-column gap-2">
        <?php foreach ($modules as $field => $meta): ?>
            <?php
                $checked = false;
                if (isset($user) && isset($user->$field)) {
                    $checked = (bool) $user->$field;
                }
                // Respect form re-population on validation failure
                if (set_value($field) !== '') {
                    $checked = (bool) set_value($field);
                }
            ?>
            <div class="form-check">
                <input class="form-check-input" type="checkbox" id="<?= $field ?>" name="<?= $field ?>" value="1" <?= $checked ? 'checked' : '' ?>>
                <label class="form-check-label" for="<?= $field ?>">
                    <i class="<?= $meta['icon'] ?> me-1 text-<?= $meta['class'] ?>"></i><?= $meta['label'] ?>
                </label>
            </div>
        <?php endforeach; ?>
    </div>
    <div class="form-text">Admin users automatically have all modules enabled.</div>
</div>
