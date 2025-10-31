<?php

declare(strict_types=1);

namespace Tourze\AutoJsControlBundle\Tests\ValueObject;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tourze\AutoJsControlBundle\ValueObject\DeviceConnectionInfo;

/**
 * @internal
 */
#[CoversClass(DeviceConnectionInfo::class)]
final class DeviceConnectionInfoTest extends TestCase
{
    #[Test]
    public function constructorSetsPropertiesCorrectly(): void
    {
        // Arrange
        $deviceCode = 'DEVICE_001';
        $ipAddress = '192.168.1.100';
        $connectedTime = new \DateTimeImmutable('2023-01-01 10:00:00');
        $userAgent = 'Mozilla/5.0 (Android 10)';
        $connectionId = 'conn_123456';
        $headers = ['Accept' => 'application/json', 'X-Custom' => 'value'];

        // Act
        $info = new DeviceConnectionInfo(
            $deviceCode,
            $ipAddress,
            $connectedTime,
            $userAgent,
            $connectionId,
            $headers
        );

        // Assert
        $this->assertEquals($deviceCode, $info->getDeviceCode());
        $this->assertEquals($ipAddress, $info->getIpAddress());
        $this->assertEquals($connectedTime, $info->getConnectedTime());
        $this->assertEquals($userAgent, $info->getUserAgent());
        $this->assertEquals($connectionId, $info->getConnectionId());
        $this->assertEquals($headers, $info->getHeaders());
    }

    #[Test]
    public function constructorWithDefaultConnectedTimeUsesCurrentTime(): void
    {
        // Arrange
        $before = new \DateTimeImmutable();

        // Act
        $info = new DeviceConnectionInfo('DEVICE_001', '192.168.1.1');

        // Assert
        $after = new \DateTimeImmutable();
        $this->assertGreaterThanOrEqual($before, $info->getConnectedTime());
        $this->assertLessThanOrEqual($after, $info->getConnectedTime());
    }

    #[Test]
    public function constructorWithOptionalParametersAsNull(): void
    {
        // Act
        $info = new DeviceConnectionInfo('DEVICE_001', '192.168.1.1');

        // Assert
        $this->assertNull($info->getUserAgent());
        $this->assertNull($info->getConnectionId());
        $this->assertEquals([], $info->getHeaders());
    }

    #[Test]
    public function getConnectionDurationReturnsCorrectSeconds(): void
    {
        // Arrange
        $connectedTime = new \DateTimeImmutable('-30 seconds');
        $info = new DeviceConnectionInfo('DEVICE_001', '192.168.1.1', $connectedTime);

        // Act
        $duration = $info->getConnectionDuration();

        // Assert
        $this->assertGreaterThanOrEqual(29, $duration);
        $this->assertLessThanOrEqual(31, $duration);
    }

    #[Test]
    public function isSameIpReturnsTrueForMatchingIp(): void
    {
        // Arrange
        $info = new DeviceConnectionInfo('DEVICE_001', '192.168.1.100');

        // Act & Assert
        $this->assertTrue($info->isSameIp('192.168.1.100'));
    }

    #[Test]
    public function isSameIpReturnsFalseForDifferentIp(): void
    {
        // Arrange
        $info = new DeviceConnectionInfo('DEVICE_001', '192.168.1.100');

        // Act & Assert
        $this->assertFalse($info->isSameIp('192.168.1.101'));
    }

    #[Test]
    public function toArrayReturnsCorrectStructure(): void
    {
        // Arrange
        $connectedTime = new \DateTimeImmutable('2023-01-01 10:00:00');
        $info = new DeviceConnectionInfo(
            'DEVICE_001',
            '192.168.1.100',
            $connectedTime,
            'Mozilla/5.0',
            'conn_123',
            ['X-Custom' => 'value']
        );

        // Act
        $array = $info->toArray();

        // Assert
        $this->assertEquals([
            'deviceCode' => 'DEVICE_001',
            'ipAddress' => '192.168.1.100',
            'userAgent' => 'Mozilla/5.0',
            'connectedTime' => $connectedTime->format(\DateTimeInterface::RFC3339),
            'connectionId' => 'conn_123',
            'headers' => ['X-Custom' => 'value'],
        ], $array);
    }

    #[Test]
    public function toArrayWithNullOptionalFields(): void
    {
        // Arrange
        $connectedTime = new \DateTimeImmutable('2023-01-01 10:00:00');
        $info = new DeviceConnectionInfo(
            'DEVICE_001',
            '192.168.1.100',
            $connectedTime
        );

        // Act
        $array = $info->toArray();

        // Assert
        $this->assertNull($array['userAgent']);
        $this->assertNull($array['connectionId']);
        $this->assertEquals([], $array['headers']);
    }

    #[Test]
    public function fromArrayCreatesCorrectInstance(): void
    {
        // Arrange
        $data = [
            'deviceCode' => 'DEVICE_001',
            'ipAddress' => '192.168.1.100',
            'connectedTime' => '2023-01-01T10:00:00+00:00',
            'userAgent' => 'Mozilla/5.0',
            'connectionId' => 'conn_123',
            'headers' => ['X-Custom' => 'value'],
        ];

        // Act
        $info = DeviceConnectionInfo::fromArray($data);

        // Assert
        $this->assertEquals('DEVICE_001', $info->getDeviceCode());
        $this->assertEquals('192.168.1.100', $info->getIpAddress());
        $this->assertEquals('2023-01-01T10:00:00+00:00', $info->getConnectedTime()->format(\DateTimeInterface::RFC3339));
        $this->assertEquals('Mozilla/5.0', $info->getUserAgent());
        $this->assertEquals('conn_123', $info->getConnectionId());
        $this->assertEquals(['X-Custom' => 'value'], $info->getHeaders());
    }

    #[Test]
    public function fromArrayWithMissingOptionalFields(): void
    {
        // Arrange
        $data = [
            'deviceCode' => 'DEVICE_001',
            'ipAddress' => '192.168.1.100',
            'connectedTime' => '2023-01-01T10:00:00+00:00',
        ];

        // Act
        $info = DeviceConnectionInfo::fromArray($data);

        // Assert
        $this->assertNull($info->getUserAgent());
        $this->assertNull($info->getConnectionId());
        $this->assertEquals([], $info->getHeaders());
    }

    #[Test]
    public function roundTripConversion(): void
    {
        // Arrange
        $original = new DeviceConnectionInfo(
            'DEVICE_001',
            '192.168.1.100',
            new \DateTimeImmutable('2023-01-01 10:00:00'),
            'Mozilla/5.0',
            'conn_123',
            ['X-Custom' => 'value']
        );

        // Act
        $array = $original->toArray();
        $reconstructed = DeviceConnectionInfo::fromArray($array);

        // Assert
        $this->assertEquals($original->getDeviceCode(), $reconstructed->getDeviceCode());
        $this->assertEquals($original->getIpAddress(), $reconstructed->getIpAddress());
        $this->assertEquals($original->getConnectedTime()->format('c'), $reconstructed->getConnectedTime()->format('c'));
        $this->assertEquals($original->getUserAgent(), $reconstructed->getUserAgent());
        $this->assertEquals($original->getConnectionId(), $reconstructed->getConnectionId());
        $this->assertEquals($original->getHeaders(), $reconstructed->getHeaders());
    }
}
