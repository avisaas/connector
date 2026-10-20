<?php

namespace AviSaaS\Connector\Classes;

final class CatalogueFreshness
{
    private const DISPLAY_FORMAT = 'j M Y, H:i T';

    public function display(mixed $syncedAt, mixed $timezone): ?string
    {
        if (!is_string($syncedAt) || $syncedAt === '' || !is_string($timezone)) {
            return null;
        }

        try {
            $instant = \DateTimeImmutable::createFromFormat(DATE_ATOM, $syncedAt);
            $errors = \DateTimeImmutable::getLastErrors();

            if ($instant === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
                return null;
            }

            $when = $instant->setTimezone(new \DateTimeZone($timezone))->format(self::DISPLAY_FORMAT);
        } catch (\Throwable) {
            return null;
        }

        return 'Catalogue as of '.$when;
    }
}
