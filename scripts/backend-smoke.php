<?php

declare(strict_types=1);

use BitaxeOc\App\ApiBootstrap;
use BitaxeOc\App\Security;
use BitaxeOc\App\ShareStore;
use BitaxeOc\App\Version;

require_once __DIR__ . '/../app/ApiBootstrap.php';
require_once __DIR__ . '/../app/Security.php';
require_once __DIR__ . '/../app/ShareStore.php';
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

$run('Version parser/display', static function (): string {
    $cfg = ApiBootstrap::loadConfig();
    $raw = Version::appVersion($cfg);
    $display = Version::display($cfg);
    if (!preg_match('/^v([0-9]+)$/', $raw, $m)) {
        throw new RuntimeException('appVersion format mismatch');
    }
    $n = (int)$m[1];
    $expectedDisplay = sprintf('v%d.%d', intdiv($n, 10), ($n % 10));
    if ($display !== $expectedDisplay) {
        throw new RuntimeException('displayVersion mismatch');
    }
    return $raw . ' / ' . $display;
});

$run('Security country header mapping', static function (): string {
    $backup = $_SERVER;
    try {
        $_SERVER['HTTP_CF_IPCOUNTRY'] = 'RO';
        $_SERVER['REMOTE_ADDR'] = '10.0.0.55';
        $code = Security::detectCountryCode(true, [
            'country_lookup_remote' => false,
            'trusted_proxies' => ['10.0.0.0/8'],
        ]);
    } finally {
        $_SERVER = $backup;
    }

    if ($code !== 'RO') {
        throw new RuntimeException('expected RO, got ' . $code);
    }
    return $code;
});

$run('ShareStore file-mode meta + dedupe', static function (): string {
    $tmpRel = 'tmp/smoke_shares_' . bin2hex(random_bytes(4));
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
        'meta' => ['appVersion' => 'v262'],
    ];

    try {
        $first = $store->createShare($payload, null);
        if (empty($first['token'])) {
            throw new RuntimeException('missing token');
        }

        $meta = $store->getShareMeta((string)$first['token']);
        if (!is_array($meta) || trim((string)($meta['payloadSha1'] ?? '')) === '') {
            throw new RuntimeException('meta payloadSha1 missing');
        }

        $record = $store->getShare((string)$first['token']);
        if (!is_array($record) || !is_array($record['payload']['consolidatedData'] ?? null)) {
            throw new RuntimeException('share payload missing');
        }
        if (count($record['payload']['consolidatedData']) !== 1) {
            throw new RuntimeException('unexpected row count');
        }

        $second = $store->createShare($payload, null);
        if (empty($second['reused']) || (string)$second['token'] !== (string)$first['token']) {
            throw new RuntimeException('dedupe token reuse failed');
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

    return 'token=' . (string)$first['token'];
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
