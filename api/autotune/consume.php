<?php

declare(strict_types=1);

use BitaxeOc\App\ApiBootstrap;
use BitaxeOc\App\AutotuneImportStore;
use BitaxeOc\App\HttpException;
use BitaxeOc\App\Security;
use BitaxeOc\App\UsageLogger;
use BitaxeOc\App\Version;

require_once __DIR__ . '/../../app/Security.php';
require_once __DIR__ . '/../../app/ApiBootstrap.php';
require_once __DIR__ . '/../../app/AutotuneImportStore.php';
require_once __DIR__ . '/../../app/UsageLogger.php';
require_once __DIR__ . '/../../app/Version.php';

$runtime = ApiBootstrap::loadRuntimeContext(['security', 'autotune_import', 'logging']);
$config = $runtime['config'];
$securityConfig = $runtime['sections']['security'];
$importConfig = $runtime['sections']['autotune_import'];
$loggingConfig = $runtime['sections']['logging'];
$clientContext = $runtime['clientContext'];
$rateLimitIdentity = (string)$clientContext['rateLimitIdentity'];
$clientIp = (string)$clientContext['clientIp'];
$clientCountryCode = (string)$clientContext['clientCountryCode'];
$clientUserAgent = (string)$clientContext['userAgent'];
$originHeader = trim((string)($_SERVER['HTTP_ORIGIN'] ?? ''));
$appVersion = Version::appVersion($config);
$requestStartedAt = microtime(true);

$normalizeCountryCode = static function (string $value): string {
    $code = strtoupper(trim($value));
    if ($code === '' || $code === 'ZZ' || $code === 'XX' || $code === '--') {
        return 'ZZ';
    }
    if (preg_match('/^[A-Z]{2}$/', $code) === 1) {
        return $code;
    }
    return 'ZZ';
};

$inferLanguageCode = static function (string $raw): string {
    $value = strtolower(trim($raw));
    if ($value === '') {
        return '';
    }
    if (preg_match('/^[a-z]{2,3}(?:-[a-z0-9]{2,8})?/', $value, $match) !== 1) {
        return '';
    }
    $code = (string)($match[0] ?? '');
    if ($code === '') {
        return '';
    }
    if (strlen($code) > 12) {
        $code = substr($code, 0, 12);
    }
    return $code;
};

$estimateCsvRows = static function (string $csv): int {
    if (trim($csv) === '') {
        return 0;
    }

    $stream = fopen('php://temp', 'r+');
    if ($stream === false) {
        return 0;
    }

    fwrite($stream, $csv);
    rewind($stream);

    $rows = 0;
    $headerSeen = false;
    while (($line = fgetcsv($stream, 0, ',', '"', '\\')) !== false) {
        if (!$headerSeen) {
            $headerSeen = true;
            continue;
        }
        if (!is_array($line)) {
            continue;
        }
        $hasData = false;
        foreach ($line as $cell) {
            if (trim((string)$cell) !== '') {
                $hasData = true;
                break;
            }
        }
        if ($hasData) {
            $rows++;
        }
    }
    fclose($stream);

    return max(0, $rows);
};

$logConsumeEvent = static function (
    string $requestStatus,
    int $httpStatus,
    string $errorMessage,
    int $filesAttempted,
    int $filesProcessed,
    int $bytesAttempted,
    int $bytesProcessed,
    int $rowsTotal,
    int $parsedRows,
    int $mergedRecords
) use (
    $loggingConfig,
    $appVersion,
    $clientIp,
    $clientCountryCode,
    $clientUserAgent,
    $normalizeCountryCode,
    $inferLanguageCode,
    $requestStartedAt
): void {
    try {
        $logger = new UsageLogger($loggingConfig);
        if (!$logger->isEnabled()) {
            return;
        }

        $acceptLanguage = trim((string)($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? ''));
        if (strlen($acceptLanguage) > 24) {
            $acceptLanguage = substr($acceptLanguage, 0, 24);
        }

        $logger->append([
            'app_version' => $appVersion,
            'client_ip' => $clientIp,
            'country_code' => $normalizeCountryCode($clientCountryCode),
            'user_agent' => substr($clientUserAgent, 0, 1024),
            'source_api' => 'autotune_import_consume',
            'request_status' => $requestStatus === 'error' ? 'error' : 'ok',
            'http_status' => max(0, min(999, $httpStatus)),
            'analysis_ms' => max(0, (int)round((microtime(true) - $requestStartedAt) * 1000)),
            'selected_language' => $inferLanguageCode($acceptLanguage),
            'browser_language' => $acceptLanguage,
            'selected_theme' => '',
            'selected_theme_variant' => '',
            'error_message' => substr($errorMessage, 0, 220),
            'files_attempted' => max(0, $filesAttempted),
            'files_processed' => max(0, $filesProcessed),
            'bytes_attempted' => max(0, $bytesAttempted),
            'bytes_processed' => max(0, $bytesProcessed),
            'largest_upload_bytes' => max(0, $bytesProcessed),
            'total_rows' => max(0, $rowsTotal),
            'parsed_rows' => max(0, $parsedRows),
            'skipped_rows' => 0,
            'merged_records' => max(0, $mergedRecords),
            'upload_skipped_non_csv' => 0,
            'upload_skipped_too_large' => 0,
            'upload_skipped_total_overflow' => 0,
            'upload_skipped_upload_error' => 0,
            'upload_skipped_count_overflow' => 0,
        ]);
    } catch (Throwable $loggingError) {
        error_log('[bitaxe-oc] autotune consume usage log error: ' . $loggingError->getMessage());
    }
};

try {
    ApiBootstrap::initRuntime($securityConfig, false);
    $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    if (!in_array($method, ['GET', 'POST'], true)) {
        throw new HttpException('Sadece GET veya POST istegi kabul edilir.', 405);
    }

    $consumeRateLimit = max(1, (int)($importConfig['consume_rate_limit_requests'] ?? 180));
    $consumeRateLimitWindowSec = max(10, (int)($importConfig['consume_rate_limit_window_sec'] ?? 300));
    Security::applyRateLimitConfig(
        $securityConfig,
        'autotune_import_consume',
        $consumeRateLimit,
        $consumeRateLimitWindowSec,
        $rateLimitIdentity
    );

    $importId = '';
    if ($method === 'GET') {
        $importId = (string)($_GET['id'] ?? ($_GET['import'] ?? ''));
    } else {
        $maxBodyBytes = max(8 * 1024, (int)($importConfig['consume_request_max_bytes'] ?? (32 * 1024)));
        $decoded = ApiBootstrap::readJsonBody(
            $maxBodyBytes,
            'Autotune import istegi bos.',
            'Autotune import istegi boyut limiti asildi.',
            'Gecersiz JSON govdesi.',
            'Autotune import istegi gecersiz.'
        );
        $importId = (string)($decoded['id'] ?? ($decoded['import'] ?? ''));
    }

    $store = new AutotuneImportStore($importConfig);
    if (!$store->isEnabled()) {
        throw new HttpException('Autotune import ozelligi gecici olarak devre disi.', 503);
    }

    $consumed = $store->consume($importId, [
        'ip' => $clientIp,
        'origin' => $originHeader,
        'userAgent' => $clientUserAgent,
    ]);

    $state = (string)($consumed['state'] ?? 'not_found');
    if ($state === 'ok') {
        $record = is_array($consumed['record'] ?? null) ? $consumed['record'] : [];
        $csv = (string)($record['csv'] ?? '');
        $bytes = max(0, (int)($record['bytes'] ?? strlen($csv)));
        $rows = $estimateCsvRows($csv);
        $logConsumeEvent('ok', 200, '', 1, 1, $bytes, $bytes, $rows, $rows, 0);
        Security::jsonResponse([
            'ok' => true,
            'import' => $consumed['record'] ?? [],
        ], 200);
    }

    if ($state === 'expired') {
        $logConsumeEvent('error', 410, 'import_expired', 1, 0, 0, 0, 0, 0, 0);
        Security::jsonResponse([
            'ok' => false,
            'error' => 'Import kaydinin suresi dolmus.',
            'state' => 'expired',
        ], 410);
    }

    if ($state === 'consumed') {
        $record = is_array($consumed['record'] ?? null) ? $consumed['record'] : [];
        $bytes = max(0, (int)($record['bytes'] ?? 0));
        $logConsumeEvent('error', 410, 'import_already_consumed', 1, 0, $bytes, 0, 0, 0, 0);
        Security::jsonResponse([
            'ok' => false,
            'error' => 'Import kaydi zaten kullanilmis.',
            'state' => 'consumed',
            'import' => $consumed['record'] ?? [],
        ], 410);
    }

    $logConsumeEvent('error', 404, 'import_not_found', 1, 0, 0, 0, 0, 0, 0);
    Security::jsonResponse([
        'ok' => false,
        'error' => 'Import kaydi bulunamadi.',
        'state' => 'not_found',
    ], 404);
} catch (HttpException $error) {
    $logConsumeEvent(
        'error',
        $error->statusCode,
        $error->getMessage(),
        1,
        0,
        max(0, (int)($_SERVER['CONTENT_LENGTH'] ?? 0)),
        0,
        0,
        0,
        0
    );
    Security::jsonResponse([
        'ok' => false,
        'error' => $error->getMessage(),
    ], $error->statusCode);
} catch (Throwable $error) {
    error_log('[bitaxe-oc] autotune consume api error: ' . $error->getMessage());
    $logConsumeEvent(
        'error',
        500,
        'unexpected_server_error',
        1,
        0,
        max(0, (int)($_SERVER['CONTENT_LENGTH'] ?? 0)),
        0,
        0,
        0,
        0
    );
    Security::jsonResponse([
        'ok' => false,
        'error' => 'Autotune import kaydi okunurken beklenmeyen bir hata olustu.',
    ], 500);
}
