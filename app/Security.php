<?php

declare(strict_types=1);

namespace BitaxeOc\App;

use PDO;
use PDOException;
use RuntimeException;
use Throwable;

final class HttpException extends RuntimeException
{
    public int $statusCode;

    public function __construct(string $message, int $statusCode)
    {
        $this->statusCode = $statusCode;
        parent::__construct($message, $statusCode);
    }
}

final class Security
{
    private static bool $sessionStarted = false;
    private static ?PDO $transientPdo = null;
    private static string $transientPdoDsn = '';
    private static string $transientTable = 'security_events';
    private static bool $transientSchemaReady = false;

    public static function ensureRuntimeDirectories(): void
    {
        $root = dirname(__DIR__);
        $dirs = [$root . '/tmp', $root . '/storage'];

        foreach ($dirs as $dir) {
            if (!is_dir($dir)) {
                @mkdir($dir, 0750, true);
            }
        }
    }

    public static function startSession(array $securityConfig): void
    {
        if (self::$sessionStarted || session_status() === PHP_SESSION_ACTIVE) {
            self::$sessionStarted = true;
            return;
        }

        $sessionName = (string)($securityConfig['session_name'] ?? 'bitaxeoc_sess');
        if ($sessionName !== '') {
            session_name($sessionName);
        }

        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https')
            || (strtolower((string)($_SERVER['REQUEST_SCHEME'] ?? '')) === 'https')
            || ((string)($_SERVER['SERVER_PORT'] ?? '') === '443');
        $sessionCookieLifetimeSec = max(0, (int)($securityConfig['session_cookie_lifetime_sec'] ?? 0));
        $sessionCookieSameSiteRaw = trim((string)($securityConfig['session_cookie_samesite'] ?? 'Strict'));
        $sessionCookieSameSite = ucfirst(strtolower($sessionCookieSameSiteRaw));
        if (!in_array($sessionCookieSameSite, ['Strict', 'Lax', 'None'], true)) {
            $sessionCookieSameSite = 'Strict';
        }

        @ini_set('session.use_strict_mode', '1');
        @ini_set('session.use_only_cookies', '1');
        @ini_set('session.cookie_httponly', '1');
        if ($sessionCookieLifetimeSec > 0) {
            @ini_set('session.gc_maxlifetime', (string)$sessionCookieLifetimeSec);
            @ini_set('session.cookie_lifetime', (string)$sessionCookieLifetimeSec);
        }

        session_set_cookie_params([
            'lifetime' => $sessionCookieLifetimeSec,
            'path' => '/',
            'domain' => '',
            'secure' => $https,
            'httponly' => true,
            'samesite' => $sessionCookieSameSite,
        ]);

        session_start();
        self::$sessionStarted = true;
    }

    public static function setCommonHeaders(): void
    {
        header('X-Frame-Options: DENY');
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: no-referrer');
        header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
        header('Cross-Origin-Resource-Policy: same-origin');
        header('Cross-Origin-Opener-Policy: same-origin');
        header('X-Permitted-Cross-Domain-Policies: none');
    }

    public static function setApiHeaders(int $statusCode = 200): void
    {
        http_response_code($statusCode);
        self::setCommonHeaders();
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
    }

    public static function getOrCreateCsrfToken(string $sessionKey): string
    {
        if (!isset($_SESSION[$sessionKey]) || !is_string($_SESSION[$sessionKey]) || strlen($_SESSION[$sessionKey]) < 32) {
            $_SESSION[$sessionKey] = bin2hex(random_bytes(32));
        }

        return (string)$_SESSION[$sessionKey];
    }

    public static function assertPostRequest(): void
    {
        $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        if ($method !== 'POST') {
            throw new HttpException('Sadece POST istegi kabul edilir.', 405);
        }
    }

    public static function assertSameOriginRequest(): void
    {
        $requestHost = self::normalizeHost((string)($_SERVER['HTTP_HOST'] ?? ''));
        if ($requestHost === '') {
            return;
        }

        $origin = (string)($_SERVER['HTTP_ORIGIN'] ?? '');
        if ($origin !== '') {
            $originHost = self::normalizeHost((string)parse_url($origin, PHP_URL_HOST));
            if ($originHost !== '' && $originHost !== $requestHost) {
                throw new HttpException('Origin dogrulamasi basarisiz.', 403);
            }
            return;
        }

        $referer = (string)($_SERVER['HTTP_REFERER'] ?? '');
        if ($referer !== '') {
            $refererHost = self::normalizeHost((string)parse_url($referer, PHP_URL_HOST));
            if ($refererHost !== '' && $refererHost !== $requestHost) {
                throw new HttpException('Referer dogrulamasi basarisiz.', 403);
            }
        }
    }

    public static function assertCsrfToken(string $sessionKey, string $incomingToken): void
    {
        $token = isset($_SESSION[$sessionKey]) ? (string)$_SESSION[$sessionKey] : '';
        if ($token === '' || $incomingToken === '' || !hash_equals($token, $incomingToken)) {
            throw new HttpException('Guvenlik dogrulamasi basarisiz (CSRF).', 403);
        }
    }

    public static function assertReplayProtection(
        array $securityConfig,
        string $scope,
        string $incomingTimestamp,
        string $incomingNonce,
        ?string $clientIdentity = null
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
            $identity = self::getClientIp(false);
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

    public static function getClientIp(bool $trustProxyHeaders = false, array $securityConfig = []): string
    {
        if (self::isTrustedProxyRequest($trustProxyHeaders, $securityConfig)) {
            $candidates = [
                (string)($_SERVER['HTTP_CF_CONNECTING_IP'] ?? ''),
                (string)($_SERVER['HTTP_X_FORWARDED_FOR'] ?? ''),
                (string)($_SERVER['REMOTE_ADDR'] ?? ''),
            ];
        } else {
            $candidates = [(string)($_SERVER['REMOTE_ADDR'] ?? '')];
        }

        foreach ($candidates as $candidate) {
            if ($candidate === '') {
                continue;
            }

            $parts = explode(',', $candidate);
            foreach ($parts as $part) {
                $ip = trim($part);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return '0.0.0.0';
    }

    public static function detectCountryCode(bool $trustProxyHeaders = false, array $securityConfig = []): string
    {
        if (self::isTrustedProxyRequest($trustProxyHeaders, $securityConfig)) {
            $headerCandidates = [
                (string)($_SERVER['HTTP_CF_IPCOUNTRY'] ?? ''),
                (string)($_SERVER['HTTP_CLOUDFRONT_VIEWER_COUNTRY'] ?? ''),
                (string)($_SERVER['HTTP_X_COUNTRY_CODE'] ?? ''),
                (string)($_SERVER['HTTP_X_APPENGINE_COUNTRY'] ?? ''),
                (string)($_SERVER['GEOIP_COUNTRY_CODE'] ?? ''),
            ];

            foreach ($headerCandidates as $candidate) {
                $normalized = self::normalizeCountryCode($candidate);
                if ($normalized !== 'ZZ') {
                    return $normalized;
                }
            }
        }

        if (function_exists('geoip_country_code_by_name')) {
            $ip = self::getClientIp($trustProxyHeaders, $securityConfig);
            if ($ip !== '' && $ip !== '0.0.0.0') {
                $geoipCode = @geoip_country_code_by_name($ip);
                if (is_string($geoipCode)) {
                    $normalized = self::normalizeCountryCode($geoipCode);
                    if ($normalized !== 'ZZ') {
                        return $normalized;
                    }
                }
            }
        }

        $remoteEnabled = !empty($securityConfig['country_lookup_remote']);
        if ($remoteEnabled) {
            $ip = self::getClientIp($trustProxyHeaders, $securityConfig);
            if (
                filter_var($ip, FILTER_VALIDATE_IP) &&
                !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)
            ) {
                // Private/reserved range: remote lookup is meaningless.
                return 'ZZ';
            }

            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                $successTtl = max(60, (int)($securityConfig['country_lookup_cache_ttl_sec'] ?? (30 * 24 * 3600)));
                $failTtl = max(30, (int)($securityConfig['country_lookup_fail_ttl_sec'] ?? 600));
                $cached = self::readCountryLookupCacheEntry($ip);
                if ($cached !== null) {
                    return $cached;
                }

                $timeoutSec = max(0.2, min(3.0, (float)($securityConfig['country_lookup_timeout_sec'] ?? 1.0)));
                $remoteCode = self::lookupCountryCodeRemote($ip, $timeoutSec);
                $ttl = $remoteCode === 'ZZ' ? $failTtl : $successTtl;
                self::writeCountryLookupCacheEntry($ip, $remoteCode, $ttl);
                if ($remoteCode !== 'ZZ') {
                    return $remoteCode;
                }
            }
        }

        return 'ZZ';
    }

    public static function applyRateLimit(
        string $scope,
        int $limit,
        int $windowSec,
        ?string $clientIdentity = null
    ): void {
        if ($limit <= 0 || $windowSec <= 0) {
            return;
        }

        $scopeName = trim($scope) !== '' ? trim($scope) : 'default';
        $identity = trim((string)$clientIdentity);
        if ($identity === '') {
            $identity = self::getClientIp(false);
        }
        if ($identity === '') {
            $identity = '0.0.0.0';
        }

        // Legacy callers keep file behavior. New callers should use applyRateLimitConfig.
        self::applyRateLimitFile($scopeName, $limit, $windowSec, $identity, dirname(__DIR__));
    }

    public static function applyRateLimitConfig(
        array $securityConfig,
        string $scope,
        int $limit,
        int $windowSec,
        ?string $clientIdentity = null
    ): void {
        if ($limit <= 0 || $windowSec <= 0) {
            return;
        }

        $scopeName = trim($scope) !== '' ? trim($scope) : 'default';
        $identity = trim((string)$clientIdentity);
        if ($identity === '') {
            $identity = self::getClientIp(false);
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

    public static function normalizeUploadedFiles(array $filesBag): array
    {
        if (!isset($filesBag['name'])) {
            return [];
        }

        $normalized = [];
        if (is_array($filesBag['name'])) {
            $count = count($filesBag['name']);
            for ($i = 0; $i < $count; $i++) {
                $normalized[] = [
                    'name' => (string)($filesBag['name'][$i] ?? ''),
                    'type' => (string)($filesBag['type'][$i] ?? ''),
                    'tmp_name' => (string)($filesBag['tmp_name'][$i] ?? ''),
                    'error' => (int)($filesBag['error'][$i] ?? UPLOAD_ERR_NO_FILE),
                    'size' => (int)($filesBag['size'][$i] ?? 0),
                ];
            }

            return $normalized;
        }

        $normalized[] = [
            'name' => (string)($filesBag['name'] ?? ''),
            'type' => (string)($filesBag['type'] ?? ''),
            'tmp_name' => (string)($filesBag['tmp_name'] ?? ''),
            'error' => (int)($filesBag['error'] ?? UPLOAD_ERR_NO_FILE),
            'size' => (int)($filesBag['size'] ?? 0),
        ];

        return $normalized;
    }

    public static function jsonResponse(array $payload, int $statusCode = 200): never
    {
        self::setApiHeaders($statusCode);
        echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    public static function normalizeHost(string $host): string
    {
        $trimmed = trim(strtolower($host));
        if ($trimmed === '') {
            return '';
        }

        return preg_replace('/:\\d+$/', '', $trimmed) ?? $trimmed;
    }

    private static function isTrustedProxyRequest(bool $trustProxyHeaders, array $securityConfig): bool
    {
        if (!$trustProxyHeaders) {
            return false;
        }

        $remoteAddr = trim((string)($_SERVER['REMOTE_ADDR'] ?? ''));
        if (!filter_var($remoteAddr, FILTER_VALIDATE_IP)) {
            return false;
        }

        $trustedProxies = self::normalizeTrustedProxyEntries($securityConfig['trusted_proxies'] ?? []);
        if ($trustedProxies === []) {
            return false;
        }

        return self::ipMatchesAllowlist($remoteAddr, $trustedProxies);
    }

    /**
     * @return list<string>
     */
    private static function normalizeTrustedProxyEntries(mixed $raw): array
    {
        if (is_string($raw)) {
            $raw = explode(',', $raw);
        }
        if (!is_array($raw)) {
            return [];
        }

        $entries = [];
        foreach ($raw as $item) {
            $value = trim((string)$item);
            if ($value === '') {
                continue;
            }
            $entries[] = $value;
        }

        return $entries;
    }

    /**
     * @param list<string> $allowlist
     */
    private static function ipMatchesAllowlist(string $ip, array $allowlist): bool
    {
        foreach ($allowlist as $entry) {
            if ($ip === $entry) {
                return true;
            }
            if (str_contains($entry, '/') && self::ipMatchesCidr($ip, $entry)) {
                return true;
            }
        }

        return false;
    }

    private static function ipMatchesCidr(string $ip, string $cidr): bool
    {
        if (!str_contains($cidr, '/')) {
            return false;
        }

        [$subnet, $prefixRaw] = explode('/', $cidr, 2);
        $subnet = trim($subnet);
        $prefix = (int)trim($prefixRaw);

        $ipBin = @inet_pton($ip);
        $subnetBin = @inet_pton($subnet);
        if ($ipBin === false || $subnetBin === false || strlen($ipBin) !== strlen($subnetBin)) {
            return false;
        }

        $bitsTotal = strlen($ipBin) * 8;
        if ($prefix < 0 || $prefix > $bitsTotal) {
            return false;
        }

        $fullBytes = intdiv($prefix, 8);
        $remainingBits = $prefix % 8;

        if ($fullBytes > 0 && substr($ipBin, 0, $fullBytes) !== substr($subnetBin, 0, $fullBytes)) {
            return false;
        }

        if ($remainingBits === 0) {
            return true;
        }

        $mask = (0xFF << (8 - $remainingBits)) & 0xFF;
        return ((ord($ipBin[$fullBytes]) & $mask) === (ord($subnetBin[$fullBytes]) & $mask));
    }

    private static function normalizeCountryCode(string $value): string
    {
        $code = strtoupper(trim($value));
        if ($code === '' || $code === 'XX' || $code === '--') {
            return 'ZZ';
        }
        if (!preg_match('/^[A-Z]{2}$/', $code)) {
            return 'ZZ';
        }
        return $code;
    }

    private static function countryLookupCachePath(): string
    {
        return dirname(__DIR__) . '/tmp/country_lookup_cache.json';
    }

    private static function loadCountryLookupCache(): array
    {
        $path = self::countryLookupCachePath();
        $raw = @file_get_contents($path);
        if (!is_string($raw) || trim($raw) === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    private static function saveCountryLookupCache(array $cache): void
    {
        $path = self::countryLookupCachePath();
        @file_put_contents($path, json_encode($cache, JSON_UNESCAPED_SLASHES), LOCK_EX);
        @chmod($path, 0600);
    }

    private static function readCountryLookupCacheEntry(string $ip): ?string
    {
        $cache = self::loadCountryLookupCache();
        $entry = $cache[$ip] ?? null;
        if (!is_array($entry)) {
            return null;
        }

        $expiresAt = (int)($entry['expires_at'] ?? 0);
        if ($expiresAt <= time()) {
            unset($cache[$ip]);
            self::saveCountryLookupCache($cache);
            return null;
        }

        $code = self::normalizeCountryCode((string)($entry['code'] ?? 'ZZ'));
        return $code;
    }

    private static function writeCountryLookupCacheEntry(string $ip, string $code, int $ttlSec): void
    {
        $cache = self::loadCountryLookupCache();
        $now = time();
        $expiresAt = $now + max(30, $ttlSec);

        foreach ($cache as $cacheIp => $entry) {
            if (!is_array($entry) || (int)($entry['expires_at'] ?? 0) <= $now) {
                unset($cache[$cacheIp]);
            }
        }

        if (count($cache) > 10000) {
            $cache = array_slice($cache, -8000, null, true);
        }

        $cache[$ip] = [
            'code' => self::normalizeCountryCode($code),
            'expires_at' => $expiresAt,
        ];
        self::saveCountryLookupCache($cache);
    }

    private static function lookupCountryCodeRemote(string $ip, float $timeoutSec): string
    {
        $sources = [
            ['kind' => 'text', 'url' => 'https://ipapi.co/' . rawurlencode($ip) . '/country/'],
            ['kind' => 'json', 'url' => 'https://ipwho.is/' . rawurlencode($ip)],
        ];

        foreach ($sources as $source) {
            $raw = self::httpGetText((string)$source['url'], $timeoutSec);
            if ($raw === '') {
                continue;
            }

            if (($source['kind'] ?? '') === 'text') {
                $normalized = self::normalizeCountryCode($raw);
                if ($normalized !== 'ZZ') {
                    return $normalized;
                }
                continue;
            }

            $decoded = json_decode($raw, true);
            if (!is_array($decoded)) {
                continue;
            }
            $normalized = self::normalizeCountryCode((string)($decoded['country_code'] ?? ''));
            if ($normalized !== 'ZZ') {
                return $normalized;
            }
        }

        return 'ZZ';
    }

    private static function httpGetText(string $url, float $timeoutSec): string
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => max(0.2, $timeoutSec),
                'ignore_errors' => true,
                'header' => "User-Agent: bitaxe-oc-country-lookup/1.0\r\nAccept: text/plain, application/json\r\n",
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);

        $raw = @file_get_contents($url, false, $context);
        return is_string($raw) ? trim($raw) : '';
    }
}
