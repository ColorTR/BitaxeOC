<?php

declare(strict_types=1);

use BitaxeOc\App\ApiBootstrap;
use BitaxeOc\App\AutotuneImportStore;
use BitaxeOc\App\HttpException;
use BitaxeOc\App\Security;

require_once __DIR__ . '/../../app/Security.php';
require_once __DIR__ . '/../../app/ApiBootstrap.php';
require_once __DIR__ . '/../../app/AutotuneImportStore.php';

$runtime = ApiBootstrap::loadRuntimeContext(['security', 'autotune_import']);
$securityConfig = $runtime['sections']['security'];
$importConfig = $runtime['sections']['autotune_import'];
$clientContext = $runtime['clientContext'];
$rateLimitIdentity = (string)$clientContext['rateLimitIdentity'];
$clientIp = (string)$clientContext['clientIp'];
$clientUserAgent = (string)$clientContext['userAgent'];
$originHeader = trim((string)($_SERVER['HTTP_ORIGIN'] ?? ''));

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
        Security::jsonResponse([
            'ok' => true,
            'import' => $consumed['record'] ?? [],
        ], 200);
    }

    if ($state === 'expired') {
        Security::jsonResponse([
            'ok' => false,
            'error' => 'Import kaydinin suresi dolmus.',
            'state' => 'expired',
        ], 410);
    }

    if ($state === 'consumed') {
        Security::jsonResponse([
            'ok' => false,
            'error' => 'Import kaydi zaten kullanilmis.',
            'state' => 'consumed',
            'import' => $consumed['record'] ?? [],
        ], 410);
    }

    Security::jsonResponse([
        'ok' => false,
        'error' => 'Import kaydi bulunamadi.',
        'state' => 'not_found',
    ], 404);
} catch (HttpException $error) {
    Security::jsonResponse([
        'ok' => false,
        'error' => $error->getMessage(),
    ], $error->statusCode);
} catch (Throwable $error) {
    error_log('[bitaxe-oc] autotune consume api error: ' . $error->getMessage());
    Security::jsonResponse([
        'ok' => false,
        'error' => 'Autotune import kaydi okunurken beklenmeyen bir hata olustu.',
    ], 500);
}

