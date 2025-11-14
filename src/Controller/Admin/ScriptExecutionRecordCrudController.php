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
use EasyCorp\Bundle\EasyAdminBundle\Field\CodeEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Tourze\AutoJsControlBundle\Entity\ScriptExecutionRecord;
use Tourze\AutoJsControlBundle\Enum\ExecutionStatus;
use Tourze\EasyAdminEnumFieldBundle\Field\EnumField;

/**
 * @extends AbstractCrudController<ScriptExecutionRecord>
 */
#[AdminCrud(routePath: '/auto-js/script-execution-record', routeName: 'auto_js_script_execution_record')]
final class ScriptExecutionRecordCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return ScriptExecutionRecord::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('脚本执行记录')
            ->setEntityLabelInPlural('脚本执行记录')
            ->setDefaultSort(['startTime' => 'DESC'])
            ->setPageTitle('index', '脚本执行记录')
            ->setPageTitle('detail', '执行记录详情')
            ->setPaginatorPageSize(30)
        ;
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->remove(Crud::PAGE_INDEX, Action::NEW)
            ->remove(Crud::PAGE_INDEX, Action::EDIT)
            ->setPermission(Action::DELETE, 'ROLE_ADMIN')
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        yield AssociationField::new('script', '脚本')
            ->setHelp('执行的脚本')
            ->setColumns(4)
        ;

        yield AssociationField::new('task', '任务')
            ->setHelp('关联的任务')
            ->setColumns(4)
        ;

        yield AssociationField::new('autoJsDevice', '执行设备')
            ->setHelp('执行脚本的Auto.js设备')
            ->setColumns(4)
        ;

        $statusField = EnumField::new('status', '执行状态');
        $statusField->setEnumCases(ExecutionStatus::cases());
        $statusField->setHelp('脚本执行的状态');
        yield $statusField;

        yield IntegerField::new('exitCode', '退出代码')
            ->setHelp('脚本执行的退出代码，0表示成功')
            ->setColumns(6)
        ;

        yield IntegerField::new('retryCount', '重试次数')
            ->setHelp('脚本执行的重试次数')
            ->setColumns(6)
        ;

        yield DateTimeField::new('startTime', '开始时间')
            ->setFormat('yyyy-MM-dd HH:mm:ss')
            ->setHelp('脚本开始执行的时间')
        ;

        yield DateTimeField::new('endTime', '结束时间')
            ->setFormat('yyyy-MM-dd HH:mm:ss')
            ->setHelp('脚本执行完成的时间')
            ->hideOnIndex()
        ;

        // 虚拟字段 - 执行时长
        yield TextField::new('executionDuration', '执行时长')
            ->setVirtual(true)
            ->setHelp('脚本执行耗时')
            ->formatValue(fn ($value, ScriptExecutionRecord $entity) => $this->formatExecutionDuration($entity))
        ;

        yield CodeEditorField::new('parameters', '执行参数')
            ->setLanguage('javascript')
            ->setHelp('脚本执行时的参数')
            ->hideOnIndex()
            ->setNumOfRows(8)
        ;

        yield TextareaField::new('output', '执行输出')
            ->setHelp('脚本执行的标准输出')
            ->hideOnIndex()
            ->setNumOfRows(10)
        ;

        yield TextareaField::new('errorOutput', '错误输出')
            ->setHelp('脚本执行的错误输出')
            ->hideOnIndex()
            ->setNumOfRows(8)
        ;

        yield TextField::new('errorMessage', '错误信息')
            ->setHelp('执行失败的错误信息')
            ->hideOnIndex()
            ->setColumns(6)
        ;

        yield TextField::new('executionId', '执行ID')
            ->setHelp('唯一的执行标识符')
            ->hideOnIndex()
            ->setColumns(6)
        ;

        yield CodeEditorField::new('resultData', '结果数据')
            ->setLanguage('javascript')
            ->setHelp('脚本执行的结果数据')
            ->hideOnIndex()
            ->setNumOfRows(8)
        ;

        yield CodeEditorField::new('deviceInfo', '设备信息')
            ->setLanguage('javascript')
            ->setHelp('执行时的设备状态信息')
            ->hideOnIndex()
            ->setNumOfRows(6)
        ;

        yield TextField::new('scriptVersion', '脚本版本')
            ->setHelp('执行时的脚本版本')
            ->hideOnIndex()
            ->setColumns(6)
        ;

        yield TextField::new('executionEnvironment', '执行环境')
            ->setHelp('脚本执行环境信息')
            ->hideOnIndex()
            ->setColumns(6)
        ;

        // 性能指标
        yield IntegerField::new('memoryUsage', '内存使用量')
            ->setHelp('执行时的内存使用量(KB)')
            ->onlyOnDetail()
        ;

        yield TextField::new('cpuUsage', 'CPU使用率')
            ->setHelp('执行时的CPU使用率')
            ->onlyOnDetail()
        ;

        // 系统字段
        yield DateTimeField::new('createTime', '记录创建时间')
            ->setFormat('yyyy-MM-dd HH:mm:ss')
            ->onlyOnDetail()
        ;

        yield DateTimeField::new('updateTime', '记录更新时间')
            ->setFormat('yyyy-MM-dd HH:mm:ss')
            ->onlyOnDetail()
        ;

        // 虚拟字段 - 成功率统计（同一脚本）
        yield TextField::new('scriptSuccessRate', '脚本成功率')
            ->setVirtual(true)
            ->setHelp('该脚本的历史成功率')
            ->onlyOnDetail()
            ->formatValue(fn ($value, ScriptExecutionRecord $entity) => $this->formatScriptSuccessRate($entity))
        ;
    }

    private function formatExecutionDuration(ScriptExecutionRecord $entity): string
    {
        $start = $entity->getStartTime();
        $end = $entity->getEndTime();

        if (null === $start) {
            return '未开始';
        }

        if (null === $end) {
            if ('running' === $entity->getStatus()->value) {
                $duration = new \DateTimeImmutable()->getTimestamp() - $start->getTimestamp();

                return $duration . '秒 (运行中)';
            }

            return '未完成';
        }

        $duration = $end->getTimestamp() - $start->getTimestamp();

        return $duration . '秒';
    }

    private function formatScriptSuccessRate(ScriptExecutionRecord $entity): string
    {
        $script = $entity->getScript();
        if (null === $script) {
            return '未知';
        }

        $totalRecords = $script->getExecutionRecords()->count();
        if (0 === $totalRecords) {
            return '0%';
        }

        $successCount = 0;
        foreach ($script->getExecutionRecords() as $record) {
            if ('success' === $record->getStatus()->value) {
                ++$successCount;
            }
        }

        $rate = round(($successCount / $totalRecords) * 100, 1);

        return $rate . '%';
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add('script')
            ->add('task')
            ->add('status')
            ->add('startTime')
            ->add('endTime')
            ->add('createdAt')
        ;
    }
}
