<?php

declare(strict_types=1);

namespace Tourze\AutoJsControlBundle\Dto\Response;

/**
 * 脚本下载响应 DTO.
 */
final class ScriptDownloadResponse implements \JsonSerializable
{
    private string $status;

    private ?int $scriptId;

    private ?string $scriptCode;

    private ?string $scriptName;

    private ?string $scriptType;

    private ?string $content;

    private ?string $version;

    /** @var array<string, mixed>|null */
    private ?array $parameters;

    private ?int $timeout;

    private ?string $checksum;

    private ?string $message;

    private \DateTimeImmutable $serverTime;

    /**
     * @param array<string, mixed>|null $parameters
     */
    public function __construct(
        string $status,
        ?int $scriptId = null,
        ?string $scriptCode = null,
        ?string $scriptName = null,
        ?string $scriptType = null,
        ?string $content = null,
        ?string $version = null,
        ?array $parameters = null,
        ?int $timeout = null,
        ?string $checksum = null,
        ?string $message = null,
        ?\DateTimeImmutable $serverTime = null,
    ) {
        $this->status = $status;
        $this->scriptId = $scriptId;
        $this->scriptCode = $scriptCode;
        $this->scriptName = $scriptName;
        $this->scriptType = $scriptType;
        $this->content = $content;
        $this->version = $version;
        $this->parameters = $parameters;
        $this->timeout = $timeout;
        $this->checksum = $checksum;
        $this->message = $message;
        $this->serverTime = $serverTime ?? new \DateTimeImmutable();
    }

    /**
     * 创建成功响应.
     *
     * @param array<string, mixed>|null $parameters
     */
    public static function success(
        int $scriptId,
        string $scriptCode,
        string $scriptName,
        string $scriptType,
        string $content,
        string $version,
        ?array $parameters = null,
        int $timeout = 3600,
    ): self {
        $checksum = hash('sha256', $content);

        return new self(
            'ok',
            $scriptId,
            $scriptCode,
            $scriptName,
            $scriptType,
            $content,
            $version,
            $parameters,
            $timeout,
            $checksum
        );
    }

    /**
     * 创建未找到响应.
     */
    public static function notFound(int $scriptId): self
    {
        return new self('not_found', $scriptId, null, null, null, null, null, null, null, null, '脚本不存在');
    }

    /**
     * 创建无权限响应.
     */
    public static function forbidden(int $scriptId): self
    {
        return new self('forbidden', $scriptId, null, null, null, null, null, null, null, null, '无权访问该脚本');
    }

    /**
     * 创建错误响应.
     */
    public static function error(string $message): self
    {
        return new self('error', null, null, null, null, null, null, null, null, null, $message);
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getScriptId(): ?int
    {
        return $this->scriptId;
    }

    public function getScriptCode(): ?string
    {
        return $this->scriptCode;
    }

    public function getScriptName(): ?string
    {
        return $this->scriptName;
    }

    public function getScriptType(): ?string
    {
        return $this->scriptType;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function getVersion(): ?string
    {
        return $this->version;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getParameters(): ?array
    {
        return $this->parameters;
    }

    public function getTimeout(): ?int
    {
        return $this->timeout;
    }

    public function getChecksum(): ?string
    {
        return $this->checksum;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function getServerTime(): \DateTimeImmutable
    {
        return $this->serverTime;
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $data = [
            'status' => $this->status,
            'serverTime' => $this->serverTime->format(\DateTimeInterface::RFC3339),
        ];

        return array_merge($data, $this->getOptionalFields());
    }

    /**
     * @return array<string, mixed>
     */
    private function getOptionalFields(): array
    {
        $fields = [];

        $fields = $this->addFieldIfNotNull($fields, 'scriptId', $this->scriptId);
        $fields = $this->addFieldIfNotNull($fields, 'scriptCode', $this->scriptCode);
        $fields = $this->addFieldIfNotNull($fields, 'scriptName', $this->scriptName);
        $fields = $this->addFieldIfNotNull($fields, 'scriptType', $this->scriptType);
        $fields = $this->addFieldIfNotNull($fields, 'version', $this->version);
        $fields = $this->addFieldIfNotNull($fields, 'parameters', $this->parameters);
        $fields = $this->addFieldIfNotNull($fields, 'timeout', $this->timeout);
        $fields = $this->addFieldIfNotNull($fields, 'checksum', $this->checksum);
        $fields = $this->addFieldIfNotNull($fields, 'message', $this->message);

        if (null !== $this->content) {
            $fields['content'] = $this->content;
            $fields['contentSize'] = strlen($this->content);
        }

        return $fields;
    }

    /**
     * @param array<string, mixed> $fields
     * @return array<string, mixed>
     */
    private function addFieldIfNotNull(array $fields, string $key, mixed $value): array
    {
        if (null !== $value) {
            $fields[$key] = $value;
        }

        return $fields;
    }
}
