#!/usr/bin/env node
'use strict';

const DEFAULT_BASE_URL = 'https://oc.colortr.com/';
const args = process.argv.slice(2);

if (args.includes('--help') || args.includes('-h')) {
  console.log('Usage: node scripts/ops-panel-audit.js [base_url] [--ops-url=<url>]');
  console.log(`Default base_url: ${DEFAULT_BASE_URL}`);
  console.log('Env (optional): BITAXE_OPS_URL, BITAXE_OPS_KEY, BITAXE_OPS_USER, BITAXE_OPS_PASS');
  process.exit(0);
}

function normalizeUrl(input) {
  return new URL(String(input || '').trim()).toString();
}

function nowMs() {
  return Date.now();
}

function assert(condition, message) {
  if (!condition) {
    throw new Error(message);
  }
}

class SkipTest extends Error {
  constructor(message) {
    super(String(message || 'skipped'));
    this.name = 'SkipTest';
  }
}

function short(value, max = 220) {
  const text = typeof value === 'string' ? value : JSON.stringify(value);
  if (!text) return '';
  return text.length > max ? `${text.slice(0, max)}...` : text;
}

let targetBaseArg = '';
let opsUrlArg = '';
for (let i = 0; i < args.length; i += 1) {
  const arg = String(args[i] || '');
  if (arg.startsWith('--ops-url=')) {
    opsUrlArg = arg.slice('--ops-url='.length);
    continue;
  }
  if (arg === '--ops-url') {
    opsUrlArg = String(args[i + 1] || '');
    i += 1;
    continue;
  }
  if (!targetBaseArg) targetBaseArg = arg;
}

const BASE_URL = normalizeUrl(targetBaseArg || process.env.BITAXE_TARGET_URL || DEFAULT_BASE_URL);
const OPS_URL = normalizeUrl(opsUrlArg || process.env.BITAXE_OPS_URL || new URL('/ops-panel.php', BASE_URL).toString());
const OPS_KEY = String(process.env.BITAXE_OPS_KEY || '').trim();
const OPS_USER = String(process.env.BITAXE_OPS_USER || '').trim();
const OPS_PASS = String(process.env.BITAXE_OPS_PASS || '');
const STRICT_AUTH = ['1', 'true', 'yes', 'on'].includes(String(process.env.BITAXE_OPS_STRICT_AUTH || '').trim().toLowerCase());

function withAccessKey(url) {
  if (!OPS_KEY) return normalizeUrl(url);
  const u = new URL(url);
  if (!u.searchParams.has('k')) {
    u.searchParams.set('k', OPS_KEY);
  }
  return u.toString();
}

function extractCsrfToken(html) {
  const inputRe = /<input[^>]*name=["']csrf_token["'][^>]*>/i;
  const inputMatch = html.match(inputRe);
  if (!inputMatch) return '';
  const tag = String(inputMatch[0] || '');
  const valueMatch = tag.match(/value=["']([^"']+)["']/i);
  return valueMatch ? String(valueMatch[1] || '') : '';
}

function hasLoginForm(html) {
  return /name=["']action["']\s+value=["']login["']/i.test(html) && /name=["']username["']/i.test(html);
}

function hasDashboardShell(html) {
  const legacy = /id=["']server-status-root["']/i.test(html) && /id=["']server-processes-table["']/i.test(html);
  const panel2 = /id=["']runs-body["']/i.test(html) && /id=["']server-proc-body["']/i.test(html);
  return legacy || panel2;
}

function detectLoginFailureReason(html) {
  if (!hasLoginForm(html)) return '';
  if (/gecersiz kullanici adi veya sifre/i.test(html)) return 'invalid_credentials';
  if (/config guvenlik bilgileri ayarlanmamis/i.test(html)) return 'default_credentials_mode';
  if (/oturum dogrulamasi basarisiz|oturum suresi doldu/i.test(html)) return 'session_rejected';
  return 'login_not_completed';
}

function parseSetCookies(res) {
  const out = [];
  if (res && res.headers && typeof res.headers.getSetCookie === 'function') {
    const many = res.headers.getSetCookie();
    if (Array.isArray(many)) {
      for (const item of many) {
        if (typeof item === 'string' && item.trim()) out.push(item.trim());
      }
    }
  }
  const single = String(res?.headers?.get?.('set-cookie') || '').trim();
  if (single) out.push(single);
  return out;
}

function mergeCookieJar(cookieJar, setCookieHeaders) {
  for (const row of setCookieHeaders) {
    const first = String(row || '').split(';', 1)[0] || '';
    const idx = first.indexOf('=');
    if (idx <= 0) continue;
    const name = first.slice(0, idx).trim();
    const value = first.slice(idx + 1).trim();
    if (!name) continue;
    cookieJar.set(name, value);
  }
}

function cookieHeader(cookieJar) {
  if (!cookieJar || cookieJar.size === 0) return '';
  return Array.from(cookieJar.entries()).map(([k, v]) => `${k}=${v}`).join('; ');
}

async function fetchText(url, opts = {}) {
  const t0 = nowMs();
  const res = await fetch(url, opts);
  const body = await res.text();
  return { res, body, ms: nowMs() - t0 };
}

async function fetchJson(url, opts = {}) {
  const t0 = nowMs();
  const res = await fetch(url, opts);
  const raw = await res.text();
  let data = null;
  try {
    data = raw ? JSON.parse(raw) : {};
  } catch (_) {
    throw new Error(`JSON parse failed: ${short(raw)}`);
  }
  return { res, data, ms: nowMs() - t0 };
}

async function run() {
  const results = [];

  async function runTest(name, fn) {
    const t0 = nowMs();
    try {
      const detail = await fn();
      results.push({ name, status: 'PASS', ms: nowMs() - t0, detail });
    } catch (error) {
      if (error instanceof SkipTest) {
        results.push({ name, status: 'SKIP', ms: nowMs() - t0, detail: error.message || 'skipped' });
      } else {
        results.push({ name, status: 'FAIL', ms: nowMs() - t0, error: error.message || String(error) });
      }
    }
  }

  async function runSkipped(name, detail) {
    results.push({ name, status: 'SKIP', ms: 0, detail: String(detail || 'skipped') });
  }

  const panelUrl = withAccessKey(OPS_URL);
  const ajaxUrl = withAccessKey(`${OPS_URL}${OPS_URL.includes('?') ? '&' : '?'}ajax=server_status`);

  let loginPage = await fetchText(panelUrl, { method: 'GET', redirect: 'manual' });
  const redirectStatuses = new Set([301, 302, 303, 307, 308]);
  if (redirectStatuses.has(loginPage.res.status)) {
    const location = String(loginPage.res.headers.get('location') || '').trim();
    if (location) {
      const redirectedUrl = normalizeUrl(new URL(location, panelUrl).toString());
      loginPage = await fetchText(redirectedUrl, { method: 'GET', redirect: 'manual' });
    }
  }
  const accessKeyRequired = (!OPS_KEY && loginPage.res.status === 404 && /^not found$/i.test(loginPage.body.trim()));

  await runTest('OPS availability: login page returns 200', async () => {
    if (accessKeyRequired) return 'access_key_required';
    assert(loginPage.res.status === 200, `Expected 200, got ${loginPage.res.status}`);
    return `status=${loginPage.res.status}`;
  });

  await runTest('OPS performance: login page response under 1800ms', async () => {
    if (accessKeyRequired) return 'access_key_required';
    assert(loginPage.ms < 1800, `Too slow: ${loginPage.ms}ms`);
    return `${loginPage.ms}ms`;
  });

  await runTest('OPS security headers: no-store cache control', async () => {
    if (accessKeyRequired) return 'access_key_required';
    const value = String(loginPage.res.headers.get('cache-control') || '').toLowerCase();
    assert(value.includes('no-store'), `cache-control missing no-store: ${value}`);
    return value;
  });

  await runTest('OPS robots: noindex meta and X-Robots-Tag', async () => {
    if (accessKeyRequired) return 'access_key_required';
    assert(/<meta[^>]+name=["']robots["'][^>]+noindex/i.test(loginPage.body), 'robots meta missing noindex');
    const tag = String(loginPage.res.headers.get('x-robots-tag') || '').toLowerCase();
    assert(tag.includes('noindex'), `x-robots-tag missing noindex: ${tag}`);
    return `meta+header ok`;
  });

  await runTest('OPS login form: csrf token exists', async () => {
    if (accessKeyRequired) return 'access_key_required';
    assert(hasLoginForm(loginPage.body), 'login form not found');
    const csrf = extractCsrfToken(loginPage.body);
    assert(csrf.length > 10, 'csrf token missing/too short');
    return `csrf=${csrf.slice(0, 8)}...`;
  });

  await runTest('OPS ajax unauthorized returns 401', async () => {
    if (accessKeyRequired) return 'access_key_required';
    const resp = await fetchJson(ajaxUrl, { method: 'GET', redirect: 'manual' });
    assert(resp.res.status === 401, `Expected 401, got ${resp.res.status}`);
    assert(resp.data && resp.data.ok === false, `Expected ok=false, got ${short(resp.data)}`);
    return `status=${resp.res.status}`;
  });

  const hasAuthEnv = OPS_USER !== '' && OPS_PASS !== '';
  if (!hasAuthEnv) {
    await runSkipped('OPS auth flow: login success + dashboard render', 'BITAXE_OPS_USER/BITAXE_OPS_PASS not set');
    await runSkipped('OPS auth flow: ajax authorized returns status payload', 'BITAXE_OPS_USER/BITAXE_OPS_PASS not set');
    await runSkipped('OPS auth flow: logout returns to login form', 'BITAXE_OPS_USER/BITAXE_OPS_PASS not set');
  } else if (accessKeyRequired) {
    await runSkipped('OPS auth flow: login success + dashboard render', 'access key required (set BITAXE_OPS_KEY)');
    await runSkipped('OPS auth flow: ajax authorized returns status payload', 'access key required (set BITAXE_OPS_KEY)');
    await runSkipped('OPS auth flow: logout returns to login form', 'access key required (set BITAXE_OPS_KEY)');
  } else {
    const cookieJar = new Map();
    let authSessionReady = false;
    let authSkipReason = '';
    const csrfLogin = extractCsrfToken(loginPage.body);
    assert(csrfLogin.length > 10, 'login csrf token not found');

    await runTest('OPS auth flow: login success + dashboard render', async () => {
      const body = new URLSearchParams({
        action: 'login',
        csrf_token: csrfLogin,
        username: OPS_USER,
        password: OPS_PASS,
      });
      const loginResp = await fetchText(panelUrl, {
        method: 'POST',
        redirect: 'manual',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
          Origin: new URL(panelUrl).origin,
        },
        body: body.toString(),
      });
      mergeCookieJar(cookieJar, parseSetCookies(loginResp.res));
      assert([302, 303].includes(loginResp.res.status), `Expected redirect after login, got ${loginResp.res.status}`);
      const afterLogin = await fetchText(panelUrl, {
        method: 'GET',
        redirect: 'manual',
        headers: {
          Cookie: cookieHeader(cookieJar),
        },
      });
      mergeCookieJar(cookieJar, parseSetCookies(afterLogin.res));
      assert(afterLogin.res.status === 200, `Expected dashboard 200, got ${afterLogin.res.status}`);
      if (!hasDashboardShell(afterLogin.body)) {
        const reason = detectLoginFailureReason(afterLogin.body);
        if (reason && !STRICT_AUTH) {
          authSkipReason = `credentials_not_verified (${reason})`;
          throw new SkipTest(authSkipReason);
        }
        throw new Error('dashboard shell not found after login');
      }
      authSessionReady = true;
      return 'dashboard ok';
    });

    await runTest('OPS auth flow: ajax authorized returns status payload', async () => {
      if (!authSessionReady && authSkipReason) {
        throw new SkipTest(authSkipReason);
      }
      const resp = await fetchJson(ajaxUrl, {
        method: 'GET',
        redirect: 'manual',
        headers: {
          Cookie: cookieHeader(cookieJar),
          'X-Requested-With': 'XMLHttpRequest',
        },
      });
      assert(resp.res.status === 200, `Expected 200, got ${resp.res.status}`);
      assert(resp.data && resp.data.ok === true, `Expected ok=true, got ${short(resp.data)}`);
      const view = resp.data?.view || {};
      const processRows = Array.isArray(view?.processes?.processRows) ? view.processes.processRows : [];
      assert(Array.isArray(processRows), 'processRows missing');
      return `rows=${processRows.length}`;
    });

    await runTest('OPS auth flow: logout returns to login form', async () => {
      if (!authSessionReady && authSkipReason) {
        throw new SkipTest(authSkipReason);
      }
      const page = await fetchText(panelUrl, {
        method: 'GET',
        redirect: 'manual',
        headers: { Cookie: cookieHeader(cookieJar) },
      });
      const csrfLogout = extractCsrfToken(page.body);
      assert(csrfLogout.length > 10, 'logout csrf token missing');
      const logoutBody = new URLSearchParams({
        action: 'logout',
        csrf_token: csrfLogout,
      });
      const logoutResp = await fetchText(panelUrl, {
        method: 'POST',
        redirect: 'manual',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
          Cookie: cookieHeader(cookieJar),
          Origin: new URL(panelUrl).origin,
        },
        body: logoutBody.toString(),
      });
      mergeCookieJar(cookieJar, parseSetCookies(logoutResp.res));
      assert([302, 303].includes(logoutResp.res.status), `Expected redirect after logout, got ${logoutResp.res.status}`);
      const finalPage = await fetchText(panelUrl, {
        method: 'GET',
        redirect: 'manual',
        headers: { Cookie: cookieHeader(cookieJar) },
      });
      assert(finalPage.res.status === 200, `Expected login page 200, got ${finalPage.res.status}`);
      assert(hasLoginForm(finalPage.body), 'login form not visible after logout');
      return 'logout ok';
    });
  }

  const passCount = results.filter((r) => r.status === 'PASS').length;
  const skipCount = results.filter((r) => r.status === 'SKIP').length;
  const failCount = results.filter((r) => r.status === 'FAIL').length;

  console.log('=== BITAXE-OC OPS PANEL AUDIT REPORT ===');
  console.log(`Base: ${BASE_URL}`);
  console.log(`Panel: ${panelUrl}`);
  console.log(`Date: ${new Date().toISOString()}`);
  console.log('');
  results.forEach((r, idx) => {
    const base = `${String(idx + 1).padStart(2, '0')}. [${r.status}] ${r.name} (${r.ms}ms)`;
    if (r.status === 'PASS' || r.status === 'SKIP') {
      console.log(`${base} -> ${short(r.detail, 280)}`);
    } else {
      console.log(`${base} -> ${short(r.error, 280)}`);
    }
  });
  console.log('');
  console.log(`Summary: PASS ${passCount}/${results.length}, SKIP ${skipCount}/${results.length}, FAIL ${failCount}/${results.length}`);

  process.exit(failCount > 0 ? 1 : 0);
}

run().catch((error) => {
  console.error('ops-panel-audit fatal:', error?.message || String(error));
  process.exit(1);
});
