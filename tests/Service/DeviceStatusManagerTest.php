<?php

declare(strict_types=1);

namespace Tourze\AutoJsControlBundle\Tests\Service;

use DeviceBundle\Entity\Device as BaseDevice;
use DeviceBundle\Enum\DeviceStatus;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Tourze\AutoJsControlBundle\Entity\AutoJsDevice;
use Tourze\AutoJsControlBundle\Event\DeviceStatusChangedEvent;
use Tourze\AutoJsControlBundle\Exception\BusinessLogicException;
use Tourze\AutoJsControlBundle\Service\CacheStorageService;
use Tourze\AutoJsControlBundle\Service\DeviceHeartbeatService;
use Tourze\AutoJsControlBundle\Service\DeviceStatusManager;

/**
 * @internal
 */
#[CoversClass(DeviceStatusManager::class)]
final class DeviceStatusManagerTest extends TestCase
{
    private DeviceStatusManager $manager;

    private EntityManagerInterface&MockObject $entityManager;

    private DeviceHeartbeatService&MockObject $heartbeatService;

    private EventDispatcherInterface&MockObject $eventDispatcher;

    private LoggerInterface&MockObject $logger;

    private CacheStorageService&MockObject $cacheStorage;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->heartbeatService = $this->createMock(DeviceHeartbeatService::class);
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->cacheStorage = $this->createMock(CacheStorageService::class);

        $this->manager = new DeviceStatusManager(
            $this->entityManager,
            $this->heartbeatService,
            $this->eventDispatcher,
            $this->logger,
            $this->cacheStorage
        );
    }

    #[Test]
    public function testUpdateStatusFromOnlineToOfflineShouldMarkOfflineAndDispatchEvent(): void
    {
        // Arrange
        $device = $this->createAutoJsDeviceWithStatus('TEST_DEVICE', DeviceStatus::ONLINE);
        $baseDevice = $device->getBaseDevice();
        $this->assertNotNull($baseDevice);

        $this->heartbeatService->expects($this->once())
            ->method('markDeviceOffline')
            ->with('TEST_DEVICE')
        ;

        $this->entityManager->expects($this->once())
            ->method('flush')
        ;

        $this->eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with(self::callback(function (DeviceStatusChangedEvent $event) use ($device) {
                $previousOnline = DeviceStatus::ONLINE === $event->getPreviousStatus() || true === $event->getPreviousStatus();
                $currentOnline = DeviceStatus::ONLINE === $event->getCurrentStatus() || true === $event->getCurrentStatus();

                return $event->getDevice() === $device
                    && true === $previousOnline
                    && false === $currentOnline;
            }))
        ;

        $this->logger->expects($this->once())
            ->method('info')
            ->with('设备状态已更新', self::callback(function (mixed $context): bool {
                if (!is_array($context)) {
                    return false;
                }

                return 'TEST_DEVICE' === ($context['deviceCode'] ?? null)
                    && ($context['oldStatus'] ?? null) === DeviceStatus::ONLINE->value
                    && ($context['newStatus'] ?? null) === DeviceStatus::OFFLINE->value;
            }))
        ;

        // Act
        $this->manager->updateStatus($device, DeviceStatus::OFFLINE);

        // Assert
        $this->assertEquals(DeviceStatus::OFFLINE, $baseDevice->getStatus());
    }

    #[Test]
    public function testUpdateStatusFromOfflineToOnlineShouldNotMarkOfflineButDispatchEvent(): void
    {
        // Arrange
        $device = $this->createAutoJsDeviceWithStatus('TEST_DEVICE', DeviceStatus::OFFLINE);
        $baseDevice = $device->getBaseDevice();
        $this->assertNotNull($baseDevice);

        $this->heartbeatService->expects($this->never())
            ->method('markDeviceOffline')
        ;

        $this->entityManager->expects($this->once())
            ->method('flush')
        ;

        $this->eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with(self::callback(function (DeviceStatusChangedEvent $event) use ($device) {
                $previousOnline = DeviceStatus::ONLINE === $event->getPreviousStatus() || true === $event->getPreviousStatus();
                $currentOnline = DeviceStatus::ONLINE === $event->getCurrentStatus() || true === $event->getCurrentStatus();

                return $event->getDevice() === $device
                    && false === $previousOnline
                    && true === $currentOnline;
            }))
        ;

        $this->logger->expects($this->once())
            ->method('info')
            ->with('设备状态已更新', self::callback(function (mixed $context): bool {
                if (!is_array($context)) {
                    return false;
                }

                return 'TEST_DEVICE' === ($context['deviceCode'] ?? null)
                    && ($context['oldStatus'] ?? null) === DeviceStatus::OFFLINE->value
                    && ($context['newStatus'] ?? null) === DeviceStatus::ONLINE->value;
            }))
        ;

        // Act
        $this->manager->updateStatus($device, DeviceStatus::ONLINE);

        // Assert
        $this->assertEquals(DeviceStatus::ONLINE, $baseDevice->getStatus());
    }

    #[Test]
    public function testUpdateStatusWithSameStatusShouldNotDispatchEvent(): void
    {
        // Arrange
        $device = $this->createAutoJsDeviceWithStatus('TEST_DEVICE', DeviceStatus::ONLINE);

        $this->heartbeatService->expects($this->never())
            ->method('markDeviceOffline')
        ;

        $this->entityManager->expects($this->once())
            ->method('flush')
        ;

        // No event should be dispatched when status doesn't change
        $this->eventDispatcher->expects($this->never())
            ->method('dispatch')
        ;

        $this->logger->expects($this->once())
            ->method('info')
            ->with('设备状态已更新', self::callback(function (mixed $context): bool {
                if (!is_array($context)) {
                    return false;
                }

                return 'TEST_DEVICE' === ($context['deviceCode'] ?? null)
                    && ($context['oldStatus'] ?? null) === DeviceStatus::ONLINE->value
                    && ($context['newStatus'] ?? null) === DeviceStatus::ONLINE->value;
            }))
        ;

        // Act
        $this->manager->updateStatus($device, DeviceStatus::ONLINE);

        // Assert
        $baseDevice = $device->getBaseDevice();
        $this->assertNotNull($baseDevice);
        $this->assertEquals(DeviceStatus::ONLINE, $baseDevice->getStatus());
    }

    #[Test]
    public function testUpdateStatusFromDisabledToOfflineShouldMarkOfflineWithoutEvent(): void
    {
        // Arrange
        $device = $this->createAutoJsDeviceWithStatus('TEST_DEVICE', DeviceStatus::DISABLED);
        $baseDevice = $device->getBaseDevice();
        $this->assertNotNull($baseDevice);

        $this->heartbeatService->expects($this->once())
            ->method('markDeviceOffline')
            ->with('TEST_DEVICE')
        ;

        $this->entityManager->expects($this->once())
            ->method('flush')
        ;

        // No event should be dispatched as neither old nor new status is ONLINE
        $this->eventDispatcher->expects($this->never())
            ->method('dispatch')
        ;

        $this->logger->expects($this->once())
            ->method('info')
            ->with('设备状态已更新', self::callback(function (mixed $context): bool {
                if (!is_array($context)) {
                    return false;
                }

                return 'TEST_DEVICE' === ($context['deviceCode'] ?? null)
                    && ($context['oldStatus'] ?? null) === DeviceStatus::DISABLED->value
                    && ($context['newStatus'] ?? null) === DeviceStatus::OFFLINE->value;
            }))
        ;

        // Act
        $this->manager->updateStatus($device, DeviceStatus::OFFLINE);

        // Assert
        $this->assertEquals(DeviceStatus::OFFLINE, $baseDevice->getStatus());
    }

    #[Test]
    public function testUpdateStatusWithMissingBaseDeviceShouldThrowException(): void
    {
        // Arrange
        $device = new AutoJsDevice();

        // Act & Assert
        $this->expectException(BusinessLogicException::class);
        $this->expectExceptionMessage('设备基础信息丢失');

        $this->manager->updateStatus($device, DeviceStatus::OFFLINE);
    }

    #[Test]
    public function testMarkAsDeletedWithValidDeviceShouldSetDisabledAndClearCache(): void
    {
        // Arrange
        $device = $this->createAutoJsDeviceWithStatus('DELETE_DEVICE', DeviceStatus::ONLINE);
        $baseDevice = $device->getBaseDevice();
        $this->assertNotNull($baseDevice);

        $this->cacheStorage->expects($this->once())
            ->method('clearDeviceData')
            ->with('DELETE_DEVICE')
        ;

        $this->entityManager->expects($this->once())
            ->method('flush')
        ;

        // Act
        $this->manager->markAsDeleted($device);

        // Assert
        $this->assertEquals(DeviceStatus::DISABLED, $baseDevice->getStatus());
    }

    #[Test]
    public function testMarkAsDeletedWithMissingBaseDeviceShouldThrowException(): void
    {
        // Arrange
        $device = new AutoJsDevice();

        $this->cacheStorage->expects($this->never())
            ->method('clearDeviceData')
        ;

        $this->entityManager->expects($this->never())
            ->method('flush')
        ;

        // Act & Assert
        $this->expectException(BusinessLogicException::class);
        $this->expectExceptionMessage('设备基础信息丢失');

        $this->manager->markAsDeleted($device);
    }

    #[Test]
    public function testUpdateStatusToDisabledShouldMarkOfflineAndLogCorrectly(): void
    {
        // Arrange
        $device = $this->createAutoJsDeviceWithStatus('DISABLED_DEVICE', DeviceStatus::ONLINE);
        $baseDevice = $device->getBaseDevice();
        $this->assertNotNull($baseDevice);

        $this->heartbeatService->expects($this->never())
            ->method('markDeviceOffline')
        ;

        $this->entityManager->expects($this->once())
            ->method('flush')
        ;

        $this->eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with(self::callback(function (DeviceStatusChangedEvent $event) use ($device) {
                $previousOnline = DeviceStatus::ONLINE === $event->getPreviousStatus() || true === $event->getPreviousStatus();
                $currentOnline = DeviceStatus::ONLINE === $event->getCurrentStatus() || true === $event->getCurrentStatus();

                return $event->getDevice() === $device
                    && true === $previousOnline
                    && false === $currentOnline;
            }))
        ;

        $this->logger->expects($this->once())
            ->method('info')
            ->with('设备状态已更新', self::callback(function (mixed $context): bool {
                if (!is_array($context)) {
                    return false;
                }

                return 'DISABLED_DEVICE' === ($context['deviceCode'] ?? null)
                    && ($context['oldStatus'] ?? null) === DeviceStatus::ONLINE->value
                    && ($context['newStatus'] ?? null) === DeviceStatus::DISABLED->value;
            }))
        ;

        // Act
        $this->manager->updateStatus($device, DeviceStatus::DISABLED);

        // Assert
        $this->assertEquals(DeviceStatus::DISABLED, $baseDevice->getStatus());
    }

    #[Test]
    public function testUpdateStatusFromDisabledToOnlineShouldDispatchOnlineEvent(): void
    {
        // Arrange
        $device = $this->createAutoJsDeviceWithStatus('DISABLED_DEVICE', DeviceStatus::DISABLED);
        $baseDevice = $device->getBaseDevice();
        $this->assertNotNull($baseDevice);

        $this->heartbeatService->expects($this->never())
            ->method('markDeviceOffline')
        ;

        $this->entityManager->expects($this->once())
            ->method('flush')
        ;

        $this->eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with(self::callback(function (DeviceStatusChangedEvent $event) use ($device) {
                $previousOnline = DeviceStatus::ONLINE === $event->getPreviousStatus() || true === $event->getPreviousStatus();
                $currentOnline = DeviceStatus::ONLINE === $event->getCurrentStatus() || true === $event->getCurrentStatus();

                return $event->getDevice() === $device
                    && false === $previousOnline
                    && true === $currentOnline;
            }))
        ;

        $this->logger->expects($this->once())
            ->method('info')
            ->with('设备状态已更新', self::callback(function (mixed $context): bool {
                if (!is_array($context)) {
                    return false;
                }

                return 'DISABLED_DEVICE' === ($context['deviceCode'] ?? null)
                    && ($context['oldStatus'] ?? null) === DeviceStatus::DISABLED->value
                    && ($context['newStatus'] ?? null) === DeviceStatus::ONLINE->value;
            }))
        ;

        // Act
        $this->manager->updateStatus($device, DeviceStatus::ONLINE);

        // Assert
        $this->assertEquals(DeviceStatus::ONLINE, $baseDevice->getStatus());
    }

    private function createAutoJsDeviceWithStatus(string $code, DeviceStatus $status): AutoJsDevice
    {
        $baseDevice = new BaseDevice();
        $baseDevice->setCode($code);
        $baseDevice->setStatus($status);

        $autoJsDevice = new AutoJsDevice();
        $autoJsDevice->setBaseDevice($baseDevice);

        return $autoJsDevice;
    }
}
