<?php

declare(strict_types=1);

namespace Tourze\AutoJsControlBundle\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Tourze\AutoJsControlBundle\Service\MonitoringCoordinator;
use Tourze\AutoJsControlBundle\Service\QueueMonitorService;

#[AsCommand(name: self::NAME, description: '监控设备指令队列状态', aliases: ['autojs:queue:monitor', 'autojs:queue:status'], help: <<<'TXT'
    <info>%command.name%</info> 命令用于监控设备指令队列的实时状态。

    示例：
      <comment>%command.full_name%</comment>                          # 监控所有设备队列
      <comment>%command.full_name% --device=DEV001</comment>          # 监控特定设备
      <comment>%command.full_name% --refresh=5</comment>              # 每5秒刷新一次
      <comment>%command.full_name% --once</comment>                   # 只显示一次状态
      <comment>%command.full_name% --clear-queue=DEV001</comment>     # 清空指定设备队列

    显示内容：
      - 队列长度和状态
      - 待执行的指令列表
      - 指令执行状态
      - 设备在线状态
      - 队列处理速率

    注意：
      - 使用 Ctrl+C 退出监控模式
      - 清空队列操作需要确认
    TXT)]
final class QueueMonitorCommand extends Command
{
    public const NAME = 'auto-js:queue:monitor';

    public function __construct(
        private readonly QueueMonitorService $queueService,
        private readonly MonitoringCoordinator $monitoringCoordinator,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('device', 'd', InputOption::VALUE_REQUIRED, '指定设备代码')
            ->addOption('refresh', 'r', InputOption::VALUE_REQUIRED, '刷新间隔（秒）', '2')
            ->addOption('once', 'o', InputOption::VALUE_NONE, '只显示一次，不持续监控')
            ->addOption('limit', 'l', InputOption::VALUE_REQUIRED, '显示的队列项目数量限制', '10')
            ->addOption('show-completed', null, InputOption::VALUE_NONE, '显示已完成的指令')
            ->addOption('clear-queue', null, InputOption::VALUE_REQUIRED, '清空指定设备的队列')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $clearDevice = $input->getOption('clear-queue');
        if (null !== $clearDevice) {
            return $this->handleClearQueue($this->safelyParseString($clearDevice), $io);
        }

        return $this->handleMonitoring($input, $output, $io);
    }

    private function handleClearQueue(string $deviceCode, SymfonyStyle $io): int
    {
        $io->warning(sprintf('即将清空设备 %s 的指令队列', $deviceCode));

        if (!$io->confirm('确定要清空该设备的所有待执行指令吗？', false)) {
            $io->comment('操作已取消');

            return Command::SUCCESS;
        }

        try {
            $clearedCount = $this->queueService->clearDeviceQueue($deviceCode);
            $io->success(sprintf('已清空设备 %s 的队列，共清除 %d 条指令', $deviceCode, $clearedCount));

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('清空队列失败: ' . $e->getMessage());

            return Command::FAILURE;
        }
    }

    private function handleMonitoring(InputInterface $input, OutputInterface $output, SymfonyStyle $io): int
    {
        $io->title('Auto.js 指令队列监控');

        $config = $this->extractMonitoringConfig($input);

        try {
            if (true === $config['once']) {
                $this->monitoringCoordinator->displayQueueStatus($config['device'], $config['limit'], $io);
            } else {
                $this->monitoringCoordinator->startMonitoring($config['device'], $config['refreshInterval'], $config['limit'], $io, $output);
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('监控队列失败: ' . $e->getMessage());

            return Command::FAILURE;
        }
    }

    /**
     * @return array{device: ?string, refreshInterval: int, once: bool, limit: int}
     */
    private function extractMonitoringConfig(InputInterface $input): array
    {
        return [
            'device' => $this->safelyParseStringOption($input->getOption('device')),
            'refreshInterval' => $this->safelyParseInt($input->getOption('refresh')),
            'once' => (bool) $input->getOption('once'),
            'limit' => $this->safelyParseInt($input->getOption('limit')),
        ];
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
     * 安全地将混合类型转换为字符串选项（可为null）
     */
    private function safelyParseStringOption(mixed $value): ?string
    {
        if (null === $value) {
            return null;
        }

        return $this->safelyParseString($value);
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
