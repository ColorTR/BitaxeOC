<?php

declare(strict_types=1);

namespace BitaxeOc\App;

final class OpsProcessMetrics
{
    /**
     * @return array{capturedAt:string,topProcesses:array<int,array<string,mixed>>}
     */
    public static function collectTopProcessMetrics(int $limit = 12, int $scanRows = 220): array
    {
        $limit = max(3, min(30, $limit));
        $scanRows = max($limit, min(600, $scanRows));

        $result = [
            'capturedAt' => gmdate('c'),
            'topProcesses' => [],
        ];

        if (!function_exists('exec')) {
            return $result;
        }

        $command = sprintf(
            'LC_ALL=C ps -eo pid=,user=,comm=,pcpu=,pmem=,rss=,etime= --sort=-pcpu,-pmem,-rss | head -n %d 2>/dev/null',
            $scanRows
        );
        $lines = [];
        $exitCode = 1;
        @exec($command, $lines, $exitCode);
        if ($exitCode !== 0 || !is_array($lines) || count($lines) < 1) {
            return $result;
        }

        $parsed = [];
        $parsedAll = [];
        foreach ($lines as $line) {
            if (!is_string($line) || trim($line) === '') {
                continue;
            }
            $parts = preg_split('/\s+/', trim($line), 7);
            if (!is_array($parts) || count($parts) < 7) {
                continue;
            }

            $pid = (int)($parts[0] ?? 0);
            $user = trim((string)($parts[1] ?? ''));
            $comm = trim((string)($parts[2] ?? ''));
            $cpuRaw = str_replace(',', '.', trim((string)($parts[3] ?? '')));
            $memRaw = str_replace(',', '.', trim((string)($parts[4] ?? '')));
            $cpuPct = is_numeric($cpuRaw) ? max(0.0, (float)$cpuRaw) : 0.0;
            $memPct = is_numeric($memRaw) ? max(0.0, (float)$memRaw) : 0.0;
            $rssKb = is_numeric($parts[5] ?? null) ? max(0, (int)$parts[5]) : 0;
            $etime = trim((string)($parts[6] ?? ''));

            if ($pid <= 0 || $comm === '') {
                continue;
            }

            $row = [
                'pid' => $pid,
                'user' => $user !== '' ? $user : '-',
                'command' => $comm,
                'cpuPct' => $cpuPct,
                'memPct' => $memPct,
                'rssBytes' => max(0, $rssKb * 1024),
                'elapsed' => $etime !== '' ? $etime : '-',
            ];
            $parsedAll[] = $row;
            if ($cpuPct > 0.0 || $memPct > 0.0 || $row['rssBytes'] > 0) {
                $parsed[] = $row;
            }
        }

        if ($parsed === [] && $parsedAll !== []) {
            $parsed = $parsedAll;
        }

        if ($parsed === []) {
            return $result;
        }

        usort($parsed, static function (array $a, array $b): int {
            $cpuCmp = (float)($b['cpuPct'] ?? 0.0) <=> (float)($a['cpuPct'] ?? 0.0);
            if ($cpuCmp !== 0) {
                return $cpuCmp;
            }
            $memCmp = (float)($b['memPct'] ?? 0.0) <=> (float)($a['memPct'] ?? 0.0);
            if ($memCmp !== 0) {
                return $memCmp;
            }
            return (int)($b['rssBytes'] ?? 0) <=> (int)($a['rssBytes'] ?? 0);
        });

        $result['topProcesses'] = array_slice($parsed, 0, $limit);

        return $result;
    }

    /**
     * @return array{capturedAt:string,topProcesses:array<int,array<string,mixed>>}
     */
    public static function collectTopProcessMetricsCached(array $config): array
    {
        $adminConfig = is_array(($config['admin'] ?? null)) ? $config['admin'] : [];
        $limit = max(3, min(30, (int)($adminConfig['server_status_process_limit'] ?? 12)));
        $refreshSec = max(1.0, min(60.0, (float)($adminConfig['server_status_process_refresh_sec'] ?? 2.0)));
        $cachePath = dirname(__DIR__) . '/tmp/ops_process_cache.json';
        $now = microtime(true);

        if (is_readable($cachePath)) {
            $raw = @file_get_contents($cachePath);
            if (is_string($raw) && $raw !== '') {
                $cached = json_decode($raw, true);
                if (is_array($cached)) {
                    $cachedTs = is_numeric($cached['ts'] ?? null) ? (float)$cached['ts'] : 0.0;
                    $cachedLimit = is_numeric($cached['limit'] ?? null) ? (int)$cached['limit'] : 0;
                    $cachedData = is_array($cached['data'] ?? null) ? $cached['data'] : [];
                    if (
                        $cachedTs > 0.0 &&
                        $cachedLimit === $limit &&
                        ($now - $cachedTs) <= $refreshSec &&
                        $cachedData !== []
                    ) {
                        return $cachedData;
                    }
                }
            }
        }

        $fresh = self::collectTopProcessMetrics($limit, max(120, $limit * 20));
        $payload = [
            'ts' => $now,
            'limit' => $limit,
            'data' => $fresh,
        ];
        $encoded = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (is_string($encoded) && $encoded !== '') {
            $written = @file_put_contents($cachePath, $encoded, LOCK_EX);
            if ($written === false) {
                error_log('[bitaxe-oc] ops-panel: process cache write failed');
            }
        }

        return $fresh;
    }
}
