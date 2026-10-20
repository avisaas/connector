<?php

namespace AviSaaS\Connector\Classes;

use Closure;
use Igniter\Flame\Composer\Manager;
use Igniter\Flame\Database\Model;
use Igniter\Main\Classes\ThemeManager;
use Igniter\System\Actions\SettingsModel;
use Igniter\System\Classes\ExtensionManager;
use Igniter\System\Classes\PackageManifest;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\ExecutableFinder;

final class ComposerRun
{
    public const LOCK = 'avisaas-connector-composer';

    private const LOCK_SECONDS = 900;

    private const SETUP_FAILED = 'It was downloaded, but setting it up failed.';

    public function __construct(
        private readonly Manager $composer,
        private readonly ExtensionManager $extensions,
        private readonly ThemeManager $themes,
        private readonly PackageManifest $manifest,
    ) {}

    public function requirePackage(string $package, ?string $credential): ComposerRunResult
    {
        return $this->locked(fn (): ComposerRunResult => $this->execute($package, $credential));
    }

    public function removePackage(string $package, string $code, bool $theme, bool $purge, ?string $credential): ComposerRunResult
    {
        return $this->locked(fn (): ComposerRunResult => $this->remove($package, $code, $theme, $purge, $credential));
    }

    private function locked(Closure $run): ComposerRunResult
    {
        if (function_exists('set_time_limit')) {
            set_time_limit(0);
        }

        if (function_exists('ignore_user_abort')) {
            ignore_user_abort(true);
        }

        $lock = Cache::lock(self::LOCK, self::LOCK_SECONDS);

        if (!$lock->get()) {
            return ComposerRunResult::busy();
        }

        try {
            return $run();
        } finally {
            $lock->release();
        }
    }

    private function execute(string $package, ?string $credential): ComposerRunResult
    {
        try {
            $this->composer->install([$package]);
        } catch (\Throwable $throwable) {
            return ComposerRunResult::failed($this->failureReason($throwable, 'require', $package, $credential), restored: true);
        }

        try {
            $this->setUp($package);
        } catch (\Throwable $throwable) {
            logger()->error('avisaas connector: '.$package.' was downloaded but setting it up failed: '.$throwable::class.': '.ComposerFailureReason::redact($throwable->getMessage(), $credential));

            return ComposerRunResult::failed(self::SETUP_FAILED, restored: false);
        }

        return ComposerRunResult::done();
    }

    private function remove(string $package, string $code, bool $theme, bool $purge, ?string $credential): ComposerRunResult
    {
        if (!function_exists('proc_open')) {
            return ComposerRunResult::notStarted(ComposerFailureReason::COULD_NOT_RUN);
        }

        if (!$this->composerIsAvailable()) {
            return ComposerRunResult::notStarted(ComposerFailureReason::NOT_FOUND);
        }

        $turnedOff = false;
        $dataTouched = false;

        try {
            if (!$theme && !$this->extensions->isDisabled($code)) {
                $this->extensions->uninstallExtension($code, false);
                $turnedOff = true;
            }

            $dataTouched = $purge;

            $this->delete($code, $theme, $purge);
        } catch (\Throwable $throwable) {
            $reason = $this->failureReason($throwable, 'remove', $package, $credential);

            if ($dataTouched) {
                return ComposerRunResult::failed($reason, restored: false);
            }

            if ($turnedOff) {
                $this->extensions->updateInstalledExtensions($code, true);
            }

            return ComposerRunResult::failed($reason, restored: true);
        }

        return ComposerRunResult::done();
    }

    private function delete(string $code, bool $theme, bool $purge): void
    {
        if ($theme) {
            $this->themes->deleteTheme($code, $purge);

            return;
        }

        if ($purge) {
            $this->resetSettings($code);
        }

        $this->extensions->deleteExtension($code, $purge);
    }

    private function resetSettings(string $code): void
    {
        foreach ($this->extensions->findExtension($code)?->registerSettings() ?? [] as $item) {
            $model = $item['model'] ?? null;

            if (!is_string($model) || !is_a($model, Model::class, true)) {
                continue;
            }

            $settings = new $model;

            if ($settings->isClassExtendedWith(SettingsModel::class)) {
                $settings->resetDefault();
            }
        }
    }

    private function composerIsAvailable(): bool
    {
        return is_file(base_path('composer.phar')) || (new ExecutableFinder())->find('composer') !== null;
    }

    private function failureReason(\Throwable $throwable, string $command, string $package, ?string $credential): string
    {
        $process = $throwable instanceof ProcessFailedException ? $throwable->getProcess() : null;

        if ($process?->isStarted()) {
            $reason = ComposerFailureReason::from($process->getExitCode() ?? 1, $process->getErrorOutput(), $process->getOutput(), $credential);

            logger()->error('avisaas connector: composer '.$command.' '.$package.' failed: '.$reason);

            return $reason;
        }

        logger()->error('avisaas connector: composer '.$command.' '.$package.' could not run: '.$throwable::class.': '.ComposerFailureReason::redact($throwable->getMessage(), $credential));

        return ComposerFailureReason::COULD_NOT_RUN;
    }

    private function setUp(string $package): void
    {
        $wanted = strtolower($package);

        foreach ($this->composer->listInstalledPackages(fresh: true) as $code => $installed) {
            if (strtolower((string) ($installed['name'] ?? '')) !== $wanted || !is_string($code) || $code === '') {
                continue;
            }

            $path = $this->manifest->getPackagePath((string) ($installed['install-path'] ?? ''));
            $version = $installed['version'] ?? null;

            if ($installed['type'] === 'theme') {
                $this->themes->loadTheme($path);

                if (!$this->themes->installTheme($code, $version)) {
                    throw new \RuntimeException('The theme was not registered.');
                }

                return;
            }

            $this->extensions->loadExtension($path);
            $this->extensions->installExtension($code, $version);

            return;
        }

        throw new \RuntimeException('The package is not in the installed packages list.');
    }
}
