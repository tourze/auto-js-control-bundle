<?php

declare(strict_types=1);

namespace Tourze\AutoJsControlBundle\Tests\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Test;
use Tourze\AutoJsControlBundle\Entity\AutoJsDevice;
use Tourze\AutoJsControlBundle\Event\InstructionSentEvent;
use Tourze\AutoJsControlBundle\ValueObject\DeviceInstruction;
use Tourze\PHPUnitSymfonyUnitTest\AbstractEventTestCase;

/**
 * @internal
 */
#[CoversClass(InstructionSentEvent::class)]
final class InstructionSentEventTest extends AbstractEventTestCase
{
    #[Test]
    public function constructorSetsProperties(): void
    {
        // Arrange
        $device = new AutoJsDevice();
        $instruction = new DeviceInstruction(
            instructionId: 'inst_001',
            type: 'execute_script',
            data: ['script_id' => 123, 'params' => ['timeout' => 5000]],
            priority: 5
        );

        // Act
        $event = new InstructionSentEvent($instruction, $device);

        // Assert
        $this->assertSame($instruction, $event->getInstruction());
        $this->assertSame($device, $event->getDevice());
    }

    #[Test]
    public function getInstructionTypeReturnsCorrectType(): void
    {
        // Arrange
        $device = new AutoJsDevice();
        $instruction = new DeviceInstruction('inst_002', 'update_config', ['key' => 'value']);
        $event = new InstructionSentEvent($instruction, $device);

        // Act & Assert
        $this->assertEquals('update_config', $event->getInstructionType());
    }

    #[Test]
    public function getInstructionContentReturnsCorrectData(): void
    {
        // Arrange
        $device = new AutoJsDevice();
        $data = [
            'action' => 'click',
            'x' => 100,
            'y' => 200,
            'delay' => 1000,
        ];
        $instruction = new DeviceInstruction('inst_003', 'user_action', $data);
        $event = new InstructionSentEvent($instruction, $device);

        // Act & Assert
        $this->assertEquals($data, $event->getInstructionContent());
    }

    #[Test]
    public function isHighPriorityReturnsCorrectValue(): void
    {
        $device = new AutoJsDevice();

        // High priority (> 5)
        $highPriorityInstruction = new DeviceInstruction('inst_004', 'urgent', [], priority: 9);
        $highPriorityEvent = new InstructionSentEvent($highPriorityInstruction, $device);
        $this->assertTrue($highPriorityEvent->isHighPriority());

        // Low priority (<= 5)
        $lowPriorityInstruction = new DeviceInstruction('inst_005', 'background', [], priority: 3);
        $lowPriorityEvent = new InstructionSentEvent($lowPriorityInstruction, $device);
        $this->assertFalse($lowPriorityEvent->isHighPriority());
    }

    #[Test]
    public function isSuccessReturnsCorrectValue(): void
    {
        $device = new AutoJsDevice();
        $instruction = new DeviceInstruction('inst_006', 'test', []);

        // Success case (default)
        $successEvent = new InstructionSentEvent($instruction, $device, true);
        $this->assertTrue($successEvent->isSuccess());

        // Failure case
        $failureEvent = new InstructionSentEvent($instruction, $device, false, 'Connection timeout');
        $this->assertFalse($failureEvent->isSuccess());
    }

    #[Test]
    public function getErrorMessageReturnsCorrectMessage(): void
    {
        $device = new AutoJsDevice();
        $instruction = new DeviceInstruction('inst_007', 'test', []);

        // With error message
        $eventWithError = new InstructionSentEvent($instruction, $device, false, 'Network error');
        $this->assertEquals('Network error', $eventWithError->getErrorMessage());

        // Without error message
        $eventWithoutError = new InstructionSentEvent($instruction, $device, true);
        $this->assertNull($eventWithoutError->getErrorMessage());
    }

    #[Test]
    public function getMetadataReturnsCorrectMetadata(): void
    {
        $device = new AutoJsDevice();
        $instruction = new DeviceInstruction('inst_008', 'test', []);
        $metadata = ['retry_count' => 3, 'source' => 'api'];

        $event = new InstructionSentEvent($instruction, $device, true, null, $metadata);
        $this->assertEquals($metadata, $event->getMetadata());

        // Empty metadata by default
        $eventWithoutMetadata = new InstructionSentEvent($instruction, $device);
        $this->assertEquals([], $eventWithoutMetadata->getMetadata());
    }

    #[Test]
    public function constructorWithAllParametersWorks(): void
    {
        $device = new AutoJsDevice();
        $instruction = new DeviceInstruction('inst_009', 'test_all', ['param' => 'value'], priority: 8);
        $metadata = ['context' => 'unit_test'];

        $event = new InstructionSentEvent(
            $instruction,
            $device,
            false,
            'Test error',
            $metadata
        );

        $this->assertSame($instruction, $event->getInstruction());
        $this->assertSame($device, $event->getDevice());
        $this->assertFalse($event->isSuccess());
        $this->assertEquals('Test error', $event->getErrorMessage());
        $this->assertEquals($metadata, $event->getMetadata());
        $this->assertEquals('test_all', $event->getInstructionType());
        $this->assertEquals(['param' => 'value'], $event->getInstructionContent());
        $this->assertTrue($event->isHighPriority()); // priority 8 > 5
    }
}
