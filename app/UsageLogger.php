<?php

declare(strict_types=1);

namespace BitaxeOc\App;

use PDO;
use PDOException;
use Throwable;

require_once __DIR__ . '/UsageLoggerAdapter.php';

final class UsageLogger
{
    private bool $enabled;
    private string $rootDir;

    private string $driver;
    private bool $fileFallbackRead;

    private string $fileRel;
    private string $archiveGlobRel;
    private int $maxFileBytes;
    private int $maxArchives;
    private string $rotation;
    private bool $compressArchives;

    private string $visitorSalt;
    private string $summaryCacheRel;

    private array $dbConfig;
    private string $dbEngine;
    private string $dbTable;
    private string $dbCharset;
    private int $dbPruneProbability;
    private int $dbPruneBatchSize;
    private int $dbRetentionDays;
    private ?PDO $pdo = null;
    private bool $dbSchemaReady = false;
    private UsageLoggerAdapter $primaryAdapter;
    private ?UsageLoggerAdapter $fallbackAdapter = null;

    public function __construct(array $loggingConfig, ?string $rootDir = null)
    {
        $this->enabled = (bool)($loggingConfig['enabled'] ?? true);
        $this->rootDir = $rootDir ? rtrim($rootDir, '/\\') : dirname(__DIR__);

        $this->driver = $this->normalizeDriver((string)($loggingConfig['driver'] ?? 'file'));
        $this->fileFallbackRead = !array_key_exists('file_fallback_read', $loggingConfig)
            ? true
            : (bool)$loggingConfig['file_fallback_read'];

        $this->fileRel = (string)($loggingConfig['file'] ?? 'storage/usage_logs.ndjson');
        $this->archiveGlobRel = (string)($loggingConfig['archive_glob'] ?? 'storage/usage_logs_*.ndjson*');
        $this->maxFileBytes = max(1024, (int)($loggingConfig['max_file_bytes'] ?? (5 * 1024 * 1024)));
        $this->maxArchives = max(0, (int)($loggingConfig['max_archives'] ?? 5));

        $this->visitorSalt = (string)($loggingConfig['visitor_salt'] ?? 'change_this_salt');
        $this->summaryCacheRel = (string)($loggingConfig['summary_cache_file'] ?? 'storage/usage_summary_cache.json');

        $this->rotation = strtolower(trim((string)($loggingConfig['rotation'] ?? 'daily')));
        if (!in_array($this->rotation, ['daily', 'weekly', 'size'], true)) {
            $this->rotation = 'daily';
        }
        $this->compressArchives = (bool)($loggingConfig['compress_archives'] ?? true);

        $this->dbConfig = is_array($loggingConfig['db'] ?? null) ? $loggingConfig['db'] : [];
        $engine = strtolower(trim((string)($this->dbConfig['engine'] ?? 'mysql')));
        $this->dbEngine = in_array($engine, ['mysql', 'pgsql'], true) ? $engine : 'mysql';
        $this->dbTable = $this->sanitizeSqlIdentifier((string)($this->dbConfig['table'] ?? 'usage_events'), 'usage_events');
        $this->dbCharset = trim((string)($this->dbConfig['charset'] ?? 'utf8mb4'));
        if ($this->dbCharset === '') {
            $this->dbCharset = 'utf8mb4';
        }
        $this->dbPruneProbability = $this->sanitizeInt($this->dbConfig['prune_probability'] ?? 3, 0, 100, 3);
        $this->dbPruneBatchSize = $this->sanitizeInt($this->dbConfig['prune_batch_size'] ?? 4000, 100, 200000, 4000);
        $this->dbRetentionDays = $this->sanitizeInt($this->dbConfig['retention_days'] ?? 365, 7, 3650, 365);

        $this->primaryAdapter = $this->driver === 'db'
            ? new UsageLoggerDbAdapter(
                function (array $record): void {
                    $this->appendDbRecord($record);
                },
                fn (int $limit): array => $this->readLatestDb($limit),
                fn (): array => $this->summarizeAllDb()
            )
            : new UsageLoggerFileAdapter(
                function (array $record): void {
                    $this->appendFileRecord($record);
                },
                fn (int $limit): array => $this->readLatestFile($limit),
                fn (): array => $this->summarizeAllFile()
            );

        if ($this->driver === 'db' && $this->fileFallbackRead) {
            $this->fallbackAdapter = new UsageLoggerFileAdapter(
                function (array $record): void {
                    $this->appendFileRecord($record);
                },
                fn (int $limit): array => $this->readLatestFile($limit),
                fn (): array => $this->summarizeAllFile()
            );
        }
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function append(array $context): void
    {
        if (!$this->enabled) {
            return;
        }

        $record = $this->buildRecord($context);
        try {
            $this->primaryAdapter->append($record);
            return;
        } catch (Throwable $error) {
            if ($this->driver !== 'db' || !$this->fileFallbackRead || !($this->fallbackAdapter instanceof UsageLoggerAdapter)) {
                return;
            }
            $this->recordFallbackEvent('append_db_to_file', $error);
        }

        $this->fallbackAdapter->append($record);
    }

    public function readLatest(int $limit = 250): array
    {
        if (!$this->enabled) {
            return [];
        }

        $safeLimit = max(1, min(5000, $limit));
        try {
            return $this->primaryAdapter->readLatest($safeLimit);
        } catch (Throwable $error) {
            if ($this->driver !== 'db' || !$this->fileFallbackRead || !($this->fallbackAdapter instanceof UsageLoggerAdapter)) {
                return [];
            }
            $this->recordFallbackEvent('read_db_to_file', $error);
        }

        return $this->fallbackAdapter->readLatest($safeLimit);
    }

    public function summarize(array $entries): array
    {
        return $this->buildSummaryFromEntries($entries);
    }

    public function summarizeAll(): array
    {
        if (!$this->enabled) {
            return $this->defaultSummary();
        }
        try {
            return $this->primaryAdapter->summarizeAll();
        } catch (Throwable $error) {
            if ($this->driver !== 'db' || !$this->fileFallbackRead || !($this->fallbackAdapter instanceof UsageLoggerAdapter)) {
                return $this->defaultSummary();
            }
            $this->recordFallbackEvent('summary_db_to_file', $error);
        }

        return $this->fallbackAdapter->summarizeAll();
    }

    private function buildRecord(array $context): array
    {
        $requestStatus = strtolower(trim((string)($context['request_status'] ?? 'ok')));
        if (!in_array($requestStatus, ['ok', 'error'], true)) {
            $requestStatus = 'ok';
        }

        return [
            'run_id' => bin2hex(random_bytes(8)),
            'created_at' => gmdate('c'),
            'app_version' => substr((string)($context['app_version'] ?? ''), 0, 48),
            'visitor_hash' => $this->buildVisitorHash(
                (string)($context['client_ip'] ?? ''),
                (string)($context['user_agent'] ?? '')
            ),
            'ip_hash' => $this->hashValue((string)($context['client_ip'] ?? '')),
            'country_code' => $this->normalizeCountryCode((string)($context['country_code'] ?? '')),
            'selected_language' => $this->normalizeLanguageCode((string)($context['selected_language'] ?? '')),
            'browser_language' => $this->normalizeBrowserLanguage((string)($context['browser_language'] ?? '')),
            'selected_theme' => $this->normalizeTheme((string)($context['selected_theme'] ?? '')),
            'selected_theme_variant' => $this->normalizeThemeVariant((string)($context['selected_theme_variant'] ?? '')),
            'timezone_name' => $this->sanitizeText((string)($context['timezone_name'] ?? ''), 64),
            'timezone_offset_min' => $this->sanitizeInt($context['timezone_offset_min'] ?? 0, -900, 900, 0),
            'source_api' => $this->sanitizeText((string)($context['source_api'] ?? ''), 24),
            'request_status' => $requestStatus,
            'http_status' => max(0, min(999, (int)($context['http_status'] ?? 200))),
            'analysis_ms' => max(0, (int)($context['analysis_ms'] ?? 0)),
            'error_message' => substr((string)($context['error_message'] ?? ''), 0, 255),
            'files_attempted' => max(0, (int)($context['files_attempted'] ?? 0)),
            'files_processed' => max(0, (int)($context['files_processed'] ?? 0)),
            'bytes_attempted' => max(0, (int)($context['bytes_attempted'] ?? 0)),
            'bytes_processed' => max(0, (int)($context['bytes_processed'] ?? 0)),
            'largest_upload_bytes' => max(0, (int)($context['largest_upload_bytes'] ?? 0)),
            'total_rows' => max(0, (int)($context['total_rows'] ?? 0)),
            'parsed_rows' => max(0, (int)($context['parsed_rows'] ?? 0)),
            'skipped_rows' => max(0, (int)($context['skipped_rows'] ?? 0)),
            'merged_records' => max(0, (int)($context['merged_records'] ?? 0)),
            'upload_skipped' => [
                'nonCsv' => max(0, (int)($context['upload_skipped_non_csv'] ?? 0)),
                'tooLarge' => max(0, (int)($context['upload_skipped_too_large'] ?? 0)),
                'totalOverflow' => max(0, (int)($context['upload_skipped_total_overflow'] ?? 0)),
                'uploadError' => max(0, (int)($context['upload_skipped_upload_error'] ?? 0)),
                'countOverflow' => max(0, (int)($context['upload_skipped_count_overflow'] ?? 0)),
            ],
        ];
    }

    private function appendFileRecord(array $record): void
    {
        $filePath = $this->resolvePath($this->fileRel);
        $dir = dirname($filePath);
        if (!is_dir($dir)) {
            @mkdir($dir, 0750, true);
        }

        $this->rotateIfNeeded($filePath);

        $encoded = json_encode($record, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!is_string($encoded) || $encoded === '') {
            return;
        }

        $fp = @fopen($filePath, 'ab');
        if ($fp === false) {
            return;
        }

        try {
            if (!flock($fp, LOCK_EX)) {
                return;
            }
            fwrite($fp, $encoded . "\n");
            fflush($fp);
        } finally {
            flock($fp, LOCK_UN);
            fclose($fp);
        }

        @chmod($filePath, 0600);
    }

    private function readLatestFile(int $safeLimit): array
    {
        $files = $this->getLogFilesChronological();
        if (!$files) {
            return [];
        }

        $entries = [];
        $files = array_reverse($files);

        foreach ($files as $file) {
            $records = $this->readRecordsFromFile($file);
            if (!$records) {
                continue;
            }

            for ($i = count($records) - 1; $i >= 0; $i--) {
                $entries[] = $records[$i];
                if (count($entries) >= $safeLimit) {
                    break 2;
                }
            }
        }

        if (!$entries) {
            return [];
        }

        usort($entries, static function (array $a, array $b): int {
            return strcmp((string)($b['created_at'] ?? ''), (string)($a['created_at'] ?? ''));
        });

        return array_slice($entries, 0, $safeLimit);
    }

    private function summarizeAllFile(): array
    {
        $files = $this->getLogFilesChronological();
        if (!$files) {
            return $this->defaultSummary();
        }

        $sourceSignature = $this->buildSummarySourceSignature($files);
        $cachedSummary = $this->readSummaryCache($sourceSignature);
        if (is_array($cachedSummary)) {
            return $cachedSummary;
        }

        $summary = $this->defaultSummary();

        foreach ($files as $file) {
            $this->forEachRecordInFile($file, function (array $decoded) use (&$summary): void {
                $this->consumeSummaryRecord($summary, $decoded);
            });
        }

        $final = $this->finalizeSummary($summary);
        $this->writeSummaryCache($sourceSignature, $final);
        return $final;
    }

    private function appendDbRecord(array $record): void
    {
        $pdo = $this->getDbConnection();

        $tableSql = $this->quotedDbTable();
        $sql = "INSERT INTO {$tableSql} (
            run_id, created_at, app_version, visitor_hash, ip_hash, country_code,
            selected_language, browser_language, selected_theme, selected_theme_variant,
            timezone_name, timezone_offset_min, source_api,
            request_status, http_status, analysis_ms, error_message,
            files_attempted, files_processed, bytes_attempted, bytes_processed,
            largest_upload_bytes, total_rows, parsed_rows, skipped_rows, merged_records,
            upload_skipped_non_csv, upload_skipped_too_large, upload_skipped_total_overflow,
            upload_skipped_upload_error, upload_skipped_count_overflow
        ) VALUES (
            :run_id, :created_at, :app_version, :visitor_hash, :ip_hash, :country_code,
            :selected_language, :browser_language, :selected_theme, :selected_theme_variant,
            :timezone_name, :timezone_offset_min, :source_api,
            :request_status, :http_status, :analysis_ms, :error_message,
            :files_attempted, :files_processed, :bytes_attempted, :bytes_processed,
            :largest_upload_bytes, :total_rows, :parsed_rows, :skipped_rows, :merged_records,
            :upload_skipped_non_csv, :upload_skipped_too_large, :upload_skipped_total_overflow,
            :upload_skipped_upload_error, :upload_skipped_count_overflow
        )";

        $stmt = $pdo->prepare($sql);
        try {
            $stmt->execute([
                ':run_id' => (string)($record['run_id'] ?? ''),
                ':created_at' => $this->dbDateTimeFromIso((string)($record['created_at'] ?? '')),
                ':app_version' => (string)($record['app_version'] ?? ''),
                ':visitor_hash' => (string)($record['visitor_hash'] ?? ''),
                ':ip_hash' => (string)($record['ip_hash'] ?? ''),
                ':country_code' => (string)($record['country_code'] ?? 'ZZ'),
                ':selected_language' => (string)($record['selected_language'] ?? ''),
                ':browser_language' => (string)($record['browser_language'] ?? ''),
                ':selected_theme' => (string)($record['selected_theme'] ?? ''),
                ':selected_theme_variant' => (string)($record['selected_theme_variant'] ?? ''),
                ':timezone_name' => (string)($record['timezone_name'] ?? ''),
                ':timezone_offset_min' => (int)($record['timezone_offset_min'] ?? 0),
                ':source_api' => (string)($record['source_api'] ?? ''),
                ':request_status' => (string)($record['request_status'] ?? 'ok'),
                ':http_status' => (int)($record['http_status'] ?? 200),
                ':analysis_ms' => (int)($record['analysis_ms'] ?? 0),
                ':error_message' => (string)($record['error_message'] ?? ''),
                ':files_attempted' => (int)($record['files_attempted'] ?? 0),
                ':files_processed' => (int)($record['files_processed'] ?? 0),
                ':bytes_attempted' => (int)($record['bytes_attempted'] ?? 0),
                ':bytes_processed' => (int)($record['bytes_processed'] ?? 0),
                ':largest_upload_bytes' => (int)($record['largest_upload_bytes'] ?? 0),
                ':total_rows' => (int)($record['total_rows'] ?? 0),
                ':parsed_rows' => (int)($record['parsed_rows'] ?? 0),
                ':skipped_rows' => (int)($record['skipped_rows'] ?? 0),
                ':merged_records' => (int)($record['merged_records'] ?? 0),
                ':upload_skipped_non_csv' => (int)(($record['upload_skipped']['nonCsv'] ?? 0)),
                ':upload_skipped_too_large' => (int)(($record['upload_skipped']['tooLarge'] ?? 0)),
                ':upload_skipped_total_overflow' => (int)(($record['upload_skipped']['totalOverflow'] ?? 0)),
                ':upload_skipped_upload_error' => (int)(($record['upload_skipped']['uploadError'] ?? 0)),
                ':upload_skipped_count_overflow' => (int)(($record['upload_skipped']['countOverflow'] ?? 0)),
            ]);
        } catch (PDOException $error) {
            if (!$this->isUniqueConstraintError($error)) {
                throw $error;
            }
            // Duplicate run_id: ignore retry.
        }

        if ($this->shouldPruneDbOnWrite()) {
            $this->pruneDbOldRows($pdo);
        }
    }

    private function readLatestDb(int $safeLimit): array
    {
        $pdo = $this->getDbConnection();
        $tableSql = $this->quotedDbTable();
        $sql = "SELECT
            run_id,
            created_at,
            app_version,
            visitor_hash,
            ip_hash,
            country_code,
            selected_language,
            browser_language,
            selected_theme,
            selected_theme_variant,
            timezone_name,
            timezone_offset_min,
            source_api,
            request_status,
            http_status,
            analysis_ms,
            error_message,
            files_attempted,
            files_processed,
            bytes_attempted,
            bytes_processed,
            largest_upload_bytes,
            total_rows,
            parsed_rows,
            skipped_rows,
            merged_records,
            upload_skipped_non_csv,
            upload_skipped_too_large,
            upload_skipped_total_overflow,
            upload_skipped_upload_error,
            upload_skipped_count_overflow
        FROM {$tableSql}
        ORDER BY id DESC
        LIMIT {$safeLimit}";

        $rows = $pdo->query($sql)->fetchAll();
        if (!$rows) {
            return [];
        }

        $entries = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $entries[] = [
                'run_id' => (string)($row['run_id'] ?? ''),
                'created_at' => $this->isoFromDbDateTime((string)($row['created_at'] ?? '')),
                'app_version' => (string)($row['app_version'] ?? ''),
                'visitor_hash' => (string)($row['visitor_hash'] ?? ''),
                'ip_hash' => (string)($row['ip_hash'] ?? ''),
                'country_code' => $this->normalizeCountryCode((string)($row['country_code'] ?? 'ZZ')),
                'selected_language' => $this->normalizeLanguageCode((string)($row['selected_language'] ?? '')),
                'browser_language' => $this->normalizeBrowserLanguage((string)($row['browser_language'] ?? '')),
                'selected_theme' => $this->normalizeTheme((string)($row['selected_theme'] ?? '')),
                'selected_theme_variant' => $this->normalizeThemeVariant((string)($row['selected_theme_variant'] ?? '')),
                'timezone_name' => $this->sanitizeText((string)($row['timezone_name'] ?? ''), 64),
                'timezone_offset_min' => $this->sanitizeInt($row['timezone_offset_min'] ?? 0, -900, 900, 0),
                'source_api' => $this->sanitizeText((string)($row['source_api'] ?? ''), 24),
                'request_status' => strtolower((string)($row['request_status'] ?? 'ok')) === 'error' ? 'error' : 'ok',
                'http_status' => max(0, (int)($row['http_status'] ?? 200)),
                'analysis_ms' => max(0, (int)($row['analysis_ms'] ?? 0)),
                'error_message' => substr((string)($row['error_message'] ?? ''), 0, 255),
                'files_attempted' => max(0, (int)($row['files_attempted'] ?? 0)),
                'files_processed' => max(0, (int)($row['files_processed'] ?? 0)),
                'bytes_attempted' => max(0, (int)($row['bytes_attempted'] ?? 0)),
                'bytes_processed' => max(0, (int)($row['bytes_processed'] ?? 0)),
                'largest_upload_bytes' => max(0, (int)($row['largest_upload_bytes'] ?? 0)),
                'total_rows' => max(0, (int)($row['total_rows'] ?? 0)),
                'parsed_rows' => max(0, (int)($row['parsed_rows'] ?? 0)),
                'skipped_rows' => max(0, (int)($row['skipped_rows'] ?? 0)),
                'merged_records' => max(0, (int)($row['merged_records'] ?? 0)),
                'upload_skipped' => [
                    'nonCsv' => max(0, (int)($row['upload_skipped_non_csv'] ?? 0)),
                    'tooLarge' => max(0, (int)($row['upload_skipped_too_large'] ?? 0)),
                    'totalOverflow' => max(0, (int)($row['upload_skipped_total_overflow'] ?? 0)),
                    'uploadError' => max(0, (int)($row['upload_skipped_upload_error'] ?? 0)),
                    'countOverflow' => max(0, (int)($row['upload_skipped_count_overflow'] ?? 0)),
                ],
            ];
        }

        return $entries;
    }

    private function summarizeAllDb(): array
    {
        $pdo = $this->getDbConnection();
        $tableSql = $this->quotedDbTable();

        $sourceSignature = $this->buildDbSummarySourceSignature($pdo);
        $cachedSummary = $this->readSummaryCache($sourceSignature);
        if (is_array($cachedSummary)) {
            return $cachedSummary;
        }

        $core = $pdo->query("SELECT
            COUNT(*) AS total_runs,
            SUM(CASE WHEN request_status <> 'ok' OR http_status >= 400 THEN 1 ELSE 0 END) AS error_runs,
            COUNT(DISTINCT NULLIF(visitor_hash, '')) AS unique_visitors,
            COALESCE(SUM(files_attempted), 0) AS files_attempted_total,
            COALESCE(SUM(files_processed), 0) AS files_processed_total,
            COALESCE(SUM(bytes_attempted), 0) AS bytes_attempted_total,
            COALESCE(SUM(bytes_processed), 0) AS bytes_processed_total,
            COALESCE(MAX(largest_upload_bytes), 0) AS largest_upload_bytes,
            COALESCE(SUM(total_rows), 0) AS rows_total,
            COALESCE(SUM(parsed_rows), 0) AS parsed_rows_total,
            COALESCE(SUM(merged_records), 0) AS merged_records_total,
            MIN(created_at) AS first_seen,
            MAX(created_at) AS last_seen,
            COALESCE(AVG(NULLIF(analysis_ms, 0)), 0) AS avg_analysis_ms,
            COALESCE(MAX(analysis_ms), 0) AS max_analysis_ms
        FROM {$tableSql}")->fetch();

        if (!is_array($core) || (int)($core['total_runs'] ?? 0) <= 0) {
            $empty = $this->defaultSummary();
            $this->writeSummaryCache($sourceSignature, $empty);
            return $empty;
        }

        $totalRuns = max(0, (int)($core['total_runs'] ?? 0));
        $errorRuns = max(0, (int)($core['error_runs'] ?? 0));
        $uniqueVisitors = max(0, (int)($core['unique_visitors'] ?? 0));
        $filesAttemptedTotal = max(0, (int)($core['files_attempted_total'] ?? 0));
        $filesProcessedTotal = max(0, (int)($core['files_processed_total'] ?? 0));
        $bytesAttemptedTotal = max(0, (int)($core['bytes_attempted_total'] ?? 0));
        $bytesProcessedTotal = max(0, (int)($core['bytes_processed_total'] ?? 0));
        $largestUploadBytes = max(0, (int)($core['largest_upload_bytes'] ?? 0));
        $rowsTotal = max(0, (int)($core['rows_total'] ?? 0));
        $parsedRowsTotal = max(0, (int)($core['parsed_rows_total'] ?? 0));
        $mergedRecordsTotal = max(0, (int)($core['merged_records_total'] ?? 0));
        $avgAnalysisMs = max(0.0, (float)($core['avg_analysis_ms'] ?? 0));
        $maxAnalysisMs = max(0, (int)($core['max_analysis_ms'] ?? 0));

        $p95AnalysisMs = $this->queryP95Db($pdo);

        $summary = $this->defaultSummary();
        $summary['totalRuns'] = $totalRuns;
        $summary['errorRuns'] = $errorRuns;
        $summary['uniqueVisitors'] = $uniqueVisitors;
        $summary['filesAttemptedTotal'] = $filesAttemptedTotal;
        $summary['filesProcessedTotal'] = $filesProcessedTotal;
        $summary['bytesAttemptedTotal'] = $bytesAttemptedTotal;
        $summary['bytesProcessedTotal'] = $bytesProcessedTotal;
        $summary['largestUploadBytes'] = $largestUploadBytes;
        $summary['largestUploadMb'] = ((float)$largestUploadBytes) / (1024 * 1024);
        $summary['rowsTotal'] = $rowsTotal;
        $summary['parsedRowsTotal'] = $parsedRowsTotal;
        $summary['mergedRecordsTotal'] = $mergedRecordsTotal;
        $summary['avgAnalysisMs'] = $avgAnalysisMs;
        $summary['maxAnalysisMs'] = $maxAnalysisMs;
        $summary['p95AnalysisMs'] = $p95AnalysisMs;
        $summary['firstSeen'] = $this->isoFromDbDateTime((string)($core['first_seen'] ?? ''));
        $summary['lastSeen'] = $this->isoFromDbDateTime((string)($core['last_seen'] ?? ''));
        $summary['errorRatePct'] = $totalRuns > 0 ? ((100.0 * $errorRuns) / $totalRuns) : 0.0;
        $summary['avgFilesPerRun'] = $totalRuns > 0 ? (((float)$filesProcessedTotal) / $totalRuns) : 0.0;
        $summary['avgMbPerRun'] = $totalRuns > 0 ? ((((float)$bytesAttemptedTotal) / (1024 * 1024)) / $totalRuns) : 0.0;
        $summary['avgRowsPerRun'] = $totalRuns > 0 ? (((float)$rowsTotal) / $totalRuns) : 0.0;
        $summary['avgProcessedMbPerRun'] = $totalRuns > 0 ? ((((float)$bytesProcessedTotal) / (1024 * 1024)) / $totalRuns) : 0.0;

        $summary['visitors'] = $this->queryTopVisitorsDb($pdo);
        $summary['countries'] = $this->queryTopCountriesDb($pdo);
        $summary['languages'] = $this->queryTopLanguagesDb($pdo);
        $summary['themes'] = $this->queryTopThemesDb($pdo);

        unset($summary['__visitorsMap'], $summary['__countriesMap'], $summary['__languagesMap'], $summary['__themesMap'], $summary['__analysisDurations'], $summary['__analysisMsSum']);

        $this->writeSummaryCache($sourceSignature, $summary);
        return $summary;
    }

    private function queryTopVisitorsDb(PDO $pdo): array
    {
        $tableSql = $this->quotedDbTable();
        $sql = "SELECT
            visitor_hash AS visitorHash,
            COUNT(*) AS runs,
            COALESCE(SUM(files_processed), 0) AS filesProcessed,
            COALESCE(SUM(bytes_processed), 0) AS bytesProcessed,
            COALESCE(SUM(total_rows), 0) AS rowsTotal,
            MAX(created_at) AS lastSeen
        FROM {$tableSql}
        WHERE visitor_hash <> ''
        GROUP BY visitor_hash
        ORDER BY filesProcessed DESC, runs DESC
        LIMIT 50";

        $rows = $pdo->query($sql)->fetchAll();
        if (!$rows) {
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $out[] = [
                'visitorHash' => substr((string)($row['visitorHash'] ?? ''), 0, 20),
                'runs' => max(0, (int)($row['runs'] ?? 0)),
                'filesProcessed' => max(0, (int)($row['filesProcessed'] ?? 0)),
                'bytesProcessed' => max(0, (int)($row['bytesProcessed'] ?? 0)),
                'rowsTotal' => max(0, (int)($row['rowsTotal'] ?? 0)),
                'lastSeen' => $this->isoFromDbDateTime((string)($row['lastSeen'] ?? '')),
            ];
        }

        return $out;
    }

    private function queryTopCountriesDb(PDO $pdo): array
    {
        $tableSql = $this->quotedDbTable();
        $sql = "SELECT
            country_code AS countryCode,
            COUNT(*) AS runs,
            COALESCE(SUM(files_processed), 0) AS filesProcessed,
            COALESCE(SUM(bytes_processed), 0) AS bytesProcessed,
            COALESCE(SUM(total_rows), 0) AS rowsTotal,
            COUNT(DISTINCT NULLIF(visitor_hash, '')) AS uniqueVisitors,
            MAX(created_at) AS lastSeen
        FROM {$tableSql}
        GROUP BY country_code
        ORDER BY uniqueVisitors DESC, runs DESC, countryCode ASC
        LIMIT 50";

        $rows = $pdo->query($sql)->fetchAll();
        if (!$rows) {
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $out[] = [
                'countryCode' => $this->normalizeCountryCode((string)($row['countryCode'] ?? 'ZZ')),
                'runs' => max(0, (int)($row['runs'] ?? 0)),
                'filesProcessed' => max(0, (int)($row['filesProcessed'] ?? 0)),
                'bytesProcessed' => max(0, (int)($row['bytesProcessed'] ?? 0)),
                'rowsTotal' => max(0, (int)($row['rowsTotal'] ?? 0)),
                'uniqueVisitors' => max(0, (int)($row['uniqueVisitors'] ?? 0)),
                'lastSeen' => $this->isoFromDbDateTime((string)($row['lastSeen'] ?? '')),
            ];
        }

        return $out;
    }

    private function queryTopLanguagesDb(PDO $pdo): array
    {
        $tableSql = $this->quotedDbTable();
        $sql = "SELECT
            CASE WHEN selected_language = '' THEN 'unknown' ELSE selected_language END AS languageCode,
            COUNT(*) AS runs,
            COUNT(DISTINCT NULLIF(visitor_hash, '')) AS uniqueVisitors,
            COALESCE(SUM(files_processed), 0) AS filesProcessed,
            COALESCE(SUM(bytes_processed), 0) AS bytesProcessed,
            COALESCE(SUM(total_rows), 0) AS rowsTotal,
            MAX(created_at) AS lastSeen
        FROM {$tableSql}
        GROUP BY languageCode
        ORDER BY runs DESC, uniqueVisitors DESC, languageCode ASC
        LIMIT 30";

        $rows = $pdo->query($sql)->fetchAll();
        if (!$rows) {
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $lang = $this->normalizeLanguageCode((string)($row['languageCode'] ?? ''));
            if ($lang === '') {
                $lang = 'unknown';
            }
            $out[] = [
                'languageCode' => $lang,
                'runs' => max(0, (int)($row['runs'] ?? 0)),
                'uniqueVisitors' => max(0, (int)($row['uniqueVisitors'] ?? 0)),
                'filesProcessed' => max(0, (int)($row['filesProcessed'] ?? 0)),
                'bytesProcessed' => max(0, (int)($row['bytesProcessed'] ?? 0)),
                'rowsTotal' => max(0, (int)($row['rowsTotal'] ?? 0)),
                'lastSeen' => $this->isoFromDbDateTime((string)($row['lastSeen'] ?? '')),
            ];
        }

        return $out;
    }

    private function queryTopThemesDb(PDO $pdo): array
    {
        $tableSql = $this->quotedDbTable();
        $sql = "SELECT
            CASE WHEN selected_theme = '' THEN 'dark' ELSE selected_theme END AS themeName,
            CASE WHEN selected_theme_variant = '' THEN 'purple' ELSE selected_theme_variant END AS themeVariant,
            COUNT(*) AS runs,
            COUNT(DISTINCT NULLIF(visitor_hash, '')) AS uniqueVisitors,
            MAX(created_at) AS lastSeen
        FROM {$tableSql}
        GROUP BY themeName, themeVariant
        ORDER BY runs DESC, uniqueVisitors DESC, themeName ASC, themeVariant ASC
        LIMIT 20";

        $rows = $pdo->query($sql)->fetchAll();
        if (!$rows) {
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $theme = $this->normalizeTheme((string)($row['themeName'] ?? 'dark'));
            $variant = $this->normalizeThemeVariant((string)($row['themeVariant'] ?? 'purple'));
            $out[] = [
                'theme' => $theme,
                'variant' => $variant,
                'key' => $theme . ':' . $variant,
                'runs' => max(0, (int)($row['runs'] ?? 0)),
                'uniqueVisitors' => max(0, (int)($row['uniqueVisitors'] ?? 0)),
                'lastSeen' => $this->isoFromDbDateTime((string)($row['lastSeen'] ?? '')),
            ];
        }

        return $out;
    }

    private function queryP95Db(PDO $pdo): float
    {
        $tableSql = $this->quotedDbTable();
        $countRow = $pdo->query("SELECT COUNT(*) AS c FROM {$tableSql} WHERE analysis_ms > 0")->fetch();
        $count = is_array($countRow) ? max(0, (int)($countRow['c'] ?? 0)) : 0;
        if ($count <= 0) {
            return 0.0;
        }

        $offset = (int)max(0, ceil($count * 0.95) - 1);
        $sql = "SELECT analysis_ms FROM {$tableSql} WHERE analysis_ms > 0 ORDER BY analysis_ms ASC LIMIT {$offset}, 1";
        $row = $pdo->query($sql)->fetch();
        if (!is_array($row)) {
            return 0.0;
        }

        return (float)max(0, (int)($row['analysis_ms'] ?? 0));
    }

    private function buildSummaryFromEntries(array $entries): array
    {
        $summary = $this->defaultSummary();

        foreach ($entries as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $this->consumeSummaryRecord($summary, $entry);
        }

        return $this->finalizeSummary($summary);
    }

    private function defaultSummary(): array
    {
        return [
            'totalRuns' => 0,
            'errorRuns' => 0,
            'errorRatePct' => 0.0,
            'uniqueVisitors' => 0,
            'filesAttemptedTotal' => 0,
            'filesProcessedTotal' => 0,
            'bytesAttemptedTotal' => 0,
            'bytesProcessedTotal' => 0,
            'largestUploadBytes' => 0,
            'largestUploadMb' => 0.0,
            'rowsTotal' => 0,
            'parsedRowsTotal' => 0,
            'mergedRecordsTotal' => 0,
            'avgFilesPerRun' => 0.0,
            'avgMbPerRun' => 0.0,
            'avgRowsPerRun' => 0.0,
            'avgProcessedMbPerRun' => 0.0,
            'avgAnalysisMs' => 0.0,
            'maxAnalysisMs' => 0,
            'p95AnalysisMs' => 0.0,
            'firstSeen' => null,
            'lastSeen' => null,
            'visitors' => [],
            'countries' => [],
            'languages' => [],
            'themes' => [],
            '__visitorsMap' => [],
            '__countriesMap' => [],
            '__languagesMap' => [],
            '__themesMap' => [],
            '__analysisDurations' => [],
            '__analysisMsSum' => 0,
        ];
    }

    private function consumeSummaryRecord(array &$summary, array $entry): void
    {
        $summary['totalRuns'] += 1;

        $visitor = (string)($entry['visitor_hash'] ?? '');
        if ($visitor !== '') {
            if (!isset($summary['__visitorsMap'][$visitor])) {
                $summary['__visitorsMap'][$visitor] = [
                    'visitorHash' => $visitor,
                    'runs' => 0,
                    'filesProcessed' => 0,
                    'bytesProcessed' => 0,
                    'rowsTotal' => 0,
                    'lastSeen' => null,
                ];
            }

            $summary['__visitorsMap'][$visitor]['runs'] += 1;
            $summary['__visitorsMap'][$visitor]['filesProcessed'] += max(0, (int)($entry['files_processed'] ?? 0));
            $summary['__visitorsMap'][$visitor]['bytesProcessed'] += max(0, (int)($entry['bytes_processed'] ?? 0));
            $summary['__visitorsMap'][$visitor]['rowsTotal'] += max(0, (int)($entry['total_rows'] ?? 0));

            $entryTs = strtotime((string)($entry['created_at'] ?? ''));
            if ($entryTs !== false) {
                $lastSeen = (string)($summary['__visitorsMap'][$visitor]['lastSeen'] ?? '');
                if ($lastSeen === '' || $entryTs > strtotime($lastSeen)) {
                    $summary['__visitorsMap'][$visitor]['lastSeen'] = gmdate('c', $entryTs);
                }
            }
        }

        $countryCode = $this->normalizeCountryCode((string)($entry['country_code'] ?? ''));
        if (!isset($summary['__countriesMap'][$countryCode])) {
            $summary['__countriesMap'][$countryCode] = [
                'countryCode' => $countryCode,
                'runs' => 0,
                'filesProcessed' => 0,
                'bytesProcessed' => 0,
                'rowsTotal' => 0,
                'lastSeen' => null,
                '__visitorsMap' => [],
            ];
        }
        $summary['__countriesMap'][$countryCode]['runs'] += 1;
        $summary['__countriesMap'][$countryCode]['filesProcessed'] += max(0, (int)($entry['files_processed'] ?? 0));
        $summary['__countriesMap'][$countryCode]['bytesProcessed'] += max(0, (int)($entry['bytes_processed'] ?? 0));
        $summary['__countriesMap'][$countryCode]['rowsTotal'] += max(0, (int)($entry['total_rows'] ?? 0));
        if ($visitor !== '') {
            $summary['__countriesMap'][$countryCode]['__visitorsMap'][$visitor] = true;
        }

        $languageCode = $this->normalizeLanguageCode((string)($entry['selected_language'] ?? ''));
        if ($languageCode === '') {
            $languageCode = 'unknown';
        }
        if (!isset($summary['__languagesMap'][$languageCode])) {
            $summary['__languagesMap'][$languageCode] = [
                'languageCode' => $languageCode,
                'runs' => 0,
                'filesProcessed' => 0,
                'bytesProcessed' => 0,
                'rowsTotal' => 0,
                'lastSeen' => null,
                '__visitorsMap' => [],
            ];
        }
        $summary['__languagesMap'][$languageCode]['runs'] += 1;
        $summary['__languagesMap'][$languageCode]['filesProcessed'] += max(0, (int)($entry['files_processed'] ?? 0));
        $summary['__languagesMap'][$languageCode]['bytesProcessed'] += max(0, (int)($entry['bytes_processed'] ?? 0));
        $summary['__languagesMap'][$languageCode]['rowsTotal'] += max(0, (int)($entry['total_rows'] ?? 0));
        if ($visitor !== '') {
            $summary['__languagesMap'][$languageCode]['__visitorsMap'][$visitor] = true;
        }

        $theme = $this->normalizeTheme((string)($entry['selected_theme'] ?? ''));
        $variant = $this->normalizeThemeVariant((string)($entry['selected_theme_variant'] ?? ''));
        $themeKey = $theme . ':' . $variant;
        if (!isset($summary['__themesMap'][$themeKey])) {
            $summary['__themesMap'][$themeKey] = [
                'theme' => $theme,
                'variant' => $variant,
                'key' => $themeKey,
                'runs' => 0,
                'lastSeen' => null,
                '__visitorsMap' => [],
            ];
        }
        $summary['__themesMap'][$themeKey]['runs'] += 1;
        if ($visitor !== '') {
            $summary['__themesMap'][$themeKey]['__visitorsMap'][$visitor] = true;
        }

        $summary['filesAttemptedTotal'] += max(0, (int)($entry['files_attempted'] ?? 0));
        $summary['filesProcessedTotal'] += max(0, (int)($entry['files_processed'] ?? 0));
        $summary['bytesAttemptedTotal'] += max(0, (int)($entry['bytes_attempted'] ?? 0));
        $summary['bytesProcessedTotal'] += max(0, (int)($entry['bytes_processed'] ?? 0));
        $summary['rowsTotal'] += max(0, (int)($entry['total_rows'] ?? 0));
        $summary['parsedRowsTotal'] += max(0, (int)($entry['parsed_rows'] ?? 0));
        $summary['mergedRecordsTotal'] += max(0, (int)($entry['merged_records'] ?? 0));

        $largestUpload = max(
            max(0, (int)($entry['largest_upload_bytes'] ?? 0)),
            max(0, (int)($entry['bytes_attempted'] ?? 0))
        );
        if ($largestUpload > (int)$summary['largestUploadBytes']) {
            $summary['largestUploadBytes'] = $largestUpload;
        }

        $status = strtolower(trim((string)($entry['request_status'] ?? 'ok')));
        $httpStatus = max(0, (int)($entry['http_status'] ?? 200));
        if ($status !== 'ok' || $httpStatus >= 400) {
            $summary['errorRuns'] += 1;
        }

        $analysisMs = max(0, (int)($entry['analysis_ms'] ?? 0));
        if ($analysisMs > 0) {
            if (count($summary['__analysisDurations']) >= 50000) {
                $summary['__analysisDurations'] = array_slice($summary['__analysisDurations'], -25000);
            }
            $summary['__analysisDurations'][] = $analysisMs;
            $summary['__analysisMsSum'] += $analysisMs;
            if ($analysisMs > (int)$summary['maxAnalysisMs']) {
                $summary['maxAnalysisMs'] = $analysisMs;
            }
        }

        $createdAt = (string)($entry['created_at'] ?? '');
        if ($createdAt !== '') {
            if ($summary['firstSeen'] === null || strcmp($createdAt, (string)$summary['firstSeen']) < 0) {
                $summary['firstSeen'] = $createdAt;
            }
            if ($summary['lastSeen'] === null || strcmp($createdAt, (string)$summary['lastSeen']) > 0) {
                $summary['lastSeen'] = $createdAt;
            }

            if (isset($summary['__countriesMap'][$countryCode]) && is_array($summary['__countriesMap'][$countryCode])) {
                $countryLastSeen = (string)($summary['__countriesMap'][$countryCode]['lastSeen'] ?? '');
                if ($countryLastSeen === '' || strcmp($createdAt, $countryLastSeen) > 0) {
                    $summary['__countriesMap'][$countryCode]['lastSeen'] = $createdAt;
                }
            }

            if (isset($summary['__languagesMap'][$languageCode]) && is_array($summary['__languagesMap'][$languageCode])) {
                $languageLastSeen = (string)($summary['__languagesMap'][$languageCode]['lastSeen'] ?? '');
                if ($languageLastSeen === '' || strcmp($createdAt, $languageLastSeen) > 0) {
                    $summary['__languagesMap'][$languageCode]['lastSeen'] = $createdAt;
                }
            }

            if (isset($summary['__themesMap'][$themeKey]) && is_array($summary['__themesMap'][$themeKey])) {
                $themeLastSeen = (string)($summary['__themesMap'][$themeKey]['lastSeen'] ?? '');
                if ($themeLastSeen === '' || strcmp($createdAt, $themeLastSeen) > 0) {
                    $summary['__themesMap'][$themeKey]['lastSeen'] = $createdAt;
                }
            }
        }
    }

    private function finalizeSummary(array $summary): array
    {
        $totalRuns = max(0, (int)($summary['totalRuns'] ?? 0));
        $errorRuns = max(0, (int)($summary['errorRuns'] ?? 0));
        $visitorsMap = is_array($summary['__visitorsMap'] ?? null) ? $summary['__visitorsMap'] : [];
        $visitorsList = array_values($visitorsMap);
        $countriesMap = is_array($summary['__countriesMap'] ?? null) ? $summary['__countriesMap'] : [];
        $languagesMap = is_array($summary['__languagesMap'] ?? null) ? $summary['__languagesMap'] : [];
        $themesMap = is_array($summary['__themesMap'] ?? null) ? $summary['__themesMap'] : [];

        usort($visitorsList, static function (array $a, array $b): int {
            if ((int)$b['filesProcessed'] !== (int)$a['filesProcessed']) {
                return (int)$b['filesProcessed'] <=> (int)$a['filesProcessed'];
            }
            return (int)$b['runs'] <=> (int)$a['runs'];
        });

        $countriesList = [];
        foreach ($countriesMap as $country) {
            if (!is_array($country)) {
                continue;
            }
            $visitorMap = is_array($country['__visitorsMap'] ?? null) ? $country['__visitorsMap'] : [];
            unset($country['__visitorsMap']);
            $country['uniqueVisitors'] = count($visitorMap);
            $countriesList[] = $country;
        }
        usort($countriesList, static function (array $a, array $b): int {
            $aVisitors = max(0, (int)($a['uniqueVisitors'] ?? 0));
            $bVisitors = max(0, (int)($b['uniqueVisitors'] ?? 0));
            if ($bVisitors !== $aVisitors) {
                return $bVisitors <=> $aVisitors;
            }
            $aRuns = max(0, (int)($a['runs'] ?? 0));
            $bRuns = max(0, (int)($b['runs'] ?? 0));
            if ($bRuns !== $aRuns) {
                return $bRuns <=> $aRuns;
            }
            return strcmp((string)($a['countryCode'] ?? ''), (string)($b['countryCode'] ?? ''));
        });

        $languagesList = [];
        foreach ($languagesMap as $language) {
            if (!is_array($language)) {
                continue;
            }
            $visitorMap = is_array($language['__visitorsMap'] ?? null) ? $language['__visitorsMap'] : [];
            unset($language['__visitorsMap']);
            $language['uniqueVisitors'] = count($visitorMap);
            $languagesList[] = $language;
        }
        usort($languagesList, static function (array $a, array $b): int {
            $aRuns = max(0, (int)($a['runs'] ?? 0));
            $bRuns = max(0, (int)($b['runs'] ?? 0));
            if ($bRuns !== $aRuns) {
                return $bRuns <=> $aRuns;
            }
            $aVisitors = max(0, (int)($a['uniqueVisitors'] ?? 0));
            $bVisitors = max(0, (int)($b['uniqueVisitors'] ?? 0));
            if ($bVisitors !== $aVisitors) {
                return $bVisitors <=> $aVisitors;
            }
            return strcmp((string)($a['languageCode'] ?? ''), (string)($b['languageCode'] ?? ''));
        });

        $themesList = [];
        foreach ($themesMap as $theme) {
            if (!is_array($theme)) {
                continue;
            }
            $visitorMap = is_array($theme['__visitorsMap'] ?? null) ? $theme['__visitorsMap'] : [];
            unset($theme['__visitorsMap']);
            $theme['uniqueVisitors'] = count($visitorMap);
            $themesList[] = $theme;
        }
        usort($themesList, static function (array $a, array $b): int {
            $aRuns = max(0, (int)($a['runs'] ?? 0));
            $bRuns = max(0, (int)($b['runs'] ?? 0));
            if ($bRuns !== $aRuns) {
                return $bRuns <=> $aRuns;
            }
            $aVisitors = max(0, (int)($a['uniqueVisitors'] ?? 0));
            $bVisitors = max(0, (int)($b['uniqueVisitors'] ?? 0));
            if ($bVisitors !== $aVisitors) {
                return $bVisitors <=> $aVisitors;
            }
            $aKey = (string)($a['key'] ?? '');
            $bKey = (string)($b['key'] ?? '');
            return strcmp($aKey, $bKey);
        });

        $analysisDurations = is_array($summary['__analysisDurations'] ?? null) ? $summary['__analysisDurations'] : [];
        $summary['p95AnalysisMs'] = $this->calculatePercentile($analysisDurations, 95);
        $summary['avgAnalysisMs'] = count($analysisDurations) > 0
            ? (((float)$summary['__analysisMsSum']) / count($analysisDurations))
            : 0.0;

        $summary['uniqueVisitors'] = count($visitorsMap);
        $summary['visitors'] = array_slice($visitorsList, 0, 50);
        $summary['countries'] = array_slice($countriesList, 0, 50);
        $summary['languages'] = array_slice($languagesList, 0, 50);
        $summary['themes'] = array_slice($themesList, 0, 30);
        $summary['avgFilesPerRun'] = $totalRuns > 0 ? (((float)$summary['filesProcessedTotal']) / $totalRuns) : 0.0;
        $summary['avgMbPerRun'] = $totalRuns > 0 ? ((((float)$summary['bytesAttemptedTotal']) / (1024 * 1024)) / $totalRuns) : 0.0;
        $summary['avgProcessedMbPerRun'] = $totalRuns > 0 ? ((((float)$summary['bytesProcessedTotal']) / (1024 * 1024)) / $totalRuns) : 0.0;
        $summary['avgRowsPerRun'] = $totalRuns > 0 ? (((float)$summary['rowsTotal']) / $totalRuns) : 0.0;
        $summary['largestUploadMb'] = ((float)max(0, (int)$summary['largestUploadBytes'])) / (1024 * 1024);
        $summary['errorRatePct'] = $totalRuns > 0 ? ((100 * $errorRuns) / $totalRuns) : 0.0;

        unset($summary['__visitorsMap'], $summary['__countriesMap'], $summary['__languagesMap'], $summary['__themesMap'], $summary['__analysisDurations'], $summary['__analysisMsSum']);
        return $summary;
    }

    private function resolvePath(string $relative): string
    {
        $trimmed = ltrim(str_replace('\\', '/', $relative), '/');
        return $this->rootDir . '/' . $trimmed;
    }

    private function getLogFilesChronological(): array
    {
        $current = $this->resolvePath($this->fileRel);
        $globPattern = $this->resolvePath($this->archiveGlobRel);
        $archives = glob($globPattern) ?: [];
        sort($archives);

        $files = [];
        foreach ($archives as $archive) {
            if (is_file($archive)) {
                $files[] = $archive;
            }
        }
        if (is_file($current)) {
            $files[] = $current;
        }

        return $files;
    }

    private function rotateIfNeeded(string $currentFile): void
    {
        clearstatcache(true, $currentFile);
        if (!is_file($currentFile)) {
            return;
        }

        $size = @filesize($currentFile);
        $sizeReached = is_int($size) && $size >= $this->maxFileBytes;
        $periodReached = $this->rotation !== 'size' ? $this->periodBoundaryCrossed($currentFile, $this->rotation) : false;
        if (!$sizeReached && !$periodReached) {
            return;
        }

        $archivePath = $this->buildArchivePath($currentFile);
        if (!@rename($currentFile, $archivePath)) {
            return;
        }
        @chmod($archivePath, 0600);

        if ($this->compressArchives) {
            $archivePath = $this->compressArchiveFile($archivePath);
        }
        if ($archivePath !== '' && is_file($archivePath)) {
            @chmod($archivePath, 0600);
        }

        $this->cleanupOldArchives();
    }

    private function periodBoundaryCrossed(string $currentFile, string $period): bool
    {
        $mtime = @filemtime($currentFile);
        if (!is_int($mtime) || $mtime <= 0) {
            return false;
        }

        $now = time();
        return $this->rotationPeriodKey($mtime, $period) !== $this->rotationPeriodKey($now, $period);
    }

    private function rotationPeriodKey(int $timestamp, string $period): string
    {
        if ($period === 'weekly') {
            return gmdate('o-W', $timestamp);
        }
        return gmdate('Y-m-d', $timestamp);
    }

    private function buildArchivePath(string $currentFile): string
    {
        $base = preg_replace('/\.ndjson(\.gz)?$/', '', $currentFile);
        if (!is_string($base) || trim($base) === '') {
            $base = $currentFile;
        }

        $stamp = gmdate('Ymd_His');
        $candidate = $base . '_' . $stamp . '.ndjson';
        $seq = 1;
        while (is_file($candidate) || is_file($candidate . '.gz')) {
            $candidate = $base . '_' . $stamp . '_' . $seq . '.ndjson';
            $seq += 1;
        }

        return $candidate;
    }

    private function compressArchiveFile(string $archivePath): string
    {
        if (!is_file($archivePath)) {
            return '';
        }
        if (!function_exists('gzopen')) {
            return $archivePath;
        }

        $gzPath = $archivePath . '.gz';
        $src = @fopen($archivePath, 'rb');
        $dst = @gzopen($gzPath, 'wb9');
        if ($src === false || $dst === false) {
            if (is_resource($src)) {
                fclose($src);
            }
            if (is_resource($dst)) {
                gzclose($dst);
            }
            @unlink($gzPath);
            return $archivePath;
        }

        try {
            while (!feof($src)) {
                $chunk = fread($src, 8192);
                if (!is_string($chunk) || $chunk === '') {
                    continue;
                }
                gzwrite($dst, $chunk);
            }
        } finally {
            fclose($src);
            gzclose($dst);
        }

        if (!is_file($gzPath) || (@filesize($gzPath) ?: 0) <= 0) {
            @unlink($gzPath);
            return $archivePath;
        }

        @unlink($archivePath);
        return $gzPath;
    }

    private function cleanupOldArchives(): void
    {
        if ($this->maxArchives < 0) {
            return;
        }

        $globPattern = $this->resolvePath($this->archiveGlobRel);
        $archives = glob($globPattern) ?: [];
        if (count($archives) <= $this->maxArchives) {
            return;
        }

        sort($archives);
        $removeCount = count($archives) - $this->maxArchives;
        for ($i = 0; $i < $removeCount; $i++) {
            @unlink($archives[$i]);
        }
    }

    private function buildVisitorHash(string $ip, string $userAgent): string
    {
        $seed = strtolower(trim($ip)) . '|' . trim($userAgent);
        return substr(hash('sha256', $seed . '|' . $this->visitorSalt), 0, 20);
    }

    private function buildSummarySourceSignature(array $files): string
    {
        $parts = ['summary-v3-extended'];
        foreach ($files as $file) {
            $size = @filesize($file);
            $mtime = @filemtime($file);
            $parts[] = $file . '|' . (is_int($size) ? $size : -1) . '|' . (is_int($mtime) ? $mtime : -1);
        }

        return sha1(implode(';', $parts));
    }

    private function buildDbSummarySourceSignature(PDO $pdo): string
    {
        $tableSql = $this->quotedDbTable();
        $row = $pdo->query("SELECT COALESCE(MAX(id), 0) AS max_id, COALESCE(MAX(created_at), '') AS max_created FROM {$tableSql}")->fetch();
        if (!is_array($row)) {
            return sha1('summary-v3-db-empty');
        }

        $maxId = max(0, (int)($row['max_id'] ?? 0));
        $maxCreated = (string)($row['max_created'] ?? '');
        return sha1('summary-v3-db|' . $maxId . '|' . $maxCreated);
    }

    private function readSummaryCache(string $sourceSignature): ?array
    {
        $cachePath = $this->resolvePath($this->summaryCacheRel);
        if (!is_file($cachePath)) {
            return null;
        }

        $raw = @file_get_contents($cachePath);
        if (!is_string($raw) || trim($raw) === '') {
            return null;
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return null;
        }

        $cachedSignature = (string)($decoded['source_signature'] ?? '');
        if ($cachedSignature === '' || !hash_equals($sourceSignature, $cachedSignature)) {
            return null;
        }

        $summary = $decoded['summary'] ?? null;
        return is_array($summary) ? $summary : null;
    }

    private function writeSummaryCache(string $sourceSignature, array $summary): void
    {
        $cachePath = $this->resolvePath($this->summaryCacheRel);
        $dir = dirname($cachePath);
        if (!is_dir($dir)) {
            @mkdir($dir, 0750, true);
        }

        $payload = [
            'generated_at' => gmdate('c'),
            'source_signature' => $sourceSignature,
            'summary' => $summary,
        ];

        $encoded = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!is_string($encoded) || $encoded === '') {
            return;
        }

        @file_put_contents($cachePath, $encoded, LOCK_EX);
        @chmod($cachePath, 0600);
    }

    private function hashValue(string $value): string
    {
        if (trim($value) === '') {
            return '';
        }

        return substr(hash('sha256', trim(strtolower($value)) . '|' . $this->visitorSalt), 0, 20);
    }

    private function normalizeCountryCode(string $countryCode): string
    {
        $code = strtoupper(trim($countryCode));
        if ($code === '' || $code === 'XX' || $code === '--') {
            return 'ZZ';
        }
        if (!preg_match('/^[A-Z]{2}$/', $code)) {
            return 'ZZ';
        }
        return $code;
    }

    private function normalizeLanguageCode(string $value): string
    {
        $code = strtolower(trim($value));
        if ($code === '' || $code === 'auto') {
            return '';
        }
        $code = preg_replace('/[^a-z0-9-]/', '', $code);
        if (!is_string($code) || $code === '') {
            return '';
        }
        if (strlen($code) > 12) {
            $code = substr($code, 0, 12);
        }
        return $code;
    }

    private function normalizeBrowserLanguage(string $value): string
    {
        $raw = strtolower(trim($value));
        if ($raw === '') {
            return '';
        }
        $raw = preg_replace('/[^a-z0-9,;_ -]/', '', $raw);
        if (!is_string($raw) || $raw === '') {
            return '';
        }
        if (strlen($raw) > 24) {
            $raw = substr($raw, 0, 24);
        }
        return $raw;
    }

    private function normalizeTheme(string $value): string
    {
        $theme = strtolower(trim($value));
        if ($theme === '') {
            return 'dark';
        }
        if (!in_array($theme, ['dark', 'light'], true)) {
            return 'dark';
        }
        return $theme;
    }

    private function normalizeThemeVariant(string $value): string
    {
        $variant = strtolower(trim($value));
        if ($variant === '') {
            return 'purple';
        }
        if (!preg_match('/^[a-z0-9_-]{1,24}$/', $variant)) {
            return 'purple';
        }
        return $variant;
    }

    private function sanitizeText(string $value, int $maxLen): string
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return '';
        }
        if (strlen($trimmed) > $maxLen) {
            $trimmed = substr($trimmed, 0, $maxLen);
        }
        return $trimmed;
    }

    private function calculatePercentile(array $values, int $percent): float
    {
        if (!$values) {
            return 0.0;
        }

        $clean = [];
        foreach ($values as $value) {
            $n = (float)$value;
            if ($n >= 0 && !is_nan($n) && !is_infinite($n)) {
                $clean[] = $n;
            }
        }
        if (!$clean) {
            return 0.0;
        }

        sort($clean, SORT_NUMERIC);
        $rank = ((max(1, min(99, $percent)) / 100) * count($clean));
        $idx = max(0, min(count($clean) - 1, (int)ceil($rank) - 1));
        return (float)$clean[$idx];
    }

    private function readRecordsFromFile(string $file): array
    {
        $records = [];
        $this->forEachRecordInFile($file, static function (array $record) use (&$records): void {
            $records[] = $record;
        });
        return $records;
    }

    private function forEachRecordInFile(string $file, callable $consume): void
    {
        if ($this->isGzipFile($file) && function_exists('gzopen')) {
            $fp = @gzopen($file, 'rb');
            if ($fp === false) {
                return;
            }
            try {
                while (!gzeof($fp)) {
                    $line = gzgets($fp);
                    if (!is_string($line) || trim($line) === '') {
                        continue;
                    }
                    $decoded = json_decode(trim($line), true);
                    if (!is_array($decoded)) {
                        continue;
                    }
                    $consume($decoded);
                }
            } finally {
                gzclose($fp);
            }
            return;
        }

        $fp = @fopen($file, 'rb');
        if ($fp === false) {
            return;
        }
        try {
            while (($line = fgets($fp)) !== false) {
                $decoded = json_decode(trim($line), true);
                if (!is_array($decoded)) {
                    continue;
                }
                $consume($decoded);
            }
        } finally {
            fclose($fp);
        }
    }

    private function isGzipFile(string $file): bool
    {
        return str_ends_with(strtolower($file), '.gz');
    }

    private function normalizeDriver(string $driver): string
    {
        $normalized = strtolower(trim($driver));
        return $normalized === 'db' ? 'db' : 'file';
    }

    private function getDbConnection(): PDO
    {
        if ($this->pdo instanceof PDO) {
            return $this->pdo;
        }

        $dsn = trim((string)($this->dbConfig['dsn'] ?? ''));
        if ($dsn === '') {
            $host = trim((string)($this->dbConfig['host'] ?? 'localhost'));
            $port = (int)($this->dbConfig['port'] ?? ($this->dbEngine === 'pgsql' ? 5432 : 3306));
            $database = trim((string)($this->dbConfig['database'] ?? ''));
            if ($database === '') {
                throw new \RuntimeException('Usage logger DB database missing.');
            }

            if ($this->dbEngine === 'pgsql') {
                $dsn = sprintf('pgsql:host=%s;port=%d;dbname=%s', $host, $port, $database);
            } else {
                $charset = trim((string)($this->dbCharset !== '' ? $this->dbCharset : 'utf8mb4'));
                $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $host, $port, $database, $charset);
            }
        }

        $username = (string)($this->dbConfig['username'] ?? '');
        $password = (string)($this->dbConfig['password'] ?? '');

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        $pdo = new PDO($dsn, $username, $password, $options);
        if ($this->dbEngine === 'mysql') {
            $pdo->exec("SET time_zone = '+00:00'");
        }

        $this->pdo = $pdo;
        $this->ensureDbSchema($pdo);

        return $this->pdo;
    }

    private function ensureDbSchema(PDO $pdo): void
    {
        if ($this->dbSchemaReady) {
            return;
        }

        $tableSql = $this->quotedDbTable();

        if ($this->dbEngine === 'pgsql') {
            $sql = "CREATE TABLE IF NOT EXISTS {$tableSql} (
                id BIGSERIAL PRIMARY KEY,
                run_id VARCHAR(40) NOT NULL UNIQUE,
                created_at TIMESTAMP NOT NULL,
                app_version VARCHAR(48) NOT NULL DEFAULT '',
                visitor_hash VARCHAR(20) NOT NULL DEFAULT '',
                ip_hash VARCHAR(20) NOT NULL DEFAULT '',
                country_code CHAR(2) NOT NULL DEFAULT 'ZZ',
                selected_language VARCHAR(16) NOT NULL DEFAULT '',
                browser_language VARCHAR(24) NOT NULL DEFAULT '',
                selected_theme VARCHAR(16) NOT NULL DEFAULT '',
                selected_theme_variant VARCHAR(24) NOT NULL DEFAULT '',
                timezone_name VARCHAR(64) NOT NULL DEFAULT '',
                timezone_offset_min SMALLINT NOT NULL DEFAULT 0,
                source_api VARCHAR(24) NOT NULL DEFAULT '',
                request_status VARCHAR(8) NOT NULL DEFAULT 'ok',
                http_status SMALLINT NOT NULL DEFAULT 200,
                analysis_ms INTEGER NOT NULL DEFAULT 0,
                error_message VARCHAR(255) NOT NULL DEFAULT '',
                files_attempted INTEGER NOT NULL DEFAULT 0,
                files_processed INTEGER NOT NULL DEFAULT 0,
                bytes_attempted BIGINT NOT NULL DEFAULT 0,
                bytes_processed BIGINT NOT NULL DEFAULT 0,
                largest_upload_bytes BIGINT NOT NULL DEFAULT 0,
                total_rows INTEGER NOT NULL DEFAULT 0,
                parsed_rows INTEGER NOT NULL DEFAULT 0,
                skipped_rows INTEGER NOT NULL DEFAULT 0,
                merged_records INTEGER NOT NULL DEFAULT 0,
                upload_skipped_non_csv INTEGER NOT NULL DEFAULT 0,
                upload_skipped_too_large INTEGER NOT NULL DEFAULT 0,
                upload_skipped_total_overflow INTEGER NOT NULL DEFAULT 0,
                upload_skipped_upload_error INTEGER NOT NULL DEFAULT 0,
                upload_skipped_count_overflow INTEGER NOT NULL DEFAULT 0
            )";
            $pdo->exec($sql);
            $pdo->exec("CREATE INDEX IF NOT EXISTS {$this->dbTable}_idx_created ON {$tableSql}(created_at)");
            $pdo->exec("CREATE INDEX IF NOT EXISTS {$this->dbTable}_idx_status_created ON {$tableSql}(request_status, created_at)");
            $pdo->exec("CREATE INDEX IF NOT EXISTS {$this->dbTable}_idx_country_created ON {$tableSql}(country_code, created_at)");
            $pdo->exec("CREATE INDEX IF NOT EXISTS {$this->dbTable}_idx_language_created ON {$tableSql}(selected_language, created_at)");
            $pdo->exec("CREATE INDEX IF NOT EXISTS {$this->dbTable}_idx_theme_created ON {$tableSql}(selected_theme, selected_theme_variant, created_at)");
            $pdo->exec("CREATE INDEX IF NOT EXISTS {$this->dbTable}_idx_visitor_created ON {$tableSql}(visitor_hash, created_at)");
            $pdo->exec("CREATE INDEX IF NOT EXISTS {$this->dbTable}_idx_analysis_ms ON {$tableSql}(analysis_ms)");
        } else {
            $sql = "CREATE TABLE IF NOT EXISTS {$tableSql} (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                run_id VARCHAR(40) NOT NULL,
                created_at DATETIME NOT NULL,
                app_version VARCHAR(48) NOT NULL DEFAULT '',
                visitor_hash CHAR(20) NOT NULL DEFAULT '',
                ip_hash CHAR(20) NOT NULL DEFAULT '',
                country_code CHAR(2) NOT NULL DEFAULT 'ZZ',
                selected_language VARCHAR(16) NOT NULL DEFAULT '',
                browser_language VARCHAR(24) NOT NULL DEFAULT '',
                selected_theme VARCHAR(16) NOT NULL DEFAULT '',
                selected_theme_variant VARCHAR(24) NOT NULL DEFAULT '',
                timezone_name VARCHAR(64) NOT NULL DEFAULT '',
                timezone_offset_min SMALLINT NOT NULL DEFAULT 0,
                source_api VARCHAR(24) NOT NULL DEFAULT '',
                request_status VARCHAR(8) NOT NULL DEFAULT 'ok',
                http_status SMALLINT UNSIGNED NOT NULL DEFAULT 200,
                analysis_ms INT UNSIGNED NOT NULL DEFAULT 0,
                error_message VARCHAR(255) NOT NULL DEFAULT '',
                files_attempted INT UNSIGNED NOT NULL DEFAULT 0,
                files_processed INT UNSIGNED NOT NULL DEFAULT 0,
                bytes_attempted BIGINT UNSIGNED NOT NULL DEFAULT 0,
                bytes_processed BIGINT UNSIGNED NOT NULL DEFAULT 0,
                largest_upload_bytes BIGINT UNSIGNED NOT NULL DEFAULT 0,
                total_rows INT UNSIGNED NOT NULL DEFAULT 0,
                parsed_rows INT UNSIGNED NOT NULL DEFAULT 0,
                skipped_rows INT UNSIGNED NOT NULL DEFAULT 0,
                merged_records INT UNSIGNED NOT NULL DEFAULT 0,
                upload_skipped_non_csv INT UNSIGNED NOT NULL DEFAULT 0,
                upload_skipped_too_large INT UNSIGNED NOT NULL DEFAULT 0,
                upload_skipped_total_overflow INT UNSIGNED NOT NULL DEFAULT 0,
                upload_skipped_upload_error INT UNSIGNED NOT NULL DEFAULT 0,
                upload_skipped_count_overflow INT UNSIGNED NOT NULL DEFAULT 0,
                PRIMARY KEY (id),
                UNIQUE KEY uq_run_id (run_id),
                KEY idx_created (created_at),
                KEY idx_status_created (request_status, created_at),
                KEY idx_country_created (country_code, created_at),
                KEY idx_language_created (selected_language, created_at),
                KEY idx_theme_created (selected_theme, selected_theme_variant, created_at),
                KEY idx_visitor_created (visitor_hash, created_at),
                KEY idx_analysis_ms (analysis_ms)
            ) ENGINE=InnoDB DEFAULT CHARSET={$this->dbCharset} COLLATE=utf8mb4_unicode_ci";
            $pdo->exec($sql);
        }

        $this->dbSchemaReady = true;
    }

    private function quotedDbTable(): string
    {
        if ($this->dbEngine === 'pgsql') {
            return '"' . str_replace('"', '""', $this->dbTable) . '"';
        }
        return '`' . str_replace('`', '``', $this->dbTable) . '`';
    }

    private function sanitizeSqlIdentifier(string $value, string $default): string
    {
        $trimmed = trim($value);
        if ($trimmed === '' || !preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $trimmed)) {
            return $default;
        }
        return $trimmed;
    }

    private function sanitizeInt(mixed $value, int $min, int $max, int $fallback): int
    {
        if (!is_numeric($value)) {
            return $fallback;
        }

        $number = (int)round((float)$value);
        if ($number < $min) {
            return $min;
        }
        if ($number > $max) {
            return $max;
        }
        return $number;
    }

    private function shouldPruneDbOnWrite(): bool
    {
        if ($this->dbPruneProbability <= 0 || $this->dbRetentionDays <= 0) {
            return false;
        }
        return random_int(1, 100) <= $this->dbPruneProbability;
    }

    private function pruneDbOldRows(PDO $pdo): void
    {
        $tableSql = $this->quotedDbTable();
        $cutoffTs = time() - ($this->dbRetentionDays * 86400);
        $cutoff = gmdate('Y-m-d H:i:s', $cutoffTs);

        if ($this->dbEngine === 'pgsql') {
            $sql = "DELETE FROM {$tableSql} WHERE id IN (
                SELECT id FROM {$tableSql}
                WHERE created_at < :cutoff
                ORDER BY id ASC
                LIMIT {$this->dbPruneBatchSize}
            )";
        } else {
            $sql = "DELETE FROM {$tableSql}
                WHERE created_at < :cutoff
                ORDER BY id ASC
                LIMIT {$this->dbPruneBatchSize}";
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute([':cutoff' => $cutoff]);
    }

    private function dbDateTimeFromIso(string $iso): string
    {
        $ts = strtotime($iso);
        if ($ts === false) {
            $ts = time();
        }
        return gmdate('Y-m-d H:i:s', $ts);
    }

    private function isoFromDbDateTime(string $value): string
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return '';
        }

        $ts = strtotime($trimmed . ' UTC');
        if ($ts === false) {
            $ts = strtotime($trimmed);
        }
        if ($ts === false) {
            return '';
        }

        return gmdate('c', $ts);
    }

    private function recordFallbackEvent(string $context, Throwable $error): void
    {
        static $lastLoggedByContext = [];
        $now = time();
        $lastLogged = (int)($lastLoggedByContext[$context] ?? 0);
        if (($now - $lastLogged) >= 60) {
            $lastLoggedByContext[$context] = $now;
            error_log('[bitaxe-oc] usage logger fallback ' . $context . ': ' . $error->getMessage());
        }

        $payload = [
            'ts' => $now,
            'iso' => gmdate('c', $now),
            'context' => $context,
            'message' => substr($error->getMessage(), 0, 240),
        ];
        $encoded = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!is_string($encoded) || $encoded === '') {
            return;
        }

        $path = $this->resolvePath('tmp/usage_logger_fallback.json');
        $dir = dirname($path);
        if (!is_dir($dir)) {
            @mkdir($dir, 0750, true);
        }
        @file_put_contents($path, $encoded, LOCK_EX);
        @chmod($path, 0600);
    }

    private function isUniqueConstraintError(PDOException $error): bool
    {
        $sqlState = (string)($error->errorInfo[0] ?? '');
        $driverCode = (string)($error->errorInfo[1] ?? '');

        if ($sqlState === '23000' || $sqlState === '23505') {
            return true;
        }

        return in_array($driverCode, ['1062', '1555', '2067'], true);
    }
}
