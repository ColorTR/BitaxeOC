#!/usr/bin/env node
'use strict';

const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const crypto = require('node:crypto');

const DEFAULT_DRIVER_BASE = 'http://127.0.0.1:4444';
const DEFAULT_TARGET_URL = 'https://oc.colortr.com/';
const args = process.argv.slice(2);

if (args.includes('--help') || args.includes('-h')) {
  console.log('Usage: node scripts/live-e2e-safari.js [target_url]');
  console.log('Env: SAFARI_WEBDRIVER_URL=http://127.0.0.1:4444');
  console.log(`Default target_url: ${DEFAULT_TARGET_URL}`);
  process.exit(0);
}

const TARGET_URL = args[0] || process.env.BITAXE_TARGET_URL || DEFAULT_TARGET_URL;
const BASE = process.env.SAFARI_WEBDRIVER_URL || DEFAULT_DRIVER_BASE;
const EXPORT_FILE_RE = /^bitaxe_snapshot_\d{8}_\d{6}\.(html|jpe?g)$/i;

function listExportArtifacts() {
  const files = [];
  const candidates = [
    path.join(os.homedir(), 'Downloads'),
    process.cwd()
  ];

  candidates.forEach((dir) => {
    let names = [];
    try {
      names = fs.readdirSync(dir);
    } catch (_) {
      return;
    }

    names
      .filter((name) => EXPORT_FILE_RE.test(name))
      .forEach((name) => {
        const fullPath = path.join(dir, name);
        files.push(fullPath);
      });
  });

  return files;
}

function captureExistingExportArtifacts() {
  return new Set(listExportArtifacts());
}

function removeNewExportArtifacts(existingArtifacts = new Set()) {
  const deleted = [];
  const baseline = existingArtifacts instanceof Set ? existingArtifacts : new Set();
  listExportArtifacts().forEach((fullPath) => {
    if (baseline.has(fullPath)) return;
    try {
      fs.unlinkSync(fullPath);
      deleted.push(fullPath);
    } catch (_) {
      // ignore cleanup failures
    }
  });
  return deleted;
}

function sleep(ms) {
  return new Promise((resolve) => setTimeout(resolve, ms));
}

function short(value, max = 220) {
  const text = typeof value === 'string' ? value : JSON.stringify(value);
  if (!text) return '';
  return text.length > max ? `${text.slice(0, max)}...` : text;
}

function assert(condition, message) {
  if (!condition) throw new Error(message);
}

async function request(method, path, body) {
  const res = await fetch(`${BASE}${path}`, {
    method,
    headers: { 'Content-Type': 'application/json' },
    body: body === undefined ? undefined : JSON.stringify(body)
  });
  const raw = await res.text();
  let parsed = null;
  try {
    parsed = raw ? JSON.parse(raw) : {};
  } catch (error) {
    throw new Error(`Non-JSON response (${method} ${path}): ${short(raw)}`);
  }
  if (!res.ok) {
    throw new Error(`HTTP ${res.status} (${method} ${path}): ${short(parsed)}`);
  }
  if (parsed && parsed.value && parsed.value.error) {
    throw new Error(`WebDriver ${parsed.value.error}: ${parsed.value.message || 'unknown error'}`);
  }
  return parsed.value;
}

async function run() {
  const existingExportArtifacts = captureExistingExportArtifacts();
  const results = [];
  let sessionId = null;

  async function runTest(name, fn) {
    const startedAt = Date.now();
    console.log(`-> ${name}`);
    try {
      const detail = await fn();
      results.push({ name, status: 'PASS', ms: Date.now() - startedAt, detail });
    } catch (error) {
      results.push({ name, status: 'FAIL', ms: Date.now() - startedAt, error: error.message || String(error) });
    }
  }

  function apiUserAgent(tag = 'default') {
    return `bitaxe-e2e/${Date.now()}-${process.pid}-${String(tag || 'default')}-${crypto.randomBytes(4).toString('hex')}`;
  }

  function isRateLimitResponse(status, raw, data) {
    if (status !== 429) return false;
    const merged = `${String(raw || '')} ${short(data, 320)}`.toLowerCase();
    return merged.includes('cok fazla istek') || merged.includes('too many');
  }

  async function fetchJsonWithRetry(url, options = {}, retryOptions = {}) {
    const maxAttempts = Math.max(1, Number(retryOptions.maxAttempts || 6));
    const baseDelayMs = Math.max(180, Number(retryOptions.baseDelayMs || 900));
    const factor = Math.max(1.1, Number(retryOptions.factor || 1.45));
    const jitterMs = Math.max(0, Number(retryOptions.jitterMs || 180));
    let attempt = 0;
    let lastError = null;

    while (attempt < maxAttempts) {
      attempt += 1;
      let response;
      let raw = '';
      let data = null;
      try {
        response = await fetch(url, options);
        raw = await response.text();
        if (raw) {
          try {
            data = JSON.parse(raw);
          } catch (_) {
            data = null;
          }
        }
      } catch (error) {
        lastError = error;
        if (attempt >= maxAttempts) throw error;
        const backoff = Math.round((baseDelayMs * (factor ** (attempt - 1))) + (Math.random() * jitterMs));
        await sleep(backoff);
        continue;
      }

      if (!isRateLimitResponse(response.status, raw, data)) {
        return { response, raw, data, attempt };
      }

      lastError = new Error(`Rate limited (429) on ${url}`);
      if (attempt >= maxAttempts) {
        return { response, raw, data, attempt };
      }
      const backoff = Math.round((baseDelayMs * (factor ** (attempt - 1))) + (Math.random() * jitterMs));
      await sleep(backoff);
    }

    throw lastError || new Error(`Request failed after ${maxAttempts} attempt(s): ${url}`);
  }

  await runTest('HTTP headers and availability', async () => {
    const response = await fetch(TARGET_URL, { method: 'GET', redirect: 'follow' });
    const csp = response.headers.get('content-security-policy') || '';
    const xfo = response.headers.get('x-frame-options') || '';
    const xcto = response.headers.get('x-content-type-options') || '';
    const hsts = response.headers.get('strict-transport-security') || '';
    assert(response.status === 200, `Expected HTTP 200, got ${response.status}`);
    assert(csp.includes("default-src 'self'"), 'CSP header is missing expected default-src policy');
    assert(xfo.toUpperCase() === 'DENY', `Unexpected X-Frame-Options: ${xfo || '(missing)'}`);
    assert(xcto.toLowerCase() === 'nosniff', `Unexpected X-Content-Type-Options: ${xcto || '(missing)'}`);
    assert(hsts.toLowerCase().includes('max-age='), 'HSTS header missing');
    return `status=${response.status}, csp-ok, xfo=${xfo}, hsts=${short(hsts, 60)}`;
  });

  await runTest('Security: API endpoint blocks anonymous direct access', async () => {
    const apiUrl = new URL('api/analyze.php', TARGET_URL).toString();
    const response = await fetch(apiUrl, { method: 'GET', redirect: 'follow' });
    assert([403, 405].includes(response.status), `Expected API access block (403/405), got ${response.status}`);
    return `apiStatus=${response.status}`;
  });

  await runTest('Security: ops panel has noindex directive', async () => {
    const opsUrl = new URL('ops-panel.php', TARGET_URL).toString();
    const response = await fetch(opsUrl, { method: 'GET', redirect: 'follow' });
    assert(response.status === 200, `Expected ops panel HTTP 200, got ${response.status}`);
    const html = await response.text();
    const hasNoIndex = html.toLowerCase().includes('name="robots"') && html.toLowerCase().includes('noindex');
    assert(hasNoIndex, 'Ops panel page should include noindex robots directive');
    return `opsStatus=${response.status}, noindex=${hasNoIndex}`;
  });

  const created = await request('POST', '/session', {
    capabilities: { alwaysMatch: { browserName: 'safari' } }
  });
  sessionId = created.sessionId;
  if (!sessionId) throw new Error('Failed to create Safari session');

  const exec = (script, args = []) => request('POST', `/session/${sessionId}/execute/sync`, { script, args });
  const navigate = (url) => request('POST', `/session/${sessionId}/url`, { url });

  async function waitFor(label, script, timeoutMs = 20000, intervalMs = 250) {
    const startedAt = Date.now();
    let lastValue = null;
    while (Date.now() - startedAt < timeoutMs) {
      try {
        lastValue = await exec(script);
        if (lastValue) return lastValue;
      } catch (error) {
        lastValue = `error: ${error.message || String(error)}`;
      }
      await sleep(intervalMs);
    }
    throw new Error(`Timeout waiting for ${label}. Last value: ${short(lastValue)}`);
  }

  async function waitForShareUrl(label, index = 0, timeoutMs = 22000) {
    const safeIndex = Number.isInteger(index) && index >= 0 ? index : 0;
    const script = `
      const copiedList = Array.isArray(window.__shareCopiedList) ? window.__shareCopiedList : [];
      const fromList = copiedList[${safeIndex}] || '';
      const fromSingle = (${safeIndex} === 0 && typeof window.__shareCopied === 'string') ? window.__shareCopied : '';
      const fromInput = document.getElementById('share-modal-link-input')?.value || '';
      return String(${safeIndex} === 0 ? (fromList || fromSingle || fromInput || '') : (fromList || ''));
    `;
    const url = await waitFor(label, script, timeoutMs, 250);
    return String(url || '');
  }

  function isDynamicShareUrl(url) {
    const value = String(url || '').trim();
    if (!value) return false;
    return (
      /[?&]share=[a-f0-9]{16,80}(?:$|[&#])/i.test(value)
      || /\/r\/[a-f0-9]{16,80}(?:\/)?(?:$|[?#])/i.test(value)
    );
  }

  async function freshPage() {
    await navigate(TARGET_URL);
    await waitFor(
      'app ready',
      `
        const fileInput = document.getElementById('fileInput');
        const processBtn = document.getElementById('process-btn');
        if (document.readyState !== 'complete' || !fileInput || !processBtn) return '';
        return {
          chartLoaded: (typeof window.Chart === 'function'),
          processDisabled: !!processBtn.disabled
        };
      `,
      35000,
      300
    );
    await exec(`
      if (!window.__testHookInstalled) {
        window.__testHookInstalled = true;
        window.__origAlert = window.alert;
      }
      window.__testAlerts = [];
      window.alert = function(msg) {
        window.__testAlerts.push(String(msg));
      };
      return true;
    `);
    await exec(`
      const sel = document.getElementById('language-selector');
      if (sel) {
        sel.value = 'en';
        sel.dispatchEvent(new Event('change', { bubbles: true }));
      }
      return sel ? sel.value : null;
    `);
    await sleep(120);
  }

  async function getAlerts() {
    return exec('return Array.isArray(window.__testAlerts) ? window.__testAlerts.slice() : [];');
  }

  async function clearAlerts() {
    await exec('window.__testAlerts = []; return true;');
  }

  let latestDynamicShareUrl = '';

  await runTest('Smoke: initial UI critical elements', async () => {
    await freshPage();
    const state = await exec(`
      const countdown = document.getElementById('data-quality-countdown');
      const nav = (performance.getEntriesByType('navigation') || [])[0] || null;
      return {
        title: document.title,
        hasFileInput: !!document.getElementById('fileInput'),
        hasProcessBtn: !!document.getElementById('process-btn'),
        hasCountdown: !!countdown,
        countdownHidden: countdown ? countdown.classList.contains('hidden') : null,
        exportHtmlHidden: document.getElementById('export-html-btn')?.classList.contains('hidden'),
        exportJpegHidden: document.getElementById('export-jpeg-btn')?.classList.contains('hidden'),
        processLabel: (document.getElementById('upload-process-label')?.textContent || '').trim(),
        navDomContentLoadedMs: nav ? Math.round(nav.domContentLoadedEventEnd) : null,
        navLoadMs: nav ? Math.round(nav.loadEventEnd) : null
      };
    `);
    assert(state.hasFileInput, 'fileInput missing');
    assert(state.hasProcessBtn, 'process button missing');
    assert(state.hasCountdown, 'countdown element missing');
    assert(state.countdownHidden === true, 'countdown should be hidden initially');
    assert(state.exportHtmlHidden === true && state.exportJpegHidden === true, 'export buttons must be hidden before analysis');
    const normalizedLabel = state.processLabel.toUpperCase();
    assert(
      normalizedLabel.includes('ANALYSIS') || normalizedLabel.includes('ANALIZ'),
      `Unexpected process label: ${state.processLabel}`
    );
    return `title=${state.title}, DCL=${state.navDomContentLoadedMs}ms, load=${state.navLoadMs}ms`;
  });

  await runTest('Theme toggle: dark default, light switch, preference persists', async () => {
    await freshPage();
    await exec(`
      try {
        localStorage.setItem('bitaxeThemePreference', 'dark');
        localStorage.setItem('bitaxeThemeVariantPreference', 'purple');
      } catch (_) {}
      return true;
    `);
    await freshPage();

    const initial = await exec(`
      const root = document.documentElement;
      const btn = document.getElementById('theme-toggle-btn');
      return {
        hasToggle: !!btn,
        className: root.className || '',
        dataTheme: root.getAttribute('data-theme') || '',
        stored: localStorage.getItem('bitaxeThemePreference'),
        toggleIsLight: !!btn && btn.classList.contains('is-light')
      };
    `);
    assert(initial.hasToggle === true, `Theme toggle missing: ${short(initial)}`);
    assert(initial.className.includes('dark') && !initial.className.includes('light-theme'), `Default theme must be dark: ${short(initial)}`);
    assert(initial.dataTheme === 'dark', `Default data-theme should be dark: ${short(initial)}`);
    assert(initial.toggleIsLight === false, `Toggle should start in dark position: ${short(initial)}`);

    await exec("document.getElementById('theme-toggle-btn')?.click(); return true;");
    await sleep(140);
    const switched = await exec(`
      const root = document.documentElement;
      const btn = document.getElementById('theme-toggle-btn');
      return {
        className: root.className || '',
        dataTheme: root.getAttribute('data-theme') || '',
        stored: localStorage.getItem('bitaxeThemePreference'),
        toggleIsLight: !!btn && btn.classList.contains('is-light')
      };
    `);
    assert(switched.className.includes('light-theme') && !switched.className.includes('dark'), `Theme did not switch to light: ${short(switched)}`);
    assert(switched.dataTheme === 'light', `data-theme should be light after toggle: ${short(switched)}`);
    assert(switched.stored === 'light', `Theme storage should persist light: ${short(switched)}`);
    assert(switched.toggleIsLight === true, `Toggle should be in light position: ${short(switched)}`);

    await freshPage();
    const persisted = await exec(`
      const root = document.documentElement;
      const btn = document.getElementById('theme-toggle-btn');
      return {
        className: root.className || '',
        dataTheme: root.getAttribute('data-theme') || '',
        stored: localStorage.getItem('bitaxeThemePreference'),
        toggleIsLight: !!btn && btn.classList.contains('is-light')
      };
    `);
    assert(persisted.className.includes('light-theme'), `Reload should keep light theme: ${short(persisted)}`);
    assert(persisted.dataTheme === 'light' && persisted.stored === 'light', `Light preference did not persist on reload: ${short(persisted)}`);
    assert(persisted.toggleIsLight === true, `Toggle should remain light after reload: ${short(persisted)}`);

    const reset = await exec(`
      try {
        localStorage.setItem('bitaxeThemePreference', 'dark');
        localStorage.setItem('bitaxeThemeVariantPreference', 'purple');
      } catch (_) {}
      if (typeof window.applyThemePreference === 'function') {
        window.applyThemePreference('dark', { persist: false });
      } else {
        const root = document.documentElement;
        root.classList.remove('light-theme');
        root.classList.add('dark');
        root.setAttribute('data-theme', 'dark');
      }
      const root = document.documentElement;
      return {
        className: root.className || '',
        dataTheme: root.getAttribute('data-theme') || '',
        stored: localStorage.getItem('bitaxeThemePreference')
      };
    `);
    assert(reset.className.includes('dark') && reset.dataTheme === 'dark', `Theme cleanup to dark failed: ${short(reset)}`);
    assert(reset.stored === 'dark', `Theme storage cleanup failed: ${short(reset)}`);

    return `default=dark -> switched=light -> persisted=light -> reset=${reset.dataTheme}`;
  });

  await runTest('Mobile header: menu behavior and theme-before-language order', async () => {
    await freshPage();
    const mobileState = await exec(`
      const originalMatchMedia = window.matchMedia;
      const mobileMatcher = (query) => ({
        matches: String(query || '').includes('(max-width: 767px)'),
        media: String(query || ''),
        onchange: null,
        addListener: () => {},
        removeListener: () => {},
        addEventListener: () => {},
        removeEventListener: () => {},
        dispatchEvent: () => false
      });

      window.matchMedia = mobileMatcher;
      if (typeof syncMobileHeaderMenuLayout === 'function') {
        syncMobileHeaderMenuLayout();
      }
      if (typeof setMobileHeaderMenuOpen === 'function') {
        setMobileHeaderMenuOpen(false, { force: true });
      }

      const menuBtn = document.getElementById('mobile-header-menu-btn');
      const panel = document.getElementById('mobile-header-menu-panel');
      const actions = document.getElementById('header-actions');
      const themeBtn = document.getElementById('theme-toggle-btn');
      const languageControl = document.getElementById('language-control');

      const menuBtnVisible = !!menuBtn && getComputedStyle(menuBtn).display !== 'none';
      const panelHiddenInitially = !!panel && panel.classList.contains('hidden');

      let themeBeforeLanguage = false;
      if (actions && themeBtn && languageControl && themeBtn.parentElement === actions && languageControl.parentElement === actions) {
        const children = Array.from(actions.children);
        themeBeforeLanguage = children.indexOf(themeBtn) >= 0 && children.indexOf(themeBtn) < children.indexOf(languageControl);
      }

      if (menuBtn) menuBtn.click();
      const panelOpened = !!panel && !panel.classList.contains('hidden');

      document.body.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true }));
      const panelClosedOutside = !!panel && panel.classList.contains('hidden');

      window.matchMedia = originalMatchMedia;
      if (typeof syncMobileHeaderMenuLayout === 'function') {
        syncMobileHeaderMenuLayout();
      }

      return {
        menuBtnVisible,
        panelHiddenInitially,
        panelOpened,
        panelClosedOutside,
        themeBeforeLanguage
      };
    `);

    assert(mobileState.panelHiddenInitially === true, `Mobile menu panel should start hidden: ${short(mobileState)}`);
    assert(mobileState.panelOpened === true, `Mobile menu panel did not open on button click: ${short(mobileState)}`);
    assert(mobileState.panelClosedOutside === true, `Mobile menu panel did not close on outside click: ${short(mobileState)}`);
    assert(mobileState.themeBeforeLanguage === true, `Theme toggle must be before language control in header actions: ${short(mobileState)}`);

    return short(mobileState);
  });

  await runTest('Temperature colors: VRM/ASIC threshold classes are correct', async () => {
    await freshPage();
    await exec(`
      const csv = [
        'Voltage,Frequency,Hashrate,Temperature,VRM Temp,Efficiency,Error,Power',
        '1300,800,3301,54,60,17.2,0.2,55',
        '1310,810,3302,60,68,17.0,0.3,56',
        '1320,820,3303,70,75,16.8,0.4,57'
      ].join('\\n');
      const dt = new DataTransfer();
      dt.items.add(new File([csv], 'temp_color_case.csv', { type: 'text/csv', lastModified: Date.now() }));
      const input = document.getElementById('fileInput');
      input.files = dt.files;
      input.dispatchEvent(new Event('change', { bubbles: true }));
      return true;
    `);
    await waitFor('temperature color file loaded', "return document.querySelectorAll('#file-list input[name=\"masterFile\"]').length === 1;", 15000, 250);
    await exec("document.getElementById('process-btn').click(); return true;");
    await waitFor('temperature color analysis complete', "return document.querySelectorAll('#tableBody tr').length >= 3;", 25000, 300);

    const thermalState = await exec(`
      const parseCellNumber = (value) => {
        const raw = String(value || '').trim().replace(/[^0-9,.-]/g, '');
        if (!raw) return NaN;
        const normalized = (raw.includes(',') && !raw.includes('.')) ? raw.replace(',', '.') : raw.replace(/,/g, '');
        const num = Number.parseFloat(normalized);
        return Number.isFinite(num) ? num : NaN;
      };

      const headerButtons = Array.from(document.querySelectorAll('#tableHead tr:first-child .table-sort-btn'));
      const indexByKey = {};
      headerButtons.forEach((btn, idx) => {
        const key = String(btn.dataset.sortKey || '').trim();
        if (key) indexByKey[key] = idx;
      });

      const hIdx = Number(indexByKey.h);
      const vrIdx = Number(indexByKey.vr);
      const tIdx = Number(indexByKey.t);
      if (!Number.isFinite(hIdx) || !Number.isFinite(vrIdx) || !Number.isFinite(tIdx)) {
        return { indexByKey, rows: {} };
      }

      const rows = {};
      const wantedHashes = new Set([3301, 3302, 3303]);
      Array.from(document.querySelectorAll('#tableBody tr')).forEach((tr) => {
        const cells = Array.from(tr.children);
        const hash = Math.round(parseCellNumber(cells[hIdx]?.textContent || ''));
        if (!wantedHashes.has(hash)) return;
        rows[String(hash)] = {
          vrClass: String(cells[vrIdx]?.className || ''),
          asicClass: String(cells[tIdx]?.className || '')
        };
      });

      return { indexByKey, rows };
    `);

    const row3301 = thermalState?.rows?.['3301'];
    const row3302 = thermalState?.rows?.['3302'];
    const row3303 = thermalState?.rows?.['3303'];
    assert(row3301 && row3302 && row3303, `Could not find expected hash rows in table: ${short(thermalState)}`);

    assert(row3301.vrClass.includes('text-neon-green'), `VRM<=62 should be green for hash 3301: ${short(row3301)}`);
    assert(row3301.asicClass.includes('text-neon-green'), `ASIC<=55 should be green for hash 3301: ${short(row3301)}`);

    assert(row3302.vrClass.includes('text-neon-amber'), `VRM<=70 should be amber for hash 3302: ${short(row3302)}`);
    assert(row3302.asicClass.includes('text-neon-amber'), `ASIC<=64 should be amber for hash 3302: ${short(row3302)}`);

    assert(row3303.vrClass.includes('text-neon-red'), `VRM>70 should be red for hash 3303: ${short(row3303)}`);
    assert(row3303.asicClass.includes('text-neon-red'), `ASIC>64 should be red for hash 3303: ${short(row3303)}`);

    return short(thermalState.rows);
  });

  await runTest('Alert: process without file', async () => {
    await freshPage();
    await clearAlerts();
    await exec("document.getElementById('process-btn').click(); return true;");
    await sleep(120);
    const alerts = await getAlerts();
    assert(alerts.some((msg) => msg.includes('Please upload at least one CSV file.')), `Expected needCsv alert, got: ${short(alerts)}`);
    return alerts[alerts.length - 1];
  });

  await runTest('Single CSV analysis + countdown starts (fast)', async () => {
    await freshPage();
    await exec(`
      const csv = [
        'Voltage,Frequency,Hashrate,Temperature,VRM Temp,Efficiency,Error,Power',
        '1300,800,3200,55,58,17.2,0.2,55',
        '1320,850,3350,57,60,17.0,0.3,57',
        '1340,900,3500,59,61,16.8,0.4,59'
      ].join('\\n');
      const dt = new DataTransfer();
      dt.items.add(new File([csv], 'single.csv', { type: 'text/csv', lastModified: Date.now() }));
      const input = document.getElementById('fileInput');
      input.files = dt.files;
      input.dispatchEvent(new Event('change', { bubbles: true }));
      return true;
    `);
    await waitFor('single file load', "return document.querySelectorAll('#file-list input[name=\"masterFile\"]').length === 1;", 15000, 250);
    await clearAlerts();
    await exec("document.getElementById('process-btn').click(); return true;");
    await waitFor(
      'analysis completion',
      "return document.querySelectorAll('#tableBody tr').length > 0 && document.getElementById('upload-section')?.style.display === 'none';",
      25000,
      300
    );
    const immediate = await exec(`
      const c = document.getElementById('data-quality-countdown');
      const panel = document.getElementById('data-quality-section');
      return {
        countdownText: (c?.textContent || '').trim(),
        countdownHidden: c?.classList.contains('hidden'),
        panelHidden: panel?.classList.contains('hidden'),
        rows: document.querySelectorAll('#tableBody tr').length,
        exportHtmlVisible: !document.getElementById('export-html-btn')?.classList.contains('hidden'),
        exportJpegVisible: !document.getElementById('export-jpeg-btn')?.classList.contains('hidden')
      };
    `);
    assert(immediate.rows > 0, 'No table rows after analysis');
    assert(immediate.exportHtmlVisible && immediate.exportJpegVisible, 'Export buttons should be visible after analysis');
    assert(immediate.countdownHidden === false, 'Countdown should be visible right after analysis');
    assert(/^\d+s$/.test(immediate.countdownText), `Countdown text invalid: ${immediate.countdownText}`);
    return `rows=${immediate.rows}, firstCountdown=${immediate.countdownText}`;
  });

  await runTest('Validation: non-CSV skipped', async () => {
    await freshPage();
    await exec(`
      const csv = [
        'Voltage,Frequency,Hashrate,Temperature,VRM Temp,Efficiency,Error,Power',
        '1300,800,3200,55,58,17.2,0.2,55'
      ].join('\\n');
      const note = 'this is not csv';
      const dt = new DataTransfer();
      dt.items.add(new File([csv], 'ok.csv', { type: 'text/csv', lastModified: Date.now() }));
      dt.items.add(new File([note], 'note.bin', { type: 'application/octet-stream', lastModified: Date.now() + 1 }));
      const input = document.getElementById('fileInput');
      input.files = dt.files;
      input.dispatchEvent(new Event('change', { bubbles: true }));
      return true;
    `);
    await waitFor('single accepted csv', "return document.querySelectorAll('#file-list input[name=\"masterFile\"]').length === 1;", 15000, 250);
    const alerts = await getAlerts();
    assert(alerts.some((msg) => msg.includes('not CSV')), `Expected nonCsvSkipped alert, got: ${short(alerts)}`);
    return alerts.find((msg) => msg.includes('not CSV'));
  });

  await runTest('Validation: max file count per batch', async () => {
    await freshPage();
    await exec(`
      const header = 'Voltage,Frequency,Hashrate,Temperature,VRM Temp,Efficiency,Error,Power\\n';
      const dt = new DataTransfer();
      for (let i = 0; i < 32; i += 1) {
        const row = (1300 + (i % 5)) + ',' + (800 + (i % 7)) + ',' + (3200 + i) + ',55,58,17.1,0.2,55\\n';
        dt.items.add(new File([header + row], 'mini_' + i + '.csv', { type: 'text/csv', lastModified: Date.now() + i }));
      }
      const input = document.getElementById('fileInput');
      input.files = dt.files;
      input.dispatchEvent(new Event('change', { bubbles: true }));
      return dt.files.length;
    `);
    await waitFor('30 files accepted by count limit', "return document.querySelectorAll('#file-list input[name=\"masterFile\"]').length === 30;", 25000, 300);
    const alerts = await getAlerts();
    const match = alerts.find((msg) => msg.includes('Only 30 files can be processed per batch'));
    assert(Boolean(match), `Expected fileCountExceeded alert, got: ${short(alerts)}`);
    assert(match.includes('2 files were skipped'), `Unexpected fileCountExceeded text: ${match}`);
    return match;
  });

  await runTest('Validation: per-file size limit', async () => {
    await freshPage();
    await exec(`
      const header = 'Voltage,Frequency,Hashrate,Temperature,VRM Temp,Efficiency,Error,Power\\n';
      const row = '1300,800,3200,55,58,17.0,0.2,55\\n';
      let bigBody = '';
      while ((header.length + bigBody.length) < (360 * 1024)) {
        bigBody += row;
      }
      const bigCsv = header + bigBody;
      const smallCsv = header + row;
      const dt = new DataTransfer();
      dt.items.add(new File([bigCsv], 'too_big.csv', { type: 'text/csv', lastModified: Date.now() }));
      dt.items.add(new File([smallCsv], 'ok_small.csv', { type: 'text/csv', lastModified: Date.now() + 1 }));
      const input = document.getElementById('fileInput');
      input.files = dt.files;
      input.dispatchEvent(new Event('change', { bubbles: true }));
      return { count: dt.files.length, bigBytes: bigCsv.length };
    `);
    await waitFor('only small file accepted', "return document.querySelectorAll('#file-list input[name=\"masterFile\"]').length === 1;", 25000, 300);
    const alerts = await getAlerts();
    const match = alerts.find((msg) => msg.includes('too large'));
    assert(Boolean(match), `Expected fileTooLarge alert, got: ${short(alerts)}`);
    return match;
  });

  await runTest('Validation: total upload size limit', async () => {
    await freshPage();
    await exec(`
      const header = 'Voltage,Frequency,Hashrate,Temperature,VRM Temp,Efficiency,Error,Power\\n';
      const row = '1300,800,3200,55,58,17.0,0.2,55\\n';
      let payload = '';
      while ((header.length + payload.length) < (320 * 1024)) {
        payload += row;
      }
      const csv = header + payload;
      const dt = new DataTransfer();
      for (let i = 0; i < 22; i += 1) {
        dt.items.add(new File([csv], 'bulk_' + i + '.csv', { type: 'text/csv', lastModified: Date.now() + i }));
      }
      const input = document.getElementById('fileInput');
      input.files = dt.files;
      input.dispatchEvent(new Event('change', { bubbles: true }));
      return { totalFiles: dt.files.length, approxEachBytes: csv.length };
    `);
    await waitFor('bulk files parsed', "return document.querySelectorAll('#file-list input[name=\"masterFile\"]').length >= 18;", 90000, 500);
    const fileCount = await exec("return document.querySelectorAll('#file-list input[name=\"masterFile\"]').length;");
    const alerts = await getAlerts();
    const match = alerts.find((msg) => msg.includes('Total upload limit'));
    assert(Boolean(match), `Expected totalSizeExceeded alert, got: ${short(alerts)}`);
    assert(fileCount < 22, `Expected some files to be rejected by total size limit, accepted=${fileCount}`);
    return `accepted=${fileCount}; alert=${match}`;
  });

  await runTest('Validation: process while files still loading', async () => {
    await freshPage();
    await exec(`
      window.__testAlerts = [];
      const header = 'Voltage,Frequency,Hashrate,Temperature,VRM Temp,Efficiency,Error,Power\\n';
      const row = '1300,800,3200,55,58,17.0,0.2,55\\n';
      let payload = '';
      while ((header.length + payload.length) < (340 * 1024)) {
        payload += row;
      }
      const csv = header + payload;
      const dt = new DataTransfer();
      for (let i = 0; i < 4; i += 1) {
        dt.items.add(new File([csv], 'slow_' + i + '.csv', { type: 'text/csv', lastModified: Date.now() + i }));
      }
      const input = document.getElementById('fileInput');
      input.files = dt.files;
      input.dispatchEvent(new Event('change', { bubbles: true }));
      document.getElementById('process-btn').click();
      return true;
    `);
    await sleep(200);
    const alerts = await getAlerts();
    const match = alerts.find((msg) => msg.includes('Files are still loading'));
    assert(Boolean(match), `Expected waitFiles alert, got: ${short(alerts)}`);
    return match;
  });

  await runTest('Validation: CSV row truncation at 7000', async () => {
    await freshPage();
    await exec(`
      const header = 'Voltage,Frequency,Hashrate,Temperature,VRM Temp,Efficiency,Error,Power\\n';
      let out = header;
      for (let i = 0; i < 7500; i += 1) {
        const v = 1250 + (i % 50);
        const f = 700 + (i % 80);
        const h = 2800 + (i % 600);
        out += v + ',' + f + ',' + h + ',55,58,17.0,0.2,55\\n';
      }
      const dt = new DataTransfer();
      dt.items.add(new File([out], 'trunc_test.csv', { type: 'text/csv', lastModified: Date.now() }));
      const input = document.getElementById('fileInput');
      input.files = dt.files;
      input.dispatchEvent(new Event('change', { bubbles: true }));
      return out.length;
    `);
    await waitFor('trunc file loaded', "return document.querySelectorAll('#file-list input[name=\"masterFile\"]').length === 1;", 25000, 300);
    await exec("document.getElementById('process-btn').click(); return true;");
    await waitFor('trunc analysis complete', "return document.querySelectorAll('#tableBody tr').length > 0;", 30000, 300);
    const summary = await exec("return (document.getElementById('data-quality-summary')?.textContent || '').replace(/\\s+/g, ' ').trim();");
    assert(/Truncated by Limits:\s*500/.test(summary), `Expected truncated count 500 in summary, got: ${short(summary, 320)}`);
    return 'summary contains Truncated by Limits: 500';
  });

  await runTest('Merge logic: selected master overrides same V/F', async () => {
    await freshPage();
    const fileA = [
      'Voltage,Frequency,Hashrate,Temperature,VRM Temp,Efficiency,Error,Power',
      '1300,800,3000,55,58,17.3,0.2,55',
      '1320,850,3100,56,59,17.1,0.2,56'
    ].join('\n');
    const fileB = [
      'Voltage,Frequency,Hashrate,Temperature,VRM Temp,Efficiency,Error,Power',
      '1300,800,4500,62,66,19.8,1.8,70',
      '1340,900,3400,58,60,17.0,0.3,58'
    ].join('\n');

    await exec(`
      const specs = arguments[0] || [];
      const dt = new DataTransfer();
      specs.forEach((s, idx) => {
        dt.items.add(new File([String(s.content || '')], String(s.name || ('f' + idx + '.csv')), {
          type: String(s.type || 'text/csv'),
          lastModified: Number(s.lastModified || (Date.now() + idx))
        }));
      });
      const input = document.getElementById('fileInput');
      input.files = dt.files;
      input.dispatchEvent(new Event('change', { bubbles: true }));
      return dt.files.length;
    `, [[
      { name: 'masterA.csv', content: fileA, type: 'text/csv', lastModified: Date.now() + 1000 },
      { name: 'masterB.csv', content: fileB, type: 'text/csv', lastModified: Date.now() }
    ]]);

    await waitFor('2 files loaded for merge', "return document.querySelectorAll('#file-list input[name=\"masterFile\"]').length === 2;", 20000, 250);

    const selected = await exec(`
      const radios = Array.from(document.querySelectorAll('#file-list input[name="masterFile"]'));
      const target = radios.find((r) => String(r.value || '').includes('masterA.csv'));
      if (!target) return false;
      target.checked = true;
      target.dispatchEvent(new Event('change', { bubbles: true }));
      return true;
    `);
    assert(selected === true, 'Could not select masterA radio');

    await exec("document.getElementById('process-btn').click(); return true;");
    await waitFor('merge analysis complete', "return document.querySelectorAll('#tableBody tr').length > 0;", 25000, 300);

    const mergedRow = await exec(`
      const parseCellNumber = (value) => {
        const raw = String(value || '').trim().replace(/[^0-9,.-]/g, '');
        if (!raw) return NaN;
        const normalized = (raw.includes(',') && !raw.includes('.'))
          ? raw.replace(',', '.')
          : raw.replace(/,/g, '');
        const num = Number.parseFloat(normalized);
        return Number.isFinite(num) ? num : NaN;
      };

      const headerButtons = Array.from(document.querySelectorAll('#tableHead tr:first-child .table-sort-btn'));
      const indexByKey = {};
      headerButtons.forEach((btn, idx) => {
        const key = String(btn.dataset.sortKey || '').trim();
        if (key) indexByKey[key] = idx;
      });

      const vIdx = Number(indexByKey.v);
      const fIdx = Number(indexByKey.f);
      const hIdx = Number(indexByKey.h);
      if (!Number.isFinite(vIdx) || !Number.isFinite(fIdx) || !Number.isFinite(hIdx)) {
        return { hash: null, indexByKey };
      }

      const rows = Array.from(document.querySelectorAll('#tableBody tr'));
      for (const tr of rows) {
        const cells = Array.from(tr.children).map((td) => (td.textContent || '').trim());
        const v = Math.round(parseCellNumber(cells[vIdx]));
        const f = Math.round(parseCellNumber(cells[fIdx]));
        if (v === 1300 && f === 800) {
          const hash = Math.round(parseCellNumber(cells[hIdx]));
          return { hash, indexByKey };
        }
      }

      return { hash: null, indexByKey };
    `);
    assert(mergedRow.hash === 3000, `Expected master override hash 3000 at 1300/800, got ${short(mergedRow)}`);
    return `1300/800 -> ${mergedRow.hash} (master override OK)`;
  });

  await runTest('UI controls: remove file, view menu restore, language switch', async () => {
    // This test continues on current analyzed state from previous test.
    const removed = await exec(`
      const rows = Array.from(document.querySelectorAll('#file-list > div'));
      const row = rows.find((el) => (el.textContent || '').includes('masterB.csv'));
      if (!row) return false;
      const btn = row.querySelector('[data-action="remove-file"]');
      if (!btn) return false;
      btn.click();
      return true;
    `);
    assert(removed === true, 'Could not remove masterB.csv from file list');
    await waitFor('file removed', "return document.querySelectorAll('#file-list input[name=\"masterFile\"]').length === 1;", 15000, 250);

    await exec("document.querySelector('[data-panel-close=\"stability\"]').click(); return true;");
    const hiddenAfterClose = await waitFor('stability panel hidden', "const p=document.getElementById('panel-stability'); return !!p && p.classList.contains('hidden');", 7000, 200);
    assert(hiddenAfterClose === true, 'Stability panel did not hide after close button');

    await exec(`
      const viewBtn = document.getElementById('view-menu-btn');
      if (viewBtn) viewBtn.click();
      const showAll = document.getElementById('view-show-all-btn');
      if (showAll) showAll.click();
      return true;
    `);
    const shownAgain = await waitFor('stability panel shown again', "const p=document.getElementById('panel-stability'); return !!p && !p.classList.contains('hidden');", 7000, 200);
    assert(shownAgain === true, 'Stability panel did not re-open with view/show-all');

    const trLabel = await exec(`
      const sel = document.getElementById('language-selector');
      sel.value = 'tr';
      sel.dispatchEvent(new Event('change', { bubbles: true }));
      return (document.getElementById('lbl-view-menu')?.textContent || '').trim();
    `);
    assert(trLabel.toUpperCase().includes('GÖRÜNÜM'), `Unexpected Turkish label: ${trLabel}`);

    const enLabel = await exec(`
      const sel = document.getElementById('language-selector');
      sel.value = 'en';
      sel.dispatchEvent(new Event('change', { bubbles: true }));
      return (document.getElementById('lbl-view-menu')?.textContent || '').trim();
    `);
    assert(enLabel.toUpperCase().includes('VIEW'), `Unexpected English label after switch back: ${enLabel}`);

    return `panel toggle and language switch OK (TR='${trLabel}', EN='${enLabel}')`;
  });

  await runTest('Sample preview lifecycle: initial-only sample and clean replacement', async () => {
    await freshPage();
    const initialState = await exec(`
      const sampleBtn = document.getElementById('sample-preview-btn');
      return {
        hasSampleButton: !!sampleBtn,
        sampleButtonHidden: sampleBtn ? sampleBtn.classList.contains('hidden') : null
      };
    `);
    assert(initialState.hasSampleButton, 'Sample preview button missing on initial screen');
    assert(initialState.sampleButtonHidden === false, 'Sample preview button should be visible on initial screen');

    await exec("document.getElementById('sample-preview-btn')?.click(); return true;");
    await waitFor(
      'sample preview analysis complete',
      "return document.querySelectorAll('#tableBody tr').length > 0 && document.getElementById('upload-section')?.style.display === 'none';",
      30000,
      300
    );

    await exec("document.getElementById('show-upload-btn')?.click(); return true;");
    await waitFor(
      'upload overlay visible after sample preview',
      "const up=document.getElementById('upload-section'); return !!up && up.style.display !== 'none' && !up.classList.contains('slide-up-hidden');",
      12000,
      250
    );

    const managerStateAfterSample = await exec(`
      const radios = document.querySelectorAll('#file-list input[name="masterFile"]').length;
      const listText = (document.getElementById('file-list')?.textContent || '').toLowerCase();
      const sampleStillListed = listText.includes('bitaxe_demo_sample_v1.csv');
      const sampleBtnHidden = document.getElementById('sample-preview-btn')?.classList.contains('hidden');
      return { radios, sampleStillListed, sampleBtnHidden };
    `);
    assert(managerStateAfterSample.radios === 0, `Sample file should not appear in manager radios: ${short(managerStateAfterSample)}`);
    assert(managerStateAfterSample.sampleStillListed === false, `Sample file should not be listed in manager: ${short(managerStateAfterSample)}`);
    assert(managerStateAfterSample.sampleBtnHidden === false, 'Sample button should stay visible inside file manager overlay');

    await exec(`
      const csv = [
        'Voltage,Frequency,Hashrate,Temperature,VRM Temp,Efficiency,Error,Power',
        '1777,999,3999,57,63,16.9,0.3,67',
        '1766,980,3888,56,62,17.1,0.4,66'
      ].join('\\n');
      const dt = new DataTransfer();
      dt.items.add(new File([csv], 'real_replace.csv', { type: 'text/csv', lastModified: Date.now() }));
      const input = document.getElementById('fileInput');
      input.files = dt.files;
      input.dispatchEvent(new Event('change', { bubbles: true }));
      return dt.files.length;
    `);
    await waitFor('real file selected in manager', "return document.querySelectorAll('#file-list input[name=\"masterFile\"]').length === 1;", 18000, 250);

    await exec("document.getElementById('process-btn').click(); return true;");
    await waitFor('real file analysis complete', "return document.querySelectorAll('#tableBody tr').length > 0;", 25000, 300);

    await exec("document.getElementById('show-upload-btn')?.click(); return true;");
    await waitFor(
      'upload overlay visible after real analysis',
      "const up=document.getElementById('upload-section'); return !!up && up.style.display !== 'none' && !up.classList.contains('slide-up-hidden');",
      12000,
      250
    );
    const managerStateAfterReal = await exec(`
      const radios = document.querySelectorAll('#file-list input[name="masterFile"]').length;
      const listText = (document.getElementById('file-list')?.textContent || '').toLowerCase();
      return {
        radios,
        hasRealFile: listText.includes('real_replace.csv'),
        sampleStillListed: listText.includes('bitaxe_demo_sample_v1.csv')
      };
    `);
    assert(managerStateAfterReal.radios === 1, `Expected exactly one real file in manager after replacement: ${short(managerStateAfterReal)}`);
    assert(managerStateAfterReal.hasRealFile === true, `Real file missing in manager: ${short(managerStateAfterReal)}`);
    assert(managerStateAfterReal.sampleStillListed === false, `Sample file leaked into manager after replacement: ${short(managerStateAfterReal)}`);

    await exec("document.getElementById('close-upload-btn')?.click(); return true;");
    return `manager states OK: afterSample=${short(managerStateAfterSample)}, afterReal=${short(managerStateAfterReal)}`;
  });

  await runTest('Filters: slider/input sync, clamp and dynamic bounds', async () => {
    await freshPage();
    await exec(`
      const csv = [
        'Voltage,Frequency,Hashrate,Temperature,VRM Temp,Efficiency,Error,Power',
        '1200,700,2800,50,56,18.6,0.2,52',
        '1300,800,3200,55,60,17.2,0.3,55',
        '1400,900,3600,62,68,16.4,0.8,60',
        '1450,950,3800,66,72,16.1,1.2,63'
      ].join('\\n');
      const dt = new DataTransfer();
      dt.items.add(new File([csv], 'filter_sync.csv', { type: 'text/csv', lastModified: Date.now() }));
      const input = document.getElementById('fileInput');
      input.files = dt.files;
      input.dispatchEvent(new Event('change', { bubbles: true }));
      return true;
    `);
    await waitFor('filter file loaded', "return document.querySelectorAll('#file-list input[name=\"masterFile\"]').length === 1;", 15000, 250);
    await exec("document.getElementById('process-btn').click(); return true;");
    await waitFor('filter analysis complete', "return document.querySelectorAll('#tableBody tr').length >= 4;", 25000, 300);

    const bounds = await exec(`
      const readRange = (id) => {
        const el = document.getElementById(id);
        return el ? {
          min: Number(el.min),
          max: Number(el.max),
          step: Number(el.step),
          disabled: Boolean(el.disabled)
        } : null;
      };
      return {
        hash: readRange('f-h-min-range'),
        volt: readRange('f-v-min-range'),
        freq: readRange('f-f-min-range'),
        vrm: readRange('f-vr-max-range'),
        asic: readRange('f-t-max-range')
      };
    `);
    assert(bounds.hash && !bounds.hash.disabled && bounds.hash.min <= 2800 && bounds.hash.max >= 3800, `Hash slider bounds invalid: ${short(bounds)}`);
    assert(bounds.vrm && !bounds.vrm.disabled && bounds.vrm.min <= 56 && bounds.vrm.max >= 72, `VRM slider bounds invalid: ${short(bounds)}`);
    assert(bounds.asic && !bounds.asic.disabled && bounds.asic.min <= 50 && bounds.asic.max >= 66, `ASIC slider bounds invalid: ${short(bounds)}`);

    const syncFromInput = await exec(`
      const input = document.getElementById('f-h-min');
      const range = document.getElementById('f-h-min-range');
      input.value = '3450';
      input.dispatchEvent(new Event('input', { bubbles: true }));
      return {
        input: Number(input.value),
        range: Number(range.value),
        min: Number(range.min),
        max: Number(range.max),
        step: Number(range.step)
      };
    `);
    const allowedDrift = Math.max(0.0001, (Number(syncFromInput.step) || 0) + 0.0001);
    assert(Math.abs(syncFromInput.range - 3450) <= allowedDrift, `Input -> range sync failed: ${short(syncFromInput)}`);

    const syncFromRange = await exec(`
      const input = document.getElementById('f-h-min');
      const range = document.getElementById('f-h-min-range');
      const min = Number(range.min);
      const step = Number(range.step);
      const target = min + (step * 2);
      range.value = String(target);
      range.dispatchEvent(new Event('input', { bubbles: true }));
      return {
        input: Number(input.value),
        range: Number(range.value),
        target
      };
    `);
    assert(Math.abs(syncFromRange.input - syncFromRange.range) < 0.001, `Range -> input sync failed: ${short(syncFromRange)}`);

    const clamped = await exec(`
      const input = document.getElementById('f-h-min');
      const range = document.getElementById('f-h-min-range');
      input.value = '999999';
      input.dispatchEvent(new Event('input', { bubbles: true }));
      input.dispatchEvent(new Event('blur', { bubbles: true }));
      return {
        input: Number(input.value),
        range: Number(range.value),
        max: Number(range.max)
      };
    `);
    assert(clamped.input <= (clamped.max + 0.001), `Clamp on blur failed: ${short(clamped)}`);

    await exec(`
      const input = document.getElementById('f-h-min');
      input.value = '3600';
      input.dispatchEvent(new Event('input', { bubbles: true }));
      return true;
    `);
    await sleep(180);
    const filteredCount = await exec("return document.querySelectorAll('#tableBody tr').length;");
    assert(filteredCount > 0 && filteredCount < 4, `Expected filtered row count between 1 and 3, got ${filteredCount}`);

    return `bounds+sync OK, filteredRows=${filteredCount}`;
  });

  await runTest('Charts: tooltip callback exposes hashrate value', async () => {
    await freshPage();
    await exec(`
      const csv = [
        'Voltage,Frequency,Hashrate,Temperature,VRM Temp,Efficiency,Error,Power',
        '1300,800,3200,55,60,17.2,0.3,55',
        '1320,840,3340,57,62,17.0,0.4,57',
        '1350,900,3520,60,65,16.7,0.6,59'
      ].join('\\n');
      const dt = new DataTransfer();
      dt.items.add(new File([csv], 'chart_tooltip.csv', { type: 'text/csv', lastModified: Date.now() }));
      const input = document.getElementById('fileInput');
      input.files = dt.files;
      input.dispatchEvent(new Event('change', { bubbles: true }));
      return true;
    `);
    await waitFor('tooltip test file loaded', "return document.querySelectorAll('#file-list input[name=\"masterFile\"]').length === 1;", 15000, 250);
    await exec("document.getElementById('process-btn').click(); return true;");
    await waitFor('tooltip test analysis complete', "return document.querySelectorAll('#tableBody tr').length >= 3;", 25000, 300);

    const tooltipState = await exec(`
      const chartMap = (typeof chartInstances !== 'undefined' && chartInstances) ? chartInstances : null;
      const chart = chartMap ? chartMap.mainScatterChart : null;
      const callback = chart?.options?.plugins?.tooltip?.callbacks?.label;
      const sampleLabel = (typeof callback === 'function')
        ? String(callback({ raw: { hash: 3456 } }) || '')
        : '';
      return {
        hasChart: !!chart,
        hasCallback: typeof callback === 'function',
        sampleLabel
      };
    `);
    assert(tooltipState.hasChart === true, `mainScatterChart missing: ${short(tooltipState)}`);
    assert(tooltipState.hasCallback === true, `Tooltip callback missing: ${short(tooltipState)}`);
    assert(tooltipState.sampleLabel.includes('3456'), `Tooltip callback does not include hashrate value: ${short(tooltipState)}`);
    return short(tooltipState.sampleLabel);
  });

  await runTest('Data quality panel: user-pinned state survives recalculation', async () => {
    await freshPage();
    await exec(`
      const csv = [
        'Voltage,Frequency,Hashrate,Temperature,VRM Temp,Efficiency,Error,Power',
        '1300,800,3200,55,60,17.2,0.2,55',
        '1320,850,3350,57,61,17.0,0.3,57'
      ].join('\\n');
      const dt = new DataTransfer();
      dt.items.add(new File([csv], 'pin_stage_1.csv', { type: 'text/csv', lastModified: Date.now() }));
      const input = document.getElementById('fileInput');
      input.files = dt.files;
      input.dispatchEvent(new Event('change', { bubbles: true }));
      return true;
    `);
    await waitFor('pin stage 1 file loaded', "return document.querySelectorAll('#file-list input[name=\"masterFile\"]').length === 1;", 15000, 250);
    await exec("document.getElementById('process-btn').click(); return true;");
    await waitFor('pin stage 1 analysis complete', "return document.querySelectorAll('#tableBody tr').length > 0;", 25000, 300);

    const pinEnabled = await exec(`
      const btn = document.getElementById('view-menu-btn');
      if (!btn) return false;
      btn.click();
      const toggle = document.querySelector('#view-menu-list input[data-panel-toggle="data-quality"]');
      if (!toggle) return false;
      toggle.checked = true;
      toggle.dispatchEvent(new Event('change', { bubbles: true }));
      const panel = document.getElementById('data-quality-section');
      const countdown = document.getElementById('data-quality-countdown');
      return Boolean(panel && !panel.classList.contains('hidden') && countdown && countdown.classList.contains('hidden'));
    `);
    assert(pinEnabled === true, 'Could not enable pinned state for data quality panel');

    await exec("document.getElementById('show-upload-btn')?.click(); return true;");
    await waitFor(
      'overlay open for pin stage 2',
      "const up=document.getElementById('upload-section'); return !!up && up.style.display !== 'none' && !up.classList.contains('slide-up-hidden');",
      12000,
      250
    );
    await exec(`
      const csv = [
        'Voltage,Frequency,Hashrate,Temperature,VRM Temp,Efficiency,Error,Power',
        '1330,860,3390,58,62,16.9,0.3,58',
        '1360,910,3560,61,66,16.6,0.5,60'
      ].join('\\n');
      const dt = new DataTransfer();
      dt.items.add(new File([csv], 'pin_stage_2.csv', { type: 'text/csv', lastModified: Date.now() }));
      const input = document.getElementById('fileInput');
      input.files = dt.files;
      input.dispatchEvent(new Event('change', { bubbles: true }));
      return true;
    `);
    await waitFor('pin stage 2 file loaded', "return document.querySelectorAll('#file-list input[name=\"masterFile\"]').length >= 2;", 18000, 250);
    await exec("document.getElementById('process-btn').click(); return true;");
    await waitFor('pin stage 2 analysis complete', "return document.querySelectorAll('#tableBody tr').length > 0;", 25000, 300);

    const pinnedState = await exec(`
      const panel = document.getElementById('data-quality-section');
      const countdown = document.getElementById('data-quality-countdown');
      return {
        panelHidden: panel ? panel.classList.contains('hidden') : null,
        countdownHidden: countdown ? countdown.classList.contains('hidden') : null,
        countdownText: (countdown?.textContent || '').trim()
      };
    `);
    assert(pinnedState.panelHidden === false, `Pinned data quality panel should remain visible: ${short(pinnedState)}`);
    assert(pinnedState.countdownHidden === true, `Countdown should stay hidden while pinned: ${short(pinnedState)}`);
    assert(pinnedState.countdownText === '', `Pinned state should have empty countdown text: ${short(pinnedState)}`);

    return short(pinnedState);
  });

  await runTest('Share link: create and open shared report', async () => {
    await freshPage();
    await exec(`
      const csv = [
        'Voltage,Frequency,Hashrate,Temperature,VRM Temp,Efficiency,Error,Power',
        '1300,800,3200,55,60,17.2,0.2,55',
        '1320,850,3350,57,61,17.0,0.3,57',
        '1350,900,3520,60,65,16.7,0.6,59'
      ].join('\\n');
      const dt = new DataTransfer();
      dt.items.add(new File([csv], 'share_case.csv', { type: 'text/csv', lastModified: Date.now() }));
      const input = document.getElementById('fileInput');
      input.files = dt.files;
      input.dispatchEvent(new Event('change', { bubbles: true }));
      return true;
    `);
    await waitFor('share file loaded', "return document.querySelectorAll('#file-list input[name=\"masterFile\"]').length === 1;", 15000, 250);
    await exec("document.getElementById('process-btn').click(); return true;");
    await waitFor('share analysis complete', "return document.querySelectorAll('#tableBody tr').length > 0;", 25000, 300);

    const hookInstalled = await exec(`
      window.__shareCopied = '';
      copyTextToClipboard = async (text) => {
        window.__shareCopied = String(text || '');
        return true;
      };
      return true;
    `);
    assert(hookInstalled === true, 'Could not install share clipboard hook');

    await exec("document.getElementById('share-btn')?.click(); return true;");
    const copiedUrl = await waitForShareUrl('share url copied', 0, 25000);
    assert(/^https?:\/\//i.test(copiedUrl), `Invalid copied URL: ${copiedUrl}`);

    await navigate(copiedUrl);
    await waitFor(
      'shared report loaded',
      "return typeof consolidatedData !== 'undefined' && Array.isArray(consolidatedData) && consolidatedData.length > 0 && document.getElementById('upload-section')?.style.display === 'none';",
      30000,
      300
    );

    const state = await exec(`
      return {
        rows: (typeof consolidatedData !== 'undefined' && Array.isArray(consolidatedData)) ? consolidatedData.length : 0,
        uploadHidden: document.getElementById('show-upload-btn')?.classList.contains('hidden'),
        shareVisible: !document.getElementById('share-btn')?.classList.contains('hidden')
      };
    `);
    assert(state.rows > 0, `Expected shared rows > 0, got ${short(state)}`);
    assert(state.uploadHidden === true, `Upload manager should be hidden in shared view: ${short(state)}`);
    return `rows=${state.rows}, shareVisible=${state.shareVisible}`;
  });

  await runTest('Sample share uses static test URL + strict shared controls', async () => {
    await freshPage();
    await exec("document.getElementById('sample-preview-btn')?.click(); return true;");
    await waitFor('sample analysis complete', "return document.querySelectorAll('#tableBody tr').length > 0;", 30000, 300);

    const sampleShareState = await exec(`
      const shareBtn = document.getElementById('share-btn');
      return {
        rows: document.querySelectorAll('#tableBody tr').length,
        shareVisible: !!shareBtn && !shareBtn.classList.contains('hidden')
      };
    `);
    assert(sampleShareState.rows > 0, `Sample rows not loaded: ${short(sampleShareState)}`);
    assert(sampleShareState.shareVisible === true, `Share should be visible on sample dataset: ${short(sampleShareState)}`);

    const hookInstalled = await exec(`
      window.__shareCopied = '';
      copyTextToClipboard = async (text) => {
        window.__shareCopied = String(text || '');
        return true;
      };
      return true;
    `);
    assert(hookInstalled === true, 'Could not install share clipboard hook for sample share');

    await exec("document.getElementById('share-btn')?.click(); return true;");
    await waitFor(
      'sample share modal',
      "return !!document.getElementById('share-modal-overlay') && !document.getElementById('share-modal-overlay').classList.contains('hidden');",
      15000,
      250
    );

    const sampleLinkState = await exec(`
      const copied = window.__shareCopied || '';
      const modalUrl = document.getElementById('share-modal-link-input')?.value || '';
      const effective = copied || modalUrl;
      const shareBtn = document.getElementById('share-btn');
      return {
        copied,
        modalUrl,
        staticCopied: /[?&]share=test(?:$|&)/.test(effective),
        staticModal: /[?&]share=test(?:$|&)/.test(modalUrl),
        shareVisible: !!shareBtn && !shareBtn.classList.contains('hidden')
      };
    `);
    assert(sampleLinkState.staticCopied === true && sampleLinkState.staticModal === true, `Sample share URL should be static ?share=test: ${short(sampleLinkState)}`);
    assert(sampleLinkState.shareVisible === true, 'Share button should stay visible after click');

    await navigate(`${TARGET_URL}?share=test`);
    await waitFor(
      'share=test loaded',
      "return typeof consolidatedData !== 'undefined' && Array.isArray(consolidatedData) && consolidatedData.length > 0;",
      30000,
      300
    );

    const sharedState = await exec(`
      const upload = document.getElementById('upload-section');
      const draggable = document.querySelector('.draggable-item');
      const languageControl = document.getElementById('language-control');
      const headerActions = document.getElementById('header-actions');
      return {
        uploadDisplayInline: upload?.style?.display || '',
        uploadDisplayComputed: upload ? getComputedStyle(upload).display : '',
        shareHidden: document.getElementById('share-btn')?.classList.contains('hidden'),
        viewHidden: document.getElementById('view-menu-btn')?.classList.contains('hidden'),
        htmlHidden: document.getElementById('export-html-btn')?.classList.contains('hidden'),
        jpegHidden: document.getElementById('export-jpeg-btn')?.classList.contains('hidden'),
        dataQualityHidden: document.getElementById('data-quality-section')?.classList.contains('hidden'),
        languageVisible: languageControl ? getComputedStyle(languageControl).display !== 'none' : false,
        headerActionsHidden: headerActions ? getComputedStyle(headerActions).display === 'none' : false,
        draggableAttr: draggable?.getAttribute('draggable') || '',
        draggableCursor: draggable ? getComputedStyle(draggable).cursor : ''
      };
    `);

    assert(sharedState.uploadDisplayComputed === 'none', `Upload overlay must be hidden in shared mode: ${short(sharedState)}`);
    assert(sharedState.shareHidden === true, `Share button must be hidden in shared mode: ${short(sharedState)}`);
    assert(sharedState.viewHidden === true, `View menu must be hidden in shared mode: ${short(sharedState)}`);
    assert(sharedState.htmlHidden === true && sharedState.jpegHidden === true, `Export buttons must be hidden in shared mode: ${short(sharedState)}`);
    assert(sharedState.dataQualityHidden === true, `Data quality must be hidden in shared mode: ${short(sharedState)}`);
    assert(sharedState.languageVisible === true && sharedState.headerActionsHidden === true, `Only language control should remain visible: ${short(sharedState)}`);
    assert(
      sharedState.draggableAttr === '' || sharedState.draggableAttr === 'false',
      `Panels must not be draggable in shared mode: ${short(sharedState)}`
    );
    return `staticShareOk, draggable=${sharedState.draggableAttr}, upload=${sharedState.uploadDisplayComputed}`;
  });

  await runTest('UI label: start analysis -> recalculate after first run', async () => {
    await freshPage();
    const initialLabel = await exec("return (document.getElementById('upload-process-label')?.textContent || '').trim();");
    assert(initialLabel.toUpperCase().includes('START'), `Initial process label should contain START: ${initialLabel}`);

    await exec(`
      const csv = [
        'Voltage,Frequency,Hashrate,Temperature,VRM Temp,Efficiency,Error,Power',
        '1300,800,3200,55,60,17.2,0.2,55'
      ].join('\\n');
      const dt = new DataTransfer();
      dt.items.add(new File([csv], 'label_case.csv', { type: 'text/csv', lastModified: Date.now() }));
      const input = document.getElementById('fileInput');
      input.files = dt.files;
      input.dispatchEvent(new Event('change', { bubbles: true }));
      return true;
    `);
    await waitFor('label file loaded', "return document.querySelectorAll('#file-list input[name=\"masterFile\"]').length === 1;", 15000, 250);
    await exec("document.getElementById('process-btn').click(); return true;");
    await waitFor('label analysis done', "return document.querySelectorAll('#tableBody tr').length > 0;", 25000, 300);

    const finalLabel = await exec("return (document.getElementById('upload-process-label')?.textContent || '').trim();");
    assert(finalLabel.toUpperCase().includes('RECALCULATE'), `Process label should switch to RECALCULATE: ${finalLabel}`);
    return `initial='${initialLabel}', final='${finalLabel}'`;
  });

  await runTest('Language picker: menu open/select/outside-close + lang context', async () => {
    await freshPage();

    const menuOpened = await exec(`
      const btn = document.getElementById('language-toggle-btn');
      const menu = document.getElementById('language-menu');
      if (!btn || !menu) return false;
      btn.click();
      return !menu.classList.contains('hidden');
    `);
    assert(menuOpened === true, 'Language menu did not open');

    const arState = await exec(`
      const arBtn = document.querySelector('#language-menu [data-lang-option="ar"]');
      if (!arBtn) return null;
      arBtn.click();
      return {
        selected: document.getElementById('language-selector')?.value || '',
        docLang: document.documentElement.getAttribute('lang') || '',
        uiLang: document.body?.dataset?.uiLang || '',
        menuHidden: document.getElementById('language-menu')?.classList.contains('hidden'),
        currentLabel: (document.getElementById('language-current-label')?.textContent || '').trim()
      };
    `);
    assert(arState && arState.selected === 'ar', `Arabic selection failed: ${short(arState)}`);
    assert(arState.docLang === 'ar', `Document lang should be ar: ${short(arState)}`);
    assert(arState.uiLang === 'ar', `UI lang data attribute should be ar: ${short(arState)}`);
    assert(arState.menuHidden === true, `Language menu should close after option click: ${short(arState)}`);

    const outsideClose = await exec(`
      const btn = document.getElementById('language-toggle-btn');
      const menu = document.getElementById('language-menu');
      if (!btn || !menu) return null;
      btn.click();
      const openNow = !menu.classList.contains('hidden');
      document.body.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true }));
      const closedAfterOutside = menu.classList.contains('hidden');
      return { openNow, closedAfterOutside };
    `);
    assert(outsideClose && outsideClose.openNow === true && outsideClose.closedAfterOutside === true, `Outside click did not close language menu: ${short(outsideClose)}`);

    const asmState = await exec(`
      const sel = document.getElementById('language-selector');
      if (!sel) return null;
      sel.value = 'asm';
      sel.dispatchEvent(new Event('change', { bubbles: true }));
      return {
        selected: sel.value,
        docLang: document.documentElement.getAttribute('lang') || '',
        uiLang: document.body?.dataset?.uiLang || ''
      };
    `);
    assert(asmState && asmState.selected === 'asm', `ASM selection failed: ${short(asmState)}`);
    assert(asmState.docLang === 'en', `ASM should map document lang to en: ${short(asmState)}`);
    assert(asmState.uiLang === 'asm', `ASM should remain as uiLang: ${short(asmState)}`);

    await exec(`
      const sel = document.getElementById('language-selector');
      if (sel) {
        sel.value = 'en';
        sel.dispatchEvent(new Event('change', { bubbles: true }));
      }
      return true;
    `);

    return `arLabel='${short(arState.currentLabel, 80)}', asmDocLang=${asmState.docLang}`;
  });

  await runTest('View menu closes on outside click', async () => {
    await freshPage();
    await exec(`
      const csv = [
        'Voltage,Frequency,Hashrate,Temperature,VRM Temp,Efficiency,Error,Power',
        '1300,800,3200,55,60,17.2,0.2,55'
      ].join('\\n');
      const dt = new DataTransfer();
      dt.items.add(new File([csv], 'view_menu_case.csv', { type: 'text/csv', lastModified: Date.now() }));
      const input = document.getElementById('fileInput');
      input.files = dt.files;
      input.dispatchEvent(new Event('change', { bubbles: true }));
      return true;
    `);
    await waitFor('view menu file loaded', "return document.querySelectorAll('#file-list input[name=\"masterFile\"]').length === 1;", 15000, 250);
    await exec("document.getElementById('process-btn').click(); return true;");
    await waitFor('view menu analysis done', "return document.querySelectorAll('#tableBody tr').length > 0;", 25000, 300);

    const state = await exec(`
      const btn = document.getElementById('view-menu-btn');
      const menu = document.getElementById('view-menu-dropdown');
      if (!btn || !menu) return null;
      btn.click();
      const opened = !menu.classList.contains('hidden');
      document.body.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true }));
      const closed = menu.classList.contains('hidden');
      return { opened, closed };
    `);
    assert(state && state.opened === true && state.closed === true, `View menu outside-close failed: ${short(state)}`);
    return short(state);
  });

  await runTest('Table sort + quick sort + filter reset workflow', async () => {
    await freshPage();
    await exec(`
      const csv = [
        'Voltage,Frequency,Hashrate,Temperature,VRM Temp,Efficiency,Error,Power',
        '1200,700,2800,50,56,18.6,0.2,52',
        '1300,800,3200,55,60,17.2,0.3,55',
        '1400,900,3600,62,68,16.4,0.8,60',
        '1450,950,3800,66,72,16.1,1.2,63'
      ].join('\\n');
      const dt = new DataTransfer();
      dt.items.add(new File([csv], 'sort_filter_case.csv', { type: 'text/csv', lastModified: Date.now() }));
      const input = document.getElementById('fileInput');
      input.files = dt.files;
      input.dispatchEvent(new Event('change', { bubbles: true }));
      return true;
    `);
    await waitFor('sort/filter file loaded', "return document.querySelectorAll('#file-list input[name=\"masterFile\"]').length === 1;", 15000, 250);
    await exec("document.getElementById('process-btn').click(); return true;");
    await waitFor('sort/filter analysis done', "return document.querySelectorAll('#tableBody tr').length >= 4;", 25000, 300);

    const hashQuickSort = await exec(`
      const parseCellNumber = (value) => {
        const raw = String(value || '').trim().replace(/[^0-9,.-]/g, '');
        if (!raw) return NaN;
        const normalized = (raw.includes(',') && !raw.includes('.')) ? raw.replace(',', '.') : raw.replace(/,/g, '');
        const num = Number.parseFloat(normalized);
        return Number.isFinite(num) ? num : NaN;
      };
      document.getElementById('quick-sort-hash-btn')?.click();
      const hBtn = document.querySelector('#tableHead .table-sort-btn[data-sort-key="h"]');
      const idx = hBtn ? Array.from(hBtn.parentElement.parentElement.children).indexOf(hBtn.parentElement) : -1;
      const firstRow = document.querySelector('#tableBody tr');
      const firstHash = (idx >= 0 && firstRow) ? parseCellNumber(firstRow.children[idx]?.textContent || '') : NaN;
      return { idx, firstHash };
    `);
    assert(Number.isFinite(hashQuickSort.firstHash) && hashQuickSort.firstHash >= 3799, `Quick hash sort failed: ${short(hashQuickSort)}`);

    const freqSort = await exec(`
      const parseCellNumber = (value) => {
        const raw = String(value || '').trim().replace(/[^0-9,.-]/g, '');
        if (!raw) return NaN;
        const normalized = (raw.includes(',') && !raw.includes('.')) ? raw.replace(',', '.') : raw.replace(/,/g, '');
        const num = Number.parseFloat(normalized);
        return Number.isFinite(num) ? num : NaN;
      };
      const fBtn = document.querySelector('#tableHead .table-sort-btn[data-sort-key="f"]');
      if (!fBtn) return null;
      const th = fBtn.closest('th');
      const idx = th ? Array.from(th.parentElement.children).indexOf(th) : -1;
      fBtn.click(); // first click -> desc
      const firstDesc = parseCellNumber(document.querySelector('#tableBody tr')?.children[idx]?.textContent || '');
      fBtn.click(); // second click -> asc
      const firstAsc = parseCellNumber(document.querySelector('#tableBody tr')?.children[idx]?.textContent || '');
      return { idx, firstDesc, firstAsc };
    `);
    assert(freqSort && freqSort.firstDesc >= 949, `Frequency desc sort failed: ${short(freqSort)}`);
    assert(freqSort.firstAsc <= 701, `Frequency asc sort failed: ${short(freqSort)}`);

    await exec(`
      const input = document.getElementById('f-h-min');
      input.value = '3600';
      input.dispatchEvent(new Event('input', { bubbles: true }));
      return true;
    `);
    const filteredRows = await waitFor('rows filtered', "return document.querySelectorAll('#tableBody tr').length <= 2 ? document.querySelectorAll('#tableBody tr').length : 0;", 12000, 250);
    assert(filteredRows > 0 && filteredRows <= 2, `Filter did not reduce rows: ${filteredRows}`);

    await exec("document.getElementById('reset-filters-btn')?.click(); return true;");
    const restoredRows = await waitFor('rows restored after reset', "return document.querySelectorAll('#tableBody tr').length >= 4 ? document.querySelectorAll('#tableBody tr').length : 0;", 12000, 250);
    assert(restoredRows >= 4, `Reset filters did not restore rows: ${restoredRows}`);
    return `quickHash=${hashQuickSort.firstHash}, freqDesc=${freqSort.firstDesc}, freqAsc=${freqSort.firstAsc}, filtered=${filteredRows}, restored=${restoredRows}`;
  });

  await runTest('Share modal UX: same dataset keeps same link, copy + close works', async () => {
    await freshPage();
    await exec(`
      const csv = [
        'Voltage,Frequency,Hashrate,Temperature,VRM Temp,Efficiency,Error,Power',
        '1300,800,3200,55,60,17.2,0.2,55',
        '1320,850,3350,57,61,17.0,0.3,57'
      ].join('\\n');
      const dt = new DataTransfer();
      dt.items.add(new File([csv], 'share_modal_case.csv', { type: 'text/csv', lastModified: Date.now() }));
      const input = document.getElementById('fileInput');
      input.files = dt.files;
      input.dispatchEvent(new Event('change', { bubbles: true }));
      return true;
    `);
    await waitFor('share-modal file loaded', "return document.querySelectorAll('#file-list input[name=\"masterFile\"]').length === 1;", 15000, 250);
    await exec("document.getElementById('process-btn').click(); return true;");
    await waitFor('share-modal analysis done', "return document.querySelectorAll('#tableBody tr').length > 0;", 25000, 300);

    await exec(`
      window.__shareCopiedList = [];
      copyTextToClipboard = async (text) => {
        window.__shareCopiedList.push(String(text || ''));
        return true;
      };
      return true;
    `);

    await exec("document.getElementById('share-btn')?.click(); return true;");
    const firstUrl = await waitForShareUrl('first share copied', 0, 20000);
    assert(isDynamicShareUrl(firstUrl), `First share URL invalid: ${firstUrl}`);

    await exec("document.getElementById('share-modal-copy-btn')?.click(); return true;");
    const modalState = await exec(`
      const status = document.getElementById('share-modal-status');
      return {
        statusText: (status?.textContent || '').trim(),
        statusLevel: status?.dataset?.level || '',
        modalVisible: !document.getElementById('share-modal-overlay')?.classList.contains('hidden')
      };
    `);
    assert(modalState.modalVisible === true, `Share modal should stay visible after copy click: ${short(modalState)}`);
    assert(modalState.statusText.length > 0, `Share modal status should not be empty: ${short(modalState)}`);

    await exec("document.getElementById('share-modal-close-btn')?.click(); return true;");
    const closed = await waitFor('share modal closed', "return document.getElementById('share-modal-overlay')?.classList.contains('hidden') ? true : false;", 7000, 200);
    assert(closed === true, 'Share modal close button did not close modal');

    await exec("document.getElementById('share-btn')?.click(); return true;");
    const secondUrl = await waitForShareUrl('second share copied', 1, 20000);
    assert(isDynamicShareUrl(secondUrl), `Second share URL invalid: ${secondUrl}`);
    assert(firstUrl === secondUrl, `Same dataset should keep same share URL: ${firstUrl} != ${secondUrl}`);
    return `sameLink=true, level=${modalState.statusLevel || 'n/a'}`;
  });

  await runTest('Export hooks: HTML and JPEG export handlers produce files', async () => {
    await freshPage();
    await exec(`
      const csv = [
        'Voltage,Frequency,Hashrate,Temperature,VRM Temp,Efficiency,Error,Power',
        '1300,800,3200,55,60,17.2,0.2,55',
        '1320,850,3350,57,61,17.0,0.3,57'
      ].join('\\n');
      const dt = new DataTransfer();
      dt.items.add(new File([csv], 'export_case.csv', { type: 'text/csv', lastModified: Date.now() }));
      const input = document.getElementById('fileInput');
      input.files = dt.files;
      input.dispatchEvent(new Event('change', { bubbles: true }));
      return true;
    `);
    await waitFor('export file loaded', "return document.querySelectorAll('#file-list input[name=\"masterFile\"]').length === 1;", 15000, 250);
    await exec("document.getElementById('process-btn').click(); return true;");
    await waitFor('export analysis done', "return document.querySelectorAll('#tableBody tr').length > 0;", 25000, 300);

    const htmlCaptureReady = await exec(`
      window.__htmlExportCapture = null;
      downloadHtmlFile = (content, fileName) => {
        const text = String(content || '');
        window.__htmlExportCapture = {
          len: text.length,
          fileName: String(fileName || ''),
          hasState: text.includes('window.__EXPORT_STATE__'),
          hasSnapshotMode: text.includes(\"window.__STATE_MODE__='snapshot'\"),
          hasUploadHiddenRule: text.includes('#upload-section')
        };
      };
      return true;
    `);
    assert(htmlCaptureReady === true, 'Failed to install HTML export capture hook');
    await exec("document.getElementById('export-html-btn')?.click(); return true;");
    const htmlCapture = await waitFor('html export capture', "return window.__htmlExportCapture || null;", 15000, 250);
    assert(htmlCapture.len > 10000, `HTML export content is unexpectedly small: ${short(htmlCapture)}`);
    assert(htmlCapture.fileName.toLowerCase().endsWith('.html'), `HTML export filename invalid: ${short(htmlCapture)}`);
    assert(htmlCapture.hasState === true && htmlCapture.hasSnapshotMode === true, `HTML export state injection missing: ${short(htmlCapture)}`);

    const jpegCaptureReady = await exec(`
      window.__jpegExportCapture = null;
      window.__origHtml2Canvas = window.html2canvas;
      window.__origDownloadBlobFile = window.downloadBlobFile;
      window.html2canvas = async () => {
        const c = document.createElement('canvas');
        c.width = 1600;
        c.height = 2000;
        const ctx = c.getContext('2d');
        if (ctx) {
          ctx.fillStyle = '#0f172a';
          ctx.fillRect(0, 0, c.width, c.height);
          ctx.fillStyle = '#e2e8f0';
          ctx.fillRect(40, 40, 300, 40);
        }
        return c;
      };
      window.downloadBlobFile = (blob, fileName) => {
        window.__jpegExportCapture = {
          fileName: String(fileName || ''),
          size: Number(blob?.size || 0),
          type: String(blob?.type || '')
        };
      };
      return true;
    `);
    assert(jpegCaptureReady === true, 'Failed to install JPEG export capture hook');
    await exec("document.getElementById('export-jpeg-btn')?.click(); return true;");
    const jpegCapture = await waitFor('jpeg export capture', "return window.__jpegExportCapture || null;", 20000, 250);
    const jpegBtnState = await exec(`
      const btn = document.getElementById('export-jpeg-btn');
      return {
        disabled: !!btn?.disabled
      };
    `);
    assert(jpegCapture.fileName.toLowerCase().endsWith('.jpg'), `JPEG export filename invalid: ${short(jpegCapture)}`);
    assert(jpegCapture.size > 0, `JPEG export blob is empty: ${short(jpegCapture)}`);
    assert(jpegCapture.type.includes('jpeg'), `JPEG export blob type invalid: ${short(jpegCapture)}`);
    assert(jpegBtnState.disabled === false, `JPEG button should recover to enabled state: ${short(jpegBtnState)}`);
    return `htmlLen=${htmlCapture.len}, jpegSize=${jpegCapture.size}`;
  });

  await runTest('Table pagination: load more increases visible row count', async () => {
    await freshPage();
    await exec(`
      let csv = 'Voltage,Frequency,Hashrate,Temperature,VRM Temp,Efficiency,Error,Power\\n';
      for (let i = 0; i < 40; i += 1) {
        csv += (1200 + i) + ',' + (700 + (i % 30)) + ',' + (2800 + (i * 7)) + ',' + (50 + (i % 15)) + ',' + (56 + (i % 20)) + ',17.2,0.3,55\\n';
      }
      const dt = new DataTransfer();
      dt.items.add(new File([csv], 'load_more_case.csv', { type: 'text/csv', lastModified: Date.now() }));
      const input = document.getElementById('fileInput');
      input.files = dt.files;
      input.dispatchEvent(new Event('change', { bubbles: true }));
      return true;
    `);
    await waitFor('load-more file loaded', "return document.querySelectorAll('#file-list input[name=\"masterFile\"]').length === 1;", 15000, 250);
    await exec("document.getElementById('process-btn').click(); return true;");
    await waitFor('load-more analysis done', "return document.querySelectorAll('#tableBody tr').length >= 15;", 25000, 300);

    const before = await exec(`
      const btn = document.getElementById('loadMoreBtn');
      return {
        rows: document.querySelectorAll('#tableBody tr').length,
        btnVisible: !!btn && getComputedStyle(btn).display !== 'none'
      };
    `);
    assert(before.rows === 15, `Initial visible rows should be 15: ${short(before)}`);
    assert(before.btnVisible === true, `Load more button should be visible: ${short(before)}`);

    await exec("document.getElementById('loadMoreBtn')?.click(); return true;");
    const after = await waitFor('rows increased after load more', "const n=document.querySelectorAll('#tableBody tr').length; return n > 15 ? n : 0;", 12000, 250);
    assert(after > 15, `Rows did not increase after load more: ${after}`);
    return `before=${before.rows}, after=${after}`;
  });

  await runTest('Table temperature classes: VRM/ASIC thresholds map to green/amber/red', async () => {
    await freshPage();
    await exec(`
      const csv = [
        'Voltage,Frequency,Hashrate,Temperature,VRM Temp,Efficiency,Error,Power',
        '1300,800,3200,54,60,17.2,0.2,55',
        '1310,810,3210,63,69,17.1,0.3,56',
        '1320,820,3220,70,75,17.0,0.4,57'
      ].join('\\n');
      const dt = new DataTransfer();
      dt.items.add(new File([csv], 'temp_class_case.csv', { type: 'text/csv', lastModified: Date.now() }));
      const input = document.getElementById('fileInput');
      input.files = dt.files;
      input.dispatchEvent(new Event('change', { bubbles: true }));
      return true;
    `);
    await waitFor('temp class file loaded', "return document.querySelectorAll('#file-list input[name=\"masterFile\"]').length === 1;", 15000, 250);
    await exec("document.getElementById('process-btn').click(); return true;");
    await waitFor('temp class analysis done', "return document.querySelectorAll('#tableBody tr').length >= 3;", 25000, 300);

    const state = await exec(`
      const headerButtons = Array.from(document.querySelectorAll('#tableHead tr:first-child .table-sort-btn'));
      const indexByKey = {};
      headerButtons.forEach((btn, idx) => {
        const key = String(btn.dataset.sortKey || '').trim();
        if (key) indexByKey[key] = idx;
      });
      const vrIdx = Number(indexByKey.vr);
      const tIdx = Number(indexByKey.t);
      const rows = Array.from(document.querySelectorAll('#tableBody tr')).map((tr) => {
        const cells = tr.children;
        const vrCell = cells[vrIdx];
        const tCell = cells[tIdx];
        return {
          vrClass: vrCell ? vrCell.className : '',
          tClass: tCell ? tCell.className : '',
          vrText: vrCell ? (vrCell.textContent || '').trim() : '',
          tText: tCell ? (tCell.textContent || '').trim() : ''
        };
      });
      return { rows };
    `);
    assert(Array.isArray(state.rows) && state.rows.length >= 3, `Temp class rows missing: ${short(state)}`);
    const classes = state.rows.map((r) => `${r.vrClass}|${r.tClass}`).join(' || ');
    assert(classes.includes('text-neon-green'), `Expected green temp classes not found: ${classes}`);
    assert(classes.includes('text-neon-amber'), `Expected amber temp classes not found: ${classes}`);
    assert(classes.includes('text-neon-red'), `Expected red temp classes not found: ${classes}`);
    return `classMixOk rows=${state.rows.length}`;
  });

  await runTest('Share link changes when layout visibility changes', async () => {
    await freshPage();
    await exec(`
      const csv = [
        'Voltage,Frequency,Hashrate,Temperature,VRM Temp,Efficiency,Error,Power',
        '1300,800,3200,55,60,17.2,0.2,55',
        '1320,850,3350,57,61,17.0,0.3,57'
      ].join('\\n');
      const dt = new DataTransfer();
      dt.items.add(new File([csv], 'share_layout_case.csv', { type: 'text/csv', lastModified: Date.now() }));
      const input = document.getElementById('fileInput');
      input.files = dt.files;
      input.dispatchEvent(new Event('change', { bubbles: true }));
      return true;
    `);
    await waitFor('share-layout file loaded', "return document.querySelectorAll('#file-list input[name=\"masterFile\"]').length === 1;", 15000, 250);
    await exec("document.getElementById('process-btn').click(); return true;");
    await waitFor('share-layout analysis done', "return document.querySelectorAll('#tableBody tr').length > 0;", 25000, 300);

    await exec(`
      window.__shareCopiedList = [];
      copyTextToClipboard = async (text) => {
        window.__shareCopiedList.push(String(text || ''));
        return true;
      };
      return true;
    `);
    await exec("document.getElementById('share-btn')?.click(); return true;");
    const firstUrl = await waitForShareUrl('first layout share copied', 0, 20000);
    assert(isDynamicShareUrl(firstUrl), `Invalid first share URL: ${firstUrl}`);
    await exec("document.getElementById('share-modal-close-btn')?.click(); return true;");

    const toggled = await exec(`
      const viewBtn = document.getElementById('view-menu-btn');
      if (!viewBtn) return false;
      viewBtn.click();
      const toggle = document.querySelector('#view-menu-list input[data-panel-toggle="power"]');
      if (!toggle) return false;
      toggle.checked = false;
      toggle.dispatchEvent(new Event('change', { bubbles: true }));
      return document.getElementById('panel-power')?.classList.contains('hidden') === true;
    `);
    assert(toggled === true, 'Could not toggle panel visibility before second share');

    await exec("document.getElementById('share-btn')?.click(); return true;");
    const secondUrl = await waitForShareUrl('second layout share copied', 1, 20000);
    assert(isDynamicShareUrl(secondUrl), `Invalid second share URL: ${secondUrl}`);
    assert(firstUrl !== secondUrl, `Share URL should change when layout changes: ${firstUrl} == ${secondUrl}`);
    return `changed=true`;
  });

  await runTest('Unknown share token fallback: app stays stable without data', async () => {
    await navigate(`${TARGET_URL}?share=ffffffffffffffffffffffffffffffff`);
    await waitFor('unknown share fallback settled', "return document.readyState === 'complete' && !!document.getElementById('fileInput');", 25000, 300);
    const state = await exec(`
      const upload = document.getElementById('upload-section');
      const showUploadBtn = document.getElementById('show-upload-btn');
      const shareModal = document.getElementById('share-modal-overlay');
      return {
        tableRows: document.querySelectorAll('#tableBody tr').length,
        uploadDisplay: upload ? getComputedStyle(upload).display : '',
        fileInputExists: !!document.getElementById('fileInput'),
        showUploadVisible: !!showUploadBtn && !showUploadBtn.classList.contains('hidden') && getComputedStyle(showUploadBtn).display !== 'none',
        shareReadonly: document.body ? document.body.classList.contains('share-readonly') : false,
        shareBtnHidden: document.getElementById('share-btn')?.classList.contains('hidden'),
        exportHtmlHidden: document.getElementById('export-html-btn')?.classList.contains('hidden'),
        exportJpegHidden: document.getElementById('export-jpeg-btn')?.classList.contains('hidden'),
        shareModalHidden: !!shareModal && shareModal.classList.contains('hidden')
      };
    `);
    assert(state.fileInputExists === true, `fileInput missing after unknown share fallback: ${short(state)}`);
    assert(state.tableRows === 0, `Unknown share should not render data rows: ${short(state)}`);
    assert(state.shareReadonly === false, `Unknown share should recover to editable mode: ${short(state)}`);
    assert(state.uploadDisplay !== 'none', `Upload overlay should be visible after unknown share fallback: ${short(state)}`);
    assert(state.showUploadVisible === false, `File manager button should stay hidden while upload overlay is visible: ${short(state)}`);
    assert(state.shareBtnHidden === true, `Share button must remain hidden with empty dataset: ${short(state)}`);
    assert(state.exportHtmlHidden === true && state.exportJpegHidden === true, `Export buttons must stay hidden with empty dataset: ${short(state)}`);
    assert(state.shareModalHidden === true, `Share modal overlay must remain hidden: ${short(state)}`);
    return `rows=${state.tableRows}, readOnly=${state.shareReadonly}, upload=${state.uploadDisplay}, shareHidden=${state.shareBtnHidden}`;
  });

  await runTest('File manager append: recalculation with second file increases result rows', async () => {
    await freshPage();
    await exec(`
      const csv = [
        'Voltage,Frequency,Hashrate,Temperature,VRM Temp,Efficiency,Error,Power',
        '1300,800,3200,55,60,17.2,0.2,55',
        '1320,850,3350,57,61,17.0,0.3,57'
      ].join('\\n');
      const dt = new DataTransfer();
      dt.items.add(new File([csv], 'append_a.csv', { type: 'text/csv', lastModified: Date.now() }));
      const input = document.getElementById('fileInput');
      input.files = dt.files;
      input.dispatchEvent(new Event('change', { bubbles: true }));
      return true;
    `);
    await waitFor('append file A loaded', "return document.querySelectorAll('#file-list input[name=\"masterFile\"]').length === 1;", 15000, 250);
    await exec("document.getElementById('process-btn').click(); return true;");
    await waitFor('append analysis A done', "return document.querySelectorAll('#tableBody tr').length > 0;", 25000, 300);
    const before = await exec("return document.querySelectorAll('#tableBody tr').length;");

    await exec("document.getElementById('show-upload-btn')?.click(); return true;");
    await waitFor('append overlay visible', "const up=document.getElementById('upload-section'); return !!up && getComputedStyle(up).display !== 'none';", 12000, 250);
    await exec(`
      const csv = [
        'Voltage,Frequency,Hashrate,Temperature,VRM Temp,Efficiency,Error,Power',
        '1400,900,3600,62,68,16.4,0.8,60',
        '1450,950,3800,66,72,16.1,1.2,63'
      ].join('\\n');
      const dt = new DataTransfer();
      dt.items.add(new File([csv], 'append_b.csv', { type: 'text/csv', lastModified: Date.now() }));
      const input = document.getElementById('fileInput');
      input.files = dt.files;
      input.dispatchEvent(new Event('change', { bubbles: true }));
      return true;
    `);
    await waitFor('append file B added', "return document.querySelectorAll('#file-list input[name=\"masterFile\"]').length >= 2;", 20000, 250);
    await exec("document.getElementById('process-btn').click(); return true;");
    await waitFor('append analysis B done', "return document.querySelectorAll('#tableBody tr').length >= 3;", 25000, 300);
    const after = await exec("return document.querySelectorAll('#tableBody tr').length;");
    assert(after > before, `Rows should increase after append recalculation: before=${before}, after=${after}`);
    return `before=${before}, after=${after}`;
  });

  await runTest('Language persistence: localStorage updates with selection', async () => {
    await freshPage();
    const saved = await exec(`
      const sel = document.getElementById('language-selector');
      if (!sel) return null;
      sel.value = 'fr';
      sel.dispatchEvent(new Event('change', { bubbles: true }));
      return {
        selected: sel.value,
        stored: localStorage.getItem('bitaxe_ui_lang')
      };
    `);
    assert(saved && saved.selected === 'fr' && saved.stored === 'fr', `Language localStorage not persisted: ${short(saved)}`);
    await exec(`
      const sel = document.getElementById('language-selector');
      if (sel) {
        sel.value = 'en';
        sel.dispatchEvent(new Event('change', { bubbles: true }));
      }
      return true;
    `);
    return `stored=${saved.stored}`;
  });

  await runTest('JPEG export failure path: alert shown and button recovers', async () => {
    await freshPage();
    await exec(`
      const csv = [
        'Voltage,Frequency,Hashrate,Temperature,VRM Temp,Efficiency,Error,Power',
        '1300,800,3200,55,60,17.2,0.2,55'
      ].join('\\n');
      const dt = new DataTransfer();
      dt.items.add(new File([csv], 'jpeg_fail_case.csv', { type: 'text/csv', lastModified: Date.now() }));
      const input = document.getElementById('fileInput');
      input.files = dt.files;
      input.dispatchEvent(new Event('change', { bubbles: true }));
      return true;
    `);
    await waitFor('jpeg-fail file loaded', "return document.querySelectorAll('#file-list input[name=\"masterFile\"]').length === 1;", 15000, 250);
    await exec("document.getElementById('process-btn').click(); return true;");
    await waitFor('jpeg-fail analysis done', "return document.querySelectorAll('#tableBody tr').length > 0;", 25000, 300);

    await exec(`
      window.__testAlerts = [];
      window.html2canvas = async () => { throw new Error('forced failure'); };
      return true;
    `);
    await exec("document.getElementById('export-jpeg-btn')?.click(); return true;");
    await sleep(220);
    const state = await exec(`
      const btn = document.getElementById('export-jpeg-btn');
      const alerts = Array.isArray(window.__testAlerts) ? window.__testAlerts.slice() : [];
      return {
        alertLast: alerts.length ? alerts[alerts.length - 1] : '',
        disabled: !!btn?.disabled
      };
    `);
    assert(state.alertLast.length > 0, `Expected alert on JPEG failure: ${short(state)}`);
    assert(state.disabled === false, `JPEG button should recover enabled state: ${short(state)}`);
    return short(state.alertLast, 120);
  });

  await runTest('Table schema: expected sortable columns are present and ordered', async () => {
    await freshPage();
    await exec(`
      const csv = [
        'Voltage,Frequency,Hashrate,Temperature,VRM Temp,Efficiency,Error,Power',
        '1300,800,3200,55,60,17.2,0.2,55'
      ].join('\\n');
      const dt = new DataTransfer();
      dt.items.add(new File([csv], 'schema_case.csv', { type: 'text/csv', lastModified: Date.now() }));
      const input = document.getElementById('fileInput');
      input.files = dt.files;
      input.dispatchEvent(new Event('change', { bubbles: true }));
      return true;
    `);
    await waitFor('schema file loaded', "return document.querySelectorAll('#file-list input[name=\"masterFile\"]').length === 1;", 15000, 250);
    await exec("document.getElementById('process-btn').click(); return true;");
    await waitFor('schema analysis done', "return document.querySelectorAll('#tableBody tr').length > 0;", 25000, 300);

    const keys = await exec(`
      return Array.from(document.querySelectorAll('#tableHead .table-sort-btn'))
        .map((el) => String(el.dataset.sortKey || '').trim())
        .filter(Boolean);
    `);
    const expected = ['source', 'h', 'v', 'f', 'vr', 't', 'err', 'e', 'score'];
    assert(Array.isArray(keys), `Sort keys is not array: ${short(keys)}`);
    assert(keys.length === expected.length, `Unexpected sort key count: ${keys.length}, expected=${expected.length}`);
    assert(JSON.stringify(keys) === JSON.stringify(expected), `Sort key order mismatch: ${JSON.stringify(keys)} != ${JSON.stringify(expected)}`);
    return keys.join(',');
  });

  await runTest('Upload flow: selecting file does not auto-start analysis', async () => {
    await freshPage();
    await exec(`
      const csv = [
        'Voltage,Frequency,Hashrate,Temperature,VRM Temp,Efficiency,Error,Power',
        '1300,800,3200,55,60,17.2,0.2,55',
        '1310,810,3250,56,61,17.1,0.3,56'
      ].join('\\n');
      const dt = new DataTransfer();
      dt.items.add(new File([csv], 'no_auto_process_case.csv', { type: 'text/csv', lastModified: Date.now() }));
      const input = document.getElementById('fileInput');
      input.files = dt.files;
      input.dispatchEvent(new Event('change', { bubbles: true }));
      return true;
    `);
    await waitFor('no-auto-process file loaded', "return document.querySelectorAll('#file-list input[name=\"masterFile\"]').length === 1;", 15000, 250);
    const state = await exec(`
      const uploadSection = document.getElementById('upload-section');
      return {
        rows: document.querySelectorAll('#tableBody tr').length,
        uploadDisplay: uploadSection ? getComputedStyle(uploadSection).display : '',
        processLabel: (document.getElementById('upload-process-label')?.textContent || '').trim()
      };
    `);
    assert(state.rows === 0, `Rows should stay 0 before manual process click: ${short(state)}`);
    assert(state.uploadDisplay !== 'none', `Upload screen should stay visible before process click: ${short(state)}`);
    return `rows=${state.rows}, upload=${state.uploadDisplay}, label=${state.processLabel}`;
  });

  await runTest('Panel UX: pointer press adds visual state then clears on release', async () => {
    await freshPage();
    const pressed = await exec(`
      const panel = document.getElementById('panel-power');
      if (!panel) return null;
      const downEvt = (typeof PointerEvent === 'function')
        ? new PointerEvent('pointerdown', { bubbles: true, button: 0 })
        : new Event('pointerdown', { bubbles: true });
      const upEvt = (typeof PointerEvent === 'function')
        ? new PointerEvent('pointerup', { bubbles: true, button: 0 })
        : new Event('pointerup', { bubbles: true });
      panel.dispatchEvent(downEvt);
      const afterDown = panel.classList.contains('is-panel-pressed');
      document.dispatchEvent(upEvt);
      const afterUp = panel.classList.contains('is-panel-pressed');
      return {
        afterDown,
        afterUp,
        draggableAttr: panel.getAttribute('draggable')
      };
    `);
    assert(pressed && pressed.afterDown === true, `Panel should gain pressed class on pointerdown: ${short(pressed)}`);
    assert(pressed.afterUp === false, `Panel pressed class should clear on pointerup: ${short(pressed)}`);
    return `pressed=${pressed.afterDown}->${pressed.afterUp}`;
  });

  await runTest('Share state: filters persist into dynamic share URL view', async () => {
    await freshPage();
    await exec(`
      const csv = [
        'Voltage,Frequency,Hashrate,Temperature,VRM Temp,Efficiency,Error,Power',
        '1290,790,3100,54,60,17.3,0.2,54',
        '1310,810,3380,56,61,17.1,0.3,56',
        '1340,840,3620,58,63,16.9,0.4,58',
        '1360,860,3890,60,65,16.7,0.5,60'
      ].join('\\n');
      const dt = new DataTransfer();
      dt.items.add(new File([csv], 'share_filter_state_case.csv', { type: 'text/csv', lastModified: Date.now() }));
      const input = document.getElementById('fileInput');
      input.files = dt.files;
      input.dispatchEvent(new Event('change', { bubbles: true }));
      return true;
    `);
    await waitFor('share-filter file loaded', "return document.querySelectorAll('#file-list input[name=\"masterFile\"]').length === 1;", 15000, 250);
    await exec("document.getElementById('process-btn').click(); return true;");
    await waitFor('share-filter analysis done', "return document.querySelectorAll('#tableBody tr').length >= 4;", 25000, 300);

    await exec(`
      const input = document.getElementById('f-h-min');
      input.value = '3600';
      input.dispatchEvent(new Event('input', { bubbles: true }));
      window.__shareCopiedList = [];
      copyTextToClipboard = async (text) => {
        window.__shareCopiedList.push(String(text || ''));
        return true;
      };
      return true;
    `);
    const filteredRows = await waitFor('share-filter rows reduced', "const n=document.querySelectorAll('#tableBody tr').length; return n > 0 && n <= 2 ? n : 0;", 12000, 250);
    await exec("document.getElementById('share-btn')?.click(); return true;");
    const shareUrl = await waitForShareUrl('share-filter copied url', 0, 20000);
    assert(isDynamicShareUrl(shareUrl), `Invalid share URL: ${shareUrl}`);
    latestDynamicShareUrl = shareUrl;

    await navigate(shareUrl);
    await waitFor('shared filter view loaded', "return document.readyState === 'complete' && document.querySelectorAll('#tableBody tr').length > 0;", 25000, 300);
    const state = await exec(`
      const input = document.getElementById('f-h-min');
      return {
        minHashInput: input ? String(input.value || '') : '',
        rows: document.querySelectorAll('#tableBody tr').length,
        readOnly: document.body ? document.body.classList.contains('share-readonly') : false
      };
    `);
    assert(state.readOnly === true, `Shared view should be read-only: ${short(state)}`);
    assert(Number(state.minHashInput) >= 3600, `Min hash filter was not restored in shared view: ${short(state)}`);
    assert(state.rows > 0 && state.rows <= filteredRows, `Filtered rows not preserved in shared view: ${short(state)} expected<=${filteredRows}`);
    return `minHash=${state.minHashInput}, rows=${state.rows}`;
  });

  await runTest('Shared mode hardening: drag handles and close buttons remain hidden', async () => {
    const targetShareUrl = latestDynamicShareUrl || `${TARGET_URL}?share=test`;
    await navigate(targetShareUrl);
    await waitFor('shared-mode hardening page ready', "return document.readyState === 'complete' && !!document.getElementById('dashboard-grid');", 25000, 300);
    const state = await exec(`
      const handles = Array.from(document.querySelectorAll('[data-drag-handle]'));
      const closes = Array.from(document.querySelectorAll('[data-panel-close]'));
      const draggablePanels = Array.from(document.querySelectorAll('.draggable-item'));
      const hiddenHandleCount = handles.filter((el) => getComputedStyle(el).display === 'none').length;
      const hiddenCloseCount = closes.filter((el) => getComputedStyle(el).display === 'none').length;
      const draggableStillTrue = draggablePanels.filter((el) => el.getAttribute('draggable') === 'true').length;
      return {
        readOnly: document.body ? document.body.classList.contains('share-readonly') : false,
        handles: handles.length,
        hiddenHandles: hiddenHandleCount,
        closes: closes.length,
        hiddenCloses: hiddenCloseCount,
        draggableStillTrue
      };
    `);
    assert(state.readOnly === true, `Shared mode should be read-only: ${short(state)}`);
    assert(state.handles > 0 && state.hiddenHandles === state.handles, `All drag handles must be hidden in shared mode: ${short(state)}`);
    assert(state.closes > 0 && state.hiddenCloses === state.closes, `All close buttons must be hidden in shared mode: ${short(state)}`);
    assert(state.draggableStillTrue === 0, `No panel should remain draggable in shared mode: ${short(state)}`);
    return `handles=${state.hiddenHandles}/${state.handles}, closes=${state.hiddenCloses}/${state.closes}`;
  });

  await runTest('Charts: core chart instances exist with non-empty datasets', async () => {
    await freshPage();
    await exec(`
      const csv = [
        'Voltage,Frequency,Hashrate,Temperature,VRM Temp,Efficiency,Error,Power',
        '1300,800,3200,55,60,17.2,0.2,55',
        '1320,840,3360,57,62,17.0,0.3,57',
        '1350,900,3550,61,67,16.8,0.5,60'
      ].join('\\n');
      const dt = new DataTransfer();
      dt.items.add(new File([csv], 'chart_integrity_case.csv', { type: 'text/csv', lastModified: Date.now() }));
      const input = document.getElementById('fileInput');
      input.files = dt.files;
      input.dispatchEvent(new Event('change', { bubbles: true }));
      return true;
    `);
    await waitFor('chart-integrity file loaded', "return document.querySelectorAll('#file-list input[name=\"masterFile\"]').length === 1;", 15000, 250);
    await exec("document.getElementById('process-btn').click(); return true;");
    await waitFor('chart-integrity analysis done', "return document.querySelectorAll('#tableBody tr').length > 0;", 25000, 300);
    const chartState = await exec(`
      const keys = (typeof chartInstances === 'object' && chartInstances) ? Object.keys(chartInstances) : [];
      const lengths = {};
      keys.forEach((key) => {
        const chart = chartInstances[key];
        const datasets = Array.isArray(chart?.data?.datasets) ? chart.data.datasets : [];
        lengths[key] = datasets.reduce((sum, ds) => sum + (Array.isArray(ds.data) ? ds.data.length : 0), 0);
      });
      return { keys, lengths };
    `);
    assert(Array.isArray(chartState.keys) && chartState.keys.length >= 5, `Expected multiple chart instances: ${short(chartState)}`);
    const emptyCharts = chartState.keys.filter((key) => Number(chartState.lengths[key] || 0) <= 0);
    assert(emptyCharts.length === 0, `Charts with empty datasets detected: ${emptyCharts.join(', ')}`);
    return `charts=${chartState.keys.length}`;
  });

  await runTest('Share API DB path: create + dedupe + fetch + ETag 304', async () => {
    const apiUrl = new URL('api/share.php', TARGET_URL).toString();
    const origin = new URL(TARGET_URL).origin;
    const payload = {
      meta: {
        selectedLanguage: 'en',
        mode: 'share',
        appVersion: 'master-test'
      },
      visibleRows: 15,
      filters: {},
      layout: {
        order: ['stability', 'elite', 'aate', 'power', 'efficiency', 'temperature', 'frequency', 'vf-heatmap', 'table'],
        visibility: {
          stability: true,
          elite: true,
          aate: true,
          power: true,
          efficiency: true,
          temperature: true,
          frequency: true,
          'vf-heatmap': true,
          table: true
        }
      },
      sourceFiles: [],
      consolidatedData: [
        { source: 'master', v: 1300, f: 800, h: 3000, e: 16.5, err: 0.1, p: 49.5, score: 95 }
      ]
    };

    const createShare = async () => {
      const req = {
        request_ts: String(Math.floor(Date.now() / 1000)),
        request_nonce: crypto.randomBytes(12).toString('hex'),
        payload
      };
      const { response, data, raw, attempt } = await fetchJsonWithRetry(`${apiUrl}?action=create`, {
        method: 'POST',
        redirect: 'follow',
        headers: {
          'Content-Type': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          Origin: origin,
          'User-Agent': apiUserAgent('share-db-create')
        },
        body: JSON.stringify(req)
      }, {
        maxAttempts: 7,
        baseDelayMs: 950,
        factor: 1.5,
        jitterMs: 180
      });
      if (raw && !data) {
        throw new Error(`create response is not JSON: ${short(raw)}`);
      }
      assert([200, 201].includes(response.status), `Unexpected create status ${response.status}: ${short(data)}`);
      assert(data && data.ok === true, `Create API returned not-ok payload: ${short(data)}`);
      const token = String(data?.share?.token || '').trim();
      assert(token.length >= 16, `Invalid share token from create: ${short(data)}`);
      return { status: response.status, data, token, attempts: attempt };
    };

    const first = await createShare();
    const second = await createShare();
    assert(first.token === second.token, `Expected dedupe token reuse, got ${first.token} vs ${second.token}`);

    const getUrl = `${apiUrl}?share=${encodeURIComponent(first.token)}`;
    const getFirst = await fetchJsonWithRetry(getUrl, {
      method: 'GET',
      redirect: 'follow',
      headers: { 'User-Agent': apiUserAgent('share-db-get') }
    }, {
      maxAttempts: 5,
      baseDelayMs: 600,
      factor: 1.4,
      jitterMs: 120
    });
    const etag = String(getFirst.response.headers.get('etag') || '').trim();
    assert(getFirst.response.status === 200, `Expected GET 200 for share token, got ${getFirst.response.status}`);
    assert(etag.length > 0, 'Expected ETag header on share GET');
    const getData = getFirst.data;
    assert(getData && getData.ok === true, `Share GET not-ok payload: ${short(getData)}`);
    assert(
      Array.isArray(getData?.share?.payload?.consolidatedData) && getData.share.payload.consolidatedData.length > 0,
      `Share payload consolidatedData missing: ${short(getData)}`
    );

    const notModifiedRes = await fetch(getUrl, {
      method: 'GET',
      redirect: 'follow',
      headers: {
        'If-None-Match': etag,
        'User-Agent': apiUserAgent('share-db-get-etag')
      }
    });
    assert(notModifiedRes.status === 304, `Expected conditional GET 304, got ${notModifiedRes.status}`);

    return `token=${first.token.slice(0, 10)}..., create=${first.status}/${second.status}, attempts=${first.attempts}/${second.attempts}, etag304=ok`;
  });

  await runTest('Share API negative/security cases', async () => {
    const apiUrl = new URL('api/share.php', TARGET_URL).toString();
    const origin = new URL(TARGET_URL).origin;
    const payload = {
      meta: { selectedLanguage: 'en', mode: 'share', appVersion: 'master-test-neg' },
      visibleRows: 10,
      filters: {},
      layout: {
        order: ['stability', 'elite', 'aate', 'power', 'efficiency', 'temperature', 'frequency', 'vf-heatmap', 'table'],
        visibility: {
          stability: true,
          elite: true,
          aate: true,
          power: true,
          efficiency: true,
          temperature: true,
          frequency: true,
          'vf-heatmap': true,
          table: true
        }
      },
      sourceFiles: [],
      consolidatedData: [{ source: 'master', v: 1250, f: 775, h: 2900, e: 17.1, err: 0.2, p: 49.6, score: 88 }]
    };

    const missingReplay = await fetchJsonWithRetry(`${apiUrl}?action=create`, {
      method: 'POST',
      redirect: 'follow',
      headers: {
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        Origin: origin,
        'User-Agent': apiUserAgent('share-neg-missing-replay')
      },
      body: JSON.stringify({ payload })
    }, {
      maxAttempts: 7,
      baseDelayMs: 900,
      factor: 1.45,
      jitterMs: 160
    });
    assert(missingReplay.response.status === 400, `Missing replay fields should return 400: ${missingReplay.response.status} ${short(missingReplay.data || missingReplay.raw)}`);

    const wrongOriginReq = {
      request_ts: String(Math.floor(Date.now() / 1000)),
      request_nonce: crypto.randomBytes(12).toString('hex'),
      payload
    };
    const wrongOrigin = await fetchJsonWithRetry(`${apiUrl}?action=create`, {
      method: 'POST',
      redirect: 'follow',
      headers: {
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        Origin: 'https://evil.example',
        'User-Agent': apiUserAgent('share-neg-wrong-origin')
      },
      body: JSON.stringify(wrongOriginReq)
    }, {
      maxAttempts: 7,
      baseDelayMs: 900,
      factor: 1.45,
      jitterMs: 160
    });
    assert(wrongOrigin.response.status === 403, `Wrong Origin should return 403: ${wrongOrigin.response.status} ${short(wrongOrigin.data || wrongOrigin.raw)}`);

    const notFound = await fetchJsonWithRetry(`${apiUrl}?share=ffffffffffffffffffffffffffffffff`, {
      method: 'GET',
      redirect: 'follow',
      headers: { 'User-Agent': apiUserAgent('share-neg-not-found') }
    }, {
      maxAttempts: 5,
      baseDelayMs: 500,
      factor: 1.35,
      jitterMs: 120
    });
    assert(notFound.response.status === 404, `Unknown share token should return 404: ${notFound.response.status} ${short(notFound.data || notFound.raw)}`);

    return `missingReplay=${missingReplay.response.status}, wrongOrigin=${wrongOrigin.response.status}, unknownToken=${notFound.response.status}`;
  });

  if (sessionId) {
    await request('DELETE', `/session/${sessionId}`);
    sessionId = null;
  }

  const passCount = results.filter((r) => r.status === 'PASS').length;
  const failCount = results.length - passCount;

  console.log('=== BITAXE-OC LIVE TEST REPORT ===');
  console.log(`Target: ${TARGET_URL}`);
  console.log(`WebDriver: ${BASE}`);
  console.log(`Date: ${new Date().toISOString()}`);
  console.log('');
  results.forEach((r, idx) => {
    const base = `${String(idx + 1).padStart(2, '0')}. [${r.status}] ${r.name} (${r.ms}ms)`;
    if (r.status === 'PASS') {
      console.log(`${base} -> ${short(r.detail, 280)}`);
    } else {
      console.log(`${base} -> ${short(r.error, 280)}`);
    }
  });
  console.log('');
  console.log(`Summary: PASS ${passCount}/${results.length}, FAIL ${failCount}/${results.length}`);

  const cleaned = removeNewExportArtifacts(existingExportArtifacts);
  if (cleaned.length > 0) {
    console.log(`Cleanup: removed ${cleaned.length} export artifact(s)`);
  }

  if (failCount > 0) {
    process.exitCode = 1;
  }
}

run().catch(async (error) => {
  console.error('FATAL:', error.message || String(error));
  process.exit(1);
});
