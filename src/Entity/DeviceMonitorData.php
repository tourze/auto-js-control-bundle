<?php

declare(strict_types=1);

namespace Tourze\AutoJsControlBundle\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Tourze\AutoJsControlBundle\Repository\DeviceMonitorDataRepository;
use Tourze\DoctrineTimestampBundle\Traits\CreateTimeAware;

#[ORM\Entity(repositoryClass: DeviceMonitorDataRepository::class)]
#[ORM\Table(name: 'auto_js_device_monitor_data', options: ['comment' => '设备监控数据表'])]
#[ORM\Index(columns: ['auto_js_device_id', 'create_time'], name: 'auto_js_device_monitor_data_auto_js_monitor_idx_device_time')]
class DeviceMonitorData implements \Stringable
{
    use CreateTimeAware {
        CreateTimeAware::getCreateTime as private baseGetCreateTime;
        CreateTimeAware::setCreateTime as private baseSetCreateTime;
    }

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::BIGINT, options: ['comment' => '主键ID'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: AutoJsDevice::class, inversedBy: 'monitorData', cascade: ['persist'])]
    #[ORM\JoinColumn(name: 'auto_js_device_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull(message: '设备不能为空')]
    private ?AutoJsDevice $autoJsDevice = null;

    #[ORM\Column(type: Types::FLOAT, options: ['comment' => 'CPU使用率（百分比）', 'default' => 0])]
    #[Assert\Type(type: 'float', message: 'CPU使用率必须是浮点数')]
    #[Assert\Range(min: 0, max: 100, notInRangeMessage: 'CPU使用率必须在 {{ min }}% 到 {{ max }}% 之间')]
    private float $cpuUsage = 0;

    #[ORM\Column(type: Types::BIGINT, options: ['comment' => '内存使用量（MB）', 'default' => 0])]
    #[Assert\Type(type: 'string', message: '内存使用量必须是字符串')]
    #[Assert\Regex(pattern: '/^\d+$/', message: '内存使用量必须是正整数')]
    #[Assert\Length(max: 20, maxMessage: '内存使用量长度不能超过 {{ limit }} 个字符')]
    private string $memoryUsed = '0';

    #[ORM\Column(type: Types::BIGINT, options: ['comment' => '内存总量（MB）', 'default' => 0])]
    #[Assert\Type(type: 'string', message: '内存总量必须是字符串')]
    #[Assert\Regex(pattern: '/^\d+$/', message: '内存总量必须是正整数')]
    #[Assert\Length(max: 20, maxMessage: '内存总量长度不能超过 {{ limit }} 个字符')]
    private string $memoryTotal = '0';

    #[ORM\Column(type: Types::BIGINT, options: ['comment' => '存储使用量（MB）', 'default' => 0])]
    #[Assert\Type(type: 'string', message: '存储使用量必须是字符串')]
    #[Assert\Regex(pattern: '/^\d+$/', message: '存储使用量必须是正整数')]
    #[Assert\Length(max: 20, maxMessage: '存储使用量长度不能超过 {{ limit }} 个字符')]
    private string $storageUsed = '0';

    #[ORM\Column(type: Types::BIGINT, options: ['comment' => '存储总量（MB）', 'default' => 0])]
    #[Assert\Type(type: 'string', message: '存储总量必须是字符串')]
    #[Assert\Regex(pattern: '/^\d+$/', message: '存储总量必须是正整数')]
    #[Assert\Length(max: 20, maxMessage: '存储总量长度不能超过 {{ limit }} 个字符')]
    private string $storageTotal = '0';

    #[ORM\Column(type: Types::FLOAT, options: ['comment' => '电池电量（百分比）', 'default' => 0])]
    #[Assert\Type(type: 'float', message: '电池电量必须是浮点数')]
    #[Assert\Range(min: 0, max: 100, notInRangeMessage: '电池电量必须在 {{ min }}% 到 {{ max }}% 之间')]
    private float $batteryLevel = 0;

    #[ORM\Column(type: Types::BOOLEAN, options: ['comment' => '是否正在充电'])]
    #[Assert\Type(type: 'bool', message: '是否正在充电必须是布尔值')]
    private bool $isCharging = false;

    #[ORM\Column(type: Types::FLOAT, options: ['comment' => '设备温度（摄氏度）', 'default' => 0])]
    #[Assert\Type(type: 'float', message: '设备温度必须是浮点数')]
    #[Assert\Range(min: -50, max: 150, notInRangeMessage: '设备温度必须在 {{ min }}°C 到 {{ max }}°C 之间')]
    private float $temperature = 0;

    #[ORM\Column(type: Types::INTEGER, options: ['comment' => '网络延迟（毫秒）', 'default' => 0])]
    #[Assert\Type(type: 'integer', message: '网络延迟必须是整数')]
    #[Assert\GreaterThanOrEqual(value: 0, message: '网络延迟不能为负数')]
    private int $networkLatency = 0;

    #[ORM\Column(type: Types::STRING, length: 20, nullable: true, options: ['comment' => '网络类型：wifi/4g/5g/ethernet'])]
    #[Assert\Length(max: 20, maxMessage: '网络类型长度不能超过 {{ limit }} 个字符')]
    #[Assert\Choice(choices: ['wifi', '2g', '3g', '4g', '5g', 'ethernet', null], message: '网络类型必须是有效值')]
    private ?string $networkType = null;

    #[ORM\Column(type: Types::INTEGER, options: ['comment' => '运行脚本数量', 'default' => 0])]
    #[Assert\Type(type: 'integer', message: '运行脚本数量必须是整数')]
    #[Assert\GreaterThanOrEqual(value: 0, message: '运行脚本数量不能为负数')]
    private int $runningScripts = 0;

    #[ORM\Column(type: Types::TEXT, nullable: true, options: ['comment' => '扩展监控数据（JSON格式）'])]
    #[Assert\Length(max: 65535, maxMessage: '扩展监控数据长度不能超过 {{ limit }} 个字符')]
    #[Assert\Json(message: '扩展监控数据必须是有效的JSON格式')]
    private ?string $extraData = null;

    public function __toString(): string
    {
        return sprintf('监控数据 %s', $this->createTime?->format('Y-m-d H:i:s') ?? 'new');
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAutoJsDevice(): ?AutoJsDevice
    {
        return $this->autoJsDevice;
    }

    public function setAutoJsDevice(?AutoJsDevice $autoJsDevice): void
    {
        $this->autoJsDevice = $autoJsDevice;
    }

    public function getCpuUsage(): float
    {
        return $this->cpuUsage;
    }

    public function setCpuUsage(float $cpuUsage): void
    {
        $this->cpuUsage = $cpuUsage;
    }

    public function getMemoryUsed(): string
    {
        return $this->memoryUsed;
    }

    public function setMemoryUsed(string $memoryUsed): void
    {
        $this->memoryUsed = $memoryUsed;
    }

    public function getMemoryTotal(): string
    {
        return $this->memoryTotal;
    }

    public function setMemoryTotal(string $memoryTotal): void
    {
        $this->memoryTotal = $memoryTotal;
    }

    public function getStorageUsed(): string
    {
        return $this->storageUsed;
    }

    public function setStorageUsed(string $storageUsed): void
    {
        $this->storageUsed = $storageUsed;
    }

    public function getStorageTotal(): string
    {
        return $this->storageTotal;
    }

    public function setStorageTotal(string $storageTotal): void
    {
        $this->storageTotal = $storageTotal;
    }

    public function getBatteryLevel(): float
    {
        return $this->batteryLevel;
    }

    public function setBatteryLevel(float $batteryLevel): void
    {
        $this->batteryLevel = $batteryLevel;
    }

    public function isCharging(): bool
    {
        return $this->isCharging;
    }

    public function setIsCharging(bool $isCharging): void
    {
        $this->isCharging = $isCharging;
    }

    public function getTemperature(): float
    {
        return $this->temperature;
    }

    public function setTemperature(float $temperature): void
    {
        $this->temperature = $temperature;
    }

    public function getNetworkLatency(): int
    {
        return $this->networkLatency;
    }

    public function setNetworkLatency(int $networkLatency): void
    {
        $this->networkLatency = $networkLatency;
    }

    public function getNetworkType(): ?string
    {
        return $this->networkType;
    }

    public function setNetworkType(?string $networkType): void
    {
        $this->networkType = $networkType;
    }

    public function getRunningScripts(): int
    {
        return $this->runningScripts;
    }

    public function setRunningScripts(int $runningScripts): void
    {
        $this->runningScripts = $runningScripts;
    }

    public function getExtraData(): ?string
    {
        return $this->extraData;
    }

    public function setExtraData(?string $extraData): void
    {
        $this->extraData = $extraData;
    }

    public function getCreateTime(): ?\DateTimeImmutable
    {
        return $this->baseGetCreateTime();
    }

    public function setCreateTime(\DateTimeImmutable $createTime): void
    {
        $this->baseSetCreateTime($createTime);
    }

    // 便捷方法：设置监控时间（兼容 setMonitorTime）
    public function setMonitorTime(\DateTimeImmutable $monitorTime): void
    {
        $this->baseSetCreateTime($monitorTime);
    }

    // 便捷方法：设置内存使用率（兼容 setMemoryUsage）
    public function setMemoryUsage(float $memoryUsage): void
    {
        // 计算内存使用量（假设总内存为基数）
        if ($this->memoryTotal > 0) {
            $this->memoryUsed = (string) ((int) ($memoryUsage / 100 * (float) $this->memoryTotal));
        }
    }

    // 便捷方法：设置可用存储（兼容 setAvailableStorage）
    public function setAvailableStorage(int $availableStorage): void
    {
        // 假设可用存储就是总存储减去已用存储
        if ($this->storageTotal > 0) {
            $this->storageUsed = (string) ((int) $this->storageTotal - $availableStorage);
        }
    }

    // 便捷方法：设置附加数据（兼容 setAdditionalData）
    /**
     * @param array<string, mixed>|null $additionalData
     */
    public function setAdditionalData(?array $additionalData): void
    {
        $this->extraData = is_array($additionalData) ? json_encode($additionalData, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) : null;
    }
}
