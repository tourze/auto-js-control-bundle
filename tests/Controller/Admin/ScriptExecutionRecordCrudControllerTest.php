<?php

declare(strict_types=1);

namespace Tourze\AutoJsControlBundle\Tests\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Test;
use Tourze\AutoJsControlBundle\Controller\Admin\ScriptExecutionRecordCrudController;
use Tourze\AutoJsControlBundle\Entity\ScriptExecutionRecord;
use Tourze\PHPUnitSymfonyWebTest\AbstractEasyAdminControllerTestCase;

/**
 * ScriptExecutionRecordCrudController 测试类.
 *
 * @internal
 */
#[CoversClass(ScriptExecutionRecordCrudController::class)]
#[RunTestsInSeparateProcesses]
final class ScriptExecutionRecordCrudControllerTest extends AbstractEasyAdminControllerTestCase
{
    /**
     * @return AbstractCrudController<ScriptExecutionRecord>
     */
    protected function getControllerService(): AbstractCrudController
    {
        return self::getService(ScriptExecutionRecordCrudController::class);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideIndexPageHeaders(): iterable
    {
        yield '脚本' => ['脚本'];
        yield '任务' => ['任务'];
        yield '执行设备' => ['执行设备'];
        yield '执行状态' => ['执行状态'];
        yield '退出代码' => ['退出代码'];
        yield '重试次数' => ['重试次数'];
        yield '开始时间' => ['开始时间'];
        yield '执行时长' => ['执行时长'];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideNewPageFields(): iterable
    {
        // Execution records are typically created automatically
        yield 'dummy' => ['dummy'];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideEditPageFields(): iterable
    {
        // Execution records are typically read-only
        yield 'dummy' => ['dummy'];
    }

    #[Test]
    public function testControllerIsInstantiable(): void
    {
        $reflection = new \ReflectionClass(ScriptExecutionRecordCrudController::class);

        $this->assertTrue($reflection->isInstantiable());
    }

    #[Test]
    public function testGetEntityFqcnReturnsScriptExecutionRecordClass(): void
    {
        $this->assertEquals(ScriptExecutionRecord::class, ScriptExecutionRecordCrudController::getEntityFqcn());
    }

    #[Test]
    public function testControllerHasAdminCrudAttribute(): void
    {
        $reflection = new \ReflectionClass(ScriptExecutionRecordCrudController::class);
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
        $reflection = new \ReflectionClass(ScriptExecutionRecordCrudController::class);

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
        $reflection = new \ReflectionClass(ScriptExecutionRecordCrudController::class);
        $method = $reflection->getMethod('getEntityFqcn');

        $this->assertTrue($method->isStatic(), 'getEntityFqcn必须是静态方法');
        $this->assertTrue($method->isPublic(), 'getEntityFqcn必须是public方法');
    }

    #[Test]
    public function testConfigureCrudMethodSignature(): void
    {
        $reflection = new \ReflectionClass(ScriptExecutionRecordCrudController::class);
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
        $reflection = new \ReflectionClass(ScriptExecutionRecordCrudController::class);
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
        $reflection = new \ReflectionClass(ScriptExecutionRecordCrudController::class);
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
    public function testControllerHasPrivateHelperMethods(): void
    {
        $reflection = new \ReflectionClass(ScriptExecutionRecordCrudController::class);

        $helperMethods = [
            'formatExecutionDuration',
            'formatScriptSuccessRate',
        ];

        foreach ($helperMethods as $methodName) {
            $this->assertTrue($reflection->hasMethod($methodName), "Helper方法 {$methodName} 必须存在");

            $method = $reflection->getMethod($methodName);
            $this->assertTrue($method->isPrivate(), "Helper方法 {$methodName} 必须是private");
        }
    }

    #[Test]
    public function testControllerHasCorrectNamespace(): void
    {
        $this->assertEquals(
            'Tourze\AutoJsControlBundle\Controller\Admin',
            new \ReflectionClass(ScriptExecutionRecordCrudController::class)->getNamespaceName()
        );
    }

    #[Test]
    public function testControllerRequiresAuthentication(): void
    {
        $client = self::createClient();

        // 尝试访问脚本执行记录页面，应该被重定向到登录
        $client->request('GET', '/admin/script-execution-record');

        // 检查是否被重定向
        $response = $client->getResponse();
        $this->assertEquals(302, $response->getStatusCode());

        $location = $response->headers->get('Location');
        $this->assertNotNull($location);
        $this->assertStringContainsString('/login', $location);
    }

    #[Test]
    public function testControllerStructure(): void
    {
        $reflection = new \ReflectionClass(ScriptExecutionRecordCrudController::class);

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
    public function testControllerSupportsCrudOperations(): void
    {
        $reflection = new \ReflectionClass(ScriptExecutionRecordCrudController::class);

        // 验证所有必需的CRUD配置方法都存在
        $crudMethods = [
            'configureCrud' => 'EasyCorp\Bundle\EasyAdminBundle\Config\Crud',
            'configureActions' => 'EasyCorp\Bundle\EasyAdminBundle\Config\Actions',
            'configureFields' => 'iterable',
        ];

        foreach ($crudMethods as $methodName => $expectedReturnType) {
            $this->assertTrue($reflection->hasMethod($methodName), "CRUD方法 {$methodName} 必须存在");

            $method = $reflection->getMethod($methodName);
            $this->assertTrue($method->isPublic(), "CRUD方法 {$methodName} 必须是public");

            $returnType = $method->getReturnType();
            $this->assertNotNull($returnType, "CRUD方法 {$methodName} 必须有返回类型");
            $this->assertEquals(
                $expectedReturnType,
                ($returnType instanceof \ReflectionNamedType) ? $returnType->getName() : (string) $returnType,
                "CRUD方法 {$methodName} 返回类型应该是 {$expectedReturnType}"
            );
        }
    }

    #[Test]
    public function testControllerUsesExecutionStatusEnum(): void
    {
        $reflection = new \ReflectionClass(ScriptExecutionRecordCrudController::class);

        // 验证控制器包含了适当的用法，通过检查类内容间接验证
        $fileName = $reflection->getFileName();
        $this->assertNotFalse($fileName, '无法获取控制器文件名');
        $source = file_get_contents($fileName);
        $this->assertNotFalse($source, '无法读取控制器源文件');

        // 验证使用了ExecutionStatus枚举
        $this->assertStringContainsString(
            'ExecutionStatus::cases()',
            $source,
            'ScriptExecutionRecordCrudController应该使用ExecutionStatus枚举'
        );
    }

    #[Test]
    public function testControllerFormatExecutionDurationMethodSignature(): void
    {
        $reflection = new \ReflectionClass(ScriptExecutionRecordCrudController::class);
        $method = $reflection->getMethod('formatExecutionDuration');

        $this->assertTrue($method->isPrivate());

        $returnType = $method->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertEquals('string', ($returnType instanceof \ReflectionNamedType) ? $returnType->getName() : (string) $returnType);

        $parameters = $method->getParameters();
        $this->assertCount(1, $parameters);
        $this->assertEquals('entity', $parameters[0]->getName());
    }

    #[Test]
    public function testControllerFormatScriptSuccessRateMethodSignature(): void
    {
        $reflection = new \ReflectionClass(ScriptExecutionRecordCrudController::class);
        $method = $reflection->getMethod('formatScriptSuccessRate');

        $this->assertTrue($method->isPrivate());

        $returnType = $method->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertEquals('string', ($returnType instanceof \ReflectionNamedType) ? $returnType->getName() : (string) $returnType);

        $parameters = $method->getParameters();
        $this->assertCount(1, $parameters);
        $this->assertEquals('entity', $parameters[0]->getName());
    }
}
