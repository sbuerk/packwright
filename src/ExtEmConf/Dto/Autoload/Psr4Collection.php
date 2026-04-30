<?php

declare(strict_types=1);

namespace SBReleaser\ExtEmConf\Dto\Autoload;

/**
 * An ordered, immutable collection of `Psr4Entry` items.
 *
 * Implements `Countable` and `IteratorAggregate` so it can be used
 * directly in `foreach` and `count()` calls. The iterator is keyed
 * by the PSR-4 namespace prefix.
 *
 * @implements \IteratorAggregate<string, Psr4Entry>
 *
 * @internal not part of public API
 */
final readonly class Psr4Collection implements \Countable, \IteratorAggregate
{
    /**
     * @param list<Psr4Entry> $entries
     */
    public function __construct(
        private array $entries = [],
    ) {}

    public function count(): int
    {
        return count($this->entries);
    }

    /**
     * @return \ArrayIterator<string, Psr4Entry>
     */
    public function getIterator(): \ArrayIterator
    {
        $keyed = [];
        foreach ($this->entries as $entry) {
            $keyed[$entry->namespace] = $entry;
        }

        return new \ArrayIterator($keyed);
    }

    /**
     * @return list<Psr4Entry>
     */
    public function all(): array
    {
        return $this->entries;
    }

    public function isEmpty(): bool
    {
        return $this->entries === [];
    }
}
