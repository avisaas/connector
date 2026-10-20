<?php

namespace AviSaaS\Connector\Classes;

final class ComposerFailureReason
{
    public const NOT_FOUND = 'Composer was not found on this server.';

    public const COULD_NOT_RUN = 'Composer could not run on this server.';

    public const STOPPED = 'Composer stopped with an error.';

    private const MEANINGFUL = '/(Your requirements could not be resolved|\[[A-Za-z\\\\]*Exception\]|Problem \d+|could not be found|failed to open stream|curl error|HTTP\/\S+ 4\d\d|Could not authenticate|Permission denied|No space left)/i';

    private const MAX_LENGTH = 200;

    public static function from(int $exitCode, string $stderr, string $stdout, ?string $credential): string
    {
        $reason = self::redact(self::pick($exitCode, $stderr, $stdout), $credential);

        return mb_substr(trim($reason), 0, self::MAX_LENGTH);
    }

    public static function redact(string $text, ?string $credential): string
    {
        if ($credential !== null && $credential !== '') {
            $text = str_replace($credential, '***', $text);
        }

        return preg_replace('~(https?://)[^/@\s]+@~i', '$1***@', $text) ?? $text;
    }

    private static function pick(int $exitCode, string $stderr, string $stdout): string
    {
        if ($exitCode === 127 || preg_match('/command not found|\bcomposer[\'"]?: not found/i', $stderr) === 1) {
            return self::NOT_FOUND;
        }

        $errorLines = self::lines($stderr);

        foreach ([$errorLines, self::lines($stdout)] as $lines) {
            foreach ($lines as $line) {
                if (preg_match(self::MEANINGFUL, $line) === 1) {
                    return $line;
                }
            }
        }

        return $errorLines === [] ? self::STOPPED : end($errorLines);
    }

    private static function lines(string $output): array
    {
        return array_values(array_filter(
            array_map('trim', preg_split('/\R/', $output) ?: []),
            fn (string $line): bool => $line !== '',
        ));
    }
}
