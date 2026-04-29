<?php

declare(strict_types=1);

namespace SBReleaser\Tests\Functional\ExtEmConf\Service;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SBReleaser\ExtEmConf\Dto\ExtEmConf;
use SBReleaser\ExtEmConf\Dto\Version;
use SBReleaser\ExtEmConf\Enum\Category;
use SBReleaser\ExtEmConf\Enum\IssueSeverity;
use SBReleaser\ExtEmConf\Enum\ResultState;
use SBReleaser\ExtEmConf\Enum\State;
use SBReleaser\ExtEmConf\Service\ExtEmConfValidator;

final class ExtEmConfValidatorTest extends TestCase
{
    private ExtEmConfValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new ExtEmConfValidator();
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function validDto(array $overrides = []): ExtEmConf
    {
        return new ExtEmConf(
            title: $overrides['title'] ?? 'My Extension',
            description: $overrides['description'] ?? 'A great extension.',
            version: $overrides['version'] ?? '1.0.0',
            category: $overrides['category'] ?? Category::PLUGIN,
            state: $overrides['state'] ?? State::STABLE,
            author: 'John Doe',
            authorEmail: $overrides['authorEmail'] ?? 'john@example.com',
            unknownProperties: $overrides['unknownProperties'] ?? [],
            clearCacheOnLoad: $overrides['clearCacheOnLoad'] ?? null,
        );
    }

    #[Test]
    public function validateCleanDtoReturnsSuccess(): void
    {
        $result = $this->validator->validate($this->validDto());

        self::assertSame(ResultState::SUCCESS, $result->state);
        self::assertEmpty($result->issues);
        self::assertTrue($result->isValid());
    }

    #[Test]
    public function validateEmptyTitleReturnsFailure(): void
    {
        $result = $this->validator->validate($this->validDto(['title' => '']));

        self::assertSame(ResultState::FAILURE, $result->state);
        self::assertFalse($result->isValid());
        self::assertTrue($result->hasErrors());

        $errorPaths = array_map(static fn($i) => $i->path, $result->issues);
        self::assertContains('title', $errorPaths);
    }

    #[Test]
    public function validateEmptyDescriptionReturnsFailure(): void
    {
        $result = $this->validator->validate($this->validDto(['description' => '']));

        self::assertSame(ResultState::FAILURE, $result->state);
        $errorPaths = array_map(static fn($i) => $i->path, $result->issues);
        self::assertContains('description', $errorPaths);
    }

    #[Test]
    public function validateEmptyVersionReturnsFailure(): void
    {
        $result = $this->validator->validate($this->validDto(['version' => '']));

        self::assertSame(ResultState::FAILURE, $result->state);
        $errorPaths = array_map(static fn($i) => $i->path, $result->issues);
        self::assertContains('version', $errorPaths);
    }

    #[Test]
    public function validateInvalidVersionStringReturnsFailure(): void
    {
        $result = $this->validator->validate($this->validDto(['version' => 'not-a-version']));

        self::assertSame(ResultState::FAILURE, $result->state);
        $errorPaths = array_map(static fn($i) => $i->path, $result->issues);
        self::assertContains('version', $errorPaths);
    }

    #[Test]
    public function validateInvalidEmailReturnsWarning(): void
    {
        $result = $this->validator->validate($this->validDto(['authorEmail' => 'not-an-email']));

        self::assertSame(ResultState::WARNING, $result->state);
        self::assertTrue($result->hasWarnings());
        self::assertFalse($result->hasErrors());

        $warnPaths = array_map(static fn($i) => $i->path, $result->issues);
        self::assertContains('author_email', $warnPaths);
    }

    #[Test]
    public function validateRawStringCategoryReturnsWarning(): void
    {
        $dto = $this->validDto(['category' => 'some_unknown_category']);
        $result = $this->validator->validate($dto);

        self::assertSame(ResultState::WARNING, $result->state);
        $warnPaths = array_map(static fn($i) => $i->path, $result->issues);
        self::assertContains('category', $warnPaths);
    }

    #[Test]
    public function validateRawStringStateReturnsWarning(): void
    {
        $dto = $this->validDto(['state' => 'totally_made_up']);
        $result = $this->validator->validate($dto);

        self::assertSame(ResultState::WARNING, $result->state);
        $warnPaths = array_map(static fn($i) => $i->path, $result->issues);
        self::assertContains('state', $warnPaths);
    }

    #[Test]
    public function validateUnknownPropertiesReturnsWarning(): void
    {
        $dto = $this->validDto(['unknownProperties' => ['my_custom_key' => 'value']]);
        $result = $this->validator->validate($dto);

        self::assertSame(ResultState::WARNING, $result->state);
        $warnPaths = array_map(static fn($i) => $i->path, $result->issues);
        self::assertContains('my_custom_key', $warnPaths);
    }

    #[Test]
    public function validateDeprecatedClearCacheOnLoadWithTargetV13ReturnsWarning(): void
    {
        $dto = $this->validDto(['clearCacheOnLoad' => false]);
        $result = $this->validator->validate($dto, Version::fromString('13.4.0'));

        self::assertSame(ResultState::WARNING, $result->state);
        $warnPaths = array_map(static fn($i) => $i->path, $result->issues);
        self::assertContains('clearCacheOnLoad', $warnPaths);
    }

    #[Test]
    public function validateDeprecatedClearCacheOnLoadWithoutTargetReturnsSuccess(): void
    {
        $dto = $this->validDto(['clearCacheOnLoad' => false]);
        $result = $this->validator->validate($dto);

        self::assertSame(ResultState::SUCCESS, $result->state);
        self::assertEmpty($result->issues);
    }

    #[Test]
    public function validateIssuesHaveCorrectSeverity(): void
    {
        $dto = new ExtEmConf(
            title: '',
            description: 'OK',
            version: '1.0.0',
            authorEmail: 'not-an-email',
        );
        $result = $this->validator->validate($dto);

        $errors = array_filter($result->issues, static fn($i) => $i->severity === IssueSeverity::ERROR);
        $warnings = array_filter($result->issues, static fn($i) => $i->severity === IssueSeverity::WARNING);

        self::assertNotEmpty($errors);
        self::assertNotEmpty($warnings);
    }
}
