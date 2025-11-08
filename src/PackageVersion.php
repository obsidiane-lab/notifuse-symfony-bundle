<?php

namespace Notifuse\SymfonyBundle;

final class PackageVersion
{
    private const FALLBACK_VERSION = '0.0.0';

    private static ?string $version = null;

    public static function getVersion(): string
    {
        if (self::$version !== null) {
            return self::$version;
        }

        $tag = $_SERVER['CI_COMMIT_TAG'] ?? getenv('CI_COMMIT_TAG') ?: self::resolveGitTag();
        if ($tag !== null && $tag !== '') {
            return self::$version = ltrim($tag, 'v');
        }

        return self::$version = self::FALLBACK_VERSION;
    }

    private static function resolveGitTag(): ?string
    {
        $projectDir = dirname(__DIR__);
        if (!is_dir($projectDir . '/.git')) {
            return null;
        }

        $descriptor = sprintf('cd %s && git describe --tags --exact-match 2>/dev/null', escapeshellarg($projectDir));
        $output = trim((string) shell_exec($descriptor));
        return $output === '' ? null : $output;
    }
}
