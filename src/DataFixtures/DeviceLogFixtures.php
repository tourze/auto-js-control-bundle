<?php

namespace Tourze\AutoJsControlBundle\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Tourze\AutoJsControlBundle\Entity\AutoJsDevice;
use Tourze\AutoJsControlBundle\Entity\DeviceLog;
use Tourze\AutoJsControlBundle\Enum\LogLevel;
use Tourze\AutoJsControlBundle\Enum\LogType;

class DeviceLogFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        $log1 = new DeviceLog();
        $log1->setAutoJsDevice($this->getReference(AutoJsDeviceFixtures::REFERENCE_DEVICE_1, AutoJsDevice::class));
        $log1->setLogLevel(LogLevel::INFO);
        $log1->setLogType(LogType::CONNECTION);
        $log1->setTitle('设备连接成功');
        $log1->setMessage('设备连接成功');
        $log1->setContext((string) json_encode(['connection_id' => 'ws_test_connection_001']));
        $manager->persist($log1);

        $log2 = new DeviceLog();
        $log2->setAutoJsDevice($this->getReference(AutoJsDeviceFixtures::REFERENCE_DEVICE_1, AutoJsDevice::class));
        $log2->setLogLevel(LogLevel::ERROR);
        $log2->setLogType(LogType::SCRIPT);
        $log2->setTitle('脚本执行失败');
        $log2->setMessage('脚本执行失败');
        $log2->setContext((string) json_encode(['script_id' => 1, 'error' => 'ReferenceError: undefined variable']));
        $manager->persist($log2);

        $log3 = new DeviceLog();
        $log3->setAutoJsDevice($this->getReference(AutoJsDeviceFixtures::REFERENCE_DEVICE_2, AutoJsDevice::class));
        $log3->setLogLevel(LogLevel::WARNING);
        $log3->setLogType(LogType::SYSTEM);
        $log3->setTitle('设备电量低');
        $log3->setMessage('设备电量低');
        $log3->setContext((string) json_encode(['battery_level' => 15]));
        $manager->persist($log3);

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            AutoJsDeviceFixtures::class,
        ];
    }
}
