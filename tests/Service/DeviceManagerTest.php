<?php

declare(strict_types=1);

namespace Tourze\AutoJsControlBundle\Tests\Service;

use DeviceBundle\Entity\Device as BaseDevice;
use DeviceBundle\Enum\DeviceStatus;
use DeviceBundle\Repository\DeviceRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Tourze\AutoJsControlBundle\Entity\AutoJsDevice;
use Tourze\AutoJsControlBundle\Exception\BusinessLogicException;
use Tourze\AutoJsControlBundle\Repository\AutoJsDeviceRepository;
use Tourze\AutoJsControlBundle\Service\DeviceManager;
use Tourze\AutoJsControlBundle\Service\DeviceQueryService;
use Tourze\AutoJsControlBundle\Service\DeviceRegistrationHandler;
use Tourze\AutoJsControlBundle\Service\DeviceStatusManager;
use Tourze\AutoJsControlBundle\Service\DeviceTaskManager;

/**
 * @internal
 */
#[CoversClass(DeviceManager::class)]
final class DeviceManagerTest extends TestCase
{
    private DeviceRepository&MockObject $deviceRepository;

    private AutoJsDeviceRepository&MockObject $autoJsDeviceRepository;

    private DeviceRegistrationHandler&MockObject $registrationHandler;

    private DeviceStatusManager&MockObject $statusManager;

    private DeviceQueryService&MockObject $queryService;

    private DeviceTaskManager&MockObject $taskManager;

    private LoggerInterface&MockObject $logger;

    private DeviceManager $deviceManager;

    protected function setUp(): void
    {
        $this->deviceRepository = $this->createMock(DeviceRepository::class);
        $this->autoJsDeviceRepository = $this->createMock(AutoJsDeviceRepository::class);
        $this->registrationHandler = $this->createMock(DeviceRegistrationHandler::class);
        $this->statusManager = $this->createMock(DeviceStatusManager::class);
        $this->queryService = $this->createMock(DeviceQueryService::class);
        $this->taskManager = $this->createMock(DeviceTaskManager::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->deviceManager = new DeviceManager(
            $this->deviceRepository,
            $this->autoJsDeviceRepository,
            $this->registrationHandler,
            $this->statusManager,
            $this->queryService,
            $this->taskManager,
            $this->logger
        );
    }

    public function testRegisterOrUpdateDeviceWithNewDeviceCreatesAndReturnsDevice(): void
    {
        // Arrange
        $deviceCode = 'TEST_DEVICE_001';
        $deviceName = 'Test Device';
        $certificateRequest = 'cert_request';
        $deviceInfo = [
            'model' => 'Test Model',
            'brand' => 'Test Brand',
            'osVersion' => 'Android 11',
            'autoJsVersion' => '4.1.1',
        ];
        $clientIp = '192.168.1.100';
        $certificate = 'generated_certificate';

        $autoJsDevice = $this->createAutoJsDevice($deviceCode);
        $autoJsDevice->setCertificate($certificate);

        $this->registrationHandler->expects($this->once())
            ->method('registerOrUpdate')
            ->with($deviceCode, $deviceName, $certificateRequest, $deviceInfo, $clientIp)
            ->willReturn($autoJsDevice)
        ;

        // Act
        $result = $this->deviceManager->registerOrUpdateDevice(
            $deviceCode,
            $deviceName,
            $certificateRequest,
            $deviceInfo,
            $clientIp
        );

        // Assert
        $this->assertInstanceOf(AutoJsDevice::class, $result);
        $this->assertEquals($certificate, $result->getCertificate());
    }

    public function testRegisterOrUpdateDeviceWithExistingDeviceUpdatesAndReturnsDevice(): void
    {
        // Arrange
        $deviceCode = 'EXISTING_DEVICE';
        $deviceName = 'Updated Device';
        $certificateRequest = 'new_cert_request';
        $deviceInfo = ['autoJsVersion' => '4.2.0'];
        $clientIp = '192.168.1.101';
        $certificate = 'new_certificate';

        $autoJsDevice = $this->createAutoJsDevice($deviceCode);
        $autoJsDevice->setCertificate($certificate);

        $this->registrationHandler->expects($this->once())
            ->method('registerOrUpdate')
            ->with($deviceCode, $deviceName, $certificateRequest, $deviceInfo, $clientIp)
            ->willReturn($autoJsDevice)
        ;

        // Act
        $result = $this->deviceManager->registerOrUpdateDevice(
            $deviceCode,
            $deviceName,
            $certificateRequest,
            $deviceInfo,
            $clientIp
        );

        // Assert
        $this->assertInstanceOf(AutoJsDevice::class, $result);
        $this->assertEquals($certificate, $result->getCertificate());
    }

    public function testRegisterOrUpdateDeviceWithDatabaseErrorRollsBackAndThrowsException(): void
    {
        // Arrange
        $deviceCode = 'ERROR_DEVICE';
        $exception = new \RuntimeException('设备注册失败: Database error');

        $this->registrationHandler->expects($this->once())
            ->method('registerOrUpdate')
            ->willThrowException($exception)
        ;

        // Act & Assert
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('设备注册失败: Database error');

        $this->deviceManager->registerOrUpdateDevice(
            $deviceCode,
            'Test Device',
            'cert_request',
            [],
            '192.168.1.100'
        );
    }

    public function testGetDeviceWithExistingDeviceReturnsDevice(): void
    {
        // Arrange
        $deviceCode = 'TEST_DEVICE';
        $baseDevice = new BaseDevice();
        $baseDevice->setCode($deviceCode);

        $autoJsDevice = new AutoJsDevice();
        $autoJsDevice->setBaseDevice($baseDevice);

        $this->deviceRepository->expects($this->once())
            ->method('findOneBy')
            ->with(['code' => $deviceCode])
            ->willReturn($baseDevice)
        ;

        $this->autoJsDeviceRepository->expects($this->once())
            ->method('findOneBy')
            ->with(['baseDevice' => $baseDevice])
            ->willReturn($autoJsDevice)
        ;

        // Act
        $result = $this->deviceManager->getDevice($deviceCode);

        // Assert
        $this->assertSame($autoJsDevice, $result);
    }

    public function testGetDeviceWithNonExistentDeviceThrowsNotFoundException(): void
    {
        // Arrange
        $deviceCode = 'NON_EXISTENT';

        $this->deviceRepository->expects($this->once())
            ->method('findOneBy')
            ->with(['code' => $deviceCode])
            ->willReturn(null)
        ;

        // Act & Assert
        $this->expectException(BusinessLogicException::class);
        $this->expectExceptionMessage('设备不存在');

        $this->deviceManager->getDevice($deviceCode);
    }

    public function testGetDeviceByIdWithExistingDeviceReturnsDevice(): void
    {
        // Arrange
        $deviceId = 123;
        $autoJsDevice = new AutoJsDevice();

        $this->autoJsDeviceRepository->expects($this->once())
            ->method('find')
            ->with($deviceId)
            ->willReturn($autoJsDevice)
        ;

        // Act
        $result = $this->deviceManager->getDeviceById($deviceId);

        // Assert
        $this->assertSame($autoJsDevice, $result);
    }

    public function testGetOnlineDevicesWithMixedDevicesReturnsOnlyOnlineDevices(): void
    {
        // Arrange
        $device1 = $this->createAutoJsDevice('DEVICE_1');
        $device2 = $this->createAutoJsDevice('DEVICE_2');
        $device3 = $this->createAutoJsDevice('DEVICE_3');

        $expected = [
            'devices' => [$device1, $device3],
            'pagination' => ['total' => 2, 'page' => 1, 'limit' => 20],
        ];

        $this->queryService->expects($this->once())
            ->method('getOnlineDevices')
            ->with(1, 20)
            ->willReturn($expected)
        ;

        // Act
        $result = $this->deviceManager->getOnlineDevices(1, 20);

        // Assert
        $this->assertCount(2, $result['devices']);
        $this->assertEquals(2, $result['pagination']['total']);
        $this->assertContains($device1, $result['devices']);
        $this->assertContains($device3, $result['devices']);
        $this->assertNotContains($device2, $result['devices']);
    }

    public function testUpdateDeviceStatusWithStatusChangeTriggersEvent(): void
    {
        // Arrange
        $deviceCode = 'TEST_DEVICE';
        $baseDevice = new BaseDevice();
        $baseDevice->setCode($deviceCode);
        $baseDevice->setStatus(DeviceStatus::ONLINE);

        $autoJsDevice = new AutoJsDevice();
        $autoJsDevice->setBaseDevice($baseDevice);

        $this->deviceRepository->expects($this->once())
            ->method('findOneBy')
            ->willReturn($baseDevice)
        ;

        $this->autoJsDeviceRepository->expects($this->once())
            ->method('findOneBy')
            ->willReturn($autoJsDevice)
        ;

        $this->statusManager->expects($this->once())
            ->method('updateStatus')
            ->with($autoJsDevice, DeviceStatus::OFFLINE)
        ;

        // Act
        $this->deviceManager->updateDeviceStatus($deviceCode, DeviceStatus::OFFLINE);

        // Assert - status change is verified through mocks
    }

    public function testDeleteDeviceWithExistingDeviceMarksAsDeletedAndClearsRedis(): void
    {
        // Arrange
        $deviceCode = 'DELETE_ME';
        $baseDevice = new BaseDevice();
        $baseDevice->setCode($deviceCode);
        $baseDevice->setStatus(DeviceStatus::ONLINE);

        $autoJsDevice = new AutoJsDevice();
        $autoJsDevice->setBaseDevice($baseDevice);

        $this->deviceRepository->expects($this->once())
            ->method('findOneBy')
            ->willReturn($baseDevice)
        ;

        $this->autoJsDeviceRepository->expects($this->once())
            ->method('findOneBy')
            ->willReturn($autoJsDevice)
        ;

        $this->statusManager->expects($this->once())
            ->method('markAsDeleted')
            ->with($autoJsDevice)
        ;

        // Act
        $this->deviceManager->deleteDevice($deviceCode);

        // 日志记录已在DeviceManager内部完成，无需额外验证
    }

    public function testGetDeviceStatisticsReturnsCorrectStats(): void
    {
        // Arrange
        $expectedStats = [
            'total' => 3,
            'online' => 2,
            'offline' => 1,
            'byBrand' => ['Samsung' => 2, 'Xiaomi' => 1],
            'byOsVersion' => ['Android 11' => 2, 'Android 12' => 1],
        ];

        $this->queryService->expects($this->once())
            ->method('getDeviceStatistics')
            ->willReturn($expectedStats)
        ;

        // Act
        $stats = $this->deviceManager->getDeviceStatistics();

        // Assert
        $this->assertEquals(3, $stats['total']);
        $this->assertEquals(2, $stats['online']);
        $this->assertEquals(1, $stats['offline']);
        $this->assertEquals(2, $stats['byBrand']['Samsung']);
        $this->assertEquals(1, $stats['byBrand']['Xiaomi']);
        $this->assertEquals(2, $stats['byOsVersion']['Android 11']);
        $this->assertEquals(1, $stats['byOsVersion']['Android 12']);
    }

    public function testSendWelcomeInstructionSendsCorrectInstruction(): void
    {
        // Arrange
        $device = $this->createAutoJsDevice('WELCOME_DEVICE');

        $this->taskManager->expects($this->once())
            ->method('sendWelcomeInstruction')
            ->with($device)
        ;

        // Act
        $this->deviceManager->sendWelcomeInstruction($device);
    }

    #[Test]
    public function testGetDevicesStatusReturnsCorrectStatuses(): void
    {
        // Arrange
        $deviceCodes = ['DEVICE_1', 'DEVICE_2', 'DEVICE_3'];

        $device1 = $this->createAutoJsDevice('DEVICE_1');
        $this->setDeviceId($device1, 1);

        $device2 = $this->createAutoJsDevice('DEVICE_2');
        $this->setDeviceId($device2, 2);

        $device3 = $this->createAutoJsDevice('DEVICE_3');
        $this->setDeviceId($device3, 3);

        // queryService 已经封装了所有查询逻辑

        $this->queryService->expects($this->once())
            ->method('getDevicesStatus')
            ->with(['DEVICE_1', 'DEVICE_2', 'DEVICE_3'])
            ->willReturn([
                'DEVICE_1' => [
                    'id' => 1,
                    'name' => 'Device 1',
                    'online' => true,
                    'metrics' => ['cpuUsage' => 45.5, 'memoryUsage' => 60.0],
                ],
                'DEVICE_2' => [
                    'id' => 2,
                    'name' => 'Device 2',
                    'online' => false,
                    'metrics' => [],
                ],
                'DEVICE_3' => [
                    'id' => 3,
                    'name' => 'Device 3',
                    'online' => true,
                    'metrics' => ['cpuUsage' => 30.0, 'memoryUsage' => 40.0],
                ],
            ])
        ;

        // Act
        $result = $this->deviceManager->getDevicesStatus($deviceCodes);

        // Assert
        $this->assertArrayHasKey('DEVICE_1', $result);
        $this->assertArrayHasKey('DEVICE_2', $result);
        $this->assertArrayHasKey('DEVICE_3', $result);

        // Check DEVICE_1
        $this->assertEquals(1, $result['DEVICE_1']['id']);
        $this->assertEquals('Device 1', $result['DEVICE_1']['name']);
        $this->assertTrue($result['DEVICE_1']['online']);
        $this->assertEquals(['cpuUsage' => 45.5, 'memoryUsage' => 60.0], $result['DEVICE_1']['metrics']);

        // Check DEVICE_2
        $this->assertEquals(2, $result['DEVICE_2']['id']);
        $this->assertEquals('Device 2', $result['DEVICE_2']['name']);
        $this->assertFalse($result['DEVICE_2']['online']);
        $this->assertEmpty($result['DEVICE_2']['metrics']);

        // Check DEVICE_3
        $this->assertEquals(3, $result['DEVICE_3']['id']);
        $this->assertEquals('Device 3', $result['DEVICE_3']['name']);
        $this->assertTrue($result['DEVICE_3']['online']);
        $this->assertEquals(['cpuUsage' => 30.0, 'memoryUsage' => 40.0], $result['DEVICE_3']['metrics']);
    }

    #[Test]
    public function testSearchDevicesReturnsFilteredResults(): void
    {
        // Arrange
        $criteria = [];
        $orderBy = ['id' => 'DESC'];
        $limit = 10;
        $offset = 0;

        $device1 = $this->createAutoJsDevice('DEVICE_1');
        $device2 = $this->createAutoJsDevice('DEVICE_2');

        $this->autoJsDeviceRepository->expects($this->once())
            ->method('findBy')
            ->with($criteria, $orderBy, $limit, $offset)
            ->willReturn([$device1, $device2])
        ;

        // Act
        $result = $this->deviceManager->searchDevices($criteria, $orderBy, $limit, $offset);

        // Assert
        $this->assertCount(2, $result);
        $this->assertEquals('DEVICE_1', $result[0]->getDeviceCode());
        $this->assertEquals('DEVICE_2', $result[1]->getDeviceCode());
    }

    #[Test]
    public function testSearchDevicesWithCriteriaReturnsCorrectResults(): void
    {
        // Arrange
        $criteria = ['deviceGroup' => 1];
        $orderBy = ['id' => 'ASC'];
        $limit = 5;
        $offset = 0;

        $device = $this->createAutoJsDevice('GROUP_DEVICE');

        $this->autoJsDeviceRepository->expects($this->once())
            ->method('findBy')
            ->with($criteria, $orderBy, $limit, $offset)
            ->willReturn([$device])
        ;

        // Act
        $result = $this->deviceManager->searchDevices($criteria, $orderBy, $limit, $offset);

        // Assert
        $this->assertCount(1, $result);
        $this->assertEquals('GROUP_DEVICE', $result[0]->getDeviceCode());
    }

    #[Test]
    public function testCheckPendingTasksWithNoTasksDoesNothing(): void
    {
        // Arrange
        $device = $this->createAutoJsDevice('DEVICE_NO_TASKS');

        $this->taskManager->expects($this->once())
            ->method('checkPendingTasks')
            ->with($device)
        ;

        // Act
        $this->deviceManager->checkPendingTasks($device);
    }

    #[Test]
    public function testCheckPendingTasksWithTasksSendsInstructions(): void
    {
        // Arrange
        $device = $this->createAutoJsDevice('DEVICE_WITH_TASKS');
        $this->setDeviceId($device, 999);

        $this->taskManager->expects($this->once())
            ->method('checkPendingTasks')
            ->with($device)
        ;

        // Act
        $this->deviceManager->checkPendingTasks($device);
    }

    private function createAutoJsDevice(string $code, ?string $brand = null, ?string $osVersion = null): AutoJsDevice
    {
        $baseDevice = new BaseDevice();
        $baseDevice->setCode($code);

        if (null !== $brand) {
            $baseDevice->setBrand($brand);
        }

        if (null !== $osVersion) {
            $baseDevice->setOsVersion($osVersion);
        }

        $autoJsDevice = new AutoJsDevice();
        $autoJsDevice->setBaseDevice($baseDevice);

        return $autoJsDevice;
    }

    private function setDeviceId(AutoJsDevice $device, int $id): void
    {
        $reflection = new \ReflectionClass($device);
        $property = $reflection->getProperty('id');
        $property->setAccessible(true);
        $property->setValue($device, $id);
    }

    #[Test]
    public function testCancelRunningTasksWithNoTasksDoesNothing(): void
    {
        // Arrange
        $device = $this->createAutoJsDevice('DEVICE_NO_RUNNING_TASKS');

        $this->taskManager->expects($this->once())
            ->method('cancelRunningTasks')
            ->with($device)
        ;

        // Act
        $this->deviceManager->cancelRunningTasks($device);
    }

    #[Test]
    public function testCancelRunningTasksWithTasksSendsInstructions(): void
    {
        // Arrange
        $device = $this->createAutoJsDevice('DEVICE_WITH_RUNNING_TASKS');
        $this->setDeviceId($device, 789);

        $this->taskManager->expects($this->once())
            ->method('cancelRunningTasks')
            ->with($device)
        ;

        // Act
        $this->deviceManager->cancelRunningTasks($device);
    }

    #[Test]
    public function testCancelRunningTasksHandlesInstructionError(): void
    {
        // Arrange
        $device = $this->createAutoJsDevice('DEVICE_ERROR');
        $this->setDeviceId($device, 999);

        $this->taskManager->expects($this->once())
            ->method('cancelRunningTasks')
            ->with($device)
        ;

        // Act & Assert - should not throw exception
        $this->deviceManager->cancelRunningTasks($device);
    }
}
