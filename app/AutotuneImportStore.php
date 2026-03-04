<?php

declare(strict_types=1);

namespace BitaxeOc\App;

use PDO;
use PDOException;
use Throwable;

final class AutotuneImportStore
{
    private bool $enabled;
    private string $rootDir;
    private string $driver;
    private bool $fileFallbackRead;
    private string $storageDir;
    private int $idBytes;
    private int $defaultTtlSec;
    private int $maxTtlSec;
    private int $maxCsvBytes;
    private int $maxFilenameBytes;
    private int $maxSourceBytes;
    private int $filePruneProbability;

    private array $dbConfig;
    private string $dbEngine;
    private string $dbTable;
    private int $dbPruneProbability;
    private int $dbPruneBatchSize;
    private ?PDO $pdo = null;
    private bool $dbSchemaReady = false;

    public function __construct(array $config, ?string $rootDir = null)
    {
        $this->enabled = !array_key_exists('enabled', $config) || !empty($config['enabled']);
        $this->rootDir = $rootDir ? rtrim($rootDir, '/\\') : dirname(__DIR__);
        $this->driver = $this->normalizeDriver((string)($config['driver'] ?? 'file'));
        $this->fileFallbackRead = !array_key_exists('file_fallback_read', $config) || !empty($config['file_fallback_read']);
        $this->storageDir = $this->resolvePath((string)($config['storage_dir'] ?? 'storage/import_tickets'));
        $this->idBytes = max(8, min(32, (int)($config['id_bytes'] ?? 12)));
        $this->defaultTtlSec = max(60, (int)($config['default_ttl_sec'] ?? 600));
        $this->maxTtlSec = max($this->defaultTtlSec, (int)($config['max_ttl_sec'] ?? 3600));
        $this->maxCsvBytes = max(64 * 1024, (int)($config['max_csv_bytes'] ?? (2 * 1024 * 1024)));
        $this->maxFilenameBytes = max(24, min(255, (int)($config['max_filename_bytes'] ?? 180)));
        $this->maxSourceBytes = max(8, min(64, (int)($config['max_source_bytes'] ?? 48)));
        $this->filePruneProbability = $this->sanitizeInt($config['file_prune_probability'] ?? 5, 0, 100, 5);

        $this->dbConfig = is_array($config['db'] ?? null) ? $config['db'] : [];
        $engine = strtolower(trim((string)($this->dbConfig['engine'] ?? 'mysql')));
        $this->dbEngine = in_array($engine, ['mysql', 'pgsql'], true) ? $engine : 'mysql';
        $this->dbTable = $this->sanitizeSqlIdentifier((string)($this->dbConfig['table'] ?? 'autotune_import_tickets'), 'autotune_import_tickets');
        $this->dbPruneProbability = $this->sanitizeInt($this->dbConfig['prune_probability'] ?? 5, 0, 100, 5);
        $this->dbPruneBatchSize = $this->sanitizeInt($this->dbConfig['prune_batch_size'] ?? 2000, 50, 200000, 2000);
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * @param array{source?:mixed,filename?:mixed,csv?:mixed,timestamp?:mixed} $payload
     * @param array{ip?:mixed,origin?:mixed,userAgent?:mixed} $context
     * @return array{
     *   id:string,
     *   source:string,
     *   filename:string,
     *   csvHash:string,
     *   bytes:int,
     *   createdAt:string,
     *   expiresAt:string,
     *   reused:bool
     * }
     */
    public function create(array $payload, ?int $ttlSec = null, array $context = []): array
    {
        if (!$this->enabled) {
            throw new HttpException('Autotune import ozelligi gecici olarak devre disi.', 503);
        }

        $normalized = $this->normalizePayload($payload);
        $ttl = $this->normalizeTtl($ttlSec);
        $ctx = $this->normalizeContext($context);

        if ($this->driver === 'db') {
            try {
                return $this->createDb($normalized, $ttl, $ctx);
            } catch (HttpException $error) {
                if (!$this->fileFallbackRead) {
                    throw $error;
                }
                $this->logRecoverable('create.db.http', $error);
            } catch (Throwable $error) {
                if (!$this->fileFallbackRead) {
                    throw new HttpException('Autotune import veritabani isleminde hata olustu.', 503);
                }
                $this->logRecoverable('create.db.throwable', $error);
            }
        }

        return $this->createFile($normalized, $ttl, $ctx);
    }

    /**
     * @param array{ip?:mixed,origin?:mixed,userAgent?:mixed} $context
     * @return array{
     *   state:'ok'|'not_found'|'expired'|'consumed',
     *   record?:array{
     *     id:string,
     *     source:string,
     *     filename:string,
     *     csv:string,
     *     csvHash:string,
     *     bytes:int,
     *     createdAt:string,
     *     expiresAt:string,
     *     consumedAt:string
     *   }
     * }
     */
    public function consume(string $importId, array $context = []): array
    {
        $normalizedId = strtolower(trim($importId));
        if (!$this->isValidImportId($normalizedId)) {
            return ['state' => 'not_found'];
        }

        if (!$this->enabled) {
            return ['state' => 'not_found'];
        }

        $ctx = $this->normalizeContext($context);

        if ($this->driver === 'db') {
            try {
                return $this->consumeDb($normalizedId, $ctx);
            } catch (Throwable $error) {
                if (!$this->fileFallbackRead) {
                    return ['state' => 'not_found'];
                }
                $this->logRecoverable('consume.db.throwable', $error);
            }
        }

        return $this->consumeFile($normalizedId, $ctx);
    }

    private function createFile(array $normalized, int $ttlSec, array $ctx): array
    {
        $this->ensureStorageDirectory();
        $this->pruneFileStorageMaybe();

        $createdAtTs = time();
        $expiresAtTs = $createdAtTs + $ttlSec;
        $record = [
            'version' => 1,
            'import_id' => '',
            'source' => $normalized['source'],
            'filename' => $normalized['filename'],
            'csv' => $normalized['csv'],
            'csv_sha256' => hash('sha256', $normalized['csv']),
            'csv_bytes' => strlen($normalized['csv']),
            'payload_timestamp' => $normalized['timestamp'],
            'created_at' => gmdate('c', $createdAtTs),
            'expires_at' => gmdate('c', $expiresAtTs),
            'consumed_at' => '',
            'created_ip' => $ctx['ip'],
            'created_origin' => $ctx['origin'],
            'created_user_agent' => $ctx['userAgent'],
            'consume_ip' => '',
            'consume_origin' => '',
            'consume_user_agent' => '',
        ];

        for ($attempt = 0; $attempt < 20; $attempt++) {
            $importId = bin2hex(random_bytes($this->idBytes));
            if (!$this->isValidImportId($importId)) {
                continue;
            }

            $record['import_id'] = $importId;
            $encoded = json_encode($record, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if (!is_string($encoded) || $encoded === '') {
                throw new HttpException('Autotune import kaydi olusturulamadi.', 422);
            }

            $path = $this->filePathForImportId($importId);
            $fp = @fopen($path, 'x');
            if ($fp === false) {
                continue;
            }

            try {
                fwrite($fp, $encoded);
                fflush($fp);
            } finally {
                fclose($fp);
            }
            @chmod($path, 0600);

            return [
                'id' => $importId,
                'source' => $record['source'],
                'filename' => $record['filename'],
                'csvHash' => $record['csv_sha256'],
                'bytes' => (int)$record['csv_bytes'],
                'createdAt' => (string)$record['created_at'],
                'expiresAt' => (string)$record['expires_at'],
                'reused' => false,
            ];
        }

        throw new HttpException('Autotune import kimligi olusturulamadi.', 500);
    }

    private function consumeFile(string $importId, array $ctx): array
    {
        $path = $this->filePathForImportId($importId);
        if (!is_file($path)) {
            return ['state' => 'not_found'];
        }

        $fp = @fopen($path, 'c+');
        if ($fp === false) {
            return ['state' => 'not_found'];
        }

        try {
            if (!flock($fp, LOCK_EX)) {
                throw new HttpException('Autotune import kaydi kilitlenemedi.', 503);
            }

            rewind($fp);
            $raw = stream_get_contents($fp);
            if (!is_string($raw) || trim($raw) === '') {
                @unlink($path);
                return ['state' => 'not_found'];
            }

            $decoded = json_decode($raw, true);
            if (!is_array($decoded)) {
                @unlink($path);
                return ['state' => 'not_found'];
            }

            $expiresAt = (string)($decoded['expires_at'] ?? '');
            $expiresAtTs = strtotime($expiresAt);
            if ($expiresAtTs === false || $expiresAtTs <= time()) {
                @unlink($path);
                return ['state' => 'expired'];
            }

            $consumedAt = trim((string)($decoded['consumed_at'] ?? ''));
            if ($consumedAt !== '') {
                return [
                    'state' => 'consumed',
                    'record' => [
                        'id' => $importId,
                        'source' => (string)($decoded['source'] ?? 'axeos'),
                        'filename' => (string)($decoded['filename'] ?? 'autotune_report.csv'),
                        'csv' => '',
                        'csvHash' => (string)($decoded['csv_sha256'] ?? ''),
                        'bytes' => max(0, (int)($decoded['csv_bytes'] ?? 0)),
                        'createdAt' => (string)($decoded['created_at'] ?? ''),
                        'expiresAt' => $expiresAt,
                        'consumedAt' => $consumedAt,
                    ],
                ];
            }

            $nowIso = gmdate('c');
            $decoded['consumed_at'] = $nowIso;
            $decoded['consume_ip'] = $ctx['ip'];
            $decoded['consume_origin'] = $ctx['origin'];
            $decoded['consume_user_agent'] = $ctx['userAgent'];

            $encoded = json_encode($decoded, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if (!is_string($encoded) || $encoded === '') {
                throw new HttpException('Autotune import kaydi guncellenemedi.', 500);
            }
            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, $encoded);
            fflush($fp);

            return [
                'state' => 'ok',
                'record' => [
                    'id' => $importId,
                    'source' => (string)($decoded['source'] ?? 'axeos'),
                    'filename' => (string)($decoded['filename'] ?? 'autotune_report.csv'),
                    'csv' => (string)($decoded['csv'] ?? ''),
                    'csvHash' => (string)($decoded['csv_sha256'] ?? hash('sha256', (string)($decoded['csv'] ?? ''))),
                    'bytes' => max(0, (int)($decoded['csv_bytes'] ?? strlen((string)($decoded['csv'] ?? '')))),
                    'createdAt' => (string)($decoded['created_at'] ?? ''),
                    'expiresAt' => (string)$expiresAt,
                    'consumedAt' => $nowIso,
                ],
            ];
        } finally {
            flock($fp, LOCK_UN);
            fclose($fp);
        }
    }

    private function createDb(array $normalized, int $ttlSec, array $ctx): array
    {
        $pdo = $this->getDbConnection();
        if ($this->shouldPruneDbOnWrite()) {
            $this->pruneDbRows($pdo);
        }

        $createdAtTs = time();
        $expiresAtTs = $createdAtTs + $ttlSec;
        $csvHash = hash('sha256', $normalized['csv']);
        $csvBytes = strlen($normalized['csv']);
        $tableSql = $this->quotedDbTable();

        for ($attempt = 0; $attempt < 20; $attempt++) {
            $importId = bin2hex(random_bytes($this->idBytes));
            if (!$this->isValidImportId($importId)) {
                continue;
            }

            try {
                $sql = "INSERT INTO {$tableSql} (import_id, source, filename, csv_body, csv_sha256, csv_bytes, payload_timestamp, created_at, expires_at, consumed_at, created_ip, created_origin, created_user_agent, consume_ip, consume_origin, consume_user_agent) VALUES (:import_id, :source, :filename, :csv_body, :csv_sha256, :csv_bytes, :payload_timestamp, :created_at, :expires_at, NULL, :created_ip, :created_origin, :created_user_agent, '', '', '')";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':import_id' => $importId,
                    ':source' => $normalized['source'],
                    ':filename' => $normalized['filename'],
                    ':csv_body' => $normalized['csv'],
                    ':csv_sha256' => $csvHash,
                    ':csv_bytes' => $csvBytes,
                    ':payload_timestamp' => $normalized['timestamp'],
                    ':created_at' => $this->dbDateTime($createdAtTs),
                    ':expires_at' => $this->dbDateTime($expiresAtTs),
                    ':created_ip' => $ctx['ip'],
                    ':created_origin' => $ctx['origin'],
                    ':created_user_agent' => $ctx['userAgent'],
                ]);

                return [
                    'id' => $importId,
                    'source' => $normalized['source'],
                    'filename' => $normalized['filename'],
                    'csvHash' => $csvHash,
                    'bytes' => $csvBytes,
                    'createdAt' => gmdate('c', $createdAtTs),
                    'expiresAt' => gmdate('c', $expiresAtTs),
                    'reused' => false,
                ];
            } catch (PDOException $error) {
                if ($this->isUniqueConstraintError($error)) {
                    continue;
                }
                throw new HttpException('Autotune import veritabani yazma hatasi.', 503);
            }
        }

        throw new HttpException('Autotune import kimligi olusturulamadi.', 500);
    }

    private function consumeDb(string $importId, array $ctx): array
    {
        $pdo = $this->getDbConnection();
        $tableSql = $this->quotedDbTable();

        try {
            $pdo->beginTransaction();

            $selectSql = "SELECT id, import_id, source, filename, csv_body, csv_sha256, csv_bytes, created_at, expires_at, consumed_at FROM {$tableSql} WHERE import_id = :import_id LIMIT 1 FOR UPDATE";
            $selectStmt = $pdo->prepare($selectSql);
            $selectStmt->execute([':import_id' => $importId]);
            $row = $selectStmt->fetch(PDO::FETCH_ASSOC);

            if (!is_array($row)) {
                $pdo->rollBack();
                return ['state' => 'not_found'];
            }

            $expiresAtTs = $this->parseDbDateTime((string)($row['expires_at'] ?? ''));
            if ($expiresAtTs <= 0 || $expiresAtTs <= time()) {
                $pdo->rollBack();
                return ['state' => 'expired'];
            }

            $consumedAtRaw = trim((string)($row['consumed_at'] ?? ''));
            if ($consumedAtRaw !== '') {
                $pdo->rollBack();
                return [
                    'state' => 'consumed',
                    'record' => $this->mapDbRecord($row, false),
                ];
            }

            $nowTs = time();
            $updateSql = "UPDATE {$tableSql} SET consumed_at = :consumed_at, consume_ip = :consume_ip, consume_origin = :consume_origin, consume_user_agent = :consume_user_agent WHERE id = :id";
            $updateStmt = $pdo->prepare($updateSql);
            $updateStmt->execute([
                ':consumed_at' => $this->dbDateTime($nowTs),
                ':consume_ip' => $ctx['ip'],
                ':consume_origin' => $ctx['origin'],
                ':consume_user_agent' => $ctx['userAgent'],
                ':id' => (int)($row['id'] ?? 0),
            ]);

            $pdo->commit();

            $record = $this->mapDbRecord($row, true);
            $record['consumedAt'] = gmdate('c', $nowTs);
            return [
                'state' => 'ok',
                'record' => $record,
            ];
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $error;
        }
    }

    private function shouldPruneDbOnWrite(): bool
    {
        if ($this->dbPruneProbability <= 0) {
            return false;
        }
        return random_int(1, 100) <= $this->dbPruneProbability;
    }

    private function pruneDbRows(PDO $pdo): void
    {
        $tableSql = $this->quotedDbTable();
        $now = $this->dbDateTime(time());
        $consumedCutoff = $this->dbDateTime(time() - 86400);

        if ($this->dbEngine === 'mysql') {
            $sql = "DELETE FROM {$tableSql} WHERE (expires_at <= :now OR (consumed_at IS NOT NULL AND consumed_at <= :consumed_cutoff)) LIMIT {$this->dbPruneBatchSize}";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':now' => $now,
                ':consumed_cutoff' => $consumedCutoff,
            ]);
            return;
        }

        $sql = "DELETE FROM {$tableSql} WHERE id IN (SELECT id FROM {$tableSql} WHERE expires_at <= :now OR (consumed_at IS NOT NULL AND consumed_at <= :consumed_cutoff) ORDER BY id ASC LIMIT {$this->dbPruneBatchSize})";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':now' => $now,
            ':consumed_cutoff' => $consumedCutoff,
        ]);
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
        $allowEmptyPassword = !empty($this->dbConfig['allow_empty_password']);

        if ($dsn === '') {
            $host = trim((string)($this->dbConfig['host'] ?? 'localhost'));
            $port = (int)($this->dbConfig['port'] ?? ($this->dbEngine === 'pgsql' ? 5432 : 3306));
            $database = trim((string)($this->dbConfig['database'] ?? ($this->dbConfig['name'] ?? '')));
            if ($database === '') {
                throw new HttpException('Autotune import veritabani adi bos.', 500);
            }

            if ($this->dbEngine === 'pgsql') {
                $dsn = "pgsql:host={$host};port={$port};dbname={$database}";
            } else {
                $charset = trim((string)($this->dbConfig['charset'] ?? 'utf8mb4'));
                $dsn = "mysql:host={$host};port={$port};dbname={$database};charset={$charset}";
            }
        }

        if ($username === '') {
            throw new HttpException('Autotune import veritabani kullanici adi bos.', 500);
        }
        if (!$allowEmptyPassword && trim($password) === '') {
            throw new HttpException('Autotune import veritabani sifresi bos.', 500);
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
        } catch (PDOException) {
            throw new HttpException('Autotune import veritabanina baglanilamadi.', 503);
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
                "  import_id VARCHAR(80) NOT NULL UNIQUE,\n" .
                "  source VARCHAR(64) NOT NULL DEFAULT 'axeos',\n" .
                "  filename VARCHAR(255) NOT NULL,\n" .
                "  csv_body TEXT NOT NULL,\n" .
                "  csv_sha256 CHAR(64) NOT NULL,\n" .
                "  csv_bytes INTEGER NOT NULL DEFAULT 0,\n" .
                "  payload_timestamp BIGINT NULL,\n" .
                "  created_at TIMESTAMPTZ NOT NULL,\n" .
                "  expires_at TIMESTAMPTZ NOT NULL,\n" .
                "  consumed_at TIMESTAMPTZ NULL,\n" .
                "  created_ip VARCHAR(64) NOT NULL DEFAULT '',\n" .
                "  created_origin VARCHAR(255) NOT NULL DEFAULT '',\n" .
                "  created_user_agent VARCHAR(255) NOT NULL DEFAULT '',\n" .
                "  consume_ip VARCHAR(64) NOT NULL DEFAULT '',\n" .
                "  consume_origin VARCHAR(255) NOT NULL DEFAULT '',\n" .
                "  consume_user_agent VARCHAR(255) NOT NULL DEFAULT ''\n" .
                ")"
            );
            $pdo->exec("CREATE INDEX IF NOT EXISTS {$this->quoteIdentifier('idx_' . $this->dbTable . '_expires_consumed')} ON {$tableSql} (expires_at, consumed_at)");
            $pdo->exec("CREATE INDEX IF NOT EXISTS {$this->quoteIdentifier('idx_' . $this->dbTable . '_created')} ON {$tableSql} (created_at)");
            $pdo->exec("CREATE INDEX IF NOT EXISTS {$this->quoteIdentifier('idx_' . $this->dbTable . '_sha')} ON {$tableSql} (csv_sha256)");
        } else {
            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS {$tableSql} (\n" .
                "  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,\n" .
                "  import_id VARCHAR(80) NOT NULL,\n" .
                "  source VARCHAR(64) NOT NULL DEFAULT 'axeos',\n" .
                "  filename VARCHAR(255) NOT NULL,\n" .
                "  csv_body LONGTEXT NOT NULL,\n" .
                "  csv_sha256 CHAR(64) NOT NULL,\n" .
                "  csv_bytes INT UNSIGNED NOT NULL DEFAULT 0,\n" .
                "  payload_timestamp BIGINT NULL,\n" .
                "  created_at DATETIME NOT NULL,\n" .
                "  expires_at DATETIME NOT NULL,\n" .
                "  consumed_at DATETIME NULL,\n" .
                "  created_ip VARCHAR(64) NOT NULL DEFAULT '',\n" .
                "  created_origin VARCHAR(255) NOT NULL DEFAULT '',\n" .
                "  created_user_agent VARCHAR(255) NOT NULL DEFAULT '',\n" .
                "  consume_ip VARCHAR(64) NOT NULL DEFAULT '',\n" .
                "  consume_origin VARCHAR(255) NOT NULL DEFAULT '',\n" .
                "  consume_user_agent VARCHAR(255) NOT NULL DEFAULT '',\n" .
                "  PRIMARY KEY (id),\n" .
                "  UNIQUE KEY uq_import_id (import_id),\n" .
                "  KEY idx_expires_consumed (expires_at, consumed_at),\n" .
                "  KEY idx_created (created_at),\n" .
                "  KEY idx_sha (csv_sha256)\n" .
                ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        }

        $this->dbSchemaReady = true;
    }

    /**
     * @param array<string,mixed> $payload
     * @return array{source:string,filename:string,csv:string,timestamp:?int}
     */
    private function normalizePayload(array $payload): array
    {
        $sourceRaw = (string)($payload['source'] ?? 'axeos');
        $source = strtolower(trim($sourceRaw));
        $source = preg_replace('/[^a-z0-9._-]/', '', $source) ?? '';
        if ($source === '') {
            $source = 'axeos';
        }
        if (strlen($source) > $this->maxSourceBytes) {
            $source = substr($source, 0, $this->maxSourceBytes);
        }

        $filename = $this->normalizeFilename((string)($payload['filename'] ?? 'autotune_report.csv'));
        $csv = (string)($payload['csv'] ?? '');
        $csv = str_replace("\0", '', $csv);
        if (str_starts_with($csv, "\xEF\xBB\xBF")) {
            $csv = substr($csv, 3);
        }
        $csv = str_replace(["\r\n", "\r"], "\n", $csv);
        $csv = trim($csv);
        if ($csv === '') {
            throw new HttpException('CSV icerigi bos.', 422);
        }

        $csvBytes = strlen($csv);
        if ($csvBytes > $this->maxCsvBytes) {
            throw new HttpException('CSV boyutu izin verilen limiti asti.', 413);
        }
        if (!str_contains($csv, "\n")) {
            throw new HttpException('CSV icerigi gecersiz gorunuyor.', 422);
        }
        if (!str_contains($csv, ',') && !str_contains($csv, ';') && !str_contains($csv, "\t")) {
            throw new HttpException('CSV ayirici bulunamadi.', 422);
        }

        $timestamp = null;
        if (isset($payload['timestamp']) && is_numeric($payload['timestamp'])) {
            $timestamp = (int)$payload['timestamp'];
        } elseif (isset($payload['ts']) && is_numeric($payload['ts'])) {
            $timestamp = (int)$payload['ts'];
        }
        if ($timestamp !== null) {
            $maxDrift = 2 * 24 * 3600;
            if (abs(time() - $timestamp) > $maxDrift) {
                $timestamp = null;
            }
        }

        return [
            'source' => $source,
            'filename' => $filename,
            'csv' => $csv,
            'timestamp' => $timestamp,
        ];
    }

    /**
     * @param array{ip?:mixed,origin?:mixed,userAgent?:mixed} $context
     * @return array{ip:string,origin:string,userAgent:string}
     */
    private function normalizeContext(array $context): array
    {
        return [
            'ip' => $this->sanitizeText((string)($context['ip'] ?? ''), 64),
            'origin' => $this->sanitizeText((string)($context['origin'] ?? ''), 255),
            'userAgent' => $this->sanitizeText((string)($context['userAgent'] ?? ''), 255),
        ];
    }

    private function normalizeFilename(string $filename): string
    {
        $name = trim(str_replace('\\', '/', $filename));
        if (str_contains($name, '/')) {
            $name = basename($name);
        }
        $name = preg_replace('/[\x00-\x1F\x7F]/', '', $name) ?? '';
        // Defense-in-depth: keep filenames plain and inert for downstream consumers.
        // UI escapes values, but API payloads may be reused by other clients.
        $name = preg_replace('/[^a-zA-Z0-9._ -]/', '_', $name) ?? '';
        $name = trim(preg_replace('/[_ ]{2,}/', ' ', $name) ?? $name);
        if ($name === '') {
            $name = 'autotune_report.csv';
        }
        if (strlen($name) > $this->maxFilenameBytes) {
            $name = substr($name, 0, $this->maxFilenameBytes);
        }
        if (!str_ends_with(strtolower($name), '.csv')) {
            $name .= '.csv';
        }
        return $name;
    }

    private function normalizeTtl(?int $ttlSec): int
    {
        $ttl = $ttlSec ?? $this->defaultTtlSec;
        if ($ttl < 60) {
            $ttl = 60;
        }
        if ($ttl > $this->maxTtlSec) {
            $ttl = $this->maxTtlSec;
        }
        return $ttl;
    }

    /**
     * @param array<string,mixed> $row
     * @return array{id:string,source:string,filename:string,csv:string,csvHash:string,bytes:int,createdAt:string,expiresAt:string,consumedAt:string}
     */
    private function mapDbRecord(array $row, bool $includeCsv): array
    {
        $createdAtTs = $this->parseDbDateTime((string)($row['created_at'] ?? ''));
        $expiresAtTs = $this->parseDbDateTime((string)($row['expires_at'] ?? ''));
        $consumedAtTs = $this->parseDbDateTime((string)($row['consumed_at'] ?? ''));

        return [
            'id' => strtolower(trim((string)($row['import_id'] ?? ''))),
            'source' => (string)($row['source'] ?? 'axeos'),
            'filename' => (string)($row['filename'] ?? 'autotune_report.csv'),
            'csv' => $includeCsv ? (string)($row['csv_body'] ?? '') : '',
            'csvHash' => (string)($row['csv_sha256'] ?? ''),
            'bytes' => max(0, (int)($row['csv_bytes'] ?? 0)),
            'createdAt' => $createdAtTs > 0 ? gmdate('c', $createdAtTs) : '',
            'expiresAt' => $expiresAtTs > 0 ? gmdate('c', $expiresAtTs) : '',
            'consumedAt' => $consumedAtTs > 0 ? gmdate('c', $consumedAtTs) : '',
        ];
    }

    private function ensureStorageDirectory(): void
    {
        if (!is_dir($this->storageDir)) {
            if (!@mkdir($this->storageDir, 0750, true) && !is_dir($this->storageDir)) {
                throw new HttpException('Autotune import klasoru olusturulamadi.', 500);
            }
        }
    }

    private function pruneFileStorageMaybe(): void
    {
        if ($this->filePruneProbability <= 0) {
            return;
        }
        if (random_int(1, 100) > $this->filePruneProbability) {
            return;
        }
        $this->pruneFileStorage();
    }

    private function pruneFileStorage(): void
    {
        $files = glob($this->storageDir . '/import_*.json');
        if (!is_array($files) || !$files) {
            return;
        }

        $now = time();
        $consumedCutoff = $now - 86400;

        foreach ($files as $file) {
            if (!is_file($file)) {
                continue;
            }
            $raw = @file_get_contents($file);
            if (!is_string($raw) || trim($raw) === '') {
                @unlink($file);
                continue;
            }
            $decoded = json_decode($raw, true);
            if (!is_array($decoded)) {
                @unlink($file);
                continue;
            }
            $expiresAtTs = strtotime((string)($decoded['expires_at'] ?? ''));
            if ($expiresAtTs !== false && $expiresAtTs <= $now) {
                @unlink($file);
                continue;
            }
            $consumedAtTs = strtotime((string)($decoded['consumed_at'] ?? ''));
            if ($consumedAtTs !== false && $consumedAtTs > 0 && $consumedAtTs <= $consumedCutoff) {
                @unlink($file);
            }
        }
    }

    private function filePathForImportId(string $importId): string
    {
        return $this->storageDir . '/import_' . $importId . '.json';
    }

    private function isValidImportId(string $importId): bool
    {
        return (bool)preg_match('/^[a-f0-9]{16,80}$/', $importId);
    }

    private function normalizeDriver(string $driver): string
    {
        $value = strtolower(trim($driver));
        return $value === 'db' ? 'db' : 'file';
    }

    private function resolvePath(string $path): string
    {
        $trimmed = trim($path);
        if ($trimmed === '') {
            $trimmed = 'storage/import_tickets';
        }
        if (preg_match('/^(?:[A-Za-z]:[\\\\\\/]|\/)/', $trimmed) === 1) {
            return rtrim($trimmed, '/\\');
        }
        return rtrim($this->rootDir . '/' . ltrim($trimmed, '/\\'), '/\\');
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

    private function sanitizeText(string $value, int $maxBytes): string
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return '';
        }
        $clean = preg_replace('/[\x00-\x1F\x7F]/', '', $trimmed) ?? '';
        if ($maxBytes > 0 && strlen($clean) > $maxBytes) {
            $clean = substr($clean, 0, $maxBytes);
        }
        return $clean;
    }

    private function sanitizeInt(mixed $value, int $min, int $max, int $default): int
    {
        if (!is_numeric($value)) {
            return $default;
        }
        $intValue = (int)$value;
        if ($intValue < $min) {
            return $min;
        }
        if ($intValue > $max) {
            return $max;
        }
        return $intValue;
    }

    private function logRecoverable(string $context, Throwable $error): void
    {
        static $lastLogTs = [];
        $now = time();
        $last = (int)($lastLogTs[$context] ?? 0);
        if (($now - $last) < 60) {
            return;
        }
        $lastLogTs[$context] = $now;
        error_log('[bitaxe-oc] autotune-import ' . $context . ': ' . $error->getMessage());
    }
}
