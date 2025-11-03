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
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Tourze\AutoJsControlBundle\Entity\DeviceMonitorData;

/**
 * 设备监控数据CRUD控制器.
 *
 * @extends AbstractCrudController<DeviceMonitorData>
 */
#[AdminCrud(routePath: '/auto-js/device-monitor-data', routeName: 'auto_js_device_monitor_data')]
final class DeviceMonitorDataCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return DeviceMonitorData::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('设备监控数据')
            ->setEntityLabelInPlural('设备监控数据')
            ->setDefaultSort(['createTime' => 'DESC'])
            ->setPageTitle(Crud::PAGE_INDEX, '设备监控数据管理')
            ->setPageTitle(Crud::PAGE_NEW, '创建设备监控数据')
            ->setPageTitle(Crud::PAGE_EDIT, '编辑设备监控数据')
            ->setPageTitle(Crud::PAGE_DETAIL, '查看设备监控数据')
            ->showEntityActionsInlined()
            ->setPaginatorPageSize(50)
            ->setTimezone('Asia/Shanghai')
        ;
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->update(Crud::PAGE_INDEX, Action::NEW, function (Action $action) {
                return $action->setIcon('fa fa-plus')->setLabel('添加监控数据');
            })
            ->update(Crud::PAGE_INDEX, Action::EDIT, function (Action $action) {
                return $action->setIcon('fa fa-edit')->setLabel('编辑');
            })
            ->update(Crud::PAGE_INDEX, Action::DELETE, function (Action $action) {
                return $action->setIcon('fa fa-trash')->setLabel('删除');
            })
            ->update(Crud::PAGE_INDEX, Action::DETAIL, function (Action $action) {
                return $action->setIcon('fa fa-eye')->setLabel('查看详情');
            })
        ;
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id', 'ID')
            ->hideOnForm()
            ->setTextAlign('center')
        ;

        yield AssociationField::new('autoJsDevice', '设备')
            ->setRequired(true)
            ->autocomplete()
            ->formatValue(function ($value, DeviceMonitorData $entity): string {
                return $entity->getAutoJsDevice()?->getName() ?? '无';
            })
            ->setHelp('选择对应的设备')
        ;

        yield NumberField::new('cpuUsage', 'CPU使用率(%)')
            ->setNumDecimals(2)
            ->setColumns(3)
            ->setHelp('CPU使用率，0-100%')
        ;

        yield TextField::new('memoryUsed', '内存使用量(MB)')
            ->setColumns(3)
            ->setHelp('当前内存使用量，单位MB')
        ;

        yield TextField::new('memoryTotal', '内存总量(MB)')
            ->setColumns(3)
            ->setHelp('设备内存总量，单位MB')
        ;

        yield TextField::new('storageUsed', '存储使用量(MB)')
            ->setColumns(3)
            ->setHelp('当前存储使用量，单位MB')
        ;

        yield TextField::new('storageTotal', '存储总量(MB)')
            ->setColumns(3)
            ->setHelp('设备存储总量，单位MB')
        ;

        yield NumberField::new('batteryLevel', '电池电量(%)')
            ->setNumDecimals(1)
            ->setColumns(3)
            ->setHelp('当前电池电量，0-100%')
        ;

        yield BooleanField::new('isCharging', '是否充电中')
            ->setColumns(2)
            ->setHelp('设备是否正在充电')
        ;

        yield NumberField::new('temperature', '设备温度(°C)')
            ->setNumDecimals(1)
            ->setColumns(3)
            ->setHelp('设备当前温度，单位摄氏度')
        ;

        yield IntegerField::new('networkLatency', '网络延迟(ms)')
            ->setColumns(3)
            ->setHelp('网络延迟，单位毫秒')
        ;

        yield TextField::new('networkType', '网络类型')
            ->setColumns(3)
            ->setHelp('网络连接类型：wifi/4g/5g/ethernet等')
        ;

        yield IntegerField::new('runningScripts', '运行脚本数')
            ->setColumns(3)
            ->setHelp('当前正在运行的脚本数量')
        ;

        yield TextareaField::new('extraData', '扩展数据')
            ->setColumns(6)
            ->hideOnIndex()
            ->setHelp('扩展监控数据，JSON格式')
        ;

        yield DateTimeField::new('createTime', '监控时间')
            ->setFormat('yyyy-MM-dd HH:mm:ss')
            ->hideOnForm()
            ->setColumns(4)
            ->setHelp('数据记录时间')
        ;
    }
}
