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
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use Tourze\AutoJsControlBundle\Entity\WebSocketMessage;

/**
 * WebSocket消息CRUD控制器.
 *
 * @extends AbstractCrudController<WebSocketMessage>
 */
#[AdminCrud]
final class WebSocketMessageCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return WebSocketMessage::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('WebSocket消息')
            ->setEntityLabelInPlural('WebSocket消息')
            ->setDefaultSort(['createTime' => 'DESC'])
            ->setPageTitle(Crud::PAGE_INDEX, 'WebSocket消息管理')
            ->setPageTitle(Crud::PAGE_NEW, '创建WebSocket消息')
            ->setPageTitle(Crud::PAGE_EDIT, '编辑WebSocket消息')
            ->setPageTitle(Crud::PAGE_DETAIL, '查看WebSocket消息')
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
                return $action->setIcon('fa fa-plus')->setLabel('添加消息');
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
        return $filters
            ->add(EntityFilter::new('autoJsDevice', '设备'))
            ->add(ChoiceFilter::new('messageType', '消息类型')
                ->setChoices([
                    '注册' => 'register',
                    '心跳' => 'heartbeat',
                    '命令' => 'command',
                    '响应' => 'response',
                    '日志' => 'log',
                    '监控' => 'monitor',
                ]))
            ->add(ChoiceFilter::new('direction', '消息方向')
                ->setChoices([
                    '输入' => 'in',
                    '输出' => 'out',
                ]))
            ->add(BooleanFilter::new('isProcessed', '是否已处理'))
            ->add(ChoiceFilter::new('processStatus', '处理状态')
                ->setChoices([
                    '成功' => 'success',
                    '失败' => 'failed',
                    '超时' => 'timeout',
                ]))
            ->add(DateTimeFilter::new('createTime', '创建时间'))
            ->add(DateTimeFilter::new('processTime', '处理时间'))
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id', 'ID')
            ->hideOnForm()
            ->setTextAlign('center')
        ;

        yield AssociationField::new('autoJsDevice', '设备')
            ->autocomplete()
            ->formatValue(function ($value, WebSocketMessage $entity): string {
                return $entity->getAutoJsDevice()?->getName() ?? '无设备';
            })
            ->setHelp('选择对应的设备，可选')
        ;

        yield TextField::new('messageId', '消息ID')
            ->setRequired(true)
            ->setColumns(4)
            ->setMaxLength(64)
            ->setHelp('消息的唯一标识')
        ;

        yield ChoiceField::new('messageType', '消息类型')
            ->setChoices([
                '注册' => 'register',
                '心跳' => 'heartbeat',
                '命令' => 'command',
                '响应' => 'response',
                '日志' => 'log',
                '监控' => 'monitor',
            ])
            ->setRequired(true)
            ->setColumns(2)
            ->renderAsBadges([
                'register' => 'info',
                'heartbeat' => 'secondary',
                'command' => 'warning',
                'response' => 'success',
                'log' => 'primary',
                'monitor' => 'dark',
            ])
            ->setHelp('消息类型')
        ;

        yield ChoiceField::new('direction', '消息方向')
            ->setChoices([
                '输入' => 'in',
                '输出' => 'out',
            ])
            ->setRequired(true)
            ->setColumns(2)
            ->renderAsBadges([
                'in' => 'success',
                'out' => 'primary',
            ])
            ->setHelp('消息方向')
        ;

        yield TextareaField::new('content', '消息内容')
            ->setRequired(true)
            ->setColumns(6)
            ->hideOnIndex()
            ->setHelp('JSON格式的消息内容')
        ;

        yield BooleanField::new('isProcessed', '是否已处理')
            ->setColumns(2)
            ->setHelp('消息是否已经处理')
        ;

        yield ChoiceField::new('processStatus', '处理状态')
            ->setChoices([
                '成功' => 'success',
                '失败' => 'failed',
                '超时' => 'timeout',
            ])
            ->setColumns(2)
            ->renderAsBadges([
                'success' => 'success',
                'failed' => 'danger',
                'timeout' => 'warning',
            ])
            ->setHelp('消息处理状态')
        ;

        yield TextareaField::new('processResult', '处理结果')
            ->setColumns(6)
            ->hideOnIndex()
            ->setHelp('消息处理的结果')
        ;

        yield DateTimeField::new('processTime', '处理时间')
            ->setFormat('yyyy-MM-dd HH:mm:ss')
            ->setColumns(4)
            ->setHelp('消息处理时间')
        ;

        yield DateTimeField::new('createTime', '创建时间')
            ->setFormat('yyyy-MM-dd HH:mm:ss')
            ->hideOnForm()
            ->setColumns(4)
            ->setHelp('消息创建时间')
        ;
    }
}
