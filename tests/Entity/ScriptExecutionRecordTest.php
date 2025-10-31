<?php

declare(strict_types=1);

namespace Tourze\AutoJsControlBundle\Tests\Entity;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tourze\AutoJsControlBundle\Entity\AutoJsDevice;
use Tourze\AutoJsControlBundle\Entity\Script;
use Tourze\AutoJsControlBundle\Entity\ScriptExecutionRecord;
use Tourze\AutoJsControlBundle\Entity\Task;
use Tourze\AutoJsControlBundle\Enum\ExecutionStatus;
use Tourze\PHPUnitDoctrineEntity\AbstractEntityTestCase;

/**
 * @internal
 */
#[CoversClass(ScriptExecutionRecord::class)]
final class ScriptExecutionRecordTest extends AbstractEntityTestCase
{
    private ScriptExecutionRecord $executionRecord;

    protected function createEntity(): object
    {
        return new ScriptExecutionRecord();
    }

    protected function setUp(): void
    {
        $this->executionRecord = new ScriptExecutionRecord();
    }

    #[Test]
    public function constructorSetsDefaultValues(): void
    {
        // Assert
        $this->assertNull($this->executionRecord->getId());
        $this->assertNull($this->executionRecord->getInstructionId());
        $this->assertNull($this->executionRecord->getScript());
        $this->assertNull($this->executionRecord->getAutoJsDevice());
        $this->assertNull($this->executionRecord->getTask());
        $this->assertEquals(ExecutionStatus::PENDING, $this->executionRecord->getStatus());
        $this->assertNull($this->executionRecord->getParameters());
        $this->assertNull($this->executionRecord->getStartTime());
        $this->assertNull($this->executionRecord->getEndTime());
        $this->assertEquals(0, $this->executionRecord->getDuration());
        $this->assertNull($this->executionRecord->getResult());
        $this->assertNull($this->executionRecord->getLogs());
        $this->assertNull($this->executionRecord->getErrorMessage());
        $this->assertEquals(0, $this->executionRecord->getRetryCount());
        $this->assertNull($this->executionRecord->getOutput());
        $this->assertNull($this->executionRecord->getExecutionMetrics());
        $this->assertNull($this->executionRecord->getScreenshots());
        $this->assertNull($this->executionRecord->getCreateTime());
    }

    #[Test]
    public function setInstructionIdSetsAndGetsCorrectly(): void
    {
        // Arrange
        $instructionId = 'inst_' . uniqid();

        // Act
        $this->executionRecord->setInstructionId($instructionId);

        // Assert
        $this->assertEquals($instructionId, $this->executionRecord->getInstructionId());
    }

    #[Test]
    public function setParametersSetsAndGetsCorrectly(): void
    {
        // Arrange
        $parameters = json_encode(['param1' => 'value1', 'param2' => 123]);
        $this->assertIsString($parameters);

        // Act
        $this->executionRecord->setParameters($parameters);

        // Assert
        $this->assertEquals($parameters, $this->executionRecord->getParameters());
    }

    #[Test]
    public function setResultSetsAndGetsCorrectly(): void
    {
        // Arrange
        $result = 'Script executed successfully with result: OK';

        // Act
        $this->executionRecord->setResult($result);

        // Assert
        $this->assertEquals($result, $this->executionRecord->getResult());
    }

    #[Test]
    public function setLogsSetsAndGetsCorrectly(): void
    {
        // Arrange
        $logs = "[2024-01-01 10:00:00] Starting script\n[2024-01-01 10:00:01] Completed";

        // Act
        $this->executionRecord->setLogs($logs);

        // Assert
        $this->assertEquals($logs, $this->executionRecord->getLogs());
    }

    #[Test]
    public function setRetryCountSetsAndGetsCorrectly(): void
    {
        // Arrange
        $retryCount = 2;

        // Act
        $this->executionRecord->setRetryCount($retryCount);

        // Assert
        $this->assertEquals($retryCount, $this->executionRecord->getRetryCount());
    }

    #[Test]
    public function setExecutionMetricsSetsAndGetsCorrectly(): void
    {
        // Arrange
        $metrics = ['cpu_usage' => 45.5, 'memory_mb' => 128];

        // Act
        $this->executionRecord->setExecutionMetrics($metrics);

        // Assert
        $this->assertEquals($metrics, $this->executionRecord->getExecutionMetrics());
    }

    #[Test]
    public function setScreenshotsSetsAndGetsCorrectly(): void
    {
        // Arrange
        $screenshots = ['/path/to/screenshot1.png', '/path/to/screenshot2.png'];

        // Act
        $this->executionRecord->setScreenshots($screenshots);

        // Assert
        $this->assertEquals($screenshots, $this->executionRecord->getScreenshots());
    }

    #[Test]
    public function setScriptSetsAndGetsCorrectly(): void
    {
        // Arrange
        $script = new Script();

        // Act
        $this->executionRecord->setScript($script);

        // Assert
        $this->assertSame($script, $this->executionRecord->getScript());
    }

    #[Test]
    public function setAutoJsDeviceSetsAndGetsCorrectly(): void
    {
        // Arrange
        $device = new AutoJsDevice();

        // Act
        $this->executionRecord->setAutoJsDevice($device);

        // Assert
        $this->assertSame($device, $this->executionRecord->getAutoJsDevice());
    }

    #[Test]
    public function setTaskSetsAndGetsCorrectly(): void
    {
        // Arrange
        $task = new Task();

        // Act
        $this->executionRecord->setTask($task);

        // Assert
        $this->assertSame($task, $this->executionRecord->getTask());
    }

    #[Test]
    public function setStatusSetsAndGetsCorrectly(): void
    {
        // Arrange
        $status = ExecutionStatus::SUCCESS;

        // Act
        $this->executionRecord->setStatus($status);

        // Assert
        $this->assertEquals($status, $this->executionRecord->getStatus());
    }

    #[Test]
    public function setOutputSetsAndGetsCorrectly(): void
    {
        // Arrange
        $output = "Script executed successfully\nResult: OK";

        // Act
        $this->executionRecord->setOutput($output);

        // Assert
        $this->assertEquals($output, $this->executionRecord->getOutput());
    }

    #[Test]
    public function setErrorMessageSetsAndGetsCorrectly(): void
    {
        // Arrange
        $errorMessage = 'Script execution failed: Syntax error on line 10';

        // Act
        $this->executionRecord->setErrorMessage($errorMessage);

        // Assert
        $this->assertEquals($errorMessage, $this->executionRecord->getErrorMessage());
    }

    #[Test]
    public function setStartTimeSetsAndGetsCorrectly(): void
    {
        // Arrange
        $startTime = new \DateTimeImmutable('2024-01-01 10:00:00');

        // Act
        $this->executionRecord->setStartTime($startTime);

        // Assert
        $this->assertEquals($startTime, $this->executionRecord->getStartTime());
    }

    #[Test]
    public function setEndTimeSetsAndGetsCorrectly(): void
    {
        // Arrange
        $endTime = new \DateTimeImmutable('2024-01-01 10:05:00');

        // Act
        $this->executionRecord->setEndTime($endTime);

        // Assert
        $this->assertEquals($endTime, $this->executionRecord->getEndTime());
    }

    #[Test]
    public function toStringReturnsCorrectFormat(): void
    {
        // Test with default status
        $this->assertEquals('执行记录 #new (pending)', (string) $this->executionRecord);

        // Test with ID using reflection
        $reflection = new \ReflectionClass($this->executionRecord);
        $property = $reflection->getProperty('id');
        $property->setAccessible(true);
        $property->setValue($this->executionRecord, 12345);

        $this->assertEquals('执行记录 #12345 (pending)', (string) $this->executionRecord);

        // Test with different status
        $this->executionRecord->setStatus(ExecutionStatus::SUCCESS);
        $this->assertEquals('执行记录 #12345 (success)', (string) $this->executionRecord);
    }

    #[Test]
    public function setDurationSetsAndGetsCorrectly(): void
    {
        // Default duration
        $this->assertEquals(0, $this->executionRecord->getDuration());

        // Set duration
        $this->executionRecord->setDuration(330);
        $this->assertEquals(330, $this->executionRecord->getDuration());
    }

    #[Test]
    public function timestampableTraitSetsTimestamps(): void
    {
        // Arrange
        $now = new \DateTimeImmutable();

        // Act
        $this->executionRecord->setCreateTime($now);

        // Assert
        $this->assertSame($now, $this->executionRecord->getCreateTime());
    }

    /**
     * 提供属性及其样本值的 Data Provider.
     *
     * @return iterable<string, array{string, mixed}>
     */
    public static function propertiesProvider(): iterable
    {
        yield 'instructionId' => ['instructionId', 'inst_' . uniqid()];

        yield 'status' => ['status', ExecutionStatus::SUCCESS];

        yield 'parameters' => ['parameters', json_encode(['param1' => 'value1', 'param2' => 123])];

        yield 'startTime' => ['startTime', new \DateTimeImmutable('2024-01-01 10:00:00')];

        yield 'endTime' => ['endTime', new \DateTimeImmutable('2024-01-01 10:05:00')];

        yield 'duration' => ['duration', 330];

        yield 'result' => ['result', 'Script executed successfully with result: OK'];

        yield 'logs' => ['logs', "[2024-01-01 10:00:00] Starting script\n[2024-01-01 10:00:01] Completed"];

        yield 'errorMessage' => ['errorMessage', 'Script execution failed: Syntax error on line 10'];

        yield 'retryCount' => ['retryCount', 2];

        yield 'output' => ['output', "Script executed successfully\nResult: OK"];

        yield 'executionMetrics' => ['executionMetrics', ['cpu_usage' => 45.5, 'memory_mb' => 128]];

        yield 'screenshots' => ['screenshots', ['/path/to/screenshot1.png', '/path/to/screenshot2.png']];

        yield 'createTime' => ['createTime', new \DateTimeImmutable('2024-01-01 10:00:00')];
    }
}
