<?php

declare(strict_types=1);

namespace Tourze\AutoJsControlBundle\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Tourze\AutoJsControlBundle\Entity\AutoJsDevice;
use Tourze\AutoJsControlBundle\Exception\BusinessLogicException;
use Tourze\AutoJsControlBundle\Service\DeviceHeartbeatService;
use Tourze\AutoJsControlBundle\Service\DeviceManager;

#[AsCommand(name: self::NAME, description: '列出所有Auto.js设备及其状态信息', aliases: ['autojs:device:list'], help: <<<'TXT'
    <info>%command.name%</info> 命令用于列出所有Auto.js设备及其状态信息。

    示例：
      <comment>%command.full_name%</comment>                              # 列出所有设备
      <comment>%command.full_name% --online-only</comment>               # 仅显示在线设备
      <comment>%command.full_name% --format=json</comment>               # 以JSON格式输出
      <comment>%command.full_name% --sort-by=last_online --order=desc</comment>  # 按最后在线时间降序排列
    TXT)]
final class DeviceListCommand extends Command
{
    use InputSanitizerTrait;

    public const NAME = 'auto-js:device:list';

    public function __construct(
        private readonly DeviceManager $deviceManager,
        private readonly DeviceHeartbeatService $heartbeatService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('online-only', null, InputOption::VALUE_NONE, '仅显示在线设备')
            ->addOption('offline-only', null, InputOption::VALUE_NONE, '仅显示离线设备')
            ->addOption('format', 'f', InputOption::VALUE_REQUIRED, '输出格式: table, json, csv', 'table')
            ->addOption('limit', 'l', InputOption::VALUE_REQUIRED, '限制显示数量', '50')
            ->addOption('sort-by', 's', InputOption::VALUE_REQUIRED, '排序字段: id, name, status, last_online', 'id')
            ->addOption('order', 'o', InputOption::VALUE_REQUIRED, '排序方向: asc, desc', 'desc')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $options = $this->parseInputOptions($input);
            if (null !== $options['error']) {
                $errorMessage = is_string($options['error']) ? $options['error'] : 'Unknown error';
                $io->error($errorMessage);

                return Command::FAILURE;
            }

            return $this->processDeviceList($options, $output, $io);
        } catch (\Exception $e) {
            $io->error('获取设备列表失败: ' . $e->getMessage());

            return Command::FAILURE;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function parseInputOptions(InputInterface $input): array
    {
        $onlineOnly = (bool) $input->getOption('online-only');
        $offlineOnly = (bool) $input->getOption('offline-only');

        if ($onlineOnly && $offlineOnly) {
            return ['error' => '不能同时使用 --online-only 和 --offline-only 选项'];
        }

        return [
            'error' => null,
            'onlineOnly' => $onlineOnly,
            'offlineOnly' => $offlineOnly,
            'format' => $this->sanitizeStringOption($input->getOption('format'), 'table'),
            'limit' => $this->sanitizeIntOption($input->getOption('limit'), 0),
            'sortBy' => $this->sanitizeStringOption($input->getOption('sort-by'), 'id'),
            'order' => strtoupper($this->sanitizeStringOption($input->getOption('order'), 'ASC')),
        ];
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return array<int, array{device: AutoJsDevice, isOnline: bool}>
     */
    private function getFilteredDevices(array $options): array
    {
        $devices = $this->fetchDevicesFromRepository($options);

        return $this->filterDevicesByOnlineStatus($devices, $options);
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return array<int, AutoJsDevice>
     */
    private function fetchDevicesFromRepository(array $options): array
    {
        $criteria = [];
        $sortBy = $options['sortBy'];
        $order = in_array($options['order'], ['ASC', 'DESC'], true) ? $options['order'] : 'ASC';
        $limit = $options['limit'];

        $orderBy = [$this->mapSortField($this->safelyParseString($sortBy)) => $order];
        $safeLimitValue = $this->safelyParseInt($limit);

        return $this->deviceManager->searchDevices($criteria, $orderBy, $safeLimitValue);
    }

    /**
     * @param array<int, AutoJsDevice> $devices
     * @param array<string, mixed> $options
     *
     * @return array<int, array{device: AutoJsDevice, isOnline: bool}>
     */
    private function filterDevicesByOnlineStatus(array $devices, array $options): array
    {
        $filteredDevices = [];
        foreach ($devices as $device) {
            $deviceData = $this->createDeviceData($device, $options);
            if (null !== $deviceData) {
                $filteredDevices[] = $deviceData;
            }
        }

        return $filteredDevices;
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return array{device: AutoJsDevice, isOnline: bool}|null
     */
    private function createDeviceData(AutoJsDevice $device, array $options): ?array
    {
        $baseDevice = $device->getBaseDevice();
        if (null === $baseDevice) {
            return null;
        }

        $isOnline = $this->heartbeatService->isDeviceOnline($baseDevice->getCode());

        $onlineOnly = $this->safelyParseBool($options['onlineOnly']);
        $offlineOnly = $this->safelyParseBool($options['offlineOnly']);

        if (!$this->shouldIncludeDevice($isOnline, $onlineOnly, $offlineOnly)) {
            return null;
        }

        return [
            'device' => $device,
            'isOnline' => $isOnline,
        ];
    }

    private function shouldIncludeDevice(bool $isOnline, bool $onlineOnly, bool $offlineOnly): bool
    {
        if ($onlineOnly && !$isOnline) {
            return false;
        }
        if ($offlineOnly && $isOnline) {
            return false;
        }

        return true;
    }

    /**
     * @param array<int, array{device: AutoJsDevice, isOnline: bool}> $devices
     */
    private function outputDevices(array $devices, string $format, OutputInterface $output, SymfonyStyle $io): void
    {
        match ($format) {
            'json' => $this->outputJson($devices, $output),
            'csv' => $this->outputCsv($devices, $output),
            default => $this->outputTable($devices, $io),
        };
    }

    /**
     * @param array<int, array{device: AutoJsDevice, isOnline: bool}> $devices
     */
    private function outputTable(array $devices, SymfonyStyle $io): void
    {
        $table = new Table($io);
        $this->setTableHeaders($table);

        foreach ($devices as $data) {
            $row = $this->buildTableRow($data);
            if (null !== $row) {
                $table->addRow($row);
            }
        }

        $table->render();
    }

    private function setTableHeaders(Table $table): void
    {
        $table->setHeaders([
            'ID',
            '设备代码',
            '设备名称',
            '状态',
            '型号',
            '品牌',
            'Auto.js版本',
            '最后在线时间',
            'IP地址',
            'CPU使用率',
            '内存使用率',
        ]);
    }

    /**
     * @param array{device: AutoJsDevice, isOnline: bool} $data
     *
     * @return array<int, string>|null
     */
    private function buildTableRow(array $data): ?array
    {
        $device = $data['device'];
        $baseDevice = $device->getBaseDevice();
        if (null === $baseDevice) {
            return null;
        }

        $isOnline = $data['isOnline'];
        $metrics = $this->heartbeatService->getDeviceMetrics($baseDevice->getCode());

        return [
            (string) $device->getId(),
            $baseDevice->getCode(),
            $baseDevice->getName() ?? 'N/A',
            $isOnline ? '<fg=green>在线</>' : '<fg=red>离线</>',
            $baseDevice->getModel() ?? 'N/A',
            $baseDevice->getBrand() ?? 'N/A',
            $device->getAutoJsVersion() ?? 'N/A',
            $baseDevice->getLastOnlineTime()?->format('Y-m-d H:i:s') ?? 'N/A',
            $baseDevice->getLastIp() ?? 'N/A',
            $this->formatMetricValue($metrics, 'cpuUsage'),
            $this->formatMetricValue($metrics, 'memoryUsage'),
        ];
    }

    /**
     * @param array<string, mixed> $metrics
     */
    private function formatMetricValue(array $metrics, string $key): string
    {
        if (!isset($metrics[$key]) || !is_numeric($metrics[$key])) {
            return 'N/A';
        }

        return sprintf('%.1f%%', (float) $metrics[$key]);
    }

    /**
     * @param array<int, array{device: AutoJsDevice, isOnline: bool}> $devices
     */
    private function outputJson(array $devices, OutputInterface $output): void
    {
        $data = [];
        foreach ($devices as $item) {
            $device = $item['device'];
            $baseDevice = $device->getBaseDevice();
            if (null === $baseDevice) {
                continue;
            }
            $metrics = $this->heartbeatService->getDeviceMetrics($baseDevice->getCode());

            $data[] = [
                'id' => $device->getId(),
                'code' => $baseDevice->getCode(),
                'name' => $baseDevice->getName(),
                'status' => $item['isOnline'] ? 'online' : 'offline',
                'model' => $baseDevice->getModel(),
                'brand' => $baseDevice->getBrand(),
                'autoJsVersion' => $device->getAutoJsVersion(),
                'lastOnlineTime' => $baseDevice->getLastOnlineTime()?->format(\DateTimeInterface::RFC3339),
                'lastIp' => $baseDevice->getLastIp(),
                'metrics' => $metrics,
            ];
        }

        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        if (false === $json) {
            throw BusinessLogicException::dataProcessingError('JSON编码失败');
        }
        $output->writeln($json);
    }

    /**
     * @param array<int, array{device: AutoJsDevice, isOnline: bool}> $devices
     */
    private function outputCsv(array $devices, OutputInterface $output): void
    {
        $output->writeln('ID,设备代码,设备名称,状态,型号,品牌,Auto.js版本,最后在线时间,IP地址,CPU使用率,内存使用率');

        foreach ($devices as $item) {
            $device = $item['device'];
            $baseDevice = $device->getBaseDevice();
            if (null === $baseDevice) {
                continue;
            }
            $metrics = $this->heartbeatService->getDeviceMetrics($baseDevice->getCode());

            $cpuUsage = isset($metrics['cpuUsage']) && is_numeric($metrics['cpuUsage'])
                ? sprintf('%.1f', (float) $metrics['cpuUsage'])
                : '';
            $memoryUsage = isset($metrics['memoryUsage']) && is_numeric($metrics['memoryUsage'])
                ? sprintf('%.1f', (float) $metrics['memoryUsage'])
                : '';

            $row = [
                $device->getId(),
                $baseDevice->getCode(),
                $baseDevice->getName(),
                $item['isOnline'] ? '在线' : '离线',
                $baseDevice->getModel() ?? '',
                $baseDevice->getBrand() ?? '',
                $device->getAutoJsVersion() ?? '',
                $baseDevice->getLastOnlineTime()?->format('Y-m-d H:i:s') ?? '',
                $baseDevice->getLastIp() ?? '',
                $cpuUsage,
                $memoryUsage,
            ];

            $output->writeln(implode(',', array_map(fn ($v) => '"' . str_replace('"', '""', (string) $v) . '"', $row)));
        }
    }

    private function showStatistics(SymfonyStyle $io): void
    {
        $stats = $this->deviceManager->getDeviceStatistics();

        $this->displayGeneralStatistics($stats, $io);
        $this->displayBrandStatistics($stats, $io);
    }

    /**
     * @param array<string, mixed> $stats
     */
    private function displayGeneralStatistics(array $stats, SymfonyStyle $io): void
    {
        $io->section('设备统计');

        $io->table(
            ['指标', '数值'],
            [
                ['设备总数', $this->safelyParseInt($stats['total'] ?? 0)],
                ['在线设备', sprintf('<fg=green>%d</>', $this->safelyParseInt($stats['online'] ?? 0))],
                ['离线设备', sprintf('<fg=red>%d</>', $this->safelyParseInt($stats['offline'] ?? 0))],
                ['在线率', $this->calculateOnlineRate($stats)],
            ]
        );
    }

    /**
     * @param array<string, mixed> $stats
     */
    private function displayBrandStatistics(array $stats, SymfonyStyle $io): void
    {
        $byBrandData = $stats['byBrand'] ?? [];
        if (!is_array($byBrandData) || [] === $byBrandData) {
            return;
        }

        /** @var array<string, mixed> $byBrandData */
        $io->section('按品牌统计');
        $brandData = $this->prepareBrandData($byBrandData);
        $io->table(['品牌', '数量'], $brandData);
    }

    /**
     * @param array<string, mixed> $byBrandData
     * @return array<int, array<int, string|int>>
     */
    private function prepareBrandData(array $byBrandData): array
    {
        $brandData = [];
        foreach ($byBrandData as $brand => $count) {
            $brandData[] = [$brand, $this->safelyParseInt($count)];
        }

        return $brandData;
    }

    /**
     * @param array<string, mixed> $options
     */
    private function processDeviceList(array $options, OutputInterface $output, SymfonyStyle $io): int
    {
        $format = $this->safelyParseString($options['format']);
        $this->displayTitle($format, $io);

        $devices = $this->getFilteredDevices($options);
        if ([] === $devices) {
            return $this->handleEmptyDeviceList($format, $output, $io);
        }

        $this->outputDevices($devices, $format, $output, $io);
        $this->displayStatisticsIfNeeded($format, $io);

        return Command::SUCCESS;
    }

    private function displayTitle(string $format, SymfonyStyle $io): void
    {
        if ('table' === $format) {
            $io->title('Auto.js 设备列表');
        }
    }

    private function handleEmptyDeviceList(string $format, OutputInterface $output, SymfonyStyle $io): int
    {
        match ($format) {
            'table' => $io->warning('没有找到符合条件的设备'),
            'json' => $output->writeln('[]'),
            default => null,
        };

        return Command::SUCCESS;
    }

    private function displayStatisticsIfNeeded(string $format, SymfonyStyle $io): void
    {
        if ('table' === $format) {
            $this->showStatistics($io);
        }
    }

    private function mapSortField(string $field): string
    {
        return match ($field) {
            'name' => 'baseDevice.name',
            'status' => 'baseDevice.status',
            'last_online' => 'baseDevice.lastOnlineTime',
            default => 'id',
        };
    }

    /**
     * 计算安全的在线率
     *
     * @param array<string, mixed> $stats
     */
    private function calculateOnlineRate(array $stats): string
    {
        $total = $this->safelyParseInt($stats['total'] ?? 0);
        $online = $this->safelyParseInt($stats['online'] ?? 0);

        if (0 === $total) {
            return '0.0%';
        }

        $rate = ($online / $total) * 100;

        return sprintf('%.1f%%', $rate);
    }
}
