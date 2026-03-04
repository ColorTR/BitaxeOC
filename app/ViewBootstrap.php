<?php

declare(strict_types=1);

namespace BitaxeOc\App;

final class ViewBootstrap
{
    private static function extractRouteToken(array $server, array $query, string $basePath): string
    {
        $fromQuery = strtolower(trim((string)($query['r'] ?? '')));
        if ($fromQuery !== '' && preg_match('/^[a-f0-9]{16,80}$/', $fromQuery) === 1) {
            return $fromQuery;
        }

        $requestUri = (string)($server['REQUEST_URI'] ?? '');
        if ($requestUri === '') return '';
        $requestPath = (string)(parse_url($requestUri, PHP_URL_PATH) ?? '');
        if ($requestPath === '') return '';

        $normalizedBase = rtrim($basePath, '/');
        if ($normalizedBase !== '' && str_starts_with($requestPath, $normalizedBase . '/')) {
            $requestPath = substr($requestPath, strlen($normalizedBase));
        } elseif ($normalizedBase !== '' && $requestPath === $normalizedBase) {
            $requestPath = '/';
        }

        $requestPath = '/' . ltrim($requestPath, '/');
        if (!preg_match('/^\/r\/([a-f0-9]{16,80})\/?$/i', $requestPath, $match)) {
            return '';
        }

        $token = strtolower(trim((string)($match[1] ?? '')));
        return (preg_match('/^[a-f0-9]{16,80}$/', $token) === 1) ? $token : '';
    }

    /**
     * Build index.php runtime context from config + request metadata.
     */
    public static function forIndex(array $config, array $server, array $query): array
    {
        $limitsConfig = is_array($config['limits'] ?? null) ? $config['limits'] : [];
        $frontendConfig = is_array($config['frontend'] ?? null) ? $config['frontend'] : [];

        $appVersion = Version::appVersion($config);
        $displayVersion = Version::display($config);
        $brandVersionLine = Version::brandLine($config);
        $assetVersionToken = rawurlencode($appVersion);

        $maxUploadFilesPerBatch = max(1, (int)($limitsConfig['max_files_per_batch'] ?? 30));
        $maxCsvFileBytes = max(1, (int)($limitsConfig['max_file_bytes'] ?? (350 * 1024)));
        $maxUploadTotalBytes = max($maxCsvFileBytes, (int)($limitsConfig['max_total_bytes'] ?? (6 * 1024 * 1024)));
        $csvMaxDataRows = max(100, (int)($limitsConfig['csv_max_data_rows'] ?? 7000));
        $csvParseTimeBudgetMs = max(500, (int)($limitsConfig['csv_parse_time_budget_ms'] ?? 8000));

        $shareToken = strtolower(trim((string)($query['share'] ?? ($query['s'] ?? ''))));
        if ($shareToken !== '' && $shareToken !== 'test' && preg_match('/^[a-f0-9]{16,80}$/', $shareToken) !== 1) {
            $shareToken = '';
        }
        $isShareView = $shareToken !== '';
        $isStaticTestShareView = ($shareToken === 'test');
        $importToken = strtolower(trim((string)($query['import'] ?? ($query['i'] ?? ''))));
        if ($importToken !== '' && preg_match('/^[a-f0-9]{16,80}$/', $importToken) !== 1) {
            $importToken = '';
        }

        $scriptName = str_replace('\\', '/', (string)($server['SCRIPT_NAME'] ?? '/index.php'));
        $scriptDir = rtrim(dirname($scriptName), '/');
        if ($scriptDir === '' || $scriptDir === '.' || $scriptDir === '\\' || $scriptDir === '/') {
            $scriptDir = '';
        }
        $basePath = $scriptDir;

        $routeToken = self::extractRouteToken($server, $query, $basePath);
        if ($importToken === '' && $routeToken !== '') {
            $importToken = $routeToken;
        }
        $isImportView = $importToken !== '';

        $forwardedProto = strtolower(trim((string)($server['HTTP_X_FORWARDED_PROTO'] ?? '')));
        $isHttps = (!empty($server['HTTPS']) && $server['HTTPS'] !== 'off') || $forwardedProto === 'https';
        $appScheme = $isHttps ? 'https' : 'http';

        $appHost = trim((string)($server['HTTP_HOST'] ?? 'oc.colortr.com'));
        if ($appHost === '') {
            $appHost = 'oc.colortr.com';
        }

        $appOrigin = $appScheme . '://' . $appHost;
        $appBaseUrl = $appOrigin . $basePath;
        $assetBasePath = $basePath;

        $seoCanonicalUrl = $isStaticTestShareView
            ? ($appBaseUrl . '/?share=test')
            : ($appBaseUrl . '/');

        $tailwindMode = strtolower(trim((string)($frontendConfig['tailwind_mode'] ?? 'runtime')));
        if (!in_array($tailwindMode, ['runtime', 'static'], true)) {
            $tailwindMode = 'runtime';
        }
        $tailwindStaticCssRel = trim((string)($frontendConfig['tailwind_static_css'] ?? 'assets/vendor/tailwind-static.css'));
        if ($tailwindStaticCssRel === '') {
            $tailwindStaticCssRel = 'assets/vendor/tailwind-static.css';
        }
        $tailwindStaticCssPath = dirname(__DIR__) . '/' . ltrim($tailwindStaticCssRel, '/');
        $tailwindStaticAvailable = is_file($tailwindStaticCssPath);
        $tailwindUseStatic = ($tailwindMode === 'static' && $tailwindStaticAvailable);

        return [
            'appVersion' => $appVersion,
            'displayVersion' => $displayVersion,
            'brandVersionLine' => $brandVersionLine,
            'assetVersionToken' => $assetVersionToken,
            'maxUploadFilesPerBatch' => $maxUploadFilesPerBatch,
            'maxCsvFileBytes' => $maxCsvFileBytes,
            'maxUploadTotalBytes' => $maxUploadTotalBytes,
            'csvMaxDataRows' => $csvMaxDataRows,
            'csvParseTimeBudgetMs' => $csvParseTimeBudgetMs,
            'shareToken' => $shareToken,
            'isShareView' => $isShareView,
            'isStaticTestShareView' => $isStaticTestShareView,
            'importToken' => $importToken,
            'isImportView' => $isImportView,
            'basePath' => $basePath,
            'appBaseUrl' => $appBaseUrl,
            'assetBasePath' => $assetBasePath,
            'seoCanonicalUrl' => $seoCanonicalUrl,
            'seoTitle' => 'Bitaxe & NerdAxe OC Stats Analyzer | Lottery Mining Dashboard',
            'seoDescription' => 'Analyze Bitaxe and NerdAxe lottery mining OC stats from CSV benchmarks. Compare hashrate, efficiency (J/TH), ASIC and VRM temperatures, error rate, and power to find stable overclock profiles.',
            'seoKeywords' => 'Bitaxe, NerdAxe, lottery mining, OC stats, overclock, hashrate, JTH, J/TH, ASIC miner tuning, benchmark dashboard, GT800',
            'seoRobots' => (($isShareView && !$isStaticTestShareView) || $isImportView)
                ? 'noindex,nofollow,noarchive'
                : 'index,follow,max-image-preview:large,max-snippet:-1,max-video-preview:-1',
            'tailwindMode' => $tailwindMode,
            'tailwindUseStatic' => $tailwindUseStatic,
            'tailwindStaticCssRel' => $tailwindStaticCssRel,
        ];
    }
}
