<?php

declare(strict_types=1);

namespace Tourze\AutoJsControlBundle\Command;

use DeviceBundle\Enum\DeviceStatus;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Tourze\AutoJsControlBundle\Dto\Request\DeviceHeartbeatRequest;
use Tourze\AutoJsControlBundle\Dto\Request\DeviceRegisterRequest;
use Tourze\AutoJsControlBundle\Dto\Request\ReportExecutionResultRequest;
use Tourze\AutoJsControlBundle\Enum\ExecutionStatus;
use Tourze\AutoJsControlBundle\Service\CacheStorageService;
use Tourze\AutoJsControlBundle\Service\DeviceHeartbeatService;
use Tourze\AutoJsControlBundle\Service\DeviceManager;
use Tourze\AutoJsControlBundle\Service\InstructionQueueService;
use Tourze\AutoJsControlBundle\ValueObject\DeviceInstruction;

#[AsCommand(name: self::NAME, description: '模拟Auto.js设备进行轮询和指令执行（用于测试）', aliases: ['autojs:device:simulate', 'autojs:test:device'], help: <<<'TXT'
    <info>%command.name%</info> 命令用于模拟Auto.js设备，用于测试系统功能。

    示例：
      <comment>%command.full_name%</comment>                           # 模拟1个设备
      <comment>%command.full_name% --device-count=5</comment>           # 模拟5个设备
      <comment>%command.full_name% -c 10 -p TEST -i 2</comment>        # 模拟10个设备，前缀TEST，2秒轮询
      <comment>%command.full_name% --failure-rate=20</comment>          # 20%的指令执行失败
      <comment>%command.full_name% --metrics --verbose-execution</comment> # 启用指标和详细日志

    功能说明：
      1. 自动注册模拟设备
      2. 定期发送心跳
      3. 轮询并执行指令
      4. 模拟执行结果（成功/失败）
      5. 可选的性能指标上报

    注意：
      - 使用 Ctrl+C 停止模拟
      - 模拟设备会自动清理
      - 适合用于压力测试和功能测试
    TXT)]
final class DeviceSimulatorCommand extends Command
{
    public const NAME = 'auto-js:device:simulator';

    /**
     * @var array<string, array{device: object, lastHeartbeat: int, lastPoll: int, executedInstructions: int, failedInstructions: int}>
     */
    private array $simulatedDevices = [];

    private bool $running = true;

    public function __construct(
        private readonly DeviceManager $deviceManager,
        private readonly DeviceHeartbeatService $heartbeatService,
        private readonly InstructionQueueService $queueService,
        private readonly CacheStorageService $cacheStorage,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('device-count', 'c', InputOption::VALUE_REQUIRED, '要模拟的设备数量', '1')
            ->addOption('device-prefix', 'p', InputOption::VALUE_REQUIRED, '设备代码前缀', 'SIM')
            ->addOption('poll-interval', 'i', InputOption::VALUE_REQUIRED, '轮询间隔（秒）', '5')
            ->addOption('heartbeat-interval', 'h', InputOption::VALUE_REQUIRED, '心跳间隔（秒）', '30')
            ->addOption('execution-delay', 'd', InputOption::VALUE_REQUIRED, '执行延迟范围（毫秒）', '100-1000')
            ->addOption('failure-rate', 'f', InputOption::VALUE_REQUIRED, '指令执行失败率（0-100）', '5')
            ->addOption('metrics', 'm', InputOption::VALUE_NONE, '启用性能指标模拟')
            ->addOption('verbose-execution', null, InputOption::VALUE_NONE, '显示详细的执行日志')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $deviceCount = $this->safelyParseInt($input->getOption('device-count'));
        $devicePrefix = $this->safelyParseString($input->getOption('device-prefix'));
        $pollInterval = $this->safelyParseInt($input->getOption('poll-interval'));
        $heartbeatInterval = $this->safelyParseInt($input->getOption('heartbeat-interval'));
        $failureRate = $this->safelyParseInt($input->getOption('failure-rate'));
        $enableMetrics = $input->getOption('metrics');
        $verboseExecution = $input->getOption('verbose-execution');

        $io->title('Auto.js 设备模拟器');

        // 验证参数
        if ($deviceCount < 1 || $deviceCount > 100) {
            $io->error('设备数量必须在1-100之间');

            return Command::FAILURE;
        }

        if ($failureRate < 0 || $failureRate > 100) {
            $io->error('失败率必须在0-100之间');

            return Command::FAILURE;
        }

        // 注册信号处理
        $this->registerSignalHandlers($io);

        try {
            // 初始化模拟设备
            $this->initializeDevices($deviceCount, $devicePrefix, $io);

            // 显示配置信息
            $this->displayConfiguration($input, $io);

            // 开始模拟
            $this->startSimulation($input, $io);
        } catch (\Exception $e) {
            $io->error('模拟过程中发生错误: ' . $e->getMessage());

            return Command::FAILURE;
        } finally {
            // 清理模拟设备
            $this->cleanup($io);
        }

        return Command::SUCCESS;
    }

    private function registerSignalHandlers(SymfonyStyle $io): void
    {
        if (function_exists('pcntl_signal')) {
            pcntl_signal(SIGINT, function () use ($io): void {
                $this->running = false;
                $io->newLine();
                $io->comment('收到中断信号，正在停止模拟...');
            });

            pcntl_signal(SIGTERM, function () use ($io): void {
                $this->running = false;
                $io->newLine();
                $io->comment('收到终止信号，正在停止模拟...');
            });
        }
    }

    private function initializeDevices(int $count, string $prefix, SymfonyStyle $io): void
    {
        $io->section('初始化模拟设备');
        $io->progressStart($count);

        for ($i = 1; $i <= $count; ++$i) {
            $deviceCode = sprintf('%s-%03d', $prefix, $i);
            $deviceName = sprintf('模拟设备 %d', $i);

            try {
                // 创建设备注册请求
                $deviceInfo = $this->generateDeviceInfo($i);
                $registerRequest = new DeviceRegisterRequest(
                    deviceCode: $deviceCode,
                    deviceName: $deviceName,
                    certificateRequest: $this->generateCertificateRequest(),
                    model: $deviceInfo['model'] ?? null,
                    brand: $deviceInfo['brand'] ?? null,
                    osVersion: $deviceInfo['osVersion'] ?? null,
                    autoJsVersion: $deviceInfo['autoJsVersion'] ?? null,
                    fingerprint: $deviceInfo['fingerprint'] ?? null,
                    hardwareInfo: $deviceInfo['hardware'] ?? []
                );

                // 注册设备
                $device = $this->deviceManager->registerOrUpdateDevice(
                    $registerRequest->getDeviceCode(),
                    $registerRequest->getDeviceName(),
                    $registerRequest->getCertificateRequest(),
                    $deviceInfo,
                    '127.0.0.1'
                );

                $this->simulatedDevices[$deviceCode] = [
                    'device' => $device,
                    'lastHeartbeat' => time(),
                    'lastPoll' => time(),
                    'executedInstructions' => 0,
                    'failedInstructions' => 0,
                ];

                $io->progressAdvance();
            } catch (\Exception $e) {
                $io->writeln(sprintf("\n<error>初始化设备 %s 失败: %s</error>", $deviceCode, $e->getMessage()));
            }
        }

        $io->progressFinish();
        $io->success(sprintf('成功初始化 %d 个模拟设备', count($this->simulatedDevices)));
    }

    private function displayConfiguration(InputInterface $input, SymfonyStyle $io): void
    {
        $io->section('模拟配置');

        $config = [
            ['设备数量', count($this->simulatedDevices)],
            ['设备前缀', $this->safelyParseString($input->getOption('device-prefix'))],
            ['轮询间隔', $this->safelyParseString($input->getOption('poll-interval')) . ' 秒'],
            ['心跳间隔', $this->safelyParseString($input->getOption('heartbeat-interval')) . ' 秒'],
            ['执行延迟', $this->safelyParseString($input->getOption('execution-delay')) . ' 毫秒'],
            ['失败率', $this->safelyParseString($input->getOption('failure-rate')) . '%'],
            ['性能指标', (bool) $input->getOption('metrics') ? '启用' : '禁用'],
            ['详细日志', (bool) $input->getOption('verbose-execution') ? '启用' : '禁用'],
        ];

        $io->table(['参数', '值'], $config);
    }

    private function startSimulation(InputInterface $input, SymfonyStyle $io): void
    {
        $config = $this->extractSimulationConfig($input);
        $io->section('开始设备模拟');
        $io->comment('按 Ctrl+C 停止模拟');
        $io->newLine();

        $state = $this->initializeSimulationState();

        while ($this->running) {
            $state = $this->runSimulationIteration($config, $state, $input, $io);
            $this->handleSignals();
            usleep(100000); // 100ms
        }
    }

    /**
     * @return array{pollInterval: int, heartbeatInterval: int, enableMetrics: bool, verboseExecution: bool}
     */
    private function extractSimulationConfig(InputInterface $input): array
    {
        return [
            'pollInterval' => $this->safelyParseInt($input->getOption('poll-interval')),
            'heartbeatInterval' => $this->safelyParseInt($input->getOption('heartbeat-interval')),
            'enableMetrics' => (bool) $input->getOption('metrics'),
            'verboseExecution' => (bool) $input->getOption('verbose-execution'),
        ];
    }

    /**
     * @return array{iteration: int, lastStatusUpdate: int}
     */
    private function initializeSimulationState(): array
    {
        return [
            'iteration' => 0,
            'lastStatusUpdate' => time(),
        ];
    }

    /**
     * @param array{pollInterval: int, heartbeatInterval: int, enableMetrics: bool, verboseExecution: bool} $config
     * @param array{iteration: int, lastStatusUpdate: int}                                                  $state
     * @return array{iteration: int, lastStatusUpdate: int}
     */
    private function runSimulationIteration(array $config, array $state, InputInterface $input, SymfonyStyle $io): array
    {
        ++$state['iteration'];
        $currentTime = time();

        $state = $this->updateStatusIfNeeded($currentTime, $state, $io);
        $this->processAllDevices($currentTime, $config, $input, $io);

        return $state;
    }

    /**
     * @param array{iteration: int, lastStatusUpdate: int} $state
     * @return array{iteration: int, lastStatusUpdate: int}
     */
    private function updateStatusIfNeeded(int $currentTime, array $state, SymfonyStyle $io): array
    {
        if ($currentTime - $state['lastStatusUpdate'] >= 10) {
            $this->displayStatus($io);
            $state['lastStatusUpdate'] = $currentTime;
        }

        return $state;
    }

    /**
     * @param array{pollInterval: int, heartbeatInterval: int, enableMetrics: bool, verboseExecution: bool} $config
     */
    private function processAllDevices(int $currentTime, array $config, InputInterface $input, SymfonyStyle $io): void
    {
        foreach ($this->simulatedDevices as $deviceCode => $deviceData) {
            $this->simulatedDevices[$deviceCode] = $this->processDevice($deviceCode, $deviceData, $currentTime, $config, $input, $io);
        }
    }

    /**
     * @param array{device: object, lastHeartbeat: int, lastPoll: int, executedInstructions: int, failedInstructions: int} $deviceData
     * @param array{pollInterval: int, heartbeatInterval: int, enableMetrics: bool, verboseExecution: bool}                $config
     * @return array{device: object, lastHeartbeat: int, lastPoll: int, executedInstructions: int, failedInstructions: int}
     */
    private function processDevice(string $deviceCode, array $deviceData, int $currentTime, array $config, InputInterface $input, SymfonyStyle $io): array
    {
        if ($currentTime - $deviceData['lastHeartbeat'] >= $config['heartbeatInterval']) {
            $this->sendHeartbeat($deviceCode, $config['enableMetrics'], $config['verboseExecution'], $io);
            $deviceData['lastHeartbeat'] = $currentTime;
        }

        if ($currentTime - $deviceData['lastPoll'] >= $config['pollInterval']) {
            $this->pollAndExecuteInstructions($deviceCode, $input, $config['verboseExecution'], $io);
            $deviceData['lastPoll'] = $currentTime;
        }

        return $deviceData;
    }

    private function handleSignals(): void
    {
        if (function_exists('pcntl_signal_dispatch')) {
            pcntl_signal_dispatch();
        }
    }

    private function sendHeartbeat(string $deviceCode, bool $enableMetrics, bool $verbose, SymfonyStyle $io): void
    {
        try {
            $timestamp = time();
            $signature = $this->generateSignature($deviceCode, $timestamp);

            $monitorData = $enableMetrics ? $this->generateMetrics() : [];

            $heartbeatRequest = new DeviceHeartbeatRequest(
                deviceCode: $deviceCode,
                signature: $signature,
                timestamp: $timestamp,
                autoJsVersion: '9.0.0',
                deviceInfo: [],
                monitorData: $monitorData,
                pollTimeout: 30
            );

            // 更新设备在线状态
            try {
                $device = $this->deviceManager->getDevice($deviceCode);
                $this->deviceManager->updateDeviceStatus($deviceCode, DeviceStatus::ONLINE);
            } catch (\Exception $e) {
                // 设备可能不存在，忽略错误
            }

            if ($verbose) {
                $io->writeln(sprintf(
                    '[%s] <fg=green>♥</> 设备 %s 发送心跳',
                    date('H:i:s'),
                    $deviceCode
                ));
            }
        } catch (\Exception $e) {
            $io->writeln(sprintf(
                '[%s] <fg=red>✗</> 设备 %s 心跳失败: %s',
                date('H:i:s'),
                $deviceCode,
                $e->getMessage()
            ));
        }
    }

    private function pollAndExecuteInstructions(string $deviceCode, InputInterface $input, bool $verbose, SymfonyStyle $io): void
    {
        try {
            // 轮询指令（短超时以避免阻塞）
            $instructions = $this->queueService->longPollInstructions($deviceCode, 1);

            if ([] === $instructions) {
                return;
            }

            foreach ($instructions as $instruction) {
                $this->executeInstruction($deviceCode, $instruction, $input, $verbose, $io);
            }
        } catch (\Exception $e) {
            $io->writeln(sprintf(
                '[%s] <fg=red>✗</> 设备 %s 轮询失败: %s',
                date('H:i:s'),
                $deviceCode,
                $e->getMessage()
            ));
        }
    }

    private function executeInstruction(string $deviceCode, DeviceInstruction $instruction, InputInterface $input, bool $verbose, SymfonyStyle $io): void
    {
        $failureRate = $this->safelyParseInt($input->getOption('failure-rate'));
        $executionDelay = $this->safelyParseString($input->getOption('execution-delay'));

        // 解析执行延迟范围
        [$minDelay, $maxDelay] = array_map('intval', explode('-', $executionDelay));
        $delay = rand($minDelay, $maxDelay);

        if ($verbose) {
            $io->writeln(sprintf(
                '[%s] <fg=blue>⚡</> 设备 %s 开始执行指令 %s',
                date('H:i:s'),
                $deviceCode,
                $instruction->getInstructionId()
            ));
        }

        // 模拟执行延迟
        usleep($delay * 1000);

        // 决定执行结果
        $success = rand(1, 100) > $failureRate;

        // 构建执行结果
        $startTime = new \DateTimeImmutable();
        $endTime = new \DateTimeImmutable();
        $timestamp = time();
        $signature = $this->generateSignature($deviceCode, $timestamp);

        $result = new ReportExecutionResultRequest(
            deviceCode: $deviceCode,
            signature: $signature,
            timestamp: $timestamp,
            instructionId: $instruction->getInstructionId(),
            status: $success ? ExecutionStatus::SUCCESS : ExecutionStatus::FAILED,
            startTime: $startTime,
            endTime: $endTime,
            output: $this->generateExecutionOutput($instruction, $success),
            errorMessage: !$success ? $this->generateErrorMessage() : null,
            executionMetrics: [],
            screenshots: null
        );

        // 更新统计
        if (isset($this->simulatedDevices[$deviceCode])) {
            ++$this->simulatedDevices[$deviceCode]['executedInstructions'];
            if (!$success) {
                ++$this->simulatedDevices[$deviceCode]['failedInstructions'];
            }
        }

        // 报告执行结果
        try {
            $this->cacheStorage->updateInstructionStatus(
                $instruction->getInstructionId(),
                [
                    'status' => $result->getStatus()->value,
                    'updateTime' => new \DateTimeImmutable()->format(\DateTimeInterface::RFC3339),
                    'output' => $result->getOutput(),
                    'errorMessage' => $result->getErrorMessage(),
                ]
            );

            if ($verbose) {
                $statusIcon = $success ? '<fg=green>✓</>' : '<fg=red>✗</>';
                $io->writeln(sprintf(
                    '[%s] %s 设备 %s 完成指令 %s (耗时: %dms)',
                    date('H:i:s'),
                    $statusIcon,
                    $deviceCode,
                    $instruction->getInstructionId(),
                    $delay
                ));
            }
        } catch (\Exception $e) {
            $io->writeln(sprintf(
                '[%s] <fg=red>✗</> 设备 %s 报告结果失败: %s',
                date('H:i:s'),
                $deviceCode,
                $e->getMessage()
            ));
        }
    }

    private function displayStatus(SymfonyStyle $io): void
    {
        $totalExecuted = 0;
        $totalFailed = 0;
        $onlineCount = 0;

        foreach ($this->simulatedDevices as $deviceCode => $data) {
            $totalExecuted += $data['executedInstructions'];
            $totalFailed += $data['failedInstructions'];
            if ($this->heartbeatService->isDeviceOnline($deviceCode)) {
                ++$onlineCount;
            }
        }

        $successRate = $totalExecuted > 0 ? (($totalExecuted - $totalFailed) / $totalExecuted * 100) : 100;

        $io->writeln(sprintf(
            '[%s] 状态: 设备 %d/%d 在线 | 执行: %d | 失败: %d | 成功率: %.1f%%',
            date('H:i:s'),
            $onlineCount,
            count($this->simulatedDevices),
            $totalExecuted,
            $totalFailed,
            $successRate
        ));
    }

    private function cleanup(SymfonyStyle $io): void
    {
        $io->section('清理模拟设备');

        foreach ($this->simulatedDevices as $deviceCode => $data) {
            try {
                // 标记设备离线
                $this->heartbeatService->markDeviceOffline($deviceCode);

                // 清空队列
                $this->queueService->clearDeviceQueue($deviceCode);
            } catch (\Exception $e) {
                $io->writeln(sprintf('<error>清理设备 %s 失败: %s</error>', $deviceCode, $e->getMessage()));
            }
        }

        $io->success('模拟设备清理完成');
    }

    private function generateCertificateRequest(): string
    {
        return base64_encode(random_bytes(32));
    }

    /**
     * @return array{model: string, brand: string, osVersion: string, autoJsVersion: string, fingerprint: string, hardwareInfo: array<string, mixed>}
     */
    private function generateDeviceInfo(int $index): array
    {
        $brands = ['Xiaomi', 'Huawei', 'Samsung', 'OPPO', 'Vivo'];
        $models = ['Pro', 'Plus', 'Max', 'Ultra', 'Note'];

        return [
            'model' => sprintf('%s %s %d', $brands[array_rand($brands)], $models[array_rand($models)], rand(10, 13)),
            'brand' => $brands[array_rand($brands)],
            'osVersion' => sprintf('Android %d', rand(10, 13)),
            'autoJsVersion' => '4.1.1',
            'fingerprint' => md5(uniqid((string) $index, true)),
            'hardwareInfo' => [
                'cpuCores' => rand(4, 8),
                'memorySize' => rand(4, 16) . 'GB',
                'storageSize' => rand(64, 256) . 'GB',
            ],
        ];
    }

    /**
     * @return array{cpuUsage: float, memoryUsage: float, batteryLevel: int, temperature: float}
     */
    private function generateMetrics(): array
    {
        return [
            'cpuUsage' => rand(10, 80) + (rand(0, 99) / 100),
            'memoryUsage' => rand(30, 70) + (rand(0, 99) / 100),
            'batteryLevel' => rand(20, 100),
            'temperature' => rand(25, 45) + (rand(0, 9) / 10),
        ];
    }

    private function generateExecutionOutput(DeviceInstruction $instruction, bool $success): string
    {
        if ($success) {
            return sprintf(
                "执行成功\n任务ID: %s\n脚本ID: %s\n执行时间: %s\n输出: 模拟执行完成",
                $instruction->getTaskId() ?? 'N/A',
                $instruction->getScriptId() ?? 'N/A',
                date('Y-m-d H:i:s')
            );
        }

        return "执行失败\n查看错误信息了解详情";
    }

    private function generateErrorMessage(): string
    {
        $errors = [
            '脚本执行超时',
            '内存不足',
            '网络连接失败',
            '权限被拒绝',
            '脚本语法错误',
            '找不到指定元素',
            '设备资源不足',
        ];

        return $errors[array_rand($errors)];
    }

    private function generateSignature(string $deviceCode, int $timestamp): string
    {
        // 模拟设备签名生成
        return hash('sha256', $deviceCode . $timestamp . 'secret');
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
}
