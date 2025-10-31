<?php

declare(strict_types=1);

namespace Tourze\AutoJsControlBundle;

use DeviceBundle\DeviceBundle;
use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Doctrine\DBAL\Types\Type;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Tourze\AutoJsControlBundle\DbalType\TestBigIntType;
use Tourze\BundleDependency\BundleDependencyInterface;
use Tourze\DoctrineIndexedBundle\DoctrineIndexedBundle;
use Tourze\DoctrineIpBundle\DoctrineIpBundle;
use Tourze\DoctrineTimestampBundle\DoctrineTimestampBundle;
use Tourze\DoctrineTrackBundle\DoctrineTrackBundle;
use Tourze\DoctrineUserBundle\DoctrineUserBundle;
use Tourze\LockServiceBundle\LockServiceBundle;
use Tourze\RedisDedicatedConnectionBundle\RedisDedicatedConnectionBundle;
use Tourze\RoutingAutoLoaderBundle\RoutingAutoLoaderBundle;

class AutoJsControlBundle extends Bundle implements BundleDependencyInterface
{
    public function boot(): void
    {
        parent::boot();

        // 在测试环境中覆盖 DBAL BigInt 类型
        // 使用 isset() 和反射来安全检查容器是否已初始化
        if (isset($this->container)) {
            try {
                $environment = $this->container->getParameter('kernel.environment');
                if ('test' === $environment) {
                    $this->registerTestDbalTypes();
                }
            } catch (\Exception) {
                // 容器未完全初始化或参数不存在，跳过DBAL类型注册
                // 这是正常情况，在单独调用boot()方法的测试中会发生
            }
        }
    }

    private function registerTestDbalTypes(): void
    {
        // 使用 Doctrine DBAL Type 系统注册自定义类型
        // 这会覆盖默认的 bigint 类型，解决 SQLite 返回整数的问题
        if (!class_exists(Type::class)) {
            return;
        }

        $typeName = 'bigint';
        $typeClass = TestBigIntType::class;

        // 检查类型是否已注册
        if (!Type::hasType($typeName)) {
            Type::addType($typeName, $typeClass);
        } else {
            // 覆盖已存在的类型
            Type::overrideType($typeName, $typeClass);
        }
    }

    public static function getBundleDependencies(): array
    {
        return [
            DoctrineBundle::class => ['all' => true],
            DoctrineTimestampBundle::class => ['all' => true],
            DoctrineTrackBundle::class => ['all' => true],
            DoctrineUserBundle::class => ['all' => true],
            DoctrineIndexedBundle::class => ['all' => true],
            DoctrineIpBundle::class => ['all' => true],
            DeviceBundle::class => ['all' => true],
            LockServiceBundle::class => ['all' => true],
            RedisDedicatedConnectionBundle::class => ['all' => true],
            RoutingAutoLoaderBundle::class => ['all' => true],
        ];
    }
}
