# PRD: EA_Signals.mq5 v8.04

## 1. Resumen Ejecutivo

EA_Signals es un Expert Advisor para MetaTrader 5 que actua como ejecutor automatizado de senales de trading generadas por analisis de IA (Telegram). Realiza polling periodico a una API REST, ejecuta ordenes, gestiona take profits parciales con breakeven, y reporta todo el ciclo de vida al servidor web.

---

## 2. Arquitectura General

```
[Telegram] --> [TradeReader + IA] --> [DB: telegram_signals]
                                           |
                                    [DB: user_telegram_signals]
                                           |
                                    status: available
                                           |
                        +------------------+------------------+
                        |                                     |
                   [EA_Signals.mq5]                    [Panel Web]
                   (MetaTrader 5)                   (my_trading/active)
                        |                                     |
                  GET /api/signals/                   Muestra estados
                  POST /api/signals/open              y PNL en tiempo
                  POST /api/signals/progress          real via AJAX
                  POST /api/signals/close
```

### Flujo de Comunicacion

```
EA (Timer cada 30s)
  |
  +--> GET /api/signals/{user_id}/{ticker}
  |      Respuesta: signal JSON o {signal: null}
  |      DB: available --> claimed
  |
  +--> Ejecuta orden (market o pending)
  |
  +--> POST /api/signals/{id}/open
  |      DB: claimed --> open (market) o pending (limit/stop)
  |
  +--> [Si pending] OnTick() monitorea ejecucion
  |      Cuando se llena: POST /api/signals/{id}/progress (now_open=true)
  |      DB: pending --> open
  |
  +--> OnTick() gestiona TPs parciales
  |      POST /api/signals/{id}/progress (por cada TP alcanzado)
  |      DB: actualiza current_level, volume_closed_percent, gross_pnl
  |
  +--> Posicion cerrada (TP final, SL, manual, etc.)
         POST /api/signals/{id}/close
         DB: open --> closed
```

---

## 3. Componentes Principales

### 3.1. Sistema de Polling (OnTimer)

| Parametro | Valor Default | Descripcion |
|-----------|--------------|-------------|
| POLL_INTERVAL | 30s | Frecuencia de consulta al API |
| ENABLE_TIME_FILTER | false | Filtro horario para trading |
| START_HOUR / END_HOUR | 11 / 13 | Ventana horaria (si habilitado) |

**Flujo:**
1. `OnTimer()` se ejecuta cada POLL_INTERVAL segundos
2. Verifica filtro horario (si habilitado)
3. Llama `CheckForSignals()` --> GET al API
4. Si hay senal: parsea JSON, valida, ejecuta
5. Si no hay senal: retorna silencioso (con DEBUG_LVL se ve en logs)

**Restriccion:** Solo procesa UNA senal por ciclo de polling. Si hay multiples senales en cola, se procesan de a una por ciclo.

### 3.2. Ejecucion de Ordenes (ExecuteTrade)

**Tipos de orden soportados:**

| Condicion | Orden | isActive |
|-----------|-------|----------|
| Precio actual ~ Entry (dentro de tolerancia) | Market (BUY/SELL) | true |
| Entry por debajo del precio actual (LONG) | BUY_LIMIT | false |
| Entry por encima del precio actual (LONG) | BUY_STOP | false |
| Entry por encima del precio actual (SHORT) | SELL_LIMIT | false |
| Entry por debajo del precio actual (SHORT) | SELL_STOP | false |

**Tolerancia de precio:**
- `PRICE_TOLERANCE_PERCENT` > 0: usa porcentaje del entry (prioridad)
- `PRICE_TOLERANCE_PERCENT` = 0: usa `PRICE_TOLERANCE_POINTS` absolutos

**Calculo de volumen:**
```
riskAmount = Balance * (RISK_PERCENT / 100)
distancePoints = |Entry - StopLoss| / point
volume = riskAmount / (distancePoints * pointValuePerLot)
```
Normalizado a min/max/step del broker.

### 3.3. Correccion de Precios (Price Correction)

Alinea precios de senales (basados en futuros) con precios CFD del broker.

```
correctionFactor = futurePrice / cfdPrice
precioCorregido = precioSenal / correctionFactor
```

| Parametro | Default | Descripcion |
|-----------|---------|-------------|
| ENABLE_PRICE_CORRECTION | true | Habilitar correccion |
| MAX_PRICE_DEVIATION | 5.0% | Desviacion maxima permitida |
| MAX_TIMESTAMP_HOURS | 4 | Antiguedad maxima del dato futures |

**Fuente:** Yahoo Finance via `/api/fut_price/{symbol}`

### 3.4. Gestion de Take Profits (ManageTPs)

El EA gestiona 5 niveles de TP con cierres parciales del volumen original:

| TP | % Default | Comportamiento |
|----|-----------|----------------|
| TP1 | 0% | Solo activa breakeven (si BE_LEVEL=1) |
| TP2 | 40% | Cierre parcial |
| TP3 | 30% | Cierre parcial |
| TP4 | 20% | Cierre parcial |
| TP5 | 10% | Cierra 100% del remanente (siempre) |

**Breakeven:**
- Se activa cuando el precio alcanza el TP configurado en `BE_LEVEL`
- Mueve el SL al precio de entrada (riesgo cero)
- Reporta el nuevo SL via ReportProgress

**Flujo por tick:**
```
OnTick()
  --> Si isActive = false y ticket > 0: CheckPendingOrderExecution()
  --> Si isActive = false: return
  --> Verificar si posicion existe (SelectByTicket)
      --> Si no existe: detectar razon de cierre en historial, reportar close
  --> Actualizar currentVolume
  --> Si ENABLE_CODE_STOP: verificar SL por codigo
  --> ManageTPs(): verificar cada nivel TP1-TP5 secuencialmente
```

### 3.5. Monitoreo de Ordenes Pendientes (CheckPendingOrderExecution)

Cuando se ejecuta una orden limit/stop, el EA queda en estado `isActive=false` con `ticket > 0`. Cada tick:

1. Busca en posiciones abiertas una que matchee symbol + magic + comment
2. **Si encuentra:** La orden se ejecuto --> activa TP management, reporta `now_open=true`
3. **Si no encuentra:** Verifica si la orden aun existe via `OrderSelect(ticket)`
4. **Si la orden no existe:** Fue cancelada --> reporta `ORDER_CANCELLED`, limpia estado

### 3.6. Deteccion de Cierre por Historial

Cuando una posicion desaparece, el EA consulta `HistorySelectByPosition()` para determinar la causa:

| DEAL_REASON | Close Reason | Exit Level |
|-------------|-------------|------------|
| DEAL_REASON_SL | CLOSED_STOPLOSS | -1 |
| DEAL_REASON_TP | CLOSED_COMPLETE | currentLevel |
| DEAL_REASON_CLIENT/MOBILE/WEB | CLOSED_EXTERNAL | currentLevel |
| DEAL_REASON_EXPERT | CLOSED_EXTERNAL | currentLevel |
| Otro | CLOSED_EXTERNAL | currentLevel |

### 3.7. Stop Loss Management

Dos modos disponibles:

| Modo | ENABLE_CODE_STOP | Comportamiento |
|------|-----------------|----------------|
| Broker | false (default) | MT5 ejecuta el SL normalmente |
| Code | true | EA cierra posicion por codigo cuando precio cruza SL. SL en broker se coloca a distancia * SAFETY_FACTOR como red de seguridad |

### 3.8. Sistema de Logging

**Input:** `MIN_LOG_LEVEL` (default: INFO_LVL)

| Nivel | Que muestra |
|-------|-------------|
| DEBUG_LVL | Todo: cada poll, response bodies, respuestas de reports, señales ignoradas |
| INFO_LVL | Normal: senales recibidas, trades ejecutados, TPs alcanzados, reports exitosos |
| WARNING_LVL | Solo advertencias y errores |
| ERROR_LVL | Solo errores criticos |

---

## 4. Estados de Senal (user_telegram_signals)

### 4.1. Diagrama de Transiciones

```
available ──[EA GET /signals]--> claimed ──[EA POST /open]--> open ──[EA POST /close]--> closed
                                    |                           |
                                    +--> [POST /open pending]   |
                                    |         |                 |
                                    |         v                 |
                                    |      pending              |
                                    |         |                 |
                                    |    [progress now_open] -->+
                                    |         |
                                    |    [ORDER_CANCELLED] --> closed (exit_level: -999)
                                    |
                                    +--> failed_execution (nunca usado actualmente)
                                    +--> expired (nunca usado actualmente)
                                    +--> cancelled (nunca usado actualmente)
```

### 4.2. Campos de Tracking

| Campo | Descripcion | Actualizado por |
|-------|-------------|----------------|
| status | Estado principal | Cada endpoint del API |
| current_level | -2=pending, 0=entry, 1-5=TP alcanzado | report_open, report_progress |
| volume_closed_percent | % del volumen original cerrado | report_progress, report_close |
| gross_pnl | PNL acumulado (se suma en cada progress) | report_progress, report_close |
| real_entry_price | Precio real de ejecucion | report_open |
| real_stop_loss | SL real (puede cambiar con breakeven) | report_open, report_progress |
| real_volume | Volumen ejecutado | report_open |
| last_price | Ultimo precio reportado | report_progress, report_close |
| order_type | Tipo de orden (market/limit/stop) | report_open |
| exit_level | Nivel final al cerrar | report_close |
| close_reason | Razon de cierre textual | report_close |

### 4.3. Panel Web (my_trading/active)

**Pestana "Active"** muestra senales con status: `pending`, `claimed`, `open`
**Pestana "Signals"** muestra senales disponibles
**Pestana "Tickers"** gestiona suscripciones

Refresco via AJAX: `refresh_dashboard_ajax()` retorna HTML + stats actualizados.

---

## 5. Respuesta a: Que pasa si cancelo una orden pendiente manual en MT5?

### Comportamiento Actual

**SI funciona correctamente.** Cuando cancelas manualmente un Buy Limit / Sell Limit / Buy Stop / Sell Stop en MT5:

1. **En el proximo OnTick()** el EA entra a `CheckPendingOrderExecution()` (porque `isActive=false` y `ticket > 0`)
2. No encuentra posicion abierta que matchee (paso 1 del check)
3. Ejecuta `OrderSelect(currentTP.ticket)` --> **falla** porque la orden ya no existe
4. Loguea: `"Orden pendiente cancelada"`
5. Llama `ReportClose(signalId, -999, "ORDER_CANCELLED", 0, 0)`
6. Llama `InitTPState()` --> limpia todo el estado, EA queda listo para nueva senal
7. **En la DB:** el status cambia a `closed` con close_reason `"ORDER_CANCELLED"` y exit_level `-999`

### Tiempos de Deteccion

La deteccion depende de que llegue un tick. En mercados liquidos (EURUSD) es casi instantaneo. En mercados con poco volumen o fuera de horario, podria tardar hasta que llegue el proximo tick.

### Lo que NO se maneja (gaps identificados)

| Gap | Descripcion | Impacto |
|-----|-------------|---------|
| Modificacion manual | Si cambias el precio/SL de la orden pendiente en MT5, el EA no lo detecta | El EA sigue con los precios originales de la senal |
| Expiracion de orden | Las ordenes se crean con ORDER_TIME_DAY, si expiran al fin del dia el EA lo detecta como cancelacion | Correcto pero no distingue expiracion vs cancelacion manual |
| OnDeinit sin cleanup | Si remueves el EA del chart con orden pendiente activa, no cancela la orden ni reporta al servidor | La orden queda viva en MT5, el server queda en status "pending" para siempre |
| Race condition | Si la orden se ejecuta Y se cierra entre dos ticks (flash crash), el EA podria no detectar la posicion | Detecta cierre pero sin datos de posicion intermedios |

---

## 6. Consideraciones para Futuro: Gestion de Status en Panel Web

### 6.1. Problema Actual

El panel `my_trading/active` muestra senales en status `pending` pero:
- No hay forma de cancelar una orden pendiente desde el panel
- No hay indicacion visual de hace cuanto esta pendiente
- No se distingue `pending` (orden en MT5 esperando precio) de `claimed` (EA acaba de tomar la senal)
- Si el EA se desconecta, la senal queda en `pending`/`claimed` indefinidamente

### 6.2. Mejoras Sugeridas

**A. Acciones desde el panel:**
- Boton "Cancelar orden pendiente" que cambie status a `cancelled` en DB
- El EA en el proximo poll deberia verificar si su senal activa fue cancelada desde el panel
- Requiere nuevo endpoint: `GET /api/signals/{id}/status` o flag en response de polling

**B. Indicadores visuales:**
- Tiempo transcurrido desde que paso a `pending`
- Tipo de orden (LIMIT/STOP) y precio objetivo
- Distancia actual al precio de entrada (requiere datos de mercado)

**C. Timeout automatico:**
- Si una senal lleva mas de X horas en `claimed` sin report_open --> marcar como `expired`
- Si lleva mas de X horas en `pending` sin progress --> alertar o expirar
- Implementable con cron job o verificacion en el polling del EA

**D. Sincronizacion bidireccional EA <--> Panel:**
- Actualmente la comunicacion es unidireccional: EA --> Server
- Para cancelar desde el panel se necesita: Panel --> DB --> EA (lee en proximo poll)
- Alternativa: Endpoint que el EA consulte periodicamente para verificar si debe cancelar

### 6.3. Estados Faltantes en el Flujo

Los status `failed_execution`, `expired`, `cancelled` existen en la DB pero nunca se usan. Podrian aprovecharse:

| Status | Uso propuesto |
|--------|--------------|
| `cancelled` | Orden cancelada desde el panel web (accion del usuario) |
| `expired` | Senal que supero el tiempo maximo sin ejecutarse |
| `failed_execution` | EA intento ejecutar pero fallo (actualmente se reporta como closed con exit_level -999) |

---

## 7. Restriccion: Una Posicion a la Vez

El EA solo maneja UNA posicion activa por instancia. El flag `currentTP.isActive` bloquea nuevas senales mientras hay trade abierto. Esto implica:

- Si hay una orden pendiente esperando, nuevas senales se ignoran
- Si hay una posicion abierta gestionando TPs, nuevas senales se ignoran
- Para operar multiples pares: una instancia del EA por chart/simbolo
- Las senales ignoradas quedan en status `claimed` (nunca se devuelven a `available`)

---

## 8. Parametros de Configuracion Completos

### API
| Input | Default | Descripcion |
|-------|---------|-------------|
| API_URL | http://bxlite.local/api/ | URL base del servidor |
| USER_ID | 1 | ID del usuario |

### Trading
| Input | Default | Descripcion |
|-------|---------|-------------|
| TICKER_SYMBOL | EURUSD | Simbolo a operar |
| RISK_PERCENT | 2.0 | % del balance a arriesgar por trade |
| POLL_INTERVAL | 30 | Segundos entre consultas |
| MAX_SPREAD | 500.0 | Spread maximo en points |
| PRICE_TOLERANCE_POINTS | 50 | Tolerancia para market vs pending (points) |
| PRICE_TOLERANCE_PERCENT | 0.0 | Tolerancia en % (si > 0 tiene prioridad sobre points) |

### Price Correction
| Input | Default | Descripcion |
|-------|---------|-------------|
| ENABLE_PRICE_CORRECTION | true | Activar correccion futures/CFD |
| MAX_PRICE_DEVIATION | 5.0 | Desviacion maxima permitida (%) |
| MAX_TIMESTAMP_HOURS | 4 | Antiguedad maxima del dato futures |

### Take Profits
| Input | Default | Descripcion |
|-------|---------|-------------|
| TP1_PERCENT | 0.0 | % a cerrar en TP1 |
| TP2_PERCENT | 40.0 | % a cerrar en TP2 |
| TP3_PERCENT | 30.0 | % a cerrar en TP3 |
| TP4_PERCENT | 20.0 | % a cerrar en TP4 |
| TP5_PERCENT | 10.0 | % a cerrar en TP5 (cierra TODO el remanente) |
| BE_LEVEL | 1 | TP al cual mover SL a breakeven (0=nunca) |

### Horario
| Input | Default | Descripcion |
|-------|---------|-------------|
| ENABLE_TIME_FILTER | false | Filtro horario |
| START_HOUR | 11 | Hora inicio |
| END_HOUR | 13 | Hora fin |

### Ordenes
| Input | Default | Descripcion |
|-------|---------|-------------|
| MAGIC_NUMBER | 12345 | Identificador de ordenes del EA |
| TRADE_COMMENT | TelegramSignal | Comentario en ordenes |

### Stop Loss
| Input | Default | Descripcion |
|-------|---------|-------------|
| ENABLE_CODE_STOP | false | SL por codigo vs broker |
| SAFETY_FACTOR | 1.5 | Multiplicador de SL de emergencia |

### Logging
| Input | Default | Descripcion |
|-------|---------|-------------|
| MIN_LOG_LEVEL | INFO_LVL | Nivel minimo de log (DEBUG_LVL para ver todo) |

---

## 9. Codigos de Salida (Exit Levels)

| Codigo | Significado |
|--------|-------------|
| 1-5 | Cerrado en TP1-TP5 |
| 0 | Cerrado por Stop Loss (broker) |
| -1 | Safety stop o code stop |
| -998 | TPs o SL invalidos |
| -999 | Error de ejecucion, price correction, spread, volumen, o ORDER_CANCELLED |

## 10. Codigos de Cierre (Close Reasons)

| Codigo | Descripcion |
|--------|-------------|
| CLOSED_COMPLETE | Todos los TPs alcanzados |
| CLOSED_STOPLOSS | Stop loss del broker |
| CLOSED_CODE_STOP | SL por codigo (ENABLE_CODE_STOP) |
| CLOSED_SAFETY_STOP | Safety factor SL |
| CLOSED_EXTERNAL | Cierre manual, mobile, web, u otro expert |
| ORDER_CANCELLED | Orden pendiente cancelada (manual o expiracion) |
| INVALID_TPS | TPs incompletos en la senal |
| INVALID_STOPLOSS | SL invalido en la senal |
| PRICE_CORRECTION_ERROR | Fallo Yahoo Finance / correccion invalida |
| SPREAD_TOO_HIGH | Spread excede MAX_SPREAD |
| VOLUME_ERROR | No se pudo calcular volumen |
| EXECUTION_FAILED | Broker rechazo la orden |
