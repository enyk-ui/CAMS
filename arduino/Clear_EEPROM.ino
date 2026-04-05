#include <EEPROM.h>

void setup() {
  Serial.begin(115200);
  Serial.println("\n=== CAMS EEPROM CLEANER ===");
  
  EEPROM.begin(100);
  
  Serial.println("Clearing EEPROM...");
  
  // Clear all EEPROM data
  for(int i = 0; i < 100; i++) {
    EEPROM.write(i, 0);
  }
  
  EEPROM.commit();
  
  Serial.println("✅ EEPROM cleared successfully!");
  Serial.println("📌 Now upload the main CAMS.ino code");
  Serial.println("🔄 Device will start config portal on next boot");
}

void loop() {
  // Blink LED to show clearing is complete
  digitalWrite(LED_BUILTIN, HIGH);
  delay(500);
  digitalWrite(LED_BUILTIN, LOW);
  delay(500);
}