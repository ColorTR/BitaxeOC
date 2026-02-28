<?php

declare(strict_types=1);

$sharedDbPassword = '';
$sharedDbPasswordPath = __DIR__ . '/.db_password';
if (is_file($sharedDbPasswordPath) && is_readable($sharedDbPasswordPath)) {
    $rawPassword = @file_get_contents($sharedDbPasswordPath);
    if (is_string($rawPassword)) {
        $sharedDbPassword = trim($rawPassword);
    }
}

$resolveDbPassword = static function (string $specificEnvKey) use ($sharedDbPassword): string {
    $specific = getenv($specificEnvKey);
    if ($specific !== false && trim((string)$specific) !== '') {
        return (string)$specific;
    }
    $common = getenv('BITAXE_DB_PASSWORD');
    if ($common !== false && trim((string)$common) !== '') {
        return (string)$common;
    }
    return $sharedDbPassword;
};

return [
    'sharing' => [
        'driver' => 'db',
        'file_fallback_read' => true,
        'db' => [
            'engine' => getenv('BITAXE_SHARING_DB_ENGINE') ?: (getenv('BITAXE_DB_ENGINE') ?: 'mysql'),
            'host' => getenv('BITAXE_SHARING_DB_HOST') ?: (getenv('BITAXE_DB_HOST') ?: 'localhost'),
            'port' => (int)(getenv('BITAXE_SHARING_DB_PORT') ?: (getenv('BITAXE_DB_PORT') ?: 3306)),
            'database' => getenv('BITAXE_SHARING_DB_NAME') ?: (getenv('BITAXE_DB_NAME') ?: 'oc_masterdata'),
            'username' => getenv('BITAXE_SHARING_DB_USER') ?: (getenv('BITAXE_DB_USER') ?: 'oc_app'),
            // Priority: specific env -> common env -> local secret file (app/.db_password).
            'password' => $resolveDbPassword('BITAXE_SHARING_DB_PASSWORD'),
            'table' => 'share_records',
            'compress_payload' => true,
            'prune_probability' => 5,
            'prune_batch_size' => 5000,
        ],
    ],
    'logging' => [
        'driver' => 'db',
        'file_fallback_read' => true,
        'db' => [
            'engine' => getenv('BITAXE_LOGGING_DB_ENGINE') ?: (getenv('BITAXE_DB_ENGINE') ?: 'mysql'),
            'host' => getenv('BITAXE_LOGGING_DB_HOST') ?: (getenv('BITAXE_DB_HOST') ?: 'localhost'),
            'port' => (int)(getenv('BITAXE_LOGGING_DB_PORT') ?: (getenv('BITAXE_DB_PORT') ?: 3306)),
            'database' => getenv('BITAXE_LOGGING_DB_NAME') ?: (getenv('BITAXE_DB_NAME') ?: 'oc_masterdata'),
            'username' => getenv('BITAXE_LOGGING_DB_USER') ?: (getenv('BITAXE_DB_USER') ?: 'oc_app'),
            // Priority: specific env -> common env -> local secret file (app/.db_password).
            'password' => $resolveDbPassword('BITAXE_LOGGING_DB_PASSWORD'),
            'table' => 'usage_events',
            'prune_probability' => 5,
            'prune_batch_size' => 4000,
            'retention_days' => 365,
        ],
        'visitor_salt' => getenv('BITAXE_LOG_VISITOR_SALT') ?: (getenv('BITAXE_VISITOR_SALT') ?: 'CHANGE_THIS_TO_A_RANDOM_64_CHAR_SECRET'),
    ],
    'admin' => [
        'username' => getenv('BITAXE_ADMIN_USERNAME') ?: 'reis',
        'password' => '',
        'password_hash' => getenv('BITAXE_ADMIN_PASSWORD_HASH') ?: '$2y$12$LRXI7wB1xoC/n9gqqLgIduLNLmDI3QhBE1zJMllCTop.Mz4E5R56S',
        'require_password_hash_only' => true,
        'access_key' => getenv('BITAXE_ADMIN_ACCESS_KEY') ?: '',
        'allowed_ips' => [],
        'session_timeout_sec' => 30 * 24 * 3600,
        'login_rate_limit_requests' => 5,
        'login_rate_limit_window_sec' => 900,
        'global_login_rate_limit_requests' => 30,
        'global_login_rate_limit_window_sec' => 300,
    ],
    'server' => [
        'public_ip' => getenv('BITAXE_PUBLIC_IP') ?: '138.124.93.59',
    ],
    'security' => [
        'trust_proxy_headers' => true,
        'session_cookie_lifetime_sec' => 30 * 24 * 3600,
        'country_lookup_remote' => true,
        'country_lookup_timeout_sec' => 1.0,
        'country_lookup_cache_ttl_sec' => 30 * 24 * 3600,
        'country_lookup_fail_ttl_sec' => 600,
    ],
];
