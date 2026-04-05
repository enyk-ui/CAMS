# CAMS Fingerprint Scanner - Quick Setup

## 🎯 What It Does

This is a **scanner-only** device:
- Displays scrolling CAMS title + "Scan your finger!"
- Scans fingerprint
- Sends to PHP server
- Server decides: Registration or Attendance?
- Displays response on LCD

## 📋 Required Libraries (Install in Arduino IDE)

**Sketch → Include Library → Manage Libraries:**

1. **Adafruit Fingerprint Sensor Library**
   - Search: `Adafruit Fingerprint`
   - Author: Adafruit Industries
   - Version: 2.0.0+

2. **ArduinoJson**
   - Search: `ArduinoJson`
   - Author: Benoit Blanchon
   - Version: 6.19.0+

3. **LiquidCrystal_I2C**
   - Search: `LiquidCrystal I2C`
   - Author: Frank de Brabander
   - (For 16x2 LCD display)

## 🔧 Board Configuration

1. **Tools → Board → Boards Manager**
2. Install: `esp8266 by ESP8266 Community` (v3.0.0+)
3. Select Board: **NodeMCU 1.0 (ESP-12E)**
4. Upload Speed: **115200**

## ⚙️ Configuration

Edit the top of CAMS.ino:

```cpp
// WiFi
#define WIFI_SSID "YourWiFi"
#define WIFI_PASSWORD "YourPassword"

// Server
#define SERVER_IP "192.168.1.10"      // Your PHP server
#define SERVER_PORT 80

// LCD I2C Address (usually 0x27)
#define LCD_I2C_ADDRESS 0x27
```

## 🔌 Hardware Connections

### Fingerprint Sensor (DY50)
```
ESP8266 D1 (GPIO5)  → DY50 RX
ESP8266 D2 (GPIO4)  → DY50 TX
ESP8266 GND         → DY50 GND
ESP8266 5V*         → DY50 VCC
```
*Use separate 5V power supply for sensor!

### LCD Display (I2C 16x2)
```
ESP8266 D3 (GPIO0)  → LCD SDA
ESP8266 D4 (GPIO2)  → LCD SCL
ESP8266 GND         → LCD GND
ESP8266 3.3V        → LCD VCC
```

## 🚀 Upload

1. Copy entire CAMS.ino content
2. Paste into Arduino IDE
3. Select correct Board and Port
4. Click **Upload**

## 📺 Display Behavior

### Idle Mode
```
Upper (scrolling):
"Criminology Attendance Monitoring System (CAMS)"

Lower (static):
"Scan your finger!"
```

### Scanning
```
"Processing..."
"Please wait"
```

### Result - Present
```
">> PRESENT <<"
"Juan Dela Cruz"
```

### Result - Late
```
">> LATE <<"
"Juan Dela Cruz"
```

### Result - Absent
```
">> ABSENT <<"
"Juan Dela Cruz"
```

### Result - Not Registered
```
"ERROR!"
"Not Registered!"
```

### WiFi Connection Failed
```
"WiFi failed"
"Offline mode"
```

## 🔄 How It Works

1. **Device starts** → Shows scrolling idle message
2. **User scans** → Display shows "Processing..."
3. **Fingerprint matched** → Extracts sensor_id
4. **Sends to server** → POST /api/scan with sensor_id
5. **Server responds** → JSON with status, name, message
6. **Display shows** → Status and student name
7. **Back to idle** → After 4 seconds

## 📡 Server Communication

### Device Sends
```json
{
  "sensor_id": "FP01A",
  "device_id": "CAMS-Scanner-01"
}
```

### Server Returns (Example)
```json
{
  "success": true,
  "status": "present",
  "student_name": "Juan Dela Cruz",
  "message": "Welcome!"
}
```

## 🐛 Troubleshooting

| Problem | Solution |
|---------|----------|
| LCD blank | Check I2C address (0x27 most common) |
| Sensor not responding | Check D1/D2 connections, 57600 baud |
| WiFi won't connect | Check SSID/password (case-sensitive) |
| Server error | Verify SERVER_IP correct, check PHP API |
| No finger detection | Clean sensor glass, 5V power stable |

## 🔍 Find I2C LCD Address

If LCD doesn't work, find its address:

1. Upload this Arduino code:
```cpp
#include <Wire.h>
void setup() {
  Serial.begin(115200);
  Wire.begin(D3, D4);
  for(int addr=1; addr<127; addr++) {
    Wire.beginTransmission(addr);
    if(Wire.endTransmission()==0) {
      Serial.print("Device at: 0x");
      Serial.println(addr, HEX);
    }
  }
}
void loop() {}
```

2. Check Serial Monitor for address
3. Update in CAMS.ino: `#define LCD_I2C_ADDRESS 0x??`

## ✅ Testing

After upload, via **Serial Monitor (115200)**:

You should see initialization messages:
```
CAMS - Fingerprint Scanner
Initializing...
LCD initialized
Initializing fingerprint sensor...
Sensor initialized. Templates: 5
Connecting to WiFi...
WiFi connected! IP: 192.168.1.100
Server verified!
System ready!
```

Then scan a finger and check:
- LCD shows student name
- Serial shows response
- Database updates

## 📝 Notes

- No enrollment on device (done on web)
- Device is WiFi-dependent (no offline mode for sending data)
- LCD shows all responses from server
- Sensor stores up to 200 fingerprints
- 16-character LCD display limit for names

**Done! Device is ready to scan! 🎉**
