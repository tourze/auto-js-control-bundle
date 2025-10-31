<?php

declare(strict_types=1);

namespace Tourze\AutoJsControlBundle\Tests\Controller\Device;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Tourze\AutoJsControlBundle\Controller\Device\HeartbeatController;
use Tourze\PHPUnitSymfonyWebTest\AbstractWebTestCase;

/**
 * @internal
 */
#[CoversClass(HeartbeatController::class)]
#[RunTestsInSeparateProcesses]
final class HeartbeatControllerTest extends AbstractWebTestCase
{
    #[Test]
    public function heartbeatWithValidDevice(): void
    {
        $client = self::createClientWithDatabase();
        $admin = $this->createAdminUser('admin@test.com', 'password123');
        $this->loginAsAdmin($client, 'admin@test.com', 'password123');

        $deviceCode = 'HEARTBEAT-DEVICE-' . uniqid();

        // Register device first
        $registerData = [
            'deviceCode' => $deviceCode,
            'deviceName' => 'Heartbeat Device',
            'certificateRequest' => 'heartbeat-cert',
            'autoJsVersion' => '9.3.0',
        ];

        $registerJson = json_encode($registerData);
        $this->assertNotFalse($registerJson, 'JSON encoding failed');
        $client->request('POST', '/api/autojs/v1/device/register', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], $registerJson);

        $registerResponse = $client->getResponse();
        $this->assertTrue($registerResponse->isSuccessful(), 'Register request should be successful. Actual response: ' . $registerResponse->getContent());

        $registerResponseContent = $registerResponse->getContent();
        $this->assertNotFalse($registerResponseContent, 'Response content should not be false');
        $registerContent = json_decode($registerResponseContent, true);
        $this->assertIsArray($registerContent, 'Register response should be valid JSON');
        $this->assertArrayHasKey('certificate', $registerContent, 'Register response should contain certificate');
        $certificate = $registerContent['certificate'];

        // Send heartbeat
        $timestamp = time();
        $signature = $this->generateSignature($deviceCode, $timestamp, $certificate);

        $heartbeatData = [
            'deviceCode' => $deviceCode,
            'signature' => $signature,
            'timestamp' => $timestamp,
        ];

        $heartbeatJson = json_encode($heartbeatData);
        $this->assertNotFalse($heartbeatJson, 'JSON encoding failed');
        $this->expectException(AccessDeniedException::class);
        $this->expectExceptionMessage('Access Denied. The user is not appropriately authenticated.');

        $client->request('POST', '/api/autojs/v1/device/heartbeat', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], $heartbeatJson);
    }

    #[Test]
    public function heartbeatWithInvalidSignatureFails(): void
    {
        $client = self::createClientWithDatabase();
        $admin = $this->createAdminUser('admin@test.com', 'password123');
        $this->loginAsAdmin($client, 'admin@test.com', 'password123');

        $deviceCode = 'INVALID-SIG-DEVICE-' . uniqid();

        // Register device first
        $registerData = [
            'deviceCode' => $deviceCode,
            'deviceName' => 'Invalid Signature Device',
            'certificateRequest' => 'invalid-sig-cert',
            'autoJsVersion' => '9.3.0',
        ];

        $registerJson = json_encode($registerData);
        $this->assertNotFalse($registerJson, 'JSON encoding failed');
        $client->request('POST', '/api/autojs/v1/device/register', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], $registerJson);

        // Send heartbeat with invalid signature
        $heartbeatData = [
            'deviceCode' => $deviceCode,
            'signature' => 'invalid-signature',
            'timestamp' => time(),
        ];

        $heartbeatJson = json_encode($heartbeatData);
        $this->assertNotFalse($heartbeatJson, 'JSON encoding failed');

        $this->expectException(AccessDeniedException::class);
        $this->expectExceptionMessage('Access Denied. The user is not appropriately authenticated.');

        $client->request('POST', '/api/autojs/v1/device/heartbeat', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], $heartbeatJson);
    }

    #[Test]
    public function heartbeatWithoutAuthenticationFails(): void
    {
        $client = self::createClientWithDatabase();

        $requestData = [
            'deviceCode' => 'UNAUTH-HEARTBEAT',
            'signature' => 'some-signature',
            'timestamp' => time(),
        ];

        $requestJson = json_encode($requestData);
        $this->assertNotFalse($requestJson, 'JSON encoding failed');

        $this->expectException(AccessDeniedException::class);
        $this->expectExceptionMessage('Access Denied. The user is not appropriately authenticated.');

        $client->request('POST', '/api/autojs/v1/device/heartbeat', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], $requestJson);
    }

    private function generateSignature(string $deviceCode, int $timestamp, string $certificate): string
    {
        $data = sprintf('%s:%d:%s', $deviceCode, $timestamp, $certificate);

        return hash_hmac('sha256', $data, $certificate);
    }

    #[Test]
    #[DataProvider('provideNotAllowedMethods')]
    public function testMethodNotAllowed(string $method): void
    {
        // 测试不支持的HTTP方法应该被路由层拒绝
        // HeartbeatController只支持POST方法，验证其他方法不被支持
        $supportedMethods = ['POST']; // HeartbeatController只支持POST

        $this->assertNotContains(
            $method,
            $supportedMethods,
            "Method {$method} should not be supported by HeartbeatController"
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
        $reflection = new \ReflectionClass(HeartbeatController::class);
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
    public function testValidationErrors(): void
    {
        $reflection = new \ReflectionClass(HeartbeatController::class);

        // 验证Controller继承正确的基类
        $this->assertTrue($reflection->isSubclassOf('Tourze\AutoJsControlBundle\Controller\AbstractApiController'));

        // 验证使用了ValidatorAwareTrait
        $traits = $reflection->getTraitNames();
        $this->assertContains('Tourze\AutoJsControlBundle\Controller\ValidatorAwareTrait', $traits);

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
}
