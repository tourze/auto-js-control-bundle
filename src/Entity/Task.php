<?php

declare(strict_types=1);

namespace Tourze\AutoJsControlBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Tourze\AutoJsControlBundle\Enum\TaskStatus;
use Tourze\AutoJsControlBundle\Enum\TaskTargetType;
use Tourze\AutoJsControlBundle\Enum\TaskType;
use Tourze\AutoJsControlBundle\Exception\BusinessLogicException;
use Tourze\AutoJsControlBundle\Repository\TaskRepository;
use Tourze\DoctrineIndexedBundle\Attribute\IndexColumn;
use Tourze\DoctrineTimestampBundle\Traits\TimestampableAware;
use Tourze\DoctrineUserBundle\Traits\BlameableAware;
use Tourze\LockServiceBundle\Model\LockEntity;

#[ORM\Entity(repositoryClass: TaskRepository::class)]
#[ORM\Table(name: 'auto_js_task', options: ['comment' => '任务管理表'])]
#[ORM\Index(name: 'auto_js_task_idx_type_status', columns: ['task_type', 'status'])]
#[ORM\Index(name: 'auto_js_task_idx_priority_status', columns: ['priority', 'status'])]
class Task implements \Stringable, LockEntity
{
    use TimestampableAware;
    use BlameableAware;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::BIGINT, options: ['comment' => '主键ID'])]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 200, options: ['comment' => '任务名称'])]
    #[Assert\NotBlank(message: '任务名称不能为空')]
    #[Assert\Length(max: 200, maxMessage: '任务名称长度不能超过200个字符')]
    private ?string $name = null;

    #[ORM\Column(type: Types::TEXT, nullable: true, options: ['comment' => '任务描述'])]
    #[Assert\Length(max: 65535, maxMessage: '任务描述长度不能超过 {{ limit }} 个字符')]
    private ?string $description = null;

    #[ORM\Column(type: Types::STRING, length: 20, enumType: TaskType::class, options: ['comment' => '任务类型'])]
    #[IndexColumn]
    #[Assert\NotNull(message: '任务类型不能为空')]
    #[Assert\Choice(callback: [TaskType::class, 'cases'], message: '任务类型必须是有效值')]
    private TaskType $taskType = TaskType::IMMEDIATE;

    #[ORM\Column(type: Types::STRING, length: 20, enumType: TaskStatus::class, options: ['comment' => '任务状态'])]
    #[IndexColumn]
    #[Assert\NotNull(message: '任务状态不能为空')]
    #[Assert\Choice(callback: [TaskStatus::class, 'cases'], message: '任务状态必须是有效值')]
    private TaskStatus $status = TaskStatus::PENDING;

    #[ORM\ManyToOne(targetEntity: Script::class, inversedBy: 'tasks', cascade: ['persist'])]
    #[ORM\JoinColumn(name: 'script_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull(message: '必须选择执行脚本')]
    private ?Script $script = null;

    #[ORM\Column(type: Types::TEXT, nullable: true, options: ['comment' => '任务参数（JSON格式）'])]
    #[Assert\Length(max: 65535, maxMessage: '任务参数长度不能超过 {{ limit }} 个字符')]
    #[Assert\Json(message: '任务参数必须是有效的JSON格式')]
    private ?string $parameters = null;

    #[ORM\Column(type: Types::STRING, length: 20, enumType: TaskTargetType::class, options: ['comment' => '目标设备选择方式'])]
    #[Assert\NotNull(message: '目标设备选择方式不能为空')]
    #[Assert\Choice(callback: [TaskTargetType::class, 'cases'], message: '目标设备选择方式必须是有效值')]
    private TaskTargetType $targetType = TaskTargetType::ALL;

    #[ORM\Column(type: Types::TEXT, nullable: true, options: ['comment' => '目标设备ID列表（JSON格式）'])]
    #[Assert\Length(max: 65535, maxMessage: '目标设备ID列表长度不能超过 {{ limit }} 个字符')]
    #[Assert\Json(message: '目标设备ID列表必须是有效的JSON格式')]
    private ?string $targetDevices = null;

    #[ORM\ManyToOne(targetEntity: DeviceGroup::class)]
    #[ORM\JoinColumn(name: 'target_group_id', referencedColumnName: 'id', onDelete: 'SET NULL')]
    private ?DeviceGroup $targetGroup = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true, options: ['comment' => '计划执行时间'])]
    #[IndexColumn]
    #[Assert\Type(type: \DateTimeImmutable::class, message: '计划执行时间必须是有效的日期时间')]
    private ?\DateTimeImmutable $scheduledTime = null;

    #[ORM\Column(type: Types::STRING, length: 100, nullable: true, options: ['comment' => 'Cron表达式（recurring类型）'])]
    #[Assert\Length(max: 100, maxMessage: 'Cron表达式长度不能超过 {{ limit }} 个字符')]
    private ?string $cronExpression = null;

    #[ORM\Column(type: Types::INTEGER, options: ['comment' => '优先级（数值越大优先级越高）', 'default' => 0])]
    #[IndexColumn]
    #[Assert\Type(type: 'integer', message: '优先级必须是整数')]
    private int $priority = 0;

    #[ORM\Column(type: Types::INTEGER, options: ['comment' => '重试次数', 'default' => 0])]
    #[Assert\Type(type: 'integer', message: '重试次数必须是整数')]
    #[Assert\PositiveOrZero(message: '重试次数不能为负数')]
    private int $retryCount = 0;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true, options: ['comment' => '开始执行时间'])]
    #[Assert\Type(type: \DateTimeImmutable::class, message: '开始执行时间必须是有效的日期时间')]
    private ?\DateTimeImmutable $startTime = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true, options: ['comment' => '结束执行时间'])]
    #[Assert\Type(type: \DateTimeImmutable::class, message: '结束执行时间必须是有效的日期时间')]
    private ?\DateTimeImmutable $endTime = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true, options: ['comment' => '最后执行时间（循环任务）'])]
    #[Assert\Type(type: \DateTimeImmutable::class, message: '最后执行时间必须是有效的日期时间')]
    private ?\DateTimeImmutable $lastExecutionTime = null;

    #[ORM\Column(type: Types::INTEGER, options: ['comment' => '最大重试次数', 'default' => 3])]
    #[Assert\Type(type: 'integer', message: '最大重试次数必须是整数')]
    #[Assert\GreaterThanOrEqual(value: 0, message: '最大重试次数不能为负数')]
    private int $maxRetries = 3;

    #[ORM\Column(type: Types::TEXT, nullable: true, options: ['comment' => '失败原因'])]
    #[Assert\Length(max: 65535, maxMessage: '失败原因长度不能超过 {{ limit }} 个字符')]
    private ?string $failureReason = null;

    #[ORM\Column(type: Types::INTEGER, options: ['comment' => '总设备数', 'default' => 0])]
    #[Assert\Type(type: 'integer', message: '总设备数必须是整数')]
    #[Assert\PositiveOrZero(message: '总设备数不能为负数')]
    private int $totalDevices = 0;

    #[ORM\Column(type: Types::INTEGER, options: ['comment' => '成功设备数', 'default' => 0])]
    #[Assert\Type(type: 'integer', message: '成功设备数必须是整数')]
    #[Assert\GreaterThanOrEqual(value: 0, message: '成功设备数不能为负数')]
    private int $successDevices = 0;

    #[ORM\Column(type: Types::INTEGER, options: ['comment' => '失败设备数', 'default' => 0])]
    #[Assert\Type(type: 'integer', message: '失败设备数必须是整数')]
    #[Assert\GreaterThanOrEqual(value: 0, message: '失败设备数不能为负数')]
    private int $failedDevices = 0;

    #[ORM\Column(type: Types::BOOLEAN, options: ['comment' => '是否启用'])]
    #[Assert\Type(type: 'bool', message: '是否启用必须是布尔值')]
    private bool $valid = true;

    /**
     * @var Collection<int, ScriptExecutionRecord>
     */
    #[ORM\OneToMany(targetEntity: ScriptExecutionRecord::class, mappedBy: 'task', fetch: 'EXTRA_LAZY')]
    private Collection $executionRecords;

    public function __construct()
    {
        $this->executionRecords = new ArrayCollection();
        $this->createTime = new \DateTimeImmutable();
        $this->updateTime = new \DateTimeImmutable();
    }

    public function __toString(): string
    {
        return sprintf('%s (%s)', $this->name ?? '未命名任务', $this->status->value);
    }

    public function getLockEntityId(): string
    {
        return (string) $this->id;
    }

    public function retrieveLockResource(): string
    {
        return 'task:' . ($this->id ?? 'new');
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    public function getTaskType(): TaskType
    {
        return $this->taskType;
    }

    public function setTaskType(TaskType $taskType): void
    {
        $this->taskType = $taskType;
    }

    /**
     * 获取任务类型（getTaskType 的别名）.
     */
    public function getType(): TaskType
    {
        return $this->taskType;
    }

    /**
     * 设置任务类型（setTaskType 的别名）.
     */
    public function setType(TaskType $type): void
    {
        $this->setTaskType($type);
    }

    public function getStatus(): TaskStatus
    {
        return $this->status;
    }

    public function setStatus(TaskStatus $status): void
    {
        $this->status = $status;
    }

    public function getScript(): ?Script
    {
        return $this->script;
    }

    public function setScript(?Script $script): void
    {
        $this->script = $script;
    }

    public function getParameters(): ?string
    {
        return $this->parameters;
    }

    public function setParameters(?string $parameters): void
    {
        $this->parameters = $parameters;
    }

    public function getTargetType(): TaskTargetType
    {
        return $this->targetType;
    }

    public function setTargetType(TaskTargetType $targetType): void
    {
        $this->targetType = $targetType;
    }

    public function getTargetDeviceIds(): ?string
    {
        return $this->targetDevices;
    }

    public function setTargetDeviceIds(?string $targetDevices): void
    {
        $this->targetDevices = $targetDevices;
    }

    public function getTargetGroup(): ?DeviceGroup
    {
        return $this->targetGroup;
    }

    public function setTargetGroup(?DeviceGroup $targetGroup): void
    {
        $this->targetGroup = $targetGroup;
    }

    public function getScheduledTime(): ?\DateTimeImmutable
    {
        return $this->scheduledTime;
    }

    public function setScheduledTime(?\DateTimeImmutable $scheduledTime): void
    {
        $this->scheduledTime = $scheduledTime;
    }

    public function getCronExpression(): ?string
    {
        return $this->cronExpression;
    }

    public function setCronExpression(?string $cronExpression): void
    {
        $this->cronExpression = $cronExpression;
    }

    public function getPriority(): int
    {
        return $this->priority;
    }

    public function setPriority(int $priority): void
    {
        $this->priority = $priority;
    }

    public function getRetryCount(): int
    {
        return $this->retryCount;
    }

    public function setRetryCount(int $retryCount): void
    {
        $this->retryCount = $retryCount;
    }

    public function getStartTime(): ?\DateTimeImmutable
    {
        return $this->startTime;
    }

    public function setStartTime(?\DateTimeImmutable $startTime): void
    {
        $this->startTime = $startTime;
    }

    public function getEndTime(): ?\DateTimeImmutable
    {
        return $this->endTime;
    }

    public function setEndTime(?\DateTimeImmutable $endTime): void
    {
        $this->endTime = $endTime;
    }

    public function getTotalDevices(): int
    {
        return $this->totalDevices;
    }

    public function setTotalDevices(int $totalDevices): void
    {
        $this->totalDevices = $totalDevices;
    }

    public function getSuccessDevices(): int
    {
        return $this->successDevices;
    }

    public function setSuccessDevices(int $successDevices): void
    {
        $this->successDevices = $successDevices;
    }

    public function getFailedDevices(): int
    {
        return $this->failedDevices;
    }

    public function setFailedDevices(int $failedDevices): void
    {
        $this->failedDevices = $failedDevices;
    }

    public function isValid(): bool
    {
        return $this->valid;
    }

    public function setValid(bool $valid): void
    {
        $this->valid = $valid;
    }

    /**
     * @return Collection<int, ScriptExecutionRecord>
     */
    public function getExecutionRecords(): Collection
    {
        return $this->executionRecords;
    }

    public function addExecutionRecord(ScriptExecutionRecord $executionRecord): void
    {
        if (!$this->executionRecords->contains($executionRecord)) {
            $this->executionRecords->add($executionRecord);
            $executionRecord->setTask($this);
        }
    }

    public function removeExecutionRecord(ScriptExecutionRecord $executionRecord): void
    {
        if ($this->executionRecords->removeElement($executionRecord)) {
            if ($executionRecord->getTask() === $this) {
                $executionRecord->setTask(null);
            }
        }
    }

    public function getLastExecutionTime(): ?\DateTimeImmutable
    {
        return $this->lastExecutionTime;
    }

    public function setLastExecutionTime(?\DateTimeImmutable $lastExecutionTime): void
    {
        $this->lastExecutionTime = $lastExecutionTime;
    }

    public function getMaxRetries(): int
    {
        return $this->maxRetries;
    }

    public function setMaxRetries(int $maxRetries): void
    {
        $this->maxRetries = $maxRetries;
    }

    public function getFailureReason(): ?string
    {
        return $this->failureReason;
    }

    public function setFailureReason(?string $failureReason): void
    {
        $this->failureReason = $failureReason;
    }

    /**
     * 获取错误信息（getFailureReason 的别名）.
     */
    public function getErrorMessage(): ?string
    {
        return $this->failureReason;
    }

    /**
     * 设置错误信息（setFailureReason 的别名）.
     */
    public function setErrorMessage(?string $errorMessage): void
    {
        $this->setFailureReason($errorMessage);
    }

    /**
     * @deprecated 使用 getScheduledTime() 代替
     */
    public function getScheduledAt(): ?\DateTimeImmutable
    {
        return $this->scheduledTime;
    }

    /**
     * @deprecated 使用 setScheduledTime() 代替
     */
    public function setScheduledAt(?\DateTimeImmutable $scheduledAt): void
    {
        $this->setScheduledTime($scheduledAt);
    }

    /**
     * 获取目标设备实体集合.
     *
     * @return Collection<int, AutoJsDevice>
     */
    public function getTargetDevices(): Collection
    {
        // 根据目标类型返回设备集合
        if (TaskTargetType::GROUP === $this->targetType && null !== $this->targetGroup) {
            return $this->targetGroup->getAutoJsDevices();
        }

        // TODO: 处理其他类型
        return new ArrayCollection();
    }

    /**
     * 获取下次运行时间.
     */
    public function getNextRunTime(): ?\DateTimeImmutable
    {
        if (null !== $this->scheduledTime) {
            return $this->scheduledTime;
        }

        return $this->scheduledTime;
    }

    /**
     * 设置下次运行时间.
     */
    public function setNextRunTime(?\DateTimeImmutable $nextRunTime): void
    {
        $this->scheduledTime = $nextRunTime;
    }

    /**
     * 获取已完成设备数.
     */
    public function getCompletedDevices(): int
    {
        return $this->successDevices + $this->failedDevices;
    }

    /**
     * 设置已完成设备数（通过设置成功数）.
     */
    public function setCompletedDevices(int $completedDevices): void
    {
        $this->successDevices = $completedDevices;
    }

    /**
     * 获取任务进度（百分比）.
     */
    public function getProgress(): float
    {
        if (0 === $this->totalDevices) {
            return 0.0;
        }

        return round(($this->getCompletedDevices() / $this->totalDevices) * 100, 2);
    }

    /**
     * 获取任务持续时间（秒）.
     */
    public function getDuration(): ?int
    {
        if (null === $this->startTime) {
            return null;
        }

        $endTime = $this->endTime ?? new \DateTimeImmutable();

        return $endTime->getTimestamp() - $this->startTime->getTimestamp();
    }

    /**
     * 检查任务是否已过期（仅适用于计划任务）.
     */
    public function isExpired(): bool
    {
        if (TaskType::SCHEDULED !== $this->taskType || null === $this->scheduledTime) {
            return false;
        }

        // 正在运行或已完成的任务不算过期
        if (TaskStatus::RUNNING === $this->status || TaskStatus::COMPLETED === $this->status) {
            return false;
        }

        $now = new \DateTimeImmutable();
        $expireTime = $this->scheduledTime->modify('+1 hour'); // 1小时后过期

        return $now > $expireTime;
    }

    /**
     * 添加目标设备.
     */
    public function addTargetDevice(AutoJsDevice $device): void
    {
        $deviceIds = $this->getTargetDeviceIdArray();
        $deviceId = $device->getId();

        if (null === $deviceId) {
            return;
        }

        if (!in_array($deviceId, $deviceIds, true)) {
            $deviceIds[] = $deviceId;
            $encoded = json_encode($deviceIds);
            if (false === $encoded) {
                throw BusinessLogicException::dataProcessingError('Failed to encode target devices');
            }
            $this->targetDevices = $encoded;
        }
    }

    /**
     * 移除目标设备.
     */
    public function removeTargetDevice(AutoJsDevice $device): void
    {
        $deviceIds = $this->getTargetDeviceIdArray();
        $deviceId = $device->getId();

        if (null === $deviceId) {
            return;
        }

        $key = array_search($deviceId, $deviceIds, true);
        if (false !== $key) {
            unset($deviceIds[$key]);
            $encoded = json_encode(array_values($deviceIds));
            if (false === $encoded) {
                throw BusinessLogicException::dataProcessingError('Failed to encode target devices');
            }
            $this->targetDevices = $encoded;
        }
    }

    /**
     * 获取目标设备ID数组.
     *
     * @return int[]
     */
    private function getTargetDeviceIdArray(): array
    {
        if (null === $this->targetDevices) {
            return [];
        }

        $decoded = json_decode($this->targetDevices, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * 获取任务开始时间（getStartTime 的别名）.
     */
    public function getStartedAt(): ?\DateTimeImmutable
    {
        return $this->startTime;
    }

    /**
     * 设置任务开始时间（setStartTime 的别名）.
     */
    public function setStartedAt(?\DateTimeInterface $startedAt): void
    {
        $this->startTime = null !== $startedAt ?
            ($startedAt instanceof \DateTimeImmutable ? $startedAt : \DateTimeImmutable::createFromInterface($startedAt))
            : null;
    }

    /**
     * 获取任务完成时间（getEndTime 的别名）.
     */
    public function getCompletedAt(): ?\DateTimeImmutable
    {
        return $this->endTime;
    }

    /**
     * 设置任务完成时间（setEndTime 的别名）.
     */
    public function setCompletedAt(?\DateTimeInterface $completedAt): void
    {
        $this->endTime = null !== $completedAt ?
            ($completedAt instanceof \DateTimeImmutable ? $completedAt : \DateTimeImmutable::createFromInterface($completedAt))
            : null;
    }
}
