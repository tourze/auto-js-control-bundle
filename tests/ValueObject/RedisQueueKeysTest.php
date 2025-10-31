<?php

declare(strict_types=1);

namespace Tourze\AutoJsControlBundle\Tests\ValueObject;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\AutoJsControlBundle\ValueObject\RedisQueueKeys;

/**
 * @internal
 */
#[CoversClass(RedisQueueKeys::class)]
final class RedisQueueKeysTest extends TestCase
{
    public function testStaticMethodsExist(): void
    {
        // Test that the class has the expected static methods
        $this->assertEquals('device_instruction_queue:TEST', RedisQueueKeys::getDeviceInstructionQueue('TEST'));
        $this->assertEquals('device_poll_notify:TEST', RedisQueueKeys::getDevicePollNotify('TEST'));
    }

    public function testConstantsAreDefined(): void
    {
        $this->assertIsString(RedisQueueKeys::DEVICE_INSTRUCTION_QUEUE);
        $this->assertIsString(RedisQueueKeys::DEVICE_POLL_NOTIFY);
        $this->assertIsString(RedisQueueKeys::DEVICE_ONLINE);
        $this->assertIsString(RedisQueueKeys::INSTRUCTION_STATUS);
        $this->assertIsString(RedisQueueKeys::DEVICE_LAST_HEARTBEAT);
        $this->assertIsString(RedisQueueKeys::GLOBAL_TASK_QUEUE);
        $this->assertIsString(RedisQueueKeys::GROUP_TASK_QUEUE);
        $this->assertIsString(RedisQueueKeys::DEVICE_LOCK);
        $this->assertIsString(RedisQueueKeys::INSTRUCTION_RETRY);
        $this->assertIsString(RedisQueueKeys::DEVICE_METRICS);
    }

    public function testConstantValues(): void
    {
        $this->assertEquals('device_instruction_queue:%s', RedisQueueKeys::DEVICE_INSTRUCTION_QUEUE);
        $this->assertEquals('device_poll_notify:%s', RedisQueueKeys::DEVICE_POLL_NOTIFY);
        $this->assertEquals('device_online:%s', RedisQueueKeys::DEVICE_ONLINE);
        $this->assertEquals('instruction_status:%s', RedisQueueKeys::INSTRUCTION_STATUS);
        $this->assertEquals('device_last_heartbeat:%s', RedisQueueKeys::DEVICE_LAST_HEARTBEAT);
        $this->assertEquals('global_task_queue', RedisQueueKeys::GLOBAL_TASK_QUEUE);
        $this->assertEquals('group_task_queue:%s', RedisQueueKeys::GROUP_TASK_QUEUE);
        $this->assertEquals('device_lock:%s', RedisQueueKeys::DEVICE_LOCK);
        $this->assertEquals('instruction_retry:%s', RedisQueueKeys::INSTRUCTION_RETRY);
        $this->assertEquals('device_metrics:%s', RedisQueueKeys::DEVICE_METRICS);
    }

    public function testTtlConstants(): void
    {
        $this->assertIsInt(RedisQueueKeys::TTL_ONLINE_STATUS);
        $this->assertIsInt(RedisQueueKeys::TTL_INSTRUCTION_STATUS);
        $this->assertIsInt(RedisQueueKeys::TTL_HEARTBEAT);
        $this->assertIsInt(RedisQueueKeys::TTL_LOCK);
        $this->assertIsInt(RedisQueueKeys::TTL_RETRY_COUNTER);
        $this->assertIsInt(RedisQueueKeys::TTL_METRICS);

        $this->assertEquals(120, RedisQueueKeys::TTL_ONLINE_STATUS);
        $this->assertEquals(3600, RedisQueueKeys::TTL_INSTRUCTION_STATUS);
        $this->assertEquals(300, RedisQueueKeys::TTL_HEARTBEAT);
        $this->assertEquals(30, RedisQueueKeys::TTL_LOCK);
        $this->assertEquals(1800, RedisQueueKeys::TTL_RETRY_COUNTER);
        $this->assertEquals(86400, RedisQueueKeys::TTL_METRICS);
    }

    public function testGetDeviceInstructionQueue(): void
    {
        // Arrange
        $deviceCode = 'DEVICE_001';

        // Act
        $result = RedisQueueKeys::getDeviceInstructionQueue($deviceCode);

        // Assert
        $this->assertEquals('device_instruction_queue:DEVICE_001', $result);
    }

    public function testGetDeviceInstructionQueueWithSpecialCharacters(): void
    {
        // Arrange
        $deviceCode = 'DEVICE-001_TEST';

        // Act
        $result = RedisQueueKeys::getDeviceInstructionQueue($deviceCode);

        // Assert
        $this->assertEquals('device_instruction_queue:DEVICE-001_TEST', $result);
    }

    public function testGetDevicePollNotify(): void
    {
        // Arrange
        $deviceCode = 'DEVICE_002';

        // Act
        $result = RedisQueueKeys::getDevicePollNotify($deviceCode);

        // Assert
        $this->assertEquals('device_poll_notify:DEVICE_002', $result);
    }

    public function testGetDeviceOnline(): void
    {
        // Arrange
        $deviceCode = 'DEVICE_003';

        // Act
        $result = RedisQueueKeys::getDeviceOnline($deviceCode);

        // Assert
        $this->assertEquals('device_online:DEVICE_003', $result);
    }

    public function testGetInstructionStatus(): void
    {
        // Arrange
        $instructionId = 'INSTRUCTION_123';

        // Act
        $result = RedisQueueKeys::getInstructionStatus($instructionId);

        // Assert
        $this->assertEquals('instruction_status:INSTRUCTION_123', $result);
    }

    public function testGetInstructionStatusWithUuid(): void
    {
        // Arrange
        $instructionId = '550e8400-e29b-41d4-a716-446655440000';

        // Act
        $result = RedisQueueKeys::getInstructionStatus($instructionId);

        // Assert
        $this->assertEquals('instruction_status:550e8400-e29b-41d4-a716-446655440000', $result);
    }

    public function testGetDeviceLastHeartbeat(): void
    {
        // Arrange
        $deviceCode = 'DEVICE_004';

        // Act
        $result = RedisQueueKeys::getDeviceLastHeartbeat($deviceCode);

        // Assert
        $this->assertEquals('device_last_heartbeat:DEVICE_004', $result);
    }

    public function testGetGroupTaskQueue(): void
    {
        // Arrange
        $groupId = 42;

        // Act
        $result = RedisQueueKeys::getGroupTaskQueue($groupId);

        // Assert
        $this->assertEquals('group_task_queue:42', $result);
    }

    public function testGetGroupTaskQueueWithZero(): void
    {
        // Arrange
        $groupId = 0;

        // Act
        $result = RedisQueueKeys::getGroupTaskQueue($groupId);

        // Assert
        $this->assertEquals('group_task_queue:0', $result);
    }

    public function testGetDeviceLock(): void
    {
        // Arrange
        $deviceCode = 'DEVICE_005';

        // Act
        $result = RedisQueueKeys::getDeviceLock($deviceCode);

        // Assert
        $this->assertEquals('device_lock:DEVICE_005', $result);
    }

    public function testGetInstructionRetry(): void
    {
        // Arrange
        $instructionId = 'INSTRUCTION_456';

        // Act
        $result = RedisQueueKeys::getInstructionRetry($instructionId);

        // Assert
        $this->assertEquals('instruction_retry:INSTRUCTION_456', $result);
    }

    public function testGetDeviceMetrics(): void
    {
        // Arrange
        $deviceCode = 'DEVICE_006';

        // Act
        $result = RedisQueueKeys::getDeviceMetrics($deviceCode);

        // Assert
        $this->assertEquals('device_metrics:DEVICE_006', $result);
    }

    public function testAllMethodsWithEmptyString(): void
    {
        // Test all methods handle empty string gracefully
        $this->assertEquals('device_instruction_queue:', RedisQueueKeys::getDeviceInstructionQueue(''));
        $this->assertEquals('device_poll_notify:', RedisQueueKeys::getDevicePollNotify(''));
        $this->assertEquals('device_online:', RedisQueueKeys::getDeviceOnline(''));
        $this->assertEquals('instruction_status:', RedisQueueKeys::getInstructionStatus(''));
        $this->assertEquals('device_last_heartbeat:', RedisQueueKeys::getDeviceLastHeartbeat(''));
        $this->assertEquals('device_lock:', RedisQueueKeys::getDeviceLock(''));
        $this->assertEquals('instruction_retry:', RedisQueueKeys::getInstructionRetry(''));
        $this->assertEquals('device_metrics:', RedisQueueKeys::getDeviceMetrics(''));
    }

    public function testAllMethodsWithNumericStrings(): void
    {
        // Test methods handle numeric strings correctly
        $this->assertEquals('device_instruction_queue:123', RedisQueueKeys::getDeviceInstructionQueue('123'));
        $this->assertEquals('device_poll_notify:456', RedisQueueKeys::getDevicePollNotify('456'));
        $this->assertEquals('device_online:789', RedisQueueKeys::getDeviceOnline('789'));
        $this->assertEquals('instruction_status:101112', RedisQueueKeys::getInstructionStatus('101112'));
        $this->assertEquals('device_last_heartbeat:131415', RedisQueueKeys::getDeviceLastHeartbeat('131415'));
        $this->assertEquals('device_lock:161718', RedisQueueKeys::getDeviceLock('161718'));
        $this->assertEquals('instruction_retry:192021', RedisQueueKeys::getInstructionRetry('192021'));
        $this->assertEquals('device_metrics:222324', RedisQueueKeys::getDeviceMetrics('222324'));
    }

    public function testGlobalTaskQueueConstant(): void
    {
        // Global task queue doesn't use sprintf formatting
        $this->assertEquals('global_task_queue', RedisQueueKeys::GLOBAL_TASK_QUEUE);
    }

    public function testKeyFormatsAreConsistent(): void
    {
        // Verify all parameterized keys use the same format pattern
        $this->assertStringContainsString('%s', RedisQueueKeys::DEVICE_INSTRUCTION_QUEUE);
        $this->assertStringContainsString('%s', RedisQueueKeys::DEVICE_POLL_NOTIFY);
        $this->assertStringContainsString('%s', RedisQueueKeys::DEVICE_ONLINE);
        $this->assertStringContainsString('%s', RedisQueueKeys::INSTRUCTION_STATUS);
        $this->assertStringContainsString('%s', RedisQueueKeys::DEVICE_LAST_HEARTBEAT);
        $this->assertStringContainsString('%s', RedisQueueKeys::GROUP_TASK_QUEUE);
        $this->assertStringContainsString('%s', RedisQueueKeys::DEVICE_LOCK);
        $this->assertStringContainsString('%s', RedisQueueKeys::INSTRUCTION_RETRY);
        $this->assertStringContainsString('%s', RedisQueueKeys::DEVICE_METRICS);

        // Global task queue should not contain format specifier
        $this->assertStringNotContainsString('%s', RedisQueueKeys::GLOBAL_TASK_QUEUE);
    }

    public function testTtlValuesAreReasonable(): void
    {
        // TTL values should be positive
        $this->assertGreaterThan(0, RedisQueueKeys::TTL_ONLINE_STATUS);
        $this->assertGreaterThan(0, RedisQueueKeys::TTL_INSTRUCTION_STATUS);
        $this->assertGreaterThan(0, RedisQueueKeys::TTL_HEARTBEAT);
        $this->assertGreaterThan(0, RedisQueueKeys::TTL_LOCK);
        $this->assertGreaterThan(0, RedisQueueKeys::TTL_RETRY_COUNTER);
        $this->assertGreaterThan(0, RedisQueueKeys::TTL_METRICS);

        // Lock TTL should be shortest (for quick release)
        $this->assertLessThan(RedisQueueKeys::TTL_ONLINE_STATUS, RedisQueueKeys::TTL_LOCK);

        // Metrics TTL should be longest (for historical data)
        $this->assertGreaterThan(RedisQueueKeys::TTL_INSTRUCTION_STATUS, RedisQueueKeys::TTL_METRICS);
    }
}
