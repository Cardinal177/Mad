<?php
declare(strict_types=1);
?><!doctype html>
<html lang="da">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>Mad - Mobil Lager</title>
    <style>
        :root {
            --bg: #f4f1e8;
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
            background: linear-gradient(to bottom, rgba(244,241,232,0.98), rgba(244,241,232,0.9));
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
        .status { font-size: 13px; color: var(--muted); min-height: 18px; }
        .status.err { color: var(--danger); }
        .controls { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
        .seg { display: grid; grid-template-columns: 1fr 1fr; gap: 6px; }
        button, select, input {
            border-radius: 12px;
            border: 1px solid var(--line);
            font-size: 16px;
            padding: 10px 12px;
            font-family: inherit;
            background: #fff;
            color: var(--text);
        }
        .mode.active { border-color: var(--accent); color: var(--accent); font-weight: 700; background: rgba(47,106,86,0.08); }
        .camera {
            position: relative;
            overflow: hidden;
            border-radius: 12px;
            border: 1px solid var(--line);
            background: #111;
            aspect-ratio: 16 / 9;
            max-height: 200px;
        }
        video { width: 100%; height: 100%; object-fit: cover; display: block; }
        .row { display: grid; grid-template-columns: 1fr auto; gap: 8px; align-items: center; }
        .btn-primary { border-color: var(--accent); color: var(--accent); font-weight: 700; }

        #scanStatus {
            font-size: 15px;
            font-weight: 600;
            min-height: 22px;
            transition: background 0.2s;
            border-radius: 8px;
            padding: 6px 10px;
        }
        #scanStatus.flash-ok {
            background: rgba(47,106,86,0.15);
            color: var(--accent);
        }
        #scanStatus.flash-err {
            background: rgba(192,57,43,0.12);
            color: var(--danger);
        }
        .item.highlight {
            border-color: var(--accent);
            background: rgba(47,106,86,0.07);
            box-shadow: 0 0 0 3px rgba(47,106,86,0.18);
        }
        .list { list-style: none; margin: 0; padding: 0; display: grid; gap: 8px; }
        .item {
            border: 2px solid #d8cbb4;
            border-radius: 12px;
            padding: 0;
            background: #fff;
            position: relative;
            overflow: hidden;
            -webkit-user-select: none;
            user-select: none;
            -webkit-touch-callout: none;
            touch-action: manipulation;
            box-shadow: 0 1px 0 rgba(28,41,33,0.04);
        }
        .item-main {
            padding: 10px;
            background: #f3ebdd;
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 8px;
            align-items: center;
            transition: transform 0.18s ease;
            will-change: transform;
        }
        .swipe-delete-wrap {
            position: absolute;
            top: 0;
            right: 0;
            bottom: 0;
            width: 92px;
            display: flex;
            justify-content: flex-end;
            align-items: stretch;
            background: #c0392b;
        }
        .swipe-delete-btn {
            width: 92px;
            border: 0;
            color: #fff;
            background: transparent;
            font-weight: 700;
            font-size: 14px;
        }
        .item.swiped .item-main {
            transform: translateX(-92px);
        }
        .item.edit-press .item-main {
            filter: brightness(0.98);
            border-color: var(--accent);
        }
        .name { margin: 0; font-size: 15px; font-weight: 700; }
        .meta { margin: 2px 0 0; font-size: 12px; color: var(--muted); }
        .qty { font-size: 14px; font-weight: 700; color: var(--accent); }
        .basis-chip {
            margin-top: 4px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 12px;
            color: var(--muted);
        }
        .basis-chip input {
            width: 16px;
            height: 16px;
        }
        .basis-pill {
            margin-top: 4px;
            display: inline-flex;
            align-items: center;
            border-radius: 999px;
            border: 1px solid rgba(47,106,86,0.35);
            background: rgba(47,106,86,0.10);
            color: var(--accent);
            padding: 2px 8px;
            font-size: 11px;
            font-weight: 700;
        }

        .modal {
            position: fixed;
            inset: 0;
            z-index: 80;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 14px;
            background: rgba(14, 26, 20, 0.45);
            backdrop-filter: blur(4px);
        }
        .modal.show { display: flex; }
        .modal-card {
            width: 100%;
            max-width: 430px;
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 14px;
            padding: 12px;
            display: grid;
            gap: 8px;
        }
        .modal-title { margin: 0; font-size: 18px; }
        .modal-actions {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
            margin-top: 4px;
        }
        .btn-danger {
            border-color: rgba(192,57,43,0.45);
            color: #c0392b;
            background: rgba(192,57,43,0.06);
            font-weight: 700;
        }
        .modal button {
            -webkit-user-select: none;
            user-select: none;
            -webkit-touch-callout: none;
        }

        .bottom-nav {
            position: fixed;
            left: 0;
            right: 0;
            bottom: 0;
            z-index: 25;
            padding: 8px 12px calc(8px + env(safe-area-inset-bottom));
            background: rgba(244,241,232,0.95);
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
<script>
// Pre-hide gate immediately if token exists — prevents login flash on tab switch
(function(){
    try {
        if (window.localStorage.getItem('madAccessToken')) {
            document.write('<style>#authGate{display:none!important}#app{aria-hidden:false}</style>');
        }
    } catch(_){}
})();
</script>
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
            <h1>Mobil lager</h1>
            <button id="logoutBtn" class="logout-btn" type="button">Log ud</button>
        </div>
        <p class="sub" id="who">Henter session...</p>
    </header>

    <section class="card stack" aria-label="Scanning">
        <div class="camera">
            <video id="video" playsinline muted></video>
        </div>

        <div class="row">
            <button id="cameraBtn" class="btn-primary" type="button">Start kamera</button>
            <button id="cameraStop" type="button">Stop</button>
        </div>

        <div class="row">
            <input id="manualBarcode" type="text" inputmode="text" autocapitalize="off" autocomplete="off" spellcheck="false" placeholder="Manuel stregkode">
            <button id="manualSend" class="btn-primary" type="button">Scan</button>
        </div>

        <div class="row">
            <button id="createFromScanBtn" type="button">Opret vare</button>
            <div id="createHint" class="status">Brug efter scanning, hvis varen ikke findes.</div>
        </div>

        <div id="scanStatus" class="status">Klar.</div>
    </section>

    <section class="card" style="margin-top:10px" aria-label="Lageroversigt">
        <div class="row" style="margin-bottom:8px">
            <input id="searchInput" type="search" placeholder="Søg på lager…" autocomplete="off">
        </div>
        <div class="status" id="invSummary"></div>
        <ul id="list" class="list"></ul>
    </section>
</main>

<nav class="bottom-nav" aria-label="Mobil navigation">
    <div class="inner">
        <a class="nav-btn" href="mobile-shopping.php">Indkøb</a>
        <a class="nav-btn active" href="mobile-lager.php">Lager</a>
    </div>
</nav>

<div id="editModal" class="modal" aria-hidden="true">
    <div class="modal-card" role="dialog" aria-modal="true" aria-label="Rediger lager vare">
        <h3 class="modal-title">Juster lager</h3>
        <div id="editItemName" class="status"></div>
        <input id="editQty" type="number" min="0" step="0.1" placeholder="Antal">
        <input id="editMinQty" type="number" min="0" step="0.1" placeholder="Minimum">
        <label class="basis-chip" for="editIsBasis">
            <input id="editIsBasis" type="checkbox">
            <span>Basisvare</span>
        </label>
        <div class="modal-actions">
            <button id="editSaveBtn" class="btn-primary" type="button">Gem</button>
            <button id="editCancelBtn" type="button">Annuller</button>
        </div>
        <button id="editDeleteBtn" class="btn-danger" type="button">Slet vare fra lager</button>
    </div>
</div>

<div id="createModal" class="modal" aria-hidden="true">
    <div class="modal-card" role="dialog" aria-modal="true" aria-label="Opret vare">
        <h3 class="modal-title">Opret vare</h3>
        <input id="createBarcode" type="text" inputmode="numeric" placeholder="Stregkode">
        <input id="createName" type="text" placeholder="Varenavn (tom = automatisk opslag)">
        <input id="createQty" type="number" min="0" step="0.1" value="1" placeholder="Antal">
        <input id="createMinQty" type="number" min="0" step="0.1" value="0" placeholder="Minimum">
        <div class="modal-actions">
            <button id="createSaveBtn" class="btn-primary" type="button">Opret</button>
            <button id="createCancelBtn" type="button">Annuller</button>
        </div>
    </div>
</div>

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
let stream = null;
let detector = null;
let rafId = 0;
let scanning = false;
let lastSignature = '';
let lastScanAt = 0;
let pendingCreateBarcode = '';
let pressTimer = 0;
let pressTarget = null;
let editTarget = null;

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

let flashTimer = 0;
function flashStatus(elId, text, isErr = false) {
    const el = document.getElementById(elId);
    if (!el) return;
    el.textContent = text;
    el.classList.toggle('err', !!isErr);
    el.classList.remove('flash-ok', 'flash-err');
    void el.offsetWidth; // force reflow
    el.classList.add(isErr ? 'flash-err' : 'flash-ok');
    clearTimeout(flashTimer);
    flashTimer = setTimeout(() => {
        el.classList.remove('flash-ok', 'flash-err');
        el.textContent = 'Klar.';
    }, 3500);
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
    stopCamera();
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

function resolveProductDisplayName(product) {
    const raw = String(product?.name || 'Ukendt vare');
    const brand = String(product?.brand || '').trim();
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

async function refreshInventory(highlightBc = null) {
    const data = await apiGet(`api.php?endpoint=products&household_id=${encodeURIComponent(householdId || 1)}`);
    const products = Array.isArray(data?.products) ? data.products : [];
    allProducts = products;
    const list = document.getElementById('list');
    const summary = document.getElementById('invSummary');
    if (summary) {
        summary.textContent = products.length + ' varer på lager';
    }
    if (!list) return;

    list.innerHTML = products
        .slice()
        .sort((a, b) => String(a?.name || '').localeCompare(String(b?.name || ''), 'da'))
        .map((p) => {
            const name = resolveProductDisplayName(p);
            const qty = Number(p?.quantity || 0);
            const min = Number(p?.minimum_quantity || 0);
            const isBasis = Number(p?.is_basis || 0) === 1;
            const place = String(p?.location_name || '').trim();
            const meta = [place, 'min ' + min].filter(Boolean).join(' · ');
            const bc = esc(String(p?.barcode || ''));
            const basisPill = isBasis ? '<span class="basis-pill">Basisvare</span>' : '';
            return `<li class="item" data-product-id="${Number(p?.id || 0)}" data-location-id="${Number(p?.location_id || 0)}" data-qty="${qty}" data-min-qty="${min}" data-is-basis="${isBasis ? 1 : 0}" data-barcode="${bc}"><div class="swipe-delete-wrap"><button class="swipe-delete-btn" type="button" aria-label="Slet vare">Slet</button></div><div class="item-main"><div><p class="name">${esc(name)}</p><p class="meta">${esc(meta)}</p>${basisPill}</div>${isBasis ? `<div class="qty">${esc(String(qty))}</div>` : ''}</div></li>`;
        }).join('');

    // Re-apply active search filter after list rebuild
    const searchQ = document.getElementById('searchInput')?.value || '';
    if (searchQ) applySearch(searchQ);

    if (highlightBc) {
        const match = list.querySelector(`[data-barcode="${CSS.escape(String(highlightBc))}"]`);
        if (match) {
            match.classList.add('highlight');
            match.scrollIntoView({behavior: 'smooth', block: 'center'});
            setTimeout(() => match.classList.remove('highlight'), 4000);
        }
    }

    bindInventoryItemGestures();
}

async function postScan(barcode) {
    const code = String(barcode || '').trim();
    if (!code) return;
    const now = Date.now();
    const signature = 'lookup:' + code;
    if (signature === lastSignature && (now - lastScanAt) < 2000) {
        return;
    }
    lastSignature = signature;
    lastScanAt = now;

    highlightBarcode(code);
}

function highlightBarcode(code) {
    const list = document.getElementById('list');
    if (!list) return;

    // Remove previous highlight
    list.querySelectorAll('.item.highlight').forEach(el => el.classList.remove('highlight'));

    // Find matching item by barcode data attribute
    const match = list.querySelector(`[data-barcode="${CSS.escape(code)}"]`);
    if (match) {
        match.classList.add('highlight');
        // scrollIntoView with block:center so item lands in middle of screen
        match.scrollIntoView({behavior: 'smooth', block: 'center'});
        flashStatus('scanStatus', 'Fundet: ' + (match.querySelector('.name')?.textContent || code), false);
        const createHint = document.getElementById('createHint');
        if (createHint) {
            createHint.textContent = 'Brug efter scanning, hvis varen ikke findes.';
        }
        // Remove highlight after 4 seconds
        setTimeout(() => match.classList.remove('highlight'), 4000);
    } else {
        pendingCreateBarcode = code;
        const createHint = document.getElementById('createHint');
        if (createHint) {
            createHint.textContent = 'Varen findes ikke. Tryk "Opret vare" for at oprette ' + code + '.';
        }
        flashStatus('scanStatus', 'Ikke på lager: ' + code, true);
    }
}

function bindInventoryItemGestures() {
    const list = document.getElementById('list');
    if (!list) return;

    const closeAllSwipes = (exceptItem = null) => {
        list.querySelectorAll('.item.swiped').forEach((el) => {
            if (exceptItem && el === exceptItem) return;
            el.classList.remove('swiped');
            const main = el.querySelector('.item-main');
            if (main) {
                main.style.transform = '';
            }
        });
    };

    list.querySelectorAll('.item').forEach((item) => {
        const main = item.querySelector('.item-main');
        const deleteBtn = item.querySelector('.swipe-delete-btn');
        let startX = 0;
        let startY = 0;
        let deltaX = 0;
        let swiping = false;
        let moved = false;

        const start = (e) => {
            if (!e) return;
            if (e.target?.closest?.('.swipe-delete-btn')) {
                return;
            }
            const t = e.touches ? e.touches[0] : null;
            startX = t ? t.clientX : (e.clientX || 0);
            startY = t ? t.clientY : (e.clientY || 0);
            deltaX = 0;
            swiping = false;
            moved = false;

            clearTimeout(pressTimer);
            pressTarget = item;
            item.classList.add('edit-press');
            closeAllSwipes(item);
            pressTimer = window.setTimeout(() => {
                openEditModal(item);
            }, 430);
        };

        const cancel = (e) => {
            if (swiping && e?.cancelable) e.preventDefault();
            clearTimeout(pressTimer);
            item.classList.remove('edit-press');

            if (swiping) {
                if (main) {
                    main.style.transform = '';
                }
                if (deltaX < -46) {
                    item.classList.add('swiped');
                    closeAllSwipes(item);
                } else {
                    item.classList.remove('swiped');
                }
            }
            swiping = false;
        };

        const move = (e) => {
            if (!e) return;
            const t = e.touches ? e.touches[0] : null;
            const x = t ? t.clientX : (e.clientX || 0);
            const y = t ? t.clientY : (e.clientY || 0);
            const dx = x - startX;
            const dy = y - startY;
            deltaX = dx;

            if (Math.abs(dx) > 8 || Math.abs(dy) > 8) {
                moved = true;
                clearTimeout(pressTimer);
                item.classList.remove('edit-press');
            }

            if (Math.abs(dx) > Math.abs(dy) && Math.abs(dx) > 10) {
                swiping = true;
                if (e.cancelable) e.preventDefault();
                const translate = Math.max(-92, Math.min(0, dx));
                if (main) {
                    main.style.transform = `translateX(${translate}px)`;
                }
            }
        };

        item.oncontextmenu = (e) => {
            e.preventDefault();
            openEditModal(item);
            return false;
        };

        if (deleteBtn) {
            deleteBtn.onclick = async (e) => {
                e.preventDefault();
                e.stopPropagation();
                const productId = Number(item.dataset.productId || 0);
                if (!productId) return;
                const ok = window.confirm('Slet vare fra lager?');
                if (!ok) return;
                try {
                    await apiPost('api.php?endpoint=inventory.delete&household_id=' + encodeURIComponent(householdId || 1), {
                        product_id: productId,
                    });
                    flashStatus('scanStatus', 'Vare slettet fra lager.', false);
                    await refreshInventory();
                } catch (err) {
                    flashStatus('scanStatus', 'Kunne ikke slette: ' + String(err?.message || err), true);
                }
            };
        }

        item.ontouchstart = start;
        item.ontouchmove = move;
        item.ontouchend = cancel;
        item.ontouchcancel = cancel;
        item.onmousedown = start;
        item.onmousemove = move;
        item.onmouseup = cancel;
        item.onmouseleave = cancel;

        item.onclick = (e) => {
            if (item.classList.contains('swiped') && !e.target.closest('.swipe-delete-btn')) {
                item.classList.remove('swiped');
            } else if (!moved) {
                closeAllSwipes(item);
            }
        };
    });
}

function openEditModal(item) {
    if (!item) return;
    item.classList.remove('edit-press');
    editTarget = item;
    const name = item.querySelector('.name')?.textContent || 'Vare';
    const qty = Number(item.dataset.qty || 0);
    const minQty = Number(item.dataset.minQty || 0);
    const isBasis = Number(item.dataset.isBasis || 0) === 1;

    const nameEl = document.getElementById('editItemName');
    if (nameEl) nameEl.textContent = name;
    const qtyInput = document.getElementById('editQty');
    const minInput = document.getElementById('editMinQty');
    const basisInput = document.getElementById('editIsBasis');
    if (qtyInput) qtyInput.value = String(qty);
    if (minInput) minInput.value = String(minQty);
    if (basisInput) basisInput.checked = isBasis;

    // Clear accidental text selection from long-press before showing modal.
    try {
        window.getSelection()?.removeAllRanges();
    } catch (_) {}

    const modal = document.getElementById('editModal');
    modal?.classList.add('show');
    qtyInput?.focus();
    qtyInput?.select();
}

function closeEditModal() {
    document.getElementById('editModal')?.classList.remove('show');
    editTarget = null;
}

async function saveEditModal() {
    if (!editTarget) return;
    const productId = Number(editTarget.dataset.productId || 0);
    const locationId = Number(editTarget.dataset.locationId || 0);
    const barcode = String(editTarget.dataset.barcode || '');
    const qty = Number(document.getElementById('editQty')?.value || 0);
    const minQty = Number(document.getElementById('editMinQty')?.value || 0);
    const isBasis = !!document.getElementById('editIsBasis')?.checked;

    await apiPost('api.php?endpoint=inventory.update_item&household_id=' + encodeURIComponent(householdId || 1), {
        product_id: productId,
        location_id: locationId,
        quantity: qty,
        minimum_quantity: minQty,
        is_basis: isBasis,
    });
    closeEditModal();
    await refreshInventory(barcode || null);
    flashStatus('scanStatus', 'Lager opdateret.', false);
}

async function deleteEditTarget() {
    if (!editTarget) return;
    const productId = Number(editTarget.dataset.productId || 0);
    if (!productId) return;
    await apiPost('api.php?endpoint=inventory.delete&household_id=' + encodeURIComponent(householdId || 1), {
        product_id: productId,
    });
    closeEditModal();
    await refreshInventory();
    flashStatus('scanStatus', 'Vare slettet fra lager.', false);
}

function openCreateModal() {
    const inputCode = String(document.getElementById('manualBarcode')?.value || '').trim();
    const barcode = inputCode || pendingCreateBarcode || '';
    const bcInput = document.getElementById('createBarcode');
    if (bcInput) bcInput.value = barcode;
    const qtyInput = document.getElementById('createQty');
    const minInput = document.getElementById('createMinQty');
    if (qtyInput && !qtyInput.value) qtyInput.value = '1';
    if (minInput && !minInput.value) minInput.value = '0';
    document.getElementById('createModal')?.classList.add('show');
}

function closeCreateModal() {
    document.getElementById('createModal')?.classList.remove('show');
}

async function createItemFromModal() {
    const barcode = String(document.getElementById('createBarcode')?.value || '').trim();
    const name = String(document.getElementById('createName')?.value || '').trim();
    const qty = Number(document.getElementById('createQty')?.value || 1);
    const minQty = Number(document.getElementById('createMinQty')?.value || 0);

    if (!barcode) {
        flashStatus('scanStatus', 'Angiv stregkode.', true);
        return;
    }

    const payload = await apiPost('api.php?endpoint=inventory.create_item&household_id=' + encodeURIComponent(householdId || 1), {
        barcode,
        name,
        quantity: qty,
        minimum_quantity: minQty,
    });

    pendingCreateBarcode = '';
    const createHint = document.getElementById('createHint');
    if (createHint) {
        createHint.textContent = 'Brug efter scanning, hvis varen ikke findes.';
    }
    closeCreateModal();
    const resolvedName = String(payload?.product_name || name || barcode);
    flashStatus('scanStatus', 'Oprettet: ' + resolvedName, false);
    await refreshInventory(barcode);
}

let allProducts = [];

function applySearch(query) {
    const q = String(query || '').toLowerCase().trim();
    const list = document.getElementById('list');
    if (!list) return;
    list.querySelectorAll('.item').forEach(li => {
        const name = String(li.querySelector('.name')?.textContent || '').toLowerCase();
        const bc = String(li.dataset.barcode || '').toLowerCase();
        li.style.display = (!q || name.includes(q) || bc.includes(q)) ? '' : 'none';
    });
    const visible = list.querySelectorAll('.item:not([style*="none"])').length;
    const summary = document.getElementById('invSummary');
    if (summary) {
        summary.textContent = q ? `${visible} match` : (allProducts.length + ' varer på lager');
    }
}

async function scanLoop() {
    if (!scanning || !detector || !stream) return;
    const video = document.getElementById('video');
    if (!(video instanceof HTMLVideoElement) || video.readyState < 2) {
        rafId = requestAnimationFrame(scanLoop);
        return;
    }

    try {
        const codes = await detector.detect(video);
        if (Array.isArray(codes) && codes.length) {
            for (const entry of codes) {
                const raw = String(entry?.rawValue || '').trim();
                if (raw.length >= 3) {
                    await postScan(raw);
                    break;
                }
            }
        }
    } catch (_) {}

    rafId = requestAnimationFrame(scanLoop);
}

async function startCamera() {
    const video = document.getElementById('video');
    if (!(video instanceof HTMLVideoElement)) return;

    if (!('BarcodeDetector' in window)) {
        setStatus('scanStatus', 'Indlæser scanner-modul…');
        try {
            await import('https://cdn.jsdelivr.net/npm/barcode-detector@3.1.3/dist/es/polyfill.js');
        } catch (e) {
            setStatus('scanStatus', 'Kunne ikke indlæse scanner: ' + String(e?.message || e), true);
            return;
        }
        if (!('BarcodeDetector' in window)) {
            setStatus('scanStatus', 'Stregkode-scanning ikke understøttet i denne browser.', true);
            return;
        }
    }

    const preferredFormats = ['ean_13', 'ean_8', 'upc_a', 'upc_e', 'code_128', 'code_39', 'code_93', 'codabar', 'itf'];
    try {
        detector = new BarcodeDetector({formats: preferredFormats});
    } catch (_) {
        detector = new BarcodeDetector();
    }
    stream = await navigator.mediaDevices.getUserMedia({
        audio: false,
        video: {
            facingMode: {ideal: 'environment'},
            width: {ideal: 1280},
            height: {ideal: 720},
        },
    });

    video.srcObject = stream;
    await video.play();
    scanning = true;
    setStatus('scanStatus', 'Kamera aktivt. Ret stregkoden mod kameraet. Virker det ikke, brug manuel stregkodefelt.');
    if (rafId) cancelAnimationFrame(rafId);
    rafId = requestAnimationFrame(scanLoop);
}

function stopCamera() {
    scanning = false;
    if (rafId) {
        cancelAnimationFrame(rafId);
        rafId = 0;
    }
    const video = document.getElementById('video');
    if (video instanceof HTMLVideoElement) {
        video.pause();
        video.srcObject = null;
    }
    if (stream) {
        stream.getTracks().forEach((t) => t.stop());
        stream = null;
    }
    setStatus('scanStatus', 'Kamera stoppet.');
}

async function bootstrap() {
    try {
        await resolveSession();
        await refreshInventory();
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

document.getElementById('cameraBtn')?.addEventListener('click', async () => {
    try {
        await startCamera();
    } catch (e) {
        setStatus('scanStatus', 'Kamera fejl: ' + String(e?.message || e), true);
    }
});

document.getElementById('cameraStop')?.addEventListener('click', () => stopCamera());

document.getElementById('manualSend')?.addEventListener('click', async () => {
    const input = document.getElementById('manualBarcode');
    const code = String(input?.value || '').trim();
    if (!code) return;
    try {
        await postScan(code);
        if (input) input.value = '';
    } catch (e) {
        setStatus('scanStatus', 'Scan fejl: ' + String(e?.message || e), true);
    }
});

document.getElementById('createFromScanBtn')?.addEventListener('click', () => {
    openCreateModal();
});

document.getElementById('editCancelBtn')?.addEventListener('click', () => closeEditModal());
document.getElementById('editSaveBtn')?.addEventListener('click', async () => {
    try {
        await saveEditModal();
    } catch (e) {
        flashStatus('scanStatus', 'Kunne ikke gemme: ' + String(e?.message || e), true);
    }
});
document.getElementById('editDeleteBtn')?.addEventListener('click', async () => {
    try {
        await deleteEditTarget();
    } catch (e) {
        flashStatus('scanStatus', 'Kunne ikke slette: ' + String(e?.message || e), true);
    }
});

document.getElementById('createCancelBtn')?.addEventListener('click', () => closeCreateModal());
document.getElementById('createSaveBtn')?.addEventListener('click', async () => {
    try {
        await createItemFromModal();
    } catch (e) {
        flashStatus('scanStatus', 'Kunne ikke oprette: ' + String(e?.message || e), true);
    }
});

document.getElementById('manualBarcode')?.addEventListener('keydown', (e) => {
    if (e.key === 'Enter') {
        e.preventDefault();
        document.getElementById('manualSend')?.click();
    }
});

window.addEventListener('pagehide', () => {
    stopCamera();
});

document.getElementById('searchInput')?.addEventListener('input', (e) => {
    applySearch(e.target.value);
});

bootstrap();
</script>
</body>
</html>
