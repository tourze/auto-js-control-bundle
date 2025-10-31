<?php

declare(strict_types=1);

namespace Tourze\AutoJsControlBundle\Event;

use Symfony\Contracts\EventDispatcher\Event;
use Tourze\AutoJsControlBundle\Entity\Task;

/**
 * 任务创建事件.
 *
 * 当新任务被创建时触发此事件
 */
class TaskCreatedEvent extends Event
{
    public function __construct(
        private readonly Task $task,
        private readonly ?string $createdBy = null,
        /** @var array<string, mixed> */
        private readonly array $context = [],
    ) {
    }

    /**
     * 获取创建的任务实体.
     */
    public function getTask(): Task
    {
        return $this->task;
    }

    /**
     * 获取任务创建者.
     */
    public function getCreatedBy(): ?string
    {
        return $this->createdBy;
    }

    /**
     * 获取上下文信息.
     *
     * @return array<string, mixed>
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * 判断是否为立即执行任务
     */
    public function isImmediate(): bool
    {
        return null === $this->task->getScheduledTime()
               || $this->task->getScheduledTime() <= new \DateTime();
    }

    /**
     * 判断是否为计划任务
     */
    public function isScheduled(): bool
    {
        return null !== $this->task->getScheduledTime()
               && $this->task->getScheduledTime() > new \DateTime();
    }
}
