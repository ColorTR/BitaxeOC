<?php

declare(strict_types=1);

namespace BitaxeOc\App;

use PDO;
use Throwable;

final class OpsDbMetrics
{
    public static function normalizeTableName(string $value, string $fallback): string
    {
        $normalized = strtolower(trim($value));
        if (preg_match('/^[a-z0-9_]{1,64}$/', $normalized) === 1) {
            return $normalized;
        }

        return $fallback;
    }

    public static function buildCandidates(array $config): array
    {
        $sharing = is_array($config['sharing'] ?? null) ? $config['sharing'] : [];
        $logging = is_array($config['logging'] ?? null) ? $config['logging'] : [];

        $sharingDb = is_array($sharing['db'] ?? null) ? $sharing['db'] : [];
        $loggingDb = is_array($logging['db'] ?? null) ? $logging['db'] : [];

        $candidateSources = [
            [
                'db' => $sharingDb,
                'usageTable' => self::normalizeTableName((string)($loggingDb['table'] ?? 'usage_events'), 'usage_events'),
                'shareTable' => self::normalizeTableName((string)($sharingDb['table'] ?? 'share_records'), 'share_records'),
            ],
            [
                'db' => $loggingDb,
                'usageTable' => self::normalizeTableName((string)($loggingDb['table'] ?? 'usage_events'), 'usage_events'),
                'shareTable' => self::normalizeTableName((string)($sharingDb['table'] ?? 'share_records'), 'share_records'),
            ],
        ];

        $candidates = [];
        $seen = [];
        foreach ($candidateSources as $source) {
            $db = is_array($source['db'] ?? null) ? $source['db'] : [];
            $engine = strtolower(trim((string)($db['engine'] ?? 'mysql')));
            if (!in_array($engine, ['mysql', 'pgsql'], true)) {
                $engine = 'mysql';
            }
            $database = trim((string)($db['database'] ?? ''));
            $dsn = trim((string)($db['dsn'] ?? ''));
            $usageTable = (string)$source['usageTable'];
            $shareTable = (string)$source['shareTable'];

            if ($dsn === '') {
                if ($database === '') {
                    continue;
                }
                $host = trim((string)($db['host'] ?? 'localhost'));
                $port = (int)($db['port'] ?? ($engine === 'pgsql' ? 5432 : 3306));
                if ($engine === 'pgsql') {
                    $dsn = sprintf('pgsql:host=%s;port=%d;dbname=%s', $host, $port, $database);
                } else {
                    $charset = trim((string)($db['charset'] ?? 'utf8mb4'));
                    if ($charset === '') {
                        $charset = 'utf8mb4';
                    }
                    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $host, $port, $database, $charset);
                }
            }

            $username = (string)($db['username'] ?? '');
            $password = (string)($db['password'] ?? '');

            $key = $engine . '|' . $dsn . '|' . $username . '|u:' . $usageTable . '|s:' . $shareTable;
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            $candidates[] = [
                'engine' => $engine,
                'dsn' => $dsn,
                'username' => $username,
                'password' => $password,
                'defaultDatabase' => $database,
                'usageTable' => $usageTable,
                'shareTable' => $shareTable,
            ];
        }

        return $candidates;
    }

    public static function createConnectionMeta(array $config): ?array
    {
        $candidates = self::buildCandidates($config);
        foreach ($candidates as $candidate) {
            try {
                $pdo = new PDO(
                    (string)$candidate['dsn'],
                    (string)$candidate['username'],
                    (string)$candidate['password'],
                    [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES => false,
                    ]
                );
                return [
                    'pdo' => $pdo,
                    'engine' => (string)$candidate['engine'],
                    'defaultDatabase' => (string)$candidate['defaultDatabase'],
                    'usageTable' => (string)$candidate['usageTable'],
                    'shareTable' => (string)$candidate['shareTable'],
                ];
            } catch (Throwable) {
                continue;
            }
        }

        return null;
    }

    public static function queryMysql(PDO $pdo, string $schema, string $usageTable, string $shareTable): array
    {
        if ($schema === '') {
            return [
                'dbTotalBytes' => 0,
                'dbUsageEventsBytes' => 0,
                'dbShareRecordsBytes' => 0,
                'dbUsageEventsRows' => 0,
                'dbShareRecordsRows' => 0,
            ];
        }

        $metrics = [
            'dbTotalBytes' => 0,
            'dbUsageEventsBytes' => 0,
            'dbShareRecordsBytes' => 0,
            'dbUsageEventsRows' => 0,
            'dbShareRecordsRows' => 0,
        ];

        $tables = array_values(array_unique([$usageTable, $shareTable]));
        try {
            $stmtTotal = $pdo->prepare(
                'SELECT COALESCE(SUM(data_length + index_length), 0) AS bytes FROM information_schema.tables WHERE table_schema = :schema'
            );
            $stmtTotal->execute([':schema' => $schema]);
            $totalRow = $stmtTotal->fetch();
            $metrics['dbTotalBytes'] = max(0, (int)($totalRow['bytes'] ?? 0));

            if ($tables !== []) {
                $placeholders = implode(',', array_fill(0, count($tables), '?'));
                $sql = 'SELECT table_name, COALESCE(data_length + index_length, 0) AS bytes, COALESCE(table_rows, 0) AS rows_est FROM information_schema.tables WHERE table_schema = ? AND table_name IN (' . $placeholders . ')';
                $stmtTables = $pdo->prepare($sql);
                $stmtTables->execute(array_merge([$schema], $tables));
                $tableRows = $stmtTables->fetchAll();

                foreach ($tableRows as $tableRow) {
                    if (!is_array($tableRow)) {
                        continue;
                    }
                    $tableName = strtolower(trim((string)($tableRow['table_name'] ?? '')));
                    $bytes = max(0, (int)($tableRow['bytes'] ?? 0));
                    $rowsEst = max(0, (int)($tableRow['rows_est'] ?? 0));
                    if ($tableName === $usageTable) {
                        $metrics['dbUsageEventsBytes'] = $bytes;
                        $metrics['dbUsageEventsRows'] = $rowsEst;
                    } elseif ($tableName === $shareTable) {
                        $metrics['dbShareRecordsBytes'] = $bytes;
                        $metrics['dbShareRecordsRows'] = $rowsEst;
                    }
                }
            }
        } catch (Throwable) {
            // Fallback below handles limited information_schema visibility.
        }

        $needsFallback = (
            $metrics['dbTotalBytes'] <= 0
            || ($metrics['dbUsageEventsBytes'] <= 0 && $metrics['dbShareRecordsBytes'] <= 0)
        );

        if ($needsFallback) {
            $schemaSql = '`' . str_replace('`', '``', $schema) . '`';
            $allStatusRows = [];
            try {
                $stmtAll = $pdo->query('SHOW TABLE STATUS FROM ' . $schemaSql);
                $rowsRaw = $stmtAll ? $stmtAll->fetchAll() : [];
                $allStatusRows = is_array($rowsRaw) ? $rowsRaw : [];
            } catch (Throwable) {
                $allStatusRows = [];
            }

            if ($allStatusRows !== []) {
                $totalBytes = 0;
                foreach ($allStatusRows as $row) {
                    if (!is_array($row)) {
                        continue;
                    }
                    $name = strtolower(trim((string)($row['Name'] ?? '')));
                    $bytes = max(0, (int)($row['Data_length'] ?? 0)) + max(0, (int)($row['Index_length'] ?? 0));
                    $rowsEst = max(0, (int)($row['Rows'] ?? 0));
                    $totalBytes += $bytes;
                    if ($name === $usageTable) {
                        $metrics['dbUsageEventsBytes'] = max($metrics['dbUsageEventsBytes'], $bytes);
                        $metrics['dbUsageEventsRows'] = max($metrics['dbUsageEventsRows'], $rowsEst);
                    } elseif ($name === $shareTable) {
                        $metrics['dbShareRecordsBytes'] = max($metrics['dbShareRecordsBytes'], $bytes);
                        $metrics['dbShareRecordsRows'] = max($metrics['dbShareRecordsRows'], $rowsEst);
                    }
                }
                if ($metrics['dbTotalBytes'] <= 0) {
                    $metrics['dbTotalBytes'] = max(0, $totalBytes);
                }
            }
        }

        foreach ($tables as $tableName) {
            if (!preg_match('/^[a-z0-9_]{1,64}$/', $tableName)) {
                continue;
            }
            $needRowCount = (
                ($tableName === $usageTable && $metrics['dbUsageEventsRows'] <= 0)
                || ($tableName === $shareTable && $metrics['dbShareRecordsRows'] <= 0)
            );
            if (!$needRowCount) {
                continue;
            }
            $tableSql = '`' . str_replace('`', '``', $tableName) . '`';
            try {
                $count = (int)($pdo->query('SELECT COUNT(*) FROM ' . $tableSql)->fetchColumn() ?: 0);
                if ($tableName === $usageTable) {
                    $metrics['dbUsageEventsRows'] = max(0, $count);
                } elseif ($tableName === $shareTable) {
                    $metrics['dbShareRecordsRows'] = max(0, $count);
                }
            } catch (Throwable) {
                // Leave estimated row count as-is.
            }
        }

        return $metrics;
    }

    public static function queryPgsql(PDO $pdo, string $schema, string $usageTable, string $shareTable): array
    {
        $schemaName = trim($schema) !== '' ? trim($schema) : 'public';
        $metrics = [
            'dbTotalBytes' => 0,
            'dbUsageEventsBytes' => 0,
            'dbShareRecordsBytes' => 0,
            'dbUsageEventsRows' => 0,
            'dbShareRecordsRows' => 0,
        ];

        $stmtTotal = $pdo->prepare(
            'SELECT COALESCE(SUM(pg_total_relation_size(quote_ident(schemaname) || \'.\' || quote_ident(tablename))), 0) AS bytes FROM pg_tables WHERE schemaname = :schema'
        );
        $stmtTotal->execute([':schema' => $schemaName]);
        $totalRow = $stmtTotal->fetch();
        $metrics['dbTotalBytes'] = max(0, (int)($totalRow['bytes'] ?? 0));

        $tables = array_values(array_unique([$usageTable, $shareTable]));
        if ($tables !== []) {
            $placeholders = implode(',', array_fill(0, count($tables), '?'));
            $sql = 'SELECT relname AS table_name, COALESCE(pg_total_relation_size(relid), 0) AS bytes, COALESCE(n_live_tup, 0) AS rows_est FROM pg_stat_user_tables WHERE schemaname = ? AND relname IN (' . $placeholders . ')';
            $stmtTables = $pdo->prepare($sql);
            $stmtTables->execute(array_merge([$schemaName], $tables));
            $tableRows = $stmtTables->fetchAll();

            foreach ($tableRows as $tableRow) {
                if (!is_array($tableRow)) {
                    continue;
                }
                $tableName = strtolower(trim((string)($tableRow['table_name'] ?? '')));
                $bytes = max(0, (int)($tableRow['bytes'] ?? 0));
                $rowsEst = max(0, (int)round((float)($tableRow['rows_est'] ?? 0)));
                if ($tableName === $usageTable) {
                    $metrics['dbUsageEventsBytes'] = $bytes;
                    $metrics['dbUsageEventsRows'] = $rowsEst;
                } elseif ($tableName === $shareTable) {
                    $metrics['dbShareRecordsBytes'] = $bytes;
                    $metrics['dbShareRecordsRows'] = $rowsEst;
                }
            }
        }

        return $metrics;
    }
}
