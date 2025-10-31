<?php

namespace Tourze\AutoJsControlBundle\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Tourze\AutoJsControlBundle\Entity\AutoJsDevice;
use Tourze\AutoJsControlBundle\Entity\DeviceMonitorData;

class DeviceMonitorDataFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        $monitor1 = new DeviceMonitorData();
        $monitor1->setAutoJsDevice($this->getReference(AutoJsDeviceFixtures::REFERENCE_DEVICE_1, AutoJsDevice::class));
        $monitor1->setCpuUsage(45.5);
        $monitor1->setMemoryUsed('6800');
        $monitor1->setMemoryTotal('10240');
        $monitor1->setBatteryLevel(85);
        $monitor1->setNetworkType('wifi');
        $monitor1->setNetworkLatency(45);
        $monitor1->setTemperature(38.5);
        $monitor1->setIsCharging(false);
        $monitor1->setRunningScripts(2);
        $monitor1->setExtraData((string) json_encode(['running_apps' => ['com.example.app1', 'com.example.app2']]));
        $manager->persist($monitor1);

        $monitor2 = new DeviceMonitorData();
        $monitor2->setAutoJsDevice($this->getReference(AutoJsDeviceFixtures::REFERENCE_DEVICE_2, AutoJsDevice::class));
        $monitor2->setCpuUsage(20.3);
        $monitor2->setMemoryUsed('2700');
        $monitor2->setMemoryTotal('6144');
        $monitor2->setBatteryLevel(15);
        $monitor2->setNetworkType('4g');
        $monitor2->setNetworkLatency(75);
        $monitor2->setTemperature(42.1);
        $monitor2->setIsCharging(true);
        $monitor2->setRunningScripts(1);
        $monitor2->setExtraData((string) json_encode(['running_apps' => ['com.android.settings']]));
        $manager->persist($monitor2);

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            AutoJsDeviceFixtures::class,
        ];
    }
}
