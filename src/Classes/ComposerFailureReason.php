<?php

namespace AviSaaS\Connector\Classes;

final class ComposerFailureReason
{
    public const NOT_FOUND = 'Composer was not found on this server.';

    public const COULD_NOT_RUN = 'Composer could not run on this server.';

    public const STOPPED = 'Composer stopped with an error.';

    public const NOT_AVAILABLE = "This extension isn't available to download for your licence yet. Please contact support.";

    private const MEANINGFUL = '/(Your requirements could not be resolved|\[[A-Za-z\\\\]*Exception\]|Problem \d+|could not be found|failed to open stream|curl error|HTTP\/\S+ 4\d\d|Could not authenticate|Permission denied|No space left)/i';

    private const NOISE = '/^(Warning\b|[\w:-]+ \[-)|could not be fully loaded|loaded from the local cache/';

    private const EXCEPTION_BLOCK = '/^In .+ line \S+:\R(?:[ \t]*\R)*((?:[ \t]*\S[^\r\n]*\R?)+)/m';

    
    private const WORD_CUT_BY_WRAP = '/(?<=^ {2}.{74}\S) {2}\R {2}(?=\S)/m';

    private const AVISAAS_DOWNLOAD_REFUSED = '~/p2/avisaas/[^"]*" file could not be downloaded \(HTTP/\S+ 4\d\d~';

    private const PROBLEM_ONE_BULLETS = '/^[ \t]*Problem 1[ \t]*\R((?:[ \t]*- .+(?:\R|\z))+)/m';

    private const RESOLUTION_STEP = '/-> satisfiable by (?!.* but )/';

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

        foreach ([$stderr, $stdout] as $output) {
            $reason = self::exceptionReason($output) ?? self::problemReason($output) ?? self::firstMeaningfulLine($output);

            if ($reason !== null) {
                return $reason;
            }
        }

        $errorLines = self::lines($stderr);

        return $errorLines === [] ? self::STOPPED : end($errorLines);
    }

    private static function exceptionReason(string $output): ?string
    {
        if (preg_match(self::EXCEPTION_BLOCK, $output, $block) !== 1) {
            return null;
        }

        $message = trim(preg_replace([self::WORD_CUT_BY_WRAP, '/\s+/'], ['', ' '], $block[1]) ?? $block[1]);

        return preg_match(self::AVISAAS_DOWNLOAD_REFUSED, $message) === 1 ? self::NOT_AVAILABLE : $message;
    }

    private static function problemReason(string $output): ?string
    {
        if (preg_match(self::PROBLEM_ONE_BULLETS, $output, $problem) !== 1) {
            return null;
        }

        preg_match_all('/^[ \t]*- (.+)$/m', $problem[1], $bullets);

        foreach ($bullets[1] as $bullet) {
            if (preg_match(self::RESOLUTION_STEP, $bullet) !== 1) {
                return $bullet;
            }
        }

        return end($bullets[1]);
    }

    private static function firstMeaningfulLine(string $output): ?string
    {
        foreach (self::lines($output) as $line) {
            if (preg_match(self::MEANINGFUL, $line) === 1) {
                return $line;
            }
        }

        return null;
    }

    private static function lines(string $output): array
    {
        return array_values(array_filter(
            array_map('trim', preg_split('/\R/', $output) ?: []),
            fn (string $line): bool => $line !== '' && preg_match(self::NOISE, $line) !== 1,
        ));
    }
}
