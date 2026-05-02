<?php

declare(strict_types=1);

namespace Packwright\Tests\Unit\ExtEmConf\Result;

use Packwright\ExtEmConf\Enum\IssueSeverity;
use Packwright\ExtEmConf\Result\Issue;
use Packwright\ExtEmConf\Result\IssueCollection;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class IssueCollectionTest extends TestCase
{
    #[Test]
    public function emptyCollectionIsEmpty(): void
    {
        $collection = new IssueCollection();

        self::assertTrue($collection->isEmpty());
        self::assertSame(0, $collection->count());
        self::assertSame([], $collection->all());
        self::assertSame([], $collection->paths());
        self::assertFalse($collection->hasErrors());
        self::assertFalse($collection->hasWarnings());
    }

    #[Test]
    public function countAndIterationPreserveInsertionOrder(): void
    {
        $a = new Issue(path: 'a', message: 'A', severity: IssueSeverity::WARNING);
        $b = new Issue(path: 'b', message: 'B', severity: IssueSeverity::ERROR);
        $c = new Issue(path: 'c', message: 'C', severity: IssueSeverity::WARNING);

        $collection = new IssueCollection([$a, $b, $c]);

        self::assertSame(3, $collection->count());
        self::assertCount(3, $collection);
        self::assertSame([$a, $b, $c], iterator_to_array($collection));
        self::assertSame([$a, $b, $c], $collection->all());
        self::assertSame(['a', 'b', 'c'], $collection->paths());
    }

    #[Test]
    public function withSeverityReturnsOnlyMatchingIssues(): void
    {
        $err = new Issue(path: 'x', message: 'broken', severity: IssueSeverity::ERROR);
        $warn = new Issue(path: 'y', message: 'iffy', severity: IssueSeverity::WARNING);

        $collection = new IssueCollection([$err, $warn]);

        self::assertSame([$err], $collection->withSeverity(IssueSeverity::ERROR));
        self::assertSame([$warn], $collection->withSeverity(IssueSeverity::WARNING));
        self::assertSame(1, $collection->countBySeverity(IssueSeverity::ERROR));
        self::assertSame(1, $collection->countBySeverity(IssueSeverity::WARNING));
    }

    #[Test]
    public function hasErrorsAndHasWarningsTrackPresence(): void
    {
        $errOnly = new IssueCollection([
            new Issue(path: 'p', message: 'm', severity: IssueSeverity::ERROR),
        ]);
        $warnOnly = new IssueCollection([
            new Issue(path: 'p', message: 'm', severity: IssueSeverity::WARNING),
        ]);

        self::assertTrue($errOnly->hasErrors());
        self::assertFalse($errOnly->hasWarnings());
        self::assertFalse($warnOnly->hasErrors());
        self::assertTrue($warnOnly->hasWarnings());
    }

    #[Test]
    public function mergeReturnsCombinedCollectionPreservingOrder(): void
    {
        $first = new IssueCollection([
            new Issue(path: '1', message: 'first', severity: IssueSeverity::WARNING),
        ]);
        $second = new IssueCollection([
            new Issue(path: '2', message: 'second', severity: IssueSeverity::ERROR),
        ]);

        $merged = $first->merge($second);

        self::assertSame(2, $merged->count());
        self::assertSame(['1', '2'], $merged->paths());
        self::assertSame(1, $first->count(), 'original collection is not mutated');
    }

    #[Test]
    public function mergeWithEmptyReturnsExistingInstanceShortCircuit(): void
    {
        $populated = new IssueCollection([
            new Issue(path: 'p', message: 'm', severity: IssueSeverity::WARNING),
        ]);

        self::assertSame($populated, $populated->merge(new IssueCollection()));
        self::assertSame($populated, (new IssueCollection())->merge($populated));
    }
}
