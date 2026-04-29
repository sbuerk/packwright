<?php

declare(strict_types=1);

namespace SBReleaser\Tests\Functional\ExtEmConf\Service;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SBReleaser\ExtEmConf\Dto\Constraints\VersionValue\AllVersions;
use SBReleaser\ExtEmConf\Dto\Constraints\VersionValue\VersionRange;
use SBReleaser\ExtEmConf\Enum\Category;
use SBReleaser\ExtEmConf\Enum\ResultState;
use SBReleaser\ExtEmConf\Enum\State;
use SBReleaser\ExtEmConf\Service\ExtEmConfReader;

final class ExtEmConfReaderTest extends TestCase
{
    private ExtEmConfReader $reader;

    protected function setUp(): void
    {
        $this->reader = new ExtEmConfReader();
    }

    private function fixtureFile(string $fixture): string
    {
        return __DIR__ . '/../Fixtures/' . $fixture . '/ext_emconf.php';
    }

    #[Test]
    public function readNonExistentFileReturnsFailure(): void
    {
        $result = $this->reader->read('/no/such/file/ext_emconf.php');

        self::assertSame(ResultState::FAILURE, $result->state);
        self::assertNull($result->extEmConf);
        self::assertNotEmpty($result->issues);
    }

    #[Test]
    public function readMinimalFileReturnsSuccess(): void
    {
        $result = $this->reader->read($this->fixtureFile('minimal'));

        self::assertSame(ResultState::SUCCESS, $result->state);
        self::assertNotNull($result->extEmConf);
        self::assertSame('Minimal Extension', $result->extEmConf->title);
        self::assertSame('0.1.0', $result->extEmConf->version);
    }

    #[Test]
    public function readV13FullFileMapsAllStandardFields(): void
    {
        $result = $this->reader->read($this->fixtureFile('v13_full'));

        self::assertSame(ResultState::SUCCESS, $result->state);
        self::assertNotNull($result->extEmConf);

        $dto = $result->extEmConf;
        self::assertSame('V13 Full Extension', $dto->title);
        self::assertSame('1.0.0', $dto->version);
        self::assertSame(Category::PLUGIN, $dto->category);
        self::assertSame(State::STABLE, $dto->state);
        self::assertSame('john@example.com', $dto->authorEmail);
        self::assertSame('MyVendor\\MyExtV13\\', array_key_first($dto->autoload->psr4));
        self::assertCount(1, $dto->autoloadDev->psr4);
    }

    #[Test]
    public function readV13FullFileParsesConstraints(): void
    {
        $result = $this->reader->read($this->fixtureFile('v13_full'));

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
        $result = $this->reader->read($this->fixtureFile('invalid_keys'));

        self::assertSame(ResultState::WARNING, $result->state);
        self::assertNotNull($result->extEmConf);
        self::assertArrayHasKey('unknown_custom_key', $result->extEmConf->unknownProperties);
        self::assertArrayHasKey('another_unknown', $result->extEmConf->unknownProperties);
    }

    #[Test]
    public function readFileWithUnknownKeysEmitsIssuePerKey(): void
    {
        $result = $this->reader->read($this->fixtureFile('invalid_keys'));

        $issuePaths = array_map(static fn($i) => $i->path, $result->issues);
        self::assertContains('unknown_custom_key', $issuePaths);
        self::assertContains('another_unknown', $issuePaths);
    }

    #[Test]
    public function readFileWithUnrecognisedStateValuePreservesRawString(): void
    {
        $result = $this->reader->read($this->fixtureFile('invalid_keys'));

        self::assertNotNull($result->extEmConf);
        self::assertSame('totally_made_up_state', $result->extEmConf->state);
    }

    #[Test]
    public function readDeprecatedKeysFixturePreservesAllDeprecatedFields(): void
    {
        $result = $this->reader->read($this->fixtureFile('deprecated_keys'));

        self::assertNotNull($result->extEmConf);
        $dto = $result->extEmConf;
        self::assertTrue($dto->clearCacheOnLoad);
        self::assertFalse($dto->uploadfolder);
        self::assertSame('uploads/my_ext', $dto->createDirs);
    }

    #[Test]
    public function readV8FullFileParsesDeprecatedFields(): void
    {
        $result = $this->reader->read($this->fixtureFile('v8_full'));

        self::assertNotNull($result->extEmConf);
        $dto = $result->extEmConf;
        self::assertFalse($dto->clearCacheOnLoad);
        self::assertFalse($dto->uploadfolder);
        self::assertSame('', $dto->createDirs);
    }

    #[Test]
    public function readV9FullFileParsesAutoloadDev(): void
    {
        $result = $this->reader->read($this->fixtureFile('v9_full'));

        self::assertNotNull($result->extEmConf);
        self::assertCount(1, $result->extEmConf->autoloadDev->psr4);
        self::assertArrayHasKey('MyVendor\\MyExtV9\\Tests\\', $result->extEmConf->autoloadDev->psr4);
    }
}
