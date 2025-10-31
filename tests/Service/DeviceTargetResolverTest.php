<?php

declare(strict_types=1);

namespace Tourze\AutoJsControlBundle\Tests\Service;

use DeviceBundle\Entity\Device as BaseDevice;
use DeviceBundle\Enum\DeviceStatus;
use DeviceBundle\Enum\DeviceType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Test;
use Tourze\AutoJsControlBundle\Entity\AutoJsDevice;
use Tourze\AutoJsControlBundle\Entity\DeviceGroup;
use Tourze\AutoJsControlBundle\Entity\Task;
use Tourze\AutoJsControlBundle\Enum\TaskTargetType;
use Tourze\AutoJsControlBundle\Enum\TaskType;
use Tourze\AutoJsControlBundle\Exception\InvalidTaskArgumentException;
use Tourze\AutoJsControlBundle\Service\DeviceTargetResolver;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(DeviceTargetResolver::class)]
#[RunTestsInSeparateProcesses]
final class DeviceTargetResolverTest extends AbstractIntegrationTestCase
{
    private DeviceTargetResolver $deviceTargetResolver;

    protected function onSetUp(): void
    {
        $this->deviceTargetResolver = self::getService(DeviceTargetResolver::class);
    }

    #[Test]
    public function testResolveTargetDevicesWithSpecificDevices(): void
    {
        // Arrange
        $device1 = $this->createTestDevice('DEVICE_1');
        $device2 = $this->createTestDevice('DEVICE_2');

        $task = new Task();
        $task->setName('Test Task');
        $task->setTaskType(TaskType::IMMEDIATE);
        $task->setTargetType(TaskTargetType::SPECIFIC);

        $data = [
            'targetDevices' => [$device1->getId(), $device2->getId()],
        ];

        // Act
        $this->deviceTargetResolver->resolveTargetDevices($task, $data);

        // Assert
        $targetDeviceIds = json_decode($task->getTargetDeviceIds() ?? '[]', true);
        $this->assertIsArray($targetDeviceIds);
        $this->assertContains($device1->getId(), $targetDeviceIds);
        $this->assertContains($device2->getId(), $targetDeviceIds);
    }

    #[Test]
    public function testResolveSpecificDevicesWithoutTargetDevicesThrowsException(): void
    {
        // Arrange
        $task = new Task();
        $task->setTaskType(TaskType::IMMEDIATE);
        $task->setTargetType(TaskTargetType::SPECIFIC);

        $data = []; // No targetDevices

        // Assert
        $this->expectException(InvalidTaskArgumentException::class);
        $this->expectExceptionMessage('必须指定目标设备列表');

        // Act
        $this->deviceTargetResolver->resolveTargetDevices($task, $data);
    }

    #[Test]
    public function testResolveGroupDevicesWithValidGroup(): void
    {
        // Arrange
        $group = $this->createTestDeviceGroup();
        $task = new Task();
        $task->setTaskType(TaskType::IMMEDIATE);
        $task->setTargetType(TaskTargetType::GROUP);

        $data = [
            'targetGroupId' => $group->getId(),
        ];

        // Act
        $this->deviceTargetResolver->resolveTargetDevices($task, $data);

        // Assert
        $this->assertEquals($group, $task->getTargetGroup());
    }

    #[Test]
    public function testResolveGroupDevicesWithInvalidGroupThrowsException(): void
    {
        // Arrange
        $task = new Task();
        $task->setTaskType(TaskType::IMMEDIATE);
        $task->setTargetType(TaskTargetType::GROUP);

        $data = [
            'targetGroupId' => 999999, // Non-existent group ID
        ];

        // Assert
        $this->expectException(InvalidTaskArgumentException::class);
        $this->expectExceptionMessage('设备组不存在');

        // Act
        $this->deviceTargetResolver->resolveTargetDevices($task, $data);
    }

    #[Test]
    public function testGetTargetDevicesForSpecificType(): void
    {
        // Arrange
        $device1 = $this->createTestDevice('SPECIFIC_1');
        $device2 = $this->createTestDevice('SPECIFIC_2');
        $this->createTestDevice('NOT_TARGET'); // Should not be included

        $task = new Task();
        $task->setTargetType(TaskTargetType::SPECIFIC);
        $deviceIds = [$device1->getId(), $device2->getId()];
        $task->setTargetDeviceIds(false !== json_encode($deviceIds) ? json_encode($deviceIds) : null);

        // Act
        $devices = $this->deviceTargetResolver->getTargetDevices($task);

        // Assert
        $this->assertCount(2, $devices);
        $deviceIds = array_map(fn ($device) => $device->getId(), $devices);
        $this->assertContains($device1->getId(), $deviceIds);
        $this->assertContains($device2->getId(), $deviceIds);
    }

    #[Test]
    public function testGetTargetDevicesForGroupType(): void
    {
        // Arrange
        $group = $this->createTestDeviceGroup();
        $device1 = $this->createTestDevice('GROUP_1');
        $device2 = $this->createTestDevice('GROUP_2');
        $device1->setDeviceGroup($group);
        $device2->setDeviceGroup($group);
        self::getEntityManager()->flush();

        $task = new Task();
        $task->setTargetType(TaskTargetType::GROUP);
        $task->setTargetGroup($group);

        // Act
        $devices = $this->deviceTargetResolver->getTargetDevices($task);

        // Assert
        $this->assertCount(2, $devices);
    }

    #[Test]
    public function testGetTargetDevicesForAllType(): void
    {
        // Arrange
        $this->createTestDevice('ALL_1');
        $this->createTestDevice('ALL_2');

        $task = new Task();
        $task->setTargetType(TaskTargetType::ALL);

        // Act
        $devices = $this->deviceTargetResolver->getTargetDevices($task);

        // Assert
        $this->assertGreaterThanOrEqual(2, count($devices));
    }

    private function createTestDevice(string $deviceCode): AutoJsDevice
    {
        $baseDevice = new BaseDevice();
        $baseDevice->setCode($deviceCode);
        $baseDevice->setName('Test Device ' . $deviceCode);
        $baseDevice->setDeviceType(DeviceType::PHONE);
        $baseDevice->setStatus(DeviceStatus::ONLINE);
        $baseDevice->setValid(true);

        $autoJsDevice = new AutoJsDevice();
        $autoJsDevice->setBaseDevice($baseDevice);
        $autoJsDevice->setAutoJsVersion('9.3.0');

        $em = self::getEntityManager();
        $em->persist($baseDevice);
        $em->persist($autoJsDevice);
        $em->flush();

        return $autoJsDevice;
    }

    private function createTestDeviceGroup(): DeviceGroup
    {
        $group = new DeviceGroup();
        $group->setName('Test Group');
        $group->setDescription('Test device group');

        $em = self::getEntityManager();
        $em->persist($group);
        $em->flush();

        return $group;
    }
}
