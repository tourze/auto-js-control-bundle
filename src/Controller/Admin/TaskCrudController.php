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
use Tourze\AutoJsControlBundle\Entity\Task;
use Tourze\AutoJsControlBundle\Enum\TaskStatus;
use Tourze\AutoJsControlBundle\Enum\TaskTargetType;
use Tourze\AutoJsControlBundle\Enum\TaskType;
use Tourze\EasyAdminEnumFieldBundle\Field\EnumField;

/**
 * @extends AbstractCrudController<Task>
 */
#[AdminCrud(routePath: '/auto-js/task', routeName: 'auto_js_task')]
final class TaskCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Task::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('任务')
            ->setEntityLabelInPlural('任务管理')
            ->setDefaultSort(['priority' => 'DESC', 'createTime' => 'DESC'])
            ->setPageTitle('index', '任务管理')
            ->setPageTitle('new', '创建任务')
            ->setPageTitle('edit', '编辑任务')
            ->setPageTitle('detail', '任务详情')
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
        yield TextField::new('name', '任务名称')
            ->setHelp('任务的显示名称')
            ->setColumns(6)
        ;

        yield TextareaField::new('description', '任务描述')
            ->setHelp('详细描述任务的用途和功能')
            ->setColumns(6)
            ->hideOnIndex()
        ;

        $taskTypeField = EnumField::new('taskType', '任务类型');
        $taskTypeField->setEnumCases(TaskType::cases());
        $taskTypeField->setHelp('任务执行方式：立即、定时或循环执行');
        $taskTypeField->setColumns(4);
        yield $taskTypeField;

        $statusField = EnumField::new('status', '任务状态');
        $statusField->setEnumCases(TaskStatus::cases());
        $statusField->setHelp('任务当前的执行状态');
        $statusField->setColumns(4);
        yield $statusField;

        $targetTypeField = EnumField::new('targetType', '目标类型');
        $targetTypeField->setEnumCases(TaskTargetType::cases());
        $targetTypeField->setHelp('任务执行的目标设备范围');
        $targetTypeField->setColumns(4);
        yield $targetTypeField;

        yield AssociationField::new('script', '关联脚本')
            ->setHelp('要执行的脚本')
            ->setColumns(6)
        ;

        yield AssociationField::new('targetGroup', '目标设备组')
            ->setHelp('当目标类型为"设备分组"时选择的设备组')
            ->setColumns(6)
            ->hideOnIndex()
        ;

        yield IntegerField::new('priority', '优先级')
            ->setHelp('数值越大优先级越高')
            ->setColumns(3)
        ;

        yield IntegerField::new('maxRetries', '最大重试次数')
            ->setHelp('执行失败时的重试次数')
            ->setColumns(3)
        ;

        yield IntegerField::new('timeout', '超时时间(秒)')
            ->setHelp('单个设备上的执行超时时间')
            ->setColumns(3)
        ;

        yield IntegerField::new('currentRetries', '当前重试次数')
            ->setHelp('已经尝试的重试次数')
            ->setColumns(3)
            ->onlyOnDetail()
        ;

        yield BooleanField::new('enabled', '是否启用')
            ->setHelp('控制任务是否可用')
        ;

        yield DateTimeField::new('scheduledTime', '计划执行时间')
            ->setFormat('yyyy-MM-dd HH:mm:ss')
            ->setHelp('定时任务的执行时间')
            ->hideOnIndex()
        ;

        yield TextField::new('cronExpression', 'Cron表达式')
            ->setHelp('循环任务的时间规则')
            ->hideOnIndex()
            ->setColumns(6)
        ;

        yield CodeEditorField::new('parameters', '任务参数')
            ->setLanguage('javascript')
            ->setHelp('JSON格式的任务参数')
            ->hideOnIndex()
            ->setNumOfRows(8)
        ;

        yield CodeEditorField::new('targetDeviceIds', '目标设备ID列表')
            ->setLanguage('javascript')
            ->setHelp('指定设备模式下的设备ID数组')
            ->hideOnIndex()
            ->setNumOfRows(6)
        ;

        // 统计字段
        yield IntegerField::new('totalDevices', '总设备数')
            ->setHelp('任务涉及的总设备数量')
            ->onlyOnDetail()
        ;

        yield IntegerField::new('successDevices', '成功设备数')
            ->setHelp('成功执行的设备数量')
            ->onlyOnDetail()
        ;

        yield IntegerField::new('failedDevices', '失败设备数')
            ->setHelp('执行失败的设备数量')
            ->onlyOnDetail()
        ;

        // 虚拟字段 - 执行进度
        yield TextField::new('executionProgress', '执行进度')
            ->setVirtual(true)
            ->setHelp('任务执行完成的百分比')
            ->onlyOnDetail()
            ->formatValue(function ($value, Task $entity) {
                $total = $entity->getTotalDevices();
                if ($total <= 0) {
                    return '0%';
                }
                $completed = $entity->getSuccessDevices() + $entity->getFailedDevices();
                $percentage = round(($completed / $total) * 100, 1);

                return $percentage . '%';
            })
        ;

        // 时间字段
        yield DateTimeField::new('startTime', '开始时间')
            ->setFormat('yyyy-MM-dd HH:mm:ss')
            ->setHelp('任务实际开始执行的时间')
            ->onlyOnDetail()
        ;

        yield DateTimeField::new('endTime', '结束时间')
            ->setFormat('yyyy-MM-dd HH:mm:ss')
            ->setHelp('任务执行完成的时间')
            ->onlyOnDetail()
        ;

        // 虚拟字段 - 执行时长
        yield TextField::new('executionDuration', '执行时长')
            ->setVirtual(true)
            ->setHelp('任务执行的总耗时')
            ->onlyOnDetail()
            ->formatValue(function ($value, Task $entity) {
                $start = $entity->getStartTime();
                $end = $entity->getEndTime();

                if (null === $start) {
                    return '未开始';
                }

                if (null === $end) {
                    $duration = new \DateTimeImmutable()->getTimestamp() - $start->getTimestamp();

                    return $duration . '秒 (进行中)';
                }

                $duration = $end->getTimestamp() - $start->getTimestamp();

                return $duration . '秒';
            })
        ;

        yield DateTimeField::new('lastExecutionTime', '最后执行时间')
            ->setFormat('yyyy-MM-dd HH:mm:ss')
            ->setHelp('最近一次执行的时间')
            ->onlyOnDetail()
        ;

        yield DateTimeField::new('nextExecutionTime', '下次执行时间')
            ->setFormat('yyyy-MM-dd HH:mm:ss')
            ->setHelp('下一次计划执行的时间（循环任务）')
            ->onlyOnDetail()
        ;

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

        // 虚拟字段 - 执行记录数量
        yield IntegerField::new('executionRecordsCount', '执行记录数')
            ->setVirtual(true)
            ->setHelp('任务的执行记录总数')
            ->onlyOnDetail()
            ->formatValue(function ($value, Task $entity) {
                return $entity->getExecutionRecords()->count();
            })
        ;
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add('name')
            ->add('type')
            ->add('status')
            ->add('targetType')
            ->add('script')
            ->add('enabled')
            ->add('scheduledTime')
            ->add('createdAt')
            ->add('updatedAt')
        ;
    }
}
