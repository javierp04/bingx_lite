#property copyright "TelegramSignals"
#property version   "8.04"
#property description "EA corregido - PNL calculation, stop loss detection y breakeven arreglados"

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
input string    API_URL = "http://bxlite.local/api/";
input int       USER_ID = 1;

input group "=== Trading Settings ==="
input string    TICKER_SYMBOL = "EURUSD";
input double    RISK_PERCENT = 2.0;
input int       POLL_INTERVAL = 30;
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

// ESTRUCTURAS SIMPLIFICADAS
struct SymbolSpecs {
    double point, tickSize, tickValue, contractSize;
    double minVolume, maxVolume, stepVolume;
    int digits;
    bool isValid;
};

struct OptimizedTPState {
    // CORE
    bool isActive;
    int signalId;
    ulong ticket;
    ulong positionID;           // NUEVO: Position ID real para historial
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
    
    // PRICES (directo, sin struct anidado)
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


// VARIABLES GLOBALES
CTrade trade;
CPositionInfo position;
OptimizedTPState currentTP;
int currentUserSignalId = 0;
string currentSymbol;
double backupStopLoss = 0.0;  // SL2 del JSON (backup, no usado actualmente)

// Cache para performance
static SymbolSpecs cachedSpecs;
static string cachedSymbol = "";
static string baseUrls[4];

// CLASE JSON SIMPLIFICADA
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
    
    double GetArrayDouble(string arrayKey, int index, double defaultValue = 0.0) {
        string search = "\"" + arrayKey + "\":[";
        int pos = StringFind(json, search);
        if(pos == -1) return defaultValue;

        pos += StringLen(search);

        // Saltar índices anteriores
        for(int i = 0; i < index; i++) {
            pos = StringFind(json, ",", pos);
            if(pos == -1) return defaultValue;
            pos++;
        }

        // Trimear espacios en blanco al inicio
        while(pos < StringLen(json) && (StringGetCharacter(json, pos) == ' ' || StringGetCharacter(json, pos) == '\t' || StringGetCharacter(json, pos) == '\n' || StringGetCharacter(json, pos) == '\r')) {
            pos++;
        }

        // Buscar fin del valor (coma o cierre de array)
        int endPos = StringFind(json, ",", pos);
        if(endPos == -1) {
            endPos = StringFind(json, "]", pos);
        }

        if(endPos == -1) return defaultValue;

        // Extraer valor y trimear espacios al final
        string value = StringSubstr(json, pos, endPos - pos);

        // Trimear espacios del valor extraído
        StringReplace(value, " ", "");
        StringReplace(value, "\t", "");
        StringReplace(value, "\n", "");
        StringReplace(value, "\r", "");

        return ValidateNumber(value) ? StringToDouble(value) : defaultValue;
    }
};

// LOGGING SIMPLIFICADO
void Log(LogLevel level, string category, string message) {    
    
    string prefix = "";
    switch(level) {
        case ERROR_LVL: prefix = "[ERROR]"; break;
        case WARNING_LVL: prefix = "[WARN]"; break;
        case INFO_LVL: prefix = "[INFO]"; break;
        case DEBUG_LVL: prefix = "[DEBUG]"; break;
    }
    
    Print(prefix + " [" + category + "] " + message);
}

// INICIALIZACIÓN
int OnInit() {
    currentSymbol = Symbol();
    InitTPState();
    SetupBaseUrls();
    
    trade.SetExpertMagicNumber(MAGIC_NUMBER);
    trade.SetDeviationInPoints(50);
    
    if(!ValidateAllInputs()) return INIT_PARAMETERS_INCORRECT;
    if(!ValidateSymbol()) return INIT_FAILED;
    
    if(!EventSetTimer(POLL_INTERVAL)) {
        Log(ERROR_LVL, "INIT", "Error configurando timer");
        return INIT_FAILED;
    }
    
    LogInitialization();

    if(!ENABLE_TIME_FILTER || IsWithinTradingHours()) {
        CheckForSignals();
    }

    return INIT_SUCCEEDED;
}

void InitTPState() {
    currentTP.isActive = false;
    currentTP.signalId = 0;
    currentTP.ticket = 0;
    currentTP.positionID = 0;              // NUEVO: inicializar Position ID
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
    baseUrls[0] = API_URL + "signals/";
    baseUrls[1] = API_URL + "signals/";
    baseUrls[2] = API_URL + "fut_price/";
}

bool ValidateAllInputs() {
    if(USER_ID <= 0 || RISK_PERCENT <= 0 || RISK_PERCENT > 10 || POLL_INTERVAL < 10) return false;
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
   Print("EA Signals v8.04 | User: ", USER_ID, " | Symbol: ", currentSymbol, " | BE Level: ", BE_LEVEL);
   Log(INFO_LVL, "INIT", "Stop management: " + (ENABLE_CODE_STOP ? "CODE" : "MT5"));    
}

// ESPECIFICACIONES DE SÍMBOLO CON CACHE
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
    cachedSpecs.digits = (int)SymbolInfoInteger(currentSymbol, SYMBOL_DIGITS);
    
    cachedSpecs.isValid = (cachedSpecs.point > 0 && cachedSpecs.contractSize > 0 && 
                          cachedSpecs.minVolume > 0 && cachedSpecs.stepVolume > 0);
    
    if(cachedSpecs.isValid) cachedSymbol = currentSymbol;
    
    return cachedSpecs;
}


// FUNCIONES DE API
string BuildAPIUrl(string endpoint, int id = 0, string param = "") {
    if(endpoint == "get_signals") {
        return API_URL + "signals/" + IntegerToString(USER_ID) + "/" + param;
    }
    else if(endpoint == "open") {
        return baseUrls[1] + IntegerToString(id) + "/open";
    }
    else if(endpoint == "progress") {
        return baseUrls[1] + IntegerToString(id) + "/progress";
    }
    else if(endpoint == "close") {
        return baseUrls[1] + IntegerToString(id) + "/close";
    } 
    else if(endpoint == "fut_price") {
        return baseUrls[2] + param;
    }
    
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
    //Log(INFO_LVL, "API", StringFormat("HTTP %d", httpCode));

    if(httpCode == 200) {
        response.result = API_SUCCESS;
        response.message = "OK";
    } else {
        response.result = API_HTTP_ERROR;
        response.message = "HTTP " + IntegerToString(httpCode);
    }

    return response;
}

// FUNCIONES DE REPORTE
void ReportOpen(int userSignalId, bool isMarketOrder, ENUM_ORDER_TYPE orderType,
                double entryPrice, double stopLoss, double volume, ulong ticket,
                string opType = "", double signalEntry = 0, double signalSL1 = 0, double signalSL2 = 0,
                double signalTP1 = 0, double signalTP2 = 0, double signalTP3 = 0,
                double signalTP4 = 0, double signalTP5 = 0) {

    string url = BuildAPIUrl("open", userSignalId);

    string json = "{";
    json += "\"success\":true,";
    json += "\"trade_id\":\"" + IntegerToString(ticket) + "\",";
    json += "\"order_type\":\"" + EnumToString(orderType) + "\",";

    if(isMarketOrder) {
        json += "\"real_entry_price\":" + DoubleToString(entryPrice, 5) + ",";
        json += "\"real_stop_loss\":" + DoubleToString(stopLoss, 5) + ",";
        json += "\"real_volume\":" + DoubleToString(volume, 2) + ",";
    }

    // NUEVO: Agregar signal_data con precios originales (pre-corrección)
    if(signalEntry > 0 && opType != "") {
        json += "\"signal_data\":{";
        json += "\"op_type\":\"" + opType + "\",";
        json += "\"entry\":" + DoubleToString(signalEntry, 5) + ",";
        json += "\"stoploss\":[" + DoubleToString(signalSL1, 5) + "," + DoubleToString(signalSL2, 5) + "],";
        json += "\"tps\":[" + DoubleToString(signalTP1, 5) + "," + DoubleToString(signalTP2, 5) + ","
                + DoubleToString(signalTP3, 5) + "," + DoubleToString(signalTP4, 5) + ","
                + DoubleToString(signalTP5, 5) + "]";
        json += "},";
    }

    json += "\"symbol\":\"" + TICKER_SYMBOL + "\",";
    json += "\"execution_time\":\"" + TimeToString(TimeGMT(), TIME_DATE|TIME_SECONDS) + "\"";
    json += "}";

    APIResponse response = SendAPIRequest("POST", url, json);
    if(response.result == API_SUCCESS) {
        Log(INFO_LVL, "REPORT", "Open reportado exitosamente");
    }
}

void ReportProgress(int userSignalId, int level, double volumeClosedPercent, 
                    double remainingVolume, double currentPrice, double pnl, 
                    string message, bool nowOpen = false, double newStopLoss = 0.0) {
    
    string url = BuildAPIUrl("progress", userSignalId);
    
    string json = "{";
    json += "\"success\":true,";
    json += "\"current_level\":" + IntegerToString(level) + ",";
    json += "\"volume_closed_percent\":" + DoubleToString(volumeClosedPercent, 2) + ",";
    json += "\"remaining_volume\":" + DoubleToString(remainingVolume, 2) + ",";
    json += "\"gross_pnl\":" + DoubleToString(pnl, 2) + ",";
    json += "\"last_price\":" + DoubleToString(currentPrice, 5) + ",";
    
    if(nowOpen) {
        json += "\"now_open\":true,";
        json += "\"real_entry_price\":" + DoubleToString(currentTP.entry, 5) + ",";
    }
    
    if(newStopLoss > 0.0) {
        json += "\"new_stop_loss\":" + DoubleToString(newStopLoss, 5) + ",";
    }
    
    json += "\"message\":\"" + message + "\",";
    json += "\"symbol\":\"" + TICKER_SYMBOL + "\",";
    json += "\"execution_time\":\"" + TimeToString(TimeGMT(), TIME_DATE|TIME_SECONDS) + "\"";
    json += "}";
    
    APIResponse response = SendAPIRequest("POST", url, json);
    if(response.result == API_SUCCESS) {
        Log(INFO_LVL, "REPORT", "Progress reportado: " + message);
    }
}

void ReportClose(int userSignalId, int exitLevel, string closeReason, 
                 double finalPrice, double finalPnl) {
    
    string url = BuildAPIUrl("close", userSignalId);
    
    string json = "{";
    json += "\"success\":true,";
    json += "\"exit_level\":" + IntegerToString(exitLevel) + ",";
    json += "\"close_reason\":\"" + closeReason + "\",";
    json += "\"gross_pnl\":" + DoubleToString(finalPnl, 2) + ",";
    json += "\"last_price\":" + DoubleToString(finalPrice, 5) + ",";
    json += "\"symbol\":\"" + TICKER_SYMBOL + "\",";
    json += "\"execution_time\":\"" + TimeToString(TimeGMT(), TIME_DATE|TIME_SECONDS) + "\"";
    json += "}";
    
    APIResponse response = SendAPIRequest("POST", url, json);
    if(response.result == API_SUCCESS) {
        Log(INFO_LVL, "REPORT", "Close reportado: " + closeReason);
    }
}

// CORRECCIÓN DE PRECIOS
double CalculatePriceCorrection(string symbol, string &errorMessage) {
    errorMessage = "";
    
    if(!ENABLE_PRICE_CORRECTION) {
        Log(INFO_LVL, "PRICE_CORR", "Price correction deshabilitado");
        return 1.0;
    }
    
    string url = BuildAPIUrl("fut_price", 0, symbol);
    Log(INFO_LVL, "PRICE_CORR", "Consultando precio futuro para: " + symbol);
    Log(INFO_LVL, "PRICE_CORR", "URL: " + url);
    
    APIResponse response = SendAPIRequest("GET", url);
    
    Log(INFO_LVL, "PRICE_CORR", StringFormat("API Response: HTTP %d, Result: %d", response.httpCode, response.result));
    Log(INFO_LVL, "PRICE_CORR", "Response data: " + response.data);
    
    if(response.result != API_SUCCESS) {
        errorMessage = "Error obteniendo precio del futuro - HTTP: " + IntegerToString(response.httpCode);
        Log(ERROR_LVL, "PRICE_CORR", errorMessage);
        return 0.0;
    }
    
    SimpleJSONParser parser(response.data);
    double futurePrice = parser.GetDouble("last_close");
    long epochTime = parser.GetInt("ts_epoch");
    
    Log(INFO_LVL, "PRICE_CORR", StringFormat("Datos parseados: Future Price=%.5f, Epoch=%d", futurePrice, epochTime));
    
    if(futurePrice <= 0 || epochTime <= 0) {
        errorMessage = StringFormat("Datos inválidos del futuro - Price: %.5f, Epoch: %d", futurePrice, epochTime);
        Log(ERROR_LVL, "PRICE_CORR", errorMessage);
        return 0.0;
    }
    
    datetime targetTime = (datetime)epochTime;
    datetime currentTime = TimeCurrent();    
    long maxAgeSeconds = MAX_TIMESTAMP_HOURS * 3600;
    
    // Lógica híbrida para timezone offset
    datetime gmtTime = TimeGMT();
    long brokerOffset = currentTime - gmtTime;
    
    
    datetime brokerTargetTime = (datetime)(targetTime + brokerOffset);
    long timeDifference = currentTime - brokerTargetTime;
    
    Log(INFO_LVL, "PRICE_CORR", StringFormat("Tiempos: Target UTC=%s, Broker=%s, Diff=%ds, Max=%ds", 
        TimeToString(targetTime), TimeToString(brokerTargetTime), timeDifference, maxAgeSeconds));
    
    if(timeDifference > maxAgeSeconds) {
        errorMessage = StringFormat("Timestamp muy viejo: %ds > %ds", timeDifference, maxAgeSeconds);
        Log(ERROR_LVL, "PRICE_CORR", errorMessage);
        return 0.0;
    }
    
    int barIndex = iBarShift(Symbol(), PERIOD_M1, brokerTargetTime);  // Usar tiempo del broker
    Log(INFO_LVL, "PRICE_CORR", StringFormat("Búsqueda vela CFD: Symbol=%s, BrokerTime=%s, BarIndex=%d", 
        Symbol(), TimeToString(brokerTargetTime), barIndex));
    
    if(barIndex < 0) {
        errorMessage = "No se encontró vela CFD para tiempo: " + TimeToString(targetTime);
        Log(ERROR_LVL, "PRICE_CORR", errorMessage);
        return 0.0;
    }
    
    double cfdPrice = iClose(Symbol(), PERIOD_M1, barIndex);
    Log(INFO_LVL, "PRICE_CORR", StringFormat("Precio CFD obtenido: %.5f (bar %d)", cfdPrice, barIndex));
    
    if(cfdPrice <= 0) {
        errorMessage = "Precio CFD inválido: " + DoubleToString(cfdPrice, 5);
        Log(ERROR_LVL, "PRICE_CORR", errorMessage);
        return 0.0;
    }
    
    double factor = futurePrice / cfdPrice;
    double deviationPercent = MathAbs((factor - 1.0) * 100.0);
    
    Log(INFO_LVL, "PRICE_CORR", StringFormat("Cálculo final: Future=%.5f, CFD=%.5f, Factor=%.6f, Desviación=%.2f%%", 
        futurePrice, cfdPrice, factor, deviationPercent));
    
    if(deviationPercent > MAX_PRICE_DEVIATION) {
        errorMessage = StringFormat("Desviación muy alta: %.2f%% > %.2f%%", deviationPercent, MAX_PRICE_DEVIATION);
        Log(ERROR_LVL, "PRICE_CORR", errorMessage);
        return 0.0;
    }

    return factor;
}

// FUNCIONES UNIFICADAS DE PNL Y VOLUMEN
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

    // Redondear al step más cercano
    double normalized = MathRound(volume / specs.stepVolume) * specs.stepVolume;

    // Validar límites
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

// NUEVA FUNCIÓN: Obtener razón de cierre desde historial
string GetCloseReasonFromHistory(ulong positionID, int &exitLevel) {
    // Cargar deals de esta posición específica
    if(!HistorySelectByPosition(positionID)) {
        Log(WARNING_LVL, "HISTORY", "No se pudo cargar historial para Position ID: " + IntegerToString(positionID));
        exitLevel = currentTP.currentLevel;
        return "CLOSED_EXTERNAL";
    }
    
    int totalDeals = HistoryDealsTotal();
    if(totalDeals == 0) {
        Log(WARNING_LVL, "HISTORY", "No se encontraron deals para Position ID: " + IntegerToString(positionID));
        exitLevel = currentTP.currentLevel;
        return "CLOSED_EXTERNAL";
    }
    
    // Verificar últimos 3 deals (o menos si hay menos deals)
    int dealsToCheck = MathMin(3, totalDeals);
    
    for(int i = totalDeals - 1; i >= totalDeals - dealsToCheck; i--) {
        ulong dealTicket = HistoryDealGetTicket(i);
        if(dealTicket == 0) continue;
        
        // Verificar que es un deal de salida
        ENUM_DEAL_ENTRY dealEntry = (ENUM_DEAL_ENTRY)HistoryDealGetInteger(dealTicket, DEAL_ENTRY);
        if(dealEntry != DEAL_ENTRY_OUT) continue;
        
        // Verificar que es el tipo correcto (opuesto a la posición)
        ENUM_DEAL_TYPE dealType = (ENUM_DEAL_TYPE)HistoryDealGetInteger(dealTicket, DEAL_TYPE);
        bool isCorrectType = false;
        
        if(currentTP.direction == "LONG" && dealType == DEAL_TYPE_SELL) isCorrectType = true;
        if(currentTP.direction == "SHORT" && dealType == DEAL_TYPE_BUY) isCorrectType = true;
        
        if(!isCorrectType) continue;
        
        // Obtener razón del deal
        ENUM_DEAL_REASON dealReason = (ENUM_DEAL_REASON)HistoryDealGetInteger(dealTicket, DEAL_REASON);
        double dealPrice = HistoryDealGetDouble(dealTicket, DEAL_PRICE);
        
        Log(INFO_LVL, "HISTORY", StringFormat("Deal encontrado: Ticket=%d, Reason=%d, Price=%.5f", dealTicket, dealReason, dealPrice));
        
        // Mapear razón a string y determinar exit level con logs específicos
        switch(dealReason) {
            case DEAL_REASON_SL:
                Log(INFO_LVL, "SL_DETECTED", StringFormat("Stop Loss por MT5! Deal=%d, SL=%.5f, Precio final=%.5f", 
                    dealTicket, currentTP.currentSL, dealPrice));
                exitLevel = -1;
                return "CLOSED_STOPLOSS";
                
            case DEAL_REASON_TP:
                Log(INFO_LVL, "TP_COMPLETE", StringFormat("Take Profit completo! Deal=%d, Precio=%.5f", dealTicket, dealPrice));
                exitLevel = currentTP.currentLevel;
                return "CLOSED_COMPLETE";
                
            case DEAL_REASON_CLIENT:
            case DEAL_REASON_MOBILE:
            case DEAL_REASON_WEB:
                Log(INFO_LVL, "MANUAL_DETECTED", StringFormat("Cierre manual detectado! Deal=%d, Tipo=%d, Precio=%.5f", 
                    dealTicket, dealReason, dealPrice));
                exitLevel = currentTP.currentLevel;
                return "CLOSED_EXTERNAL";
                
            case DEAL_REASON_EXPERT:
                // Expert puede ser nuestro EA o externo
                Log(INFO_LVL, "EXPERT_DETECTED", StringFormat("Cierre por Expert! Deal=%d, Precio=%.5f", dealTicket, dealPrice));
                exitLevel = currentTP.currentLevel;
                return "CLOSED_EXTERNAL";
                
            default:
                Log(INFO_LVL, "OTHER_DETECTED", StringFormat("Cierre por razón %d! Deal=%d, Precio=%.5f", 
                    dealReason, dealTicket, dealPrice));
                exitLevel = currentTP.currentLevel;
                return "CLOSED_EXTERNAL";
        }
    }
    
    // Fallback: no se encontró deal de cierre válido
    Log(WARNING_LVL, "HISTORY", "No se encontró deal de cierre válido en últimos " + IntegerToString(dealsToCheck) + " deals");
    exitLevel = currentTP.currentLevel;
    return "CLOSED_EXTERNAL";
}

// FUNCIONES DE STOP LOSS POR CÓDIGO
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
        // LOG: Stop loss por código detectado
        Log(INFO_LVL, "CODE_SL", StringFormat("Stop Loss por código! Precio barra anterior=%.5f, SL=%.5f", 
            lastClose, currentTP.currentSL));
        return true;
    }
    
    return false;
}

bool CheckSafetyStop() {
    if(!currentTP.isActive) return false;
    
    double currentPrice = (currentTP.direction == "LONG") ? 
                         SymbolInfoDouble(currentSymbol, SYMBOL_BID) : 
                         SymbolInfoDouble(currentSymbol, SYMBOL_ASK);
    
    double currentPnl = CalculatePNL(currentPrice);
    double balance = AccountInfoDouble(ACCOUNT_BALANCE);
    double maxLoss = -(balance * (RISK_PERCENT / 100.0) * SAFETY_FACTOR);
    
    return (currentPnl <= maxLoss);
}

bool ClosePositionByCode(string reason) {
    if(!position.SelectByTicket(currentTP.ticket)) return false;
    
    double currentPrice = (currentTP.direction == "LONG") ? 
                         SymbolInfoDouble(currentSymbol, SYMBOL_BID) : 
                         SymbolInfoDouble(currentSymbol, SYMBOL_ASK);
    
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

// GESTIÓN DE HORARIOS
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

// EVENTOS PRINCIPALES
void OnDeinit(const int reason) {
    EventKillTimer();
    Log(INFO_LVL, "SYSTEM", "EA desactivado");
}

void OnTimer() {
    if(ENABLE_TIME_FILTER && !IsWithinTradingHours()) return;
    CheckForSignals();
}

// ONTICK CORREGIDO - Nueva lógica con historial
void OnTick() {
    if(!currentTP.isActive && currentTP.ticket > 0) {
        CheckPendingOrderExecution();
        return;
    }
    
    if(!currentTP.isActive) return;
    
    double bid = SymbolInfoDouble(currentSymbol, SYMBOL_BID);
    double ask = SymbolInfoDouble(currentSymbol, SYMBOL_ASK);
    double currentPrice = (currentTP.direction == "LONG") ? bid : ask;
    
    // NUEVO: Verificar si la posición sigue existiendo PRIMERO
    if(!position.SelectByTicket(currentTP.ticket)) {
        // Posición no existe - determinar por qué usando historial
        int exitLevel;
        string closeReason = GetCloseReasonFromHistory(currentTP.positionID, exitLevel);
        
        double finalPnl = CalculatePNL(currentPrice);
        ReportClose(currentTP.signalId, exitLevel, closeReason, currentPrice, finalPnl);
        
        Log(INFO_LVL, "POSITION", StringFormat("Posición cerrada: %s (Exit Level: %d)", closeReason, exitLevel));
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

// GESTIÓN DE TPs MEJORADA - Con validación de TPs
bool CheckAndExecuteTP(int tpLevel, double tpPrice, double tpPercent, double currentPrice) {
    // NUEVO: Validar TP configurado
    if(tpPrice <= 0.0) {
        Log(DEBUG_LVL, "TP_SKIP", StringFormat("TP%d saltado: precio no válido (%.5f)", tpLevel, tpPrice));
        return false;
    }
    
    bool isLong = (currentTP.direction == "LONG");
    bool priceReached = (isLong && currentPrice >= tpPrice) || (!isLong && currentPrice <= tpPrice);
    
    if(!currentTP.levelFlags[tpLevel] && priceReached) {
        currentTP.currentLevel = MathMax(currentTP.currentLevel, tpLevel);
        
        // NUEVO: Separar lógica de cierre de volumen vs activación de BE
        bool hasVolumeToClose = (tpPercent > 0);
        bool shouldActivateBE = (BE_LEVEL == tpLevel && !currentTP.slMovedToBE);
        
        // LOG: TP alcanzado
        Log(INFO_LVL, "TP_HIT", StringFormat("TP%d alcanzado! Precio=%.5f, Target=%.5f, Volumen a cerrar=%.2f%%, BE=%s",
            tpLevel, currentPrice, tpPrice, tpPercent, (shouldActivateBE ? "SÍ" : "NO")));

        // Paso 1: Cerrar volumen si aplica
        if(hasVolumeToClose) {
            // Calcular volumen crudo y normalizado
            double rawVolume = (currentTP.originalVolume * tpPercent) / 100.0;
            double volumeToClose = CalculateVolumeToClose(tpPercent);

            // LOG: Volumen calculado vs normalizado
            if(rawVolume != volumeToClose) {
                SymbolSpecs specs = GetSymbolSpecs();
                Log(INFO_LVL, "VOLUME_NORM", StringFormat("TP%d: Volumen calculado=%.3f → normalizado=%.2f (step=%.2f)",
                    tpLevel, rawVolume, volumeToClose, specs.stepVolume));
            }

            ClosePartialPosition(volumeToClose);
        }
        
        // Paso 2: Activar breakeven si aplica (DESPUÉS del cierre parcial)
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
    
    // TP5: Close ALL remaining - NUEVO: Validar TP5 configurado
    if(!currentTP.levelFlags[5] && currentTP.tp5 > 0.0 && currentTP.currentVolume > 0 &&
       (((currentTP.direction == "LONG") && currentPrice >= currentTP.tp5) || 
        ((currentTP.direction == "SHORT") && currentPrice <= currentTP.tp5))) {
        
        currentTP.currentLevel = 5;
        
        // LOG: TP5 alcanzado
        Log(INFO_LVL, "TP_HIT", StringFormat("TP5 alcanzado! Precio=%.5f, Target=%.5f, Cerrando posición completa", 
            currentPrice, currentTP.tp5));
        
        ClosePartialPosition(currentTP.currentVolume);
        currentTP.levelFlags[5] = true;
    }
}

// BREAKEVEN CORREGIDO - Sin reportar PNL
void SetBreakeven() {
    if(position.SelectByTicket(currentTP.ticket)) {
        double newSL = currentTP.entry;
        if(trade.PositionModify(position.Ticket(), newSL, position.TakeProfit())) {
            currentTP.slMovedToBE = true;
            currentTP.currentSL = newSL;
            
            Log(INFO_LVL, "BE", "Stop Loss movido a Breakeven");
            // LOG: Detalles del breakeven
            Log(INFO_LVL, "BE_DETAIL", StringFormat("BE activado en TP%d: SL %.5f → %.5f, Vol restante=%.2f", 
                currentTP.currentLevel, currentTP.originalSL, newSL, currentTP.currentVolume));
            
            double currentPrice = (currentTP.direction == "LONG") ? 
                                 SymbolInfoDouble(currentSymbol, SYMBOL_BID) : 
                                 SymbolInfoDouble(currentSymbol, SYMBOL_ASK);
            
            // CORREGIDO: Reportar BE SIN PNL
            ReportProgress(currentTP.signalId, currentTP.currentLevel, currentTP.closedPercent, 
                          currentTP.currentVolume, currentPrice, 0.0,  // PNL = 0.0 para BE
                          "Stop Loss movido a Breakeven", false, newSL);
        }
    }
}

bool ClosePartialPosition(double volume) {
    if(!position.SelectByTicket(currentTP.ticket)) return false;

    SymbolSpecs specs = GetSymbolSpecs();

    // Validar que el volumen no exceda el volumen actual de la posición
    if(volume > position.Volume()) {
        volume = position.Volume();
    }

    double currentPrice = (currentTP.direction == "LONG") ?
                         SymbolInfoDouble(currentSymbol, SYMBOL_BID) :
                         SymbolInfoDouble(currentSymbol, SYMBOL_ASK);

    // PNL del volumen que se va a cerrar
    double closedPnl = CalculatePNL(currentPrice, volume);
    
    if(trade.PositionClosePartial(position.Ticket(), volume)) {
        // Update tracking
        currentTP.totalClosedVolume += volume;
        currentTP.closedPercent = (currentTP.totalClosedVolume / currentTP.originalVolume) * 100.0;
        currentTP.currentVolume = position.Volume() - volume;
        
        // Reportar PNL del volumen cerrado
        ReportProgress(currentTP.signalId, currentTP.currentLevel, currentTP.closedPercent, 
                      currentTP.currentVolume, currentPrice, closedPnl, "TP parcial");
        
        // LOG: Cierre parcial detallado
        Log(INFO_LVL, "PARTIAL_CLOSE", StringFormat("TP%d: Cerrado %.2f lots (%.1f%%) a %.5f, PNL=%.2f, Restante=%.2f", 
            currentTP.currentLevel, volume, (volume/currentTP.originalVolume)*100, currentPrice, closedPnl, currentTP.currentVolume));
        
        // Check if fully closed
        if(currentTP.currentVolume <= specs.minVolume) {
            ReportClose(currentTP.signalId, currentTP.currentLevel, "CLOSED_COMPLETE", currentPrice, 0.0);
            InitTPState();
        }
        return true;
    }
    return false;
}

void CheckPendingOrderExecution() {
    for(int i = 0; i < PositionsTotal(); i++) {
        if(position.SelectByIndex(i)) {
            if(position.Symbol() == currentSymbol && 
               position.Magic() == MAGIC_NUMBER &&
               position.Comment() == TRADE_COMMENT) {
                
                Log(INFO_LVL, "PENDING", "Orden pendiente ejecutada");
                
                currentTP.isActive = true;
                currentTP.ticket = position.Ticket();
                // NUEVO: Obtener Position ID real
                currentTP.positionID = position.Identifier();
                currentTP.currentVolume = position.Volume();
                currentTP.entry = position.PriceOpen();
                currentTP.currentLevel = 0;
                currentTP.currentSL = position.StopLoss();
                
                double currentPrice = (currentTP.direction == "LONG") ? 
                                     SymbolInfoDouble(currentSymbol, SYMBOL_BID) : 
                                     SymbolInfoDouble(currentSymbol, SYMBOL_ASK);
                double pnl = CalculatePNL(currentPrice);
                ReportProgress(currentTP.signalId, 0, 0.0, currentTP.currentVolume, 
                              currentTP.entry, pnl, "Orden pendiente ejecutada", true);
                return;
            }
        }
    }
    
    if(!OrderSelect(currentTP.ticket)) {
        Log(WARNING_LVL, "PENDING", "Orden pendiente cancelada");
        ReportClose(currentTP.signalId, -999, "ORDER_CANCELLED", 0, 0);
        InitTPState();
    }
}

// CONSULTA DE SEÑALES
void CheckForSignals() {

    string url = BuildAPIUrl("get_signals", 0, TICKER_SYMBOL);
    APIResponse response = SendAPIRequest("GET", url, "", true);

    if(response.result != API_SUCCESS) {
        Log(WARNING_LVL, "SIGNALS", "Error consultando señales");
        return;
    }

    ProcessSignalResponse(response.data);
}

void ProcessSignalResponse(string jsonResponse) {
    if(StringFind(jsonResponse, "\"signal\":null") > -1) return;
    if(StringFind(jsonResponse, "\"success\":true") == -1) return;

    Log(INFO_LVL, "JSON_DEBUG", "JSON recibido (500 chars): " + StringSubstr(jsonResponse, 0, 500));

    SimpleJSONParser parser(jsonResponse);

    int userSignalId = parser.GetInt("user_signal_id");
    if(userSignalId <= 0) return;
    
    string opType = parser.GetString("op_type");
    double entry = parser.GetDouble("entry");
    double sl1 = parser.GetArrayDouble("stoploss", 0);  // SL más alejado (usado para volumen)
    double sl2 = parser.GetArrayDouble("stoploss", 1);  // SL más cercano (backup)

    double tp1 = parser.GetArrayDouble("tps", 0);
    double tp2 = parser.GetArrayDouble("tps", 1);
    double tp3 = parser.GetArrayDouble("tps", 2);
    double tp4 = parser.GetArrayDouble("tps", 3);
    double tp5 = parser.GetArrayDouble("tps", 4);
    
    // Guardar SL2 en variable global (para uso futuro)
    backupStopLoss = sl2;

    // LOG: Señal encontrada con AMBOS SLs
    Log(INFO_LVL, "SIGNAL", StringFormat("Nueva señal recibida: ID=%d, %s, Entry=%.5f, SL1=%.5f (usado), SL2=%.5f (backup), TPs=[%.5f,%.5f,%.5f,%.5f,%.5f]",
        userSignalId, opType, entry, sl1, sl2, tp1, tp2, tp3, tp4, tp5));

    // Validar SL1
    if(sl1 <= 0) {
        Log(ERROR_LVL, "SIGNAL", StringFormat("Señal rechazada ID=%d: SL1 inválido", userSignalId));
        ReportClose(userSignalId, -998, "INVALID_STOPLOSS", 0, 0);
        return;
    }

    // Validación completa de TPs
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
    
    if(ExecuteTrade(userSignalId, opType, entry, sl1, sl2, tp1, tp2, tp3, tp4, tp5)) {
        currentUserSignalId = userSignalId;
        Log(INFO_LVL, "TRADE", "Trade ejecutado exitosamente");
    }
}

bool ExecuteTrade(int userSignalId, string opType, double entryPrice, double stopLoss,
                  double tp1, double tp2, double tp3, double tp4, double tp5,
                  double sl2 = 0) {

    if(currentTP.isActive) return false;

    // NUEVO: Guardar precios originales de la señal (pre-corrección)
    double originalEntry = entryPrice;
    double originalSL1 = stopLoss;
    double originalSL2 = sl2;
    double originalTP1 = tp1;
    double originalTP2 = tp2;
    double originalTP3 = tp3;
    double originalTP4 = tp4;
    double originalTP5 = tp5;

    // Aplicar corrección de precios
    string errorMessage = "";
    double correctionFactor = CalculatePriceCorrection(TICKER_SYMBOL, errorMessage);

    if(correctionFactor <= 0.0) {
        ReportClose(userSignalId, -999, "PRICE_CORRECTION_ERROR", 0, 0);
        return false;
    }

    // Aplicar corrección si es necesaria (convertir ES → US500)
    if(correctionFactor != 1.0) {
        entryPrice /= correctionFactor;  // CORREGIDO: dividir para convertir ES a US500
        stopLoss /= correctionFactor;
        tp1 /= correctionFactor;
        tp2 /= correctionFactor;
        tp3 /= correctionFactor;
        tp4 /= correctionFactor;
        tp5 /= correctionFactor;

        Log(INFO_LVL, "PRICE_CORR", StringFormat("Precios corregidos: Entry=%.5f, SL=%.5f, TPs=[%.5f,%.5f,%.5f,%.5f,%.5f]",
                entryPrice, stopLoss, tp1, tp2, tp3, tp4, tp5));
    } else {
        Log(INFO_LVL, "PRICES", StringFormat("Precios originales (sin corrección): Entry=%.5f, SL=%.5f, TPs=[%.5f,%.5f,%.5f,%.5f,%.5f]",
                entryPrice, stopLoss, tp1, tp2, tp3, tp4, tp5));
    }

    // Validar spread
    int spreadPoints = (int)SymbolInfoInteger(currentSymbol, SYMBOL_SPREAD);
    if(spreadPoints > MAX_SPREAD) {
        SymbolSpecs specs = GetSymbolSpecs();
        double spreadPrice = spreadPoints * specs.point;

        Log(ERROR_LVL, "SPREAD", StringFormat("Spread demasiado alto: Actual=%d points (%.5f precio) | Max=%d points (%.5f precio) | Symbol=%s | Digits=%d | Point=%.5f",
                spreadPoints, spreadPrice,
                (int)MAX_SPREAD, MAX_SPREAD * specs.point,
                currentSymbol, specs.digits, specs.point));

        ReportClose(userSignalId, -999, "SPREAD_TOO_HIGH", 0, 0);
        return false;
    }
    
    // Calcular volumen
    double calculatedVolume = CalculateVolumeOptimized(entryPrice, stopLoss);
    if(calculatedVolume <= 0) {
        ReportClose(userSignalId, -999, "VOLUME_ERROR", 0, 0);
        return false;
    }
    
    // Determinar tipo de orden
    ENUM_ORDER_TYPE orderType;
    double currentPrice = (opType == "LONG") ?
                         SymbolInfoDouble(currentSymbol, SYMBOL_ASK) :
                         SymbolInfoDouble(currentSymbol, SYMBOL_BID);

    double point = SymbolInfoDouble(currentSymbol, SYMBOL_POINT);
    double priceDifference = MathAbs(entryPrice - currentPrice);
    double differencePoints = priceDifference / point;

    // Determinar tolerancia según configuración (prioridad: PERCENT > POINTS)
    double tolerance;
    double tolerancePoints;
    string toleranceMode;

    if(PRICE_TOLERANCE_PERCENT > 0.0) {
        // Modo: Porcentaje del precio de entrada
        tolerance = entryPrice * (PRICE_TOLERANCE_PERCENT / 100.0);
        tolerancePoints = tolerance / point;
        toleranceMode = StringFormat("%.3f%% (%.1f points)", PRICE_TOLERANCE_PERCENT, tolerancePoints);
    } else {
        // Modo: Points absolutos (fallback)
        tolerance = PRICE_TOLERANCE_POINTS * point;
        tolerancePoints = (double)PRICE_TOLERANCE_POINTS;
        toleranceMode = StringFormat("%d points", PRICE_TOLERANCE_POINTS);
    }

    string orderDecision = "";

    if(opType == "LONG") {
        if(priceDifference <= tolerance) {
            orderType = ORDER_TYPE_BUY;
            orderDecision = "MARKET";
        } else if(entryPrice < currentPrice) {
            orderType = ORDER_TYPE_BUY_LIMIT;
            orderDecision = "LIMIT";
        } else {
            orderType = ORDER_TYPE_BUY_STOP;
            orderDecision = "STOP";
        }
    } else {
        if(priceDifference <= tolerance) {
            orderType = ORDER_TYPE_SELL;
            orderDecision = "MARKET";
        } else if(entryPrice > currentPrice) {
            orderType = ORDER_TYPE_SELL_LIMIT;
            orderDecision = "LIMIT";
        } else {
            orderType = ORDER_TYPE_SELL_STOP;
            orderDecision = "STOP";
        }
    }

    Log(INFO_LVL, "ORDER_DECISION", StringFormat("Precio actual=%.5f, Entry=%.5f, Diff=%.1f points, Tolerancia=%s, Decisión=%s",
        currentPrice, entryPrice, differencePoints, toleranceMode, orderDecision));
    
    // Configurar SL según modo - CORREGIDO: multiplicar DISTANCIA, no precio
    double slDistance = MathAbs(entryPrice - stopLoss);
    double effectiveDistance = ENABLE_CODE_STOP ? (slDistance * SAFETY_FACTOR) : slDistance;
    double orderStopLoss = (opType == "LONG") ? (entryPrice - effectiveDistance) : (entryPrice + effectiveDistance);

    Log(INFO_LVL, "SL_CALC", StringFormat("SL Calculation: Original=%.5f, Distance=%.5f, SafetyFactor=%.2f, Effective=%.5f, Final=%.5f",
            stopLoss, slDistance, ENABLE_CODE_STOP ? SAFETY_FACTOR : 1.0, effectiveDistance, orderStopLoss));

    // Ejecutar orden
    bool success = false;
    ulong ticket = 0;
    bool isMarketOrder = (orderType == ORDER_TYPE_BUY || orderType == ORDER_TYPE_SELL);
    
    if(isMarketOrder) {
        success = trade.PositionOpen(currentSymbol, orderType, calculatedVolume, 0, orderStopLoss, 0, TRADE_COMMENT);
        ticket = trade.ResultOrder();
    } else {
        success = trade.OrderOpen(currentSymbol, orderType, calculatedVolume, 0, entryPrice, orderStopLoss, 0, 
                                 ORDER_TIME_DAY, 0, TRADE_COMMENT);
        ticket = trade.ResultOrder();
    }
    
    if(success && ticket > 0) {
        // NUEVO: Para market orders, obtener precio real de ejecución
        double realEntryPrice = entryPrice; // fallback al precio de señal
        
        if(isMarketOrder) {
            // Buscar posición recién creada para obtener precio real
            for(int i = 0; i < PositionsTotal(); i++) {
                if(position.SelectByIndex(i)) {
                    if(position.Symbol() == Symbol() && 
                       position.Magic() == MAGIC_NUMBER &&
                       position.Comment() == TRADE_COMMENT) {
                        realEntryPrice = position.PriceOpen();
                        Log(INFO_LVL, "REAL_ENTRY", StringFormat("Precio real obtenido: %.5f (vs señal: %.5f)", 
                            realEntryPrice, entryPrice));
                        break;
                    }
                }
            }
            
            // Actualizar precio de entrada con precio real
            entryPrice = realEntryPrice;
        }
        
        SetupTPState(userSignalId, opType, entryPrice, stopLoss, calculatedVolume, tp1, tp2, tp3, tp4, tp5, ticket, isMarketOrder);

        // LOG: Posición abierta con detalles
        Log(INFO_LVL, "TRADE_OPEN", StringFormat("Posición abierta: %s %s, Ticket=%d, Vol=%.2f, Tipo=%s, Entry=%.5f, Corrección=%.4f",
            (isMarketOrder ? "MARKET" : "PENDING"), opType, ticket, calculatedVolume, EnumToString(orderType), entryPrice, correctionFactor));

        // NUEVO: Reportar con precio real para market orders + datos originales de la señal
        ReportOpen(userSignalId, isMarketOrder, orderType, entryPrice, orderStopLoss, calculatedVolume, ticket,
                   opType, originalEntry, originalSL1, originalSL2,
                   originalTP1, originalTP2, originalTP3, originalTP4, originalTP5);
        return true;
    } else {
        ReportClose(userSignalId, -999, "EXECUTION_FAILED", 0, 0);
        return false;
    }
}

void SetupTPState(int signalId, string direction, double entry, double originalStopLoss, double volume, 
                  double tp1, double tp2, double tp3, double tp4, double tp5, ulong ticket, bool isMarketOrder) {
    
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
    
    // NUEVO: Obtener Position ID real para market orders
    if(isMarketOrder) {
        // Para market orders, obtener Position ID después de la ejecución
        for(int i = 0; i < PositionsTotal(); i++) {
            if(position.SelectByIndex(i)) {
                if(position.Symbol() == currentSymbol && 
                   position.Magic() == MAGIC_NUMBER &&
                   position.Comment() == TRADE_COMMENT) {
                    currentTP.positionID = position.Identifier();
                    Log(INFO_LVL, "SETUP", "Position ID obtenido: " + IntegerToString(currentTP.positionID));
                    break;
                }
            }
        }
    } else {
        // Para pending orders, usar el ticket por ahora
        currentTP.positionID = ticket;
        Log(INFO_LVL, "SETUP", "Position ID temporal (pending): " + IntegerToString(currentTP.positionID));
    }
}