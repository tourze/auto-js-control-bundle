<?php

declare(strict_types=1);

namespace Tourze\AutoJsControlBundle\Tests\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Test;
use Tourze\AutoJsControlBundle\Controller\Admin\ScriptCrudController;
use Tourze\AutoJsControlBundle\Entity\Script;
use Tourze\PHPUnitSymfonyWebTest\AbstractEasyAdminControllerTestCase;

/**
 * ScriptCrudController 测试类.
 *
 * @internal
 */
#[CoversClass(ScriptCrudController::class)]
#[RunTestsInSeparateProcesses]
final class ScriptCrudControllerTest extends AbstractEasyAdminControllerTestCase
{
    /**
     * @return AbstractCrudController<Script>
     */
    protected function getControllerService(): AbstractCrudController
    {
        return self::getService(ScriptCrudController::class);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideIndexPageHeaders(): iterable
    {
        yield '脚本编码' => ['脚本编码'];
        yield '脚本名称' => ['脚本名称'];
        yield '脚本类型' => ['脚本类型'];
        yield '脚本状态' => ['脚本状态'];
        yield '版本号' => ['版本号'];
        yield '优先级' => ['优先级'];
        yield '超时时间(秒)' => ['超时时间(秒)'];
        yield '最大重试次数' => ['最大重试次数'];
        yield '是否启用' => ['是否启用'];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideNewPageFields(): iterable
    {
        yield 'code' => ['code'];
        yield 'name' => ['name'];
        yield 'description' => ['description'];
        yield 'version' => ['version'];
        yield 'priority' => ['priority'];
        yield 'timeout' => ['timeout'];
        yield 'maxRetries' => ['maxRetries'];
        yield 'valid' => ['valid'];
        yield 'projectPath' => ['projectPath'];
        yield 'content' => ['content'];
        yield 'parameters' => ['parameters'];
        yield 'securityRules' => ['securityRules'];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideEditPageFields(): iterable
    {
        yield 'code' => ['code'];
        yield 'name' => ['name'];
        yield 'description' => ['description'];
        yield 'version' => ['version'];
        yield 'priority' => ['priority'];
        yield 'timeout' => ['timeout'];
        yield 'maxRetries' => ['maxRetries'];
        yield 'valid' => ['valid'];
        yield 'projectPath' => ['projectPath'];
        yield 'content' => ['content'];
        yield 'parameters' => ['parameters'];
        yield 'securityRules' => ['securityRules'];
    }

    #[Test]
    public function testControllerIsInstantiable(): void
    {
        $reflection = new \ReflectionClass(ScriptCrudController::class);

        $this->assertTrue($reflection->isInstantiable());
    }

    #[Test]
    public function testGetEntityFqcnReturnsScriptClass(): void
    {
        $this->assertEquals(Script::class, ScriptCrudController::getEntityFqcn());
    }

    #[Test]
    public function testControllerHasAdminCrudAttribute(): void
    {
        $reflection = new \ReflectionClass(ScriptCrudController::class);
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
        $reflection = new \ReflectionClass(ScriptCrudController::class);

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
        $reflection = new \ReflectionClass(ScriptCrudController::class);
        $method = $reflection->getMethod('getEntityFqcn');

        $this->assertTrue($method->isStatic(), 'getEntityFqcn必须是静态方法');
        $this->assertTrue($method->isPublic(), 'getEntityFqcn必须是public方法');
    }

    #[Test]
    public function testConfigureCrudMethodSignature(): void
    {
        $reflection = new \ReflectionClass(ScriptCrudController::class);
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
        $reflection = new \ReflectionClass(ScriptCrudController::class);
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
        $reflection = new \ReflectionClass(ScriptCrudController::class);
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
            (new \ReflectionClass(ScriptCrudController::class))->getNamespaceName()
        );
    }

    #[Test]
    public function testControllerRequiresAuthentication(): void
    {
        $client = self::createClient();

        // 尝试访问脚本管理页面，应该被重定向到登录
        $client->request('GET', '/admin/script');

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
        $reflection = new \ReflectionClass(ScriptCrudController::class);

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
        $reflection = new \ReflectionClass(ScriptCrudController::class);

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
    public function testControllerUsesEnumFields(): void
    {
        $reflection = new \ReflectionClass(ScriptCrudController::class);

        // 验证控制器包含了适当的用法，通过检查类内容间接验证
        $fileName = $reflection->getFileName();
        $this->assertNotFalse($fileName, '无法获取控制器文件名');
        $source = file_get_contents($fileName);
        $this->assertNotFalse($source, '无法读取控制器源文件');

        // 验证使用了ScriptType和ScriptStatus枚举
        $this->assertStringContainsString(
            'ScriptType::cases()',
            $source,
            'ScriptCrudController应该使用ScriptType枚举'
        );
        $this->assertStringContainsString(
            'ScriptStatus::cases()',
            $source,
            'ScriptCrudController应该使用ScriptStatus枚举'
        );
    }
}
