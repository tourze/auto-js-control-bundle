<?php

declare(strict_types=1);

namespace Tourze\AutoJsControlBundle\Tests\Dto\Response;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tourze\AutoJsControlBundle\Dto\Response\DeviceLogResponse;

/**
 * @internal
 */
#[CoversClass(DeviceLogResponse::class)]
final class DeviceLogResponseTest extends TestCase
{
    #[Test]
    public function constructorSetsProperties(): void
    {
        // Arrange
        $serverTime = new \DateTimeImmutable('2024-01-01 12:00:00');

        // Act
        $response = new DeviceLogResponse(
            status: 'ok',
            receivedCount: 10,
            savedCount: 8,
            message: 'Log saved successfully',
            serverTime: $serverTime
        );

        // Assert
        $this->assertEquals('ok', $response->getStatus());
        $this->assertEquals('Log saved successfully', $response->getMessage());
        $this->assertEquals(10, $response->getReceivedCount());
        $this->assertEquals(8, $response->getSavedCount());
        $this->assertEquals($serverTime, $response->getServerTime());
    }

    #[Test]
    public function constructorWithDefaultServerTime(): void
    {
        // Arrange & Act
        $response = new DeviceLogResponse(
            status: 'error',
            receivedCount: 5,
            savedCount: 0,
            message: 'Failed to save logs'
        );

        // Assert
        $this->assertEquals('error', $response->getStatus());
        $this->assertEquals('Failed to save logs', $response->getMessage());
        $this->assertEquals(5, $response->getReceivedCount());
        $this->assertEquals(0, $response->getSavedCount());
        $this->assertInstanceOf(\DateTimeImmutable::class, $response->getServerTime());
    }

    #[Test]
    public function createSuccessResponse(): void
    {
        // Act
        $response = DeviceLogResponse::success(15, 15);

        // Assert
        $this->assertEquals('ok', $response->getStatus());
        $this->assertEquals('成功接收15条日志，保存15条', $response->getMessage());
        $this->assertEquals(15, $response->getReceivedCount());
        $this->assertEquals(15, $response->getSavedCount());
    }

    #[Test]
    public function createPartialResponse(): void
    {
        // Act
        $response = DeviceLogResponse::partial(20, 18, '2条日志格式错误');

        // Assert
        $this->assertEquals('partial', $response->getStatus());
        $this->assertEquals('接收20条日志，保存18条，部分失败：2条日志格式错误', $response->getMessage());
        $this->assertEquals(20, $response->getReceivedCount());
        $this->assertEquals(18, $response->getSavedCount());
    }

    #[Test]
    public function createErrorResponse(): void
    {
        // Act
        $response = DeviceLogResponse::error('数据库连接失败');

        // Assert
        $this->assertEquals('error', $response->getStatus());
        $this->assertEquals('数据库连接失败', $response->getMessage());
        $this->assertEquals(0, $response->getReceivedCount());
        $this->assertEquals(0, $response->getSavedCount());
    }

    #[Test]
    public function jsonSerializeReturnsCorrectData(): void
    {
        // Arrange
        $serverTime = new \DateTimeImmutable('2024-01-01 14:00:00');
        $response = new DeviceLogResponse(
            status: 'ok',
            receivedCount: 100,
            savedCount: 98,
            message: 'Logs processed',
            serverTime: $serverTime
        );

        // Act
        $json = json_encode($response);
        $this->assertNotFalse($json);
        $decoded = json_decode($json, true);

        // Assert
        $this->assertEquals('ok', $decoded['status']);
        $this->assertEquals('Logs processed', $decoded['message']);
        $this->assertEquals(100, $decoded['receivedCount']);
        $this->assertEquals(98, $decoded['savedCount']);
        $this->assertEquals($serverTime->format(\DateTimeInterface::RFC3339), $decoded['serverTime']);
    }

    #[Test]
    public function jsonSerializeWithoutMessage(): void
    {
        // Arrange
        $response = new DeviceLogResponse(
            status: 'ok',
            receivedCount: 5,
            savedCount: 5
        );

        // Act
        $json = json_encode($response);
        $this->assertNotFalse($json);
        $decoded = json_decode($json, true);

        // Assert
        $this->assertEquals('ok', $decoded['status']);
        $this->assertArrayNotHasKey('message', $decoded);
        $this->assertEquals(5, $decoded['receivedCount']);
        $this->assertEquals(5, $decoded['savedCount']);
        $this->assertArrayHasKey('serverTime', $decoded);
    }
}
