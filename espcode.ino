#include <Arduino.h>
#include <ESP8266WiFi.h>
#include <ESP8266HTTPClient.h>
#include <WiFiClient.h>
#include <SoftwareSerial.h>
#include <Adafruit_Fingerprint.h>
#include <ArduinoJson.h>
#include <Wire.h>
#include <LiquidCrystal_I2C.h>

#define WIFI_SSID "Fingerprintscans"
#define WIFI_PASSWORD "fingerpassword"

#define DISCOVERY_RETRY_INTERVAL 10000

#define DISCOVERY_SCAN_CONNECT_TIMEOUT_MS 80
#define DISCOVERY_SCAN_HTTP_WINDOW_MS 220
#define DISCOVERY_SCAN_HOSTS_PER_ATTEMPT 16


#define WIFI_TIMEOUT 20000
#define WIFI_RECONNECT_INTERVAL 10000
#define SENSOR_RETRY_INTERVAL 5000
#define SERVER_CHECK_INTERVAL 10000
#define COMMAND_POLL_INTERVAL 1500
#define SYSTEM_SUMMARY_INTERVAL 10000
#define ENROLL_SCAN_STEPS 3
#define ENROLL_IDLE_COOLDOWN_MS 5000
#define ATTEND_IDLE_COOLDOWN_MS 2500
#define ENROLL_CAPTURE_TIMEOUT_MS 25000
#define ENROLL_REMOVE_TIMEOUT_MS 12000
#define ENROLL_MODEL_RETRY_LIMIT 2
#define ENROLL_DUPLICATE_CONFIDENCE_MIN 110

#define SENSOR_RX_PIN D1
#define SENSOR_TX_PIN D2
#define SENSOR_BAUD_RATE 57600

#define GREEN_LED_PIN D5
#define RED_LED_PIN D6
#define BUZZER_PIN D7

#define LCD_I2C_ADDRESS 0x27
#define LCD_COLS 16
#define LCD_ROWS 2
#define I2C_SDA_PIN D3
#define I2C_SCL_PIN D4



const uint16_t SERVER_PORT = 80;
const int DEVICE_ID = 1;
const char* DEVICE_KEY = "CAMS_ESP8266";
const char* API_BASE_PATH = "/CAMS/api";

SoftwareSerial fingerSerial(SENSOR_RX_PIN, SENSOR_TX_PIN);
Adafruit_Fingerprint finger(&fingerSerial);
LiquidCrystal_I2C lcd(LCD_I2C_ADDRESS, LCD_COLS, LCD_ROWS);

unsigned long lastWifiAttempt = 0;
unsigned long lastIdleScanMs = 0;
unsigned long lastModeLogMs = 0;
unsigned long lastIdleModeLogMs = 0;
unsigned long lastCmdFetchLogMs = 0;
unsigned long lastCmdPollMs = 0;
unsigned long lastSensorStatusLogMs = 0;
unsigned long lastWifiSkipLogMs = 0;
unsigned long lastSensorInitAttemptMs = 0;
unsigned long lastScanErrorLogMs = 0;
unsigned long lastServerCheckMs = 0;
unsigned long lastSystemSummaryMs = 0;
unsigned long lastDiscoveryAttemptMs = 0;
unsigned long lastEnrollCompleteMs = 0;
unsigned long lastAttendanceCompleteMs = 0;
uint8_t subnetScanNextHost = 1;
String lcdLine1 = "";
String lcdLine2 = "";
bool wifiWasConnected = false;
bool sensorReady = false;
bool serverReady = false;
bool bootReady = false;
String currentMode = "IDLE";
String lastEnrollFailureReason = "";
String serverHost = "";

bool probeServerCandidate(const IPAddress& candidateIp) {
  WiFiClient probeClient;
  probeClient.setTimeout(DISCOVERY_SCAN_CONNECT_TIMEOUT_MS);

  if (!probeClient.connect(candidateIp, SERVER_PORT)) {
    return false;
  }

  String host = candidateIp.toString();
  String healthPath = String(API_BASE_PATH) + "/health.php?device_key=" + String(DEVICE_KEY);
  probeClient.print("GET ");
  probeClient.print(healthPath);
  probeClient.println(" HTTP/1.1");
  probeClient.print("Host: ");
  probeClient.println(host);
  probeClient.println("Connection: close");
  probeClient.println();

  unsigned long start = millis();
  String response = "";
  while (millis() - start < DISCOVERY_SCAN_HTTP_WINDOW_MS) {
    while (probeClient.available()) {
      char c = (char)probeClient.read();
      if (response.length() < 256) {
        response += c;
      }
      if (response.indexOf("\"success\":true") >= 0) {
        probeClient.stop();
        return true;
      }
      start = millis();
    }

    if (!probeClient.connected()) {
      break;
    }
    delay(5);
    yield();
  }

  probeClient.stop();
  return response.indexOf("\"success\":true") >= 0;
}

bool discoverServerBySubnetScan() {
  if (WiFi.status() != WL_CONNECTED) {
    return false;
  }

  IPAddress localIp = WiFi.localIP();
  IPAddress subnetMask = WiFi.subnetMask();
  if (!(subnetMask[0] == 255 && subnetMask[1] == 255 && subnetMask[2] == 255)) {
    logInfo("DISCOVERY", "Subnet scan skipped (non-/24 network)");
    return false;
  }

  displayLCD("Finding server", "Subnet scan");
  logInfo("DISCOVERY", "Trying subnet scan fallback...");

  bool tried[255] = {false};

  // Prioritize common CAMS host ranges first to reduce first-match latency.
  int localHost = (int)localIp[3];
  int nearMinus1 = localHost > 1 ? localHost - 1 : localHost;
  int nearPlus1 = localHost < 254 ? localHost + 1 : localHost;
  uint8_t preferredHosts[] = {
    94, 95, 93, 100, 101, 102, 90, 110, 120,
    (uint8_t)localHost, (uint8_t)nearMinus1, (uint8_t)nearPlus1,
    1, 2, 10, 20, 50, 150, 200, 254
  };

  for (size_t i = 0; i < (sizeof(preferredHosts) / sizeof(preferredHosts[0])); i++) {
    uint8_t host = preferredHosts[i];
    if (host < 1 || host > 254 || tried[host]) {
      continue;
    }

    tried[host] = true;
    IPAddress candidate(localIp[0], localIp[1], localIp[2], host);
    if (probeServerCandidate(candidate)) {
      serverHost = candidate.toString();
      subnetScanNextHost = 1;
      logInfo("DISCOVERY", "Server found by scan at " + serverHost);
      displayLCD("Server found", serverHost);
      return true;
    }
    yield();
  }

  for (uint8_t scanned = 0; scanned < DISCOVERY_SCAN_HOSTS_PER_ATTEMPT; scanned++) {
    if (subnetScanNextHost > 254) {
      subnetScanNextHost = 1;
    }

    uint8_t host = subnetScanNextHost++;
    if (tried[host]) {
      continue;
    }

    IPAddress candidate(localIp[0], localIp[1], localIp[2], host);
    if (probeServerCandidate(candidate)) {
      serverHost = candidate.toString();
      subnetScanNextHost = 1;
      logInfo("DISCOVERY", "Server found by scan at " + serverHost);
      displayLCD("Server found", serverHost);
      return true;
    }
    yield();
  }

  logInfo("DISCOVERY", "Subnet scan chunk finished, no match yet");
  return false;
}

bool discoverServerHost() {
  if (WiFi.status() != WL_CONNECTED) {
    return false;
  }

  unsigned long now = millis();
  if (now - lastDiscoveryAttemptMs < DISCOVERY_RETRY_INTERVAL) {
    return serverHost.length() > 0;
  }
  lastDiscoveryAttemptMs = now;

  return discoverServerBySubnetScan();
}

const uint8_t FEEDBACK_IDLE = 0;
const uint8_t FEEDBACK_WAITING = 1;
const uint8_t FEEDBACK_SUCCESS = 2;
const uint8_t FEEDBACK_ERROR = 3;

uint8_t feedbackMode = FEEDBACK_IDLE;
bool feedbackWaitingGreenPhase = true;
unsigned long feedbackLastToggleMs = 0;
unsigned long feedbackPulseUntilMs = 0;

void setFeedbackOutputs(bool greenOn, bool redOn) {
  digitalWrite(GREEN_LED_PIN, greenOn ? HIGH : LOW);
  digitalWrite(RED_LED_PIN, redOn ? HIGH : LOW);
}

void stopFeedbackTone() {
  noTone(BUZZER_PIN);
  digitalWrite(BUZZER_PIN, LOW);
}

void playFeedbackTone(uint16_t frequency, uint16_t durationMs) {
  tone(BUZZER_PIN, frequency, durationMs);
  feedbackPulseUntilMs = millis() + durationMs;
}

void setFeedbackMode(uint8_t mode) {
  feedbackMode = mode;

  if (mode == FEEDBACK_WAITING) {
    feedbackLastToggleMs = 0;
    feedbackWaitingGreenPhase = true;
    setFeedbackOutputs(true, false);
    stopFeedbackTone();
    return;
  }

  if (mode == FEEDBACK_SUCCESS) {
    setFeedbackOutputs(true, false);
    playFeedbackTone(2400, 90);
    return;
  }

  if (mode == FEEDBACK_ERROR) {
    setFeedbackOutputs(false, true);
    playFeedbackTone(1200, 180);
    return;
  }

  setFeedbackOutputs(false, false);
  stopFeedbackTone();
}

void syncFeedbackFromDisplay(const String& line1, const String& line2) {
  String text = (line1 + " " + line2);
  text.toLowerCase();

  if (text.indexOf("booting") >= 0 || text.indexOf("wifi connecting") >= 0 || text.indexOf("wifi failed") >= 0) {
    setFeedbackMode(FEEDBACK_IDLE);
    return;
  }

  if (text.indexOf("try again") >= 0 || text.indexOf("not found") >= 0 || text.indexOf("failed") >= 0 || text.indexOf("offline") >= 0 || text.indexOf("error") >= 0 || text.indexOf("duplicate") >= 0 || text.indexOf("not enrolled") >= 0 || text.indexOf("read error") >= 0) {
    setFeedbackMode(FEEDBACK_ERROR);
    return;
  }

  if (text.indexOf("wait finger") >= 0 || text.indexOf("scanning f") >= 0 || text.indexOf("scan finger") >= 0) {
    setFeedbackMode(FEEDBACK_WAITING);
    return;
  }

  if (text.indexOf("saved") >= 0 || text.indexOf("done") >= 0 || text.indexOf("welcome") >= 0) {
    setFeedbackMode(FEEDBACK_SUCCESS);
    return;
  }

  setFeedbackMode(FEEDBACK_IDLE);
}

void updateFeedback() {
  if (feedbackMode == FEEDBACK_WAITING) {
    if (millis() - feedbackLastToggleMs >= 300) {
      feedbackLastToggleMs = millis();
      feedbackWaitingGreenPhase = !feedbackWaitingGreenPhase;
      setFeedbackOutputs(feedbackWaitingGreenPhase, !feedbackWaitingGreenPhase);
    }
    return;
  }

  if ((feedbackMode == FEEDBACK_SUCCESS || feedbackMode == FEEDBACK_ERROR) && feedbackPulseUntilMs > 0 && millis() >= feedbackPulseUntilMs) {
    feedbackPulseUntilMs = 0;
    setFeedbackMode(FEEDBACK_IDLE);
  }
}

void initFeedbackHardware() {
  pinMode(GREEN_LED_PIN, OUTPUT);
  pinMode(RED_LED_PIN, OUTPUT);
  pinMode(BUZZER_PIN, OUTPUT);
  setFeedbackMode(FEEDBACK_IDLE);
}

void logInfo(const String& tag, const String& message) {
  Serial.print("[");
  Serial.print(tag);
  Serial.print("] ");
  Serial.println(message);
}

void logSystemSummary() {
  if (millis() - lastSystemSummaryMs < SYSTEM_SUMMARY_INTERVAL) {
    return;
  }

  lastSystemSummaryMs = millis();
  String ip = (WiFi.status() == WL_CONNECTED) ? WiFi.localIP().toString() : "-";

  Serial.print("[SYS] WIFI=");
  Serial.print((WiFi.status() == WL_CONNECTED) ? "UP" : "DOWN");
  Serial.print(" SERVER=");
  Serial.print(serverReady ? "UP" : "DOWN");
  Serial.print(" SENSOR=");
  Serial.print(sensorReady ? "READY" : "DOWN");
  Serial.print(" IP=");
  Serial.print(ip);
  Serial.print(" CURRENT=");
  Serial.println(currentMode);
}

bool checkServerReady() {
  if (WiFi.status() != WL_CONNECTED) {
    serverReady = false;
    return false;
  }

  if (serverHost.length() == 0 && !discoverServerHost()) {
    serverReady = false;
    return false;
  }

  unsigned long now = millis();
  if (now - lastServerCheckMs < SERVER_CHECK_INTERVAL) {
    return serverReady;
  }
  lastServerCheckMs = now;

  logInfo("SERVER", "Checking API health...");

  String body;
  int statusCode = 0;
  if (!httpGet(String(API_BASE_PATH) + "/health.php?device_key=" + String(DEVICE_KEY), body, &statusCode)) {
    serverReady = false;
    logInfo("SERVER", "API unreachable (HTTP code=" + String(statusCode) + ")");
    return false;
  }

  logInfo("SERVER", "API health HTTP code=" + String(statusCode));

  StaticJsonDocument<256> doc;
  DeserializationError err = deserializeJson(doc, body);
  if (err) {
    serverReady = false;
    logInfo("SERVER", "API response parse failed");
    return false;
  }

  if (!(doc["success"] | false)) {
    serverReady = false;
    logInfo("SERVER", "API returned success=false");
    return false;
  }

  serverReady = true;
  logInfo("SERVER", "API reachable");
  return true;
}

void logSensorStatus() {
  if (millis() - lastSensorStatusLogMs < 10000) {
    return;
  }

  lastSensorStatusLogMs = millis();
  if (sensorReady) {
    logInfo("SENSOR", "Status=READY");
  } else {
    logInfo("SENSOR", "Status=NOT_READY");
  }
}

bool initFingerprintSensor() {
  lastSensorInitAttemptMs = millis();

  fingerSerial.begin(SENSOR_BAUD_RATE);
  finger.begin(SENSOR_BAUD_RATE);

  if (!finger.verifyPassword()) {
    sensorReady = false;
    logInfo("SENSOR", "Not found / password verify failed");
    return false;
  }

  sensorReady = true;
  Serial.print("[SENSOR] Ready, baud=");
  Serial.println(SENSOR_BAUD_RATE);

  uint8_t countStatus = finger.getTemplateCount();
  if (countStatus == FINGERPRINT_OK) {
    Serial.print("[SENSOR] Templates=");
    Serial.println(finger.templateCount);
  } else {
    Serial.print("[SENSOR] getTemplateCount failed, code=");
    Serial.println(countStatus);
  }
  return true;
}

typedef struct DeviceCommand {
  String mode;
  int studentId;
  int fingerIndex;
  int sensorId;
  bool valid;
} DeviceCommand;

DeviceCommand getCommand();
bool reportScanProgress(int studentId, int fingerIndex, int scanStep, int totalSteps);
bool reportScanProgressWithStatus(int studentId, int fingerIndex, int scanStep, int totalSteps, const char* uiStatus, const String& uiMessage);
bool isDuplicateAssignedToOtherStudent(int sensorId, int studentId);

void displayLCD(const String& message) {
  String line1 = message;
  if (line1.length() > LCD_COLS) {
    line1 = line1.substring(0, LCD_COLS);
  }

  if (lcdLine1 == line1 && lcdLine2 == "") {
    return;
  }

  lcdLine1 = line1;
  lcdLine2 = "";

  lcd.clear();
  lcd.setCursor(0, 0);
  lcd.print(line1);
  syncFeedbackFromDisplay(line1, "");
}

void displayLCD(const String& line1, const String& line2) {
  String l1 = line1;
  String l2 = line2;

  if (l1.length() > LCD_COLS) {
    l1 = l1.substring(0, LCD_COLS);
  }
  if (l2.length() > LCD_COLS) {
    l2 = l2.substring(0, LCD_COLS);
  }

  if (lcdLine1 == l1 && lcdLine2 == l2) {
    return;
  }

  lcdLine1 = l1;
  lcdLine2 = l2;

  lcd.clear();
  lcd.setCursor(0, 0);
  lcd.print(l1);
  lcd.setCursor(0, 1);
  lcd.print(l2);
  syncFeedbackFromDisplay(l1, l2);
}

bool connectWiFi() {
  if (WiFi.status() == WL_CONNECTED) {
    if (!wifiWasConnected) {
      wifiWasConnected = true;
      Serial.print("[WIFI] Connected, IP=");
      Serial.println(WiFi.localIP());
    }
    return true;
  }

  if (wifiWasConnected) {
    wifiWasConnected = false;
    serverReady = false;
    serverHost = "";
    logInfo("WIFI", "Disconnected");
  }

  unsigned long now = millis();
  if (now - lastWifiAttempt < WIFI_RECONNECT_INTERVAL) {
    if (now - lastWifiSkipLogMs > 5000) {
      lastWifiSkipLogMs = now;
      logInfo("WIFI", "Retry pending...");
    }
    return false;
  }

  lastWifiAttempt = now;

  logInfo("WIFI", "Connecting...");
  displayLCD("WiFi connecting");

  WiFi.mode(WIFI_STA);
  WiFi.begin(WIFI_SSID, WIFI_PASSWORD);

  unsigned long start = millis();
  while (WiFi.status() != WL_CONNECTED && (millis() - start) < WIFI_TIMEOUT) {
    delay(200);
    yield();
  }

  if (WiFi.status() == WL_CONNECTED) {
    wifiWasConnected = true;
    Serial.print("[WIFI] Connected, IP=");
    Serial.println(WiFi.localIP());
    displayLCD("WiFi connected");
    return true;
  }

  logInfo("WIFI", "Connect failed");
  displayLCD("WiFi failed");
  return false;
}

bool httpGet(const String& path, String& outBody, int* outStatusCode) {
  outBody = "";
  if (WiFi.status() != WL_CONNECTED) {
    logInfo("HTTP", "GET skipped, WiFi not connected");
    if (outStatusCode) {
      *outStatusCode = 0;
    }
    return false;
  }

  if (serverHost.length() == 0 && !discoverServerHost()) {
    logInfo("HTTP", "GET skipped, server host not discovered");
    if (outStatusCode) {
      *outStatusCode = 0;
    }
    return false;
  }

  WiFiClient client;
  HTTPClient http;
  String url = "http://" + serverHost + ":" + String(SERVER_PORT) + path;
  Serial.print("[HTTP] GET ");
  Serial.println(url);

  if (!http.begin(client, url)) {
    serverReady = false;
    logInfo("HTTP", "GET begin() failed");
    if (outStatusCode) {
      *outStatusCode = 0;
    }
    return false;
  }

  http.setTimeout(5000);

  int code = http.GET();
  if (outStatusCode) {
    *outStatusCode = code;
  }
  Serial.print("[HTTP] GET status=");
  Serial.println(code);
  
  if (code <= 0) {
    serverReady = false;
    String err = HTTPClient::errorToString(code);
    logInfo("HTTP", "GET error=" + err);
  }
  if (code > 0) {
    serverReady = true;
    outBody = http.getString();
  }

  http.end();
  return code > 0;
}

bool httpPostJson(const String& path, const String& payload, String& outBody) {
  outBody = "";
  if (WiFi.status() != WL_CONNECTED) {
    logInfo("HTTP", "POST skipped, WiFi not connected");
    return false;
  }

  if (serverHost.length() == 0 && !discoverServerHost()) {
    logInfo("HTTP", "POST skipped, server host not discovered");
    return false;
  }

  WiFiClient client;
  HTTPClient http;
  String url = "http://" + serverHost + ":" + String(SERVER_PORT) + path;
  Serial.print("[HTTP] POST ");
  Serial.println(url);

  if (!http.begin(client, url)) {
    serverReady = false;
    logInfo("HTTP", "POST begin() failed");
    return false;
  }

  http.setTimeout(5000);
  http.addHeader("Content-Type", "application/json");
  int code = http.POST(payload);
  Serial.print("[HTTP] POST status=");
  Serial.println(code);
  if (code <= 0) {
    serverReady = false;
    String err = HTTPClient::errorToString(code);
    logInfo("HTTP", "POST error=" + err);
  }

  if (code > 0) {
    serverReady = true;
    outBody = http.getString();
  }

  http.end();
  return code > 0;
}

DeviceCommand getCommand() {
  DeviceCommand cmd;
  cmd.mode = "IDLE";
  cmd.studentId = 0;
  cmd.fingerIndex = 0;
  cmd.sensorId = 0;
  cmd.valid = false;

  // Reduce API polling frequency without changing fingerprint scan timing.
  if (millis() - lastCmdPollMs < COMMAND_POLL_INTERVAL) {
    return cmd;
  }
  lastCmdPollMs = millis();

  String body;
  String path = String(API_BASE_PATH) + "/device-command.php?device_id=" + String(DEVICE_ID);
  if (millis() - lastCmdFetchLogMs > 10000) {
    lastCmdFetchLogMs = millis();
    logInfo("MODE", "Fetching mode from API...");
  }
  int statusCode = 0;
  if (!httpGet(path, body, &statusCode)) {
    logInfo("MODE", "Fetch failed (server unreachable/error)");
    return cmd;
  }

  logInfo("MODE", "Server connected, mode received (HTTP code=" + String(statusCode) + ")");

  StaticJsonDocument<256> doc;
  DeserializationError err = deserializeJson(doc, body);
  if (err) {
    Serial.println("[CMD] JSON parse failed");
    return cmd;
  }

  if (!(doc["success"] | false)) {
    Serial.println("[CMD] success=false");
    return cmd;
  }

  cmd.mode = String((const char*)doc["mode"]);
  cmd.studentId = doc["student_id"] | 0;
  cmd.fingerIndex = doc["finger_index"] | 0;
  cmd.sensorId = doc["sensor_id"] | 0;
  cmd.valid = true;

  Serial.print("[MODE] mode=");
  Serial.print(cmd.mode);
  Serial.print(" student_id=");
  Serial.print(cmd.studentId);
  Serial.print(" finger_index=");
  Serial.print(cmd.fingerIndex);
  Serial.print(" sensor_id=");
  Serial.println(cmd.sensorId);

  return cmd;
}

int getNextAvailableID() {
  for (int id = 1; id <= 127; id++) {
    uint8_t p = finger.loadModel(id);
    if (p != FINGERPRINT_OK) {
      return id;
    }
  }

  return -1;
}

bool captureToBuffer(uint8_t bufferNo, unsigned long timeoutMs) {
  unsigned long start = millis();

  while (millis() - start < timeoutMs) {
    uint8_t p = finger.getImage();
    if (p == FINGERPRINT_NOFINGER) {
      delay(20);
      yield();
      continue;
    }

    if (p != FINGERPRINT_OK) {
      return false;
    }

    p = finger.image2Tz(bufferNo);
    return p == FINGERPRINT_OK;
  }

  return false;
}

bool waitFingerRemoved(unsigned long timeoutMs) {
  unsigned long start = millis();

  while (millis() - start < timeoutMs) {
    if (finger.getImage() == FINGERPRINT_NOFINGER) {
      return true;
    }
    updateFeedback();
    delay(20);
    yield();
  }

  return false;
}

bool isTransientGetImageError(uint8_t code) {
  // Packet receive and image acquisition errors are often transient on ESP8266 + sensor wiring.
  return code == FINGERPRINT_PACKETRECIEVEERR || code == FINGERPRINT_IMAGEFAIL;
}

void showEnrollStep(int fingerIndex, int stepNo, const String& message) {
  String line1 = "Scanning F" + String(fingerIndex);
  String line2 = String(stepNo) + " of " + String(ENROLL_SCAN_STEPS);

  if (message.length() > 0) {
    line2 += " ";
    line2 += message;
  }

  displayLCD(line1, line2);
}

bool isDuplicateAssignedToOtherStudent(int sensorId, int studentId) {
  if (sensorId <= 0 || studentId <= 0) {
    return true;
  }

  String path = String(API_BASE_PATH) + "/fingerprint-owner.php?sensor_id=" + String(sensorId) + "&student_id=" + String(studentId);
  String body;
  int statusCode = 0;
  if (!httpGet(path, body, &statusCode)) {
    Serial.println("[ENROLL] Ownership check failed, treating as duplicate for safety");
    return true;
  }

  StaticJsonDocument<256> doc;
  DeserializationError err = deserializeJson(doc, body);
  if (err || !(doc["success"] | false)) {
    Serial.println("[ENROLL] Ownership check parse failed, treating as duplicate for safety");
    return true;
  }

  return doc["owned_by_other"] | false;
}

bool enrollFinger(int sensorId, int fingerIndex, int studentId) {
  Serial.print("[ENROLL] Start ID=");
  Serial.println(sensorId);
  lastEnrollFailureReason = "";

  uint8_t p;
  unsigned long start;
  int modelRetryCount = 0;
  int lastReportedScanStep = 0;
  String lastReportedUiStatus = "";
  String lastReportedUiMessage = "";
  bool retryEnrollmentAttempt = false;

  do {
    retryEnrollmentAttempt = false;
    // Report scan step 1 to server before starting
    reportEnrollmentProgressIfChanged(studentId, fingerIndex, 1, ENROLL_SCAN_STEPS, "waiting", "", lastReportedScanStep, lastReportedUiStatus, lastReportedUiMessage);

    // Step 1: getImage() + image2Tz(1)
    showEnrollStep(fingerIndex, 1, "");
    start = millis();
    int step1TransientErrors = 0;
    do {
      p = finger.getImage();
      if (p == FINGERPRINT_OK) {
        break;
      }
      if (p != FINGERPRINT_NOFINGER && !isTransientGetImageError(p)) {
        Serial.print("[ENROLL] getImage #1 failed: ");
        Serial.println(p);
        lastEnrollFailureReason = "getImage#1 code=" + String((int)p);
        displayLCD("Not Found", "Try Again");
        return false;
      }
      if (p != FINGERPRINT_NOFINGER) {
        step1TransientErrors++;
        if (step1TransientErrors <= 3 || step1TransientErrors % 10 == 0) {
          Serial.print("[ENROLL] getImage #1 transient: ");
          Serial.println(p);
        }
      }
      updateFeedback();
      delay(20);
      yield();
    } while (millis() - start < ENROLL_CAPTURE_TIMEOUT_MS);

    if (p != FINGERPRINT_OK) {
      Serial.println("[ENROLL] getImage #1 timeout");
      lastEnrollFailureReason = "getImage#1 timeout";
      displayLCD("Not Found", "Try Again");
      return false;
    }

    p = finger.image2Tz(1);
    if (p != FINGERPRINT_OK) {
      Serial.print("[ENROLL] image2Tz(1) failed: ");
      Serial.println(p);
      lastEnrollFailureReason = "image2Tz#1 code=" + String((int)p);
      displayLCD("Not Found", "Try Again");
      return false;
    }

    // Reject a finger that is already enrolled in sensor memory.
    // Keep waiting so the user can try another finger instead of failing the whole session.
    p = finger.fingerFastSearch();
    if (p == FINGERPRINT_OK) {
      int duplicateSensorId = finger.fingerID;
      int duplicateConfidence = finger.confidence;

      // Ignore weak matches to reduce false duplicate detection between different fingers.
      if (duplicateConfidence < ENROLL_DUPLICATE_CONFIDENCE_MIN) {
        Serial.print("[ENROLL] Weak duplicate match ignored sensor_id=");
        Serial.print(duplicateSensorId);
        Serial.print(" confidence=");
        Serial.println(duplicateConfidence);
      } else {
        bool duplicateConfirmed = false;

        // Require a second scan confirmation before rejecting as duplicate.
        displayLCD("Confirm finger", "Scan again");
        waitFingerRemoved(ENROLL_REMOVE_TIMEOUT_MS);
        if (captureToBuffer(1, ENROLL_CAPTURE_TIMEOUT_MS)) {
          uint8_t confirmSearch = finger.fingerFastSearch();
          if (confirmSearch == FINGERPRINT_OK && finger.fingerID == duplicateSensorId && finger.confidence >= ENROLL_DUPLICATE_CONFIDENCE_MIN) {
            duplicateConfirmed = true;
          }
        }

        if (duplicateConfirmed) {
          if (isDuplicateAssignedToOtherStudent(duplicateSensorId, studentId)) {
            Serial.print("[ENROLL] Duplicate finger confirmed at sensor_id=");
            Serial.print(duplicateSensorId);
            Serial.print(" confidence=");
            Serial.println(duplicateConfidence);
            reportEnrollmentProgressIfChanged(studentId, fingerIndex, 1, ENROLL_SCAN_STEPS, "duplicate", "Duplicate finger already enrolled. Use another finger.", lastReportedScanStep, lastReportedUiStatus, lastReportedUiMessage);
            displayLCD("Use other finger", "Try again");
            waitFingerRemoved(ENROLL_REMOVE_TIMEOUT_MS);
            retryEnrollmentAttempt = true;
            break;
          }

          Serial.print("[ENROLL] Duplicate sensor match is not linked to other student, continuing. sensor_id=");
          Serial.println(duplicateSensorId);
        }

        Serial.print("[ENROLL] Duplicate not confirmed, continuing enrollment. sensor_id=");
        Serial.println(duplicateSensorId);
      }
    }

  } while (retryEnrollmentAttempt);

  // Wait for finger removal.
  showEnrollStep(fingerIndex, 1, "Remove");
  start = millis();
  while (millis() - start < ENROLL_REMOVE_TIMEOUT_MS) {
    p = finger.getImage();
    if (p == FINGERPRINT_NOFINGER) {
      break;
    }
      updateFeedback();
    delay(20);
    yield();
  }

  if (p != FINGERPRINT_NOFINGER) {
    Serial.println("[ENROLL] remove finger timeout");
    lastEnrollFailureReason = "remove finger timeout";
    displayLCD("Not Found", "Try Again");
    return false;
  }

  // Report scan step 2 to server
  reportScanProgress(studentId, fingerIndex, 2, ENROLL_SCAN_STEPS);

  // Step 2: getImage() + image2Tz(2)
  showEnrollStep(fingerIndex, 2, "");
  start = millis();
  int step2TransientErrors = 0;
  do {
    p = finger.getImage();
    if (p == FINGERPRINT_OK) {
      break;
    }
    if (p != FINGERPRINT_NOFINGER && !isTransientGetImageError(p)) {
      Serial.print("[ENROLL] getImage #2 failed: ");
      Serial.println(p);
      lastEnrollFailureReason = "getImage#2 code=" + String((int)p);
      displayLCD("Not Found", "Try Again");
      return false;
    }
    
    if (p != FINGERPRINT_NOFINGER) {
      step2TransientErrors++;
      if (step2TransientErrors <= 3 || step2TransientErrors % 10 == 0) {
        Serial.print("[ENROLL] getImage #2 transient: ");
        Serial.println(p);
      }
    }
      updateFeedback();
    delay(20);
    yield();
  } while (millis() - start < ENROLL_CAPTURE_TIMEOUT_MS);

  if (p != FINGERPRINT_OK) {
    Serial.println("[ENROLL] getImage #2 timeout");
    lastEnrollFailureReason = "getImage#2 timeout";
    displayLCD("Not Found", "Try Again");
    return false;
  }

  p = finger.image2Tz(2);
  if (p != FINGERPRINT_OK) {
    Serial.print("[ENROLL] image2Tz(2) failed: ");
    Serial.println(p);
    lastEnrollFailureReason = "image2Tz#2 code=" + String((int)p);
    displayLCD("Not Found", "Try Again");
    return false;
  }

  // Report scan step 3 to server
  reportScanProgress(studentId, fingerIndex, 3, ENROLL_SCAN_STEPS);

  // Keep step 3 visible briefly so web polling can render "3 of 3" before finalize.
  unsigned long step3HoldStart = millis();
  while (millis() - step3HoldStart < 900) {
    updateFeedback();
    delay(20);
    yield();
  }

  // Step 3: createModel()
  showEnrollStep(fingerIndex, 3, "Save");
  p = finger.createModel();
  if (p != FINGERPRINT_OK) {
    Serial.print("[ENROLL] createModel failed: ");
    Serial.println(p);
    if ((int)p == 10 && modelRetryCount < ENROLL_MODEL_RETRY_LIMIT) {
      modelRetryCount++;
      Serial.print("[ENROLL] createModel mismatch, retrying enrollment attempt #");
      Serial.println(modelRetryCount);
      lastEnrollFailureReason = "createModel mismatch";
      reportEnrollmentProgressIfChanged(studentId, fingerIndex, 2, ENROLL_SCAN_STEPS, "retry", "Fingerprint mismatch. Scan the same finger again.", lastReportedScanStep, lastReportedUiStatus, lastReportedUiMessage);
      displayLCD("Hold steady", "Scan again");
      waitFingerRemoved(ENROLL_REMOVE_TIMEOUT_MS);
      return false;
    }

    lastEnrollFailureReason = "createModel code=" + String((int)p);
    displayLCD("Not Found", "Try Again");
    return false;
  }

  // Step 4: storeModel(sensor_id)
  p = finger.storeModel(sensorId);
  if (p != FINGERPRINT_OK) {
    Serial.print("[ENROLL] storeModel failed: ");
    Serial.println(p);
    lastEnrollFailureReason = "storeModel code=" + String((int)p);
    displayLCD("Not Found", "Try Again");
    return false;
  }

  // Prevent accidental immediate attendance reads by requiring finger release.
  showEnrollStep(fingerIndex, 3, "Remove");
  waitFingerRemoved(5000);

  Serial.println("[ENROLL] Success");
  displayLCD("Finger " + String(fingerIndex) + " Saved", "ID:" + String(sensorId));
  return true;
}

bool sendEnrollResult(int studentId, int fingerIndex, int sensorId) {
  StaticJsonDocument<192> doc;
  doc["student_id"] = studentId;
  doc["finger_index"] = fingerIndex;
  doc["sensor_id"] = sensorId;

  String payload;
  serializeJson(doc, payload);

  String body;
  Serial.println("[ENROLL] Posting enroll result to server...");
  if (!httpPostJson(String(API_BASE_PATH) + "/enroll-result.php", payload, body)) {
    Serial.println("[ENROLL] POST enroll-result failed");
    return false;
  }

  StaticJsonDocument<256> resp;
  if (deserializeJson(resp, body)) {
    Serial.println("[ENROLL] Response parse failed");
    return false;
  }

  bool ok = resp["success"] | false;
  Serial.print("[ENROLL] Result POST success=");
  Serial.println(ok ? "true" : "false");
  return ok;
}

bool sendEnrollFailure(int studentId, int fingerIndex, const String& reason) {
  StaticJsonDocument<256> doc;
  doc["student_id"] = studentId;
  doc["finger_index"] = fingerIndex;
  doc["success"] = false;
  doc["error_message"] = reason;

  String payload;
  serializeJson(doc, payload);

  String body;
  Serial.println("[ENROLL] Posting enroll failure to server...");
  if (!httpPostJson(String(API_BASE_PATH) + "/enroll-result.php", payload, body)) {
    Serial.println("[ENROLL] POST enroll-result (failure) failed");
    return false;
  }

  StaticJsonDocument<256> resp;
  if (deserializeJson(resp, body)) {
    Serial.println("[ENROLL] Failure response parse failed");
    return false;
  }

  bool ok = resp["success"] | false;
  Serial.print("[ENROLL] Failure POST success=");
  Serial.println(ok ? "true" : "false");
  return ok;
}

bool advanceToNextFinger(int studentId, int fingerIndex) {
  StaticJsonDocument<128> doc;
  doc["student_id"] = studentId;
  doc["finger_index"] = fingerIndex;

  String payload;
  serializeJson(doc, payload);

  String body;
  Serial.println("[ENROLL] Requesting advance to next finger...");
  if (!httpPostJson(String(API_BASE_PATH) + "/advance-finger.php", payload, body)) {
    Serial.println("[ENROLL] POST advance-finger failed");
    return false;
  }

  StaticJsonDocument<256> resp;
  if (deserializeJson(resp, body)) {
    Serial.println("[ENROLL] Advance response parse failed");
    return false;
  }

  bool ok = resp["success"] | false;
  bool enrollmentComplete = resp["enrollment_complete"] | false;
  int nextFinger = resp["next_finger_index"] | 0;

  if (ok) {
    if (enrollmentComplete) {
      Serial.println("[ENROLL] All fingers enrolled, returning to IDLE");
    } else {
      Serial.print("[ENROLL] Advanced to finger ");
      Serial.println(nextFinger);
    }
  }

  return ok;
}

bool reportScanProgress(int studentId, int fingerIndex, int scanStep, int totalSteps) {
  return reportScanProgressWithStatus(studentId, fingerIndex, scanStep, totalSteps, "waiting", "");
}

bool reportScanProgressWithStatus(int studentId, int fingerIndex, int scanStep, int totalSteps, const char* uiStatus, const String& uiMessage) {
  StaticJsonDocument<128> doc;
  doc["student_id"] = studentId;
  doc["finger_index"] = fingerIndex;
  doc["scan_step"] = scanStep;
  doc["total_steps"] = totalSteps;
  doc["ui_status"] = uiStatus;
  if (uiMessage.length() > 0) {
    doc["ui_message"] = uiMessage;
  }

  String payload;
  serializeJson(doc, payload);

  String body;
  Serial.print("[ENROLL] Reporting scan progress: step ");
  Serial.print(scanStep);
  Serial.print(" of ");
  Serial.println(totalSteps);

  if (!httpPostJson(String(API_BASE_PATH) + "/scan-progress.php", payload, body)) {
    Serial.println("[ENROLL] POST scan-progress failed");
    return false;

  }

  return true;
}

bool reportEnrollmentProgressIfChanged(
  int studentId,
  int fingerIndex,
  int scanStep,
  int totalSteps,
  const char* uiStatus,
  const String& uiMessage,
  int& lastReportedScanStep,
  String& lastReportedUiStatus,
  String& lastReportedUiMessage
) {
  String normalizedStatus = uiStatus ? String(uiStatus) : String("waiting");
  if (lastReportedScanStep == scanStep && lastReportedUiStatus == normalizedStatus && lastReportedUiMessage == uiMessage) {
    return true;
  }

  if (!reportScanProgressWithStatus(studentId, fingerIndex, scanStep, totalSteps, uiStatus, uiMessage)) {
    return false;
  }

  lastReportedScanStep = scanStep;
  lastReportedUiStatus = normalizedStatus;
  lastReportedUiMessage = uiMessage;
  return true;
}

bool sendDeleteResult(int studentId, int sensorId, bool success, const String& errorMessage) {
  StaticJsonDocument<192> doc;
  doc["student_id"] = studentId;
  doc["sensor_id"] = sensorId;
  doc["success"] = success;
  if (!success && errorMessage.length() > 0) {
    doc["error_message"] = errorMessage;
  }

  String payload;
  serializeJson(doc, payload);

  String body;
  Serial.println("[DELETE] Posting delete result to server...");
  if (!httpPostJson(String(API_BASE_PATH) + "/delete-result.php", payload, body)) {
    Serial.println("[DELETE] POST delete-result failed");
    return false;
  }

  StaticJsonDocument<256> resp;
  if (deserializeJson(resp, body)) {
    Serial.println("[DELETE] Response parse failed");
    return false;
  }

  bool ok = resp["success"] | false;
  Serial.print("[DELETE] Result POST success=");
  Serial.println(ok ? "true" : "false");
  return ok;
}

bool deleteFingerprintModel(int sensorId, String& outError) {
  if (sensorId < 0 || sensorId > 127) {
    outError = "Invalid sensor_id";
    return false;
  }

  if (sensorId == 0) {
    for (int slot = 1; slot <= 127; slot++) {
      uint8_t clearStatus = finger.deleteModel(slot);
      if (!(clearStatus == FINGERPRINT_OK || clearStatus == FINGERPRINT_BADLOCATION || clearStatus == FINGERPRINT_FLASHERR)) {
        outError = "clearAll code=" + String((int)clearStatus);
        return false;
      }
      delay(5);
      yield();
    }

    return true;
  }

  uint8_t p = finger.deleteModel(sensorId);
  if (p == FINGERPRINT_OK || p == FINGERPRINT_BADLOCATION || p == FINGERPRINT_FLASHERR) {
    // Treat missing/invalid slot as non-blocking for retry cleanup.
    return true;
  }

  outError = "deleteModel code=" + String((int)p);
  return false;
}

bool sendAttendance(int sensorId) {
  StaticJsonDocument<192> doc;
  doc["sensor_id"] = sensorId;
  doc["device_id"] = DEVICE_ID;

  String payload;
  serializeJson(doc, payload);

  String body;
  Serial.println("[ATTEND] Posting attendance to server...");
  if (!httpPostJson(String(API_BASE_PATH) + "/attendance.php", payload, body)) {
    Serial.println("[ATTEND] POST failed");
    displayLCD("Not Found", "Try Again");
    return false;
  }

  StaticJsonDocument<256> resp;
  if (deserializeJson(resp, body)) {
    Serial.println("[ATTEND] JSON parse failed");
    displayLCD("Not Found", "Try Again");
    return false;
  }

  bool ok = resp["success"] | false;
  if (!ok) {
    const char* msg = resp["message"] | "Attendance err";
    Serial.print("[ATTEND] API error: ");
    Serial.println(msg);
    String msgText = String(msg);
    msgText.toLowerCase();

    if (String(msg) == "Not enrolled") {
      displayLCD("Not enrolled", "Try Again");
    } else if (msgText.indexOf("no schedule") >= 0) {
      displayLCD("No class today", "Attendance off");
    } else if (msgText.indexOf("not allowed") >= 0 || msgText.indexOf("outside") >= 0) {
      displayLCD("Outside schedule", "Try on class");
    } else {
      displayLCD("Not Found", "Try Again");
    }
    return false;
  }

  const char* type = resp["type"] | "";
  const char* studentName = resp["student_name"] | "John";
  bool duplicateScan = resp["duplicate"] | false;

  String timeValue = "";
  if (resp.containsKey("time")) {
    timeValue = String((const char*)resp["time"]);
  } else if (resp.containsKey("timestamp")) {
    String ts = String((const char*)resp["timestamp"]);
    int spacePos = ts.indexOf(' ');
    if (spacePos >= 0 && spacePos + 1 < ts.length()) {
      timeValue = ts.substring(spacePos + 1);
    }
  }

  if (timeValue.length() > 8) {
    timeValue = timeValue.substring(0, 8);
  }

  String actionText = String(type) == "IN" ? "Time IN" : "Time OUT";
  String displayLine1 = actionText + " " + timeValue;
  String displayLine2 = String(studentName);
  String serialActionText = String(type) == "IN" ? "Time in" : "Time out";

  Serial.print("[ATTEND] ");
  if (duplicateScan) {
    Serial.print("Already ");
    Serial.print(serialActionText);
    Serial.print(" [");
    Serial.print(timeValue);
    Serial.print("] ");
    Serial.println(studentName);
    displayLine1 = String("Already ") + (String(type) == "IN" ? "time in" : "time out");
    displayLine2 = String(studentName);
  } else {
    Serial.print(serialActionText);
    Serial.print(" [");
    Serial.print(timeValue);
    Serial.print("] ");
    Serial.println(studentName);
  }

  displayLCD(displayLine1, displayLine2);
  return true;
}

void setup() {
  Serial.begin(115200);
  delay(100);
  Serial.println("\n[CAMS] Boot");
  Serial.println("[SYSTEM] -------------------------------");
  Serial.print("[CONFIG] Device ID=");
  Serial.println(DEVICE_ID);
  Serial.print("[CONFIG] Server=");
  Serial.println("AUTO (local subnet scan)");
  Serial.print("[CONFIG] Server Port=");
  Serial.println(SERVER_PORT);
  Serial.print("[CONFIG] API Path=");
  Serial.println(API_BASE_PATH);
  Serial.print("[CONFIG] WiFi SSID=");
  Serial.println(WIFI_SSID);

  Wire.begin(I2C_SDA_PIN, I2C_SCL_PIN);
  lcd.init();
  lcd.backlight();
  initFeedbackHardware();
  displayLCD("Booting...", "Step1: WiFi");

  // Force immediate first attempts in loop() boot sequence.
  lastWifiAttempt = 0;
  lastServerCheckMs = 0;
  lastSensorInitAttemptMs = 0;
}

void loop() {
  updateFeedback();

  if (!bootReady) {
    if (WiFi.status() != WL_CONNECTED) {
      displayLCD("Booting...", "Step1: WiFi");
      connectWiFi();
      logSystemSummary();
      return;
    }

    if (!sensorReady) {
      displayLCD("Booting...", "Step2: Sensor");
      if (lastSensorInitAttemptMs == 0 || millis() - lastSensorInitAttemptMs >= SENSOR_RETRY_INTERVAL) {
        logInfo("SENSOR", "Initializing...");
        if (!initFingerprintSensor()) {
          displayLCD("Sensor error", "Retrying...");
          logSystemSummary();
          return;
        }
      }
      if (!sensorReady) {
        logSystemSummary();
        return;
      }
    }

    displayLCD("Booting...", "Step3: Server");
    if (serverHost.length() == 0) {
      discoverServerHost();
    }
    if (!checkServerReady()) {
      displayLCD("Server offline", "Retrying...");
      logSystemSummary();
      return;
    }

    bootReady = true;
    displayLCD("Boot complete", "System ready");
    logInfo("BOOT", "Ready (WiFi->Sensor->Server)");
  }

  if (currentMode != "ENROLL") {
    logSensorStatus();
    logSystemSummary();
  }

  if (WiFi.status() == WL_CONNECTED && !serverReady) {
    if (!checkServerReady()) {
      displayLCD("Server offline", "Check network");
      logSystemSummary();
      return;
    }
    if (currentMode != "ENROLL") {
      logSystemSummary();
    }
  }

  connectWiFi();

  if (WiFi.status() != WL_CONNECTED) {
    return;
  }

  if (!serverReady) {
    displayLCD("Server offline", "Wait scan");
    return;
  }

  DeviceCommand cmd = getCommand();

  if (cmd.valid) {
    currentMode = cmd.mode;
  }

  if (currentMode != "ENROLL" && millis() - lastModeLogMs > 3000) {
    lastModeLogMs = millis();
    Serial.print("[MODE] Current=");
    Serial.print(currentMode);
    if (!cmd.valid) {
      Serial.print(" (stale: command fetch failed)");
    }
    Serial.println();
  }

  if (cmd.valid && currentMode == "ENROLL") {
    logInfo("MODE", "REGISTRATION active");
    displayLCD("Reg mode", "Wait finger...");
    int sensorId = getNextAvailableID();
    if (sensorId <= 0) {
      Serial.println("[ENROLL] No free sensor ID");
      displayLCD("Not Found", "Try Again");
      return;
    }

    int enrollFingerIndex = cmd.fingerIndex > 0 ? cmd.fingerIndex : 1;
    bool enrolled = enrollFinger(sensorId, enrollFingerIndex, cmd.studentId);
    if (!enrolled) {
      String reason = lastEnrollFailureReason.length() > 0
        ? lastEnrollFailureReason
        : "Scanner enrollment failed (image/model/store)";
      sendEnrollFailure(cmd.studentId, enrollFingerIndex, reason);
      displayLCD("Not Found", "Try Again");
      return;
    }

    bool posted = sendEnrollResult(cmd.studentId, enrollFingerIndex, sensorId);
    if (posted) {
      lastEnrollCompleteMs = millis();
      displayLCD("Saved!", "ID:" + String(sensorId));

       advanceToNextFinger(cmd.studentId, enrollFingerIndex);
    } else {
      displayLCD("Not Found", "Try Again");
    }

    return;
  }

  if (cmd.valid && currentMode == "DELETE") {
    if (cmd.sensorId <= 0) {
      logInfo("DELETE", "Clearing all sensor templates");
      displayLCD("Resetting", "All fingerprints");
    } else {
      Serial.print("[DELETE] Deleting sensor_id=");
      Serial.println(cmd.sensorId);
      displayLCD("Deleting", "ID:" + String(cmd.sensorId));
    }

    String deleteError = "";
    bool deleted = deleteFingerprintModel(cmd.sensorId, deleteError);
    bool posted = sendDeleteResult(cmd.studentId, cmd.sensorId, deleted, deleteError);

    if (deleted && posted) {
      if (cmd.sensorId <= 0) {
        displayLCD("Reset done", "All cleared");
      } else {
        displayLCD("Delete done", "ID:" + String(cmd.sensorId));
      }
    } else {
      displayLCD("Delete failed", cmd.sensorId <= 0 ? "All fingerprints" : ("ID:" + String(cmd.sensorId)));
    }

    return;
  }

  if (!cmd.valid && currentMode == "ENROLL") {
    if (millis() - lastIdleModeLogMs > 10000) {
      lastIdleModeLogMs = millis();
      logInfo("MODE", "Registration mode, waiting for finger scan command...");
    }
    displayLCD("Reg mode", "Wait finger...");
    updateFeedback();
    return;
  }

   if (currentMode == "IDLE") {
    if (lastEnrollCompleteMs > 0 && (millis() - lastEnrollCompleteMs) < ENROLL_IDLE_COOLDOWN_MS) {
      displayLCD("Enroll done", "Wait finger...");
      return;
    }

    if (lastAttendanceCompleteMs > 0 && (millis() - lastAttendanceCompleteMs) < ATTEND_IDLE_COOLDOWN_MS) {
      displayLCD("Attendance saved", "Remove finger");
      return;
    }

    if (millis() - lastIdleModeLogMs > 3000) {
      lastIdleModeLogMs = millis();
      logInfo("MODE", "IDLE waiting for fingerprint");
    }
  }
  displayLCD("Scan Finger");
  updateFeedback();

  if (millis() - lastIdleScanMs < 150) {
    return;
  }
  lastIdleScanMs = millis();

  uint8_t p = finger.getImage();
  if (p == FINGERPRINT_NOFINGER) {
    return;
  }

  if (p != FINGERPRINT_OK) {
    if (millis() - lastScanErrorLogMs > 3000) {
      lastScanErrorLogMs = millis();
      Serial.print("[IDLE] getImage error code=");
      Serial.println(p);
    }
    return;
  }

  logInfo("IDLE", "Finger detected");

  p = finger.image2Tz();
  if (p != FINGERPRINT_OK) {
    Serial.print("[IDLE] image2Tz failed, code=");
    Serial.println(p);
    return;
  }

  p = finger.fingerFastSearch();
  if (p != FINGERPRINT_OK) {
    if (p == FINGERPRINT_NOTFOUND) {
      Serial.println("[IDLE] No match (finger not enrolled)");
      displayLCD("Not enrolled", "Try Again");
      waitFingerRemoved(2000);
    } else {
      Serial.print("[IDLE] Search failed, code=");
      Serial.println(p);
      displayLCD("Read error", "Try Again");
      waitFingerRemoved(1500);
    }
    return;
  }

  int matchedId = finger.fingerID;
  Serial.print("[IDLE] Match sensor_id=");
  Serial.println(matchedId);
  if (sendAttendance(matchedId)) {
    lastAttendanceCompleteMs = millis();
    waitFingerRemoved(4000);
  } else {
    // Avoid rapid reprocessing loops while finger remains on scanner.
    waitFingerRemoved(2500);
  }
}
