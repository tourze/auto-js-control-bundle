<?php

namespace Tourze\AutoJsControlBundle\Dto\Response;

/**
 * 设备注册响应 DTO.
 */
final class DeviceRegisterResponse implements \JsonSerializable
{
    private string $status;

    private ?string $deviceId;

    private ?string $certificate;

    private ?string $message;

    private \DateTimeImmutable $serverTime;

    /**
     * @var array<string, mixed>|null
     */
    private ?array $config;

    /**
     * @param array<string, mixed>|null $config
     */
    public function __construct(
        string $status,
        ?string $deviceId = null,
        ?string $certificate = null,
        ?string $message = null,
        ?\DateTimeImmutable $serverTime = null,
        ?array $config = null,
    ) {
        $this->status = $status;
        $this->deviceId = $deviceId;
        $this->certificate = $certificate;
        $this->message = $message;
        $this->serverTime = $serverTime ?? new \DateTimeImmutable();
        $this->config = $config;
    }

    /**
     * 创建成功响应.
     *
     * @param array<string, mixed>|null $config
     */
    public static function success(string $deviceId, string $certificate, ?array $config = null): self
    {
        return new self('ok', $deviceId, $certificate, '设备注册成功', null, $config);
    }

    /**
     * 创建设备已存在响应.
     */
    public static function exists(string $deviceId, string $message = '设备已注册'): self
    {
        return new self('exists', $deviceId, null, $message);
    }

    /**
     * 创建错误响应.
     */
    public static function error(string $message): self
    {
        return new self('error', null, null, $message);
    }

    /**
     * 创建无效请求响应.
     */
    public static function invalid(string $message): self
    {
        return new self('invalid', null, null, $message);
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getDeviceId(): ?string
    {
        return $this->deviceId;
    }

    public function getCertificate(): ?string
    {
        return $this->certificate;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function getServerTime(): \DateTimeImmutable
    {
        return $this->serverTime;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getConfig(): ?array
    {
        return $this->config;
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $data = [
            'status' => $this->status,
            'serverTime' => $this->serverTime->format(\DateTimeInterface::RFC3339),
        ];

        if (null !== $this->deviceId) {
            $data['deviceId'] = $this->deviceId;
        }

        if (null !== $this->certificate) {
            $data['certificate'] = $this->certificate;
        }

        if (null !== $this->message) {
            $data['message'] = $this->message;
        }

        if (null !== $this->config) {
            $data['config'] = $this->config;
        }

        return $data;
    }
}
