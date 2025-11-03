<?php

namespace Tourze\AutoJsControlBundle\Dto\Request;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * 设备注册请求 DTO.
 */
final class DeviceRegisterRequest
{
    #[Assert\NotBlank(message: '设备代码不能为空')]
    #[Assert\Length(max: 64, maxMessage: '设备代码长度不能超过64个字符')]
    #[Assert\Regex(pattern: '/^[a-zA-Z0-9\-_]+$/', message: '设备代码只能包含字母、数字、横线和下划线')]
    private string $deviceCode;

    #[Assert\NotBlank(message: '设备名称不能为空')]
    #[Assert\Length(max: 100, maxMessage: '设备名称长度不能超过100个字符')]
    private string $deviceName;

    #[Assert\Length(max: 50, maxMessage: '设备型号长度不能超过50个字符')]
    private ?string $model = null;

    #[Assert\Length(max: 50, maxMessage: '品牌长度不能超过50个字符')]
    private ?string $brand = null;

    #[Assert\Length(max: 50, maxMessage: '操作系统版本长度不能超过50个字符')]
    private ?string $osVersion = null;

    #[Assert\Length(max: 20, maxMessage: 'Auto.js版本长度不能超过20个字符')]
    private ?string $autoJsVersion = null;

    #[Assert\Length(max: 128, maxMessage: '设备指纹长度不能超过128个字符')]
    private ?string $fingerprint = null;

    /**
     * @var array<string, mixed>
     */
    private array $hardwareInfo = [];

    #[Assert\NotBlank(message: '证书请求不能为空')]
    private string $certificateRequest;

    /**
     * @param array<string, mixed> $hardwareInfo
     */
    public function __construct(
        string $deviceCode,
        string $deviceName,
        string $certificateRequest,
        ?string $model = null,
        ?string $brand = null,
        ?string $osVersion = null,
        ?string $autoJsVersion = null,
        ?string $fingerprint = null,
        array $hardwareInfo = [],
    ) {
        $this->deviceCode = $deviceCode;
        $this->deviceName = $deviceName;
        $this->certificateRequest = $certificateRequest;
        $this->model = $model;
        $this->brand = $brand;
        $this->osVersion = $osVersion;
        $this->autoJsVersion = $autoJsVersion;
        $this->fingerprint = $fingerprint;
        $this->hardwareInfo = $hardwareInfo;
    }

    public function getDeviceCode(): string
    {
        return $this->deviceCode;
    }

    public function getDeviceName(): string
    {
        return $this->deviceName;
    }

    public function getModel(): ?string
    {
        return $this->model;
    }

    public function getBrand(): ?string
    {
        return $this->brand;
    }

    public function getOsVersion(): ?string
    {
        return $this->osVersion;
    }

    public function getAutoJsVersion(): ?string
    {
        return $this->autoJsVersion;
    }

    public function getFingerprint(): ?string
    {
        return $this->fingerprint;
    }

    /**
     * @return array<string, mixed>
     */
    public function getHardwareInfo(): array
    {
        return $this->hardwareInfo;
    }

    public function getCertificateRequest(): string
    {
        return $this->certificateRequest;
    }

    /**
     * 获取CPU核心数.
     */
    public function getCpuCores(): int
    {
        $value = $this->hardwareInfo['cpuCores'] ?? 0;
        if (is_int($value)) {
            return $value;
        }
        if (is_numeric($value)) {
            return (int) $value;
        }
        return 0;
    }

    /**
     * 获取内存大小（MB）.
     */
    public function getMemorySize(): int
    {
        $value = $this->hardwareInfo['memorySize'] ?? 0;
        if (is_int($value)) {
            return $value;
        }
        if (is_numeric($value)) {
            return (int) $value;
        }
        return 0;
    }

    /**
     * 获取存储空间大小（MB）.
     */
    public function getStorageSize(): int
    {
        $value = $this->hardwareInfo['storageSize'] ?? 0;
        if (is_int($value)) {
            return $value;
        }
        if (is_numeric($value)) {
            return (int) $value;
        }
        return 0;
    }

    /**
     * 获取屏幕分辨率.
     */
    public function getScreenResolution(): ?string
    {
        $value = $this->hardwareInfo['screenResolution'] ?? null;
        return is_string($value) ? $value : null;
    }
}
