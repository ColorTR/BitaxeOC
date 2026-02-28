#!/usr/bin/env php
<?php

declare(strict_types=1);

$rootDir = dirname(__DIR__);
$config = require $rootDir . '/app/Config.php';
$logging = is_array($config['logging'] ?? null) ? $config['logging'] : [];
$db = is_array($logging['db'] ?? null) ? $logging['db'] : [];

$engine = strtolower(trim((string)($db['engine'] ?? 'mysql')));
if ($engine !== 'mysql') {
    fwrite(STDERR, "Only mysql migration is supported by this script.\n");
    exit(2);
}

$host = trim((string)($db['host'] ?? 'localhost'));
$port = (int)($db['port'] ?? 3306);
$database = trim((string)($db['database'] ?? ''));
$username = (string)($db['username'] ?? '');
$password = (string)($db['password'] ?? '');
$table = trim((string)($db['table'] ?? 'usage_events'));
$charset = trim((string)($db['charset'] ?? 'utf8mb4'));

if ($database === '' || $username === '' || $table === '') {
    fwrite(STDERR, "Missing logging.db database credentials in app/Config.secret.php\n");
    exit(2);
}
if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $table)) {
    fwrite(STDERR, "Invalid logging.db.table value.\n");
    exit(2);
}

$dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $host, $port, $database, $charset !== '' ? $charset : 'utf8mb4');

try {
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $pdo->exec("SET time_zone = '+00:00'");
} catch (Throwable $e) {
    fwrite(STDERR, "DB connection failed: " . $e->getMessage() . "\n");
    exit(1);
}

$tableSql = '`' . str_replace('`', '``', $table) . '`';
$createSql = "CREATE TABLE IF NOT EXISTS {$tableSql} (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
$pdo->exec($createSql);

$logFileRel = (string)($logging['file'] ?? 'storage/usage_logs.ndjson');
$archiveGlobRel = (string)($logging['archive_glob'] ?? 'storage/usage_logs_*.ndjson*');

$resolve = static function (string $rel) use ($rootDir): string {
    $trimmed = ltrim(str_replace('\\', '/', $rel), '/');
    return $rootDir . '/' . $trimmed;
};

$current = $resolve($logFileRel);
$archives = glob($resolve($archiveGlobRel)) ?: [];
sort($archives);
$files = [];
foreach ($archives as $path) {
    if (is_file($path)) {
        $files[] = $path;
    }
}
if (is_file($current)) {
    $files[] = $current;
}

if (!$files) {
    fwrite(STDOUT, "No usage log files found.\n");
    exit(0);
}

$insertSql = "INSERT INTO {$tableSql} (
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
)
ON DUPLICATE KEY UPDATE run_id = run_id";
$stmt = $pdo->prepare($insertSql);

$normalizeText = static function (mixed $value, int $maxLen): string {
    $text = trim((string)$value);
    if ($text === '') {
        return '';
    }
    if (strlen($text) > $maxLen) {
        $text = substr($text, 0, $maxLen);
    }
    return $text;
};
$toInt = static function (mixed $value, int $min, int $max): int {
    if (!is_numeric($value)) {
        return $min;
    }
    $n = (int)round((float)$value);
    if ($n < $min) return $min;
    if ($n > $max) return $max;
    return $n;
};
$countryCode = static function (mixed $value): string {
    $code = strtoupper(trim((string)$value));
    if ($code === '' || $code === 'XX' || $code === '--' || !preg_match('/^[A-Z]{2}$/', $code)) {
        return 'ZZ';
    }
    return $code;
};
$toDateTime = static function (mixed $value): string {
    $raw = trim((string)$value);
    $ts = $raw !== '' ? strtotime($raw) : false;
    if ($ts === false) {
        $ts = time();
    }
    return gmdate('Y-m-d H:i:s', $ts);
};

$filesProcessed = 0;
$linesRead = 0;
$inserted = 0;
$duplicates = 0;
$invalid = 0;

foreach ($files as $file) {
    $filesProcessed += 1;
    $isGz = str_ends_with(strtolower($file), '.gz');

    if ($isGz) {
        $fp = @gzopen($file, 'rb');
        if ($fp === false) {
            fwrite(STDERR, "Skip unreadable gz: {$file}\n");
            continue;
        }
        $readLine = static fn() => gzgets($fp);
        $isEof = static fn() => gzeof($fp);
        $close = static fn() => gzclose($fp);
    } else {
        $fp = @fopen($file, 'rb');
        if ($fp === false) {
            fwrite(STDERR, "Skip unreadable file: {$file}\n");
            continue;
        }
        $readLine = static fn() => fgets($fp);
        $isEof = static fn() => feof($fp);
        $close = static fn() => fclose($fp);
    }

    try {
        while (!$isEof()) {
            $line = $readLine();
            if (!is_string($line) || trim($line) === '') {
                continue;
            }
            $linesRead += 1;
            $decoded = json_decode(trim($line), true);
            if (!is_array($decoded)) {
                $invalid += 1;
                continue;
            }

            $runId = $normalizeText($decoded['run_id'] ?? '', 40);
            if ($runId === '') {
                $runId = substr(hash('sha256', $file . '|' . $linesRead . '|' . trim($line)), 0, 32);
            }

            $uploadSkipped = is_array($decoded['upload_skipped'] ?? null) ? $decoded['upload_skipped'] : [];
            $status = strtolower(trim((string)($decoded['request_status'] ?? 'ok')));
            if ($status !== 'error') {
                $status = 'ok';
            }

            $params = [
                ':run_id' => $runId,
                ':created_at' => $toDateTime($decoded['created_at'] ?? ''),
                ':app_version' => $normalizeText($decoded['app_version'] ?? '', 48),
                ':visitor_hash' => $normalizeText($decoded['visitor_hash'] ?? '', 20),
                ':ip_hash' => $normalizeText($decoded['ip_hash'] ?? '', 20),
                ':country_code' => $countryCode($decoded['country_code'] ?? 'ZZ'),
                ':selected_language' => $normalizeText($decoded['selected_language'] ?? '', 16),
                ':browser_language' => $normalizeText($decoded['browser_language'] ?? '', 24),
                ':selected_theme' => $normalizeText($decoded['selected_theme'] ?? '', 16),
                ':selected_theme_variant' => $normalizeText($decoded['selected_theme_variant'] ?? '', 24),
                ':timezone_name' => $normalizeText($decoded['timezone_name'] ?? '', 64),
                ':timezone_offset_min' => $toInt($decoded['timezone_offset_min'] ?? 0, -900, 900),
                ':source_api' => $normalizeText($decoded['source_api'] ?? '', 24),
                ':request_status' => $status,
                ':http_status' => $toInt($decoded['http_status'] ?? 200, 0, 999),
                ':analysis_ms' => $toInt($decoded['analysis_ms'] ?? 0, 0, 3600000),
                ':error_message' => $normalizeText($decoded['error_message'] ?? '', 255),
                ':files_attempted' => $toInt($decoded['files_attempted'] ?? 0, 0, 2000000000),
                ':files_processed' => $toInt($decoded['files_processed'] ?? 0, 0, 2000000000),
                ':bytes_attempted' => $toInt($decoded['bytes_attempted'] ?? 0, 0, 2000000000),
                ':bytes_processed' => $toInt($decoded['bytes_processed'] ?? 0, 0, 2000000000),
                ':largest_upload_bytes' => $toInt($decoded['largest_upload_bytes'] ?? 0, 0, 2000000000),
                ':total_rows' => $toInt($decoded['total_rows'] ?? 0, 0, 2000000000),
                ':parsed_rows' => $toInt($decoded['parsed_rows'] ?? 0, 0, 2000000000),
                ':skipped_rows' => $toInt($decoded['skipped_rows'] ?? 0, 0, 2000000000),
                ':merged_records' => $toInt($decoded['merged_records'] ?? 0, 0, 2000000000),
                ':upload_skipped_non_csv' => $toInt($uploadSkipped['nonCsv'] ?? 0, 0, 2000000000),
                ':upload_skipped_too_large' => $toInt($uploadSkipped['tooLarge'] ?? 0, 0, 2000000000),
                ':upload_skipped_total_overflow' => $toInt($uploadSkipped['totalOverflow'] ?? 0, 0, 2000000000),
                ':upload_skipped_upload_error' => $toInt($uploadSkipped['uploadError'] ?? 0, 0, 2000000000),
                ':upload_skipped_count_overflow' => $toInt($uploadSkipped['countOverflow'] ?? 0, 0, 2000000000),
            ];

            $stmt->execute($params);
            if ($stmt->rowCount() === 0) {
                $duplicates += 1;
            } else {
                $inserted += 1;
            }
        }
    } finally {
        $close();
    }
}

fwrite(STDOUT, "Files processed: {$filesProcessed}\n");
fwrite(STDOUT, "Lines read: {$linesRead}\n");
fwrite(STDOUT, "Inserted: {$inserted}\n");
fwrite(STDOUT, "Duplicates: {$duplicates}\n");
fwrite(STDOUT, "Invalid lines: {$invalid}\n");
