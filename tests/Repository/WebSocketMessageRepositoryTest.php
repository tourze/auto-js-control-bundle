<?php

declare(strict_types=1);

namespace Tourze\AutoJsControlBundle\Tests\Repository;

use DeviceBundle\Entity\Device as BaseDevice;
use DeviceBundle\Enum\DeviceType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Test;
use Tourze\AutoJsControlBundle\Entity\AutoJsDevice;
use Tourze\AutoJsControlBundle\Entity\WebSocketMessage;
use Tourze\AutoJsControlBundle\Repository\WebSocketMessageRepository;
use Tourze\PHPUnitSymfonyKernelTest\AbstractRepositoryTestCase;

/**
 * @internal
 */
#[CoversClass(WebSocketMessageRepository::class)]
#[RunTestsInSeparateProcesses]
final class WebSocketMessageRepositoryTest extends AbstractRepositoryTestCase
{
    private WebSocketMessageRepository $repository;

    protected function onSetUp(): void
    {
        $repository = self::getEntityManager()->getRepository(WebSocketMessage::class);
        $this->assertInstanceOf(WebSocketMessageRepository::class, $repository);
        $this->repository = $repository;
    }

    #[Test]
    public function testFindUnprocessedMessages(): void
    {
        // Arrange
        $device = $this->createAutoJsDevice('WS_DEVICE_001');
        $em = self::getEntityManager();
        $em->persist($device);

        // Unprocessed incoming messages
        $unprocessed1 = $this->createMessage($device, 'in', false, 'heartbeat');
        $unprocessed2 = $this->createMessage($device, 'in', false, 'event');

        // Processed incoming message (should not be included)
        $processed = $this->createMessage($device, 'in', true, 'heartbeat');

        // Unprocessed outgoing message (should not be included)
        $outgoing = $this->createMessage($device, 'out', false, 'command');

        $em->persist($unprocessed1);
        $em->persist($unprocessed2);
        $em->persist($processed);
        $em->persist($outgoing);
        $em->flush();

        // Act
        $messages = $this->repository->findUnprocessedMessages(10);

        // Assert
        $this->assertCount(2, $messages);
        $messageIds = array_map(fn ($m) => $m->getId(), $messages);
        $this->assertContains($unprocessed1->getId(), $messageIds);
        $this->assertContains($unprocessed2->getId(), $messageIds);

        // Check order (createTime ASC - oldest first)
        $this->assertEquals($unprocessed1->getId(), $messages[0]->getId());
        $this->assertEquals($unprocessed2->getId(), $messages[1]->getId());
    }

    #[Test]
    public function testFindUnprocessedMessagesWithLimit(): void
    {
        // Arrange
        $device = $this->createAutoJsDevice('WS_DEVICE_002');
        $em = self::getEntityManager();
        $em->persist($device);

        // Create 5 unprocessed messages
        for ($i = 0; $i < 5; ++$i) {
            $message = $this->createMessage($device, 'in', false, 'event');
            $em->persist($message);
        }
        $em->flush();

        // Act
        $messages = $this->repository->findUnprocessedMessages(3);

        // Assert
        $this->assertCount(3, $messages);
    }

    #[Test]
    public function testFindByAutoJsDevice(): void
    {
        // Arrange
        $device1 = $this->createAutoJsDevice('WS_DEVICE_003');
        $device2 = $this->createAutoJsDevice('WS_DEVICE_004');
        $em = self::getEntityManager();
        $em->persist($device1);
        $em->persist($device2);

        // Messages for device1
        $message1 = $this->createMessage($device1, 'in', false, 'heartbeat');
        $message2 = $this->createMessage($device1, 'out', true, 'command');

        // Message for device2 (should not be included)
        $message3 = $this->createMessage($device2, 'in', false, 'event');

        $em->persist($message1);
        $em->persist($message2);
        $em->persist($message3);
        $em->flush();

        // Act
        $deviceId = $device1->getId();
        $this->assertNotNull($deviceId);
        $messages = $this->repository->findByAutoJsDevice((string) $deviceId);

        // Assert
        $this->assertCount(2, $messages);
        $messageIds = array_map(fn ($m) => $m->getId(), $messages);
        $this->assertContains($message1->getId(), $messageIds);
        $this->assertContains($message2->getId(), $messageIds);
        $this->assertNotContains($message3->getId(), $messageIds);

        // Check order (createTime DESC - newest first)
        $this->assertEquals($message2->getId(), $messages[0]->getId());
        $this->assertEquals($message1->getId(), $messages[1]->getId());
    }

    #[Test]
    public function testFindByAutoJsDeviceWithMessageTypeFilter(): void
    {
        // Arrange
        $device = $this->createAutoJsDevice('WS_DEVICE_005');
        $em = self::getEntityManager();
        $em->persist($device);

        $heartbeatMessage = $this->createMessage($device, 'in', false, 'heartbeat');
        $eventMessage = $this->createMessage($device, 'in', false, 'event');
        $commandMessage = $this->createMessage($device, 'out', false, 'command');

        $em->persist($heartbeatMessage);
        $em->persist($eventMessage);
        $em->persist($commandMessage);
        $em->flush();

        // Act
        $deviceId = $device->getId();
        $this->assertNotNull($deviceId);
        $messages = $this->repository->findByAutoJsDevice((string) $deviceId, ['messageType' => 'heartbeat']);

        // Assert
        $this->assertCount(1, $messages);
        $this->assertEquals($heartbeatMessage->getId(), $messages[0]->getId());
    }

    #[Test]
    public function testFindByAutoJsDeviceWithDirectionFilter(): void
    {
        // Arrange
        $device = $this->createAutoJsDevice('WS_DEVICE_006');
        $em = self::getEntityManager();
        $em->persist($device);

        $inMessage1 = $this->createMessage($device, 'in', false, 'heartbeat');
        $inMessage2 = $this->createMessage($device, 'in', true, 'event');
        $outMessage = $this->createMessage($device, 'out', false, 'command');

        $em->persist($inMessage1);
        $em->persist($inMessage2);
        $em->persist($outMessage);
        $em->flush();

        // Act
        $deviceId = $device->getId();
        $this->assertNotNull($deviceId);
        $messages = $this->repository->findByAutoJsDevice((string) $deviceId, ['direction' => 'in']);

        // Assert
        $this->assertCount(2, $messages);
        $messageIds = array_map(fn ($m) => $m->getId(), $messages);
        $this->assertContains($inMessage1->getId(), $messageIds);
        $this->assertContains($inMessage2->getId(), $messageIds);
        $this->assertNotContains($outMessage->getId(), $messageIds);
    }

    #[Test]
    public function testFindByAutoJsDeviceWithMultipleFilters(): void
    {
        // Arrange
        $device = $this->createAutoJsDevice('WS_DEVICE_007');
        $em = self::getEntityManager();
        $em->persist($device);

        $inHeartbeat = $this->createMessage($device, 'in', false, 'heartbeat');
        $inEvent = $this->createMessage($device, 'in', false, 'event');
        $outHeartbeat = $this->createMessage($device, 'out', false, 'heartbeat');

        $em->persist($inHeartbeat);
        $em->persist($inEvent);
        $em->persist($outHeartbeat);
        $em->flush();

        // Act
        $deviceId = $device->getId();
        $this->assertNotNull($deviceId);
        $messages = $this->repository->findByAutoJsDevice((string) $deviceId, [
            'messageType' => 'heartbeat',
            'direction' => 'in',
        ]);

        // Assert
        $this->assertCount(1, $messages);
        $this->assertEquals($inHeartbeat->getId(), $messages[0]->getId());
    }

    #[Test]
    public function testFindByAutoJsDeviceWithLimit(): void
    {
        // Arrange
        $device = $this->createAutoJsDevice('WS_DEVICE_008');
        $em = self::getEntityManager();
        $em->persist($device);

        // Create 5 messages
        for ($i = 0; $i < 5; ++$i) {
            $message = $this->createMessage($device, 'in', false, 'event');
            $em->persist($message);
        }
        $em->flush();

        // Act
        $deviceId = $device->getId();
        $this->assertNotNull($deviceId);
        $messages = $this->repository->findByAutoJsDevice((string) $deviceId, [], 3);

        // Assert
        $this->assertCount(3, $messages);
    }

    #[Test]
    public function testDeleteOldMessages(): void
    {
        // Arrange
        $device = $this->createAutoJsDevice('WS_DEVICE_009');
        $em = self::getEntityManager();
        $em->persist($device);

        $threshold = new \DateTime('-1 day');

        // Old messages
        $oldMessage1 = $this->createMessage($device, 'in', true, 'heartbeat');
        $oldMessage1->setCreateTime(new \DateTimeImmutable('-2 days'));

        $oldMessage2 = $this->createMessage($device, 'out', false, 'command');
        $oldMessage2->setCreateTime(new \DateTimeImmutable('-3 days'));

        // New message
        $newMessage = $this->createMessage($device, 'in', false, 'event');
        $newMessage->setCreateTime(new \DateTimeImmutable('-12 hours'));

        $em->persist($oldMessage1);
        $em->persist($oldMessage2);
        $em->persist($newMessage);
        $em->flush();

        // Act
        $deletedCount = $this->repository->deleteOldMessages($threshold);

        // Assert
        $this->assertEquals(2, $deletedCount);

        // Verify new message still exists
        $em->clear();
        $deviceId = $device->getId();
        $this->assertNotNull($deviceId);
        $remainingMessages = $this->repository->findByAutoJsDevice((string) $deviceId);
        $this->assertCount(1, $remainingMessages);
        $this->assertEquals($newMessage->getId(), $remainingMessages[0]->getId());
    }

    #[Test]
    public function testFind(): void
    {
        // Arrange
        $device = $this->createAutoJsDevice('FIND_WS_DEVICE');
        $message = $this->createMessage($device, 'in', false, 'heartbeat');

        $em = self::getEntityManager();
        $em->persist($device);
        $em->persist($message);
        $em->flush();

        $messageId = $message->getId();
        $this->assertNotNull($messageId);

        // Act
        $found = $this->repository->find($messageId);

        // Assert
        $this->assertNotNull($found);
        $this->assertEquals($messageId, $found->getId());
        $this->assertEquals($message->getMessageId(), $found->getMessageId());
    }

    #[Test]
    public function testFindWithNonExistentId(): void
    {
        // Act
        $found = $this->repository->find(99999);

        // Assert
        $this->assertNull($found);
    }

    #[Test]
    public function testFindAll(): void
    {
        // Arrange
        $device = $this->createAutoJsDevice('ALL_WS_DEVICE');
        $message1 = $this->createMessage($device, 'in', false, 'heartbeat');
        $message2 = $this->createMessage($device, 'out', true, 'command');
        $message3 = $this->createMessage($device, 'in', false, 'event');

        $em = self::getEntityManager();
        $em->persist($device);
        $em->persist($message1);
        $em->persist($message2);
        $em->persist($message3);
        $em->flush();

        // Act
        $messages = $this->repository->findAll();

        // Assert
        $this->assertGreaterThanOrEqual(3, count($messages));
        $messageIds = array_map(fn ($m) => $m->getId(), $messages);
        $this->assertContains($message1->getId(), $messageIds);
        $this->assertContains($message2->getId(), $messageIds);
        $this->assertContains($message3->getId(), $messageIds);
    }

    #[Test]
    public function testFindBy(): void
    {
        // Arrange
        $device = $this->createAutoJsDevice('BY_WS_DEVICE');
        $message1 = $this->createMessage($device, 'in', false, 'heartbeat');
        $message2 = $this->createMessage($device, 'in', true, 'heartbeat');
        $message3 = $this->createMessage($device, 'out', false, 'command');

        $em = self::getEntityManager();
        $em->persist($device);
        $em->persist($message1);
        $em->persist($message2);
        $em->persist($message3);
        $em->flush();

        // Act
        $inMessages = $this->repository->findBy(['direction' => 'in']);
        $unprocessedMessages = $this->repository->findBy(['isProcessed' => false]);
        $heartbeatMessages = $this->repository->findBy(['messageType' => 'heartbeat']);

        // Assert
        $this->assertGreaterThanOrEqual(2, count($inMessages));
        foreach ($inMessages as $message) {
            $this->assertEquals('in', $message->getDirection());
        }

        $this->assertGreaterThanOrEqual(2, count($unprocessedMessages));
        foreach ($unprocessedMessages as $message) {
            $this->assertFalse($message->isProcessed());
        }

        $this->assertGreaterThanOrEqual(2, count($heartbeatMessages));
        foreach ($heartbeatMessages as $message) {
            $this->assertEquals('heartbeat', $message->getMessageType());
        }
    }

    #[Test]
    public function testFindByWithLimit(): void
    {
        // Arrange
        $device = $this->createAutoJsDevice('LIMIT_WS_DEVICE');
        $message1 = $this->createMessage($device, 'in', false, 'heartbeat');
        $message2 = $this->createMessage($device, 'in', false, 'event');
        $message3 = $this->createMessage($device, 'in', false, 'command');

        $em = self::getEntityManager();
        $em->persist($device);
        $em->persist($message1);
        $em->persist($message2);
        $em->persist($message3);
        $em->flush();

        // Act
        $messages = $this->repository->findBy(['direction' => 'in'], null, 2);

        // Assert
        $this->assertGreaterThanOrEqual(2, count($messages));
        $this->assertLessThanOrEqual(2, count($messages));
    }

    #[Test]
    public function testFindOneBy(): void
    {
        // Arrange
        $device = $this->createAutoJsDevice('ONE_WS_DEVICE');
        $message1 = $this->createMessage($device, 'in', false, 'heartbeat');
        $message2 = $this->createMessage($device, 'in', false, 'heartbeat');

        $em = self::getEntityManager();
        $em->persist($device);
        $em->persist($message1);
        $em->persist($message2);
        $em->flush();

        // Act
        $found = $this->repository->findOneBy(['messageType' => 'heartbeat']);

        // Assert
        $this->assertNotNull($found);
        $this->assertEquals('heartbeat', $found->getMessageType());
    }

    #[Test]
    public function testFindOneByWithNoMatch(): void
    {
        // Arrange
        $device = $this->createAutoJsDevice('ONE_WS_DEVICE_NULL');
        $message = $this->createMessage($device, 'in', false, 'heartbeat');

        $em = self::getEntityManager();
        $em->persist($device);
        $em->persist($message);
        $em->flush();

        // Act
        $found = $this->repository->findOneBy(['messageType' => 'nonexistent']);

        // Assert
        $this->assertNull($found);
    }

    #[Test]
    public function testCount(): void
    {
        // Arrange
        $initialCount = $this->repository->count([]);

        $device = $this->createAutoJsDevice('COUNT_WS_DEVICE');
        $message1 = $this->createMessage($device, 'in', false, 'heartbeat');
        $message2 = $this->createMessage($device, 'out', true, 'command');

        $em = self::getEntityManager();
        $em->persist($device);
        $em->persist($message1);
        $em->persist($message2);
        $em->flush();

        // Act
        $totalCount = $this->repository->count([]);
        $inCount = $this->repository->count(['direction' => 'in']);
        $unprocessedCount = $this->repository->count(['isProcessed' => false]);
        $processedCount = $this->repository->count(['isProcessed' => true]);

        // Assert
        $this->assertEquals($initialCount + 2, $totalCount);
        $this->assertGreaterThanOrEqual(1, $inCount);
        $this->assertGreaterThanOrEqual(1, $unprocessedCount);
        $this->assertGreaterThanOrEqual(1, $processedCount);
    }

    #[Test]
    public function testSave(): void
    {
        // Arrange
        $device = $this->createAutoJsDevice('SAVE_WS_DEVICE');
        $message = $this->createMessage($device, 'in', false, 'heartbeat');

        $em = self::getEntityManager();
        $em->persist($device);

        // Act
        $this->repository->save($message);

        // Assert - Verify message is persisted with correct data
        $found = $this->repository->find($message->getId());
        $this->assertNotNull($found);
        $this->assertEquals($message->getMessageId(), $found->getMessageId());
    }

    #[Test]
    public function testSaveWithoutFlush(): void
    {
        // Arrange
        $device = $this->createAutoJsDevice('SAVE_WS_DEVICE_NO_FLUSH');
        $message = $this->createMessage($device, 'in', false, 'heartbeat');

        $em = self::getEntityManager();
        $em->persist($device);

        // Act
        $this->repository->save($message, false);

        // Clear entity manager to force database query
        $em->clear();

        // Assert - since we didn't flush, we need to clear the entity manager first
        $messageId = $message->getId();
        $this->assertNull($messageId); // Entity doesn't have an ID yet since we didn't flush
    }

    #[Test]
    public function testRemove(): void
    {
        // Arrange
        $device = $this->createAutoJsDevice('REMOVE_WS_DEVICE');
        $message = $this->createMessage($device, 'in', false, 'heartbeat');

        $em = self::getEntityManager();
        $em->persist($device);
        $em->persist($message);
        $em->flush();

        $messageId = $message->getId();
        $this->assertNotNull($messageId);

        // Act
        $this->repository->remove($message);

        // Assert
        $found = $this->repository->find($messageId);
        $this->assertNull($found);
    }

    #[Test]
    public function testFindByWithNullableFieldIsNull(): void
    {
        // Arrange
        $device = $this->createAutoJsDevice('NULL_FIELD_WS_DEVICE');
        $message = $this->createMessage($device, 'in', false, 'heartbeat');
        // processStatus, processResult 和 processTime 都是可空字段
        $message->setProcessStatus(null);
        $message->setProcessResult(null);
        $message->setProcessTime(null);

        $em = self::getEntityManager();
        $em->persist($device);
        $em->persist($message);
        $em->flush();

        // Act
        $resultProcessStatus = $this->repository->findBy(['processStatus' => null]);
        $resultProcessResult = $this->repository->findBy(['processResult' => null]);
        $resultProcessTime = $this->repository->findBy(['processTime' => null]);

        // Assert
        $this->assertIsArray($resultProcessStatus);
        $this->assertGreaterThanOrEqual(1, count($resultProcessStatus));
        $messageIds = array_map(fn ($m) => $m->getId(), $resultProcessStatus);
        $this->assertContains($message->getId(), $messageIds);

        $this->assertIsArray($resultProcessResult);
        $this->assertGreaterThanOrEqual(1, count($resultProcessResult));
        $messageIds = array_map(fn ($m) => $m->getId(), $resultProcessResult);
        $this->assertContains($message->getId(), $messageIds);

        $this->assertIsArray($resultProcessTime);
        $this->assertGreaterThanOrEqual(1, count($resultProcessTime));
        $messageIds = array_map(fn ($m) => $m->getId(), $resultProcessTime);
        $this->assertContains($message->getId(), $messageIds);
    }

    #[Test]
    public function testCountWithNullableFieldIsNull(): void
    {
        // Arrange
        $device = $this->createAutoJsDevice('COUNT_NULL_WS_DEVICE');
        $message = $this->createMessage($device, 'in', false, 'heartbeat');
        $message->setProcessStatus(null);
        $message->setProcessResult(null);
        $message->setProcessTime(null);

        $em = self::getEntityManager();
        $em->persist($device);
        $em->persist($message);
        $em->flush();

        // Act
        $countProcessStatus = $this->repository->count(['processStatus' => null]);
        $countProcessResult = $this->repository->count(['processResult' => null]);
        $countProcessTime = $this->repository->count(['processTime' => null]);

        // Assert
        $this->assertGreaterThanOrEqual(1, $countProcessStatus);
        $this->assertGreaterThanOrEqual(1, $countProcessResult);
        $this->assertGreaterThanOrEqual(1, $countProcessTime);
    }

    #[Test]
    public function testFindByWithAutoJsDeviceAssociation(): void
    {
        // Arrange
        $device = $this->createAutoJsDevice('ASSOC_WS_DEVICE');
        $message1 = $this->createMessage($device, 'in', false, 'heartbeat');
        $message2 = $this->createMessage($device, 'out', true, 'command');

        $em = self::getEntityManager();
        $em->persist($device);
        $em->persist($message1);
        $em->persist($message2);
        $em->flush();

        // Act
        $result = $this->repository->findBy(['autoJsDevice' => $device]);

        // Assert
        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $messageIds = array_map(fn ($m) => $m->getId(), $result);
        $this->assertContains($message1->getId(), $messageIds);
        $this->assertContains($message2->getId(), $messageIds);
        foreach ($result as $message) {
            $autoJsDevice = $message->getAutoJsDevice();
            $this->assertNotNull($autoJsDevice, 'AutoJsDevice should not be null');
            $this->assertSame($device->getId(), $autoJsDevice->getId());
        }
    }

    #[Test]
    public function testCountWithAutoJsDeviceAssociation(): void
    {
        // Arrange
        $device = $this->createAutoJsDevice('COUNT_ASSOC_WS_DEVICE');
        $message1 = $this->createMessage($device, 'in', false, 'heartbeat');
        $message2 = $this->createMessage($device, 'out', true, 'command');

        $em = self::getEntityManager();
        $em->persist($device);
        $em->persist($message1);
        $em->persist($message2);
        $em->flush();

        // Act
        $count = $this->repository->count(['autoJsDevice' => $device]);

        // Assert
        $this->assertSame(2, $count);
    }

    #[Test]
    public function testFindOneByWithAutoJsDeviceAssociation(): void
    {
        // Arrange
        $device = $this->createAutoJsDevice('FIND_ONE_ASSOC_WS_DEVICE');
        $message = $this->createMessage($device, 'in', false, 'heartbeat');

        $em = self::getEntityManager();
        $em->persist($device);
        $em->persist($message);
        $em->flush();

        // Act
        $result = $this->repository->findOneBy(['autoJsDevice' => $device]);

        // Assert
        $this->assertNotNull($result);
        $this->assertInstanceOf(WebSocketMessage::class, $result);
        $autoJsDevice = $result->getAutoJsDevice();
        $this->assertNotNull($autoJsDevice, 'AutoJsDevice should not be null');
        $this->assertSame($device->getId(), $autoJsDevice->getId());
    }

    #[Test]
    public function testFindOneByAssociationAutoJsDeviceShouldReturnMatchingEntity(): void
    {
        // Arrange
        $device = $this->createAutoJsDevice('FIND_ONE_ASSOC_SPEC_WS_DEVICE');
        $message = $this->createMessage($device, 'in', false, 'heartbeat');

        $em = self::getEntityManager();
        $em->persist($device);
        $em->persist($message);
        $em->flush();

        // Act
        $result = $this->repository->findOneBy(['autoJsDevice' => $device]);

        // Assert
        $this->assertNotNull($result);
        $this->assertInstanceOf(WebSocketMessage::class, $result);
        $autoJsDevice = $result->getAutoJsDevice();
        $this->assertNotNull($autoJsDevice, 'AutoJsDevice should not be null');
        $this->assertSame($device->getId(), $autoJsDevice->getId());
    }

    #[Test]
    public function testCountByAssociationAutoJsDeviceShouldReturnCorrectNumber(): void
    {
        // Arrange
        $device = $this->createAutoJsDevice('COUNT_ASSOC_SPEC_WS_DEVICE');
        $message1 = $this->createMessage($device, 'in', false, 'heartbeat');
        $message2 = $this->createMessage($device, 'out', true, 'command');

        $em = self::getEntityManager();
        $em->persist($device);
        $em->persist($message1);
        $em->persist($message2);
        $em->flush();

        // Act
        $count = $this->repository->count(['autoJsDevice' => $device]);

        // Assert
        $this->assertSame(2, $count);
    }

    #[Test]
    private function createAutoJsDevice(string $code): AutoJsDevice
    {
        $baseDevice = new BaseDevice();
        $baseDevice->setCode($code);
        $baseDevice->setName('Device ' . $code);
        $baseDevice->setModel('TestModel-' . $code);
        $baseDevice->setDeviceType(DeviceType::PHONE);

        $autoJsDevice = new AutoJsDevice();
        $autoJsDevice->setBaseDevice($baseDevice);
        $autoJsDevice->setAutoJsVersion('4.1.1');

        self::getEntityManager()->persist($baseDevice);

        return $autoJsDevice;
    }

    private function createMessage(
        AutoJsDevice $device,
        string $direction,
        bool $isProcessed,
        string $messageType,
    ): WebSocketMessage {
        $message = new WebSocketMessage();
        $message->setAutoJsDevice($device);
        $message->setDirection($direction);
        $message->setIsProcessed($isProcessed);
        $message->setMessageType($messageType);
        $message->setCreateTime(new \DateTimeImmutable());

        // Set sample message content based on type
        $content = match ($messageType) {
            'heartbeat' => ($encoded = json_encode(['type' => 'heartbeat', 'timestamp' => time()])) !== false ? $encoded : '{"type":"heartbeat"}',
            'event' => ($encoded = json_encode(['type' => 'event', 'name' => 'test_event', 'data' => []])) !== false ? $encoded : '{"type":"event"}',
            'command' => ($encoded = json_encode(['type' => 'command', 'action' => 'execute', 'scriptId' => 'test'])) !== false ? $encoded : '{"type":"command"}',
            default => ($encoded = json_encode(['type' => $messageType])) !== false ? $encoded : '{"type":"unknown"}',
        };

        $message->setMessageId(uniqid('msg_', true));
        $message->setContent($content);

        return $message;
    }

    protected function createNewEntity(): object
    {
        $baseDevice = new BaseDevice();
        $baseDevice->setCode('TEST-DEVICE-' . uniqid());
        $baseDevice->setName('Test Device');
        $baseDevice->setModel('TestModel');
        $baseDevice->setDeviceType(DeviceType::PHONE);

        $autoJsDevice = new AutoJsDevice();
        $autoJsDevice->setBaseDevice($baseDevice);
        $autoJsDevice->setAutoJsVersion('4.1.1');

        $message = new WebSocketMessage();
        $message->setAutoJsDevice($autoJsDevice);
        $message->setDirection('in');
        $message->setIsProcessed(false);
        $message->setMessageType('heartbeat');
        $message->setCreateTime(new \DateTimeImmutable());
        $message->setMessageId(uniqid('msg_', true));
        $message->setContent(($encoded = json_encode(['type' => 'heartbeat', 'timestamp' => time()])) !== false ? $encoded : '{"type":"heartbeat"}');

        return $message;
    }

    /**
     * @return ServiceEntityRepository<WebSocketMessage>
     */
    protected function getRepository(): ServiceEntityRepository
    {
        return $this->repository;
    }
}
