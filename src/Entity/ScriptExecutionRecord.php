<?php

declare(strict_types=1);

namespace Tourze\AutoJsControlBundle\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Tourze\AutoJsControlBundle\Enum\ExecutionStatus;
use Tourze\AutoJsControlBundle\Repository\ScriptExecutionRecordRepository;
use Tourze\DoctrineIndexedBundle\Attribute\IndexColumn;
use Tourze\DoctrineTimestampBundle\Traits\CreateTimeAware;

#[ORM\Entity(repositoryClass: ScriptExecutionRecordRepository::class)]
#[ORM\Table(name: 'auto_js_script_execution_record', options: ['comment' => '脚本执行记录表'])]
#[ORM\Index(columns: ['auto_js_device_id', 'status'], name: 'auto_js_script_execution_record_auto_js_execution_idx_device_status')]
#[ORM\Index(columns: ['task_id', 'status'], name: 'auto_js_script_execution_record_auto_js_execution_idx_task_status')]
#[ORM\Index(columns: ['script_id', 'status'], name: 'auto_js_script_execution_record_auto_js_execution_idx_script_status')]
class ScriptExecutionRecord implements \Stringable
{
    use CreateTimeAware;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::BIGINT, options: ['comment' => '主键ID'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: AutoJsDevice::class, inversedBy: 'scriptExecutionRecords')]
    #[ORM\JoinColumn(name: 'auto_js_device_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull(message: '设备不能为空')]
    private ?AutoJsDevice $autoJsDevice = null;

    #[ORM\ManyToOne(targetEntity: Script::class, inversedBy: 'executionRecords')]
    #[ORM\JoinColumn(name: 'script_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull(message: '脚本不能为空')]
    private ?Script $script = null;

    #[ORM\ManyToOne(targetEntity: Task::class, inversedBy: 'executionRecords')]
    #[ORM\JoinColumn(name: 'task_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Task $task = null;

    #[ORM\Column(type: Types::STRING, length: 20, enumType: ExecutionStatus::class, options: ['comment' => '执行状态'])]
    #[IndexColumn]
    #[Assert\NotNull(message: '执行状态不能为空')]
    #[Assert\Choice(callback: [ExecutionStatus::class, 'cases'], message: '执行状态必须是有效值')]
    private ExecutionStatus $status = ExecutionStatus::PENDING;

    #[ORM\Column(type: Types::TEXT, nullable: true, options: ['comment' => '执行参数（JSON格式）'])]
    #[Assert\Length(max: 65535, maxMessage: '执行参数长度不能超过 {{ limit }} 个字符')]
    #[Assert\Json(message: '执行参数必须是有效的JSON格式')]
    private ?string $parameters = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true, options: ['comment' => '开始执行时间'])]
    #[Assert\Type(type: \DateTimeImmutable::class, message: '开始执行时间必须是有效的日期时间')]
    private ?\DateTimeImmutable $startTime = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true, options: ['comment' => '结束执行时间'])]
    #[Assert\Type(type: \DateTimeImmutable::class, message: '结束执行时间必须是有效的日期时间')]
    private ?\DateTimeImmutable $endTime = null;

    #[ORM\Column(type: Types::INTEGER, options: ['comment' => '执行耗时（秒）', 'default' => 0])]
    #[Assert\Type(type: 'integer', message: '执行耗时必须是整数')]
    #[Assert\GreaterThanOrEqual(value: 0, message: '执行耗时不能为负数')]
    private int $duration = 0;

    #[ORM\Column(type: Types::TEXT, nullable: true, options: ['comment' => '执行结果'])]
    #[Assert\Length(max: 16777215, maxMessage: '执行结果长度不能超过 {{ limit }} 个字符')]
    private ?string $result = null;

    #[ORM\Column(type: Types::TEXT, nullable: true, options: ['comment' => '执行日志'])]
    #[Assert\Length(max: 16777215, maxMessage: '执行日志长度不能超过 {{ limit }} 个字符')]
    private ?string $logs = null;

    #[ORM\Column(type: Types::TEXT, nullable: true, options: ['comment' => '错误信息'])]
    #[Assert\Length(max: 65535, maxMessage: '错误信息长度不能超过 {{ limit }} 个字符')]
    private ?string $errorMessage = null;

    #[ORM\Column(type: Types::INTEGER, options: ['comment' => '重试次数', 'default' => 0])]
    #[Assert\Type(type: 'integer', message: '重试次数必须是整数')]
    #[Assert\PositiveOrZero(message: '重试次数不能为负数')]
    private int $retryCount = 0;

    #[ORM\Column(type: Types::STRING, length: 64, nullable: true, options: ['comment' => '指令ID'])]
    #[Assert\Length(max: 64, maxMessage: '指令ID长度不能超过 {{ limit }} 个字符')]
    private ?string $instructionId = null;

    #[ORM\Column(type: Types::TEXT, nullable: true, options: ['comment' => '执行输出'])]
    #[Assert\Length(max: 16777215, maxMessage: '执行输出长度不能超过 {{ limit }} 个字符')]
    private ?string $output = null;

    /**
     * @var mixed[]|null
     */
    #[ORM\Column(type: Types::JSON, nullable: true, options: ['comment' => '执行指标'])]
    #[Assert\Type(type: 'array', message: '执行指标必须是数组')]
    private ?array $executionMetrics = null;

    /**
     * @var string[]|null
     */
    #[ORM\Column(type: Types::JSON, nullable: true, options: ['comment' => '截图路径列表'])]
    #[Assert\Type(type: 'array', message: '截图路径列表必须是数组')]
    private ?array $screenshots = null;

    public function __toString(): string
    {
        return sprintf('执行记录 #%s (%s)', $this->id ?? 'new', $this->status->value);
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

    public function getScript(): ?Script
    {
        return $this->script;
    }

    public function setScript(?Script $script): void
    {
        $this->script = $script;
    }

    public function getTask(): ?Task
    {
        return $this->task;
    }

    public function setTask(?Task $task): void
    {
        $this->task = $task;
    }

    public function getStatus(): ExecutionStatus
    {
        return $this->status;
    }

    public function setStatus(ExecutionStatus $status): void
    {
        $this->status = $status;
    }

    public function getParameters(): ?string
    {
        return $this->parameters;
    }

    public function setParameters(?string $parameters): void
    {
        $this->parameters = $parameters;
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

    public function getDuration(): int
    {
        return $this->duration;
    }

    public function setDuration(int $duration): void
    {
        $this->duration = $duration;
    }

    public function getResult(): ?string
    {
        return $this->result;
    }

    public function setResult(?string $result): void
    {
        $this->result = $result;
    }

    public function getLogs(): ?string
    {
        return $this->logs;
    }

    public function setLogs(?string $logs): void
    {
        $this->logs = $logs;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function setErrorMessage(?string $errorMessage): void
    {
        $this->errorMessage = $errorMessage;
    }

    public function getRetryCount(): int
    {
        return $this->retryCount;
    }

    public function setRetryCount(int $retryCount): void
    {
        $this->retryCount = $retryCount;
    }

    public function getInstructionId(): ?string
    {
        return $this->instructionId;
    }

    public function setInstructionId(?string $instructionId): void
    {
        $this->instructionId = $instructionId;
    }

    public function getOutput(): ?string
    {
        return $this->output;
    }

    public function setOutput(?string $output): void
    {
        $this->output = $output;
    }

    /**
     * @return mixed[]|null
     */
    public function getExecutionMetrics(): ?array
    {
        return $this->executionMetrics;
    }

    /**
     * @param mixed[]|null $executionMetrics
     */
    public function setExecutionMetrics(?array $executionMetrics): void
    {
        $this->executionMetrics = $executionMetrics;
    }

    /**
     * @return string[]|null
     */
    public function getScreenshots(): ?array
    {
        return $this->screenshots;
    }

    /**
     * @param string[]|null $screenshots
     */
    public function setScreenshots(?array $screenshots): void
    {
        $this->screenshots = $screenshots;
    }

    /**
     * setAutoJsDevice 的别名方法.
     */
    public function setDevice(?AutoJsDevice $device): void
    {
        $this->setAutoJsDevice($device);
    }

    /**
     * setStartTime 的别名方法.
     */
    public function setStartedAt(\DateTime $startedAt): void
    {
        $this->startTime = \DateTimeImmutable::createFromMutable($startedAt);
    }

    /**
     * setEndTime 的别名方法.
     */
    public function setCompletedAt(\DateTime $completedAt): void
    {
        $this->endTime = \DateTimeImmutable::createFromMutable($completedAt);
    }
}
