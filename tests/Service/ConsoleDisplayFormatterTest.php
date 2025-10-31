<?php

declare(strict_types=1);

namespace Tourze\AutoJsControlBundle\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\ConsoleSectionOutput;
use Symfony\Component\Console\Output\StreamOutput;
use Symfony\Component\Console\Style\SymfonyStyle;
use Tourze\AutoJsControlBundle\Service\ConsoleDisplayFormatter;

/**
 * @internal
 */
#[CoversClass(ConsoleDisplayFormatter::class)]
final class ConsoleDisplayFormatterTest extends TestCase
{
    private ConsoleDisplayFormatter $formatter;

    private SymfonyStyle $io;

    private StreamOutput $output;

    protected function setUp(): void
    {
        $this->formatter = new ConsoleDisplayFormatter();

        // Create test output stream
        $stream = fopen('php://memory', 'r+', false);
        if (false === $stream) {
            self::fail('Failed to open memory stream');
        }
        $this->output = new StreamOutput($stream);

        // Create SymfonyStyle with test input/output
        $input = new ArrayInput([]);
        $this->io = new SymfonyStyle($input, $this->output);
    }

    protected function onTearDown(): void
    {
        if (isset($this->output) && is_resource($this->output->getStream())) {
            fclose($this->output->getStream());
        }
    }

    public function testDisplayDeviceInfo(): void
    {
        // Arrange
        $device = $this->createMockDevice('测试设备');
        $deviceCode = 'DEVICE_001';
        $isOnline = true;
        $metrics = [
            'queueLength' => 5,
            'cpuUsage' => 45.5,
            'memoryUsage' => 60.2,
        ];

        // Act
        $this->formatter->displayDeviceInfo($device, $deviceCode, $isOnline, $metrics, $this->io);

        // Assert
        $output = $this->getOutput();
        $this->assertStringContainsString('测试设备', $output);
        $this->assertStringContainsString('在线', $output);
        $this->assertStringContainsString('5', $output);
        $this->assertStringContainsString('45.5%', $output);
        $this->assertStringContainsString('60.2%', $output);
    }

    public function testDisplayDeviceInfoOffline(): void
    {
        // Arrange
        $device = $this->createMockDevice('离线设备');
        $deviceCode = 'DEVICE_002';
        $isOnline = false;
        $metrics = ['queueLength' => 0];

        // Act
        $this->formatter->displayDeviceInfo($device, $deviceCode, $isOnline, $metrics, $this->io);

        // Assert
        $output = $this->getOutput();
        $this->assertStringContainsString('离线设备', $output);
        $this->assertStringContainsString('离线', $output);
    }

    public function testDisplayDeviceInfoWithMissingMetrics(): void
    {
        // Arrange
        $device = $this->createMockDevice('设备');
        $deviceCode = 'DEVICE_003';
        $isOnline = true;
        $metrics = ['queueLength' => 2];

        // Act
        $this->formatter->displayDeviceInfo($device, $deviceCode, $isOnline, $metrics, $this->io);

        // Assert
        $output = $this->getOutput();
        $this->assertStringContainsString('N/A', $output); // CPU and memory should show N/A
    }

    public function testDisplayDevicesListTable(): void
    {
        // Arrange
        $devices = [
            [
                'id' => 1,
                'code' => 'DEV001',
                'name' => '设备1',
                'statusDisplay' => '<fg=green>在线</>',
                'queueLength' => 3,
                'queueStatusDisplay' => '正常',
            ],
            [
                'id' => 2,
                'code' => 'DEV002',
                'name' => '设备2',
                'statusDisplay' => '<fg=red>离线</>',
                'queueLength' => 0,
                'queueStatusDisplay' => '空闲',
            ],
        ];

        // Act
        $this->formatter->displayDevicesListTable($devices, $this->io);

        // Assert
        $output = $this->getOutput();
        $this->assertStringContainsString('设备队列状态', $output);
        $this->assertStringContainsString('DEV001', $output);
        $this->assertStringContainsString('设备1', $output);
        $this->assertStringContainsString('DEV002', $output);
        $this->assertStringContainsString('设备2', $output);
    }

    public function testDisplayDevicesSummaryTable(): void
    {
        // Arrange
        $stats = [
            'devices' => [],
            'totalQueueLength' => 15,
            'onlineCount' => 8,
            'totalCount' => 10,
        ];

        // Act
        $this->formatter->displayDevicesSummaryTable($stats, $this->io);

        // Assert
        $output = $this->getOutput();
        $this->assertStringContainsString('队列汇总', $output);
        $this->assertStringContainsString('10', $output); // Total devices
        $this->assertStringContainsString('8', $output); // Online devices
        $this->assertStringContainsString('2', $output); // Offline devices (10-8)
        $this->assertStringContainsString('15', $output); // Total queue length
    }

    public function testDisplayInstructions(): void
    {
        // Arrange
        $instructions = [
            [
                'instructionId' => 'INST001',
                'type' => 'execute_script',
                'priority' => 1,
                'createdTime' => '2024-01-15 10:30:45',
                'data' => ['scriptId' => 123],
            ],
            [
                'instructionId' => 'INST002',
                'type' => 'update_config',
                'priority' => 2,
                'createdTime' => '2024-01-15 10:31:00',
                'data' => [],
            ],
        ];

        // Act
        $this->formatter->displayInstructions($instructions, $this->io);

        // Assert
        $output = $this->getOutput();
        $this->assertStringContainsString('INST001', $output);
        $this->assertStringContainsString('execute_script', $output);
        $this->assertStringContainsString('执行脚本 #123', $output);
        $this->assertStringContainsString('INST002', $output);
        $this->assertStringContainsString('更新配置', $output);
    }

    public function testDisplayInstructionsWithUnknownType(): void
    {
        // Arrange
        $instructions = [
            [
                'instructionId' => 'INST003',
                'type' => 'unknown_type',
                'priority' => 3,
                'createdTime' => '2024-01-15 10:32:00',
                'data' => [],
            ],
        ];

        // Act
        $this->formatter->displayInstructions($instructions, $this->io);

        // Assert
        $output = $this->getOutput();
        $this->assertStringContainsString('其他操作', $output);
    }

    public function testDisplayExecutionStats(): void
    {
        // Act
        $this->formatter->displayExecutionStats('DEVICE_001', $this->io);

        // Assert
        $output = $this->getOutput();
        $this->assertStringContainsString('执行统计（最近1小时）', $output);
        $this->assertStringContainsString('执行总数', $output);
        $this->assertStringContainsString('成功数', $output);
        $this->assertStringContainsString('失败数', $output);
        $this->assertStringContainsString('成功率', $output);
        $this->assertStringContainsString('平均执行时间', $output);
    }

    public function testFormatDeviceStatus(): void
    {
        // Arrange
        $deviceCode = 'DEV001';
        $deviceInfo = [
            'isOnline' => true,
            'queueLength' => 5,
            'metrics' => ['cpuUsage' => 50.0, 'memoryUsage' => 60.0],
        ];

        // Act
        $result = $this->formatter->formatDeviceStatus($deviceCode, $deviceInfo);

        // Assert
        $this->assertStringContainsString('DEV001', $result);
        $this->assertStringContainsString('在线', $result);
        $this->assertStringContainsString('5', $result);
    }

    public function testFormatDeviceStatusOffline(): void
    {
        // Arrange
        $deviceCode = 'DEV002';
        $deviceInfo = [
            'isOnline' => false,
            'queueLength' => 0,
            'metrics' => [],
        ];

        // Act
        $result = $this->formatter->formatDeviceStatus($deviceCode, $deviceInfo);

        // Assert
        $this->assertStringContainsString('DEV002', $result);
        $this->assertStringContainsString('离线', $result);
        $this->assertStringContainsString('0', $result);
    }

    public function testFormatMetrics(): void
    {
        // Arrange
        $metrics = [
            'cpuUsage' => 45.5,
            'memoryUsage' => 60.2,
        ];

        // Act
        $result = $this->formatter->formatMetrics($metrics);

        // Assert
        $this->assertStringContainsString('45.5%', $result);
        $this->assertStringContainsString('60.2%', $result);
        $this->assertStringContainsString('CPU:', $result);
        $this->assertStringContainsString('内存:', $result);
    }

    public function testFormatMetricsWithMissingValues(): void
    {
        // Arrange
        $metrics = [];

        // Act
        $result = $this->formatter->formatMetrics($metrics);

        // Assert
        $this->assertStringContainsString('0.0%', $result);
    }

    public function testHasMetrics(): void
    {
        // Test with CPU usage only
        $this->assertTrue($this->formatter->hasMetrics(['cpuUsage' => 50.0]));

        // Test with memory usage only
        $this->assertTrue($this->formatter->hasMetrics(['memoryUsage' => 60.0]));

        // Test with both
        $this->assertTrue($this->formatter->hasMetrics([
            'cpuUsage' => 50.0,
            'memoryUsage' => 60.0,
        ]));

        // Test with empty metrics
        $this->assertFalse($this->formatter->hasMetrics([]));

        // Test with other metrics
        $this->assertFalse($this->formatter->hasMetrics(['other' => 'value']));
    }

    public function testFormatInstructionLine(): void
    {
        // Arrange
        $index = 0;
        $instruction = [
            'type' => 'execute_script',
            'instructionId' => 'INST123',
            'priority' => 1,
        ];

        // Act
        $result = $this->formatter->formatInstructionLine($index, $instruction);

        // Assert
        $this->assertStringContainsString('1.', $result); // index + 1
        $this->assertStringContainsString('execute_script', $result);
        $this->assertStringContainsString('INST123', $result);
        $this->assertStringContainsString('优先级: 1', $result);
    }

    public function testFormatInstructionLineWithMissingData(): void
    {
        // Arrange
        $index = 2;
        $instruction = [];

        // Act
        $result = $this->formatter->formatInstructionLine($index, $instruction);

        // Assert
        $this->assertStringContainsString('3.', $result); // index + 1
        $this->assertStringContainsString('unknown', $result);
        $this->assertStringContainsString('N/A', $result);
        $this->assertStringContainsString('优先级: 0', $result);
    }

    public function testFormatBusyDeviceLine(): void
    {
        // Arrange
        $device = [
            'code' => 'DEV001',
            'name' => '测试设备',
            'queueLength' => 10,
            'isOnline' => true,
        ];

        // Act
        $result = $this->formatter->formatBusyDeviceLine($device);

        // Assert
        $this->assertStringContainsString('DEV001', $result);
        $this->assertStringContainsString('测试设备', $result);
        $this->assertStringContainsString('10', $result);
        $this->assertStringContainsString('✓', $result);
    }

    public function testFormatBusyDeviceLineOffline(): void
    {
        // Arrange
        $device = [
            'code' => 'DEV002',
            'name' => '离线设备',
            'queueLength' => 5,
            'isOnline' => false,
        ];

        // Act
        $result = $this->formatter->formatBusyDeviceLine($device);

        // Assert
        $this->assertStringContainsString('DEV002', $result);
        $this->assertStringContainsString('离线设备', $result);
        $this->assertStringContainsString('5', $result);
        $this->assertStringContainsString('✗', $result);
    }

    public function testUpdateDeviceStatusSection(): void
    {
        // Arrange
        $stream = fopen('php://memory', 'r+', false);
        if (false === $stream) {
            self::fail('Failed to open memory stream');
        }
        $output = new StreamOutput($stream);
        $sections = [];
        $section = new ConsoleSectionOutput($output->getStream(), $sections, $output->getVerbosity(), $output->isDecorated(), $output->getFormatter());

        $deviceCode = 'DEV001';
        $deviceInfo = [
            'isOnline' => true,
            'queueLength' => 3,
            'metrics' => ['cpuUsage' => 40.0, 'memoryUsage' => 50.0],
        ];

        // Act
        $this->formatter->updateDeviceStatusSection($section, $deviceCode, $deviceInfo);

        // Assert
        if (is_resource($stream)) {
            rewind($stream);
            $content = stream_get_contents($stream);
        } else {
            $content = '';
        }
        $this->assertStringContainsString('DEV001', $content);
        $this->assertStringContainsString('在线', $content);
        $this->assertStringContainsString('40.0%', $content);

        if (is_resource($stream)) {
            fclose($stream);
        }
    }

    public function testUpdateInstructionsSection(): void
    {
        // Arrange
        $stream = fopen('php://memory', 'r+', false);
        if (false === $stream) {
            self::fail('Failed to open memory stream');
        }
        $output = new StreamOutput($stream);
        $sections = [];
        $section = new ConsoleSectionOutput($output->getStream(), $sections, $output->getVerbosity(), $output->isDecorated(), $output->getFormatter());

        $instructions = [
            ['type' => 'execute_script', 'instructionId' => 'INST001', 'priority' => 1],
            ['type' => 'update_config', 'instructionId' => 'INST002', 'priority' => 2],
        ];
        $queueLength = 5;
        $limit = 2;

        // Act
        $this->formatter->updateInstructionsSection($section, $instructions, $queueLength, $limit);

        // Assert
        if (is_resource($stream)) {
            rewind($stream);
            $content = stream_get_contents($stream);
        } else {
            $content = '';
        }
        $this->assertStringContainsString('待执行指令', $content);
        $this->assertStringContainsString('INST001', $content);
        $this->assertStringContainsString('还有 3 条指令', $content); // 5 - 2 = 3

        if (is_resource($stream)) {
            fclose($stream);
        }
    }

    public function testUpdateInstructionsSectionEmptyQueue(): void
    {
        // Arrange
        $stream = fopen('php://memory', 'r+', false);
        if (false === $stream) {
            self::fail('Failed to open memory stream');
        }
        $output = new StreamOutput($stream);
        $sections = [];
        $section = new ConsoleSectionOutput($output->getStream(), $sections, $output->getVerbosity(), $output->isDecorated(), $output->getFormatter());

        $instructions = [];
        $queueLength = 0;
        $limit = 10;

        // Act
        $this->formatter->updateInstructionsSection($section, $instructions, $queueLength, $limit);

        // Assert
        if (is_resource($stream)) {
            rewind($stream);
            $content = stream_get_contents($stream);
        } else {
            $content = '';
        }
        $this->assertStringContainsString('队列为空', $content);

        if (is_resource($stream)) {
            fclose($stream);
        }
    }

    public function testUpdateDevicesSummary(): void
    {
        // Arrange
        $stream = fopen('php://memory', 'r+', false);
        if (false === $stream) {
            self::fail('Failed to open memory stream');
        }
        $output = new StreamOutput($stream);
        $sections = [];
        $section = new ConsoleSectionOutput($output->getStream(), $sections, $output->getVerbosity(), $output->isDecorated(), $output->getFormatter());

        $stats = [
            'totalDevices' => 10,
            'onlineCount' => 7,
            'totalQueueLength' => 25,
        ];

        // Act
        $this->formatter->updateDevicesSummary($section, $stats);

        // Assert
        if (is_resource($stream)) {
            rewind($stream);
            $content = stream_get_contents($stream);
        } else {
            $content = '';
        }
        $this->assertStringContainsString('设备总数: 10', $content);
        $this->assertStringContainsString('在线: 7', $content);
        $this->assertStringContainsString('离线: 3', $content);
        $this->assertStringContainsString('总队列: 25', $content);

        if (is_resource($stream)) {
            fclose($stream);
        }
    }

    public function testUpdateBusyDevicesSection(): void
    {
        // Arrange
        $stream = fopen('php://memory', 'r+', false);
        if (false === $stream) {
            self::fail('Failed to open memory stream');
        }
        $output = new StreamOutput($stream);
        $sections = [];
        $section = new ConsoleSectionOutput($output->getStream(), $sections, $output->getVerbosity(), $output->isDecorated(), $output->getFormatter());

        $busyDevices = [
            ['code' => 'DEV001', 'name' => '设备1', 'queueLength' => 5, 'isOnline' => true],
            ['code' => 'DEV002', 'name' => '设备2', 'queueLength' => 3, 'isOnline' => false],
            ['code' => 'DEV003', 'name' => '设备3', 'queueLength' => 2, 'isOnline' => true],
        ];
        $limit = 2;

        // Act
        $this->formatter->updateBusyDevicesSection($section, $busyDevices, $limit);

        // Assert
        if (is_resource($stream)) {
            rewind($stream);
            $content = stream_get_contents($stream);
        } else {
            $content = '';
        }
        $this->assertStringContainsString('繁忙设备', $content);
        $this->assertStringContainsString('DEV001', $content);
        $this->assertStringContainsString('DEV002', $content);
        $this->assertStringContainsString('还有 1 个繁忙设备', $content);

        if (is_resource($stream)) {
            fclose($stream);
        }
    }

    public function testUpdateBusyDevicesSectionEmpty(): void
    {
        // Arrange
        $stream = fopen('php://memory', 'r+', false);
        if (false === $stream) {
            self::fail('Failed to open memory stream');
        }
        $output = new StreamOutput($stream);
        $sections = [];
        $section = new ConsoleSectionOutput($output->getStream(), $sections, $output->getVerbosity(), $output->isDecorated(), $output->getFormatter());

        $busyDevices = [];
        $limit = 10;

        // Act
        $this->formatter->updateBusyDevicesSection($section, $busyDevices, $limit);

        // Assert
        if (is_resource($stream)) {
            rewind($stream);
            $content = stream_get_contents($stream);
        } else {
            $content = '';
        }
        $this->assertStringContainsString('所有设备队列为空', $content);

        if (is_resource($stream)) {
            fclose($stream);
        }
    }

    public function testUpdateStatusSection(): void
    {
        // Arrange
        $stream = fopen('php://memory', 'r+', false);
        if (false === $stream) {
            self::fail('Failed to open memory stream');
        }
        $output = new StreamOutput($stream);
        $sections = [];
        $section = new ConsoleSectionOutput($output->getStream(), $sections, $output->getVerbosity(), $output->isDecorated(), $output->getFormatter());

        $iteration = 42;

        // Act
        $this->formatter->updateStatusSection($section, $iteration);

        // Assert
        if (is_resource($stream)) {
            rewind($stream);
            $content = stream_get_contents($stream);
        } else {
            $content = '';
        }
        $this->assertStringContainsString('监控状态: 运行中', $content);
        $this->assertStringContainsString('迭代次数: 42', $content);

        if (is_resource($stream)) {
            fclose($stream);
        }
    }

    /**
     * 获取控制台输出内容.
     */
    private function getOutput(): string
    {
        rewind($this->output->getStream());

        return stream_get_contents($this->output->getStream());
    }

    /**
     * 创建模拟设备对象
     */
    private function createMockDevice(string $name): object
    {
        return new class($name) {
            private string $name;

            public function __construct(string $name)
            {
                $this->name = $name;
            }

            public function getBaseDevice(): object
            {
                return new class($this->name) {
                    private string $name;

                    public function __construct(string $name)
                    {
                        $this->name = $name;
                    }

                    public function getName(): string
                    {
                        return $this->name;
                    }
                };
            }
        };
    }
}
