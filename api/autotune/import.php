<?php

declare(strict_types=1);

use BitaxeOc\App\ApiBootstrap;
use BitaxeOc\App\AutotuneImportStore;
use BitaxeOc\App\HttpException;
use BitaxeOc\App\Security;

require_once __DIR__ . '/../../app/Security.php';
require_once __DIR__ . '/../../app/ApiBootstrap.php';
require_once __DIR__ . '/../../app/AutotuneImportStore.php';

/**
 * @param array<string,mixed> $importConfig
 */
function autotuneResolveAllowedOrigin(string $origin, array $importConfig): string
{
    $origin = trim($origin);
    if ($origin === '' || strtolower($origin) === 'null') {
        return '';
    }

    $parts = parse_url($origin);
    if (!is_array($parts)) {
        return '';
    }

    $scheme = strtolower((string)($parts['scheme'] ?? ''));
    $host = strtolower(trim((string)($parts['host'] ?? '')));
    $port = isset($parts['port']) ? (int)$parts['port'] : 0;
    if (!in_array($scheme, ['http', 'https'], true) || $host === '') {
        return '';
    }

    $normalizedOrigin = $scheme . '://' . $host;
    if ($port > 0) {
        $normalizedOrigin .= ':' . $port;
    }

    if (!empty($importConfig['allow_any_origin'])) {
        return $normalizedOrigin;
    }

    $allowedOriginsRaw = $importConfig['allowed_origins'] ?? [];
    if (is_string($allowedOriginsRaw)) {
        $allowedOriginsRaw = explode(',', $allowedOriginsRaw);
    }
    $allowedOrigins = [];
    if (is_array($allowedOriginsRaw)) {
        foreach ($allowedOriginsRaw as $entry) {
            $value = strtolower(trim((string)$entry));
            if ($value !== '') {
                $allowedOrigins[] = $value;
            }
        }
    }
    if (in_array(strtolower($normalizedOrigin), $allowedOrigins, true)) {
        return $normalizedOrigin;
    }

    $requestHost = Security::normalizeHost((string)($_SERVER['HTTP_HOST'] ?? ''));
    if ($requestHost !== '' && $host === $requestHost) {
        return $normalizedOrigin;
    }

    if (!array_key_exists('allow_bitaxe_origin', $importConfig) || !empty($importConfig['allow_bitaxe_origin'])) {
        if ($host === 'bitaxe.colortr.com') {
            return $normalizedOrigin;
        }
    }

    if (!empty($importConfig['allow_localhost_origins'])) {
        if (in_array($host, ['localhost', '127.0.0.1', '::1', '[::1]'], true)) {
            return $normalizedOrigin;
        }
    }

    if (!array_key_exists('allow_private_lan_origins', $importConfig) || !empty($importConfig['allow_private_lan_origins'])) {
        if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $isPrivate = (
                str_starts_with($host, '10.')
                || str_starts_with($host, '192.168.')
                || preg_match('/^172\.(1[6-9]|2[0-9]|3[0-1])\./', $host) === 1
            );
            if ($isPrivate) {
                return $normalizedOrigin;
            }
        } elseif (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $ipv6 = strtolower($host);
            if (
                $ipv6 === '::1'
                || str_starts_with($ipv6, 'fc')
                || str_starts_with($ipv6, 'fd')
            ) {
                return $normalizedOrigin;
            }
        }
    }

    return '';
}

/**
 * @param array<string,mixed> $importConfig
 */
function autotuneApplyCorsHeaders(string $allowOrigin, array $importConfig): void
{
    if ($allowOrigin === '') {
        return;
    }
    $maxAge = max(60, (int)($importConfig['cors_max_age_sec'] ?? 600));
    header('Access-Control-Allow-Origin: ' . $allowOrigin);
    header('Vary: Origin');
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, X-Requested-With, X-AxeOS-Source, X-AxeOS-Version');
    header('Access-Control-Max-Age: ' . $maxAge);
    header('Access-Control-Allow-Credentials: false');
}

function autotuneCurrentOrigin(): string
{
    $forwardedProto = strtolower(trim((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')));
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $forwardedProto === 'https';
    $scheme = $https ? 'https' : 'http';

    $host = trim((string)($_SERVER['HTTP_HOST'] ?? 'oc.colortr.com'));
    if ($host === '') {
        $host = 'oc.colortr.com';
    }

    return $scheme . '://' . $host;
}

function autotuneBasePathFromScript(): string
{
    $script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '/api/autotune/import.php'));
    $suffixes = ['/api/autotune/import.php', '/api/autotune/import'];
    foreach ($suffixes as $suffix) {
        if (str_ends_with($script, $suffix)) {
            $base = substr($script, 0, -strlen($suffix));
            $base = rtrim($base, '/');
            if ($base === '' || $base === '/' || $base === '.') {
                return '';
            }
            return $base;
        }
    }

    $dir = str_replace('\\', '/', dirname($script));
    $dir = rtrim($dir, '/');
    if ($dir === '' || $dir === '/' || $dir === '.') {
        return '';
    }
    return $dir;
}

$runtime = ApiBootstrap::loadRuntimeContext(['security', 'autotune_import']);
$securityConfig = $runtime['sections']['security'];
$importConfig = $runtime['sections']['autotune_import'];
$clientContext = $runtime['clientContext'];
$rateLimitIdentity = (string)$clientContext['rateLimitIdentity'];
$clientIp = (string)$clientContext['clientIp'];
$clientUserAgent = (string)$clientContext['userAgent'];

try {
    ApiBootstrap::initRuntime($securityConfig, false);
    $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    $originHeader = trim((string)($_SERVER['HTTP_ORIGIN'] ?? ''));
    $allowedOrigin = autotuneResolveAllowedOrigin($originHeader, $importConfig);
    $allowNoOriginRequests = !array_key_exists('allow_no_origin_requests', $importConfig) || !empty($importConfig['allow_no_origin_requests']);

    if ($method === 'OPTIONS') {
        if ($originHeader !== '' && $allowedOrigin === '') {
            throw new HttpException('Origin yetkisi reddedildi.', 403);
        }
        if ($originHeader === '' && !$allowNoOriginRequests) {
            throw new HttpException('Origin gerekli.', 403);
        }
        http_response_code(204);
        Security::setCommonHeaders();
        autotuneApplyCorsHeaders($allowedOrigin, $importConfig);
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        exit;
    }

    if ($method !== 'POST') {
        throw new HttpException('Sadece POST istegi kabul edilir.', 405);
    }

    if ($originHeader !== '' && $allowedOrigin === '') {
        throw new HttpException('Origin yetkisi reddedildi.', 403);
    }
    if ($originHeader === '' && !$allowNoOriginRequests) {
        throw new HttpException('Origin gerekli.', 403);
    }

    $store = new AutotuneImportStore($importConfig);
    if (!$store->isEnabled()) {
        throw new HttpException('Autotune import ozelligi gecici olarak devre disi.', 503);
    }

    $createRateLimit = max(1, (int)($importConfig['create_rate_limit_requests'] ?? 30));
    $createRateLimitWindowSec = max(10, (int)($importConfig['create_rate_limit_window_sec'] ?? 300));
    Security::applyRateLimitConfig(
        $securityConfig,
        'autotune_import_create',
        $createRateLimit,
        $createRateLimitWindowSec,
        $rateLimitIdentity
    );

    $maxCsvBytes = max(64 * 1024, (int)($importConfig['max_csv_bytes'] ?? (2 * 1024 * 1024)));
    $maxRequestBytes = max(
        $maxCsvBytes + 256 * 1024,
        (int)($importConfig['max_request_bytes'] ?? ($maxCsvBytes + 256 * 1024))
    );

    $decoded = ApiBootstrap::readJsonBody(
        $maxRequestBytes,
        'Autotune import istegi bos.',
        'Autotune import istegi boyut limiti asildi.',
        'Gecersiz JSON govdesi.',
        'Autotune import istegi gecersiz.'
    );

    $payload = is_array($decoded['payload'] ?? null) ? $decoded['payload'] : $decoded;
    $type = strtolower(trim((string)($payload['type'] ?? 'autotune_csv_import')));
    if ($type !== '' && $type !== 'autotune_csv_import') {
        throw new HttpException('Gecersiz import tipi.', 400);
    }

    $ttlSec = null;
    if (isset($payload['ttl_sec']) && is_numeric($payload['ttl_sec'])) {
        $ttlSec = (int)$payload['ttl_sec'];
    }

    $created = $store->create(
        [
            'source' => (string)($payload['source'] ?? 'axeos'),
            'filename' => (string)($payload['filename'] ?? 'autotune_report.csv'),
            'csv' => (string)($payload['csv'] ?? ''),
            'timestamp' => $payload['timestamp'] ?? ($payload['ts'] ?? null),
        ],
        $ttlSec,
        [
            'ip' => $clientIp,
            'origin' => $originHeader,
            'userAgent' => $clientUserAgent,
        ]
    );

    $appOrigin = autotuneCurrentOrigin();
    $basePath = autotuneBasePathFromScript();
    $importPath = ($basePath === '' ? '' : $basePath) . '/r/' . rawurlencode($created['id']);
    $consumePath = ($basePath === '' ? '' : $basePath) . '/api/autotune/consume.php?id=' . rawurlencode($created['id']);
    $importUrl = $appOrigin . $importPath;
    $consumeUrl = $appOrigin . $consumePath;

    autotuneApplyCorsHeaders($allowedOrigin, $importConfig);
    Security::jsonResponse([
        'ok' => true,
        'import' => [
            'id' => $created['id'],
            'source' => $created['source'],
            'filename' => $created['filename'],
            'bytes' => $created['bytes'],
            'csvHash' => $created['csvHash'],
            'createdAt' => $created['createdAt'],
            'expiresAt' => $created['expiresAt'],
            'importPath' => $importPath,
            'importUrl' => $importUrl,
            'consumePath' => $consumePath,
            'consumeUrl' => $consumeUrl,
            'reused' => !empty($created['reused']),
        ],
    ], 201);
} catch (HttpException $error) {
    $originHeader = trim((string)($_SERVER['HTTP_ORIGIN'] ?? ''));
    if ($originHeader !== '') {
        $allowedOrigin = autotuneResolveAllowedOrigin($originHeader, $importConfig ?? []);
        autotuneApplyCorsHeaders($allowedOrigin, $importConfig ?? []);
    }
    Security::jsonResponse([
        'ok' => false,
        'error' => $error->getMessage(),
    ], $error->statusCode);
} catch (Throwable $error) {
    error_log('[bitaxe-oc] autotune import api error: ' . $error->getMessage());
    $originHeader = trim((string)($_SERVER['HTTP_ORIGIN'] ?? ''));
    if ($originHeader !== '') {
        $allowedOrigin = autotuneResolveAllowedOrigin($originHeader, $importConfig ?? []);
        autotuneApplyCorsHeaders($allowedOrigin, $importConfig ?? []);
    }
    Security::jsonResponse([
        'ok' => false,
        'error' => 'Autotune import islemi sirasinda beklenmeyen bir hata olustu.',
    ], 500);
}
