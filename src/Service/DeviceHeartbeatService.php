<?php

namespace Tourze\AutoJsControlBundle\Service;

use DeviceBundle\Entity\Device;
use DeviceBundle\Enum\DeviceStatus;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Tourze\AutoJsControlBundle\Entity\AutoJsDevice;
use Tourze\AutoJsControlBundle\Entity\DeviceMonitorData;
use Tourze\AutoJsControlBundle\Exception\BusinessLogicException;
use Tourze\AutoJsControlBundle\Exception\InvalidArgumentException;
use Tourze\AutoJsControlBundle\ValueObject\RedisQueueKeys;
use Tourze\LockServiceBundle\Service\LockService;

/**
 * 设备心跳处理服务
 *
 * 负责处理设备心跳、更新在线状态和监控数据
 */
class DeviceHeartbeatService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
        private CacheStorageService $cacheStorage,
        private LockService $lockService,
    ) {
    }

    /**
     * 处理设备心跳.
     *
     * @param AutoJsDevice $device        设备实体
     * @param string       $autoJsVersion Auto.js版本
     * @param array        $deviceInfo    设备信息
     * @param array        $monitorData   监控数据
     */
    /**
     * @param array<string, mixed> $deviceInfo
     * @param array<string, mixed> $monitorData
     */
    public function processHeartbeat(
        AutoJsDevice $device,
        ?string $autoJsVersion = null,
        array $deviceInfo = [],
        array $monitorData = [],
    ): void {
        $baseDevice = $device->getBaseDevice();
        if (null === $baseDevice) {
            throw BusinessLogicException::configurationError('Base device is required');
        }

        $deviceCode = $baseDevice->getCode();
        // 使用设备锁防止并发心跳处理
        $lockKey = sprintf('device_heartbeat:%s', $deviceCode);

        $this->lockService->blockingRun($lockKey, function () use ($device, $autoJsVersion, $deviceInfo, $monitorData, $deviceCode): void {
            try {
                // 更新设备状态
                $this->updateDeviceStatus($device, $autoJsVersion, $deviceInfo);

                // 保存监控数据
                if ([] !== $monitorData) {
                    $this->saveMonitorData($device, $monitorData);
                }

                // 更新Redis中的心跳记录
                $this->updateHeartbeatInRedis($deviceCode);

                $this->logger->info('设备心跳处理成功', [
                    'deviceCode' => $deviceCode,
                    'deviceId' => $device->getId(),
                ]);
            } catch (\Exception $e) {
                $this->logger->error('处理设备心跳失败', [
                    'deviceId' => $device->getId(),
                    'error' => $e->getMessage(),
                    'exception' => $e,
                ]);

                throw BusinessLogicException::configurationError('处理设备心跳失败: ' . $e->getMessage());
            }
        });
    }

    /**
     * 更新设备状态信息.
     *
     * @param array<string, mixed> $deviceInfo
     */
    private function updateDeviceStatus(
        AutoJsDevice $device,
        ?string $autoJsVersion = null,
        array $deviceInfo = [],
    ): void {
        $baseDevice = $device->getBaseDevice();
        if (null === $baseDevice) {
            throw BusinessLogicException::configurationError('Base device is required');
        }

        // 更新基础设备信息
        $baseDevice->setStatus(DeviceStatus::ONLINE);
        $baseDevice->setLastOnlineTime(new \DateTimeImmutable());

        // 更新设备信息
        if ([] !== $deviceInfo) {
            $this->updateBaseDeviceInfo($baseDevice, $deviceInfo);
        }

        // 更新Auto.js版本
        if (null !== $autoJsVersion) {
            $device->setAutoJsVersion($autoJsVersion);
        }

        $this->entityManager->persist($baseDevice);
        $this->entityManager->persist($device);
        $this->entityManager->flush();
    }

    /**
     * 更新基础设备信息.
     *
     * @param array<string, mixed> $deviceInfo
     */
    private function updateBaseDeviceInfo(Device $baseDevice, array $deviceInfo): void
    {
        $this->updateDeviceStringFields($baseDevice, $deviceInfo);
        $this->updateDeviceNumericFields($baseDevice, $deviceInfo);
    }

    /**
     * @param array<string, mixed> $deviceInfo
     */
    private function updateDeviceStringFields(Device $baseDevice, array $deviceInfo): void
    {
        if (array_key_exists('model', $deviceInfo) && (is_string($deviceInfo['model']) || null === $deviceInfo['model'])) {
            $baseDevice->setModel($deviceInfo['model']);
        }
        if (array_key_exists('brand', $deviceInfo) && (is_string($deviceInfo['brand']) || null === $deviceInfo['brand'])) {
            $baseDevice->setBrand($deviceInfo['brand']);
        }
        if (array_key_exists('osVersion', $deviceInfo) && (is_string($deviceInfo['osVersion']) || null === $deviceInfo['osVersion'])) {
            $baseDevice->setOsVersion($deviceInfo['osVersion']);
        }
    }

    /**
     * @param array<string, mixed> $deviceInfo
     */
    private function updateDeviceNumericFields(Device $baseDevice, array $deviceInfo): void
    {
        if (isset($deviceInfo['cpuCores']) && is_numeric($deviceInfo['cpuCores'])) {
            $baseDevice->setCpuCores((int) $deviceInfo['cpuCores']);
        }
        if (isset($deviceInfo['memorySize']) && is_scalar($deviceInfo['memorySize'])) {
            $baseDevice->setMemorySize((string) $deviceInfo['memorySize']);
        }
        if (isset($deviceInfo['storageSize']) && is_scalar($deviceInfo['storageSize'])) {
            $baseDevice->setStorageSize((string) $deviceInfo['storageSize']);
        }
    }

    /**
     * 保存监控数据.
     *
     * @param array<string, mixed> $monitorData
     */
    private function saveMonitorData(AutoJsDevice $device, array $monitorData): void
    {
        $data = new DeviceMonitorData();
        $data->setAutoJsDevice($device);
        $data->setMonitorTime(new \DateTimeImmutable());

        // 设置监控指标
        $this->setMonitoringMetrics($data, $monitorData);

        // 保存额外数据
        $data->setAdditionalData($monitorData);

        $this->entityManager->persist($data);
        $this->entityManager->flush();

        // 更新Redis中的实时指标
        $baseDevice = $device->getBaseDevice();
        if (null !== $baseDevice) {
            $this->updateMetricsInRedis($baseDevice->getCode(), $monitorData);
        }
    }

    /**
     * @param array<string, mixed> $monitorData
     */
    private function setMonitoringMetrics(DeviceMonitorData $data, array $monitorData): void
    {
        $this->setMonitoringNumericMetrics($data, $monitorData);
        $this->setMonitoringStringMetrics($data, $monitorData);
    }

    /**
     * @param array<string, mixed> $monitorData
     */
    private function setMonitoringNumericMetrics(DeviceMonitorData $data, array $monitorData): void
    {
        if (isset($monitorData['cpuUsage']) && is_numeric($monitorData['cpuUsage'])) {
            $data->setCpuUsage((float) $monitorData['cpuUsage']);
        }
        if (isset($monitorData['memoryUsage']) && is_numeric($monitorData['memoryUsage'])) {
            $data->setMemoryUsage((float) $monitorData['memoryUsage']);
        }
        if (isset($monitorData['availableStorage']) && is_numeric($monitorData['availableStorage'])) {
            $data->setAvailableStorage((int) $monitorData['availableStorage']);
        }
        if (isset($monitorData['batteryLevel']) && is_numeric($monitorData['batteryLevel'])) {
            $data->setBatteryLevel((int) $monitorData['batteryLevel']);
        }
    }

    /**
     * @param array<string, mixed> $monitorData
     */
    private function setMonitoringStringMetrics(DeviceMonitorData $data, array $monitorData): void
    {
        if (array_key_exists('networkType', $monitorData) && (is_string($monitorData['networkType']) || null === $monitorData['networkType'])) {
            $data->setNetworkType($monitorData['networkType']);
        }
    }

    /**
     * 更新Redis中的心跳记录.
     */
    private function updateHeartbeatInRedis(string $deviceCode): void
    {
        // 更新最后心跳时间
        $this->cacheStorage->setDeviceHeartbeat($deviceCode, time());

        // 更新在线状态
        $this->cacheStorage->setDeviceOnline($deviceCode, true);
    }

    /**
     * 更新Redis中的实时指标.
     *
     * @param array<string, mixed> $metrics
     */
    private function updateMetricsInRedis(string $deviceCode, array $metrics): void
    {
        $metricsKey = RedisQueueKeys::getDeviceMetrics($deviceCode);

        // 转换为Redis hash格式
        $redisData = [];
        foreach ($metrics as $key => $value) {
            if (is_array($value)) {
                $encoded = json_encode($value);
                if (false === $encoded) {
                    throw new InvalidArgumentException('JSON encoding of metrics data failed');
                }
                $redisData[$key] = $encoded;
            } elseif (is_string($value) || is_numeric($value) || is_bool($value) || null === $value) {
                $redisData[$key] = (string) $value;
            }
        }

        // 添加时间戳
        $redisData['lastUpdate'] = (string) time();

        // 保存到Redis
        $this->cacheStorage->updateDeviceMetrics($deviceCode, $redisData);
    }

    /**
     * 检查设备是否在线
     *
     * @param string $deviceCode 设备代码
     *
     * @return bool 是否在线
     */
    public function isDeviceOnline(string $deviceCode): bool
    {
        $lastOnlineTime = $this->cacheStorage->getDeviceOnline($deviceCode);

        if (null === $lastOnlineTime) {
            return false;
        }

        // 检查是否超过在线超时时间（2分钟）
        return (time() - $lastOnlineTime) < RedisQueueKeys::TTL_ONLINE_STATUS;
    }

    /**
     * 获取设备实时指标.
     *
     * @param string $deviceCode 设备代码
     *
     * @return array<string, mixed> 指标数据
     */
    public function getDeviceMetrics(string $deviceCode): array
    {
        $metrics = $this->cacheStorage->getDeviceMetrics($deviceCode);

        if ([] === $metrics) {
            return [];
        }

        // 解析JSON数据
        foreach ($metrics as $key => $value) {
            if (is_string($value) && $this->isJson($value)) {
                $decoded = json_decode($value, true);
                $metrics[$key] = (null !== $decoded && false !== $decoded) ? $decoded : $value;
            }
        }

        return $metrics;
    }

    /**
     * 批量检查设备在线状态
     *
     * @param array<string> $deviceCodes 设备代码列表
     *
     * @return array<string, bool> 设备在线状态映射
     */
    public function checkDevicesOnlineStatus(array $deviceCodes): array
    {
        $result = [];

        foreach ($deviceCodes as $deviceCode) {
            $result[$deviceCode] = $this->isDeviceOnline($deviceCode);
        }

        return $result;
    }

    /**
     * 标记设备离线
     *
     * @param string $deviceCode 设备代码
     */
    public function markDeviceOffline(string $deviceCode): void
    {
        // 删除Redis中的在线状态
        $this->cacheStorage->setDeviceOnline($deviceCode, false);

        $this->logger->info('设备已标记为离线', [
            'deviceCode' => $deviceCode,
        ]);
    }

    /**
     * 检查是否为有效的JSON字符串.
     */
    private function isJson(string $string): bool
    {
        json_decode($string);

        return JSON_ERROR_NONE === json_last_error();
    }
}
