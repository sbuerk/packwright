<?php

declare(strict_types=1);

namespace Packwright\ExtEmConf\Dto;

use Composer\Semver\VersionParser;
use Packwright\ExtEmConf\Enum\VersionFormat;

/**
 * Immutable value object representing a semantic version number.
 *
 * Used for both extension versions stored in `ext_emconf.php` and for
 * TYPO3 core version numbers passed to the validator and normalizer.
 *
 * Create instances via the named constructor `Version::fromString()`
 * which delegates parsing and validation to `composer/semver`.
 * Direct construction is available when all parts are already known.
 *
 * Implements `Stringable` with the default `x.y.z` representation.
 * Alternative formats are available via `format()`.
 *
 * @internal not part of public API
 */
final readonly class Version implements \Stringable
{
    public function __construct(
        public int $major,
        public int $minor,
        public int $patch,
        public int $build = 0,
        public ?string $stability = null,
    ) {}

    /**
     * Creates a Version from a raw version string.
     *
     * Uses `Composer\Semver\VersionParser` to normalize and validate the
     * input. Throws `\UnexpectedValueException` for strings that cannot
     * be parsed as a valid version — callers are responsible for catching
     * this when processing untrusted input (e.g. in the reader service).
     */
    public static function fromString(string $raw): self
    {
        $parser = new VersionParser();
        $normalized = $parser->normalize($raw);

        $versionPart = str_contains($normalized, '-')
            ? explode('-', $normalized, 2)[0]
            : $normalized;

        $parts = array_pad(explode('.', $versionPart, 4), 4, '0');

        $stabilityStr = VersionParser::parseStability($raw);

        return new self(
            major: (int)$parts[0],
            minor: (int)$parts[1],
            patch: (int)$parts[2],
            build: (int)$parts[3],
            stability: $stabilityStr !== 'stable' ? $stabilityStr : null,
        );
    }

    /**
     * Returns the version in the requested format.
     *
     * - `XYZ`           → `1.2.3`
     * - `XYZ_D`         → `1.2.3.0`
     * - `XYZ_STABILITY` → `1.2.3-alpha` (falls back to `x.y.z` when
     *                      no stability label is set)
     */
    public function format(VersionFormat $format = VersionFormat::XYZ): string
    {
        return match ($format) {
            VersionFormat::XYZ => "{$this->major}.{$this->minor}.{$this->patch}",
            VersionFormat::XYZ_D => "{$this->major}.{$this->minor}.{$this->patch}.{$this->build}",
            VersionFormat::XYZ_STABILITY => $this->stability !== null
                ? "{$this->major}.{$this->minor}.{$this->patch}-{$this->stability}"
                : "{$this->major}.{$this->minor}.{$this->patch}",
        };
    }

    public function __toString(): string
    {
        return $this->format(VersionFormat::XYZ);
    }
}
