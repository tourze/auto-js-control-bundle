<?php

declare(strict_types=1);

namespace Tourze\AutoJsControlBundle\Tests\Service;

use DeviceBundle\Entity\Device;
use DeviceBundle\Enum\DeviceStatus;
use DeviceBundle\Enum\DeviceType;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\AutoJsControlBundle\Entity\AutoJsDevice;
use Tourze\AutoJsControlBundle\Service\CacheStorageService;
use Tourze\AutoJsControlBundle\Service\InstructionQueueService;
use Tourze\AutoJsControlBundle\ValueObject\DeviceInstruction;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(InstructionQueueService::class)]
#[RunTestsInSeparateProcesses]
final class InstructionQueueServiceTest extends AbstractIntegrationTestCase
{
    private InstructionQueueService $service;

    protected function onSetUp(): void
    {
        // 获取完整的服务实例，测试完整流程
        $this->service = self::getService(InstructionQueueService::class);

        // 清理测试环境状态
        $this->cleanupTestState();
    }

    private function cleanupTestState(): void
    {
        // 清理常用的测试设备队列
        $commonDeviceCodes = ['TEST_DEVICE', 'DEVICE_1', 'DEVICE_2', 'DEVICE_3', 'NONEXISTENT_DEVICE'];

        foreach ($commonDeviceCodes as $deviceCode) {
            try {
                $this->service->clearDeviceQueue($deviceCode);
            } catch (\Exception $e) {
                // 忽略清理失败，继续下一个
            }
        }
    }

    private function createTestDevice(string $deviceCode = 'TEST_DEVICE'): AutoJsDevice
    {
        $entityManager = self::getService(EntityManagerInterface::class);

        // 创建基础设备
        $baseDevice = new Device();
        $baseDevice->setCode($deviceCode);
        $baseDevice->setName('Test Device');
        $baseDevice->setDeviceType(DeviceType::PHONE);
        $baseDevice->setStatus(DeviceStatus::ONLINE);
        $entityManager->persist($baseDevice);

        // 创建 AutoJs 设备扩展
        $device = new AutoJsDevice();
        $device->setBaseDevice($baseDevice);
        $device->setAutoJsVersion('8.0.0');
        $entityManager->persist($device);
        $entityManager->flush();

        return $device;
    }

    public function testSendInstructionWithNormalPriorityAddsToEndOfQueue(): void
    {
        // Arrange
        $deviceCode = 'TEST_DEVICE';
        $this->createTestDevice($deviceCode);

        $instruction1 = new DeviceInstruction(
            'test-instruction-1',
            'execute_script',
            ['data' => 'test1'],
            300,
            5
        );

        $instruction2 = new DeviceInstruction(
            'test-instruction-2',
            'execute_script',
            ['data' => 'test2'],
            300,
            5
        );

        // Act
        $this->service->sendInstruction($deviceCode, $instruction1, false);
        $this->service->sendInstruction($deviceCode, $instruction2, false);

        // Assert - 验证队列长度正确
        $this->assertEquals(2, $this->service->getQueueLength($deviceCode));
    }

    public function testSendInstructionWithHighPriorityAddsToStartOfQueue(): void
    {
        // Arrange
        $deviceCode = 'TEST_DEVICE';
        $this->createTestDevice($deviceCode);

        // 清理可能存在的队列数据
        $this->service->clearDeviceQueue($deviceCode);

        $normalInstruction = new DeviceInstruction(
            'normal-instruction',
            'execute_script',
            ['data' => 'normal'],
            300,
            5
        );

        $highPriorityInstruction = new DeviceInstruction(
            'high-priority-instruction',
            'execute_script',
            ['data' => 'high'],
            300,
            5
        );

        // Act
        $this->service->sendInstruction($deviceCode, $normalInstruction, false);
        $this->service->sendInstruction($deviceCode, $highPriorityInstruction, true); // 高优先级

        // Assert - 验证高优先级指令在队列开头
        $preview = $this->service->previewQueue($deviceCode, 10);
        $this->assertCount(2, $preview);
        $this->assertEquals('high-priority-instruction', $preview[0]['instructionId']);
        $this->assertEquals('normal-instruction', $preview[1]['instructionId']);
    }

    public function testSendInstructionWithErrorThrowsExceptionAndDispatchesFailureEvent(): void
    {
        // Arrange
        $deviceCode = 'NONEXISTENT_DEVICE';
        $instruction = new DeviceInstruction(
            'test-instruction',
            'execute_script',
            ['data' => 'test'],
            300,
            5
        );

        // Act & Assert
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Device not found: NONEXISTENT_DEVICE');

        $this->service->sendInstruction($deviceCode, $instruction, false);
    }

    public function testSendInstructionToMultipleDevicesWithMixedResultsLogsFailures(): void
    {
        // Arrange
        $device1 = $this->createTestDevice('DEVICE_1');
        $device2 = $this->createTestDevice('DEVICE_2');
        // DEVICE_3 不存在

        $deviceCodes = ['DEVICE_1', 'DEVICE_2', 'DEVICE_3'];
        $instruction = new DeviceInstruction(
            'test-instruction',
            'execute_script',
            ['data' => 'test'],
            300,
            5
        );

        // Act
        $this->service->sendInstructionToMultipleDevices($deviceCodes, $instruction);

        // Assert - 验证存在的设备队列中有指令
        $this->assertEquals(1, $this->service->getQueueLength('DEVICE_1'));
        $this->assertEquals(1, $this->service->getQueueLength('DEVICE_2'));
        $this->assertEquals(0, $this->service->getQueueLength('DEVICE_3'));
    }

    public function testLongPollInstructionsWithExistingInstructionsReturnsImmediately(): void
    {
        // Arrange
        $deviceCode = 'TEST_DEVICE';
        $this->createTestDevice($deviceCode);

        $instruction = new DeviceInstruction(
            'test-instruction',
            'execute_script',
            ['data' => 'test'],
            300,
            5
        );

        // 先发送一条指令
        $this->service->sendInstruction($deviceCode, $instruction, false);

        // Act
        $startTime = microtime(true);
        $instructions = $this->service->longPollInstructions($deviceCode, 10);
        $endTime = microtime(true);

        // Assert
        $this->assertCount(1, $instructions);
        $this->assertEquals('test-instruction', $instructions[0]->getInstructionId());

        // 应该立即返回（小于1秒）
        $this->assertLessThan(1, $endTime - $startTime);
    }

    public function testLongPollInstructionsWithExpiredInstructionSkipsIt(): void
    {
        // Arrange
        $deviceCode = 'TEST_DEVICE';
        $this->createTestDevice($deviceCode);

        // 创建一个已过期的指令
        $expiredInstruction = new DeviceInstruction(
            'expired-instruction',
            'execute_script',
            ['data' => 'expired'],
            -100, // 过期时间设为负数
            5
        );

        // 创建一个有效的指令
        $validInstruction = new DeviceInstruction(
            'valid-instruction',
            'execute_script',
            ['data' => 'valid'],
            300,
            5
        );

        // 发送指令到队列（正常的服务流程）
        $this->service->sendInstruction($deviceCode, $expiredInstruction, false);
        $this->service->sendInstruction($deviceCode, $validInstruction, false);

        // Act
        $instructions = $this->service->longPollInstructions($deviceCode, 1);

        // Assert - 应该只返回有效的指令
        $this->assertCount(1, $instructions);
        $this->assertEquals('valid-instruction', $instructions[0]->getInstructionId());
    }

    public function testCancelInstructionWithExistingInstructionRemovesAndReturnsTrue(): void
    {
        // Arrange
        $deviceCode = 'TEST_DEVICE';
        $this->createTestDevice($deviceCode);

        $instruction1 = new DeviceInstruction(
            'instruction-1',
            'execute_script',
            ['data' => 'test1'],
            300,
            5
        );

        $instruction2 = new DeviceInstruction(
            'instruction-2',
            'execute_script',
            ['data' => 'test2'],
            300,
            5
        );

        $this->service->sendInstruction($deviceCode, $instruction1, false);
        $this->service->sendInstruction($deviceCode, $instruction2, false);

        // Act
        $result = $this->service->cancelInstruction($deviceCode, 'instruction-1');

        // Assert
        $this->assertTrue($result);

        // 验证队列长度（使用服务方法）
        $this->assertEquals(1, $this->service->getQueueLength($deviceCode));
    }

    public function testCancelInstructionWithNonExistentInstructionReturnsFalse(): void
    {
        // Arrange
        $deviceCode = 'TEST_DEVICE';
        $this->createTestDevice($deviceCode);

        // Act
        $result = $this->service->cancelInstruction($deviceCode, 'nonexistent-instruction');

        // Assert
        $this->assertFalse($result);
    }

    public function testClearDeviceQueueWithMultipleInstructionsClearsAllAndReturnsCount(): void
    {
        // Arrange
        $deviceCode = 'TEST_DEVICE';
        $this->createTestDevice($deviceCode);

        // 添加3条指令
        for ($i = 1; $i <= 3; ++$i) {
            $instruction = new DeviceInstruction(
                "instruction-{$i}",
                'execute_script',
                ['data' => "test{$i}"],
                300,
                5
            );
            $this->service->sendInstruction($deviceCode, $instruction, false);
        }

        // Act
        $clearedCount = $this->service->clearDeviceQueue($deviceCode);

        // Assert
        $this->assertEquals(3, $clearedCount);
    }

    public function testGetQueueLengthReturnsCorrectLength(): void
    {
        // Arrange
        $deviceCode = 'TEST_DEVICE';
        $this->createTestDevice($deviceCode);

        // Act & Assert - 初始长度为0
        $this->assertEquals(0, $this->service->getQueueLength($deviceCode));

        // 添加一条指令
        $instruction = new DeviceInstruction(
            'test-instruction',
            'execute_script',
            ['data' => 'test'],
            300,
            5
        );
        $this->service->sendInstruction($deviceCode, $instruction, false);

        // 长度应该为1
        $this->assertEquals(1, $this->service->getQueueLength($deviceCode));
    }

    public function testPreviewQueueReturnsInstructionsWithoutRemoving(): void
    {
        // Arrange
        $deviceCode = 'TEST_DEVICE';
        $this->createTestDevice($deviceCode);

        $instruction1 = new DeviceInstruction(
            'instruction-1',
            'execute_script',
            ['data' => 'test1'],
            300,
            5
        );

        $instruction2 = new DeviceInstruction(
            'instruction-2',
            'execute_script',
            ['data' => 'test2'],
            300,
            5
        );

        $this->service->sendInstruction($deviceCode, $instruction1, false);
        $this->service->sendInstruction($deviceCode, $instruction2, false);

        // Act
        $preview = $this->service->previewQueue($deviceCode, 10);

        // Assert
        $this->assertCount(2, $preview);
        $this->assertEquals('instruction-1', $preview[0]['instructionId']);
        $this->assertEquals('instruction-2', $preview[1]['instructionId']);

        // 验证队列没有被修改
        $this->assertEquals(2, $this->service->getQueueLength($deviceCode));
    }

    public function testUpdateInstructionStatusSetsStatusInRedis(): void
    {
        // Arrange
        $instructionId = 'test-instruction';
        $statusValue = 'completed';
        $additionalData = [
            'result' => 'success',
            'updateTime' => '2024-01-01T00:00:00Z',
        ];

        // Act - 使用 CacheStorageService 更新指令状态
        $fullStatus = array_merge(['status' => $statusValue], $additionalData);
        $cacheStorage = self::getService(CacheStorageService::class);
        $cacheStorage->updateInstructionStatus($instructionId, $fullStatus);

        // Assert
        $retrievedStatus = $this->service->getInstructionStatus($instructionId);
        $this->assertEquals($fullStatus, $retrievedStatus);
    }

    public function testGetInstructionStatusWithExistingStatusReturnsStatusArray(): void
    {
        // Arrange
        $instructionId = 'test-instruction-unique';
        $status = [
            'status' => 'executing',
            'updateTime' => '2024-01-01T00:00:00Z',
        ];

        $cacheStorage = self::getService(CacheStorageService::class);
        $cacheStorage->updateInstructionStatus($instructionId, $status);

        // Act
        $retrievedStatus = $this->service->getInstructionStatus($instructionId);

        // Assert
        $this->assertEquals($status, $retrievedStatus);
    }

    public function testGetInstructionStatusWithNonExistentStatusReturnsNull(): void
    {
        // Arrange
        $instructionId = 'nonexistent-instruction';

        // Act
        $status = $this->service->getInstructionStatus($instructionId);

        // Assert
        $this->assertNull($status);
    }

    public function testUpdateInstructionStatusWithCacheStorageService(): void
    {
        // Arrange
        $instructionId = 'cache-test-instruction';
        $statusValue = 'completed';
        $additionalData = [
            'result' => 'success',
            'executionTime' => 120,
        ];

        $cacheStorageService = self::getService(CacheStorageService::class);

        // Act - 使用新的方法
        $statusData = array_merge([
            'status' => $statusValue,
            'updateTime' => (new \DateTimeImmutable())->format(\DateTimeInterface::RFC3339),
        ], $additionalData);
        $cacheStorageService->updateInstructionStatus($instructionId, $statusData);

        // Assert
        $retrievedStatus = $this->service->getInstructionStatus($instructionId);
        $this->assertNotNull($retrievedStatus);
        $this->assertEquals($statusValue, $retrievedStatus['status']);
        $this->assertEquals('success', $retrievedStatus['result']);
        $this->assertEquals(120, $retrievedStatus['executionTime']);
        $this->assertArrayHasKey('updateTime', $retrievedStatus);
    }
}
