<?php

declare(strict_types=1);

namespace SBReleaser\ExtEmConf\Dto\Autoload;

/**
 * An ordered, immutable collection of `ClassmapEntry` items.
 *
 * Implements `Countable` and `IteratorAggregate` so it can be used
 * directly in `foreach` and `count()` calls.
 *
 * @implements \IteratorAggregate<int, ClassmapEntry>
 *
 * @internal not part of public API
 */
final readonly class ClassmapCollection implements \Countable, \IteratorAggregate
{
    /**
     * @param list<ClassmapEntry> $entries
     */
    public function __construct(
        private array $entries = [],
    ) {}

    public function count(): int
    {
        return count($this->entries);
    }

    /**
     * @return \ArrayIterator<int, ClassmapEntry>
     */
    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->entries);
    }

    /**
     * @return list<ClassmapEntry>
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
