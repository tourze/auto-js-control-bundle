<?php

namespace Tourze\AutoJsControlBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Tourze\AutoJsControlBundle\Dto\Request\ReportExecutionResultRequest;
use Tourze\AutoJsControlBundle\Entity\AutoJsDevice;
use Tourze\AutoJsControlBundle\Entity\ScriptExecutionRecord;
use Tourze\AutoJsControlBundle\Repository\ScriptExecutionRecordRepository;

/**
 * 设备报告服务
 *
 * 负责处理设备执行结果上报
 */
readonly class DeviceReportService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ScriptExecutionRecordRepository $executionRecordRepository,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * 处理执行结果上报.
     */
    public function processExecutionReport(
        ReportExecutionResultRequest $request,
        AutoJsDevice $autoJsDevice,
    ): ScriptExecutionRecord {
        $executionRecord = $this->findOrCreateExecutionRecord($request, $autoJsDevice);
        $this->updateExecutionRecord($executionRecord, $request);
        $this->processScreenshots($executionRecord, $request);

        $this->entityManager->persist($executionRecord);
        $this->entityManager->flush();

        return $executionRecord;
    }

    private function findOrCreateExecutionRecord(
        ReportExecutionResultRequest $request,
        AutoJsDevice $autoJsDevice,
    ): ScriptExecutionRecord {
        $executionRecord = $this->executionRecordRepository->findOneBy([
            'instructionId' => $request->getInstructionId(),
            'autoJsDevice' => $autoJsDevice,
        ]);

        if (null === $executionRecord) {
            $executionRecord = new ScriptExecutionRecord();
            $executionRecord->setAutoJsDevice($autoJsDevice);
            $executionRecord->setInstructionId($request->getInstructionId());
        }

        return $executionRecord;
    }

    private function updateExecutionRecord(
        ScriptExecutionRecord $executionRecord,
        ReportExecutionResultRequest $request,
    ): void {
        $executionRecord->setStatus($request->getStatus());
        $executionRecord->setStartTime($request->getStartTime());
        $executionRecord->setEndTime($request->getEndTime());
        $executionRecord->setOutput($request->getOutput());
        $executionRecord->setErrorMessage($request->getErrorMessage());
        $executionRecord->setExecutionMetrics($request->getExecutionMetrics());
    }

    private function processScreenshots(
        ScriptExecutionRecord $executionRecord,
        ReportExecutionResultRequest $request,
    ): void {
        $screenshots = $request->getScreenshots();
        if (null === $screenshots || [] === $screenshots) {
            return;
        }

        $screenshotPaths = [];
        foreach ($screenshots as $index => $screenshot) {
            $filename = sprintf(
                'screenshot_%s_%s_%d.png',
                $request->getInstructionId(),
                date('YmdHis'),
                $index
            );

            $path = $this->saveBase64Image($screenshot, $filename);
            if (null !== $path) {
                $screenshotPaths[] = $path;
            }
        }

        $executionRecord->setScreenshots($screenshotPaths);
    }

    private function saveBase64Image(string $base64Data, string $filename): ?string
    {
        try {
            $cleanData = preg_replace('/^data:image\/\w+;base64,/', '', $base64Data);
            if (null === $cleanData) {
                return null;
            }

            $data = base64_decode($cleanData, true);
            if (false === $data) {
                return null;
            }

            $uploadDir = $_ENV['KERNEL_PROJECT_DIR'] . '/var/uploads/screenshots';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0o755, true);
            }

            $filepath = $uploadDir . '/' . $filename;
            file_put_contents($filepath, $data);

            return 'screenshots/' . $filename;
        } catch (\Exception $e) {
            $this->logger->error('保存Base64图片失败', [
                'filename' => $filename,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
