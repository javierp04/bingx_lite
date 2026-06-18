#property copyright "TelegramSignals"
#property version   "10.22"
#property description "Reliability + gates asset-agnostic (TP1) + CSV trade journal (dataset + live)"
// v10.22: reporta acct_balance + sl_risk_per_lot en el snapshot -> el detalle muestra la formula
//         de volumen completa (riesgo $ = RISK% x balance) / (riesgo $/lote) = lots, que cierra.
// v10.21: reporta a BD el snapshot completo del trade (objetos snapshot/ea_config en ReportOpen) +
//         telemetria del proceso de correccion (objeto correction: futuro vs vela CFD, verificacion de
//         alineacion de velas). Permite materializar el journal/analisis en tablas consultables.
#define EA_VERSION "10.22"
// v10.20: (E) si el broker rechaza la pendiente por caer dentro de STOPS_LEVEL (INVALID_STOPS),
//         fallback a MARKET (self-gateado: en stops level 0 nunca dispara). Recalibra K_STOP 0.02->0.05
//         (ahora >= M_SLIP) y M_SLIP 0.05->0.04. Slippage cap ahora OPCIONAL (ENABLE_SLIP_CHECK, OFF
//         por defecto): OFF = deviation amplia (alta liquidez), el tol se sigue registrando en journal.
// v10.19: recalibra bandas de order type: K_STOP 0.30->0.02 (no adelantarse al breakout, respeta el
//         stop del analista) y K_LIMIT 0.15->0.10 (cubre latencia señal->ejecucion). stop-estricto/limit-laxo.
// v10.18: validacion de spread ahora OPCIONAL (ENABLE_SPREAD_CHECK, OFF por defecto) y coeficiente
//         recalibrado 0.40 -> 0.05. El spread se sigue registrando en el journal aunque no valide.
// v10.17: cierre con SL ya en breakeven se reporta CLOSED_BREAKEVEN (no CLOSED_STOPLOSS perdedor);
//         no se recrea un state file fantasma (signalId=0) al completar el trade.
// v10.16: valida op_type (LONG/SHORT exacto) y entry>0 antes de operar. Rechaza señal malformada
//         (INVALID_OPTYPE / INVALID_ENTRY) en vez de abrir al reves o con entry=0.
// v10.15: reparto de salidas en lotes ENTEROS calculado al abrir (metodo de mayor resto). Cada TP
//         cierra un tramo fijo >= 1 step y el ultimo lleva el remanente a 0: el "puchito" sub-minimo
//         NO nace (se elimina dust-close, reintentos y recalculo en caliente). COMPLETE solo con
//         posicion cerrada (heredado de v10.14). Cualquier cierre por SL/BE liquida todo el remanente.

// LIBRERÍAS
#include <Trade\Trade.mqh>
#include <Trade\PositionInfo.mqh>

// CONSTANTES
#define HTTP_TIMEOUT 5000

// Deviation "sin cap" cuando ENABLE_SLIP_CHECK=false: valor grande en points (efectivamente ilimitado
// para cualquier instrumento) -> el market no se rechaza por slippage. Mirrors el gate de spread.
#define SLIP_NO_CAP_POINTS 1000000

// Exit-level codes reportados a la API (ver CLAUDE.md). >0 = cerro en TPn.
#define EXIT_STOP      -1     // cerrado por Stop Loss (broker) o code-stop
#define EXIT_INVALID   -998   // señal invalida (SL/TPs faltantes): nunca operó
#define EXIT_ERROR     -999   // error de ejecucion/correccion/gate: nunca operó

// close_reason: el server (Telegram_signals_model::is_failure_reason) matchea estos
// strings EXACTOS para clasificar fallo vs cierre real. NO cambiar los valores.
#define REASON_COMPLETE        "CLOSED_COMPLETE"
#define REASON_STOPLOSS        "CLOSED_STOPLOSS"
#define REASON_BREAKEVEN       "CLOSED_BREAKEVEN"
#define REASON_MANUAL          "CLOSED_MANUAL"
#define REASON_EXTERNAL        "CLOSED_EXTERNAL"
#define REASON_CODE_STOP       "CLOSED_CODE_STOP"
#define REASON_SAFETY_STOP     "CLOSED_SAFETY_STOP"
#define REASON_ORDER_CANCELLED "ORDER_CANCELLED"
#define REASON_INVALID_SL      "INVALID_STOPLOSS"
#define REASON_INVALID_TPS     "INVALID_TPS"
#define REASON_INVALID_OPTYPE  "INVALID_OPTYPE"
#define REASON_INVALID_ENTRY   "INVALID_ENTRY"
#define REASON_PRICE_CORR_ERR  "PRICE_CORRECTION_ERROR"
#define REASON_SPREAD_HIGH     "SPREAD_TOO_HIGH"
#define REASON_VOLUME_ERR      "VOLUME_ERROR"
#define REASON_SL_TOO_CLOSE    "SL_TOO_CLOSE"
#define REASON_EXEC_FAILED     "EXECUTION_FAILED"
#define REASON_TIME_CLOSE      "CLOSED_TIME"        // cierre por horario (cierre real, no fallo)

// Enums
enum LogLevel { DEBUG_LVL, INFO_LVL, WARNING_LVL, ERROR_LVL };
enum APIResult { API_SUCCESS, API_HTTP_ERROR, API_NETWORK_ERROR };

// INPUTS
input group "=== API Configuration ==="
input string    API_URL = "https://bx-trade.2bunnylabs.com/api/";
input int       USER_ID = 1;

input group "=== Trading Settings ==="
input string    TICKER_SYMBOL = "EURUSD";
input double    RISK_PERCENT = 2.0;
input int       POLL_INTERVAL = 10;
input group "=== Gates asset-agnostic (anclados a TP1) ==="
input double    K_STOP_RATIO   = 0.05;   // banda market lado STOP (favorable): respeta la confirmacion del analista; >= M_SLIP_RATIO
input double    K_LIMIT_RATIO  = 0.15;   // banda market lado LIMIT (adverso): cubre latencia señal->ejecucion — debe ser > M_SLIP_RATIO
input bool      ENABLE_SLIP_CHECK = false; // OFF por defecto: deviation amplia (alta liquidez). ON = cap = M_SLIP_RATIO*T1
input double    M_SLIP_RATIO   = 0.04;   // si ENABLE_SLIP_CHECK: tope de slippage (deviation) — debe ser < K_LIMIT_RATIO
input bool      ENABLE_SPREAD_CHECK = false; // OFF por defecto (brokers/horarios de alta liquidez)
input double    C_SPREAD_RATIO = 0.05;   // si ENABLE_SPREAD_CHECK: rechaza si spread > C_SPREAD_RATIO * T1

input group "=== Price Correction ==="
input bool      ENABLE_PRICE_CORRECTION = true;
input double    MAX_PRICE_DEVIATION = 5.0;
input int       MAX_TIMESTAMP_HOURS = 4;

input group "=== Take Profit Settings ==="
input double    TP1_PERCENT = 20.0;
input double    TP2_PERCENT = 30.0;
input double    TP3_PERCENT = 20.0;
input double    TP4_PERCENT = 20.0;
input double    TP5_PERCENT = 10.0;
input int       BE_LEVEL = 2;                       // 0=never, 1=TP1, 2=TP2, etc.

input group "=== Trading Hours ==="
input bool      ENABLE_TIME_FILTER = false;
input int       START_HOUR = 11;
input int       END_HOUR = 13;

input group "=== Order Settings ==="
input int       MAGIC_NUMBER = 12345;
input string    TRADE_COMMENT = "TelegramSignal";

input group "=== Stop Loss Management ==="
input bool      ENABLE_CODE_STOP = false;
input double    SAFETY_FACTOR = 1.5;

input group "=== Cortes por horario (hora NY, DST automatico) ==="
input bool      ENABLE_PENDING_CUTOFF = true;   // cancelar la pendiente NO ejecutada al llegar la hora
input int       CUTOFF_HOUR_NY = 16;            // hora NY de corte de pendientes
input int       CUTOFF_MIN_NY  = 30;            // minuto NY de corte de pendientes
input bool      ENABLE_FORCE_CLOSE = true;      // cerrar la posicion a mercado al llegar la hora (sin importar precio)
input int       CLOSE_HOUR_NY = 20;             // hora NY de cierre forzado
input int       CLOSE_MIN_NY  = 0;              // minuto NY de cierre forzado

input group "=== Logging ==="
input LogLevel  MIN_LOG_LEVEL = INFO_LVL;   // DEBUG_LVL = ver todo, INFO_LVL = normal

// ESTRUCTURAS SIMPLIFICADAS
struct SymbolSpecs {
    double point, tickSize, tickValue, contractSize;
    double minVolume, maxVolume, stepVolume;
    bool isValid;
};

struct OptimizedTPState {
    // CORE
    bool isActive;
    int signalId;
    ulong ticket;
    ulong positionID;
    string direction;

    // VOLUMES
    double originalVolume;
    double currentVolume;
    double totalClosedVolume;
    double closedPercent;

    // LEVELS
    int currentLevel;
    bool levelFlags[6];
    double levelVolumes[6];   // lotes a cerrar en cada TP (1..5), calculados en lotes enteros al abrir
    bool slMovedToBE;

    // PRICES
    double entry;
    double originalSL;
    double currentSL;
    double tp1, tp2, tp3, tp4, tp5;

    // TIME (UTC en que se estableció la orden/posición: ancla de los cortes por horario NY)
    datetime tradeStartUtc;
    datetime cutoffTargetUtc;   // UTC del corte de pendiente, precalculado al abrir (0 = off / sin trade)
    datetime closeTargetUtc;    // UTC del cierre forzado, precalculado al abrir (0 = off / sin trade)
};

struct JournalRecord {
    string  ts_signal;
    int     signal_id;
    string  dir;
    bool    corr_on;
    double  corr_factor, entry_raw, sl_raw;
    double  entry, sl, tp1, tp2, tp3, tp4, tp5, rdist, t1;
    double  spread_real, spread_tol;
    double  price_signal, dist_entry;
    string  side;
    double  k_band;
    string  order_type;
    double  real_entry, slip_real, slip_tol, real_volume;
    double  stops_min, sl_dist;
    double  acct_balance, sl_risk_per_lot;   // para la fórmula de volumen (detalle): balance + riesgo $/lote
    // --- Telemetria del proceso de correccion (para tabla ea_price_corrections) ---
    string  corr_status;        // "" (no corrido) / "OK" / "ERROR"
    string  corr_error_stage;   // FETCH/INVALID_DATA/STALE_TIMESTAMP/NO_BAR/INVALID_CFD/DEVIATION_TOO_HIGH
    double  fut_price;          // last_close del futuro (Yahoo)
    string  fut_candle_time;    // vela del futuro (UTC)
    double  mt5_price;          // iClose de la vela CFD usada
    string  mt5_bar_time;       // iTime real de la vela usada (reloj broker)
    int     mt5_bar_index;      // barIndex de iBarShift
    int     broker_offset_sec;  // TimeCurrent - TimeGMT
    string  target_broker_time; // ts_epoch + offset (lo que se busco)
    int     bar_gap_sec;        // mt5_bar_time - target_broker_time (0 = misma vela)
    bool    candles_aligned;    // |bar_gap_sec| <= tolerancia
    double  deviation_pct;      // |factor-1|*100
    int     timestamp_age_sec;  // antiguedad del dato del futuro
};

struct TPConfig {
    double percents[5];
    double totalPercent;
    bool isValid;
};

// Conjunto de precios de una señal (crudo o corregido). Evita firmas con 8+ doubles sueltos.
struct PriceSet {
    double entry;
    double sl1, sl2;
    double tp1, tp2, tp3, tp4, tp5;
};

struct APIResponse {
    APIResult result;
    int httpCode;
    string message;
    string data;
};

// Resultado de analisis de historial
struct HistoryCloseResult {
    string reason;
    int exitLevel;
    double dealPrice;
    double dealProfit;
    bool hasDealData;
};

// VARIABLES GLOBALES
CTrade trade;
CPositionInfo position;
OptimizedTPState currentTP;
JournalRecord currentJournal;
string currentSymbol;

// Cache para performance
static SymbolSpecs cachedSpecs;
static string cachedSymbol = "";
static string signalsBaseUrl;
static string futPriceBaseUrl;

// CLASE JSON PARSER (lectura)
class SimpleJSONParser {
private:
    string json;

    bool ValidateNumber(string value) {
        int len = StringLen(value);
        if(len == 0) return false;
        int dots = 0, digits = 0;
        for(int i = 0; i < len; i++) {
            ushort c = StringGetCharacter(value, i);
            if(c >= '0' && c <= '9') { digits++; continue; }
            if(c == '.') { dots++; if(dots > 1) return false; continue; }   // un solo punto decimal
            if(c == '-' || c == '+') { if(i != 0) return false; continue; } // signo solo al inicio
            return false;                                                   // cualquier otro caracter
        }
        return (digits > 0);   // al menos un dígito (rechaza "", ".", "+", "-")
    }

    string ExtractNumericValue(string key) {
        string search = "\"" + key + "\":";
        int pos = StringFind(json, search);
        if(pos == -1) return "";

        pos += StringLen(search);
        int endPos = StringFind(json, ",", pos);
        if(endPos == -1) endPos = StringFind(json, "}", pos);

        return StringSubstr(json, pos, endPos - pos);
    }

public:
    SimpleJSONParser(string jsonStr) : json(jsonStr) {}

    double GetDouble(string key, double defaultValue = 0.0) {
        string value = ExtractNumericValue(key);
        return (ValidateNumber(value)) ? StringToDouble(value) : defaultValue;
    }

    int GetInt(string key, int defaultValue = 0) {
        string value = ExtractNumericValue(key);
        return (ValidateNumber(value)) ? (int)StringToInteger(value) : defaultValue;
    }

    // GetLong: como GetInt pero sin truncar a 32 bits (ej. epoch unix, que excede int en 2038).
    long GetLong(string key, long defaultValue = 0) {
        string value = ExtractNumericValue(key);
        return (ValidateNumber(value)) ? StringToInteger(value) : defaultValue;
    }

    string GetString(string key, string defaultValue = "") {
        string search = "\"" + key + "\":\"";
        int pos = StringFind(json, search);
        if(pos == -1) return defaultValue;

        pos += StringLen(search);
        int endPos = StringFind(json, "\"", pos);

        return StringSubstr(json, pos, endPos - pos);
    }

    // GetArrayDouble: busca coma Y corchete, usa el primero
    double GetArrayDouble(string arrayKey, int index, double defaultValue = 0.0) {
        string search = "\"" + arrayKey + "\":[";
        int pos = StringFind(json, search);
        if(pos == -1) return defaultValue;
        pos += StringLen(search);
        for(int i = 0; i < index; i++) {
            pos = StringFind(json, ",", pos);
            if(pos == -1) return defaultValue;
            pos++;
        }
        while(pos < StringLen(json) && (StringGetCharacter(json, pos) == ' ' || StringGetCharacter(json, pos) == '\t' || StringGetCharacter(json, pos) == '\n' || StringGetCharacter(json, pos) == '\r')) pos++;
        int commaPos = StringFind(json, ",", pos);
        int bracketPos = StringFind(json, "]", pos);
        int endPos = (commaPos != -1 && bracketPos != -1) ? (commaPos < bracketPos ? commaPos : bracketPos) : (commaPos != -1 ? commaPos : bracketPos);
        if(endPos == -1) return defaultValue;
        string value = StringSubstr(json, pos, endPos - pos);
        StringReplace(value, " ", "");
        StringReplace(value, "\t", "");
        StringReplace(value, "\n", "");
        StringReplace(value, "\r", "");
        return ValidateNumber(value) ? StringToDouble(value) : defaultValue;
    }
};

// CLASE JSON BUILDER (escritura)
class JsonBuilder {
private:
    string pairs[];
    int count;

    // Escapa los chars que romperian el JSON (y el TSV del outbox del v11, que asume bodies de una
    // sola linea sin tabs). El backslash va PRIMERO para no re-escapar lo que se agrega despues.
    string Escape(string s) {
        StringReplace(s, "\\", "\\\\");
        StringReplace(s, "\"", "\\\"");
        StringReplace(s, "\r", "\\r");
        StringReplace(s, "\n", "\\n");
        StringReplace(s, "\t", "\\t");
        return s;
    }

public:
    JsonBuilder() : count(0) { ArrayResize(pairs, 0); }

    void AddBool(string key, bool value) {
        ArrayResize(pairs, count + 1);
        pairs[count++] = "\"" + key + "\":" + (value ? "true" : "false");
    }

    void AddInt(string key, long value) {
        ArrayResize(pairs, count + 1);
        pairs[count++] = "\"" + key + "\":" + IntegerToString(value);
    }

    void AddDouble(string key, double value, int decimals = 5) {
        ArrayResize(pairs, count + 1);
        pairs[count++] = "\"" + key + "\":" + DoubleToString(value, decimals);
    }

    void AddString(string key, string value) {
        ArrayResize(pairs, count + 1);
        pairs[count++] = "\"" + key + "\":\"" + Escape(value) + "\"";
    }

    void AddRaw(string key, string rawJson) {
        ArrayResize(pairs, count + 1);
        pairs[count++] = "\"" + key + "\":" + rawJson;
    }

    string Build() {
        string result = "{";
        for(int i = 0; i < count; i++) {
            if(i > 0) result += ",";
            result += pairs[i];
        }
        result += "}";
        return result;
    }
};

// LOGGING SIMPLIFICADO
void Log(LogLevel level, string category, string message) {
    if(level < MIN_LOG_LEVEL) return;

    string prefix = "";
    switch(level) {
        case DEBUG_LVL:   prefix = "[DEBUG]"; break;
        case INFO_LVL:    prefix = "[INFO]"; break;
        case WARNING_LVL: prefix = "[WARN]"; break;
        case ERROR_LVL:   prefix = "[ERROR]"; break;
    }
    Print(prefix, " [", category, "] ", message);
}

// ==========================================
// HELPER FUNCTIONS (deduplicados)
// ==========================================

// Obtiene precio actual segun direccion del trade
double GetCurrentPrice(string direction) {
    return (direction == "LONG") ?
        SymbolInfoDouble(currentSymbol, SYMBOL_BID) :
        SymbolInfoDouble(currentSymbol, SYMBOL_ASK);
}

// Busca posicion propia por Magic+Symbol (SIN depender del comment:
// muchos brokers reescriben el comment al ejecutar una pendiente).
// El EA maneja una sola posicion a la vez, asi que magic+symbol es univoco.
// Deja position seleccionada si la encuentra.
bool FindOwnPosition() {
    for(int i = 0; i < PositionsTotal(); i++) {
        if(position.SelectByIndex(i)) {
            if(position.Symbol() == currentSymbol &&
               position.Magic() == MAGIC_NUMBER) {
                return true;
            }
        }
    }
    return false;
}

// Trunca string para logging
string TruncLog(string data, int maxLen = 500) {
    return StringSubstr(data, 0, maxLen);
}

// El EA esta ocupado: tiene posicion activa o una orden pendiente esperando ejecucion.
bool HasTrade() {
    return (currentTP.isActive || currentTP.ticket > 0);
}

// Cierre de ciclo de trade: resetea el estado en memoria y borra el archivo de estado.
// InitTPState y ClearState siempre van juntos al terminar un trade.
void EndTrade() {
    InitTPState();
    ClearState();
}

// ==========================================
// STATE STORE (PERSISTENCIA EN DISCO)
// ==========================================
// Persiste currentTP a MQL5/Files para sobrevivir reinicios del EA/terminal.
// Formato: una sola linea JSON. Se reusa JsonBuilder (write) y SimpleJSONParser (read).

string StateFilePath() {
    return "bxlite_state_" + IntegerToString(USER_ID) + "_" + TICKER_SYMBOL + ".json";
}

void SaveState() {
    JsonBuilder jb;
    jb.AddBool("isActive", currentTP.isActive);
    jb.AddInt("signalId", currentTP.signalId);
    jb.AddString("ticket", IntegerToString((long)currentTP.ticket));
    jb.AddString("positionID", IntegerToString((long)currentTP.positionID));
    jb.AddString("direction", currentTP.direction);
    jb.AddDouble("originalVolume", currentTP.originalVolume, 2);
    jb.AddDouble("currentVolume", currentTP.currentVolume, 2);
    jb.AddDouble("totalClosedVolume", currentTP.totalClosedVolume, 2);
    jb.AddDouble("closedPercent", currentTP.closedPercent, 2);
    jb.AddInt("currentLevel", currentTP.currentLevel);
    jb.AddBool("slMovedToBE", currentTP.slMovedToBE);
    jb.AddDouble("entry", currentTP.entry, 5);
    jb.AddDouble("originalSL", currentTP.originalSL, 5);
    jb.AddDouble("currentSL", currentTP.currentSL, 5);
    jb.AddDouble("tp1", currentTP.tp1, 5);
    jb.AddDouble("tp2", currentTP.tp2, 5);
    jb.AddDouble("tp3", currentTP.tp3, 5);
    jb.AddDouble("tp4", currentTP.tp4, 5);
    jb.AddDouble("tp5", currentTP.tp5, 5);
    jb.AddInt("tradeStartUtc", (long)currentTP.tradeStartUtc);
    // levelFlags como array crudo [0/1 x6]
    string flags = "[";
    for(int i = 0; i < 6; i++) {
        if(i > 0) flags += ",";
        flags += (currentTP.levelFlags[i] ? "1" : "0");
    }
    flags += "]";
    jb.AddRaw("levelFlags", flags);

    // levelVolumes como array crudo [v0..v5] (lotes por TP pre-calculados al abrir)
    string vols = "[";
    for(int i = 0; i < 6; i++) {
        if(i > 0) vols += ",";
        vols += DoubleToString(currentTP.levelVolumes[i], 2);
    }
    vols += "]";
    jb.AddRaw("levelVolumes", vols);

    string json = jb.Build();

    int h = FileOpen(StateFilePath(), FILE_WRITE|FILE_TXT|FILE_ANSI);
    if(h == INVALID_HANDLE) {
        Log(ERROR_LVL, "STATE", "No se pudo abrir state file para escritura: " + IntegerToString(GetLastError()));
        return;
    }
    FileWriteString(h, json);
    FileClose(h);
    Log(DEBUG_LVL, "STATE", "Estado persistido: " + TruncLog(json));

    JournalWriteLive();
}

// Devuelve true si habia un estado con trade (activo o pendiente) cargado.
bool LoadState() {
    if(!FileIsExist(StateFilePath())) return false;

    int h = FileOpen(StateFilePath(), FILE_READ|FILE_TXT|FILE_ANSI);
    if(h == INVALID_HANDLE) {
        Log(ERROR_LVL, "STATE", "No se pudo abrir state file para lectura: " + IntegerToString(GetLastError()));
        return false;
    }
    string json = "";
    while(!FileIsEnding(h)) {
        json += FileReadString(h);
    }
    FileClose(h);

    if(StringLen(json) < 2) return false;

    SimpleJSONParser parser(json);
    currentTP.isActive          = (StringFind(json, "\"isActive\":true") > -1);
    currentTP.signalId          = parser.GetInt("signalId", 0);
    currentTP.ticket            = (ulong)StringToInteger(parser.GetString("ticket", "0"));
    currentTP.positionID        = (ulong)StringToInteger(parser.GetString("positionID", "0"));
    currentTP.direction         = parser.GetString("direction", "");
    currentTP.originalVolume    = parser.GetDouble("originalVolume", 0);
    currentTP.currentVolume     = parser.GetDouble("currentVolume", 0);
    currentTP.totalClosedVolume = parser.GetDouble("totalClosedVolume", 0);
    currentTP.closedPercent     = parser.GetDouble("closedPercent", 0);
    currentTP.currentLevel      = parser.GetInt("currentLevel", -2);
    currentTP.slMovedToBE       = (StringFind(json, "\"slMovedToBE\":true") > -1);
    currentTP.entry             = parser.GetDouble("entry", 0);
    currentTP.originalSL        = parser.GetDouble("originalSL", 0);
    currentTP.currentSL         = parser.GetDouble("currentSL", 0);
    currentTP.tp1               = parser.GetDouble("tp1", 0);
    currentTP.tp2               = parser.GetDouble("tp2", 0);
    currentTP.tp3               = parser.GetDouble("tp3", 0);
    currentTP.tp4               = parser.GetDouble("tp4", 0);
    currentTP.tp5               = parser.GetDouble("tp5", 0);
    currentTP.tradeStartUtc     = (datetime)parser.GetLong("tradeStartUtc", 0);
    for(int i = 0; i < 6; i++) {
        currentTP.levelFlags[i] = (parser.GetArrayDouble("levelFlags", i, 0) >= 0.5);
    }
    for(int i = 0; i < 6; i++) {
        currentTP.levelVolumes[i] = parser.GetArrayDouble("levelVolumes", i, 0);
    }

    // Fallback: estado de una version previa (sin levelVolumes) -> recalcular el reparto desde el
    // volumen original para no quedar con todos los tramos en 0 tras adoptar el trade al reiniciar.
    bool hasVols = false;
    for(int i = 1; i <= 5; i++) if(currentTP.levelVolumes[i] > 0) { hasVols = true; break; }
    if(!hasVols && currentTP.originalVolume > 0) {
        SymbolSpecs fbSpecs = GetSymbolSpecs();
        ComputeLevelVolumes(currentTP.originalVolume, fbSpecs, currentTP.levelVolumes);
    }

    // Targets de corte por horario: se recalculan determinísticamente desde tradeStartUtc (no se
    // persisten), así el state file no cambia de schema y un trade adoptado recupera sus cortes.
    SetupTimeCutoffs();

    // signalId>0 indica que habia un trade trackeado (activo o pendiente)
    return (currentTP.signalId > 0);
}

void ClearState() {
    if(FileIsExist(StateFilePath())) {
        FileDelete(StateFilePath());
        Log(DEBUG_LVL, "STATE", "State file eliminado");
    }
    JournalWriteLive();   // refresca el live (sin trade activo queda solo el header)
}

// ==========================================
// CSV JOURNAL (dataset + live)
// ==========================================
string JournalIsoTime() {
    MqlDateTime t; TimeGMT(t);
    return StringFormat("%04d-%02d-%02dT%02d:%02d:%02d", t.year, t.mon, t.day, t.hour, t.min, t.sec);
}

string JournalFilePath() { return "bxlite_journal_" + IntegerToString(USER_ID) + "_" + TICKER_SYMBOL + ".csv"; }
string JournalLivePath() { return "bxlite_live_"    + IntegerToString(USER_ID) + "_" + TICKER_SYMBOL + ".csv"; }

void JournalReset() {
    currentJournal.ts_signal = "";  currentJournal.signal_id = 0;  currentJournal.dir = "";
    currentJournal.corr_on = false; currentJournal.corr_factor = 1.0;
    currentJournal.entry_raw = 0;   currentJournal.sl_raw = 0;
    currentJournal.entry = 0; currentJournal.sl = 0;
    currentJournal.tp1 = 0; currentJournal.tp2 = 0; currentJournal.tp3 = 0; currentJournal.tp4 = 0; currentJournal.tp5 = 0;
    currentJournal.rdist = 0; currentJournal.t1 = 0;
    currentJournal.spread_real = 0; currentJournal.spread_tol = 0;
    currentJournal.price_signal = 0; currentJournal.dist_entry = 0;
    currentJournal.side = ""; currentJournal.k_band = 0; currentJournal.order_type = "";
    currentJournal.real_entry = 0; currentJournal.slip_real = 0; currentJournal.slip_tol = 0; currentJournal.real_volume = 0;
    currentJournal.stops_min = 0; currentJournal.sl_dist = 0;
    currentJournal.acct_balance = 0; currentJournal.sl_risk_per_lot = 0;
    currentJournal.corr_status = ""; currentJournal.corr_error_stage = "";
    currentJournal.fut_price = 0; currentJournal.fut_candle_time = "";
    currentJournal.mt5_price = 0; currentJournal.mt5_bar_time = ""; currentJournal.mt5_bar_index = 0;
    currentJournal.broker_offset_sec = 0; currentJournal.target_broker_time = "";
    currentJournal.bar_gap_sec = 0; currentJournal.candles_aligned = false;
    currentJournal.deviation_pct = 0; currentJournal.timestamp_age_sec = 0;
}

string JournalHeader() {
    return "ts_signal,signal_id,symbol,dir,corr_on,corr_factor,entry_raw,sl_raw,"
         + "entry,sl,tp1,tp2,tp3,tp4,tp5,R,T1,spread_real,spread_tol,"
         + "price_signal,dist_entry,side,k_band,order_type,"
         + "real_entry,slip_real,slip_tol,real_volume,stops_min,sl_dist,"
         + "max_level,vol_closed_pct,be_on,exit_level,close_reason,gross_pnl,last_price,result";
}

string JournalRow(int exitLevel, string closeReason, double grossPnl, double lastPrice, string result) {
    string r = "";
    r += currentJournal.ts_signal + ",";
    r += IntegerToString(currentJournal.signal_id) + ",";
    r += TICKER_SYMBOL + ",";
    r += currentJournal.dir + ",";
    r += (currentJournal.corr_on ? "1" : "0") + ",";
    r += DoubleToString(currentJournal.corr_factor, 6) + ",";
    r += DoubleToString(currentJournal.entry_raw, 5) + ",";
    r += DoubleToString(currentJournal.sl_raw, 5) + ",";
    r += DoubleToString(currentJournal.entry, 5) + ",";
    r += DoubleToString(currentJournal.sl, 5) + ",";
    r += DoubleToString(currentJournal.tp1, 5) + ",";
    r += DoubleToString(currentJournal.tp2, 5) + ",";
    r += DoubleToString(currentJournal.tp3, 5) + ",";
    r += DoubleToString(currentJournal.tp4, 5) + ",";
    r += DoubleToString(currentJournal.tp5, 5) + ",";
    r += DoubleToString(currentJournal.rdist, 5) + ",";
    r += DoubleToString(currentJournal.t1, 5) + ",";
    r += DoubleToString(currentJournal.spread_real, 5) + ",";
    r += DoubleToString(currentJournal.spread_tol, 5) + ",";
    r += DoubleToString(currentJournal.price_signal, 5) + ",";
    r += DoubleToString(currentJournal.dist_entry, 5) + ",";
    r += currentJournal.side + ",";
    r += DoubleToString(currentJournal.k_band, 5) + ",";
    r += currentJournal.order_type + ",";
    r += DoubleToString(currentJournal.real_entry, 5) + ",";
    r += DoubleToString(currentJournal.slip_real, 5) + ",";
    r += DoubleToString(currentJournal.slip_tol, 5) + ",";
    r += DoubleToString(currentJournal.real_volume, 2) + ",";
    r += DoubleToString(currentJournal.stops_min, 5) + ",";
    r += DoubleToString(currentJournal.sl_dist, 5) + ",";
    r += IntegerToString(currentTP.currentLevel) + ",";
    r += DoubleToString(currentTP.closedPercent, 2) + ",";
    r += (currentTP.slMovedToBE ? "1" : "0") + ",";
    r += IntegerToString(exitLevel) + ",";
    r += closeReason + ",";
    r += DoubleToString(grossPnl, 2) + ",";
    r += DoubleToString(lastPrice, 5) + ",";
    r += result;
    return r;
}

// Sobrescribe el archivo live: header + 1 fila (si hay trade activo/pendiente).
void JournalWriteLive() {
    int h = FileOpen(JournalLivePath(), FILE_WRITE|FILE_TXT|FILE_ANSI);
    if(h == INVALID_HANDLE) return;
    FileWriteString(h, JournalHeader() + "\r\n");
    if(HasTrade()) {
        FileWriteString(h, JournalRow(currentTP.currentLevel, "OPEN", 0.0, 0.0, "OPEN") + "\r\n");
    }
    FileClose(h);
}

// Append de una fila final (cierre o rechazo) al journal.
void JournalAppendClosed(int exitLevel, string closeReason, double grossPnl, double lastPrice) {
    bool existed = FileIsExist(JournalFilePath());
    int h;
    if(existed) {
        h = FileOpen(JournalFilePath(), FILE_READ|FILE_WRITE|FILE_TXT|FILE_ANSI);
        if(h == INVALID_HANDLE) return;
        FileSeek(h, 0, SEEK_END);
    } else {
        h = FileOpen(JournalFilePath(), FILE_WRITE|FILE_TXT|FILE_ANSI);
        if(h == INVALID_HANDLE) return;
        FileWriteString(h, JournalHeader() + "\r\n");
    }
    FileWriteString(h, JournalRow(exitLevel, closeReason, grossPnl, lastPrice, closeReason) + "\r\n");
    FileClose(h);
}

// Adopta al estado del EA la posicion viva ya seleccionada (FindOwnPosition debe haber
// retornado true antes de llamar). Reusa entre el arranque y el monitoreo de pendientes.
void AdoptLivePosition(bool reportExecution) {
    currentTP.isActive      = true;
    currentTP.ticket        = position.Ticket();
    currentTP.positionID    = position.Identifier();
    currentTP.currentVolume = position.Volume();
    currentTP.currentSL     = position.StopLoss();
    currentTP.entry         = position.PriceOpen();   // precio REAL de ejecucion
    if(currentTP.currentLevel < 0) currentTP.currentLevel = 0;

    if(reportExecution) {
        ReportPendingExecuted(currentTP.signalId, currentTP.ticket, currentTP.entry,
                              currentTP.currentSL, currentTP.currentVolume);
    }
    SaveState();
}

// La posicion desaparecio: averigua el motivo en el historial, reporta el cierre y
// finaliza el ciclo. fallbackPrice/Pnl se usan solo si el deal no trae datos.
void FinalizeClosedFromHistory(double fallbackPrice, double fallbackPnl) {
    HistoryCloseResult closeResult = GetCloseReasonFromHistory(currentTP.positionID);
    double reportPrice = closeResult.hasDealData ? closeResult.dealPrice  : fallbackPrice;
    double finalPnl    = closeResult.hasDealData ? closeResult.dealProfit : fallbackPnl;

    Log(INFO_LVL, "POSITION", StringFormat("Posición cerrada: %s (Exit Level: %d, Price: %.5f, PNL: %.2f, FromDeal: %s)",
        closeResult.reason, closeResult.exitLevel, reportPrice, finalPnl, closeResult.hasDealData ? "YES" : "NO"));

    ReportClose(currentTP.signalId, closeResult.exitLevel, closeResult.reason, reportPrice, finalPnl);
    EndTrade();
}

// Reconcilia el estado persistido contra la realidad de MT5 al arrancar.
void AdoptStateOnInit() {
    if(!LoadState()) {
        ClearState();   // archivo vacio/corrupto: limpiar
        return;
    }

    // Caso 1: hay una posicion viva nuestra (magic+symbol) -> re-adoptar
    if(FindOwnPosition()) {
        bool wasPending = !currentTP.isActive;   // estado guardado pendiente + ahora hay posicion => se ejecuto mientras estabamos caidos
        AdoptLivePosition(wasPending);           // re-adopta campos + (si pendiente) avisa a la API + persiste

        Log(INFO_LVL, "ADOPT", StringFormat("Posición re-adoptada: SignalID=%d, Ticket=%d, PosID=%d, Vol=%.2f, %s",
            currentTP.signalId, currentTP.ticket, currentTP.positionID, currentTP.currentVolume,
            (wasPending ? "(pendiente ejecutada mientras EA caido)" : "")));
        return;
    }

    // Caso 2: la orden pendiente sigue viva (todavia no se ejecuto) -> seguir esperando
    if(!currentTP.isActive && currentTP.ticket > 0 && OrderSelect(currentTP.ticket)) {
        Log(INFO_LVL, "ADOPT", StringFormat("Orden pendiente aún viva re-adoptada: SignalID=%d, Ticket=%d",
            currentTP.signalId, currentTP.ticket));
        SaveState();
        return;
    }

    // Caso 3: no hay posicion ni pendiente viva -> cerro/cancelo mientras estabamos caidos.
    // Si el estado guardado era PENDIENTE, distinguir cancelacion/expiracion (la orden nunca fillró)
    // de un fill+cierre ocurridos mientras el EA estaba caido. Solo desviar a ORDER_CANCELLED con
    // evidencia POSITIVA en el historial de la orden; ante la duda (orden no sincronizada / fillró),
    // caer al reconciliado de cierre, que registra el trade. Usa EXIT_ERROR + REASON_ORDER_CANCELLED,
    // consistente con el path de cancelacion con EA vivo (CheckPendingOrderExecution).
    if(!currentTP.isActive && currentTP.ticket > 0 && HistoryOrderSelect(currentTP.ticket)) {
        ENUM_ORDER_STATE st = (ENUM_ORDER_STATE)HistoryOrderGetInteger(currentTP.ticket, ORDER_STATE);
        if(st == ORDER_STATE_CANCELED || st == ORDER_STATE_EXPIRED || st == ORDER_STATE_REJECTED) {
            Log(WARNING_LVL, "ADOPT", StringFormat("Pendiente SignalID=%d %s mientras EA caido -> ORDER_CANCELLED",
                currentTP.signalId, EnumToString(st)));
            ReportClose(currentTP.signalId, EXIT_ERROR, REASON_ORDER_CANCELLED, 0, 0);
            EndTrade();
            return;
        }
    }

    Log(WARNING_LVL, "ADOPT", StringFormat("Trade SignalID=%d ya no existe en MT5: reconciliando cierre desde historial",
        currentTP.signalId));
    FinalizeClosedFromHistory(0.0, 0.0);
}

// ==========================================
// INICIALIZACIÓN
// ==========================================
int OnInit() {
    currentSymbol = Symbol();

    if(!ValidateAllInputs()) {
        Log(ERROR_LVL, "INIT", "Parámetros inválidos");
        return INIT_PARAMETERS_INCORRECT;
    }

    if(!ValidateSymbol()) return INIT_FAILED;

    InitTPState();
    SetupBaseUrls();

    trade.SetExpertMagicNumber(MAGIC_NUMBER);
    // deviation se setea por-trade en ExecuteTrade (m*T1), no fijo

    AdoptStateOnInit();   // reconciliar estado persistido contra MT5 (sobrevive reinicios)

    LogInitialization();
    EventSetTimer(POLL_INTERVAL);

    // Solo pollear señales nuevas si NO quedamos con un trade adoptado
    if(!HasTrade()) {
        CheckForSignals();
    }

    return INIT_SUCCEEDED;
}

void InitTPState() {
    currentTP.isActive = false;
    currentTP.signalId = 0;
    currentTP.ticket = 0;
    currentTP.positionID = 0;
    currentTP.direction = "";
    currentTP.originalVolume = 0;
    currentTP.currentVolume = 0;
    currentTP.totalClosedVolume = 0;
    currentTP.closedPercent = 0;
    currentTP.currentLevel = -2;
    currentTP.slMovedToBE = false;

    ArrayInitialize(currentTP.levelFlags, false);
    ArrayInitialize(currentTP.levelVolumes, 0.0);   // higiene: no arrastrar el reparto del trade anterior

    currentTP.entry = 0;
    currentTP.originalSL = 0;
    currentTP.currentSL = 0;
    currentTP.tp1 = currentTP.tp2 = currentTP.tp3 = currentTP.tp4 = currentTP.tp5 = 0;
    currentTP.tradeStartUtc = 0;
    currentTP.cutoffTargetUtc = 0;
    currentTP.closeTargetUtc = 0;
}

void SetupBaseUrls() {
    signalsBaseUrl = API_URL + "signals/";
    futPriceBaseUrl = API_URL + "fut_price/";
}

bool ValidateAllInputs() {
    if(USER_ID <= 0 || RISK_PERCENT <= 0 || RISK_PERCENT > 10 || POLL_INTERVAL < 5) return false;
    if(BE_LEVEL < 0 || BE_LEVEL > 5) return false;
    if(SAFETY_FACTOR < 1.0 || SAFETY_FACTOR > 5.0) return false;

    // Gates asset-agnostic: ratios positivos y el slippage permitido (m) DEBE ser menor que
    // la banda LIMIT (k_limit); si no, el deviation de market supera la banda adversa.
    if(K_STOP_RATIO <= 0 || K_LIMIT_RATIO <= 0 || M_SLIP_RATIO <= 0 || C_SPREAD_RATIO <= 0) {
        Log(ERROR_LVL, "INIT", "Ratios de gates inválidos: deben ser > 0");
        return false;
    }
    // El invariante M_SLIP < K_LIMIT solo importa si el cap de slippage esta activo (si no, el
    // deviation es amplio y M_SLIP queda sin uso). Asi un M_SLIP grande con el check OFF no bloquea.
    if(ENABLE_SLIP_CHECK && M_SLIP_RATIO >= K_LIMIT_RATIO) {
        Log(ERROR_LVL, "INIT", StringFormat("Invariante de gates violado: M_SLIP_RATIO(%.2f) debe ser < K_LIMIT_RATIO(%.2f)",
            M_SLIP_RATIO, K_LIMIT_RATIO));
        return false;
    }

    TPConfig tpConfig = ValidateTPConfig();
    return tpConfig.isValid;
}

TPConfig ValidateTPConfig() {
    TPConfig config;
    config.percents[0] = TP1_PERCENT;
    config.percents[1] = TP2_PERCENT;
    config.percents[2] = TP3_PERCENT;
    config.percents[3] = TP4_PERCENT;
    config.percents[4] = TP5_PERCENT;

    config.totalPercent = 0;
    for(int i = 0; i < 5; i++) {
        config.totalPercent += config.percents[i];
    }

    config.isValid = (config.totalPercent <= 100.0);

    // Config degenerada: valida igual (no es error), pero avisar para que no sorprenda el comportamiento.
    if(config.totalPercent <= 0.0)
        Log(WARNING_LVL, "INIT", "Config TP suma 0%: sin cierres parciales; el trade sale solo por TP5(closeAll) o SL");
    else if(config.totalPercent < 100.0)
        Log(WARNING_LVL, "INIT", StringFormat("Config TP suma %.0f%% (<100): el remanente lo barre TP5 closeAll", config.totalPercent));

    return config;
}

bool ValidateSymbol() {
    if(!SymbolSelect(currentSymbol, true)) {
        Log(ERROR_LVL, "VALIDATE", "No se puede seleccionar símbolo: " + currentSymbol);
        return false;
    }

    if(SymbolInfoInteger(currentSymbol, SYMBOL_TRADE_MODE) == SYMBOL_TRADE_MODE_DISABLED) {
        Log(ERROR_LVL, "VALIDATE", "Trading deshabilitado para: " + currentSymbol);
        return false;
    }

    return true;
}

void LogInitialization() {
   Print("EA Signals v", EA_VERSION, " | User: ", USER_ID, " | Symbol: ", currentSymbol, " | BE Level: ", BE_LEVEL);
   Log(INFO_LVL, "INIT", "Stop management: " + (ENABLE_CODE_STOP ? "CODE" : "MT5"));
}

// ==========================================
// ESPECIFICACIONES DE SÍMBOLO CON CACHE
// ==========================================
SymbolSpecs GetSymbolSpecs() {
    if(cachedSymbol == currentSymbol && cachedSpecs.isValid) {
        return cachedSpecs;
    }

    cachedSpecs.point = SymbolInfoDouble(currentSymbol, SYMBOL_POINT);
    cachedSpecs.tickSize = SymbolInfoDouble(currentSymbol, SYMBOL_TRADE_TICK_SIZE);
    cachedSpecs.tickValue = SymbolInfoDouble(currentSymbol, SYMBOL_TRADE_TICK_VALUE);
    cachedSpecs.contractSize = SymbolInfoDouble(currentSymbol, SYMBOL_TRADE_CONTRACT_SIZE);
    cachedSpecs.minVolume = SymbolInfoDouble(currentSymbol, SYMBOL_VOLUME_MIN);
    cachedSpecs.maxVolume = SymbolInfoDouble(currentSymbol, SYMBOL_VOLUME_MAX);
    cachedSpecs.stepVolume = SymbolInfoDouble(currentSymbol, SYMBOL_VOLUME_STEP);

    cachedSpecs.isValid = (cachedSpecs.point > 0 && cachedSpecs.contractSize > 0 &&
                          cachedSpecs.minVolume > 0 && cachedSpecs.stepVolume > 0);

    if(cachedSpecs.isValid) cachedSymbol = currentSymbol;

    return cachedSpecs;
}

// ==========================================
// FUNCIONES DE API
// ==========================================
string BuildAPIUrl(string endpoint, int id = 0, string param = "") {
    if(endpoint == "get_signals")
        return signalsBaseUrl + IntegerToString(USER_ID) + "/" + param;
    if(endpoint == "open")
        return signalsBaseUrl + IntegerToString(id) + "/open";
    if(endpoint == "progress")
        return signalsBaseUrl + IntegerToString(id) + "/progress";
    if(endpoint == "close")
        return signalsBaseUrl + IntegerToString(id) + "/close";
    if(endpoint == "fut_price")
        return futPriceBaseUrl + param;
    return "";
}

APIResponse SendAPIRequest(string method, string url, string jsonData = "", bool silent = false) {
    APIResponse response;
    response.result = API_NETWORK_ERROR;
    response.httpCode = 0;
    response.data = "";

    char data[];
    char result[];
    string req_headers = "Content-Type: application/json\r\n";
    string res_headers = "";

    if(method == "POST" && jsonData != "") {
        StringToCharArray(jsonData, data, 0, StringLen(jsonData));
    }

    if(!silent) Log(INFO_LVL, "API", StringFormat("%s %s", method, url));
    int httpCode = WebRequest(method, url, req_headers, HTTP_TIMEOUT, data, result, res_headers);

    if(httpCode == -1) {
        response.message = "WebRequest error: " + IntegerToString(GetLastError());
        Log(ERROR_LVL, "API", response.message);
        return response;
    }

    response.httpCode = httpCode;
    response.data = CharArrayToString(result);
    Log(DEBUG_LVL, "API", StringFormat("HTTP %d | Body: %s", httpCode, TruncLog(response.data)));

    if(httpCode == 200) {
        response.result = API_SUCCESS;
        response.message = "OK";
    } else {
        response.result = API_HTTP_ERROR;
        response.message = "HTTP " + IntegerToString(httpCode);
        Log(ERROR_LVL, "API", StringFormat("HTTP %d error — Response: %s", httpCode, TruncLog(response.data, 1000)));
    }

    return response;
}

// ==========================================
// FUNCIONES DE REPORTE (usando JsonBuilder)
// ==========================================

// Arma el objeto JSON {op_type, entry, stoploss[], tps[]} usado en signal_data y mt_corrected_data.
string BuildSignalDataJson(string opType, PriceSet &p) {
    string j = "{";
    j += "\"op_type\":\"" + opType + "\",";
    j += "\"entry\":" + DoubleToString(p.entry, 5) + ",";
    j += "\"stoploss\":[" + DoubleToString(p.sl1, 5) + "," + DoubleToString(p.sl2, 5) + "],";
    j += "\"tps\":[" + DoubleToString(p.tp1, 5) + "," + DoubleToString(p.tp2, 5) + ","
       + DoubleToString(p.tp3, 5) + "," + DoubleToString(p.tp4, 5) + ","
       + DoubleToString(p.tp5, 5) + "]";
    j += "}";
    return j;
}

// Envia un POST de reporte y loguea OK/FALLO de forma uniforme. Retorna true si HTTP 200.
bool PostReport(string url, string json, string label) {
    APIResponse response = SendAPIRequest("POST", url, json);
    if(response.result == API_SUCCESS) {
        Log(INFO_LVL, "REPORT", label + " reportado OK");
        return true;
    }
    Log(ERROR_LVL, "REPORT", StringFormat("%s FALLÓ: %s (HTTP %d) — Response: %s",
        label, response.message, response.httpCode, TruncLog(response.data)));
    return false;
}

// ---- Objetos de analytics para la BD (mirror del CSV journal) ----

// snapshot: feature vector estatico del trade (keys == columnas de ea_trade_snapshots)
string BuildSnapshotJson() {
    JsonBuilder jb;
    jb.AddString("dir", currentJournal.dir);
    jb.AddInt("corr_on", currentJournal.corr_on ? 1 : 0);
    jb.AddDouble("corr_factor", currentJournal.corr_factor, 6);
    jb.AddDouble("entry_raw", currentJournal.entry_raw);
    jb.AddDouble("sl_raw", currentJournal.sl_raw);
    jb.AddDouble("entry", currentJournal.entry);
    jb.AddDouble("sl", currentJournal.sl);
    jb.AddDouble("tp1", currentJournal.tp1);
    jb.AddDouble("tp2", currentJournal.tp2);
    jb.AddDouble("tp3", currentJournal.tp3);
    jb.AddDouble("tp4", currentJournal.tp4);
    jb.AddDouble("tp5", currentJournal.tp5);
    jb.AddDouble("r_dist", currentJournal.rdist);
    jb.AddDouble("t1", currentJournal.t1);
    jb.AddDouble("spread_real", currentJournal.spread_real);
    jb.AddDouble("spread_tol", currentJournal.spread_tol);
    jb.AddDouble("slip_real", currentJournal.slip_real);
    jb.AddDouble("slip_tol", currentJournal.slip_tol);
    jb.AddDouble("price_signal", currentJournal.price_signal);
    jb.AddDouble("dist_entry", currentJournal.dist_entry);
    jb.AddString("side", currentJournal.side);
    jb.AddDouble("k_band", currentJournal.k_band);
    jb.AddString("order_type", currentJournal.order_type);
    jb.AddDouble("real_entry", currentJournal.real_entry);
    jb.AddDouble("real_volume", currentJournal.real_volume, 2);
    jb.AddDouble("stops_min", currentJournal.stops_min);
    jb.AddDouble("sl_dist", currentJournal.sl_dist);
    jb.AddDouble("acct_balance", currentJournal.acct_balance, 2);
    jb.AddDouble("sl_risk_per_lot", currentJournal.sl_risk_per_lot, 4);
    return jb.Build();
}

// ea_config: las constantes (inputs) vigentes en ESTE trade
string BuildEaConfigJson() {
    JsonBuilder jb;
    jb.AddDouble("cfg_risk_percent", RISK_PERCENT, 2);
    jb.AddDouble("cfg_k_stop_ratio", K_STOP_RATIO, 4);
    jb.AddDouble("cfg_k_limit_ratio", K_LIMIT_RATIO, 4);
    jb.AddDouble("cfg_m_slip_ratio", M_SLIP_RATIO, 4);
    jb.AddDouble("cfg_c_spread_ratio", C_SPREAD_RATIO, 4);
    jb.AddInt("cfg_enable_slip", ENABLE_SLIP_CHECK ? 1 : 0);
    jb.AddInt("cfg_enable_spread", ENABLE_SPREAD_CHECK ? 1 : 0);
    jb.AddInt("cfg_enable_corr", ENABLE_PRICE_CORRECTION ? 1 : 0);
    jb.AddInt("cfg_be_level", BE_LEVEL);
    jb.AddDouble("cfg_tp1_pct", TP1_PERCENT, 2);
    jb.AddDouble("cfg_tp2_pct", TP2_PERCENT, 2);
    jb.AddDouble("cfg_tp3_pct", TP3_PERCENT, 2);
    jb.AddDouble("cfg_tp4_pct", TP4_PERCENT, 2);
    jb.AddDouble("cfg_tp5_pct", TP5_PERCENT, 2);
    return jb.Build();
}

// correction: telemetria del proceso de correccion (futuro vs vela CFD + verificacion)
string BuildCorrectionJson() {
    JsonBuilder jb;
    jb.AddInt("enabled", ENABLE_PRICE_CORRECTION ? 1 : 0);
    jb.AddString("status", currentJournal.corr_status == "" ? "OK" : currentJournal.corr_status);
    if(currentJournal.corr_error_stage != "")
        jb.AddString("error_stage", currentJournal.corr_error_stage);
    jb.AddDouble("fut_price", currentJournal.fut_price);
    jb.AddString("fut_candle_time", currentJournal.fut_candle_time);
    jb.AddDouble("mt5_price", currentJournal.mt5_price);
    jb.AddString("mt5_bar_time", currentJournal.mt5_bar_time);
    jb.AddInt("mt5_bar_index", currentJournal.mt5_bar_index);
    jb.AddDouble("signal_mt5_price", currentJournal.price_signal);
    jb.AddInt("broker_offset_sec", currentJournal.broker_offset_sec);
    jb.AddString("target_broker_time", currentJournal.target_broker_time);
    jb.AddInt("bar_gap_sec", currentJournal.bar_gap_sec);
    jb.AddInt("candles_aligned", currentJournal.candles_aligned ? 1 : 0);
    jb.AddDouble("corr_factor", currentJournal.corr_factor, 6);
    jb.AddDouble("deviation_pct", currentJournal.deviation_pct, 4);
    jb.AddInt("timestamp_age_sec", currentJournal.timestamp_age_sec);
    return jb.Build();
}

// Footer comun de los reportes: symbol (opcional) + execution_time, en ese orden. Centraliza el
// formato del timestamp — el body se hashea server-side para idempotencia, asi que un solo formato
// evita drift entre los 4 Report*.
void AddReportFooter(JsonBuilder &jb, bool withSymbol) {
    if(withSymbol) jb.AddString("symbol", TICKER_SYMBOL);
    jb.AddString("execution_time", TimeToString(TimeGMT(), TIME_DATE|TIME_SECONDS));
}

// Bloque de analytics para la BD: version + snapshot estatico + config + (si hubo) correccion.
// Lo comparten ReportOpen (siempre) y ReportClose (solo en fallos pre-ejecucion).
void AddAnalyticsBlock(JsonBuilder &jb) {
    jb.AddString("ea_version", EA_VERSION);
    jb.AddRaw("snapshot", BuildSnapshotJson());
    jb.AddRaw("ea_config", BuildEaConfigJson());
    if(currentJournal.corr_status != "")
        jb.AddRaw("correction", BuildCorrectionJson());
}

void ReportOpen(int userSignalId, bool isMarketOrder, ENUM_ORDER_TYPE orderType,
                double entryPrice, double stopLoss, double volume, ulong ticket,
                string opType, PriceSet &raw, PriceSet &corrected) {

    string url = BuildAPIUrl("open", userSignalId);

    JsonBuilder jb;
    jb.AddBool("success", true);
    jb.AddString("trade_id", IntegerToString(ticket));
    jb.AddString("order_type", EnumToString(orderType));

    if(isMarketOrder) {
        jb.AddDouble("real_entry_price", entryPrice);
        jb.AddDouble("real_stop_loss", stopLoss);
    }
    jb.AddDouble("real_volume", volume, 2);

    // signal_data: precios originales (pre-corrección). mt_corrected_data: lo que realmente usa MT5.
    if(raw.entry > 0 && opType != "")
        jb.AddRaw("signal_data", BuildSignalDataJson(opType, raw));
    if(corrected.entry > 0 && opType != "")
        jb.AddRaw("mt_corrected_data", BuildSignalDataJson(opType, corrected));

    // Analytics BD: snapshot estatico + constantes de config + proceso de correccion
    AddAnalyticsBlock(jb);

    AddReportFooter(jb, true);

    PostReport(url, jb.Build(), "Open");
}

void ReportProgress(int userSignalId, int level, double volumeClosedPercent,
                    double remainingVolume, double currentPrice, double pnl,
                    string message, double newStopLoss = 0.0) {

    string url = BuildAPIUrl("progress", userSignalId);

    JsonBuilder jb;
    // Solo campos dinámicos que cambian durante el trade - no sobreescribir execution_data
    jb.AddInt("current_level", level);
    jb.AddDouble("volume_closed_percent", volumeClosedPercent, 2);
    jb.AddDouble("remaining_volume", remainingVolume, 2);
    jb.AddDouble("gross_pnl", pnl, 2);
    jb.AddDouble("last_price", currentPrice);
    jb.AddString("message", message);
    AddReportFooter(jb, false);

    // Solo cuando se mueve SL a BE
    if(newStopLoss > 0.0) {
        jb.AddDouble("new_stop_loss", newStopLoss);
    }

    PostReport(url, jb.Build(), "Progress: " + message);
}

void ReportClose(int userSignalId, int exitLevel, string closeReason,
                 double finalPrice, double finalPnl) {

    string url = BuildAPIUrl("close", userSignalId);

    JsonBuilder jb;
    jb.AddBool("success", true);
    jb.AddInt("exit_level", exitLevel);
    jb.AddString("close_reason", closeReason);
    jb.AddDouble("gross_pnl", finalPnl, 2);
    jb.AddDouble("last_price", finalPrice);

    // Fallos pre-ejecucion (nunca operó, ReportOpen no corrió): adjuntamos el feature vector
    // + config + correccion para no perder la fila de analisis (mirror del CSV de rechazos).
    if(exitLevel == EXIT_INVALID || exitLevel == EXIT_ERROR) {
        AddAnalyticsBlock(jb);
    }

    AddReportFooter(jb, true);

    PostReport(url, jb.Build(), "Close: " + closeReason);

    JournalAppendClosed(exitLevel, closeReason, finalPnl, finalPrice);
}

// ==========================================
// CORRECCIÓN DE PRECIOS (resultado y datos clave en INFO, internals en DEBUG)
// ==========================================
// Formatea un datetime al formato DATETIME de MySQL ("YYYY-MM-DD HH:MM:SS").
string DbDateTime(datetime t) {
    if(t <= 0) return "";
    MqlDateTime d;
    TimeToStruct(t, d);
    return StringFormat("%04d-%02d-%02d %02d:%02d:%02d", d.year, d.mon, d.day, d.hour, d.min, d.sec);
}

double CalculatePriceCorrection(string symbol, string &errorMessage) {
    errorMessage = "";

    if(!ENABLE_PRICE_CORRECTION) {
        Log(DEBUG_LVL, "PRICE_CORR", "Price correction deshabilitado");
        return 1.0;
    }

    string url = BuildAPIUrl("fut_price", 0, symbol);
    Log(INFO_LVL, "PRICE_CORR", "Consultando precio futuro: " + url);

    APIResponse response = SendAPIRequest("GET", url);

    if(response.result != API_SUCCESS) {
        errorMessage = "Error obteniendo precio del futuro - HTTP: " + IntegerToString(response.httpCode);
        Log(ERROR_LVL, "PRICE_CORR", errorMessage);
        currentJournal.corr_status = "ERROR"; currentJournal.corr_error_stage = "FETCH";
        return 0.0;
    }

    SimpleJSONParser parser(response.data);
    double futurePrice = parser.GetDouble("last_close");
    long epochTime = parser.GetLong("ts_epoch");

    currentJournal.fut_price = futurePrice;
    if(epochTime > 0) currentJournal.fut_candle_time = DbDateTime((datetime)epochTime);

    Log(INFO_LVL, "PRICE_CORR", StringFormat("Future Price=%.5f, Epoch=%d", futurePrice, epochTime));

    if(futurePrice <= 0 || epochTime <= 0) {
        errorMessage = StringFormat("Datos inválidos del futuro - Price: %.5f, Epoch: %d", futurePrice, epochTime);
        Log(ERROR_LVL, "PRICE_CORR", errorMessage);
        currentJournal.corr_status = "ERROR"; currentJournal.corr_error_stage = "INVALID_DATA";
        return 0.0;
    }

    datetime targetTime = (datetime)epochTime;
    datetime currentTime = TimeCurrent();
    long maxAgeSeconds = MAX_TIMESTAMP_HOURS * 3600;

    datetime gmtTime = TimeGMT();
    long brokerOffset = currentTime - gmtTime;

    datetime brokerTargetTime = (datetime)(targetTime + brokerOffset);
    long timeDifference = currentTime - brokerTargetTime;

    currentJournal.broker_offset_sec   = (int)brokerOffset;
    currentJournal.target_broker_time  = DbDateTime(brokerTargetTime);
    currentJournal.timestamp_age_sec   = (int)timeDifference;

    Log(DEBUG_LVL, "PRICE_CORR", StringFormat("Target UTC=%s, Broker=%s, Diff=%ds, Max=%ds",
        TimeToString(targetTime), TimeToString(brokerTargetTime), timeDifference, maxAgeSeconds));

    if(timeDifference > maxAgeSeconds) {
        errorMessage = StringFormat("Timestamp muy viejo: %ds > %ds", timeDifference, maxAgeSeconds);
        Log(ERROR_LVL, "PRICE_CORR", errorMessage);
        currentJournal.corr_status = "ERROR"; currentJournal.corr_error_stage = "STALE_TIMESTAMP";
        return 0.0;
    }

    int barIndex = iBarShift(Symbol(), PERIOD_M1, brokerTargetTime);
    currentJournal.mt5_bar_index = barIndex;

    if(barIndex < 0) {
        errorMessage = "No se encontró vela CFD para tiempo: " + TimeToString(targetTime);
        Log(ERROR_LVL, "PRICE_CORR", errorMessage);
        currentJournal.corr_status = "ERROR"; currentJournal.corr_error_stage = "NO_BAR";
        return 0.0;
    }

    // Vela CFD realmente matcheada: verificacion de alineacion vs el tiempo objetivo
    datetime matchedBarTime = iTime(Symbol(), PERIOD_M1, barIndex);
    currentJournal.mt5_bar_time    = DbDateTime(matchedBarTime);
    currentJournal.bar_gap_sec     = (int)(matchedBarTime - brokerTargetTime);
    currentJournal.candles_aligned = (MathAbs(currentJournal.bar_gap_sec) <= 60);

    double cfdPrice = iClose(Symbol(), PERIOD_M1, barIndex);
    currentJournal.mt5_price = cfdPrice;

    if(cfdPrice <= 0) {
        errorMessage = "Precio CFD inválido: " + DoubleToString(cfdPrice, 5);
        Log(ERROR_LVL, "PRICE_CORR", errorMessage);
        currentJournal.corr_status = "ERROR"; currentJournal.corr_error_stage = "INVALID_CFD";
        return 0.0;
    }

    double factor = futurePrice / cfdPrice;
    double deviationPercent = MathAbs((factor - 1.0) * 100.0);
    currentJournal.corr_factor   = factor;
    currentJournal.deviation_pct = deviationPercent;

    Log(INFO_LVL, "PRICE_CORR", StringFormat("Future=%.5f, CFD=%.5f, Factor=%.6f, Desviación=%.2f%%",
        futurePrice, cfdPrice, factor, deviationPercent));

    if(deviationPercent > MAX_PRICE_DEVIATION) {
        errorMessage = StringFormat("Desviación muy alta: %.2f%% > %.2f%%", deviationPercent, MAX_PRICE_DEVIATION);
        Log(ERROR_LVL, "PRICE_CORR", errorMessage);
        currentJournal.corr_status = "ERROR"; currentJournal.corr_error_stage = "DEVIATION_TOO_HIGH";
        return 0.0;
    }

    currentJournal.corr_status = "OK";
    return factor;
}

// ==========================================
// FUNCIONES DE PNL Y VOLUMEN
// ==========================================
// Valor monetario de 1 punto por 1 lote. Centraliza el factor tickValue*(point/tickSize)
// usado tanto en el PNL como en el sizing por riesgo.
double PointValuePerLot(SymbolSpecs &specs) {
    return specs.tickValue * (specs.point / specs.tickSize);
}

double CalculatePNL(double currentPrice, double volume = 0) {
    if(!currentTP.isActive) return 0.0;

    if(volume == 0) volume = currentTP.currentVolume;

    double priceDiff = (currentTP.direction == "LONG") ?
                      (currentPrice - currentTP.entry) :
                      (currentTP.entry - currentPrice);

    SymbolSpecs specs = GetSymbolSpecs();
    return (priceDiff / specs.point) * volume * PointValuePerLot(specs);
}

// Alinea un precio a la grilla de tick del símbolo y lo limpia a SYMBOL_DIGITS. La corrección de
// precios (división por un factor irracional) deja decimales arbitrarios; un precio no alineado al
// tickSize puede ser rechazado por el broker (Invalid price/stops), sobre todo en pendientes y en
// instrumentos donde tickSize != point (índices, oil, etc.).
double NormalizePrice(double price) {
    SymbolSpecs specs = GetSymbolSpecs();
    int digits = (int)SymbolInfoInteger(currentSymbol, SYMBOL_DIGITS);
    if(specs.isValid && specs.tickSize > 0)
        price = MathRound(price / specs.tickSize) * specs.tickSize;
    return NormalizeDouble(price, digits);
}

// Setea el deviation (slippage máximo) de un market. Si ENABLE_SLIP_CHECK: cap = M_SLIP_RATIO*T1;
// si está OFF (default, alta liquidez): deviation amplia (sin cap efectivo). El tol se registra en el
// journal SIEMPRE (informativo), igual que el gate de spread. Reusada por el market normal y por el
// fallback de E (pendiente rechazada -> market).
void ApplyMarketDeviation(double t1, double point) {
    double slipTol = M_SLIP_RATIO * t1;
    currentJournal.slip_tol = slipTol;   // informativo (journal) siempre
    int devPoints;
    if(ENABLE_SLIP_CHECK) {
        devPoints = (point > 0) ? (int)MathRound(slipTol / point) : 1;
        if(devPoints < 1) devPoints = 1;
        Log(INFO_LVL, "SLIPPAGE", StringFormat("check ON | tol=%.5f (m=%.2f*T1) = %d points", slipTol, M_SLIP_RATIO, devPoints));
    } else {
        devPoints = SLIP_NO_CAP_POINTS;
        Log(INFO_LVL, "SLIPPAGE", StringFormat("check OFF | tol=%.5f informativo | deviation=%d (sin cap)", slipTol, devPoints));
    }
    trade.SetDeviationInPoints(devPoints);
}

// Reparte 'volume' entre los 5 niveles de TP segun sus porcentajes, en multiplos ENTEROS de
// stepVolume que suman exactamente el volumen original (metodo de mayor resto / Hare). Asi cada
// TP cierra un tramo >= 1 step (nunca sub-minimo) y no nace ningun remanente huerfano: se elimina
// de raiz el "puchito". outVolumes[1..5] = lotes por TP; outVolumes[0] sin usar.
// Nota: si la config suma < 100%, los steps no repartidos los cierra TP5 (closeAll) como remanente.
void ComputeLevelVolumes(double volume, SymbolSpecs &specs, double &outVolumes[]) {
    ArrayInitialize(outVolumes, 0.0);

    if(!specs.isValid || specs.stepVolume <= 0) {
        outVolumes[1] = volume;   // fallback degenerado: sin specs validas, todo al primer nivel
        return;
    }

    int totalSteps = (int)MathRound(volume / specs.stepVolume);
    if(totalSteps <= 0) return;

    double percents[6];
    percents[0] = 0;
    percents[1] = TP1_PERCENT; percents[2] = TP2_PERCENT; percents[3] = TP3_PERCENT;
    percents[4] = TP4_PERCENT; percents[5] = TP5_PERCENT;

    int    baseSteps[6];
    double frac[6];
    int    assigned = 0;
    for(int i = 1; i <= 5; i++) {
        double quota = (percents[i] / 100.0) * totalSteps;
        baseSteps[i] = (int)MathFloor(quota);
        frac[i]      = quota - baseSteps[i];
        assigned    += baseSteps[i];
    }

    // Los steps sobrantes van al nivel con mayor parte fraccionaria (solo niveles con peso > 0).
    int remainder = totalSteps - assigned;
    while(remainder > 0) {
        int    best = -1;
        double bestFrac = -1.0;
        for(int i = 1; i <= 5; i++) {
            if(percents[i] <= 0) continue;
            if(frac[i] > bestFrac) { bestFrac = frac[i]; best = i; }
        }
        if(best == -1) break;   // config suma < 100%: el resto lo barre TP5 closeAll
        baseSteps[best] += 1;
        frac[best] = -1.0;      // ya no elegible
        remainder--;
    }

    for(int i = 1; i <= 5; i++)
        outVolumes[i] = baseSteps[i] * specs.stepVolume;
}

double CalculateVolumeOptimized(double entryPrice, double stopLoss) {
    SymbolSpecs specs = GetSymbolSpecs();
    if(!specs.isValid) return 0.0;

    double balance = AccountInfoDouble(ACCOUNT_BALANCE);
    double riskAmount = balance * (RISK_PERCENT / 100.0);
    double distancePoints = MathAbs(entryPrice - stopLoss) / specs.point;
    double pointValuePerLot = PointValuePerLot(specs);

    if(pointValuePerLot <= 0 || distancePoints <= 0) return 0.0;

    double volume = riskAmount / (distancePoints * pointValuePerLot);
    // Telemetria para la formula de volumen en el detalle (balance + riesgo $/lote del SL)
    currentJournal.acct_balance    = balance;
    currentJournal.sl_risk_per_lot = distancePoints * pointValuePerLot;
    volume = MathMax(specs.minVolume, MathMin(specs.maxVolume, volume));
    volume = MathFloor(volume / specs.stepVolume) * specs.stepVolume;

    if(volume < specs.minVolume) volume = specs.minVolume;

    return volume;
}

// ==========================================
// DETECCIÓN DE CIERRE DESDE HISTORIAL (MEJORADA)
// ==========================================
HistoryCloseResult GetCloseReasonFromHistory(ulong positionID) {
    HistoryCloseResult result;
    result.exitLevel = currentTP.currentLevel;
    result.reason = REASON_EXTERNAL;
    result.dealPrice = 0;
    result.dealProfit = 0;
    result.hasDealData = false;

    if(positionID == 0) {
        Log(WARNING_LVL, "HISTORY", "Position ID es 0 - no se puede consultar historial");
        return result;
    }

    if(!HistorySelectByPosition(positionID)) {
        Log(WARNING_LVL, "HISTORY", "No se pudo cargar historial para Position ID: " + IntegerToString(positionID));
        return result;
    }

    int totalDeals = HistoryDealsTotal();
    if(totalDeals == 0) {
        Log(WARNING_LVL, "HISTORY", "No se encontraron deals para Position ID: " + IntegerToString(positionID));
        return result;
    }

    // Recorrer deals desde el mas reciente buscando el deal de cierre
    int dealsToCheck = MathMin(5, totalDeals);

    for(int i = totalDeals - 1; i >= totalDeals - dealsToCheck; i--) {
        ulong dealTicket = HistoryDealGetTicket(i);
        if(dealTicket == 0) continue;

        ENUM_DEAL_ENTRY dealEntry = (ENUM_DEAL_ENTRY)HistoryDealGetInteger(dealTicket, DEAL_ENTRY);

        // Aceptar DEAL_ENTRY_OUT y DEAL_ENTRY_INOUT (close-by / netting)
        if(dealEntry != DEAL_ENTRY_OUT && dealEntry != DEAL_ENTRY_INOUT) continue;

        ENUM_DEAL_TYPE dealType = (ENUM_DEAL_TYPE)HistoryDealGetInteger(dealTicket, DEAL_TYPE);
        bool isCorrectType = false;

        if(currentTP.direction == "LONG" && dealType == DEAL_TYPE_SELL) isCorrectType = true;
        if(currentTP.direction == "SHORT" && dealType == DEAL_TYPE_BUY) isCorrectType = true;

        if(!isCorrectType) continue;

        ENUM_DEAL_REASON dealReason = (ENUM_DEAL_REASON)HistoryDealGetInteger(dealTicket, DEAL_REASON);
        result.dealPrice = HistoryDealGetDouble(dealTicket, DEAL_PRICE);
        result.dealProfit = HistoryDealGetDouble(dealTicket, DEAL_PROFIT);
        result.hasDealData = true;

        Log(INFO_LVL, "HISTORY", StringFormat("Deal: Ticket=%d, Reason=%s(%d), Price=%.5f, Profit=%.2f",
            dealTicket, EnumToString(dealReason), dealReason, result.dealPrice, result.dealProfit));

        switch(dealReason) {
            case DEAL_REASON_SL:
                // Un SL ya movido a breakeven/profit (tras alcanzar un TP) NO es una pérdida: se
                // distingue del SL original para no contaminar las stats con un falso stop perdedor.
                if(currentTP.slMovedToBE) {
                    result.exitLevel = currentTP.currentLevel;   // TP máximo alcanzado antes de volver al BE
                    result.reason = REASON_BREAKEVEN;
                } else {
                    result.exitLevel = EXIT_STOP;
                    result.reason = REASON_STOPLOSS;
                }
                return result;

            case DEAL_REASON_TP:
                result.exitLevel = currentTP.currentLevel;
                result.reason = REASON_COMPLETE;
                return result;

            case DEAL_REASON_CLIENT:
            case DEAL_REASON_MOBILE:
            case DEAL_REASON_WEB:
                Log(INFO_LVL, "MANUAL_CLOSE", StringFormat("Cierre manual detectado! Reason=%s, Price=%.5f, Profit=%.2f",
                    EnumToString(dealReason), result.dealPrice, result.dealProfit));
                result.exitLevel = currentTP.currentLevel;
                result.reason = REASON_MANUAL;
                return result;

            case DEAL_REASON_EXPERT:
                result.exitLevel = currentTP.currentLevel;
                result.reason = REASON_EXTERNAL;
                return result;

            default:
                Log(INFO_LVL, "HISTORY", StringFormat("Cierre por razón: %s(%d)", EnumToString(dealReason), dealReason));
                result.exitLevel = currentTP.currentLevel;
                result.reason = REASON_EXTERNAL;
                return result;
        }
    }

    Log(WARNING_LVL, "HISTORY", "No se encontró deal de cierre válido en últimos " + IntegerToString(dealsToCheck) + " deals");
    return result;
}

// ==========================================
// FUNCIONES DE STOP LOSS POR CÓDIGO
// ==========================================
bool IsNewBarClosed() {
    static datetime lastBarTime = 0;
    datetime currentBarTime = iTime(currentSymbol, Period(), 0);

    if(currentBarTime != lastBarTime && lastBarTime != 0) {
        lastBarTime = currentBarTime;
        return true;
    }

    lastBarTime = currentBarTime;
    return false;
}

bool IsStopLossHit(double currentPrice) {
    if(!currentTP.isActive) return false;

    double activeSL = currentTP.currentSL;

    if(currentTP.direction == "LONG") {
        return (currentPrice <= activeSL);
    } else {
        return (currentPrice >= activeSL);
    }
}

bool CheckCodeStopLoss() {
    if(!ENABLE_CODE_STOP || !IsNewBarClosed()) return false;

    double lastClose = iClose(currentSymbol, Period(), 1);
    if(lastClose <= 0) return false;

    if(IsStopLossHit(lastClose)) {
        Log(INFO_LVL, "CODE_SL", StringFormat("Stop Loss por código! Precio barra anterior=%.5f, SL=%.5f",
            lastClose, currentTP.currentSL));
        return true;
    }

    return false;
}

bool CheckSafetyStop() {
    if(!currentTP.isActive) return false;

    double currentPrice = GetCurrentPrice(currentTP.direction);
    double currentPnl = CalculatePNL(currentPrice);
    double balance = AccountInfoDouble(ACCOUNT_BALANCE);
    double maxLoss = -(balance * (RISK_PERCENT / 100.0) * SAFETY_FACTOR);

    return (currentPnl <= maxLoss);
}

bool ClosePositionByCode(string reason) {
    if(!position.SelectByTicket(currentTP.ticket)) return false;

    double currentPrice = GetCurrentPrice(currentTP.direction);

    if(trade.PositionClose(position.Ticket())) {
        double finalPnl = CalculatePNL(currentPrice);
        // Code-stop y safety-stop son cierres tipo stop -> exit_level = EXIT_STOP (no el nivel de TP).
        int exitLevel = (reason == REASON_CODE_STOP || reason == REASON_SAFETY_STOP) ? EXIT_STOP : currentTP.currentLevel;

        ReportClose(currentTP.signalId, exitLevel, reason, currentPrice, finalPnl);
        Log(INFO_LVL, "CODE_CLOSE", "Posición cerrada por código: " + reason);
        EndTrade();
        return true;
    }
    return false;
}

// ==========================================
// GESTIÓN DE HORARIOS
// ==========================================
bool IsWithinTradingHours() {
    if(!ENABLE_TIME_FILTER) return true;

    MqlDateTime dt;
    TimeGMT(dt);

    if(dt.day_of_week == 0 || dt.day_of_week == 6) return false;

    int hour = dt.hour;
    return (START_HOUR <= END_HOUR) ?
           (hour >= START_HOUR && hour < END_HOUR) :
           (hour >= START_HOUR || hour < END_HOUR);
}

// ==========================================
// CORTES POR HORARIO (hora NY con DST automático)
// ==========================================
// Día del mes del N-ésimo domingo (n=1..). day_of_week: 0=domingo.
int NthSundayOfMonth(int year, int month, int n) {
    MqlDateTime f; f.year=year; f.mon=month; f.day=1; f.hour=0; f.min=0; f.sec=0;
    MqlDateTime d; TimeToStruct(StructToTime(f), d);
    int firstSunday = 1 + ((7 - d.day_of_week) % 7);
    return firstSunday + (n - 1) * 7;
}

// ¿Rige el horario de verano de EEUU para este instante UTC?
// DST: 2º domingo de marzo 07:00 UTC -> 1º domingo de noviembre 06:00 UTC.
bool IsUsDst(datetime utc) {
    MqlDateTime d; TimeToStruct(utc, d);
    MqlDateTime s; s.year=d.year; s.mon=3;  s.day=NthSundayOfMonth(d.year,3,2);  s.hour=7; s.min=0; s.sec=0;
    MqlDateTime e; e.year=d.year; e.mon=11; e.day=NthSundayOfMonth(d.year,11,1); e.hour=6; e.min=0; e.sec=0;
    return (utc >= StructToTime(s) && utc < StructToTime(e));
}

// Offset NY respecto de UTC, en segundos (EDT -4h verano / EST -5h invierno).
int NyOffsetSec(datetime utc) { return IsUsDst(utc) ? -4 * 3600 : -5 * 3600; }

// Próxima ocurrencia de HH:MM hora NY en/después de fromUtc, devuelta en UTC.
datetime NextNyTimeUtc(datetime fromUtc, int hh, int mm) {
    int off = NyOffsetSec(fromUtc);
    datetime fromNy = fromUtc + off;
    MqlDateTime ny; TimeToStruct(fromNy, ny);
    ny.hour = hh; ny.min = mm; ny.sec = 0;
    datetime targetNy = StructToTime(ny);
    if(targetNy <= fromNy) targetNy += 86400;        // ya pasó hoy -> mañana
    int offTarget = NyOffsetSec(targetNy - off);     // offset cerca del objetivo (cubre cruce de DST)
    return targetNy - offTarget;
}

// Precalcula (una sola vez) los instantes UTC de corte desde tradeStartUtc, para no recomputar la
// matemática NY/DST en cada OnTick. Deja 0 cuando la feature está off o no hay ancla de trade.
void SetupTimeCutoffs() {
    currentTP.cutoffTargetUtc = 0;
    currentTP.closeTargetUtc  = 0;
    if(currentTP.tradeStartUtc <= 0) return;
    if(ENABLE_PENDING_CUTOFF)
        currentTP.cutoffTargetUtc = NextNyTimeUtc(currentTP.tradeStartUtc, CUTOFF_HOUR_NY, CUTOFF_MIN_NY);
    if(ENABLE_FORCE_CLOSE)
        currentTP.closeTargetUtc  = NextNyTimeUtc(currentTP.tradeStartUtc, CLOSE_HOUR_NY, CLOSE_MIN_NY);
}

// ¿Llegó la hora de cortar la pendiente no ejecutada? (target precalculado al abrir)
bool ShouldCutoffPending() {
    return (currentTP.cutoffTargetUtc > 0 && TimeGMT() >= currentTP.cutoffTargetUtc);
}

// ¿Llegó la hora de cerrar la posición a mercado? (target precalculado al abrir)
bool ShouldForceCloseNow() {
    return (currentTP.closeTargetUtc > 0 && TimeGMT() >= currentTP.closeTargetUtc);
}

// Cancela la orden pendiente no ejecutada por corte de horario y reporta ORDER_CANCELLED.
void CancelPendingByTime() {
    Log(INFO_LVL, "CUTOFF", StringFormat("Corte de horario NY: cancelando pendiente no ejecutada (ticket=%d)", currentTP.ticket));
    if(OrderSelect(currentTP.ticket)) trade.OrderDelete(currentTP.ticket);
    ReportClose(currentTP.signalId, EXIT_ERROR, REASON_ORDER_CANCELLED, 0, 0);
    EndTrade();
}

// ==========================================
// EVENTOS PRINCIPALES
// ==========================================
void OnDeinit(const int reason) {
    EventKillTimer();
    Log(INFO_LVL, "SYSTEM", "EA desactivado");
}

void OnTimer() {
    // Cortes por horario NY: fiables cada POLL_INTERVAL aunque no lleguen ticks (futuros con baja
    // liquidez en algunos momentos). OnTick los maneja igual para respuesta inmediata.
    if(currentTP.ticket > 0) {
        if(!currentTP.isActive) {
            if(ShouldCutoffPending()) { CancelPendingByTime(); return; }
        } else if(ShouldForceCloseNow()) {
            Log(INFO_LVL, "FORCE_CLOSE", "Hora de cierre NY alcanzada (timer): cerrando posición a mercado");
            ClosePositionByCode(REASON_TIME_CLOSE);
            return;
        }
    }

    if(ENABLE_TIME_FILTER && !IsWithinTradingHours()) return;

    if(HasTrade()) return;

    CheckForSignals();
}

void OnTick() {
    if(!currentTP.isActive && currentTP.ticket > 0) {
        if(ShouldCutoffPending()) {   // corte por horario NY: pendiente no ejecutada
            CancelPendingByTime();
            return;
        }
        CheckPendingOrderExecution();
        return;
    }

    if(!currentTP.isActive) return;

    double currentPrice = GetCurrentPrice(currentTP.direction);

    // Verificar si la posición sigue existiendo
    if(!position.SelectByTicket(currentTP.ticket)) {
        // Posición desaparecio - reconciliar cierre desde historial
        FinalizeClosedFromHistory(currentPrice, CalculatePNL(currentPrice));
        return;
    }

    // Actualizar volumen actual
    currentTP.currentVolume = position.Volume();

    // Verificar stops por código si está habilitado
    if(ENABLE_CODE_STOP) {
        if(CheckCodeStopLoss()) {
            ClosePositionByCode(REASON_CODE_STOP);
            return;
        }

        if(CheckSafetyStop()) {
            ClosePositionByCode(REASON_SAFETY_STOP);
            return;
        }
    }

    // Cierre forzado por horario NY (sin importar precio)
    if(ShouldForceCloseNow()) {
        Log(INFO_LVL, "FORCE_CLOSE", "Hora de cierre NY alcanzada: cerrando posición a mercado");
        ClosePositionByCode(REASON_TIME_CLOSE);
        return;
    }

    // Reintento throttleado de BE si quedó pendiente por un PositionModify fallido
    RetryBreakevenIfPending();

    // Gestionar TPs
    ManageTPs(currentPrice);
}

// ==========================================
// GESTIÓN DE TPs (unificada - TP5 ya no es caso especial)
// ==========================================
bool CheckAndExecuteTP(int tpLevel, double tpPrice, double currentPrice, bool isLong, bool closeAll = false) {
    if(tpPrice <= 0.0) {
        Log(DEBUG_LVL, "TP_SKIP", StringFormat("TP%d saltado: precio no válido (%.5f)", tpLevel, tpPrice));
        return false;
    }

    bool priceReached = (isLong && currentPrice >= tpPrice) || (!isLong && currentPrice <= tpPrice);

    if(!currentTP.levelFlags[tpLevel] && priceReached) {
        currentTP.currentLevel = MathMax(currentTP.currentLevel, tpLevel);

        // Tramo pre-calculado al abrir (lotes enteros). closeAll (TP5) barre todo el remanente.
        double levelVolume = currentTP.levelVolumes[tpLevel];
        bool hasVolumeToClose = closeAll || (levelVolume > 0);
        bool shouldActivateBE = (BE_LEVEL == tpLevel && !currentTP.slMovedToBE);

        Log(INFO_LVL, "TP_HIT", StringFormat("TP%d alcanzado! Precio=%.5f, Target=%.5f, %s, BE=%s",
            tpLevel, currentPrice, tpPrice,
            (closeAll ? "Cerrando TODO" : StringFormat("Lotes=%.2f", levelVolume)),
            (shouldActivateBE ? "SÍ" : "NO")));

        if(hasVolumeToClose) {
            double volumeToClose = closeAll ? currentTP.currentVolume : levelVolume;
            ClosePartialPosition(volumeToClose);
        }

        // Si ese cierre completó el trade, ClosePartialPosition ya hizo ReportClose + EndTrade
        // (estado reseteado y state file borrado). No seguir: marcar flags/SaveState aquí recrearía
        // un state file fantasma con signalId=0.
        if(!currentTP.isActive) return true;

        if(shouldActivateBE) {
            SetBreakeven();
        }

        currentTP.levelFlags[tpLevel] = true;
        SaveState();
        return true;
    }
    return false;
}

void ManageTPs(double currentPrice) {
    // Si un TP completa el trade (ClosePartialPosition -> EndTrade), cortar: seguir iterando
    // dispararía CheckAndExecuteTP sobre un estado ya reseteado (logs espurios / mutación de currentLevel).
    bool isLong = (currentTP.direction == "LONG");   // 2a: calculado 1× por tick, no 1× por TP
    CheckAndExecuteTP(1, currentTP.tp1, currentPrice, isLong);
    if(!currentTP.isActive) return;
    CheckAndExecuteTP(2, currentTP.tp2, currentPrice, isLong);
    if(!currentTP.isActive) return;
    CheckAndExecuteTP(3, currentTP.tp3, currentPrice, isLong);
    if(!currentTP.isActive) return;
    CheckAndExecuteTP(4, currentTP.tp4, currentPrice, isLong);
    if(!currentTP.isActive) return;

    // TP5: cierra TODO el volumen restante (incluye cualquier sobrante de config < 100%)
    if(currentTP.currentVolume > 0) {
        CheckAndExecuteTP(5, currentTP.tp5, currentPrice, isLong, true);
    }
}

// ==========================================
// BREAKEVEN
// ==========================================
// Mueve el SL a entry. Devuelve true si quedó aplicado. Los fallos (no se pudo seleccionar la
// posición, o PositionModify rechazado por el broker — p.ej. distancia < STOPS_LEVEL) se loguean
// y devuelven false: RetryBreakevenIfPending lo reintenta 1×/barra hasta que entra o cierra el trade.
bool SetBreakeven() {
    if(!position.SelectByTicket(currentTP.ticket)) {
        Log(WARNING_LVL, "BE", StringFormat("BE no aplicado: no se pudo seleccionar la posición (ticket=%d). Reintentará.",
            currentTP.ticket));
        return false;
    }

    double newSL = currentTP.entry;
    if(!trade.PositionModify(position.Ticket(), newSL, position.TakeProfit())) {
        Log(WARNING_LVL, "BE", StringFormat("BE no aplicado: PositionModify falló %d (%s), SL→%.5f. Reintentará.",
            trade.ResultRetcode(), trade.ResultRetcodeDescription(), newSL));
        return false;
    }

    currentTP.slMovedToBE = true;
    currentTP.currentSL = newSL;
    SaveState();

    Log(INFO_LVL, "BE", StringFormat("BE activado en TP%d: SL %.5f → %.5f, Vol restante=%.2f",
        currentTP.currentLevel, currentTP.originalSL, newSL, currentTP.currentVolume));

    double currentPrice = GetCurrentPrice(currentTP.direction);

    ReportProgress(currentTP.signalId, currentTP.currentLevel, currentTP.closedPercent,
                  currentTP.currentVolume, currentPrice, 0.0,
                  "Stop Loss movido a Breakeven", newSL);
    return true;
}

// Reintento throttleado de breakeven. Si el nivel de BE ya se alcanzó pero el SL no llegó a BE
// (un PositionModify previo falló, típicamente por T1 < STOPS_LEVEL), reintenta 1×/barra M1.
// A medida que el precio se aleja del entry la distancia crece y el modify se vuelve válido; el
// gate por barra evita el spam de un reintento por tick. Se auto-cura tras reinicios del EA.
void RetryBreakevenIfPending() {
    if(BE_LEVEL <= 0 || currentTP.slMovedToBE || currentTP.currentLevel < BE_LEVEL) return;

    static datetime lastTryBar = 0;
    datetime curBar = iTime(currentSymbol, PERIOD_M1, 0);
    if(curBar == lastTryBar) return;   // ya se intentó en esta barra
    lastTryBar = curBar;

    Log(INFO_LVL, "BE", StringFormat("Reintentando BE (nivel %d alcanzado, SL aún no en BE)...", currentTP.currentLevel));
    SetBreakeven();
}

// ==========================================
// CIERRE PARCIAL
// ==========================================
bool ClosePartialPosition(double volume) {
    if(!position.SelectByTicket(currentTP.ticket)) return false;

    if(volume > position.Volume()) {
        volume = position.Volume();
    }

    double currentPrice = GetCurrentPrice(currentTP.direction);
    double closedPnl = CalculatePNL(currentPrice, volume);

    if(!trade.PositionClosePartial(position.Ticket(), volume))
        return false;

    currentTP.totalClosedVolume += volume;
    currentTP.closedPercent = (currentTP.totalClosedVolume / currentTP.originalVolume) * 100.0;

    // Releer el volumen REAL tras el cierre (no inferirlo de un snapshot previo a la operacion):
    // si la posicion ya no existe, el remanente es 0.
    double remaining = position.SelectByTicket(currentTP.ticket) ? position.Volume() : 0.0;
    currentTP.currentVolume = remaining;

    ReportProgress(currentTP.signalId, currentTP.currentLevel, currentTP.closedPercent,
                  remaining, currentPrice, closedPnl, "TP parcial");

    Log(INFO_LVL, "PARTIAL_CLOSE", StringFormat("TP%d: Cerrado %.2f lots (%.1f%%) a %.5f, PNL=%.2f, Restante=%.2f",
        currentTP.currentLevel, volume, (volume/currentTP.originalVolume)*100, currentPrice, closedPnl, remaining));

    SaveState();

    // COMPLETE solo cuando la posicion ya NO existe (remaining 0). El remanente min-lot
    // (ej: el ~10% de TP5 que redondea a 1 lote minimo) NO se completa aca: queda trackeado
    // y lo cierra TP5 (closeAll) a SU precio — o el SL. Asi cada TP cierra su tramo y el ultimo
    // reporta el cierre, sin huerfanos y respetando la distribucion de TPs.
    if(remaining <= 0) {
        ReportClose(currentTP.signalId, currentTP.currentLevel, REASON_COMPLETE, currentPrice, 0.0);
        EndTrade();
    }
    return true;
}

// NUEVO: Reportar ejecución completa cuando pending order se convierte en posición
void ReportPendingExecuted(int userSignalId, ulong ticket, double entryPrice, 
                           double stopLoss, double volume) {
    
    string url = BuildAPIUrl("progress", userSignalId);
    
    JsonBuilder jb;
    jb.AddBool("now_open", true);
    jb.AddString("trade_id", IntegerToString(ticket));
    jb.AddDouble("real_entry_price", entryPrice);
    jb.AddDouble("real_stop_loss", stopLoss);
    jb.AddDouble("real_volume", volume, 2);
    jb.AddInt("current_level", 0);
    jb.AddDouble("volume_closed_percent", 0.0, 2);
    jb.AddDouble("remaining_volume", volume, 2);
    jb.AddDouble("gross_pnl", 0.0, 2);
    jb.AddDouble("last_price", entryPrice);
    jb.AddString("message", "Orden pendiente ejecutada - datos completos");
    AddReportFooter(jb, true);
    
    PostReport(url, jb.Build(), "Pending execution");
}

// ==========================================
// PENDING ORDER MONITORING
// ==========================================
void CheckPendingOrderExecution() {
    if(FindOwnPosition()) {
        Log(INFO_LVL, "PENDING", "Orden pendiente ejecutada");
        AdoptLivePosition(true);   // adopta campos + reporta ejecución + persiste
        return;
    }

    if(!OrderSelect(currentTP.ticket)) {
        Log(WARNING_LVL, "PENDING", "Orden pendiente cancelada");
        ReportClose(currentTP.signalId, EXIT_ERROR, REASON_ORDER_CANCELLED, 0, 0);
        EndTrade();
    }
}

// ==========================================
// CONSULTA DE SEÑALES
// ==========================================
void CheckForSignals() {
    string url = BuildAPIUrl("get_signals", 0, TICKER_SYMBOL);
    Log(DEBUG_LVL, "POLL", StringFormat("Polling señales: %s", url));
    APIResponse response = SendAPIRequest("GET", url, "", true);

    if(response.result != API_SUCCESS) {
        Log(WARNING_LVL, "SIGNALS", StringFormat("Error consultando señales: %s (HTTP %d)", response.message, response.httpCode));
        return;
    }

    Log(DEBUG_LVL, "POLL", "Polling OK, procesando respuesta...");
    ProcessSignalResponse(response.data);
}

// Rechaza una señal sin operar: loguea el motivo (categoria SIGNAL) y reporta el cierre. El caller
// hace el return. Unifica el patron Log+ReportClose repetido en la validacion de ProcessSignalResponse.
void RejectSignal(int userSignalId, int exitCode, string reason, string logMsg) {
    Log(ERROR_LVL, "SIGNAL", logMsg);
    ReportClose(userSignalId, exitCode, reason, 0, 0);
}

void ProcessSignalResponse(string jsonResponse) {
    if(StringFind(jsonResponse, "\"signal\":null") > -1) {
        Log(DEBUG_LVL, "POLL", "Sin señales pendientes (signal:null)");
        return;
    }
    if(StringFind(jsonResponse, "\"success\":true") == -1) {
        Log(DEBUG_LVL, "POLL", "Respuesta sin success:true — Body: " + TruncLog(jsonResponse));
        return;
    }

    Log(INFO_LVL, "JSON_DEBUG", "JSON recibido (500 chars): " + TruncLog(jsonResponse));

    SimpleJSONParser parser(jsonResponse);

    int userSignalId = parser.GetInt("user_signal_id");
    if(userSignalId <= 0) {
        Log(DEBUG_LVL, "POLL", "user_signal_id inválido o ausente en respuesta");
        return;
    }

    string opType = parser.GetString("op_type");
    double entry = parser.GetDouble("entry");
    double sl1 = parser.GetArrayDouble("stoploss", 0);
    double sl2 = parser.GetArrayDouble("stoploss", 1);

    double tp1 = parser.GetArrayDouble("tps", 0);
    double tp2 = parser.GetArrayDouble("tps", 1);
    double tp3 = parser.GetArrayDouble("tps", 2);
    double tp4 = parser.GetArrayDouble("tps", 3);
    double tp5 = parser.GetArrayDouble("tps", 4);

    Log(INFO_LVL, "SIGNAL", StringFormat("Nueva señal: ID=%d, %s, Entry=%.5f, SL1=%.5f, SL2=%.5f, TPs=[%.5f,%.5f,%.5f,%.5f,%.5f]",
        userSignalId, opType, entry, sl1, sl2, tp1, tp2, tp3, tp4, tp5));

    JournalReset();
    currentJournal.ts_signal = JournalIsoTime();
    currentJournal.signal_id = userSignalId;
    currentJournal.dir       = opType;

    // Direccion: el EA decide LONG vs SHORT con (opType == "LONG"); cualquier otro valor caeria en
    // la rama SHORT y abriria al reves. Exigir el string exacto y rechazar si no.
    if(opType != "LONG" && opType != "SHORT") {
        RejectSignal(userSignalId, EXIT_INVALID, REASON_INVALID_OPTYPE,
            StringFormat("Señal rechazada ID=%d: op_type inválido '%s' (esperado LONG o SHORT)", userSignalId, opType));
        return;
    }

    if(entry <= 0) {
        RejectSignal(userSignalId, EXIT_INVALID, REASON_INVALID_ENTRY,
            StringFormat("Señal rechazada ID=%d: entry inválido (%.5f)", userSignalId, entry));
        return;
    }

    if(sl1 <= 0) {
        RejectSignal(userSignalId, EXIT_INVALID, REASON_INVALID_SL,
            StringFormat("Señal rechazada ID=%d: SL1 inválido", userSignalId));
        return;
    }

    if(tp1 <= 0 || tp2 <= 0 || tp3 <= 0 || tp4 <= 0 || tp5 <= 0) {
        RejectSignal(userSignalId, EXIT_INVALID, REASON_INVALID_TPS,
            StringFormat("Señal rechazada ID=%d: TPs incompletos. Faltan: %s%s%s%s%s",
            userSignalId,
            (tp1 <= 0 ? "TP1 " : ""),
            (tp2 <= 0 ? "TP2 " : ""),
            (tp3 <= 0 ? "TP3 " : ""),
            (tp4 <= 0 ? "TP4 " : ""),
            (tp5 <= 0 ? "TP5 " : "")));
        return;
    }

    // Geometría: validar lado y orden según la dirección. Precios crudos (la corrección divide por un
    // factor >0 y preserva el orden). Una señal malformada ejecutaría absurdo: un TP del lado equivocado
    // se "alcanza" al instante y un SL del lado equivocado se espeja a un nivel inventado.
    bool isLong = (opType == "LONG");

    bool slOk = isLong ? (sl1 < entry) : (sl1 > entry);
    if(!slOk) {
        RejectSignal(userSignalId, EXIT_INVALID, REASON_INVALID_SL,
            StringFormat("Señal rechazada ID=%d: SL del lado equivocado (%s entry=%.5f SL=%.5f)",
            userSignalId, opType, entry, sl1));
        return;
    }

    bool tpsOk = isLong
        ? (entry < tp1 && tp1 < tp2 && tp2 < tp3 && tp3 < tp4 && tp4 < tp5)
        : (entry > tp1 && tp1 > tp2 && tp2 > tp3 && tp3 > tp4 && tp4 > tp5);
    if(!tpsOk) {
        RejectSignal(userSignalId, EXIT_INVALID, REASON_INVALID_TPS,
            StringFormat("Señal rechazada ID=%d: TPs fuera de orden/lado (%s entry=%.5f TPs=[%.5f,%.5f,%.5f,%.5f,%.5f])",
            userSignalId, opType, entry, tp1, tp2, tp3, tp4, tp5));
        return;
    }

    if(ExecuteTrade(userSignalId, opType, entry, sl1, tp1, tp2, tp3, tp4, tp5, sl2)) {
        Log(INFO_LVL, "TRADE", "Trade ejecutado exitosamente");
    }
}

// ==========================================
// EJECUCIÓN DE TRADE (refactorizado en subfunciones)
// ==========================================

// Subfuncion: Aplicar correccion de precios. Retorna false si falla.
bool ApplyPriceCorrection(double &entryPrice, double &stopLoss,
                          double &tp1, double &tp2, double &tp3, double &tp4, double &tp5,
                          int userSignalId, double &correctionFactor) {

    string errorMessage = "";
    correctionFactor = CalculatePriceCorrection(TICKER_SYMBOL, errorMessage);

    if(correctionFactor <= 0.0) {
        ReportClose(userSignalId, EXIT_ERROR, REASON_PRICE_CORR_ERR, 0, 0);
        return false;
    }

    if(correctionFactor != 1.0) {
        entryPrice /= correctionFactor;
        stopLoss /= correctionFactor;
        tp1 /= correctionFactor;
        tp2 /= correctionFactor;
        tp3 /= correctionFactor;
        tp4 /= correctionFactor;
        tp5 /= correctionFactor;

        Log(INFO_LVL, "PRICE_CORR", StringFormat("Precios corregidos: Entry=%.5f, SL=%.5f, TPs=[%.5f,%.5f,%.5f,%.5f,%.5f]",
                entryPrice, stopLoss, tp1, tp2, tp3, tp4, tp5));
    } else {
        Log(INFO_LVL, "PRICES", StringFormat("Precios originales (sin corrección): Entry=%.5f, SL=%.5f",
                entryPrice, stopLoss));
    }
    return true;
}

// Subfuncion: Validar spread como fraccion de T1 (asset-agnostic). Retorna false si muy alto.
bool ValidateSpread(int userSignalId, double t1) {
    double spreadReal = SymbolInfoDouble(currentSymbol, SYMBOL_ASK) - SymbolInfoDouble(currentSymbol, SYMBOL_BID);
    double spreadTol  = C_SPREAD_RATIO * t1;
    double pctT1      = (t1 > 0) ? (spreadReal / t1 * 100.0) : 0.0;

    // Registrar en el journal SIEMPRE, antes de decidir: si se rechaza, este es justo el dato que lo explica.
    currentJournal.spread_real = spreadReal;
    currentJournal.spread_tol  = spreadTol;

    // Check OPCIONAL: OFF por defecto (alta liquidez). El spread queda registrado arriba para el
    // journal/analisis, pero no rechaza la señal.
    if(!ENABLE_SPREAD_CHECK) {
        Log(DEBUG_LVL, "SPREAD", StringFormat("check OFF | real=%.5f | %.1f%% T1 (informativo)", spreadReal, pctT1));
        return true;
    }

    // spreadReal <= 0: dato no disponible (algunos brokers reportan 0 un instante) -> no rechazar por 0
    if(spreadReal > 0 && spreadReal > spreadTol) {
        Log(ERROR_LVL, "SPREAD", StringFormat("real=%.5f | tol=%.5f (c=%.2f*T1) | %.1f%% T1 -> RECHAZA SPREAD_TOO_HIGH",
            spreadReal, spreadTol, C_SPREAD_RATIO, pctT1));
        ReportClose(userSignalId, EXIT_ERROR, REASON_SPREAD_HIGH, 0, 0);
        return false;
    }
    Log(INFO_LVL, "SPREAD", StringFormat("real=%.5f | tol=%.5f (c=%.2f*T1) | %.1f%% T1 -> OK",
        spreadReal, spreadTol, C_SPREAD_RATIO, pctT1));
    return true;
}

// Subfuncion: Determinar tipo de orden. Banda market = k*T1, con k distinto por lado:
//   lado FAVORABLE (STOP): market daria mejor precio que el entry  -> k_stop  (mas ancho)
//   lado ADVERSO  (LIMIT): market seria chase (peor precio)        -> k_limit (mas angosto)
ENUM_ORDER_TYPE DetermineOrderType(string opType, double entryPrice, double t1, double &currentPrice, string &orderDecision) {
    currentPrice = (opType == "LONG") ?
                   SymbolInfoDouble(currentSymbol, SYMBOL_ASK) :
                   SymbolInfoDouble(currentSymbol, SYMBOL_BID);

    double diff = MathAbs(entryPrice - currentPrice);

    // Lado favorable = el precio actual esta del lado que daria mejor entrada que el entry de la señal.
    bool stopSide = (opType == "LONG") ? (currentPrice < entryPrice) : (currentPrice > entryPrice);
    double k      = stopSide ? K_STOP_RATIO : K_LIMIT_RATIO;
    double tol    = k * t1;
    double pctT1  = (t1 > 0) ? (diff / t1 * 100.0) : 0.0;
    string side   = stopSide ? "FAVORABLE" : "ADVERSO";

    ENUM_ORDER_TYPE orderType;
    if(diff <= tol) {
        orderType     = (opType == "LONG") ? ORDER_TYPE_BUY : ORDER_TYPE_SELL;
        orderDecision = "MARKET";
    } else if(stopSide) {
        orderType     = (opType == "LONG") ? ORDER_TYPE_BUY_STOP : ORDER_TYPE_SELL_STOP;
        orderDecision = "STOP";
    } else {
        orderType     = (opType == "LONG") ? ORDER_TYPE_BUY_LIMIT : ORDER_TYPE_SELL_LIMIT;
        orderDecision = "LIMIT";
    }

    Log(INFO_LVL, "ORDER", StringFormat("price=%.5f | dist=%.5f (%.1f%% T1) | side=%s | k=%.2f tol=%.5f -> %s",
        currentPrice, diff, pctT1, side, k, tol, orderDecision));

    currentJournal.price_signal = currentPrice;
    currentJournal.dist_entry   = diff;
    currentJournal.side         = side;
    currentJournal.k_band       = tol;
    currentJournal.order_type   = orderDecision;

    return orderType;
}

bool ExecuteTrade(int userSignalId, string opType, double entryPrice, double stopLoss,
                  double tp1, double tp2, double tp3, double tp4, double tp5,
                  double sl2 = 0) {

    if(currentTP.isActive) {
        Log(WARNING_LVL, "TRADE", StringFormat("Señal IGNORADA (posición activa): nueva=%d, actual=%d ticket=%d",
            userSignalId, currentTP.signalId, currentTP.ticket));
        return false;
    }

    // Guardar precios originales (pre-corrección)
    double originalEntry = entryPrice, originalSL1 = stopLoss, originalSL2 = sl2;
    double originalTP1 = tp1, originalTP2 = tp2, originalTP3 = tp3, originalTP4 = tp4, originalTP5 = tp5;

    // 1. Corrección de precios
    double correctionFactor;
    if(!ApplyPriceCorrection(entryPrice, stopLoss, tp1, tp2, tp3, tp4, tp5, userSignalId, correctionFactor))
        return false;

    // 1.5 Escala de la señal: T1 (distancia a TP1) para los gates de entrada/costo.
    //     R (entry->SL) se sigue usando para el volumen. Ambos con precios ya corregidos.
    double t1 = MathAbs(entryPrice - tp1);
    if(t1 <= 0) t1 = MathAbs(entryPrice - stopLoss);   // fallback a R si TP1 degenerado

    Log(INFO_LVL, "GATES", StringFormat("#%d %s %s | entry=%.5f SL=%.5f TP1=%.5f | R=%.5f T1=%.5f",
        userSignalId, TICKER_SYMBOL, opType, entryPrice, stopLoss, tp1, MathAbs(entryPrice - stopLoss), t1));

    currentJournal.corr_on     = (correctionFactor != 1.0);
    currentJournal.corr_factor = correctionFactor;
    currentJournal.entry_raw   = originalEntry;
    currentJournal.sl_raw      = originalSL1;
    currentJournal.entry       = entryPrice;
    currentJournal.sl          = stopLoss;
    currentJournal.tp1 = tp1; currentJournal.tp2 = tp2; currentJournal.tp3 = tp3; currentJournal.tp4 = tp4; currentJournal.tp5 = tp5;
    currentJournal.rdist       = MathAbs(entryPrice - stopLoss);
    currentJournal.t1          = t1;

    // 2. Validar spread (fraccion de T1)
    if(!ValidateSpread(userSignalId, t1))
        return false;

    // 3. Calcular volumen
    double calculatedVolume = CalculateVolumeOptimized(entryPrice, stopLoss);
    if(calculatedVolume <= 0) {
        double balance = AccountInfoDouble(ACCOUNT_BALANCE);
        Log(ERROR_LVL, "VOLUME", StringFormat("Volumen=0: Balance=%.2f, Risk%%=%.1f, Entry=%.5f, SL=%.5f",
            balance, RISK_PERCENT, entryPrice, stopLoss));
        ReportClose(userSignalId, EXIT_ERROR, REASON_VOLUME_ERR, 0, 0);
        return false;
    }

    // 4. Determinar tipo de orden
    double currentPrice;
    string orderDecision;
    ENUM_ORDER_TYPE orderType = DetermineOrderType(opType, entryPrice, t1, currentPrice, orderDecision);

    // 5. Configurar SL
    double slDistance = MathAbs(entryPrice - stopLoss);
    double effectiveDistance = ENABLE_CODE_STOP ? (slDistance * SAFETY_FACTOR) : slDistance;
    double orderStopLoss = (opType == "LONG") ? (entryPrice - effectiveDistance) : (entryPrice + effectiveDistance);

    // Alinear a tick/digits los precios que van al broker (post-corrección quedan con decimales
    // arbitrarios). entryPrice = precio de la PENDIENTE; orderStopLoss = SL de market y pendiente.
    // Se hace antes del chequeo de STOPS_LEVEL para que slDistFinal refleje lo que realmente se manda.
    entryPrice    = NormalizePrice(entryPrice);
    orderStopLoss = NormalizePrice(orderStopLoss);

    Log(INFO_LVL, "SL_CALC", StringFormat("Original=%.5f, Distance=%.5f, Factor=%.2f, Final=%.5f",
            stopLoss, slDistance, ENABLE_CODE_STOP ? SAFETY_FACTOR : 1.0, orderStopLoss));

    // 5.5 Validar distancia minima del broker (STOPS_LEVEL): si el SL queda mas cerca, el broker rechaza.
    double point    = SymbolInfoDouble(currentSymbol, SYMBOL_POINT);
    double stopsMin = (double)SymbolInfoInteger(currentSymbol, SYMBOL_TRADE_STOPS_LEVEL) * point;
    double slDistFinal = MathAbs(entryPrice - orderStopLoss);
    currentJournal.stops_min = stopsMin;   // journal SIEMPRE, antes de decidir (tambien si se rechaza)
    currentJournal.sl_dist   = slDistFinal;
    if(stopsMin > 0 && slDistFinal < stopsMin) {
        Log(ERROR_LVL, "STOPS", StringFormat("broker_min=%.5f | sl_dist=%.5f -> RECHAZA SL_TOO_CLOSE", stopsMin, slDistFinal));
        ReportClose(userSignalId, EXIT_ERROR, REASON_SL_TOO_CLOSE, 0, 0);
        return false;
    }
    Log(INFO_LVL, "STOPS", StringFormat("broker_min=%.5f | sl_dist=%.5f -> OK", stopsMin, slDistFinal));

    // 6. Ejecutar orden
    bool success = false;
    ulong ticket = 0;
    bool isMarketOrder = (orderType == ORDER_TYPE_BUY || orderType == ORDER_TYPE_SELL);

    if(isMarketOrder) {
        ApplyMarketDeviation(t1, point);
        success = trade.PositionOpen(currentSymbol, orderType, calculatedVolume, 0, orderStopLoss, 0, TRADE_COMMENT);
    } else {
        success = trade.OrderOpen(currentSymbol, orderType, calculatedVolume, 0, entryPrice, orderStopLoss, 0,
                                 ORDER_TIME_DAY, 0, TRADE_COMMENT);

        // E: si el broker rechaza la pendiente por caer dentro de STOPS_LEVEL (precio a pocos puntos
        // del entry), entrar a MARKET. El fill queda ~en el entry (lado favorable: igual o mejor;
        // lado adverso: chase mínimo). Self-gateado: en brokers con stops level 0 nunca se rechaza.
        if(!success && trade.ResultRetcode() == TRADE_RETCODE_INVALID_STOPS) {
            Log(WARNING_LVL, "ORDER", StringFormat("Pendiente %s rechazada por cercanía (INVALID_STOPS) -> fallback MARKET",
                EnumToString(orderType)));
            orderType     = (opType == "LONG") ? ORDER_TYPE_BUY : ORDER_TYPE_SELL;
            isMarketOrder = true;
            orderDecision = "MARKET_FB";
            currentJournal.order_type = orderDecision;
            ApplyMarketDeviation(t1, point);
            success = trade.PositionOpen(currentSymbol, orderType, calculatedVolume, 0, orderStopLoss, 0, TRADE_COMMENT);
        }
    }
    ticket = trade.ResultOrder();   // ticket de la ORDEN (correcto para pendientes)

    // Para MARKET la posición es la fuente de verdad del éxito, NO ResultOrder: algunos brokers
    // devuelven order=0 en ejecución instantánea (sólo setean deal). Si exigiéramos ResultOrder>0,
    // un fill real se reportaría EXECUTION_FAILED dejando la posición huérfana (y abriría un
    // duplicado en el próximo poll). Para PENDIENTES sí se exige el ticket de la orden.
    double realEntryPrice = entryPrice;
    ulong positionID = 0;
    bool opened;

    if(isMarketOrder) {
        // La posición es la fuente de verdad. Tras un open exitoso el cache de posiciones del
        // terminal puede tardar unos ms en reflejarla: reintentar FindOwnPosition antes de concluir.
        bool found = false;
        for(int attempt = 0; success && attempt < 5; attempt++) {
            if(FindOwnPosition()) { found = true; break; }
            Sleep(50);
        }

        if(found) {
            ticket         = position.Ticket();      // ticket REAL de posición (consistente con AdoptLivePosition)
            positionID     = position.Identifier();
            realEntryPrice = position.PriceOpen();
            double slipReal = MathAbs(realEntryPrice - currentPrice);
            Log(INFO_LVL, "SLIPPAGE", StringFormat("real=%.5f | pedido=%.5f fill=%.5f", slipReal, currentPrice, realEntryPrice));
            currentJournal.slip_real = slipReal;
            entryPrice = realEntryPrice;
            opened = true;
        } else if(success && ticket > 0) {
            // No hallada tras reintentos pero el broker dio un order ticket válido: seguir con él
            // (best effort); OnTick la maneja por SelectByTicket.
            Log(WARNING_LVL, "TRADE", StringFormat("PositionOpen OK, posición no hallada tras reintentos; usando ResultOrder=%d", ticket));
            opened = true;
        } else {
            // success pero sin posición NI order ticket trackeable (o open fallido): no proseguir con
            // ticket 0 (OnTick lo cerraría en falso). Reportar EXECUTION_FAILED.
            if(success)
                Log(ERROR_LVL, "TRADE", "PositionOpen reportó OK pero no hay posición ni ticket trackeable -> EXECUTION_FAILED");
            opened = false;
        }
    } else {
        opened = (success && ticket > 0);
    }

    if(opened) {
        // Setup state (ya con positionID resuelto)
        currentJournal.real_entry  = entryPrice;
        currentJournal.real_volume = calculatedVolume;

        SetupTPState(userSignalId, opType, entryPrice, stopLoss, calculatedVolume,
                     tp1, tp2, tp3, tp4, tp5, ticket, isMarketOrder, positionID);

        Log(INFO_LVL, "TRADE_OPEN", StringFormat("%s %s, Ticket=%d, PosID=%d, Vol=%.2f, Entry=%.5f, Corrección=%.4f",
            (isMarketOrder ? "MARKET" : "PENDING"), opType, ticket, positionID, calculatedVolume, entryPrice, correctionFactor));

        PriceSet rawPrices;
        rawPrices.entry = originalEntry; rawPrices.sl1 = originalSL1; rawPrices.sl2 = originalSL2;
        rawPrices.tp1 = originalTP1; rawPrices.tp2 = originalTP2; rawPrices.tp3 = originalTP3;
        rawPrices.tp4 = originalTP4; rawPrices.tp5 = originalTP5;

        PriceSet corrPrices;
        corrPrices.entry = entryPrice; corrPrices.sl1 = orderStopLoss; corrPrices.sl2 = originalSL2;
        corrPrices.tp1 = tp1; corrPrices.tp2 = tp2; corrPrices.tp3 = tp3; corrPrices.tp4 = tp4; corrPrices.tp5 = tp5;

        ReportOpen(userSignalId, isMarketOrder, orderType, entryPrice, orderStopLoss, calculatedVolume, ticket,
                   opType, rawPrices, corrPrices);
        return true;
    } else {
        uint retcode = trade.ResultRetcode();
        string retdesc = trade.ResultRetcodeDescription();
        Log(ERROR_LVL, "TRADE", StringFormat("Orden falló: %d (%s), Tipo=%s, Vol=%.2f, Entry=%.5f, SL=%.5f",
            retcode, retdesc, EnumToString(orderType), calculatedVolume, entryPrice, orderStopLoss));
        ReportClose(userSignalId, EXIT_ERROR, REASON_EXEC_FAILED, 0, 0);
        return false;
    }
}

// ==========================================
// SETUP TP STATE (recibe positionID, no re-busca)
// ==========================================
void SetupTPState(int signalId, string direction, double entry, double originalStopLoss, double volume,
                  double tp1, double tp2, double tp3, double tp4, double tp5,
                  ulong ticket, bool isMarketOrder, ulong positionID = 0) {

    currentTP.isActive = isMarketOrder;
    currentTP.signalId = signalId;
    currentTP.direction = direction;
    currentTP.ticket = ticket;
    currentTP.originalVolume = volume;
    currentTP.currentVolume = volume;
    currentTP.totalClosedVolume = 0;
    currentTP.closedPercent = 0;
    currentTP.currentLevel = isMarketOrder ? 0 : -2;
    currentTP.slMovedToBE = false;
    currentTP.tradeStartUtc = TimeGMT();   // ancla de los cortes por horario NY
    SetupTimeCutoffs();                    // precalcula los instantes UTC de corte (una sola vez)

    ArrayInitialize(currentTP.levelFlags, false);

    // Reparto de salidas en lotes ENTEROS, calculado UNA sola vez al abrir: cada TP cierra un
    // tramo fijo y el ultimo lleva el remanente a 0. Mata el "puchito" de raiz (no nace sub-minimo).
    SymbolSpecs specs = GetSymbolSpecs();
    ComputeLevelVolumes(volume, specs, currentTP.levelVolumes);
    Log(INFO_LVL, "TP_SPLIT", StringFormat("Reparto (vol=%.2f): TP1=%.2f TP2=%.2f TP3=%.2f TP4=%.2f TP5=%.2f",
        volume, currentTP.levelVolumes[1], currentTP.levelVolumes[2], currentTP.levelVolumes[3],
        currentTP.levelVolumes[4], currentTP.levelVolumes[5]));

    currentTP.entry = entry;
    currentTP.originalSL = originalStopLoss;
    currentTP.currentSL = originalStopLoss;
    currentTP.tp1 = tp1;
    currentTP.tp2 = tp2;
    currentTP.tp3 = tp3;
    currentTP.tp4 = tp4;
    currentTP.tp5 = tp5;

    if(isMarketOrder && positionID > 0) {
        currentTP.positionID = positionID;
    } else if(!isMarketOrder) {
        currentTP.positionID = ticket;  // Temporal para pending, se actualiza en CheckPendingOrderExecution
    }

    Log(INFO_LVL, "SETUP", StringFormat("TP State: SignalID=%d, Ticket=%d, PosID=%d, Active=%s",
        signalId, ticket, currentTP.positionID, (isMarketOrder ? "YES" : "PENDING")));

    SaveState();
}
