<?php

namespace AviSaaS\Connector\Classes;

final class SiteDomain
{
    private const LOOPBACK_HOSTS = ['localhost', '127.0.0.1', '::1', '[::1]'];

    public static function fromAppUrl(?string $appUrl): ?string
    {
        $appUrl = trim((string) $appUrl);

        if ($appUrl === '') {
            return null;
        }

        if (!preg_match('#^https?://#i', $appUrl)) {
            $appUrl = 'http://'.$appUrl;
        }

        $host = parse_url($appUrl, PHP_URL_HOST);

        if (!is_string($host) || $host === '') {
            return null;
        }

        return self::normalizeDomain($host);
    }

    public static function isUsable(?string $domain): bool
    {
        if ($domain === null) {
            return false;
        }

        return !in_array(strtolower($domain), self::LOOPBACK_HOSTS, true);
    }

    private static function normalizeDomain(string $domain): string
    {
        $domain = preg_replace('#^https?://#i', '', $domain);
        $domain = preg_replace('#^www\.#i', '', $domain);
        $domain = rtrim($domain, '/');
        $domain = preg_replace('#:(80|443)$#', '', $domain);

        return strtolower($domain);
    }
}
