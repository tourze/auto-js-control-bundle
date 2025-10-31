<?php

namespace Tourze\AutoJsControlBundle\Tests\Service;

use DeviceBundle\Entity\Device as BaseDevice;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Tourze\AutoJsControlBundle\Dto\Request\ReportExecutionResultRequest;
use Tourze\AutoJsControlBundle\Entity\AutoJsDevice;
use Tourze\AutoJsControlBundle\Entity\ScriptExecutionRecord;
use Tourze\AutoJsControlBundle\Enum\ExecutionStatus;
use Tourze\AutoJsControlBundle\Repository\ScriptExecutionRecordRepository;
use Tourze\AutoJsControlBundle\Service\DeviceReportService;

/**
 * @internal
 */
#[CoversClass(DeviceReportService::class)]
final class DeviceReportServiceTest extends TestCase
{
    private DeviceReportService $deviceReportService;

    private EntityManagerInterface&MockObject $entityManager;

    private ScriptExecutionRecordRepository&MockObject $executionRecordRepository;

    private LoggerInterface&MockObject $logger;

    protected function onSetUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->executionRecordRepository = $this->createMock(ScriptExecutionRecordRepository::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->deviceReportService = new DeviceReportService(
            $this->entityManager,
            $this->executionRecordRepository,
            $this->logger
        );
    }

    public function testServiceExists(): void
    {
        $this->assertInstanceOf(DeviceReportService::class, $this->deviceReportService);
    }

    #[Test]
    public function testProcessExecutionReportWithNewRecordCreatesAndReturnsRecord(): void
    {
        // Arrange
        $request = $this->createExecutionResultRequest();
        $autoJsDevice = $this->createAutoJsDevice();

        $this->executionRecordRepository->expects($this->once())
            ->method('findOneBy')
            ->with([
                'instructionId' => 'instruction_123',
                'autoJsDevice' => $autoJsDevice,
            ])
            ->willReturn(null)
        ;

        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with(self::isInstanceOf(ScriptExecutionRecord::class))
        ;

        $this->entityManager->expects($this->once())
            ->method('flush')
        ;

        // Act
        $result = $this->deviceReportService->processExecutionReport($request, $autoJsDevice);

        // Assert
        $this->assertInstanceOf(ScriptExecutionRecord::class, $result);
        $this->assertEquals('instruction_123', $result->getInstructionId());
        $this->assertEquals(ExecutionStatus::SUCCESS, $result->getStatus());
        $this->assertEquals('Script executed successfully', $result->getOutput());
        $this->assertNull($result->getErrorMessage());
    }

    #[Test]
    public function testProcessExecutionReportWithExistingRecordUpdatesAndReturnsRecord(): void
    {
        // Arrange
        $request = $this->createExecutionResultRequest();
        $autoJsDevice = $this->createAutoJsDevice();

        $existingRecord = new ScriptExecutionRecord();
        $existingRecord->setAutoJsDevice($autoJsDevice);
        $existingRecord->setInstructionId('instruction_123');
        $existingRecord->setStatus(ExecutionStatus::RUNNING);

        $this->executionRecordRepository->expects($this->once())
            ->method('findOneBy')
            ->with([
                'instructionId' => 'instruction_123',
                'autoJsDevice' => $autoJsDevice,
            ])
            ->willReturn($existingRecord)
        ;

        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($existingRecord)
        ;

        $this->entityManager->expects($this->once())
            ->method('flush')
        ;

        // Act
        $result = $this->deviceReportService->processExecutionReport($request, $autoJsDevice);

        // Assert
        $this->assertSame($existingRecord, $result);
        $this->assertEquals(ExecutionStatus::SUCCESS, $result->getStatus());
        $this->assertEquals('Script executed successfully', $result->getOutput());
    }

    #[Test]
    public function testProcessExecutionReportWithErrorUpdatesErrorMessage(): void
    {
        // Arrange
        $request = $this->createExecutionResultRequestWithError();
        $autoJsDevice = $this->createAutoJsDevice();

        $this->executionRecordRepository->expects($this->once())
            ->method('findOneBy')
            ->willReturn(null)
        ;

        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with(self::callback(function (ScriptExecutionRecord $record) {
                return ExecutionStatus::FAILED === $record->getStatus()
                    && 'Script execution failed' === $record->getErrorMessage();
            }))
        ;

        $this->entityManager->expects($this->once())
            ->method('flush')
        ;

        // Act
        $result = $this->deviceReportService->processExecutionReport($request, $autoJsDevice);

        // Assert
        $this->assertEquals(ExecutionStatus::FAILED, $result->getStatus());
        $this->assertEquals('Script execution failed', $result->getErrorMessage());
    }

    #[Test]
    public function testProcessExecutionReportWithMetricsUpdatesMetrics(): void
    {
        // Arrange
        $request = $this->createExecutionResultRequestWithMetrics();
        $autoJsDevice = $this->createAutoJsDevice();

        $this->executionRecordRepository->expects($this->once())
            ->method('findOneBy')
            ->willReturn(null)
        ;

        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with(self::callback(function (ScriptExecutionRecord $record) {
                $metrics = $record->getExecutionMetrics();

                return isset($metrics['duration']) && 5.5 === $metrics['duration']
                    && isset($metrics['memory']) && 1024 === $metrics['memory'];
            }))
        ;

        $this->entityManager->expects($this->once())
            ->method('flush')
        ;

        // Act
        $result = $this->deviceReportService->processExecutionReport($request, $autoJsDevice);

        // Assert
        $metrics = $result->getExecutionMetrics();
        $this->assertIsArray($metrics, 'Execution metrics should be an array');
        $this->assertArrayHasKey('duration', $metrics, 'Metrics should contain duration key');
        $this->assertArrayHasKey('memory', $metrics, 'Metrics should contain memory key');
        $this->assertEquals(5.5, $metrics['duration']);
        $this->assertEquals(1024, $metrics['memory']);
    }

    private function createAutoJsDevice(): AutoJsDevice
    {
        $baseDevice = new BaseDevice();
        $baseDevice->setCode('TEST_DEVICE');

        $autoJsDevice = new AutoJsDevice();
        $autoJsDevice->setBaseDevice($baseDevice);

        // 使用反射设置ID
        $reflection = new \ReflectionClass($autoJsDevice);
        $idProperty = $reflection->getProperty('id');
        $idProperty->setAccessible(true);
        $idProperty->setValue($autoJsDevice, 123);

        return $autoJsDevice;
    }

    private function createExecutionResultRequest(): ReportExecutionResultRequest
    {
        return new ReportExecutionResultRequest(
            'TEST_DEVICE',
            'test_signature',
            time(),
            'instruction_123',
            ExecutionStatus::SUCCESS,
            new \DateTimeImmutable('2024-01-01 10:00:00'),
            new \DateTimeImmutable('2024-01-01 10:05:00'),
            'Script executed successfully',
            null,
            [],
            []
        );
    }

    private function createExecutionResultRequestWithError(): ReportExecutionResultRequest
    {
        return new ReportExecutionResultRequest(
            'TEST_DEVICE',
            'test_signature',
            time(),
            'instruction_error',
            ExecutionStatus::FAILED,
            new \DateTimeImmutable('2024-01-01 10:00:00'),
            new \DateTimeImmutable('2024-01-01 10:02:00'),
            '',
            'Script execution failed',
            [],
            []
        );
    }

    private function createExecutionResultRequestWithMetrics(): ReportExecutionResultRequest
    {
        return new ReportExecutionResultRequest(
            'TEST_DEVICE',
            'test_signature',
            time(),
            'instruction_metrics',
            ExecutionStatus::SUCCESS,
            new \DateTimeImmutable('2024-01-01 10:00:00'),
            new \DateTimeImmutable('2024-01-01 10:05:30'),
            'Script with metrics',
            null,
            [
                'duration' => 5.5,
                'memory' => 1024,
                'cpu' => 25.3,
            ],
            []
        );
    }
}
