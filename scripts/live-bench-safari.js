#!/usr/bin/env node
'use strict';

const fs = require('fs');
const path = require('path');

const DEFAULT_DRIVER_BASE = 'http://127.0.0.1:4444';
const DEFAULT_TARGET_URL = 'https://oc.colortr.com/';
const DEFAULT_BENCH_DIR = path.resolve(__dirname, '..', 'bench');

const args = process.argv.slice(2);
if (args.includes('--help') || args.includes('-h')) {
  console.log('Usage: node scripts/live-bench-safari.js [target_url] [bench_dir]');
  console.log('Env: SAFARI_WEBDRIVER_URL=http://127.0.0.1:4444');
  process.exit(0);
}

const TARGET_URL = args[0] || process.env.BITAXE_TARGET_URL || DEFAULT_TARGET_URL;
const BENCH_DIR = path.resolve(args[1] || process.env.BITAXE_BENCH_DIR || DEFAULT_BENCH_DIR);
const BASE = process.env.SAFARI_WEBDRIVER_URL || DEFAULT_DRIVER_BASE;
const ELEMENT_KEY = 'element-6066-11e4-a52e-4f735466cecf';

function sleep(ms) {
  return new Promise((resolve) => setTimeout(resolve, ms));
}

function short(value, max = 240) {
  const text = typeof value === 'string' ? value : JSON.stringify(value);
  if (!text) return '';
  return text.length > max ? `${text.slice(0, max)}...` : text;
}

function assert(condition, message) {
  if (!condition) throw new Error(message);
}

function nowMs() {
  return Date.now();
}

function listCsvFiles(dir) {
  const files = fs.readdirSync(dir)
    .filter((name) => name.toLowerCase().endsWith('.csv'))
    .sort()
    .map((name) => path.resolve(dir, name));
  return files;
}

function listByPrefix(dir, prefix) {
  return fs.readdirSync(dir)
    .filter((name) => name.toLowerCase().endsWith('.csv') && name.startsWith(prefix))
    .sort()
    .map((name) => path.resolve(dir, name));
}

function bytesOf(paths) {
  return paths.reduce((sum, filePath) => sum + fs.statSync(filePath).size, 0);
}

async function request(method, pathName, body) {
  const response = await fetch(`${BASE}${pathName}`, {
    method,
    headers: { 'Content-Type': 'application/json' },
    body: body === undefined ? undefined : JSON.stringify(body)
  });
  const raw = await response.text();
  let parsed = null;
  try {
    parsed = raw ? JSON.parse(raw) : {};
  } catch (error) {
    throw new Error(`Non-JSON response (${method} ${pathName}): ${short(raw)}`);
  }
  if (!response.ok) {
    throw new Error(`HTTP ${response.status} (${method} ${pathName}): ${short(parsed)}`);
  }
  if (parsed && parsed.value && parsed.value.error) {
    throw new Error(`WebDriver ${parsed.value.error}: ${parsed.value.message || 'unknown error'}`);
  }
  return parsed.value;
}

async function run() {
  const startedAtIso = new Date().toISOString();
  const results = [];
  let sessionId = null;

  const manifestPath = path.join(BENCH_DIR, 'bench_manifest.json');
  assert(fs.existsSync(manifestPath), `bench manifest not found: ${manifestPath}`);
  const manifest = JSON.parse(fs.readFileSync(manifestPath, 'utf8'));

  const singleMaxAccepted = path.join(BENCH_DIR, 'single', 'max_accepted_349kb.csv');
  const singleOverLimit = path.join(BENCH_DIR, 'single', 'over_file_limit_360kb.csv');
  const singleTruncate = path.join(BENCH_DIR, 'single', 'truncate_rows_7500.csv');
  const batchMax = listByPrefix(path.join(BENCH_DIR, 'batch_max'), 'max_');
  const batchOverflow = listByPrefix(path.join(BENCH_DIR, 'batch_overflow'), 'overflow_');

  [singleMaxAccepted, singleOverLimit, singleTruncate].forEach((filePath) => {
    assert(fs.existsSync(filePath), `missing bench file: ${filePath}`);
  });
  assert(batchMax.length === 30, `expected 30 batch_max files, got ${batchMax.length}`);
  assert(batchOverflow.length === 32, `expected 32 batch_overflow files, got ${batchOverflow.length}`);

  async function runTest(name, fn) {
    const t0 = nowMs();
    console.log(`-> ${name}`);
    try {
      const detail = await fn();
      results.push({ name, status: 'PASS', ms: nowMs() - t0, detail });
    } catch (error) {
      results.push({ name, status: 'FAIL', ms: nowMs() - t0, error: error.message || String(error) });
    }
  }

  const created = await request('POST', '/session', {
    capabilities: { alwaysMatch: { browserName: 'safari' } }
  });
  sessionId = created.sessionId;
  if (!sessionId) throw new Error('Failed to create Safari session');

  const exec = (script, args = []) => request('POST', `/session/${sessionId}/execute/sync`, { script, args });
  const navigate = (url) => request('POST', `/session/${sessionId}/url`, { url });

  async function waitFor(label, script, timeoutMs = 20000, intervalMs = 250) {
    const startedAt = nowMs();
    let lastValue = null;
    while ((nowMs() - startedAt) < timeoutMs) {
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
      40000,
      300
    );
    await exec(`
      if (!window.__benchHookInstalled) {
        window.__benchHookInstalled = true;
        window.__origAlert = window.alert;
      }
      window.__benchAlerts = [];
      window.alert = function(msg) {
        window.__benchAlerts.push(String(msg));
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
    return exec('return Array.isArray(window.__benchAlerts) ? window.__benchAlerts.slice() : [];');
  }

  async function clearAlerts() {
    await exec('window.__benchAlerts = []; return true;');
  }

  async function uploadByPaths(paths) {
    const elementRef = await request('POST', `/session/${sessionId}/element`, {
      using: 'css selector',
      value: '#fileInput'
    });
    const elementId = elementRef[ELEMENT_KEY] || elementRef.ELEMENT;
    if (!elementId) throw new Error(`No WebDriver element id: ${short(elementRef)}`);

    const payloadText = paths.map((item) => path.resolve(item)).join('\n');
    await request('POST', `/session/${sessionId}/element/${elementId}/value`, {
      text: payloadText,
      value: payloadText.split('')
    });
  }

  await runTest('Bench sanity: fixture integrity', async () => {
    const summary = {
      singleAcceptedBytes: fs.statSync(singleMaxAccepted).size,
      singleOverBytes: fs.statSync(singleOverLimit).size,
      singleTruncateBytes: fs.statSync(singleTruncate).size,
      batchMaxCount: batchMax.length,
      batchMaxBytes: bytesOf(batchMax),
      batchOverflowCount: batchOverflow.length,
      batchOverflowBytes: bytesOf(batchOverflow),
      generatedAt: manifest.generatedAt || null
    };
    assert(summary.singleAcceptedBytes <= (350 * 1024), `max accepted file too large: ${summary.singleAcceptedBytes}`);
    assert(summary.singleOverBytes > (350 * 1024), `over-limit file should exceed 350KB: ${summary.singleOverBytes}`);
    return summary;
  });

  await runTest('Scenario A: single max accepted file', async () => {
    await freshPage();
    await clearAlerts();

    const uploadStart = nowMs();
    await uploadByPaths([singleMaxAccepted]);
    await waitFor('single file listed', "return document.querySelectorAll('#file-list input[name=\"masterFile\"]').length === 1;", 90000, 250);
    const uploadMs = nowMs() - uploadStart;

    const processStart = nowMs();
    await exec("document.getElementById('process-btn').click(); return true;");
    await waitFor(
      'analysis done',
      "return document.querySelectorAll('#tableBody tr').length > 0 && document.getElementById('upload-section')?.style.display === 'none';",
      90000,
      300
    );
    const analysisMs = nowMs() - processStart;

    const state = await exec(`
      return {
        rows: document.querySelectorAll('#tableBody tr').length,
        summary: (document.getElementById('data-quality-summary')?.textContent || '').replace(/\\s+/g, ' ').trim()
      };
    `);
    const alerts = await getAlerts();
    assert(state.rows > 0, `No rows after single max accepted file: ${short(state)}`);
    assert(!alerts.some((a) => a.includes('too large')), `Unexpected too large alert: ${short(alerts)}`);
    return { uploadMs, analysisMs, rows: state.rows };
  });

  await runTest('Scenario A2: post-analysis controls and pagination remain healthy', async () => {
    const before = await exec(`
      const btn = document.getElementById('loadMoreBtn');
      return {
        rows: document.querySelectorAll('#tableBody tr').length,
        loadMoreVisible: !!btn && getComputedStyle(btn).display !== 'none',
        processLabel: (document.getElementById('upload-process-label')?.textContent || '').trim(),
        exportHtmlVisible: !document.getElementById('export-html-btn')?.classList.contains('hidden'),
        exportJpegVisible: !document.getElementById('export-jpeg-btn')?.classList.contains('hidden')
      };
    `);
    assert(before.rows === 15, `Expected 15 visible rows before load more: ${short(before)}`);
    assert(before.loadMoreVisible === true, `Load more button should be visible: ${short(before)}`);
    assert(before.exportHtmlVisible && before.exportJpegVisible, `Export buttons should be visible: ${short(before)}`);

    await exec("document.getElementById('loadMoreBtn')?.click(); return true;");
    const afterRows = await waitFor('scenario-a2 rows increased', "const n=document.querySelectorAll('#tableBody tr').length; return n > 15 ? n : 0;", 15000, 250);
    assert(afterRows > 15, `Load more did not increase rows: ${afterRows}`);
    return { before: before.rows, after: afterRows, processLabel: before.processLabel };
  });

  await runTest('Scenario B: single file over size limit rejected', async () => {
    await freshPage();
    await clearAlerts();

    const uploadStart = nowMs();
    await uploadByPaths([singleOverLimit]);
    await waitFor('size alert appears', "return (window.__benchAlerts || []).length > 0;", 30000, 200);
    const uploadMs = nowMs() - uploadStart;

    const state = await exec(`
      return {
        listed: document.querySelectorAll('#file-list input[name="masterFile"]').length,
        alerts: (window.__benchAlerts || []).slice()
      };
    `);
    const tooLarge = state.alerts.find((a) => String(a).includes('too large'));
    assert(state.listed === 0, `Over-limit file should not be listed: ${short(state)}`);
    assert(Boolean(tooLarge), `Expected too-large alert for over-limit file: ${short(state.alerts)}`);
    return { uploadMs, listed: state.listed, tooLarge };
  });

  await runTest('Scenario C: row truncation at 7000', async () => {
    await freshPage();
    await clearAlerts();

    const uploadStart = nowMs();
    await uploadByPaths([singleTruncate]);
    await waitFor('truncate file listed', "return document.querySelectorAll('#file-list input[name=\"masterFile\"]').length === 1;", 90000, 250);
    const uploadMs = nowMs() - uploadStart;

    const processStart = nowMs();
    await exec("document.getElementById('process-btn').click(); return true;");
    await waitFor('truncate analysis done', "return document.querySelectorAll('#tableBody tr').length > 0;", 90000, 300);
    const analysisMs = nowMs() - processStart;

    const summary = await exec("return (document.getElementById('data-quality-summary')?.textContent || '').replace(/\\s+/g, ' ').trim();");
    assert(/Truncated by Limits:\s*500/.test(summary), `Expected Truncated by Limits: 500. Got: ${short(summary, 400)}`);
    return { uploadMs, analysisMs, truncated: 500 };
  });

  await runTest('Scenario C2: truncation view keeps countdown visible right after analysis', async () => {
    const state = await exec(`
      const countdown = document.getElementById('data-quality-countdown');
      return {
        text: (countdown?.textContent || '').trim(),
        hidden: countdown ? countdown.classList.contains('hidden') : null,
        rows: document.querySelectorAll('#tableBody tr').length
      };
    `);
    assert(state.rows > 0, `Expected table rows in truncation scenario: ${short(state)}`);
    assert(state.hidden === false, `Countdown should be visible right after process: ${short(state)}`);
    assert(/^\d+s$/.test(state.text), `Countdown text is invalid: ${short(state)}`);
    return state;
  });

  await runTest('Scenario D: max batch accepted (30 files, ~6MB)', async () => {
    await freshPage();
    await clearAlerts();

    const uploadStart = nowMs();
    await uploadByPaths(batchMax);
    await waitFor('30 files listed', "return document.querySelectorAll('#file-list input[name=\"masterFile\"]').length === 30;", 180000, 350);
    const uploadMs = nowMs() - uploadStart;

    const processStart = nowMs();
    await exec("document.getElementById('process-btn').click(); return true;");
    await waitFor('max batch analysis done', "return document.querySelectorAll('#tableBody tr').length > 0;", 180000, 350);
    const analysisMs = nowMs() - processStart;

    const state = await exec(`
      return {
        listed: document.querySelectorAll('#file-list input[name="masterFile"]').length,
        rows: document.querySelectorAll('#tableBody tr').length,
        summary: (document.getElementById('data-quality-summary')?.textContent || '').replace(/\\s+/g, ' ').trim()
      };
    `);
    const alerts = await getAlerts();
    assert(state.listed === 30, `Expected 30 listed files: ${short(state)}`);
    assert(state.rows > 0, `No table rows after max batch process: ${short(state)}`);
    assert(!alerts.some((a) => a.includes('Total upload limit')), `Unexpected total-limit alert on max batch: ${short(alerts)}`);
    return { uploadMs, analysisMs, rows: state.rows };
  });

  await runTest('Scenario D2: max batch supports quick sort + filter reset', async () => {
    const sorted = await exec(`
      const parseCellNumber = (value) => {
        const raw = String(value || '').trim().replace(/[^0-9,.-]/g, '');
        if (!raw) return NaN;
        const normalized = (raw.includes(',') && !raw.includes('.')) ? raw.replace(',', '.') : raw.replace(/,/g, '');
        const num = Number.parseFloat(normalized);
        return Number.isFinite(num) ? num : NaN;
      };
      const btn = document.getElementById('quick-sort-hash-btn');
      if (!btn) return null;
      btn.click();
      const headerBtn = document.querySelector('#tableHead .table-sort-btn[data-sort-key="h"]');
      const th = headerBtn ? headerBtn.closest('th') : null;
      const idx = th ? Array.from(th.parentElement.children).indexOf(th) : -1;
      const rows = Array.from(document.querySelectorAll('#tableBody tr'));
      const first = rows[0] ? parseCellNumber(rows[0].children[idx]?.textContent || '') : NaN;
      const second = rows[1] ? parseCellNumber(rows[1].children[idx]?.textContent || '') : NaN;
      return { idx, first, second, visibleRows: rows.length };
    `);
    assert(sorted && Number.isFinite(sorted.first), `Quick hash sort state invalid: ${short(sorted)}`);
    assert(sorted.visibleRows >= 15, `Unexpected visible row count after sort: ${short(sorted)}`);
    assert(!Number.isFinite(sorted.second) || sorted.first >= sorted.second, `Hash sort order seems invalid: ${short(sorted)}`);

    const filterApplied = await exec(`
      const input = document.getElementById('f-h-min');
      input.value = '999999';
      input.dispatchEvent(new Event('input', { bubbles: true }));
      return {
        inputValue: String(input.value || ''),
        visibleRows: document.querySelectorAll('#tableBody tr').length
      };
    `);
    assert(filterApplied.inputValue.length > 0, `Filter input should contain typed value: ${short(filterApplied)}`);
    assert(filterApplied.visibleRows > 0, `Filtered table should still render rows: ${short(filterApplied)}`);

    await exec("document.getElementById('reset-filters-btn')?.click(); return true;");
    const restoredRows = await waitFor('scenario-d2 rows restored', "const n=document.querySelectorAll('#tableBody tr').length; return n >= 15 ? n : 0;", 15000, 250);
    const resetState = await exec(`
      const input = document.getElementById('f-h-min');
      return {
        value: String(input?.value || ''),
        placeholder: String(input?.placeholder || '')
      };
    `);
    assert(restoredRows >= 15, `Rows should restore after filter reset: ${restoredRows}`);
    assert(resetState.value === '', `Filter reset did not clear input value: ${short(resetState)}`);
    return { sortedFirst: sorted.first, filteredMin: filterApplied.inputValue, restoredRows };
  });

  await runTest('Scenario E: overflow batch (32 files) limit behavior', async () => {
    await freshPage();
    await clearAlerts();

    const uploadStart = nowMs();
    await uploadByPaths(batchOverflow);
    await waitFor('overflow alerts + listing', `
      const alerts = window.__benchAlerts || [];
      const listed = document.querySelectorAll('#file-list input[name="masterFile"]').length;
      return alerts.length >= 2 && listed >= 25;
    `, 180000, 350);
    const uploadMs = nowMs() - uploadStart;

    const beforeProcess = await exec(`
      return {
        listed: document.querySelectorAll('#file-list input[name="masterFile"]').length,
        alerts: (window.__benchAlerts || []).slice()
      };
    `);
    const countAlert = beforeProcess.alerts.find((a) => String(a).includes('Only 30 files can be processed per batch'));
    const totalAlert = beforeProcess.alerts.find((a) => String(a).includes('Total upload limit'));
    assert(beforeProcess.listed === 27, `Expected 27 accepted files for overflow fixture: ${short(beforeProcess)}`);
    assert(Boolean(countAlert), `Expected file count overflow alert: ${short(beforeProcess.alerts)}`);
    assert(Boolean(totalAlert), `Expected total size overflow alert: ${short(beforeProcess.alerts)}`);

    const processStart = nowMs();
    await exec("document.getElementById('process-btn').click(); return true;");
    await waitFor('overflow analysis done', "return document.querySelectorAll('#tableBody tr').length > 0;", 180000, 350);
    const analysisMs = nowMs() - processStart;

    const rows = await exec("return document.querySelectorAll('#tableBody tr').length;");
    return {
      uploadMs,
      analysisMs,
      listed: beforeProcess.listed,
      rows,
      countAlert,
      totalAlert
    };
  });

  await runTest('Scenario E2: overflow path keeps process control interactive', async () => {
    const state = await exec(`
      const btn = document.getElementById('process-btn');
      return {
        listed: document.querySelectorAll('#file-list input[name="masterFile"]').length,
        rows: document.querySelectorAll('#tableBody tr').length,
        disabled: !!btn?.disabled,
        label: (document.getElementById('upload-process-label')?.textContent || '').trim(),
        alerts: (window.__benchAlerts || []).slice()
      };
    `);
    assert(state.listed === 27, `Overflow accepted file count drifted: ${short(state)}`);
    assert(state.rows > 0, `Overflow analysis did not produce rows: ${short(state)}`);
    assert(state.disabled === false, `Process button should be enabled after overflow analysis: ${short(state)}`);
    assert(state.alerts.some((msg) => String(msg).includes('Only 30 files')), `Expected count-limit alert to persist in session: ${short(state.alerts)}`);
    assert(state.alerts.some((msg) => String(msg).includes('Total upload limit')), `Expected total-limit alert to persist in session: ${short(state.alerts)}`);
    return { listed: state.listed, rows: state.rows, label: state.label };
  });

  if (sessionId) {
    await request('DELETE', `/session/${sessionId}`);
    sessionId = null;
  }

  const passCount = results.filter((r) => r.status === 'PASS').length;
  const failCount = results.length - passCount;
  const report = {
    startedAt: startedAtIso,
    finishedAt: new Date().toISOString(),
    targetUrl: TARGET_URL,
    benchDir: BENCH_DIR,
    webdriver: BASE,
    summary: {
      pass: passCount,
      fail: failCount,
      total: results.length
    },
    results
  };

  console.log('=== BITAXE-OC LIVE BENCH REPORT ===');
  results.forEach((item, idx) => {
    const line = `${String(idx + 1).padStart(2, '0')}. [${item.status}] ${item.name} (${item.ms}ms)`;
    if (item.status === 'PASS') console.log(`${line} -> ${short(item.detail, 320)}`);
    else console.log(`${line} -> ${short(item.error, 320)}`);
  });
  console.log(`Summary: PASS ${passCount}/${results.length}, FAIL ${failCount}/${results.length}`);
  console.log('');
  console.log('JSON_REPORT_START');
  console.log(JSON.stringify(report, null, 2));
  console.log('JSON_REPORT_END');

  if (failCount > 0) {
    process.exitCode = 1;
  }
}

run().catch(async (error) => {
  console.error('FATAL:', error.message || String(error));
  process.exit(1);
});
