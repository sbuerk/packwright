<?php

declare(strict_types=1);

namespace SBReleaser\Tests\Functional\ExtEmConf\Service;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SBReleaser\ExtEmConf\Dto\ExtEmConf;
use SBReleaser\ExtEmConf\Dto\Version;
use SBReleaser\ExtEmConf\Enum\Category;
use SBReleaser\ExtEmConf\Enum\State;
use SBReleaser\ExtEmConf\Service\ExtEmConfNormalizer;

final class ExtEmConfNormalizerTest extends TestCase
{
    private ExtEmConfNormalizer $normalizer;

    protected function setUp(): void
    {
        $this->normalizer = new ExtEmConfNormalizer();
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function baseDto(array $overrides = []): ExtEmConf
    {
        return new ExtEmConf(
            title: 'My Extension',
            description: 'A great extension.',
            version: '1.0.0',
            category: $overrides['category'] ?? Category::PLUGIN,
            state: $overrides['state'] ?? State::STABLE,
            clearCacheOnLoad: $overrides['clearCacheOnLoad'] ?? null,
            uploadfolder: $overrides['uploadfolder'] ?? null,
            createDirs: $overrides['createDirs'] ?? null,
            unknownProperties: $overrides['unknownProperties'] ?? [],
        );
    }

    #[Test]
    public function normalizeCleanDtoHasNoChanges(): void
    {
        $result = $this->normalizer->normalize($this->baseDto());

        self::assertFalse($result->hasChanges);
        self::assertEmpty($result->droppedItems);
    }

    #[Test]
    public function normalizeDropsRawStringCategory(): void
    {
        $result = $this->normalizer->normalize($this->baseDto(['category' => 'totally_unknown']));

        self::assertTrue($result->hasChanges);
        self::assertNull($result->extEmConf->category);

        $paths = array_map(static fn($i) => $i->path, $result->droppedItems);
        self::assertContains('category', $paths);
    }

    #[Test]
    public function normalizeKeepsValidCategoryEnum(): void
    {
        $result = $this->normalizer->normalize($this->baseDto(['category' => Category::FE]));

        self::assertFalse($result->hasChanges);
        self::assertSame(Category::FE, $result->extEmConf->category);
    }

    #[Test]
    public function normalizeDropsRawStringState(): void
    {
        $result = $this->normalizer->normalize($this->baseDto(['state' => 'totally_unknown']));

        self::assertTrue($result->hasChanges);
        self::assertNull($result->extEmConf->state);

        $paths = array_map(static fn($i) => $i->path, $result->droppedItems);
        self::assertContains('state', $paths);
    }

    #[Test]
    public function normalizeDropsUnknownProperties(): void
    {
        $dto = $this->baseDto(['unknownProperties' => ['my_key' => 'value', 'another' => 42]]);
        $result = $this->normalizer->normalize($dto);

        self::assertTrue($result->hasChanges);
        self::assertSame([], $result->extEmConf->unknownProperties);

        $paths = array_map(static fn($i) => $i->path, $result->droppedItems);
        self::assertContains('my_key', $paths);
        self::assertContains('another', $paths);
    }

    #[Test]
    public function normalizeDropsUploadfolderForTargetV9(): void
    {
        $dto = $this->baseDto(['uploadfolder' => false]);
        $result = $this->normalizer->normalize($dto, Version::fromString('9.5.0'));

        self::assertTrue($result->hasChanges);
        self::assertNull($result->extEmConf->uploadfolder);

        $paths = array_map(static fn($i) => $i->path, $result->droppedItems);
        self::assertContains('uploadfolder', $paths);
    }

    #[Test]
    public function normalizeKeepsUploadfolderForTargetV8(): void
    {
        $dto = $this->baseDto(['uploadfolder' => false]);
        $result = $this->normalizer->normalize($dto, Version::fromString('8.7.0'));

        self::assertFalse($result->hasChanges);
        self::assertFalse($result->extEmConf->uploadfolder);
    }

    #[Test]
    public function normalizeDropsCreateDirsForTargetV9(): void
    {
        $dto = $this->baseDto(['createDirs' => 'uploads/my_ext']);
        $result = $this->normalizer->normalize($dto, Version::fromString('9.5.0'));

        self::assertTrue($result->hasChanges);
        self::assertNull($result->extEmConf->createDirs);
    }

    #[Test]
    public function normalizeDropsClearCacheOnLoadForTargetV121(): void
    {
        $dto = $this->baseDto(['clearCacheOnLoad' => false]);
        $result = $this->normalizer->normalize($dto, Version::fromString('12.1.0'));

        self::assertTrue($result->hasChanges);
        self::assertNull($result->extEmConf->clearCacheOnLoad);

        $paths = array_map(static fn($i) => $i->path, $result->droppedItems);
        self::assertContains('clearCacheOnLoad', $paths);
    }

    #[Test]
    public function normalizeKeepsClearCacheOnLoadForTargetV120(): void
    {
        $dto = $this->baseDto(['clearCacheOnLoad' => false]);
        $result = $this->normalizer->normalize($dto, Version::fromString('12.0.0'));

        self::assertFalse($result->hasChanges);
        self::assertFalse($result->extEmConf->clearCacheOnLoad);
    }

    #[Test]
    public function normalizeDropsClearCacheOnLoadForTargetV13(): void
    {
        $dto = $this->baseDto(['clearCacheOnLoad' => true]);
        $result = $this->normalizer->normalize($dto, Version::fromString('13.4.0'));

        self::assertTrue($result->hasChanges);
        self::assertNull($result->extEmConf->clearCacheOnLoad);
    }

    #[Test]
    public function normalizeWithoutTargetVersionPreservesDeprecatedFields(): void
    {
        $dto = $this->baseDto([
            'clearCacheOnLoad' => false,
            'uploadfolder' => false,
            'createDirs' => 'uploads/',
        ]);
        $result = $this->normalizer->normalize($dto);

        self::assertFalse($result->hasChanges);
        self::assertFalse($result->extEmConf->clearCacheOnLoad);
        self::assertFalse($result->extEmConf->uploadfolder);
        self::assertSame('uploads/', $result->extEmConf->createDirs);
    }

    #[Test]
    public function normalizeAlwaysReturnsNewDtoInstance(): void
    {
        $dto = $this->baseDto();
        $result = $this->normalizer->normalize($dto);

        self::assertNotSame($dto, $result->extEmConf);
    }
}
