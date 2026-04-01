#property copyright "TelegramSignals"
#property version   "9.00"
#property description "EA refactorizado - helpers, JsonBuilder, deteccion de cierre manual mejorada"

// LIBRERÍAS
#include <Trade\Trade.mqh>
#include <Trade\PositionInfo.mqh>

// CONSTANTES
#define HTTP_TIMEOUT 5000

// Enums
enum LogLevel { DEBUG_LVL, INFO_LVL, WARNING_LVL, ERROR_LVL };
enum APIResult { API_SUCCESS, API_HTTP_ERROR, API_JSON_ERROR, API_BUSINESS_ERROR, API_NETWORK_ERROR };

// INPUTS
input group "=== API Configuration ==="
input string    API_URL = "http://bx-trade.local/api/";
input int       USER_ID = 1;

input group "=== Trading Settings ==="
input string    TICKER_SYMBOL = "EURUSD";
input double    RISK_PERCENT = 2.0;
input int       POLL_INTERVAL = 10;
input double    MAX_SPREAD = 500.0;
input int       PRICE_TOLERANCE_POINTS = 50;
input double    PRICE_TOLERANCE_PERCENT = 0.0;  // 0.0 = usar points, > 0 = usar porcentaje (prioridad)

input group "=== Price Correction ==="
input bool      ENABLE_PRICE_CORRECTION = true;
input double    MAX_PRICE_DEVIATION = 5.0;
input int       MAX_TIMESTAMP_HOURS = 4;

input group "=== Take Profit Settings ==="
input double    TP1_PERCENT = 0.0;
input double    TP2_PERCENT = 40.0;
input double    TP3_PERCENT = 30.0;
input double    TP4_PERCENT = 20.0;
input double    TP5_PERCENT = 10.0;
input int       BE_LEVEL = 1;                       // 0=never, 1=TP1, 2=TP2, etc.

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
    bool slMovedToBE;

    // PRICES
    double entry;
    double originalSL;
    double currentSL;
    double tp1, tp2, tp3, tp4, tp5;
};

struct TPConfig {
    double percents[5];
    bool enabled[5];
    double totalPercent;
    bool isValid;
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
        if(StringLen(value) == 0) return false;
        for(int i = 0; i < StringLen(value); i++) {
            ushort char_code = StringGetCharacter(value, i);
            if(char_code < '0' || char_code > '9') {
                if(char_code != '.' && char_code != '-' && char_code != '+') {
                    return false;
                }
            }
        }
        return true;
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

    string GetString(string key, string defaultValue = "") {
        string search = "\"" + key + "\":\"";
        int pos = StringFind(json, search);
        if(pos == -1) return defaultValue;

        pos += StringLen(search);
        int endPos = StringFind(json, "\"", pos);

        return StringSubstr(json, pos, endPos - pos);
    }

    /*
    // VIEJO - bug: busca solo coma, para arrays cortos encuentra la coma fuera del array
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
        int endPos = StringFind(json, ",", pos);
        if(endPos == -1) endPos = StringFind(json, "]", pos);
        if(endPos == -1) return defaultValue;
        string value = StringSubstr(json, pos, endPos - pos);
        StringReplace(value, " ", "");
        StringReplace(value, "\t", "");
        StringReplace(value, "\n", "");
        StringReplace(value, "\r", "");
        return ValidateNumber(value) ? StringToDouble(value) : defaultValue;
    }
    */

    // NUEVO - fix minimal: busca coma Y corchete, usa el primero
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
        pairs[count++] = "\"" + key + "\":\"" + value + "\"";
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

// Busca posicion propia por Magic+Comment+Symbol. Retorna true si encontro y deja position seleccionada.
bool FindOwnPosition() {
    for(int i = 0; i < PositionsTotal(); i++) {
        if(position.SelectByIndex(i)) {
            if(position.Symbol() == currentSymbol &&
               position.Magic() == MAGIC_NUMBER &&
               position.Comment() == TRADE_COMMENT) {
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
    trade.SetDeviationInPoints(50);

    LogInitialization();
    EventSetTimer(POLL_INTERVAL);
    CheckForSignals();

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

    currentTP.entry = 0;
    currentTP.originalSL = 0;
    currentTP.currentSL = 0;
    currentTP.tp1 = currentTP.tp2 = currentTP.tp3 = currentTP.tp4 = currentTP.tp5 = 0;
}

void SetupBaseUrls() {
    signalsBaseUrl = API_URL + "signals/";
    futPriceBaseUrl = API_URL + "fut_price/";
}

bool ValidateAllInputs() {
    if(USER_ID <= 0 || RISK_PERCENT <= 0 || RISK_PERCENT > 10 || POLL_INTERVAL < 5) return false;
    if(BE_LEVEL < 0 || BE_LEVEL > 5) return false;
    if(SAFETY_FACTOR < 1.0 || SAFETY_FACTOR > 5.0) return false;

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
        config.enabled[i] = (config.percents[i] > 0);
        config.totalPercent += config.percents[i];
    }

    config.isValid = (config.totalPercent <= 100.0);
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
   Print("EA Signals v9.00 | User: ", USER_ID, " | Symbol: ", currentSymbol, " | BE Level: ", BE_LEVEL);
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
void ReportOpen(int userSignalId, bool isMarketOrder, ENUM_ORDER_TYPE orderType,
                double entryPrice, double stopLoss, double volume, ulong ticket,
                string opType = "", double signalEntry = 0, double signalSL1 = 0, double signalSL2 = 0,
                double signalTP1 = 0, double signalTP2 = 0, double signalTP3 = 0,
                double signalTP4 = 0, double signalTP5 = 0,
                double correctedEntry = 0, double correctedSL = 0, double correctedSL2 = 0,
                double correctedTP1 = 0, double correctedTP2 = 0, double correctedTP3 = 0,
                double correctedTP4 = 0, double correctedTP5 = 0) {

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

    // Agregar signal_data con precios originales (pre-corrección)
    if(signalEntry > 0 && opType != "") {
        string signalJson = "{";
        signalJson += "\"op_type\":\"" + opType + "\",";
        signalJson += "\"entry\":" + DoubleToString(signalEntry, 5) + ",";
        signalJson += "\"stoploss\":[" + DoubleToString(signalSL1, 5) + "," + DoubleToString(signalSL2, 5) + "],";
        signalJson += "\"tps\":[" + DoubleToString(signalTP1, 5) + "," + DoubleToString(signalTP2, 5) + ","
                + DoubleToString(signalTP3, 5) + "," + DoubleToString(signalTP4, 5) + ","
                + DoubleToString(signalTP5, 5) + "]";
        signalJson += "}";
        jb.AddRaw("signal_data", signalJson);
    }

    // NUEVO: Agregar mt_corrected_data con precios post-corrección (lo que realmente usa MT5)
    if(correctedEntry > 0 && opType != "") {
        string correctedJson = "{";
        correctedJson += "\"op_type\":\"" + opType + "\",";
        correctedJson += "\"entry\":" + DoubleToString(correctedEntry, 5) + ",";
        correctedJson += "\"stoploss\":[" + DoubleToString(correctedSL, 5) + "," + DoubleToString(correctedSL2, 5) + "],";
        correctedJson += "\"tps\":[" + DoubleToString(correctedTP1, 5) + "," + DoubleToString(correctedTP2, 5) + ","
                + DoubleToString(correctedTP3, 5) + "," + DoubleToString(correctedTP4, 5) + ","
                + DoubleToString(correctedTP5, 5) + "]";
        correctedJson += "}";
        jb.AddRaw("mt_corrected_data", correctedJson);
    }

    jb.AddString("symbol", TICKER_SYMBOL);
    jb.AddString("execution_time", TimeToString(TimeGMT(), TIME_DATE|TIME_SECONDS));

    string json = jb.Build();
    APIResponse response = SendAPIRequest("POST", url, json);
    if(response.result == API_SUCCESS) {
        Log(INFO_LVL, "REPORT", "Open reportado exitosamente");
    } else {
        Log(ERROR_LVL, "REPORT", StringFormat("Open FALLÓ: %s (HTTP %d) — Response: %s", response.message, response.httpCode, TruncLog(response.data)));
    }
}

void ReportProgress(int userSignalId, int level, double volumeClosedPercent,
                    double remainingVolume, double currentPrice, double pnl,
                    string message, bool nowOpen = false, double newStopLoss = 0.0) {

    string url = BuildAPIUrl("progress", userSignalId);

    JsonBuilder jb;
    // Solo campos dinámicos que cambian durante el trade - no sobreescribir execution_data
    jb.AddInt("current_level", level);
    jb.AddDouble("volume_closed_percent", volumeClosedPercent, 2);
    jb.AddDouble("remaining_volume", remainingVolume, 2);
    jb.AddDouble("gross_pnl", pnl, 2);
    jb.AddDouble("last_price", currentPrice);
    jb.AddString("message", message);
    jb.AddString("execution_time", TimeToString(TimeGMT(), TIME_DATE|TIME_SECONDS));

    // Solo para cuando pending order se ejecuta (una sola vez)
    if(nowOpen) {
        jb.AddBool("now_open", true);
        jb.AddDouble("real_entry_price", currentTP.entry);
    }

    // Solo cuando se mueve SL a BE
    if(newStopLoss > 0.0) {
        jb.AddDouble("new_stop_loss", newStopLoss);
    }

    string json = jb.Build();
    APIResponse response = SendAPIRequest("POST", url, json);
    if(response.result == API_SUCCESS) {
        Log(INFO_LVL, "REPORT", "Progress reportado: " + message);
    } else {
        Log(ERROR_LVL, "REPORT", StringFormat("Progress FALLÓ: %s (HTTP %d) — Response: %s", response.message, response.httpCode, TruncLog(response.data)));
    }
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
    jb.AddString("symbol", TICKER_SYMBOL);
    jb.AddString("execution_time", TimeToString(TimeGMT(), TIME_DATE|TIME_SECONDS));

    string json = jb.Build();
    APIResponse response = SendAPIRequest("POST", url, json);
    if(response.result == API_SUCCESS) {
        Log(INFO_LVL, "REPORT", "Close reportado: " + closeReason);
    } else {
        Log(ERROR_LVL, "REPORT", StringFormat("Close FALLÓ: %s (HTTP %d) — Response: %s", response.message, response.httpCode, TruncLog(response.data)));
    }
}

// ==========================================
// CORRECCIÓN DE PRECIOS (logging reducido a DEBUG)
// ==========================================
double CalculatePriceCorrection(string symbol, string &errorMessage) {
    errorMessage = "";

    if(!ENABLE_PRICE_CORRECTION) {
        Log(DEBUG_LVL, "PRICE_CORR", "Price correction deshabilitado");
        return 1.0;
    }

    string url = BuildAPIUrl("fut_price", 0, symbol);
    Log(DEBUG_LVL, "PRICE_CORR", "Consultando precio futuro: " + url);

    APIResponse response = SendAPIRequest("GET", url);

    if(response.result != API_SUCCESS) {
        errorMessage = "Error obteniendo precio del futuro - HTTP: " + IntegerToString(response.httpCode);
        Log(ERROR_LVL, "PRICE_CORR", errorMessage);
        return 0.0;
    }

    SimpleJSONParser parser(response.data);
    double futurePrice = parser.GetDouble("last_close");
    long epochTime = parser.GetInt("ts_epoch");

    Log(DEBUG_LVL, "PRICE_CORR", StringFormat("Future Price=%.5f, Epoch=%d", futurePrice, epochTime));

    if(futurePrice <= 0 || epochTime <= 0) {
        errorMessage = StringFormat("Datos inválidos del futuro - Price: %.5f, Epoch: %d", futurePrice, epochTime);
        Log(ERROR_LVL, "PRICE_CORR", errorMessage);
        return 0.0;
    }

    datetime targetTime = (datetime)epochTime;
    datetime currentTime = TimeCurrent();
    long maxAgeSeconds = MAX_TIMESTAMP_HOURS * 3600;

    datetime gmtTime = TimeGMT();
    long brokerOffset = currentTime - gmtTime;

    datetime brokerTargetTime = (datetime)(targetTime + brokerOffset);
    long timeDifference = currentTime - brokerTargetTime;

    Log(DEBUG_LVL, "PRICE_CORR", StringFormat("Target UTC=%s, Broker=%s, Diff=%ds, Max=%ds",
        TimeToString(targetTime), TimeToString(brokerTargetTime), timeDifference, maxAgeSeconds));

    if(timeDifference > maxAgeSeconds) {
        errorMessage = StringFormat("Timestamp muy viejo: %ds > %ds", timeDifference, maxAgeSeconds);
        Log(ERROR_LVL, "PRICE_CORR", errorMessage);
        return 0.0;
    }

    int barIndex = iBarShift(Symbol(), PERIOD_M1, brokerTargetTime);

    if(barIndex < 0) {
        errorMessage = "No se encontró vela CFD para tiempo: " + TimeToString(targetTime);
        Log(ERROR_LVL, "PRICE_CORR", errorMessage);
        return 0.0;
    }

    double cfdPrice = iClose(Symbol(), PERIOD_M1, barIndex);

    if(cfdPrice <= 0) {
        errorMessage = "Precio CFD inválido: " + DoubleToString(cfdPrice, 5);
        Log(ERROR_LVL, "PRICE_CORR", errorMessage);
        return 0.0;
    }

    double factor = futurePrice / cfdPrice;
    double deviationPercent = MathAbs((factor - 1.0) * 100.0);

    Log(INFO_LVL, "PRICE_CORR", StringFormat("Future=%.5f, CFD=%.5f, Factor=%.6f, Desviación=%.2f%%",
        futurePrice, cfdPrice, factor, deviationPercent));

    if(deviationPercent > MAX_PRICE_DEVIATION) {
        errorMessage = StringFormat("Desviación muy alta: %.2f%% > %.2f%%", deviationPercent, MAX_PRICE_DEVIATION);
        Log(ERROR_LVL, "PRICE_CORR", errorMessage);
        return 0.0;
    }

    return factor;
}

// ==========================================
// FUNCIONES DE PNL Y VOLUMEN
// ==========================================
double CalculatePNL(double currentPrice, double volume = 0) {
    if(!currentTP.isActive) return 0.0;

    if(volume == 0) volume = currentTP.currentVolume;

    double priceDiff = (currentTP.direction == "LONG") ?
                      (currentPrice - currentTP.entry) :
                      (currentTP.entry - currentPrice);

    SymbolSpecs specs = GetSymbolSpecs();
    return (priceDiff / specs.point) * volume * (specs.tickValue * (specs.point / specs.tickSize));
}

double NormalizeVolume(double volume, SymbolSpecs &specs) {
    if(!specs.isValid || specs.stepVolume <= 0) return volume;

    double normalized = MathRound(volume / specs.stepVolume) * specs.stepVolume;
    normalized = MathMax(specs.minVolume, normalized);
    normalized = MathMin(specs.maxVolume, normalized);

    return normalized;
}

double CalculateVolumeToClose(double percentOfOriginal) {
    double rawVolume = (currentTP.originalVolume * percentOfOriginal) / 100.0;
    SymbolSpecs specs = GetSymbolSpecs();
    return NormalizeVolume(rawVolume, specs);
}

double CalculateVolumeOptimized(double entryPrice, double stopLoss) {
    SymbolSpecs specs = GetSymbolSpecs();
    if(!specs.isValid) return 0.0;

    double balance = AccountInfoDouble(ACCOUNT_BALANCE);
    double riskAmount = balance * (RISK_PERCENT / 100.0);
    double distancePoints = MathAbs(entryPrice - stopLoss) / specs.point;
    double pointValuePerLot = specs.tickValue * (specs.point / specs.tickSize);

    if(pointValuePerLot <= 0 || distancePoints <= 0) return 0.0;

    double volume = riskAmount / (distancePoints * pointValuePerLot);
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
    result.reason = "CLOSED_EXTERNAL";
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
                result.exitLevel = -1;
                result.reason = "CLOSED_STOPLOSS";
                return result;

            case DEAL_REASON_TP:
                result.exitLevel = currentTP.currentLevel;
                result.reason = "CLOSED_COMPLETE";
                return result;

            case DEAL_REASON_CLIENT:
            case DEAL_REASON_MOBILE:
            case DEAL_REASON_WEB:
                Log(INFO_LVL, "MANUAL_CLOSE", StringFormat("Cierre manual detectado! Reason=%s, Price=%.5f, Profit=%.2f",
                    EnumToString(dealReason), result.dealPrice, result.dealProfit));
                result.exitLevel = currentTP.currentLevel;
                result.reason = "CLOSED_MANUAL";
                return result;

            case DEAL_REASON_EXPERT:
                result.exitLevel = currentTP.currentLevel;
                result.reason = "CLOSED_EXTERNAL";
                return result;

            default:
                Log(INFO_LVL, "HISTORY", StringFormat("Cierre por razón: %s(%d)", EnumToString(dealReason), dealReason));
                result.exitLevel = currentTP.currentLevel;
                result.reason = "CLOSED_EXTERNAL";
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
        int exitLevel = (reason == "CLOSED_CODE_STOP") ? -1 : currentTP.currentLevel;

        ReportClose(currentTP.signalId, exitLevel, reason, currentPrice, finalPnl);
        Log(INFO_LVL, "CODE_CLOSE", "Posición cerrada por código: " + reason);
        InitTPState();
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
// EVENTOS PRINCIPALES
// ==========================================
void OnDeinit(const int reason) {
    EventKillTimer();
    Log(INFO_LVL, "SYSTEM", "EA desactivado");
}

void OnTimer() {
    if(ENABLE_TIME_FILTER && !IsWithinTradingHours()) return;

    if(currentTP.isActive || currentTP.ticket > 0) return;

    CheckForSignals();
}

void OnTick() {
    if(!currentTP.isActive && currentTP.ticket > 0) {
        CheckPendingOrderExecution();
        return;
    }

    if(!currentTP.isActive) return;

    double currentPrice = GetCurrentPrice(currentTP.direction);

    // Verificar si la posición sigue existiendo
    if(!position.SelectByTicket(currentTP.ticket)) {
        // Posición desaparecio - analizar historial para determinar causa
        HistoryCloseResult closeResult = GetCloseReasonFromHistory(currentTP.positionID);

        // Usar precio del deal si disponible, sino precio actual
        double reportPrice = closeResult.hasDealData ? closeResult.dealPrice : currentPrice;

        // Usar profit del deal si disponible, sino calcular
        double finalPnl = closeResult.hasDealData ? closeResult.dealProfit : CalculatePNL(currentPrice);

        Log(INFO_LVL, "POSITION", StringFormat("Posición cerrada: %s (Exit Level: %d, Price: %.5f, PNL: %.2f, FromDeal: %s)",
            closeResult.reason, closeResult.exitLevel, reportPrice, finalPnl, closeResult.hasDealData ? "YES" : "NO"));

        ReportClose(currentTP.signalId, closeResult.exitLevel, closeResult.reason, reportPrice, finalPnl);
        InitTPState();
        return;
    }

    // Actualizar volumen actual
    currentTP.currentVolume = position.Volume();

    // Verificar stops por código si está habilitado
    if(ENABLE_CODE_STOP) {
        if(CheckCodeStopLoss()) {
            ClosePositionByCode("CLOSED_CODE_STOP");
            return;
        }

        if(CheckSafetyStop()) {
            ClosePositionByCode("CLOSED_SAFETY_STOP");
            return;
        }
    }

    // Gestionar TPs
    ManageTPs(currentPrice);
}

// ==========================================
// GESTIÓN DE TPs (unificada - TP5 ya no es caso especial)
// ==========================================
bool CheckAndExecuteTP(int tpLevel, double tpPrice, double tpPercent, double currentPrice, bool closeAll = false) {
    if(tpPrice <= 0.0) {
        Log(DEBUG_LVL, "TP_SKIP", StringFormat("TP%d saltado: precio no válido (%.5f)", tpLevel, tpPrice));
        return false;
    }

    bool isLong = (currentTP.direction == "LONG");
    bool priceReached = (isLong && currentPrice >= tpPrice) || (!isLong && currentPrice <= tpPrice);

    if(!currentTP.levelFlags[tpLevel] && priceReached) {
        currentTP.currentLevel = MathMax(currentTP.currentLevel, tpLevel);

        bool hasVolumeToClose = closeAll || (tpPercent > 0);
        bool shouldActivateBE = (BE_LEVEL == tpLevel && !currentTP.slMovedToBE);

        Log(INFO_LVL, "TP_HIT", StringFormat("TP%d alcanzado! Precio=%.5f, Target=%.5f, %s, BE=%s",
            tpLevel, currentPrice, tpPrice,
            (closeAll ? "Cerrando TODO" : StringFormat("Volumen=%.2f%%", tpPercent)),
            (shouldActivateBE ? "SÍ" : "NO")));

        if(hasVolumeToClose) {
            double volumeToClose;
            if(closeAll) {
                volumeToClose = currentTP.currentVolume;
            } else {
                double rawVolume = (currentTP.originalVolume * tpPercent) / 100.0;
                volumeToClose = CalculateVolumeToClose(tpPercent);

                if(rawVolume != volumeToClose) {
                    SymbolSpecs specs = GetSymbolSpecs();
                    Log(INFO_LVL, "VOLUME_NORM", StringFormat("TP%d: Volumen calculado=%.3f → normalizado=%.2f (step=%.2f)",
                        tpLevel, rawVolume, volumeToClose, specs.stepVolume));
                }
            }
            ClosePartialPosition(volumeToClose);
        }

        if(shouldActivateBE) {
            SetBreakeven();
        }

        currentTP.levelFlags[tpLevel] = true;
        return true;
    }
    return false;
}

void ManageTPs(double currentPrice) {
    CheckAndExecuteTP(1, currentTP.tp1, TP1_PERCENT, currentPrice);
    CheckAndExecuteTP(2, currentTP.tp2, TP2_PERCENT, currentPrice);
    CheckAndExecuteTP(3, currentTP.tp3, TP3_PERCENT, currentPrice);
    CheckAndExecuteTP(4, currentTP.tp4, TP4_PERCENT, currentPrice);

    // TP5: cierra todo el volumen restante
    if(currentTP.currentVolume > 0) {
        CheckAndExecuteTP(5, currentTP.tp5, TP5_PERCENT, currentPrice, true);
    }
}

// ==========================================
// BREAKEVEN
// ==========================================
void SetBreakeven() {
    if(position.SelectByTicket(currentTP.ticket)) {
        double newSL = currentTP.entry;
        if(trade.PositionModify(position.Ticket(), newSL, position.TakeProfit())) {
            currentTP.slMovedToBE = true;
            currentTP.currentSL = newSL;

            Log(INFO_LVL, "BE", StringFormat("BE activado en TP%d: SL %.5f → %.5f, Vol restante=%.2f",
                currentTP.currentLevel, currentTP.originalSL, newSL, currentTP.currentVolume));

            double currentPrice = GetCurrentPrice(currentTP.direction);

            ReportProgress(currentTP.signalId, currentTP.currentLevel, currentTP.closedPercent,
                          currentTP.currentVolume, currentPrice, 0.0,
                          "Stop Loss movido a Breakeven", false, newSL);
        }
    }
}

// ==========================================
// CIERRE PARCIAL
// ==========================================
bool ClosePartialPosition(double volume) {
    if(!position.SelectByTicket(currentTP.ticket)) return false;

    SymbolSpecs specs = GetSymbolSpecs();

    if(volume > position.Volume()) {
        volume = position.Volume();
    }

    double currentPrice = GetCurrentPrice(currentTP.direction);
    double closedPnl = CalculatePNL(currentPrice, volume);

    if(trade.PositionClosePartial(position.Ticket(), volume)) {
        currentTP.totalClosedVolume += volume;
        currentTP.closedPercent = (currentTP.totalClosedVolume / currentTP.originalVolume) * 100.0;
        currentTP.currentVolume = position.Volume() - volume;

        ReportProgress(currentTP.signalId, currentTP.currentLevel, currentTP.closedPercent,
                      currentTP.currentVolume, currentPrice, closedPnl, "TP parcial");

        Log(INFO_LVL, "PARTIAL_CLOSE", StringFormat("TP%d: Cerrado %.2f lots (%.1f%%) a %.5f, PNL=%.2f, Restante=%.2f",
            currentTP.currentLevel, volume, (volume/currentTP.originalVolume)*100, currentPrice, closedPnl, currentTP.currentVolume));

        if(currentTP.currentVolume <= specs.minVolume) {
            ReportClose(currentTP.signalId, currentTP.currentLevel, "CLOSED_COMPLETE", currentPrice, 0.0);
            InitTPState();
        }
        return true;
    }
    return false;
}

// NUEVO: Reportar ejecución completa cuando pending order se convierte en posición
void ReportPendingExecuted(int userSignalId, ulong ticket, double entryPrice, 
                           double stopLoss, double volume, string direction,
                           ENUM_ORDER_TYPE orderType) {
    
    string url = BuildAPIUrl("progress", userSignalId);
    
    JsonBuilder jb;
    jb.AddBool("success", true);
    jb.AddBool("now_open", true);
    jb.AddString("order_type", EnumToString(orderType));
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
    jb.AddString("symbol", TICKER_SYMBOL);
    jb.AddString("execution_time", TimeToString(TimeGMT(), TIME_DATE|TIME_SECONDS));
    
    string json = jb.Build();
    APIResponse response = SendAPIRequest("POST", url, json);
    if(response.result == API_SUCCESS) {
        Log(INFO_LVL, "REPORT", "Pending execution reportada exitosamente con datos completos");
    } else {
        Log(ERROR_LVL, "REPORT", StringFormat("Pending execution FALLÓ: %s (HTTP %d)", response.message, response.httpCode));
    }
}

// ==========================================
// PENDING ORDER MONITORING
// ==========================================
void CheckPendingOrderExecution() {
    if(FindOwnPosition()) {
        Log(INFO_LVL, "PENDING", "Orden pendiente ejecutada");

        currentTP.isActive = true;
        currentTP.ticket = position.Ticket();
        currentTP.positionID = position.Identifier();
        currentTP.currentVolume = position.Volume();
        currentTP.entry = position.PriceOpen();
        currentTP.currentLevel = 0;
        currentTP.currentSL = position.StopLoss();

        double currentPrice = GetCurrentPrice(currentTP.direction);
        double pnl = CalculatePNL(currentPrice);
        
        // Reportar ejecución completa de la pending order
        ReportPendingExecuted(currentTP.signalId, currentTP.ticket, currentTP.entry, 
                              currentTP.currentSL, currentTP.currentVolume, 
                              currentTP.direction, position.OrderType());
        return;
    }

    if(!OrderSelect(currentTP.ticket)) {
        Log(WARNING_LVL, "PENDING", "Orden pendiente cancelada");
        ReportClose(currentTP.signalId, -999, "ORDER_CANCELLED", 0, 0);
        InitTPState();
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

    if(sl1 <= 0) {
        Log(ERROR_LVL, "SIGNAL", StringFormat("Señal rechazada ID=%d: SL1 inválido", userSignalId));
        ReportClose(userSignalId, -998, "INVALID_STOPLOSS", 0, 0);
        return;
    }

    if(tp1 <= 0 || tp2 <= 0 || tp3 <= 0 || tp4 <= 0 || tp5 <= 0) {
        Log(ERROR_LVL, "SIGNAL", StringFormat("Señal rechazada ID=%d: TPs incompletos. Faltan: %s%s%s%s%s",
            userSignalId,
            (tp1 <= 0 ? "TP1 " : ""),
            (tp2 <= 0 ? "TP2 " : ""),
            (tp3 <= 0 ? "TP3 " : ""),
            (tp4 <= 0 ? "TP4 " : ""),
            (tp5 <= 0 ? "TP5 " : "")
        ));
        ReportClose(userSignalId, -998, "INVALID_TPS", 0, 0);
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
        ReportClose(userSignalId, -999, "PRICE_CORRECTION_ERROR", 0, 0);
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

// Subfuncion: Validar spread. Retorna false si muy alto.
bool ValidateSpread(int userSignalId) {
    int spreadPoints = (int)SymbolInfoInteger(currentSymbol, SYMBOL_SPREAD);
    if(spreadPoints > MAX_SPREAD) {
        SymbolSpecs specs = GetSymbolSpecs();
        Log(ERROR_LVL, "SPREAD", StringFormat("Spread demasiado alto: %d points > %d max | Symbol=%s",
                spreadPoints, (int)MAX_SPREAD, currentSymbol));
        ReportClose(userSignalId, -999, "SPREAD_TOO_HIGH", 0, 0);
        return false;
    }
    return true;
}

// Subfuncion: Determinar tipo de orden basado en precio actual vs entry
ENUM_ORDER_TYPE DetermineOrderType(string opType, double entryPrice, double &currentPrice, string &orderDecision) {
    currentPrice = (opType == "LONG") ?
                   SymbolInfoDouble(currentSymbol, SYMBOL_ASK) :
                   SymbolInfoDouble(currentSymbol, SYMBOL_BID);

    double point = SymbolInfoDouble(currentSymbol, SYMBOL_POINT);
    double priceDifference = MathAbs(entryPrice - currentPrice);
    double differencePoints = priceDifference / point;

    // Calcular tolerancia
    double tolerance;
    string toleranceMode;

    if(PRICE_TOLERANCE_PERCENT > 0.0) {
        tolerance = entryPrice * (PRICE_TOLERANCE_PERCENT / 100.0);
        double tolerancePoints = tolerance / point;
        toleranceMode = StringFormat("%.3f%% (%.1f pts)", PRICE_TOLERANCE_PERCENT, tolerancePoints);
    } else {
        tolerance = PRICE_TOLERANCE_POINTS * point;
        toleranceMode = StringFormat("%d pts", PRICE_TOLERANCE_POINTS);
    }

    ENUM_ORDER_TYPE orderType;

    if(opType == "LONG") {
        if(priceDifference <= tolerance)     { orderType = ORDER_TYPE_BUY;       orderDecision = "MARKET"; }
        else if(entryPrice < currentPrice)   { orderType = ORDER_TYPE_BUY_LIMIT; orderDecision = "LIMIT"; }
        else                                 { orderType = ORDER_TYPE_BUY_STOP;  orderDecision = "STOP"; }
    } else {
        if(priceDifference <= tolerance)     { orderType = ORDER_TYPE_SELL;       orderDecision = "MARKET"; }
        else if(entryPrice > currentPrice)   { orderType = ORDER_TYPE_SELL_LIMIT; orderDecision = "LIMIT"; }
        else                                 { orderType = ORDER_TYPE_SELL_STOP;  orderDecision = "STOP"; }
    }

    Log(INFO_LVL, "ORDER_DECISION", StringFormat("Actual=%.5f, Entry=%.5f, Diff=%.1f pts, Tolerancia=%s, Decisión=%s",
        currentPrice, entryPrice, differencePoints, toleranceMode, orderDecision));

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

    // 2. Validar spread
    if(!ValidateSpread(userSignalId))
        return false;

    // 3. Calcular volumen
    double calculatedVolume = CalculateVolumeOptimized(entryPrice, stopLoss);
    if(calculatedVolume <= 0) {
        double balance = AccountInfoDouble(ACCOUNT_BALANCE);
        Log(ERROR_LVL, "VOLUME", StringFormat("Volumen=0: Balance=%.2f, Risk%%=%.1f, Entry=%.5f, SL=%.5f",
            balance, RISK_PERCENT, entryPrice, stopLoss));
        ReportClose(userSignalId, -999, "VOLUME_ERROR", 0, 0);
        return false;
    }

    // 4. Determinar tipo de orden
    double currentPrice;
    string orderDecision;
    ENUM_ORDER_TYPE orderType = DetermineOrderType(opType, entryPrice, currentPrice, orderDecision);

    // 5. Configurar SL
    double slDistance = MathAbs(entryPrice - stopLoss);
    double effectiveDistance = ENABLE_CODE_STOP ? (slDistance * SAFETY_FACTOR) : slDistance;
    double orderStopLoss = (opType == "LONG") ? (entryPrice - effectiveDistance) : (entryPrice + effectiveDistance);

    Log(INFO_LVL, "SL_CALC", StringFormat("Original=%.5f, Distance=%.5f, Factor=%.2f, Final=%.5f",
            stopLoss, slDistance, ENABLE_CODE_STOP ? SAFETY_FACTOR : 1.0, orderStopLoss));

    // 6. Ejecutar orden
    bool success = false;
    ulong ticket = 0;
    bool isMarketOrder = (orderType == ORDER_TYPE_BUY || orderType == ORDER_TYPE_SELL);

    if(isMarketOrder) {
        success = trade.PositionOpen(currentSymbol, orderType, calculatedVolume, 0, orderStopLoss, 0, TRADE_COMMENT);
    } else {
        success = trade.OrderOpen(currentSymbol, orderType, calculatedVolume, 0, entryPrice, orderStopLoss, 0,
                                 ORDER_TIME_DAY, 0, TRADE_COMMENT);
    }
    ticket = trade.ResultOrder();

    if(success && ticket > 0) {
        // Para market orders, obtener precio real y position ID
        double realEntryPrice = entryPrice;
        ulong positionID = 0;

        if(isMarketOrder && FindOwnPosition()) {
            realEntryPrice = position.PriceOpen();
            positionID = position.Identifier();
            if(realEntryPrice != entryPrice) {
                Log(INFO_LVL, "REAL_ENTRY", StringFormat("Precio real: %.5f (vs señal: %.5f)", realEntryPrice, entryPrice));
            }
            entryPrice = realEntryPrice;
        }

        // Setup state (ya con positionID resuelto)
        SetupTPState(userSignalId, opType, entryPrice, stopLoss, calculatedVolume,
                     tp1, tp2, tp3, tp4, tp5, ticket, isMarketOrder, positionID);

        Log(INFO_LVL, "TRADE_OPEN", StringFormat("%s %s, Ticket=%d, PosID=%d, Vol=%.2f, Entry=%.5f, Corrección=%.4f",
            (isMarketOrder ? "MARKET" : "PENDING"), opType, ticket, positionID, calculatedVolume, entryPrice, correctionFactor));

        ReportOpen(userSignalId, isMarketOrder, orderType, entryPrice, orderStopLoss, calculatedVolume, ticket,
                   opType, originalEntry, originalSL1, originalSL2,
                   originalTP1, originalTP2, originalTP3, originalTP4, originalTP5,
                   entryPrice, orderStopLoss, originalSL2, tp1, tp2, tp3, tp4, tp5);
        return true;
    } else {
        uint retcode = trade.ResultRetcode();
        string retdesc = trade.ResultRetcodeDescription();
        Log(ERROR_LVL, "TRADE", StringFormat("Orden falló: %d (%s), Tipo=%s, Vol=%.2f, Entry=%.5f, SL=%.5f",
            retcode, retdesc, EnumToString(orderType), calculatedVolume, entryPrice, orderStopLoss));
        ReportClose(userSignalId, -999, "EXECUTION_FAILED", 0, 0);
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

    ArrayInitialize(currentTP.levelFlags, false);

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
}
