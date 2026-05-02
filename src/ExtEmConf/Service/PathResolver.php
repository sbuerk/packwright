<?php

declare(strict_types=1);

namespace SBReleaser\ExtEmConf\Service;

/**
 * Resolves a list of user-supplied path tokens into a sorted, deduplicated
 * list of absolute `ext_emconf.php` file paths.
 *
 * Each token may be:
 *
 * - a literal file path (must exist),
 * - a directory (walked recursively for `ext_emconf.php` files), or
 * - a glob pattern (resolved via `glob()` with `GLOB_BRACE`).
 *
 * @internal not part of public API
 */
final class PathResolver
{
    private const GLOB_CHARS = ['*', '?', '['];

    /**
     * @param list<string> $paths
     * @return list<string>
     * @throws \InvalidArgumentException when a literal path does not exist
     */
    public function resolve(array $paths): array
    {
        $resolved = [];

        foreach ($paths as $path) {
            foreach ($this->resolveSingle($path) as $absolute) {
                $resolved[$absolute] = true;
            }
        }

        $files = array_keys($resolved);
        sort($files);

        return $files;
    }

    /**
     * @return list<string>
     */
    private function resolveSingle(string $path): array
    {
        if ($this->isGlob($path)) {
            return $this->resolveGlob($path);
        }

        if (is_dir($path)) {
            return $this->resolveDirectory($path);
        }

        if (is_file($path)) {
            return [$this->absolute($path)];
        }

        throw new \InvalidArgumentException(sprintf('Path "%s" does not exist.', $path));
    }

    private function isGlob(string $path): bool
    {
        foreach (self::GLOB_CHARS as $char) {
            if (str_contains($path, $char)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private function resolveGlob(string $pattern): array
    {
        $matches = glob($pattern, GLOB_BRACE);
        if ($matches === false) {
            return [];
        }

        $files = [];
        foreach ($matches as $match) {
            if (is_file($match)) {
                $files[] = $this->absolute($match);
            } elseif (is_dir($match)) {
                foreach ($this->resolveDirectory($match) as $found) {
                    $files[] = $found;
                }
            }
        }

        return $files;
    }

    /**
     * @return list<string>
     */
    private function resolveDirectory(string $directory): array
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS),
        );

        $files = [];
        /** @var \SplFileInfo $entry */
        foreach ($iterator as $entry) {
            if ($entry->isFile() && $entry->getFilename() === 'ext_emconf.php') {
                $files[] = $this->absolute($entry->getPathname());
            }
        }

        return $files;
    }

    private function absolute(string $path): string
    {
        $real = realpath($path);

        return $real !== false ? $real : $path;
    }
}
