<?php

namespace Tourze\AutoJsControlBundle\Tests\Service;

use DeviceBundle\Entity\Device as BaseDevice;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Tourze\AutoJsControlBundle\Entity\AutoJsDevice;
use Tourze\AutoJsControlBundle\Exception\BusinessLogicException;
use Tourze\AutoJsControlBundle\Repository\AutoJsDeviceRepository;
use Tourze\AutoJsControlBundle\Service\DeviceHeartbeatService;
use Tourze\AutoJsControlBundle\Service\InstructionQueueService;
use Tourze\AutoJsControlBundle\Service\QueueMonitorService;

/**
 * @internal
 */
#[CoversClass(QueueMonitorService::class)]
final class QueueMonitorServiceTest extends TestCase
{
    private QueueMonitorService $queueMonitorService;

    private InstructionQueueService&MockObject $queueService;

    private DeviceHeartbeatService&MockObject $heartbeatService;

    private AutoJsDeviceRepository&MockObject $deviceRepository;

    protected function setUp(): void
    {
        $this->queueService = $this->createMock(InstructionQueueService::class);
        $this->heartbeatService = $this->createMock(DeviceHeartbeatService::class);
        $this->deviceRepository = $this->createMock(AutoJsDeviceRepository::class);

        $this->queueMonitorService = new QueueMonitorService(
            $this->queueService,
            $this->heartbeatService,
            $this->deviceRepository
        );
    }

    public function testServiceExists(): void
    {
        $this->assertInstanceOf(QueueMonitorService::class, $this->queueMonitorService);
    }

    public function testGetDeviceStats(): void
    {
        // Arrange
        $baseDevice = new BaseDevice();
        $baseDevice->setCode('TEST_DEVICE');
        $baseDevice->setName('Test Device');

        $device = new AutoJsDevice();
        $device->setBaseDevice($baseDevice);

        $this->heartbeatService->expects($this->once())
            ->method('isDeviceOnline')
            ->with('TEST_DEVICE')
            ->willReturn(true)
        ;

        $this->queueService->expects($this->once())
            ->method('getQueueLength')
            ->with('TEST_DEVICE')
            ->willReturn(5)
        ;

        // Act
        $stats = $this->queueMonitorService->getDeviceStats($device);

        // Assert
        $this->assertIsArray($stats);
        $this->assertEquals('TEST_DEVICE', $stats['code']);
        $this->assertEquals('Test Device', $stats['name']);
        $this->assertTrue($stats['isOnline']);
        $this->assertEquals(5, $stats['queueLength']);
        $this->assertEquals('<fg=green>在线</>', $stats['statusDisplay']);
    }

    public function testGetDeviceStatsThrowsExceptionWhenBaseDeviceIsNull(): void
    {
        // Arrange
        $device = new AutoJsDevice();

        // Act & Assert
        $this->expectException(BusinessLogicException::class);
        $this->expectExceptionMessage('配置错误: Device base device is required');

        $this->queueMonitorService->getDeviceStats($device);
    }

    public function testCollectDeviceStatistics(): void
    {
        // Arrange
        $baseDevice1 = new BaseDevice();
        $baseDevice1->setCode('DEVICE_1');
        $baseDevice1->setName('Device 1');
        $device1 = new AutoJsDevice();
        $device1->setBaseDevice($baseDevice1);

        $baseDevice2 = new BaseDevice();
        $baseDevice2->setCode('DEVICE_2');
        $baseDevice2->setName('Device 2');
        $device2 = new AutoJsDevice();
        $device2->setBaseDevice($baseDevice2);

        $devices = [$device1, $device2];

        $this->heartbeatService->expects($this->exactly(2))
            ->method('isDeviceOnline')
            ->willReturnCallback(function ($deviceCode) {
                return match ($deviceCode) {
                    'DEVICE_1' => true,
                    'DEVICE_2' => false,
                    default => false,
                };
            })
        ;

        $this->queueService->expects($this->exactly(2))
            ->method('getQueueLength')
            ->willReturnCallback(function ($deviceCode) {
                return match ($deviceCode) {
                    'DEVICE_1' => 3,
                    'DEVICE_2' => 7,
                    default => 0,
                };
            })
        ;

        // Act
        $stats = $this->queueMonitorService->collectDeviceStatistics($devices);

        // Assert
        $this->assertEquals(2, $stats['totalCount']);
        $this->assertEquals(1, $stats['onlineCount']);
        $this->assertEquals(10, $stats['totalQueueLength']);
        $this->assertCount(2, $stats['devices']);
    }

    public function testCollectDeviceInfo(): void
    {
        // Arrange
        $deviceCode = 'TEST_DEVICE';
        $metrics = ['cpu' => 50, 'memory' => 60];

        $this->heartbeatService->expects($this->once())
            ->method('isDeviceOnline')
            ->with($deviceCode)
            ->willReturn(true)
        ;

        $this->queueService->expects($this->once())
            ->method('getQueueLength')
            ->with($deviceCode)
            ->willReturn(3)
        ;

        $this->heartbeatService->expects($this->once())
            ->method('getDeviceMetrics')
            ->with($deviceCode)
            ->willReturn($metrics)
        ;

        // Act
        $info = $this->queueMonitorService->collectDeviceInfo($deviceCode);

        // Assert
        $this->assertTrue($info['isOnline']);
        $this->assertEquals(3, $info['queueLength']);
        $this->assertEquals($metrics, $info['metrics']);
    }

    public function testGatherDeviceStatistics(): void
    {
        // Arrange
        $baseDevice1 = new BaseDevice();
        $baseDevice1->setCode('DEVICE_1');
        $baseDevice1->setName('Device 1');
        $device1 = new AutoJsDevice();
        $device1->setBaseDevice($baseDevice1);

        $baseDevice2 = new BaseDevice();
        $baseDevice2->setCode('DEVICE_2');
        $baseDevice2->setName('Device 2');
        $device2 = new AutoJsDevice();
        $device2->setBaseDevice($baseDevice2);

        $baseDevice3 = new BaseDevice();
        $baseDevice3->setCode('DEVICE_3');
        $baseDevice3->setName('Device 3');
        $device3 = new AutoJsDevice();
        $device3->setBaseDevice($baseDevice3);

        $this->deviceRepository->expects($this->once())
            ->method('findAll')
            ->willReturn([$device1, $device2, $device3])
        ;

        $this->heartbeatService->expects($this->exactly(3))
            ->method('isDeviceOnline')
            ->willReturnCallback(function ($deviceCode) {
                return match ($deviceCode) {
                    'DEVICE_1' => true,
                    'DEVICE_2' => false,
                    'DEVICE_3' => true,
                    default => false,
                };
            })
        ;

        $this->queueService->expects($this->exactly(3))
            ->method('getQueueLength')
            ->willReturnCallback(function ($deviceCode) {
                return match ($deviceCode) {
                    'DEVICE_1' => 5,
                    'DEVICE_2' => 0,
                    'DEVICE_3' => 8,
                    default => 0,
                };
            })
        ;

        // Act
        $stats = $this->queueMonitorService->gatherDeviceStatistics();

        // Assert
        $this->assertEquals(3, $stats['totalDevices']);
        $this->assertEquals(2, $stats['onlineCount']);
        $this->assertEquals(13, $stats['totalQueueLength']);
        $this->assertCount(2, $stats['busyDevices']); // Only devices with queue > 0
    }

    public function testSortDevicesByQueueLength(): void
    {
        // Arrange
        $busyDevices = [
            ['code' => 'DEVICE_1', 'name' => 'Device 1', 'queueLength' => 3, 'isOnline' => true],
            ['code' => 'DEVICE_2', 'name' => 'Device 2', 'queueLength' => 7, 'isOnline' => true],
            ['code' => 'DEVICE_3', 'name' => 'Device 3', 'queueLength' => 1, 'isOnline' => false],
        ];

        // Act
        $sorted = $this->queueMonitorService->sortDevicesByQueueLength($busyDevices);

        // Assert
        $this->assertEquals('DEVICE_2', $sorted[0]['code']); // Highest queue length first
        $this->assertEquals('DEVICE_1', $sorted[1]['code']);
        $this->assertEquals('DEVICE_3', $sorted[2]['code']); // Lowest queue length last
    }

    public function testPreviewQueue(): void
    {
        // Arrange
        $deviceCode = 'TEST_DEVICE';
        $limit = 10;
        $expectedQueue = ['instruction1', 'instruction2'];

        $this->queueService->expects($this->once())
            ->method('previewQueue')
            ->with($deviceCode, $limit)
            ->willReturn($expectedQueue)
        ;

        // Act
        $result = $this->queueMonitorService->previewQueue($deviceCode, $limit);

        // Assert
        $this->assertEquals($expectedQueue, $result);
    }

    public function testGetQueueLength(): void
    {
        // Arrange
        $deviceCode = 'TEST_DEVICE';
        $expectedLength = 15;

        $this->queueService->expects($this->once())
            ->method('getQueueLength')
            ->with($deviceCode)
            ->willReturn($expectedLength)
        ;

        // Act
        $result = $this->queueMonitorService->getQueueLength($deviceCode);

        // Assert
        $this->assertEquals($expectedLength, $result);
    }

    public function testClearDeviceQueue(): void
    {
        // Arrange
        $deviceCode = 'TEST_DEVICE';
        $clearedCount = 8;

        $this->queueService->expects($this->once())
            ->method('clearDeviceQueue')
            ->with($deviceCode)
            ->willReturn($clearedCount)
        ;

        // Act
        $result = $this->queueMonitorService->clearDeviceQueue($deviceCode);

        // Assert
        $this->assertEquals($clearedCount, $result);
    }

    public function testFindDevice(): void
    {
        // Arrange
        $deviceCode = 'TEST_DEVICE';
        $expectedDevice = new AutoJsDevice();

        $this->deviceRepository->expects($this->once())
            ->method('findByDeviceCode')
            ->with($deviceCode)
            ->willReturn($expectedDevice)
        ;

        // Act
        $result = $this->queueMonitorService->findDevice($deviceCode);

        // Assert
        $this->assertSame($expectedDevice, $result);
    }

    public function testGetAllDevices(): void
    {
        // Arrange
        $expectedDevices = [new AutoJsDevice(), new AutoJsDevice()];

        $this->deviceRepository->expects($this->once())
            ->method('findAll')
            ->willReturn($expectedDevices)
        ;

        // Act
        $result = $this->queueMonitorService->getAllDevices();

        // Assert
        $this->assertEquals($expectedDevices, $result);
    }
}
