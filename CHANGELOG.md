# Changelog

## [0.2.5] - 2026-05-06 18:38

### Changed
- Use one .env file and update roadmap toward meal plans, recipes, and food locations


## [0.2.4] - 2026-05-06 18:33

### Changed
- Auto-enrich new barcode scans with Open Food Facts product names


## [0.2.3] - 2026-05-06 18:26

### Changed
- Replace the temporary live test view with a mobile-first New Nordic HMI shell


## [0.2.2] - 2026-05-06 18:00

### Changed
- Add dedicated auth.test_sms endpoint for live InMobile delivery checks


## [0.2.1] - 2026-05-06 17:52

### Changed
- Use correct InMobile REST v4 SMS endpoint format with Basic Auth and messages payload


## [0.2.0] - 2026-05-06 17:44

### Changed
- Add backend 2FA foundation: OTP request/verify endpoints, auth sessions, and InMobile SMS provider integration hooks


## [0.1.9] - 2026-05-06 17:37

### Changed
- Tighten duplicate suppression: 6s window on ESP32 and backend


## [0.1.8] - 2026-05-06 17:33

### Changed
- Add X-Device-Token protection and duplicate-scan suppression (client + server)


## [0.1.7] - 2026-05-06 17:28

### Changed
- Fix ESP32 API 404: use raw HTTP request with explicit Host header for virtual-host routing


## [0.1.6] - 2026-05-06 17:24

### Changed
- Root now serves test dashboard, API moved to api.php, deploy script uploads root api/live files


## [0.1.5] - 2026-05-06 17:18

### Changed
- Add temporary live test view (live.php) with recent scans and inventory data endpoints


## [0.1.4] - 2026-05-06 17:11

### Changed
- Include IN/OUT mode firmware and wiring docs in tracked release


## [0.1.3] - 2026-05-06 17:11

### Changed
- Add IN/OUT scan mode toggle on GPIO4 button and send correct movement_type in ESP32 payload


## [0.1.2] - 2026-05-06 17:06

### Changed
- Include ESP32 firmware and backend scan endpoint changes in tracked release


## [0.1.1] - 2026-05-06 17:06

### Changed
- Fix ESP32 scan ingestion: working POST endpoint on index.php, hardened scan handler, and firmware API target update


## [0.1.0] - 2026-05-06 16:30

### Added
- Initial project setup (PHP backend, MySQL schema, FTPS deploy scripts).
- ESP32 scanner firmware with SSD1306 display support.
- Initial API/status endpoints and scan handler baseline.

