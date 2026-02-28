(() => {
    'use strict';

    const MAX_FILES = 30;
    const MAX_FILE_BYTES = 350 * 1024;
    const MAX_TOTAL_BYTES = 6 * 1024 * 1024;
    const DEFAULT_VISIBLE_ROWS = 40;
    const FILTER_INPUT_DEBOUNCE_MS = 120;

    const refs = {
        fileInput: document.getElementById('fileInput'),
        selectedFiles: document.getElementById('selectedFiles'),
        selectionHint: document.getElementById('selectionHint'),
        analyzeBtn: document.getElementById('analyzeBtn'),
        alertBox: document.getElementById('alertBox'),
        qualitySummary: document.getElementById('qualitySummary'),
        kpiMaster: document.getElementById('kpiMaster'),
        kpiMaxHash: document.getElementById('kpiMaxHash'),
        kpiBestEff: document.getElementById('kpiBestEff'),
        tableHead: document.getElementById('tableHead'),
        tableBody: document.getElementById('tableBody'),
        loadMoreBtn: document.getElementById('loadMoreBtn'),
        filterVMin: document.getElementById('filterVMin'),
        filterFMin: document.getElementById('filterFMin'),
        filterHMin: document.getElementById('filterHMin'),
        filterEMax: document.getElementById('filterEMax')
    };

    if (!refs.fileInput || !refs.selectedFiles || !refs.analyzeBtn) {
        return;
    }

    const meta = {
        csrfToken: document.querySelector('meta[name="csrf-token"]')?.content || '',
        basePath: document.querySelector('meta[name="app-base"]')?.content || '',
        appVersion: document.querySelector('meta[name="app-version"]')?.content || 'v61-backend.4'
    };

    const state = {
        selectedFiles: [],
        masterClientId: null,
        files: [],
        consolidatedData: [],
        recommendations: {
            masterSelection: null,
            maxHash: null,
            bestEfficiency: null
        },
        summary: null,
        visibleRows: DEFAULT_VISIBLE_ROWS,
        tableSort: { key: 'score', dir: 'desc' },
        renderRafId: 0,
        filterDebounceTimer: 0,
        charts: {}
    };

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function isFiniteNumber(value) {
        return Number.isFinite(Number(value));
    }

    function formatValue(value, digits = 2) {
        if (!isFiniteNumber(value)) return '-';
        return Number(value).toFixed(digits);
    }

    function formatCompact(value, digits = 2) {
        if (!isFiniteNumber(value)) return '-';
        const n = Number(value);
        return Number.isInteger(n) ? String(n) : n.toFixed(digits);
    }

    function bytesToMb(bytes) {
        const mb = Number(bytes) / (1024 * 1024);
        if (!Number.isFinite(mb) || mb <= 0) return '0';
        return mb < 10 ? mb.toFixed(1).replace(/\.0$/, '') : String(Math.round(mb));
    }

    function buildApiUrl(path) {
        const cleanBase = meta.basePath.replace(/\/$/, '');
        const cleanPath = String(path || '').replace(/^\//, '');
        if (!cleanBase) return `/${cleanPath}`;
        return `${cleanBase}/${cleanPath}`;
    }

    function showAlert(message, type = 'error') {
        refs.alertBox.className = `alert ${type}`;
        refs.alertBox.textContent = message;
        refs.alertBox.classList.remove('hidden');
    }

    function clearAlert() {
        refs.alertBox.className = 'alert hidden';
        refs.alertBox.textContent = '';
    }

    function setAnalyzeBusy(isBusy) {
        refs.analyzeBtn.disabled = isBusy;
        refs.analyzeBtn.textContent = isBusy ? 'Analiz Calisiyor...' : 'Analizi Baslat';
    }

    function isCsvLike(file) {
        const name = String(file?.name || '').toLowerCase();
        const mime = String(file?.type || '').toLowerCase();
        if (name.endsWith('.csv')) return true;
        if (mime.includes('csv')) return true;
        return mime === 'text/plain' || mime === 'application/vnd.ms-excel';
    }

    function selectedTotalBytes() {
        return state.selectedFiles.reduce((sum, item) => sum + (Number(item.size) || 0), 0);
    }

    function createClientId(file, indexSeed = 0) {
        const name = String(file?.name || 'file').replace(/[^a-zA-Z0-9_.-]/g, '_');
        const ts = Number(file?.lastModified || Date.now());
        return `${name}__${ts}_${indexSeed}`;
    }

    function generateRequestNonce() {
        try {
            if (window.crypto && typeof window.crypto.getRandomValues === 'function') {
                const bytes = new Uint8Array(16);
                window.crypto.getRandomValues(bytes);
                return Array.from(bytes).map((b) => b.toString(16).padStart(2, '0')).join('');
            }
        } catch (_) {
            // fallback below
        }
        return `${Date.now().toString(16)}_${Math.random().toString(16).slice(2, 16)}`;
    }

    function renderSelectionHint() {
        if (!state.selectedFiles.length) {
            refs.selectionHint.textContent = 'Henuz dosya secilmedi.';
            return;
        }

        refs.selectionHint.textContent = `${state.selectedFiles.length} dosya secildi | Toplam ${bytesToMb(selectedTotalBytes())} MB | Master: ${state.selectedFiles.find((f) => f.clientId === state.masterClientId)?.name || '-'}`;
    }

    function renderSelectedFiles() {
        renderSelectionHint();

        if (!state.selectedFiles.length) {
            refs.selectedFiles.innerHTML = '';
            return;
        }

        const rows = state.selectedFiles.map((item) => {
            const checked = item.clientId === state.masterClientId ? 'checked' : '';
            return `
                <div class="file-row">
                    <button type="button" class="file-remove" data-action="remove" data-id="${escapeHtml(item.clientId)}" aria-label="Dosyayi kaldir">X</button>
                    <div>
                        <div class="file-row-name">${escapeHtml(item.name)}</div>
                        <div class="file-row-meta">${bytesToMb(item.size)} MB</div>
                    </div>
                    <label class="file-master-label">
                        <input type="radio" name="masterFile" value="${escapeHtml(item.clientId)}" ${checked}>
                        MASTER
                    </label>
                </div>
            `;
        });

        refs.selectedFiles.innerHTML = rows.join('');
    }

    function addFiles(fileList) {
        const incoming = Array.from(fileList || []);
        if (!incoming.length) return;

        const counters = {
            nonCsv: 0,
            tooLarge: 0,
            totalOverflow: 0,
            countOverflow: 0,
            duplicate: 0,
            accepted: 0
        };

        const existingKeys = new Set(state.selectedFiles.map((f) => `${f.name}::${f.lastModified}::${f.size}`));
        let totalBytes = selectedTotalBytes();

        incoming.forEach((file, index) => {
            if (!isCsvLike(file)) {
                counters.nonCsv += 1;
                return;
            }

            const key = `${file.name}::${file.lastModified}::${file.size}`;
            if (existingKeys.has(key)) {
                counters.duplicate += 1;
                return;
            }

            if (state.selectedFiles.length >= MAX_FILES) {
                counters.countOverflow += 1;
                return;
            }

            const size = Math.max(0, Number(file.size || 0));
            if (size <= 0 || size > MAX_FILE_BYTES) {
                counters.tooLarge += 1;
                return;
            }

            if ((totalBytes + size) > MAX_TOTAL_BYTES) {
                counters.totalOverflow += 1;
                return;
            }

            totalBytes += size;
            existingKeys.add(key);

            const clientId = createClientId(file, Date.now() + index);
            state.selectedFiles.push({
                clientId,
                file,
                name: String(file.name || 'untitled.csv'),
                size,
                lastModified: Number(file.lastModified || Date.now())
            });
            counters.accepted += 1;
        });

        if (!state.masterClientId && state.selectedFiles.length) {
            state.masterClientId = state.selectedFiles[0].clientId;
        }

        renderSelectedFiles();

        const messages = [];
        if (counters.accepted > 0) messages.push(`${counters.accepted} dosya eklendi.`);
        if (counters.duplicate > 0) messages.push(`${counters.duplicate} dosya tekrar oldugu icin atlandi.`);
        if (counters.nonCsv > 0) messages.push(`${counters.nonCsv} dosya CSV olmadigi icin atlandi.`);
        if (counters.tooLarge > 0) messages.push(`${counters.tooLarge} dosya boyut limiti (${bytesToMb(MAX_FILE_BYTES)} MB) nedeniyle atlandi.`);
        if (counters.totalOverflow > 0) messages.push(`${counters.totalOverflow} dosya toplam limit (${bytesToMb(MAX_TOTAL_BYTES)} MB) nedeniyle atlandi.`);
        if (counters.countOverflow > 0) messages.push(`${counters.countOverflow} dosya adet limiti (${MAX_FILES}) nedeniyle atlandi.`);

        if (messages.length) {
            showAlert(messages.join(' '), counters.accepted > 0 ? 'info' : 'error');
        }
    }

    function removeSelectedFile(clientId) {
        const before = state.selectedFiles.length;
        state.selectedFiles = state.selectedFiles.filter((item) => item.clientId !== clientId);
        if (state.selectedFiles.length === before) return;

        if (!state.selectedFiles.length) {
            state.masterClientId = null;
            renderSelectedFiles();
            return;
        }

        const hasMaster = state.selectedFiles.some((item) => item.clientId === state.masterClientId);
        if (!hasMaster) {
            state.masterClientId = state.selectedFiles[0].clientId;
        }

        renderSelectedFiles();
    }

    function getMasterIndex() {
        if (!state.selectedFiles.length) return 0;
        const idx = state.selectedFiles.findIndex((item) => item.clientId === state.masterClientId);
        return idx >= 0 ? idx : 0;
    }

    async function analyzeOnServer() {
        if (!state.selectedFiles.length) {
            showAlert('Lutfen en az bir CSV dosyasi secin.', 'error');
            return;
        }
        if (!meta.csrfToken) {
            showAlert('CSRF token bulunamadi. Sayfayi yenileyin.', 'error');
            return;
        }

        clearAlert();
        setAnalyzeBusy(true);

        const formData = new FormData();
        formData.append('csrf_token', meta.csrfToken);
        formData.append('master_index', String(getMasterIndex()));
        formData.append('request_ts', String(Math.floor(Date.now() / 1000)));
        formData.append('request_nonce', generateRequestNonce());

        state.selectedFiles.forEach((entry) => {
            formData.append('files[]', entry.file, entry.name);
        });

        try {
            const response = await fetch(buildApiUrl('api/analyze.php'), {
                method: 'POST',
                body: formData,
                credentials: 'same-origin',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            const payload = await response.json();
            if (!response.ok || !payload?.ok) {
                throw new Error(payload?.error || 'Analiz basarisiz.');
            }

            const data = payload.data || {};
            state.files = Array.isArray(data.files) ? data.files : [];
            state.consolidatedData = Array.isArray(data.consolidatedData) ? data.consolidatedData : [];
            state.recommendations = data.recommendations || { masterSelection: null, maxHash: null, bestEfficiency: null };
            state.summary = data.summary || null;
            state.visibleRows = DEFAULT_VISIBLE_ROWS;
            state.tableSort = { key: 'score', dir: 'desc' };

            renderSortIndicators();
            renderAll();
            const mergedRecords = Number(state.summary?.mergedRecords ?? state.consolidatedData.length);
            const dropped = Number(state.summary?.responseRowsDropped ?? 0);
            let message = `Analiz tamamlandi. ${mergedRecords} kayit birlestirildi.`;
            if (dropped > 0) {
                message += ` Performans icin ${dropped} satir cevapta sinirlandi.`;
            }
            showAlert(message, 'success');
        } catch (error) {
            showAlert(error instanceof Error ? error.message : 'Analiz sirasinda hata olustu.', 'error');
        } finally {
            setAnalyzeBusy(false);
        }
    }

    function renderSummary() {
        if (!refs.qualitySummary) return;

        if (!state.summary) {
            refs.qualitySummary.innerHTML = '<h3>Veri Kalite Ozeti</h3><p>Analiz sonrasi dosya kalite metrikleri burada gorunecek.</p>';
            return;
        }

        const summary = state.summary;
        const chips = [
            `Dosya: ${summary.fileCount ?? 0}`,
            `Aktif: ${summary.activeCount ?? 0}`,
            `Toplam Satir: ${summary.totalRows ?? 0}`,
            `Islenen: ${summary.parsedRows ?? 0}`,
            `Atlanan: ${summary.skippedRows ?? 0}`,
            `Birlesik Kayit: ${summary.mergedRecords ?? 0}`,
            `VR Eksik: ${summary.missingVrRows ?? 0}`,
            `Hash Turetim: ${summary.derivedHashRows ?? 0}`,
            `Verim Turetim: ${summary.derivedEffRows ?? 0}`,
            `Guc Turetim: ${summary.derivedPowerRows ?? 0}`,
            `Kismi Satir: ${summary.partialRows ?? 0}`,
            `Limit Truncate: ${summary.truncatedRows ?? 0}`
        ];
        if ((summary.responseRowsLimited || false) === true) {
            chips.push(`API Row Limiti: ${summary.responseRows ?? 0}/${summary.mergedRecords ?? 0}`);
        }

        const uploadSkipped = summary.uploadSkipped || {};
        const uploadWarnings = [];
        if ((uploadSkipped.nonCsv || 0) > 0) uploadWarnings.push(`CSV disi dosya: ${uploadSkipped.nonCsv}`);
        if ((uploadSkipped.tooLarge || 0) > 0) uploadWarnings.push(`Buyuk dosya: ${uploadSkipped.tooLarge}`);
        if ((uploadSkipped.totalOverflow || 0) > 0) uploadWarnings.push(`Toplam limit asimi: ${uploadSkipped.totalOverflow}`);
        if ((uploadSkipped.countOverflow || 0) > 0) uploadWarnings.push(`Adet limiti asimi: ${uploadSkipped.countOverflow}`);
        if ((uploadSkipped.uploadError || 0) > 0) uploadWarnings.push(`Upload hatasi: ${uploadSkipped.uploadError}`);
        if ((summary.responseRowsDropped || 0) > 0) {
            uploadWarnings.push(`UI performansi icin ${summary.responseRowsDropped} satir cevaptan cikarildi.`);
        }

        const fileWarnings = state.files
            .map((file) => {
                const stats = file.stats || {};
                const issues = [];
                if (Array.isArray(stats.missingRequiredColumns) && stats.missingRequiredColumns.length) {
                    issues.push(`Eksik kolon: ${stats.missingRequiredColumns.join(', ')}`);
                }
                if ((stats.skippedRows || 0) > 0) issues.push(`Atlanan satir: ${stats.skippedRows}`);
                if (stats.usedTempAsVr) issues.push('VR yerine Temp fallback kullanildi');
                if ((stats.parseTimedOut || false) === true) issues.push('Parse timeout');
                return issues.length ? `${file.name}: ${issues.join(' | ')}` : null;
            })
            .filter(Boolean);

        refs.qualitySummary.innerHTML = `
            <h3>Veri Kalite Ozeti</h3>
            <div class="summary-chips">
                ${chips.map((chip) => `<span class="summary-chip">${escapeHtml(chip)}</span>`).join('')}
            </div>
            ${uploadWarnings.length || fileWarnings.length ? `
                <ul class="summary-warning-list">
                    ${uploadWarnings.map((w) => `<li>${escapeHtml(w)}</li>`).join('')}
                    ${fileWarnings.map((w) => `<li>${escapeHtml(w)}</li>`).join('')}
                </ul>
            ` : '<p>Kritik veri sorunu tespit edilmedi.</p>'}
        `;
    }

    function renderKpiCard(container, label, row, accent) {
        if (!container) return;
        if (!row) {
            container.innerHTML = `<div class="kpi-label">${escapeHtml(label)}</div><div class="kpi-value">-</div><div class="kpi-meta">Veri yok</div>`;
            return;
        }

        const hash = formatValue(row.h, 0);
        const v = formatCompact(row.v);
        const f = formatCompact(row.f);
        const e = formatValue(row.e, 2);

        container.innerHTML = `
            <div class="kpi-label" style="color:${accent};">${escapeHtml(label)}</div>
            <div class="kpi-value">${hash} <span style="font-size:0.95rem;color:#94a3b8">GH/s</span></div>
            <div class="kpi-meta">${v} mV | ${f} MHz | ${e} J/TH</div>
        `;
    }

    function renderKpis() {
        renderKpiCard(refs.kpiMaster, 'Master Selection', state.recommendations?.masterSelection, '#d946ef');
        renderKpiCard(refs.kpiMaxHash, 'Maximum Hash', state.recommendations?.maxHash, '#ef4444');
        renderKpiCard(refs.kpiBestEff, 'Best Efficiency', state.recommendations?.bestEfficiency, '#10b981');
    }

    function destroyCharts() {
        Object.values(state.charts).forEach((chart) => {
            if (chart && typeof chart.destroy === 'function') {
                chart.destroy();
            }
        });
        state.charts = {};
    }

    function createChart(id, config) {
        const canvas = document.getElementById(id);
        if (!canvas || typeof Chart !== 'function') return;
        if (state.charts[id]) state.charts[id].destroy();
        state.charts[id] = new Chart(canvas, config);
    }

    function sampleRows(rows, maxPoints) {
        if (!Array.isArray(rows) || rows.length <= maxPoints) return rows;
        if (maxPoints <= 0) return [];

        const sampled = [];
        const step = rows.length / maxPoints;
        for (let i = 0; i < maxPoints; i += 1) {
            const idx = Math.min(rows.length - 1, Math.floor(i * step));
            sampled.push(rows[idx]);
        }
        return sampled;
    }

    function renderCharts() {
        if (typeof Chart !== 'function') return;
        Chart.defaults.color = '#94a3b8';
        Chart.defaults.borderColor = '#334155';
        Chart.defaults.font.family = "'Inter', sans-serif";

        const rows = state.consolidatedData;
        if (!rows.length) {
            destroyCharts();
            return;
        }

        const chartRows = sampleRows(rows, 3000);
        const master = chartRows.filter((row) => row.source === 'master');
        const high = chartRows.filter((row) => row.source === 'legacy_high');
        const archive = chartRows.filter((row) => row.source !== 'master' && row.source !== 'legacy_high');

        createChart('chartScatter', {
            type: 'bubble',
            data: {
                datasets: [
                    {
                        label: 'Master',
                        data: master.map((row) => ({ x: Number(row.f), y: Number(row.v), r: Math.max(4, Math.min(15, Number(row.h) / 320)) })),
                        backgroundColor: 'rgba(217,70,239,0.75)'
                    },
                    {
                        label: 'High',
                        data: high.map((row) => ({ x: Number(row.f), y: Number(row.v), r: Math.max(4, Math.min(15, Number(row.h) / 320)) })),
                        backgroundColor: 'rgba(239,68,68,0.75)'
                    },
                    {
                        label: 'Archive',
                        data: archive.map((row) => ({ x: Number(row.f), y: Number(row.v), r: Math.max(3, Math.min(13, Number(row.h) / 340)) })),
                        backgroundColor: 'rgba(71,85,105,0.65)'
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: { title: { display: true, text: 'Frekans (MHz)' } },
                    y: { title: { display: true, text: 'Voltaj (mV)' } }
                }
            }
        });

        createChart('chartEff', {
            type: 'scatter',
            data: {
                datasets: [
                    {
                        label: 'Master',
                        data: master.filter((row) => isFiniteNumber(row.e)).map((row) => ({ x: Number(row.h), y: Number(row.e) })),
                        backgroundColor: '#d946ef'
                    },
                    {
                        label: 'Digerleri',
                        data: [...high, ...archive].filter((row) => isFiniteNumber(row.e)).map((row) => ({ x: Number(row.h), y: Number(row.e) })),
                        backgroundColor: '#475569'
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: { title: { display: true, text: 'Hashrate (GH/s)' } },
                    y: { title: { display: true, text: 'J/TH' }, reverse: true }
                }
            }
        });

        const byHash = [...chartRows].sort((a, b) => Number(a.h) - Number(b.h));
        createChart('chartPower', {
            type: 'line',
            data: {
                labels: byHash.map((row) => formatValue(row.h, 0)),
                datasets: [{
                    label: 'Guc (W)',
                    data: byHash.map((row) => (isFiniteNumber(row.p) ? Number(row.p) : null)),
                    borderColor: '#10b981',
                    backgroundColor: 'rgba(16,185,129,0.2)',
                    fill: true,
                    tension: 0.3,
                    pointRadius: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: { display: false },
                    y: { title: { display: true, text: 'Watt' } }
                },
                plugins: { legend: { display: false } }
            }
        });

        const buckets = new Map();
        chartRows.forEach((row) => {
            const freq = Number(row.f);
            const hash = Number(row.h);
            if (!Number.isFinite(freq) || !Number.isFinite(hash)) return;
            const bucket = Math.floor(freq / 50) * 50;
            const prev = buckets.get(bucket) || { sum: 0, count: 0 };
            prev.sum += hash;
            prev.count += 1;
            buckets.set(bucket, prev);
        });

        const bucketKeys = Array.from(buckets.keys()).sort((a, b) => a - b);
        createChart('chartFreq', {
            type: 'bar',
            data: {
                labels: bucketKeys.map((start) => `${start}-${start + 50}`),
                datasets: [{
                    label: 'Ort. Hash',
                    data: bucketKeys.map((key) => {
                        const item = buckets.get(key);
                        return item && item.count ? item.sum / item.count : 0;
                    }),
                    backgroundColor: '#f59e0b',
                    borderRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: { y: { title: { display: true, text: 'GH/s' } } }
            }
        });
    }

    function getSortValue(row, key) {
        if (key === 'source') {
            if (row.source === 'master') return 0;
            if (row.source === 'legacy_high') return 1;
            return 2;
        }
        const value = row[key];
        if (typeof value === 'number') {
            return Number.isFinite(value) ? value : null;
        }
        return Number.isFinite(Number(value)) ? Number(value) : null;
    }

    function compareRows(a, b) {
        const key = state.tableSort.key;
        const dir = state.tableSort.dir;

        const av = getSortValue(a, key);
        const bv = getSortValue(b, key);

        if (key === 'source') {
            const delta = Number(av) - Number(bv);
            return dir === 'asc' ? delta : -delta;
        }

        if (av === null && bv === null) return 0;
        if (av === null) return 1;
        if (bv === null) return -1;
        if (av === bv) return 0;

        return dir === 'asc' ? av - bv : bv - av;
    }

    function parseFilterNumber(inputEl) {
        const raw = String(inputEl?.value ?? '').trim();
        if (raw === '') return NaN;
        const value = Number(raw);
        return Number.isFinite(value) ? value : NaN;
    }

    function applyFilters(rows) {
        const vMin = parseFilterNumber(refs.filterVMin);
        const fMin = parseFilterNumber(refs.filterFMin);
        const hMin = parseFilterNumber(refs.filterHMin);
        const errMax = parseFilterNumber(refs.filterEMax);

        const hasFilter = Number.isFinite(vMin)
            || Number.isFinite(fMin)
            || Number.isFinite(hMin)
            || Number.isFinite(errMax);
        if (!hasFilter) {
            return rows.slice();
        }

        return rows.filter((row) => {
            const v = Number(row.v);
            const f = Number(row.f);
            const h = Number(row.h);
            const err = Number(row.err);

            if (Number.isFinite(vMin) && !(v >= vMin)) return false;
            if (Number.isFinite(fMin) && !(f >= fMin)) return false;
            if (Number.isFinite(hMin) && !(h >= hMin)) return false;
            if (Number.isFinite(errMax) && !(err <= errMax)) return false;
            return true;
        });
    }

    function renderSortIndicators() {
        const buttons = refs.tableHead?.querySelectorAll('[data-sort-key]') || [];
        buttons.forEach((button) => {
            const key = button.getAttribute('data-sort-key');
            button.classList.toggle('active', key === state.tableSort.key);
        });
    }

    function renderTable() {
        if (!refs.tableBody) return;

        if (!state.consolidatedData.length) {
            refs.tableBody.innerHTML = '<tr><td colspan="8" class="empty">Veri bekleniyor...</td></tr>';
            refs.loadMoreBtn?.classList.add('hidden');
            return;
        }

        const filtered = applyFilters(state.consolidatedData).sort(compareRows);
        const visible = filtered.slice(0, state.visibleRows);

        const rowsHtml = visible.map((row) => {
            const source = String(row.source || 'archive');
            const rowClass = source === 'master' ? 'row-master' : (source === 'legacy_high' ? 'row-high' : '');
            const badgeClass = source === 'master' ? 'badge master' : (source === 'legacy_high' ? 'badge high' : 'badge archive');
            const badge = source === 'master' ? 'MASTER' : (source === 'legacy_high' ? 'HIGH' : 'ARCHIVE');

            return `
                <tr class="${rowClass}">
                    <td><span class="${badgeClass}">${badge}</span></td>
                    <td>${formatCompact(row.v)}</td>
                    <td>${formatCompact(row.f)}</td>
                    <td>${formatValue(row.h, 0)}</td>
                    <td>${formatCompact(row.vr)}</td>
                    <td>${formatValue(row.err, 2)}%</td>
                    <td>${formatValue(row.e, 2)}</td>
                    <td>${formatValue(row.score, 0)}</td>
                </tr>
            `;
        }).join('');

        refs.tableBody.innerHTML = rowsHtml || '<tr><td colspan="8" class="empty">Filtreye uygun satir yok.</td></tr>';

        if (filtered.length > state.visibleRows) {
            refs.loadMoreBtn?.classList.remove('hidden');
        } else {
            refs.loadMoreBtn?.classList.add('hidden');
        }
    }

    function scheduleTableRender({ resetRows = false } = {}) {
        if (resetRows) {
            state.visibleRows = DEFAULT_VISIBLE_ROWS;
        }

        if (state.renderRafId) {
            cancelAnimationFrame(state.renderRafId);
        }

        state.renderRafId = requestAnimationFrame(() => {
            state.renderRafId = 0;
            renderTable();
        });
    }

    function scheduleFilteredTableRender() {
        if (state.filterDebounceTimer) {
            clearTimeout(state.filterDebounceTimer);
        }
        state.filterDebounceTimer = setTimeout(() => {
            state.filterDebounceTimer = 0;
            scheduleTableRender({ resetRows: true });
        }, FILTER_INPUT_DEBOUNCE_MS);
    }

    function setTableSort(nextKey) {
        if (!nextKey) return;
        if (state.tableSort.key === nextKey) {
            state.tableSort.dir = state.tableSort.dir === 'asc' ? 'desc' : 'asc';
        } else {
            state.tableSort = { key: nextKey, dir: 'desc' };
        }
        renderSortIndicators();
        scheduleTableRender();
    }

    function renderAll() {
        renderSummary();
        renderKpis();
        renderCharts();
        renderTable();
    }

    function bindEvents() {
        refs.fileInput.addEventListener('change', (event) => {
            addFiles(event.target.files);
            refs.fileInput.value = '';
        });

        refs.selectedFiles.addEventListener('click', (event) => {
            const removeBtn = event.target.closest('[data-action="remove"]');
            if (!removeBtn) return;
            removeSelectedFile(removeBtn.getAttribute('data-id') || '');
        });

        refs.selectedFiles.addEventListener('change', (event) => {
            const target = event.target;
            if (!(target instanceof HTMLInputElement)) return;
            if (target.name !== 'masterFile') return;
            state.masterClientId = target.value;
            renderSelectionHint();
        });

        refs.analyzeBtn.addEventListener('click', analyzeOnServer);

        refs.tableHead?.addEventListener('click', (event) => {
            const button = event.target.closest('[data-sort-key]');
            if (!button) return;
            setTableSort(button.getAttribute('data-sort-key'));
        });

        refs.loadMoreBtn?.addEventListener('click', () => {
            state.visibleRows += 30;
            scheduleTableRender();
        });

        [refs.filterVMin, refs.filterFMin, refs.filterHMin, refs.filterEMax].forEach((input) => {
            input?.addEventListener('input', scheduleFilteredTableRender);
        });
    }

    function init() {
        bindEvents();
        renderSelectionHint();
        renderSortIndicators();
        renderAll();
        showAlert(`Uygulama hazir (${meta.appVersion}). CSV secip analizi baslatin.`, 'info');
    }

    init();
})();
