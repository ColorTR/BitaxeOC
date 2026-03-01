<?php

declare(strict_types=1);

namespace BitaxeOc\App;

final class SecurityCountryLookup
{
    public static function detectCountryCode(
        bool $trustProxyHeaders,
        array $securityConfig,
        callable $isTrustedProxyRequest,
        callable $getClientIp
    ): string {
        if ($isTrustedProxyRequest($trustProxyHeaders, $securityConfig)) {
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
            $ip = (string)$getClientIp($trustProxyHeaders, $securityConfig);
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
            $ip = (string)$getClientIp($trustProxyHeaders, $securityConfig);
            if (
                filter_var($ip, FILTER_VALIDATE_IP) &&
                !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)
            ) {
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
