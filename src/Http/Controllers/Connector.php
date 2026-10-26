<?php

namespace AviSaaS\Connector\Http\Controllers;

use AviSaaS\Connector\Classes\CatalogueClient;
use AviSaaS\Connector\Classes\CatalogueFreshness;
use AviSaaS\Connector\Classes\CataloguePresenter;
use AviSaaS\Connector\Classes\CentralLicenseKey;
use AviSaaS\Connector\Classes\ComposerFiles;
use AviSaaS\Connector\Classes\ComposerPackageName;
use AviSaaS\Connector\Classes\ComposerRun;
use AviSaaS\Connector\Classes\ComposerRunKind;
use AviSaaS\Connector\Classes\ComposerRunResult;
use AviSaaS\Connector\Classes\ConnectionState;
use AviSaaS\Connector\Classes\DeleteRefusal;
use AviSaaS\Connector\Classes\ExtensionList;
use AviSaaS\Connector\Classes\FlashText;
use AviSaaS\Connector\Classes\InstallInstructions;
use AviSaaS\Connector\Classes\InstalledPackages;
use AviSaaS\Connector\Classes\KeyExchangeClient;
use AviSaaS\Connector\Classes\KeyExchangeResult;
use AviSaaS\Connector\Classes\KeyMask;
use AviSaaS\Connector\Classes\LicenceServer;
use AviSaaS\Connector\Classes\OwnedProductCount;
use AviSaaS\Connector\Classes\RowStatus;
use AviSaaS\Connector\Classes\SilentReconnectWindow;
use AviSaaS\Connector\Classes\SiteDomain;
use AviSaaS\Connector\Classes\ThemeActivation;
use AviSaaS\Connector\Models\Settings;
use Igniter\Admin\Classes\AdminController;
use Igniter\Admin\Facades\AdminMenu;
use Igniter\Admin\Facades\Template;
use Igniter\Flame\Composer\Manager;
use Igniter\Flame\Exception\FlashException;
use Igniter\Main\Classes\ThemeManager;
use Igniter\System\Classes\ExtensionManager;
use Illuminate\Http\RedirectResponse;

class Connector extends AdminController
{
    protected array|string|null $requiredPermissions = ['Avisaas.Connector.Manage'];

    private const NO_KEY_MESSAGE = 'Paste the licence key from your purchase email.';

    private const DOMAIN_NOT_USABLE_MESSAGE = "This site's web address isn't set yet. Set APP_URL to your real web address first: a licence key locks to the first website it is used on.";

    private const REPLACED_MESSAGE = 'Download access was replaced, perhaps by another copy of this website.';

    private const NOT_ALLOWED_MESSAGE = 'You are not allowed to install or update extensions.';

    private const REFUSED_MESSAGE = "This extension can't be installed from here right now. Reload the page.";

    private const BUSY_MESSAGE = 'Another install is running. Try again when it finishes.';

    private const TOGGLE_NOT_ALLOWED_MESSAGE = 'You are not allowed to enable or disable extensions.';

    private const DELETE_NOT_ALLOWED_MESSAGE = 'You are not allowed to delete extensions.';

    private const CHANGE_REFUSED_MESSAGE = "This extension can't be changed from here right now. Reload the page.";

    private const FAIL_VERBS = ['install' => 'installed', 'update' => 'updated', 'delete' => 'deleted'];

    public function __construct()
    {
        parent::__construct();

        AdminMenu::setContext('avisaas_connector', 'system');

        $this->addCss('avisaas.connector::css/connector.css', 'avisaas-connector-css');
        $this->addJs('avisaas.connector::js/connector.js', 'avisaas-connector-js');
    }

    public function index(): string
    {
        Template::setTitle('Marketplace');

        $catalogue = $this->resolveCatalogue();
        $result = $catalogue['result'];

        $this->vars['licence'] = $this->renderLicenceCard(
            reconnectMessage: $catalogue['reconnectMessage'],
            catalogueDocument: $result['ok'] ? $result['document'] : null,
            catalogueFetched: true,
        );

        if (!$result['ok']) {
            $this->vars['status'] = 'error';
            $this->vars['message'] = $result['error'];

            return $this->makeView('index');
        }

        try {
            $list = $this->buildList($result['document']);
        } catch (\InvalidArgumentException) {
            $this->vars['status'] = 'error';
            $this->vars['message'] = 'The licence server sent a catalogue this panel could not read.';

            return $this->makeView('index');
        }

        $identified = $result['document']['identified'];
        $repositoryUrl = $result['document']['repository_url'] ?? null;
        $catalogueSyncedAt = $result['document']['catalogue_synced_at'] ?? null;

        $this->vars['status'] = $identified ? 'ok' : 'undetermined';
        $this->vars['list'] = $list;
        $this->vars['canInstall'] = $this->canInstall();
        $this->vars['canToggle'] = $this->canToggle();
        $this->vars['installInstructions'] = new InstallInstructions(is_string($repositoryUrl) ? $repositoryUrl : null);
        $this->vars['catalogueAsOf'] = (new CatalogueFreshness())->display($catalogueSyncedAt, $this->timezone());

        if (!$identified) {
            $this->vars['message'] = $catalogue['reasonMessage'];
        }

        return $this->makeView('index');
    }

    public function onConnect(): array|RedirectResponse
    {
        $wasConnected = ConnectionState::isConnected(CentralLicenseKey::get(), Settings::get('download_credential'));
        $submittedKey = trim((string) post('license_key'));

        if ($submittedKey === '') {
            return $this->licenceCardError($wasConnected, self::NO_KEY_MESSAGE);
        }

        $domain = $this->currentDomain();

        if (!SiteDomain::isUsable($domain)) {
            return $this->licenceCardError($wasConnected, self::DOMAIN_NOT_USABLE_MESSAGE);
        }

        $exchange = (new KeyExchangeClient())->exchange($this->exchangeUrl(), $submittedKey, $domain);

        if (!$exchange->isConnected()) {
            return $this->licenceCardError($wasConnected, (string) $exchange->message());
        }

        $this->saveConnection($submittedKey, $exchange);

        return redirect(admin_url('avisaas/connector'));
    }

    public function onReconnect(): array|RedirectResponse
    {
        $licenceKey = CentralLicenseKey::get();

        if ($licenceKey === null) {
            return ['#connector-licence-card' => $this->renderLicenceCard(notConnectedError: self::NO_KEY_MESSAGE)];
        }

        $domain = $this->currentDomain();

        if (!SiteDomain::isUsable($domain)) {
            return ['#connector-licence-card' => $this->renderLicenceCard(reconnectMessage: self::DOMAIN_NOT_USABLE_MESSAGE)];
        }

        $exchange = (new KeyExchangeClient())->exchange($this->exchangeUrl(), $licenceKey, $domain);

        if (!$exchange->isConnected()) {
            return ['#connector-licence-card' => $this->renderLicenceCard(reconnectMessage: (string) $exchange->message())];
        }

        $this->saveConnection($licenceKey, $exchange);

        return redirect(admin_url('avisaas/connector'));
    }

    public function onInstall(): array|RedirectResponse
    {
        return $this->runComposer('install');
    }

    public function onUpdate(): array|RedirectResponse
    {
        return $this->runComposer('update');
    }

    public function onEnable(): array|RedirectResponse
    {
        return $this->toggle(enable: true);
    }

    public function onDisable(): array|RedirectResponse
    {
        return $this->toggle(enable: false);
    }

    public function onDelete(): array|RedirectResponse
    {
        throw_unless($this->canInstall(), FlashException::error(self::DELETE_NOT_ALLOWED_MESSAGE));

        [$entry, $repositoryUrl, $rows] = $this->lookUpEntry(post('product'));

        throw_if($entry === null, FlashException::error(self::CHANGE_REFUSED_MESSAGE));

        $code = $this->installedCode($entry);

        if ($code === null) {
            return $this->rowResponse($entry, notice: self::CHANGE_REFUSED_MESSAGE);
        }

        $row = $entry['row'];
        $refusal = $this->deleteRefusal($row, $code, $rows);

        if ($refusal !== null) {
            return $this->rowResponse($entry, notice: $refusal);
        }

        $purge = post('purge') === '1';
        $credential = $this->downloadCredential();

        $result = resolve(ComposerRun::class)->removePackage($row['composer_package'], $code, $row['is_theme'], $purge, $credential);

        return match ($result->kind) {
            ComposerRunKind::Done => $this->redirectWithSuccess($row['label'].' deleted'.($purge ? ', with its data' : '; its data was kept')),
            ComposerRunKind::Busy => $this->rowResponse($entry, notice: self::BUSY_MESSAGE),
            ComposerRunKind::Failed => $this->rowResponse($entry, fail: $this->failBox($entry, 'delete', $result, $repositoryUrl, $credential, $purge)),
        };
    }

    private function runComposer(string $action): array|RedirectResponse
    {
        throw_unless($this->canInstall(), FlashException::error(self::NOT_ALLOWED_MESSAGE));

        [$entry, $repositoryUrl] = $this->lookUpEntry(post('product'));

        throw_if($entry === null, FlashException::error(self::REFUSED_MESSAGE));

        $package = $entry['row']['composer_package'];

        if ($entry['status']['action'] !== $action || !ComposerPackageName::isValid($package)) {
            return $this->rowResponse($entry, notice: self::REFUSED_MESSAGE);
        }

        $credential = $this->downloadCredential();

        $result = resolve(ComposerRun::class)->requirePackage($package, $credential);

        return match ($result->kind) {
            ComposerRunKind::Done => redirect(admin_url('avisaas/connector')),
            ComposerRunKind::Busy => $this->rowResponse($entry, notice: self::BUSY_MESSAGE),
            ComposerRunKind::Failed => $this->rowResponse($entry, fail: $this->failBox($entry, $action, $result, $repositoryUrl, $credential)),
        };
    }

    private function toggle(bool $enable): array|RedirectResponse
    {
        throw_unless($this->canToggle(), FlashException::error(self::TOGGLE_NOT_ALLOWED_MESSAGE));

        [$entry] = $this->lookUpEntry(post('product'));

        throw_if($entry === null, FlashException::error(self::CHANGE_REFUSED_MESSAGE));

        $row = $entry['row'];
        $code = $this->installedCode($entry);
        $alreadyInState = $row['disabled'] === !$enable;

        if ($code === null || $row['is_theme'] || $alreadyInState) {
            return $this->rowResponse($entry, notice: self::CHANGE_REFUSED_MESSAGE);
        }

        $extensions = resolve(ExtensionManager::class);

        if (!$enable && $extensions->isRequired($code)) {
            return $this->rowResponse($entry, notice: $row['label']." is required by TastyIgniter and can't be turned off.");
        }

        if ($enable) {
            $extensions->installExtension($code);
        } else {
            $extensions->uninstallExtension($code, false);
        }

        return $this->redirectWithSuccess($row['label'].($enable ? ' enabled' : ' disabled'));
    }

    private function installedCode(array $entry): ?string
    {
        $row = $entry['row'];

        if (!$entry['status']['installed'] || !ComposerPackageName::isValid($row['composer_package'])) {
            return null;
        }

        $installed = resolve(InstalledPackages::class);
        $codes = $row['is_theme'] ? $installed->themeCodes() : $installed->extensionCodes();

        return $codes[strtolower($row['composer_package'])] ?? null;
    }

    private function deleteRefusal(array $row, string $code, array $rows): ?string
    {
        $themes = resolve(ThemeManager::class);
        $activeCode = $themes->getActiveThemeCode();

        return (new DeleteRefusal(
            resolve(Manager::class)->listInstalledPackages(fresh: true)->all(),
            $rows,
            array_filter([$activeCode, $themes->findParentCode($activeCode)]),
        ))->reason($row, $code, !$row['is_theme'] && resolve(ExtensionManager::class)->isRequired($code));
    }

    private function redirectWithSuccess(string $message): RedirectResponse
    {
        flash()->success(FlashText::fromPlain($message));

        return redirect(admin_url('avisaas/connector'));
    }

    private function downloadCredential(): ?string
    {
        $credential = Settings::get('download_credential');

        return is_string($credential) ? $credential : null;
    }

    private function lookUpEntry(mixed $key): array
    {
        $result = $this->fetchCatalogue();

        try {
            $list = $result['ok'] && is_string($key) ? $this->buildList($result['document']) : null;
        } catch (\InvalidArgumentException) {
            $list = null;
        }

        foreach ($list['entries'] ?? [] as $entry) {
            if ($entry['kind'] === 'row' && $entry['row']['key'] === $key) {
                $repositoryUrl = $result['document']['repository_url'] ?? null;

                return [$entry, is_string($repositoryUrl) ? $repositoryUrl : null, array_column($list['entries'], 'row')];
            }
        }

        return [null, null, []];
    }

    private function failBox(array $entry, string $action, ComposerRunResult $result, ?string $repositoryUrl, ?string $credential, ?bool $purge = null): array
    {
        $package = $entry['row']['composer_package'];
        $instructions = new InstallInstructions($repositoryUrl);

        return [
            'action' => $action,
            'verb' => self::FAIL_VERBS[$action],
            'restored' => $result->restored,
            'notStarted' => $result->notStarted,
            'reason' => $result->reason,
            'lines' => match (true) {
                $action === 'delete' => [$instructions->removeCommand($package)],
                $result->restored => $instructions->fallbackLines($package, Settings::get('composer_files_written') !== false, $credential),
                default => [InstallInstructions::FINISH_COMMAND],
            },
            'purge' => $purge,
        ];
    }

    private function rowResponse(array $entry, ?string $notice = null, ?array $fail = null): array
    {
        return ['~#connector-row-'.$entry['row']['key'] => view('avisaas.connector::connector._row', [
            'row' => $entry['row'],
            'status' => $entry['status'],
            'sub' => $entry['sub'],
            'grouped' => $entry['grouped'],
            'canInstall' => $this->canInstall(),
            'canToggle' => $this->canToggle(),
            'notice' => $notice,
            'fail' => $fail,
        ])->render()];
    }

    private function canInstall(): bool
    {
        return $this->getUser()?->hasPermission('Site.Updates') === true;
    }

    private function canToggle(): bool
    {
        return $this->getUser()?->hasPermission('Admin.Extensions') === true;
    }

    private function buildList(array $document): array
    {
        $installed = resolve(InstalledPackages::class);
        $themeCodes = $installed->themeCodes();
        $activeThemeCode = resolve(ThemeManager::class)->getActiveThemeCode();
        $activation = new ThemeActivation();

        $rows = array_map(
            fn (array $row): array => $row + ['activate_theme' => $activation->needed($row, $themeCodes, $activeThemeCode)],
            (new CataloguePresenter())->present($document, $installed->versions(), $installed->disabled()),
        );

        return (new ExtensionList(new RowStatus($this->timezone())))->build($rows);
    }

    private function timezone(): mixed
    {
        return setting('timezone', config('app.timezone'));
    }

    private function licenceCardError(bool $wasConnected, string $message): array
    {
        return ['#connector-licence-card' => $wasConnected
            ? $this->renderLicenceCard(changeKeyOpen: true, changeKeyError: $message)
            : $this->renderLicenceCard(notConnectedError: $message)];
    }

    private function resolveCatalogue(): array
    {
        $result = $this->fetchCatalogue();

        if (!$result['ok'] || $result['document']['identified'] !== false) {
            return ['result' => $result, 'reasonMessage' => null, 'reconnectMessage' => null];
        }

        $reason = $result['document']['reason'] ?? null;

        if ($reason !== 'unknown_credential') {
            return ['result' => $result, 'reasonMessage' => $this->reasonMessage($reason), 'reconnectMessage' => null];
        }

        $licenceKey = CentralLicenseKey::get();
        $domain = $this->currentDomain();

        if ($licenceKey === null || !SiteDomain::isUsable($domain)) {
            return ['result' => $result, 'reasonMessage' => $this->reasonMessage($reason), 'reconnectMessage' => null];
        }

        $now = time();
        $lastAttempt = Settings::get('silent_exchange_at');

        if (!SilentReconnectWindow::shouldAttempt(is_int($lastAttempt) ? $lastAttempt : null, $now)) {
            return ['result' => $result, 'reasonMessage' => null, 'reconnectMessage' => self::REPLACED_MESSAGE];
        }

        Settings::set('silent_exchange_at', $now);

        $exchange = (new KeyExchangeClient())->exchange($this->exchangeUrl(), $licenceKey, $domain);

        if (!$exchange->isConnected()) {
            return ['result' => $result, 'reasonMessage' => null, 'reconnectMessage' => $exchange->message()];
        }

        $this->saveConnection($licenceKey, $exchange);

        $refetched = $this->fetchCatalogue();

        if (!$refetched['ok'] || $refetched['document']['identified'] !== false) {
            return ['result' => $refetched, 'reasonMessage' => null, 'reconnectMessage' => null];
        }

        return ['result' => $refetched, 'reasonMessage' => $this->reasonMessage($refetched['document']['reason'] ?? null), 'reconnectMessage' => null];
    }

    private function reasonMessage(?string $reason): ?string
    {
        return match ($reason) {
            'no_credential' => null,
            'unknown_credential' => 'The credential was not recognised, so ownership could not be determined.',
            'license_inactive' => 'The licence is not active, so ownership could not be determined.',
            'throttled' => 'Too many attempts from this server. Try again in a few minutes.',
            'malformed' => 'The credential is not in the expected format.',
            default => 'Ownership could not be determined.',
        };
    }

    private function fetchCatalogue(): array
    {
        return (new CatalogueClient())->fetch(
            (new LicenceServer(config('avisaas-connector.licence_server')))->catalogueUrl(),
            Settings::get('download_credential'),
        );
    }

    private function exchangeUrl(): string
    {
        return (new LicenceServer(config('avisaas-connector.licence_server')))->exchangeUrl();
    }

    private function currentDomain(): ?string
    {
        return SiteDomain::fromAppUrl(config('app.url') ?: env('APP_URL'));
    }

    private function saveConnection(string $licenceKey, KeyExchangeResult $exchange): void
    {
        Settings::set([
            'license_key' => trim($licenceKey),
            'download_credential' => $exchange->credential(),
            'license_domain' => $exchange->domain(),
        ]);

        $written = (new ComposerFiles(base_path()))->write($exchange->repositoryUrl(), $exchange->credential());

        Settings::set('composer_files_written', $written);
    }

    private function renderLicenceCard(
        ?string $notConnectedError = null,
        bool $changeKeyOpen = false,
        ?string $changeKeyError = null,
        ?string $reconnectMessage = null,
        ?array $catalogueDocument = null,
        bool $catalogueFetched = false,
    ): string {
        $connected = ConnectionState::isConnected(CentralLicenseKey::get(), Settings::get('download_credential'));
        $document = $catalogueFetched ? $catalogueDocument : ($connected ? $this->fetchCatalogueDocumentOrNull() : null);

        return view('avisaas.connector::connector._licence', [
            'connected' => $connected,
            'domain' => $connected ? Settings::get('license_domain') : null,
            'keyMasked' => $connected ? KeyMask::lastFour((string) CentralLicenseKey::get()) : null,
            'ownedCount' => $document !== null ? OwnedProductCount::fromDocument($document) : null,
            'composerWriteFailed' => $connected && Settings::get('composer_files_written') === false,
            'notConnectedError' => $notConnectedError,
            'changeKeyOpen' => $changeKeyOpen || $changeKeyError !== null,
            'changeKeyError' => $changeKeyError,
            'reconnectMessage' => $reconnectMessage,
        ])->render();
    }

    private function fetchCatalogueDocumentOrNull(): ?array
    {
        $result = $this->fetchCatalogue();

        return $result['ok'] ? $result['document'] : null;
    }
}
