<?php

namespace Tourze\AutoJsControlBundle\Dto\Request;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * 设备心跳请求 DTO.
 */
final class DeviceHeartbeatRequest
{
    #[Assert\NotBlank(message: '设备代码不能为空')]
    #[Assert\Length(max: 64, maxMessage: '设备代码长度不能超过64个字符')]
    private string $deviceCode;

    #[Assert\NotBlank(message: '签名不能为空')]
    private string $signature;

    #[Assert\NotNull(message: '时间戳不能为空')]
    #[Assert\Range(
        min: 'now - 5 minutes',
        max: 'now + 5 minutes',
        notInRangeMessage: '时间戳无效，请同步系统时间'
    )]
    private int $timestamp;

    #[Assert\Length(max: 20, maxMessage: 'Auto.js版本长度不能超过20个字符')]
    private ?string $autoJsVersion = null;

    /**
     * @var array<string, mixed>
     */
    private array $deviceInfo = [];

    /**
     * @var array<string, mixed>
     */
    private array $monitorData = [];

    #[Assert\Range(min: 1, max: 60, notInRangeMessage: '轮询超时时间必须在1-60秒之间')]
    private int $pollTimeout = 30;

    /**
     * @param array<string, mixed> $deviceInfo
     * @param array<string, mixed> $monitorData
     */
    public function __construct(
        string $deviceCode,
        string $signature,
        int $timestamp,
        ?string $autoJsVersion = null,
        array $deviceInfo = [],
        array $monitorData = [],
        int $pollTimeout = 30,
    ) {
        $this->deviceCode = $deviceCode;
        $this->signature = $signature;
        $this->timestamp = $timestamp;
        $this->autoJsVersion = $autoJsVersion;
        $this->deviceInfo = $deviceInfo;
        $this->monitorData = $monitorData;
        $this->pollTimeout = $pollTimeout;
    }

    public function getDeviceCode(): string
    {
        return $this->deviceCode;
    }

    public function getSignature(): string
    {
        return $this->signature;
    }

    public function getTimestamp(): int
    {
        return $this->timestamp;
    }

    public function getAutoJsVersion(): ?string
    {
        return $this->autoJsVersion;
    }

    /**
     * @return array<string, mixed>
     */
    public function getDeviceInfo(): array
    {
        return $this->deviceInfo;
    }

    /**
     * @return array<string, mixed>
     */
    public function getMonitorData(): array
    {
        return $this->monitorData;
    }

    public function getPollTimeout(): int
    {
        return $this->pollTimeout;
    }

    /**
     * 验证签名.
     */
    public function verifySignature(string $certificate): bool
    {
        $data = sprintf(
            '%s:%d:%s',
            $this->deviceCode,
            $this->timestamp,
            $certificate
        );

        $expectedSignature = hash_hmac('sha256', $data, $certificate);

        return hash_equals($expectedSignature, $this->signature);
    }
}
