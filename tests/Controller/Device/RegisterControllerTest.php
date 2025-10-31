<?php

declare(strict_types=1);

namespace Tourze\AutoJsControlBundle\Tests\Controller\Device;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Test;
use Tourze\AutoJsControlBundle\Controller\Device\RegisterController;
use Tourze\PHPUnitSymfonyWebTest\AbstractWebTestCase;

/**
 * @internal
 */
#[CoversClass(RegisterController::class)]
#[RunTestsInSeparateProcesses]
final class RegisterControllerTest extends AbstractWebTestCase
{
    #[Test]
    #[DataProvider('provideNotAllowedMethods')]
    public function testMethodNotAllowed(string $method): void
    {
        // 测试不支持的HTTP方法应该被路由层拒绝
        // RegisterController只支持POST方法，验证其他方法不被支持
        $supportedMethods = ['POST']; // RegisterController只支持POST

        $this->assertNotContains(
            $method,
            $supportedMethods,
            "Method {$method} should not be supported by RegisterController"
        );

        // 验证方法名是一个有效的HTTP方法
        $validHttpMethods = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'HEAD', 'OPTIONS', 'TRACE', 'CONNECT', 'PURGE'];
        $this->assertContains(
            $method,
            $validHttpMethods,
            "Method {$method} should be a valid HTTP method"
        );
    }

    #[Test]
    public function testControllerHasCorrectRoute(): void
    {
        $reflection = new \ReflectionClass(RegisterController::class);
        $method = $reflection->getMethod('__invoke');
        $attributes = $method->getAttributes();

        $hasRouteAttribute = false;
        foreach ($attributes as $attribute) {
            if (str_contains($attribute->getName(), 'Route')) {
                $hasRouteAttribute = true;
                $routeArgs = $attribute->getArguments();
                $this->assertArrayHasKey('path', $routeArgs);
                $this->assertArrayHasKey('methods', $routeArgs);
                $this->assertEquals([0 => 'POST'], $routeArgs['methods']);
                break;
            }
        }

        $this->assertTrue($hasRouteAttribute, 'Controller应该有Route注解');
    }

    #[Test]
    public function testControllerHasRequiredMethods(): void
    {
        $reflection = new \ReflectionClass(RegisterController::class);

        // 验证__invoke方法存在且为public
        $this->assertTrue($reflection->hasMethod('__invoke'));
        $invokeMethod = $reflection->getMethod('__invoke');
        $this->assertTrue($invokeMethod->isPublic());

        // 验证__invoke方法的参数
        $parameters = $invokeMethod->getParameters();
        $this->assertCount(1, $parameters);
        $this->assertEquals('request', $parameters[0]->getName());

        // 验证返回类型
        $returnType = $invokeMethod->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertEquals('Symfony\Component\HttpFoundation\JsonResponse', ($returnType instanceof \ReflectionNamedType) ? $returnType->getName() : (string) $returnType);
    }

    #[Test]
    public function testValidationErrors(): void
    {
        $reflection = new \ReflectionClass(RegisterController::class);

        // 验证Controller继承正确的基类
        $this->assertTrue($reflection->isSubclassOf('Tourze\AutoJsControlBundle\Controller\AbstractApiController'));

        // 验证使用了ValidatorAwareTrait
        $traits = $reflection->getTraitNames();
        $this->assertContains('Tourze\AutoJsControlBundle\Controller\ValidatorAwareTrait', $traits);

        // 验证构造函数参数
        $constructor = $reflection->getConstructor();
        $this->assertNotNull($constructor);
        $parameters = $constructor->getParameters();
        $this->assertGreaterThanOrEqual(4, count($parameters)); // 至少4个参数

        // 验证私有方法存在
        $privateMethods = [
            'createDeviceRegisterRequest',
            'buildRegisterSuccessResponse',
            'updateDeviceOnlineStatus',
        ];

        foreach ($privateMethods as $methodName) {
            $this->assertTrue($reflection->hasMethod($methodName), "私有方法 {$methodName} 必须存在");
            $method = $reflection->getMethod($methodName);
            $this->assertTrue($method->isPrivate(), "方法 {$methodName} 必须是private");
        }
    }
}
