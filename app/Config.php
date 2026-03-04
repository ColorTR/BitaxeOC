<?php

declare(strict_types=1);

$config = [
    'app_version' => 'v323',
    'frontend' => [
        // runtime: keep Tailwind runtime script (safe default)
        // static: use prebuilt CSS if available at frontend.tailwind_static_css
        'tailwind_mode' => 'runtime',
        'tailwind_static_css' => 'assets/vendor/tailwind-static.css',
    ],
    'limits' => [
        'max_files_per_batch' => 30,
        'max_file_bytes' => 350 * 1024,
        'max_total_bytes' => 6 * 1024 * 1024,
        'csv_max_data_rows' => 7000,
        'csv_parse_time_budget_ms' => 8000,
        // Keep API responses fast on large merged datasets.
        'response_max_rows' => 8000,
        // UI currently does not use per-file raw rows; keep false for faster payloads.
        'response_include_file_rows' => false,
        // Time-series extraction is optional and expensive on big CSV sets.
        'collect_time_series' => false,
        'max_time_series_points' => 800,
    ],
    'sharing' => [
        'enabled' => true,
        // `file` keeps legacy filesystem behavior, `db` uses PDO-backed storage.
        'driver' => 'file',
        // If db mode fails, keep reading old file-based tokens to avoid broken links.
        'file_fallback_read' => true,
        'storage_dir' => 'storage/shares',
        'token_bytes' => 12,
        // Minimum retention target: 1 year
        'default_ttl_sec' => 365 * 24 * 3600,
        // Max allowed TTL (5 years)
        'max_ttl_sec' => 5 * 365 * 24 * 3600,
        'max_payload_bytes' => 1200 * 1024,
        'max_rows' => 12000,
        // File backend safety caps (ignored in db mode)
        'max_shares' => 4000,
        'max_storage_bytes' => 512 * 1024 * 1024,
        // File backend garbage collection probability
        'gc_probability' => 3,
        // DB backend options
        'db' => [
            // `mysql` (cPanel default) or `pgsql`
            'engine' => 'mysql',
            'host' => 'localhost',
            'port' => 3306,
            'database' => '',
            'username' => '',
            'password' => '',
            // Keep false in production. Enable only if DB explicitly uses empty password.
            'allow_empty_password' => false,
            'table' => 'share_records',
            // Optional full DSN override (if set, host/port/database ignored)
            'dsn' => '',
            'charset' => 'utf8mb4',
            // Payload encoding: prefer compressed for storage efficiency
            'compress_payload' => true,
            // Probabilistic expired-row cleanup on write
            'prune_probability' => 3,
            'prune_batch_size' => 5000,
        ],
        'create_rate_limit_requests' => 20,
        'create_rate_limit_window_sec' => 300,
        'view_rate_limit_requests' => 240,
        'view_rate_limit_window_sec' => 300,
    ],
    'autotune_import' => [
        'enabled' => true,
        // `db` (recommended) or `file`.
        'driver' => 'db',
        // If db mode fails, continue with file backend.
        'file_fallback_read' => true,
        'storage_dir' => 'storage/import_tickets',
        'id_bytes' => 12,
        // Keep tickets short-lived by default.
        'default_ttl_sec' => 600,
        'max_ttl_sec' => 3600,
        'max_csv_bytes' => 2 * 1024 * 1024,
        'max_filename_bytes' => 180,
        'max_source_bytes' => 48,
        // Keep request parser bounded (CSV + protocol overhead).
        'max_request_bytes' => (2 * 1024 * 1024) + (256 * 1024),
        'consume_request_max_bytes' => 32 * 1024,
        // CORS policy for browser-side AxeOS -> OC import.
        'allow_any_origin' => false,
        // Exact allowed origins (case-insensitive compare after normalization).
        'allowed_origins' => [
            'https://bitaxe.colortr.com',
            'http://bitaxe.colortr.com',
        ],
        // Convenience toggles for local/private deployments.
        'allow_bitaxe_origin' => true,
        'allow_localhost_origins' => true,
        'allow_private_lan_origins' => true,
        // If request has no Origin header (curl/server-side), allow it.
        'allow_no_origin_requests' => true,
        'cors_max_age_sec' => 600,
        'create_rate_limit_requests' => 30,
        'create_rate_limit_window_sec' => 300,
        'consume_rate_limit_requests' => 180,
        'consume_rate_limit_window_sec' => 300,
        // File backend GC probability
        'file_prune_probability' => 5,
        // DB backend options
        'db' => [
            'engine' => 'mysql',
            'host' => 'localhost',
            'port' => 3306,
            'database' => '',
            'username' => '',
            'password' => '',
            'allow_empty_password' => false,
            'table' => 'autotune_import_tickets',
            'dsn' => '',
            'charset' => 'utf8mb4',
            // Probabilistic prune on write.
            'prune_probability' => 5,
            'prune_batch_size' => 2000,
        ],
    ],
    'logging' => [
        'enabled' => true,
        // `file` (legacy) or `db` (recommended for scalable ops panel analytics)
        'driver' => 'file',
        // If db mode fails, allow reading old file logs as fallback.
        'file_fallback_read' => true,
        'file' => 'storage/usage_logs.ndjson',
        'archive_glob' => 'storage/usage_logs_*.ndjson*',
        'max_file_bytes' => 5 * 1024 * 1024,
        'max_archives' => 5,
        // rotation: size|daily|weekly (size check always remains as a fallback)
        'rotation' => 'daily',
        'compress_archives' => true,
        'summary_cache_file' => 'storage/usage_summary_cache.json',
        // DB backend options for usage analytics logs.
        'db' => [
            'engine' => 'mysql',
            'host' => 'localhost',
            'port' => 3306,
            'database' => '',
            'username' => '',
            'password' => '',
            // Keep false in production. Enable only if DB explicitly uses empty password.
            'allow_empty_password' => false,
            'table' => 'usage_events',
            // Optional full DSN override (if set, host/port/database ignored)
            'dsn' => '',
            'charset' => 'utf8mb4',
            // Probabilistic retention prune on write.
            'prune_probability' => 3,
            'prune_batch_size' => 4000,
            // Keep at least 1 year by default.
            'retention_days' => 365,
        ],
        // Change to a long random string.
        'visitor_salt' => 'CHANGE_THIS_TO_A_RANDOM_64_CHAR_SECRET',
    ],
    'admin' => [
        // Hidden panel path is: /ops-panel.php
        'username' => 'owner',
        // Change this immediately after deployment.
        'password' => 'CHANGE_THIS_ADMIN_PASSWORD',
        // Preferred: generate with password_hash('your_password', PASSWORD_DEFAULT)
        // and keep plain-text password empty.
        'password_hash' => '',
        // Dedicated ops-panel session cookie name to avoid frontend cookie collisions.
        'session_name' => 'bitaxeoc_admin_sess',
        // Dedicated CSRF session key for ops-panel.
        'csrf_session_key' => 'bitaxeoc_admin_csrf',
        'session_key' => 'bitaxeoc_admin_auth',
        // Keep false for users behind changing IP networks (mobile/private relay).
        'bind_session_to_ip' => false,
        // Keep false for better session continuity on Safari/mobile/proxy UA variants.
        'bind_session_to_user_agent' => false,
        // Keep admin login for up to 30 days by default.
        'session_timeout_sec' => 30 * 24 * 3600,
        // Keep panel cookie cross-navigation friendly (still same-site protected).
        'session_cookie_samesite' => 'Lax',
        // Persistent remember cookie for auto re-auth when PHP session is dropped.
        'remember_cookie_name' => 'bitaxeoc_admin_remember',
        'remember_ttl_sec' => 30 * 24 * 3600,
        // Update last_seen at a controlled interval to reduce session I/O churn.
        'session_touch_interval_sec' => 60,
        // Refresh remember cookie only when close to expiry (avoid per-request rewrites).
        'remember_refresh_threshold_sec' => 24 * 3600,
        // Optional extra secret gate for ops-panel.php (set empty string to disable).
        // Access with: /ops-panel.php?k=YOUR_KEY
        'access_key' => '',
        // Optional IP allowlist for ops panel. Keep empty array to disable.
        'allowed_ips' => [],
        'login_rate_limit_requests' => 12,
        'login_rate_limit_window_sec' => 900,
        'max_rows_in_panel' => 500,
        // Ops panel live server status refresh interval (seconds).
        'server_status_refresh_sec' => 1.0,
        // DB metrics refresh interval (seconds). Kept slower than CPU/RAM refresh.
        'server_status_db_refresh_sec' => 1.0,
        // Process sampler refresh interval and row limit for task-manager table.
        'server_status_process_refresh_sec' => 2.0,
        'server_status_process_limit' => 12,
        // Lightweight API guard for server-status AJAX endpoint.
        'server_status_rate_limit_requests' => 120,
        'server_status_rate_limit_window_sec' => 60,
    ],
    'server' => [
        // Optional explicit public server IP for ops panel display.
        'public_ip' => '',
    ],
    'security' => [
        'session_name' => 'bitaxeoc_sess',
        'csrf_session_key' => 'bitaxeoc_csrf',
        // Keep false unless server/proxy setup is trusted and configured.
        'trust_proxy_headers' => false,
        // Allowlist of trusted reverse-proxy IP/CIDR entries.
        // Example: ['127.0.0.1', '10.0.0.0/8']
        'trusted_proxies' => [],
        // Runtime transient store for replay + rate-limit state: file|db
        'transient_store' => 'file',
        // If db transient store fails, optionally fall back to file-based state.
        'transient_store_file_fallback' => true,
        'transient_store_table' => 'security_events',
        // DB config for transient store when transient_store=db.
        // You can keep empty and override via Config.secret.php.
        'db' => [
            'engine' => 'mysql',
            'host' => 'localhost',
            'port' => 3306,
            'database' => '',
            'username' => '',
            'password' => '',
            // Keep false in production. Enable only if DB explicitly uses empty password.
            'allow_empty_password' => false,
            'dsn' => '',
            'charset' => 'utf8mb4',
        ],
        // Optional remote IP->country fallback when provider headers are missing.
        // Sends client public IP to remote geo service; keep false if not desired.
        'country_lookup_remote' => false,
        'country_lookup_timeout_sec' => 1.0,
        'country_lookup_cache_ttl_sec' => 30 * 24 * 3600,
        'country_lookup_fail_ttl_sec' => 600,
        // Additional overhead (multipart boundaries/headers) allowed over max_total_bytes.
        'max_request_overhead_bytes' => 1024 * 1024,
        'rate_limit_requests' => 25,
        'rate_limit_window_sec' => 300,
        'replay_window_sec' => 180,
        'replay_nonce_ttl_sec' => 900,
    ],
];

$mergeConfigFromFile = static function (array $currentConfig, string $path): array {
    if (!is_file($path) || !is_readable($path)) {
        return $currentConfig;
    }

    $loaded = require $path;
    if (!is_array($loaded)) {
        return $currentConfig;
    }

    return array_replace_recursive($currentConfig, $loaded);
};

$envString = static function (string $key): ?string {
    $value = getenv($key);
    if ($value === false) {
        return null;
    }

    return trim((string)$value);
};

$envInt = static function (string $key) use ($envString): ?int {
    $value = $envString($key);
    if ($value === null || $value === '') {
        return null;
    }
    if (!preg_match('/^-?[0-9]+$/', $value)) {
        return null;
    }

    return (int)$value;
};

$envBool = static function (string $key) use ($envString): ?bool {
    $value = $envString($key);
    if ($value === null || $value === '') {
        return null;
    }

    $normalized = strtolower($value);
    if (in_array($normalized, ['1', 'true', 'yes', 'on'], true)) {
        return true;
    }
    if (in_array($normalized, ['0', 'false', 'no', 'off'], true)) {
        return false;
    }

    return null;
};

$envSecretString = static function (string $valueKey, string $fileKey) use ($envString): ?string {
    $inline = $envString($valueKey);
    if ($inline !== null) {
        return $inline;
    }

    $secretFile = $envString($fileKey);
    if ($secretFile === null || $secretFile === '') {
        return null;
    }

    if (!is_file($secretFile) || !is_readable($secretFile)) {
        return null;
    }

    $content = @file_get_contents($secretFile);
    if (!is_string($content)) {
        return null;
    }

    return rtrim($content, "\r\n");
};

$applyDbEnv = static function (array &$dbConfig, string $prefix) use ($envString, $envInt, $envBool, $envSecretString): void {
    $engine = $envString($prefix . 'DB_ENGINE');
    if ($engine !== null && $engine !== '') {
        $dbConfig['engine'] = $engine;
    }

    $host = $envString($prefix . 'DB_HOST');
    if ($host !== null && $host !== '') {
        $dbConfig['host'] = $host;
    }

    $port = $envInt($prefix . 'DB_PORT');
    if ($port !== null && $port > 0) {
        $dbConfig['port'] = $port;
    }

    $database = $envString($prefix . 'DB_NAME');
    if ($database === null || $database === '') {
        $database = $envString($prefix . 'DB_DATABASE');
    }
    if ($database !== null && $database !== '') {
        $dbConfig['database'] = $database;
    }

    $username = $envString($prefix . 'DB_USER');
    if ($username === null || $username === '') {
        $username = $envString($prefix . 'DB_USERNAME');
    }
    if ($username !== null && $username !== '') {
        $dbConfig['username'] = $username;
    }

    $password = $envSecretString($prefix . 'DB_PASSWORD', $prefix . 'DB_PASSWORD_FILE');
    if ($password !== null) {
        $dbConfig['password'] = $password;
    }

    $dsn = $envString($prefix . 'DB_DSN');
    if ($dsn !== null && $dsn !== '') {
        $dbConfig['dsn'] = $dsn;
    }

    $charset = $envString($prefix . 'DB_CHARSET');
    if ($charset !== null && $charset !== '') {
        $dbConfig['charset'] = $charset;
    }

    $allowEmptyPassword = $envBool($prefix . 'DB_ALLOW_EMPTY_PASSWORD');
    if ($allowEmptyPassword !== null) {
        $dbConfig['allow_empty_password'] = $allowEmptyPassword;
    }
};

$dbIsConfigured = static function (array $dbConfig): bool {
    $dsn = trim((string)($dbConfig['dsn'] ?? ''));
    if ($dsn !== '') {
        return true;
    }

    $database = trim((string)($dbConfig['database'] ?? ''));
    $username = trim((string)($dbConfig['username'] ?? ''));
    $password = (string)($dbConfig['password'] ?? '');
    $allowEmptyPassword = !empty($dbConfig['allow_empty_password']);

    if ($database === '' || $username === '') {
        return false;
    }

    if ($allowEmptyPassword) {
        return true;
    }

    return trim($password) !== '';
};

$secretPath = __DIR__ . '/Config.secret.php';
$config = $mergeConfigFromFile($config, $secretPath);

$localSecretPath = __DIR__ . '/Config.local.php';
$config = $mergeConfigFromFile($config, $localSecretPath);

$externalConfigPath = $envString('BITAXE_CONFIG_FILE');
if ($externalConfigPath !== null && $externalConfigPath !== '') {
    if ($externalConfigPath[0] !== '/' && !preg_match('/^[A-Za-z]:[\\\\\\/]/', $externalConfigPath)) {
        $externalConfigPath = dirname(__DIR__) . '/' . ltrim($externalConfigPath, '/\\');
    }
    $config = $mergeConfigFromFile($config, $externalConfigPath);
}

if (!is_array($config['sharing'] ?? null)) {
    $config['sharing'] = [];
}
if (!is_array($config['autotune_import'] ?? null)) {
    $config['autotune_import'] = [];
}
if (!is_array($config['logging'] ?? null)) {
    $config['logging'] = [];
}
if (!is_array($config['security'] ?? null)) {
    $config['security'] = [];
}
if (!is_array($config['admin'] ?? null)) {
    $config['admin'] = [];
}
if (!is_array($config['server'] ?? null)) {
    $config['server'] = [];
}

if (!is_array($config['sharing']['db'] ?? null)) {
    $config['sharing']['db'] = [];
}
if (!is_array($config['autotune_import']['db'] ?? null)) {
    $config['autotune_import']['db'] = [];
}
if (!is_array($config['logging']['db'] ?? null)) {
    $config['logging']['db'] = [];
}
if (!is_array($config['security']['db'] ?? null)) {
    $config['security']['db'] = [];
}
if (!is_array($config['security']['trusted_proxies'] ?? null)) {
    $trustedProxiesRaw = $config['security']['trusted_proxies'] ?? [];
    if (is_string($trustedProxiesRaw)) {
        $trustedProxiesRaw = explode(',', $trustedProxiesRaw);
    } else {
        $trustedProxiesRaw = [];
    }
    $config['security']['trusted_proxies'] = array_values(array_filter(array_map('trim', (array)$trustedProxiesRaw), static function (string $entry): bool {
        return $entry !== '';
    }));
}

$applyDbEnv($config['sharing']['db'], 'BITAXE_');
$applyDbEnv($config['autotune_import']['db'], 'BITAXE_');
$applyDbEnv($config['logging']['db'], 'BITAXE_');
$applyDbEnv($config['security']['db'], 'BITAXE_');

$applyDbEnv($config['sharing']['db'], 'BITAXE_SHARING_');
$applyDbEnv($config['autotune_import']['db'], 'BITAXE_IMPORT_');
$applyDbEnv($config['autotune_import']['db'], 'BITAXE_AUTOTUNE_IMPORT_');
$applyDbEnv($config['logging']['db'], 'BITAXE_LOGGING_');
$applyDbEnv($config['security']['db'], 'BITAXE_SECURITY_');

$sharingDriver = $envString('BITAXE_SHARING_DRIVER');
if ($sharingDriver !== null && $sharingDriver !== '') {
    $config['sharing']['driver'] = strtolower($sharingDriver);
}

$loggingDriver = $envString('BITAXE_LOGGING_DRIVER');
if ($loggingDriver !== null && $loggingDriver !== '') {
    $config['logging']['driver'] = strtolower($loggingDriver);
}

$importDriver = $envString('BITAXE_IMPORT_DRIVER');
if ($importDriver !== null && $importDriver !== '') {
    $config['autotune_import']['driver'] = strtolower($importDriver);
}

$importAllowAnyOrigin = $envBool('BITAXE_IMPORT_ALLOW_ANY_ORIGIN');
if ($importAllowAnyOrigin !== null) {
    $config['autotune_import']['allow_any_origin'] = $importAllowAnyOrigin;
}

$importAllowedOrigins = $envString('BITAXE_IMPORT_ALLOWED_ORIGINS');
if ($importAllowedOrigins !== null) {
    $config['autotune_import']['allowed_origins'] = array_values(array_filter(array_map('trim', explode(',', $importAllowedOrigins)), static function (string $entry): bool {
        return $entry !== '';
    }));
}

$transientStore = $envString('BITAXE_SECURITY_TRANSIENT_STORE');
if ($transientStore !== null && $transientStore !== '') {
    $config['security']['transient_store'] = strtolower($transientStore);
}

$visitorSalt = $envString('BITAXE_LOG_VISITOR_SALT');
if ($visitorSalt === null || $visitorSalt === '') {
    $visitorSalt = $envString('BITAXE_VISITOR_SALT');
}
if ($visitorSalt !== null && $visitorSalt !== '') {
    $config['logging']['visitor_salt'] = $visitorSalt;
}

$adminUsername = $envString('BITAXE_ADMIN_USERNAME');
if ($adminUsername !== null && $adminUsername !== '') {
    $config['admin']['username'] = $adminUsername;
}

$adminPasswordHash = $envString('BITAXE_ADMIN_PASSWORD_HASH');
if ($adminPasswordHash !== null) {
    $config['admin']['password_hash'] = $adminPasswordHash;
}

$adminAccessKey = $envString('BITAXE_ADMIN_ACCESS_KEY');
if ($adminAccessKey !== null) {
    $config['admin']['access_key'] = $adminAccessKey;
}

$allowedIpsRaw = $envString('BITAXE_ADMIN_ALLOWED_IPS');
if ($allowedIpsRaw !== null) {
    $allowedIps = array_values(array_filter(array_map('trim', explode(',', $allowedIpsRaw)), static function (string $ip): bool {
        return $ip !== '';
    }));
    $config['admin']['allowed_ips'] = $allowedIps;
}

$publicIp = $envString('BITAXE_PUBLIC_IP');
if ($publicIp !== null && $publicIp !== '') {
    $config['server']['public_ip'] = $publicIp;
}

$trustProxyHeaders = $envBool('BITAXE_TRUST_PROXY_HEADERS');
if ($trustProxyHeaders !== null) {
    $config['security']['trust_proxy_headers'] = $trustProxyHeaders;
}

$trustedProxiesEnv = $envString('BITAXE_TRUSTED_PROXIES');
if ($trustedProxiesEnv !== null) {
    $config['security']['trusted_proxies'] = array_values(array_filter(array_map('trim', explode(',', $trustedProxiesEnv)), static function (string $entry): bool {
        return $entry !== '';
    }));
}

if (($config['sharing']['driver'] ?? '') === 'db' && !$dbIsConfigured($config['sharing']['db'])) {
    $config['sharing']['driver'] = 'file';
}

if (($config['logging']['driver'] ?? '') === 'db' && !$dbIsConfigured($config['logging']['db'])) {
    $config['logging']['driver'] = 'file';
}

if (($config['autotune_import']['driver'] ?? '') === 'db' && !$dbIsConfigured($config['autotune_import']['db'])) {
    $config['autotune_import']['driver'] = 'file';
}

if (($config['security']['transient_store'] ?? '') === 'db' && !$dbIsConfigured($config['security']['db'])) {
    $config['security']['transient_store'] = 'file';
}

return $config;
