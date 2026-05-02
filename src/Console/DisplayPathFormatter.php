<?php

declare(strict_types=1);

namespace Packwright\Console;

/**
 * Rewrites absolute file paths for human-readable console output.
 *
 * Paths beneath the current working directory are returned with a
 * leading `./` prefix; paths elsewhere are returned unchanged. The
 * formatter is purely string-based and never touches the filesystem,
 * so symlinked working directories simply fall through to absolute
 * paths.
 *
 * @internal not part of public API
 */
final class DisplayPathFormatter
{
    public function format(string $absolutePath, ?string $workingDirectoryOverride = null): string
    {
        $cwd = $workingDirectoryOverride ?? (getcwd() ?: null);
        if ($cwd === null || $cwd === '') {
            return $absolutePath;
        }

        $cwd = rtrim($cwd, '/');
        if ($cwd === '') {
            return $absolutePath;
        }

        if ($absolutePath === $cwd) {
            return './';
        }

        $prefix = $cwd . '/';
        if (str_starts_with($absolutePath, $prefix)) {
            return './' . substr($absolutePath, strlen($prefix));
        }

        return $absolutePath;
    }
}
