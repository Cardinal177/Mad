<?php

declare(strict_types=1);
?>
<!doctype html>
<html lang="da">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Mad Live Test View</title>
    <style>
        :root {
            --bg: #f4f6f8;
            --panel: #ffffff;
            --text: #1a1f2b;
            --muted: #5f6b7a;
            --accent: #0f766e;
            --line: #d9dee5;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            color: var(--text);
            background: linear-gradient(180deg, #eef3f7 0%, #f8fafb 60%, #ffffff 100%);
        }
        .wrap {
            max-width: 1100px;
            margin: 24px auto;
            padding: 0 14px;
        }
        h1 { margin: 0 0 8px; font-size: 24px; }
        .sub { color: var(--muted); margin: 0 0 18px; }
        .grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
        }
        .panel {
            background: var(--panel);
            border: 1px solid var(--line);
            border-radius: 10px;
            overflow: hidden;
        }
        .head {
            padding: 10px 12px;
            border-bottom: 1px solid var(--line);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .title { font-weight: 600; }
        .badge {
            color: #fff;
            background: var(--accent);
            border-radius: 999px;
            padding: 2px 8px;
            font-size: 12px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }
        th, td {
            padding: 8px 10px;
            border-bottom: 1px solid var(--line);
            text-align: left;
            vertical-align: top;
        }
        th { color: var(--muted); font-weight: 600; }
        tr:last-child td { border-bottom: 0; }
        .row-in { color: #0f766e; font-weight: 600; }
        .row-out { color: #b91c1c; font-weight: 600; }
        .foot {
            margin-top: 12px;
            color: var(--muted);
            font-size: 12px;
        }
        @media (max-width: 900px) {
            .grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
<div class="wrap">
    <h1>Mad Live Test View</h1>
    <p class="sub">Midlertidig testside til validering af scanning og lager. Endeligt HMI-design laves senere.</p>

    <div class="grid">
        <section class="panel">
            <div class="head">
                <span class="title">Seneste scanninger</span>
                <span id="scanCount" class="badge">0</span>
            </div>
            <table>
                <thead>
                <tr>
                    <th>Tid</th>
                    <th>Type</th>
                    <th>Barcode</th>
                    <th>Produkt</th>
                    <th>Antal</th>
                </tr>
                </thead>
                <tbody id="scansBody"></tbody>
            </table>
        </section>

        <section class="panel">
            <div class="head">
                <span class="title">Nuværende lager</span>
                <span id="productCount" class="badge">0</span>
            </div>
            <table>
                <thead>
                <tr>
                    <th>Barcode</th>
                    <th>Produkt</th>
                    <th>Beholdning</th>
                    <th>Minimum</th>
                </tr>
                </thead>
                <tbody id="productsBody"></tbody>
            </table>
        </section>
    </div>

    <div class="foot">Auto-refresh hver 5. sekund.</div>
</div>

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

async function refresh() {
    try {
        const [recent, products] = await Promise.all([
            loadJson('api.php?endpoint=recent&limit=25'),
            loadJson('api.php?endpoint=products')
        ]);

        const scans = recent.scans || [];
        const scanRows = scans.map(s => {
            const cls = s.movement_type === 'out' ? 'row-out' : 'row-in';
            return `<tr>
                <td>${esc(s.created_at)}</td>
                <td class="${cls}">${esc(s.movement_type).toUpperCase()}</td>
                <td>${esc(s.barcode)}</td>
                <td>${esc(s.product_name)}</td>
                <td>${esc(s.quantity_delta)}</td>
            </tr>`;
        }).join('');

        document.getElementById('scanCount').textContent = scans.length;
        document.getElementById('scansBody').innerHTML = scanRows || '<tr><td colspan="5">Ingen scanninger endnu.</td></tr>';

        const rows = (products.products || []).map(p => `<tr>
            <td>${esc(p.barcode)}</td>
            <td>${esc(p.name)}</td>
            <td>${esc(p.quantity ?? 0)}</td>
            <td>${esc(p.minimum_quantity ?? 0)}</td>
        </tr>`).join('');

        document.getElementById('productCount').textContent = (products.products || []).length;
        document.getElementById('productsBody').innerHTML = rows || '<tr><td colspan="4">Ingen produkter endnu.</td></tr>';
    } catch (e) {
        document.getElementById('scansBody').innerHTML = '<tr><td colspan="5">Fejl ved indlaesning.</td></tr>';
        document.getElementById('productsBody').innerHTML = '<tr><td colspan="4">Fejl ved indlaesning.</td></tr>';
    }
}

refresh();
setInterval(refresh, 5000);
</script>
</body>
</html>
