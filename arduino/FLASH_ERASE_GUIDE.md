# 🔥 How to Completely Erase ESP8266 Flash Memory

## Problem
Old code is still running even after uploading new sketch. You see messages like:
```
[Config] Web server started on 192.168.4.1
[EEPROM] Read IP:
[Config] Starting WiFi config portal...
```

This means the old code is still in flash memory.

---

## ✅ Solution: Erase Flash Memory

### Method 1: Using Arduino IDE (Easiest)

1. **Open Arduino IDE**

2. **Select Your Board**
   - Tools → Board → ESP8266 → NodeMCU 1.0

3. **Select Your COM Port**
   - Tools → Port → (Your ESP8266 port)

4. **Set Erase Flash Option**
   - Tools → Erase Flash → **"All Flash Contents"**
   
5. **Upload ANY sketch** (or use a blank sketch)
   - This will erase everything and upload fresh code

6. **Upload Your CAMS.ino**
   - Now upload your actual code

---

### Method 2: Using esptool.py (Complete Erase)

**Step 1: Install esptool**
```bash
pip install esptool
```

**Step 2: Find Your COM Port**
- Windows: Check Device Manager (e.g., COM3, COM4)
- Mac/Linux: `/dev/ttyUSB0` or `/dev/cu.usbserial-*`

**Step 3: Erase Flash**
```bash
# Replace COM3 with your actual port
esptool.py --port COM3 erase_flash
```

**Step 4: Upload New Code**
- Open Arduino IDE
- Upload CAMS.ino normally

---

### Method 3: Upload Blank Sketch First

**Step 1: Create blank sketch**
```cpp
void setup() {
  Serial.begin(115200);
  Serial.println("Flash cleared!");
}

void loop() {
  delay(1000);
}
```

**Step 2: Upload it**
- Tools → Erase Flash → "All Flash Contents"
- Upload the blank sketch

**Step 3: Upload CAMS.ino**
- Now upload your actual code

---

## 🎯 Quick Fix (3 Steps)

### If you just want it to work NOW:

1. **In Arduino IDE**
   ```
   Tools → Erase Flash → All Flash Contents
   ```

2. **Click Upload** (↑ button)
   - Let it finish completely

3. **Open Serial Monitor**
   - Set to 115200 baud
   - You should now see the NEW messages:
   ```
   🚀 CAMS Fingerprint Scanner v4.0
   Simplified: Scan & Send Only
   ```

---

## 🔍 Verify It Worked

After upload, Serial Monitor should show:
```
=================================
🚀 CAMS Fingerprint Scanner v4.0
   Simplified: Scan & Send Only
=================================
[Boot] System starting...
[Config] Server IP: 10.18.239.46
[Sensor] Initializing...
[Sensor] Found!
```

**NO MORE:**
- ❌ `[Config] Web server started`
- ❌ `[EEPROM] Read IP`
- ❌ `[Config] AP started`

---

## 📋 Complete Flash Erase Checklist

- [ ] Close Serial Monitor
- [ ] Select correct Board (NodeMCU 1.0)
- [ ] Select correct Port
- [ ] Tools → Erase Flash → "All Flash Contents"
- [ ] Click Upload
- [ ] Wait for "Done uploading"
- [ ] Open Serial Monitor (115200)
- [ ] Press Reset button on ESP8266
- [ ] Check for new boot messages

---

## 🐛 Still Seeing Old Messages?

### Try This:

1. **Physically unplug ESP8266**
2. **Wait 10 seconds**
3. **Plug it back in**
4. **Erase flash again:**
   ```bash
   esptool.py --port COM3 erase_flash
   ```
5. **Upload CAMS.ino**

---

## 🛠️ Advanced: Manual esptool Commands

### Complete Flash Erase + Upload

```bash
# 1. Erase everything
esptool.py --port COM3 erase_flash

# 2. Verify erase
esptool.py --port COM3 read_flash 0x0 0x100 test.bin
# Should show all 0xFF bytes

# 3. Upload new code from Arduino IDE
```

---

## 📸 Screenshots Reference

### Arduino IDE Settings:
```
Tools Menu:
  ├── Board: "NodeMCU 1.0 (ESP-12E Module)"
  ├── Upload Speed: "115200"
  ├── CPU Frequency: "80 MHz"
  ├── Flash Size: "4MB (FS:2MB OTA:~1019KB)"
  ├── Erase Flash: "All Flash Contents" ← SELECT THIS!
  └── Port: "COM3" (your port)
```

---

## ✅ Expected Output After Fix

```
=================================
🚀 CAMS Fingerprint Scanner v4.0
   Simplified: Scan & Send Only
=================================
[Boot] System starting...
[Config] Server IP: 10.18.239.46
[Setup] Hardware initialized
[Sensor] Initializing...
[Sensor] Found!
[Setup] ✅ Fingerprint sensor OK
[Setup] Connecting to WiFi: Redmi Note 13
..........
[Setup] ✅ WiFi connected!
[Setup] Local IP: 192.168.x.x
[Server] Testing connection to: 10.18.239.46
[Server] ✅ Connected OK
[Setup] ✅ Server is ONLINE
[Setup] 🎯 System ready!
[Loop] 🔄 Main loop started - Ready for fingerprint scanning
```

**Clean output with NO old config messages!** ✅

---

## 💡 Why This Happens

ESP8266 flash memory stores:
- Program code
- WiFi credentials  
- EEPROM data
- File system data

Simply uploading new code **doesn't erase** old data in other flash sectors.

The **"Erase Flash: All Flash Contents"** option clears EVERYTHING.

---

## 🚀 Prevention

For future uploads:
- Always use "Erase Flash: All Flash Contents" when switching to completely different code
- For minor code updates: "Only Sketch" is fine

---

**Last Updated:** 2026-04-05  
**Issue:** Old code persisting in flash  
**Solution:** Complete flash erase before upload
