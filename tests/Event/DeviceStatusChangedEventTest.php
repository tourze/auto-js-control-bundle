<?php

declare(strict_types=1);

namespace Tourze\AutoJsControlBundle\Tests\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Test;
use Tourze\AutoJsControlBundle\Entity\AutoJsDevice;
use Tourze\AutoJsControlBundle\Event\DeviceStatusChangedEvent;
use Tourze\PHPUnitSymfonyUnitTest\AbstractEventTestCase;

/**
 * @internal
 */
#[CoversClass(DeviceStatusChangedEvent::class)]
final class DeviceStatusChangedEventTest extends AbstractEventTestCase
{
    #[Test]
    public function constructorSetsProperties(): void
    {
        // Arrange
        $device = new AutoJsDevice();
        $previousOnline = false; // was offline
        $currentOnline = true;   // now online
        $statusChangedTime = new \DateTime();

        // Act
        $event = new DeviceStatusChangedEvent($device, $previousOnline, $currentOnline, $statusChangedTime);

        // Assert
        $this->assertSame($device, $event->getDevice());
        $this->assertEquals($previousOnline, $event->getPreviousStatus());
        $this->assertEquals($currentOnline, $event->getCurrentStatus());
        $this->assertSame($statusChangedTime, $event->getStatusChangedTime());
    }

    #[Test]
    public function isOnlineReturnsCorrectValue(): void
    {
        $device = new AutoJsDevice();

        // Test offline to online (becomes online)
        $event1 = new DeviceStatusChangedEvent($device, false, true);
        $this->assertTrue($event1->isOnline());

        // Test online to offline (not becomes online)
        $event2 = new DeviceStatusChangedEvent($device, true, false);
        $this->assertFalse($event2->isOnline());

        // Test offline to offline (no change)
        $event3 = new DeviceStatusChangedEvent($device, false, false);
        $this->assertFalse($event3->isOnline());

        // Test online to online (no change)
        $event4 = new DeviceStatusChangedEvent($device, true, true);
        $this->assertFalse($event4->isOnline());
    }

    #[Test]
    public function isOfflineReturnsCorrectValue(): void
    {
        $device = new AutoJsDevice();

        // Test online to offline (becomes offline)
        $event1 = new DeviceStatusChangedEvent($device, true, false);
        $this->assertTrue($event1->isOffline());

        // Test offline to online (not becomes offline)
        $event2 = new DeviceStatusChangedEvent($device, false, true);
        $this->assertFalse($event2->isOffline());

        // Test offline to offline (no change)
        $event3 = new DeviceStatusChangedEvent($device, false, false);
        $this->assertFalse($event3->isOffline());

        // Test online to online (no change)
        $event4 = new DeviceStatusChangedEvent($device, true, true);
        $this->assertFalse($event4->isOffline());
    }

    #[Test]
    public function staticFactoryMethodsWork(): void
    {
        $device = new AutoJsDevice();
        $timestamp = new \DateTime();

        // Test online factory method
        $onlineEvent = DeviceStatusChangedEvent::online($device, $timestamp);
        $this->assertSame($device, $onlineEvent->getDevice());
        $this->assertTrue($onlineEvent->isOnline());
        $this->assertFalse($onlineEvent->getPreviousStatus());
        $this->assertTrue($onlineEvent->getCurrentStatus());
        $this->assertSame($timestamp, $onlineEvent->getStatusChangedTime());

        // Test offline factory method
        $offlineEvent = DeviceStatusChangedEvent::offline($device, $timestamp);
        $this->assertSame($device, $offlineEvent->getDevice());
        $this->assertTrue($offlineEvent->isOffline());
        $this->assertTrue($offlineEvent->getPreviousStatus());
        $this->assertFalse($offlineEvent->getCurrentStatus());
        $this->assertSame($timestamp, $offlineEvent->getStatusChangedTime());
    }

    #[Test]
    public function statusChangedTimeIsOptional(): void
    {
        $device = new AutoJsDevice();

        // Without timestamp
        $eventWithoutTimestamp = new DeviceStatusChangedEvent($device, false, true);
        $this->assertNull($eventWithoutTimestamp->getStatusChangedTime());

        // With timestamp
        $timestamp = new \DateTime();
        $eventWithTimestamp = new DeviceStatusChangedEvent($device, false, true, $timestamp);
        $this->assertSame($timestamp, $eventWithTimestamp->getStatusChangedTime());
    }

    #[Test]
    public function getDeviceReturnsCorrectDevice(): void
    {
        $device = new AutoJsDevice();
        $event = new DeviceStatusChangedEvent($device, false, true);

        $this->assertSame($device, $event->getDevice());
    }

    #[Test]
    public function testToArray(): void
    {
        // Arrange
        $device = new AutoJsDevice();
        $reflection = new \ReflectionClass($device);
        $property = $reflection->getProperty('id');
        $property->setAccessible(true);
        $property->setValue($device, 123);

        $timestamp = new \DateTime('2024-01-15 10:30:00');

        // Test with timestamp
        $event = new DeviceStatusChangedEvent($device, false, true, $timestamp);
        $array = $event->toArray();

        $this->assertEquals([
            'deviceId' => 123,
            'oldStatus' => 'offline',
            'newStatus' => 'online',
            'timestamp' => '2024-01-15 10:30:00',
        ], $array);

        // Test without timestamp (should use current time)
        $eventWithoutTimestamp = new DeviceStatusChangedEvent($device, true, false);
        $arrayWithoutTimestamp = $eventWithoutTimestamp->toArray();

        $this->assertEquals(123, $arrayWithoutTimestamp['deviceId']);
        $this->assertEquals('online', $arrayWithoutTimestamp['oldStatus']);
        $this->assertEquals('offline', $arrayWithoutTimestamp['newStatus']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $arrayWithoutTimestamp['timestamp']);
    }
}
