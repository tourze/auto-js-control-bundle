<?php

declare(strict_types=1);

namespace Tourze\AutoJsControlBundle\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tourze\AutoJsControlBundle\Exception\DeviceAuthException;
use Tourze\PHPUnitBase\AbstractExceptionTestCase;

/**
 * @internal
 */
#[CoversClass(DeviceAuthException::class)]
final class DeviceAuthExceptionTest extends AbstractExceptionTestCase
{
    #[Test]
    public function certificateGenerationFailedCreatesCorrectException(): void
    {
        // Arrange
        $deviceCode = 'DEVICE_001';
        $reason = 'Network connection failed';

        // Act
        $exception = DeviceAuthException::certificateGenerationFailed($deviceCode, $reason);

        // Assert
        $this->assertInstanceOf(DeviceAuthException::class, $exception);
        $this->assertEquals('设备证书生成失败 [DEVICE_001]: Network connection failed', $exception->getMessage());
    }

    #[Test]
    public function signatureVerificationFailedCreatesCorrectException(): void
    {
        // Arrange
        $deviceCode = 'DEVICE_002';

        // Act
        $exception = DeviceAuthException::signatureVerificationFailed($deviceCode);

        // Assert
        $this->assertInstanceOf(DeviceAuthException::class, $exception);
        $this->assertEquals('设备签名验证失败: DEVICE_002', $exception->getMessage());
    }

    #[Test]
    public function invalidCertificateCreatesCorrectException(): void
    {
        // Arrange
        $deviceCode = 'DEVICE_003';

        // Act
        $exception = DeviceAuthException::invalidCertificate($deviceCode);

        // Assert
        $this->assertInstanceOf(DeviceAuthException::class, $exception);
        $this->assertEquals('设备证书无效: DEVICE_003', $exception->getMessage());
    }

    #[Test]
    public function timestampExpiredCreatesCorrectException(): void
    {
        // Act
        $exception = DeviceAuthException::timestampExpired();

        // Assert
        $this->assertInstanceOf(DeviceAuthException::class, $exception);
        $this->assertEquals('请求时间戳已过期', $exception->getMessage());
    }
}
