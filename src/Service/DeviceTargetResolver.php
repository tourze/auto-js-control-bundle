<?php

namespace Tourze\AutoJsControlBundle\Service;

use Tourze\AutoJsControlBundle\Entity\AutoJsDevice;
use Tourze\AutoJsControlBundle\Entity\Task;
use Tourze\AutoJsControlBundle\Enum\TaskTargetType;
use Tourze\AutoJsControlBundle\Exception\InvalidTaskArgumentException;
use Tourze\AutoJsControlBundle\Repository\AutoJsDeviceRepository;
use Tourze\AutoJsControlBundle\Repository\DeviceGroupRepository;

/**
 * 设备目标解析服务
 *
 * 专门负责解析任务的目标设备
 */
readonly class DeviceTargetResolver
{
    public function __construct(
        private AutoJsDeviceRepository $autoJsDeviceRepository,
        private DeviceGroupRepository $deviceGroupRepository,
    ) {
    }

    /**
     * 解析目标设备.
     *
     * @param array<string, mixed> $data
     */
    public function resolveTargetDevices(Task $task, array $data): void
    {
        $targetType = $task->getTargetType();

        match ($targetType) {
            TaskTargetType::SPECIFIC => $this->resolveSpecificDevices($task, $data),
            TaskTargetType::GROUP => $this->resolveGroupDevices($task, $data),
            TaskTargetType::ALL => null, // 不需要额外处理
        };
    }

    /**
     * 获取任务的目标设备.
     *
     * @return array<AutoJsDevice>
     */
    public function getTargetDevices(Task $task): array
    {
        $targetType = $task->getTargetType();

        return match ($targetType) {
            TaskTargetType::ALL => $this->getAllOnlineDevices(),
            TaskTargetType::GROUP => $this->getGroupDevices($task),
            TaskTargetType::SPECIFIC => $this->getSpecificDevices($task),
        };
    }

    /**
     * 解析指定设备目标.
     *
     * @param array<string, mixed> $data
     */
    private function resolveSpecificDevices(Task $task, array $data): void
    {
        if (!isset($data['targetDevices']) || !is_array($data['targetDevices'])) {
            throw new InvalidTaskArgumentException('必须指定目标设备列表');
        }

        $targetDevicesJson = $this->encodeDeviceIds($data['targetDevices']);
        $task->setTargetDeviceIds($targetDevicesJson);
    }

    /**
     * 解析设备组目标.
     *
     * @param array<string, mixed> $data
     */
    private function resolveGroupDevices(Task $task, array $data): void
    {
        if (!isset($data['targetGroupId'])) {
            throw new InvalidTaskArgumentException('必须指定目标设备组');
        }

        $group = $this->deviceGroupRepository->find($data['targetGroupId']);
        if (null === $group) {
            throw new InvalidTaskArgumentException('设备组不存在');
        }

        $task->setTargetGroup($group);
    }

    /**
     * 编码设备ID列表.
     *
     * @param array<mixed> $deviceIds
     */
    private function encodeDeviceIds(array $deviceIds): string
    {
        $encoded = json_encode($deviceIds);
        if (false === $encoded) {
            throw new InvalidTaskArgumentException('JSON encoding of target devices failed');
        }

        return $encoded;
    }

    /**
     * 获取所有在线设备.
     *
     * @return array<AutoJsDevice>
     */
    private function getAllOnlineDevices(): array
    {
        return $this->autoJsDeviceRepository->findAllOnlineDevices();
    }

    /**
     * 获取设备组中的设备.
     *
     * @return array<AutoJsDevice>
     */
    private function getGroupDevices(Task $task): array
    {
        $targetGroup = $task->getTargetGroup();
        if (null === $targetGroup) {
            return [];
        }

        return $this->autoJsDeviceRepository->findByGroup($targetGroup);
    }

    /**
     * 获取指定的设备列表.
     *
     * @return array<AutoJsDevice>
     */
    private function getSpecificDevices(Task $task): array
    {
        $deviceIds = $this->decodeDeviceIds($task->getTargetDeviceIds());
        if ([] === $deviceIds) {
            return [];
        }

        return $this->autoJsDeviceRepository->findBy(['id' => $deviceIds]);
    }

    /**
     * 解码设备ID列表.
     *
     * @return array<int>
     */
    private function decodeDeviceIds(?string $targetDeviceIds): array
    {
        if (null === $targetDeviceIds) {
            return [];
        }

        $decodedIds = json_decode($targetDeviceIds, true);

        return is_array($decodedIds) ? $decodedIds : [];
    }
}
