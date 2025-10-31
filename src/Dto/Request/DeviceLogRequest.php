<?php

declare(strict_types=1);

namespace Tourze\AutoJsControlBundle\Dto\Request;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * 设备日志上报请求 DTO.
 */
final class DeviceLogRequest
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

    /**
     * @var LogEntry[]
     */
    #[Assert\NotBlank(message: '日志条目不能为空')]
    #[Assert\Valid]
    private array $logs;

    /**
     * @param array<int, array{level: string, type: string, message: string, logTime: string, context?: string|null, stackTrace?: string|null}> $logs
     */
    public function __construct(
        string $deviceCode,
        string $signature,
        int $timestamp,
        array $logs,
    ) {
        $this->deviceCode = $deviceCode;
        $this->signature = $signature;
        $this->timestamp = $timestamp;
        $this->logs = array_map(fn ($log) => LogEntry::fromArray($log), $logs);
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

    /**
     * @return array<int, LogEntry>
     */
    public function getLogs(): array
    {
        return $this->logs;
    }

    /**
     * 验证签名.
     */
    public function verifySignature(string $certificate): bool
    {
        $data = sprintf(
            '%s:%d:%d:%s',
            $this->deviceCode,
            $this->timestamp,
            count($this->logs),
            $certificate
        );

        $expectedSignature = hash_hmac('sha256', $data, $certificate);

        return hash_equals($expectedSignature, $this->signature);
    }
}
