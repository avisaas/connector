<?php

namespace AviSaaS\Connector\Classes;



final class FlashText
{
    public static function fromPlain(string $text): string
    {
        return self::escape(self::escape($text));
    }

    private static function escape(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', true);
    }
}
