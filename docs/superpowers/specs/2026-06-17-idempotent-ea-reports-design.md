# Idempotencia de reportes del EA (server-side)

**Fecha:** 2026-06-17
**Estado:** Diseño aprobado — pendiente plan de implementación
**Alcance:** solo server-side (PHP / CodeIgniter 3). El EA no se toca en este trabajo.

## Problema

Los endpoints `POST /api/signals/{id}/progress` y `.../close` acumulan PNL:
`gross_pnl = gross_pnl_actual + delta` ([Telegram_signals_model::report_progress](../../../application/models/Telegram_signals_model.php), `report_close`).
Hoy el EA reporta **best-effort sin retry**: si un POST falla, el reporte se pierde
(la posición queda cerrada en MT5 pero `open`/desincronizada en la DB).

Cuando más adelante se agregue un retry (cola de salida en el EA, fuera de este trabajo),
reenviar el mismo reporte **duplicaría el PNL** y duplicaría eventos/filas en `trades`.

Este trabajo deja el server **idempotente**: recibir el mismo reporte dos veces no muta nada
la segunda vez. Es la base segura sobre la que luego se agrega el retry en el EA.

## Objetivo / criterios de éxito

- Reenviar un reporte byte-idéntico (mismo body) **no** vuelve a sumar PNL, ni inserta
  un evento/trade duplicado. La segunda vez es un no-op que igual responde `200`.
- Dos reportes **distintos** (distinto nivel/precio/hora → distinto body) se aplican ambos.
- Cero cambios en el EA. Cero cambios en la matemática de PNL (`+= delta` sigue igual; solo
  no se ejecuta dos veces).
- Deploy gradual: si la migración no corrió, el comportamiento es el actual (sin romper trading).

## No-objetivos (YAGNI)

- No se implementa el retry/cola del EA (es el trabajo #2-EA, posterior).
- No se cambia el path legacy `user_telegram_signals.event_log`.
- No se reescribe el cálculo de PNL a "autoritativo" (enfoque descartado: depende de
  `ea_trade_events`, rompe el fallback legacy y toca números ya guardados).

## Diseño

### 1. Esquema — migración nueva

`database/migrations/2026-06-17-ea-report-dedup.sql`:

```sql
CREATE TABLE IF NOT EXISTS `ea_report_dedup` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_signal_id` INT UNSIGNED NOT NULL,
  `endpoint`       ENUM('open','progress','close') NOT NULL,  -- informativo/debug
  `body_hash`      CHAR(40) NOT NULL,                          -- sha1 del body crudo
  `created_at`     DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_signal_hash` (`user_signal_id`, `body_hash`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

El `UNIQUE (user_signal_id, body_hash)` es el corazón del diseño: la atomicidad del
"¿ya lo vi?" la garantiza la DB. Esto cierra de paso cualquier race entre dos requests
concurrentes con el mismo body, no solo el retry.

### 2. Flujo de control

Primer paso de `report_open` / `report_progress` / `report_close`, **después** del guard
`signal_exists()` ya existente:

```
hash = sha1(raw_body)
si NO dedup_ready():            # tabla no migrada -> comportamiento actual
    aplicar mutaciones; return
trans_start()
  INSERT IGNORE ea_report_dedup (user_signal_id, endpoint, hash, NOW())
  si affected_rows() == 0  -> dup: trans_complete(); return TRUE   # no-op idempotente -> controller 200
  si affected_rows() == 1  -> aplicar mutaciones normales (PNL, eventos, trades, analytics)
trans_complete()   # si algo falla, el rollback borra también la fila dedup -> el retry reintenta limpio
```

**`INSERT IGNORE` + `affected_rows()`, no captura de excepción:** detectar el duplicado por la
violación del `UNIQUE` es frágil en CI3 (con `db_debug = TRUE` el handler de error corta la
ejecución). `INSERT IGNORE` convierte la colisión en un no-op silencioso: `affected_rows() == 0`
significa "ya visto", `== 1` significa "primera vez". Atómico y sin depender del modo debug.

**Por qué dedup-first + transacción:** el INSERT del dedup debe ocurrir *antes* de aplicar,
porque si se aplicara primero y fallara a mitad, un retry re-aplicaría y duplicaría (justo lo
que evitamos). Insertar primero y envolver todo en una transacción hace que un fallo parcial
revierta también la fila dedup, dejando el reintento limpio. Es el único lugar donde se agrega
una transacción.

### 3. Origen del hash

El controller ([Api.php](../../../application/controllers/Api.php)) hoy hace
`json_decode(file_get_contents("php://input"), true)` y descarta el crudo. Se cambia a capturar
el crudo primero y pasarlo al modelo como 3er argumento opcional:

```php
$raw = file_get_contents("php://input");
$data = json_decode($raw, true);
... ->report_open($user_signal_id, $data, $raw);
```

Firma del modelo: `report_open($user_signal_id, $open_data, $raw_body = null)` (ídem progress/close).
El hash se calcula sobre el **body crudo** (`sha1($raw_body)`), fiel byte-a-byte a lo que reenvíe
el retry futuro. Si `$raw_body` es null (llamador que no lo pasa), fallback a `sha1(json_encode($data))`.

### 4. Guard / fallback

Helper `dedup_ready()` análogo a `ea_tables_ready()` (cacheado, `$this->db->table_exists('ea_report_dedup')`).
Si la tabla no existe, se saltea el dedup y se aplica directo = comportamiento actual. La migración
se puede correr cuando se quiera, sin romper el flujo de trading.

### 5. Alcance: los tres endpoints

`open`, `progress` y `close`. El gate es genérico (una sola función `claim_report()`), así que
cubrir `open` no agrega costo y evita que un `open` reenviado pise `gross_pnl = 0` sobre un
`progress` ya aplicado.

### 6. Limpieza

~3-8 filas por trade. Prune colgado del `cleanup_old_signals($days)` existente: borrar filas de
`ea_report_dedup` con `created_at` anterior a la misma ventana. Sin cron nuevo.

## Contrato con el retry futuro (#2-EA, posterior)

La cola de salida en el EA debe **reenviar el body idéntico** (mismo `execution_time` incluido),
no reconstruir el payload. Es la propiedad natural de bufferear los bytes del request en disco.
Si el retry reconstruyera el body con un `execution_time` nuevo, el hash cambiaría y el dedup no
lo reconocería.

## Componentes tocados

| Archivo | Cambio |
|---|---|
| `database/migrations/2026-06-17-ea-report-dedup.sql` | nueva tabla `ea_report_dedup` |
| `application/controllers/Api.php` | capturar raw body y pasarlo a `report_open/progress/close` |
| `application/models/Telegram_signals_model.php` | `dedup_ready()`, `claim_report()`, gate al inicio de los 3 report_*, prune en `cleanup_old_signals` |

## Testing / verificación

- Sin harness de DB en el proyecto (los tests cubren solo librerías puras). La lógica nueva está
  acoplada a `$this->db`, así que la verificación es:
  - `php -l` de los archivos modificados.
  - Verificación manual contra la DB de dev:
    1. `POST .../progress` con un body → `gross_pnl` sube por `delta`.
    2. Reenviar el **mismo** body → `gross_pnl` no cambia (200, no-op); 1 sola fila en `ea_report_dedup`.
    3. `POST .../progress` con body distinto (otro nivel) → se aplica.
    4. Sin la tabla migrada → comportamiento actual (aplica siempre).
- Si se decide extraer la generación de hash a una función pura, se puede cubrir con el harness
  liviano existente (`tests/journals/_helpers.php`). Opcional.

## Riesgos

- **Partial failure mitigado** por la transacción (rollback borra el dedup).
- **Colisión de hash** (sha1 de bodies distintos): despreciable; además acotada por `user_signal_id`.
- **Float drift** en el fallback `json_encode($data)`: evitado usando el raw body como fuente primaria.
