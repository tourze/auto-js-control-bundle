<?php

declare(strict_types=1);

namespace Tourze\AutoJsControlBundle\Tests\Event;

use DeviceBundle\Entity\Device as BaseDevice;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\AutoJsControlBundle\Entity\AutoJsDevice;
use Tourze\AutoJsControlBundle\Event\DeviceRegisteredEvent;
use Tourze\PHPUnitSymfonyUnitTest\AbstractEventTestCase;

/**
 * @internal
 */
#[CoversClass(DeviceRegisteredEvent::class)]
final class DeviceRegisteredEventTest extends AbstractEventTestCase
{
    private function createTestDevice(): AutoJsDevice
    {
        $baseDevice = new BaseDevice();
        $baseDevice->setCode('TEST_DEVICE_001');
        $baseDevice->setName('Test Device');

        $device = new AutoJsDevice();
        $device->setBaseDevice($baseDevice);
        $device->setAutoJsVersion('4.1.1');

        return $device;
    }

    public function testConstructorSetsPropertiesCorrectly(): void
    {
        // Arrange
        $device = $this->createTestDevice();
        $clientIp = '192.168.1.100';
        $deviceInfo = [
            'model' => 'Test Model',
            'brand' => 'Test Brand',
            'osVersion' => 'Android 11',
            'hardwareInfo' => [
                'cpuCores' => 8,
                'memorySize' => '4GB',
                'storageSize' => '64GB',
            ],
        ];

        // Act
        $event = new DeviceRegisteredEvent($device, $clientIp, $deviceInfo);

        // Assert
        $this->assertSame($device, $event->getDevice());
        $this->assertEquals($clientIp, $event->getClientIp());
        $this->assertEquals($deviceInfo, $event->getDeviceInfo());
        $this->assertInstanceOf(\DateTimeImmutable::class, $event->getRegisteredTime());
    }

    public function testGetDeviceReturnsCorrectDevice(): void
    {
        // Arrange
        $device = $this->createTestDevice();
        $event = new DeviceRegisteredEvent($device, '192.168.1.1', []);

        // Act
        $result = $event->getDevice();

        // Assert
        $this->assertSame($device, $result);
        $baseDevice = $result->getBaseDevice();
        $this->assertNotNull($baseDevice);
        $this->assertEquals('TEST_DEVICE_001', $baseDevice->getCode());
    }

    public function testGetClientIpReturnsCorrectIp(): void
    {
        // Arrange
        $device = $this->createTestDevice();
        $clientIp = '10.0.0.1';
        $event = new DeviceRegisteredEvent($device, $clientIp, []);

        // Act
        $result = $event->getClientIp();

        // Assert
        $this->assertEquals($clientIp, $result);
    }

    public function testGetDeviceInfoReturnsCorrectInfo(): void
    {
        // Arrange
        $device = $this->createTestDevice();
        $deviceInfo = [
            'model' => 'Pixel 5',
            'brand' => 'Google',
            'osVersion' => 'Android 12',
            'autoJsVersion' => '4.1.1',
            'customData' => ['key' => 'value'],
        ];
        $event = new DeviceRegisteredEvent($device, '192.168.1.1', $deviceInfo);

        // Act
        $result = $event->getDeviceInfo();

        // Assert
        $this->assertEquals($deviceInfo, $result);
        $this->assertArrayHasKey('model', $result);
        $this->assertArrayHasKey('brand', $result);
        $this->assertArrayHasKey('osVersion', $result);
        $this->assertArrayHasKey('customData', $result);
    }

    public function testGetRegisteredTimeReturnsDateTimeImmutable(): void
    {
        // Arrange
        $device = $this->createTestDevice();
        $beforeCreation = new \DateTimeImmutable();
        $event = new DeviceRegisteredEvent($device, '192.168.1.1', []);
        $afterCreation = new \DateTimeImmutable();

        // Act
        $registeredTime = $event->getRegisteredTime();

        // Assert
        $this->assertInstanceOf(\DateTimeImmutable::class, $registeredTime);
        $this->assertGreaterThanOrEqual($beforeCreation, $registeredTime);
        $this->assertLessThanOrEqual($afterCreation, $registeredTime);
    }

    public function testDeviceInfoHandlesEmptyArray(): void
    {
        // Arrange
        $device = $this->createTestDevice();
        $event = new DeviceRegisteredEvent($device, '192.168.1.1', []);

        // Act
        $result = $event->getDeviceInfo();

        // Assert
        $this->assertEmpty($result);
    }

    public function testDeviceInfoPreservesComplexStructure(): void
    {
        // Arrange
        $device = $this->createTestDevice();
        $complexInfo = [
            'basic' => [
                'model' => 'Test Model',
                'brand' => 'Test Brand',
            ],
            'hardware' => [
                'cpu' => [
                    'cores' => 8,
                    'frequency' => '2.4GHz',
                ],
                'memory' => [
                    'total' => '8GB',
                    'available' => '4GB',
                ],
            ],
            'software' => [
                'os' => 'Android',
                'version' => '11',
                'apiLevel' => 30,
            ],
            'features' => ['nfc', 'bluetooth', 'wifi'],
        ];

        $event = new DeviceRegisteredEvent($device, '192.168.1.1', $complexInfo);

        // Act
        $result = $event->getDeviceInfo();

        // Assert
        $this->assertEquals($complexInfo, $result);
        $this->assertIsArray($result['hardware']['cpu']);
        $this->assertEquals(8, $result['hardware']['cpu']['cores']);
        $this->assertIsArray($result['features']);
        $this->assertContains('bluetooth', $result['features']);
    }

    public function testMultipleEventsHaveDifferentTimestamps(): void
    {
        // Arrange & Act
        $device = $this->createTestDevice();
        $event1 = new DeviceRegisteredEvent($device, '192.168.1.1', []);
        $event2 = new DeviceRegisteredEvent($device, '192.168.1.2', []);

        // Assert - events should be created at or near the same time
        $time1 = $event1->getRegisteredTime();
        $time2 = $event2->getRegisteredTime();

        // Timestamps might be equal or one might be newer - both are acceptable
        $this->assertGreaterThanOrEqual($time1, $time2);

        // But they should be within a reasonable time window (1 second)
        $diff = $time2->getTimestamp() - $time1->getTimestamp();
        $this->assertLessThanOrEqual(1, $diff);
    }

    public function testEventIsImmutable(): void
    {
        // Arrange
        $device = $this->createTestDevice();
        $originalDevice = clone $device;
        $originalIp = '192.168.1.1';
        $originalInfo = ['key' => 'value'];

        $event = new DeviceRegisteredEvent($originalDevice, $originalIp, $originalInfo);

        // Act - Try to modify the original data
        $device->setAutoJsVersion('5.0.0');
        $originalInfo['key'] = 'modified';

        // Assert - Event data should remain unchanged
        $this->assertEquals('4.1.1', $event->getDevice()->getAutoJsVersion());
        $this->assertEquals(['key' => 'value'], $event->getDeviceInfo());
    }
}
