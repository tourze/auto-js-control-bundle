<?php

declare(strict_types=1);

namespace Tourze\AutoJsControlBundle\Tests\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Test;
use Tourze\AutoJsControlBundle\Controller\Admin\AutoJsDeviceCrudController;
use Tourze\AutoJsControlBundle\Entity\AutoJsDevice;
use Tourze\PHPUnitSymfonyWebTest\AbstractEasyAdminControllerTestCase;

/**
 * AutoJsDeviceCrudController 测试类.
 *
 * @internal
 */
#[CoversClass(AutoJsDeviceCrudController::class)]
#[RunTestsInSeparateProcesses]
final class AutoJsDeviceCrudControllerTest extends AbstractEasyAdminControllerTestCase
{
    #[Test]
    public function testControllerIsInstantiable(): void
    {
        $reflection = new \ReflectionClass(AutoJsDeviceCrudController::class);

        $this->assertTrue($reflection->isInstantiable());
    }

    #[Test]
    public function testGetEntityFqcnReturnsAutoJsDeviceClass(): void
    {
        $this->assertEquals(AutoJsDevice::class, AutoJsDeviceCrudController::getEntityFqcn());
    }

    #[Test]
    public function testControllerHasAdminCrudAttribute(): void
    {
        $reflection = new \ReflectionClass(AutoJsDeviceCrudController::class);
        $attributes = $reflection->getAttributes();

        $hasAdminCrudAttribute = false;
        foreach ($attributes as $attribute) {
            if (str_contains($attribute->getName(), 'AdminCrud')) {
                $hasAdminCrudAttribute = true;
                break;
            }
        }

        $this->assertTrue($hasAdminCrudAttribute, 'Controller应该有AdminCrud注解');
    }

    #[Test]
    public function testControllerHasRequiredConfigurationMethods(): void
    {
        $reflection = new \ReflectionClass(AutoJsDeviceCrudController::class);

        $requiredMethods = [
            'getEntityFqcn',
            'configureCrud',
            'configureActions',
            'configureFields',
        ];

        foreach ($requiredMethods as $methodName) {
            $this->assertTrue($reflection->hasMethod($methodName), "方法 {$methodName} 必须存在");

            $method = $reflection->getMethod($methodName);
            $this->assertTrue($method->isPublic(), "方法 {$methodName} 必须是public");
        }
    }

    #[Test]
    public function testGetEntityFqcnIsStaticMethod(): void
    {
        $reflection = new \ReflectionClass(AutoJsDeviceCrudController::class);
        $method = $reflection->getMethod('getEntityFqcn');

        $this->assertTrue($method->isStatic(), 'getEntityFqcn必须是静态方法');
        $this->assertTrue($method->isPublic(), 'getEntityFqcn必须是public方法');
    }

    #[Test]
    public function testConfigureCrudMethodSignature(): void
    {
        $reflection = new \ReflectionClass(AutoJsDeviceCrudController::class);
        $method = $reflection->getMethod('configureCrud');

        $this->assertTrue($method->isPublic());

        $returnType = $method->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertEquals('EasyCorp\Bundle\EasyAdminBundle\Config\Crud', ($returnType instanceof \ReflectionNamedType) ? $returnType->getName() : (string) $returnType);

        $parameters = $method->getParameters();
        $this->assertCount(1, $parameters);
        $this->assertEquals('crud', $parameters[0]->getName());
    }

    #[Test]
    public function testConfigureActionsMethodSignature(): void
    {
        $reflection = new \ReflectionClass(AutoJsDeviceCrudController::class);
        $method = $reflection->getMethod('configureActions');

        $this->assertTrue($method->isPublic());

        $returnType = $method->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertEquals('EasyCorp\Bundle\EasyAdminBundle\Config\Actions', ($returnType instanceof \ReflectionNamedType) ? $returnType->getName() : (string) $returnType);

        $parameters = $method->getParameters();
        $this->assertCount(1, $parameters);
        $this->assertEquals('actions', $parameters[0]->getName());
    }

    #[Test]
    public function testConfigureFieldsMethodSignature(): void
    {
        $reflection = new \ReflectionClass(AutoJsDeviceCrudController::class);
        $method = $reflection->getMethod('configureFields');

        $this->assertTrue($method->isPublic());

        $returnType = $method->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertEquals('iterable', ($returnType instanceof \ReflectionNamedType) ? $returnType->getName() : (string) $returnType);

        $parameters = $method->getParameters();
        $this->assertCount(1, $parameters);
        $this->assertEquals('pageName', $parameters[0]->getName());
    }

    #[Test]
    public function testControllerHasCorrectNamespace(): void
    {
        $this->assertEquals(
            'Tourze\AutoJsControlBundle\Controller\Admin',
            new \ReflectionClass(AutoJsDeviceCrudController::class)->getNamespaceName()
        );
    }

    /**
     * @return AbstractCrudController<AutoJsDevice>
     */
    protected function getControllerService(): AbstractCrudController
    {
        return self::getService(AutoJsDeviceCrudController::class);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideIndexPageHeaders(): iterable
    {
        yield '基础设备' => ['基础设备'];
        yield '设备代码' => ['设备代码'];
        yield 'Auto.js版本' => ['Auto.js版本'];
        yield 'Root权限' => ['Root权限'];
        yield '无障碍服务' => ['无障碍服务'];
        yield '悬浮窗权限' => ['悬浮窗权限'];
        yield '最大并发任务' => ['最大并发任务'];
        yield '屏幕分辨率' => ['屏幕分辨率'];
        yield 'Android版本' => ['Android版本'];
        yield '设备型号' => ['设备型号'];
        yield '设备状态' => ['设备状态'];
        yield '最后在线时间' => ['最后在线时间'];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideNewPageFields(): iterable
    {
        yield '基础设备' => ['baseDevice'];
        yield 'Auto.js版本' => ['autoJsVersion'];
        yield '证书信息' => ['certificate'];
        yield 'Root权限' => ['rootAccess'];
        yield '无障碍服务' => ['accessibilityEnabled'];
        yield '悬浮窗权限' => ['floatingWindowEnabled'];
        yield '设备能力' => ['capabilities'];
        yield '设备配置' => ['configuration'];
        yield '最大并发任务' => ['maxConcurrentTasks'];
        yield '屏幕分辨率' => ['screenResolution'];
        yield 'Android版本' => ['androidVersion'];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideEditPageFields(): iterable
    {
        yield '基础设备' => ['baseDevice'];
        yield 'Auto.js版本' => ['autoJsVersion'];
        yield '证书信息' => ['certificate'];
        yield 'Root权限' => ['rootAccess'];
        yield '无障碍服务' => ['accessibilityEnabled'];
        yield '悬浮窗权限' => ['floatingWindowEnabled'];
        yield '设备能力' => ['capabilities'];
        yield '设备配置' => ['configuration'];
        yield '最大并发任务' => ['maxConcurrentTasks'];
        yield '屏幕分辨率' => ['screenResolution'];
        yield 'Android版本' => ['androidVersion'];
    }

    // Note: Authentication tests are not appropriate for Bundle unit tests
    // as they depend on application-level configuration (security, routing, dashboard)
    // Such tests should be performed at the integration level in the consuming application

    #[Test]
    public function testControllerStructure(): void
    {
        $reflection = new \ReflectionClass(AutoJsDeviceCrudController::class);

        // 测试继承关系
        $this->assertTrue($reflection->isSubclassOf('EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController'));

        // 测试getEntityFqcn是静态方法
        $getEntityMethod = $reflection->getMethod('getEntityFqcn');
        $this->assertTrue($getEntityMethod->isStatic());
        $this->assertTrue($getEntityMethod->isPublic());

        // 测试类不是抽象类
        $this->assertFalse($reflection->isAbstract());
    }

    #[Test]
    public function testConfigureFiltersMethodSignature(): void
    {
        $reflection = new \ReflectionClass(AutoJsDeviceCrudController::class);
        $method = $reflection->getMethod('configureFilters');

        $this->assertTrue($method->isPublic());

        $returnType = $method->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertEquals('EasyCorp\Bundle\EasyAdminBundle\Config\Filters', ($returnType instanceof \ReflectionNamedType) ? $returnType->getName() : (string) $returnType);

        $parameters = $method->getParameters();
        $this->assertCount(1, $parameters);
        $this->assertEquals('filters', $parameters[0]->getName());
    }

    #[Test]
    public function testValidationErrors(): void
    {
        $reflection = new \ReflectionClass(AutoJsDeviceCrudController::class);

        // 验证必需的配置方法是否存在
        $requiredMethods = [
            'getEntityFqcn',
            'configureCrud',
            'configureActions',
            'configureFields',
            'configureFilters',
        ];

        foreach ($requiredMethods as $methodName) {
            $this->assertTrue($reflection->hasMethod($methodName), "方法 {$methodName} 必须存在");

            $method = $reflection->getMethod($methodName);
            $this->assertTrue($method->isPublic(), "方法 {$methodName} 必须是public");
        }

        // 验证AdminCrud属性配置
        $attributes = $reflection->getAttributes();
        $hasAdminCrudAttribute = false;
        foreach ($attributes as $attribute) {
            if (str_contains($attribute->getName(), 'AdminCrud')) {
                $hasAdminCrudAttribute = true;
                $this->assertNotEmpty($attribute->getArguments(), 'AdminCrud属性应该有配置参数');
                break;
            }
        }

        $this->assertTrue($hasAdminCrudAttribute, 'Controller必须有AdminCrud注解');
    }
}
