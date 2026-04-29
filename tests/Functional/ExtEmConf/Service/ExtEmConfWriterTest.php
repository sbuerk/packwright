<?php

declare(strict_types=1);

namespace SBReleaser\Tests\Functional\ExtEmConf\Service;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SBReleaser\ExtEmConf\Dto\Autoload;
use SBReleaser\ExtEmConf\Dto\Constraints\Constraints;
use SBReleaser\ExtEmConf\Dto\Constraints\Dependency;
use SBReleaser\ExtEmConf\Dto\Constraints\DependencyCollection;
use SBReleaser\ExtEmConf\Dto\Constraints\VersionValue\AllVersions;
use SBReleaser\ExtEmConf\Dto\Constraints\VersionValue\SingleVersion;
use SBReleaser\ExtEmConf\Dto\Constraints\VersionValue\VersionRange;
use SBReleaser\ExtEmConf\Dto\ExtEmConf;
use SBReleaser\ExtEmConf\Dto\Version;
use SBReleaser\ExtEmConf\Enum\Category;
use SBReleaser\ExtEmConf\Enum\Typo3Version;
use SBReleaser\ExtEmConf\Enum\ConstraintVersionType;
use SBReleaser\ExtEmConf\Enum\ResultState;
use SBReleaser\ExtEmConf\Enum\State;
use SBReleaser\ExtEmConf\Service\ExtEmConfNormalizer;
use SBReleaser\ExtEmConf\Service\ExtEmConfReader;
use SBReleaser\ExtEmConf\Service\ExtEmConfValidator;
use SBReleaser\ExtEmConf\Service\ExtEmConfWriter;

final class ExtEmConfWriterTest extends TestCase
{
    private ExtEmConfWriter $writer;
    private ExtEmConfReader $reader;

    protected function setUp(): void
    {
        $this->writer = new ExtEmConfWriter();
        $this->reader = new ExtEmConfReader();
    }

    private function fixtureFile(string $fixture): string
    {
        return __DIR__ . '/../Fixtures/' . $fixture . '/ext_emconf.php';
    }

    private function minimalDto(): ExtEmConf
    {
        return new ExtEmConf(
            title: 'Test Extension',
            description: 'A test extension.',
            version: '1.0.0',
            category: Category::PLUGIN,
            state: State::STABLE,
            author: 'John Doe',
            authorEmail: 'john@example.com',
            authorCompany: 'ACME Corp',
            constraints: new Constraints(
                depends: new DependencyCollection([
                    new Dependency(
                        extensionKey: 'typo3',
                        versionType: ConstraintVersionType::RANGE,
                        version: new VersionRange(
                            lowest: new Version(major: 12, minor: 4, patch: 0),
                            highest: new Version(major: 13, minor: 4, patch: 99),
                        ),
                    ),
                ]),
            ),
        );
    }

    #[Test]
    public function generateProducesValidPhpFileHeader(): void
    {
        $output = $this->writer->generate($this->minimalDto());

        self::assertStringStartsWith("<?php\n", $output);
        self::assertStringContainsString('$EM_CONF[$_EXTKEY] = [', $output);
        self::assertStringEndsWith("];\n", $output);
    }

    #[Test]
    public function generateDoesNotOutputUnknownProperties(): void
    {
        $dto = new ExtEmConf(
            title: 'Test',
            description: 'Test.',
            version: '1.0.0',
            unknownProperties: ['secret_key' => 'secret_value', 'another_key' => 42],
        );

        $output = $this->writer->generate($dto);

        self::assertStringNotContainsString('secret_key', $output);
        self::assertStringNotContainsString('another_key', $output);
        self::assertStringNotContainsString('secret_value', $output);
    }

    #[Test]
    public function generateAnnotatesDeprecatedClearCacheOnLoad(): void
    {
        $dto = new ExtEmConf(
            title: 'Test',
            description: 'Test.',
            version: '1.0.0',
            clearCacheOnLoad: false,
        );

        $output = $this->writer->generate($dto);

        self::assertStringContainsString("'clearCacheOnLoad' => false, // @deprecated", $output);
    }

    #[Test]
    public function generateOmitsClearCacheOnLoadForTargetV13(): void
    {
        $dto = new ExtEmConf(
            title: 'Test',
            description: 'Test.',
            version: '1.0.0',
            clearCacheOnLoad: false,
        );

        $output = $this->writer->generate($dto, Typo3Version::V13);

        self::assertStringNotContainsString('clearCacheOnLoad', $output);
    }

    #[Test]
    public function generateIncludesClearCacheOnLoadForTargetV11(): void
    {
        $dto = new ExtEmConf(
            title: 'Test',
            description: 'Test.',
            version: '1.0.0',
            clearCacheOnLoad: true,
        );

        $output = $this->writer->generate($dto, Typo3Version::V11);

        self::assertStringContainsString("'clearCacheOnLoad' => true, // @deprecated", $output);
    }

    #[Test]
    public function generateRendersVersionRangeCorrectly(): void
    {
        $output = $this->writer->generate($this->minimalDto());

        self::assertStringContainsString("'typo3' => '12.4.0-13.4.99'", $output);
    }

    #[Test]
    public function generateRendersAllVersionsAsEmptyString(): void
    {
        $dto = new ExtEmConf(
            title: 'Test',
            description: 'Test.',
            version: '1.0.0',
            constraints: new Constraints(
                suggests: new DependencyCollection([
                    new Dependency(
                        extensionKey: 'news',
                        versionType: ConstraintVersionType::ALL,
                        version: new AllVersions(),
                    ),
                ]),
            ),
        );

        $output = $this->writer->generate($dto);

        self::assertStringContainsString("'news' => ''", $output);
    }

    #[Test]
    public function generateRendersSingleVersionCorrectly(): void
    {
        $dto = new ExtEmConf(
            title: 'Test',
            description: 'Test.',
            version: '1.0.0',
            constraints: new Constraints(
                depends: new DependencyCollection([
                    new Dependency(
                        extensionKey: 'fluid',
                        versionType: ConstraintVersionType::VERSION,
                        version: new SingleVersion(new Version(major: 12, minor: 4, patch: 0)),
                    ),
                ]),
            ),
        );

        $output = $this->writer->generate($dto);

        self::assertStringContainsString("'fluid' => '12.4.0'", $output);
    }

    #[Test]
    public function generateRendersAutoloadPsr4Correctly(): void
    {
        $dto = new ExtEmConf(
            title: 'Test',
            description: 'Test.',
            version: '1.0.0',
            autoload: new Autoload(psr4: ['MyVendor\\MyExt\\' => 'Classes/']),
        );

        $output = $this->writer->generate($dto);

        self::assertStringContainsString("'psr-4'", $output);
        self::assertStringContainsString("'MyVendor\\\\MyExt\\\\'", $output);
        self::assertStringContainsString("'Classes/'", $output);
    }

    #[Test]
    public function generateRendersEnumCategoryValue(): void
    {
        $output = $this->writer->generate($this->minimalDto());

        self::assertStringContainsString("'category' => 'plugin'", $output);
        self::assertStringContainsString("'state' => 'stable'", $output);
    }

    #[Test]
    public function generatedFileCanBeReadBackByReader(): void
    {
        $original = $this->minimalDto();
        $content = $this->writer->generate($original);

        $tmpFile = tempnam(sys_get_temp_dir(), 'sb_releaser_test_') . '.php';
        try {
            file_put_contents($tmpFile, $content);
            $result = $this->reader->read($tmpFile);

            self::assertSame(ResultState::SUCCESS, $result->state);
            self::assertNotNull($result->extEmConf);

            $dto = $result->extEmConf;
            self::assertSame('Test Extension', $dto->title);
            self::assertSame('1.0.0', $dto->version);
            self::assertSame(Category::PLUGIN, $dto->category);
            self::assertSame(State::STABLE, $dto->state);
        } finally {
            @unlink($tmpFile);
        }
    }

    #[Test]
    public function fullPipelineReadValidateNormalizeWriteProducesCleanOutput(): void
    {
        $reader = new ExtEmConfReader();
        $validator = new ExtEmConfValidator();
        $normalizer = new ExtEmConfNormalizer();
        $writer = new ExtEmConfWriter();

        $readResult = $reader->read($this->fixtureFile('v13_full'));
        self::assertSame(ResultState::SUCCESS, $readResult->state);
        self::assertNotNull($readResult->extEmConf);

        $validationResult = $validator->validate($readResult->extEmConf, Typo3Version::V13);
        self::assertSame(ResultState::SUCCESS, $validationResult->state);

        $normResult = $normalizer->normalize($readResult->extEmConf, Typo3Version::V13);
        self::assertFalse($normResult->hasChanges);

        $output = $writer->generate($normResult->extEmConf, Typo3Version::V13);
        self::assertStringContainsString("'title' => 'V13 Full Extension'", $output);
        self::assertStringContainsString("'typo3' => '13.4.0-13.4.99'", $output);
        self::assertStringNotContainsString('clearCacheOnLoad', $output);
        self::assertStringNotContainsString('uploadfolder', $output);
    }

    #[Test]
    public function fullPipelineDropsDeprecatedFieldsWhenNormalizingForV13(): void
    {
        $reader = new ExtEmConfReader();
        $normalizer = new ExtEmConfNormalizer();
        $writer = new ExtEmConfWriter();

        $readResult = $reader->read($this->fixtureFile('deprecated_keys'));
        self::assertNotNull($readResult->extEmConf);

        $normResult = $normalizer->normalize($readResult->extEmConf, Typo3Version::V13);
        self::assertTrue($normResult->hasChanges);

        $output = $writer->generate($normResult->extEmConf, Typo3Version::V13);
        self::assertStringNotContainsString('clearCacheOnLoad', $output);
        self::assertStringNotContainsString('uploadfolder', $output);
        self::assertStringNotContainsString('createDirs', $output);
    }

    #[Test]
    public function unknownKeysSurviveReadButAreAbsentFromWrittenOutput(): void
    {
        $reader = new ExtEmConfReader();
        $writer = new ExtEmConfWriter();

        $readResult = $reader->read($this->fixtureFile('invalid_keys'));
        self::assertNotNull($readResult->extEmConf);

        // Unknown keys are preserved in the DTO after reading
        self::assertArrayHasKey('unknown_custom_key', $readResult->extEmConf->unknownProperties);
        self::assertArrayHasKey('another_unknown', $readResult->extEmConf->unknownProperties);

        // But they must not appear in the written output
        $output = $writer->generate($readResult->extEmConf);
        self::assertStringNotContainsString('unknown_custom_key', $output);
        self::assertStringNotContainsString('another_unknown', $output);
    }
}
