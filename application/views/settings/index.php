<div class="mb-4">
    <h1 class="h3 mb-0">
        <i class="fas fa-robot me-2"></i>Configuración de IA (análisis de señales)
    </h1>
</div>

<?php if ($this->session->flashdata('success')): ?>
    <div class="alert alert-success"><?= $this->session->flashdata('success') ?></div>
<?php endif; ?>
<?php if ($this->session->flashdata('error')): ?>
    <div class="alert alert-danger"><?= $this->session->flashdata('error') ?></div>
<?php endif; ?>

<?php if (isset($settings_ready) && !$settings_ready): ?>
    <div class="alert alert-warning">
        <i class="fas fa-exclamation-triangle me-1"></i>
        La tabla <code>system_settings</code> no existe todavía, así que <strong>los cambios acá no se guardan</strong>.
        Aplicá la migración y recargá:
        <br><code>mysql -u u_bingx -p bingx_lite &lt; database/migrations/2026-06-13-ai-provider-gemini-and-settings.sql</code>
        <br>Mientras tanto, la selección de proveedores se toma de <code>config.php</code>.
    </div>
<?php endif; ?>

<div class="row">
    <div class="col-md-6">
        <div class="card">
            <div class="card-body">
                <?= form_open('settings/save') ?>

                <div class="mb-3">
                    <label for="ai_mode" class="form-label">Modo de análisis</label>
                    <select class="form-select" id="ai_mode" name="ai_mode">
                        <option value="dual" <?= $ai_mode === 'dual' ? 'selected' : '' ?>>Dual (consenso de 2 proveedores)</option>
                        <option value="single" <?= $ai_mode === 'single' ? 'selected' : '' ?>>Single (un proveedor)</option>
                    </select>
                    <small class="text-muted">Dual cruza 2 IAs y solo distribuye la señal si coinciden (mayor certeza).</small>
                </div>

                <div class="mb-3">
                    <label for="ai_provider_a" class="form-label">Proveedor A</label>
                    <select class="form-select" id="ai_provider_a" name="ai_provider_a">
                        <?php foreach ($providers as $key => $label): ?>
                            <option value="<?= $key ?>" <?= $ai_provider_a === $key ? 'selected' : '' ?>><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
                    <small class="text-muted">En modo Single, se usa solo este.</small>
                </div>

                <div class="mb-3">
                    <label for="ai_provider_b" class="form-label">Proveedor B <span class="text-muted">(solo modo Dual)</span></label>
                    <select class="form-select" id="ai_provider_b" name="ai_provider_b">
                        <?php foreach ($providers as $key => $label): ?>
                            <option value="<?= $key ?>" <?= $ai_provider_b === $key ? 'selected' : '' ?>><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="alert alert-info py-2 small mb-3">
                    <i class="fas fa-info-circle me-1"></i>
                    En modo <strong>Dual</strong>, A y B deben ser <strong>distintos</strong>: proveedores
                    diferentes = errores independientes = mejor consenso.
                </div>

                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save me-1"></i>Guardar
                </button>
                <?= form_close() ?>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card">
            <div class="card-body">
                <h6 class="card-title">Proveedores disponibles</h6>
                <ul class="mb-0 small">
                    <li><strong>Gemini 2.5 Flash</strong> — fuerte en OCR/visión, costo-efectivo.</li>
                    <li><strong>OpenAI GPT-4o</strong> — visión general.</li>
                    <li><strong>Claude</strong> — visión / lectura de estructura.</li>
                </ul>
                <hr>
                <p class="small text-muted mb-0">
                    Las API keys se configuran en <code>application/config/config.php</code>
                    (<code>gemini_api_key</code>, <code>openai_api_key</code>, <code>claude_api_key</code>).
                    Si falta la key del proveedor elegido, ese análisis falla y el dual cae a "una sola respondió".
                </p>
            </div>
        </div>
    </div>
</div>
