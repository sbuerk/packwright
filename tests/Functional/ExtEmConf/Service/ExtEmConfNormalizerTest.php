<?php

declare(strict_types=1);

namespace Packwright\Tests\Functional\ExtEmConf\Service;

use Packwright\ExtEmConf\Dto\ExtEmConf;
use Packwright\ExtEmConf\Enum\Category;
use Packwright\ExtEmConf\Enum\State;
use Packwright\ExtEmConf\Enum\Typo3Version;
use Packwright\ExtEmConf\Service\ExtEmConfNormalizer;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ExtEmConfNormalizerTest extends TestCase
{
    #[Test]
    public function normalizeCleanDtoHasNoChanges(): void
    {
        $result = (new ExtEmConfNormalizer())->normalize(new ExtEmConf(
            title: 'My Extension',
            description: 'A great extension.',
            version: '1.0.0',
            category: Category::PLUGIN,
            state: State::STABLE,
        ));

        self::assertFalse($result->hasChanges);
        self::assertEmpty($result->droppedItems);
    }

    #[Test]
    public function normalizeReplacesRawStringCategoryWithMisc(): void
    {
        $result = (new ExtEmConfNormalizer())->normalize(new ExtEmConf(
            title: 'My Extension',
            description: 'A great extension.',
            version: '1.0.0',
            category: 'totally_unknown',
            state: State::STABLE,
        ));

        self::assertTrue($result->hasChanges);
        self::assertSame(Category::MISC, $result->extEmConf->category);

        $categoryIssues = array_values(array_filter(
            $result->droppedItems,
            static fn($i) => $i->path === 'category',
        ));
        self::assertCount(1, $categoryIssues);
        self::assertStringContainsString('totally_unknown', $categoryIssues[0]->message);
        self::assertStringContainsString('misc', $categoryIssues[0]->message);
    }

    #[Test]
    public function normalizeMissingCategoryDefaultsToMisc(): void
    {
        $result = (new ExtEmConfNormalizer())->normalize(new ExtEmConf(
            title: 'My Extension',
            description: 'A great extension.',
            version: '1.0.0',
            state: State::STABLE,
        ));

        self::assertTrue($result->hasChanges);
        self::assertSame(Category::MISC, $result->extEmConf->category);

        $categoryIssues = array_values(array_filter(
            $result->droppedItems,
            static fn($i) => $i->path === 'category',
        ));
        self::assertCount(1, $categoryIssues);
        self::assertStringContainsString('missing', $categoryIssues[0]->message);
        self::assertStringContainsString('misc', $categoryIssues[0]->message);
    }

    #[Test]
    public function normalizeKeepsValidCategoryEnum(): void
    {
        $result = (new ExtEmConfNormalizer())->normalize(new ExtEmConf(
            title: 'My Extension',
            description: 'A great extension.',
            version: '1.0.0',
            category: Category::FE,
            state: State::STABLE,
        ));

        self::assertFalse($result->hasChanges);
        self::assertSame(Category::FE, $result->extEmConf->category);
    }

    #[Test]
    public function normalizeReplacesRawStringStateWithBeta(): void
    {
        $result = (new ExtEmConfNormalizer())->normalize(new ExtEmConf(
            title: 'My Extension',
            description: 'A great extension.',
            version: '1.0.0',
            category: Category::PLUGIN,
            state: 'totally_unknown',
        ));

        self::assertTrue($result->hasChanges);
        self::assertSame(State::BETA, $result->extEmConf->state);

        $stateIssues = array_values(array_filter(
            $result->droppedItems,
            static fn($i) => $i->path === 'state',
        ));
        self::assertCount(1, $stateIssues);
        self::assertStringContainsString('totally_unknown', $stateIssues[0]->message);
        self::assertStringContainsString('beta', $stateIssues[0]->message);
    }

    #[Test]
    public function normalizeMissingStateDefaultsToBeta(): void
    {
        $result = (new ExtEmConfNormalizer())->normalize(new ExtEmConf(
            title: 'My Extension',
            description: 'A great extension.',
            version: '1.0.0',
            category: Category::PLUGIN,
        ));

        self::assertTrue($result->hasChanges);
        self::assertSame(State::BETA, $result->extEmConf->state);

        $stateIssues = array_values(array_filter(
            $result->droppedItems,
            static fn($i) => $i->path === 'state',
        ));
        self::assertCount(1, $stateIssues);
        self::assertStringContainsString('missing', $stateIssues[0]->message);
        self::assertStringContainsString('beta', $stateIssues[0]->message);
    }

    #[Test]
    public function normalizeDropsUnknownProperties(): void
    {
        $result = (new ExtEmConfNormalizer())->normalize(new ExtEmConf(
            title: 'My Extension',
            description: 'A great extension.',
            version: '1.0.0',
            category: Category::PLUGIN,
            state: State::STABLE,
            unknownProperties: ['my_key' => 'value', 'another' => 42],
        ));

        self::assertTrue($result->hasChanges);
        self::assertSame([], $result->extEmConf->unknownProperties);

        $paths = array_map(static fn($i) => $i->path, $result->droppedItems);
        self::assertContains('my_key', $paths);
        self::assertContains('another', $paths);
    }

    #[Test]
    public function normalizeDropsUploadfolderForTargetV9(): void
    {
        $result = (new ExtEmConfNormalizer())->normalize(
            new ExtEmConf(
                title: 'My Extension',
                description: 'A great extension.',
                version: '1.0.0',
                category: Category::PLUGIN,
                state: State::STABLE,
                uploadfolder: false,
            ),
            Typo3Version::V9,
        );

        self::assertTrue($result->hasChanges);
        self::assertNull($result->extEmConf->uploadfolder);

        $paths = array_map(static fn($i) => $i->path, $result->droppedItems);
        self::assertContains('uploadfolder', $paths);
    }

    #[Test]
    public function normalizeKeepsUploadfolderForTargetV8(): void
    {
        $result = (new ExtEmConfNormalizer())->normalize(
            new ExtEmConf(
                title: 'My Extension',
                description: 'A great extension.',
                version: '1.0.0',
                category: Category::PLUGIN,
                state: State::STABLE,
                uploadfolder: false,
            ),
            Typo3Version::V8,
        );

        self::assertFalse($result->hasChanges);
        self::assertFalse($result->extEmConf->uploadfolder);
    }

    #[Test]
    public function normalizeDropsCreateDirsForTargetV9(): void
    {
        $result = (new ExtEmConfNormalizer())->normalize(
            new ExtEmConf(
                title: 'My Extension',
                description: 'A great extension.',
                version: '1.0.0',
                category: Category::PLUGIN,
                state: State::STABLE,
                createDirs: 'uploads/my_ext',
            ),
            Typo3Version::V9,
        );

        self::assertTrue($result->hasChanges);
        self::assertNull($result->extEmConf->createDirs);
    }

    #[Test]
    public function normalizeDropsClearCacheOnLoadForTargetV12(): void
    {
        $result = (new ExtEmConfNormalizer())->normalize(
            new ExtEmConf(
                title: 'My Extension',
                description: 'A great extension.',
                version: '1.0.0',
                category: Category::PLUGIN,
                state: State::STABLE,
                clearCacheOnLoad: false,
            ),
            Typo3Version::V12,
        );

        self::assertTrue($result->hasChanges);
        self::assertNull($result->extEmConf->clearCacheOnLoad);

        $paths = array_map(static fn($i) => $i->path, $result->droppedItems);
        self::assertContains('clearCacheOnLoad', $paths);
    }

    #[Test]
    public function normalizeKeepsClearCacheOnLoadForTargetV11(): void
    {
        $result = (new ExtEmConfNormalizer())->normalize(
            new ExtEmConf(
                title: 'My Extension',
                description: 'A great extension.',
                version: '1.0.0',
                category: Category::PLUGIN,
                state: State::STABLE,
                clearCacheOnLoad: false,
            ),
            Typo3Version::V11,
        );

        self::assertFalse($result->hasChanges);
        self::assertFalse($result->extEmConf->clearCacheOnLoad);
    }

    #[Test]
    public function normalizeDropsClearCacheOnLoadForTargetV13(): void
    {
        $result = (new ExtEmConfNormalizer())->normalize(
            new ExtEmConf(
                title: 'My Extension',
                description: 'A great extension.',
                version: '1.0.0',
                category: Category::PLUGIN,
                state: State::STABLE,
                clearCacheOnLoad: true,
            ),
            Typo3Version::V13,
        );

        self::assertTrue($result->hasChanges);
        self::assertNull($result->extEmConf->clearCacheOnLoad);
    }

    #[Test]
    public function normalizeWithoutTargetVersionPreservesDeprecatedFields(): void
    {
        $result = (new ExtEmConfNormalizer())->normalize(new ExtEmConf(
            title: 'My Extension',
            description: 'A great extension.',
            version: '1.0.0',
            category: Category::PLUGIN,
            state: State::STABLE,
            clearCacheOnLoad: false,
            uploadfolder: false,
            createDirs: 'uploads/',
        ));

        self::assertFalse($result->hasChanges);
        self::assertFalse($result->extEmConf->clearCacheOnLoad);
        self::assertFalse($result->extEmConf->uploadfolder);
        self::assertSame('uploads/', $result->extEmConf->createDirs);
    }

    #[Test]
    public function normalizeReportsEmptyRequiredStringFields(): void
    {
        $result = (new ExtEmConfNormalizer())->normalize(new ExtEmConf(
            title: '',
            description: '',
            version: '',
            category: Category::PLUGIN,
            state: State::STABLE,
        ));

        self::assertTrue($result->hasChanges);
        self::assertSame('', $result->extEmConf->title);
        self::assertSame('', $result->extEmConf->description);
        self::assertSame('', $result->extEmConf->version);

        $paths = array_map(static fn($i) => $i->path, $result->droppedItems);
        self::assertContains('title', $paths);
        self::assertContains('description', $paths);
        self::assertContains('version', $paths);

        foreach (['title', 'description', 'version'] as $field) {
            $matches = array_values(array_filter(
                $result->droppedItems,
                static fn($i) => $i->path === $field,
            ));
            self::assertCount(1, $matches);
            self::assertStringContainsString('empty string', $matches[0]->message);
        }
    }

    #[Test]
    public function normalizeReportsOnlyOneEmptyRequiredFieldWhenOthersArePopulated(): void
    {
        $result = (new ExtEmConfNormalizer())->normalize(new ExtEmConf(
            title: 'My Extension',
            description: '',
            version: '1.0.0',
            category: Category::PLUGIN,
            state: State::STABLE,
        ));

        self::assertTrue($result->hasChanges);
        $paths = array_map(static fn($i) => $i->path, $result->droppedItems);
        self::assertContains('description', $paths);
        self::assertNotContains('title', $paths);
        self::assertNotContains('version', $paths);
    }

    #[Test]
    public function normalizeAlwaysReturnsNewDtoInstance(): void
    {
        $dto = new ExtEmConf(
            title: 'My Extension',
            description: 'A great extension.',
            version: '1.0.0',
            category: Category::PLUGIN,
            state: State::STABLE,
        );
        $result = (new ExtEmConfNormalizer())->normalize($dto);

        self::assertNotSame($dto, $result->extEmConf);
    }
}
