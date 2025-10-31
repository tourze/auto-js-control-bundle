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
use Tourze\AutoJsControlBundle\Entity\Script;
use Tourze\AutoJsControlBundle\Entity\Task;
use Tourze\AutoJsControlBundle\Enum\ScriptStatus;
use Tourze\AutoJsControlBundle\Enum\TaskStatus;
use Tourze\AutoJsControlBundle\Enum\TaskTargetType;
use Tourze\AutoJsControlBundle\Enum\TaskType;
use Tourze\AutoJsControlBundle\Service\TaskDispatcher;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(TaskDispatcher::class)]
#[RunTestsInSeparateProcesses]
final class TaskDispatcherTest extends AbstractIntegrationTestCase
{
    private TaskDispatcher $taskDispatcher;

    protected function onSetUp(): void
    {
        $this->taskDispatcher = self::getService(TaskDispatcher::class);
    }

    #[Test]
    public function testDispatchTaskWithDevicesUpdatesTaskStatus(): void
    {
        // Arrange
        $script = $this->createTestScript();
        $device = $this->createTestDevice('DISPATCH_DEVICE');

        $task = new Task();
        $task->setName('Dispatch Test Task');
        $task->setTaskType(TaskType::IMMEDIATE);
        $task->setTargetType(TaskTargetType::ALL);
        $task->setStatus(TaskStatus::PENDING);
        $task->setScript($script);
        $task->setParameters('{}');

        self::getEntityManager()->persist($task);
        self::getEntityManager()->flush();

        // Act
        $this->taskDispatcher->dispatchTask($task);

        // Assert
        self::getEntityManager()->refresh($task);
        $this->assertEquals(TaskStatus::RUNNING, $task->getStatus());
        $this->assertNotNull($task->getStartTime());
        $this->assertGreaterThan(0, $task->getTotalDevices());
    }

    #[Test]
    public function testDispatchTaskWithNoDevicesMarksTaskFailed(): void
    {
        // Arrange - No devices available
        $script = $this->createTestScript();

        $task = new Task();
        $task->setName('No Devices Task');
        $task->setTaskType(TaskType::IMMEDIATE);
        $task->setTargetType(TaskTargetType::SPECIFIC);
        $task->setStatus(TaskStatus::PENDING);
        $task->setScript($script);
        $task->setParameters('{}');
        $task->setTargetDeviceIds('[]'); // No target devices

        self::getEntityManager()->persist($task);
        self::getEntityManager()->flush();

        // Act
        $this->taskDispatcher->dispatchTask($task);

        // Assert
        self::getEntityManager()->refresh($task);
        $this->assertEquals(TaskStatus::FAILED, $task->getStatus());
        $this->assertEquals('没有可用的目标设备', $task->getFailureReason());
    }

    #[Test]
    public function testUpdateTaskProgressWithSuccessStatus(): void
    {
        // Arrange
        $script = $this->createTestScript();
        $task = new Task();
        $task->setName('Progress Test Task');
        $task->setTaskType(TaskType::IMMEDIATE);
        $task->setTargetType(TaskTargetType::ALL);
        $task->setStatus(TaskStatus::RUNNING);
        $task->setScript($script);
        $task->setTotalDevices(2);
        $task->setSuccessDevices(0);
        $task->setFailedDevices(0);

        self::getEntityManager()->persist($task);
        self::getEntityManager()->flush();

        // Act
        $this->taskDispatcher->updateTaskProgress($task, 'INSTRUCTION_1', 'success');

        // Assert
        self::getEntityManager()->refresh($task);
        $this->assertEquals(1, $task->getSuccessDevices());
        $this->assertEquals(0, $task->getFailedDevices());
        $this->assertEquals(TaskStatus::RUNNING, $task->getStatus()); // Still running
    }

    #[Test]
    public function testUpdateTaskProgressWithFailedStatus(): void
    {
        // Arrange
        $script = $this->createTestScript();
        $task = new Task();
        $task->setName('Progress Fail Test Task');
        $task->setTaskType(TaskType::IMMEDIATE);
        $task->setTargetType(TaskTargetType::ALL);
        $task->setStatus(TaskStatus::RUNNING);
        $task->setScript($script);
        $task->setTotalDevices(2);
        $task->setSuccessDevices(0);
        $task->setFailedDevices(0);

        self::getEntityManager()->persist($task);
        self::getEntityManager()->flush();

        // Act
        $this->taskDispatcher->updateTaskProgress($task, 'INSTRUCTION_1', 'failed');

        // Assert
        self::getEntityManager()->refresh($task);
        $this->assertEquals(0, $task->getSuccessDevices());
        $this->assertEquals(1, $task->getFailedDevices());
    }

    #[Test]
    public function testUpdateTaskProgressCompletesTaskWhenAllDevicesReported(): void
    {
        // Arrange
        $script = $this->createTestScript();
        $task = new Task();
        $task->setName('Complete Test Task');
        $task->setTaskType(TaskType::IMMEDIATE);
        $task->setTargetType(TaskTargetType::ALL);
        $task->setStatus(TaskStatus::RUNNING);
        $task->setScript($script);
        $task->setTotalDevices(2);
        $task->setSuccessDevices(1);
        $task->setFailedDevices(0);

        self::getEntityManager()->persist($task);
        self::getEntityManager()->flush();

        // Act - Last device reports success
        $this->taskDispatcher->updateTaskProgress($task, 'INSTRUCTION_2', 'success');

        // Assert
        self::getEntityManager()->refresh($task);
        $this->assertEquals(TaskStatus::COMPLETED, $task->getStatus());
        $this->assertEquals(2, $task->getSuccessDevices());
        $this->assertNotNull($task->getEndTime());
    }

    #[Test]
    public function testUpdateTaskProgressMarksPartiallyCompleted(): void
    {
        // Arrange
        $script = $this->createTestScript();
        $task = new Task();
        $task->setName('Partial Complete Test Task');
        $task->setTaskType(TaskType::IMMEDIATE);
        $task->setTargetType(TaskTargetType::ALL);
        $task->setStatus(TaskStatus::RUNNING);
        $task->setScript($script);
        $task->setTotalDevices(3);
        $task->setSuccessDevices(1);
        $task->setFailedDevices(1);

        self::getEntityManager()->persist($task);
        self::getEntityManager()->flush();

        // Act - Last device reports failure
        $this->taskDispatcher->updateTaskProgress($task, 'INSTRUCTION_3', 'failed');

        // Assert
        self::getEntityManager()->refresh($task);
        $this->assertEquals(TaskStatus::PARTIALLY_COMPLETED, $task->getStatus());
        $this->assertEquals(1, $task->getSuccessDevices());
        $this->assertEquals(2, $task->getFailedDevices());
    }

    private function createTestScript(): Script
    {
        $script = new Script();
        $script->setCode('TEST-SCRIPT-' . uniqid());
        $script->setName('Test Script');
        $script->setContent('console.log("test");');
        $script->setStatus(ScriptStatus::ACTIVE);
        $script->setVersion(1);
        $script->setTimeout(600);

        $em = self::getEntityManager();
        $em->persist($script);
        $em->flush();

        return $script;
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
}
