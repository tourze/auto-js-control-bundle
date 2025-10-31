<?php

namespace Tourze\AutoJsControlBundle\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Tourze\AutoJsControlBundle\Entity\AutoJsDevice;
use Tourze\AutoJsControlBundle\Event\InstructionSentEvent;
use Tourze\AutoJsControlBundle\Exception\BusinessLogicException;
use Tourze\AutoJsControlBundle\Exception\InvalidArgumentException;
use Tourze\AutoJsControlBundle\Repository\AutoJsDeviceRepository;
use Tourze\AutoJsControlBundle\ValueObject\DeviceInstruction;
use Tourze\AutoJsControlBundle\ValueObject\RedisQueueKeys;
use Tourze\LockServiceBundle\Service\LockService;

/**
 * 指令队列管理服务
 *
 * 负责管理设备指令队列的增删改查和长轮询支持
 */
readonly class InstructionQueueService
{
    public function __construct(
        private AutoJsDeviceRepository $deviceRepository,
        private EventDispatcherInterface $eventDispatcher,
        private LoggerInterface $logger,
        private StorageAdapterInterface $storage,
        private CacheStorageService $cacheStorage,
        private LockService $lockService,
    ) {
    }

    /**
     * 发送指令到设备队列.
     *
     * @param string            $deviceCode  设备代码
     * @param DeviceInstruction $instruction 指令对象
     * @param bool              $priority    是否高优先级（插入队列头部）
     */
    public function sendInstruction(
        string $deviceCode,
        DeviceInstruction $instruction,
        bool $priority = false,
    ): void {
        $lockKey = sprintf('device_instruction_queue:%s', $deviceCode);

        $this->lockService->blockingRun($lockKey, function () use ($deviceCode, $instruction, $priority): void {
            try {
                $this->executeInstructionSending($deviceCode, $instruction, $priority);
            } catch (\Exception $e) {
                $this->handleInstructionSendingFailure($deviceCode, $instruction, $e);
            }
        });
    }

    private function executeInstructionSending(
        string $deviceCode,
        DeviceInstruction $instruction,
        bool $priority,
    ): void {
        // 验证设备是否存在并获取设备对象
        $device = $this->validateAndGetDevice($deviceCode);

        $queueKey = RedisQueueKeys::getDeviceInstructionQueue($deviceCode);
        $instructionData = $this->encodeInstruction($instruction);

        $this->pushInstructionToQueue($queueKey, $instructionData, $priority);
        $this->notifyDevice($deviceCode);
        $this->markInstructionAsPending($instruction);
        $this->logInstructionSent($deviceCode, $instruction, $priority);
        $this->dispatchInstructionEventWithDevice($device, $instruction, true, null, $deviceCode);
    }

    private function validateAndGetDevice(string $deviceCode): AutoJsDevice
    {
        $device = $this->deviceRepository->findOneByDeviceCode($deviceCode);
        if (null === $device) {
            throw new \Exception("Device not found: {$deviceCode}");
        }

        return $device;
    }

    private function encodeInstruction(DeviceInstruction $instruction): string
    {
        $instructionData = json_encode($instruction->toArray());
        if (false === $instructionData) {
            throw new InvalidArgumentException('JSON encoding of instruction failed');
        }

        return $instructionData;
    }

    private function pushInstructionToQueue(string $queueKey, string $instructionData, bool $priority): void
    {
        if ($priority) {
            $this->storage->lPush($queueKey, $instructionData);

            return;
        }

        $this->storage->rPush($queueKey, $instructionData);
    }

    private function markInstructionAsPending(DeviceInstruction $instruction): void
    {
        $this->cacheStorage->updateInstructionStatus($instruction->getInstructionId(), [
            'status' => 'pending',
            'updateTime' => (new \DateTimeImmutable())->format(\DateTimeInterface::RFC3339),
        ]);
    }

    private function logInstructionSent(string $deviceCode, DeviceInstruction $instruction, bool $priority): void
    {
        $this->logger->info('指令已发送到设备队列', [
            'deviceCode' => $deviceCode,
            'instructionId' => $instruction->getInstructionId(),
            'instructionType' => $instruction->getType(),
            'priority' => $priority,
        ]);
    }

    private function handleInstructionSendingFailure(
        string $deviceCode,
        DeviceInstruction $instruction,
        \Exception $e,
    ): never {
        $this->logger->error('发送指令到设备队列失败', [
            'deviceCode' => $deviceCode,
            'instructionId' => $instruction->getInstructionId(),
            'error' => $e->getMessage(),
            'exception' => $e,
        ]);

        $this->dispatchInstructionEvent($deviceCode, $instruction, false, $e->getMessage());

        throw BusinessLogicException::configurationError('发送指令失败: ' . $e->getMessage());
    }

    /**
     * 批量发送指令到多个设备.
     *
     * @param array<string>     $deviceCodes 设备代码列表
     * @param DeviceInstruction $instruction 指令对象
     */
    public function sendInstructionToMultipleDevices(
        array $deviceCodes,
        DeviceInstruction $instruction,
    ): void {
        $successCount = 0;
        $failedDevices = [];

        foreach ($deviceCodes as $deviceCode) {
            try {
                $this->sendInstruction($deviceCode, $instruction);
                ++$successCount;
            } catch (\Exception $e) {
                $failedDevices[] = $deviceCode;
                $this->logger->warning('发送指令到设备失败', [
                    'deviceCode' => $deviceCode,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->logger->info('批量发送指令完成', [
            'totalDevices' => count($deviceCodes),
            'successCount' => $successCount,
            'failedCount' => count($failedDevices),
            'failedDevices' => $failedDevices,
        ]);
    }

    /**
     * 长轮询获取设备指令.
     *
     * @param string $deviceCode 设备代码
     * @param int    $timeout    超时时间（秒）
     *
     * @return array<DeviceInstruction> 指令列表
     */
    public function longPollInstructions(string $deviceCode, int $timeout = 30): array
    {
        $lockKey = sprintf('device_instruction_queue:%s', $deviceCode);

        /** @var array<DeviceInstruction> */
        return $this->lockService->blockingRun($lockKey, function () use ($deviceCode, $timeout): array {
            return $this->executeLongPoll($deviceCode, $timeout);
        });
    }

    /**
     * @return array<DeviceInstruction>
     */
    private function executeLongPoll(string $deviceCode, int $timeout): array
    {
        $queueKey = RedisQueueKeys::getDeviceInstructionQueue($deviceCode);

        // 先检查队列中是否有已存在的指令
        $instructions = $this->getExistingInstructions($queueKey);
        if ([] !== $instructions) {
            return $instructions;
        }

        return $this->waitForNewInstructions($deviceCode, $queueKey, $timeout);
    }

    /**
     * @return array<DeviceInstruction>
     */
    private function waitForNewInstructions(string $deviceCode, string $queueKey, int $timeout): array
    {
        $notifyChannel = RedisQueueKeys::getDevicePollNotify($deviceCode);
        $state = (object) [
            'instructions' => [],
            'endTime' => time() + $timeout,
        ];

        try {
            $this->storage->subscribe([$notifyChannel], function ($redis, $channel, $message) use ($state, $queueKey) {
                return $this->handleSubscriptionMessage($message, $queueKey, $state);
            });
        } catch (\Exception $e) {
            $this->logger->error('长轮询失败', [
                'deviceCode' => $deviceCode,
                'error' => $e->getMessage(),
            ]);
        }

        /** @var array<DeviceInstruction> */
        return $state->instructions;
    }

    /**
     * @param \stdClass $state
     */
    private function handleSubscriptionMessage(
        string $message,
        string $queueKey,
        \stdClass $state,
    ): bool {
        if ('new_instruction' === $message) {
            $instructions = $this->getExistingInstructions($queueKey);
            if ([] !== $instructions) {
                $state->instructions = $instructions;

                return false;
            }
        }

        /** @var int $endTime */
        $endTime = $state->endTime;
        if (time() >= $endTime) {
            return false;
        }

        return true;
    }

    /**
     * 获取队列中的现有指令.
     *
     * @return array<DeviceInstruction>
     */
    private function getExistingInstructions(string $queueKey): array
    {
        $instructions = [];

        // 获取队列中的所有指令
        $queueLength = $this->storage->lLen($queueKey);
        if ($queueLength <= 0) {
            return $instructions;
        }

        for ($i = 0; $i < $queueLength; ++$i) {
            $instructionData = $this->storage->rPop($queueKey);
            if (null === $instructionData) {
                break;
            }

            $instruction = $this->processInstructionData($instructionData);
            if (null !== $instruction) {
                $instructions[] = $instruction;
            }
        }

        return $instructions;
    }

    /**
     * 处理单条指令数据.
     */
    private function processInstructionData(mixed $instructionData): ?DeviceInstruction
    {
        try {
            $instruction = $this->parseInstructionData($instructionData);

            return $this->handleInstructionExpiration($instruction);
        } catch (\Exception $e) {
            $this->logger->error('解析指令数据失败', [
                'error' => $e->getMessage(),
                'data' => $instructionData,
            ]);

            return null;
        }
    }

    private function parseInstructionData(mixed $instructionData): DeviceInstruction
    {
        if (!is_string($instructionData)) {
            throw new InvalidArgumentException('Invalid instruction data: not a string');
        }

        $decodedData = json_decode($instructionData, true);
        if (!is_array($decodedData)) {
            throw new InvalidArgumentException('Invalid instruction data format');
        }

        /** @var array<string, mixed> $decodedData */
        return DeviceInstruction::fromArray($decodedData);
    }

    /**
     * 处理指令过期状态.
     */
    private function handleInstructionExpiration(DeviceInstruction $instruction): ?DeviceInstruction
    {
        if (!$instruction->isExpired()) {
            $this->markInstructionAsExecuting($instruction);

            return $instruction;
        }

        $this->markInstructionAsExpired($instruction);

        return null;
    }

    private function markInstructionAsExecuting(DeviceInstruction $instruction): void
    {
        $this->cacheStorage->updateInstructionStatus($instruction->getInstructionId(), [
            'status' => 'executing',
            'updateTime' => (new \DateTimeImmutable())->format(\DateTimeInterface::RFC3339),
        ]);
    }

    private function markInstructionAsExpired(DeviceInstruction $instruction): void
    {
        $this->cacheStorage->updateInstructionStatus($instruction->getInstructionId(), [
            'status' => 'expired',
            'updateTime' => (new \DateTimeImmutable())->format(\DateTimeInterface::RFC3339),
        ]);
        $this->logger->warning('指令已过期', [
            'instructionId' => $instruction->getInstructionId(),
        ]);
    }

    /**
     * 通知设备有新指令.
     */
    private function notifyDevice(string $deviceCode): void
    {
        $notifyChannel = RedisQueueKeys::getDevicePollNotify($deviceCode);
        $this->storage->publish($notifyChannel, 'new_instruction');
    }

    /**
     * 更新指令执行状态
     *
     * @param string               $instructionId  指令ID
     * @param string               $status         状态
     * @param array<string, mixed> $additionalData 额外数据
     *
     * @deprecated 使用 CacheStorageService::updateInstructionStatus 替代
     */
    public function updateInstructionStatus(
        string $instructionId,
        string $status,
        array $additionalData = [],
    ): void {
        $data = array_merge([
            'status' => $status,
            'updateTime' => (new \DateTimeImmutable())->format(\DateTimeInterface::RFC3339),
        ], $additionalData);

        // 使用 CacheStorageService 更新状态
        $this->cacheStorage->updateInstructionStatus($instructionId, $data);
    }

    /**
     * 获取指令执行状态
     *
     * @param string $instructionId 指令ID
     *
     * @return array<string, mixed>|null 状态信息
     */
    public function getInstructionStatus(string $instructionId): ?array
    {
        return $this->cacheStorage->getInstructionStatus($instructionId);
    }

    /**
     * 取消指令.
     *
     * @param string $deviceCode    设备代码
     * @param string $instructionId 指令ID
     *
     * @return bool 是否成功取消
     */
    public function cancelInstruction(string $deviceCode, string $instructionId): bool
    {
        // 使用设备锁防止并发操作
        $lockKey = sprintf('device_instruction_queue:%s', $deviceCode);

        /** @var bool */
        return $this->lockService->blockingRun($lockKey, function () use ($deviceCode, $instructionId): bool {
            $queueKey = RedisQueueKeys::getDeviceInstructionQueue($deviceCode);
            $queueLength = $this->storage->lLen($queueKey);

            if ($queueLength <= 0) {
                return false;
            }

            $result = $this->processQueueForCancellation($queueKey, $instructionId, $queueLength);
            $this->restoreRemainingInstructions($queueKey, $result['remaining']);

            if ($result['cancelled']) {
                $this->logInstructionCancellation($deviceCode, $instructionId);
            }

            return $result['cancelled'];
        });
    }

    /**
     * @return array{remaining: array<string>, cancelled: bool}
     */
    private function processQueueForCancellation(string $queueKey, string $instructionId, int $queueLength): array
    {
        /** @var array<string> */
        $remainingInstructions = [];
        $cancelled = false;

        for ($i = 0; $i < $queueLength; ++$i) {
            $instructionData = $this->storage->rPop($queueKey);
            if (null === $instructionData) {
                break;
            }

            $result = $this->processCancellationItem($instructionData, $instructionId);
            if ($result['shouldKeep']) {
                $remainingInstructions[] = $instructionData;
            }
            if ($result['wasCancelled']) {
                $cancelled = true;
            }
        }

        return ['remaining' => $remainingInstructions, 'cancelled' => $cancelled];
    }

    /**
     * @return array{shouldKeep: bool, wasCancelled: bool}
     */
    private function processCancellationItem(mixed $instructionData, string $instructionId): array
    {
        if (!is_string($instructionData)) {
            return $this->createCancellationResult(false, false);
        }

        $data = json_decode($instructionData, true);
        if (!is_array($data) || !isset($data['instructionId'])) {
            return $this->createCancellationResult(false, false);
        }

        if ($data['instructionId'] !== $instructionId) {
            return $this->createCancellationResult(true, false);
        }

        $this->markInstructionAsCancelled($instructionId);

        return $this->createCancellationResult(false, true);
    }

    /**
     * @return array{shouldKeep: bool, wasCancelled: bool}
     */
    private function createCancellationResult(bool $shouldKeep, bool $wasCancelled): array
    {
        return ['shouldKeep' => $shouldKeep, 'wasCancelled' => $wasCancelled];
    }

    private function markInstructionAsCancelled(string $instructionId): void
    {
        $this->cacheStorage->updateInstructionStatus($instructionId, [
            'status' => 'cancelled',
            'updateTime' => (new \DateTimeImmutable())->format(\DateTimeInterface::RFC3339),
        ]);
    }

    /**
     * @param string[] $instructions
     */
    private function restoreRemainingInstructions(string $queueKey, array $instructions): void
    {
        foreach ($instructions as $instructionData) {
            $this->storage->lPush($queueKey, $instructionData);
        }
    }

    private function logInstructionCancellation(string $deviceCode, string $instructionId): void
    {
        $this->logger->info('指令已取消', [
            'deviceCode' => $deviceCode,
            'instructionId' => $instructionId,
        ]);
    }

    /**
     * 清空设备的所有待执行指令.
     *
     * @param string $deviceCode 设备代码
     *
     * @return int 清除的指令数量
     */
    public function clearDeviceQueue(string $deviceCode): int
    {
        // 使用设备锁防止并发操作
        $lockKey = sprintf('device_instruction_queue:%s', $deviceCode);

        /** @var int */
        return $this->lockService->blockingRun($lockKey, function () use ($deviceCode): int {
            $queueKey = RedisQueueKeys::getDeviceInstructionQueue($deviceCode);
            $queueLength = $this->storage->lLen($queueKey);

            if ($queueLength <= 0) {
                return 0;
            }

            $this->clearAllInstructionsInQueue($queueKey, $queueLength);
            $this->logQueueCleared($deviceCode, $queueLength);

            return $queueLength;
        });
    }

    /**
     * 清空队列中的所有指令并更新状态.
     */
    private function clearAllInstructionsInQueue(string $queueKey, int $queueLength): void
    {
        for ($i = 0; $i < $queueLength; ++$i) {
            $instructionData = $this->storage->rPop($queueKey);
            if (null === $instructionData) {
                break;
            }

            $this->markInstructionAsCleared($instructionData);
        }
    }

    /**
     * 标记指令为已清空.
     *
     * @param mixed $instructionData
     */
    private function markInstructionAsCleared($instructionData): void
    {
        if (!is_string($instructionData)) {
            return;
        }

        try {
            $data = json_decode($instructionData, true);
            if (!is_array($data) || !isset($data['instructionId']) || !is_string($data['instructionId'])) {
                return;
            }

            $this->cacheStorage->updateInstructionStatus($data['instructionId'], [
                'status' => 'cleared',
                'updateTime' => (new \DateTimeImmutable())->format(\DateTimeInterface::RFC3339),
            ]);
        } catch (\Exception $e) {
            // 忽略解析错误
        }
    }

    private function logQueueCleared(string $deviceCode, int $clearedCount): void
    {
        $this->logger->info('设备队列已清空', [
            'deviceCode' => $deviceCode,
            'clearedCount' => $clearedCount,
        ]);
    }

    /**
     * 获取设备队列长度.
     *
     * @param string $deviceCode 设备代码
     *
     * @return int 队列长度
     */
    public function getQueueLength(string $deviceCode): int
    {
        $queueKey = RedisQueueKeys::getDeviceInstructionQueue($deviceCode);

        return $this->storage->lLen($queueKey);
    }

    /**
     * 获取设备队列中的指令预览（不移除）.
     *
     * @param string $deviceCode 设备代码
     * @param int    $limit      限制数量
     *
     * @return array<array<string, mixed>> 指令列表
     */
    public function previewQueue(string $deviceCode, int $limit = 10): array
    {
        $queueKey = RedisQueueKeys::getDeviceInstructionQueue($deviceCode);
        $rawInstructions = $this->storage->lRange($queueKey, 0, $limit - 1);

        return $this->parsePreviewInstructions($rawInstructions);
    }

    /**
     * @param array<mixed> $rawInstructions
     *
     * @return array<array<string, mixed>>
     */
    private function parsePreviewInstructions(array $rawInstructions): array
    {
        $instructions = [];

        foreach ($rawInstructions as $instructionData) {
            $instruction = $this->tryParsePreviewInstruction($instructionData);
            if (null !== $instruction) {
                $instructions[] = $instruction;
            }
        }

        return $instructions;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function tryParsePreviewInstruction(mixed $instructionData): ?array
    {
        try {
            $instruction = $this->parseInstructionData($instructionData);

            return $instruction->toArray();
        } catch (\Exception $e) {
            $this->logger->warning('解析队列中的指令失败', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * 触发指令发送事件（已有设备对象版本）.
     */
    private function dispatchInstructionEventWithDevice(
        AutoJsDevice $device,
        DeviceInstruction $instruction,
        bool $success,
        ?string $errorMessage,
        string $deviceCode,
    ): void {
        try {
            $event = $this->createInstructionEvent($instruction, $device, $success, $errorMessage, $deviceCode);
            $this->eventDispatcher->dispatch($event);
        } catch (\Exception $e) {
            $this->logEventDispatchError($deviceCode, $instruction, $e);
        }
    }

    /**
     * 触发指令发送事件（需要查找设备版本）.
     */
    private function dispatchInstructionEvent(
        string $deviceCode,
        DeviceInstruction $instruction,
        bool $success,
        ?string $errorMessage = null,
    ): void {
        try {
            $device = $this->deviceRepository->findOneByDeviceCode($deviceCode);
            if (null === $device) {
                return;
            }

            $this->dispatchInstructionEventWithDevice($device, $instruction, $success, $errorMessage, $deviceCode);
        } catch (\Exception $e) {
            $this->logEventDispatchError($deviceCode, $instruction, $e);
        }
    }

    private function createInstructionEvent(
        DeviceInstruction $instruction,
        AutoJsDevice $device,
        bool $success,
        ?string $errorMessage,
        string $deviceCode,
    ): InstructionSentEvent {
        return new InstructionSentEvent(
            $instruction,
            $device,
            $success,
            $errorMessage,
            [
                'queueLength' => $this->getQueueLength($deviceCode),
                'timestamp' => time(),
            ]
        );
    }

    private function logEventDispatchError(string $deviceCode, DeviceInstruction $instruction, \Exception $e): void
    {
        $this->logger->error('触发指令发送事件失败', [
            'deviceCode' => $deviceCode,
            'instructionId' => $instruction->getInstructionId(),
            'error' => $e->getMessage(),
        ]);
    }
}
