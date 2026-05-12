# Changelog

## [0.2.43] - 2026-05-12 18:46

### Changed
- Use microdata parser (itemprop ingredients/instructions) before AI for Valdemarsro-style recipes


## [0.2.42] - 2026-05-12 18:40

### Changed
- Claude-first recipe extraction + manual recipe template with editable prefill


## [0.2.41] - 2026-05-12 18:35

### Changed
- Fix recipe extraction: use first occurrence of Ingredienser not last


## [0.2.40] - 2026-05-12 18:33

### Changed
- Fix recipe extraction: anchor to raw HTML position of Ingredienser before stripping tags


## [0.2.39] - 2026-05-12 18:30

### Changed
- Fix recipe extraction: anchor to last occurrence of Ingredienser, increase limit to 20000 chars


## [0.2.38] - 2026-05-12 18:28

### Changed
- Fix recipe delete: use owner_household_id column name


## [0.2.37] - 2026-05-12 18:26

### Changed
- Fix recipe delete: remove meal_plan_days references before deleting recipe


## [0.2.36] - 2026-05-12 18:25

### Changed
- Add delete button on recipe cards with DELETE API endpoint


## [0.2.35] - 2026-05-12 18:23

### Changed
- Improve recipe extraction: skip cookie banners, anchor to recipe section, increase text limit to 14000 chars


## [0.2.34] - 2026-05-12 18:20

### Changed
- Send raw HTML to Claude for recipe extraction instead of regex parsing


## [0.2.33] - 2026-05-12 17:41

### Changed
- Recipe cards UI with clickable modal detail view


## [0.2.33] - 2025-05-13 09:00

### Added
- Recipe cards UI with clickable thumbnail grid on Opskrifter page
- Recipe detail modal with ingredients list, step-by-step guide, and stats
- Modal triggered by clicking card, closed by ✕, backdrop click, or Escape key
- "Tildel til madplan" button integrated in modal for quick day assignment
- Fetch recipes with `include=details` to load ingredients and steps

## [0.2.32] - 2026-05-12 17:40

### Added
- Integrate Claude AI (Anthropic) to clean and structure imported recipes
- AI standardizes ingredient quantities and units (e.g., "2 cups" → "500ml")
- AI extracts and structures meal preparation into numbered steps
- AI extracts cooking times and serving information
- AI gracefully skips if AI not enabled or API key missing (fallback to raw HTML parsing)
- Add "ai_cleaned" flag to indicate recipes processed by Claude

## [0.2.31] - 2026-05-12 17:36

### Added
- Add HTML fallback parser for recipe import when Recipe JSON-LD schema is missing
- Parse recipe title from h1, meta og:title, or page title
- Parse description from meta og:description or first p tag
- Parse ingredients from HTML lists (li tags) or lines with cooking units (g, ml, cups, etc)
- Parse instructions from numbered list items
- Works with any Danish recipe site, not just those with Recipe schema

## [0.2.30] - 2026-05-12 17:32

### Fixed
- Fix recipe import error handling: return 422 for missing/invalid Recipe schema instead of 500
- Improve error messages for URL imports to clearly indicate when Recipe JSON-LD is not found
- Update postJson frontend function to extract message/details from API error responses

## [0.2.29] - 2026-05-12 17:28

### Changed
- Move meal plan weekly grid from recipes page to dedicated madplan page for better UX separation

## [0.2.28] - 2026-05-12 17:24

### Added
- Add interactive recipe UI with import-from-URL button, recipe list, and meal plan weekly grid
- Add recipe handlers: importRecipeFromUrl, loadRecipes, loadMealPlan, assignRecipeToDay for full frontend flow
- Add auto-refresh of recipes and meal plan every 30 seconds while on recipes page
- Add modal dialogs for assigning recipes to meal plan days

## [0.2.27] - 2026-05-12 17:19

### Added
- Add recipe API endpoints for manual creation, URL import (JSON-LD), and detailed recipe listing with ingredients/steps
- Add meal plan API endpoints for weekly plan retrieval and assigning recipes to days
- Add Danish-focused URL recipe import scoring to flag likely non-Danish recipes for review
- Add migration script and SQL migration for recipe metadata, recipe steps, ingredient aliases, and meal plan tables

### Changed
- Extend base schema with recipe locale/servings/time/import columns plus recipe_steps, ingredient_aliases, meal_plans, and meal_plan_days tables

## [0.2.26] - 2026-05-12 17:05

### Fixed
- Deduplicate active offers in shopping.offer_feed so repeated scrape rows do not inflate store totals or duplicate cards in store views

## [0.2.25] - 2026-05-12 17:02

### Fixed
- Fix SQL query composition in shopping.offer_feed that caused "Database error" on offer views

## [0.2.24] - 2026-05-12 17:00

### Fixed
- Align offer counts between live overview and store pages by computing shopping.offer_feed summary on the full active offer set (not only limited returned items)
- Update Netto/Kvickly/365discount pages to show `summary.total` for total offers instead of counting returned list length

## [0.2.23] - 2026-05-12 16:57

### Changed
- Remove store title text/logo in top bar next to Tilbage on all three store pages (Netto, Kvickly, 365discount)
- Make offer-card image placeholder area significantly smaller on all three store pages (more compact cards when no image is available)

## [0.2.22] - 2026-05-12 16:53

### Changed
- Add a shopping offer status box on live dashboard with active offers per store and latest scrape timestamp
- Reduce offer-card image placeholder height on Netto/Kvickly/365discount store pages to make missing-image cards more compact

## [0.2.21] - 2026-05-12 16:45

### Changed
- Adjust offer window from strict week-end cap to active-now filtering (valid_from <= today <= valid_to) so current Kvickly campaigns remain visible while old offers are still pruned

## [0.2.20] - 2026-05-12 16:40

### Changed
- Restrict offers to currently active entries within the current week across scraping and backend offer endpoints, and prune out-of-week leaflet offers

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

