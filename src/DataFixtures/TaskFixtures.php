<?php

namespace Tourze\AutoJsControlBundle\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Tourze\AutoJsControlBundle\Entity\AutoJsDevice;
use Tourze\AutoJsControlBundle\Entity\DeviceGroup;
use Tourze\AutoJsControlBundle\Entity\Script;
use Tourze\AutoJsControlBundle\Entity\Task;
use Tourze\AutoJsControlBundle\Enum\TaskStatus;
use Tourze\AutoJsControlBundle\Enum\TaskTargetType;
use Tourze\AutoJsControlBundle\Enum\TaskType;

class TaskFixtures extends Fixture implements DependentFixtureInterface
{
    public const REFERENCE_TASK_1 = 'task-1';
    public const REFERENCE_TASK_2 = 'task-2';

    public function load(ObjectManager $manager): void
    {
        $task1 = new Task();
        $task1->setName('测试任务1');
        $task1->setDescription('立即执行的测试任务');
        $task1->setTaskType(TaskType::IMMEDIATE);
        $task1->setStatus(TaskStatus::PENDING);
        $task1->setTargetType(TaskTargetType::SPECIFIC);
        $device1 = $this->getReference(AutoJsDeviceFixtures::REFERENCE_DEVICE_1, AutoJsDevice::class);
        $deviceIds = json_encode([$device1->getId()]);
        $task1->setTargetDeviceIds(false !== $deviceIds ? $deviceIds : null);
        $task1->setScript($this->getReference(ScriptFixtures::REFERENCE_SCRIPT_1, Script::class));
        $task1->setPriority(5);
        $task1->setMaxRetries(3);
        $task1->setRetryCount(0);
        $task1->setScheduledTime(new \DateTimeImmutable());
        $manager->persist($task1);
        $this->addReference(self::REFERENCE_TASK_1, $task1);

        $task2 = new Task();
        $task2->setName('测试任务2');
        $task2->setDescription('定时执行的测试任务');
        $task2->setTaskType(TaskType::SCHEDULED);
        $task2->setStatus(TaskStatus::PENDING);
        $task2->setTargetType(TaskTargetType::GROUP);
        $task2->setTargetGroup($this->getReference(DeviceGroupFixtures::REFERENCE_GROUP_1, DeviceGroup::class));
        $task2->setScript($this->getReference(ScriptFixtures::REFERENCE_SCRIPT_2, Script::class));
        $task2->setPriority(3);
        $task2->setMaxRetries(2);
        $task2->setRetryCount(0);
        $task2->setScheduledTime(new \DateTimeImmutable('+1 hour'));
        $manager->persist($task2);
        $this->addReference(self::REFERENCE_TASK_2, $task2);

        $task3 = new Task();
        $task3->setName('已完成任务');
        $task3->setDescription('已完成的测试任务');
        $task3->setTaskType(TaskType::IMMEDIATE);
        $task3->setStatus(TaskStatus::COMPLETED);
        $task3->setTargetType(TaskTargetType::SPECIFIC);
        $device2 = $this->getReference(AutoJsDeviceFixtures::REFERENCE_DEVICE_2, AutoJsDevice::class);
        $deviceIds = json_encode([$device2->getId()]);
        $task3->setTargetDeviceIds(false !== $deviceIds ? $deviceIds : null);
        $task3->setScript($this->getReference(ScriptFixtures::REFERENCE_SCRIPT_1, Script::class));
        $task3->setPriority(1);
        $task3->setMaxRetries(3);
        $task3->setRetryCount(0);
        $task3->setScheduledTime(new \DateTimeImmutable('-1 hour'));
        $task3->setStartedAt(new \DateTimeImmutable('-50 minutes'));
        $task3->setCompletedAt(new \DateTimeImmutable('-45 minutes'));
        $manager->persist($task3);

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            AutoJsDeviceFixtures::class,
            DeviceGroupFixtures::class,
            ScriptFixtures::class,
        ];
    }
}
