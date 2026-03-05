<?php

declare(strict_types=1);

namespace BitaxeOc\App;

use RuntimeException;

final class Analyzer
{
    private int $maxFilesPerBatch;
    private int $maxFileBytes;
    private int $maxTotalBytes;
    private int $csvMaxDataRows;
    private int $csvParseTimeBudgetMs;
    private bool $collectTimeSeries;
    private int $maxTimeSeriesPoints;
    private bool $responseIncludeFileRows;
    private int $responseMaxRows;

    public function __construct(array $limits = [])
    {
        $this->maxFilesPerBatch = (int)($limits['max_files_per_batch'] ?? 50);
        $this->maxFileBytes = (int)($limits['max_file_bytes'] ?? (500 * 1024));
        $this->maxTotalBytes = (int)($limits['max_total_bytes'] ?? (10 * 1024 * 1024));
        $this->csvMaxDataRows = (int)($limits['csv_max_data_rows'] ?? 10000);
        $this->csvParseTimeBudgetMs = (int)($limits['csv_parse_time_budget_ms'] ?? 12000);
        $this->collectTimeSeries = (bool)($limits['collect_time_series'] ?? false);
        $this->maxTimeSeriesPoints = max(0, (int)($limits['max_time_series_points'] ?? 1200));
        $this->responseIncludeFileRows = (bool)($limits['response_include_file_rows'] ?? false);
        $this->responseMaxRows = max(500, (int)($limits['response_max_rows'] ?? 15000));
    }

    public function analyze(array $uploads, ?int $requestedMasterIndex = null): array
    {
        if (count($uploads) === 0) {
            throw new RuntimeException('En az bir CSV dosyasi yuklemelisiniz.');
        }

        $skipped = [
            'nonCsv' => 0,
            'tooLarge' => 0,
            'totalOverflow' => 0,
            'uploadError' => 0,
            'countOverflow' => 0,
        ];

        $accepted = [];
        $totalBytes = 0;

        foreach ($uploads as $upload) {
            if (!$this->isValidUploadArray($upload)) {
                $skipped['uploadError']++;
                continue;
            }

            if (count($accepted) >= $this->maxFilesPerBatch) {
                $skipped['countOverflow']++;
                continue;
            }

            $errorCode = (int)$upload['error'];
            if ($errorCode !== UPLOAD_ERR_OK) {
                $skipped['uploadError']++;
                continue;
            }

            $name = (string)$upload['name'];
            $mime = (string)$upload['type'];
            $size = max(0, (int)$upload['size']);

            if (!$this->isCsvLikeFile($name, $mime)) {
                $skipped['nonCsv']++;
                continue;
            }

            if ($size <= 0 || $size > $this->maxFileBytes) {
                $skipped['tooLarge']++;
                continue;
            }

            if (($totalBytes + $size) > $this->maxTotalBytes) {
                $skipped['totalOverflow']++;
                continue;
            }

            $tmpName = (string)$upload['tmp_name'];
            if ($tmpName === '' || !is_uploaded_file($tmpName)) {
                $skipped['uploadError']++;
                continue;
            }

            $totalBytes += $size;
            $accepted[] = $upload;
        }

        if (count($accepted) === 0) {
            throw new RuntimeException('Islenebilir CSV dosyasi bulunamadi.');
        }

        $fileRecords = [];
        $baseTsMs = (int)round(microtime(true) * 1000);

        foreach ($accepted as $index => $upload) {
            $parsed = $this->parseCsvFile((string)$upload['tmp_name']);
            $safeName = $this->sanitizeFileName((string)$upload['name']);
            $id = $this->generateServerFileId($safeName, $baseTsMs + $index, $index);

            $fileRecords[] = [
                'id' => $id,
                'name' => $safeName,
                'lastModified' => $baseTsMs + $index,
                'isMaster' => false,
                'enabled' => true,
                'stats' => $parsed['stats'],
                'data' => $parsed['data'],
                'timeSeries' => $parsed['timeSeries'],
            ];
        }

        if (count($fileRecords) === 0) {
            throw new RuntimeException('Yuklenen dosyalardan veri okunamadi.');
        }

        $masterIndex = $this->resolveMasterIndex($requestedMasterIndex, count($fileRecords));
        foreach ($fileRecords as $idx => &$fileRecord) {
            $fileRecord['isMaster'] = ($idx === $masterIndex);
        }
        unset($fileRecord);

        $consolidatedData = $this->mergeData($fileRecords);
        $fullConsolidatedCount = count($consolidatedData);
        $responseConsolidatedData = $consolidatedData;
        $responseRowsDropped = 0;
        if ($fullConsolidatedCount > $this->responseMaxRows) {
            $responseRowsDropped = $fullConsolidatedCount - $this->responseMaxRows;
            $responseConsolidatedData = array_slice($consolidatedData, 0, $this->responseMaxRows);
        }

        $recommendations = $this->buildRecommendations($consolidatedData);
        $summary = $this->buildSummary($fileRecords, $consolidatedData, $skipped);
        $summary['responseRows'] = count($responseConsolidatedData);
        $summary['responseRowsDropped'] = $responseRowsDropped;
        $summary['responseRowsLimited'] = ($responseRowsDropped > 0);

        $responseFiles = [];
        foreach ($fileRecords as $fileRecord) {
            $payload = [
                'id' => (string)($fileRecord['id'] ?? ''),
                'name' => (string)($fileRecord['name'] ?? ''),
                'lastModified' => (int)($fileRecord['lastModified'] ?? 0),
                'isMaster' => !empty($fileRecord['isMaster']),
                'enabled' => !empty($fileRecord['enabled']),
                'stats' => is_array($fileRecord['stats'] ?? null) ? $fileRecord['stats'] : [],
                'rowCount' => is_array($fileRecord['data'] ?? null) ? count($fileRecord['data']) : 0,
            ];

            if ($this->responseIncludeFileRows) {
                $payload['data'] = is_array($fileRecord['data'] ?? null) ? $fileRecord['data'] : [];
                if ($this->collectTimeSeries) {
                    $payload['timeSeries'] = is_array($fileRecord['timeSeries'] ?? null) ? $fileRecord['timeSeries'] : [];
                }
            }

            $responseFiles[] = $payload;
        }

        return [
            'files' => $responseFiles,
            'consolidatedData' => $responseConsolidatedData,
            'recommendations' => $recommendations,
            'summary' => $summary,
            'upload' => [
                'attemptedFiles' => count($uploads),
                'acceptedFiles' => count($accepted),
                'acceptedBytes' => $totalBytes,
                'skipped' => $skipped,
            ],
            'limits' => [
                'maxFilesPerBatch' => $this->maxFilesPerBatch,
                'maxFileBytes' => $this->maxFileBytes,
                'maxTotalBytes' => $this->maxTotalBytes,
                'csvMaxDataRows' => $this->csvMaxDataRows,
                'collectTimeSeries' => $this->collectTimeSeries,
                'responseMaxRows' => $this->responseMaxRows,
            ],
        ];
    }

    private function isValidUploadArray(mixed $upload): bool
    {
        if (!is_array($upload)) {
            return false;
        }

        $requiredKeys = ['name', 'type', 'tmp_name', 'error', 'size'];
        foreach ($requiredKeys as $key) {
            if (!array_key_exists($key, $upload)) {
                return false;
            }
        }

        return true;
    }

    private function sanitizeFileName(string $name): string
    {
        $name = trim($name);
        if ($name === '') {
            return 'untitled.csv';
        }

        $name = str_replace(["\0", "\r", "\n"], '', $name);
        $base = basename($name);

        if ($base === '' || $base === '.' || $base === '..') {
            return 'untitled.csv';
        }

        return substr($base, 0, 180);
    }

    private function generateServerFileId(string $safeName, int $tsMs, int $index): string
    {
        $safe = preg_replace('/[^a-zA-Z0-9_.-]/', '_', $safeName) ?? 'file';
        return sprintf('%s__%d_%d', $safe, $tsMs, $index);
    }

    private function resolveMasterIndex(?int $requestedMasterIndex, int $count): int
    {
        if ($count <= 0) {
            return 0;
        }

        if ($requestedMasterIndex !== null && $requestedMasterIndex >= 0 && $requestedMasterIndex < $count) {
            return $requestedMasterIndex;
        }

        return 0;
    }

    private function isCsvLikeFile(string $name, string $mime): bool
    {
        $nameLower = strtolower($name);
        $mimeLower = strtolower($mime);

        if (str_ends_with($nameLower, '.csv')) {
            return true;
        }

        if (str_contains($mimeLower, 'csv')) {
            return true;
        }

        return in_array($mimeLower, ['text/plain', 'application/vnd.ms-excel'], true);
    }

    private function parseCsvFile(string $path): array
    {
        if ($path === '' || !is_file($path) || !is_readable($path)) {
            return $this->parseCsvLines([]);
        }

        $rawLines = @file($path, FILE_IGNORE_NEW_LINES);
        if (!is_array($rawLines) || count($rawLines) === 0) {
            return $this->parseCsvLines([]);
        }

        if (isset($rawLines[0])) {
            $rawLines[0] = preg_replace('/^\xEF\xBB\xBF/u', '', (string)$rawLines[0]) ?? (string)$rawLines[0];
        }

        $lines = $this->collectNonEmptyLines($rawLines);
        return $this->parseCsvLines($lines);
    }

    private function collectNonEmptyLines(array $linesRaw): array
    {
        $lines = [];
        foreach ($linesRaw as $line) {
            $lineString = (string)$line;
            if (trim($lineString) !== '') {
                $lines[] = $lineString;
            }
        }
        return $lines;
    }

    private function parseCsvLines(array $lines): array
    {
        $stats = [
            'totalRows' => 0,
            'parsedRows' => 0,
            'skippedRows' => 0,
            'missingVrRows' => 0,
            'usedTempAsVr' => false,
            'missingRequiredColumns' => [],
            'derivedHashRows' => 0,
            'derivedEffRows' => 0,
            'derivedPowerRows' => 0,
            'missingErrRows' => 0,
            'partialRows' => 0,
            'truncatedRows' => 0,
            'parseTimedOut' => false,
        ];

        $timeSeries = [];

        if (count($lines) < 2) {
            return [
                'data' => [],
                'stats' => $stats,
                'timeSeries' => $timeSeries,
            ];
        }

        $delimiter = $this->detectCsvDelimiter($lines[0]);
        $headers = array_map(
            static fn ($item) => trim((string)$item),
            $this->splitCsvLine($lines[0], $delimiter)
        );
        $normalizedHeaders = array_map([$this, 'normalizeHeader'], $headers);

        $map = [
            'v' => $this->findHeaderIndex($normalizedHeaders, ['corevoltage', 'voltage', 'voltaj', 'vcore', 'mv', 'mvolt', 'vdd']),
            'f' => $this->findHeaderIndex($normalizedHeaders, ['frequency', 'freq', 'clock', 'mhz', 'pll']),
            'h' => $this->findHeaderIndex($normalizedHeaders, ['averagehashrate', 'hashrate', 'hash', 'ghs', 'ths', 'throughput']),
            't' => $this->findHeaderIndex($normalizedHeaders, ['averagetemperature', 'temperature', 'temp', 'asictemp', 'chiptemp']),
            'vr' => $this->findHeaderIndex($normalizedHeaders, ['averagevrtemp', 'vrmtemp', 'vrtemp', 'vrmtemperature', 'vrm', 'mosfettemp']),
            'e' => $this->findHeaderIndex($normalizedHeaders, ['efficiencyjth', 'efficiency', 'verim', 'jth', 'jgh', 'wth', 'wgh', 'eff']),
            'err' => $this->findHeaderIndex($normalizedHeaders, ['errorpercentage', 'errorrate', 'error', 'hata', 'hwerror', 'rejectrate', 'err']),
            'p' => $this->findHeaderIndex($normalizedHeaders, ['averagepower', 'power', 'watt', 'watts', 'pow', 'consumption', 'guc']),
            'ts' => $this->collectTimeSeries
                ? $this->findHeaderIndex($normalizedHeaders, ['timestamp', 'datetime', 'time', 'date', 'createdat'])
                : -1,
            'action' => $this->collectTimeSeries
                ? $this->findHeaderIndex($normalizedHeaders, ['action', 'event', 'state', 'status', 'reason'])
                : -1,
        ];

        if ($map['v'] < 0) {
            $stats['missingRequiredColumns'][] = 'missing_voltage';
        }
        if ($map['f'] < 0) {
            $stats['missingRequiredColumns'][] = 'missing_frequency';
        }
        if ($map['h'] < 0 && ($map['p'] < 0 || $map['e'] < 0)) {
            $stats['missingRequiredColumns'][] = 'missing_hash_or_pow_eff';
        }

        $stats['totalRows'] = max(0, count($lines) - 1);
        $data = [];

        $hashHeader = ($map['h'] >= 0) ? ($headers[$map['h']] ?? '') : '';
        $effHeader = ($map['e'] >= 0) ? ($headers[$map['e']] ?? '') : '';
        $powerHeader = ($map['p'] >= 0) ? ($headers[$map['p']] ?? '') : '';

        $maxLineIndex = min(count($lines) - 1, $this->csvMaxDataRows);
        if ($stats['totalRows'] > $this->csvMaxDataRows) {
            $stats['truncatedRows'] += ($stats['totalRows'] - $this->csvMaxDataRows);
        }

        $start = (int)round(microtime(true) * 1000);

        for ($i = 1; $i <= $maxLineIndex; $i++) {
            if (($i & 1023) === 0) {
                $elapsed = (int)round(microtime(true) * 1000) - $start;
                if ($elapsed > $this->csvParseTimeBudgetMs) {
                    $stats['parseTimedOut'] = true;
                    $remainingRows = $maxLineIndex - $i + 1;
                    if ($remainingRows > 0) {
                        $stats['truncatedRows'] += $remainingRows;
                    }
                    break;
                }
            }

            $cells = $this->splitCsvLine($lines[$i], $delimiter);

            $v = ($map['v'] >= 0) ? $this->parseNumber($cells[$map['v']] ?? null) : null;
            $f = ($map['f'] >= 0) ? $this->parseNumber($cells[$map['f']] ?? null) : null;
            $h = ($map['h'] >= 0) ? $this->convertHashToGh($this->parseNumber($cells[$map['h']] ?? null), $hashHeader) : null;
            $e = ($map['e'] >= 0) ? $this->convertEfficiencyToJth($this->parseNumber($cells[$map['e']] ?? null), $effHeader) : null;
            $p = ($map['p'] >= 0) ? $this->convertPowerToW($this->parseNumber($cells[$map['p']] ?? null), $powerHeader) : null;

            $rawErr = ($map['err'] >= 0) ? $this->parseNumber($cells[$map['err']] ?? null) : null;
            $err = $rawErr;

            $t = ($map['t'] >= 0) ? $this->parseNumber($cells[$map['t']] ?? null) : null;
            $rawVr = ($map['vr'] >= 0) ? $this->parseNumber($cells[$map['vr']] ?? null) : null;
            $vr = $rawVr;

            $derivedHash = false;
            $derivedEff = false;
            $derivedPower = false;

            if (!$this->isFinite($h)) {
                $inferredHash = $this->deriveHashFromPowerAndEfficiency($p, $e);
                if ($this->isFinite($inferredHash)) {
                    $h = $inferredHash;
                    $derivedHash = true;
                }
            }

            if (!$this->isFinite($e)) {
                $inferredEff = $this->deriveEfficiencyFromPowerAndHash($p, $h);
                if ($this->isFinite($inferredEff)) {
                    $e = $inferredEff;
                    $derivedEff = true;
                }
            }

            if (!$this->isFinite($p)) {
                $inferredPower = $this->derivePowerFromHashAndEfficiency($h, $e);
                if ($this->isFinite($inferredPower)) {
                    $p = $inferredPower;
                    $derivedPower = true;
                }
            }

            if (!$this->isFinite($err)) {
                $err = 0.0;
            }

            if (!$this->isFinite($vr) && $this->isFinite($t)) {
                $vr = $t;
                $stats['usedTempAsVr'] = true;
            }

            if (!$this->isFinite($t) && $this->isFinite($vr)) {
                $t = $vr;
            }

            $hasCoreRow = $this->isFinite($v) && $this->isFinite($f) && $this->isFinite($h);

            if ($hasCoreRow) {
                if (!$this->isFinite($rawErr)) {
                    $stats['missingErrRows']++;
                }
                if ($derivedHash) {
                    $stats['derivedHashRows']++;
                }
                if ($derivedEff) {
                    $stats['derivedEffRows']++;
                }
                if ($derivedPower) {
                    $stats['derivedPowerRows']++;
                }
                if (!$this->isFinite($vr)) {
                    $stats['missingVrRows']++;
                }
                if (!$this->isFinite($e) || !$this->isFinite($p)) {
                    $stats['partialRows']++;
                }

                $data[] = [
                    'v' => (float)$v,
                    'f' => (float)$f,
                    'h' => (float)$h,
                    't' => $this->finiteOrNull($t),
                    'vr' => $this->finiteOrNull($vr),
                    'e' => $this->finiteOrNull($e),
                    'err' => (float)$err,
                    'p' => $this->finiteOrNull($p),
                ];
                $stats['parsedRows']++;
            } else {
                $stats['skippedRows']++;
            }

            if ($this->collectTimeSeries && count($timeSeries) < $this->maxTimeSeriesPoints) {
                $tsCell = ($map['ts'] >= 0) ? ($cells[$map['ts']] ?? null) : null;
                $ts = $this->parseTimestampMs($tsCell);

                if ($ts !== null) {
                    $timePoint = [
                        'ts' => $ts,
                        'action' => ($map['action'] >= 0) ? trim((string)($cells[$map['action']] ?? '')) : '',
                        'h' => $this->finiteOrNull($h),
                        'err' => $this->finiteOrNull($rawErr),
                        'temp' => $this->pickFinite($vr, $t),
                        'p' => $this->finiteOrNull($p),
                    ];

                    $hasAnyMetric = ($timePoint['h'] !== null)
                        || ($timePoint['err'] !== null)
                        || ($timePoint['temp'] !== null)
                        || ($timePoint['p'] !== null);

                    if ($hasAnyMetric) {
                        $timeSeries[] = $timePoint;
                    }
                }
            }
        }

        return [
            'data' => $data,
            'stats' => $stats,
            'timeSeries' => $timeSeries,
        ];
    }

    private function splitCsvLine(string $line, string $delimiter): array
    {
        return str_getcsv($line, $delimiter, '"', '\\');
    }

    private function detectCsvDelimiter(string $headerLine): string
    {
        $candidates = [',', ';', "\t"];
        $bestDelimiter = ',';
        $bestCount = -1;

        foreach ($candidates as $candidate) {
            $count = $this->countDelimiterOutsideQuotes($headerLine, $candidate);
            if ($count > $bestCount) {
                $bestCount = $count;
                $bestDelimiter = $candidate;
            }
        }

        return $bestDelimiter;
    }

    private function countDelimiterOutsideQuotes(string $line, string $delimiter): int
    {
        $count = 0;
        $inQuotes = false;
        $len = strlen($line);

        for ($i = 0; $i < $len; $i++) {
            $ch = $line[$i];
            if ($ch === '"') {
                if ($inQuotes && $i + 1 < $len && $line[$i + 1] === '"') {
                    $i++;
                } else {
                    $inQuotes = !$inQuotes;
                }
                continue;
            }

            if (!$inQuotes && $ch === $delimiter) {
                $count++;
            }
        }

        return $count;
    }

    private function normalizeHeader(string $value): string
    {
        return preg_replace('/[^a-z0-9]/', '', strtolower($value)) ?? '';
    }

    private function findHeaderIndex(array $normalizedHeaders, array $aliases): int
    {
        foreach ($normalizedHeaders as $index => $header) {
            foreach ($aliases as $alias) {
                if (str_contains((string)$header, (string)$alias)) {
                    return (int)$index;
                }
            }
        }

        return -1;
    }

    private function parseNumber(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value) || is_float($value)) {
            return $this->isFinite($value) ? (float)$value : null;
        }

        $normalized = trim((string)$value);
        if ($normalized === '') {
            return null;
        }

        $normalized = str_replace(["\u{00A0}", ' '], '', $normalized);
        if (is_numeric($normalized)) {
            $parsed = (float)$normalized;
            return $this->isFinite($parsed) ? $parsed : null;
        }

        if (!preg_match('/[-+]?[0-9][0-9.,]*/', $normalized, $matches)) {
            return null;
        }

        $token = (string)$matches[0];
        $hasComma = str_contains($token, ',');
        $hasDot = str_contains($token, '.');

        if ($hasComma && $hasDot) {
            if (strrpos($token, ',') > strrpos($token, '.')) {
                $token = str_replace('.', '', $token);
                $token = str_replace(',', '.', $token);
            } else {
                $token = str_replace(',', '', $token);
            }
        } elseif ($hasComma) {
            $parts = explode(',', $token);
            if (count($parts) === 2 && strlen($parts[1]) <= 4) {
                $token = str_replace(',', '', $parts[0]) . '.' . $parts[1];
            } else {
                $token = str_replace(',', '', $token);
            }
        } elseif ($hasDot) {
            $parts = explode('.', $token);
            if (count($parts) > 2) {
                $decimals = array_pop($parts);
                $token = implode('', $parts) . '.' . $decimals;
            }
        }

        $parsed = (float)$token;
        return $this->isFinite($parsed) ? $parsed : null;
    }

    private function convertHashToGh(?float $value, string $rawHeader): ?float
    {
        if (!$this->isFinite($value)) {
            return null;
        }

        $header = $this->normalizeHeader($rawHeader);
        if (str_contains($header, 'ths') || str_contains($header, 'thash')) {
            return $value * 1000.0;
        }
        if (str_contains($header, 'mhs') || str_contains($header, 'mhash')) {
            return $value / 1000.0;
        }
        if (str_contains($header, 'khs') || str_contains($header, 'khash')) {
            return $value / 1000000.0;
        }

        return $value;
    }

    private function convertEfficiencyToJth(?float $value, string $rawHeader): ?float
    {
        if (!$this->isFinite($value)) {
            return null;
        }

        $header = $this->normalizeHeader($rawHeader);
        if (str_contains($header, 'jgh') || str_contains($header, 'wgh')) {
            return $value * 1000.0;
        }

        return $value;
    }

    private function convertPowerToW(?float $value, string $rawHeader): ?float
    {
        if (!$this->isFinite($value)) {
            return null;
        }

        $header = $this->normalizeHeader($rawHeader);
        if (str_contains($header, 'kw')) {
            return $value * 1000.0;
        }

        return $value;
    }

    private function deriveHashFromPowerAndEfficiency(?float $powerW, ?float $efficiencyJth): ?float
    {
        if (!$this->isFinite($powerW) || !$this->isFinite($efficiencyJth) || $efficiencyJth <= 0) {
            return null;
        }

        return ($powerW * 1000.0) / $efficiencyJth;
    }

    private function deriveEfficiencyFromPowerAndHash(?float $powerW, ?float $hashGh): ?float
    {
        if (!$this->isFinite($powerW) || !$this->isFinite($hashGh) || $hashGh <= 0) {
            return null;
        }

        return ($powerW * 1000.0) / $hashGh;
    }

    private function derivePowerFromHashAndEfficiency(?float $hashGh, ?float $efficiencyJth): ?float
    {
        if (!$this->isFinite($hashGh) || !$this->isFinite($efficiencyJth) || $hashGh <= 0) {
            return null;
        }

        return ($hashGh * $efficiencyJth) / 1000.0;
    }

    private function parseTimestampMs(mixed $value): ?int
    {
        if ($value === null) {
            return null;
        }

        $raw = trim((string)$value);
        if ($raw === '') {
            return null;
        }

        if (is_numeric($raw)) {
            $num = (float)$raw;
            if ($num > 1000000000000) {
                return (int)round($num);
            }
            if ($num > 1000000000) {
                return (int)round($num * 1000);
            }
        }

        $ts = strtotime($raw);
        if ($ts === false) {
            return null;
        }

        return (int)$ts * 1000;
    }

    private function mergeData(array $fileRecords): array
    {
        $map = [];
        $enabledFiles = array_values(array_filter($fileRecords, static fn ($file) => !empty($file['enabled'])));

        $masterFile = null;
        foreach ($enabledFiles as $file) {
            if (!empty($file['isMaster'])) {
                $masterFile = $file;
                break;
            }
        }

        foreach ($enabledFiles as $file) {
            if (!empty($file['isMaster'])) {
                continue;
            }

            $rows = is_array($file['data'] ?? null) ? $file['data'] : [];
            foreach ($rows as $row) {
                $key = $this->makeVfKey($row['v'] ?? null, $row['f'] ?? null);
                if ($key === '') {
                    continue;
                }

                $hash = $this->asFloat($row['h'] ?? null);
                $candidate = [
                    'v' => $this->asFloat($row['v'] ?? null),
                    'f' => $this->asFloat($row['f'] ?? null),
                    'h' => $hash,
                    't' => $this->finiteOrNull($row['t'] ?? null),
                    'vr' => $this->finiteOrNull($row['vr'] ?? null),
                    'e' => $this->finiteOrNull($row['e'] ?? null),
                    'err' => $this->finiteOrNull($row['err'] ?? null) ?? 0.0,
                    'p' => $this->finiteOrNull($row['p'] ?? null),
                    'source' => ($hash !== null && $hash > 4000.0) ? 'legacy_high' : 'archive',
                    'sourceFileId' => (string)($file['id'] ?? ''),
                    'sourceFileName' => (string)($file['name'] ?? ''),
                ];

                if (!array_key_exists($key, $map)) {
                    $map[$key] = $candidate;
                    continue;
                }

                $existing = $map[$key];
                $candidateScore = $this->calculateMergePriorityScore($candidate);
                $existingScore = $this->calculateMergePriorityScore($existing);

                $candidateHash = $this->asFloat($candidate['h'] ?? null) ?? -INF;
                $existingHash = $this->asFloat($existing['h'] ?? null) ?? -INF;

                $preferCandidate = ($candidateScore > $existingScore)
                    || ($candidateScore === $existingScore && $candidateHash > $existingHash);

                if ($preferCandidate) {
                    $map[$key] = $candidate;
                }
            }
        }

        if (is_array($masterFile)) {
            $rows = is_array($masterFile['data'] ?? null) ? $masterFile['data'] : [];
            foreach ($rows as $row) {
                $key = $this->makeVfKey($row['v'] ?? null, $row['f'] ?? null);
                if ($key === '') {
                    continue;
                }

                $map[$key] = [
                    'v' => $this->asFloat($row['v'] ?? null),
                    'f' => $this->asFloat($row['f'] ?? null),
                    'h' => $this->asFloat($row['h'] ?? null),
                    't' => $this->finiteOrNull($row['t'] ?? null),
                    'vr' => $this->finiteOrNull($row['vr'] ?? null),
                    'e' => $this->finiteOrNull($row['e'] ?? null),
                    'err' => $this->finiteOrNull($row['err'] ?? null) ?? 0.0,
                    'p' => $this->finiteOrNull($row['p'] ?? null),
                    'source' => 'master',
                    'sourceFileId' => (string)($masterFile['id'] ?? ''),
                    'sourceFileName' => (string)($masterFile['name'] ?? ''),
                ];
            }
        }

        $consolidated = [];
        foreach (array_values($map) as $row) {
            $row['score'] = $this->calculateScore($row);
            $consolidated[] = $row;
        }

        usort($consolidated, static function (array $a, array $b): int {
            return ((int)($b['score'] ?? -9999)) <=> ((int)($a['score'] ?? -9999));
        });

        return $consolidated;
    }

    private function buildSummary(array $files, array $consolidatedData, array $skipped): array
    {
        $totals = [
            'fileCount' => count($files),
            'activeCount' => count(array_filter($files, static fn ($file) => !empty($file['enabled']))),
            'totalRows' => 0,
            'parsedRows' => 0,
            'skippedRows' => 0,
            'missingVrRows' => 0,
            'derivedHashRows' => 0,
            'derivedEffRows' => 0,
            'derivedPowerRows' => 0,
            'missingErrRows' => 0,
            'partialRows' => 0,
            'truncatedRows' => 0,
            'parseTimedOutFiles' => 0,
            'mergedRecords' => count($consolidatedData),
            'uploadSkipped' => $skipped,
        ];

        foreach ($files as $file) {
            $stats = is_array($file['stats'] ?? null) ? $file['stats'] : [];
            $totals['totalRows'] += (int)($stats['totalRows'] ?? 0);
            $totals['parsedRows'] += (int)($stats['parsedRows'] ?? 0);
            $totals['skippedRows'] += (int)($stats['skippedRows'] ?? 0);
            $totals['missingVrRows'] += (int)($stats['missingVrRows'] ?? 0);
            $totals['derivedHashRows'] += (int)($stats['derivedHashRows'] ?? 0);
            $totals['derivedEffRows'] += (int)($stats['derivedEffRows'] ?? 0);
            $totals['derivedPowerRows'] += (int)($stats['derivedPowerRows'] ?? 0);
            $totals['missingErrRows'] += (int)($stats['missingErrRows'] ?? 0);
            $totals['partialRows'] += (int)($stats['partialRows'] ?? 0);
            $totals['truncatedRows'] += (int)($stats['truncatedRows'] ?? 0);
            if (!empty($stats['parseTimedOut'])) {
                $totals['parseTimedOutFiles']++;
            }
        }

        return $totals;
    }

    private function buildRecommendations(array $consolidatedData): array
    {
        $maxHash = $this->pickMaxHashRow($consolidatedData);
        $masterSelection = $this->pickMasterSelectionRow($consolidatedData);
        $bestEfficiency = $this->pickBestEfficiencyRow($consolidatedData, $masterSelection);

        return [
            'masterSelection' => $masterSelection,
            'maxHash' => $maxHash,
            'bestEfficiency' => $bestEfficiency,
        ];
    }

    private function pickMasterSelectionRow(array $rows): ?array
    {
        if (count($rows) === 0) {
            return null;
        }

        $ranked = $rows;
        usort($ranked, static function (array $a, array $b): int {
            return ((int)($b['score'] ?? -9999)) <=> ((int)($a['score'] ?? -9999));
        });

        $maxHash = $this->pickMaxHashRow($rows);
        $first = $ranked[0] ?? null;
        if (!is_array($first)) {
            return null;
        }

        if (!is_array($maxHash)) {
            return $first;
        }

        $maxHashKey = $this->rowVfKey($maxHash);
        if ($this->rowVfKey($first) !== $maxHashKey) {
            return $first;
        }

        foreach ($ranked as $row) {
            if ($this->rowVfKey($row) !== $maxHashKey) {
                return $row;
            }
        }

        return $first;
    }

    private function pickMaxHashRow(array $rows): ?array
    {
        if (count($rows) === 0) {
            return null;
        }

        $copy = $rows;
        usort($copy, function (array $a, array $b): int {
            $aHash = $this->asFloat($a['h'] ?? null) ?? -INF;
            $bHash = $this->asFloat($b['h'] ?? null) ?? -INF;
            if ($aHash === $bHash) {
                return 0;
            }
            return ($aHash < $bHash) ? 1 : -1;
        });

        return $copy[0] ?? null;
    }

    private function pickBestEfficiencyRow(array $rows, ?array $fallback): ?array
    {
        $filtered = array_values(array_filter($rows, function (array $row): bool {
            $hash = $this->asFloat($row['h'] ?? null);
            $eff = $this->asFloat($row['e'] ?? null);
            return $hash !== null && $hash > 2000.0 && $eff !== null;
        }));

        if (count($filtered) === 0) {
            return $fallback;
        }

        usort($filtered, function (array $a, array $b): int {
            $aEff = $this->asFloat($a['e'] ?? null) ?? INF;
            $bEff = $this->asFloat($b['e'] ?? null) ?? INF;
            if ($aEff === $bEff) {
                return 0;
            }
            return ($aEff < $bEff) ? -1 : 1;
        });

        return $filtered[0] ?? $fallback;
    }

    private function evaluateBm1370Row(array $row): array
    {
        $h = $this->asFloat($row['h'] ?? null);
        $e = $this->asFloat($row['e'] ?? null);
        $vr = $this->asFloat($row['vr'] ?? null);
        $err = $this->asFloat($row['err'] ?? null);
        $v = $this->asFloat($row['v'] ?? null);
        $f = $this->asFloat($row['f'] ?? null);
        $p = $this->asFloat($row['p'] ?? null);

        $hashBalanced = $this->clamp01($this->bandScore($h, 2600, 3450, 4100));
        $hashPush = $this->clamp01($this->higherBetterScore($h, 3600, 4700));
        $eff = ($e !== null) ? $this->clamp01($this->lowerBetterScore($e, 15.8, 21.5)) : 0.45;
        $temp = ($vr !== null) ? $this->clamp01($this->lowerBetterScore($vr, 55, 72)) : 0.45;
        $error = ($err !== null) ? $this->clamp01($this->lowerBetterScore($err, 0.35, 2.5)) : 0.4;
        $voltage = ($v !== null) ? $this->clamp01($this->bandScore($v, 1220, 1310, 1390)) : 0.45;
        $freq = ($f !== null) ? $this->clamp01($this->bandScore($f, 720, 860, 1020)) : 0.45;
        $power = ($p !== null) ? $this->clamp01($this->bandScore($p, 45, 60, 78)) : 0.45;
        $electrical = $this->averageScores([$voltage, $freq, $power], 0.45);

        $ocLevel = $this->averageScores([
            $this->higherBetterScore($f, 980, 1120),
            $this->higherBetterScore($v, 1360, 1450),
            $this->higherBetterScore($h, 4000, 4800),
        ], 0.0);

        $riskLevel = $this->averageScores([
            $this->higherBetterScore($vr, 65, 82),
            $this->higherBetterScore($err, 1.2, 6),
        ], 0.0);

        return [
            'hashBalanced' => $hashBalanced,
            'hashPush' => $hashPush,
            'eff' => $eff,
            'temp' => $temp,
            'error' => $error,
            'voltage' => $voltage,
            'freq' => $freq,
            'power' => $power,
            'electrical' => $electrical,
            'ocLevel' => $ocLevel,
            'riskLevel' => $riskLevel,
        ];
    }

    private function calculateScore(array $row): int
    {
        $hash = $this->asFloat($row['h'] ?? null);
        if ($hash === null) {
            return -9999;
        }

        $metrics = $this->evaluateBm1370Row($row);

        $score = 0.0;
        $score += $metrics['hashBalanced'] * 26;
        $score += $metrics['eff'] * 26;
        $score += $metrics['temp'] * 21;
        $score += $metrics['error'] * 17;
        $score += $metrics['electrical'] * 10;

        $score -= $metrics['ocLevel'] * 12;
        $score -= $metrics['riskLevel'] * 18;

        $source = (string)($row['source'] ?? '');
        if ($source === 'master') {
            $score += 2;
        }
        if ($source === 'legacy_high') {
            $score -= 3;
        }

        if (!$this->isFinite($score)) {
            return -9999;
        }

        return (int)round($score);
    }

    private function calculateMergePriorityScore(array $row): float
    {
        $metrics = $this->evaluateBm1370Row($row);

        $score = 0.0;
        $score += $metrics['eff'] * 28;
        $score += $metrics['temp'] * 24;
        $score += $metrics['error'] * 24;
        $score += $metrics['hashBalanced'] * 16;
        $score += $metrics['electrical'] * 8;
        $score += $metrics['hashPush'] * 4;
        $score -= $metrics['riskLevel'] * 8;

        return $this->isFinite($score) ? $score : -9999.0;
    }

    private function makeVfKey(mixed $v, mixed $f): string
    {
        $vNum = $this->asFloat($v);
        $fNum = $this->asFloat($f);
        if ($vNum === null || $fNum === null) {
            return '';
        }

        return sprintf('%.3f-%.3f', $vNum, $fNum);
    }

    private function rowVfKey(array $row): string
    {
        return $this->makeVfKey($row['v'] ?? null, $row['f'] ?? null);
    }

    private function clamp01(?float $value): float
    {
        if ($value === null || !$this->isFinite($value)) {
            return 0.0;
        }

        return min(1.0, max(0.0, $value));
    }

    private function higherBetterScore(?float $value, float $low, float $high): ?float
    {
        if (!$this->isFinite($value) || !$this->isFinite($low) || !$this->isFinite($high) || $high <= $low) {
            return null;
        }
        if ($value <= $low) {
            return 0.0;
        }
        if ($value >= $high) {
            return 1.0;
        }
        return ($value - $low) / ($high - $low);
    }

    private function lowerBetterScore(?float $value, float $good, float $bad): ?float
    {
        if (!$this->isFinite($value) || !$this->isFinite($good) || !$this->isFinite($bad) || $bad <= $good) {
            return null;
        }
        if ($value <= $good) {
            return 1.0;
        }
        if ($value >= $bad) {
            return 0.0;
        }
        return ($bad - $value) / ($bad - $good);
    }

    private function bandScore(?float $value, float $min, float $ideal, float $max): ?float
    {
        if (!$this->isFinite($value) || !$this->isFinite($min) || !$this->isFinite($ideal) || !$this->isFinite($max)) {
            return null;
        }
        if ($ideal <= $min || $max <= $ideal) {
            return null;
        }
        if ($value <= $min || $value >= $max) {
            return 0.0;
        }
        if ($value === $ideal) {
            return 1.0;
        }
        if ($value < $ideal) {
            return ($value - $min) / ($ideal - $min);
        }
        return ($max - $value) / ($max - $ideal);
    }

    private function averageScores(array $values, float $fallback = 0.0): float
    {
        $safe = [];
        foreach ($values as $value) {
            if ($this->isFinite($value)) {
                $safe[] = (float)$value;
            }
        }

        if (count($safe) === 0) {
            return $fallback;
        }

        return array_sum($safe) / count($safe);
    }

    private function pickFinite(mixed ...$values): ?float
    {
        foreach ($values as $value) {
            if ($this->isFinite($value)) {
                return (float)$value;
            }
        }

        return null;
    }

    private function asFloat(mixed $value): ?float
    {
        if (!$this->isFinite($value)) {
            return null;
        }

        return (float)$value;
    }

    private function finiteOrNull(mixed $value): ?float
    {
        return $this->asFloat($value);
    }

    private function isFinite(mixed $value): bool
    {
        if (!is_numeric($value)) {
            return false;
        }

        $float = (float)$value;
        return !is_nan($float) && !is_infinite($float);
    }
}
