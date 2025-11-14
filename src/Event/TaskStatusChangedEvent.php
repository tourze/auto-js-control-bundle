<?php

declare(strict_types=1);

namespace Tourze\AutoJsControlBundle\Event;

use Symfony\Contracts\EventDispatcher\Event;
use Tourze\AutoJsControlBundle\Entity\Task;
use Tourze\AutoJsControlBundle\Enum\TaskStatus;

/**
 * 任务状态变更事件.
 *
 * 当任务的执行状态发生变化时触发此事件
 */
class TaskStatusChangedEvent extends Event
{
    public function __construct(
        private readonly Task $task,
        private readonly TaskStatus $previousStatus,
        private readonly TaskStatus $currentStatus,
        private readonly ?\DateTimeInterface $statusChangedTime = null,
        private readonly ?string $reason = null,
    ) {
    }

    /**
     * 获取状态变更的任务
     */
    public function getTask(): Task
    {
        return $this->task;
    }

    /**
     * 获取之前的状态
     */
    public function getPreviousStatus(): TaskStatus
    {
        return $this->previousStatus;
    }

    /**
     * 获取当前状态
     */
    public function getCurrentStatus(): TaskStatus
    {
        return $this->currentStatus;
    }

    /**
     * 获取状态变更时间.
     */
    public function getStatusChangedTime(): ?\DateTimeInterface
    {
        return $this->statusChangedTime;
    }

    /**
     * 获取状态变更时间（废弃方法）.
     *
     * @deprecated 使用 getStatusChangedTime() 代替
     */
    public function getStatusChangedAt(): ?\DateTimeInterface
    {
        return $this->statusChangedTime;
    }

    /**
     * 获取状态变更原因.
     */
    public function getReason(): ?string
    {
        return $this->reason;
    }

    /**
     * 判断任务是否开始执行.
     */
    public function hasStarted(): bool
    {
        return (TaskStatus::PENDING === $this->previousStatus || TaskStatus::SCHEDULED === $this->previousStatus)
               && TaskStatus::RUNNING === $this->currentStatus;
    }

    /**
     * 判断任务是否执行完成.
     */
    public function hasCompleted(): bool
    {
        return TaskStatus::COMPLETED === $this->currentStatus;
    }

    /**
     * 判断任务是否执行失败.
     */
    public function hasFailed(): bool
    {
        return TaskStatus::FAILED === $this->currentStatus;
    }

    /**
     * 判断任务是否被取消.
     */
    public function wasCancelled(): bool
    {
        return TaskStatus::CANCELLED === $this->currentStatus;
    }

    /**
     * 获取旧状态（别名方法）.
     */
    public function getOldStatus(): TaskStatus
    {
        return $this->previousStatus;
    }

    /**
     * 获取新状态（别名方法）.
     */
    public function getNewStatus(): TaskStatus
    {
        return $this->currentStatus;
    }

    /**
     * 获取任务ID.
     */
    public function getTaskId(): ?int
    {
        return $this->task->getId();
    }

    /**
     * 获取任务名称.
     */
    public function getTaskName(): ?string
    {
        return $this->task->getName();
    }

    /**
     * 判断任务是否开始执行.
     */
    public function isStarted(): bool
    {
        return $this->hasStarted();
    }

    /**
     * 判断任务是否执行完成.
     */
    public function isCompleted(): bool
    {
        return $this->hasCompleted();
    }

    /**
     * 判断任务是否执行失败.
     */
    public function isFailed(): bool
    {
        return $this->hasFailed();
    }

    /**
     * 判断任务是否被取消.
     */
    public function isCancelled(): bool
    {
        return $this->wasCancelled();
    }

    /**
     * 判断是否为终态
     */
    public function isTerminalStatus(): bool
    {
        return $this->currentStatus->isFinal();
    }

    /**
     * 获取状态变更描述.
     */
    public function getStatusChangeDescription(): string
    {
        return sprintf(
            '任务 "%s" (#%d) 状态从 %s 变更为 %s%s',
            $this->task->getName(),
            $this->task->getId(),
            $this->previousStatus->getLabel(),
            $this->currentStatus->getLabel(),
            null !== $this->reason ? " (原因: {$this->reason})" : ''
        );
    }

    /**
     * 转换为数组.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'taskId' => $this->task->getId(),
            'taskName' => $this->task->getName(),
            'taskType' => $this->task->getTaskType()->value,
            'oldStatus' => $this->previousStatus->value,
            'newStatus' => $this->currentStatus->value,
            'scriptId' => $this->task->getScript()?->getId(),
            'timestamp' => $this->statusChangedTime?->format('Y-m-d H:i:s') ?? new \DateTime()->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * 获取任务执行时长（秒）.
     */
    public function getDuration(): ?int
    {
        $startTime = $this->task->getStartTime();
        $endTime = $this->task->getEndTime();

        if (null === $startTime || null === $endTime) {
            return null;
        }

        return $endTime->getTimestamp() - $startTime->getTimestamp();
    }
}
