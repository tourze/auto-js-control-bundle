<?php

declare(strict_types=1);

namespace Tourze\AutoJsControlBundle\Event;

use Symfony\Contracts\EventDispatcher\Event;
use Tourze\AutoJsControlBundle\Entity\AutoJsDevice;
use Tourze\AutoJsControlBundle\Entity\Script;
use Tourze\AutoJsControlBundle\Entity\ScriptExecutionRecord;
use Tourze\AutoJsControlBundle\Entity\Task;

/**
 * 脚本执行事件.
 *
 * 当脚本在设备上开始执行或执行完成时触发此事件
 */
class ScriptExecutedEvent extends Event
{
    public function __construct(
        private readonly Script $script,
        private readonly AutoJsDevice $device,
        private readonly ?Task $task = null,
        private readonly ?ScriptExecutionRecord $executionRecord = null,
        private readonly bool $isStarted = true,
        /** @var array<string, mixed> */
        private readonly array $executionResult = [],
    ) {
    }

    /**
     * 获取执行的脚本.
     */
    public function getScript(): Script
    {
        return $this->script;
    }

    /**
     * 获取执行脚本的设备.
     */
    public function getDevice(): AutoJsDevice
    {
        return $this->device;
    }

    /**
     * 获取关联的任务（如果有）.
     */
    public function getTask(): ?Task
    {
        return $this->task;
    }

    /**
     * 获取执行记录.
     */
    public function getExecutionRecord(): ?ScriptExecutionRecord
    {
        return $this->executionRecord;
    }

    /**
     * 判断是否为执行开始事件.
     */
    public function isStarted(): bool
    {
        return $this->isStarted;
    }

    /**
     * 判断是否为执行完成事件.
     */
    public function isCompleted(): bool
    {
        return !$this->isStarted;
    }

    /**
     * 获取执行结果（仅在执行完成时有效）.
     *
     * @return array<string, mixed>
     */
    public function getExecutionResult(): array
    {
        return $this->executionResult;
    }

    /**
     * 判断执行是否成功（仅在执行完成时有效）.
     */
    public function isSuccess(): bool
    {
        return !$this->isStarted && (bool) ($this->executionResult['success'] ?? false);
    }

    /**
     * 获取错误信息（如果有）.
     */
    public function getErrorMessage(): ?string
    {
        return $this->executionResult['error'] ?? null;
    }
}
