<?php

declare(strict_types=1);

namespace BitaxeOc\App;

use PDO;
use PDOException;
use Throwable;

final class SecurityTransientStore
{
    private static ?PDO $transientPdo = null;
    private static string $transientPdoDsn = '';
    private static string $transientTable = 'security_events';
    private static bool $transientSchemaReady = false;

    public static function assertReplayProtection(
        array $securityConfig,
        string $scope,
        string $incomingTimestamp,
        string $incomingNonce,
        ?string $clientIdentity,
        callable $defaultIdentityResolver
    ): void {
        $scopeName = trim($scope) !== '' ? trim($scope) : 'default';
        $windowSec = max(5, (int)($securityConfig['replay_window_sec'] ?? 180));
        $nonceTtlSec = max($windowSec, (int)($securityConfig['replay_nonce_ttl_sec'] ?? 900));

        $timestampRaw = trim($incomingTimestamp);
        if ($timestampRaw === '' || !preg_match('/^[0-9]{10}$/', $timestampRaw)) {
            throw new HttpException('Gecersiz istek zamani.', 400);
        }

        $requestTs = (int)$timestampRaw;
        $now = time();
        if (abs($now - $requestTs) > $windowSec) {
            throw new HttpException('Istek zamani asimina ugradi.', 408);
        }

        $nonce = trim($incomingNonce);
        if (
            $nonce === '' ||
            strlen($nonce) < 16 ||
            strlen($nonce) > 128 ||
            !preg_match('/^[A-Za-z0-9_.:\-]+$/', $nonce)
        ) {
            throw new HttpException('Gecersiz istek nonce degeri.', 400);
        }

        $identity = trim((string)$clientIdentity);
        if ($identity === '') {
            $identity = trim((string)$defaultIdentityResolver());
        }
        if ($identity === '') {
            $identity = '0.0.0.0';
        }

        if (self::transientStoreMode($securityConfig) === 'db') {
            try {
                self::assertReplayProtectionDb(
                    $securityConfig,
                    $scopeName,
                    substr($identity, 0, 220),
                    $requestTs,
                    $nonce,
                    $nonceTtlSec
                );
                return;
            } catch (HttpException $error) {
                if (!self::shouldTransientFallbackToFile($securityConfig)) {
                    throw $error;
                }
                self::logTransientStoreFailure('replay-db-http', $error);
            } catch (Throwable $error) {
                if (!self::shouldTransientFallbackToFile($securityConfig)) {
                    throw new HttpException('Replay korumasi baslatilamadi.', 503);
                }
                self::logTransientStoreFailure('replay-db-fallback', $error);
            }
        }

        self::assertReplayProtectionFile($scopeName, $identity, $requestTs, $nonce, $nonceTtlSec, $now);
    }

    public static function applyRateLimitConfig(
        array $securityConfig,
        string $scope,
        int $limit,
        int $windowSec,
        ?string $clientIdentity,
        callable $defaultIdentityResolver
    ): void {
        if ($limit <= 0 || $windowSec <= 0) {
            return;
        }

        $scopeName = trim($scope) !== '' ? trim($scope) : 'default';
        $identity = trim((string)$clientIdentity);
        if ($identity === '') {
            $identity = trim((string)$defaultIdentityResolver());
        }
        if ($identity === '') {
            $identity = '0.0.0.0';
        }

        if (self::transientStoreMode($securityConfig) === 'db') {
            try {
                self::applyRateLimitDb($securityConfig, $scopeName, $limit, $windowSec, substr($identity, 0, 200));
                return;
            } catch (HttpException $error) {
                if (!self::shouldTransientFallbackToFile($securityConfig)) {
                    throw $error;
                }
                self::logTransientStoreFailure('ratelimit-db-http', $error);
            } catch (Throwable $error) {
                if (!self::shouldTransientFallbackToFile($securityConfig)) {
                    throw new HttpException('Rate limit kontrolu baslatilamadi.', 503);
                }
                self::logTransientStoreFailure('ratelimit-db-fallback', $error);
            }
        }

        self::applyRateLimitFile($scopeName, $limit, $windowSec, $identity, dirname(__DIR__));
    }

    public static function applyRateLimitLegacyFile(
        string $scope,
        int $limit,
        int $windowSec,
        ?string $clientIdentity,
        callable $defaultIdentityResolver
    ): void {
        if ($limit <= 0 || $windowSec <= 0) {
            return;
        }

        $scopeName = trim($scope) !== '' ? trim($scope) : 'default';
        $identity = trim((string)$clientIdentity);
        if ($identity === '') {
            $identity = trim((string)$defaultIdentityResolver());
        }
        if ($identity === '') {
            $identity = '0.0.0.0';
        }

        self::applyRateLimitFile($scopeName, $limit, $windowSec, $identity, dirname(__DIR__));
    }

    private static function applyRateLimitFile(
        string $scopeName,
        int $limit,
        int $windowSec,
        string $identity,
        string $root
    ): void {
        $identity = substr($identity, 0, 200);
        $key = sha1($scopeName . '|' . $identity);
        $path = $root . '/tmp/ratelimit_' . $key . '.json';
        $now = time();
        $windowStart = $now - $windowSec;

        $fp = @fopen($path, 'c+');
        if ($fp === false) {
            error_log('[bitaxe-oc] ratelimit storage open failed: ' . $path);
            return;
        }

        try {
            if (!flock($fp, LOCK_EX)) {
                error_log('[bitaxe-oc] ratelimit lock failed: ' . $path);
                return;
            }

            rewind($fp);
            $raw = stream_get_contents($fp);
            $timestamps = [];
            if (is_string($raw) && trim($raw) !== '') {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    foreach ($decoded as $item) {
                        $ts = (int)$item;
                        if ($ts > $windowStart) {
                            $timestamps[] = $ts;
                        }
                    }
                }
            }

            if (count($timestamps) >= $limit) {
                throw new HttpException('Cok fazla istek. Lutfen biraz sonra tekrar deneyin.', 429);
            }

            $timestamps[] = $now;
            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, (string)json_encode($timestamps, JSON_UNESCAPED_SLASHES));
            fflush($fp);
            @chmod($path, 0600);
        } finally {
            flock($fp, LOCK_UN);
            fclose($fp);
        }
    }

    private static function transientStoreMode(array $securityConfig): string
    {
        $mode = strtolower(trim((string)($securityConfig['transient_store'] ?? 'file')));
        return $mode === 'db' ? 'db' : 'file';
    }

    private static function shouldTransientFallbackToFile(array $securityConfig): bool
    {
        return !array_key_exists('transient_store_file_fallback', $securityConfig)
            || !empty($securityConfig['transient_store_file_fallback']);
    }

    private static function logTransientStoreFailure(string $context, Throwable $error): void
    {
        static $lastLogTs = [];
        $now = time();
        $last = (int)($lastLogTs[$context] ?? 0);
        if (($now - $last) < 60) {
            return;
        }
        $lastLogTs[$context] = $now;
        error_log('[bitaxe-oc] transient-store ' . $context . ': ' . $error->getMessage());
    }

    private static function assertReplayProtectionFile(
        string $scopeName,
        string $identity,
        int $requestTs,
        string $nonce,
        int $nonceTtlSec,
        int $now
    ): void {
        $root = dirname(__DIR__);
        $key = sha1($scopeName . '|' . substr($identity, 0, 220));
        $path = $root . '/tmp/replay_' . $key . '.json';
        $cutoffTs = $now - $nonceTtlSec;

        $fp = @fopen($path, 'c+');
        if ($fp === false) {
            throw new HttpException('Replay korumasi baslatilamadi.', 503);
        }

        try {
            if (!flock($fp, LOCK_EX)) {
                throw new HttpException('Replay kilidi alinamadi.', 503);
            }

            rewind($fp);
            $raw = stream_get_contents($fp);
            $entries = [];
            if (is_string($raw) && trim($raw) !== '') {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    foreach ($decoded as $storedNonce => $storedTs) {
                        if (!is_string($storedNonce)) {
                            continue;
                        }
                        $ts = (int)$storedTs;
                        if ($ts > $cutoffTs) {
                            $entries[$storedNonce] = $ts;
                        }
                    }
                }
            }

            if (isset($entries[$nonce])) {
                throw new HttpException('Tekrarlanan istek engellendi.', 409);
            }

            if (count($entries) > 6000) {
                asort($entries);
                $entries = array_slice($entries, -4000, null, true);
            }
            $entries[$nonce] = $requestTs;

            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, (string)json_encode($entries, JSON_UNESCAPED_SLASHES));
            fflush($fp);
            @chmod($path, 0600);
        } finally {
            flock($fp, LOCK_UN);
            fclose($fp);
        }
    }

    private static function assertReplayProtectionDb(
        array $securityConfig,
        string $scopeName,
        string $identity,
        int $requestTs,
        string $nonce,
        int $nonceTtlSec
    ): void {
        $pdo = self::transientDbConnection($securityConfig);
        $now = time();
        self::cleanupTransientRows($pdo, $securityConfig, $now);

        $identityHash = sha1($scopeName . '|' . $identity);
        $tableSql = self::quoteTransientIdentifier(self::transientTableName($securityConfig), $securityConfig);
        $expiresTs = $now + max(30, $nonceTtlSec);

        $sql = "INSERT INTO {$tableSql} (scope_name, identity_hash, event_type, nonce_value, created_ts, expires_ts) VALUES (:scope_name, :identity_hash, 'rp', :nonce_value, :created_ts, :expires_ts)";
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':scope_name' => $scopeName,
                ':identity_hash' => $identityHash,
                ':nonce_value' => $nonce,
                ':created_ts' => $requestTs,
                ':expires_ts' => $expiresTs,
            ]);
        } catch (PDOException $error) {
            if (self::isDuplicateKeyViolation($error)) {
                throw new HttpException('Tekrarlanan istek engellendi.', 409);
            }
            throw $error;
        }
    }

    private static function applyRateLimitDb(
        array $securityConfig,
        string $scopeName,
        int $limit,
        int $windowSec,
        string $identity
    ): void {
        $pdo = self::transientDbConnection($securityConfig);
        $now = time();
        $windowStart = $now - $windowSec;
        self::cleanupTransientRows($pdo, $securityConfig, $now);

        $identityHash = sha1($scopeName . '|' . $identity);
        $tableSql = self::quoteTransientIdentifier(self::transientTableName($securityConfig), $securityConfig);

        $countStmt = $pdo->prepare(
            "SELECT COUNT(*) FROM {$tableSql} WHERE scope_name = :scope_name AND identity_hash = :identity_hash AND event_type = 'rl' AND created_ts > :window_start"
        );
        $countStmt->execute([
            ':scope_name' => $scopeName,
            ':identity_hash' => $identityHash,
            ':window_start' => $windowStart,
        ]);
        $hitCount = (int)$countStmt->fetchColumn();
        if ($hitCount >= $limit) {
            throw new HttpException('Cok fazla istek. Lutfen biraz sonra tekrar deneyin.', 429);
        }

        $expiresTs = $now + max(30, $windowSec);
        $insStmt = $pdo->prepare(
            "INSERT INTO {$tableSql} (scope_name, identity_hash, event_type, nonce_value, created_ts, expires_ts) VALUES (:scope_name, :identity_hash, 'rl', :nonce_value, :created_ts, :expires_ts)"
        );
        try {
            $nonce = bin2hex(random_bytes(8));
        } catch (Throwable) {
            $nonce = (string)$now . '_' . (string)mt_rand(100000, 999999);
        }
        $insStmt->execute([
            ':scope_name' => $scopeName,
            ':identity_hash' => $identityHash,
            ':nonce_value' => $nonce,
            ':created_ts' => $now,
            ':expires_ts' => $expiresTs,
        ]);
    }

    private static function transientDbConnection(array $securityConfig): PDO
    {
        $db = is_array($securityConfig['db'] ?? null) ? $securityConfig['db'] : [];
        $engine = strtolower(trim((string)($db['engine'] ?? 'mysql')));
        if (!in_array($engine, ['mysql', 'pgsql'], true)) {
            $engine = 'mysql';
        }

        $dsn = trim((string)($db['dsn'] ?? ''));
        if ($dsn === '') {
            $host = trim((string)($db['host'] ?? 'localhost'));
            $port = (int)($db['port'] ?? ($engine === 'pgsql' ? 5432 : 3306));
            $database = trim((string)($db['database'] ?? ''));
            if ($database === '') {
                throw new HttpException('Transient store DB ayarlari eksik.', 503);
            }
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

        if (self::$transientPdo instanceof PDO && self::$transientPdoDsn === $dsn) {
            self::ensureTransientSchema(self::$transientPdo, $securityConfig, $engine);
            return self::$transientPdo;
        }

        $username = (string)($db['username'] ?? ($db['user'] ?? ''));
        $password = (string)($db['password'] ?? '');
        $pdo = new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        self::$transientPdo = $pdo;
        self::$transientPdoDsn = $dsn;
        self::$transientSchemaReady = false;
        self::ensureTransientSchema($pdo, $securityConfig, $engine);
        return $pdo;
    }

    private static function ensureTransientSchema(PDO $pdo, array $securityConfig, string $engine): void
    {
        if (self::$transientSchemaReady) {
            return;
        }

        $table = self::transientTableName($securityConfig);
        $tableSql = self::quoteTransientIdentifier($table, $securityConfig);

        if ($engine === 'pgsql') {
            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS {$tableSql} (" .
                " id BIGSERIAL PRIMARY KEY," .
                " scope_name VARCHAR(64) NOT NULL," .
                " identity_hash CHAR(40) NOT NULL," .
                " event_type VARCHAR(2) NOT NULL," .
                " nonce_value VARCHAR(128) NOT NULL DEFAULT ''," .
                " created_ts BIGINT NOT NULL," .
                " expires_ts BIGINT NOT NULL" .
                ")"
            );
            $pdo->exec("CREATE INDEX IF NOT EXISTS {$table}_idx_scope_identity_event_ts ON {$tableSql} (scope_name, identity_hash, event_type, created_ts)");
            $pdo->exec("CREATE INDEX IF NOT EXISTS {$table}_idx_expires_ts ON {$tableSql} (expires_ts)");
            $pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS {$table}_uniq_replay ON {$tableSql} (scope_name, identity_hash, event_type, nonce_value)");
        } else {
            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS {$tableSql} (" .
                " id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT," .
                " scope_name VARCHAR(64) NOT NULL," .
                " identity_hash CHAR(40) NOT NULL," .
                " event_type CHAR(2) NOT NULL," .
                " nonce_value VARCHAR(128) NOT NULL DEFAULT ''," .
                " created_ts BIGINT UNSIGNED NOT NULL," .
                " expires_ts BIGINT UNSIGNED NOT NULL," .
                " PRIMARY KEY (id)," .
                " KEY idx_scope_identity_event_ts (scope_name, identity_hash, event_type, created_ts)," .
                " KEY idx_expires_ts (expires_ts)," .
                " UNIQUE KEY uniq_replay (scope_name, identity_hash, event_type, nonce_value)" .
                ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        }

        self::$transientSchemaReady = true;
    }

    private static function cleanupTransientRows(PDO $pdo, array $securityConfig, int $now): void
    {
        $tableSql = self::quoteTransientIdentifier(self::$transientTable, $securityConfig);
        try {
            $stmt = $pdo->prepare("DELETE FROM {$tableSql} WHERE expires_ts <= :now");
            $stmt->execute([':now' => $now]);
        } catch (Throwable $error) {
            self::logTransientStoreFailure('cleanup', $error);
        }
    }

    private static function transientTableName(array $securityConfig): string
    {
        $nameRaw = strtolower(trim((string)($securityConfig['transient_store_table'] ?? 'security_events')));
        if (!preg_match('/^[a-z0-9_]{1,64}$/', $nameRaw)) {
            $nameRaw = 'security_events';
        }
        self::$transientTable = $nameRaw;
        return $nameRaw;
    }

    private static function quoteTransientIdentifier(string $identifier, array $securityConfig): string
    {
        $engine = strtolower(trim((string)((is_array($securityConfig['db'] ?? null) ? ($securityConfig['db']['engine'] ?? '') : '') ?: 'mysql')));
        if ($engine === 'pgsql') {
            return '"' . str_replace('"', '""', $identifier) . '"';
        }
        return '`' . str_replace('`', '``', $identifier) . '`';
    }

    private static function isDuplicateKeyViolation(PDOException $error): bool
    {
        $sqlState = (string)$error->getCode();
        if ($sqlState === '23505') {
            return true;
        }
        $driverCode = (string)($error->errorInfo[1] ?? '');
        return in_array($driverCode, ['1062', '1555', '2067'], true);
    }
}
