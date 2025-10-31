<?php

declare(strict_types=1);

namespace Tourze\AutoJsControlBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;
use Tourze\AutoJsControlBundle\Enum\ScriptStatus;
use Tourze\AutoJsControlBundle\Enum\ScriptType;
use Tourze\AutoJsControlBundle\Repository\ScriptRepository;
use Tourze\DoctrineIndexedBundle\Attribute\IndexColumn;
use Tourze\DoctrineTimestampBundle\Traits\TimestampableAware;
use Tourze\DoctrineTrackBundle\Attribute\TrackColumn;
use Tourze\DoctrineUserBundle\Traits\BlameableAware;

#[ORM\Entity(repositoryClass: ScriptRepository::class)]
#[ORM\Table(name: 'auto_js_script', options: ['comment' => '脚本管理表'])]
#[UniqueEntity(fields: ['code'], message: '脚本编码已存在')]
class Script implements \Stringable
{
    use TimestampableAware;
    use BlameableAware;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::BIGINT, options: ['comment' => '主键ID'])]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 64, unique: true, options: ['comment' => '脚本编码'])]
    #[Assert\NotBlank(message: '脚本编码不能为空')]
    #[Assert\Length(max: 64, maxMessage: '脚本编码长度不能超过64个字符')]
    private ?string $code = null;

    #[ORM\Column(type: Types::STRING, length: 200, options: ['comment' => '脚本名称'])]
    #[Assert\NotBlank(message: '脚本名称不能为空')]
    #[Assert\Length(max: 200, maxMessage: '脚本名称长度不能超过200个字符')]
    private ?string $name = null;

    #[ORM\Column(type: Types::TEXT, nullable: true, options: ['comment' => '脚本描述'])]
    #[Assert\Length(max: 65535, maxMessage: '脚本描述长度不能超过 {{ limit }} 个字符')]
    private ?string $description = null;

    #[ORM\Column(type: Types::STRING, enumType: ScriptType::class, length: 20, options: ['comment' => '脚本类型'])]
    #[IndexColumn]
    #[Assert\NotNull(message: '脚本类型不能为空')]
    #[Assert\Choice(callback: [ScriptType::class, 'cases'], message: '脚本类型必须是有效值')]
    private ScriptType $scriptType = ScriptType::JAVASCRIPT;

    #[ORM\Column(type: Types::TEXT, nullable: true, options: ['comment' => '脚本内容（JavaScript代码）'])]
    #[TrackColumn]
    #[Assert\Length(max: 16777215, maxMessage: '脚本内容长度不能超过 {{ limit }} 个字符')]
    private ?string $content = null;

    #[ORM\Column(type: Types::STRING, length: 500, nullable: true, options: ['comment' => '项目文件路径（project类型）'])]
    #[Assert\Length(max: 500, maxMessage: '项目文件路径长度不能超过 {{ limit }} 个字符')]
    private ?string $projectPath = null;

    #[ORM\Column(type: Types::INTEGER, options: ['comment' => '版本号', 'default' => 1])]
    #[Assert\Type(type: 'integer', message: '版本号必须是整数')]
    #[Assert\GreaterThan(value: 0, message: '版本号必须大于0')]
    private int $version = 1;

    #[ORM\Column(type: Types::TEXT, nullable: true, options: ['comment' => '脚本参数定义（JSON格式）'])]
    #[Assert\Length(max: 65535, maxMessage: '脚本参数定义长度不能超过 {{ limit }} 个字符')]
    #[Assert\Json(message: '脚本参数定义必须是有效的JSON格式')]
    private ?string $parameters = null;

    #[ORM\Column(type: Types::INTEGER, options: ['comment' => '优先级（数值越大优先级越高）', 'default' => 0])]
    #[IndexColumn]
    #[Assert\Type(type: 'integer', message: '优先级必须是整数')]
    private int $priority = 0;

    #[ORM\Column(type: Types::INTEGER, options: ['comment' => '超时时间（秒）', 'default' => 3600])]
    #[Assert\Type(type: 'integer', message: '超时时间必须是整数')]
    #[Assert\GreaterThan(value: 0, message: '超时时间必须大于0')]
    private int $timeout = 3600;

    #[ORM\Column(type: Types::INTEGER, options: ['comment' => '最大重试次数', 'default' => 3])]
    #[Assert\Type(type: 'integer', message: '最大重试次数必须是整数')]
    #[Assert\GreaterThanOrEqual(value: 0, message: '最大重试次数不能为负数')]
    private int $maxRetries = 3;

    #[ORM\Column(type: Types::BOOLEAN, options: ['comment' => '是否启用'])]
    #[Assert\Type(type: 'bool', message: '是否启用必须是布尔值')]
    private bool $valid = true;

    #[ORM\Column(type: Types::TEXT, nullable: true, options: ['comment' => '安全校验规则（JSON格式）'])]
    #[Assert\Length(max: 65535, maxMessage: '安全校验规则长度不能超过 {{ limit }} 个字符')]
    #[Assert\Json(message: '安全校验规则必须是有效的JSON格式')]
    private ?string $securityRules = null;

    #[ORM\Column(type: Types::STRING, length: 64, nullable: true, options: ['comment' => '内容校验和'])]
    #[Assert\Length(max: 64, maxMessage: '内容校验和长度不能超过 {{ limit }} 个字符')]
    private ?string $checksum = null;

    #[ORM\Column(type: Types::STRING, enumType: ScriptStatus::class, length: 20, options: ['comment' => '脚本状态'])]
    #[Assert\NotNull(message: '脚本状态不能为空')]
    #[Assert\Choice(callback: [ScriptStatus::class, 'cases'], message: '脚本状态必须是有效值')]
    private ScriptStatus $status = ScriptStatus::DRAFT;

    /**
     * @var string[]|null
     */
    #[ORM\Column(type: Types::JSON, nullable: true, options: ['comment' => '标签'])]
    #[Assert\Type(type: 'array', message: '标签必须是数组')]
    private ?array $tags = null;

    /**
     * @var Collection<int, ScriptExecutionRecord>
     */
    #[ORM\OneToMany(targetEntity: ScriptExecutionRecord::class, mappedBy: 'script', fetch: 'EXTRA_LAZY')]
    private Collection $executionRecords;

    /**
     * @var Collection<int, Task>
     */
    #[ORM\OneToMany(targetEntity: Task::class, mappedBy: 'script', fetch: 'EXTRA_LAZY')]
    private Collection $tasks;

    public function __construct()
    {
        $this->executionRecords = new ArrayCollection();
        $this->tasks = new ArrayCollection();
    }

    public function __toString(): string
    {
        return sprintf('%s (%s)', $this->name ?? '未命名脚本', $this->code ?? '');
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(string $code): void
    {
        $this->code = $code;
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

    public function getScriptType(): ScriptType
    {
        return $this->scriptType;
    }

    public function setScriptType(ScriptType $scriptType): void
    {
        $this->scriptType = $scriptType;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(?string $content): void
    {
        $this->content = $content;
    }

    public function getProjectPath(): ?string
    {
        return $this->projectPath;
    }

    public function setProjectPath(?string $projectPath): void
    {
        $this->projectPath = $projectPath;
    }

    public function getVersion(): int
    {
        return $this->version;
    }

    public function setVersion(int $version): void
    {
        $this->version = $version;
    }

    public function getParameters(): ?string
    {
        return $this->parameters;
    }

    public function setParameters(?string $parameters): void
    {
        $this->parameters = $parameters;
    }

    public function getPriority(): int
    {
        return $this->priority;
    }

    public function setPriority(int $priority): void
    {
        $this->priority = $priority;
    }

    public function getTimeout(): int
    {
        return $this->timeout;
    }

    public function setTimeout(int $timeout): void
    {
        $this->timeout = $timeout;
    }

    public function getMaxRetries(): int
    {
        return $this->maxRetries;
    }

    public function setMaxRetries(int $maxRetries): void
    {
        $this->maxRetries = $maxRetries;
    }

    public function isValid(): bool
    {
        return $this->valid;
    }

    public function setValid(bool $valid): void
    {
        $this->valid = $valid;
    }

    public function getSecurityRules(): ?string
    {
        return $this->securityRules;
    }

    public function setSecurityRules(?string $securityRules): void
    {
        $this->securityRules = $securityRules;
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
            $executionRecord->setScript($this);
        }
    }

    public function removeExecutionRecord(ScriptExecutionRecord $executionRecord): void
    {
        if ($this->executionRecords->removeElement($executionRecord)) {
            if ($executionRecord->getScript() === $this) {
                $executionRecord->setScript(null);
            }
        }
    }

    /**
     * @return Collection<int, Task>
     */
    public function getTasks(): Collection
    {
        return $this->tasks;
    }

    public function addTask(Task $task): void
    {
        if (!$this->tasks->contains($task)) {
            $this->tasks->add($task);
            $task->setScript($this);
        }
    }

    public function removeTask(Task $task): void
    {
        if ($this->tasks->removeElement($task)) {
            if ($task->getScript() === $this) {
                $task->setScript(null);
            }
        }
    }

    public function getChecksum(): ?string
    {
        return $this->checksum;
    }

    public function setChecksum(?string $checksum): void
    {
        $this->checksum = $checksum;
    }

    public function getStatus(): ScriptStatus
    {
        return $this->status;
    }

    public function setStatus(ScriptStatus $status): void
    {
        $this->status = $status;
    }

    /**
     * @return string[]|null
     */
    public function getTags(): ?array
    {
        return $this->tags;
    }

    /**
     * @param string[]|null $tags
     */
    public function setTags(?array $tags): void
    {
        $this->tags = $tags;
    }
}
