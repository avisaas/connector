<?php

namespace AviSaaS\Connector\Classes;

final class DeleteRefusal
{
    public const STOREFRONT_THEME = "This is your storefront's theme. Switch to another theme in Design → Themes first.";

    public function __construct(
        private readonly array $installed,
        private readonly array $rows,
        private readonly array $storefrontCodes,
    ) {}

    public function reason(array $row, string $code, bool $required): ?string
    {
        $name = $row['label'];
        $package = strtolower($row['composer_package']);

        if (!$this->isListed($package)) {
            return $name." was not installed with Composer, so it can't be deleted from here.";
        }

        if ($required) {
            return $name." is required by TastyIgniter and can't be deleted.";
        }

        $dependents = implode(', ', $this->dependents($package));

        if ($dependents !== '') {
            return $name.' is still needed by '.$dependents.'. Delete '.$dependents.' first.';
        }

        return $row['is_theme'] && in_array($code, $this->storefrontCodes, true) ? self::STOREFRONT_THEME : null;
    }

    private function isListed(string $package): bool
    {
        foreach ($this->installed as $other) {
            if ($this->nameOf($other) === $package) {
                return true;
            }
        }

        return false;
    }

    private function dependents(string $package): array
    {
        $labels = array_change_key_case(array_column($this->rows, 'label', 'composer_package'));
        $dependents = [];

        foreach ($this->installed as $other) {
            $name = $this->nameOf($other);
            $require = is_array($other['require'] ?? null) ? array_change_key_case($other['require']) : [];

            if ($name !== $package && array_key_exists($package, $require)) {
                $dependents[] = $labels[$name] ?? $name;
            }
        }

        return $dependents;
    }

    private function nameOf(array $installed): string
    {
        return strtolower((string) ($installed['name'] ?? ''));
    }
}
