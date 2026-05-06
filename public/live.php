<?php

declare(strict_types=1);

$householdId = max(1, (int) ($_GET['household_id'] ?? 1));
$householdName = trim((string) ($_GET['household_name'] ?? ''));

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
<body>
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
                <div class="inventory-grid" id="productsBody"></div>
            </section>

            <section class="card section" id="shoppingSection">
                <div class="section-head">
                    <div>
                        <p class="section-kicker">Indkøb</p>
                        <h2 class="section-title">Lav beholdning bliver til næste liste</h2>
                    </div>
                    <div class="chip" id="shoppingChip">0 klare kandidater</div>
                </div>
                <div class="placeholder-grid">
                    <article class="placeholder-card">
                        <h3>Automatisk indkøbsseddel</h3>
                        <p>Varer under minimum kan blive næste mobil-liste. Når vi forbinder madplan og opskrifter, kan listen prioriteres efter både husholdningens behov og lokation.</p>
                    </article>
                    <article class="placeholder-card">
                        <h3>Butik og tilbud</h3>
                        <p>README peger på butikstilbud som næste lag. Denne zone er lagt klar til at samle butik, status og tilbud uden at flytte rundt på resten af HMI'et.</p>
                    </article>
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
        <a class="nav-item active" href="#top" data-nav-target="top">
            <strong>🏠</strong>
            <span>Overblik</span>
        </a>
        <a class="nav-item" href="#madplanSection" data-nav-target="madplanSection">
            <strong>📅</strong>
            <span>Madplan</span>
        </a>
        <a class="nav-item" href="#recipesSection" data-nav-target="recipesSection">
            <strong>📖</strong>
            <span>Opskrifter</span>
        </a>
        <a class="nav-item" href="#inventorySection" data-nav-target="inventorySection">
            <strong>📦</strong>
            <span>Lager</span>
        </a>
        <a class="nav-item" href="#shoppingSection" data-nav-target="shoppingSection">
            <strong>🛒</strong>
            <span>Indkøb</span>
        </a>
    </nav>
</main>

<script>
const householdId = <?= json_encode($householdId, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
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

async function resolveSession() {
    if (!accessToken) {
        isPlatformAdmin = false;
        setAdminOnlyVisibility(false);
        return false;
    }

    try {
        const me = await loadJson('api.php?endpoint=auth.me');
        const user = me.user || {};
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

async function loadJson(url) {
    const res = await fetch(url, {
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

    const res = await fetch(url, {
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
        return `<article class="inventory-card">
            <div class="inventory-visual">
                ${image}
                <div>
                    <div class="inventory-top">
                        <div>
                            <h3 class="inventory-name">${esc(product.name || 'Ukendt vare')}</h3>
                            <p class="inventory-brand">${esc(product.brand || 'Ukendt brand')}</p>
                        </div>
                        <span class="state-badge ${state.className}">${esc(state.label)}</span>
                    </div>
                    <p class="inventory-code">Fødevarekort med lagerstatus og ernæring.</p>
                </div>
            </div>
            <div class="inventory-meta">
                <div class="meta-block">
                    <span class="meta-label">Beholdning</span>
                    <div class="meta-value">${esc(formatQuantity(product.quantity ?? 0))}</div>
                </div>
                <div class="meta-block">
                    <span class="meta-label">Minimum</span>
                    <div class="meta-value">${esc(formatQuantity(product.minimum_quantity ?? 0))}</div>
                </div>
            </div>
            ${nutritionStrip}
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
}

function updateNavFromHash() {
    const hash = window.location.hash.replace('#', '');
    const fallback = 'top';
    const targetId = hash || fallback;

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
        document.getElementById('productSummary').textContent = 'Data er nu knyttet til den bruger og de husstande, du er tildelt.';
        document.getElementById('scanSummary').textContent = 'Scanlog vises kun for den aktive husstand efter login.';
        document.getElementById('heroSummaryMeta').textContent = 'Adgangen er nu husstands-styret. Hent et access token via login, og aabn siden med access_token i URL eller localStorage.';
        syncStatus.textContent = 'Gaestetilstand';
        return;
    }

    try {
        const [recent, products] = await Promise.all([
            loadJson(`api.php?endpoint=recent&limit=25&household_id=${encodeURIComponent(householdId)}`),
            loadJson(`api.php?endpoint=products&household_id=${encodeURIComponent(householdId)}`)
        ]);

        const scans = recent.scans || [];
        const productList = products.products || [];
        const lowStock = productList.filter(product => Number(product.quantity ?? 0) <= Number(product.minimum_quantity ?? 0)).length;
        const latest = scans[0] || null;
        const balanced = Math.max(productList.length - lowStock, 0);

        renderScans(scans);
        renderProducts(productList);

        document.getElementById('scanCount').textContent = String(scans.length);
        document.getElementById('productCount').textContent = String(productList.length);
        document.getElementById('lowStockCount').textContent = String(lowStock);
        document.getElementById('activityChip').textContent = `${scans.length} hændelser`;
        document.getElementById('inventoryChip').textContent = `${balanced} i balance`;
        document.getElementById('shoppingChip').textContent = `${lowStock} klare kandidater`;
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
        syncStatus.textContent = 'Synkroniseret ' + new Date().toLocaleTimeString('da-DK', {hour: '2-digit', minute: '2-digit'});
    } catch (e) {
        document.getElementById('scansBody').innerHTML = '<div class="empty">Fejl ved indlæsning af scannerflow.</div>';
        document.getElementById('productsBody').innerHTML = '<div class="empty">Fejl ved indlæsning af lagerdata.</div>';
        syncStatus.textContent = 'Forbindelse fejlede';
    }
}

document.getElementById('refreshButton').addEventListener('click', refresh);
document.getElementById('aiSuggestButton').addEventListener('click', fetchAiIdeas);
window.addEventListener('hashchange', updateNavFromHash);
initAdminConsole();
applyTraditionalPage(<?= json_encode($currentPage, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>);
updateNavFromHash();
initAuthGate();
enforceAuthGate().then(() => {
    if (accessToken) {
        refresh();
    }
});
setInterval(refresh, 5000);
</script>
</body>
</html>
