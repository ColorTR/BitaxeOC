<?php

declare(strict_types=1);

use BitaxeOc\App\ApiBootstrap;
use BitaxeOc\App\AutotuneImportStore;
use BitaxeOc\App\HttpException;
use BitaxeOc\App\Security;
use BitaxeOc\App\ShareStore;
use BitaxeOc\App\UsageLogger;
use BitaxeOc\App\Version;
use BitaxeOc\App\ViewBootstrap;

require_once __DIR__ . '/../app/Security.php';
require_once __DIR__ . '/../app/AutotuneImportStore.php';
require_once __DIR__ . '/../app/ShareStore.php';
require_once __DIR__ . '/../app/UsageLogger.php';
require_once __DIR__ . '/../app/ApiBootstrap.php';
require_once __DIR__ . '/../app/ViewBootstrap.php';
require_once __DIR__ . '/../app/Version.php';

$results = [];

$run = static function (string $name, callable $fn) use (&$results): void {
    $start = microtime(true);
    try {
        $detail = $fn();
        $results[] = [
            'name' => $name,
            'ok' => true,
            'ms' => (int)round((microtime(true) - $start) * 1000),
            'detail' => is_string($detail) ? $detail : '',
        ];
    } catch (Throwable $error) {
        $results[] = [
            'name' => $name,
            'ok' => false,
            'ms' => (int)round((microtime(true) - $start) * 1000),
            'detail' => $error->getMessage(),
        ];
    }
};

$dbTestConfig = (static function (): ?array {
    $dsn = trim((string)(getenv('BITAXE_TEST_DB_DSN') ?: ''));
    if ($dsn === '') {
        return null;
    }

    $engine = strtolower(trim((string)(getenv('BITAXE_TEST_DB_ENGINE') ?: '')));
    if (!in_array($engine, ['mysql', 'pgsql'], true)) {
        $engine = str_starts_with($dsn, 'pgsql:') ? 'pgsql' : 'mysql';
    }

    $suffixRaw = strtolower(trim((string)(getenv('BITAXE_TEST_DB_SUFFIX') ?: '')));
    if (!preg_match('/^[a-z0-9_]{0,20}$/', $suffixRaw)) {
        $suffixRaw = '';
    }
    $suffix = ($suffixRaw !== '') ? ('_' . $suffixRaw) : '';

    return [
        'dsn' => $dsn,
        'engine' => $engine,
        'username' => (string)(getenv('BITAXE_TEST_DB_USER') ?: ''),
        'password' => (string)(getenv('BITAXE_TEST_DB_PASS') ?: ''),
        'tables' => [
            'transient' => 'security_events_unit' . $suffix,
            'share' => 'share_records_unit' . $suffix,
            'usage' => 'usage_events_unit' . $suffix,
        ],
    ];
})();

$quoteTable = static function (string $engine, string $table): string {
    $safe = strtolower(trim($table));
    if (!preg_match('/^[a-z0-9_]{1,64}$/', $safe)) {
        throw new RuntimeException('invalid table identifier: ' . $table);
    }
    if ($engine === 'pgsql') {
        return '"' . str_replace('"', '""', $safe) . '"';
    }
    return '`' . str_replace('`', '``', $safe) . '`';
};

$buildDbConfig = static function (array $dbTestConfig, string $table): array {
    return [
        'engine' => (string)$dbTestConfig['engine'],
        'dsn' => (string)$dbTestConfig['dsn'],
        'username' => (string)$dbTestConfig['username'],
        'password' => (string)$dbTestConfig['password'],
        'table' => $table,
        'charset' => 'utf8mb4',
    ];
};

$run('Version display mapping', static function (): string {
    $cfg = ApiBootstrap::loadConfig();
    $app = Version::appVersion($cfg);
    $display = Version::display($cfg);
    if (!preg_match('/^v([0-9]+)$/', $app, $m)) {
        throw new RuntimeException('appVersion format mismatch');
    }
    $n = (int)$m[1];
    $expectedDisplay = sprintf('v%d.%d', intdiv($n, 10), ($n % 10));
    if ($display !== $expectedDisplay) {
        throw new RuntimeException('display mismatch');
    }
    return $app . ' -> ' . $display;
});

$run('View bootstrap canonical + robots', static function (): string {
    $cfg = ApiBootstrap::loadConfig();
    $server = [
        'SCRIPT_NAME' => '/index.php',
        'REQUEST_URI' => '/',
        'HTTP_HOST' => 'oc.colortr.com',
        'HTTPS' => 'on',
    ];
    $ctxMain = ViewBootstrap::forIndex($cfg, $server, []);
    if (($ctxMain['seoCanonicalUrl'] ?? '') !== 'https://oc.colortr.com/') {
        throw new RuntimeException('canonical mismatch main');
    }
    if (($ctxMain['seoRobots'] ?? '') === 'noindex,nofollow,noarchive') {
        throw new RuntimeException('main should be indexable');
    }
    $ctxShare = ViewBootstrap::forIndex($cfg, $server, ['share' => str_repeat('a', 16)]);
    if (($ctxShare['seoRobots'] ?? '') !== 'noindex,nofollow,noarchive') {
        throw new RuntimeException('share robots mismatch');
    }
    $ctxImport = ViewBootstrap::forIndex($cfg, $server, ['import' => str_repeat('b', 16)]);
    if (($ctxImport['seoRobots'] ?? '') !== 'noindex,nofollow,noarchive') {
        throw new RuntimeException('import robots mismatch');
    }
    $serverRoute = $server;
    $serverRoute['REQUEST_URI'] = '/r/' . str_repeat('c', 16);
    $ctxRoute = ViewBootstrap::forIndex($cfg, $serverRoute, []);
    if (($ctxRoute['seoRobots'] ?? '') !== 'noindex,nofollow,noarchive') {
        throw new RuntimeException('route robots mismatch');
    }
    if (($ctxRoute['importToken'] ?? '') !== str_repeat('c', 16)) {
        throw new RuntimeException('route import token mismatch');
    }
    return 'main/share/import/route robots ok';
});

$run('Api bootstrap client context identity', static function (): string {
    $backup = $_SERVER;
    try {
        $_SERVER['REMOTE_ADDR'] = '10.0.0.55'; // trusted proxy
        $_SERVER['HTTP_USER_AGENT'] = 'UnitTestAgent/1.0';
        $_SERVER['HTTP_CF_CONNECTING_IP'] = '203.0.113.194';
        $_SERVER['HTTP_CF_IPCOUNTRY'] = 'RO';
        $ctx = ApiBootstrap::clientContext([
            'trust_proxy_headers' => true,
            'trusted_proxies' => ['10.0.0.0/8'],
            'country_lookup_remote' => false,
        ]);
    } finally {
        $_SERVER = $backup;
    }
    if (($ctx['clientIp'] ?? '') !== '203.0.113.194') {
        throw new RuntimeException('clientIp mismatch');
    }
    if (($ctx['clientCountryCode'] ?? '') !== 'RO') {
        throw new RuntimeException('country mismatch');
    }
    if (!str_contains((string)($ctx['rateLimitIdentity'] ?? ''), '203.0.113.194|')) {
        throw new RuntimeException('identity format mismatch');
    }
    return (string)$ctx['rateLimitIdentity'];
});

$run('Security rate limit file backend blocks at limit', static function (): string {
    $root = dirname(__DIR__);
    $identity = 'unit-rl-' . bin2hex(random_bytes(6));
    $scope = 'unit_rl_scope_' . bin2hex(random_bytes(4));
    $path = $root . '/tmp/ratelimit_' . sha1($scope . '|' . substr($identity, 0, 200)) . '.json';
    @unlink($path);

    $cfg = [
        'transient_store' => 'file',
        'transient_store_file_fallback' => true,
    ];
    Security::applyRateLimitConfig($cfg, $scope, 2, 60, $identity);
    Security::applyRateLimitConfig($cfg, $scope, 2, 60, $identity);

    $blocked = false;
    try {
        Security::applyRateLimitConfig($cfg, $scope, 2, 60, $identity);
    } catch (HttpException $error) {
        if ($error->statusCode === 429) {
            $blocked = true;
        } else {
            throw $error;
        }
    } finally {
        @unlink($path);
    }
    if (!$blocked) {
        throw new RuntimeException('rate limit did not block at threshold');
    }
    return '429 ok';
});

$run('Security replay file backend rejects duplicate nonce', static function (): string {
    $root = dirname(__DIR__);
    $identity = 'unit-rp-' . bin2hex(random_bytes(6));
    $scope = 'unit_rp_scope_' . bin2hex(random_bytes(4));
    $path = $root . '/tmp/replay_' . sha1($scope . '|' . substr($identity, 0, 220)) . '.json';
    @unlink($path);

    $cfg = [
        'transient_store' => 'file',
        'transient_store_file_fallback' => true,
        'replay_window_sec' => 120,
        'replay_nonce_ttl_sec' => 300,
    ];
    $nonce = 'nonce_' . bin2hex(random_bytes(10));
    $ts = (string)time();

    Security::assertReplayProtection($cfg, $scope, $ts, $nonce, $identity);
    $blocked = false;
    try {
        Security::assertReplayProtection($cfg, $scope, $ts, $nonce, $identity);
    } catch (HttpException $error) {
        if ($error->statusCode === 409) {
            $blocked = true;
        } else {
            throw $error;
        }
    } finally {
        @unlink($path);
    }
    if (!$blocked) {
        throw new RuntimeException('duplicate nonce not blocked');
    }
    return '409 ok';
});

$run('Security rate limit DB backend blocks at limit', static function () use ($dbTestConfig, $buildDbConfig): string {
    if (!is_array($dbTestConfig)) {
        return 'skipped (BITAXE_TEST_DB_DSN not set)';
    }

    if (!in_array((string)$dbTestConfig['engine'], PDO::getAvailableDrivers(), true)) {
        return 'skipped (pdo driver not installed)';
    }

    $scope = 'unit_db_rl_' . bin2hex(random_bytes(4));
    $identity = 'unit-db-rl-' . bin2hex(random_bytes(6));

    $cfg = [
        'transient_store' => 'db',
        'transient_store_file_fallback' => false,
        'transient_store_table' => (string)$dbTestConfig['tables']['transient'],
        'db' => $buildDbConfig($dbTestConfig, (string)$dbTestConfig['tables']['transient']),
    ];

    Security::applyRateLimitConfig($cfg, $scope, 2, 60, $identity);
    Security::applyRateLimitConfig($cfg, $scope, 2, 60, $identity);

    $blocked = false;
    try {
        Security::applyRateLimitConfig($cfg, $scope, 2, 60, $identity);
    } catch (HttpException $error) {
        if ($error->statusCode === 429) {
            $blocked = true;
        } else {
            throw $error;
        }
    }

    if (!$blocked) {
        throw new RuntimeException('db rate limit did not block at threshold');
    }

    return '429 ok (db)';
});

$run('Security replay DB backend rejects duplicate nonce', static function () use ($dbTestConfig, $buildDbConfig): string {
    if (!is_array($dbTestConfig)) {
        return 'skipped (BITAXE_TEST_DB_DSN not set)';
    }

    if (!in_array((string)$dbTestConfig['engine'], PDO::getAvailableDrivers(), true)) {
        return 'skipped (pdo driver not installed)';
    }

    $scope = 'unit_db_rp_' . bin2hex(random_bytes(4));
    $identity = 'unit-db-rp-' . bin2hex(random_bytes(6));
    $nonce = 'nonce_' . bin2hex(random_bytes(10));
    $ts = (string)time();

    $cfg = [
        'transient_store' => 'db',
        'transient_store_file_fallback' => false,
        'transient_store_table' => (string)$dbTestConfig['tables']['transient'],
        'replay_window_sec' => 120,
        'replay_nonce_ttl_sec' => 300,
        'db' => $buildDbConfig($dbTestConfig, (string)$dbTestConfig['tables']['transient']),
    ];

    Security::assertReplayProtection($cfg, $scope, $ts, $nonce, $identity);

    $blocked = false;
    try {
        Security::assertReplayProtection($cfg, $scope, $ts, $nonce, $identity);
    } catch (HttpException $error) {
        if ($error->statusCode === 409) {
            $blocked = true;
        } else {
            throw $error;
        }
    }

    if (!$blocked) {
        throw new RuntimeException('db duplicate nonce not blocked');
    }

    return '409 ok (db)';
});

$run('ShareStore file dedupe + meta integrity', static function (): string {
    $tmpRel = 'tmp/unit_shares_' . bin2hex(random_bytes(4));
    $root = dirname(__DIR__);
    $tmpAbs = $root . '/' . $tmpRel;

    $cfg = [
        'enabled' => true,
        'driver' => 'file',
        'storage_dir' => $tmpRel,
        'token_bytes' => 12,
        'default_ttl_sec' => 3600,
        'max_ttl_sec' => 7200,
        'max_payload_bytes' => 1024 * 1024,
        'max_rows' => 2000,
        'max_shares' => 5000,
        'max_storage_bytes' => 128 * 1024 * 1024,
        'file_fallback_read' => true,
    ];

    $store = new ShareStore($cfg, $root);
    $payload = [
        'consolidatedData' => [[
            'source' => 'master',
            'v' => 1300,
            'f' => 800,
            'h' => 3000,
            'e' => 16.5,
            'err' => 0.12,
            'p' => 49.5,
            'score' => 94,
            'vr' => 64,
            't' => 57,
        ]],
        'meta' => ['appVersion' => 'v263'],
    ];

    try {
        $first = $store->createShare($payload, null);
        $token = (string)($first['token'] ?? '');
        if ($token === '') {
            throw new RuntimeException('missing token');
        }
        $meta = $store->getShareMeta($token);
        if (!is_array($meta) || trim((string)($meta['payloadSha1'] ?? '')) === '') {
            throw new RuntimeException('meta payload sha missing');
        }
        $second = $store->createShare($payload, null);
        if (empty($second['reused']) || (string)$second['token'] !== $token) {
            throw new RuntimeException('dedupe mismatch');
        }
    } finally {
        if (is_dir($tmpAbs)) {
            $it = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($tmpAbs, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($it as $entry) {
                /** @var SplFileInfo $entry */
                if ($entry->isDir()) {
                    @rmdir($entry->getPathname());
                } else {
                    @unlink($entry->getPathname());
                }
            }
            @rmdir($tmpAbs);
        }
    }

    return 'dedupe ok';
});

$run('Autotune import file mode one-time consume + filename hardening', static function (): string {
    $tmpRel = 'tmp/unit_import_tickets_' . bin2hex(random_bytes(4));
    $root = dirname(__DIR__);
    $tmpAbs = $root . '/' . $tmpRel;

    $cfg = [
        'enabled' => true,
        'driver' => 'file',
        'file_fallback_read' => true,
        'storage_dir' => $tmpRel,
        'id_bytes' => 12,
        'default_ttl_sec' => 600,
        'max_ttl_sec' => 3600,
        'max_csv_bytes' => 2 * 1024 * 1024,
        'max_filename_bytes' => 180,
        'max_source_bytes' => 48,
        'file_prune_probability' => 0,
    ];

    $store = new AutotuneImportStore($cfg, $root);
    try {
        $created = $store->create([
            'source' => 'axeos<script>alert(1)</script>',
            'filename' => '../../<img src=x onerror=alert(1)>.csv',
            'csv' => "voltage,frequency,hashrate,temp,vrTemp,errorRate\n1270,832,3426,58,69,0.4",
            'timestamp' => time(),
        ], 600, [
            'ip' => '127.0.0.1',
            'origin' => 'https://bitaxe.colortr.com',
            'userAgent' => 'unit-test',
        ]);

        $importId = (string)($created['id'] ?? '');
        if (!preg_match('/^[a-f0-9]{16,80}$/', $importId)) {
            throw new RuntimeException('invalid import id format');
        }

        $sanitizedFilename = (string)($created['filename'] ?? '');
        if ($sanitizedFilename === '' || str_contains($sanitizedFilename, '<') || str_contains($sanitizedFilename, '>')) {
            throw new RuntimeException('filename hardening failed');
        }

        $first = $store->consume($importId, [
            'ip' => '127.0.0.1',
            'origin' => 'https://oc.colortr.com',
            'userAgent' => 'unit-test-consume',
        ]);
        if (($first['state'] ?? '') !== 'ok') {
            throw new RuntimeException('first consume should be ok');
        }
        $firstCsv = (string)($first['record']['csv'] ?? '');
        if ($firstCsv === '' || !str_contains($firstCsv, 'voltage,frequency,hashrate')) {
            throw new RuntimeException('first consume csv missing');
        }

        $second = $store->consume($importId, [
            'ip' => '127.0.0.1',
            'origin' => 'https://oc.colortr.com',
            'userAgent' => 'unit-test-consume-2',
        ]);
        if (($second['state'] ?? '') !== 'consumed') {
            throw new RuntimeException('second consume should be consumed');
        }
        if ((string)($second['record']['csv'] ?? '') !== '') {
            throw new RuntimeException('second consume must not expose csv');
        }
    } finally {
        if (is_dir($tmpAbs)) {
            $it = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($tmpAbs, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($it as $entry) {
                /** @var SplFileInfo $entry */
                if ($entry->isDir()) {
                    @rmdir($entry->getPathname());
                } else {
                    @unlink($entry->getPathname());
                }
            }
            @rmdir($tmpAbs);
        }
    }

    return 'one-time + sanitize ok';
});

$run('ShareStore DB mode file fallback create/read/meta/dedupe', static function (): string {
    $tmpRel = 'tmp/unit_shares_db_fallback_' . bin2hex(random_bytes(4));
    $root = dirname(__DIR__);
    $tmpAbs = $root . '/' . $tmpRel;

    $cfg = [
        'enabled' => true,
        'driver' => 'db',
        'storage_dir' => $tmpRel,
        'file_fallback_read' => true,
        'token_bytes' => 12,
        'default_ttl_sec' => 3600,
        'max_ttl_sec' => 7200,
        'max_payload_bytes' => 1024 * 1024,
        'max_rows' => 2000,
        'max_shares' => 5000,
        'max_storage_bytes' => 128 * 1024 * 1024,
        'db' => [
            'engine' => 'mysql',
            'dsn' => 'mysql:host=127.0.0.1;port=65000;dbname=bitaxe_missing',
            'username' => 'missing_user',
            'password' => 'missing_pass',
            'table' => 'share_records_unit_missing',
            'charset' => 'utf8mb4',
        ],
    ];

    $store = new ShareStore($cfg, $root);
    $payload = [
        'consolidatedData' => [[
            'source' => 'master',
            'v' => 1270,
            'f' => 832.5,
            'h' => 3426,
            'e' => 16.11,
            'err' => 0.46,
            'p' => 55.19,
            'score' => 97,
            'vr' => 51.2,
            't' => 44.0,
        ]],
        'meta' => ['appVersion' => 'v-unit-fallback'],
    ];

    try {
        $first = $store->createShare($payload, null);
        $token = (string)($first['token'] ?? '');
        if ($token === '' || !preg_match('/^[a-f0-9]{24}$/', $token)) {
            throw new RuntimeException('fallback create token invalid');
        }
        if (!empty($first['reused'])) {
            throw new RuntimeException('first fallback create should not be reused');
        }

        $second = $store->createShare($payload, null);
        if (empty($second['reused']) || (string)$second['token'] !== $token) {
            throw new RuntimeException('fallback dedupe mismatch');
        }

        $record = $store->getShare($token);
        if (!is_array($record) || !is_array($record['payload']['consolidatedData'] ?? null)) {
            throw new RuntimeException('fallback getShare payload missing');
        }

        $meta = $store->getShareMeta($token);
        if (!is_array($meta) || trim((string)($meta['payloadSha1'] ?? '')) === '') {
            throw new RuntimeException('fallback meta payload sha missing');
        }

        $unknownToken = str_repeat('f', 24);
        if ($unknownToken === $token) {
            $unknownToken = str_repeat('e', 24);
        }
        if ($store->getShare($unknownToken) !== null) {
            throw new RuntimeException('fallback unknown getShare should be null');
        }
        if ($store->getShareMeta($unknownToken) !== null) {
            throw new RuntimeException('fallback unknown getShareMeta should be null');
        }
    } finally {
        if (is_dir($tmpAbs)) {
            $it = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($tmpAbs, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($it as $entry) {
                /** @var SplFileInfo $entry */
                if ($entry->isDir()) {
                    @rmdir($entry->getPathname());
                } else {
                    @unlink($entry->getPathname());
                }
            }
            @rmdir($tmpAbs);
        }
    }

    return 'fallback path ok';
});

$run('UsageLogger file append + summary', static function (): string {
    $root = dirname(__DIR__);
    $suffix = bin2hex(random_bytes(4));
    $fileRel = 'tmp/unit_usage_' . $suffix . '.ndjson';
    $cacheRel = 'tmp/unit_usage_summary_' . $suffix . '.json';

    $logger = new UsageLogger([
        'enabled' => true,
        'driver' => 'file',
        'file' => $fileRel,
        'archive_glob' => 'tmp/unit_usage_' . $suffix . '_*.ndjson*',
        'summary_cache_file' => $cacheRel,
        'visitor_salt' => 'unit-test-salt',
        'rotation' => 'size',
        'max_file_bytes' => 10 * 1024 * 1024,
        'max_archives' => 1,
    ], $root);

    $baseContext = [
        'app_version' => Version::appVersion(ApiBootstrap::loadConfig()),
        'client_ip' => '203.0.113.10',
        'country_code' => 'RO',
        'user_agent' => 'UnitAgent/1.0',
        'source_api' => 'unit',
        'request_status' => 'ok',
        'http_status' => 200,
        'analysis_ms' => 120,
        'selected_language' => 'en',
        'browser_language' => 'en-US',
        'selected_theme' => 'dark',
        'selected_theme_variant' => 'purple',
        'files_attempted' => 2,
        'files_processed' => 2,
        'bytes_attempted' => 1024,
        'bytes_processed' => 1000,
        'largest_upload_bytes' => 600,
        'total_rows' => 100,
        'parsed_rows' => 95,
        'skipped_rows' => 5,
        'merged_records' => 90,
    ];
    $logger->append($baseContext);
    $baseContext['request_status'] = 'error';
    $baseContext['http_status'] = 500;
    $baseContext['error_message'] = 'unit-error';
    $logger->append($baseContext);

    $latest = $logger->readLatest(10);
    if (count($latest) < 2) {
        throw new RuntimeException('latest entries missing');
    }
    $summary = $logger->summarize($latest);
    $runs = max(0, (int)($summary['totalRuns'] ?? 0));
    if ($runs < 2) {
        throw new RuntimeException('summary runs mismatch');
    }

    @unlink($root . '/' . $fileRel);
    @unlink($root . '/' . $cacheRel);

    return 'runs=' . (string)$runs;
});

$run('ShareStore DB dedupe + readback', static function () use ($dbTestConfig, $buildDbConfig): string {
    if (!is_array($dbTestConfig)) {
        return 'skipped (BITAXE_TEST_DB_DSN not set)';
    }

    if (!in_array((string)$dbTestConfig['engine'], PDO::getAvailableDrivers(), true)) {
        return 'skipped (pdo driver not installed)';
    }

    $cfg = [
        'enabled' => true,
        'driver' => 'db',
        'file_fallback_read' => false,
        'token_bytes' => 12,
        'default_ttl_sec' => 3600,
        'max_ttl_sec' => 7200,
        'max_payload_bytes' => 1024 * 1024,
        'max_rows' => 2000,
        'db' => $buildDbConfig($dbTestConfig, (string)$dbTestConfig['tables']['share']),
    ];

    $store = new ShareStore($cfg, dirname(__DIR__));
    $payload = [
        'consolidatedData' => [[
            'source' => 'master',
            'v' => 1300,
            'f' => 800,
            'h' => 3000,
            'e' => 16.5,
            'err' => 0.12,
            'p' => 49.5,
            'score' => 94,
            'vr' => 64,
            't' => 57,
        ]],
        'meta' => ['appVersion' => 'v-unit-db'],
    ];

    $first = $store->createShare($payload, null);
    $token = (string)($first['token'] ?? '');
    if ($token === '') {
        throw new RuntimeException('missing db token');
    }

    $record = $store->getShare($token);
    if (!is_array($record) || !is_array($record['payload']['consolidatedData'] ?? null)) {
        throw new RuntimeException('db getShare payload missing');
    }

    $second = $store->createShare($payload, null);
    if (empty($second['reused']) || (string)$second['token'] !== $token) {
        throw new RuntimeException('db dedupe mismatch');
    }

    return 'db token=' . $token;
});

$run('UsageLogger DB append + summary', static function () use ($dbTestConfig, $buildDbConfig, $quoteTable): string {
    if (!is_array($dbTestConfig)) {
        return 'skipped (BITAXE_TEST_DB_DSN not set)';
    }

    if (!in_array((string)$dbTestConfig['engine'], PDO::getAvailableDrivers(), true)) {
        return 'skipped (pdo driver not installed)';
    }

    $root = dirname(__DIR__);
    $cacheRel = 'tmp/unit_usage_db_summary_' . bin2hex(random_bytes(4)) . '.json';
    $table = (string)$dbTestConfig['tables']['usage'];
    $engine = (string)$dbTestConfig['engine'];
    $pdo = new PDO(
        (string)$dbTestConfig['dsn'],
        (string)$dbTestConfig['username'],
        (string)$dbTestConfig['password'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
    try {
        $pdo->exec('DELETE FROM ' . $quoteTable($engine, $table));
    } catch (Throwable) {
        // Table may not exist yet on first run; schema is created by logger.
    }

    $logger = new UsageLogger([
        'enabled' => true,
        'driver' => 'db',
        'file_fallback_read' => false,
        'summary_cache_file' => $cacheRel,
        'visitor_salt' => 'unit-test-db-salt',
        'db' => $buildDbConfig($dbTestConfig, $table),
    ], $root);

    $baseContext = [
        'app_version' => 'v-unit-db',
        'client_ip' => '203.0.113.10',
        'country_code' => 'RO',
        'user_agent' => 'UnitAgentDb/1.0',
        'source_api' => 'unit-db',
        'request_status' => 'ok',
        'http_status' => 200,
        'analysis_ms' => 150,
        'selected_language' => 'en',
        'browser_language' => 'en-US',
        'selected_theme' => 'dark',
        'selected_theme_variant' => 'purple',
        'files_attempted' => 2,
        'files_processed' => 2,
        'bytes_attempted' => 1024,
        'bytes_processed' => 1000,
        'largest_upload_bytes' => 600,
        'total_rows' => 100,
        'parsed_rows' => 95,
        'skipped_rows' => 5,
        'merged_records' => 90,
    ];
    $logger->append($baseContext);
    $baseContext['request_status'] = 'error';
    $baseContext['http_status'] = 500;
    $baseContext['error_message'] = 'unit-db-error';
    $logger->append($baseContext);

    $latest = $logger->readLatest(10);
    if (count($latest) < 2) {
        throw new RuntimeException('db latest entries missing');
    }

    $summary = $logger->summarizeAll();
    $runs = max(0, (int)($summary['totalRuns'] ?? 0));
    if ($runs < 2) {
        throw new RuntimeException('db summary runs mismatch');
    }

    @unlink($root . '/' . $cacheRel);
    return 'db runs=' . (string)$runs;
});

$pass = 0;
$fail = 0;
foreach ($results as $r) {
    if (!empty($r['ok'])) {
        $pass++;
        echo '[PASS] ' . $r['name'] . ' (' . $r['ms'] . "ms) -> " . $r['detail'] . PHP_EOL;
    } else {
        $fail++;
        echo '[FAIL] ' . $r['name'] . ' (' . $r['ms'] . "ms) -> " . $r['detail'] . PHP_EOL;
    }
}
echo 'Summary: PASS ' . $pass . '/' . count($results) . ', FAIL ' . $fail . '/' . count($results) . PHP_EOL;

exit($fail > 0 ? 1 : 0);
