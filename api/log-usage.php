<?php

declare(strict_types=1);

use BitaxeOc\App\ApiBootstrap;
use BitaxeOc\App\HttpException;
use BitaxeOc\App\Security;
use BitaxeOc\App\UsageIngest;
use BitaxeOc\App\UsageLogger;

require_once __DIR__ . '/../app/Security.php';
require_once __DIR__ . '/../app/ApiBootstrap.php';
require_once __DIR__ . '/../app/UsageIngest.php';
require_once __DIR__ . '/../app/UsageLogger.php';

$runtime = ApiBootstrap::loadRuntimeContext(['security', 'logging']);
$config = $runtime['config'];
$securityConfig = $runtime['sections']['security'];
$loggingConfig = $runtime['sections']['logging'];
$clientContext = $runtime['clientContext'];
$clientIp = (string)$clientContext['clientIp'];
$clientCountryCode = (string)$clientContext['clientCountryCode'];
$userAgent = (string)$clientContext['userAgent'];
$rateLimitIdentity = (string)$clientContext['rateLimitIdentity'];

try {
    ApiBootstrap::initRuntime($securityConfig, true);
    ApiBootstrap::assertPostAndSameOrigin();

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
        'log_usage'
    ));

    Security::jsonResponse(['ok' => true, 'logged' => true], 201);
} catch (HttpException $error) {
    Security::jsonResponse([
        'ok' => false,
        'error' => $error->getMessage(),
    ], $error->statusCode);
} catch (Throwable $error) {
    error_log('[bitaxe-oc] usage ingest error: ' . $error->getMessage());
    Security::jsonResponse([
        'ok' => false,
        'error' => 'Kullanim logu yazilirken beklenmeyen bir hata olustu.',
    ], 500);
}
