/**
 * ESP8266 I2C LCD Display - Sample Code
 * 
 * This example shows how to use a 16x2 LCD display connected via I2C to an ESP8266.
 * 
 * Wiring:
 * LCD I2C Module | ESP8266
 * VCC           | 5V or 3.3V (depending on module)
 * GND           | GND
 * SDA (Data)    | D2 (GPIO4)
 * SCL (Clock)   | D1 (GPIO5)
 * 
 * Default I2C Address: 0x27 (some modules use 0x3F, test to find yours)
 */

#include <Wire.h>
#include <LiquidCrystal_I2C.h>

// Define I2C pins (D1 = SCL, D2 = SDA)
#define I2C_SDA_PIN D2   // GPIO4
#define I2C_SCL_PIN D1   // GPIO5

// Create LCD object: address 0x27, 16 columns, 2 rows
LiquidCrystal_I2C lcd(0x27, 16, 2);

void setup() {
  Serial.begin(115200);
  delay(1000);
  
  Serial.println("\n\nESP8266 I2C LCD Test");
  Serial.println("=====================");
  
  // Initialize I2C with custom pins
  Wire.begin(I2C_SDA_PIN, I2C_SCL_PIN);
  delay(100);
  
  // Initialize LCD
  lcd.init();
  lcd.backlight();  // Turn on backlight (optional)
  
  Serial.println("LCD Initialized!");
  
  // Display welcome message
  lcd.print("ESP8266 Ready!");
  lcd.setCursor(0, 1);
  lcd.print("I2C LCD Test");
  
  delay(2000);
}

void loop() {
  // Example 1: Simple text display
  displayExample1();
  delay(3000);
  
  // Example 2: Moving text
  displayExample2();
  delay(3000);
  
  // Example 3: Custom characters
  displayExample3();
  delay(3000);
  
  // Example 4: Data display (counter)
  displayExample4();
  delay(3000);
}

/**
 * Example 1: Clear and display text
 */
void displayExample1() {
  lcd.clear();
  lcd.setCursor(0, 0);  // Column 0, Row 0
  lcd.print("Line 1: ");
  lcd.setCursor(0, 1);  // Column 0, Row 1
  lcd.print("Line 2");
  
  Serial.println("Example 1: Basic text");
}

/**
 * Example 2: Moving text animation
 */
void displayExample2() {
  String text = "Scrolling Text";
  
  // Scroll right
  for (int i = 0; i < 16; i++) {
    lcd.clear();
    lcd.setCursor(i, 0);
    lcd.print(text);
    delay(200);
  }
  
  // Scroll left
  for (int i = 16; i >= 0; i--) {
    lcd.clear();
    lcd.setCursor(i, 0);
    lcd.print(text);
    delay(200);
  }
  
  Serial.println("Example 2: Scrolling text");
}

/**
 * Example 3: Display time and status
 */
void displayExample3() {
  lcd.clear();
  
  // Top line: Time
  lcd.setCursor(0, 0);
  lcd.print("Time: ");
  unsigned long seconds = millis() / 1000;
  lcd.print(seconds);
  lcd.print("s");
  
  // Bottom line: Status
  lcd.setCursor(0, 1);
  lcd.print("Status: Running");
  
  Serial.println("Example 3: Time and status");
}

/**
 * Example 4: Counter display
 */
void displayExample4() {
  static int counter = 0;
  
  lcd.clear();
  lcd.setCursor(0, 0);
  lcd.print("Counter:");
  
  lcd.setCursor(0, 1);
  lcd.print(counter);
  lcd.print("   ");  // Clear rest of line
  
  counter++;
  if (counter > 9999) counter = 0;  // Reset after 9999
  
  Serial.print("Example 4: Counter = ");
  Serial.println(counter);
}

/**
 * Utility: Find I2C LCD address
 * Run this to scan and find your LCD's I2C address
 */
void scanI2CAddresses() {
  Serial.println("Scanning I2C addresses...");
  Serial.println("Address\tStatus");
  Serial.println("-------\t------");
  
  for (int address = 0x20; address < 0x7F; address++) {
    Wire.beginTransmission(address);
    int error = Wire.endTransmission();
    
    if (error == 0) {
      Serial.print("0x");
      Serial.print(address, HEX);
      Serial.println("\tFound!");
    }
  }
  
  Serial.println("Done scanning.");
}

/**
 * Helper: Display formatted text
 */
void displayFormatted(String line1, String line2) {
  lcd.clear();
  
  lcd.setCursor(0, 0);
  lcd.print(line1);
  
  lcd.setCursor(0, 1);
  lcd.print(line2);
}

/**
 * Helper: Clear line
 */
void clearLine(int line) {
  lcd.setCursor(0, line);
  lcd.print("                ");  // 16 spaces
}

/**
 * Helper: Display progress bar
 */
void displayProgressBar(int progress) {
  // progress: 0-100
  lcd.clear();
  
  lcd.setCursor(0, 0);
  lcd.print("Progress: ");
  lcd.print(progress);
  lcd.print("%");
  
  lcd.setCursor(0, 1);
  
  int bars = (progress * 16) / 100;  // 16 characters per line
  for (int i = 0; i < bars; i++) {
    lcd.write(255);  // Full block character
  }
  for (int i = bars; i < 16; i++) {
    lcd.print(" ");  // Empty space
  }
}

/*
 * ===============================================
 * ALTERNATIVE: Using rs, en, d4, d5, d6, d7 pins
 * (Not I2C, but useful reference)
 * ===============================================
 * 
 * #include <LiquidCrystal.h>
 * 
 * const int rs = D0;
 * const int en = D1;
 * const int d4 = D5;
 * const int d5 = D6;
 * const int d6 = D7;
 * const int d7 = D8;
 * 
 * LiquidCrystal lcd(rs, en, d4, d5, d6, d7);
 * 
 * void setup() {
 *   lcd.begin(16, 2);
 *   lcd.print("Hello, World!");
 * }
 */

/*
 * ===============================================
 * COMMON I2C LCD ADDRESSES
 * ===============================================
 * 0x27 - Most common (16 char models)
 * 0x3F - Some 20x4 models
 * 0x20 - Some blue backlight models
 * 
 * If unsure, run scanI2CAddresses() to find yours
 */

/*
 * ===============================================
 * LCD COMMANDS
 * ===============================================
 * 
 * lcd.init()              - Initialize LCD
 * lcd.backlight()         - Turn on backlight
 * lcd.noBacklight()       - Turn off backlight
 * lcd.clear()             - Clear display
 * lcd.setCursor(col, row) - Set cursor position
 * lcd.print(text)         - Print text
 * lcd.write(byte)         - Print custom character
 * lcd.blink()             - Enable blinking cursor
 * lcd.noBlink()           - Disable blinking cursor
 * lcd.display()           - Turn display on
 * lcd.noDisplay()         - Turn display off
 * lcd.scrollDisplayLeft() - Scroll left
 * lcd.scrollDisplayRight()- Scroll right
 * lcd.createChar(0, pattern) - Create custom char
 */
