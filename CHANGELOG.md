# Changelog

## [0.2.19] - 2026-05-11 20:38

### Changed
- Show basis marker as a compact inline "B" badge to the right of item names in mobile Lager list

## [0.2.18] - 2026-05-11 20:36

### Changed
- Remove the extra bottom login/logout button from mobile Lager and Indkøb pages

## [0.2.17] - 2026-05-11 20:34

### Changed
- Hide mobile scan section while search input is focused to keep inventory results visible above the keyboard

## [0.2.16] - 2026-05-11 20:31

### Changed
- Make mobile inventory camera section compact with one Scan vare toggle button that starts scanning immediately

## [0.2.15] - 2026-05-11 20:28

### Changed
- Remove confirmation dialog when using mobile swipe action to set inventory quantity to 0

## [0.2.14] - 2026-05-11 20:27

### Changed
- Change mobile inventory left-swipe action from delete to set quantity to 0, keeping products for future shopping suggestions

## [0.2.13] - 2026-05-11 20:23

### Changed
- Show quantity for standard inventory items in both mobile and live inventory views

## [0.2.12] - 2026-05-11 20:21

### Changed
- Reverse inventory quantity when a shopping item is changed from bought to not bought, preventing accidental stock inflation

## [0.2.11] - 2026-05-11 20:16

### Changed
- Make shopping suggestion selection focus quantity input before adding, and always show quantity on shopping list rows

## [0.2.10] - 2026-05-11 20:12

### Changed
- Restore shopping suggestions for out-of-stock items by allowing products endpoint include_zero mode for autocomplete cache

## [0.2.9] - 2026-05-11 20:09

### Changed
- Ensure lagerlister hides varer with 0 quantity (API + mobile/live rendering) and allow shopping checked items to increase both standard and basis inventory rows

## [0.2.8] - 2026-05-06 18:58

### Changed
- Backfill old products from Open Food Facts and clean repeated legacy barcode entries


## [0.2.7] - 2026-05-06 18:53

### Changed
- Hide technical scan details and enrich food cards with image and nutrition data


## [0.2.6] - 2026-05-06 18:45

### Changed
- Fix repeated scanner payloads and auto-refresh existing placeholder products from Open Food Facts


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

