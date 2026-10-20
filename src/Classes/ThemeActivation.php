<?php

namespace AviSaaS\Connector\Classes;

final class ThemeActivation
{
    private const INSTALLED_STATES = ['installed', 'update_available'];

    public function needed(array $row, array $themeCodes, ?string $activeCode): bool
    {
        if (!$row['is_theme'] || !is_string($row['composer_package']) || !in_array($row['state'], self::INSTALLED_STATES, true)) {
            return false;
        }

        $code = $themeCodes[strtolower($row['composer_package'])] ?? null;

        return $code !== null && $code !== $activeCode;
    }
}
