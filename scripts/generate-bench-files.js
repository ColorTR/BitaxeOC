#!/usr/bin/env node
'use strict';

const fs = require('fs');
const path = require('path');

const KB = 1024;
const LIMITS = {
  maxFilesPerBatch: 30,
  maxFileBytes: 350 * KB,
  maxTotalBytes: 6 * 1024 * KB,
  csvMaxDataRows: 7000
};

const rootDir = path.resolve(__dirname, '..');
const benchDir = path.join(rootDir, 'bench');
const out = {
  generatedAt: new Date().toISOString(),
  limits: LIMITS,
  files: []
};

function ensureDir(dir) {
  fs.mkdirSync(dir, { recursive: true });
}

function byteLength(text) {
  return Buffer.byteLength(text, 'utf8');
}

function toRow(i) {
  const v = 1200 + (i % 250);
  const f = 650 + (i % 550);
  const h = 2500 + ((i * 13) % 2300);
  const t = 45 + (i % 28);
  const vr = t + 5 + (i % 8);
  const e = (15.4 + ((i * 7) % 520) / 100).toFixed(2);
  const err = (((i * 3) % 210) / 100).toFixed(2);
  const p = (42 + (i % 40)).toFixed(1);
  return `${v},${f},${h},${t},${vr},${e},${err},${p}\n`;
}

function toRowWithNote(i, noteLen = 32) {
  const base = toRow(i).trimEnd();
  const token = `N${String(i).padStart(6, '0')}`;
  const filler = 'x'.repeat(Math.max(0, noteLen - token.length));
  return `${base},${token}${filler}\n`;
}

function buildCsvByTargetBytes(targetBytes, noteLen = 32) {
  const header = 'Voltage,Frequency,Hashrate,Temperature,VRM Temp,Efficiency,Error,Power,Note\n';
  let body = header;
  let i = 0;
  while (byteLength(body) < targetBytes) {
    body += toRowWithNote(i, noteLen);
    i += 1;
    if (i > 25000) break;
  }
  return { content: body, rows: i, bytes: byteLength(body) };
}

function buildCsvByRows(rows) {
  const header = 'Voltage,Frequency,Hashrate,Temperature,VRM Temp,Efficiency,Error,Power\n';
  let body = header;
  for (let i = 0; i < rows; i += 1) {
    body += toRow(i);
  }
  return { content: body, rows, bytes: byteLength(body) };
}

function writeCsv(filePath, data, tag) {
  fs.writeFileSync(filePath, data.content, 'utf8');
  out.files.push({
    file: path.relative(rootDir, filePath),
    tag,
    bytes: data.bytes,
    rows: data.rows
  });
}

function main() {
  const singleDir = path.join(benchDir, 'single');
  const batchMaxDir = path.join(benchDir, 'batch_max');
  const batchOverflowDir = path.join(benchDir, 'batch_overflow');
  const reportDir = path.join(benchDir, 'reports');

  [benchDir, singleDir, batchMaxDir, batchOverflowDir, reportDir].forEach(ensureDir);

  writeCsv(
    path.join(singleDir, 'max_accepted_349kb.csv'),
    buildCsvByTargetBytes((349 * KB), 30),
    'single_max_accepted'
  );

  writeCsv(
    path.join(singleDir, 'over_file_limit_360kb.csv'),
    buildCsvByTargetBytes((360 * KB), 30),
    'single_over_file_limit'
  );

  writeCsv(
    path.join(singleDir, 'truncate_rows_7500.csv'),
    buildCsvByRows(7500),
    'single_row_truncation'
  );

  for (let i = 1; i <= 30; i += 1) {
    const fileName = `max_${String(i).padStart(2, '0')}.csv`;
    writeCsv(
      path.join(batchMaxDir, fileName),
      buildCsvByTargetBytes((200 * KB), 28),
      'batch_max_limit'
    );
  }

  for (let i = 1; i <= 32; i += 1) {
    const fileName = `overflow_${String(i).padStart(2, '0')}.csv`;
    writeCsv(
      path.join(batchOverflowDir, fileName),
      buildCsvByTargetBytes((220 * KB), 28),
      'batch_overflow_limit'
    );
  }

  const manifestPath = path.join(benchDir, 'bench_manifest.json');
  fs.writeFileSync(manifestPath, JSON.stringify(out, null, 2) + '\n', 'utf8');

  const byTag = out.files.reduce((acc, file) => {
    if (!acc[file.tag]) acc[file.tag] = { count: 0, bytes: 0 };
    acc[file.tag].count += 1;
    acc[file.tag].bytes += file.bytes;
    return acc;
  }, {});

  console.log('Bench files generated.');
  Object.keys(byTag).sort().forEach((tag) => {
    const g = byTag[tag];
    console.log(`${tag}: count=${g.count}, bytes=${g.bytes}`);
  });
  console.log(`Manifest: ${manifestPath}`);
}

main();
