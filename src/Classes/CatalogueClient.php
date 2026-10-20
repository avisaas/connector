<?php

namespace AviSaaS\Connector\Classes;

use Illuminate\Support\Facades\Http;

final class CatalogueClient
{
    public function fetch(string $url, ?string $credential): array
    {
        if ($url === '') {
            return ['ok' => false, 'error' => 'No licence server address is set.'];
        }

        try {
            $request = Http::acceptJson()->connectTimeout(5)->timeout(10);

            if ($credential !== null && $credential !== '') {
                $request = $request->withBasicAuth($credential, '');
            }

            $response = $request->get($url);
        } catch (\Throwable) {
            return ['ok' => false, 'error' => 'Couldn\'t reach avisaas. Check this server\'s internet connection, or try again in a few minutes.'];
        }

        if (!$response->successful()) {
            return ['ok' => false, 'error' => sprintf('The licence server returned HTTP %d.', $response->status())];
        }

        $document = $response->json();

        if (!is_array($document)) {
            return ['ok' => false, 'error' => 'The licence server returned a response this panel could not read.'];
        }

        return ['ok' => true, 'document' => $document];
    }
}
