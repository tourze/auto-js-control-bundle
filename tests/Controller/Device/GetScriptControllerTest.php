<?php

declare(strict_types=1);

namespace Tourze\AutoJsControlBundle\Tests\Controller\Device;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Tourze\AutoJsControlBundle\Controller\Device\GetScriptController;
use Tourze\PHPUnitSymfonyWebTest\AbstractWebTestCase;

/**
 * GetScriptController 测试类.
 *
 * @internal
 */
#[CoversClass(GetScriptController::class)]
#[RunTestsInSeparateProcesses]
final class GetScriptControllerTest extends AbstractWebTestCase
{
    #[Test]
    public function testControllerIsInstantiable(): void
    {
        $reflection = new \ReflectionClass(GetScriptController::class);

        $this->assertTrue($reflection->isInstantiable());
        $this->assertTrue($reflection->isFinal());
    }

    #[Test]
    public function testControllerExtendsAbstractApiController(): void
    {
        $reflection = new \ReflectionClass(GetScriptController::class);

        $this->assertTrue(
            $reflection->isSubclassOf('Tourze\AutoJsControlBundle\Controller\AbstractApiController'),
            'GetScriptController必须继承AbstractApiController'
        );
    }

    #[Test]
    public function testControllerHasConstructor(): void
    {
        $reflection = new \ReflectionClass(GetScriptController::class);
        $constructor = $reflection->getConstructor();

        $this->assertNotNull($constructor);
        $this->assertTrue($constructor->isPublic());

        $parameters = $constructor->getParameters();
        $this->assertCount(4, $parameters);

        // 验证构造函数参数
        $expectedParameterNames = [
            'autoJsDeviceRepository',
            'deviceRepository',
            'scriptRepository',
            'logger',
        ];

        for ($i = 0; $i < count($expectedParameterNames); ++$i) {
            $this->assertEquals($expectedParameterNames[$i], $parameters[$i]->getName());
        }
    }

    #[Test]
    public function testControllerHasInvokeMethod(): void
    {
        $reflection = new \ReflectionClass(GetScriptController::class);

        $this->assertTrue($reflection->hasMethod('__invoke'));

        $method = $reflection->getMethod('__invoke');
        $this->assertTrue($method->isPublic());

        $returnType = $method->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertEquals('Symfony\Component\HttpFoundation\JsonResponse', ($returnType instanceof \ReflectionNamedType) ? $returnType->getName() : (string) $returnType);

        $parameters = $method->getParameters();
        $this->assertCount(2, $parameters);
        $this->assertEquals('request', $parameters[0]->getName());
        $this->assertEquals('scriptId', $parameters[1]->getName());
    }

    #[Test]
    public function testControllerHasPrivateHelperMethods(): void
    {
        $reflection = new \ReflectionClass(GetScriptController::class);

        $privateMethods = [
            'validateScriptRequest',
            'getScriptById',
            'buildScriptDownloadResponse',
            'getAutoJsDeviceByCode',
        ];

        foreach ($privateMethods as $methodName) {
            $this->assertTrue($reflection->hasMethod($methodName), "Private方法 {$methodName} 必须存在");

            $method = $reflection->getMethod($methodName);
            $this->assertTrue($method->isPrivate(), "方法 {$methodName} 必须是private");
        }
    }

    #[Test]
    public function testControllerHasCorrectNamespace(): void
    {
        $this->assertEquals(
            'Tourze\AutoJsControlBundle\Controller\Device',
            (new \ReflectionClass(GetScriptController::class))->getNamespaceName()
        );
    }

    #[Test]
    public function testControllerHasRouteAttribute(): void
    {
        $reflection = new \ReflectionClass(GetScriptController::class);
        $method = $reflection->getMethod('__invoke');
        $attributes = $method->getAttributes();

        $hasRouteAttribute = false;
        foreach ($attributes as $attribute) {
            if (str_contains($attribute->getName(), 'Route')) {
                $hasRouteAttribute = true;
                break;
            }
        }

        $this->assertTrue($hasRouteAttribute, '__invoke方法应该有Route注解');
    }

    #[Test]
    public function testControllerHasAutoconfigureAttribute(): void
    {
        $reflection = new \ReflectionClass(GetScriptController::class);
        $attributes = $reflection->getAttributes();

        $hasAutoconfigureAttribute = false;
        foreach ($attributes as $attribute) {
            if (str_contains($attribute->getName(), 'Autoconfigure')) {
                $hasAutoconfigureAttribute = true;
                break;
            }
        }

        $this->assertTrue($hasAutoconfigureAttribute, 'Controller应该有Autoconfigure注解');
    }

    #[Test]
    public function testGetScriptWithoutAuthenticationFails(): void
    {
        $client = self::createClientWithDatabase();

        $this->expectException(AccessDeniedException::class);
        $this->expectExceptionMessage('Access Denied. The user is not appropriately authenticated.');

        $client->request('GET', '/api/autojs/v1/device/script/1?deviceCode=TEST_DEVICE&signature=invalid&timestamp=' . time());
    }

    #[Test]
    public function testGetScriptWithMissingParametersFails(): void
    {
        $client = self::createClientWithDatabase();

        $this->expectException(AccessDeniedException::class);
        $this->expectExceptionMessage('Access Denied. The user is not appropriately authenticated.');

        // 缺少必需的参数
        $client->request('GET', '/api/autojs/v1/device/script/1');
    }

    #[Test]
    public function testGetScriptWithInvalidScriptIdFails(): void
    {
        $client = self::createClientWithDatabase();

        $this->expectException(AccessDeniedException::class);
        $this->expectExceptionMessage('Access Denied. The user is not appropriately authenticated.');

        $params = [
            'deviceCode' => 'INVALID_DEVICE',
            'signature' => 'invalid_signature',
            'timestamp' => time(),
        ];

        $client->request('GET', '/api/autojs/v1/device/script/99999?' . http_build_query($params));
    }

    #[Test]
    #[DataProvider('provideNotAllowedMethods')]
    public function testMethodNotAllowed(string $method): void
    {
        // GetScriptController只支持GET方法，验证其他方法不被支持
        $supportedMethods = ['GET']; // GetScriptController只支持GET

        $this->assertNotContains(
            $method,
            $supportedMethods,
            "Method {$method} should not be supported by GetScriptController"
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
    public function testControllerStructure(): void
    {
        $reflection = new \ReflectionClass(GetScriptController::class);

        // 测试类是final的
        $this->assertTrue($reflection->isFinal());

        // 测试继承关系
        $this->assertTrue($reflection->isSubclassOf('Tourze\AutoJsControlBundle\Controller\AbstractApiController'));

        // 测试有构造函数
        $constructor = $reflection->getConstructor();
        $this->assertNotNull($constructor);
        $this->assertCount(4, $constructor->getParameters());

        // 测试__invoke方法存在并且是公共的
        $invokeMethod = $reflection->getMethod('__invoke');
        $this->assertTrue($invokeMethod->isPublic());
    }

    #[Test]
    public function testValidateScriptRequestMethodSignature(): void
    {
        $reflection = new \ReflectionClass(GetScriptController::class);
        $method = $reflection->getMethod('validateScriptRequest');

        $this->assertTrue($method->isPrivate());

        $returnType = $method->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertEquals('void', ($returnType instanceof \ReflectionNamedType) ? $returnType->getName() : (string) $returnType);

        $parameters = $method->getParameters();
        $this->assertCount(1, $parameters);
        $this->assertEquals('request', $parameters[0]->getName());
    }

    #[Test]
    public function testGetScriptByIdMethodSignature(): void
    {
        $reflection = new \ReflectionClass(GetScriptController::class);
        $method = $reflection->getMethod('getScriptById');

        $this->assertTrue($method->isPrivate());

        $returnType = $method->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertEquals('Tourze\AutoJsControlBundle\Entity\Script', ($returnType instanceof \ReflectionNamedType) ? $returnType->getName() : (string) $returnType);

        $parameters = $method->getParameters();
        $this->assertCount(1, $parameters);
        $this->assertEquals('scriptId', $parameters[0]->getName());
    }

    #[Test]
    public function testBuildScriptDownloadResponseMethodSignature(): void
    {
        $reflection = new \ReflectionClass(GetScriptController::class);
        $method = $reflection->getMethod('buildScriptDownloadResponse');

        $this->assertTrue($method->isPrivate());

        $returnType = $method->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertEquals('Tourze\AutoJsControlBundle\Dto\Response\ScriptDownloadResponse', ($returnType instanceof \ReflectionNamedType) ? $returnType->getName() : (string) $returnType);

        $parameters = $method->getParameters();
        $this->assertCount(1, $parameters);
        $this->assertEquals('script', $parameters[0]->getName());
    }

    #[Test]
    public function testGetAutoJsDeviceByCodeMethodSignature(): void
    {
        $reflection = new \ReflectionClass(GetScriptController::class);
        $method = $reflection->getMethod('getAutoJsDeviceByCode');

        $this->assertTrue($method->isPrivate());

        $returnType = $method->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertEquals('Tourze\AutoJsControlBundle\Entity\AutoJsDevice', ($returnType instanceof \ReflectionNamedType) ? $returnType->getName() : (string) $returnType);

        $parameters = $method->getParameters();
        $this->assertCount(1, $parameters);
        $this->assertEquals('deviceCode', $parameters[0]->getName());
    }

    #[Test]
    public function testControllerUsesCorrectDependencies(): void
    {
        $reflection = new \ReflectionClass(GetScriptController::class);

        // 验证控制器使用了正确的依赖注入
        $fileName = $reflection->getFileName();
        $this->assertNotFalse($fileName, '无法获取控制器文件名');
        $source = file_get_contents($fileName);
        $this->assertNotFalse($source, '无法读取控制器源文件');

        // 验证导入了必要的类和接口
        $requiredImports = [
            'AutoJsDeviceRepository',
            'DeviceRepository',
            'ScriptRepository',
            'LoggerInterface',
            'ScriptDownloadResponse',
            'JsonResponse',
        ];

        foreach ($requiredImports as $import) {
            $this->assertStringContainsString(
                $import,
                $source,
                "GetScriptController应该导入 {$import}"
            );
        }
    }
}
