<?php

declare(strict_types=1);

namespace Tourze\AutoJsControlBundle\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminCrud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CodeEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Tourze\AutoJsControlBundle\Entity\AutoJsDevice;

/**
 * @extends AbstractCrudController<AutoJsDevice>
 */
#[AdminCrud(routePath: '/auto-js/device', routeName: 'auto_js_device')]
final class AutoJsDeviceCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return AutoJsDevice::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Auto.js设备')
            ->setEntityLabelInPlural('Auto.js设备管理')
            ->setDefaultSort(['createTime' => 'DESC'])
            ->setPageTitle('index', 'Auto.js设备管理')
            ->setPageTitle('detail', '设备详情')
            ->setPaginatorPageSize(20)
        ;
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->setPermission(Action::NEW, 'ROLE_ADMIN')
            ->setPermission(Action::EDIT, 'ROLE_ADMIN')
            ->setPermission(Action::DELETE, 'ROLE_ADMIN')
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        yield AssociationField::new('baseDevice', '基础设备')
            ->setHelp('关联的基础设备信息')
            ->setColumns(6)
        ;

        yield TextField::new('deviceCode', '设备代码')
            ->setVirtual(true)
            ->setHelp('设备的唯一标识')
            ->setColumns(6)
            ->hideOnForm()
            ->formatValue(function ($value, AutoJsDevice $entity) {
                return $entity->getBaseDevice()?->getCode();
            })
        ;

        yield TextField::new('autoJsVersion', 'Auto.js版本')
            ->setHelp('设备上安装的Auto.js版本')
            ->setColumns(6)
        ;

        yield TextField::new('certificate', '证书信息')
            ->setHelp('设备认证证书')
            ->hideOnIndex()
            ->setColumns(6)
        ;

        yield BooleanField::new('rootAccess', 'Root权限')
            ->setHelp('设备是否具有Root权限')
        ;

        yield BooleanField::new('accessibilityEnabled', '无障碍服务')
            ->setHelp('无障碍服务是否启用')
        ;

        yield BooleanField::new('floatingWindowEnabled', '悬浮窗权限')
            ->setHelp('悬浮窗权限是否开启')
        ;

        yield TextareaField::new('capabilities', '设备能力')
            ->setHelp('设备支持的功能列表')
            ->hideOnIndex()
            ->setNumOfRows(5)
        ;

        yield CodeEditorField::new('configuration', '设备配置')
            ->setLanguage('javascript')
            ->setHelp('设备的JSON配置信息')
            ->hideOnIndex()
            ->setNumOfRows(10)
        ;

        yield IntegerField::new('maxConcurrentTasks', '最大并发任务')
            ->setHelp('设备同时执行的最大任务数')
            ->setColumns(6)
        ;

        yield TextField::new('screenResolution', '屏幕分辨率')
            ->setHelp('设备屏幕分辨率')
            ->setColumns(6)
        ;

        yield TextField::new('androidVersion', 'Android版本')
            ->setHelp('设备Android系统版本')
            ->setColumns(6)
        ;

        yield TextField::new('deviceModel', '设备型号')
            ->setHelp('设备的型号信息')
            ->setColumns(6)
        ;

        // 统计信息
        yield IntegerField::new('totalTasks', '总任务数')
            ->setVirtual(true)
            ->setHelp('设备执行的总任务数')
            ->onlyOnDetail()
            ->formatValue(function ($value, AutoJsDevice $entity) {
                // 这里需要根据实际关联关系调整
                return 0; // TODO: 实现任务统计
            })
        ;

        yield IntegerField::new('successTasks', '成功任务数')
            ->setVirtual(true)
            ->setHelp('设备成功执行的任务数')
            ->onlyOnDetail()
            ->formatValue(function ($value, AutoJsDevice $entity) {
                // 这里需要根据实际关联关系调整
                return 0; // TODO: 实现成功任务统计
            })
        ;

        // 设备状态信息（来自baseDevice）
        yield TextField::new('deviceStatus', '设备状态')
            ->setHelp('设备当前在线状态')
            ->setColumns(6)
            ->hideOnForm()
        ;

        yield DateTimeField::new('lastOnlineTime', '最后在线时间')
            ->setVirtual(true)
            ->setFormat('yyyy-MM-dd HH:mm:ss')
            ->setHelp('设备最后在线的时间')
            ->hideOnForm()
            ->formatValue(function ($value, AutoJsDevice $entity) {
                return $entity->getBaseDevice()?->getLastOnlineTime();
            })
        ;

        // TODO: 需要在BaseDevice中添加getIpAddress()方法
        // yield TextField::new('ipAddress', 'IP地址')
        //     ->setVirtual(true)
        //     ->setHelp('设备的IP地址')
        //     ->setColumns(6)
        //     ->formatValue(function ($value, AutoJsDevice $entity) {
        //         return $entity->getBaseDevice()?->getIpAddress();
        //     })
        // ;

        // 系统字段
        yield DateTimeField::new('createTime', '创建时间')
            ->setFormat('yyyy-MM-dd HH:mm:ss')
            ->onlyOnDetail()
        ;

        yield DateTimeField::new('updateTime', '更新时间')
            ->setFormat('yyyy-MM-dd HH:mm:ss')
            ->onlyOnDetail()
        ;

        // 监控数据统计
        yield IntegerField::new('monitorDataCount', '监控记录数')
            ->setVirtual(true)
            ->setHelp('设备监控数据记录总数')
            ->onlyOnDetail()
            ->formatValue(function ($value, AutoJsDevice $entity) {
                return $entity->getMonitorData()->count();
            })
        ;

        // TODO: 需要在AutoJsDevice中添加与DeviceLog的关联或使用Repository查询
        // yield IntegerField::new('logCount', '日志记录数')
        //     ->setVirtual(true)
        //     ->setHelp('设备日志记录总数')
        //     ->onlyOnDetail()
        //     ->formatValue(function ($value, AutoJsDevice $entity) {
        //         return $entity->getLogs()->count();
        //     });
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add('baseDevice')
            ->add('autoJsVersion')
            ->add('deviceGroup')
            ->add('createTime')
            ->add('updateTime')
        ;
    }
}
