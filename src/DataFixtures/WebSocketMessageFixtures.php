<?php

namespace Tourze\AutoJsControlBundle\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Tourze\AutoJsControlBundle\Entity\AutoJsDevice;
use Tourze\AutoJsControlBundle\Entity\WebSocketMessage;

class WebSocketMessageFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        $message1 = new WebSocketMessage();
        $message1->setMessageId('msg_001');
        $message1->setAutoJsDevice($this->getReference(AutoJsDeviceFixtures::REFERENCE_DEVICE_1, AutoJsDevice::class));
        $message1->setMessageType('heartbeat');
        $message1->setDirection('in');
        $message1->setContent((string) json_encode(['timestamp' => time(), 'battery' => 85]));
        $message1->setIsProcessed(true);
        $message1->setProcessTime(new \DateTimeImmutable('-1 minute'));
        $manager->persist($message1);

        $message2 = new WebSocketMessage();
        $message2->setMessageId('msg_002');
        $message2->setAutoJsDevice($this->getReference(AutoJsDeviceFixtures::REFERENCE_DEVICE_1, AutoJsDevice::class));
        $message2->setMessageType('command');
        $message2->setDirection('out');
        $message2->setContent((string) json_encode(['action' => 'execute_script', 'script_id' => 1]));
        $message2->setIsProcessed(false);
        $message2->setProcessTime(null);
        $manager->persist($message2);

        $message3 = new WebSocketMessage();
        $message3->setMessageId('msg_003');
        $message3->setAutoJsDevice($this->getReference(AutoJsDeviceFixtures::REFERENCE_DEVICE_2, AutoJsDevice::class));
        $message3->setMessageType('log');
        $message3->setDirection('in');
        $message3->setContent((string) json_encode(['level' => 'info', 'message' => '脚本执行完成']));
        $message3->setIsProcessed(true);
        $message3->setProcessTime(new \DateTimeImmutable('-5 minutes'));
        $manager->persist($message3);

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            AutoJsDeviceFixtures::class,
        ];
    }
}
