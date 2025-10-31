<?php

declare(strict_types=1);

namespace Tourze\AutoJsControlBundle\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Test;
use Tourze\AutoJsControlBundle\Entity\Script;
use Tourze\AutoJsControlBundle\Entity\Task;
use Tourze\AutoJsControlBundle\Enum\ScriptStatus;
use Tourze\AutoJsControlBundle\Enum\TaskStatus;
use Tourze\AutoJsControlBundle\Enum\TaskTargetType;
use Tourze\AutoJsControlBundle\Enum\TaskType;
use Tourze\AutoJsControlBundle\Exception\InvalidTaskArgumentException;
use Tourze\AutoJsControlBundle\Service\TaskCreationService;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(TaskCreationService::class)]
#[RunTestsInSeparateProcesses]
final class TaskCreationServiceTest extends AbstractIntegrationTestCase
{
    private TaskCreationService $taskCreationService;

    protected function onSetUp(): void
    {
        $this->taskCreationService = self::getService(TaskCreationService::class);
    }

    #[Test]
    public function testCreateTaskWithBasicData(): void
    {
        // Arrange
        $script = $this->createTestScript();
        $taskData = [
            'name' => 'Test Task',
            'description' => 'Test Description',
            'taskType' => TaskType::IMMEDIATE->value,
            'targetType' => TaskTargetType::ALL->value,
            'priority' => 5,
            'script' => $script,
        ];

        // Act
        $task = $this->taskCreationService->createTask($taskData);

        // Assert
        $this->assertInstanceOf(Task::class, $task);
        $this->assertEquals('Test Task', $task->getName());
        $this->assertEquals('Test Description', $task->getDescription());
        $this->assertEquals(TaskType::IMMEDIATE, $task->getTaskType());
        $this->assertEquals(TaskTargetType::ALL, $task->getTargetType());
        $this->assertEquals(5, $task->getPriority());
        $this->assertEquals(TaskStatus::PENDING, $task->getStatus());
    }

    #[Test]
    public function testCreateTaskWithArrayParameters(): void
    {
        // Arrange
        $script = $this->createTestScript();
        $taskData = [
            'name' => 'Task with Array Parameters',
            'taskType' => TaskType::IMMEDIATE->value,
            'targetType' => TaskTargetType::ALL->value,
            'script' => $script,
            'parameters' => ['key1' => 'value1', 'key2' => 'value2'],
        ];

        // Act
        $task = $this->taskCreationService->createTask($taskData);

        // Assert
        $expectedJson = json_encode(['key1' => 'value1', 'key2' => 'value2']);
        $this->assertEquals($expectedJson, $task->getParameters());
    }

    #[Test]
    public function testCreateTaskWithStringParameters(): void
    {
        // Arrange
        $script = $this->createTestScript();
        $parametersString = '{"test": "value"}';
        $taskData = [
            'name' => 'Task with String Parameters',
            'taskType' => TaskType::IMMEDIATE->value,
            'targetType' => TaskTargetType::ALL->value,
            'script' => $script,
            'parameters' => $parametersString,
        ];

        // Act
        $task = $this->taskCreationService->createTask($taskData);

        // Assert
        $this->assertEquals($parametersString, $task->getParameters());
    }

    #[Test]
    public function testCreateTaskWithScheduledTime(): void
    {
        // Arrange
        $script = $this->createTestScript();
        $scheduledTime = '2024-01-01 12:00:00';
        $taskData = [
            'name' => 'Scheduled Task',
            'taskType' => TaskType::SCHEDULED->value,
            'targetType' => TaskTargetType::ALL->value,
            'script' => $script,
            'scheduledTime' => $scheduledTime,
        ];

        // Act
        $task = $this->taskCreationService->createTask($taskData);

        // Assert
        $expectedTime = new \DateTimeImmutable($scheduledTime);
        $this->assertEquals($expectedTime, $task->getScheduledTime());
    }

    #[Test]
    public function testCreateRecurringTaskWithCronExpression(): void
    {
        // Arrange
        $script = $this->createTestScript();
        $cronExpression = '0 */6 * * *';
        $taskData = [
            'name' => 'Recurring Task',
            'taskType' => TaskType::RECURRING->value,
            'targetType' => TaskTargetType::ALL->value,
            'script' => $script,
            'cronExpression' => $cronExpression,
        ];

        // Act
        $task = $this->taskCreationService->createTask($taskData);

        // Assert
        $this->assertEquals($cronExpression, $task->getCronExpression());
    }

    #[Test]
    public function testCreateRecurringTaskWithoutCronExpressionThrowsException(): void
    {
        // Arrange
        $script = $this->createTestScript();
        $taskData = [
            'name' => 'Invalid Recurring Task',
            'taskType' => TaskType::RECURRING->value,
            'targetType' => TaskTargetType::ALL->value,
            'script' => $script,
        ];

        // Assert
        $this->expectException(InvalidTaskArgumentException::class);
        $this->expectExceptionMessage('循环任务必须指定Cron表达式');

        // Act
        $this->taskCreationService->createTask($taskData);
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
}
