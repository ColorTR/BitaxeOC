<?php

declare(strict_types=1);

use BitaxeOc\App\Security;
use BitaxeOc\App\OpsDbMetrics;
use BitaxeOc\App\OpsProcessMetrics;
use BitaxeOc\App\UsageLogger;

require_once __DIR__ . '/app/Security.php';
require_once __DIR__ . '/app/OpsDbMetrics.php';
require_once __DIR__ . '/app/OpsProcessMetrics.php';
require_once __DIR__ . '/app/UsageLogger.php';

function ipMatchesCidrPanel2(string $ip, string $cidr): bool
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

function isClientIpAllowedPanel2(string $clientIp, array $allowlist): bool
{
    if ($allowlist === []) {
        return true;
    }

    foreach ($allowlist as $entry) {
        $token = trim((string)$entry);
        if ($token === '') {
            continue;
        }
        if ($clientIp === $token) {
            return true;
        }
        if (ipMatchesCidrPanel2($clientIp, $token)) {
            return true;
        }
    }

    return false;
}

function h2(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function nfmt2(mixed $value, int $digits = 0): string
{
    if (!is_numeric($value)) {
        return '-';
    }
    $float = (float)$value;
    if (is_nan($float) || is_infinite($float)) {
        return '-';
    }

    return number_format($float, $digits, '.', ',');
}

function mbfmt2(mixed $bytes, int $digits = 2): string
{
    if (!is_numeric($bytes)) {
        return '-';
    }

    $mb = ((float)$bytes) / (1024 * 1024);
    if (is_nan($mb) || is_infinite($mb)) {
        return '-';
    }

    return nfmt2($mb, $digits) . ' MB';
}

function pctfmt2(?float $value, int $digits = 1): string
{
    if (!is_numeric($value)) {
        return '-';
    }
    $clamped = max(0.0, min(999.0, (float)$value));
    return nfmt2($clamped, $digits) . '%';
}

$config = require __DIR__ . '/app/Config.php';
$securityConfig = is_array($config['security'] ?? null) ? $config['security'] : [];
$adminConfig = is_array($config['admin'] ?? null) ? $config['admin'] : [];
$loggingConfig = is_array($config['logging'] ?? null) ? $config['logging'] : [];
$appVersion = (string)($config['app_version'] ?? 'v0');

Security::ensureRuntimeDirectories();

$adminSessionTimeoutForCookie = max(300, (int)($adminConfig['session_timeout_sec'] ?? 1800));
$effectiveSecurityConfig = $securityConfig;
$adminSessionName = trim((string)($adminConfig['session_name'] ?? ''));
if ($adminSessionName === '') {
    $adminSessionName = trim((string)($securityConfig['session_name'] ?? 'bitaxeoc_admin_sess'));
}
$adminCsrfSessionKey = trim((string)($adminConfig['csrf_session_key'] ?? ''));
if ($adminCsrfSessionKey === '') {
    $adminCsrfSessionKey = trim((string)($securityConfig['csrf_session_key'] ?? 'bitaxeoc_admin_csrf'));
}
$effectiveSecurityConfig['session_name'] = $adminSessionName !== '' ? $adminSessionName : 'bitaxeoc_admin_sess';
$effectiveSecurityConfig['csrf_session_key'] = $adminCsrfSessionKey !== '' ? $adminCsrfSessionKey : 'bitaxeoc_admin_csrf';
$adminSessionSameSite = trim((string)($adminConfig['session_cookie_samesite'] ?? ''));
if ($adminSessionSameSite !== '') {
    $effectiveSecurityConfig['session_cookie_samesite'] = $adminSessionSameSite;
}
$configuredCookieLifetime = max(0, (int)($securityConfig['session_cookie_lifetime_sec'] ?? 0));
if ($configuredCookieLifetime < $adminSessionTimeoutForCookie) {
    $effectiveSecurityConfig['session_cookie_lifetime_sec'] = $adminSessionTimeoutForCookie;
}

Security::startSession($effectiveSecurityConfig);
Security::setCommonHeaders();
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Robots-Tag: noindex, nofollow, noarchive');
header('Vary: Cookie');

$trustProxyHeaders = (bool)($securityConfig['trust_proxy_headers'] ?? false);
$clientIp = Security::getClientIp($trustProxyHeaders, $securityConfig);
$userAgent = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
$clientIdentity = $clientIp . '|' . substr(hash('sha256', $userAgent), 0, 24);

$csrfSessionKey = (string)($effectiveSecurityConfig['csrf_session_key'] ?? 'bitaxeoc_admin_csrf');
$csrfToken = Security::getOrCreateCsrfToken($csrfSessionKey);
$adminSessionKey = (string)($adminConfig['session_key'] ?? 'bitaxeoc_admin_auth');
$adminGateSessionKey = $adminSessionKey . '_gate';
$flashErrorSessionKey = $adminSessionKey . '_flash_error';
$expectedUsername = trim((string)($adminConfig['username'] ?? 'owner'));
$expectedPassword = (string)($adminConfig['password'] ?? 'CHANGE_THIS_ADMIN_PASSWORD');
$expectedPasswordHash = trim((string)($adminConfig['password_hash'] ?? ''));
$passwordHashInfo = password_get_info($expectedPasswordHash);
$hasPasswordHash = ($expectedPasswordHash !== '') && ((int)($passwordHashInfo['algo'] ?? 0) !== 0);
$requirePasswordHashOnly = (bool)($adminConfig['require_password_hash_only'] ?? true);
$sessionTimeoutSec = max(300, (int)($adminConfig['session_timeout_sec'] ?? 1800));
$bindSessionToIp = (bool)($adminConfig['bind_session_to_ip'] ?? false);
$bindSessionToUserAgent = (bool)($adminConfig['bind_session_to_user_agent'] ?? true);
$accessKey = trim((string)($adminConfig['access_key'] ?? ''));

$allowedIpsRaw = $adminConfig['allowed_ips'] ?? [];
if (is_string($allowedIpsRaw)) {
    $allowedIpsRaw = explode(',', $allowedIpsRaw);
}
$allowedIps = [];
if (is_array($allowedIpsRaw)) {
    foreach ($allowedIpsRaw as $item) {
        $token = trim((string)$item);
        if ($token !== '') {
            $allowedIps[] = $token;
        }
    }
}

$loginRateLimitRequests = max(1, (int)($adminConfig['login_rate_limit_requests'] ?? 12));
$loginRateLimitWindow = max(60, (int)($adminConfig['login_rate_limit_window_sec'] ?? 900));
$globalLoginRateLimitRequests = max(1, (int)($adminConfig['global_login_rate_limit_requests'] ?? 80));
$globalLoginRateLimitWindow = max(60, (int)($adminConfig['global_login_rate_limit_window_sec'] ?? 300));
$serverStatusRateLimitRequests = max(1, (int)($adminConfig['server_status_rate_limit_requests'] ?? 120));
$serverStatusRateLimitWindow = max(30, (int)($adminConfig['server_status_rate_limit_window_sec'] ?? 60));

$defaultCredentialMode = (
    $expectedUsername === '' ||
    (
        $requirePasswordHashOnly
            ? !$hasPasswordHash
            : (
                !$hasPasswordHash &&
                ($expectedPassword === '' || str_starts_with($expectedPassword, 'CHANGE_THIS_'))
            )
    )
);

$rememberCookieName = trim((string)($adminConfig['remember_cookie_name'] ?? 'bitaxeoc_admin_remember'));
if ($rememberCookieName === '' || !preg_match('/^[A-Za-z0-9_.-]{4,64}$/', $rememberCookieName)) {
    $rememberCookieName = 'bitaxeoc_admin_remember';
}
$rememberTtlSec = max(86400, (int)($adminConfig['remember_ttl_sec'] ?? (30 * 24 * 3600)));
$sessionTouchIntervalSec = max(30, min(900, (int)($adminConfig['session_touch_interval_sec'] ?? 60)));
$rememberRefreshThresholdSec = (int)($adminConfig['remember_refresh_threshold_sec'] ?? (24 * 3600));
if ($rememberRefreshThresholdSec <= 0) {
    $rememberRefreshThresholdSec = 24 * 3600;
}
if ($rememberRefreshThresholdSec >= $rememberTtlSec) {
    $rememberRefreshThresholdSec = max(3600, intdiv($rememberTtlSec, 2));
}

$isHttpsRequest = (
    (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https')
    || (strtolower((string)($_SERVER['REQUEST_SCHEME'] ?? '')) === 'https')
    || ((string)($_SERVER['SERVER_PORT'] ?? '') === '443')
);

$rememberSecret = hash(
    'sha256',
    'bitaxeoc-admin-remember|'
    . $expectedUsername . '|'
    . $expectedPasswordHash . '|'
    . $expectedPassword . '|'
    . (string)($adminConfig['session_key'] ?? 'bitaxeoc_admin_auth')
);

$base64UrlEncode = static function (string $raw): string {
    return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
};

$base64UrlDecode = static function (string $encoded): string {
    $normalized = strtr($encoded, '-_', '+/');
    $padding = strlen($normalized) % 4;
    if ($padding > 0) {
        $normalized .= str_repeat('=', 4 - $padding);
    }
    $decoded = base64_decode($normalized, true);
    return is_string($decoded) ? $decoded : '';
};

$buildRememberToken = static function (string $username, int $expiresAt, string $secret) use ($base64UrlEncode): string {
    $payloadRaw = json_encode([
        'u' => $username,
        'exp' => $expiresAt,
        'v' => 1,
    ], JSON_UNESCAPED_SLASHES);
    if (!is_string($payloadRaw) || $payloadRaw === '') {
        return '';
    }
    $payload = $base64UrlEncode($payloadRaw);
    $signature = hash_hmac('sha256', $payload, $secret);
    return $payload . '.' . $signature;
};

$parseRememberToken = static function (string $token, string $secret) use ($base64UrlDecode): ?array {
    $parts = explode('.', trim($token), 2);
    if (count($parts) !== 2) {
        return null;
    }
    [$payload, $signature] = $parts;
    if ($payload === '' || strlen($signature) !== 64 || !preg_match('/^[a-f0-9]{64}$/', $signature)) {
        return null;
    }

    $expected = hash_hmac('sha256', $payload, $secret);
    if (!hash_equals($expected, $signature)) {
        return null;
    }

    $decodedRaw = $base64UrlDecode($payload);
    if ($decodedRaw === '') {
        return null;
    }

    $decoded = json_decode($decodedRaw, true);
    if (!is_array($decoded)) {
        return null;
    }

    return [
        'u' => trim((string)($decoded['u'] ?? '')),
        'exp' => (int)($decoded['exp'] ?? 0),
        'v' => (int)($decoded['v'] ?? 0),
    ];
};

$setRememberCookie = static function (string $name, string $value, int $ttlSec, bool $secure): void {
    if ($name === '' || $value === '') {
        return;
    }
    setcookie($name, $value, [
        'expires' => time() + max(60, $ttlSec),
        'path' => '/',
        'domain' => '',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
};

$clearRememberCookie = static function (string $name, bool $secure): void {
    if ($name === '') {
        return;
    }
    setcookie($name, '', [
        'expires' => time() - 3600,
        'path' => '/',
        'domain' => '',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
};

$rememberCookieUpdated = false;
$rememberRaw = trim((string)($_COOKIE[$rememberCookieName] ?? ''));
$rememberData = null;
if ($rememberRaw !== '') {
    $parsedRemember = $parseRememberToken($rememberRaw, $rememberSecret);
    if (is_array($parsedRemember)) {
        $rememberData = $parsedRemember;
    }
}

$errorMessage = '';
$redirectSelfUrl = (string)($_SERVER['PHP_SELF'] ?? '/ops-panel.php');
if ($accessKey !== '') {
    $incomingGateKey = trim((string)($_GET['k'] ?? ''));
    if ($incomingGateKey !== '') {
        $redirectSelfUrl .= '?k=' . rawurlencode($incomingGateKey);
    }
}

if ($accessKey !== '') {
    $providedAccessKey = trim((string)($_GET['k'] ?? ''));
    if ($providedAccessKey !== '' && hash_equals($accessKey, $providedAccessKey)) {
        $_SESSION[$adminGateSessionKey] = true;
    }

    if (empty($_SESSION[$adminGateSessionKey])) {
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Not Found';
        exit;
    }
}

if (!isClientIpAllowedPanel2($clientIp, $allowedIps)) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Forbidden';
    exit;
}

$adminState = isset($_SESSION[$adminSessionKey]) && is_array($_SESSION[$adminSessionKey])
    ? $_SESSION[$adminSessionKey]
    : [];

$isAuthenticated = !empty($adminState['ok']);

$buildAdminFingerprint = static function (string $ip, string $ua, string $sessionKey, bool $bindIp, bool $bindUa): string {
    $parts = ['v2', 'sess:' . $sessionKey];
    if ($bindIp) {
        $parts[] = 'ip:' . strtolower(trim($ip));
    }
    if ($bindUa) {
        $parts[] = 'ua:' . substr(hash('sha256', $ua), 0, 40);
    }
    return hash('sha256', implode('|', $parts));
};

if ($isAuthenticated) {
    $sessionFingerprint = (string)($adminState['fp'] ?? '');
    $expectedFingerprint = $buildAdminFingerprint(
        $clientIp,
        $userAgent,
        $adminSessionKey,
        $bindSessionToIp,
        $bindSessionToUserAgent
    );
    $lastSeen = (int)($adminState['last_seen'] ?? 0);

    if ($sessionFingerprint === '' || !hash_equals($expectedFingerprint, $sessionFingerprint)) {
        $isAuthenticated = false;
        unset($_SESSION[$adminSessionKey]);
        $adminState = [];
        $errorMessage = 'Session verification failed. Please login again.';
    } elseif ($lastSeen <= 0 || (time() - $lastSeen) > $sessionTimeoutSec) {
        $isAuthenticated = false;
        unset($_SESSION[$adminSessionKey]);
        $adminState = [];
        $errorMessage = 'Session expired. Please login again.';
    } else {
        $now = time();
        if (($now - $lastSeen) >= $sessionTouchIntervalSec) {
            $_SESSION[$adminSessionKey]['last_seen'] = $now;
        }
        $adminState = $_SESSION[$adminSessionKey];
    }
}

if (!$isAuthenticated && !$defaultCredentialMode) {
    if ($rememberRaw !== '') {
        $rememberData = $parseRememberToken($rememberRaw, $rememberSecret);
        $rememberUser = is_array($rememberData) ? (string)($rememberData['u'] ?? '') : '';
        $rememberExp = is_array($rememberData) ? (int)($rememberData['exp'] ?? 0) : 0;

        if (
            $rememberUser !== '' &&
            $rememberExp > time() &&
            $expectedUsername !== '' &&
            hash_equals($expectedUsername, $rememberUser)
        ) {
            session_regenerate_id(true);
            $now = time();
            $_SESSION[$adminSessionKey] = [
                'ok' => true,
                'username' => $expectedUsername,
                'at' => $now,
                'last_seen' => $now,
                'fp' => $buildAdminFingerprint(
                    $clientIp,
                    $userAgent,
                    $adminSessionKey,
                    $bindSessionToIp,
                    $bindSessionToUserAgent
                ),
                'ip_hash' => substr(hash('sha256', strtolower($clientIp)), 0, 20),
            ];
            $isAuthenticated = true;
            $adminState = $_SESSION[$adminSessionKey];
            $errorMessage = '';

            $rememberRemainingSec = max(0, $rememberExp - $now);
            if ($rememberRemainingSec <= $rememberRefreshThresholdSec) {
                $renewedToken = $buildRememberToken($expectedUsername, $now + $rememberTtlSec, $rememberSecret);
                if ($renewedToken !== '') {
                    $setRememberCookie($rememberCookieName, $renewedToken, $rememberTtlSec, $isHttpsRequest);
                    $rememberCookieUpdated = true;
                }
            }
        } else {
            $clearRememberCookie($rememberCookieName, $isHttpsRequest);
            $rememberCookieUpdated = true;
        }
    }
}

if ($isAuthenticated && !$defaultCredentialMode && $expectedUsername !== '') {
    $rememberUser = is_array($rememberData) ? (string)($rememberData['u'] ?? '') : '';
    $rememberExp = is_array($rememberData) ? (int)($rememberData['exp'] ?? 0) : 0;
    $rememberMatchesCurrentUser = (
        $rememberUser !== '' &&
        $expectedUsername !== '' &&
        hash_equals($expectedUsername, $rememberUser) &&
        $rememberExp > time()
    );
    $rememberRemainingSec = $rememberMatchesCurrentUser ? max(0, $rememberExp - time()) : 0;
    $shouldRefreshRemember = (!$rememberMatchesCurrentUser) || ($rememberRemainingSec <= $rememberRefreshThresholdSec);
    if ($shouldRefreshRemember && !$rememberCookieUpdated) {
        $renewedRememberToken = $buildRememberToken($expectedUsername, time() + $rememberTtlSec, $rememberSecret);
        if ($renewedRememberToken !== '') {
            $setRememberCookie($rememberCookieName, $renewedRememberToken, $rememberTtlSec, $isHttpsRequest);
            $rememberCookieUpdated = true;
        }
    }
}

if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST') {
    $postErrorMessage = '';
    try {
        Security::assertSameOriginRequest();
        $incomingCsrf = isset($_POST['csrf_token']) ? (string)$_POST['csrf_token'] : '';
        Security::assertCsrfToken($csrfSessionKey, $incomingCsrf);

        $action = isset($_POST['action']) ? (string)$_POST['action'] : '';
        if ($action === 'logout') {
            unset($_SESSION[$adminSessionKey]);
            session_regenerate_id(true);
            $clearRememberCookie($rememberCookieName, $isHttpsRequest);
            $isAuthenticated = false;
            $adminState = [];
        } elseif ($action === 'login') {
            Security::applyRateLimitConfig(
                $securityConfig,
                'admin_panel_login',
                $loginRateLimitRequests,
                $loginRateLimitWindow,
                $clientIdentity
            );
            Security::applyRateLimitConfig(
                $securityConfig,
                'admin_panel_login_global',
                $globalLoginRateLimitRequests,
                $globalLoginRateLimitWindow,
                'global'
            );

            $username = trim((string)($_POST['username'] ?? ''));
            $password = (string)($_POST['password'] ?? '');

            if ($defaultCredentialMode) {
                $postErrorMessage = 'Admin credentials are not configured in app/Config.php.';
                $isAuthenticated = false;
                unset($_SESSION[$adminSessionKey]);
            } else {
                $validUser = ($expectedUsername !== '' && hash_equals($expectedUsername, $username));
                $validPass = false;
                if ($hasPasswordHash) {
                    $validPass = password_verify($password, $expectedPasswordHash);
                } elseif (!$requirePasswordHashOnly) {
                    $validPass = ($expectedPassword !== '' && hash_equals($expectedPassword, $password));
                }

                if ($validUser && $validPass) {
                    session_regenerate_id(true);
                    $now = time();
                    $_SESSION[$adminSessionKey] = [
                        'ok' => true,
                        'username' => $expectedUsername,
                        'at' => $now,
                        'last_seen' => $now,
                        'fp' => $buildAdminFingerprint(
                            $clientIp,
                            $userAgent,
                            $adminSessionKey,
                            $bindSessionToIp,
                            $bindSessionToUserAgent
                        ),
                        'ip_hash' => substr(hash('sha256', strtolower($clientIp)), 0, 20),
                    ];
                    $isAuthenticated = true;
                    $adminState = $_SESSION[$adminSessionKey];
                    $rememberToken = $buildRememberToken($expectedUsername, $now + $rememberTtlSec, $rememberSecret);
                    $setRememberCookie($rememberCookieName, $rememberToken, $rememberTtlSec, $isHttpsRequest);
                } else {
                    usleep(random_int(180000, 420000));
                    $postErrorMessage = 'Invalid username or password.';
                    $isAuthenticated = false;
                    unset($_SESSION[$adminSessionKey]);
                }
            }
        } else {
            $postErrorMessage = 'Invalid action.';
        }
    } catch (Throwable $error) {
        $postErrorMessage = $error->getMessage() !== '' ? $error->getMessage() : 'Request failed.';
    }

    if ($postErrorMessage !== '') {
        $_SESSION[$flashErrorSessionKey] = $postErrorMessage;
    }
    unset($_SESSION[$csrfSessionKey]);
    header('Location: ' . $redirectSelfUrl, true, 303);
    exit;
}

if (isset($_SESSION[$flashErrorSessionKey]) && is_string($_SESSION[$flashErrorSessionKey])) {
    $errorMessage = (string)$_SESSION[$flashErrorSessionKey];
    unset($_SESSION[$flashErrorSessionKey]);
}

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function nfmt(mixed $value, int $digits = 0): string
{
    if (!is_numeric($value)) {
        return '-';
    }
    $float = (float)$value;
    if (is_nan($float) || is_infinite($float)) {
        return '-';
    }

    return number_format($float, $digits, '.', ',');
}

function mbfmt(mixed $bytes, int $digits = 2): string
{
    if (!is_numeric($bytes)) {
        return '-';
    }

    $mb = ((float)$bytes) / (1024 * 1024);
    if (is_nan($mb) || is_infinite($mb)) {
        return '-';
    }

    return nfmt($mb, $digits) . ' MB';
}

function msfmt(mixed $ms): string
{
    if (!is_numeric($ms)) {
        return '-';
    }
    $value = (float)$ms;
    if (is_nan($value) || is_infinite($value)) {
        return '-';
    }
    if ($value < 1) {
        return '0 ms';
    }
    return nfmt($value, $value < 100 ? 1 : 0) . ' ms';
}

function countryLabel(string $code): string
{
    static $labels = [
        'TR' => 'Turkiye',
        'US' => 'United States',
        'DE' => 'Germany',
        'GB' => 'United Kingdom',
        'RU' => 'Russia',
        'CN' => 'China',
        'FR' => 'France',
        'NL' => 'Netherlands',
        'IN' => 'India',
        'BR' => 'Brazil',
        'CA' => 'Canada',
        'AU' => 'Australia',
        'JP' => 'Japan',
        'KR' => 'South Korea',
        'ES' => 'Spain',
        'IT' => 'Italy',
        'PL' => 'Poland',
        'UA' => 'Ukraine',
        'ID' => 'Indonesia',
        'VN' => 'Vietnam',
        'MX' => 'Mexico',
        'AR' => 'Argentina',
        'AE' => 'United Arab Emirates',
        'SA' => 'Saudi Arabia',
        'EG' => 'Egypt',
        'ZZ' => 'Unknown',
    ];
    $normalized = strtoupper(trim($code));
    if ($normalized === '' || !preg_match('/^[A-Z]{2}$/', $normalized)) {
        return 'Unknown';
    }
    return $labels[$normalized] ?? $normalized;
}

function languageLabel(string $code): string
{
    static $labels = [
        'tr' => 'Turkish',
        'en' => 'English',
        'ar' => 'Arabic',
        'de' => 'German',
        'fr' => 'French',
        'es' => 'Spanish',
        'it' => 'Italian',
        'ru' => 'Russian',
        'pt' => 'Portuguese',
        'pl' => 'Polish',
        'nl' => 'Dutch',
        'id' => 'Indonesian',
        'zh' => 'Chinese',
        'ja' => 'Japanese',
        'ko' => 'Korean',
        'unknown' => 'Unknown',
    ];
    $normalized = strtolower(trim($code));
    if ($normalized === '' || $normalized === 'auto') {
        return 'Unknown';
    }
    if (str_contains($normalized, '-')) {
        $normalized = explode('-', $normalized, 2)[0];
    }
    return $labels[$normalized] ?? strtoupper($normalized);
}

function sizefmt(mixed $bytes, int $digits = 2): string
{
    if (!is_numeric($bytes)) {
        return '-';
    }
    $value = (float)$bytes;
    if (is_nan($value) || is_infinite($value) || $value < 0) {
        return '-';
    }

    $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
    $idx = 0;
    while ($value >= 1024 && $idx < (count($units) - 1)) {
        $value /= 1024;
        $idx++;
    }

    $precision = $idx === 0 ? 0 : $digits;
    return nfmt($value, $precision) . ' ' . $units[$idx];
}

function pctfmt(?float $value, int $digits = 1): string
{
    if (!is_numeric($value)) {
        return '-';
    }
    $clamped = max(0.0, min(999.0, (float)$value));
    return nfmt($clamped, $digits) . '%';
}

function uptimefmt(?int $seconds): string
{
    if (!is_int($seconds) || $seconds <= 0) {
        return '-';
    }

    $days = intdiv($seconds, 86400);
    $hours = intdiv($seconds % 86400, 3600);
    $mins = intdiv($seconds % 3600, 60);

    if ($days > 0) {
        return sprintf('%dd %02dh %02dm', $days, $hours, $mins);
    }
    return sprintf('%02dh %02dm', $hours, $mins);
}

function toneByPct(float $pct, float $warn = 75.0, float $bad = 90.0): string
{
    if ($pct >= $bad) {
        return 'tone-bad';
    }
    if ($pct >= $warn) {
        return 'tone-warn';
    }
    return 'tone-good';
}

function readMemInfoLinux(): array
{
    $path = '/proc/meminfo';
    if (!is_readable($path)) {
        return [];
    }

    $result = [];
    $lines = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!is_array($lines)) {
        return [];
    }

    foreach ($lines as $line) {
        if (!is_string($line)) {
            continue;
        }
        if (!preg_match('/^([A-Za-z_]+):\s+([0-9]+)/', trim($line), $matches)) {
            continue;
        }
        $key = (string)$matches[1];
        $kb = (int)$matches[2];
        $result[$key] = max(0, $kb * 1024);
    }

    return $result;
}

function readCpuUsageLinux(): ?float
{
    $path = '/proc/stat';
    if (!is_readable($path)) {
        return null;
    }

    $line = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!is_array($line) || $line === []) {
        return null;
    }

    $cpuLine = '';
    foreach ($line as $row) {
        if (is_string($row) && str_starts_with($row, 'cpu ')) {
            $cpuLine = trim($row);
            break;
        }
    }
    if ($cpuLine === '') {
        return null;
    }

    $parts = preg_split('/\s+/', $cpuLine);
    if (!is_array($parts) || count($parts) < 5) {
        return null;
    }

    $stats = array_slice($parts, 1);
    $total = 0.0;
    foreach ($stats as $value) {
        if (is_numeric($value)) {
            $total += (float)$value;
        }
    }
    if ($total <= 0.0) {
        return null;
    }

    $idle = 0.0;
    if (isset($stats[3]) && is_numeric($stats[3])) {
        $idle += (float)$stats[3];
    }
    if (isset($stats[4]) && is_numeric($stats[4])) {
        $idle += (float)$stats[4];
    }

    $statePath = __DIR__ . '/tmp/ops_cpu_cache.json';
    $now = microtime(true);
    $prevTotal = 0.0;
    $prevIdle = 0.0;

    if (is_readable($statePath)) {
        $raw = @file_get_contents($statePath);
        if (is_string($raw) && $raw !== '') {
            $cached = json_decode($raw, true);
            if (is_array($cached)) {
                $prevTotal = is_numeric($cached['total'] ?? null) ? (float)$cached['total'] : 0.0;
                $prevIdle = is_numeric($cached['idle'] ?? null) ? (float)$cached['idle'] : 0.0;
            }
        }
    }

    @file_put_contents(
        $statePath,
        json_encode(['ts' => $now, 'total' => $total, 'idle' => $idle], JSON_UNESCAPED_SLASHES),
        LOCK_EX
    );

    if ($prevTotal <= 0.0 || $total <= $prevTotal) {
        return null;
    }

    $deltaTotal = $total - $prevTotal;
    $deltaIdle = max(0.0, $idle - $prevIdle);
    if ($deltaTotal <= 0.0) {
        return null;
    }

    $busyPct = (($deltaTotal - $deltaIdle) / $deltaTotal) * 100.0;
    return max(0.0, min(100.0, $busyPct));
}

function detectCpuCoreCount(): int
{
    $cpuInfoPath = '/proc/cpuinfo';
    if (is_readable($cpuInfoPath)) {
        $lines = @file($cpuInfoPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (is_array($lines)) {
            $cores = 0;
            foreach ($lines as $line) {
                if (is_string($line) && preg_match('/^processor\\s*:/i', trim($line))) {
                    $cores++;
                }
            }
            if ($cores > 0) {
                return $cores;
            }
        }
    }

    return 1;
}

function detectServerIp(): string
{
    $candidates = [];
    $serverAddr = trim((string)($_SERVER['SERVER_ADDR'] ?? ''));
    if ($serverAddr !== '') {
        $candidates[] = $serverAddr;
    }

    $hostName = trim((string)(gethostname() ?: ''));
    if ($hostName !== '') {
        $resolved = @gethostbyname($hostName);
        if (is_string($resolved) && $resolved !== '') {
            $candidates[] = $resolved;
        }
    }

    $unameHost = trim((string)php_uname('n'));
    if ($unameHost !== '' && $unameHost !== $hostName) {
        $resolved = @gethostbyname($unameHost);
        if (is_string($resolved) && $resolved !== '') {
            $candidates[] = $resolved;
        }
    }

    $fallback = '';
    foreach ($candidates as $candidateRaw) {
        $candidate = trim((string)$candidateRaw);
        if (!filter_var($candidate, FILTER_VALIDATE_IP)) {
            continue;
        }
        if ($candidate !== '127.0.0.1' && $candidate !== '::1') {
            return $candidate;
        }
        if ($fallback === '') {
            $fallback = $candidate;
        }
    }

    return $fallback !== '' ? $fallback : '-';
}

function collectTopProcessMetrics(int $limit = 12, int $scanRows = 220): array
{
    return OpsProcessMetrics::collectTopProcessMetrics($limit, $scanRows);
}

function collectTopProcessMetricsCached(array $config): array
{
    return OpsProcessMetrics::collectTopProcessMetricsCached($config);
}

function normalizeOpsDbTableName(string $value, string $fallback): string
{
    return OpsDbMetrics::normalizeTableName($value, $fallback);
}

function buildOpsDbCandidates(array $config): array
{
    return OpsDbMetrics::buildCandidates($config);
}

function createOpsDbConnectionMeta(array $config): ?array
{
    return OpsDbMetrics::createConnectionMeta($config);
}

function queryOpsDbMetricsMysql(PDO $pdo, string $schema, string $usageTable, string $shareTable): array
{
    return OpsDbMetrics::queryMysql($pdo, $schema, $usageTable, $shareTable);
}

function queryOpsDbMetricsPgsql(PDO $pdo, string $schema, string $usageTable, string $shareTable): array
{
    return OpsDbMetrics::queryPgsql($pdo, $schema, $usageTable, $shareTable);
}

function collectServerStatus(array $config): array
{
    $adminConfig = is_array(($config['admin'] ?? null)) ? $config['admin'] : [];
    $processRefreshSec = max(1.0, min(60.0, (float)($adminConfig['server_status_process_refresh_sec'] ?? 2.0)));
    $configuredServerIp = trim((string)($config['server']['public_ip'] ?? ''));
    $serverIp = filter_var($configuredServerIp, FILTER_VALIDATE_IP)
        ? $configuredServerIp
        : detectServerIp();

    $status = [
        'hostname' => trim((string)(gethostname() ?: php_uname('n'))),
        'serverIp' => $serverIp,
        'phpVersion' => PHP_VERSION,
        'serverSoftware' => trim((string)($_SERVER['SERVER_SOFTWARE'] ?? '')),
        'uptimeSec' => 0,
        'cpuCores' => detectCpuCoreCount(),
        'load1' => null,
        'load5' => null,
        'load15' => null,
        'cpuLoadPct' => null,
        'cpuSource' => 'load',
        'memoryTotalBytes' => 0,
        'memoryAvailableBytes' => 0,
        'memoryUsedBytes' => 0,
        'memoryUsedPct' => null,
        'diskMount' => '/',
        'diskTotalBytes' => 0,
        'diskFreeBytes' => 0,
        'diskUsedBytes' => 0,
        'diskUsedPct' => null,
        'dbSchema' => '',
        'dbVersion' => '',
        'dbTotalBytes' => 0,
        'dbUsageTable' => 'usage_events',
        'dbShareTable' => 'share_records',
        'dbUsageEventsBytes' => 0,
        'dbShareRecordsBytes' => 0,
        'dbUsageEventsRows' => 0,
        'dbShareRecordsRows' => 0,
        'processRefreshSec' => $processRefreshSec,
        'processCapturedAt' => '',
        'topProcesses' => [],
        'loggerFallbackActive' => false,
        'loggerFallbackAt' => '',
        'loggerFallbackContext' => '',
        'loggerFallbackMessage' => '',
    ];

    $uptimePath = '/proc/uptime';
    if (is_readable($uptimePath)) {
        $raw = @file_get_contents($uptimePath);
        if (is_string($raw) && trim($raw) !== '') {
            $parts = preg_split('/\\s+/', trim($raw));
            $first = is_array($parts) ? ($parts[0] ?? '') : '';
            if (is_numeric($first)) {
                $status['uptimeSec'] = max(0, (int)floor((float)$first));
            }
        }
    }

    $loads = function_exists('sys_getloadavg') ? sys_getloadavg() : false;
    if (is_array($loads) && count($loads) >= 3) {
        $status['load1'] = (float)$loads[0];
        $status['load5'] = (float)$loads[1];
        $status['load15'] = (float)$loads[2];
    }

    $cpuUsagePct = readCpuUsageLinux();
    if (is_numeric($cpuUsagePct)) {
        $status['cpuLoadPct'] = max(0.0, min(100.0, (float)$cpuUsagePct));
        $status['cpuSource'] = 'usage';
    } elseif (is_numeric($status['load1'] ?? null)) {
        $cores = max(1, (int)$status['cpuCores']);
        $status['cpuLoadPct'] = max(0.0, (($status['load1'] ?? 0.0) / $cores) * 100.0);
        $status['cpuSource'] = 'load';
    }

    $mem = readMemInfoLinux();
    $memTotal = max(0, (int)($mem['MemTotal'] ?? 0));
    $memAvailable = max(
        0,
        (int)($mem['MemAvailable'] ?? ((int)($mem['MemFree'] ?? 0) + (int)($mem['Buffers'] ?? 0) + (int)($mem['Cached'] ?? 0)))
    );
    if ($memTotal > 0) {
        $memUsed = max(0, $memTotal - $memAvailable);
        $status['memoryTotalBytes'] = $memTotal;
        $status['memoryAvailableBytes'] = $memAvailable;
        $status['memoryUsedBytes'] = $memUsed;
        $status['memoryUsedPct'] = ($memUsed / $memTotal) * 100.0;
    }

    $diskTotal = @disk_total_space('/');
    $diskFree = @disk_free_space('/');
    if (is_numeric($diskTotal) && is_numeric($diskFree) && (float)$diskTotal > 0) {
        $diskTotalBytes = (int)round((float)$diskTotal);
        $diskFreeBytes = max(0, (int)round((float)$diskFree));
        $diskUsedBytes = max(0, $diskTotalBytes - $diskFreeBytes);
        $status['diskTotalBytes'] = $diskTotalBytes;
        $status['diskFreeBytes'] = $diskFreeBytes;
        $status['diskUsedBytes'] = $diskUsedBytes;
        $status['diskUsedPct'] = ($diskUsedBytes / $diskTotalBytes) * 100.0;
    }

    $dbRefreshSec = max(1.0, min(60.0, (float)($adminConfig['server_status_db_refresh_sec'] ?? 5.0)));
    $dbCachePath = __DIR__ . '/tmp/ops_db_status_cache.json';
    $dbCacheApplied = false;
    if (is_readable($dbCachePath)) {
        $rawCache = @file_get_contents($dbCachePath);
        if (is_string($rawCache) && $rawCache !== '') {
            $decodedCache = json_decode($rawCache, true);
            if (is_array($decodedCache)) {
                $cacheTs = is_numeric($decodedCache['ts'] ?? null) ? (float)$decodedCache['ts'] : 0.0;
                $cacheData = is_array($decodedCache['data'] ?? null) ? $decodedCache['data'] : [];
                if ($cacheTs > 0 && (microtime(true) - $cacheTs) <= $dbRefreshSec && $cacheData !== []) {
                    $status['dbSchema'] = (string)($cacheData['dbSchema'] ?? '');
                    $status['dbVersion'] = (string)($cacheData['dbVersion'] ?? '');
                    $status['dbTotalBytes'] = max(0, (int)($cacheData['dbTotalBytes'] ?? 0));
                    $status['dbUsageEventsBytes'] = max(0, (int)($cacheData['dbUsageEventsBytes'] ?? 0));
                    $status['dbShareRecordsBytes'] = max(0, (int)($cacheData['dbShareRecordsBytes'] ?? 0));
                    $status['dbUsageEventsRows'] = max(0, (int)($cacheData['dbUsageEventsRows'] ?? 0));
                    $status['dbShareRecordsRows'] = max(0, (int)($cacheData['dbShareRecordsRows'] ?? 0));
                    $dbCacheApplied = true;
                }
            }
        }
    }

    if (!$dbCacheApplied) {
        try {
            $dbMeta = createOpsDbConnectionMeta($config);
            if (is_array($dbMeta) && ($dbMeta['pdo'] ?? null) instanceof PDO) {
                /** @var PDO $pdo */
                $pdo = $dbMeta['pdo'];
                $engine = strtolower(trim((string)($dbMeta['engine'] ?? 'mysql')));
                $usageTable = normalizeOpsDbTableName((string)($dbMeta['usageTable'] ?? 'usage_events'), 'usage_events');
                $shareTable = normalizeOpsDbTableName((string)($dbMeta['shareTable'] ?? 'share_records'), 'share_records');
                $status['dbUsageTable'] = $usageTable;
                $status['dbShareTable'] = $shareTable;

                if ($engine === 'pgsql') {
                    $dbName = (string)($pdo->query('SELECT current_database()')->fetchColumn() ?: '');
                    $schema = (string)($pdo->query('SELECT current_schema()')->fetchColumn() ?: 'public');
                    $status['dbSchema'] = $dbName !== '' ? $dbName : $schema;
                    $status['dbVersion'] = (string)($pdo->query('SELECT version()')->fetchColumn() ?: '');
                    $metrics = queryOpsDbMetricsPgsql($pdo, $schema, $usageTable, $shareTable);
                } else {
                    $schema = (string)($pdo->query('SELECT DATABASE()')->fetchColumn() ?: '');
                    if ($schema === '') {
                        $schema = trim((string)($dbMeta['defaultDatabase'] ?? ''));
                    }
                    $status['dbSchema'] = $schema;
                    $status['dbVersion'] = (string)($pdo->query('SELECT VERSION()')->fetchColumn() ?: '');
                    $metrics = queryOpsDbMetricsMysql($pdo, $schema, $usageTable, $shareTable);
                }

                $status['dbTotalBytes'] = max(0, (int)($metrics['dbTotalBytes'] ?? 0));
                $status['dbUsageEventsBytes'] = max(0, (int)($metrics['dbUsageEventsBytes'] ?? 0));
                $status['dbShareRecordsBytes'] = max(0, (int)($metrics['dbShareRecordsBytes'] ?? 0));
                $status['dbUsageEventsRows'] = max(0, (int)($metrics['dbUsageEventsRows'] ?? 0));
                $status['dbShareRecordsRows'] = max(0, (int)($metrics['dbShareRecordsRows'] ?? 0));

                $cachePayload = [
                    'ts' => microtime(true),
                    'data' => [
                        'dbSchema' => $status['dbSchema'],
                        'dbVersion' => $status['dbVersion'],
                        'dbTotalBytes' => $status['dbTotalBytes'],
                        'dbUsageEventsBytes' => $status['dbUsageEventsBytes'],
                        'dbShareRecordsBytes' => $status['dbShareRecordsBytes'],
                        'dbUsageEventsRows' => $status['dbUsageEventsRows'],
                        'dbShareRecordsRows' => $status['dbShareRecordsRows'],
                    ],
                ];
                $encodedCache = json_encode($cachePayload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                if (is_string($encodedCache) && $encodedCache !== '') {
                    @file_put_contents($dbCachePath, $encodedCache, LOCK_EX);
                }
            }
        } catch (Throwable $error) {
            error_log('[bitaxe-oc] ops-panel: db status fetch failed - ' . $error->getMessage());
            // Keep status resilient; panel should continue even if DB stats fail.
        }
    }

    try {
        $proc = collectTopProcessMetricsCached($config);
        if (is_array($proc)) {
            $status['processCapturedAt'] = (string)($proc['capturedAt'] ?? '');
            $status['topProcesses'] = is_array($proc['topProcesses'] ?? null) ? $proc['topProcesses'] : [];
        }
    } catch (Throwable $error) {
        error_log('[bitaxe-oc] ops-panel: process status fetch failed - ' . $error->getMessage());
        // Keep status resilient; process metrics are optional diagnostics.
    }

    $loggerFallbackPath = __DIR__ . '/tmp/usage_logger_fallback.json';
    if (is_readable($loggerFallbackPath)) {
        $rawFallback = @file_get_contents($loggerFallbackPath);
        if (is_string($rawFallback) && $rawFallback !== '') {
            $decodedFallback = json_decode($rawFallback, true);
            if (is_array($decodedFallback)) {
                $fallbackTs = max(0, (int)($decodedFallback['ts'] ?? 0));
                if ($fallbackTs > 0 && (time() - $fallbackTs) <= 86400) {
                    $status['loggerFallbackActive'] = true;
                    $status['loggerFallbackAt'] = gmdate('c', $fallbackTs);
                    $status['loggerFallbackContext'] = (string)($decodedFallback['context'] ?? '');
                    $status['loggerFallbackMessage'] = (string)($decodedFallback['message'] ?? '');
                }
            }
        }
    }

    return $status;
}

function buildServerStatusView(array $serverStatus): array
{
    $serverDiskPct = is_numeric($serverStatus['diskUsedPct'] ?? null)
        ? max(0.0, min(100.0, (float)$serverStatus['diskUsedPct']))
        : 0.0;
    $serverRamPct = is_numeric($serverStatus['memoryUsedPct'] ?? null)
        ? max(0.0, min(100.0, (float)$serverStatus['memoryUsedPct']))
        : 0.0;
    $serverCpuPctRaw = is_numeric($serverStatus['cpuLoadPct'] ?? null)
        ? max(0.0, (float)$serverStatus['cpuLoadPct'])
        : 0.0;
    $serverCpuPct = min(100.0, $serverCpuPctRaw);
    $serverDbOnDiskPct = ((int)($serverStatus['diskTotalBytes'] ?? 0) > 0)
        ? min(100.0, max(0.0, (((float)($serverStatus['dbTotalBytes'] ?? 0) / (float)$serverStatus['diskTotalBytes']) * 100.0)))
        : 0.0;

    $serverDiskTone = toneByPct($serverDiskPct, 75.0, 90.0);
    $serverRamTone = toneByPct($serverRamPct, 78.0, 92.0);
    $serverCpuTone = toneByPct($serverCpuPctRaw, 70.0, 90.0);
    $serverDbTone = toneByPct($serverDbOnDiskPct, 60.0, 85.0);

    $serverUptimeLabel = uptimefmt((int)($serverStatus['uptimeSec'] ?? 0));
    $serverLoadLabel = is_numeric($serverStatus['load1'] ?? null)
        ? sprintf(
            '%s / %s / %s',
            nfmt((float)($serverStatus['load1'] ?? 0.0), 2),
            nfmt((float)($serverStatus['load5'] ?? 0.0), 2),
            nfmt((float)($serverStatus['load15'] ?? 0.0), 2)
        )
        : '-';
    $runtimeMeta = (string)($serverStatus['serverSoftware'] ?? '-');
    $loggerFallbackActive = !empty($serverStatus['loggerFallbackActive']);
    if ($loggerFallbackActive) {
        $fallbackContext = trim((string)($serverStatus['loggerFallbackContext'] ?? 'db->file'));
        if ($fallbackContext === '') {
            $fallbackContext = 'db->file';
        }
        $runtimeMeta .= ($runtimeMeta !== '' ? ' | ' : '') . 'logger fallback: ' . $fallbackContext;
    }

    $topProcessesRaw = is_array($serverStatus['topProcesses'] ?? null) ? $serverStatus['topProcesses'] : [];
    $processRows = [];

    foreach ($topProcessesRaw as $proc) {
        if (!is_array($proc)) {
            continue;
        }
        $commandRaw = trim((string)($proc['command'] ?? '-'));
        if ($commandRaw === '') {
            $commandRaw = '-';
        }
        $commandLabel = $commandRaw;
        if (strlen($commandLabel) > 32) {
            $commandLabel = substr($commandLabel, 0, 29) . '...';
        }

        $pid = max(0, (int)($proc['pid'] ?? 0));
        $cpuPct = max(0.0, (float)($proc['cpuPct'] ?? 0.0));
        $memPct = max(0.0, (float)($proc['memPct'] ?? 0.0));
        $rssBytes = max(0, (int)($proc['rssBytes'] ?? 0));

        $processRows[] = [
            // Raw numeric fields expected by the frontend task manager.
            'pid' => $pid,
            'command' => $commandRaw,
            'cpuPct' => $cpuPct,
            'memPct' => $memPct,
            'rssBytes' => $rssBytes,
            'elapsed' => (string)($proc['elapsed'] ?? '-'),

            // Compatibility/display helpers for old consumers.
            'user' => (string)($proc['user'] ?? '-'),
            'app' => $commandLabel,
            'cpu' => pctfmt($cpuPct, 1),
            'mem' => pctfmt($memPct, 1),
            'rss' => sizefmt($rssBytes),
            'pidLabel' => nfmt($pid),
            'cpuLabel' => pctfmt($cpuPct, 1),
            'memLabel' => pctfmt($memPct, 1),
            'rssLabel' => sizefmt($rssBytes),
            'pidSort' => $pid,
            'userSort' => strtolower(trim((string)($proc['user'] ?? '-'))),
            'appSort' => strtolower($commandRaw),
            'cpuSort' => $cpuPct,
            'memSort' => $memPct,
            'rssSort' => $rssBytes,
        ];
    }

    return [
        'disk' => [
            'tone' => $serverDiskTone,
            'pct' => $serverDiskPct,
            'pctLabel' => pctfmt($serverDiskPct, 1),
            'meterPct' => $serverDiskPct,
            'meta' => 'Used ' . sizefmt($serverStatus['diskUsedBytes'] ?? 0) . ' / Total ' . sizefmt($serverStatus['diskTotalBytes'] ?? 0),
        ],
        'ram' => [
            'tone' => $serverRamTone,
            'pct' => $serverRamPct,
            'pctLabel' => pctfmt($serverRamPct, 1),
            'meterPct' => $serverRamPct,
            'meta' => 'Used ' . sizefmt($serverStatus['memoryUsedBytes'] ?? 0) . ' / Total ' . sizefmt($serverStatus['memoryTotalBytes'] ?? 0),
        ],
        'cpu' => [
            'tone' => $serverCpuTone,
            'source' => (string)($serverStatus['cpuSource'] ?? 'load'),
            'pctRaw' => $serverCpuPctRaw,
            'pctLabel' => pctfmt($serverCpuPctRaw, 1),
            'meterPct' => $serverCpuPct,
            'meta' => 'Load ' . $serverLoadLabel . ' | Cores ' . nfmt($serverStatus['cpuCores'] ?? 1),
        ],
        'db' => [
            'tone' => $serverDbTone,
            'sizeLabel' => sizefmt($serverStatus['dbTotalBytes'] ?? 0),
            'meterPct' => $serverDbOnDiskPct,
            'meta' => (string)($serverStatus['dbUsageTable'] ?? 'usage_events') . ' ' . sizefmt($serverStatus['dbUsageEventsBytes'] ?? 0) . ' | ' . (string)($serverStatus['dbShareTable'] ?? 'share_records') . ' ' . sizefmt($serverStatus['dbShareRecordsBytes'] ?? 0),
        ],
        'host' => [
            'name' => (string)($serverStatus['hostname'] ?? '-'),
            'ipLabel' => (string)($serverStatus['serverIp'] ?? '-'),
            'uptimeLabel' => $serverUptimeLabel,
        ],
        'runtime' => [
            'label' => 'PHP ' . (string)($serverStatus['phpVersion'] ?? '-'),
            'meta' => $runtimeMeta,
            'loggerFallbackActive' => $loggerFallbackActive,
            'loggerFallbackAt' => (string)($serverStatus['loggerFallbackAt'] ?? ''),
            'loggerFallbackMessage' => (string)($serverStatus['loggerFallbackMessage'] ?? ''),
        ],
        'database' => [
            'schema' => (string)(($serverStatus['dbSchema'] ?? '') !== '' ? $serverStatus['dbSchema'] : '-'),
            'version' => (string)(($serverStatus['dbVersion'] ?? '') !== '' ? $serverStatus['dbVersion'] : '-'),
        ],
        'rows' => [
            'logsLabel' => 'logs ' . nfmt($serverStatus['dbUsageEventsRows'] ?? 0),
            'sharesLabel' => 'shares: ' . nfmt($serverStatus['dbShareRecordsRows'] ?? 0),
        ],
        'processes' => [
            'capturedAt' => (string)($serverStatus['processCapturedAt'] ?? ''),
            'refreshSec' => max(1.0, (float)($serverStatus['processRefreshSec'] ?? 2.0)),
            'processRows' => $processRows,
        ],
    ];
}

if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

$isServerStatusAjax = strtolower(trim((string)($_GET['ajax'] ?? ''))) === 'server_status';
if ($isServerStatusAjax) {
    header('Content-Type: application/json; charset=utf-8');
    try {
        Security::applyRateLimitConfig(
            $securityConfig,
            'ops_server_status_ajax',
            $serverStatusRateLimitRequests,
            $serverStatusRateLimitWindow,
            $clientIdentity
        );
    } catch (Throwable $error) {
        $statusCode = ($error instanceof \BitaxeOc\App\HttpException)
            ? max(400, (int)$error->statusCode)
            : 429;
        http_response_code($statusCode);
        echo json_encode([
            'ok' => false,
            'error' => $error->getMessage() !== '' ? $error->getMessage() : 'rate_limited',
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    if (!$isAuthenticated) {
        http_response_code(401);
        echo json_encode([
            'ok' => false,
            'error' => 'unauthorized',
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    $serverStatusAjax = collectServerStatus($config);
    $serverStatusViewAjax = buildServerStatusView($serverStatusAjax);
    echo json_encode([
        'ok' => true,
        'refreshed_at' => gmdate('c'),
        'view' => $serverStatusViewAjax,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}


$entries = [];
$summary = [
    'totalRuns' => 0,
    'errorRuns' => 0,
    'errorRatePct' => 0.0,
    'uniqueVisitors' => 0,
    'filesAttemptedTotal' => 0,
    'filesProcessedTotal' => 0,
    'bytesAttemptedTotal' => 0,
    'bytesProcessedTotal' => 0,
    'largestUploadBytes' => 0,
    'rowsTotal' => 0,
    'parsedRowsTotal' => 0,
    'mergedRecordsTotal' => 0,
    'avgFilesPerRun' => 0.0,
    'avgMbPerRun' => 0.0,
    'avgRowsPerRun' => 0.0,
    'avgProcessedMbPerRun' => 0.0,
    'avgAnalysisMs' => 0.0,
    'p95AnalysisMs' => 0.0,
    'maxAnalysisMs' => 0,
    'firstSeen' => null,
    'lastSeen' => null,
    'countries' => [],
    'languages' => [],
    'themes' => [],
    'visitors' => [],
];

if ($isAuthenticated) {
    try {
        $logger = new UsageLogger($loggingConfig);
        $entries = $logger->readLatest(2500);
        $summary = $logger->summarizeAll();
    } catch (Throwable $error) {
        $errorMessage = $error->getMessage() !== '' ? $error->getMessage() : 'Failed to read analytics logs.';
    }
}

$entriesForClient = [];
$extractRegionFromLocale = static function (string $value): string {
    $raw = strtolower(trim($value));
    if ($raw === '') {
        return '';
    }
    $raw = trim((string)explode(',', $raw, 2)[0]);
    if ($raw === '') {
        return '';
    }
    $parts = preg_split('/[-_]/', $raw) ?: [];
    for ($i = count($parts) - 1; $i >= 0; $i--) {
        $token = strtoupper(trim((string)($parts[$i] ?? '')));
        if ($token !== '' && preg_match('/^[A-Z]{2}$/', $token) === 1 && $token !== 'ZZ' && $token !== 'XX') {
            return $token;
        }
    }
    return '';
};

$inferLanguageCode = static function (string $selectedLanguage, string $browserLanguage): string {
    $selected = strtolower(trim($selectedLanguage));
    if ($selected !== '' && preg_match('/^[a-z0-9-]{2,12}$/', $selected) === 1) {
        return $selected;
    }

    $browser = strtolower(trim($browserLanguage));
    if ($browser === '') {
        return 'unknown';
    }

    if (preg_match('/^[a-z]{2,3}(?:-[a-z0-9]{2,8})?/', $browser, $match) === 1) {
        $code = (string)($match[0] ?? '');
        if ($code !== '') {
            if (strlen($code) > 12) {
                $code = substr($code, 0, 12);
            }
            return $code;
        }
    }

    return 'unknown';
};

$inferCountryCode = static function (string $countryCode, string $selectedLanguage, string $browserLanguage) use ($extractRegionFromLocale): string {
    $country = strtoupper(trim($countryCode));
    if (preg_match('/^[A-Z]{2}$/', $country) === 1 && $country !== 'ZZ' && $country !== 'XX' && $country !== '--') {
        return $country;
    }

    $fromSelected = $extractRegionFromLocale($selectedLanguage);
    if ($fromSelected !== '') {
        return $fromSelected;
    }

    $fromBrowser = $extractRegionFromLocale($browserLanguage);
    if ($fromBrowser !== '') {
        return $fromBrowser;
    }

    return 'ZZ';
};

if (is_array($entries)) {
    foreach ($entries as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $rawSelectedLanguage = (string)($entry['selected_language'] ?? '');
        $rawBrowserLanguage = (string)($entry['browser_language'] ?? '');
        $resolvedLanguage = $inferLanguageCode($rawSelectedLanguage, $rawBrowserLanguage);
        $resolvedCountry = $inferCountryCode((string)($entry['country_code'] ?? 'ZZ'), $rawSelectedLanguage, $rawBrowserLanguage);
        $entriesForClient[] = [
            'createdAt' => (string)($entry['created_at'] ?? ''),
            'status' => (string)($entry['request_status'] ?? 'ok'),
            'httpStatus' => max(0, (int)($entry['http_status'] ?? 0)),
            'analysisMs' => max(0, (int)($entry['analysis_ms'] ?? 0)),
            'errorMessage' => (string)($entry['error_message'] ?? ''),
            'filesProcessed' => max(0, (int)($entry['files_processed'] ?? 0)),
            'filesAttempted' => max(0, (int)($entry['files_attempted'] ?? 0)),
            'bytesProcessed' => max(0, (int)($entry['bytes_processed'] ?? 0)),
            'bytesAttempted' => max(0, (int)($entry['bytes_attempted'] ?? 0)),
            'largestUploadBytes' => max(0, (int)($entry['largest_upload_bytes'] ?? 0)),
            'rowsTotal' => max(0, (int)($entry['total_rows'] ?? 0)),
            'parsedRows' => max(0, (int)($entry['parsed_rows'] ?? 0)),
            'skippedRows' => max(0, (int)($entry['skipped_rows'] ?? 0)),
            'mergedRecords' => max(0, (int)($entry['merged_records'] ?? 0)),
            'country' => $resolvedCountry,
            'language' => $resolvedLanguage,
            'theme' => strtolower(trim((string)($entry['selected_theme'] ?? 'dark'))),
            'themeVariant' => strtolower(trim((string)($entry['selected_theme_variant'] ?? 'purple'))),
            'sourceApi' => (string)($entry['source_api'] ?? ''),
            'uploadSkipped' => [
                'nonCsv' => max(0, (int)(($entry['upload_skipped']['nonCsv'] ?? 0))),
                'tooLarge' => max(0, (int)(($entry['upload_skipped']['tooLarge'] ?? 0))),
                'totalOverflow' => max(0, (int)(($entry['upload_skipped']['totalOverflow'] ?? 0))),
                'uploadError' => max(0, (int)(($entry['upload_skipped']['uploadError'] ?? 0))),
                'countOverflow' => max(0, (int)(($entry['upload_skipped']['countOverflow'] ?? 0))),
            ],
        ];
    }
}

$clientPayload = [
    'appVersion' => $appVersion,
    'summary' => [
        'totalRuns' => max(0, (int)($summary['totalRuns'] ?? 0)),
        'errorRuns' => max(0, (int)($summary['errorRuns'] ?? 0)),
        'errorRatePct' => max(0.0, (float)($summary['errorRatePct'] ?? 0.0)),
        'uniqueVisitors' => max(0, (int)($summary['uniqueVisitors'] ?? 0)),
        'filesAttemptedTotal' => max(0, (int)($summary['filesAttemptedTotal'] ?? 0)),
        'filesProcessedTotal' => max(0, (int)($summary['filesProcessedTotal'] ?? 0)),
        'bytesAttemptedTotal' => max(0, (int)($summary['bytesAttemptedTotal'] ?? 0)),
        'bytesProcessedTotal' => max(0, (int)($summary['bytesProcessedTotal'] ?? 0)),
        'largestUploadBytes' => max(0, (int)($summary['largestUploadBytes'] ?? 0)),
        'rowsTotal' => max(0, (int)($summary['rowsTotal'] ?? 0)),
        'parsedRowsTotal' => max(0, (int)($summary['parsedRowsTotal'] ?? 0)),
        'mergedRecordsTotal' => max(0, (int)($summary['mergedRecordsTotal'] ?? 0)),
        'avgFilesPerRun' => max(0.0, (float)($summary['avgFilesPerRun'] ?? 0.0)),
        'avgMbPerRun' => max(0.0, (float)($summary['avgMbPerRun'] ?? 0.0)),
        'avgRowsPerRun' => max(0.0, (float)($summary['avgRowsPerRun'] ?? 0.0)),
        'avgProcessedMbPerRun' => max(0.0, (float)($summary['avgProcessedMbPerRun'] ?? 0.0)),
        'avgAnalysisMs' => max(0.0, (float)($summary['avgAnalysisMs'] ?? 0.0)),
        'p95AnalysisMs' => max(0.0, (float)($summary['p95AnalysisMs'] ?? 0.0)),
        'maxAnalysisMs' => max(0, (int)($summary['maxAnalysisMs'] ?? 0)),
        'firstSeen' => (string)($summary['firstSeen'] ?? ''),
        'lastSeen' => (string)($summary['lastSeen'] ?? ''),
        'countries' => is_array($summary['countries'] ?? null) ? array_values(array_slice($summary['countries'], 0, 20)) : [],
        'languages' => is_array($summary['languages'] ?? null) ? array_values(array_slice($summary['languages'], 0, 20)) : [],
        'themes' => is_array($summary['themes'] ?? null) ? array_values(array_slice($summary['themes'], 0, 20)) : [],
    ],
    'entries' => $entriesForClient,
    'serverStatusUrl' => '/ops-panel.php?ajax=server_status',
    'serverStatusRefreshSec' => max(1.0, min(60.0, (float)($adminConfig['server_status_refresh_sec'] ?? 1.0))),
];

if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex,nofollow,noarchive">
    <title>Ops Panel</title>
    <meta name="theme-color" content="#070b14">
    <link rel="icon" type="image/x-icon" href="/favicon.ico?<?= h2($appVersion) ?>">
    <link rel="icon" type="image/svg+xml" sizes="any" href="/assets/favicon.svg?<?= h2($appVersion) ?>">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">
    <style>
        :root {
            --bg: #070b14;
            --bg-soft: #0a1220;
            --bg-soft-2: #0d1628;
            --line: #18253a;
            --line-strong: #22314a;
            --text: #d9e6f8;
            --text-strong: #f7fbff;
            --muted: #8ea0bb;
            --accent: #8b5cf6;
            --accent-soft: rgba(139, 92, 246, 0.16);
            --ok: #10b981;
            --warn: #f59e0b;
            --bad: #ef4444;
            --card-shadow: 0 16px 36px rgba(2, 6, 23, 0.38);
            --radius: 16px;
            --radius-sm: 12px;
        }

        * { box-sizing: border-box; }

        html, body {
            margin: 0;
            padding: 0;
            font-family: Inter, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: radial-gradient(circle at top, #0c1830 0%, #070b14 55%);
            color: var(--text);
        }

        a { color: inherit; text-decoration: none; }

        .shell {
            width: min(1480px, calc(100vw - 32px));
            margin: 20px auto 40px;
            display: grid;
            gap: 14px;
        }

        .panel {
            border: 1px solid var(--line);
            border-radius: var(--radius);
            background: linear-gradient(180deg, rgba(11, 20, 36, 0.98), rgba(8, 14, 27, 0.98));
            box-shadow: var(--card-shadow);
        }

        .topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 16px 18px;
            gap: 12px;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 0;
        }

        .brand-badge {
            width: 38px;
            height: 38px;
            border-radius: 12px;
            display: grid;
            place-items: center;
            background: var(--accent-soft);
            border: 1px solid rgba(139, 92, 246, 0.32);
            color: #c4b5fd;
            flex: 0 0 auto;
        }

        .brand h1 {
            margin: 0;
            font-size: 18px;
            font-weight: 800;
            letter-spacing: .01em;
            color: var(--text-strong);
        }

        .brand p {
            margin: 2px 0 0;
            font-size: 12px;
            color: var(--muted);
        }

        .toolbar {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            justify-content: flex-end;
        }

        .btn {
            border: 1px solid var(--line-strong);
            background: var(--bg-soft);
            color: var(--text);
            border-radius: 10px;
            padding: 9px 12px;
            font-size: 12px;
            font-weight: 600;
            letter-spacing: .01em;
            cursor: pointer;
            transition: background .18s ease, border-color .18s ease, transform .18s ease;
        }

        .btn:hover { background: var(--bg-soft-2); border-color: #2c3d5d; }
        .btn:active { transform: translateY(1px); }

        .btn-primary {
            border-color: rgba(139, 92, 246, 0.55);
            background: rgba(139, 92, 246, 0.16);
            color: #ddd6fe;
        }

        .controls {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 12px;
            padding: 12px 18px 16px;
            border-top: 1px solid var(--line);
        }

        .range-group {
            display: flex;
            align-items: center;
            gap: 6px;
            flex-wrap: wrap;
        }

        .range-chip {
            border: 1px solid var(--line-strong);
            border-radius: 999px;
            padding: 7px 10px;
            font-size: 12px;
            color: var(--muted);
            background: rgba(10, 18, 32, 0.92);
            cursor: pointer;
            transition: all .18s ease;
        }

        .range-chip.active {
            color: #f5f3ff;
            border-color: rgba(139, 92, 246, 0.62);
            background: rgba(139, 92, 246, 0.2);
        }

        .search-box {
            min-width: 220px;
            max-width: 320px;
            width: 100%;
            border: 1px solid var(--line-strong);
            background: #0a1220;
            color: var(--text);
            border-radius: 10px;
            padding: 10px 12px;
            font-size: 13px;
            outline: none;
        }

        .search-box:focus {
            border-color: rgba(139, 92, 246, 0.65);
            box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.18);
        }

        .cards {
            display: grid;
            grid-template-columns: repeat(6, minmax(0, 1fr));
            gap: 10px;
            padding: 0 18px 16px;
        }

        .stat-card {
            background: linear-gradient(180deg, rgba(11, 18, 34, .96), rgba(9, 15, 29, .96));
            border: 1px solid var(--line);
            border-radius: var(--radius-sm);
            padding: 12px 12px 10px;
            min-height: 95px;
            display: grid;
            gap: 8px;
        }

        .stat-label {
            margin: 0;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: .08em;
            color: var(--muted);
            font-weight: 700;
        }

        .stat-value {
            margin: 0;
            font-size: 24px;
            line-height: 1.06;
            font-weight: 800;
            color: var(--text-strong);
        }

        .stat-meta {
            margin: 0;
            color: var(--muted);
            font-size: 12px;
        }

        .stat-ok .stat-value { color: #86efac; }
        .stat-warn .stat-value { color: #fdba74; }
        .stat-bad .stat-value { color: #fca5a5; }

        .layout {
            display: grid;
            grid-template-columns: 1.25fr .95fr;
            gap: 12px;
            padding: 0 18px 18px;
        }

        .stack {
            display: grid;
            gap: 12px;
        }

        .card-title {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            padding: 12px 14px;
            border-bottom: 1px solid var(--line);
        }

        .card-title h3 {
            margin: 0;
            font-size: 13px;
            letter-spacing: .06em;
            text-transform: uppercase;
            color: #c9d7ea;
        }

        .card-title small {
            color: var(--muted);
            font-size: 11px;
        }

        .card-body { padding: 12px 14px 14px; }

        .chart-wrap { position: relative; width: 100%; height: 260px; }

        .insights {
            display: grid;
            gap: 8px;
        }

        .insight {
            border: 1px solid var(--line);
            background: rgba(10, 18, 32, 0.72);
            border-radius: 10px;
            padding: 10px 11px;
            display: grid;
            grid-template-columns: auto 1fr;
            gap: 10px;
            align-items: start;
        }

        .insight-dot {
            width: 10px;
            height: 10px;
            border-radius: 999px;
            margin-top: 4px;
        }

        .insight-ok .insight-dot { background: var(--ok); }
        .insight-warn .insight-dot { background: var(--warn); }
        .insight-bad .insight-dot { background: var(--bad); }

        .insight p { margin: 0; font-size: 12px; color: var(--text); line-height: 1.45; }

        .view-controls {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 0 18px 14px;
            flex-wrap: wrap;
        }

        .view-controls small {
            color: var(--muted);
            font-size: 11px;
            margin-left: 2px;
        }

        .card-title-actions {
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .cols-menu {
            position: relative;
        }

        .cols-menu summary {
            list-style: none;
            cursor: pointer;
            border: 1px solid var(--line-strong);
            border-radius: 8px;
            padding: 5px 8px;
            font-size: 11px;
            color: var(--muted);
            background: rgba(10, 18, 32, 0.9);
        }

        .cols-menu summary::-webkit-details-marker { display: none; }

        .cols-menu[open] summary {
            color: #e5edff;
            border-color: #31486e;
        }

        .cols-menu-body {
            position: absolute;
            right: 0;
            top: calc(100% + 6px);
            width: 180px;
            z-index: 25;
            border: 1px solid var(--line-strong);
            border-radius: 10px;
            background: #0b1322;
            box-shadow: 0 14px 28px rgba(2, 8, 22, 0.5);
            padding: 8px;
            display: grid;
            gap: 5px;
        }

        .cols-menu-body label {
            display: flex;
            align-items: center;
            gap: 7px;
            font-size: 11px;
            color: var(--text);
            user-select: none;
        }

        .cols-menu-body input {
            accent-color: #8b5cf6;
        }

        .advanced-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 12px;
        }

        .mini-panel {
            border: 1px solid var(--line);
            border-radius: 12px;
            background: rgba(10, 18, 32, 0.7);
            padding: 10px;
        }

        .mini-head {
            display: flex;
            align-items: baseline;
            justify-content: space-between;
            gap: 8px;
            margin-bottom: 8px;
        }

        .mini-head h4 {
            margin: 0;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: .08em;
            color: #c9d7ea;
        }

        .mini-head small {
            color: var(--muted);
            font-size: 10px;
            text-align: right;
        }

        .slo-grid,
        .share-kpis {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 8px;
            margin-bottom: 10px;
        }

        .share-kpis {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }

        .slo-kpi {
            border: 1px solid #1b2941;
            border-radius: 10px;
            background: #0a1425;
            padding: 8px 9px;
        }

        .slo-kpi span {
            display: block;
            color: var(--muted);
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: .07em;
        }

        .slo-kpi strong {
            display: block;
            margin-top: 6px;
            color: var(--text-strong);
            font-size: 15px;
            line-height: 1.1;
        }

        .db-health-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 8px;
            margin-bottom: 10px;
        }

        .db-health-grid > div {
            border: 1px solid #1b2941;
            border-radius: 10px;
            background: #0a1425;
            padding: 8px 9px;
        }

        .db-health-grid span {
            display: block;
            color: var(--muted);
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: .07em;
        }

        .db-health-grid strong {
            display: block;
            margin-top: 6px;
            color: var(--text-strong);
            font-size: 14px;
            line-height: 1.15;
            word-break: break-word;
        }

        .table-wrap {
            overflow: auto;
            border-top: 1px solid var(--line);
        }

        .runs-table-wrap {
            scrollbar-width: thin;
            scrollbar-color: #6d28d9 #0f1729;
        }

        .runs-table-wrap::-webkit-scrollbar { width: 8px; height: 8px; }
        .runs-table-wrap::-webkit-scrollbar-track { background: #0f1729; }
        .runs-table-wrap::-webkit-scrollbar-thumb {
            background: linear-gradient(180deg, #7c3aed, #4c1d95);
            border-radius: 999px;
            border: 1px solid #2a1d4f;
        }
        .runs-table-wrap::-webkit-scrollbar-thumb:hover { background: linear-gradient(180deg, #8b5cf6, #5b21b6); }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 860px;
        }

        th, td {
            text-align: left;
            padding: 10px 10px;
            border-bottom: 1px solid #132137;
            font-size: 12px;
            white-space: nowrap;
        }

        th {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: .08em;
            color: var(--muted);
            font-weight: 700;
            background: rgba(10, 17, 31, 0.9);
            position: sticky;
            top: 0;
            z-index: 1;
        }

        .th-sort {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border: 0;
            background: transparent;
            color: inherit;
            font: inherit;
            text-transform: inherit;
            letter-spacing: inherit;
            padding: 0;
            cursor: pointer;
        }

        .th-sort:hover { color: #dce9ff; }

        .sort-indicator {
            font-size: 10px;
            color: #6f83a5;
            min-width: 10px;
            text-align: center;
        }

        .th-sort.active .sort-indicator {
            color: #c4b5fd;
        }

        tbody tr:hover { background: rgba(255, 255, 255, 0.02); }

        .pill {
            display: inline-flex;
            align-items: center;
            border-radius: 999px;
            border: 1px solid transparent;
            padding: 3px 8px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .05em;
        }

        .pill-ok { color: #a7f3d0; background: rgba(16, 185, 129, .14); border-color: rgba(16, 185, 129, .35); }
        .pill-bad { color: #fecaca; background: rgba(239, 68, 68, .14); border-color: rgba(239, 68, 68, .35); }

        .server-overview-grid {
            display: grid;
            grid-template-columns: minmax(500px, 1fr) minmax(0, 1fr);
            gap: 12px;
            align-items: stretch;
        }

        .server-metrics-stack {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 8px;
        }

        .server-card {
            border: 1px solid var(--line);
            border-radius: 10px;
            background: rgba(10, 18, 32, 0.72);
            padding: 9px 10px 8px;
            display: grid;
            gap: 5px;
            min-height: 76px;
        }

        .server-card-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
        }

        .server-card h4 {
            margin: 0;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: .08em;
            color: var(--muted);
        }

        .server-card strong {
            font-size: 14px;
            line-height: 1.05;
            color: var(--text-strong);
            white-space: nowrap;
        }

        .meter {
            width: 100%;
            height: 7px;
            border-radius: 999px;
            background: #111d31;
            overflow: hidden;
        }

        .meter > span {
            display: block;
            height: 100%;
            width: 0;
            border-radius: inherit;
            background: linear-gradient(90deg, #6366f1, #22d3ee);
            transition: width .3s ease;
        }

        .server-meta { color: var(--muted); font-size: 10px; }

        .server-proc-wrap {
            border: 1px solid var(--line);
            border-radius: 10px;
            overflow: hidden;
            max-height: 170px;
            overflow: auto;
            scrollbar-width: thin;
            scrollbar-color: #2a3956 #0d1627;
        }

        .server-proc-wrap::-webkit-scrollbar { width: 7px; }
        .server-proc-wrap::-webkit-scrollbar-track { background: #0d1627; }
        .server-proc-wrap::-webkit-scrollbar-thumb { background: #2a3956; border-radius: 999px; }

        .server-proc-wrap table { min-width: 640px; }
        .server-proc-wrap th,
        .server-proc-wrap td {
            padding: 8px 8px;
            font-size: 11px;
        }

        .empty {
            border: 1px dashed var(--line-strong);
            border-radius: 12px;
            background: rgba(10, 17, 30, 0.75);
            color: var(--muted);
            padding: 18px;
            font-size: 13px;
            text-align: center;
        }

        .login-wrap {
            width: min(460px, calc(100vw - 30px));
            margin: 120px auto;
            border: 1px solid var(--line);
            border-radius: 16px;
            background: linear-gradient(180deg, rgba(11, 20, 35, 0.98), rgba(8, 14, 25, 0.98));
            box-shadow: var(--card-shadow);
            padding: 22px;
        }

        .login-wrap h2 {
            margin: 0 0 8px;
            color: var(--text-strong);
            font-size: 22px;
        }

        .login-wrap p {
            margin: 0 0 16px;
            color: var(--muted);
            font-size: 13px;
        }

        .field {
            display: grid;
            gap: 6px;
            margin-bottom: 12px;
        }

        .field label { font-size: 12px; color: var(--muted); font-weight: 600; }

        .field input {
            border: 1px solid var(--line-strong);
            background: #0a1220;
            color: var(--text);
            border-radius: 10px;
            padding: 10px 12px;
            font-size: 14px;
            outline: none;
        }

        .field input:focus {
            border-color: rgba(139, 92, 246, 0.65);
            box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.2);
        }

        .error {
            margin: 10px 0 0;
            padding: 9px 10px;
            border-radius: 10px;
            border: 1px solid rgba(239, 68, 68, 0.35);
            background: rgba(239, 68, 68, 0.14);
            color: #fecaca;
            font-size: 12px;
        }

        .muted-note {
            color: var(--muted);
            font-size: 11px;
        }

        @media (max-width: 1380px) {
            .cards { grid-template-columns: repeat(3, minmax(0, 1fr)); }
            .layout { grid-template-columns: 1fr; }
            .server-overview-grid { grid-template-columns: 1fr; }
            .advanced-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }

        @media (max-width: 860px) {
            .shell { width: min(1480px, calc(100vw - 16px)); margin-top: 10px; }
            .topbar { flex-direction: column; align-items: stretch; }
            .toolbar { justify-content: flex-start; }
            .controls { grid-template-columns: 1fr; }
            .cards { grid-template-columns: repeat(2, minmax(0, 1fr)); padding-left: 12px; padding-right: 12px; }
            .layout { padding-left: 12px; padding-right: 12px; }
            .server-overview-grid { grid-template-columns: 1fr; }
            .server-metrics-stack { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .advanced-grid { grid-template-columns: 1fr; }
            .slo-grid,
            .share-kpis,
            .db-health-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }

        @media (max-width: 520px) {
            .cards { grid-template-columns: 1fr; }
            .brand h1 { font-size: 16px; }
            .brand p { font-size: 11px; }
            .search-box { min-width: 0; }
        }
    </style>
</head>
<body>
<?php if (!$isAuthenticated): ?>
    <form class="login-wrap" method="post" action="<?= h2($redirectSelfUrl) ?>" autocomplete="off">
        <h2>Ops Panel Login</h2>
        <p>Modern analytics panel with advanced operational diagnostics.</p>
        <input type="hidden" name="csrf_token" value="<?= h2($csrfToken) ?>">
        <input type="hidden" name="action" value="login">

        <div class="field">
            <label for="username">Username</label>
            <input id="username" name="username" type="text" required maxlength="64" autocomplete="username">
        </div>

        <div class="field">
            <label for="password">Password</label>
            <input id="password" name="password" type="password" required maxlength="200" autocomplete="current-password">
        </div>

        <button class="btn btn-primary" type="submit">Login</button>
        <a class="btn" href="/index.php" style="margin-left:8px;display:inline-flex;">Open Dashboard</a>

        <?php if ($errorMessage !== ''): ?>
            <div class="error"><?= h2($errorMessage) ?></div>
        <?php endif; ?>
    </form>
<?php else: ?>
    <div class="shell">
        <section class="panel">
            <div class="topbar">
                <div class="brand">
                    <div class="brand-badge" aria-hidden="true">
                        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8">
                            <path d="M12 3v12" stroke-linecap="round"></path>
                            <path d="M7 8h10" stroke-linecap="round"></path>
                            <path d="M5 17c0-2.2 3.1-4 7-4s7 1.8 7 4" stroke-linecap="round"></path>
                        </svg>
                    </div>
                    <div>
                        <h1>Ops Panel</h1>
                        <p>Advanced analytics workspace | <?= h2(strtoupper($appVersion)) ?></p>
                    </div>
                </div>

                <div class="toolbar">
                    <a class="btn" href="/index.php">Open Dashboard</a>
                    <button class="btn" id="btn-export-csv" type="button">Export Filtered CSV</button>
                    <button class="btn" id="btn-refresh" type="button">Refresh Data</button>
                    <form method="post" action="<?= h2($redirectSelfUrl) ?>" style="display:inline;">
                        <input type="hidden" name="csrf_token" value="<?= h2($csrfToken) ?>">
                        <input type="hidden" name="action" value="logout">
                        <button class="btn" type="submit">Logout</button>
                    </form>
                </div>
            </div>
        </section>

        <section class="panel">
            <div class="card-title">
                <h3>Server Core Status</h3>
                <small id="server-status-note">Waiting live data...</small>
            </div>
            <div class="card-body">
                <div class="server-overview-grid">
                    <div class="server-metrics-stack">
                        <article class="server-card">
                            <div class="server-card-head">
                                <h4>DB Size</h4>
                                <strong id="sv-db-value">-</strong>
                            </div>
                            <div class="meter"><span id="sv-db-meter"></span></div>
                            <div class="server-meta" id="sv-db-meta">-</div>
                        </article>
                        <article class="server-card">
                            <div class="server-card-head">
                                <h4>Disk Usage</h4>
                                <strong id="sv-disk-value">-</strong>
                            </div>
                            <div class="meter"><span id="sv-disk-meter"></span></div>
                            <div class="server-meta" id="sv-disk-meta">-</div>
                        </article>
                        <article class="server-card">
                            <div class="server-card-head">
                                <h4>RAM Usage</h4>
                                <strong id="sv-ram-value">-</strong>
                            </div>
                            <div class="meter"><span id="sv-ram-meter"></span></div>
                            <div class="server-meta" id="sv-ram-meta">-</div>
                        </article>
                        <article class="server-card">
                            <div class="server-card-head">
                                <h4>CPU Usage</h4>
                                <strong id="sv-cpu-value">-</strong>
                            </div>
                            <div class="meter"><span id="sv-cpu-meter"></span></div>
                            <div class="server-meta" id="sv-cpu-meta">-</div>
                        </article>
                    </div>

                    <div class="server-proc-wrap">
                        <table>
                            <thead>
                            <tr>
                                <th><button class="th-sort" data-table="proc" data-key="pid" type="button">PID <span class="sort-indicator"></span></button></th>
                                <th><button class="th-sort" data-table="proc" data-key="command" type="button">Command <span class="sort-indicator"></span></button></th>
                                <th><button class="th-sort" data-table="proc" data-key="cpuPct" type="button">CPU % <span class="sort-indicator"></span></button></th>
                                <th><button class="th-sort" data-table="proc" data-key="memPct" type="button">RAM % <span class="sort-indicator"></span></button></th>
                                <th><button class="th-sort" data-table="proc" data-key="rssBytes" type="button">RSS <span class="sort-indicator"></span></button></th>
                                <th><button class="th-sort" data-table="proc" data-key="elapsed" type="button">Elapsed <span class="sort-indicator"></span></button></th>
                            </tr>
                            </thead>
                            <tbody id="server-proc-body">
                            <tr><td colspan="6" class="muted-note">No process data yet.</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </section>

        <section class="panel">
            <div class="controls">
                <div class="range-group" id="range-group">
                    <button class="range-chip active" data-range="all" type="button">All time</button>
                    <button class="range-chip" data-range="24h" type="button">24h</button>
                    <button class="range-chip" data-range="7d" type="button">7d</button>
                    <button class="range-chip" data-range="30d" type="button">30d</button>
                    <button class="range-chip" data-range="90d" type="button">90d</button>
                </div>
                <input id="run-search" class="search-box" type="text" placeholder="Search country, language, source or status">
            </div>
            <div class="view-controls">
                <button class="btn" id="btn-save-view" type="button">Save View</button>
                <button class="btn" id="btn-reset-view" type="button">Reset View</button>
                <small id="saved-view-note">Local view state is active.</small>
            </div>

            <div class="cards" id="metric-cards">
                <article class="stat-card">
                    <p class="stat-label">Total Runs</p>
                    <p class="stat-value" id="m-total-runs">0</p>
                    <p class="stat-meta" id="m-total-runs-meta">-</p>
                </article>
                <article class="stat-card">
                    <p class="stat-label">Unique Visitors</p>
                    <p class="stat-value" id="m-unique-visitors">0</p>
                    <p class="stat-meta" id="m-unique-visitors-meta">-</p>
                </article>
                <article class="stat-card stat-ok" id="card-success-rate">
                    <p class="stat-label">Success Rate</p>
                    <p class="stat-value" id="m-success-rate">0%</p>
                    <p class="stat-meta" id="m-success-rate-meta">-</p>
                </article>
                <article class="stat-card">
                    <p class="stat-label">Avg Analysis</p>
                    <p class="stat-value" id="m-avg-analysis">0 ms</p>
                    <p class="stat-meta" id="m-p95-analysis">P95: -</p>
                </article>
                <article class="stat-card">
                    <p class="stat-label">Avg Upload</p>
                    <p class="stat-value" id="m-avg-upload">0 MB</p>
                    <p class="stat-meta" id="m-total-upload">Total processed: -</p>
                </article>
                <article class="stat-card">
                    <p class="stat-label">Rows Processed</p>
                    <p class="stat-value" id="m-rows-processed">0</p>
                    <p class="stat-meta" id="m-merged-records">Merged records: -</p>
                </article>
            </div>

            <div class="layout">
                <div class="stack">
                    <section class="panel">
                        <div class="card-title">
                            <h3>Run Activity Timeline</h3>
                            <small id="timeline-caption">Last 48 buckets</small>
                        </div>
                        <div class="card-body">
                            <div class="chart-wrap"><canvas id="chart-activity"></canvas></div>
                        </div>
                    </section>

                    <section class="panel">
                        <div class="card-title">
                            <h3>Recent Runs</h3>
                            <div class="card-title-actions">
                                <details class="cols-menu" id="runs-cols-menu">
                                    <summary>Columns</summary>
                                    <div class="cols-menu-body">
                                        <label><input type="checkbox" data-col-toggle="createdAt" checked> Timestamp</label>
                                        <label><input type="checkbox" data-col-toggle="status" checked> Status</label>
                                        <label><input type="checkbox" data-col-toggle="analysisMs" checked> Analysis</label>
                                        <label><input type="checkbox" data-col-toggle="filesProcessed" checked> Files</label>
                                        <label><input type="checkbox" data-col-toggle="bytesProcessed" checked> Processed MB</label>
                                        <label><input type="checkbox" data-col-toggle="rowsTotal" checked> Rows</label>
                                        <label><input type="checkbox" data-col-toggle="country" checked> Country</label>
                                        <label><input type="checkbox" data-col-toggle="language" checked> Lang</label>
                                        <label><input type="checkbox" data-col-toggle="theme" checked> Theme</label>
                                        <label><input type="checkbox" data-col-toggle="sourceApi" checked> Source</label>
                                    </div>
                                </details>
                                <small id="runs-count">0 rows</small>
                            </div>
                        </div>
                        <div class="table-wrap runs-table-wrap" style="max-height: 420px; overflow:auto;">
                            <table>
                                <thead>
                                <tr>
                                    <th data-col="createdAt"><button class="th-sort" data-table="runs" data-key="createdAt" type="button">Timestamp <span class="sort-indicator"></span></button></th>
                                    <th data-col="status"><button class="th-sort" data-table="runs" data-key="status" type="button">Status <span class="sort-indicator"></span></button></th>
                                    <th data-col="analysisMs"><button class="th-sort" data-table="runs" data-key="analysisMs" type="button">Analysis <span class="sort-indicator"></span></button></th>
                                    <th data-col="filesProcessed"><button class="th-sort" data-table="runs" data-key="filesProcessed" type="button">Files <span class="sort-indicator"></span></button></th>
                                    <th data-col="bytesProcessed"><button class="th-sort" data-table="runs" data-key="bytesProcessed" type="button">Processed MB <span class="sort-indicator"></span></button></th>
                                    <th data-col="rowsTotal"><button class="th-sort" data-table="runs" data-key="rowsTotal" type="button">Rows <span class="sort-indicator"></span></button></th>
                                    <th data-col="country"><button class="th-sort" data-table="runs" data-key="country" type="button">Country <span class="sort-indicator"></span></button></th>
                                    <th data-col="language"><button class="th-sort" data-table="runs" data-key="language" type="button">Lang <span class="sort-indicator"></span></button></th>
                                    <th data-col="theme"><button class="th-sort" data-table="runs" data-key="theme" type="button">Theme <span class="sort-indicator"></span></button></th>
                                    <th data-col="sourceApi"><button class="th-sort" data-table="runs" data-key="sourceApi" type="button">Source <span class="sort-indicator"></span></button></th>
                                </tr>
                                </thead>
                                <tbody id="runs-body"></tbody>
                            </table>
                        </div>
                    </section>
                </div>

                <div class="stack">
                    <section class="panel">
                        <div class="card-title">
                            <h3>Country Distribution</h3>
                            <small>Top origins</small>
                        </div>
                        <div class="card-body">
                            <div class="chart-wrap" style="height:230px;"><canvas id="chart-countries"></canvas></div>
                        </div>
                    </section>

                    <section class="panel">
                        <div class="card-title">
                            <h3>Status and Language Mix</h3>
                            <small>Quality + locale signal</small>
                        </div>
                        <div class="card-body" style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
                            <div class="chart-wrap" style="height:210px;"><canvas id="chart-status"></canvas></div>
                            <div class="chart-wrap" style="height:210px;"><canvas id="chart-languages"></canvas></div>
                        </div>
                    </section>

                    <section class="panel">
                        <div class="card-title">
                            <h3>Auto Insights</h3>
                            <small>Operational recommendations</small>
                        </div>
                        <div class="card-body">
                            <div class="insights" id="insights-list"></div>
                        </div>
                    </section>
                </div>
            </div>
        </section>

        <section class="panel">
            <div class="card-title">
                <h3>Advanced Reliability Suite</h3>
                <small id="advanced-scope-note">Error intelligence, SLO, security, share and DB diagnostics</small>
            </div>
            <div class="card-body advanced-grid">
                <article class="mini-panel">
                    <div class="mini-head">
                        <h4>Error Intelligence</h4>
                        <small id="error-intel-meta">Error categories and pressure split</small>
                    </div>
                    <div class="chart-wrap" style="height:220px;"><canvas id="chart-error-intel"></canvas></div>
                </article>

                <article class="mini-panel">
                    <div class="mini-head">
                        <h4>SLO and Latency</h4>
                        <small id="slo-meta">p50 / p95 / p99 and SLI target tracking</small>
                    </div>
                    <div class="slo-grid">
                        <div class="slo-kpi"><span>P50</span><strong id="slo-p50">-</strong></div>
                        <div class="slo-kpi"><span>P95</span><strong id="slo-p95">-</strong></div>
                        <div class="slo-kpi"><span>P99</span><strong id="slo-p99">-</strong></div>
                        <div class="slo-kpi"><span>SLI</span><strong id="slo-sli">-</strong></div>
                    </div>
                    <div class="chart-wrap" style="height:145px;"><canvas id="chart-slo-latency"></canvas></div>
                </article>

                <article class="mini-panel">
                    <div class="mini-head">
                        <h4>Security Monitoring</h4>
                        <small id="security-meta">Rate-limit/replay/csrf and 5xx surface</small>
                    </div>
                    <div class="chart-wrap" style="height:220px;"><canvas id="chart-security"></canvas></div>
                </article>

                <article class="mini-panel">
                    <div class="mini-head">
                        <h4>Upload Funnel</h4>
                        <small id="funnel-meta">Attempted -> processed -> parsed -> merged</small>
                    </div>
                    <div class="chart-wrap" style="height:220px;"><canvas id="chart-upload-funnel"></canvas></div>
                </article>

                <article class="mini-panel">
                    <div class="mini-head">
                        <h4>Share Lifecycle</h4>
                        <small id="share-meta">Share activity + archive rows from DB</small>
                    </div>
                    <div class="share-kpis">
                        <div class="slo-kpi"><span>Share runs</span><strong id="share-runs">0</strong></div>
                        <div class="slo-kpi"><span>Share errors</span><strong id="share-errors">0</strong></div>
                        <div class="slo-kpi"><span>DB share rows</span><strong id="share-db-rows">-</strong></div>
                    </div>
                    <div class="chart-wrap" style="height:145px;"><canvas id="chart-share-lifecycle"></canvas></div>
                </article>

                <article class="mini-panel">
                    <div class="mini-head">
                        <h4>DB Health</h4>
                        <small id="db-health-meta">Schema/version/rows and table footprint</small>
                    </div>
                    <div class="db-health-grid">
                        <div><span>Schema</span><strong id="db-schema-value">-</strong></div>
                        <div><span>Version</span><strong id="db-version-value">-</strong></div>
                        <div><span>DB Total</span><strong id="db-total-value">-</strong></div>
                        <div><span>Rows</span><strong id="db-rows-value">-</strong></div>
                    </div>
                    <div class="chart-wrap" style="height:145px;"><canvas id="chart-db-health"></canvas></div>
                </article>
            </div>
            <div class="card-body" style="padding-top:0;">
                <article class="mini-panel">
                    <div class="mini-head">
                        <h4>Alert Center</h4>
                        <small id="alerts-meta">Threshold based warnings (latency, errors, infra)</small>
                    </div>
                    <div class="insights" id="alerts-list"></div>
                </article>
            </div>
        </section>

    </div>

    <script src="/assets/vendor/chart.umd.min.js?<?= h2($appVersion) ?>"></script>
    <script>
        (function () {
            const payload = <?= json_encode($clientPayload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
            const allEntries = Array.isArray(payload.entries) ? payload.entries : [];
            const summary = payload.summary || {};
            const serverStatusUrl = String(payload.serverStatusUrl || '/ops-panel.php?ajax=server_status');
            const serverStatusRefreshSec = Math.max(1, Math.min(60, Number(payload.serverStatusRefreshSec || 1)));
            const serverStatusRefreshMs = Math.round(serverStatusRefreshSec * 1000);

            const numberFmt = new Intl.NumberFormat('en-US', { maximumFractionDigits: 0 });
            const numberFmt2 = new Intl.NumberFormat('en-US', { maximumFractionDigits: 2, minimumFractionDigits: 2 });

            let currentRange = 'all';
            let currentSearch = '';
            let filtered = allEntries.slice();
            let lastProcessRows = [];
            let lastServerView = null;
            const runsSortState = { key: 'createdAt', dir: 'desc' };
            const procSortState = { key: 'cpuPct', dir: 'desc' };
            const runColumnKeys = ['createdAt', 'status', 'analysisMs', 'filesProcessed', 'bytesProcessed', 'rowsTotal', 'country', 'language', 'theme', 'sourceApi'];
            let visibleRunColumns = new Set(runColumnKeys);
            const viewStateKey = 'ops_panel_view_state_v3';

            const charts = {
                activity: null,
                countries: null,
                status: null,
                languages: null,
                errorIntel: null,
                sloLatency: null,
                security: null,
                uploadFunnel: null,
                shareLifecycle: null,
                dbHealth: null,
            };

            function toTs(value) {
                if (!value) return 0;
                const t = Date.parse(value);
                return Number.isFinite(t) ? t : 0;
            }

            function fmtInt(value) {
                const safe = Number.isFinite(value) ? value : 0;
                return numberFmt.format(Math.max(0, Math.round(safe)));
            }

            function fmtFloat(value, digits = 2) {
                const safe = Number.isFinite(value) ? value : 0;
                if (digits <= 0) {
                    return numberFmt.format(Math.max(0, Math.round(safe)));
                }
                return safe.toLocaleString('en-US', {
                    minimumFractionDigits: digits,
                    maximumFractionDigits: digits,
                });
            }

            function fmtMs(value) {
                const safe = Number.isFinite(value) ? Math.max(0, value) : 0;
                if (safe < 1000) {
                    return fmtInt(safe) + ' ms';
                }
                return fmtFloat(safe / 1000, 2) + ' s';
            }

            function fmtMb(bytes) {
                const safe = Number.isFinite(bytes) ? Math.max(0, bytes) : 0;
                return fmtFloat(safe / (1024 * 1024), 2) + ' MB';
            }

            function parseNumberLoose(value) {
                if (Number.isFinite(Number(value))) return Number(value);
                const text = String(value ?? '').replace(/[^\d.,-]/g, '').replace(/,/g, '');
                const num = Number(text);
                return Number.isFinite(num) ? num : 0;
            }

            function quantile(sortedAsc, ratio) {
                if (!Array.isArray(sortedAsc) || sortedAsc.length === 0) return 0;
                const clamped = Math.max(0, Math.min(1, Number(ratio) || 0));
                const idx = Math.min(sortedAsc.length - 1, Math.max(0, Math.floor((sortedAsc.length - 1) * clamped)));
                const value = Number(sortedAsc[idx] || 0);
                return Number.isFinite(value) ? Math.max(0, value) : 0;
            }

            function escapeHtml(value) {
                return String(value ?? '')
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/\"/g, '&quot;')
                    .replace(/'/g, '&#39;');
            }

            function setText(id, value) {
                const node = document.getElementById(id);
                if (node) node.textContent = String(value);
            }

            function normalizeDbSizeLabel(label) {
                const raw = String(label ?? '').trim();
                if (!raw) return '-';
                return raw
                    .replace(/((?:\d{1,3}(?:[.,]\d{3})+|\d+))[.,]00(\s*[A-Za-z]+)$/u, '$1$2')
                    .replace(/(\d+)\.00(?!\d)/u, '$1')
                    .replace(/(\d+),00(?!\d)/u, '$1');
            }

            function setMeter(id, pct) {
                const node = document.getElementById(id);
                if (!node) return;
                const safe = Math.max(0, Math.min(100, Number(pct) || 0));
                node.style.width = safe.toFixed(1) + '%';
            }

            function buildViewState() {
                return {
                    range: currentRange,
                    search: currentSearch,
                    runsSort: { key: runsSortState.key, dir: runsSortState.dir },
                    procSort: { key: procSortState.key, dir: procSortState.dir },
                    visibleColumns: Array.from(visibleRunColumns),
                };
            }

            function persistViewState() {
                try {
                    localStorage.setItem(viewStateKey, JSON.stringify(buildViewState()));
                } catch (_) {
                    // noop
                }
            }

            function restoreViewState() {
                try {
                    const raw = localStorage.getItem(viewStateKey);
                    if (!raw) return;
                    const parsed = JSON.parse(raw);
                    if (!parsed || typeof parsed !== 'object') return;

                    const range = String(parsed.range || '');
                    if (['all', '24h', '7d', '30d', '90d'].includes(range)) {
                        currentRange = range;
                    }

                    currentSearch = String(parsed.search || '');
                    const searchNode = document.getElementById('run-search');
                    if (searchNode) searchNode.value = currentSearch;

                    const runsSort = parsed.runsSort || {};
                    const procSort = parsed.procSort || {};
                    if (typeof runsSort.key === 'string' && typeof runsSort.dir === 'string') {
                        runsSortState.key = runsSort.key;
                        runsSortState.dir = runsSort.dir === 'asc' ? 'asc' : 'desc';
                    }
                    if (typeof procSort.key === 'string' && typeof procSort.dir === 'string') {
                        procSortState.key = procSort.key;
                        procSortState.dir = procSort.dir === 'asc' ? 'asc' : 'desc';
                    }

                    const cols = Array.isArray(parsed.visibleColumns) ? parsed.visibleColumns.filter((key) => runColumnKeys.includes(String(key))) : [];
                    if (cols.length > 0) {
                        visibleRunColumns = new Set(cols);
                    }
                } catch (_) {
                    // noop
                }
            }

            function applyRangeChipUi() {
                const chips = Array.from(document.querySelectorAll('.range-chip'));
                chips.forEach((chip) => {
                    const isActive = String(chip.dataset.range || 'all') === currentRange;
                    chip.classList.toggle('active', isActive);
                });
            }

            function syncColumnMenuUi() {
                const toggles = Array.from(document.querySelectorAll('[data-col-toggle]'));
                toggles.forEach((toggle) => {
                    const key = String(toggle.getAttribute('data-col-toggle') || '');
                    toggle.checked = visibleRunColumns.has(key);
                });
            }

            function applyRunColumnVisibility() {
                runColumnKeys.forEach((key) => {
                    const isVisible = visibleRunColumns.has(key);
                    const header = document.querySelector('th[data-col="' + key + '"]');
                    if (header) header.style.display = isVisible ? '' : 'none';
                    const cells = document.querySelectorAll('td[data-col="' + key + '"]');
                    cells.forEach((cell) => {
                        cell.style.display = isVisible ? '' : 'none';
                    });
                });
                syncColumnMenuUi();
            }

            function setSavedViewNote(text) {
                const note = document.getElementById('saved-view-note');
                if (note) note.textContent = text;
            }

            function compareNullableNumbers(a, b) {
                const na = Number.isFinite(Number(a)) ? Number(a) : 0;
                const nb = Number.isFinite(Number(b)) ? Number(b) : 0;
                if (na === nb) return 0;
                return na < nb ? -1 : 1;
            }

            function compareNullableStrings(a, b) {
                const sa = String(a ?? '').toLowerCase();
                const sb = String(b ?? '').toLowerCase();
                if (sa === sb) return 0;
                return sa < sb ? -1 : 1;
            }

            function sortRowsByState(rows, state, tableName) {
                const sorted = rows.slice();
                sorted.sort((ra, rb) => {
                    let cmp = 0;
                    if (tableName === 'runs') {
                        if (state.key === 'createdAt') cmp = compareNullableNumbers(toTs(ra.createdAt), toTs(rb.createdAt));
                        else if (['analysisMs', 'filesProcessed', 'bytesProcessed', 'rowsTotal'].includes(state.key)) cmp = compareNullableNumbers(ra[state.key], rb[state.key]);
                        else cmp = compareNullableStrings(ra[state.key], rb[state.key]);
                    } else {
                        if (['pid', 'cpuPct', 'memPct', 'rssBytes'].includes(state.key)) cmp = compareNullableNumbers(ra[state.key], rb[state.key]);
                        else cmp = compareNullableStrings(ra[state.key], rb[state.key]);
                    }
                    return state.dir === 'asc' ? cmp : -cmp;
                });
                return sorted;
            }

            function updateSortIndicators() {
                const buttons = document.querySelectorAll('.th-sort');
                buttons.forEach((btn) => {
                    btn.classList.remove('active');
                    const table = String(btn.dataset.table || '');
                    const key = String(btn.dataset.key || '');
                    const indicator = btn.querySelector('.sort-indicator');
                    if (!indicator) return;
                    let symbol = '↕';
                    if (table === 'runs' && key === runsSortState.key) {
                        btn.classList.add('active');
                        symbol = runsSortState.dir === 'asc' ? '↑' : '↓';
                    } else if (table === 'proc' && key === procSortState.key) {
                        btn.classList.add('active');
                        symbol = procSortState.dir === 'asc' ? '↑' : '↓';
                    }
                    indicator.textContent = symbol;
                });
            }

            function computeRangeStart(range) {
                const now = Date.now();
                const map = {
                    '24h': 24 * 3600 * 1000,
                    '7d': 7 * 24 * 3600 * 1000,
                    '30d': 30 * 24 * 3600 * 1000,
                    '90d': 90 * 24 * 3600 * 1000,
                };
                if (!map[range]) return 0;
                return now - map[range];
            }

            function normalizeSearchText(entry) {
                return [
                    entry.status,
                    entry.httpStatus,
                    entry.errorMessage,
                    entry.country,
                    entry.language,
                    entry.theme,
                    entry.themeVariant,
                    entry.sourceApi,
                ].join(' ').toLowerCase();
            }

            function applyFilters() {
                const rangeStart = computeRangeStart(currentRange);
                const q = currentSearch.trim().toLowerCase();

                filtered = allEntries.filter((entry) => {
                    const ts = toTs(entry.createdAt);
                    if (rangeStart > 0 && ts > 0 && ts < rangeStart) return false;
                    if (!q) return true;
                    return normalizeSearchText(entry).includes(q);
                });
            }

            function summarizeEntries(entries) {
                const stats = {
                    totalRuns: entries.length,
                    errorRuns: 0,
                    uniqueVisitorsApprox: summary.uniqueVisitors || 0,
                    bytesProcessed: 0,
                    bytesAttempted: 0,
                    rowsTotal: 0,
                    parsedRows: 0,
                    skippedRows: 0,
                    merged: 0,
                    filesAttempted: 0,
                    filesProcessed: 0,
                    analysisSum: 0,
                    analysisCount: 0,
                    p50AnalysisMs: 0,
                    p95AnalysisMs: 0,
                    p99AnalysisMs: 0,
                    byCountry: new Map(),
                    byLanguage: new Map(),
                    byStatus: new Map(),
                    byErrorCategory: new Map(),
                    bySecuritySignal: new Map(),
                    shareRuns: 0,
                    shareErrors: 0,
                    uploadSkipTooLarge: 0,
                    uploadSkipOverflow: 0,
                    uploadSkipUploadError: 0,
                };

                const analysisSeries = [];

                for (const row of entries) {
                    const isError = String(row.status || '').toLowerCase() !== 'ok';
                    if (isError) stats.errorRuns += 1;
                    stats.bytesProcessed += Number(row.bytesProcessed || 0);
                    stats.bytesAttempted += Number(row.bytesAttempted || 0);
                    stats.rowsTotal += Number(row.rowsTotal || 0);
                    stats.parsedRows += Number(row.parsedRows || 0);
                    stats.skippedRows += Number(row.skippedRows || 0);
                    stats.merged += Number(row.mergedRecords || 0);
                    stats.filesAttempted += Number(row.filesAttempted || 0);
                    stats.filesProcessed += Number(row.filesProcessed || 0);

                    const skipped = row.uploadSkipped || {};
                    stats.uploadSkipTooLarge += Number(skipped.tooLarge || 0);
                    stats.uploadSkipOverflow += Number(skipped.totalOverflow || 0) + Number(skipped.countOverflow || 0);
                    stats.uploadSkipUploadError += Number(skipped.uploadError || 0);

                    const ms = Number(row.analysisMs || 0);
                    if (Number.isFinite(ms) && ms >= 0) {
                        stats.analysisSum += ms;
                        stats.analysisCount += 1;
                        analysisSeries.push(ms);
                    }

                    const country = (row.country || 'ZZ').toUpperCase();
                    stats.byCountry.set(country, (stats.byCountry.get(country) || 0) + 1);

                    const language = (row.language || 'unknown').toLowerCase();
                    stats.byLanguage.set(language, (stats.byLanguage.get(language) || 0) + 1);

                    const status = isError ? 'error' : 'ok';
                    stats.byStatus.set(status, (stats.byStatus.get(status) || 0) + 1);

                    const src = String(row.sourceApi || '').toLowerCase();
                    if (src.includes('share')) {
                        stats.shareRuns += 1;
                        if (isError) stats.shareErrors += 1;
                    }

                    if (isError) {
                        const http = Number(row.httpStatus || 0);
                        const msg = String(row.errorMessage || '').toLowerCase();
                        const rowSkipTooLarge = Number(skipped.tooLarge || 0);
                        const rowSkipOverflow = Number(skipped.totalOverflow || 0) + Number(skipped.countOverflow || 0);
                        const rowSkipUploadError = Number(skipped.uploadError || 0);
                        let cat = 'other';
                        if (http === 429 || msg.includes('rate')) cat = 'rate_limit';
                        else if (msg.includes('csrf') || msg.includes('nonce') || msg.includes('replay')) cat = 'csrf_replay';
                        else if (rowSkipTooLarge > 0 || rowSkipOverflow > 0 || msg.includes('limit')) cat = 'limits';
                        else if (rowSkipUploadError > 0 || msg.includes('upload')) cat = 'upload';
                        else if (msg.includes('csv') || msg.includes('parse') || msg.includes('json')) cat = 'parse_data';
                        else if (http >= 500) cat = 'server';
                        stats.byErrorCategory.set(cat, (stats.byErrorCategory.get(cat) || 0) + 1);

                        const sig = (http === 401 || http === 403) ? 'auth' :
                            (http === 409 ? 'replay' :
                            (http === 429 ? 'rate_limit' :
                            (http >= 500 ? 'server_5xx' : 'other')));
                        stats.bySecuritySignal.set(sig, (stats.bySecuritySignal.get(sig) || 0) + 1);
                    }
                }

                analysisSeries.sort((a, b) => a - b);
                if (analysisSeries.length > 0) {
                    stats.p50AnalysisMs = quantile(analysisSeries, 0.50);
                    stats.p95AnalysisMs = quantile(analysisSeries, 0.95);
                    stats.p99AnalysisMs = quantile(analysisSeries, 0.99);
                }

                return stats;
            }

            function buildTimeline(entries) {
                if (entries.length === 0) {
                    return { labels: [], okSeries: [], errSeries: [] };
                }

                const buckets = new Map();
                const now = Date.now();
                const horizonMs = currentRange === '24h' ? 24 * 3600 * 1000 : (currentRange === '7d' ? 7 * 24 * 3600 * 1000 : 48 * 3600 * 1000);
                const bucketMs = currentRange === '7d' ? 6 * 3600 * 1000 : 3600 * 1000;
                const start = now - horizonMs;

                for (const row of entries) {
                    const ts = toTs(row.createdAt);
                    if (!ts || ts < start) continue;
                    const bucketStart = Math.floor((ts - start) / bucketMs) * bucketMs + start;
                    const key = String(bucketStart);
                    if (!buckets.has(key)) {
                        buckets.set(key, { ok: 0, err: 0 });
                    }
                    const item = buckets.get(key);
                    if (String(row.status || '').toLowerCase() === 'ok') {
                        item.ok += 1;
                    } else {
                        item.err += 1;
                    }
                }

                const labels = [];
                const okSeries = [];
                const errSeries = [];

                const bucketCount = Math.ceil(horizonMs / bucketMs);
                for (let i = 0; i < bucketCount; i++) {
                    const bucketStart = start + (i * bucketMs);
                    const key = String(bucketStart);
                    const item = buckets.get(key) || { ok: 0, err: 0 };
                    labels.push(new Date(bucketStart).toLocaleString([], {
                        month: '2-digit',
                        day: '2-digit',
                        hour: '2-digit',
                    }));
                    okSeries.push(item.ok);
                    errSeries.push(item.err);
                }

                return { labels, okSeries, errSeries };
            }

            function renderCards(stats) {
                const total = Math.max(0, stats.totalRuns);
                const errors = Math.max(0, stats.errorRuns);
                const success = Math.max(0, total - errors);
                const successRate = total > 0 ? (100 * success / total) : 0;
                const avgAnalysis = stats.analysisCount > 0 ? (stats.analysisSum / stats.analysisCount) : 0;

                setText('m-total-runs', fmtInt(total));
                setText('m-total-runs-meta', currentRange.toUpperCase() + ' filtered rows');
                setText('m-unique-visitors', fmtInt(summary.uniqueVisitors || 0));
                setText('m-unique-visitors-meta', 'Global unique visitors');
                setText('m-success-rate', fmtFloat(successRate, 2) + '%');
                setText('m-success-rate-meta', fmtInt(success) + ' ok / ' + fmtInt(errors) + ' error');
                setText('m-avg-analysis', fmtMs(avgAnalysis));
                setText('m-p95-analysis', 'P95: ' + fmtMs(stats.p95AnalysisMs));
                setText('m-avg-upload', total > 0 ? fmtMb(stats.bytesProcessed / total) : '0 MB');
                setText('m-total-upload', 'Total processed: ' + fmtMb(stats.bytesProcessed));
                setText('m-rows-processed', fmtInt(stats.rowsTotal));
                setText('m-merged-records', 'Merged records: ' + fmtInt(stats.merged));

                const card = document.getElementById('card-success-rate');
                if (card) {
                    card.classList.remove('stat-ok', 'stat-warn', 'stat-bad');
                    if (successRate >= 96) card.classList.add('stat-ok');
                    else if (successRate >= 90) card.classList.add('stat-warn');
                    else card.classList.add('stat-bad');
                }
            }

            function destroyChart(instance) {
                if (instance && typeof instance.destroy === 'function') {
                    instance.destroy();
                }
            }

            function renderCharts(stats) {
                const timeline = buildTimeline(filtered);

                destroyChart(charts.activity);
                const activityCtx = document.getElementById('chart-activity');
                if (activityCtx) {
                    charts.activity = new Chart(activityCtx, {
                        type: 'line',
                        data: {
                            labels: timeline.labels,
                            datasets: [
                                {
                                    label: 'OK',
                                    data: timeline.okSeries,
                                    borderColor: '#22c55e',
                                    backgroundColor: 'rgba(34,197,94,0.15)',
                                    borderWidth: 2,
                                    tension: 0.25,
                                    pointRadius: 0,
                                    fill: true,
                                },
                                {
                                    label: 'Error',
                                    data: timeline.errSeries,
                                    borderColor: '#ef4444',
                                    backgroundColor: 'rgba(239,68,68,0.1)',
                                    borderWidth: 2,
                                    tension: 0.25,
                                    pointRadius: 0,
                                    fill: true,
                                }
                            ]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            interaction: { mode: 'index', intersect: false },
                            scales: {
                                x: { ticks: { color: '#8ea0bb', maxRotation: 0, autoSkip: true, maxTicksLimit: 10 }, grid: { color: 'rgba(142,160,187,0.08)' } },
                                y: { ticks: { color: '#8ea0bb' }, grid: { color: 'rgba(142,160,187,0.08)' }, beginAtZero: true }
                            },
                            plugins: {
                                legend: { labels: { color: '#c9d7ea' } },
                                tooltip: { backgroundColor: '#101a2f', borderColor: '#2b3f5d', borderWidth: 1 }
                            }
                        }
                    });
                }

                const sortedCountries = Array.from(stats.byCountry.entries())
                    .sort((a, b) => b[1] - a[1])
                    .slice(0, 10);

                destroyChart(charts.countries);
                const countryCtx = document.getElementById('chart-countries');
                if (countryCtx) {
                    charts.countries = new Chart(countryCtx, {
                        type: 'bar',
                        data: {
                            labels: sortedCountries.map((x) => x[0]),
                            datasets: [{
                                label: 'Runs',
                                data: sortedCountries.map((x) => x[1]),
                                backgroundColor: 'rgba(99,102,241,0.72)',
                                borderColor: '#818cf8',
                                borderWidth: 1,
                                borderRadius: 8,
                            }]
                        },
                        options: {
                            indexAxis: 'y',
                            responsive: true,
                            maintainAspectRatio: false,
                            scales: {
                                x: { ticks: { color: '#8ea0bb' }, grid: { color: 'rgba(142,160,187,0.08)' }, beginAtZero: true },
                                y: { ticks: { color: '#c9d7ea' }, grid: { display: false } }
                            },
                            plugins: {
                                legend: { display: false },
                                tooltip: { backgroundColor: '#101a2f', borderColor: '#2b3f5d', borderWidth: 1 }
                            }
                        }
                    });
                }

                destroyChart(charts.status);
                const statusCtx = document.getElementById('chart-status');
                if (statusCtx) {
                    charts.status = new Chart(statusCtx, {
                        type: 'doughnut',
                        data: {
                            labels: ['OK', 'Error'],
                            datasets: [{
                                data: [stats.byStatus.get('ok') || 0, stats.byStatus.get('error') || 0],
                                backgroundColor: ['rgba(16,185,129,0.8)', 'rgba(239,68,68,0.8)'],
                                borderColor: ['#34d399', '#f87171'],
                                borderWidth: 1,
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: { labels: { color: '#c9d7ea' }, position: 'bottom' },
                                tooltip: { backgroundColor: '#101a2f', borderColor: '#2b3f5d', borderWidth: 1 }
                            },
                            cutout: '62%'
                        }
                    });
                }

                const sortedLang = Array.from(stats.byLanguage.entries())
                    .sort((a, b) => b[1] - a[1])
                    .slice(0, 7);

                destroyChart(charts.languages);
                const langCtx = document.getElementById('chart-languages');
                if (langCtx) {
                    charts.languages = new Chart(langCtx, {
                        type: 'polarArea',
                        data: {
                            labels: sortedLang.map((x) => x[0]),
                            datasets: [{
                                data: sortedLang.map((x) => x[1]),
                                backgroundColor: [
                                    'rgba(139,92,246,0.5)',
                                    'rgba(59,130,246,0.5)',
                                    'rgba(16,185,129,0.5)',
                                    'rgba(245,158,11,0.5)',
                                    'rgba(236,72,153,0.5)',
                                    'rgba(14,165,233,0.5)',
                                    'rgba(168,85,247,0.5)'
                                ],
                                borderColor: [
                                    '#8b5cf6', '#3b82f6', '#10b981', '#f59e0b', '#ec4899', '#0ea5e9', '#a855f7'
                                ],
                                borderWidth: 1,
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            scales: {
                                r: {
                                    angleLines: { color: 'rgba(142,160,187,0.12)' },
                                    grid: { color: 'rgba(142,160,187,0.12)' },
                                    pointLabels: { color: '#8ea0bb' },
                                    ticks: { color: '#8ea0bb', backdropColor: 'transparent' },
                                }
                            },
                            plugins: {
                                legend: { labels: { color: '#c9d7ea' }, position: 'bottom' },
                                tooltip: { backgroundColor: '#101a2f', borderColor: '#2b3f5d', borderWidth: 1 }
                            }
                        }
                    });
                }
            }

            function renderAdvancedSuite(stats) {
                const total = Math.max(0, stats.totalRuns);
                const errorTotal = Math.max(0, stats.errorRuns);
                const errorRate = total > 0 ? ((100 * errorTotal) / total) : 0;
                const sli = Math.max(0, 100 - errorRate);

                setText('slo-p50', fmtMs(stats.p50AnalysisMs || 0));
                setText('slo-p95', fmtMs(stats.p95AnalysisMs || 0));
                setText('slo-p99', fmtMs(stats.p99AnalysisMs || 0));
                setText('slo-sli', fmtFloat(sli, 2) + '%');
                setText('slo-meta', 'Target: p95 < 2.5s, error < 3%');

                setText('share-runs', fmtInt(stats.shareRuns || 0));
                setText('share-errors', fmtInt(stats.shareErrors || 0));

                const errorEntries = Array.from(stats.byErrorCategory.entries()).sort((a, b) => b[1] - a[1]);
                const errorLabels = errorEntries.map((entry) => {
                    const key = String(entry[0] || '');
                    if (key === 'rate_limit') return 'Rate limit';
                    if (key === 'csrf_replay') return 'CSRF/Replay';
                    if (key === 'limits') return 'Limits';
                    if (key === 'upload') return 'Upload';
                    if (key === 'parse_data') return 'Parse/Data';
                    if (key === 'server') return 'Server';
                    return 'Other';
                });
                const errorSeries = errorEntries.map((entry) => Number(entry[1] || 0));
                if (errorSeries.length === 0) {
                    errorLabels.push('No errors');
                    errorSeries.push(1);
                }

                destroyChart(charts.errorIntel);
                const errorCtx = document.getElementById('chart-error-intel');
                if (errorCtx) {
                    charts.errorIntel = new Chart(errorCtx, {
                        type: 'doughnut',
                        data: {
                            labels: errorLabels,
                            datasets: [{
                                data: errorSeries,
                                backgroundColor: ['#ef4444', '#f59e0b', '#22d3ee', '#8b5cf6', '#fb7185', '#f97316', '#334155'],
                                borderColor: '#0b1322',
                                borderWidth: 2,
                            }],
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            cutout: '58%',
                            plugins: {
                                legend: { labels: { color: '#c9d7ea', boxWidth: 10 } },
                                tooltip: { backgroundColor: '#101a2f', borderColor: '#2b3f5d', borderWidth: 1 },
                            },
                        },
                    });
                }

                destroyChart(charts.sloLatency);
                const sloCtx = document.getElementById('chart-slo-latency');
                if (sloCtx) {
                    charts.sloLatency = new Chart(sloCtx, {
                        type: 'bar',
                        data: {
                            labels: ['P50', 'P95', 'P99'],
                            datasets: [{
                                label: 'Latency (ms)',
                                data: [stats.p50AnalysisMs || 0, stats.p95AnalysisMs || 0, stats.p99AnalysisMs || 0],
                                backgroundColor: ['rgba(34,211,238,0.65)', 'rgba(245,158,11,0.65)', 'rgba(239,68,68,0.65)'],
                                borderColor: ['#22d3ee', '#f59e0b', '#ef4444'],
                                borderWidth: 1,
                                borderRadius: 8,
                            }],
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            scales: {
                                y: { beginAtZero: true, ticks: { color: '#8ea0bb' }, grid: { color: 'rgba(142,160,187,0.08)' } },
                                x: { ticks: { color: '#c9d7ea' }, grid: { display: false } },
                            },
                            plugins: {
                                legend: { display: false },
                                tooltip: { backgroundColor: '#101a2f', borderColor: '#2b3f5d', borderWidth: 1 },
                            },
                        },
                    });
                }

                const secEntries = Array.from(stats.bySecuritySignal.entries()).sort((a, b) => b[1] - a[1]);
                const secLabels = secEntries.map((entry) => {
                    const key = String(entry[0] || '');
                    if (key === 'rate_limit') return 'Rate limit';
                    if (key === 'replay') return 'Replay';
                    if (key === 'auth') return 'Auth';
                    if (key === 'server_5xx') return '5xx';
                    return 'Other';
                });
                const secSeries = secEntries.map((entry) => Number(entry[1] || 0));
                if (secSeries.length === 0) {
                    secLabels.push('Clean');
                    secSeries.push(1);
                }

                destroyChart(charts.security);
                const secCtx = document.getElementById('chart-security');
                if (secCtx) {
                    charts.security = new Chart(secCtx, {
                        type: 'bar',
                        data: {
                            labels: secLabels,
                            datasets: [{
                                label: 'Events',
                                data: secSeries,
                                backgroundColor: 'rgba(248,113,113,0.58)',
                                borderColor: '#ef4444',
                                borderWidth: 1,
                                borderRadius: 8,
                            }],
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            scales: {
                                y: { beginAtZero: true, ticks: { color: '#8ea0bb' }, grid: { color: 'rgba(142,160,187,0.08)' } },
                                x: { ticks: { color: '#c9d7ea' }, grid: { display: false } },
                            },
                            plugins: {
                                legend: { display: false },
                                tooltip: { backgroundColor: '#101a2f', borderColor: '#2b3f5d', borderWidth: 1 },
                            },
                        },
                    });
                }

                const funnelAttempted = Math.max(0, stats.filesAttempted || 0);
                const funnelProcessed = Math.max(0, stats.filesProcessed || 0);
                const funnelParsed = Math.max(0, stats.parsedRows || 0);
                const funnelMerged = Math.max(0, stats.merged || 0);
                const funnelMax = Math.max(1, funnelAttempted, funnelProcessed, funnelParsed, funnelMerged);
                setText('funnel-meta', 'Attempted ' + fmtInt(funnelAttempted) + ' | Processed ' + fmtInt(funnelProcessed));

                destroyChart(charts.uploadFunnel);
                const funnelCtx = document.getElementById('chart-upload-funnel');
                if (funnelCtx) {
                    charts.uploadFunnel = new Chart(funnelCtx, {
                        type: 'bar',
                        data: {
                            labels: ['Files attempted', 'Files processed', 'Rows parsed', 'Records merged'],
                            datasets: [{
                                data: [funnelAttempted, funnelProcessed, funnelParsed, funnelMerged],
                                backgroundColor: [
                                    'rgba(99,102,241,0.62)',
                                    'rgba(34,197,94,0.62)',
                                    'rgba(14,165,233,0.62)',
                                    'rgba(245,158,11,0.62)'
                                ],
                                borderColor: ['#818cf8', '#22c55e', '#0ea5e9', '#f59e0b'],
                                borderWidth: 1,
                                borderRadius: 8,
                            }],
                        },
                        options: {
                            indexAxis: 'y',
                            responsive: true,
                            maintainAspectRatio: false,
                            scales: {
                                x: {
                                    beginAtZero: true,
                                    suggestedMax: funnelMax,
                                    ticks: { color: '#8ea0bb' },
                                    grid: { color: 'rgba(142,160,187,0.08)' },
                                },
                                y: { ticks: { color: '#c9d7ea' }, grid: { display: false } },
                            },
                            plugins: {
                                legend: { display: false },
                                tooltip: { backgroundColor: '#101a2f', borderColor: '#2b3f5d', borderWidth: 1 },
                            },
                        },
                    });
                }

                const sharePoints = buildShareTimeline(entriesFromCurrentScope());
                destroyChart(charts.shareLifecycle);
                const shareCtx = document.getElementById('chart-share-lifecycle');
                if (shareCtx) {
                    charts.shareLifecycle = new Chart(shareCtx, {
                        type: 'line',
                        data: {
                            labels: sharePoints.labels,
                            datasets: [{
                                label: 'Share runs',
                                data: sharePoints.series,
                                borderColor: '#34d399',
                                backgroundColor: 'rgba(52,211,153,0.16)',
                                borderWidth: 2,
                                tension: 0.3,
                                pointRadius: 0,
                                fill: true,
                            }],
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            scales: {
                                x: { ticks: { color: '#8ea0bb', maxTicksLimit: 6 }, grid: { color: 'rgba(142,160,187,0.08)' } },
                                y: { beginAtZero: true, ticks: { color: '#8ea0bb' }, grid: { color: 'rgba(142,160,187,0.08)' } },
                            },
                            plugins: {
                                legend: { display: false },
                                tooltip: { backgroundColor: '#101a2f', borderColor: '#2b3f5d', borderWidth: 1 },
                            },
                        },
                    });
                }

                renderAlertCenter(stats, sli);
            }

            function entriesFromCurrentScope() {
                return Array.isArray(filtered) ? filtered : [];
            }

            function buildShareTimeline(entries) {
                const points = [];
                const now = Date.now();
                const start = now - (48 * 3600 * 1000);
                const bucketMs = 4 * 3600 * 1000;
                const buckets = new Map();

                for (const row of entries) {
                    const src = String(row.sourceApi || '').toLowerCase();
                    if (!src.includes('share')) continue;
                    const ts = toTs(row.createdAt);
                    if (!ts || ts < start) continue;
                    const b = Math.floor((ts - start) / bucketMs);
                    buckets.set(String(b), (buckets.get(String(b)) || 0) + 1);
                }

                const labels = [];
                const series = [];
                const bucketCount = Math.ceil((48 * 3600 * 1000) / bucketMs);
                for (let i = 0; i < bucketCount; i++) {
                    const ts = start + (i * bucketMs);
                    labels.push(new Date(ts).toLocaleString([], { month: '2-digit', day: '2-digit', hour: '2-digit' }));
                    series.push(Number(buckets.get(String(i)) || 0));
                }
                return { labels, series };
            }

            function renderDbHealthFromServer() {
                const view = lastServerView || {};
                const db = view.db || {};
                const database = view.database || {};
                const rows = view.rows || {};
                const dbTotal = normalizeDbSizeLabel(db.sizeLabel || '-');
                setText('db-schema-value', database.schema || '-');
                setText('db-version-value', database.version || '-');
                setText('db-total-value', dbTotal);
                setText('db-rows-value', ((rows.logsLabel || 'logs -') + ' | ' + (rows.sharesLabel || 'shares -')));
                setText('share-db-rows', rows.sharesLabel || '-');
                setText('db-health-meta', 'Schema ' + (database.schema || '-') + ' | Version ' + (database.version || '-'));

                const logsValue = parseNumberLoose(rows.logsLabel || 0);
                const sharesValue = parseNumberLoose(rows.sharesLabel || 0);
                const usageMb = parseNumberLoose((db.meta || '').split('|')[0] || 0);
                const shareMb = parseNumberLoose((db.meta || '').split('|')[1] || 0);

                destroyChart(charts.dbHealth);
                const dbCtx = document.getElementById('chart-db-health');
                if (dbCtx) {
                    charts.dbHealth = new Chart(dbCtx, {
                        type: 'bar',
                        data: {
                            labels: ['log rows', 'share rows', 'logs MB', 'shares MB'],
                            datasets: [{
                                data: [logsValue, sharesValue, usageMb, shareMb],
                                backgroundColor: ['rgba(99,102,241,0.6)', 'rgba(34,197,94,0.6)', 'rgba(14,165,233,0.6)', 'rgba(245,158,11,0.6)'],
                                borderColor: ['#818cf8', '#22c55e', '#0ea5e9', '#f59e0b'],
                                borderWidth: 1,
                                borderRadius: 8,
                            }],
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            scales: {
                                y: { beginAtZero: true, ticks: { color: '#8ea0bb' }, grid: { color: 'rgba(142,160,187,0.08)' } },
                                x: { ticks: { color: '#c9d7ea' }, grid: { display: false } },
                            },
                            plugins: {
                                legend: { display: false },
                                tooltip: { backgroundColor: '#101a2f', borderColor: '#2b3f5d', borderWidth: 1 },
                            },
                        },
                    });
                }
            }

            function renderAlertCenter(stats, sli) {
                const alerts = [];
                const errorRate = stats.totalRuns > 0 ? ((100 * stats.errorRuns) / stats.totalRuns) : 0;
                if (errorRate >= 3) {
                    alerts.push({ tone: errorRate >= 8 ? 'bad' : 'warn', text: 'Error rate ' + fmtFloat(errorRate, 2) + '%. Check Error Intelligence categories.' });
                }
                if ((stats.p95AnalysisMs || 0) >= 2500) {
                    alerts.push({ tone: (stats.p95AnalysisMs || 0) >= 4500 ? 'bad' : 'warn', text: 'P95 latency ' + fmtMs(stats.p95AnalysisMs || 0) + '. Review row/file limits and DB indexes.' });
                }
                if ((stats.uploadSkipTooLarge || 0) > 0 || (stats.uploadSkipOverflow || 0) > 0) {
                    alerts.push({ tone: 'warn', text: 'Upload limit skips detected. Too large: ' + fmtInt(stats.uploadSkipTooLarge || 0) + ', overflow: ' + fmtInt(stats.uploadSkipOverflow || 0) + '.' });
                }
                if ((stats.bySecuritySignal.get('rate_limit') || 0) > 0 || (stats.bySecuritySignal.get('replay') || 0) > 0) {
                    alerts.push({ tone: 'warn', text: 'Security pressure visible (rate-limit/replay). Monitor abusive traffic patterns.' });
                }
                if (sli < 97) {
                    alerts.push({ tone: sli < 92 ? 'bad' : 'warn', text: 'SLI below target at ' + fmtFloat(sli, 2) + '%. Goal is >= 97%.' });
                }
                if (alerts.length === 0) {
                    alerts.push({ tone: 'ok', text: 'No active alert. Latency, error rate and security signals are in healthy range.' });
                }

                const container = document.getElementById('alerts-list');
                if (!container) return;
                container.innerHTML = alerts.map((item) => (
                    '<div class="insight insight-' + item.tone + '">' +
                    '<span class="insight-dot"></span>' +
                    '<p>' + escapeHtml(item.text) + '</p>' +
                    '</div>'
                )).join('');
                setText('alerts-meta', 'Active alerts: ' + fmtInt(alerts.filter((x) => x.tone !== 'ok').length));
            }

            function renderTable(entries) {
                const body = document.getElementById('runs-body');
                const count = document.getElementById('runs-count');
                if (!body || !count) return;

                if (!entries.length) {
                    body.innerHTML = '<tr><td colspan="10" class="muted-note">No runs matched current filters.</td></tr>';
                    applyRunColumnVisibility();
                    count.textContent = '0 rows';
                    return;
                }

                const sortedEntries = sortRowsByState(entries, runsSortState, 'runs');
                const take = Math.min(sortedEntries.length, 300);
                const rows = [];
                for (let i = 0; i < take; i++) {
                    const row = sortedEntries[i];
                    const status = String(row.status || '').toLowerCase() === 'ok' ? 'ok' : 'error';
                    const ts = toTs(row.createdAt);
                    const dateLabel = ts > 0 ? new Date(ts).toLocaleString() : '-';
                    const filesLabel = fmtInt(Number(row.filesProcessed || 0)) + '/' + fmtInt(Number(row.filesAttempted || 0));
                    rows.push(
                        '<tr>' +
                        '<td data-col="createdAt">' + escapeHtml(dateLabel) + '</td>' +
                        '<td data-col="status"><span class="pill ' + (status === 'ok' ? 'pill-ok' : 'pill-bad') + '">' + escapeHtml(status) + '</span></td>' +
                        '<td data-col="analysisMs">' + fmtMs(Number(row.analysisMs || 0)) + '</td>' +
                        '<td data-col="filesProcessed">' + escapeHtml(filesLabel) + '</td>' +
                        '<td data-col="bytesProcessed">' + fmtMb(Number(row.bytesProcessed || 0)) + '</td>' +
                        '<td data-col="rowsTotal">' + fmtInt(Number(row.rowsTotal || 0)) + '</td>' +
                        '<td data-col="country">' + escapeHtml(row.country || 'ZZ') + '</td>' +
                        '<td data-col="language">' + escapeHtml(row.language || 'unknown') + '</td>' +
                        '<td data-col="theme">' + escapeHtml((row.theme || 'dark') + '/' + (row.themeVariant || '-')) + '</td>' +
                        '<td data-col="sourceApi">' + escapeHtml(row.sourceApi || '-') + '</td>' +
                        '</tr>'
                    );
                }

                body.innerHTML = rows.join('');
                applyRunColumnVisibility();
                count.textContent = fmtInt(sortedEntries.length) + ' rows (' + fmtInt(take) + ' shown)';
            }

            function buildInsights(stats) {
                const list = [];
                const total = Math.max(0, stats.totalRuns);
                const errorRate = total > 0 ? ((100 * stats.errorRuns) / total) : 0;
                const avgMs = stats.analysisCount > 0 ? (stats.analysisSum / stats.analysisCount) : 0;
                const avgRows = total > 0 ? (stats.rowsTotal / total) : 0;
                const avgMb = total > 0 ? (stats.bytesProcessed / (1024 * 1024) / total) : 0;

                if (total === 0) {
                    list.push({ tone: 'warn', text: 'No data in the selected range. Try switching to All time.' });
                }

                if (errorRate >= 8) {
                    list.push({ tone: 'bad', text: 'Error rate is ' + fmtFloat(errorRate, 2) + '%. Investigate upload limits and invalid CSV rows.' });
                } else if (errorRate >= 3) {
                    list.push({ tone: 'warn', text: 'Error rate is elevated at ' + fmtFloat(errorRate, 2) + '%. Monitor logs for recurring causes.' });
                } else if (total > 0) {
                    list.push({ tone: 'ok', text: 'Error rate is healthy at ' + fmtFloat(errorRate, 2) + '%. Current reliability looks stable.' });
                }

                if (avgMs >= 4500) {
                    list.push({ tone: 'bad', text: 'Average analysis time is high (' + fmtMs(avgMs) + '). Consider tighter max rows/files or DB index tuning.' });
                } else if (avgMs >= 2500) {
                    list.push({ tone: 'warn', text: 'Analysis latency is moderate (' + fmtMs(avgMs) + '). Track during peak hours.' });
                } else if (total > 0) {
                    list.push({ tone: 'ok', text: 'Analysis latency is efficient (' + fmtMs(avgMs) + ' average).' });
                }

                if (avgMb >= 3.5) {
                    list.push({ tone: 'warn', text: 'Average upload size is ' + fmtFloat(avgMb, 2) + ' MB. Shared hosting headroom may tighten with traffic spikes.' });
                }

                if (avgRows >= 5000) {
                    list.push({ tone: 'warn', text: 'Average rows per run reached ' + fmtInt(avgRows) + '. Consider stricter CSV pre-filtering for faster UX.' });
                }

                const topCountry = Array.from(stats.byCountry.entries()).sort((a, b) => b[1] - a[1])[0];
                if (topCountry && total > 0) {
                    const sharePct = (100 * topCountry[1]) / total;
                    list.push({ tone: sharePct >= 60 ? 'warn' : 'ok', text: 'Top country ' + topCountry[0] + ' accounts for ' + fmtFloat(sharePct, 2) + '% of runs.' });
                }

                return list.slice(0, 8);
            }

            function renderInsights(stats) {
                const container = document.getElementById('insights-list');
                if (!container) return;
                const insights = buildInsights(stats);
                if (!insights.length) {
                    container.innerHTML = '<div class="empty">No insight available.</div>';
                    return;
                }
                container.innerHTML = insights.map((item) => (
                    '<div class="insight insight-' + item.tone + '">' +
                    '<span class="insight-dot"></span>' +
                    '<p>' + item.text + '</p>' +
                    '</div>'
                )).join('');
            }

            function parsePctLabel(label) {
                if (typeof label !== 'string') return 0;
                const m = label.match(/([0-9]+(?:\.[0-9]+)?)/);
                if (!m) return 0;
                const value = parseFloat(m[1]);
                return Number.isFinite(value) ? value : 0;
            }

            function parseBytesLabel(label) {
                const raw = String(label || '').trim().toUpperCase();
                if (!raw) return 0;
                const match = raw.match(/([0-9]+(?:[.,][0-9]+)?)\s*([KMGT]?B)?/);
                if (!match) return 0;
                const value = Number(String(match[1]).replace(',', '.'));
                if (!Number.isFinite(value) || value < 0) return 0;
                const unit = String(match[2] || 'B');
                const mul = unit === 'KB' ? 1024
                    : unit === 'MB' ? 1024 * 1024
                    : unit === 'GB' ? 1024 * 1024 * 1024
                    : unit === 'TB' ? 1024 * 1024 * 1024 * 1024
                    : 1;
                return value * mul;
            }

            function renderProcesses(rows) {
                const body = document.getElementById('server-proc-body');
                if (!body) return;
                if (!Array.isArray(rows) || rows.length === 0) {
                    body.innerHTML = '<tr><td colspan="6" class="muted-note">No process data available.</td></tr>';
                    return;
                }
                const sortedRows = sortRowsByState(rows, procSortState, 'proc').slice(0, 4);
                body.innerHTML = sortedRows.map((row) => {
                    const pid = parseNumberLoose(row.pid ?? row.pidSort ?? row.pidLabel ?? 0);
                    const cpu = Number.isFinite(Number(row.cpuPct))
                        ? Number(row.cpuPct)
                        : parsePctLabel(String(row.cpuLabel || row.cpu || '0'));
                    const mem = Number.isFinite(Number(row.memPct))
                        ? Number(row.memPct)
                        : parsePctLabel(String(row.memLabel || row.mem || '0'));
                    const rssBytesRaw = Number.isFinite(Number(row.rssBytes))
                        ? Number(row.rssBytes)
                        : parseNumberLoose(row.rssSort ?? 0);
                    const rssBytes = rssBytesRaw > 0
                        ? rssBytesRaw
                        : parseBytesLabel(String(row.rssLabel || row.rss || '0 B'));
                    const commandRaw = String(row.command || row.app || '-');
                    const command = commandRaw.length > 26 ? (commandRaw.slice(0, 25) + '…') : commandRaw;
                    return '<tr>' +
                        '<td>' + fmtInt(pid) + '</td>' +
                        '<td title="' + escapeHtml(commandRaw) + '">' + escapeHtml(command) + '</td>' +
                        '<td>' + fmtFloat(cpu, 1) + '</td>' +
                        '<td>' + fmtFloat(mem, 1) + '</td>' +
                        '<td>' + fmtMb(rssBytes) + '</td>' +
                        '<td>' + escapeHtml(String(row.elapsed || '-')) + '</td>' +
                        '</tr>';
                }).join('');
            }

            function applyServerStatus(view) {
                if (!view || typeof view !== 'object') return;
                lastServerView = view;
                const disk = view.disk || {};
                const ram = view.ram || {};
                const cpu = view.cpu || {};
                const db = view.db || {};
                const proc = (view.processes && Array.isArray(view.processes.processRows)) ? view.processes.processRows : [];

                const cpuPct = Number(cpu.meterPct || parsePctLabel(cpu.pctLabel || '0'));
                const ramPct = Number(ram.meterPct || parsePctLabel(ram.pctLabel || '0'));
                const diskPct = Number(disk.meterPct || parsePctLabel(disk.pctLabel || '0'));
                const dbPct = Number(db.meterPct || 0);

                setText('sv-cpu-value', cpu.pctLabel || '-');
                setText('sv-cpu-meta', cpu.meta || '-');
                setMeter('sv-cpu-meter', cpuPct);

                setText('sv-ram-value', ram.pctLabel || '-');
                setText('sv-ram-meta', ram.meta || '-');
                setMeter('sv-ram-meter', ramPct);

                setText('sv-disk-value', disk.pctLabel || '-');
                setText('sv-disk-meta', disk.meta || '-');
                setMeter('sv-disk-meter', diskPct);

                setText('sv-db-value', normalizeDbSizeLabel(db.sizeLabel || '-'));
                setText('sv-db-meta', db.meta || '-');
                setMeter('sv-db-meter', dbPct);

                lastProcessRows = proc.slice();
                renderProcesses(proc);
                renderDbHealthFromServer();
            }

            async function refreshServerStatus() {
                const note = document.getElementById('server-status-note');
                try {
                    const url = new URL(serverStatusUrl, window.location.origin);
                    url.searchParams.set('_ts', String(Date.now()));
                    const res = await fetch(url.toString(), {
                        method: 'GET',
                        credentials: 'same-origin',
                        cache: 'no-store',
                        headers: { 'Accept': 'application/json' },
                    });
                    if (!res.ok) throw new Error('http_' + res.status);
                    const payload = await res.json();
                    if (!payload || payload.ok !== true || !payload.view) throw new Error('invalid_payload');

                    applyServerStatus(payload.view);
                    if (note) {
                        const ts = payload.refreshed_at ? new Date(payload.refreshed_at) : new Date();
                        note.textContent = 'Last refresh: ' + ts.toLocaleString() + ' | Auto ' + serverStatusRefreshSec + 's';
                    }
                } catch (err) {
                    if (note) note.textContent = 'Live refresh pending. Retrying...';
                }
            }

            function exportFilteredCsv() {
                if (!filtered.length) return;
                const header = [
                    'created_at','status','analysis_ms','files_processed','files_attempted','bytes_processed','bytes_attempted',
                    'rows_total','parsed_rows','merged_records','country','language','theme','theme_variant','source_api'
                ];
                const escapeCsv = (v) => {
                    const s = String(v ?? '');
                    if (/[,"\n]/.test(s)) return '"' + s.replace(/"/g, '""') + '"';
                    return s;
                };
                const rows = [header.join(',')];
                for (const row of filtered) {
                    rows.push([
                        row.createdAt,
                        row.status,
                        row.analysisMs,
                        row.filesProcessed,
                        row.filesAttempted,
                        row.bytesProcessed,
                        row.bytesAttempted,
                        row.rowsTotal,
                        row.parsedRows,
                        row.mergedRecords,
                        row.country,
                        row.language,
                        row.theme,
                        row.themeVariant,
                        row.sourceApi,
                    ].map(escapeCsv).join(','));
                }

                const blob = new Blob([rows.join('\n')], { type: 'text/csv;charset=utf-8' });
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = 'ops_panel_filtered_' + new Date().toISOString().replace(/[:.]/g, '-') + '.csv';
                document.body.appendChild(a);
                a.click();
                a.remove();
                URL.revokeObjectURL(url);
            }

            function refreshAll() {
                applyFilters();
                const stats = summarizeEntries(filtered);
                renderCards(stats);
                renderCharts(stats);
                renderAdvancedSuite(stats);
                renderInsights(stats);
                renderTable(filtered);
                persistViewState();
            }

            function bindEvents() {
                const chips = Array.from(document.querySelectorAll('.range-chip'));
                chips.forEach((chip) => {
                    chip.addEventListener('click', () => {
                        currentRange = String(chip.dataset.range || 'all');
                        applyRangeChipUi();
                        refreshAll();
                    });
                });

                const search = document.getElementById('run-search');
                if (search) {
                    search.addEventListener('input', () => {
                        currentSearch = search.value || '';
                        refreshAll();
                    });
                }

                const refreshBtn = document.getElementById('btn-refresh');
                if (refreshBtn) {
                    refreshBtn.addEventListener('click', () => {
                        refreshAll();
                        refreshServerStatus();
                    });
                }

                const exportBtn = document.getElementById('btn-export-csv');
                if (exportBtn) {
                    exportBtn.addEventListener('click', exportFilteredCsv);
                }

                const saveViewBtn = document.getElementById('btn-save-view');
                if (saveViewBtn) {
                    saveViewBtn.addEventListener('click', () => {
                        persistViewState();
                        setSavedViewNote('View preferences saved.');
                    });
                }

                const resetViewBtn = document.getElementById('btn-reset-view');
                if (resetViewBtn) {
                    resetViewBtn.addEventListener('click', () => {
                        try { localStorage.removeItem(viewStateKey); } catch (_) { /* noop */ }
                        currentRange = 'all';
                        currentSearch = '';
                        runsSortState.key = 'createdAt';
                        runsSortState.dir = 'desc';
                        procSortState.key = 'cpuPct';
                        procSortState.dir = 'desc';
                        visibleRunColumns = new Set(runColumnKeys);
                        const searchNode = document.getElementById('run-search');
                        if (searchNode) searchNode.value = '';
                        applyRangeChipUi();
                        updateSortIndicators();
                        applyRunColumnVisibility();
                        refreshAll();
                        setSavedViewNote('View reset to defaults.');
                    });
                }

                const colToggles = Array.from(document.querySelectorAll('[data-col-toggle]'));
                colToggles.forEach((toggle) => {
                    toggle.addEventListener('change', () => {
                        const key = String(toggle.getAttribute('data-col-toggle') || '');
                        if (!runColumnKeys.includes(key)) return;
                        const checkedCount = Array.from(colToggles).filter((node) => node.checked).length;
                        if (!toggle.checked && checkedCount === 0) {
                            toggle.checked = true;
                            return;
                        }
                        if (toggle.checked) visibleRunColumns.add(key);
                        else visibleRunColumns.delete(key);
                        applyRunColumnVisibility();
                        persistViewState();
                    });
                });

                const sortButtons = Array.from(document.querySelectorAll('.th-sort'));
                sortButtons.forEach((btn) => {
                    btn.addEventListener('click', () => {
                        const table = String(btn.dataset.table || '');
                        const key = String(btn.dataset.key || '');
                        if (!table || !key) return;
                        const state = table === 'proc' ? procSortState : runsSortState;
                        if (state.key === key) {
                            state.dir = state.dir === 'asc' ? 'desc' : 'asc';
                        } else {
                            state.key = key;
                            state.dir = 'desc';
                        }
                        updateSortIndicators();
                        if (table === 'proc') {
                            renderProcesses(lastProcessRows);
                        } else {
                            renderTable(filtered);
                        }
                        persistViewState();
                    });
                });
            }

            restoreViewState();
            applyRangeChipUi();
            bindEvents();
            updateSortIndicators();
            applyRunColumnVisibility();
            setSavedViewNote('Local view state is active.');
            refreshAll();
            renderDbHealthFromServer();
            refreshServerStatus();
            window.setInterval(() => {
                if (document.hidden) return;
                refreshServerStatus();
            }, serverStatusRefreshMs);

            document.addEventListener('visibilitychange', () => {
                if (!document.hidden) {
                    refreshServerStatus();
                }
            });
        })();
    </script>
<?php endif; ?>
</body>
</html>
