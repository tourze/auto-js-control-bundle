<?php

declare(strict_types=1);

namespace Tourze\AutoJsControlBundle\Tests\Service;

use DeviceBundle\Entity\Device as BaseDevice;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Tourze\AutoJsControlBundle\Entity\AutoJsDevice;
use Tourze\AutoJsControlBundle\Repository\AutoJsDeviceRepository;
use Tourze\AutoJsControlBundle\Service\DeviceHeartbeatService;
use Tourze\AutoJsControlBundle\Service\DeviceQueryService;

/**
 * @internal
 */
#[CoversClass(DeviceQueryService::class)]
final class DeviceQueryServiceTest extends TestCase
{
    private DeviceQueryService $service;

    private AutoJsDeviceRepository&MockObject $autoJsDeviceRepository;

    private DeviceHeartbeatService&MockObject $heartbeatService;

    protected function setUp(): void
    {
        $this->autoJsDeviceRepository = $this->createMock(AutoJsDeviceRepository::class);
        $this->heartbeatService = $this->createMock(DeviceHeartbeatService::class);

        $this->service = new DeviceQueryService(
            $this->autoJsDeviceRepository,
            $this->heartbeatService
        );
    }

    #[Test]
    public function testGetOnlineDevicesWithMixedDevicesShouldReturnOnlyOnlineDevices(): void
    {
        // Arrange
        $device1 = $this->createAutoJsDevice('DEVICE_1');
        $device2 = $this->createAutoJsDevice('DEVICE_2');
        $device3 = $this->createAutoJsDevice('DEVICE_3');
        $device4 = $this->createDeviceWithoutBaseDevice(); // Device without base device

        $allDevices = [$device1, $device2, $device3, $device4];

        $this->autoJsDeviceRepository->expects($this->once())
            ->method('findAll')
            ->willReturn($allDevices)
        ;

        $this->heartbeatService->expects($this->exactly(3))
            ->method('isDeviceOnline')
            ->willReturnMap([
                ['DEVICE_1', true],
                ['DEVICE_2', false],
                ['DEVICE_3', true],
            ])
        ;

        // Act
        $result = $this->service->getOnlineDevices(1, 20);

        // Assert
        $this->assertArrayHasKey('devices', $result);
        $this->assertArrayHasKey('pagination', $result);
        $devices = $result['devices'];
        $pagination = $result['pagination'];
        $this->assertIsArray($devices);
        $this->assertIsArray($pagination);
        $this->assertCount(2, $devices);
        $this->assertEquals(2, $pagination['total']);
        $this->assertEquals(1, $pagination['page']);
        $this->assertEquals(20, $pagination['limit']);
        $this->assertEquals(1, $pagination['totalPages']);
        $this->assertContains($device1, $devices);
        $this->assertContains($device3, $devices);
    }

    #[Test]
    public function testGetOnlineDevicesWithPaginationShouldReturnCorrectSlice(): void
    {
        // Arrange
        $onlineDevices = [];
        for ($i = 1; $i <= 25; ++$i) {
            $onlineDevices[] = $this->createAutoJsDevice("DEVICE_{$i}");
        }

        $this->autoJsDeviceRepository->expects($this->once())
            ->method('findAll')
            ->willReturn($onlineDevices)
        ;

        // All devices are online
        $this->heartbeatService->expects($this->exactly(25))
            ->method('isDeviceOnline')
            ->willReturn(true)
        ;

        // Act - Get page 2 with limit 10
        $result = $this->service->getOnlineDevices(2, 10);

        // Assert
        $devices = $result['devices'];
        $pagination = $result['pagination'];
        $this->assertIsArray($devices);
        $this->assertIsArray($pagination);
        $this->assertCount(10, $devices);
        $this->assertEquals(25, $pagination['total']);
        $this->assertEquals(2, $pagination['page']);
        $this->assertEquals(10, $pagination['limit']);
        $this->assertEquals(3, $pagination['totalPages']); // ceil(25/10)
    }

    #[Test]
    public function testGetOnlineDevicesWithNoDevicesShouldReturnEmptyResult(): void
    {
        // Arrange
        $this->autoJsDeviceRepository->expects($this->once())
            ->method('findAll')
            ->willReturn([])
        ;

        $this->heartbeatService->expects($this->never())
            ->method('isDeviceOnline')
        ;

        // Act
        $result = $this->service->getOnlineDevices();

        // Assert
        $devices = $result['devices'];
        $pagination = $result['pagination'];
        $this->assertIsArray($devices);
        $this->assertIsArray($pagination);
        $this->assertEmpty($devices);
        $this->assertEquals(0, $pagination['total']);
    }

    #[Test]
    public function testGetDevicesStatusWithValidDeviceCodesShouldReturnStatusMap(): void
    {
        // Arrange
        $deviceCodes = ['DEVICE_1', 'DEVICE_2', 'DEVICE_3'];

        $device1 = $this->createAutoJsDevice('DEVICE_1');
        $this->setDeviceId($device1, 1);
        $device1->getBaseDevice()?->setName('Test Device 1');
        $device1->getBaseDevice()?->setLastOnlineTime(new \DateTimeImmutable('2024-01-01 12:00:00'));

        $device2 = $this->createAutoJsDevice('DEVICE_2');
        $this->setDeviceId($device2, 2);
        $device2->getBaseDevice()?->setName('Test Device 2');

        $device3 = $this->createAutoJsDevice('DEVICE_3');
        $this->setDeviceId($device3, 3);
        $device3->getBaseDevice()?->setName('Test Device 3');

        $this->autoJsDeviceRepository->expects($this->exactly(3))
            ->method('findByDeviceCode')
            ->willReturnMap([
                ['DEVICE_1', $device1],
                ['DEVICE_2', $device2],
                ['DEVICE_3', $device3],
            ])
        ;

        $this->heartbeatService->expects($this->exactly(3))
            ->method('isDeviceOnline')
            ->willReturnMap([
                ['DEVICE_1', true],
                ['DEVICE_2', false],
                ['DEVICE_3', true],
            ])
        ;

        $this->heartbeatService->expects($this->exactly(3))
            ->method('getDeviceMetrics')
            ->willReturnMap([
                ['DEVICE_1', ['cpuUsage' => 45.5, 'memoryUsage' => 60.0]],
                ['DEVICE_2', []],
                ['DEVICE_3', ['cpuUsage' => 30.0, 'memoryUsage' => 40.0]],
            ])
        ;

        // Act
        $result = $this->service->getDevicesStatus($deviceCodes);

        // Assert
        $this->assertArrayHasKey('DEVICE_1', $result);
        $this->assertArrayHasKey('DEVICE_2', $result);
        $this->assertArrayHasKey('DEVICE_3', $result);

        // Check DEVICE_1
        $this->assertEquals(1, $result['DEVICE_1']['id']);
        $this->assertEquals('Test Device 1', $result['DEVICE_1']['name']);
        $this->assertTrue($result['DEVICE_1']['online']);
        $this->assertEquals('2024-01-01T12:00:00+00:00', $result['DEVICE_1']['lastOnlineTime']);
        $this->assertEquals(['cpuUsage' => 45.5, 'memoryUsage' => 60.0], $result['DEVICE_1']['metrics']);

        // Check DEVICE_2
        $this->assertEquals(2, $result['DEVICE_2']['id']);
        $this->assertEquals('Test Device 2', $result['DEVICE_2']['name']);
        $this->assertFalse($result['DEVICE_2']['online']);
        $this->assertEmpty($result['DEVICE_2']['metrics']);
    }

    #[Test]
    public function testGetDevicesStatusWithNonExistentDeviceShouldReturnErrorStatus(): void
    {
        // Arrange
        $deviceCodes = ['NON_EXISTENT'];

        $this->autoJsDeviceRepository->expects($this->once())
            ->method('findByDeviceCode')
            ->with('NON_EXISTENT')
            ->willReturn(null)
        ;

        $this->heartbeatService->expects($this->never())
            ->method('isDeviceOnline')
        ;

        // Act
        $result = $this->service->getDevicesStatus($deviceCodes);

        // Assert
        $this->assertArrayHasKey('NON_EXISTENT', $result);
        $this->assertEquals(['error' => '设备不存在'], $result['NON_EXISTENT']);
    }

    #[Test]
    public function testGetDevicesStatusWithDeviceMissingBaseDeviceShouldReturnErrorStatus(): void
    {
        // Arrange
        $deviceCodes = ['BROKEN_DEVICE'];
        $brokenDevice = $this->createDeviceWithoutBaseDevice();

        $this->autoJsDeviceRepository->expects($this->once())
            ->method('findByDeviceCode')
            ->with('BROKEN_DEVICE')
            ->willReturn($brokenDevice)
        ;

        // Act
        $result = $this->service->getDevicesStatus($deviceCodes);

        // Assert
        $this->assertArrayHasKey('BROKEN_DEVICE', $result);
        $this->assertEquals(['error' => '设备基础信息丢失'], $result['BROKEN_DEVICE']);
    }

    #[Test]
    public function testGetDeviceStatisticsShouldReturnCorrectStatistics(): void
    {
        // Arrange
        $device1 = $this->createAutoJsDevice('DEVICE_1', 'Samsung', 'Android 11');
        $device2 = $this->createAutoJsDevice('DEVICE_2', 'Xiaomi', 'Android 12');
        $device3 = $this->createAutoJsDevice('DEVICE_3', 'Samsung', 'Android 11');
        $device4 = $this->createAutoJsDevice('DEVICE_4', null, null); // Unknown brand and OS
        $device5 = $this->createDeviceWithoutBaseDevice(); // Device without base device

        $allDevices = [$device1, $device2, $device3, $device4, $device5];

        $this->autoJsDeviceRepository->expects($this->once())
            ->method('findAll')
            ->willReturn($allDevices)
        ;

        $this->heartbeatService->expects($this->exactly(4))
            ->method('isDeviceOnline')
            ->willReturnMap([
                ['DEVICE_1', true],
                ['DEVICE_2', true],
                ['DEVICE_3', false],
                ['DEVICE_4', false],
            ])
        ;

        // Act
        $stats = $this->service->getDeviceStatistics();

        // Assert
        $this->assertIsArray($stats);
        $this->assertEquals(5, $stats['total']);
        $this->assertEquals(2, $stats['online']);
        $this->assertEquals(2, $stats['offline']);
        $this->assertEquals(0, $stats['maintenance']);

        // Brand statistics
        $byBrand = $stats['byBrand'];
        $this->assertIsArray($byBrand);
        $this->assertEquals(2, $byBrand['Samsung']);
        $this->assertEquals(1, $byBrand['Xiaomi']);
        $this->assertEquals(1, $byBrand['Unknown']);

        // OS version statistics
        $byOsVersion = $stats['byOsVersion'];
        $this->assertIsArray($byOsVersion);
        $this->assertEquals(2, $byOsVersion['Android 11']);
        $this->assertEquals(1, $byOsVersion['Android 12']);
        $this->assertEquals(1, $byOsVersion['Unknown']);
    }

    #[Test]
    public function testGetDeviceStatisticsWithNoDevicesShouldReturnEmptyStats(): void
    {
        // Arrange
        $this->autoJsDeviceRepository->expects($this->once())
            ->method('findAll')
            ->willReturn([])
        ;

        $this->heartbeatService->expects($this->never())
            ->method('isDeviceOnline')
        ;

        // Act
        $stats = $this->service->getDeviceStatistics();

        // Assert
        $this->assertEquals(0, $stats['total']);
        $this->assertEquals(0, $stats['online']);
        $this->assertEquals(0, $stats['offline']);
        $this->assertEquals(0, $stats['maintenance']);
        $this->assertEmpty($stats['byBrand']);
        $this->assertEmpty($stats['byOsVersion']);
    }

    private function createAutoJsDevice(string $code, ?string $brand = null, ?string $osVersion = null): AutoJsDevice
    {
        $baseDevice = new BaseDevice();
        $baseDevice->setCode($code);

        if (null !== $brand) {
            $baseDevice->setBrand($brand);
        }

        if (null !== $osVersion) {
            $baseDevice->setOsVersion($osVersion);
        }

        $autoJsDevice = new AutoJsDevice();
        $autoJsDevice->setBaseDevice($baseDevice);

        return $autoJsDevice;
    }

    private function createDeviceWithoutBaseDevice(): AutoJsDevice
    {
        return new AutoJsDevice();
    }

    private function setDeviceId(AutoJsDevice $device, int $id): void
    {
        $reflection = new \ReflectionClass($device);
        $property = $reflection->getProperty('id');
        $property->setAccessible(true);
        $property->setValue($device, $id);
    }
}
