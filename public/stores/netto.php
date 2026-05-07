<?php

declare(strict_types=1);

$householdId = max(1, (int) ($_GET['household_id'] ?? 1));
$householdName = trim((string) ($_GET['household_name'] ?? ''));
if ($householdName === '') {
    $householdName = 'Husstand ' . $householdId;
}

?>
<!doctype html>
<html lang="da">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Netto Tilbud</title>
    <style>
        :root {
            --accent: #d32f2f;
            --accent-dark: #b52a2a;
            --green: #2f6a56;
            --text: #14231d;
            --muted: #61716a;
            --line: rgba(20,35,29,0.1);
            --panel: #fdf8f1;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        html { scroll-behavior: smooth; }
        body { font-family: "Avenir Next","Helvetica Neue","Segoe UI",sans-serif; color: var(--text); background: #f0ece4; min-height: 100vh; }
        .site-header { position: sticky; top: 0; z-index: 100; background: #d32f2f; color: white; box-shadow: 0 2px 12px rgba(0,0,0,0.18); }
        .header-inner { max-width: 1200px; margin: 0 auto; display: flex; align-items: center; gap: 16px; height: 64px; padding: 0 20px; }
        .back-btn { display: flex; align-items: center; gap: 6px; color: rgba(255,255,255,0.85); text-decoration: none; font-size: 14px; font-weight: 600; flex-shrink: 0; }
        .back-btn:hover { color: white; }
        .header-brand { flex: 1; display: flex; align-items: center; }
        .header-brand svg { height: 28px; fill: white; }
        .header-chip { background: rgba(255,255,255,0.22); border-radius: 999px; padding: 4px 12px; font-size: 12px; font-weight: 700; }
        .store-hero { background: linear-gradient(135deg,#d32f2f 0%,#b71c1c 100%); color: white; padding: 28px 20px 24px; }
        .hero-inner { max-width: 1200px; margin: 0 auto; display: flex; align-items: center; gap: 24px; }
        .store-logo-box { width: 88px; height: 88px; background: white; border-radius: 18px; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 16px rgba(0,0,0,0.22); flex-shrink: 0; }
        .hero-text h1 { font-size: 26px; font-weight: 800; }
        .hero-text p { margin-top: 4px; font-size: 13px; opacity: 0.88; }
        .hero-stats { display: flex; gap: 24px; margin-top: 12px; }
        .hstat strong { display: block; font-size: 22px; font-weight: 800; }
        .hstat span { display: block; font-size: 11px; opacity: 0.72; text-transform: uppercase; letter-spacing: 0.06em; }
        .controls-wrap { background: white; border-bottom: 1px solid rgba(0,0,0,0.07); padding: 12px 20px; position: sticky; top: 64px; z-index: 90; }
        .controls-inner { max-width: 1200px; margin: 0 auto; display: grid; grid-template-columns: 1fr auto auto; gap: 10px; align-items: center; }
        .search-box { display: flex; align-items: center; background: #f5f0e8; border: 1px solid var(--line); border-radius: 10px; padding: 0 12px; gap: 8px; }
        .search-box svg { opacity: 0.4; flex-shrink: 0; }
        input.search-input { border: none; background: transparent; padding: 10px 0; font: inherit; font-size: 14px; width: 100%; outline: none; color: var(--text); }
        select.sort-sel { padding: 10px 12px; border-radius: 10px; border: 1px solid var(--line); font: inherit; font-size: 14px; background: #f5f0e8; cursor: pointer; color: var(--text); }
        .add-btn { padding: 10px 20px; border-radius: 10px; background: var(--accent); color: white; border: none; cursor: pointer; font: inherit; font-size: 14px; font-weight: 700; white-space: nowrap; }
        .add-btn:hover:not(:disabled) { background: var(--accent-dark); }
        .add-btn:disabled { background: #bbb; cursor: not-allowed; }
        .content { max-width: 1200px; margin: 24px auto; padding: 0 20px 100px; }
        .offers-grid { display: grid; grid-template-columns: repeat(auto-fill,minmax(196px,1fr)); gap: 14px; }
        .offer-card { background: white; border-radius: 14px; border: 2px solid transparent; overflow: hidden; cursor: pointer; transition: transform 0.15s, box-shadow 0.15s, border-color 0.15s; position: relative; box-shadow: 0 2px 8px rgba(0,0,0,0.07); }
        .offer-card:hover { transform: translateY(-2px); box-shadow: 0 8px 22px rgba(0,0,0,0.12); }
        .offer-card.selected { border-color: var(--accent); }
        .chk { position: absolute; top: 10px; right: 10px; width: 22px; height: 22px; border-radius: 50%; border: 2px solid rgba(0,0,0,0.15); background: white; transition: all 0.15s; }
        .offer-card.selected .chk { background: var(--accent); border-color: var(--accent); }
        .offer-card.selected .chk::after { content: '✓'; color: white; font-size: 11px; font-weight: 700; position: absolute; top: 50%; left: 50%; transform: translate(-50%,-50%); }
        .chk { position: absolute; }
        .img-wrap { height: 110px; background: #f7f3ed; display: flex; align-items: center; justify-content: center; }
        .img-wrap img { width: 100%; height: 100%; object-fit: contain; padding: 8px; }
        .no-img { font-size: 38px; opacity: 0.15; }
        .card-body { padding: 11px 12px 13px; }
        .offer-name { font-size: 13px; font-weight: 700; line-height: 1.3; margin-bottom: 6px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
        .offer-price { font-size: 20px; font-weight: 800; color: var(--accent); }
        .offer-valid { margin-top: 4px; font-size: 11px; color: var(--muted); }
        .match-badge { display: inline-block; margin-top: 5px; padding: 2px 7px; border-radius: 5px; font-size: 10px; font-weight: 700; background: rgba(47,106,86,0.12); color: var(--green); }
        .state-panel { text-align: center; padding: 60px 16px; color: var(--muted); }
        .state-panel .icon { font-size: 44px; margin-bottom: 12px; }
        .state-panel h3 { font-size: 17px; color: var(--text); margin-bottom: 6px; }
        .spinner-wrap { display: flex; justify-content: center; padding: 60px 0; }
        .spinner { width: 36px; height: 36px; border: 3px solid rgba(211,47,47,0.15); border-top-color: var(--accent); border-radius: 50%; animation: spin 0.75s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }
        .sel-bar { position: fixed; bottom: 0; left: 0; right: 0; background: var(--accent); color: white; padding: 14px 20px; display: flex; align-items: center; justify-content: space-between; gap: 12px; transform: translateY(100%); transition: transform 0.25s cubic-bezier(0.34,1.56,0.64,1); z-index: 200; box-shadow: 0 -4px 20px rgba(0,0,0,0.15); }
        .sel-bar.visible { transform: translateY(0); }
        .sel-bar span { font-weight: 600; }
        .sel-btns { display: flex; gap: 8px; }
        .sbtn { padding: 9px 18px; border-radius: 9px; border: 2px solid rgba(255,255,255,0.55); background: transparent; color: white; cursor: pointer; font: inherit; font-weight: 700; font-size: 14px; }
        .sbtn.primary { background: white; color: var(--accent); border-color: white; }
        @media (max-width: 580px) {
            .hero-inner { flex-direction: column; text-align: center; }
            .hero-stats { justify-content: center; }
            .controls-inner { grid-template-columns: 1fr; }
            .sort-sel { display: none; }
            .add-btn { display: none; }
        }
    </style>
</head>
<body>

<header class="site-header">
    <div class="header-inner">
        <a id="backLink" href="../live.php?household_id=<?= htmlspecialchars((string) $householdId) ?>&household_name=<?= htmlspecialchars($householdName) ?>&page=indkoeb" class="back-btn">← Tilbage</a>
        <div class="header-brand">
            <!-- netto wordmark -->
            <svg viewBox="0 0 150 38" xmlns="http://www.w3.org/2000/svg">
                <text y="32" font-family="Arial Black,Arial,sans-serif" font-size="36" font-weight="900" fill="white">netto</text>
            </svg>
        </div>
        <div class="header-chip" id="headerChip">Indlæser…</div>
    </div>
</header>

<div class="store-hero">
    <div class="hero-inner">
        <div class="store-logo-box">
            <!-- Netto logo -->
            <svg viewBox="0 0 80 80" xmlns="http://www.w3.org/2000/svg" width="72" height="72">
                <circle cx="40" cy="40" r="40" fill="#fdd835"/>
                <text x="40" y="48" font-family="Arial Black,Arial,sans-serif" font-size="36" font-weight="900" fill="#111" text-anchor="middle" dominant-baseline="middle">N</text>
            </svg>
        </div>
        <div class="hero-text">
            <h1>Netto Tilbud</h1>
            <p id="heroSub">Henter aktuelle tilbud…</p>
            <div class="hero-stats">
                <div class="hstat"><strong id="hTotal">–</strong><span>Tilbud</span></div>
                <div class="hstat"><strong id="hSel">0</strong><span>Valgt</span></div>
                <div class="hstat"><strong id="hMatched">–</strong><span>I katalog</span></div>
            </div>
        </div>
    </div>
</div>

<div class="controls-wrap">
    <div class="controls-inner">
        <div class="search-box">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
            <input type="text" id="searchInput" class="search-input" placeholder="Søg i Netto tilbud…">
        </div>
        <select id="sortSel" class="sort-sel">
            <option value="name">Navn A–Å</option>
            <option value="price_asc">Billigst først</option>
            <option value="price_desc">Dyrest først</option>
        </select>
        <button id="addBtn" class="add-btn" disabled>Tilføj valgte</button>
    </div>
</div>

<div class="content">
    <div id="offersContainer"><div class="spinner-wrap"><div class="spinner"></div></div></div>
</div>

<div class="sel-bar" id="selBar">
    <span id="selLabel">0 varer valgt</span>
    <div class="sel-btns">
        <button class="sbtn" id="clearBtn">Ryd</button>
        <button class="sbtn primary" id="selAddBtn">Tilføj til indkøbsseddel</button>
    </div>
</div>

<script>
(function () {
    const params     = new URLSearchParams(location.search);
    const qToken     = params.get('access_token');
    if (qToken) localStorage.setItem('madAccessToken', qToken);
    let token        = qToken || localStorage.getItem('madAccessToken') || '';
    let household    = params.get('household_id') || '1';
    const STORE      = 'Netto';
    let all = [], selected = new Set();

    function esc(s) { const d = document.createElement('div'); d.textContent = String(s ?? ''); return d.innerHTML; }
    function dkk(v) { return v != null ? Number(v).toLocaleString('da-DK',{minimumFractionDigits:2,maximumFractionDigits:2})+'\u00a0kr.' : '–'; }
    function shortDate(s) { return s ? new Date(s).toLocaleDateString('da-DK',{day:'numeric',month:'short'}) : null; }

    async function load() {
        const box = document.getElementById('offersContainer');
        if (!token) {
            box.innerHTML = `<div class="state-panel"><div class="icon">🔒</div><h3>Ikke logget ind</h3><p>Gå tilbage og log ind.</p></div>`;
            return;
        }
        try {
            const r = await fetch(`../api.php?endpoint=shopping.offer_feed&store=${encodeURIComponent(STORE)}&limit=500`,
                { headers: { Authorization: 'Bearer ' + token } });
            const d = await r.json();
            if (!r.ok) throw new Error(d.error || 'API fejl');
            all = (d.items || []).filter(i => i.store_name === STORE);
            const matched = all.filter(i => i.is_catalog_matched).length;
            const validTo = all.find(i => i.valid_to)?.valid_to;
            document.getElementById('heroSub').textContent =
                all.length + ' tilbud' + (validTo ? ' · Gyldig til ' + shortDate(validTo) : '');
            document.getElementById('hTotal').textContent   = all.length;
            document.getElementById('hMatched').textContent = matched;
            document.getElementById('headerChip').textContent = all.length + ' tilbud';
            applyFilters();
        } catch (e) {
            box.innerHTML = `<div class="state-panel"><div class="icon">⚠️</div><h3>Fejl</h3><p>${esc(e.message)}</p></div>`;
        }
    }

    function applyFilters() {
        const q    = document.getElementById('searchInput').value.toLowerCase();
        const sort = document.getElementById('sortSel').value;
        let items  = all.filter(i => (i.product_name||'').toLowerCase().includes(q));
        if (sort === 'price_asc')  items.sort((a,b) => (a.price??999)-(b.price??999));
        if (sort === 'price_desc') items.sort((a,b) => (b.price??0)-(a.price??0));
        if (sort === 'name')       items.sort((a,b) => (a.product_name||'').localeCompare(b.product_name||'','da'));
        render(items);
    }

    function render(items) {
        const box = document.getElementById('offersContainer');
        if (!items.length) {
            box.innerHTML = `<div class="state-panel"><div class="icon">🛒</div><h3>Ingen varer</h3><p>Prøv et andet søgeord.</p></div>`;
            return;
        }
        box.innerHTML = `<div class="offers-grid">${items.map(card).join('')}</div>`;
        box.querySelectorAll('.offer-card').forEach(el =>
            el.addEventListener('click', () => toggle(Number(el.dataset.id))));
    }

    function card(i) {
        const sel   = selected.has(i.id);
        const valid = shortDate(i.valid_to);
        const img   = i.image_url
            ? `<img src="${esc(i.image_url)}" alt="" loading="lazy">`
            : `<div class="no-img">🛍</div>`;
        const badge = i.is_catalog_matched ? `<div class="match-badge">✓ I dit katalog</div>` : '';
        return `<div class="offer-card${sel?' selected':''}" data-id="${i.id}">
            <div class="chk"></div>
            <div class="img-wrap">${img}</div>
            <div class="card-body">
                <div class="offer-name">${esc(i.product_name||'Ukendt vare')}</div>
                <div class="offer-price">${dkk(i.price)}</div>
                ${valid ? `<div class="offer-valid">Gyldig til ${esc(valid)}</div>` : ''}
                ${badge}
            </div>
        </div>`;
    }

    function toggle(id) {
        selected.has(id) ? selected.delete(id) : selected.add(id);
        document.querySelectorAll('.offer-card').forEach(el =>
            el.classList.toggle('selected', selected.has(Number(el.dataset.id))));
        const n = selected.size;
        document.getElementById('hSel').textContent   = n;
        document.getElementById('selLabel').textContent = `${n} vare${n!==1?'r':''} valgt`;
        document.getElementById('selBar').classList.toggle('visible', n > 0);
        document.getElementById('addBtn').disabled = n === 0;
    }

    async function addToList() {
        if (!selected.size) return;
        const items = Array.from(selected).map(id => {
            const o = all.find(x => x.id === id);
            return { title: o?.product_name || 'Ukendt', store: STORE };
        });
        try {
            const r = await fetch(`../api.php?endpoint=shopping.list.add_items&household_id=${encodeURIComponent(household)}`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Authorization: 'Bearer ' + token },
                body: JSON.stringify({ items }),
            });
            const d = await r.json();
            if (!r.ok) throw new Error(d.error || 'Fejl');
            toast(`${d.items_added ?? items.length} vare(r) tilføjet ✓`);
            selected.clear();
            document.querySelectorAll('.offer-card.selected').forEach(el => el.classList.remove('selected'));
            document.getElementById('hSel').textContent = 0;
            document.getElementById('selBar').classList.remove('visible');
            document.getElementById('addBtn').disabled = true;
        } catch (e) { alert('Fejl: ' + e.message); }
    }

    function toast(msg) {
        const t = Object.assign(document.createElement('div'), { textContent: msg });
        Object.assign(t.style, { position:'fixed', bottom:'80px', left:'50%', transform:'translateX(-50%)',
            background:'#14231d', color:'white', padding:'11px 20px', borderRadius:'9px',
            fontSize:'14px', fontWeight:'600', zIndex:'9999', boxShadow:'0 4px 16px rgba(0,0,0,0.25)',
            transition:'opacity 0.3s', whiteSpace:'nowrap' });
        document.body.appendChild(t);
        setTimeout(() => { t.style.opacity = '0'; setTimeout(() => t.remove(), 300); }, 2500);
    }

    async function resolveActiveHousehold() {
        if (!token) {
            return;
        }
        try {
            const r = await fetch('../api.php?endpoint=auth.me', {
                headers: { Authorization: 'Bearer ' + token },
            });
            const d = await r.json();
            if (!r.ok) {
                return;
            }
            const active = Number(d?.active_household_id || d?.user?.active_household_id || 0);
            if (active > 0) {
                household = String(active);
            }
        } catch (_e) {
            // Keep URL household fallback when auth lookup fails.
        }
    }

    document.getElementById('searchInput').addEventListener('input', applyFilters);
    document.getElementById('sortSel').addEventListener('change', applyFilters);
    document.getElementById('addBtn').addEventListener('click', addToList);
    document.getElementById('selAddBtn').addEventListener('click', addToList);
    document.getElementById('clearBtn').addEventListener('click', () => {
        selected.clear();
        document.querySelectorAll('.offer-card.selected').forEach(el => el.classList.remove('selected'));
        document.getElementById('hSel').textContent = 0;
        document.getElementById('selBar').classList.remove('visible');
        document.getElementById('addBtn').disabled = true;
    });

    // Preserve access token in back link
    const bl = document.getElementById('backLink');
    if (bl && token) {
        const u = new URL(bl.href, location.origin);
        u.searchParams.set('access_token', token);
        bl.href = u.pathname + u.search;
    }

    resolveActiveHousehold().then(() => {
        load();
    });
}());
</script>
</body>
</html>
