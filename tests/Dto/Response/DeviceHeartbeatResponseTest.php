<?php

declare(strict_types=1);

namespace Tourze\AutoJsControlBundle\Tests\Dto\Response;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tourze\AutoJsControlBundle\Dto\Response\DeviceHeartbeatResponse;
use Tourze\AutoJsControlBundle\ValueObject\DeviceInstruction;

/**
 * @internal
 */
#[CoversClass(DeviceHeartbeatResponse::class)]
final class DeviceHeartbeatResponseTest extends TestCase
{
    #[Test]
    public function constructorSetsProperties(): void
    {
        // Arrange
        $status = 'ok';
        $serverTime = new \DateTimeImmutable('2024-01-01 12:00:00');
        $config = ['heartbeatInterval' => 30, 'logLevel' => 'debug'];
        $message = 'Heartbeat acknowledged';
        $instruction1 = new DeviceInstruction('inst_001', DeviceInstruction::TYPE_EXECUTE_SCRIPT, ['script' => 'test.js']);
        $instruction2 = new DeviceInstruction('inst_002', DeviceInstruction::TYPE_UPDATE_APP, ['version' => '1.0.0']);
        $instructions = [$instruction1, $instruction2];

        // Act
        $response = new DeviceHeartbeatResponse(
            status: $status,
            instructions: $instructions,
            serverTime: $serverTime,
            config: $config,
            message: $message
        );

        // Assert
        $this->assertEquals($status, $response->getStatus());
        $this->assertEquals($message, $response->getMessage());
        $this->assertEquals($instructions, $response->getInstructions());
        $this->assertEquals($serverTime, $response->getServerTime());
        $this->assertEquals($config, $response->getConfig());
    }

    #[Test]
    public function constructorWithDefaultValues(): void
    {
        // Act
        $response = new DeviceHeartbeatResponse();

        // Assert
        $this->assertEquals('ok', $response->getStatus());
        $this->assertNull($response->getMessage());
        $this->assertEquals([], $response->getInstructions());
        $this->assertInstanceOf(\DateTimeImmutable::class, $response->getServerTime());
        $this->assertNull($response->getConfig());
    }

    #[Test]
    public function createSuccessResponse(): void
    {
        // Arrange
        $instruction = new DeviceInstruction('inst_test', DeviceInstruction::TYPE_PING, ['data' => 'value']);
        $instructions = [$instruction];
        $config = ['heartbeatInterval' => 45];

        // Act
        $response = DeviceHeartbeatResponse::success(
            instructions: $instructions,
            config: $config
        );

        // Assert
        $this->assertEquals('ok', $response->getStatus());
        $this->assertNull($response->getMessage());
        $this->assertEquals($instructions, $response->getInstructions());
        $this->assertEquals($config, $response->getConfig());
    }

    #[Test]
    public function createErrorResponse(): void
    {
        // Act
        $response = DeviceHeartbeatResponse::error('Device not found');

        // Assert
        $this->assertEquals('error', $response->getStatus());
        $this->assertEquals('Device not found', $response->getMessage());
        $this->assertEquals([], $response->getInstructions());
        $this->assertInstanceOf(\DateTimeImmutable::class, $response->getServerTime());
        $this->assertNull($response->getConfig());
    }

    #[Test]
    public function createUnauthorizedResponse(): void
    {
        // Act
        $response = DeviceHeartbeatResponse::unauthorized();

        // Assert
        $this->assertEquals('unauthorized', $response->getStatus());
        $this->assertEquals('设备认证失败', $response->getMessage());
        $this->assertEquals([], $response->getInstructions());
    }

    #[Test]
    public function createUnauthorizedResponseWithCustomMessage(): void
    {
        // Act
        $response = DeviceHeartbeatResponse::unauthorized('证书已过期');

        // Assert
        $this->assertEquals('unauthorized', $response->getStatus());
        $this->assertEquals('证书已过期', $response->getMessage());
    }

    #[Test]
    public function addInstructionAddsCorrectly(): void
    {
        // Arrange
        $response = new DeviceHeartbeatResponse();
        $instruction = new DeviceInstruction('inst_add', DeviceInstruction::TYPE_COLLECT_LOG);

        // Act
        $response->addInstruction($instruction);

        // Assert
        $this->assertCount(1, $response->getInstructions());
        $this->assertEquals($instruction, $response->getInstructions()[0]);
        $this->assertTrue($response->hasInstructions());
    }

    #[Test]
    public function jsonSerializeReturnsCorrectData(): void
    {
        // Arrange
        $serverTime = new \DateTimeImmutable('2024-01-01 14:00:00');
        $instruction1 = new DeviceInstruction('inst_001', DeviceInstruction::TYPE_RESTART_APP, [], 600, 10);
        $instruction2 = new DeviceInstruction('inst_002', DeviceInstruction::TYPE_COLLECT_LOG, ['level' => 'debug'], 300, 3);

        $response = new DeviceHeartbeatResponse(
            status: 'ok',
            instructions: [$instruction1, $instruction2],
            serverTime: $serverTime,
            config: ['debug' => true],
            message: 'Multiple instructions'
        );

        // Act
        $json = json_encode($response);
        $this->assertNotFalse($json, 'JSON encoding should not fail');
        $decoded = json_decode($json, true);

        // Assert
        $this->assertEquals('ok', $decoded['status']);
        $this->assertEquals('Multiple instructions', $decoded['message']);
        $this->assertEquals($serverTime->format(\DateTimeInterface::RFC3339), $decoded['serverTime']);
        $this->assertEquals(2, $decoded['instructionCount']);
        $this->assertCount(2, $decoded['instructions']);
        $this->assertEquals(DeviceInstruction::TYPE_RESTART_APP, $decoded['instructions'][0]['type']);
        $this->assertEquals(DeviceInstruction::TYPE_COLLECT_LOG, $decoded['instructions'][1]['type']);
        $this->assertEquals(['debug' => true], $decoded['config']);
    }

    #[Test]
    public function hasInstructionsReturnsCorrectValue(): void
    {
        // Test with instructions
        $responseWithInstructions = new DeviceHeartbeatResponse(
            instructions: [new DeviceInstruction('test', DeviceInstruction::TYPE_PING)]
        );
        $this->assertTrue($responseWithInstructions->hasInstructions());

        // Test without instructions
        $responseWithoutInstructions = new DeviceHeartbeatResponse();
        $this->assertFalse($responseWithoutInstructions->hasInstructions());
    }

    #[Test]
    public function getInstructionCountReturnsCorrectValue(): void
    {
        // Arrange
        $instructions = [
            new DeviceInstruction('inst_1', DeviceInstruction::TYPE_EXECUTE_SCRIPT),
            new DeviceInstruction('inst_2', DeviceInstruction::TYPE_STOP_SCRIPT),
            new DeviceInstruction('inst_3', DeviceInstruction::TYPE_UPDATE_STATUS),
        ];

        $response = new DeviceHeartbeatResponse(instructions: $instructions);

        // Assert
        $this->assertEquals(3, $response->getInstructionCount());
    }

    #[Test]
    public function jsonSerializeWithEmptyInstructions(): void
    {
        // Arrange
        $response = new DeviceHeartbeatResponse(
            status: 'ok',
            message: 'No instructions'
        );

        // Act
        $json = json_encode($response);
        $this->assertNotFalse($json, 'JSON encoding should not fail');
        $decoded = json_decode($json, true);

        // Assert
        $this->assertEquals('ok', $decoded['status']);
        $this->assertEquals('No instructions', $decoded['message']);
        $this->assertEquals(0, $decoded['instructionCount']);
        $this->assertArrayNotHasKey('instructions', $decoded);
        $this->assertArrayNotHasKey('config', $decoded);
    }
}
