<?php

declare(strict_types=1);

namespace Tourze\AutoJsControlBundle\Tests\Entity;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tourze\AutoJsControlBundle\Entity\AutoJsDevice;
use Tourze\AutoJsControlBundle\Entity\DeviceMonitorData;
use Tourze\PHPUnitDoctrineEntity\AbstractEntityTestCase;

/**
 * @internal
 */
#[CoversClass(DeviceMonitorData::class)]
final class DeviceMonitorDataTest extends AbstractEntityTestCase
{
    private DeviceMonitorData $monitorData;

    protected function createEntity(): object
    {
        return new DeviceMonitorData();
    }

    protected function setUp(): void
    {
        $this->monitorData = new DeviceMonitorData();
    }

    #[Test]
    public function constructorSetsDefaultValues(): void
    {
        // Assert
        $this->assertNull($this->monitorData->getId());
        $this->assertNull($this->monitorData->getAutoJsDevice());
        $this->assertEquals(0, $this->monitorData->getCpuUsage());
        $this->assertEquals('0', $this->monitorData->getMemoryUsed());
        $this->assertEquals('0', $this->monitorData->getMemoryTotal());
        $this->assertEquals('0', $this->monitorData->getStorageUsed());
        $this->assertEquals('0', $this->monitorData->getStorageTotal());
        $this->assertEquals(0, $this->monitorData->getBatteryLevel());
        $this->assertFalse($this->monitorData->isCharging());
        $this->assertEquals(0, $this->monitorData->getTemperature());
        $this->assertEquals(0, $this->monitorData->getNetworkLatency());
        $this->assertNull($this->monitorData->getNetworkType());
        $this->assertEquals(0, $this->monitorData->getRunningScripts());
        $this->assertNull($this->monitorData->getExtraData());
        $this->assertNull($this->monitorData->getCreateTime());
    }

    #[Test]
    public function setAutoJsDeviceSetsAndGetsCorrectly(): void
    {
        // Arrange
        $device = new AutoJsDevice();

        // Act
        $this->monitorData->setAutoJsDevice($device);

        // Assert
        $this->assertSame($device, $this->monitorData->getAutoJsDevice());
    }

    #[Test]
    public function setCpuUsageSetsAndGetsCorrectly(): void
    {
        // Arrange
        $cpuUsage = 45.5;

        // Act
        $this->monitorData->setCpuUsage($cpuUsage);

        // Assert
        $this->assertEquals($cpuUsage, $this->monitorData->getCpuUsage());
    }

    #[Test]
    public function setMemoryUsedSetsAndGetsCorrectly(): void
    {
        // Arrange
        $memoryUsed = '1024';

        // Act
        $this->monitorData->setMemoryUsed($memoryUsed);

        // Assert
        $this->assertEquals($memoryUsed, $this->monitorData->getMemoryUsed());
    }

    #[Test]
    public function setMemoryTotalSetsAndGetsCorrectly(): void
    {
        // Arrange
        $memoryTotal = '4096';

        // Act
        $this->monitorData->setMemoryTotal($memoryTotal);

        // Assert
        $this->assertEquals($memoryTotal, $this->monitorData->getMemoryTotal());
    }

    #[Test]
    public function setBatteryLevelSetsAndGetsCorrectly(): void
    {
        // Arrange
        $batteryLevel = 85.0;

        // Act
        $this->monitorData->setBatteryLevel($batteryLevel);

        // Assert
        $this->assertEquals($batteryLevel, $this->monitorData->getBatteryLevel());
    }

    #[Test]
    public function setIsChargingSetsAndGetsCorrectly(): void
    {
        // Act & Assert - charging
        $this->monitorData->setIsCharging(true);
        $this->assertTrue($this->monitorData->isCharging());

        // Act & Assert - not charging
        $this->monitorData->setIsCharging(false);
        $this->assertFalse($this->monitorData->isCharging());
    }

    #[Test]
    public function setTemperatureSetsAndGetsCorrectly(): void
    {
        // Arrange
        $temperature = 36.5;

        // Act
        $this->monitorData->setTemperature($temperature);

        // Assert
        $this->assertEquals($temperature, $this->monitorData->getTemperature());
    }

    #[Test]
    public function setNetworkLatencySetsAndGetsCorrectly(): void
    {
        // Arrange
        $latency = 120;

        // Act
        $this->monitorData->setNetworkLatency($latency);

        // Assert
        $this->assertEquals($latency, $this->monitorData->getNetworkLatency());
    }

    #[Test]
    public function setRunningScriptsSetsAndGetsCorrectly(): void
    {
        // Arrange
        $runningScripts = 3;

        // Act
        $this->monitorData->setRunningScripts($runningScripts);

        // Assert
        $this->assertEquals($runningScripts, $this->monitorData->getRunningScripts());
    }

    #[Test]
    public function setNetworkTypeSetsAndGetsCorrectly(): void
    {
        // Arrange
        $networkType = 'WiFi';

        // Act
        $this->monitorData->setNetworkType($networkType);

        // Assert
        $this->assertEquals($networkType, $this->monitorData->getNetworkType());
    }

    #[Test]
    public function setStorageUsedSetsAndGetsCorrectly(): void
    {
        // Arrange
        $storageUsed = '15360';

        // Act
        $this->monitorData->setStorageUsed($storageUsed);

        // Assert
        $this->assertEquals($storageUsed, $this->monitorData->getStorageUsed());
    }

    #[Test]
    public function setStorageTotalSetsAndGetsCorrectly(): void
    {
        // Arrange
        $storageTotal = '65536';

        // Act
        $this->monitorData->setStorageTotal($storageTotal);

        // Assert
        $this->assertEquals($storageTotal, $this->monitorData->getStorageTotal());
    }

    #[Test]
    public function setExtraDataSetsAndGetsCorrectly(): void
    {
        // Arrange
        $extraData = json_encode(['key' => 'value', 'count' => 42]);
        $this->assertIsString($extraData);

        // Act
        $this->monitorData->setExtraData($extraData);

        // Assert
        $this->assertEquals($extraData, $this->monitorData->getExtraData());
    }

    #[Test]
    public function toStringReturnsCorrectFormat(): void
    {
        // Test with no create time
        $this->assertEquals('监控数据 new', (string) $this->monitorData);

        // Test with create time
        $createTime = new \DateTimeImmutable('2024-01-01 16:00:00');
        $this->monitorData->setCreateTime($createTime);

        $this->assertEquals('监控数据 2024-01-01 16:00:00', (string) $this->monitorData);
    }

    #[Test]
    public function timestampableTraitSetsTimestamps(): void
    {
        // Arrange
        $now = new \DateTimeImmutable();

        // Act
        $this->monitorData->setCreateTime($now);

        // Assert
        $this->assertSame($now, $this->monitorData->getCreateTime());
    }

    #[Test]
    public function setMonitorTimeAliasWorksCorrectly(): void
    {
        // Arrange
        $monitorTime = new \DateTimeImmutable('2024-01-01 12:00:00');

        // Act
        $this->monitorData->setMonitorTime($monitorTime);

        // Assert
        $this->assertEquals($monitorTime, $this->monitorData->getCreateTime());
    }

    #[Test]
    public function setMemoryUsageCompatibilityWorksCorrectly(): void
    {
        // Arrange
        $this->monitorData->setMemoryTotal('4096'); // 4GB

        // Act
        $this->monitorData->setMemoryUsage(75.0); // 75%

        // Assert
        $expectedUsed = (string) (int) (0.75 * 4096);
        $this->assertEquals($expectedUsed, $this->monitorData->getMemoryUsed());
    }

    #[Test]
    public function setAvailableStorageCompatibilityWorksCorrectly(): void
    {
        // Arrange
        $this->monitorData->setStorageTotal('65536'); // 64GB

        // Act
        $this->monitorData->setAvailableStorage(20480); // 20GB available

        // Assert
        $expectedUsed = (string) (65536 - 20480);
        $this->assertEquals($expectedUsed, $this->monitorData->getStorageUsed());
    }

    #[Test]
    public function setAdditionalDataCompatibilityWorksCorrectly(): void
    {
        // Arrange
        $additionalData = ['custom' => 'value', 'count' => 123];

        // Act
        $this->monitorData->setAdditionalData($additionalData);

        // Assert
        $this->assertEquals(json_encode($additionalData), $this->monitorData->getExtraData());

        // Test null value
        $this->monitorData->setAdditionalData(null);
        $this->assertNull($this->monitorData->getExtraData());
    }

    /**
     * 提供属性及其样本值的 Data Provider.
     *
     * @return iterable<string, array{string, mixed}>
     */
    public static function propertiesProvider(): iterable
    {
        yield 'cpuUsage' => ['cpuUsage', 45.5];

        yield 'memoryUsed' => ['memoryUsed', '1024'];

        yield 'memoryTotal' => ['memoryTotal', '4096'];

        yield 'storageUsed' => ['storageUsed', '15360'];

        yield 'storageTotal' => ['storageTotal', '65536'];

        yield 'batteryLevel' => ['batteryLevel', 85.0];

        // yield 'charging' => ['charging', true]; // 暂时注释，AbstractEntityTest 不支持 is 前缀的属性名

        yield 'temperature' => ['temperature', 36.5];

        yield 'networkLatency' => ['networkLatency', 120];

        yield 'networkType' => ['networkType', 'WiFi'];

        yield 'runningScripts' => ['runningScripts', 3];

        yield 'extraData' => ['extraData', json_encode(['key' => 'value', 'count' => 42])];

        yield 'createTime' => ['createTime', new \DateTimeImmutable('2024-01-01 16:00:00')];
    }
}
