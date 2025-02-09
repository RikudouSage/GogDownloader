<?php

namespace App;

use App\DependencyInjection\CommandLocatorCompilerPass;
use Closure;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\HttpKernel\Kernel;

final class AppKernel extends Kernel
{
    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
    }

    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
        $loader->load(function (ContainerBuilder $container) use ($loader) {
            $kernelLoader = $loader->getResolver()->resolve(__FILE__);
            assert($kernelLoader instanceof PhpFileLoader);
            $kernelLoader->setCurrentDir(__DIR__);
            /** @noinspection PhpPassByRefInspection */
            $instanceof = &Closure::bind(function &() {
                return $this->instanceof;
            }, $kernelLoader, $kernelLoader)();

            $this->configureContainer(new ContainerConfigurator(
                $container,
                $kernelLoader,
                $instanceof,
                __FILE__,
                __FILE__,
                $this->getEnvironment(),
            ));
        });
    }

    public function getCacheDir(): string
    {
        return sys_get_temp_dir() . "/{$this->getAppKey()}/cache";
    }

    public function getLogDir(): string
    {
        return sys_get_temp_dir() . "/{$this->getAppKey()}/logs";
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->import('../config/{packages}/*.yaml');
        $container->import('../config/{packages}/' . $this->environment . '/*.yaml');

        if (is_file(\dirname(__DIR__) . '/config/services.yaml')) {
            $container->import('../config/services.yaml');
            $container->import('../config/{services}_' . $this->environment . '.yaml');
        } elseif (is_file($path = \dirname(__DIR__) . '/config/services.php')) {
            (require $path)($container->withPath($path), $this);
        }
    }

    protected function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new CommandLocatorCompilerPass());
    }

    private function getAppKey(): string
    {
        return md5(file_get_contents(__FILE__) . '2');
    }
}
