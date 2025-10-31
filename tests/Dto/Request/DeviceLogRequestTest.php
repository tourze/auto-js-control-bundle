<?php

declare(strict_types=1);

namespace Tourze\AutoJsControlBundle\Tests\Dto\Request;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tourze\AutoJsControlBundle\Dto\Request\DeviceLogRequest;
use Tourze\AutoJsControlBundle\Dto\Request\LogEntry;
use Tourze\AutoJsControlBundle\Enum\LogLevel;
use Tourze\AutoJsControlBundle\Enum\LogType;

/**
 * @internal
 */
#[CoversClass(DeviceLogRequest::class)]
final class DeviceLogRequestTest extends TestCase
{
    #[Test]
    public function constructorSetsProperties(): void
    {
        // Arrange
        $deviceCode = 'TEST_DEVICE_001';
        $signature = 'test_signature';
        $timestamp = time();
        $logs = [
            [
                'level' => LogLevel::INFO->value,
                'type' => LogType::SCRIPT->value,
                'message' => 'Test log message 1',
                'logTime' => '2024-01-01 12:00:00',
                'context' => 'test context',
                'stackTrace' => null,
            ],
            [
                'level' => LogLevel::ERROR->value,
                'type' => LogType::SYSTEM->value,
                'message' => 'Test error message',
                'logTime' => '2024-01-01 12:01:00',
                'context' => null,
                'stackTrace' => 'Error stack trace',
            ],
        ];

        // Act
        $request = new DeviceLogRequest(
            deviceCode: $deviceCode,
            signature: $signature,
            timestamp: $timestamp,
            logs: $logs
        );

        // Assert
        $this->assertEquals($deviceCode, $request->getDeviceCode());
        $this->assertEquals($signature, $request->getSignature());
        $this->assertEquals($timestamp, $request->getTimestamp());
        $this->assertCount(2, $request->getLogs());

        $logEntries = $request->getLogs();
        $this->assertInstanceOf(LogEntry::class, $logEntries[0]);
        $this->assertEquals(LogLevel::INFO, $logEntries[0]->getLevel());
        $this->assertEquals(LogType::SCRIPT, $logEntries[0]->getType());
        $this->assertEquals('Test log message 1', $logEntries[0]->getMessage());
        $this->assertEquals('test context', $logEntries[0]->getContext());
        $this->assertNull($logEntries[0]->getStackTrace());
    }

    #[Test]
    public function verifySignatureWithValidSignature(): void
    {
        // Arrange
        $deviceCode = 'DEVICE_002';
        $timestamp = time();
        $certificate = 'test_certificate';
        $logs = [
            [
                'level' => LogLevel::INFO->value,
                'type' => LogType::SCRIPT->value,
                'message' => 'Test message',
                'logTime' => '2024-01-01 12:00:00',
            ],
        ];

        $data = sprintf('%s:%d:%d:%s', $deviceCode, $timestamp, count($logs), $certificate);
        $signature = hash_hmac('sha256', $data, $certificate);

        $request = new DeviceLogRequest(
            deviceCode: $deviceCode,
            signature: $signature,
            timestamp: $timestamp,
            logs: $logs
        );

        // Act & Assert
        $this->assertTrue($request->verifySignature($certificate));
    }

    #[Test]
    public function verifySignatureWithInvalidSignature(): void
    {
        // Arrange
        $request = new DeviceLogRequest(
            deviceCode: 'DEVICE_003',
            signature: 'invalid_signature',
            timestamp: time(),
            logs: []
        );

        // Act & Assert
        $this->assertFalse($request->verifySignature('test_certificate'));
    }

    #[Test]
    public function constructorWithEmptyLogs(): void
    {
        // Arrange
        $deviceCode = 'DEVICE_004';
        $signature = 'signature_004';
        $timestamp = time();

        // Act
        $request = new DeviceLogRequest(
            deviceCode: $deviceCode,
            signature: $signature,
            timestamp: $timestamp,
            logs: []
        );

        // Assert
        $this->assertEquals($deviceCode, $request->getDeviceCode());
        $this->assertCount(0, $request->getLogs());
    }

    #[Test]
    public function logEntryConstructorSetsProperties(): void
    {
        // Arrange
        $level = LogLevel::WARNING;
        $type = LogType::CONNECTION;
        $message = 'Connection warning';
        $logTime = new \DateTimeImmutable('2024-01-01 14:30:00');
        $context = 'connection context';
        $stackTrace = 'stack trace info';

        // Act
        $logEntry = new LogEntry(
            level: $level,
            type: $type,
            message: $message,
            logTime: $logTime,
            context: $context,
            stackTrace: $stackTrace
        );

        // Assert
        $this->assertEquals($level, $logEntry->getLevel());
        $this->assertEquals($type, $logEntry->getType());
        $this->assertEquals($message, $logEntry->getMessage());
        $this->assertEquals($logTime, $logEntry->getLogTime());
        $this->assertEquals($context, $logEntry->getContext());
        $this->assertEquals($stackTrace, $logEntry->getStackTrace());
    }

    #[Test]
    public function logEntryFromArrayCreatesInstanceCorrectly(): void
    {
        // Arrange
        $data = [
            'level' => LogLevel::CRITICAL->value,
            'type' => LogType::SYSTEM->value,
            'message' => 'Critical system error',
            'logTime' => '2024-01-01 16:00:00',
            'context' => 'system critical',
            'stackTrace' => 'full stack trace',
        ];

        // Act
        $logEntry = LogEntry::fromArray($data);

        // Assert
        $this->assertEquals(LogLevel::CRITICAL, $logEntry->getLevel());
        $this->assertEquals(LogType::SYSTEM, $logEntry->getType());
        $this->assertEquals('Critical system error', $logEntry->getMessage());
        $this->assertInstanceOf(\DateTimeImmutable::class, $logEntry->getLogTime());
        $this->assertEquals('system critical', $logEntry->getContext());
        $this->assertEquals('full stack trace', $logEntry->getStackTrace());
    }

    #[Test]
    public function logEntryFromArrayWithMinimalData(): void
    {
        // Arrange
        $data = [
            'level' => LogLevel::DEBUG->value,
            'type' => LogType::COMMAND->value,
            'message' => 'Debug message',
            'logTime' => '2024-01-01 18:00:00',
        ];

        // Act
        $logEntry = LogEntry::fromArray($data);

        // Assert
        $this->assertEquals(LogLevel::DEBUG, $logEntry->getLevel());
        $this->assertEquals(LogType::COMMAND, $logEntry->getType());
        $this->assertEquals('Debug message', $logEntry->getMessage());
        $this->assertNull($logEntry->getContext());
        $this->assertNull($logEntry->getStackTrace());
    }
}
