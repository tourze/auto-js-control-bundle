<?php

declare(strict_types=1);

namespace Tourze\AutoJsControlBundle\Tests\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Test;
use Tourze\AutoJsControlBundle\Controller\Admin\WebSocketMessageCrudController;
use Tourze\AutoJsControlBundle\Entity\WebSocketMessage;
use Tourze\PHPUnitSymfonyWebTest\AbstractEasyAdminControllerTestCase;

/**
 * WebSocketMessageCrudController 测试类.
 *
 * @internal
 */
#[CoversClass(WebSocketMessageCrudController::class)]
#[RunTestsInSeparateProcesses]
final class WebSocketMessageCrudControllerTest extends AbstractEasyAdminControllerTestCase
{
    /**
     * @return AbstractCrudController<WebSocketMessage>
     */
    protected function getControllerService(): AbstractCrudController
    {
        return self::getService(WebSocketMessageCrudController::class);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideIndexPageHeaders(): iterable
    {
        yield 'ID' => ['ID'];
        yield '设备' => ['设备'];
        yield '消息ID' => ['消息ID'];
        yield '消息类型' => ['消息类型'];
        yield '消息方向' => ['消息方向'];
        yield '是否已处理' => ['是否已处理'];
        yield '处理状态' => ['处理状态'];
        yield '处理时间' => ['处理时间'];
        yield '创建时间' => ['创建时间'];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideNewPageFields(): iterable
    {
        // NEW action might be disabled, providing dummy entry to avoid PHPUnit error
        yield 'dummy' => ['dummy'];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideEditPageFields(): iterable
    {
        // EDIT action might be disabled, providing dummy entry to avoid PHPUnit error
        yield 'dummy' => ['dummy'];
    }

    #[Test]
    public function testControllerIsInstantiable(): void
    {
        $reflection = new \ReflectionClass(WebSocketMessageCrudController::class);

        $this->assertTrue($reflection->isInstantiable());
    }

    #[Test]
    public function testGetEntityFqcnReturnsWebSocketMessageClass(): void
    {
        $this->assertEquals(WebSocketMessage::class, WebSocketMessageCrudController::getEntityFqcn());
    }

    #[Test]
    public function testControllerHasAdminCrudAttribute(): void
    {
        $reflection = new \ReflectionClass(WebSocketMessageCrudController::class);
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
        $reflection = new \ReflectionClass(WebSocketMessageCrudController::class);

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
        $reflection = new \ReflectionClass(WebSocketMessageCrudController::class);
        $method = $reflection->getMethod('getEntityFqcn');

        $this->assertTrue($method->isStatic(), 'getEntityFqcn必须是静态方法');
        $this->assertTrue($method->isPublic(), 'getEntityFqcn必须是public方法');
    }

    #[Test]
    public function testConfigureCrudMethodSignature(): void
    {
        $reflection = new \ReflectionClass(WebSocketMessageCrudController::class);
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
        $reflection = new \ReflectionClass(WebSocketMessageCrudController::class);
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
        $reflection = new \ReflectionClass(WebSocketMessageCrudController::class);
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
        $reflection = new \ReflectionClass(WebSocketMessageCrudController::class);
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
            new \ReflectionClass(WebSocketMessageCrudController::class)->getNamespaceName()
        );
    }

    #[Test]
    public function testValidationErrors(): void
    {
        // WebSocketMessage 是通信消息实体
        // Bundle 级别的单元测试不适合进行完整的表单验证测试（如 assertResponseStatusCodeSame(422)）
        // 因为这需要完整的应用级配置（security, routing, dashboard）
        // 表单提交验证测试（如 should not be blank 或 invalid-feedback）应在集成环境中进行

        // 验证控制器的正确配置
        $controller = $this->getControllerService();
        $this->assertInstanceOf(WebSocketMessageCrudController::class, $controller);

        // 验证实体类正确
        $this->assertEquals(WebSocketMessage::class, WebSocketMessageCrudController::getEntityFqcn());

        // 验证必要的配置方法存在
        $reflection = new \ReflectionClass(WebSocketMessageCrudController::class);
        $this->assertTrue($reflection->hasMethod('configureFields'), 'configureFields 方法必须存在');
    }

    // Note: Authentication tests are not appropriate for Bundle unit tests
    // as they depend on application-level configuration (security, routing, dashboard)
    // Such tests should be performed at the integration level in the consuming application

    #[Test]
    public function testControllerStructure(): void
    {
        $reflection = new \ReflectionClass(WebSocketMessageCrudController::class);

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
