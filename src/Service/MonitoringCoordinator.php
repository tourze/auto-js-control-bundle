<?php

declare(strict_types=1);

namespace Tourze\AutoJsControlBundle\Service;

use Symfony\Component\Console\Output\ConsoleSectionOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final class MonitoringCoordinator
{
    private ?ConsoleSectionOutput $statusSection = null;

    private ?ConsoleSectionOutput $queueSection = null;

    private ?ConsoleSectionOutput $instructionSection = null;

    public function __construct(
        private readonly QueueMonitorService $monitorService,
        private readonly ConsoleDisplayFormatter $displayFormatter,
    ) {
    }

    public function startMonitoring(?string $deviceCode, int $refreshInterval, int $limit, SymfonyStyle $io, OutputInterface $output): void
    {
        $this->initializeOutputSections($output);
        $this->displayMonitoringHeader($refreshInterval, $io);

        $iteration = 0;
        while (true) {
            ++$iteration;

            try {
                $this->performMonitoringIteration($deviceCode, $limit, $iteration, $io);
                sleep($refreshInterval);
            } catch (\Exception $e) {
                $io->error('监控过程中发生错误: ' . $e->getMessage());
                break;
            }
        }
    }

    private function initializeOutputSections(OutputInterface $output): void
    {
        if (method_exists($output, 'section')) {
            $this->statusSection = $output->section();
            $this->queueSection = $output->section();
            $this->instructionSection = $output->section();
        }
    }

    private function displayMonitoringHeader(int $refreshInterval, SymfonyStyle $io): void
    {
        $io->info(sprintf('开始实时监控（刷新间隔: %d秒）', $refreshInterval));
        $io->comment('按 Ctrl+C 退出监控');
        $io->newLine();
    }

    private function performMonitoringIteration(?string $deviceCode, int $limit, int $iteration, SymfonyStyle $io): void
    {
        if ($this->hasOutputSections()) {
            $this->updateMonitorDisplay($deviceCode, $limit, $iteration);
        } else {
            $this->fallbackToStaticDisplay($deviceCode, $limit, $io);
        }
    }

    private function hasOutputSections(): bool
    {
        return null !== $this->statusSection && null !== $this->queueSection;
    }

    private function fallbackToStaticDisplay(?string $deviceCode, int $limit, SymfonyStyle $io): void
    {
        $io->write("\033[2J\033[H");
        $io->title(sprintf('Auto.js 指令队列监控 - [%s]', (new \DateTime())->format('H:i:s')));
        $this->displayQueueStatus($deviceCode, $limit, $io);
    }

    private function updateMonitorDisplay(?string $deviceCode, int $limit, int $iteration): void
    {
        if (null !== $this->statusSection) {
            $this->displayFormatter->updateStatusSection($this->statusSection, $iteration);
        }

        if (null !== $deviceCode) {
            $this->updateSingleDeviceMonitor($deviceCode, $limit);
        } else {
            $this->updateAllDevicesMonitor($limit);
        }
    }

    private function updateSingleDeviceMonitor(string $deviceCode, int $limit): void
    {
        $deviceInfo = $this->monitorService->collectDeviceInfo($deviceCode);
        if (null !== $this->queueSection) {
            $this->displayFormatter->updateDeviceStatusSection($this->queueSection, $deviceCode, $deviceInfo);
        }

        $instructions = $this->monitorService->previewQueue($deviceCode, $limit);
        if (null !== $this->instructionSection) {
            $this->displayFormatter->updateInstructionsSection(
                $this->instructionSection,
                $instructions,
                $deviceInfo['queueLength'],
                $limit
            );
        }
    }

    private function updateAllDevicesMonitor(int $limit): void
    {
        $deviceStats = $this->monitorService->gatherDeviceStatistics();
        if (null !== $this->queueSection) {
            $this->displayFormatter->updateDevicesSummary($this->queueSection, $deviceStats);
        }

        $busyDevices = $this->monitorService->sortDevicesByQueueLength($deviceStats['busyDevices']);
        if (null !== $this->instructionSection) {
            $this->displayFormatter->updateBusyDevicesSection($this->instructionSection, $busyDevices, $limit);
        }
    }

    public function displayQueueStatus(?string $deviceCode, int $limit, SymfonyStyle $io): void
    {
        if (null !== $deviceCode) {
            $this->displaySingleDeviceStatus($deviceCode, $limit, $io);
        } else {
            $this->displayAllDevicesStatus($limit, $io);
        }
    }

    private function displaySingleDeviceStatus(string $deviceCode, int $limit, SymfonyStyle $io): void
    {
        $io->section(sprintf('设备 %s 队列状态', $deviceCode));

        $device = $this->monitorService->findDevice($deviceCode);
        if (null === $device) {
            $io->error(sprintf('设备 %s 不存在', $deviceCode));

            return;
        }

        $deviceInfo = $this->monitorService->collectDeviceInfo($deviceCode);
        $this->displayFormatter->displayDeviceInfo($device, $deviceCode, $deviceInfo['isOnline'], $deviceInfo, $io);

        $instructions = $this->monitorService->previewQueue($deviceCode, $limit);
        if ([] !== $instructions) {
            $io->section('待执行指令');
            $this->displayFormatter->displayInstructions($instructions, $io);
        } else {
            $io->info('队列中没有待执行的指令');
        }

        $this->displayFormatter->displayExecutionStats($deviceCode, $io);
    }

    private function displayAllDevicesStatus(int $limit, SymfonyStyle $io): void
    {
        $devices = $this->monitorService->getAllDevices();

        if ([] === $devices) {
            $io->warning('没有找到任何设备');

            return;
        }

        $stats = $this->monitorService->collectDeviceStatistics($devices);
        $this->displayFormatter->displayDevicesSummaryTable($stats, $io);
        $this->displayFormatter->displayDevicesListTable($stats['devices'], $io);
    }
}
