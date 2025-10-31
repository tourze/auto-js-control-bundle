<?php

namespace Tourze\AutoJsControlBundle\Service;

use Tourze\AutoJsControlBundle\Entity\AutoJsDevice;
use Tourze\AutoJsControlBundle\Exception\BusinessLogicException;
use Tourze\AutoJsControlBundle\Repository\AutoJsDeviceRepository;

/**
 * 设备查询服务
 *
 * 专门负责设备的查询、统计和列表获取
 */
readonly class DeviceQueryService
{
    public function __construct(
        private AutoJsDeviceRepository $autoJsDeviceRepository,
        private DeviceHeartbeatService $heartbeatService,
    ) {
    }

    /**
     * 获取在线设备列表.
     *
     * @return array<string, mixed>
     */
    public function getOnlineDevices(int $page = 1, int $limit = 20): array
    {
        $allDevices = $this->autoJsDeviceRepository->findAll();
        $onlineDevices = [];

        foreach ($allDevices as $device) {
            $baseDevice = $device->getBaseDevice();
            if (null !== $baseDevice) {
                $deviceCode = $baseDevice->getCode();
                if ($this->heartbeatService->isDeviceOnline($deviceCode)) {
                    $onlineDevices[] = $device;
                }
            }
        }

        return $this->paginateDevices($onlineDevices, $page, $limit);
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
        $statusMap = [];

        foreach ($deviceCodes as $deviceCode) {
            try {
                $device = $this->getDeviceByCode($deviceCode);
                $statusMap[$deviceCode] = $this->buildDeviceStatus($device);
            } catch (BusinessLogicException) {
                $statusMap[$deviceCode] = ['error' => '设备不存在'];
            }
        }

        return $statusMap;
    }

    /**
     * 获取设备统计信息.
     *
     * @return array<string, mixed>
     */
    public function getDeviceStatistics(): array
    {
        $stats = [
            'total' => 0,
            'online' => 0,
            'offline' => 0,
            'maintenance' => 0,
            'byBrand' => [],
            'byOsVersion' => [],
        ];

        $devices = $this->autoJsDeviceRepository->findAll();
        $stats['total'] = count($devices);

        foreach ($devices as $device) {
            $stats = $this->updateStatsForDevice($stats, $device);
        }

        return $stats;
    }

    private function getDeviceByCode(string $deviceCode): AutoJsDevice
    {
        $baseDevice = $this->autoJsDeviceRepository->findByDeviceCode($deviceCode);
        if (null === $baseDevice) {
            throw BusinessLogicException::deviceNotFound($deviceCode);
        }

        return $baseDevice;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildDeviceStatus(AutoJsDevice $device): array
    {
        $baseDevice = $device->getBaseDevice();
        if (null === $baseDevice) {
            return ['error' => '设备基础信息丢失'];
        }

        $deviceCode = $baseDevice->getCode();
        $isOnline = $this->heartbeatService->isDeviceOnline($deviceCode);
        $metrics = $this->heartbeatService->getDeviceMetrics($deviceCode);

        return [
            'id' => $device->getId(),
            'name' => $baseDevice->getName(),
            'online' => $isOnline,
            'lastOnlineTime' => $baseDevice->getLastOnlineTime()?->format(\DateTimeInterface::RFC3339),
            'metrics' => $metrics,
        ];
    }

    /**
     * @param array<AutoJsDevice> $devices
     *
     * @return array<string, mixed>
     */
    private function paginateDevices(array $devices, int $page, int $limit): array
    {
        $total = count($devices);
        $offset = ($page - 1) * $limit;
        $pageDevices = array_slice($devices, $offset, $limit);

        return [
            'devices' => $pageDevices,
            'pagination' => [
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'totalPages' => ceil($total / $limit),
            ],
        ];
    }

    /**
     * @param array<string, mixed> $stats
     * @return array<string, mixed>
     */
    private function updateStatsForDevice(array $stats, AutoJsDevice $device): array
    {
        $baseDevice = $device->getBaseDevice();
        if (null === $baseDevice) {
            return $stats;
        }

        $deviceCode = $baseDevice->getCode();

        if ($this->heartbeatService->isDeviceOnline($deviceCode)) {
            /** @var int $onlineCount */
            $onlineCount = $stats['online'];
            $stats['online'] = $onlineCount + 1;
        } else {
            /** @var int $offlineCount */
            $offlineCount = $stats['offline'];
            $stats['offline'] = $offlineCount + 1;
        }

        $stats = $this->updateBrandStats($stats, $baseDevice->getBrand());

        return $this->updateOsVersionStats($stats, $baseDevice->getOsVersion());
    }

    /**
     * @param array<string, mixed> $stats
     * @return array<string, mixed>
     */
    private function updateBrandStats(array $stats, ?string $brand): array
    {
        $brand ??= 'Unknown';

        /** @var array<string, int> $byBrand */
        $byBrand = $stats['byBrand'];

        if (!isset($byBrand[$brand])) {
            $byBrand[$brand] = 0;
        }
        $byBrand[$brand] = $byBrand[$brand] + 1;

        $stats['byBrand'] = $byBrand;

        return $stats;
    }

    /**
     * @param array<string, mixed> $stats
     * @return array<string, mixed>
     */
    private function updateOsVersionStats(array $stats, ?string $osVersion): array
    {
        $osVersion ??= 'Unknown';

        /** @var array<string, int> $byOsVersion */
        $byOsVersion = $stats['byOsVersion'];

        if (!isset($byOsVersion[$osVersion])) {
            $byOsVersion[$osVersion] = 0;
        }
        $byOsVersion[$osVersion] = $byOsVersion[$osVersion] + 1;

        $stats['byOsVersion'] = $byOsVersion;

        return $stats;
    }
}
