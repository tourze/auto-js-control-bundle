<?php

declare(strict_types=1);

namespace Tourze\AutoJsControlBundle\Tests\Service;

use DeviceBundle\Enum\DeviceStatus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Test;
use Tourze\AutoJsControlBundle\Dto\Request\DeviceRegisterRequest;
use Tourze\AutoJsControlBundle\Entity\AutoJsDevice;
use Tourze\AutoJsControlBundle\Service\DeviceRegistrationService;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(DeviceRegistrationService::class)]
#[RunTestsInSeparateProcesses]
final class DeviceRegistrationServiceTest extends AbstractIntegrationTestCase
{
    private DeviceRegistrationService $deviceRegistrationService;

    protected function onSetUp(): void
    {
        $this->deviceRegistrationService = self::getService(DeviceRegistrationService::class);
    }

    public function testServiceExists(): void
    {
        $this->assertInstanceOf(DeviceRegistrationService::class, $this->deviceRegistrationService);
    }

    #[Test]
    public function testRegisterDeviceWithNewDeviceCreatesAndReturnsResult(): void
    {
        // Arrange
        $request = $this->createDeviceRegisterRequest();
        $clientIp = '192.168.1.100';

        // Act
        $result = $this->deviceRegistrationService->registerDevice($request, $clientIp);

        // Assert
        $this->assertArrayHasKey('autoJsDevice', $result);
        $this->assertArrayHasKey('certificate', $result);
        $this->assertInstanceOf(AutoJsDevice::class, $result['autoJsDevice']);
        $this->assertIsString($result['certificate']);
        $this->assertNotEmpty($result['certificate']);

        // Verify device was created with correct data
        $autoJsDevice = $result['autoJsDevice'];
        $baseDevice = $autoJsDevice->getBaseDevice();
        $this->assertNotNull($baseDevice, 'Base device should not be null');
        $this->assertStringStartsWith('TEST_DEVICE_', $baseDevice->getCode());
        $this->assertEquals('Test Device', $baseDevice->getName());
        $this->assertEquals($clientIp, $baseDevice->getLastIp());
        $this->assertEquals(DeviceStatus::ONLINE, $baseDevice->getStatus());
    }

    #[Test]
    public function testRegisterDeviceWithExistingDeviceUpdatesAndReturnsResult(): void
    {
        // Arrange - First create a device
        $firstRequest = $this->createDeviceRegisterRequest();
        $firstIp = '192.168.1.100';

        $this->deviceRegistrationService->registerDevice($firstRequest, $firstIp);

        // Now update the same device
        $updateRequest = $this->createDeviceRegisterRequest();
        $updateIp = '192.168.1.101';

        // Act
        $result = $this->deviceRegistrationService->registerDevice($updateRequest, $updateIp);

        // Assert
        $this->assertArrayHasKey('autoJsDevice', $result);
        $this->assertArrayHasKey('certificate', $result);
        $this->assertIsString($result['certificate']);

        // Verify device was updated
        $autoJsDevice = $result['autoJsDevice'];
        $baseDevice = $autoJsDevice->getBaseDevice();
        $this->assertNotNull($baseDevice);
        $this->assertStringStartsWith('TEST_DEVICE_', $baseDevice->getCode());
        $this->assertEquals('Test Device', $baseDevice->getName());
        $this->assertEquals($updateIp, $baseDevice->getLastIp());
        $this->assertEquals(DeviceStatus::ONLINE, $baseDevice->getStatus());
    }

    #[Test]
    public function testRegisterDeviceWithValidDataSucceeds(): void
    {
        // Arrange
        $request = new DeviceRegisterRequest(
            'UNIQUE_DEVICE_' . uniqid(),
            'Test Device',
            'cert_request',
            'Test Model',
            'Test Brand',
            'Android 11',
            '4.1.1',
            'test_fingerprint',
            []
        );
        $clientIp = '192.168.1.100';

        // Act
        $result = $this->deviceRegistrationService->registerDevice($request, $clientIp);

        // Assert
        $this->assertArrayHasKey('autoJsDevice', $result);
        $this->assertArrayHasKey('certificate', $result);
        $this->assertInstanceOf(AutoJsDevice::class, $result['autoJsDevice']);
    }

    #[Test]
    public function testRegisterDeviceWithHardwareInfoUpdatesBaseDevice(): void
    {
        // Arrange
        $request = $this->createDeviceRegisterRequestWithHardware();
        $clientIp = '192.168.1.100';

        // Act
        $result = $this->deviceRegistrationService->registerDevice($request, $clientIp);

        // Assert
        $this->assertInstanceOf(AutoJsDevice::class, $result['autoJsDevice']);

        $baseDevice = $result['autoJsDevice']->getBaseDevice();
        $this->assertNotNull($baseDevice, 'Base device should not be null');
        $this->assertEquals(8, $baseDevice->getCpuCores());
        $this->assertEquals('8GB', $baseDevice->getMemorySize());
        $this->assertEquals('128GB', $baseDevice->getStorageSize());
        $this->assertStringStartsWith('HARDWARE_DEVICE_', $baseDevice->getCode());
        $this->assertEquals('Hardware Device', $baseDevice->getName());
    }

    private function createDeviceRegisterRequest(): DeviceRegisterRequest
    {
        return new DeviceRegisterRequest(
            'TEST_DEVICE_' . uniqid(),
            'Test Device',
            'cert_request',
            'Test Model',
            'Test Brand',
            'Android 11',
            '4.1.1',
            'test_fingerprint',
            []
        );
    }

    private function createDeviceRegisterRequestWithHardware(): DeviceRegisterRequest
    {
        return new DeviceRegisterRequest(
            'HARDWARE_DEVICE_' . uniqid(),
            'Hardware Device',
            'hardware_cert_request',
            'Hardware Model',
            'Hardware Brand',
            'Android 12',
            '4.1.1',
            'hardware_fingerprint',
            [
                'cpuCores' => 8,
                'memorySize' => '8GB',
                'storageSize' => '128GB',
            ]
        );
    }
}
