# ESP32 Barcode Scanner Firmware

Arduino IDE sketch for ESP32 with SSD1306 OLED display and DFROBOT SEN0486 barcode scanner.

## Hardware Setup

### Components
- ESP32 Development Board (e.g., ESP32 Dev Kit)
- SSD1306 OLED Display 128x32
- DFROBOT SEN0486 Barcode Scanner
- USB cable for programming

### Wiring

#### SSD1306 Display (I2C)
```
SSD1306 -> ESP32
GND     -> GND
VCC     -> 5V (or 3.3V, check display specs)
SDA     -> GPIO 21 (I2C SDA)
SCL     -> GPIO 22 (I2C SCL)
```

#### DFROBOT SEN0486 Scanner (Serial UART)
```
SEN0486 -> ESP32
GND     -> GND
RX      -> GPIO 16 (Serial2 RX on ESP32)
TX      -> GPIO 17 (Serial2 TX on ESP32)
5V      -> 5V (or directly to USB if powered)
```

#### Mode button (IN/OUT)
```
Button leg 1 -> GPIO 4
Button leg 2 -> GND
```

Firmware uses `INPUT_PULLUP`, so pressing the button toggles between `IN` and `OUT` mode.

**Note:** The scanner baud rate is typically 9600 bps. Verify with your device.

## Arduino IDE Setup

### 1. Install ESP32 Board Support
- File → Preferences
- Paste this URL in "Additional Boards Manager URLs":
  ```
  https://raw.githubusercontent.com/espressif/arduino-esp32/gh-pages/package_esp32_index.json
  ```
- Tools → Board Manager → Search "esp32" → Install "ESP32 by Espressif Systems"

### 2. Install Required Libraries
- Sketch → Include Library → Manage Libraries...
- Search and install:
  - **Adafruit SSD1306** (for OLED display)
  - **Adafruit GFX Library** (graphics library)

### 3. Configure Board
- Tools → Board → ESP32 Dev Module (or your specific board)
- Tools → Port → Select your ESP32 COM port

## Configuration

Edit the sketch to add your WiFi credentials:

```cpp
const char* ssid = "YOUR_SSID";
const char* password = "YOUR_PASSWORD";
```

Update API endpoint if needed:
```cpp
const char* apiHost = "http://mad.cardinal.webd.pro";
```

## API Endpoint

The sketch sends scanned barcodes to:
```
POST /api/scan
Content-Type: application/json

{
  "barcode": "1234567890",
  "scanned_at": "1234567890"
}
```

You need to implement this endpoint in the PHP backend.

## Display Status

The OLED shows:
- WiFi connection status
- Current scan mode (`IN` or `OUT`)
- Last scanned barcode
- "Scan a barcode..." when idle

## Troubleshooting

### Display not showing
- Check I2C address (default 0x3C)
- Verify SDA/SCL connections
- Try scanning with `i2c_scanner` sketch

### Scanner not sending data
- Check UART RX/TX connections (cross-verify: scanner TX → ESP32 RX)
- Verify baud rate is 9600
- Test with a USB-Serial adapter to confirm scanner output

### WiFi not connecting
- Double-check SSID and password
- Ensure 2.4GHz network (ESP32 doesn't support 5GHz on most boards)
- Check router encryption type
- Use WPA2-PSK (some ESP32 firmwares fail on WPA3-only networks)
- Avoid special enterprise/captive portal networks
- Check Serial Monitor at 115200 for reason codes: `NO_SSID`, `CONNECT_FAILED`, `DISCONNECTED`
- If SSID has hidden spaces, retype manually (do not copy/paste)
- Try mobile hotspot (2.4GHz) to isolate if issue is router-specific

## Next Steps

1. Implement `/api/scan` endpoint in PHP backend
2. Add inventory management logic
3. Implement "In/Out" mode selection on device
4. Add local caching for offline scanning
