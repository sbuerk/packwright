<?php

declare(strict_types=1);

namespace Packwright\ExtEmConf\Dto\Constraints;

/**
 * An ordered, immutable collection of `Dependency` entries.
 *
 * Wraps the flat array that the reader builds from a `depends`,
 * `conflicts`, or `suggests` map so that typed iteration and indexed
 * lookup are available without exposing a raw array.
 *
 * Implements `Countable` and `IteratorAggregate` so it can be used
 * directly in `foreach` and `count()` calls.
 *
 * @implements \IteratorAggregate<string, Dependency>
 *
 * @internal not part of public API
 */
final readonly class DependencyCollection implements \Countable, \IteratorAggregate
{
    /**
     * @param list<Dependency> $dependencies
     */
    public function __construct(
        private array $dependencies = [],
    ) {}

    public function count(): int
    {
        return count($this->dependencies);
    }

    /**
     * @return \ArrayIterator<string, Dependency>
     */
    public function getIterator(): \ArrayIterator
    {
        $keyed = [];
        foreach ($this->dependencies as $dependency) {
            $keyed[$dependency->extensionKey] = $dependency;
        }

        return new \ArrayIterator($keyed);
    }

    /**
     * @return list<Dependency>
     */
    public function all(): array
    {
        return $this->dependencies;
    }

    public function isEmpty(): bool
    {
        return $this->dependencies === [];
    }
}
