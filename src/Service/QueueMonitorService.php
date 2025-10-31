<?php

declare(strict_types=1);

namespace Tourze\AutoJsControlBundle\Service;

use Tourze\AutoJsControlBundle\Entity\AutoJsDevice;
use Tourze\AutoJsControlBundle\Exception\BusinessLogicException;
use Tourze\AutoJsControlBundle\Repository\AutoJsDeviceRepository;

final readonly class QueueMonitorService
{
    public function __construct(
        private InstructionQueueService $queueService,
        private DeviceHeartbeatService $heartbeatService,
        private AutoJsDeviceRepository $deviceRepository,
    ) {
    }

    /**
     * @return array{id: int, code: string, name: string, isOnline: bool, queueLength: int, statusDisplay: '<fg=green>在线</>'|'<fg=red>离线</>', queueStatusDisplay: string}
     */
    public function getDeviceStats(AutoJsDevice $device): array
    {
        $baseDevice = $device->getBaseDevice();
        if (null === $baseDevice) {
            throw BusinessLogicException::configurationError('Device base device is required');
        }

        $deviceCode = $baseDevice->getCode();

        $isOnline = $this->heartbeatService->isDeviceOnline($deviceCode);
        $queueLength = $this->queueService->getQueueLength($deviceCode);

        return [
            'id' => $device->getId() ?? 0,
            'code' => $deviceCode,
            'name' => $baseDevice->getName() ?? '',
            'isOnline' => $isOnline,
            'queueLength' => $queueLength,
            'statusDisplay' => $isOnline ? '<fg=green>在线</>' : '<fg=red>离线</>',
            'queueStatusDisplay' => $this->getQueueStatus($queueLength),
        ];
    }

    /**
     * @param array<AutoJsDevice> $devices
     *
     * @return array{devices: array<array{id: int, code: string, name: string, isOnline: bool, queueLength: int, statusDisplay: string, queueStatusDisplay: string}>, totalQueueLength: int, onlineCount: int, totalCount: int}
     */
    public function collectDeviceStatistics(array $devices): array
    {
        $stats = [
            'devices' => [],
            'totalQueueLength' => 0,
            'onlineCount' => 0,
            'totalCount' => count($devices),
        ];

        foreach ($devices as $device) {
            $deviceStats = $this->getDeviceStats($device);
            $stats['devices'][] = $deviceStats;
            $stats['totalQueueLength'] += $deviceStats['queueLength'];
            if ($deviceStats['isOnline']) {
                ++$stats['onlineCount'];
            }
        }

        return $stats;
    }

    /**
     * @return array{isOnline: bool, queueLength: int, metrics: array<mixed>}
     */
    public function collectDeviceInfo(string $deviceCode): array
    {
        return [
            'isOnline' => $this->heartbeatService->isDeviceOnline($deviceCode),
            'queueLength' => $this->queueService->getQueueLength($deviceCode),
            'metrics' => $this->heartbeatService->getDeviceMetrics($deviceCode),
        ];
    }

    /**
     * @return array{totalDevices: int, onlineCount: int, totalQueueLength: int, busyDevices: array<array{code: string, name: string, queueLength: int, isOnline: bool}>}
     */
    public function gatherDeviceStatistics(): array
    {
        $devices = $this->deviceRepository->findAll();
        $stats = [
            'totalDevices' => count($devices),
            'onlineCount' => 0,
            'totalQueueLength' => 0,
            'busyDevices' => [],
        ];

        foreach ($devices as $device) {
            $stats = $this->processDeviceForStatistics($device, $stats);
        }

        return $stats;
    }

    /**
     * @param array{totalDevices: int, onlineCount: int, totalQueueLength: int, busyDevices: array<array{code: string, name: string, queueLength: int, isOnline: bool}>} $stats
     * @return array{totalDevices: int, onlineCount: int, totalQueueLength: int, busyDevices: array<array{code: string, name: string, queueLength: int, isOnline: bool}>}
     */
    private function processDeviceForStatistics(AutoJsDevice $device, array $stats): array
    {
        $baseDevice = $device->getBaseDevice();
        if (null === $baseDevice) {
            return $stats;
        }

        $deviceCode = $baseDevice->getCode();
        $isOnline = $this->heartbeatService->isDeviceOnline($deviceCode);
        $queueLength = $this->queueService->getQueueLength($deviceCode);

        if ($isOnline) {
            ++$stats['onlineCount'];
        }

        $stats['totalQueueLength'] += $queueLength;

        if ($queueLength > 0) {
            $stats['busyDevices'][] = [
                'code' => $deviceCode,
                'name' => $baseDevice->getName() ?? '',
                'queueLength' => $queueLength,
                'isOnline' => $isOnline,
            ];
        }

        return $stats;
    }

    /**
     * @return array<array{code: string, name: string, queueLength: int, isOnline: bool}>
     */
    /**
     * @param array<array{code: string, name: string, queueLength: int, isOnline: bool}> $busyDevices
     *
     * @return array<array{code: string, name: string, queueLength: int, isOnline: bool}>
     */
    public function sortDevicesByQueueLength(array $busyDevices): array
    {
        usort($busyDevices, fn ($a, $b) => $b['queueLength'] - $a['queueLength']);

        return $busyDevices;
    }

    /**
     * @return array<mixed>
     */
    public function previewQueue(string $deviceCode, int $limit): array
    {
        return $this->queueService->previewQueue($deviceCode, $limit);
    }

    public function getQueueLength(string $deviceCode): int
    {
        return $this->queueService->getQueueLength($deviceCode);
    }

    public function clearDeviceQueue(string $deviceCode): int
    {
        return $this->queueService->clearDeviceQueue($deviceCode);
    }

    public function findDevice(string $deviceCode): ?AutoJsDevice
    {
        return $this->deviceRepository->findByDeviceCode($deviceCode);
    }

    /**
     * @return array<AutoJsDevice>
     */
    public function getAllDevices(): array
    {
        return $this->deviceRepository->findAll();
    }

    private function getQueueStatus(int $queueLength): string
    {
        if (0 === $queueLength) {
            return '<fg=green>空闲</>';
        }
        if ($queueLength < 10) {
            return '<fg=yellow>正常</>';
        }
        if ($queueLength < 50) {
            return '<fg=magenta>繁忙</>';
        }

        return '<fg=red>拥塞</>';
    }
}
