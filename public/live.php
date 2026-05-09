<?php

declare(strict_types=1);

$householdId = max(1, (int) ($_GET['household_id'] ?? 1));
$householdName = trim((string) ($_GET['household_name'] ?? ''));
$requestUri = (string) ($_SERVER['REQUEST_URI'] ?? '');
$nettoPagePath = str_contains($requestUri, '/mad/')
    ? '/mad/public/stores/netto.php'
    : 'stores/netto.php';
$kvicklyPagePath = str_contains($requestUri, '/mad/')
    ? '/mad/public/stores/kvickly.php'
    : 'stores/kvickly.php';
$discount365PagePath = str_contains($requestUri, '/mad/')
    ? '/mad/public/stores/discount365.php'
    : 'stores/discount365.php';

if ($householdName == '') {
    $householdName = 'Husstand ' . $householdId;
}

$allowedPages = ['overblik', 'madplan', 'opskrifter', 'lager', 'indkoeb', 'opsaetning'];
$currentPage = strtolower(trim((string) ($_GET['page'] ?? 'overblik')));
if (!in_array($currentPage, $allowedPages, true)) {
    $currentPage = 'overblik';
}

$navParams = $_GET;
unset($navParams['page']);

$buildPageUrl = static function (string $page) use ($navParams): string {
    $query = array_merge($navParams, ['page' => $page]);
    return 'live.php?' . http_build_query($query);
};
?>
<!doctype html>
<html lang="da">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Mad HMI</title>
    <style>
        :root {
            --bg: #f3efe7;
            --bg-deep: #d9dfd2;
            --panel: rgba(255, 252, 247, 0.86);
            --panel-strong: #fdf8f1;
            --text: #14231d;
            --muted: #61716a;
            --line: rgba(20, 35, 29, 0.1);
            --accent: #2f6a56;
            --accent-soft: #dae7dc;
            --berry: #8f4f4f;
            --sand: #ece2d4;
            --warning: #c7772f;
            --ok: #2f6a56;
            --shadow: 0 18px 40px rgba(20, 35, 29, 0.12);
        }
        * { box-sizing: border-box; }
        html {
            scroll-behavior: smooth;
        }
        body {
            margin: 0;
            min-height: 100vh;
            font-family: "Avenir Next", "Helvetica Neue", "Segoe UI", sans-serif;
            color: var(--text);
            background:
                radial-gradient(circle at top left, rgba(255,255,255,0.72), transparent 34%),
                radial-gradient(circle at bottom right, rgba(47,106,86,0.12), transparent 28%),
                linear-gradient(180deg, var(--bg-deep) 0%, var(--bg) 22%, #f8f4ed 100%);
        }
        body::before {
            content: "";
            position: fixed;
            inset: 0;
            pointer-events: none;
            background-image: linear-gradient(rgba(255,255,255,0.08) 1px, transparent 1px), linear-gradient(90deg, rgba(20,35,29,0.03) 1px, transparent 1px);
            background-size: 100% 100%, 22px 22px;
            mask-image: linear-gradient(180deg, rgba(0,0,0,0.35), transparent 70%);
        }
        a {
            color: inherit;
            text-decoration: none;
        }
        button {
            font: inherit;
        }
        .app {
            position: relative;
            z-index: 1;
            max-width: 1240px;
            margin: 0 auto;
            padding: 20px 16px 96px;
        }
        .masthead {
            display: grid;
            gap: 16px;
            margin-bottom: 18px;
        }
        .context-strip {
            display: grid;
            gap: 10px;
        }
        .context-grid {
            display: grid;
            gap: 10px;
        }
        .context-card {
            padding: 14px 16px;
            border-radius: 20px;
            border: 1px solid rgba(255,255,255,0.68);
            background: rgba(255, 252, 247, 0.72);
            box-shadow: var(--shadow);
            backdrop-filter: blur(18px);
        }
        .context-label {
            display: block;
            color: var(--muted);
            font-size: 11px;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            margin-bottom: 6px;
        }
        .context-value {
            font-size: 18px;
            font-weight: 700;
            line-height: 1.2;
        }
        .context-copy {
            margin: 6px 0 0;
            color: var(--muted);
            font-size: 13px;
            line-height: 1.45;
        }
        .source-line {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 10px;
        }
        .source-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 7px 10px;
            border-radius: 999px;
            background: rgba(47, 106, 86, 0.09);
            color: var(--accent);
            font-size: 12px;
            font-weight: 700;
        }
        .source-pill.planned {
            background: rgba(236, 226, 212, 0.78);
            color: #6f5a42;
        }
        .topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }
        .page-menu {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }
        .page-link {
            display: inline-flex;
            align-items: center;
            min-height: 40px;
            padding: 0 14px;
            border-radius: 999px;
            border: 1px solid rgba(20,35,29,0.12);
            background: rgba(255,252,247,0.76);
            color: var(--text);
            font-size: 13px;
            font-weight: 600;
            letter-spacing: 0.02em;
        }
        .page-link.active {
            background: var(--text);
            color: #fbf7ef;
            border-color: transparent;
        }
        .admin-only {
        }
        .auth-gate {
            position: fixed;
            inset: 0;
            z-index: 9999;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            background: rgba(20, 35, 29, 0.48);
            backdrop-filter: blur(6px);
        }
        .auth-gate.hidden {
            display: none;
        }
        body.auth-pending #authGate {
            display: none;
        }
        body.auth-pending #top {
            visibility: hidden;
        }
        .auth-card {
            width: min(520px, 100%);
            border: 1px solid rgba(255,255,255,0.68);
            border-radius: 22px;
            background: rgba(255,252,247,0.94);
            box-shadow: var(--shadow);
            padding: 18px;
        }
        .auth-card h2 {
            margin: 0 0 8px;
            font-family: "Iowan Old Style", "Palatino Linotype", serif;
            font-size: 28px;
            line-height: 1;
        }
        .auth-card p {
            margin: 0 0 12px;
            color: var(--muted);
            font-size: 14px;
            line-height: 1.45;
        }
        .auth-grid {
            display: grid;
            gap: 10px;
        }
        .auth-grid input {
            width: 100%;
            border: 1px solid rgba(20,35,29,0.14);
            border-radius: 10px;
            padding: 10px 12px;
            font-size: 15px;
            color: var(--text);
            background: rgba(255,255,255,0.8);
        }
        .auth-status {
            margin-top: 10px;
            min-height: 20px;
            color: var(--muted);
            font-size: 13px;
        }
        .auth-status.err {
            color: var(--berry);
        }
        .brand {
            display: inline-flex;
            align-items: center;
            gap: 12px;
        }
        .brand-mark {
            width: 44px;
            height: 44px;
            display: grid;
            place-items: center;
            border-radius: 14px;
            color: #f7f1e7;
            background: linear-gradient(135deg, #29473d 0%, #3f7b63 100%);
            box-shadow: var(--shadow);
            font-family: "Iowan Old Style", "Palatino Linotype", serif;
            font-size: 20px;
        }
        .brand-name {
            margin: 0;
            font-family: "Iowan Old Style", "Palatino Linotype", serif;
            font-size: 28px;
            font-weight: 700;
            letter-spacing: 0.02em;
        }
        .brand-subtitle {
            margin: 2px 0 0;
            color: var(--muted);
            font-size: 13px;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }
        .sync-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 14px;
            border: 1px solid rgba(47, 106, 86, 0.16);
            border-radius: 999px;
            background: rgba(255, 252, 247, 0.7);
            color: var(--muted);
            font-size: 13px;
            backdrop-filter: blur(14px);
        }
        .sync-dot {
            width: 9px;
            height: 9px;
            border-radius: 999px;
            background: var(--ok);
            box-shadow: 0 0 0 8px rgba(47, 106, 86, 0.1);
        }
        .hero {
            display: grid;
            gap: 14px;
            padding: 18px;
            border: 1px solid rgba(255,255,255,0.65);
            border-radius: 28px;
            background:
                linear-gradient(160deg, rgba(255,252,247,0.9) 0%, rgba(247,241,231,0.82) 100%),
                linear-gradient(135deg, rgba(47,106,86,0.1), transparent 40%);
            box-shadow: var(--shadow);
            overflow: hidden;
        }
        .hero-copy {
            display: grid;
            gap: 12px;
        }
        .eyebrow {
            display: inline-flex;
            width: fit-content;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            border-radius: 999px;
            background: rgba(47, 106, 86, 0.09);
            color: var(--accent);
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }
        .hero h1 {
            margin: 0;
            font-family: "Iowan Old Style", "Palatino Linotype", serif;
            font-size: clamp(34px, 8vw, 58px);
            line-height: 0.96;
            letter-spacing: -0.03em;
        }
        .hero p {
            margin: 0;
            max-width: 58ch;
            color: var(--muted);
            font-size: 15px;
            line-height: 1.5;
        }
        .hero-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 4px;
        }
        .action {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            min-height: 46px;
            padding: 0 16px;
            border: 0;
            border-radius: 999px;
            background: var(--text);
            color: #fbf7ef;
            box-shadow: 0 12px 24px rgba(20,35,29,0.18);
            cursor: pointer;
        }
        .action.ghost {
            background: rgba(255,252,247,0.84);
            color: var(--text);
            border: 1px solid rgba(20,35,29,0.08);
            box-shadow: none;
        }
        .hero-aside {
            display: grid;
            gap: 12px;
        }
        .card {
            border: 1px solid rgba(255,255,255,0.68);
            border-radius: 22px;
            background: var(--panel);
            backdrop-filter: blur(18px);
            box-shadow: var(--shadow);
        }
        .signal-card {
            padding: 16px;
            background:
                linear-gradient(180deg, rgba(255,255,255,0.92) 0%, rgba(248,242,233,0.88) 100%);
        }
        .signal-label {
            display: block;
            color: var(--muted);
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            margin-bottom: 6px;
        }
        .signal-value {
            font-size: 34px;
            font-weight: 700;
            line-height: 1;
            letter-spacing: -0.04em;
            margin-bottom: 6px;
        }
        .signal-meta {
            color: var(--muted);
            font-size: 14px;
            line-height: 1.4;
        }
        .status-grid {
            display: grid;
            gap: 12px;
            margin: 18px 0 22px;
        }
        .metric {
            padding: 16px;
            border-radius: 22px;
            border: 1px solid rgba(255,255,255,0.7);
            background: var(--panel);
            box-shadow: var(--shadow);
        }
        .metric-name {
            color: var(--muted);
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            margin-bottom: 8px;
        }
        .metric-value {
            font-family: "Iowan Old Style", "Palatino Linotype", serif;
            font-size: 34px;
            line-height: 1;
            margin-bottom: 10px;
        }
        .metric-note {
            color: var(--muted);
            font-size: 14px;
            line-height: 1.4;
        }
        .layout {
            display: grid;
            gap: 18px;
        }
        .section {
            padding: 18px;
        }
        .section-head {
            display: flex;
            align-items: end;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 16px;
        }
        .section-kicker {
            margin: 0 0 6px;
            color: var(--muted);
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }
        .section-title {
            margin: 0;
            font-family: "Iowan Old Style", "Palatino Linotype", serif;
            font-size: 28px;
            line-height: 1.05;
        }
        .section-note {
            margin: 0;
            color: var(--muted);
            font-size: 14px;
        }
        .chip {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 9px 12px;
            border-radius: 999px;
            background: var(--accent-soft);
            color: var(--accent);
            font-size: 13px;
            font-weight: 600;
        }
        .section-copy {
            margin: 0;
            color: var(--muted);
            font-size: 14px;
            line-height: 1.5;
        }
        .subsection-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-top: 16px;
            margin-bottom: 10px;
        }
        .subsection-title {
            margin: 0;
            font-size: 17px;
            line-height: 1.2;
        }
        .inventory-grid,
        .flow-grid,
        .placeholder-grid,
        .planner-grid,
        .recipe-grid,
        .settings-grid {
            display: grid;
            gap: 12px;
        }
        .inventory-card,
        .scan-card,
        .placeholder-card,
        .planner-card,
        .recipe-card,
        .settings-card {
            padding: 16px;
            border-radius: 20px;
            border: 1px solid var(--line);
            background: var(--panel-strong);
        }
        .inventory-card {
            overflow: hidden;
            background:
                linear-gradient(180deg, rgba(255,252,247,0.98) 0%, rgba(246,238,227,0.92) 100%);
        }
        .inventory-visual {
            display: grid;
            grid-template-columns: 86px minmax(0, 1fr);
            gap: 14px;
            align-items: center;
            margin-bottom: 14px;
        }
        .inventory-image,
        .inventory-image-fallback {
            width: 86px;
            height: 86px;
            border-radius: 18px;
        }
        .inventory-image {
            object-fit: cover;
            background: #fff;
            border: 1px solid rgba(20,35,29,0.08);
        }
        .inventory-image-fallback {
            display: grid;
            place-items: center;
            background: linear-gradient(135deg, rgba(47,106,86,0.12), rgba(236,226,212,0.8));
            color: var(--accent);
            font-family: "Iowan Old Style", "Palatino Linotype", serif;
            font-size: 28px;
        }
        .inventory-top,
        .scan-top {
            display: flex;
            justify-content: space-between;
            align-items: start;
            gap: 12px;
        }
        .inventory-name,
        .scan-name {
            margin: 0;
            font-size: 17px;
            line-height: 1.2;
        }
        .inventory-code,
        .scan-time,
        .placeholder-copy {
            margin: 4px 0 0;
            color: var(--muted);
            font-size: 13px;
            line-height: 1.4;
        }
        .inventory-brand {
            margin: 6px 0 0;
            color: var(--accent);
            font-size: 13px;
            font-weight: 600;
        }
        .state-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border-radius: 999px;
            padding: 7px 10px;
            font-size: 12px;
            font-weight: 700;
            white-space: nowrap;
        }
        .state-ok {
            background: rgba(47, 106, 86, 0.12);
            color: var(--ok);
        }
        .state-low {
            background: rgba(199, 119, 47, 0.14);
            color: var(--warning);
        }
        .state-empty {
            background: rgba(143, 79, 79, 0.14);
            color: var(--berry);
        }
        .inventory-meta,
        .scan-meta {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px;
            margin-top: 14px;
        }
        .meta-block {
            padding: 12px;
            border-radius: 16px;
            background: rgba(236, 226, 212, 0.58);
        }
        .meta-label {
            display: block;
            color: var(--muted);
            font-size: 11px;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            margin-bottom: 6px;
        }
        .meta-value {
            font-size: 22px;
            font-weight: 700;
            line-height: 1;
        }
        .nutrition-strip {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 8px;
            margin-top: 12px;
        }
        .nutrition-chip {
            padding: 10px;
            border-radius: 14px;
            background: rgba(255,255,255,0.72);
            border: 1px solid rgba(20,35,29,0.06);
        }
        .nutrition-chip strong {
            display: block;
            font-size: 15px;
            line-height: 1.1;
        }
        .nutrition-chip span {
            display: block;
            margin-top: 4px;
            font-size: 11px;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: var(--muted);
        }
        .nutrition-note {
            margin-top: 10px;
            color: var(--muted);
            font-size: 12px;
        }
        .inventory-card-actions {
            margin-top: 10px;
            display: flex;
            justify-content: flex-end;
        }
        .inventory-add-shopping {
            border: 1px solid var(--line);
            background: rgba(47, 106, 86, 0.1);
            color: var(--accent);
            border-radius: 10px;
            padding: 6px 10px;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
        }
        .inventory-add-shopping:disabled {
            opacity: 0.6;
            cursor: wait;
        }
        .inventory-suggestion-row {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 8px;
            align-items: center;
            padding: 9px 10px;
            border-top: 1px solid rgba(20,35,29,0.08);
            cursor: pointer;
        }
        .inventory-suggestion-row:first-child {
            border-top: 0;
        }
        .inventory-suggestion-row:active {
            background: rgba(47,106,86,0.08);
        }
        .inventory-suggestion-name {
            font-size: 13px;
            font-weight: 700;
            line-height: 1.2;
        }
        .inventory-suggestion-meta {
            margin-top: 2px;
            font-size: 11px;
            color: var(--muted);
            line-height: 1.2;
        }
        .inventory-suggestion-add {
            border: 1px solid var(--line);
            background: #fff;
            color: var(--accent);
            border-radius: 8px;
            padding: 5px 8px;
            font-size: 11px;
            font-weight: 700;
            cursor: pointer;
        }
        .inventory-scan-box {
            margin: 0 0 12px;
            padding: 12px;
            border: 1px dashed var(--line);
            border-radius: 14px;
            background: rgba(255,252,247,0.9);
        }
        .inventory-scan-title {
            margin: 0;
            font-size: 14px;
            font-weight: 800;
            color: var(--ink);
        }
        .inventory-scan-copy {
            margin: 4px 0 0;
            font-size: 12px;
            color: var(--muted);
            line-height: 1.4;
        }
        .inventory-scan-status {
            margin-top: 10px;
            font-size: 12px;
            color: var(--muted);
        }
        .inventory-scan-status.err {
            color: var(--berry);
        }
        .inventory-scan-result {
            margin-top: 8px;
            padding-top: 8px;
            border-top: 1px solid rgba(20,35,29,0.08);
            display: grid;
            gap: 8px;
        }
        .inventory-scan-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        .scan-action {
            border: 1px solid var(--line);
            background: #fff;
            color: var(--ink);
            border-radius: 10px;
            padding: 7px 10px;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
        }
        .scan-action.primary {
            background: rgba(47,106,86,0.12);
            color: var(--accent);
        }
        .scan-mode-toggle {
            display: inline-flex;
            border: 1px solid var(--line);
            border-radius: 10px;
            overflow: hidden;
            background: #fff;
        }
        .scan-mode-btn {
            border: 0;
            background: transparent;
            padding: 7px 12px;
            font-size: 12px;
            font-weight: 700;
            color: var(--muted);
            cursor: pointer;
        }
        .scan-mode-btn.active {
            background: rgba(47,106,86,0.14);
            color: var(--accent);
        }
        .inventory-scan-debug {
            margin-top: 8px;
            padding: 8px;
            border-radius: 10px;
            border: 1px solid rgba(20,35,29,0.08);
            background: rgba(255,255,255,0.75);
            font-size: 11px;
            color: var(--muted);
            line-height: 1.35;
            max-height: 120px;
            overflow: auto;
            white-space: pre-wrap;
        }
        #inventoryScanInput {
            height: 36px;
        }
        #inventoryCameraPreview {
            background: #000;
            max-height: 260px;
            object-fit: cover;
        }
        @media (max-width: 640px) {
            #inventoryScanInput {
                min-width: 100%;
            }
            #inventoryCameraStart,
            #inventoryCameraStop,
            #inventoryScanSubmit {
                flex: 1;
            }
        }
        .scan-type {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 60px;
            padding: 8px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }
        .scan-in {
            background: rgba(47, 106, 86, 0.12);
            color: var(--ok);
        }
        .scan-out {
            background: rgba(143, 79, 79, 0.12);
            color: var(--berry);
        }
        .placeholder-card {
            background:
                linear-gradient(180deg, rgba(255,252,247,0.94) 0%, rgba(245,237,226,0.88) 100%);
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .placeholder-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(20, 35, 29, 0.12);
        }
        .placeholder-card h3 {
            margin: 0 0 8px;
            font-size: 20px;
        }
        .planner-card,
        .recipe-card,
        .settings-card {
            background:
                linear-gradient(180deg, rgba(255,252,247,0.96) 0%, rgba(244,236,226,0.9) 100%);
        }
        .planner-card h3,
        .recipe-card h3,
        .settings-card h3 {
            margin: 0 0 8px;
            font-size: 20px;
        }
        .planner-card p,
        .recipe-card p,
        .settings-card p {
            margin: 0;
            color: var(--muted);
            line-height: 1.5;
        }
        .planner-meta,
        .settings-meta {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px;
            margin-top: 14px;
        }
        .mini-stat {
            padding: 12px;
            border-radius: 16px;
            background: rgba(236, 226, 212, 0.58);
        }
        .mini-stat strong {
            display: block;
            font-size: 20px;
            line-height: 1.1;
        }
        .mini-stat span {
            display: block;
            margin-top: 6px;
            color: var(--muted);
            font-size: 11px;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }
        .recipe-list {
            display: grid;
            gap: 10px;
            margin-top: 14px;
        }
        .recipe-line {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            padding: 12px 0;
            border-top: 1px solid rgba(20,35,29,0.08);
        }
        .recipe-line:first-child {
            border-top: 0;
            padding-top: 0;
        }
        .recipe-line strong {
            display: block;
            font-size: 15px;
            line-height: 1.25;
        }
        .recipe-line span {
            color: var(--muted);
            font-size: 13px;
            line-height: 1.4;
            text-align: right;
        }
        .ai-panel {
            margin-top: 12px;
            padding: 14px;
            border-radius: 16px;
            border: 1px solid rgba(20,35,29,0.08);
            background: rgba(255,255,255,0.72);
        }
        .ai-panel-head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
        }
        .ai-panel-head strong {
            font-size: 14px;
        }
        .ai-action {
            border: 0;
            border-radius: 999px;
            padding: 8px 12px;
            font-size: 12px;
            font-weight: 700;
            color: #fbf7ef;
            background: var(--text);
            cursor: pointer;
        }
        .ai-action:disabled {
            opacity: 0.55;
            cursor: not-allowed;
        }
        .ai-status {
            color: var(--muted);
            font-size: 12px;
            line-height: 1.4;
            margin-bottom: 10px;
        }
        .ai-result {
            display: grid;
            gap: 10px;
        }
        .ai-idea {
            padding: 10px;
            border-radius: 12px;
            background: rgba(236,226,212,0.5);
        }
        .ai-idea h4 {
            margin: 0 0 6px;
            font-size: 15px;
        }
        .ai-idea p {
            margin: 0;
            font-size: 13px;
            line-height: 1.45;
            color: var(--muted);
        }
        .ai-list {
            margin: 8px 0 0;
            padding-left: 18px;
            color: var(--muted);
            font-size: 12px;
            line-height: 1.45;
        }
        .admin-panel {
            margin-top: 10px;
            padding: 14px;
            border-radius: 16px;
            border: 1px solid rgba(20,35,29,0.08);
            background: rgba(255,255,255,0.7);
            display: grid;
            gap: 12px;
        }
        .admin-row {
            display: grid;
            gap: 8px;
        }
        .admin-grid {
            display: grid;
            gap: 8px;
        }
        .admin-grid.cols-2 {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
        .admin-grid.cols-3 {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }
        .admin-label {
            display: block;
            color: var(--muted);
            font-size: 11px;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            margin-bottom: 4px;
        }
        .admin-input,
        .admin-select {
            width: 100%;
            border: 1px solid rgba(20,35,29,0.12);
            border-radius: 10px;
            padding: 10px 11px;
            font-size: 14px;
            background: #fff;
            color: var(--text);
        }
        .admin-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }
        .admin-button {
            border: 0;
            border-radius: 999px;
            padding: 8px 12px;
            font-size: 12px;
            font-weight: 700;
            color: #fbf7ef;
            background: var(--text);
            cursor: pointer;
        }
        .admin-button.alt {
            color: var(--text);
            background: rgba(236,226,212,0.95);
            border: 1px solid rgba(20,35,29,0.1);
        }
        .admin-status {
            font-size: 12px;
            color: var(--muted);
            line-height: 1.45;
        }
        .admin-status.err {
            color: #a12626;
        }
        .admin-list {
            padding: 10px;
            border-radius: 12px;
            background: rgba(236,226,212,0.5);
            font-size: 12px;
            color: var(--muted);
            line-height: 1.5;
            max-height: 150px;
            overflow: auto;
            white-space: pre-wrap;
        }
        .admin-divider {
            border-top: 1px solid rgba(20,35,29,0.08);
            padding-top: 10px;
        }
        @media (max-width: 520px) {
            .admin-grid.cols-2,
            .admin-grid.cols-3 {
                grid-template-columns: 1fr;
            }
        }
        .placeholder-card p {
            margin: 0;
            color: var(--muted);
            line-height: 1.5;
        }
        .store-cards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 16px;
            margin: 20px 0;
        }
        .store-card {
            display: flex;
            flex-direction: column;
            padding: 20px 16px;
            border-radius: 16px;
            border: 2px solid transparent;
            transition: all 0.25s cubic-bezier(0.34,1.56,0.64,1);
            cursor: pointer;
            position: relative;
            overflow: hidden;
        }
        .store-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            opacity: 0.04;
            z-index: -1;
        }
        .store-card:hover {
            transform: translateY(-4px);
            border-color: rgba(0,0,0,0.1);
            box-shadow: 0 12px 24px rgba(0,0,0,0.12);
        }
        .store-card-netto {
            background: linear-gradient(135deg, #ffd54f 0%, #ffca28 100%);
            color: #332200;
        }
        .store-card-netto .store-card-icon {
            background: rgba(255,255,255,0.3);
            color: #d48806;
        }
        .store-card-kvickly {
            background: linear-gradient(135deg, #ef5350 0%, #d32f2f 100%);
            color: white;
        }
        .store-card-kvickly .store-card-icon {
            background: rgba(255,255,255,0.25);
            color: white;
        }
        .store-card-365 {
            background: linear-gradient(135deg, #43a047 0%, #2e7d32 100%);
            color: white;
        }
        .store-card-365 .store-card-icon {
            background: rgba(255,255,255,0.25);
            color: white;
        }
        .store-card-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 12px;
        }
        .store-card-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 900;
            font-size: 22px;
            flex-shrink: 0;
        }
        .store-card-title {
            margin: 0;
            font-size: 18px;
            font-weight: 800;
            line-height: 1;
        }
        .store-card-copy {
            margin: 0 0 12px 0;
            font-size: 12px;
            opacity: 0.85;
            line-height: 1.4;
            flex-grow: 1;
        }
        .store-card-cta {
            font-size: 13px;
            font-weight: 700;
            opacity: 0.75;
            margin-top: auto;
        }
        @media (max-width: 640px) {
            .store-cards-grid {
                grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
                gap: 12px;
            }
            .store-card-icon {
                width: 40px;
                height: 40px;
                font-size: 18px;
            }
            .store-card-title {
                font-size: 15px;
            }
        }
        .shopping-top-bar {
            display: grid;
            grid-template-columns: 1fr;
            gap: 16px;
            align-items: start;
            margin-bottom: 24px;
        }
        .search-container {
            position: relative;
            display: flex;
            flex-direction: column;
        }
        .shopping-top-sidebar {
            display: flex;
            flex-direction: column;
            width: 100%;
        }
        .shopping-layout {
            display: grid;
            grid-template-columns: 1fr;
            gap: 24px;
            align-items: start;
        }
        .shopping-main {
            display: grid;
            gap: 20px;
        }
        .shopping-sidebar {
            display: flex;
            flex-direction: column;
        }
        .shopping-sticky {
            position: sticky;
            top: 80px;
            background: var(--panel-strong);
            padding: 16px;
            border-radius: 20px;
            border: 1px solid var(--line);
            box-shadow: 0 2px 8px rgba(0,0,0,0.07);
            max-height: calc(100vh - 100px);
            overflow-y: auto;
        }
        @media (max-width: 1024px) {
            .shopping-top-bar {
                grid-template-columns: 1fr;
            }
            .shopping-layout {
                grid-template-columns: 1fr;
            }
        }
        .inventory-badges {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-top: 6px;
        }
        .type-badge,
        .location-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 9px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.05em;
        }
        .type-badge {
            background: rgba(47,106,86,0.1);
            color: var(--accent);
        }
        .location-badge {
            background: rgba(236,226,212,0.9);
            color: var(--muted);
        }
        .ingredient-create-panel summary::-webkit-details-marker {
            display: none;
        }
        .shopping-compact {
            border: 1px solid var(--line);
            border-radius: 14px;
            background: rgba(255,252,247,0.92);
            overflow: hidden;
        }
        .shopping-list {
            list-style: none;
            margin: 0;
            padding: 0;
        }
        .shopping-row {
            display: grid;
            grid-template-columns: auto minmax(0, 1fr) auto auto;
            gap: 10px;
            align-items: center;
            padding: 8px 12px;
            border-top: 1px solid rgba(20,35,29,0.08);
        }
        .shopping-row:first-child {
            border-top: 0;
        }
        .shopping-row.checked {
            opacity: 0.72;
        }
        .shopping-row-main {
            min-width: 0;
            cursor: pointer;
            border-radius: 8px;
            padding: 2px 0;
        }
        .shopping-row-main:active {
            background: rgba(47,106,86,0.08);
        }
        .shopping-name {
            margin: 0;
            font-size: 14px;
            line-height: 1.2;
            font-weight: 700;
        }
        .shopping-row.checked .shopping-name {
            text-decoration: line-through;
        }
        .shopping-meta {
            margin-top: 3px;
            font-size: 11px;
            color: var(--muted);
            white-space: normal;
        }
        .shopping-qty {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 34px;
            height: 24px;
            border-radius: 999px;
            background: rgba(47,106,86,0.12);
            color: var(--accent);
            font-size: 12px;
            font-weight: 800;
            padding: 0 9px;
        }
        .shopping-pills {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            justify-self: start;
        }
        .shopping-price {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 42px;
            height: 24px;
            border-radius: 999px;
            padding: 0 8px;
            font-size: 11px;
            font-weight: 700;
            background: rgba(20,35,29,0.08);
            color: var(--ink);
        }
        .shopping-price.missing {
            background: rgba(20,35,29,0.05);
            color: var(--muted);
            font-weight: 600;
        }
        .shopping-check,
        .shopping-delete {
            min-width: 34px;
            height: 34px;
            border-radius: 10px;
            border: 1px solid var(--line);
            background: #fff;
            cursor: pointer;
            font-size: 13px;
            font-weight: 800;
            color: var(--muted);
        }
        .shopping-check {
            color: var(--accent);
        }
        .shopping-row.checked .shopping-check {
            background: rgba(47,106,86,0.14);
            border-color: rgba(47,106,86,0.35);
        }
        .shopping-delete {
            color: #8a2f2f;
        }
        .shopping-check:disabled,
        .shopping-delete:disabled {
            opacity: 0.5;
            cursor: wait;
        }
        @media (max-width: 640px) {
            .shopping-row {
                grid-template-columns: auto minmax(0, 1fr) auto;
                gap: 8px;
            }
            .shopping-pills {
                justify-self: start;
                grid-column: 2;
            }
            .shopping-delete {
                grid-column: 3;
                grid-row: span 2;
                align-self: stretch;
            }
        }
        .stack {
            display: grid;
            gap: 10px;
        }
        .collapsed-note {
            margin: 0 0 14px;
            color: var(--muted);
            font-size: 14px;
            line-height: 1.5;
        }
        .scan-disclosure {
            border-top: 1px solid var(--line);
            padding-top: 12px;
        }
        .scan-disclosure summary {
            cursor: pointer;
            list-style: none;
            font-weight: 600;
            color: var(--accent);
        }
        .scan-disclosure summary::-webkit-details-marker {
            display: none;
        }
        .scan-disclosure[open] summary {
            margin-bottom: 12px;
        }
        .scan-quiet {
            margin-top: 10px;
            color: var(--muted);
            font-size: 12px;
        }
        .empty {
            padding: 18px;
            border-radius: 20px;
            border: 1px dashed rgba(20,35,29,0.16);
            background: rgba(255,252,247,0.5);
            color: var(--muted);
            line-height: 1.5;
        }
        .bottom-nav {
            position: fixed;
            left: 50%;
            transform: translateX(-50%);
            bottom: 12px;
            z-index: 2;
            width: min(96vw, 540px);
            display: grid;
            grid-template-columns: repeat(5, minmax(0, 1fr));
            gap: 8px;
            padding: 10px;
            border: 1px solid rgba(255,255,255,0.72);
            border-radius: 24px;
            background: rgba(20, 35, 29, 0.9);
            color: #f5efe6;
            box-shadow: 0 14px 30px rgba(20,35,29,0.26);
            backdrop-filter: blur(22px);
        }
        .layout.single-page {
            grid-template-columns: 1fr;
        }
        .nav-item {
            display: grid;
            justify-items: center;
            gap: 4px;
            padding: 10px 0;
            border-radius: 16px;
            color: rgba(245,239,230,0.8);
            font-size: 12px;
        }
        .nav-item strong {
            font-size: 15px;
            line-height: 1;
        }
        .nav-item span {
            text-align: center;
        }
        .nav-item.active {
            background: rgba(255,255,255,0.09);
            color: #fffaf3;
        }
        @media (min-width: 760px) {
            .app {
                padding: 28px 24px 36px;
            }
            .hero {
                grid-template-columns: 1.4fr 0.9fr;
                align-items: start;
                padding: 22px;
            }
            .status-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }
            .context-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }
            .inventory-grid,
            .placeholder-grid,
            .planner-grid,
            .recipe-grid,
            .settings-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
            .flow-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
            .layout {
                grid-template-columns: minmax(0, 1.35fr) minmax(300px, 0.85fr);
                align-items: start;
            }
        }
        @media (min-width: 1080px) {
            .inventory-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }
            .flow-grid {
                grid-template-columns: 1fr;
            }
            .planner-grid,
            .recipe-grid,
            .settings-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }
        .bottom-nav {
            display: none;
        }
    </style>
</head>
<body class="auth-pending">
<div id="authGate" class="auth-gate">
    <div class="auth-card">
        <h2>Log ind</h2>
        <p>Skriv dine initialer. Vi sender en 2FA SMS-kode automatisk. Indtast derefter koden for at fortsætte.</p>
        <div class="auth-grid">
            <input id="gateInitials" type="text" maxlength="10" placeholder="Initialer (fx CMP)">
            <input id="gateChallenge" type="text" placeholder="Challenge ID" readonly>
            <input id="gateCode" type="text" maxlength="6" inputmode="numeric" placeholder="SMS kode (6 cifre)">
        </div>
        <div class="auth-status" id="gateStatus">2FA påkrævet.</div>
    </div>
</div>

<main class="app" id="top" aria-hidden="true">
    <header class="masthead">
        <div class="topbar">
            <div class="brand">
                <div class="brand-mark">M</div>
                <div>
                    <p class="brand-name">Mad</p>
                    <p class="brand-subtitle">Nordic household HMI</p>
                </div>
            </div>
            <div class="sync-pill">
                <span class="sync-dot"></span>
                <span id="syncStatus">Forbinder...</span>
            </div>
        </div>

        <nav class="page-menu" aria-label="Sidemenu">
            <a class="page-link<?= $currentPage === 'overblik' ? ' active' : '' ?>" href="<?= htmlspecialchars($buildPageUrl('overblik'), ENT_QUOTES, 'UTF-8') ?>">Overblik</a>
            <a class="page-link<?= $currentPage === 'madplan' ? ' active' : '' ?>" href="<?= htmlspecialchars($buildPageUrl('madplan'), ENT_QUOTES, 'UTF-8') ?>">Madplan</a>
            <a class="page-link<?= $currentPage === 'opskrifter' ? ' active' : '' ?>" href="<?= htmlspecialchars($buildPageUrl('opskrifter'), ENT_QUOTES, 'UTF-8') ?>">Opskrifter</a>
            <a class="page-link<?= $currentPage === 'lager' ? ' active' : '' ?>" href="<?= htmlspecialchars($buildPageUrl('lager'), ENT_QUOTES, 'UTF-8') ?>">Lager</a>
            <a class="page-link<?= $currentPage === 'indkoeb' ? ' active' : '' ?>" href="<?= htmlspecialchars($buildPageUrl('indkoeb'), ENT_QUOTES, 'UTF-8') ?>">Indkob</a>
            <a class="page-link<?= $currentPage === 'opsaetning' ? ' active' : '' ?> admin-only" id="setupMenuLink" href="opsaetning.php" style="display:none;">Opsaetning</a>
        </nav>

        <section class="context-strip" aria-label="Husstand og datakilder" id="overviewContext">
            <div class="context-grid">
                <article class="context-card">
                    <span class="context-label">Aktiv husstand</span>
                    <div class="context-value" id="householdLabel"><?= htmlspecialchars($householdName, ENT_QUOTES, 'UTF-8') ?></div>
                    <p class="context-copy">HMI'et henter allerede lagerdata pr. husstand. Navigationen er lagt, så opskrifter, madplan og indkøb senere kan holdes adskilt pr. hjem uden at ændre hele fronten igen.</p>
                </article>
                <article class="context-card">
                    <span class="context-label">Datagrundlag</span>
                    <div class="context-value">Open Food Facts nu, Frida/DTU næste lag</div>
                    <p class="context-copy">Open Food Facts er stadig hurtigste berigelse på scan. Frida/DTU er tænkt ind som mere kontrolleret næringskilde, når vi vil kvalitetssikre danske fødevarekort og opskriftsberegninger.</p>
                    <div class="source-line">
                        <span class="source-pill">Live: Open Food Facts</span>
                        <span class="source-pill planned">Planlagt: Frida/DTU</span>
                    </div>
                </article>
                <article class="context-card">
                    <span class="context-label">Informationsarkitektur</span>
                    <div class="context-value">Madplan først, scanning bagefter</div>
                    <p class="context-copy">Skærmen er nu organiseret efter README-retningen: madplan, opskrifter, lager, indkøb og opsætning. Scanlog er stadig tilgængelig, men ikke længere hovedoplevelsen.</p>
                </article>
            </div>
        </section>

        <section class="hero" id="overviewHero">
            <div class="hero-copy">
                <div class="eyebrow">Mobil HMI ramme</div>
                <h1>Køkkenets rolige overblik.</h1>
                <p>En mobil-først styreflade til madplan, opskrifter, lager og indkøb. Den bruger stadig live lagerdata, men navigationen er nu lagt som et rigtigt husstandsprodukt frem for et scanner-dashboard.</p>
                <div class="hero-actions">
                    <button class="action" type="button" id="refreshButton">Opdater nu</button>
                    <a class="action ghost" href="#madplanSection">Se madplan</a>
                    <a class="action ghost" href="#inventorySection">Se lager</a>
                </div>
            </div>
            <aside class="hero-aside">
                <div class="card signal-card">
                    <span class="signal-label">Husets rytme</span>
                    <div class="signal-value" id="heroSummaryValue">0 varer</div>
                    <div class="signal-meta" id="heroSummaryMeta">Vi henter live data fra lageret og viser fødevarer først.</div>
                </div>
                <div class="card signal-card">
                    <span class="signal-label">Næste fokus</span>
                    <div class="signal-value" id="latestMovementValue">Madplan</div>
                    <div class="signal-meta" id="latestMovementMeta">Fødevarekort, opskrifter og indkøb hænger nu sammen som menu, så den videre udbygning kan ske uden ny informationsarkitektur.</div>
                </div>
            </aside>
        </section>
    </header>

    <section class="status-grid" aria-label="Statusoversigt" id="overviewStats">
        <article class="metric">
            <div class="metric-name">Produkter i hjemmet</div>
            <div class="metric-value" id="productCount">0</div>
            <p class="metric-note" id="productSummary">Antal kendte varer i lageret lige nu.</p>
        </article>
        <article class="metric">
            <div class="metric-name">Lav beholdning</div>
            <div class="metric-value" id="lowStockCount">0</div>
            <p class="metric-note" id="lowStockSummary">Varer under eller ved minimum kan blive prioriteret til indkøb.</p>
        </article>
        <article class="metric">
            <div class="metric-name">Seneste bevægelser</div>
            <div class="metric-value" id="scanCount">0</div>
            <p class="metric-note" id="scanSummary">Scannerflow gemmes væk, men er stadig tilgængeligt ved behov.</p>
        </article>
    </section>

    <div class="layout" id="mainLayout">
        <section class="card section" id="madplanSection">
            <div class="section-head">
                <div>
                    <p class="section-kicker">Madplan</p>
                    <h2 class="section-title">Ugens rytme bygges omkring husstanden</h2>
                </div>
                <div class="chip" id="planChip">Madplan først</div>
            </div>
            <p class="section-copy">README-retningen siger madplan før scanning. Derfor er første sektion nu reserveret til den ugevisning, der senere skal koble opskrifter, lagerstatus og indkøb sammen for den valgte husstand.</p>
            <div class="planner-grid">
                <article class="planner-card">
                    <h3>Dagens beslutninger</h3>
                    <p>Her kommer dagens måltider, lagertræk og eventuelle mangler. Skabelonen er nu på plads, så vi kan fylde den med rigtige opskrifter næste gang.</p>
                    <div class="planner-meta">
                        <div class="mini-stat">
                            <strong id="plannedMealsValue">3</strong>
                            <span>Planvinduer</span>
                        </div>
                        <div class="mini-stat">
                            <strong id="attentionItemsValue">0</strong>
                            <span>Kræver indkøb</span>
                        </div>
                    </div>
                </article>
                <article class="planner-card">
                    <h3>Frida/DTU-spor</h3>
                    <p>Open Food Facts løfter scan-flowet nu. Frida/DTU bør være næste datalag for danske referenceværdier, især når madplan og opskrifter skal kunne regne mere præcist på ernæring pr. husstand.</p>
                    <div class="planner-meta">
                        <div class="mini-stat">
                            <strong>Nu</strong>
                            <span>OFF til hurtige opslag</span>
                        </div>
                        <div class="mini-stat">
                            <strong>Snart</strong>
                            <span>Frida/DTU til kvalitet</span>
                        </div>
                    </div>
                    <div class="ai-panel">
                        <div class="ai-panel-head">
                            <strong>AI madplansforslag (beta)</strong>
                            <button type="button" class="ai-action" id="aiSuggestButton">Hent forslag</button>
                        </div>
                        <div class="ai-status" id="aiStatus">Kræver login + AI aktiveret i backend.</div>
                        <div class="ai-result" id="aiResult"></div>
                    </div>
                </article>
            </div>
        </section>

        <div class="stack">
            <section class="card section" id="recipesSection">
                <div class="section-head">
                    <div>
                        <p class="section-kicker">Opskrifter</p>
                        <h2 class="section-title">Opskrifter som fælles arbejdslag</h2>
                    </div>
                    <div class="chip">Delbar mellem husstande</div>
                </div>
                <div class="recipe-grid">
                    <article class="recipe-card">
                        <h3>Opskriftssamling</h3>
                        <p>Opskrifter bør kunne leve på tværs af husstande, mens lager og beholdning forbliver private. Det matcher README-kravet om delt opskriftssamling med separat lagerantal.</p>
                        <div class="recipe-list">
                            <div class="recipe-line">
                                <strong>Hverdagsretter</strong>
                                <span>lagerkobling og hurtige mangellister</span>
                            </div>
                            <div class="recipe-line">
                                <strong>Fryseretter</strong>
                                <span>lokation og portionslogik</span>
                            </div>
                            <div class="recipe-line">
                                <strong>Delte favoritter</strong>
                                <span>samme opskrift, forskellige husstande</span>
                            </div>
                        </div>
                    </article>
                </div>
            </section>

            <section class="card section" id="inventorySection">
                <div class="section-head">
                    <div>
                        <p class="section-kicker">Lagerblik</p>
                        <h2 class="section-title">Hvad mangler snart opmærksomhed?</h2>
                    </div>
                    <div class="chip" id="inventoryChip">0 i balance</div>
                </div>
                <div class="inventory-scan-box" id="inventoryScanBox">
                    <h3 class="inventory-scan-title">Scan på Lager-siden</h3>
                    <p class="inventory-scan-copy">Du kan blive stående her og scanne. Vi viser med det samme om varen allerede er oprettet, eller om du vil oprette den.</p>
                    <div class="inventory-scan-actions" style="margin-top: 8px;">
                        <div class="scan-mode-toggle" role="group" aria-label="Lager retning">
                            <button type="button" class="scan-mode-btn active" id="scanModeIn" data-scan-mode="in">Går ind</button>
                            <button type="button" class="scan-mode-btn" id="scanModeOut" data-scan-mode="out">Går ud</button>
                        </div>
                    </div>
                    <div class="inventory-scan-actions" style="margin-top: 10px;">
                        <input class="admin-input" id="inventoryScanInput" type="text" inputmode="numeric" autocomplete="off" placeholder="Scan eller skriv barcode her" style="flex:1; min-width: 180px;" />
                        <button type="button" class="scan-action" id="inventoryScanSubmit">Registrer</button>
                        <button type="button" class="scan-action primary" id="inventoryCameraStart">Kamera scan</button>
                        <button type="button" class="scan-action" id="inventoryCameraStop" style="display:none;">Stop kamera</button>
                    </div>
                    <video id="inventoryCameraPreview" playsinline muted style="display:none; width:100%; margin-top:10px; border-radius:12px; border:1px solid var(--line);"></video>
                    <div class="inventory-scan-status" id="inventoryScanStatus">Venter på scanning...</div>
                    <div class="inventory-scan-debug" id="inventoryScanDebug">Diagnose: venter på tastetryk fra scanner...</div>
                    <div class="inventory-scan-result" id="inventoryScanResult" style="display:none;"></div>
                </div>
                <details class="planner-card ingredient-create-panel" style="margin-bottom:12px;" id="ingredientCreateDetails">
                    <summary style="cursor:pointer; font-weight:700; font-size:16px; list-style:none; display:flex; justify-content:space-between; align-items:center;">
                        <span>Ny ingrediens</span>
                        <span class="chip" style="font-size:11px;">+ Tilføj</span>
                    </summary>
                    <p style="margin:10px 0 14px; color:var(--muted); font-size:13px;">Opret ingrediens manuelt eller slå op via barcode. Udløbsdato registreres automatisk ved ind-scanning.</p>

                    <div class="admin-row" style="margin-bottom:10px;">
                        <span class="admin-label">Grundoplysninger</span>
                        <div class="admin-grid cols-2">
                            <input class="admin-input" id="ingredientBarcode" type="text" placeholder="Barcode (scan eller skriv)">
                            <input class="admin-input" id="ingredientName" type="text" placeholder="Navn (fx Havregryn)">
                            <input class="admin-input" id="ingredientBrand" type="text" placeholder="Brand (fx Amo)">
                            <input class="admin-input" id="ingredientImageUrl" type="url" placeholder="Billede URL (valgfri)">
                        </div>
                    </div>

                    <div class="admin-grid cols-2" style="margin-bottom:10px;">
                        <div>
                            <span class="admin-label">Produkttype</span>
                            <select class="admin-select" id="ingredientProductType">
                                <option value="tørvare">Tørvare</option>
                                <option value="ferskvare">Ferskvare</option>
                                <option value="mejeri">Mejeri</option>
                                <option value="kød">Kød</option>
                                <option value="fisk">Fisk &amp; skaldyr</option>
                                <option value="frugt_groent">Frugt &amp; grønt</option>
                                <option value="frostvare">Frostvare</option>
                                <option value="krydderier">Krydderier &amp; urter</option>
                                <option value="drikke">Drikke</option>
                                <option value="konserves">Konserves &amp; dåse</option>
                                <option value="brød">Brød &amp; bageri</option>
                                <option value="andet" selected>Andet</option>
                            </select>
                        </div>
                        <div>
                            <span class="admin-label">Lokation</span>
                            <select class="admin-select" id="ingredientLocationId">
                                <option value="">Hentes automatisk…</option>
                            </select>
                        </div>
                    </div>

                    <div class="admin-row" style="margin-bottom:10px;">
                        <span class="admin-label">Beholdning</span>
                        <div class="admin-grid cols-2">
                            <input class="admin-input" id="ingredientQuantity" type="number" step="0.1" min="0" placeholder="Startbeholdning (antal)">
                            <input class="admin-input" id="ingredientMinimum" type="number" step="0.1" min="0" placeholder="Minimum (alarm under)">
                            <input class="admin-input" id="ingredientWeightGrams" type="number" step="1" min="0" placeholder="Vægt (gram)">
                        </div>
                    </div>

                    <div class="admin-row" style="margin-bottom:12px;">
                        <span class="admin-label">Priser (valgfri)</span>
                        <div class="admin-grid cols-2">
                            <input class="admin-input" id="ingredientStore" type="text" placeholder="Butik (standardpris)">
                            <input class="admin-input" id="ingredientPrice" type="number" step="0.01" min="0" placeholder="Standardpris (kr)">
                            <input class="admin-input" id="ingredientOfferStore" type="text" placeholder="Butik (tilbud)">
                            <input class="admin-input" id="ingredientOfferPrice" type="number" step="0.01" min="0" placeholder="Tilbudspris (kr)">
                            <input class="admin-input" id="ingredientOfferValidTo" type="date" title="Tilbud gyldig til">
                        </div>
                    </div>

                    <div class="admin-actions">
                        <button class="admin-button alt" type="button" id="ingredientLookupBtn">Slå op barcode</button>
                        <button class="admin-button" type="button" id="ingredientCreateBtn">Opret ingrediens</button>
                        <button class="admin-button alt" type="button" id="ingredientCancelEditBtn" style="display:none;">Annuller redigering</button>
                    </div>
                    <div class="admin-status" id="ingredientCreateStatus" style="margin-top:8px;"></div>
                </details>
                <div class="inventory-grid" id="productsBody"></div>
            </section>

            <section class="card section" id="shoppingSection">
                <div class="section-head">
                    <div>
                        <p class="section-kicker">Indkøb</p>
                        <h2 class="section-title">Aktiv indkøbsseddel</h2>
                    </div>
                    <div class="chip" id="shoppingChip">0 på sedlen</div>
                </div>
                <p class="section-copy">Varer du allerede har lagt på indkøbssedlen vises her. Tilbud på dine mangler vises nedenunder.</p>
                
                <div class="subsection-head">
                    <h3 class="subsection-title">Tilbud i aviser</h3>
                </div>
                <div class="store-cards-grid" id="storesGrid">
                    <a href="<?= htmlspecialchars($nettoPagePath) ?>?household_id=<?= htmlspecialchars((string) $householdId) ?>" class="store-card store-card-netto" style="text-decoration: none; color: inherit;">
                        <div class="store-card-header">
                            <div class="store-card-icon">N</div>
                            <h3 class="store-card-title">Netto</h3>
                        </div>
                        <p class="store-card-copy">Se aktuelle tilbud og udsalg</p>
                        <div class="store-card-cta">Gennemse →</div>
                    </a>
                    <a href="<?= htmlspecialchars($kvicklyPagePath) ?>?household_id=<?= htmlspecialchars((string) $householdId) ?>" class="store-card store-card-kvickly" style="text-decoration: none; color: inherit;">
                        <div class="store-card-header">
                            <div class="store-card-icon">K</div>
                            <h3 class="store-card-title">Kvickly</h3>
                        </div>
                        <p class="store-card-copy">Se aktuelle tilbud og udsalg</p>
                        <div class="store-card-cta">Gennemse →</div>
                    </a>
                    <a href="<?= htmlspecialchars($discount365PagePath) ?>?household_id=<?= htmlspecialchars((string) $householdId) ?>" class="store-card store-card-365" style="text-decoration: none; color: inherit;">
                        <div class="store-card-header">
                            <div class="store-card-icon">365</div>
                            <h3 class="store-card-title">365discount</h3>
                        </div>
                        <p class="store-card-copy">Se aktuelle tilbud og udsalg</p>
                        <div class="store-card-cta">Gennemse →</div>
                    </a>
                </div>
                
                <div class="shopping-top-bar">
                    <div class="search-container">
                        <input type="text" id="inventoryShoppingSearch" placeholder="Tilføj fra lager (skriv produktnavn)..." style="padding: 8px 12px; border-radius: 12px; border: 1px solid var(--line); font-family: inherit; font-size: inherit; width: 100%;" />
                        <div id="inventoryShoppingSuggestions" style="display:none; border: 1px solid var(--line); border-radius: 12px; background: rgba(255,255,255,0.95); position: absolute; top: 100%; left: 0; right: 0; z-index: 11; margin-top: 4px;"></div>
                    </div>
                    <div class="search-container">
                        <input type="text" id="leafletOffersSearch" placeholder="Søg i tilbudsvarer..." style="padding: 8px 12px; border-radius: 12px; border: 1px solid var(--line); font-family: inherit; font-size: inherit; width: 100%;" />
                        <div id="leafletOfferSuggestions" style="display:none; border: 1px solid var(--line); border-radius: 12px; background: rgba(255,255,255,0.95); position: absolute; top: 100%; left: 0; right: 0; z-index: 10; margin-top: 4px;"></div>
                    </div>
                    <aside class="shopping-top-sidebar">
                        <div class="shopping-sticky" style="position: static; max-height: none; top: auto;">
                            <h3 class="subsection-title" style="margin: 0 0 12px; font-size: 16px; border-bottom: 2px solid var(--line); padding-bottom: 8px;">Indkøbsseddel</h3>
                            <div class="inventory-grid" id="shoppingBody"></div>
                        </div>
                    </aside>
                </div>

                <div class="shopping-layout">
                    <div class="shopping-main">
                        <div class="subsection-head" style="margin-top: 0;">
                            <h3 class="subsection-title" style="margin-top: 0; margin-bottom: 12px;">Tilbud på dine mangler</h3>
                        <div class="chip" style="font-size: 12px; margin-bottom: 12px; width: fit-content;" id="offersChip">0 tilbud fundet</div>
                        <div class="inventory-grid" id="offersBody"></div>
                        
                        <div class="subsection-head" style="margin-top: 28px;">
                            <h3 class="subsection-title">Søg i alle tilbudsaviser</h3>
                            <div class="chip" id="leafletOffersChip">0 fundet</div>
                        </div>
                        <div id="leafletOffersBody"></div>
                    </div>
                </div>
            </section>

            <section class="card section" id="activitySection">
                <div class="section-head">
                    <div>
                        <p class="section-kicker">Baggrundslog</p>
                        <h2 class="section-title">Scanhistorik er gemt væk</h2>
                    </div>
                    <div class="chip" id="activityChip">0 hændelser</div>
                </div>
                <p class="collapsed-note">Tidspunkter og barcodes er stadig gemt, men de skal ikke dominere HMI'et. Åbn kun loggen når du vil fejlfinde eller se de seneste bevægelser.</p>
                <details class="scan-disclosure">
                    <summary>Vis scanlog</summary>
                    <div class="flow-grid" id="scansBody"></div>
                </details>
            </section>

            <section class="card section" id="settingsSection">
                <div class="section-head">
                    <div>
                        <p class="section-kicker">Opsætning</p>
                        <h2 class="section-title">Tænk fler-husstande ind fra starten</h2>
                    </div>
                </div>
                <div class="settings-grid">
                    <article class="settings-card">
                        <h3>Husstandsramme</h3>
                        <p>Aktiv visning kører på husstand <strong>#<?= (int) $householdId ?></strong>. Fronten er nu bygget, så et senere husstandsskift kan ske som egentlig navigation i stedet for særkode i hver enkelt sektion.</p>
                        <div class="settings-meta">
                            <div class="mini-stat">
                                <strong><?= (int) $householdId ?></strong>
                                <span>Aktiv husstand</span>
                            </div>
                            <div class="mini-stat">
                                <strong>klar</strong>
                                <span>til husstandsskift</span>
                            </div>
                        </div>
                    </article>
                    <article class="settings-card">
                        <h3>Datakilder og styring</h3>
                        <p>Open Food Facts bruges til scanberigelse. Frida/DTU bør næste gang beskrives som en egentlig integration med mapping, fallback-regler og brug i opskriftsberegninger pr. husstand.</p>
                        <p style="margin-top:8px; font-size:13px;">API status og endpoint-oversigt: <a href="api.php" target="_blank" rel="noopener">åbn api.php</a></p>
                        <p class="admin-only" id="setupInlineWrap" style="display:none; margin-top:6px; font-size:13px;"><a id="setupInlineLink" href="opsaetning.php" style="color:var(--accent);">→ Åbn Opsætning (API-nøgler, brugere, husstande)</a></p>
                    </article>
                    <article class="settings-card">
                        <h3>Admin-konsol</h3>
                        <p>Opret brugere, husstande og medlemskaber direkte her. Brug samme token som i resten af HMI'et.</p>
                        <div class="admin-panel">
                            <div class="admin-row">
                                <label class="admin-label" for="adminAccessToken">Access token</label>
                                <input class="admin-input" id="adminAccessToken" type="text" placeholder="Bearer token" autocomplete="off">
                            </div>
                            <div class="admin-row">
                                <label class="admin-label" for="adminDeviceToken">Device token (kun til 2FA request/test sms)</label>
                                <input class="admin-input" id="adminDeviceToken" type="text" placeholder="Valgfri X-Device-Token" autocomplete="off">
                            </div>
                            <div class="admin-actions">
                                <button class="admin-button" type="button" id="saveAdminTokensButton">Gem tokens</button>
                                <button class="admin-button alt" type="button" id="loadAdminOverviewButton">Indlaes oversigt</button>
                            </div>
                            <div class="admin-status" id="adminStatus">Klar. Gem token og indlaes oversigt.</div>
                            <div class="admin-list" id="adminOverview">Ingen data indlaest endnu.</div>

                            <div class="admin-divider"></div>

                            <div class="admin-grid cols-2">
                                <div>
                                    <label class="admin-label" for="newHouseholdName">Ny husstand</label>
                                    <input class="admin-input" id="newHouseholdName" type="text" placeholder="Fx Familie Hansen">
                                </div>
                                <div>
                                    <label class="admin-label" for="newHouseholdAdminUser">Start admin (user id)</label>
                                    <input class="admin-input" id="newHouseholdAdminUser" type="number" min="1" placeholder="Valgfri">
                                </div>
                            </div>
                            <div class="admin-actions">
                                <button class="admin-button" type="button" id="createHouseholdButton">Opret husstand</button>
                            </div>

                            <div class="admin-grid cols-3">
                                <div>
                                    <label class="admin-label" for="newUserInitials">Initialer</label>
                                    <input class="admin-input" id="newUserInitials" type="text" maxlength="10" placeholder="TT">
                                </div>
                                <div>
                                    <label class="admin-label" for="newUserName">Fulde navn</label>
                                    <input class="admin-input" id="newUserName" type="text" placeholder="Test Bruger">
                                </div>
                                <div>
                                    <label class="admin-label" for="newUserPhone">Telefon (E.164)</label>
                                    <input class="admin-input" id="newUserPhone" type="text" placeholder="+4512345678">
                                </div>
                            </div>
                            <div class="admin-actions">
                                <button class="admin-button" type="button" id="createUserButton">Opret bruger</button>
                            </div>

                            <div class="admin-grid cols-3">
                                <div>
                                    <label class="admin-label" for="assignUserId">Bruger</label>
                                    <select class="admin-select" id="assignUserId"></select>
                                </div>
                                <div>
                                    <label class="admin-label" for="assignHouseholdId">Husstand</label>
                                    <select class="admin-select" id="assignHouseholdId"></select>
                                </div>
                                <div>
                                    <label class="admin-label" for="assignRole">Rolle</label>
                                    <select class="admin-select" id="assignRole">
                                        <option value="member">member</option>
                                        <option value="admin">admin</option>
                                        <option value="owner">owner</option>
                                    </select>
                                </div>
                            </div>
                            <div class="admin-actions">
                                <button class="admin-button" type="button" id="assignMembershipButton">Tilknyt bruger til husstand</button>
                            </div>

                            <div class="admin-divider"></div>

                            <div class="admin-grid cols-2">
                                <div>
                                    <label class="admin-label" for="twofaInitials">2FA initialer</label>
                                    <input class="admin-input" id="twofaInitials" type="text" maxlength="10" placeholder="TT">
                                </div>
                                <div>
                                    <label class="admin-label" for="twofaChallengeId">Challenge ID</label>
                                    <input class="admin-input" id="twofaChallengeId" type="text" placeholder="Udfyldes automatisk ved request">
                                </div>
                            </div>
                            <div class="admin-grid cols-2">
                                <div>
                                    <label class="admin-label" for="twofaCode">SMS kode</label>
                                    <input class="admin-input" id="twofaCode" type="text" maxlength="6" placeholder="123456">
                                </div>
                            </div>
                            <div class="admin-actions">
                                <button class="admin-button alt" type="button" id="request2faButton">Request 2FA kode</button>
                                <button class="admin-button alt" type="button" id="verify2faButton">Verificer 2FA kode</button>
                            </div>
                        </div>
                    </article>
                </div>
            </section>
        </div>
    </div>

    <nav class="bottom-nav" aria-label="Primær navigation">
        <a class="nav-item<?= $currentPage === 'overblik' ? ' active' : '' ?>" href="<?= htmlspecialchars($buildPageUrl('overblik'), ENT_QUOTES, 'UTF-8') ?>" data-nav-target="top">
            <strong>🏠</strong>
            <span>Overblik</span>
        </a>
        <a class="nav-item<?= $currentPage === 'madplan' ? ' active' : '' ?>" href="<?= htmlspecialchars($buildPageUrl('madplan'), ENT_QUOTES, 'UTF-8') ?>" data-nav-target="madplanSection">
            <strong>📅</strong>
            <span>Madplan</span>
        </a>
        <a class="nav-item<?= $currentPage === 'opskrifter' ? ' active' : '' ?>" href="<?= htmlspecialchars($buildPageUrl('opskrifter'), ENT_QUOTES, 'UTF-8') ?>" data-nav-target="recipesSection">
            <strong>📖</strong>
            <span>Opskrifter</span>
        </a>
        <a class="nav-item<?= $currentPage === 'lager' ? ' active' : '' ?>" href="<?= htmlspecialchars($buildPageUrl('lager'), ENT_QUOTES, 'UTF-8') ?>" data-nav-target="inventorySection">
            <strong>📦</strong>
            <span>Lager</span>
        </a>
        <a class="nav-item<?= $currentPage === 'indkoeb' ? ' active' : '' ?>" href="<?= htmlspecialchars($buildPageUrl('indkoeb'), ENT_QUOTES, 'UTF-8') ?>" data-nav-target="shoppingSection">
            <strong>🛒</strong>
            <span>Indkøb</span>
        </a>
    </nav>
</main>

<script>
let householdId = <?= json_encode($householdId, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const currentPage = <?= json_encode($currentPage, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const params = new URLSearchParams(window.location.search);
const queryAccessToken = params.get('access_token');

if (queryAccessToken) {
    window.localStorage.setItem('madAccessToken', queryAccessToken);
}

let accessToken = queryAccessToken || window.localStorage.getItem('madAccessToken') || '';
let deviceToken = params.get('device_token') || window.localStorage.getItem('madDeviceToken') || '';
let isPlatformAdmin = false;
let gateSending = false;
let gateVerifying = false;
let gateLastInitials = '';
let inventoryProductsCache = [];
let lastScannedBarcode = '';
let lastScanLookupProduct = null;
let inventoryCameraStream = null;
let inventoryCameraActive = false;
let inventoryCameraFrameToken = null;
let inventoryCameraLastDetectAt = 0;
let inventoryScanMode = 'in';
let inventoryScanDebugLines = [];
let inventoryLastProcessedScan = {signature: '', at: 0};
let inventoryLastServerScanTimestamp = 0;
let ingredientEditingProductId = 0;
let inventoryLastContextPushAt = 0;

if (params.get('device_token')) {
    window.localStorage.setItem('madDeviceToken', params.get('device_token'));
}

function authHeaders(includeDeviceToken = false) {
    const headers = {};
    if (accessToken) {
        headers.Authorization = 'Bearer ' + accessToken;
    }
    if (includeDeviceToken && deviceToken) {
        headers['X-Device-Token'] = deviceToken;
    }
    return headers;
}

function setGateStatus(message, isError = false) {
    const el = document.getElementById('gateStatus');
    if (!el) {
        return;
    }
    el.textContent = message;
    el.classList.toggle('err', !!isError);
}

function setAdminOnlyVisibility(visible) {
    document.querySelectorAll('.admin-only').forEach((el) => {
        el.style.display = visible ? '' : 'none';
    });
}

function syncActiveHousehold(me) {
    const households = Array.isArray(me?.households) ? me.households : [];
    if (!households.length) {
        return;
    }

    const allowedIds = households.map((household) => Number(household.id)).filter((id) => id > 0);
    if (!allowedIds.length) {
        return;
    }

    const preferredId = Number(me?.active_household_id || 0);
    const targetId = allowedIds.includes(householdId)
        ? householdId
        : (allowedIds.includes(preferredId) ? preferredId : allowedIds[0]);

    householdId = targetId;

    const labelEl = document.getElementById('householdLabel');
    const chosen = households.find((household) => Number(household.id) === targetId);
    if (labelEl) {
        labelEl.textContent = chosen?.name ? String(chosen.name) : ('Husstand ' + targetId);
    }
}

async function resolveSession() {
    if (!accessToken) {
        isPlatformAdmin = false;
        setAdminOnlyVisibility(false);
        return false;
    }

    try {
        const me = await loadJson('api.php?endpoint=auth.me');
        const user = me.user || {};
        syncActiveHousehold(me);
        isPlatformAdmin = !!user.is_platform_admin;
        setAdminOnlyVisibility(isPlatformAdmin);
        return true;
    } catch (_e) {
        accessToken = '';
        window.localStorage.removeItem('madAccessToken');
        isPlatformAdmin = false;
        setAdminOnlyVisibility(false);
        return false;
    }
}

function lockApp() {
    const gate = document.getElementById('authGate');
    const app = document.getElementById('top');
    document.body.classList.remove('auth-pending');
    if (gate) {
        gate.classList.remove('hidden');
    }
    if (app) {
        app.setAttribute('aria-hidden', 'true');
    }
}

function unlockApp() {
    const gate = document.getElementById('authGate');
    const app = document.getElementById('top');
    document.body.classList.remove('auth-pending');
    if (gate) {
        gate.classList.add('hidden');
    }
    if (app) {
        app.setAttribute('aria-hidden', 'false');
    }
}

async function requestGateCode() {
    const initialsEl = document.getElementById('gateInitials');
    const challengeEl = document.getElementById('gateChallenge');
    if (!initialsEl || !challengeEl) {
        return;
    }

    const initials = initialsEl.value.trim().toUpperCase();
    initialsEl.value = initials;
    if (!initials) {
        setGateStatus('Skriv initialer for at modtage SMS-kode.', true);
        return;
    }
    if (gateSending) {
        return;
    }
    if (gateLastInitials === initials && challengeEl.value.trim() !== '') {
        return;
    }

    gateSending = true;
    gateLastInitials = initials;
    setGateStatus('Sender SMS-kode...');
    try {
        const payload = await postJson('api.php?endpoint=auth.request_code', {initials}, {includeDeviceToken: false});
        challengeEl.value = String(payload.challenge_id || '');
        setGateStatus('SMS-kode sendt. Indtast de 6 cifre for at logge ind.');
    } catch (error) {
        gateLastInitials = '';
        setGateStatus('Kunne ikke sende SMS-kode: ' + (error?.message || 'Ukendt fejl'), true);
    }
    gateSending = false;
}

async function verifyGateCode() {
    const initialsEl = document.getElementById('gateInitials');
    const challengeEl = document.getElementById('gateChallenge');
    const codeEl = document.getElementById('gateCode');
    if (!initialsEl || !challengeEl || !codeEl) {
        return;
    }

    codeEl.value = codeEl.value.replace(/\D+/g, '').slice(0, 6);
    const initials = initialsEl.value.trim().toUpperCase();
    const challengeId = challengeEl.value.trim();
    const code = codeEl.value.trim();

    if (!initials || !challengeId || code.length !== 6) {
        return;
    }
    if (gateVerifying) {
        return;
    }

    gateVerifying = true;
    setGateStatus('Verificerer kode...');
    try {
        const payload = await postJson('api.php?endpoint=auth.verify_code', {
            initials,
            challenge_id: challengeId,
            code,
        }, {includeDeviceToken: false});

        if (!payload.access_token) {
            throw new Error('Manglende access token');
        }

        accessToken = payload.access_token;
        window.localStorage.setItem('madAccessToken', accessToken);

        const ok = await resolveSession();
        if (!ok) {
            throw new Error('Session kunne ikke valideres');
        }

        unlockApp();
        refresh();
        setGateStatus('Logget ind.');
    } catch (error) {
        setGateStatus('Login fejlede: ' + (error?.message || 'Ukendt fejl'), true);
        codeEl.value = '';
    }
    gateVerifying = false;
}

function initAuthGate() {
    const initialsEl = document.getElementById('gateInitials');
    const codeEl = document.getElementById('gateCode');
    if (!initialsEl || !codeEl) {
        return;
    }

    initialsEl.addEventListener('blur', requestGateCode);
    initialsEl.addEventListener('change', requestGateCode);
    initialsEl.addEventListener('keydown', (event) => {
        if (event.key === 'Enter') {
            event.preventDefault();
            requestGateCode();
        }
    });

    codeEl.addEventListener('input', verifyGateCode);
}

async function enforceAuthGate() {
    setAdminOnlyVisibility(false);
    const ok = await resolveSession();
    if (ok) {
        unlockApp();
        return;
    }
    lockApp();
}

function resolveApiUrl(url) {
    if (!/^api\.php(\?|$)/.test(String(url))) {
        return url;
    }

    const suffix = String(url).slice('api.php'.length);
    const path = String(window.location.pathname || '');

    if (path.includes('/public/')) {
        return '../api.php' + suffix;
    }
    if (path.startsWith('/mad/')) {
        return 'api.php' + suffix;
    }
    return '/mad/api.php' + suffix;
}

async function loadJson(url) {
    const res = await fetch(resolveApiUrl(url), {
        cache: 'no-store',
        headers: authHeaders(),
    });
    if (!res.ok) {
        throw new Error('HTTP ' + res.status);
    }
    return await res.json();
}

async function postJson(url, body, options = {}) {
    const headers = {
        'Content-Type': 'application/json',
        ...authHeaders(!!options.includeDeviceToken),
    };

    const res = await fetch(resolveApiUrl(url), {
        method: 'POST',
        cache: 'no-store',
        headers,
        body: JSON.stringify(body || {}),
    });

    const payload = await res.json().catch(() => ({}));
    if (!res.ok) {
        const details = payload.details ? `: ${payload.details}` : '';
        throw new Error(`HTTP ${res.status}${details}`);
    }
    return payload;
}

function formatConnectionError(error) {
    const message = String(error?.message || '').trim();
    if (!message) {
        return 'ukendt fejl';
    }
    if (/^HTTP\s+\d+/i.test(message)) {
        return message;
    }
    if (/failed to fetch|networkerror|network error/i.test(message)) {
        return 'netvaerksfejl';
    }
    return message.length > 80 ? message.slice(0, 80) + '…' : message;
}

function esc(v) {
    return String(v ?? '').replace(/[&<>'"]/g, c => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;'
    }[c]));
}

function formatQuantity(value) {
    const quantity = Number(value ?? 0);
    if (Number.isNaN(quantity)) {
        return '0';
    }
    return Number.isInteger(quantity) ? String(quantity) : quantity.toFixed(1);
}

function formatNutritionValue(value, unit = 'g') {
    const numeric = Number(value);
    if (Number.isNaN(numeric)) {
        return '–';
    }
    if (Number.isInteger(numeric)) {
        return `${numeric} ${unit}`;
    }
    return `${numeric.toFixed(1)} ${unit}`;
}

function formatDkk(value) {
    const numeric = Number(value);
    if (Number.isNaN(numeric)) {
        return '–';
    }
    return new Intl.NumberFormat('da-DK', {style: 'currency', currency: 'DKK'}).format(numeric);
}

function formatDateDa(value) {
    if (!value) {
        return '';
    }
    const date = new Date(value + 'T00:00:00');
    if (Number.isNaN(date.getTime())) {
        return String(value);
    }
    return date.toLocaleDateString('da-DK');
}

function productTypeLabel(type) {
    const map = {
        'tørvare': 'Tørvare',
        'ferskvare': 'Ferskvare',
        'mejeri': 'Mejeri',
        'kød': 'Kød',
        'fisk': 'Fisk & skaldyr',
        'frugt_groent': 'Frugt & grønt',
        'frostvare': 'Frostvare',
        'krydderier': 'Krydderier',
        'drikke': 'Drikke',
        'konserves': 'Konserves',
        'brød': 'Brød',
        'andet': 'Diverse',
    };
    return map[String(type || '')] || String(type || 'Diverse');
}

function inferCategoryLabelFromName(name) {
    const text = normalizeSearchText(name);
    if (!text) {
        return 'Diverse';
    }

    const rules = [
        {label: 'Mejeri', tokens: ['maelk', 'yoghurt', 'skyr', 'ost', 'smoer', 'floe', 'flode']},
        {label: 'Koed', tokens: ['kylling', 'okse', 'svin', 'koed', 'boef', 'boef', 'fars', 'paalaeg', 'palaeg']},
        {label: 'Fisk & skaldyr', tokens: ['fisk', 'laks', 'tun', 'reje', 'torsk']},
        {label: 'Frugt & groent', tokens: ['tomat', 'agurk', 'salat', 'banan', 'aeble', 'apple', 'groent', 'frugt', 'kartoffel', 'loeg', 'log']},
        {label: 'Broed', tokens: ['broed', 'brod', 'rugbroed', 'rugbrod', 'bolle', 'toast']},
        {label: 'Drikke', tokens: ['juice', 'sodavand', 'vand', 'kaffe', 'te', 'cola']},
        {label: 'Frostvare', tokens: ['frost', 'pizza', 'is']},
        {label: 'Konserves', tokens: ['daase', 'dase', 'hakkede tomater', 'baked beans', 'tun i vand']},
        {label: 'Toervare', tokens: ['pasta', 'ris', 'mel', 'gryn', 'havre', 'sukker']},
    ];

    for (const rule of rules) {
        if (rule.tokens.some(token => text.includes(token))) {
            return rule.label;
        }
    }

    return 'Diverse';
}

function locationTypeIcon(locType) {
    if (locType === 'fridge') {
        return '❄';
    }
    if (locType === 'freezer') {
        return '🧊';
    }
    if (locType === 'counter') {
        return '🍎';
    }
    return '📦';
}

function nutritionForProduct(product) {
    if (!product.nutrition_json) {
        return null;
    }

    if (typeof product.nutrition_json === 'object') {
        return product.nutrition_json;
    }

    try {
        return JSON.parse(product.nutrition_json);
    } catch {
        return null;
    }
}

function stateForProduct(product) {
    const quantity = Number(product.quantity ?? 0);
    const minimum = Number(product.minimum_quantity ?? 0);

    if (quantity <= 0) {
        return {label: 'Tomt', className: 'state-empty'};
    }
    if (quantity <= minimum) {
        return {label: 'Lav beholdning', className: 'state-low'};
    }
    return {label: 'I balance', className: 'state-ok'};
}

function renderProducts(products) {
    const body = document.getElementById('productsBody');

    if (!products.length) {
        body.innerHTML = '<div class="empty">Ingen produkter endnu. Når scanner eller HMI begynder at oprette varer, dukker lagerkortene op her.</div>';
        return;
    }

    body.innerHTML = products.map(product => {
        const state = stateForProduct(product);
        const nutrition = nutritionForProduct(product);
        const image = product.image_url ? `<img class="inventory-image" src="${esc(product.image_url)}" alt="${esc(product.name || 'Varebillede')}">` : `<div class="inventory-image-fallback">${esc((product.name || 'M').slice(0, 1).toUpperCase())}</div>`;
        const hasStandardPrice = product.standard_price !== null && product.standard_price !== undefined && product.standard_price !== '';
        const hasOfferPrice = product.offer_price !== null && product.offer_price !== undefined && product.offer_price !== '';
        const priceStrip = (hasStandardPrice || hasOfferPrice)
            ? `<div class="nutrition-strip">
                ${hasStandardPrice ? `<div class="nutrition-chip"><strong>${esc(formatDkk(product.standard_price))}</strong><span>Standard ${esc(product.standard_store || '')}</span></div>` : ''}
                ${hasOfferPrice ? `<div class="nutrition-chip"><strong>${esc(formatDkk(product.offer_price))}</strong><span>Tilbud ${esc(product.offer_store || '')}${product.offer_valid_to ? ' til ' + esc(formatDateDa(product.offer_valid_to)) : ''}</span></div>` : ''}
            </div>`
            : '<div class="nutrition-note">Ingen prisdata endnu. Tilføj standardpris eller tilbud i formularen ovenfor.</div>';
        const nutritionStrip = nutrition ? `<div class="nutrition-strip">
                <div class="nutrition-chip">
                    <strong>${esc(formatNutritionValue(nutrition.energy_kcal, 'kcal'))}</strong>
                    <span>Energi</span>
                </div>
                <div class="nutrition-chip">
                    <strong>${esc(formatNutritionValue(nutrition.protein_g))}</strong>
                    <span>Protein</span>
                </div>
                <div class="nutrition-chip">
                    <strong>${esc(formatNutritionValue(nutrition.sugars_g))}</strong>
                    <span>Sukker</span>
                </div>
            </div>
            <div class="nutrition-note">Næringsoplysninger pr. ${esc(nutrition.per || '100g')}.</div>` : '<div class="nutrition-note">Næringsoplysninger kommer ind, når varen er beriget fra maddatabasen.</div>';
        const typeBadge = product.product_type && product.product_type !== 'andet'
            ? `<span class="type-badge">${esc(productTypeLabel(product.product_type))}</span>`
            : '';
        const locationBadge = product.location_name
            ? `<span class="location-badge">${esc(locationTypeIcon(product.location_type))} ${esc(product.location_name)}</span>`
            : '';
        const weightValue = Number(product.weight_grams ?? 0);
        const hasWeight = Number.isFinite(weightValue) && weightValue > 0;
        return `<article class="inventory-card" data-product-id="${esc(String(product.id || ''))}" data-location-id="${esc(String(product.location_id || ''))}" data-barcode="${esc(String(product.barcode || ''))}">
            <div class="inventory-visual">
                ${image}
                <div>
                    <div class="inventory-top">
                        <div>
                            <h3 class="inventory-name">${esc(product.name || 'Ukendt vare')}</h3>
                            <p class="inventory-brand">${esc(product.brand || '')}</p>
                        </div>
                        <span class="state-badge ${state.className}">${esc(state.label)}</span>
                    </div>
                    <div class="inventory-badges">${typeBadge}${locationBadge}</div>
                </div>
            </div>
            <div class="inventory-meta">
                <div class="meta-block">
                    <span class="meta-label">Beholdning</span>
                    <div class="meta-value" data-field="quantity">${esc(formatQuantity(product.quantity ?? 0))}</div>
                </div>
                <div class="meta-block">
                    <span class="meta-label">Minimum</span>
                    <div class="meta-value">${esc(formatQuantity(product.minimum_quantity ?? 0))}</div>
                </div>
                <div class="meta-block">
                    <span class="meta-label">Vægt</span>
                    <div class="meta-value">${hasWeight ? esc(String(Math.round(weightValue)) + ' g') : '-'}</div>
                </div>
            </div>
            ${priceStrip}
            ${nutritionStrip}
            <div class="inventory-card-actions">
                <button class="inventory-add-shopping"
                    data-product-action="edit-ingredient"
                    data-product-id="${esc(String(product.id || ''))}">Rediger</button>
                <button class="inventory-add-shopping"
                    data-product-action="add-to-shopping"
                    data-product-id="${esc(String(product.id || ''))}"
                    data-product-name="${esc(product.name || 'Ukendt vare')}"
                    data-store="${esc(product.offer_store || product.standard_store || '')}">Tilføj til indkøbsliste</button>
            </div>
        </article>`;
    }).join('');
}

function renderScans(scans) {
    const body = document.getElementById('scansBody');

    if (!scans.length) {
        body.innerHTML = '<div class="empty">Ingen scanninger endnu. Når en vare går ind eller ud, vises strømmen her med det samme.</div>';
        return;
    }

    body.innerHTML = scans.map(scan => {
        const isOut = String(scan.movement_type || '').toLowerCase() === 'out';
        return `<article class="scan-card">
            <div class="scan-top">
                <div>
                    <h3 class="scan-name">${esc(scan.product_name || 'Ukendt vare')}</h3>
                    <p class="scan-time">Registreret i baggrunden som del af lagerflowet.</p>
                </div>
                <span class="scan-type ${isOut ? 'scan-out' : 'scan-in'}">${esc((scan.movement_type || 'in').toUpperCase())}</span>
            </div>
            <div class="scan-meta">
                <div class="meta-block">
                    <span class="meta-label">Bevægelse</span>
                    <div class="meta-value">${esc(isOut ? 'Ud' : 'Ind')}</div>
                </div>
                <div class="meta-block">
                    <span class="meta-label">Mængde</span>
                    <div class="meta-value">${esc(formatQuantity(scan.quantity_delta ?? 0))}</div>
                </div>
            </div>
            <div class="scan-quiet">Teknisk reference: ${esc(scan.created_at || '')} · ${esc(scan.barcode || '-')}</div>
        </article>`;
    }).join('');
}

function renderShoppingCandidates(items) {
    const body = document.getElementById('shoppingBody');
    if (!body) {
        return;
    }

    if (!Array.isArray(items) || !items.length) {
        body.innerHTML = '<div class="empty">Ingen aktuelle indkøbskandidater. Når minimum rammes, eller en vare tilføjes manuelt, vises den her.</div>';
        return;
    }

    body.innerHTML = items.map(item => {
        const hasOffer = !!item.has_offer && item.offer_price !== null && item.offer_price !== undefined;
        const offerLine = hasOffer
            ? `<div class="nutrition-strip"><div class="nutrition-chip"><strong>${esc(formatDkk(item.offer_price))}</strong><span>Tilbud hos ${esc(item.offer_store || 'ukendt butik')}${item.offer_valid_to ? ' til ' + esc(formatDateDa(item.offer_valid_to)) : ''}</span></div></div>`
            : '<div class="nutrition-note">Ingen aktivt tilbud fundet endnu.</div>';
        const brandLine = item.brand ? `<p class="inventory-brand">${esc(item.brand)}</p>` : '';
        const typeBadge = item.product_type && item.product_type !== 'andet'
            ? `<span class="type-badge">${esc(productTypeLabel(item.product_type))}</span>`
            : '';

        return `<article class="inventory-card">
            <div class="inventory-top">
                <div>
                    <h3 class="inventory-name">${esc(item.product_name || 'Ukendt vare')}</h3>
                    ${brandLine}
                </div>
                <span class="state-badge ${hasOffer ? 'state-ok' : 'state-low'}">${hasOffer ? 'Tilbud fundet' : 'Mangler tilbud'}</span>
            </div>
            <div class="inventory-badges">${typeBadge}</div>
            <div class="inventory-meta">
                <div class="meta-block">
                    <span class="meta-label">Behov</span>
                    <div class="meta-value">${esc(formatQuantity(item.needed_quantity ?? 1))}</div>
                </div>
                <div class="meta-block">
                    <span class="meta-label">Årsag</span>
                    <div class="meta-value">${esc(item.trigger_reason || 'Ukendt')}</div>
                </div>
            </div>
            ${offerLine}
        </article>`;
    }).join('');
}

function renderShoppingList(items, shoppingList = null, candidateItems = []) {
    const body = document.getElementById('shoppingBody');
    if (!body) {
        return;
    }

    if (!Array.isArray(items) || !items.length) {
        body.innerHTML = '<div class="empty">Ingen aktiv indkøbsseddel endnu. Tilføj varer fra tilbud eller lad lave beholdninger opbygge næste liste.</div>';
        return;
    }

    const sortedItems = items.slice().sort((a, b) => {
        const aChecked = !!a?.is_checked;
        const bChecked = !!b?.is_checked;
        if (aChecked !== bChecked) {
            return aChecked ? 1 : -1;
        }

        const aStore = String(a?.preferred_store || '').trim();
        const bStore = String(b?.preferred_store || '').trim();
        if (aStore !== bStore) {
            if (!aStore) {
                return 1;
            }
            if (!bStore) {
                return -1;
            }
            return aStore.localeCompare(bStore, 'da');
        }

        return String(a?.product_name || '').localeCompare(String(b?.product_name || ''), 'da');
    });

    const listTitle = shoppingList?.title
        ? `<div class="nutrition-note" style="margin-bottom: 8px;">${esc(shoppingList.title)}</div>`
        : '';

    const offerByNameStore = new Map();
    const offerByName = new Map();
    (Array.isArray(candidateItems) ? candidateItems : []).forEach(candidate => {
        const rawName = String(candidate?.product_name || '').trim();
        const offerPrice = candidate?.offer_price;
        if (!rawName || offerPrice === null || offerPrice === undefined) {
            return;
        }

        const normalizedName = normalizeSearchText(rawName);
        if (!normalizedName) {
            return;
        }

        const rawStore = String(candidate?.offer_store || '').trim();
        const normalizedStore = normalizeSearchText(rawStore);
        const priceValue = Number(offerPrice);
        if (Number.isNaN(priceValue)) {
            return;
        }

        if (normalizedStore) {
            const storeKey = `${normalizedName}|${normalizedStore}`;
            const existingStorePrice = offerByNameStore.get(storeKey);
            if (existingStorePrice === undefined || priceValue < existingStorePrice) {
                offerByNameStore.set(storeKey, priceValue);
            }
        }

        const existingPrice = offerByName.get(normalizedName);
        if (existingPrice === undefined || priceValue < existingPrice) {
            offerByName.set(normalizedName, priceValue);
        }
    });

    const rowsHtml = sortedItems.map(item => {
        const baseType = productTypeLabel(item?.product_type || 'andet');
        const typeText = (String(item?.product_type || 'andet') === 'andet')
            ? inferCategoryLabelFromName(item?.product_name || '')
            : baseType;
        const storeText = String(item?.preferred_store || '').trim();
        const metaParts = [typeText];
        if (storeText) {
            metaParts.push(storeText);
        }
        let rowPrice = (item?.offer_price !== null && item?.offer_price !== undefined)
            ? Number(item.offer_price)
            : null;
        if (rowPrice === null || Number.isNaN(rowPrice)) {
            const normalizedName = normalizeSearchText(item?.product_name || '');
            const normalizedStore = normalizeSearchText(storeText);
            if (normalizedName) {
                if (normalizedStore) {
                    const storeKey = `${normalizedName}|${normalizedStore}`;
                    if (offerByNameStore.has(storeKey)) {
                        rowPrice = Number(offerByNameStore.get(storeKey));
                    }
                }
                if ((rowPrice === null || Number.isNaN(rowPrice)) && offerByName.has(normalizedName)) {
                    rowPrice = Number(offerByName.get(normalizedName));
                }
            }
        }

        if (item?.brand) {
            metaParts.push(String(item.brand));
        }
        if (item?.is_checked) {
            metaParts.push('Koebt');
        }

        const itemId = Number(item?.id || 0);
        const isChecked = !!item?.is_checked;
        const priceBadge = (rowPrice !== null && !Number.isNaN(rowPrice))
            ? `<span class="shopping-price" title="Tilbudspris">${esc(formatDkk(rowPrice))}</span>`
            : '<span class="shopping-price missing" title="Pris ikke fundet">pris ?</span>';

        return `<li class="shopping-row${isChecked ? ' checked' : ''}">
            <button class="shopping-check"
                data-shopping-action="toggle"
                data-item-id="${itemId}"
                data-next-checked="${isChecked ? '0' : '1'}"
                aria-label="${isChecked ? 'Fjern markering' : 'Marker som koebt'}"
                title="${isChecked ? 'Fortryd koebt' : 'Marker som koebt'}">${isChecked ? 'OK' : '+'}</button>
            <div class="shopping-row-main"
                data-shopping-action="toggle"
                data-item-id="${itemId}"
                data-next-checked="${isChecked ? '0' : '1'}"
                role="button"
                aria-label="${isChecked ? 'Fjern markering' : 'Marker som koebt'}"
                tabindex="0">
                <p class="shopping-name">${esc(item?.product_name || 'Ukendt vare')}</p>
                <p class="shopping-meta">${esc(metaParts.join(' · '))}</p>
            </div>
            <div class="shopping-pills">
                <span class="shopping-qty">${esc(formatQuantity(item?.quantity ?? 1))}</span>
                ${priceBadge}
            </div>
            <button class="shopping-delete"
                data-shopping-action="remove"
                data-item-id="${itemId}"
                aria-label="Slet vare"
                title="Slet vare">Slet</button>
        </li>`;
    }).join('');

    body.innerHTML = `${listTitle}<div class="shopping-compact"><ul class="shopping-list">${rowsHtml}</ul></div>`;
}

async function setShoppingItemChecked(itemId, isChecked) {
    const targetHouseholdId = Number(householdId || 0) > 0 ? String(householdId) : '1';
    await postJson(`api.php?endpoint=shopping.list.set_item_checked&household_id=${encodeURIComponent(targetHouseholdId)}`, {
        item_id: Number(itemId || 0),
        is_checked: !!isChecked,
    });
}

async function removeShoppingItem(itemId) {
    const targetHouseholdId = Number(householdId || 0) > 0 ? String(householdId) : '1';
    await postJson(`api.php?endpoint=shopping.list.remove_item&household_id=${encodeURIComponent(targetHouseholdId)}`, {
        item_id: Number(itemId || 0),
    });
}

function initShoppingListActions() {
    const body = document.getElementById('shoppingBody');
    if (!body || body.dataset.actionsBound === '1') {
        return;
    }

    body.dataset.actionsBound = '1';
    body.addEventListener('click', async (event) => {
        const target = event.target;
        if (!(target instanceof HTMLElement)) {
            return;
        }

        const actionTarget = target.closest('[data-shopping-action]');
        if (!(actionTarget instanceof HTMLElement)) {
            return;
        }

        const itemId = Number(actionTarget.dataset.itemId || 0);
        if (itemId <= 0) {
            return;
        }

        const action = actionTarget.dataset.shoppingAction || '';
        const disableElement = (actionTarget instanceof HTMLButtonElement) ? actionTarget : null;
        if (disableElement) {
            disableElement.disabled = true;
        } else {
            actionTarget.classList.add('is-busy');
        }

        try {
            if (action === 'toggle') {
                const nextChecked = actionTarget.dataset.nextChecked === '1';
                await setShoppingItemChecked(itemId, nextChecked);
                await refresh();
                return;
            }

            if (action === 'remove') {
                await removeShoppingItem(itemId);
                await refresh();
            }
        } catch (e) {
            alert('Kunne ikke opdatere indkoebssedlen: ' + String(e?.message || e));
            if (disableElement) {
                disableElement.disabled = false;
            } else {
                actionTarget.classList.remove('is-busy');
            }
        }
    });
}

function initShoppingListKeyboardActions() {
    const body = document.getElementById('shoppingBody');
    if (!body || body.dataset.keyboardBound === '1') {
        return;
    }

    body.dataset.keyboardBound = '1';
    body.addEventListener('keydown', async (event) => {
        if (!(event.target instanceof HTMLElement)) {
            return;
        }

        const isToggleTarget = event.target.hasAttribute('data-shopping-action')
            && event.target.getAttribute('data-shopping-action') === 'toggle';
        if (!isToggleTarget) {
            return;
        }

        if (event.key !== 'Enter' && event.key !== ' ') {
            return;
        }

        event.preventDefault();
        event.target.click();
    });
}

function renderOfferHighlights(items) {
    const body = document.getElementById('offersBody');
    if (!body) {
        return;
    }

    const offers = (Array.isArray(items) ? items : [])
        .filter(item => !!item.has_offer && item.offer_price !== null && item.offer_price !== undefined)
        .sort((a, b) => Number(a.offer_price) - Number(b.offer_price));

    if (!offers.length) {
        body.innerHTML = '<div class="empty">Ingen aktive tilbud fundet på dine aktuelle mangler endnu.</div>';
        return;
    }

    body.innerHTML = offers.map(item => {
        const brandLine = item.brand ? `<p class="inventory-brand">${esc(item.brand)}</p>` : '';
        const validLine = item.offer_valid_to ? `Gyldig til ${esc(formatDateDa(item.offer_valid_to))}` : 'Gyldighed ikke oplyst';
        return `<article class="inventory-card">
            <div class="inventory-top">
                <div>
                    <h3 class="inventory-name">${esc(item.product_name || 'Ukendt vare')}</h3>
                    ${brandLine}
                </div>
                <span class="state-badge state-ok">Tilbud</span>
            </div>
            <div class="nutrition-strip">
                <div class="nutrition-chip">
                    <strong>${esc(formatDkk(item.offer_price))}</strong>
                    <span>Pris</span>
                </div>
                <div class="nutrition-chip">
                    <strong>${esc(item.offer_store || 'Ukendt')}</strong>
                    <span>Butik</span>
                </div>
                <div class="nutrition-chip">
                    <strong>${esc(formatQuantity(item.needed_quantity ?? 1))}</strong>
                    <span>Behov</span>
                </div>
            </div>
            <div class="nutrition-note">${validLine}</div>
        </article>`;
    }).join('');
}

function normalizeSearchText(value) {
    return String(value || '')
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/[^a-z0-9\s]/g, ' ')
        .replace(/\s+/g, ' ')
        .trim();
}

function levenshteinDistance(a, b) {
    const s = String(a || '');
    const t = String(b || '');
    const m = s.length;
    const n = t.length;

    if (m === 0) {
        return n;
    }
    if (n === 0) {
        return m;
    }

    const dp = Array.from({length: m + 1}, () => new Array(n + 1).fill(0));
    for (let i = 0; i <= m; i++) {
        dp[i][0] = i;
    }
    for (let j = 0; j <= n; j++) {
        dp[0][j] = j;
    }

    for (let i = 1; i <= m; i++) {
        for (let j = 1; j <= n; j++) {
            const cost = s[i - 1] === t[j - 1] ? 0 : 1;
            dp[i][j] = Math.min(
                dp[i - 1][j] + 1,
                dp[i][j - 1] + 1,
                dp[i - 1][j - 1] + cost
            );
        }
    }

    return dp[m][n];
}

function matchesSearchQuery(text, query) {
    const q = normalizeSearchText(query);
    if (!q) {
        return true;
    }

    const haystack = normalizeSearchText(text);
    if (!haystack) {
        return false;
    }

    if (haystack.includes(q)) {
        return true;
    }

    const words = haystack.split(' ').filter(Boolean);
    if (words.some(word => word.startsWith(q))) {
        return true;
    }

    if (q.length >= 4) {
        return words.some(word => {
            if (Math.abs(word.length - q.length) > 1) {
                return false;
            }
            return levenshteinDistance(word, q) <= 1;
        });
    }

    return false;
}

function renderLeafletOfferFeed(items, suggestionSourceItems = null) {
    const body = document.getElementById('leafletOffersBody');
    if (!body) {
        return;
    }

    const offers = Array.isArray(items) ? items : [];
    const suggestionOffers = Array.isArray(suggestionSourceItems) ? suggestionSourceItems : offers;
    attachLeafletOfferHandlers(offers, suggestionOffers);

    if (!offers.length) {
        body.innerHTML = '<div class="empty">Ingen varer fundet i tilbudsaviser endnu.</div>';
        return;
    }

    // Group offers by store
    const storeGroups = {};
    offers.forEach(item => {
        const storeName = item.store_name || 'Ukendt butik';
        if (!storeGroups[storeName]) {
            storeGroups[storeName] = [];
        }
        storeGroups[storeName].push(item);
    });

    // Sort stores alphabetically
    const sortedStores = Object.keys(storeGroups).sort();

    const groupsHtml = sortedStores.map(storeName => {
        const storeOffers = storeGroups[storeName];
        const offersHtml = storeOffers.map(item => {
            const validLine = item.valid_to ? `Gyldig til ${esc(formatDateDa(item.valid_to))}` : 'Gyldighed ikke oplyst';
            const matchedBadge = item.is_catalog_matched
                ? '<span class="state-badge state-ok">Matchet</span>'
                : '<span class="state-badge state-low">Ikke matchet</span>';

            return `<article class="leaflet-offer-card inventory-card" data-offer-id="${esc(item.id || '')}" style="position: relative;">
                <div style="position: absolute; top: 12px; right: 12px;">
                    <input type="checkbox" class="leaflet-offer-checkbox" data-offer-id="${esc(item.id || '')}" data-title="${esc(item.product_name || 'Ukendt vare')}" data-store="${esc(item.store_name || '')}" />
                </div>
                <div class="inventory-top">
                    <div>
                        <h3 class="inventory-name">${esc(item.product_name || 'Ukendt vare')}</h3>
                        <p class="inventory-brand">${esc(item.store_name || 'Ukendt butik')}</p>
                    </div>
                    ${matchedBadge}
                </div>
                <div class="nutrition-strip">
                    <div class="nutrition-chip">
                        <strong>${esc(formatDkk(item.price))}</strong>
                        <span>Pris</span>
                    </div>
                    <div class="nutrition-chip">
                        <strong>${esc(item.store_name || 'Ukendt')}</strong>
                        <span>Butik</span>
                    </div>
                    <div class="nutrition-chip">
                        <strong>${esc(item.is_catalog_matched ? 'Ja' : 'Nej')}</strong>
                        <span>I katalog</span>
                    </div>
                </div>
                <div class="nutrition-note">${validLine}</div>
            </article>`;
        }).join('');

        return `<div class="store-group" data-store="${esc(storeName)}">
            <h3 style="padding: 12px 0 8px 0; margin: 0; font-size: 16px; font-weight: 700; color: var(--accent); border-bottom: 2px solid var(--line);">${esc(storeName)}</h3>
            <div class="inventory-grid" style="margin-top: 12px;">
                ${offersHtml}
            </div>
        </div>`;
    }).join('');

    body.innerHTML = groupsHtml;
}

function attachLeafletOfferHandlers(offers, suggestionOffers = null) {
    const searchInput = document.getElementById('leafletOffersSearch');
    const addBtn = document.getElementById('leafletOffersAddBtn');
    const body = document.getElementById('leafletOffersBody');
    const suggestions = document.getElementById('leafletOfferSuggestions');
    const offerItems = Array.isArray(offers) ? offers : [];
    const offerItemsForSuggestions = Array.isArray(suggestionOffers) ? suggestionOffers : offerItems;

    if (!body) {
        return;
    }

    function renderSuggestions(query) {
        if (!suggestions) {
            return;
        }

        const normalized = String(query || '').trim().toLowerCase();
        if (!normalized) {
            suggestions.style.display = 'none';
            suggestions.innerHTML = '';
            return;
        }

        const matches = offerItemsForSuggestions
            .filter(item => {
                const title = String(item?.product_name || '');
                const store = String(item?.store_name || '');
                return matchesSearchQuery(title, normalized) || matchesSearchQuery(store, normalized);
            })
            .slice(0, 8);

        if (!matches.length) {
            suggestions.style.display = '';
            suggestions.innerHTML = '<div style="padding:10px 12px; color: var(--muted);">Ingen forslag fundet</div>';
            return;
        }

        suggestions.style.display = '';
        suggestions.innerHTML = matches.map(item => `
            <div style="display:flex; align-items:center; justify-content:space-between; gap:10px; padding:8px 10px; border-top:1px solid var(--line);">
                <div>
                    <div style="font-weight:700; font-size:14px;">${esc(item.product_name || 'Ukendt vare')}</div>
                    <div style="font-size:12px; color: var(--muted);">${esc(item.store_name || 'Ukendt butik')} · ${esc(formatDkk(item.price))}</div>
                </div>
                <button class="leaflet-suggestion-add" data-title="${esc(item.product_name || 'Ukendt vare')}" data-store="${esc(item.store_name || '')}" data-offer-id="${esc(item.id || '')}" style="padding:6px 10px; border-radius:10px; background: var(--accent); color:white; border:none; cursor:pointer; font-weight:700; white-space:nowrap;">Tilføj</button>
            </div>
        `).join('');
    }

    if (searchInput) {
        searchInput.oninput = (e) => {
            const query = e.target.value.toLowerCase();
            renderSuggestions(query);

            const cards = body.querySelectorAll('.leaflet-offer-card');
            cards.forEach(card => {
                const title = card.querySelector('.inventory-name')?.textContent || '';
                const store = card.querySelector('.inventory-brand')?.textContent || '';
                const matches = matchesSearchQuery(title, query);
                const storeMatches = matchesSearchQuery(store, query);
                card.style.display = (matches || storeMatches) ? '' : 'none';
            });

            const groups = body.querySelectorAll('.store-group');
            groups.forEach(group => {
                const visibleCards = group.querySelectorAll('.leaflet-offer-card:not([style*="display: none"])').length;
                group.style.display = visibleCards > 0 ? '' : 'none';
            });
        };

        searchInput.onblur = () => {
            setTimeout(() => {
                if (suggestions) {
                    suggestions.style.display = 'none';
                }
            }, 180);
        };

        searchInput.onfocus = () => {
            renderSuggestions(searchInput.value || '');
        };
    }

    if (suggestions) {
        suggestions.onclick = async (event) => {
            const target = event.target;
            if (!(target instanceof HTMLElement) || !target.classList.contains('leaflet-suggestion-add')) {
                return;
            }

            const title = target.dataset.title || '';
            const store = target.dataset.store || '';
            const offerId = Number(target.dataset.offerId || 0);
            if (!title) {
                return;
            }

            try {
                await addLeafletOffersToShopping([{title, store, offerId}]);
                alert('1 vare tilføjet til indkøbsseddel');
                await refresh();
            } catch (e) {
                alert('Fejl ved tilføjelse: ' + e.message);
            }
        };
    }

    if (addBtn) {
        addBtn.onclick = async () => {
            const checkboxes = body.querySelectorAll('.leaflet-offer-checkbox:checked');
            if (!checkboxes.length) {
                alert('Vælg mindst en vare at tilføje');
                return;
            }

            const items = Array.from(checkboxes).map(cb => ({
                title: cb.dataset.title,
                offerId: cb.dataset.offerId,
                store: cb.dataset.store
            }));

            try {
                const result = await addLeafletOffersToShopping(items);
                if (result) {
                    alert(`${items.length} vare(r) tilføjet til indkøbsseddel`);
                    // Clear checkboxes
                    checkboxes.forEach(cb => cb.checked = false);
                    await refresh();
                }
            } catch (e) {
                alert('Fejl ved tilføjelse: ' + e.message);
            }
        };
    }
}

async function addLeafletOffersToShopping(items) {
    try {
        const targetHouseholdId = Number(householdId || 0) > 0 ? String(householdId) : '1';
        await postJson(`api.php?endpoint=shopping.list.add_items&household_id=${encodeURIComponent(targetHouseholdId)}`, {
            items,
        });
        return true;
    } catch (e) {
        console.error('Error adding items:', e);
        throw e;
    }
}

async function addInventoryProductToShopping(product) {
    const name = String(product?.name || product?.product_name || '').trim();
    if (!name) {
        throw new Error('Produktnavn mangler');
    }

    const productId = Number(product?.id || product?.product_id || 0);
    const preferredStore = String(product?.offer_store || product?.standard_store || '').trim();
    const targetHouseholdId = Number(householdId || 0) > 0 ? String(householdId) : '1';

    await postJson(`api.php?endpoint=shopping.list.add_items&household_id=${encodeURIComponent(targetHouseholdId)}`, {
        items: [{
            title: name,
            productId: productId > 0 ? productId : undefined,
            store: preferredStore,
        }],
    });
}

function initInventoryCardActions() {
    const body = document.getElementById('productsBody');
    if (!body || body.dataset.shoppingAddBound === '1') {
        return;
    }

    body.dataset.shoppingAddBound = '1';
    body.addEventListener('click', async (event) => {
        const target = event.target;
        if (!(target instanceof HTMLElement)) {
            return;
        }

        const button = target.closest('[data-product-action]');
        if (!(button instanceof HTMLButtonElement)) {
            return;
        }

        const action = String(button.dataset.productAction || '');

        if (action === 'edit-ingredient') {
            const productId = Number(button.dataset.productId || 0);
            if (productId <= 0) {
                return;
            }

            const product = (Array.isArray(inventoryProductsCache) ? inventoryProductsCache : []).find((item) => Number(item?.id || 0) === productId);
            if (!product) {
                alert('Kunne ikke finde produktdata til redigering. Opdater siden og prøv igen.');
                return;
            }

            openIngredientCreatePanel();
            startIngredientEditFromProduct(product);
            return;
        }

        if (action !== 'add-to-shopping') {
            return;
        }

        const productId = Number(button.dataset.productId || 0);
        const productName = String(button.dataset.productName || '').trim();
        if (!productName) {
            return;
        }

        const product = {
            id: productId,
            name: productName,
            offer_store: String(button.dataset.store || '').trim(),
        };

        button.disabled = true;
        try {
            await addInventoryProductToShopping(product);
            await refresh();
        } catch (e) {
            alert('Kunne ikke tilføje til indkøbsliste: ' + String(e?.message || e));
            button.disabled = false;
        }
    });
}

function initInventoryShoppingSearch() {
    const input = document.getElementById('inventoryShoppingSearch');
    const suggestions = document.getElementById('inventoryShoppingSuggestions');
    if (!input || !suggestions || input.dataset.inventorySearchBound === '1') {
        return;
    }

    input.dataset.inventorySearchBound = '1';

    const renderInventorySuggestions = (rawQuery) => {
        const query = normalizeSearchText(rawQuery);
        if (!query) {
            suggestions.style.display = 'none';
            suggestions.innerHTML = '';
            return;
        }

        const ranked = inventoryProductsCache
            .map((product) => {
                const name = String(product?.name || '').trim();
                if (!name) {
                    return null;
                }
                const normName = normalizeSearchText(name);
                const normBrand = normalizeSearchText(String(product?.brand || ''));
                let score = 0;
                if (normName.startsWith(query)) {
                    score += 30;
                }
                if (normName.includes(query)) {
                    score += 20;
                }
                if (normBrand && normBrand.includes(query)) {
                    score += 10;
                }
                if (score <= 0) {
                    return null;
                }
                return {product, score};
            })
            .filter(Boolean)
            .sort((a, b) => b.score - a.score || String(a.product.name || '').localeCompare(String(b.product.name || ''), 'da'))
            .slice(0, 8);

        if (!ranked.length) {
            suggestions.style.display = 'none';
            suggestions.innerHTML = '';
            return;
        }

        suggestions.innerHTML = ranked.map(({product}) => {
            const productId = Number(product?.id || 0);
            const name = String(product?.name || 'Ukendt vare');
            const brand = String(product?.brand || '').trim();
            const store = String(product?.offer_store || product?.standard_store || '').trim();
            const meta = [brand, store].filter(Boolean).join(' · ');

            return `<div class="inventory-suggestion-row" data-inventory-action="add" data-product-id="${esc(String(productId))}" data-product-name="${esc(name)}" data-store="${esc(store)}">
                <div>
                    <div class="inventory-suggestion-name">${esc(name)}</div>
                    <div class="inventory-suggestion-meta">${esc(meta || 'Fra lager')}</div>
                </div>
                <button type="button" class="inventory-suggestion-add" data-inventory-action="add" data-product-id="${esc(String(productId))}" data-product-name="${esc(name)}" data-store="${esc(store)}">Tilføj</button>
            </div>`;
        }).join('');
        suggestions.style.display = '';
    };

    input.addEventListener('input', () => {
        renderInventorySuggestions(input.value || '');
    });

    input.addEventListener('focus', () => {
        renderInventorySuggestions(input.value || '');
    });

    input.addEventListener('blur', () => {
        setTimeout(() => {
            suggestions.style.display = 'none';
        }, 180);
    });

    suggestions.addEventListener('click', async (event) => {
        const target = event.target;
        if (!(target instanceof HTMLElement)) {
            return;
        }

        const row = target.closest('[data-inventory-action="add"]');
        if (!(row instanceof HTMLElement)) {
            return;
        }

        const productId = Number(row.dataset.productId || 0);
        const name = String(row.dataset.productName || '').trim();
        const store = String(row.dataset.store || '').trim();
        if (!name) {
            return;
        }

        try {
            await addInventoryProductToShopping({id: productId, name, offer_store: store});
            input.value = '';
            suggestions.style.display = 'none';
            await refresh();
        } catch (e) {
            alert('Kunne ikke tilføje fra lager: ' + String(e?.message || e));
        }
    });
}

function setActiveNav(targetId) {
    document.querySelectorAll('[data-nav-target]').forEach(link => {
        link.classList.toggle('active', link.dataset.navTarget === targetId);
    });
}

function setVisible(id, visible) {
    const el = document.getElementById(id);
    if (!el) {
        return;
    }
    el.style.display = visible ? '' : 'none';
}

function applyTraditionalPage(page) {
    const layout = document.getElementById('mainLayout');
    const showOverview = page === 'overblik';

    setVisible('overviewContext', showOverview);
    setVisible('overviewHero', showOverview);
    setVisible('overviewStats', showOverview);

    setVisible('madplanSection', page === 'madplan');
    setVisible('recipesSection', page === 'opskrifter');
    setVisible('inventorySection', page === 'lager');
    setVisible('activitySection', page === 'lager');
    setVisible('shoppingSection', page === 'indkoeb');
    setVisible('settingsSection', page === 'opsaetning');

    if (layout) {
        layout.classList.toggle('single-page', page !== 'overblik');
        layout.style.display = page === 'overblik' ? 'none' : '';
    }

    if (page === 'lager') {
        setTimeout(() => {
            const input = document.getElementById('inventoryScanInput');
            if (input instanceof HTMLInputElement) {
                input.focus();
                input.select();
            }
        }, 60);
    } else {
        const stopBtn = document.getElementById('inventoryCameraStop');
        if (stopBtn instanceof HTMLButtonElement && stopBtn.style.display !== 'none') {
            stopBtn.click();
        }
    }
}

function updateNavFromHash() {
    const hash = window.location.hash.replace('#', '');
    const pageTargetMap = {
        overblik: 'top',
        madplan: 'madplanSection',
        opskrifter: 'recipesSection',
        lager: 'inventorySection',
        indkoeb: 'shoppingSection',
        opsaetning: 'settingsSection',
    };
    const targetPageMap = {
        top: 'overblik',
        madplanSection: 'madplan',
        recipesSection: 'opskrifter',
        inventorySection: 'lager',
        shoppingSection: 'indkoeb',
        settingsSection: 'opsaetning',
    };
    const fallback = pageTargetMap[String(currentPage || '')] || 'top';
    const targetId = hash || fallback;

    const forcedPage = targetPageMap[targetId];
    if (forcedPage) {
        applyTraditionalPage(forcedPage);
    }

    if (document.getElementById(targetId)) {
        setActiveNav(targetId);
    } else {
        setActiveNav(fallback);
    }
}

function renderAiIdeas(payload) {
    const aiResult = document.getElementById('aiResult');
    const aiStatus = document.getElementById('aiStatus');
    const ideas = Array.isArray(payload.meal_ideas) ? payload.meal_ideas : [];

    aiStatus.textContent = payload.summary || 'Forslag klar.';

    if (!ideas.length) {
        aiResult.innerHTML = '<div class="empty">Ingen konkrete forslag i svaret endnu.</div>';
        return;
    }

    aiResult.innerHTML = ideas.slice(0, 3).map(idea => {
        const uses = Array.isArray(idea.uses_products) ? idea.uses_products : [];
        const missing = Array.isArray(idea.missing_items) ? idea.missing_items : [];
        return `<article class="ai-idea">
            <h4>${esc(idea.name || 'Forslag')}</h4>
            <p>${esc(idea.why || '')}</p>
            <ul class="ai-list">
                <li><strong>Bruger fra lager:</strong> ${esc(uses.join(', ') || 'Ikke angivet')}</li>
                <li><strong>Mangler:</strong> ${esc(missing.join(', ') || 'Intet')}</li>
            </ul>
        </article>`;
    }).join('');
}

function setAdminStatus(message) {
    const el = document.getElementById('adminStatus');
    if (el) {
        el.textContent = message;
    }
}

function setIngredientStatus(message, isError = false) {
    const el = document.getElementById('ingredientCreateStatus');
    if (!el) {
        return;
    }
    el.textContent = message;
    el.classList.toggle('err', !!isError);
}

function setInventoryScanStatus(message, isError = false) {
    const el = document.getElementById('inventoryScanStatus');
    if (!el) {
        return;
    }
    el.textContent = message;
    el.classList.toggle('err', !!isError);
}

function appendInventoryScanDebug(message) {
    const el = document.getElementById('inventoryScanDebug');
    if (!el) {
        return;
    }
    const stamp = new Date().toLocaleTimeString('da-DK', {hour: '2-digit', minute: '2-digit', second: '2-digit'});
    inventoryScanDebugLines.push(`[${stamp}] ${message}`);
    if (inventoryScanDebugLines.length > 8) {
        inventoryScanDebugLines = inventoryScanDebugLines.slice(-8);
    }
    el.textContent = inventoryScanDebugLines.join('\n');
    el.scrollTop = el.scrollHeight;
}

function setInventoryScanMode(mode, source = '') {
    inventoryScanMode = mode === 'out' ? 'out' : 'in';
    const modeInBtn = document.getElementById('scanModeIn');
    const modeOutBtn = document.getElementById('scanModeOut');
    if (modeInBtn && modeOutBtn) {
        modeInBtn.classList.toggle('active', inventoryScanMode === 'in');
        modeOutBtn.classList.toggle('active', inventoryScanMode === 'out');
    }

    const label = inventoryScanMode === 'out' ? 'ud af lager' : 'ind i lager';
    setInventoryScanStatus(`Venter på scanning... Retning: ${label}`);
    if (source) {
        appendInventoryScanDebug(`Retning sat til ${inventoryScanMode} (${source})`);
    }

    // Notify backend if mode was changed by user click (for ESP32 feedback)
    if (source === 'klik') {
        void syncDeviceScanContext();
    }
}

function getActiveInventoryLocationId() {
    return Number(document.getElementById('ingredientLocationId')?.value || 0) || 1;
}

async function syncDeviceScanContext(force = false) {
    const now = Date.now();
    if (!force && (now - Number(inventoryLastContextPushAt || 0)) < 4000) {
        return;
    }

    inventoryLastContextPushAt = now;
    const contextPayload = {
        mode: inventoryScanMode,
        household_id: Number(householdId || 1),
        location_id: getActiveInventoryLocationId(),
    };

    try {
        await postJson('api.php?endpoint=device.set_mode', contextPayload, {includeDeviceToken: true});
    } catch (err) {
        appendInventoryScanDebug(`Advarsel: kunne ikke synke scan-kontekst: ${String(err?.message || err)}`);
    }
}

async function pollInventoryModeFromServer() {
    try {
        const response = await loadJson('api.php?endpoint=device.get_mode');
        const serverMode = String(response?.mode || 'in').trim();
        
        if (serverMode !== inventoryScanMode && (serverMode === 'in' || serverMode === 'out')) {
            console.log('[Poll] Mode changed from', inventoryScanMode, 'to', serverMode);
            setInventoryScanMode(serverMode, 'server poll');
        }
    } catch (e) {
        console.error('[Poll] Failed to get mode:', e);
    }
}

async function pollInventoryLastScanFromServer() {
    try {
        const response = await loadJson('api.php?endpoint=device.get_last_scan');
        const scan = response?.scan;
        if (!scan) {
            return;
        }

        const timestamp = Number(scan.timestamp || 0);
        if (!Number.isFinite(timestamp) || timestamp <= inventoryLastServerScanTimestamp) {
            return;
        }

        inventoryLastServerScanTimestamp = timestamp;

        const movement = String(scan.movement_type || 'in') === 'out' ? 'out' : 'in';
        const code = String(scan.barcode || '').trim();
        const scanProductId = Number(scan.product_id || 0);
        const scanQuantityAfter = scan.quantity_after === null || scan.quantity_after === undefined
            ? null
            : Number(scan.quantity_after);
        const scanHouseholdId = Number(scan.household_id || 0);
        const scanLocationId = Number(scan.location_id || 0);
        const uiHouseholdId = Number(householdId || 0);
        if (!code) {
            return;
        }

        const scanInput = document.getElementById('inventoryScanInput');
        if (scanInput && 'value' in scanInput) {
            scanInput.value = code;
        }

        if (movement !== inventoryScanMode) {
            setInventoryScanMode(movement, 'server scan');
        }

        if (scanProductId > 0 && Number.isFinite(scanQuantityAfter)) {
            let changed = false;
            inventoryProductsCache = (Array.isArray(inventoryProductsCache) ? inventoryProductsCache : []).map((product) => {
                const productId = Number(product?.id || 0);
                const productLocationId = Number(product?.location_id || 0);
                if (productId === scanProductId && (!scanLocationId || productLocationId === scanLocationId)) {
                    changed = true;
                    return {
                        ...product,
                        quantity: scanQuantityAfter,
                    };
                }
                return product;
            });

            let productCards = document.querySelectorAll(`#productsBody .inventory-card[data-product-id="${scanProductId}"]`);
            if (!productCards.length && code) {
                const escapedCode = String(code).replace(/"/g, '\\"');
                productCards = document.querySelectorAll(`#productsBody .inventory-card[data-barcode="${escapedCode}"]`);
            }
            productCards.forEach((card) => {
                if (!(card instanceof HTMLElement)) {
                    return;
                }
                const cardLocationId = Number(card.dataset.locationId || 0);
                if (scanLocationId && cardLocationId && cardLocationId !== scanLocationId) {
                    return;
                }
                const quantityEl = card.querySelector('[data-field="quantity"]');
                if (quantityEl instanceof HTMLElement) {
                    quantityEl.textContent = formatQuantity(scanQuantityAfter);
                    changed = true;
                }
            });

            if (changed) {
                renderProducts(inventoryProductsCache);
                initInventoryCardActions();
            }
        }

        const movementLabel = movement === 'out' ? 'ud' : 'ind';
        setInventoryScanStatus(`Scanner (ESP32): ${code} (${movementLabel})`);
        appendInventoryScanDebug(`ESP32 scan modtaget: ${code} (${movementLabel}) product=${scanProductId || '-'} hh=${scanHouseholdId || '-'} loc=${scanLocationId || '-'} ui_hh=${uiHouseholdId || '-'} qty=${scanQuantityAfter ?? '-'}`);

        void refresh();
    } catch (e) {
        // Ignore temporary polling/network errors.
    }
}

function parseScannerModeCommand(rawValue) {
    const token = String(rawValue || '').trim().toLowerCase();
    if (!token) {
        return null;
    }

    if (['in', 'ind', 'i', '+', 'up', 'op'].includes(token)) {
        return 'in';
    }
    if (['out', 'ud', 'o', '-', 'down', 'ned'].includes(token)) {
        return 'out';
    }

    return null;
}

function findInventoryProductByBarcode(barcode) {
    const target = String(barcode || '').trim();
    if (!target) {
        return null;
    }
    return inventoryProductsCache.find((product) => String(product?.barcode || '').trim() === target) || null;
}

function parseScannerPayload(rawValue) {
    const raw = String(rawValue || '').trim();
    if (!raw) {
        return {barcode: '', modeHint: null, normalizedRaw: ''};
    }

    const compact = raw.replace(/\s+/g, '');

    const prefixedMode = compact.match(/^(out|ud|o|in|ind|i)[:;,_\-]?([0-9]{8,14})$/i);
    if (prefixedMode) {
        const modeToken = String(prefixedMode[1] || '').toLowerCase();
        const modeHint = (modeToken === 'out' || modeToken === 'ud' || modeToken === 'o') ? 'out' : 'in';
        return {
            barcode: String(prefixedMode[2] || ''),
            modeHint,
            normalizedRaw: compact,
        };
    }

    const signMode = compact.match(/^([+-])([0-9]{8,14})$/);
    if (signMode) {
        return {
            barcode: String(signMode[2] || ''),
            modeHint: signMode[1] === '-' ? 'out' : 'in',
            normalizedRaw: compact,
        };
    }

    const suffixMode = compact.match(/^([0-9]{8,14})[:;,_\-]?(out|ud|o|in|ind|i)$/i);
    if (suffixMode) {
        const modeToken = String(suffixMode[2] || '').toLowerCase();
        const modeHint = (modeToken === 'out' || modeToken === 'ud' || modeToken === 'o') ? 'out' : 'in';
        return {
            barcode: String(suffixMode[1] || ''),
            modeHint,
            normalizedRaw: compact,
        };
    }

    const digits = compact.match(/([0-9]{8,14})/);
    return {
        barcode: digits ? String(digits[1] || '') : compact,
        modeHint: null,
        normalizedRaw: compact,
    };
}

function isLikelyScannerToken(rawValue) {
    const value = String(rawValue || '').trim();
    if (!value) {
        return false;
    }

    const compact = value.replace(/\s+/g, '');
    if (compact.length >= 8) {
        return true;
    }

    if (parseScannerModeCommand(compact)) {
        return true;
    }

    if (/^[A-Za-z0-9:_;+\-,]{4,}$/.test(compact)) {
        return true;
    }

    return false;
}

function renderInventoryScanResult(html) {
    const box = document.getElementById('inventoryScanResult');
    if (!box) {
        return;
    }

    if (!html) {
        box.style.display = 'none';
        box.innerHTML = '';
        return;
    }

    box.innerHTML = html;
    box.style.display = '';
}

async function lookupIngredientProduct(barcode) {
    return await loadJson(`api.php?endpoint=ingredients.lookup&household_id=${encodeURIComponent(householdId)}&barcode=${encodeURIComponent(barcode)}`);
}

function fillIngredientFieldsForBarcode(barcode, product = null) {
    const barcodeInput = document.getElementById('ingredientBarcode');
    if (barcodeInput) {
        barcodeInput.value = String(barcode || '');
        barcodeInput.dispatchEvent(new Event('input', {bubbles: true}));
        barcodeInput.dispatchEvent(new Event('change', {bubbles: true}));
    }
    if (product) {
        fillIngredientFieldsFromLookup(product);
    }
}

function shouldIgnoreDuplicateScan(barcode, mode) {
    const code = String(barcode || '').trim();
    const movement = mode === 'out' ? 'out' : 'in';
    if (!code) {
        return false;
    }

    const now = Date.now();
    const signature = `${movement}:${code}`;
    const withinWindow = (now - Number(inventoryLastProcessedScan.at || 0)) < 1200;
    if (withinWindow && inventoryLastProcessedScan.signature === signature) {
        return true;
    }

    inventoryLastProcessedScan = {signature, at: now};
    return false;
}

async function registerInventoryMovement(barcode, locationIdRaw = '') {
    const code = String(barcode || '').trim();
    if (!code) {
        return;
    }
    const locationId = Number(locationIdRaw || 0) || Number(document.getElementById('ingredientLocationId')?.value || 0) || 1;
    appendInventoryScanDebug(`Sender scan API: ${code}, mode=${inventoryScanMode}, loc=${locationId}`);
    const payload = await postJson('api.php?endpoint=scan', {
        barcode: code,
        household_id: Number(householdId || 1),
        location_id: locationId,
        movement_type: inventoryScanMode,
        quantity: 1,
    }, {includeDeviceToken: true});
    const autoAdded = !!payload?.auto_added_to_shopping_list;
    setInventoryScanStatus(`Lager registreret: ${inventoryScanMode === 'out' ? 'ud' : 'ind'} (${String(payload?.barcode || code)})${autoAdded ? ' · tilføjet til indkøbsliste' : ''}`);
    appendInventoryScanDebug(`API svar: ${String(payload?.status || 'ok')} ${String(payload?.message || '')}`);
    if (autoAdded) {
        appendInventoryScanDebug('Minimum nået: varen er automatisk tilføjet til indkøbslisten.');
    }
}

function openIngredientCreatePanel() {
    const panel = document.getElementById('ingredientCreateDetails');
    if (panel instanceof HTMLElement && panel.tagName.toLowerCase() === 'details') {
        panel.setAttribute('open', 'open');
    }
}

async function handleScannedBarcode(barcode) {
    const parsed = parseScannerPayload(barcode);
    const code = String(parsed.barcode || '').trim();
    if (code.length < 4) {
        appendInventoryScanDebug(`Ignoreret scan (${code.length} tegn): ${code || '(tom)'}`);
        return;
    }

    if (parsed.modeHint === 'in' || parsed.modeHint === 'out') {
        setInventoryScanMode(parsed.modeHint, 'scanner payload');
    }

    if (shouldIgnoreDuplicateScan(code, inventoryScanMode)) {
        appendInventoryScanDebug(`Ignoreret dublet-scan: ${code}`);
        return;
    }

    lastScannedBarcode = code;
    lastScanLookupProduct = null;

    setInventoryScanStatus(`Scanner: ${code}${parsed.modeHint ? ' (' + (parsed.modeHint === 'out' ? 'ud' : 'ind') + ')' : ''}`);

    const existing = findInventoryProductByBarcode(code);
    if (existing) {
        let autoRegisterMessage = 'Klik for at registrere bevægelse.';
        let autoRegisterClass = '';
        try {
            await registerInventoryMovement(code, String(existing.location_id || ''));
            autoRegisterMessage = `Bevægelse er registreret automatisk (${inventoryScanMode === 'out' ? 'ud' : 'ind'}).`;
            autoRegisterClass = ' state-ok';
            await refresh();
        } catch (e) {
            autoRegisterMessage = 'Automatisk registrering fejlede. Prøv knappen nedenfor.';
            autoRegisterClass = ' state-low';
            appendInventoryScanDebug('Auto-registrering fejlede: ' + String(e?.message || e));
        }

        renderInventoryScanResult(`
            <div>
                <strong>Varen findes allerede:</strong> ${esc(existing.name || 'Ukendt vare')}
                <div class="inventory-scan-copy">Barcode ${esc(code)} findes i lageret.</div>
                <div class="inventory-scan-copy${autoRegisterClass}">${esc(autoRegisterMessage)}</div>
            </div>
            <div class="inventory-scan-actions">
                <button type="button" class="scan-action primary" data-scan-action="register-movement" data-barcode="${esc(code)}" data-location-id="${esc(String(existing.location_id || ''))}">Registrer ${inventoryScanMode === 'out' ? 'ud af lager' : 'ind i lager'}</button>
                <button type="button" class="scan-action primary" data-scan-action="add-shopping" data-product-id="${esc(String(existing.id || ''))}" data-product-name="${esc(existing.name || '')}" data-store="${esc(existing.offer_store || existing.standard_store || '')}">Tilføj til indkøbsliste</button>
                <button type="button" class="scan-action" data-scan-action="open-create" data-barcode="${esc(code)}">Se/Opret detaljer</button>
            </div>
        `);
        setInventoryScanStatus(`Fundet i lager. ${autoRegisterMessage}`);
        return;
    }

    setInventoryScanStatus('Ikke fundet i lager. Slår op i API...');

    try {
        const payload = await lookupIngredientProduct(code);
        const product = payload?.product || null;
        lastScanLookupProduct = product;

        if (product) {
            renderInventoryScanResult(`
                <div>
                    <strong>Ikke oprettet endnu:</strong> ${esc(product.name || 'Ukendt vare')}
                    <div class="inventory-scan-copy">Barcode ${esc(code)} blev fundet i opslag. Du kan oprette varen nu.</div>
                </div>
                <div class="inventory-scan-actions">
                    <button type="button" class="scan-action primary" data-scan-action="open-create" data-barcode="${esc(code)}">Opret vare fra scanning</button>
                </div>
            `);
            setInventoryScanStatus('Opslag ok. Varen er klar til oprettelse.');
            return;
        }
    } catch (_e) {
        // handled below with generic message
    }

    renderInventoryScanResult(`
        <div>
            <strong>Ingen opslag fundet</strong>
            <div class="inventory-scan-copy">Barcode ${esc(code)} blev ikke fundet i lookup-kilden.</div>
        </div>
        <div class="inventory-scan-actions">
            <button type="button" class="scan-action primary" data-scan-action="open-create" data-barcode="${esc(code)}">Opret manuelt med barcode</button>
        </div>
    `);
    setInventoryScanStatus('Ikke fundet i lager eller opslag. Du kan oprette manuelt.', true);
}

function initInventoryScanActions() {
    const result = document.getElementById('inventoryScanResult');
    const scanInput = document.getElementById('inventoryScanInput');
    const scanSubmit = document.getElementById('inventoryScanSubmit');
    const cameraStart = document.getElementById('inventoryCameraStart');
    const cameraStop = document.getElementById('inventoryCameraStop');
    const cameraPreview = document.getElementById('inventoryCameraPreview');
    const modeInBtn = document.getElementById('scanModeIn');
    const modeOutBtn = document.getElementById('scanModeOut');

    if (!result || !scanInput || !scanSubmit || !cameraStart || !cameraStop || !cameraPreview || !modeInBtn || !modeOutBtn || result.dataset.scanActionsBound === '1') {
        return;
    }
    result.dataset.scanActionsBound = '1';

    let scanInputTimer = null;

    const submitScanInput = async () => {
        const code = String(scanInput.value || '').trim();
        if (!code) {
            return;
        }
        scanInput.value = '';
        appendInventoryScanDebug(`Manuel registrer: ${code}`);
        await handleScannedBarcode(code);
    };

    const processScannerFieldValue = async (rawValue, sourceLabel) => {
        const raw = String(rawValue || '').trim();
        if (!raw) {
            return;
        }

        const modeOnly = parseScannerModeCommand(raw);
        if (modeOnly) {
            setInventoryScanMode(modeOnly, sourceLabel + ' mode');
            scanInput.value = '';
            return;
        }

        const parsed = parseScannerPayload(raw);
        if (String(parsed.barcode || '').trim().length >= 4 || isLikelyScannerToken(raw)) {
            appendInventoryScanDebug(`${sourceLabel}: ${raw}`);
            scanInput.value = '';
            await handleScannedBarcode(raw);
        }
    };

    const setMode = (mode) => setInventoryScanMode(mode, 'klik');

    modeInBtn.addEventListener('click', () => setMode('in'));
    modeOutBtn.addEventListener('click', () => setMode('out'));
    setMode('in');

    const stopCameraScan = () => {
        inventoryCameraActive = false;
        if (inventoryCameraFrameToken !== null) {
            cancelAnimationFrame(inventoryCameraFrameToken);
            inventoryCameraFrameToken = null;
        }
        if (inventoryCameraStream) {
            inventoryCameraStream.getTracks().forEach(track => track.stop());
            inventoryCameraStream = null;
        }
        if (cameraPreview instanceof HTMLVideoElement) {
            cameraPreview.pause();
            cameraPreview.srcObject = null;
            cameraPreview.style.display = 'none';
        }
        cameraStop.style.display = 'none';
        cameraStart.style.display = '';
    };

    const cameraTick = async () => {
        if (!inventoryCameraActive) {
            return;
        }

        const video = cameraPreview;
        const detectorSupported = typeof window.BarcodeDetector === 'function';
        if (!detectorSupported || !(video instanceof HTMLVideoElement) || video.readyState < 2) {
            inventoryCameraFrameToken = requestAnimationFrame(() => {
                void cameraTick();
            });
            return;
        }

        try {
            const now = Date.now();
            if ((now - inventoryCameraLastDetectAt) >= 180) {
                inventoryCameraLastDetectAt = now;
                const detector = new window.BarcodeDetector({formats: ['ean_13', 'ean_8', 'upc_a', 'upc_e', 'code_128', 'qr_code']});
                const codes = await detector.detect(video);
                const first = Array.isArray(codes) && codes.length ? String(codes[0].rawValue || '').trim() : '';
                if (first) {
                    stopCameraScan();
                    await handleScannedBarcode(first);
                    return;
                }
            }
        } catch (_e) {
            // ignore frame-level detector errors and keep scanning
        }

        inventoryCameraFrameToken = requestAnimationFrame(() => {
            void cameraTick();
        });
    };

    const startCameraScan = async () => {
        if (inventoryCameraActive) {
            return;
        }

        if (!(navigator.mediaDevices && typeof navigator.mediaDevices.getUserMedia === 'function')) {
            setInventoryScanStatus('Kamera er ikke tilgængeligt på denne enhed/browser.', true);
            return;
        }
        if (typeof window.BarcodeDetector !== 'function') {
            setInventoryScanStatus('Kamera-scan understøttes ikke i denne browser. Brug scan-feltet eller ekstern scanner.', true);
            return;
        }

        try {
            inventoryCameraStream = await navigator.mediaDevices.getUserMedia({
                video: {
                    facingMode: {ideal: 'environment'},
                    width: {ideal: 1280},
                    height: {ideal: 720},
                },
                audio: false,
            });

            cameraPreview.srcObject = inventoryCameraStream;
            await cameraPreview.play();
            cameraPreview.style.display = '';
            cameraStart.style.display = 'none';
            cameraStop.style.display = '';
            inventoryCameraActive = true;
            setInventoryScanStatus('Kamera scanner aktiv. Peg på barcode.');
            inventoryCameraLastDetectAt = 0;
            void cameraTick();
        } catch (e) {
            stopCameraScan();
            setInventoryScanStatus('Kunne ikke starte kamera: ' + String(e?.message || e), true);
        }
    };

    scanSubmit.addEventListener('click', () => {
        void submitScanInput();
    });

    scanInput.addEventListener('input', () => {
        if (scanInputTimer) {
            clearTimeout(scanInputTimer);
            scanInputTimer = null;
        }

        // Bluetooth/ESP32 keyboards sometimes only emit input changes, not usable keydown keys.
        scanInputTimer = setTimeout(() => {
            void processScannerFieldValue(scanInput.value, 'input-event');
        }, 90);
    });

    scanInput.addEventListener('paste', (event) => {
        const pasted = String(event.clipboardData?.getData('text') || '').trim();
        if (!pasted) {
            return;
        }

        event.preventDefault();
        void processScannerFieldValue(pasted, 'paste-event');
    });

    scanInput.addEventListener('beforeinput', (event) => {
        const inserted = String(event.data || '').trim();
        if (!inserted || !isLikelyScannerToken(inserted)) {
            return;
        }

        // Some HID/BLE devices deliver chunks via beforeinput without reliable keydown events.
        if (inserted.length >= 4) {
            event.preventDefault();
            void processScannerFieldValue(inserted, 'beforeinput-event');
        }
    });

    scanInput.addEventListener('keydown', (event) => {
        if (event.key === 'Enter') {
            event.preventDefault();
            void submitScanInput();
        }
    });

    scanInput.addEventListener('blur', () => {
        const page = String(window.location.hash || '').replace('#', '') || String(currentPage || '');
        if (page === 'lager' || page === 'inventorySection') {
            setTimeout(() => {
                scanInput.focus();
            }, 120);
        }
    });

    cameraStart.addEventListener('click', () => {
        void startCameraScan();
    });

    cameraStop.addEventListener('click', () => {
        stopCameraScan();
        setInventoryScanStatus('Kamera scanning stoppet.');
    });

    document.addEventListener('visibilitychange', () => {
        if (document.hidden) {
            stopCameraScan();
        }
    });

    result.addEventListener('click', async (event) => {
        const target = event.target;
        if (!(target instanceof HTMLElement)) {
            return;
        }

        const actionEl = target.closest('[data-scan-action]');
        if (!(actionEl instanceof HTMLElement)) {
            return;
        }

        const action = String(actionEl.dataset.scanAction || '');
        if (action === 'open-create') {
            const barcode = String(actionEl.dataset.barcode || lastScannedBarcode || '').trim();
            openIngredientCreatePanel();
            fillIngredientFieldsForBarcode(barcode, lastScanLookupProduct);
            setIngredientStatus('Scan klar i formularen. Tjek felter og tryk Opret ingrediens.');
            return;
        }

        if (action === 'register-movement') {
            const barcode = String(actionEl.dataset.barcode || lastScannedBarcode || '').trim();
            const locationId = String(actionEl.dataset.locationId || '').trim();
            if (!barcode) {
                return;
            }
            try {
                await registerInventoryMovement(barcode, locationId);
                await refresh();
            } catch (e) {
                setInventoryScanStatus('Kunne ikke registrere lagerbevægelse: ' + String(e?.message || e), true);
                appendInventoryScanDebug('API fejl: ' + String(e?.message || e));
            }
            return;
        }

        if (action === 'add-shopping') {
            const productId = Number(actionEl.dataset.productId || 0);
            const productName = String(actionEl.dataset.productName || '').trim();
            const store = String(actionEl.dataset.store || '').trim();
            if (!productName) {
                return;
            }

            try {
                await addInventoryProductToShopping({id: productId, name: productName, offer_store: store});
                setInventoryScanStatus('Varen er tilføjet til indkøbslisten.');
                appendInventoryScanDebug(`Tilføjet til indkøbsliste: ${productName}`);
                await refresh();
            } catch (e) {
                setInventoryScanStatus('Kunne ikke tilføje til indkøbslisten: ' + String(e?.message || e), true);
                appendInventoryScanDebug('Tilføj fejl: ' + String(e?.message || e));
            }
        }
    });
}

function ingredientFormValues() {
    return {
        product_id: ingredientEditingProductId > 0 ? ingredientEditingProductId : undefined,
        name: document.getElementById('ingredientName')?.value.trim() || '',
        barcode: document.getElementById('ingredientBarcode')?.value.trim() || '',
        brand: document.getElementById('ingredientBrand')?.value.trim() || '',
        image_url: document.getElementById('ingredientImageUrl')?.value.trim() || '',
        product_type: document.getElementById('ingredientProductType')?.value || 'andet',
        location_id: document.getElementById('ingredientLocationId')?.value || '',
        quantity: document.getElementById('ingredientQuantity')?.value.trim() || '',
        minimum_quantity: document.getElementById('ingredientMinimum')?.value.trim() || '',
        weight_grams: document.getElementById('ingredientWeightGrams')?.value.trim() || '',
        store_name: document.getElementById('ingredientStore')?.value.trim() || '',
        price: document.getElementById('ingredientPrice')?.value.trim() || '',
        offer_store: document.getElementById('ingredientOfferStore')?.value.trim() || '',
        offer_price: document.getElementById('ingredientOfferPrice')?.value.trim() || '',
        offer_valid_to: document.getElementById('ingredientOfferValidTo')?.value.trim() || '',
    };
}

function setIngredientFormMode(editing) {
    const createBtn = document.getElementById('ingredientCreateBtn');
    const cancelBtn = document.getElementById('ingredientCancelEditBtn');
    const panelTitle = document.querySelector('#ingredientCreateDetails summary span');
    const panelChip = document.querySelector('#ingredientCreateDetails summary .chip');

    if (createBtn) {
        createBtn.textContent = editing ? 'Gem ændringer' : 'Opret ingrediens';
    }
    if (cancelBtn) {
        cancelBtn.style.display = editing ? '' : 'none';
    }
    if (panelTitle) {
        panelTitle.textContent = editing ? 'Rediger ingrediens' : 'Ny ingrediens';
    }
    if (panelChip) {
        panelChip.textContent = editing ? 'Rediger' : '+ Tilføj';
    }
}

function resetIngredientEditMode(clearForm = false) {
    ingredientEditingProductId = 0;
    setIngredientFormMode(false);

    if (!clearForm) {
        return;
    }

    const formIds = [
        'ingredientBarcode',
        'ingredientName',
        'ingredientBrand',
        'ingredientImageUrl',
        'ingredientProductType',
        'ingredientQuantity',
        'ingredientMinimum',
        'ingredientWeightGrams',
        'ingredientStore',
        'ingredientPrice',
        'ingredientOfferStore',
        'ingredientOfferPrice',
        'ingredientOfferValidTo',
    ];

    formIds.forEach((id) => {
        const el = document.getElementById(id);
        if (!el || !('value' in el)) {
            return;
        }
        if (id === 'ingredientProductType') {
            el.value = 'andet';
            return;
        }
        el.value = '';
    });
}

function startIngredientEditFromProduct(product) {
    ingredientEditingProductId = Number(product?.id || 0);
    if (ingredientEditingProductId <= 0) {
        return;
    }
    const locationIdRaw = String(product?.location_id || '');

    const setValue = (id, value) => {
        const el = document.getElementById(id);
        if (!el || !('value' in el)) {
            return;
        }
        el.value = value;
    };

    setValue('ingredientBarcode', String(product?.barcode || ''));
    setValue('ingredientName', String(product?.name || ''));
    setValue('ingredientBrand', String(product?.brand || ''));
    setValue('ingredientImageUrl', String(product?.image_url || ''));
    setValue('ingredientProductType', String(product?.product_type || 'andet'));
    setValue('ingredientLocationId', locationIdRaw);
    setValue('ingredientQuantity', String(product?.quantity ?? ''));
    setValue('ingredientMinimum', String(product?.minimum_quantity ?? ''));
    setValue('ingredientWeightGrams', String(product?.weight_grams ?? ''));
    setValue('ingredientStore', String(product?.standard_store || ''));
    setValue('ingredientPrice', product?.standard_price === null || product?.standard_price === undefined ? '' : String(product.standard_price));
    setValue('ingredientOfferStore', String(product?.offer_store || ''));
    setValue('ingredientOfferPrice', product?.offer_price === null || product?.offer_price === undefined ? '' : String(product.offer_price));

    const validTo = String(product?.offer_valid_to || '');
    setValue('ingredientOfferValidTo', validTo ? validTo.slice(0, 10) : '');

    if (accessToken) {
        void loadIngredientLocations().then(() => {
            setValue('ingredientLocationId', locationIdRaw);
        });
    }

    setIngredientFormMode(true);
    setIngredientStatus('Redigerer: ' + String(product?.name || 'Ukendt vare'));
}

function fillIngredientFieldsFromLookup(product) {
    if (!product || typeof product !== 'object') {
        return;
    }

    const nameEl = document.getElementById('ingredientName');
    const brandEl = document.getElementById('ingredientBrand');
    const imageEl = document.getElementById('ingredientImageUrl');
    const typeEl = document.getElementById('ingredientProductType');

    if (nameEl && !nameEl.value.trim() && product.name) {
        nameEl.value = String(product.name);
    }
    if (brandEl && !brandEl.value.trim() && product.brand) {
        brandEl.value = String(product.brand);
    }
    if (imageEl && !imageEl.value.trim() && product.image_url) {
        imageEl.value = String(product.image_url);
    }
    if (typeEl && product.product_type && product.product_type !== 'andet') {
        typeEl.value = String(product.product_type);
    }

    // Show AI note + estimated shelf days as a status hint
    const parts = [];
    if (product.ai_note) {
        parts.push(product.ai_note);
    }
    if (product.estimated_shelf_days) {
        parts.push(`Typisk holdbarhed: ~${product.estimated_shelf_days} dage fra køb.`);
    }
    if (parts.length) {
        setIngredientStatus('AI: ' + parts.join(' '));
    }
}

async function lookupIngredientFromBarcode() {
    if (!accessToken) {
        setIngredientStatus('Log ind for at kunne slå ingredienser op.', true);
        return;
    }

    const barcode = document.getElementById('ingredientBarcode')?.value.trim() || '';
    if (!barcode) {
        setIngredientStatus('Skriv en barcode først.', true);
        return;
    }

    setIngredientStatus('Slår op i varedatabase...');
    try {
        const payload = await loadJson(`api.php?endpoint=ingredients.lookup&household_id=${encodeURIComponent(householdId)}&barcode=${encodeURIComponent(barcode)}`);
        fillIngredientFieldsFromLookup(payload.product || null);
        setIngredientStatus('Opslag gennemført. Felter er udfyldt med data fra databasen.');
    } catch (error) {
        setIngredientStatus('Kunne ikke finde barcode: ' + (error?.message || 'Ukendt fejl'), true);
    }
}

async function createIngredientFromForm() {
    if (!accessToken) {
        setIngredientStatus('Log ind for at oprette ingredienser.', true);
        return;
    }

    const values = ingredientFormValues();
    if (!values.name && !values.barcode) {
        setIngredientStatus('Angiv mindst navn eller barcode.', true);
        return;
    }

    setIngredientStatus(ingredientEditingProductId > 0 ? 'Gemmer ændringer...' : 'Opretter ingrediens...');
    try {
        const payload = await postJson('api.php?endpoint=ingredients.create', {
            household_id: householdId,
            product_id: values.product_id,
            name: values.name,
            barcode: values.barcode,
            brand: values.brand,
            image_url: values.image_url,
            product_type: values.product_type,
            location_id: values.location_id !== '' ? Number(values.location_id) : undefined,
            quantity: values.quantity,
            minimum_quantity: values.minimum_quantity,
            weight_grams: values.weight_grams,
            store_name: values.store_name,
            price: values.price,
            offer_store: values.offer_store,
            offer_price: values.offer_price,
            offer_valid_to: values.offer_valid_to,
        });

        setIngredientStatus((ingredientEditingProductId > 0 ? 'Ingrediens opdateret: ' : 'Ingrediens oprettet: ') + (payload?.ingredient?.name || values.name || values.barcode));
        document.getElementById('ingredientCreateDetails')?.removeAttribute('open');
        resetIngredientEditMode(true);
        refresh();
    } catch (error) {
        setIngredientStatus('Kunne ikke gemme ingrediens: ' + (error?.message || 'Ukendt fejl'), true);
    }
}

async function loadIngredientLocations() {
    if (!accessToken) {
        return;
    }

    try {
        const payload = await loadJson(`api.php?endpoint=locations&household_id=${encodeURIComponent(householdId)}`);
        const locations = Array.isArray(payload.locations) ? payload.locations : [];
        const select = document.getElementById('ingredientLocationId');
        if (!select) {
            return;
        }

        if (!locations.length) {
            select.innerHTML = '<option value="">Ingen lokationer fundet</option>';
            return;
        }

        const typeIcon = {'dry': '📦', 'fridge': '❄', 'freezer': '🧊', 'counter': '🍎', 'other': '🔵'};
        select.innerHTML = locations.map(loc => {
            const icon = typeIcon[loc.location_type] || '📦';
            return `<option value="${esc(String(loc.id))}">${icon} ${esc(loc.name)}</option>`;
        }).join('');
    } catch (_e) {
        // Lokationer er valgfrie at indlæse - fejl er ikke kritisk
    }
}

function initIngredientTools() {
    const lookupBtn = document.getElementById('ingredientLookupBtn');
    const createBtn = document.getElementById('ingredientCreateBtn');
    const cancelEditBtn = document.getElementById('ingredientCancelEditBtn');
    const barcodeInput = document.getElementById('ingredientBarcode');
    const panel = document.getElementById('ingredientCreateDetails');

    if (lookupBtn) {
        lookupBtn.addEventListener('click', lookupIngredientFromBarcode);
    }
    if (createBtn) {
        createBtn.addEventListener('click', createIngredientFromForm);
    }
    if (cancelEditBtn) {
        cancelEditBtn.addEventListener('click', () => {
            resetIngredientEditMode(true);
            setIngredientStatus('Redigering annulleret.');
        });
    }
    if (barcodeInput) {
        barcodeInput.addEventListener('keydown', (event) => {
            if (event.key === 'Enter') {
                event.preventDefault();
                lookupIngredientFromBarcode();
            }
        });
    }

    // Hent lokationer når panelet åbnes for første gang
    if (panel) {
        panel.addEventListener('toggle', () => {
            if (panel.open && accessToken) {
                loadIngredientLocations();
            }
            if (!panel.open) {
                resetIngredientEditMode(false);
            }
        });
    }

    const locationSelect = document.getElementById('ingredientLocationId');
    if (locationSelect && locationSelect.dataset.scanContextBound !== '1') {
        locationSelect.dataset.scanContextBound = '1';
        locationSelect.addEventListener('change', () => {
            void syncDeviceScanContext(true);
        });
    }
}

function initBarcodeScannerCapture() {
    if (document.body.dataset.barcodeCaptureBound === '1') {
        return;
    }
    document.body.dataset.barcodeCaptureBound = '1';

    let buffer = '';
    let lastKeyAt = 0;
    let flushTimer = null;

    const resetBuffer = () => {
        buffer = '';
        lastKeyAt = 0;
        if (flushTimer) {
            clearTimeout(flushTimer);
            flushTimer = null;
        }
    };

    const commitBufferToBarcodeField = async () => {
        const raw = String(buffer || '').trim();
        resetBuffer();

        const modeOnly = parseScannerModeCommand(raw);
        if (modeOnly) {
            setInventoryScanMode(modeOnly, 'scanner mode command');
            return;
        }

        const code = raw;

        if (code.length < 4) {
            appendInventoryScanDebug(`Ignoreret kort buffer: ${code || '(tom)'}`);
            return;
        }

        try {
            await handleScannedBarcode(code);
        } catch (_e) {
            setInventoryScanStatus('Scan blev fanget, men kunne ikke behandles.', true);
        }
    };

    document.addEventListener('keydown', (event) => {
        if (event.defaultPrevented || event.ctrlKey || event.metaKey || event.altKey) {
            return;
        }

        if (event.key === 'F9' || event.key === 'ArrowUp' || event.key === 'PageUp') {
            setInventoryScanMode('in', `special key ${event.key}`);
            return;
        }
        if (event.key === 'F10' || event.key === 'ArrowDown' || event.key === 'PageDown') {
            setInventoryScanMode('out', `special key ${event.key}`);
            return;
        }

        const active = document.activeElement;
        const isTypingField = active instanceof HTMLInputElement
            || active instanceof HTMLTextAreaElement
            || (active instanceof HTMLElement && active.isContentEditable);

        // Do not interfere with normal typing except when directly in scanner-specific fields.
        const allowedInputIds = ['ingredientBarcode', 'inventoryScanInput'];
        if (isTypingField && (!(active instanceof HTMLInputElement) || !allowedInputIds.includes(active.id))) {
            return;
        }

        const now = Date.now();
        if (lastKeyAt && (now - lastKeyAt) > 100) {
            buffer = '';
        }
        lastKeyAt = now;

        if (event.key === 'Enter' || event.key === 'Tab') {
            if (buffer.length >= 4) {
                event.preventDefault();
                appendInventoryScanDebug(`Scanner suffix ${event.key} med buffer ${buffer}`);
                void commitBufferToBarcodeField();
            } else {
                appendInventoryScanDebug(`Suffix ${event.key} uden gyldig buffer`);
                resetBuffer();
            }
            return;
        }

        if (/^[0-9A-Za-z\-:;,_+]$/.test(event.key)) {
            buffer += event.key;
            if (flushTimer) {
                clearTimeout(flushTimer);
            }
            flushTimer = setTimeout(() => {
                appendInventoryScanDebug(`Timeout commit med buffer ${buffer}`);
                void commitBufferToBarcodeField();
            }, 140);
            return;
        }

        resetBuffer();
    }, true);

    document.addEventListener('paste', (event) => {
        const page = String(window.location.hash || '').replace('#', '') || String(currentPage || '');
        if (!(page === 'lager' || page === 'inventorySection')) {
            return;
        }

        const pasted = String(event.clipboardData?.getData('text') || '').trim();
        if (!pasted || !isLikelyScannerToken(pasted)) {
            return;
        }

        event.preventDefault();
        appendInventoryScanDebug(`Global paste-capture: ${pasted}`);
        void handleScannedBarcode(pasted).catch(() => {
            setInventoryScanStatus('Scan blev fanget via paste, men kunne ikke behandles.', true);
        });
    }, true);
}

function fillSelect(selectId, items, formatter) {
    const select = document.getElementById(selectId);
    if (!select) {
        return;
    }

    select.innerHTML = items.map(item => `<option value="${esc(item.value)}">${esc(item.label)}</option>`).join('');
    if (!items.length) {
        select.innerHTML = '<option value="">Ingen data</option>';
    }
    if (typeof formatter === 'function') {
        formatter(select);
    }
}

function setTokenFromInputs() {
    const accessInput = document.getElementById('adminAccessToken');
    const deviceInput = document.getElementById('adminDeviceToken');

    accessToken = (accessInput?.value || '').trim();
    deviceToken = (deviceInput?.value || '').trim();

    if (accessToken) {
        window.localStorage.setItem('madAccessToken', accessToken);
    }
    if (deviceToken) {
        window.localStorage.setItem('madDeviceToken', deviceToken);
    }
}

async function loadAdminOverview() {
    setTokenFromInputs();
    if (!accessToken) {
        setAdminStatus('Indtast access token for at bruge admin-konsollen.');
        return;
    }

    setAdminStatus('Indlaeser brugere og husstande...');

    try {
        const [usersPayload, householdsPayload] = await Promise.all([
            loadJson('api.php?endpoint=admin.users'),
            loadJson('api.php?endpoint=admin.households'),
        ]);

        const users = Array.isArray(usersPayload.users) ? usersPayload.users : [];
        const households = Array.isArray(householdsPayload.households) ? householdsPayload.households : [];

        fillSelect('assignUserId', users.map(user => ({
            value: String(user.id),
            label: `${user.id} - ${user.full_name} (${user.initials})`,
        })));

        fillSelect('assignHouseholdId', households.map(household => ({
            value: String(household.id),
            label: `${household.id} - ${household.name}`,
        })));

        const userLines = users.map(user => {
            const memberships = Array.isArray(user.households) && user.households.length
                ? user.households.map(h => `${h.name} (${h.role})`).join(', ')
                : 'ingen husstand';
            return `${user.id} ${user.initials} ${user.full_name} -> ${memberships}`;
        });

        const householdLines = households.map(h => `${h.id} ${h.name} | users: ${h.user_count}`);
        document.getElementById('adminOverview').textContent =
            'Brugere:\n' + (userLines.join('\n') || 'Ingen') + '\n\nHusstande:\n' + (householdLines.join('\n') || 'Ingen');

        setAdminStatus(`Oversigt opdateret (${users.length} brugere, ${households.length} husstande).`);
    } catch (error) {
        setAdminStatus('Fejl ved indlaesning af admin-overblik: ' + (error?.message || 'Ukendt fejl'));
    }
}

async function createHouseholdFromForm() {
    setTokenFromInputs();
    const name = document.getElementById('newHouseholdName').value.trim();
    const adminUserIdRaw = document.getElementById('newHouseholdAdminUser').value.trim();

    if (!name) {
        setAdminStatus('Angiv navn paa ny husstand.');
        return;
    }

    const body = {name};
    if (adminUserIdRaw !== '') {
        body.admin_user_id = Number(adminUserIdRaw);
    }

    try {
        const payload = await postJson('api.php?endpoint=admin.households.create', body);
        setAdminStatus('Husstand oprettet: #' + payload.household.id + ' ' + payload.household.name);
        document.getElementById('newHouseholdName').value = '';
        await loadAdminOverview();
    } catch (error) {
        setAdminStatus('Kunne ikke oprette husstand: ' + (error?.message || 'Ukendt fejl'));
    }
}

async function createUserFromForm() {
    setTokenFromInputs();
    const initials = document.getElementById('newUserInitials').value.trim().toUpperCase();
    const fullName = document.getElementById('newUserName').value.trim();
    const phone = document.getElementById('newUserPhone').value.trim();

    if (!initials || !fullName || !phone) {
        setAdminStatus('Udfyld initialer, navn og telefon for ny bruger.');
        return;
    }

    try {
        const payload = await postJson('api.php?endpoint=admin.users.create', {
            initials,
            full_name: fullName,
            phone_e164: phone,
            is_active: 1,
        });
        setAdminStatus('Bruger oprettet: #' + payload.user.id + ' ' + payload.user.full_name);
        document.getElementById('newUserInitials').value = '';
        document.getElementById('newUserName').value = '';
        document.getElementById('newUserPhone').value = '';
        await loadAdminOverview();
    } catch (error) {
        setAdminStatus('Kunne ikke oprette bruger: ' + (error?.message || 'Ukendt fejl'));
    }
}

async function assignMembershipFromForm() {
    setTokenFromInputs();
    const userId = Number(document.getElementById('assignUserId').value || 0);
    const householdIdSelect = Number(document.getElementById('assignHouseholdId').value || 0);
    const role = document.getElementById('assignRole').value;

    if (!userId || !householdIdSelect) {
        setAdminStatus('Vaelg baade bruger og husstand.');
        return;
    }

    try {
        await postJson('api.php?endpoint=admin.households.assign_user', {
            user_id: userId,
            household_id: householdIdSelect,
            role,
        });
        setAdminStatus(`Bruger ${userId} tilknyttet husstand ${householdIdSelect} som ${role}.`);
        await loadAdminOverview();
    } catch (error) {
        setAdminStatus('Kunne ikke tilknytte bruger: ' + (error?.message || 'Ukendt fejl'));
    }
}

async function requestTwoFaCodeFromForm() {
    setTokenFromInputs();
    const initials = document.getElementById('twofaInitials').value.trim().toUpperCase();
    if (!initials) {
        setAdminStatus('Angiv initialer for 2FA request.');
        return;
    }

    try {
        const payload = await postJson('api.php?endpoint=auth.request_code', {initials}, {includeDeviceToken: true});
        if (payload.challenge_id) {
            document.getElementById('twofaChallengeId').value = payload.challenge_id;
        }
        setAdminStatus('2FA kode sendt. Challenge: ' + (payload.challenge_id || 'ukendt'));
    } catch (error) {
        setAdminStatus('Kunne ikke anmode 2FA kode: ' + (error?.message || 'Ukendt fejl'));
    }
}

async function verifyTwoFaCodeFromForm() {
    setTokenFromInputs();
    const challengeId = document.getElementById('twofaChallengeId').value.trim();
    const code = document.getElementById('twofaCode').value.trim();

    if (!challengeId || !code) {
        setAdminStatus('Angiv baade challenge id og SMS kode.');
        return;
    }

    try {
        const payload = await postJson('api.php?endpoint=auth.verify_code', {
            challenge_id: challengeId,
            code,
        }, {includeDeviceToken: true});

        if (payload.access_token) {
            accessToken = payload.access_token;
            window.localStorage.setItem('madAccessToken', accessToken);
            const accessInput = document.getElementById('adminAccessToken');
            if (accessInput) {
                accessInput.value = accessToken;
            }
        }

        setAdminStatus('2FA verificeret. Nyt access token gemt.');
        await loadAdminOverview();
        refresh();
    } catch (error) {
        setAdminStatus('Kunne ikke verificere 2FA kode: ' + (error?.message || 'Ukendt fejl'));
    }
}

function initAdminConsole() {
    const accessInput = document.getElementById('adminAccessToken');
    const deviceInput = document.getElementById('adminDeviceToken');
    if (accessInput) {
        accessInput.value = accessToken;
    }
    if (deviceInput) {
        deviceInput.value = deviceToken;
    }

    document.getElementById('saveAdminTokensButton').addEventListener('click', () => {
        setTokenFromInputs();
        setAdminStatus('Tokens gemt lokalt i browseren.');
        refresh();
    });
    document.getElementById('loadAdminOverviewButton').addEventListener('click', loadAdminOverview);
    document.getElementById('createHouseholdButton').addEventListener('click', createHouseholdFromForm);
    document.getElementById('createUserButton').addEventListener('click', createUserFromForm);
    document.getElementById('assignMembershipButton').addEventListener('click', assignMembershipFromForm);
    document.getElementById('request2faButton').addEventListener('click', requestTwoFaCodeFromForm);
    document.getElementById('verify2faButton').addEventListener('click', verifyTwoFaCodeFromForm);
}

async function fetchAiIdeas() {
    const aiButton = document.getElementById('aiSuggestButton');
    const aiStatus = document.getElementById('aiStatus');
    const aiResult = document.getElementById('aiResult');

    if (!accessToken) {
        aiStatus.textContent = 'Log ind foerst. AI bruger samme adgangstoken som resten af husstanden.';
        aiResult.innerHTML = '';
        return;
    }

    aiButton.disabled = true;
    aiStatus.textContent = 'Henter forslag fra AI...';

    try {
        const payload = await postJson('api.php?endpoint=ai.meal_ideas', {
            household_id: householdId,
        });
        renderAiIdeas(payload);
    } catch (error) {
        aiStatus.textContent = 'AI-fejl: ' + (error?.message || 'Ukendt fejl');
        aiResult.innerHTML = '<div class="empty">Kunne ikke hente AI-forslag lige nu.</div>';
    } finally {
        aiButton.disabled = false;
    }
}

async function refresh() {
    const syncStatus = document.getElementById('syncStatus');

    if (!accessToken) {
        document.getElementById('scansBody').innerHTML = '<div class="empty">Log ind for at se husstandens scanlog.</div>';
        document.getElementById('productsBody').innerHTML = '<div class="empty">Log ind for at se husstandens varer og lokationer.</div>';
        const shoppingBody = document.getElementById('shoppingBody');
        if (shoppingBody) {
            shoppingBody.innerHTML = '<div class="empty">Log ind for at se indkøbskandidater.</div>';
        }
        const offersBody = document.getElementById('offersBody');
        if (offersBody) {
            offersBody.innerHTML = '<div class="empty">Log ind for at se tilbud.</div>';
        }
        const leafletOffersBody = document.getElementById('leafletOffersBody');
        if (leafletOffersBody) {
            leafletOffersBody.innerHTML = '<div class="empty">Log ind for at se varer fra tilbudsaviser.</div>';
        }
        const shoppingChip = document.getElementById('shoppingChip');
        if (shoppingChip) {
            shoppingChip.textContent = '0 på sedlen';
        }
        const offersChip = document.getElementById('offersChip');
        if (offersChip) {
            offersChip.textContent = '0 tilbud fundet';
        }
        const leafletOffersChip = document.getElementById('leafletOffersChip');
        if (leafletOffersChip) {
            leafletOffersChip.textContent = '0 fundet';
        }
        document.getElementById('productSummary').textContent = 'Data er nu knyttet til den bruger og de husstande, du er tildelt.';
        document.getElementById('scanSummary').textContent = 'Scanlog vises kun for den aktive husstand efter login.';
        document.getElementById('heroSummaryMeta').textContent = 'Adgangen er nu husstands-styret. Hent et access token via login, og aabn siden med access_token i URL eller localStorage.';
        syncStatus.textContent = 'Gaestetilstand';
        return;
    }

    try {
        let recent;
        let products;
        let shoppingList;
        let shopping;
        let offerFeed;

        try {
            recent = await loadJson(`api.php?endpoint=recent&limit=25&household_id=${encodeURIComponent(householdId)}`);
        } catch (e) {
            throw new Error('recent: ' + formatConnectionError(e));
        }

        try {
            products = await loadJson(`api.php?endpoint=products&household_id=${encodeURIComponent(householdId)}`);
        } catch (e) {
            throw new Error('products: ' + formatConnectionError(e));
        }

        try {
            shoppingList = await loadJson(`api.php?endpoint=shopping.list&household_id=${encodeURIComponent(householdId)}`);
        } catch (e) {
            throw new Error('shopping.list: ' + formatConnectionError(e));
        }

        try {
            shopping = await loadJson(`api.php?endpoint=shopping.candidates&household_id=${encodeURIComponent(householdId)}`);
        } catch (e) {
            throw new Error('shopping.candidates: ' + formatConnectionError(e));
        }

        try {
            offerFeed = await loadJson('api.php?endpoint=shopping.offer_feed&limit=500');
        } catch (e) {
            throw new Error('shopping.offer_feed: ' + formatConnectionError(e));
        }

        const scans = recent.scans || [];
        const productList = products.products || [];
        inventoryProductsCache = Array.isArray(productList) ? productList : [];
        const lowStock = productList.filter(product => Number(product.quantity ?? 0) <= Number(product.minimum_quantity ?? 0)).length;
        const latest = scans[0] || null;
        const balanced = Math.max(productList.length - lowStock, 0);
        const leafletOffersWithoutNetto = (offerFeed.items || []).filter(item => {
            const storeName = String(item?.store_name || '').trim().toLowerCase();
            return storeName !== 'netto'
                && storeName !== 'kvickly'
                && storeName !== '365discount'
                && storeName !== '365 discount'
                && storeName !== '365';
        });

        const shoppingListItems = Array.isArray(shoppingList.items) ? shoppingList.items : [];
        const shoppingCandidateItems = Array.isArray(shopping.items) ? shopping.items : [];

        renderScans(scans);
        renderProducts(productList);
        initInventoryCardActions();
        initInventoryShoppingSearch();
        initInventoryScanActions();
        if (shoppingListItems.length > 0) {
            renderShoppingList(shoppingListItems, shoppingList.list || null, shopping.items || []);
        } else {
            // Fallback to candidate view so users still see actionable items before first manual add.
            renderShoppingCandidates(shoppingCandidateItems);
        }
        renderOfferHighlights(shopping.items || []);
        renderLeafletOfferFeed(leafletOffersWithoutNetto, offerFeed.items || []);

        document.getElementById('scanCount').textContent = String(scans.length);
        document.getElementById('productCount').textContent = String(productList.length);
        document.getElementById('lowStockCount').textContent = String(lowStock);
        document.getElementById('activityChip').textContent = `${scans.length} hændelser`;
        document.getElementById('inventoryChip').textContent = `${balanced} i balance`;
        if (shoppingListItems.length > 0) {
            document.getElementById('shoppingChip').textContent = `${Number(shoppingList?.summary?.unchecked_items || 0)} på sedlen`;
        } else {
            document.getElementById('shoppingChip').textContent = `${Number(shopping?.summary?.total_candidates || 0)} klare kandidater`;
        }
        const offersChip = document.getElementById('offersChip');
        if (offersChip) {
            offersChip.textContent = `${Number(shopping?.summary?.with_offer || 0)} tilbud fundet`;
        }
        const leafletOffersChip = document.getElementById('leafletOffersChip');
        if (leafletOffersChip) {
            leafletOffersChip.textContent = `${leafletOffersWithoutNetto.length} fundet`;
        }
        document.getElementById('planChip').textContent = `${productList.length ? 'Klar til ugeblik' : 'Afventer varer'}`;
        document.getElementById('attentionItemsValue').textContent = String(lowStock);
        document.getElementById('scanSummary').textContent = scans.length ? 'Scanlog findes stadig, men er gjort diskret.' : 'Scannerflowet er klar, men ligger i baggrunden indtil der er brug for det.';
        document.getElementById('productSummary').textContent = productList.length ? 'Fødevarer vises som husstandens kort med lagerstatus og ernæring, når data findes.' : 'Ingen varer er endnu blevet oprettet i lageret.';
        document.getElementById('lowStockSummary').textContent = lowStock ? 'Varer under eller ved minimum bør være næste fokus i indkøb.' : 'Ingen varer presser minimumsgrænsen lige nu.';
        document.getElementById('heroSummaryValue').textContent = `${productList.length} varer`;
        document.getElementById('heroSummaryMeta').textContent = lowStock ? `${lowStock} varer kræver snart opmærksomhed i ${document.getElementById('householdLabel').textContent}.` : 'Lageret ser stabilt og mindre teknisk ud lige nu.';
        document.getElementById('latestMovementValue').textContent = latest ? 'Lager + plan' : 'Madplan';
        document.getElementById('latestMovementMeta').textContent = latest
            ? `${latest.product_name || 'Ukendt vare'} er senest registreret og kan senere indgå direkte i madplan og indkøb for husstanden.`
            : 'Når nye fødevarer kommer ind, bliver kortene automatisk beriget her.';
        setInventoryScanStatus('Venter på scanning...');
        syncStatus.textContent = 'Synkroniseret ' + new Date().toLocaleTimeString('da-DK', {hour: '2-digit', minute: '2-digit'});
    } catch (e) {
        document.getElementById('scansBody').innerHTML = '<div class="empty">Fejl ved indlæsning af scannerflow.</div>';
        document.getElementById('productsBody').innerHTML = '<div class="empty">Fejl ved indlæsning af lagerdata.</div>';
        const shoppingBody = document.getElementById('shoppingBody');
        if (shoppingBody) {
            shoppingBody.innerHTML = '<div class="empty">Fejl ved indlæsning af indkøbskandidater.</div>';
        }
        const offersBody = document.getElementById('offersBody');
        if (offersBody) {
            offersBody.innerHTML = '<div class="empty">Fejl ved indlæsning af tilbud.</div>';
        }
        const leafletOffersBody = document.getElementById('leafletOffersBody');
        if (leafletOffersBody) {
            leafletOffersBody.innerHTML = '<div class="empty">Fejl ved indlæsning af tilbudsavis-liste.</div>';
        }
        setInventoryScanStatus('Data kunne ikke opdateres lige nu.', true);

        const reason = formatConnectionError(e);
        if (/HTTP\s+401/i.test(reason)) {
            accessToken = '';
            window.localStorage.removeItem('madAccessToken');
            isPlatformAdmin = false;
            setAdminOnlyVisibility(false);
            lockApp();
            setGateStatus('Din session er udløbet. Log ind igen.', true);
            syncStatus.textContent = 'Session udløbet';
            return;
        }

        syncStatus.textContent = 'Forbindelse fejlede (' + reason + ')';
    }
}

document.getElementById('refreshButton').addEventListener('click', refresh);
document.getElementById('aiSuggestButton').addEventListener('click', fetchAiIdeas);
window.addEventListener('hashchange', updateNavFromHash);
initAdminConsole();
initIngredientTools();
initBarcodeScannerCapture();
initInventoryScanActions();
initShoppingListActions();
initShoppingListKeyboardActions();
applyTraditionalPage(<?= json_encode($currentPage, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>);
updateNavFromHash();
initAuthGate();
enforceAuthGate().then(() => {
    if (accessToken) {
        refresh();
    }
});
setInterval(refresh, 5000);
const pollModeInterval = setInterval(pollInventoryModeFromServer, 500);  // Poll server for mode changes from ESP32 button
console.log('[Init] Started mode polling interval:', pollModeInterval);
pollInventoryModeFromServer();  // Initial poll
setInterval(pollInventoryLastScanFromServer, 700);
pollInventoryLastScanFromServer();
void syncDeviceScanContext(true);
setInterval(() => {
    void syncDeviceScanContext(false);
}, 10000);
</script>
</body>
</html>
