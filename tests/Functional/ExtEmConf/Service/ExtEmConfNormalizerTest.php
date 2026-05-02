<?php

declare(strict_types=1);

namespace Packwright\Tests\Functional\ExtEmConf\Service;

use Packwright\ExtEmConf\Dto\Constraints\Constraints;
use Packwright\ExtEmConf\Dto\Constraints\Dependency;
use Packwright\ExtEmConf\Dto\Constraints\DependencyCollection;
use Packwright\ExtEmConf\Dto\Constraints\VersionValue\AllVersions;
use Packwright\ExtEmConf\Dto\Constraints\VersionValue\SingleVersion;
use Packwright\ExtEmConf\Dto\ExtEmConf;
use Packwright\ExtEmConf\Dto\Version;
use Packwright\ExtEmConf\Enum\Category;
use Packwright\ExtEmConf\Enum\ConstraintVersionType;
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
        self::assertTrue($result->droppedItems->isEmpty());
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
            $result->droppedItems->all(),
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
            $result->droppedItems->all(),
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
            $result->droppedItems->all(),
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
            $result->droppedItems->all(),
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

        $paths = $result->droppedItems->paths();
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

        $paths = $result->droppedItems->paths();
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

        $paths = $result->droppedItems->paths();
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

        $paths = $result->droppedItems->paths();
        self::assertContains('title', $paths);
        self::assertContains('description', $paths);
        self::assertContains('version', $paths);

        foreach (['title', 'description', 'version'] as $field) {
            $matches = array_values(array_filter(
                $result->droppedItems->all(),
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
        $paths = $result->droppedItems->paths();
        self::assertContains('description', $paths);
        self::assertNotContains('title', $paths);
        self::assertNotContains('version', $paths);
    }

    #[Test]
    public function normalizeMigratesTypoConstraintsKeysIntoCanonicalGroup(): void
    {
        $result = (new ExtEmConfNormalizer())->normalize(new ExtEmConf(
            title: 'My Extension',
            description: 'A great extension.',
            version: '1.0.0',
            category: Category::PLUGIN,
            state: State::STABLE,
            constraints: new Constraints(
                depends: new DependencyCollection([
                    new Dependency('typo3', ConstraintVersionType::ALL, new AllVersions()),
                ]),
                migratable: [
                    'depend' => new DependencyCollection([
                        new Dependency(
                            extensionKey: 'php',
                            versionType: ConstraintVersionType::VERSION,
                            version: new SingleVersion(Version::fromString('8.2.0')),
                        ),
                    ]),
                ],
            ),
        ));

        self::assertTrue($result->hasChanges);
        self::assertSame([], $result->extEmConf->constraints->migratable);

        $depends = iterator_to_array($result->extEmConf->constraints->depends);
        self::assertCount(2, $depends);
        self::assertArrayHasKey('typo3', $depends);
        self::assertArrayHasKey('php', $depends);

        $migratedIssue = array_values(array_filter(
            $result->droppedItems->all(),
            static fn($i) => $i->path === 'constraints.depends.php',
        ));
        self::assertCount(1, $migratedIssue);
        self::assertStringContainsString('Migrated', $migratedIssue[0]->message);
        self::assertStringContainsString('depend.php', $migratedIssue[0]->message);
        self::assertStringContainsString('depends.php', $migratedIssue[0]->message);
    }

    #[Test]
    public function normalizeMigratesAllThreeTypoGroupsIntoCanonicalCounterparts(): void
    {
        $result = (new ExtEmConfNormalizer())->normalize(new ExtEmConf(
            title: 'My Extension',
            description: 'A great extension.',
            version: '1.0.0',
            category: Category::PLUGIN,
            state: State::STABLE,
            constraints: new Constraints(
                migratable: [
                    'depend' => new DependencyCollection([
                        new Dependency('php', ConstraintVersionType::ALL, new AllVersions()),
                    ]),
                    'suggest' => new DependencyCollection([
                        new Dependency('news', ConstraintVersionType::ALL, new AllVersions()),
                    ]),
                    'conflict' => new DependencyCollection([
                        new Dependency('old_ext', ConstraintVersionType::ALL, new AllVersions()),
                    ]),
                ],
            ),
        ));

        self::assertCount(1, $result->extEmConf->constraints->depends);
        self::assertCount(1, $result->extEmConf->constraints->suggests);
        self::assertCount(1, $result->extEmConf->constraints->conflicts);
        self::assertSame([], $result->extEmConf->constraints->migratable);
    }

    #[Test]
    public function normalizeCollisionDuringMigrationKeepsCanonicalAndEmitsSkipWarning(): void
    {
        $canonicalDep = new Dependency(
            extensionKey: 'typo3',
            versionType: ConstraintVersionType::VERSION,
            version: new SingleVersion(Version::fromString('13.4.0')),
        );

        $result = (new ExtEmConfNormalizer())->normalize(new ExtEmConf(
            title: 'My Extension',
            description: 'A great extension.',
            version: '1.0.0',
            category: Category::PLUGIN,
            state: State::STABLE,
            constraints: new Constraints(
                depends: new DependencyCollection([$canonicalDep]),
                migratable: [
                    'depend' => new DependencyCollection([
                        new Dependency(
                            extensionKey: 'typo3',
                            versionType: ConstraintVersionType::VERSION,
                            version: new SingleVersion(Version::fromString('12.4.0')),
                        ),
                    ]),
                ],
            ),
        ));

        $depends = iterator_to_array($result->extEmConf->constraints->depends);
        self::assertCount(1, $depends);
        self::assertSame($canonicalDep, $depends['typo3']);

        $skipIssue = array_values(array_filter(
            $result->droppedItems->all(),
            static fn($i) => $i->path === 'constraints.depend.typo3',
        ));
        self::assertCount(1, $skipIssue);
        self::assertStringContainsString('Skipped', $skipIssue[0]->message);
        self::assertStringContainsString('depends.typo3', $skipIssue[0]->message);

        $migrationPath = array_filter(
            $result->droppedItems->all(),
            static fn($i) => $i->path === 'constraints.depends.typo3',
        );
        self::assertEmpty($migrationPath);
    }

    #[Test]
    public function normalizeWithNoMigratableEntriesProducesNoMigrationDroppedItems(): void
    {
        $result = (new ExtEmConfNormalizer())->normalize(new ExtEmConf(
            title: 'My Extension',
            description: 'A great extension.',
            version: '1.0.0',
            category: Category::PLUGIN,
            state: State::STABLE,
            constraints: new Constraints(
                depends: new DependencyCollection([
                    new Dependency('typo3', ConstraintVersionType::ALL, new AllVersions()),
                ]),
            ),
        ));

        $migrationItems = array_filter(
            $result->droppedItems->all(),
            static fn($i) => str_starts_with($i->path, 'constraints.'),
        );
        self::assertEmpty($migrationItems);
    }

    #[Test]
    public function normalizeMigrationProducesHasChangesTrue(): void
    {
        $result = (new ExtEmConfNormalizer())->normalize(new ExtEmConf(
            title: 'My Extension',
            description: 'A great extension.',
            version: '1.0.0',
            category: Category::PLUGIN,
            state: State::STABLE,
            constraints: new Constraints(
                migratable: [
                    'depend' => new DependencyCollection([
                        new Dependency('php', ConstraintVersionType::ALL, new AllVersions()),
                    ]),
                ],
            ),
        ));

        self::assertTrue($result->hasChanges);
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
