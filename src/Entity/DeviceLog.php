<?php

declare(strict_types=1);

namespace Tourze\AutoJsControlBundle\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Tourze\AutoJsControlBundle\Enum\LogLevel;
use Tourze\AutoJsControlBundle\Enum\LogType;
use Tourze\AutoJsControlBundle\Repository\DeviceLogRepository;
use Tourze\DoctrineIndexedBundle\Attribute\IndexColumn;
use Tourze\DoctrineTimestampBundle\Traits\CreateTimeAware;

#[ORM\Entity(repositoryClass: DeviceLogRepository::class)]
#[ORM\Table(name: 'auto_js_device_log', options: ['comment' => '设备日志表'])]
#[ORM\Index(name: 'auto_js_device_log_idx_device_level', columns: ['auto_js_device_id', 'log_level'])]
#[ORM\Index(name: 'auto_js_device_log_idx_type_time', columns: ['log_type', 'create_time'])]
class DeviceLog implements \Stringable
{
    use CreateTimeAware;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::BIGINT, options: ['comment' => '主键ID'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: AutoJsDevice::class, inversedBy: 'deviceLogs')]
    #[ORM\JoinColumn(name: 'auto_js_device_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull(message: '设备不能为空')]
    private ?AutoJsDevice $autoJsDevice = null;

    #[ORM\Column(type: Types::STRING, length: 20, enumType: LogLevel::class, options: ['comment' => '日志级别'])]
    #[IndexColumn]
    #[Assert\NotNull(message: '日志级别不能为空')]
    #[Assert\Choice(callback: [LogLevel::class, 'cases'], message: '日志级别必须是有效值')]
    private LogLevel $logLevel = LogLevel::INFO;

    #[ORM\Column(type: Types::STRING, length: 50, enumType: LogType::class, options: ['comment' => '日志类型'])]
    #[IndexColumn]
    #[Assert\NotNull(message: '日志类型不能为空')]
    #[Assert\Choice(callback: [LogType::class, 'cases'], message: '日志类型必须是有效值')]
    private LogType $logType = LogType::SYSTEM;

    #[ORM\Column(type: Types::STRING, length: 500, options: ['comment' => '日志标题'])]
    #[Assert\NotBlank(message: '日志标题不能为空')]
    #[Assert\Length(max: 500, maxMessage: '日志标题长度不能超过 {{ limit }} 个字符')]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT, nullable: true, options: ['comment' => '日志内容'])]
    #[Assert\Length(max: 65535, maxMessage: '日志内容长度不能超过 {{ limit }} 个字符')]
    private ?string $content = null;

    #[ORM\Column(type: Types::TEXT, nullable: true, options: ['comment' => '上下文信息（JSON格式）'])]
    #[Assert\Length(max: 65535, maxMessage: '上下文信息长度不能超过 {{ limit }} 个字符')]
    #[Assert\Json(message: '上下文信息必须是有效的JSON格式')]
    private ?string $context = null;

    #[ORM\Column(type: Types::STRING, length: 45, nullable: true, options: ['comment' => '设备IP'])]
    #[Assert\Length(max: 45, maxMessage: '设备IP长度不能超过 {{ limit }} 个字符')]
    private ?string $deviceIp = null;

    #[ORM\Column(type: Types::TEXT, nullable: true, options: ['comment' => '日志消息'])]
    #[Assert\Length(max: 65535, maxMessage: '日志消息长度不能超过 {{ limit }} 个字符')]
    private ?string $message = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true, options: ['comment' => '日志时间'])]
    #[Assert\Type(type: \DateTimeImmutable::class, message: '日志时间必须是有效的日期时间')]
    private ?\DateTimeImmutable $logTime = null;

    #[ORM\Column(type: Types::TEXT, nullable: true, options: ['comment' => '堆栈跟踪'])]
    #[Assert\Length(max: 65535, maxMessage: '堆栈跟踪长度不能超过 {{ limit }} 个字符')]
    private ?string $stackTrace = null;

    public function __toString(): string
    {
        return sprintf('[%s] %s', $this->logLevel->value, $this->title ?? '未命名日志');
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

    public function getLogLevel(): LogLevel
    {
        return $this->logLevel;
    }

    public function setLogLevel(LogLevel $logLevel): void
    {
        $this->logLevel = $logLevel;
    }

    public function getLogType(): LogType
    {
        return $this->logType;
    }

    public function setLogType(LogType $logType): void
    {
        $this->logType = $logType;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(?string $content): void
    {
        $this->content = $content;
    }

    public function getContext(): ?string
    {
        return $this->context;
    }

    public function setContext(?string $context): void
    {
        $this->context = $context;
    }

    public function getDeviceIp(): ?string
    {
        return $this->deviceIp;
    }

    public function setDeviceIp(?string $deviceIp): void
    {
        $this->deviceIp = $deviceIp;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(?string $message): void
    {
        $this->message = $message;
    }

    public function getLogTime(): ?\DateTimeImmutable
    {
        return $this->logTime;
    }

    public function setLogTime(?\DateTimeImmutable $logTime): void
    {
        $this->logTime = $logTime;
    }

    public function getStackTrace(): ?string
    {
        return $this->stackTrace;
    }

    public function setStackTrace(?string $stackTrace): void
    {
        $this->stackTrace = $stackTrace;
    }

    // 便捷方法：设置日志级别（兼容 setLevel）
    public function setLevel(LogLevel $level): void
    {
        $this->setLogLevel($level);
    }
}
