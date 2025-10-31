<?php

declare(strict_types=1);

namespace Tourze\AutoJsControlBundle\ValueObject;

/**
 * 指令执行上下文值对象
 */
final class InstructionExecutionContext
{
    private string $instructionId;

    private string $deviceCode;

    private string $instructionType;

    /**
     * @var array<string, mixed>
     */
    private array $parameters;

    private \DateTimeImmutable $scheduledTime;

    private int $priority;

    private int $retryCount;

    private int $maxRetries;

    private ?int $taskId;

    private ?int $scriptId;

    private ?string $userId;

    /**
     * @var array<string, mixed>
     */
    private array $metadata;

    /**
     * @param array<string, mixed> $parameters
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        string $instructionId,
        string $deviceCode,
        string $instructionType,
        array $parameters = [],
        ?\DateTimeImmutable $scheduledAt = null,
        int $priority = 5,
        int $retryCount = 0,
        int $maxRetries = 3,
        ?int $taskId = null,
        ?int $scriptId = null,
        ?string $userId = null,
        array $metadata = [],
    ) {
        $this->instructionId = $instructionId;
        $this->deviceCode = $deviceCode;
        $this->instructionType = $instructionType;
        $this->parameters = $parameters;
        $this->scheduledTime = $scheduledAt ?? new \DateTimeImmutable();
        $this->priority = $priority;
        $this->retryCount = $retryCount;
        $this->maxRetries = $maxRetries;
        $this->taskId = $taskId;
        $this->scriptId = $scriptId;
        $this->userId = $userId;
        $this->metadata = $metadata;
    }

    public function getInstructionId(): string
    {
        return $this->instructionId;
    }

    public function getDeviceCode(): string
    {
        return $this->deviceCode;
    }

    public function getInstructionType(): string
    {
        return $this->instructionType;
    }

    /**
     * @return array<string, mixed>
     */
    public function getParameters(): array
    {
        return $this->parameters;
    }

    public function getScheduledTime(): \DateTimeImmutable
    {
        return $this->scheduledTime;
    }

    /**
     * 获取计划时间（废弃方法）.
     *
     * @deprecated 使用 getScheduledTime() 代替
     */
    public function getScheduledAt(): \DateTimeImmutable
    {
        return $this->scheduledTime;
    }

    public function getPriority(): int
    {
        return $this->priority;
    }

    public function getRetryCount(): int
    {
        return $this->retryCount;
    }

    public function getMaxRetries(): int
    {
        return $this->maxRetries;
    }

    public function getTaskId(): ?int
    {
        return $this->taskId;
    }

    public function getScriptId(): ?int
    {
        return $this->scriptId;
    }

    public function getUserId(): ?string
    {
        return $this->userId;
    }

    /**
     * @return array<string, mixed>
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * 是否可以重试.
     */
    public function canRetry(): bool
    {
        return $this->retryCount < $this->maxRetries;
    }

    /**
     * 增加重试次数.
     */
    public function incrementRetryCount(): self
    {
        $clone = clone $this;
        ++$clone->retryCount;

        return $clone;
    }

    /**
     * 是否应该立即执行.
     */
    public function shouldExecuteNow(): bool
    {
        $now = new \DateTimeImmutable();

        return $this->scheduledTime <= $now;
    }

    /**
     * 获取延迟执行的秒数.
     */
    public function getDelaySeconds(): int
    {
        $now = new \DateTimeImmutable();
        $diff = $this->scheduledTime->getTimestamp() - $now->getTimestamp();

        return max(0, $diff);
    }

    /**
     * 转换为数组.
     */
    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'instructionId' => $this->instructionId,
            'deviceCode' => $this->deviceCode,
            'instructionType' => $this->instructionType,
            'parameters' => $this->parameters,
            'scheduledTime' => $this->scheduledTime->format(\DateTimeInterface::RFC3339),
            'priority' => $this->priority,
            'retryCount' => $this->retryCount,
            'maxRetries' => $this->maxRetries,
            'taskId' => $this->taskId,
            'scriptId' => $this->scriptId,
            'userId' => $this->userId,
            'metadata' => $this->metadata,
        ];
    }

    /**
     * 从数组创建实例.
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            self::extractString($data, 'instructionId', ''),
            self::extractString($data, 'deviceCode', ''),
            self::extractString($data, 'instructionType', ''),
            self::extractArray($data, 'parameters'),
            self::extractScheduledTime($data),
            self::extractInt($data, 'priority', 5),
            self::extractInt($data, 'retryCount', 0),
            self::extractInt($data, 'maxRetries', 3),
            self::extractNullableInt($data, 'taskId'),
            self::extractNullableInt($data, 'scriptId'),
            self::extractNullableString($data, 'userId'),
            self::extractArray($data, 'metadata')
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function extractString(array $data, string $key, string $default): string
    {
        return is_string($data[$key] ?? null) ? $data[$key] : $default;
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function extractInt(array $data, string $key, int $default): int
    {
        return is_int($data[$key] ?? null) ? $data[$key] : $default;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private static function extractArray(array $data, string $key): array
    {
        $value = $data[$key] ?? [];

        return is_array($value) ? $value : [];
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function extractNullableInt(array $data, string $key): ?int
    {
        return isset($data[$key]) && is_int($data[$key]) ? $data[$key] : null;
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function extractNullableString(array $data, string $key): ?string
    {
        return isset($data[$key]) && is_string($data[$key]) ? $data[$key] : null;
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function extractScheduledTime(array $data): \DateTimeImmutable
    {
        $scheduledTimeStr = $data['scheduledTime'] ?? $data['scheduledAt'] ?? 'now';
        if (!is_string($scheduledTimeStr)) {
            $scheduledTimeStr = 'now';
        }

        return new \DateTimeImmutable($scheduledTimeStr);
    }
}
