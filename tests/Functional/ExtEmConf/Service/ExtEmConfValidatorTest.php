<?php

declare(strict_types=1);

namespace Packwright\Tests\Functional\ExtEmConf\Service;

use Packwright\ExtEmConf\Dto\ExtEmConf;
use Packwright\ExtEmConf\Enum\Category;
use Packwright\ExtEmConf\Enum\IssueSeverity;
use Packwright\ExtEmConf\Enum\ResultState;
use Packwright\ExtEmConf\Enum\State;
use Packwright\ExtEmConf\Enum\Typo3Version;
use Packwright\ExtEmConf\Service\ExtEmConfValidator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ExtEmConfValidatorTest extends TestCase
{
    #[Test]
    public function validateCleanDtoReturnsSuccess(): void
    {
        $result = (new ExtEmConfValidator())->validate(new ExtEmConf(
            title: 'My Extension',
            description: 'A great extension.',
            version: '1.0.0',
            category: Category::PLUGIN,
            state: State::STABLE,
            author: 'John Doe',
            authorEmail: 'john@example.com',
        ));

        self::assertSame(ResultState::SUCCESS, $result->state);
        self::assertEmpty($result->issues);
        self::assertTrue($result->isValid());
    }

    #[Test]
    public function validateEmptyTitleReturnsFailure(): void
    {
        $result = (new ExtEmConfValidator())->validate(new ExtEmConf(
            title: '',
            description: 'A great extension.',
            version: '1.0.0',
            category: Category::PLUGIN,
            state: State::STABLE,
            author: 'John Doe',
            authorEmail: 'john@example.com',
        ));

        self::assertSame(ResultState::FAILURE, $result->state);
        self::assertFalse($result->isValid());
        self::assertTrue($result->hasErrors());

        $errorPaths = array_map(static fn($i) => $i->path, $result->issues);
        self::assertContains('title', $errorPaths);
    }

    #[Test]
    public function validateEmptyDescriptionReturnsFailure(): void
    {
        $result = (new ExtEmConfValidator())->validate(new ExtEmConf(
            title: 'My Extension',
            description: '',
            version: '1.0.0',
            category: Category::PLUGIN,
            state: State::STABLE,
            author: 'John Doe',
            authorEmail: 'john@example.com',
        ));

        self::assertSame(ResultState::FAILURE, $result->state);
        $errorPaths = array_map(static fn($i) => $i->path, $result->issues);
        self::assertContains('description', $errorPaths);
    }

    #[Test]
    public function validateEmptyVersionReturnsFailure(): void
    {
        $result = (new ExtEmConfValidator())->validate(new ExtEmConf(
            title: 'My Extension',
            description: 'A great extension.',
            version: '',
            category: Category::PLUGIN,
            state: State::STABLE,
            author: 'John Doe',
            authorEmail: 'john@example.com',
        ));

        self::assertSame(ResultState::FAILURE, $result->state);
        $errorPaths = array_map(static fn($i) => $i->path, $result->issues);
        self::assertContains('version', $errorPaths);
    }

    #[Test]
    public function validateInvalidVersionStringReturnsFailure(): void
    {
        $result = (new ExtEmConfValidator())->validate(new ExtEmConf(
            title: 'My Extension',
            description: 'A great extension.',
            version: 'not-a-version',
            category: Category::PLUGIN,
            state: State::STABLE,
            author: 'John Doe',
            authorEmail: 'john@example.com',
        ));

        self::assertSame(ResultState::FAILURE, $result->state);
        $errorPaths = array_map(static fn($i) => $i->path, $result->issues);
        self::assertContains('version', $errorPaths);
    }

    #[Test]
    public function validateInvalidEmailReturnsWarning(): void
    {
        $result = (new ExtEmConfValidator())->validate(new ExtEmConf(
            title: 'My Extension',
            description: 'A great extension.',
            version: '1.0.0',
            category: Category::PLUGIN,
            state: State::STABLE,
            author: 'John Doe',
            authorEmail: 'not-an-email',
        ));

        self::assertSame(ResultState::WARNING, $result->state);
        self::assertTrue($result->hasWarnings());
        self::assertFalse($result->hasErrors());

        $warnPaths = array_map(static fn($i) => $i->path, $result->issues);
        self::assertContains('author_email', $warnPaths);
    }

    #[Test]
    public function validateRawStringCategoryReturnsWarning(): void
    {
        $result = (new ExtEmConfValidator())->validate(new ExtEmConf(
            title: 'My Extension',
            description: 'A great extension.',
            version: '1.0.0',
            category: 'some_unknown_category',
            state: State::STABLE,
            author: 'John Doe',
            authorEmail: 'john@example.com',
        ));

        self::assertSame(ResultState::WARNING, $result->state);
        $categoryIssues = array_values(array_filter(
            $result->issues,
            static fn($i) => $i->path === 'category',
        ));
        self::assertCount(1, $categoryIssues);
        self::assertStringContainsString('some_unknown_category', $categoryIssues[0]->message);
        self::assertStringContainsString('misc', $categoryIssues[0]->message);
    }

    #[Test]
    public function validateMissingCategoryReturnsWarning(): void
    {
        $result = (new ExtEmConfValidator())->validate(new ExtEmConf(
            title: 'My Extension',
            description: 'A great extension.',
            version: '1.0.0',
            state: State::STABLE,
            author: 'John Doe',
            authorEmail: 'john@example.com',
        ));

        self::assertSame(ResultState::WARNING, $result->state);
        $categoryIssues = array_values(array_filter(
            $result->issues,
            static fn($i) => $i->path === 'category',
        ));
        self::assertCount(1, $categoryIssues);
        self::assertStringContainsString('missing', $categoryIssues[0]->message);
        self::assertStringContainsString('misc', $categoryIssues[0]->message);
    }

    #[Test]
    public function validateRawStringStateReturnsFailure(): void
    {
        $result = (new ExtEmConfValidator())->validate(new ExtEmConf(
            title: 'My Extension',
            description: 'A great extension.',
            version: '1.0.0',
            category: Category::PLUGIN,
            state: 'totally_made_up',
            author: 'John Doe',
            authorEmail: 'john@example.com',
        ));

        self::assertSame(ResultState::FAILURE, $result->state);
        $stateIssues = array_values(array_filter(
            $result->issues,
            static fn($i) => $i->path === 'state',
        ));
        self::assertCount(1, $stateIssues);
        self::assertSame(IssueSeverity::ERROR, $stateIssues[0]->severity);
        self::assertStringContainsString('totally_made_up', $stateIssues[0]->message);
        self::assertStringContainsString('beta', $stateIssues[0]->message);
    }

    #[Test]
    public function validateMissingStateReturnsFailure(): void
    {
        $result = (new ExtEmConfValidator())->validate(new ExtEmConf(
            title: 'My Extension',
            description: 'A great extension.',
            version: '1.0.0',
            category: Category::PLUGIN,
            author: 'John Doe',
            authorEmail: 'john@example.com',
        ));

        self::assertSame(ResultState::FAILURE, $result->state);
        $stateIssues = array_values(array_filter(
            $result->issues,
            static fn($i) => $i->path === 'state',
        ));
        self::assertCount(1, $stateIssues);
        self::assertSame(IssueSeverity::ERROR, $stateIssues[0]->severity);
        self::assertStringContainsString('missing', $stateIssues[0]->message);
        self::assertStringContainsString('beta', $stateIssues[0]->message);
    }

    #[Test]
    public function validateUnknownPropertiesReturnsWarning(): void
    {
        $result = (new ExtEmConfValidator())->validate(new ExtEmConf(
            title: 'My Extension',
            description: 'A great extension.',
            version: '1.0.0',
            category: Category::PLUGIN,
            state: State::STABLE,
            author: 'John Doe',
            authorEmail: 'john@example.com',
            unknownProperties: ['my_custom_key' => 'value'],
        ));

        self::assertSame(ResultState::WARNING, $result->state);
        $warnPaths = array_map(static fn($i) => $i->path, $result->issues);
        self::assertContains('my_custom_key', $warnPaths);
    }

    #[Test]
    public function validateDeprecatedClearCacheOnLoadWithTargetV13ReturnsWarning(): void
    {
        $result = (new ExtEmConfValidator())->validate(
            new ExtEmConf(
                title: 'My Extension',
                description: 'A great extension.',
                version: '1.0.0',
                category: Category::PLUGIN,
                state: State::STABLE,
                author: 'John Doe',
                authorEmail: 'john@example.com',
                clearCacheOnLoad: false,
            ),
            Typo3Version::V13,
        );

        self::assertSame(ResultState::WARNING, $result->state);
        $warnPaths = array_map(static fn($i) => $i->path, $result->issues);
        self::assertContains('clearCacheOnLoad', $warnPaths);
    }

    #[Test]
    public function validateDeprecatedClearCacheOnLoadWithoutTargetReturnsSuccess(): void
    {
        $result = (new ExtEmConfValidator())->validate(new ExtEmConf(
            title: 'My Extension',
            description: 'A great extension.',
            version: '1.0.0',
            category: Category::PLUGIN,
            state: State::STABLE,
            author: 'John Doe',
            authorEmail: 'john@example.com',
            clearCacheOnLoad: false,
        ));

        self::assertSame(ResultState::SUCCESS, $result->state);
        self::assertEmpty($result->issues);
    }

    #[Test]
    public function validateIssuesHaveCorrectSeverity(): void
    {
        $result = (new ExtEmConfValidator())->validate(new ExtEmConf(
            title: '',
            description: 'OK',
            version: '1.0.0',
            authorEmail: 'not-an-email',
        ));

        $errors = array_filter($result->issues, static fn($i) => $i->severity === IssueSeverity::ERROR);
        $warnings = array_filter($result->issues, static fn($i) => $i->severity === IssueSeverity::WARNING);

        self::assertNotEmpty($errors);
        self::assertNotEmpty($warnings);
    }
}
