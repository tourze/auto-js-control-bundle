<?php

declare(strict_types=1);

namespace Tourze\AutoJsControlBundle\Tests\Entity;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tourze\AutoJsControlBundle\Entity\AutoJsDevice;
use Tourze\AutoJsControlBundle\Entity\DeviceGroup;
use Tourze\PHPUnitDoctrineEntity\AbstractEntityTestCase;

/**
 * @internal
 */
#[CoversClass(DeviceGroup::class)]
final class DeviceGroupTest extends AbstractEntityTestCase
{
    private DeviceGroup $deviceGroup;

    protected function createEntity(): object
    {
        return new DeviceGroup();
    }

    protected function setUp(): void
    {
        $this->deviceGroup = new DeviceGroup();
    }

    #[Test]
    public function constructorSetsDefaultValues(): void
    {
        // Assert
        $this->assertNull($this->deviceGroup->getId());
        $this->assertNull($this->deviceGroup->getName());
        $this->assertNull($this->deviceGroup->getDescription());
        $this->assertEquals(0, $this->deviceGroup->getSortOrder());
        $this->assertTrue($this->deviceGroup->isValid());
        $this->assertCount(0, $this->deviceGroup->getAutoJsDevices());
        $this->assertNull($this->deviceGroup->getCreateTime());
        $this->assertNull($this->deviceGroup->getUpdateTime());
    }

    #[Test]
    public function setNameSetsAndGetsCorrectly(): void
    {
        // Arrange
        $name = 'Production Devices';

        // Act
        $this->deviceGroup->setName($name);

        // Assert
        $this->assertEquals($name, $this->deviceGroup->getName());
    }

    #[Test]
    public function setDescriptionSetsAndGetsCorrectly(): void
    {
        // Arrange
        $description = 'This group contains all production devices';

        // Act
        $this->deviceGroup->setDescription($description);

        // Assert
        $this->assertEquals($description, $this->deviceGroup->getDescription());
    }

    #[Test]
    public function setSortOrderSetsAndGetsCorrectly(): void
    {
        // Arrange
        $sortOrder = 100;

        // Act
        $this->deviceGroup->setSortOrder($sortOrder);

        // Assert
        $this->assertEquals($sortOrder, $this->deviceGroup->getSortOrder());
    }

    #[Test]
    public function setValidSetsAndGetsCorrectly(): void
    {
        // Act & Assert - test enabling
        $this->deviceGroup->setValid(true);
        $this->assertTrue($this->deviceGroup->isValid());

        // Act & Assert - test disabling
        $this->deviceGroup->setValid(false);
        $this->assertFalse($this->deviceGroup->isValid());
    }

    #[Test]
    public function addDeviceAddsToCollection(): void
    {
        // Arrange
        $device1 = new AutoJsDevice();
        $device2 = new AutoJsDevice();

        // Act
        $this->deviceGroup->addAutoJsDevice($device1);
        $this->deviceGroup->addAutoJsDevice($device2);

        // Assert
        $this->assertCount(2, $this->deviceGroup->getAutoJsDevices());
        $this->assertTrue($this->deviceGroup->getAutoJsDevices()->contains($device1));
        $this->assertTrue($this->deviceGroup->getAutoJsDevices()->contains($device2));
        $this->assertSame($this->deviceGroup, $device1->getDeviceGroup());
        $this->assertSame($this->deviceGroup, $device2->getDeviceGroup());
    }

    #[Test]
    public function addDevicePreventsDuplicates(): void
    {
        // Arrange
        $device = new AutoJsDevice();

        // Act
        $this->deviceGroup->addAutoJsDevice($device);
        $this->deviceGroup->addAutoJsDevice($device); // Add same device again

        // Assert
        $this->assertCount(1, $this->deviceGroup->getAutoJsDevices());
    }

    #[Test]
    public function removeDeviceRemovesFromCollection(): void
    {
        // Arrange
        $device1 = new AutoJsDevice();
        $device2 = new AutoJsDevice();

        $this->deviceGroup->addAutoJsDevice($device1);
        $this->deviceGroup->addAutoJsDevice($device2);

        // Act
        $this->deviceGroup->removeAutoJsDevice($device1);

        // Assert
        $this->assertCount(1, $this->deviceGroup->getAutoJsDevices());
        $this->assertFalse($this->deviceGroup->getAutoJsDevices()->contains($device1));
        $this->assertTrue($this->deviceGroup->getAutoJsDevices()->contains($device2));
        $this->assertNull($device1->getDeviceGroup());
        $this->assertSame($this->deviceGroup, $device2->getDeviceGroup());
    }

    #[Test]
    public function toStringReturnsCorrectFormat(): void
    {
        // Test with no name
        $this->assertEquals('未命名分组', (string) $this->deviceGroup);

        // Test with name
        $this->deviceGroup->setName('测试组');
        $this->assertEquals('测试组', (string) $this->deviceGroup);
    }

    #[Test]
    public function timestampableTraitSetsTimestamps(): void
    {
        // Arrange
        $now = new \DateTimeImmutable();

        // Act
        $this->deviceGroup->setCreateTime($now);
        $this->deviceGroup->setUpdateTime($now);

        // Assert
        $this->assertSame($now, $this->deviceGroup->getCreateTime());
        $this->assertSame($now, $this->deviceGroup->getUpdateTime());
    }

    /**
     * 提供属性及其样本值的 Data Provider.
     *
     * @return iterable<string, array{string, mixed}>
     */
    public static function propertiesProvider(): iterable
    {
        yield 'name' => ['name', 'Production Devices'];

        yield 'description' => ['description', 'This group contains all production devices'];

        yield 'sortOrder' => ['sortOrder', 100];

        yield 'valid' => ['valid', false];

        yield 'createTime' => ['createTime', new \DateTimeImmutable('2024-01-01 10:00:00')];

        yield 'updateTime' => ['updateTime', new \DateTimeImmutable('2024-01-01 10:00:00')];
    }
}
