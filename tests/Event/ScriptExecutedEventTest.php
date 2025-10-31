<?php

declare(strict_types=1);

namespace Tourze\AutoJsControlBundle\Tests\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Test;
use Tourze\AutoJsControlBundle\Entity\AutoJsDevice;
use Tourze\AutoJsControlBundle\Entity\Script;
use Tourze\AutoJsControlBundle\Entity\ScriptExecutionRecord;
use Tourze\AutoJsControlBundle\Entity\Task;
use Tourze\AutoJsControlBundle\Enum\ExecutionStatus;
use Tourze\AutoJsControlBundle\Event\ScriptExecutedEvent;
use Tourze\PHPUnitSymfonyUnitTest\AbstractEventTestCase;

/**
 * @internal
 */
#[CoversClass(ScriptExecutedEvent::class)]
final class ScriptExecutedEventTest extends AbstractEventTestCase
{
    #[Test]
    public function constructorSetsProperties(): void
    {
        // Arrange
        $script = new Script();
        $device = new AutoJsDevice();
        $task = new Task();
        $executionRecord = new ScriptExecutionRecord();
        $executionRecord->setInstructionId('exec_123');

        // Act
        $event = new ScriptExecutedEvent($script, $device, $task, $executionRecord);

        // Assert
        $this->assertSame($script, $event->getScript());
        $this->assertSame($device, $event->getDevice());
        $this->assertSame($task, $event->getTask());
        $this->assertSame($executionRecord, $event->getExecutionRecord());
    }

    #[Test]
    public function getScriptReturnsCorrectScript(): void
    {
        // Arrange
        $script = new Script();
        $script->setCode('EXECUTED-TEST-' . uniqid());
        $script->setName('Test Script');
        $device = new AutoJsDevice();

        $executionRecord = new ScriptExecutionRecord();
        $executionRecord->setScript($script);

        $event = new ScriptExecutedEvent($script, $device, null, $executionRecord);

        // Act & Assert
        $this->assertSame($script, $event->getScript());
    }

    #[Test]
    public function getDeviceReturnsCorrectDevice(): void
    {
        // Arrange
        $script = new Script();
        $device = new AutoJsDevice();

        $executionRecord = new ScriptExecutionRecord();
        $executionRecord->setAutoJsDevice($device);

        $event = new ScriptExecutedEvent($script, $device, null, $executionRecord);

        // Act & Assert
        $this->assertSame($device, $event->getDevice());
    }

    #[Test]
    public function getTaskReturnsCorrectTask(): void
    {
        // Arrange
        $script = new Script();
        $device = new AutoJsDevice();
        $task = new Task();
        $task->setName('Test Task');

        $executionRecord = new ScriptExecutionRecord();
        $executionRecord->setTask($task);

        $event = new ScriptExecutedEvent($script, $device, $task, $executionRecord);

        // Act & Assert
        $this->assertSame($task, $event->getTask());
    }

    #[Test]
    public function isSuccessReturnsCorrectValue(): void
    {
        $script = new Script();
        $device = new AutoJsDevice();

        // Success case
        $successRecord = new ScriptExecutionRecord();
        $successRecord->setStatus(ExecutionStatus::SUCCESS);
        $successEvent = new ScriptExecutedEvent($script, $device, null, $successRecord, false, ['success' => true]);
        $this->assertTrue($successEvent->isSuccess());

        // Failure case
        $failureRecord = new ScriptExecutionRecord();
        $failureRecord->setStatus(ExecutionStatus::FAILED);
        $failureEvent = new ScriptExecutedEvent($script, $device, null, $failureRecord, false, ['success' => false]);
        $this->assertFalse($failureEvent->isSuccess());

        // Running (started) case
        $runningRecord = new ScriptExecutionRecord();
        $runningRecord->setStatus(ExecutionStatus::RUNNING);
        $runningEvent = new ScriptExecutedEvent($script, $device, null, $runningRecord, true);
        $this->assertFalse($runningEvent->isSuccess());
    }

    #[Test]
    public function isCompletedReturnsCorrectValue(): void
    {
        $script = new Script();
        $device = new AutoJsDevice();

        // Completed case (isStarted = false)
        $completedRecord = new ScriptExecutionRecord();
        $completedRecord->setStatus(ExecutionStatus::SUCCESS);
        $completedEvent = new ScriptExecutedEvent($script, $device, null, $completedRecord, false);
        $this->assertTrue($completedEvent->isCompleted());

        // Started case (isStarted = true)
        $startedRecord = new ScriptExecutionRecord();
        $startedRecord->setStatus(ExecutionStatus::RUNNING);
        $startedEvent = new ScriptExecutedEvent($script, $device, null, $startedRecord, true);
        $this->assertFalse($startedEvent->isCompleted());
    }

    #[Test]
    public function getTaskReturnsNullWhenNoTask(): void
    {
        $script = new Script();
        $device = new AutoJsDevice();

        // With task
        $task = new Task();
        $recordWithTask = new ScriptExecutionRecord();
        $recordWithTask->setTask($task);
        $eventWithTask = new ScriptExecutedEvent($script, $device, $task, $recordWithTask);
        $this->assertSame($task, $eventWithTask->getTask());

        // Without task
        $recordWithoutTask = new ScriptExecutionRecord();
        $eventWithoutTask = new ScriptExecutedEvent($script, $device, null, $recordWithoutTask);
        $this->assertNull($eventWithoutTask->getTask());
    }

    #[Test]
    public function getExecutionResultReturnsCorrectResult(): void
    {
        $script = new Script();
        $device = new AutoJsDevice();

        // With execution result
        $executionResult = ['success' => true, 'output' => 'Script completed', 'duration' => 5.5];
        $recordWithResult = new ScriptExecutionRecord();
        $eventWithResult = new ScriptExecutedEvent($script, $device, null, $recordWithResult, false, $executionResult);
        $this->assertEquals($executionResult, $eventWithResult->getExecutionResult());

        // Without execution result
        $recordWithoutResult = new ScriptExecutionRecord();
        $eventWithoutResult = new ScriptExecutedEvent($script, $device, null, $recordWithoutResult);
        $this->assertEquals([], $eventWithoutResult->getExecutionResult());
    }

    #[Test]
    public function getErrorMessageReturnsCorrectMessage(): void
    {
        $script = new Script();
        $device = new AutoJsDevice();

        // With error message
        $executionResult = ['success' => false, 'error' => 'Script timeout'];
        $recordWithError = new ScriptExecutionRecord();
        $eventWithError = new ScriptExecutedEvent($script, $device, null, $recordWithError, false, $executionResult);
        $this->assertEquals('Script timeout', $eventWithError->getErrorMessage());

        // Without error message
        $recordWithoutError = new ScriptExecutionRecord();
        $eventWithoutError = new ScriptExecutedEvent($script, $device, null, $recordWithoutError);
        $this->assertNull($eventWithoutError->getErrorMessage());
    }

    #[Test]
    public function isStartedReturnsCorrectValue(): void
    {
        $script = new Script();
        $device = new AutoJsDevice();
        $executionRecord = new ScriptExecutionRecord();

        // Started case (default)
        $startedEvent = new ScriptExecutedEvent($script, $device, null, $executionRecord, true);
        $this->assertTrue($startedEvent->isStarted());

        // Completed case
        $completedEvent = new ScriptExecutedEvent($script, $device, null, $executionRecord, false);
        $this->assertFalse($completedEvent->isStarted());
    }
}
