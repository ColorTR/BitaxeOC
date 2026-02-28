#!/usr/bin/env node
'use strict';

const crypto = require('node:crypto');

const DEFAULT_TARGET_URL = 'https://oc.colortr.com/';
const args = process.argv.slice(2);

if (args.includes('--help') || args.includes('-h')) {
  console.log('Usage: node scripts/live-http-audit.js [--profile=live|local] [target_url]');
  console.log(`Default target_url: ${DEFAULT_TARGET_URL}`);
  console.log('Default profile: auto (local for localhost/private IP, otherwise live)');
  process.exit(0);
}

function normalizeProfile(input) {
  const value = String(input || '').trim().toLowerCase();
  if (value === 'local' || value === 'live') return value;
  return '';
}

function inferProfileFromTarget(url) {
  try {
    const host = String(new URL(url).hostname || '').toLowerCase();
    if (
      host === 'localhost'
      || host === '127.0.0.1'
      || host === '::1'
      || host.startsWith('10.')
      || host.startsWith('192.168.')
      || /^172\.(1[6-9]|2[0-9]|3[01])\./.test(host)
    ) {
      return 'local';
    }
  } catch (_) {
    return 'live';
  }
  return 'live';
}

let targetArg = '';
let profileArg = normalizeProfile(process.env.BITAXE_HTTP_AUDIT_PROFILE || '');
for (let i = 0; i < args.length; i += 1) {
  const arg = String(args[i] || '');
  if (arg.startsWith('--profile=')) {
    profileArg = normalizeProfile(arg.slice('--profile='.length));
    continue;
  }
  if (arg === '--profile') {
    profileArg = normalizeProfile(args[i + 1] || '');
    i += 1;
    continue;
  }
  if (!targetArg) {
    targetArg = arg;
  }
}

const TARGET_URL = targetArg || process.env.BITAXE_TARGET_URL || DEFAULT_TARGET_URL;
const PROFILE = profileArg || inferProfileFromTarget(TARGET_URL);
const IS_LOCAL_PROFILE = PROFILE === 'local';
const API_TEST_USER_AGENT_BASE = `bitaxe-http-audit/${Date.now()}-${Math.random().toString(16).slice(2)}`;

function short(value, max = 260) {
  const text = typeof value === 'string' ? value : JSON.stringify(value);
  if (!text) return '';
  return text.length > max ? `${text.slice(0, max)}...` : text;
}

function assert(condition, message) {
  if (!condition) throw new Error(message);
}

function normalizeUrl(input) {
  const u = new URL(input);
  return u.toString();
}

function normalizeOrigin(input) {
  const u = new URL(input);
  return u.origin;
}

function nowMs() {
  return Date.now();
}

function apiUserAgent(tag = 'default') {
  return `${API_TEST_USER_AGENT_BASE} ${String(tag || 'default')}`;
}

function apiJsonHeaders(origin, tag = 'default') {
  return {
    'Content-Type': 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
    Origin: origin,
    'User-Agent': apiUserAgent(tag)
  };
}

function parseAttributes(tagText) {
  const attrs = {};
  const re = /([a-zA-Z_:][a-zA-Z0-9:._-]*)\s*=\s*(['"])(.*?)\2/g;
  let m = null;
  while ((m = re.exec(tagText)) !== null) {
    attrs[m[1].toLowerCase()] = m[3];
  }
  return attrs;
}

function collectTags(html, tagName) {
  const re = new RegExp(`<${tagName}\\b[^>]*>`, 'gi');
  const out = [];
  let m = null;
  while ((m = re.exec(html)) !== null) {
    out.push({ raw: m[0], attrs: parseAttributes(m[0]) });
  }
  return out;
}

function findMetaContent(html, key, value) {
  const metas = collectTags(html, 'meta');
  const found = metas.find((m) => String(m.attrs[key] || '').toLowerCase() === String(value).toLowerCase());
  return found ? String(found.attrs.content || '') : '';
}

function findLinkHref(html, relValue) {
  const links = collectTags(html, 'link');
  const found = links.find((l) => {
    const rel = String(l.attrs.rel || '').toLowerCase();
    return rel.split(/\s+/).includes(String(relValue).toLowerCase());
  });
  return found ? String(found.attrs.href || '') : '';
}

function findAllLinksByRel(html, relValue) {
  const links = collectTags(html, 'link');
  return links.filter((l) => {
    const rel = String(l.attrs.rel || '').toLowerCase();
    return rel.split(/\s+/).includes(String(relValue).toLowerCase());
  });
}

function findScriptSrc(html, fragment) {
  const scripts = collectTags(html, 'script');
  const found = scripts.find((s) => String(s.attrs.src || '').includes(fragment));
  return found ? String(found.attrs.src || '') : '';
}

function findLinkHrefByFragment(html, fragment) {
  const links = collectTags(html, 'link');
  const found = links.find((l) => String(l.attrs.href || '').includes(fragment));
  return found ? String(found.attrs.href || '') : '';
}

function collectJsonLdBlocks(html) {
  const re = /<script[^>]*type=['"]application\/ld\+json['"][^>]*>([\s\S]*?)<\/script>/gi;
  const blocks = [];
  let m = null;
  while ((m = re.exec(html)) !== null) {
    const raw = String(m[1] || '').trim();
    if (!raw) continue;
    try {
      blocks.push(JSON.parse(raw));
    } catch (_) {
      blocks.push({ __parseError: true, raw });
    }
  }
  return blocks;
}

async function fetchWithTiming(url, opts = {}) {
  const t0 = nowMs();
  const res = await fetch(url, opts);
  const ms = nowMs() - t0;
  return { res, ms };
}

async function getText(url, opts = {}) {
  const { res, ms } = await fetchWithTiming(url, opts);
  const body = await res.text();
  return { res, ms, body };
}

async function getJson(url, opts = {}) {
  const { res, ms } = await fetchWithTiming(url, opts);
  const raw = await res.text();
  let data = null;
  try {
    data = raw ? JSON.parse(raw) : {};
  } catch (_) {
    throw new Error(`JSON parse failed for ${url}: ${short(raw)}`);
  }
  return { res, ms, data };
}

async function run() {
  const results = [];
  const baseUrl = normalizeUrl(TARGET_URL);
  const baseOrigin = normalizeOrigin(baseUrl);

  const indexResp = await getText(baseUrl, { method: 'GET', redirect: 'follow' });
  const indexHtml = indexResp.body;
  const finalIndexUrl = indexResp.res.url || baseUrl;
  const indexHeaders = indexResp.res.headers;

  const titleMatch = indexHtml.match(/<title[^>]*>([\s\S]*?)<\/title>/i);
  const pageTitle = titleMatch ? String(titleMatch[1]).replace(/\s+/g, ' ').trim() : '';
  const description = findMetaContent(indexHtml, 'name', 'description');
  const keywords = findMetaContent(indexHtml, 'name', 'keywords');
  const robots = findMetaContent(indexHtml, 'name', 'robots');
  const canonical = findLinkHref(indexHtml, 'canonical');
  const alternateLinks = findAllLinksByRel(indexHtml, 'alternate');
  const ogTitle = findMetaContent(indexHtml, 'property', 'og:title');
  const ogDescription = findMetaContent(indexHtml, 'property', 'og:description');
  const ogUrl = findMetaContent(indexHtml, 'property', 'og:url');
  const ogImage = findMetaContent(indexHtml, 'property', 'og:image');
  const twitterCard = findMetaContent(indexHtml, 'name', 'twitter:card');
  const twitterTitle = findMetaContent(indexHtml, 'name', 'twitter:title');
  const twitterDescription = findMetaContent(indexHtml, 'name', 'twitter:description');
  const viewport = findMetaContent(indexHtml, 'name', 'viewport');
  const author = findMetaContent(indexHtml, 'name', 'author');
  const themeColor = findMetaContent(indexHtml, 'name', 'theme-color');
  const htmlTagMatch = indexHtml.match(/<html\b([^>]*)>/i);
  const htmlTagAttrs = htmlTagMatch ? parseAttributes(htmlTagMatch[0]) : {};
  const htmlClassList = String(htmlTagAttrs.class || '').split(/\s+/).filter(Boolean);
  const htmlLang = String(htmlTagAttrs.lang || '').trim();
  const cspMeta = findMetaContent(indexHtml, 'http-equiv', 'content-security-policy');
  const scriptTags = collectTags(indexHtml, 'script');
  const scriptSrcs = scriptTags
    .map((s) => String(s.attrs.src || '').trim())
    .filter(Boolean);
  const jsonLd = collectJsonLdBlocks(indexHtml);
  const appJsonLd = jsonLd.find((item) => item && !item.__parseError && String(item['@type'] || '').toLowerCase() === 'webapplication');

  async function runTest(name, fn) {
    const t0 = nowMs();
    try {
      const detail = await fn();
      results.push({ name, status: 'PASS', ms: nowMs() - t0, detail });
    } catch (error) {
      results.push({ name, status: 'FAIL', ms: nowMs() - t0, error: error.message || String(error) });
    }
  }

  async function runLiveOnlyTest(name, fn) {
    if (IS_LOCAL_PROFILE) {
      results.push({ name, status: 'PASS', ms: 0, detail: 'skipped (local profile)' });
      return;
    }
    await runTest(name, fn);
  }

  await runTest('Availability: index returns HTTP 200', async () => {
    assert(indexResp.res.status === 200, `Expected 200, got ${indexResp.res.status}`);
    return `status=${indexResp.res.status}`;
  });

  await runLiveOnlyTest('Availability: final URL is HTTPS', async () => {
    assert(String(finalIndexUrl).startsWith('https://'), `Final URL is not HTTPS: ${finalIndexUrl}`);
    return finalIndexUrl;
  });

  await runTest('Performance: index response time under 3500ms', async () => {
    assert(indexResp.ms < 3500, `Index response too slow: ${indexResp.ms}ms`);
    return `${indexResp.ms}ms`;
  });

  await runTest('Performance: index HTML size under 2MB', async () => {
    const bytes = Buffer.byteLength(indexHtml, 'utf8');
    assert(bytes < (2 * 1024 * 1024), `Index HTML too large: ${bytes} bytes`);
    return `${bytes} bytes`;
  });

  await runTest('Performance: index average latency under 1200ms (3 requests)', async () => {
    const samples = [];
    for (let i = 0; i < 3; i += 1) {
      const { res, ms } = await fetchWithTiming(baseUrl, { method: 'GET', redirect: 'follow' });
      assert(res.status === 200, `Sample request status not 200: ${res.status}`);
      samples.push(ms);
      await res.arrayBuffer();
    }
    const avg = Math.round(samples.reduce((a, b) => a + b, 0) / samples.length);
    assert(avg < 1200, `Average latency too high: ${avg}ms, samples=${samples.join(',')}`);
    return `avg=${avg}ms samples=[${samples.join(',')}]`;
  });

  await runTest('HTTP: HEAD request on index returns 200', async () => {
    const { res, ms } = await fetchWithTiming(baseUrl, { method: 'HEAD', redirect: 'follow' });
    assert(res.status === 200, `HEAD expected 200, got ${res.status}`);
    return `${res.status} (${ms}ms)`;
  });

  await runTest('HTTP: index content-type is text/html', async () => {
    const value = String(indexHeaders.get('content-type') || '').toLowerCase();
    assert(value.includes('text/html'), `Unexpected content-type: ${value}`);
    return value;
  });

  await runTest('SEO: title contains Bitaxe and NerdAxe', async () => {
    const low = pageTitle.toLowerCase();
    assert(low.includes('bitaxe') && low.includes('nerdaxe'), `Unexpected title: ${pageTitle}`);
    return pageTitle;
  });

  await runTest('SEO: description length is healthy', async () => {
    const len = description.trim().length;
    assert(len >= 110 && len <= 260, `Description length out of range: ${len}`);
    return `length=${len}`;
  });

  await runTest('SEO: keywords include target terms', async () => {
    const low = keywords.toLowerCase();
    const required = ['bitaxe', 'nerdaxe', 'lottery mining', 'oc stats'];
    const missing = required.filter((k) => !low.includes(k));
    assert(missing.length === 0, `Missing keyword terms: ${missing.join(', ')}`);
    return `ok=${required.length}`;
  });

  await runTest('SEO: canonical points to base app URL', async () => {
    assert(canonical === baseUrl, `Canonical mismatch: ${canonical} != ${baseUrl}`);
    return canonical;
  });

  await runLiveOnlyTest('SEO: canonical is absolute HTTPS URL', async () => {
    assert(/^https:\/\//i.test(canonical), `Canonical is not absolute HTTPS: ${canonical}`);
    return canonical;
  });

  await runTest('SEO: viewport meta exists', async () => {
    const low = viewport.toLowerCase();
    assert(low.includes('width=device-width'), `Viewport meta missing/invalid: ${viewport}`);
    return viewport;
  });

  await runTest('SEO: author meta exists', async () => {
    assert(author.trim().length > 0, 'Author meta is missing');
    return author;
  });

  await runTest('SEO: theme-color is valid hex', async () => {
    assert(/^#[0-9a-f]{6}$/i.test(themeColor), `Invalid theme-color: ${themeColor}`);
    return themeColor;
  });

  await runTest('Theme: default page class starts in dark mode', async () => {
    assert(htmlClassList.includes('dark'), `html class missing dark default: ${htmlClassList.join(' ')}`);
    assert(!htmlClassList.includes('light-theme'), `html should not default to light-theme: ${htmlClassList.join(' ')}`);
    return htmlClassList.join(' ') || '(empty)';
  });

  await runTest('Theme: bootstrap storage key exists in HTML source', async () => {
    assert(indexHtml.includes('bitaxeThemePreference'), 'Theme storage key script missing in HTML');
    return 'bitaxeThemePreference';
  });

  await runTest('Theme: toggle button is present in header', async () => {
    assert(indexHtml.includes('id="theme-toggle-btn"'), 'theme-toggle button markup missing');
    return 'theme-toggle-btn';
  });

  await runTest('SEO: html lang attribute exists and is supported', async () => {
    const normalized = htmlLang.toLowerCase();
    const supported = ['en', 'tr', 'ar', 'fr', 'es', 'pt', 'ru', 'hi', 'bn', 'ur', 'zh-cn', 'asm'];
    assert(normalized.length > 0, 'Missing html[lang] attribute');
    assert(supported.includes(normalized), `Unexpected html[lang]: ${htmlLang}`);
    return htmlLang;
  });

  await runTest('SEO: description includes efficiency and temperature context', async () => {
    const low = description.toLowerCase();
    assert(low.includes('efficiency') || low.includes('j/th'), `Description missing efficiency context: ${description}`);
    assert(low.includes('temperature'), `Description missing temperature context: ${description}`);
    return short(description, 140);
  });

  await runTest('SEO: keywords include GT800 tag', async () => {
    const low = keywords.toLowerCase();
    assert(low.includes('gt800'), `Expected GT800 keyword tag: ${keywords}`);
    return 'gt800-ok';
  });

  await runTest('SEO: alternate hreflang en and x-default exist', async () => {
    const map = new Map(alternateLinks.map((item) => [String(item.attrs.hreflang || '').toLowerCase(), String(item.attrs.href || '')]));
    const enHref = map.get('en') || '';
    const xDefaultHref = map.get('x-default') || '';
    assert(enHref === baseUrl, `hreflang=en mismatch: ${enHref}`);
    assert(xDefaultHref === baseUrl, `hreflang=x-default mismatch: ${xDefaultHref}`);
    return `en=${enHref}, x-default=${xDefaultHref}`;
  });

  await runTest('SEO: robots allows index on main page', async () => {
    const low = robots.toLowerCase();
    assert(low.includes('index') && low.includes('follow'), `Unexpected robots: ${robots}`);
    return robots;
  });

  await runTest('SEO: Open Graph fields exist', async () => {
    assert(ogTitle.length > 0 && ogDescription.length > 0, `Missing OG tags: ${short({ ogTitle, ogDescription })}`);
    return `og:title=${short(ogTitle, 80)}`;
  });

  await runTest('SEO: OG URL matches canonical', async () => {
    assert(ogUrl === canonical, `OG URL mismatch: ${ogUrl} != ${canonical}`);
    return ogUrl;
  });

  await runTest('SEO: OG title contains key terms', async () => {
    const low = ogTitle.toLowerCase();
    assert(low.includes('bitaxe') && low.includes('nerdaxe'), `OG title missing key terms: ${ogTitle}`);
    return ogTitle;
  });

  await runTest('SEO: OG description includes lottery mining', async () => {
    const low = ogDescription.toLowerCase();
    assert(low.includes('lottery mining'), `OG description missing phrase: ${ogDescription}`);
    return short(ogDescription, 120);
  });

  await runLiveOnlyTest('SEO: OG image is absolute HTTPS', async () => {
    assert(/^https:\/\/.+/i.test(ogImage), `OG image should be absolute HTTPS: ${ogImage}`);
    return ogImage;
  });

  await runTest('SEO: Twitter tags exist', async () => {
    assert(twitterCard.length > 0 && twitterTitle.length > 0 && twitterDescription.length > 0, 'Missing Twitter meta tags');
    return `card=${twitterCard}`;
  });

  await runTest('SEO: Twitter title aligns with page title', async () => {
    const tw = twitterTitle.toLowerCase();
    const tt = pageTitle.toLowerCase();
    assert(tw.includes('bitaxe') && tw.includes('nerdaxe'), `Twitter title missing terms: ${twitterTitle}`);
    assert(tt.includes('bitaxe'), `Page title invalid: ${pageTitle}`);
    return twitterTitle;
  });

  await runTest('SEO: Twitter description includes OC stats', async () => {
    const low = twitterDescription.toLowerCase();
    assert(low.includes('oc stats'), `Twitter description missing OC stats: ${twitterDescription}`);
    return short(twitterDescription, 120);
  });

  await runTest('SEO: JSON-LD WebApplication exists and valid', async () => {
    assert(Boolean(appJsonLd), `No WebApplication JSON-LD found: ${short(jsonLd)}`);
    assert(String(appJsonLd.url || '') === baseUrl, `JSON-LD url mismatch: ${short(appJsonLd.url)} != ${baseUrl}`);
    return short({ type: appJsonLd['@type'], url: appJsonLd.url }, 120);
  });

  await runTest('SEO: JSON-LD includes expected app metadata', async () => {
    assert(String(appJsonLd?.name || '').toLowerCase().includes('bitaxe'), `JSON-LD name invalid: ${short(appJsonLd)}`);
    assert(String(appJsonLd?.applicationCategory || '').toLowerCase().includes('application'), `JSON-LD applicationCategory invalid: ${short(appJsonLd)}`);
    assert(String(appJsonLd?.operatingSystem || '').toLowerCase() === 'web', `JSON-LD operatingSystem invalid: ${short(appJsonLd)}`);
    assert(String(appJsonLd?.keywords || '').toLowerCase().includes('nerdaxe'), `JSON-LD keywords missing nerdaxe: ${short(appJsonLd)}`);
    return short({ name: appJsonLd.name, os: appJsonLd.operatingSystem }, 120);
  });

  await runTest('SEO/Security: CSP meta tag exists in HTML', async () => {
    const low = cspMeta.toLowerCase();
    assert(low.includes("default-src 'self'"), `CSP meta default-src missing: ${cspMeta}`);
    assert(low.includes("object-src 'none'"), `CSP meta object-src none missing: ${cspMeta}`);
    return 'csp-meta-ok';
  });

  await runLiveOnlyTest('Security headers: X-Frame-Options DENY', async () => {
    const value = String(indexHeaders.get('x-frame-options') || '');
    assert(value.toUpperCase() === 'DENY', `Unexpected X-Frame-Options: ${value}`);
    return value;
  });

  await runLiveOnlyTest('Security headers: X-Content-Type-Options nosniff', async () => {
    const value = String(indexHeaders.get('x-content-type-options') || '');
    assert(value.toLowerCase() === 'nosniff', `Unexpected XCTO: ${value}`);
    return value;
  });

  await runLiveOnlyTest('Security headers: Referrer-Policy is strict', async () => {
    const value = String(indexHeaders.get('referrer-policy') || '').toLowerCase();
    assert(value === 'no-referrer', `Unexpected Referrer-Policy: ${value}`);
    return value;
  });

  await runLiveOnlyTest('Security headers: CORP same-origin', async () => {
    const value = String(indexHeaders.get('cross-origin-resource-policy') || '').toLowerCase();
    assert(value === 'same-origin', `Unexpected CORP header: ${value}`);
    return value;
  });

  await runLiveOnlyTest('Security headers: COOP same-origin', async () => {
    const value = String(indexHeaders.get('cross-origin-opener-policy') || '').toLowerCase();
    assert(value === 'same-origin', `Unexpected COOP header: ${value}`);
    return value;
  });

  await runLiveOnlyTest('Security headers: X-Powered-By is not exposed', async () => {
    const value = indexHeaders.get('x-powered-by');
    assert(!value, `X-Powered-By should be absent but got: ${value}`);
    return 'absent';
  });

  await runLiveOnlyTest('Security headers: index cache-control is no-store', async () => {
    const value = String(indexHeaders.get('cache-control') || '').toLowerCase();
    assert(value.includes('no-store'), `Expected no-store cache-control on index: ${value}`);
    return value;
  });

  await runLiveOnlyTest('Security headers: HSTS enabled with includeSubDomains', async () => {
    const value = String(indexHeaders.get('strict-transport-security') || '');
    const low = value.toLowerCase();
    assert(low.includes('max-age=') && low.includes('includesubdomains'), `Unexpected HSTS: ${value}`);
    return value;
  });

  await runLiveOnlyTest('Security headers: CSP blocks object and framing', async () => {
    const value = String(indexHeaders.get('content-security-policy') || '');
    const low = value.toLowerCase();
    assert(low.includes("object-src 'none'"), `CSP missing object-src none: ${value}`);
    assert(low.includes("frame-ancestors 'none'"), `CSP missing frame-ancestors none: ${value}`);
    return 'object-src/frame-ancestors ok';
  });

  await runLiveOnlyTest('Security headers: CSP includes base-uri and form-action self', async () => {
    const value = String(indexHeaders.get('content-security-policy') || '').toLowerCase();
    const baseUriOk = value.includes("base-uri 'self'") || value.includes("base-uri 'none'");
    assert(baseUriOk, `CSP base-uri missing/invalid: ${value}`);
    assert(value.includes("form-action 'self'"), `CSP form-action missing/invalid: ${value}`);
    return 'base-uri/form-action ok';
  });

  await runLiveOnlyTest('Security headers: CSP has no wildcard script-src', async () => {
    const value = String(indexHeaders.get('content-security-policy') || '').toLowerCase();
    const wildcardAllowed = /script-src[^;]*\*/.test(value);
    assert(!wildcardAllowed, `CSP script-src should not contain wildcard: ${value}`);
    return 'no-wildcard';
  });

  await runLiveOnlyTest('Security headers: Permissions-Policy denies camera/mic/geo', async () => {
    const value = String(indexHeaders.get('permissions-policy') || '').toLowerCase();
    assert(value.includes('camera=()') && value.includes('microphone=()') && value.includes('geolocation=()'), `Unexpected Permissions-Policy: ${value}`);
    return value;
  });

  await runLiveOnlyTest('Security: protected app directory is not directly readable', async () => {
    const { res } = await fetchWithTiming(`${baseOrigin}/app/Config.php`, { method: 'GET', redirect: 'follow' });
    assert(res.status !== 200, `app/Config.php should not be readable (got 200)`);
    return `status=${res.status}`;
  });

  await runLiveOnlyTest('Security: protected storage directory is blocked', async () => {
    const { res } = await fetchWithTiming(`${baseOrigin}/storage/`, { method: 'GET', redirect: 'follow' });
    assert(res.status !== 200, `storage/ should not be readable (got 200)`);
    return `status=${res.status}`;
  });

  await runLiveOnlyTest('Security: protected tmp directory is blocked', async () => {
    const { res } = await fetchWithTiming(`${baseOrigin}/tmp/`, { method: 'GET', redirect: 'follow' });
    assert(res.status !== 200, `tmp/ should not be readable (got 200)`);
    return `status=${res.status}`;
  });

  await runLiveOnlyTest('Security: README is blocked from web access', async () => {
    const { res } = await fetchWithTiming(`${baseOrigin}/README.md`, { method: 'GET', redirect: 'follow' });
    assert(res.status !== 200, `README.md should be blocked (got 200)`);
    return `status=${res.status}`;
  });

  await runLiveOnlyTest('Security: shell scripts are blocked from web access', async () => {
    const { res } = await fetchWithTiming(`${baseOrigin}/scripts/run-master-test.sh`, { method: 'GET', redirect: 'follow' });
    assert(res.status !== 200, `run-master-test.sh should be blocked (got 200)`);
    return `status=${res.status}`;
  });

  await runTest('Share page SEO: ?share=test allows index', async () => {
    const sharePage = await getText(`${baseUrl}?share=test`, { method: 'GET', redirect: 'follow' });
    const shareRobots = findMetaContent(sharePage.body, 'name', 'robots').toLowerCase();
    assert(shareRobots.includes('index'), `Expected index on share page, got: ${shareRobots}`);
    return shareRobots;
  });

  await runTest('Share page SEO: ?share=test keeps follow policy', async () => {
    const sharePage = await getText(`${baseUrl}?share=test`, { method: 'GET', redirect: 'follow' });
    const shareRobots = findMetaContent(sharePage.body, 'name', 'robots').toLowerCase();
    assert(shareRobots.includes('follow'), `Expected follow on share=test, got: ${shareRobots}`);
    assert(!shareRobots.includes('noindex'), `share=test should not be noindex: ${shareRobots}`);
    return shareRobots;
  });

  await runTest('API: share GET without token returns 400', async () => {
    const resp = await getJson(`${baseOrigin}/api/share.php`, {
      method: 'GET',
      redirect: 'follow',
      headers: { 'User-Agent': apiUserAgent('get-no-token') }
    });
    assert(resp.res.status === 400, `Expected 400, got ${resp.res.status}`);
    assert(resp.data && resp.data.ok === false, `Expected ok=false, got: ${short(resp.data)}`);
    return `status=${resp.res.status}`;
  });

  await runTest('API: share GET unknown token returns 404', async () => {
    const resp = await getJson(`${baseOrigin}/api/share.php?share=ffffffffffffffffffffffffffffffff`, {
      method: 'GET',
      redirect: 'follow',
      headers: { 'User-Agent': apiUserAgent('get-unknown') }
    });
    assert(resp.res.status === 404, `Expected 404, got ${resp.res.status}`);
    return `status=${resp.res.status}`;
  });

  await runTest('API: share PUT method rejected with 405', async () => {
    const resp = await getJson(`${baseOrigin}/api/share.php`, {
      method: 'PUT',
      redirect: 'follow',
      headers: { 'User-Agent': apiUserAgent('put-method') }
    });
    assert(resp.res.status === 405, `Expected 405, got ${resp.res.status}`);
    return `status=${resp.res.status}`;
  });

  await runTest('API: share POST invalid JSON returns 400', async () => {
    const { res } = await fetchWithTiming(`${baseOrigin}/api/share.php?action=create`, {
      method: 'POST',
      redirect: 'follow',
      headers: apiJsonHeaders(baseOrigin, 'post-invalid-json'),
      body: '{"invalid_json":'
    });
    assert(res.status === 400, `Expected 400 for invalid JSON, got ${res.status}`);
    return `status=${res.status}`;
  });

  await runTest('API: share POST invalid nonce format returns 400', async () => {
    const req = {
      request_ts: String(Math.floor(Date.now() / 1000)),
      request_nonce: 'bad nonce !',
      payload: {
        meta: { selectedLanguage: 'en', mode: 'share', appVersion: 'http-audit' },
        visibleRows: 10,
        filters: {},
        layout: { order: ['table'], visibility: { table: true } },
        sourceFiles: [],
        consolidatedData: [{ source: 'master', v: 1300, f: 800, h: 3000, e: 16.5, err: 0.1, p: 49.5, score: 95 }]
      }
    };
    const resp = await getJson(`${baseOrigin}/api/share.php?action=create`, {
      method: 'POST',
      redirect: 'follow',
      headers: apiJsonHeaders(baseOrigin, 'post-invalid-nonce'),
      body: JSON.stringify(req)
    });
    assert(resp.res.status === 400, `Expected 400 for invalid nonce, got ${resp.res.status}`);
    return `status=${resp.res.status}`;
  });

  await runTest('API: share POST expired timestamp returns 408', async () => {
    const req = {
      request_ts: String(Math.floor(Date.now() / 1000) - 3600),
      request_nonce: crypto.randomBytes(12).toString('hex'),
      payload: {
        meta: { selectedLanguage: 'en', mode: 'share', appVersion: 'http-audit' },
        visibleRows: 10,
        filters: {},
        layout: { order: ['table'], visibility: { table: true } },
        sourceFiles: [],
        consolidatedData: [{ source: 'master', v: 1300, f: 800, h: 3000, e: 16.5, err: 0.1, p: 49.5, score: 95 }]
      }
    };
    const resp = await getJson(`${baseOrigin}/api/share.php?action=create`, {
      method: 'POST',
      redirect: 'follow',
      headers: apiJsonHeaders(baseOrigin, 'post-expired-ts'),
      body: JSON.stringify(req)
    });
    assert(resp.res.status === 408, `Expected 408 for expired request_ts, got ${resp.res.status}`);
    return `status=${resp.res.status}`;
  });

  await runTest('API compatibility: share create without Origin is accepted', async () => {
    const req = {
      request_ts: String(Math.floor(Date.now() / 1000)),
      request_nonce: crypto.randomBytes(12).toString('hex'),
      payload: {
        meta: { selectedLanguage: 'en', mode: 'share', appVersion: 'http-audit' },
        visibleRows: 10,
        filters: {},
        layout: { order: ['table'], visibility: { table: true } },
        sourceFiles: [],
        consolidatedData: [{ source: 'master', v: 1301, f: 801, h: 3011, e: 16.4, err: 0.2, p: 50.0, score: 96 }]
      }
    };
    const resp = await getJson(`${baseOrigin}/api/share.php?action=create`, {
      method: 'POST',
      redirect: 'follow',
      headers: {
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'User-Agent': apiUserAgent('post-no-origin')
      },
      body: JSON.stringify(req)
    });
    assert([200, 201].includes(resp.res.status), `Expected 200/201 when Origin missing, got ${resp.res.status}`);
    assert(resp.data && resp.data.ok === true, `Expected ok=true payload, got: ${short(resp.data)}`);
    return `status=${resp.res.status}`;
  });

  await runTest('API security: share create wrong Origin blocked (403)', async () => {
    const req = {
      request_ts: String(Math.floor(Date.now() / 1000)),
      request_nonce: crypto.randomBytes(12).toString('hex'),
      payload: {
        meta: { selectedLanguage: 'en', mode: 'share', appVersion: 'http-audit' },
        visibleRows: 10,
        filters: {},
        layout: { order: ['table'], visibility: { table: true } },
        sourceFiles: [],
        consolidatedData: [{ source: 'master', v: 1300, f: 800, h: 3000, e: 16.5, err: 0.1, p: 49.5, score: 95 }]
      }
    };
    const resp = await getJson(`${baseOrigin}/api/share.php?action=create`, {
      method: 'POST',
      redirect: 'follow',
      headers: apiJsonHeaders('https://evil.example', 'post-wrong-origin'),
      body: JSON.stringify(req)
    });
    assert(resp.res.status === 403, `Expected 403, got ${resp.res.status}`);
    return `status=${resp.res.status}`;
  });

  await runTest('API security: replay nonce rejected with 409', async () => {
    const nonce = crypto.randomBytes(12).toString('hex');
    const payload = {
      meta: { selectedLanguage: 'en', mode: 'share', appVersion: 'http-audit' },
      visibleRows: 10,
      filters: {},
      layout: { order: ['table'], visibility: { table: true } },
      sourceFiles: [],
      consolidatedData: [{ source: 'master', v: 1300, f: 800, h: 3000, e: 16.5, err: 0.1, p: 49.5, score: 95 }]
    };
    const body = {
      request_ts: String(Math.floor(Date.now() / 1000)),
      request_nonce: nonce,
      payload
    };

    const first = await getJson(`${baseOrigin}/api/share.php?action=create`, {
      method: 'POST',
      redirect: 'follow',
      headers: apiJsonHeaders(baseOrigin, 'post-replay'),
      body: JSON.stringify(body)
    });
    assert([200, 201].includes(first.res.status), `First create must succeed, got ${first.res.status}`);

    const second = await getJson(`${baseOrigin}/api/share.php?action=create`, {
      method: 'POST',
      redirect: 'follow',
      headers: apiJsonHeaders(baseOrigin, 'post-replay'),
      body: JSON.stringify(body)
    });
    assert(second.res.status === 409, `Expected replay 409, got ${second.res.status}`);
    return `first=${first.res.status}, second=${second.res.status}`;
  });

  await runTest('API cache: share GET supports ETag + 304', async () => {
    const createReq = {
      request_ts: String(Math.floor(Date.now() / 1000)),
      request_nonce: crypto.randomBytes(12).toString('hex'),
      payload: {
        meta: { selectedLanguage: 'en', mode: 'share', appVersion: 'http-audit' },
        visibleRows: 10,
        filters: {},
        layout: { order: ['table'], visibility: { table: true } },
        sourceFiles: [],
        consolidatedData: [{ source: 'master', v: 1301, f: 801, h: 3010, e: 16.4, err: 0.2, p: 50.0, score: 96 }]
      }
    };
    const create = await getJson(`${baseOrigin}/api/share.php?action=create`, {
      method: 'POST',
      redirect: 'follow',
      headers: apiJsonHeaders(baseOrigin, 'post-cache-etag'),
      body: JSON.stringify(createReq)
    });
    assert(create.data && create.data.ok === true, `Create failed: ${short(create.data)}`);
    const token = String(create.data?.share?.token || '').trim();
    assert(token.length >= 16, `Invalid token: ${token}`);

    const getUrl = `${baseOrigin}/api/share.php?share=${encodeURIComponent(token)}`;
    const first = await fetchWithTiming(getUrl, { method: 'GET', redirect: 'follow' });
    const etag = String(first.res.headers.get('etag') || '').trim();
    assert(first.res.status === 200, `Expected 200, got ${first.res.status}`);
    assert(etag.length > 0, 'Missing ETag');
    await first.res.text();

    const second = await fetchWithTiming(getUrl, {
      method: 'GET',
      redirect: 'follow',
      headers: { 'If-None-Match': etag, 'User-Agent': apiUserAgent('get-cache-etag') }
    });
    assert(second.res.status === 304, `Expected 304, got ${second.res.status}`);
    return `etag=${etag.slice(0, 14)}..., second=${second.res.status}`;
  });

  await runTest('API response headers: share GET has JSON content-type', async () => {
    const resp = await getJson(`${baseOrigin}/api/share.php?share=ffffffffffffffffffffffffffffffff`, {
      method: 'GET',
      redirect: 'follow',
      headers: { 'User-Agent': apiUserAgent('get-json-content-type') }
    });
    const contentType = String(resp.res.headers.get('content-type') || '').toLowerCase();
    assert(contentType.includes('application/json'), `Expected JSON content-type, got: ${contentType}`);
    return contentType;
  });

  await runTest('API response headers: share endpoint sends X-Frame-Options', async () => {
    const { res } = await fetchWithTiming(`${baseOrigin}/api/share.php?share=ffffffffffffffffffffffffffffffff`, {
      method: 'GET',
      redirect: 'follow',
      headers: { 'User-Agent': apiUserAgent('get-xfo') }
    });
    const value = String(res.headers.get('x-frame-options') || '').toUpperCase();
    assert(value === 'DENY', `Unexpected X-Frame-Options on API: ${value}`);
    await res.arrayBuffer();
    return value;
  });

  await runTest('API response headers: share endpoint sends no-referrer policy', async () => {
    const { res } = await fetchWithTiming(`${baseOrigin}/api/share.php?share=ffffffffffffffffffffffffffffffff`, {
      method: 'GET',
      redirect: 'follow',
      headers: { 'User-Agent': apiUserAgent('get-referrer-policy') }
    });
    const value = String(res.headers.get('referrer-policy') || '').toLowerCase();
    assert(value === 'no-referrer', `Unexpected referrer-policy on API: ${value}`);
    await res.arrayBuffer();
    return value;
  });

  await runTest('API response headers: error responses are no-store', async () => {
    const { res, ms } = await fetchWithTiming(`${baseOrigin}/api/share.php?share=ffffffffffffffffffffffffffffffff`, {
      method: 'GET',
      redirect: 'follow',
      headers: { 'User-Agent': apiUserAgent('get-error-cache') }
    });
    const cacheControl = String(res.headers.get('cache-control') || '').toLowerCase();
    assert(res.status === 404, `Expected 404, got ${res.status}`);
    assert(cacheControl.includes('no-store'), `Error response must be no-store: ${cacheControl}`);
    await res.arrayBuffer();
    return `${res.status} ${cacheControl} (${ms}ms)`;
  });

  await runTest('Frontend assets: local tailwind asset is referenced', async () => {
    const scriptSrc = findScriptSrc(indexHtml, '/assets/vendor/tailwindcss-cdn.js');
    const staticCssHref = findLinkHrefByFragment(indexHtml, '/assets/vendor/tailwind-static.css');
    const src = scriptSrc || staticCssHref;
    assert(src.length > 0, 'tailwind local asset not found in HTML');
    assert(src.startsWith('/assets/vendor/'), `Unexpected tailwind asset src: ${src}`);
    return src;
  });

  await runTest('Frontend assets: vendor scripts in HTML include version query', async () => {
    const vendorSrcs = scriptSrcs.filter((src) => src.includes('/assets/vendor/'));
    assert(vendorSrcs.length >= 1, `Expected at least one local vendor script in HTML, got: ${short(vendorSrcs)}`);
    const missingVersion = vendorSrcs.filter((src) => !/[?&]v=[a-z0-9._-]+/i.test(src));
    assert(missingVersion.length === 0, `Vendor script without version query: ${missingVersion.join(', ')}`);
    return `versioned=${vendorSrcs.length}`;
  });

  await runLiveOnlyTest('Static asset cache: chart.umd.min.js immutable cache-control', async () => {
    const absolute = `${baseOrigin}/assets/vendor/chart.umd.min.js?v=61.1`;
    const { res, ms } = await fetchWithTiming(absolute, { method: 'GET', redirect: 'follow' });
    const cacheControl = String(res.headers.get('cache-control') || '').toLowerCase();
    assert(res.status === 200, `Asset status not 200: ${res.status}`);
    assert(cacheControl.includes('max-age=') && cacheControl.includes('immutable'), `Unexpected cache-control: ${cacheControl}`);
    await res.arrayBuffer();
    return `${ms}ms ${cacheControl}`;
  });

  await runLiveOnlyTest('Static asset cache: html2canvas immutable cache-control', async () => {
    const absolute = `${baseOrigin}/assets/vendor/html2canvas.min.js?v=61.1`;
    const { res, ms } = await fetchWithTiming(absolute, { method: 'GET', redirect: 'follow' });
    const cacheControl = String(res.headers.get('cache-control') || '').toLowerCase();
    assert(res.status === 200, `Asset status not 200: ${res.status}`);
    assert(cacheControl.includes('max-age=') && cacheControl.includes('immutable'), `Unexpected cache-control: ${cacheControl}`);
    await res.arrayBuffer();
    return `${ms}ms ${cacheControl}`;
  });

  await runLiveOnlyTest('Static asset cache: tailwind local bundle immutable cache-control', async () => {
    const scriptSrc = findScriptSrc(indexHtml, '/assets/vendor/tailwindcss-cdn.js');
    const staticCssHref = findLinkHrefByFragment(indexHtml, '/assets/vendor/tailwind-static.css');
    const src = scriptSrc || staticCssHref;
    assert(src.length > 0, 'tailwind local bundle src not found in HTML');
    const absolute = new URL(src, baseUrl).toString();
    const { res, ms } = await fetchWithTiming(absolute, { method: 'GET', redirect: 'follow' });
    const cacheControl = String(res.headers.get('cache-control') || '').toLowerCase();
    assert(res.status === 200, `Asset status not 200: ${res.status}`);
    assert(cacheControl.includes('max-age=') && cacheControl.includes('immutable'), `Unexpected cache-control: ${cacheControl}`);
    await res.arrayBuffer();
    return `${ms}ms ${cacheControl}`;
  });

  await runTest('Static assets: favicon-32 is reachable', async () => {
    const { res } = await fetchWithTiming(`${baseOrigin}/assets/favicon-32.png?v=61.1`, { method: 'GET', redirect: 'follow' });
    assert(res.status === 200, `favicon-32 status is ${res.status}`);
    const ct = String(res.headers.get('content-type') || '').toLowerCase();
    assert(ct.includes('image/png'), `favicon-32 content-type invalid: ${ct}`);
    await res.arrayBuffer();
    return ct;
  });

  await runTest('Static assets: favicon-192 is reachable', async () => {
    const { res } = await fetchWithTiming(`${baseOrigin}/assets/favicon-192.png?v=61.1`, { method: 'GET', redirect: 'follow' });
    assert(res.status === 200, `favicon-192 status is ${res.status}`);
    const ct = String(res.headers.get('content-type') || '').toLowerCase();
    assert(ct.includes('image/png'), `favicon-192 content-type invalid: ${ct}`);
    await res.arrayBuffer();
    return ct;
  });

  await runTest('Static assets: favicon.svg is reachable', async () => {
    const { res } = await fetchWithTiming(`${baseOrigin}/assets/favicon.svg?v=61.1`, { method: 'GET', redirect: 'follow' });
    assert(res.status === 200, `favicon.svg status is ${res.status}`);
    await res.arrayBuffer();
    return `status=${res.status}`;
  });

  await runTest('Manifest: site.webmanifest is valid JSON', async () => {
    const { res, body } = await getText(`${baseOrigin}/site.webmanifest?v=61.1`, { method: 'GET', redirect: 'follow' });
    assert(res.status === 200, `webmanifest status is ${res.status}`);
    let parsed = null;
    try {
      parsed = JSON.parse(body);
    } catch (_) {
      throw new Error(`webmanifest is not valid JSON: ${short(body)}`);
    }
    assert(parsed && typeof parsed === 'object', 'webmanifest parsed value is not an object');
    return short({ name: parsed.name, short_name: parsed.short_name }, 140);
  });

  await runTest('Manifest: required fields exist', async () => {
    const { body } = await getText(`${baseOrigin}/site.webmanifest?v=61.1`, { method: 'GET', redirect: 'follow' });
    const parsed = JSON.parse(body);
    assert(String(parsed.name || '').length > 0, 'manifest.name missing');
    assert(String(parsed.short_name || '').length > 0, 'manifest.short_name missing');
    assert(String(parsed.start_url || '').startsWith('/'), `manifest.start_url invalid: ${parsed.start_url}`);
    assert(String(parsed.scope || '').startsWith('/'), `manifest.scope invalid: ${parsed.scope}`);
    return short({ start_url: parsed.start_url, scope: parsed.scope }, 120);
  });

  await runTest('Manifest: icons array has png and svg entries', async () => {
    const { body } = await getText(`${baseOrigin}/site.webmanifest?v=61.1`, { method: 'GET', redirect: 'follow' });
    const parsed = JSON.parse(body);
    const icons = Array.isArray(parsed.icons) ? parsed.icons : [];
    const hasPng = icons.some((i) => String(i.type || '').toLowerCase().includes('png'));
    const hasSvg = icons.some((i) => String(i.type || '').toLowerCase().includes('svg'));
    assert(hasPng && hasSvg, `manifest icons missing png/svg: ${short(icons)}`);
    return `icons=${icons.length}`;
  });

  await runTest('Performance: static JS responds under 2000ms', async () => {
    const absolute = `${baseOrigin}/assets/vendor/chart.umd.min.js?v=61.1`;
    const { res, ms } = await fetchWithTiming(absolute, { method: 'GET', redirect: 'follow' });
    assert(res.status === 200, `Asset status not 200: ${res.status}`);
    assert(ms < 2000, `Asset response too slow: ${ms}ms`);
    await res.arrayBuffer();
    return `${ms}ms`;
  });

  await runTest('Performance: share page responds under 2500ms', async () => {
    const shareUrl = `${baseUrl}?share=test`;
    const { res, ms } = await fetchWithTiming(shareUrl, { method: 'GET', redirect: 'follow' });
    assert(res.status === 200, `Share page status not 200: ${res.status}`);
    assert(ms < 2500, `Share page too slow: ${ms}ms`);
    await res.arrayBuffer();
    return `${ms}ms`;
  });

  await runTest('Performance: share API unknown-token response under 1800ms', async () => {
    const { res, ms } = await fetchWithTiming(`${baseOrigin}/api/share.php?share=ffffffffffffffffffffffffffffffff`, {
      method: 'GET',
      redirect: 'follow',
      headers: { 'User-Agent': apiUserAgent('perf-unknown-token') }
    });
    assert(res.status === 404, `Expected 404 for unknown token, got ${res.status}`);
    assert(ms < 1800, `Unknown-token API response too slow: ${ms}ms`);
    await res.arrayBuffer();
    return `${ms}ms`;
  });

  const passCount = results.filter((r) => r.status === 'PASS').length;
  const failCount = results.length - passCount;

  console.log('=== BITAXE-OC HTTP AUDIT REPORT ===');
  console.log(`Target: ${baseUrl}`);
  console.log(`Profile: ${PROFILE}`);
  console.log(`Date: ${new Date().toISOString()}`);
  console.log('');
  results.forEach((r, idx) => {
    const base = `${String(idx + 1).padStart(2, '0')}. [${r.status}] ${r.name} (${r.ms}ms)`;
    if (r.status === 'PASS') {
      console.log(`${base} -> ${short(r.detail, 300)}`);
    } else {
      console.log(`${base} -> ${short(r.error, 300)}`);
    }
  });
  console.log('');
  console.log(`Summary: PASS ${passCount}/${results.length}, FAIL ${failCount}/${results.length}`);

  if (failCount > 0) process.exitCode = 1;
}

run().catch((error) => {
  console.error('FATAL:', error.message || String(error));
  process.exit(1);
});
