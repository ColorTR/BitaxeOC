<?php

declare(strict_types=1);

namespace BitaxeOc\App;

final class UsageIngest
{
    private function __construct()
    {
    }

    public static function buildLogContext(
        array $config,
        array $securityConfig,
        array $loggingConfig,
        string $rateLimitIdentity,
        string $clientIp,
        string $clientCountryCode,
        string $userAgent,
        string $sourceDefault
    ): array {
        $maxBodyBytes = max(4 * 1024, (int)($loggingConfig['max_ingest_body_bytes'] ?? (64 * 1024)));
        $requestOverhead = max(0, (int)($securityConfig['max_request_overhead_bytes'] ?? (256 * 1024)));
        $maxRequestBytes = $maxBodyBytes + $requestOverhead;

        $contentLength = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
        if ($contentLength > $maxRequestBytes) {
            throw new HttpException('Istek boyutu limiti asildi.', 413);
        }

        $rateLimitRequests = max(1, (int)($loggingConfig['ingest_rate_limit_requests'] ?? 60));
        $rateLimitWindowSec = max(10, (int)($loggingConfig['ingest_rate_limit_window_sec'] ?? 300));
        Security::applyRateLimitConfig($securityConfig, 'usage_log_ingest', $rateLimitRequests, $rateLimitWindowSec, $rateLimitIdentity);

        $rawBody = file_get_contents('php://input');
        if (!is_string($rawBody) || trim($rawBody) === '') {
            throw new HttpException('Istek govdesi bos.', 400);
        }
        if (strlen($rawBody) > $maxRequestBytes) {
            throw new HttpException('Istek boyutu limiti asildi.', 413);
        }

        try {
            $decoded = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            throw new HttpException('Gecersiz JSON govdesi.', 400);
        }
        if (!is_array($decoded)) {
            throw new HttpException('Istek govdesi gecersiz.', 400);
        }

        $requestTimestamp = isset($decoded['request_ts']) ? (string)$decoded['request_ts'] : '';
        $requestNonce = isset($decoded['request_nonce']) ? (string)$decoded['request_nonce'] : '';
        Security::assertReplayProtection(
            $securityConfig,
            'usage_log_ingest',
            $requestTimestamp,
            $requestNonce,
            $rateLimitIdentity
        );

        $payload = $decoded['payload'] ?? null;
        if (!is_array($payload)) {
            throw new HttpException('Payload alani gecersiz.', 400);
        }

        $uploadSkippedRaw = $payload['upload_skipped'] ?? [];
        $uploadSkipped = is_array($uploadSkippedRaw) ? $uploadSkippedRaw : [];

        $browserLanguage = self::usageText(
            $payload['browser_language'] ?? (string)($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? ''),
            24
        );
        $selectedLanguage = self::usageText($payload['selected_language'] ?? '', 12);
        if ($selectedLanguage === '') {
            $selectedLanguage = self::inferLanguageCode($browserLanguage);
        }

        $countryCode = self::usageCountryCode($clientCountryCode);
        if ($countryCode === 'ZZ') {
            $countryHint = self::usageCountryCode($payload['country_hint'] ?? '');
            if ($countryHint !== 'ZZ') {
                $countryCode = $countryHint;
            }
        }

        return [
            'app_version' => substr((string)($payload['app_version'] ?? Version::appVersion($config)), 0, 40),
            'client_ip' => $clientIp,
            'country_code' => $countryCode,
            'user_agent' => substr($userAgent, 0, 1024),
            'source_api' => self::usageText($payload['source_api'] ?? $sourceDefault, 24),
            'request_status' => self::usageStatus($payload['request_status'] ?? 'ok'),
            'http_status' => self::usageInt($payload['http_status'] ?? 200, 0, 999),
            'analysis_ms' => self::usageInt($payload['analysis_ms'] ?? 0, 0, 3600000),
            'selected_language' => $selectedLanguage,
            'browser_language' => $browserLanguage,
            'selected_theme' => self::usageText($payload['selected_theme'] ?? '', 16),
            'selected_theme_variant' => self::usageText($payload['selected_theme_variant'] ?? '', 24),
            'timezone_name' => self::usageText($payload['timezone_name'] ?? '', 64),
            'timezone_offset_min' => self::usageInt($payload['timezone_offset_min'] ?? 0, -900, 900),
            'error_message' => substr((string)($payload['error_message'] ?? ''), 0, 220),
            'files_attempted' => self::usageInt($payload['files_attempted'] ?? 0, 0, 10000),
            'files_processed' => self::usageInt($payload['files_processed'] ?? 0, 0, 10000),
            'bytes_attempted' => self::usageInt($payload['bytes_attempted'] ?? 0, 0, 2147483647),
            'bytes_processed' => self::usageInt($payload['bytes_processed'] ?? 0, 0, 2147483647),
            'largest_upload_bytes' => self::usageInt($payload['largest_upload_bytes'] ?? 0, 0, 2147483647),
            'total_rows' => self::usageInt($payload['total_rows'] ?? 0, 0, 100000000),
            'parsed_rows' => self::usageInt($payload['parsed_rows'] ?? 0, 0, 100000000),
            'skipped_rows' => self::usageInt($payload['skipped_rows'] ?? 0, 0, 100000000),
            'merged_records' => self::usageInt($payload['merged_records'] ?? 0, 0, 100000000),
            'upload_skipped_non_csv' => self::usageInt($uploadSkipped['nonCsv'] ?? 0, 0, 100000),
            'upload_skipped_too_large' => self::usageInt($uploadSkipped['tooLarge'] ?? 0, 0, 100000),
            'upload_skipped_total_overflow' => self::usageInt($uploadSkipped['totalOverflow'] ?? 0, 0, 100000),
            'upload_skipped_upload_error' => self::usageInt($uploadSkipped['uploadError'] ?? 0, 0, 100000),
            'upload_skipped_count_overflow' => self::usageInt($uploadSkipped['countOverflow'] ?? 0, 0, 100000),
        ];
    }

    private static function usageInt(mixed $value, int $min = 0, int $max = 2147483647): int
    {
        if (!is_numeric($value)) {
            return $min;
        }

        $number = (int)round((float)$value);
        if ($number < $min) {
            return $min;
        }
        if ($number > $max) {
            return $max;
        }
        return $number;
    }

    private static function usageStatus(mixed $value): string
    {
        $status = strtolower(trim((string)$value));
        return $status === 'error' ? 'error' : 'ok';
    }

    private static function usageCountryCode(mixed $value): string
    {
        $raw = strtoupper(trim((string)$value));
        if ($raw === '' || $raw === 'ZZ' || $raw === 'XX' || $raw === '--') {
            return 'ZZ';
        }
        if (preg_match('/^[A-Z]{2}$/', $raw) === 1) {
            return $raw;
        }
        if (preg_match('/(?:^|[-_])([A-Z]{2})(?:$|[-_])/', $raw, $match) === 1) {
            $code = strtoupper((string)($match[1] ?? ''));
            if ($code !== '' && $code !== 'ZZ' && $code !== 'XX') {
                return $code;
            }
        }
        return 'ZZ';
    }

    private static function inferLanguageCode(string $browserLanguage): string
    {
        $raw = strtolower(trim($browserLanguage));
        if ($raw === '') {
            return '';
        }
        if (preg_match('/^[a-z]{2,3}(?:-[a-z0-9]{2,8})?/', $raw, $match) !== 1) {
            return '';
        }
        $code = (string)($match[0] ?? '');
        if ($code === '') {
            return '';
        }
        if (strlen($code) > 12) {
            $code = substr($code, 0, 12);
        }
        return $code;
    }

    private static function usageText(mixed $value, int $maxLen = 64): string
    {
        $text = trim((string)$value);
        if ($text === '') {
            return '';
        }
        if (strlen($text) > $maxLen) {
            $text = substr($text, 0, $maxLen);
        }
        return $text;
    }
}
