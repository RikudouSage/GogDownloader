<?php

namespace App\DependencyInjection;

use Symfony\Component\Console\Application;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

final readonly class EventCoordinatorCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        $application = $container->getDefinition(Application::class);
        $application->addMethodCall('setDispatcher', [new Reference('event_dispatcher')]);
    }
}
