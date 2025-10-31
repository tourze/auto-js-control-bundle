<?php

declare(strict_types=1);

namespace Tourze\AutoJsControlBundle\Tests\Dto\Response;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tourze\AutoJsControlBundle\Dto\Response\ExecutionResultResponse;

/**
 * @internal
 */
#[CoversClass(ExecutionResultResponse::class)]
final class ExecutionResultResponseTest extends TestCase
{
    #[Test]
    public function constructorSetsProperties(): void
    {
        // Arrange
        $serverTime = new \DateTimeImmutable('2024-01-01 12:00:00');

        // Act
        $response = new ExecutionResultResponse(
            status: 'ok',
            instructionId: 'inst_123',
            message: 'Execution result saved',
            serverTime: $serverTime
        );

        // Assert
        $this->assertEquals('ok', $response->getStatus());
        $this->assertEquals('Execution result saved', $response->getMessage());
        $this->assertEquals('inst_123', $response->getInstructionId());
        $this->assertEquals($serverTime, $response->getServerTime());
    }

    #[Test]
    public function constructorWithDefaultServerTime(): void
    {
        // Arrange & Act
        $response = new ExecutionResultResponse(
            status: 'error',
            instructionId: 'inst_error',
            message: 'Failed to save result'
        );

        // Assert
        $this->assertEquals('error', $response->getStatus());
        $this->assertEquals('Failed to save result', $response->getMessage());
        $this->assertEquals('inst_error', $response->getInstructionId());
        $this->assertInstanceOf(\DateTimeImmutable::class, $response->getServerTime());
    }

    #[Test]
    public function createSuccessResponse(): void
    {
        // Act
        $response = ExecutionResultResponse::success('inst_456');

        // Assert
        $this->assertEquals('ok', $response->getStatus());
        $this->assertEquals('执行结果已记录', $response->getMessage());
        $this->assertEquals('inst_456', $response->getInstructionId());
    }

    #[Test]
    public function createSuccessResponseWithCustomMessage(): void
    {
        // Act
        $response = ExecutionResultResponse::success('inst_789', '自定义成功消息');

        // Assert
        $this->assertEquals('ok', $response->getStatus());
        $this->assertEquals('自定义成功消息', $response->getMessage());
        $this->assertEquals('inst_789', $response->getInstructionId());
    }

    #[Test]
    public function createErrorResponse(): void
    {
        // Act
        $response = ExecutionResultResponse::error('inst_err', '数据格式错误');

        // Assert
        $this->assertEquals('error', $response->getStatus());
        $this->assertEquals('数据格式错误', $response->getMessage());
        $this->assertEquals('inst_err', $response->getInstructionId());
    }

    #[Test]
    public function createNotFoundResponse(): void
    {
        // Act
        $response = ExecutionResultResponse::notFound('inst_missing');

        // Assert
        $this->assertEquals('not_found', $response->getStatus());
        $this->assertEquals('指令不存在或已过期', $response->getMessage());
        $this->assertEquals('inst_missing', $response->getInstructionId());
    }

    #[Test]
    public function createDuplicateResponse(): void
    {
        // Act
        $response = ExecutionResultResponse::duplicate('inst_dup');

        // Assert
        $this->assertEquals('duplicate', $response->getStatus());
        $this->assertEquals('该指令的执行结果已经上报过', $response->getMessage());
        $this->assertEquals('inst_dup', $response->getInstructionId());
    }

    #[Test]
    public function jsonSerializeReturnsCorrectData(): void
    {
        // Arrange
        $serverTime = new \DateTimeImmutable('2024-01-01 14:00:00');
        $response = new ExecutionResultResponse(
            status: 'ok',
            instructionId: 'inst_json_123',
            message: 'JSON test',
            serverTime: $serverTime
        );

        // Act
        $json = json_encode($response);
        $this->assertNotFalse($json);
        $decoded = json_decode($json, true);

        // Assert
        $this->assertEquals('ok', $decoded['status']);
        $this->assertEquals('JSON test', $decoded['message']);
        $this->assertEquals('inst_json_123', $decoded['instructionId']);
        $this->assertEquals($serverTime->format(\DateTimeInterface::RFC3339), $decoded['serverTime']);
    }

    #[Test]
    public function jsonSerializeWithoutMessage(): void
    {
        // Arrange
        $response = new ExecutionResultResponse(
            status: 'ok',
            instructionId: 'inst_no_msg'
        );

        // Act
        $json = json_encode($response);
        $this->assertNotFalse($json);
        $decoded = json_decode($json, true);

        // Assert
        $this->assertEquals('ok', $decoded['status']);
        $this->assertEquals('inst_no_msg', $decoded['instructionId']);
        $this->assertArrayNotHasKey('message', $decoded);
        $this->assertArrayHasKey('serverTime', $decoded);
    }
}
