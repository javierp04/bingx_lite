# PRD - BingX Lite: Sistema de Gestión de Señales de Trading

Documento de producto (PRD) que detalla la funcionalidad completa del sistema BingX Lite, un sistema de gestión de señales de trading multi-plataforma.

---

## 1. Visión General del Producto

**BingX Lite** es una plataforma web que automatiza el flujo completo de señales de trading: desde la recepción de señales vía Telegram y TradingView, pasando por su análisis con inteligencia artificial, hasta la ejecución automática de operaciones en el exchange BingX (spot y futuros) y en MetaTrader 5 (forex, índices, commodities).

**Stack Tecnológico:**
- **Backend:** PHP 7.4 + CodeIgniter 3 (MVC)
- **Base de datos:** MySQL/MariaDB
- **APIs externas:** BingX Exchange, OpenAI/Claude (análisis IA), Yahoo Finance (precios futuros)
- **Integraciones:** Telegram Bot Webhook, TradingView Webhook, MetaTrader 5 Expert Advisors
- **Deploy:** GitHub Actions → SSH a servidor de producción (`2bunnylabs.com`)

---

## 2. Usuarios y Roles

| Rol | Capacidades |
|-----|-------------|
| **Admin** | Gestión de tickers disponibles, visualización de señales de Telegram, logs del sistema, panel de simulación, gestión de usuarios |
| **User** | Dashboard de trading personal, suscripción a tickers, seguimiento de señales y trades, gestión de API keys y estrategias |

---

## 3. Módulos Funcionales

### 3.1. Ingesta de Señales desde Telegram (TradeReader)

**Flujo completo:**
1. Un analista publica en el canal de Telegram "AT VIP Canal" un mensaje con formato: `Sentimiento #TICKER URL_TRADINGVIEW`
2. El webhook de Telegram (`POST /tradereader/run`) recibe el mensaje
3. El sistema extrae el ticker (ej: `#EURUSD`) y la URL de TradingView
4. Descarga la imagen del gráfico desde TradingView
5. **Pipeline de procesamiento automático:**
   - **Cropping:** Detecta y recorta la zona relevante del gráfico (caja verde/roja)
   - **Análisis IA:** Envía la imagen recortada a OpenAI o Claude para extraer niveles de precio
   - **Transformación:** Convierte la respuesta de IA en datos estructurados (op_type, entry, stoploss[], tps[])
6. Almacena la señal maestra en `telegram_signals`
7. Distribuye copias a cada usuario suscrito al ticker en `user_telegram_signals`

**Datos extraídos por la IA:**
```json
{
  "op_type": "LONG|SHORT",
  "stoploss": [SL1, SL2],
  "entry": precio_entrada,
  "tps": [TP1, TP2, TP3, TP4, TP5]
}
```

**Tickers soportados:** EURUSD, USDBRL, BTCUSD, GC (Oro), CL (Petróleo), ES (S&P 500), NQ (NASDAQ 100), RTY (Russell 2000), ZS (Soja), VIX

**Estados de señal maestra:** `pending` → `cropping` → `analyzing` → `completed` | `failed_crop` | `failed_analysis` | `failed_download`

---

### 3.2. Ingesta de Señales desde TradingView (Webhook)

**Flujo:**
1. TradingView envía alerta JSON a `POST /webhook/tradingview`
2. El sistema valida campos requeridos: `strategy_id`, `ticker`, `timeframe`, `action`
3. Identifica la estrategia y usuario asociados
4. Ejecuta la orden directamente en BingX via API (spot o futuros)
5. Registra el trade en la tabla `trades`

**Campos del webhook:**
- `strategy_id` - Identificador de estrategia (ej: `FUT_BTC_H1_GANN`)
- `ticker` - Par a operar (ej: `BTCUSDT`)
- `action` - `BUY` o `SELL`
- `quantity` - Cantidad a operar
- `leverage` - Apalancamiento (para futuros)
- `environment` - `production` o `sandbox`
- `position_id` - ID único de posición

---

### 3.3. Ejecución en BingX (BingxApi Library)

**Capacidades:**
- **Spot:** Órdenes de compra/venta al mercado
- **Futuros:** Órdenes con apalancamiento (hasta 16x según datos observados)
- **Entornos:** Producción (`open-api.bingx.com`) y Sandbox (`open-api-vst.bingx.com`, solo futuros)
- **Operaciones:** Consulta de balances, precios en tiempo real, ejecución de órdenes, consulta de posiciones
- **Formato de símbolos:** Convierte `BTCUSDT` → `BTC-USDT` para la API de BingX

**Estrategias activas registradas (BingX):**
- BTC H1 RSI Trend Aligned, BTC H1 Gann HiLo, BTC H1 TEMA ST Channel (Over/Below MA)
- ETH H1 URSI Trend, ETH H1 TEMA Supertrend, ETH H1 Gann HiLo

---

### 3.4. Ejecución en MetaTrader 5 (Expert Advisors)

#### 3.4.1. EA_Signals.mq5 (EA de Señales ATVIP)

**Propósito:** Ejecuta señales del canal de Telegram (procesadas por IA) en MetaTrader 5.

**Flujo:**
1. El EA hace polling cada 30s: `GET /api/signals/{user_id}/{ticker}`
2. El servidor devuelve la señal más antigua con estado `available` y la marca como `claimed`
3. El EA ejecuta la operación con gestión de riesgo (% del balance)
4. Reporta apertura: `POST /api/signals/{id}/open`
5. Gestiona múltiples Take Profits (TP1-TP5) con cierres parciales
6. Reporta progreso en cada TP: `POST /api/signals/{id}/progress`
7. Al cerrar la posición: `POST /api/signals/{id}/close`

**Características clave:**
- **Gestión de riesgo:** Calcula volumen basado en % de balance y distancia al stop loss
- **Cierres parciales:** TP1=0%, TP2=40%, TP3=30%, TP4=20%, TP5=10% (cierra todo el remanente)
- **Breakeven:** Mueve SL a precio de entrada al alcanzar TP configurado (BE_LEVEL)
- **Corrección de precios:** Usa Yahoo Finance para alinear precios CFD con futuros reales
- **Restricción:** Solo una posición activa a la vez por instancia de EA
- **Safety Stop:** Factor de emergencia (1.5x) como protección adicional

**Ciclo de vida de señal por usuario:** `available` → `claimed` → `open` → `closed`

#### 3.4.2. EA_TV.mq5 (EA de TradingView)

**Propósito:** Ejecuta señales de TradingView directamente en MetaTrader 5.

**Flujo:**
1. Polling cada 5s: `GET /metatrader/pending_signals?user_id={ID}&symbol={SYMBOL}`
2. Procesa múltiples señales por ciclo
3. Ejecuta órdenes a mercado con SL/TP del servidor
4. Confirma ejecución: `POST /metatrader/confirm_execution`

**Diferencias con EA_Signals:**
- Puede procesar múltiples señales por ciclo (sin restricción de posición única)
- No gestiona TPs parciales ni breakeven (delega al broker)
- Diseño stateless: solo abre posiciones, no las monitorea

---

### 3.5. Dashboard Principal (`/dashboard`)

**Funcionalidad:**
- Muestra todas las operaciones abiertas del usuario (BingX y MetaTrader)
- Filtro por plataforma (`bingx` / `metatrader`)
- **Actualización en tiempo real de PNL** para trades de BingX (consulta precios vía API)
- Los trades de MetaTrader NO tienen PNL en tiempo real (no hay API de precio disponible)
- Muestra estrategia asociada, símbolo, lado, precio de entrada, PNL actual
- Panel de simulación para admin (puede simular webhooks)

---

### 3.6. My Trading - Dashboard del Usuario (`/my_trading`)

Tiene 3 tabs principales:

#### Tab "Active" (por defecto)
- Vista de señales de trading del usuario con filtros:
  - **Status:** available, claimed, open, closed, etc.
  - **Ticker:** Filtrar por instrumento
  - **Date range:** 7 días, 30 días, etc.
  - **PNL:** Filtrar por resultado
- Muestra datos de ejecución real (precio de entrada, SL, volumen, PNL)
- Link a detalle de cada señal/trade

#### Tab "Signals"
- Historial completo de señales recibidas con filtros por ticker, status, fechas
- Permite ver análisis de IA, imagen del gráfico, datos de ejecución

#### Tab "Tickers"
- Gestión de suscripciones a tickers
- Agregar/eliminar tickers de la lista de available_tickers
- **Mapeo MT:** Cada ticker tiene un equivalente en MetaTrader (ej: GC → XAUUSD, ES → US500, NQ → US100)
- Activar/desactivar recepción de señales por ticker

---

### 3.7. Gestión de Estrategias (`/strategies`)

- CRUD completo de estrategias de trading
- Campos: nombre, ID único, tipo (spot/futures/forex/indices/commodities), plataforma (bingx/metatrader), descripción, imagen
- Cada estrategia está vinculada a un usuario
- Se activan/desactivan independientemente

---

### 3.8. Gestión de Trades (`/trades`)

- Listado de todos los trades (abiertos y cerrados)
- Detalle por trade: datos del webhook, PNL, precios, estrategia
- Cierre manual de trades
- Soporta trades de ambas plataformas (BingX y MetaTrader)

---

### 3.9. Gestión de API Keys (`/apikeys`)

- CRUD de credenciales API de BingX (api_key + api_secret)
- Vinculadas a usuario
- Necesarias para ejecutar órdenes en BingX

---

### 3.10. Administración de Tickers Disponibles (`/available_tickers` - Admin)

- CRUD de tickers que pueden ser suscritos por usuarios
- Campos: símbolo, nombre, decimales de display, estado activo
- Controla qué instrumentos están disponibles en el sistema

---

### 3.11. Señales de Telegram - Admin (`/telegram_signals`)

- Vista administrativa de todas las señales recibidas de Telegram
- Ver imagen original, imagen recortada, datos de análisis
- Eliminar señales, limpieza masiva
- Monitoreo del pipeline de procesamiento

---

### 3.12. Logs del Sistema (`/systemlogs`)

- Registro de todas las acciones del sistema
- Búsqueda y filtrado
- Limpieza de logs antiguos
- Registra: webhooks recibidos, errores de procesamiento, ejecuciones de trades, etc.

---

### 3.13. API para MetaTrader EA (`/api/...`)

| Endpoint | Método | Descripción |
|----------|--------|-------------|
| `/api/signals/{user_id}/{ticker}` | GET | Obtener señal disponible (la marca como claimed) |
| `/api/signals/{id}/open` | POST | Reportar apertura de posición |
| `/api/signals/{id}/progress` | POST | Reportar progreso (TP alcanzado) |
| `/api/signals/{id}/close` | POST | Reportar cierre de posición |
| `/api/fut_price/{symbol}` | GET | Precio de futuros vía Yahoo Finance |
| `/api/trades/open` | POST | Abrir trade desde EA autónomo |
| `/api/trades/{id}/update` | POST | Actualizar trade |
| `/api/trades/{id}/close` | POST | Cerrar trade |

---

### 3.14. API para MetaTrader TradingView (`/metatrader/...`)

| Endpoint | Método | Descripción |
|----------|--------|-------------|
| `/metatrader/pending_signals` | GET | Obtener señales pendientes para EA_TV |
| `/metatrader/confirm_execution` | POST | Confirmar ejecución de señal |
| `/metatrader/webhook` | POST | Recibir webhook de TradingView para MT |

---

### 3.15. Debug y Testing (`/debug/...`)

- Simulador de webhooks de TradingView
- Simulador de webhooks de Telegram
- Tests de conectividad BingX (balances spot/futuros, precios)
- Generación de señales de prueba

---

## 4. Modelo de Datos

### Tablas principales:

| Tabla | Propósito | Registros aprox. |
|-------|-----------|-----------------|
| `users` | Usuarios del sistema | 2 |
| `api_keys` | Credenciales BingX por usuario | 2 |
| `strategies` | Estrategias de trading | 11 |
| `available_tickers` | Catálogo de tickers disponibles | 10 |
| `user_selected_tickers` | Suscripciones de usuario a tickers (con mapeo MT) | 6 |
| `telegram_signals` | Señales maestras de Telegram | 91+ |
| `user_telegram_signals` | Señales por usuario (ciclo de vida individual) | 54+ |
| `trades` | Trades ejecutados (BingX + MT) | 99+ |
| `mt_signals` | Señales para MetaTrader (vía webhook TV) | - |
| `system_logs` | Logs del sistema | - |

### Relaciones clave:
```
telegram_signals (1) ──→ (N) user_telegram_signals
users (1) ──→ (N) user_selected_tickers
users (1) ──→ (N) strategies
users (1) ──→ (N) trades
users (1) ──→ (N) api_keys
strategies (1) ──→ (N) trades
available_tickers (1) ──→ (N) user_selected_tickers
```

---

## 5. Flujos de Datos (Diagrama de Alto Nivel)

```
┌─────────────┐     Webhook      ┌──────────────────┐
│  Telegram    │ ──────────────→  │  TradeReader.php  │
│  Canal VIP   │                  │  (Pipeline IA)    │
└─────────────┘                  └────────┬─────────┘
                                          │
                                          ▼
                                 ┌──────────────────┐
                                 │ telegram_signals  │
                                 │ user_signals      │
                                 └────────┬─────────┘
                                          │
                              ┌───────────┴───────────┐
                              ▼                       ▼
                    ┌──────────────┐        ┌──────────────┐
                    │  EA_Signals  │        │   Web UI     │
                    │  (MT5)       │        │  My Trading  │
                    │  Polling API │        │  Dashboard   │
                    └──────────────┘        └──────────────┘

┌─────────────┐     Webhook      ┌──────────────────┐     API     ┌───────────┐
│ TradingView │ ──────────────→  │  Webhook.php      │ ─────────→ │  BingX    │
│  Alerts     │                  │  Webhook_proc.php │            │  Exchange │
└─────────────┘                  └────────┬─────────┘            └───────────┘
                                          │
                                          ▼
                              ┌──────────────────┐
                              │     trades       │
                              └──────────────────┘

┌─────────────┐     Webhook      ┌──────────────────┐
│ TradingView │ ──────────────→  │  Metatrader.php   │
│  (para MT)  │                  │  mt_signals       │
└─────────────┘                  └────────┬─────────┘
                                          │
                                          ▼
                                 ┌──────────────┐
                                 │   EA_TV.mq5  │
                                 │   (MT5)      │
                                 │  Polling API │
                                 └──────────────┘
```

---

## 6. Autenticación y Seguridad

- **Login/Logout:** Basado en sesiones de CodeIgniter (`Auth.php`)
- **Protección de rutas:** Cada controlador verifica `session->userdata('logged_in')`
- **CSRF:** Actualmente deshabilitado
- **Credenciales hardcodeadas:** DB password, OpenAI API key en archivos de config (riesgo de seguridad)
- **Webhooks sin autenticación:** Los endpoints de Telegram y TradingView no validan tokens/secretos

---

## 7. Despliegue

- **Desarrollo:** XAMPP local (Windows), acceso vía `http://localhost/bingx_lite/`
- **Producción:** Servidor Linux (`2bunnylabs.com`), ruta `/var/www/html/bx-trade`
- **CI/CD:** GitHub Actions → SSH deploy (git pull en main)
- **No requiere build:** PHP se ejecuta directamente

---

## 8. Métricas Observadas (datos del SQL dump)

- **91 señales de Telegram** procesadas (Oct 14-24, 2025)
- **~10 señales diarias** (una por cada ticker activo)
- **99 trades ejecutados** en BingX (Sep-Oct 2025)
- **Instrumentos más operados:** BTCUSDT, ETHUSDT (futuros con apalancamiento 8x-16x)
- **Tasa de éxito del pipeline IA:** ~97% (2 fallos de análisis en 91 señales)
- **Canal fuente:** "AT VIP Canal" de Telegram, analista: Favio Schneeberger
