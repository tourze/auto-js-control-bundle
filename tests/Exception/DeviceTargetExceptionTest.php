<?php

declare(strict_types=1);

namespace Tourze\AutoJsControlBundle\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\AutoJsControlBundle\Exception\DeviceTargetException;
use Tourze\PHPUnitBase\AbstractExceptionTestCase;

/**
 * @internal
 */
#[CoversClass(DeviceTargetException::class)]
final class DeviceTargetExceptionTest extends AbstractExceptionTestCase
{
    public function testTargetDevicesRequired(): void
    {
        $exception = DeviceTargetException::targetDevicesRequired();

        $this->assertInstanceOf(DeviceTargetException::class, $exception);
        $this->assertInstanceOf(\InvalidArgumentException::class, $exception);
        $this->assertSame('必须指定目标设备列表', $exception->getMessage());
        $this->assertSame(0, $exception->getCode());
    }

    public function testTargetGroupRequired(): void
    {
        $exception = DeviceTargetException::targetGroupRequired();

        $this->assertInstanceOf(DeviceTargetException::class, $exception);
        $this->assertSame('必须指定目标设备组', $exception->getMessage());
        $this->assertSame(0, $exception->getCode());
    }

    public function testGroupNotFound(): void
    {
        $exception = DeviceTargetException::groupNotFound();

        $this->assertInstanceOf(DeviceTargetException::class, $exception);
        $this->assertSame('设备组不存在', $exception->getMessage());
        $this->assertSame(0, $exception->getCode());
    }

    public function testTargetDeviceRequired(): void
    {
        $exception = DeviceTargetException::targetDeviceRequired();

        $this->assertInstanceOf(DeviceTargetException::class, $exception);
        $this->assertSame('必须指定目标设备：--device-ids, --group-id 或 --all-devices', $exception->getMessage());
        $this->assertSame(0, $exception->getCode());
    }

    public function testTargetDeviceOptionsExclusive(): void
    {
        $exception = DeviceTargetException::targetDeviceOptionsExclusive();

        $this->assertInstanceOf(DeviceTargetException::class, $exception);
        $this->assertSame('目标设备选项只能选择一个', $exception->getMessage());
        $this->assertSame(0, $exception->getCode());
    }

    public function testConstructorWithCustomValues(): void
    {
        $message = 'Custom error message';
        $code = 123;
        $previous = new \Exception('Previous exception');

        $exception = new DeviceTargetException($message, $code, $previous);

        $this->assertSame($message, $exception->getMessage());
        $this->assertSame($code, $exception->getCode());
        $this->assertSame($previous, $exception->getPrevious());
    }

    public function testConstructorWithDefaults(): void
    {
        $exception = new DeviceTargetException();

        $this->assertSame('', $exception->getMessage());
        $this->assertSame(0, $exception->getCode());
        $this->assertNull($exception->getPrevious());
    }
}
