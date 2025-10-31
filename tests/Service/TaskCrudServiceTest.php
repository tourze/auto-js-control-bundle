<?php

declare(strict_types=1);

namespace Tourze\AutoJsControlBundle\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\AutoJsControlBundle\Entity\Script;
use Tourze\AutoJsControlBundle\Entity\Task;
use Tourze\AutoJsControlBundle\Enum\ScriptStatus;
use Tourze\AutoJsControlBundle\Enum\TaskStatus;
use Tourze\AutoJsControlBundle\Enum\TaskTargetType;
use Tourze\AutoJsControlBundle\Enum\TaskType;
use Tourze\AutoJsControlBundle\Exception\BusinessLogicException;
use Tourze\AutoJsControlBundle\Service\TaskCrudService;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(TaskCrudService::class)]
#[RunTestsInSeparateProcesses]
final class TaskCrudServiceTest extends AbstractIntegrationTestCase
{
    private TaskCrudService $taskCrudService;

    protected function onSetUp(): void
    {
        $this->taskCrudService = self::getService(TaskCrudService::class);
    }

    public function testServiceExists(): void
    {
        $this->assertInstanceOf(TaskCrudService::class, $this->taskCrudService);
    }

    private function createTestScript(): Script
    {
        $script = new Script();
        $script->setName('Test Script');
        $script->setCode('console.log("test");');
        $script->setValid(true);
        $script->setStatus(ScriptStatus::ACTIVE);

        self::getEntityManager()->persist($script);
        self::getEntityManager()->flush();

        return $script;
    }

    public function testCreateTaskSuccess(): void
    {
        // Arrange
        $script = $this->createTestScript();
        $data = [
            'name' => 'Test Task',
            'description' => 'Test Description',
            'scriptId' => $script->getId(),
            'taskType' => 'immediate',
            'targetType' => 'all',
            'priority' => 5,
            'parameters' => json_encode(['param1' => 'value1']),
        ];

        // Act
        $result = $this->taskCrudService->createTask($data);

        // Assert
        $this->assertInstanceOf(Task::class, $result);
        $this->assertEquals('Test Task', $result->getName());
        $this->assertEquals('Test Description', $result->getDescription());
        $this->assertEquals(TaskType::IMMEDIATE, $result->getTaskType());
        $this->assertEquals(TaskTargetType::ALL, $result->getTargetType());
        $this->assertEquals(5, $result->getPriority());
        $this->assertEquals($script, $result->getScript());
        $this->assertEquals(TaskStatus::PENDING, $result->getStatus());
    }

    public function testCreateTaskWithMissingName(): void
    {
        // Arrange
        $script = $this->createTestScript();
        $data = [
            'scriptId' => $script->getId(),
            'taskType' => 'immediate',
            'targetType' => 'all',
        ];

        // Act & Assert
        $this->expectException(BusinessLogicException::class);
        $this->expectExceptionMessage('字段 name 不能为空');

        $this->taskCrudService->createTask($data);
    }

    public function testCreateTaskWithMissingScriptId(): void
    {
        // Arrange
        $data = [
            'name' => 'Test Task',
            'taskType' => 'immediate',
            'targetType' => 'all',
        ];

        // Act & Assert
        $this->expectException(BusinessLogicException::class);
        $this->expectExceptionMessage('字段 scriptId 不能为空');

        $this->taskCrudService->createTask($data);
    }

    public function testCreateTaskWithInvalidScript(): void
    {
        // Arrange
        $data = [
            'name' => 'Test Task',
            'scriptId' => 999999, // Non-existent script ID
            'taskType' => 'immediate',
            'targetType' => 'all',
        ];

        // Act & Assert
        $this->expectException(BusinessLogicException::class);
        $this->expectExceptionMessage('脚本不存在或已禁用');

        $this->taskCrudService->createTask($data);
    }

    public function testCreateTaskWithDisabledScript(): void
    {
        // Arrange
        $script = new Script();
        $script->setName('Disabled Script');
        $script->setCode('console.log("disabled");');
        $script->setValid(false); // Disabled script
        $script->setStatus(ScriptStatus::INACTIVE);

        self::getEntityManager()->persist($script);
        self::getEntityManager()->flush();

        $data = [
            'name' => 'Test Task',
            'scriptId' => $script->getId(),
            'taskType' => 'immediate',
            'targetType' => 'all',
        ];

        // Act & Assert
        $this->expectException(BusinessLogicException::class);
        $this->expectExceptionMessage('脚本不存在或已禁用');

        $this->taskCrudService->createTask($data);
    }

    public function testCreateTaskWithScheduledTime(): void
    {
        // Arrange
        $script = $this->createTestScript();
        $scheduledTime = new \DateTimeImmutable('+1 hour');
        $data = [
            'name' => 'Scheduled Task',
            'scriptId' => $script->getId(),
            'taskType' => 'scheduled',
            'targetType' => 'all',
            'scheduledTime' => $scheduledTime->format('Y-m-d H:i:s'),
        ];

        // Act
        $result = $this->taskCrudService->createTask($data);

        // Assert
        $this->assertInstanceOf(Task::class, $result);
        $this->assertEquals('Scheduled Task', $result->getName());
        $this->assertEquals(TaskType::SCHEDULED, $result->getTaskType());
        $this->assertNotNull($result->getScheduledTime());
        $this->assertEquals($scheduledTime->format('Y-m-d H:i'), $result->getScheduledTime()->format('Y-m-d H:i'));
    }

    public function testUpdateTaskSuccess(): void
    {
        // Arrange - create a task first
        $script = $this->createTestScript();
        $data = [
            'name' => 'Original Task',
            'scriptId' => $script->getId(),
            'taskType' => 'immediate',
            'targetType' => 'all',
        ];
        $task = $this->taskCrudService->createTask($data);

        // Update data
        $updateData = [
            'name' => 'Updated Task',
            'description' => 'Updated Description',
            'priority' => 10,
        ];

        // Act
        $taskId = $task->getId();
        $this->assertNotNull($taskId, 'Task ID should not be null after persist');
        $result = $this->taskCrudService->updateTask($taskId, $updateData);

        // Assert
        $this->assertInstanceOf(Task::class, $result);
        $this->assertEquals('Updated Task', $result->getName());
        $this->assertEquals('Updated Description', $result->getDescription());
        $this->assertEquals(10, $result->getPriority());
    }

    public function testUpdateTaskNotFound(): void
    {
        // Arrange
        $updateData = [
            'name' => 'Updated Task',
        ];

        // Act & Assert
        $this->expectException(BusinessLogicException::class);
        $this->expectExceptionMessage('任务不存在');

        $this->taskCrudService->updateTask(999999, $updateData);
    }

    public function testDeleteTaskSuccess(): void
    {
        // Arrange - create a task first
        $script = $this->createTestScript();
        $data = [
            'name' => 'Task to Delete',
            'scriptId' => $script->getId(),
            'taskType' => 'immediate',
            'targetType' => 'all',
        ];
        $task = $this->taskCrudService->createTask($data);
        $taskId = $task->getId();

        // Act
        $this->assertNotNull($taskId, 'Task ID should not be null after persist');
        $this->taskCrudService->deleteTask($taskId);

        // Assert - check the task is marked as invalid
        self::getEntityManager()->refresh($task);
        $this->assertFalse($task->isValid());
    }

    public function testDeleteTaskNotFound(): void
    {
        // Act & Assert
        $this->expectException(BusinessLogicException::class);
        $this->expectExceptionMessage('任务不存在');

        $this->taskCrudService->deleteTask(999999);
    }

    public function testExecuteTaskSuccess(): void
    {
        // Arrange - create a task first
        $script = $this->createTestScript();
        $data = [
            'name' => 'Task to Execute',
            'scriptId' => $script->getId(),
            'taskType' => 'immediate',
            'targetType' => 'all',
        ];
        $task = $this->taskCrudService->createTask($data);

        // Act
        $taskId = $task->getId();
        $this->assertNotNull($taskId, 'Task ID should not be null after persist');
        $result = $this->taskCrudService->executeTask($taskId);

        // Assert - executeTask just validates, doesn't change status
        $this->assertInstanceOf(Task::class, $result);
        $this->assertEquals(TaskStatus::PENDING, $result->getStatus());
        $this->assertEquals($task, $result);
    }

    public function testPauseTaskSuccess(): void
    {
        // Arrange - create a task in PENDING status (which can be paused)
        $script = $this->createTestScript();
        $data = [
            'name' => 'Task to Pause',
            'scriptId' => $script->getId(),
            'taskType' => 'immediate',
            'targetType' => 'all',
        ];
        $task = $this->taskCrudService->createTask($data);

        // Act
        $taskId = $task->getId();
        $this->assertNotNull($taskId, 'Task ID should not be null after persist');
        $result = $this->taskCrudService->pauseTask($taskId);

        // Assert
        $this->assertInstanceOf(Task::class, $result);
        $this->assertEquals(TaskStatus::PAUSED, $result->getStatus());
    }

    public function testResumeTaskSuccess(): void
    {
        // Arrange - create and pause a task first
        $script = $this->createTestScript();
        $data = [
            'name' => 'Task to Resume',
            'scriptId' => $script->getId(),
            'taskType' => 'immediate',
            'targetType' => 'all',
        ];
        $task = $this->taskCrudService->createTask($data);
        $taskId = $task->getId();
        $this->assertNotNull($taskId, 'Task ID should not be null after persist');
        $this->taskCrudService->pauseTask($taskId);

        // Act
        $result = $this->taskCrudService->resumeTask($taskId);

        // Assert
        $this->assertInstanceOf(Task::class, $result);
        $this->assertEquals(TaskStatus::PENDING, $result->getStatus());
    }
}
