<?php

declare(strict_types=1);

namespace Tourze\AutoJsControlBundle\Tests\Repository;

use DeviceBundle\Entity\Device as BaseDevice;
use DeviceBundle\Enum\DeviceStatus;
use DeviceBundle\Enum\DeviceType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Test;
use Tourze\AutoJsControlBundle\Entity\AutoJsDevice;
use Tourze\AutoJsControlBundle\Entity\DeviceGroup;
use Tourze\AutoJsControlBundle\Repository\AutoJsDeviceRepository;
use Tourze\PHPUnitSymfonyKernelTest\AbstractRepositoryTestCase;

/**
 * @internal
 */
#[CoversClass(AutoJsDeviceRepository::class)]
#[RunTestsInSeparateProcesses]
final class AutoJsDeviceRepositoryTest extends AbstractRepositoryTestCase
{
    private AutoJsDeviceRepository $repository;

    protected function onSetUp(): void
    {
        $repository = self::getEntityManager()->getRepository(AutoJsDevice::class);
        $this->assertInstanceOf(AutoJsDeviceRepository::class, $repository);
        $this->repository = $repository;
    }

    #[Test]
    public function testFindByBaseDevice(): void
    {
        // 委托给现有测试
        $this->findByBaseDeviceReturnsCorrectDevice();
        // 添加基本断言
        $this->assertTrue(true);
    }

    #[Test]
    public function findByBaseDeviceReturnsCorrectDevice(): void
    {
        // Arrange
        $baseDevice = new BaseDevice();
        $baseDevice->setCode('TEST_DEVICE_001');
        $baseDevice->setName('Test Device');
        $baseDevice->setModel('TestModel-X1');
        $baseDevice->setDeviceType(DeviceType::PHONE);

        $autoJsDevice = new AutoJsDevice();
        $autoJsDevice->setBaseDevice($baseDevice);
        $autoJsDevice->setAutoJsVersion('4.1.1');

        $em = self::getEntityManager();
        $em->persist($baseDevice);
        $em->persist($autoJsDevice);
        $em->flush();

        // Act
        $baseDeviceId = $baseDevice->getId();
        $this->assertNotNull($baseDeviceId, 'BaseDevice should have an ID after flush');
        $found = $this->repository->findByBaseDevice($baseDeviceId);

        // Assert
        $this->assertNotNull($found);
        $this->assertEquals($autoJsDevice->getId(), $found->getId());
        $this->assertEquals('4.1.1', $found->getAutoJsVersion());
    }

    #[Test]
    public function findByBaseDeviceReturnsNullWhenNotFound(): void
    {
        // Arrange
        $baseDevice = new BaseDevice();
        $baseDevice->setCode('NON_EXISTENT');
        $baseDevice->setModel('TestModel-X1');
        $baseDevice->setDeviceType(DeviceType::PHONE);

        $em = self::getEntityManager();
        $em->persist($baseDevice);
        $em->flush();

        // Act
        $baseDeviceId = $baseDevice->getId();
        $this->assertNotNull($baseDeviceId, 'BaseDevice should have an ID after flush');
        $found = $this->repository->findByBaseDevice($baseDeviceId);

        // Assert
        $this->assertNull($found);
    }

    #[Test]
    public function testFindByDeviceGroup(): void
    {
        // 委托给现有测试
        $this->findByDeviceGroupReturnsCorrectDevices();
        // 添加基本断言
        $this->assertTrue(true);
    }

    #[Test]
    public function findByDeviceGroupReturnsCorrectDevices(): void
    {
        // Arrange
        $group = new DeviceGroup();
        $group->setName('Test Group');

        $device1 = $this->createAutoJsDevice('DEVICE_001', $group);
        $baseDevice1 = $device1->getBaseDevice();
        $this->assertNotNull($baseDevice1, 'Device should have a base device');
        $baseDevice1->setValid(true);

        $device2 = $this->createAutoJsDevice('DEVICE_002', $group);
        $baseDevice2 = $device2->getBaseDevice();
        $this->assertNotNull($baseDevice2, 'Device should have a base device');
        $baseDevice2->setValid(true);

        $device3 = $this->createAutoJsDevice('DEVICE_003', null); // Not in group
        $baseDevice3 = $device3->getBaseDevice();
        $this->assertNotNull($baseDevice3, 'Device should have a base device');
        $baseDevice3->setValid(true);

        $em = self::getEntityManager();
        $em->persist($group);
        $em->persist($device1);
        $em->persist($device2);
        $em->persist($device3);
        $em->flush();

        // Act
        $devices = $this->repository->findByDeviceGroup($group);

        // Assert
        $this->assertCount(2, $devices);
        $deviceCodes = array_map(fn ($d) => $d->getDeviceCode(), $devices);
        $this->assertContains('DEVICE_001', $deviceCodes);
        $this->assertContains('DEVICE_002', $deviceCodes);
        $this->assertNotContains('DEVICE_003', $deviceCodes);
    }

    #[Test]
    public function testFindActiveDevices(): void
    {
        // 委托给现有测试
        $this->findActiveDevicesReturnsOnlyActiveDevices();
        // 添加基本断言
        $this->assertTrue(true);
    }

    #[Test]
    public function findActiveDevicesReturnsOnlyActiveDevices(): void
    {
        // Arrange
        $activeDevice = $this->createAutoJsDevice('ACTIVE_001');
        $activeBaseDevice = $activeDevice->getBaseDevice();
        $this->assertNotNull($activeBaseDevice, 'Device should have a base device');
        $activeBaseDevice->setValid(true);
        $activeBaseDevice->setStatus(DeviceStatus::ONLINE);

        $disabledDevice = $this->createAutoJsDevice('DISABLED_001');
        $disabledBaseDevice = $disabledDevice->getBaseDevice();
        $this->assertNotNull($disabledBaseDevice, 'Device should have a base device');
        $disabledBaseDevice->setValid(false);
        $disabledBaseDevice->setStatus(DeviceStatus::OFFLINE);

        $em = self::getEntityManager();
        $em->persist($activeDevice);
        $em->persist($disabledDevice);
        $em->flush();

        // Act
        $devices = $this->repository->findActiveDevices();

        // Assert
        $deviceCodes = array_map(fn ($d) => $d->getDeviceCode(), $devices);
        $this->assertContains('ACTIVE_001', $deviceCodes);
        $this->assertNotContains('DISABLED_001', $deviceCodes);
    }

    #[Test]
    public function testFindByWsConnectionId(): void
    {
        // 委托给现有测试
        $this->findByWsConnectionIdReturnsCorrectDevice();
        // 添加基本断言
        $this->assertTrue(true);
    }

    #[Test]
    public function findByWsConnectionIdReturnsCorrectDevice(): void
    {
        // Arrange
        $device = $this->createAutoJsDevice('WS_DEVICE_001');
        $device->setWsConnectionId('ws-conn-123456');

        $em = self::getEntityManager();
        $em->persist($device);
        $em->flush();

        // Act
        $found = $this->repository->findByWsConnectionId('ws-conn-123456');

        // Assert
        $this->assertNotNull($found);
        $this->assertEquals('WS_DEVICE_001', $found->getDeviceCode());
    }

    #[Test]
    public function getDeviceCountReturnsCorrectCount(): void
    {
        // Arrange
        $device1 = $this->createAutoJsDevice('COUNT_001');
        $baseDevice1 = $device1->getBaseDevice();
        $this->assertNotNull($baseDevice1, 'Device should have a base device');
        $baseDevice1->setValid(true);

        $device2 = $this->createAutoJsDevice('COUNT_002');
        $baseDevice2 = $device2->getBaseDevice();
        $this->assertNotNull($baseDevice2, 'Device should have a base device');
        $baseDevice2->setValid(true);

        $device3 = $this->createAutoJsDevice('COUNT_003');
        $baseDevice3 = $device3->getBaseDevice();
        $this->assertNotNull($baseDevice3, 'Device should have a base device');
        $baseDevice3->setValid(true);

        $em = self::getEntityManager();
        $em->persist($device1);
        $em->persist($device2);
        $em->persist($device3);
        $em->flush();

        // Act
        $count = $this->repository->getDeviceCount();

        // Assert
        $this->assertGreaterThanOrEqual(3, $count);
    }

    #[Test]
    public function findByDeviceCodesReturnsCorrectDevices(): void
    {
        // Arrange
        $device1 = $this->createAutoJsDevice('BATCH_001');
        $device2 = $this->createAutoJsDevice('BATCH_002');
        $device3 = $this->createAutoJsDevice('BATCH_003');

        $em = self::getEntityManager();
        $em->persist($device1);
        $em->persist($device2);
        $em->persist($device3);
        $em->flush();

        // Act
        $devices = $this->repository->findByDeviceCodes(['BATCH_001', 'BATCH_003']);

        // Assert
        $this->assertCount(2, $devices);
        $deviceCodes = array_map(fn ($d) => $d->getDeviceCode(), $devices);
        $this->assertContains('BATCH_001', $deviceCodes);
        $this->assertContains('BATCH_003', $deviceCodes);
        $this->assertNotContains('BATCH_002', $deviceCodes);
    }

    #[Test]
    public function testFindOnlineDevices(): void
    {
        // 委托给现有测试
        $this->findOnlineDevicesReturnsOnlyOnlineDevices();
        // 添加基本断言
        $this->assertTrue(true);
    }

    #[Test]
    public function findOnlineDevicesReturnsOnlyOnlineDevices(): void
    {
        // Arrange
        $onlineDevice1 = $this->createAutoJsDevice('ONLINE_001');
        $onlineBaseDevice1 = $onlineDevice1->getBaseDevice();
        $this->assertNotNull($onlineBaseDevice1, 'Device should have a base device');
        $onlineBaseDevice1->setStatus(DeviceStatus::ONLINE);
        $onlineBaseDevice1->setValid(true);
        $onlineBaseDevice1->setLastOnlineTime(new \DateTimeImmutable());

        $onlineDevice2 = $this->createAutoJsDevice('ONLINE_002');
        $onlineBaseDevice2 = $onlineDevice2->getBaseDevice();
        $this->assertNotNull($onlineBaseDevice2, 'Device should have a base device');
        $onlineBaseDevice2->setStatus(DeviceStatus::ONLINE);
        $onlineBaseDevice2->setValid(true);
        $onlineBaseDevice2->setLastOnlineTime(new \DateTimeImmutable());

        $offlineDevice = $this->createAutoJsDevice('OFFLINE_001');
        $offlineBaseDevice = $offlineDevice->getBaseDevice();
        $this->assertNotNull($offlineBaseDevice, 'Device should have a base device');
        $offlineBaseDevice->setStatus(DeviceStatus::OFFLINE);
        $offlineBaseDevice->setValid(true);

        $em = self::getEntityManager();
        $em->persist($onlineDevice1);
        $em->persist($onlineDevice2);
        $em->persist($offlineDevice);
        $em->flush();

        // Act
        $devices = $this->repository->findOnlineDevices();

        // Assert - 只检查我们创建的测试设备是否在结果中
        $deviceCodes = array_map(fn ($d) => $d->getDeviceCode(), $devices);
        $this->assertContains('ONLINE_001', $deviceCodes);
        $this->assertContains('ONLINE_002', $deviceCodes);
        $this->assertNotContains('OFFLINE_001', $deviceCodes);
    }

    #[Test]
    public function testFindOfflineDevices(): void
    {
        // 委托给现有测试
        $this->findOfflineDevicesReturnsDevicesOfflineAfterThreshold();
        // 添加基本断言
        $this->assertTrue(true);
    }

    #[Test]
    public function findOfflineDevicesReturnsDevicesOfflineAfterThreshold(): void
    {
        // Arrange
        $threshold = new \DateTime('-30 minutes');

        $recentOfflineDevice = $this->createAutoJsDevice('RECENT_OFFLINE');
        $recentOfflineBaseDevice = $recentOfflineDevice->getBaseDevice();
        $this->assertNotNull($recentOfflineBaseDevice, 'Device should have a base device');
        $recentOfflineBaseDevice->setStatus(DeviceStatus::OFFLINE);
        $recentOfflineBaseDevice->setLastOnlineTime(new \DateTimeImmutable('-10 minutes'));
        $recentOfflineBaseDevice->setValid(true);

        $oldOfflineDevice = $this->createAutoJsDevice('OLD_OFFLINE');
        $oldOfflineBaseDevice = $oldOfflineDevice->getBaseDevice();
        $this->assertNotNull($oldOfflineBaseDevice, 'Device should have a base device');
        $oldOfflineBaseDevice->setStatus(DeviceStatus::OFFLINE);
        $oldOfflineBaseDevice->setLastOnlineTime(new \DateTimeImmutable('-2 hours'));
        $oldOfflineBaseDevice->setValid(true);

        $onlineDevice = $this->createAutoJsDevice('STILL_ONLINE');
        $onlineStillBaseDevice = $onlineDevice->getBaseDevice();
        $this->assertNotNull($onlineStillBaseDevice, 'Device should have a base device');
        $onlineStillBaseDevice->setStatus(DeviceStatus::ONLINE);
        $onlineStillBaseDevice->setLastOnlineTime(new \DateTimeImmutable());
        $onlineStillBaseDevice->setValid(true);

        $em = self::getEntityManager();
        $em->persist($recentOfflineDevice);
        $em->persist($oldOfflineDevice);
        $em->persist($onlineDevice);
        $em->flush();

        // Act
        $devices = $this->repository->findOfflineDevices($threshold);

        // Assert
        $this->assertCount(1, $devices);
        $this->assertEquals('OLD_OFFLINE', $devices[0]->getDeviceCode());
    }

    #[Test]
    public function findByDeviceCodeReturnsCorrectDevice(): void
    {
        // Arrange
        $device = $this->createAutoJsDevice('FIND_BY_CODE_001');

        $em = self::getEntityManager();
        $em->persist($device);
        $em->flush();

        // Act
        $found = $this->repository->findByDeviceCode('FIND_BY_CODE_001');

        // Assert
        $this->assertNotNull($found);
        $this->assertEquals('FIND_BY_CODE_001', $found->getDeviceCode());
    }

    #[Test]
    public function findByDeviceCodeReturnsNullWhenNotFound(): void
    {
        // Act
        $found = $this->repository->findByDeviceCode('NON_EXISTENT_CODE');

        // Assert
        $this->assertNull($found);
    }

    #[Test]
    public function countByStatusReturnsCorrectCounts(): void
    {
        // Arrange
        $online1 = $this->createAutoJsDevice('STATUS_ONLINE_1');
        $online1BaseDevice = $online1->getBaseDevice();
        $this->assertNotNull($online1BaseDevice, 'Device should have a base device');
        $online1BaseDevice->setStatus(DeviceStatus::ONLINE);
        $online1BaseDevice->setValid(true);

        $online2 = $this->createAutoJsDevice('STATUS_ONLINE_2');
        $online2BaseDevice = $online2->getBaseDevice();
        $this->assertNotNull($online2BaseDevice, 'Device should have a base device');
        $online2BaseDevice->setStatus(DeviceStatus::ONLINE);
        $online2BaseDevice->setValid(true);

        $offline1 = $this->createAutoJsDevice('STATUS_OFFLINE_1');
        $offline1BaseDevice = $offline1->getBaseDevice();
        $this->assertNotNull($offline1BaseDevice, 'Device should have a base device');
        $offline1BaseDevice->setStatus(DeviceStatus::OFFLINE);
        $offline1BaseDevice->setValid(true);

        $disabled1 = $this->createAutoJsDevice('STATUS_DISABLED_1');
        $disabled1BaseDevice = $disabled1->getBaseDevice();
        $this->assertNotNull($disabled1BaseDevice, 'Device should have a base device');
        $disabled1BaseDevice->setStatus(DeviceStatus::DISABLED);
        $disabled1BaseDevice->setValid(false);

        $em = self::getEntityManager();
        $em->persist($online1);
        $em->persist($online2);
        $em->persist($offline1);
        $em->persist($disabled1);
        $em->flush();

        // Act
        $counts = $this->repository->countByStatus();

        // Assert - 检查我们的设备是否正确计算在内
        $this->assertArrayHasKey('ONLINE', $counts);
        $this->assertArrayHasKey('OFFLINE', $counts);
        $this->assertGreaterThanOrEqual(2, $counts['ONLINE']); // 至少包含我们创建的2个在线设备
        $this->assertGreaterThanOrEqual(1, $counts['OFFLINE']); // 至少包含我们创建的1个离线设备

        // DISABLED 状态的设备不包含在结果中，因为 disabled 设备的 valid=false
    }

    private function createAutoJsDevice(string $code, ?DeviceGroup $group = null): AutoJsDevice
    {
        $baseDevice = new BaseDevice();
        $baseDevice->setCode($code);
        $baseDevice->setName('Device ' . $code);
        $baseDevice->setModel('TestModel-' . $code);
        $baseDevice->setDeviceType(DeviceType::PHONE);

        $autoJsDevice = new AutoJsDevice();
        $autoJsDevice->setBaseDevice($baseDevice);
        $autoJsDevice->setDeviceGroup($group);

        return $autoJsDevice;
    }

    #[Test]
    public function testCountByStatusShouldReturnCorrectCounts(): void
    {
        // Arrange
        $device1 = $this->createAutoJsDevice('COUNT_STATUS_001');
        $device1BaseDevice = $device1->getBaseDevice();
        $this->assertNotNull($device1BaseDevice, 'Device should have a base device');
        $device1BaseDevice->setStatus(DeviceStatus::ONLINE);
        $device1BaseDevice->setValid(true);

        $device2 = $this->createAutoJsDevice('COUNT_STATUS_002');
        $device2BaseDevice = $device2->getBaseDevice();
        $this->assertNotNull($device2BaseDevice, 'Device should have a base device');
        $device2BaseDevice->setStatus(DeviceStatus::OFFLINE);
        $device2BaseDevice->setValid(true);

        $em = self::getEntityManager();
        $em->persist($device1);
        $em->persist($device2);
        $em->flush();

        // Act
        $counts = $this->repository->countByStatus();

        // Assert
        $this->assertIsArray($counts);
        $this->assertArrayHasKey('ONLINE', $counts);
        $this->assertArrayHasKey('OFFLINE', $counts);
        $this->assertGreaterThanOrEqual(1, $counts['ONLINE']);
        $this->assertGreaterThanOrEqual(1, $counts['OFFLINE']);
    }

    #[Test]
    public function testFindAllOnlineDevicesShouldReturnOnlineDevices(): void
    {
        // Arrange
        $onlineDevice = $this->createAutoJsDevice('ALL_ONLINE_001');
        $onlineDeviceBase = $onlineDevice->getBaseDevice();
        $this->assertNotNull($onlineDeviceBase, 'Device should have a base device');
        $onlineDeviceBase->setStatus(DeviceStatus::ONLINE);
        $onlineDeviceBase->setValid(true);
        $onlineDeviceBase->setLastOnlineTime(new \DateTimeImmutable());

        $offlineDevice = $this->createAutoJsDevice('ALL_ONLINE_002');
        $offlineDeviceBase = $offlineDevice->getBaseDevice();
        $this->assertNotNull($offlineDeviceBase, 'Device should have a base device');
        $offlineDeviceBase->setStatus(DeviceStatus::OFFLINE);
        $offlineDeviceBase->setValid(true);

        $em = self::getEntityManager();
        $em->persist($onlineDevice);
        $em->persist($offlineDevice);
        $em->flush();

        // Act
        $devices = $this->repository->findAllOnlineDevices();

        // Assert
        $this->assertIsArray($devices);
        $deviceCodes = array_map(fn ($d) => $d->getDeviceCode(), $devices);
        $this->assertContains('ALL_ONLINE_001', $deviceCodes);
        $this->assertNotContains('ALL_ONLINE_002', $deviceCodes);
    }

    #[Test]
    public function testFindByDeviceCodesShouldReturnCorrectDevices(): void
    {
        // Arrange
        $device1 = $this->createAutoJsDevice('CODES_001');
        $device2 = $this->createAutoJsDevice('CODES_002');
        $device3 = $this->createAutoJsDevice('CODES_003');

        $em = self::getEntityManager();
        $em->persist($device1);
        $em->persist($device2);
        $em->persist($device3);
        $em->flush();

        // Act
        $devices = $this->repository->findByDeviceCodes(['CODES_001', 'CODES_003']);

        // Assert
        $this->assertIsArray($devices);
        $this->assertCount(2, $devices);
        $deviceCodes = array_map(fn ($d) => $d->getDeviceCode(), $devices);
        $this->assertContains('CODES_001', $deviceCodes);
        $this->assertContains('CODES_003', $deviceCodes);
        $this->assertNotContains('CODES_002', $deviceCodes);
    }

    #[Test]
    public function testFindByGroupShouldReturnDevicesInGroup(): void
    {
        // Arrange
        $group = new DeviceGroup();
        $group->setName('Test Group For findByGroup');

        $device1 = $this->createAutoJsDevice('GROUP_001', $group);
        $device1BaseDevice = $device1->getBaseDevice();
        $this->assertNotNull($device1BaseDevice, 'Device should have a base device');
        $device1BaseDevice->setValid(true);

        $device2 = $this->createAutoJsDevice('GROUP_002', $group);
        $device2BaseDevice = $device2->getBaseDevice();
        $this->assertNotNull($device2BaseDevice, 'Device should have a base device');
        $device2BaseDevice->setValid(true);
        $device3 = $this->createAutoJsDevice('GROUP_003'); // No group

        $em = self::getEntityManager();
        $em->persist($group);
        $em->persist($device1);
        $em->persist($device2);
        $em->persist($device3);
        $em->flush();

        // Act
        $devices = $this->repository->findByGroup($group);

        // Assert
        $this->assertIsArray($devices);
        $this->assertCount(2, $devices);
        $deviceCodes = array_map(fn ($d) => $d->getDeviceCode(), $devices);
        $this->assertContains('GROUP_001', $deviceCodes);
        $this->assertContains('GROUP_002', $deviceCodes);
        $this->assertNotContains('GROUP_003', $deviceCodes);
    }

    #[Test]
    public function testSaveShouldPersistEntity(): void
    {
        // Arrange
        $device = $this->createAutoJsDevice('SAVE_001');
        $device->setAutoJsVersion('4.2.0');

        // Persist baseDevice first to avoid cascade issues
        $em = self::getEntityManager();
        $deviceBaseDevice = $device->getBaseDevice();
        $this->assertNotNull($deviceBaseDevice, 'Device should have a base device');
        $em->persist($deviceBaseDevice);

        // Act
        $this->repository->save($device, true);

        // Assert
        $deviceId = $device->getId();
        $this->assertNotNull($deviceId, 'Device should have an ID after save');

        // Verify it's actually persisted
        $found = $this->repository->find($deviceId);
        $this->assertNotNull($found);
        $this->assertEquals('4.2.0', $found->getAutoJsVersion());
        $this->assertEquals('SAVE_001', $found->getDeviceCode());
    }

    #[Test]
    public function testRemoveShouldDeleteEntity(): void
    {
        // Arrange
        $device = $this->createAutoJsDevice('REMOVE_001');
        $em = self::getEntityManager();
        $em->persist($device);
        $em->flush();

        $deviceId = $device->getId();
        $this->assertNotNull($deviceId);

        // Act
        $this->repository->remove($device);

        // Assert
        $found = $this->repository->find($deviceId);
        $this->assertNull($found);
    }

    #[Test]
    public function testFindAssociationQueryShouldFindByDeviceGroup(): void
    {
        // Arrange
        $group1 = new DeviceGroup();
        $group1->setName('Association Group 1');
        $group2 = new DeviceGroup();
        $group2->setName('Association Group 2');

        $device1 = $this->createAutoJsDevice('ASSOC_001', $group1);
        $device2 = $this->createAutoJsDevice('ASSOC_002', $group1);
        $device3 = $this->createAutoJsDevice('ASSOC_003', $group2);

        $em = self::getEntityManager();
        $em->persist($group1);
        $em->persist($group2);
        $em->persist($device1);
        $em->persist($device2);
        $em->persist($device3);
        $em->flush();

        // Act
        $devicesInGroup1 = $this->repository->findBy(['deviceGroup' => $group1]);

        // Assert
        $this->assertCount(2, $devicesInGroup1);
        foreach ($devicesInGroup1 as $device) {
            $deviceGroup = $device->getDeviceGroup();
            $this->assertNotNull($deviceGroup, 'Device should have a device group');
            $this->assertEquals($group1->getId(), $deviceGroup->getId());
        }
    }

    #[Test]
    public function testCountAssociationQueryShouldCountByDeviceGroup(): void
    {
        // Arrange
        $group = new DeviceGroup();
        $group->setName('Count Association Group');

        $device1 = $this->createAutoJsDevice('COUNT_ASSOC_001', $group);
        $device2 = $this->createAutoJsDevice('COUNT_ASSOC_002', $group);

        $em = self::getEntityManager();
        $em->persist($group);
        $em->persist($device1);
        $em->persist($device2);
        $em->flush();

        // Act
        $count = $this->repository->count(['deviceGroup' => $group]);

        // Assert
        $this->assertEquals(2, $count);
    }

    #[Test]
    public function testFindByNullFieldShouldReturnEntitiesWithNullValues(): void
    {
        // Arrange
        $deviceWithGroup = $this->createAutoJsDevice('NULL_001');
        $group = new DeviceGroup();
        $group->setName('Not Null Group');
        $deviceWithGroup->setDeviceGroup($group);

        $deviceWithoutGroup = $this->createAutoJsDevice('NULL_002');
        // deviceGroup is null by default

        $em = self::getEntityManager();
        $em->persist($group);
        $em->persist($deviceWithGroup);
        $em->persist($deviceWithoutGroup);
        $em->flush();

        // Act
        $devicesWithoutGroup = $this->repository->findBy(['deviceGroup' => null]);

        // Assert
        $this->assertIsArray($devicesWithoutGroup);
        $deviceCodes = array_map(fn ($d) => $d->getDeviceCode(), $devicesWithoutGroup);
        $this->assertContains('NULL_002', $deviceCodes);
        $this->assertNotContains('NULL_001', $deviceCodes);
    }

    #[Test]
    public function testCountByNullFieldShouldReturnCorrectCount(): void
    {
        // Arrange
        $deviceWithGroup = $this->createAutoJsDevice('COUNT_NULL_001');
        $group = new DeviceGroup();
        $group->setName('Count Null Group');
        $deviceWithGroup->setDeviceGroup($group);

        $deviceWithoutGroup1 = $this->createAutoJsDevice('COUNT_NULL_002');
        $deviceWithoutGroup2 = $this->createAutoJsDevice('COUNT_NULL_003');

        $em = self::getEntityManager();
        $em->persist($group);
        $em->persist($deviceWithGroup);
        $em->persist($deviceWithoutGroup1);
        $em->persist($deviceWithoutGroup2);
        $em->flush();

        // Act
        $countWithoutGroup = $this->repository->count(['deviceGroup' => null]);

        // Assert
        $this->assertGreaterThanOrEqual(2, $countWithoutGroup);
    }

    #[Test]
    public function testFindDevicesForHeartbeatCheckShouldReturnCorrectDevices(): void
    {
        // Arrange
        $threshold = new \DateTime('-5 minutes');

        $oldDevice = $this->createAutoJsDevice('HEARTBEAT_OLD');
        $oldDeviceBase = $oldDevice->getBaseDevice();
        $this->assertNotNull($oldDeviceBase, 'Device should have a base device');
        $oldDeviceBase->setStatus(DeviceStatus::ONLINE);
        $oldDeviceBase->setLastOnlineTime(new \DateTimeImmutable('-10 minutes'));
        $oldDeviceBase->setValid(true);

        $recentDevice = $this->createAutoJsDevice('HEARTBEAT_RECENT');
        $recentDeviceBase = $recentDevice->getBaseDevice();
        $this->assertNotNull($recentDeviceBase, 'Device should have a base device');
        $recentDeviceBase->setStatus(DeviceStatus::ONLINE);
        $recentDeviceBase->setLastOnlineTime(new \DateTimeImmutable('-2 minutes'));
        $recentDeviceBase->setValid(true);

        $em = self::getEntityManager();
        $em->persist($oldDevice);
        $em->persist($recentDevice);
        $em->flush();

        // Act
        $devices = $this->repository->findDevicesForHeartbeatCheck($threshold);

        // Assert
        $this->assertIsArray($devices);
        $deviceCodes = array_map(fn ($d) => $d->getDeviceCode(), $devices);
        $this->assertContains('HEARTBEAT_OLD', $deviceCodes);
        $this->assertNotContains('HEARTBEAT_RECENT', $deviceCodes);
    }

    #[Test]
    public function testFindOneByDeviceCodeShouldReturnCorrectDevice(): void
    {
        // Arrange
        $device = $this->createAutoJsDevice('FIND_ONE_BY_CODE');

        $em = self::getEntityManager();
        $em->persist($device);
        $em->flush();

        // Act
        $found = $this->repository->findOneByDeviceCode('FIND_ONE_BY_CODE');

        // Assert
        $this->assertNotNull($found);
        $this->assertEquals('FIND_ONE_BY_CODE', $found->getDeviceCode());
    }

    #[Test]
    public function testFindByNullWsConnectionIdShouldReturnDevicesWithoutConnection(): void
    {
        // Arrange
        $deviceWithConnection = $this->createAutoJsDevice('WITH_WS');
        $deviceWithConnection->setWsConnectionId('ws-123');

        $deviceWithoutConnection = $this->createAutoJsDevice('WITHOUT_WS');
        $deviceWithoutConnection->setWsConnectionId(null);

        $em = self::getEntityManager();
        $em->persist($deviceWithConnection);
        $em->persist($deviceWithoutConnection);
        $em->flush();

        // Act
        $devicesWithoutConnection = $this->repository->findBy(['wsConnectionId' => null]);

        // Assert
        $this->assertIsArray($devicesWithoutConnection);
        $deviceCodes = array_map(fn ($d) => $d->getDeviceCode(), $devicesWithoutConnection);
        $this->assertContains('WITHOUT_WS', $deviceCodes);
        $this->assertNotContains('WITH_WS', $deviceCodes);
    }

    #[Test]
    public function testCountByNullWsConnectionIdShouldReturnCorrectCount(): void
    {
        // Arrange
        $deviceWithConnection = $this->createAutoJsDevice('COUNT_WITH_WS');
        $deviceWithConnection->setWsConnectionId('ws-456');

        $deviceWithoutConnection1 = $this->createAutoJsDevice('COUNT_WITHOUT_WS_1');
        $deviceWithoutConnection1->setWsConnectionId(null);

        $deviceWithoutConnection2 = $this->createAutoJsDevice('COUNT_WITHOUT_WS_2');
        $deviceWithoutConnection2->setWsConnectionId(null);

        $em = self::getEntityManager();
        $em->persist($deviceWithConnection);
        $em->persist($deviceWithoutConnection1);
        $em->persist($deviceWithoutConnection2);
        $em->flush();

        // Act
        $countWithoutConnection = $this->repository->count(['wsConnectionId' => null]);

        // Assert
        $this->assertGreaterThanOrEqual(2, $countWithoutConnection);
    }

    #[Test]
    public function testFindByNullCertificateShouldReturnDevicesWithoutCertificate(): void
    {
        // Arrange
        $deviceWithCert = $this->createAutoJsDevice('WITH_CERT');
        $deviceWithCert->setCertificate('certificate-data');

        $deviceWithoutCert = $this->createAutoJsDevice('WITHOUT_CERT');
        $deviceWithoutCert->setCertificate(null);

        $em = self::getEntityManager();
        $em->persist($deviceWithCert);
        $em->persist($deviceWithoutCert);
        $em->flush();

        // Act
        $devicesWithoutCert = $this->repository->findBy(['certificate' => null]);

        // Assert
        $this->assertIsArray($devicesWithoutCert);
        $deviceCodes = array_map(fn ($d) => $d->getDeviceCode(), $devicesWithoutCert);
        $this->assertContains('WITHOUT_CERT', $deviceCodes);
        $this->assertNotContains('WITH_CERT', $deviceCodes);
    }

    #[Test]
    public function testCountByNullCertificateShouldReturnCorrectCount(): void
    {
        // Arrange
        $deviceWithCert = $this->createAutoJsDevice('COUNT_WITH_CERT');
        $deviceWithCert->setCertificate('certificate-data-123');

        $deviceWithoutCert1 = $this->createAutoJsDevice('COUNT_WITHOUT_CERT_1');
        $deviceWithoutCert1->setCertificate(null);

        $deviceWithoutCert2 = $this->createAutoJsDevice('COUNT_WITHOUT_CERT_2');
        $deviceWithoutCert2->setCertificate(null);

        $em = self::getEntityManager();
        $em->persist($deviceWithCert);
        $em->persist($deviceWithoutCert1);
        $em->persist($deviceWithoutCert2);
        $em->flush();

        // Act
        $countWithoutCert = $this->repository->count(['certificate' => null]);

        // Assert
        $this->assertGreaterThanOrEqual(2, $countWithoutCert);
    }

    #[Test]
    public function testFindOneByAssociationDeviceGroupShouldReturnMatchingEntity(): void
    {
        // Arrange
        $group1 = new DeviceGroup();
        $group1->setName('Association Test Group 1');
        $group2 = new DeviceGroup();
        $group2->setName('Association Test Group 2');

        $device1 = $this->createAutoJsDevice('ASSOC_FIND_ONE_1', $group1);
        $device2 = $this->createAutoJsDevice('ASSOC_FIND_ONE_2', $group2);

        $em = self::getEntityManager();
        $em->persist($group1);
        $em->persist($group2);
        $em->persist($device1);
        $em->persist($device2);
        $em->flush();

        // Act
        $found = $this->repository->findOneBy(['deviceGroup' => $group1]);

        // Assert
        $this->assertNotNull($found);
        $foundDeviceGroup = $found->getDeviceGroup();
        $this->assertNotNull($foundDeviceGroup, 'Found device should have a device group');
        $this->assertEquals($group1->getId(), $foundDeviceGroup->getId());
        $this->assertEquals('ASSOC_FIND_ONE_1', $found->getDeviceCode());
    }

    #[Test]
    public function testCountByAssociationDeviceGroupShouldReturnCorrectNumber(): void
    {
        // Arrange
        $group = new DeviceGroup();
        $group->setName('Association Count Group');

        $device1 = $this->createAutoJsDevice('ASSOC_COUNT_1', $group);
        $device2 = $this->createAutoJsDevice('ASSOC_COUNT_2', $group);
        $device3 = $this->createAutoJsDevice('ASSOC_COUNT_3', $group);
        $device4 = $this->createAutoJsDevice('ASSOC_COUNT_4'); // No group

        $em = self::getEntityManager();
        $em->persist($group);
        $em->persist($device1);
        $em->persist($device2);
        $em->persist($device3);
        $em->persist($device4);
        $em->flush();

        // Act
        $count = $this->repository->count(['deviceGroup' => $group]);

        // Assert
        $this->assertEquals(3, $count);
    }

    #[Test]
    public function testFindAllWithBaseDevice(): void
    {
        // Arrange
        $device1 = $this->createAutoJsDevice('WITH_BASE_001');
        $device2 = $this->createAutoJsDevice('WITH_BASE_002');
        $device3 = $this->createAutoJsDevice('WITH_BASE_003');

        $em = self::getEntityManager();
        $em->persist($device1);
        $em->persist($device2);
        $em->persist($device3);
        $em->flush();

        // Clear entity manager to ensure we're testing eager loading
        $em->clear();

        // Act
        $devices = $this->repository->findAllWithBaseDevice();

        // Assert
        $this->assertIsArray($devices);
        $this->assertGreaterThanOrEqual(3, count($devices));

        // Find our test devices in the result
        $testDeviceCodes = ['WITH_BASE_001', 'WITH_BASE_002', 'WITH_BASE_003'];
        $foundDevices = array_filter($devices, function ($device) use ($testDeviceCodes) {
            $baseDevice = $device->getBaseDevice();
            if (null === $baseDevice) {
                return false;
            }

            return in_array($baseDevice->getCode(), $testDeviceCodes, true);
        });

        $this->assertCount(3, $foundDevices);

        // Verify that baseDevice is already loaded (no lazy loading)
        foreach ($foundDevices as $device) {
            $baseDevice = $device->getBaseDevice();
            $this->assertNotNull($baseDevice);
            $this->assertNotNull($baseDevice->getId());
            $this->assertNotNull($baseDevice->getName());
            $this->assertNotNull($baseDevice->getCode());
        }
    }

    #[Test]
    public function testFindAllWithBaseDeviceReturnsEmpty(): void
    {
        // Arrange - 清空所有设备（如果有）
        $em = self::getEntityManager();
        $connection = $em->getConnection();
        $connection->executeStatement('DELETE FROM auto_js_device');
        $connection->executeStatement('DELETE FROM device');

        // Act
        $devices = $this->repository->findAllWithBaseDevice();

        // Assert
        $this->assertIsArray($devices);
        $this->assertEmpty($devices);
    }

    protected function createNewEntity(): object
    {
        // 创建完整的 BaseDevice 和 AutoJsDevice 实体
        $baseDevice = new BaseDevice();
        $baseDevice->setCode('TEST-DEVICE-' . uniqid());
        $baseDevice->setName('Test Device');
        $baseDevice->setModel('TestModel');
        $baseDevice->setDeviceType(DeviceType::PHONE);
        $baseDevice->setValid(true);
        $baseDevice->setStatus(DeviceStatus::ONLINE);

        $autoJsDevice = new AutoJsDevice();
        $autoJsDevice->setBaseDevice($baseDevice);
        $autoJsDevice->setAutoJsVersion('4.1.1');
        $autoJsDevice->setWsConnectionId('TEST-WS-' . uniqid());

        // Persist baseDevice to avoid cascade issues
        self::getEntityManager()->persist($baseDevice);

        return $autoJsDevice;
    }

    /**
     * @return ServiceEntityRepository<AutoJsDevice>
     */
    protected function getRepository(): ServiceEntityRepository
    {
        return $this->repository;
    }
}
