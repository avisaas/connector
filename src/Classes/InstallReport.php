<?php

namespace AviSaaS\Connector\Classes;

use Igniter\System\Classes\PackageManifest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class InstallReport
{
    private const MODULE_CODE = 'connector';

    private const EXTENSION_CODE = 'avisaas.connector';

    private const CONFIG_KEY = 'avisaas-connector.report_url';

    private const DEFAULT_URL = 'https://support.avisaas.com/modules/license_manager/api_report.php';

    private const SENT_KEY = 'avisaas_connector_install_sent';

    private const PENDING_NONCE_KEY = 'avisaas_connector_install_nonce';

    private const REQUEST_SLOT = 'avisaas_install_report_sent';

    public function sendIfDue(): void
    {
        try {
            $siteUrl = app()->runningInConsole() ? null : $this->siteUrl();
            
            if ($siteUrl === null || request()->attributes->has(self::REQUEST_SLOT) || !Cache::add(self::SENT_KEY, true, 86400)) {
                return;
            }

            request()->attributes->set(self::REQUEST_SLOT, true);

            $nonce = bin2hex(random_bytes(32));
            Cache::put(self::PENDING_NONCE_KEY, $nonce, 3600);

            $this->post($this->reportUrl(), [
                'report_version' => 2,
                'module' => self::MODULE_CODE,
                'site_url' => $siteUrl,
                'nonce' => $nonce,
            ]);
        } catch (\Throwable) {
            
        }
    }

    public function answer(Request $request): Response
    {
        $nonce = $request->query('nonce');
        $pending = Cache::get(self::PENDING_NONCE_KEY);
        if (!is_string($nonce) || !is_string($pending) || !hash_equals($pending, $nonce)) {
            return response('Not Found', 404);
        }

        Cache::forget(self::PENDING_NONCE_KEY);

        $manifest = resolve(PackageManifest::class);

        return response()->json([
            'module' => self::MODULE_CODE,
            'nonce' => $nonce,
            'version' => (string) $manifest->getVersion(self::EXTENSION_CODE),
            'core_version' => (string) $manifest->coreVersion(),
            'licence_state' => 'free',
        ]);
    }

    protected function post(string $url, array $fields): void
    {
        
        
        
        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($fields),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT => 5,
        ]);
        curl_exec($curl);
    }

    private function reportUrl(): string
    {
        return trim((string) config(self::CONFIG_KEY)) ?: self::DEFAULT_URL;
    }

    private function siteUrl(): ?string
    {
        $appUrl = trim((string) config('app.url'));
        if (preg_match('#^(?!https?://)[a-z][a-z0-9+.-]*://#i', $appUrl)) {
            return null;
        }

        $parts = parse_url(preg_match('#^https?://#i', $appUrl) ? $appUrl : 'http://'.$appUrl);
        if (!is_array($parts) || !isset($parts['host'])) {
            return null;
        }

        $scheme = strtolower($parts['scheme']);
        $port = isset($parts['port']) && $parts['port'] !== ($scheme === 'https' ? 443 : 80) ? ':'.$parts['port'] : '';
        $siteUrl = $scheme.'://'.strtolower($parts['host']).$port.rtrim($parts['path'] ?? '', '/');

        return filter_var($siteUrl, FILTER_VALIDATE_URL) === false ? null : $siteUrl;
    }
}
