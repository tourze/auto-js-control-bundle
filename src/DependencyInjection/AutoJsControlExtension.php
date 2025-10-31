<?php

declare(strict_types=1);

namespace Tourze\AutoJsControlBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Tourze\SymfonyDependencyServiceLoader\AutoExtension;

class AutoJsControlExtension extends AutoExtension
{
    protected function getConfigDir(): string
    {
        return __DIR__ . '/../Resources/config';
    }

    public function load(array $configs, ContainerBuilder $container): void
    {
        parent::load($configs, $container);

        // 在测试环境下加载额外的服务配置
        if ('test' === $container->getParameter('kernel.environment')) {
            $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../../config'));
            if (file_exists(__DIR__ . '/../../config/services_test.yaml')) {
                $loader->load('services_test.yaml');
            }
        }
    }
}
