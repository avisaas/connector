<?php

namespace AviSaaS\Connector\Classes;

final class KeyMask
{
    public static function lastFour(string $key): string
    {
        return '•••• '.substr(trim($key), -4);
    }
}
