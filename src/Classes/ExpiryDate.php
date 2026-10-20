<?php

namespace AviSaaS\Connector\Classes;

final class ExpiryDate
{
    private const DISPLAY_FORMAT = 'j M Y';

    private const BARE_FORMAT = 'Y-m-d H:i:s';

    public function display(mixed $expiresAt, mixed $timezone): ?string
    {
        if (!is_string($expiresAt) || $expiresAt === '' || !is_string($timezone)) {
            return null;
        }

        try {
            
            
            $instant = $this->parse(self::BARE_FORMAT, $expiresAt, new \DateTimeZone($timezone))
                ?? $this->parse(DATE_ATOM, $expiresAt);

            return $instant?->setTimezone(new \DateTimeZone($timezone))->format(self::DISPLAY_FORMAT);
        } catch (\Throwable) {
            return null;
        }
    }

    private function parse(string $format, string $value, ?\DateTimeZone $zone = null): ?\DateTimeImmutable
    {
        $instant = \DateTimeImmutable::createFromFormat($format, $value, $zone);
        $errors = \DateTimeImmutable::getLastErrors();

        if ($instant === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            return null;
        }

        return $instant;
    }
}
