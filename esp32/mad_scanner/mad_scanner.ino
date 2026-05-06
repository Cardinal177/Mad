#include <Wire.h>
#include <Adafruit_GFX.h>
#include <Adafruit_SSD1306.h>
#include <WiFi.h>
#include <WiFiClient.h>

// OLED Display setup
#define SCREEN_WIDTH 128
#define SCREEN_HEIGHT 32
#define OLED_RESET -1
Adafruit_SSD1306 display(SCREEN_WIDTH, SCREEN_HEIGHT, &Wire, OLED_RESET);

// UART for barcode scanner (SEN0486)
#define RXD 16  // ESP32 RX pin
#define TXD 17  // ESP32 TX pin
#define MODE_BUTTON_PIN 4  // Button to toggle IN/OUT mode (to GND)

// WiFi credentials
const char* ssid = "UniFi";
const char* password = "Tommeltot2020";

// API settings
const char* apiHostIp = "194.181.228.25";
const int apiPort = 80;
const char* apiHostHeader = "mad.cardinal.webd.pro";
const char* apiPath = "/api.php?endpoint=scan";
const char* deviceToken = "mad-esp32-2026";

// State management
String lastScannedBarcode = "";
uint32_t lastScanTime = 0;
bool isConnected = false;
uint32_t lastDisplayRefresh = 0;
uint32_t lastWifiRetryAt = 0;
bool lastButtonState = HIGH;
uint32_t lastButtonDebounceAt = 0;
String lastSubmittedBarcode = "";
String lastSubmittedMode = "";
uint32_t lastSubmittedAt = 0;
const uint32_t duplicateWindowMs = 2500;

enum ScanMode {
    MODE_IN,
    MODE_OUT
};

ScanMode currentMode = MODE_IN;

const char* modeToMovementType(ScanMode mode) {
    return mode == MODE_OUT ? "out" : "in";
}

String wifiStatusToString(wl_status_t status) {
    switch (status) {
        case WL_IDLE_STATUS: return "IDLE";
        case WL_NO_SSID_AVAIL: return "NO_SSID";
        case WL_SCAN_COMPLETED: return "SCAN_DONE";
        case WL_CONNECTED: return "CONNECTED";
        case WL_CONNECT_FAILED: return "CONNECT_FAILED";
        case WL_CONNECTION_LOST: return "CONNECTION_LOST";
        case WL_DISCONNECTED: return "DISCONNECTED";
        default: return "UNKNOWN";
    }
}

void setup() {
    Serial.begin(115200);
    Serial.println("\n\nESP32 Barcode Scanner starting...");
    
    // Initialize OLED
    if (!display.begin(SSD1306_SWITCHCAPVCC, 0x3C)) {
        Serial.println("SSD1306 allocation failed");
        while (1);
    }
    
    displaySplash();
    
    // Initialize scanner serial
    Serial2.begin(9600, SERIAL_8N1, RXD, TXD);
    Serial.println("Scanner serial initialized");

    pinMode(MODE_BUTTON_PIN, INPUT_PULLUP);
    Serial.println("Mode button initialized on GPIO4");
    
    // Connect to WiFi
    connectWiFi();
}

void loop() {
    if (WiFi.status() != WL_CONNECTED) {
        isConnected = false;
        if (millis() - lastWifiRetryAt > 15000) {
            lastWifiRetryAt = millis();
            Serial.println("WiFi lost. Retrying connection...");
            connectWiFi();
        }
    }

    bool buttonState = digitalRead(MODE_BUTTON_PIN);
    if (buttonState != lastButtonState && millis() - lastButtonDebounceAt > 60) {
        lastButtonDebounceAt = millis();
        lastButtonState = buttonState;

        if (buttonState == LOW) {
            currentMode = (currentMode == MODE_IN) ? MODE_OUT : MODE_IN;
            Serial.println(String("Mode switched to: ") + modeToMovementType(currentMode));
        }
    }

    // Read from barcode scanner
    if (Serial2.available()) {
        String scanned = Serial2.readStringUntil('\n');
        scanned.trim();
        
        if (scanned.length() > 0) {
            lastScannedBarcode = scanned;
            lastScanTime = millis();
            Serial.println("Scanned: " + scanned + " | mode=" + String(modeToMovementType(currentMode)));
            
            displayScanned(scanned);
            
            // Send to API
            if (isConnected) {
                sendToAPI(scanned);
            }
        }
    }
    
    // Update display every 500ms
    if (millis() - lastDisplayRefresh > 500) {
        lastDisplayRefresh = millis();
        displayStatus();
    }
    
    delay(10);
}

void displaySplash() {
    display.clearDisplay();
    display.setTextSize(1);
    display.setTextColor(SSD1306_WHITE);
    display.setCursor(0, 0);
    display.println("Mad Scanner v1.0");
    display.println("SEN0486 + SSD1306");
    display.println("");
    display.println("Initializing...");
    display.display();
}

void displayStatus() {
    display.clearDisplay();
    display.setTextSize(1);
    display.setTextColor(SSD1306_WHITE);
    
    // Title
    display.setCursor(0, 0);
    display.println("=== Mad Scanner ===");
    
    // WiFi status + mode
    display.setCursor(0, 10);
    if (isConnected) {
        display.println(String("WiFi:OK ") + (currentMode == MODE_IN ? "IN" : "OUT"));
    } else {
        display.println(String("WiFi:NO ") + (currentMode == MODE_IN ? "IN" : "OUT"));
    }
    
    // Last scanned (or waiting)
    display.setCursor(0, 20);
    if (millis() - lastScanTime < 3000 && lastScannedBarcode.length() > 0) {
        String display_text = lastScannedBarcode;
        if (display_text.length() > 20) {
            display_text = display_text.substring(0, 20);
        }
        display.println("Last: " + display_text);
    } else {
        display.println("Scan a barcode...");
    }
    
    display.display();
}

void displayScanned(String barcode) {
    display.clearDisplay();
    display.setTextSize(1);
    display.setTextColor(SSD1306_WHITE);
    
    display.setCursor(0, 0);
    display.println("=== Scanned ===");
    
    // Display barcode in chunks
    display.setCursor(0, 10);
    String chunk1 = barcode.substring(0, min(20, (int)barcode.length()));
    display.println(chunk1);
    
    if (barcode.length() > 20) {
        display.setCursor(0, 20);
        String chunk2 = barcode.substring(20);
        if (chunk2.length() > 20) chunk2 = chunk2.substring(0, 20);
        display.println(chunk2);
    }
    
    display.display();
}

void connectWiFi() {
    display.clearDisplay();
    display.setTextSize(1);
    display.setTextColor(SSD1306_WHITE);
    display.setCursor(0, 0);
    display.println("Connecting WiFi...");
    display.println(ssid);
    display.display();
    
    WiFi.mode(WIFI_STA);
    WiFi.setSleep(false);
    WiFi.setAutoReconnect(true);
    WiFi.disconnect(true, true);
    delay(300);
    WiFi.begin(ssid, password);
    
    int attempts = 0;
    while (WiFi.status() != WL_CONNECTED && attempts < 60) {
        delay(500);
        Serial.print(".");
        attempts++;
    }
    
    wl_status_t status = WiFi.status();

    if (status == WL_CONNECTED) {
        isConnected = true;
        Serial.println("\nWiFi connected!");
        Serial.println("SSID: " + WiFi.SSID());
        Serial.println("IP: " + WiFi.localIP().toString());
        Serial.println("RSSI: " + String(WiFi.RSSI()));
        
        display.clearDisplay();
        display.setCursor(0, 0);
        display.println("WiFi OK!");
        display.println("IP: " + WiFi.localIP().toString());
        display.display();
        delay(2000);
    } else {
        isConnected = false;
        Serial.println("\nWiFi connection failed!");
        Serial.println("Reason: " + wifiStatusToString(status));
        Serial.println("Tips: Use 2.4GHz, WPA2, exact SSID/password");
        
        display.clearDisplay();
        display.setCursor(0, 0);
        display.println("WiFi Failed!");
        display.println(wifiStatusToString(status));
        display.display();
        delay(2000);
    }
}

void sendToAPI(String barcode) {
    if (!isConnected) return;

    String movementType = String(modeToMovementType(currentMode));
    if (barcode == lastSubmittedBarcode && movementType == lastSubmittedMode && (millis() - lastSubmittedAt) < duplicateWindowMs) {
        Serial.println("Duplicate scan suppressed (client-side)");
        return;
    }

    // Build JSON payload
    String payload = "{\"barcode\":\"" + barcode + "\",\"household_id\":1,\"location_id\":1,\"movement_type\":\"" + movementType + "\",\"quantity\":1}";

    Serial.println("Sending to API: " + payload);

    WiFiClient client;
    if (!client.connect(apiHostIp, apiPort)) {
        Serial.println("API Error: TCP connect failed");
        return;
    }

    client.print(String("POST ") + apiPath + " HTTP/1.1\r\n");
    client.print(String("Host: ") + apiHostHeader + "\r\n");
    client.print("User-Agent: MadScannerESP32/1.0\r\n");
    client.print("Connection: close\r\n");
    client.print("Content-Type: application/json\r\n");
    client.print(String("X-Device-Token: ") + deviceToken + "\r\n");
    client.print(String("Content-Length: ") + payload.length() + "\r\n\r\n");
    client.print(payload);

    uint32_t started = millis();
    while (!client.available() && millis() - started < 8000) {
        delay(10);
    }

    if (!client.available()) {
        Serial.println("API Error: timeout waiting for response");
        client.stop();
        return;
    }

    String statusLine = client.readStringUntil('\n');
    statusLine.trim();
    Serial.println("API Raw: " + statusLine);

    int httpCode = -1;
    if (statusLine.startsWith("HTTP/1.1 ") || statusLine.startsWith("HTTP/1.0 ")) {
        int firstSpace = statusLine.indexOf(' ');
        int secondSpace = statusLine.indexOf(' ', firstSpace + 1);
        if (firstSpace > 0 && secondSpace > firstSpace) {
            httpCode = statusLine.substring(firstSpace + 1, secondSpace).toInt();
        }
    }

    if (httpCode == 200 || httpCode == 201) {
        Serial.println("API Response: Success");
        lastSubmittedBarcode = barcode;
        lastSubmittedMode = movementType;
        lastSubmittedAt = millis();
    } else {
        Serial.println("API Response: " + String(httpCode));
    }

    String body = "";
    bool inBody = false;
    while (client.connected() || client.available()) {
        String line = client.readStringUntil('\n');
        if (!inBody) {
            if (line == "\r" || line.length() == 0) {
                inBody = true;
            }
            continue;
        }
        body += line;
    }

    body.trim();
    if (body.length() > 0) {
        Serial.println("API Body: " + body);
    }

    client.stop();
}
