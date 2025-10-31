<?php

namespace Tourze\AutoJsControlBundle\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Tourze\AutoJsControlBundle\Entity\DeviceGroup;

class DeviceGroupFixtures extends Fixture
{
    public const REFERENCE_GROUP_1 = 'device-group-1';
    public const REFERENCE_GROUP_2 = 'device-group-2';

    public function load(ObjectManager $manager): void
    {
        $group1 = new DeviceGroup();
        $group1->setName('测试分组1');
        $group1->setDescription('用于测试的设备分组1');
        $group1->setSortOrder(1);
        $group1->setValid(true);
        $manager->persist($group1);
        $this->addReference(self::REFERENCE_GROUP_1, $group1);

        $group2 = new DeviceGroup();
        $group2->setName('测试分组2');
        $group2->setDescription('用于测试的设备分组2');
        $group2->setSortOrder(2);
        $group2->setValid(true);
        $manager->persist($group2);
        $this->addReference(self::REFERENCE_GROUP_2, $group2);

        $group3 = new DeviceGroup();
        $group3->setName('停用分组');
        $group3->setDescription('已停用的设备分组');
        $group3->setSortOrder(99);
        $group3->setValid(false);
        $manager->persist($group3);

        $manager->flush();
    }
}
