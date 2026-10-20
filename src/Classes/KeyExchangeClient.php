<?php

namespace AviSaaS\Connector\Classes;

use Illuminate\Support\Facades\Http;

final class KeyExchangeClient
{
    public function exchange(string $exchangeUrl, string $licenceKey, string $domain): KeyExchangeResult
    {
        try {
            $response = Http::asForm()->acceptJson()
                ->connectTimeout(5)
                ->timeout(15)
                ->post($exchangeUrl, [
                    'license_key' => $licenceKey,
                    'domain' => $domain,
                ]);
        } catch (\Throwable) {
            return KeyExchangeResult::unreachable();
        }

        return KeyExchangeResult::fromResponse($response->status(), $response->json(), $response->header('Retry-After'));
    }
}
