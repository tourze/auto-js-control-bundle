<?php

namespace Tourze\AutoJsControlBundle\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Tourze\AutoJsControlBundle\Entity\Script;
use Tourze\AutoJsControlBundle\Enum\ScriptStatus;
use Tourze\AutoJsControlBundle\Enum\ScriptType;

class ScriptFixtures extends Fixture
{
    public const REFERENCE_SCRIPT_1 = 'script-1';
    public const REFERENCE_SCRIPT_2 = 'script-2';

    public function load(ObjectManager $manager): void
    {
        $script1 = new Script();
        $script1->setCode('test_automation_001');
        $script1->setName('测试自动化脚本1');
        $script1->setDescription('用于测试的自动化脚本');
        $script1->setScriptType(ScriptType::AUTO_JS);
        $script1->setStatus(ScriptStatus::ACTIVE);
        $script1->setContent('console.log("Hello from test script 1");');
        $script1->setVersion(1);
        // Script entity has no setAuthor method
        $script1->setTags(['测试', '自动化']);
        $script1->setValid(true);
        $manager->persist($script1);
        $this->addReference(self::REFERENCE_SCRIPT_1, $script1);

        $script2 = new Script();
        $script2->setCode('test_monitoring_001');
        $script2->setName('测试监控脚本1');
        $script2->setDescription('用于设备监控的测试脚本');
        $script2->setScriptType(ScriptType::JAVASCRIPT);
        $script2->setStatus(ScriptStatus::ACTIVE);
        $script2->setContent('console.log("Monitoring script running");');
        $script2->setVersion(1);
        // Script entity has no setAuthor method
        $script2->setTags(['测试', '监控']);
        $script2->setValid(true);
        $manager->persist($script2);
        $this->addReference(self::REFERENCE_SCRIPT_2, $script2);

        $script3 = new Script();
        $script3->setCode('test_disabled_001');
        $script3->setName('已停用脚本');
        $script3->setDescription('已停用的测试脚本');
        $script3->setScriptType(ScriptType::AUTO_JS);
        $script3->setStatus(ScriptStatus::INACTIVE);
        $script3->setContent('console.log("Disabled script");');
        $script3->setVersion(1);
        // Script entity has no setAuthor method
        $script3->setTags(['测试']);
        $script3->setValid(false);
        $manager->persist($script3);

        $manager->flush();
    }
}
