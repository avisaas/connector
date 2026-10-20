<?php

namespace AviSaaS\Connector\Classes;

final class LicenceServer
{
    public const BUILT_IN_BASE = 'https://support.avisaas.com';

    private const LEGACY_CATALOGUE_SUFFIX = '/avisaas/catalogue.json';

    private readonly string $base;

    public function __construct(?string $override)
    {
        $this->base = $this->resolveBase($override);
    }

    public function catalogueUrl(): string
    {
        return $this->base.'/avisaas/catalogue.json';
    }

    public function exchangeUrl(): string
    {
        return $this->base.'/avisaas/key-exchange';
    }

    private function resolveBase(?string $override): string
    {
        $trimmed = trim((string) $override);

        if ($trimmed === '') {
            return self::BUILT_IN_BASE;
        }

        $trimmed = rtrim($trimmed, '/');

        if (str_ends_with($trimmed, self::LEGACY_CATALOGUE_SUFFIX)) {
            $trimmed = substr($trimmed, 0, -strlen(self::LEGACY_CATALOGUE_SUFFIX));
        }

        return $trimmed;
    }
}
