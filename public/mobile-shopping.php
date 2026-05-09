<?php
declare(strict_types=1);
?><!doctype html>
<html lang="da">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>Mad - Mobil Indkøb</title>
    <style>
        :root {
            --bg: #f6f2ea;
            --card: #ffffff;
            --text: #1c2921;
            --muted: #5f6f66;
            --line: #d6ddd8;
            --accent: #2f6a56;
            --danger: #c0392b;
        }
        * { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; background: var(--bg); color: var(--text); font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; }
        .wrap { max-width: 760px; margin: 0 auto; min-height: 100dvh; padding: env(safe-area-inset-top) 12px calc(78px + env(safe-area-inset-bottom)); }
        .top {
            position: sticky;
            top: 0;
            z-index: 20;
            background: linear-gradient(to bottom, rgba(246,242,234,0.98), rgba(246,242,234,0.9));
            backdrop-filter: blur(8px);
            padding: 10px 2px;
        }
        .top-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
        }
        h1 { margin: 0; font-size: 20px; }
        .logout-btn {
            border: 1px solid rgba(192,57,43,0.45);
            color: #c0392b;
            background: rgba(192,57,43,0.06);
            border-radius: 10px;
            padding: 7px 10px;
            font-size: 13px;
            font-weight: 700;
        }
        .sub { margin: 4px 0 0; color: var(--muted); font-size: 13px; }
        .card { background: var(--card); border: 1px solid var(--line); border-radius: 14px; padding: 12px; box-shadow: 0 8px 20px rgba(0,0,0,0.04); }
        .stack { display: grid; gap: 10px; }
        .row { display: grid; grid-template-columns: 1fr auto; gap: 8px; align-items: center; }
        .add-meta-row {
            display: grid;
            grid-template-columns: 0.9fr 1fr 1.2fr;
            gap: 8px;
        }
        .store-picker {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 6px;
        }
        .store-btn {
            border-radius: 10px;
            border: 1px solid var(--line);
            background: #fff;
            font-weight: 800;
            letter-spacing: 0.2px;
            padding: 10px 0;
            transition: transform 0.12s ease, box-shadow 0.12s ease;
        }
        .store-btn:active {
            transform: scale(0.98);
        }
        .store-btn[data-store="Netto"] {
            background: linear-gradient(135deg, #ffd54f 0%, #ffca28 100%);
            color: #332200;
            border-color: rgba(212,136,6,0.55);
        }
        .store-btn[data-store="Kvickly"] {
            background: linear-gradient(135deg, #ef5350 0%, #d32f2f 100%);
            color: #fff;
            border-color: rgba(163,28,28,0.75);
        }
        .store-btn[data-store="365discount"] {
            background: linear-gradient(135deg, #43a047 0%, #2e7d32 100%);
            color: #fff;
            border-color: rgba(31,106,35,0.75);
        }
        .store-btn.selected {
            box-shadow: 0 0 0 2px #fff, 0 0 0 4px rgba(47,106,86,0.5);
            transform: translateY(-1px);
        }
        input, button {
            border-radius: 12px;
            border: 1px solid var(--line);
            font-size: 16px;
            padding: 10px 12px;
            font-family: inherit;
        }
        input { width: 100%; background: #fff; }
        button { background: #fff; color: var(--text); }
        .btn-primary { border-color: var(--accent); color: var(--accent); font-weight: 700; }
        .status { font-size: 13px; color: var(--muted); min-height: 18px; }
        .status.err { color: var(--danger); }
        .suggestions {
            border: 1px solid var(--line);
            border-radius: 12px;
            background: #fff;
            display: none;
            overflow: hidden;
        }
        .sg-item {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 10px;
            align-items: center;
            padding: 9px 10px;
            border-bottom: 1px solid var(--line);
        }
        .sg-item:last-child { border-bottom: 0; }
        .sg-name { font-size: 14px; font-weight: 600; }
        .sg-meta { font-size: 12px; color: var(--muted); }
        .sg-add { font-size: 13px; color: var(--accent); border-color: var(--accent); padding: 6px 10px; }
        .list { list-style: none; margin: 0; padding: 0; display: grid; gap: 8px; }
        .item-shell {
            position: relative;
            border-radius: 12px;
            overflow: hidden;
        }
        .item-card {
            display: grid;
            grid-template-columns: 1fr;
            gap: 10px;
            align-items: center;
            border: 1px solid var(--line);
            border-radius: 12px;
            padding: 10px;
            background: #fff;
            transition: transform 0.18s ease;
            position: relative;
            z-index: 2;
            touch-action: pan-y;
        }
        .item-shell.checked .item-card {
            background: #f5f6f5;
            border-color: #d8dfdb;
        }
        .item-shell.checked .name,
        .item-shell.checked .meta,
        .item-shell.checked .price {
            color: #8a938e;
        }
        .item-shell.checked .name {
            text-decoration: line-through;
            text-decoration-thickness: 1.5px;
        }
        .item-main {
            cursor: pointer;
            user-select: none;
        }
        .item-shell.swiped .item-card {
            transform: translateX(-92px);
        }
        .swipe-delete {
            position: absolute;
            right: 0;
            top: 0;
            bottom: 0;
            width: 92px;
            border: 1px solid rgba(192,57,43,0.45);
            border-left: none;
            border-radius: 0;
            color: var(--danger);
            background: rgba(192,57,43,0.1);
            font-weight: 700;
            z-index: 1;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .name { margin: 0; font-size: 15px; font-weight: 700; }
        .meta { margin: 2px 0 0; font-size: 12px; color: var(--muted); }
        .price { font-size: 12px; color: var(--accent); font-weight: 700; }
        .empty { color: var(--muted); font-size: 14px; text-align: center; padding: 20px 8px; }

        .bottom-nav {
            position: fixed;
            left: 0;
            right: 0;
            bottom: 0;
            z-index: 25;
            padding: 8px 12px calc(8px + env(safe-area-inset-bottom));
            background: rgba(246,242,234,0.95);
            backdrop-filter: blur(8px);
            border-top: 1px solid var(--line);
        }
        .bottom-nav .inner {
            max-width: 760px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
        }
        .nav-btn {
            text-decoration: none;
            text-align: center;
            border-radius: 12px;
            border: 1px solid var(--line);
            color: var(--text);
            padding: 10px;
            font-weight: 700;
            background: #fff;
        }
        .nav-btn.active { border-color: var(--accent); color: var(--accent); background: rgba(47,106,86,0.08); }

        .auth-gate {
            position: fixed;
            inset: 0;
            z-index: 60;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 14px;
            background: rgba(20,35,29,0.45);
            backdrop-filter: blur(5px);
        }
        .auth-gate.hidden { display: none; }
        .auth-card { max-width: 420px; width: 100%; background: #fff; border: 1px solid var(--line); border-radius: 16px; padding: 14px; }
        .auth-card h2 { margin: 0 0 6px; font-size: 18px; }
        .auth-card p { margin: 0 0 10px; color: var(--muted); font-size: 13px; }
        .auth-grid { display: grid; gap: 8px; }
        .auth-grid input {
            text-align: center;
        }
    </style>
</head>
<body>
<div id="authGate" class="auth-gate">
    <div class="auth-card">
        <h2>Log ind</h2>
        <p>Skriv initialer, modtag SMS-kode, og indtast den for adgang.</p>
        <div class="auth-grid">
            <input id="gateInitials" type="text" maxlength="6" placeholder="Initialer" autocapitalize="characters" autocomplete="username">
            <input id="gateCode" type="text" maxlength="6" inputmode="numeric" placeholder="SMS kode (6 cifre)" autocomplete="one-time-code">
            <button id="gateVerify" class="btn-primary" type="button">Log ind</button>
            <div id="gateStatus" class="status">2FA påkrævet.</div>
        </div>
    </div>
</div>

<main id="app" class="wrap" aria-hidden="true" inert>
    <header class="top">
        <div class="top-head">
            <h1>Mobil indkøbsseddel</h1>
            <button id="logoutBtn" class="logout-btn" type="button">Log ud</button>
        </div>
        <p class="sub" id="who">Henter session...</p>
    </header>

    <section class="card stack" aria-label="Tilføj vare">
        <div class="row">
            <input id="addInput" type="text" placeholder="Skriv vare (fx Faxe Kondi) eller vælg lagerforslag">
            <button id="addBtn" class="btn-primary" type="button">Tilføj</button>
        </div>
        <div class="add-meta-row">
            <input id="addQty" type="number" min="1" step="1" value="1" placeholder="Antal">
            <input id="addPrice" type="text" inputmode="decimal" placeholder="Pris (kr)">
            <div id="storePicker" class="store-picker" aria-label="Vælg butik">
                <button class="store-btn" type="button" data-store="Netto">N</button>
                <button class="store-btn" type="button" data-store="Kvickly">K</button>
                <button class="store-btn" type="button" data-store="365discount">365</button>
            </div>
        </div>
        <div id="suggestions" class="suggestions"></div>
        <div id="addStatus" class="status"></div>
    </section>

    <section class="card" style="margin-top:10px" aria-label="Indkøbsliste">
        <ul id="list" class="list"></ul>
        <div id="empty" class="empty" style="display:none">Ingen varer på indkøbssedlen endnu.</div>
    </section>
</main>

<nav class="bottom-nav" aria-label="Mobil navigation">
    <div class="inner">
        <a class="nav-btn active" href="mobile-shopping.php">Indkøb</a>
        <a class="nav-btn" href="mobile-lager.php">Lager</a>
    </div>
</nav>

<script>
const params = new URLSearchParams(window.location.search);
const queryAccessToken = params.get('access_token') || '';
if (queryAccessToken) {
    window.localStorage.setItem('madAccessToken', queryAccessToken);
}
let accessToken = queryAccessToken || window.localStorage.getItem('madAccessToken') || '';
let householdId = Number(params.get('household_id') || 0) || 0;
let challengeId = '';
let gateLastRequestedInitials = '';
let gateLastRequestedAt = 0;
let gateRequestInFlight = false;
let gateAutoVerifyInFlight = false;
let gateLastAutoVerifyCode = '';
let selectedAddStore = '';
let inventoryProducts = [];
let shoppingItems = [];

function authHeaders() {
    const headers = {'Content-Type': 'application/json'};
    if (accessToken) {
        headers.Authorization = 'Bearer ' + accessToken;
    }
    return headers;
}

function esc(value) {
    return String(value || '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

function setStatus(elId, text, isErr = false) {
    const el = document.getElementById(elId);
    if (!el) return;
    el.textContent = text;
    el.classList.toggle('err', !!isErr);
}

async function apiGet(url) {
    const res = await fetch(url, {headers: authHeaders()});
    if (!res.ok) {
        let msg = 'HTTP ' + res.status;
        try {
            const data = await res.json();
            if (data && data.error) msg = String(data.error);
            if (data && data.message) msg = String(data.message);
        } catch (_) {}
        throw new Error(msg);
    }
    return await res.json();
}

async function apiPost(url, payload) {
    const res = await fetch(url, {
        method: 'POST',
        headers: authHeaders(),
        body: JSON.stringify(payload || {}),
    });
    if (!res.ok) {
        let msg = 'HTTP ' + res.status;
        try {
            const data = await res.json();
            if (data && data.error) msg = String(data.error);
            if (data && data.message) msg = String(data.message);
        } catch (_) {}
        throw new Error(msg);
    }
    return await res.json();
}

function lockApp() {
    document.getElementById('authGate')?.classList.remove('hidden');
    const app = document.getElementById('app');
    if (app) {
        app.setAttribute('aria-hidden', 'true');
        app.setAttribute('inert', '');
    }
}

function logoutApp() {
    accessToken = '';
    challengeId = '';
    gateLastRequestedInitials = '';
    gateLastRequestedAt = 0;
    window.localStorage.removeItem('madAccessToken');
    const codeInput = document.getElementById('gateCode');
    const initialsInput = document.getElementById('gateInitials');
    if (codeInput instanceof HTMLInputElement) {
        codeInput.value = '';
    }
    if (initialsInput instanceof HTMLInputElement) {
        initialsInput.value = '';
        initialsInput.focus();
    }
    const who = document.getElementById('who');
    if (who) {
        who.textContent = 'Ikke logget ind';
    }
    setStatus('gateStatus', 'Logget ud. Skriv initialer for ny kode.');
    lockApp();
}

function unlockApp() {
    document.getElementById('authGate')?.classList.add('hidden');
    const app = document.getElementById('app');
    if (app) {
        app.setAttribute('aria-hidden', 'false');
        app.removeAttribute('inert');
    }
}

function normalizeSearchText(v) {
    return String(v || '').toLowerCase().trim().replace(/\s+/g, ' ');
}

function resolveDisplayName(item) {
    const raw = String(item?.product_name || 'Ukendt vare');
    const brand = String(item?.brand || '').trim();
    if (!brand) return raw;
    const normWords = (s) => s
        .replace(/([a-z])([A-Z])/g, '$1 $2')
        .toLowerCase()
        .replace(/[^a-z0-9\s]/g, ' ')
        .split(/\s+/)
        .filter(Boolean);
    const brandWords = normWords(brand);
    const nameWords = raw.split(/\s+/);
    let consumed = 0;
    let bi = 0;
    for (let ni = 0; ni < nameWords.length && bi < brandWords.length; ni++) {
        const nw = normWords(nameWords[ni]);
        if (nw.length === 1 && nw[0] === brandWords[bi]) {
            consumed = ni + 1;
            bi++;
        } else if (nw.join('') === brandWords.slice(bi, bi + nw.length).join('')) {
            consumed = ni + 1;
            bi += nw.length;
        } else {
            break;
        }
    }
    let stripped = nameWords.slice(consumed).join(' ').trim();
    stripped = stripped.replace(/\s*\d+\s*(?:g|ml|l|cl|dl|kg|oz)\s*$/i, '').trim();
    return stripped || raw;
}

function renderList(items) {
    const listEl = document.getElementById('list');
    const empty = document.getElementById('empty');
    if (!listEl || !empty) return;

    const rows = Array.isArray(items) ? items : [];
    if (!rows.length) {
        listEl.innerHTML = '';
        empty.style.display = '';
        return;
    }

    empty.style.display = 'none';
    listEl.innerHTML = rows.map((item) => {
        const checked = !!item?.is_checked;
        const id = Number(item?.id || 0);
        const displayName = resolveDisplayName(item);
        const store = String(item?.preferred_store || '').trim();
        const quantity = Math.max(1, Number(item?.quantity || 1));
        const hasPrice = item?.offer_price !== null && item?.offer_price !== undefined && !Number.isNaN(Number(item.offer_price));
        const price = hasPrice ? Number(item.offer_price).toFixed(2).replace('.', ',') + ' kr' : '-';
        const meta = `Antal: ${Number.isInteger(quantity) ? quantity : quantity.toString().replace('.', ',')} · Pris: ${price} · Butik: ${store || '-'}`;
        return `<li class="item-shell${checked ? ' checked' : ''}" data-item-id="${id}">
            <button class="swipe-delete" data-action="remove" data-id="${id}">Slet</button>
            <div class="item-card item-main" data-action="toggle" data-id="${id}" data-next="${checked ? '0' : '1'}" role="button" tabindex="0" aria-label="Marker ${esc(displayName)} som købt">
                <p class="name">${esc(displayName)}</p>
                <p class="meta">${esc(meta)}</p>
            </div>
        </li>`;
    }).join('');

    bindShoppingSwipeRows();
}

function closeSwipedRows(exceptItemId = 0) {
    document.querySelectorAll('#list .item-shell.swiped').forEach((row) => {
        const rowId = Number(row.getAttribute('data-item-id') || 0);
        if (!exceptItemId || rowId !== exceptItemId) {
            row.classList.remove('swiped');
        }
    });
}

function bindShoppingSwipeRows() {
    const list = document.getElementById('list');
    if (!list) return;

    list.querySelectorAll('.item-shell').forEach((row) => {
        if (row.dataset.swipeBound === '1') return;
        row.dataset.swipeBound = '1';

        const track = row.querySelector('.item-card');
        if (!(track instanceof HTMLElement)) return;

        let startX = 0;
        let startY = 0;
        let deltaX = 0;
        let isHorizontal = false;

        track.addEventListener('touchstart', (event) => {
            const touch = event.touches[0];
            if (!touch) return;
            startX = touch.clientX;
            startY = touch.clientY;
            deltaX = 0;
            isHorizontal = false;
            closeSwipedRows(Number(row.getAttribute('data-item-id') || 0));
        }, {passive: true});

        track.addEventListener('touchmove', (event) => {
            const touch = event.touches[0];
            if (!touch) return;
            const moveX = touch.clientX - startX;
            const moveY = touch.clientY - startY;
            if (!isHorizontal) {
                isHorizontal = Math.abs(moveX) > Math.abs(moveY) && Math.abs(moveX) > 10;
            }
            if (isHorizontal) {
                deltaX = moveX;
            }
        }, {passive: true});

        track.addEventListener('touchend', () => {
            if (!isHorizontal) return;
            if (deltaX <= -45) {
                row.classList.add('swiped');
            } else if (deltaX >= 30) {
                row.classList.remove('swiped');
            }
        });
    });
}

function renderSuggestions(rawQuery) {
    const el = document.getElementById('suggestions');
    if (!el) return;
    const query = normalizeSearchText(rawQuery);
    if (!query) {
        el.style.display = 'none';
        el.innerHTML = '';
        return;
    }

    const ranked = inventoryProducts
        .map((p) => {
            const name = String(p?.name || '').trim();
            if (!name) return null;
            const normName = normalizeSearchText(name);
            const normBrand = normalizeSearchText(p?.brand || '');
            let score = 0;
            if (normName.startsWith(query)) score += 30;
            if (normName.includes(query)) score += 20;
            if (normBrand.includes(query)) score += 10;
            if (score <= 0) return null;
            return {p, score};
        })
        .filter(Boolean)
        .sort((a, b) => b.score - a.score)
        .slice(0, 7);

    if (!ranked.length) {
        el.style.display = 'none';
        el.innerHTML = '';
        return;
    }

    el.innerHTML = ranked.map(({p}) => {
        const id = Number(p?.id || 0);
        const name = String(p?.name || 'Ukendt vare');
        const brand = String(p?.brand || '').trim();
        const store = String(p?.offer_store || p?.standard_store || '').trim();
        const meta = [brand, store].filter(Boolean).join(' · ') || 'Fra lager';
        return `<div class="sg-item" data-action="add-sg" data-id="${id}" data-name="${esc(name)}" data-store="${esc(store)}">
            <div>
                <div class="sg-name">${esc(name)}</div>
                <div class="sg-meta">${esc(meta)}</div>
            </div>
            <button class="sg-add" type="button" data-action="add-sg" data-id="${id}" data-name="${esc(name)}" data-store="${esc(store)}">Tilføj</button>
        </div>`;
    }).join('');
    el.style.display = '';
}

async function addItemByText(name) {
    const cleaned = String(name || '').trim();
    if (!cleaned) return;
    const qtyValue = Number(document.getElementById('addQty')?.value || 1);
    const quantity = Number.isFinite(qtyValue) && qtyValue > 0 ? Math.max(1, Math.round(qtyValue)) : 1;
    const priceRaw = String(document.getElementById('addPrice')?.value || '').trim();
    const store = String(selectedAddStore || '').trim();
    const priceNormalized = priceRaw !== '' ? priceRaw.replace(',', '.') : '';
    const price = priceNormalized !== '' ? Number(priceNormalized) : null;
    if (priceRaw !== '' && (price === null || Number.isNaN(price) || price < 0)) {
        throw new Error('Pris skal være et gyldigt tal');
    }

    const addItem = {
        title: cleaned,
        quantity,
    };
    if (store !== '') {
        addItem.store = store;
    }
    if (price !== null) {
        addItem.price = price;
    }

    await apiPost(`api.php?endpoint=shopping.list.add_items&household_id=${encodeURIComponent(householdId || 1)}`, {
        items: [addItem],
    });
}

async function addItemFromInventory(productId, name, store) {
    const qtyValue = Number(document.getElementById('addQty')?.value || 1);
    const quantity = Number.isFinite(qtyValue) && qtyValue > 0 ? Math.max(1, Math.round(qtyValue)) : 1;
    const priceRaw = String(document.getElementById('addPrice')?.value || '').trim();
    const priceNormalized = priceRaw !== '' ? priceRaw.replace(',', '.') : '';
    const price = priceNormalized !== '' ? Number(priceNormalized) : null;
    const normalizeStoreName = (raw) => {
        const text = String(raw || '').trim().toLowerCase();
        if (!text) return '';
        if (text.includes('netto')) return 'Netto';
        if (text.includes('kvickly')) return 'Kvickly';
        if (text.includes('365')) return '365discount';
        return '';
    };
    const preferredStore = String(selectedAddStore || normalizeStoreName(store) || '').trim();
    if (priceRaw !== '' && (price === null || Number.isNaN(price) || price < 0)) {
        throw new Error('Pris skal være et gyldigt tal');
    }

    const addItem = {
        productId: Number(productId || 0),
        title: String(name || ''),
        quantity,
    };
    if (preferredStore !== '') {
        addItem.store = preferredStore;
    }
    if (price !== null) {
        addItem.price = price;
    }

    await apiPost(`api.php?endpoint=shopping.list.add_items&household_id=${encodeURIComponent(householdId || 1)}`, {
        items: [addItem],
    });
}

async function refreshShopping() {
    const payload = await apiGet(`api.php?endpoint=shopping.list&household_id=${encodeURIComponent(householdId || 1)}`);
    shoppingItems = Array.isArray(payload?.items) ? payload.items : [];
    renderList(shoppingItems);
}

async function refreshInventoryCache() {
    const payload = await apiGet(`api.php?endpoint=products&household_id=${encodeURIComponent(householdId || 1)}`);
    inventoryProducts = Array.isArray(payload?.products) ? payload.products : [];
}

async function requestCode(force = false) {
    const initialsEl = document.getElementById('gateInitials');
    const codeEl = document.getElementById('gateCode');
    const initials = String(initialsEl?.value || '').trim().toUpperCase();
    if (initialsEl) {
        initialsEl.value = initials;
    }
    if (!initials) {
        setStatus('gateStatus', 'Skriv initialer.', true);
        return;
    }
    if (gateRequestInFlight) {
        return;
    }
    const now = Date.now();
    const isRecentSameInitials = initials === gateLastRequestedInitials && (now - gateLastRequestedAt) < 30000;
    if (!force && isRecentSameInitials) {
        return;
    }
    setStatus('gateStatus', 'Sender SMS-kode...');
    gateRequestInFlight = true;
    try {
        const payload = await apiPost('api.php?endpoint=auth.request_code', {initials});
        challengeId = String(payload?.challenge_id || '');
        gateLastRequestedInitials = initials;
        gateLastRequestedAt = Date.now();
        setStatus('gateStatus', 'SMS-kode sendt. Indtast koden.');
        if (codeEl instanceof HTMLInputElement) {
            codeEl.focus();
            codeEl.select();
        }
    } finally {
        gateRequestInFlight = false;
    }
}

async function verifyCode() {
    const verifyBtn = document.getElementById('gateVerify');
    const code = String(document.getElementById('gateCode')?.value || '').trim();
    if (!challengeId || !code) {
        setStatus('gateStatus', 'Mangler challenge eller kode.', true);
        return;
    }
    setStatus('gateStatus', 'Logger ind...');
    let payload;
    try {
        if (verifyBtn instanceof HTMLButtonElement) {
            verifyBtn.disabled = true;
        }
        payload = await apiPost('api.php?endpoint=auth.verify_code', {challenge_id: challengeId, code});
    } finally {
        if (verifyBtn instanceof HTMLButtonElement) {
            verifyBtn.disabled = false;
        }
    }
    accessToken = String(payload?.access_token || '');
    if (!accessToken) {
        throw new Error('Mangler adgangstoken');
    }
    window.localStorage.setItem('madAccessToken', accessToken);
    const targetHousehold = Number(payload?.active_household_id || 0);
    if (targetHousehold > 0 && (!householdId || householdId <= 0)) {
        householdId = targetHousehold;
    }
    await bootstrap();
}

async function resolveSession() {
    if (!accessToken) {
        throw new Error('NO_TOKEN');
    }
    const me = await apiGet('api.php?endpoint=auth.me');
    const households = Array.isArray(me?.households) ? me.households : [];
    if (!householdId || householdId <= 0) {
        const preferred = Number(me?.active_household_id || 0);
        householdId = preferred > 0 ? preferred : Number(households[0]?.id || 1);
    }
    const fullName = String(me?.user?.full_name || me?.user?.initials || 'Bruger');
    const hhName = String((households.find(h => Number(h?.id) === Number(householdId)) || {}).name || ('Husstand ' + householdId));
    const who = document.getElementById('who');
    if (who) {
        who.textContent = fullName + ' · ' + hhName;
    }
}

async function bootstrap() {
    try {
        await resolveSession();
        await Promise.all([refreshShopping(), refreshInventoryCache()]);
        unlockApp();
    } catch (e) {
        lockApp();
        if (String(e?.message || '') !== 'NO_TOKEN') {
            setStatus('gateStatus', 'Kunne ikke logge ind: ' + String(e?.message || e), true);
        }
    }
}

document.getElementById('gateVerify')?.addEventListener('click', async () => {
    try {
        await verifyCode();
    } catch (e) {
        setStatus('gateStatus', 'Fejl ved login: ' + String(e?.message || e), true);
    }
});

const gateInitialsInput = document.getElementById('gateInitials');
const gateCodeInput = document.getElementById('gateCode');
const storePicker = document.getElementById('storePicker');

function setSelectedStore(value) {
    selectedAddStore = String(value || '').trim();
    storePicker?.querySelectorAll('.store-btn').forEach((btn) => {
        if (!(btn instanceof HTMLElement)) return;
        btn.classList.toggle('selected', btn.dataset.store === selectedAddStore);
    });
}

storePicker?.addEventListener('click', (event) => {
    const target = event.target;
    if (!(target instanceof HTMLElement)) return;
    const button = target.closest('.store-btn');
    if (!(button instanceof HTMLElement)) return;
    const value = String(button.dataset.store || '').trim();
    if (!value) return;
    if (selectedAddStore === value) {
        setSelectedStore('');
        return;
    }
    setSelectedStore(value);
});

gateInitialsInput?.addEventListener('input', () => {
    gateInitialsInput.value = gateInitialsInput.value.toUpperCase();
});

gateInitialsInput?.addEventListener('keydown', async (event) => {
    if (event.key !== 'Enter') return;
    event.preventDefault();
    try {
        await requestCode(true);
    } catch (e) {
        setStatus('gateStatus', 'Fejl ved SMS: ' + String(e?.message || e), true);
    }
});

gateCodeInput?.addEventListener('focus', async () => {
    try {
        await requestCode(false);
    } catch (e) {
        setStatus('gateStatus', 'Fejl ved SMS: ' + String(e?.message || e), true);
    }
});

gateCodeInput?.addEventListener('click', async () => {
    try {
        await requestCode(false);
    } catch (e) {
        setStatus('gateStatus', 'Fejl ved SMS: ' + String(e?.message || e), true);
    }
});

gateCodeInput?.addEventListener('keydown', async (event) => {
    if (event.key !== 'Enter') return;
    event.preventDefault();
    try {
        await verifyCode();
    } catch (e) {
        setStatus('gateStatus', 'Fejl ved login: ' + String(e?.message || e), true);
    }
});

gateCodeInput?.addEventListener('input', async () => {
    const raw = String(gateCodeInput.value || '');
    const digits = raw.replace(/\D+/g, '').slice(0, 6);
    gateCodeInput.value = digits;
    if (digits.length !== 6 || !challengeId) {
        return;
    }
    if (gateAutoVerifyInFlight || gateLastAutoVerifyCode === digits) {
        return;
    }

    gateAutoVerifyInFlight = true;
    gateLastAutoVerifyCode = digits;
    try {
        await verifyCode();
    } catch (e) {
        setStatus('gateStatus', 'Fejl ved login: ' + String(e?.message || e), true);
    } finally {
        gateAutoVerifyInFlight = false;
    }
});

document.getElementById('logoutBtn')?.addEventListener('click', () => {
    logoutApp();
});

const addInput = document.getElementById('addInput');
const addBtn = document.getElementById('addBtn');
const suggestions = document.getElementById('suggestions');

addBtn?.addEventListener('click', async () => {
    if (!addInput) return;
    const name = addInput.value.trim();
    if (!name) return;
    addBtn.disabled = true;
    try {
        await addItemByText(name);
        addInput.value = '';
        if (suggestions) {
            suggestions.style.display = 'none';
            suggestions.innerHTML = '';
        }
        setStatus('addStatus', 'Vare tilføjet.');
        await refreshShopping();
    } catch (e) {
        setStatus('addStatus', 'Kunne ikke tilføje: ' + String(e?.message || e), true);
    } finally {
        addBtn.disabled = false;
        addInput.focus();
    }
});

addInput?.addEventListener('input', () => {
    renderSuggestions(addInput.value || '');
});

addInput?.addEventListener('keydown', async (e) => {
    if (e.key !== 'Enter') return;
    e.preventDefault();
    if (!addBtn || !addInput) return;
    const name = addInput.value.trim();
    if (!name) return;
    addBtn.click();
});

addInput?.addEventListener('blur', () => {
    setTimeout(() => {
        if (suggestions) suggestions.style.display = 'none';
    }, 160);
});

suggestions?.addEventListener('click', async (event) => {
    const t = event.target;
    if (!(t instanceof HTMLElement)) return;
    const row = t.closest('[data-action="add-sg"]');
    if (!(row instanceof HTMLElement)) return;

    const productId = Number(row.dataset.id || 0);
    const name = String(row.dataset.name || '').trim();
    const store = String(row.dataset.store || '').trim();
    if (!name) return;

    try {
        await addItemFromInventory(productId, name, store);
        if (addInput) addInput.value = '';
        if (suggestions) {
            suggestions.style.display = 'none';
            suggestions.innerHTML = '';
        }
        setStatus('addStatus', 'Vare tilføjet fra lager.');
        await refreshShopping();
    } catch (e) {
        setStatus('addStatus', 'Kunne ikke tilføje fra lager: ' + String(e?.message || e), true);
    }
});

document.getElementById('list')?.addEventListener('click', async (event) => {
    const target = event.target;
    if (!(target instanceof HTMLElement)) return;
    const actionEl = target.closest('[data-action]');
    if (!(actionEl instanceof HTMLElement)) return;
    const action = actionEl.dataset.action || '';
    const id = Number(actionEl.dataset.id || 0);
    if (!id) return;

    const row = actionEl.closest('.item-shell');
    if (action === 'toggle' && row instanceof HTMLElement && row.classList.contains('swiped')) {
        row.classList.remove('swiped');
        return;
    }

    try {
        if (action === 'toggle') {
            const next = actionEl.dataset.next === '1';
            await apiPost(`api.php?endpoint=shopping.list.set_item_checked&household_id=${encodeURIComponent(householdId || 1)}`, {
                item_id: id,
                is_checked: next,
            });
            await refreshShopping();
            return;
        }
        if (action === 'remove') {
            await apiPost(`api.php?endpoint=shopping.list.remove_item&household_id=${encodeURIComponent(householdId || 1)}`, {
                item_id: id,
            });
            await refreshShopping();
        }
    } catch (e) {
        setStatus('addStatus', 'Handling fejlede: ' + String(e?.message || e), true);
    }
});

document.getElementById('list')?.addEventListener('keydown', (event) => {
    const target = event.target;
    if (!(target instanceof HTMLElement)) return;
    if (target.dataset.action !== 'toggle') return;
    if (event.key !== 'Enter' && event.key !== ' ') return;
    event.preventDefault();
    target.click();
});

bootstrap();
</script>
</body>
</html>
