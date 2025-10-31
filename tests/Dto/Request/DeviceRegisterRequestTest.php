<?php

declare(strict_types=1);

namespace Tourze\AutoJsControlBundle\Tests\Dto\Request;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tourze\AutoJsControlBundle\Dto\Request\DeviceRegisterRequest;

/**
 * @internal
 */
#[CoversClass(DeviceRegisterRequest::class)]
final class DeviceRegisterRequestTest extends TestCase
{
    #[Test]
    public function constructorSetsProperties(): void
    {
        // Arrange
        $deviceCode = 'TEST_DEVICE_001';
        $deviceName = 'Test Device';
        $certificateRequest = 'cert_request_data';
        $model = 'Test Model';
        $brand = 'Test Brand';
        $osVersion = 'Android 12';
        $autoJsVersion = '4.1.1';
        $fingerprint = 'unique_fingerprint';
        $hardwareInfo = [
            'cpuCores' => 8,
            'memorySize' => 8192,
            'storageSize' => 131072,
            'screenResolution' => '1920x1080',
        ];

        // Act
        $request = new DeviceRegisterRequest(
            deviceCode: $deviceCode,
            deviceName: $deviceName,
            certificateRequest: $certificateRequest,
            model: $model,
            brand: $brand,
            osVersion: $osVersion,
            autoJsVersion: $autoJsVersion,
            fingerprint: $fingerprint,
            hardwareInfo: $hardwareInfo
        );

        // Assert
        $this->assertEquals($deviceCode, $request->getDeviceCode());
        $this->assertEquals($deviceName, $request->getDeviceName());
        $this->assertEquals($certificateRequest, $request->getCertificateRequest());
        $this->assertEquals($model, $request->getModel());
        $this->assertEquals($brand, $request->getBrand());
        $this->assertEquals($osVersion, $request->getOsVersion());
        $this->assertEquals($autoJsVersion, $request->getAutoJsVersion());
        $this->assertEquals($fingerprint, $request->getFingerprint());
        $this->assertEquals($hardwareInfo, $request->getHardwareInfo());
    }

    #[Test]
    public function constructorWithMinimalParameters(): void
    {
        // Arrange
        $deviceCode = 'DEVICE_002';
        $deviceName = 'Minimal Device';
        $certificateRequest = 'minimal_cert_request';

        // Act
        $request = new DeviceRegisterRequest(
            deviceCode: $deviceCode,
            deviceName: $deviceName,
            certificateRequest: $certificateRequest
        );

        // Assert
        $this->assertEquals($deviceCode, $request->getDeviceCode());
        $this->assertEquals($deviceName, $request->getDeviceName());
        $this->assertEquals($certificateRequest, $request->getCertificateRequest());
        $this->assertNull($request->getModel());
        $this->assertNull($request->getBrand());
        $this->assertNull($request->getOsVersion());
        $this->assertNull($request->getAutoJsVersion());
        $this->assertNull($request->getFingerprint());
        $this->assertEquals([], $request->getHardwareInfo());
    }

    #[Test]
    public function getHardwareInfoMethods(): void
    {
        // Arrange
        $hardwareInfo = [
            'cpuCores' => 8,
            'memorySize' => 8192,
            'storageSize' => 131072,
            'screenResolution' => '2560x1440',
        ];

        $request = new DeviceRegisterRequest(
            deviceCode: 'DEVICE_003',
            deviceName: 'Hardware Test Device',
            certificateRequest: 'cert',
            hardwareInfo: $hardwareInfo
        );

        // Act & Assert
        $this->assertEquals(8, $request->getCpuCores());
        $this->assertEquals(8192, $request->getMemorySize());
        $this->assertEquals(131072, $request->getStorageSize());
        $this->assertEquals('2560x1440', $request->getScreenResolution());
    }

    #[Test]
    public function getHardwareInfoMethodsWithEmptyArray(): void
    {
        // Arrange
        $request = new DeviceRegisterRequest(
            deviceCode: 'DEVICE_004',
            deviceName: 'Empty Hardware Device',
            certificateRequest: 'cert'
        );

        // Act & Assert
        $this->assertEquals(0, $request->getCpuCores());
        $this->assertEquals(0, $request->getMemorySize());
        $this->assertEquals(0, $request->getStorageSize());
        $this->assertNull($request->getScreenResolution());
    }

    #[Test]
    public function constructorWithPartialOptionalParameters(): void
    {
        // Arrange
        $request = new DeviceRegisterRequest(
            deviceCode: 'DEVICE_005',
            deviceName: 'Partial Device',
            certificateRequest: 'partial_cert',
            model: 'Partial Model',
            brand: null,
            osVersion: 'Android 13',
            autoJsVersion: null,
            fingerprint: 'partial_fingerprint'
        );

        // Act & Assert
        $this->assertEquals('DEVICE_005', $request->getDeviceCode());
        $this->assertEquals('Partial Device', $request->getDeviceName());
        $this->assertEquals('partial_cert', $request->getCertificateRequest());
        $this->assertEquals('Partial Model', $request->getModel());
        $this->assertNull($request->getBrand());
        $this->assertEquals('Android 13', $request->getOsVersion());
        $this->assertNull($request->getAutoJsVersion());
        $this->assertEquals('partial_fingerprint', $request->getFingerprint());
    }

    #[Test]
    public function complexHardwareInfo(): void
    {
        // Arrange
        $hardwareInfo = [
            'cpuCores' => 12,
            'memorySize' => 16384,
            'storageSize' => 524288,
            'screenResolution' => '3840x2160',
            'additionalInfo' => [
                'gpu' => 'Adreno 730',
                'sensors' => ['accelerometer', 'gyroscope', 'proximity'],
            ],
        ];

        $request = new DeviceRegisterRequest(
            deviceCode: 'DEVICE_006',
            deviceName: 'Complex Hardware Device',
            certificateRequest: 'complex_cert',
            model: 'Flagship Model',
            brand: 'Premium Brand',
            hardwareInfo: $hardwareInfo
        );

        // Act & Assert
        $this->assertEquals(12, $request->getCpuCores());
        $this->assertEquals(16384, $request->getMemorySize());
        $this->assertEquals(524288, $request->getStorageSize());
        $this->assertEquals('3840x2160', $request->getScreenResolution());
        $this->assertEquals($hardwareInfo, $request->getHardwareInfo());
        $this->assertArrayHasKey('additionalInfo', $request->getHardwareInfo());
    }
}
