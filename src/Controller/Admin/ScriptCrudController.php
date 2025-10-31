<?php

declare(strict_types=1);

namespace Tourze\AutoJsControlBundle\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminCrud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CodeEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Tourze\AutoJsControlBundle\Entity\Script;
use Tourze\AutoJsControlBundle\Enum\ScriptStatus;
use Tourze\AutoJsControlBundle\Enum\ScriptType;
use Tourze\EasyAdminEnumFieldBundle\Field\EnumField;

/**
 * @extends AbstractCrudController<Script>
 */
#[AdminCrud]
final class ScriptCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Script::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('脚本')
            ->setEntityLabelInPlural('脚本管理')
            ->setDefaultSort(['priority' => 'DESC', 'createTime' => 'DESC'])
            ->setPageTitle('index', '脚本管理')
            ->setPageTitle('new', '创建脚本')
            ->setPageTitle('edit', '编辑脚本')
            ->setPageTitle('detail', '脚本详情')
            ->setPaginatorPageSize(20)
        ;
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->setPermission(Action::NEW, 'ROLE_ADMIN')
            ->setPermission(Action::DELETE, 'ROLE_ADMIN')
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('code', '脚本编码')
            ->setHelp('脚本的唯一标识符')
            ->setColumns(6)
        ;

        yield TextField::new('name', '脚本名称')
            ->setHelp('脚本的显示名称')
            ->setColumns(6)
        ;

        yield TextareaField::new('description', '脚本描述')
            ->setHelp('详细描述脚本的用途和功能')
            ->hideOnIndex()
        ;

        $scriptTypeField = EnumField::new('scriptType', '脚本类型');
        $scriptTypeField->setEnumCases(ScriptType::cases());
        $scriptTypeField->setHelp('选择脚本的类型');
        $scriptTypeField->setColumns(6);
        yield $scriptTypeField;

        $statusField = EnumField::new('status', '脚本状态');
        $statusField->setEnumCases(ScriptStatus::cases());
        $statusField->setHelp('脚本当前的状态');
        $statusField->setColumns(6);
        yield $statusField;

        yield IntegerField::new('version', '版本号')
            ->setHelp('脚本的版本号，每次更新后递增')
            ->setColumns(3)
        ;

        yield IntegerField::new('priority', '优先级')
            ->setHelp('数值越大优先级越高')
            ->setColumns(3)
        ;

        yield IntegerField::new('timeout', '超时时间(秒)')
            ->setHelp('脚本执行的最大时长')
            ->setColumns(3)
        ;

        yield IntegerField::new('maxRetries', '最大重试次数')
            ->setHelp('执行失败时的重试次数')
            ->setColumns(3)
        ;

        yield BooleanField::new('valid', '是否启用')
            ->setHelp('控制脚本是否可用')
        ;

        yield TextField::new('projectPath', '项目路径')
            ->setHelp('Auto.js项目文件的路径')
            ->hideOnIndex()
            ->setColumns(6)
        ;

        yield CodeEditorField::new('content', '脚本内容')
            ->setLanguage('javascript')
            ->setHelp('JavaScript代码内容')
            ->hideOnIndex()
            ->setNumOfRows(15)
        ;

        yield CodeEditorField::new('parameters', '参数定义')
            ->setLanguage('javascript')
            ->setHelp('JSON格式的参数定义')
            ->hideOnIndex()
            ->setNumOfRows(8)
        ;

        yield CodeEditorField::new('securityRules', '安全规则')
            ->setLanguage('javascript')
            ->setHelp('JSON格式的安全校验规则')
            ->hideOnIndex()
            ->setNumOfRows(8)
        ;

        yield ArrayField::new('tags', '标签')
            ->setHelp('用于分类和搜索的标签')
            ->hideOnIndex()
        ;

        yield TextField::new('checksum', '校验和')
            ->setHelp('内容的MD5校验和')
            ->onlyOnDetail()
            ->setColumns(6)
        ;

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

        // 统计信息字段（只在详情页显示）
        yield IntegerField::new('tasksCount', '任务数量')
            ->setVirtual(true)
            ->setHelp('使用此脚本的任务数量')
            ->onlyOnDetail()
            ->formatValue(function ($value, Script $entity) {
                return $entity->getTasks()->count();
            })
        ;

        yield IntegerField::new('executionsCount', '执行次数')
            ->setVirtual(true)
            ->setHelp('脚本的总执行次数')
            ->onlyOnDetail()
            ->formatValue(function ($value, Script $entity) {
                return $entity->getExecutionRecords()->count();
            })
        ;
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add('name')
            ->add('scriptType')
            ->add('status')
            ->add('valid')
            ->add('createTime')
            ->add('updateTime')
        ;
    }
}
