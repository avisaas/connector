<?php

namespace AviSaaS\Connector\Classes;

use AviSaaS\Connector\Models\Settings as ConnectorSettings;

final class CentralLicenseKey
{
    public static function get(): ?string
    {
        try {
            $value = ConnectorSettings::get('license_key');
        } catch (\Throwable) {
            
            return null;
        }

        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value !== '' ? $value : null;
    }
}
