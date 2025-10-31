<?php

namespace Tourze\AutoJsControlBundle\Dto\Request;

use Symfony\Component\Validator\Constraints as Assert;
use Tourze\AutoJsControlBundle\Enum\ExecutionStatus;

/**
 * 报告执行结果请求 DTO.
 */
final class ReportExecutionResultRequest
{
    #[Assert\NotBlank(message: '设备代码不能为空')]
    #[Assert\Length(max: 64, maxMessage: '设备代码长度不能超过64个字符')]
    private string $deviceCode;

    #[Assert\NotBlank(message: '签名不能为空')]
    private string $signature;

    #[Assert\NotNull(message: '时间戳不能为空')]
    #[Assert\Range(
        min: 'now - 5 minutes',
        max: 'now + 5 minutes',
        notInRangeMessage: '时间戳无效，请同步系统时间'
    )]
    private int $timestamp;

    #[Assert\NotBlank(message: '指令ID不能为空')]
    #[Assert\Length(max: 64, maxMessage: '指令ID长度不能超过64个字符')]
    private string $instructionId;

    #[Assert\NotNull(message: '执行状态不能为空')]
    private ExecutionStatus $status;

    private ?string $output = null;

    private ?string $errorMessage = null;

    #[Assert\NotNull(message: '开始时间不能为空')]
    private \DateTimeImmutable $startTime;

    #[Assert\NotNull(message: '结束时间不能为空')]
    private \DateTimeImmutable $endTime;

    /**
     * @var array<string, mixed>
     */
    private array $executionMetrics = [];

    /**
     * @var array<string>|null
     */
    private ?array $screenshots = null;

    /**
     * @param array<string, mixed> $executionMetrics
     * @param array<string>|null   $screenshots
     */
    public function __construct(
        string $deviceCode,
        string $signature,
        int $timestamp,
        string $instructionId,
        ExecutionStatus $status,
        \DateTimeImmutable $startTime,
        \DateTimeImmutable $endTime,
        ?string $output = null,
        ?string $errorMessage = null,
        array $executionMetrics = [],
        ?array $screenshots = null,
    ) {
        $this->deviceCode = $deviceCode;
        $this->signature = $signature;
        $this->timestamp = $timestamp;
        $this->instructionId = $instructionId;
        $this->status = $status;
        $this->startTime = $startTime;
        $this->endTime = $endTime;
        $this->output = $output;
        $this->errorMessage = $errorMessage;
        $this->executionMetrics = $executionMetrics;
        $this->screenshots = $screenshots;
    }

    public function getDeviceCode(): string
    {
        return $this->deviceCode;
    }

    public function getSignature(): string
    {
        return $this->signature;
    }

    public function getTimestamp(): int
    {
        return $this->timestamp;
    }

    public function getInstructionId(): string
    {
        return $this->instructionId;
    }

    public function getStatus(): ExecutionStatus
    {
        return $this->status;
    }

    public function getOutput(): ?string
    {
        return $this->output;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function getStartTime(): \DateTimeImmutable
    {
        return $this->startTime;
    }

    public function getEndTime(): \DateTimeImmutable
    {
        return $this->endTime;
    }

    /**
     * @return array<string, mixed>
     */
    public function getExecutionMetrics(): array
    {
        return $this->executionMetrics;
    }

    /**
     * @return array<string>|null
     */
    public function getScreenshots(): ?array
    {
        return $this->screenshots;
    }

    /**
     * 获取执行耗时（秒）.
     */
    public function getExecutionDuration(): int
    {
        return $this->endTime->getTimestamp() - $this->startTime->getTimestamp();
    }

    /**
     * 验证签名.
     */
    public function verifySignature(string $certificate): bool
    {
        $data = sprintf(
            '%s:%s:%d:%s',
            $this->deviceCode,
            $this->instructionId,
            $this->timestamp,
            $certificate
        );

        $expectedSignature = hash_hmac('sha256', $data, $certificate);

        return hash_equals($expectedSignature, $this->signature);
    }
}
