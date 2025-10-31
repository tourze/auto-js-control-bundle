<?php

declare(strict_types=1);

namespace Tourze\AutoJsControlBundle\Tests\Dto\Request;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tourze\AutoJsControlBundle\Dto\Request\ReportExecutionResultRequest;
use Tourze\AutoJsControlBundle\Enum\ExecutionStatus;

/**
 * @internal
 */
#[CoversClass(ReportExecutionResultRequest::class)]
final class ReportExecutionResultRequestTest extends TestCase
{
    #[Test]
    public function constructorSetsProperties(): void
    {
        // Arrange
        $deviceCode = 'TEST_DEVICE_001';
        $signature = 'test_signature';
        $timestamp = time();
        $instructionId = 'exec_123456';
        $status = ExecutionStatus::SUCCESS;
        $startTime = new \DateTimeImmutable('2024-01-01 10:00:00');
        $endTime = new \DateTimeImmutable('2024-01-01 10:05:00');
        $output = 'Script executed successfully';
        $errorMessage = null;
        $executionMetrics = ['cpuUsage' => 45.5, 'memoryUsage' => '128MB'];
        $screenshots = null;

        // Act
        $request = new ReportExecutionResultRequest(
            deviceCode: $deviceCode,
            signature: $signature,
            timestamp: $timestamp,
            instructionId: $instructionId,
            status: $status,
            startTime: $startTime,
            endTime: $endTime,
            output: $output,
            errorMessage: $errorMessage,
            executionMetrics: $executionMetrics,
            screenshots: $screenshots
        );

        // Assert
        $this->assertEquals($deviceCode, $request->getDeviceCode());
        $this->assertEquals($signature, $request->getSignature());
        $this->assertEquals($timestamp, $request->getTimestamp());
        $this->assertEquals($instructionId, $request->getInstructionId());
        $this->assertEquals($status, $request->getStatus());
        $this->assertEquals($startTime, $request->getStartTime());
        $this->assertEquals($endTime, $request->getEndTime());
        $this->assertEquals($output, $request->getOutput());
        $this->assertNull($request->getErrorMessage());
        $this->assertEquals($executionMetrics, $request->getExecutionMetrics());
        $this->assertNull($request->getScreenshots());
    }

    #[Test]
    public function constructorWithErrorStatus(): void
    {
        // Arrange
        $timestamp = time();
        $startTime = new \DateTimeImmutable();
        $endTime = new \DateTimeImmutable('+5 minutes');

        $request = new ReportExecutionResultRequest(
            deviceCode: 'ERROR_DEVICE',
            signature: 'error_signature',
            timestamp: $timestamp,
            instructionId: 'error_exec_001',
            status: ExecutionStatus::FAILED,
            startTime: $startTime,
            endTime: $endTime,
            output: null,
            errorMessage: 'Script execution failed: Syntax error',
            executionMetrics: ['error_code' => 'SYNTAX_ERROR']
        );

        // Assert
        $this->assertEquals(ExecutionStatus::FAILED, $request->getStatus());
        $this->assertNull($request->getOutput());
        $this->assertEquals('Script execution failed: Syntax error', $request->getErrorMessage());
        $this->assertEquals(['error_code' => 'SYNTAX_ERROR'], $request->getExecutionMetrics());
    }

    #[Test]
    public function getExecutionDurationCalculatesCorrectly(): void
    {
        // Arrange
        $startTime = new \DateTimeImmutable('2024-01-01 12:00:00');
        $endTime = new \DateTimeImmutable('2024-01-01 12:05:30');

        $request = new ReportExecutionResultRequest(
            deviceCode: 'DEVICE_002',
            signature: 'duration_signature',
            timestamp: time(),
            instructionId: 'exec_789',
            status: ExecutionStatus::SUCCESS,
            startTime: $startTime,
            endTime: $endTime
        );

        // Act
        $duration = $request->getExecutionDuration();

        // Assert
        $this->assertEquals(330, $duration); // 5 minutes 30 seconds = 330 seconds
    }

    #[Test]
    public function verifySignatureWithValidSignature(): void
    {
        // Arrange
        $deviceCode = 'DEVICE_003';
        $instructionId = 'exec_minimal';
        $timestamp = time();
        $certificate = 'test_certificate';

        $data = sprintf('%s:%s:%d:%s', $deviceCode, $instructionId, $timestamp, $certificate);
        $signature = hash_hmac('sha256', $data, $certificate);

        $request = new ReportExecutionResultRequest(
            deviceCode: $deviceCode,
            signature: $signature,
            timestamp: $timestamp,
            instructionId: $instructionId,
            status: ExecutionStatus::PENDING,
            startTime: new \DateTimeImmutable(),
            endTime: new \DateTimeImmutable()
        );

        // Act & Assert
        $this->assertTrue($request->verifySignature($certificate));
    }

    #[Test]
    public function verifySignatureWithInvalidSignature(): void
    {
        // Arrange
        $request = new ReportExecutionResultRequest(
            deviceCode: 'DEVICE_004',
            signature: 'invalid_signature',
            timestamp: time(),
            instructionId: 'exec_array_test',
            status: ExecutionStatus::SUCCESS,
            startTime: new \DateTimeImmutable('2024-01-01 14:00:00'),
            endTime: new \DateTimeImmutable('2024-01-01 14:10:00')
        );

        // Act & Assert
        $this->assertFalse($request->verifySignature('test_certificate'));
    }

    #[Test]
    public function constructorWithTimeoutStatus(): void
    {
        // Arrange
        $startTime = new \DateTimeImmutable('2024-01-01 15:00:00');
        $endTime = new \DateTimeImmutable('2024-01-01 15:30:00');

        $request = new ReportExecutionResultRequest(
            deviceCode: 'DEVICE_005',
            signature: 'timeout_signature',
            timestamp: time(),
            instructionId: 'exec_timeout',
            status: ExecutionStatus::TIMEOUT,
            startTime: $startTime,
            endTime: $endTime,
            errorMessage: 'Execution timeout after 30 minutes'
        );

        // Assert
        $this->assertEquals(ExecutionStatus::TIMEOUT, $request->getStatus());
        $this->assertEquals('Execution timeout after 30 minutes', $request->getErrorMessage());
        $this->assertEquals(1800, $request->getExecutionDuration()); // 30 minutes = 1800 seconds
    }

    #[Test]
    public function constructorWithScreenshots(): void
    {
        // Arrange
        $screenshots = [
            'screenshot1.png',
            'screenshot2.png',
        ];

        $request = new ReportExecutionResultRequest(
            deviceCode: 'DEVICE_006',
            signature: 'screenshots_signature',
            timestamp: time(),
            instructionId: 'exec_screenshots',
            status: ExecutionStatus::SUCCESS,
            startTime: new \DateTimeImmutable(),
            endTime: new \DateTimeImmutable(),
            screenshots: $screenshots
        );

        // Assert
        $this->assertIsArray($request->getScreenshots());
        $this->assertCount(2, $request->getScreenshots());
        $this->assertEquals($screenshots, $request->getScreenshots());
    }

    #[Test]
    public function constructorWithComplexExecutionMetrics(): void
    {
        // Arrange
        $executionMetrics = [
            'cpuUsage' => 85.5,
            'memoryUsage' => '512MB',
            'peakMemory' => '768MB',
            'executionTime' => 120.5,
            'scriptLines' => 1500,
            'errors' => 0,
            'warnings' => 3,
        ];

        $request = new ReportExecutionResultRequest(
            deviceCode: 'DEVICE_007',
            signature: 'metrics_signature',
            timestamp: time(),
            instructionId: 'exec_metrics',
            status: ExecutionStatus::SUCCESS,
            startTime: new \DateTimeImmutable(),
            endTime: new \DateTimeImmutable(),
            executionMetrics: $executionMetrics
        );

        // Assert
        $this->assertEquals($executionMetrics, $request->getExecutionMetrics());
        $this->assertArrayHasKey('cpuUsage', $request->getExecutionMetrics());
        $this->assertArrayHasKey('memoryUsage', $request->getExecutionMetrics());
    }

    #[Test]
    public function constructorWithMinimalRequiredParameters(): void
    {
        // Arrange
        $deviceCode = 'DEVICE_008';
        $signature = 'minimal_signature';
        $timestamp = time();
        $instructionId = 'exec_minimal';
        $status = ExecutionStatus::SUCCESS;
        $startTime = new \DateTimeImmutable();
        $endTime = new \DateTimeImmutable();

        // Act
        $request = new ReportExecutionResultRequest(
            deviceCode: $deviceCode,
            signature: $signature,
            timestamp: $timestamp,
            instructionId: $instructionId,
            status: $status,
            startTime: $startTime,
            endTime: $endTime
        );

        // Assert
        $this->assertEquals($deviceCode, $request->getDeviceCode());
        $this->assertEquals($signature, $request->getSignature());
        $this->assertEquals($timestamp, $request->getTimestamp());
        $this->assertEquals($instructionId, $request->getInstructionId());
        $this->assertEquals($status, $request->getStatus());
        $this->assertEquals($startTime, $request->getStartTime());
        $this->assertEquals($endTime, $request->getEndTime());
        $this->assertNull($request->getOutput());
        $this->assertNull($request->getErrorMessage());
        $this->assertEquals([], $request->getExecutionMetrics());
        $this->assertNull($request->getScreenshots());
    }
}
