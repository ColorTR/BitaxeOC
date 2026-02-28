<?php

declare(strict_types=1);

use BitaxeOc\App\Analyzer;

require_once __DIR__ . '/../app/Analyzer.php';

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

$fixturesDir = __DIR__ . '/fixtures/analyzer';
$parseCsvFile = new ReflectionMethod(Analyzer::class, 'parseCsvFile');

$run('Analyzer fixture: basic dataset parses expected rows', static function () use ($fixturesDir, $parseCsvFile): string {
    $analyzer = new Analyzer([
        'csv_max_data_rows' => 7000,
        'csv_parse_time_budget_ms' => 8000,
    ]);
    $fixture = $fixturesDir . '/01_basic.csv';
    if (!is_file($fixture)) {
        throw new RuntimeException('fixture missing: 01_basic.csv');
    }

    $parsed = $parseCsvFile->invoke($analyzer, $fixture);
    $stats = is_array($parsed['stats'] ?? null) ? $parsed['stats'] : [];
    $rows = is_array($parsed['data'] ?? null) ? $parsed['data'] : [];

    if (count($rows) !== 3) {
        throw new RuntimeException('expected 3 parsed rows');
    }
    if ((int)($stats['parsedRows'] ?? 0) !== 3) {
        throw new RuntimeException('stats.parsedRows mismatch');
    }
    if (!empty($stats['missingRequiredColumns'])) {
        throw new RuntimeException('unexpected missingRequiredColumns');
    }

    return 'rows=3';
});

$run('Analyzer fixture: derive hash + temp->vr fallback', static function () use ($fixturesDir, $parseCsvFile): string {
    $analyzer = new Analyzer([
        'csv_max_data_rows' => 7000,
        'csv_parse_time_budget_ms' => 8000,
    ]);
    $fixture = $fixturesDir . '/02_derive_hash_temp_to_vr.csv';
    if (!is_file($fixture)) {
        throw new RuntimeException('fixture missing: 02_derive_hash_temp_to_vr.csv');
    }

    $parsed = $parseCsvFile->invoke($analyzer, $fixture);
    $stats = is_array($parsed['stats'] ?? null) ? $parsed['stats'] : [];
    $rows = is_array($parsed['data'] ?? null) ? $parsed['data'] : [];
    if (count($rows) !== 2) {
        throw new RuntimeException('expected 2 parsed rows');
    }

    if ((int)($stats['derivedHashRows'] ?? 0) !== 2) {
        throw new RuntimeException('expected derivedHashRows=2');
    }
    if (empty($stats['usedTempAsVr'])) {
        throw new RuntimeException('expected usedTempAsVr=true');
    }
    if (abs(((float)($rows[0]['h'] ?? 0.0)) - 3000.0) > 0.01) {
        throw new RuntimeException('unexpected derived hash in row0');
    }

    return 'derivedHashRows=2';
});

$run('Analyzer fixture: unit conversion + derived power', static function () use ($fixturesDir, $parseCsvFile): string {
    $analyzer = new Analyzer([
        'csv_max_data_rows' => 7000,
        'csv_parse_time_budget_ms' => 8000,
    ]);
    $fixture = $fixturesDir . '/03_unit_conversion_and_power_derive.csv';
    if (!is_file($fixture)) {
        throw new RuntimeException('fixture missing: 03_unit_conversion_and_power_derive.csv');
    }

    $parsed = $parseCsvFile->invoke($analyzer, $fixture);
    $stats = is_array($parsed['stats'] ?? null) ? $parsed['stats'] : [];
    $rows = is_array($parsed['data'] ?? null) ? $parsed['data'] : [];
    if (count($rows) !== 2) {
        throw new RuntimeException('expected 2 parsed rows');
    }

    if ((int)($stats['derivedPowerRows'] ?? 0) !== 2) {
        throw new RuntimeException('expected derivedPowerRows=2');
    }
    if (abs(((float)($rows[0]['h'] ?? 0.0)) - 3500.0) > 0.01) {
        throw new RuntimeException('hash conversion TH/s -> GH/s failed');
    }
    if (abs(((float)($rows[0]['e'] ?? 0.0)) - 17.0) > 0.01) {
        throw new RuntimeException('eff conversion J/GH -> J/TH failed');
    }

    return 'power-derive ok';
});

$run('Analyzer fixture: truncation limit is enforced', static function () use ($fixturesDir, $parseCsvFile): string {
    $analyzer = new Analyzer([
        'csv_max_data_rows' => 1,
        'csv_parse_time_budget_ms' => 8000,
    ]);
    $fixture = $fixturesDir . '/01_basic.csv';
    $parsed = $parseCsvFile->invoke($analyzer, $fixture);
    $stats = is_array($parsed['stats'] ?? null) ? $parsed['stats'] : [];
    $rows = is_array($parsed['data'] ?? null) ? $parsed['data'] : [];

    if (count($rows) !== 1) {
        throw new RuntimeException('expected parsed rows capped at 1');
    }
    if ((int)($stats['truncatedRows'] ?? 0) !== 2) {
        throw new RuntimeException('expected truncatedRows=2');
    }

    return 'truncated=2';
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
