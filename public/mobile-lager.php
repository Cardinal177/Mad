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
        h1 { margin: 0; font-size: 20px; }
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
            aspect-ratio: 4 / 3;
        }
        video { width: 100%; height: 100%; object-fit: cover; display: block; }
        .scanline {
            position: absolute;
            left: 8%;
            right: 8%;
            top: 50%;
            height: 2px;
            background: rgba(89,255,164,0.95);
            box-shadow: 0 0 10px rgba(89,255,164,0.95);
            animation: sweep 2.2s linear infinite;
        }
        @keyframes sweep {
            0% { transform: translateY(-70px); }
            50% { transform: translateY(70px); }
            100% { transform: translateY(-70px); }
        }
        .row { display: grid; grid-template-columns: 1fr auto; gap: 8px; align-items: center; }
        .btn-primary { border-color: var(--accent); color: var(--accent); font-weight: 700; }

        .list { list-style: none; margin: 0; padding: 0; display: grid; gap: 8px; }
        .item {
            border: 1px solid var(--line);
            border-radius: 12px;
            padding: 10px;
            background: #fff;
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 8px;
            align-items: center;
        }
        .name { margin: 0; font-size: 15px; font-weight: 700; }
        .meta { margin: 2px 0 0; font-size: 12px; color: var(--muted); }
        .qty { font-size: 14px; font-weight: 700; color: var(--accent); }

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
    </style>
</head>
<body>
<div id="authGate" class="auth-gate">
    <div class="auth-card">
        <h2>Log ind</h2>
        <p>Skriv initialer, modtag SMS-kode, og indtast den for adgang.</p>
        <div class="auth-grid">
            <input id="gateInitials" type="text" maxlength="6" placeholder="Initialer" autocapitalize="characters" autocomplete="username">
            <button id="gateSend" class="btn-primary" type="button">Send kode</button>
            <input id="gateCode" type="text" maxlength="6" inputmode="numeric" placeholder="SMS kode (6 cifre)" autocomplete="one-time-code">
            <button id="gateVerify" class="btn-primary" type="button">Log ind</button>
            <div id="gateStatus" class="status">2FA påkrævet.</div>
        </div>
    </div>
</div>

<main id="app" class="wrap" aria-hidden="true" inert>
    <header class="top">
        <h1>Mobil lager</h1>
        <p class="sub" id="who">Henter session...</p>
    </header>

    <section class="card stack" aria-label="Scanning">
        <div class="controls">
            <div class="seg">
                <button id="modeIn" class="mode active" type="button">Ind</button>
                <button id="modeOut" class="mode" type="button">Ud</button>
            </div>
            <select id="locationSelect"></select>
        </div>

        <div class="camera">
            <video id="video" playsinline muted></video>
            <div class="scanline"></div>
        </div>

        <div class="row">
            <button id="cameraBtn" class="btn-primary" type="button">Start kamera</button>
            <button id="cameraStop" type="button">Stop</button>
        </div>

        <div class="row">
            <input id="manualBarcode" type="text" inputmode="numeric" placeholder="Manuel stregkode">
            <button id="manualSend" class="btn-primary" type="button">Scan</button>
        </div>

        <div id="scanStatus" class="status">Klar.</div>
    </section>

    <section class="card" style="margin-top:10px" aria-label="Lageroversigt">
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

<script>
const params = new URLSearchParams(window.location.search);
// Mobile pages always require a fresh 2FA verification.
let accessToken = '';
let householdId = Number(params.get('household_id') || 0) || 0;
let challengeId = '';
let movementType = 'in';
let stream = null;
let detector = null;
let rafId = 0;
let scanning = false;
let lastSignature = '';
let lastScanAt = 0;

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

async function requestCode() {
    const initialsEl = document.getElementById('gateInitials');
    const codeEl = document.getElementById('gateCode');
    const sendBtn = document.getElementById('gateSend');
    const initials = String(initialsEl?.value || '').trim().toUpperCase();
    if (initialsEl) {
        initialsEl.value = initials;
    }
    if (!initials) {
        setStatus('gateStatus', 'Skriv initialer.', true);
        return;
    }
    setStatus('gateStatus', 'Sender SMS-kode...');
    try {
        if (sendBtn instanceof HTMLButtonElement) {
            sendBtn.disabled = true;
        }
        const payload = await apiPost('api.php?endpoint=auth.request_code', {initials});
        challengeId = String(payload?.challenge_id || '');
        setStatus('gateStatus', 'SMS-kode sendt. Indtast koden.');
        if (codeEl instanceof HTMLInputElement) {
            codeEl.focus();
            codeEl.select();
        }
    } finally {
        if (sendBtn instanceof HTMLButtonElement) {
            sendBtn.disabled = false;
        }
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

async function refreshInventory() {
    const data = await apiGet(`api.php?endpoint=products&household_id=${encodeURIComponent(householdId || 1)}`);
    const products = Array.isArray(data?.products) ? data.products : [];
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
            const place = String(p?.location_name || '').trim();
            const meta = [place, 'min ' + min].filter(Boolean).join(' · ');
            return `<li class="item"><div><p class="name">${esc(name)}</p><p class="meta">${esc(meta)}</p></div><div class="qty">${esc(String(qty))}</div></li>`;
        }).join('');
}

async function refreshLocations() {
    const data = await apiGet(`api.php?endpoint=location.list&household_id=${encodeURIComponent(householdId || 1)}`);
    const locations = Array.isArray(data?.locations) ? data.locations : [];
    const select = document.getElementById('locationSelect');
    if (!select) return;
    const fallback = [{id: 1, name: 'Køkken'}];
    const rows = locations.length ? locations : fallback;
    select.innerHTML = rows
        .map((l) => `<option value="${Number(l?.id || 1)}">${esc(String(l?.name || 'Lokation'))}</option>`)
        .join('');
}

async function postScan(barcode) {
    const code = String(barcode || '').trim();
    if (!code) return;
    const now = Date.now();
    const signature = movementType + ':' + code;
    if (signature === lastSignature && (now - lastScanAt) < 1300) {
        return;
    }
    lastSignature = signature;
    lastScanAt = now;

    const locationId = Number(document.getElementById('locationSelect')?.value || 1) || 1;
    setStatus('scanStatus', 'Sender scan: ' + code + ' (' + movementType + ')');
    const payload = await apiPost('api.php?endpoint=scan', {
        barcode: code,
        household_id: Number(householdId || 1),
        location_id: locationId,
        movement_type: movementType,
        quantity: 1,
    });

    const autoAdded = !!payload?.auto_added_to_shopping_list;
    const autoRemoved = !!payload?.auto_removed_from_shopping_list;
    const suffix = autoAdded ? ' · tilføjet til indkøb' : (autoRemoved ? ' · fjernet fra indkøb' : '');
    setStatus('scanStatus', 'Registreret: ' + code + ' (' + (movementType === 'out' ? 'ud' : 'ind') + ')' + suffix);
    await refreshInventory();
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
            const raw = String(codes[0]?.rawValue || '').trim();
            if (raw.length >= 4) {
                await postScan(raw);
            }
        }
    } catch (_) {}

    rafId = requestAnimationFrame(scanLoop);
}

async function startCamera() {
    const video = document.getElementById('video');
    if (!(video instanceof HTMLVideoElement)) return;

    if (!('BarcodeDetector' in window)) {
        setStatus('scanStatus', 'Denne browser understøtter ikke BarcodeDetector. Brug manuel scan.', true);
        return;
    }

    detector = new BarcodeDetector({formats: ['ean_13', 'ean_8', 'upc_a', 'upc_e', 'code_128']});
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
    setStatus('scanStatus', 'Kamera aktivt. Ret stregkoden mod kameraet.');
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

function setMovement(next) {
    movementType = next === 'out' ? 'out' : 'in';
    document.getElementById('modeIn')?.classList.toggle('active', movementType === 'in');
    document.getElementById('modeOut')?.classList.toggle('active', movementType === 'out');
}

async function bootstrap() {
    try {
        await resolveSession();
        await Promise.all([refreshLocations(), refreshInventory()]);
        unlockApp();
    } catch (e) {
        lockApp();
        if (String(e?.message || '') !== 'NO_TOKEN') {
            setStatus('gateStatus', 'Kunne ikke logge ind: ' + String(e?.message || e), true);
        }
    }
}

document.getElementById('gateSend')?.addEventListener('click', async () => {
    try {
        await requestCode();
    } catch (e) {
        setStatus('gateStatus', 'Fejl ved SMS: ' + String(e?.message || e), true);
    }
});

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
        await requestCode();
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

document.getElementById('modeIn')?.addEventListener('click', () => setMovement('in'));
document.getElementById('modeOut')?.addEventListener('click', () => setMovement('out'));

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

document.getElementById('manualBarcode')?.addEventListener('keydown', (e) => {
    if (e.key === 'Enter') {
        e.preventDefault();
        document.getElementById('manualSend')?.click();
    }
});

window.addEventListener('pagehide', () => {
    stopCamera();
});

bootstrap();
</script>
</body>
</html>
