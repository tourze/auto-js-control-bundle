<?php

declare(strict_types=1);

namespace Tourze\AutoJsControlBundle\Tests\Service;

use DeviceBundle\Entity\Device as BaseDevice;
use DeviceBundle\Enum\DeviceStatus;
use DeviceBundle\Enum\DeviceType;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Tourze\AutoJsControlBundle\Entity\AutoJsDevice;
use Tourze\AutoJsControlBundle\Entity\DeviceMonitorData;
use Tourze\AutoJsControlBundle\Exception\BusinessLogicException;
use Tourze\AutoJsControlBundle\Service\CacheStorageService;
use Tourze\AutoJsControlBundle\Service\DeviceHeartbeatService;
use Tourze\AutoJsControlBundle\ValueObject\RedisQueueKeys;
use Tourze\LockServiceBundle\Service\LockService;

/**
 * @internal
 */
#[CoversClass(DeviceHeartbeatService::class)]
final class DeviceHeartbeatServiceTest extends TestCase
{
    private DeviceHeartbeatService $service;

    private EntityManagerInterface&MockObject $entityManager;

    private LoggerInterface&MockObject $logger;

    private CacheStorageService&MockObject $cacheStorage;

    private LockService&MockObject $lockService;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->cacheStorage = $this->createMock(CacheStorageService::class);
        $this->lockService = $this->createMock(LockService::class);

        $this->service = new DeviceHeartbeatService(
            $this->entityManager,
            $this->logger,
            $this->cacheStorage,
            $this->lockService
        );
    }

    #[Test]
    public function isDeviceOnlineReturnsTrueWhenHeartbeatExists(): void
    {
        // Arrange
        $deviceCode = 'TEST_001';
        $currentTime = time();

        $this->cacheStorage->expects($this->once())
            ->method('getDeviceOnline')
            ->with($deviceCode)
            ->willReturn($currentTime)
        ;

        // Act
        $result = $this->service->isDeviceOnline($deviceCode);

        // Assert
        $this->assertTrue($result);
    }

    #[Test]
    public function isDeviceOnlineReturnsFalseWhenHeartbeatNotExists(): void
    {
        // Arrange
        $deviceCode = 'TEST_001';

        $this->cacheStorage->expects($this->once())
            ->method('getDeviceOnline')
            ->with($deviceCode)
            ->willReturn(null)
        ;

        // Act
        $result = $this->service->isDeviceOnline($deviceCode);

        // Assert
        $this->assertFalse($result);
    }

    #[Test]
    public function isDeviceOnlineReturnsFalseWhenHeartbeatExpired(): void
    {
        // Arrange
        $deviceCode = 'TEST_001';
        $expiredTime = time() - RedisQueueKeys::TTL_ONLINE_STATUS - 1;

        $this->cacheStorage->expects($this->once())
            ->method('getDeviceOnline')
            ->with($deviceCode)
            ->willReturn($expiredTime)
        ;

        // Act
        $result = $this->service->isDeviceOnline($deviceCode);

        // Assert
        $this->assertFalse($result);
    }

    #[Test]
    public function getDeviceMetricsReturnsMetricsWhenExists(): void
    {
        // Arrange
        $deviceCode = 'TEST_001';
        $metrics = [
            'cpuUsage' => '45.5',
            'memoryUsage' => '60.0',
            'batteryLevel' => '85',
            'activeScripts' => '["script1","script2"]',
            'lastUpdate' => (string) time(),
        ];

        $this->cacheStorage->expects($this->once())
            ->method('getDeviceMetrics')
            ->with($deviceCode)
            ->willReturn($metrics)
        ;

        // Act
        $result = $this->service->getDeviceMetrics($deviceCode);

        // Assert
        $this->assertEquals(45.5, $result['cpuUsage']);
        $this->assertEquals(60.0, $result['memoryUsage']);
        $this->assertEquals(85, $result['batteryLevel']);
        $this->assertEquals(['script1', 'script2'], $result['activeScripts']);
    }

    #[Test]
    public function getDeviceMetricsReturnsEmptyArrayWhenNotExists(): void
    {
        // Arrange
        $deviceCode = 'TEST_001';

        $this->cacheStorage->expects($this->once())
            ->method('getDeviceMetrics')
            ->with($deviceCode)
            ->willReturn([])
        ;

        // Act
        $result = $this->service->getDeviceMetrics($deviceCode);

        // Assert
        $this->assertEmpty($result);
    }

    #[Test]
    public function testMarkDeviceOffline(): void
    {
        // Arrange
        $deviceCode = 'TEST_001';

        $this->cacheStorage->expects($this->once())
            ->method('setDeviceOnline')
            ->with($deviceCode, false)
        ;

        $this->logger->expects($this->once())
            ->method('info')
            ->with('设备已标记为离线', ['deviceCode' => $deviceCode])
        ;

        // Act
        $this->service->markDeviceOffline($deviceCode);
    }

    #[Test]
    public function testCheckDevicesOnlineStatus(): void
    {
        // Arrange
        $deviceCodes = ['TEST_001', 'TEST_002', 'TEST_003'];
        $currentTime = time();

        $matcher = $this->exactly(3);
        $this->cacheStorage->expects($matcher)
            ->method('getDeviceOnline')
            ->willReturnCallback(function ($deviceCode) use ($matcher, $currentTime) {
                return match ($matcher->numberOfInvocations()) {
                    1 => $currentTime,      // TEST_001 - online
                    2 => null,               // TEST_002 - not exists
                    3 => $currentTime - RedisQueueKeys::TTL_ONLINE_STATUS - 1, // TEST_003 - expired
                    default => throw BusinessLogicException::configurationError('Unexpected number of invocations: ' . $matcher->numberOfInvocations()),
                };
            })
        ;

        // Act
        $result = $this->service->checkDevicesOnlineStatus($deviceCodes);

        // Assert
        $this->assertTrue($result['TEST_001']);
        $this->assertFalse($result['TEST_002']);
        $this->assertFalse($result['TEST_003']);
    }

    #[Test]
    public function processHeartbeatUpdatesDeviceStatusAndSavesMonitorData(): void
    {
        // Arrange
        $baseDevice = new BaseDevice();
        $baseDevice->setCode('TEST_DEVICE_001');
        $baseDevice->setName('Test Device');
        $baseDevice->setModel('TestModel');
        $baseDevice->setDeviceType(DeviceType::PHONE);

        $autoJsDevice = new AutoJsDevice();
        $autoJsDevice->setBaseDevice($baseDevice);
        $autoJsDevice->setAutoJsVersion('4.0.0');

        $autoJsVersion = '4.1.1';
        $deviceInfo = [
            'model' => 'NewModel',
            'brand' => 'TestBrand',
            'osVersion' => '11.0',
            'cpuCores' => 8,
            'memorySize' => '8GB',
            'storageSize' => '128GB',
        ];
        $monitorData = [
            'cpuUsage' => 45.5,
            'memoryUsage' => 60.0,
            'batteryLevel' => 85,
            'networkType' => 'WiFi',
            'additionalData' => ['key' => 'value'],
        ];

        // Mock lock service to execute callback immediately
        $this->lockService->expects($this->once())
            ->method('blockingRun')
            ->willReturnCallback(static function ($lockKey, $callback) {
                return call_user_func($callback);
            })
        ;

        // Mock entity manager
        $persistMatcher = $this->exactly(3);
        $this->entityManager->expects($persistMatcher)
            ->method('persist')
            ->willReturnCallback(function ($entity) use ($persistMatcher, $baseDevice, $autoJsDevice) {
                return match ($persistMatcher->numberOfInvocations()) {
                    1 => $baseDevice === $entity,
                    2 => $autoJsDevice === $entity,
                    3 => $entity instanceof DeviceMonitorData,
                    default => throw new \RuntimeException('Unexpected persist call'),
                };
            })
        ;

        $this->entityManager->expects($this->exactly(2))
            ->method('flush')
        ;

        // Mock cache storage for heartbeat and metrics
        $this->cacheStorage->expects($this->once())
            ->method('setDeviceHeartbeat')
        ;

        $this->cacheStorage->expects($this->once())
            ->method('setDeviceOnline')
            ->with('TEST_DEVICE_001', true)
        ;

        $this->cacheStorage->expects($this->once())
            ->method('updateDeviceMetrics')
        ;

        // Mock logger
        $this->logger->expects($this->once())
            ->method('info')
        ;

        // Act
        $this->service->processHeartbeat($autoJsDevice, $autoJsVersion, $deviceInfo, $monitorData);

        // Assert - verify device was updated
        $this->assertEquals('4.1.1', $autoJsDevice->getAutoJsVersion());
        $this->assertEquals(DeviceStatus::ONLINE, $baseDevice->getStatus());
        $this->assertNotNull($baseDevice->getLastOnlineTime());
        $this->assertEquals('NewModel', $baseDevice->getModel());
        $this->assertEquals('TestBrand', $baseDevice->getBrand());
        $this->assertEquals('11.0', $baseDevice->getOsVersion());
        $this->assertEquals(8, $baseDevice->getCpuCores());
        $this->assertEquals('8GB', $baseDevice->getMemorySize());
        $this->assertEquals('128GB', $baseDevice->getStorageSize());
    }

    #[Test]
    public function processHeartbeatHandlesMinimalData(): void
    {
        // Arrange
        $baseDevice = new BaseDevice();
        $baseDevice->setCode('TEST_DEVICE_002');
        $baseDevice->setName('Test Device');
        $baseDevice->setModel('TestModel');
        $baseDevice->setDeviceType(DeviceType::PHONE);

        $autoJsDevice = new AutoJsDevice();
        $autoJsDevice->setBaseDevice($baseDevice);
        $autoJsDevice->setAutoJsVersion('4.0.0');

        // Mock lock service
        $this->lockService->expects($this->once())
            ->method('blockingRun')
            ->willReturnCallback(static function ($lockKey, $callback) {
                return call_user_func($callback);
            })
        ;

        // Mock entity manager for minimal updates
        $persistMatcher = $this->exactly(2);
        $this->entityManager->expects($persistMatcher)
            ->method('persist')
            ->willReturnCallback(function ($entity) use ($persistMatcher, $baseDevice, $autoJsDevice) {
                return match ($persistMatcher->numberOfInvocations()) {
                    1 => $baseDevice === $entity,
                    2 => $autoJsDevice === $entity,
                    default => throw new \RuntimeException('Unexpected persist call'),
                };
            })
        ;

        $this->entityManager->expects($this->once())
            ->method('flush')
        ;

        // Mock cache storage
        $this->cacheStorage->expects($this->once())
            ->method('setDeviceHeartbeat')
        ;

        $this->cacheStorage->expects($this->once())
            ->method('setDeviceOnline')
            ->with('TEST_DEVICE_002', true)
        ;

        // Mock logger
        $this->logger->expects($this->once())
            ->method('info')
        ;

        // Act - with minimal parameters
        $this->service->processHeartbeat($autoJsDevice);

        // Assert
        $this->assertEquals(DeviceStatus::ONLINE, $baseDevice->getStatus());
        $this->assertNotNull($baseDevice->getLastOnlineTime());
    }

    #[Test]
    public function processHeartbeatThrowsExceptionWhenBaseDeviceIsNull(): void
    {
        // Arrange
        $autoJsDevice = new AutoJsDevice();
        // No base device set

        // Assert
        $this->expectException(BusinessLogicException::class);
        $this->expectExceptionMessage('Base device is required');

        // Act
        $this->service->processHeartbeat($autoJsDevice);
    }

    #[Test]
    public function testProcessHeartbeat(): void
    {
        // 基础的 processHeartbeat 测试，确保方法被覆盖
        $this->expectException(BusinessLogicException::class);
        $autoJsDevice = new AutoJsDevice();
        $this->service->processHeartbeat($autoJsDevice);
    }

    #[Test]
    public function processHeartbeatHandlesExceptionDuringProcessing(): void
    {
        // Arrange
        $baseDevice = new BaseDevice();
        $baseDevice->setCode('TEST_DEVICE_003');
        $baseDevice->setName('Test Device');
        $baseDevice->setModel('TestModel');
        $baseDevice->setDeviceType(DeviceType::PHONE);

        $autoJsDevice = new AutoJsDevice();
        $autoJsDevice->setBaseDevice($baseDevice);

        // Mock lock service
        $this->lockService->expects($this->once())
            ->method('blockingRun')
            ->willReturnCallback(static function ($lockKey, $callback) {
                return call_user_func($callback);
            })
        ;

        // Mock entity manager to throw exception
        $this->entityManager->expects($this->once())
            ->method('persist')
            ->willThrowException(new \RuntimeException('Database error'))
        ;

        // Mock logger to capture error
        $this->logger->expects($this->once())
            ->method('error')
        ;

        // Assert
        $this->expectException(BusinessLogicException::class);
        $this->expectExceptionMessage('处理设备心跳失败: Database error');

        // Act
        $this->service->processHeartbeat($autoJsDevice);
    }

    #[Test]
    public function processHeartbeatUpdatesOnlyProvidedDeviceInfo(): void
    {
        // Arrange
        $baseDevice = new BaseDevice();
        $baseDevice->setCode('TEST_DEVICE_004');
        $baseDevice->setName('Test Device');
        $baseDevice->setModel('OldModel');
        $baseDevice->setBrand('OldBrand');
        $baseDevice->setDeviceType(DeviceType::PHONE);

        $autoJsDevice = new AutoJsDevice();
        $autoJsDevice->setBaseDevice($baseDevice);

        $deviceInfo = [
            'model' => 'NewModel',
            // Only updating model, not brand
        ];

        // Mock lock service
        $this->lockService->expects($this->once())
            ->method('blockingRun')
            ->willReturnCallback(static function ($lockKey, $callback) {
                return call_user_func($callback);
            })
        ;

        // Mock entity manager
        $this->entityManager->expects($this->exactly(2))
            ->method('persist')
        ;

        $this->entityManager->expects($this->once())
            ->method('flush')
        ;

        // Mock cache storage
        $this->cacheStorage->expects($this->once())
            ->method('setDeviceHeartbeat')
        ;

        $this->cacheStorage->expects($this->once())
            ->method('setDeviceOnline')
        ;

        // Mock logger
        $this->logger->expects($this->once())
            ->method('info')
        ;

        // Act
        $this->service->processHeartbeat($autoJsDevice, null, $deviceInfo);

        // Assert - only model should be updated, brand should remain
        $this->assertEquals('NewModel', $baseDevice->getModel());
        $this->assertEquals('OldBrand', $baseDevice->getBrand());
    }
}
