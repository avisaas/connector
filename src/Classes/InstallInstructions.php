<?php

namespace AviSaaS\Connector\Classes;

final class InstallInstructions
{
    public const FINISH_COMMAND = 'php artisan igniter:up';

    public function __construct(private readonly ?string $repositoryUrl) {}

    public function requireCommand(string $package): string
    {
        return 'composer require '.$package;
    }

    public function removeCommand(string $package): string
    {
        return 'composer remove '.$package;
    }

    public function fallbackLines(string $package, bool $composerFilesWritten, ?string $credential): array
    {
        return [
            ...$this->setupLines($composerFilesWritten, $credential),
            $this->requireCommand($package),
            self::FINISH_COMMAND,
        ];
    }

    private function setupLines(bool $composerFilesWritten, ?string $credential): array
    {
        if ($composerFilesWritten || !$this->hasUsableRepository() || $credential === null || $credential === '') {
            return [];
        }

        return [
            'composer config repositories.avisaas composer '.$this->repositoryUrl,
            'composer config --auth http-basic.'.ComposerTransforms::origin($this->repositoryUrl).' '.$credential.' ""',
        ];
    }

    private function hasUsableRepository(): bool
    {
        if ($this->repositoryUrl === null) {
            return false;
        }

        $scheme = parse_url($this->repositoryUrl, PHP_URL_SCHEME);
        $host = parse_url($this->repositoryUrl, PHP_URL_HOST);

        return is_string($scheme)
            && in_array(strtolower($scheme), ['http', 'https'], true)
            && is_string($host)
            && $host !== '';
    }
}
