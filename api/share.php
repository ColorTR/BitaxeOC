<?php

declare(strict_types=1);

use BitaxeOc\App\ApiBootstrap;
use BitaxeOc\App\HttpException;
use BitaxeOc\App\Security;
use BitaxeOc\App\ShareStore;
use BitaxeOc\App\UsageIngest;
use BitaxeOc\App\UsageLogger;

require_once __DIR__ . '/../app/Security.php';
require_once __DIR__ . '/../app/ShareStore.php';
require_once __DIR__ . '/../app/ApiBootstrap.php';
require_once __DIR__ . '/../app/UsageIngest.php';
require_once __DIR__ . '/../app/UsageLogger.php';

$runtime = ApiBootstrap::loadRuntimeContext(['security', 'sharing', 'logging']);
$config = $runtime['config'];
$securityConfig = $runtime['sections']['security'];
$sharingConfig = $runtime['sections']['sharing'];
$loggingConfig = $runtime['sections']['logging'];
$clientContext = $runtime['clientContext'];
$clientIp = (string)$clientContext['clientIp'];
$clientCountryCode = (string)$clientContext['clientCountryCode'];
$userAgent = (string)$clientContext['userAgent'];
$rateLimitIdentity = (string)$clientContext['rateLimitIdentity'];

function shareJsonCachedResponse(array $payload, string $etagSeed): void
{
    $etag = '';
    if ($etagSeed !== '') {
        $etag = '"' . $etagSeed . '"';
    }

    $ifNoneMatch = trim((string)($_SERVER['HTTP_IF_NONE_MATCH'] ?? ''));
    $ifNoneMatch = preg_replace('/^W\//', '', $ifNoneMatch);

    if ($etag !== '' && $ifNoneMatch === $etag) {
        http_response_code(304);
        Security::setCommonHeaders();
        header('Cache-Control: public, max-age=120, stale-while-revalidate=60');
        header('ETag: ' . $etag);
        exit;
    }

    $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if (!is_string($json) || $json === '') {
        throw new HttpException('Paylasim verisi encode edilemedi.', 500);
    }
    if ($etag === '') {
        $etag = '"' . sha1($json) . '"';
    }

    if ($ifNoneMatch === $etag) {
        http_response_code(304);
        Security::setCommonHeaders();
        header('Cache-Control: public, max-age=120, stale-while-revalidate=60');
        header('ETag: ' . $etag);
        exit;
    }

    http_response_code(200);
    Security::setCommonHeaders();
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: public, max-age=120, stale-while-revalidate=60');
    header('ETag: ' . $etag);
    echo $json;
    exit;
}

function shareNotModifiedResponse(string $etag): void
{
    http_response_code(304);
    Security::setCommonHeaders();
    header('Cache-Control: public, max-age=120, stale-while-revalidate=60');
    if ($etag !== '') {
        header('ETag: "' . $etag . '"');
    }
    exit;
}

try {
    ApiBootstrap::initRuntime($securityConfig, false);
    $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    $store = new ShareStore($sharingConfig);

    if ($method === 'POST') {
        ApiBootstrap::assertPostAndSameOrigin();
        $action = strtolower(trim((string)($_GET['action'] ?? 'create')));

        if ($action === 'usage-log') {
            $logger = new UsageLogger($loggingConfig);
            if (!$logger->isEnabled()) {
                Security::jsonResponse(['ok' => true, 'logged' => false], 200);
            }

            $logger->append(UsageIngest::buildLogContext(
                $config,
                $securityConfig,
                $loggingConfig,
                $rateLimitIdentity,
                $clientIp,
                $clientCountryCode,
                $userAgent,
                'share_usage_log'
            ));

            Security::jsonResponse(['ok' => true, 'logged' => true], 201);
        }

        if ($action !== '' && $action !== 'create') {
            throw new HttpException('Gecersiz islem.', 400);
        }

        $maxPayloadBytes = max(16 * 1024, (int)($sharingConfig['max_payload_bytes'] ?? (1200 * 1024)));
        $requestOverhead = max(0, (int)($securityConfig['max_request_overhead_bytes'] ?? (256 * 1024)));
        $maxRequestBytes = $maxPayloadBytes + $requestOverhead;

        $rateLimitRequests = max(1, (int)($sharingConfig['create_rate_limit_requests'] ?? 20));
        $rateLimitWindowSec = max(10, (int)($sharingConfig['create_rate_limit_window_sec'] ?? 300));
        Security::applyRateLimitConfig($securityConfig, 'share_create', $rateLimitRequests, $rateLimitWindowSec, $rateLimitIdentity);

        $decoded = ApiBootstrap::readJsonBody(
            $maxRequestBytes,
            'Paylasim istegi bos.',
            'Paylasim istegi boyut limiti asildi.',
            'Gecersiz JSON govdesi.',
            'Paylasim istegi gecersiz.'
        );

        $requestTimestamp = isset($decoded['request_ts']) ? (string)$decoded['request_ts'] : '';
        $requestNonce = isset($decoded['request_nonce']) ? (string)$decoded['request_nonce'] : '';
        Security::assertReplayProtection(
            $securityConfig,
            'share_create',
            $requestTimestamp,
            $requestNonce,
            $rateLimitIdentity
        );

        $payload = $decoded['payload'] ?? null;
        if (!is_array($payload)) {
            throw new HttpException('Paylasim payload alani gecersiz.', 400);
        }

        $ttlSec = null;
        if (isset($decoded['ttl_sec']) && is_numeric($decoded['ttl_sec'])) {
            $ttlSec = (int)$decoded['ttl_sec'];
        }

        $share = $store->createShare($payload, $ttlSec);
        $statusCode = !empty($share['reused']) ? 200 : 201;
        Security::jsonResponse([
            'ok' => true,
            'share' => $share,
        ], $statusCode);
    }

    if ($method === 'GET') {
        $rateLimitRequests = max(10, (int)($sharingConfig['view_rate_limit_requests'] ?? 240));
        $rateLimitWindowSec = max(10, (int)($sharingConfig['view_rate_limit_window_sec'] ?? 300));
        Security::applyRateLimitConfig($securityConfig, 'share_view', $rateLimitRequests, $rateLimitWindowSec, $rateLimitIdentity);

        $token = strtolower(trim((string)($_GET['s'] ?? ($_GET['share'] ?? ''))));
        if ($token === '') {
            throw new HttpException('Paylasim token alani bos.', 400);
        }

        $meta = $store->getShareMeta($token);
        if (!$meta) {
            throw new HttpException('Paylasim linki bulunamadi veya suresi doldu.', 404);
        }
        $metaPayloadSha1 = strtolower(trim((string)($meta['payloadSha1'] ?? '')));
        $ifNoneMatch = trim((string)($_SERVER['HTTP_IF_NONE_MATCH'] ?? ''));
        $ifNoneMatch = preg_replace('/^W\//', '', $ifNoneMatch);
        if ($metaPayloadSha1 !== '' && $ifNoneMatch === '"' . $metaPayloadSha1 . '"') {
            shareNotModifiedResponse($metaPayloadSha1);
        }

        $share = $store->getShare($token);
        if (!$share) {
            throw new HttpException('Paylasim linki bulunamadi veya suresi doldu.', 404);
        }

        $response = [
            'ok' => true,
            'share' => [
                'token' => (string)($share['token'] ?? $token),
                'createdAt' => (string)($share['createdAt'] ?? ''),
                'expiresAt' => (string)($share['expiresAt'] ?? ''),
                'payload' => is_array($share['payload'] ?? null) ? $share['payload'] : [],
            ],
        ];

        $etagSeed = $metaPayloadSha1 !== '' ? $metaPayloadSha1 : (string)($share['payloadSha1'] ?? '');
        shareJsonCachedResponse($response, $etagSeed);
    }

    throw new HttpException('Sadece GET veya POST istegi kabul edilir.', 405);
} catch (HttpException $error) {
    Security::jsonResponse([
        'ok' => false,
        'error' => $error->getMessage(),
    ], $error->statusCode);
} catch (Throwable $error) {
    error_log('[bitaxe-oc] share api error: ' . $error->getMessage());
    Security::jsonResponse([
        'ok' => false,
        'error' => 'Paylasim islemi sirasinda beklenmeyen bir hata olustu.',
    ], 500);
}
