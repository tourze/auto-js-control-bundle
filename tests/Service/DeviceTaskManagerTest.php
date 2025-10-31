<?php

declare(strict_types=1);

namespace Tourze\AutoJsControlBundle\Tests\Service;

use DeviceBundle\Entity\Device as BaseDevice;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Tourze\AutoJsControlBundle\Entity\AutoJsDevice;
use Tourze\AutoJsControlBundle\Entity\Script;
use Tourze\AutoJsControlBundle\Entity\Task;
use Tourze\AutoJsControlBundle\Repository\TaskRepository;
use Tourze\AutoJsControlBundle\Service\DeviceTaskManager;
use Tourze\AutoJsControlBundle\Service\InstructionQueueService;
use Tourze\AutoJsControlBundle\ValueObject\DeviceInstruction;

/**
 * @internal
 */
#[CoversClass(DeviceTaskManager::class)]
final class DeviceTaskManagerTest extends TestCase
{
    private DeviceTaskManager $manager;

    private TaskRepository&MockObject $taskRepository;

    private InstructionQueueService&MockObject $instructionQueueService;

    private LoggerInterface&MockObject $logger;

    protected function setUp(): void
    {
        $this->taskRepository = $this->createMock(TaskRepository::class);
        $this->instructionQueueService = $this->createMock(InstructionQueueService::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->manager = new DeviceTaskManager(
            $this->taskRepository,
            $this->instructionQueueService,
            $this->logger
        );
    }

    #[Test]
    public function testSendWelcomeInstructionShouldSendCorrectInstruction(): void
    {
        // Arrange
        $device = $this->createAutoJsDevice('WELCOME_DEVICE', 123);

        $this->instructionQueueService->expects($this->once())
            ->method('sendInstruction')
            ->with(
                'WELCOME_DEVICE',
                self::callback(function (DeviceInstruction $instruction) use ($device) {
                    $data = $instruction->getData();

                    return 'welcome' === $instruction->getType()
                        && 300 === $instruction->getTimeout()
                        && 5 === $instruction->getPriority()
                        && '欢迎加入Auto.js控制系统' === $data['message']
                        && $device->getId() === $data['deviceId']
                        && isset($data['serverTime'])
                        && is_string($data['serverTime'])
                        && 1 === preg_match('/\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}/', $data['serverTime']);
                })
            )
        ;

        // Act
        $this->manager->sendWelcomeInstruction($device);
    }

    #[Test]
    public function testSendWelcomeInstructionWithDeviceWithoutBaseDeviceShouldNotSendInstruction(): void
    {
        // Arrange
        $device = new AutoJsDevice();

        $this->instructionQueueService->expects($this->never())
            ->method('sendInstruction')
        ;

        // Act
        $this->manager->sendWelcomeInstruction($device);
    }

    #[Test]
    public function testCheckPendingTasksWithNoTasksShouldNotSendInstructions(): void
    {
        // Arrange
        $device = $this->createAutoJsDevice('NO_TASKS_DEVICE', 456);

        $this->taskRepository->expects($this->once())
            ->method('findPendingTasksForDevice')
            ->with($device)
            ->willReturn([])
        ;

        $this->instructionQueueService->expects($this->never())
            ->method('sendInstruction')
        ;

        $this->logger->expects($this->never())
            ->method('info')
        ;

        // Act
        $this->manager->checkPendingTasks($device);
    }

    #[Test]
    public function testCheckPendingTasksWithValidTasksShouldSendInstructions(): void
    {
        // Arrange
        $device = $this->createAutoJsDevice('TASKS_DEVICE', 789);

        $task1 = $this->createTask(101, 'Task 1');
        $script1 = $this->createScript(201, 'SCRIPT_1');
        $task1->setScript($script1);

        $task2 = $this->createTask(102, 'Task 2');
        $script2 = $this->createScript(202, 'SCRIPT_2');
        $task2->setScript($script2);

        $this->taskRepository->expects($this->once())
            ->method('findPendingTasksForDevice')
            ->with($device)
            ->willReturn([$task1, $task2])
        ;

        $this->instructionQueueService->expects($this->exactly(2))
            ->method('sendInstruction')
            ->willReturnCallback(function ($deviceCode, DeviceInstruction $instruction): void {
                $this->assertEquals('TASKS_DEVICE', $deviceCode);
                $this->assertEquals('execute_task', $instruction->getType());
                $this->assertEquals(300, $instruction->getTimeout());
                $this->assertEquals(8, $instruction->getPriority());

                $data = $instruction->getData();
                $this->assertArrayHasKey('taskId', $data);
                $this->assertArrayHasKey('taskName', $data);
                $this->assertArrayHasKey('scriptId', $data);

                if (101 === $data['taskId']) {
                    $this->assertEquals('Task 1', $data['taskName']);
                    $this->assertEquals(201, $data['scriptId']);
                } elseif (102 === $data['taskId']) {
                    $this->assertEquals('Task 2', $data['taskName']);
                    $this->assertEquals(202, $data['scriptId']);
                } else {
                    $taskId = $data['taskId'] ?? 'null';
                    static::fail('Unexpected taskId: ' . (is_scalar($taskId) ? (string) $taskId : 'null'));
                }
            })
        ;

        $this->logger->expects($this->once())
            ->method('info')
            ->with('为上线设备发送待执行任务', self::callback(function (mixed $context) use ($device): bool {
                if (!is_array($context)) {
                    return false;
                }

                return ($context['deviceId'] ?? null) === $device->getId()
                    && 2 === ($context['taskCount'] ?? null);
            }))
        ;

        // Act
        $this->manager->checkPendingTasks($device);
    }

    #[Test]
    public function testCheckPendingTasksWithTaskWithoutScriptShouldSkipAndLog(): void
    {
        // Arrange
        $device = $this->createAutoJsDevice('SCRIPT_MISSING_DEVICE', 111);

        $task1 = $this->createTask(101, 'Task 1');
        $script1 = $this->createScript(201, 'SCRIPT_1');
        $task1->setScript($script1);

        $task2 = $this->createTask(102, 'Task 2 without script');
        // No script set for task2

        $this->taskRepository->expects($this->once())
            ->method('findPendingTasksForDevice')
            ->with($device)
            ->willReturn([$task1, $task2])
        ;

        // Only one instruction should be sent (for task1)
        $this->instructionQueueService->expects($this->once())
            ->method('sendInstruction')
            ->with('SCRIPT_MISSING_DEVICE', self::callback(function (DeviceInstruction $instruction) {
                $data = $instruction->getData();

                return 101 === $data['taskId']
                    && 'Task 1' === $data['taskName']
                    && 201 === $data['scriptId'];
            }))
        ;

        // Warning should be logged for task without script
        $this->logger->expects($this->once())
            ->method('warning')
            ->with('任务关联的脚本不存在，跳过执行', self::callback(function (mixed $context): bool {
                if (!is_array($context)) {
                    return false;
                }

                return 102 === ($context['taskId'] ?? null)
                    && 'Task 2 without script' === ($context['taskName'] ?? null);
            }))
        ;

        $this->logger->expects($this->once())
            ->method('info')
            ->with('为上线设备发送待执行任务', self::callback(function (mixed $context) use ($device): bool {
                if (!is_array($context)) {
                    return false;
                }

                return ($context['deviceId'] ?? null) === $device->getId()
                    && 2 === ($context['taskCount'] ?? null); // Still logs total count including skipped ones
            }))
        ;

        // Act
        $this->manager->checkPendingTasks($device);
    }

    #[Test]
    public function testCancelRunningTasksWithNoTasksShouldNotSendInstructions(): void
    {
        // Arrange
        $device = $this->createAutoJsDevice('NO_RUNNING_TASKS', 222);

        $this->taskRepository->expects($this->once())
            ->method('findRunningTasksForDevice')
            ->with($device)
            ->willReturn([])
        ;

        $this->instructionQueueService->expects($this->never())
            ->method('sendInstruction')
        ;

        $this->logger->expects($this->never())
            ->method('warning')
        ;

        // Act
        $this->manager->cancelRunningTasks($device);
    }

    #[Test]
    public function testCancelRunningTasksWithTasksShouldSendCancellationInstructions(): void
    {
        // Arrange
        $device = $this->createAutoJsDevice('RUNNING_TASKS_DEVICE', 333);

        $task1 = $this->createTask(301, 'Running Task 1');
        $task2 = $this->createTask(302, 'Running Task 2');

        $this->taskRepository->expects($this->once())
            ->method('findRunningTasksForDevice')
            ->with($device)
            ->willReturn([$task1, $task2])
        ;

        $this->instructionQueueService->expects($this->exactly(2))
            ->method('sendInstruction')
            ->willReturnCallback(function ($deviceCode, DeviceInstruction $instruction): void {
                $this->assertEquals('RUNNING_TASKS_DEVICE', $deviceCode);
                $this->assertEquals('cancel_task', $instruction->getType());
                $this->assertEquals(300, $instruction->getTimeout());
                $this->assertEquals(10, $instruction->getPriority()); // Highest priority

                $data = $instruction->getData();
                $this->assertArrayHasKey('taskId', $data);
                $this->assertArrayHasKey('reason', $data);
                $this->assertEquals('设备离线', $data['reason']);
                $this->assertContains($data['taskId'], [301, 302]);
            })
        ;

        $this->logger->expects($this->once())
            ->method('warning')
            ->with('设备离线，取消运行中任务', self::callback(function (mixed $context) use ($device): bool {
                if (!is_array($context)) {
                    return false;
                }

                return ($context['deviceId'] ?? null) === $device->getId()
                    && 2 === ($context['taskCount'] ?? null);
            }))
        ;

        // Act
        $this->manager->cancelRunningTasks($device);
    }

    #[Test]
    public function testCancelRunningTasksWithInstructionErrorShouldLogAndContinue(): void
    {
        // Arrange
        $device = $this->createAutoJsDevice('ERROR_DEVICE', 444);

        $task1 = $this->createTask(401, 'Error Task 1');
        $task2 = $this->createTask(402, 'Error Task 2');

        $this->taskRepository->expects($this->once())
            ->method('findRunningTasksForDevice')
            ->with($device)
            ->willReturn([$task1, $task2])
        ;

        $exception = new \Exception('Queue error');

        $this->instructionQueueService->expects($this->exactly(2))
            ->method('sendInstruction')
            ->willReturnCallback(function ($deviceCode, DeviceInstruction $instruction) use ($exception): void {
                $data = $instruction->getData();
                if (401 === $data['taskId']) {
                    throw $exception; // First task fails
                }
                // Second task succeeds
            })
        ;

        // Error should be logged for the failed task
        $this->logger->expects($this->once())
            ->method('error')
            ->with('发送任务取消指令失败', self::callback(function (mixed $context) use ($device, $exception): bool {
                if (!is_array($context)) {
                    return false;
                }

                return ($context['deviceId'] ?? null) === $device->getId()
                    && 401 === ($context['taskId'] ?? null)
                    && ($context['error'] ?? null) === $exception->getMessage();
            }))
        ;

        $this->logger->expects($this->once())
            ->method('warning')
            ->with('设备离线，取消运行中任务', self::callback(function (mixed $context) use ($device): bool {
                if (!is_array($context)) {
                    return false;
                }

                return ($context['deviceId'] ?? null) === $device->getId()
                    && 2 === ($context['taskCount'] ?? null);
            }))
        ;

        // Act & Assert - should not throw exception
        $this->manager->cancelRunningTasks($device);
    }

    #[Test]
    public function testCancelRunningTasksWithDeviceWithoutBaseDeviceShouldNotSendInstructions(): void
    {
        // Arrange
        $device = new AutoJsDevice();
        $task = $this->createTask(501, 'Task without base device');

        $this->taskRepository->expects($this->once())
            ->method('findRunningTasksForDevice')
            ->with($device)
            ->willReturn([$task])
        ;

        $this->instructionQueueService->expects($this->never())
            ->method('sendInstruction')
        ;

        $this->logger->expects($this->once())
            ->method('warning')
            ->with('设备离线，取消运行中任务', self::callback(function (mixed $context): bool {
                if (!is_array($context)) {
                    return false;
                }

                // Device without base device will have null deviceId
                $hasNullDeviceId = array_key_exists('deviceId', $context) && null === $context['deviceId'];

                return $hasNullDeviceId && 1 === ($context['taskCount'] ?? 0);
            }))
        ;

        // Act
        $this->manager->cancelRunningTasks($device);
    }

    private function createAutoJsDevice(string $code, ?int $id = null): AutoJsDevice
    {
        $baseDevice = new BaseDevice();
        $baseDevice->setCode($code);

        $autoJsDevice = new AutoJsDevice();
        $autoJsDevice->setBaseDevice($baseDevice);

        if (null !== $id) {
            $this->setDeviceId($autoJsDevice, $id);
        }

        return $autoJsDevice;
    }

    private function createTask(int $id, string $name): Task
    {
        $task = new Task();
        $task->setName($name);
        $this->setTaskId($task, $id);

        return $task;
    }

    private function createScript(int $id, string $code): Script
    {
        $script = new Script();
        $script->setCode($code);
        $this->setScriptId($script, $id);

        return $script;
    }

    private function setDeviceId(AutoJsDevice $device, int $id): void
    {
        $reflection = new \ReflectionClass($device);
        $property = $reflection->getProperty('id');
        $property->setAccessible(true);
        $property->setValue($device, $id);
    }

    private function setTaskId(Task $task, int $id): void
    {
        $reflection = new \ReflectionClass($task);
        $property = $reflection->getProperty('id');
        $property->setAccessible(true);
        $property->setValue($task, $id);
    }

    private function setScriptId(Script $script, int $id): void
    {
        $reflection = new \ReflectionClass($script);
        $property = $reflection->getProperty('id');
        $property->setAccessible(true);
        $property->setValue($script, $id);
    }
}
