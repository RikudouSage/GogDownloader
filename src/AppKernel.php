<?php

namespace App;

use App\DependencyInjection\CommandLocatorCompilerPass;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel;

final class AppKernel extends Kernel
{
    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
    }

    public function registerContainerConfiguration(LoaderInterface $loader)
    {
        $loader->load(__DIR__ . '/../config/services.yaml');
    }

    public function getCacheDir(): string
    {
        return sys_get_temp_dir() . "/{$this->getAppKey()}/cache";
    }

    public function getLogDir(): string
    {
        return sys_get_temp_dir() . "/{$this->getAppKey()}/logs";
    }

    protected function build(ContainerBuilder $container)
    {
        $container->addCompilerPass(new CommandLocatorCompilerPass());
    }

    private function getAppKey(): string
    {
        return md5(__FILE__);
    }
}
