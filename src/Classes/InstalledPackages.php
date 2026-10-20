<?php

namespace AviSaaS\Connector\Classes;

use Igniter\Main\Classes\ThemeManager;
use Igniter\System\Classes\ExtensionManager;

final class InstalledPackages
{
    public function __construct(
        private readonly ExtensionManager $extensionManager,
        private readonly ThemeManager $themeManager,
        private readonly ComposerPackageName $composerPackageName,
    ) {}

    public function versions(): array
    {
        $versions = [];

        foreach ($this->extensionManager->listExtensions() as $code) {
            $package = $this->extensionPackage($code);

            if ($package !== null && !array_key_exists($package['name'], $versions)) {
                $versions[$package['name']] = $package['version'];
            }
        }

        foreach ($this->themePackages() as $package) {
            if (!array_key_exists($package['name'], $versions)) {
                $versions[$package['name']] = $package['version'];
            }
        }

        return $versions;
    }

    public function extensionCodes(): array
    {
        $codes = [];

        foreach ($this->extensionManager->listExtensions() as $code) {
            $package = $this->extensionPackage($code);

            if ($package !== null) {
                $codes[$package['name']] ??= $code;
            }
        }

        return $codes;
    }

    public function themeCodes(): array
    {
        $codes = [];

        foreach ($this->themePackages() as $code => $package) {
            $codes[$package['name']] = $code;
        }

        return $codes;
    }

    public function disabled(): array
    {
        $disabled = [];

        foreach ($this->extensionManager->listExtensions() as $code) {
            $package = $this->extensionManager->isDisabled($code) ? $this->extensionPackage($code) : null;

            if ($package !== null) {
                $disabled[] = $package['name'];
            }
        }

        return $disabled;
    }

    private function themePackages(): array
    {
        $packages = [];

        foreach ($this->themeManager->paths() as $code => $themePath) {
            $package = $this->composerPackageName->read($themePath.'/composer.json');

            if ($package !== null) {
                $packages[$code] = $package;
            }
        }

        return $packages;
    }

    private function extensionPackage(string $code): ?array
    {
        return $this->composerPackageName->read($this->extensionManager->path($code, 'composer.json'));
    }
}
