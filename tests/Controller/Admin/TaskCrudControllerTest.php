<?php

declare(strict_types=1);

namespace Tourze\AutoJsControlBundle\Tests\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Test;
use Tourze\AutoJsControlBundle\Controller\Admin\TaskCrudController;
use Tourze\AutoJsControlBundle\Entity\Task;
use Tourze\PHPUnitSymfonyWebTest\AbstractEasyAdminControllerTestCase;

/**
 * TaskCrudController 测试类.
 *
 * @internal
 */
#[CoversClass(TaskCrudController::class)]
#[RunTestsInSeparateProcesses]
final class TaskCrudControllerTest extends AbstractEasyAdminControllerTestCase
{
    /**
     * @return AbstractCrudController<Task>
     */
    protected function getControllerService(): AbstractCrudController
    {
        return self::getService(TaskCrudController::class);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideIndexPageHeaders(): iterable
    {
        yield '任务名称' => ['任务名称'];
        yield '任务类型' => ['任务类型'];
        yield '任务状态' => ['任务状态'];
        yield '目标类型' => ['目标类型'];
        yield '关联脚本' => ['关联脚本'];
        yield '优先级' => ['优先级'];
        yield '最大重试次数' => ['最大重试次数'];
        yield '超时时间(秒)' => ['超时时间(秒)'];
        yield '是否启用' => ['是否启用'];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideNewPageFields(): iterable
    {
        yield 'name' => ['name'];
        yield 'description' => ['description'];
        yield 'script' => ['script'];
        yield 'targetGroup' => ['targetGroup'];
        yield 'priority' => ['priority'];
        yield 'maxRetries' => ['maxRetries'];
        yield 'timeout' => ['timeout'];
        yield 'enabled' => ['enabled'];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideEditPageFields(): iterable
    {
        yield 'name' => ['name'];
        yield 'description' => ['description'];
        yield 'script' => ['script'];
        yield 'targetGroup' => ['targetGroup'];
        yield 'priority' => ['priority'];
        yield 'maxRetries' => ['maxRetries'];
        yield 'timeout' => ['timeout'];
        yield 'enabled' => ['enabled'];
    }

    #[Test]
    public function testControllerIsInstantiable(): void
    {
        $reflection = new \ReflectionClass(TaskCrudController::class);

        $this->assertTrue($reflection->isInstantiable());
    }

    #[Test]
    public function testGetEntityFqcnReturnsTaskClass(): void
    {
        $this->assertEquals(Task::class, TaskCrudController::getEntityFqcn());
    }

    #[Test]
    public function testControllerHasAdminCrudAttribute(): void
    {
        $reflection = new \ReflectionClass(TaskCrudController::class);
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
        $reflection = new \ReflectionClass(TaskCrudController::class);

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
        $reflection = new \ReflectionClass(TaskCrudController::class);
        $method = $reflection->getMethod('getEntityFqcn');

        $this->assertTrue($method->isStatic(), 'getEntityFqcn必须是静态方法');
        $this->assertTrue($method->isPublic(), 'getEntityFqcn必须是public方法');
    }

    #[Test]
    public function testConfigureCrudMethodSignature(): void
    {
        $reflection = new \ReflectionClass(TaskCrudController::class);
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
        $reflection = new \ReflectionClass(TaskCrudController::class);
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
        $reflection = new \ReflectionClass(TaskCrudController::class);
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
            new \ReflectionClass(TaskCrudController::class)->getNamespaceName()
        );
    }

    #[Test]
    public function testControllerStructure(): void
    {
        $reflection = new \ReflectionClass(TaskCrudController::class);

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
        $reflection = new \ReflectionClass(TaskCrudController::class);

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
    public function testControllerUsesTaskEnums(): void
    {
        $reflection = new \ReflectionClass(TaskCrudController::class);

        // 验证控制器包含了适当的用法，通过检查类内容间接验证
        $fileName = $reflection->getFileName();
        $this->assertNotFalse($fileName, '无法获取控制器文件名');
        $source = file_get_contents($fileName);
        $this->assertNotFalse($source, '无法读取控制器源文件');

        // 验证使用了任务相关的枚举
        $this->assertStringContainsString(
            'TaskType::cases()',
            $source,
            'TaskCrudController应该使用TaskType枚举'
        );
        $this->assertStringContainsString(
            'TaskStatus::cases()',
            $source,
            'TaskCrudController应该使用TaskStatus枚举'
        );
        $this->assertStringContainsString(
            'TaskTargetType::cases()',
            $source,
            'TaskCrudController应该使用TaskTargetType枚举'
        );
    }

    #[Test]
    public function testControllerHasVirtualFieldsForStatistics(): void
    {
        $reflection = new \ReflectionClass(TaskCrudController::class);

        // 验证控制器包含了统计相关的虚拟字段
        $fileName = $reflection->getFileName();
        $this->assertNotFalse($fileName, '无法获取控制器文件名');
        $source = file_get_contents($fileName);
        $this->assertNotFalse($source, '无法读取控制器源文件');

        // 验证包含了执行进度和执行时长的计算
        $this->assertStringContainsString(
            'executionProgress',
            $source,
            'TaskCrudController应该包含executionProgress虚拟字段'
        );
        $this->assertStringContainsString(
            'executionDuration',
            $source,
            'TaskCrudController应该包含executionDuration虚拟字段'
        );
        $this->assertStringContainsString(
            'executionRecordsCount',
            $source,
            'TaskCrudController应该包含executionRecordsCount虚拟字段'
        );
    }

    #[Test]
    public function testControllerSupportsTaskScheduling(): void
    {
        $reflection = new \ReflectionClass(TaskCrudController::class);

        // 验证控制器支持任务调度相关字段
        $fileName = $reflection->getFileName();
        $this->assertNotFalse($fileName, '无法获取控制器文件名');
        $source = file_get_contents($fileName);
        $this->assertNotFalse($source, '无法读取控制器源文件');

        // 验证包含了调度相关字段
        $this->assertStringContainsString(
            'scheduledTime',
            $source,
            'TaskCrudController应该支持scheduledTime字段'
        );
        $this->assertStringContainsString(
            'cronExpression',
            $source,
            'TaskCrudController应该支持cronExpression字段'
        );
        $this->assertStringContainsString(
            'nextExecutionTime',
            $source,
            'TaskCrudController应该支持nextExecutionTime字段'
        );
    }
}
