<?php

namespace Tourze\AutoJsControlBundle\Service;

use Cron\CronExpression;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Tourze\AutoJsControlBundle\Entity\Task;
use Tourze\AutoJsControlBundle\Enum\TaskStatus;
use Tourze\AutoJsControlBundle\Enum\TaskType;
use Tourze\AutoJsControlBundle\Exception\InvalidTaskArgumentException;
use Tourze\AutoJsControlBundle\Exception\TaskException;
use Tourze\AutoJsControlBundle\Repository\TaskRepository;
use Tourze\LockServiceBundle\Service\LockService;

/**
 * 任务调度服务
 *
 * 负责任务的协调和高级调度管理
 */
readonly class TaskScheduler
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private TaskRepository $taskRepository,
        private TaskCreationService $taskCreationService,
        private TaskDispatcher $taskDispatcher,
        private LoggerInterface $logger,
        private LockService $lockService,
    ) {
    }

    /**
     * 创建并调度任务
     *
     * @param array<string, mixed> $taskData 任务数据
     *
     * @return Task 创建的任务
     */
    public function createAndScheduleTask(array $taskData): Task
    {
        try {
            $task = $this->taskCreationService->createTask($taskData);

            // 如果是立即执行的任务，直接调度
            if (TaskType::IMMEDIATE === $task->getTaskType()) {
                $this->dispatchTask($task);
            }

            return $task;
        } catch (\Exception $e) {
            $this->logger->error('创建并调度任务失败', [
                'error' => $e->getMessage(),
                'taskData' => $taskData,
                'exception' => $e,
            ]);

            throw new TaskException('创建任务失败: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * 分发任务到设备.
     *
     * @param Task $task 任务实体
     */
    public function dispatchTask(Task $task): void
    {
        $lockKey = sprintf('task_dispatch:%s', $task->getId());

        $this->lockService->blockingRun($lockKey, function () use ($task): void {
            $this->taskDispatcher->dispatchTask($task);
        });
    }

    /**
     * 更新任务执行进度.
     *
     * @param int    $taskId        任务ID
     * @param string $instructionId 指令ID
     * @param string $status        执行状态
     */
    public function updateTaskProgress(int $taskId, string $instructionId, string $status): void
    {
        $task = $this->taskRepository->find($taskId);

        if (null === $task) {
            return;
        }

        $this->taskDispatcher->updateTaskProgress($task, $instructionId, $status);
    }

    /**
     * 执行计划任务
     *
     * 此方法应该由定时任务调用
     */
    public function executeScheduledTasks(): int
    {
        $executedCount = 0;
        try {
            // 查找需要执行的计划任务
            $now = new \DateTimeImmutable();
            $tasks = $this->taskRepository->findScheduledTasksToExecute($now);

            $this->logger->info('开始执行计划任务', [
                'taskCount' => count($tasks),
            ]);

            foreach ($tasks as $task) {
                try {
                    $this->dispatchTask($task);
                    ++$executedCount;
                } catch (\Exception $e) {
                    $this->logger->error('执行计划任务失败', [
                        'taskId' => $task->getId(),
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        } catch (\Exception $e) {
            $this->logger->error('执行计划任务批处理失败', [
                'error' => $e->getMessage(),
                'exception' => $e,
            ]);
        }

        return $executedCount;
    }

    /**
     * 执行循环任务
     *
     * 此方法应该由定时任务调用
     */
    public function executeRecurringTasks(): void
    {
        try {
            // 查找所有激活的循环任务
            $tasks = $this->taskRepository->findActiveRecurringTasks();

            $this->logger->info('检查循环任务', [
                'taskCount' => count($tasks),
            ]);

            $now = new \DateTimeImmutable();

            foreach ($tasks as $task) {
                try {
                    if ($this->shouldExecuteRecurringTask($task, $now)) {
                        $this->dispatchTask($task);

                        // 更新下次执行时间
                        $this->updateNextExecutionTime($task);
                    }
                } catch (\Exception $e) {
                    $this->logger->error('执行循环任务失败', [
                        'taskId' => $task->getId(),
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        } catch (\Exception $e) {
            $this->logger->error('执行循环任务批处理失败', [
                'error' => $e->getMessage(),
                'exception' => $e,
            ]);
        }
    }

    /**
     * 判断循环任务是否应该执行.
     */
    private function shouldExecuteRecurringTask(Task $task, \DateTimeImmutable $now): bool
    {
        $cronExpression = $task->getCronExpression();
        if (null === $cronExpression || '' === $cronExpression) {
            return false;
        }

        try {
            $cron = new CronExpression($cronExpression);

            return $this->isCronTaskDue($cron, $task, $now);
        } catch (\Exception $e) {
            $this->logCronParsingError($task, $e);

            return false;
        }
    }

    /**
     * 检查Cron任务是否到期
     */
    private function isCronTaskDue(CronExpression $cron, Task $task, \DateTimeImmutable $now): bool
    {
        $lastExecutionTime = $task->getLastExecutionTime();
        if (null !== $lastExecutionTime) {
            $nextRunTime = $cron->getNextRunDate($lastExecutionTime);

            return $now >= $nextRunTime;
        }

        return $cron->isDue($now);
    }

    /**
     * 记录Cron解析错误.
     */
    private function logCronParsingError(Task $task, \Exception $e): void
    {
        $this->logger->error('解析Cron表达式失败', [
            'taskId' => $task->getId(),
            'cronExpression' => $task->getCronExpression(),
            'error' => $e->getMessage(),
        ]);
    }

    /**
     * 更新下次执行时间.
     */
    private function updateNextExecutionTime(Task $task): void
    {
        $task->setLastExecutionTime(new \DateTimeImmutable());
        $this->entityManager->flush();
    }

    /**
     * 暂停任务
     *
     * @param int $taskId 任务ID
     */
    public function pauseTask(int $taskId): void
    {
        $task = $this->findTaskOrThrow($taskId);
        $this->validateTaskCanBePaused($task);

        $task->setStatus(TaskStatus::PAUSED);
        $this->entityManager->flush();

        // TODO: 通知正在执行的设备停止任务

        $this->logger->info('任务已暂停', ['taskId' => $taskId]);
    }

    /**
     * 恢复任务
     *
     * @param int $taskId 任务ID
     */
    public function resumeTask(int $taskId): void
    {
        $task = $this->findTaskOrThrow($taskId);
        $this->validateTaskCanBeResumed($task);

        $task->setStatus(TaskStatus::PENDING);
        $this->entityManager->flush();

        $this->dispatchTask($task);
        $this->logger->info('任务已恢复', ['taskId' => $taskId]);
    }

    /**
     * 取消任务
     *
     * @param int $taskId 任务ID
     */
    public function cancelTask(int $taskId): void
    {
        $task = $this->findTaskOrThrow($taskId);
        $this->validateTaskCanBeCancelled($task);

        $task->setStatus(TaskStatus::CANCELLED);
        $task->setEndTime(new \DateTimeImmutable());
        $this->entityManager->flush();

        // TODO: 通知正在执行的设备停止任务

        $this->logger->info('任务已取消', ['taskId' => $taskId]);
    }

    /**
     * 查找任务或抛出异常.
     */
    private function findTaskOrThrow(int $taskId): Task
    {
        $task = $this->taskRepository->find($taskId);

        if (null === $task) {
            throw new InvalidTaskArgumentException('任务不存在');
        }

        return $task;
    }

    /**
     * 验证任务可以被暂停.
     */
    private function validateTaskCanBePaused(Task $task): void
    {
        if (!in_array($task->getStatus(), [TaskStatus::PENDING, TaskStatus::RUNNING], true)) {
            throw new InvalidTaskArgumentException('只能暂停待执行或正在执行的任务');
        }
    }

    /**
     * 验证任务可以被恢复.
     */
    private function validateTaskCanBeResumed(Task $task): void
    {
        if (TaskStatus::PAUSED !== $task->getStatus()) {
            throw new InvalidTaskArgumentException('只能恢复已暂停的任务');
        }
    }

    /**
     * 验证任务可以被取消.
     */
    private function validateTaskCanBeCancelled(Task $task): void
    {
        if (in_array($task->getStatus(), [TaskStatus::COMPLETED, TaskStatus::FAILED], true)) {
            throw new InvalidTaskArgumentException('不能取消已完成或失败的任务');
        }
    }

    /**
     * 验证任务可以被重新调度.
     */
    private function validateTaskCanBeRescheduled(Task $task): void
    {
        if (!in_array($task->getStatus(), [TaskStatus::PENDING, TaskStatus::FAILED], true)) {
            throw new InvalidTaskArgumentException('任务状态不允许重新调度');
        }
    }

    /**
     * 获取任务统计信息.
     *
     * @return array<string, mixed> 统计信息
     */
    public function getTaskStatistics(): array
    {
        return [
            'total' => $this->taskRepository->count(['valid' => true]),
            'byStatus' => $this->taskRepository->countByStatus(),
            'byType' => $this->taskRepository->countByType(),
            'todayTasks' => $this->taskRepository->countTodayTasks(),
            'activeRecurring' => $this->taskRepository->count([
                'taskType' => TaskType::RECURRING,
                'status' => TaskStatus::PENDING,
                'valid' => true,
            ]),
        ];
    }

    /**
     * 将任务加入立即执行队列.
     */
    public function scheduleForImmediate(Task $task): void
    {
        $this->validateTaskCanBeRescheduled($task);

        $task->setStatus(TaskStatus::PENDING);
        $task->setScheduledTime(new \DateTimeImmutable());
        $this->entityManager->flush();

        $this->dispatchTask($task);
    }

    /**
     * 调度任务（scheduleForImmediate 的别名）.
     */
    public function scheduleTask(Task $task): void
    {
        $this->scheduleForImmediate($task);
    }

    /**
     * 调度任务重试.
     */
    public function scheduleRetry(Task $task): void
    {
        // 增加重试次数
        $task->setRetryCount($task->getRetryCount() + 1);
        $task->setStatus(TaskStatus::PENDING);

        // 计算下次重试时间（指数退避策略）
        $retryDelay = min(300, pow(2, $task->getRetryCount()) * 10); // 最多5分钟
        $task->setScheduledTime(
            (new \DateTimeImmutable())->add(new \DateInterval('PT' . $retryDelay . 'S'))
        );

        $this->entityManager->flush();

        $this->logger->info('任务已安排重试', [
            'taskId' => $task->getId(),
            'retryCount' => $task->getRetryCount(),
            'scheduledTime' => $task->getScheduledTime()?->format('Y-m-d H:i:s'),
        ]);
    }
}
