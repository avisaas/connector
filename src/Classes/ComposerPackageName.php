<?php

namespace AviSaaS\Connector\Classes;

final class ComposerPackageName
{
    private const VALID = '~^[a-z0-9]([_.-]?[a-z0-9]+)*/[a-z0-9](([_.]?|-{0,2})[a-z0-9]+)*$~iD';

    public static function isValid(mixed $name): bool
    {
        return is_string($name) && preg_match(self::VALID, $name) === 1;
    }

    public function read(string $composerJsonPath): ?array
    {
        if (!is_readable($composerJsonPath)) {
            return null;
        }

        $composer = json_decode(file_get_contents($composerJsonPath), true);

        if (!is_array($composer) || !is_string($composer['name'] ?? null) || $composer['name'] === '') {
            return null;
        }

        return [
            'name' => strtolower($composer['name']),
            'version' => is_string($composer['version'] ?? null) ? $composer['version'] : null,
        ];
    }
}
