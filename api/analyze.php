<?php

declare(strict_types=1);

use BitaxeOc\App\Analyzer;
use BitaxeOc\App\ApiBootstrap;
use BitaxeOc\App\HttpException;
use BitaxeOc\App\Security;
use BitaxeOc\App\UsageLogger;
use BitaxeOc\App\Version;

require_once __DIR__ . '/../app/Security.php';
require_once __DIR__ . '/../app/Analyzer.php';
require_once __DIR__ . '/../app/ApiBootstrap.php';
require_once __DIR__ . '/../app/UsageLogger.php';
require_once __DIR__ . '/../app/Version.php';

$config = ApiBootstrap::loadConfig();
$securityConfig = ApiBootstrap::section($config, 'security');
$limitsConfig = ApiBootstrap::section($config, 'limits');
$loggingConfig = ApiBootstrap::section($config, 'logging');

$clientContext = ApiBootstrap::clientContext($securityConfig);
$clientIp = (string)$clientContext['clientIp'];
$clientCountryCode = (string)$clientContext['clientCountryCode'];
$userAgent = (string)$clientContext['userAgent'];
$rateLimitIdentity = (string)$clientContext['rateLimitIdentity'];

ApiBootstrap::initRuntime($securityConfig, true);

$requestStartedAt = microtime(true);
$appVersion = Version::appVersion($config);
$normalizedFiles = [];
$result = [];
$statusCode = 200;
$requestStatus = 'ok';
$responsePayload = [
    'ok' => true,
    'appVersion' => $appVersion,
    'analyzedAt' => gmdate('c'),
    'data' => [],
];
$errorMessageForLog = '';

$normalizeCountryCode = static function (string $value): string {
    $code = strtoupper(trim($value));
    if ($code === '' || $code === 'ZZ' || $code === 'XX' || $code === '--') {
        return 'ZZ';
    }
    if (preg_match('/^[A-Z]{2}$/', $code) !== 1) {
        return 'ZZ';
    }
    return $code;
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

try {
    Security::assertPostRequest();
    Security::assertSameOriginRequest();

    $maxTotalBytes = max(1, (int)($limitsConfig['max_total_bytes'] ?? (10 * 1024 * 1024)));
    $requestOverhead = max(0, (int)($securityConfig['max_request_overhead_bytes'] ?? (1 * 1024 * 1024)));
    $contentLength = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
    if ($contentLength > ($maxTotalBytes + $requestOverhead)) {
        throw new HttpException('Istek boyutu limitin uzerinde.', 413);
    }

    $rateLimitRequests = (int)($securityConfig['rate_limit_requests'] ?? 25);
    $rateLimitWindowSec = (int)($securityConfig['rate_limit_window_sec'] ?? 300);
    Security::applyRateLimitConfig($securityConfig, 'analyze', $rateLimitRequests, $rateLimitWindowSec, $rateLimitIdentity);

    $csrfSessionKey = (string)($securityConfig['csrf_session_key'] ?? 'bitaxeoc_csrf');
    $csrfToken = isset($_POST['csrf_token']) ? (string)$_POST['csrf_token'] : '';
    Security::assertCsrfToken($csrfSessionKey, $csrfToken);

    $requestTimestamp = isset($_POST['request_ts']) ? (string)$_POST['request_ts'] : '';
    $requestNonce = isset($_POST['request_nonce']) ? (string)$_POST['request_nonce'] : '';
    Security::assertReplayProtection(
        $securityConfig,
        'analyze',
        $requestTimestamp,
        $requestNonce,
        $rateLimitIdentity
    );

    $normalizedFiles = Security::normalizeUploadedFiles($_FILES['files'] ?? []);
    $masterIndexRaw = $_POST['master_index'] ?? null;
    $masterIndex = null;
    if ($masterIndexRaw !== null && $masterIndexRaw !== '') {
        if (!is_numeric($masterIndexRaw)) {
            throw new HttpException('Master index gecersiz.', 400);
        }
        $masterIndex = (int)$masterIndexRaw;
        if ($masterIndex < 0 || $masterIndex > 100000) {
            throw new HttpException('Master index aralik disi.', 400);
        }
    }

    $analyzer = new Analyzer($limitsConfig);
    $result = $analyzer->analyze($normalizedFiles, $masterIndex);

    $responsePayload = [
        'ok' => true,
        'appVersion' => $appVersion,
        'analyzedAt' => gmdate('c'),
        'data' => $result,
    ];
} catch (HttpException $error) {
    $statusCode = $error->statusCode;
    $requestStatus = 'error';
    $errorMessageForLog = $error->getMessage();
    $responsePayload = [
        'ok' => false,
        'error' => $error->getMessage(),
    ];
} catch (Throwable $error) {
    error_log('[bitaxe-oc] analyze error: ' . $error->getMessage());
    $statusCode = 500;
    $requestStatus = 'error';
    $errorMessageForLog = 'unexpected_server_error';
    $responsePayload = [
        'ok' => false,
        'error' => 'Sunucu tarafinda beklenmeyen bir hata olustu.',
    ];
}

try {
    $logger = new UsageLogger($loggingConfig);
    if ($logger->isEnabled()) {
        $rawFileBag = $_FILES['files'] ?? [];
        $rawFileCount = 0;
        if (isset($rawFileBag['name'])) {
            $rawFileCount = is_array($rawFileBag['name']) ? count($rawFileBag['name']) : 1;
        }

        $bytesAttempted = 0;
        $largestUploadBytes = 0;
        foreach ($normalizedFiles as $file) {
            $size = max(0, (int)($file['size'] ?? 0));
            $bytesAttempted += $size;
            if ($size > $largestUploadBytes) {
                $largestUploadBytes = $size;
            }
        }

        $summary = is_array($result['summary'] ?? null) ? $result['summary'] : [];
        $uploadMeta = is_array($result['upload'] ?? null) ? $result['upload'] : [];
        $uploadSkipped = is_array($summary['uploadSkipped'] ?? null) ? $summary['uploadSkipped'] : [];
        $analysisMs = (int)max(0, round((microtime(true) - $requestStartedAt) * 1000));
        $acceptLanguage = trim((string)($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? ''));
        if (strlen($acceptLanguage) > 24) {
            $acceptLanguage = substr($acceptLanguage, 0, 24);
        }
        $selectedLanguage = trim((string)($_POST['selected_language'] ?? ''));
        if (strlen($selectedLanguage) > 12) {
            $selectedLanguage = substr($selectedLanguage, 0, 12);
        }
        if ($selectedLanguage === '') {
            $selectedLanguage = $inferLanguageCode($acceptLanguage);
        }
        $selectedTheme = trim((string)($_POST['selected_theme'] ?? ''));
        if (strlen($selectedTheme) > 16) {
            $selectedTheme = substr($selectedTheme, 0, 16);
        }
        $selectedThemeVariant = trim((string)($_POST['selected_theme_variant'] ?? ''));
        if (strlen($selectedThemeVariant) > 24) {
            $selectedThemeVariant = substr($selectedThemeVariant, 0, 24);
        }
        $countryHint = $normalizeCountryCode((string)($_POST['country_hint'] ?? ''));
        $countryCodeForLog = $normalizeCountryCode($clientCountryCode);
        if ($countryCodeForLog === 'ZZ' && $countryHint !== 'ZZ') {
            $countryCodeForLog = $countryHint;
        }

        $logger->append([
            'app_version' => $appVersion,
            'client_ip' => $clientIp,
            'country_code' => $countryCodeForLog,
            'user_agent' => substr($userAgent, 0, 1024),
            'source_api' => 'analyze',
            'request_status' => $requestStatus,
            'http_status' => $statusCode,
            'analysis_ms' => $analysisMs,
            'selected_language' => $selectedLanguage,
            'browser_language' => $acceptLanguage,
            'selected_theme' => $selectedTheme,
            'selected_theme_variant' => $selectedThemeVariant,
            'error_message' => substr($errorMessageForLog, 0, 220),
            'files_attempted' => (int)($uploadMeta['attemptedFiles'] ?? ($rawFileCount > 0 ? $rawFileCount : count($normalizedFiles))),
            'files_processed' => (int)($summary['fileCount'] ?? 0),
            'bytes_attempted' => $bytesAttempted,
            'bytes_processed' => (int)($uploadMeta['acceptedBytes'] ?? 0),
            'largest_upload_bytes' => $largestUploadBytes,
            'total_rows' => (int)($summary['totalRows'] ?? 0),
            'parsed_rows' => (int)($summary['parsedRows'] ?? 0),
            'skipped_rows' => (int)($summary['skippedRows'] ?? 0),
            'merged_records' => (int)($summary['mergedRecords'] ?? 0),
            'upload_skipped_non_csv' => (int)($uploadSkipped['nonCsv'] ?? 0),
            'upload_skipped_too_large' => (int)($uploadSkipped['tooLarge'] ?? 0),
            'upload_skipped_total_overflow' => (int)($uploadSkipped['totalOverflow'] ?? 0),
            'upload_skipped_upload_error' => (int)($uploadSkipped['uploadError'] ?? 0),
            'upload_skipped_count_overflow' => (int)($uploadSkipped['countOverflow'] ?? 0),
        ]);
    }
} catch (Throwable $loggingError) {
    error_log('[bitaxe-oc] usage log error: ' . $loggingError->getMessage());
}

Security::jsonResponse($responsePayload, $statusCode);
