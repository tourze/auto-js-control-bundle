<?php

declare(strict_types=1);

namespace Tourze\AutoJsControlBundle\Tests\Dto\Request;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tourze\AutoJsControlBundle\Dto\Request\DeviceHeartbeatRequest;

/**
 * @internal
 */
#[CoversClass(DeviceHeartbeatRequest::class)]
final class DeviceHeartbeatRequestTest extends TestCase
{
    #[Test]
    public function constructorSetsProperties(): void
    {
        // Arrange
        $deviceCode = 'TEST_DEVICE_001';
        $signature = 'test_signature';
        $timestamp = time();
        $autoJsVersion = '4.1.1';
        $deviceInfo = [
            'model' => 'Test Model',
            'manufacturer' => 'Test Manufacturer',
        ];
        $monitorData = [
            'cpuUsage' => 45.5,
            'memoryUsage' => 60.2,
            'batteryLevel' => 85,
            'screenOn' => true,
            'activeScripts' => ['script1', 'script2'],
        ];
        $pollTimeout = 30;

        // Act
        $request = new DeviceHeartbeatRequest(
            deviceCode: $deviceCode,
            signature: $signature,
            timestamp: $timestamp,
            autoJsVersion: $autoJsVersion,
            deviceInfo: $deviceInfo,
            monitorData: $monitorData,
            pollTimeout: $pollTimeout
        );

        // Assert
        $this->assertEquals($deviceCode, $request->getDeviceCode());
        $this->assertEquals($signature, $request->getSignature());
        $this->assertEquals($timestamp, $request->getTimestamp());
        $this->assertEquals($autoJsVersion, $request->getAutoJsVersion());
        $this->assertEquals($deviceInfo, $request->getDeviceInfo());
        $this->assertEquals($monitorData, $request->getMonitorData());
        $this->assertEquals($pollTimeout, $request->getPollTimeout());
    }

    #[Test]
    public function gettersReturnCorrectValues(): void
    {
        // Arrange
        $timestamp = time();
        $request = new DeviceHeartbeatRequest(
            deviceCode: 'DEVICE_002',
            signature: 'signature_002',
            timestamp: $timestamp
        );

        // Assert
        $this->assertEquals('DEVICE_002', $request->getDeviceCode());
        $this->assertEquals('signature_002', $request->getSignature());
        $this->assertEquals($timestamp, $request->getTimestamp());
        $this->assertNull($request->getAutoJsVersion());
        $this->assertIsArray($request->getDeviceInfo());
        $this->assertEmpty($request->getDeviceInfo());
        $this->assertIsArray($request->getMonitorData());
        $this->assertEmpty($request->getMonitorData());
        $this->assertEquals(30, $request->getPollTimeout());
    }

    #[Test]
    public function verifySignatureWithValidSignature(): void
    {
        // Arrange
        $deviceCode = 'DEVICE_003';
        $timestamp = time();
        $certificate = 'test_certificate';
        $data = sprintf('%s:%d:%s', $deviceCode, $timestamp, $certificate);
        $signature = hash_hmac('sha256', $data, $certificate);

        $request = new DeviceHeartbeatRequest(
            deviceCode: $deviceCode,
            signature: $signature,
            timestamp: $timestamp
        );

        // Act & Assert
        $this->assertTrue($request->verifySignature($certificate));
    }

    #[Test]
    public function verifySignatureWithInvalidSignature(): void
    {
        // Arrange
        $request = new DeviceHeartbeatRequest(
            deviceCode: 'DEVICE_004',
            signature: 'invalid_signature',
            timestamp: time()
        );

        // Act & Assert
        $this->assertFalse($request->verifySignature('test_certificate'));
    }

    #[Test]
    public function constructorWithMinimalParameters(): void
    {
        // Arrange
        $deviceCode = 'DEVICE_005';
        $signature = 'signature_005';
        $timestamp = time();

        // Act
        $request = new DeviceHeartbeatRequest(
            deviceCode: $deviceCode,
            signature: $signature,
            timestamp: $timestamp
        );

        // Assert
        $this->assertEquals($deviceCode, $request->getDeviceCode());
        $this->assertEquals($signature, $request->getSignature());
        $this->assertEquals($timestamp, $request->getTimestamp());
        $this->assertNull($request->getAutoJsVersion());
        $this->assertEquals([], $request->getDeviceInfo());
        $this->assertEquals([], $request->getMonitorData());
        $this->assertEquals(30, $request->getPollTimeout());
    }

    #[Test]
    public function constructorWithComplexMonitorData(): void
    {
        // Arrange
        $monitorData = [
            'cpuUsage' => 75.5,
            'memoryUsage' => 80.0,
            'batteryLevel' => 50,
            'screenOn' => false,
            'activeScripts' => ['script1', 'script2', 'script3'],
            'network' => [
                'type' => 'wifi',
                'strength' => -60,
            ],
        ];

        // Act
        $request = new DeviceHeartbeatRequest(
            deviceCode: 'DEVICE_006',
            signature: 'signature_006',
            timestamp: time(),
            monitorData: $monitorData
        );

        // Assert
        $this->assertEquals($monitorData, $request->getMonitorData());
    }
}
