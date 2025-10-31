<?php

namespace Tourze\AutoJsControlBundle\DataFixtures;

use DeviceBundle\Entity\Device;
use DeviceBundle\Enum\DeviceStatus;
use DeviceBundle\Enum\DeviceType;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Tourze\AutoJsControlBundle\Entity\AutoJsDevice;

class AutoJsDeviceFixtures extends Fixture
{
    public const REFERENCE_DEVICE_1 = 'auto-js-device-1';
    public const REFERENCE_DEVICE_2 = 'auto-js-device-2';

    public function load(ObjectManager $manager): void
    {
        // 创建基础设备 1
        $baseDevice1 = new Device();
        $baseDevice1->setCode('AUTO_JS_TEST_001');
        $baseDevice1->setName('测试Auto.js设备1');
        $baseDevice1->setModel('Android Test Device');
        $baseDevice1->setDeviceType(DeviceType::PHONE);
        $baseDevice1->setStatus(DeviceStatus::ONLINE);
        $baseDevice1->setBrand('TestBrand');
        $baseDevice1->setOsVersion('Android 12');
        $baseDevice1->setCpuCores(8);
        $baseDevice1->setMemorySize('8GB');
        $baseDevice1->setStorageSize('128GB');
        $baseDevice1->setFingerprint('test_fingerprint_001');
        $baseDevice1->setLastIp('192.168.1.100');
        $baseDevice1->setRegIp('192.168.1.100');
        $baseDevice1->setValid(true);
        $manager->persist($baseDevice1);

        $autoJsDevice1 = new AutoJsDevice();
        $autoJsDevice1->setBaseDevice($baseDevice1);
        $autoJsDevice1->setAutoJsVersion('4.1.1 Alpha2');
        $autoJsDevice1->setCertificate('-----BEGIN CERTIFICATE-----\nTest Certificate Data\n-----END CERTIFICATE-----');
        $autoJsDevice1->setWsConnectionId('ws_test_connection_001');
        $manager->persist($autoJsDevice1);
        $this->addReference(self::REFERENCE_DEVICE_1, $autoJsDevice1);

        // 创建基础设备 2
        $baseDevice2 = new Device();
        $baseDevice2->setCode('AUTO_JS_TEST_002');
        $baseDevice2->setName('测试Auto.js设备2');
        $baseDevice2->setModel('Android Test Device 2');
        $baseDevice2->setDeviceType(DeviceType::PHONE);
        $baseDevice2->setStatus(DeviceStatus::OFFLINE);
        $baseDevice2->setBrand('TestBrand');
        $baseDevice2->setOsVersion('Android 11');
        $baseDevice2->setCpuCores(6);
        $baseDevice2->setMemorySize('6GB');
        $baseDevice2->setStorageSize('64GB');
        $baseDevice2->setFingerprint('test_fingerprint_002');
        $baseDevice2->setLastIp('192.168.1.101');
        $baseDevice2->setRegIp('192.168.1.101');
        $baseDevice2->setValid(true);
        $manager->persist($baseDevice2);

        $autoJsDevice2 = new AutoJsDevice();
        $autoJsDevice2->setBaseDevice($baseDevice2);
        $autoJsDevice2->setAutoJsVersion('4.1.0');
        $autoJsDevice2->setCertificate('-----BEGIN CERTIFICATE-----\nTest Certificate Data 2\n-----END CERTIFICATE-----');
        $autoJsDevice2->setWsConnectionId('ws_test_connection_002');
        $manager->persist($autoJsDevice2);
        $this->addReference(self::REFERENCE_DEVICE_2, $autoJsDevice2);

        $manager->flush();
    }
}
