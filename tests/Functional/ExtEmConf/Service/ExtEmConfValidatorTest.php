<?php

declare(strict_types=1);

namespace SBReleaser\Tests\Functional\ExtEmConf\Service;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SBReleaser\ExtEmConf\Dto\ExtEmConf;
use SBReleaser\ExtEmConf\Enum\Category;
use SBReleaser\ExtEmConf\Enum\IssueSeverity;
use SBReleaser\ExtEmConf\Enum\ResultState;
use SBReleaser\ExtEmConf\Enum\State;
use SBReleaser\ExtEmConf\Enum\Typo3Version;
use SBReleaser\ExtEmConf\Service\ExtEmConfValidator;

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
        $warnPaths = array_map(static fn($i) => $i->path, $result->issues);
        self::assertContains('category', $warnPaths);
    }

    #[Test]
    public function validateRawStringStateReturnsWarning(): void
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

        self::assertSame(ResultState::WARNING, $result->state);
        $warnPaths = array_map(static fn($i) => $i->path, $result->issues);
        self::assertContains('state', $warnPaths);
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
