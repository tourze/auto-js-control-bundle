<?php

declare(strict_types=1);

namespace Tourze\AutoJsControlBundle\Entity;

use DeviceBundle\Entity\Device as BaseDevice;
use DeviceBundle\Enum\DeviceStatus;
use DeviceBundle\Enum\DeviceType;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Tourze\AutoJsControlBundle\Repository\AutoJsDeviceRepository;
use Tourze\DoctrineTimestampBundle\Traits\TimestampableAware;
use Tourze\LockServiceBundle\Model\LockEntity;

#[ORM\Entity(repositoryClass: AutoJsDeviceRepository::class)]
#[ORM\Table(name: 'auto_js_device_extension', options: ['comment' => 'Auto.js设备扩展信息表'])]
#[ORM\Index(columns: ['base_device_id', 'id'], name: 'auto_js_device_extension_auto_js_device_ext_idx_base_device')]
#[ORM\Index(columns: ['device_group_id', 'id'], name: 'auto_js_device_extension_auto_js_device_ext_idx_device_group')]
class AutoJsDevice implements \Stringable, LockEntity
{
    use TimestampableAware;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::BIGINT, options: ['comment' => '主键ID'])]
    private ?int $id = null;

    #[ORM\OneToOne(targetEntity: BaseDevice::class, cascade: ['persist'])]
    #[ORM\JoinColumn(name: 'base_device_id', nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull(message: '基础设备不能为空')]
    private ?BaseDevice $baseDevice = null;

    #[ORM\Column(type: Types::STRING, length: 50, nullable: true, options: ['comment' => 'Auto.js版本'])]
    #[Assert\Length(max: 50, maxMessage: 'Auto.js版本长度不能超过 {{ limit }} 个字符')]
    private ?string $autoJsVersion = null;

    #[ORM\Column(type: Types::TEXT, nullable: true, options: ['comment' => '设备证书'])]
    #[Assert\Length(max: 65535, maxMessage: '设备证书长度不能超过 {{ limit }} 个字符')]
    private ?string $certificate = null;

    #[ORM\Column(type: Types::STRING, length: 500, nullable: true, options: ['comment' => 'WebSocket连接标识'])]
    #[Assert\Length(max: 500, maxMessage: 'WebSocket连接标识长度不能超过 {{ limit }} 个字符')]
    private ?string $wsConnectionId = null;

    #[ORM\ManyToOne(targetEntity: DeviceGroup::class, inversedBy: 'autoJsDevices')]
    #[ORM\JoinColumn(name: 'device_group_id', referencedColumnName: 'id', onDelete: 'SET NULL')]
    private ?DeviceGroup $deviceGroup = null;

    /**
     * @var Collection<int, DeviceLog>
     */
    #[ORM\OneToMany(targetEntity: DeviceLog::class, mappedBy: 'autoJsDevice', fetch: 'EXTRA_LAZY')]
    private Collection $deviceLogs;

    /**
     * @var Collection<int, ScriptExecutionRecord>
     */
    #[ORM\OneToMany(targetEntity: ScriptExecutionRecord::class, mappedBy: 'autoJsDevice', fetch: 'EXTRA_LAZY')]
    private Collection $scriptExecutionRecords;

    /**
     * @var Collection<int, DeviceMonitorData>
     */
    #[ORM\OneToMany(targetEntity: DeviceMonitorData::class, mappedBy: 'autoJsDevice', fetch: 'EXTRA_LAZY')]
    private Collection $monitorData;

    /**
     * @var Collection<int, WebSocketMessage>
     */
    #[ORM\OneToMany(targetEntity: WebSocketMessage::class, mappedBy: 'autoJsDevice', fetch: 'EXTRA_LAZY')]
    private Collection $webSocketMessages;

    #[ORM\Column(type: Types::BOOLEAN, options: ['comment' => '是否具有Root权限', 'default' => false])]
    #[Assert\NotNull(message: 'Root权限状态不能为空')]
    private bool $rootAccess = false;

    #[ORM\Column(type: Types::BOOLEAN, options: ['comment' => '无障碍服务是否启用', 'default' => false])]
    #[Assert\NotNull(message: '无障碍服务状态不能为空')]
    private bool $accessibilityEnabled = false;

    #[ORM\Column(type: Types::BOOLEAN, options: ['comment' => '悬浮窗权限是否开启', 'default' => false])]
    #[Assert\NotNull(message: '悬浮窗权限状态不能为空')]
    private bool $floatingWindowEnabled = false;

    #[ORM\Column(type: Types::TEXT, nullable: true, options: ['comment' => '设备支持的功能列表'])]
    #[Assert\Length(max: 65535, maxMessage: '设备能力描述长度不能超过 {{ limit }} 个字符')]
    private ?string $capabilities = null;

    #[ORM\Column(type: Types::TEXT, nullable: true, options: ['comment' => '设备的JSON配置信息'])]
    #[Assert\Length(max: 65535, maxMessage: '设备配置信息长度不能超过 {{ limit }} 个字符')]
    private ?string $configuration = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true, options: ['comment' => '设备同时执行的最大任务数'])]
    #[Assert\Range(min: 1, max: 100, notInRangeMessage: '最大并发任务数必须在 {{ min }} 到 {{ max }} 之间')]
    private ?int $maxConcurrentTasks = null;

    #[ORM\Column(type: Types::STRING, length: 50, nullable: true, options: ['comment' => '设备屏幕分辨率'])]
    #[Assert\Length(max: 50, maxMessage: '屏幕分辨率长度不能超过 {{ limit }} 个字符')]
    #[Assert\Regex(pattern: '/^\d+x\d+$/', message: '屏幕分辨率格式应为宽x高，例如：1920x1080')]
    private ?string $screenResolution = null;

    #[ORM\Column(type: Types::STRING, length: 50, nullable: true, options: ['comment' => '设备Android系统版本'])]
    #[Assert\Length(max: 50, maxMessage: 'Android版本长度不能超过 {{ limit }} 个字符')]
    private ?string $androidVersion = null;

    public function __construct()
    {
        $this->deviceLogs = new ArrayCollection();
        $this->scriptExecutionRecords = new ArrayCollection();
        $this->monitorData = new ArrayCollection();
        $this->webSocketMessages = new ArrayCollection();
    }

    public function __toString(): string
    {
        if (null !== $this->baseDevice) {
            return sprintf('Auto.js %s (%s)', $this->baseDevice->getName() ?? '未命名设备', $this->baseDevice->getCode());
        }

        return 'Auto.js设备 #' . ($this->id ?? 'new');
    }

    public function getLockEntityId(): string
    {
        return (string) $this->id;
    }

    public function retrieveLockResource(): string
    {
        return 'auto_js_device:' . ($this->id ?? 'new');
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getBaseDevice(): ?BaseDevice
    {
        return $this->baseDevice;
    }

    public function setBaseDevice(?BaseDevice $baseDevice): void
    {
        $this->baseDevice = $baseDevice;
    }

    public function getAutoJsVersion(): ?string
    {
        return $this->autoJsVersion;
    }

    public function setAutoJsVersion(?string $autoJsVersion): void
    {
        $this->autoJsVersion = $autoJsVersion;
    }

    public function getCertificate(): ?string
    {
        return $this->certificate;
    }

    public function setCertificate(?string $certificate): void
    {
        $this->certificate = $certificate;
    }

    public function getWsConnectionId(): ?string
    {
        return $this->wsConnectionId;
    }

    public function setWsConnectionId(?string $wsConnectionId): void
    {
        $this->wsConnectionId = $wsConnectionId;
    }

    public function getDeviceGroup(): ?DeviceGroup
    {
        return $this->deviceGroup;
    }

    public function setDeviceGroup(?DeviceGroup $deviceGroup): void
    {
        $this->deviceGroup = $deviceGroup;
    }

    /**
     * @return Collection<int, DeviceLog>
     */
    public function getDeviceLogs(): Collection
    {
        return $this->deviceLogs;
    }

    public function addDeviceLog(DeviceLog $deviceLog): void
    {
        if (!$this->deviceLogs->contains($deviceLog)) {
            $this->deviceLogs->add($deviceLog);
            $deviceLog->setAutoJsDevice($this);
        }
    }

    public function removeDeviceLog(DeviceLog $deviceLog): void
    {
        if ($this->deviceLogs->removeElement($deviceLog)) {
            if ($deviceLog->getAutoJsDevice() === $this) {
                $deviceLog->setAutoJsDevice(null);
            }
        }
    }

    /**
     * @return Collection<int, ScriptExecutionRecord>
     */
    public function getScriptExecutionRecords(): Collection
    {
        return $this->scriptExecutionRecords;
    }

    public function addScriptExecutionRecord(ScriptExecutionRecord $scriptExecutionRecord): void
    {
        if (!$this->scriptExecutionRecords->contains($scriptExecutionRecord)) {
            $this->scriptExecutionRecords->add($scriptExecutionRecord);
            $scriptExecutionRecord->setAutoJsDevice($this);
        }
    }

    public function removeScriptExecutionRecord(ScriptExecutionRecord $scriptExecutionRecord): void
    {
        if ($this->scriptExecutionRecords->removeElement($scriptExecutionRecord)) {
            if ($scriptExecutionRecord->getAutoJsDevice() === $this) {
                $scriptExecutionRecord->setAutoJsDevice(null);
            }
        }
    }

    /**
     * @return Collection<int, DeviceMonitorData>
     */
    public function getMonitorData(): Collection
    {
        return $this->monitorData;
    }

    public function addMonitorData(DeviceMonitorData $monitorData): void
    {
        if (!$this->monitorData->contains($monitorData)) {
            $this->monitorData->add($monitorData);
            $monitorData->setAutoJsDevice($this);
        }
    }

    public function removeMonitorData(DeviceMonitorData $monitorData): void
    {
        if ($this->monitorData->removeElement($monitorData)) {
            if ($monitorData->getAutoJsDevice() === $this) {
                $monitorData->setAutoJsDevice(null);
            }
        }
    }

    /**
     * @return Collection<int, WebSocketMessage>
     */
    public function getWebSocketMessages(): Collection
    {
        return $this->webSocketMessages;
    }

    public function addWebSocketMessage(WebSocketMessage $webSocketMessage): void
    {
        if (!$this->webSocketMessages->contains($webSocketMessage)) {
            $this->webSocketMessages->add($webSocketMessage);
            $webSocketMessage->setAutoJsDevice($this);
        }
    }

    public function removeWebSocketMessage(WebSocketMessage $webSocketMessage): void
    {
        if ($this->webSocketMessages->removeElement($webSocketMessage)) {
            if ($webSocketMessage->getAutoJsDevice() === $this) {
                $webSocketMessage->setAutoJsDevice(null);
            }
        }
    }

    /**
     * 便捷方法：获取设备代码
     */
    public function getDeviceCode(): string
    {
        return null !== $this->baseDevice ? $this->baseDevice->getCode() : '';
    }

    /**
     * 便捷方法：获取设备名称.
     */
    public function getDeviceName(): ?string
    {
        return $this->baseDevice?->getName();
    }

    /**
     * 便捷方法：获取设备型号.
     */
    public function getDeviceModel(): ?string
    {
        return $this->baseDevice?->getModel();
    }

    /**
     * 便捷方法：获取设备类型.
     */
    public function getDeviceType(): ?DeviceType
    {
        return $this->baseDevice?->getDeviceType();
    }

    /**
     * 便捷方法：获取操作系统版本.
     */
    public function getOsVersion(): ?string
    {
        return $this->baseDevice?->getOsVersion();
    }

    /**
     * 便捷方法：获取设备品牌.
     */
    public function getBrand(): ?string
    {
        return $this->baseDevice?->getBrand();
    }

    /**
     * 便捷方法：获取设备状态
     */
    public function getStatus(): DeviceStatus
    {
        return $this->baseDevice?->getStatus() ?? DeviceStatus::OFFLINE;
    }

    /**
     * 便捷方法：获取设备状态标签（用于EasyAdmin显示）
     */
    public function getDeviceStatus(): string
    {
        $status = $this->getStatus();

        return $status->getLabel();
    }

    /**
     * 便捷方法：获取最后在线时间.
     */
    public function getLastOnlineTime(): ?\DateTimeImmutable
    {
        return $this->baseDevice?->getLastOnlineTime();
    }

    /**
     * 便捷方法：获取最后连接IP.
     */
    public function getLastIp(): ?string
    {
        return $this->baseDevice?->getLastIp();
    }

    /**
     * 便捷方法：获取设备指纹.
     */
    public function getFingerprint(): ?string
    {
        return $this->baseDevice?->getFingerprint();
    }

    /**
     * 便捷方法：获取CPU核心数.
     */
    public function getCpuCores(): int
    {
        return $this->baseDevice?->getCpuCores() ?? 0;
    }

    /**
     * 便捷方法：获取内存大小.
     */
    public function getMemorySize(): string
    {
        return $this->baseDevice?->getMemorySize() ?? '0';
    }

    /**
     * 便捷方法：获取存储空间大小.
     */
    public function getStorageSize(): string
    {
        return $this->baseDevice?->getStorageSize() ?? '0';
    }

    /**
     * 便捷方法：获取备注.
     */
    public function getRemark(): ?string
    {
        return $this->baseDevice?->getRemark();
    }

    /**
     * 便捷方法：检查是否在线
     */
    public function isOnline(): bool
    {
        return null !== $this->baseDevice && $this->baseDevice->isOnline();
    }

    /**
     * 便捷方法：检查是否启用.
     */
    public function isEnabled(): bool
    {
        return null !== $this->baseDevice && $this->baseDevice->isEnabled();
    }

    /**
     * 别名方法：获取设备ID（测试兼容性）.
     */
    public function getDeviceId(): ?int
    {
        return $this->getId();
    }

    /**
     * 别名方法：获取设备名称（测试兼容性）.
     */
    public function getName(): ?string
    {
        return $this->getDeviceName();
    }

    /**
     * 别名方法：获取最后心跳时间（EventSubscriber兼容性）.
     *
     * @deprecated 使用 getLastOnlineTime() 代替
     */
    public function getLastHeartbeatAt(): ?\DateTimeImmutable
    {
        return $this->getLastOnlineTime();
    }

    /**
     * 别名方法：获取最后心跳时间.
     */
    public function getLastHeartbeatTime(): ?\DateTimeImmutable
    {
        return $this->getLastOnlineTime();
    }

    public function isRootAccess(): bool
    {
        return $this->rootAccess;
    }

    public function setRootAccess(bool $rootAccess): void
    {
        $this->rootAccess = $rootAccess;
    }

    public function isAccessibilityEnabled(): bool
    {
        return $this->accessibilityEnabled;
    }

    public function setAccessibilityEnabled(bool $accessibilityEnabled): void
    {
        $this->accessibilityEnabled = $accessibilityEnabled;
    }

    public function isFloatingWindowEnabled(): bool
    {
        return $this->floatingWindowEnabled;
    }

    public function setFloatingWindowEnabled(bool $floatingWindowEnabled): void
    {
        $this->floatingWindowEnabled = $floatingWindowEnabled;
    }

    public function getCapabilities(): ?string
    {
        return $this->capabilities;
    }

    public function setCapabilities(?string $capabilities): void
    {
        $this->capabilities = $capabilities;
    }

    public function getConfiguration(): ?string
    {
        return $this->configuration;
    }

    public function setConfiguration(?string $configuration): void
    {
        $this->configuration = $configuration;
    }

    public function getMaxConcurrentTasks(): ?int
    {
        return $this->maxConcurrentTasks;
    }

    public function setMaxConcurrentTasks(?int $maxConcurrentTasks): void
    {
        $this->maxConcurrentTasks = $maxConcurrentTasks;
    }

    public function getScreenResolution(): ?string
    {
        return $this->screenResolution;
    }

    public function setScreenResolution(?string $screenResolution): void
    {
        $this->screenResolution = $screenResolution;
    }

    public function getAndroidVersion(): ?string
    {
        return $this->androidVersion;
    }

    public function setAndroidVersion(?string $androidVersion): void
    {
        $this->androidVersion = $androidVersion;
    }

    /**
     * 别名方法：为EasyAdmin布尔字段提供getter支持
     */
    public function getRootAccess(): bool
    {
        return $this->rootAccess;
    }

    /**
     * 别名方法：为EasyAdmin布尔字段提供getter支持
     */
    public function getAccessibilityEnabled(): bool
    {
        return $this->accessibilityEnabled;
    }

    /**
     * 别名方法：为EasyAdmin布尔字段提供getter支持
     */
    public function getFloatingWindowEnabled(): bool
    {
        return $this->floatingWindowEnabled;
    }
}
