<?php

declare(strict_types=1);

namespace Tourze\AutoJsControlBundle\Tests\Dto\Request;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tourze\AutoJsControlBundle\Dto\Request\LogEntry;
use Tourze\AutoJsControlBundle\Enum\LogLevel;
use Tourze\AutoJsControlBundle\Enum\LogType;

/**
 * @internal
 */
#[CoversClass(LogEntry::class)]
final class LogEntryTest extends TestCase
{
    #[Test]
    public function constructorSetsPropertiesCorrectly(): void
    {
        // Arrange
        $level = LogLevel::ERROR;
        $type = LogType::SCRIPT;
        $message = 'Test error message';
        $logTime = new \DateTimeImmutable();
        $context = 'Test context';
        $stackTrace = 'Test stack trace';

        // Act
        $logEntry = new LogEntry(
            $level,
            $type,
            $message,
            $logTime,
            $context,
            $stackTrace
        );

        // Assert
        $this->assertSame($level, $logEntry->getLevel());
        $this->assertSame($type, $logEntry->getType());
        $this->assertSame($message, $logEntry->getMessage());
        $this->assertSame($logTime, $logEntry->getLogTime());
        $this->assertSame($context, $logEntry->getContext());
        $this->assertSame($stackTrace, $logEntry->getStackTrace());
    }

    #[Test]
    public function fromArrayCreatesValidInstance(): void
    {
        // Arrange
        $data = [
            'level' => 'error',
            'type' => 'script',
            'message' => 'Test error message',
            'logTime' => '2023-01-01 12:00:00',
            'context' => 'Test context',
            'stackTrace' => 'Test stack trace',
        ];

        // Act
        $logEntry = LogEntry::fromArray($data);

        // Assert
        $this->assertSame(LogLevel::ERROR, $logEntry->getLevel());
        $this->assertSame(LogType::SCRIPT, $logEntry->getType());
        $this->assertSame('Test error message', $logEntry->getMessage());
        $this->assertSame('2023-01-01 12:00:00', $logEntry->getLogTime()->format('Y-m-d H:i:s'));
        $this->assertSame('Test context', $logEntry->getContext());
        $this->assertSame('Test stack trace', $logEntry->getStackTrace());
    }

    #[Test]
    public function fromArrayHandlesOptionalFields(): void
    {
        // Arrange
        $data = [
            'level' => 'info',
            'type' => 'system',
            'message' => 'Test message',
            'logTime' => '2023-01-01 12:00:00',
        ];

        // Act
        $logEntry = LogEntry::fromArray($data);

        // Assert
        $this->assertSame(LogLevel::INFO, $logEntry->getLevel());
        $this->assertSame(LogType::SYSTEM, $logEntry->getType());
        $this->assertSame('Test message', $logEntry->getMessage());
        $this->assertNull($logEntry->getContext());
        $this->assertNull($logEntry->getStackTrace());
    }
}
