<?php

declare(strict_types=1);

namespace Tourze\AutoJsControlBundle\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Test;
use Tourze\AutoJsControlBundle\Service\CacheStorageService;
use Tourze\AutoJsControlBundle\Service\InMemoryStorageAdapter;
use Tourze\AutoJsControlBundle\Service\StorageAdapterInterface;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(CacheStorageService::class)]
#[RunTestsInSeparateProcesses]
final class CacheStorageServiceTest extends AbstractIntegrationTestCase
{
    private CacheStorageService $service;

    private InMemoryStorageAdapter $storage;

    protected function onSetUp(): void
    {
        // 使用内存存储适配器，无需外部 Redis 服务
        $this->storage = new InMemoryStorageAdapter();

        self::getContainer()->set(StorageAdapterInterface::class, $this->storage);
        $this->service = self::getService(CacheStorageService::class);
    }

    protected function onTearDown(): void
    {
        $this->storage->flushAll();
    }

    #[Test]
    public function testSetAndGetDeviceOnlineStatus(): void
    {
        // Arrange
        $deviceCode = 'TEST_DEVICE';

        // Act - 设置在线
        $beforeTime = time();
        $this->service->setDeviceOnline($deviceCode, true);
        $isOnline = $this->service->getDeviceOnline($deviceCode);
        $afterTime = time();

        // Assert - 在线状态应该是一个时间戳
        $this->assertGreaterThanOrEqual($beforeTime, $isOnline);
        $this->assertLessThanOrEqual($afterTime, $isOnline);

        // Act - 设置离线
        $this->service->setDeviceOnline($deviceCode, false);
        $isOnline = $this->service->getDeviceOnline($deviceCode);

        // Assert - 离线状态应该返回 null
        $this->assertNull($isOnline);
    }

    #[Test]
    public function testGetDeviceOnlineStatusForUnknownDevice(): void
    {
        // Arrange
        $deviceCode = 'UNKNOWN_DEVICE';

        // Act
        $isOnline = $this->service->getDeviceOnline($deviceCode);

        // Assert - 默认为离线
        $this->assertNull($isOnline);
    }

    #[Test]
    public function testUpdateAndGetInstructionStatus(): void
    {
        // Arrange
        $instructionId = 'test-instruction-123';
        $status = [
            'status' => 'executing',
            'updateTime' => '2024-01-01T00:00:00Z',
            'deviceCode' => 'TEST_DEVICE',
        ];

        // Act
        $this->service->updateInstructionStatus($instructionId, $status);
        $retrievedStatus = $this->service->getInstructionStatus($instructionId);

        // Assert
        $this->assertEquals($status, $retrievedStatus);
    }

    #[Test]
    public function testGetInstructionStatusForNonExistent(): void
    {
        // Arrange
        $instructionId = 'nonexistent-instruction';

        // Act
        $status = $this->service->getInstructionStatus($instructionId);

        // Assert
        $this->assertNull($status);
    }

    #[Test]
    public function testUpdateInstructionStatusMultipleTimes(): void
    {
        // Arrange
        $instructionId = 'test-instruction-456';

        $status1 = [
            'status' => 'pending',
            'updateTime' => '2024-01-01T00:00:00Z',
        ];

        $status2 = [
            'status' => 'executing',
            'updateTime' => '2024-01-01T00:01:00Z',
        ];

        $status3 = [
            'status' => 'completed',
            'updateTime' => '2024-01-01T00:02:00Z',
            'result' => 'success',
        ];

        // Act - 多次更新
        $this->service->updateInstructionStatus($instructionId, $status1);
        $this->service->updateInstructionStatus($instructionId, $status2);
        $this->service->updateInstructionStatus($instructionId, $status3);

        // Assert - 应该返回最后的状态
        $finalStatus = $this->service->getInstructionStatus($instructionId);
        $this->assertEquals($status3, $finalStatus);
    }

    #[Test]
    public function testSetDeviceOnlineWithExpiry(): void
    {
        // Arrange
        $deviceCode = 'TEST_DEVICE_EXPIRY';

        // Act
        $beforeTime = time();
        $this->service->setDeviceOnline($deviceCode, true);
        $afterTime = time();
        $timestamp = $this->service->getDeviceOnline($deviceCode);

        // Assert - 设备应该在线，返回时间戳
        $this->assertGreaterThanOrEqual($beforeTime, $timestamp);
        $this->assertLessThanOrEqual($afterTime, $timestamp);

        // 验证键有过期时间设置（默认300秒）
        $ttl = $this->storage->ttl("device_online:{$deviceCode}");
        $this->assertGreaterThan(0, $ttl);
        $this->assertLessThanOrEqual(300, $ttl);
    }

    #[Test]
    public function testInstructionStatusKeyFormat(): void
    {
        // Arrange
        $instructionId = 'test-instruction-789';
        $status = ['status' => 'pending'];

        // Act
        $this->service->updateInstructionStatus($instructionId, $status);

        // Assert - 验证存储的键格式
        $expectedKey = "instruction_status:{$instructionId}";
        $exists = $this->storage->exists($expectedKey);
        $this->assertTrue($exists);
    }

    #[Test]
    public function testMultipleDevicesOnlineStatus(): void
    {
        // Arrange
        $devices = [
            'DEVICE_1' => true,
            'DEVICE_2' => false,
            'DEVICE_3' => true,
        ];

        $beforeTime = time();

        // Act
        foreach ($devices as $deviceCode => $isOnline) {
            $this->service->setDeviceOnline($deviceCode, $isOnline);
        }

        $afterTime = time();

        // Assert
        foreach ($devices as $deviceCode => $expectedOnline) {
            $actualOnline = $this->service->getDeviceOnline($deviceCode);
            if ($expectedOnline) {
                // 在线设备应该返回时间戳
                $this->assertGreaterThanOrEqual($beforeTime, $actualOnline, "Device {$deviceCode} should return valid timestamp");
                $this->assertLessThanOrEqual($afterTime, $actualOnline, "Device {$deviceCode} should return valid timestamp");
            } else {
                // 离线设备应该返回 null
                $this->assertNull($actualOnline, "Device {$deviceCode} should be offline (null)");
            }
        }
    }

    #[Test]
    public function testUpdateDeviceMetrics(): void
    {
        // Arrange
        $deviceCode = 'TEST_DEVICE';
        $metrics = [
            'cpu_usage' => 45.5,
            'memory_usage' => 78.2,
            'battery_level' => 85,
            'network_signal' => -65,
        ];

        // Act
        $this->service->updateDeviceMetrics($deviceCode, $metrics);
        $result = $this->service->getDeviceMetrics($deviceCode);

        // Assert
        $this->assertEquals($metrics, $result);
    }

    #[Test]
    public function testGetDeviceMetricsForNonExistentDevice(): void
    {
        // Arrange
        $deviceCode = 'NON_EXISTENT_DEVICE';

        // Act
        $result = $this->service->getDeviceMetrics($deviceCode);

        // Assert
        $this->assertEquals([], $result);
    }

    #[Test]
    public function testSetAndGetDeviceHeartbeat(): void
    {
        // Arrange
        $deviceCode = 'TEST_DEVICE';
        $timestamp = time();

        // Act
        $this->service->setDeviceHeartbeat($deviceCode, $timestamp);
        $result = $this->service->getDeviceHeartbeat($deviceCode);

        // Assert
        $this->assertEquals($timestamp, $result);
    }

    #[Test]
    public function testGetDeviceHeartbeatForNonExistentDevice(): void
    {
        // Arrange
        $deviceCode = 'NON_EXISTENT_DEVICE';

        // Act
        $result = $this->service->getDeviceHeartbeat($deviceCode);

        // Assert
        $this->assertNull($result);
    }

    #[Test]
    public function testClearDeviceData(): void
    {
        // Arrange
        $deviceCode = 'TEST_DEVICE';

        // 先设置一些数据
        $this->service->setDeviceOnline($deviceCode, true);
        $this->service->updateDeviceMetrics($deviceCode, ['cpu' => 50]);
        $this->service->setDeviceHeartbeat($deviceCode, time());

        // Act
        $this->service->clearDeviceData($deviceCode);

        // Assert - 验证所有相关数据都被清除
        $this->assertNull($this->service->getDeviceOnline($deviceCode));
        $this->assertEquals([], $this->service->getDeviceMetrics($deviceCode));
        $this->assertNull($this->service->getDeviceHeartbeat($deviceCode));
    }
}
