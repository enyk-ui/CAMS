#include <Arduino.h>
#include <ESP8266WiFi.h>
#include <SoftwareSerial.h>
#include <Adafruit_Fingerprint.h>
#include <ArduinoJson.h>
#include <Wire.h>
#include <LiquidCrystal_I2C.h>

// ================= CONFIG =================

#define WIFI_SSID "Redmi Note 13"    
#define WIFI_PASSWORD "aaaaaaaa"  
#define SERVER_IP "10.18.239.46"   
#define SERVER_PORT 80
#define DEVICE_NAME "CAMS-Scanner-01"
#define API_BASE_PATH "/CAMS/api"

  #define SENSOR_RX_PIN D7
  #define SENSOR_TX_PIN D6
  #define SENSOR_BAUD_RATE 57600

#define I2C_SDA_PIN D2
#define I2C_SCL_PIN D1

#define BUZZER_PIN D5
#define LED_GREEN D0
#define LED_RED D3

#define DEBUG_BAUD 115200

// Using Serial.print and Serial.println directly

 
SoftwareSerial mySerial(SENSOR_RX_PIN, SENSOR_TX_PIN);
Adafruit_Fingerprint finger(&mySerial);
LiquidCrystal_I2C lcd(0x27, 16, 2);

 
unsigned long lastWiFiAttempt = 0;
unsigned long lastServerAttempt = 0;
unsigned long lastScan = 0;
unsigned long lastModeCheck = 0;
unsigned long lastHeartbeat = 0;
unsigned long lastHeartbeatDebug = 0;

String serverIP = SERVER_IP;  
String currentMode = "attendance";  

String registrationId = "";  
int currentFinger = 1;      
int currentScan = 0;          
int totalFingers = 1;       
String lastCompletedRegKey = "";

 
void beep(int d=150){
  digitalWrite(BUZZER_PIN,HIGH); delay(d);
  digitalWrite(BUZZER_PIN,LOW);
}

void lcdShow(String a,String b){
  lcd.clear();
  lcd.setCursor(0,0); lcd.print(a);
  lcd.setCursor(0,1); lcd.print(b);
}

 
void connectWiFi(){
  if(WiFi.status()==WL_CONNECTED) return;

  if(millis()-lastWiFiAttempt < 10000) return;

  lastWiFiAttempt = millis();

  Serial.println("[WiFi] Connecting to: " + String(WIFI_SSID));
  lcdShow("Connecting WiFi", String(WIFI_SSID));
  
  WiFi.begin(WIFI_SSID, WIFI_PASSWORD);

  int c=0;
  while(WiFi.status()!=WL_CONNECTED && c<20){
    delay(500);
    Serial.print(".");
    
    // Update LCD with progress dots
    String progress = "";
    for(int i = 0; i < (c % 4) + 1; i++) {
      progress += ".";
    }
    lcdShow("Connecting WiFi", String(WIFI_SSID) + progress);
    
    c++;
  }

  if(WiFi.status()==WL_CONNECTED){
    Serial.println("\n[WiFi] ✅ Connected!");
    Serial.print("[WiFi] Local IP: "); Serial.println(WiFi.localIP());

    lcdShow("WiFi Connected", WiFi.localIP().toString());
    
    // Success feedback
    beep(100);
    digitalWrite(LED_GREEN,HIGH); delay(300); digitalWrite(LED_GREEN,LOW);
    delay(1500); // Show IP for 1.5 seconds
  }else{
    Serial.println("\n[WiFi] ❌ Connection failed!");
    lcdShow("WiFi Failed", "Retrying...");
    
    // Error feedback
    digitalWrite(LED_RED,HIGH); delay(200); digitalWrite(LED_RED,LOW);
    delay(1000);
  }
}

// ================= SERVER =================

bool checkServer(){
  if(serverIP == "") {
    Serial.println("[Server] No server IP configured");
    return false;
  }
  if(WiFi.status()!=WL_CONNECTED) return false;

  Serial.print("[Server] Testing connection to: "); Serial.println(serverIP);
  lcdShow("Testing Server", serverIP);

  WiFiClient client;
  client.setTimeout(3000); // 3 second timeout
  
  if(!client.connect(serverIP.c_str(), SERVER_PORT)){
    Serial.println("[Server] ❌ Connection FAILED");
    lcdShow("Server Offline", serverIP);
    return false;
  }

  Serial.println("[Server] ✅ Connected OK");
  client.stop();
  return true;
}

void autoFindServer(){
  if(serverIP == "") return;

  if(millis()-lastServerAttempt < 10000) return;  
  lastServerAttempt = millis();

  Serial.println("[Server] Auto checking server...");
  lcdShow("Checking Server", serverIP);

  if(checkServer()){
    Serial.println("[Server] 🟢 ONLINE");
    lcdShow("Server Online", serverIP);
    
    // Success beep
    beep(50);
    digitalWrite(LED_GREEN, HIGH); delay(100); digitalWrite(LED_GREEN, LOW);
    delay(1000); 
  }else{
    Serial.println("[Server] 🔴 OFFLINE");
    lcdShow("Server Offline", "Retrying...");
    
    // Error indication
    digitalWrite(LED_RED, HIGH); delay(100); digitalWrite(LED_RED, LOW);
    delay(1500);
  }
}

// ================= SENSOR =================

bool initSensor(){
  Serial.println("[Sensor] Initializing...");
  finger.begin(SENSOR_BAUD_RATE);
  if(finger.verifyPassword()){
    Serial.println("[Sensor] Found!");
    return true;
  }
  Serial.println("[Sensor] NOT FOUND!");
  return false;
}

// ================= MODE CHECK =================

void checkServerMode(){
  // Check server mode every 3 seconds
  if(millis() - lastModeCheck < 3000) return;
  
  lastModeCheck = millis();

  if(WiFi.status() != WL_CONNECTED) return;
  if(serverIP == "") return;

  WiFiClient client;
  client.setTimeout(3000);
  
  if(!client.connect(serverIP.c_str(), SERVER_PORT)) {
    return;
  }

  // Request current mode from server
  client.print("GET ");
  client.print(API_BASE_PATH);
  client.println("/get_mode.php HTTP/1.1");
  client.print("Host: "); client.println(serverIP);
  client.println("Connection: close");
  client.println();

  String response = "";
  unsigned long timeout = millis();

  while(client.connected() && millis() - timeout < 3000) {
    if(client.available()) {
      String line = client.readStringUntil('\n');
      if(line.startsWith("{")) {
        response = line;
        break;
      }
    }
  }

  client.stop();

  if(response.length() == 0) return;
 
  StaticJsonDocument<300> doc;
  String jsonBody = extractJsonBody(response);
  if (jsonBody.length() == 0 || deserializeJson(doc, jsonBody)) {
    Serial.println("[Mode] Failed to parse mode response");
    return;
  }

  if(doc["success"]) {
    String newMode = doc["mode"].as<String>();
    
    // Check for registration details
    if(newMode == "registration" && doc.containsKey("registration_id")) {
      registrationId = doc["registration_id"].as<String>();
      currentFinger = doc["finger_number"] | 1;
      currentScan = doc["scan_number"] | 0;
      totalFingers = doc["total_fingers"] | 1;
      
      Serial.println("[Reg] ID: " + registrationId);
      Serial.println("[Reg] Finger " + String(currentFinger) + " of " + String(totalFingers));
    }
    
    // Mode changed?
    if(newMode != currentMode && (newMode == "registration" || newMode == "attendance")) {
      currentMode = newMode;
      
      Serial.println("[Mode] Changed to: " + currentMode);
      
      if(currentMode == "registration") {
        lcdShow("REG BUSY", "No attendance");
        beep(100); delay(100); beep(100);
        digitalWrite(LED_GREEN, HIGH); delay(300); digitalWrite(LED_GREEN, LOW);
        delay(1000);
      } else {
        lcdShow("ATTENDANCE", "Mode Active");
        beep(150);
        delay(1000);
        // Reset registration tracking
        registrationId = "";
        currentFinger = 1;
        currentScan = 0;
        lastCompletedRegKey = "";
        lcdShow("Ready to Scan", "attendance");
      }
    } else if(currentMode == "registration" && registrationId != "") {
      // Update LCD with current scan progress
      static unsigned long lastProgressUpdate = 0;
      if(millis() - lastProgressUpdate > 5000) {
        lcdShow("REG BUSY", "Finger " + String(currentFinger) + "/" + String(totalFingers));
        lastProgressUpdate = millis();
      }
    }
  }
}

// ================= HEARTBEAT =================

void sendHeartbeat(){
  // Send heartbeat every 5 seconds so web UI can show scanner online status.
  if(millis() - lastHeartbeat < 5000) return;
  lastHeartbeat = millis();

  if(WiFi.status() != WL_CONNECTED) return;
  if(serverIP == "") return;

  WiFiClient client;
  client.setTimeout(1500);

  if(!client.connect(serverIP.c_str(), SERVER_PORT)) {
    if(millis() - lastHeartbeatDebug > 30000) {
      Serial.println("[Heartbeat] Failed to connect to server");
      lastHeartbeatDebug = millis();
    }
    return;
  }

  client.print("GET ");
  client.print(API_BASE_PATH);
  client.println("/scanner_status.php?action=heartbeat HTTP/1.1");
  client.print("Host: "); client.println(serverIP);
  client.println("Connection: close");
  client.println();

  // Drain response quickly to close cleanly without heavy parsing.
  unsigned long timeout = millis();
  while(client.connected() && millis() - timeout < 1500) {
    while(client.available()) {
      client.read();
      timeout = millis();
    }
    yield();
  }

  client.stop();

  if(millis() - lastHeartbeatDebug > 30000) {
    Serial.println("[Heartbeat] Sent successfully");
    lastHeartbeatDebug = millis();
  }
}

int findAvailableTemplateId() {
  // Search sensor slots and return the first free template slot (1-127).
  for (int id = 1; id <= 127; id++) {
    uint8_t p = finger.loadModel(id);
    if (p != FINGERPRINT_OK) {
      return id;
    }
    yield();
  }
  return -1;
}

bool waitForFingerRelease(unsigned long timeoutMs) {
  unsigned long start = millis();
  while (millis() - start < timeoutMs) {
    int r = finger.getImage();
    if (r == FINGERPRINT_NOFINGER) {
      return true;
    }
    delay(80);
    yield();
  }
  return false;
}

bool captureSecondImageToBuffer2(unsigned long timeoutMs) {
  unsigned long start = millis();
  while (millis() - start < timeoutMs) {
    int r = finger.getImage();
    if (r == FINGERPRINT_OK) {
      return finger.image2Tz(2) == FINGERPRINT_OK;
    }
    delay(120);
    yield();
  }
  return false;
}

String extractJsonBody(const String& response) {
  int jsonStart = response.indexOf('{');
  if (jsonStart < 0) {
    return "";
  }

  int jsonEnd = response.lastIndexOf('}');
  if (jsonEnd < jsonStart) {
    return response.substring(jsonStart);
  }

  return response.substring(jsonStart, jsonEnd + 1);
}

// ================= SEND =================

String sendToServer(uint16_t id){

  Serial.print("[SEND] Finger ID: "); Serial.println(id);
  Serial.print("[SEND] Mode: "); Serial.println(currentMode);

  if(WiFi.status()!=WL_CONNECTED) return "No WiFi";
  if(!checkServer()) return "Server Down";

  WiFiClient client;
  if(!client.connect(serverIP.c_str(), SERVER_PORT)){
    return "Connect Fail";
  }

  StaticJsonDocument<300> doc;
  doc["sensor_id"] = String(id);
  doc["device"] = DEVICE_NAME;
  doc["mode"] = currentMode;
  
  // Add registration details if in registration mode
  if(currentMode == "registration") {
    doc["registration_id"] = registrationId;
    doc["finger_number"] = currentFinger;
    doc["scan_number"] = currentScan;
    doc["total_fingers"] = totalFingers;
  }

  String payload;
  serializeJson(doc,payload);

  Serial.print("[SEND] Payload: "); Serial.println(payload);

  client.print("POST ");
  client.print(API_BASE_PATH);
  client.println("/scan.php HTTP/1.1");
  client.print("Host: "); client.println(serverIP);
  client.println("Content-Type: application/json");
  client.print("Content-Length: "); client.println(payload.length());
  client.println();
  client.println(payload);

  unsigned long t=millis();
  while(client.connected() && millis()-t<5000){
    if(client.available()){
      String response = client.readString();
      String jsonBody = extractJsonBody(response);

      // Parse JSON response to get lcd_display
      StaticJsonDocument<300> responseDoc;
      if (jsonBody.length() == 0 || deserializeJson(responseDoc, jsonBody)) {
        Serial.println("[SEND] Could not parse server response");
        Serial.print("[SEND] Raw response: ");
        Serial.println(response);
        return "Server Parse Error";
      }

      if(responseDoc["success"]) {
        String lcdDisplay = responseDoc["lcd_display"];
        return "OK:" + lcdDisplay;
      } else {
        String message = responseDoc["message"];
        return "ERROR:" + message;
      }
    }
  }

  return "No Response";
}

// ================= SCAN =================

void scanFinger(){
  static unsigned long lastDebugOutput = 0;
  
  // Scan delay to prevent multiple rapid scans
  if(millis()-lastScan < 1500) return;

  // Wait for finger on sensor
  int result = finger.getImage();
  if(result == FINGERPRINT_PACKETRECIEVEERR) {
    delay(50);
    result = finger.getImage();
  }
  
  // Debug output every 10 seconds to show we're checking for fingerprints
  if(millis() - lastDebugOutput > 10000) {
    Serial.println("[Scan] Waiting for fingerprint... (place finger on sensor)");
    lastDebugOutput = millis();
  }
  
  if(result != FINGERPRINT_OK) {
    // Show specific error codes to help diagnose sensor issues
    if(result != FINGERPRINT_NOFINGER) {  // Don't spam "no finger" messages
      Serial.print("[Scan] Sensor status: ");
      switch(result) {
        case FINGERPRINT_NOFINGER: Serial.println("No finger detected"); break;
        case FINGERPRINT_PACKETRECIEVEERR: Serial.println("Communication error"); break;
        case FINGERPRINT_IMAGEFAIL: Serial.println("Imaging error"); break;
        default: Serial.println("Error code: " + String(result)); break;
      }
    }
    return;
  }
  
  Serial.println("[Scan] ✓ Finger detected on sensor!");

  if(currentMode == "registration") {
    lcdShow("Finger " + String(currentFinger), "First Capture");
  } else {
    lcdShow("Processing","Fingerprint...");
  }

  // Convert image to template
  if(finger.image2Tz()!=FINGERPRINT_OK){
    lcdShow("Read Error","Try Again");
    digitalWrite(LED_RED,HIGH);
    beep(100); delay(100); beep(100);
    delay(1500);
    digitalWrite(LED_RED,LOW);
    lcdShow("Ready to Scan", currentMode);
    return;
  }

  // In REGISTRATION mode - enroll and store locally, then send real sensor ID.
  if(currentMode == "registration") {
    if(registrationId == "") {
      lcdShow("REG BUSY", "Wait session");
      delay(1000);
      lastScan = millis();
      return;
    }

    String regKey = registrationId + "-" + String(currentFinger);
    if(lastCompletedRegKey == regKey) {
      // Prevent duplicate enroll for the same requested finger while waiting for next mode/state update.
      lcdShow("Finger Saved", "Waiting Server");
      delay(700);
      lastScan = millis();
      return;
    }

    int templateId = findAvailableTemplateId();
    if(templateId < 1) {
      lcdShow("Sensor Full", "Delete old IDs");
      digitalWrite(LED_RED,HIGH); beep(400); delay(1200); digitalWrite(LED_RED,LOW);
      lastScan = millis();
      return;
    }

    Serial.println("[Reg] Using sensor slot ID: " + String(templateId));

    lcdShow("Remove Finger", "Then place again");
    bool secondBufferReady = false;

    if(waitForFingerRelease(5000)) {
      lcdShow("Same Finger", "Second Capture");
      secondBufferReady = captureSecondImageToBuffer2(12000);
      if(!secondBufferReady) {
        Serial.println("[Reg] Second capture timeout, using first capture fallback");
      }
    } else {
      Serial.println("[Reg] Finger not removed in time, using first capture fallback");
    }

    if(!secondBufferReady) {
      // Fallback path: duplicate first processed image into buffer 2 to avoid dead-end timeout.
      if(finger.image2Tz(2) == FINGERPRINT_OK) {
        secondBufferReady = true;
      }
    }

    if(!secondBufferReady) {
      lcdShow("Read Error", "Try Again");
      digitalWrite(LED_RED,HIGH); beep(100); delay(100); beep(100); delay(900); digitalWrite(LED_RED,LOW);
      Serial.println("[Reg] Failed to prepare second template buffer");
      lastScan = millis();
      return;
    }

    if(finger.createModel()!=FINGERPRINT_OK) {
      lcdShow("Model Error", "Try Again");
      digitalWrite(LED_RED,HIGH); beep(300); delay(900); digitalWrite(LED_RED,LOW);
      lastScan = millis();
      return;
    }

    if(finger.storeModel(templateId)!=FINGERPRINT_OK) {
      lcdShow("Store Failed", "Try Again");
      digitalWrite(LED_RED,HIGH); beep(400); delay(900); digitalWrite(LED_RED,LOW);
      lastScan = millis();
      return;
    }

    Serial.println("[Reg] Stored template ID: " + String(templateId));
    lcdShow("Stored ID", String(templateId));
    Serial.println("[Reg] Sending enrollment to API...");

    String resp = sendToServer((uint16_t)templateId);
    Serial.println("[Reg] API response: " + resp);

    if(resp.indexOf("OK:")>=0){
      String displayMsg = resp.substring(3);

      digitalWrite(LED_GREEN,HIGH);
      beep(150);
      delay(500);
      digitalWrite(LED_GREEN,LOW);

      lastCompletedRegKey = regKey;

      if(displayMsg.indexOf("Complete") >= 0 || displayMsg.indexOf("Done") >= 0) {
        lcdShow("Registration", "Complete!");
        beep(200); delay(100); beep(200); delay(100); beep(200);
        delay(1800);
      } else {
        lcdShow(displayMsg, "ID " + String(templateId));
        delay(1200);
      }

      lcdShow("Ready to Scan", "Next Finger");
    } else {
      // Roll back local slot if server rejects mapping.
      finger.deleteModel(templateId);
      lcdShow("Server Reject", "Retry Finger");
      digitalWrite(LED_RED,HIGH);
      beep(400);
      delay(1300);
      digitalWrite(LED_RED,LOW);
      Serial.println("[Reg] Server rejected enrollment: " + resp);
    }
    
    lastScan = millis();
    return;
  }

  // In ATTENDANCE mode - search for fingerprint
  if(finger.fingerFastSearch()!=FINGERPRINT_OK){
    lcdShow("Not Found","Unknown Finger");
    digitalWrite(LED_RED,HIGH);
    beep(400);
    delay(2000);
    digitalWrite(LED_RED,LOW);
    lastScan = millis();
    lcdShow("Ready to Scan", currentMode);
    return;
  }

  // Fingerprint found - get ID
  uint16_t id = finger.fingerID;
  
  Serial.print("[Scan] Found ID: "); Serial.println(id);
  lcdShow("ID: " + String(id), "Sending...");

  // Send to server
  String resp = sendToServer(id);

  // Handle server response
  if(resp.indexOf("OK:")>=0){
    String displayMsg = resp.substring(3);
    lcdShow("Success", displayMsg);
    
    digitalWrite(LED_GREEN,HIGH);
    beep(200); delay(150); beep(200);
    delay(2000);
    digitalWrite(LED_GREEN,LOW);
  } else if(resp.indexOf("ERROR:")>=0) {
    String errorMsg = resp.substring(6);
    lcdShow("Error", errorMsg);
    digitalWrite(LED_RED,HIGH);
    beep(500);
    delay(2000);
    digitalWrite(LED_RED,LOW);
  } else {
    lcdShow("Server Error", resp.length() > 16 ? resp.substring(0,16) : resp);
    digitalWrite(LED_RED,HIGH);
    beep(500);
    delay(2000);
    digitalWrite(LED_RED,LOW);
  }

  lastScan = millis();
  lcdShow("Ready to Scan", currentMode);
}

// ================= SETUP =================

void setup(){
  Serial.begin(DEBUG_BAUD);
  delay(1000); // Give serial time to initialize
  
  Serial.println("\n=================================");
  Serial.println("🚀 CAMS Fingerprint Scanner v4.0");
  Serial.println("   Simplified: Scan & Send Only");
  Serial.println("=================================");
  Serial.println("[Boot] System starting...");
  Serial.println("[Config] Server IP: " + String(SERVER_IP));

  pinMode(BUZZER_PIN,OUTPUT);
  pinMode(LED_GREEN,OUTPUT);
  pinMode(LED_RED,OUTPUT);

  Wire.begin(I2C_SDA_PIN, I2C_SCL_PIN);
  lcd.init();
  lcd.backlight();

  lcdShow("CAMS v4.0", "Booting...");
  Serial.println("[Setup] Hardware initialized");

  // Test hardware with LED sequence
  digitalWrite(LED_GREEN, HIGH); delay(200); digitalWrite(LED_GREEN, LOW);
  digitalWrite(LED_RED, HIGH); delay(200); digitalWrite(LED_RED, LOW);
  beep(100);

  if(!initSensor()){
    Serial.println("[Setup] ❌ Sensor initialization FAILED");
    Serial.println("[Setup] Check fingerprint sensor wiring:");
    Serial.println("[Setup] - VCC → 3.3V");
    Serial.println("[Setup] - GND → GND"); 
    Serial.println("[Setup] - TX → D7");
    Serial.println("[Setup] - RX → D6");
    lcdShow("Sensor Error", "Check Wiring");
    while(1) {
      digitalWrite(LED_RED, HIGH); delay(500);
      digitalWrite(LED_RED, LOW); delay(500);
    }
  }

  Serial.println("[Setup] ✅ Fingerprint sensor OK");
  lcdShow("Sensor OK", "Connecting...");
  delay(1000);

  // Connect to WiFi
  Serial.println("[Setup] Connecting to WiFi: " + String(WIFI_SSID));
  lcdShow("Connecting WiFi", String(WIFI_SSID));
  
  WiFi.begin(WIFI_SSID, WIFI_PASSWORD);
  
  int wifiAttempts = 0;
  while(WiFi.status() != WL_CONNECTED && wifiAttempts < 30) {
    delay(500);
    Serial.print(".");
    
    String progress = "";
    for(int i = 0; i < (wifiAttempts % 4) + 1; i++) progress += ".";
    lcdShow("Connecting WiFi", String(WIFI_SSID) + progress);
    
    wifiAttempts++;
  }
  
  if(WiFi.status() == WL_CONNECTED) {
    Serial.println("\n[Setup] ✅ WiFi connected!");
    Serial.print("[Setup] Local IP: "); Serial.println(WiFi.localIP());
    lcdShow("WiFi Connected", WiFi.localIP().toString());
    beep(100);
    delay(1500);
    
    // Test server connection
    Serial.println("[Setup] Testing server connection to: " + serverIP);
    lcdShow("Testing Server", serverIP);
    
    if(checkServer()) {
      Serial.println("[Setup] ✅ Server is ONLINE");
      lcdShow("Server Online", serverIP);
      beep(100); delay(100); beep(100);
    } else {
      Serial.println("[Setup] ⚠️  Server is OFFLINE - will retry");
      lcdShow("Server Offline", "Will retry...");
    }
    delay(1500);
  } else {
    Serial.println("\n[Setup] ❌ WiFi connection failed!");
    lcdShow("WiFi Failed", "Check Settings");
    delay(2000);
  }

  Serial.println("[Setup] ");
  Serial.println("[Setup] 🎯 System ready!");
  Serial.println("[Setup] WiFi SSID: " + String(WIFI_SSID));
  Serial.println("[Setup] Server IP: " + serverIP);
  Serial.println("[Setup] Device: " + String(DEVICE_NAME));
  Serial.println("[Setup] Mode: Scan & Send to Server");
  Serial.println("[Setup] ");

  lcdShow("Ready to Scan", serverIP);
  
  // Success startup sequence
  beep(100); delay(100); 
  beep(100); delay(100);
  beep(200);
  
  digitalWrite(LED_GREEN, HIGH); delay(500); digitalWrite(LED_GREEN, LOW);
}

// ================= LOOP =================

void loop(){
  static unsigned long lastStatusReport = 0;
  static unsigned long lastScanDebug = 0;
  static bool firstLoop = true;
  
  if(firstLoop) {
    Serial.println("[Loop] 🔄 Main loop started - Ready for fingerprint scanning");
    Serial.println("[Loop] Place your finger on the sensor to test...");
    lcdShow("Ready to Scan", currentMode);
    firstLoop = false;
  }
  
  // Debug: Show that scan loop is running every 30 seconds
  if(millis() - lastScanDebug > 30000) {
    Serial.println("[Loop] ✓ Scan loop active - Mode: " + currentMode);
    lastScanDebug = millis();
  }

  // Report status every 60 seconds
  if(millis() - lastStatusReport > 60000) {
    Serial.println("[Status] WiFi: " + String(WiFi.status() == WL_CONNECTED ? "Connected" : "Disconnected"));
    Serial.println("[Status] Server: " + serverIP);
    Serial.println("[Status] Mode: " + currentMode);
    Serial.println("[Status] Free Memory: " + String(ESP.getFreeHeap()) + " bytes");
    lastStatusReport = millis();
  }

  connectWiFi();
  autoFindServer();
  checkServerMode();  // Check if registration or attendance mode
  sendHeartbeat();    // Keep scanner status online in web UI
  scanFinger();
  yield();
}