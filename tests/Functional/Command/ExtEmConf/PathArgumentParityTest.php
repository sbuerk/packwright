<?php

declare(strict_types=1);

namespace Packwright\Tests\Functional\Command\ExtEmConf;

use Packwright\Application;
use Packwright\Kernel;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class PathArgumentParityTest extends TestCase
{
    #[Test]
    public function validateAndGetResolveTheSameFileForSingleLiteralPath(): void
    {
        $path = dirname(__DIR__, 2) . '/ExtEmConf/Fixtures/minimal/ext_emconf.php';

        $kernel = new Kernel(dirname(__DIR__, 4));
        /** @var Application $application */
        $application = $kernel->createContainer()->get(Application::class);

        $validateTester = new CommandTester($application->find('extemconf:validate'));
        $validateTester->execute(['paths' => [$path], '--json' => true]);
        $validateDisplay = $validateTester->getDisplay();
        self::assertStringNotContainsString("\n", trim($validateDisplay), 'JSON output must be compact (single line)');
        /** @var array{files: list<array{path: string}>} $validatePayload */
        $validatePayload = json_decode($validateDisplay, true, flags: JSON_THROW_ON_ERROR);
        $validatePaths = array_map(static fn(array $f): string => $f['path'], $validatePayload['files']);

        $getTester = new CommandTester($application->find('extemconf:get'));
        $getTester->execute(['paths' => [$path], '--json' => true]);
        $getDisplay = $getTester->getDisplay();
        self::assertStringNotContainsString("\n", trim($getDisplay), 'JSON output must be compact (single line)');
        /** @var array{files: list<array{path: string}>} $getPayload */
        $getPayload = json_decode($getDisplay, true, flags: JSON_THROW_ON_ERROR);
        $getPaths = array_map(static fn(array $f): string => $f['path'], $getPayload['files']);

        self::assertSame([realpath($path)], $validatePaths);
        self::assertSame($validatePaths, $getPaths);
    }

    #[Test]
    public function validateAndGetResolveTheSameFilesForMultipleLiteralPaths(): void
    {
        $first = dirname(__DIR__, 2) . '/ExtEmConf/Fixtures/minimal/ext_emconf.php';
        $second = dirname(__DIR__, 2) . '/ExtEmConf/Fixtures/v13_full/ext_emconf.php';

        $kernel = new Kernel(dirname(__DIR__, 4));
        /** @var Application $application */
        $application = $kernel->createContainer()->get(Application::class);

        $validateTester = new CommandTester($application->find('extemconf:validate'));
        $validateTester->execute(['paths' => [$first, $second], '--json' => true]);
        $validateDisplay = $validateTester->getDisplay();
        self::assertStringNotContainsString("\n", trim($validateDisplay), 'JSON output must be compact (single line)');
        /** @var array{files: list<array{path: string}>} $validatePayload */
        $validatePayload = json_decode($validateDisplay, true, flags: JSON_THROW_ON_ERROR);
        $validatePaths = array_map(static fn(array $f): string => $f['path'], $validatePayload['files']);

        $getTester = new CommandTester($application->find('extemconf:get'));
        $getTester->execute(['paths' => [$first, $second], '--json' => true]);
        $getDisplay = $getTester->getDisplay();
        self::assertStringNotContainsString("\n", trim($getDisplay), 'JSON output must be compact (single line)');
        /** @var array{files: list<array{path: string}>} $getPayload */
        $getPayload = json_decode($getDisplay, true, flags: JSON_THROW_ON_ERROR);
        $getPaths = array_map(static fn(array $f): string => $f['path'], $getPayload['files']);

        $expected = [realpath($first), realpath($second)];
        sort($expected);
        self::assertSame($expected, $validatePaths);
        self::assertSame($validatePaths, $getPaths);
    }

    #[Test]
    public function validateAndGetResolveTheSameFilesForGlobPattern(): void
    {
        $pattern = dirname(__DIR__, 2) . '/ExtEmConf/Fixtures/Packages/*/*/ext_emconf.php';

        $kernel = new Kernel(dirname(__DIR__, 4));
        /** @var Application $application */
        $application = $kernel->createContainer()->get(Application::class);

        $validateTester = new CommandTester($application->find('extemconf:validate'));
        $validateTester->execute(['paths' => [$pattern], '--json' => true]);
        $validateDisplay = $validateTester->getDisplay();
        self::assertStringNotContainsString("\n", trim($validateDisplay), 'JSON output must be compact (single line)');
        /** @var array{files: list<array{path: string}>} $validatePayload */
        $validatePayload = json_decode($validateDisplay, true, flags: JSON_THROW_ON_ERROR);
        $validatePaths = array_map(static fn(array $f): string => $f['path'], $validatePayload['files']);

        $getTester = new CommandTester($application->find('extemconf:get'));
        $getTester->execute(['paths' => [$pattern], '--json' => true]);
        $getDisplay = $getTester->getDisplay();
        self::assertStringNotContainsString("\n", trim($getDisplay), 'JSON output must be compact (single line)');
        /** @var array{files: list<array{path: string}>} $getPayload */
        $getPayload = json_decode($getDisplay, true, flags: JSON_THROW_ON_ERROR);
        $getPaths = array_map(static fn(array $f): string => $f['path'], $getPayload['files']);

        self::assertCount(5, $validatePaths);
        self::assertSame($validatePaths, $getPaths);
        foreach ($validatePaths as $resolvedPath) {
            self::assertStringNotContainsString('/Tests/', $resolvedPath);
        }
    }
}
