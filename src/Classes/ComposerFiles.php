<?php

namespace AviSaaS\Connector\Classes;

final class ComposerFiles
{
    public function __construct(private readonly string $siteRoot) {}

    public function write(string $repositoryUrl, string $credential): bool
    {
        $composerPath = $this->path('composer.json');
        $authPath = $this->path('auth.json');

        if (!is_file($composerPath)) {
            return false;
        }

        $currentComposerJson = @file_get_contents($composerPath);

        if ($currentComposerJson === false) {
            return false;
        }

        $currentAuthJson = is_file($authPath) ? @file_get_contents($authPath) : null;

        if ($currentAuthJson === false) {
            return false;
        }

        try {
            $newComposerJson = ComposerTransforms::composerJson($currentComposerJson, $repositoryUrl);
            $newAuthJson = ComposerTransforms::authJson($currentAuthJson, $repositoryUrl, $credential);
        } catch (\InvalidArgumentException) {
            return false;
        }

        return $this->writeAtomically($authPath, $newAuthJson)
            && $this->writeAtomically($composerPath, $newComposerJson);
    }

    private function path(string $filename): string
    {
        return rtrim($this->siteRoot, '/').'/'.$filename;
    }

    private function writeAtomically(string $path, string $contents): bool
    {
        $mode = is_file($path) ? (fileperms($path) & 0777) : (0666 & ~umask());
        $directory = dirname($path);
        $canonicalDirectory = realpath($directory);

        $tempPath = @tempnam($directory, '.composer-files-');

        if ($tempPath === false || $canonicalDirectory === false || dirname($tempPath) !== $canonicalDirectory) {
            if (is_string($tempPath)) {
                @unlink($tempPath);
            }

            return false;
        }

        if (@file_put_contents($tempPath, $contents) === false) {
            @unlink($tempPath);

            return false;
        }

        if (!@chmod($tempPath, $mode) || !@rename($tempPath, $path)) {
            @unlink($tempPath);

            return false;
        }

        return true;
    }
}
