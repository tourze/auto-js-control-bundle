<?php

declare(strict_types=1);

namespace Tourze\AutoJsControlBundle\Dto\Request;

use Symfony\Component\Validator\Constraints as Assert;
use Tourze\AutoJsControlBundle\Enum\LogLevel;
use Tourze\AutoJsControlBundle\Enum\LogType;

/**
 * 日志条目.
 */
final class LogEntry
{
    #[Assert\NotNull(message: '日志级别不能为空')]
    private LogLevel $level;

    #[Assert\NotNull(message: '日志类型不能为空')]
    private LogType $type;

    #[Assert\NotBlank(message: '日志内容不能为空')]
    #[Assert\Length(max: 5000, maxMessage: '日志内容长度不能超过5000个字符')]
    private string $message;

    #[Assert\NotNull(message: '日志时间不能为空')]
    private \DateTimeImmutable $logTime;

    private ?string $context = null;

    private ?string $stackTrace = null;

    public function __construct(
        LogLevel $level,
        LogType $type,
        string $message,
        \DateTimeImmutable $logTime,
        ?string $context = null,
        ?string $stackTrace = null,
    ) {
        $this->level = $level;
        $this->type = $type;
        $this->message = $message;
        $this->logTime = $logTime;
        $this->context = $context;
        $this->stackTrace = $stackTrace;
    }

    public function getLevel(): LogLevel
    {
        return $this->level;
    }

    public function getType(): LogType
    {
        return $this->type;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getLogTime(): \DateTimeImmutable
    {
        return $this->logTime;
    }

    public function getContext(): ?string
    {
        return $this->context;
    }

    public function getStackTrace(): ?string
    {
        return $this->stackTrace;
    }

    /**
     * 从数组创建实例.
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            LogLevel::from($data['level']),
            LogType::from($data['type']),
            $data['message'],
            new \DateTimeImmutable($data['logTime']),
            $data['context'] ?? null,
            $data['stackTrace'] ?? null
        );
    }
}
