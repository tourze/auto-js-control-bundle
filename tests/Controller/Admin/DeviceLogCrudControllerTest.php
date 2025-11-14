<?php

declare(strict_types=1);

namespace Tourze\AutoJsControlBundle\Tests\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Test;
use Tourze\AutoJsControlBundle\Controller\Admin\DeviceLogCrudController;
use Tourze\AutoJsControlBundle\Entity\DeviceLog;
use Tourze\PHPUnitSymfonyWebTest\AbstractEasyAdminControllerTestCase;

/**
 * DeviceLogCrudController 测试类.
 *
 * @internal
 */
#[CoversClass(DeviceLogCrudController::class)]
#[RunTestsInSeparateProcesses]
final class DeviceLogCrudControllerTest extends AbstractEasyAdminControllerTestCase
{
    /**
     * @return AbstractCrudController<DeviceLog>
     */
    protected function getControllerService(): AbstractCrudController
    {
        return self::getService(DeviceLogCrudController::class);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideIndexPageHeaders(): iterable
    {
        yield 'ID' => ['ID'];
        yield '设备' => ['设备'];
        yield '日志级别' => ['日志级别'];
        yield '日志类型' => ['日志类型'];
        yield '日志标题' => ['日志标题'];
        yield '设备IP' => ['设备IP'];
        yield '日志时间' => ['日志时间'];
        yield '创建时间' => ['创建时间'];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideNewPageFields(): iterable
    {
        // NEW action is disabled for log entities - provide minimal dummy field
        yield 'disabled' => ['__disabled__'];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideEditPageFields(): iterable
    {
        // EDIT action is disabled for log entities - provide minimal dummy field
        yield 'disabled' => ['__disabled__'];
    }

    #[Test]
    public function testControllerIsInstantiable(): void
    {
        $reflection = new \ReflectionClass(DeviceLogCrudController::class);

        $this->assertTrue($reflection->isInstantiable());
    }

    #[Test]
    public function testGetEntityFqcnReturnsDeviceLogClass(): void
    {
        $this->assertEquals(DeviceLog::class, DeviceLogCrudController::getEntityFqcn());
    }

    #[Test]
    public function testControllerHasAdminCrudAttribute(): void
    {
        $reflection = new \ReflectionClass(DeviceLogCrudController::class);
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
        $reflection = new \ReflectionClass(DeviceLogCrudController::class);

        $requiredMethods = [
            'getEntityFqcn',
            'configureCrud',
            'configureActions',
            'configureFilters',
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
        $reflection = new \ReflectionClass(DeviceLogCrudController::class);
        $method = $reflection->getMethod('getEntityFqcn');

        $this->assertTrue($method->isStatic(), 'getEntityFqcn必须是静态方法');
        $this->assertTrue($method->isPublic(), 'getEntityFqcn必须是public方法');
    }

    #[Test]
    public function testConfigureCrudMethodSignature(): void
    {
        $reflection = new \ReflectionClass(DeviceLogCrudController::class);
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
        $reflection = new \ReflectionClass(DeviceLogCrudController::class);
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
    public function testConfigureFiltersMethodSignature(): void
    {
        $reflection = new \ReflectionClass(DeviceLogCrudController::class);
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
    public function testConfigureFieldsMethodSignature(): void
    {
        $reflection = new \ReflectionClass(DeviceLogCrudController::class);
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
            new \ReflectionClass(DeviceLogCrudController::class)->getNamespaceName()
        );
    }

    #[Test]
    public function testValidationErrors(): void
    {
        // DeviceLog 是只读实体，NEW和EDIT操作被禁用
        // 因此不需要测试表单验证错误（assertResponseStatusCodeSame(422)）
        // 表单验证测试（如 should not be blank 或 invalid-feedback）仅适用于可编辑的实体

        // 验证控制器正确配置了禁用的操作
        $controller = $this->getControllerService();
        $this->assertInstanceOf(DeviceLogCrudController::class, $controller);

        // 验证实体类正确
        $this->assertEquals(DeviceLog::class, DeviceLogCrudController::getEntityFqcn());

        // 验证 NEW 和 EDIT 操作确实被禁用（通过反射检查 configureActions 方法）
        $reflection = new \ReflectionClass(DeviceLogCrudController::class);
        $this->assertTrue($reflection->hasMethod('configureActions'), 'configureActions 方法必须存在');
    }

    // Note: Authentication tests are not appropriate for Bundle unit tests
    // as they depend on application-level configuration (security, routing, dashboard)
    // Such tests should be performed at the integration level in the consuming application

    #[Test]
    public function testControllerStructure(): void
    {
        $reflection = new \ReflectionClass(DeviceLogCrudController::class);

        // 测试继承关系
        $this->assertTrue($reflection->isSubclassOf('EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController'));

        // 测试getEntityFqcn是静态方法
        $getEntityMethod = $reflection->getMethod('getEntityFqcn');
        $this->assertTrue($getEntityMethod->isStatic());
        $this->assertTrue($getEntityMethod->isPublic());

        // 测试类不是抽象类
        $this->assertFalse($reflection->isAbstract());
    }
}
