<?php

declare(strict_types=1);

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $container, ContainerBuilder $builder): void {
    $builder->registerAttributeForAutoconfiguration(
        AsCommand::class,
        static function (ChildDefinition $definition, AsCommand $attribute): void {
            $definition->addTag('console.command');
        },
    );

    $container->services()
        ->defaults()
            ->autowire()
            ->autoconfigure()
        ->load('SBReleaser\\', __DIR__ . '/../src/')
            ->exclude([__DIR__ . '/../src/Kernel.php']);
};
