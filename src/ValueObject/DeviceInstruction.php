<?php

declare(strict_types=1);

namespace Tourze\AutoJsControlBundle\ValueObject;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * 设备指令值对象
 */
final class DeviceInstruction
{
    public const TYPE_EXECUTE_SCRIPT = 'execute_script';
    public const TYPE_STOP_SCRIPT = 'stop_script';
    public const TYPE_UPDATE_STATUS = 'update_status';
    public const TYPE_COLLECT_LOG = 'collect_log';
    public const TYPE_RESTART_APP = 'restart_app';
    public const TYPE_UPDATE_APP = 'update_app';
    public const TYPE_PING = 'ping';

    #[Assert\NotBlank(message: '指令ID不能为空')]
    #[Assert\Length(max: 64, maxMessage: '指令ID长度不能超过64个字符')]
    private string $instructionId;

    #[Assert\NotBlank(message: '指令类型不能为空')]
    #[Assert\Choice(choices: [
        self::TYPE_EXECUTE_SCRIPT,
        self::TYPE_STOP_SCRIPT,
        self::TYPE_UPDATE_STATUS,
        self::TYPE_COLLECT_LOG,
        self::TYPE_RESTART_APP,
        self::TYPE_UPDATE_APP,
        self::TYPE_PING,
    ], message: '无效的指令类型')]
    private string $type;

    /**
     * @var array<string, mixed>
     */
    #[Assert\NotNull(message: '指令数据不能为空')]
    private array $data;

    #[Assert\NotNull(message: '创建时间不能为空')]
    private \DateTimeImmutable $createdTime;

    #[Assert\Range(min: 1, max: 3600, notInRangeMessage: '超时时间必须在1-3600秒之间')]
    private int $timeout;

    #[Assert\Range(min: 0, max: 10, notInRangeMessage: '优先级必须在0-10之间')]
    private int $priority;

    private ?int $taskId;

    private ?int $scriptId;

    private ?string $correlationId;

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        string $instructionId,
        string $type,
        array $data = [],
        int $timeout = 300,
        int $priority = 5,
        ?int $taskId = null,
        ?int $scriptId = null,
        ?string $correlationId = null,
    ) {
        $this->instructionId = $instructionId;
        $this->type = $type;
        $this->data = $data;
        $this->timeout = $timeout;
        $this->priority = $priority;
        $this->taskId = $taskId;
        $this->scriptId = $scriptId;
        $this->correlationId = $correlationId;
        $this->createdTime = new \DateTimeImmutable();
    }

    public function getInstructionId(): string
    {
        return $this->instructionId;
    }

    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        return $this->data;
    }

    public function getCreatedTime(): \DateTimeImmutable
    {
        return $this->createdTime;
    }

    /**
     * 获取创建时间（废弃方法）.
     *
     * @deprecated 使用 getCreatedTime() 代替
     */
    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdTime;
    }

    public function getTimeout(): int
    {
        return $this->timeout;
    }

    public function getPriority(): int
    {
        return $this->priority;
    }

    public function getTaskId(): ?int
    {
        return $this->taskId;
    }

    public function getScriptId(): ?int
    {
        return $this->scriptId;
    }

    public function getCorrelationId(): ?string
    {
        return $this->correlationId;
    }

    /**
     * 检查指令是否已过期
     */
    public function isExpired(): bool
    {
        $now = new \DateTimeImmutable();
        $expiryTime = $this->createdTime->modify(sprintf('+%d seconds', $this->timeout));

        return $now > $expiryTime;
    }

    /**
     * 转换为数组格式（用于序列化）.
     */
    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'instructionId' => $this->instructionId,
            'type' => $this->type,
            'data' => $this->data,
            'createdTime' => $this->createdTime->format(\DateTimeInterface::RFC3339),
            'timeout' => $this->timeout,
            'priority' => $this->priority,
            'taskId' => $this->taskId,
            'scriptId' => $this->scriptId,
            'correlationId' => $this->correlationId,
        ];
    }

    /**
     * 从数组创建实例.
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $instruction = new self(
            self::extractString($data, 'instructionId', ''),
            self::extractString($data, 'type', ''),
            self::extractArray($data, 'data'),
            self::extractInt($data, 'timeout', 300),
            self::extractInt($data, 'priority', 5),
            self::extractNullableInt($data, 'taskId'),
            self::extractNullableInt($data, 'scriptId'),
            self::extractNullableString($data, 'correlationId')
        );

        self::setCreatedTimeIfProvided($instruction, $data);

        return $instruction;
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
    private static function setCreatedTimeIfProvided(self $instruction, array $data): void
    {
        $createTime = $data['createTime'] ?? $data['createdTime'] ?? null;
        if (!is_string($createTime)) {
            return;
        }

        $reflection = new \ReflectionClass($instruction);
        $property = $reflection->getProperty('createdTime');
        $property->setAccessible(true);
        $property->setValue($instruction, new \DateTimeImmutable($createTime));
    }
}
