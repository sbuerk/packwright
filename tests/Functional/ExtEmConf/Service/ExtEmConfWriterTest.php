<?php

declare(strict_types=1);

namespace SBReleaser\Tests\Functional\ExtEmConf\Service;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SBReleaser\ExtEmConf\Dto\Autoload;
use SBReleaser\ExtEmConf\Dto\Autoload\Psr4Collection;
use SBReleaser\ExtEmConf\Dto\Autoload\Psr4Entry;
use SBReleaser\ExtEmConf\Dto\Constraints\Constraints;
use SBReleaser\ExtEmConf\Dto\Constraints\Dependency;
use SBReleaser\ExtEmConf\Dto\Constraints\DependencyCollection;
use SBReleaser\ExtEmConf\Dto\Constraints\VersionValue\AllVersions;
use SBReleaser\ExtEmConf\Dto\Constraints\VersionValue\SingleVersion;
use SBReleaser\ExtEmConf\Dto\Constraints\VersionValue\VersionRange;
use SBReleaser\ExtEmConf\Dto\ExtEmConf;
use SBReleaser\ExtEmConf\Dto\Version;
use SBReleaser\ExtEmConf\Enum\Category;
use SBReleaser\ExtEmConf\Enum\ConstraintVersionType;
use SBReleaser\ExtEmConf\Enum\ResultState;
use SBReleaser\ExtEmConf\Enum\State;
use SBReleaser\ExtEmConf\Enum\Typo3Version;
use SBReleaser\ExtEmConf\Service\ExtEmConfNormalizer;
use SBReleaser\ExtEmConf\Service\ExtEmConfReader;
use SBReleaser\ExtEmConf\Service\ExtEmConfValidator;
use SBReleaser\ExtEmConf\Service\ExtEmConfWriter;

final class ExtEmConfWriterTest extends TestCase
{
    #[Test]
    public function generateProducesValidPhpFileHeader(): void
    {
        $output = (new ExtEmConfWriter())->generate(new ExtEmConf(
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
        ));

        self::assertStringStartsWith("<?php\n", $output);
        self::assertStringContainsString('$EM_CONF[$_EXTKEY] = [', $output);
        self::assertStringEndsWith("];\n", $output);
    }

    #[Test]
    public function generateDoesNotOutputUnknownProperties(): void
    {
        $output = (new ExtEmConfWriter())->generate(new ExtEmConf(
            title: 'Test',
            description: 'Test.',
            version: '1.0.0',
            unknownProperties: ['secret_key' => 'secret_value', 'another_key' => 42],
        ));

        self::assertStringNotContainsString('secret_key', $output);
        self::assertStringNotContainsString('another_key', $output);
        self::assertStringNotContainsString('secret_value', $output);
    }

    #[Test]
    public function generateAnnotatesDeprecatedClearCacheOnLoad(): void
    {
        $output = (new ExtEmConfWriter())->generate(new ExtEmConf(
            title: 'Test',
            description: 'Test.',
            version: '1.0.0',
            clearCacheOnLoad: false,
        ));

        self::assertStringContainsString("'clearCacheOnLoad' => false, // @deprecated", $output);
    }

    #[Test]
    public function generateOmitsClearCacheOnLoadForTargetV13(): void
    {
        $output = (new ExtEmConfWriter())->generate(
            new ExtEmConf(
                title: 'Test',
                description: 'Test.',
                version: '1.0.0',
                clearCacheOnLoad: false,
            ),
            Typo3Version::V13,
        );

        self::assertStringNotContainsString('clearCacheOnLoad', $output);
    }

    #[Test]
    public function generateIncludesClearCacheOnLoadForTargetV11(): void
    {
        $output = (new ExtEmConfWriter())->generate(
            new ExtEmConf(
                title: 'Test',
                description: 'Test.',
                version: '1.0.0',
                clearCacheOnLoad: true,
            ),
            Typo3Version::V11,
        );

        self::assertStringContainsString("'clearCacheOnLoad' => true, // @deprecated", $output);
    }

    #[Test]
    public function generateRendersVersionRangeCorrectly(): void
    {
        $output = (new ExtEmConfWriter())->generate(new ExtEmConf(
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
        ));

        self::assertStringContainsString("'typo3' => '12.4.0-13.4.99'", $output);
    }

    #[Test]
    public function generateRendersAllVersionsAsEmptyString(): void
    {
        $output = (new ExtEmConfWriter())->generate(new ExtEmConf(
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
        ));

        self::assertStringContainsString("'news' => ''", $output);
    }

    #[Test]
    public function generateRendersSingleVersionCorrectly(): void
    {
        $output = (new ExtEmConfWriter())->generate(new ExtEmConf(
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
        ));

        self::assertStringContainsString("'fluid' => '12.4.0'", $output);
    }

    #[Test]
    public function generateRendersAutoloadPsr4Correctly(): void
    {
        $output = (new ExtEmConfWriter())->generate(new ExtEmConf(
            title: 'Test',
            description: 'Test.',
            version: '1.0.0',
            autoload: new Autoload(
                psr4: new Psr4Collection([new Psr4Entry(namespace: 'MyVendor\\MyExt\\', path: 'Classes/')]),
            ),
        ));

        self::assertStringContainsString("'psr-4'", $output);
        self::assertStringContainsString("'MyVendor\\\\MyExt\\\\'", $output);
        self::assertStringContainsString("'Classes/'", $output);
    }

    #[Test]
    public function generateRendersEnumCategoryValue(): void
    {
        $output = (new ExtEmConfWriter())->generate(new ExtEmConf(
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
        ));

        self::assertStringContainsString("'category' => 'plugin'", $output);
        self::assertStringContainsString("'state' => 'stable'", $output);
    }

    #[Test]
    public function generatedFileCanBeReadBackByReader(): void
    {
        $content = (new ExtEmConfWriter())->generate(new ExtEmConf(
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
        ));

        $tmpFile = tempnam(sys_get_temp_dir(), 'sb_releaser_test_') . '.php';
        try {
            file_put_contents($tmpFile, $content);
            $result = (new ExtEmConfReader())->read($tmpFile);

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
        $readResult = (new ExtEmConfReader())->read(__DIR__ . '/../Fixtures/v13_full/ext_emconf.php');
        self::assertSame(ResultState::SUCCESS, $readResult->state);
        self::assertNotNull($readResult->extEmConf);

        $validationResult = (new ExtEmConfValidator())->validate($readResult->extEmConf, Typo3Version::V13);
        self::assertSame(ResultState::SUCCESS, $validationResult->state);

        $normResult = (new ExtEmConfNormalizer())->normalize($readResult->extEmConf, Typo3Version::V13);
        self::assertFalse($normResult->hasChanges);

        $output = (new ExtEmConfWriter())->generate($normResult->extEmConf, Typo3Version::V13);
        self::assertStringContainsString("'title' => 'V13 Full Extension'", $output);
        self::assertStringContainsString("'typo3' => '13.4.0-13.4.99'", $output);
        self::assertStringNotContainsString('clearCacheOnLoad', $output);
        self::assertStringNotContainsString('uploadfolder', $output);
    }

    #[Test]
    public function fullPipelineDropsDeprecatedFieldsWhenNormalizingForV13(): void
    {
        $readResult = (new ExtEmConfReader())->read(__DIR__ . '/../Fixtures/deprecated_keys/ext_emconf.php');
        self::assertNotNull($readResult->extEmConf);

        $normResult = (new ExtEmConfNormalizer())->normalize($readResult->extEmConf, Typo3Version::V13);
        self::assertTrue($normResult->hasChanges);

        $output = (new ExtEmConfWriter())->generate($normResult->extEmConf, Typo3Version::V13);
        self::assertStringNotContainsString('clearCacheOnLoad', $output);
        self::assertStringNotContainsString('uploadfolder', $output);
        self::assertStringNotContainsString('createDirs', $output);
    }

    #[Test]
    public function unknownKeysSurviveReadButAreAbsentFromWrittenOutput(): void
    {
        $readResult = (new ExtEmConfReader())->read(__DIR__ . '/../Fixtures/invalid_keys/ext_emconf.php');
        self::assertNotNull($readResult->extEmConf);

        self::assertArrayHasKey('unknown_custom_key', $readResult->extEmConf->unknownProperties);
        self::assertArrayHasKey('another_unknown', $readResult->extEmConf->unknownProperties);

        $output = (new ExtEmConfWriter())->generate($readResult->extEmConf);
        self::assertStringNotContainsString('unknown_custom_key', $output);
        self::assertStringNotContainsString('another_unknown', $output);
    }
}
