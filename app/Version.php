<?php

declare(strict_types=1);

namespace BitaxeOc\App;

final class Version
{
    private function __construct()
    {
    }

    public static function appVersion(array $config = []): string
    {
        $raw = trim((string)($config['app_version'] ?? ''));
        if (preg_match('/^v[0-9]+$/', $raw) === 1) {
            return $raw;
        }

        if (preg_match('/^v([0-9]+)/', $raw, $match) === 1) {
            return 'v' . (string)((int)$match[1]);
        }

        return 'v0';
    }

    public static function numeric(array $config = []): int
    {
        $version = self::appVersion($config);
        return max(0, (int)substr($version, 1));
    }

    public static function display(array $config = []): string
    {
        $n = self::numeric($config);
        return sprintf('v%d.%d', intdiv($n, 10), $n % 10);
    }

    public static function brandLine(array $config = []): string
    {
        return self::display($config) . ' | CHART.JS';
    }
}

