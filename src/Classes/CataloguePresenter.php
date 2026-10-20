<?php

namespace AviSaaS\Connector\Classes;

final class CataloguePresenter
{
    private const INSTALLED_STATES = ['installed', 'update_available'];

    public function present(array $document, array $installedVersions, array $disabledPackages = []): array
    {
        $this->assertShape($document);

        $installed = [];
        foreach ($installedVersions as $package => $version) {
            $installed[strtolower($package)] = $this->nullableString($version);
        }

        $disabled = array_flip(array_map('strtolower', $disabledPackages));

        $rows = [];

        foreach ($document['products'] as $product) {
            $this->assertProduct($product, $document['identified']);

            $advertisedVersion = $this->nullableString($product['advertised_version'] ?? null);
            [$state, $installedVersion] = $this->resolveInstallState($product, $installed, $advertisedVersion);
            $composerPackage = $product['composer_package'] ?? null;

            $rows[] = [
                'key' => $product['key'],
                'label' => $product['label'],
                'composer_package' => $composerPackage,
                'buy_url' => $product['buy_url'] ?? null,
                'icon' => $this->nullableArray($product['icon'] ?? null),
                'installed_version' => $installedVersion,
                'advertised_version' => $advertisedVersion,
                'state' => $state,
                'expires_at' => $this->nullableString($product['expires_at'] ?? null),
                'licence_state' => $this->licenceState($product),
                'disabled' => in_array($state, self::INSTALLED_STATES, true) && isset($disabled[strtolower($composerPackage)]),
                'is_theme' => $this->isTheme($composerPackage),
            ];
        }

        return $rows;
    }

    private function resolveInstallState(array $product, array $installed, ?string $advertisedVersion): array
    {
        if (!$product['owned']) {
            return [($product['buy_url'] ?? null) !== null ? 'buyable' : 'unavailable', null];
        }

        $package = $product['composer_package'] ?? null;
        if ($package === null) {
            return ['owned_unknown_package', null];
        }

        $package = strtolower($package);
        if (!array_key_exists($package, $installed)) {
            return ['owned_not_installed', null];
        }

        $installedVersion = $installed[$package];
        $state = $this->hasUpdate($advertisedVersion, $installedVersion) ? 'update_available' : 'installed';

        return [$state, $installedVersion];
    }

    private function licenceState(array $product): ?string
    {
        if (!$product['owned']) {
            return null;
        }

        return in_array($product['state'] ?? null, ['expired', 'stopped'], true) ? $product['state'] : 'active';
    }

    private function isTheme(mixed $package): bool
    {
        if (!is_string($package)) {
            return false;
        }

        $name = explode('/', strtolower($package), 2)[1] ?? '';

        return str_starts_with($name, 'ti-theme-') || str_ends_with($name, '-theme');
    }

    private function hasUpdate(?string $advertisedVersion, ?string $installedVersion): bool
    {
        $advertised = $this->normaliseForComparison($advertisedVersion);
        $installed = $this->normaliseForComparison($installedVersion);

        if ($advertised === null || $installed === null) {
            return false;
        }

        return version_compare($advertised, $installed, '>');
    }

    private function normaliseForComparison(?string $version): ?string
    {
        if ($version === null || $version === '') {
            return null;
        }

        $stripped = str_starts_with($version, 'v') ? substr($version, 1) : $version;

        return $stripped !== '' && ctype_digit($stripped[0]) ? $stripped : null;
    }

    private function nullableString(mixed $value): ?string
    {
        return is_string($value) ? $value : null;
    }

    private function nullableArray(mixed $value): ?array
    {
        return is_array($value) && $this->hasSafeIconValues($value) ? $value : null;
    }

    private function hasSafeIconValues(array $icon): bool
    {
        foreach ($icon as $key => $value) {
            if ($key === 'backgroundImage' && is_array($value)) {
                if (!is_string($value[0] ?? null) || !is_string($value[1] ?? null)) {
                    return false;
                }

                continue;
            }

            if (is_array($value)) {
                return false;
            }
        }

        return true;
    }

    private function assertShape(array $document): void
    {
        if (!array_key_exists('identified', $document) || !is_bool($document['identified'])) {
            throw new \InvalidArgumentException('The catalogue response is missing its identification flag.');
        }

        if (!array_key_exists('products', $document) || !is_array($document['products']) || !array_is_list($document['products'])) {
            throw new \InvalidArgumentException('The catalogue response has no product list.');
        }
    }

    private function assertProduct(mixed $product, bool $identified): void
    {
        if (!is_array($product)) {
            throw new \InvalidArgumentException('The catalogue response contains an invalid product entry.');
        }

        if (!array_key_exists('key', $product) || !is_string($product['key']) || $product['key'] === '') {
            throw new \InvalidArgumentException('The catalogue response contains a product with no key.');
        }

        if (!array_key_exists('label', $product) || !is_string($product['label']) || $product['label'] === '') {
            throw new \InvalidArgumentException('The catalogue response contains a product with no label.');
        }

        if (!array_key_exists('owned', $product) || !is_bool($product['owned'])) {
            throw new \InvalidArgumentException('The catalogue response contains a product with no ownership flag.');
        }

        if (!$identified && $product['owned']) {
            throw new \InvalidArgumentException('The catalogue response marks a product owned without an identified customer.');
        }
    }
}
