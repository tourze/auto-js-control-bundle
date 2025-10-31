<?php

declare(strict_types=1);

namespace Tourze\AutoJsControlBundle\Tests\ValueObject;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tourze\AutoJsControlBundle\ValueObject\InstructionExecutionContext;

/**
 * @internal
 */
#[CoversClass(InstructionExecutionContext::class)]
final class InstructionExecutionContextTest extends TestCase
{
    #[Test]
    public function constructorSetsPropertiesCorrectly(): void
    {
        // Arrange
        $instructionId = 'INST_001';
        $deviceCode = 'DEVICE_001';
        $instructionType = 'execute_script';
        $parameters = ['script' => 'test.js', 'args' => ['a', 'b']];
        $scheduledTime = new \DateTimeImmutable('2023-01-01 10:00:00');
        $priority = 8;
        $retryCount = 1;
        $maxRetries = 5;
        $taskId = 123;
        $scriptId = 456;
        $userId = 'user_789';
        $metadata = ['source' => 'api', 'version' => '1.0'];

        // Act
        $context = new InstructionExecutionContext(
            $instructionId,
            $deviceCode,
            $instructionType,
            $parameters,
            $scheduledTime,
            $priority,
            $retryCount,
            $maxRetries,
            $taskId,
            $scriptId,
            $userId,
            $metadata
        );

        // Assert
        $this->assertEquals($instructionId, $context->getInstructionId());
        $this->assertEquals($deviceCode, $context->getDeviceCode());
        $this->assertEquals($instructionType, $context->getInstructionType());
        $this->assertEquals($parameters, $context->getParameters());
        $this->assertEquals($scheduledTime, $context->getScheduledTime());
        $this->assertEquals($priority, $context->getPriority());
        $this->assertEquals($retryCount, $context->getRetryCount());
        $this->assertEquals($maxRetries, $context->getMaxRetries());
        $this->assertEquals($taskId, $context->getTaskId());
        $this->assertEquals($scriptId, $context->getScriptId());
        $this->assertEquals($userId, $context->getUserId());
        $this->assertEquals($metadata, $context->getMetadata());
    }

    #[Test]
    public function constructorWithDefaultValues(): void
    {
        // Act
        $context = new InstructionExecutionContext(
            'INST_001',
            'DEVICE_001',
            'ping'
        );

        // Assert
        $this->assertEquals([], $context->getParameters());
        $this->assertInstanceOf(\DateTimeImmutable::class, $context->getScheduledTime());
        $this->assertEquals(5, $context->getPriority());
        $this->assertEquals(0, $context->getRetryCount());
        $this->assertEquals(3, $context->getMaxRetries());
        $this->assertNull($context->getTaskId());
        $this->assertNull($context->getScriptId());
        $this->assertNull($context->getUserId());
        $this->assertEquals([], $context->getMetadata());
    }

    #[Test]
    public function canRetryReturnsTrueWhenRetriesAvailable(): void
    {
        // Arrange
        $context = new InstructionExecutionContext(
            'INST_001',
            'DEVICE_001',
            'execute_script',
            [],
            null,
            5,
            1, // retryCount
            3  // maxRetries
        );

        // Act & Assert
        $this->assertTrue($context->canRetry());
    }

    #[Test]
    public function canRetryReturnsFalseWhenMaxRetriesReached(): void
    {
        // Arrange
        $context = new InstructionExecutionContext(
            'INST_001',
            'DEVICE_001',
            'execute_script',
            [],
            null,
            5,
            3, // retryCount
            3  // maxRetries
        );

        // Act & Assert
        $this->assertFalse($context->canRetry());
    }

    #[Test]
    public function incrementRetryCountCreatesNewInstance(): void
    {
        // Arrange
        $original = new InstructionExecutionContext(
            'INST_001',
            'DEVICE_001',
            'execute_script',
            [],
            null,
            5,
            1, // retryCount
            3  // maxRetries
        );

        // Act
        $incremented = $original->incrementRetryCount();

        // Assert
        $this->assertNotSame($original, $incremented);
        $this->assertEquals(1, $original->getRetryCount());
        $this->assertEquals(2, $incremented->getRetryCount());
    }

    #[Test]
    public function shouldExecuteNowReturnsTrueForPastTime(): void
    {
        // Arrange
        $context = new InstructionExecutionContext(
            'INST_001',
            'DEVICE_001',
            'execute_script',
            [],
            new \DateTimeImmutable('-1 hour')
        );

        // Act & Assert
        $this->assertTrue($context->shouldExecuteNow());
    }

    #[Test]
    public function shouldExecuteNowReturnsFalseForFutureTime(): void
    {
        // Arrange
        $context = new InstructionExecutionContext(
            'INST_001',
            'DEVICE_001',
            'execute_script',
            [],
            new \DateTimeImmutable('+1 hour')
        );

        // Act & Assert
        $this->assertFalse($context->shouldExecuteNow());
    }

    #[Test]
    public function getDelaySecondsReturnsCorrectValueForFutureTime(): void
    {
        // Arrange
        $futureTime = new \DateTimeImmutable('+60 seconds');
        $context = new InstructionExecutionContext(
            'INST_001',
            'DEVICE_001',
            'execute_script',
            [],
            $futureTime
        );

        // Act
        $delay = $context->getDelaySeconds();

        // Assert
        $this->assertGreaterThanOrEqual(59, $delay);
        $this->assertLessThanOrEqual(61, $delay);
    }

    #[Test]
    public function getDelaySecondsReturnsZeroForPastTime(): void
    {
        // Arrange
        $pastTime = new \DateTimeImmutable('-60 seconds');
        $context = new InstructionExecutionContext(
            'INST_001',
            'DEVICE_001',
            'execute_script',
            [],
            $pastTime
        );

        // Act
        $delay = $context->getDelaySeconds();

        // Assert
        $this->assertEquals(0, $delay);
    }

    #[Test]
    public function toArrayReturnsCorrectStructure(): void
    {
        // Arrange
        $scheduledTime = new \DateTimeImmutable('2023-01-01 10:00:00');
        $context = new InstructionExecutionContext(
            'INST_001',
            'DEVICE_001',
            'execute_script',
            ['script' => 'test.js'],
            $scheduledTime,
            8,
            2,
            5,
            123,
            456,
            'user_789',
            ['source' => 'api']
        );

        // Act
        $array = $context->toArray();

        // Assert
        $this->assertEquals([
            'instructionId' => 'INST_001',
            'deviceCode' => 'DEVICE_001',
            'instructionType' => 'execute_script',
            'parameters' => ['script' => 'test.js'],
            'scheduledTime' => $scheduledTime->format(\DateTimeInterface::RFC3339),
            'priority' => 8,
            'retryCount' => 2,
            'maxRetries' => 5,
            'taskId' => 123,
            'scriptId' => 456,
            'userId' => 'user_789',
            'metadata' => ['source' => 'api'],
        ], $array);
    }

    #[Test]
    public function fromArrayCreatesCorrectInstance(): void
    {
        // Arrange
        $data = [
            'instructionId' => 'INST_001',
            'deviceCode' => 'DEVICE_001',
            'instructionType' => 'execute_script',
            'parameters' => ['script' => 'test.js'],
            'scheduledTime' => '2023-01-01T10:00:00+00:00',
            'priority' => 8,
            'retryCount' => 2,
            'maxRetries' => 5,
            'taskId' => 123,
            'scriptId' => 456,
            'userId' => 'user_789',
            'metadata' => ['source' => 'api'],
        ];

        // Act
        $context = InstructionExecutionContext::fromArray($data);

        // Assert
        $this->assertEquals('INST_001', $context->getInstructionId());
        $this->assertEquals('DEVICE_001', $context->getDeviceCode());
        $this->assertEquals('execute_script', $context->getInstructionType());
        $this->assertEquals(['script' => 'test.js'], $context->getParameters());
        $this->assertEquals('2023-01-01T10:00:00+00:00', $context->getScheduledTime()->format(\DateTimeInterface::RFC3339));
        $this->assertEquals(8, $context->getPriority());
        $this->assertEquals(2, $context->getRetryCount());
        $this->assertEquals(5, $context->getMaxRetries());
        $this->assertEquals(123, $context->getTaskId());
        $this->assertEquals(456, $context->getScriptId());
        $this->assertEquals('user_789', $context->getUserId());
        $this->assertEquals(['source' => 'api'], $context->getMetadata());
    }

    #[Test]
    public function fromArrayWithMinimalData(): void
    {
        // Arrange
        $data = [
            'instructionId' => 'INST_001',
            'deviceCode' => 'DEVICE_001',
            'instructionType' => 'ping',
            'scheduledTime' => '2023-01-01T10:00:00+00:00',
        ];

        // Act
        $context = InstructionExecutionContext::fromArray($data);

        // Assert
        $this->assertEquals('INST_001', $context->getInstructionId());
        $this->assertEquals('DEVICE_001', $context->getDeviceCode());
        $this->assertEquals('ping', $context->getInstructionType());
        $this->assertEquals([], $context->getParameters());
        $this->assertEquals(5, $context->getPriority());
        $this->assertEquals(0, $context->getRetryCount());
        $this->assertEquals(3, $context->getMaxRetries());
        $this->assertNull($context->getTaskId());
        $this->assertNull($context->getScriptId());
        $this->assertNull($context->getUserId());
        $this->assertEquals([], $context->getMetadata());
    }

    #[Test]
    public function roundTripConversion(): void
    {
        // Arrange
        $original = new InstructionExecutionContext(
            'INST_001',
            'DEVICE_001',
            'execute_script',
            ['script' => 'test.js'],
            new \DateTimeImmutable('2023-01-01 10:00:00'),
            8,
            2,
            5,
            123,
            456,
            'user_789',
            ['source' => 'api']
        );

        // Act
        $array = $original->toArray();
        $reconstructed = InstructionExecutionContext::fromArray($array);

        // Assert
        $this->assertEquals($original->getInstructionId(), $reconstructed->getInstructionId());
        $this->assertEquals($original->getDeviceCode(), $reconstructed->getDeviceCode());
        $this->assertEquals($original->getInstructionType(), $reconstructed->getInstructionType());
        $this->assertEquals($original->getParameters(), $reconstructed->getParameters());
        $this->assertEquals($original->getScheduledTime()->format('c'), $reconstructed->getScheduledTime()->format('c'));
        $this->assertEquals($original->getPriority(), $reconstructed->getPriority());
        $this->assertEquals($original->getRetryCount(), $reconstructed->getRetryCount());
        $this->assertEquals($original->getMaxRetries(), $reconstructed->getMaxRetries());
        $this->assertEquals($original->getTaskId(), $reconstructed->getTaskId());
        $this->assertEquals($original->getScriptId(), $reconstructed->getScriptId());
        $this->assertEquals($original->getUserId(), $reconstructed->getUserId());
        $this->assertEquals($original->getMetadata(), $reconstructed->getMetadata());
    }
}
