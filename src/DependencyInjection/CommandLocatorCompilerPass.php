<?php

namespace App\DependencyInjection;

use App\Interfaces\SingleCommandInterface;
use function call_user_func;
use function is_a;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

final class CommandLocatorCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        $application = $container->getDefinition(Application::class);

        foreach ($container->getDefinitions() as $name => $definition) {
            if (
                is_a($definition->getClass(), Command::class, true)
                && !$definition->isAbstract()
                && str_starts_with($definition->getClass(), 'App\\')
            ) {
                $application->addMethodCall('add', [new Reference($name)]);

                if (is_a($definition->getClass(), SingleCommandInterface::class, true)) {
                    $this->setDefaultCommand($definition, $application);
                }
            }
        }
    }

    private function setDefaultCommand(Definition $definition, Definition $application)
    {
        $class = $definition->getClass();
        $commandName = call_user_func([$class, 'getCommandName']);

        $application->addMethodCall('setDefaultCommand', [$commandName, true]);
    }
}
