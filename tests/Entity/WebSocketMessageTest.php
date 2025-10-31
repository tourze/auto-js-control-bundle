<?php

declare(strict_types=1);

namespace Tourze\AutoJsControlBundle\Tests\Entity;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tourze\AutoJsControlBundle\Entity\AutoJsDevice;
use Tourze\AutoJsControlBundle\Entity\WebSocketMessage;
use Tourze\PHPUnitDoctrineEntity\AbstractEntityTestCase;

/**
 * @internal
 */
#[CoversClass(WebSocketMessage::class)]
final class WebSocketMessageTest extends AbstractEntityTestCase
{
    private WebSocketMessage $message;

    protected function createEntity(): object
    {
        return new WebSocketMessage();
    }

    protected function setUp(): void
    {
        $this->message = new WebSocketMessage();
    }

    #[Test]
    public function constructorSetsDefaultValues(): void
    {
        // Assert
        $this->assertNull($this->message->getId());
        $this->assertNull($this->message->getMessageId());
        $this->assertNull($this->message->getAutoJsDevice());
        $this->assertEquals('command', $this->message->getMessageType());
        $this->assertNull($this->message->getContent());
        $this->assertEquals('out', $this->message->getDirection());
        $this->assertFalse($this->message->isProcessed());
        $this->assertNull($this->message->getProcessStatus());
        $this->assertNull($this->message->getProcessResult());
        $this->assertNull($this->message->getProcessTime());
        $this->assertNull($this->message->getCreateTime());
    }

    #[Test]
    public function setMessageIdSetsAndGetsCorrectly(): void
    {
        // Arrange
        $messageId = 'msg_' . uniqid();

        // Act
        $this->message->setMessageId($messageId);

        // Assert
        $this->assertEquals($messageId, $this->message->getMessageId());
    }

    #[Test]
    public function setAutoJsDeviceSetsAndGetsCorrectly(): void
    {
        // Arrange
        $device = new AutoJsDevice();

        // Act
        $this->message->setAutoJsDevice($device);

        // Assert
        $this->assertSame($device, $this->message->getAutoJsDevice());
    }

    #[Test]
    public function setMessageTypeSetsAndGetsCorrectly(): void
    {
        // Arrange
        $type = 'instruction';

        // Act
        $this->message->setMessageType($type);

        // Assert
        $this->assertEquals($type, $this->message->getMessageType());
    }

    #[Test]
    public function setContentSetsAndGetsCorrectly(): void
    {
        // Arrange
        $content = json_encode([
            'action' => 'execute_script',
            'script_id' => 123,
            'parameters' => ['timeout' => 5000],
        ]);
        $this->assertIsString($content);

        // Act
        $this->message->setContent($content);

        // Assert
        $this->assertEquals($content, $this->message->getContent());
    }

    #[Test]
    public function setDirectionSetsAndGetsCorrectly(): void
    {
        // Act & Assert - out
        $this->message->setDirection('out');
        $this->assertEquals('out', $this->message->getDirection());

        // Act & Assert - in
        $this->message->setDirection('in');
        $this->assertEquals('in', $this->message->getDirection());
    }

    #[Test]
    public function setProcessStatusSetsAndGetsCorrectly(): void
    {
        // Arrange
        $statuses = ['success', 'failed', 'timeout'];

        foreach ($statuses as $status) {
            // Act
            $this->message->setProcessStatus($status);

            // Assert
            $this->assertEquals($status, $this->message->getProcessStatus());
        }
    }

    #[Test]
    public function setProcessResultSetsAndGetsCorrectly(): void
    {
        // Arrange
        $result = 'Process completed successfully';

        // Act
        $this->message->setProcessResult($result);

        // Assert
        $this->assertEquals($result, $this->message->getProcessResult());
    }

    #[Test]
    public function setProcessTimeSetsAndGetsCorrectly(): void
    {
        // Arrange
        $processTime = new \DateTimeImmutable('2024-01-01 12:00:00');

        // Act
        $this->message->setProcessTime($processTime);

        // Assert
        $this->assertEquals($processTime, $this->message->getProcessTime());
    }

    #[Test]
    public function setIsProcessedSetsAndGetsCorrectly(): void
    {
        // Act & Assert - true
        $this->message->setIsProcessed(true);
        $this->assertTrue($this->message->isProcessed());

        // Act & Assert - false
        $this->message->setIsProcessed(false);
        $this->assertFalse($this->message->isProcessed());
    }

    #[Test]
    public function toStringReturnsCorrectFormat(): void
    {
        // Test with default type and no message ID
        $this->assertEquals('[command] new', (string) $this->message);

        // Test with message ID
        $this->message->setMessageId('msg_12345');
        $this->assertEquals('[command] msg_12345', (string) $this->message);

        // Test with different type
        $this->message->setMessageType('heartbeat');
        $this->assertEquals('[heartbeat] msg_12345', (string) $this->message);
    }

    #[Test]
    public function directionWorksCorrectly(): void
    {
        // Default is out
        $this->assertEquals('out', $this->message->getDirection());

        // Set to in
        $this->message->setDirection('in');
        $this->assertEquals('in', $this->message->getDirection());

        // Set back to out
        $this->message->setDirection('out');
        $this->assertEquals('out', $this->message->getDirection());
    }

    #[Test]
    public function timestampableTraitSetsTimestamps(): void
    {
        // Arrange
        $now = new \DateTimeImmutable();

        // Act
        $this->message->setCreateTime($now);

        // Assert
        $this->assertSame($now, $this->message->getCreateTime());
    }

    /**
     * 提供属性及其样本值的 Data Provider.
     *
     * @return iterable<string, array{string, mixed}>
     */
    public static function propertiesProvider(): iterable
    {
        yield 'messageId' => ['messageId', 'msg_' . uniqid()];

        yield 'messageType' => ['messageType', 'instruction'];

        yield 'content' => ['content', json_encode([
            'action' => 'execute_script',
            'script_id' => 123,
            'parameters' => ['timeout' => 5000],
        ])];

        yield 'direction' => ['direction', 'in'];

        // yield 'processed' => ['processed', true]; // 暂时注释，AbstractEntityTest 不支持 is 前缀的属性名

        yield 'processStatus' => ['processStatus', 'success'];

        yield 'processResult' => ['processResult', 'Process completed successfully'];

        yield 'processTime' => ['processTime', new \DateTimeImmutable('2024-01-01 12:00:00')];

        yield 'createTime' => ['createTime', new \DateTimeImmutable('2024-01-01 10:00:00')];
    }
}
