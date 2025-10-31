<?php

declare(strict_types=1);

namespace Tourze\AutoJsControlBundle\Tests\Service;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\AutoJsControlBundle\Entity\DeviceGroup;
use Tourze\AutoJsControlBundle\Entity\Task;
use Tourze\AutoJsControlBundle\Enum\TaskTargetType;
use Tourze\AutoJsControlBundle\Exception\DeviceTargetException;
use Tourze\AutoJsControlBundle\Service\TaskTargetService;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(TaskTargetService::class)]
#[RunTestsInSeparateProcesses]
final class TaskTargetServiceTest extends AbstractIntegrationTestCase
{
    private TaskTargetService $taskTargetService;

    protected function onSetUp(): void
    {
        $this->taskTargetService = self::getService(TaskTargetService::class);
    }

    public function testServiceExists(): void
    {
        $this->assertInstanceOf(TaskTargetService::class, $this->taskTargetService);
    }

    public function testProcessTargetDevicesWithSpecificType(): void
    {
        // Arrange
        $task = new Task();
        $task->setTargetType(TaskTargetType::SPECIFIC);

        $data = [
            'targetDevices' => [1, 2, 3],
        ];

        // Act
        $this->taskTargetService->processTargetDevices($task, $data);

        // Assert
        $this->assertEquals(json_encode([1, 2, 3]), $task->getTargetDeviceIds());
        $this->assertNull($task->getTargetGroup());
    }

    public function testProcessTargetDevicesWithSpecificTypeButMissingDevices(): void
    {
        // Arrange
        $task = new Task();
        $task->setTargetType(TaskTargetType::SPECIFIC);

        $data = [];

        // Act & Assert
        $this->expectException(DeviceTargetException::class);
        $this->expectExceptionMessage('必须指定目标设备列表');

        $this->taskTargetService->processTargetDevices($task, $data);
    }

    public function testProcessTargetDevicesWithSpecificTypeButInvalidDevices(): void
    {
        // Arrange
        $task = new Task();
        $task->setTargetType(TaskTargetType::SPECIFIC);

        $data = [
            'targetDevices' => 'not_an_array',
        ];

        // Act & Assert
        $this->expectException(DeviceTargetException::class);
        $this->expectExceptionMessage('必须指定目标设备列表');

        $this->taskTargetService->processTargetDevices($task, $data);
    }

    public function testProcessTargetDevicesWithGroupType(): void
    {
        // Arrange - 创建实际的设备组数据
        $deviceGroup = new DeviceGroup();
        $deviceGroup->setName('Test Group');

        /** @var EntityManagerInterface $entityManager */
        $entityManager = self::getContainer()->get('doctrine.orm.entity_manager');
        $entityManager->persist($deviceGroup);
        $entityManager->flush();

        $task = new Task();
        $task->setTargetType(TaskTargetType::GROUP);

        $data = [
            'targetGroupId' => $deviceGroup->getId(),
        ];

        // Act
        $this->taskTargetService->processTargetDevices($task, $data);

        // Assert
        $this->assertEquals($deviceGroup, $task->getTargetGroup());
        $this->assertNull($task->getTargetDeviceIds());
    }

    public function testProcessTargetDevicesWithGroupTypeButMissingGroupId(): void
    {
        // Arrange
        $task = new Task();
        $task->setTargetType(TaskTargetType::GROUP);

        $data = [];

        // Act & Assert
        $this->expectException(DeviceTargetException::class);
        $this->expectExceptionMessage('必须指定目标设备组');

        $this->taskTargetService->processTargetDevices($task, $data);
    }

    public function testProcessTargetDevicesWithGroupTypeButGroupNotFound(): void
    {
        // Arrange
        $task = new Task();
        $task->setTargetType(TaskTargetType::GROUP);

        // 使用不存在的ID
        $data = [
            'targetGroupId' => 999999,
        ];

        // Act & Assert
        $this->expectException(DeviceTargetException::class);
        $this->expectExceptionMessage('设备组不存在');

        $this->taskTargetService->processTargetDevices($task, $data);
    }

    public function testProcessTargetDevicesWithAllType(): void
    {
        // Arrange
        $task = new Task();
        $task->setTargetType(TaskTargetType::ALL);

        // Set some initial values to verify they are cleared
        $task->setTargetDeviceIds('["1","2","3"]');
        $deviceGroup = new DeviceGroup();
        $task->setTargetGroup($deviceGroup);

        $data = [];

        // Act
        $this->taskTargetService->processTargetDevices($task, $data);

        // Assert
        $this->assertNull($task->getTargetDeviceIds());
        $this->assertNull($task->getTargetGroup());
    }

    public function testProcessTargetDevicesWithAllTypeIgnoresProvidedData(): void
    {
        // Arrange
        $task = new Task();
        $task->setTargetType(TaskTargetType::ALL);

        $data = [
            'targetDevices' => [1, 2, 3],
            'targetGroupId' => 1,
        ];

        // Act
        $this->taskTargetService->processTargetDevices($task, $data);

        // Assert - Should ignore the provided data
        $this->assertNull($task->getTargetDeviceIds());
        $this->assertNull($task->getTargetGroup());
    }

    public function testProcessTargetDevicesWithEmptySpecificDevicesArray(): void
    {
        // Arrange
        $task = new Task();
        $task->setTargetType(TaskTargetType::SPECIFIC);

        $data = [
            'targetDevices' => [],
        ];

        // Act
        $this->taskTargetService->processTargetDevices($task, $data);

        // Assert - Empty array is allowed, should set empty JSON array
        $this->assertEquals('[]', $task->getTargetDeviceIds());
        $this->assertNull($task->getTargetGroup());
    }

    public function testProcessTargetDevicesPreservesExistingTargetWhenNotUpdating(): void
    {
        // Arrange
        $task = new Task();
        $task->setTargetType(TaskTargetType::SPECIFIC);
        $task->setTargetDeviceIds('["existing","devices"]');

        $deviceGroup = new DeviceGroup();
        $task->setTargetGroup($deviceGroup);

        $data = [
            'targetDevices' => [1, 2, 3],
        ];

        // Act
        $this->taskTargetService->processTargetDevices($task, $data);

        // Assert
        $this->assertEquals(json_encode([1, 2, 3]), $task->getTargetDeviceIds());
        $this->assertNull($task->getTargetGroup()); // Should be cleared for SPECIFIC type
    }

    public function testProcessTargetDevicesWithStringDeviceIds(): void
    {
        // Arrange
        $task = new Task();
        $task->setTargetType(TaskTargetType::SPECIFIC);

        $data = [
            'targetDevices' => ['device1', 'device2', 'device3'],
        ];

        // Act
        $this->taskTargetService->processTargetDevices($task, $data);

        // Assert
        $this->assertEquals(json_encode(['device1', 'device2', 'device3']), $task->getTargetDeviceIds());
        $this->assertNull($task->getTargetGroup());
    }

    public function testProcessTargetDevicesWithMixedDeviceIds(): void
    {
        // Arrange
        $task = new Task();
        $task->setTargetType(TaskTargetType::SPECIFIC);

        $data = [
            'targetDevices' => [1, 'device2', 3],
        ];

        // Act
        $this->taskTargetService->processTargetDevices($task, $data);

        // Assert
        $this->assertEquals(json_encode([1, 'device2', 3]), $task->getTargetDeviceIds());
        $this->assertNull($task->getTargetGroup());
    }

    public function testProcessTargetDevicesWithZeroGroupId(): void
    {
        // Arrange
        $task = new Task();
        $task->setTargetType(TaskTargetType::GROUP);

        $data = [
            'targetGroupId' => 0,
        ];

        // Act & Assert
        $this->expectException(DeviceTargetException::class);
        $this->expectExceptionMessage('设备组不存在');

        $this->taskTargetService->processTargetDevices($task, $data);
    }

    public function testProcessTargetDevicesWithNullGroupId(): void
    {
        // Arrange
        $task = new Task();
        $task->setTargetType(TaskTargetType::GROUP);

        $data = [
            'targetGroupId' => null,
        ];

        // Act & Assert
        $this->expectException(DeviceTargetException::class);
        $this->expectExceptionMessage('必须指定目标设备组');

        $this->taskTargetService->processTargetDevices($task, $data);
    }
}
