<?php

declare(strict_types=1);

namespace Tourze\AutoJsControlBundle\Tests\Repository;

use DeviceBundle\Entity\Device as BaseDevice;
use DeviceBundle\Enum\DeviceType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Test;
use Tourze\AutoJsControlBundle\Entity\AutoJsDevice;
use Tourze\AutoJsControlBundle\Entity\DeviceLog;
use Tourze\AutoJsControlBundle\Enum\LogLevel;
use Tourze\AutoJsControlBundle\Enum\LogType;
use Tourze\AutoJsControlBundle\Repository\DeviceLogRepository;
use Tourze\PHPUnitSymfonyKernelTest\AbstractRepositoryTestCase;

/**
 * @internal
 */
#[CoversClass(DeviceLogRepository::class)]
#[RunTestsInSeparateProcesses]
final class DeviceLogRepositoryTest extends AbstractRepositoryTestCase
{
    private DeviceLogRepository $repository;

    protected function onSetUp(): void
    {
        $repository = self::getEntityManager()->getRepository(DeviceLog::class);
        $this->assertInstanceOf(DeviceLogRepository::class, $repository);
        $this->repository = $repository;
    }

    #[Test]
    public function findByAutoJsDeviceReturnsCorrectLogs(): void
    {
        // Arrange
        $device = $this->createAutoJsDevice('LOG_DEVICE_001');

        $log1 = $this->createDeviceLog($device, 'Test log 1', LogLevel::INFO, LogType::SYSTEM);
        $log2 = $this->createDeviceLog($device, 'Test log 2', LogLevel::ERROR, LogType::SCRIPT);

        $otherDevice = $this->createAutoJsDevice('OTHER_DEVICE');
        $otherLog = $this->createDeviceLog($otherDevice, 'Other log', LogLevel::INFO, LogType::SYSTEM);

        $em = self::getEntityManager();
        $em->persist($log1);
        $em->persist($log2);
        $em->persist($otherLog);
        $em->flush();

        // Act
        $deviceId = $device->getId();
        $this->assertNotNull($deviceId);
        $logs = $this->repository->findByAutoJsDevice((string) $deviceId);

        // Assert
        $this->assertCount(2, $logs);
        $logContents = array_map(fn ($l) => $l->getContent(), $logs);
        $this->assertContains('Test log 1', $logContents);
        $this->assertContains('Test log 2', $logContents);
        $this->assertNotContains('Other log', $logContents);
    }

    #[Test]
    public function findByAutoJsDeviceFiltersCorrectly(): void
    {
        // Arrange
        $device = $this->createAutoJsDevice('FILTER_DEVICE');

        $infoLog = $this->createDeviceLog($device, 'Info message', LogLevel::INFO, LogType::SYSTEM);
        $errorLog = $this->createDeviceLog($device, 'Error message', LogLevel::ERROR, LogType::SCRIPT);

        $em = self::getEntityManager();
        $em->persist($infoLog);
        $em->persist($errorLog);
        $em->flush();

        // Act - filter by log level
        $deviceId = $device->getId();
        $this->assertNotNull($deviceId);
        $errorLogs = $this->repository->findByAutoJsDevice((string) $deviceId, ['logLevel' => LogLevel::ERROR]);
        $appLogs = $this->repository->findByAutoJsDevice((string) $deviceId, ['logType' => LogType::SCRIPT]);

        // Assert
        $this->assertCount(1, $errorLogs);
        $this->assertEquals('Error message', $errorLogs[0]->getContent());

        $this->assertCount(1, $appLogs);
        $this->assertEquals('Error message', $appLogs[0]->getContent());
    }

    #[Test]
    public function deleteOldLogsRemovesOldEntries(): void
    {
        // Arrange
        $device = $this->createAutoJsDevice('DELETE_TEST_DEVICE');

        $oldLog = $this->createDeviceLog($device, 'Old log', LogLevel::INFO, LogType::SYSTEM);
        $oldLog->setCreateTime(new \DateTimeImmutable('-30 days'));

        $newLog = $this->createDeviceLog($device, 'New log', LogLevel::INFO, LogType::SYSTEM);
        $newLog->setCreateTime(new \DateTimeImmutable('-1 day'));

        $em = self::getEntityManager();
        $em->persist($oldLog);
        $em->persist($newLog);
        $em->flush();

        // Act
        $threshold = new \DateTime('-7 days');
        $deletedCount = $this->repository->deleteOldLogs($threshold);

        // Assert
        $this->assertEquals(1, $deletedCount);

        $em->refresh($device);
        $deviceId = $device->getId();
        $this->assertNotNull($deviceId);
        $remainingLogs = $this->repository->findByAutoJsDevice((string) $deviceId);
        $this->assertCount(1, $remainingLogs);
        $this->assertEquals('New log', $remainingLogs[0]->getContent());
    }

    private function createAutoJsDevice(string $code): AutoJsDevice
    {
        $baseDevice = new BaseDevice();
        $baseDevice->setCode($code);
        $baseDevice->setName('Device ' . $code);
        $baseDevice->setModel('TestModel-' . $code);
        $baseDevice->setDeviceType(DeviceType::PHONE);

        $autoJsDevice = new AutoJsDevice();
        $autoJsDevice->setBaseDevice($baseDevice);

        $em = self::getEntityManager();
        $em->persist($baseDevice);
        $em->persist($autoJsDevice);
        $em->flush();

        return $autoJsDevice;
    }

    private function createDeviceLog(
        AutoJsDevice $device,
        string $content,
        LogLevel $level,
        LogType $type,
    ): DeviceLog {
        $log = new DeviceLog();
        $log->setAutoJsDevice($device);
        $log->setTitle($content);
        $log->setContent($content);
        $log->setLogLevel($level);
        $log->setLogType($type);
        $log->setCreateTime(new \DateTimeImmutable());

        return $log;
    }

    #[Test]
    public function testFindByAutoJsDeviceShouldReturnDeviceLogs(): void
    {
        // Arrange
        $device1 = $this->createAutoJsDevice('BY_DEVICE_1');
        $device2 = $this->createAutoJsDevice('BY_DEVICE_2');

        $log1 = $this->createDeviceLog($device1, 'Device 1 log', LogLevel::INFO, LogType::SYSTEM);
        $log2 = $this->createDeviceLog($device2, 'Device 2 log', LogLevel::INFO, LogType::SYSTEM);

        $em = self::getEntityManager();
        $em->persist($log1);
        $em->persist($log2);
        $em->flush();

        // Act
        $device1Id = $device1->getId();
        $this->assertNotNull($device1Id);
        $device1Logs = $this->repository->findByAutoJsDevice((string) $device1Id);

        // Assert
        $this->assertIsArray($device1Logs);
        $this->assertCount(1, $device1Logs);
        $this->assertEquals('Device 1 log', $device1Logs[0]->getContent());
    }

    #[Test]
    public function testDeleteOldLogsShouldRemoveOldEntries(): void
    {
        // Arrange
        $device = $this->createAutoJsDevice('DELETE_OLD_DEVICE');

        $oldLog = $this->createDeviceLog($device, 'Old log to delete', LogLevel::INFO, LogType::SYSTEM);
        $oldLog->setCreateTime(new \DateTimeImmutable('-60 days'));

        $recentLog = $this->createDeviceLog($device, 'Recent log to keep', LogLevel::INFO, LogType::SYSTEM);
        $recentLog->setCreateTime(new \DateTimeImmutable('-1 day'));

        $em = self::getEntityManager();
        $em->persist($oldLog);
        $em->persist($recentLog);
        $em->flush();

        // Act
        $threshold = new \DateTime('-30 days');
        $deletedCount = $this->repository->deleteOldLogs($threshold);

        // Assert
        $this->assertGreaterThanOrEqual(1, $deletedCount);

        // Verify only recent log remains
        $deviceId = $device->getId();
        $this->assertNotNull($deviceId);
        $remainingLogs = $this->repository->findByAutoJsDevice((string) $deviceId);
        $logContents = array_map(fn ($l) => $l->getContent(), $remainingLogs);
        $this->assertContains('Recent log to keep', $logContents);
        $this->assertNotContains('Old log to delete', $logContents);
    }

    #[Test]
    public function testSaveShouldPersistEntity(): void
    {
        // Arrange
        $device = $this->createAutoJsDevice('SAVE_DEVICE');
        $log = $this->createDeviceLog($device, 'Save test log', LogLevel::INFO, LogType::SYSTEM);

        // Act
        $this->repository->save($log);

        // Assert - Verify log is persisted with correct data
        $found = $this->repository->find($log->getId());
        $this->assertNotNull($found);
        $this->assertEquals('Save test log', $found->getContent());
        $this->assertEquals(LogLevel::INFO, $found->getLogLevel());
    }

    #[Test]
    public function testRemoveShouldDeleteEntity(): void
    {
        // Arrange
        $device = $this->createAutoJsDevice('REMOVE_DEVICE');
        $log = $this->createDeviceLog($device, 'Remove test log', LogLevel::INFO, LogType::SYSTEM);

        $em = self::getEntityManager();
        $em->persist($log);
        $em->flush();

        $logId = $log->getId();
        $this->assertNotNull($logId);

        // Act
        $this->repository->remove($log);

        // Assert
        $found = $this->repository->find($logId);
        $this->assertNull($found);
    }

    #[Test]
    public function testFindAssociationQueryShouldFindByAutoJsDevice(): void
    {
        // Arrange
        $device1 = $this->createAutoJsDevice('ASSOC_DEVICE_1');
        $device2 = $this->createAutoJsDevice('ASSOC_DEVICE_2');

        $log1 = $this->createDeviceLog($device1, 'Association log 1', LogLevel::INFO, LogType::SYSTEM);
        $log2 = $this->createDeviceLog($device1, 'Association log 2', LogLevel::ERROR, LogType::SCRIPT);
        $log3 = $this->createDeviceLog($device2, 'Association log 3', LogLevel::INFO, LogType::SYSTEM);

        $em = self::getEntityManager();
        $em->persist($log1);
        $em->persist($log2);
        $em->persist($log3);
        $em->flush();

        // Act
        $device1Logs = $this->repository->findBy(['autoJsDevice' => $device1]);

        // Assert
        $this->assertCount(2, $device1Logs);
        foreach ($device1Logs as $log) {
            $autoJsDevice = $log->getAutoJsDevice();
            $this->assertNotNull($autoJsDevice, 'AutoJsDevice should not be null');
            $this->assertEquals($device1->getId(), $autoJsDevice->getId());
        }
    }

    #[Test]
    public function testCountAssociationQueryShouldCountByAutoJsDevice(): void
    {
        // Arrange
        $device = $this->createAutoJsDevice('COUNT_ASSOC_DEVICE');
        $log1 = $this->createDeviceLog($device, 'Count association log 1', LogLevel::INFO, LogType::SYSTEM);
        $log2 = $this->createDeviceLog($device, 'Count association log 2', LogLevel::ERROR, LogType::SCRIPT);

        $em = self::getEntityManager();
        $em->persist($log1);
        $em->persist($log2);
        $em->flush();

        // Act
        $count = $this->repository->count(['autoJsDevice' => $device]);

        // Assert
        $this->assertEquals(2, $count);
    }

    #[Test]
    public function testFindByMultipleCriteriaShouldReturnFilteredResults(): void
    {
        // Arrange
        $device = $this->createAutoJsDevice('MULTIPLE_CRITERIA_DEVICE');

        $infoSystemLog = $this->createDeviceLog($device, 'Info system log', LogLevel::INFO, LogType::SYSTEM);
        $errorSystemLog = $this->createDeviceLog($device, 'Error system log', LogLevel::ERROR, LogType::SYSTEM);
        $infoScriptLog = $this->createDeviceLog($device, 'Info script log', LogLevel::INFO, LogType::SCRIPT);

        $em = self::getEntityManager();
        $em->persist($infoSystemLog);
        $em->persist($errorSystemLog);
        $em->persist($infoScriptLog);
        $em->flush();

        // Act - find by multiple criteria
        $infoSystemLogs = $this->repository->findBy([
            'logLevel' => LogLevel::INFO,
            'logType' => LogType::SYSTEM,
        ]);

        // Assert
        $this->assertIsArray($infoSystemLogs);
        $this->assertCount(1, $infoSystemLogs);
        $this->assertEquals('Info system log', $infoSystemLogs[0]->getContent());
    }

    #[Test]
    public function testCountByMultipleCriteriaShouldReturnCorrectCount(): void
    {
        // Arrange
        $device = $this->createAutoJsDevice('COUNT_MULTIPLE_DEVICE');

        $infoSystemLog1 = $this->createDeviceLog($device, 'Info system log 1', LogLevel::INFO, LogType::SYSTEM);
        $infoSystemLog2 = $this->createDeviceLog($device, 'Info system log 2', LogLevel::INFO, LogType::SYSTEM);
        $errorSystemLog = $this->createDeviceLog($device, 'Error system log', LogLevel::ERROR, LogType::SYSTEM);
        $infoScriptLog = $this->createDeviceLog($device, 'Info script log', LogLevel::INFO, LogType::SCRIPT);

        $em = self::getEntityManager();
        $em->persist($infoSystemLog1);
        $em->persist($infoSystemLog2);
        $em->persist($errorSystemLog);
        $em->persist($infoScriptLog);
        $em->flush();

        // Act - count by multiple criteria
        $count = $this->repository->count([
            'logLevel' => LogLevel::INFO,
            'logType' => LogType::SYSTEM,
        ]);

        // Assert
        $this->assertEquals(2, $count);
    }

    #[Test]
    public function testCountByAssociationAutoJsDeviceShouldReturnCorrectNumber(): void
    {
        // Arrange
        $device1 = $this->createAutoJsDevice('COUNT_ASSOC_DEVICE_1');
        $device2 = $this->createAutoJsDevice('COUNT_ASSOC_DEVICE_2');

        $log1 = $this->createDeviceLog($device1, 'Count association log 1', LogLevel::INFO, LogType::SYSTEM);
        $log2 = $this->createDeviceLog($device1, 'Count association log 2', LogLevel::ERROR, LogType::SCRIPT);
        $log3 = $this->createDeviceLog($device1, 'Count association log 3', LogLevel::WARNING, LogType::SYSTEM);
        $log4 = $this->createDeviceLog($device2, 'Other device log', LogLevel::INFO, LogType::SYSTEM);

        $em = self::getEntityManager();
        $em->persist($log1);
        $em->persist($log2);
        $em->persist($log3);
        $em->persist($log4);
        $em->flush();

        // Act
        $count = $this->repository->count(['autoJsDevice' => $device1]);

        // Assert
        $this->assertEquals(3, $count);
    }

    #[Test]
    public function testFindOneByAssociationAutoJsDeviceShouldReturnMatchingEntity(): void
    {
        // Arrange
        $device1 = $this->createAutoJsDevice('ASSOC_FIND_ONE_DEVICE_1');
        $device2 = $this->createAutoJsDevice('ASSOC_FIND_ONE_DEVICE_2');

        $log1 = $this->createDeviceLog($device1, 'Association find one log 1', LogLevel::INFO, LogType::SYSTEM);
        $log2 = $this->createDeviceLog($device2, 'Association find one log 2', LogLevel::INFO, LogType::SYSTEM);

        $em = self::getEntityManager();
        $em->persist($log1);
        $em->persist($log2);
        $em->flush();

        // Act
        $found = $this->repository->findOneBy(['autoJsDevice' => $device1]);

        // Assert
        $this->assertNotNull($found);
        $autoJsDevice = $found->getAutoJsDevice();
        $this->assertNotNull($autoJsDevice, 'AutoJsDevice should not be null');
        $this->assertEquals($device1->getId(), $autoJsDevice->getId());
        $this->assertEquals('Association find one log 1', $found->getContent());
    }

    protected function createNewEntity(): object
    {
        $baseDevice = new BaseDevice();
        $baseDevice->setCode('TEST-DEVICE-' . uniqid());
        $baseDevice->setName('Test Device');
        $baseDevice->setModel('TestModel');
        $baseDevice->setDeviceType(DeviceType::PHONE);

        $autoJsDevice = new AutoJsDevice();
        $autoJsDevice->setBaseDevice($baseDevice);
        $autoJsDevice->setAutoJsVersion('4.1.1');

        // Persist the device entities first
        $em = self::getEntityManager();
        $em->persist($baseDevice);
        $em->persist($autoJsDevice);
        $em->flush();

        $log = new DeviceLog();
        $log->setAutoJsDevice($autoJsDevice);
        $log->setTitle('Test Log');
        $log->setContent('Test log content');
        $log->setLogLevel(LogLevel::INFO);
        $log->setLogType(LogType::SYSTEM);
        $log->setCreateTime(new \DateTimeImmutable());

        return $log;
    }

    /**
     * @return ServiceEntityRepository<DeviceLog>
     */
    protected function getRepository(): ServiceEntityRepository
    {
        return $this->repository;
    }
}
