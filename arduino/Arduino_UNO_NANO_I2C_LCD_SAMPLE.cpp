/**
 * Arduino Uno/Nano I2C LCD Display - Sample Code
 * 
 * This example shows how to use a 16x2 LCD display connected via I2C 
 * to an Arduino Uno or Nano.
 * 
 * Wiring for Arduino Uno/Nano:
 * LCD I2C Module | Arduino
 * VCC           | 5V
 * GND           | GND
 * SDA (Data)    | A4 (Analog pin 4)
 * SCL (Clock)   | A5 (Analog pin 5)
 * 
 * Default I2C Address: 0x27 (some modules use 0x3F)
 */

#include <Wire.h>
#include <LiquidCrystal_I2C.h>

// Create LCD object: address 0x27, 16 columns, 2 rows
// Note: Uno/Nano automatically use A4 (SDA) and A5 (SCL) for Wire library
LiquidCrystal_I2C lcd(0x27, 16, 2);

void setup() {
  Serial.begin(9600);
  delay(1000);
  
  Serial.println("\n\nArduino Uno/Nano I2C LCD Test");
  Serial.println("==============================");
  
  // Initialize I2C (Wire.begin() with no params uses A4/A5 on Uno/Nano)
  Wire.begin();
  delay(100);
  
  // Initialize LCD
  lcd.init();
  lcd.backlight();  // Turn on backlight
  
  Serial.println("LCD Initialized!");
  
  // Display welcome message
  lcd.print("Arduino Ready!");
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
  
  // Example 3: Display time and status
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
  lcd.print("Line 1");
  lcd.setCursor(0, 1);  // Column 0, Row 1
  lcd.print("Line 2");
  
  Serial.println("Example 1: Basic text");
}

/**
 * Example 2: Moving text animation
 */
void displayExample2() {
  String text = "Scroll";
  
  // Scroll right
  for (int i = 0; i < 10; i++) {
    lcd.clear();
    lcd.setCursor(i, 0);
    lcd.print(text);
    delay(150);
  }
  
  // Scroll left
  for (int i = 10; i >= 0; i--) {
    lcd.clear();
    lcd.setCursor(i, 0);
    lcd.print(text);
    delay(150);
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
  lcd.print("Status: OK");
  
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
 * PIN DEFINITIONS BY BOARD
 * ===============================================
 * 
 * Arduino Uno/Nano:
 *   SDA → A4 (Analog pin 4)
 *   SCL → A5 (Analog pin 5)
 *   Wire.begin() uses A4/A5 automatically
 * 
 * Arduino Mega 2560:
 *   SDA → 20
 *   SCL → 21
 *   Wire.begin() uses 20/21 automatically
 * 
 * Arduino Leonardo:
 *   SDA → 2
 *   SCL → 3
 *   Wire.begin() uses 2/3 automatically
 * 
 * *** DO NOT use Wire.begin(pin1, pin2) on AVR boards ***
 * The I2C pins are fixed and cannot be changed.
 */

/*
 * ===============================================
 * COMMON I2C LCD ADDRESSES
 * ===============================================
 * 0x27 - Most common (16x2 models)
 * 0x3F - Some 20x4 models
 * 0x20 - Some older/blue backlight models
 * 
 * If unsure, run scanI2CAddresses() to find yours
 */

/*
 * ===============================================
 * INSTALLATION
 * ===============================================
 * 
 * In Arduino IDE:
 * 1. Go to Sketch → Include Library → Manage Libraries
 * 2. Search for "LiquidCrystal I2C"
 * 3. Install by Frank de Brabander
 * 4. Also install "Wire" (usually included with core)
 */
