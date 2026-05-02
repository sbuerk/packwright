<?php

declare(strict_types=1);

namespace Packwright\Tests\Unit\ExtEmConf\Service;

use Packwright\ExtEmConf\Dto\Autoload;
use Packwright\ExtEmConf\Dto\Autoload\ClassmapCollection;
use Packwright\ExtEmConf\Dto\Autoload\ClassmapEntry;
use Packwright\ExtEmConf\Dto\Autoload\Psr4Collection;
use Packwright\ExtEmConf\Dto\Autoload\Psr4Entry;
use Packwright\ExtEmConf\Dto\Constraints\Constraints;
use Packwright\ExtEmConf\Dto\Constraints\Dependency;
use Packwright\ExtEmConf\Dto\Constraints\DependencyCollection;
use Packwright\ExtEmConf\Dto\Constraints\MigratableConstraints;
use Packwright\ExtEmConf\Dto\Constraints\VersionValue\AllVersions;
use Packwright\ExtEmConf\Dto\Constraints\VersionValue\SingleVersion;
use Packwright\ExtEmConf\Dto\Constraints\VersionValue\VersionRange;
use Packwright\ExtEmConf\Dto\ExtEmConf;
use Packwright\ExtEmConf\Dto\Unknown\UnknownProperty;
use Packwright\ExtEmConf\Dto\Unknown\UnknownPropertyCollection;
use Packwright\ExtEmConf\Dto\Version;
use Packwright\ExtEmConf\Enum\Category;
use Packwright\ExtEmConf\Enum\ConstraintVersionType;
use Packwright\ExtEmConf\Enum\State;
use Packwright\ExtEmConf\Service\ExtEmConfArraySerializer;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ExtEmConfArraySerializerTest extends TestCase
{
    #[Test]
    public function serializesScalarFieldsAsIs(): void
    {
        $payload = (new ExtEmConfArraySerializer())->serialize(new ExtEmConf(
            title: 'My Extension',
            description: 'A description.',
            version: '1.2.3',
            author: 'John Doe',
            authorEmail: 'john@example.com',
            authorCompany: 'ACME Corp',
        ));

        self::assertSame('My Extension', $payload['title']);
        self::assertSame('A description.', $payload['description']);
        self::assertSame('1.2.3', $payload['version']);
        self::assertSame('John Doe', $payload['author']);
        self::assertSame('john@example.com', $payload['author_email']);
        self::assertSame('ACME Corp', $payload['author_company']);
    }

    #[Test]
    public function serializesEnumValuesToTheirBackingString(): void
    {
        $payload = (new ExtEmConfArraySerializer())->serialize(new ExtEmConf(
            category: Category::PLUGIN,
            state: State::STABLE,
        ));

        self::assertSame('plugin', $payload['category']);
        self::assertSame('stable', $payload['state']);
    }

    #[Test]
    public function serializesUnrecognisedRawCategoryAndStateStringsUnchanged(): void
    {
        $payload = (new ExtEmConfArraySerializer())->serialize(new ExtEmConf(
            category: 'made-up',
            state: 'unknown',
        ));

        self::assertSame('made-up', $payload['category']);
        self::assertSame('unknown', $payload['state']);
    }

    #[Test]
    public function serializesNullCategoryAndStateAsNull(): void
    {
        $payload = (new ExtEmConfArraySerializer())->serialize(new ExtEmConf());

        self::assertNull($payload['category']);
        self::assertNull($payload['state']);
    }

    #[Test]
    public function serializesEmptyConstraintsAsEmptyMaps(): void
    {
        $payload = (new ExtEmConfArraySerializer())->serialize(new ExtEmConf());

        self::assertSame(
            [
                'depends' => [],
                'conflicts' => [],
                'suggests' => [],
                'migratable' => [
                    'depend' => [],
                    'suggest' => [],
                    'conflict' => [],
                ],
            ],
            $payload['constraints'],
        );
    }

    #[Test]
    public function serializesDependencyCollectionsKeyedByExtensionWithVersionString(): void
    {
        $constraints = new Constraints(
            depends: new DependencyCollection([
                new Dependency(
                    extensionKey: 'typo3',
                    versionType: ConstraintVersionType::RANGE,
                    version: new VersionRange(
                        lowest: Version::fromString('12.4.0'),
                        highest: Version::fromString('13.4.99'),
                    ),
                ),
                new Dependency(
                    extensionKey: 'php',
                    versionType: ConstraintVersionType::VERSION,
                    version: new SingleVersion(Version::fromString('8.2.0')),
                ),
            ]),
            suggests: new DependencyCollection([
                new Dependency(
                    extensionKey: 'news',
                    versionType: ConstraintVersionType::ALL,
                    version: new AllVersions(),
                ),
            ]),
        );

        $payload = (new ExtEmConfArraySerializer())->serialize(new ExtEmConf(constraints: $constraints));

        self::assertSame(['typo3' => '12.4.0-13.4.99', 'php' => '8.2.0'], $payload['constraints']['depends']);
        self::assertSame(['news' => ''], $payload['constraints']['suggests']);
        self::assertSame([], $payload['constraints']['conflicts']);
    }

    #[Test]
    public function serializesMigratableConstraintsUnderTheirSingularKeys(): void
    {
        $constraints = new Constraints(
            migratable: new MigratableConstraints(
                depend: new DependencyCollection([
                    new Dependency(
                        extensionKey: 'php',
                        versionType: ConstraintVersionType::ALL,
                        version: new AllVersions(),
                    ),
                ]),
                suggest: new DependencyCollection([
                    new Dependency(
                        extensionKey: 'news',
                        versionType: ConstraintVersionType::ALL,
                        version: new AllVersions(),
                    ),
                ]),
                conflict: new DependencyCollection([
                    new Dependency(
                        extensionKey: 'old_ext',
                        versionType: ConstraintVersionType::VERSION,
                        version: new SingleVersion(Version::fromString('1.0.0')),
                    ),
                ]),
            ),
        );

        $payload = (new ExtEmConfArraySerializer())->serialize(new ExtEmConf(constraints: $constraints));

        self::assertSame(
            [
                'depend' => ['php' => ''],
                'suggest' => ['news' => ''],
                'conflict' => ['old_ext' => '1.0.0'],
            ],
            $payload['constraints']['migratable'],
        );
    }

    #[Test]
    public function serializesAutoloadAndAutoloadDevWithCanonicalKeys(): void
    {
        $payload = (new ExtEmConfArraySerializer())->serialize(new ExtEmConf(
            autoload: new Autoload(
                psr4: new Psr4Collection([
                    new Psr4Entry(namespace: 'Vendor\\Ext\\', path: 'Classes/'),
                ]),
                classmap: new ClassmapCollection([
                    new ClassmapEntry(path: 'Resources/PHP/'),
                ]),
            ),
            autoloadDev: new Autoload(
                psr4: new Psr4Collection([
                    new Psr4Entry(namespace: 'Vendor\\Ext\\Tests\\', path: 'Tests/'),
                ]),
            ),
        ));

        self::assertSame(['Vendor\\Ext\\' => 'Classes/'], $payload['autoload']['psr-4']);
        self::assertSame(['Resources/PHP/'], $payload['autoload']['classmap']);
        self::assertSame(['Vendor\\Ext\\Tests\\' => 'Tests/'], $payload['autoload-dev']['psr-4']);
        self::assertSame([], $payload['autoload-dev']['classmap']);
    }

    #[Test]
    public function serializesDeprecatedNullableFieldsPreservingNulls(): void
    {
        $payload = (new ExtEmConfArraySerializer())->serialize(new ExtEmConf(
            clearCacheOnLoad: true,
            uploadfolder: false,
            createDirs: 'fileadmin/uploads/',
        ));

        self::assertTrue($payload['clearCacheOnLoad']);
        self::assertFalse($payload['uploadfolder']);
        self::assertSame('fileadmin/uploads/', $payload['createDirs']);

        $defaults = (new ExtEmConfArraySerializer())->serialize(new ExtEmConf());
        self::assertNull($defaults['clearCacheOnLoad']);
        self::assertNull($defaults['uploadfolder']);
        self::assertNull($defaults['createDirs']);
    }

    #[Test]
    public function serializesUnknownPropertiesPreservingArbitraryValuesAndDeduplicatingOnFirst(): void
    {
        $payload = (new ExtEmConfArraySerializer())->serialize(new ExtEmConf(
            unknownProperties: new UnknownPropertyCollection([
                new UnknownProperty(key: 'custom_key', value: 'first value'),
                new UnknownProperty(key: 'numeric', value: 42),
                new UnknownProperty(key: 'nested', value: ['a' => 1, 'b' => 'two']),
                new UnknownProperty(key: 'custom_key', value: 'duplicate ignored'),
            ]),
        ));

        self::assertSame(
            [
                'custom_key' => 'first value',
                'numeric' => 42,
                'nested' => ['a' => 1, 'b' => 'two'],
            ],
            $payload['unknownProperties'],
        );
    }

    #[Test]
    public function topLevelKeyOrderMirrorsExtEmConfStructure(): void
    {
        $payload = (new ExtEmConfArraySerializer())->serialize(new ExtEmConf());

        self::assertSame(
            [
                'title',
                'description',
                'version',
                'category',
                'state',
                'author',
                'author_email',
                'author_company',
                'constraints',
                'autoload',
                'autoload-dev',
                'clearCacheOnLoad',
                'uploadfolder',
                'createDirs',
                'unknownProperties',
            ],
            array_keys($payload),
        );
    }
}
