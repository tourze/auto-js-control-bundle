<?php

declare(strict_types=1);

namespace Tourze\AutoJsControlBundle\EventSubscriber;

use DeviceBundle\Enum\DeviceStatus;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Tourze\AutoJsControlBundle\Entity\DeviceLog;
use Tourze\AutoJsControlBundle\Enum\LogLevel;
use Tourze\AutoJsControlBundle\Enum\LogType;
use Tourze\AutoJsControlBundle\Event\DeviceRegisteredEvent;
use Tourze\AutoJsControlBundle\Event\DeviceStatusChangedEvent;
use Tourze\AutoJsControlBundle\Event\InstructionSentEvent;
use Tourze\AutoJsControlBundle\Event\ScriptExecutedEvent;
use Tourze\AutoJsControlBundle\Event\TaskCreatedEvent;
use Tourze\AutoJsControlBundle\Event\TaskStatusChangedEvent;

readonly class NotificationEventSubscriber
{
    public function __construct(
        private LoggerInterface $logger,
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[AsEventListener(event: DeviceRegisteredEvent::class, priority: -10)]
    public function onDeviceRegistered(DeviceRegisteredEvent $event): void
    {
        $device = $event->getDevice();

        $this->createDeviceLog(
            $device,
            LogType::SYSTEM,
            LogLevel::INFO,
            'Device registered',
            [
                'ip_address' => $event->getIpAddress(),
                'device_info' => $event->getDeviceInfo(),
            ]
        );
    }

    #[AsEventListener(event: DeviceStatusChangedEvent::class, priority: -10)]
    public function onDeviceStatusChanged(DeviceStatusChangedEvent $event): void
    {
        $device = $event->getDevice();

        $message = $event->isOnline() ? 'Device came online' : 'Device went offline';
        $level = $event->isOnline() ? LogLevel::INFO : LogLevel::WARNING;

        $this->createDeviceLog(
            $device,
            LogType::SYSTEM,
            $level,
            $message,
            [
                'previous_status' => $this->getStatusString($event->getPreviousStatus()),
                'current_status' => $this->getStatusString($event->getCurrentStatus()),
            ]
        );
    }

    #[AsEventListener(event: TaskCreatedEvent::class, priority: -10)]
    public function onTaskCreated(TaskCreatedEvent $event): void
    {
        $task = $event->getTask();

        foreach ($task->getTargetDevices() as $device) {
            $this->createDeviceLog(
                $device,
                LogType::TASK,
                LogLevel::INFO,
                sprintf('Task "%s" created', $task->getName()),
                [
                    'task_id' => $task->getId(),
                    'task_type' => $task->getType()->value,
                    'is_immediate' => $event->isImmediate(),
                    'created_by' => $event->getCreatedBy(),
                ]
            );
        }
    }

    #[AsEventListener(event: TaskStatusChangedEvent::class, priority: -10)]
    public function onTaskStatusChanged(TaskStatusChangedEvent $event): void
    {
        $task = $event->getTask();

        $level = match (true) {
            $event->hasCompleted() => LogLevel::INFO,
            $event->hasFailed() => LogLevel::ERROR,
            $event->wasCancelled() => LogLevel::WARNING,
            default => LogLevel::INFO,
        };

        $message = sprintf(
            'Task "%s" status changed: %s → %s',
            $task->getName(),
            $event->getPreviousStatus()->value,
            $event->getCurrentStatus()->value
        );

        foreach ($task->getTargetDevices() as $device) {
            $this->createDeviceLog(
                $device,
                LogType::TASK,
                $level,
                $message,
                [
                    'task_id' => $task->getId(),
                    'reason' => $event->getReason(),
                ]
            );
        }
    }

    #[AsEventListener(event: ScriptExecutedEvent::class, priority: -10)]
    public function onScriptExecuted(ScriptExecutedEvent $event): void
    {
        $device = $event->getDevice();
        $script = $event->getScript();

        if ($event->isStarted()) {
            $message = sprintf('Script "%s" execution started', $script->getName());
            $level = LogLevel::INFO;
        } else {
            $message = $event->isSuccess()
                ? sprintf('Script "%s" execution completed successfully', $script->getName())
                : sprintf('Script "%s" execution failed', $script->getName());
            $level = $event->isSuccess() ? LogLevel::INFO : LogLevel::ERROR;
        }

        $this->createDeviceLog(
            $device,
            LogType::SCRIPT,
            $level,
            $message,
            [
                'script_id' => $script->getId(),
                'task_id' => $event->getTask()?->getId(),
                'execution_result' => $event->getExecutionResult(),
                'error_message' => $event->getErrorMessage(),
            ]
        );
    }

    #[AsEventListener(event: InstructionSentEvent::class, priority: -10)]
    public function onInstructionSent(InstructionSentEvent $event): void
    {
        $device = $event->getDevice();
        $instruction = $event->getInstruction();

        if (!$event->isSuccess() || $event->isHighPriority()) {
            $level = $event->isSuccess() ? LogLevel::INFO : LogLevel::ERROR;
            $message = $event->isSuccess()
                ? sprintf('Instruction "%s" sent successfully', $instruction->getType())
                : sprintf('Failed to send instruction "%s"', $instruction->getType());

            $this->createDeviceLog(
                $device,
                LogType::SYSTEM,
                $level,
                $message,
                [
                    'instruction_type' => $instruction->getType(),
                    'priority' => $instruction->getPriority(),
                    'error_message' => $event->getErrorMessage(),
                    'metadata' => $event->getMetadata(),
                ]
            );
        }
    }

    /**
     * @param array<string, mixed> $context
     */
    private function createDeviceLog(
        mixed $device,
        LogType $type,
        LogLevel $level,
        string $message,
        array $context = [],
    ): void {
        try {
            $log = new DeviceLog();
            $log->setAutoJsDevice($device);
            $log->setLogType($type);
            $log->setLogLevel($level);
            $log->setMessage($message);
            $contextJson = json_encode($context);
            if (false !== $contextJson) {
                $log->setContext($contextJson);
            }
            $log->setCreateTime(new \DateTimeImmutable());

            $this->entityManager->persist($log);
            $this->entityManager->flush();
        } catch (\Exception $e) {
            $this->logger->error('Failed to create device log', [
                'device_id' => $device->getId(),
                'message' => $message,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * 将设备状态转换为字符串表示.
     */
    private function getStatusString(mixed $status): string
    {
        if (is_bool($status)) {
            return $status ? 'online' : 'offline';
        }
        if ($status instanceof DeviceStatus) {
            return $status->value;
        }

        return 'unknown';
    }
}
