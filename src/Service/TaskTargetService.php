<?php

namespace Tourze\AutoJsControlBundle\Service;

use Tourze\AutoJsControlBundle\Entity\Task;
use Tourze\AutoJsControlBundle\Enum\TaskTargetType;
use Tourze\AutoJsControlBundle\Exception\DeviceTargetException;
use Tourze\AutoJsControlBundle\Exception\InvalidTaskArgumentException;
use Tourze\AutoJsControlBundle\Repository\DeviceGroupRepository;

/**
 * 任务目标设备服务
 *
 * 负责处理任务的目标设备逻辑
 */
readonly class TaskTargetService
{
    public function __construct(
        private DeviceGroupRepository $deviceGroupRepository,
    ) {
    }

    /**
     * 获取DeviceGroupRepository（仅用于测试）.
     */
    public function getDeviceGroupRepository(): DeviceGroupRepository
    {
        return $this->deviceGroupRepository;
    }

    /**
     * 处理目标设备.
     *
     * @param array<string, mixed> $data
     */
    public function processTargetDevices(Task $task, array $data): void
    {
        switch ($task->getTargetType()) {
            case TaskTargetType::SPECIFIC:
                $this->processSpecificDevices($task, $data);
                break;

            case TaskTargetType::GROUP:
                $this->processDeviceGroup($task, $data);
                break;

            case TaskTargetType::ALL:
            default:
                $this->processAllDevices($task);
                break;
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private function processSpecificDevices(Task $task, array $data): void
    {
        if (!isset($data['targetDevices']) || !is_array($data['targetDevices'])) {
            throw DeviceTargetException::targetDevicesRequired();
        }

        $encoded = json_encode($data['targetDevices']);
        if (false === $encoded) {
            throw new InvalidTaskArgumentException('JSON encoding of target devices failed');
        }
        $task->setTargetDeviceIds($encoded);
        $task->setTargetGroup(null);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function processDeviceGroup(Task $task, array $data): void
    {
        if (!isset($data['targetGroupId'])) {
            throw DeviceTargetException::targetGroupRequired();
        }

        $group = $this->deviceGroupRepository->find($data['targetGroupId']);
        if (null === $group) {
            throw DeviceTargetException::groupNotFound();
        }

        $task->setTargetGroup($group);
        $task->setTargetDeviceIds(null);
    }

    private function processAllDevices(Task $task): void
    {
        $task->setTargetDeviceIds(null);
        $task->setTargetGroup(null);
    }
}
