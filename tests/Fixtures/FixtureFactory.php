<?php

declare(strict_types=1);

namespace Tourze\AutoJsControlBundle\Tests\Fixtures;

use DeviceBundle\Entity\Device as BaseDevice;
use DeviceBundle\Enum\DeviceStatus;
use DeviceBundle\Enum\DeviceType;
use Tourze\AutoJsControlBundle\Entity\AutoJsDevice;
use Tourze\AutoJsControlBundle\Entity\DeviceLog;
use Tourze\AutoJsControlBundle\Entity\DeviceMonitorData;
use Tourze\AutoJsControlBundle\Entity\Script;
use Tourze\AutoJsControlBundle\Entity\ScriptExecutionRecord;
use Tourze\AutoJsControlBundle\Entity\Task;
use Tourze\AutoJsControlBundle\Enum\ExecutionStatus;
use Tourze\AutoJsControlBundle\Enum\LogLevel;
use Tourze\AutoJsControlBundle\Enum\LogType;
use Tourze\AutoJsControlBundle\Enum\ScriptType;
use Tourze\AutoJsControlBundle\Enum\TaskStatus;
use Tourze\AutoJsControlBundle\ValueObject\DeviceInstruction;
use Tourze\AutoJsControlBundle\ValueObject\InstructionType;

/**
 * 测试数据工厂类 - Linus优化版本.
 *
 * 设计原则：
 * 1. 批量创建避免N+1问题
 * 2. 最小化对象关系复杂度
 * 3. 支持一次性创建完整的数据集合
 */
final class FixtureFactory
{
    /**
     * 创建基础设备实体
     * Linus修复：所有默认值必须唯一，消除数据库约束冲突
     */
    public static function createBaseDevice(array $data = []): BaseDevice
    {
        $uuid = uniqid('', true); // 确保绝对唯一性

        $device = new BaseDevice();
        $device->setCode($data['code'] ?? 'TEST-DEVICE-' . $uuid);
        $device->setName($data['name'] ?? 'Test Device ' . $uuid);
        $device->setDeviceType($data['deviceType'] ?? DeviceType::PHONE);
        $device->setStatus($data['status'] ?? DeviceStatus::ONLINE);
        $device->setModel($data['model'] ?? 'Test Model');
        $device->setBrand($data['brand'] ?? 'Test Brand');
        $device->setOsVersion($data['osVersion'] ?? 'Android 12');
        $device->setFingerprint($data['fingerprint'] ?? 'test-fingerprint-' . $uuid);
        $device->setLastOnlineTime($data['lastOnlineTime'] ?? new \DateTimeImmutable());
        $device->setLastIp($data['lastIp'] ?? '192.168.1.100');
        $device->setCpuCores($data['cpuCores'] ?? 8);
        $device->setMemorySize($data['memorySize'] ?? '8GB');
        $device->setStorageSize($data['storageSize'] ?? '128GB');

        return $device;
    }

    /**
     * 创建 Auto.js 设备实体.
     */
    public static function createAutoJsDevice(array $data = []): AutoJsDevice
    {
        $baseDevice = $data['baseDevice'] ?? self::createBaseDevice();

        $device = new AutoJsDevice();
        $device->setBaseDevice($baseDevice);
        $device->setCertificate($data['certificate'] ?? 'test-certificate-hash');
        $device->setAutoJsVersion($data['autoJsVersion'] ?? '9.0.0');
        $device->setCreateTime($data['createTime'] ?? new \DateTimeImmutable());
        $device->setUpdateTime($data['updateTime'] ?? new \DateTimeImmutable());

        return $device;
    }

    /**
     * 创建脚本实体
     * Linus修复：脚本code也必须唯一
     */
    public static function createScript(array $data = []): Script
    {
        $uuid = uniqid('', true); // 确保绝对唯一性
        $script = new Script();

        // 如果提供了ID，使用反射设置
        if (isset($data['id'])) {
            $reflection = new \ReflectionClass($script);
            $idProperty = $reflection->getProperty('id');
            $idProperty->setAccessible(true);
            $idProperty->setValue($script, $data['id']);
        }

        $script->setCode($data['code'] ?? 'TEST-SCRIPT-' . $uuid);
        $script->setName($data['name'] ?? 'Test Script ' . $uuid);
        $script->setDescription($data['description'] ?? 'Test script description');
        $script->setScriptType($data['scriptType'] ?? ScriptType::JAVASCRIPT);
        $script->setContent($data['content'] ?? 'console.log("Hello, World!");');
        $script->setVersion($data['version'] ?? 1);
        $script->setParameters($data['parameters'] ?? '{}');
        $script->setTimeout($data['timeout'] ?? 300);
        $script->setValid($data['valid'] ?? true);
        $script->setCreateTime($data['createTime'] ?? new \DateTimeImmutable());
        $script->setUpdateTime($data['updateTime'] ?? new \DateTimeImmutable());

        return $script;
    }

    /**
     * 创建任务实体
     * Linus修复：任务名称和目标设备ID也要唯一
     */
    public static function createTask(array $data = []): Task
    {
        $uuid = uniqid('', true); // 确保绝对唯一性
        $script = $data['script'] ?? self::createScript();

        $task = new Task();
        $task->setName($data['name'] ?? 'Test Task ' . $uuid);
        $task->setDescription($data['description'] ?? 'Test task description');
        $task->setScript($script);
        $task->setStatus($data['status'] ?? TaskStatus::PENDING);
        $task->setScheduledTime($data['scheduledTime'] ?? new \DateTimeImmutable('+1 hour'));
        $task->setTargetDeviceIds($data['targetDeviceIds'] ?? json_encode(['TEST-DEVICE-' . $uuid]));
        $task->setParameters($data['parameters'] ?? json_encode([]));
        $task->setPriority($data['priority'] ?? 5);
        $task->setMaxRetries($data['maxRetries'] ?? 3);
        $task->setRetryCount($data['retryCount'] ?? 0);
        $task->setValid($data['valid'] ?? true);

        return $task;
    }

    /**
     * 创建脚本执行记录实体
     * Linus修复：指令ID也必须唯一
     */
    public static function createScriptExecutionRecord(array $data = []): ScriptExecutionRecord
    {
        $uuid = uniqid('', true); // 确保绝对唯一性
        $autoJsDevice = $data['autoJsDevice'] ?? self::createAutoJsDevice();
        $script = $data['script'] ?? self::createScript();
        $task = $data['task'] ?? self::createTask();

        $record = new ScriptExecutionRecord();
        $record->setAutoJsDevice($autoJsDevice);
        $record->setScript($script);
        $record->setTask($task);
        $record->setInstructionId($data['instructionId'] ?? 'test-instruction-id-' . $uuid);
        $record->setStatus($data['status'] ?? ExecutionStatus::PENDING);
        $record->setStartTime($data['startTime'] ?? null);
        $record->setEndTime($data['endTime'] ?? null);
        $record->setOutput($data['output'] ?? null);
        $record->setErrorMessage($data['errorMessage'] ?? null);
        $record->setExecutionMetrics($data['executionMetrics'] ?? []);
        $record->setScreenshots($data['screenshots'] ?? []);

        return $record;
    }

    /**
     * 创建设备日志实体.
     */
    public static function createDeviceLog(array $data = []): DeviceLog
    {
        $autoJsDevice = $data['autoJsDevice'] ?? self::createAutoJsDevice();

        $log = new DeviceLog();
        $log->setAutoJsDevice($autoJsDevice);
        $log->setLevel($data['level'] ?? LogLevel::INFO);
        $log->setLogType($data['logType'] ?? LogType::SYSTEM);
        $log->setMessage($data['message'] ?? 'Test log message');
        $log->setLogTime($data['logTime'] ?? new \DateTimeImmutable());
        $log->setContext($data['context'] ?? null);
        $log->setStackTrace($data['stackTrace'] ?? null);
        $log->setCreateTime($data['createTime'] ?? new \DateTimeImmutable());

        return $log;
    }

    /**
     * 创建设备监控数据实体.
     */
    public static function createDeviceMonitorData(array $data = []): DeviceMonitorData
    {
        $autoJsDevice = $data['autoJsDevice'] ?? self::createAutoJsDevice();

        $monitorData = new DeviceMonitorData();
        $monitorData->setAutoJsDevice($autoJsDevice);
        $monitorData->setMonitorTime($data['monitorTime'] ?? new \DateTimeImmutable());
        $monitorData->setCpuUsage($data['cpuUsage'] ?? 50.5);
        $monitorData->setMemoryUsage($data['memoryUsage'] ?? 60.3);
        $monitorData->setAvailableStorage($data['availableStorage'] ?? 50000);
        $monitorData->setBatteryLevel($data['batteryLevel'] ?? 85);
        $monitorData->setNetworkType($data['networkType'] ?? 'WIFI');
        $monitorData->setAdditionalData($data['additionalData'] ?? []);
        $monitorData->setCreateTime($data['createTime'] ?? new \DateTimeImmutable());

        return $monitorData;
    }

    /**
     * 创建设备指令值对象
     * Linus修复：指令ID唯一性.
     */
    public static function createDeviceInstruction(array $data = []): DeviceInstruction
    {
        $uuid = uniqid('', true); // 确保绝对唯一性

        return new DeviceInstruction(
            instructionId: $data['instructionId'] ?? 'test-instruction-id-' . $uuid,
            type: $data['type'] ?? InstructionType::EXECUTE_SCRIPT->value,
            data: $data['data'] ?? ['scriptId' => 1],
            timeout: $data['timeout'] ?? 300,
            priority: $data['priority'] ?? 5,
            taskId: $data['taskId'] ?? null,
            scriptId: $data['scriptId'] ?? null,
            correlationId: $data['correlationId'] ?? null
        );
    }

    /**
     * 创建设备信息数组（用于API请求）.
     */
    public static function createDeviceInfo(array $data = []): array
    {
        return [
            'cpuCores' => $data['cpuCores'] ?? 8,
            'memorySize' => $data['memorySize'] ?? '8GB',
            'storageSize' => $data['storageSize'] ?? '128GB',
            'screenResolution' => $data['screenResolution'] ?? '1080x2400',
            'androidId' => $data['androidId'] ?? 'test-android-id',
            'imei' => $data['imei'] ?? 'test-imei',
        ];
    }

    /**
     * 创建监控数据数组（用于API请求）.
     */
    public static function createMonitorDataArray(array $data = []): array
    {
        return [
            'cpuUsage' => $data['cpuUsage'] ?? 50.5,
            'memoryUsage' => $data['memoryUsage'] ?? 60.3,
            'availableStorage' => $data['availableStorage'] ?? 50000,
            'batteryLevel' => $data['batteryLevel'] ?? 85,
            'networkType' => $data['networkType'] ?? 'WIFI',
            'isCharging' => $data['isCharging'] ?? false,
            'temperature' => $data['temperature'] ?? 35.5,
        ];
    }

    /**
     * 创建执行指标数组（用于API请求）.
     */
    public static function createExecutionMetrics(array $data = []): array
    {
        return [
            'executionTime' => $data['executionTime'] ?? 1234,
            'memoryUsed' => $data['memoryUsed'] ?? 1024000,
            'cpuTime' => $data['cpuTime'] ?? 1000,
            'networkRequests' => $data['networkRequests'] ?? 5,
            'errorCount' => $data['errorCount'] ?? 0,
        ];
    }

    /**
     * Linus优化：批量创建完整的测试数据集.
     *
     * 一次性创建所有相关实体，避免重复的persist操作
     * 这就是"好品味" - 把复杂的创建过程简化为一个方法调用
     */
    public static function createCompleteDataSet(array $config = []): array
    {
        $deviceCount = $config['deviceCount'] ?? 2;
        $scriptCount = $config['scriptCount'] ?? 2;
        $taskCount = $config['taskCount'] ?? 2;
        $recordCount = $config['recordCount'] ?? 3;

        $devices = [];
        $scripts = [];
        $tasks = [];
        $records = [];

        $uuid = uniqid('', true); // 使用uniqid确保绝对唯一性

        // 批量创建设备
        for ($i = 1; $i <= $deviceCount; ++$i) {
            $devices[] = self::createAutoJsDevice([
                'code' => 'BATCH_DEV_' . $uuid . '_' . $i,
            ]);
        }

        // 批量创建脚本
        for ($i = 1; $i <= $scriptCount; ++$i) {
            $scripts[] = self::createScript([
                'code' => 'BATCH_SCRIPT_' . $uuid . '_' . $i,
            ]);
        }

        // 批量创建任务
        for ($i = 1; $i <= $taskCount; ++$i) {
            $tasks[] = self::createTask([
                'script' => $scripts[($i - 1) % count($scripts)], // 循环使用脚本
                'name' => 'Batch Task ' . str_pad((string) $i, 3, '0', STR_PAD_LEFT),
            ]);
        }

        // 批量创建执行记录
        for ($i = 1; $i <= $recordCount; ++$i) {
            $records[] = self::createScriptExecutionRecord([
                'autoJsDevice' => $devices[($i - 1) % count($devices)], // 循环使用设备
                'task' => $tasks[($i - 1) % count($tasks)], // 循环使用任务
                'script' => $tasks[($i - 1) % count($tasks)]->getScript(),
                'status' => [ExecutionStatus::SUCCESS, ExecutionStatus::FAILED, ExecutionStatus::RUNNING][$i % 3],
            ]);
        }

        return [
            'devices' => $devices,
            'scripts' => $scripts,
            'tasks' => $tasks,
            'records' => $records,
            'all' => array_merge($devices, $scripts, $tasks, $records),
        ];
    }

    /**
     * Linus优化：创建最小化的测试实体.
     *
     * 当你只需要测试基本功能时，不要创建复杂的对象关系
     */
    public static function createMinimalScriptExecutionRecord(array $data = []): ScriptExecutionRecord
    {
        $uuid = uniqid('', true);

        // 最简单的依赖对象
        $device = self::createAutoJsDevice(['code' => 'MIN_DEV_' . $uuid]);
        $script = self::createScript(['code' => 'MIN_SCRIPT_' . $uuid]);
        $task = self::createTask(['script' => $script, 'name' => 'Minimal Task ' . $uuid]);

        $record = new ScriptExecutionRecord();
        $record->setAutoJsDevice($device);
        $record->setScript($script);
        $record->setTask($task);
        $record->setInstructionId($data['instructionId'] ?? 'min-instruction-id-' . $uuid);
        $record->setStatus($data['status'] ?? ExecutionStatus::SUCCESS);
        $record->setStartTime($data['startTime'] ?? new \DateTimeImmutable());

        return $record;
    }
}
