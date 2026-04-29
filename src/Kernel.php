<?php

declare(strict_types=1);

namespace SBReleaser;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;

final class Kernel
{
    private const COMPILED_CONTAINER_FILE = '/var/cache/SBReleaserContainer.php';

    public function __construct(
        private readonly string $projectDir,
    ) {}

    public function createContainer(): ContainerInterface
    {
        if (\Phar::running() !== '') {
            require_once $this->projectDir . self::COMPILED_CONTAINER_FILE;
            return new \SBReleaserContainer();
        }

        return $this->buildContainer();
    }

    public function buildContainer(): ContainerInterface
    {
        $container = new ContainerBuilder();
        $loader = new PhpFileLoader($container, new FileLocator($this->projectDir . '/config'));
        $loader->load('services.php');
        $container->compile(true);

        return $container;
    }
}
