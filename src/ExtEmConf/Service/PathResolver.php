<?php

declare(strict_types=1);

namespace Packwright\ExtEmConf\Service;

use Symfony\Component\Finder\Finder;

/**
 * Resolves a list of user-supplied path tokens into a sorted, deduplicated
 * list of absolute `ext_emconf.php` file paths.
 *
 * Each token may be:
 *
 * - a literal file path (must exist),
 * - a directory (walked recursively for `ext_emconf.php` files),
 * - a glob pattern resolved via `glob()` with `GLOB_BRACE`
 *   (supports `*`, `?`, `[...]`, `{a,b}` — single-segment only), or
 * - a recursive glob pattern containing a `**` path segment, matching
 *   zero or more path segments at the `**` position. Recursive globs
 *   are walked with Symfony's `Finder` and matched against a regex
 *   compiled from the pattern.
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
        if ($this->isRecursiveGlob($path)) {
            return $this->resolveRecursive($path);
        }

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

    private function isRecursiveGlob(string $path): bool
    {
        return preg_match('#(^|/)\*\*(/|$)#', $path) === 1;
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
    private function resolveRecursive(string $pattern): array
    {
        [$base, $tail] = $this->splitAtFirstGlobChar($pattern);
        if ($base === '') {
            $base = '.';
        }
        $base = rtrim($base, '/');
        if ($base === '') {
            $base = '/';
        }
        if (!is_dir($base)) {
            return [];
        }

        $regex = $this->compilePatternToRegex($tail);

        $finder = Finder::create()
            ->in($base)
            ->files()
            ->name('ext_emconf.php');

        $files = [];
        foreach ($finder as $file) {
            $relative = str_replace('\\', '/', $file->getRelativePathname());
            if (preg_match($regex, $relative) === 1) {
                $files[] = $this->absolute($file->getPathname());
            }
        }

        return $files;
    }

    /**
     * @return array{string, string}
     */
    private function splitAtFirstGlobChar(string $pattern): array
    {
        $first = PHP_INT_MAX;
        foreach (['*', '?', '[', '{'] as $char) {
            $position = strpos($pattern, $char);
            if ($position !== false && $position < $first) {
                $first = $position;
            }
        }
        if ($first === PHP_INT_MAX) {
            return [$pattern, ''];
        }
        $literalPrefix = substr($pattern, 0, $first);
        $slashPos = strrpos($literalPrefix, '/');
        if ($slashPos === false) {
            return ['', $pattern];
        }

        return [substr($pattern, 0, $slashPos), substr($pattern, $slashPos + 1)];
    }

    private function compilePatternToRegex(string $pattern): string
    {
        $tokens = explode('/', $pattern);
        $parts = [];
        foreach ($tokens as $token) {
            if ($token === '**') {
                $parts[] = '__GLOBSTAR__';
                continue;
            }
            $parts[] = $this->compileSegmentToRegex($token);
        }

        $joined = implode('/', $parts);
        $joined = (string)preg_replace('#^__GLOBSTAR__/#', '(?:.*/)?', $joined);
        $joined = (string)preg_replace('#/__GLOBSTAR__$#', '(?:/.*)?', $joined);
        $joined = (string)preg_replace('#/__GLOBSTAR__/#', '/(?:.*/)?', $joined);
        $joined = str_replace('__GLOBSTAR__', '.*', $joined);

        return '#^' . $joined . '$#';
    }

    private function compileSegmentToRegex(string $segment): string
    {
        $regex = '';
        $i = 0;
        $len = strlen($segment);
        while ($i < $len) {
            $char = $segment[$i];
            if ($char === '*') {
                $regex .= '[^/]*';
                $i++;
                continue;
            }
            if ($char === '?') {
                $regex .= '[^/]';
                $i++;
                continue;
            }
            if ($char === '[') {
                $closing = strpos($segment, ']', $i + 1);
                if ($closing !== false) {
                    $regex .= substr($segment, $i, $closing - $i + 1);
                    $i = $closing + 1;
                    continue;
                }
                $regex .= '\[';
                $i++;
                continue;
            }
            if ($char === '{') {
                $closing = strpos($segment, '}', $i + 1);
                if ($closing !== false) {
                    $alternatives = explode(',', substr($segment, $i + 1, $closing - $i - 1));
                    $regex .= '(?:' . implode('|', array_map(
                        static fn(string $alt): string => preg_quote($alt, '#'),
                        $alternatives,
                    )) . ')';
                    $i = $closing + 1;
                    continue;
                }
                $regex .= '\{';
                $i++;
                continue;
            }
            $regex .= preg_quote($char, '#');
            $i++;
        }

        return $regex;
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
