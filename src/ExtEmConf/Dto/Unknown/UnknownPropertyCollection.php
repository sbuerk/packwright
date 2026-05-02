<?php

declare(strict_types=1);

namespace Packwright\ExtEmConf\Dto\Unknown;

/**
 * An ordered, immutable collection of `UnknownProperty` entries.
 *
 * Iteration is keyed by `$key` so consumers can use `foreach
 * ($collection as $key => $property)` directly. Storage preserves
 * insertion order; on duplicate keys the first occurrence wins.
 *
 * Implements `Countable` and `IteratorAggregate`.
 *
 * @implements \IteratorAggregate<string, UnknownProperty>
 *
 * @internal not part of public API
 */
final readonly class UnknownPropertyCollection implements \Countable, \IteratorAggregate
{
    /**
     * @param list<UnknownProperty> $properties
     */
    public function __construct(
        private array $properties = [],
    ) {}

    public function count(): int
    {
        return count($this->properties);
    }

    /**
     * @return \ArrayIterator<string, UnknownProperty>
     */
    public function getIterator(): \ArrayIterator
    {
        $keyed = [];
        foreach ($this->properties as $property) {
            if (!array_key_exists($property->key, $keyed)) {
                $keyed[$property->key] = $property;
            }
        }

        return new \ArrayIterator($keyed);
    }

    /**
     * @return list<UnknownProperty>
     */
    public function all(): array
    {
        return $this->properties;
    }

    public function isEmpty(): bool
    {
        return $this->properties === [];
    }

    public function has(string $key): bool
    {
        foreach ($this->properties as $property) {
            if ($property->key === $key) {
                return true;
            }
        }

        return false;
    }

    public function get(string $key): ?UnknownProperty
    {
        foreach ($this->properties as $property) {
            if ($property->key === $key) {
                return $property;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    public function keys(): array
    {
        $keys = [];
        $seen = [];
        foreach ($this->properties as $property) {
            if (!isset($seen[$property->key])) {
                $keys[] = $property->key;
                $seen[$property->key] = true;
            }
        }

        return $keys;
    }
}
