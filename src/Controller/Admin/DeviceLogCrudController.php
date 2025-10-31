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
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use Tourze\AutoJsControlBundle\Entity\DeviceLog;
use Tourze\AutoJsControlBundle\Enum\LogLevel;
use Tourze\AutoJsControlBundle\Enum\LogType;
use Tourze\EasyAdminEnumFieldBundle\Field\EnumField;

/**
 * 设备日志CRUD控制器.
 *
 * @extends AbstractCrudController<DeviceLog>
 */
#[AdminCrud(routePath: '/auto-js/device-logs', routeName: 'auto_js_device_logs')]
final class DeviceLogCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return DeviceLog::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('设备日志')
            ->setEntityLabelInPlural('设备日志')
            ->setDefaultSort(['createTime' => 'DESC'])
            ->setPageTitle(Crud::PAGE_INDEX, '设备日志管理')
            ->setPageTitle(Crud::PAGE_NEW, '创建设备日志')
            ->setPageTitle(Crud::PAGE_EDIT, '编辑设备日志')
            ->setPageTitle(Crud::PAGE_DETAIL, '查看设备日志')
            ->showEntityActionsInlined()
            ->setPaginatorPageSize(50)
            ->setTimezone('Asia/Shanghai')
        ;
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->disable(Action::NEW, Action::EDIT, Action::DELETE)
            ->update(Crud::PAGE_INDEX, Action::DETAIL, function (Action $action) {
                return $action->setIcon('fa fa-eye')->setLabel('查看详情');
            })
        ;
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(EntityFilter::new('autoJsDevice', '设备'))
            ->add(ChoiceFilter::new('logLevel', '日志级别')
                ->setChoices([
                    '调试' => LogLevel::DEBUG->value,
                    '信息' => LogLevel::INFO->value,
                    '警告' => LogLevel::WARNING->value,
                    '错误' => LogLevel::ERROR->value,
                    '严重' => LogLevel::CRITICAL->value,
                ]))
            ->add(ChoiceFilter::new('logType', '日志类型')
                ->setChoices([
                    '系统日志' => LogType::SYSTEM->value,
                    '脚本执行' => LogType::SCRIPT->value,
                    '连接日志' => LogType::CONNECTION->value,
                    '命令执行' => LogType::COMMAND->value,
                    '任务日志' => LogType::TASK->value,
                ]))
            ->add(DateTimeFilter::new('createTime', '创建时间'))
            ->add(DateTimeFilter::new('logTime', '日志时间'))
        ;
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
            ->formatValue(function ($value, DeviceLog $entity): string {
                return $entity->getAutoJsDevice()?->getName() ?? '无';
            })
            ->setHelp('选择对应的设备')
        ;

        $logLevelField = EnumField::new('logLevel', '日志级别');
        $logLevelField->setEnumCases(LogLevel::cases());
        $logLevelField->setRequired(true);
        $logLevelField->setColumns(2);
        $logLevelField->setHelp('日志级别');
        yield $logLevelField;

        $logTypeField = EnumField::new('logType', '日志类型');
        $logTypeField->setEnumCases(LogType::cases());
        $logTypeField->setRequired(true);
        $logTypeField->setColumns(2);
        $logTypeField->setHelp('日志类型');
        yield $logTypeField;

        yield TextField::new('title', '日志标题')
            ->setRequired(true)
            ->setColumns(6)
            ->setMaxLength(500)
            ->setHelp('简要描述日志内容')
        ;

        yield TextareaField::new('content', '日志内容')
            ->setColumns(6)
            ->hideOnIndex()
            ->setHelp('详细日志内容')
        ;

        yield TextareaField::new('message', '日志消息')
            ->setColumns(6)
            ->hideOnIndex()
            ->setHelp('日志消息内容')
        ;

        yield TextField::new('deviceIp', '设备IP')
            ->setColumns(3)
            ->setMaxLength(45)
            ->setHelp('设备IP地址')
        ;

        yield TextareaField::new('context', '上下文信息')
            ->setColumns(6)
            ->hideOnIndex()
            ->setHelp('上下文信息，JSON格式')
        ;

        yield TextareaField::new('stackTrace', '堆栈跟踪')
            ->setColumns(6)
            ->hideOnIndex()
            ->setHelp('错误堆栈跟踪信息')
        ;

        yield DateTimeField::new('logTime', '日志时间')
            ->setFormat('yyyy-MM-dd HH:mm:ss')
            ->setColumns(4)
            ->setHelp('日志产生的时间')
        ;

        yield DateTimeField::new('createTime', '创建时间')
            ->setFormat('yyyy-MM-dd HH:mm:ss')
            ->hideOnForm()
            ->setColumns(4)
            ->setHelp('记录创建时间')
        ;
    }
}
