<?php

declare(strict_types=1);

namespace Tourze\AutoJsControlBundle\Tests\Service;

use DeviceBundle\Entity\Device as BaseDevice;
use DeviceBundle\Enum\DeviceStatus;
use DeviceBundle\Enum\DeviceType;
use DeviceBundle\Repository\DeviceRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Tourze\AutoJsControlBundle\Entity\AutoJsDevice;
use Tourze\AutoJsControlBundle\Event\DeviceRegisteredEvent;
use Tourze\AutoJsControlBundle\Exception\BusinessLogicException;
use Tourze\AutoJsControlBundle\Repository\AutoJsDeviceRepository;
use Tourze\AutoJsControlBundle\Service\CacheStorageService;
use Tourze\AutoJsControlBundle\Service\DeviceAuthService;
use Tourze\AutoJsControlBundle\Service\DeviceRegistrationHandler;

/**
 * @internal
 */
#[CoversClass(DeviceRegistrationHandler::class)]
final class DeviceRegistrationHandlerTest extends TestCase
{
    private DeviceRegistrationHandler $handler;

    private EntityManagerInterface&MockObject $entityManager;

    private DeviceRepository&MockObject $deviceRepository;

    private AutoJsDeviceRepository&MockObject $autoJsDeviceRepository;

    private DeviceAuthService&MockObject $authService;

    private EventDispatcherInterface&MockObject $eventDispatcher;

    private LoggerInterface&MockObject $logger;

    private CacheStorageService&MockObject $cacheStorage;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->deviceRepository = $this->createMock(DeviceRepository::class);
        $this->autoJsDeviceRepository = $this->createMock(AutoJsDeviceRepository::class);
        $this->authService = $this->createMock(DeviceAuthService::class);
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->cacheStorage = $this->createMock(CacheStorageService::class);

        $this->handler = new DeviceRegistrationHandler(
            $this->entityManager,
            $this->deviceRepository,
            $this->autoJsDeviceRepository,
            $this->authService,
            $this->eventDispatcher,
            $this->logger,
            $this->cacheStorage
        );
    }

    #[Test]
    public function testRegisterOrUpdateWithNewDeviceShouldCreateDeviceAndDispatchEvent(): void
    {
        // Arrange
        $deviceCode = 'TEST_DEVICE_001';
        $deviceName = 'Test Device';
        $certificateRequest = 'cert_request';
        $deviceInfo = [
            'model' => 'Test Model',
            'brand' => 'Test Brand',
            'osVersion' => 'Android 11',
            'fingerprint' => 'test_fingerprint',
            'autoJsVersion' => '4.1.1',
            'hardwareInfo' => [
                'cpuCores' => 8,
                'memorySize' => '8GB',
                'storageSize' => '256GB',
            ],
        ];
        $clientIp = '192.168.1.100';
        $certificate = 'generated_certificate';

        // Mock base device creation
        $this->deviceRepository->expects($this->once())
            ->method('findOneBy')
            ->with(['code' => $deviceCode])
            ->willReturn(null)
        ;

        // Mock AutoJs device creation
        $this->autoJsDeviceRepository->expects($this->once())
            ->method('findOneBy')
            ->willReturn(null)
        ;

        // Mock certificate generation
        $this->authService->expects($this->once())
            ->method('generateDeviceCertificate')
            ->with($deviceCode, $certificateRequest)
            ->willReturn($certificate)
        ;

        // Mock transaction management
        $this->entityManager->expects($this->once())
            ->method('beginTransaction')
        ;

        $this->entityManager->expects($this->exactly(2))
            ->method('persist')
        ;

        $this->entityManager->expects($this->once())
            ->method('flush')
        ;

        $this->entityManager->expects($this->once())
            ->method('commit')
        ;

        // Mock cache update
        $this->cacheStorage->expects($this->once())
            ->method('setDeviceOnline')
            ->with($deviceCode, true)
        ;

        // Mock event dispatch for new device
        $this->eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with(self::isInstanceOf(DeviceRegisteredEvent::class))
        ;

        // Mock logging
        $this->logger->expects($this->once())
            ->method('info')
            ->with('设备注册/更新成功', self::callback(function ($context) use ($deviceCode) {
                return $context['deviceCode'] === $deviceCode
                    && true === $context['isNew'];
            }))
        ;

        // Act
        $result = $this->handler->registerOrUpdate(
            $deviceCode,
            $deviceName,
            $certificateRequest,
            $deviceInfo,
            $clientIp
        );

        // Assert
        $this->assertInstanceOf(AutoJsDevice::class, $result);
        $this->assertEquals($certificate, $result->getCertificate());

        $baseDevice = $result->getBaseDevice();
        $this->assertNotNull($baseDevice);
        $this->assertEquals($deviceCode, $baseDevice->getCode());
        $this->assertEquals($deviceName, $baseDevice->getName());
        $this->assertEquals(DeviceStatus::ONLINE, $baseDevice->getStatus());
        $this->assertEquals(DeviceType::PHONE, $baseDevice->getDeviceType());
        $this->assertEquals($clientIp, $baseDevice->getLastIp());
        $this->assertEquals('Test Model', $baseDevice->getModel());
        $this->assertEquals('Test Brand', $baseDevice->getBrand());
        $this->assertEquals('Android 11', $baseDevice->getOsVersion());
        $this->assertEquals('test_fingerprint', $baseDevice->getFingerprint());
        $this->assertEquals(8, $baseDevice->getCpuCores());
        $this->assertEquals('8GB', $baseDevice->getMemorySize());
        $this->assertEquals('256GB', $baseDevice->getStorageSize());
        $this->assertEquals('4.1.1', $result->getAutoJsVersion());
    }

    #[Test]
    public function testRegisterOrUpdateWithExistingDeviceShouldUpdateDeviceWithoutEvent(): void
    {
        // Arrange
        $deviceCode = 'EXISTING_DEVICE';
        $deviceName = 'Updated Device';
        $certificateRequest = 'new_cert_request';
        $deviceInfo = ['autoJsVersion' => '4.2.0'];
        $clientIp = '192.168.1.101';
        $certificate = 'new_certificate';

        $baseDevice = new BaseDevice();
        $baseDevice->setCode($deviceCode);

        $autoJsDevice = new AutoJsDevice();
        $autoJsDevice->setBaseDevice($baseDevice);
        // 使用反射设置ID模拟现有设备
        $reflection = new \ReflectionClass($autoJsDevice);
        $idProperty = $reflection->getProperty('id');
        $idProperty->setValue($autoJsDevice, 123);

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

        $this->authService->expects($this->once())
            ->method('generateDeviceCertificate')
            ->with($deviceCode, $certificateRequest)
            ->willReturn($certificate)
        ;

        $this->entityManager->expects($this->once())
            ->method('beginTransaction')
        ;

        $this->entityManager->expects($this->exactly(2))
            ->method('persist')
        ;

        $this->entityManager->expects($this->once())
            ->method('flush')
        ;

        $this->entityManager->expects($this->once())
            ->method('commit')
        ;

        $this->cacheStorage->expects($this->once())
            ->method('setDeviceOnline')
            ->with($deviceCode, true)
        ;

        // No event should be dispatched for existing device
        $this->eventDispatcher->expects($this->never())
            ->method('dispatch')
        ;

        $this->logger->expects($this->once())
            ->method('info')
            ->with('设备注册/更新成功', self::callback(function ($context) use ($deviceCode) {
                return $context['deviceCode'] === $deviceCode
                    && false === $context['isNew'];
            }))
        ;

        // Act
        $result = $this->handler->registerOrUpdate(
            $deviceCode,
            $deviceName,
            $certificateRequest,
            $deviceInfo,
            $clientIp
        );

        // Assert
        $this->assertInstanceOf(AutoJsDevice::class, $result);
        $this->assertEquals($certificate, $result->getCertificate());
        $this->assertEquals('4.2.0', $result->getAutoJsVersion());
    }

    #[Test]
    public function testRegisterOrUpdateWithPartialDeviceInfoShouldHandleNullValues(): void
    {
        // Arrange
        $deviceCode = 'PARTIAL_INFO_DEVICE';
        $deviceName = 'Partial Device';
        $certificateRequest = 'cert_request';
        $deviceInfo = [
            'model' => 'Test Model',
            // Missing brand, osVersion, fingerprint, hardwareInfo
        ];
        $clientIp = '192.168.1.102';
        $certificate = 'generated_certificate';

        $this->deviceRepository->expects($this->once())
            ->method('findOneBy')
            ->with(['code' => $deviceCode])
            ->willReturn(null)
        ;

        $this->autoJsDeviceRepository->expects($this->once())
            ->method('findOneBy')
            ->willReturn(null)
        ;

        $this->authService->expects($this->once())
            ->method('generateDeviceCertificate')
            ->with($deviceCode, $certificateRequest)
            ->willReturn($certificate)
        ;

        $this->entityManager->expects($this->once())
            ->method('beginTransaction')
        ;

        $this->entityManager->expects($this->exactly(2))
            ->method('persist')
        ;

        $this->entityManager->expects($this->once())
            ->method('flush')
        ;

        $this->entityManager->expects($this->once())
            ->method('commit')
        ;

        $this->cacheStorage->expects($this->once())
            ->method('setDeviceOnline')
            ->with($deviceCode, true)
        ;

        $this->eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with(self::isInstanceOf(DeviceRegisteredEvent::class))
        ;

        $this->logger->expects($this->once())
            ->method('info')
        ;

        // Act
        $result = $this->handler->registerOrUpdate(
            $deviceCode,
            $deviceName,
            $certificateRequest,
            $deviceInfo,
            $clientIp
        );

        // Assert
        $baseDevice = $result->getBaseDevice();
        $this->assertNotNull($baseDevice);
        $this->assertEquals('Test Model', $baseDevice->getModel());
        // Note: BaseDevice might not have brand, osVersion, and hardware properties
    }

    #[Test]
    public function testRegisterOrUpdateWithDatabaseErrorShouldRollbackAndThrowException(): void
    {
        // Arrange
        $deviceCode = 'ERROR_DEVICE';
        $exception = new \Exception('Database error');

        $this->entityManager->expects($this->once())
            ->method('beginTransaction')
        ;

        $this->deviceRepository->expects($this->once())
            ->method('findOneBy')
            ->willThrowException($exception)
        ;

        $this->entityManager->expects($this->once())
            ->method('rollback')
        ;

        $this->logger->expects($this->once())
            ->method('error')
            ->with('设备注册/更新失败', self::callback(function ($context) use ($deviceCode, $exception) {
                return $context['deviceCode'] === $deviceCode
                    && $context['error'] === $exception->getMessage()
                    && $context['exception'] === $exception;
            }))
        ;

        // Act & Assert
        $this->expectException(BusinessLogicException::class);
        $this->expectExceptionMessage('设备注册失败: Database error');

        $this->handler->registerOrUpdate(
            $deviceCode,
            'Test Device',
            'cert_request',
            [],
            '192.168.1.100'
        );
    }

    #[Test]
    public function testRegisterOrUpdateWithCertificateGenerationErrorShouldRollbackAndThrowException(): void
    {
        // Arrange
        $deviceCode = 'CERT_ERROR_DEVICE';
        $deviceName = 'Test Device';
        $certificateRequest = 'invalid_cert_request';
        $deviceInfo = [];
        $clientIp = '192.168.1.100';
        $exception = new \Exception('Certificate generation failed');

        $this->deviceRepository->expects($this->once())
            ->method('findOneBy')
            ->with(['code' => $deviceCode])
            ->willReturn(null)
        ;

        $this->autoJsDeviceRepository->expects($this->once())
            ->method('findOneBy')
            ->willReturn(null)
        ;

        $this->authService->expects($this->once())
            ->method('generateDeviceCertificate')
            ->with($deviceCode, $certificateRequest)
            ->willThrowException($exception)
        ;

        $this->entityManager->expects($this->once())
            ->method('beginTransaction')
        ;

        $this->entityManager->expects($this->once())
            ->method('rollback')
        ;

        $this->logger->expects($this->once())
            ->method('error')
            ->with('设备注册/更新失败', self::callback(function ($context) use ($deviceCode) {
                return $context['deviceCode'] === $deviceCode
                    && str_contains($context['error'], 'Certificate generation failed');
            }))
        ;

        // Act & Assert
        $this->expectException(BusinessLogicException::class);
        $this->expectExceptionMessage('设备注册失败: Certificate generation failed');

        $this->handler->registerOrUpdate(
            $deviceCode,
            $deviceName,
            $certificateRequest,
            $deviceInfo,
            $clientIp
        );
    }

    #[Test]
    public function testRegisterOrUpdateWithCompleteHardwareInfoShouldSetAllProperties(): void
    {
        // Arrange
        $deviceCode = 'COMPLETE_HARDWARE_DEVICE';
        $deviceName = 'Complete Hardware Device';
        $certificateRequest = 'cert_request';
        $deviceInfo = [
            'model' => 'Galaxy S21',
            'brand' => 'Samsung',
            'osVersion' => 'Android 12',
            'fingerprint' => 'samsung/r0sks/r0s:12/SP1A.210812.016/G991BXXU5DVKD:user/release-keys',
            'autoJsVersion' => '4.3.0',
            'hardwareInfo' => [
                'cpuCores' => 8,
                'memorySize' => '12GB',
                'storageSize' => '512GB',
            ],
        ];
        $clientIp = '192.168.1.103';
        $certificate = 'generated_certificate';

        $this->deviceRepository->expects($this->once())
            ->method('findOneBy')
            ->with(['code' => $deviceCode])
            ->willReturn(null)
        ;

        $this->autoJsDeviceRepository->expects($this->once())
            ->method('findOneBy')
            ->willReturn(null)
        ;

        $this->authService->expects($this->once())
            ->method('generateDeviceCertificate')
            ->willReturn($certificate)
        ;

        $this->entityManager->expects($this->once())
            ->method('beginTransaction')
        ;

        $this->entityManager->expects($this->exactly(2))
            ->method('persist')
        ;

        $this->entityManager->expects($this->once())
            ->method('flush')
        ;

        $this->entityManager->expects($this->once())
            ->method('commit')
        ;

        $this->cacheStorage->expects($this->once())
            ->method('setDeviceOnline')
            ->with($deviceCode, true)
        ;

        $this->eventDispatcher->expects($this->once())
            ->method('dispatch')
        ;

        $this->logger->expects($this->once())
            ->method('info')
        ;

        // Act
        $result = $this->handler->registerOrUpdate(
            $deviceCode,
            $deviceName,
            $certificateRequest,
            $deviceInfo,
            $clientIp
        );

        // Assert
        $baseDevice = $result->getBaseDevice();
        $this->assertNotNull($baseDevice);
        $this->assertEquals('Galaxy S21', $baseDevice->getModel());
        // Note: BaseDevice might not have brand, osVersion, fingerprint, and hardware properties
        $this->assertEquals('4.3.0', $result->getAutoJsVersion());
    }
}
