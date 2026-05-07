<!DOCTYPE html>
<html lang="da">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Opsætning · Mad</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --bg: #f3efe7;
            --bg-deep: #d9dfd2;
            --surface: rgba(255, 252, 247, 0.84);
            --surface2: #f7f1e7;
            --border: rgba(20, 35, 29, 0.12);
            --accent: #2f6a56;
            --accent2: #2f6a56;
            --danger: #8f4f4f;
            --warn: #c7772f;
            --text: #14231d;
            --muted: #61716a;
            --radius: 16px;
            --shadow: 0 18px 40px rgba(20, 35, 29, 0.12);
        }

        body {
            font-family: "Avenir Next", "Helvetica Neue", "Segoe UI", sans-serif;
            background:
                radial-gradient(circle at top left, rgba(255,255,255,0.72), transparent 34%),
                radial-gradient(circle at bottom right, rgba(47,106,86,0.12), transparent 28%),
                linear-gradient(180deg, var(--bg-deep) 0%, var(--bg) 22%, #f8f4ed 100%);
            color: var(--text);
            min-height: 100dvh;
            font-size: 15px;
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

        /* ── Layout ── */
        .page {
            position: relative;
            z-index: 1;
            max-width: 980px;
            margin: 0 auto;
            padding: 24px 16px 80px;
        }

        .page-header {
            display: flex; align-items: center; justify-content: space-between;
            padding: 14px 0 18px;
            margin-bottom: 18px;
        }
        .page-header h1 {
            font-family: "Iowan Old Style", "Palatino Linotype", serif;
            font-size: 32px;
            font-weight: 700;
            letter-spacing: -0.02em;
            line-height: 1;
        }
        .page-header a {
            font-size: 13px;
            color: var(--text);
            text-decoration: none;
            padding: 10px 14px;
            border: 1px solid rgba(20,35,29,0.1);
            border-radius: 999px;
            background: rgba(255,252,247,0.82);
            transition: border-color .15s, background .15s;
        }
        .page-header a:hover { border-color: var(--accent); background: var(--surface2); color: var(--accent); }

        /* ── Auth bar ── */
        #authBar {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 16px 20px;
            margin-bottom: 24px;
            box-shadow: var(--shadow);
            backdrop-filter: blur(18px);
            display: flex;
            gap: 10px;
            align-items: flex-end;
            flex-wrap: wrap;
        }
        #authBar label { font-size: 12px; color: var(--muted); display: block; margin-bottom: 4px; }
        #authBar .field { flex: 1 1 220px; min-width: 0; }
        #authBar input {
            width: 100%;
            background: rgba(255,255,255,0.72);
            border: 1px solid var(--border);
            color: var(--text);
            border-radius: 10px;
            padding: 10px 12px;
            font-size: 14px;
        }
        #authBar input:focus { outline: none; border-color: var(--accent); }
        #authStatus {
            font-size: 13px; color: var(--muted); width: 100%;
            padding-top: 6px;
        }
        #authStatus.ok { color: var(--accent2); }
        #authStatus.err { color: var(--danger); }

        /* ── Tabs ── */
        .tabs {
            display: flex; gap: 4px; border-bottom: 1px solid var(--border);
            margin-bottom: 24px; overflow-x: auto;
        }
        .tab-btn {
            background: none; border: none; cursor: pointer;
            color: var(--muted); font-size: 14px; font-weight: 500;
            padding: 10px 16px; border-bottom: 2px solid transparent;
            white-space: nowrap; transition: color .15s, border-color .15s;
        }
        .tab-btn:hover { color: var(--text); }
        .tab-btn.active { color: var(--accent); border-bottom-color: var(--accent); font-weight: 700; }

        .tab-panel { display: none; }
        .tab-panel.active { display: block; }

        /* ── Cards ── */
        .card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 20px 24px;
            margin-bottom: 20px;
            box-shadow: var(--shadow);
            backdrop-filter: blur(18px);
        }
        .card-head {
            display: flex; align-items: center; justify-content: space-between;
            margin-bottom: 16px;
        }
        .card-head h2 { font-size: 16px; font-weight: 600; }
        .card-kicker { font-size: 11px; text-transform: uppercase; letter-spacing: .8px; color: var(--accent); margin-bottom: 4px; }

        /* ── Form elements ── */
        .field-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .field-grid.cols-3 { grid-template-columns: 1fr 1fr 1fr; }
        @media (max-width: 560px) {
            .field-grid, .field-grid.cols-3 { grid-template-columns: 1fr; }
        }
        .field-row { display: flex; flex-direction: column; gap: 4px; }
        .field-row label { font-size: 12px; color: var(--muted); }
        .f-input, .f-select {
            background: rgba(255,255,255,0.72);
            border: 1px solid var(--border);
            color: var(--text);
            border-radius: 10px;
            padding: 10px 12px;
            font-size: 14px;
            width: 100%;
        }
        .f-input:focus, .f-select:focus { outline: none; border-color: var(--accent); }
        .f-toggle { display: flex; align-items: center; gap: 10px; font-size: 14px; }
        .f-toggle input[type=checkbox] { width: 16px; height: 16px; accent-color: var(--accent); }
        .hint { font-size: 12px; color: var(--muted); margin-top: 2px; }

        /* ── Actions ── */
        .actions { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 16px; }
        .btn {
            background: var(--accent);
            color: #fbf7ef;
            border: none;
            border-radius: 999px;
            padding: 10px 18px;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            transition: opacity .15s;
        }
        .btn:hover { opacity: .85; }
        .btn.alt { background: rgba(255,252,247,0.82); color: var(--text); border: 1px solid var(--border); }
        .btn.danger { background: var(--danger); }
        .btn:disabled { opacity: .45; cursor: default; }

        /* ── Status / feedback ── */
        .status {
            margin-top: 12px;
            font-size: 13px;
            color: var(--muted);
            min-height: 20px;
        }
        .status.ok { color: var(--accent2); }
        .status.err { color: var(--danger); }
        .status.warn { color: var(--warn); }

        /* ── Data list ── */
        .data-table {
            width: 100%; border-collapse: collapse;
            font-size: 13px; margin-top: 12px;
        }
        .data-table th {
            text-align: left; color: var(--muted);
            font-weight: 500; font-size: 11px; text-transform: uppercase;
            letter-spacing: .6px; padding: 6px 10px;
            border-bottom: 1px solid var(--border);
        }
        .data-table td {
            padding: 8px 10px;
            border-bottom: 1px solid rgba(20,35,29,0.06);
            vertical-align: middle;
        }
        .data-table tr:last-child td { border-bottom: none; }
        .badge {
            display: inline-block; font-size: 11px; padding: 2px 8px;
            border-radius: 20px; background: rgba(236,226,212,0.58); color: var(--muted);
        }
        .badge.green { background: rgba(74,222,128,.12); color: var(--accent2); }
        .badge.purple { background: rgba(47,106,86,.12); color: var(--accent); }

        /* ── 2FA inline section ── */
        .section-divider {
            border: none; border-top: 1px solid var(--border);
            margin: 20px 0;
        }
    </style>
</head>
<body>
<div class="page">

    <header class="page-header">
        <div>
            <div style="font-size:11px;color:var(--muted);text-transform:uppercase;letter-spacing:.8px;margin-bottom:4px;">Mad</div>
            <h1>Opsætning</h1>
        </div>
        <a href="live.php">← HMI</a>
    </header>

    <!-- Auth bar -->
    <div id="authBar">
        <div class="field">
            <label for="tokenInput">Access token</label>
            <input id="tokenInput" type="text" placeholder="Indsæt Bearer token (uden 'Bearer ')" autocomplete="off">
        </div>
        <div class="field" style="flex: 0 1 180px;">
            <label for="deviceTokenInput">Device token (til SMS)</label>
            <input id="deviceTokenInput" type="text" placeholder="X-Device-Token" autocomplete="off">
        </div>
        <div style="display:flex;gap:8px;">
            <button class="btn alt" id="clearTokenBtn" type="button">Nulstil login</button>
        </div>
        <div id="authStatus">Gem dit access token for at bruge opsætningen.</div>
    </div>

    <!-- Tab navigation -->
    <nav class="tabs">
        <button class="tab-btn active" data-tab="tab-config">Konfiguration</button>
        <button class="tab-btn"        data-tab="tab-users">Brugere</button>
        <button class="tab-btn"        data-tab="tab-households">Husstande</button>
    </nav>

    <!-- ═══ TAB: KONFIGURATION ═══ -->
    <div class="tab-panel active" id="tab-config">

        <!-- SMS -->
        <div class="card">
            <p class="card-kicker">SMS</p>
            <div class="card-head">
                <h2>InMobile konfiguration</h2>
                <button class="btn alt" id="loadConfigBtn" type="button" style="font-size:13px;padding:6px 14px;">Indlæs</button>
            </div>
            <div class="field-grid">
                <div class="field-row">
                    <label for="cfg_SMS_DRY_RUN">Dry run (ingen rigtige SMS'er)</label>
                    <div class="f-toggle">
                        <input type="checkbox" id="cfg_SMS_DRY_RUN">
                        <span style="font-size:13px;color:var(--muted);">Slå fra for at sende rigtige SMS'er</span>
                    </div>
                </div>
                <div class="field-row">
                    <label for="cfg_INMOBILE_SENDER">Afsender</label>
                    <input class="f-input" id="cfg_INMOBILE_SENDER" type="text" placeholder="MAD" maxlength="11">
                </div>
                <div class="field-row">
                    <label for="cfg_INMOBILE_API_URL">API URL</label>
                    <input class="f-input" id="cfg_INMOBILE_API_URL" type="url" placeholder="https://api.inmobile.com/v4/sms/outgoing">
                </div>
                <div class="field-row">
                    <label for="cfg_INMOBILE_API_TOKEN">API Token</label>
                    <input class="f-input" id="cfg_INMOBILE_API_TOKEN" type="text" placeholder="(maskeret)" autocomplete="off">
                    <span class="hint">Efterlad tomt for at beholde nuværende værdi</span>
                </div>
            </div>
            <div class="actions">
                <button class="btn" id="saveSmsConfigBtn" type="button">Gem SMS-indstillinger</button>
            </div>
            <div class="status" id="smsConfigStatus"></div>
        </div>

        <!-- AI -->
        <div class="card">
            <p class="card-kicker">AI</p>
            <div class="card-head">
                <h2>Anthropic / Claude konfiguration</h2>
            </div>
            <div class="field-grid">
                <div class="field-row">
                    <label for="cfg_AI_ENABLED">AI aktiveret</label>
                    <div class="f-toggle">
                        <input type="checkbox" id="cfg_AI_ENABLED">
                        <span style="font-size:13px;color:var(--muted);">Slå til for at aktivere meal ideas</span>
                    </div>
                </div>
                <div class="field-row">
                    <label for="cfg_ANTHROPIC_MODEL">Model</label>
                    <select class="f-select" id="cfg_ANTHROPIC_MODEL">
                        <option value="claude-3-5-haiku-latest">claude-3-5-haiku-latest</option>
                        <option value="claude-3-5-sonnet-latest">claude-3-5-sonnet-latest</option>
                        <option value="claude-opus-4-5">claude-opus-4-5</option>
                    </select>
                </div>
                <div class="field-row">
                    <label for="cfg_ANTHROPIC_API_KEY">API Key</label>
                    <input class="f-input" id="cfg_ANTHROPIC_API_KEY" type="text" placeholder="(maskeret)" autocomplete="off">
                    <span class="hint">Efterlad tomt for at beholde nuværende værdi</span>
                </div>
                <div class="field-row">
                    <label for="cfg_ANTHROPIC_MAX_TOKENS">Max tokens</label>
                    <input class="f-input" id="cfg_ANTHROPIC_MAX_TOKENS" type="number" min="100" max="4096" placeholder="900">
                </div>
                <div class="field-row">
                    <label for="cfg_ANTHROPIC_TEMPERATURE">Temperature</label>
                    <input class="f-input" id="cfg_ANTHROPIC_TEMPERATURE" type="number" min="0" max="1" step="0.1" placeholder="0.5">
                </div>
                <div class="field-row">
                    <label for="cfg_ANTHROPIC_API_URL">API URL</label>
                    <input class="f-input" id="cfg_ANTHROPIC_API_URL" type="url" placeholder="https://api.anthropic.com/v1/messages">
                </div>
            </div>
            <div class="actions">
                <button class="btn" id="saveAiConfigBtn" type="button">Gem AI-indstillinger</button>
            </div>
            <div class="status" id="aiConfigStatus"></div>
        </div>

        <div class="card">
            <p class="card-kicker">Datakvalitet</p>
            <div class="card-head">
                <h2>Frida/DTU ernæringsmatch</h2>
            </div>
            <p style="font-size:13px;color:var(--muted);margin-bottom:12px;">Kør et simpelt auto-match, som løfter produkter fra OFF/placeholder til Frida-reference, når confidence er høj nok.</p>
            <div class="field-grid cols-3">
                <div class="field-row">
                    <label for="nutritionMatchLimit">Max produkter pr. kørsel</label>
                    <input class="f-input" id="nutritionMatchLimit" type="number" min="1" max="200" value="40">
                </div>
                <div class="field-row">
                    <label for="nutritionMatchScore">Min score (0.2 - 0.95)</label>
                    <input class="f-input" id="nutritionMatchScore" type="number" min="0.2" max="0.95" step="0.05" value="0.55">
                </div>
                <div class="field-row">
                    <label for="nutritionDryRun">Dry run</label>
                    <div class="f-toggle">
                        <input type="checkbox" id="nutritionDryRun" checked>
                        <span style="font-size:13px;color:var(--muted);">Vis kun forslag (ingen DB-opdatering)</span>
                    </div>
                </div>
            </div>
            <div class="actions">
                <button class="btn alt" id="loadNutritionQualityBtn" type="button">Indlæs datakvalitet</button>
                <button class="btn" id="runNutritionMatchBtn" type="button">Kør auto-match</button>
            </div>
            <div class="status" id="nutritionQualityStatus"></div>
            <div id="nutritionQualityList" style="margin-top:10px;"></div>
        </div>

    </div>

    <!-- ═══ TAB: BRUGERE ═══ -->
    <div class="tab-panel" id="tab-users">

        <div class="card">
            <div class="card-head">
                <h2>Brugere</h2>
                <button class="btn alt" id="loadUsersBtn" type="button" style="font-size:13px;padding:6px 14px;">Opdater</button>
            </div>
            <div id="usersList"><span style="color:var(--muted);font-size:13px;">Indlæs brugerliste…</span></div>
        </div>

        <div class="card">
            <p class="card-kicker">Opret bruger</p>
            <h2 style="font-size:16px;font-weight:600;margin-bottom:16px;">Ny bruger</h2>
            <div class="field-grid cols-3">
                <div class="field-row">
                    <label for="newInitials">Initialer</label>
                    <input class="f-input" id="newInitials" type="text" maxlength="10" placeholder="CT">
                </div>
                <div class="field-row">
                    <label for="newName">Fulde navn</label>
                    <input class="f-input" id="newName" type="text" placeholder="Christian Test">
                </div>
                <div class="field-row">
                    <label for="newPhone">Telefon (E.164)</label>
                    <input class="f-input" id="newPhone" type="tel" placeholder="+4512345678">
                </div>
            </div>
            <div class="actions">
                <button class="btn" id="createUserBtn" type="button">Opret bruger</button>
            </div>
            <div class="status" id="createUserStatus"></div>
        </div>

    </div>

    <!-- ═══ TAB: HUSSTANDE ═══ -->
    <div class="tab-panel" id="tab-households">

        <div class="card">
            <div class="card-head">
                <h2>Husstande</h2>
                <button class="btn alt" id="loadHouseholdsBtn" type="button" style="font-size:13px;padding:6px 14px;">Opdater</button>
            </div>
            <div id="householdsList"><span style="color:var(--muted);font-size:13px;">Indlæs husstandsliste…</span></div>
        </div>

        <div class="card">
            <p class="card-kicker">Opret husstand</p>
            <h2 style="font-size:16px;font-weight:600;margin-bottom:16px;">Ny husstand</h2>
            <div class="field-grid">
                <div class="field-row">
                    <label for="newHouseholdName">Navn</label>
                    <input class="f-input" id="newHouseholdName" type="text" placeholder="Familie Hansen">
                </div>
                <div class="field-row">
                    <label for="newHouseholdAdmin">Start-admin (user id, valgfri)</label>
                    <input class="f-input" id="newHouseholdAdmin" type="number" min="1" placeholder="">
                </div>
            </div>
            <div class="actions">
                <button class="btn" id="createHouseholdBtn" type="button">Opret husstand</button>
            </div>
            <div class="status" id="createHouseholdStatus"></div>
        </div>

        <div class="card">
            <p class="card-kicker">Tilknyt</p>
            <h2 style="font-size:16px;font-weight:600;margin-bottom:16px;">Tilknyt bruger til husstand</h2>
            <div class="field-grid cols-3">
                <div class="field-row">
                    <label for="assignUser">Bruger</label>
                    <select class="f-select" id="assignUser"><option value="">– vælg –</option></select>
                </div>
                <div class="field-row">
                    <label for="assignHousehold">Husstand</label>
                    <select class="f-select" id="assignHousehold"><option value="">– vælg –</option></select>
                </div>
                <div class="field-row">
                    <label for="assignRole">Rolle</label>
                    <select class="f-select" id="assignRole">
                        <option value="member">member</option>
                        <option value="admin">admin</option>
                        <option value="owner">owner</option>
                    </select>
                </div>
            </div>
            <div class="actions">
                <button class="btn" id="assignBtn" type="button">Tilknyt</button>
            </div>
            <div class="status" id="assignStatus"></div>
        </div>

    </div>

</div><!-- /page -->

<script>
'use strict';

const API = 'api.php';
const _params = new URLSearchParams(window.location.search);
let _token = (_params.get('access_token') || localStorage.getItem('madAccessToken') || '').trim().replace(/^Bearer\s+/i, '');
let _device = (_params.get('device_token') || localStorage.getItem('madDeviceToken') || '').trim();
let _isPlatformAdmin = false;

if (_params.get('device_token')) {
    localStorage.setItem('madDeviceToken', _device);
}

// ── Init ──────────────────────────────────────────────────────────────────
document.getElementById('tokenInput').value = _token;
document.getElementById('deviceTokenInput').value = _device;
updateAuthStatus();
openTab('tab-config');

// ── Tabs ─────────────────────────────────────────────────────────────────
function openTab(tabId) {
    const targetId = tabId || 'tab-config';

    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));

    const tabBtn = document.querySelector(`[data-tab="${targetId}"]`);
    const panel = document.getElementById(targetId);
    if (tabBtn && panel) {
        tabBtn.classList.add('active');
        panel.classList.add('active');
    }
}

document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.addEventListener('click', () => openTab(btn.dataset.tab));
});

// ── Auth helpers ──────────────────────────────────────────────────────────
function authHeaders(includeDevice = false) {
    const h = { 'Content-Type': 'application/json' };
    if (_token) h['Authorization'] = 'Bearer ' + _token;
    if (includeDevice && _device) h['X-Device-Token'] = _device;
    return h;
}

async function apiFetch(endpoint, { method = 'GET', body, includeDevice = false } = {}) {
    const opts = { method, headers: authHeaders(includeDevice) };
    if (body) opts.body = JSON.stringify(body);
    const r = await fetch(API + '?endpoint=' + encodeURIComponent(endpoint), opts);
    const json = await r.json().catch(() => ({}));
    return { ok: r.ok, status: r.status, data: json };
}

function setStatus(id, msg, type = '') {
    const el = document.getElementById(id);
    if (!el) return;
    el.textContent = msg;
    el.className = 'status' + (type ? ' ' + type : '');
}

function updateAuthStatus() {
    const el = document.getElementById('authStatus');

    if (_token) {
        el.textContent = _isPlatformAdmin
            ? 'Token er gyldigt og har platform admin-adgang.'
            : 'Token gemt lokalt. Tjekker adgang...';
        el.className = 'ok';
    } else {
        el.textContent = 'Intet token. Brug Login-fanen eller indsæt et token ovenfor.';
        el.className = '';
    }
}

async function validateSession() {
    if (!_token) {
        _isPlatformAdmin = false;
        updateAuthStatus();
        return false;
    }

    const me = await apiFetch('auth.me');
    if (!me.ok) {
        _isPlatformAdmin = false;
        _token = '';
        localStorage.removeItem('madAccessToken');
        document.getElementById('tokenInput').value = '';
        const el = document.getElementById('authStatus');
        el.textContent = 'Token er ugyldigt eller udloeberet. Log ind via 2FA igen.';
        el.className = 'err';
        openTab('tab-config');
        return false;
    }

    const user = me.data?.user || {};
    _isPlatformAdmin = !!user.is_platform_admin;
    const el = document.getElementById('authStatus');

    if (_isPlatformAdmin) {
        el.textContent = `Logget ind som ${user.initials || 'bruger'} (platform admin).`;
        el.className = 'ok';
        return true;
    }

    el.textContent = `Logget ind som ${user.initials || 'bruger'}, men mangler platform admin.`;
    el.className = 'err';
    return false;
}

document.getElementById('clearTokenBtn').addEventListener('click', () => {
    _token = '';
    localStorage.removeItem('madAccessToken');
    document.getElementById('tokenInput').value = '';
    _isPlatformAdmin = false;
    updateAuthStatus();
    openTab('tab-config');
});

// ── Config tab ────────────────────────────────────────────────────────────
document.getElementById('loadConfigBtn').addEventListener('click', loadConfig);

async function loadConfig() {
    const canUse = await validateSession();
    if (!canUse) {
        setStatus('smsConfigStatus', 'Unauthorized: log ind med en platform admin-bruger.', 'err');
        setStatus('aiConfigStatus', 'Unauthorized: log ind med en platform admin-bruger.', 'err');
        return;
    }

    setStatus('smsConfigStatus', 'Indlæser…');
    setStatus('aiConfigStatus', 'Indlæser…');
    const { ok, data } = await apiFetch('config.get');
    if (!ok) {
        const msg = data.error || 'Fejl ' + (data.status || '');
        setStatus('smsConfigStatus', msg, 'err');
        setStatus('aiConfigStatus', msg, 'err');
        return;
    }
    const cfg = data.config || {};
    // SMS
    document.getElementById('cfg_SMS_DRY_RUN').checked    = cfg.SMS_DRY_RUN?.value === 'true';
    document.getElementById('cfg_INMOBILE_SENDER').value  = cfg.INMOBILE_SENDER?.value || '';
    document.getElementById('cfg_INMOBILE_API_URL').value = cfg.INMOBILE_API_URL?.value || '';
    const smsToken = cfg.INMOBILE_API_TOKEN;
    document.getElementById('cfg_INMOBILE_API_TOKEN').placeholder = smsToken?.masked ? smsToken.value + ' (maskeret)' : '(ikke sat)';
    document.getElementById('cfg_INMOBILE_API_TOKEN').value = '';
    // AI
    document.getElementById('cfg_AI_ENABLED').checked         = cfg.AI_ENABLED?.value === 'true';
    const modelSel = document.getElementById('cfg_ANTHROPIC_MODEL');
    modelSel.value = cfg.ANTHROPIC_MODEL?.value || 'claude-3-5-haiku-latest';
    document.getElementById('cfg_ANTHROPIC_MAX_TOKENS').value   = cfg.ANTHROPIC_MAX_TOKENS?.value || '';
    document.getElementById('cfg_ANTHROPIC_TEMPERATURE').value = cfg.ANTHROPIC_TEMPERATURE?.value || '';
    document.getElementById('cfg_ANTHROPIC_API_URL').value     = cfg.ANTHROPIC_API_URL?.value || '';
    const aiKey = cfg.ANTHROPIC_API_KEY;
    document.getElementById('cfg_ANTHROPIC_API_KEY').placeholder = aiKey?.masked ? aiKey.value + ' (maskeret)' : '(ikke sat)';
    document.getElementById('cfg_ANTHROPIC_API_KEY').value = '';

    setStatus('smsConfigStatus', 'Indstillinger indlæst.', 'ok');
    setStatus('aiConfigStatus', 'Indstillinger indlæst.', 'ok');
}

document.getElementById('saveSmsConfigBtn').addEventListener('click', async () => {
    const canUse = await validateSession();
    if (!canUse) {
        setStatus('smsConfigStatus', 'Unauthorized: log ind med platform admin før gem.', 'err');
        return;
    }

    const updates = {
        SMS_DRY_RUN:         document.getElementById('cfg_SMS_DRY_RUN').checked ? 'true' : 'false',
        INMOBILE_SENDER:     document.getElementById('cfg_INMOBILE_SENDER').value.trim(),
        INMOBILE_API_URL:    document.getElementById('cfg_INMOBILE_API_URL').value.trim(),
    };
    const tokenVal = document.getElementById('cfg_INMOBILE_API_TOKEN').value.trim();
    if (tokenVal) updates.INMOBILE_API_TOKEN = tokenVal;

    setStatus('smsConfigStatus', 'Gemmer…');
    const { ok, data } = await apiFetch('config.set', { method: 'POST', body: { updates } });
    if (ok) {
        setStatus('smsConfigStatus', 'Gemt: ' + (data.saved || []).join(', '), 'ok');
    } else {
        setStatus('smsConfigStatus', data.error || 'Fejl ved gem', 'err');
    }
});

document.getElementById('saveAiConfigBtn').addEventListener('click', async () => {
    const canUse = await validateSession();
    if (!canUse) {
        setStatus('aiConfigStatus', 'Unauthorized: log ind med platform admin før gem.', 'err');
        return;
    }

    const updates = {
        AI_ENABLED:           document.getElementById('cfg_AI_ENABLED').checked ? 'true' : 'false',
        ANTHROPIC_MODEL:      document.getElementById('cfg_ANTHROPIC_MODEL').value,
        ANTHROPIC_MAX_TOKENS: document.getElementById('cfg_ANTHROPIC_MAX_TOKENS').value.trim(),
        ANTHROPIC_TEMPERATURE:document.getElementById('cfg_ANTHROPIC_TEMPERATURE').value.trim(),
        ANTHROPIC_API_URL:    document.getElementById('cfg_ANTHROPIC_API_URL').value.trim(),
    };
    const keyVal = document.getElementById('cfg_ANTHROPIC_API_KEY').value.trim();
    if (keyVal) updates.ANTHROPIC_API_KEY = keyVal;

    setStatus('aiConfigStatus', 'Gemmer…');
    const { ok, data } = await apiFetch('config.set', { method: 'POST', body: { updates } });
    if (ok) {
        setStatus('aiConfigStatus', 'Gemt: ' + (data.saved || []).join(', '), 'ok');
    } else {
        setStatus('aiConfigStatus', data.error || 'Fejl ved gem', 'err');
    }
});

document.getElementById('loadNutritionQualityBtn').addEventListener('click', loadNutritionQuality);
document.getElementById('runNutritionMatchBtn').addEventListener('click', runNutritionMatch);

function renderNutritionNeedsReview(items) {
    const root = document.getElementById('nutritionQualityList');
    if (!items.length) {
        root.innerHTML = '<span style="color:var(--muted);font-size:13px;">Ingen produkter kræver review lige nu.</span>';
        return;
    }

    let html = '<table class="data-table"><thead><tr><th>ID</th><th>Navn</th><th>Kilde</th><th>Confidence</th><th>Frida kode</th></tr></thead><tbody>';
    for (const p of items.slice(0, 30)) {
        html += `<tr>
            <td>${p.id}</td>
            <td><strong>${esc(p.name || '–')}</strong></td>
            <td>${esc(p.nutrition_source || 'unknown')}</td>
            <td>${p.nutrition_confidence === null || p.nutrition_confidence === undefined ? '–' : Number(p.nutrition_confidence).toFixed(3)}</td>
            <td>${esc(p.frida_food_code || '–')}</td>
        </tr>`;
    }
    html += '</tbody></table>';
    root.innerHTML = html;
}

async function loadNutritionQuality() {
    setStatus('nutritionQualityStatus', 'Indlæser datakvalitet…');
    const { ok, data } = await apiFetch('nutrition.quality');
    if (!ok) {
        setStatus('nutritionQualityStatus', data.error || 'Fejl ved indlæsning af datakvalitet.', 'err');
        return;
    }

    const q = data.quality || {};
    setStatus(
        'nutritionQualityStatus',
        `Produkter: ${q.total_products || 0} | Frida: ${q.frida_dtu || 0} | OFF: ${q.off_label || 0} | Mangler data: ${q.missing_nutrition || 0} | Lav confidence: ${q.low_confidence || 0}`,
        'ok'
    );
    renderNutritionNeedsReview(data.needs_review || []);
}

async function runNutritionMatch() {
    const limit = parseInt(document.getElementById('nutritionMatchLimit').value || '40', 10);
    const minScore = parseFloat(document.getElementById('nutritionMatchScore').value || '0.55');
    const dryRun = document.getElementById('nutritionDryRun').checked;

    setStatus('nutritionQualityStatus', 'Kører auto-match…');
    const { ok, data } = await apiFetch('nutrition.match.run', {
        method: 'POST',
        body: { limit, min_score: minScore, dry_run: dryRun }
    });

    if (!ok) {
        setStatus('nutritionQualityStatus', data.error || 'Fejl ved auto-match.', 'err');
        return;
    }

    setStatus(
        'nutritionQualityStatus',
        `Match færdig. Scannet: ${data.scanned_products || 0}, Matchet: ${data.matched_products || 0}, Opdateret: ${data.updated_products || 0}, Dry run: ${data.dry_run ? 'ja' : 'nej'}`,
        'ok'
    );

    if (Array.isArray(data.matches) && data.matches.length) {
        renderNutritionNeedsReview(data.matches.map(m => ({
            id: m.product_id,
            name: m.product_name,
            nutrition_source: 'frida_dtu kandidat',
            nutrition_confidence: m.score,
            frida_food_code: m.frida_food_code,
        })));
    }

    if (!dryRun) {
        loadNutritionQuality();
    }
}

// ── Users tab ─────────────────────────────────────────────────────────────
document.getElementById('loadUsersBtn').addEventListener('click', loadUsers);

async function loadUsers() {
    document.getElementById('usersList').innerHTML = '<span style="color:var(--muted);font-size:13px;">Indlæser…</span>';
    const { ok, data } = await apiFetch('admin.users');
    if (!ok) {
        document.getElementById('usersList').innerHTML = '<span style="color:var(--danger);font-size:13px;">' + (data.error || 'Fejl') + '</span>';
        return;
    }
    const users = data.users || [];
    if (!users.length) {
        document.getElementById('usersList').innerHTML = '<span style="color:var(--muted);font-size:13px;">Ingen brugere.</span>';
        return;
    }
    let html = '<table class="data-table"><thead><tr><th>ID</th><th>Initialer</th><th>Navn</th><th>Telefon</th><th>Admin</th><th>Aktiv</th></tr></thead><tbody>';
    for (const u of users) {
        html += `<tr>
            <td>${u.id}</td>
            <td><strong>${esc(u.initials)}</strong></td>
            <td>${esc(u.full_name || '–')}</td>
            <td>${esc(u.phone_e164 || '–')}</td>
            <td>${u.is_platform_admin ? '<span class="badge purple">admin</span>' : ''}</td>
            <td>${u.is_active ? '<span class="badge green">aktiv</span>' : '<span class="badge">inaktiv</span>'}</td>
        </tr>`;
    }
    html += '</tbody></table>';
    document.getElementById('usersList').innerHTML = html;

    // Fill assign dropdown
    const sel = document.getElementById('assignUser');
    sel.innerHTML = '<option value="">– vælg bruger –</option>';
    for (const u of users) {
        sel.innerHTML += `<option value="${u.id}">${esc(u.initials)} – ${esc(u.full_name || u.id)}</option>`;
    }
}

document.getElementById('createUserBtn').addEventListener('click', async () => {
    const initials = document.getElementById('newInitials').value.trim();
    const name     = document.getElementById('newName').value.trim();
    const phone    = document.getElementById('newPhone').value.trim();
    if (!initials || !phone) { setStatus('createUserStatus', 'Initialer og telefon er påkrævet.', 'err'); return; }
    setStatus('createUserStatus', 'Opretter…');
    const { ok, data } = await apiFetch('admin.users.create', { method: 'POST', body: { initials, full_name: name, phone_e164: phone } });
    if (ok) {
        setStatus('createUserStatus', `Bruger oprettet (id ${data.user?.id}).`, 'ok');
        document.getElementById('newInitials').value = '';
        document.getElementById('newName').value = '';
        document.getElementById('newPhone').value = '';
        loadUsers();
    } else {
        setStatus('createUserStatus', data.error || 'Fejl', 'err');
    }
});

// ── Households tab ────────────────────────────────────────────────────────
document.getElementById('loadHouseholdsBtn').addEventListener('click', loadHouseholds);

async function loadHouseholds() {
    document.getElementById('householdsList').innerHTML = '<span style="color:var(--muted);font-size:13px;">Indlæser…</span>';
    const { ok, data } = await apiFetch('admin.households');
    if (!ok) {
        document.getElementById('householdsList').innerHTML = '<span style="color:var(--danger);font-size:13px;">' + (data.error || 'Fejl') + '</span>';
        return;
    }
    const hh = data.households || [];
    if (!hh.length) {
        document.getElementById('householdsList').innerHTML = '<span style="color:var(--muted);font-size:13px;">Ingen husstande.</span>';
        return;
    }
    let html = '<table class="data-table"><thead><tr><th>ID</th><th>Navn</th><th>Oprettet</th></tr></thead><tbody>';
    for (const h of hh) {
        html += `<tr>
            <td>${h.id}</td>
            <td><strong>${esc(h.name)}</strong></td>
            <td style="color:var(--muted);font-size:12px;">${esc(h.created_at || '–')}</td>
        </tr>`;
    }
    html += '</tbody></table>';
    document.getElementById('householdsList').innerHTML = html;

    const sel = document.getElementById('assignHousehold');
    sel.innerHTML = '<option value="">– vælg husstand –</option>';
    for (const h of hh) {
        sel.innerHTML += `<option value="${h.id}">${esc(h.name)} (#${h.id})</option>`;
    }
}

document.getElementById('createHouseholdBtn').addEventListener('click', async () => {
    const name       = document.getElementById('newHouseholdName').value.trim();
    const adminUser  = document.getElementById('newHouseholdAdmin').value.trim();
    if (!name) { setStatus('createHouseholdStatus', 'Navn er påkrævet.', 'err'); return; }
    setStatus('createHouseholdStatus', 'Opretter…');
    const body = { name };
    if (adminUser) body.admin_user_id = parseInt(adminUser, 10);
    const { ok, data } = await apiFetch('admin.households.create', { method: 'POST', body });
    if (ok) {
        setStatus('createHouseholdStatus', `Husstand oprettet (id ${data.household?.id}).`, 'ok');
        document.getElementById('newHouseholdName').value = '';
        document.getElementById('newHouseholdAdmin').value = '';
        loadHouseholds();
    } else {
        setStatus('createHouseholdStatus', data.error || 'Fejl', 'err');
    }
});

document.getElementById('assignBtn').addEventListener('click', async () => {
    const userId      = document.getElementById('assignUser').value;
    const householdId = document.getElementById('assignHousehold').value;
    const role        = document.getElementById('assignRole').value;
    if (!userId || !householdId) { setStatus('assignStatus', 'Vælg bruger og husstand.', 'err'); return; }
    setStatus('assignStatus', 'Tilknytter…');
    const { ok, data } = await apiFetch('admin.households.assign_user', {
        method: 'POST',
        body: { user_id: parseInt(userId, 10), household_id: parseInt(householdId, 10), role }
    });
    if (ok) {
        setStatus('assignStatus', `Tilknyttet som ${role}.`, 'ok');
    } else {
        setStatus('assignStatus', data.error || 'Fejl', 'err');
    }
});

// ── Utilities ─────────────────────────────────────────────────────────────
function esc(str) {
    return String(str ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// Auto-load config and lists when token is present
if (_token) {
    validateSession().then((ok) => {
        if (ok) {
            loadConfig();
            loadUsers();
            loadHouseholds();
            loadNutritionQuality();
        }
    });
} else {
    updateAuthStatus();
}

updateAuthStatus();
</script>
</body>
</html>
