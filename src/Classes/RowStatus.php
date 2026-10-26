<?php

namespace AviSaaS\Connector\Classes;

final class RowStatus
{
    private const INSTALLED_STATES = ['installed', 'update_available'];

    private const NOT_OWNED_STATES = ['buyable', 'unavailable'];

    public function __construct(
        private readonly mixed $timezone,
        private readonly ExpiryDate $expiryDate = new ExpiryDate(),
    ) {}

    public function describe(array $row): array
    {
        $installed = in_array($row['state'], self::INSTALLED_STATES, true);
        $status = $this->resolve($row, $installed);
        $href = null;

        if (in_array($status['action'], ['buy', 'renew'], true)) {
            $href = $this->safeUrl($row['buy_url']);

            if ($href === null) {
                $status['action'] = null;
            }
        }

        return $status + ['href' => $href, 'installed' => $installed, 'tabs' => $this->tabs($row, $installed)];
    }

    private function resolve(array $row, bool $installed): array
    {
        if ($row['licence_state'] === 'stopped') {
            return $this->status('warn', null, 'Stopped', $this->updatesEnded($row), 'renew');
        }

        if ($row['licence_state'] === 'expired') {
            return $this->status('warn', null, $this->updatesEnded($row), $installed ? $this->installedLine($row) : 'Not installed', 'renew');
        }

        if ($row['disabled']) {
            return $this->status('mute', 'fa-power-off', 'Disabled', $this->installedLine($row), 'enable');
        }

        return match ($row['state']) {
            'update_available' => $this->status('warn', 'fa-arrow-up', 'Update to '.$row['advertised_version'], $this->installedLine($row), 'update'),
            'installed' => $this->status('ok', 'fa-check', 'Up to date', $this->installedLine($row), null),
            'owned_not_installed' => $this->status('own', null, 'Owned', $this->notInstalledLine($row), 'install'),
            'owned_coming_soon' => $this->status('own', null, 'Coming soon', $this->withUpdatesUntil($row, 'Not installed'), null),
            'owned_unknown_package' => $this->status('own', null, 'Owned', $this->withUpdatesUntil($row, 'Install status unknown'), null),
            'free_not_installed' => $this->status('ok', null, 'Free', $this->latestLine($row), $row['composer_package'] === null ? null : 'install'),
            default => $this->status('mute', null, 'Not owned', $this->latestLine($row), 'buy'),
        };
    }

    private function status(string $tone, ?string $icon, string $label, ?string $line, ?string $action): array
    {
        return ['tone' => $tone, 'icon' => $icon, 'label' => $label, 'line' => $line, 'action' => $action];
    }

    private function tabs(array $row, bool $installed): array
    {
        $tabs = ['all'];

        if ($installed) {
            $tabs[] = 'installed';
        }

        if ($row['state'] === 'update_available' && in_array($row['licence_state'], ['active', 'free'], true)) {
            $tabs[] = 'updates';
        }

        if (in_array($row['state'], self::NOT_OWNED_STATES, true)) {
            $tabs[] = 'notowned';
        }

        if ($row['licence_state'] === 'free') {
            $tabs[] = 'free';
        }

        return $tabs;
    }

    private function updatesEnded(array $row): string
    {
        $date = $this->expiryDate->display($row['expires_at'], $this->timezone);

        return $date === null ? 'Updates ended' : 'Updates ended '.$date;
    }

    private function withUpdatesUntil(array $row, string $line): string
    {
        $date = $row['licence_state'] === 'active' ? $this->expiryDate->display($row['expires_at'], $this->timezone) : null;

        return $date === null ? $line : $line.' · Updates until '.$date;
    }

    private function installedLine(array $row): string
    {
        $line = trim('Installed '.$row['installed_version']);

        return $this->withUpdatesUntil($row, $row['licence_state'] === 'free' ? 'Free · '.$line : $line);
    }

    private function notInstalledLine(array $row): string
    {
        $version = (string) $row['advertised_version'];

        return $this->withUpdatesUntil($row, $version === '' ? 'Not installed' : 'Not installed · '.$version.' available');
    }

    private function latestLine(array $row): ?string
    {
        $version = (string) $row['advertised_version'];

        return $version === '' ? null : 'Latest '.$version;
    }

    private function safeUrl(mixed $url): ?string
    {
        return is_string($url) && preg_match('~^https?://~i', $url) === 1 ? $url : null;
    }
}
