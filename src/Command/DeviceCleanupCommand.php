<?php

declare(strict_types=1);

namespace Tourze\AutoJsControlBundle\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Tourze\AutoJsControlBundle\Entity\AutoJsDevice;
use Tourze\AutoJsControlBundle\Repository\AutoJsDeviceRepository;
use Tourze\AutoJsControlBundle\Service\DeviceHeartbeatService;
use Tourze\AutoJsControlBundle\Service\DeviceManager;

#[AsCommand(name: self::NAME, description: '清理离线设备和过期数据', aliases: ['autojs:device:cleanup'], help: <<<'TXT'
    <info>%command.name%</info> 命令用于清理长时间离线的设备和过期数据。

    示例：
      <comment>%command.full_name%</comment>                        # 清理离线超过30天的设备
      <comment>%command.full_name% --offline-days=7</comment>       # 清理离线超过7天的设备
      <comment>%command.full_name% --dry-run</comment>              # 模拟运行，查看将要清理的设备
      <comment>%command.full_name% --force</comment>                 # 强制执行，不需要确认
      <comment>%command.full_name% --delete-permanently</comment>   # 永久删除设备记录

    注意：
      - 默认执行软删除（标记为已删除状态）
      - 使用 --delete-permanently 将永久删除数据，此操作不可恢复
      - 建议先使用 --dry-run 查看将要清理的设备
    TXT)]
final class DeviceCleanupCommand extends Command
{
    public const NAME = 'auto-js:device:cleanup';

    public function __construct(
        private readonly DeviceManager $deviceManager,
        private readonly DeviceHeartbeatService $heartbeatService,
        private readonly AutoJsDeviceRepository $deviceRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('offline-days', 'd', InputOption::VALUE_REQUIRED, '离线天数阈值（超过该天数的设备将被清理）', '30')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, '模拟运行，只显示将要清理的设备但不执行清理')
            ->addOption('force', 'f', InputOption::VALUE_NONE, '强制执行，不需要确认')
            ->addOption('delete-permanently', null, InputOption::VALUE_NONE, '永久删除而不是软删除')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $offlineDaysOption = $input->getOption('offline-days');
        $offlineDays = is_numeric($offlineDaysOption) ? (int) $offlineDaysOption : 0;
        $dryRun = (bool) $input->getOption('dry-run');
        $force = (bool) $input->getOption('force');
        $deletePermanently = (bool) $input->getOption('delete-permanently');

        $io->title('Auto.js 设备清理');

        if ($dryRun) {
            $io->note('模拟运行模式：只显示将要清理的设备，不会执行实际清理操作');
        }

        if ($deletePermanently) {
            $io->warning('永久删除模式：设备数据将被永久删除，此操作不可恢复！');
        }

        try {
            // 查找需要清理的设备
            $devicesToClean = $this->findDevicesToClean($offlineDays);

            if ([] === $devicesToClean) {
                $io->success(sprintf('没有找到离线超过 %d 天的设备，无需清理', $offlineDays));

                return Command::SUCCESS;
            }

            // 显示将要清理的设备
            $this->displayDevicesToClean($devicesToClean, $io);

            if ($dryRun) {
                $io->info(sprintf('模拟运行完成：发现 %d 个设备需要清理', count($devicesToClean)));

                return Command::SUCCESS;
            }

            // 确认清理
            if (!$force && !$this->confirmCleanup($io, count($devicesToClean), $deletePermanently)) {
                $io->comment('清理操作已取消');

                return Command::SUCCESS;
            }

            // 执行清理
            $cleanedCount = $this->performCleanup($devicesToClean, $deletePermanently, $io);

            $io->success(sprintf('清理完成：成功清理 %d 个设备', $cleanedCount));

            // 显示清理后的统计信息
            $this->showCleanupStatistics($io);
        } catch (\Exception $e) {
            $io->error('清理设备失败: ' . $e->getMessage());

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    /**
     * @return array<int, AutoJsDevice>
     */
    private function findDevicesToClean(int $offlineDays): array
    {
        // 使用预加载方法避免懒加载导致的代理对象问题
        $devices = $this->deviceRepository->findAllWithBaseDevice();
        $devicesToClean = [];
        $cutoffDate = new \DateTimeImmutable(sprintf('-%d days', $offlineDays));

        foreach ($devices as $device) {
            if ($this->shouldCleanDevice($device, $cutoffDate)) {
                $devicesToClean[] = $device;
            }
        }

        return $devicesToClean;
    }

    private function shouldCleanDevice(AutoJsDevice $device, \DateTimeImmutable $cutoffDate): bool
    {
        $baseDevice = $device->getBaseDevice();
        if (null === $baseDevice) {
            return false;
        }
        $deviceCode = $baseDevice->getCode();

        if ($this->heartbeatService->isDeviceOnline($deviceCode)) {
            return false;
        }

        return $this->isDeviceOldEnough($device, $cutoffDate);
    }

    private function isDeviceOldEnough(AutoJsDevice $device, \DateTimeImmutable $cutoffDate): bool
    {
        $baseDevice = $device->getBaseDevice();
        if (null === $baseDevice) {
            return false;
        }
        $lastOnlineTime = $baseDevice->getLastOnlineTime();

        if (null !== $lastOnlineTime) {
            return $lastOnlineTime <= $cutoffDate;
        }

        $createTime = $device->getCreateTime();

        return null === $createTime || $createTime <= $cutoffDate;
    }

    /**
     * @param array<int, AutoJsDevice> $devices
     */
    private function displayDevicesToClean(array $devices, SymfonyStyle $io): void
    {
        $io->section(sprintf('将要清理的设备（共 %d 个）', count($devices)));

        $tableData = [];
        foreach ($devices as $device) {
            $baseDevice = $device->getBaseDevice();
            if (null === $baseDevice) {
                continue;
            }
            $lastOnlineTime = $baseDevice->getLastOnlineTime();

            $offlineDays = 'N/A';
            if (null !== $lastOnlineTime) {
                $now = new \DateTimeImmutable();
                $interval = $now->diff($lastOnlineTime);
                $offlineDays = $interval->days . ' 天';
            }

            $tableData[] = [
                $device->getId(),
                $baseDevice->getCode(),
                $baseDevice->getName(),
                $baseDevice->getModel() ?? 'N/A',
                $lastOnlineTime?->format('Y-m-d H:i:s') ?? 'N/A',
                $offlineDays,
            ];
        }

        $io->table(
            ['ID', '设备代码', '设备名称', '型号', '最后在线时间', '离线时长'],
            $tableData
        );
    }

    private function confirmCleanup(SymfonyStyle $io, int $count, bool $deletePermanently): bool
    {
        $action = $deletePermanently ? '永久删除' : '标记为已删除';
        $question = sprintf('确定要%s这 %d 个设备吗？', $action, $count);

        return $io->confirm($question, false);
    }

    /**
     * @param array<int, AutoJsDevice> $devices
     */
    private function performCleanup(array $devices, bool $deletePermanently, SymfonyStyle $io): int
    {
        $progressBar = $this->createProgressBar($io, count($devices));
        $cleanedCount = 0;
        $errors = [];

        foreach ($devices as $device) {
            $result = $this->cleanupDevice($device, $deletePermanently, $io, $progressBar);
            if ($result['success']) {
                ++$cleanedCount;
            } else {
                $errors[] = $result['error'];
            }
        }

        $progressBar->finish();
        $io->newLine(2);

        $this->displayErrors($errors, $io);

        return $cleanedCount;
    }

    private function createProgressBar(SymfonyStyle $io, int $total): ProgressBar
    {
        $progressBar = new ProgressBar($io, $total);
        $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %message%');
        $progressBar->start();

        return $progressBar;
    }

    /**
     * @return array{success: bool, error: string}
     */
    private function cleanupDevice(AutoJsDevice $device, bool $deletePermanently, SymfonyStyle $io, ProgressBar $progressBar): array
    {
        $baseDevice = $device->getBaseDevice();
        if (null === $baseDevice) {
            return ['success' => false, 'error' => ''];
        }

        $deviceCode = $baseDevice->getCode();
        $progressBar->setMessage(sprintf('清理设备: %s', $deviceCode));
        $progressBar->advance();

        try {
            $this->deleteDevice($deviceCode, $deletePermanently, $io);

            return ['success' => true, 'error' => ''];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => sprintf('设备 %s 清理失败: %s', $deviceCode, $e->getMessage())];
        }
    }

    private function deleteDevice(string $deviceCode, bool $deletePermanently, SymfonyStyle $io): void
    {
        if ($deletePermanently) {
            $io->writeln(sprintf("\n<comment>永久删除功能暂未实现，设备 %s 将被软删除</comment>", $deviceCode));
        }
        $this->deviceManager->deleteDevice($deviceCode);
    }

    /**
     * @param array<string> $errors
     */
    private function displayErrors(array $errors, SymfonyStyle $io): void
    {
        if ([] === $errors) {
            return;
        }

        $io->error('部分设备清理失败：');
        foreach ($errors as $error) {
            $io->writeln('  - ' . $error);
        }
    }

    private function showCleanupStatistics(SymfonyStyle $io): void
    {
        $stats = $this->deviceManager->getDeviceStatistics();

        // 使用安全的类型转换和验证
        $total = $this->safelyParseInt($stats['total'] ?? 0);
        $online = $this->safelyParseInt($stats['online'] ?? 0);
        $offline = $this->safelyParseInt($stats['offline'] ?? 0);

        $io->section('清理后设备统计');

        $io->table(
            ['指标', '数值'],
            [
                ['设备总数', $total],
                ['在线设备', sprintf('<fg=green>%d</>', $online)],
                ['离线设备', sprintf('<fg=yellow>%d</>', $offline)],
            ]
        );

        // 清理建议
        if ($offline > 0) {
            $io->note([
                sprintf('仍有 %d 个离线设备', $offline),
                '您可以调整离线天数阈值来清理更多设备',
            ]);
        }
    }

    /**
     * 安全地将混合类型转换为整数
     */
    private function safelyParseInt(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && is_numeric($value)) {
            return (int) $value;
        }

        if (is_float($value)) {
            return (int) $value;
        }

        return 0;
    }
}
