<?php

declare(strict_types=1);

namespace Packwright\ExtEmConf\Result;

use Packwright\ExtEmConf\Enum\IssueSeverity;

/**
 * An ordered, immutable collection of `Issue` entries.
 *
 * Wraps the flat array that read, validate, and normalize services
 * accumulate so that result DTOs never expose a raw array. Items are
 * positional (no stable identifier — multiple issues on the same path
 * are common), so `has`/`get` by identifier do not apply. Convenience
 * filters are provided for the common access patterns.
 *
 * Implements `Countable` and `IteratorAggregate` so it can be used
 * directly in `foreach` and `count()` calls.
 *
 * @implements \IteratorAggregate<int, Issue>
 *
 * @internal not part of public API
 */
final readonly class IssueCollection implements \Countable, \IteratorAggregate
{
    /**
     * @param list<Issue> $issues
     */
    public function __construct(
        private array $issues = [],
    ) {}

    public function count(): int
    {
        return count($this->issues);
    }

    /**
     * @return \ArrayIterator<int, Issue>
     */
    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->issues);
    }

    /**
     * @return list<Issue>
     */
    public function all(): array
    {
        return $this->issues;
    }

    public function isEmpty(): bool
    {
        return $this->issues === [];
    }

    public function merge(self $other): self
    {
        if ($other->isEmpty()) {
            return $this;
        }

        if ($this->isEmpty()) {
            return $other;
        }

        return new self(array_values(array_merge($this->issues, $other->issues)));
    }

    /**
     * @return list<Issue>
     */
    public function withSeverity(IssueSeverity $severity): array
    {
        $matches = [];
        foreach ($this->issues as $issue) {
            if ($issue->severity === $severity) {
                $matches[] = $issue;
            }
        }

        return $matches;
    }

    public function countBySeverity(IssueSeverity $severity): int
    {
        $count = 0;
        foreach ($this->issues as $issue) {
            if ($issue->severity === $severity) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * @return list<string>
     */
    public function paths(): array
    {
        $paths = [];
        foreach ($this->issues as $issue) {
            $paths[] = $issue->path;
        }

        return $paths;
    }

    public function hasErrors(): bool
    {
        foreach ($this->issues as $issue) {
            if ($issue->isError()) {
                return true;
            }
        }

        return false;
    }

    public function hasWarnings(): bool
    {
        foreach ($this->issues as $issue) {
            if ($issue->isWarning()) {
                return true;
            }
        }

        return false;
    }
}
