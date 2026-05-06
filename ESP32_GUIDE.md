# ESP32 Setup Guide

## Quick Start

Du har nu en fuldt integreret system:
- **ESP32 sketch** med OLED + barcode scanner support
- **Live API** på `mad.cardinal.webd.pro/index.php`
- **Database** med lagerstyring

## Hardware

Sæt ledningerne således op:

### SSD1306 OLED (I2C)
```
GND -> GND
VCC -> 5V/3.3V
SDA -> GPIO 21
SCL -> GPIO 22
```

### DFROBOT SEN0486 (Serial UART)
```
GND -> GND
RX  -> GPIO 16
TX  -> GPIO 17
5V  -> 5V
```

## Arduino IDE

1. Installer ESP32 board support (se `/esp32/README.md`)
2. Installer biblioteker:
   - Adafruit SSD1306
   - Adafruit GFX
3. Upload `/esp32/mad_scanner.ino` til ESP32

## Konfiguration

Rediger i sketchen:
```cpp
const char* ssid = "YOUR_WIFI";
const char* password = "YOUR_PASSWORD";
```

## Testing

Fra terminal (lokalt):
```bash
cd /Users/christian/Library/CloudStorage/OneDrive-LufthavnsteknikApS/VSCode/Mad
php test_scan.php
```

Fra live API:
```bash
curl -H 'Host: mad.cardinal.webd.pro' \
  'http://194.181.228.25/index.php?test=write'
```

## Næste trin

1. **Implementer `/api/scan` på webserver** – Der mangler en URL router der virker med LiteSpeed
2. **Upload og test ESP32** – Skann nogle stregkoder med scanneren
3. **Lav frontend** – Dashboard til visualisering af lager
