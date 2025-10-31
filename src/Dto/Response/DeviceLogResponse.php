<?php

namespace Tourze\AutoJsControlBundle\Dto\Response;

/**
 * 设备日志响应 DTO.
 */
final class DeviceLogResponse implements \JsonSerializable
{
    private string $status;

    private int $receivedCount;

    private int $savedCount;

    private ?string $message;

    private \DateTimeImmutable $serverTime;

    public function __construct(
        string $status,
        int $receivedCount,
        int $savedCount,
        ?string $message = null,
        ?\DateTimeImmutable $serverTime = null,
    ) {
        $this->status = $status;
        $this->receivedCount = $receivedCount;
        $this->savedCount = $savedCount;
        $this->message = $message;
        $this->serverTime = $serverTime ?? new \DateTimeImmutable();
    }

    /**
     * 创建成功响应.
     */
    public static function success(int $receivedCount, int $savedCount): self
    {
        $message = sprintf('成功接收%d条日志，保存%d条', $receivedCount, $savedCount);

        return new self('ok', $receivedCount, $savedCount, $message);
    }

    /**
     * 创建部分成功响应.
     */
    public static function partial(int $receivedCount, int $savedCount, string $reason): self
    {
        $message = sprintf('接收%d条日志，保存%d条，部分失败：%s', $receivedCount, $savedCount, $reason);

        return new self('partial', $receivedCount, $savedCount, $message);
    }

    /**
     * 创建错误响应.
     */
    public static function error(string $message): self
    {
        return new self('error', 0, 0, $message);
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getReceivedCount(): int
    {
        return $this->receivedCount;
    }

    public function getSavedCount(): int
    {
        return $this->savedCount;
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
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $data = [
            'status' => $this->status,
            'receivedCount' => $this->receivedCount,
            'savedCount' => $this->savedCount,
            'serverTime' => $this->serverTime->format(\DateTimeInterface::RFC3339),
        ];

        if (null !== $this->message) {
            $data['message'] = $this->message;
        }

        return $data;
    }
}
