<?php

namespace Tourze\AutoJsControlBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Tourze\AutoJsControlBundle\Entity\AutoJsDevice;
use Tourze\AutoJsControlBundle\Entity\Task;
use Tourze\AutoJsControlBundle\Enum\TaskStatus;
use Tourze\AutoJsControlBundle\Exception\TaskException;
use Tourze\AutoJsControlBundle\ValueObject\DeviceInstruction;

/**
 * 任务分发服务
 *
 * 专门负责任务的分发和执行管理
 */
readonly class TaskDispatcher
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private DeviceTargetResolver $deviceTargetResolver,
        private InstructionQueueService $instructionQueueService,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * 分发任务到设备.
     */
    public function dispatchTask(Task $task): void
    {
        try {
            $devices = $this->deviceTargetResolver->getTargetDevices($task);

            if ([] === $devices) {
                $this->markTaskFailed($task, '没有可用的目标设备');

                return;
            }

            $this->updateTaskToRunning($task, count($devices));
            $successCount = $this->sendInstructionsToDevices($task, $devices);
            $this->logTaskDispatchResult($task, count($devices), $successCount);

            if (0 === $successCount) {
                $this->markTaskFailed($task, '所有设备发送指令失败');
            }
        } catch (\Exception $e) {
            $this->logger->error('分发任务失败', [
                'taskId' => $task->getId(),
                'error' => $e->getMessage(),
                'exception' => $e,
            ]);

            $this->markTaskFailed($task, $e->getMessage());
        }
    }

    /**
     * 更新任务进度.
     */
    public function updateTaskProgress(Task $task, string $instructionId, string $status): void
    {
        $this->updateTaskDeviceCount($task, $status);
        $this->updateTaskStatusIfCompleted($task);

        $this->entityManager->flush();
        $this->logTaskProgress($task, $instructionId, $status);
    }

    /**
     * 更新任务状态为运行中.
     */
    private function updateTaskToRunning(Task $task, int $deviceCount): void
    {
        $task->setStatus(TaskStatus::RUNNING);
        $task->setStartTime(new \DateTimeImmutable());
        $task->setTotalDevices($deviceCount);
        $this->entityManager->flush();
    }

    /**
     * 向设备发送指令.
     *
     * @param array<AutoJsDevice> $devices
     */
    private function sendInstructionsToDevices(Task $task, array $devices): int
    {
        $successCount = 0;

        foreach ($devices as $device) {
            if ($this->sendInstructionToDevice($task, $device)) {
                ++$successCount;
            }
        }

        return $successCount;
    }

    /**
     * 向单个设备发送指令.
     */
    private function sendInstructionToDevice(Task $task, AutoJsDevice $device): bool
    {
        $baseDevice = $device->getBaseDevice();
        if (null === $baseDevice) {
            return false;
        }

        try {
            $instruction = $this->createInstructionForDevice($task);
            $this->instructionQueueService->sendInstruction(
                $baseDevice->getCode(),
                $instruction,
                $task->getPriority() > 5
            );

            return true;
        } catch (\Exception $e) {
            $this->logger->warning('发送指令到设备失败', [
                'taskId' => $task->getId(),
                'deviceCode' => $baseDevice->getCode(),
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * 创建设备指令.
     */
    private function createInstructionForDevice(Task $task): DeviceInstruction
    {
        $script = $task->getScript();
        if (null === $script) {
            throw new TaskException('Task script cannot be null');
        }

        return new DeviceInstruction(
            instructionId: $this->generateInstructionId(),
            type: DeviceInstruction::TYPE_EXECUTE_SCRIPT,
            data: [
                'scriptId' => $script->getId(),
                'parameters' => $this->decodeTaskParameters($task),
                'taskId' => $task->getId(),
            ],
            timeout: $script->getTimeout(),
            priority: $task->getPriority(),
            taskId: $task->getId(),
            scriptId: $script->getId()
        );
    }

    /**
     * 解码任务参数.
     *
     * @return array<string, mixed>
     */
    private function decodeTaskParameters(Task $task): array
    {
        $parameters = $task->getParameters() ?? '{}';
        $decodedParams = json_decode($parameters, true);

        return (false !== $decodedParams && is_array($decodedParams)) ? $decodedParams : [];
    }

    /**
     * 生成指令ID.
     */
    private function generateInstructionId(): string
    {
        return 'INS-' . uniqid() . '-' . bin2hex(random_bytes(4));
    }

    /**
     * 更新任务设备计数.
     */
    private function updateTaskDeviceCount(Task $task, string $status): void
    {
        if ('success' === $status) {
            $task->setSuccessDevices($task->getSuccessDevices() + 1);

            return;
        }

        if (in_array($status, ['failed', 'timeout', 'cancelled'], true)) {
            $task->setFailedDevices($task->getFailedDevices() + 1);
        }
    }

    /**
     * 如果任务完成则更新任务状态
     */
    private function updateTaskStatusIfCompleted(Task $task): void
    {
        $completedDevices = $task->getSuccessDevices() + $task->getFailedDevices();

        if ($completedDevices < $task->getTotalDevices()) {
            return;
        }

        $task->setStatus($this->determineCompletionStatus($task));
        $task->setEndTime(new \DateTimeImmutable());
    }

    /**
     * 确定任务完成状态
     */
    private function determineCompletionStatus(Task $task): TaskStatus
    {
        if ($task->getSuccessDevices() === $task->getTotalDevices()) {
            return TaskStatus::COMPLETED;
        }

        if ($task->getSuccessDevices() > 0) {
            return TaskStatus::PARTIALLY_COMPLETED;
        }

        return TaskStatus::FAILED;
    }

    /**
     * 标记任务失败.
     */
    private function markTaskFailed(Task $task, string $reason): void
    {
        $task->setStatus(TaskStatus::FAILED);
        $task->setEndTime(new \DateTimeImmutable());
        $task->setFailureReason($reason);

        $this->entityManager->flush();

        $this->logger->error('任务执行失败', [
            'taskId' => $task->getId(),
            'reason' => $reason,
        ]);
    }

    /**
     * 记录任务分发结果.
     */
    private function logTaskDispatchResult(Task $task, int $totalDevices, int $successCount): void
    {
        $this->logger->info('任务已分发', [
            'taskId' => $task->getId(),
            'totalDevices' => $totalDevices,
            'successCount' => $successCount,
        ]);
    }

    /**
     * 记录任务进度.
     */
    private function logTaskProgress(Task $task, string $instructionId, string $status): void
    {
        $this->logger->debug('任务进度已更新', [
            'taskId' => $task->getId(),
            'instructionId' => $instructionId,
            'status' => $status,
            'progress' => [
                'total' => $task->getTotalDevices(),
                'success' => $task->getSuccessDevices(),
                'failed' => $task->getFailedDevices(),
            ],
        ]);
    }
}
