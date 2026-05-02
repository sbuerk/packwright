<?php

declare(strict_types=1);

namespace Packwright\Tests\Functional\ExtEmConf\Service;

use Packwright\ExtEmConf\Dto\Constraints\VersionValue\AllVersions;
use Packwright\ExtEmConf\Dto\Constraints\VersionValue\VersionRange;
use Packwright\ExtEmConf\Enum\Category;
use Packwright\ExtEmConf\Enum\ResultState;
use Packwright\ExtEmConf\Enum\State;
use Packwright\ExtEmConf\Service\ExtEmConfReader;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ExtEmConfReaderTest extends TestCase
{
    #[Test]
    public function readNonExistentFileReturnsFailure(): void
    {
        $result = (new ExtEmConfReader())->read('/no/such/file/ext_emconf.php');

        self::assertSame(ResultState::FAILURE, $result->state);
        self::assertNull($result->extEmConf);
        self::assertFalse($result->issues->isEmpty());
    }

    #[Test]
    public function readMinimalFileReturnsSuccess(): void
    {
        $result = (new ExtEmConfReader())->read(__DIR__ . '/../Fixtures/minimal/ext_emconf.php');

        self::assertSame(ResultState::SUCCESS, $result->state);
        self::assertNotNull($result->extEmConf);
        self::assertSame('Minimal Extension', $result->extEmConf->title);
        self::assertSame('0.1.0', $result->extEmConf->version);
    }

    #[Test]
    public function readV13FullFileMapsAllStandardFields(): void
    {
        $result = (new ExtEmConfReader())->read(__DIR__ . '/../Fixtures/v13_full/ext_emconf.php');

        self::assertSame(ResultState::SUCCESS, $result->state);
        self::assertNotNull($result->extEmConf);

        $dto = $result->extEmConf;
        self::assertSame('V13 Full Extension', $dto->title);
        self::assertSame('1.0.0', $dto->version);
        self::assertSame(Category::PLUGIN, $dto->category);
        self::assertSame(State::STABLE, $dto->state);
        self::assertSame('john@example.com', $dto->authorEmail);
        self::assertSame('MyVendor\\MyExtV13\\', $dto->autoload->psr4->all()[0]->namespace);
        self::assertCount(1, $dto->autoloadDev->psr4);
    }

    #[Test]
    public function readV13FullFileParsesConstraints(): void
    {
        $result = (new ExtEmConfReader())->read(__DIR__ . '/../Fixtures/v13_full/ext_emconf.php');

        self::assertNotNull($result->extEmConf);
        $depends = $result->extEmConf->constraints->depends;
        $suggests = $result->extEmConf->constraints->suggests;

        self::assertCount(2, $depends);
        self::assertCount(0, $result->extEmConf->constraints->conflicts);
        self::assertCount(1, $suggests);

        $typo3Dep = iterator_to_array($depends)['typo3'] ?? null;
        self::assertNotNull($typo3Dep);
        self::assertInstanceOf(VersionRange::class, $typo3Dep->version);
        self::assertSame('13.4.0', (string)$typo3Dep->version->lowest);
        self::assertSame('13.4.99', (string)$typo3Dep->version->highest);

        $newsSug = iterator_to_array($suggests)['news'] ?? null;
        self::assertNotNull($newsSug);
        self::assertInstanceOf(AllVersions::class, $newsSug->version);
    }

    #[Test]
    public function readFileWithUnknownKeysReturnsWarning(): void
    {
        $result = (new ExtEmConfReader())->read(__DIR__ . '/../Fixtures/invalid_keys/ext_emconf.php');

        self::assertSame(ResultState::WARNING, $result->state);
        self::assertNotNull($result->extEmConf);
        self::assertArrayHasKey('unknown_custom_key', $result->extEmConf->unknownProperties);
        self::assertArrayHasKey('another_unknown', $result->extEmConf->unknownProperties);
    }

    #[Test]
    public function readFileWithUnknownKeysEmitsIssuePerKey(): void
    {
        $result = (new ExtEmConfReader())->read(__DIR__ . '/../Fixtures/invalid_keys/ext_emconf.php');

        $issuePaths = $result->issues->paths();
        self::assertContains('unknown_custom_key', $issuePaths);
        self::assertContains('another_unknown', $issuePaths);
    }

    #[Test]
    public function readFileWithUnrecognisedStateValuePreservesRawString(): void
    {
        $result = (new ExtEmConfReader())->read(__DIR__ . '/../Fixtures/invalid_state/ext_emconf.php');

        self::assertNotNull($result->extEmConf);
        self::assertSame('totally_made_up_state', $result->extEmConf->state);
        self::assertSame(ResultState::SUCCESS, $result->state);
        $statePaths = array_filter($result->issues->all(), static fn($i) => $i->path === 'state');
        self::assertEmpty($statePaths);
    }

    #[Test]
    public function readFileWithUnrecognisedCategoryValueDoesNotEmitReaderIssue(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'packwright_reader_');
        self::assertNotFalse($tempFile);
        file_put_contents($tempFile, <<<'PHP'
<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'Bogus Category Extension',
    'description' => 'Has an unrecognised category for reader contract testing.',
    'version' => '1.0.0',
    'category' => 'totally_unknown_category',
    'state' => 'stable',
    'constraints' => [
        'depends' => ['typo3' => '13.4.0-13.4.99'],
        'conflicts' => [],
        'suggests' => [],
    ],
];
PHP);

        try {
            $result = (new ExtEmConfReader())->read($tempFile);

            self::assertNotNull($result->extEmConf);
            self::assertSame('totally_unknown_category', $result->extEmConf->category);
            self::assertSame(ResultState::SUCCESS, $result->state);
            $categoryPaths = array_filter($result->issues->all(), static fn($i) => $i->path === 'category');
            self::assertEmpty($categoryPaths);
        } finally {
            @unlink($tempFile);
        }
    }

    #[Test]
    public function readDeprecatedKeysFixturePreservesAllDeprecatedFields(): void
    {
        $result = (new ExtEmConfReader())->read(__DIR__ . '/../Fixtures/deprecated_keys/ext_emconf.php');

        self::assertNotNull($result->extEmConf);
        $dto = $result->extEmConf;
        self::assertTrue($dto->clearCacheOnLoad);
        self::assertFalse($dto->uploadfolder);
        self::assertSame('uploads/my_ext', $dto->createDirs);
    }

    #[Test]
    public function readV8FullFileParsesDeprecatedFields(): void
    {
        $result = (new ExtEmConfReader())->read(__DIR__ . '/../Fixtures/v8_full/ext_emconf.php');

        self::assertNotNull($result->extEmConf);
        $dto = $result->extEmConf;
        self::assertFalse($dto->clearCacheOnLoad);
        self::assertFalse($dto->uploadfolder);
        self::assertSame('', $dto->createDirs);
    }

    #[Test]
    public function readV9FullFileParsesAutoloadDev(): void
    {
        $result = (new ExtEmConfReader())->read(__DIR__ . '/../Fixtures/v9_full/ext_emconf.php');

        self::assertNotNull($result->extEmConf);
        self::assertCount(1, $result->extEmConf->autoloadDev->psr4);
        self::assertSame('MyVendor\\MyExtV9\\Tests\\', $result->extEmConf->autoloadDev->psr4->all()[0]->namespace);
    }

    #[Test]
    public function readFileWithTypoConstraintsKeysCapturesEntriesUnderMigratable(): void
    {
        $result = (new ExtEmConfReader())->read(__DIR__ . '/../Fixtures/constraints_typo/ext_emconf.php');

        self::assertSame(ResultState::WARNING, $result->state);
        self::assertNotNull($result->extEmConf);

        $migratable = $result->extEmConf->constraints->migratable;
        self::assertFalse($migratable->depend->isEmpty());
        self::assertFalse($migratable->suggest->isEmpty());
        self::assertFalse($migratable->conflict->isEmpty());

        self::assertCount(2, $migratable->depend);
        self::assertCount(1, $migratable->suggest);
        self::assertCount(1, $migratable->conflict);

        $phpDep = iterator_to_array($migratable->depend)['php'] ?? null;
        self::assertNotNull($phpDep);
        self::assertInstanceOf(VersionRange::class, $phpDep->version);
    }

    #[Test]
    public function readFileWithTypoConstraintsKeysLeavesCanonicalGroupsUntouched(): void
    {
        $result = (new ExtEmConfReader())->read(__DIR__ . '/../Fixtures/constraints_typo/ext_emconf.php');

        self::assertNotNull($result->extEmConf);
        self::assertCount(1, $result->extEmConf->constraints->depends);
        self::assertCount(0, $result->extEmConf->constraints->conflicts);
        self::assertCount(0, $result->extEmConf->constraints->suggests);

        $typo3Dep = iterator_to_array($result->extEmConf->constraints->depends)['typo3'] ?? null;
        self::assertNotNull($typo3Dep);
    }

    #[Test]
    public function readFileWithTypoConstraintsKeysEmitsMigrationHintIssuePerGroup(): void
    {
        $result = (new ExtEmConfReader())->read(__DIR__ . '/../Fixtures/constraints_typo/ext_emconf.php');

        $issuePaths = $result->issues->paths();
        self::assertContains('constraints.depend', $issuePaths);
        self::assertContains('constraints.suggest', $issuePaths);
        self::assertContains('constraints.conflict', $issuePaths);
    }

    #[Test]
    public function readFileWithTypoConstraintsKeysAppliesSubValueValidationToTypoEntries(): void
    {
        $result = (new ExtEmConfReader())->read(__DIR__ . '/../Fixtures/constraints_typo/ext_emconf.php');

        $issuePaths = $result->issues->paths();
        self::assertContains('constraints.depend.broken', $issuePaths);

        $migratable = $result->extEmConf?->constraints->migratable->depend;
        self::assertNotNull($migratable);
        $brokenDep = iterator_to_array($migratable)['broken'] ?? null;
        self::assertNotNull($brokenDep);
        self::assertInstanceOf(AllVersions::class, $brokenDep->version);
    }
}
