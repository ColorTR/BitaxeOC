<?php

declare(strict_types=1);

namespace BitaxeOc\App;

use PDO;
use PDOException;
use Throwable;

final class ShareStore
{
    private const FILTER_KEYS = ['f-v-min', 'f-f-min', 'f-h-min', 'f-e-max', 'f-vr-max', 'f-t-max'];
    private const SORT_KEYS = ['score', 'h', 'source', 'v', 'f', 'vr', 't', 'err', 'e'];
    private const SHARE_LAYOUT_PANEL_IDS = ['stability', 'elite', 'aate', 'power', 'efficiency', 'temperature', 'frequency', 'vf-heatmap', 'table'];

    private bool $enabled;
    private string $rootDir;
    private string $storageDir;
    private int $tokenBytes;
    private int $defaultTtlSec;
    private int $maxTtlSec;
    private int $maxPayloadBytes;
    private int $maxRows;
    private int $maxShares;
    private int $maxStorageBytes;

    private string $driver;
    private bool $fileFallbackRead;

    private array $dbConfig;
    private string $dbEngine;
    private string $dbTable;
    private bool $dbCompressPayload;
    private int $dbPruneProbability;
    private int $dbPruneBatchSize;
    private ?PDO $pdo = null;
    private bool $dbSchemaReady = false;

    public function __construct(array $sharingConfig, ?string $rootDir = null)
    {
        $this->enabled = (bool)($sharingConfig['enabled'] ?? true);
        $this->rootDir = $rootDir ? rtrim($rootDir, '/\\') : dirname(__DIR__);
        $storageRel = (string)($sharingConfig['storage_dir'] ?? 'storage/shares');
        $this->storageDir = $this->resolvePath($storageRel);
        $this->tokenBytes = max(8, min(32, (int)($sharingConfig['token_bytes'] ?? 12)));
        $this->defaultTtlSec = max(3600, (int)($sharingConfig['default_ttl_sec'] ?? (365 * 24 * 3600)));
        $this->maxTtlSec = max($this->defaultTtlSec, (int)($sharingConfig['max_ttl_sec'] ?? (5 * 365 * 24 * 3600)));
        $this->maxPayloadBytes = max(16 * 1024, (int)($sharingConfig['max_payload_bytes'] ?? (1200 * 1024)));
        $this->maxRows = max(100, (int)($sharingConfig['max_rows'] ?? 12000));
        $this->maxShares = max(10, (int)($sharingConfig['max_shares'] ?? 4000));
        $this->maxStorageBytes = max(10 * 1024 * 1024, (int)($sharingConfig['max_storage_bytes'] ?? (512 * 1024 * 1024)));

        $this->driver = $this->normalizeDriver((string)($sharingConfig['driver'] ?? 'file'));
        $this->fileFallbackRead = !empty($sharingConfig['file_fallback_read']);

        $this->dbConfig = is_array($sharingConfig['db'] ?? null) ? $sharingConfig['db'] : [];
        $engine = strtolower(trim((string)($this->dbConfig['engine'] ?? 'mysql')));
        $this->dbEngine = in_array($engine, ['mysql', 'pgsql'], true) ? $engine : 'mysql';
        $this->dbTable = $this->sanitizeSqlIdentifier((string)($this->dbConfig['table'] ?? 'share_records'), 'share_records');
        $this->dbCompressPayload = !array_key_exists('compress_payload', $this->dbConfig) || !empty($this->dbConfig['compress_payload']);
        $this->dbPruneProbability = $this->sanitizeInt($this->dbConfig['prune_probability'] ?? 3, 0, 100, 3);
        $this->dbPruneBatchSize = $this->sanitizeInt($this->dbConfig['prune_batch_size'] ?? 5000, 100, 200000, 5000);
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function createShare(array $payload, ?int $ttlSec = null): array
    {
        if (!$this->enabled) {
            throw new HttpException('Paylasim ozelligi gecici olarak devre disi.', 503);
        }

        if ($this->driver === 'db') {
            try {
                return $this->createShareDb($payload, $ttlSec);
            } catch (HttpException $error) {
                if (!$this->fileFallbackRead) {
                    throw $error;
                }
                $this->logRecoverable('createShareDb.http', $error);
            } catch (Throwable $error) {
                if (!$this->fileFallbackRead) {
                    throw new HttpException('Paylasim veritabani isleminde hata olustu.', 503);
                }
                $this->logRecoverable('createShareDb.throwable', $error);
            }
        }

        return $this->createShareFile($payload, $ttlSec);
    }

    public function getShare(string $token): ?array
    {
        if (!$this->enabled) {
            return null;
        }

        if ($this->driver === 'db') {
            try {
                $record = $this->getShareDb($token);
                if ($record !== null) {
                    return $record;
                }
            } catch (Throwable $error) {
                if (!$this->fileFallbackRead) {
                    return null;
                }
                $this->logRecoverable('getShareDb.throwable', $error);
            }

            if ($this->fileFallbackRead) {
                return $this->getShareFile($token);
            }

            return null;
        }

        return $this->getShareFile($token);
    }

    public function getShareMeta(string $token): ?array
    {
        if (!$this->enabled) {
            return null;
        }

        if ($this->driver === 'db') {
            try {
                $meta = $this->getShareMetaDb($token);
                if ($meta !== null) {
                    return $meta;
                }
            } catch (Throwable $error) {
                if (!$this->fileFallbackRead) {
                    return null;
                }
                $this->logRecoverable('getShareMetaDb.throwable', $error);
            }

            if ($this->fileFallbackRead) {
                return $this->getShareMetaFile($token);
            }

            return null;
        }

        return $this->getShareMetaFile($token);
    }

    private function createShareFile(array $payload, ?int $ttlSec = null): array
    {
        $this->ensureStorageDirectory();

        $sanitizedPayload = $this->sanitizePayload($payload);
        $payloadJson = json_encode($sanitizedPayload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!is_string($payloadJson) || $payloadJson === '') {
            throw new HttpException('Paylasim verisi hazirlanamadi.', 422);
        }
        if (strlen($payloadJson) > $this->maxPayloadBytes) {
            throw new HttpException('Paylasim verisi boyut limiti asildi.', 413);
        }

        $dedupeHash = $this->buildDedupeHash($sanitizedPayload);
        $existing = $this->findActiveShareByDedupeHash($dedupeHash);
        if ($existing !== null) {
            return [
                'token' => $existing['token'],
                'createdAt' => $existing['createdAt'],
                'expiresAt' => $existing['expiresAt'],
                'rows' => $existing['rows'],
                'bytes' => $existing['bytes'],
                'reused' => true,
            ];
        }

        $token = $this->generateUniqueToken();
        $createdAtTs = time();
        $ttl = $this->normalizeTtl($ttlSec);
        $expiresAtTs = $createdAtTs + $ttl;

        $record = [
            'version' => 1,
            'token' => $token,
            'created_at' => gmdate('c', $createdAtTs),
            'expires_at' => gmdate('c', $expiresAtTs),
            'payload_sha1' => sha1($payloadJson),
            'dedupe_sha256' => $dedupeHash,
            'payload' => $sanitizedPayload,
        ];

        $encodedRecord = json_encode($record, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!is_string($encodedRecord) || $encodedRecord === '') {
            throw new HttpException('Paylasim kaydi olusturulamadi.', 422);
        }

        $this->writeTokenRecord($token, $encodedRecord);
        $this->pruneStorage();

        return [
            'token' => $token,
            'createdAt' => $record['created_at'],
            'expiresAt' => $record['expires_at'],
            'rows' => count($sanitizedPayload['consolidatedData']),
            'bytes' => strlen($encodedRecord),
            'reused' => false,
        ];
    }

    private function getShareFile(string $token): ?array
    {
        $normalizedToken = strtolower(trim($token));
        if (!$this->isValidToken($normalizedToken)) {
            return null;
        }

        $path = $this->tokenPath($normalizedToken);
        if (!is_file($path)) {
            return null;
        }

        $raw = @file_get_contents($path);
        if (!is_string($raw) || $raw === '') {
            return null;
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            @unlink($path);
            return null;
        }

        $expiresAt = (string)($decoded['expires_at'] ?? '');
        $expiresTs = strtotime($expiresAt);
        if ($expiresAt === '' || $expiresTs === false || $expiresTs <= time()) {
            @unlink($path);
            return null;
        }

        $payload = is_array($decoded['payload'] ?? null) ? $decoded['payload'] : null;
        if ($payload === null) {
            return null;
        }

        return [
            'token' => $normalizedToken,
            'createdAt' => (string)($decoded['created_at'] ?? ''),
            'expiresAt' => $expiresAt,
            'payloadSha1' => (string)($decoded['payload_sha1'] ?? ''),
            'payload' => $payload,
        ];
    }

    private function createShareDb(array $payload, ?int $ttlSec = null): array
    {
        $sanitizedPayload = $this->sanitizePayload($payload);
        $payloadJson = json_encode($sanitizedPayload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!is_string($payloadJson) || $payloadJson === '') {
            throw new HttpException('Paylasim verisi hazirlanamadi.', 422);
        }
        if (strlen($payloadJson) > $this->maxPayloadBytes) {
            throw new HttpException('Paylasim verisi boyut limiti asildi.', 413);
        }

        $dedupeHash = $this->buildDedupeHash($sanitizedPayload);
        $pdo = $this->getDbConnection();

        if ($this->shouldPruneDbOnWrite()) {
            $this->pruneDbExpiredRows($pdo);
        }

        $existing = $this->findActiveShareByDedupeHashDb($pdo, $dedupeHash);
        if ($existing !== null) {
            return [
                'token' => $existing['token'],
                'createdAt' => $existing['createdAt'],
                'expiresAt' => $existing['expiresAt'],
                'rows' => $existing['rows'],
                'bytes' => $existing['bytes'],
                'reused' => true,
            ];
        }

        $encodedPayload = $this->encodePayloadForDb($payloadJson);
        $createdAtTs = time();
        $ttl = $this->normalizeTtl($ttlSec);
        $expiresAtTs = $createdAtTs + $ttl;
        $createdAtIso = gmdate('c', $createdAtTs);
        $expiresAtIso = gmdate('c', $expiresAtTs);
        $rows = count($sanitizedPayload['consolidatedData']);
        $bytes = strlen($encodedPayload['body']);
        $payloadSha1 = sha1($payloadJson);
        $appVersion = $this->sanitizeText($sanitizedPayload['meta']['appVersion'] ?? '', 48);

        $tableSql = $this->quotedDbTable();

        for ($attempt = 0; $attempt < 20; $attempt++) {
            $token = bin2hex(random_bytes($this->tokenBytes));
            if (!$this->isValidToken($token)) {
                continue;
            }

            try {
                $sql = "INSERT INTO {$tableSql} (token, dedupe_sha256, payload_sha1, payload_body, payload_encoding, rows_count, bytes_count, created_at, expires_at, last_access_at, access_count, app_version) VALUES (:token, :dedupe_sha256, :payload_sha1, :payload_body, :payload_encoding, :rows_count, :bytes_count, :created_at, :expires_at, :last_access_at, 0, :app_version)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':token' => $token,
                    ':dedupe_sha256' => $dedupeHash,
                    ':payload_sha1' => $payloadSha1,
                    ':payload_body' => $encodedPayload['body'],
                    ':payload_encoding' => $encodedPayload['encoding'],
                    ':rows_count' => $rows,
                    ':bytes_count' => $bytes,
                    ':created_at' => $this->dbDateTime($createdAtTs),
                    ':expires_at' => $this->dbDateTime($expiresAtTs),
                    ':last_access_at' => $this->dbDateTime($createdAtTs),
                    ':app_version' => $appVersion,
                ]);

                return [
                    'token' => $token,
                    'createdAt' => $createdAtIso,
                    'expiresAt' => $expiresAtIso,
                    'rows' => $rows,
                    'bytes' => $bytes,
                    'reused' => false,
                ];
            } catch (PDOException $error) {
                if ($this->isUniqueConstraintError($error)) {
                    continue;
                }
                throw new HttpException('Paylasim kaydi veritabanina yazilamadi.', 503);
            }
        }

        throw new HttpException('Paylasim tokeni olusturulamadi.', 503);
    }

    private function getShareDb(string $token): ?array
    {
        $normalizedToken = strtolower(trim($token));
        if (!$this->isValidToken($normalizedToken)) {
            return null;
        }

        $pdo = $this->getDbConnection();
        $row = $this->findActiveShareRowByTokenDb($pdo, $normalizedToken, true);
        if ($row === null) {
            return null;
        }

        $expiresTs = $this->parseDbDateTime((string)($row['expires_at'] ?? ''));
        $payload = $this->decodePayloadFromDb(
            (string)($row['payload_encoding'] ?? 'json'),
            (string)($row['payload_body'] ?? '')
        );
        if (!is_array($payload)) {
            return null;
        }

        $this->touchShareAccessStatsDb($pdo, $normalizedToken, time(), null);

        $createdTs = $this->parseDbDateTime((string)($row['created_at'] ?? ''));

        return [
            'token' => $normalizedToken,
            'createdAt' => $createdTs > 0 ? gmdate('c', $createdTs) : '',
            'expiresAt' => gmdate('c', $expiresTs),
            'payloadSha1' => (string)($row['payload_sha1'] ?? ''),
            'payload' => $payload,
        ];
    }

    private function getShareMetaDb(string $token): ?array
    {
        $normalizedToken = strtolower(trim($token));
        if (!$this->isValidToken($normalizedToken)) {
            return null;
        }

        $pdo = $this->getDbConnection();
        $row = $this->findActiveShareRowByTokenDb($pdo, $normalizedToken, false);
        if ($row === null) {
            return null;
        }
        $expiresTs = $this->parseDbDateTime((string)($row['expires_at'] ?? ''));

        $this->touchShareAccessStatsDb($pdo, $normalizedToken, time(), 'getShareMetaDb.touch');

        $createdTs = $this->parseDbDateTime((string)($row['created_at'] ?? ''));
        return [
            'token' => $normalizedToken,
            'createdAt' => $createdTs > 0 ? gmdate('c', $createdTs) : '',
            'expiresAt' => gmdate('c', $expiresTs),
            'payloadSha1' => (string)($row['payload_sha1'] ?? ''),
        ];
    }

    private function findActiveShareRowByTokenDb(PDO $pdo, string $token, bool $includePayload): ?array
    {
        $tableSql = $this->quotedDbTable();
        $columns = $includePayload
            ? 'token, payload_sha1, payload_body, payload_encoding, created_at, expires_at'
            : 'token, payload_sha1, created_at, expires_at';

        $sql = "SELECT {$columns} FROM {$tableSql} WHERE token = :token LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':token' => $token]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return null;
        }

        $expiresTs = $this->parseDbDateTime((string)($row['expires_at'] ?? ''));
        if ($expiresTs <= 0 || $expiresTs <= time()) {
            $deleteStmt = $pdo->prepare("DELETE FROM {$tableSql} WHERE token = :token");
            $deleteStmt->execute([':token' => $token]);
            return null;
        }

        return $row;
    }

    private function touchShareAccessStatsDb(PDO $pdo, string $token, int $nowTs, ?string $recoverableTag): void
    {
        $tableSql = $this->quotedDbTable();
        try {
            $touchStmt = $pdo->prepare("UPDATE {$tableSql} SET last_access_at = :last_access_at, access_count = access_count + 1 WHERE token = :token");
            $touchStmt->execute([
                ':last_access_at' => $this->dbDateTime($nowTs),
                ':token' => $token,
            ]);
        } catch (Throwable $error) {
            if ($recoverableTag !== null && $recoverableTag !== '') {
                $this->logRecoverable($recoverableTag, $error);
            }
        }
    }

    private function encodePayloadForDb(string $payloadJson): array
    {
        if ($this->dbCompressPayload && function_exists('gzencode')) {
            $compressed = @gzencode($payloadJson, 6);
            if (is_string($compressed) && $compressed !== '') {
                $base64 = base64_encode($compressed);
                if (is_string($base64) && $base64 !== '' && strlen($base64) < strlen($payloadJson)) {
                    return [
                        'encoding' => 'json+gz+base64',
                        'body' => $base64,
                    ];
                }
            }
        }

        return [
            'encoding' => 'json',
            'body' => $payloadJson,
        ];
    }

    private function decodePayloadFromDb(string $encoding, string $body): ?array
    {
        $json = '';
        if ($encoding === 'json+gz+base64') {
            if (!function_exists('gzdecode')) {
                return null;
            }
            $decoded = base64_decode($body, true);
            if (!is_string($decoded) || $decoded === '') {
                return null;
            }
            $jsonDecoded = @gzdecode($decoded);
            if (!is_string($jsonDecoded) || $jsonDecoded === '') {
                return null;
            }
            $json = $jsonDecoded;
        } else {
            $json = $body;
        }

        $payload = json_decode($json, true);
        return is_array($payload) ? $payload : null;
    }

    private function shouldPruneDbOnWrite(): bool
    {
        if ($this->dbPruneProbability <= 0) {
            return false;
        }
        return random_int(1, 100) <= $this->dbPruneProbability;
    }

    private function pruneDbExpiredRows(PDO $pdo): void
    {
        $tableSql = $this->quotedDbTable();
        $now = $this->dbDateTime(time());

        if ($this->dbEngine === 'mysql') {
            $sql = "DELETE FROM {$tableSql} WHERE expires_at <= :now LIMIT {$this->dbPruneBatchSize}";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':now' => $now]);
            return;
        }

        $sql = "DELETE FROM {$tableSql} WHERE id IN (SELECT id FROM {$tableSql} WHERE expires_at <= :now ORDER BY id ASC LIMIT {$this->dbPruneBatchSize})";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':now' => $now]);
    }

    private function findActiveShareByDedupeHashDb(PDO $pdo, string $dedupeHash): ?array
    {
        $tableSql = $this->quotedDbTable();
        $sql = "SELECT token, created_at, expires_at, rows_count, bytes_count FROM {$tableSql} WHERE dedupe_sha256 = :dedupe_sha256 AND expires_at > :now ORDER BY id DESC LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':dedupe_sha256' => $dedupeHash,
            ':now' => $this->dbDateTime(time()),
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return null;
        }

        $token = strtolower(trim((string)($row['token'] ?? '')));
        if (!$this->isValidToken($token)) {
            return null;
        }

        $createdTs = $this->parseDbDateTime((string)($row['created_at'] ?? ''));
        $expiresTs = $this->parseDbDateTime((string)($row['expires_at'] ?? ''));
        if ($expiresTs <= 0 || $expiresTs <= time()) {
            return null;
        }

        return [
            'token' => $token,
            'createdAt' => $createdTs > 0 ? gmdate('c', $createdTs) : '',
            'expiresAt' => gmdate('c', $expiresTs),
            'rows' => max(0, (int)($row['rows_count'] ?? 0)),
            'bytes' => max(0, (int)($row['bytes_count'] ?? 0)),
        ];
    }

    private function getDbConnection(): PDO
    {
        if ($this->pdo instanceof PDO) {
            return $this->pdo;
        }

        if (!class_exists(PDO::class)) {
            throw new HttpException('PDO eklentisi bulunamadi.', 503);
        }

        $availableDrivers = PDO::getAvailableDrivers();
        if (!in_array($this->dbEngine, $availableDrivers, true)) {
            throw new HttpException('PDO surucusu bulunamadi: ' . $this->dbEngine, 503);
        }

        $dsn = trim((string)($this->dbConfig['dsn'] ?? ''));
        $username = (string)($this->dbConfig['username'] ?? ($this->dbConfig['user'] ?? ''));
        $password = (string)($this->dbConfig['password'] ?? '');

        if ($dsn === '') {
            $host = trim((string)($this->dbConfig['host'] ?? 'localhost'));
            $port = (int)($this->dbConfig['port'] ?? ($this->dbEngine === 'pgsql' ? 5432 : 3306));
            $database = trim((string)($this->dbConfig['database'] ?? ($this->dbConfig['name'] ?? '')));
            if ($database === '') {
                throw new HttpException('Paylasim veritabani adi bos.', 500);
            }

            if ($this->dbEngine === 'pgsql') {
                $dsn = "pgsql:host={$host};port={$port};dbname={$database}";
            } else {
                $charset = trim((string)($this->dbConfig['charset'] ?? 'utf8mb4'));
                $dsn = "mysql:host={$host};port={$port};dbname={$database};charset={$charset}";
            }
        }

        if ($username === '') {
            throw new HttpException('Paylasim veritabani kullanici adi bos.', 500);
        }

        try {
            $this->pdo = new PDO(
                $dsn,
                $username,
                $password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );
        } catch (PDOException $error) {
            throw new HttpException('Paylasim veritabanina baglanilamadi.', 503);
        }

        $this->ensureDbSchema($this->pdo);

        return $this->pdo;
    }

    private function ensureDbSchema(PDO $pdo): void
    {
        if ($this->dbSchemaReady) {
            return;
        }

        $tableSql = $this->quotedDbTable();

        if ($this->dbEngine === 'pgsql') {
            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS {$tableSql} (\n" .
                "  id BIGSERIAL PRIMARY KEY,\n" .
                "  token VARCHAR(80) NOT NULL UNIQUE,\n" .
                "  dedupe_sha256 CHAR(64) NOT NULL,\n" .
                "  payload_sha1 CHAR(40) NOT NULL,\n" .
                "  payload_body TEXT NOT NULL,\n" .
                "  payload_encoding VARCHAR(24) NOT NULL DEFAULT 'json',\n" .
                "  rows_count INTEGER NOT NULL DEFAULT 0,\n" .
                "  bytes_count INTEGER NOT NULL DEFAULT 0,\n" .
                "  created_at TIMESTAMPTZ NOT NULL,\n" .
                "  expires_at TIMESTAMPTZ NOT NULL,\n" .
                "  last_access_at TIMESTAMPTZ NULL,\n" .
                "  access_count BIGINT NOT NULL DEFAULT 0,\n" .
                "  app_version VARCHAR(48) NOT NULL DEFAULT ''\n" .
                ")"
            );
            $pdo->exec("CREATE INDEX IF NOT EXISTS {$this->quoteIdentifier('idx_' . $this->dbTable . '_dedupe_expires')} ON {$tableSql} (dedupe_sha256, expires_at)");
            $pdo->exec("CREATE INDEX IF NOT EXISTS {$this->quoteIdentifier('idx_' . $this->dbTable . '_expires')} ON {$tableSql} (expires_at)");
            $pdo->exec("CREATE INDEX IF NOT EXISTS {$this->quoteIdentifier('idx_' . $this->dbTable . '_created')} ON {$tableSql} (created_at)");
        } else {
            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS {$tableSql} (\n" .
                "  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,\n" .
                "  token VARCHAR(80) NOT NULL,\n" .
                "  dedupe_sha256 CHAR(64) NOT NULL,\n" .
                "  payload_sha1 CHAR(40) NOT NULL,\n" .
                "  payload_body LONGTEXT NOT NULL,\n" .
                "  payload_encoding VARCHAR(24) NOT NULL DEFAULT 'json',\n" .
                "  rows_count INT UNSIGNED NOT NULL DEFAULT 0,\n" .
                "  bytes_count INT UNSIGNED NOT NULL DEFAULT 0,\n" .
                "  created_at DATETIME NOT NULL,\n" .
                "  expires_at DATETIME NOT NULL,\n" .
                "  last_access_at DATETIME NULL,\n" .
                "  access_count BIGINT UNSIGNED NOT NULL DEFAULT 0,\n" .
                "  app_version VARCHAR(48) NOT NULL DEFAULT '',\n" .
                "  PRIMARY KEY (id),\n" .
                "  UNIQUE KEY uq_token (token),\n" .
                "  KEY idx_dedupe_expires (dedupe_sha256, expires_at),\n" .
                "  KEY idx_expires (expires_at),\n" .
                "  KEY idx_created (created_at)\n" .
                ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        }

        $this->dbSchemaReady = true;
    }

    private function isUniqueConstraintError(PDOException $error): bool
    {
        $sqlState = (string)$error->getCode();
        if ($sqlState === '23000' || $sqlState === '23505') {
            return true;
        }
        $message = strtolower((string)$error->getMessage());
        return str_contains($message, 'duplicate') || str_contains($message, 'unique');
    }

    private function dbDateTime(int $timestamp): string
    {
        return gmdate('Y-m-d H:i:s', $timestamp);
    }

    private function parseDbDateTime(string $value): int
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return 0;
        }
        $ts = strtotime($trimmed . ' UTC');
        if ($ts === false) {
            $ts = strtotime($trimmed);
        }
        return $ts === false ? 0 : $ts;
    }

    private function normalizeDriver(string $driver): string
    {
        $value = strtolower(trim($driver));
        return $value === 'db' ? 'db' : 'file';
    }

    private function sanitizeSqlIdentifier(string $value, string $fallback): string
    {
        $normalized = strtolower(trim($value));
        if ($normalized === '' || !preg_match('/^[a-z_][a-z0-9_]{0,62}$/', $normalized)) {
            return $fallback;
        }
        return $normalized;
    }

    private function quoteIdentifier(string $identifier): string
    {
        if ($this->dbEngine === 'pgsql') {
            return '"' . str_replace('"', '""', $identifier) . '"';
        }
        return '`' . str_replace('`', '``', $identifier) . '`';
    }

    private function quotedDbTable(): string
    {
        return $this->quoteIdentifier($this->dbTable);
    }

    private function buildDedupeHash(array $sanitizedPayload): string
    {
        $canonical = $sanitizedPayload;
        if (isset($canonical['meta']) && is_array($canonical['meta'])) {
            unset($canonical['meta']['exportedAt']);
        }

        $json = json_encode($canonical, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!is_string($json) || $json === '') {
            throw new HttpException('Paylasim verisi hash olusturulamadi.', 422);
        }

        return hash('sha256', $json);
    }

    private function findActiveShareByDedupeHash(string $dedupeHash): ?array
    {
        $files = $this->collectShareFiles();
        if (!$files) {
            return null;
        }

        usort($files, static function (array $a, array $b): int {
            return $b['mtime'] <=> $a['mtime'];
        });

        $now = time();
        foreach ($files as $file) {
            $raw = @file_get_contents($file['path']);
            if (!is_string($raw) || $raw === '') {
                continue;
            }

            $decoded = json_decode($raw, true);
            if (!is_array($decoded)) {
                @unlink($file['path']);
                continue;
            }

            $expiresAt = (string)($decoded['expires_at'] ?? '');
            $expiresTs = strtotime($expiresAt);
            if ($expiresAt === '' || $expiresTs === false || $expiresTs <= $now) {
                @unlink($file['path']);
                continue;
            }

            $recordHash = strtolower(trim((string)($decoded['dedupe_sha256'] ?? '')));
            if ($recordHash === '') {
                $recordPayload = is_array($decoded['payload'] ?? null) ? $decoded['payload'] : null;
                if ($recordPayload !== null) {
                    try {
                        $recordHash = $this->buildDedupeHash($recordPayload);
                    } catch (HttpException) {
                        $recordHash = '';
                    }
                }
            }

            if ($recordHash === '' || !hash_equals($dedupeHash, $recordHash)) {
                continue;
            }

            $token = strtolower(trim((string)($decoded['token'] ?? '')));
            if (!$this->isValidToken($token)) {
                $fromFile = basename((string)$file['path'], '.json');
                $token = strtolower(trim($fromFile));
            }
            if (!$this->isValidToken($token)) {
                continue;
            }

            $payload = is_array($decoded['payload'] ?? null) ? $decoded['payload'] : [];
            $rows = is_array($payload['consolidatedData'] ?? null) ? count($payload['consolidatedData']) : 0;
            $createdAt = (string)($decoded['created_at'] ?? gmdate('c', max(0, (int)$file['mtime'])));

            return [
                'token' => $token,
                'createdAt' => $createdAt,
                'expiresAt' => $expiresAt,
                'rows' => $rows,
                'bytes' => is_int($file['size']) ? max(0, $file['size']) : strlen($raw),
            ];
        }

        return null;
    }

    private function sanitizePayload(array $payload): array
    {
        $rowsRaw = is_array($payload['consolidatedData'] ?? null) ? $payload['consolidatedData'] : [];
        if (!$rowsRaw) {
            throw new HttpException('Paylasim verisinde consolidatedData bulunamadi.', 422);
        }
        if (count($rowsRaw) > $this->maxRows) {
            throw new HttpException('Paylasim satir limiti asildi.', 413);
        }

        $rows = [];
        foreach ($rowsRaw as $item) {
            if (!is_array($item)) {
                continue;
            }
            $normalized = $this->sanitizeShareRow($item);
            if ($normalized === null) {
                continue;
            }
            $rows[] = $normalized;
        }

        if (!$rows) {
            throw new HttpException('Paylasim icin gecerli satir bulunamadi.', 422);
        }

        $metaRaw = is_array($payload['meta'] ?? null) ? $payload['meta'] : [];
        $meta = [
            'exportedAt' => $this->sanitizeIsoDate((string)($metaRaw['exportedAt'] ?? '')),
            'appVersion' => $this->sanitizeText($metaRaw['appVersion'] ?? '', 48),
            'mode' => 'share',
            'selectedLanguage' => $this->sanitizeLanguageCode($metaRaw['selectedLanguage'] ?? ''),
        ];

        $visibleRows = $this->sanitizeInt($payload['visibleRows'] ?? 15, 1, 500, 15);

        $filtersRaw = is_array($payload['filters'] ?? null) ? $payload['filters'] : [];
        $filters = [];
        foreach (self::FILTER_KEYS as $key) {
            if (!array_key_exists($key, $filtersRaw)) {
                continue;
            }
            $numeric = $this->toNumber($filtersRaw[$key]);
            if ($numeric === null) {
                continue;
            }
            $filters[$key] = round($numeric, 6);
        }
        $sortKey = strtolower(trim((string)($filtersRaw['sortKey'] ?? '')));
        if (in_array($sortKey, self::SORT_KEYS, true)) {
            $filters['sortKey'] = $sortKey;
        }
        $sortDir = strtolower(trim((string)($filtersRaw['sortDir'] ?? '')));
        if ($sortDir === 'asc' || $sortDir === 'desc') {
            $filters['sortDir'] = $sortDir;
        }

        $sourceFilesRaw = is_array($payload['sourceFiles'] ?? null) ? $payload['sourceFiles'] : [];
        $sourceFiles = [];
        foreach ($sourceFilesRaw as $item) {
            if (!is_array($item)) {
                continue;
            }
            if (count($sourceFiles) >= 128) {
                break;
            }
            $sourceFiles[] = [
                'id' => $this->sanitizeText($item['id'] ?? '', 220),
                'name' => $this->sanitizeText($item['name'] ?? '', 200),
                'lastModified' => $this->sanitizeInt($item['lastModified'] ?? 0, 0, 4102444800000, 0),
                'isMaster' => !empty($item['isMaster']),
                'enabled' => !empty($item['enabled']),
                'stats' => $this->sanitizeStats(is_array($item['stats'] ?? null) ? $item['stats'] : []),
            ];
        }

        $layout = $this->sanitizeLayout(is_array($payload['layout'] ?? null) ? $payload['layout'] : []);

        return [
            'meta' => $meta,
            'visibleRows' => $visibleRows,
            'filters' => $filters,
            'layout' => $layout,
            'sourceFiles' => $sourceFiles,
            'consolidatedData' => $rows,
        ];
    }

    private function sanitizeShareRow(array $row): ?array
    {
        $v = $this->toNumber($row['v'] ?? null);
        $f = $this->toNumber($row['f'] ?? null);
        $h = $this->toNumber($row['h'] ?? null);
        $e = $this->toNumber($row['e'] ?? null);
        $p = $this->toNumber($row['p'] ?? null);

        if ($h === null && $p !== null && $e !== null && $e > 0) {
            $h = ($p * 1000) / $e;
        }
        if ($e === null && $p !== null && $h !== null && $h > 0) {
            $e = ($p * 1000) / $h;
        }
        if ($p === null && $h !== null && $e !== null && $h > 0) {
            $p = ($h * $e) / 1000;
        }

        if ($v === null || $f === null || $h === null) {
            return null;
        }

        $source = strtolower(trim((string)($row['source'] ?? 'archive')));
        if (!in_array($source, ['master', 'legacy_high', 'archive'], true)) {
            $source = 'archive';
        }

        $score = $this->toNumber($row['score'] ?? null);
        if ($score === null) {
            $score = 0.0;
        }

        return [
            'source' => $source,
            'sourceFileId' => $this->sanitizeText($row['sourceFileId'] ?? '', 220),
            'sourceFileName' => $this->sanitizeText($row['sourceFileName'] ?? '', 200),
            'v' => round($v, 6),
            'f' => round($f, 6),
            'h' => round($h, 6),
            't' => $this->roundNullable($this->toNumber($row['t'] ?? null), 6),
            'vr' => $this->roundNullable($this->toNumber($row['vr'] ?? null), 6),
            'e' => $this->roundNullable($e, 6),
            'err' => round($this->toNumber($row['err'] ?? 0) ?? 0, 6),
            'p' => $this->roundNullable($p, 6),
            'score' => round($score, 4),
        ];
    }

    private function sanitizeStats(array $stats): array
    {
        return [
            'totalRows' => $this->sanitizeInt($stats['totalRows'] ?? 0, 0, 1000000, 0),
            'parsedRows' => $this->sanitizeInt($stats['parsedRows'] ?? 0, 0, 1000000, 0),
            'skippedRows' => $this->sanitizeInt($stats['skippedRows'] ?? 0, 0, 1000000, 0),
            'missingVrRows' => $this->sanitizeInt($stats['missingVrRows'] ?? 0, 0, 1000000, 0),
            'usedTempAsVr' => !empty($stats['usedTempAsVr']),
            'derivedHashRows' => $this->sanitizeInt($stats['derivedHashRows'] ?? 0, 0, 1000000, 0),
            'derivedEffRows' => $this->sanitizeInt($stats['derivedEffRows'] ?? 0, 0, 1000000, 0),
            'derivedPowerRows' => $this->sanitizeInt($stats['derivedPowerRows'] ?? 0, 0, 1000000, 0),
            'missingErrRows' => $this->sanitizeInt($stats['missingErrRows'] ?? 0, 0, 1000000, 0),
            'partialRows' => $this->sanitizeInt($stats['partialRows'] ?? 0, 0, 1000000, 0),
            'truncatedRows' => $this->sanitizeInt($stats['truncatedRows'] ?? 0, 0, 1000000, 0),
            'parseTimedOut' => !empty($stats['parseTimedOut']),
        ];
    }

    private function sanitizeLayout(array $layout): array
    {
        $orderRaw = is_array($layout['order'] ?? null) ? $layout['order'] : [];
        $seen = [];
        $order = [];

        foreach ($orderRaw as $panelIdRaw) {
            $panelId = strtolower(trim((string)$panelIdRaw));
            if ($panelId === '' || isset($seen[$panelId])) {
                continue;
            }
            if (!in_array($panelId, self::SHARE_LAYOUT_PANEL_IDS, true)) {
                continue;
            }
            $order[] = $panelId;
            $seen[$panelId] = true;
            if (count($order) >= count(self::SHARE_LAYOUT_PANEL_IDS)) {
                break;
            }
        }

        foreach (self::SHARE_LAYOUT_PANEL_IDS as $panelId) {
            if (isset($seen[$panelId])) {
                continue;
            }
            $order[] = $panelId;
        }

        $visibilityRaw = is_array($layout['visibility'] ?? null) ? $layout['visibility'] : [];
        $visibility = [];
        foreach (self::SHARE_LAYOUT_PANEL_IDS as $panelId) {
            if (!array_key_exists($panelId, $visibilityRaw)) {
                continue;
            }
            $visibility[$panelId] = !empty($visibilityRaw[$panelId]);
        }

        return [
            'order' => $order,
            'visibility' => $visibility,
        ];
    }

    private function normalizeTtl(?int $ttlSec): int
    {
        if ($ttlSec === null) {
            return $this->defaultTtlSec;
        }
        return max(3600, min($this->maxTtlSec, $ttlSec));
    }

    private function generateUniqueToken(): string
    {
        for ($attempt = 0; $attempt < 12; $attempt++) {
            $token = bin2hex(random_bytes($this->tokenBytes));
            if (!$this->isValidToken($token)) {
                continue;
            }
            if (!is_file($this->tokenPath($token))) {
                return $token;
            }
        }

        throw new HttpException('Paylasim tokeni olusturulamadi.', 503);
    }

    private function isValidToken(string $token): bool
    {
        return (bool)preg_match('/^[a-f0-9]{16,80}$/', $token);
    }

    private function tokenPath(string $token): string
    {
        $prefix = substr($token, 0, 2);
        return $this->storageDir . DIRECTORY_SEPARATOR . $prefix . DIRECTORY_SEPARATOR . $token . '.json';
    }

    private function ensureStorageDirectory(): void
    {
        if (!is_dir($this->storageDir)) {
            @mkdir($this->storageDir, 0750, true);
        }
    }

    private function writeTokenRecord(string $token, string $json): void
    {
        $path = $this->tokenPath($token);
        $dir = dirname($path);
        if (!is_dir($dir)) {
            @mkdir($dir, 0750, true);
        }

        $tmp = $path . '.tmp.' . bin2hex(random_bytes(4));
        $written = @file_put_contents($tmp, $json, LOCK_EX);
        if (!is_int($written) || $written <= 0) {
            @unlink($tmp);
            throw new HttpException('Paylasim kaydi diske yazilamadi.', 503);
        }

        @chmod($tmp, 0600);
        if (!@rename($tmp, $path)) {
            @unlink($tmp);
            throw new HttpException('Paylasim kaydi finalize edilemedi.', 503);
        }
        @chmod($path, 0600);
    }

    private function pruneStorage(): void
    {
        $files = $this->collectShareFiles();
        if (!$files) {
            return;
        }

        $maxAgeThreshold = time() - $this->maxTtlSec;
        foreach ($files as $idx => $file) {
            if ($file['mtime'] < $maxAgeThreshold) {
                @unlink($file['path']);
                unset($files[$idx]);
            }
        }

        if (!$files) {
            return;
        }

        $files = array_values($files);
        $count = count($files);
        $totalBytes = 0;
        foreach ($files as $file) {
            $totalBytes += $file['size'];
        }

        if ($count <= $this->maxShares && $totalBytes <= $this->maxStorageBytes) {
            return;
        }

        usort($files, static function (array $a, array $b): int {
            return $a['mtime'] <=> $b['mtime'];
        });

        foreach ($files as $file) {
            if ($count <= $this->maxShares && $totalBytes <= $this->maxStorageBytes) {
                break;
            }
            @unlink($file['path']);
            $count -= 1;
            $totalBytes -= $file['size'];
        }
    }

    private function collectShareFiles(): array
    {
        if (!is_dir($this->storageDir)) {
            return [];
        }

        $out = [];
        $prefixDirs = @scandir($this->storageDir);
        if (!is_array($prefixDirs)) {
            return [];
        }

        foreach ($prefixDirs as $prefix) {
            if ($prefix === '.' || $prefix === '..') {
                continue;
            }
            $prefixPath = $this->storageDir . DIRECTORY_SEPARATOR . $prefix;
            if (!is_dir($prefixPath)) {
                continue;
            }

            $files = @scandir($prefixPath);
            if (!is_array($files)) {
                continue;
            }

            foreach ($files as $fileName) {
                if (!preg_match('/^[a-f0-9]{16,80}\.json$/', $fileName)) {
                    continue;
                }
                $path = $prefixPath . DIRECTORY_SEPARATOR . $fileName;
                if (!is_file($path)) {
                    continue;
                }
                $size = @filesize($path);
                $mtime = @filemtime($path);
                $out[] = [
                    'path' => $path,
                    'size' => is_int($size) ? $size : 0,
                    'mtime' => is_int($mtime) ? $mtime : 0,
                ];
            }
        }

        return $out;
    }

    private function resolvePath(string $path): string
    {
        $trimmed = trim($path);
        if ($trimmed === '') {
            $trimmed = 'storage/shares';
        }

        $isAbsolute = (
            str_starts_with($trimmed, '/') ||
            str_starts_with($trimmed, '\\') ||
            (bool)preg_match('/^[A-Za-z]:[\/\\\\]/', $trimmed)
        );
        if ($isAbsolute) {
            return rtrim($trimmed, '/\\');
        }

        return $this->rootDir . DIRECTORY_SEPARATOR . ltrim($trimmed, '/\\');
    }

    private function sanitizeIsoDate(string $value): string
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return gmdate('c');
        }
        $ts = strtotime($trimmed);
        if ($ts === false) {
            return gmdate('c');
        }
        return gmdate('c', $ts);
    }

    private function sanitizeLanguageCode(mixed $value): string
    {
        $code = strtolower(trim((string)$value));
        if ($code === '') {
            return 'en';
        }
        if (strlen($code) > 12) {
            $code = substr($code, 0, 12);
        }
        if (!preg_match('/^[a-z0-9_-]+$/', $code)) {
            return 'en';
        }
        return $code;
    }

    private function sanitizeText(mixed $value, int $maxLen): string
    {
        $text = trim((string)$value);
        if ($text === '') {
            return '';
        }
        $text = str_replace(["\0", "\r", "\n", "\t"], ' ', $text);
        if (strlen($text) > $maxLen) {
            $text = substr($text, 0, $maxLen);
        }
        return $text;
    }

    private function sanitizeInt(mixed $value, int $min, int $max, int $fallback): int
    {
        if (!is_numeric($value)) {
            return $fallback;
        }
        $number = (int)$value;
        if ($number < $min) {
            return $min;
        }
        if ($number > $max) {
            return $max;
        }
        return $number;
    }

    private function toNumber(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_string($value)) {
            $normalized = trim(str_replace(',', '.', $value));
            if ($normalized === '' || !is_numeric($normalized)) {
                return null;
            }
            $value = $normalized;
        }

        if (!is_numeric($value)) {
            return null;
        }

        $number = (float)$value;
        if (!is_finite($number)) {
            return null;
        }
        return $number;
    }

    private function getShareMetaFile(string $token): ?array
    {
        $record = $this->getShareFile($token);
        if (!is_array($record)) {
            return null;
        }

        return [
            'token' => (string)($record['token'] ?? ''),
            'createdAt' => (string)($record['createdAt'] ?? ''),
            'expiresAt' => (string)($record['expiresAt'] ?? ''),
            'payloadSha1' => (string)($record['payloadSha1'] ?? ''),
        ];
    }

    private function logRecoverable(string $context, ?Throwable $error = null): void
    {
        static $lastLoggedByContext = [];
        $now = time();
        $lastLogged = (int)($lastLoggedByContext[$context] ?? 0);
        if (($now - $lastLogged) < 60) {
            return;
        }
        $lastLoggedByContext[$context] = $now;

        $message = '[bitaxe-oc] share_store_recoverable:' . $context;
        if ($error !== null) {
            $message .= ' - ' . $error->getMessage();
        }
        error_log($message);
    }

    private function roundNullable(?float $value, int $precision = 6): ?float
    {
        if ($value === null || !is_finite($value)) {
            return null;
        }
        return round($value, $precision);
    }
}
