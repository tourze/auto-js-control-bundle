<?php

namespace Tourze\AutoJsControlBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Tourze\AutoJsControlBundle\Entity\Script;
use Tourze\AutoJsControlBundle\Entity\Task;
use Tourze\AutoJsControlBundle\Enum\TaskStatus;
use Tourze\AutoJsControlBundle\Enum\TaskTargetType;
use Tourze\AutoJsControlBundle\Enum\TaskType;
use Tourze\AutoJsControlBundle\Exception\InvalidTaskArgumentException;

/**
 * 任务创建服务
 *
 * 专门负责任务的创建和基本验证
 */
readonly class TaskCreationService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private DeviceTargetResolver $deviceTargetResolver,
    ) {
    }

    /**
     * 创建任务
     *
     * @param array<string, mixed> $data
     */
    public function createTask(array $data): Task
    {
        $task = $this->createTaskEntity($data);
        $this->setTaskBasicProperties($task, $data);
        $this->setTaskScript($task, $data);
        $this->setTaskParameters($task, $data);
        $this->deviceTargetResolver->resolveTargetDevices($task, $data);
        $this->setTaskScheduling($task, $data);

        $this->entityManager->persist($task);
        $this->entityManager->flush();

        return $task;
    }

    /**
     * 创建任务实体.
     *
     * @param array<string, mixed> $data
     */
    private function createTaskEntity(array $data): Task
    {
        $task = new Task();
        $task->setStatus(TaskStatus::PENDING);

        return $task;
    }

    /**
     * 设置任务基本属性.
     *
     * @param array<string, mixed> $data
     */
    private function setTaskBasicProperties(Task $task, array $data): void
    {
        if (!isset($data['name']) || !is_string($data['name'])) {
            throw new InvalidTaskArgumentException('任务名称必须是字符串');
        }
        $task->setName($data['name']);

        $description = $data['description'] ?? null;
        if (null !== $description && !is_string($description)) {
            throw new InvalidTaskArgumentException('任务描述必须是字符串或null');
        }
        $task->setDescription($description);

        if (!isset($data['taskType']) || !(is_string($data['taskType']) || is_int($data['taskType']))) {
            throw new InvalidTaskArgumentException('任务类型必须是字符串或整数');
        }
        $task->setTaskType(TaskType::from($data['taskType']));

        if (!isset($data['targetType']) || !(is_string($data['targetType']) || is_int($data['targetType']))) {
            throw new InvalidTaskArgumentException('目标类型必须是字符串或整数');
        }
        $task->setTargetType(TaskTargetType::from($data['targetType']));

        $priority = $data['priority'] ?? 0;
        if (!is_int($priority) && !is_numeric($priority)) {
            throw new InvalidTaskArgumentException('优先级必须是整数');
        }
        $task->setPriority((int) $priority);
    }

    /**
     * 设置任务脚本.
     *
     * @param array<string, mixed> $data
     */
    private function setTaskScript(Task $task, array $data): void
    {
        if (!isset($data['script'])) {
            return;
        }

        $script = $data['script'];
        if (!($script instanceof Script)) {
            throw new InvalidTaskArgumentException('任务脚本必须是Script实体');
        }

        $task->setScript($script);
    }

    /**
     * 设置任务参数.
     *
     * @param array<string, mixed> $data
     */
    private function setTaskParameters(Task $task, array $data): void
    {
        if (!isset($data['parameters'])) {
            return;
        }

        $parametersString = $this->encodeParameters($data['parameters']);
        $task->setParameters($parametersString);
    }

    /**
     * 编码参数.
     * @param mixed $parameters
     */
    private function encodeParameters($parameters): string
    {
        if (is_string($parameters)) {
            return $parameters;
        }

        $encoded = json_encode($parameters);
        if (false === $encoded) {
            throw new InvalidTaskArgumentException('JSON encoding of parameters failed');
        }

        return $encoded;
    }

    /**
     * 设置任务调度相关属性.
     *
     * @param array<string, mixed> $data
     */
    private function setTaskScheduling(Task $task, array $data): void
    {
        $this->setScheduledTime($task, $data);
        $this->setCronExpressionForRecurring($task, $data);
    }

    /**
     * 设置计划时间.
     *
     * @param array<string, mixed> $data
     */
    private function setScheduledTime(Task $task, array $data): void
    {
        if (!isset($data['scheduledTime'])) {
            return;
        }

        $scheduledTime = $data['scheduledTime'];
        if (!is_string($scheduledTime)) {
            throw new InvalidTaskArgumentException('计划时间必须是字符串');
        }

        $task->setScheduledTime(new \DateTimeImmutable($scheduledTime));
    }

    /**
     * 为循环任务设置Cron表达式.
     *
     * @param array<string, mixed> $data
     */
    private function setCronExpressionForRecurring(Task $task, array $data): void
    {
        if (TaskType::RECURRING !== $task->getTaskType()) {
            return;
        }

        if (!isset($data['cronExpression'])) {
            throw new InvalidTaskArgumentException('循环任务必须指定Cron表达式');
        }

        $cronExpression = $data['cronExpression'];
        if (!is_string($cronExpression)) {
            throw new InvalidTaskArgumentException('Cron表达式必须是字符串');
        }

        $task->setCronExpression($cronExpression);
    }
}
