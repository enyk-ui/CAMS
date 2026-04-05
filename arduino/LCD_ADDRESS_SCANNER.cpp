/**
 * I2C LCD Address Scanner & Diagnostic
 * 
 * This sketch will:
 * 1. Find your LCD's I2C address
 * 2. Test communication with the LCD
 * 3. Try to display text on the found address
 * 
 * Upload this first to identify your LCD address!
 */

#include <Wire.h>
#include <LiquidCrystal_I2C.h>

void setup() {
  Serial.begin(9600);
  delay(2000);
  
  Serial.println("\n\n====================================");
  Serial.println("  I2C LCD Address Scanner");
  Serial.println("====================================\n");
  
  Wire.begin();
  
  // Step 1: Scan for I2C devices
  Serial.println("Step 1: Scanning for I2C devices...\n");
  scanI2CDevices();
  
  delay(2000);
  
  // Step 2: Try to initialize LCD on common addresses
  Serial.println("\n\nStep 2: Testing common LCD addresses...\n");
  testCommonAddresses();
}

void loop() {
  delay(1000);
}

/**
 * Scan all I2C addresses and report found devices
 */
void scanI2CDevices() {
  byte error, address;
  int nDevices = 0;
  
  Serial.println("Scanning I2C bus from 0x20 to 0x7F...\n");
  Serial.println("Address\t| Status");
  Serial.println("--------+----------");
  
  for(address = 0x20; address < 0x7F; address++) {
    Wire.beginTransmission(address);
    error = Wire.endTransmission();
    
    Serial.print("0x");
    if (address < 16) Serial.print("0");
    Serial.print(address, HEX);
    Serial.print("\t| ");
    
    if (error == 0) {
      Serial.println("✓ Device Found");
      nDevices++;
    } else {
      Serial.println("--");
    }
  }
  
  Serial.println("\n--------+----------");
  Serial.print("Total devices found: ");
  Serial.println(nDevices);
  
  if(nDevices > 0) {
    Serial.println("\n✓ I2C communication working!");
  } else {
    Serial.println("\n✗ NO I2C devices found!");
    Serial.println("  CHECK:");
    Serial.println("  - SDA/SCL connections");
    Serial.println("  - Pull-up resistors (4.7k on both lines)");
    Serial.println("  - LCD power (VCC/GND)");
  }
}

/**
 * Try initializing LCD on common addresses and display text
 */
void testCommonAddresses() {
  byte addresses[] = {0x27, 0x3F, 0x20, 0x25, 0x38};
  int numAddresses = sizeof(addresses) / sizeof(addresses[0]);
  
  for(int i = 0; i < numAddresses; i++) {
    byte addr = addresses[i];
    
    Serial.print("Testing address 0x");
    if (addr < 16) Serial.print("0");
    Serial.print(addr, HEX);
    Serial.print("... ");
    
    // Create LCD object with this address
    LiquidCrystal_I2C lcd(addr, 16, 2);
    
    // Try to initialize
    lcd.init();
    
    // Turn on backlight
    lcd.backlight();
    delay(500);
    
    // Clear and print test message
    lcd.clear();
    lcd.setCursor(0, 0);
    lcd.print("Address: 0x");
    if (addr < 16) lcd.print("0");
    lcd.print(addr, HEX);
    
    lcd.setCursor(0, 1);
    lcd.print("SUCCESS!");
    
    Serial.println("✓ TEXT DISPLAYED!");
    Serial.print("\n*** YOUR LCD ADDRESS IS: 0x");
    if (addr < 16) Serial.print("0");
    Serial.println(addr, HEX);
    Serial.println("*** Copy this address to your code!\n");
    
    delay(3000);
    
    // Keep it on
    return;
  }
  
  Serial.println("\nNo common address worked.");
  Serial.println("Your LCD might be on an uncommon address.");
  Serial.println("Run scanI2CDevices() to find your device.");
}

/*
 * ===============================================
 * IF TEXT DISPLAYS:
 * ===============================================
 * 
 * Note the address shown (e.g., 0x27)
 * 
 * Update your code:
 * LiquidCrystal_I2C lcd(0x27, 16, 2);  // Replace 0x27 with your address
 * 
 */

/*
 * ===============================================
 * COMMON I2C LCD ADDRESSES
 * ===============================================
 * 
 * 0x27 - Most common (16x2) - GREEN backlight
 * 0x3F - Common variant (20x4) - BLUE backlight
 * 0x20 - Some older models
 * 0x25 - Some clones
 * 0x38 - Rare variant
 * 
 */

/*
 * ===============================================
 * IF NO TEXT DISPLAYS:
 * ===============================================
 * 
 * Check connections:
 * - VCC (5V) → LCD VCC
 * - GND → LCD GND
 * - SDA → Arduino A4 (Uno/Nano) or pin 20 (Mega)
 * - SCL → Arduino A5 (Uno/Nano) or pin 21 (Mega)
 * 
 * Check for pull-up resistors:
 * - 4.7k resistor from SDA to 5V
 * - 4.7k resistor from SCL to 5V
 * 
 * Try this simpler I2C scan:
 * 
 * void setup() {
 *   Serial.begin(9600);
 *   Wire.begin();
 * }
 * 
 * void loop() {
 *   for(int addr=0x20; addr<0x7F; addr++) {
 *     Wire.beginTransmission(addr);
 *     if(Wire.endTransmission() == 0) {
 *       Serial.print("Found: 0x");
 *       Serial.println(addr, HEX);
 *     }
 *   }
 *   delay(5000);
 * }
 * 
 */
