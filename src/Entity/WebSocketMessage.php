<?php

declare(strict_types=1);

namespace Tourze\AutoJsControlBundle\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Tourze\AutoJsControlBundle\Repository\WebSocketMessageRepository;
use Tourze\DoctrineIndexedBundle\Attribute\IndexColumn;
use Tourze\DoctrineTimestampBundle\Traits\CreateTimeAware;

#[ORM\Entity(repositoryClass: WebSocketMessageRepository::class)]
#[ORM\Table(name: 'auto_js_websocket_message', options: ['comment' => 'WebSocket消息记录表'])]
#[ORM\Index(columns: ['auto_js_device_id', 'create_time'], name: 'auto_js_websocket_message_auto_js_ws_msg_idx_device_time')]
#[ORM\Index(columns: ['message_type', 'direction'], name: 'auto_js_websocket_message_auto_js_ws_msg_idx_type_direction')]
class WebSocketMessage implements \Stringable
{
    use CreateTimeAware;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::BIGINT, options: ['comment' => '主键ID'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: AutoJsDevice::class, inversedBy: 'webSocketMessages', cascade: ['persist'])]
    #[ORM\JoinColumn(name: 'auto_js_device_id', referencedColumnName: 'id', nullable: true, onDelete: 'CASCADE')]
    private ?AutoJsDevice $autoJsDevice = null;

    #[ORM\Column(type: Types::STRING, length: 64, options: ['comment' => '消息唯一标识'])]
    #[Assert\NotBlank(message: '消息ID不能为空')]
    #[Assert\Length(max: 64, maxMessage: '消息ID长度不能超过 {{ limit }} 个字符')]
    private ?string $messageId = null;

    #[ORM\Column(type: Types::STRING, length: 50, options: ['comment' => '消息类型：register/heartbeat/command/response/log/monitor'])]
    #[IndexColumn]
    #[Assert\NotBlank(message: '消息类型不能为空')]
    #[Assert\Length(max: 50, maxMessage: '消息类型长度不能超过 {{ limit }} 个字符')]
    #[Assert\Choice(choices: ['register', 'heartbeat', 'command', 'response', 'log', 'monitor'], message: '消息类型必须是有效值')]
    private string $messageType = 'command';

    #[ORM\Column(type: Types::STRING, length: 10, options: ['comment' => '消息方向：in/out'])]
    #[IndexColumn]
    #[Assert\NotBlank(message: '消息方向不能为空')]
    #[Assert\Length(max: 10, maxMessage: '消息方向长度不能超过 {{ limit }} 个字符')]
    #[Assert\Choice(choices: ['in', 'out'], message: '消息方向必须是in或out')]
    private string $direction = 'out';

    #[ORM\Column(type: Types::TEXT, options: ['comment' => '消息内容（JSON格式）'])]
    #[Assert\NotBlank(message: '消息内容不能为空')]
    #[Assert\Length(max: 16777215, maxMessage: '消息内容长度不能超过 {{ limit }} 个字符')]
    #[Assert\Json(message: '消息内容必须是有效的JSON格式')]
    private ?string $content = null;

    #[ORM\Column(type: Types::BOOLEAN, options: ['comment' => '是否已处理'])]
    #[Assert\Type(type: 'bool', message: '是否已处理必须是布尔值')]
    private bool $isProcessed = false;

    #[ORM\Column(type: Types::STRING, length: 20, nullable: true, options: ['comment' => '处理状态：success/failed/timeout'])]
    #[Assert\Length(max: 20, maxMessage: '处理状态长度不能超过 {{ limit }} 个字符')]
    #[Assert\Choice(choices: ['success', 'failed', 'timeout', null], message: '处理状态必须是有效值')]
    private ?string $processStatus = null;

    #[ORM\Column(type: Types::TEXT, nullable: true, options: ['comment' => '处理结果'])]
    #[Assert\Length(max: 65535, maxMessage: '处理结果长度不能超过 {{ limit }} 个字符')]
    private ?string $processResult = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true, options: ['comment' => '处理时间'])]
    #[Assert\Type(type: \DateTimeImmutable::class, message: '处理时间必须是有效的日期时间')]
    private ?\DateTimeImmutable $processTime = null;

    public function __toString(): string
    {
        return sprintf('[%s] %s', $this->messageType, $this->messageId ?? 'new');
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

    public function getMessageId(): ?string
    {
        return $this->messageId;
    }

    public function setMessageId(string $messageId): void
    {
        $this->messageId = $messageId;
    }

    public function getMessageType(): string
    {
        return $this->messageType;
    }

    public function setMessageType(string $messageType): void
    {
        $this->messageType = $messageType;
    }

    public function getDirection(): string
    {
        return $this->direction;
    }

    public function setDirection(string $direction): void
    {
        $this->direction = $direction;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(string $content): void
    {
        $this->content = $content;
    }

    public function isProcessed(): bool
    {
        return $this->isProcessed;
    }

    public function setIsProcessed(bool $isProcessed): void
    {
        $this->isProcessed = $isProcessed;
    }

    public function getProcessStatus(): ?string
    {
        return $this->processStatus;
    }

    public function setProcessStatus(?string $processStatus): void
    {
        $this->processStatus = $processStatus;
    }

    public function getProcessResult(): ?string
    {
        return $this->processResult;
    }

    public function setProcessResult(?string $processResult): void
    {
        $this->processResult = $processResult;
    }

    public function getProcessTime(): ?\DateTimeImmutable
    {
        return $this->processTime;
    }

    public function setProcessTime(?\DateTimeImmutable $processTime): void
    {
        $this->processTime = $processTime;
    }
}
