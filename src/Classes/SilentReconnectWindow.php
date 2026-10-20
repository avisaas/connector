<?php

namespace AviSaaS\Connector\Classes;

final class SilentReconnectWindow
{
    public const WINDOW_SECONDS = 900;

    public static function shouldAttempt(?int $lastAttemptAt, int $now): bool
    {
        if ($lastAttemptAt === null) {
            return true;
        }

        return ($now - $lastAttemptAt) >= self::WINDOW_SECONDS;
    }
}
