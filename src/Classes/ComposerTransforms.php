<?php

namespace AviSaaS\Connector\Classes;

final class ComposerTransforms
{
    private const OWNED_REPOSITORY_KEY = 'avisaas';

    public static function authJson(?string $currentJson, string $repositoryUrl, string $credential): string
    {
        $auth = self::decodeObject($currentJson, allowEmpty: true);

        if (!property_exists($auth, 'http-basic') || !($auth->{'http-basic'} instanceof \stdClass)) {
            $auth->{'http-basic'} = new \stdClass();
        }

        $auth->{'http-basic'}->{self::origin($repositoryUrl)} = (object) ['username' => $credential, 'password' => ''];

        return self::encode($auth);
    }

    public static function composerJson(string $currentJson, string $repositoryUrl): string
    {
        $composer = self::decodeObject($currentJson, allowEmpty: false);
        $normalizedUrl = rtrim($repositoryUrl, '/');
        $ours = (object) ['type' => 'composer', 'url' => $repositoryUrl];

        $existing = $composer->repositories ?? null;

        $composer->repositories = match (true) {
            $existing instanceof \stdClass => self::mergeIntoObject($existing, $ours, $normalizedUrl),
            is_array($existing) => self::mergeIntoList($existing, $ours, $normalizedUrl),
            default => [$ours],
        };

        return self::encode($composer);
    }

    public static function origin(string $url): string
    {
        $host = (string) parse_url($url, PHP_URL_HOST);
        $port = parse_url($url, PHP_URL_PORT);

        return is_int($port) ? "{$host}:{$port}" : $host;
    }

    private static function mergeIntoObject(\stdClass $existing, \stdClass $ours, string $normalizedUrl): \stdClass
    {
        $merged = new \stdClass();
        $merged->{self::OWNED_REPOSITORY_KEY} = $ours;

        foreach ($existing as $key => $value) {
            if ($key === self::OWNED_REPOSITORY_KEY || self::isSameRepository($value, $normalizedUrl)) {
                continue;
            }

            $merged->{$key} = $value;
        }

        return $merged;
    }

    private static function mergeIntoList(array $existing, \stdClass $ours, string $normalizedUrl): array
    {
        $kept = array_values(array_filter(
            $existing,
            fn (mixed $value): bool => !self::isSameRepository($value, $normalizedUrl),
        ));

        array_unshift($kept, $ours);

        return $kept;
    }

    private static function isSameRepository(mixed $value, string $normalizedUrl): bool
    {
        return $value instanceof \stdClass
            && is_string($value->url ?? null)
            && rtrim($value->url, '/') === $normalizedUrl;
    }

    private static function decodeObject(?string $json, bool $allowEmpty): \stdClass
    {
        $trimmed = trim((string) $json);

        if ($trimmed === '') {
            if ($allowEmpty) {
                return new \stdClass();
            }

            throw new \InvalidArgumentException('Expected JSON content is empty.');
        }

        $decoded = json_decode($trimmed, false);

        if (!$decoded instanceof \stdClass) {
            throw new \InvalidArgumentException('Expected the content to be a JSON object.');
        }

        return $decoded;
    }

    private static function encode(\stdClass $value): string
    {
        return json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n";
    }
}
