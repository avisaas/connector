<?php

namespace AviSaaS\Connector\Classes;

final class ConnectionState
{
    public static function isConnected(?string $licenceKey, mixed $downloadCredential): bool
    {
        if ($licenceKey === null) {
            return false;
        }

        return is_string($downloadCredential) && trim($downloadCredential) !== '';
    }
}
