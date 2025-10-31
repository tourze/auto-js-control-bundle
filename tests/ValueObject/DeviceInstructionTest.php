<?php

declare(strict_types=1);

namespace Tourze\AutoJsControlBundle\Tests\ValueObject;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tourze\AutoJsControlBundle\ValueObject\DeviceInstruction;

/**
 * @internal
 */
#[CoversClass(DeviceInstruction::class)]
final class DeviceInstructionTest extends TestCase
{
    #[Test]
    public function constructorSetsPropertiesCorrectly(): void
    {
        // Arrange
        $instructionId = 'INST_001';
        $type = DeviceInstruction::TYPE_EXECUTE_SCRIPT;
        $data = ['script_id' => 123, 'params' => ['key' => 'value']];
        $timeout = 600;
        $priority = 8;
        $taskId = 456;
        $scriptId = 789;
        $correlationId = 'CORR_001';

        // Act
        $instruction = new DeviceInstruction(
            $instructionId,
            $type,
            $data,
            $timeout,
            $priority,
            $taskId,
            $scriptId,
            $correlationId
        );

        // Assert
        $this->assertEquals($instructionId, $instruction->getInstructionId());
        $this->assertEquals($type, $instruction->getType());
        $this->assertEquals($data, $instruction->getData());
        $this->assertEquals($timeout, $instruction->getTimeout());
        $this->assertEquals($priority, $instruction->getPriority());
        $this->assertEquals($taskId, $instruction->getTaskId());
        $this->assertEquals($scriptId, $instruction->getScriptId());
        $this->assertEquals($correlationId, $instruction->getCorrelationId());
        $this->assertInstanceOf(\DateTimeImmutable::class, $instruction->getCreatedTime());
    }

    #[Test]
    public function constructorWithDefaultValues(): void
    {
        // Act
        $instruction = new DeviceInstruction('INST_001', DeviceInstruction::TYPE_PING);

        // Assert
        $this->assertEquals([], $instruction->getData());
        $this->assertEquals(300, $instruction->getTimeout());
        $this->assertEquals(5, $instruction->getPriority());
        $this->assertNull($instruction->getTaskId());
        $this->assertNull($instruction->getScriptId());
        $this->assertNull($instruction->getCorrelationId());
    }

    #[Test]
    public function isExpiredReturnsFalseForFreshInstruction(): void
    {
        // Arrange
        $instruction = new DeviceInstruction(
            'INST_001',
            DeviceInstruction::TYPE_PING,
            [],
            300 // 5 minutes timeout
        );

        // Act & Assert
        $this->assertFalse($instruction->isExpired());
    }

    #[Test]
    public function isExpiredReturnsTrueForExpiredInstruction(): void
    {
        // Arrange
        $instruction = new DeviceInstruction(
            'INST_001',
            DeviceInstruction::TYPE_PING,
            [],
            1 // 1 second timeout
        );

        // Use reflection to set createdTime to past time
        $reflection = new \ReflectionClass($instruction);
        $property = $reflection->getProperty('createdTime');
        $property->setAccessible(true);
        $property->setValue($instruction, new \DateTimeImmutable('-2 seconds'));

        // Act & Assert
        $this->assertTrue($instruction->isExpired());
    }

    #[Test]
    public function toArrayReturnsCorrectStructure(): void
    {
        // Arrange
        $instruction = new DeviceInstruction(
            'INST_001',
            DeviceInstruction::TYPE_EXECUTE_SCRIPT,
            ['script_id' => 123],
            600,
            8,
            456,
            789,
            'CORR_001'
        );

        // Act
        $array = $instruction->toArray();

        // Assert
        $this->assertEquals('INST_001', $array['instructionId']);
        $this->assertEquals(DeviceInstruction::TYPE_EXECUTE_SCRIPT, $array['type']);
        $this->assertEquals(['script_id' => 123], $array['data']);
        $this->assertEquals(600, $array['timeout']);
        $this->assertEquals(8, $array['priority']);
        $this->assertEquals(456, $array['taskId']);
        $this->assertEquals(789, $array['scriptId']);
        $this->assertEquals('CORR_001', $array['correlationId']);
        $this->assertArrayHasKey('createdTime', $array);
        $this->assertIsString($array['createdTime']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $array['createdTime']);
    }

    #[Test]
    public function fromArrayCreatesCorrectInstance(): void
    {
        // Arrange
        $data = [
            'instructionId' => 'INST_001',
            'type' => DeviceInstruction::TYPE_STOP_SCRIPT,
            'data' => ['script_id' => 123],
            'timeout' => 600,
            'priority' => 8,
            'taskId' => 456,
            'scriptId' => 789,
            'correlationId' => 'CORR_001',
            'createdTime' => '2023-01-01T10:00:00+00:00',
        ];

        // Act
        $instruction = DeviceInstruction::fromArray($data);

        // Assert
        $this->assertEquals('INST_001', $instruction->getInstructionId());
        $this->assertEquals(DeviceInstruction::TYPE_STOP_SCRIPT, $instruction->getType());
        $this->assertEquals(['script_id' => 123], $instruction->getData());
        $this->assertEquals(600, $instruction->getTimeout());
        $this->assertEquals(8, $instruction->getPriority());
        $this->assertEquals(456, $instruction->getTaskId());
        $this->assertEquals(789, $instruction->getScriptId());
        $this->assertEquals('CORR_001', $instruction->getCorrelationId());
        $this->assertEquals('2023-01-01T10:00:00+00:00', $instruction->getCreatedTime()->format(\DateTimeInterface::RFC3339));
    }

    #[Test]
    public function fromArrayWithMinimalData(): void
    {
        // Arrange
        $data = [
            'instructionId' => 'INST_001',
            'type' => DeviceInstruction::TYPE_PING,
        ];

        // Act
        $instruction = DeviceInstruction::fromArray($data);

        // Assert
        $this->assertEquals('INST_001', $instruction->getInstructionId());
        $this->assertEquals(DeviceInstruction::TYPE_PING, $instruction->getType());
        $this->assertEquals([], $instruction->getData());
        $this->assertEquals(300, $instruction->getTimeout());
        $this->assertEquals(5, $instruction->getPriority());
        $this->assertNull($instruction->getTaskId());
        $this->assertNull($instruction->getScriptId());
        $this->assertNull($instruction->getCorrelationId());
    }

    #[Test]
    public function roundTripConversion(): void
    {
        // Arrange
        $original = new DeviceInstruction(
            'INST_001',
            DeviceInstruction::TYPE_UPDATE_STATUS,
            ['status' => 'active'],
            600,
            8,
            456,
            789,
            'CORR_001'
        );

        // Act
        $array = $original->toArray();
        $reconstructed = DeviceInstruction::fromArray($array);

        // Assert
        $this->assertEquals($original->getInstructionId(), $reconstructed->getInstructionId());
        $this->assertEquals($original->getType(), $reconstructed->getType());
        $this->assertEquals($original->getData(), $reconstructed->getData());
        $this->assertEquals($original->getTimeout(), $reconstructed->getTimeout());
        $this->assertEquals($original->getPriority(), $reconstructed->getPriority());
        $this->assertEquals($original->getTaskId(), $reconstructed->getTaskId());
        $this->assertEquals($original->getScriptId(), $reconstructed->getScriptId());
        $this->assertEquals($original->getCorrelationId(), $reconstructed->getCorrelationId());
        $this->assertEquals($original->getCreatedTime()->format('c'), $reconstructed->getCreatedTime()->format('c'));
    }

    #[Test]
    public function allInstructionTypesAreDefined(): void
    {
        // Assert - verify all instruction type constants exist
        $this->assertEquals('execute_script', DeviceInstruction::TYPE_EXECUTE_SCRIPT);
        $this->assertEquals('stop_script', DeviceInstruction::TYPE_STOP_SCRIPT);
        $this->assertEquals('update_status', DeviceInstruction::TYPE_UPDATE_STATUS);
        $this->assertEquals('collect_log', DeviceInstruction::TYPE_COLLECT_LOG);
        $this->assertEquals('restart_app', DeviceInstruction::TYPE_RESTART_APP);
        $this->assertEquals('update_app', DeviceInstruction::TYPE_UPDATE_APP);
        $this->assertEquals('ping', DeviceInstruction::TYPE_PING);
    }
}
