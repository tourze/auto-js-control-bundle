<?php

declare(strict_types=1);

namespace Tourze\AutoJsControlBundle\ValueObject;

/**
 * 设备连接信息值对象
 */
final class DeviceConnectionInfo
{
    private string $deviceCode;

    private string $ipAddress;

    private ?string $userAgent;

    private \DateTimeImmutable $connectedTime;

    private ?string $connectionId;

    /**
     * @var array<string, string>
     */
    private array $headers;

    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        string $deviceCode,
        string $ipAddress,
        ?\DateTimeImmutable $connectedAt = null,
        ?string $userAgent = null,
        ?string $connectionId = null,
        array $headers = [],
    ) {
        $this->deviceCode = $deviceCode;
        $this->ipAddress = $ipAddress;
        $this->connectedTime = $connectedAt ?? new \DateTimeImmutable();
        $this->userAgent = $userAgent;
        $this->connectionId = $connectionId;
        $this->headers = $headers;
    }

    public function getDeviceCode(): string
    {
        return $this->deviceCode;
    }

    public function getIpAddress(): string
    {
        return $this->ipAddress;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function getConnectedTime(): \DateTimeImmutable
    {
        return $this->connectedTime;
    }

    /**
     * 获取连接时间（废弃方法）.
     *
     * @deprecated 使用 getConnectedTime() 代替
     */
    public function getConnectedAt(): \DateTimeImmutable
    {
        return $this->connectedTime;
    }

    public function getConnectionId(): ?string
    {
        return $this->connectionId;
    }

    /**
     * @return array<string, string>
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * 获取连接持续时间（秒）.
     */
    public function getConnectionDuration(): int
    {
        $now = new \DateTimeImmutable();

        return $now->getTimestamp() - $this->connectedTime->getTimestamp();
    }

    /**
     * 检查是否来自同一IP.
     */
    public function isSameIp(string $ipAddress): bool
    {
        return $this->ipAddress === $ipAddress;
    }

    /**
     * 转换为数组（用于Redis存储）.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'deviceCode' => $this->deviceCode,
            'ipAddress' => $this->ipAddress,
            'userAgent' => $this->userAgent,
            'connectedTime' => $this->connectedTime->format(\DateTimeInterface::RFC3339),
            'connectionId' => $this->connectionId,
            'headers' => $this->headers,
        ];
    }

    /**
     * 从数组创建实例.
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $deviceCode = is_string($data['deviceCode'] ?? null) ? $data['deviceCode'] : '';
        $ipAddress = is_string($data['ipAddress'] ?? null) ? $data['ipAddress'] : '';

        $connectedTimeStr = is_string($data['connectedTime'] ?? null) ? $data['connectedTime'] : ($data['connectedAt'] ?? 'now');
        if (!is_string($connectedTimeStr)) {
            $connectedTimeStr = 'now';
        }
        $connectedTime = new \DateTimeImmutable($connectedTimeStr);

        $userAgent = isset($data['userAgent']) && is_string($data['userAgent']) ? $data['userAgent'] : null;
        $connectionId = isset($data['connectionId']) && is_string($data['connectionId']) ? $data['connectionId'] : null;
        $headers = is_array($data['headers'] ?? null) ? $data['headers'] : [];
        /** @var array<string, string> $typedHeaders */
        $typedHeaders = $headers;

        return new self(
            $deviceCode,
            $ipAddress,
            $connectedTime,
            $userAgent,
            $connectionId,
            $typedHeaders
        );
    }
}
