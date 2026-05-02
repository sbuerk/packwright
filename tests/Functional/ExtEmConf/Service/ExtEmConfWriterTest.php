<?php

declare(strict_types=1);

namespace Packwright\Tests\Functional\ExtEmConf\Service;

use Packwright\ExtEmConf\Dto\Autoload;
use Packwright\ExtEmConf\Dto\Autoload\ClassmapCollection;
use Packwright\ExtEmConf\Dto\Autoload\ClassmapEntry;
use Packwright\ExtEmConf\Dto\Autoload\Psr4Collection;
use Packwright\ExtEmConf\Dto\Autoload\Psr4Entry;
use Packwright\ExtEmConf\Dto\Constraints\Constraints;
use Packwright\ExtEmConf\Dto\Constraints\Dependency;
use Packwright\ExtEmConf\Dto\Constraints\DependencyCollection;
use Packwright\ExtEmConf\Dto\Constraints\VersionValue\AllVersions;
use Packwright\ExtEmConf\Dto\Constraints\VersionValue\SingleVersion;
use Packwright\ExtEmConf\Dto\Constraints\VersionValue\VersionRange;
use Packwright\ExtEmConf\Dto\ExtEmConf;
use Packwright\ExtEmConf\Dto\Unknown\UnknownProperty;
use Packwright\ExtEmConf\Dto\Unknown\UnknownPropertyCollection;
use Packwright\ExtEmConf\Dto\Version;
use Packwright\ExtEmConf\Enum\Category;
use Packwright\ExtEmConf\Enum\ConstraintVersionType;
use Packwright\ExtEmConf\Enum\ResultState;
use Packwright\ExtEmConf\Enum\State;
use Packwright\ExtEmConf\Enum\Typo3Version;
use Packwright\ExtEmConf\Service\ExtEmConfNormalizer;
use Packwright\ExtEmConf\Service\ExtEmConfReader;
use Packwright\ExtEmConf\Service\ExtEmConfValidator;
use Packwright\ExtEmConf\Service\ExtEmConfWriter;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

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
            unknownProperties: new UnknownPropertyCollection([
                new UnknownProperty('secret_key', 'secret_value'),
                new UnknownProperty('another_key', 42),
            ]),
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

        $tmpFile = tempnam(sys_get_temp_dir(), 'packwright_test_') . '.php';
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

        self::assertTrue($readResult->extEmConf->unknownProperties->has('unknown_custom_key'));
        self::assertTrue($readResult->extEmConf->unknownProperties->has('another_unknown'));

        $output = (new ExtEmConfWriter())->generate($readResult->extEmConf);
        self::assertStringNotContainsString('unknown_custom_key', $output);
        self::assertStringNotContainsString('another_unknown', $output);
    }

    #[Test]
    public function generateOmitsAutoloadPsr4WhenEmpty(): void
    {
        $output = (new ExtEmConfWriter())->generate(new ExtEmConf(
            title: 'Test',
            description: 'Test.',
            version: '1.0.0',
            autoload: new Autoload(
                classmap: new ClassmapCollection([new ClassmapEntry(path: 'Classes/Legacy/')]),
            ),
        ));

        self::assertStringContainsString("'autoload'", $output);
        self::assertStringContainsString("'classmap'", $output);
        self::assertStringNotContainsString("'psr-4'", $output);
    }

    #[Test]
    public function generateOmitsAutoloadClassmapWhenEmpty(): void
    {
        $output = (new ExtEmConfWriter())->generate(new ExtEmConf(
            title: 'Test',
            description: 'Test.',
            version: '1.0.0',
            autoload: new Autoload(
                psr4: new Psr4Collection([new Psr4Entry(namespace: 'MyVendor\\MyExt\\', path: 'Classes/')]),
            ),
        ));

        self::assertStringContainsString("'autoload'", $output);
        self::assertStringContainsString("'psr-4'", $output);
        self::assertStringNotContainsString("'classmap'", $output);
    }

    #[Test]
    public function generateOmitsAutoloadKeyWhenBothEmpty(): void
    {
        $output = (new ExtEmConfWriter())->generate(new ExtEmConf(
            title: 'Test',
            description: 'Test.',
            version: '1.0.0',
            autoload: new Autoload(),
        ));

        self::assertStringNotContainsString("'autoload'", $output);
    }

    #[Test]
    public function generateOmitsAutoloadDevPsr4WhenEmpty(): void
    {
        $output = (new ExtEmConfWriter())->generate(new ExtEmConf(
            title: 'Test',
            description: 'Test.',
            version: '1.0.0',
            autoloadDev: new Autoload(
                classmap: new ClassmapCollection([new ClassmapEntry(path: 'Tests/Classmap/')]),
            ),
        ));

        self::assertStringContainsString("'autoload-dev'", $output);
        self::assertStringContainsString("'classmap'", $output);
        self::assertStringNotContainsString("'psr-4'", $output);
    }

    #[Test]
    public function generateOmitsAutoloadDevClassmapWhenEmpty(): void
    {
        $output = (new ExtEmConfWriter())->generate(new ExtEmConf(
            title: 'Test',
            description: 'Test.',
            version: '1.0.0',
            autoloadDev: new Autoload(
                psr4: new Psr4Collection([new Psr4Entry(namespace: 'MyVendor\\MyExt\\Tests\\', path: 'Tests/')]),
            ),
        ));

        self::assertStringContainsString("'autoload-dev'", $output);
        self::assertStringContainsString("'psr-4'", $output);
        self::assertStringNotContainsString("'classmap'", $output);
    }

    #[Test]
    public function generateOmitsAutoloadDevKeyWhenBothEmpty(): void
    {
        $output = (new ExtEmConfWriter())->generate(new ExtEmConf(
            title: 'Test',
            description: 'Test.',
            version: '1.0.0',
            autoloadDev: new Autoload(),
        ));

        self::assertStringNotContainsString("'autoload-dev'", $output);
    }
}
