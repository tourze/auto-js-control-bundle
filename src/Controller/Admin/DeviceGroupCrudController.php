<?php

declare(strict_types=1);

namespace Tourze\AutoJsControlBundle\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminCrud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Tourze\AutoJsControlBundle\Entity\DeviceGroup;

/**
 * @extends AbstractCrudController<DeviceGroup>
 */
#[AdminCrud]
final class DeviceGroupCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return DeviceGroup::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('设备组')
            ->setEntityLabelInPlural('设备组管理')
            ->setDefaultSort(['createTime' => 'DESC'])
            ->setPageTitle('index', '设备组管理')
            ->setPageTitle('new', '创建设备组')
            ->setPageTitle('edit', '编辑设备组')
            ->setPageTitle('detail', '设备组详情')
            ->setPaginatorPageSize(20)
        ;
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->setPermission(Action::DELETE, 'ROLE_ADMIN')
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('name', '组名称')
            ->setHelp('设备组的名称')
            ->setColumns(6)
        ;

        yield TextField::new('code', '组代码')
            ->setHelp('设备组的唯一标识符')
            ->setColumns(6)
        ;

        yield TextareaField::new('description', '组描述')
            ->setHelp('详细描述设备组的用途')
            ->hideOnIndex()
            ->setNumOfRows(4)
        ;

        yield BooleanField::new('active', '是否激活')
            ->setHelp('控制设备组是否可用')
        ;

        yield IntegerField::new('maxDevices', '最大设备数')
            ->setHelp('组内最多允许的设备数量')
            ->setColumns(6)
        ;

        yield IntegerField::new('priority', '优先级')
            ->setHelp('设备组的优先级，数值越大优先级越高')
            ->setColumns(6)
        ;

        // 统计信息（虚拟字段）
        yield IntegerField::new('deviceCount', '设备数量')
            ->setVirtual(true)
            ->setHelp('当前组内的设备数量')
            ->formatValue(function ($value, DeviceGroup $entity) {
                return $entity->getAutoJsDevices()->count();
            })
        ;

        yield IntegerField::new('activeDeviceCount', '在线设备数')
            ->setVirtual(true)
            ->setHelp('当前组内在线的设备数量')
            ->onlyOnDetail()
            ->formatValue(function ($value, DeviceGroup $entity) {
                $count = 0;
                foreach ($entity->getAutoJsDevices() as $device) {
                    if ('online' === $device->getStatus()->value) {
                        ++$count;
                    }
                }

                return $count;
            })
        ;

        // TODO: 需要在Task实体中添加反向关系或使用Repository查询
        // yield IntegerField::new('taskCount', '关联任务数')
        //     ->setVirtual(true)
        //     ->setHelp('使用此设备组的任务数量')
        //     ->onlyOnDetail()
        //     ->formatValue(function ($value, DeviceGroup $entity) {
        //         return $entity->getTasks()->count();
        //     })
        // ;

        // 系统字段
        yield TextField::new('createdBy', '创建者')
            ->onlyOnDetail()
            ->setColumns(6)
        ;

        yield TextField::new('updatedBy', '更新者')
            ->onlyOnDetail()
            ->setColumns(6)
        ;

        yield DateTimeField::new('createTime', '创建时间')
            ->setFormat('yyyy-MM-dd HH:mm:ss')
            ->onlyOnDetail()
        ;

        yield DateTimeField::new('updateTime', '更新时间')
            ->setFormat('yyyy-MM-dd HH:mm:ss')
            ->onlyOnDetail()
        ;

        // 使用率统计
        yield TextField::new('utilizationRate', '使用率')
            ->setVirtual(true)
            ->setHelp('设备组的使用率')
            ->onlyOnDetail()
            ->formatValue(function ($value, DeviceGroup $entity) {
                $totalDevices = $entity->getAutoJsDevices()->count();
                if (0 === $totalDevices) {
                    return '0%';
                }

                $activeDevices = 0;
                foreach ($entity->getAutoJsDevices() as $device) {
                    if ('online' === $device->getStatus()->value) {
                        ++$activeDevices;
                    }
                }

                $rate = round(($activeDevices / $totalDevices) * 100, 1);

                return $rate . '%';
            })
        ;
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add('name')
            ->add('description')
            ->add('createdAt')
            ->add('updatedAt')
        ;
    }
}
