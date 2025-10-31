<?php

namespace Tourze\AutoJsControlBundle\Dto\Response;

use Tourze\AutoJsControlBundle\ValueObject\DeviceInstruction;

/**
 * 设备心跳响应 DTO.
 */
final class DeviceHeartbeatResponse implements \JsonSerializable
{
    private string $status;

    /**
     * @var array<DeviceInstruction>
     */
    private array $instructions;

    private \DateTimeImmutable $serverTime;

    /**
     * @var array<string, mixed>|null
     */
    private ?array $config;

    private ?string $message;

    /**
     * @param array<DeviceInstruction>  $instructions
     * @param array<string, mixed>|null $config
     */
    public function __construct(
        string $status = 'ok',
        array $instructions = [],
        ?\DateTimeImmutable $serverTime = null,
        ?array $config = null,
        ?string $message = null,
    ) {
        $this->status = $status;
        $this->instructions = $instructions;
        $this->serverTime = $serverTime ?? new \DateTimeImmutable();
        $this->config = $config;
        $this->message = $message;
    }

    /**
     * 创建成功响应.
     *
     * @param array<DeviceInstruction>  $instructions
     * @param array<string, mixed>|null $config
     */
    public static function success(array $instructions = [], ?array $config = null): self
    {
        return new self('ok', $instructions, null, $config);
    }

    /**
     * 创建错误响应.
     */
    public static function error(string $message): self
    {
        return new self('error', [], null, null, $message);
    }

    /**
     * 创建需要重新认证的响应.
     */
    public static function unauthorized(string $message = '设备认证失败'): self
    {
        return new self('unauthorized', [], null, null, $message);
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    /**
     * @return DeviceInstruction[]
     */
    public function getInstructions(): array
    {
        return $this->instructions;
    }

    public function getServerTime(): \DateTimeImmutable
    {
        return $this->serverTime;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getConfig(): ?array
    {
        return $this->config;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    /**
     * 添加指令.
     */
    public function addInstruction(DeviceInstruction $instruction): void
    {
        $this->instructions[] = $instruction;
    }

    /**
     * 是否有待执行的指令.
     */
    public function hasInstructions(): bool
    {
        return [] !== $this->instructions;
    }

    /**
     * 获取指令数量.
     */
    public function getInstructionCount(): int
    {
        return count($this->instructions);
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $data = [
            'status' => $this->status,
            'serverTime' => $this->serverTime->format(\DateTimeInterface::RFC3339),
            'instructionCount' => $this->getInstructionCount(),
        ];

        if ([] !== $this->instructions) {
            $data['instructions'] = array_map(
                fn (DeviceInstruction $instruction) => $instruction->toArray(),
                $this->instructions
            );
        }

        if (null !== $this->config) {
            $data['config'] = $this->config;
        }

        if (null !== $this->message) {
            $data['message'] = $this->message;
        }

        return $data;
    }
}
