<?php

namespace Tourze\AutoJsControlBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Tourze\AutoJsControlBundle\Entity\Script;
use Tourze\AutoJsControlBundle\Entity\Task;
use Tourze\AutoJsControlBundle\Enum\TaskStatus;
use Tourze\AutoJsControlBundle\Enum\TaskTargetType;
use Tourze\AutoJsControlBundle\Enum\TaskType;
use Tourze\AutoJsControlBundle\Exception\BusinessLogicException;
use Tourze\AutoJsControlBundle\Exception\InvalidTaskArgumentException;
use Tourze\AutoJsControlBundle\Repository\ScriptRepository;
use Tourze\AutoJsControlBundle\Repository\TaskRepository;

/**
 * 任务CRUD服务
 *
 * 负责任务的创建、更新、删除等操作
 */
readonly class TaskCrudService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private TaskRepository $taskRepository,
        private ScriptRepository $scriptRepository,
        private TaskTargetService $targetService,
        private ValidatorInterface $validator,
    ) {
    }

    /**
     * 获取EntityManager（仅用于测试）.
     */
    public function getEntityManager(): EntityManagerInterface
    {
        return $this->entityManager;
    }

    /**
     * 获取TaskRepository（仅用于测试）.
     */
    public function getTaskRepository(): TaskRepository
    {
        return $this->taskRepository;
    }

    /**
     * 获取ScriptRepository（仅用于测试）.
     */
    public function getScriptRepository(): ScriptRepository
    {
        return $this->scriptRepository;
    }

    /**
     * 获取TargetService（仅用于测试）.
     */
    public function getTargetService(): TaskTargetService
    {
        return $this->targetService;
    }

    /**
     * 获取Validator（仅用于测试）.
     */
    public function getValidator(): ValidatorInterface
    {
        return $this->validator;
    }

    /**
     * 创建任务
     *
     * @param array<string, mixed> $data
     */
    public function createTask(array $data): Task
    {
        $this->validateRequiredFields($data);

        $scriptId = $this->ensureInt($data['scriptId'], 'scriptId');
        $script = $this->getScript($scriptId);

        $task = $this->buildTask($data, $script);
        $this->processTargetDevices($task, $data);
        $this->setScheduledTime($task, $data);
        $this->setCronExpression($task, $data);

        $this->validateAndSave($task);

        return $task;
    }

    /**
     * 更新任务
     *
     * @param array<string, mixed> $data
     */
    public function updateTask(int $taskId, array $data): Task
    {
        $task = $this->getTaskById($taskId);

        if (!$this->canUpdateTask($task)) {
            throw BusinessLogicException::taskStateError('只能更新待执行或已暂停的任务');
        }

        $this->updateBasicInfo($task, $data);
        $this->updateScript($task, $data);
        $this->updateParameters($task, $data);
        $this->updateTargetDevices($task, $data);
        $this->updateSchedule($task, $data);

        $this->validateAndSave($task);

        return $task;
    }

    /**
     * 删除任务（软删除）.
     */
    public function deleteTask(int $taskId): void
    {
        $task = $this->getTaskById($taskId);

        if (!$this->canDeleteTask($task)) {
            throw BusinessLogicException::invalidRequest('不能删除正在执行的任务');
        }

        $task->setValid(false);
        $this->entityManager->flush();
    }

    /**
     * 执行任务
     */
    public function executeTask(int $taskId): Task
    {
        $task = $this->getTaskById($taskId);

        if (!$this->canExecuteTask($task)) {
            throw BusinessLogicException::invalidRequest('只能执行待执行或已暂停的任务');
        }

        $script = $task->getScript();
        if (null === $script) {
            throw BusinessLogicException::invalidRequest('任务关联的脚本不存在');
        }

        if (!$script->isValid()) {
            throw BusinessLogicException::invalidRequest('任务关联的脚本已禁用');
        }

        return $task;
    }

    /**
     * 暂停任务
     */
    public function pauseTask(int $taskId): Task
    {
        $task = $this->getTaskById($taskId);

        if (!$this->canPauseTask($task)) {
            throw BusinessLogicException::invalidRequest('只能暂停待执行或正在执行的任务');
        }

        $task->setStatus(TaskStatus::PAUSED);
        $this->entityManager->flush();

        return $task;
    }

    /**
     * 恢复任务
     */
    public function resumeTask(int $taskId): Task
    {
        $task = $this->getTaskById($taskId);

        if (TaskStatus::PAUSED !== $task->getStatus()) {
            throw BusinessLogicException::invalidRequest('只能恢复已暂停的任务');
        }

        $task->setStatus(TaskStatus::PENDING);
        $this->entityManager->flush();

        return $task;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function validateRequiredFields(array $data): void
    {
        $required = ['name', 'scriptId', 'taskType', 'targetType'];
        foreach ($required as $field) {
            if (!isset($data[$field]) || '' === $data[$field]) {
                throw BusinessLogicException::invalidRequest("字段 {$field} 不能为空");
            }
        }
    }

    private function getTaskById(int $taskId): Task
    {
        $task = $this->taskRepository->find($taskId);
        if (null === $task) {
            throw BusinessLogicException::resourceStateError('任务不存在');
        }

        return $task;
    }

    private function getScript(int $scriptId): Script
    {
        $script = $this->scriptRepository->find($scriptId);
        if (null === $script || !$script->isValid()) {
            throw BusinessLogicException::invalidRequest('脚本不存在或已禁用');
        }

        return $script;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function buildTask(array $data, Script $script): Task
    {
        $task = new Task();

        $this->setTaskName($task, $data);
        $this->setTaskDescription($task, $data);
        $task->setScript($script);
        $this->setTaskType($task, $data);
        $this->setTaskTargetType($task, $data);
        $this->setTaskPriority($task, $data);
        $this->setTaskParameters($task, $data);

        return $task;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function setTaskName(Task $task, array $data): void
    {
        $name = $this->ensureString($data['name'], 'name');
        $task->setName($name);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function setTaskDescription(Task $task, array $data): void
    {
        $description = $this->ensureOptionalString($data['description'] ?? null, 'description');
        $task->setDescription($description);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function setTaskType(Task $task, array $data): void
    {
        $taskType = $this->ensureStringOrInt($data['taskType'], 'taskType');
        $task->setTaskType(TaskType::from($taskType));
    }

    /**
     * @param array<string, mixed> $data
     */
    private function setTaskTargetType(Task $task, array $data): void
    {
        $targetType = $this->ensureStringOrInt($data['targetType'], 'targetType');
        $task->setTargetType(TaskTargetType::from($targetType));
    }

    /**
     * @param array<string, mixed> $data
     */
    private function setTaskPriority(Task $task, array $data): void
    {
        $priority = $this->ensureInt($data['priority'] ?? 0, 'priority');
        $task->setPriority($priority);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function setTaskParameters(Task $task, array $data): void
    {
        if (!isset($data['parameters'])) {
            return;
        }

        $encoded = json_encode($data['parameters']);
        if (false === $encoded) {
            throw new InvalidTaskArgumentException('JSON encoding of parameters failed');
        }
        $task->setParameters($encoded);
    }

    /**
     * 确保值是字符串类型
     */
    private function ensureString(mixed $value, string $fieldName): string
    {
        if (!is_string($value)) {
            throw BusinessLogicException::invalidRequest("{$fieldName} 必须是字符串");
        }

        return $value;
    }

    /**
     * 确保值是字符串或null
     */
    private function ensureOptionalString(mixed $value, string $fieldName): ?string
    {
        if (null !== $value && !is_string($value)) {
            throw BusinessLogicException::invalidRequest("{$fieldName} 必须是字符串或null");
        }

        return $value;
    }

    /**
     * 确保值是整数类型
     */
    private function ensureInt(mixed $value, string $fieldName): int
    {
        if (!is_int($value)) {
            throw BusinessLogicException::invalidRequest("{$fieldName} 必须是整数");
        }

        return $value;
    }

    /**
     * 确保值是字符串或整数
     */
    private function ensureStringOrInt(mixed $value, string $fieldName): string|int
    {
        if (!is_string($value) && !is_int($value)) {
            throw BusinessLogicException::invalidRequest("{$fieldName} 必须是字符串或整数");
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function processTargetDevices(Task $task, array $data): void
    {
        $this->targetService->processTargetDevices($task, $data);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function setScheduledTime(Task $task, array $data): void
    {
        if (isset($data['scheduledTime'])) {
            $scheduledTime = $this->ensureString($data['scheduledTime'], 'scheduledTime');
            $task->setScheduledTime(new \DateTimeImmutable($scheduledTime));
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private function setCronExpression(Task $task, array $data): void
    {
        if (TaskType::RECURRING === $task->getTaskType() && isset($data['cronExpression'])) {
            $cronExpression = $this->ensureString($data['cronExpression'], 'cronExpression');
            $task->setCronExpression($cronExpression);
        }
    }

    private function canUpdateTask(Task $task): bool
    {
        return in_array($task->getStatus(), [TaskStatus::PENDING, TaskStatus::PAUSED], true);
    }

    private function canDeleteTask(Task $task): bool
    {
        return !in_array($task->getStatus(), [TaskStatus::RUNNING, TaskStatus::PARTIALLY_COMPLETED], true);
    }

    private function canExecuteTask(Task $task): bool
    {
        return in_array($task->getStatus(), [TaskStatus::PENDING, TaskStatus::PAUSED], true);
    }

    private function canPauseTask(Task $task): bool
    {
        return in_array($task->getStatus(), [TaskStatus::PENDING, TaskStatus::RUNNING], true);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function updateBasicInfo(Task $task, array $data): void
    {
        if (isset($data['name'])) {
            $name = $this->ensureString($data['name'], 'name');
            $task->setName($name);
        }
        if (array_key_exists('description', $data)) {
            $description = $this->ensureOptionalString($data['description'], 'description');
            $task->setDescription($description);
        }
        if (isset($data['priority'])) {
            $priority = $this->ensureInt($data['priority'], 'priority');
            $task->setPriority($priority);
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private function updateScript(Task $task, array $data): void
    {
        if (isset($data['scriptId'])) {
            $scriptId = $this->ensureInt($data['scriptId'], 'scriptId');
            $script = $this->getScript($scriptId);
            $task->setScript($script);
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private function updateParameters(Task $task, array $data): void
    {
        if (isset($data['parameters'])) {
            $encoded = json_encode($data['parameters']);
            if (false === $encoded) {
                throw new InvalidTaskArgumentException('JSON encoding of parameters failed');
            }
            $task->setParameters($encoded);
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private function updateTargetDevices(Task $task, array $data): void
    {
        $hasTargetUpdate = isset($data['targetType']) || isset($data['targetDevices']) || isset($data['targetGroupId']);
        if (!$hasTargetUpdate) {
            return;
        }

        if (isset($data['targetType'])) {
            $targetType = $this->ensureStringOrInt($data['targetType'], 'targetType');
            $task->setTargetType(TaskTargetType::from($targetType));
        }
        $this->targetService->processTargetDevices($task, $data);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function updateSchedule(Task $task, array $data): void
    {
        if (isset($data['scheduledTime'])) {
            $scheduledTime = $this->ensureString($data['scheduledTime'], 'scheduledTime');
            $task->setScheduledTime(new \DateTimeImmutable($scheduledTime));
        }

        if (array_key_exists('cronExpression', $data)) {
            $cronExpression = $this->ensureOptionalString($data['cronExpression'], 'cronExpression');
            $task->setCronExpression($cronExpression);
        }
    }

    private function validateAndSave(Task $task): void
    {
        $violations = $this->validator->validate($task);
        if (count($violations) > 0) {
            $errors = [];
            foreach ($violations as $violation) {
                $errors[$violation->getPropertyPath()] = $violation->getMessage();
            }
            $errorJson = json_encode($errors);
            if (false === $errorJson) {
                throw BusinessLogicException::validationFailed('验证失败且无法编码错误信息');
            }
            throw BusinessLogicException::validationFailed('验证失败: ' . $errorJson);
        }

        if (null === $task->getId()) {
            $this->entityManager->persist($task);
        }
        $this->entityManager->flush();
    }
}
