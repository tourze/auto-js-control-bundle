<?php

namespace Tourze\AutoJsControlBundle\Service;

use DeviceBundle\Enum\DeviceStatus;
use DeviceBundle\Repository\DeviceRepository;
use Psr\Log\LoggerInterface;
use Tourze\AutoJsControlBundle\Entity\AutoJsDevice;
use Tourze\AutoJsControlBundle\Exception\BusinessLogicException;
use Tourze\AutoJsControlBundle\Repository\AutoJsDeviceRepository;

/**
 * 设备生命周期管理服务
 *
 * 专门负责设备的注册、更新、删除等核心生命周期管理
 */
readonly class DeviceManager
{
    public function __construct(
        private DeviceRepository $deviceRepository,
        private AutoJsDeviceRepository $autoJsDeviceRepository,
        private DeviceRegistrationHandler $registrationHandler,
        private DeviceStatusManager $statusManager,
        private DeviceQueryService $queryService,
        private DeviceTaskManager $taskManager,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * 注册或更新设备.
     *
     * @param array<string, mixed> $deviceInfo
     */
    public function registerOrUpdateDevice(
        string $deviceCode,
        string $deviceName,
        string $certificateRequest,
        array $deviceInfo,
        string $clientIp,
    ): AutoJsDevice {
        return $this->registrationHandler->registerOrUpdate(
            $deviceCode,
            $deviceName,
            $certificateRequest,
            $deviceInfo,
            $clientIp
        );
    }

    /**
     * 获取设备信息.
     *
     * @param string $deviceCode 设备代码
     *
     * @return AutoJsDevice 设备实体
     */
    public function getDevice(string $deviceCode): AutoJsDevice
    {
        $baseDevice = $this->deviceRepository->findOneBy(['code' => $deviceCode]);
        if (null === $baseDevice) {
            throw BusinessLogicException::deviceNotFound($deviceCode);
        }

        $autoJsDevice = $this->autoJsDeviceRepository->findOneBy(['baseDevice' => $baseDevice]);
        if (null === $autoJsDevice) {
            throw BusinessLogicException::deviceNotFound($deviceCode);
        }

        return $autoJsDevice;
    }

    /**
     * 获取设备信息（通过ID）.
     *
     * @param int $deviceId 设备ID
     *
     * @return AutoJsDevice 设备实体
     */
    public function getDeviceById(int $deviceId): AutoJsDevice
    {
        $autoJsDevice = $this->autoJsDeviceRepository->find($deviceId);
        if (null === $autoJsDevice) {
            throw BusinessLogicException::deviceNotFound((string) $deviceId);
        }

        return $autoJsDevice;
    }

    /**
     * 删除设备（软删除）.
     */
    public function deleteDevice(string $deviceCode): void
    {
        $device = $this->getDevice($deviceCode);
        $this->statusManager->markAsDeleted($device);

        $this->logger->info('设备已删除', [
            'deviceCode' => $deviceCode,
            'deviceId' => $device->getId(),
        ]);
    }

    /**
     * 更新设备状态
     */
    public function updateDeviceStatus(string $deviceCode, DeviceStatus $status): void
    {
        $device = $this->getDevice($deviceCode);
        $this->statusManager->updateStatus($device, $status);
    }

    /**
     * 获取在线设备列表.
     *
     * @return array<string, mixed>
     */
    public function getOnlineDevices(int $page = 1, int $limit = 20): array
    {
        return $this->queryService->getOnlineDevices($page, $limit);
    }

    /**
     * 批量获取设备状态
     *
     * @param array<string> $deviceCodes
     *
     * @return array<string, array<string, mixed>>
     */
    public function getDevicesStatus(array $deviceCodes): array
    {
        return $this->queryService->getDevicesStatus($deviceCodes);
    }

    /**
     * 获取设备统计信息.
     *
     * @return array<string, mixed>
     */
    public function getDeviceStatistics(): array
    {
        return $this->queryService->getDeviceStatistics();
    }

    /**
     * 检查设备的待执行任务
     */
    public function checkPendingTasks(AutoJsDevice $device): void
    {
        $this->taskManager->checkPendingTasks($device);
    }

    /**
     * 取消设备的运行中任务
     */
    public function cancelRunningTasks(AutoJsDevice $device): void
    {
        $this->taskManager->cancelRunningTasks($device);
    }

    /**
     * 发送欢迎指令到设备.
     */
    public function sendWelcomeInstruction(AutoJsDevice $device): void
    {
        $this->taskManager->sendWelcomeInstruction($device);
    }

    /**
     * 搜索设备.
     *
     * @param array<string, mixed>                     $criteria
     * @param array<string, 'ASC'|'asc'|'DESC'|'desc'> $orderBy
     *
     * @return array<int, AutoJsDevice>
     */
    public function searchDevices(
        array $criteria = [],
        array $orderBy = ['id' => 'DESC'],
        int $limit = 20,
        int $offset = 0,
    ): array {
        return $this->autoJsDeviceRepository->findBy($criteria, $orderBy, $limit, $offset);
    }
}
