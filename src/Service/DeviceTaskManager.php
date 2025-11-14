<?php

namespace Tourze\AutoJsControlBundle\Service;

use Psr\Log\LoggerInterface;
use Tourze\AutoJsControlBundle\Entity\AutoJsDevice;
use Tourze\AutoJsControlBundle\Entity\Script;
use Tourze\AutoJsControlBundle\Entity\Task;
use Tourze\AutoJsControlBundle\Repository\TaskRepository;
use Tourze\AutoJsControlBundle\ValueObject\DeviceInstruction;

/**
 * 设备任务管理器.
 *
 * 专门负责设备任务的管理和指令发送
 */
readonly class DeviceTaskManager
{
    public function __construct(
        private TaskRepository $taskRepository,
        private InstructionQueueService $instructionQueueService,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * 发送欢迎指令到设备.
     */
    public function sendWelcomeInstruction(AutoJsDevice $device): void
    {
        $instruction = new DeviceInstruction(
            'welcome',
            'welcome',
            [
                'message' => '欢迎加入Auto.js控制系统',
                'serverTime' => new \DateTime()->format('Y-m-d H:i:s'),
                'deviceId' => $device->getId(),
            ],
            300,
            5
        );

        $this->sendInstructionToDevice($device, $instruction);
    }

    /**
     * 检查设备的待执行任务
     */
    public function checkPendingTasks(AutoJsDevice $device): void
    {
        $pendingTasks = $this->taskRepository->findPendingTasksForDevice($device);

        foreach ($pendingTasks as $task) {
            $script = $task->getScript();
            if (null === $script) {
                $this->logger->warning('任务关联的脚本不存在，跳过执行', [
                    'taskId' => $task->getId(),
                    'taskName' => $task->getName(),
                ]);
                continue;
            }

            $instruction = $this->createTaskExecutionInstruction($task, $script);
            $this->sendInstructionToDevice($device, $instruction);
        }

        if (count($pendingTasks) > 0) {
            $this->logger->info('为上线设备发送待执行任务', [
                'deviceId' => $device->getId(),
                'taskCount' => count($pendingTasks),
            ]);
        }
    }

    /**
     * 取消设备的运行中任务
     */
    public function cancelRunningTasks(AutoJsDevice $device): void
    {
        $runningTasks = $this->taskRepository->findRunningTasksForDevice($device);

        foreach ($runningTasks as $task) {
            $instruction = $this->createTaskCancellationInstruction($task);

            try {
                $this->sendInstructionToDevice($device, $instruction);
            } catch (\Exception $e) {
                $this->logger->error('发送任务取消指令失败', [
                    'deviceId' => $device->getId(),
                    'taskId' => $task->getId(),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if (count($runningTasks) > 0) {
            $this->logger->warning('设备离线，取消运行中任务', [
                'deviceId' => $device->getId(),
                'taskCount' => count($runningTasks),
            ]);
        }
    }

    private function sendInstructionToDevice(AutoJsDevice $device, DeviceInstruction $instruction): void
    {
        $baseDevice = $device->getBaseDevice();
        if (null !== $baseDevice) {
            $this->instructionQueueService->sendInstruction($baseDevice->getCode(), $instruction);
        }
    }

    private function createTaskExecutionInstruction(
        Task $task,
        Script $script,
    ): DeviceInstruction {
        return new DeviceInstruction(
            'execute_task',
            'execute_task',
            [
                'taskId' => $task->getId(),
                'taskName' => $task->getName(),
                'scriptId' => $script->getId(),
            ],
            300,
            8
        );
    }

    private function createTaskCancellationInstruction(
        Task $task,
    ): DeviceInstruction {
        return new DeviceInstruction(
            'cancel_task',
            'cancel_task',
            [
                'taskId' => $task->getId(),
                'reason' => '设备离线',
            ],
            300,
            10
        );
    }
}
