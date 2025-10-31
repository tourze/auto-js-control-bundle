<?php

declare(strict_types=1);

namespace Tourze\AutoJsControlBundle\Tests\Entity;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tourze\AutoJsControlBundle\Entity\AutoJsDevice;
use Tourze\AutoJsControlBundle\Entity\DeviceLog;
use Tourze\AutoJsControlBundle\Enum\LogLevel;
use Tourze\AutoJsControlBundle\Enum\LogType;
use Tourze\PHPUnitDoctrineEntity\AbstractEntityTestCase;

/**
 * @internal
 */
#[CoversClass(DeviceLog::class)]
final class DeviceLogTest extends AbstractEntityTestCase
{
    private DeviceLog $deviceLog;

    protected function createEntity(): object
    {
        return new DeviceLog();
    }

    protected function setUp(): void
    {
        $this->deviceLog = new DeviceLog();
    }

    #[Test]
    public function constructorSetsDefaultValues(): void
    {
        // Assert
        $this->assertNull($this->deviceLog->getId());
        $this->assertNull($this->deviceLog->getAutoJsDevice());
        $this->assertEquals(LogType::SYSTEM, $this->deviceLog->getLogType());
        $this->assertEquals(LogLevel::INFO, $this->deviceLog->getLogLevel());
        $this->assertNull($this->deviceLog->getTitle());
        $this->assertNull($this->deviceLog->getContent());
        $this->assertNull($this->deviceLog->getContext());
        $this->assertNull($this->deviceLog->getLogTime());
        $this->assertNull($this->deviceLog->getMessage());
        $this->assertNull($this->deviceLog->getDeviceIp());
        $this->assertNull($this->deviceLog->getStackTrace());
        $this->assertNull($this->deviceLog->getCreateTime());
    }

    #[Test]
    public function setAutoJsDeviceSetsAndGetsCorrectly(): void
    {
        // Arrange
        $device = new AutoJsDevice();

        // Act
        $this->deviceLog->setAutoJsDevice($device);

        // Assert
        $this->assertSame($device, $this->deviceLog->getAutoJsDevice());
    }

    #[Test]
    public function setLogTypeSetsAndGetsCorrectly(): void
    {
        // Arrange
        $type = LogType::SCRIPT;

        // Act
        $this->deviceLog->setLogType($type);

        // Assert
        $this->assertEquals($type, $this->deviceLog->getLogType());
    }

    #[Test]
    public function setLogLevelSetsAndGetsCorrectly(): void
    {
        // Arrange
        $level = LogLevel::ERROR;

        // Act
        $this->deviceLog->setLogLevel($level);

        // Assert
        $this->assertEquals($level, $this->deviceLog->getLogLevel());
    }

    #[Test]
    public function setLevelAliasWorksCorrectly(): void
    {
        // Arrange
        $level = LogLevel::WARNING;

        // Act
        $this->deviceLog->setLevel($level);

        // Assert
        $this->assertEquals($level, $this->deviceLog->getLogLevel());
    }

    #[Test]
    public function setTitleSetsAndGetsCorrectly(): void
    {
        // Arrange
        $title = 'Script Execution Error';

        // Act
        $this->deviceLog->setTitle($title);

        // Assert
        $this->assertEquals($title, $this->deviceLog->getTitle());
    }

    #[Test]
    public function setContentSetsAndGetsCorrectly(): void
    {
        // Arrange
        $content = 'Error details: Script failed to execute due to syntax error';

        // Act
        $this->deviceLog->setContent($content);

        // Assert
        $this->assertEquals($content, $this->deviceLog->getContent());
    }

    #[Test]
    public function setContextSetsAndGetsCorrectly(): void
    {
        // Arrange
        $contextData = [
            'error_code' => 'SYNTAX_ERROR',
            'line' => 42,
            'column' => 15,
            'script_id' => 123,
        ];
        $context = json_encode($contextData);
        $this->assertIsString($context, 'JSON encoding should not fail');

        // Act
        $this->deviceLog->setContext($context);

        // Assert
        $this->assertEquals($context, $this->deviceLog->getContext());
    }

    #[Test]
    public function setLogTimeSetsAndGetsCorrectly(): void
    {
        // Arrange
        $logTime = new \DateTimeImmutable('2024-01-01 12:00:00');

        // Act
        $this->deviceLog->setLogTime($logTime);

        // Assert
        $this->assertEquals($logTime, $this->deviceLog->getLogTime());
    }

    #[Test]
    public function toStringReturnsCorrectFormat(): void
    {
        // Test with default level and no title
        $this->assertEquals('[info] 未命名日志', (string) $this->deviceLog);

        // Test with title
        $this->deviceLog->setTitle('测试日志');
        $this->assertEquals('[info] 测试日志', (string) $this->deviceLog);

        // Test with different level
        $this->deviceLog->setLogLevel(LogLevel::ERROR);
        $this->assertEquals('[error] 测试日志', (string) $this->deviceLog);
    }

    #[Test]
    public function setDeviceIpSetsAndGetsCorrectly(): void
    {
        // Arrange
        $ip = '192.168.1.100';

        // Act
        $this->deviceLog->setDeviceIp($ip);

        // Assert
        $this->assertEquals($ip, $this->deviceLog->getDeviceIp());
    }

    #[Test]
    public function setMessageSetsAndGetsCorrectly(): void
    {
        // Arrange
        $message = 'This is a detailed log message';

        // Act
        $this->deviceLog->setMessage($message);

        // Assert
        $this->assertEquals($message, $this->deviceLog->getMessage());
    }

    #[Test]
    public function setStackTraceSetsAndGetsCorrectly(): void
    {
        // Arrange
        $stackTrace = "Error at line 42\nCalled from script.js:100";

        // Act
        $this->deviceLog->setStackTrace($stackTrace);

        // Assert
        $this->assertEquals($stackTrace, $this->deviceLog->getStackTrace());
    }

    #[Test]
    public function timestampableTraitSetsTimestamps(): void
    {
        // Arrange
        $now = new \DateTimeImmutable();

        // Act
        $this->deviceLog->setCreateTime($now);

        // Assert
        $this->assertSame($now, $this->deviceLog->getCreateTime());
    }

    /**
     * 提供属性及其样本值的 Data Provider.
     *
     * @return iterable<string, array{string, mixed}>
     */
    public static function propertiesProvider(): iterable
    {
        yield 'logLevel' => ['logLevel', LogLevel::ERROR];

        yield 'logType' => ['logType', LogType::SCRIPT];

        yield 'title' => ['title', 'Script Execution Error'];

        yield 'content' => ['content', 'Error details: Script failed to execute due to syntax error'];

        yield 'context' => ['context', json_encode([
            'error_code' => 'SYNTAX_ERROR',
            'line' => 42,
            'column' => 15,
            'script_id' => 123,
        ])];

        yield 'deviceIp' => ['deviceIp', '192.168.1.100'];

        yield 'message' => ['message', 'This is a detailed log message'];

        yield 'logTime' => ['logTime', new \DateTimeImmutable('2024-01-01 12:00:00')];

        yield 'stackTrace' => ['stackTrace', "Error at line 42\nCalled from script.js:100"];

        yield 'createTime' => ['createTime', new \DateTimeImmutable('2024-01-01 10:00:00')];
    }
}
