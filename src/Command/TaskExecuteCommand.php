<?php

declare(strict_types=1);

namespace Tourze\AutoJsControlBundle\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Tourze\AutoJsControlBundle\Entity\Script;
use Tourze\AutoJsControlBundle\Entity\Task;
use Tourze\AutoJsControlBundle\Enum\TaskStatus;
use Tourze\AutoJsControlBundle\Enum\TaskTargetType;
use Tourze\AutoJsControlBundle\Enum\TaskType;
use Tourze\AutoJsControlBundle\Exception\BusinessLogicException;
use Tourze\AutoJsControlBundle\Exception\DeviceTargetException;
use Tourze\AutoJsControlBundle\Exception\ScriptNotFoundException;
use Tourze\AutoJsControlBundle\Exception\TaskConfigurationException;
use Tourze\AutoJsControlBundle\Repository\ScriptRepository;
use Tourze\AutoJsControlBundle\Repository\TaskRepository;
use Tourze\AutoJsControlBundle\Service\TaskScheduler;

#[AsCommand(name: self::NAME, description: '手动执行任务或创建新任务', aliases: ['autojs:task:execute', 'autojs:task:run'], help: <<<'TXT'
    <info>%command.name%</info> 命令用于手动执行任务或创建新任务。

    执行已存在的任务：
      <comment>%command.full_name% 123</comment>                     # 执行ID为123的任务

    创建并执行新任务：
      <comment>%command.full_name% --script-id=10 --all-devices</comment>         # 在所有设备上执行脚本10
      <comment>%command.full_name% --script-code=hello-world --device-ids=1,2,3</comment>  # 在指定设备上执行脚本
      <comment>%command.full_name% --script-id=5 --group-id=2 --monitor</comment>      # 在设备组2上执行并监控进度

    参数说明：
      - task-id: 要执行的现有任务ID
      - --script-id: 脚本ID（与--script-code二选一）
      - --script-code: 脚本代码（与--script-id二选一）
      - --device-ids: 指定设备ID列表
      - --group-id: 指定设备组
      - --all-devices: 在所有在线设备上执行
      - --parameters: JSON格式的参数，如 '{"timeout": 30, "retry": 3}'
      - --scheduled: 计划执行时间，如 '2023-12-25T10:00:00+08:00'
      - --monitor: 实时监控任务执行进度

    注意：
      - 设备选择选项（--device-ids, --group-id, --all-devices）只能选择一个
      - 如果指定了--scheduled，任务将被创建为计划任务
    TXT)]
final class TaskExecuteCommand extends Command
{
    use InputSanitizerTrait;

    public const NAME = 'auto-js:task:execute';

    public function __construct(
        private readonly TaskScheduler $taskScheduler,
        private readonly TaskRepository $taskRepository,
        private readonly ScriptRepository $scriptRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('task-id', InputArgument::OPTIONAL, '要执行的任务ID')
            ->addOption('script-id', 's', InputOption::VALUE_REQUIRED, '脚本ID（创建新任务时使用）')
            ->addOption('script-code', null, InputOption::VALUE_REQUIRED, '脚本代码（创建新任务时使用）')
            ->addOption('device-ids', 'd', InputOption::VALUE_REQUIRED, '目标设备ID列表，逗号分隔')
            ->addOption('group-id', 'g', InputOption::VALUE_REQUIRED, '目标设备组ID')
            ->addOption('all-devices', 'a', InputOption::VALUE_NONE, '在所有在线设备上执行')
            ->addOption('name', null, InputOption::VALUE_REQUIRED, '任务名称')
            ->addOption('description', null, InputOption::VALUE_REQUIRED, '任务描述')
            ->addOption('priority', 'p', InputOption::VALUE_REQUIRED, '任务优先级（0-10）', '5')
            ->addOption('parameters', null, InputOption::VALUE_REQUIRED, '任务参数（JSON格式）')
            ->addOption('scheduled', null, InputOption::VALUE_REQUIRED, '计划执行时间（ISO8601格式）')
            ->addOption('monitor', 'm', InputOption::VALUE_NONE, '实时监控任务执行进度')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        return $this->executeWithErrorHandling($input, $io);
    }

    private function executeWithErrorHandling(InputInterface $input, SymfonyStyle $io): int
    {
        try {
            $result = $this->handleTaskExecution($input, $io);

            return $this->handlePostExecution($result, $input, $io);
        } catch (\InvalidArgumentException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        } catch (\Exception $e) {
            $io->error('执行任务失败: ' . $e->getMessage());

            return Command::FAILURE;
        }
    }

    /**
     * @return array{taskId: ?int}
     */
    private function handleTaskExecution(InputInterface $input, SymfonyStyle $io): array
    {
        $taskId = $input->getArgument('task-id');

        if (null !== $taskId) {
            return $this->executeExistingTask($this->safelyParseInt($taskId), $input, $io);
        }

        return $this->createAndExecuteTask($input, $io);
    }

    /**
     * @param array{taskId: ?int} $result
     */
    private function handlePostExecution(array $result, InputInterface $input, SymfonyStyle $io): int
    {
        if ((bool) $input->getOption('monitor') && null !== $result['taskId']) {
            $this->monitorTaskProgress($result['taskId'], $io);
        }

        return Command::SUCCESS;
    }

    /**
     * @return array{taskId: ?int}
     */
    private function executeExistingTask(int $taskId, InputInterface $input, SymfonyStyle $io): array
    {
        $io->title(sprintf('执行任务 #%d', $taskId));

        $task = $this->findTask($taskId);
        $this->displayTaskInfo($task, $io);

        if ($this->isTaskReExecutable($task)) {
            return $this->executeTask($task, $taskId, $io);
        }

        return $this->handleNonReExecutableTask($task, $taskId, $io);
    }

    private function findTask(int $taskId): Task
    {
        $task = $this->taskRepository->find($taskId);
        if (null === $task) {
            throw BusinessLogicException::taskNotFound($taskId);
        }

        return $task;
    }

    private function displayTaskInfo(Task $task, SymfonyStyle $io): void
    {
        $io->section('任务信息');
        $io->table(
            ['属性', '值'],
            [
                ['任务名称', $task->getName()],
                ['任务类型', $task->getTaskType()->value],
                ['目标类型', $task->getTargetType()->value],
                ['脚本', $task->getScript()?->getName() ?? 'N/A'],
                ['优先级', (string) $task->getPriority()],
                ['当前状态', $task->getStatus()->value],
            ]
        );
    }

    private function isTaskReExecutable(Task $task): bool
    {
        return !$this->isTaskInFinalState($task->getStatus());
    }

    private function isTaskInFinalState(TaskStatus $status): bool
    {
        return in_array($status, [
            TaskStatus::RUNNING,
            TaskStatus::COMPLETED,
            TaskStatus::CANCELLED,
        ], true);
    }

    /**
     * @return array{taskId: int}
     */
    private function executeTask(Task $task, int $taskId, SymfonyStyle $io): array
    {
        $this->taskScheduler->dispatchTask($task);
        $io->success('任务已开始执行');

        return ['taskId' => $taskId];
    }

    /**
     * @return array{taskId: ?int}
     */
    private function handleNonReExecutableTask(Task $task, int $taskId, SymfonyStyle $io): array
    {
        $io->warning(sprintf('任务当前状态为 %s，无法重新执行', $task->getStatus()->value));

        if ($io->confirm('是否创建一个相同配置的新任务？', false)) {
            return $this->createDuplicateTaskAndReturnId($task, $io);
        }

        return ['taskId' => $taskId];
    }

    /**
     * @return array{taskId: ?int}
     */
    private function createDuplicateTaskAndReturnId(Task $task, SymfonyStyle $io): array
    {
        $newTask = $this->duplicateTask($task);
        $io->success(sprintf('已创建新任务 #%d 并开始执行', $newTask->getId()));

        return ['taskId' => $newTask->getId()];
    }

    private function duplicateTask(Task $originalTask): Task
    {
        $newTaskData = $this->buildDuplicateTaskData($originalTask);

        return $this->taskScheduler->createAndScheduleTask($newTaskData);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildDuplicateTaskData(Task $task): array
    {
        $newTaskData = $this->createBaseTaskDataFromOriginal($task);

        return $this->addTargetDataToDuplicate($task, $newTaskData);
    }

    /**
     * @return array<string, mixed>
     */
    private function createBaseTaskDataFromOriginal(Task $task): array
    {
        return [
            'name' => $task->getName() . ' (副本)',
            'description' => $task->getDescription(),
            'taskType' => TaskType::IMMEDIATE->value,
            'targetType' => $task->getTargetType()->value,
            'script' => $task->getScript(),
            'priority' => $task->getPriority(),
            'parameters' => $task->getParameters(),
        ];
    }

    /**
     * @param array<string, mixed> $newTaskData
     *
     * @return array<string, mixed>
     */
    private function addTargetDataToDuplicate(Task $task, array $newTaskData): array
    {
        return match ($task->getTargetType()) {
            TaskTargetType::SPECIFIC => $this->addSpecificDeviceTarget($task, $newTaskData),
            TaskTargetType::GROUP => $this->addGroupTarget($task, $newTaskData),
            default => $newTaskData,
        };
    }

    /**
     * @param array<string, mixed> $newTaskData
     * @return array<string, mixed>
     */
    private function addSpecificDeviceTarget(Task $task, array $newTaskData): array
    {
        $decodedTargets = $this->decodeTargetDeviceIds($task);
        $newTaskData['targetDevices'] = $decodedTargets;

        return $newTaskData;
    }

    /**
     * @return array<mixed>
     */
    private function decodeTargetDeviceIds(Task $task): array
    {
        $decodedTargets = json_decode($task->getTargetDeviceIds() ?? '[]', true);
        if (!is_array($decodedTargets)) {
            throw new \InvalidArgumentException('Invalid JSON in target device IDs');
        }

        return $decodedTargets;
    }

    /**
     * @param array<string, mixed> $newTaskData
     * @return array<string, mixed>
     */
    private function addGroupTarget(Task $task, array $newTaskData): array
    {
        $newTaskData['targetGroupId'] = $task->getTargetGroup()?->getId();

        return $newTaskData;
    }

    /**
     * @return array{taskId: ?int}
     */
    private function createAndExecuteTask(InputInterface $input, SymfonyStyle $io): array
    {
        $io->title('创建并执行新任务');

        $script = $this->resolveScript($input);
        $taskData = $this->buildTaskData($script, $input);

        if (!$this->confirmTaskCreation($taskData, $script, $io)) {
            return ['taskId' => null];
        }

        return $this->scheduleTaskAndReturnId($taskData, $io);
    }

    /**
     * @param array<string, mixed> $taskData
     * @return array{taskId: ?int}
     */
    private function scheduleTaskAndReturnId(array $taskData, SymfonyStyle $io): array
    {
        $task = $this->taskScheduler->createAndScheduleTask($taskData);
        $this->displayTaskCreationSuccess($task, $taskData, $io);

        return ['taskId' => $task->getId()];
    }

    private function resolveScript(InputInterface $input): Script
    {
        $scriptParams = $this->validateScriptParameters($input);

        return $this->findScriptByParams($scriptParams);
    }

    /**
     * @param array{hasScriptId: bool, hasScriptCode: bool, scriptId: mixed, scriptCode: mixed} $scriptParams
     */
    private function findScriptByParams(array $scriptParams): Script
    {
        if ($scriptParams['hasScriptId']) {
            return $this->findScriptById($this->safelyParseInt($scriptParams['scriptId']));
        }

        return $this->findScriptByCode($this->safelyParseString($scriptParams['scriptCode']));
    }

    /**
     * @return array{hasScriptId: bool, hasScriptCode: bool, scriptId: mixed, scriptCode: mixed}
     */
    private function validateScriptParameters(InputInterface $input): array
    {
        $scriptId = $input->getOption('script-id');
        $scriptCode = $input->getOption('script-code');

        $hasScriptId = null !== $scriptId && '' !== $scriptId;
        $hasScriptCode = null !== $scriptCode && '' !== $scriptCode;

        if (!$hasScriptId && !$hasScriptCode) {
            throw TaskConfigurationException::scriptParameterRequired();
        }

        if ($hasScriptId && $hasScriptCode) {
            throw TaskConfigurationException::scriptParametersExclusive();
        }

        return [
            'hasScriptId' => $hasScriptId,
            'hasScriptCode' => $hasScriptCode,
            'scriptId' => $scriptId,
            'scriptCode' => $scriptCode,
        ];
    }

    private function findScriptById(int $scriptId): Script
    {
        $script = $this->scriptRepository->find($scriptId);
        if (null === $script) {
            throw ScriptNotFoundException::byId($scriptId);
        }

        return $script;
    }

    private function findScriptByCode(string $scriptCode): Script
    {
        $script = $this->scriptRepository->findOneBy(['code' => $scriptCode]);
        if (null === $script) {
            throw ScriptNotFoundException::byCode($scriptCode);
        }

        return $script;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildTaskData(Script $script, InputInterface $input): array
    {
        $targetType = $this->determineTargetType($input);
        $targetData = $this->getTargetData($input, $targetType);
        $scheduled = $input->getOption('scheduled');

        return $this->createCompleteTaskData($script, $input, $targetType, $scheduled, $targetData);
    }

    /**
     * @param array<string, mixed> $targetData
     * @return array<string, mixed>
     */
    private function createCompleteTaskData(Script $script, InputInterface $input, TaskTargetType $targetType, mixed $scheduled, array $targetData): array
    {
        $taskData = [
            'name' => $this->generateTaskName($script, $input),
            'description' => $this->generateTaskDescription($script),
            'taskType' => $this->determineTaskType($scheduled),
            'targetType' => $targetType->value,
            'script' => $script,
            'priority' => $this->safelyParseInt($input->getOption('priority')),
            'parameters' => $input->getOption('parameters'),
        ];

        if (null !== $scheduled) {
            $taskData['scheduledTime'] = $scheduled;
        }

        return array_merge($taskData, $targetData);
    }

    private function generateTaskName(Script $script, InputInterface $input): string
    {
        $optionName = $input->getOption('name');
        if (is_string($optionName) && '' !== $optionName) {
            return $optionName;
        }

        return sprintf('手动执行: %s', $script->getName() ?? 'Unknown');
    }

    private function generateTaskDescription(Script $script): string
    {
        $code = $script->getCode();

        return sprintf('通过命令行手动执行脚本 %s', $code ?? 'Unknown');
    }

    private function determineTaskType(mixed $scheduled): string
    {
        return null !== $scheduled ? TaskType::SCHEDULED->value : TaskType::IMMEDIATE->value;
    }

    /**
     * @param array<string, mixed> $taskData
     */
    private function confirmTaskCreation(array $taskData, Script $script, SymfonyStyle $io): bool
    {
        $this->displayTaskConfiguration($taskData, $script, $io);

        return $this->confirmCreation($io);
    }

    /**
     * @param array<string, mixed> $taskData
     */
    private function displayTaskConfiguration(array $taskData, Script $script, SymfonyStyle $io): void
    {
        $io->section('任务配置');
        $rows = $this->buildConfigurationRows($taskData, $script);
        $io->table(['属性', '值'], $rows);
    }

    /**
     * @param array<string, mixed> $taskData
     * @return array<array{string, string}>
     */
    private function buildConfigurationRows(array $taskData, Script $script): array
    {
        $name = $this->convertToStringValue($taskData['name'] ?? '');
        $targetType = $this->convertToStringValue($taskData['targetType'] ?? '');
        $priority = $this->convertToStringValue($taskData['priority'] ?? '');
        $taskType = $this->convertToStringValue($taskData['taskType'] ?? '');
        $scheduledTime = $this->convertToStringValue($taskData['scheduledTime'] ?? '立即执行');

        return [
            ['任务名称', $name],
            ['脚本', $script->getName() ?? 'Unknown'],
            ['目标类型', $targetType],
            ['优先级', $priority],
            ['任务类型', $taskType],
            ['计划时间', $scheduledTime],
        ];
    }

    private function convertToStringValue(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }
        if (is_scalar($value)) {
            return (string) $value;
        }

        return '';
    }

    private function confirmCreation(SymfonyStyle $io): bool
    {
        $confirmed = $io->confirm('确认创建并执行此任务？', true);
        if (!$confirmed) {
            $io->comment('操作已取消');
        }

        return $confirmed;
    }

    /**
     * @param array<string, mixed> $taskData
     */
    private function displayTaskCreationSuccess(Task $task, array $taskData, SymfonyStyle $io): void
    {
        $taskId = $task->getId();
        $io->success(sprintf(
            '任务 #%d 已创建%s',
            $taskId ?? 0,
            isset($taskData['scheduledTime']) ? '，将在指定时间执行' : '并开始执行'
        ));
    }

    private function determineTargetType(InputInterface $input): TaskTargetType
    {
        $targetOptions = $this->extractTargetOptions($input);

        $this->validateTargetOptions($targetOptions['deviceIds'], $targetOptions['groupId'], $targetOptions['allDevices']);

        return $this->selectTargetType($targetOptions['deviceIds'], $targetOptions['groupId']);
    }

    /**
     * @return array{deviceIds: mixed, groupId: mixed, allDevices: mixed}
     */
    private function extractTargetOptions(InputInterface $input): array
    {
        return [
            'deviceIds' => $input->getOption('device-ids'),
            'groupId' => $input->getOption('group-id'),
            'allDevices' => $input->getOption('all-devices'),
        ];
    }

    private function validateTargetOptions(mixed $deviceIds, mixed $groupId, mixed $allDevices): void
    {
        $optionCount = (null !== $deviceIds ? 1 : 0) + (null !== $groupId ? 1 : 0) + (true === $allDevices ? 1 : 0);

        if (0 === $optionCount) {
            throw DeviceTargetException::targetDeviceRequired();
        }

        if ($optionCount > 1) {
            throw DeviceTargetException::targetDeviceOptionsExclusive();
        }
    }

    private function selectTargetType(mixed $deviceIds, mixed $groupId): TaskTargetType
    {
        if (null !== $deviceIds) {
            return TaskTargetType::SPECIFIC;
        }
        if (null !== $groupId) {
            return TaskTargetType::GROUP;
        }

        return TaskTargetType::ALL;
    }

    /**
     * @return array<string, mixed>
     */
    private function getTargetData(InputInterface $input, TaskTargetType $targetType): array
    {
        return match ($targetType) {
            TaskTargetType::SPECIFIC => $this->getSpecificDevicesData($input),
            TaskTargetType::GROUP => ['targetGroupId' => $this->safelyParseInt($input->getOption('group-id'))],
            TaskTargetType::ALL => [],
        };
    }

    /**
     * @return array{targetDevices: array<int>}
     */
    private function getSpecificDevicesData(InputInterface $input): array
    {
        $deviceIdsString = $this->safelyParseString($input->getOption('device-ids'));
        $deviceIds = array_map('intval', explode(',', $deviceIdsString));

        return ['targetDevices' => $deviceIds];
    }

    private function monitorTaskProgress(int $taskId, SymfonyStyle $io): void
    {
        $io->section('监控任务进度');

        $monitor = $this->initializeMonitor();

        $monitor = $this->runMonitoringLoop($taskId, $monitor, $io);
    }

    /**
     * @param array{progressBar: ?ProgressBar, lastStatus: ?TaskStatus, checkInterval: int} $monitor
     * @return array{progressBar: ?ProgressBar, lastStatus: ?TaskStatus, checkInterval: int}
     */
    private function runMonitoringLoop(int $taskId, array $monitor, SymfonyStyle $io): array
    {
        while ($this->shouldContinueMonitoring($taskId, $io)) {
            $task = $this->fetchTask($taskId, $io);
            if (null === $task) {
                break;
            }

            $monitor = $this->updateMonitorProgress($task, $monitor, $io);

            if ($this->isTaskComplete($task)) {
                $this->finalizeMonitoring($task, $monitor['progressBar'], $io);
                break;
            }

            sleep($monitor['checkInterval']);
        }

        return $monitor;
    }

    private function shouldContinueMonitoring(int $taskId, SymfonyStyle $io): bool
    {
        $task = $this->taskRepository->find($taskId);

        return null !== $task;
    }

    /**
     * @return array{progressBar: ?ProgressBar, lastStatus: ?TaskStatus, checkInterval: int}
     */
    private function initializeMonitor(): array
    {
        return [
            'progressBar' => null,
            'lastStatus' => null,
            'checkInterval' => 2,
        ];
    }

    private function fetchTask(int $taskId, SymfonyStyle $io): ?Task
    {
        $task = $this->taskRepository->find($taskId);
        if (null === $task) {
            $io->error('任务不存在');
        }

        return $task;
    }

    /**
     * @param array{progressBar: ?ProgressBar, lastStatus: ?TaskStatus, checkInterval: int} $monitor
     * @return array{progressBar: ?ProgressBar, lastStatus: ?TaskStatus, checkInterval: int}
     */
    private function updateMonitorProgress(Task $task, array $monitor, SymfonyStyle $io): array
    {
        $monitor['progressBar'] = $this->ensureProgressBar($task, $monitor['progressBar'], $io);

        if (null !== $monitor['progressBar']) {
            $this->updateProgressBar($task, $monitor['progressBar']);
        }

        if ($task->getStatus() !== $monitor['lastStatus']) {
            $this->displayStatusChange($task, $monitor['progressBar'], $io);
            $monitor['lastStatus'] = $task->getStatus();
        }

        return $monitor;
    }

    private function ensureProgressBar(Task $task, ?ProgressBar $progressBar, SymfonyStyle $io): ?ProgressBar
    {
        // Skip if already exists or no devices
        if (null !== $progressBar || $task->getTotalDevices() <= 0) {
            return $progressBar;
        }

        return $this->createProgressBar($task, $io);
    }

    private function createProgressBar(Task $task, SymfonyStyle $io): ProgressBar
    {
        $newProgressBar = new ProgressBar($io, $task->getTotalDevices());
        $newProgressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% - 成功: %success% 失败: %failed%');
        $newProgressBar->setMessage((string) $task->getSuccessDevices(), 'success');
        $newProgressBar->setMessage((string) $task->getFailedDevices(), 'failed');
        $newProgressBar->start();

        return $newProgressBar;
    }

    private function updateProgressBar(Task $task, ProgressBar $progressBar): void
    {
        $completed = $task->getSuccessDevices() + $task->getFailedDevices();
        $progressBar->setProgress($completed);
        $progressBar->setMessage((string) $task->getSuccessDevices(), 'success');
        $progressBar->setMessage((string) $task->getFailedDevices(), 'failed');
    }

    private function displayStatusChange(Task $task, ?ProgressBar $progressBar, SymfonyStyle $io): void
    {
        if (null !== $progressBar) {
            $progressBar->clear();
        }

        $statusMessage = sprintf(
            '[%s] 任务状态: <fg=%s>%s</>',
            new \DateTime()->format('H:i:s'),
            $this->getStatusColor($task->getStatus()),
            $task->getStatus()->value
        );
        $io->writeln($statusMessage);

        if (null !== $progressBar) {
            $progressBar->display();
        }
    }

    private function isTaskComplete(Task $task): bool
    {
        return $this->isCompletedStatus($task->getStatus());
    }

    private function isCompletedStatus(TaskStatus $status): bool
    {
        return in_array($status, [
            TaskStatus::COMPLETED,
            TaskStatus::PARTIALLY_COMPLETED,
            TaskStatus::FAILED,
            TaskStatus::CANCELLED,
        ], true);
    }

    private function finalizeMonitoring(Task $task, ?ProgressBar $progressBar, SymfonyStyle $io): void
    {
        if (null !== $progressBar) {
            $progressBar->finish();
            $io->newLine(2);
        }

        $this->showTaskResult($task, $io);
    }

    private function showTaskResult(Task $task, SymfonyStyle $io): void
    {
        $io->section('任务执行结果');

        $statusColor = $this->getStatusColor($task->getStatus());
        $io->table(
            ['指标', '值'],
            $this->buildTaskResultRows($task, $statusColor)
        );

        if (null !== $task->getFailureReason()) {
            $io->error('失败原因: ' . $task->getFailureReason());
        }
    }

    /**
     * @return array<array{string, string}>
     */
    private function buildTaskResultRows(Task $task, string $statusColor): array
    {
        return [
            ['最终状态', sprintf('<fg=%s>%s</>', $statusColor, $task->getStatus()->value)],
            ['目标设备数', (string) $task->getTotalDevices()],
            ['成功设备数', sprintf('<fg=green>%d</>', $task->getSuccessDevices())],
            ['失败设备数', sprintf('<fg=red>%d</>', $task->getFailedDevices())],
            ['开始时间', $this->formatDateTime($task->getStartTime())],
            ['结束时间', $this->formatDateTime($task->getEndTime())],
            ['执行时长', $this->calculateDuration($task)],
        ];
    }

    private function formatDateTime(?\DateTimeImmutable $dateTime): string
    {
        return $dateTime?->format('Y-m-d H:i:s') ?? 'N/A';
    }

    private function getStatusColor(TaskStatus $status): string
    {
        $colorMap = $this->createStatusColorMap();

        return $colorMap[$status->value] ?? 'default';
    }

    /**
     * @return array<string, string>
     */
    private function createStatusColorMap(): array
    {
        return [
            TaskStatus::PENDING->value => 'yellow',
            TaskStatus::SCHEDULED->value => 'cyan',
            TaskStatus::RUNNING->value => 'blue',
            TaskStatus::COMPLETED->value => 'green',
            TaskStatus::PARTIALLY_COMPLETED->value => 'yellow',
            TaskStatus::FAILED->value => 'red',
            TaskStatus::CANCELLED->value => 'gray',
            TaskStatus::PAUSED->value => 'magenta',
        ];
    }

    private function calculateDuration(Task $task): string
    {
        $startTime = $task->getStartTime();
        $endTime = $task->getEndTime();

        if (null === $startTime || null === $endTime) {
            return 'N/A';
        }

        $interval = $startTime->diff($endTime);

        return $this->formatDurationParts($interval);
    }

    private function formatDurationParts(\DateInterval $interval): string
    {
        $parts = [];

        if ($interval->h > 0) {
            $parts[] = $interval->h . '小时';
        }
        if ($interval->i > 0) {
            $parts[] = $interval->i . '分钟';
        }
        if ($interval->s > 0 || [] === $parts) {
            $parts[] = $interval->s . '秒';
        }

        return implode(' ', $parts);
    }
}
