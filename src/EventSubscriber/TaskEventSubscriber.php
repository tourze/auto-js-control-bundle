<?php

declare(strict_types=1);

namespace Tourze\AutoJsControlBundle\EventSubscriber;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Tourze\AutoJsControlBundle\Entity\ScriptExecutionRecord;
use Tourze\AutoJsControlBundle\Enum\ExecutionStatus;
use Tourze\AutoJsControlBundle\Event\ScriptExecutedEvent;
use Tourze\AutoJsControlBundle\Event\TaskCreatedEvent;
use Tourze\AutoJsControlBundle\Event\TaskStatusChangedEvent;
use Tourze\AutoJsControlBundle\Repository\ScriptExecutionRecordRepository;
use Tourze\AutoJsControlBundle\Service\TaskScheduler;

/**
 * 任务相关事件订阅者.
 *
 * 处理任务创建、状态变更、脚本执行等事件
 */
readonly class TaskEventSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private TaskScheduler $taskScheduler,
        private ScriptExecutionRecordRepository $executionRecordRepository,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * 处理任务创建事件.
     */
    #[AsEventListener(event: TaskCreatedEvent::class, priority: 10)]
    public function onTaskCreated(TaskCreatedEvent $event): void
    {
        $task = $event->getTask();
        $this->logger->info('Task created', [
            'task_id' => $task->getId(),
            'name' => $task->getName(),
            'type' => $task->getType()->value,
            'is_immediate' => $event->isImmediate(),
            'created_by' => $event->getCreatedBy(),
        ]);

        // 如果是立即执行的任务，将其加入执行队列
        if ($event->isImmediate()) {
            try {
                $this->taskScheduler->scheduleTask($task);
                $this->logger->info('Task scheduled for immediate execution', [
                    'task_id' => $task->getId(),
                ]);
            } catch (\Exception $e) {
                $this->logger->error('Failed to schedule task for immediate execution', [
                    'task_id' => $task->getId(),
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * 处理任务状态变更事件.
     */
    #[AsEventListener(event: TaskStatusChangedEvent::class, priority: 5)]
    public function onTaskStatusChanged(TaskStatusChangedEvent $event): void
    {
        $task = $event->getTask();
        $this->logger->info('Task status changed', [
            'task_id' => $task->getId(),
            'name' => $task->getName(),
            'previous_status' => $event->getPreviousStatus()->value,
            'current_status' => $event->getCurrentStatus()->value,
            'reason' => $event->getReason(),
        ]);

        // 根据不同的状态变更执行不同的逻辑
        if ($event->hasStarted()) {
            $this->handleTaskStarted($event);
        } elseif ($event->hasCompleted()) {
            $this->handleTaskCompleted($event);
        } elseif ($event->hasFailed()) {
            $this->handleTaskFailed($event);
        } elseif ($event->wasCancelled()) {
            $this->handleTaskCancelled($event);
        }
    }

    /**
     * 处理脚本执行事件.
     */
    #[AsEventListener(event: ScriptExecutedEvent::class, priority: 0)]
    public function onScriptExecuted(ScriptExecutedEvent $event): void
    {
        if ($event->isStarted()) {
            $this->handleScriptStarted($event);
        } else {
            $this->handleScriptCompleted($event);
        }
    }

    private function handleScriptStarted(ScriptExecutedEvent $event): void
    {
        $script = $event->getScript();
        $device = $event->getDevice();

        $this->logScriptStarted($script, $device, $event->getTask()?->getId());
        $this->createExecutionRecord($event);
    }

    private function handleScriptCompleted(ScriptExecutedEvent $event): void
    {
        $script = $event->getScript();
        $device = $event->getDevice();

        $this->logScriptCompleted($script, $device, $event);
        $this->updateExecutionRecord($event);
    }

    private function logScriptStarted(mixed $script, mixed $device, ?int $taskId): void
    {
        $this->logger->info('Script execution started', [
            'script_id' => $script->getId(),
            'script_name' => $script->getName(),
            'device_id' => $device->getDeviceId(),
            'task_id' => $taskId,
        ]);
    }

    private function logScriptCompleted(mixed $script, mixed $device, ScriptExecutedEvent $event): void
    {
        $this->logger->info('Script execution completed', [
            'script_id' => $script->getId(),
            'script_name' => $script->getName(),
            'device_id' => $device->getDeviceId(),
            'success' => $event->isSuccess(),
            'error' => $event->getErrorMessage(),
        ]);
    }

    private function createExecutionRecord(ScriptExecutedEvent $event): void
    {
        if (null === $event->getExecutionRecord() && null !== $event->getTask()) {
            try {
                $record = new ScriptExecutionRecord();
                $record->setScript($event->getScript());
                $record->setDevice($event->getDevice());
                $record->setTask($event->getTask());
                $record->setStatus(ExecutionStatus::RUNNING);
                $record->setStartedAt(new \DateTime());

                $this->entityManager->persist($record);
                $this->entityManager->flush();
            } catch (\Exception $e) {
                $this->logger->error('Failed to create execution record', [
                    'script_id' => $event->getScript()->getId(),
                    'device_id' => $event->getDevice()->getDeviceId(),
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    private function updateExecutionRecord(ScriptExecutedEvent $event): void
    {
        if (($record = $event->getExecutionRecord()) !== null) {
            try {
                $record->setStatus($event->isSuccess() ? ExecutionStatus::SUCCESS : ExecutionStatus::FAILED);
                $record->setCompletedAt(new \DateTime());
                $resultJson = json_encode($event->getExecutionResult());
                if (false !== $resultJson) {
                    $record->setResult($resultJson);
                }

                $this->entityManager->persist($record);
                $this->entityManager->flush();
            } catch (\Exception $e) {
                $this->logger->error('Failed to update execution record', [
                    'record_id' => $record->getId(),
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * 处理任务开始执行.
     */
    private function handleTaskStarted(TaskStatusChangedEvent $event): void
    {
        $task = $event->getTask();

        // 记录任务开始时间
        $task->setStartedAt($event->getStatusChangedTime() ?? new \DateTime());

        $this->logger->info('Task execution started', [
            'task_id' => $task->getId(),
            'devices_count' => count($task->getTargetDevices()),
        ]);
    }

    /**
     * 处理任务执行完成.
     */
    private function handleTaskCompleted(TaskStatusChangedEvent $event): void
    {
        $task = $event->getTask();

        // 记录任务完成时间
        $task->setCompletedAt($event->getStatusChangedTime() ?? new \DateTime());

        // 计算执行统计
        $stats = $this->executionRecordRepository->getTaskExecutionStats($task);

        $this->logger->info('Task execution completed', [
            'task_id' => $task->getId(),
            'total_executions' => $stats['total'],
            'successful' => $stats['successful'],
            'failed' => $stats['failed'],
            'duration' => ($task->getCompletedAt()?->getTimestamp() ?? 0) - ($task->getStartedAt()?->getTimestamp() ?? 0),
        ]);
    }

    /**
     * 处理任务执行失败.
     */
    private function handleTaskFailed(TaskStatusChangedEvent $event): void
    {
        $task = $event->getTask();

        $this->logger->error('Task execution failed', [
            'task_id' => $task->getId(),
            'reason' => $event->getReason(),
        ]);

        // 根据重试策略决定是否重试
        if ($task->getRetryCount() < $task->getMaxRetries()) {
            try {
                $this->taskScheduler->scheduleTask($task);
                $this->logger->info('Task scheduled for retry', [
                    'task_id' => $task->getId(),
                    'retry_count' => $task->getRetryCount() + 1,
                ]);
            } catch (\Exception $e) {
                $this->logger->error('Failed to schedule task retry', [
                    'task_id' => $task->getId(),
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * 处理任务取消.
     */
    private function handleTaskCancelled(TaskStatusChangedEvent $event): void
    {
        $task = $event->getTask();

        $this->logger->warning('Task cancelled', [
            'task_id' => $task->getId(),
            'reason' => $event->getReason(),
        ]);

        // 取消所有相关的执行记录
        try {
            $this->executionRecordRepository->cancelTaskExecutions($task);
        } catch (\Exception $e) {
            $this->logger->error('Failed to cancel task executions', [
                'task_id' => $task->getId(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * 获取订阅的事件.
     */
    public static function getSubscribedEvents(): array
    {
        return [
            TaskCreatedEvent::class => 'onTaskCreated',
            TaskStatusChangedEvent::class => 'onTaskStatusChanged',
            ScriptExecutedEvent::class => 'onScriptExecuted',
        ];
    }
}
