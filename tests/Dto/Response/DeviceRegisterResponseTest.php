<?php

declare(strict_types=1);

namespace Tourze\AutoJsControlBundle\Tests\Dto\Response;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tourze\AutoJsControlBundle\Dto\Response\DeviceRegisterResponse;

/**
 * @internal
 */
#[CoversClass(DeviceRegisterResponse::class)]
final class DeviceRegisterResponseTest extends TestCase
{
    #[Test]
    public function constructorSetsProperties(): void
    {
        // Arrange
        $serverTime = new \DateTimeImmutable('2024-01-01 12:00:00');
        $config = ['heartbeatInterval' => 60, 'logLevel' => 'info'];

        // Act
        $response = new DeviceRegisterResponse(
            status: 'ok',
            deviceId: 'DEV_123',
            certificate: 'cert_hash_12345',
            message: 'Device registered successfully',
            serverTime: $serverTime,
            config: $config
        );

        // Assert
        $this->assertEquals('ok', $response->getStatus());
        $this->assertEquals('Device registered successfully', $response->getMessage());
        $this->assertEquals('DEV_123', $response->getDeviceId());
        $this->assertEquals('cert_hash_12345', $response->getCertificate());
        $this->assertEquals($serverTime, $response->getServerTime());
        $this->assertEquals($config, $response->getConfig());
    }

    #[Test]
    public function constructorWithDefaultValues(): void
    {
        // Arrange & Act
        $response = new DeviceRegisterResponse(
            status: 'error',
            message: 'Registration failed'
        );

        // Assert
        $this->assertEquals('error', $response->getStatus());
        $this->assertEquals('Registration failed', $response->getMessage());
        $this->assertNull($response->getDeviceId());
        $this->assertNull($response->getCertificate());
        $this->assertInstanceOf(\DateTimeImmutable::class, $response->getServerTime());
        $this->assertNull($response->getConfig());
    }

    #[Test]
    public function createSuccessResponse(): void
    {
        // Arrange
        $config = ['maxScripts' => 10];

        // Act
        $response = DeviceRegisterResponse::success(
            deviceId: 'DEV_456',
            certificate: 'new_cert_789',
            config: $config
        );

        // Assert
        $this->assertEquals('ok', $response->getStatus());
        $this->assertEquals('设备注册成功', $response->getMessage());
        $this->assertEquals('DEV_456', $response->getDeviceId());
        $this->assertEquals('new_cert_789', $response->getCertificate());
        $this->assertInstanceOf(\DateTimeImmutable::class, $response->getServerTime());
        $this->assertEquals($config, $response->getConfig());
    }

    #[Test]
    public function createExistsResponse(): void
    {
        // Act
        $response = DeviceRegisterResponse::exists('DEV_EXISTING');

        // Assert
        $this->assertEquals('exists', $response->getStatus());
        $this->assertEquals('设备已注册', $response->getMessage());
        $this->assertEquals('DEV_EXISTING', $response->getDeviceId());
        $this->assertNull($response->getCertificate());
        $this->assertInstanceOf(\DateTimeImmutable::class, $response->getServerTime());
    }

    #[Test]
    public function createExistsResponseWithCustomMessage(): void
    {
        // Act
        $response = DeviceRegisterResponse::exists('DEV_DUP', '设备代码重复');

        // Assert
        $this->assertEquals('exists', $response->getStatus());
        $this->assertEquals('设备代码重复', $response->getMessage());
        $this->assertEquals('DEV_DUP', $response->getDeviceId());
    }

    #[Test]
    public function createErrorResponse(): void
    {
        // Act
        $response = DeviceRegisterResponse::error('数据库连接失败');

        // Assert
        $this->assertEquals('error', $response->getStatus());
        $this->assertEquals('数据库连接失败', $response->getMessage());
        $this->assertNull($response->getDeviceId());
        $this->assertNull($response->getCertificate());
    }

    #[Test]
    public function createInvalidResponse(): void
    {
        // Act
        $response = DeviceRegisterResponse::invalid('设备代码格式错误');

        // Assert
        $this->assertEquals('invalid', $response->getStatus());
        $this->assertEquals('设备代码格式错误', $response->getMessage());
        $this->assertNull($response->getDeviceId());
        $this->assertNull($response->getCertificate());
    }

    #[Test]
    public function jsonSerializeReturnsCorrectData(): void
    {
        // Arrange
        $serverTime = new \DateTimeImmutable('2024-01-01 14:30:00');
        $response = new DeviceRegisterResponse(
            status: 'ok',
            deviceId: 'DEV_999',
            certificate: 'json_cert',
            message: 'Success',
            serverTime: $serverTime,
            config: ['debug' => false]
        );

        // Act
        $json = json_encode($response);
        $this->assertNotFalse($json);
        $decoded = json_decode($json, true);

        // Assert
        $this->assertEquals('ok', $decoded['status']);
        $this->assertEquals('Success', $decoded['message']);
        $this->assertEquals('DEV_999', $decoded['deviceId']);
        $this->assertEquals('json_cert', $decoded['certificate']);
        $this->assertEquals($serverTime->format(\DateTimeInterface::RFC3339), $decoded['serverTime']);
        $this->assertEquals(['debug' => false], $decoded['config']);
    }

    #[Test]
    public function jsonSerializeWithMinimalData(): void
    {
        // Arrange
        $response = new DeviceRegisterResponse(
            status: 'error',
            message: 'Error occurred'
        );

        // Act
        $json = json_encode($response);
        $this->assertNotFalse($json);
        $decoded = json_decode($json, true);

        // Assert
        $this->assertEquals('error', $decoded['status']);
        $this->assertEquals('Error occurred', $decoded['message']);
        $this->assertArrayNotHasKey('deviceId', $decoded);
        $this->assertArrayNotHasKey('certificate', $decoded);
        $this->assertArrayNotHasKey('config', $decoded);
        $this->assertArrayHasKey('serverTime', $decoded);
    }

    #[Test]
    public function jsonSerializeHandlesAllOptionalFields(): void
    {
        // Arrange
        $response = new DeviceRegisterResponse(
            status: 'ok',
            deviceId: 'DEV_FULL',
            certificate: 'full_cert',
            message: 'Complete response',
            config: [
                'heartbeatInterval' => 30,
                'maxRetries' => 3,
                'features' => ['logging', 'monitoring'],
            ]
        );

        // Act
        $json = json_encode($response);
        $this->assertNotFalse($json);
        $decoded = json_decode($json, true);

        // Assert
        $this->assertArrayHasKey('status', $decoded);
        $this->assertArrayHasKey('deviceId', $decoded);
        $this->assertArrayHasKey('certificate', $decoded);
        $this->assertArrayHasKey('message', $decoded);
        $this->assertArrayHasKey('config', $decoded);
        $this->assertArrayHasKey('serverTime', $decoded);
        $this->assertIsArray($decoded['config']);
        $this->assertArrayHasKey('features', $decoded['config']);
    }
}
