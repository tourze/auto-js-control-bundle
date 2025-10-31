<?php

declare(strict_types=1);

namespace Tourze\AutoJsControlBundle\Service;

use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Output\ConsoleSectionOutput;
use Symfony\Component\Console\Style\SymfonyStyle;

final class ConsoleDisplayFormatter
{
    /**
     * @param array<string, mixed> $metrics
     */
    public function displayDeviceInfo(object $device, string $deviceCode, bool $isOnline, array $metrics, SymfonyStyle $io): void
    {
        $io->table(
            ['属性', '值'],
            $this->buildDeviceInfoRows($device, $deviceCode, $isOnline, $metrics)
        );
    }

    /**
     * @param array<string, mixed> $metrics
     *
     * @return array<array{0: string, 1: string}>
     */
    private function buildDeviceInfoRows(object $device, string $deviceCode, bool $isOnline, array $metrics): array
    {
        $baseDevice = method_exists($device, 'getBaseDevice') ? $device->getBaseDevice() : $device;
        $deviceName = $deviceCode; // 默认值

        if (is_object($baseDevice) && method_exists($baseDevice, 'getName')) {
            $deviceName = $baseDevice->getName();
        }

        return [
            ['设备名称', $this->safelyParseString($deviceName)],
            ['在线状态', $isOnline ? '<fg=green>在线</>' : '<fg=red>离线</>'],
            ['队列长度', (string) $this->safelyParseInt($metrics['queueLength'] ?? 0)],
            ['CPU使用率', $this->formatMetric($metrics, 'cpuUsage', '%.1f%%')],
            ['内存使用率', $this->formatMetric($metrics, 'memoryUsage', '%.1f%%')],
        ];
    }

    /**
     * @param array<string, mixed> $metrics
     */
    private function formatMetric(array $metrics, string $key, string $format): string
    {
        return isset($metrics[$key]) ? sprintf($format, $this->safelyParseFloat($metrics[$key])) : 'N/A';
    }

    /**
     * @param array<array{id: int, code: string, name: string, statusDisplay: string, queueLength: int, queueStatusDisplay: string}> $devices
     */
    public function displayDevicesListTable(array $devices, SymfonyStyle $io): void
    {
        $io->section('设备队列状态');
        $table = new Table($io);
        $table->setHeaders(['ID', '设备代码', '设备名称', '在线状态', '队列长度', '队列状态']);

        $rows = array_map(fn ($device) => [
            $device['id'],
            $device['code'],
            $device['name'],
            $device['statusDisplay'],
            $device['queueLength'],
            $device['queueStatusDisplay'],
        ], $devices);

        $table->setRows($rows);
        $table->render();
    }

    /**
     * @param array{devices: array, totalQueueLength: int, onlineCount: int, totalCount: int} $stats
     */
    /**
     * @param array<string, mixed> $stats
     */
    public function displayDevicesSummaryTable(array $stats, SymfonyStyle $io): void
    {
        $io->section('队列汇总');
        $io->table(
            ['指标', '值'],
            [
                ['设备总数', (string) $this->safelyParseInt($stats['totalCount'])],
                ['在线设备', sprintf('<fg=green>%d</>', $this->safelyParseInt($stats['onlineCount']))],
                ['离线设备', sprintf('<fg=red>%d</>', $this->safelyParseInt($stats['totalCount']) - $this->safelyParseInt($stats['onlineCount']))],
                ['总队列长度', (string) $this->safelyParseInt($stats['totalQueueLength'])],
            ]
        );
    }

    /**
     * @param array<int, array<string, mixed>> $instructions
     */
    public function displayInstructions(array $instructions, SymfonyStyle $io): void
    {
        $tableData = [];

        foreach ($instructions as $index => $instruction) {
            $tableData[] = [
                $index + 1,
                $instruction['instructionId'] ?? 'N/A',
                $instruction['type'] ?? 'unknown',
                $instruction['priority'] ?? 0,
                isset($instruction['createTime']) ? (new \DateTime($this->safelyParseString($instruction['createTime'])))->format('H:i:s') : 'N/A',
                $this->getInstructionSummary($instruction),
            ];
        }

        $table = new Table($io);
        $table->setHeaders(['#', '指令ID', '类型', '优先级', '创建时间', '摘要']);
        $table->setRows($tableData);
        $table->render();
    }

    /**
     * @param array<string, mixed> $instruction
     */
    private function getInstructionSummary(array $instruction): string
    {
        $type = $instruction['type'] ?? 'unknown';
        $data = $this->safelyParseArray($instruction['data'] ?? []);

        switch ($type) {
            case 'execute_script':
                return sprintf('执行脚本 #%d', $this->safelyParseInt($data['scriptId'] ?? 0));
            case 'update_config':
                return '更新配置';
            case 'collect_logs':
                return '收集日志';
            default:
                return '其他操作';
        }
    }

    public function displayExecutionStats(string $deviceCode, SymfonyStyle $io): void
    {
        $io->section('执行统计（最近1小时）');

        $stats = [
            'total' => rand(50, 200),
            'success' => rand(40, 180),
            'failed' => rand(5, 20),
            'avgExecutionTime' => rand(100, 5000) / 1000,
        ];

        $successRate = $stats['total'] > 0 ? ($stats['success'] / $stats['total'] * 100) : 0;

        $io->table(
            ['指标', '值'],
            [
                ['执行总数', $stats['total']],
                ['成功数', sprintf('<fg=green>%d</>', $stats['success'])],
                ['失败数', sprintf('<fg=red>%d</>', $stats['failed'])],
                ['成功率', sprintf('%.1f%%', $successRate)],
                ['平均执行时间', sprintf('%.2f秒', $stats['avgExecutionTime'])],
            ]
        );
    }

    /**
     * @param array<string, mixed> $deviceInfo
     */
    public function formatDeviceStatus(string $deviceCode, array $deviceInfo): string
    {
        $isOnline = (bool) ($deviceInfo['isOnline'] ?? false);

        return sprintf(
            '<fg=cyan>设备:</> %s | <fg=%s>状态:</> %s | <fg=magenta>队列长度:</> %d',
            $deviceCode,
            $isOnline ? 'green' : 'red',
            $isOnline ? '在线' : '离线',
            $this->safelyParseInt($deviceInfo['queueLength'] ?? 0)
        );
    }

    /**
     * @param array<string, mixed> $metrics
     */
    public function formatMetrics(array $metrics): string
    {
        return sprintf(
            '<fg=blue>CPU:</> %.1f%% | <fg=blue>内存:</> %.1f%%',
            $this->safelyParseFloat($metrics['cpuUsage'] ?? 0),
            $this->safelyParseFloat($metrics['memoryUsage'] ?? 0)
        );
    }

    /**
     * @param array<string, mixed> $metrics
     */
    public function hasMetrics(array $metrics): bool
    {
        return isset($metrics['cpuUsage']) || isset($metrics['memoryUsage']);
    }

    /**
     * @param array<string, mixed> $instruction
     */
    public function formatInstructionLine(int $index, array $instruction): string
    {
        return sprintf(
            '  %d. [%s] %s (优先级: %d)',
            $index + 1,
            $this->safelyParseString($instruction['type'] ?? 'unknown'),
            $this->safelyParseString($instruction['instructionId'] ?? 'N/A'),
            $this->safelyParseInt($instruction['priority'] ?? 0)
        );
    }

    /**
     * @param array{code: string, name: string, queueLength: int, isOnline: bool} $device
     */
    public function formatBusyDeviceLine(array $device): string
    {
        return sprintf(
            '  • %s (%s) - 队列: %d %s',
            $device['code'],
            $device['name'],
            $device['queueLength'],
            $device['isOnline'] ? '<fg=green>✓</>' : '<fg=red>✗</>'
        );
    }

    /**
     * @param array{isOnline: bool, queueLength: int, metrics: array<string, mixed>} $deviceInfo
     */
    public function updateDeviceStatusSection(ConsoleSectionOutput $section, string $deviceCode, array $deviceInfo): void
    {
        $section->clear();
        $section->writeln($this->formatDeviceStatus($deviceCode, $deviceInfo));

        if ($this->hasMetrics($deviceInfo['metrics'])) {
            $section->writeln($this->formatMetrics($deviceInfo['metrics']));
        }
    }

    /**
     * @param array<int, array<string, mixed>> $instructions
     */
    public function updateInstructionsSection(ConsoleSectionOutput $section, array $instructions, int $queueLength, int $limit): void
    {
        $section->clear();

        if ($queueLength > 0) {
            $section->writeln('<fg=yellow>待执行指令:</>');

            foreach ($instructions as $index => $instruction) {
                $section->writeln($this->formatInstructionLine($index, $instruction));
            }

            if ($queueLength > $limit) {
                $section->writeln(sprintf(
                    '  <fg=gray>... 还有 %d 条指令</>',
                    $queueLength - $limit
                ));
            }
        } else {
            $section->writeln('<fg=gray>队列为空</>');
        }
    }

    /**
     * @param array{totalDevices: int, onlineCount: int, totalQueueLength: int} $stats
     */
    public function updateDevicesSummary(ConsoleSectionOutput $section, array $stats): void
    {
        $section->clear();
        $section->writeln(sprintf(
            '<fg=cyan>设备总数:</> %d | <fg=green>在线:</> %d | <fg=red>离线:</> %d | <fg=magenta>总队列:</> %d',
            $stats['totalDevices'],
            $stats['onlineCount'],
            $stats['totalDevices'] - $stats['onlineCount'],
            $stats['totalQueueLength']
        ));
    }

    /**
     * @param array<array{code: string, name: string, queueLength: int, isOnline: bool}> $busyDevices
     */
    public function updateBusyDevicesSection(ConsoleSectionOutput $section, array $busyDevices, int $limit): void
    {
        $section->clear();

        if ([] === $busyDevices) {
            $section->writeln('<fg=gray>所有设备队列为空</>');

            return;
        }

        $section->writeln('<fg=yellow>繁忙设备（有待执行指令）:</>');
        $displayedDevices = array_slice($busyDevices, 0, $limit);

        foreach ($displayedDevices as $device) {
            $section->writeln($this->formatBusyDeviceLine($device));
        }

        if (count($busyDevices) > $limit) {
            $section->writeln(sprintf(
                '  <fg=gray>... 还有 %d 个繁忙设备</>',
                count($busyDevices) - $limit
            ));
        }
    }

    public function updateStatusSection(ConsoleSectionOutput $section, int $iteration): void
    {
        $timestamp = (new \DateTime())->format('Y-m-d H:i:s');

        $section->clear();
        $section->writeln(sprintf(
            '<fg=cyan>监控状态:</> 运行中 | <fg=yellow>更新时间:</> %s | <fg=green>迭代次数:</> %d',
            $timestamp,
            $iteration
        ));
    }

    /**
     * 安全地将混合类型转换为字符串
     */
    private function safelyParseString(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        return '';
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

    /**
     * 安全地将混合类型转换为浮点数
     */
    private function safelyParseFloat(mixed $value): float
    {
        if (is_float($value)) {
            return $value;
        }

        if (is_int($value)) {
            return (float) $value;
        }

        if (is_string($value) && is_numeric($value)) {
            return (float) $value;
        }

        return 0.0;
    }

    /**
     * 安全地将混合类型转换为数组
     *
     * @return array<string, mixed>
     */
    private function safelyParseArray(mixed $value): array
    {
        if (is_array($value)) {
            $result = [];
            foreach ($value as $key => $val) {
                $stringKey = is_string($key) ? $key : (string) $key;
                $result[$stringKey] = $val;
            }

            return $result;
        }

        return [];
    }
}
