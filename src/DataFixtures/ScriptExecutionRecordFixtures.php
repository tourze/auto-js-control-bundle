<?php

namespace Tourze\AutoJsControlBundle\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Tourze\AutoJsControlBundle\Entity\AutoJsDevice;
use Tourze\AutoJsControlBundle\Entity\Script;
use Tourze\AutoJsControlBundle\Entity\ScriptExecutionRecord;
use Tourze\AutoJsControlBundle\Entity\Task;
use Tourze\AutoJsControlBundle\Enum\ExecutionStatus;

class ScriptExecutionRecordFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        $record1 = new ScriptExecutionRecord();
        $record1->setAutoJsDevice($this->getReference(AutoJsDeviceFixtures::REFERENCE_DEVICE_1, AutoJsDevice::class));
        $record1->setScript($this->getReference(ScriptFixtures::REFERENCE_SCRIPT_1, Script::class));
        $record1->setTask($this->getReference(TaskFixtures::REFERENCE_TASK_1, Task::class));
        $record1->setStatus(ExecutionStatus::SUCCESS);
        $record1->setStartTime(new \DateTimeImmutable('-1 hour'));
        $record1->setEndTime(new \DateTimeImmutable('-58 minutes'));
        $record1->setDuration(120);
        $record1->setOutput('执行成功');
        $record1->setErrorMessage(null);
        // No setExitCode method - removed
        $manager->persist($record1);

        $record2 = new ScriptExecutionRecord();
        $record2->setAutoJsDevice($this->getReference(AutoJsDeviceFixtures::REFERENCE_DEVICE_2, AutoJsDevice::class));
        $record2->setScript($this->getReference(ScriptFixtures::REFERENCE_SCRIPT_2, Script::class));
        $record2->setTask($this->getReference(TaskFixtures::REFERENCE_TASK_2, Task::class));
        $record2->setStatus(ExecutionStatus::FAILED);
        $record2->setStartTime(new \DateTimeImmutable('-30 minutes'));
        $record2->setEndTime(new \DateTimeImmutable('-29 minutes'));
        $record2->setDuration(60);
        $record2->setOutput('');
        $record2->setErrorMessage('脚本执行超时');
        // No setExitCode method - removed
        $manager->persist($record2);

        $record3 = new ScriptExecutionRecord();
        $record3->setAutoJsDevice($this->getReference(AutoJsDeviceFixtures::REFERENCE_DEVICE_1, AutoJsDevice::class));
        $record3->setScript($this->getReference(ScriptFixtures::REFERENCE_SCRIPT_1, Script::class));
        $record3->setTask(null);
        $record3->setStatus(ExecutionStatus::RUNNING);
        $record3->setStartTime(new \DateTimeImmutable('-5 minutes'));
        $record3->setEndTime(null);
        $record3->setDuration(0);
        $record3->setOutput('脚本正在运行中...');
        $record3->setErrorMessage(null);
        // No setExitCode method - removed
        $manager->persist($record3);

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            AutoJsDeviceFixtures::class,
            ScriptFixtures::class,
            TaskFixtures::class,
        ];
    }
}
