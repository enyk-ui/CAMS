#include <Arduino.h>
#include <Wire.h>
#include <LiquidCrystal_I2C.h>

// ESP8266 NodeMCU pins used in your CAMS sketch.
#define I2C_SDA_PIN D3
#define I2C_SCL_PIN D4

#define LCD_COLS 16
#define LCD_ROWS 2

// Common PCF8574 LCD addresses.
#define LCD_ADDR_PRIMARY 0x27
#define LCD_ADDR_FALLBACK 0x3F

LiquidCrystal_I2C lcdPrimary(LCD_ADDR_PRIMARY, LCD_COLS, LCD_ROWS);
LiquidCrystal_I2C lcdFallback(LCD_ADDR_FALLBACK, LCD_COLS, LCD_ROWS);
LiquidCrystal_I2C* lcd = nullptr;

void showScrollingLine(uint8_t row, const String& message, uint16_t stepDelayMs) {
  if (!lcd) {
    return;
  }

  String padded = "                " + message + "                ";
  int maxStart = padded.length() - LCD_COLS;
  if (maxStart < 0) {
    maxStart = 0;
  }

  for (int start = 0; start <= maxStart; start++) {
    lcd->setCursor(0, row);
    lcd->print(padded.substring(start, start + LCD_COLS));
    delay(stepDelayMs);
    yield();
  }
}

void showScrollingDemo() {
  if (!lcd) {
    return;
  }

  lcd->clear();
  printCentered(0, "Scroll Test");
  showScrollingLine(1, "CAMS LCD scrolling text demo", 180);

  lcd->clear();
  printCentered(0, "Bidirectional");

  String msg = "ESP8266 + I2C LCD";
  String padded = "                " + msg + "                ";
  int maxStart = padded.length() - LCD_COLS;
  if (maxStart < 0) {
    maxStart = 0;
  }

  for (int start = maxStart; start >= 0; start--) {
    lcd->setCursor(0, 1);
    lcd->print(padded.substring(start, start + LCD_COLS));
    delay(140);
    yield();
  }
}

bool i2cDevicePresent(uint8_t address) {
  Wire.beginTransmission(address);
  return Wire.endTransmission() == 0;
}

bool initLcdAutoAddress() {
  if (i2cDevicePresent(LCD_ADDR_PRIMARY)) {
    lcd = &lcdPrimary;
    lcd->init();
    lcd->backlight();
    return true;
  }

  if (i2cDevicePresent(LCD_ADDR_FALLBACK)) {
    lcd = &lcdFallback;
    lcd->init();
    lcd->backlight();
    return true;
  }

  return false;
}

void printCentered(uint8_t row, const String& text) {
  if (!lcd) {
    return;
  }

  String out = text;
  if (out.length() > LCD_COLS) {
    out = out.substring(0, LCD_COLS);
  }

  int startCol = (LCD_COLS - out.length()) / 2;
  if (startCol < 0) {
    startCol = 0;
  }

  lcd->setCursor(0, row);
  lcd->print("                ");
  lcd->setCursor(startCol, row);
  lcd->print(out);
}

void showI2CScanSummary() {
  Serial.println("[I2C] Scanning 0x03..0x77");
  int found = 0;

  for (uint8_t address = 0x03; address < 0x78; address++) {
    if (i2cDevicePresent(address)) {
      Serial.print("[I2C] Found at 0x");
      Serial.println(address, HEX);
      found++;
    }
    delay(2);
    yield();
  }

  if (found == 0) {
    Serial.println("[I2C] No devices found");
  }
}

void setup() {
  Serial.begin(115200);
  delay(200);

  Serial.println();
  Serial.println("[LCD TEST] Boot");
  Serial.println("[LCD TEST] SDA=D3, SCL=D4");

  Wire.begin(I2C_SDA_PIN, I2C_SCL_PIN);
  delay(100);

  showI2CScanSummary();

  if (!initLcdAutoAddress()) {
    Serial.println("[LCD TEST] LCD not found at 0x27 or 0x3F");
    return;
  }

  Serial.print("[LCD TEST] LCD ready at 0x");
  if (lcd == &lcdPrimary) {
    Serial.println(LCD_ADDR_PRIMARY, HEX);
  } else {
    Serial.println(LCD_ADDR_FALLBACK, HEX);
  }

  lcd->clear();
  printCentered(0, "LCD TEST MODE");
  printCentered(1, "Starting...");
  delay(1200);
}

void loop() {
  if (!lcd) {
    delay(1500);
    Serial.println("[LCD TEST] Waiting, LCD still not detected");
    return;
  }

  static uint32_t counter = 0;

  lcd->clear();
  printCentered(0, "LCD OK");
  printCentered(1, "Counter " + String(counter));
  Serial.print("[LCD TEST] Tick ");
  Serial.println(counter);
  counter++;
  delay(1200);

  lcd->clear();
  printCentered(0, "Backlight Test");
  printCentered(1, "ON");
  delay(700);

  lcd->noBacklight();
  delay(250);
  lcd->backlight();

  lcd->clear();
  printCentered(0, "Chars Test");
  lcd->setCursor(0, 1);
  lcd->print("0123456789ABCDEF");
  delay(1200);

  showScrollingDemo();
}
