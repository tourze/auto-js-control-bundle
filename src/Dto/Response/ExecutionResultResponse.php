<?php

namespace Tourze\AutoJsControlBundle\Dto\Response;

/**
 * 执行结果响应 DTO.
 */
final class ExecutionResultResponse implements \JsonSerializable
{
    private string $status;

    private string $instructionId;

    private ?string $message;

    private \DateTimeImmutable $serverTime;

    public function __construct(
        string $status,
        string $instructionId,
        ?string $message = null,
        ?\DateTimeImmutable $serverTime = null,
    ) {
        $this->status = $status;
        $this->instructionId = $instructionId;
        $this->message = $message;
        $this->serverTime = $serverTime ?? new \DateTimeImmutable();
    }

    /**
     * 创建成功响应.
     */
    public static function success(string $instructionId, string $message = '执行结果已记录'): self
    {
        return new self('ok', $instructionId, $message);
    }

    /**
     * 创建错误响应.
     */
    public static function error(string $instructionId, string $message): self
    {
        return new self('error', $instructionId, $message);
    }

    /**
     * 创建指令未找到响应.
     */
    public static function notFound(string $instructionId): self
    {
        return new self('not_found', $instructionId, '指令不存在或已过期');
    }

    /**
     * 创建重复上报响应.
     */
    public static function duplicate(string $instructionId): self
    {
        return new self('duplicate', $instructionId, '该指令的执行结果已经上报过');
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getInstructionId(): string
    {
        return $this->instructionId;
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
            'instructionId' => $this->instructionId,
            'serverTime' => $this->serverTime->format(\DateTimeInterface::RFC3339),
        ];

        if (null !== $this->message) {
            $data['message'] = $this->message;
        }

        return $data;
    }
}
