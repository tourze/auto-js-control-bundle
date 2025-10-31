<?php

declare(strict_types=1);

namespace Tourze\AutoJsControlBundle\Tests\Entity;

use DeviceBundle\Entity\Device as BaseDevice;
use DeviceBundle\Enum\DeviceStatus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tourze\AutoJsControlBundle\Entity\AutoJsDevice;
use Tourze\AutoJsControlBundle\Entity\DeviceGroup;
use Tourze\AutoJsControlBundle\Entity\DeviceLog;
use Tourze\AutoJsControlBundle\Entity\DeviceMonitorData;
use Tourze\AutoJsControlBundle\Entity\ScriptExecutionRecord;
use Tourze\AutoJsControlBundle\Enum\ExecutionStatus;
use Tourze\PHPUnitDoctrineEntity\AbstractEntityTestCase;

/**
 * @internal
 */
#[CoversClass(AutoJsDevice::class)]
final class AutoJsDeviceTest extends AbstractEntityTestCase
{
    private AutoJsDevice $device;

    protected function createEntity(): object
    {
        return new AutoJsDevice();
    }

    protected function setUp(): void
    {
        $this->device = new AutoJsDevice();
    }

    #[Test]
    public function constructorSetsDefaultValues(): void
    {
        // Assert
        $this->assertNull($this->device->getId());
        $this->assertNull($this->device->getBaseDevice());
        $this->assertNull($this->device->getAutoJsVersion());
        $this->assertNull($this->device->getCertificate());
        $this->assertNull($this->device->getWsConnectionId());
        $this->assertNull($this->device->getDeviceGroup());
        $this->assertCount(0, $this->device->getDeviceLogs());
        $this->assertCount(0, $this->device->getScriptExecutionRecords());
        $this->assertCount(0, $this->device->getMonitorData());
        $this->assertCount(0, $this->device->getWebSocketMessages());
    }

    #[Test]
    public function setBaseDeviceSetsAndGetsCorrectly(): void
    {
        // Arrange
        $baseDevice = new BaseDevice();
        $baseDevice->setCode('TEST_DEVICE');

        // Act
        $this->device->setBaseDevice($baseDevice);

        // Assert
        $this->assertSame($baseDevice, $this->device->getBaseDevice());
    }

    #[Test]
    public function setAutoJsVersionSetsAndGetsCorrectly(): void
    {
        // Arrange
        $version = '9.0.0';

        // Act
        $this->device->setAutoJsVersion($version);

        // Assert
        $this->assertEquals($version, $this->device->getAutoJsVersion());
    }

    #[Test]
    public function setCertificateSetsAndGetsCorrectly(): void
    {
        // Arrange
        $certificate = 'test_certificate_hash_123456';

        // Act
        $this->device->setCertificate($certificate);

        // Assert
        $this->assertEquals($certificate, $this->device->getCertificate());
    }

    #[Test]
    public function setWsConnectionIdSetsAndGetsCorrectly(): void
    {
        // Arrange
        $wsConnectionId = 'ws-connection-123';

        // Act
        $this->device->setWsConnectionId($wsConnectionId);

        // Assert
        $this->assertEquals($wsConnectionId, $this->device->getWsConnectionId());
    }

    #[Test]
    public function setDeviceGroupSetsAndGetsCorrectly(): void
    {
        // Arrange
        $deviceGroup = new DeviceGroup();

        // Act
        $this->device->setDeviceGroup($deviceGroup);

        // Assert
        $this->assertSame($deviceGroup, $this->device->getDeviceGroup());
    }

    #[Test]
    public function addDeviceLogAddsToCollection(): void
    {
        // Arrange
        $log1 = new DeviceLog();
        $log1->setTitle('Log title 1');

        $log2 = new DeviceLog();
        $log2->setTitle('Log title 2');

        // Act
        $this->device->addDeviceLog($log1);
        $this->device->addDeviceLog($log2);

        // Assert
        $this->assertCount(2, $this->device->getDeviceLogs());
        $this->assertTrue($this->device->getDeviceLogs()->contains($log1));
        $this->assertTrue($this->device->getDeviceLogs()->contains($log2));
        $this->assertSame($this->device, $log1->getAutoJsDevice());
        $this->assertSame($this->device, $log2->getAutoJsDevice());
    }

    #[Test]
    public function removeDeviceLogRemovesFromCollection(): void
    {
        // Arrange
        $log1 = new DeviceLog();
        $log2 = new DeviceLog();

        $this->device->addDeviceLog($log1);
        $this->device->addDeviceLog($log2);

        // Act
        $this->device->removeDeviceLog($log1);

        // Assert
        $this->assertCount(1, $this->device->getDeviceLogs());
        $this->assertFalse($this->device->getDeviceLogs()->contains($log1));
        $this->assertTrue($this->device->getDeviceLogs()->contains($log2));
        $this->assertNull($log1->getAutoJsDevice());
        $this->assertSame($this->device, $log2->getAutoJsDevice());
    }

    #[Test]
    public function addScriptExecutionRecordAddsToCollection(): void
    {
        // Arrange
        $record1 = new ScriptExecutionRecord();
        $record1->setStatus(ExecutionStatus::PENDING);

        $record2 = new ScriptExecutionRecord();
        $record2->setStatus(ExecutionStatus::RUNNING);

        // Act
        $this->device->addScriptExecutionRecord($record1);
        $this->device->addScriptExecutionRecord($record2);

        // Assert
        $this->assertCount(2, $this->device->getScriptExecutionRecords());
        $this->assertTrue($this->device->getScriptExecutionRecords()->contains($record1));
        $this->assertTrue($this->device->getScriptExecutionRecords()->contains($record2));
        $this->assertSame($this->device, $record1->getAutoJsDevice());
        $this->assertSame($this->device, $record2->getAutoJsDevice());
    }

    #[Test]
    public function removeScriptExecutionRecordRemovesFromCollection(): void
    {
        // Arrange
        $record1 = new ScriptExecutionRecord();
        $record2 = new ScriptExecutionRecord();

        $this->device->addScriptExecutionRecord($record1);
        $this->device->addScriptExecutionRecord($record2);

        // Act
        $this->device->removeScriptExecutionRecord($record1);

        // Assert
        $this->assertCount(1, $this->device->getScriptExecutionRecords());
        $this->assertFalse($this->device->getScriptExecutionRecords()->contains($record1));
        $this->assertTrue($this->device->getScriptExecutionRecords()->contains($record2));
        $this->assertNull($record1->getAutoJsDevice());
        $this->assertSame($this->device, $record2->getAutoJsDevice());
    }

    #[Test]
    public function addMonitorDataAddsToCollection(): void
    {
        // Arrange
        $data1 = new DeviceMonitorData();
        $data1->setCpuUsage(50.5);

        $data2 = new DeviceMonitorData();
        $data2->setCpuUsage(75.2);

        // Act
        $this->device->addMonitorData($data1);
        $this->device->addMonitorData($data2);

        // Assert
        $this->assertCount(2, $this->device->getMonitorData());
        $this->assertTrue($this->device->getMonitorData()->contains($data1));
        $this->assertTrue($this->device->getMonitorData()->contains($data2));
        $this->assertSame($this->device, $data1->getAutoJsDevice());
        $this->assertSame($this->device, $data2->getAutoJsDevice());
    }

    #[Test]
    public function removeMonitorDataRemovesFromCollection(): void
    {
        // Arrange
        $data1 = new DeviceMonitorData();
        $data2 = new DeviceMonitorData();

        $this->device->addMonitorData($data1);
        $this->device->addMonitorData($data2);

        // Act
        $this->device->removeMonitorData($data1);

        // Assert
        $this->assertCount(1, $this->device->getMonitorData());
        $this->assertFalse($this->device->getMonitorData()->contains($data1));
        $this->assertTrue($this->device->getMonitorData()->contains($data2));
        $this->assertNull($data1->getAutoJsDevice());
        $this->assertSame($this->device, $data2->getAutoJsDevice());
    }

    #[Test]
    public function addDeviceLogPreventsDuplicates(): void
    {
        // Arrange
        $log = new DeviceLog();
        $log->setTitle('Test log');

        // Act
        $this->device->addDeviceLog($log);
        $this->device->addDeviceLog($log); // Add same log again

        // Assert
        $this->assertCount(1, $this->device->getDeviceLogs());
    }

    #[Test]
    public function addScriptExecutionRecordPreventsDuplicates(): void
    {
        // Arrange
        $record = new ScriptExecutionRecord();
        $record->setStatus(ExecutionStatus::SUCCESS);

        // Act
        $this->device->addScriptExecutionRecord($record);
        $this->device->addScriptExecutionRecord($record); // Add same record again

        // Assert
        $this->assertCount(1, $this->device->getScriptExecutionRecords());
    }

    #[Test]
    public function toStringReturnsDefaultWhenNoBaseDevice(): void
    {
        // Assert
        $this->assertEquals('Auto.js设备 #new', (string) $this->device);
    }

    #[Test]
    public function toStringReturnsFormattedStringWhenBaseDeviceSet(): void
    {
        // Arrange
        $baseDevice = new BaseDevice();
        $baseDevice->setCode('DEVICE_CODE_123');
        $baseDevice->setName('测试设备');
        $this->device->setBaseDevice($baseDevice);

        // Assert
        $this->assertEquals('Auto.js 测试设备 (DEVICE_CODE_123)', (string) $this->device);
    }

    #[Test]
    public function toStringHandlesNullDeviceName(): void
    {
        // Arrange
        $baseDevice = new BaseDevice();
        $baseDevice->setCode('DEVICE_CODE_123');
        $baseDevice->setName(null);
        $this->device->setBaseDevice($baseDevice);

        // Assert
        $this->assertEquals('Auto.js 未命名设备 (DEVICE_CODE_123)', (string) $this->device);
    }

    #[Test]
    public function getLockEntityIdReturnsStringId(): void
    {
        // Assert for new device
        $this->assertEquals('', $this->device->getLockEntityId());

        // Test with reflection to set ID
        $reflection = new \ReflectionClass($this->device);
        $property = $reflection->getProperty('id');
        $property->setAccessible(true);
        $property->setValue($this->device, 123);

        // Assert
        $this->assertEquals('123', $this->device->getLockEntityId());
    }

    #[Test]
    public function retrieveLockResourceReturnsCorrectFormat(): void
    {
        // Assert for new device
        $this->assertEquals('auto_js_device:new', $this->device->retrieveLockResource());

        // Test with reflection to set ID
        $reflection = new \ReflectionClass($this->device);
        $property = $reflection->getProperty('id');
        $property->setAccessible(true);
        $property->setValue($this->device, 456);

        // Assert
        $this->assertEquals('auto_js_device:456', $this->device->retrieveLockResource());
    }

    #[Test]
    public function convenienceMethodsReturnExpectedValues(): void
    {
        // Arrange
        $baseDevice = new BaseDevice();
        $baseDevice->setCode('TEST-001');
        $baseDevice->setName('Test Device');
        $baseDevice->setModel('Model X');
        $baseDevice->setBrand('Brand Y');
        $baseDevice->setOsVersion('Android 10');
        $baseDevice->setStatus(DeviceStatus::ONLINE);
        $baseDevice->setLastOnlineTime(new \DateTimeImmutable('2024-01-01 10:00:00'));
        $baseDevice->setLastIp('192.168.1.100');
        $baseDevice->setFingerprint('fingerprint123');
        $baseDevice->setCpuCores(8);
        $baseDevice->setMemorySize('8GB');
        $baseDevice->setStorageSize('128GB');
        $baseDevice->setRemark('Test remark');
        // Note: BaseDevice doesn't have setEnabled method

        $this->device->setBaseDevice($baseDevice);

        // Assert convenience methods
        $this->assertEquals('TEST-001', $this->device->getDeviceCode());
        $this->assertEquals('Test Device', $this->device->getDeviceName());
        $this->assertEquals('Model X', $this->device->getDeviceModel());
        $this->assertEquals('Brand Y', $this->device->getBrand());
        $this->assertEquals('Android 10', $this->device->getOsVersion());
        $this->assertEquals(DeviceStatus::ONLINE, $this->device->getStatus());
        $this->assertEquals(new \DateTimeImmutable('2024-01-01 10:00:00'), $this->device->getLastOnlineTime());
        $this->assertEquals('192.168.1.100', $this->device->getLastIp());
        $this->assertEquals('fingerprint123', $this->device->getFingerprint());
        $this->assertEquals(8, $this->device->getCpuCores());
        $this->assertEquals('8GB', $this->device->getMemorySize());
        $this->assertEquals('128GB', $this->device->getStorageSize());
        $this->assertEquals('Test remark', $this->device->getRemark());
        $this->assertTrue($this->device->isOnline());
        // Note: isEnabled depends on BaseDevice's enabled property
    }

    #[Test]
    public function convenienceMethodsReturnDefaultsWhenNoBaseDevice(): void
    {
        // Assert
        $this->assertEquals('', $this->device->getDeviceCode());
        $this->assertNull($this->device->getDeviceName());
        $this->assertNull($this->device->getDeviceModel());
        $this->assertNull($this->device->getDeviceType());
        $this->assertNull($this->device->getBrand());
        $this->assertNull($this->device->getOsVersion());
        $this->assertEquals(DeviceStatus::OFFLINE, $this->device->getStatus());
        $this->assertNull($this->device->getLastOnlineTime());
        $this->assertNull($this->device->getLastIp());
        $this->assertNull($this->device->getFingerprint());
        $this->assertEquals(0, $this->device->getCpuCores());
        $this->assertEquals('0', $this->device->getMemorySize());
        $this->assertEquals('0', $this->device->getStorageSize());
        $this->assertNull($this->device->getRemark());
        $this->assertFalse($this->device->isOnline());
        $this->assertFalse($this->device->isEnabled());
    }

    #[Test]
    public function timestampableTraitSetsTimestamps(): void
    {
        // Arrange
        $now = new \DateTimeImmutable();

        // Act - Using the actual method names from TimestampableAware trait
        $this->device->setCreateTime($now);
        $this->device->setUpdateTime($now);

        // Assert
        $this->assertSame($now, $this->device->getCreateTime());
        $this->assertSame($now, $this->device->getUpdateTime());
    }

    /**
     * 提供属性及其样本值的 Data Provider.
     *
     * @return iterable<string, array{string, mixed}>
     */
    public static function propertiesProvider(): iterable
    {
        yield 'autoJsVersion' => ['autoJsVersion', '9.0.0'];

        yield 'certificate' => ['certificate', 'test_certificate_hash_123456'];

        yield 'wsConnectionId' => ['wsConnectionId', 'ws-connection-123'];

        yield 'createTime' => ['createTime', new \DateTimeImmutable('2024-01-01 10:00:00')];

        yield 'updateTime' => ['updateTime', new \DateTimeImmutable('2024-01-01 10:00:00')];
    }
}
