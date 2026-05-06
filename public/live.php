<?php

declare(strict_types=1);
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
        .topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
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
        .inventory-grid,
        .flow-grid,
        .placeholder-grid {
            display: grid;
            gap: 12px;
        }
        .inventory-card,
        .scan-card,
        .placeholder-card {
            padding: 16px;
            border-radius: 20px;
            border: 1px solid var(--line);
            background: var(--panel-strong);
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
        .placeholder-card p {
            margin: 0;
            color: var(--muted);
            line-height: 1.5;
        }
        .stack {
            display: grid;
            gap: 10px;
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
            left: 12px;
            right: 12px;
            bottom: 12px;
            z-index: 2;
            width: 100%;
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 8px;
            padding: 10px;
            border: 1px solid rgba(255,255,255,0.72);
            border-radius: 24px;
            background: rgba(20, 35, 29, 0.9);
            color: #f5efe6;
            box-shadow: 0 14px 30px rgba(20,35,29,0.26);
            backdrop-filter: blur(22px);
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
            .inventory-grid,
            .placeholder-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
            .flow-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
            .layout {
                grid-template-columns: minmax(0, 1.35fr) minmax(300px, 0.85fr);
                align-items: start;
            }
            .bottom-nav {
                position: static;
                max-width: 420px;
                margin: 26px auto 0;
            }
        }
        @media (min-width: 1080px) {
            .inventory-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }
            .flow-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
<main class="app" id="top">
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

        <section class="hero">
            <div class="hero-copy">
                <div class="eyebrow">Mobil HMI ramme</div>
                <h1>Køkkenets rolige overblik.</h1>
                <p>En mobil-først styreflade til lager, scanning og de næste rutiner omkring indkøb og madplan. Denne version holder fast i de live data, men lægger den visuelle retning for den rigtige HMI.</p>
                <div class="hero-actions">
                    <button class="action" type="button" id="refreshButton">Opdater nu</button>
                    <a class="action ghost" href="#inventorySection">Se lager</a>
                    <a class="action ghost" href="#activitySection">Seneste flow</a>
                </div>
            </div>
            <aside class="hero-aside">
                <div class="card signal-card">
                    <span class="signal-label">Husets rytme</span>
                    <div class="signal-value" id="heroSummaryValue">0 varer</div>
                    <div class="signal-meta" id="heroSummaryMeta">Vi henter live data fra lager og scanninger.</div>
                </div>
                <div class="card signal-card">
                    <span class="signal-label">Sidste aktivitet</span>
                    <div class="signal-value" id="latestMovementValue">Ingen endnu</div>
                    <div class="signal-meta" id="latestMovementMeta">Når scannerne bliver brugt, dukker strømmen op her.</div>
                </div>
            </aside>
        </section>
    </header>

    <section class="status-grid" aria-label="Statusoversigt">
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
            <p class="metric-note" id="scanSummary">Auto-refresh hvert 5. sekund for scannerflowet.</p>
        </article>
    </section>

    <div class="layout">
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

        <div class="stack">
            <section class="card section" id="activitySection">
                <div class="section-head">
                    <div>
                        <p class="section-kicker">Flow</p>
                        <h2 class="section-title">Seneste scanninger</h2>
                    </div>
                    <div class="chip" id="activityChip">0 hændelser</div>
                </div>
                <div class="flow-grid" id="scansBody"></div>
            </section>

            <section class="card section" id="nextSection">
                <div class="section-head">
                    <div>
                        <p class="section-kicker">Næste lag</p>
                        <h2 class="section-title">Kommende HMI-zoner</h2>
                    </div>
                </div>
                <div class="placeholder-grid">
                    <article class="placeholder-card">
                        <h3>Indkøb</h3>
                        <p>Lav beholdning kan blive næste input til en mobil indkøbsliste med prioritering, butik og status pr. vare.</p>
                    </article>
                    <article class="placeholder-card">
                        <h3>Madplan</h3>
                        <p>Opskrifter, sæson og lager kan samles i en daglig planvisning, så mobilen bliver den naturlige frontflade.</p>
                    </article>
                </div>
            </section>
        </div>
    </div>

    <nav class="bottom-nav" aria-label="Primær navigation">
        <a class="nav-item active" href="#top">
            <strong>◉</strong>
            <span>Hjem</span>
        </a>
        <a class="nav-item" href="#inventorySection">
            <strong>◌</strong>
            <span>Lager</span>
        </a>
        <a class="nav-item" href="#activitySection">
            <strong>◎</strong>
            <span>Flow</span>
        </a>
        <a class="nav-item" href="#nextSection">
            <strong>⌂</strong>
            <span>Mere</span>
        </a>
    </nav>
</main>

<script>
async function loadJson(url) {
    const res = await fetch(url, {cache: 'no-store'});
    if (!res.ok) {
        throw new Error('HTTP ' + res.status);
    }
    return await res.json();
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
        return `<article class="inventory-card">
            <div class="inventory-top">
                <div>
                    <h3 class="inventory-name">${esc(product.name || 'Ukendt vare')}</h3>
                    <p class="inventory-code">Barcode ${esc(product.barcode || '-')}</p>
                </div>
                <span class="state-badge ${state.className}">${esc(state.label)}</span>
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
                    <p class="scan-time">${esc(scan.created_at || '')}</p>
                </div>
                <span class="scan-type ${isOut ? 'scan-out' : 'scan-in'}">${esc((scan.movement_type || 'in').toUpperCase())}</span>
            </div>
            <div class="scan-meta">
                <div class="meta-block">
                    <span class="meta-label">Barcode</span>
                    <div class="meta-value">${esc(scan.barcode || '-')}</div>
                </div>
                <div class="meta-block">
                    <span class="meta-label">Mængde</span>
                    <div class="meta-value">${esc(formatQuantity(scan.quantity_delta ?? 0))}</div>
                </div>
            </div>
        </article>`;
    }).join('');
}

async function refresh() {
    const syncStatus = document.getElementById('syncStatus');

    try {
        const [recent, products] = await Promise.all([
            loadJson('api.php?endpoint=recent&limit=25'),
            loadJson('api.php?endpoint=products')
        ]);

        const scans = recent.scans || [];
        const productList = products.products || [];
        const lowStock = productList.filter(product => Number(product.quantity ?? 0) <= Number(product.minimum_quantity ?? 0)).length;
        const latest = scans[0] || null;

        renderScans(scans);
        renderProducts(productList);

        document.getElementById('scanCount').textContent = String(scans.length);
        document.getElementById('productCount').textContent = String(productList.length);
        document.getElementById('lowStockCount').textContent = String(lowStock);
        document.getElementById('activityChip').textContent = `${scans.length} hændelser`;
        document.getElementById('inventoryChip').textContent = `${Math.max(productList.length - lowStock, 0)} i balance`;
        document.getElementById('scanSummary').textContent = scans.length ? 'Bevægelser opdateres automatisk hvert 5. sekund.' : 'Scannerflowet er klar, men der er endnu ingen hændelser.';
        document.getElementById('productSummary').textContent = productList.length ? 'Antal kendte varer, der allerede er i hjemmets system.' : 'Ingen varer er endnu blevet oprettet i lageret.';
        document.getElementById('lowStockSummary').textContent = lowStock ? 'Varer under eller ved minimum bør være næste fokus i indkøb.' : 'Ingen varer presser minimumsgrænsen lige nu.';
        document.getElementById('heroSummaryValue').textContent = `${productList.length} varer`;
        document.getElementById('heroSummaryMeta').textContent = lowStock ? `${lowStock} varer kræver snart opmærksomhed.` : 'Lageret ser stabilt ud lige nu.';
        document.getElementById('latestMovementValue').textContent = latest ? String(latest.movement_type || 'in').toUpperCase() : 'Ingen endnu';
        document.getElementById('latestMovementMeta').textContent = latest
            ? `${latest.product_name || 'Ukendt vare'} blev registreret ${latest.created_at || ''}.`
            : 'Når scannerne bliver brugt, dukker strømmen op her.';
        syncStatus.textContent = 'Synkroniseret ' + new Date().toLocaleTimeString('da-DK', {hour: '2-digit', minute: '2-digit'});
    } catch (e) {
        document.getElementById('scansBody').innerHTML = '<div class="empty">Fejl ved indlæsning af scannerflow.</div>';
        document.getElementById('productsBody').innerHTML = '<div class="empty">Fejl ved indlæsning af lagerdata.</div>';
        syncStatus.textContent = 'Forbindelse fejlede';
    }
}

document.getElementById('refreshButton').addEventListener('click', refresh);
refresh();
setInterval(refresh, 5000);
</script>
</body>
</html>
