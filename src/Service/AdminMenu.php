<?php

declare(strict_types=1);

namespace Tourze\AutoJsControlBundle\Service;

use Knp\Menu\ItemInterface;
use Tourze\AutoJsControlBundle\Entity\AutoJsDevice;
use Tourze\AutoJsControlBundle\Entity\DeviceGroup;
use Tourze\AutoJsControlBundle\Entity\DeviceLog;
use Tourze\AutoJsControlBundle\Entity\DeviceMonitorData;
use Tourze\AutoJsControlBundle\Entity\Script;
use Tourze\AutoJsControlBundle\Entity\ScriptExecutionRecord;
use Tourze\AutoJsControlBundle\Entity\Task;
use Tourze\AutoJsControlBundle\Entity\WebSocketMessage;
use Tourze\EasyAdminMenuBundle\Service\LinkGeneratorInterface;
use Tourze\EasyAdminMenuBundle\Service\MenuProviderInterface;

/**
 * AutoJS控制后台管理菜单.
 */
readonly class AdminMenu implements MenuProviderInterface
{
    public function __construct(private LinkGeneratorInterface $linkGenerator)
    {
    }

    public function __invoke(ItemInterface $item): void
    {
        if (null === $item->getChild('AutoJS管理')) {
            $item->addChild('AutoJS管理');
        }

        $autoJsMenu = $item->getChild('AutoJS管理');
        if (null === $autoJsMenu) {
            return;
        }

        // 设备管理相关
        $autoJsMenu
            ->addChild('设备管理')
            ->setUri($this->linkGenerator->getCurdListPage(AutoJsDevice::class))
            ->setAttribute('icon', 'fas fa-mobile-alt')
        ;

        $autoJsMenu
            ->addChild('设备分组')
            ->setUri($this->linkGenerator->getCurdListPage(DeviceGroup::class))
            ->setAttribute('icon', 'fas fa-layer-group')
        ;

        // 脚本管理相关
        $autoJsMenu
            ->addChild('脚本管理')
            ->setUri($this->linkGenerator->getCurdListPage(Script::class))
            ->setAttribute('icon', 'fas fa-code')
        ;

        $autoJsMenu
            ->addChild('任务管理')
            ->setUri($this->linkGenerator->getCurdListPage(Task::class))
            ->setAttribute('icon', 'fas fa-tasks')
        ;

        $autoJsMenu
            ->addChild('执行记录')
            ->setUri($this->linkGenerator->getCurdListPage(ScriptExecutionRecord::class))
            ->setAttribute('icon', 'fas fa-history')
        ;

        // 监控与日志
        $autoJsMenu
            ->addChild('设备监控')
            ->setUri($this->linkGenerator->getCurdListPage(DeviceMonitorData::class))
            ->setAttribute('icon', 'fas fa-chart-line')
        ;

        $autoJsMenu
            ->addChild('设备日志')
            ->setUri($this->linkGenerator->getCurdListPage(DeviceLog::class))
            ->setAttribute('icon', 'fas fa-file-alt')
        ;

        $autoJsMenu
            ->addChild('WebSocket消息')
            ->setUri($this->linkGenerator->getCurdListPage(WebSocketMessage::class))
            ->setAttribute('icon', 'fas fa-comments')
        ;
    }
}
