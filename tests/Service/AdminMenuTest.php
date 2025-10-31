<?php

declare(strict_types=1);

namespace Tourze\AutoJsControlBundle\Tests\Service;

use Knp\Menu\MenuFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\AutoJsControlBundle\Service\AdminMenu;
use Tourze\EasyAdminMenuBundle\Service\MenuProviderInterface;
use Tourze\PHPUnitSymfonyWebTest\AbstractEasyAdminMenuTestCase;

/**
 * @internal
 */
#[CoversClass(AdminMenu::class)]
#[RunTestsInSeparateProcesses]
final class AdminMenuTest extends AbstractEasyAdminMenuTestCase
{
    protected function onSetUp(): void
    {
        // AdminMenu测试的基础设置 - 无需特殊配置
    }

    public function testServiceCreation(): void
    {
        /** @var AdminMenu $adminMenu */
        $adminMenu = self::getContainer()->get(AdminMenu::class);
        $this->assertInstanceOf(AdminMenu::class, $adminMenu);
    }

    public function testImplementsMenuProviderInterface(): void
    {
        /** @var AdminMenu $adminMenu */
        $adminMenu = self::getContainer()->get(AdminMenu::class);
        $this->assertInstanceOf(MenuProviderInterface::class, $adminMenu);
    }

    public function testInvokeShouldBeCallable(): void
    {
        $reflection = new \ReflectionClass(AdminMenu::class);
        $this->assertTrue($reflection->hasMethod('__invoke'));
    }

    public function testInvokeCreatesNewMenu(): void
    {
        // 创建真实的菜单对象进行集成测试
        $menuFactory = new MenuFactory();
        $menu = $menuFactory->createItem('main');

        // 从容器获取 AdminMenu 服务
        /** @var AdminMenu $adminMenu */
        $adminMenu = self::getContainer()->get(AdminMenu::class);

        // 执行菜单创建
        $adminMenu($menu);

        // 验证主菜单创建
        $autoJsMenu = $menu->getChild('AutoJS管理');
        $this->assertNotNull($autoJsMenu, 'AutoJS管理菜单应该被创建');

        // 验证子菜单存在
        $expectedMenus = [
            '设备管理' => 'fas fa-mobile-alt',
            '设备分组' => 'fas fa-layer-group',
            '脚本管理' => 'fas fa-code',
            '任务管理' => 'fas fa-tasks',
            '执行记录' => 'fas fa-history',
            '设备监控' => 'fas fa-chart-line',
            '设备日志' => 'fas fa-file-alt',
            'WebSocket消息' => 'fas fa-comments',
        ];

        foreach ($expectedMenus as $menuName => $expectedIcon) {
            $childMenu = $autoJsMenu->getChild($menuName);
            $this->assertNotNull($childMenu, sprintf('子菜单 "%s" 应该被创建', $menuName));

            // 验证URI不为空（具体URL由LinkGenerator生成，我们只验证存在性）
            $this->assertNotEmpty($childMenu->getUri(), sprintf('菜单 "%s" 应该有URI', $menuName));

            // 验证图标设置
            $this->assertEquals($expectedIcon, $childMenu->getAttribute('icon'), sprintf('菜单 "%s" 应该有正确的图标', $menuName));
        }
    }

    public function testInvokeUsesExistingMenu(): void
    {
        // 创建真实的菜单对象，并先添加主菜单
        $menuFactory = new MenuFactory();
        $menu = $menuFactory->createItem('main');
        $menu->addChild('AutoJS管理');

        // 从容器获取 AdminMenu 服务
        /** @var AdminMenu $adminMenu */
        $adminMenu = self::getContainer()->get(AdminMenu::class);

        // 执行菜单创建（第二次，应该使用已有菜单）
        $adminMenu($menu);

        // 验证主菜单仍然存在
        $autoJsMenu = $menu->getChild('AutoJS管理');
        $this->assertNotNull($autoJsMenu, 'AutoJS管理菜单应该继续存在');

        // 验证子菜单被正确添加（即使主菜单已存在）
        $expectedMenus = [
            '设备管理', '设备分组', '脚本管理', '任务管理',
            '执行记录', '设备监控', '设备日志', 'WebSocket消息',
        ];

        foreach ($expectedMenus as $menuName) {
            $childMenu = $autoJsMenu->getChild($menuName);
            $this->assertNotNull($childMenu, sprintf('子菜单 "%s" 应该被添加到已存在的主菜单', $menuName));
        }
    }

    public function testInvokeHandlesEdgeCases(): void
    {
        // 测试边缘情况：创建一个特殊菜单对象来验证逻辑路径
        $menuFactory = new MenuFactory();
        $menu = $menuFactory->createItem('main');

        // 先添加一个空的子菜单，然后获取AdminMenu服务
        $menu->addChild('AutoJS管理');

        /** @var AdminMenu $adminMenu */
        $adminMenu = self::getContainer()->get(AdminMenu::class);

        // 调用AdminMenu，应该在现有菜单中添加子项
        $adminMenu($menu);

        // 验证子菜单确实被添加了
        $autoJsMenu = $menu->getChild('AutoJS管理');
        $this->assertNotNull($autoJsMenu, 'AutoJS管理菜单应该存在');

        // 验证至少有一个子菜单被添加（证明没有提前返回）
        $this->assertGreaterThan(0, count($autoJsMenu->getChildren()), '应该有子菜单被添加');
    }
}
