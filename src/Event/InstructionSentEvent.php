<?php

declare(strict_types=1);

namespace Tourze\AutoJsControlBundle\Event;

use Symfony\Contracts\EventDispatcher\Event;
use Tourze\AutoJsControlBundle\Entity\AutoJsDevice;
use Tourze\AutoJsControlBundle\ValueObject\DeviceInstruction;

/**
 * 指令发送事件.
 *
 * 当向设备发送指令时触发此事件
 */
class InstructionSentEvent extends Event
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        private readonly DeviceInstruction $instruction,
        private readonly AutoJsDevice $device,
        private readonly bool $success = true,
        private readonly ?string $errorMessage = null,
        private readonly array $metadata = [],
    ) {
    }

    /**
     * 获取发送的指令.
     */
    public function getInstruction(): DeviceInstruction
    {
        return $this->instruction;
    }

    /**
     * 获取目标设备.
     */
    public function getDevice(): AutoJsDevice
    {
        return $this->device;
    }

    /**
     * 判断指令是否发送成功
     */
    public function isSuccess(): bool
    {
        return $this->success;
    }

    /**
     * 获取错误信息（如果发送失败）.
     */
    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    /**
     * 获取元数据.
     *
     * @return array<string, mixed>
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * 获取指令类型.
     */
    public function getInstructionType(): string
    {
        return $this->instruction->getType();
    }

    /**
     * 获取指令内容.
     *
     * @return array<string, mixed>
     */
    public function getInstructionContent(): array
    {
        return $this->instruction->getData();
    }

    /**
     * 判断是否为高优先级指令.
     */
    public function isHighPriority(): bool
    {
        return $this->instruction->getPriority() > 5;
    }
}
