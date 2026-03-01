<?php

declare(strict_types=1);

namespace BitaxeOc\App;

use Throwable;

final class ApiBootstrap
{
    /**
     * @param list<string> $sections
     * @return array{
     *   config: array<string,mixed>,
     *   sections: array<string,array<string,mixed>>,
     *   clientContext: array{
     *     trustProxyHeaders: bool,
     *     clientIp: string,
     *     clientCountryCode: string,
     *     userAgent: string,
     *     rateLimitIdentity: string
     *   }
     * }
     */
    public static function loadRuntimeContext(array $sections = []): array
    {
        $config = self::loadConfig();
        $resolvedSections = [];
        foreach ($sections as $sectionName) {
            if (!is_string($sectionName) || $sectionName === '') {
                continue;
            }
            $resolvedSections[$sectionName] = self::section($config, $sectionName);
        }

        if (!array_key_exists('security', $resolvedSections)) {
            $resolvedSections['security'] = self::section($config, 'security');
        }

        return [
            'config' => $config,
            'sections' => $resolvedSections,
            'clientContext' => self::clientContext($resolvedSections['security']),
        ];
    }

    public static function loadConfig(): array
    {
        $config = require __DIR__ . '/Config.php';
        return is_array($config) ? $config : [];
    }

    public static function section(array $config, string $section): array
    {
        return is_array($config[$section] ?? null) ? $config[$section] : [];
    }

    /**
     * @return array{trustProxyHeaders: bool, clientIp: string, clientCountryCode: string, userAgent: string, rateLimitIdentity: string}
     */
    public static function clientContext(array $securityConfig): array
    {
        $trustProxyHeaders = (bool)($securityConfig['trust_proxy_headers'] ?? false);
        $clientIp = Security::getClientIp($trustProxyHeaders, $securityConfig);
        $clientCountryCode = Security::detectCountryCode($trustProxyHeaders, $securityConfig);
        $userAgent = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
        $rateLimitIdentity = self::buildRateLimitIdentity($clientIp, $userAgent);

        return [
            'trustProxyHeaders' => $trustProxyHeaders,
            'clientIp' => $clientIp,
            'clientCountryCode' => $clientCountryCode,
            'userAgent' => $userAgent,
            'rateLimitIdentity' => $rateLimitIdentity,
        ];
    }

    public static function buildRateLimitIdentity(string $clientIp, string $userAgent): string
    {
        return $clientIp . '|' . substr(hash('sha256', $userAgent), 0, 24);
    }

    public static function initRuntime(array $securityConfig, bool $startSession = false): void
    {
        Security::ensureRuntimeDirectories();
        if ($startSession) {
            Security::startSession($securityConfig);
        }
    }

    public static function assertPostAndSameOrigin(): void
    {
        Security::assertPostRequest();
        Security::assertSameOriginRequest();
    }

    /**
     * @throws HttpException
     */
    public static function readJsonBody(
        int $maxRequestBytes,
        string $emptyMessage = 'Istek govdesi bos.',
        string $sizeMessage = 'Istek boyut limiti asildi.',
        string $invalidJsonMessage = 'Gecersiz JSON govdesi.',
        string $invalidTypeMessage = 'JSON govdesi nesne olmali.'
    ): array
    {
        $contentLength = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
        if ($contentLength > $maxRequestBytes) {
            throw new HttpException($sizeMessage, 413);
        }

        $rawBody = file_get_contents('php://input');
        if (!is_string($rawBody) || trim($rawBody) === '') {
            throw new HttpException($emptyMessage, 400);
        }
        if (strlen($rawBody) > $maxRequestBytes) {
            throw new HttpException($sizeMessage, 413);
        }

        try {
            $decoded = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            throw new HttpException($invalidJsonMessage, 400);
        }

        if (!is_array($decoded)) {
            throw new HttpException($invalidTypeMessage, 400);
        }

        return $decoded;
    }
}
